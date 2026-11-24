<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * السلف: فصلُ الصرف والتحصيل والإلغاء عن الاعتماد.
 *
 * كان `advance_requests_approve` يحرس البتَّ في الطلب وإخراجَ النقد من
 * الخزينة وتحصيلَه وإلغاءَ السلفة معاً — فمن مُنح أن يوافق مُنح أن يصرف.
 *
 *   `advance_requests_disburse` ← صرفُ سلفةٍ معتمدة من الخزينة (قيد صرف)
 *   `advance_requests_settle`   ← تحصيلُ سدادٍ نقديٍّ من الموظف (قيد تحصيل)
 *   `advance_requests_cancel`   ← إلغاءُ سلفةٍ (بقيدٍ عكسيٍّ إن كانت صُرفت)
 *
 * وتُمنح لكلّ دورٍ يملك `advance_requests_approve` اليوم، فلا يُحجب أحدٌ
 * عمّا كان يفعله. وما يُمنح يبقى قابلاً للسحب من شاشة الأدوار.
 */
return new class extends Migration
{
    private const MAPPING = [
        'advance_requests_disburse' => 'advance_requests_approve',
        'advance_requests_settle' => 'advance_requests_approve',
        'advance_requests_cancel' => 'advance_requests_approve',
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
