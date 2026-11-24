<?php

namespace App\Support;

use App\Models\FawtraSubscription;
use App\Services\Fawtra\SubscriptionGate;
use Throwable;

/**
 * قفلُ الميزة غير المشتراة — **يُعرض ولا يُخفى**.
 *
 * ─────────────────── لماذا قفلٌ لا إخفاء؟ ───────────────────
 *
 * إخفاءُ ما لم يُشترَ يجعل المنشأة لا تعرف أنه موجود أصلاً. فمن لا يرى
 * «قوائم الأسعار» لا يسأل عنها ولا يشتريها — ويظنّ أن النظام لا يملكها.
 * والقفلُ يقول شيئين معاً: الميزة موجودة، وهي خارج باقتك — وهذا وحده ما
 * يجعل الترقية خياراً مفهوماً.
 *
 * وهو يفترق عن **صلاحيات النظام**: تلك تُخفى ولا تُقفل. موظّفٌ لا يملك
 * صلاحية المستودعات لا شأن له بها ولا يشتريها — وعرضُ قفلٍ له يدعوه إلى
 * بابٍ ليس بابه، ويكشف له بنية النظام بلا حاجة.
 *
 *   • **صلاحية نظام ناقصة** → `@if (hasPermission(...))` — إخفاء.
 *   • **ميزة خارج الباقة**   → قفلٌ ظاهر — هذا الصنف.
 *
 * ─────────────────── والقفل ليس منعاً ───────────────────
 *
 * المنع الفعلي في وسيط `plan:` على المسارات (انظر
 * [\App\Http\Middleware\EnsurePlanFeature]). فمن نسخ العنوان ولصقه يُردّ
 * بـ402 مهما فعلت هذه الشاشة. وما هنا واجهةٌ تشرح، لا حارس.
 */
class PlanLock
{
    /** وجهة المقفول: شاشة الاشتراك — منها يرى باقته ويُرقّيها. */
    public static function url(): string
    {
        try {
            return route('Fawtra.index');
        } catch (Throwable) {
            // المسار غير مُسجَّل (وحدة الإعدادات معطَّلة) — لا رابط مكسور
            return '#';
        }
    }

    /**
     * هل هذه الميزة مقفولة؟ — أي: لا تشملها الباقة.
     *
     * تقبل قائمةً كذلك، فتُقفَل حين **لا شيء** منها مسموح — وهي حال البنود
     * الجامعة التي تفتح على عدّة شاشات.
     *
     * @param  string|array<int, string>  $features
     */
    public static function locked(string|array $features): bool
    {
        return ! SubscriptionGate::permits($features);
    }

    /**
     * أسماء الميزات كما يراها المستخدم في شاشة الاشتراك — لا مفاتيحها.
     *
     * «باقتك لا تشمل stock_price_lists» لا تقول لمشترٍ شيئاً.
     *
     * @param  string|array<int, string>  $features
     */
    public static function label(string|array $features): string
    {
        $gate = app(SubscriptionGate::class);

        $labels = array_map(
            fn (string $feature) => $gate->permissionLabel($feature),
            (array) $features
        );

        return implode('، ', $labels);
    }

    /**
     * نصّ التلميح على العنصر المقفول — يشرح السبب والمخرج معاً.
     *
     * @param  string|array<int, string>  $features
     */
    public static function reason(string|array $features): string
    {
        return 'خارج باقتك الحالية: «'.self::label($features).'». اضغط لعرض الاشتراك والترقية.';
    }

    /** اسم الباقة الحالية — يُذكر في الشاشات لا في التلميح المختصر. */
    public static function planName(): ?string
    {
        try {
            return FawtraSubscription::current()?->plan_name;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * شارة القفل لبند القائمة الجانبية — قفلٌ صغير في طرف السطر.
     *
     * تُعاد HTML جاهزة كي لا يتكرّر الترميز في كل بند، ولا يتباعد شكلُه
     * بين شريطٍ وآخر.
     */
    /**
     * أيقونةُ قفل بند القائمة.
     *
     * تحمل صنفَين: `app-plan-lock__icon` المحايد الذي تستعمله الوحدات
     * الجديدة، و`stk-plan-lock__icon` الذي كُتب حين كانت الميزةُ في وحدة
     * المخزون وحدها. والثاني يبقى كي لا ينكسر تنسيقُ قائمة المخزون —
     * فالأصنافُ تُجمع ولا تُستبدل.
     */
    public static function menuBadge(): string
    {
        return '<i class="feather icon-lock app-plan-lock__icon stk-plan-lock__icon" aria-hidden="true"></i>';
    }
}
