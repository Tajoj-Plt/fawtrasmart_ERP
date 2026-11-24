<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيات بوابة العميل وإعفاءات شاشة العرض.
 *
 * ── لماذا ترحيلٌ ولا يكفي `permissions:sync-catalog`؟ ──
 *
 * الأمرُ يُنشئ صفَّ الصلاحية ولا يربطها بدور، و`hasPermission()` تقرأ دور
 * المستخدم (`users.role_id` ← `role_has_permissions`). فصلاحيةٌ جديدةٌ بلا
 * ربطٍ تبقى بلا حاملٍ واحد، وزرُّها لا يظهر لأحد — وهو ما وقع فعلاً مع
 * `clients_portal_preview`: أُنشئت، ولم يرَ زرَّ «معاينة كعميل» أحد.
 * نفس درس `2026_09_02_090100_add_visit_justification_period_permissions`.
 *
 * ── ما يُمنح وما لا يُمنح ──
 *
 * **يُمنح بالنظير**: `clients_portal_preview` لكل دورٍ يحمل
 * `clients_view_all_clients`. من يرى كل العملاء ويدعمهم هو من يحتاج أن
 * يرى شاشتهم؛ والمعاينة محصورةٌ في البوابة ومسجَّلةٌ في سجلّ النشاط، فليست
 * توسيعَ سلطة بقدر ما هي نافذةُ اطّلاع.
 *
 * **لا يُمنح لأحد**: `clients_exempt_action_locks` — إعفاءٌ من ضابط
 * («سجّل زيارتك قبل أن تعمل على العميل»)، ومجموعةُ «الإعفاءات» في الكتالوج
 * منصوصٌ فيها أنها تُمنح فرداً فرداً لا مع الدور كاملاً. يُنشأ الصفُّ فقط
 * ليظهر في شاشة الأدوار، ويمنحه صاحبُ النظام لمن يقصده.
 *
 * `down()` تفكّ الروابط ولا تحذف الصفوف — حذفُ صلاحيةٍ يسحبها من كل دورٍ
 * يحملها، وذلك قرارُ إنسانٍ لا أثرُ تراجعٍ عن ترحيل.
 */
return new class extends Migration
{
    /** يُمنح لكل دورٍ يحمل هذه الصلاحية. */
    private const ANCHOR = 'clients_view_all_clients';

    private const GRANTED_LIKE_ANCHOR = [
        'clients_portal_preview',
    ];

    /** يُنشأ الصفّ ولا يُربط بدور — يمنحه صاحبُ النظام عمداً. */
    private const CREATED_ONLY = [
        'clients_exempt_action_locks',
        'clients_exempt_visit_before_view',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::CREATED_ONLY as $name) {
            $this->permissionId($name, true);
        }

        $anchorId = $this->permissionId(self::ANCHOR, false);

        $roleIds = $anchorId
            ? DB::table('role_has_permissions')->where('permission_id', $anchorId)->pluck('role_id')
            : collect();

        foreach (self::GRANTED_LIKE_ANCHOR as $name) {
            $permissionId = $this->permissionId($name, true);

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', [...self::GRANTED_LIKE_ANCHOR, ...self::CREATED_ONLY])
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    private function permissionId(string $name, bool $create): ?int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

        if ($id || ! $create) {
            return $id ? (int) $id : null;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function forgetPermissionCache(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الحزمة غير مهيّأة في هذا السياق — الكاش سينتهي بنفسه
        }
    }
};
