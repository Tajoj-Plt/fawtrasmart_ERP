<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحياتُ الموارد البشرية كلُّها بنمطٍ واحد: `<الشاشة>.<الفعل>` (2026-09-24).
 *
 * يُكمل `2026_09_24_200000_grouped_hr_payroll_permissions` (الرواتب) إلى باقي
 * القسم: الموظفون، الهيكل التنظيمي (كلُّ جزءٍ مجموعة)، الحضور، الإجازات،
 * تتبّع الوقت، التقارير. ويُلغي صلاحيات «هو» (`*_own`, `view_own`): على أيّ
 * موظفين تسري الصلاحية يحدّده نطاق الرؤية `data_scope_*` وحده.
 *
 * ١) تُنشأ الأسماء الجديدة، ويُمنح كلٌّ منها لكل دورٍ حمل **أيّاً** من نظيراته
 *    القديمة — فلا يُحجب أحدٌ عمّا كان يفتحه.
 * ٢) ثمّ تُزال صفوف الأسماء المتقاعدة وروابطها — بأسمائها صراحةً (لا «كلّ ما
 *    ليس في الكتالوج»)، فلا يُمسّ شيءٌ خارج الموارد البشرية. كانت ستبقى في شاشة
 *    الأدوار بتسمياتها القديمة بجوار الجديدة، ولم يعد في الشيفرة من يسألها.
 *
 * `down()` تسحب روابط الأسماء الجديدة من هذا الترحيل؛ الحذفُ في (٢) لا يُعاد.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> الجديدة ← نظيراتها القديمة */
    private const MAPPING = [
        'employees.index' => ['employees_view_profile'],
        'employees.show' => ['employees_view_profile'],
        'employees.create' => ['employees_add'],
        'employees.edit' => ['employees_edit_delete'],
        'employees.destroy' => ['employees_edit_delete'],
        'employees.terminate' => ['employees_terminate'],
        'employees.archive' => ['employees_archive'],
        'employees.sensitive' => ['employees_view_sensitive'],
        'employees.export' => ['employees_view_profile'],
        'employee_documents.index' => ['employees_documents_view', 'employees_documents_manage'],
        'employee_documents.create' => ['employees_documents_manage'],
        'employee_documents.edit' => ['employees_documents_manage'],
        'employee_documents.destroy' => ['employees_documents_manage'],
        'employee_accounts.access' => ['hr_system_management'],
        'employee_accounts.password' => ['hr_system_management'],
        'employee_accounts.roles' => ['hr_system_management'],
        'employee_accounts.login_as' => ['hr_system_management'],
        'employee_stock.index' => ['staff_inventory_permissions_manage'],
        'employee_stock.manage' => ['staff_inventory_permissions_manage'],

        'departments.index' => ['hr_departments_view', 'hr_departments_manage'],
        'departments.show' => ['hr_departments_view', 'hr_departments_manage'],
        'departments.create' => ['hr_departments_manage'],
        'departments.edit' => ['hr_departments_manage'],
        'departments.destroy' => ['hr_departments_manage'],
        'job_titles.index' => ['hr_job_titles_view', 'hr_job_titles_manage'],
        'job_titles.show' => ['hr_job_titles_view', 'hr_job_titles_manage'],
        'job_titles.create' => ['hr_job_titles_manage'],
        'job_titles.edit' => ['hr_job_titles_manage'],
        'job_titles.destroy' => ['hr_job_titles_manage'],
        'job_levels.index' => ['hr_functional_levels_manage'],
        'job_levels.show' => ['hr_functional_levels_manage'],
        'job_levels.create' => ['hr_functional_levels_manage'],
        'job_levels.edit' => ['hr_functional_levels_manage'],
        'job_levels.destroy' => ['hr_functional_levels_manage'],
        'job_types.index' => ['hr_job_types_manage'],
        'job_types.show' => ['hr_job_types_manage'],
        'job_types.create' => ['hr_job_types_manage'],
        'job_types.edit' => ['hr_job_types_manage'],
        'job_types.destroy' => ['hr_job_types_manage'],
        'org_chart.index' => ['hr_org_chart_view'],

        'attendance_records.index' => ['staff_attendance_records_view', 'staff_attendance_view_all', 'staff_attendance_view_own_records', 'employee_staffview_own_attendance_logs'],
        'attendance_records.create' => ['staff_attendance_manual_entry'],
        'attendance_records.destroy' => ['staff_attendance_records_delete', 'staff_attendance_delete'],
        'attendance_records.import' => ['staff_attendance_records_import', 'staff_attendance_import'],
        'attendance_records.pull' => ['staff_attendance_records_pull_device', 'staff_attendance_pull_from_device'],
        'attendance_books.index' => ['staff_attendance_books_view', 'staff_attendance_view_own', 'staff_attendance_view_other_books', 'employee_staffview_own_attendance_books'],
        'attendance_books.show' => ['staff_attendance_books_view', 'staff_attendance_view_own', 'staff_attendance_view_other_books', 'employee_staffview_own_attendance_books'],
        'attendance_books.create' => ['staff_attendance_books_create', 'staff_attendance_create_book'],
        'attendance_books.edit' => ['staff_attendance_books_edit', 'staff_attendance_books_create', 'staff_attendance_create_book'],
        'attendance_books.destroy' => ['staff_attendance_books_delete', 'staff_attendance_delete_books'],
        'attendance_books.status' => ['staff_attendance_books_change_status', 'staff_attendance_change_status'],
        'attendance_days.index' => ['staff_attendance_days_view', 'staff_attendance_view_all'],
        'attendance_days.show' => ['staff_attendance_days_view', 'staff_attendance_view_all'],
        'attendance_days.edit' => ['staff_attendance_days_edit', 'staff_attendance_edit_days'],
        'attendance_days.calculate' => ['staff_attendance_days_calculate', 'staff_attendance_calculate_days'],
        'attendance_corrections.index' => ['staff_attendance_correction_request', 'staff_attendance_correction_approve'],
        'attendance_corrections.create' => ['staff_attendance_correction_request'],
        'attendance_corrections.approve' => ['staff_attendance_correction_approve'],
        'attendance_registration.self' => ['staff_attendance_self_registration', 'employee_staffmark_own_attendance_online'],
        'attendance_registration.others' => ['staff_attendance_online'],
        'attendance_team.index' => ['staff_attendance_team_view'],
        'shifts.index' => ['hr_shifts_view', 'hr_shifts_manage'],
        'shifts.show' => ['hr_shifts_view', 'hr_shifts_manage'],
        'shifts.create' => ['hr_shifts_manage'],
        'shifts.edit' => ['hr_shifts_manage'],
        'shifts.destroy' => ['hr_shifts_manage'],
        'attendance_devices.index' => ['hr_attendance_devices_view', 'hr_attendance_devices_manage'],
        'attendance_devices.show' => ['hr_attendance_devices_view', 'hr_attendance_devices_manage'],
        'attendance_devices.create' => ['hr_attendance_devices_manage'],
        'attendance_devices.edit' => ['hr_attendance_devices_manage'],
        'attendance_devices.destroy' => ['hr_attendance_devices_manage'],
        'attendance_rules.index' => ['hr_attendance_rules_manage'],
        'attendance_rules.show' => ['hr_attendance_rules_manage'],
        'attendance_rules.create' => ['hr_attendance_rules_manage'],
        'attendance_rules.edit' => ['hr_attendance_rules_manage'],
        'attendance_rules.destroy' => ['hr_attendance_rules_manage'],
        'holidays.index' => ['hr_holidays_manage'],
        'holidays.show' => ['hr_holidays_manage'],
        'holidays.create' => ['hr_holidays_manage'],
        'holidays.edit' => ['hr_holidays_manage'],
        'holidays.destroy' => ['hr_holidays_manage'],
        'attendance_settings.manage' => ['staff_attendance_settings_manage'],

        'leave_requests.index' => ['staff_leave_requests_view', 'staff_leave_requests_view_all', 'staff_leave_requests_view_own', 'employee_staffview_own_leave_requests'],
        'leave_requests.show' => ['staff_leave_requests_view', 'staff_leave_requests_view_all', 'staff_leave_requests_view_own', 'employee_staffview_own_leave_requests'],
        'leave_requests.create' => ['staff_leave_requests_add', 'employee_staffadd_leave_request'],
        'leave_requests.edit' => ['staff_leave_requests_edit', 'staff_leave_requests_edit_delete_all', 'staff_leave_requests_edit_delete_own'],
        'leave_requests.destroy' => ['staff_leave_requests_delete', 'staff_leave_requests_edit_delete_all', 'staff_leave_requests_edit_delete_own'],
        'leave_requests.approve' => ['staff_leave_requests_approve_reject'],
        'leave_permissions.index' => ['hr_leave_permissions_view', 'hr_leave_permissions_manage'],
        'leave_permissions.show' => ['hr_leave_permissions_view', 'hr_leave_permissions_manage'],
        'leave_permissions.create' => ['hr_leave_permissions_create', 'hr_leave_permissions_manage'],
        'leave_permissions.edit' => ['hr_leave_permissions_edit', 'hr_leave_permissions_manage'],
        'leave_permissions.destroy' => ['hr_leave_permissions_delete', 'hr_leave_permissions_manage'],
        'leave_types.index' => ['hr_leave_types_manage'],
        'leave_types.show' => ['hr_leave_types_manage'],
        'leave_types.create' => ['hr_leave_types_manage'],
        'leave_types.edit' => ['hr_leave_types_manage'],
        'leave_types.destroy' => ['hr_leave_types_manage'],
        'leave_policies.index' => ['hr_leave_policies_manage'],
        'leave_policies.show' => ['hr_leave_policies_manage'],
        'leave_policies.create' => ['hr_leave_policies_manage'],
        'leave_policies.edit' => ['hr_leave_policies_manage'],
        'leave_policies.destroy' => ['hr_leave_policies_manage'],
        'leave_balances.index' => ['hr_leave_balances_manage'],
        'leave_balances.show' => ['hr_leave_balances_manage'],
        'leave_balances.create' => ['hr_leave_balances_manage'],
        'leave_balances.edit' => ['hr_leave_balances_manage'],
        'leave_balances.destroy' => ['hr_leave_balances_manage'],

        // «هو» يُلغى: من كان يرى قسيمتَه/عقدَه وحده يحمل الآن الصلاحيةَ العامة، ونطاقُه يقصره
        'payslips.index' => ['payslips.view_own'],
        'payslips.show' => ['payslips.view_own'],
        'payslips.print' => ['payslips.view_own'],
        'contracts.index' => ['contracts.view_own'],
        'contracts.show' => ['contracts.view_own'],
        'contracts.print' => ['contracts.view_own'],

        'time_entries.index' => ['track_time_view_other_employees_work_hours', 'track_time_add_employee_work_hours'],
        'time_entries.create' => ['track_time_add_employee_work_hours'],
        'time_entries.edit' => ['track_time_edit_other_employees_work_hours'],
        'time_entries.destroy' => ['track_time_edit_other_employees_work_hours'],
        'time_projects.create' => ['track_time_add_new_project'],
        'time_projects.edit' => ['track_time_edit_delete_all_projects'],
        'time_projects.destroy' => ['track_time_edit_delete_all_projects'],
        'time_activities.create' => ['track_time_add_new_activity'],
        'time_activities.edit' => ['track_time_edit_delete_all_activities'],
        'time_activities.destroy' => ['track_time_edit_delete_all_activities'],

        'hr_dashboard.index' => ['hr_dashboard_view'],
        'hr_reports.employees' => ['hr_reports_view'],
        'hr_reports.attendance' => ['hr_reports_view', 'staff_attendance_report_view'],
        'hr_reports.payroll' => ['hr_reports_view', 'payroll.index'],
        'hr_reports.export' => ['hr_reports_view'],
        'performance.index' => ['hr_performance_view'],
        'performance.export' => ['hr_performance_view'],
    ];

    /** الأسماء المتقاعدة من الجولتين — تُزال بعد نقل منحها. */
    private const RETIRED = [
        'advance_requests_approve',
        'advance_requests_cancel',
        'advance_requests_create',
        'advance_requests_disburse',
        'advance_requests_settle',
        'contracts.view_own',
        'deductions_approve',
        'deductions_create',
        'deductions_delete',
        'deductions_edit',
        'deductions_history_view',
        'deductions_reject',
        'deductions_settle',
        'deductions_view',
        'employee_staffadd_leave_request',
        'employee_staffmark_own_attendance_online',
        'employee_staffview_own_attendance_books',
        'employee_staffview_own_attendance_logs',
        'employee_staffview_own_leave_requests',
        'employee_view_his_salary_slip',
        'employees_add',
        'employees_archive',
        'employees_documents_manage',
        'employees_documents_view',
        'employees_edit_delete',
        'employees_terminate',
        'employees_view_profile',
        'employees_view_sensitive',
        'hr_attendance_devices_manage',
        'hr_attendance_devices_view',
        'hr_attendance_rules_manage',
        'hr_commissions_view',
        'hr_dashboard_view',
        'hr_departments_manage',
        'hr_departments_view',
        'hr_functional_levels_manage',
        'hr_holidays_manage',
        'hr_job_titles_manage',
        'hr_job_titles_view',
        'hr_job_types_manage',
        'hr_leave_balances_manage',
        'hr_leave_permissions_create',
        'hr_leave_permissions_delete',
        'hr_leave_permissions_edit',
        'hr_leave_permissions_manage',
        'hr_leave_permissions_view',
        'hr_leave_policies_manage',
        'hr_leave_types_manage',
        'hr_org_chart_view',
        'hr_payslips_view',
        'hr_performance_view',
        'hr_printable_templates_manage',
        'hr_reports_view',
        'hr_salary_items_manage',
        'hr_salary_items_view',
        'hr_salary_templates_manage',
        'hr_shifts_manage',
        'hr_shifts_view',
        'hr_system_management',
        'overtime_requests_approve',
        'overtime_requests_create',
        'payslips.view_own',
        'salaries_contracts_create',
        'salaries_contracts_edit_delete_all',
        'salaries_contracts_edit_delete_own',
        'salaries_contracts_notifications',
        'salaries_contracts_receive_notifications',
        'salaries_contracts_view_all',
        'salaries_contracts_view_own',
        'salaries_eos_manage',
        'salaries_eos_pay',
        'salaries_eos_view',
        'salaries_loans_manage',
        'salaries_overtime_manage',
        'salaries_payroll_approve',
        'salaries_payroll_bank_export',
        'salaries_payroll_create',
        'salaries_payroll_delete',
        'salaries_payroll_delete_all',
        'salaries_payroll_edit',
        'salaries_payroll_export',
        'salaries_payroll_hold',
        'salaries_payroll_payment',
        'salaries_payroll_print',
        'salaries_payroll_retro',
        'salaries_payroll_reverse',
        'salaries_payroll_settings_manage',
        'salaries_payroll_view',
        'salaries_payroll_view_own',
        'salaries_statutory_manage',
        'salaries_statutory_remit',
        'salaries_statutory_view',
        'staff_attendance_books_change_status',
        'staff_attendance_books_create',
        'staff_attendance_books_delete',
        'staff_attendance_books_edit',
        'staff_attendance_books_view',
        'staff_attendance_calculate_days',
        'staff_attendance_change_status',
        'staff_attendance_correction_approve',
        'staff_attendance_correction_request',
        'staff_attendance_create_book',
        'staff_attendance_days_calculate',
        'staff_attendance_days_edit',
        'staff_attendance_days_view',
        'staff_attendance_delete',
        'staff_attendance_delete_books',
        'staff_attendance_edit_days',
        'staff_attendance_import',
        'staff_attendance_manual_entry',
        'staff_attendance_online',
        'staff_attendance_pull_from_device',
        'staff_attendance_records_delete',
        'staff_attendance_records_import',
        'staff_attendance_records_pull_device',
        'staff_attendance_records_view',
        'staff_attendance_report_view',
        'staff_attendance_self_registration',
        'staff_attendance_settings_manage',
        'staff_attendance_team_view',
        'staff_attendance_view_all',
        'staff_attendance_view_other_books',
        'staff_attendance_view_own',
        'staff_attendance_view_own_records',
        'staff_inventory_permissions_manage',
        'staff_leave_requests_add',
        'staff_leave_requests_approve_reject',
        'staff_leave_requests_delete',
        'staff_leave_requests_edit',
        'staff_leave_requests_edit_delete_all',
        'staff_leave_requests_edit_delete_own',
        'staff_leave_requests_view',
        'staff_leave_requests_view_all',
        'staff_leave_requests_view_own',
        'track_time_add_employee_work_hours',
        'track_time_add_new_activity',
        'track_time_add_new_project',
        'track_time_edit_delete_all_activities',
        'track_time_edit_delete_all_projects',
        'track_time_edit_other_employees_work_hours',
        'track_time_view_other_employees_work_hours',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        DB::transaction(function () {
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

            $retiredIds = DB::table('permissions')
                ->whereIn('name', self::RETIRED)
                ->where('guard_name', 'web')
                ->pluck('id');

            if ($retiredIds->isNotEmpty()) {
                DB::table('role_has_permissions')->whereIn('permission_id', $retiredIds)->delete();
                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $retiredIds)->delete();
                }
                DB::table('permissions')->whereIn('id', $retiredIds)->delete();
            }
        });

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
