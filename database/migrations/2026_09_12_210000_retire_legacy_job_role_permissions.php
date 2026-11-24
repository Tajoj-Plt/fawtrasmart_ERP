<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * تقاعدُ أسماء الصلاحيات القديمة — «صلاحيات أخرى» في شاشة الأدوار.
 *
 * ═════════════════════ ما هذه المئةُ وستّون ═════════════════════
 *
 * كان لجدول `permissions` مصدران: كتالوجاتُ `app/Constants/permissions/`
 * (المصدر الحيّ، تقرؤه الشيفرة) و`JobRole::$job_roles` مع
 * `config/job_permissions.php` (النظام القديم، يزرعه `permissions:sync`).
 * وما زرعه الثاني ولم يعرفه الأول بقي صفّاً **بلا قسمٍ ولا مجموعة**، فوقع
 * كلُّه في سلّةٍ واحدة اسمها «صلاحيات أخرى» أسفل شاشة الأدوار — مئةٌ
 * وستّون خانةً بأسمائها الإنجليزية الخام، بلا تسميةٍ عربية ولا وصف.
 *
 * وستٌّ أُخرى كانت في الكتالوج موسومةً «(اسم قديم)» — مُدرَجةً هناك
 * لتُصنَّف لا لأنها الاسمُ المقصود، لأنّ الشيفرة كانت تقرؤها حرفياً.
 *
 * ═════════════════════ ما جرى في الشيفرة أولاً ═════════════════════
 *
 * لم يُحذف اسمٌ تقرؤه الشيفرة إلا بعد تحويل موضع قراءته إلى مقابله في
 * الكتالوج: القوائمُ الجانبية والرؤوس، و`AssignedEmployeeAccess`،
 * و`InvoiceTransferController`، و`Installment::scopeVisibleTo()`،
 * و`NotificationsController`، و`ChecksClientProximity` (سقط منها الوسيطُ
 * القديم كلّه). فالمحذوفُ هنا لا يحرس شيئاً.
 *
 * ═════════════════════ ولماذا المنحُ قبل الحذف ═════════════════════
 *
 * حذفُ صفٍّ يسحبُه من كل دورٍ يحمله (`cascadeOnDelete`). فلكل اسمٍ كانت
 * الشيفرة تقرؤه، يُمنح مقابلُه الحديث لكل دورٍ يحمل القديم — قبل الحذف،
 * وبنفس الترتيب — حتى لا يفقد أحدٌ وصولاً كان يملكه.
 *
 * **ما لا يُمنح: نطاقاتُ `data_scope_*`.** كان `sales_view_all_invoices`
 * يعني «كلُّ الفواتير»، ومقابلُه اليوم `data_scope_all` — وهو نطاقٌ عابرٌ
 * للنظام كلِّه لا للمبيعات وحدها، فمنحُه آلياً يوسّع ما لم يُطلب توسيعه.
 * فيُمنح بدلَه `sales.invoices.view` (العملية وحدها)، ويبقى قرارُ النطاق
 * لشاشة الأدوار. والأثرُ عمليّاً على دورٍ واحد — «مشرف» — وهو أثرُ
 * **اتّساقٍ لا تضييق**: شاشةُ الفواتير نفسُها تقرأ `data_scope_all` منذ
 * نقلها، فكان هذا الدور مقيَّداً فيها أصلاً وواسعاً في شاشات العميل وحدها.
 *
 * `down()` لا تُعيد شيئاً: الترحيلُ لا يعرف أيَّ الأدوار كانت تحمل ماذا،
 * والشيفرةُ لم تعد تقرأ أياً من هذه الأسماء فإعادةُ الصفّ لا تُعيد سلوكاً.
 */
return new class extends Migration
{
    /**
     * الاسم القديم ← مقابلُه في الكتالوج، لكل اسمٍ كانت الشيفرة تقرؤه.
     *
     * @var array<string, string>
     */
    private const SUCCESSORS = [
        'Edit_Client' => 'clients_edit_delete_all_clients',
        'Delete_Client' => 'clients_delete',
        'check_cycle_view_checkbook' => 'account.cheque_books.view',
        'check_cycle_add_checkbook' => 'account.cheque_books.create',
        'check_cycle_edit_delete_checkbook' => 'account.cheque_books.edit',
        'check_cycle_manage_received_checks' => 'account.received_cheques.view',
        'employee_staffedit_delete_own_leave_requests' => 'staff_leave_requests_edit_delete_own',
        'employee_visit_justifications_view' => 'clients_visit_justifications_view_all',
        'employees_roles_add' => 'settings.roles.create',
        'employees_roles_edit' => 'settings.roles.edit',
        'finance_view_all_expenses' => 'account.expenses.view',
        'finance_view_own_expenses' => 'account.expenses.view',
        'finance_view_all_receipts' => 'account.incomes.view',
        'finance_view_own_receipts' => 'account.incomes.view',
        'finance_view_own_cashboxes' => 'account.treasury.view',
        'finance_edit_default_cashbox' => 'account.treasury.edit',
        'g_a_d_r_view_all_journal_entries' => 'account.journal.view',
        'g_a_d_r_add_edit_delete_all_journal_entries' => 'account.journal.create',
        'g_a_d_r_manage_journal_entries' => 'account.journal.edit',
        'g_a_d_r_view_cost_centers' => 'account.cost_centers.view',
        'g_a_d_r_add_new_assets' => 'account.assets.create',
        'installments_add' => 'sales.installments.create',
        'installments_edit' => 'sales.installments.edit_amount',
        'installments_delete' => 'sales.installments.edit_amount',
        'installments_collect' => 'sales.installments.view',
        'installments_view_all' => 'sales.installments.view',
        'installments_view_own' => 'sales.installments.view',
        'inv_manage_inventory_permission_view' => 'stock.permits.view',
        'products_add_product' => 'stock.products.edit',
        'products_view_all_products' => 'stock.products.view',
        'products_view_own_products' => 'stock.products.view',
        'products_edit_delete_all_products' => 'stock.products.edit',
        'products_edit_delete_own_products' => 'stock.products.edit',
        'products_view_price_groups' => 'stock.price_lists.view',
        'products_add_edit_price_groups' => 'stock.price_lists.edit',
        'products_delete_price_groups' => 'stock.price_lists.delete',
        'purchase_cycle_orders_manage' => 'purchases.orders.edit',
        'sales_add_credit_notice_all' => 'sales.credit_notes.create',
        'sales_add_credit_notice_own' => 'sales.credit_notes.create',
        'sales_add_payments_all' => 'sales.payments.create',
        'sales_add_payments_own' => 'sales.payments.create',
        'sales_add_payments_assigned_client' => 'sales.payments.view_assigned_clients',
        'sales_add_quote_all' => 'sales.quotes.create',
        'sales_create_invoice_from_any_location' => 'sales.invoices.bypass_location',
        'sales_credit_note_from_any_location' => 'sales.credit_notes.bypass_location',
        'sales_return_invoice_from_any_location' => 'sales.returns.bypass_location',
        'sales_edit_delete_all_invoices' => 'sales.invoices.edit',
        'sales_edit_delete_own_invoices' => 'sales.invoices.edit',
        'sales_edit_delete_assigned_client_invoices' => 'sales.invoices.view_assigned_clients',
        'sales_view_all_credit_notices' => 'sales.credit_notes.view',
        'sales_view_own_credit_notices' => 'sales.credit_notes.view',
        'sales_view_all_payments' => 'sales.payments.view',
        'sales_view_all_return_invoices' => 'sales.returns.view',
        'sales_view_own_return_invoices' => 'sales.returns.view',
        'storehouse_view' => 'stock.warehouses.view',
        // النطاقُ لا يُمنح آلياً — انظر ديباجة الملف
        'sales_view_all_invoices' => 'sales.invoices.view',
        'sales_view_own_invoices' => 'sales.invoices.view',
        'sales_view_assigned_client_invoices' => 'sales.invoices.view_assigned_clients',
        'view_system_notifications' => 'notifications_view_system_notifications',
    ];

    /**
     * كلُّ ما يُحذف — الستّون بعد المئة من سلّة «صلاحيات أخرى»، والستُّ
     * الموسومة «(اسم قديم)» في الكتالوج.
     *
     * @var array<int, string>
     */
    private const RETIRED = [
        'Delete_Client',
        'Edit_Client',
        'check_cycle_add_checkbook',
        'check_cycle_edit_delete_checkbook',
        'check_cycle_manage_received_checks',
        'check_cycle_view_checkbook',
        'employee_staffedit_delete_own_leave_requests',
        'employee_visit_justifications_view',
        'employees_roles_add',
        'employees_roles_edit',
        'finance_add_edit_delete_draft_expenses',
        'finance_add_edit_delete_draft_revenue',
        'finance_add_expense',
        'finance_add_revenue',
        'finance_add_revenue_expense_category',
        'finance_edit_default_cashbox',
        'finance_edit_delete_all_expenses',
        'finance_edit_delete_all_receipts',
        'finance_edit_delete_own_expenses',
        'finance_edit_delete_own_receipts',
        'finance_view_all_expenses',
        'finance_view_all_receipts',
        'finance_view_own_cashboxes',
        'finance_view_own_expenses',
        'finance_view_own_receipts',
        'g_a_d_r_add_edit_delete_all_journal_entries',
        'g_a_d_r_add_edit_delete_draft_journal_entries',
        'g_a_d_r_add_edit_delete_own_journal_entries',
        'g_a_d_r_add_new_assets',
        'g_a_d_r_manage_closed_periods',
        'g_a_d_r_manage_cost_centers',
        'g_a_d_r_manage_journal_entries',
        'g_a_d_r_view_all_journal_entries',
        'g_a_d_r_view_closed_periods',
        'g_a_d_r_view_cost_centers',
        'g_a_d_r_view_own_journal_entries',
        'installments_add',
        'installments_collect',
        'installments_delete',
        'installments_edit',
        'installments_view_all',
        'installments_view_own',
        'inv_manage_allow_sale_below_min_price',
        'inv_manage_inventory_edit_quantity',
        'inv_manage_inventory_monitor',
        'inv_manage_inventory_permission_add',
        'inv_manage_inventory_permission_edit',
        'inv_manage_inventory_permission_view',
        'inv_manage_inventory_price_edit',
        'inv_manage_inventory_transfer',
        'inv_manage_inventory_view_price',
        'inv_manage_purchase_invoice_add',
        'inv_manage_purchase_invoice_edit_delete_all',
        'inv_manage_purchase_invoice_edit_delete_own',
        'inv_manage_purchase_invoices_view_all',
        'inv_manage_purchase_invoices_view_own',
        'inv_manage_suppliers_add',
        'inv_manage_suppliers_edit_delete_all',
        'inv_manage_suppliers_edit_delete_own',
        'inv_manage_suppliers_view_all',
        'inv_manage_suppliers_view_created',
        'inventory_management_add',
        'inventory_management_delete',
        'inventory_management_edit',
        'inventory_management_view',
        'inventory_settings_add',
        'inventory_settings_delete',
        'inventory_settings_edit',
        'inventory_settings_view',
        'points_sale_view_all_sessions',
        'product_settings_add',
        'product_settings_delete',
        'product_settings_edit',
        'product_settings_view',
        'product_tracking_view',
        'products_add_edit_price_groups',
        'products_add_product',
        'products_delete_price_groups',
        'products_edit_delete_all_products',
        'products_edit_delete_own_products',
        'products_view_all_products',
        'products_view_all_warehouse_movements',
        'products_view_other_products',
        'products_view_own_products',
        'products_view_price_groups',
        'products_view_system_products',
        'products_view_warehouse_movements',
        'products_view_warehouse_products',
        'purchase_cycle_order_to_invoice',
        'purchase_cycle_orders_approve_reject',
        'purchase_cycle_orders_manage',
        'purchase_cycle_orders_manage_orders',
        'purchase_cycle_quotes_approve_reject',
        'purchase_cycle_quotes_manage',
        'purchase_cycle_quotes_to_orders',
        'sales.customer_wallet.settle',
        'sales.customer_wallet.view',
        'sales_add_credit_notice_all',
        'sales_add_credit_notice_own',
        'sales_add_invoices',
        'sales_add_own_invoices',
        'sales_add_payments_all',
        'sales_add_payments_assigned_client',
        'sales_add_payments_own',
        'sales_add_periodic_invoices_all',
        'sales_add_periodic_invoices_own',
        'sales_add_quote_all',
        'sales_add_quote_own',
        'sales_add_return_invoice',
        'sales_add_sales_order_all',
        'sales_add_sales_order_own',
        'sales_change_seller',
        'sales_create_invoice_from_any_location',
        'sales_create_tax_report',
        'sales_credit_note_from_any_location',
        'sales_daily_closing_entry',
        'sales_edit_delete_all_credit_notices',
        'sales_edit_delete_all_invoices',
        'sales_edit_delete_all_payments',
        'sales_edit_delete_all_periodic_invoices',
        'sales_edit_delete_all_quotes',
        'sales_edit_delete_all_return_invoices',
        'sales_edit_delete_all_sales_orders',
        'sales_edit_delete_assigned_client_invoices',
        'sales_edit_delete_own_credit_notices',
        'sales_edit_delete_own_invoices',
        'sales_edit_delete_own_payments',
        'sales_edit_delete_own_periodic_invoices',
        'sales_edit_delete_own_quotes',
        'sales_edit_delete_own_return_invoices',
        'sales_edit_delete_own_sales_orders',
        'sales_edit_invoice_date',
        'sales_edit_payment_options',
        'sales_invoice_all_products',
        'sales_return_invoice_from_any_location',
        'sales_test_print_template',
        'sales_view_all_credit_notices',
        'sales_view_all_invoices',
        'sales_view_all_payments',
        'sales_view_all_periodic_invoices',
        'sales_view_all_quotes',
        'sales_view_all_return_invoices',
        'sales_view_all_sales_orders',
        'sales_view_assigned_client_invoices',
        'sales_view_invoice_profit',
        'sales_view_kpi_dashboard',
        'sales_view_own_credit_notices',
        'sales_view_own_invoices',
        'sales_view_own_periodic_invoices',
        'sales_view_own_quotes',
        'sales_view_own_return_invoices',
        'sales_view_own_sales_orders',
        'sales_view_payments_assigned_client',
        'sales_view_periodic_invoices',
        'sales_view_returned_invoices',
        'store_permits_add',
        'store_permits_delete',
        'store_permits_edit',
        'store_permits_view',
        'storehouse_add',
        'storehouse_delete',
        'storehouse_edit',
        'storehouse_report_export',
        'storehouse_report_view',
        'storehouse_view',
        'view_system_notifications',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', self::RETIRED)->pluck('id', 'name');

        if ($ids->isEmpty()) {
            return;
        }

        $this->grantSuccessors($ids);

        DB::table('role_has_permissions')->whereIn('permission_id', $ids->values())->delete();

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->whereIn('permission_id', $ids->values())->delete();
        }

        DB::table('permissions')->whereIn('id', $ids->values())->delete();

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        // لا رجعة — انظر ديباجة الملف
    }

    /**
     * منحُ المقابل الحديث لكل دورٍ يحمل اسماً قديماً كانت الشيفرة تقرؤه.
     *
     * @param  \Illuminate\Support\Collection<string, int>  $retiredIds
     */
    private function grantSuccessors($retiredIds): void
    {
        $successorIds = DB::table('permissions')
            ->whereIn('name', array_values(self::SUCCESSORS))
            ->pluck('id', 'name');

        $rows = [];

        foreach (self::SUCCESSORS as $legacy => $successor) {
            $legacyId = $retiredIds[$legacy] ?? null;
            $successorId = $successorIds[$successor] ?? null;

            if (! $legacyId || ! $successorId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $legacyId)->pluck('role_id') as $roleId) {
                $rows[$successorId.'-'.$roleId] = ['permission_id' => $successorId, 'role_id' => $roleId];
            }
        }

        if ($rows === []) {
            return;
        }

        // `insertOrIgnore` لا `insert`: أغلب الأدوار تحمل المقابلَ أصلاً
        foreach (array_chunk(array_values($rows), 200) as $chunk) {
            DB::table('role_has_permissions')->insertOrIgnore($chunk);
        }
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
