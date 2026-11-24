<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Constants\planFeatures\SalesPlanFeatures;;
use App\Services\Fawtra\SubscriptionGate;

/**
 * ربطُ شاشات المبيعات بحدود باقة «فوترة» — الطبقة التي يراها المستخدم.
 *
 * ═══════════════════ موضعُها من الطبقات الأربع ═══════════════════
 *
 * حراسةُ الباقة أربعُ طبقاتٍ لا واحدة، ولكلٍّ دورٌ لا يؤدّيه غيرُها
 * (`docs/Subscription_Plan_Enforcement_Guide.md`):
 *
 *   ١. وسيط `plan:` على مجموعة المسارات — يمنع فتحَ شاشة ميزةٍ لم تُشترَ.
 *   ٢. وسيط `plan.limit:` على مسار الإنشاء — يمنع الإنشاء فوق السقف.
 *   ٣. **هذه السمة** — تردّ برسالةٍ عربيةٍ تذكر الرقمين داخل تدفّق الحفظ،
 *      قبل حجز رقم المستند وقبل أيّ كتابة.
 *   ٤. `PlanLimitObserver` على النموذج — الضمانة الأخيرة التي لا تُخترق.
 *
 * والثالثةُ وحدها لا تكفي: هي تحرس البابَ الذي مُرَّ منه، والصفوف تُنشأ من
 * أبوابٍ أخرى — عملياتٌ جماعية، واستيرادٌ من ملفّ، وأوامرُ طرفية، ومهامُّ
 * طابور لا تمرّ بمتحكّمٍ أصلاً. **الوسيطُ والسمةُ للرسالة الواضحة، والمراقبُ
 * للضمانة.**
 *
 * ═══════════════════ لماذا `checkLive` لا `check` ═══════════════════
 *
 * `check()` تقرأ `used` من اللقطة المحفوظة، واللقطة تُجلب كل ربع ساعة.
 * فالمنعُ المبنيّ عليها يترك نافذةً مقدارها دورةُ مزامنةٍ كاملة، الإنشاءُ
 * فيها **غير محدودٍ عملياً** — ثم تأتي المزامنة فتجد الحدّ متجاوَزاً ولا
 * تملك إرجاعه.
 *
 * `checkLive()` تأخذ الحدَّ من فوترة والمستهلَك بعدٍّ من قاعدتنا الآن،
 * وتعتمد **الأكبر** من الرقمين. وهي دالّة المنع في كل هذا النظام —
 * `PlanLimitObserver` يسألها هي أيضاً.
 *
 * وكلفتُها `COUNT(*)` واحدة لكل ميزةٍ في الطلب، محفوظةً بعد أوّل سؤال؛
 * وتخرج بلا استعلامٍ أصلاً إن كانت الميزة بلا حدّ أو مجهولةً للباقة.
 *
 * ═══════════════════ المفتاحان مختلفان ═══════════════════
 *
 * أسماءُ الميزات هنا مفاتيحُ [SalesPlanFeatures] لا نصوصٌ حرّة: لكلّ ميزةٍ
 * مفتاحُ **صلاحيةِ باقة** (`invoices`) ومفتاحُ **حدٍّ** (`sales_invoices`)،
 * واسمُهما يتقارب حتى يُخلطا. والمفتاح المخلوط لا يُنتج عطلاً يُلاحَظ:
 * `SubscriptionGate` تعامل المجهول «مسموحاً بلا حدّ»، فيبدو حارساً وليس
 * بشيء. ولذلك تُشتقّ الخريطةُ هنا من `SalesPlanFeatures::limitOf()` بدل أن
 * تُكتب مرّتين.
 */
trait ChecksSubscriptionLimits
{
    /**
     * حالةُ حدّ ميزةٍ لعرضها في الشاشة — تُمرَّر إلى `components.subscription-bar`.
     *
     * `liveLimit()` لا `limit()`: الشريط يقول للمستخدم كم بقي له، ورقمٌ
     * متأخّرٌ ربعَ ساعة يجعله يخطّط على رصيدٍ أنفقه. والكلفة `COUNT(*)`
     * واحدة، محفوظةً للطلب.
     *
     * @param  string  $permission  مفتاح صلاحية الباقة (`invoices`…)
     * @return array{known:bool, unlimited:bool, allowance:int, used:int, remaining:int, percent:int, exhausted:bool, label:string}
     */
    protected function subscriptionLimit(string $permission): array
    {
        return app(SubscriptionGate::class)->liveLimit($this->planLimitKey($permission));
    }

    /**
     * هل يُمنع الحفظ؟ — رسالةُ المنع أو `null` إن كان مسموحاً.
     *
     * سببان للمنع، من الأعمّ إلى الأخصّ:
     *
     *   ١. باقةٌ لا تشمل الميزة أصلاً — `allows()`.
     *   ٢. باقةٌ تشملها وقد نفد عددُ الفترة — `checkLive()`.
     *
     * ونشاطُ الاشتراك **لا يُفحَص هنا**: يحرسه `EnsureSubscription` على كل
     * مسار `web` و`api` معاً، فلا يبلغ متحكّمَ إنشاءٍ طلبٌ واشتراكُه موقوف.
     * وتكرارُه يُنتج رسالةً في غير موضعها ويوقف ما لا يمرّ بمسار.
     *
     * @param  string  $permission  مفتاح صلاحية الباقة (`invoices`…)
     */
    protected function subscriptionBlock(string $permission, int $quantity = 1): ?string
    {
        $gate = app(SubscriptionGate::class);

        if (! $gate->allows($permission)) {
            return __('sales_subscription.blocked.not_in_plan', [
                'feature' => $gate->permissionLabel($permission),
            ]);
        }

        $limitKey = $this->planLimitKey($permission);

        // ميزةٌ بلا سقفٍ في الكتالوج — الصلاحية وحدها حارسُها
        if ($limitKey === $permission && SalesPlanFeatures::limitOf($permission) === null) {
            return null;
        }

        $quota = $gate->checkLive($limitKey, $quantity);

        // `reason` تصل جملةً عربيةً جاهزة تذكر الاسم والرقمين؛ ولا يُترك
        // الفرع بلا رسالة إن رجعت فارغة
        return $quota['allowed']
            ? null
            : ($quota['reason'] ?: __('sales_subscription.blocked.limit_reached'));
    }

    /**
     * مفتاحُ الحدّ المقابل لصلاحية الباقة.
     *
     * ميزةٌ بلا سقفٍ تُردّ باسمها هي — و`liveLimit()` تعامل ما لا تعرفه بلا
     * حدّ، وهو المطلوب تماماً. وردُّ سلسلةٍ فارغة كان سيفعل الشيء نفسه لكنّه
     * يُخفي اسمَ الميزة عن الشاشة فيتعذّر تتبّعُ الخطأ.
     */
    private function planLimitKey(string $permission): string
    {
        return SalesPlanFeatures::limitOf($permission) ?? $permission;
    }
}
