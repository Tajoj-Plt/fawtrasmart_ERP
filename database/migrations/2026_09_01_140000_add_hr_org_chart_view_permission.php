<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * فصل صلاحية "الهيكل التنظيمي" عن "عرض الأقسام".
 *
 * `EmployeeReportController::organization()` (شجرة التسلسل الإداري —
 * `direct_manager_id`) كانت محروسة بـ`hr_departments_view`، وهي صلاحية
 * `OrganizationalStructure/DepartmentController` (شاشة الأقسام الحقيقية،
 * جدول `departments`) لا علاقة لها بالتسلسل الإداري. أي دور مُنح "عرض
 * الأقسام" فقط كان يرى بالخطأ أسماء كل الموظفين ومسمّياتهم وحالاتهم
 * الوظيفية عبر شاشة لا يُفترض أن تفتح له.
 *
 * `HrPermissions::groups()` تُعرّف الآن `hr_org_chart_view` مستقلةً، و
 * `permissions:sync-catalog` يخلق صفَّها في `permissions` — لكنه **لا
 * يمنحها لأي دور** (نفس ملاحظة 2026_08_27_100000). هذا الترحيل يمنحها
 * لكل دورٍ كان يملك `hr_departments_view` وقت تشغيله، فلا يفقد أحدٌ
 * وصولاً كان يستخدمه فعلياً يوم الترقية؛ من يريد فصل الصلاحيتين لاحقاً
 * يسحب إحداهما يدوياً من شاشة الأدوار.
 */
return new class extends Migration
{
    private const NEW_PERMISSION = 'hr_org_chart_view';

    private const SOURCE_PERMISSION = 'hr_departments_view';

    private const GUARD = 'web';

    public function up(): void
    {
        // تركيبٌ بلا جداول Spatie بعد — ترحيلُ الحزمة يسبق هذا الترحيل
        // دائماً، لكن الفحص أهون من ترحيلٍ يسقط ويوقف ما بعده
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $newPermissionId = DB::table('permissions')
            ->where('name', self::NEW_PERMISSION)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if (! $newPermissionId) {
            $newPermissionId = DB::table('permissions')->insertGetId([
                'name' => self::NEW_PERMISSION,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sourcePermissionId = DB::table('permissions')
            ->where('name', self::SOURCE_PERMISSION)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if (! $sourcePermissionId) {
            $this->forgetPermissionCache();

            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $sourcePermissionId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            // `insertOrIgnore` لا `insert`: المفتاح مركّب، وإعادة تشغيل
            // الترحيل على قاعدةٍ مُنحت الصلاحية سلفاً تسقط بلا هذا
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $newPermissionId,
                'role_id' => $roleId,
            ]);
        }

        $this->forgetPermissionCache();
    }

    /**
     * السحب يزيل الربط ويترك صفّ الصلاحية.
     *
     * حذف الصلاحية نفسها يُسقط معها كل ربطٍ يدويٍّ أضافه المستخدم لاحقاً
     * من شاشة الأدوار — وهو ما لا يعرفه هذا الترحيل ولا يملك إعادته.
     */
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

    /** بلا هذا تبقى الصلاحية غير مرئيةٍ حتى انتهاء ذاكرة Spatie المؤقّتة. */
    private function forgetPermissionCache(): void
    {
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
