<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحياتُ الرواتب مجموعاتٍ مفصّلة: `<الشاشة>.<الفعل>` لكل شاشة.
 *
 * كانت الرواتب كلُّها تحت `salaries_payroll_*` وأخواتها بمعانٍ متداخلة
 * (`salaries_payroll_edit` = تعديل القسائم والتسوية الرجعية معاً؛
 * `salaries_payroll_view` يفتح المسيرَ والقسيمةَ والصرفَ). صار لكل شاشةٍ
 * مجموعةٌ في `HrPermissions` ولكل فعلٍ صلاحية.
 *
 * يُنشئ هذا الترحيلُ الأسماءَ الجديدة ويمنح كلَّ واحدةٍ لكل دورٍ حمل
 * **أيّاً** من نظيراتها القديمة — فلا يُحجب أحدٌ عمّا كان يفتحه، ولا
 * يُمنح أحدٌ ما لم يكن يملكه. الأسماء القديمة تبقى صفوفاً (لا حذف) لكنها
 * خرجت من الكتالوج ومن الشيفرة.
 *
 * `down()` تسحب ارتباطاتِ الأسماء الجديدة وحدها.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> الجديدة ← نظيراتها القديمة */
    private const MAPPING = [
        // مسيرات الرواتب
        'payroll.index' => ['salaries_payroll_view'],
        'payroll.show' => ['salaries_payroll_view'],
        'payroll.create' => ['salaries_payroll_create'],
        'payroll.edit' => ['salaries_payroll_edit', 'salaries_payroll_create'],
        'payroll.destroy' => ['salaries_payroll_delete_all'],
        'payroll.approve' => ['salaries_payroll_approve'],
        'payroll.export' => ['salaries_payroll_export'],
        'payroll.print' => ['salaries_payroll_print'],

        // قسائم الرواتب
        'payslips.index' => ['salaries_payroll_view', 'hr_payslips_view'],
        'payslips.show' => ['salaries_payroll_view', 'hr_payslips_view'],
        'payslips.create' => ['salaries_payroll_create'],
        'payslips.edit' => ['salaries_payroll_edit'],
        'payslips.destroy' => ['salaries_payroll_delete', 'salaries_payroll_delete_all'],
        'payslips.approve' => ['salaries_payroll_approve'],
        'payslips.disburse' => ['salaries_payroll_payment'],
        'payslips.print' => ['salaries_payroll_print'],
        'payslips.view_own' => ['salaries_payroll_view_own', 'employee_view_his_salary_slip'],

        // سجلات الصرف
        'disbursements.index' => ['salaries_payroll_view'],
        'disbursements.show' => ['salaries_payroll_view'],
        'disbursements.stop' => ['salaries_payroll_hold'],
        'disbursements.settle' => ['salaries_payroll_retro'],
        'disbursements.reverse' => ['salaries_payroll_reverse'],
        'disbursements.cancel' => ['salaries_payroll_delete', 'salaries_payroll_delete_all'],

        // العقود
        'contracts.index' => ['salaries_contracts_view_all'],
        'contracts.show' => ['salaries_contracts_view_all'],
        'contracts.create' => ['salaries_contracts_create'],
        'contracts.edit' => ['salaries_contracts_edit_delete_all'],
        'contracts.destroy' => ['salaries_contracts_edit_delete_all'],
        'contracts.approve' => ['salaries_contracts_edit_delete_all'],
        'contracts.print' => ['salaries_contracts_view_all'],
        'contracts.view_own' => ['salaries_contracts_view_own'],
        'contracts.notifications' => ['salaries_contracts_notifications', 'salaries_contracts_receive_notifications'],

        // السلف
        'loans.index' => ['salaries_loans_manage', 'advance_requests_approve'],
        'loans.show' => ['salaries_loans_manage', 'advance_requests_approve', 'advance_requests_create'],
        'loans.create' => ['advance_requests_create', 'salaries_loans_manage'],
        'loans.edit' => ['salaries_loans_manage'],
        'loans.destroy' => ['salaries_loans_manage'],
        'loans.approve' => ['advance_requests_approve'],
        'loans.disburse' => ['advance_requests_disburse'],
        'loans.cancel' => ['advance_requests_cancel'],
        'loans.settle' => ['advance_requests_settle'],

        // الخصومات
        'deductions.index' => ['deductions_view', 'deductions_history_view'],
        'deductions.show' => ['deductions_view'],
        'deductions.create' => ['deductions_create'],
        'deductions.edit' => ['deductions_edit'],
        'deductions.destroy' => ['deductions_delete'],
        'deductions.approve' => ['deductions_approve', 'deductions_reject'],
        'deductions.settle' => ['deductions_settle'],

        // العمولات — العرضُ من تقرير العمولات، والاحتسابُ والصرفُ من تسوية أهداف المبيعات
        'commissions.index' => ['hr_commissions_view'],
        'commissions.show' => ['hr_commissions_view'],
        'commissions.export' => ['hr_commissions_view'],
        'commissions.print' => ['hr_commissions_view'],
        'commissions.create' => ['sales.targets.settle'],
        'commissions.edit' => ['sales.targets.settle'],
        'commissions.destroy' => ['sales.targets.settle'],
        'commissions.approve' => ['sales.targets.settle'],
        'commissions.disburse' => ['sales.targets.settle'],

        // التأمينات
        'insurance.index' => ['salaries_statutory_view', 'salaries_statutory_manage'],
        'insurance.show' => ['salaries_statutory_view', 'salaries_statutory_manage'],
        'insurance.pay' => ['salaries_statutory_remit'],
        'insurance.settings' => ['salaries_statutory_manage'],

        // نهاية الخدمة
        'eos.index' => ['salaries_eos_view', 'salaries_eos_manage'],
        'eos.show' => ['salaries_eos_view', 'salaries_eos_manage'],
        'eos.create' => ['salaries_eos_manage'],
        'eos.approve' => ['salaries_eos_manage'],
        'eos.settle' => ['salaries_eos_manage'],
        'eos.pay' => ['salaries_eos_pay'],
        'eos.settings' => ['salaries_eos_manage'],

        // بنود الرواتب وقوالبها
        'salary_items.index' => ['hr_salary_items_view', 'hr_salary_items_manage'],
        'salary_items.show' => ['hr_salary_items_view', 'hr_salary_items_manage'],
        'salary_items.create' => ['hr_salary_items_manage'],
        'salary_items.edit' => ['hr_salary_items_manage'],
        'salary_items.destroy' => ['hr_salary_items_manage'],
        'salary_templates.index' => ['hr_salary_templates_manage'],
        'salary_templates.show' => ['hr_salary_templates_manage'],
        'salary_templates.create' => ['hr_salary_templates_manage'],
        'salary_templates.edit' => ['hr_salary_templates_manage'],
        'salary_templates.destroy' => ['hr_salary_templates_manage'],

        // العمل الإضافي
        'overtime.index' => ['overtime_requests_approve', 'overtime_requests_create', 'salaries_overtime_manage'],
        'overtime.show' => ['overtime_requests_approve', 'overtime_requests_create', 'salaries_overtime_manage'],
        'overtime.create' => ['overtime_requests_create'],
        'overtime.approve' => ['overtime_requests_approve'],
        'overtime.settings' => ['salaries_overtime_manage'],

        // ملفات البنك
        'bank_files.index' => ['salaries_payroll_bank_export'],
        'bank_files.show' => ['salaries_payroll_bank_export'],
        'bank_files.create' => ['salaries_payroll_bank_export'],
        'bank_files.download' => ['salaries_payroll_bank_export'],

        // الإعدادات
        'payroll_settings.manage' => ['salaries_payroll_settings_manage'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::MAPPING as $name => $counterparts) {
            $permissionId = $this->permissionId($name);

            $counterpartIds = DB::table('permissions')
                ->whereIn('name', $counterparts)
                ->where('guard_name', 'web')
                ->pluck('id');

            if ($counterpartIds->isEmpty()) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->whereIn('permission_id', $counterpartIds)
                ->distinct()
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::MAPPING))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    private function permissionId(string $name): int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function forgetPermissionCache(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الذاكرةُ المؤقتة تنتهي وحدها — لا يُفشَل الترحيلُ لأجلها
        }
    }
};
