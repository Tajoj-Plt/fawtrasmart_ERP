<?php

namespace Database\Seeders;

use App\Constants\permissions\SettingsUserManagementPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * منح صلاحيات شاشات المستخدمين/الأدوار/الصلاحيات للأدوار.
 *
 * نظير [StockPermissionsSeeder] حرفاً بحرف. `owner` وحده يُمنح افتراضياً
 * كل صلاحياتها: هذه الشاشات تُنشئ حسابات وتُوزّع صلاحيات النظام كلّه، فمنحها
 * جملةً لـ`manager`/`admin` كمنح باقي الوحدات موضعُ نظرٍ لصاحب النظام —
 * `manager` يُمنح صلاحية العرض فقط، والباقي يُضبط يدوياً من شاشة الأدوار.
 */
class SettingsUserManagementPermissionsSeeder extends Seeder
{
    protected const FULL_ACCESS_ROLES = ['owner'];

    protected const VIEW_ONLY_ROLES = ['manager'];

    public function run(): void
    {
        $created = $this->ensurePermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->grantToRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            '  صلاحيات إدارة المستخدمين: %d إجمالاً، %d أُنشئت الآن.',
            count(SettingsUserManagementPermissions::all()),
            $created
        ));
    }

    protected function ensurePermissions(): int
    {
        $names = SettingsUserManagementPermissions::all();

        $existing = Permission::where('guard_name', SettingsUserManagementPermissions::GUARD)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $missing = array_diff($names, $existing);

        foreach ($missing as $name) {
            Permission::create([
                'name' => $name,
                'guard_name' => SettingsUserManagementPermissions::GUARD,
            ]);
        }

        return count($missing);
    }

    protected function grantToRoles(): void
    {
        $operations = SettingsUserManagementPermissions::all();
        $viewOnly = array_filter($operations, fn ($name) => str_ends_with($name, '.view'));

        foreach (self::FULL_ACCESS_ROLES as $roleName) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($operations);
            $this->command?->info("  «{$roleName}»: مُنح ".count($operations).' صلاحية إدارة مستخدمين (كاملة).');
        }

        foreach (self::VIEW_ONLY_ROLES as $roleName) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($viewOnly);
            $this->command?->info("  «{$roleName}»: مُنح ".count($viewOnly).' صلاحية عرض فقط.');
        }
    }

    protected function findRole(string $name): ?Role
    {
        $role = Role::where('name', $name)
            ->where('guard_name', SettingsUserManagementPermissions::GUARD)
            ->first();

        if (! $role) {
            $this->command?->warn("  الدور «{$name}» غير موجود — تُخطّي منحه. شغّل RolesSeeder أولاً.");
        }

        return $role;
    }
}
