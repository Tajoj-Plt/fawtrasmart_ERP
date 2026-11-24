<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحياتُ أزرار «الإقفال اليومي» الأربعة الجديدة — إلى من يملك أصلها.
 *
 * كانت خمسُ صلاحياتٍ تحرس ثمانية أزرار: «إقفال تلقائي» و«مراجعة ومطابقة
 * النظام» تحت `create` مع «الإقفال اليدوي»، و«طباعة PDF» تحت `print` مع
 * طباعة السطر، و«تقرير جماعي» تحت `bulk` مع الإقفال الجماعي. فُصل كلُّ زرٍّ
 * بصلاحيته، وهذا الترحيل يمنح كلّ جديدةٍ لكلّ دورٍ يملك الصلاحيةَ التي كانت
 * تحرس زرَّها — فلا يختفي زرٌّ عن أحدٍ كان يراه، ثم يُنزع ما يُراد من شاشة
 * الأدوار.
 *
 * `hasPermission()` تقرأ روابط الدور وحدها، و`permissions:sync` لا يربط شيئاً
 * — راجع [[new-permissions-need-role-links]].
 */
return new class extends Migration
{
    /** الصلاحية الجديدة ← الصلاحية التي كانت تحرس زرَّها. */
    private const MAPPING = [
        'sales.daily_closing.auto_close' => 'sales.daily_closing.create',
        'sales.daily_closing.review' => 'sales.daily_closing.create',
        'sales.daily_closing.print_report' => 'sales.daily_closing.print',
        'sales.daily_closing.bulk_report' => 'sales.daily_closing.bulk',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::MAPPING as $name => $counterpart) {
            $permissionId = $this->permissionId($name, true);
            $counterpartId = $this->permissionId($counterpart, false);

            if (! $permissionId || ! $counterpartId) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $counterpartId)
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

        // الروابط وحدها — الصلاحياتُ نفسُها ملكُ كتالوج `SalesPermissions`
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
