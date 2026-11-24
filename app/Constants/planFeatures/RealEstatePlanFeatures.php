<?php

namespace App\Constants\planFeatures;

/**
 * ميزات الباقة لإدارة العقارات.
 *
 * **تنبيه — اقرأه قبل الاعتماد على هذه الحرّاس.** بوّابة الاشتراك
 * (`SubscriptionGate::allows`) تعامل المفتاح الذي لا تذكره لقطةُ فوترة
 * **مسموحاً**. وكتالوج فوترة لا يحمل مفاتيح عقارات حتى تاريخ كتابة هذا
 * الملف (2026-09-20)؛ فالحرّاس المبنيّة على هذه الثوابت موصولةٌ في مواضعها
 * الصحيحة (المسارات والقائمة) لكنها **لا تمنع شيئاً** إلى أن تُضاف المفاتيح
 * نفسها — حرفياً — إلى `plan.permissions` و`usage[].key` في فوترة. حينها
 * تعمل بلا أي تعديلٍ هنا. وإن اختارت فوترة أسماءً أخرى فتُصحَّح القيم هنا
 * في موضعٍ واحد.
 */
class RealEstatePlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    public const PROPERTIES = 'realestate_properties';

    public const LISTINGS = 'realestate_listings';

    public const CONTRACTS = 'realestate_contracts';

    public const PAYMENTS = 'realestate_payments';

    public const MAINTENANCE = 'realestate_maintenance';

    public const TASKS = 'realestate_tasks';

    public const REPORTS = 'realestate_reports';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::PROPERTIES, self::LISTINGS, self::CONTRACTS, self::PAYMENTS,
            self::MAINTENANCE, self::TASKS, self::REPORTS,
        ];
    }

    /*
        لا مفاتيح حدود استخدام هنا عمداً: حدّ الاستخدام يحتاج مدخلاً في
        `UsageReporter` يرفع الاستهلاك إلى فوترة، ورفعُ مفتاحٍ لا تعرفه فوترة
        ليس «حارساً خاملاً» كميزة الشاشة بل طلبٌ قد يُرفض. تُضاف حين تُعرَّف
        هناك.
    */
}
