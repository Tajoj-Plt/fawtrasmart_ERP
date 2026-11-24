<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\EndOfServiceBenefit;
use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Services\EndOfServiceBenefitService;
use App\Services\EndOfServiceCalculator;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * مكافأة نهاية الخدمة (§15 TODO 4.3) من مدة الخدمة وسبب الإنهاء.
 */
class EndOfServiceBenefitTest extends TestCase
{
    private EndOfServiceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        PayrollSettings::forget();
        $this->calculator = new EndOfServiceCalculator;

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('email')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->date('hire_date')->nullable();
            $t->date('termination_date')->nullable();
            $t->date('last_working_day')->nullable();
            $t->text('termination_reason')->nullable();
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

        Schema::create('salary_component_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->unsignedTinyInteger('kind');
            $t->boolean('is_default')->default(false);
            $t->boolean('is_insurable')->default(false);
            $t->boolean('is_system')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedTinyInteger('status')->default(1);
            $t->timestamps();
        });

        Schema::create('salary_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->tinyInteger('type')->default(1);
            $t->unsignedBigInteger('component_type_id')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->unsignedBigInteger('salary_slips_id')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->timestamps();
        });

        Schema::create('end_of_service_benefits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('status', 20)->default('draft');
            $t->unsignedTinyInteger('employment_status')->nullable();
            $t->date('hire_date')->nullable();
            $t->date('last_working_day')->nullable();
            $t->decimal('years_of_service', 8, 4)->default(0);
            $t->decimal('monthly_wage', 15, 2)->default(0);
            $t->decimal('full_amount', 15, 2)->default(0);
            $t->decimal('fraction', 8, 4)->default(1);
            $t->decimal('payable_amount', 15, 2)->default(0);
            $t->unsignedBigInteger('component_type_id')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('eos_enabled')->default(true);
            $t->boolean('eos_include_default_allowances')->default(true);
            $t->decimal('eos_first_bracket_years', 6, 2)->default(5);
            $t->decimal('eos_first_months_per_year', 6, 2)->default(0.5);
            $t->decimal('eos_later_months_per_year', 6, 2)->default(1);
            $t->timestamps();
        });

        DB::table('payroll_settings')->insert([
            'eos_enabled' => true,
            'eos_include_default_allowances' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PayrollSettings::forget();
    }

    public function test_routes_use_eos_permission_and_payroll_plan(): void
    {
        $this->assertTrue(Route::has('hr.eos.index'));
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'hr.eos.approve');

        $this->assertContains('permission.role:salaries_eos_manage', $route->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $route->gatherMiddleware());
        $this->assertContains(
            'salaries_eos_manage',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_six_years_termination_is_three_and_a_half_months(): void
    {
        $rules = ['first_bracket_years' => 5.0, 'first_months_per_year' => 0.5, 'later_months_per_year' => 1.0];
        $full = $this->calculator->fullAmount(6.0, 10000, $rules);

        $this->assertEquals(35000, $full);
        $this->assertEquals(1.0, $this->calculator->payableFraction(6.0, false, EndOfServiceCalculator::defaultResignationBands()));
    }

    public function test_resignation_bands_follow_saudi_style_fractions(): void
    {
        $bands = EndOfServiceCalculator::defaultResignationBands();

        $this->assertEquals(0.0, $this->calculator->payableFraction(1.5, true, $bands));
        $this->assertEqualsWithDelta(1 / 3, $this->calculator->payableFraction(3.0, true, $bands), 0.0001);
        $this->assertEqualsWithDelta(2 / 3, $this->calculator->payableFraction(6.0, true, $bands), 0.0001);
        $this->assertEquals(1.0, $this->calculator->payableFraction(12.0, true, $bands));
    }

    public function test_terminated_employee_gets_a_draft_benefit(): void
    {
        $employee = $this->ended(EmploymentStatus::TERMINATED, '2018-08-01', '2024-08-01', 10000);

        $benefit = app(EndOfServiceBenefitService::class)->syncDraftFor($employee);

        $this->assertNotNull($benefit);
        $this->assertTrue($benefit->isDraft());
        $this->assertEqualsWithDelta(6.0, (float) $benefit->years_of_service, 0.03);
        $this->assertEquals(10000, (float) $benefit->monthly_wage);
        $this->assertEqualsWithDelta(35000, (float) $benefit->payable_amount, 200);
    }

    public function test_short_resignation_pays_nothing(): void
    {
        $employee = $this->ended(EmploymentStatus::RESIGNED, '2025-01-01', '2026-01-01', 8000);

        $benefit = app(EndOfServiceBenefitService::class)->syncDraftFor($employee);

        $this->assertEquals(0, (float) $benefit->payable_amount);
        $this->assertEquals(0, (float) $benefit->fraction);
    }

    public function test_wage_includes_default_catalog_allowances(): void
    {
        $employee = $this->ended(EmploymentStatus::TERMINATED, '2018-08-01', '2024-08-01', 8000);
        $housing = SalaryComponentType::create([
            'code' => SalaryComponentType::CODE_HOUSING,
            'name' => 'بدل سكن',
            'kind' => SalaryItem::TYPE_ADDITION,
            'is_default' => true,
            'status' => SalaryComponentType::STATUS_ACTIVE,
        ]);
        $slip = SalarySlip::query()->where('employee_id', $employee->id)->first();
        SalaryItem::create([
            'name' => 'سكن',
            'type' => SalaryItem::TYPE_ADDITION,
            'amount' => 2000,
            'salary_slips_id' => $slip->id,
            'component_type_id' => $housing->id,
        ]);

        $wage = app(EndOfServiceBenefitService::class)->monthlyWage($employee, true);

        $this->assertEquals(10000, $wage);
    }

    public function test_paid_benefit_is_not_overwritten(): void
    {
        $employee = $this->ended(EmploymentStatus::TERMINATED, '2018-08-01', '2024-08-01', 10000);
        $paid = app(EndOfServiceBenefitService::class)->syncDraftFor($employee);
        $paid->update(['status' => EndOfServiceBenefit::STATUS_PAID, 'payable_amount' => 1]);

        $again = app(EndOfServiceBenefitService::class)->syncDraftFor($employee->fresh());

        $this->assertTrue($again->isPaid());
        $this->assertEquals(1, (float) $again->payable_amount);
    }

    public function test_disabled_setting_skips_auto_draft(): void
    {
        DB::table('payroll_settings')->update(['eos_enabled' => false]);
        PayrollSettings::forget();
        $employee = $this->ended(EmploymentStatus::TERMINATED, '2018-08-01', '2024-08-01', 10000);

        $this->assertNull(app(EndOfServiceBenefitService::class)->syncDraftFor($employee));
        $this->assertNotNull(app(EndOfServiceBenefitService::class)->syncDraftFor($employee, false));
    }

    private function ended(EmploymentStatus $status, string $hire, string $end, float $basic): Employee
    {
        $employee = Employee::create([
            'first_name' => 'خالد',
            'email' => 'e'.uniqid('', true).'@example.test',
            'status' => $status->value,
            'hire_date' => $hire,
            'termination_date' => $end,
            'last_working_day' => $end,
            'termination_reason' => 'اختبار',
        ]);
        SalarySlip::create([
            'employee_id' => $employee->id,
            'basic_amount' => $basic,
            'status' => SalarySlip::STATUS_ACTIVE,
        ]);

        return $employee;
    }
}
