<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Reports\Http\Controllers\EmployeesController;
use Tests\TestCase;

/**
 * تقرير حضور موظفٍ واحد في وحدة Reports — كان قالباً ثابتاً بلا مسارٍ
 * ولا استعلام (مصفوفة "OFF" مكتوبة يدوياً)، صار يقرأ من `attendance_days`
 * فعلياً بنفس منطق `HRReportsController::attendanceByDepartment` (TODO 11).
 *
 * الجداولُ تُبنى يدوياً كما في بقية اختبارات الحضور لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class ReportsEmployeesAttendanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('email')->unique();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->string('status')->default('present');
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->unsignedSmallInteger('overtime_minutes')->default(0);
            $t->timestamps();

            $t->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('role_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });

        // مستخدمٌ بلا دور وبلا ربطٍ بموظف: ScopesToAccessibleEmployees
        // تعامله "ليس موظفاً" فلا تقيّده — كمسؤول نظامٍ حقيقي.
        $this->actingAs(User::create([
            'name' => 'مسؤول', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'x',
        ]));
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'first_name' => 'سالم',
            'middle_name' => 'محمد',
            'email' => 'e'.uniqid().'@example.test',
        ], $attributes));
    }

    private function controller(): EmployeesController
    {
        return new class extends EmployeesController
        {
            public function __construct() {}
        };
    }

    public function test_searching_by_id_finds_the_employee_and_summarizes_their_attendance(): void
    {
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            ['employee_id' => $employee->id, 'attendance_date' => '2026-01-01', 'status' => 'present', 'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00', 'late_minutes' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $employee->id, 'attendance_date' => '2026-01-02', 'status' => 'late', 'check_in_time' => '08:30:00', 'check_out_time' => '16:00:00', 'late_minutes' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $employee->id, 'attendance_date' => '2026-01-03', 'status' => 'absent', 'check_in_time' => null, 'check_out_time' => null, 'late_minutes' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $request = Request::create('/', 'GET', [
            'employee' => (string) $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $view = $this->controller()->attendance($request);
        $data = $view->getData();

        $this->assertNotNull($data['employee']);
        $this->assertTrue($data['employee']->is($employee));
        $this->assertSame(3, $data['attendanceData']->count());
        $this->assertSame(1, $data['totals']['total_present']);
        $this->assertSame(1, $data['totals']['total_late']);
        $this->assertSame(1, $data['totals']['total_absent']);
    }

    public function test_searching_by_name_finds_the_employee(): void
    {
        $employee = $this->employee(['first_name' => 'فاطمة الزهراء']);

        $request = Request::create('/', 'GET', ['employee' => 'الزهراء']);
        $view = $this->controller()->attendance($request);

        $this->assertTrue($view->getData()['employee']->is($employee));
    }

    public function test_searching_by_email_finds_the_employee(): void
    {
        $employee = $this->employee(['email' => 'unique-search-target@example.test']);

        $request = Request::create('/', 'GET', ['employee' => 'unique-search-target']);
        $view = $this->controller()->attendance($request);

        $this->assertTrue($view->getData()['employee']->is($employee));
    }

    public function test_no_match_returns_a_null_employee_not_an_error(): void
    {
        $request = Request::create('/', 'GET', ['employee' => 'لا أحد بهذا الاسم إطلاقاً']);
        $view = $this->controller()->attendance($request);

        $this->assertNull($view->getData()['employee']);
        $this->assertSame(0, $view->getData()['attendanceData']->count());
    }

    public function test_overtime_minutes_are_summed_across_the_period(): void
    {
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            ['employee_id' => $employee->id, 'attendance_date' => '2026-01-01', 'status' => 'present', 'overtime_minutes' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => $employee->id, 'attendance_date' => '2026-01-02', 'status' => 'present', 'overtime_minutes' => 45, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $request = Request::create('/', 'GET', [
            'employee' => (string) $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $totals = $this->controller()->attendance($request)->getData()['totals'];

        $this->assertSame(75, $totals['total_overtime_minutes']);
    }

    /** خارج الفترة المطلوبة لا يُحتسب. */
    public function test_days_outside_the_date_range_are_excluded(): void
    {
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id, 'attendance_date' => '2025-06-01', 'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/', 'GET', [
            'employee' => (string) $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $data = $this->controller()->attendance($request)->getData();

        $this->assertSame(0, $data['attendanceData']->count());
    }

    // ═══════════════ نطاق الموظفين ═══════════════

    public function test_the_controller_applies_the_employee_scope(): void
    {
        $this->assertContains(
            \Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees::class,
            array_values(class_uses_recursive(EmployeesController::class))
        );
    }

    public function test_attendance_calls_the_scope(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(EmployeesController::class))->getFileName()
        );

        $this->assertStringContainsString('$this->scopeToEmployees(', $source);
    }
}
