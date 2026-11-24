<?php

use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * التأمينات الاجتماعية (§15 TODO 4.4): نسب الموظف والمنشأة وسقف الأجر
 * الخاضع، وحسابات القيد، وبند كتالوج للاستقطاع المولَّد عند الصرف.
 *
 * النسب والسقف قيم بدء قابلة للتعديل — ليست نصّاً نظامياً مضمّناً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_insurance_settings')) {
            Schema::create('social_insurance_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->decimal('employee_rate', 6, 3)->default(9.75);
                $table->decimal('employer_rate', 6, 3)->default(11.75);
                $table->decimal('wage_cap', 15, 2)->nullable()->default(45000);
                $table->unsignedBigInteger('employee_payable_account_id')->nullable();
                $table->unsignedBigInteger('employer_payable_account_id')->nullable();
                $table->unsignedBigInteger('expense_account_id')->nullable();
                $table->unsignedBigInteger('component_type_id')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        $typeId = null;

        if (Schema::hasTable('salary_component_types')) {
            $typeId = DB::table('salary_component_types')
                ->where('code', SalaryComponentType::CODE_SOCIAL_INSURANCE)
                ->value('id');

            if (! $typeId) {
                $typeId = DB::table('salary_component_types')->insertGetId([
                    'code' => SalaryComponentType::CODE_SOCIAL_INSURANCE,
                    'name' => 'تأمينات اجتماعية',
                    'kind' => SalaryItem::TYPE_DEDUCTION,
                    'is_insurable' => false,
                    'is_default' => false,
                    'is_system' => true,
                    'sort_order' => 50,
                    'status' => SalaryComponentType::STATUS_ACTIVE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('social_insurance_settings') && ! DB::table('social_insurance_settings')->exists()) {
            DB::table('social_insurance_settings')->insertOrIgnore([
                'enabled' => false,
                'employee_rate' => 9.75,
                'employer_rate' => 11.75,
                'wage_cap' => 45000,
                'employee_payable_account_id' => $this->accountIdByCode('2161'),
                'employer_payable_account_id' => $this->accountIdByCode('2162'),
                'expense_account_id' => $this->accountIdByCode('5305'),
                'component_type_id' => $typeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif (Schema::hasTable('social_insurance_settings') && $typeId) {
            DB::table('social_insurance_settings')
                ->whereNull('component_type_id')
                ->update(['component_type_id' => $typeId, 'updated_at' => $now]);
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $id = DB::table('permissions')->where('name', 'salaries_statutory_manage')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            }
        }

        Schema::dropIfExists('social_insurance_settings');
    }

    private function accountIdByCode(string $code): ?int
    {
        if (! Schema::hasTable('accounts')) {
            return null;
        }

        $id = DB::table('accounts')->where('code', $code)->value('id');

        return $id ? (int) $id : null;
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'salaries_statutory_manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'salaries_statutory_manage',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counterparts = DB::table('permissions')
            ->whereIn('name', ['salaries_payroll_settings_manage'])
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
