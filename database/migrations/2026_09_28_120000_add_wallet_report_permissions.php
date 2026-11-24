<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحياتُ تقارير 2026-09-28: محافظُ العملاء، ومحافظُ الموردين، والمنافسون،
 * والعملاءُ المسنَدون للموظفين.
 *
 * `permissions:sync` يُنشئ الصلاحية ولا يربطها بدور، و`hasPermission()` تقرأ
 * ربطَ الأدوار وحدَه — فيبقى التقريرُ مخفيّاً عن الجميع. فالربطُ هنا: كلُّ دورٍ
 * يملك تقريرَ الأرصدة المجاور **أو** عرضَ المحافظ نفسها يُمنح تقريرَها.
 *
 * والمتحكّمان يقبلان صلاحيةَ عرض المحافظ أيضاً، فلا يُحجب التقريرُ قبل
 * تشغيل هذا المُهاجر عمّن يرى المحافظ أصلاً.
 */
return new class extends Migration
{
    /** الصلاحيةُ ← [بياناتُها الوصفية، الصلاحياتُ التي يُمنح بها] */
    private const PERMISSIONS = [
        'clients.reports.wallets' => [
            'label' => 'تقرير محافظ العملاء',
            'module' => 'clients', 'module_label' => 'العملاء',
            'group_key' => 'reports', 'group_label' => 'تقارير العملاء',
            'sort_order' => 300092,
            'granted_with' => ['clients.reports.balances', 'clients.wallet.view'],
        ],
        'clients.reports.competitors' => [
            'label' => 'تقرير المنافسين',
            'module' => 'clients', 'module_label' => 'العملاء',
            'group_key' => 'reports', 'group_label' => 'تقارير العملاء',
            'sort_order' => 300093,
            'granted_with' => ['clients.reports.balances', 'clients_competitors_view'],
        ],
        'clients.reports.assignments' => [
            'label' => 'تقرير العملاء المسندين للموظفين',
            'module' => 'clients', 'module_label' => 'العملاء',
            'group_key' => 'reports', 'group_label' => 'تقارير العملاء',
            'sort_order' => 300094,
            'granted_with' => ['clients.reports.balances'],
        ],
        'purchases.reports.supplier_wallets' => [
            'label' => 'تقرير محافظ الموردين',
            'module' => 'purchases', 'module_label' => 'المشتريات',
            'group_key' => 'reports', 'group_label' => 'تقارير المشتريات',
            'sort_order' => 450083,
            'granted_with' => ['purchases.reports.supplier_balances', 'purchases.supplier_wallet.view'],
        ],
    ];

    public function up(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $name => $meta) {
            if (! DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'module' => $meta['module'],
                    'module_label' => $meta['module_label'],
                    'group_key' => $meta['group_key'],
                    'group_label' => $meta['group_label'],
                    'label' => $meta['label'],
                    'sort_order' => $meta['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            $roleIds = DB::table('role_has_permissions as rp')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->whereIn('p.name', $meta['granted_with'])
                ->distinct()
                ->pluck('rp.role_id');

            // `insertOrIgnore`: شاشةُ الأدوار قد تكون منحتها يدوياً قبل هذا
            DB::table('role_has_permissions')->insertOrIgnore(
                $roleIds->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->all()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** يفكّ الربطَ وحدَه — الصفُّ ملكُ الكتالوج (`permissions:sync`) */
    public function down(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function tablesExist(): bool
    {
        $schema = DB::getSchemaBuilder();

        return $schema->hasTable('permissions') && $schema->hasTable('role_has_permissions');
    }
};
