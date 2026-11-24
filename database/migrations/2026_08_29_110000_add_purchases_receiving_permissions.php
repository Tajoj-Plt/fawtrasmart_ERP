<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيات «استلام المشتريات» — إلى الأدوار التي تملك نظيرَها.
 *
 * نفس منطق `2026_08_27_130000_grant_sales_order_and_debit_note_permissions.php`:
 * `permissions:sync` يُنشئ صفّ الصلاحية في `permissions` ولا يربطها بدور،
 * فميزةٌ لا يفتحها أحد ليست ميزة. الاستلام مستندٌ جديدٌ بين أمر الشراء
 * والفاتورة، فمن يملك أيّاً من الاثنين — لا كليهما بالضرورة — يُفترض أنه
 * يحتاج فتح الاستلام أيضاً؛ لذا يُمنَح كل دورٍ يملك **أحد** نظيريه.
 */
return new class extends Migration
{
    /**
     * الصلاحية الجديدة ← نظيراها في «أوامر الشراء» و«فواتير المشتريات».
     * من يملك أيّاً منهما يأخذ صلاحية الاستلام المقابلة.
     */
    private const MAPPING = [
        'purchases.receiving.view' => ['purchases.order_requests.view', 'purchases.invoices.view'],
        'purchases.receiving.create' => ['purchases.order_requests.create', 'purchases.invoices.create'],
        'purchases.receiving.edit' => ['purchases.order_requests.edit', 'purchases.invoices.edit'],
        'purchases.receiving.delete' => ['purchases.order_requests.delete', 'purchases.invoices.delete'],
        'purchases.receiving.approve' => ['purchases.order_requests.approve_reject', 'purchases.invoices.approve'],
        'purchases.receiving.convert' => ['purchases.order_requests.convert'],
        'purchases.receiving.export' => ['purchases.order_requests.export', 'purchases.invoices.export'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::MAPPING as $name => $counterparts) {
            $permissionId = $this->permissionId($name, true);

            if (! $permissionId) {
                continue;
            }

            $roleIds = collect();

            foreach ($counterparts as $counterpart) {
                $counterpartId = $this->permissionId($counterpart, false);

                if ($counterpartId) {
                    $roleIds = $roleIds->merge(
                        DB::table('role_has_permissions')->where('permission_id', $counterpartId)->pluck('role_id')
                    );
                }
            }

            foreach ($roleIds->unique() as $roleId) {
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
            ->whereIn('name', array_keys(self::MAPPING))
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    /** معرّف الصلاحية — وتُنشأ إن غابت وكان `$create` صحيحاً. */
    private function permissionId(string $name, bool $create): ?int
    {
        $id = DB::table('permissions')
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->value('id');

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
