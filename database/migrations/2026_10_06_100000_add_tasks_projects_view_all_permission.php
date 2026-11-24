<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحية «كل المشاريع» في إدارة المهام.
 *
 * كانت الوحدة لا تسأل «على أيّ مشروع»: من يملك `tasks.items.view` يقرأ
 * مهامّ كل المشاريع ويعدّلها. صارت الرؤية بالعضوية
 * ([\Modules\TaskManager\Services\ProjectAccess]) — ومن يملك
 * `tasks.projects.view_all` يرى الكل كما كان.
 *
 * تُمنح لمن يملك `tasks.projects.delete` اليوم (المدير ومن فوقه): هم من
 * يُفترض أن يروا كل شيء، فلا يتغيّر عليهم شيءٌ يوم التشغيل. غيرُهم يرى
 * مشاريعه وحدها — وهو المقصود.
 */
return new class extends Migration
{
    private const PERMISSION = 'tasks.projects.view_all';

    private const GRANT_TO_HOLDERS_OF = 'tasks.projects.delete';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('role_has_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.name', self::GRANT_TO_HOLDERS_OF)
            ->pluck('rp.role_id');

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

        $ids = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
