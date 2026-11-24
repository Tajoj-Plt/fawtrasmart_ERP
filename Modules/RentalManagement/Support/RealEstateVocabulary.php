<?php

namespace Modules\RentalManagement\Support;

/**
 * مفردات إدارة العقارات — الحالات والأنواع وتسمياتها وألوانها في مكانٍ واحد.
 *
 * كلّ قائمة: `القيمة => ['label' => …, 'tone' => …, 'icon' => …]`. والشاشات
 * والتحقّق والتقارير تقرأ من هنا، فلا تُكتب حالةٌ نصّاً حرّاً في متحكّمٍ
 * ولا يختلف لونُها بين شاشةٍ وأخرى.
 *
 * اللون دلالةٌ لا زينة (كما في لوحة المخزون): info خبرٌ محايد، success
 * سليم، warning يحتاج انتباهاً، danger يحتاج تصرّفاً، secondary منتهٍ.
 */
class RealEstateVocabulary
{
    /**
     * نوع العقار يحدّد شكله: كم وحدةً فيه وهل له طوابق. «مجمع» و«مشروع»
     * ليسا نوعين: المجمع حاويةٌ فوق المباني (Complex)، والمشروع خارج هذه الوحدة.
     */
    public const ENTITY_TYPES = [
        'building' => ['label' => 'عمارة'],
        'villa' => ['label' => 'فيلا مستقلة'],
        'land' => ['label' => 'أرض'],
        'standalone' => ['label' => 'منشأة مستقلة'],
    ];

    /** أنواعٌ وحدتُها واحدة تُنشأ معها تلقائياً (`re_units.is_auto`). */
    public const SINGLE_UNIT_ENTITY_TYPES = ['villa', 'land', 'standalone'];

    /** ما تكونه المنشأة المستقلة: نوعُ وحدتها التلقائية. */
    public const STANDALONE_UNIT_TYPES = ['warehouse', 'showroom', 'workshop'];

    /** ملكية المبنى: لمالكٍ خارجي يديره المكتب، أو مملوكٌ للمنشأة نفسها. */
    public const OWNERSHIP = [
        'managed' => ['label' => 'لمالكٍ خارجي (إدارة أملاك)'],
        'own' => ['label' => 'مملوك للمنشأة'],
    ];

    public const USAGE_TYPES = [
        'residential' => ['label' => 'سكني'],
        'commercial' => ['label' => 'تجاري'],
        'mixed' => ['label' => 'مختلط'],
    ];

    public const BUILDING_STATUSES = [
        'active' => ['label' => 'نشط', 'tone' => 'success'],
        'inactive' => ['label' => 'غير نشط', 'tone' => 'secondary'],
        'maintenance' => ['label' => 'تحت الصيانة', 'tone' => 'warning'],
    ];

    /** استخدام الوحدة — قيمتان فقط، وهو المعتمد في نوع العقد والضريبة. */
    public const UNIT_USAGE_TYPES = [
        'residential' => ['label' => 'سكني'],
        'commercial' => ['label' => 'تجاري'],
    ];

    /**
     * أنواع الوحدات مجمَّعةً بالاستخدام (`usage`): null = تصلح للاستخدامين
     * (الأرض، و«أخرى» الموروثة من بياناتٍ قديمة).
     */
    public const UNIT_TYPES = [
        'apartment' => ['label' => 'شقة', 'usage' => 'residential'],
        'villa' => ['label' => 'فيلا', 'usage' => 'residential'],
        'studio' => ['label' => 'استوديو', 'usage' => 'residential'],
        'floor' => ['label' => 'دور', 'usage' => 'residential'],
        'annex' => ['label' => 'ملحق', 'usage' => 'residential'],
        'room' => ['label' => 'غرفة', 'usage' => 'residential'],
        'shop' => ['label' => 'محل', 'usage' => 'commercial'],
        'office' => ['label' => 'مكتب', 'usage' => 'commercial'],
        'showroom' => ['label' => 'معرض', 'usage' => 'commercial'],
        'warehouse' => ['label' => 'مستودع', 'usage' => 'commercial'],
        'workshop' => ['label' => 'ورشة', 'usage' => 'commercial'],
        'land' => ['label' => 'أرض', 'usage' => null],
        'other' => ['label' => 'أخرى', 'usage' => null],
    ];

    public const FURNISHED = [
        'furnished' => ['label' => 'مفروشة'],
        'unfurnished' => ['label' => 'غير مفروشة'],
        'partial' => ['label' => 'مفروشة جزئياً'],
    ];

    public const UNIT_CONDITIONS = [
        'excellent' => ['label' => 'ممتاز'],
        'good' => ['label' => 'جيد'],
        'needs_maintenance' => ['label' => 'يحتاج صيانة'],
    ];

    public const ORIENTATIONS = [
        'north' => ['label' => 'شمالية'],
        'south' => ['label' => 'جنوبية'],
        'east' => ['label' => 'شرقية'],
        'west' => ['label' => 'غربية'],
        'corner' => ['label' => 'زاوية'],
    ];

    public const OCCUPANCY = [
        'vacant' => ['label' => 'شاغرة', 'tone' => 'info', 'icon' => 'unlock'],
        'rented' => ['label' => 'مؤجرة', 'tone' => 'success', 'icon' => 'key'],
        'reserved' => ['label' => 'محجوزة', 'tone' => 'warning', 'icon' => 'bookmark'],
        'maintenance' => ['label' => 'تحت الصيانة', 'tone' => 'warning', 'icon' => 'tool'],
        'unavailable' => ['label' => 'غير متاحة', 'tone' => 'secondary', 'icon' => 'slash'],
    ];

    public const RENT_PERIODS = [
        'monthly' => ['label' => 'شهري'],
        'yearly' => ['label' => 'سنوي'],
    ];

    public const LISTING_TYPES = [
        'rent' => ['label' => 'إيجار', 'tone' => 'info'],
        'sale' => ['label' => 'بيع', 'tone' => 'primary'],
    ];

    /** عرضٌ (معروضٌ للتسويق) أو طلبٌ (عميلٌ يبحث عن عقار). */
    public const LISTING_KINDS = [
        'offer' => ['label' => 'عرض', 'tone' => 'primary'],
        'request' => ['label' => 'طلب', 'tone' => 'warning'],
    ];

    public const LISTING_STATUSES = [
        'draft' => ['label' => 'مسودة', 'tone' => 'secondary'],
        'active' => ['label' => 'نشط', 'tone' => 'success'],
        'paused' => ['label' => 'موقوف', 'tone' => 'warning'],
        'rented' => ['label' => 'تم الإيجار', 'tone' => 'success'],
        'purchased' => ['label' => 'تم الشراء', 'tone' => 'success'],
        'expired' => ['label' => 'منتهي', 'tone' => 'danger'],
        'closed' => ['label' => 'مغلق', 'tone' => 'secondary'],
    ];

    /** حالاتٌ نهائيّة: العرض/الطلب انتهى أمره (تمّت الصفقة أو أُغلق) ولا يُعدّ نشطاً. */
    public const LISTING_FINAL_STATUSES = ['rented', 'purchased', 'closed'];

    public const COMMISSION_TYPES = [
        'percent' => ['label' => 'نسبة %'],
        'fixed' => ['label' => 'مبلغ ثابت'],
    ];

    public const RELATIONSHIP_ROLES = [
        'tenant' => ['label' => 'مستأجر', 'tone' => 'success'],
        'owner' => ['label' => 'مالك', 'tone' => 'primary'],
        'prospect_tenant' => ['label' => 'مستأجر محتمل', 'tone' => 'info'],
        'prospect_buyer' => ['label' => 'مشتري محتمل', 'tone' => 'info'],
        'buyer' => ['label' => 'مشتري', 'tone' => 'success'],
        'investor' => ['label' => 'مستثمر', 'tone' => 'warning'],
        'broker' => ['label' => 'وسيط', 'tone' => 'secondary'],
    ];

    public const RELATIONSHIP_STATUSES = [
        'active' => ['label' => 'نشط', 'tone' => 'success'],
        'follow_up' => ['label' => 'متابعة', 'tone' => 'warning'],
        'closed' => ['label' => 'مغلق', 'tone' => 'secondary'],
    ];

    public const VIEWING_STATUSES = [
        'scheduled' => ['label' => 'مجدولة', 'tone' => 'info'],
        'done' => ['label' => 'تمّت', 'tone' => 'success'],
        'no_show' => ['label' => 'لم يحضر', 'tone' => 'warning'],
        'cancelled' => ['label' => 'ملغاة', 'tone' => 'secondary'],
    ];

    public const CONTRACT_TYPES = [
        'residential_lease' => ['label' => 'إيجار سكني'],
        'commercial_lease' => ['label' => 'إيجار تجاري'],
        'sale' => ['label' => 'بيع'],
    ];

    public const CONTRACT_STATUSES = [
        'draft' => ['label' => 'مسودة', 'tone' => 'secondary'],
        'pending_approval' => ['label' => 'بانتظار الاعتماد', 'tone' => 'warning'],
        'active' => ['label' => 'ساري', 'tone' => 'success'],
        'renewing' => ['label' => 'قيد التجديد', 'tone' => 'info'],
        'expired' => ['label' => 'منتهي', 'tone' => 'danger'],
        'cancelled' => ['label' => 'ملغى', 'tone' => 'secondary'],
    ];

    /** الحالات التي يشغل فيها العقدُ وحدتَه فعلاً — أساسُ فحص التداخل. */
    public const CONTRACT_OCCUPYING = ['pending_approval', 'active', 'renewing'];

    public const PAYMENT_FREQUENCIES = [
        'monthly' => ['label' => 'شهري', 'months' => 1],
        'quarterly' => ['label' => 'ربع سنوي', 'months' => 3],
        'semi_annual' => ['label' => 'نصف سنوي', 'months' => 6],
        'annual' => ['label' => 'سنوي', 'months' => 12],
    ];

    public const RENEWAL_STATUSES = [
        'draft' => ['label' => 'مسودة', 'tone' => 'secondary'],
        'review' => ['label' => 'مراجعة', 'tone' => 'warning'],
        'approved' => ['label' => 'موافقة', 'tone' => 'info'],
        'completed' => ['label' => 'مكتمل', 'tone' => 'success'],
        'rejected' => ['label' => 'مرفوض', 'tone' => 'danger'],
    ];

    public const SCHEDULE_STATUSES = [
        'scheduled' => ['label' => 'مجدولة', 'tone' => 'secondary'],
        'due' => ['label' => 'مستحقة', 'tone' => 'info'],
        'partial' => ['label' => 'جزئية', 'tone' => 'warning'],
        'paid' => ['label' => 'مدفوعة', 'tone' => 'success'],
        'overdue' => ['label' => 'متأخرة', 'tone' => 'danger'],
        'cancelled' => ['label' => 'ملغاة', 'tone' => 'secondary'],
    ];

    public const SCHEDULE_KINDS = [
        'rent' => ['label' => 'إيجار'],
        'deposit' => ['label' => 'تأمين'],
    ];

    public const PAYMENT_METHODS = [
        'cash' => ['label' => 'نقد'],
        'transfer' => ['label' => 'تحويل بنكي'],
        'card' => ['label' => 'بطاقة'],
        'cheque' => ['label' => 'شيك'],
    ];

    public const POSTING_STATUSES = [
        'draft' => ['label' => 'مسودة', 'tone' => 'secondary'],
        'posted' => ['label' => 'مرحّلة', 'tone' => 'success'],
        'cancelled' => ['label' => 'ملغاة', 'tone' => 'danger'],
    ];

    public const MAINTENANCE_CATEGORIES = [
        'plumbing' => ['label' => 'سباكة', 'icon' => 'droplet'],
        'electrical' => ['label' => 'كهرباء', 'icon' => 'zap'],
        'hvac' => ['label' => 'تكييف', 'icon' => 'wind'],
        'doors' => ['label' => 'أبواب ونوافذ', 'icon' => 'log-in'],
        'painting' => ['label' => 'دهانات', 'icon' => 'edit-3'],
        'elevator' => ['label' => 'مصاعد', 'icon' => 'chevrons-up'],
        'cleaning' => ['label' => 'نظافة', 'icon' => 'trash'],
        'other' => ['label' => 'أخرى', 'icon' => 'tool'],
    ];

    public const PRIORITIES = [
        'low' => ['label' => 'منخفضة', 'tone' => 'secondary'],
        'medium' => ['label' => 'متوسطة', 'tone' => 'info'],
        'high' => ['label' => 'عالية', 'tone' => 'warning'],
        'urgent' => ['label' => 'طارئة', 'tone' => 'danger'],
    ];

    public const MAINTENANCE_STATUSES = [
        'open' => ['label' => 'مفتوحة', 'tone' => 'info'],
        'waiting' => ['label' => 'بانتظار', 'tone' => 'warning'],
        'in_progress' => ['label' => 'قيد التنفيذ', 'tone' => 'primary'],
        'completed' => ['label' => 'مكتملة', 'tone' => 'success'],
        'cancelled' => ['label' => 'ملغاة', 'tone' => 'secondary'],
    ];

    public const CHARGE_TO = [
        'owner' => ['label' => 'المالك'],
        'tenant' => ['label' => 'المستأجر'],
        'company' => ['label' => 'الشركة'],
    ];

    public const ID_TYPES = [
        'national_id' => ['label' => 'هوية وطنية', 'tone' => 'primary'],
        'iqama' => ['label' => 'إقامة', 'tone' => 'info'],
        'passport' => ['label' => 'جواز سفر', 'tone' => 'secondary'],
        'gcc_id' => ['label' => 'هوية خليجية', 'tone' => 'secondary'],
        'commercial_register' => ['label' => 'سجل تجاري', 'tone' => 'warning'],
    ];

    public const GENDERS = [
        'male' => ['label' => 'ذكر', 'tone' => 'secondary'],
        'female' => ['label' => 'أنثى', 'tone' => 'secondary'],
    ];

    public const MARITAL_STATUSES = [
        'single' => ['label' => 'أعزب', 'tone' => 'secondary'],
        'married' => ['label' => 'متزوج', 'tone' => 'secondary'],
    ];

    public const OWNER_TYPES = [
        'individual' => ['label' => 'فرد'],
        'company' => ['label' => 'شركة / مؤسسة'],
    ];

    /** نوع اتفاقية إدارة الأملاك: نسبةٌ من الإيجار أو مبلغٌ ثابت لكل قسط. */
    public const AGREEMENT_TYPES = [
        'percentage' => ['label' => 'نسبة مئوية %'],
        'fixed' => ['label' => 'قيمة ثابتة'],
    ];

    /** أساس احتساب العمولة (وثيقة الدورة المحاسبية): من المحصَّل فعلاً أو من المستحق. */
    public const COMMISSION_BASES = [
        'collected' => ['label' => 'من الإيجار المحصَّل فعلاً'],
        'due' => ['label' => 'من الإيجار المستحق'],
    ];

    public const OWNER_STATUSES = [
        'active' => ['label' => 'نشط', 'tone' => 'success'],
        'inactive' => ['label' => 'غير نشط', 'tone' => 'secondary'],
    ];

    /** حركات دفتر المالك؛ الاتجاه: credit = له، debit = عليه. */
    public const OWNER_ENTRY_TYPES = [
        'rent' => ['label' => 'إيجار محصَّل', 'tone' => 'success'],
        'commission' => ['label' => 'عمولة إدارة الأملاك', 'tone' => 'warning'],
        'maintenance' => ['label' => 'صيانة على المالك', 'tone' => 'warning'],
        'transfer' => ['label' => 'تحويل للمالك', 'tone' => 'info'],
        'adjustment' => ['label' => 'تسوية', 'tone' => 'secondary'],
        'reversal' => ['label' => 'عكس حركة', 'tone' => 'danger'],
    ];

    public const ACTIVITY_TYPES = [
        'call' => ['label' => 'اتصال', 'icon' => 'phone'],
        'viewing' => ['label' => 'معاينة', 'icon' => 'eye'],
        'follow_up' => ['label' => 'متابعة عقد', 'icon' => 'file-text'],
        'collection' => ['label' => 'تحصيل', 'icon' => 'dollar-sign'],
        'visit' => ['label' => 'زيارة', 'icon' => 'map-pin'],
        'send_document' => ['label' => 'إرسال مستند', 'icon' => 'send'],
    ];

    public const TASK_RELATED = [
        'client' => ['label' => 'عميل'],
        'building' => ['label' => 'عقار'],
        'unit' => ['label' => 'وحدة'],
        'listing' => ['label' => 'عرض'],
        'contract' => ['label' => 'عقد'],
        'payment' => ['label' => 'دفعة'],
        'maintenance' => ['label' => 'صيانة'],
    ];

    public const TASK_STATUSES = [
        'new' => ['label' => 'جديدة', 'tone' => 'info'],
        'today' => ['label' => 'اليوم', 'tone' => 'primary'],
        'in_progress' => ['label' => 'قيد المتابعة', 'tone' => 'warning'],
        'waiting_client' => ['label' => 'بانتظار العميل', 'tone' => 'secondary'],
        'completed' => ['label' => 'مكتملة', 'tone' => 'success'],
    ];

    /** هل العقار وحيد الوحدة (فيلا، أرض، منشأة مستقلة)؟ */
    public static function isSingleUnit(?string $entityType): bool
    {
        return in_array($entityType, self::SINGLE_UNIT_ENTITY_TYPES, true);
    }

    /** الاستخدام الذي يفرضه نوع الوحدة، أو null إن صلح للاستخدامين. */
    public static function unitUsageFor(?string $unitType): ?string
    {
        return self::UNIT_TYPES[$unitType]['usage'] ?? null;
    }

    /**
     * أنواع الوحدة المسموحة لاستخدامٍ بعينه (مع ما يصلح للاستخدامين).
     *
     * @return array<string, array{label:string, usage:?string}>
     */
    public static function unitTypesFor(?string $usage): array
    {
        return array_filter(self::UNIT_TYPES, fn (array $meta) => $meta['usage'] === null || $meta['usage'] === $usage);
    }

    public static function unitTypeAllowed(?string $unitType, ?string $usage): bool
    {
        return isset(self::UNIT_TYPES[$unitType]) && in_array(self::UNIT_TYPES[$unitType]['usage'], [null, $usage], true);
    }

    public static function label(array $list, ?string $value, string $fallback = '—'): string
    {
        return $list[$value]['label'] ?? ($value ?: $fallback);
    }

    public static function tone(array $list, ?string $value): string
    {
        return $list[$value]['tone'] ?? 'secondary';
    }

    /** قاعدة تحقّقٍ جاهزة: `in:a,b,c` من مفاتيح القائمة. */
    public static function in(array $list): string
    {
        return 'in:'.implode(',', array_keys($list));
    }
}
