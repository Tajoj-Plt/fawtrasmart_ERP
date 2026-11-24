<?php

namespace Database\Seeders;

use App\Constants\permissions\SalesPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * زرع صلاحيات نظام المبيعات ومنحها للأدوار.
 *
 * الأسماء كلّها من [App\Constants\permissions\SalesPermissions] — مصدرٌ واحد تقرأ منه
 * الشاشاتُ والمساراتُ والسيدر معاً، فلا تنشأ صلاحيةٌ في مكانٍ ولا تُعرف في آخر
 * (وهو ما حدث في `ApplicationRegistry` قبل توحيده).
 *
 * **المنح إضافي لا مزامنة:** `givePermissionTo` لا `syncPermissions` — إعادةُ
 * تشغيل السيدر يجب ألّا تسحب ما ضبطه صاحب النظام من شاشة الأدوار.
 */
class SalesPermissionsSeeder extends Seeder
{
    /**
     * صلاحيات النطاق لكل دور.
     *
     * النطاق قرار تنظيمي لا تقني: من يرى بيانات من. القيم هنا الحدّ الأدنى
     * المعقول ليعمل النظام فور التثبيت — `owner` و`manager` يريان كل شيء،
     * و`employee` بياناته وحدها. وأي هيكلة أدقّ (فرع، منطقة، فريق) تُضبط من
     * شاشة الأدوار لأنها تختلف من منشأة لأخرى.
     */
    protected const ROLE_SCOPES = [
        'owner' => 'data_scope_all',
        'manager' => 'data_scope_all',
        'admin' => 'data_scope_all',
        'employee' => 'data_scope_own',
    ];

    /** الأدوار التي تأخذ كل صلاحيات عمليات المبيعات. */
    protected const FULL_ACCESS_ROLES = ['owner', 'manager', 'admin'];

    public function run(): void
    {
        $created = $this->ensurePermissions();

        // قبل المنح: الصلاحيات المُنشأة قبل قليل لا تراها طبقة Spatie
        // المخزَّنة، فيفشل `givePermissionTo` بحجّة أنها غير موجودة
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->grantToRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            '  صلاحيات المبيعات: %d إجمالاً (%d عملية + %d نطاق)، %d أُنشئت الآن.',
            count(SalesPermissions::allWithScopes()),
            count(SalesPermissions::all()),
            count(SalesPermissions::scopes()),
            $created
        ));
    }

    /**
     * إنشاء ما ينقص من الصلاحيات.
     *
     * `guard_name` صريح في الاستعلام وفي الإنشاء: جدول `permissions` مفتاحه
     * الفريد `(name, guard_name)`، فالمطابقة على الاسم وحده تُنشئ صفّاً
     * مكرّراً حين يختلف الحارس.
     *
     * @return int عدد ما أُنشئ
     */
    protected function ensurePermissions(): int
    {
        $names = SalesPermissions::allWithScopes();

        $existing = Permission::where('guard_name', SalesPermissions::GUARD)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $missing = array_diff($names, $existing);

        foreach ($missing as $name) {
            Permission::create([
                'name' => $name,
                'guard_name' => SalesPermissions::GUARD,
            ]);
        }

        return count($missing);
    }

    /**
     * منح الصلاحيات للأدوار.
     *
     * الدور غير الموجود يُتخطّى بتحذير لا باستثناء: هذا السيدر قد يُشغَّل قبل
     * [RolesSeeder] على تركيبٍ قديم، وسقوطُه حينها يمنع زرع الصلاحيات نفسها
     * — وهي نافعة حتى بلا منحٍ فوري.
     */
    protected function grantToRoles(): void
    {
        $operations = SalesPermissions::all();

        foreach (self::FULL_ACCESS_ROLES as $roleName) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($operations);

            $this->command?->info("  «{$roleName}»: مُنح ".count($operations).' صلاحية مبيعات.');
        }

        foreach (self::ROLE_SCOPES as $roleName => $scope) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($scope);

            $this->command?->info("  «{$roleName}»: نطاق البيانات {$scope}.");
        }
    }

    protected function findRole(string $name): ?Role
    {
        $role = Role::where('name', $name)
            ->where('guard_name', SalesPermissions::GUARD)
            ->first();

        if (! $role) {
            $this->command?->warn("  الدور «{$name}» غير موجود — تُخطّي منحه. شغّل RolesSeeder أولاً.");
        }

        return $role;
    }
}
