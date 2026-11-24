<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\EmployeeController;
use Tests\TestCase;

/**
 * أرشفةُ الموظف بدل حذفه.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كان «حذفُ الموظف» حذفاً صلباً على جدولٍ تشير إليه إحدى وثلاثون علاقة،
 * **خمسٌ وعشرون منها `ON DELETE CASCADE`**. فنقرةٌ واحدة تمحو عقودَه
 * وخصمياتِه وسجلَّ حضوره وتسوياتِ أهدافه ونقاطَه ومكافآتِه — و**تمحو
 * شجرةَ مرؤوسيه كلَّها** عبر `employees.direct_manager_id`. ويُقال
 * للمستخدم «تم بنجاح».
 *
 * ويُقاس هنا الأثرُ الفعلي لا وجودُ العمود: يُبنى مديرٌ ومرؤوسٌ وعقد
 * بمفاتيح أجنبية `CASCADE` حقيقية، ثم يُؤرشَف المدير — فيُنظَر أنجا
 * المرؤوسُ والعقد.
 */
class EmployeeArchiveTest extends TestCase
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

        // عيّنةٌ من الخمسة والعشرين: العقود
        Schema::create('contracts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('code');

            $t->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
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

        /*
            نطاقُ الرؤية (`ScopesToAccessibleEmployees`) يقيّد بمحور الموظف،
            ومستخدمٌ غير مسجَّل لا يرى شيئاً — فترفض `restore()` بـ403 وتخرج
            قائمةُ الأرشيف فارغة، وهو الصواب.

            فيُسجَّل هنا مستخدمٌ **ليس موظفاً** (`employee_id` فارغ): تلك
            حالةُ «بلا قيدٍ من النطاق» المنصوص عليها في المرجع نفسه —
            أمرُه إلى الصلاحيات وحدها، وهي تُختبر على المسار لا هنا.
        */
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

    // ═══════════════ الأثر الحقيقي ═══════════════

    /** أخطرُ ما كان: حذفُ مديرٍ يحذف شجرةَ مرؤوسيه. */
    public function test_archiving_a_manager_does_not_delete_the_team(): void
    {
        $manager = $this->employee();
        $subordinate = $this->employee(['direct_manager_id' => $manager->id]);

        $this->controller()->archive($manager->id);

        $this->assertTrue(
            DB::table('employees')->where('id', $subordinate->id)->exists(),
            'المرؤوسُ حُذف مع مديره — عاد الشلّال'
        );
    }

    /** والعقودُ وأخواتُها الأربعُ والعشرون تبقى. */
    public function test_archiving_keeps_the_cascading_relations(): void
    {
        $employee = $this->employee();
        DB::table('contracts')->insert(['employee_id' => $employee->id, 'code' => 'CTR-1']);

        $this->controller()->archive($employee->id);

        $this->assertTrue(
            DB::table('contracts')->where('employee_id', $employee->id)->exists(),
            'العقدُ مُحي مع الموظف'
        );
    }

    /** والصفُّ نفسُه باقٍ في القاعدة — مؤرشفاً لا ممحوّاً. */
    public function test_the_row_survives_and_leaves_the_default_queries(): void
    {
        $employee = $this->employee();

        $this->controller()->archive($employee->id);

        $this->assertTrue(DB::table('employees')->where('id', $employee->id)->exists());
        $this->assertSame(0, Employee::count(), 'المؤرشَف يختفي من الاستعلامات العادية');
        $this->assertSame(1, Employee::onlyTrashed()->count());
        $this->assertNotNull(Employee::withTrashed()->find($employee->id)->deleted_at);
    }

    // ═══════════════ الدخول ═══════════════

    /** والأرشفةُ تقطع دخولَ النظام — لا يبقى حسابٌ حيٌّ لموظفٍ مؤرشَف. */
    public function test_archiving_revokes_system_access(): void
    {
        $employee = $this->employee(['allow_system_access' => 1]);

        $this->controller()->archive($employee->id);

        $this->assertSame(
            0,
            (int) Employee::withTrashed()->find($employee->id)->allow_system_access
        );
    }

    /**
     * وحارسُ تسجيل الدخول لا يمرّ حين يغيب الملفّ.
     *
     * كان الشرطُ `if ($employee && ! $employee->allow_system_access)` —
     * فملفٌّ مفقود يتخطّى الفحصَ كلَّه. ومع الأرشفة تردّ `find()` عدماً
     * للمؤرشَف، فيصير ذلك باباً مفتوحاً.
     */
    public function test_the_login_guard_denies_a_missing_employee_profile(): void
    {
        $source = (string) file_get_contents(base_path('app/Http/Controllers/Auth/AuthController.php'));

        // تأكيدٌ منطقيّ لا نصّي: `assertStringNotContainsString` تطبع الملفّ
        // كلَّه عند السقوط فتُغرق الناتج
        $this->assertFalse(
            str_contains($source, 'if ($employee && ! $employee->allow_system_access)'),
            'عاد الشرطُ الذي يتخطّى الفحص عند غياب الملفّ'
        );

        $this->assertTrue(
            str_contains($source, 'if (! $employee) {'),
            'لا حارسَ يمنع الدخول حين يغيب ملفُّ الموظف'
        );
    }

    // ═══════════════ الاسترداد ═══════════════

    public function test_an_archived_employee_can_be_restored(): void
    {
        $employee = $this->employee();
        $this->controller()->archive($employee->id);

        $this->controller()->restore($employee->id);

        $this->assertSame(1, Employee::count());
        $this->assertNull(Employee::find($employee->id)->deleted_at);
    }

    /** ولا يُعاد إذنُ الدخول تلقائياً — قُطع بقرار ويُعاد بقرار. */
    public function test_restoring_does_not_hand_back_system_access(): void
    {
        $employee = $this->employee(['allow_system_access' => 1]);
        $this->controller()->archive($employee->id);

        $this->controller()->restore($employee->id);

        $this->assertSame(0, (int) Employee::find($employee->id)->allow_system_access);
    }

    public function test_the_archived_list_shows_only_archived_employees(): void
    {
        $kept = $this->employee();
        $archived = $this->employee();

        $this->controller()->archive($archived->id);

        $view = $this->controller()->archived(Request::create('/', 'GET'));
        $ids = $view->getData()['employees']->pluck('id')->all();

        $this->assertSame([$archived->id], $ids);
        $this->assertNotContains($kept->id, $ids);
    }

    // ═══════════════ الحراسة ═══════════════

    public function test_the_permission_is_in_the_catalog(): void
    {
        $names = collect(HrPermissions::groups())
            ->flatMap(fn ($group) => array_keys($group['permissions']))
            ->all();

        $this->assertContains('employees_archive', $names);
    }

    /**
     * والمساراتُ الباقيةُ محروسةٌ بها، والمغيِّرُ منها `POST`.
     *
     * وكان `employee.delete` مساراً `GET` بينما مودالُ القائمة يرسل `POST`
     * مع `@method('DELETE')` — فلم يعمل زرُّ الحذف في القائمة قطّ (405)،
     * وكان يعمل من البطاقة برابطٍ `GET` يقع بمجرّد زيارته. وهو الآن حذفٌ
     * نهائيٌّ محروس، يُختبر في `EmployeeDeletionGuardTest`.
     */
    public function test_the_mutating_routes_are_guarded_posts(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->keyBy(fn ($r) => (string) $r->getName());

        $restore = $routes->get('employee.restore');

        $this->assertNotNull($restore, 'المسار employee.restore غير مسجَّل');
        $this->assertContains('POST', $restore->methods(), 'employee.restore ليس POST');
        $this->assertNotContains('GET', $restore->methods(), 'employee.restore ما زال يقبل GET');
        $this->assertContains('permission.role:employees_archive', $restore->gatherMiddleware());

        $this->assertContains(
            'permission.role:employees_archive',
            $routes->get('employee.archived')->gatherMiddleware()
        );
    }

    /**
     * والأرشفةُ نفسُها معلَّقةٌ بطلب — لا مسارَ يبلغها.
     *
     * يُثبَّت هنا لا ليمنع عودتَها، بل ليُعلَم أن أيَّ قالبٍ يبني
     * `route('employee.archive')` سيرمي `RouteNotFoundException` ويُسقط
     * شاشتَه — وذلك ما أوجب تعليقَ مودال القائمة معه.
     */
    public function test_the_archive_route_is_suspended(): void
    {
        $this->assertNull(
            collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => $r->getName() === 'employee.archive'),
            'عاد مسارُ الأرشفة — فارفعْ تعليقَ مودال القائمة ورابطِ المؤرشفين معه'
        );
    }
}
