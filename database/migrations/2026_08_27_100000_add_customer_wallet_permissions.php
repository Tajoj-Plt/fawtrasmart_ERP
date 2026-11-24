<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيات شاشة «محفظة العملاء» في المبيعات.
 *
 * `SalesPermissions::groups()` تُعرّف `sales.customer_wallet.view` و
 * `sales.customer_wallet.settle` منذ إضافة الشاشة، و`permissions:sync-catalog`
 * يخلق صفَّيهما في جدول `permissions` — لكنه **لا يمنح صلاحيةً لدور**
 * (التوثيق في الأمر نفسه صريح بذلك). فبقيت الشاشة محجوبةً عن الجميع: لا
 * دور يحمل الصلاحية فتظهر رسالة «لا تملك صلاحية» حتى لصاحب النظام.
 *
 * هذا الترحيل يمنحها — بنفس نمط 2026_08_26_120000 لصلاحيات «تحديث النظام»:
 * يُنشئ صفَّ الصلاحية إن غاب، ثم يربطه بالأدوار المناسبة. تشغيله بعد
 * `permissions:sync-catalog` أو قبله لا يضرّ: كلاهما يضيف ولا يسحب.
 *
 * **توزيع الأدوار** يطابق نظائره في نفس الوحدة: العرض بعرض «الإقفال
 * اليومي» (`sales.daily_closing.view`) — كل من يعمل بالمبيعات. والتسوية
 * بعرض «الإقفال الجماعي» (`sales.daily_closing.bulk`) — الإدارة وحدها،
 * لأنها فعلٌ يكتب قيداً محاسبياً لا مجرّد اطّلاع.
 */
return new class extends Migration
{
    /** صلاحية => الأدوار التي تُمنَحها. */
    private const GRANTS = [
        'sales.customer_wallet.view' => ['owner', 'manager', 'admin', 'employee', 'مدير مبيعات', 'مندوب مبيعات'],
        'sales.customer_wallet.settle' => ['owner', 'manager', 'admin', 'مدير مبيعات'],
    ];

    public function up(): void
    {
        // تركيبٌ بلا جداول Spatie بعد — ترحيلُ الحزمة يسبق هذا الترحيل
        // دائماً، لكن الفحص أهون من ترحيلٍ يسقط ويوقف ما بعده
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::GRANTS as $name => $roles) {
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
                ->whereIn('name', $roles)
                ->where('guard_name', 'web')
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                // `insertOrIgnore` لا `insert`: المفتاح مركّب، وإعادة تشغيل
                // الترحيل على قاعدةٍ مُنحت الصلاحية سلفاً تسقط بلا هذا
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
            ->whereIn('name', array_keys(self::GRANTS))
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
