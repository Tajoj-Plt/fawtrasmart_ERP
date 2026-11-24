<?php

namespace App\Services\Backup;

use Illuminate\Support\Str;

/**
 * أسماء عربية لجداول القاعدة وأعمدتها.
 *
 * شاشة التصدير كانت تقتصر على واحد وثلاثين كياناً مكتوبة بأسمائها العربية
 * يدوياً في `ExportCatalog`، والقاعدة فيها ثلاثمئة وثلاثون جدولاً. فمن أراد
 * تصدير «طلبات الإجازة» أو «قيود اليومية» لم يجد لهما أثراً في الشاشة.
 *
 * الحلّ ليس عرض الأسماء الإنجليزية — فذلك يُسقط الغرض — بل ترجمتها. وهذه
 * الترجمة على ثلاث درجات، تُجرَّب بالترتيب:
 *
 * 1. **`TABLES`**: اسم مكتوب بيدٍ لكل جدول. أدقّ ما يمكن، وهو الأصل هنا.
 * 2. **قواعد اللواحق للأعمدة**: `*_id` معرّف، و`*_at` تاريخ، و`is_*` سؤال.
 * 3. **الترجمة كلمةً كلمة** من `WORDS` لما لم تغطّه الدرجتان.
 *
 * وما عجز عن كلّ ذلك يُعاد اسمه كما هو — لأن إسقاط عمود لا نعرف ترجمته
 * يعني تصديراً ناقصاً بصمت، وهو أسوأ من عمودٍ باسم إنجليزي.
 */
class ArabicSchemaTranslator
{
    /**
     * الجداول: الاسم التقني ← [الاسم العربي، المجموعة].
     *
     * المجموعة مفتاح لاتيني يُترجَم في `GROUPS`.
     */
    public const TABLES = [
        // ==================== العملاء ====================
        'clients' => ['العملاء', 'clients'],
        'client_addresses' => ['عناوين العملاء', 'clients'],
        'client_devices' => ['أجهزة العملاء', 'clients'],
        'client_relations' => ['صلات العملاء', 'clients'],
        'client_permissions' => ['صلاحيات العملاء', 'clients'],
        'client_payment_cards' => ['بطاقات دفع العملاء', 'clients'],
        'client_status_requests' => ['طلبات تغيير حالة العملاء', 'clients'],
        'client_employee' => ['ربط العملاء بالموظفين', 'clients'],
        'client_employee_notes' => ['ملاحظات الموظفين على العملاء', 'clients'],
        'categories_clients' => ['تصنيفات العملاء', 'clients'],
        'type_client' => ['أنواع العملاء', 'clients'],
        'hidden_clients' => ['العملاء المخفيّون', 'clients'],
        'contacts' => ['جهات الاتصال', 'clients'],
        'credit_limit' => ['حدود الائتمان', 'clients'],
        'doubtful_debts' => ['الديون المشكوك في تحصيلها', 'clients'],
        'note_classifications' => ['تصنيفات الملاحظات', 'clients'],
        'comments' => ['التعليقات', 'clients'],
        'referrals' => ['الإحالات', 'clients'],
        'referral_settings' => ['إعدادات الإحالة', 'clients'],
        'suppliers' => ['الموردون', 'purchases'],

        // ==================== المبيعات ====================
        'invoices' => ['فواتير المبيعات', 'sales'],
        'invoice_items' => ['بنود فواتير المبيعات', 'sales'],
        'invoice_transfers' => ['تحويلات الفواتير', 'sales'],
        'quotes' => ['عروض الأسعار', 'sales'],
        'quote_items' => ['بنود عروض الأسعار', 'sales'],
        'periodic_invoices' => ['الفواتير الدورية', 'sales'],
        'periodic_invoice_instances' => ['إصدارات الفواتير الدورية', 'sales'],
        'installments' => ['الأقساط', 'sales'],
        'installments_details' => ['تفاصيل الأقساط', 'sales'],
        'installment_payments' => ['دفعات الأقساط', 'sales'],
        'offers' => ['العروض', 'sales'],
        'gift_offers' => ['العروض الإهدائية', 'sales'],
        'client_gift_offer' => ['العروض الإهدائية للعملاء', 'sales'],
        'employee_gift_offer' => ['العروض الإهدائية للموظفين', 'sales'],
        'excluded_client_gift_offers' => ['العملاء المستثنون من العروض', 'sales'],
        'price_lists' => ['قوائم الأسعار', 'sales'],
        'price_list_items' => ['بنود قوائم الأسعار', 'sales'],
        'pricing_rules' => ['قواعد التسعير', 'sales'],
        'seasonal_prices' => ['الأسعار الموسمية', 'sales'],
        'order_sources' => ['مصادر الطلبات', 'sales'],
        'settings_order_sources' => ['إعدادات مصادر الطلبات', 'settings'],
        'settings _ordersources' => ['إعدادات مصادر الطلبات (قديم)', 'settings'],
        'order_manual_statuses' => ['حالات الطلب اليدوية', 'sales'],
        'taxs_invoice' => ['ضرائب الفواتير', 'sales'],
        'taxes' => ['الضرائب', 'accounting'],
        'tax_sittings' => ['إعدادات الضرائب', 'settings'],

        // ==================== المشتريات ====================
        'purchase_orders' => ['أوامر الشراء', 'purchases'],
        'purchase_invoices' => ['فواتير المشتريات', 'purchases'],
        'purchase_invoice_settings' => ['إعدادات فواتير المشتريات', 'settings'],
        'sales_invoice_settings' => ['إعدادات فواتير المبيعات', 'settings'],
        'electronic_invoice_settings' => ['إعدادات الفاتورة الإلكترونية', 'settings'],
        'purchase_quotations' => ['عروض أسعار المشتريات', 'purchases'],
        'purchase_quotations_view' => ['عرض عروض أسعار المشتريات', 'purchases'],
        'purchase_quotation_supplier' => ['موردو عروض أسعار المشتريات', 'purchases'],
        'purchase_requested' => ['طلبات الشراء', 'purchases'],
        'purchase_receivings' => ['استلامات المشتريات', 'purchases'],
        'product_exchanges' => ['مرتجعات وتبديل الأصناف', 'purchases'],
        'product_exchange_items' => ['بنود المرتجعات والتبديل', 'purchases'],
        'exchange_reasons' => ['أسباب التبديل', 'purchases'],

        // ==================== المقبوضات والمدفوعات ====================
        'receipts' => ['سندات القبض', 'payments'],
        'receipt_vouchers' => ['سندات القبض', 'payments'],
        'receipt_categories' => ['تصنيفات سندات القبض', 'payments'],
        'payment_vouchers' => ['سندات الصرف', 'payments'],
        'payment_voucher_details' => ['تفاصيل سندات الصرف', 'payments'],
        'payments_process' => ['عمليات الدفع', 'payments'],
        'payment_methods' => ['طرق الدفع', 'payments'],
        'collections_targets' => ['مستهدفات التحصيل', 'targets'],
        'collections_target_achievements' => ['إنجاز مستهدفات التحصيل', 'targets'],
        'collections_target_settlements' => ['تسويات مستهدفات التحصيل', 'targets'],
        'collections_target_commission_tiers' => ['شرائح عمولة التحصيل', 'targets'],
        'received_cheques' => ['الشيكات المستلَمة', 'payments'],
        'payable_cheques' => ['الشيكات المستحقّة', 'payments'],
        'cheque_books' => ['دفاتر الشيكات', 'payments'],
        'treasuries' => ['الخزائن', 'payments'],
        'treasury_employees' => ['موظفو الخزائن', 'payments'],
        'bank_accounts' => ['الحسابات البنكية', 'payments'],
        'transactions' => ['الحركات المالية', 'accounting'],
        'expenses' => ['المصروفات', 'accounting'],
        'expenses_categories' => ['تصنيفات المصروفات', 'accounting'],
        'income' => ['الإيرادات', 'accounting'],

        // ==================== المحاسبة ====================
        'accounts' => ['الحسابات', 'accounting'],
        'accounts_backup' => ['نسخة الحسابات', 'accounting'],
        'account_settings' => ['إعدادات الحسابات', 'settings'],
        'chart_of_accounts' => ['شجرة الحسابات', 'accounting'],
        'journal_entries' => ['قيود اليومية', 'accounting'],
        'journal_entry_details' => ['تفاصيل قيود اليومية', 'accounting'],
        'journal_entry_invoices' => ['فواتير قيود اليومية', 'accounting'],
        'cost_centers' => ['مراكز التكلفة', 'accounting'],
        'indirect_costs' => ['التكاليف غير المباشرة', 'accounting'],
        'indirect_cost_items' => ['بنود التكاليف غير المباشرة', 'accounting'],
        'asset_dep' => ['إهلاك الأصول', 'accounting'],
        'asset_depreciations' => ['إهلاكات الأصول', 'accounting'],
        'daily_closings' => ['الإقفالات اليومية', 'accounting'],
        'daily_closing_stock_details' => ['تفاصيل مخزون الإقفال اليومي', 'accounting'],
        'system_currencies' => ['عملات النظام', 'settings'],

        // ==================== المخزون والأصناف ====================
        'products' => ['الأصناف', 'stock'],
        'product_details' => ['تفاصيل الأصناف', 'stock'],
        'product_groups' => ['مجموعات الأصناف', 'stock'],
        'product_units' => ['وحدات الأصناف', 'stock'],
        'product_barcode_settings' => ['إعدادات باركود الأصناف', 'settings'],
        'compiled_products' => ['الأصناف المركّبة', 'stock'],
        'categories' => ['التصنيفات', 'stock'],
        'category_unit' => ['وحدات التصنيفات', 'stock'],
        'brands' => ['العلامات التجارية', 'stock'],
        'units' => ['الوحدات', 'stock'],
        'sub_units' => ['الوحدات الفرعية', 'stock'],
        'unit_types' => ['أنواع الوحدات', 'stock'],
        'store_houses' => ['المستودعات', 'stock'],
        'default_warehouses' => ['المستودعات الافتراضية', 'stock'],
        'warehouse_permits' => ['أذون المستودعات', 'stock'],
        'warehouse_permits_products' => ['أصناف أذون المستودعات', 'stock'],
        'inventory_items' => ['أصناف الجرد', 'stock'],
        'inventory_adjustments' => ['تسويات الجرد', 'stock'],
        'inventory_adjustment_logs' => ['سجلّ تسويات الجرد', 'stock'],
        'locations' => ['المواقع', 'stock'],
        'directions' => ['الاتجاهات', 'stock'],

        // ==================== التصنيع ====================
        'manufactur_orders' => ['أوامر التصنيع', 'manufacturing'],
        'manufactur_orders_items' => ['بنود أوامر التصنيع', 'manufacturing'],
        'manufacturing_order_statuses' => ['حالات أوامر التصنيع', 'manufacturing'],
        'manual_manufacturing_order_statuses' => ['حالات أوامر التصنيع اليدوية', 'manufacturing'],
        'manufacturing_general_settings' => ['إعدادات التصنيع العامة', 'settings'],
        'production_materials' => ['مواد الإنتاج', 'manufacturing'],
        'production_materials_items' => ['بنود مواد الإنتاج', 'manufacturing'],
        'production_paths' => ['مسارات الإنتاج', 'manufacturing'],
        'production_stages' => ['مراحل الإنتاج', 'manufacturing'],
        'work_stations' => ['محطات العمل', 'manufacturing'],
        'work_stations_costs' => ['تكاليف محطات العمل', 'manufacturing'],
        'machines' => ['الآلات', 'manufacturing'],
        'procedures' => ['الإجراءات', 'manufacturing'],

        // ==================== الموظفون ====================
        'employees' => ['الموظفون', 'hr'],
        'employee_attachments' => ['مرفقات الموظفين', 'hr'],
        'employee_documents' => ['وثائق الموظفين', 'hr'],
        'employee_branch' => ['فروع الموظفين', 'hr'],
        'employee_group' => ['مجموعات الموظفين', 'hr'],
        'employee_tracking' => ['تتبّع الموظفين', 'hr'],
        'employee_client_visits' => ['زيارات الموظفين للعملاء', 'visits'],
        'departments' => ['الأقسام', 'hr'],
        'department_employee' => ['موظفو الأقسام', 'hr'],
        'job_roles' => ['الأدوار الوظيفية', 'hr'],
        'jop_titles' => ['المسمّيات الوظيفية', 'hr'],
        'types_jobs' => ['أنواع الوظائف', 'hr'],
        'functional_levels' => ['المستويات الوظيفية', 'hr'],
        'contracts' => ['العقود', 'hr'],
        'contract_templates' => ['قوالب العقود', 'hr'],
        'signatures' => ['التواقيع', 'hr'],
        'trusted_devices' => ['الأجهزة الموثوقة', 'security'],

        // ==================== الحضور والدوام ====================
        'attendance_records' => ['سجلات الحضور', 'attendance'],
        'attendance_correction_requests' => ['طلبات تصحيح الحضور', 'attendance'],
        'attendance_days' => ['أيام الحضور', 'attendance'],
        'attendance_sheets' => ['كشوف الحضور', 'attendance'],
        'attendance_sheets_employees' => ['موظفو كشوف الحضور', 'attendance'],
        'attendance_rules' => ['قواعد الحضور', 'attendance'],
        'attendance_settings' => ['إعدادات الحضور', 'settings'],
        'attendance_settings_employees' => ['إعدادات حضور الموظفين', 'settings'],
        'attendance_determinants' => ['محدّدات الحضور', 'attendance'],
        'attendance_photos' => ['صور الحضور', 'attendance'],
        'attendance_photo_types' => ['أنواع صور الحضور', 'attendance'],
        'attendance_session_records' => ['سجلات جلسات الحضور', 'attendance'],
        'employee_attendance_configs' => ['ضبط حضور الموظفين', 'settings'],
        'shifts' => ['الورديات', 'attendance'],
        'shift_days' => ['أيام الورديات', 'attendance'],
        'shifts_pos' => ['ورديات نقاط البيع', 'pos'],
        'custom_shifts' => ['الورديات المخصّصة', 'attendance'],
        'custom_shifts_employees' => ['موظفو الورديات المخصّصة', 'attendance'],
        'holidays' => ['العطل', 'attendance'],
        'holiday_lists' => ['قوائم العطل', 'attendance'],
        'holy_day_list_customizes' => ['تخصيص قوائم العطل', 'attendance'],
        'holy_day_list_customize_employees' => ['موظفو تخصيص قوائم العطل', 'attendance'],
        'leave_requests' => ['طلبات الإجازة', 'attendance'],
        'leave_types' => ['أنواع الإجازات', 'attendance'],
        'leave_type_leave_types' => ['ربط أنواع الإجازات', 'attendance'],
        'leave_policies' => ['سياسات الإجازات', 'attendance'],
        'leave_policy_customizes' => ['تخصيص سياسات الإجازات', 'attendance'],
        'leave_policy_customize_employees' => ['موظفو تخصيص سياسات الإجازات', 'attendance'],
        'leave_permissions' => ['أذونات الاستئذان', 'attendance'],
        'employee_leave_balances' => ['أرصدة إجازات الموظفين', 'attendance'],

        // ==================== الرواتب ====================
        'payrolls' => ['مسيّرات الرواتب', 'payroll'],
        'payroll_runs' => ['دورات صرف الرواتب', 'payroll'],
        'payroll_run_items' => ['بنود دورات صرف الرواتب', 'payroll'],
        'payroll_employee' => ['موظفو مسيّرات الرواتب', 'payroll'],
        'payroll_settings' => ['إعدادات الرواتب', 'settings'],
        'salary_items' => ['بنود الراتب', 'payroll'],
        'salary_slips' => ['قسائم الرواتب', 'payroll'],
        'salary_template' => ['قوالب الرواتب', 'payroll'],
        'salary_advances' => ['السلف', 'payroll'],
        'salary_advance_requests' => ['طلبات السلف', 'payroll'],
        'deductions' => ['الخصومات', 'payroll'],
        'deduction_histories' => ['سجلّ الخصومات', 'payroll'],
        'end_of_service_benefits' => ['مستحقات نهاية الخدمة', 'payroll'],

        // ==================== المستهدفات ====================
        'targets' => ['المستهدفات', 'targets'],
        'target_settings' => ['إعدادات المستهدفات', 'settings'],
        'sales_targets' => ['مستهدفات المبيعات', 'targets'],
        'sales_target_achievements' => ['إنجاز مستهدفات المبيعات', 'targets'],
        'sales_target_settlements' => ['تسويات مستهدفات المبيعات', 'targets'],
        'sales_target_commission_tiers' => ['شرائح عمولة المبيعات', 'targets'],
        'employee_targets' => ['مستهدفات الموظفين', 'targets'],
        'customer_targets' => ['مستهدفات العملاء', 'targets'],
        'achievements' => ['الإنجازات', 'targets'],
        'achievements_summary' => ['ملخّص الإنجازات', 'targets'],
        'user_achievements' => ['إنجازات المستخدمين', 'targets'],
        'challenges' => ['التحدّيات', 'targets'],
        'challenge_teams' => ['فرق التحدّيات', 'targets'],
        'challenge_participants' => ['مشاركو التحدّيات', 'targets'],
        'challenge_transactions' => ['حركات التحدّيات', 'targets'],

        // ==================== النقاط والولاء ====================
        'points_transactions' => ['حركات النقاط', 'loyalty'],
        'points_batches' => ['دفعات النقاط', 'loyalty'],
        'points_tiers' => ['فئات النقاط', 'loyalty'],
        'points_daily_summary' => ['ملخّص النقاط اليومي', 'loyalty'],
        'points_settings' => ['إعدادات النقاط', 'settings'],
        'points_general_settings' => ['إعدادات النقاط العامة', 'settings'],
        'points_expiry_settings' => ['إعدادات انتهاء النقاط', 'settings'],
        'points_notification_templates' => ['قوالب إشعارات النقاط', 'settings'],
        'points_notifications_log' => ['سجلّ إشعارات النقاط', 'system'],
        'tier_upgrades_log' => ['سجلّ ترقية الفئات', 'loyalty'],
        'rewards' => ['المكافآت', 'loyalty'],
        'reward_redemptions' => ['استبدال المكافآت', 'loyalty'],

        // ==================== العضويات والاشتراكات ====================
        'system_subscriptions' => ['اشتراكات النظام', 'memberships'],
        'system_subscription_invoices' => ['فواتير اشتراكات النظام', 'memberships'],

        // ==================== نقاط البيع ====================
        'pos_sessions' => ['جلسات نقاط البيع', 'pos'],
        'pos_session_details' => ['تفاصيل جلسات نقاط البيع', 'pos'],
        'pos_transactions' => ['حركات نقاط البيع', 'pos'],
        'pos_transaction_items' => ['بنود حركات نقاط البيع', 'pos'],
        'pos_general_settings' => ['إعدادات نقاط البيع', 'settings'],
        'cashier_devices' => ['أجهزة الكاشير', 'pos'],

        // ==================== المتجر الإلكتروني ====================
        'online_orders' => ['طلبات المتجر', 'store'],
        'online_order_items' => ['بنود طلبات المتجر', 'store'],
        'online_order_extra_fees' => ['رسوم طلبات المتجر', 'store'],
        'online_order_status_history' => ['سجلّ حالات طلبات المتجر', 'store'],
        'online_store_settings' => ['إعدادات المتجر', 'settings'],
        'carts' => ['سلات التسوّق', 'store'],
        'cart_items' => ['بنود سلات التسوّق', 'store'],
        'wishlists' => ['قوائم الأمنيات', 'store'],
        'recently_viewed_products' => ['الأصناف المشاهَدة حديثاً', 'store'],
        'product_reviews' => ['تقييمات الأصناف', 'store'],
        'product_review_helpfuls' => ['إفادة تقييمات الأصناف', 'store'],
        'product_questions' => ['أسئلة الأصناف', 'store'],
        'store_pages' => ['صفحات المتجر', 'store'],
        'store_banners' => ['لافتات المتجر', 'store'],
        'store_coupons' => ['كوبونات المتجر', 'store'],
        'store_coupon_usages' => ['استخدام كوبونات المتجر', 'store'],
        'shipping_methods' => ['طرق الشحن', 'store'],
        'shipping_options' => ['خيارات الشحن', 'store'],
        'shipping_zones' => ['مناطق الشحن', 'store'],

        // ==================== الحجوزات والمواعيد ====================
        'reservations' => ['الحجوزات', 'rentals'],
        'bookings' => ['الحجوزات', 'rentals'],

        // ==================== إدارة العقارات ====================
        're_owners' => ['ملّاك العقارات', 'realestate'],
        're_owner_entries' => ['حركات حسابات الملّاك', 'realestate'],
        're_complexes' => ['المجمعات العقارية', 'realestate'],
        're_buildings' => ['المباني', 'realestate'],
        're_floors' => ['طوابق المباني', 'realestate'],
        're_units' => ['الوحدات العقارية', 'realestate'],
        're_amenities' => ['مرافق الوحدات', 'realestate'],
        're_unit_amenity' => ['ربط الوحدات بالمرافق', 'realestate'],
        're_listings' => ['العروض العقارية', 'realestate'],
        're_viewings' => ['معاينات العروض العقارية', 'realestate'],
        're_tenant_profiles' => ['ملفّات المستأجرين', 'realestate'],
        're_customer_relationships' => ['علاقات العملاء العقارية', 'realestate'],
        're_contracts' => ['عقود الإيجار العقارية', 'realestate'],
        're_contract_terms' => ['شروط عقود الإيجار', 'realestate'],
        're_contract_amendments' => ['تعديلات عقود الإيجار', 'realestate'],
        're_contract_renewals' => ['تجديدات عقود الإيجار', 'realestate'],
        're_term_templates' => ['قوالب شروط العقود العقارية', 'realestate'],
        're_payment_schedules' => ['أقساط عقود الإيجار', 'realestate'],
        're_payments' => ['دفعات الإيجار', 'realestate'],
        're_payment_allocations' => ['توزيع دفعات الإيجار على الأقساط', 'realestate'],
        're_deposit_settlements' => ['تسويات تأمين المستأجرين', 'realestate'],
        're_maintenance_requests' => ['طلبات الصيانة العقارية', 'realestate'],
        're_maintenance_costs' => ['تكاليف الصيانة العقارية', 'realestate'],
        're_tasks' => ['مهام ومتابعات العقارات', 'realestate'],
        're_saved_reports' => ['تقارير العقارات المحفوظة', 'realestate'],
        're_settings' => ['إعدادات العقارات', 'realestate'],

        'appointments' => ['المواعيد', 'visits'],
        'appointment_notes' => ['ملاحظات المواعيد', 'visits'],
        'visits' => ['الزيارات', 'visits'],
        'visit_justifications' => ['مبرّرات الزيارات', 'visits'],
        'visit_follow_up_tasks' => ['مهام متابعة الزيارات', 'visits'],
        'visit_competitor_observations' => ['ملاحظات المنافسين في الزيارات', 'visits'],

        // ==================== المهام والمشاريع ====================
        'tasks' => ['المهام', 'tasks'],
        'task_user' => ['مستخدمو المهام', 'tasks'],
        'task_settings' => ['إعدادات المهام', 'settings'],
        'projects' => ['المشاريع', 'tasks'],
        'project_user' => ['مستخدمو المشاريع', 'tasks'],
        'sprints' => ['دورات العمل', 'tasks'],
        'workspaces' => ['مساحات العمل', 'tasks'],
        'statuses' => ['الحالات', 'tasks'],

        // ==================== الفروع والإعدادات ====================
        'branches' => ['الفروع', 'settings'],
        'branch_settings' => ['إعدادات الفروع', 'settings'],
        'branch_setting_branch' => ['ربط إعدادات الفروع', 'settings'],
        'general_settings' => ['الإعدادات العامة', 'settings'],
        'general_client_settings' => ['الإعدادات العامة للعملاء', 'settings'],
        'application_settings' => ['إعدادات التطبيق', 'settings'],
        'business_data' => ['بيانات المنشأة', 'settings'],
        'serial_settings' => ['إعدادات الترقيم', 'settings'],
        'color_sittings' => ['إعدادات الألوان', 'settings'],
        'document_templates' => ['قوالب المستندات', 'settings'],
        'templates' => ['القوالب', 'settings'],
        'template_units' => ['وحدات القوالب', 'settings'],
        'email_templates' => ['قوالب البريد', 'settings'],
        'smtp_settings' => ['إعدادات البريد الصادر', 'settings'],
        'sms_settings' => ['إعدادات الرسائل النصية', 'settings'],
        'sms_templates' => ['قوالب الرسائل النصية', 'settings'],
        'system_languages' => ['لغات النظام', 'settings'],
        'countries' => ['الدول', 'settings'],
        'cities' => ['المدن', 'settings'],
        'neighborhoods' => ['الأحياء', 'settings'],
        'notification_settings' => ['إعدادات الإشعارات', 'settings'],
        'backup_settings' => ['إعدادات النسخ الاحتياطي', 'settings'],

        // ==================== النظام والسجلات ====================
        'users' => ['المستخدمون', 'security'],
        'roles' => ['الأدوار', 'security'],
        'permissions' => ['الصلاحيات', 'security'],
        'permission_sources' => ['مصادر الصلاحيات', 'security'],
        'role_has_permissions' => ['صلاحيات الأدوار', 'security'],
        'model_has_roles' => ['أدوار السجلات', 'security'],
        'model_has_permissions' => ['صلاحيات السجلات', 'security'],
        'api_keys' => ['مفاتيح الواجهة البرمجية', 'security'],
        'sessions' => ['الجلسات القائمة', 'security'],
        'session_logs' => ['سجلّ الجلسات', 'security'],
        'password_reset_tokens' => ['رموز استعادة كلمة المرور', 'security'],
        'personal_access_tokens' => ['رموز الوصول الشخصية', 'security'],

        'logs' => ['سجلّ العمليات', 'system'],
        'error_logs' => ['سجلّ الأخطاء', 'system'],
        'event_logs' => ['سجلّ الأحداث', 'system'],
        'email_logs' => ['سجلّ البريد', 'system'],
        'sms_logs' => ['سجلّ الرسائل النصية', 'system'],
        'notifications' => ['الإشعارات', 'system'],
        'credit_notifications' => ['إشعارات الائتمان', 'system'],
        'group_merge_logs' => ['سجلّ دمج المجموعات', 'system'],
        'backups' => ['النسخ الاحتياطية', 'system'],
        'backup_restores' => ['عمليات الاسترجاع', 'system'],
        'cron_jobs' => ['المهام المجدولة', 'system'],
        'cron_job_executions' => ['تنفيذ المهام المجدولة', 'system'],
        'jobs' => ['طابور المهام', 'system'],
        'job_batches' => ['دفعات المهام', 'system'],
        'failed_jobs' => ['المهام الفاشلة', 'system'],
        'migrations' => ['ترحيلات القاعدة', 'system'],
        'cache' => ['الذاكرة المؤقتة', 'system'],
        'cache_locks' => ['أقفال الذاكرة المؤقتة', 'system'],
        'telescope_entries' => ['سجلات المراقبة', 'system'],
        'telescope_entries_tags' => ['وسوم سجلات المراقبة', 'system'],
        'telescope_monitoring' => ['مراقبة النظام', 'system'],
    ];

    /**
     * مجموعات العرض.
     */
    public const GROUPS = [
        'clients' => 'العملاء',
        'sales' => 'المبيعات والفواتير',
        'purchases' => 'المشتريات والموردون',
        'payments' => 'المقبوضات والمدفوعات',
        'accounting' => 'المحاسبة والقيود',
        'stock' => 'المخزون والأصناف',
        'manufacturing' => 'التصنيع والإنتاج',
        'hr' => 'الموظفون',
        'attendance' => 'الحضور والإجازات',
        'payroll' => 'الرواتب والمستحقات',
        'targets' => 'المستهدفات والعمولات',
        'loyalty' => 'النقاط والولاء',
        'memberships' => 'العضويات والاشتراكات',
        'pos' => 'نقاط البيع',
        'store' => 'المتجر الإلكتروني',
        'rentals' => 'الحجوزات والإيجارات',
        'realestate' => 'إدارة العقارات',
        'visits' => 'الزيارات والمواعيد',
        'tasks' => 'المهام والمشاريع',
        'settings' => 'الإعدادات',
        'security' => 'المستخدمون والصلاحيات',
        'system' => 'النظام والسجلات',
        'other' => 'جداول أخرى',
    ];

    /**
     * جداول لا تُدرَج ضمن «تحديد الكل».
     *
     * ليست ممنوعة — للمالك أن يصدّرها بتحديدها بنفسه — لكنها لا تدخل في
     * تصدير شامل بنقرة واحدة: رموز الوصول وكلمات المرور والجلسات مفاتيحُ
     * دخول لا بيانات عمل، وملفٌّ يجمع بيانات المبيعات ورموز الدخول معاً
     * يصير خطراً بمجرّد أن يُرسَل بالبريد.
     */
    public const SENSITIVE = [
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'api_keys',
        'trusted_devices',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    /**
     * كلمات تُترجَم داخل أسماء الأعمدة والجداول غير المعروفة.
     *
     * @var array<string, string>
     */
    public const WORDS = [
        'client' => 'العميل', 'clients' => 'العملاء', 'customer' => 'العميل',
        'employee' => 'الموظف', 'employees' => 'الموظفون', 'user' => 'المستخدم',
        'users' => 'المستخدمون', 'supplier' => 'المورّد', 'branch' => 'الفرع',
        'invoice' => 'الفاتورة', 'order' => 'الطلب', 'quote' => 'عرض السعر',
        'product' => 'الصنف', 'products' => 'الأصناف', 'item' => 'البند',
        'items' => 'البنود', 'category' => 'التصنيف', 'unit' => 'الوحدة',
        'warehouse' => 'المستودع', 'stock' => 'المخزون', 'account' => 'الحساب',
        'payment' => 'الدفعة', 'receipt' => 'السند', 'voucher' => 'السند',
        'amount' => 'المبلغ', 'total' => 'الإجمالي', 'price' => 'السعر',
        'cost' => 'التكلفة', 'tax' => 'الضريبة', 'discount' => 'الخصم',
        'quantity' => 'الكمية', 'balance' => 'الرصيد', 'currency' => 'العملة',
        'date' => 'التاريخ', 'time' => 'الوقت', 'status' => 'الحالة',
        'type' => 'النوع', 'name' => 'الاسم', 'code' => 'الرمز',
        'number' => 'الرقم', 'note' => 'الملاحظة', 'notes' => 'الملاحظات',
        'description' => 'الوصف', 'address' => 'العنوان', 'phone' => 'الهاتف',
        'mobile' => 'الجوّال', 'email' => 'البريد', 'city' => 'المدينة',
        'country' => 'الدولة', 'region' => 'المنطقة', 'setting' => 'الإعداد',
        'settings' => 'الإعدادات', 'log' => 'السجل', 'logs' => 'السجلات',
        'report' => 'التقرير', 'target' => 'المستهدف', 'commission' => 'العمولة',
        'salary' => 'الراتب', 'payroll' => 'الرواتب', 'leave' => 'الإجازة',
        'shift' => 'الوردية', 'attendance' => 'الحضور', 'holiday' => 'العطلة',
        'department' => 'القسم', 'role' => 'الدور', 'permission' => 'الصلاحية',
        'point' => 'النقطة', 'points' => 'النقاط', 'reward' => 'المكافأة',
        'tier' => 'الفئة', 'membership' => 'العضوية', 'subscription' => 'الاشتراك',
        'visit' => 'الزيارة', 'appointment' => 'الموعد', 'task' => 'المهمة',
        'project' => 'المشروع', 'store' => 'المتجر', 'cart' => 'السلة',
        'shipping' => 'الشحن', 'coupon' => 'الكوبون', 'review' => 'التقييم',
        'template' => 'القالب', 'notification' => 'الإشعار', 'message' => 'الرسالة',
        'file' => 'الملف', 'image' => 'الصورة', 'photo' => 'الصورة',
        'detail' => 'التفصيل', 'details' => 'التفاصيل', 'group' => 'المجموعة',
        'session' => 'الجلسة', 'device' => 'الجهاز', 'contract' => 'العقد',
        'deduction' => 'الخصم', 'advance' => 'السلفة', 'expense' => 'المصروف',
        'income' => 'الإيراد', 'transaction' => 'الحركة', 'journal' => 'اليومية',
        'entry' => 'القيد', 'treasury' => 'الخزنة', 'cheque' => 'الشيك',
        'bank' => 'البنك', 'installment' => 'القسط', 'offer' => 'العرض',
        'brand' => 'العلامة التجارية', 'variant' => 'المتغيّر', 'attribute' => 'الخاصية',
        'value' => 'القيمة', 'reason' => 'السبب', 'source' => 'المصدر',
        'method' => 'الطريقة', 'rule' => 'القاعدة', 'policy' => 'السياسة',
        'level' => 'المستوى', 'title' => 'العنوان', 'job' => 'الوظيفة',
        'machine' => 'الآلة', 'production' => 'الإنتاج', 'stage' => 'المرحلة',
        'path' => 'المسار', 'station' => 'المحطة', 'material' => 'المادة',
        'achievement' => 'الإنجاز', 'challenge' => 'التحدّي', 'team' => 'الفريق',
        'participant' => 'المشارك', 'referral' => 'الإحالة', 'gift' => 'الهدية',
        'package' => 'الباقة', 'reservation' => 'الحجز', 'booking' => 'الحجز',
        'location' => 'الموقع', 'zone' => 'المنطقة', 'banner' => 'اللافتة',
        'page' => 'الصفحة', 'comment' => 'التعليق', 'question' => 'السؤال',
        'answer' => 'الإجابة', 'summary' => 'الملخّص', 'history' => 'السجل',
        'batch' => 'الدفعة', 'run' => 'الدورة', 'slip' => 'القسيمة',
        'sheet' => 'الكشف', 'record' => 'السجل', 'records' => 'السجلات',
        'request' => 'الطلب', 'requests' => 'الطلبات', 'approval' => 'الاعتماد',
        'day' => 'اليوم', 'days' => 'الأيام', 'hour' => 'الساعة',
        'month' => 'الشهر', 'year' => 'السنة', 'week' => 'الأسبوع',
        'start' => 'البداية', 'end' => 'النهاية', 'due' => 'الاستحقاق',
        'paid' => 'المدفوع', 'remaining' => 'المتبقي', 'net' => 'الصافي',
        'gross' => 'الإجمالي', 'sub' => 'الفرعي', 'main' => 'الرئيسي',
        'parent' => 'الأصل', 'child' => 'الفرع', 'order_number' => 'رقم الطلب',
        'serial' => 'التسلسل', 'sequence' => 'التسلسل', 'ref' => 'المرجع',
        'reference' => 'المرجع', 'currency_rate' => 'سعر الصرف',
        'opening' => 'الافتتاحي', 'closing' => 'الختامي', 'credit' => 'دائن',
        'debit' => 'مدين', 'profit' => 'الربح', 'loss' => 'الخسارة',
        'min' => 'الأدنى', 'max' => 'الأقصى', 'limit' => 'الحدّ',
        'weight' => 'الوزن', 'size' => 'الحجم', 'color' => 'اللون',
        'active' => 'المفعَّل', 'archived' => 'المؤرشَف', 'draft' => 'المسودّة',
        'approved' => 'المعتمَد', 'rejected' => 'المرفوض', 'pending' => 'المعلَّق',
        'cancelled' => 'الملغى', 'completed' => 'المكتمل', 'delivery' => 'التسليم',
        'return' => 'المرتجع', 'adjustment' => 'التسوية', 'transfer' => 'التحويل',
        'permit' => 'الإذن', 'attachment' => 'المرفق', 'signature' => 'التوقيع',
        'setting' => 'الإعداد', 'config' => 'الضبط', 'option' => 'الخيار',
        'mapping' => 'الربط', 'usage' => 'الاستخدام', 'redemption' => 'الاستبدال',
        'expiry' => 'الانتهاء', 'upgrade' => 'الترقية', 'charge' => 'الشحن',
        'consumption' => 'الاستهلاك', 'depreciation' => 'الإهلاك', 'asset' => 'الأصل',
        'center' => 'المركز', 'closing_stock' => 'مخزون الإقفال',
        'holy' => 'الرسمية', 'custom' => 'المخصّص', 'default' => 'الافتراضي',
        'permission' => 'الصلاحية', 'token' => 'الرمز', 'key' => 'المفتاح',
        'execution' => 'التنفيذ', 'restore' => 'الاسترجاع', 'backup' => 'النسخة',
        'monitoring' => 'المراقبة', 'entry_tags' => 'وسوم القيد',
        'per' => 'لكل', 'of' => 'من', 'and' => 'و', 'to' => 'إلى',
        'rejection' => 'الرفض', 'approval_note' => 'ملاحظة الاعتماد',
        'consecutive' => 'متتالية', 'applicable' => 'يسري', 'after' => 'بعد',
        'before' => 'قبل', 'replace' => 'استبدال', 'weekend' => 'نهاية الأسبوع',
        'weekends' => 'نهايات الأسبوع', 'balance_type' => 'نوع الرصيد',
        'allowed' => 'المسموح', 'required' => 'المطلوب', 'available' => 'المتاح',
        'used' => 'المستخدَم', 'remain' => 'المتبقي', 'expired' => 'المنتهي',
        'valid' => 'الصالح', 'invalid' => 'غير الصالح', 'deleted' => 'المحذوف',
        'created' => 'الإنشاء', 'updated' => 'التعديل', 'sent' => 'المرسَل',
        'received' => 'المستلَم', 'paid_at' => 'تاريخ الدفع', 'due_date' => 'تاريخ الاستحقاق',
        'first' => 'الأول', 'last' => 'الأخير', 'next' => 'التالي', 'previous' => 'السابق',
        'from' => 'من', 'until' => 'حتى', 'during' => 'خلال', 'without' => 'بدون',

        // أكثر الكلمات وروداً فيما بقي بلا ترجمة بعد القواعد
        'expires' => 'الانتهاء', 'expire' => 'الانتهاء', 'started' => 'البدء',
        'ended' => 'الانتهاء', 'delivered' => 'التسليم', 'processed' => 'المعالجة',
        'executed' => 'التنفيذ', 'settled' => 'التسوية', 'cancellation' => 'الإلغاء',
        'metadata' => 'بيانات وصفية', 'payload' => 'المحتوى', 'content' => 'المحتوى',
        'output' => 'المخرجات', 'variables' => 'المتغيّرات', 'duration' => 'المدّة',
        'subtotal' => 'المجموع الفرعي', 'grand' => 'الكلي', 'budget' => 'الميزانية',
        'storehouse' => 'المستودع', 'timezone' => 'المنطقة الزمنية',
        'period' => 'الفترة', 'frequency' => 'التكرار', 'recurrence' => 'التكرار',
        'cycle' => 'الدورة', 'phase' => 'المرحلة', 'action' => 'الإجراء',
        'tag' => 'الوسم', 'tags' => 'الوسوم', 'logo' => 'الشعار',
        'cover' => 'الغلاف', 'language' => 'اللغة', 'featured' => 'المميّز',
        'primary' => 'الرئيسي', 'secondary' => 'الثانوي', 'hidden' => 'المخفي',
        'enabled' => 'المفعَّل', 'browser' => 'المتصفّح', 'os' => 'نظام التشغيل',
        'abilities' => 'الصلاحيات', 'secret' => 'السرّ', 'stamp' => 'الختم',
        'business' => 'المنشأة', 'condition' => 'الشرط', 'operator' => 'المُعامِل',
        'radius' => 'النطاق', 'mime' => 'نوع الملف', 'original' => 'الأصلي',
        'login' => 'الدخول', 'logout' => 'الخروج', 'expected' => 'المتوقّع',
        'check' => 'التسجيل', 'in' => 'الدخول', 'out' => 'الخروج',
        'grace' => 'السماح', 'late' => 'التأخّر', 'join' => 'الالتحاق',
        'notify' => 'التنبيه', 'achievers' => 'المحقّقون', 'mode' => 'الوضع',
        'table' => 'الجدول', 'dark' => 'الداكن', 'factor' => 'العامل',
        'two' => 'ثنائي', 'ms' => 'مللي ثانية', 'delivery_at' => 'تاريخ التسليم',
    ];

    // ==================== الجداول ====================

    /** الاسم العربي لجدول */
    public static function table(string $name): string
    {
        if (isset(self::TABLES[$name])) {
            return self::TABLES[$name][0];
        }

        return self::fromWords($name);
    }

    /** مفتاح مجموعة الجدول */
    public static function groupKey(string $name): string
    {
        return self::TABLES[$name][1] ?? 'other';
    }

    /** الاسم العربي لمجموعة الجدول */
    public static function group(string $name): string
    {
        $key = self::groupKey($name);

        return self::GROUPS[$key] ?? self::GROUPS['other'];
    }

    public static function isSensitive(string $table): bool
    {
        return in_array($table, self::SENSITIVE, true);
    }

    /** الجدول معروف بالاسم لا مترجَم آلياً */
    public static function isKnown(string $table): bool
    {
        return isset(self::TABLES[$table]);
    }

    // ==================== الأعمدة ====================

    /**
     * الاسم العربي لعمود.
     *
     * تُجرَّب اللواحق المعروفة أولاً لأنها أدقّ من ترجمة الكلمات: عمود
     * `client_id` معناه «معرّف العميل» لا «العميل الرقم».
     */
    public static function column(string $name): string
    {
        // الأسماء الشائعة أوّلاً — أدقّ ما لدينا
        if (isset(ExportCatalog::COMMON[$name])) {
            return ExportCatalog::COMMON[$name];
        }

        $known = [
            // الأعمدة الشائعة التي لا تنفع فيها قاعدة اللواحق: «بواسطة»
            // وحدها تعطي «اعتمد بواسطة» وهي ركيكة
            'created_by' => 'أنشأه',
            'updated_by' => 'عدّله',
            'deleted_by' => 'حذفه',
            'approved_by' => 'اعتمده',
            'rejected_by' => 'رفضه',
            'cancelled_by' => 'ألغاه',
            'confirmed_by' => 'أكّده',
            'assigned_to' => 'مُسنَد إلى',
            'assigned_by' => 'أسنده',
            'received_by' => 'استلمه',
            'paid_by' => 'دفعه',
            'sort_order' => 'الترتيب',
            'order' => 'الترتيب',
            'position' => 'الترتيب',
            'parent_id' => 'التابع لـ',
            'days' => 'عدد الأيام',
            'hours' => 'عدد الساعات',
            'minutes' => 'عدد الدقائق',
            'count' => 'العدد',
            'active' => 'مفعَّل',
            'enabled' => 'مفعَّل',
            'visible' => 'ظاهر',
            'default' => 'افتراضي',
            'color' => 'اللون',
            'icon' => 'الأيقونة',
            'url' => 'الرابط',
            'link' => 'الرابط',
            'ip_address' => 'عنوان IP',
            'user_agent' => 'بيانات المتصفّح',

            'id' => 'الرقم',
            'uuid' => 'المعرّف الفريد',
            'slug' => 'المعرّف النصّي',
            'title' => 'العنوان',
            'body' => 'المحتوى',
            'value' => 'القيمة',
            'price' => 'السعر',
            'cost' => 'التكلفة',
            'qty' => 'الكمية',
            'tax' => 'الضريبة',
            'note' => 'ملاحظة',
            'remarks' => 'ملاحظات',
            'address' => 'العنوان',
            'lat' => 'خط العرض',
            'lng' => 'خط الطول',
            'latitude' => 'خط العرض',
            'longitude' => 'خط الطول',
            'deleted_at' => 'تاريخ الحذف',
            'password' => 'كلمة المرور',
            'remember_token' => 'رمز التذكّر',
            'email_verified_at' => 'تاريخ توثيق البريد',
        ];

        if (isset($known[$name])) {
            return $known[$name];
        }

        // ---------- اللواحق والبادئات ----------
        $rules = [
            // البادئات أوّلاً: «أقصى عدد الأيام» أسلم من «الأقصى الأيام»
            '/^max_(.+)$/' => 'أقصى %s',
            '/^min_(.+)$/' => 'أدنى %s',
            '/^total_(.+)$/' => 'إجمالي %s',
            '/^num_(.+)$/' => 'عدد %s',
            '/^number_of_(.+)$/' => 'عدد %s',
            '/^default_(.+)$/' => '%s الافتراضي',
            '/^is_(.+)$/' => 'هل %s',
            '/^has_(.+)$/' => 'هل يملك %s',
            '/^can_(.+)$/' => 'يمكنه %s',
            '/^(.+)_id$/' => 'معرّف %s',
            '/^(.+)_at$/' => 'تاريخ %s',
            '/^(.+)_date$/' => 'تاريخ %s',
            '/^(.+)_time$/' => 'وقت %s',
            '/^(.+)_by$/' => '%s بواسطة',
            '/^(.+)_count$/' => 'عدد %s',
            '/^(.+)_amount$/' => 'مبلغ %s',
            '/^(.+)_total$/' => 'إجمالي %s',
            '/^(.+)_rate$/' => 'نسبة %s',
            '/^(.+)_percentage$/' => 'نسبة %s',
            '/^(.+)_name$/' => 'اسم %s',
            '/^(.+)_type$/' => 'نوع %s',
            '/^(.+)_status$/' => 'حالة %s',
            '/^(.+)_code$/' => 'رمز %s',
            '/^(.+)_number$/' => 'رقم %s',
            '/^(.+)_note$/' => 'ملاحظة %s',
            '/^(.+)_notes$/' => 'ملاحظات %s',
            '/^(.+)_reason$/' => 'سبب %s',
            '/^(.+)_url$/' => 'رابط %s',
            '/^(.+)_path$/' => 'مسار %s',
            '/^(.+)_file$/' => 'ملف %s',
            '/^(.+)_image$/' => 'صورة %s',
            '/^(.+)_price$/' => 'سعر %s',
            '/^(.+)_cost$/' => 'تكلفة %s',
            '/^(.+)_qty$/' => 'كمية %s',
            '/^(.+)_quantity$/' => 'كمية %s',
        ];

        foreach ($rules as $pattern => $format) {
            if (preg_match($pattern, $name, $matches)) {
                $inner = self::fromWords($matches[1]);

                // الكلمة المجهولة تُترك كما هي بدل أن تُلبَس قالباً عربياً
                // يُنتج «تاريخ foo_bar» — وهو أسوأ من `foo_bar_at`
                return $inner === $matches[1] ? $name : sprintf($format, $inner);
            }
        }

        return self::fromWords($name);
    }

    // ==================== الداخل ====================

    /**
     * ترجمة كلمةً كلمة.
     *
     * تُعاد السلسلة كما هي إن لم تُترجَم أيّ كلمة — كي يعرف المستدعي أن
     * الترجمة أخفقت فيتصرّف، بدل أن يتلقّى خليطاً نصفه عربي.
     */
    protected static function fromWords(string $name): string
    {
        $parts = preg_split('/[_\s]+/', $name) ?: [];
        $out = [];
        $translated = 0;

        foreach ($parts as $part) {
            $lower = Str::lower($part);
            $singular = Str::singular($lower);

            $word = self::WORDS[$lower] ?? self::WORDS[$singular] ?? null;

            if ($word !== null) {
                $translated++;
                $out[] = $word;

                continue;
            }

            $out[] = $part;
        }

        return $translated === 0 ? $name : implode(' ', $out);
    }
}
