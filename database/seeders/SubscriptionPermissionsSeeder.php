<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات شاشة الاشتراك.
 *
 * العرض للمالك والمدير: معرفة موعد انتهاء الاشتراك تخصّ من يدير المنشأة.
 * أمّا تسجيل المدد ومبالغها فللمالك وحده — هو بند مالي.
 */
class SubscriptionPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['settings.subscription.view', 'settings.subscription.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->grant('owner', ['settings.subscription.view', 'settings.subscription.manage']);
        $this->grant('manager', ['settings.subscription.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('صلاحيات الاشتراك: صلاحيتان.');
    }

    /** @param  array<int, string>  $permissions */
    protected function grant(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->command?->warn("الدور «{$roleName}» غير موجود.");

            return;
        }

        $role->givePermissionTo($permissions);
    }
}
