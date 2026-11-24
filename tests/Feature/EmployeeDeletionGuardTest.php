<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Support\EmployeeDeletionGuard;
use Tests\TestCase;

/**
 * حذفُ الموظف نهائياً — ومتى يُمنع.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * زرُّ «حذف» في بطاقة الموظف كان يؤرشف ثم يعود إلى البطاقة نفسِها،
 * و`show()` لا ترى المؤرشَف (`findOrFail`) — فصفحةُ 404 بعد كلّ «نجاح».
 * والحذفُ الصلبُ بديلاً عنه لا يجوز مطلقاً: الجدولُ يشير إليه ثلاثةٌ
 * وثلاثون مفتاحاً أجنبياً أكثرُها `CASCADE`، فيمحو الموظفُ معه عقودَه
 * وحضورَه وشجرةَ مرؤوسيه.
 *
 * فالمقياسُ هنا على ثلاثة: أن يُمنع الحذفُ حين يكون للموظف ارتباط، وأن
 * يُمنع حين تكون له حركة، وأن يقع فعلاً — ويمحو معه إعدادَه وحسابَ
 * دخوله — حين لا يكون له شيء.
 */
class EmployeeDeletionGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->string('employee_code', 64)->nullable();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            $t->tinyInteger('allow_system_access')->default(0);
            $t->unsignedBigInteger('direct_manager_id')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->foreign('direct_manager_id')->references('id')->on('employees')->cascadeOnDelete();
        });

        // عيّنةُ «الحركة»: العقود
        Schema::create('contracts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('code');

            $t->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });

        // وعيّنةُ «الإعداد» الذي يُمحى مع الموظف ولا يمنع حذفه
        Schema::create('employee_branch', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('branch_id');
            $t->boolean('is_main')->default(0);
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('role')->default('owner');
            $t->unsignedBigInteger('role_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->timestamps();
        });

        // نشاطُ الحساب في النظام — لا ارتباطَ في ملفّ الموظف
        Schema::create('session_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip_address')->nullable();
        });

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    private function controller(): EmployeeController
    {
        return new class extends EmployeeController
        {
            public function __construct() {}
        };
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create($attributes + [
            'first_name' => 'موظف',
            'email' => 'e'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
        ]);
    }

    private function loginAccountFor(Employee $employee): User
    {
        return User::create([
            'name' => 'حساب',
            'email' => 'u'.uniqid().'@example.test',
            'password' => 'x',
            'employee_id' => $employee->id,
        ]);
    }

    // ═══════════════ ما يُمنع ═══════════════

    /** حركةٌ في النظام: عقدٌ واحدٌ يكفي لمنع الحذف. */
    public function test_movement_blocks_the_deletion(): void
    {
        $employee = $this->employee();
        DB::table('contracts')->insert(['employee_id' => $employee->id, 'code' => 'CTR-1']);

        $this->assertFalse(EmployeeDeletionGuard::for($employee)->allowsDeletion());

        $this->controller()->delete($employee->id);

        $this->assertTrue(
            Employee::whereKey($employee->id)->exists(),
            'حُذف موظفٌ له عقد'
        );
        $this->assertTrue(
            DB::table('contracts')->where('employee_id', $employee->id)->exists(),
            'مُحي العقدُ مع الموظف — عاد الشلّال'
        );
    }

    /** وارتباطٌ إداري: من يتبعه أحدٌ لا يُحذف، وإلّا مُحيت شجرتُه. */
    public function test_having_a_team_blocks_the_deletion(): void
    {
        $manager = $this->employee();
        $subordinate = $this->employee(['direct_manager_id' => $manager->id]);

        $this->controller()->delete($manager->id);

        $this->assertTrue(Employee::whereKey($manager->id)->exists());
        $this->assertTrue(
            Employee::whereKey($subordinate->id)->exists(),
            'المرؤوسُ حُذف مع مديره'
        );
    }

    /** ونشاطُ حساب الدخول يمنع كذلك، ولو خلا ملفُّ الموظف. */
    public function test_system_activity_on_the_login_account_blocks_the_deletion(): void
    {
        $employee = $this->employee();
        $user = $this->loginAccountFor($employee);
        DB::table('session_logs')->insert(['user_id' => $user->id, 'ip_address' => '127.0.0.1']);

        $this->assertFalse(EmployeeDeletionGuard::for($employee)->allowsDeletion());

        $this->controller()->delete($employee->id);

        $this->assertTrue(Employee::whereKey($employee->id)->exists());
        $this->assertTrue(DB::table('users')->where('id', $user->id)->exists());
    }

    /** والمانعُ يُسمّى باسمه — لا «تعذّر الحذف» وحدها. */
    public function test_the_refusal_names_what_blocks_it(): void
    {
        $employee = $this->employee();
        DB::table('contracts')->insert(['employee_id' => $employee->id, 'code' => 'CTR-1']);

        $reason = EmployeeDeletionGuard::for($employee)->reason();

        $this->assertNotNull($reason);
        $this->assertStringContainsString('العقود', $reason);
    }

    // ═══════════════ ما يقع ═══════════════

    /** موظفٌ بلا ارتباطٍ ولا حركة يُحذف نهائياً — لا يُؤرشَف. */
    public function test_a_clean_employee_is_deleted_for_good(): void
    {
        $employee = $this->employee();

        $this->assertTrue(EmployeeDeletionGuard::for($employee)->allowsDeletion());

        $this->controller()->delete($employee->id);

        $this->assertFalse(
            DB::table('employees')->where('id', $employee->id)->exists(),
            'بقي الصفُّ — أُرشف بدل أن يُحذف'
        );
        $this->assertSame(0, Employee::withTrashed()->count());
    }

    /** ويُمحى معه إعدادُه — الفروعُ تُنشأ في `store()` فلا تُعدّ مانعاً. */
    public function test_deleting_purges_the_employee_own_configuration(): void
    {
        $employee = $this->employee();
        DB::table('employee_branch')->insert([
            'employee_id' => $employee->id, 'branch_id' => 1, 'is_main' => 1,
        ]);

        $this->assertTrue(EmployeeDeletionGuard::for($employee)->allowsDeletion());

        $this->controller()->delete($employee->id);

        $this->assertFalse(DB::table('employees')->where('id', $employee->id)->exists());
        $this->assertFalse(
            DB::table('employee_branch')->where('employee_id', $employee->id)->exists(),
            'بقيت صفوفُ الفروع بلا موظف'
        );
    }

    /** وحسابُ دخوله معه — لا يبقى حسابٌ حيٌّ لموظفٍ لا وجود له. */
    public function test_deleting_takes_the_login_account_with_it(): void
    {
        $employee = $this->employee();
        $user = $this->loginAccountFor($employee);

        $this->controller()->delete($employee->id);

        $this->assertFalse(
            DB::table('users')->where('id', $user->id)->exists(),
            'بقي حسابُ الدخول بعد حذف الموظف'
        );
    }

    // ═══════════════ الحراسة ═══════════════

    /** والمسارُ `POST` محروسٌ بصلاحية «تعديل وحذف الموظفين». */
    public function test_the_delete_route_is_a_guarded_post(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'employee.delete');

        $this->assertNotNull($route, 'المسار employee.delete غير مسجَّل');
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
        $this->assertContains('permission.role:employees_edit_delete', $route->gatherMiddleware());
    }
}
