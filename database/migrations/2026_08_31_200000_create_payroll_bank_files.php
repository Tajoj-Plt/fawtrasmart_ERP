<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * ملف الرواتب البنكي (§15 TODO 4.5): سجلّ الملفات المولَّدة من
 * الرواتب المصروفة + إعدادات البنك وصيغة الملف.
 *
 * الصرف من الخزينة يبقى كما هو. هذا الملف تعليمات تحويل تُرفع إلى بنك المنشأة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_bank_files')) {
            Schema::create('payroll_bank_files', function (Blueprint $table) {
                $table->id();
                $table->date('period_from');
                $table->date('period_to');
                $table->string('format', 16)->default('csv');
                $table->string('bank_code', 8)->nullable();
                $table->string('filename');
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('payroll_settings')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_settings', 'bank_export_format')) {
                    $table->string('bank_export_format', 16)->default('csv');
                }
                if (! Schema::hasColumn('payroll_settings', 'bank_export_bank_code')) {
                    $table->string('bank_export_bank_code', 8)->nullable();
                }
            });
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $id = DB::table('permissions')->where('name', 'salaries_payroll_bank_export')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            }
        }

        if (Schema::hasTable('payroll_settings')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                foreach (['bank_export_format', 'bank_export_bank_code'] as $column) {
                    if (Schema::hasColumn('payroll_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('payroll_bank_files');
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'salaries_payroll_bank_export')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'salaries_payroll_bank_export',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counterparts = DB::table('permissions')
            ->whereIn('name', ['salaries_payroll_payment', 'salaries_payroll_settings_manage'])
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
