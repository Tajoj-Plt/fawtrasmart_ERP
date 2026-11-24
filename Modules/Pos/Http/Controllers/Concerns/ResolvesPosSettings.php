<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Models\PosGeneralSetting;

/**
 * إعدادات نقطة البيع بتوريثٍ ثلاثي: عام ← فرع ← جهاز.
 *
 * ─────────────────────── نظامان متوازيان ───────────────────────
 *
 * كان في النظام مصدران للإعداد نفسه، لا يعرف أحدهما الآخر:
 *
 *   ١. `pos_general_setting` — صفٌّ **واحد** عالمي، تقرؤه الشيفرة دائماً
 *      بـ`PosGeneralSetting::first()` (وفي موضعٍ بـ`find(1)` صراحةً).
 *
 *   ٢. مجموعة `pos` في `Modules\Branches\Services\BranchSettingsCatalog` —
 *      خمسةُ مفاتيح **لكل فرع**: `enable_pos`، `require_shift_open`،
 *      `allow_cash_refund`، `max_refund_amount`، `pos_print_receipt_auto`.
 *      **ولا يقرؤها أيُّ سطرٍ في وحدة نقاط البيع.**
 *
 * والمفارقة أن ثلاثةً من تلك المفاتيح هي بالضبط الضوابطُ التي بدت «غائبة»
 * عن الوحدة: إلزامُ فتح الوردية، والسماحُ بالمرتجع النقدي، وحدُّ المرتجع
 * بلا موافقة. فهي لم تكن غائبةً عن التصميم بل عن التوصيل: المسؤولُ يضبطها
 * في شاشة الفروع فلا يحدث شيء — وهو أسوأ من غيابها، لأنه يعد بضبطٍ لا وجود له.
 *
 * ─────────────────────── ترتيب الحلّ ───────────────────────
 *
 *   إعدادُ الجهاز (إن وُجد) ← إعدادُ الفرع ← الإعدادُ العام ← الافتراضي.
 *
 * والأخصُّ يفوز: فرعٌ يمنع المرتجع النقدي يمنعه وإن سمح به العام. وهذا هو
 * التعاقدُ الذي تعمل به `BranchSettingsService` أصلاً (`own` ثم `inherited`
 * ثم `default`)، فلا يُخترع هنا تعاقدٌ ثانٍ.
 *
 * ─────────────────────── ولماذا لا يُلغى العام؟ ───────────────────────
 *
 * ما يخصّ المنشأة كلَّها — الحساباتُ المحاسبية والبنكُ الافتراضي وقالبُ
 * الفاتورة — لا معنى لتفريعه: بنكان مختلفان لفرعين مسألةُ حسابٍ لا مسألةُ
 * إعداد. فالعامُّ يبقى لها، والفرعُ لما يتغيّر فعلاً بتغيّر المكان.
 */
trait ResolvesPosSettings
{
    /**
     * صفُّ الإعدادات المحفوظ لكل فرعٍ على حدة في الطلب الواحد.
     *
     * مفهرسٌ بالفرع لا واحداً: شاشةٌ قد تسأل عن إعداد فرعين (تقريرٌ يقارنهما
     * مثلاً)، وحفظُ صفٍّ واحد يُعيد إعدادَ الأوّل عن الثاني بصمت.
     *
     * @var array<string, PosGeneralSetting|null>
     */
    private array $posSettingsCache = [];

    /**
     * صفُّ الإعدادات المطبَّق — **لهذا الفرع** لا للمنشأة كلِّها.
     *
     * ═══════════════ ما كان ═══════════════
     *
     * `PosGeneralSetting::first()` — أوّلُ صفٍّ في الجدول أيّاً كان فرعُه.
     *
     * وهو يُبطل الإعدادَ الفرعي كلَّه: شاشةُ الإعدادات تحفظ صفّاً مستقلاً
     * لكل فرع (`SettingsController` يستعمل `forBranch()`)، والتشغيلُ يقرأ
     * الأوّلَ دائماً. فالمستخدمُ يضبط إعدادَ الفرع الثاني ويحفظه ويراه
     * محفوظاً عند إعادة الفتح — **ولا يُطبَّق منه شيء**. وهذا أسوأُ من ألّا
     * يُحفظ: لا شيء يدلّ على أنه معطَّل.
     *
     * و`first()` لا `find(1)` كان تصحيحاً لعطبٍ آخر (افتراضُ أن المعرّف ١)،
     * لكنه عالج نصفَ المسألة: البُعدُ المفقود هو الفرع لا المعرّف.
     *
     * @param  int|null  $branchId  فرعٌ بعينه، أو الفرعُ الجاري
     */
    protected function posSettings(?int $branchId = null): ?PosGeneralSetting
    {
        if ($branchId === null && function_exists('current_branch_id')) {
            $branchId = current_branch_id();
        }

        $key = (string) ($branchId ?: 'global');

        if (! array_key_exists($key, $this->posSettingsCache)) {
            // `forBranch()` تسقط إلى الإعداد العام حين لا صفَّ للفرع —
            // فالفرعُ الذي لم يُخصَّص يرث ولا يُترك بلا إعداد
            $this->posSettingsCache[$key] = PosGeneralSetting::forBranch($branchId);
        }

        return $this->posSettingsCache[$key];
    }

    /** يُنسى المحفوظ — بعد حفظ الإعدادات في الشاشة. */
    protected function forgetPosSettings(): void
    {
        $this->posSettingsCache = [];
    }

    /**
     * قيمةُ إعدادٍ بالتوريث الثلاثي.
     *
     * @param  string  $branchKey  مفتاحُه في كتالوج إعدادات الفروع
     * @param  string|null  $globalKey  عمودُه في `pos_general_setting`
     * @param  mixed  $default
     * @return mixed
     */
    protected function posSetting(string $branchKey, ?string $globalKey = null, $default = null, ?int $branchId = null)
    {
        // الفرعُ أخصُّ من العام، فيُسأل أولاً
        $branchValue = branch_setting($branchKey, null, $branchId);

        if ($branchValue !== null && $branchValue !== '') {
            return $branchValue;
        }

        if ($globalKey) {
            // الفرعُ نفسُه يُمرَّر: صفُّ الإعدادات فرعيٌّ كذلك، لا عامٌّ وحده
            $globalValue = $this->posSettings($branchId)?->{$globalKey};

            if ($globalValue !== null && $globalValue !== '') {
                return $globalValue;
            }
        }

        return $default;
    }

    /** إعدادٌ بولياني بالتوريث نفسه. */
    protected function posSettingEnabled(string $branchKey, ?string $globalKey = null, bool $default = false, ?int $branchId = null): bool
    {
        $value = $this->posSetting($branchKey, $globalKey, null, $branchId);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    /* ═══════════════ الضوابط التي كانت معطَّلة ═══════════════ */

    /**
     * أتُلزَم وردية مفتوحة قبل البيع؟
     *
     * كان الفحصُ مكتوباً في الشيفرة بلا شرط: لا بيعَ بلا جلسةٍ نشطة، دائماً.
     * والمفتاحُ يجعله قراراً للمنشأة — ومنشأةٌ بجهازٍ واحدٍ ومالكٍ واحد قد
     * لا تريد عهدةً مفتوحةً مغلقةً كلَّ يوم.
     */
    protected function requiresOpenShift(?int $branchId = null): bool
    {
        return $this->posSettingEnabled('require_shift_open', 'require_shift_open', true, $branchId);
    }

    /** أيُسمح بردّ المبلغ نقداً من الدرج؟ وإلّا فإشعارٌ دائن على الحساب. */
    protected function allowsCashRefund(?int $branchId = null): bool
    {
        return $this->posSettingEnabled('allow_cash_refund', 'allow_cash_refund', false, $branchId);
    }

    /**
     * أقصى مبلغ مرتجعٍ بلا اعتماد — صفرٌ يعني «كلُّها تحتاج اعتماداً».
     */
    protected function maxRefundWithoutApproval(?int $branchId = null): float
    {
        return (float) $this->posSetting('max_refund_amount', 'max_refund_amount', 0, $branchId);
    }

    /**
     * أيتجاوز هذا المرتجعُ الحدَّ المسموح بلا اعتماد؟
     *
     * صفرٌ يعني أن كلَّ مرتجعٍ يحتاج اعتماداً — وهو ما ينصّ عليه وصفُ المفتاح
     * في كتالوج الفروع حرفياً.
     */
    protected function refundNeedsApproval(float $amount, ?int $branchId = null): bool
    {
        $limit = $this->maxRefundWithoutApproval($branchId);

        return $limit <= 0 || $amount > $limit;
    }

    /** أتُطبع الإيصالُ تلقائياً بعد الإتمام؟ */
    protected function printsReceiptAutomatically(?int $branchId = null): bool
    {
        return $this->posSettingEnabled('pos_print_receipt_auto', 'show_print_window_after_confirm', true, $branchId);
    }

    /** أنقطةُ البيع مفعَّلة لهذا الفرع أصلاً؟ */
    protected function posEnabledForBranch(?int $branchId = null): bool
    {
        return $this->posSettingEnabled('enable_pos', null, true, $branchId);
    }
}
