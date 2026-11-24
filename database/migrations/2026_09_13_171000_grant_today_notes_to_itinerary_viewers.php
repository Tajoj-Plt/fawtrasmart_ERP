<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «ملاحظات اليوم» لكل دورٍ يرى خطوط السير.
 *
 * مسارُ الشاشة داخل مجموعة خط السير، فيمرّ بحارسين معاً: حارسُ المجموعة
 * (`clients_itinerary_view`) وحارسُه هو (`clients_itinerary_today_notes_view`).
 * المشرفُ يملك الأوّل وحده، فكانت الشاشة تُرجع «User does not have the right
 * permissions.» وهو يرى خطوط فريقه. النظيرُ بالاسم لا برقم الدور — الأرقامُ
 * تختلف بين قاعدةٍ وأخرى ([[new-permissions-need-role-links]]).
 */
return new class extends Migration
{
    private const GRANTS = [
        'clients_itinerary_today_notes_view' => ['clients_itinerary_view'],
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
