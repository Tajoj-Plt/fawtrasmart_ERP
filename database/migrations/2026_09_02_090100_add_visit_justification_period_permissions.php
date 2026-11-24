<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيات «تبريرات/إعفاءات الزيارات» الجديدة — الحبيبية بدل الصلاحية
 * الواحدة الشاملة `employee_visit_justifications_view` (كانت وحدها تحرس
 * كل شيء: العرض، الاعتماد، الرفض، الاعتماد الجماعي — راجع الروتات القديمة
 * في Modules/Client/routes/web.php قبل هذا التغيير).
 *
 * ── نفس مشكلة `2026_08_27_130000_grant_sales_order_and_debit_note_permissions` ──
 * `permissions:sync` يُنشئ الصلاحية ولا يربطها بدور، و`hasPermission()`
 * تقرأ دور المستخدم (`role_id` ← `role_has_permissions`) لا Spatie مباشرة.
 *
 * ── المنح بالنظير ──
 * كل دورٍ كان يملك `employee_visit_justifications_view` كان بالفعل يملك
 * فعلياً كل قدرات الشاشة الإدارية (عرض/اعتماد/رفض — الروتات القديمة كلها
 * كانت محروسةً بنفس الصلاحية الواحدة)، فيُمنح نفس الدور الآن الصلاحياتِ
 * الخمس الجديدة كلَّها (view_all/create/edit/delete/approve) حفاظاً على
 * قدرته الحالية بلا نقصان.
 *
 * ── `view_own` استثناءٌ: منحٌ عامّ لا بالنظير ──
 * شاشات «تبريراتي» الذاتية-الخدمة (`incomplete.visits.*`/
 * `employee.visit-justifications.*`) لم تكن محروسةً بأي صلاحيةٍ إطلاقاً —
 * أي موظفٍ مسجّلٍ دخوله يصلها (مقيّدةً فقط بـ employee_id في المتحكّم).
 * فرضُ صلاحيةٍ جديدة عليها الآن دون منحها لكل الأدوار الحالية كان سيحجب
 * موظفين عن الشاشة الوحيدة التي تُخرجهم من حجب `CheckIncompleteVisits` —
 * قفلٌ دائم. فتُمنح لكل الأدوار الموجودة، لا بالنظير.
 */
return new class extends Migration
{
    private const OLD_PERMISSION = 'employee_visit_justifications_view';

    private const GRANTED_LIKE_OLD = [
        'clients_visit_justifications_view_all',
        'clients_visit_justifications_create',
        'clients_visit_justifications_edit',
        'clients_visit_justifications_delete',
        'clients_visit_justifications_approve',
    ];

    private const GRANTED_TO_ALL_ROLES = [
        'clients_visit_justifications_view_own',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $oldId = $this->permissionId(self::OLD_PERMISSION, false);
        $roleIdsWithOld = $oldId
            ? DB::table('role_has_permissions')->where('permission_id', $oldId)->pluck('role_id')
            : collect();

        foreach (self::GRANTED_LIKE_OLD as $name) {
            $permissionId = $this->permissionId($name, true);
            foreach ($roleIdsWithOld as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $allRoleIds = Schema::hasTable('roles') ? DB::table('roles')->pluck('id') : collect();

        foreach (self::GRANTED_TO_ALL_ROLES as $name) {
            $permissionId = $this->permissionId($name, true);
            foreach ($allRoleIds as $roleId) {
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

        $ids = DB::table('permissions')
            ->whereIn('name', [...self::GRANTED_LIKE_OLD, ...self::GRANTED_TO_ALL_ROLES])
            ->pluck('id');

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
