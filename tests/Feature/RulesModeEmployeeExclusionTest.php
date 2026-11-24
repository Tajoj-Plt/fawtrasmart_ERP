<?php

namespace Tests\Feature;

use App\Http\Requests\AttendanceSheetsRequest;
use App\Http\Requests\CustomShiftRequest;
use App\Http\Requests\LeavePolicyEmployeesRequest;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\HR\Http\Controllers\Attendance\AttendanceDaysController;
use Modules\HR\Http\Controllers\Attendance\AttendanceSheetsController;
use Modules\HR\Http\Controllers\Attendance\CustomShiftsController;
use Modules\HR\Http\Controllers\Attendance\Settings\LeavePoliciesController;
use Modules\HR\Http\Controllers\Concerns\ResolvesEmployeesByRules;
use Tests\TestCase;

/**
 * وضعُ «إختيار القواعد» في شاشات الحضور.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كان حقلُ «اختر الموظفين المستبعدين» يحمل الاسم `employee_id[]` نفسَه الذي
 * يحمله حقلُ التضمين في الوضع الآخر، والمتحكّماتُ تمرّ على `employee_id`
 * لتنشئ لكلٍّ منهم سجلاً — فمن يُختار «مستبعداً» هو وحده من يُعالَج،
 * والقواعدُ (فرع/قسم/مسمى/وردية) لا تجلب أحداً أصلاً. الاستبعادُ كان
 * تضميناً مقلوباً.
 */
class RulesModeEmployeeExclusionTest extends TestCase
{
    use ResolvesEmployeesByRules;

    protected function setUp(): void
    {
        parent::setUp();

        // هجرات الموظفين تعتمد على أنواع MySQL، فيُبنى أصغرُ جدولٍ يكفي
        // للقاعدة المختبَرة — كما في [AttendanceDaysScopeTest].
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // قواعدُ `exists` في طلبات التحقق تقرأ هذه الجداول.
        foreach (['branches', 'departments', 'jop_titles', 'shifts'] as $lookup) {
            Schema::create($lookup, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });

            \DB::table($lookup)->insert(['id' => 1, 'name' => 'مرجع']);
        }
    }

    /**
     * @param  class-string<FormRequest>  $requestClass
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    private function validationErrorKeys(string $requestClass, array $input): array
    {
        $request = $requestClass::create('/', 'POST', $input);

        $validator = Validator::make($input, $request->rules(), $request->messages());
        $request->withValidator($validator);
        $validator->passes();

        return $validator->errors()->keys();
    }

    private function makeEmployee(array $attributes): Employee
    {
        $employee = new Employee;
        $employee->forceFill($attributes + ['first_name' => 'موظف']);
        $employee->save();

        return $employee;
    }

    private function request(array $input): Request
    {
        return Request::create('/hr/attendanceDays/calculation', 'POST', $input);
    }

    public function test_rules_mode_pulls_from_the_rules_and_drops_the_excluded(): void
    {
        $kept = $this->makeEmployee(['branch_id' => 1, 'department_id' => 5]);
        $excluded = $this->makeEmployee(['branch_id' => 1, 'department_id' => 5]);
        $otherBranch = $this->makeEmployee(['branch_id' => 2, 'department_id' => 5]);

        $resolved = $this->resolveEmployeeIdsFromRequest($this->request([
            'use_rules' => 'rules',
            'branch_id' => 1,
            'department_id' => 5,
            'excluded_employee_id' => [$excluded->id],
        ]));

        $this->assertSame([$kept->id], $resolved, 'المستبعَد لم يُستبعد، أو القواعد لم تجلب');
        $this->assertNotContains($otherBranch->id, $resolved, 'القاعدة لم تقيّد بالفرع');
    }

    /** القاعدةُ الوحيدةُ المخالفة تُسقط الموظف ولو طابق البقية. */
    public function test_every_rule_narrows_the_result(): void
    {
        $match = $this->makeEmployee(['branch_id' => 1, 'department_id' => 5, 'job_title_id' => 3, 'shift_id' => 7]);
        $wrongShift = $this->makeEmployee(['branch_id' => 1, 'department_id' => 5, 'job_title_id' => 3, 'shift_id' => 9]);

        $resolved = $this->resolveEmployeeIdsFromRequest($this->request([
            'use_rules' => 'rules',
            'branch_id' => 1,
            'department_id' => 5,
            'job_title_id' => 3,
            'shifts_id' => 7,
        ]));

        $this->assertSame([$match->id], $resolved);
        $this->assertNotContains($wrongShift->id, $resolved);
    }

    /** الوردياتُ المتخصصة تسمّي حقلَها `shift_rule_id` لا `shifts_id`. */
    public function test_the_custom_shift_rule_field_name_is_honoured(): void
    {
        $match = $this->makeEmployee(['shift_id' => 7]);
        $this->makeEmployee(['shift_id' => 9]);

        $resolved = $this->resolveEmployeeIdsFromRequest($this->request([
            'use_rules' => 'rules',
            'shift_rule_id' => 7,
        ]));

        $this->assertSame([$match->id], $resolved);
    }

    /** وضعُ الموظفين لم يتغيّر: المختارُ صراحةً هو المقصود. */
    public function test_employees_mode_still_takes_the_explicit_selection(): void
    {
        $first = $this->makeEmployee(['branch_id' => 1]);
        $second = $this->makeEmployee(['branch_id' => 2]);

        $resolved = $this->resolveEmployeeIdsFromRequest($this->request([
            'use_rules' => 'employees',
            'employee_id' => [$first->id, $second->id],
        ]));

        $this->assertSame([$first->id, $second->id], $resolved);
    }

    /** الموظفُ المؤرشف لا تجلبه القاعدة. */
    public function test_archived_employees_are_not_pulled_by_the_rules(): void
    {
        $active = $this->makeEmployee(['branch_id' => 1]);
        $this->makeEmployee(['branch_id' => 1])->delete();

        $resolved = $this->resolveEmployeeIdsFromRequest($this->request([
            'use_rules' => 'rules',
            'branch_id' => 1,
        ]));

        $this->assertSame([$active->id], $resolved);
    }

    /** ما استُبعد سابقاً يُستنتج عند التعديل: مطابقو القواعد ناقصَ المرتبطين. */
    public function test_the_excluded_list_is_derived_for_the_edit_screens(): void
    {
        $attached = $this->makeEmployee(['branch_id' => 1]);
        $excluded = $this->makeEmployee(['branch_id' => 1]);

        $this->assertSame(
            [$excluded->id],
            $this->excludedEmployeeIdsFromRules(['branch_id' => 1], [$attached->id])
        );
    }

    /**
     * الحقلان لا يتشاركان الاسم في أيٍّ من الشاشات الأربع — وهو أصلُ العلة.
     */
    public function test_no_screen_posts_the_excluded_field_as_employee_id(): void
    {
        $screens = [
            'Modules/HR/resources/views/attendance/attendance_days/Calculation.blade.php',
            'Modules/HR/resources/views/attendance/attendance_sheets/create.blade.php',
            'Modules/HR/resources/views/attendance/attendance_sheets/edit.blade.php',
            'Modules/HR/resources/views/attendance/custom_shifts/create.blade.php',
            'Modules/HR/resources/views/attendance/custom_shifts/edit.blade.php',
            'Modules/HR/resources/views/attendance/settings/leave_policies/leave_policy_employees.blade.php',
        ];

        foreach ($screens as $screen) {
            $source = (string) file_get_contents(base_path($screen));

            $this->assertStringContainsString(
                'name="excluded_employee_id[]"',
                $source,
                "حقلُ الاستبعاد في {$screen} لا يحمل اسمه الخاص"
            );

            $this->assertSame(
                1,
                substr_count($source, 'name="employee_id[]"'),
                "{$screen} فيه أكثر من حقلٍ باسم employee_id[]"
            );
        }
    }

    /** المتحكّماتُ الثلاثة تمرّ بالمرجع نفسِه لا بـ`employee_id` مباشرةً. */
    public function test_the_controllers_resolve_through_the_shared_concern(): void
    {
        foreach ([AttendanceSheetsController::class, CustomShiftsController::class, LeavePoliciesController::class] as $controller) {
            $this->assertContains(
                ResolvesEmployeesByRules::class,
                array_values(class_uses_recursive($controller)),
                "{$controller} لا يستعمل مرجعَ جلب الموظفين"
            );

            $source = (string) file_get_contents((new \ReflectionClass($controller))->getFileName());

            $this->assertStringContainsString('resolveEmployeeIdsFromRequest($request)', $source);
        }
    }

    /** وضعُ القواعد بلا قاعدةٍ واحدة يشمل كلَّ المنشأة، فيُرفض. */
    public function test_rules_mode_demands_at_least_one_rule(): void
    {
        $screens = [
            AttendanceSheetsRequest::class => ['from_date' => '2026-01-01', 'to_date' => '2026-01-05'],
            CustomShiftRequest::class => ['name' => 'وردية', 'from_date' => '2026-01-01', 'to_date' => '2026-01-05', 'shift_id' => 1],
            LeavePolicyEmployeesRequest::class => [],
        ];

        foreach ($screens as $requestClass => $base) {
            $this->assertContains(
                'use_rules',
                $this->validationErrorKeys($requestClass, $base + ['use_rules' => 'rules']),
                "{$requestClass} يقبل وضعَ القواعد بلا قاعدة"
            );

            $this->assertNotContains(
                'use_rules',
                $this->validationErrorKeys($requestClass, $base + ['use_rules' => 'rules', 'department_id' => 1]),
                "{$requestClass} يرفض قاعدةً صالحة"
            );
        }
    }

    /** الشرطُ لا يمسّ وضعَ الموظفين: لا قاعدة تُطلب فيه. */
    public function test_employees_mode_is_not_asked_for_a_rule(): void
    {
        $employee = $this->makeEmployee([]);

        $errors = $this->validationErrorKeys(AttendanceSheetsRequest::class, [
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-05',
            'use_rules' => 'employees',
            'employee_id' => [$employee->id],
        ]);

        $this->assertSame([], $errors);
    }

    /** الحسابُ الميت: لا مسارَ له ولا دالة. */
    public function test_the_dead_calculate_endpoint_is_gone(): void
    {
        $this->assertFalse(Route::has('calculateAttendance'), 'مسارُ calculateAttendance ما زال مسجَّلاً');
        $this->assertFalse(method_exists(AttendanceDaysController::class, 'calculateAttendance'));
    }

    /**
     * صفُّ تخصيص سياسة الإجازات واحدٌ لكلّ سياسة — فالمطابقةُ على
     * `leave_policy_id` وحده، لا على القواعد معه.
     */
    public function test_the_leave_policy_customization_is_matched_by_policy_alone(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(LeavePoliciesController::class))->getFileName()
        );

        $this->assertMatchesRegularExpression(
            "/updateOrCreate\(\s*\['leave_policy_id' => \\\$id\],/",
            $source,
            'المطابقة ما زالت تشمل القواعد فتُنشئ صفاً ثانياً'
        );

        $this->assertMatchesRegularExpression(
            "/firstOrCreate\(\s*\['leave_policy_id' => \\\$leavePolicyId\],/",
            $source,
            'مسارُ الإضافة المباشرة ما زال يطابق بـ use_rules معه'
        );
    }
}
