<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحياتُ «خط سيري اليومي» الثلاث — تُنشأ وتُربط بالأدوار التي تملك نظيرها.
 *
 * `permissions:sync` يُنشئ الصلاحية ولا يربطها بأي دور، و`hasPermission()`
 * يقرأ روابط الدور وحدها — فتبقى الشاشة مخفيّةً عن الجميع
 * ([[new-permissions-need-role-links]]). النظائر:
 *
 *   • عرض خط سيري اليومي   ← من يرى العملاء بخط السير اليومي أو يضيف خطّ سير
 *                            أو يدخل مساحة الزيارة.
 *   • إضافة عميل لخط سيري  ← من يملك «إضافة خط سير».
 *   • الإضافة لمندوبٍ آخر   ← من يملك «تعديل خط سير» — تعديلُ خطوط الآخرين
 *                            قائمٌ عنده أصلاً. أيُّ دورٍ آخر (المشرف مثلاً)
 *                            تُؤشَّر له من شاشة الأدوار.
 */
return new class extends Migration
{
    private const GRANTS = [
        'clients_daily_itinerary_view' => ['clients_view_by_daily_itinerary', 'clients_itinerary_add', 'clients_visit_workspace_access'],
        'clients_daily_itinerary_add_client' => ['clients_itinerary_add'],
        'clients_daily_itinerary_add_for_others' => ['clients_itinerary_edit'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::GRANTS as $name => $counterparts) {
            $permissionId = $this->permissionId($name);

            $roleIds = DB::table('role_has_permissions')
                ->whereIn('permission_id', DB::table('permissions')->whereIn('name', $counterparts)->where('guard_name', 'web')->select('id'))
                ->distinct()
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الحزمة غير مهيّأة في هذا السياق — الكاش سينتهي بنفسه
        }
    }

    public function down(): void
    {
        // لا نزعَ للروابط: لا سبيل لتمييز ما منحه هذا الترحيل عمّا منحته شاشة الأدوار بعده
    }

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
};
