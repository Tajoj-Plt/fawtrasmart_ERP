<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Enums\EmploymentStatus;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\HR\Http\Controllers\EmployeeController;
use Tests\TestCase;

/**
 * حالاتُ الموظف وإنهاءُ الخدمة.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كان `employees.status` قيمتين (`1 نشط` / `2 غير نشط`)، فيستوي فيه
 * الموقوفُ تأديبياً والمستقيلُ ومن أُنهيت خدمتُه — كلُّهم «غير نشط»
 * واحد، بلا تاريخٍ ولا سبب.
 *
 * **وكان التبديلُ معطوباً**: `$employee->status = ! $employee->status` —
 * نفيٌ منطقيّ على عمودٍ مجالُه `1`/`2`:
 *
 *     1 → !1 = false → 0
 *     2 → !2 = false → 0   ← المعطَّل لا يعود نشطاً أبداً
 *     0 → !0 = true  → 1
 *
 * فتعطيلُ النشط يكتب `0` لا `2`، و**الموظفُ المعطَّل لا يمكن تفعيلُه**.
 * ومن هنا جاءت `0` في قواعد قائمة، وبُني عليها في `EmployeesExport` فحصٌ
 * مقلوب.
 *
 * ويُقاس كذلك **أنّ «على رأس العمل» صار جواباً واحداً**: كانت تسعةُ
 * حرّاسٍ تقارن الرقمَ بـ`1` وخمسةَ عشرَ استعلاماً يبني قوائم الموظفين
 * بـ`where('status', 1)`. و«تحت التجربة» عملٌ قيمتُه `3` — فكلُّ واحدٍ من
 * تلك المواضع كان سيمنع راتبَه أو يُسقطه من القائمة. فنُقلت جميعاً إلى
 * `isActive()` و`Employee::active()`، ويحرس بقاءَها هناك
 * `test_no_raw_status_comparison_survives_anywhere`.
 */
class EmploymentStatusTest extends TestCase
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
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            $t->tinyInteger('allow_system_access')->default(1);
            $t->date('hire_date')->nullable();
            $t->date('termination_date')->nullable();
            $t->date('last_working_day')->nullable();
            $t->text('termination_reason')->nullable();
            // الموديل يستعمل `SoftDeletes` — فالعمود شرطُ كل استعلام عليه
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('contracts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });
    }

    private function employee(int $status = EmploymentStatus::ACTIVE->value): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => $status,
        ]);
    }

    private function controller(): EmployeeController
    {
        // بلا الباني: حُرّاسه ميدل وير تُختبر على المسار لا هنا
        return new class extends EmployeeController
        {
            public function __construct() {}
        };
    }

    // ═══════════════ النوع ═══════════════

    public function test_the_active_value_is_unchanged(): void
    {
        $this->assertSame(1, EmploymentStatus::ACTIVE->value, 'قيمةُ النشط هي ما يفحصه النظام كلُّه');
        $this->assertSame(2, EmploymentStatus::INACTIVE->value);
    }

    /** «على رأس العمل» = النشط ومن هو تحت التجربة، لا غير. */
    public function test_only_active_and_probation_count_as_working(): void
    {
        $this->assertTrue(EmploymentStatus::ACTIVE->isActive());
        $this->assertTrue(EmploymentStatus::PROBATION->isActive(), 'من تحت التجربة يعمل ويُصرف له');

        foreach ([EmploymentStatus::INACTIVE, EmploymentStatus::SUSPENDED,
            EmploymentStatus::RESIGNED, EmploymentStatus::TERMINATED] as $case) {
            $this->assertFalse($case->isActive(), "الحالة {$case->name} ليست عملاً");
        }

        $this->assertSame(
            [EmploymentStatus::ACTIVE->value, EmploymentStatus::PROBATION->value],
            EmploymentStatus::activeValues()
        );
    }

    /**
     * «تحت التجربة» حالةٌ عاملةٌ قيمتُها ليست `1`.
     *
     * وهذا بالضبط ما جعل نقلَ الفحوص إلى `isActive()` شرطاً: كلُّ حارسٍ كان
     * يقارن الرقمَ بـ`1` كان سيمنع راتبَ من هو تحت التجربة.
     */
    public function test_probation_is_working_but_is_not_the_number_one(): void
    {
        $this->assertSame(3, EmploymentStatus::PROBATION->value);
        $this->assertNotSame(EmploymentStatus::ACTIVE->value, EmploymentStatus::PROBATION->value);
        $this->assertTrue(EmploymentStatus::PROBATION->isOnProbation());
        $this->assertFalse(EmploymentStatus::ACTIVE->isOnProbation());
        $this->assertFalse(EmploymentStatus::PROBATION->isEnded());
    }

    public function test_ending_states_are_resignation_and_termination(): void
    {
        $this->assertTrue(EmploymentStatus::RESIGNED->isEnded());
        $this->assertTrue(EmploymentStatus::TERMINATED->isEnded());

        $this->assertFalse(EmploymentStatus::SUSPENDED->isEnded(), 'الإيقاف مؤقّت — يُرجَع منه');
        $this->assertFalse(EmploymentStatus::INACTIVE->isEnded());
        $this->assertFalse(EmploymentStatus::ACTIVE->isEnded());
    }

    /** والقيمةُ `0` التي خلّفها التبديلُ المعطوب تُقرأ «غير نشط» لا تنفجر. */
    public function test_out_of_range_values_read_as_inactive(): void
    {
        $this->assertSame(EmploymentStatus::INACTIVE, EmploymentStatus::fromValue(0));
        $this->assertSame(EmploymentStatus::INACTIVE, EmploymentStatus::fromValue(99));
        $this->assertSame(EmploymentStatus::ACTIVE, EmploymentStatus::fromValue('1'));
    }

    // ═══════════════ الموديل ═══════════════

    public function test_the_model_reads_its_status_as_a_type(): void
    {
        $this->assertTrue($this->employee()->isActive());
        $this->assertFalse($this->employee(EmploymentStatus::SUSPENDED->value)->isActive());
        $this->assertTrue($this->employee(EmploymentStatus::RESIGNED->value)->hasEndedService());
        $this->assertFalse($this->employee(EmploymentStatus::SUSPENDED->value)->hasEndedService());
    }

    public function test_the_scopes_select_the_right_rows(): void
    {
        $this->employee();
        $this->employee(EmploymentStatus::PROBATION->value);
        $this->employee(EmploymentStatus::INACTIVE->value);
        $this->employee(EmploymentStatus::SUSPENDED->value);
        $this->employee(EmploymentStatus::RESIGNED->value);
        $this->employee(EmploymentStatus::TERMINATED->value);

        $this->assertSame(2, Employee::active()->count(), 'النطاق يشمل من هو تحت التجربة');
        $this->assertSame(2, Employee::endedService()->count());
    }

    // ═══════════════ التبديل — العطل المُصلَح ═══════════════

    public function test_deactivating_writes_two_not_zero(): void
    {
        $employee = $this->employee();

        $this->controller()->updateStatus($employee->id);

        $this->assertSame(EmploymentStatus::INACTIVE->value, (int) $employee->fresh()->status);
        $this->assertNotSame(0, (int) $employee->fresh()->status, 'كُتبت 0 — عادت علّة النفي المنطقي');
    }

    /** وهي الحالةُ التي كانت مستحيلة: المعطَّل يعود نشطاً. */
    public function test_an_inactive_employee_can_be_reactivated(): void
    {
        $employee = $this->employee(EmploymentStatus::INACTIVE->value);

        $this->controller()->updateStatus($employee->id);

        $this->assertSame(EmploymentStatus::ACTIVE->value, (int) $employee->fresh()->status);
    }

    /** ومن انتهت خدمتُه لا تُبدَّل حالتُه بنقرة. */
    public function test_the_toggle_refuses_an_ended_employee(): void
    {
        $employee = $this->employee(EmploymentStatus::TERMINATED->value);

        $this->controller()->updateStatus($employee->id);

        $this->assertSame(EmploymentStatus::TERMINATED->value, (int) $employee->fresh()->status);
    }

    // ═══════════════ إنهاء الخدمة ═══════════════

    public function test_termination_records_date_reason_and_state(): void
    {
        $employee = $this->employee();

        $request = Request::create('/', 'POST', [
            'status' => EmploymentStatus::RESIGNED->value,
            'termination_date' => '2026-08-30',
            'last_working_day' => '2026-09-30',
            'termination_reason' => 'استقالة بطلبه',
        ]);

        $this->controller()->terminate($request, $employee->id);

        $fresh = $employee->fresh();

        $this->assertSame(EmploymentStatus::RESIGNED->value, (int) $fresh->status);
        $this->assertSame('2026-08-30', $fresh->termination_date->format('Y-m-d'));
        $this->assertSame('2026-09-30', $fresh->last_working_day->format('Y-m-d'));
        $this->assertSame('استقالة بطلبه', $fresh->termination_reason);
        $this->assertFalse($fresh->isActive(), 'من أُنهيت خدمتُه لا يُصرف له');
    }

    /** وآخرُ يومِ عملٍ متروكاً يساوي تاريخ الإنهاء. */
    public function test_the_last_working_day_defaults_to_the_termination_date(): void
    {
        $employee = $this->employee();

        $request = Request::create('/', 'POST', [
            'status' => EmploymentStatus::TERMINATED->value,
            'termination_date' => '2026-08-30',
            'termination_reason' => 'إنهاء من المنشأة',
        ]);

        $this->controller()->terminate($request, $employee->id);

        $this->assertSame('2026-08-30', $employee->fresh()->last_working_day->format('Y-m-d'));
    }

    /** ومن أُنهيت خدمتُه يُقطع دخولُه إلى النظام فوراً، لا بتبديلٍ يدويٍّ لاحق. */
    public function test_termination_disables_system_access(): void
    {
        $employee = $this->employee();
        $employee->update(['allow_system_access' => 1]);

        $this->controller()->terminate(Request::create('/', 'POST', [
            'status' => EmploymentStatus::TERMINATED->value,
            'termination_date' => '2026-08-30',
            'termination_reason' => 'إنهاء من المنشأة',
        ]), $employee->id);

        $this->assertSame(0, (int) $employee->fresh()->allow_system_access);
    }

    /** والعقدُ الساري بلا تاريخ انتهاء يُقفل عند آخر يوم عمل. */
    public function test_termination_ends_an_open_contract(): void
    {
        $employee = $this->employee();

        Contract::create([
            'employee_id' => $employee->id,
            'start_date' => '2025-01-01',
            'end_date' => null,
        ]);

        $this->controller()->terminate(Request::create('/', 'POST', [
            'status' => EmploymentStatus::RESIGNED->value,
            'termination_date' => '2026-08-30',
            'last_working_day' => '2026-09-30',
            'termination_reason' => 'استقالة بطلبه',
        ]), $employee->id);

        $this->assertSame('2026-09-30', $employee->contracts()->first()->fresh()->end_date->format('Y-m-d'));
    }

    /** ولا يُمدَّد عقدٌ ينتهي أصلاً قبل آخر يوم عمل. */
    public function test_termination_does_not_extend_a_contract_ending_earlier(): void
    {
        $employee = $this->employee();

        Contract::create([
            'employee_id' => $employee->id,
            'start_date' => '2025-01-01',
            'end_date' => '2026-09-10',
        ]);

        $this->controller()->terminate(Request::create('/', 'POST', [
            'status' => EmploymentStatus::RESIGNED->value,
            'termination_date' => '2026-08-30',
            'last_working_day' => '2026-09-30',
            'termination_reason' => 'استقالة بطلبه',
        ]), $employee->id);

        $this->assertSame('2026-09-10', $employee->contracts()->first()->fresh()->end_date->format('Y-m-d'));
    }

    public function test_a_reason_is_required(): void
    {
        $employee = $this->employee();

        $this->expectException(ValidationException::class);

        $this->controller()->terminate(Request::create('/', 'POST', [
            'status' => EmploymentStatus::RESIGNED->value,
            'termination_date' => '2026-08-30',
        ]), $employee->id);
    }

    /** ولا يُقبل إنهاءٌ بحالةٍ ليست من حالات الإنهاء. */
    public function test_a_non_ending_state_is_rejected(): void
    {
        $employee = $this->employee();

        $this->expectException(ValidationException::class);

        $this->controller()->terminate(Request::create('/', 'POST', [
            'status' => EmploymentStatus::INACTIVE->value,
            'termination_date' => '2026-08-30',
            'termination_reason' => 'x',
        ]), $employee->id);
    }

    public function test_an_already_ended_employee_is_not_terminated_twice(): void
    {
        $employee = $this->employee(EmploymentStatus::RESIGNED->value);
        $employee->update(['termination_reason' => 'الأصلي']);

        $this->controller()->terminate(Request::create('/', 'POST', [
            'status' => EmploymentStatus::TERMINATED->value,
            'termination_date' => '2026-09-01',
            'termination_reason' => 'محاولة ثانية',
        ]), $employee->id);

        $this->assertSame('الأصلي', $employee->fresh()->termination_reason);
    }

    // ═══════════════ الحراسة ═══════════════

    public function test_the_permission_is_in_the_catalog(): void
    {
        $names = collect(HrPermissions::groups())
            ->flatMap(fn ($group) => array_keys($group['permissions']))
            ->all();

        $this->assertContains('employees_terminate', $names);
    }

    /** والمسارُ `POST` محروسٌ بها — أثرُ الإنهاء لا يقع بزيارة رابط. */
    public function test_the_route_is_a_guarded_post(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'employee.terminate');

        $this->assertNotNull($route, 'المسار employee.terminate غير مسجَّل');
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
        $this->assertContains('permission.role:employees_terminate', $route->gatherMiddleware());
    }

    /** والموديلُ يوافق النوعَ: من تحت التجربة نشطٌ لا منتهٍ. */
    public function test_an_employee_on_probation_reads_as_working(): void
    {
        $employee = $this->employee(EmploymentStatus::PROBATION->value);

        $this->assertTrue($employee->isActive());
        $this->assertFalse($employee->hasEndedService());
        $this->assertSame('تحت التجربة', $employee->employmentStatus()->label());
    }

    /**
     * لا يبقى في النظام حارسٌ يقارن حالةَ الموظف بالرقم `1`.
     *
     * كان تسعةُ حرّاسٍ في مسار الصرف والأهداف، وخمسةَ عشرَ استعلاماً يبني
     * قوائم الموظفين — وكلُّها كانت ستُسقط من هو تحت التجربة. فهذا الحارسُ
     * يمنع عودةَ النمط في شيفرةٍ جديدة.
     */
    public function test_no_raw_status_comparison_survives_anywhere(): void
    {
        $offenders = [];

        $roots = [base_path('app'), base_path('Modules')];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();

                // النوعُ نفسُه والموديل يذكران النمط في شرحهما
                if (str_contains($path, 'EmploymentStatus.php') || str_contains($path, 'Models/Employee.php')) {
                    continue;
                }

                $code = (string) file_get_contents($path);

                if (preg_match("/in_array\(\s*\\$[a-zA-Z_>\-]*->status,\s*\[1, '1', 'active'\]/", $code)
                    || str_contains($code, "Employee::where('status', 1)")) {
                    $offenders[] = str_replace(base_path().'/', '', $path);
                }
            }
        }

        $this->assertSame([], $offenders, 'مواضع ما زالت تقارن حالة الموظف بالرقم مباشرةً');
    }
}
