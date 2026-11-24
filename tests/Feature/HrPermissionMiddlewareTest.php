<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Http\Middleware\CheckPermissionRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * حارسُ صلاحيات وحدة الموارد البشرية بعد توحيده على `permission.role`.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كانت متحكّمات الوحدة تُحرَس بـ`permission:` — وسيط Spatie الذي يقرأ
 * `model_has_roles` و`model_has_permissions`. وبقيّةُ النظام تقرأ
 * `users.role_id ← role_has_permissions`: الوسيطُ `CheckPermissionRole`،
 * والدالّةُ `hasPermission()`، وموجِّهُ `@permission` في القوالب. فكان
 * خادمُ هذه الوحدة يسأل غيرَ ما تسأله شاشاتُها.
 *
 * والفرقُ لا يظهر ما دام المصدران متوافقَين — و`User::assignPrimaryRole()`
 * تكتبهما معاً. فالاختبار يفرّق بينهما عمداً في الحالتين الأخيرتين، إذ
 * لا معنى لاختبارٍ يمرّ قبل التغيير وبعده سواء.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml` — وهو ما يفعله
 * [InstallerAdminRoleTest] للسبب نفسه.
 */
class HrPermissionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('role')->default('employee');
            $t->unsignedBigInteger('role_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** يمرّر الطلب عبر الوسيط ويردّ رمزَ الحالة — 200 يعني السماح. */
    private function guard(?User $user, string $permission): int
    {
        $request = Request::create('/hr/employee/index', 'GET');
        $request->setUserResolver(fn () => $user);

        try {
            $response = app(CheckPermissionRole::class)->handle(
                $request,
                fn () => response('ok'),
                $permission
            );

            return $response->getStatusCode();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }
    }

    private function permission(string $name): SpatiePermission
    {
        return SpatiePermission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    private function userWithRole(string $roleName, array $permissions): User
    {
        $role = SpatieRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo($this->permission($name));
        }

        $user = User::create([
            'name' => 'موظف',
            'email' => $roleName.'@example.test',
            'password' => 'x',
            'role' => $roleName,
            'role_id' => $role->id,
        ]);

        $user->syncRoles([$roleName]);

        return $user->fresh();
    }

    public function test_role_holding_the_permission_is_allowed(): void
    {
        $user = $this->userWithRole('hr_manager', ['employees_view_profile']);

        $this->assertSame(200, $this->guard($user, 'employees_view_profile'));
    }

    public function test_role_without_the_permission_is_denied(): void
    {
        $user = $this->userWithRole('cashier', ['stock.products.view']);

        $this->assertSame(403, $this->guard($user, 'employees_view_profile'));
    }

    public function test_alternatives_list_accepts_any_one_of_them(): void
    {
        // النمطُ الغالب في الوحدة: عرضُ الكلّ أو عرضُ ما يخصّه هو
        $user = $this->userWithRole('employee', ['salaries_payroll_view_own']);

        $this->assertSame(
            200,
            $this->guard($user, 'salaries_payroll_view|salaries_payroll_view_own')
        );
        $this->assertSame(403, $this->guard($user, 'salaries_payroll_view'));
    }

    public function test_user_without_any_role_is_denied(): void
    {
        $user = User::create([
            'name' => 'بلا دور',
            'email' => 'norole@example.test',
            'password' => 'x',
            'role' => 'employee',
            'role_id' => null,
        ]);

        $this->assertSame(403, $this->guard($user->fresh(), 'employees_view_profile'));
    }

    public function test_guest_is_denied(): void
    {
        $this->assertSame(403, $this->guard(null, 'employees_view_profile'));
    }

    /**
     * الفرقُ الذي أُجري التغييرُ من أجله (١): صلاحيةٌ مُسنَدة للمستخدم
     * مباشرةً في `model_has_permissions` — خارج دوره — لم تعد تفتح شاشات
     * الوحدة. وسيطُ Spatie كان يقبلها.
     *
     * وهذا هو السلوك المقصود، وهو نفسه في المخزون والمبيعات المالية: لا
     * صلاحية إلا من دور. ولا يمسّ حسابَ التثبيت لأن فجوة `owner` التي
     * تُمنح مباشرةً (إعدادات ومحاسبة ومفاتيح API) لا صلاحية موارد بشرية
     * فيها.
     */
    public function test_direct_user_permission_outside_the_role_no_longer_opens_hr(): void
    {
        $user = $this->userWithRole('viewer', []);
        $user->givePermissionTo($this->permission('employees_view_profile'));

        $user = $user->fresh();

        // Spatie يراها…
        $this->assertTrue($user->hasPermissionTo('employees_view_profile'));

        // …والحارسُ الجديد لا: مصدرُه الدورُ وحده
        $this->assertSame(403, $this->guard($user, 'employees_view_profile'));
    }

    /**
     * الفرقُ الذي أُجري التغييرُ من أجله (٢): حين يفترق المصدران، يتبع
     * الحارسُ `role_id` — فيوافق ما تعرضه القوالب عبر `hasPermission()`.
     */
    public function test_guard_follows_role_id_and_matches_the_blade_helper(): void
    {
        $granted = SpatieRole::firstOrCreate(['name' => 'granted', 'guard_name' => 'web']);
        $granted->givePermissionTo($this->permission('employees_add'));

        $empty = SpatieRole::firstOrCreate(['name' => 'empty', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'مفترق',
            'email' => 'diverged@example.test',
            'password' => 'x',
            'role' => 'granted',
            'role_id' => $granted->id,
        ]);

        // ‏Spatie يرى الدور الفارغ، و`role_id` يشير إلى الدور المانح
        $user->syncRoles([$empty->name]);
        $user = $user->fresh();

        $this->assertFalse($user->hasPermissionTo('employees_add'), 'Spatie يتبع model_has_roles');
        $this->assertTrue($user->hasRolePermission('employees_add'), 'والنظام يتبع role_id');

        $this->assertSame(200, $this->guard($user, 'employees_add'));
    }

    /**
     * كل اسمٍ يحرس مسارات الوحدة موجودٌ في كتالوجها — فلا حارسَ باسمٍ
     * لا يُنشئه `RolesSeeder`، وهو ما يجعل الحارسَ صامتاً لا مانعاً.
     */
    public function test_every_guard_name_in_hr_controllers_exists_in_the_catalog(): void
    {
        $known = collect(HrPermissions::groups())
            ->flatMap(fn ($group) => array_keys($group['permissions']))
            ->all();

        $sources = array_merge(
            glob(base_path('Modules/HR/Http/Controllers/**/*.php')) ?: [],
            glob(base_path('Modules/HR/Http/Controllers/*.php')) ?: [],
            glob(base_path('Modules/HR/Http/Controllers/**/**/*.php')) ?: [],
        );

        $used = [];

        foreach ($sources as $file) {
            preg_match_all(
                "/middleware\('permission\.role:([^']+)'/",
                (string) file_get_contents($file),
                $matches
            );

            foreach ($matches[1] as $expression) {
                foreach (explode('|', $expression) as $name) {
                    $used[$name] = basename($file);
                }
            }
        }

        $this->assertNotEmpty($used, 'يُفترض أن تُحرَس متحكّمات الوحدة');

        // نطاقات البيانات كتالوجُها مستقلّ ومشترك بين الوحدات
        $scopes = ['data_scope_all', 'data_scope_region', 'data_scope_branch', 'data_scope_team'];
        // كشف الحساب صلاحية محاسبية تُجمع AND مع صلاحية الرواتب — ليست في كتالوج HR
        $foreign = ['account.statement.view'];

        foreach ($used as $name => $file) {
            if (in_array($name, $scopes, true) || in_array($name, $foreign, true)) {
                continue;
            }

            $this->assertContains(
                $name,
                $known,
                "الصلاحية «{$name}» في {$file} ليست في كتالوج HrPermissions"
            );
        }
    }
}
