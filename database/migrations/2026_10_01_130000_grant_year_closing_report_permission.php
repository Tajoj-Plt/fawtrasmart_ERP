<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيةُ «تقرير الإقفال السنوي» في التقارير المالية — إلى من يملك نظيرَها.
 *
 * `permissions:sync` يُنشئ الصلاحية ولا يربطها بدور، و`hasPermission()` تقرأ
 * دورَ المستخدم وحده — فتقريرٌ جديدٌ بلا ترحيلٍ كهذا مخفيٌّ عن الجميع (انظر
 * `2026_09_28_120000_grant_sales_exchange_and_commission_report_permissions`).
 *
 * النظيران: «قائمة الدخل» (الإقفالُ يرحّل صافيها)، و«عرض الفترات المحاسبية»
 * (التقريرُ نفسُه معروضٌ من شاشتها) — تكفي صلاحيةٌ واحدةٌ منهما.
 *
 * `down()` تسحب الارتباطات وحدها — الصلاحيةُ مصدرُها كتالوج `AccountPermissions`.
 */
return new class extends Migration
{
    private const PERMISSION = 'account.reports.year_closing';

    private const COUNTERPARTS = ['account.reports.income_statement', 'settings.accounting_periods.view'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = $this->permissionId(self::PERMISSION);

        $roleIds = DB::table('role_has_permissions')
            ->whereIn('permission_id', DB::table('permissions')->whereIn('name', self::COUNTERPARTS)->where('guard_name', 'web')->pluck('id'))
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
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        }

        $this->forgetPermissionCache();
    }

    /** معرّف الصلاحية — تُنشأ إن غابت، فلا تتوقّف الميزة على `permissions:sync`. */
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

    private function forgetPermissionCache(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable $e) {
            // الحزمة غير مهيّأة في هذا السياق — الكاش سينتهي بنفسه
        }
    }
};
