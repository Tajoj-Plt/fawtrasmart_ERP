<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\Employee;
use App\Models\PayrollBankFile;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollBankFileService;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ملف الرواتب البنكي (§15 TODO 4.5) من عمليات الصرف المصروفة والآيبان.
 *
 * الصرف النقدي من الخزينة لا يتغيّر. الملف تعليمات تحويل. بلا آيبان يُستبعد.
 */
class PayrollBankFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayrollSettings::forget();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('employee_code')->nullable();
            $t->string('email')->nullable();
            $t->string('bank_name')->nullable();
            $t->string('bank_account_holder')->nullable();
            $t->string('iban', 34)->nullable();
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('currency', 10)->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });

        Schema::create('payroll_bank_files', function (Blueprint $t) {
            $t->id();
            $t->date('period_from');
            $t->date('period_to');
            $t->string('format', 16)->default('csv');
            $t->string('bank_code', 8)->nullable();
            $t->string('filename');
            $t->unsignedInteger('row_count')->default(0);
            $t->unsignedInteger('skipped_count')->default(0);
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->json('payload')->nullable();
            $t->unsignedBigInteger('generated_by')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('send_salary_slips')->default(false);
            $t->string('default_template')->default('ar');
            $t->boolean('auto_pay_approved_sales_periods')->default(true);
            $t->string('bank_export_format', 16)->default('csv');
            $t->string('bank_export_bank_code', 8)->nullable();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->timestamps();
        });

        DB::table('payroll_settings')->insert([
            'bank_export_format' => 'csv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PayrollSettings::forget();

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    public function test_routes_use_the_new_permission_and_payroll_plan(): void
    {
        $this->assertTrue(Route::has('hr.bank-files.index'));
        $this->assertTrue(Route::has('hr.bank-files.download'));
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'hr.bank-files.store');

        $this->assertContains('permission.role:salaries_payroll_bank_export', $route->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $route->gatherMiddleware());
        $this->assertContains(
            'salaries_payroll_bank_export',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_disbursed_run_with_iban_is_included(): void
    {
        $run = $this->makeRun(3200, 'SA0380000000608010167519', PayrollRun::STATUS_DISBURSED);

        $preview = app(PayrollBankFileService::class)->preview(collect([$run->load('employee')]));

        $this->assertCount(1, $preview['included']);
        $this->assertSame('SA0380000000608010167519', $preview['included'][0]['iban']);
        $this->assertEquals(3200, $preview['total']);
        $this->assertSame([], $preview['skipped']);
    }

    public function test_missing_iban_is_skipped(): void
    {
        $run = $this->makeRun(3200, null, PayrollRun::STATUS_DISBURSED);

        $preview = app(PayrollBankFileService::class)->preview(collect([$run->load('employee')]));

        $this->assertSame([], $preview['included']);
        $this->assertSame('لا آيبان', $preview['skipped'][0]['reason']);
    }

    public function test_short_iban_is_skipped(): void
    {
        $run = $this->makeRun(3200, 'SA038000', PayrollRun::STATUS_DISBURSED);

        $preview = app(PayrollBankFileService::class)->preview(collect([$run->load('employee')]));

        $this->assertSame([], $preview['included']);
        $this->assertSame('آيبان غير مكتمل', $preview['skipped'][0]['reason']);
    }

    public function test_pending_run_is_not_generated_from_this_helper(): void
    {
        $this->makeRun(3200, 'SA0380000000608010167519', PayrollRun::STATUS_PENDING);

        $runs = PayrollRun::query()->where('status', PayrollRun::STATUS_DISBURSED)->with('employee')->get();
        $preview = app(PayrollBankFileService::class)->preview($runs);

        $this->assertSame([], $preview['included']);
    }

    public function test_zero_net_is_skipped(): void
    {
        $run = $this->makeRun(0, 'SA0380000000608010167519', PayrollRun::STATUS_DISBURSED);

        $preview = app(PayrollBankFileService::class)->preview(collect([$run->load('employee')]));

        $this->assertSame('صافي الراتب صفر', $preview['skipped'][0]['reason']);
    }

    public function test_generate_persists_and_csv_contains_iban_and_amount(): void
    {
        $run = $this->makeRun(1500.5, 'SA0380000000608010167519', PayrollRun::STATUS_DISBURSED);

        $file = app(PayrollBankFileService::class)->generate(
            collect([$run->load('employee')]),
            '2026-08-01',
            '2026-08-31',
            Auth::id()
        );

        $this->assertNotNull($file);
        $this->assertSame(1, $file->row_count);
        $this->assertEquals(1500.5, (float) $file->total_amount);

        $csv = app(PayrollBankFileService::class)->toCsv($file);
        $this->assertStringContainsString('SA0380000000608010167519', $csv);
        $this->assertStringContainsString('1500.5', $csv);
        $this->assertSame(PayrollBankFile::FORMAT_CSV, $file->format);
    }

    public function test_generate_returns_null_when_nothing_is_exportable(): void
    {
        $run = $this->makeRun(100, null, PayrollRun::STATUS_DISBURSED);

        $file = app(PayrollBankFileService::class)->generate(
            collect([$run->load('employee')]),
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertNull($file);
        $this->assertSame(0, PayrollBankFile::query()->count());
    }

    public function test_missing_tables_are_a_no_op(): void
    {
        Schema::drop('payroll_bank_files');
        $run = $this->makeRun(1000, 'SA0380000000608010167519', PayrollRun::STATUS_DISBURSED);

        $file = app(PayrollBankFileService::class)->generate(
            collect([$run->load('employee')]),
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertNull($file);
    }

    private function makeRun(float $net, ?string $iban, string $status): PayrollRun
    {
        $employee = Employee::create([
            'first_name' => 'سامي',
            'email' => 's'.uniqid('', true).'@example.test',
            'employee_code' => 'E-1',
            'iban' => $iban,
            'bank_name' => 'مصرف الراجحي',
        ]);

        return PayrollRun::create([
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'net_salary' => $net,
            'status' => $status,
        ]);
    }
}
