<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * عكس راتب مصروف (§15 TODO 2.3 / اعتماد 4.6): قيد عكسي بلا حذف الأصلي،
 * وإرجاع العملية إلى pending لإعادة الصرف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_runs')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_runs', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable();
                }
                if (! Schema::hasColumn('payroll_runs', 'reversed_by')) {
                    $table->unsignedBigInteger('reversed_by')->nullable();
                }
                if (! Schema::hasColumn('payroll_runs', 'reversal_reason')) {
                    $table->text('reversal_reason')->nullable();
                }
                if (! Schema::hasColumn('payroll_runs', 'reversal_journal_entry_id')) {
                    $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
                }
            });
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $id = DB::table('permissions')->where('name', 'salaries_payroll_reverse')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            }
        }

        if (Schema::hasTable('payroll_runs')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                foreach (['reversed_at', 'reversed_by', 'reversal_reason', 'reversal_journal_entry_id'] as $column) {
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
            ->where('name', 'salaries_payroll_reverse')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'salaries_payroll_reverse',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counterparts = DB::table('permissions')
            ->whereIn('name', ['salaries_payroll_payment', 'salaries_payroll_approve'])
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
