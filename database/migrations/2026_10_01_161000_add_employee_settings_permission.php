<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيةُ شاشة «إعدادات الموظفين والهيكل التنظيمي».
 *
 * `HrPermissions::groups()` تعرّفها و`permissions:sync-catalog` يخلق صفَّها،
 * لكنّه **لا يمنحها لأيّ دور** — فالشاشةُ تبقى مغلقةً على الجميع حتى تُمنح
 * يدوياً من شاشة الأدوار. وهذا الترحيل يمنحها لمن يضبط إعداداتَ الموارد
 * البشرية فعلاً يومَ الترقية: حاملي `attendance_settings.manage` أو
 * `payroll_settings.manage`.
 *
 * ولا تُمنح لحاملي `employees.create`/`edit`: من يسجّل الموظفين ليس بالضرورة
 * من يضع قواعدَ ما يُطلب منهم — والقاعدةُ تسري على الجميع، فمن يملك تغييرَها
 * يملك تخفيفَ شرطٍ على نفسه.
 *
 * `down()` تسحب الروابطَ وتترك صفَّ الصلاحية: حذفُه يُسقط معه كلَّ ربطٍ
 * يدويٍّ أضافه المستخدمُ لاحقاً من شاشة الأدوار (نفس ملاحظة
 * 2026_09_01_140000).
 */
return new class extends Migration
{
    private const NEW_PERMISSION = 'employee_settings.manage';

    /** أيُّ واحدةٍ منها تكفي. */
    private const SOURCE_PERMISSIONS = [
        'attendance_settings.manage',
        'payroll_settings.manage',
    ];

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', self::NEW_PERMISSION)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => self::NEW_PERMISSION,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sourceIds = DB::table('permissions')
            ->whereIn('name', self::SOURCE_PERMISSIONS)
            ->where('guard_name', self::GUARD)
            ->pluck('id');

        if ($sourceIds->isEmpty()) {
            $this->forgetPermissionCache();

            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->whereIn('permission_id', $sourceIds)
            ->distinct()
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            // المفتاحُ مركّب، وإعادةُ تشغيل الترحيل تسقط بلا `insertOrIgnore`
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $id = DB::table('permissions')
            ->where('name', self::NEW_PERMISSION)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        }

        $this->forgetPermissionCache();
    }

    /** بلا هذا تبقى الصلاحيةُ غيرَ مرئيّةٍ حتى انتهاء ذاكرة Spatie المؤقّتة. */
    private function forgetPermissionCache(): void
    {
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
