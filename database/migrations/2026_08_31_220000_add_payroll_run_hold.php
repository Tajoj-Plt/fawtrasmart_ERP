<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * إيقاف راتب معلّق بقرار إداري (§15 TODO 2.6): يمنع الصرف اليدوي والتلقائي
 * دون إلغاء العملية، ويُرفع الإيقاف بنفس الصلاحية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_runs')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_runs', 'held_at')) {
                    $table->timestamp('held_at')->nullable();
                }
                if (! Schema::hasColumn('payroll_runs', 'held_by')) {
                    $table->unsignedBigInteger('held_by')->nullable();
                }
                if (! Schema::hasColumn('payroll_runs', 'hold_reason')) {
                    $table->text('hold_reason')->nullable();
                }
            });
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $id = DB::table('permissions')->where('name', 'salaries_payroll_hold')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            }
        }

        if (Schema::hasTable('payroll_runs')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                foreach (['held_at', 'held_by', 'hold_reason'] as $column) {
                    if (Schema::hasColumn('payroll_runs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'salaries_payroll_hold')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'salaries_payroll_hold',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counterparts = DB::table('permissions')
            ->whereIn('name', ['salaries_payroll_approve', 'salaries_payroll_payment'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = DB::table('role_has_permissions')
            ->whereIn('permission_id', $counterparts)
            ->pluck('role_id')
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable $e) {
            // الحزمة غير مهيّأة أثناء بعض سياقات الترحيل
        }
    }
};
