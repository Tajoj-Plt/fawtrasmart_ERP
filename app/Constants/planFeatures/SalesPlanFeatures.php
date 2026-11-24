<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة المبيعات على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * ─────────────────────── طبقتان لا واحدة ───────────────────────
 *
 * صلاحيات النظام ([SalesPermissions]، بنمط `sales.invoices.view`) تقول **من**
 * يفعل ماذا داخل المنشأة. وصلاحيات الباقة هنا (بنمط `invoices`) تقول **ماذا
 * اشترت المنشأة أصلاً**. لا يتداخلان ولا يُغني أحدهما عن الآخر: مديرٌ يملك
 * كل صلاحيات النظام لا يفتح شاشةً لم تُشترَ، ومنشأةٌ اشترت الميزة لا تفتحها
 * لموظفٍ بلا صلاحية. وترقيةُ الباقة يجب ألّا تمرّ بتعديل صلاحيات المستخدمين.
 *
 * ─────────────────────── المفتاحان مختلفان ───────────────────────
 *
 * لفوترة خريطتان متقاربتا الأسماء يسهل خلطهما:
 *
 *   `plan.permissions[]` ← تُسأل بـ`allows()`/`permits()`  — مثالها `invoices`
 *   `usage[].key`        ← تُسأل بـ`liveLimit()`/`checkLive()` — مثالها `sales_invoices`
 *
 * «فواتير المبيعات» صلاحيتُها `invoices` وحدُّها `sales_invoices` — اسمان
 * لشيءٍ واحد. ولذلك فُصل الثابتان هنا ولا يُعاد استعمال أحدهما مكان الآخر.
 *
 * وخلطُهما لا يُنتج عطلاً يُلاحَظ: `SubscriptionGate` تعامل المفتاح المجهول
 * «مسموحاً بلا حدّ» — وهو الصواب لكتالوجٍ قد يسبق النظام أو يتأخّر عنه —
 * فالخطأ الإملائي هنا حارسٌ صامتٌ لا يحرس. وقد وقع فعلاً في وحدة المشتريات:
 * خمسةُ نداءات `allows()` تمرّر مفاتيح **استهلاكٍ** إلى خريطة **الصلاحيات**
 * فتعود `true` أبداً.
 *
 * ─────────────────────── ما لا تذكره الباقة ───────────────────────
 *
 * ثلاثُ شاشاتٍ في الوحدة بلا مفتاحٍ في الكتالوج: «الفواتير المتجدّدة» لها
 * صلاحيةُ باقةٍ (`revolving_invoices`) ولا حدَّ لها، و«الإقفال اليومي»
 * و«التحليل المالي» لا مفتاحَ لهما إطلاقاً. فالأوّل يُحرَس بصلاحيته بلا
 * سقف، والأخيران يُلحقان بأقرب ميزةٍ يتبعانها فعلاً (`dashboard`) — ولا
 * يُخترع لهما مفتاح، فالمخترع يُعامَل مسموحاً دائماً ويبدو حارساً وليس بشيء.
 */
class SalesPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    /* ── المستندات ── */

    public const INVOICES = 'invoices';

    public const QUOTES = 'quotes';

    public const RETURN_INVOICES = 'return_invoices';

    public const CREDIT_NOTES = 'credit_notes';

    public const PERIODIC_INVOICES = 'periodic_invoices';

    public const REVOLVING_INVOICES = 'revolving_invoices';

    /** مطبوعةٌ لا شاشة: الفاتورة الضريبية طبعةٌ ثانية للفاتورة نفسها. */
    public const INVOICE_TAX_PRINT = 'invoice_tax_print';

    /** الملصقات وبوليصة الشحن. */
    public const INVOICE_LABELS = 'invoice_labels';

    /* ── التحصيل ── */

    public const PAYMENTS = 'payments';

    public const BULK_PAYMENTS = 'bulk_payments';

    public const INSTALLMENTS = 'installments';

    /* ── الاستبدال ── */

    public const PRODUCT_EXCHANGES = 'product_exchanges';

    public const EXCHANGE_REASONS = 'exchange_reasons';

    public const EXCHANGE_LIMITS = 'exchange_limits';

    /* ── العروض والأهداف ── */

    public const OFFERS = 'offers';

    public const GIFT_OFFERS = 'gift_offers';

    public const COLLECTIONS_TARGETS = 'collections_targets';

    public const COLLECTIONS_SETTLE = 'collections_settle';

    public const TARGET_SETTINGS = 'target_settings';

    /* ── الإعدادات ── */

    public const INVOICE_TEMPLATES = 'invoice_templates';

    public const TEMPLATE_TEST_PRINT = 'template_test_print';

    public const SHIPPING_OPTIONS = 'shipping_options';

    public const ORDER_SOURCES = 'order_sources';

    public const NOTIFICATIONS = 'notifications';

    /* ── اللوحة والتقارير ── */

    public const DASHBOARD = 'dashboard';

    public const REPORTS = 'reports';

    public const REPORTS_PROFITS = 'reports_profits';

    public const REPORTS_BY_EMPLOYEE = 'reports_by_employee';

    public const REPORTS_BY_CUSTOMER = 'reports_by_customer';

    public const REPORTS_BY_PRODUCT = 'reports_by_product';

    public const REPORTS_COLLECTIONS = 'reports_collections';

    public const REPORTS_UNIFIED_CUSTOMER = 'reports_unified_customer';

    public const REPORTS_EXPORT = 'reports_export';

    /**
     * كل صلاحيات باقة المبيعات — تكفي واحدةٌ منها لفتح لوحة الوحدة وبند
     * قائمتها الجامع.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::INVOICES,
            self::QUOTES,
            self::RETURN_INVOICES,
            self::CREDIT_NOTES,
            self::PERIODIC_INVOICES,
            self::REVOLVING_INVOICES,
            self::INVOICE_TAX_PRINT,
            self::INVOICE_LABELS,
            self::PAYMENTS,
            self::BULK_PAYMENTS,
            self::INSTALLMENTS,
            self::PRODUCT_EXCHANGES,
            self::EXCHANGE_REASONS,
            self::EXCHANGE_LIMITS,
            self::OFFERS,
            self::GIFT_OFFERS,
            self::COLLECTIONS_TARGETS,
            self::COLLECTIONS_SETTLE,
            self::TARGET_SETTINGS,
            self::INVOICE_TEMPLATES,
            self::TEMPLATE_TEST_PRINT,
            self::SHIPPING_OPTIONS,
            self::ORDER_SOURCES,
            self::NOTIFICATIONS,
            self::DASHBOARD,
            self::REPORTS,
            self::REPORTS_PROFITS,
            self::REPORTS_BY_EMPLOYEE,
            self::REPORTS_BY_CUSTOMER,
            self::REPORTS_BY_PRODUCT,
            self::REPORTS_COLLECTIONS,
            self::REPORTS_UNIFIED_CUSTOMER,
            self::REPORTS_EXPORT,
        ];
    }

    /**
     * صلاحيات التقارير وحدها — لإخفاء قسم «تقارير المبيعات» كلّه.
     *
     * @return array<int, string>
     */
    public static function reports(): array
    {
        return [
            self::REPORTS,
            self::REPORTS_PROFITS,
            self::REPORTS_BY_EMPLOYEE,
            self::REPORTS_BY_CUSTOMER,
            self::REPORTS_BY_PRODUCT,
            self::REPORTS_COLLECTIONS,
            self::REPORTS_UNIFIED_CUSTOMER,
            self::REPORTS_EXPORT,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام ==================== */

    /** عدّادٌ شهري: فواتير البيع (`normal`/`pos`/`online`). */
    public const LIMIT_INVOICES = 'sales_invoices';

    /** عدّادٌ شهري: عروض الأسعار. */
    public const LIMIT_QUOTES = 'quotes';

    /** عدّادٌ شهري: فواتير المرتجعات (`type = returned`). */
    public const LIMIT_RETURN_INVOICES = 'return_invoices';

    /** عدّادٌ شهري: الإشعارات الدائنة. */
    public const LIMIT_CREDIT_NOTES = 'credit_notes';

    /** عدّادٌ شهري: سندات قبض العملاء. */
    public const LIMIT_PAYMENTS = 'sales_payments';

    /** عدّادٌ شهري: أوامر التوريد. */

    /** عدّادٌ شهري: عمليات استبدال المنتجات. */
    public const LIMIT_PRODUCT_EXCHANGES = 'product_exchanges';

    /**
     * عدّادٌ شهري: إرسال المستندات بالبريد.
     *
     * **لا يُقاس محلياً**: الإرسال يمرّ بـ`Mail::send` مباشرةً بلا صفٍّ يُعَدّ،
     * فلا مدخلَ له في `UsageReporter::catalog()` ولا يصلح للمنع. يبقى الثابت
     * هنا ليُعرف أنّ الميزة موجودةٌ في الباقة وأنّ غيابَ حراستها مقصود.
     */
    public const LIMIT_DOCUMENT_EMAILS = 'document_emails';

    /** عدّادٌ شهري: الإشعارات الداخلية. */
    public const LIMIT_NOTIFICATIONS = 'internal_notifications';

    /** سقفٌ متزامن: الفواتير الدورية النشطة. */
    public const LIMIT_PERIODIC_INVOICES = 'periodic_invoices';

    /** سقفٌ متزامن: اتفاقيات التقسيط (مميَّزةً بـ`invoice_id`). */
    public const LIMIT_INSTALLMENT_PLANS = 'installment_plans';

    /** سقفٌ متزامن: العروض الترويجية النشطة. */
    public const LIMIT_OFFERS = 'offers';

    /** سقفٌ متزامن: عروض الهدايا السارية. */
    public const LIMIT_GIFT_OFFERS = 'gift_offers';

    /** سقفٌ متزامن: أهداف التحصيل النشطة. */
    public const LIMIT_COLLECTIONS_TARGETS = 'collections_targets';

    /** سقفٌ متزامن: تصاميم الفواتير. */
    public const LIMIT_INVOICE_TEMPLATES = 'invoice_templates';

    /** سقفٌ متزامن: خيارات الشحن النشطة. */
    public const LIMIT_SHIPPING_OPTIONS = 'shipping_options';

    /** سقفٌ متزامن: مصادر الطلب النشطة. */
    public const LIMIT_ORDER_SOURCES = 'order_sources';

    /** سقفٌ متزامن: أسباب الاستبدال النشطة. */
    public const LIMIT_EXCHANGE_REASONS = 'exchange_reasons';

    /** سقفٌ متزامن: مقاعد مستخدمي المبيعات — تُقاس بالصلاحية لا بعمود الدور. */
    public const LIMIT_USERS = 'sales_users';

    /**
     * الحدّ المرتبط بكل صلاحية باقة — لعرض الشارة في شاشة الميزة نفسها.
     *
     * الغائبُ عن هذه الخريطة صلاحيةٌ بلا سقف (كالفواتير المتجدّدة وطباعة
     * الفاتورة الضريبية) — لا نقصٌ يُستدرَك.
     */
    public static function limitOf(string $permission): ?string
    {
        return [
            self::INVOICES => self::LIMIT_INVOICES,
            self::QUOTES => self::LIMIT_QUOTES,
            self::RETURN_INVOICES => self::LIMIT_RETURN_INVOICES,
            self::CREDIT_NOTES => self::LIMIT_CREDIT_NOTES,
            self::PAYMENTS => self::LIMIT_PAYMENTS,
            self::PRODUCT_EXCHANGES => self::LIMIT_PRODUCT_EXCHANGES,
            self::NOTIFICATIONS => self::LIMIT_NOTIFICATIONS,
            self::PERIODIC_INVOICES => self::LIMIT_PERIODIC_INVOICES,
            self::INSTALLMENTS => self::LIMIT_INSTALLMENT_PLANS,
            self::OFFERS => self::LIMIT_OFFERS,
            self::GIFT_OFFERS => self::LIMIT_GIFT_OFFERS,
            self::COLLECTIONS_TARGETS => self::LIMIT_COLLECTIONS_TARGETS,
            self::INVOICE_TEMPLATES => self::LIMIT_INVOICE_TEMPLATES,
            self::SHIPPING_OPTIONS => self::LIMIT_SHIPPING_OPTIONS,
            self::ORDER_SOURCES => self::LIMIT_ORDER_SOURCES,
            self::EXCHANGE_REASONS => self::LIMIT_EXCHANGE_REASONS,
        ][$permission] ?? null;
    }
}
