<?php

namespace Tests\Feature;

use App\Enums\EmployeeDocumentType;
use App\Enums\EmploymentStatus;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\JopTitle;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Controllers\EmployeeReportController;
use Tests\TestCase;

/**
 * تقاريرُ الموظفين (§11.2) التي كانت فهرساً ساكناً بروابط فارغة.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `EmployeeReportController::index` كان يعرض قالب `employee/report/index`
 * بلا استعلامٍ واحد، وروابطُ «عرض» فيه `href=""` عن تحصيلٍ لا عن موظفين.
 * ومصفوفةُ §11.2 تطلب تسعة تقارير على نطاق الرؤية، بصلاحياتٍ مختلفة:
 * القائمة `employees_view_profile`، والعقود `salaries_contracts_view_all`،
 * والوثائق `employees_documents_view`.
 *
 * ويُقاس هنا: المسارات، والحراسة، والنطاق، وأن الاستعلام يفرّق النشط من
 * المستقيل، والمتحقّق من المغادر، والمنتهي من الذي ينتهي، وأن البيانات
 * البنكية لا تتسرّب في القوالب. التصديرُ الموحَّد مؤجَّل إلى الجولة 3.4.
 */
class EmployeeManagementReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('departments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('jop_titles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->unsignedBigInteger('department_id')->nullable();
            $t->timestamps();
        });

        Schema::create('branches', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->string('employee_code', 64)->nullable();
            $t->tinyInteger('employee_type')->nullable();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('direct_manager_id')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->date('hire_date')->nullable();
            $t->date('termination_date')->nullable();
            $t->date('last_working_day')->nullable();
            $t->text('termination_reason')->nullable();
            $t->string('iban')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('contracts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('code')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('type', 40)->default('other');
            $t->string('title')->nullable();
            $t->string('number', 100)->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('issuing_authority')->nullable();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->timestamps();
        });

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    private function controller(?array $onlyIds = null): EmployeeReportController
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

    private function employee(array $attributes = []): Employee
    {
        return Employee::create($attributes + [
            'first_name' => 'سالم',
            'email' => 'e'.uniqid('', true).'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'employee_type' => 1,
        ]);
    }

    private function namedRoute(string $name): \Illuminate\Routing\Route
    {
        $found = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === $name);

        $this->assertNotNull($found, "المسار {$name} غير مسجَّل");

        return $found;
    }

    // ═══════════════ المسارات والحراسة ═══════════════

    public function test_every_catalog_report_has_a_named_route(): void
    {
        foreach (EmployeeReportController::catalog() as $tile) {
            $this->assertTrue(
                Route::has($tile['route']),
                "المسار {$tile['route']} غير مسجَّل"
            );
        }
    }

    public function test_each_action_is_guarded_by_the_catalog_permission(): void
    {
        $actions = [
            'employees' => 'employees_view_profile',
            'byDepartment' => 'hr_reports_view',
            'byJobTitle' => 'hr_reports_view',
            'byStatus' => 'hr_reports_view',
            'turnover' => 'hr_reports_view',
            'contracts' => 'salaries_contracts_view_all',
            'documents' => 'employees_documents_view',
            'statistics' => 'hr_dashboard_view',
            'organization' => 'hr_org_chart_view',
        ];

        foreach (EmployeeReportController::catalog() as $tile) {
            $route = $this->namedRoute($tile['route']);
            $action = class_basename($route->getActionName());
            $method = explode('@', $action)[1] ?? '';

            $this->assertArrayHasKey($method, $actions, "لا صلاحية معلنة للفعل {$method}");
            $this->assertSame(
                $actions[$method],
                $tile['permission'],
                "بلاطة «{$tile['title']}» صلاحيتُها تفارق حارس الفعل"
            );

            $guards = array_values(array_filter(
                $route->gatherMiddleware(),
                fn ($m) => is_string($m) && str_starts_with($m, 'permission.role:')
            ));

            $this->assertContains(
                'permission.role:'.$tile['permission'],
                $guards,
                "{$tile['route']} بلا حارس {$tile['permission']}"
            );
        }
    }

    public function test_the_report_routes_sit_behind_the_reports_plan(): void
    {
        $middleware = $this->namedRoute('hr.reports.employees')->gatherMiddleware();

        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => is_string($m) && str_contains($m, 'plan:') && str_contains($m, 'hr_reports')),
            'مسارات تقارير الموظفين خارج سور باقة hr_reports'
        );
    }

    public function test_the_controller_applies_the_employee_scope_trait(): void
    {
        $this->assertContains(
            ScopesToAccessibleEmployees::class,
            array_values(class_uses_recursive(EmployeeReportController::class))
        );
    }

    public function test_employee_queries_go_through_the_scope(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(EmployeeReportController::class))->getFileName()
        );

        $this->assertGreaterThanOrEqual(
            3,
            preg_match_all('/\$this->scopeToEmployees\(/', $source)
        );

        $this->assertStringContainsString('scopedEmployeeOptions(', $source);
        $this->assertStringNotContainsString('Employee::all()', $source);
        $this->assertStringNotContainsString('Employee::get()', $source);
    }

    public function test_the_dead_collection_hub_is_gone(): void
    {
        $hub = (string) file_get_contents(module_path('HR', 'resources/views/employee/report/index.blade.php'));

        $this->assertStringNotContainsString('href=""', $hub);
        $this->assertStringNotContainsString('تقرير التحصيل', $hub);
        $this->assertStringContainsString('$tiles', $hub);
    }

    public function test_the_main_reports_index_lists_the_employee_catalog(): void
    {
        $index = (string) file_get_contents(module_path('HR', 'resources/views/reports/index.blade.php'));

        $this->assertStringContainsString('EmployeeReportController::catalog()', $index);
        $this->assertStringContainsString('الموظفون', $index);
    }

    public function test_views_do_not_expose_bank_fields(): void
    {
        $dir = module_path('HR', 'resources/views/reports/employees');

        foreach (glob($dir.'/*.blade.php') ?: [] as $file) {
            $html = (string) file_get_contents($file);
            $this->assertStringNotContainsString('iban', $html, basename($file).' يذكر الآيبان');
            $this->assertStringNotContainsString('bank_name', $html, basename($file).' يذكر البنك');
            $this->assertStringNotContainsString('bank_account', $html, basename($file).' يذكر الحساب البنكي');
        }
    }

    // ═══════════════ السلوك ═══════════════

    public function test_the_list_is_limited_to_the_visible_employees(): void
    {
        $visible = $this->employee(['first_name' => 'ظاهر']);
        $this->employee(['first_name' => 'مخفي']);

        $view = $this->controller([$visible->id])->employees(Request::create('/x', 'GET'));
        $rows = $view->getData()['employees'];

        $this->assertCount(1, $rows);
        $this->assertSame('ظاهر', $rows->first()->first_name);
    }

    public function test_archived_employees_do_not_appear_in_the_list(): void
    {
        $kept = $this->employee(['first_name' => 'باق']);
        $gone = $this->employee(['first_name' => 'مؤرشف']);
        $gone->delete();

        $view = $this->controller()->employees(Request::create('/x', 'GET'));
        $names = $view->getData()['employees']->pluck('first_name')->all();

        $this->assertContains('باق', $names);
        $this->assertNotContains('مؤرشف', $names);
        $this->assertTrue($kept->exists);
    }

    public function test_the_list_filters_by_employment_status(): void
    {
        $this->employee(['first_name' => 'عامل', 'status' => EmploymentStatus::ACTIVE->value]);
        $this->employee(['first_name' => 'مستقيل', 'status' => EmploymentStatus::RESIGNED->value]);

        $view = $this->controller()->employees(Request::create('/x', 'GET', [
            'status' => EmploymentStatus::RESIGNED->value,
        ]));

        $rows = $view->getData()['employees'];
        $this->assertCount(1, $rows);
        $this->assertSame('مستقيل', $rows->first()->first_name);
    }

    public function test_employees_are_grouped_by_department(): void
    {
        $sales = Department::create(['name' => 'المبيعات']);
        $hr = Department::create(['name' => 'الموارد']);
        $this->employee(['first_name' => 'أ', 'department_id' => $sales->id]);
        $this->employee(['first_name' => 'ب', 'department_id' => $sales->id]);
        $this->employee(['first_name' => 'ج', 'department_id' => $hr->id]);

        $view = $this->controller()->byDepartment(Request::create('/x', 'GET'));
        $groups = $view->getData()['groups']->keyBy('name');

        $this->assertSame(2, $groups['المبيعات']['count']);
        $this->assertSame(1, $groups['الموارد']['count']);
    }

    public function test_employees_are_grouped_by_job_title(): void
    {
        $dev = JopTitle::create(['name' => 'مطوّر']);
        $this->employee(['job_title_id' => $dev->id]);
        $this->employee(['job_title_id' => $dev->id]);
        $this->employee(['job_title_id' => null]);

        $view = $this->controller()->byJobTitle(Request::create('/x', 'GET'));
        $groups = $view->getData()['groups']->keyBy('name');

        $this->assertSame(2, $groups['مطوّر']['count']);
        $this->assertSame(1, $groups['بدون مسمّى']['count']);
    }

    public function test_status_report_uses_employment_states_not_a_boolean(): void
    {
        $this->employee(['status' => EmploymentStatus::PROBATION->value]);
        $this->employee(['status' => EmploymentStatus::TERMINATED->value]);

        $view = $this->controller()->byStatus(Request::create('/x', 'GET'));
        $labels = $view->getData()['groups']->map(fn ($g) => $g['status']->label())->all();

        $this->assertContains('تحت التجربة', $labels);
        $this->assertContains('منتهي الخدمة', $labels);
        $this->assertNotContains('غير نشط', $labels);
    }

    public function test_turnover_splits_hires_from_leavers(): void
    {
        $this->employee([
            'first_name' => 'جديد',
            'hire_date' => '2026-03-01',
        ]);
        $this->employee([
            'first_name' => 'غادر',
            'hire_date' => '2024-01-01',
            'termination_date' => '2026-04-01',
            'termination_reason' => 'استقالة',
            'status' => EmploymentStatus::RESIGNED->value,
        ]);
        $this->employee([
            'first_name' => 'قديم',
            'hire_date' => '2020-01-01',
        ]);

        $view = $this->controller()->turnover(Request::create('/x', 'GET', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
        ]));
        $data = $view->getData();

        $this->assertSame(['جديد'], $data['hires']->pluck('first_name')->all());
        $this->assertSame(['غادر'], $data['leavers']->pluck('first_name')->all());
        // المغادر كان على رأس العمل في 1 يناير، فيُحسب في البسط والمقام
        $this->assertSame(2, $data['headcountStart']);
    }

    public function test_contracts_report_separates_expired_from_expiring(): void
    {
        $soon = $this->employee(['first_name' => 'قريب']);
        $gone = $this->employee(['first_name' => 'منتهٍ']);
        $far = $this->employee(['first_name' => 'بعيد']);

        Contract::create([
            'employee_id' => $soon->id,
            'code' => 'C-SOON',
            'end_date' => now()->addDays(10)->toDateString(),
        ]);
        Contract::create([
            'employee_id' => $gone->id,
            'code' => 'C-GONE',
            'end_date' => now()->subDays(4)->toDateString(),
        ]);
        Contract::create([
            'employee_id' => $far->id,
            'code' => 'C-FAR',
            'end_date' => now()->addDays(200)->toDateString(),
        ]);

        $expiring = $this->controller()->contracts(Request::create('/x', 'GET', [
            'status' => 'expiring',
        ]));
        $expired = $this->controller()->contracts(Request::create('/x', 'GET', [
            'status' => 'expired',
        ]));

        $this->assertSame(['C-SOON'], $expiring->getData()['contracts']->pluck('code')->all());
        $this->assertSame(['C-GONE'], $expired->getData()['contracts']->pluck('code')->all());
        $this->assertSame(30, $expiring->getData()['noticeDays']);
    }

    public function test_documents_report_uses_remaining_days_and_includes_expired(): void
    {
        $employee = $this->employee();

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'type' => EmployeeDocumentType::RESIDENCY->value,
            'number' => 'IQAMA-1',
            'expiry_date' => now()->addDays(8)->toDateString(),
        ]);
        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'type' => EmployeeDocumentType::PASSPORT->value,
            'number' => 'P-OLD',
            'expiry_date' => now()->subDays(2)->toDateString(),
        ]);
        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'type' => EmployeeDocumentType::CERTIFICATE->value,
            'number' => 'CERT',
            'expiry_date' => now()->addDays(400)->toDateString(),
        ]);

        $view = $this->controller()->documents(Request::create('/x', 'GET', [
            'within_days' => 30,
        ]));
        $numbers = $view->getData()['documents']->pluck('number')->all();

        $this->assertContains('IQAMA-1', $numbers);
        $this->assertContains('P-OLD', $numbers);
        $this->assertNotContains('CERT', $numbers);
    }

    public function test_statistics_compute_counts_and_averages(): void
    {
        $this->employee([
            'employee_type' => 1,
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'hire_date' => now()->subYears(10)->toDateString(),
        ]);
        $this->employee([
            'employee_type' => 2,
            'date_of_birth' => now()->subYears(40)->toDateString(),
            'hire_date' => now()->subYears(4)->toDateString(),
        ]);

        $data = $this->controller()->statistics(Request::create('/x', 'GET'))->getData();

        $this->assertCount(2, $data['employees']);
        $this->assertEqualsWithDelta(35.0, (float) $data['averageAge'], 1.5);
        $this->assertEqualsWithDelta(7.0, (float) $data['averageServiceYears'], 1.5);
        $this->assertSame(2, $data['byType']->count());
    }

    public function test_organization_tree_puts_orphans_at_the_root(): void
    {
        $boss = $this->employee(['first_name' => 'مدير']);
        $this->employee(['first_name' => 'مرؤوس', 'direct_manager_id' => $boss->id]);
        $this->employee(['first_name' => 'يتيم', 'direct_manager_id' => 999]);

        $data = $this->controller()->organization(Request::create('/x', 'GET'))->getData();
        $rootNames = $data['roots']->pluck('first_name')->all();

        $this->assertContains('مدير', $rootNames);
        $this->assertContains('يتيم', $rootNames);
        $this->assertNotContains('مرؤوس', $rootNames);
        $this->assertCount(1, $data['childrenOf']->get($boss->id) ?? $data['childrenOf']->get((string) $boss->id, collect()));
    }
}
