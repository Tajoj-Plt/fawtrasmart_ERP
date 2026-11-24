<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobRole extends Model
{
    use HasFactory;

    protected $table = 'job_roles';

    protected $guarded = [];

    public static $job_roles = [
        // System notifications and tasks
        'tasks_view_tasks',

        // ⭐ صلاحيات الإشعارات - Notifications Permissions
        'notifications_view_all_notifications',
        'notifications_view_system_notifications',
        'notifications_view_client_notifications',
        'notifications_send_notifications',
        'notifications_reply_to_notifications',
        'notifications_mark_as_read',
        'notifications_delete_notifications',

        // Basic permissions
        'work_cycle',
        'templates',
        'branches',
        'Issue_an_invoice_to_a_customer_who_has_a_debt',

        // Sales targets and advances
        'sales.targets.manage',
        'sales.targets.settle',
        // صلاحياتُ الرواتب والسلف والخصميات القديمة أُزيلت من هنا (2026-09-24):
        // مصدرُها اليوم كتالوج `HrPermissions` (payroll.* / loans.* / deductions.* …)
        // ويُنشئها `permissions:sync-catalog` — لو بقيت هنا لأعاد
        // `permissions:sync` زرعَ الأسماء المتقاعدة صفوفاً يتيمة.

        // Deductions (الخصميات / الحسومات)

        // Sales permissions

        // Return invoices permissions

        // Assigned employee client permissions
        'client_follow_up_add_notes_assigned_client',

        'points_sale_edit_product_prices',
        'points_sale_add_discount',
        'points_sale_open_sessions_all',
        'points_sale_open_sessions_own',
        'points_sale_close_sessions_all',
        'points_sale_close_sessions_own',
        'points_sale_view_own_sessions',
        'points_sale_confirm_close_sessions_all',
        'points_sale_confirm_close_sessions_own',

        // Products permissions
        // شاشة المستودعات — الاسم مستعمل في قائمة المخزون وفي شاشة الأدوار
        // منذ البداية، لكنه لم يكن مسجَّلاً هنا فلم يُزرع قطّ، وكان فحصه
        // يرمي PermissionDoesNotExist فيسقط «إدارة المخزون» بخطأ 500
        'sending_invoices_to_the_tax_authority',

        // Client follow-up permissions
        'client_follow_up_add_notes_attachments_appointments_all',
        'client_follow_up_add_notes_attachments_appointments_own',
        'client_follow_up_add_notes_from_any_location', // إضافة ملاحظة من أي موقع (بدون التحقق من موقع العميل)
        'client_follow_up_edit_delete_notes_attachments_appointments_all',
        'client_follow_up_edit_delete_notes_attachments_appointments_own',
        'client_follow_up_view_notes_attachments_appointments_all',
        'client_follow_up_view_notes_attachments_appointments_assigned',
        'client_follow_up_view_notes_attachments_appointments_own',
        'client_follow_up_assign_clients_to_employees',

        // Clients permissions
        'clients_add_client',
        'clients_view_all_clients',
        'clients_view_own_clients',
        'clients_view_by_employee_groups', // عرض العملاء حسب مجموعات الموظف
        'clients_view_by_daily_itinerary', // عرض العملاء حسب خط السير اليومي
        'clients_edit_delete_all_clients',
        'clients_edit_delete_own_clients',
        'clients_view_all_activity_logs',
        'clients_view_own_activity_log',
        'clients_edit_client_settings',

        // Points credits permissions

        // Membership permissions

        // Customer attendance permissions
        'customer_attendance_display',
        'customer_attendance_manage',

        // Employees permissions

        // Staff attendance permissions
        'staff_visit_justification_exempt', // الاعفاء من تقديم تبرير الزيارات غير المكتملة

        // Orders permissions
        'orders_management',
        'orders_setting_management',

        // Inventory permissions

        // Purchase cycle permissions

        // Supply orders permissions

        // Installments permissions
        // كان القسم محروساً بـ`role:manager` مكتوبةً في ملفّ المسارات —
        // دورٌ واحد مثبَّت، خارج نظام الصلاحيات الذي يعمل به بقيّة النظام.
        // فلا محاسبٌ ولا مندوبُ تحصيلٍ يفتح الأقساط مهما أُسنِد له، ولا
        // شاشةَ صلاحياتٍ تعرض القسم أصلاً.
        //
        // `installments_collect` مفصولةٌ عن `installments_edit` عمداً: من
        // يسجّل تحصيل قسطٍ ليس بالضرورة من يعدّل شروط الاتفاقية.

        // Track time permissions
        'rental_unit_view_booking_orders',
        'rental_unit_manage_booking_orders',
        'rental_unit_manage_rental_settings',

        // GADR permissions

        // Finance permissions

        // Check cycle permissions

        // Settings permissions
        'settings_edit_general_settings',
        'settings_edit_tax_settings',
        'settings_view_own_reports',
        'settings_general_view',
        'settings_customer_categories',
        'settings_customer_permissions',
        'settings_customer_followup_status',
        'settings_groups_management',
        'settings_statistics_configuration',
        'settings_groups_management_add',
        'settings_groups_management_edit',
        'settings_groups_management_delete',

        // ⚙️ المهام المجدولة (Cron Jobs) — تشغيل أوامر على الخادم، فلا
        // تُمنح مع صلاحية الإعدادات العامة بل تُطلب صراحةً
        'settings.cron.view',
        'settings.cron.create',
        'settings.cron.update',
        'settings.cron.delete',
        'settings.cron.run',
        'settings.cron.logs',

        // 💾 النسخ الاحتياطي — التنزيل يعني تسريب قاعدة البيانات كاملة،
        // والاسترجاع يعني الكتابة فوقها، فلكلٍّ صلاحيته المستقلّة
        'settings.backup.view',
        'settings.backup.create',
        'settings.backup.download',
        'settings.backup.delete',
        'settings.backup.restore',
        'settings.backup.settings',

        // Languages management permissions (إدارة لغات النظام)
        'settings_languages_view',
        'settings_languages_create',
        'settings_languages_edit',
        'settings_languages_delete',
        'settings_languages_activate',
        'settings_languages_set_default',

        // Online store permissions
        'online_store_content_management',

        // Employee staff permissions
        'employee_orders_management',

        // Client reports and itinerary permissions
        'client_reports_view',
        'doubtful_debts_view',
        'clients_itinerary_view',
        'clients_visits_track',
        'clients_itinerary_add',
        'clients_itinerary_filter',
        'clients_itinerary_export',
        'clients_itinerary_edit',

        // Insurance agents permissions

        // Employee visit justifications

        // Client management permissions
        'clients_edit_data',
        'clients_manage_opening_balance',
        'clients_view_statement',
        'clients_assign_employees',
        'clients_manage_debts',
        'clients_delete',
        'clients_change_status',

        // Additional client actions/exemptions
        'clients_export',
        'clients_manage_credit_limit',
        'clients_manage_quantity_limit',
        'clients_add_employee_note',
        'clients_hide_from_map',
        'clients_exempt_visit_location',
        'clients_exempt_exchange_transaction',
        'clients_product_exchange',
        'clients_create_invoice',
        'clients_create_payment',
        'clients_create_credit_notice',
        'clients_bulk_pay_invoices',

        // Periodic invoices permissions

        // Sales reports and settings
        'sales.reports.view',

        // POS permissions
        'pos_view_reports',
        'pos_manage_settings',
    ];
}
