<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ApiKeyPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SubscriptionPermissionsSeeder;
use Database\Seeders\SystemMaintenancePermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Installer\Services\SettingsInstaller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * دور حساب التثبيت وصلاحياته.
 *
 * الجداول تُبنى هنا يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي
 * `ALTER TABLE … MODIFY` وهي صيغة MySQL لا يفهمها sqlite الذي يفرضه
 * `phpunit.xml`، فتتعثّر قبل أن تصل إلى ما يخصّ هذا الاختبار. والمطلوب
 * هنا ستة جداول: المستخدمون وجداول spatie.
 */
class InstallerAdminRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('phone')->nullable();
            $t->string('role')->default('employee');
            $t->unsignedBigInteger('role_id')->nullable();
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

        // RolesSeeder ينشئ الصلاحيات الأساسية بنفسه، ثم تمنح المتخصّصةُ
        // `owner` ما تحجبه عن `manager` — وهي الفجوة محلّ الاختبار
        $this->seed(RolesSeeder::class);
        $this->seed(SystemMaintenancePermissionsSeeder::class);
        $this->seed(ApiKeyPermissionsSeeder::class);
        $this->seed(SubscriptionPermissionsSeeder::class);
    }

    protected function manager(): Role
    {
        return Role::where('name', 'manager')->where('guard_name', 'web')->firstOrFail();
    }

    public function test_installer_admin_gets_manager_role_and_role_id(): void
    {
        $user = (new SettingsInstaller)->createAdministrator([
            'name' => 'مدير',
            'email' => 'admin@example.test',
            'password' => 'secret-pass',
        ]);

        $this->assertNotNull($user);
        $this->assertSame('manager', $user->role);
        $this->assertSame($this->manager()->id, $user->role_id, 'role_id يشير إلى دور manager');
        $this->assertTrue($user->hasRole('manager'));
        $this->assertFalse($user->hasRole('owner'));
        $this->assertTrue($user->primaryRole->is($this->manager()));
    }

    public function test_owner_only_permissions_reach_the_user_not_the_manager_role(): void
    {
        // الفجوة الحقيقية أضيق ممّا توحي به ثوابت `OWNER_ONLY`: بعضها في
        // `JobRole::$job_roles` أصلاً، و`RolesSeeder` يمنح الأساس كاملاً لكل
        // دورٍ `full_access` — فيصل `manager` قبل أن يحجبه السيدر المتخصّص.
        // فتُحسب هنا من الدورين لا من قائمةٍ مكتوبة، كما تحسبها الخدمة.
        $before = $this->manager()->permissions->pluck('name');
        $gap = Role::where('name', 'owner')->firstOrFail()->permissions->pluck('name')->diff($before);

        $this->assertNotEmpty($gap, 'يُفترض وجود صلاحياتٍ يحتكرها owner، وإلا فالاختبار لا يقيس شيئاً');

        $user = (new SettingsInstaller)->createAdministrator([
            'email' => 'admin@example.test',
            'password' => 'secret-pass',
        ]);

        foreach ($gap as $permission) {
            $this->assertTrue($user->can($permission), "حساب التثبيت يجب أن يملك {$permission}");
        }

        // ودور manager لم يرتفع: المنح ذهب إلى `model_has_permissions` وحده
        $this->assertEqualsCanonicalizing(
            $before->all(),
            $this->manager()->fresh()->permissions->pluck('name')->all(),
            'صلاحيات دور manager يجب ألّا تتغيّر'
        );

        // فمديرٌ آخر يُنشأ لاحقاً يبقى على حدوده المقصودة
        $other = User::create([
            'name' => 'مدير آخر', 'email' => 'other@example.test', 'password' => 'x',
            'role' => 'manager', 'role_id' => $this->manager()->id,
        ]);
        $other->syncRoles([$this->manager()]);

        foreach ($gap as $permission) {
            $this->assertFalse($other->can($permission), "مديرٌ عادي يجب ألّا يملك {$permission}");
        }
    }

    public function test_it_is_idempotent(): void
    {
        $installer = new SettingsInstaller;
        $first = $installer->createAdministrator(['email' => 'admin@example.test', 'password' => 'a']);
        $second = $installer->createAdministrator(['email' => 'admin@example.test', 'password' => 'a']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, User::where('email', 'admin@example.test')->count());
        $this->assertCount(1, $second->fresh()->roles);
    }

    public function test_it_skips_without_credentials(): void
    {
        $this->assertNull((new SettingsInstaller)->createAdministrator([]));
        $this->assertSame(0, User::count());
    }
}
