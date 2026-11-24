<?php

namespace App\Enums;

enum ActionType: string
{
    // ─── إجراءات الموظفين - المبيعات ───
    case INVOICE_CREATED = 'invoice_created';
    case CASH_SALE = 'cash_sale';
    case RETURN_PROCESSED = 'return_processed';

    // ─── إجراءات الموظفين - التحصيل ───
    case PAYMENT_COLLECTED = 'payment_collected';
    case RECEIPT_CREATED = 'receipt_created';
    case OVERDUE_COLLECTION = 'overdue_collection';

    // ─── إجراءات الموظفين - العملاء ───
    case NEW_CLIENT_ACQUIRED = 'new_client_acquired';
    case CUSTOMER_SATISFACTION = 'customer_satisfaction';
    case CUSTOMER_COMPLAINT = 'customer_complaint';
    case CLIENT_ISSUE_RESOLVED = 'client_issue_resolved';
    case CLIENT_LOST = 'client_lost';

    // ─── إجراءات الموظفين - المهام ───
    case TASK_COMPLETED = 'task_completed';
    case EARLY_COMPLETION = 'early_completion';
    case LATE_DELIVERY = 'late_delivery';
    case INVENTORY_COUNT = 'inventory_count';
    case TRAINING_COMPLETED = 'training_completed';
    case COLLABORATION = 'collaboration';
    case NOTE_CREATED = 'note_created';
    case REPORT_SUBMITTED = 'report_submitted';
    case SUGGESTION_SUBMITTED = 'suggestion_submitted';
    case MENTORING = 'mentoring';

    // ─── إجراءات الموظفين - الحضور ───
    case ATTENDANCE_ON_TIME = 'attendance_on_time';
    case LATE_ARRIVAL = 'late_arrival';
    case ABSENCE = 'absence';
    case OVERTIME_HOURS = 'overtime_hours';
    case SHIFT_COVERAGE = 'shift_coverage';

    // ─── إجراءات الموظفين - الأداء ───
    case PERFORMANCE_BONUS = 'performance_bonus';
    case SALES_TARGET_ACHIEVED = 'sales_target_achieved';
    case MONTHLY_TOP_PERFORMER = 'monthly_top_performer';
    case TARGET_MISSED = 'target_missed';

    // ─── إجراءات الموظفين - المخالفات والأخطاء ───
    case SALES_ERROR = 'sales_error';
    case RETURN_CAUSED = 'return_caused';
    case VISIT_FAILED = 'visit_failed';
    case POLICY_VIOLATION = 'policy_violation';
    case CASH_SHORTAGE = 'cash_shortage';
    case SAFETY_VIOLATION = 'safety_violation';

    // ─── إجراءات العملاء - المشتريات ───
    case INVOICE_PAID = 'invoice_paid';
    case ORDER_COMPLETED = 'order_completed';
    case FIRST_PURCHASE = 'first_purchase';
    case RETURN_INVOICE = 'return_invoice';

    // ─── إجراءات العملاء - المدفوعات ───
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_VOUCHER = 'payment_voucher';
    case CHEQUE_RECEIVED = 'cheque_received';
    case RENTAL_PAID = 'rental_paid';

    // ─── إجراءات العملاء - التفاعل ───
    case REVIEW_SUBMITTED = 'review_submitted';
    case REFERRAL = 'referral';
    case PROFILE_COMPLETED = 'profile_completed';
    case VISIT_COMPLETED = 'visit_completed';

    // ─── إجراءات العملاء - العضويات ───
    case SUBSCRIPTION_RENEWED = 'subscription_renewed';
    case MEMBERSHIP_UPGRADED = 'membership_upgraded';
    case RESERVATION_CONFIRMED = 'reservation_confirmed';
    case RESERVATION_CANCELLED = 'reservation_cancelled';

    // ─── إجراءات العملاء - مكافآت ───
    case BIRTHDAY_BONUS = 'birthday_bonus';

    // ─── إجراءات مشتركة ───
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
    case BONUS = 'bonus';
    case REDEMPTION = 'redemption';
    case CHALLENGE_REWARD = 'challenge_reward';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE_CREATED => 'إنشاء فاتورة',
            self::CASH_SALE => 'بيع نقدي',
            self::RETURN_PROCESSED => 'معالجة مرتجع',
            self::PAYMENT_COLLECTED => 'تحصيل دفعة',
            self::RECEIPT_CREATED => 'سند قبض',
            self::OVERDUE_COLLECTION => 'تحصيل ديون متأخرة',
            self::NEW_CLIENT_ACQUIRED => 'اكتساب عميل جديد',
            self::CUSTOMER_SATISFACTION => 'رضا العميل',
            self::CUSTOMER_COMPLAINT => 'شكوى عميل',
            self::CLIENT_ISSUE_RESOLVED => 'حل مشكلة عميل',
            self::CLIENT_LOST => 'فقدان عميل',
            self::TASK_COMPLETED => 'إنجاز مهمة',
            self::EARLY_COMPLETION => 'إنجاز قبل الموعد',
            self::LATE_DELIVERY => 'تأخر في التسليم',
            self::INVENTORY_COUNT => 'جرد مخزون',
            self::TRAINING_COMPLETED => 'إتمام تدريب',
            self::COLLABORATION => 'تعاون بين الأقسام',
            self::NOTE_CREATED => 'تسجيل ملاحظة',
            self::REPORT_SUBMITTED => 'تقديم تقرير',
            self::SUGGESTION_SUBMITTED => 'تقديم اقتراح تحسين',
            self::MENTORING => 'إرشاد زميل',
            self::ATTENDANCE_ON_TIME => 'حضور في الموعد',
            self::LATE_ARRIVAL => 'تأخر عن العمل',
            self::ABSENCE => 'غياب',
            self::OVERTIME_HOURS => 'عمل إضافي',
            self::SHIFT_COVERAGE => 'تغطية وردية زميل',
            self::PERFORMANCE_BONUS => 'مكافأة أداء',
            self::SALES_TARGET_ACHIEVED => 'تحقيق هدف المبيعات',
            self::MONTHLY_TOP_PERFORMER => 'أفضل موظف شهري',
            self::TARGET_MISSED => 'عدم تحقيق الهدف',
            self::SALES_ERROR => 'خطأ في المبيعات',
            self::RETURN_CAUSED => 'تسبب في مرتجع',
            self::VISIT_FAILED => 'إخفاق زيارة',
            self::POLICY_VIOLATION => 'مخالفة سياسات',
            self::CASH_SHORTAGE => 'عجز في الخزينة',
            self::SAFETY_VIOLATION => 'مخالفة سلامة',
            self::INVOICE_PAID => 'دفع فاتورة',
            self::ORDER_COMPLETED => 'طلب مكتمل',
            self::FIRST_PURCHASE => 'أول عملية شراء',
            self::RETURN_INVOICE => 'مرتجع',
            self::PAYMENT_RECEIVED => 'دفعة مستلمة',
            self::PAYMENT_VOUCHER => 'سند صرف',
            self::CHEQUE_RECEIVED => 'شيك مستلم',
            self::RENTAL_PAID => 'إيجار مدفوع',
            self::REVIEW_SUBMITTED => 'تقديم تقييم',
            self::REFERRAL => 'إحالة عميل',
            self::PROFILE_COMPLETED => 'إكمال الملف الشخصي',
            self::VISIT_COMPLETED => 'زيارة مكتملة',
            self::SUBSCRIPTION_RENEWED => 'تجديد اشتراك',
            self::MEMBERSHIP_UPGRADED => 'ترقية العضوية',
            self::RESERVATION_CONFIRMED => 'حجز مؤكد',
            self::RESERVATION_CANCELLED => 'إلغاء حجز',
            self::BIRTHDAY_BONUS => 'مكافأة عيد ميلاد',
            self::MANUAL_ADJUSTMENT => 'تعديل يدوي',
            self::BONUS => 'مكافأة',
            self::REDEMPTION => 'استبدال نقاط',
            self::CHALLENGE_REWARD => 'مكافأة تحدي',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::INVOICE_CREATED, self::CASH_SALE, self::RETURN_PROCESSED => 'المبيعات',
            self::PAYMENT_COLLECTED, self::RECEIPT_CREATED, self::OVERDUE_COLLECTION => 'التحصيل',
            self::NEW_CLIENT_ACQUIRED, self::CUSTOMER_SATISFACTION, self::CUSTOMER_COMPLAINT, self::CLIENT_ISSUE_RESOLVED, self::CLIENT_LOST => 'العملاء',
            self::TASK_COMPLETED, self::EARLY_COMPLETION, self::LATE_DELIVERY, self::INVENTORY_COUNT, self::TRAINING_COMPLETED, self::COLLABORATION, self::NOTE_CREATED, self::REPORT_SUBMITTED, self::SUGGESTION_SUBMITTED, self::MENTORING => 'المهام',
            self::ATTENDANCE_ON_TIME, self::LATE_ARRIVAL, self::ABSENCE, self::OVERTIME_HOURS, self::SHIFT_COVERAGE => 'الحضور',
            self::PERFORMANCE_BONUS, self::SALES_TARGET_ACHIEVED, self::MONTHLY_TOP_PERFORMER, self::TARGET_MISSED => 'الأداء',
            self::SALES_ERROR, self::RETURN_CAUSED, self::VISIT_FAILED, self::POLICY_VIOLATION, self::CASH_SHORTAGE, self::SAFETY_VIOLATION => 'المخالفات',
            self::INVOICE_PAID, self::ORDER_COMPLETED, self::FIRST_PURCHASE, self::RETURN_INVOICE => 'المشتريات',
            self::PAYMENT_RECEIVED, self::PAYMENT_VOUCHER, self::CHEQUE_RECEIVED, self::RENTAL_PAID => 'المدفوعات',
            self::REVIEW_SUBMITTED, self::REFERRAL, self::PROFILE_COMPLETED, self::VISIT_COMPLETED => 'التفاعل',
            self::SUBSCRIPTION_RENEWED, self::MEMBERSHIP_UPGRADED, self::RESERVATION_CONFIRMED, self::RESERVATION_CANCELLED => 'العضويات',
            self::BIRTHDAY_BONUS => 'مكافآت',
            self::MANUAL_ADJUSTMENT, self::BONUS, self::REDEMPTION, self::CHALLENGE_REWARD => 'أخرى',
        };
    }

    /**
     * هل هذا الإجراء سلبي (خصم نقاط)؟
     */
    public function isNegative(): bool
    {
        return in_array($this, [
            self::CUSTOMER_COMPLAINT,
            self::CLIENT_LOST,
            self::LATE_DELIVERY,
            self::LATE_ARRIVAL,
            self::ABSENCE,
            self::TARGET_MISSED,
            self::SALES_ERROR,
            self::RETURN_CAUSED,
            self::POLICY_VIOLATION,
            self::CASH_SHORTAGE,
            self::SAFETY_VIOLATION,
            self::RETURN_INVOICE,
            self::RESERVATION_CANCELLED,
            self::REDEMPTION,
        ]);
    }

    /**
     * إجراءات الموظفين
     */
    public static function employeeActions(): array
    {
        return [
            // إيجابية
            self::INVOICE_CREATED,
            self::CASH_SALE,
            self::RETURN_PROCESSED,
            self::PAYMENT_COLLECTED,
            self::RECEIPT_CREATED,
            self::OVERDUE_COLLECTION,
            self::NEW_CLIENT_ACQUIRED,
            self::CUSTOMER_SATISFACTION,
            self::CLIENT_ISSUE_RESOLVED,
            self::TASK_COMPLETED,
            self::EARLY_COMPLETION,
            self::INVENTORY_COUNT,
            self::TRAINING_COMPLETED,
            self::COLLABORATION,
            self::NOTE_CREATED,
            self::REPORT_SUBMITTED,
            self::SUGGESTION_SUBMITTED,
            self::MENTORING,
            self::ATTENDANCE_ON_TIME,
            self::OVERTIME_HOURS,
            self::SHIFT_COVERAGE,
            self::PERFORMANCE_BONUS,
            self::SALES_TARGET_ACHIEVED,
            self::MONTHLY_TOP_PERFORMER,
            self::VISIT_COMPLETED,
            self::PAYMENT_VOUCHER,
            self::MANUAL_ADJUSTMENT,
            self::BONUS,
            self::CHALLENGE_REWARD,
            // سلبية
            self::CUSTOMER_COMPLAINT,
            self::CLIENT_LOST,
            self::LATE_DELIVERY,
            self::LATE_ARRIVAL,
            self::ABSENCE,
            self::TARGET_MISSED,
            self::SALES_ERROR,
            self::RETURN_CAUSED,
            self::VISIT_FAILED,
            self::POLICY_VIOLATION,
            self::CASH_SHORTAGE,
            self::SAFETY_VIOLATION,
        ];
    }

    /**
     * إجراءات العملاء
     */
    public static function clientActions(): array
    {
        return [
            self::INVOICE_CREATED,
            self::INVOICE_PAID,
            self::ORDER_COMPLETED,
            self::FIRST_PURCHASE,
            self::RETURN_INVOICE,
            self::PAYMENT_RECEIVED,
            self::PAYMENT_VOUCHER,
            self::RECEIPT_CREATED,
            self::CHEQUE_RECEIVED,
            self::RENTAL_PAID,
            self::REVIEW_SUBMITTED,
            self::REFERRAL,
            self::PROFILE_COMPLETED,
            self::VISIT_COMPLETED,
            self::SUBSCRIPTION_RENEWED,
            self::MEMBERSHIP_UPGRADED,
            self::RESERVATION_CONFIRMED,
            self::RESERVATION_CANCELLED,
            self::BIRTHDAY_BONUS,
            self::MANUAL_ADJUSTMENT,
            self::REDEMPTION,
            self::CHALLENGE_REWARD,
        ];
    }

    /**
     * إجراءات الموظفين السلبية فقط
     */
    public static function employeeNegativeActions(): array
    {
        return array_filter(self::employeeActions(), fn(self $action) => $action->isNegative());
    }

    /**
     * إجراءات الموظفين الإيجابية فقط
     */
    public static function employeePositiveActions(): array
    {
        return array_filter(self::employeeActions(), fn(self $action) => !$action->isNegative());
    }

    /**
     * إرجاع الإجراءات مجمّعة حسب المجموعة لنوع مستخدم معين
     */
    public static function groupedForUserType(string $userType): array
    {
        $actions = $userType === 'employee' ? self::employeeActions() : self::clientActions();
        $grouped = [];

        foreach ($actions as $action) {
            $group = $action->group();
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = [
                'value' => $action->value,
                'label' => $action->label(),
                'is_negative' => $action->isNegative(),
            ];
        }

        return $grouped;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
