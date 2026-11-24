<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيتا «تقرير التبديلات» و«تقرير العمولات» — إلى الأدوار التي تملك نظيرَهما.
 *
 * `permissions:sync` يُنشئ الصلاحية ولا يربطها بدور، و`hasPermission()` تقرأ
 * دورَ المستخدم وحده — فتقريرٌ جديدٌ بلا ترحيلٍ كهذا مخفيٌّ عن الجميع حتى
 * صاحب النظام (انظر `2026_08_27_130000_grant_sales_order_and_debit_note_permissions`).
 *
 * المنحُ بالنظير لا بالتعميم — تكفي صلاحيةٌ واحدةٌ من نظائرها:
 *
 *   `sales.reports.exchanges`   ← من يملك لوحة متابعة التبديل، أو تقرير البنود
 *                                 (التبديلُ حركةُ أصنافٍ عند العميل)
 *   `sales.reports.commissions` ← من يملك «العمولات المصروفة» في الموارد
 *                                 البشرية، أو تسوية أهداف المبيعات أو التحصيلات
 *
 * `down()` تسحب الارتباطات وحدها — الصلاحيةُ مصدرُها كتالوج `SalesPermissions`.
 */
return new class extends Migration
{
    private const MAPPING = [
        'sales.reports.exchanges' => ['sales.exchanges.dashboard', 'sales.reports.by_item'],
        'sales.reports.commissions' => ['commissions.index', 'sales.targets.settle', 'sales.collections_targets.settle'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::MAPPING as $name => $counterparts) {
            $permissionId = $this->permissionId($name);

            $counterpartIds = DB::table('permissions')
                ->whereIn('name', $counterparts)
                ->where('guard_name', 'web')
                ->pluck('id');

            if ($counterpartIds->isEmpty()) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->whereIn('permission_id', $counterpartIds)
                ->distinct()
                ->pluck('role_id');

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

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::MAPPING))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    /** معرّف الصلاحية — تُنشأ إن غابت، فلا تتوقّف الميزة على `permissions:sync`. */
    private function permissionId(string $name): int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

        return $id ? (int) $id : (int) DB::table('permissions')->insertGetId([
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
