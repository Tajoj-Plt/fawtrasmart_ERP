<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحية «عرض ملاحظات اليوم» (`clients_itinerary_today_notes_view`) — إلى
 * كل دورٍ يملك نظيرَها `clients_itinerary_edit`.
 *
 * كانت هذه الشاشة محروسةً بشرطٍ ثابتٍ في الكود (`! isEmployee()`) لا
 * بصلاحية — فتفعيلها/إخفاؤها لم يكن ممكناً من شاشة الأدوار. صارت صلاحيةً
 * كأي صلاحيةٍ أخرى، ونُسِبت بالنظير لا بالتعميم: `clients_itinerary_edit`
 * هو الفارق الفعلي بين أدوار الإدارة (owner/manager/admin) ودور الموظف في
 * جدول الأدوار الحالي — فكل دورٍ يملكها يملك الآن رؤية ملاحظات اليوم أيضاً.
 *
 * راجع [[new-permissions-need-role-links]]: `permissions:sync` يُنشئ
 * الصلاحية ولا يربطها بدور، و`hasPermission()` تقرأ `role_has_permissions`
 * فقط — فبلا هذا الترحيل تبقى الصلاحية موجودةً في القائمة وممنوحةً لصفر
 * أحد، والزر يختفي عن الجميع بمن فيهم صاحب النظام.
 */
return new class extends Migration
{
    private const NEW_PERMISSION = 'clients_itinerary_today_notes_view';

    private const COUNTERPART = 'clients_itinerary_edit';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = $this->permissionId(self::NEW_PERMISSION, true);
        $counterpartId = $this->permissionId(self::COUNTERPART, false);

        if (! $permissionId || ! $counterpartId) {
            return;
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

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', self::NEW_PERMISSION)->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        }

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
