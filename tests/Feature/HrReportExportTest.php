<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\HR\Http\Controllers\Concerns\ExportsHrReports;
use Modules\HR\Http\Controllers\EmployeeReportController;
use Modules\HR\Http\Controllers\HRReportsController;
use Modules\HR\Http\Controllers\PayrollReportController;
use Modules\HR\Support\HrReportExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * تصدير Excel/PDF الموحَّد لتقارير الوحدة (§15 TODO 3.4).
 *
 * الملف يُبنى من استعلام الشاشة نفسه بعد النطاق، لا من الجدول المعروض.
 * إحصائيات الموظفين بلا تصدير وفق §11.2.
 */
class HrReportExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('departments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('branches', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('jop_titles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('employee_code')->nullable();
            $t->string('email')->unique();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->unsignedBigInteger('direct_manager_id')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->tinyInteger('employee_type')->nullable();
            $t->date('hire_date')->nullable();
            $t->string('iban')->nullable();
            $t->string('bank_name')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->decimal('total_additions', 15, 2)->default(0);
            $t->decimal('total_deductions', 15, 2)->default(0);
            $t->decimal('total_advances_deducted', 15, 2)->default(0);
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->string('trigger_source', 20)->default('manual');
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    private function employeesController(?array $onlyIds = null): EmployeeReportController
    {
        return new class($onlyIds) extends EmployeeReportController
        {
            public function __construct(private ?array $onlyIds) {}

            protected function accessibleEmployeeIds(): ?array
            {
                return $this->onlyIds;
            }
        };
    }

    private function payrollController(?array $onlyIds = null): PayrollReportController
    {
        return new class($onlyIds) extends PayrollReportController
        {
            public function __construct(private ?array $onlyIds) {}

            protected function accessibleEmployeeIds(): ?array
            {
                return $this->onlyIds;
            }
        };
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create($attributes + [
            'first_name' => 'سالم',
            'email' => 'e'.uniqid('', true).'@example.test',
            'iban' => 'SA0000000000000000000000',
            'bank_name' => 'بنك سري',
        ]);
    }

    public function test_the_three_reporting_controllers_use_the_shared_exporter(): void
    {
        foreach ([
            EmployeeReportController::class,
            PayrollReportController::class,
            HRReportsController::class,
        ] as $controller) {
            $this->assertContains(
                ExportsHrReports::class,
                array_values(class_uses_recursive($controller)),
                class_basename($controller)
            );
        }
    }

    public function test_unknown_format_is_ignored(): void
    {
        $this->assertNull(HrReportExporter::download('csv', 'عنوان', ['أ'], [['ب']], 'x'));
    }

    public function test_employee_list_excel_is_scoped_and_omits_bank_fields(): void
    {
        $visible = $this->employee(['first_name' => 'ظاهر', 'employee_code' => 'E-1']);
        $this->employee(['first_name' => 'مخفي', 'employee_code' => 'E-9']);

        $response = $this->employeesController([$visible->id])
            ->employees(Request::create('/x', 'GET', ['export' => 'xlsx']));

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $path = $response->getFile()->getPathname();
        $xml = $this->xlsxSharedStrings($path);

        $this->assertStringContainsString('ظاهر', $xml);
        $this->assertStringNotContainsString('مخفي', $xml);
        $this->assertStringNotContainsString('SA0000000000000000000000', $xml);
        $this->assertStringNotContainsString('بنك سري', $xml);
        $this->assertStringNotContainsString('iban', strtolower($xml));
    }

    public function test_payroll_summary_excel_is_scoped(): void
    {
        $visible = $this->employee(['first_name' => 'ظاهر']);
        $hidden = $this->employee(['first_name' => 'مخفي']);

        PayrollRun::create([
            'employee_id' => $visible->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => '2026-08-31',
            'net_salary' => 1000,
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);
        PayrollRun::create([
            'employee_id' => $hidden->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => '2026-08-31',
            'net_salary' => 9000,
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);

        $response = $this->payrollController([$visible->id])
            ->summary(Request::create('/x', 'GET', [
                'export' => 'xlsx',
                'from_date' => '2026-01-01',
                'to_date' => '2026-12-31',
            ]));

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $xml = $this->xlsxSharedStrings($response->getFile()->getPathname());
        $this->assertStringContainsString('ظاهر', $xml);
        $this->assertStringNotContainsString('مخفي', $xml);
    }

    public function test_statistics_are_not_exported(): void
    {
        $this->employee();

        $response = $this->employeesController()->statistics(
            Request::create('/x', 'GET', ['export' => 'xlsx'])
        );

        $this->assertInstanceOf(View::class, $response);
    }

    public function test_filter_actions_offer_excel_and_pdf_except_statistics(): void
    {
        $actions = (string) file_get_contents(module_path('HR', 'resources/views/reports/employees/_filter_actions.blade.php'));
        $this->assertStringContainsString('name="export" value="xlsx"', $actions);
        $this->assertStringContainsString('name="export" value="pdf"', $actions);

        $stats = (string) file_get_contents(module_path('HR', 'resources/views/reports/employees/statistics.blade.php'));
        $this->assertStringContainsString("'showExport' => false", $stats);
    }

    public function test_export_row_builders_do_not_mention_bank_columns(): void
    {
        foreach ([
            EmployeeReportController::class,
            PayrollReportController::class,
            HrReportExporter::class,
        ] as $class) {
            $source = (string) file_get_contents((new \ReflectionClass($class))->getFileName());
            $this->assertStringNotContainsString('iban', $source, class_basename($class));
            $this->assertStringNotContainsString('bank_name', $source, class_basename($class));
        }
    }

    private function xlsxSharedStrings(string $path): string
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'تعذّر فتح ملف الإكسل');
        $xml = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        return $xml;
    }
}
