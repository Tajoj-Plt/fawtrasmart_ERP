<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * إزالةُ خمسِ صلاحياتٍ في الموارد البشرية لا فعلَ لها (2026-10-04).
 *
 * خرجت من تدقيقٍ شامل: صفوفٌ في `permissions` وبنودٌ في شاشة الأدوار
 * **لا يسألها أحدٌ في الشيفرة كلِّها**، فمنحُها لا يفتح شيئاً ومنعُها لا
 * يغلق شيئاً — وتبقى في الشاشة تُوهم بضابطٍ غير موجود.
 *
 *   • `employee_stock.index` و`employee_stock.manage` — المجموعةُ كلُّها.
 *     وصفُها يحيل إلى «HR/EmployeeController (stock permits)» ولا وجودَ له،
 *     ولا مسارَ في الوحدة يمسّ أذوناتِ مخزونِ الموظفين. والأذوناتُ الفعلية
 *     تحرسها صلاحياتُ وحدة المخزون.
 *   • `commissions.edit` و`commissions.destroy` — شاشةُ العمولات تقريرٌ:
 *     `index` و`show` و`export` و`print` وحدها في المسارات، ولا فعلَ
 *     تعديلٍ ولا حذفٍ في متحكّمها.
 *   • `time_entries.destroy` — `Modules\Reports\TimeTrackingController`
 *     أفعالُه عرضٌ وتقاريرُ، ولا مسارَ لحذف ساعةِ عمل.
 *
 * وكانت الخمسُ ممنوحةً لـ`manager` و`owner` و`admin` — فالحذفُ لا يسلب
 * أحداً وصولاً كان يستعمله، لأنّه لم يكن يفتح باباً.
 *
 * ولا يُحذف إلّا ما نُزع من الكتالوج (`HrPermissions::groups()`) في الدفعة
 * نفسِها: لو بقي الاسمُ في الكتالوج لأعاد `permissions:sync-catalog` خلقَ
 * صفِّه بلا روابط، فيعود البندُ إلى الشاشة بلا دور.
 *
 * `down()` تُعيد الصفوفَ وروابطَها للأدوار الثلاثة — فالتراجعُ يُرجع ما كان.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const DEAD = [
        'employee_stock.index',
        'employee_stock.manage',
        'commissions.edit',
        'commissions.destroy',
        'time_entries.destroy',
    ];

    /** الأدوارُ التي كانت تحملها — يُعيدها `down()` إليها. */
    private const ROLES = ['manager', 'owner', 'admin'];

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', self::DEAD)
            ->where('guard_name', self::GUARD)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->forgetPermissionCache();

            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        // `model_has_permissions` قد لا يكون موجوداً في تركيبٍ بلا Spatie كامل
        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        }

        DB::table('permissions')->whereIn('id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');

        foreach (self::DEAD as $name) {
            $id = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', self::GUARD)
                ->value('id');

            if (! $id) {
                $id = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'guard_name' => self::GUARD,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $id,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    /** بلا هذا تبقى الصلاحياتُ محسوبةً حتى انتهاء ذاكرة Spatie المؤقّتة. */
    private function forgetPermissionCache(): void
    {
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
