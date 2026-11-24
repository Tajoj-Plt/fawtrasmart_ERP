<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة المتجر الإلكتروني على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * الأسماء هنا مطابقة حرفياً لِـ `fawtra_dashboard/config/online_store_features.php`
 * (`permission_groups.*.permissions` و`limits`) — أي حرفٍ مختلف يجعل
 * `SubscriptionGate` تعامل المفتاح كميزةٍ مجهولة، والمجهول **مسموحٌ بلا حدّ**
 * (انظر تعليق [StockPlanFeatures] لنفس الفخّ).
 *
 * ─────────────────────── موارد مشتركة لا تُكرَّر هنا ───────────────────────
 *
 * العلامات التجارية والأصناف والمنتجات جداولٌ يتقاسمها المتجر مع المخزون
 * فعلياً (`brands`، `categories`، `products` — تحقّق من الموديلات وقت
 * الكتابة: `App\Models\Store\Brand`/`Category`/`Product` تُشير لنفس الجداول
 * التي تديرها Modules/Stock). فحدودها هي حدود [StockPlanFeatures] نفسها
 * (`LIMIT_BRANDS`, `LIMIT_CATEGORIES`, `LIMIT_PRODUCTS`) لا حدودٌ موازية —
 * إنشاء علامة من شاشة المتجر يستهلك السقف نفسه الذي يستهلكه المخزون.
 *
 * صلاحية الباقة **`online_store_catalog`/`online_store_products`** هنا تسأل
 * سؤالاً مختلفاً: «هل تملك المنشأة ميزة المتجر الإلكتروني أصلاً؟» — لا
 * «كم علامة/صنف/منتج تملك؟». فالمساران متراكبان لا متطابقان: مسارُ إنشاءٍ
 * في شاشة المتجر يحمل `plan:online_store_catalog` **و** `plan.limit` بمفتاح
 * Stock نفسه معاً.
 */
class OnlineStorePlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    public const DASHBOARD = 'online_store_dashboard';

    /** العلامات التجارية والأصناف — كتالوج واجهة المتجر. */
    public const CATALOG = 'online_store_catalog';

    /** واجهة المتجر لمنتجات المخزون: الإتاحة أونلاين، التمييز، التصدير. */
    public const PRODUCTS = 'online_store_products';

    /** الطلبات: تصفّحها ومتابعتها وإلغاؤها واستردادها. */
    public const ORDERS = 'online_store_orders';

    public const SHIPPING = 'online_store_shipping';

    public const COUPONS = 'online_store_coupons';

    /** البانرات وصفحات المتجر الثابتة. */
    public const CONTENT = 'online_store_content';

    /** تقييمات المنتجات وأسئلة العملاء. */
    public const FEEDBACK = 'online_store_feedback';

    public const CLIENTS = 'online_store_clients';

    /** قوالب البريد الإلكتروني وسجلّه. */
    public const EMAIL = 'online_store_email';

    /** إعدادات المتجر، بما فيها الدول والمدن المستعملة في الشحن. */
    public const SETTINGS = 'online_store_settings';

    public const REPORTS = 'online_store_reports';

    /**
     * كل صلاحيات باقة المتجر — تكفي واحدةٌ منها لفتح لوحة الوحدة وبند
     * قائمتها الجامع.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD,
            self::CATALOG,
            self::PRODUCTS,
            self::ORDERS,
            self::SHIPPING,
            self::COUPONS,
            self::CONTENT,
            self::FEEDBACK,
            self::CLIENTS,
            self::EMAIL,
            self::SETTINGS,
            self::REPORTS,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام (خاصّة بالمتجر وحده) ==================== */

    /** عدّادٌ شهري: الطلبات الواردة من واجهة المتجر — يُلزَم في `Api/StoreOrderController@store`. */
    public const LIMIT_ORDERS = 'online_orders';

    /** سقفٌ متزامن: طرق الشحن. */
    public const LIMIT_SHIPPING = 'store_shipping_methods';

    /** سقفٌ متزامن: كوبونات الخصم. */
    public const LIMIT_COUPONS = 'store_coupons';

    /** سقفٌ متزامن: بانرات المتجر. */
    public const LIMIT_CONTENT = 'store_banners';

    /**
     * الحدّ المرتبط بكل صلاحية باقة — لعرض الشارة في شاشة الميزة نفسها.
     *
     * `CATALOG`/`PRODUCTS` غائبتان عمداً: حدّهما حدُّ [StockPlanFeatures]
     * (`LIMIT_BRANDS`/`LIMIT_CATEGORIES`/`LIMIT_PRODUCTS`) لا حدٌّ هنا — من
     * أراد شارة الحدّ لهما يقرأ تلك الثوابت مباشرةً، لا هذه الدالّة.
     */
    public static function limitOf(string $permission): ?string
    {
        return [
            self::ORDERS => self::LIMIT_ORDERS,
            self::SHIPPING => self::LIMIT_SHIPPING,
            self::COUPONS => self::LIMIT_COUPONS,
            self::CONTENT => self::LIMIT_CONTENT,
        ][$permission] ?? null;
    }
}
