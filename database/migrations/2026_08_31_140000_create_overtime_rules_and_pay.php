<?php

use App\Models\OvertimeRule;
use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * الأجر الإضافي (§15 TODO 4.2): قواعد المضاعف، إعدادات الاحتساب،
 * وبند كتالوج يُصنَّف به الاستحقاق المولَّد من `overtime_minutes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('overtime_rules')) {
            Schema::create('overtime_rules', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('name');
                $table->string('applies_on', 16);
                $table->decimal('multiplier', 6, 2)->default(1.50);
                $table->decimal('hourly_rate', 15, 2)->nullable();
                $table->unsignedInteger('max_minutes_per_day')->nullable();
                $table->boolean('is_system')->default(false);
                $table->unsignedTinyInteger('status')->default(OvertimeRule::STATUS_ACTIVE);
                $table->timestamps();
            });
        }

        $now = now();
        foreach (OvertimeRule::seededRows() as $row) {
            if (DB::table('overtime_rules')->where('code', $row['code'])->exists()) {
                continue;
            }
            DB::table('overtime_rules')->insertOrIgnore($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('payroll_settings')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_settings', 'overtime_enabled')) {
                    $table->boolean('overtime_enabled')->default(false);
                }
                if (! Schema::hasColumn('payroll_settings', 'overtime_monthly_hours')) {
                    $table->decimal('overtime_monthly_hours', 8, 2)->default(240);
                }
                if (! Schema::hasColumn('payroll_settings', 'overtime_hourly_rate')) {
                    $table->decimal('overtime_hourly_rate', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('payroll_settings', 'overtime_max_minutes_per_period')) {
                    $table->unsignedInteger('overtime_max_minutes_per_period')->nullable();
                }
                if (! Schema::hasColumn('payroll_settings', 'overtime_component_type_id')) {
                    $table->unsignedBigInteger('overtime_component_type_id')->nullable();
                }
            });
        }

        if (Schema::hasTable('salary_items') && ! Schema::hasColumn('salary_items', 'origin')) {
            Schema::table('salary_items', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_items', 'origin')) {
                    $table->string('origin', 32)->nullable()->after('component_type_id');
                }
            });
        }

        if (Schema::hasTable('salary_component_types')) {
            $typeId = DB::table('salary_component_types')
                ->where('code', SalaryComponentType::CODE_OVERTIME)
                ->value('id');

            if (! $typeId) {
                $typeId = DB::table('salary_component_types')->insertGetId([
                    'code' => SalaryComponentType::CODE_OVERTIME,
                    'name' => 'أجر إضافي',
                    'kind' => SalaryItem::TYPE_ADDITION,
                    'is_insurable' => false,
                    'is_default' => false,
                    'is_system' => true,
                    'sort_order' => 40,
                    'status' => SalaryComponentType::STATUS_ACTIVE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (Schema::hasTable('payroll_settings') && Schema::hasColumn('payroll_settings', 'overtime_component_type_id')) {
                DB::table('payroll_settings')
                    ->whereNull('overtime_component_type_id')
                    ->update(['overtime_component_type_id' => $typeId]);
            }
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $id = DB::table('permissions')->where('name', 'salaries_overtime_manage')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            }
        }

        if (Schema::hasTable('salary_items') && Schema::hasColumn('salary_items', 'origin')) {
            Schema::table('salary_items', function (Blueprint $table) {
                $table->dropColumn('origin');
            });
        }

        if (Schema::hasTable('payroll_settings')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                foreach ([
                    'overtime_enabled',
                    'overtime_monthly_hours',
                    'overtime_hourly_rate',
                    'overtime_max_minutes_per_period',
                    'overtime_component_type_id',
                ] as $column) {
                    if (Schema::hasColumn('payroll_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('overtime_rules');
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'salaries_overtime_manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'salaries_overtime_manage',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counterparts = DB::table('permissions')
            ->whereIn('name', ['hr_salary_items_manage', 'salaries_payroll_settings_manage'])
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
