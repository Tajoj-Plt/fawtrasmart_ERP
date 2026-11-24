<?php

/*
 * أسماء أنواع الإشعارات كما تُعرض للمستخدم — مفتاح النوع ← اسمه.
 *
 * يقرؤها `App\Models\notifications::typeLabel()` قبل جدول `notification_settings`:
 * الجدول يُملأ بالهجرة وبالاكتشاف الآلي، والصفُّ المكتشَف تسميتُه مفتاحُه
 * الإنجليزي — فكانت شاشة الإشعارات تعرض `invoice_payment` بدل «دفعة على فاتورة»
 * في كل قاعدةٍ لم تُسمَّ أنواعُها بعد.
 */

return [
    // clients
    'client_note' => 'ملاحظة على عميل',
    'client_popup' => 'إشعار منبثق للعميل',
    'client_status_request' => 'طلب تغيير حالة عميل',
    'client_status_approved' => 'قبول تغيير حالة عميل',
    'client_status_rejected' => 'رفض تغيير حالة عميل',
    'visit' => 'زيارة عميل',
    'visit_update' => 'تعديل زيارة',
    'visit_notification' => 'تنبيه زيارة',
    'target_evaluation' => 'تقييم مستهدف العميل',
    'مراجعة عميل' => 'مراجعة عميل',
    'reservation_created' => 'إنشاء حجز',
    'reservation_confirmed' => 'تأكيد حجز',
    'reservation_cancelled' => 'إلغاء حجز',
    'reservation_rescheduled' => 'تغيير موعد حجز',
    'reservation_completed' => 'اكتمال حجز',
    'reservation_no_show' => 'عدم حضور لحجز',
    'reservation_reminder' => 'تذكير بموعد حجز',
    'reservation_reminder_employee' => 'تذكير الموظّف بحجزٍ عليه',
    'reservation_portal_request' => 'طلب حجز من بوابة العميل',
    'client_appointment_reminder' => 'تذكير بموعد عميل',
    'client_appointment_assigned' => 'إسناد موعد عميل',
    'client_appointment_shared' => 'مشاركة موعد مع العميل',
    'client_payment_notice' => 'إشعار دفع من بوابة العميل',
    'client_payment_notice_reviewed' => 'البتّ في إشعار دفع',
    'client_visit_request' => 'طلب زيارة ميدانية',
    'client_visit_request_reviewed' => 'البتّ في طلب زيارة',

    // hr
    'advance_request' => 'طلب سلفة',
    'advance_approved' => 'قبول سلفة',
    'advance_rejected' => 'رفض سلفة',
    'advance_settled' => 'تسوية سلفة',
    'deduction' => 'خصم على موظف',
    'salary_disbursement' => 'صرف راتب',
    'contract_expiration' => 'قرب انتهاء عقد',
    'contract_expiration_admin' => 'قرب انتهاء عقد — للإدارة',
    'expiry' => 'انتهاء صلاحية',

    // purchases
    'purchase_creation' => 'أمر شراء جديد',
    'purchase_request' => 'طلب شراء',
    'purchase_update' => 'تعديل أمر شراء',
    'purchase_invoice' => 'فاتورة مشتريات',
    'purchase_conversion' => 'تحويل أمر شراء',
    'purchase_return' => 'مرتجع مشتريات',
    'purchase_return_creation' => 'إنشاء مرتجع مشتريات',
    'supplier_payment_cancelled' => 'إلغاء دفعة مورّد',
    'city_notice_creation' => 'إشعار مدينة',
    'purchase_due_payment' => 'مستحقّات الموردين',

    // sales
    'invoice' => 'فاتورة جديدة',
    'invoice_payment' => 'دفعة على فاتورة',
    'invoice_payment_cancelled' => 'إلغاء دفعة فاتورة',
    'invoice_payment_summary' => 'ملخّص دفعات الفاتورة',
    'Receipt' => 'سند قبض',
    'receipt_cancelled' => 'إلغاء سند قبض',
    'Payment' => 'سند صرف',
    'payment_create' => 'تسجيل دفعة',
    'payment_update' => 'تعديل دفعة',
    'payment_cancellation' => 'إلغاء دفعة',
    'bulk_payment' => 'دفعة مجمّعة',
    'collection' => 'تحصيل',
    'sales' => 'إشعار مبيعات',
    'invoice_transfer' => 'طلب تسليم فاتورة',
    'invoice_transfer_accepted' => 'قبول تسليم فاتورة',
    'invoice_transfer_rejected' => 'رفض تسليم فاتورة',
    'invoice_transfer_cancelled' => 'إلغاء تسليم فاتورة',
    'installment_due' => 'استحقاق قسط',
    'installment_overdue' => 'تأخّر قسط',
    'installment_late_fee' => 'غرامة تأخير قسط',
    'sales_due_payment' => 'مستحقّات العملاء',
    'sales_periodic_failed' => 'تعذّر إصدار فاتورة دورية',

    // stock
    'manufacturing_order' => 'أمر تصنيع',
    'low_stock' => 'نفاد مخزون',
    'Products' => 'إشعار أصناف',

    // system
    'general' => 'إشعار عام',
    'notification' => 'إشعار',
    'automatic_notification' => 'إشعار تلقائي',
    'notes' => 'ملاحظات',
    'reply' => 'ردّ على إشعار',
    'alert' => 'تنبيه',
    'urgent' => 'عاجل',
    'reminder' => 'تذكير',
    'warning' => 'تحذير',
    'error' => 'خطأ',
    'success' => 'نجاح عملية',
    'status_change' => 'تغيير حالة',
    'finance_log' => 'سجلّ مالي',
    'workspace' => 'مساحة عمل',
    'sms_gateway_failing' => 'تعطّل بوابة الرسائل',
    'cron_job_failed' => 'فشل مهمة مجدولة',
    'cron_job_stuck' => 'مهمة مجدولة متعثّرة',
    'cron_job_succeeded' => 'نجاح مهمة مجدولة',
    'scheduler_down' => 'توقّف المجدول',
    'backup_completed' => 'اكتمال نسخة احتياطية',
    'backup_failed' => 'فشل نسخة احتياطية',
    'backup_cleanup_failed' => 'فشل تنظيف النسخ القديمة',
    'backup_storage_full' => 'امتلاء مساحة النسخ الاحتياطية',
    'backup_restore_completed' => 'اكتمال استرجاع نسخة',
    'backup_restore_failed' => 'فشل استرجاع نسخة',
    'subscription_usage_threshold' => 'بلوغ حدّ استخدام في الاشتراك',
    'subscription_usage_exhausted' => 'استنفاد حدّ استخدام في الاشتراك',
    'subscription_status' => 'حالة الاشتراك',

    // أنواعٌ تُنشئها الشيفرة ولا صفَّ لها في الإعدادات
    'commission_settlement' => 'تسوية عمولة',
    'target_settlement_run' => 'تشغيل تسوية المستهدفات',
    'access_notification' => 'تنبيه دخول',
    'task' => 'مهمة',
];
