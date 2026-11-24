<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة المشتريات على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * ─────────────────────── لماذا وُجد هذا الملفّ ───────────────────────
 *
 * وحدةُ المشتريات كانت الوحيدةَ بين المخزون والمبيعات والمشتريات **بلا
 * حارس باقةٍ واحد**: مئةٌ وتسعة وأربعون مساراً تحمل `permission:` كلُّها،
 * ولا مسارَ واحدٌ يحمل `plan:` ولا `plan.limit:`. فمنشأةٌ لم تشترِ
 * المشتريات كانت تفتح فواتيرها وأوامرها ومورّديها كاملةً — الصلاحيةُ
 * وحدها كانت البوّابة، وهي تسأل «من أنت» لا «ماذا اشتريتَ».
 *
 * ─────────────────────── المفتاحان مختلفان ───────────────────────
 *
 * لفوترة خريطتان متقاربتا الأسماء يسهل خلطهما، وفي المشتريات يفترقان
 * بحرفٍ واحد:
 *
 *   `plan.permissions[]` ← تُسأل بـ`allows()`   — مثالها `purchases_invoices`
 *   `usage[].key`        ← تُسأل بـ`liveLimit()` — مثالها `purchase_invoices`
 *
 * «فواتير الشراء» صلاحيتُها `purchases_invoices` (بالسين) وحدُّها
 * `purchase_invoices` (بلا سين). وخلطُهما لا يُنتج عطلاً يُلاحَظ:
 * `SubscriptionGate` تعامل المفتاح المجهول «مسموحاً بلا حدّ»، فالخطأ
 * الإملائي حارسٌ صامتٌ لا يحرس. وقد وقع فعلاً: خمسةُ نداءات `allows()`
 * في متحكّمات هذه الوحدة تمرّر مفاتيح **استهلاكٍ** إلى خريطة
 * **الصلاحيات** فتعود `true` أبداً.
 *
 * ─────────────────────── ما لا تذكره الباقة ───────────────────────
 *
 * الكتالوج يعرف عشرَ صلاحياتٍ للمشتريات وأربعةَ حدودٍ فقط. فالمرتجعات
 * والإشعارات المدينة وسندات الصرف لها صلاحيةُ باقةٍ بلا سقف — تُحرَس
 * بـ`plan:` ولا `plan.limit:` لها. **ولا يُخترع لها مفتاح**: المخترَع
 * يُعامَل مسموحاً دائماً فيبدو حارساً وليس بشيء.
 *
 * كلُّ اسمٍ هنا مُتحقَّقٌ منه على لقطة الاشتراك الحيّة (٢٨‑٠٨‑٢٠٢٦):
 * عشرُ صلاحيات وأربعةُ حدود، بلا انحرافٍ في الاتجاهين.
 *
 * @see \App\Constants\planFeatures\SalesPlanFeatures النظير في المبيعات
 * @see \App\Constants\planFeatures\StockPlanFeatures الوحدة المرجعية
 * @see docs/Subscription_Plan_Enforcement_Guide.md
 */
class PurchasesPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    /* ── المستندات ── */

    /** فواتير الشراء. */
    public const INVOICES = 'purchases_invoices';

    /** أوامر الشراء — `purchasing_order_requests` في القاعدة. */
    public const ORDERS = 'purchases_orders';

    /** طلبات الشراء — مجلّد `ordersPurchase`، وهو الطلب لا الأمر. */
    public const ORDER_REQUESTS = 'purchases_order_requests';

    /** طلبات عروض الأسعار المرسَلة للمورّدين. */
    public const QUOTATIONS = 'purchases_quotations';

    /** عروض أسعار المورّدين الواردة ومقارنتها. */
    public const PRICE_QUOTATIONS = 'purchases_price_quotations';

    /** مرتجعات الشراء. */
    public const RETURNS = 'purchases_returns';

    /** الإشعارات المدينة للمورّدين. */
    public const DEBIT_NOTICES = 'purchases_debit_notices';

    /** سندات الصرف للمورّدين. */
    public const PAYMENTS = 'purchases_payments';

    /* ── البيانات والتقارير ── */

    /** المورّدون وأرصدتهم. */
    public const SUPPLIERS = 'purchases_suppliers';

    /** تقارير المشتريات كلّها. */
    public const REPORTS = 'purchases_reports';

    /**
     * كل صلاحيات باقة المشتريات — تكفي واحدةٌ منها لفتح لوحة الوحدة.
     *
     * الشرط «واحدةٌ على الأقل» لا «كلّها»: باقةٌ تشمل المورّدين وحدهم يجب
     * أن تفتح شاشتهم، وحارسُ كل شاشةٍ داخلَها يتكفّل بالباقي.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::INVOICES,
            self::ORDERS,
            self::ORDER_REQUESTS,
            self::QUOTATIONS,
            self::PRICE_QUOTATIONS,
            self::RETURNS,
            self::DEBIT_NOTICES,
            self::PAYMENTS,
            self::SUPPLIERS,
            self::REPORTS,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام ==================== */

    /** عدّادٌ شهري: فواتير الشراء. */
    public const LIMIT_INVOICES = 'purchase_invoices';

    /** عدّادٌ شهري: أوامر الشراء. */
    public const LIMIT_ORDERS = 'purchase_orders';

    /** عدّادٌ شهري: طلبات عروض الأسعار — مفتاحُها `quotations` لا `purchase_quotations`. */
    public const LIMIT_QUOTATIONS = 'quotations';

    /** سقفٌ متزامن: عدد المورّدين. */
    public const LIMIT_SUPPLIERS = 'suppliers';

    /**
     * الحدّ المرتبط بكل صلاحية باقة — لعرض الشارة في شاشة الميزة نفسها.
     *
     * الغائبُ عن هذه الخريطة صلاحيةٌ بلا سقف (المرتجعات والإشعارات المدينة
     * وسندات الصرف وطلبات الشراء وعروض المورّدين والتقارير) — لا نقصٌ
     * يُستدرَك، بل هو ما يشحنه الكتالوج.
     */
    public static function limitOf(string $permission): ?string
    {
        return [
            self::INVOICES => self::LIMIT_INVOICES,
            self::ORDERS => self::LIMIT_ORDERS,
            self::QUOTATIONS => self::LIMIT_QUOTATIONS,
            self::SUPPLIERS => self::LIMIT_SUPPLIERS,
        ][$permission] ?? null;
    }
}
