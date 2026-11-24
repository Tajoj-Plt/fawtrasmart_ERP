<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيتا «طباعة مسيرات وقسائم الرواتب» و«تصدير مسير الرواتب إلى Excel».
 *
 * كانت الطباعةُ والتصديرُ يُحرسان بصلاحية العرض وحدها — فمن يطّلع على
 * المسير يُخرج رواتبَ الجميع ملفّاً. صارتا صلاحيتين مستقلّتين، وتُمنحان
 * هنا لكلّ دورٍ يملك نظيرَهما (نفس منطق
 * `2026_08_27_130000_grant_sales_order_and_debit_note_permissions`):
 *
 *   `salaries_payroll_export` ← كلّ دورٍ يملك `salaries_payroll_view`
 *   `salaries_payroll_print`  ← كلّ دورٍ يملك `salaries_payroll_view`
 *                               أو `salaries_payroll_view_own` (يطبع قسيمته هو)
 *
 * فلا يفقد أحدٌ ما كان يفعله أمس، ويصير سحبُهما ممكناً من شاشة الأدوار.
 * `down()` تسحب الارتباطات وحدها.
 */
return new class extends Migration
{
    private const MAPPING = [
        'salaries_payroll_export' => ['salaries_payroll_view'],
        'salaries_payroll_print' => ['salaries_payroll_view', 'salaries_payroll_view_own'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::MAPPING as $name => $counterparts) {
            $permissionId = $this->permissionId($name, true);

            $counterpartIds = DB::table('permissions')
                ->whereIn('name', $counterparts)
                ->where('guard_name', 'web')
                ->pluck('id');

            if (! $permissionId || $counterpartIds->isEmpty()) {
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
