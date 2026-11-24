<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\SocialInsuranceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * التأمينات الاجتماعية (§15 TODO 4.4): استقطاع موظف + مساهمة منشأة
 * من الأجر الخاضع (أساسي + بدلات `is_insurable`) مع سقف اختياري.
 */
class SocialInsuranceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('email')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });

        Schema::create('salary_component_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->unsignedTinyInteger('kind');
            $t->boolean('is_insurable')->default(false);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_system')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedTinyInteger('status')->default(1);
            $t->timestamps();
        });

        Schema::create('salary_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->tinyInteger('type')->nullable()->default(1);
            $t->unsignedBigInteger('component_type_id')->nullable();
            $t->string('origin', 32)->nullable();
            $t->tinyInteger('status')->nullable()->default(1);
            $t->tinyInteger('salary_item_value')->nullable()->default(1);
            $t->decimal('amount', 10)->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('salary_slips_id')->nullable();
            $t->timestamps();
        });

        Schema::create('social_insurance_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('enabled')->default(false);
            $t->decimal('employee_rate', 6, 3)->default(9.75);
            $t->decimal('employer_rate', 6, 3)->default(11.75);
            $t->decimal('wage_cap', 15, 2)->nullable();
            $t->unsignedBigInteger('employee_payable_account_id')->nullable();
            $t->unsignedBigInteger('employer_payable_account_id')->nullable();
            $t->unsignedBigInteger('expense_account_id')->nullable();
            $t->unsignedBigInteger('component_type_id')->nullable();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->timestamps();
        });

        $housing = SalaryComponentType::create([
            'code' => SalaryComponentType::CODE_HOUSING,
            'name' => 'بدل سكن',
            'kind' => SalaryItem::TYPE_ADDITION,
            'is_insurable' => true,
            'is_default' => true,
            'is_system' => true,
            'sort_order' => 10,
            'status' => SalaryComponentType::STATUS_ACTIVE,
        ]);
        SalaryComponentType::create([
            'code' => SalaryComponentType::CODE_NATURE_OF_WORK,
            'name' => 'بدل طبيعة عمل',
            'kind' => SalaryItem::TYPE_ADDITION,
            'is_insurable' => false,
            'is_default' => false,
            'is_system' => true,
            'sort_order' => 30,
            'status' => SalaryComponentType::STATUS_ACTIVE,
        ]);
        $gosi = SalaryComponentType::create([
            'code' => SalaryComponentType::CODE_SOCIAL_INSURANCE,
            'name' => 'تأمينات اجتماعية',
            'kind' => SalaryItem::TYPE_DEDUCTION,
            'is_insurable' => false,
            'is_default' => false,
            'is_system' => true,
            'sort_order' => 50,
            'status' => SalaryComponentType::STATUS_ACTIVE,
        ]);

        DB::table('social_insurance_settings')->insert([
            'enabled' => true,
            'employee_rate' => 9.75,
            'employer_rate' => 11.75,
            'wage_cap' => 45000,
            'component_type_id' => $gosi->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));

        $this->housingTypeId = $housing->id;
    }

    private int $housingTypeId;

    public function test_settings_route_uses_the_new_permission_and_payroll_plan(): void
    {
        $this->assertTrue(Route::has('hr.gosi.settings'));
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'hr.gosi.settings');

        $this->assertContains('permission.role:salaries_statutory_manage', $route->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $route->gatherMiddleware());
        $this->assertContains(
            'salaries_statutory_manage',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_rates_apply_to_basic_wage(): void
    {
        [$employee, $slip, $run] = $this->period(10000);

        $breakdown = app(SocialInsuranceService::class)->breakdown($slip);

        $this->assertEquals(10000, $breakdown['contributory_wage']);
        $this->assertEquals(975, $breakdown['employee_amount']);
        $this->assertEquals(1175, $breakdown['employer_amount']);
    }

    public function test_insurable_allowances_are_included_and_nature_of_work_is_not(): void
    {
        [$employee, $slip, $run] = $this->period(10000);
        $this->addition($slip, 'بدل سكن', 2000, $this->housingTypeId);
        $nature = SalaryComponentType::query()->where('code', SalaryComponentType::CODE_NATURE_OF_WORK)->first();
        $this->addition($slip, 'بدل طبيعة عمل', 3000, $nature->id);

        $breakdown = app(SocialInsuranceService::class)->breakdown($slip);

        $this->assertEquals(12000, $breakdown['wage_base']);
        $this->assertEquals(12000, $breakdown['contributory_wage']);
        $this->assertEquals(1170, $breakdown['employee_amount']);
        $this->assertEquals(1410, $breakdown['employer_amount']);
    }

    public function test_wage_cap_clips_the_contributory_base(): void
    {
        [$employee, $slip, $run] = $this->period(40000);
        $this->addition($slip, 'بدل سكن', 20000, $this->housingTypeId);

        $breakdown = app(SocialInsuranceService::class)->breakdown($slip);

        $this->assertEquals(60000, $breakdown['wage_base']);
        $this->assertEquals(45000, $breakdown['contributory_wage']);
        $this->assertEquals(4387.5, $breakdown['employee_amount']);
        $this->assertEquals(5287.5, $breakdown['employer_amount']);
    }

    public function test_disabled_setting_does_not_create_a_slip_item(): void
    {
        DB::table('social_insurance_settings')->update(['enabled' => false]);
        [$employee, $slip, $run] = $this->period(10000);

        $result = app(SocialInsuranceService::class)->syncForRun($run, $slip, $employee);

        $this->assertNull($result['item']);
        $this->assertSame(0, $slip->deductionItems()->count());
        $this->assertEquals(0, $result['employee_amount']);
        $this->assertEquals(0, $result['employer_amount']);
    }

    public function test_sync_writes_a_deduction_item_that_deduction_items_picks_up(): void
    {
        [$employee, $slip, $run] = $this->period(10000);

        $result = app(SocialInsuranceService::class)->syncForRun($run, $slip, $employee);
        $item = $result['item'];

        $this->assertNotNull($item);
        $this->assertSame(SalaryItem::ORIGIN_SOCIAL_INSURANCE, $item->origin);
        $this->assertSame(SalaryItem::TYPE_DEDUCTION, (int) $item->type);
        $this->assertEquals(975, (float) $item->amount);
        $this->assertTrue($slip->deductionItems()->where('id', $item->id)->exists());
        $this->assertEquals(1175, $result['employer_amount']);
    }

    public function test_zero_or_disabled_removes_a_previously_generated_item(): void
    {
        [$employee, $slip, $run] = $this->period(10000);
        app(SocialInsuranceService::class)->syncForRun($run, $slip, $employee);
        $this->assertSame(1, $slip->deductionItems()->count());

        DB::table('social_insurance_settings')->update(['enabled' => false]);
        $result = app(SocialInsuranceService::class)->syncForRun($run, $slip, $employee);

        $this->assertNull($result['item']);
        $this->assertSame(0, $slip->deductionItems()->count());
    }

    public function test_missing_tables_are_a_no_op(): void
    {
        Schema::drop('social_insurance_settings');
        [$employee, $slip, $run] = $this->period(10000);

        $result = app(SocialInsuranceService::class)->syncForRun($run, $slip, $employee);

        $this->assertNull($result['item']);
        $this->assertEquals(0, $result['employee_amount']);
        $this->assertSame(0, $slip->deductionItems()->count());
    }

    /** @return array{0: Employee, 1: SalarySlip, 2: PayrollRun} */
    private function period(float $basic): array
    {
        $employee = Employee::create([
            'first_name' => 'سامي',
            'email' => 's'.uniqid('', true).'@example.test',
        ]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'basic_amount' => $basic,
        ]);
        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'basic_amount' => $basic,
        ]);

        return [$employee, $slip, $run];
    }

    private function addition(SalarySlip $slip, string $name, float $amount, int $typeId): void
    {
        SalaryItem::create([
            'name' => $name,
            'type' => SalaryItem::TYPE_ADDITION,
            'status' => SalaryItem::STATUS_ACTIVE,
            'salary_item_value' => SalaryItem::VALUE_TYPE_AMOUNT,
            'amount' => $amount,
            'salary_slips_id' => $slip->id,
            'component_type_id' => $typeId,
        ]);
    }
}
