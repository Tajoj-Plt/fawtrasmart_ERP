<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\HRReportsController;
use Tests\TestCase;

/**
 * تقرير الحضور التفصيلي يقرأ من attendance_days — لا attendance_records.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `attendanceByDepartment` كان يُبنى على `attendance_records` (جدول
 * الجلسات) بعتبتين ثابتتين (09:00 تأخير، 17:00 انصراف مبكر)، فيغيب عنه
 * أيّ موظفٍ سُجِّل حضورُه يدوياً أو عبر طلب تصحيح معتمَد — لا سجلَّ جلسةٍ
 * له إطلاقاً — ويحسب التأخير بعتبةٍ خيالية بصرف النظر عن ورديته
 * الفعلية. صار الآن يقرأ `attendance_days` ويعرض ما حسبه المعالج فعلياً
 * (`status`, `late_minutes`, `early_leave_minutes`, `overtime_minutes`).
 *
 * الجداولُ تُبنى يدوياً كما في بقية اختبارات الحضور لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class HRReportsAttendanceByDepartmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('departments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('branches', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->tinyInteger('status')->default(1);
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
            $t->unsignedSmallInteger('early_leave_minutes')->default(0);
            $t->unsignedSmallInteger('overtime_minutes')->default(0);
            $t->timestamps();

            $t->unique(['employee_id', 'attendance_date']);
        });

        // موجودٌ لكن يجب ألّا يُقرأ منه هذا التقرير بعد الآن
        Schema::create('attendance_records', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->boolean('status')->default(false);
            $t->dateTime('login_time')->nullable();
            $t->dateTime('logout_time')->nullable();
            $t->timestamps();
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
        // تعامله "ليس موظفاً" فلا تقيّده — يرى كلَّ الموظفين، كما يفعل
        // مسؤولُ نظامٍ حقيقي بلا نطاق بيانات محدَّد.
        $this->actingAs(User::create([
            'name' => 'مسؤول', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'x',
        ]));
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
        ], $attributes));
    }

    private function controller(): HRReportsController
    {
        return new class extends HRReportsController
        {
            public function __construct() {}
        };
    }

    public function test_the_report_includes_a_day_recorded_manually_with_no_session_row_at_all(): void
    {
        // موظفٌ سُجِّل حضورُه يدوياً — لا مسحةَ باركود ولا سجلَّ جلسةٍ له إطلاقاً.
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => now()->format('Y-m-d'),
            'status' => 'present',
            'check_in_time' => '08:00:00',
            'check_out_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/', 'GET', ['fetch_data' => 1]);
        $response = $this->controller()->attendanceByDepartment($request);

        $data = $response->getData();

        $this->assertSame(1, $data['totals']['total_employees'], 'كان هذا الموظف غائباً عن التقرير كلياً قبل الإصلاح');
        $this->assertSame(1, $data['totals']['total_present']);
    }

    public function test_totals_use_the_real_computed_minutes_not_fixed_thresholds(): void
    {
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => now()->format('Y-m-d'),
            'status' => 'late',
            'check_in_time' => '08:05:00',
            'check_out_time' => '16:00:00',
            'late_minutes' => 5,
            'early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/', 'GET', ['fetch_data' => 1]);
        $response = $this->controller()->attendanceByDepartment($request);
        $totals = $response->getData()['totals'];

        // 08:05 كان سيُقرأ "في الوقت" بعتبة 09:00 الثابتة القديمة، أو
        // "متأخر" زوراً لو قورن بعتبةٍ لا تعرف وردية هذا الموظف الفعلية.
        // هنا: يُقرأ حسب status الحقيقية المحسوبة فعلياً من المعالج.
        $this->assertSame(1, $totals['total_late']);
        $this->assertSame(0, $totals['total_present']);
    }

    public function test_missing_checkout_is_reported(): void
    {
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => now()->format('Y-m-d'),
            'status' => 'present',
            'check_in_time' => '08:00:00',
            'check_out_time' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/', 'GET', ['fetch_data' => 1]);
        $response = $this->controller()->attendanceByDepartment($request);
        $totals = $response->getData()['totals'];

        $this->assertSame(1, $totals['total_missing_checkout'], 'حضورٌ بلا انصراف مسجَّل لم يكن يظهر في التقرير القديم إطلاقاً');
    }

    public function test_overtime_is_reported(): void
    {
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => now()->format('Y-m-d'),
            'status' => 'present',
            'check_in_time' => '08:00:00',
            'check_out_time' => '18:00:00',
            'overtime_minutes' => 120,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/', 'GET', ['fetch_data' => 1]);
        $response = $this->controller()->attendanceByDepartment($request);
        $totals = $response->getData()['totals'];

        $this->assertSame(1, $totals['total_overtime'], 'الإضافي لم يكن له عمودٌ في التقرير القديم إطلاقاً');
    }

    /** سجلٌّ في attendance_records القديم وحده لا يُحتسَب — المصدر توقّف عنه. */
    public function test_a_session_only_record_with_no_attendance_day_is_not_counted(): void
    {
        $employee = $this->employee();

        DB::table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'status' => true,
            'login_time' => now()->setTime(8, 0),
            'logout_time' => now()->setTime(16, 0),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/', 'GET', ['fetch_data' => 1]);
        $response = $this->controller()->attendanceByDepartment($request);
        $totals = $response->getData()['totals'];

        $this->assertSame(0, $totals['total_employees'], 'المصدر attendance_days فقط الآن — لا attendance_records');
    }
}
