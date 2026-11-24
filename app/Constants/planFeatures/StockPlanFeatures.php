<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة المخزون على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * ─────────────────────── طبقتان لا واحدة ───────────────────────
 *
 * صلاحيات النظام ([StockPermissions]، بنمط `stock.products.view`) تقول
 * **من** يفعل ماذا داخل المنشأة. وصلاحيات الباقة هنا (بنمط `stock_products`)
 * تقول **ماذا اشترت المنشأة أصلاً**. وهما لا يتداخلان ولا يُغني أحدهما عن
 * الآخر: مديرٌ يملك كل صلاحيات النظام لا يفتح شاشةً لم تُشترَ، ومنشأةٌ
 * اشترت الميزة لا تفتحها لموظفٍ بلا صلاحية.
 *
 * فلا يُعدَّل هنا شيءٌ من نظام الصلاحيات، ولا يُقرأ من هناك شيء.
 *
 * ─────────────────────── من أين هذه المفاتيح؟ ───────────────────────
 *
 * من لقطة الاشتراك نفسها لا من اجتهاد: `plan.permissions` تحمل مفاتيح
 * الصلاحيات، و`usage[].key` في القسم `stock` تحمل مفاتيح الحدود. وأي حرفٍ
 * مختلف عمّا ترسله فوترة يجعل `SubscriptionGate` تعامل المفتاح كميزةٍ
 * مجهولة — والمجهول **مسموح** بلا حدّ (وهو الصواب لكتالوجٍ قد يسبق النظام)،
 * فالخطأ الإملائي هنا لا يُنتج عطلاً يُلاحَظ بل ثغرةً صامتة.
 *
 * ─────────────────────── ما لا تذكره الباقة ───────────────────────
 *
 * الوحدات (`units`) ومجموعات المنتجات وإعدادات الباركود لا مفاتيح لها في
 * كتالوج فوترة. فهي تُلحق بأقرب ميزةٍ تتبعها فعلاً في الشاشات — الوحدة صفةٌ
 * للمنتج، ومجموعة المنتجات تصنيفٌ — لا تُترك بلا حراسة ولا يُخترع لها مفتاح:
 * مفتاحٌ مخترع لا يعرفه الكتالوج يُعامل «مسموحاً» دائماً، فيبدو حارساً وهو
 * ليس بشيء.
 */
class StockPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    public const PRODUCTS = 'stock_products';

    public const CATEGORIES = 'stock_categories';

    public const BRANDS = 'stock_brands';

    public const PRICE_LISTS = 'stock_price_lists';

    public const STOREHOUSES = 'stock_storehouses';

    public const WAREHOUSE_PERMITS = 'stock_warehouse_permits';

    public const INVENTORY_ADJUSTMENT = 'stock_inventory_adjustment';

    public const MANUFACTURING = 'stock_manufacturing';

    public const BOM = 'stock_bom';

    public const WORKSTATIONS = 'stock_workstations';

    public const REPORTS = 'stock_reports';

    /**
     * كل صلاحيات باقة المخزون — يكفي واحدةٌ منها لفتح لوحة الوحدة وبند
     * قائمتها الجامع.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PRODUCTS,
            self::CATEGORIES,
            self::BRANDS,
            self::PRICE_LISTS,
            self::STOREHOUSES,
            self::WAREHOUSE_PERMITS,
            self::INVENTORY_ADJUSTMENT,
            self::MANUFACTURING,
            self::BOM,
            self::WORKSTATIONS,
            self::REPORTS,
        ];
    }

    /**
     * ما تشمله وحدة التصنيع — مقابلُ [self::stockOnly()] من الجهة الأخرى.
     *
     * `stockOnly()` كانت موجودةً وحدها: تُخرج التصنيع من قائمة المخزون ولا
     * تقول ما يدخل فيه. فحُرست وحدةُ التصنيع بـ`stock_manufacturing` وحدها،
     * وهي مفتاحُ **أوامر التصنيع** لا الوحدة كلِّها — فباقةٌ تشمل قوائم
     * المواد أو محطّات العمل دون أوامر التصنيع تُردّ عن الوحدة بأسرها،
     * ومعها الشاشتان اللتان اشترتهما فعلاً.
     *
     * والتقارير معها: هي `stock_reports` في الكتالوج — لا مفتاحَ تقاريرَ
     * خاصّاً بالتصنيع — وشاشاتُ التقارير في المشروع كلّها تُحرَس به.
     *
     * @return array<int, string>
     */
    public static function manufacturingOnly(): array
    {
        return [
            self::MANUFACTURING,
            self::BOM,
            self::WORKSTATIONS,
            self::REPORTS,
        ];
    }

    /**
     * ما تشمله وحدة المخزون وحدها — بلا التصنيع، فقد صار وحدةً مستقلّة لها
     * شريطها ولوحتها. تُستعمل لإخفاء قسم المخزون في الشريط الجانبي كلّه.
     *
     * @return array<int, string>
     */
    public static function stockOnly(): array
    {
        return [
            self::PRODUCTS,
            self::CATEGORIES,
            self::BRANDS,
            self::PRICE_LISTS,
            self::STOREHOUSES,
            self::WAREHOUSE_PERMITS,
            self::INVENTORY_ADJUSTMENT,
            self::REPORTS,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام ==================== */

    /** سقفٌ متزامن: عدد المنتجات والخدمات الموجودة. */
    public const LIMIT_PRODUCTS = 'products';

    /** سقفٌ متزامن: عدد المستودعات. */
    public const LIMIT_STOREHOUSES = 'storehouses';

    /** سقفٌ متزامن: عدد قوائم الأسعار. */
    public const LIMIT_PRICE_LISTS = 'price_lists';

    /** سقفٌ متزامن: عدد الأصناف والتصنيفات. */
    public const LIMIT_CATEGORIES = 'product_categories';

    /** سقفٌ متزامن: عدد العلامات التجارية. */
    public const LIMIT_BRANDS = 'brands';

    /** عدّادٌ شهري: أذونات المخزن المُنشأة. */
    public const LIMIT_WAREHOUSE_PERMITS = 'warehouse_permits';

    /** عدّادٌ شهري: عمليات الجرد وتعديل الكميات. */
    public const LIMIT_ADJUSTMENTS = 'inventory_adjustments';

    /*
     * ─────────────────── حدود التصنيع الثلاثة ───────────────────
     *
     * **كانت تُقاس وتُرفَع ولا تُلزَم.**
     *
     * [\App\Services\Fawtra\UsageReporter] يعرّف الثلاثة في القسم `stock`
     * منذ البداية — يعدّها من `manufactur_orders` و`production_materials`
     * و`work_stations` ويرفعها إلى فوترة — ويقول في تعليقه صراحةً: «أمّا
     * إلزام حدودها في لحظة الإنشاء فموضعُه متحكّمات تلك الوحدة». ولم يكن
     * لها مقابلٌ هنا، فلا مسارَ في وحدة التصنيع يحمل `plan.limit`.
     *
     * ونتيجةُ ذلك أسوأ من غياب الحدّ: فوترة **ترى** الاستهلاك يتجاوز السقف
     * (لأننا نرفعه)، والنظام **يسمح** به (لأننا لا نلزمه). فتظهر المنشأة
     * متجاوزةً حدَّها في لوحة فوترة بينما لم يمنعها شيءٌ من التجاوز، ولا
     * سبيل لإرجاع ما أُنشئ.
     *
     * والأسماء هي مفاتيح فوترة نفسها كما تصل في `usage[].key` — لا أسماءٌ
     * من عندنا: مفتاحٌ مختلفٌ بحرف يجعل `SubscriptionGate::limit()` تعامله
     * «ميزةً غير متابَعة» فيصير بلا حدّ، وهي ثغرةٌ لا تُنتج خطأً يُلاحَظ.
     */

    /** عدّادٌ شهري: أوامر التصنيع المُنشأة. */
    public const LIMIT_MANUFACTURING_ORDERS = 'manufacturing_orders';

    /** سقفٌ متزامن: عدد قوائم مواد الإنتاج (BOM). */
    public const LIMIT_BOM_LISTS = 'bom_lists';

    /** سقفٌ متزامن: عدد محطّات العمل. */
    public const LIMIT_WORKSTATIONS = 'workstations';

    /**
     * الحدّ المرتبط بكل صلاحية باقة — لعرض الشارة في شاشة الميزة نفسها.
     *
     * @return array<string, string>
     */
    public static function limitOf(string $permission): ?string
    {
        return [
            self::PRODUCTS => self::LIMIT_PRODUCTS,
            self::CATEGORIES => self::LIMIT_CATEGORIES,
            self::BRANDS => self::LIMIT_BRANDS,
            self::PRICE_LISTS => self::LIMIT_PRICE_LISTS,
            self::STOREHOUSES => self::LIMIT_STOREHOUSES,
            self::WAREHOUSE_PERMITS => self::LIMIT_WAREHOUSE_PERMITS,
            self::INVENTORY_ADJUSTMENT => self::LIMIT_ADJUSTMENTS,
            self::MANUFACTURING => self::LIMIT_MANUFACTURING_ORDERS,
            self::BOM => self::LIMIT_BOM_LISTS,
            self::WORKSTATIONS => self::LIMIT_WORKSTATIONS,
        ][$permission] ?? null;
    }
}
