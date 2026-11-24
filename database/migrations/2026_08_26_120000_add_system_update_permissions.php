<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيات شاشة «تحديث النظام».
 *
 * لماذا في ترحيلٍ لا في السيدر وحده؟ لأن الشاشة نفسها هي ما يشغّل السيدرات
 * على خادمٍ بلا سطر أوامر — فلو كانت صلاحيتُها في سيدرٍ فقط لدارت الحلقة:
 * لا تُفتَح الشاشة حتى يُشغَّل السيدر، ولا يُشغَّل السيدر إلا من الشاشة.
 * الترحيل يكسر الحلقة: أول `migrate` — بأي وسيلة جرى — يمنح الصلاحية.
 *
 * `SystemMaintenancePermissionsSeeder` يبقى المصدر المعلن للقائمة، وهذا
 * الترحيل يطابقه. تشغيل أيّهما بعد الآخر لا يضرّ: كلاهما يضيف ولا يسحب.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'settings.system_update.view',
        'settings.system_update.migrate',
        'settings.system_update.seed',
    ];

    /** الأدوار التي تُمنَح الصلاحية — أعلى ثلاثة أدوارٍ إدارية في النظام. */
    private const ROLES = ['owner', 'manager', 'admin'];

    public function up(): void
    {
        // تركيبٌ بلا جداول Spatie بعد — ترحيلُ الحزمة يسبق هذا الترحيل
        // دائماً، لكن الفحص أهون من ترحيلٍ يسقط ويوقف ما بعده
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $roleIds = DB::table('roles')
                ->whereIn('name', self::ROLES)
                ->where('guard_name', 'web')
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                // `insertOrIgnore` لا `insert`: المفتاح مركّب، وإعادة تشغيل
                // الترحيل على قاعدةٍ منحت الصلاحية من السيدر تسقط بلا هذا
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    /**
     * السحب يزيل الربط ويترك صفوف الصلاحيات.
     *
     * حذف الصلاحية نفسها يُسقط معها كل ربطٍ يدويٍّ أضافه المستخدم لأدوارٍ
     * أخرى من شاشة الأدوار — وهو ما لا يعرفه هذا الترحيل ولا يملك إعادته.
     */
    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    /** بلا هذا تبقى الصلاحية غير مرئيةٍ حتى انتهاء ذاكرة Spatie المؤقّتة. */
    private function forgetPermissionCache(): void
    {
        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
