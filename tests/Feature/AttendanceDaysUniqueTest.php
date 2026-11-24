<?php

namespace Tests\Feature;

use App\Http\Requests\AttendanceDaysRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * قيدٌ فريدٌ حقيقي على يوم الموظف — لا فهرسٌ يُهمَل.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `idx_att_emp_date` كان موجوداً منذ أوّل هجرة لكنه غير Unique، فمصدرا
 * الحضور المتوازيان (نموذج الإدخال اليدوي وصفحة الباركود) قادران على
 * كتابة أكثر من صفٍّ واحد لنفس (employee_id, attendance_date) — وهو ما
 * يُبنى عليه لاحقاً حسابُ التأخير والغياب في الراتب.
 *
 * يُختبر هنا مستويان: قاعدة البيانات (قيدٌ حقيقي يرفض التكرار)، والنموذج
 * (`AttendanceDaysRequest` يعطي رسالةً مفهومة بدل استثناءٍ خام، ويتجاهل
 * الصفَّ نفسَه عند التعديل).
 *
 * الجداولُ تُبنى يدوياً كما في [EmploymentStatusTest] لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class AttendanceDaysUniqueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->string('status')->default('present');
            $t->unsignedTinyInteger('absence_type')->nullable();
            $t->integer('absence_balance')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['employee_id', 'attendance_date'], 'attendance_days_employee_date_unique');
        });
    }

    private function employeeId(): int
    {
        return (int) \DB::table('employees')->insertGetId([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ═══════════════ قاعدة البيانات ═══════════════

    public function test_the_database_rejects_a_duplicate_pair(): void
    {
        $employeeId = $this->employeeId();

        \DB::table('attendance_days')->insert([
            'employee_id' => $employeeId,
            'attendance_date' => '2026-01-01',
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('attendance_days')->insert([
            'employee_id' => $employeeId,
            'attendance_date' => '2026-01-01',
            'status' => 'late',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_database_allows_the_same_employee_on_different_days(): void
    {
        $employeeId = $this->employeeId();

        \DB::table('attendance_days')->insert([
            'employee_id' => $employeeId, 'attendance_date' => '2026-01-01',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('attendance_days')->insert([
            'employee_id' => $employeeId, 'attendance_date' => '2026-01-02',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, \DB::table('attendance_days')->where('employee_id', $employeeId)->count());
    }

    // ═══════════════ AttendanceDaysRequest ═══════════════

    /** @return array{0: AttendanceDaysRequest, 1: \Illuminate\Validation\Validator} */
    private function validate(array $data, ?int $routeId = null): array
    {
        $request = AttendanceDaysRequest::create('/hr/attendance-days', 'POST', $data);
        $request->setContainer($this->app);

        $route = new RoutingRoute('POST', '/attendance-days', []);
        $route->bind($request);
        if ($routeId !== null) {
            $route->setParameter('id', $routeId);
        }
        $request->setRouteResolver(fn () => $route);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        return [$request, $validator];
    }

    public function test_creating_a_second_day_for_the_same_employee_and_date_fails_validation(): void
    {
        $employeeId = $this->employeeId();

        \DB::table('attendance_days')->insert([
            'employee_id' => $employeeId, 'attendance_date' => '2026-01-01',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [, $validator] = $this->validate([
            'employee_id' => $employeeId,
            'attendance_date' => '2026-01-01',
            'status' => 'present',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('attendance_date', $validator->errors()->toArray());
    }

    public function test_creating_a_day_for_a_free_date_passes_validation(): void
    {
        $employeeId = $this->employeeId();

        [, $validator] = $this->validate([
            'employee_id' => $employeeId,
            'attendance_date' => '2026-01-01',
            'status' => 'present',
        ]);

        $this->assertFalse($validator->fails());
    }

    /** تعديلُ اليوم نفسِه لا يتعارض مع نفسه. */
    public function test_updating_a_day_without_changing_its_date_passes_validation(): void
    {
        $employeeId = $this->employeeId();

        $id = \DB::table('attendance_days')->insertGetId([
            'employee_id' => $employeeId, 'attendance_date' => '2026-01-01',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [, $validator] = $this->validate([
            'employee_id' => $employeeId,
            'attendance_date' => '2026-01-01',
            'status' => 'late',
        ], routeId: $id);

        $this->assertFalse($validator->fails(), 'تجاهلُ الصفّ نفسه عند التعديل مطلوب');
    }

    /** لكن تعديلَه إلى تاريخٍ يملكه صفٌّ آخر يبقى مرفوضاً. */
    public function test_updating_a_day_into_another_days_date_still_fails(): void
    {
        $employeeId = $this->employeeId();

        \DB::table('attendance_days')->insert([
            'employee_id' => $employeeId, 'attendance_date' => '2026-01-01',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $secondId = \DB::table('attendance_days')->insertGetId([
            'employee_id' => $employeeId, 'attendance_date' => '2026-01-02',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [, $validator] = $this->validate([
            'employee_id' => $employeeId,
            'attendance_date' => '2026-01-01',
            'status' => 'late',
        ], routeId: $secondId);

        $this->assertTrue($validator->fails());
    }
}
