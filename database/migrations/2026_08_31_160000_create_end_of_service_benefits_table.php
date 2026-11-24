<?php

use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * مكافأة نهاية الخدمة (§15 TODO 4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('end_of_service_benefits')) {
            Schema::create('end_of_service_benefits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('status', 20)->default('draft');
                $table->unsignedTinyInteger('employment_status')->nullable();
                $table->date('hire_date')->nullable();
                $table->date('last_working_day')->nullable();
                $table->decimal('years_of_service', 8, 4)->default(0);
                $table->decimal('monthly_wage', 15, 2)->default(0);
                $table->decimal('full_amount', 15, 2)->default(0);
                $table->decimal('fraction', 8, 4)->default(1);
                $table->decimal('payable_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('component_type_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('accrual_journal_entry_id')->nullable();
                $table->unsignedBigInteger('settlement_journal_entry_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('payroll_settings')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_settings', 'eos_enabled')) {
                    $table->boolean('eos_enabled')->default(true);
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_include_default_allowances')) {
                    $table->boolean('eos_include_default_allowances')->default(true);
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_first_bracket_years')) {
                    $table->decimal('eos_first_bracket_years', 6, 2)->default(5);
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_first_months_per_year')) {
                    $table->decimal('eos_first_months_per_year', 6, 2)->default(0.5);
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_later_months_per_year')) {
                    $table->decimal('eos_later_months_per_year', 6, 2)->default(1);
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_expense_account_id')) {
                    $table->unsignedBigInteger('eos_expense_account_id')->nullable();
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_provision_account_id')) {
                    $table->unsignedBigInteger('eos_provision_account_id')->nullable();
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_component_type_id')) {
                    $table->unsignedBigInteger('eos_component_type_id')->nullable();
                }
                if (! Schema::hasColumn('payroll_settings', 'eos_resignation_bands')) {
                    $table->json('eos_resignation_bands')->nullable();
                }
            });
        }

        $now = now();
        if (Schema::hasTable('salary_component_types')) {
            $typeId = DB::table('salary_component_types')
                ->where('code', SalaryComponentType::CODE_END_OF_SERVICE)
                ->value('id');
            if (! $typeId) {
                $typeId = DB::table('salary_component_types')->insertGetId([
                    'code' => SalaryComponentType::CODE_END_OF_SERVICE,
                    'name' => 'مكافأة نهاية الخدمة',
                    'kind' => SalaryItem::TYPE_ADDITION,
                    'is_insurable' => false,
                    'is_default' => false,
                    'is_system' => true,
                    'sort_order' => 50,
                    'status' => SalaryComponentType::STATUS_ACTIVE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if (Schema::hasTable('payroll_settings') && Schema::hasColumn('payroll_settings', 'eos_component_type_id')) {
                DB::table('payroll_settings')->whereNull('eos_component_type_id')
                    ->update(['eos_component_type_id' => $typeId]);
            }
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $id = DB::table('permissions')->where('name', 'salaries_eos_manage')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            }
        }

        if (Schema::hasTable('payroll_settings')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                foreach ([
                    'eos_enabled',
                    'eos_include_default_allowances',
                    'eos_first_bracket_years',
                    'eos_first_months_per_year',
                    'eos_later_months_per_year',
                    'eos_expense_account_id',
                    'eos_provision_account_id',
                    'eos_component_type_id',
                    'eos_resignation_bands',
                ] as $column) {
                    if (Schema::hasColumn('payroll_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('end_of_service_benefits');
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'salaries_eos_manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'salaries_eos_manage',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counterparts = DB::table('permissions')
            ->whereIn('name', ['salaries_payroll_settings_manage', 'employees_terminate'])
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
        }
    }
};
