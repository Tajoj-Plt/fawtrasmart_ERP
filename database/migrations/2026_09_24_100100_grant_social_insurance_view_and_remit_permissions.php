<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * التأمينات الاجتماعية: فصلُ الاطّلاع والسداد عن ضبط الإعدادات.
 *
 * كانت `salaries_statutory_manage` تحرس ثلاثةَ أشياء مختلفة الخطر معاً:
 * تغييرَ النسب والحسابات، والاطّلاعَ على الذمّتين وكشف الاشتراكات،
 * وإخراجَ النقد من الخزينة سداداً. فمن احتاج أن يرى المستحقَّ لزمه أن
 * يُمنح تغييرَ النسب وصرفَ النقد.
 *
 *   `salaries_statutory_view`  ← الاطّلاع على شاشة السداد وكشف الاشتراكات وتصديره
 *   `salaries_statutory_remit` ← تسجيل دفعة سداد (قيد + سند صرف)
 *
 * وتُمنحان لكلّ دورٍ يملك `salaries_statutory_manage` اليوم، فلا يُحجب أحدٌ
 * عمّا كان يفتحه. وما يُمنح يبقى قابلاً للسحب من شاشة الأدوار.
 */
return new class extends Migration
{
    private const MAPPING = [
        'salaries_statutory_view' => 'salaries_statutory_manage',
        'salaries_statutory_remit' => 'salaries_statutory_manage',
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

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::MAPPING))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

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
