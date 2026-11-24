<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحية «تغيير دوام مسؤول العميل بعد تسجيله» (`clients_change_contact_shift`)
 * — إلى كل دورٍ يستطيع فتح مساحة عمل الزيارة.
 *
 * «ودي يكون يسوي تعديل لدوام الموظف من مساحة الزيارة»: زرّ تعديل الدوام في
 * مساحة الزيارة كان يختفي عن المندوب متى كان لموظف العميل دوامٌ مسجَّل، لأنّ
 * الصلاحية لم تُربط إلا بـowner/manager/admin. والمندوب هو من يقف أمام
 * موظف العميل ويعرف أنّ دوامه تغيّر.
 *
 * النظير هنا أيٌّ من الصلاحيات الأربع التي تحرس راوت `clients.visitWorkspace`
 * و`clients.visits.storeContactShift` نفسه — من يدخل الزيارة يعدّل الدوام
 * منها. الصلاحية باقيةٌ في شاشة الأدوار: من أراد حجبها عن دورٍ ينزعها منه.
 *
 * راجع [[new-permissions-need-role-links]].
 */
return new class extends Migration
{
    private const NEW_PERMISSION = 'clients_change_contact_shift';

    private const COUNTERPARTS = [
        'clients_visits_track',
        'clients_itinerary_add',
        'clients_visit_workspace_access',
        'clients_must_visit_before_view',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = $this->permissionId(self::NEW_PERMISSION, true);

        $counterpartIds = DB::table('permissions')
            ->whereIn('name', self::COUNTERPARTS)
            ->where('guard_name', 'web')
            ->pluck('id');

        if (! $permissionId || $counterpartIds->isEmpty()) {
            return;
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

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        // لا نزعَ للروابط عند التراجع: owner/manager/admin كانوا يملكونها قبل هذا
        // الترحيل، ولا سبيل لتمييز ما منحه هو عمّا منحته شاشة الأدوار.
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
