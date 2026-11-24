<?php

namespace Database\Seeders;

use App\Constants\permissions\PurchasesPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * منح صلاحيات وحدة المشتريات للأدوار.
 *
 * نظير [StockPermissionsSeeder] حرفاً بحرف — انظر توثيقه لتفصيل المشكلة
 * العامة (كتالوجٌ يُنشئ صفوفاً في `permissions` ولا يمنحها أحد فتُقفَل
 * الوحدة على الجميع). الترتيب مُلزِم: بعد [PermissionTableSeeder] و[RolesSeeder].
 *
 * @see \App\Constants\permissions\PurchasesPermissions كتالوج الأسماء
 * @see \Database\Seeders\StockPermissionsSeeder النظير في المخزون
 */
class PurchasesPermissionsSeeder extends Seeder
{
    protected const FULL_ACCESS_ROLES = ['owner', 'manager', 'admin'];

    public function run(): void
    {
        $created = $this->ensurePermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->grantToRoles();

        $this->backfillDuplicate();
        $this->backfillAssignEmployee();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            '  صلاحيات المشتريات: %d إجمالاً، %d أُنشئت الآن.',
            count(PurchasesPermissions::all()),
            $created
        ));
    }

    protected function ensurePermissions(): int
    {
        $names = PurchasesPermissions::all();

        $existing = Permission::where('guard_name', PurchasesPermissions::GUARD)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $missing = array_diff($names, $existing);

        foreach ($missing as $name) {
            Permission::create([
                'name' => $name,
                'guard_name' => PurchasesPermissions::GUARD,
            ]);
        }

        return count($missing);
    }

    protected function grantToRoles(): void
    {
        $operations = PurchasesPermissions::all();

        foreach (self::FULL_ACCESS_ROLES as $roleName) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($operations);

            $this->command?->info("  «{$roleName}»: مُنح ".count($operations).' صلاحية مشتريات.');
        }
    }

    /**
     * يمنح `<قسم>.duplicate` لكلّ دورٍ يملك `<قسم>.create` أصلاً.
     *
     * صلاحيةُ النسخ استُحدثت بعد أن كان الزرّ محروساً بـ`create` (طلب
     * المستخدم 2026-09-21). ولولا هذا الترحيل لاختفى زرُّ النسخ فجأةً عن
     * كلّ دورٍ مخصَّصٍ كان ينسخ بالأمس — فالصفُّ الجديد في `permissions` لا
     * يمنحه أحدٌ لأحد.
     *
     * فمن كان يقدر على النسخ يبقى قادراً، ثمّ يسحبها المسؤول ممّن لا
     * يريده أن ينسخ. ولا يوسّع هذا صلاحيةَ أحد: من لا يملك `create` لم
     * يكن ينسخ ولا يُمنح.
     */
    /**
     * «تعيين مورد لموظف» أُضيفت بعد التوزيع (2026-09-21) — تُمنح لكل دورٍ
     * يملك «تعديل مورد» حتى لا يختفي الزرُّ عمّن كان يعدّل المورّد أصلاً.
     */
    protected function backfillAssignEmployee(): void
    {
        $name = 'purchases.suppliers.assign_employee';
        $granted = 0;

        Role::where('guard_name', PurchasesPermissions::GUARD)
            ->whereHas('permissions', fn ($q) => $q->where('name', 'purchases.suppliers.edit'))
            ->each(function (Role $role) use ($name, &$granted) {
                if ($role->hasPermissionTo($name)) {
                    return;
                }

                $role->givePermissionTo($name);
                $granted++;
            });

        if ($granted > 0) {
            $this->command?->info("  ترحيل التعيين: مُنحت {$granted} صلاحية «تعيين مورد لموظف» لأدوارٍ تملك «تعديل مورد».");
        }
    }

    protected function backfillDuplicate(): void
    {
        $granted = 0;

        foreach (PurchasesPermissions::all() as $name) {
            if (! str_ends_with($name, '.duplicate')) {
                continue;
            }

            $create = substr($name, 0, -strlen('.duplicate')).'.create';

            Role::where('guard_name', PurchasesPermissions::GUARD)
                ->whereHas('permissions', fn ($q) => $q->where('name', $create))
                ->each(function (Role $role) use ($name, &$granted) {
                    if ($role->hasPermissionTo($name)) {
                        return;
                    }

                    $role->givePermissionTo($name);
                    $granted++;
                });
        }

        if ($granted > 0) {
            $this->command?->info("  ترحيل النسخ: مُنحت {$granted} صلاحية «نسخ» لأدوارٍ تملك «إضافة».");
        }
    }

    protected function findRole(string $name): ?Role
    {
        $role = Role::where('name', $name)
            ->where('guard_name', PurchasesPermissions::GUARD)
            ->first();

        if (! $role) {
            $this->command?->warn("  الدور «{$name}» غير موجود — تُخطّي منحه. شغّل RolesSeeder أولاً.");
        }

        return $role;
    }
}
