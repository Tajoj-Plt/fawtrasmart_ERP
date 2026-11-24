<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * قالب رسالة نصية — نصّه يُعدَّل من الشاشة لا من الكود.
 */
class SmsTemplate extends Model
{
    protected $fillable = [
        'code', 'name', 'category', 'description', 'event',
        'body', 'variables', 'is_active', 'is_system', 'sort_order',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * تصنيفات القوالب: الرمز ← [التسمية، الأيقونة].
     *
     * التصنيف ليس زينة — الكتالوج يتجاوز خمسين قالباً، وقائمة مسطّحة
     * بهذا الطول لا يُعثر فيها على قالب.
     */
    public const CATEGORIES = [
        'sales' => ['المبيعات والفواتير', 'file-text'],
        'payments' => ['المدفوعات والتحصيل', 'dollar-sign'],
        'purchases' => ['المشتريات والموردون', 'shopping-cart'],
        'clients' => ['العملاء', 'users'],
        'appointments' => ['المواعيد والزيارات', 'calendar'],
        'stock' => ['المخزون والتسليم', 'package'],
        'hr' => ['الموارد البشرية', 'briefcase'],
        'loyalty' => ['الولاء والنقاط', 'award'],
        'memberships' => ['العضويات والاشتراكات', 'credit-card'],
        'rentals' => ['الإيجارات والحجوزات', 'key'],
        'store' => ['المتجر والشحن', 'truck'],
        'security' => ['الأمان والتحقق', 'shield'],
        'marketing' => ['التسويق والمناسبات', 'speaker'],
        'system' => ['النظام والتنبيهات', 'settings'],
        'general' => ['عام', 'message-square'],
    ];

    /**
     * الأحداث التي يمكن ربط قالب بها: الحدث ← [التسمية، التصنيف].
     *
     * كانت خمسة أحداث فقط، فالقوالب التي لا حدث لها تُجبَر على «إرسال
     * يدوي» — أي قالبٌ موجود لا يستطيع شيء في النظام إطلاقه.
     */
    public const EVENT_CATALOG = [
        'manual' => ['إرسال يدوي', 'general'],

        // ============ المبيعات ============
        'invoice.created' => ['عند إصدار فاتورة', 'sales'],
        'invoice.updated' => ['عند تعديل فاتورة', 'sales'],
        'invoice.cancelled' => ['عند إلغاء فاتورة', 'sales'],
        'invoice.due' => ['عند اقتراب الاستحقاق', 'sales'],
        'invoice.overdue' => ['عند تأخّر السداد', 'sales'],
        'invoice.settled' => ['عند سداد الفاتورة كاملة', 'sales'],
        'quote.sent' => ['عند إرسال عرض سعر', 'sales'],
        'quote.accepted' => ['عند قبول عرض السعر', 'sales'],
        'quote.expiring' => ['عند قرب انتهاء عرض السعر', 'sales'],
        'order.created' => ['عند إنشاء طلب بيع', 'sales'],
        'return.created' => ['عند تسجيل مرتجع مبيعات', 'sales'],

        // ============ المدفوعات ============
        'payment.received' => ['عند استلام دفعة', 'payments'],
        'payment.failed' => ['عند فشل عملية دفع', 'payments'],
        'payment.refunded' => ['عند ردّ مبلغ', 'payments'],
        'receipt.issued' => ['عند إصدار سند قبض', 'payments'],
        'statement.ready' => ['عند جاهزية كشف الحساب', 'payments'],
        'balance.due' => ['عند وجود رصيد مستحق', 'payments'],
        'credit.limit' => ['عند بلوغ حدّ الائتمان', 'payments'],

        // ============ المشتريات ============
        'purchase.ordered' => ['عند إصدار أمر شراء', 'purchases'],
        'purchase.received' => ['عند استلام بضاعة', 'purchases'],
        'supplier.payment' => ['عند دفع مستحقات مورّد', 'purchases'],

        // ============ العملاء ============
        'customer.created' => ['عند إضافة عميل', 'clients'],
        'customer.reactivated' => ['عند إعادة تنشيط عميل', 'clients'],
        'customer.inactive' => ['عند خمول العميل مدّة', 'clients'],
        'customer.feedback' => ['عند طلب تقييم', 'clients'],

        // ============ المواعيد والزيارات ============
        'appointment.booked' => ['عند حجز موعد', 'appointments'],
        'appointment.reminder' => ['قبل الموعد بمدّة', 'appointments'],
        'appointment.rescheduled' => ['عند تغيير الموعد', 'appointments'],
        'appointment.cancelled' => ['عند إلغاء الموعد', 'appointments'],
        'visit.scheduled' => ['عند جدولة زيارة', 'appointments'],
        'visit.completed' => ['عند إتمام الزيارة', 'appointments'],

        // ============ المخزون والتسليم ============
        'delivery.ready' => ['عند جاهزية الطلب', 'stock'],
        'delivery.dispatched' => ['عند خروج الشحنة', 'stock'],
        'delivery.delivered' => ['عند تسليم الطلب', 'stock'],
        'stock.available' => ['عند توفّر صنف مطلوب', 'stock'],

        // ============ الموارد البشرية ============
        'employee.salary' => ['عند صرف الراتب', 'hr'],
        'employee.advance' => ['عند البتّ في طلب سلفة', 'hr'],
        'employee.contract' => ['عند قرب انتهاء العقد', 'hr'],
        'employee.attendance' => ['عند رصد غياب أو تأخّر', 'hr'],

        // ============ الولاء ============
        'points.earned' => ['عند اكتساب نقاط', 'loyalty'],
        'points.redeemed' => ['عند استبدال نقاط', 'loyalty'],
        'points.expiring' => ['عند قرب انتهاء النقاط', 'loyalty'],
        'tier.upgraded' => ['عند الترقّي لفئة أعلى', 'loyalty'],

        // ============ العضويات ============
        'membership.activated' => ['عند تفعيل عضوية', 'memberships'],
        'membership.expiring' => ['عند قرب انتهاء العضوية', 'memberships'],
        'membership.expired' => ['عند انتهاء العضوية', 'memberships'],
        'subscription.renewed' => ['عند تجديد الاشتراك', 'memberships'],

        // ============ الإيجارات والحجوزات ============
        'rental.started' => ['عند بدء عقد إيجار', 'rentals'],
        'rental.due' => ['عند استحقاق دفعة إيجار', 'rentals'],
        'rental.ending' => ['عند قرب انتهاء العقد', 'rentals'],
        'reservation.confirmed' => ['عند تأكيد حجز', 'rentals'],

        // ============ المتجر ============
        'store.order_placed' => ['عند طلب من المتجر', 'store'],
        'store.order_shipped' => ['عند شحن الطلب', 'store'],
        'store.cart_abandoned' => ['عند ترك السلة', 'store'],

        // ============ الأمان ============
        'auth.otp' => ['عند طلب رمز تحقّق', 'security'],
        'auth.password_reset' => ['عند طلب استعادة كلمة المرور', 'security'],
        'auth.new_login' => ['عند دخول من جهاز جديد', 'security'],

        // ============ التسويق ============
        'marketing.campaign' => ['ضمن حملة تسويقية', 'marketing'],
        'marketing.occasion' => ['في مناسبة أو عيد', 'marketing'],

        // ============ النظام ============
        'system.alert' => ['عند تنبيه نظامي', 'system'],
        'system.test' => ['عند فحص الإعدادات', 'system'],
    ];

    /**
     * الأحداث بصيغة الثابت القديم (الحدث ← التسمية).
     *
     * أُبقي لأن الكود القائم يقرؤه، والتغيير إلى بنية مركّبة كان سيكسر
     * كل مستدعٍ دون داعٍ.
     */
    public const EVENTS = [
        'manual' => 'إرسال يدوي',
        'invoice.created' => 'عند إصدار فاتورة',
        'payment.received' => 'عند استلام دفعة',
        'invoice.due' => 'عند اقتراب الاستحقاق',
        'customer.created' => 'عند إضافة عميل',
    ];

    // ==================== الكتالوج ====================

    /** كل الأحداث المعروفة — مفاتيحها هي المقبولة عند الحفظ */
    public static function eventKeys(): array
    {
        return array_keys(self::EVENT_CATALOG);
    }

    /**
     * الأحداث بأسمائها المترجَمة.
     *
     * النقطة في اسم الحدث مسارٌ داخل المصفوفة عند `__()`، فتُحوَّل إلى
     * شُرطة سفلية.
     */
    public static function events(): array
    {
        $out = [];

        foreach (self::EVENT_CATALOG as $event => [$fallback, $category]) {
            $out[$event] = self::tr('events.'.str_replace('.', '_', $event), $fallback);
        }

        return $out;
    }

    /**
     * الأحداث مجمَّعة تحت تصنيفاتها — لقوائم `optgroup`.
     *
     * @return array<string, array{label: string, events: array<string, string>}>
     */
    public static function eventGroups(): array
    {
        $groups = [];

        foreach (self::EVENT_CATALOG as $event => [$fallback, $category]) {
            $groups[$category]['label'] ??= self::categoryLabel($category);
            $groups[$category]['events'][$event] = self::tr('events.'.str_replace('.', '_', $event), $fallback);
        }

        return $groups;
    }

    /** التصنيفات بأسمائها المترجَمة: الرمز ← التسمية */
    public static function categories(): array
    {
        $out = [];

        foreach (self::CATEGORIES as $key => [$label, $icon]) {
            $out[$key] = self::tr('categories.'.$key, $label);
        }

        return $out;
    }

    public static function categoryLabel(?string $key): string
    {
        $key = $key ?: 'general';

        return self::tr('categories.'.$key, self::CATEGORIES[$key][0] ?? $key);
    }

    public static function categoryIcon(?string $key): string
    {
        return self::CATEGORIES[$key ?: 'general'][1] ?? 'message-square';
    }

    /** تصنيف الحدث — يُقترح على القالب حين لا يُختار تصنيف */
    public static function categoryOfEvent(?string $event): string
    {
        return self::EVENT_CATALOG[$event][1] ?? 'general';
    }

    protected static function tr(string $key, string $fallback): string
    {
        $full = 'settings.sms_templates.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    // ==================== الاستعلام ====================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /** القالب المفعَّل لحدث معيّن — أوّلُه ترتيباً حين تعدّدت القوالب */
    public static function forEvent(string $event): ?self
    {
        return static::active()->where('event', $event)->orderBy('sort_order')->first();
    }

    // ==================== النصّ ====================

    /**
     * يعبّئ متغيّرات القالب.
     *
     * المتغيّر الذي لا قيمة له يُستبدل بفراغ لا يُترك ظاهراً — وصول رسالة
     * فيها `{customer}` إلى العميل أسوأ من وصولها ناقصة.
     *
     * @param  array<string, string|int|null>  $data
     */
    public function render(array $data = []): string
    {
        $body = (string) $this->body;

        foreach ($data as $key => $value) {
            $body = str_replace('{'.$key.'}', (string) $value, $body);
        }

        return trim((string) preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '', $body));
    }

    /** المتغيّرات المكتوبة فعلاً داخل النص */
    public function detectedVariables(): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', (string) $this->body, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * عدد الرسائل التي ستُحتسب: العربية تُرسل بترميز Unicode فحدّها 70 حرفاً.
     */
    public function segments(?string $body = null): int
    {
        return static::segmentsOf($body ?? (string) $this->body);
    }

    /**
     * حساب الأجزاء لأي نصّ — يستعمله `SmsGateway` قبل الإرسال أيضاً.
     *
     * `$force` يفرض الترميز حين يضبطه المستخدم في الإعدادات: بوابة
     * تُرسل كل شيء Unicode تُحاسِب النصّ الإنجليزي على 70 حرفاً لا 160.
     */
    public static function segmentsOf(string $body, string $force = 'auto'): int
    {
        $length = mb_strlen($body);

        if ($length === 0) {
            return 0;
        }

        $unicode = match ($force) {
            'unicode' => true,
            'gsm' => false,
            default => (bool) preg_match('/[^\x00-\x7F]/u', $body),
        };

        $limit = $unicode ? 70 : 160;
        $multipart = $unicode ? 67 : 153;

        return $length <= $limit ? 1 : (int) ceil($length / $multipart);
    }
}
