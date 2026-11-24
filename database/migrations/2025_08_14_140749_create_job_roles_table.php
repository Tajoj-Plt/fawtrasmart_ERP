<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('job_roles')) {
            Schema::create('job_roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('role_name');
                $table->tinyInteger('role_type');
                $table->tinyInteger('work_cycle')->default(0);
                $table->tinyInteger('templates')->default(0);
                $table->tinyInteger('branches')->default(0);
            
                // System notifications and tasks
                $table->integer('view_system_notifications')->default(0);
                $table->integer('tasks_view_tasks')->default(0);
            
                // Sales permissions
                $table->tinyInteger('Issue_an_invoice_to_a_customer_who_has_a_debt')->default(0);
                $table->tinyInteger('sales_add_invoices')->default(0);
                $table->tinyInteger('sales_add_own_invoices')->default(0);
                $table->tinyInteger('sales_edit_delete_all_invoices')->default(0);
                $table->tinyInteger('sales_edit_delete_own_invoices')->default(0);
                $table->tinyInteger('sales_view_own_invoices')->default(0);
                $table->tinyInteger('sales_view_all_invoices')->default(0);
                $table->tinyInteger('sales_create_tax_report')->default(0);
                $table->tinyInteger('sales_change_seller')->default(0);
                $table->tinyInteger('sales_invoice_all_products')->default(0);
                $table->tinyInteger('sales_view_invoice_profit')->default(0);
                $table->tinyInteger('sales_add_credit_notice_all')->default(0);
                $table->tinyInteger('sales_add_credit_notice_own')->default(0);
                $table->tinyInteger('sales_edit_invoice_date')->default(0);
                $table->tinyInteger('sales_add_payments_all')->default(0);
                $table->tinyInteger('sales_add_payments_own')->default(0);
                $table->tinyInteger('sales_edit_payment_options')->default(0);
                $table->tinyInteger('sales_edit_delete_all_payments')->default(0);
                $table->tinyInteger('sales_edit_delete_own_payments')->default(0);
                $table->tinyInteger('sales_add_quote_all')->default(0);
                $table->tinyInteger('sales_add_quote_own')->default(0);
                $table->tinyInteger('sales_view_all_quotes')->default(0);
                $table->tinyInteger('sales_view_own_quotes')->default(0);
                $table->tinyInteger('sales_edit_delete_all_quotes')->default(0);
                $table->tinyInteger('sales_edit_delete_own_quotes')->default(0);
                $table->tinyInteger('sales_view_all_sales_orders')->default(0);
                $table->tinyInteger('sales_view_own_sales_orders')->default(0);
                $table->tinyInteger('sales_add_sales_order_all')->default(0);
                $table->tinyInteger('sales_add_sales_order_own')->default(0);
                $table->tinyInteger('sales_edit_delete_all_sales_orders')->default(0);
                $table->tinyInteger('sales_edit_delete_own_sales_orders')->default(0);
                $table->tinyInteger('sales_edit_delete_all_credit_notices')->default(0);
                $table->tinyInteger('sales_edit_delete_own_credit_notices')->default(0);
                $table->tinyInteger('sales_view_all_credit_notices')->default(0);
                $table->tinyInteger('sales_view_own_credit_notices')->default(0);
                $table->tinyInteger('points_sale_edit_product_prices')->default(0);
                $table->tinyInteger('points_sale_add_discount')->default(0);
                $table->tinyInteger('points_sale_open_sessions_all')->default(0);
                $table->tinyInteger('points_sale_open_sessions_own')->default(0);
                $table->tinyInteger('points_sale_close_sessions_all')->default(0);
                $table->tinyInteger('points_sale_close_sessions_own')->default(0);
                $table->tinyInteger('points_sale_view_all_sessions')->default(0);
                $table->tinyInteger('points_sale_view_own_sessions')->default(0);
                $table->tinyInteger('points_sale_confirm_close_sessions_all')->default(0);
                $table->tinyInteger('points_sale_confirm_close_sessions_own')->default(0);
            
                // Products permissions
                $table->tinyInteger('products_add_product')->default(0);
                $table->tinyInteger('products_view_all_products')->default(0);
                $table->tinyInteger('products_view_own_products')->default(0);
                $table->tinyInteger('products_edit_delete_all_products')->default(0);
                $table->tinyInteger('products_edit_delete_own_products')->default(0);
                $table->tinyInteger('products_view_price_groups')->default(0);
                $table->tinyInteger('products_add_edit_price_groups')->default(0);
                $table->tinyInteger('products_delete_price_groups')->default(0);
                $table->tinyInteger('sending_invoices_to_the_tax_authority')->default(0);
            
                // Client follow-up permissions
                $table->tinyInteger('client_follow_up_add_notes_attachments_appointments_all')->default(0);
                $table->tinyInteger('client_follow_up_add_notes_attachments_appointments_own')->default(0);
                $table->tinyInteger('client_follow_up_edit_delete_notes_attachments_appointments_all')->default(0);
                $table->tinyInteger('client_follow_up_edit_delete_notes_attachments_appointments_own')->default(0);
                $table->tinyInteger('client_follow_up_view_notes_attachments_appointments_all')->default(0);
                $table->tinyInteger('client_follow_up_view_notes_attachments_appointments_assigned')->default(0);
                $table->tinyInteger('client_follow_up_view_notes_attachments_appointments_own')->default(0);
                $table->tinyInteger('client_follow_up_assign_clients_to_employees')->default(0);
            
                // Clients permissions
                $table->tinyInteger('clients_add_client')->default(0);
                $table->tinyInteger('clients_view_all_clients')->default(0);
                $table->tinyInteger('Edit_Client')->default(0);
                $table->tinyInteger('Delete_Client')->default(0);
                $table->tinyInteger('clients_view_own_clients')->default(0);
                $table->tinyInteger('clients_edit_delete_all_clients')->default(0);
                $table->tinyInteger('clients_edit_delete_own_clients')->default(0);
                $table->tinyInteger('clients_view_all_activity_logs')->default(0);
                $table->tinyInteger('clients_view_own_activity_log')->default(0);
                $table->tinyInteger('clients_edit_client_settings')->default(0);
                $table->tinyInteger('clients_view_all_reports')->default(0);
                $table->tinyInteger('clients_view_own_reports')->default(0);
            
                // Points credits permissions
            
                // Membership permissions
            
                // Customer attendance permissions
                $table->tinyInteger('customer_attendance_display')->default(0);
                $table->tinyInteger('customer_attendance_manage')->default(0);
            
                // Employees permissions
                $table->tinyInteger('employees_add')->default(0);
                $table->tinyInteger('employees_edit_delete')->default(0);
                $table->tinyInteger('employees_roles_add')->default(0);
                $table->tinyInteger('employees_roles_edit')->default(0);
                $table->tinyInteger('employees_view_profile')->default(0);
                $table->tinyInteger('hr_system_management')->default(0);
            
                // Salaries permissions
                $table->tinyInteger('salaries_loans_manage')->default(0);
                $table->tinyInteger('salaries_payroll_view')->default(0);
                $table->tinyInteger('salaries_payroll_create')->default(0);
                $table->tinyInteger('salaries_payroll_approve')->default(0);
                $table->tinyInteger('salaries_payroll_edit')->default(0);
                $table->tinyInteger('salaries_payroll_delete')->default(0);
                $table->tinyInteger('salaries_contracts_notifications')->default(0);
                $table->tinyInteger('salaries_contracts_view_own')->default(0);
                $table->tinyInteger('salaries_contracts_edit_delete_own')->default(0);
                $table->tinyInteger('salaries_payroll_settings_manage')->default(0);
                $table->tinyInteger('salaries_payroll_view_own')->default(0);
                $table->tinyInteger('salaries_payroll_delete_all')->default(0);
                $table->tinyInteger('salaries_payroll_payment')->default(0);
                $table->tinyInteger('salaries_contracts_receive_notifications')->default(0);
                $table->tinyInteger('salaries_contracts_view_all')->default(0);
                $table->tinyInteger('salaries_contracts_edit_delete_all')->default(0);
                $table->tinyInteger('salaries_contracts_create')->default(0);
            
                // Staff attendance permissions
                $table->tinyInteger('staff_attendance_online')->default(0);
                $table->tinyInteger('staff_attendance_pull_from_device')->default(0);
                $table->tinyInteger('staff_attendance_view_all')->default(0);
                $table->tinyInteger('staff_attendance_settings_manage')->default(0);
                $table->tinyInteger('staff_attendance_delete')->default(0);
                $table->tinyInteger('staff_attendance_edit_days')->default(0);
                $table->tinyInteger('staff_attendance_view_own')->default(0);
                $table->tinyInteger('staff_attendance_change_status')->default(0);
                $table->tinyInteger('staff_attendance_report_view')->default(0);
                $table->tinyInteger('staff_leave_requests_edit_delete_own')->default(0);
                $table->tinyInteger('staff_leave_requests_view_own')->default(0);
                $table->tinyInteger('staff_leave_requests_approve_reject')->default(0);
                $table->tinyInteger('staff_attendance_self_registration')->default(0);
                $table->tinyInteger('staff_attendance_import')->default(0);
                $table->tinyInteger('staff_attendance_view_own_records')->default(0);
                $table->tinyInteger('staff_inventory_permissions_manage')->default(0);
                $table->tinyInteger('staff_attendance_calculate_days')->default(0);
                $table->tinyInteger('staff_attendance_create_book')->default(0);
                $table->tinyInteger('staff_attendance_view_other_books')->default(0);
                $table->tinyInteger('staff_attendance_delete_books')->default(0);
                $table->tinyInteger('staff_leave_requests_add')->default(0);
                $table->tinyInteger('staff_leave_requests_edit_delete_all')->default(0);
                $table->tinyInteger('staff_leave_requests_view_all')->default(0);
            
                // Orders permissions
                $table->tinyInteger('orders_management')->default(0);
                $table->tinyInteger('orders_setting_management')->default(0);
            
                // Inventory permissions
                $table->tinyInteger('inv_manage_inventory_permission_add')->default(0);
                $table->tinyInteger('inv_manage_inventory_permission_view')->default(0);
                $table->tinyInteger('inv_manage_inventory_permission_edit')->default(0);
                $table->tinyInteger('inv_manage_inventory_price_edit')->default(0);
                $table->tinyInteger('inv_manage_inventory_view_price')->default(0);
                $table->tinyInteger('inv_manage_purchase_invoices_view_own')->default(0);
                $table->tinyInteger('inv_manage_purchase_invoice_add')->default(0);
                $table->tinyInteger('inv_manage_purchase_invoice_edit_delete_own')->default(0);
                $table->tinyInteger('inv_manage_purchase_invoice_edit_delete_all')->default(0);
                $table->tinyInteger('inv_manage_purchase_invoices_view_all')->default(0);
                $table->tinyInteger('inv_manage_suppliers_add')->default(0);
                $table->tinyInteger('inv_manage_suppliers_view_all')->default(0);
                $table->tinyInteger('inv_manage_suppliers_edit_delete_all')->default(0);
                $table->tinyInteger('inv_manage_suppliers_edit_delete_own')->default(0);
                $table->tinyInteger('inv_manage_suppliers_view_created')->default(0);
                $table->tinyInteger('inv_manage_inventory_edit_quantity')->default(0);
                $table->tinyInteger('inv_manage_inventory_transfer')->default(0);
                $table->tinyInteger('inv_manage_allow_sale_below_min_price')->default(0);
                $table->tinyInteger('inv_manage_inventory_monitor')->default(0);
            
                // Purchase cycle permissions
                $table->tinyInteger('purchase_cycle_orders_manage')->default(0);
                $table->tinyInteger('purchase_cycle_quotes_manage')->default(0);
                $table->tinyInteger('purchase_cycle_quotes_to_orders')->default(0);
                $table->tinyInteger('purchase_cycle_order_to_invoice')->default(0);
                $table->tinyInteger('purchase_cycle_orders_approve_reject')->default(0);
                $table->tinyInteger('purchase_cycle_quotes_approve_reject')->default(0);
                $table->tinyInteger('purchase_cycle_orders_manage_orders')->default(0);
            
                // Supply orders permissions
                $table->tinyInteger('supply_orders_view_all')->default(0);
                $table->tinyInteger('supply_orders_add')->default(0);
                $table->tinyInteger('supply_orders_edit_delete_all')->default(0);
                $table->tinyInteger('supply_orders_edit_delete_own')->default(0);
                $table->tinyInteger('supply_orders_update_status')->default(0);
                $table->tinyInteger('supply_orders_view_own')->default(0);
            
                // Track time permissions
                $table->tinyInteger('track_time_add_employee_work_hours')->default(0);
                $table->tinyInteger('track_time_edit_other_employees_work_hours')->default(0);
                $table->tinyInteger('track_time_view_other_employees_work_hours')->default(0);
                $table->tinyInteger('track_time_edit_delete_all_projects')->default(0);
                $table->tinyInteger('track_time_add_new_project')->default(0);
                $table->tinyInteger('track_time_edit_delete_all_activities')->default(0);
                $table->tinyInteger('track_time_add_new_activity')->default(0);
            
                // Rental unit permissions
                $table->tinyInteger('rental_unit_view_booking_orders')->default(0);
                $table->tinyInteger('rental_unit_manage_booking_orders')->default(0);
                $table->tinyInteger('rental_unit_manage_rental_settings')->default(0);
            
                // GADR permissions
                $table->tinyInteger('g_a_d_r_add_new_assets')->default(0);
                $table->tinyInteger('g_a_d_r_view_cost_centers')->default(0);
                $table->tinyInteger('g_a_d_r_manage_cost_centers')->default(0);
                $table->tinyInteger('g_a_d_r_manage_closed_periods')->default(0);
                $table->tinyInteger('g_a_d_r_view_closed_periods')->default(0);
                $table->tinyInteger('g_a_d_r_manage_journal_entries')->default(0);
                $table->tinyInteger('g_a_d_r_view_all_journal_entries')->default(0);
                $table->tinyInteger('g_a_d_r_view_own_journal_entries')->default(0);
                $table->tinyInteger('g_a_d_r_add_edit_delete_all_journal_entries')->default(0);
                $table->tinyInteger('g_a_d_r_add_edit_delete_own_journal_entries')->default(0);
                $table->tinyInteger('g_a_d_r_add_edit_delete_draft_journal_entries')->default(0);
            
                // Finance permissions
                $table->tinyInteger('finance_add_expense')->default(0);
                $table->tinyInteger('finance_edit_delete_all_expenses')->default(0);
                $table->tinyInteger('finance_edit_delete_own_expenses')->default(0);
                $table->tinyInteger('finance_view_all_expenses')->default(0);
                $table->tinyInteger('finance_view_own_expenses')->default(0);
                $table->tinyInteger('finance_add_edit_delete_draft_expenses')->default(0);
                $table->tinyInteger('finance_edit_default_cashbox')->default(0);
                $table->tinyInteger('finance_view_own_cashboxes')->default(0);
                $table->tinyInteger('finance_add_revenue')->default(0);
                $table->tinyInteger('finance_edit_delete_all_receipts')->default(0);
                $table->tinyInteger('finance_edit_delete_own_receipts')->default(0);
                $table->tinyInteger('finance_view_all_receipts')->default(0);
                $table->tinyInteger('finance_view_own_receipts')->default(0);
                $table->tinyInteger('finance_add_edit_delete_draft_revenue')->default(0);
                $table->tinyInteger('finance_add_revenue_expense_category')->default(0);
            
                // Check cycle permissions
                $table->tinyInteger('check_cycle_add_checkbook')->default(0);
                $table->tinyInteger('check_cycle_view_checkbook')->default(0);
                $table->tinyInteger('check_cycle_edit_delete_checkbook')->default(0);
                $table->tinyInteger('check_cycle_manage_received_checks')->default(0);
            
                // Settings permissions
                $table->tinyInteger('settings_edit_general_settings')->default(0);
                $table->tinyInteger('settings_edit_tax_settings')->default(0);
                $table->tinyInteger('settings_view_own_reports')->default(0);
            
                // Online store permissions
                $table->tinyInteger('online_store_content_management')->default(0);
            
                // Employee staff permissions
                $table->tinyInteger('employee_staffmark_own_attendance_online')->default(0);
                $table->tinyInteger('employee_staffview_own_attendance_books')->default(0);
                $table->tinyInteger('employee_staffedit_delete_own_leave_requests')->default(0);
                $table->tinyInteger('employee_staffview_own_attendance_logs')->default(0);
                $table->tinyInteger('employee_staffadd_leave_request')->default(0);
                $table->tinyInteger('employee_staffview_own_leave_requests')->default(0);
            
                // Additional employee permissions
                $table->tinyInteger('employee_view_his_salary_slip')->default(0);
                $table->tinyInteger('employee_orders_management')->default(0);
            
                // Additional permissions
                $table->integer('sales_targets_add')->default(0);
                $table->integer('sales_targets_settle')->default(0);
                $table->integer('advance_requests_approve')->default(0);
                $table->integer('advance_requests_create')->default(0);
                $table->tinyInteger('client_reports_view')->default(0);
                $table->tinyInteger('doubtful_debts_view')->default(0);
                $table->tinyInteger('clients_itinerary_view')->default(0);
                $table->tinyInteger('clients_visits_track')->default(0);
                $table->tinyInteger('employee_visit_justifications_view')->default(0);
                $table->tinyInteger('clients_manage_relationship')->default(0);
                $table->tinyInteger('settings_general_view')->default(0);
                $table->tinyInteger('settings_customer_categories')->default(0);
                $table->tinyInteger('settings_customer_permissions')->default(0);
                $table->tinyInteger('settings_customer_followup_status')->default(0);
                $table->tinyInteger('settings_groups_management')->default(0);
                $table->tinyInteger('settings_statistics_configuration')->default(0);
                $table->tinyInteger('clients_edit_data')->default(0);
                $table->tinyInteger('clients_manage_opening_balance')->default(0);
                $table->tinyInteger('clients_view_statement')->default(0);
                $table->tinyInteger('clients_assign_employees')->default(0);
                $table->tinyInteger('clients_manage_debts')->default(0);
                $table->tinyInteger('clients_delete')->default(0);
                $table->tinyInteger('clients_change_status')->default(0);
                $table->tinyInteger('settings_groups_management_add')->default(0);
                $table->tinyInteger('settings_groups_management_edit')->default(0);
                $table->tinyInteger('settings_groups_management_delete')->default(0);
                $table->tinyInteger('clients_itinerary_add')->default(0);
                $table->tinyInteger('clients_itinerary_filter')->default(0);
                $table->tinyInteger('clients_itinerary_export')->default(0);
                $table->tinyInteger('clients_itinerary_edit')->default(0);
                $table->tinyInteger('sales_view_kpi_dashboard')->default(0);
                $table->tinyInteger('sales_view_returned_invoices')->default(0);
                $table->tinyInteger('sales_view_periodic_invoices')->default(0);
                $table->tinyInteger('sales_test_print_template')->default(0);
                $table->tinyInteger('sales_view_reports')->default(0);
                $table->tinyInteger('sales_daily_closing_entry')->default(0);
                $table->tinyInteger('pos_view_reports')->default(0);
                $table->tinyInteger('pos_manage_settings')->default(0);
                $table->tinyInteger('supply_orders_manage_settings')->default(0);
            
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_roles');
    }
};