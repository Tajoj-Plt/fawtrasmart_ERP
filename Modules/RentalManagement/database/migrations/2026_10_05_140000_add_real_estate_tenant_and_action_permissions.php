<?php

use App\Constants\permissions\RealEstatePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات العقارات الجديدة — تُنشأ وتُمنح لمن يملك نظيرها.
 *
 * المستأجرون كانوا يركبون صلاحيات «علاقات العملاء»؛ صار لهم مجموعتهم
 * (`realestate.tenants.*`). ومعها صلاحيات إجراءاتٍ جديدة: إلغاء تحويل المالك،
 * تسوية التأمين، إدارة المجمعات، إضافة الوحدات بالجملة.
 *
 * `permissions:sync` يُنشئ الصلاحية ولا يربطها بدور — فبلا هذا الترحيل يُحجب
 * المستأجرون عن كلّ من كان يراهم. فكلّ صلاحيةٍ جديدة تُمنح لحامل نظيرها
 * القائم، ولا يُنزع شيء. قابلٌ للتكرار.
 */
return new class extends Migration
{
    /** الصلاحية الجديدة => النظير الذي يُمنح حاملوه إيّاها. */
    private const GRANTS = [
        'realestate.tenants.view' => 'realestate.relationships.view',
        'realestate.tenants.show' => 'realestate.relationships.view',
        'realestate.tenants.create' => 'realestate.relationships.create',
        'realestate.tenants.edit' => 'realestate.relationships.edit',
        'realestate.owners.cancel_transfer' => 'realestate.owners.transfer',
        'realestate.payments.settle_deposit' => 'realestate.contracts.terminate',
        'realestate.buildings.complexes' => 'realestate.buildings.edit',
        'realestate.units.bulk_create' => 'realestate.units.create',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::GRANTS as $permission => $counterpart) {
            $permissionId = $this->permissionId($permission);
            $counterpartId = DB::table('permissions')->where('name', $counterpart)->where('guard_name', 'web')->value('id');

            if (! $counterpartId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $counterpartId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }

            // منحٌ مباشر للمستخدم (خارج الدور) يُحترم كذلك
            if (Schema::hasTable('model_has_permissions')) {
                foreach (DB::table('model_has_permissions')->where('permission_id', $counterpartId)->get() as $row) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId, 'model_type' => $row->model_type, 'model_id' => $row->model_id,
                    ]);
                }
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::GRANTS))->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        }

        $this->forgetPermissionCache();
    }

    /** معرّف الصلاحية — تُنشأ مصنَّفةً إن غابت، فلا تتوقّف الميزة على `permissions:sync`. */
    private function permissionId(string $name): int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('permissions')->insertGetId(
            ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()] + $this->catalogColumns($name)
        );
    }

    /** أعمدة التصنيف من الكتالوج — إن كانت في الجدول؛ وإلّا يكتبها `permissions:sync-catalog`. */
    private function catalogColumns(string $name): array
    {
        if (! Schema::hasColumn('permissions', 'group_key')) {
            return [];
        }

        try {
            $module = RealEstatePermissions::module();

            foreach (RealEstatePermissions::groups() as $key => $group) {
                if (isset($group['permissions'][$name])) {
                    return [
                        'module' => $module['key'], 'module_label' => $module['label'],
                        'group_key' => $key, 'group_label' => $group['label'], 'label' => $group['permissions'][$name],
                    ];
                }
            }
        } catch (Throwable $e) {
            // الكتالوج غير متاح في هذا السياق — يبقى الصفّ بلا تصنيف حتى المزامنة
        }

        return [];
    }

    private function forgetPermissionCache(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable $e) {
            // الحزمة غير مهيّأة في هذا السياق — الكاش سينتهي بنفسه
        }
    }
};
