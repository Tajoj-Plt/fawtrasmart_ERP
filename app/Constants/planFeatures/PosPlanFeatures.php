<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة نقاط البيع على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * ─────────────────────── لماذا لم تكن لها كتالوج؟ ───────────────────────
 *
 * `app/Constants/planFeatures/` فيها كتالوجات المبيعات والمخزون والحسابات
 * والفروع والموارد البشرية — **ولا شيء لنقاط البيع**. والأثر أن الوحدة
 * كلّها كانت خارج التسعير: عدد أجهزة الكاشير بلا حدّ، والجلسات المتزامنة
 * بلا حدّ، وتقارير الربحية مفتوحةٌ لكل باقة.
 *
 * والشيءُ الوحيد الذي كان يُحسب أن فاتورة `pos` تُعدّ ضمن سقف فواتير
 * المبيعات المشترك — أي أن نقاط البيع كانت تستهلك حدّ وحدةٍ أخرى ولا تحمل
 * حدّاً لنفسها.
 *
 * ─────────────────────── طبقتان لا واحدة ───────────────────────
 *
 * صلاحيات النظام ([PosPermissions]، بنمط `pos.sessions.suspend`) تقول **من**
 * يفعل ماذا داخل المنشأة. وصلاحيات الباقة هنا (بنمط `pos_terminal`) تقول
 * **ماذا اشترت المنشأة أصلاً**. كاشيرٌ يملك كل صلاحيات النظام لا يفتح شاشةً
 * لم تُشترَ، ومنشأةٌ اشترت الميزة لا تفتحها لموظفٍ بلا صلاحية.
 *
 * ولذلك مفاتيحُ الباقة **أخشن**: `pos_returns` واحدةٌ تقابل ثلاث صلاحيات
 * نظام (`pos.returns.create` و`.approve` و`.cash_refund`) — فالعميل يشتري
 * «المرتجعات» لا يشتري «اعتماد المرتجع».
 *
 * ─────────────────────── المفتاح المجهول مسموح ───────────────────────
 *
 * `SubscriptionGate` تعامل المفتاح الذي لا يعرفه كتالوج فوترة على أنه
 * **مسموح بلا حدّ** — وهو الصواب لكتالوجٍ قد يسبق النظام، لكنه يعني أن
 * الخطأ الإملائي هنا لا يُنتج عطلاً يُلاحَظ بل حارساً صامتاً لا يحرس.
 *
 * فالأسماءُ هنا مطابقةٌ حرفياً لـ`config/pos_features.php` في لوحة فوترة.
 */
class PosPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    /** شاشة البيع والكتالوج — أصل الوحدة، وبدونها لا معنى لبقيّة المفاتيح. */
    public const TERMINAL = 'pos_terminal';

    /** تعليقُ السلّة واستئنافها. */
    public const HOLD_ORDERS = 'pos_hold_orders';

    /** إلغاءُ الفاتورة وعكسُ أثرها. */
    public const VOID = 'pos_void';

    /** المرتجعات من شاشة البيع. */
    public const RETURNS = 'pos_returns';

    /** جلساتُ الكاشير وإقفالها. */
    public const SESSIONS = 'pos_sessions_mgmt';

    /** تأكيدُ إقفال العهدة — الفصلُ بين من يُقفل ومن يؤكّد. */
    public const SESSION_CONFIRM = 'pos_session_confirm';

    /** الدرجُ النقدي وحركاته. */
    public const CASH_DRAWER = 'pos_cash_drawer';

    /** أجهزةُ الكاشير والورديات. */
    public const DEVICES = 'pos_devices';

    /** الطابعاتُ وملفات تعريفها. */
    public const PRINTERS = 'pos_printers';

    /** إعداداتُ نقطة البيع وحساباتها. */
    public const SETTINGS = 'pos_settings';

    /** نقاطُ بيعٍ في أكثر من فرع. */
    public const MULTI_BRANCH = 'pos_multi_branch';

    /** التقاريرُ التشغيلية وتقريرا X وZ. */
    public const REPORTS = 'pos_reports';

    /**
     * تقاريرُ الربحية — مفصولةٌ عن `REPORTS` عمداً.
     *
     * التكلفةُ والهامشُ ليسا كعدد الفواتير: باقةٌ أساسية قد تُعطى تقارير
     * المبيعات ولا تُعطى ما يكشف هامشَ كل صنف.
     */
    public const PROFIT_REPORTS = 'pos_profit_reports';

    /** تقاريرُ فروقات الصندوق. */
    public const VARIANCE_REPORTS = 'pos_variance_reports';

    /** محرّكُ العروض والخصومات. */
    public const PROMOTIONS = 'pos_promotions';

    /** العملُ دون اتصال ومزامنته. */
    public const OFFLINE = 'pos_offline';

    /* ==================== حدود الاستخدام ==================== */

    /** سقفٌ متزامن: أجهزةُ الكاشير المسجَّلة. */
    public const LIMIT_DEVICES = 'pos_devices';

    /** سقفٌ متزامن: الجلساتُ المفتوحة في وقتٍ واحد. */
    public const LIMIT_CONCURRENT_SESSIONS = 'pos_concurrent_sessions';

    /** سقفٌ متزامن: الطابعات. */
    public const LIMIT_PRINTERS = 'pos_printers';

    /** سقفٌ متزامن: ورديّاتُ نقاط البيع. */
    public const LIMIT_SHIFTS = 'pos_shifts';

    /** عدّادٌ شهري: الجلساتُ المفتوحة. */
    public const LIMIT_SESSIONS = 'pos_sessions';

    /** عدّادٌ شهري: حركاتُ الدرج النقدي. */
    public const LIMIT_CASH_MOVEMENTS = 'pos_cash_movements';

    /** عدّادٌ شهري: السلالُ المعلَّقة. */
    public const LIMIT_HELD_ORDERS = 'pos_held_orders';

    /**
     * كل مفاتيح الوحدة — لحارس المجموعة في ملف المسارات.
     *
     * الشرط «واحدةٌ على الأقل» لا «كلّها»: باقةٌ تشمل التقارير وحدها يجب أن
     * تفتح تقاريرها، وحارسُ كل شاشةٍ داخلها يتكفّل بالباقي.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::TERMINAL,
            self::HOLD_ORDERS,
            self::VOID,
            self::RETURNS,
            self::SESSIONS,
            self::SESSION_CONFIRM,
            self::CASH_DRAWER,
            self::DEVICES,
            self::PRINTERS,
            self::SETTINGS,
            self::MULTI_BRANCH,
            self::REPORTS,
            self::PROFIT_REPORTS,
            self::VARIANCE_REPORTS,
            self::PROMOTIONS,
            self::OFFLINE,
        ];
    }

    /** صيغة `plan:` الجاهزة لحارس المجموعة — «أيٌّ من هذه». */
    public static function anyOf(): string
    {
        return implode('|', self::all());
    }

    /**
     * الحدّ المرتبط بكل صلاحية باقة — لعرض الشارة في شاشة الميزة نفسها.
     *
     * ولاحظ أن `DEVICES` و`PRINTERS` يحملان الاسم نفسه صلاحيةً وحدّاً
     * (`pos_devices`) — وهو مقصود: الميزةُ والسقفُ شيءٌ واحد هناك، فشراءُ
     * «الأجهزة» هو شراءُ عددٍ منها.
     */
    public static function limitOf(string $permission): ?string
    {
        return [
            self::DEVICES => self::LIMIT_DEVICES,
            self::PRINTERS => self::LIMIT_PRINTERS,
            self::SESSIONS => self::LIMIT_SESSIONS,
            self::CASH_DRAWER => self::LIMIT_CASH_MOVEMENTS,
            self::HOLD_ORDERS => self::LIMIT_HELD_ORDERS,
        ][$permission] ?? null;
    }
}
