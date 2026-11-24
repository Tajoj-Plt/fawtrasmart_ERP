<?php

namespace App\Services\Fawtra;

use App\Models\FawtraSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * بوّابة الاشتراك — تُجيب عن «هل يُسمح؟» **من اللقطة المحفوظة لا بنداء شبكة**.
 *
 * **لماذا محلياً؟** لأن السؤال يتكرّر مع كل فاتورة وكل عميل جديد. ونداءُ
 * الشبكة لكل عملية يعني:
 *
 * - إبطاءَ كل عملية بزمن رحلة كاملة، والمستخدم ينتظر.
 * - استنفادَ حدّ الطلبات (١٢٠/دقيقة) في منشأةٍ نشطة، فتُرفض عمليات صحيحة.
 * - توقّفَ النظام كلّه بانقطاع شبكةٍ عند فوترة — وهو ما تنهى عنه الوثيقة
 *   صريحاً: «لا توقف نظام العميل لعطل شبكة عندنا».
 *
 * فالحدود تُقرأ من اللقطة التي تجلبها المزامنة الدورية كل ربع ساعة، ويُقرأ
 * الاستهلاك الفعلي من قاعدة البيانات المحلّية. والفارق بين الرقمين يُسوَّى في
 * المزامنة التالية.
 *
 * **والقراءة مخزَّنة ٦٠ ثانية** كما توصي الوثيقة لـ`/status` — فحتى فكّ JSON
 * لا يتكرّر مع كل سؤال.
 */
class SubscriptionGate
{
    private const CACHE_KEY = 'fawtra.gate';

    private const CACHE_TTL = 60;

    /**
     * اللقطة المُسطَّحة محفوظة للطلب الجاري.
     *
     * **لماذا فوق الكاش؟** لأن السؤال يتكرّر عشرات المرّات في الطلب الواحد
     * (كل بند فاتورة، كل زرّ في الشاشة)، ومخزن الكاش قد يكون قاعدة البيانات
     * أو ملفاً — فيصير لكل سؤالٍ رحلة إلى القرص. القياس على ١٠٠٠ فحص:
     * ‎848ms‎ عبر الكاش وحده، و‎9ms‎ بهذه الحفظة.
     *
     * وهي `static` لا خاصّية نسخة: البوّابة تُبنى في أماكن كثيرة بـ`app()`
     * ولا تُسجَّل singleton، فحفظُها في النسخة لا يوفّر شيئاً.
     *
     * @var array{value: array<string, mixed>|null}|null
     */
    private static ?array $memo = null;

    /**
     * لحظة انتهاء الحفظة.
     *
     * الحفظة `static` فتعيش ما عاش العمليّة — وفي عامل طابور أو Octane تعني
     * العمليّة ساعات. فتُقيَّد بعمر الكاش نفسه (٦٠ ثانية) حتى لا يعمل عاملٌ
     * طويل العمر بلقطةٍ قديمة لا يعرف أنها تغيّرت.
     */
    private static float $memoExpiresAt = 0.0;

    /**
     * القياسات الحيّة المحفوظة للطلب الجاري — مفتاح الميزة إلى عددها.
     *
     * **لا تُخزَّن في الكاش ولا تُعمَّر بزمن**: هذه أرقامٌ تُبنى عليها قرارات
     * منعٍ في لحظة الإنشاء، وقيمةٌ عمرها ثوانٍ تعني أن عمليتين متتاليتين
     * تريان العدد نفسه فتمرّان معاً فوق السقف. عمرُها الطلب الواحد لا أكثر.
     *
     * وتُصفَّر بعد كل عملية إنشاء ناجحة عبر `forgetLiveCounts()` كي لا يقرأ
     * ما بعدها في الطلب نفسه عدداً سبق الإنشاء (الاستيراد الجماعي مثلاً).
     *
     * @var array<string, int|null>
     */
    private static array $liveMemo = [];

    /**
     * هل الاشتراك نشط؟
     *
     * نظامٌ غير مربوط يعمل بلا قيد (`assume_active_when_unlinked`): الربط
     * ميزةٌ تُضاف، ومنعُ العمل على من لا اشتراك له يوقف كل تركيب محلّي.
     */
    public function active(): bool
    {
        $snapshot = $this->snapshot();

        if ($snapshot === null) {
            return (bool) config('fawtra.assume_active_when_unlinked', true);
        }

        return (bool) ($snapshot['is_active'] ?? false);
    }

    /**
     * هل تسمح الباقة بميزة؟ — للشاشات والقوائم.
     *
     * الميزة غير المذكورة في الباقة **مسموحة**: كتالوج فوترة قد يسبق النظام
     * أو يتأخّر عنه، ومنعُ ما لا يُعرف يُقفل شاشاتٍ لم يقصد أحد إقفالها.
     */
    public function allows(string $permission): bool
    {
        $snapshot = $this->snapshot();

        if ($snapshot === null) {
            return true;
        }

        $permissions = $snapshot['permissions'] ?? [];

        return ! array_key_exists($permission, $permissions) || (bool) $permissions[$permission];
    }

    /**
     * حالة حدّ ميزة — بلا أي نداء شبكة.
     *
     * @return array{known:bool, unlimited:bool, allowance:int, used:int, remaining:int, percent:int, exhausted:bool, label:string}
     */
    public function limit(string $feature): array
    {
        $snapshot = $this->snapshot();
        $item = $snapshot['usage'][$feature] ?? null;

        if ($item === null) {
            // ميزة غير متابَعة — تُعامَل بلا حدّ لا بحدٍّ صفري
            return [
                'known' => false, 'unlimited' => true, 'allowance' => -1, 'used' => 0,
                'remaining' => -1, 'percent' => 0, 'exhausted' => false, 'label' => $feature,
            ];
        }

        return $item + ['known' => true];
    }

    /**
     * حالة حدّ ميزة بعد **عدّ المستهلَك من قاعدة البيانات الآن**.
     *
     * ─────────────────── لماذا لا تكفي `limit()` للمنع؟ ───────────────────
     * `limit()` تقرأ `used` من اللقطة، واللقطة تُجلب كل ربع ساعة والرفعُ
     * إليها دوريٌّ كذلك. فهي تصلح لعرض شارةٍ في شاشة، ولا تصلح **جداراً**:
     * بين مزامنتين يمكن إنشاء آلاف الصفوف فوق السقف بلا أن يرتفع `used`
     * حرفاً واحداً، ثم تأتي المزامنة فتجد الحدّ متجاوَزاً ولا تملك إرجاعه.
     *
     * فهنا: **الحدّ من فوترة، والمستهلَك من عندنا الآن.** والمأخوذ هو الأكبر
     * من الرقمين — لقطةُ فوترة قد تعرف استهلاكاً لا نراه محلياً (صفوفٌ حُذفت
     * بعد رفعها مثلاً)، وأخذُ الأصغر يفتح الباب نفسه من الجهة الأخرى.
     *
     * والقياس محفوظٌ للطلب الجاري: شاشةٌ واحدة قد تسأل عن الميزة نفسها
     * مرّاتٍ، وكل سؤالٍ `COUNT(*)` على جدولٍ كبير.
     *
     * @return array{known:bool, unlimited:bool, allowance:int, used:int, remaining:int, percent:int, exhausted:bool, label:string, live:bool}
     */
    public function liveLimit(string $feature): array
    {
        $limit = $this->limit($feature);

        // ميزةٌ لا تعرفها الباقة أو بلا حدّ: لا شيء يُعدّ ولا شيء يُمنع
        if (! $limit['known'] || $limit['unlimited']) {
            return $limit + ['live' => false];
        }

        if (array_key_exists($feature, self::$liveMemo)) {
            $counted = self::$liveMemo[$feature];
        } else {
            try {
                $counted = app(UsageReporter::class)->liveCount($feature, $limit['period'] ?? null);
            } catch (Throwable $e) {
                // عطلٌ في العدّ لا يفتح الباب ولا يقفله: يُرجَع ما تقوله
                // اللقطة — وهو أضعف الحارسَين لا أقواهما، لكنّ البديل
                // (المنع الكامل) يوقف نظاماً يعمل بسبب استعلامٍ أخفق
                Log::warning('فوترة: تعذّر القياس الحيّ لحدّ '.$feature, ['error' => $e->getMessage()]);
                $counted = null;
            }

            self::$liveMemo[$feature] = $counted;
        }

        if ($counted === null) {
            return $limit + ['live' => false];
        }

        $allowance = (int) $limit['allowance'];
        $used = max((int) $limit['used'], $counted);

        return [
            'known' => true,
            'unlimited' => false,
            'label' => $limit['label'],
            'period' => $limit['period'] ?? null,
            'type' => $limit['type'] ?? null,
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0, $allowance - $used),
            'percent' => $allowance > 0 ? (int) min(100, round(($used / $allowance) * 100)) : 0,
            'exhausted' => $used >= $allowance,
            'live' => true,
        ];
    }

    /**
     * هل يُسمح باستهلاك كمّية — بالقياس الحيّ؟ **هذه هي دالّة المنع.**
     *
     * `check()` تبقى لِما لا يُقاس محلياً ولعرض الشاشات؛ وكل مسارٍ يُنشئ
     * صفّاً فعلياً يسأل هذه.
     *
     * @return array{allowed:bool, reason:?string, remaining:int}
     */
    public function checkLive(string $feature, int $quantity = 1): array
    {
        /*
            **لا فحصَ لنشاط الاشتراك هنا — عن قصد.**

            خلافاً لـ`check()`: هذه الدالّة سؤالٌ عن **الحدّ** وحده، ونشاطُ
            الاشتراك يحرسه [\App\Http\Middleware\EnsureSubscription] على كل
            مسار `web` و`api` معاً — فلا يبلغ متحكّمَ إنشاءٍ طلبٌ واشتراكُه
            موقوف أصلاً.

            وخلطُهما هنا يُنتج ضررين: رسالة «الاشتراك غير نشط» في موضعٍ
            سؤاله عن السقف فتُربك من يقرؤها، و — الأهمّ — إيقافُ ما لا يمرّ
            بمسار: أوامر الطرفية والمهامّ في الطابور والبذور. فاشتراكٌ تعثّرت
            مزامنته يصير نظاماً لا تُصلَح قاعدةُ بياناته من الطرفية أيضاً.
        */
        $limit = $this->liveLimit($feature);

        if (! $limit['known'] || $limit['unlimited']) {
            return ['allowed' => true, 'reason' => null, 'remaining' => -1];
        }

        $remaining = (int) $limit['remaining'];

        if ($remaining >= $quantity) {
            return ['allowed' => true, 'reason' => null, 'remaining' => $remaining];
        }

        return [
            'allowed' => false,
            'reason' => sprintf(
                $quantity > 1
                    ? 'حدّ «%s» في باقة فوترة لا يتّسع لهذه العملية: المستهلَك %d من %d، والمتبقّي %d فقط بينما العملية تحتاج %d. اشترِ إضافة أو احذف ما لم يعد مستعمَلاً.'
                    : 'بلغت حدّ «%s» في باقة فوترة: %d من %d. اشترِ إضافة أو احذف ما لم يعد مستعمَلاً.',
                $limit['label'],
                $limit['used'],
                $limit['allowance'],
                $remaining,
                $quantity
            ),
            'remaining' => $remaining,
        ];
    }

    /**
     * هل تسمح الباقة بواحدةٍ من هذه الميزات على الأقل؟
     *
     * لشاشاتٍ لا تخصّ ميزةً بعينها بل تجمع عدّة (لوحة المخزون، وبند القائمة
     * الجامع): إخفاؤها يجب أن يقع حين **لا شيء** منها مسموح، لا حين يُمنع
     * أوّلها.
     *
     * @param  array<int, string>  $permissions
     */
    public function allowsAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->allows($permission)) {
                return true;
            }
        }

        return $permissions === [];
    }

    /**
     * الميزات الممنوعة من قائمة — لرسالة تقول أي ميزةٍ بالضبط ينقص.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public function deniedAmong(array $permissions): array
    {
        return array_values(array_filter($permissions, fn ($p) => ! $this->allows($p)));
    }

    /**
     * مدخلٌ ثابت للقوالب — يقبل مفتاحاً واحداً أو قائمةً (تكفي واحدة منها).
     *
     * يُغذّي موجّه Blade‏ `@plan(...)`. وهو `static` كي يُستدعى من القالب بلا
     * `app()` في كل سطر، والبوّابة نفسها لا حالة لها تُفقد.
     *
     * @param  string|array<int, string>  $permissions
     */
    public static function permits(string|array $permissions): bool
    {
        return app(self::class)->allowsAny((array) $permissions);
    }

    /** الاسم العربي لصلاحية باقة كما ترسله فوترة — أو المفتاح نفسه. */
    public function permissionLabel(string $permission): string
    {
        try {
            $record = FawtraSubscription::current();
        } catch (Throwable) {
            return $permission;
        }

        return $record?->permissionLabels()[$permission] ?? $permission;
    }

    /**
     * هل يُسمح باستهلاك كمّية من ميزة؟
     *
     * يوافق دلالة `/usage/check` عند فوترة لكن بلا نداء. والقيد الفعلي يُرفع
     * إلى فوترة في المزامنة الدورية، لا مع كل عملية.
     *
     * @return array{allowed:bool, reason:?string, remaining:int}
     */
    public function check(string $feature, int $quantity = 1): array
    {
        if (! $this->active()) {
            return ['allowed' => false, 'reason' => 'الاشتراك غير نشط.', 'remaining' => 0];
        }

        $limit = $this->limit($feature);

        if ($limit['unlimited']) {
            return ['allowed' => true, 'reason' => null, 'remaining' => -1];
        }

        $remaining = (int) $limit['remaining'];

        if ($remaining >= $quantity) {
            return ['allowed' => true, 'reason' => null, 'remaining' => $remaining];
        }

        return [
            'allowed' => false,
            'reason' => sprintf(
                'بلغت حدّ «%s»: %d من %d. اشترِ إضافة أو انتظر الفترة الجديدة.',
                $limit['label'],
                $limit['used'],
                $limit['allowance']
            ),
            'remaining' => max(0, $remaining),
        ];
    }

    /** الميزات التي بلغت عتبة التنبيه (٨٠٪ افتراضاً) أو استُنفدت. */
    public function nearingLimits(int $threshold = 80): array
    {
        $snapshot = $this->snapshot();

        if ($snapshot === null) {
            return [];
        }

        return array_values(array_filter(
            $snapshot['usage'] ?? [],
            fn ($item) => ! $item['unlimited'] && (int) $item['percent'] >= $threshold
        ));
    }

    /** إبطال الكاش وحفظة الطلب — تستدعيه المزامنة بعد كل تحديث. */
    public static function forget(): void
    {
        self::$memo = null;
        self::$memoExpiresAt = 0.0;
        self::$liveMemo = [];
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * إبطال القياسات الحيّة وحدها — بعد إنشاء صفٍّ أو حذفه.
     *
     * الحدود لم تتغيّر، فلا داعي لإبطال اللقطة كلّها ولا للعودة إلى مخزن
     * الكاش؛ المتغيّر هو المستهلَك وحده.
     */
    public static function forgetLiveCounts(): void
    {
        self::$liveMemo = [];
    }

    /**
     * تحديث العدّاد المحفوظ بالزيادة أو النقصان — بعد إنشاء صفٍّ أو حذفه.
     *
     * **لماذا لا يُعاد العدّ؟** لأن الاستيراد الجماعي يُنشئ آلاف الصفوف في
     * طلبٍ واحد، و`COUNT(*)` مع كلٍّ منها يحوّل ثوانيَ إلى دقائق. والزيادة
     * دقيقةٌ تماماً: نحن من أنشأ الصفّ ونعرف أنه واحد.
     *
     * وبلا هذا التحديث يقرأ الصفّ الألفُ العددَ الذي قرأه الأوّل، فيمرّ
     * الاستيراد كلّه فوق السقف بفحصٍ واحدٍ صحيحٍ في أوّله.
     *
     * ولا شيء يقع إن لم تُقَس الميزة بعد في هذا الطلب: أوّل سؤالٍ عنها
     * سيعدّها من القاعدة وقد صار الصفّ فيها.
     */
    public static function bumpLiveCount(string $feature, int $delta): void
    {
        if (! array_key_exists($feature, self::$liveMemo) || self::$liveMemo[$feature] === null) {
            return;
        }

        self::$liveMemo[$feature] = max(0, self::$liveMemo[$feature] + $delta);
    }

    /**
     * اللقطة مُسطَّحة ومخزَّنة — أو null إن لم يكن النظام مربوطاً.
     *
     * التسطيح مقصود: `usage` مفهرسة بمفتاح الميزة لا مصفوفةً مرقّمة، فقراءة
     * حدّ ميزة بعينها لا تحتاج مسحاً خطّياً في كل سؤال.
     */
    protected function snapshot(): ?array
    {
        if (self::$memo !== null && microtime(true) < self::$memoExpiresAt) {
            return self::$memo['value'];
        }

        // القيمة مغلَّفة في `['value' => …]` عن قصد: `Cache::remember` تعتبر
        // القيمة `null` غياباً فتعيد تنفيذ الدالة في كل نداء — ونظامٌ غير
        // مربوط لقطتُه null، فيستعلم القاعدة مع كل سؤال بلا هذا الغلاف
        $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return ['value' => $this->build()];
        });

        self::$memo = is_array($cached) && array_key_exists('value', $cached)
            ? $cached
            : ['value' => null];
        self::$memoExpiresAt = microtime(true) + self::CACHE_TTL;

        return self::$memo['value'];
    }

    /**
     * بناء اللقطة المُسطَّحة من الصفّ المحفوظ.
     *
     * @return array<string, mixed>|null
     */
    protected function build(): ?array
    {
        $record = FawtraSubscription::current();

        if (! $record || ! $record->snapshot) {
            return null;
        }

        $usage = [];

        foreach ($record->usage() as $item) {
            $key = $item['key'] ?? null;

            if ($key === null) {
                continue;
            }

            $unlimited = (bool) ($item['unlimited'] ?? false);

            $usage[$key] = [
                'label' => $item['label'] ?? $key,
                // `period` و`type` يلزمان القياسَ الحيّ في `liveLimit()`:
                // بلا وسم الفترة لا تُعرف نافذة العدّ لعدّادٍ دوري، وبلا
                // النوع لا يُفرَّق السقف المتزامن من العدّاد
                'period' => $item['period'] ?? null,
                'type' => $item['type'] ?? ($unlimited ? null : 'quota'),
                'unlimited' => $unlimited,
                'allowance' => (int) ($item['allowance'] ?? -1),
                'used' => (int) ($item['used'] ?? 0),
                // `-1` يعني بلا حدّ — الوثيقة تحذّر من تحويله إلى صفر
                'remaining' => $unlimited ? -1 : (int) ($item['remaining'] ?? 0),
                'percent' => (int) ($item['percent'] ?? 0),
                'exhausted' => (bool) ($item['exhausted'] ?? false),
            ];
        }

        return [
            'is_active' => (bool) $record->is_active,
            'permissions' => $record->planPermissions(),
            'usage' => $usage,
        ];
    }
}
