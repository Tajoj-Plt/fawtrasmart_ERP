<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجلاتُ الصرف ونهايةُ الخدمة: فصلُ صلاحياتٍ كانت مجتمعة.
 *
 *   `salaries_payroll_retro` ← كانت «التسوية الرجعية» على صرفةٍ معلّقة تُحرس
 *       بـ`salaries_payroll_edit` (تعديل قسائم الرواتب)، و«الإيقاف» بـ
 *       `salaries_payroll_hold`. فمن مُنح تعديلَ القسائم ملك إضافةَ مبالغ
 *       تُصرف نقداً، وبقيت الصلاحيتان في الشاشة متشابكتين. صارت التسويةُ
 *       صلاحيةً مستقلّة عن الإيقاف وعن تعديل القسيمة.
 *   `salaries_eos_view` ← الاطّلاع على مكافآت نهاية الخدمة دون إدارتها.
 *   `salaries_eos_pay`  ← صرفُ المكافأة نقداً من خزينة — كانت تحت «الإدارة».
 *
 * وتُمنح لكلّ دورٍ يملك نظيرَها اليوم، فلا يُحجب أحدٌ عمّا كان يفتحه.
 * `down()` تسحب الارتباطات وحدها — الصلاحياتُ مصدرُها كتالوج `HrPermissions`.
 */
return new class extends Migration
{
    private const MAPPING = [
        'salaries_payroll_retro' => 'salaries_payroll_edit',
        'salaries_eos_view' => 'salaries_eos_manage',
        'salaries_eos_pay' => 'salaries_eos_manage',
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
