<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use App\Models\AttendanceSheets;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\AttendanceSheetsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * دفاترُ الحضور — الملخّصُ التجميعي.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن أرقامَ الملخّص تُحسَب على **فترة الدفتر كاملة**، لا على صفحةِ
 * الجدول. كان القالبُ يبنيها من `$attendanceDays` — وهي مُرقَّمةٌ بخمسين
 * صفّاً — فدفترٌ فيه ستّون يوماً يقول «إجمالي الأيام: 50»، وتُحسَب نسبةُ
 * الحضور على الخمسين التي وقعت في الصفحة وحدها.
 *
 * وهذه شاشةٌ تُراجَع قبل إغلاق الشهر ويُبنى عليها حسمُ الراتب، فالرقمُ
 * فيها إمّا صحيحٌ أو لا معنى له.
 */
class AttendanceSheetSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_sheets', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->date('from_date');
            $t->date('to_date');
            $t->string('status')->nullable();
            $t->boolean('use_rules')->default(false);
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->unsignedBigInteger('shift_id')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_sheets_employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('attendance_sheets_id');
            $t->unsignedBigInteger('employee_id');
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('attendance_sheets_id')->nullable();
            $t->date('attendance_date');
            $t->string('status')->default('present');
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->integer('late_minutes')->default(0);
            $t->integer('early_leave_minutes')->default(0);
            $t->integer('overtime_minutes')->default(0);
            $t->unsignedBigInteger('deduction_id')->nullable();
            $t->timestamps();
        });

        Schema::create('deductions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('deduction_type')->nullable();
            $t->string('status')->nullable();
            $t->date('date')->nullable();
            $t->timestamps();
        });

        // جداولُ القوائم التي تقرؤها `index()` لبناء المُرشِّحات
        foreach (['departments', 'branches', 'jop_titles'] as $lookup) {
            Schema::create($lookup, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name')->nullable();
                $t->timestamps();
            });
        }

        Schema::create('logs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('type')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->string('type_log')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        $this->actingAs(User::create([
            'name' => 'مشغّل', 'email' => 'sheets@example.test', 'employee_id' => null,
        ]));
    }

    private function controller(): AttendanceSheetsController
    {
        return new class extends AttendanceSheetsController
        {
            public function __construct() {}
        };
    }

    private function employee(string $name): Employee
    {
        return Employee::create([
            'first_name' => $name,
            'email' => 'e'.uniqid().'@example.test',
        ]);
    }

    /**
     * الملخّصُ يتجاوز حدَّ الصفحة.
     *
     * ستّون يوماً في الفترة والجدولُ يُرقَّم بخمسين — فالرقمُ الصحيح 60.
     */
    public function test_the_summary_counts_the_whole_period_not_one_page(): void
    {
        $employee = $this->employee('سالم');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-03-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach($employee->id);

        // ستّون يوماً: خمسون حاضرةً وعشرةٌ غائبة
        for ($i = 0; $i < 60; $i++) {
            AttendanceDays::create([
                'employee_id' => $employee->id,
                'attendance_date' => Carbon::parse('2026-01-01')->addDays($i)->toDateString(),
                'status' => $i < 50 ? 'present' : 'absent',
                'check_in_time' => $i < 50 ? '08:00:00' : null,
                'check_out_time' => $i < 50 ? '16:00:00' : null,
            ]);
        }

        $data = $this->controller()->show($sheet->id)->getData();

        $this->assertCount(50, $data['attendanceDays'], 'الجدولُ مُرقَّمٌ كما كان');

        $this->assertSame(60, $data['summary']['total'], 'الملخّصُ يعدّ الفترة كلَّها');
        $this->assertSame(50, $data['summary']['present']);
        $this->assertSame(10, $data['summary']['absent']);
        $this->assertSame(50 * 480, $data['summary']['worked_minutes']);
    }

    /** «إجمالي دقائق الخصميات» — ما تُفتح الشاشةُ لأجله قبل الإغلاق. */
    public function test_the_summary_reports_deducted_minutes_and_amounts(): void
    {
        $employee = $this->employee('سالم');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach($employee->id);

        $deductionId = DB::table('deductions')->insertGetId([
            'employee_id' => $employee->id, 'amount' => 42.50,
            'deduction_type' => 'late', 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        AttendanceDays::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'status' => 'late', 'check_in_time' => '08:40:00', 'check_out_time' => '16:00:00',
            'late_minutes' => 40, 'deduction_id' => $deductionId,
        ]);
        AttendanceDays::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-06',
            'status' => 'present', 'check_in_time' => '08:00:00', 'check_out_time' => '15:30:00',
            'early_leave_minutes' => 30,
        ]);

        $summary = $this->controller()->show($sheet->id)->getData()['summary'];

        $this->assertSame(40, $summary['late_minutes']);
        $this->assertSame(30, $summary['early_leave_minutes']);
        $this->assertSame(1, $summary['deducted_days']);
        $this->assertSame(42.5, $summary['deducted_amount']);
    }

    /**
     * مُرشِّحُ الجدول لا يُحرِّك الملخّص.
     *
     * كان التصفيةُ بـ«غائب» تجعل نسبةَ الحضور صفراً للجميع، لأن الملخّص
     * يُبنى من الصفوف المُصفّاة نفسِها.
     */
    public function test_filtering_the_table_does_not_move_the_summary(): void
    {
        $employee = $this->employee('سالم');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach($employee->id);

        foreach (['present', 'present', 'present', 'absent'] as $i => $status) {
            AttendanceDays::create([
                'employee_id' => $employee->id,
                'attendance_date' => '2026-01-0'.($i + 1),
                'status' => $status,
                'check_in_time' => $status === 'present' ? '08:00:00' : null,
                'check_out_time' => $status === 'present' ? '16:00:00' : null,
            ]);
        }

        request()->merge(['status_filter' => 'absent']);
        $data = $this->controller()->show($sheet->id)->getData();

        $this->assertCount(1, $data['attendanceDays'], 'الجدولُ مُصفّى');
        $this->assertSame(4, $data['summary']['total'], 'والملخّصُ على الفترة كلِّها');
        $this->assertSame(75.0, $data['employeeStats'][0]['attendance_rate']);
        $this->assertSame(25.0, $data['employeeStats'][0]['absence_rate']);
    }

    /** موظفٌ في الدفتر بلا يومٍ واحد يظهر بصفٍّ أصفار — وهو أوّلُ من يُبحَث عنه. */
    public function test_an_employee_with_no_days_still_gets_a_row(): void
    {
        $present = $this->employee('سالم');
        $missing = $this->employee('خالد');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach([$present->id, $missing->id]);

        AttendanceDays::create([
            'employee_id' => $present->id, 'attendance_date' => '2026-01-05',
            'status' => 'present', 'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00',
        ]);

        $stats = $this->controller()->show($sheet->id)->getData()['employeeStats'];

        $this->assertCount(2, $stats);

        $missingRow = collect($stats)->firstWhere('id', $missing->id);
        $this->assertSame(0, $missingRow['total']);
        $this->assertSame(0.0, $missingRow['attendance_rate']);
    }

    /**
     * النطاق: صاحبُ «دفاتر حضوره هو» لا يرى زملاءه في الدفتر.
     *
     * كان هذا المتحكّم وحدَه بين شاشات الحضور بلا تقييدِ نطاق: يفتح
     * الشاشةَ فيرى دفاترَ المنشأة كلَّها وفيها أيامُ الموظفين ونسبُ
     * غيابهم وما خُصم منهم — رغم أنّ صلاحيةَ عرض الدفاتر المنفصلة
     * صلاحيةٌ منفصلةٌ لا يملكها.
     */
    public function test_a_scoped_user_sees_only_their_own_rows_inside_a_shared_book(): void
    {
        $mine = $this->employee('سالم');
        $theirs = $this->employee('خالد');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach([$mine->id, $theirs->id]);

        foreach ([$mine, $theirs] as $employee) {
            AttendanceDays::create([
                'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
                'status' => 'present', 'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00',
            ]);
        }

        // مستخدمٌ مربوطٌ بـ«سالم» وبلا أيّ صلاحية نطاقٍ أوسع
        $this->actingAs(User::create([
            'name' => 'سالم', 'email' => 'salem@example.test', 'employee_id' => $mine->id,
        ]));

        $data = $this->controller()->show($sheet->id)->getData();

        $this->assertCount(1, $data['attendanceDays'], 'الصفوفُ مقيَّدة');
        $this->assertSame($mine->id, $data['attendanceDays'][0]->employee_id);

        $this->assertSame(1, $data['summary']['total'], 'والملخّصُ لا يَعُدّ أيامَ الزميل');
        $this->assertCount(1, $data['employeeStats'], 'ولا يُبنى له صفُّ إحصاء');
        $this->assertSame($mine->id, $data['employeeStats'][0]['id']);
        $this->assertCount(1, $data['scopedEmployees']);
    }

    /** ودفترٌ لا يخصّه فيه أحد لا يُفتح بتغيير الرقم في العنوان. */
    public function test_a_book_with_nobody_in_scope_is_refused(): void
    {
        $outsider = $this->employee('خالد');
        $viewer = $this->employee('سالم');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach($outsider->id);

        $this->actingAs(User::create([
            'name' => 'سالم', 'email' => 'salem2@example.test', 'employee_id' => $viewer->id,
        ]));

        $this->expectException(HttpException::class);
        $this->controller()->show($sheet->id);
    }

    /** والقائمةُ لا تعرض دفتراً ليس فيه أحدٌ من نطاقه. */
    public function test_the_list_hides_books_with_nobody_in_scope(): void
    {
        $outsider = $this->employee('خالد');
        $viewer = $this->employee('سالم');

        $mineSheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $mineSheet->employees()->attach($viewer->id);

        $theirsSheet = AttendanceSheets::create([
            'from_date' => '2026-02-01', 'to_date' => '2026-02-28', 'status' => 'active',
        ]);
        $theirsSheet->employees()->attach($outsider->id);

        $this->actingAs(User::create([
            'name' => 'سالم', 'email' => 'salem3@example.test', 'employee_id' => $viewer->id,
        ]));

        $sheets = $this->controller()->index(Request::create('/', 'GET'))->getData()['attendanceSheets'];

        $this->assertCount(1, $sheets);
        $this->assertSame($mineSheet->id, $sheets[0]->id);
    }

    /** ومن نطاقُه مفتوح يرى الدفترَ كاملاً كما كان. */
    public function test_an_unrestricted_user_still_sees_the_whole_book(): void
    {
        $a = $this->employee('سالم');
        $b = $this->employee('خالد');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach([$a->id, $b->id]);

        foreach ([$a, $b] as $employee) {
            AttendanceDays::create([
                'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
                'status' => 'present', 'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00',
            ]);
        }

        // المستخدمُ الافتراضي في setUp بلا `employee_id` — لا محورَ لتقييده
        $data = $this->controller()->show($sheet->id)->getData();

        $this->assertSame(2, $data['summary']['total']);
        $this->assertCount(2, $data['employeeStats']);
    }

    /** المتأخّرُ حاضرٌ: نسبةُ الالتزام تقابل الغياب لا التأخير. */
    public function test_a_late_day_counts_as_attendance_not_absence(): void
    {
        $employee = $this->employee('سالم');

        $sheet = AttendanceSheets::create([
            'from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'status' => 'active',
        ]);
        $sheet->employees()->attach($employee->id);

        foreach (['present', 'late'] as $i => $status) {
            AttendanceDays::create([
                'employee_id' => $employee->id,
                'attendance_date' => '2026-01-0'.($i + 1),
                'status' => $status,
                'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00',
            ]);
        }

        $row = $this->controller()->show($sheet->id)->getData()['employeeStats'][0];

        $this->assertSame(100.0, $row['attendance_rate'], 'المتأخّرُ حضر');
        $this->assertSame(0.0, $row['absence_rate']);
    }
}
