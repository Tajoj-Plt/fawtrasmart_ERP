<?php

namespace App\Support;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * المدَيات التي أُقفلت محاسبياً — مصدرٌ واحدٌ لكلّ من يُخفيها.
 *
 * الإقفال كان يمنع الكتابة ولا يمسّ العرض، فتبقى مستنداتُ سنةٍ أُقفلت
 * وأُصدرت قوائمُها معروضةً في الشاشات وداخلةً في إحصائها. صار يُخفيها،
 * وهذا الصنف هو تعريفُ «ما أُقفل» الذي تشترك فيه شاشاتُ المستندات
 * ولوحاتُ المؤشّرات في المبيعات والمشتريات معاً — فلا يتفرّق التعريفُ
 * بينها فتقول شاشةُ المبيعات غيرَ ما تقوله شاشةُ المشتريات عن الشهر نفسه.
 *
 * **وموضعُه `App\Support` لا وحدةٌ بعينها**: ليس فيه سطرٌ يخصّ مبيعاتٍ أو
 * مشترياتٍ — هو جوابٌ عن «أيّ المدَيات مقفلة؟» وحدَه. وكان في
 * `Modules\Sales\Support` حين لم يكن له إلّا قارئٌ واحد؛ فلمّا قرأته
 * المشترياتُ معه صار تكرارُه يعني ذاكرتين مؤقّتتين ونسختين تفترقان،
 * و`forget()` تُنسي إحداهما دون الأخرى.
 *
 * **تابعٌ لضابط `enforce_lock`**: الشاشة تَعِد بأن «قبل تفعيله لا أثر
 * لأيّ إقفال»، فإخفاءُ مستنداتٍ والضابطُ مطفأٌ يُخلف ذلك الوعد.
 *
 * **والسنةُ المقفلةُ تُدرَج بمداها كاملاً** لا بفتراتها: `closeYear` يُبقي
 * الفترةَ المقفلةَ نهائياً على حالها ويُقفل المفتوحةَ فقط، فالاكتفاءُ
 * بالفترات يترك ثقوباً في سنةٍ أُعلن إقفالها.
 */
class ClosedPeriodRanges
{
    /** @var array<int, array{0: string, 1: string}>|null */
    protected static ?array $memo = null;

    /**
     * كل مدىً مقفل — فترةً كان أو سنة.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function all(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        try {
            if (! app(AccountingPeriodService::class)->settings()->enforce_lock) {
                return static::$memo = [];
            }

            $toRange = fn ($row) => [$row->start_date->toDateString(), $row->end_date->toDateString()];

            $periods = AccountingPeriod::whereIn('status', [
                AccountingPeriod::STATUS_CLOSED,
                AccountingPeriod::STATUS_LOCKED,
            ])->get(['start_date', 'end_date'])->map($toRange);

            $years = FiscalYear::where('status', FiscalYear::STATUS_CLOSED)
                ->get(['start_date', 'end_date'])->map($toRange);

            return static::$memo = static::coalesce($periods->merge($years)->all());
        } catch (\Throwable $e) {
            // تقويمٌ غير مهيّأ يجب ألّا يُفرغ شاشةً: يُستبعَد لا شيء، ويُكتب الأثر
            Log::warning('تعذّر حساب المدَيات المقفلة — لم يُستبعد شيء', ['error' => $e->getMessage()]);

            return static::$memo = [];
        }
    }

    /**
     * ضمُّ المتداخل والمتجاور من المدَيات في مدىً واحد.
     *
     * سنةٌ مقفلةٌ مقسّمةٌ شهوراً تُعطي ثلاثةَ عشرَ مدىً — اثنا عشر شهراً
     * والسنةُ فوقها — تتّحد كلُّها في مدىً واحد. وبدون الضمّ يُضاف إلى كلّ
     * استعلامٍ في الشاشات ثلاثةَ عشرَ شرطاً مركّباً، وهي شروطٌ يقول
     * أوّلُها ما تقوله بقيّتُها.
     *
     * والمتجاورُ يُضمّ كالمتداخل: نهايةُ يناير ٣١ وبدايةُ فبراير ١ يومان
     * متلاصقان لا فجوةَ بينهما.
     *
     * @param  array<int, array{0: string, 1: string}>  $ranges
     * @return array<int, array{0: string, 1: string}>
     */
    protected static function coalesce(array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        usort($ranges, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $merged = [array_shift($ranges)];

        foreach ($ranges as [$from, $to]) {
            $last = count($merged) - 1;

            // أوّلُ يومٍ خارج المدى السابق — فما بدأ عنده أو قبله ملتصقٌ به
            $nextDay = Carbon::parse($merged[$last][1])->addDay()->toDateString();

            if ($from <= $nextDay) {
                if ($to > $merged[$last][1]) {
                    $merged[$last][1] = $to;
                }

                continue;
            }

            $merged[] = [$from, $to];
        }

        return $merged;
    }

    /**
     * استبعادُ ما أُقفل من استعلام.
     *
     * `$column` مؤهَّلٌ باسم جدوله أو باسمه المستعار: هذه الاستعلامات
     * تضمّ جداولَ كثيرة، وعمودٌ مجرَّدٌ يصير ملتبساً.
     *
     * والفحصُ على تاريخ **المستند** لا لحظةِ إدخاله: الإقفال محاسبيٌّ، وهو
     * التاريخ نفسه الذي يفحصه منعُ الحفظ — فما مُنع كتابةً لا يظهر قراءةً.
     *
     * **ولماذا مدىً نصفُ مفتوح لا `whereNotBetween`؟**
     *
     * لأن من الأعمدة ما هو `datetime` لا `date` — `payments_process.payment_date`
     * منها. و`NOT BETWEEN '2025-01-01' AND '2025-12-31'` يقابل الطرفَ
     * الأخير بمنتصف ليله، فسندٌ حُرّر في ٣١ ديسمبر ظهراً يقع **بعد**
     * الحدّ فينجو من الاستبعاد: يوماً كاملاً من سنةٍ أُقفلت يظلّ ظاهراً
     * في شاشةٍ تَعِد بإخفائها. والقياسُ هنا `[from, to+1)` فيدخل اليومُ
     * الأخير بساعاته كلِّها، ويستوي العمودان.
     *
     * والصفوفُ التي لا تاريخ لها تخرج كما كانت تخرج مع `whereNotBetween`:
     * مقارنةُ `NULL` لا تصدُق، فلا يقلب هذا التغييرُ سلوكاً قائماً.
     *
     * @param  mixed  $query  باني استعلامٍ من Eloquent أو من DB
     */
    public static function exclude($query, string $column): void
    {
        /*
            **مدىً طلبه المستخدم صراحةً يُعرض بما فيه — ولو أُقفل.**

            الإخفاءُ للعرض الافتراضي: الشاشةُ تفتح على ما هو جارٍ ولا يزاحمه ما
            أُقفل. لكنّه كان يُطبَّق حتى على من كتب تواريخَ السنة المقفلة بيده:
            فبعد إقفال السنة لا تُرى فاتورةٌ منها في شاشة المبيعات، ولا يُستخرج
            تقريرُ مبيعاتها أو أرباحها — والسنةُ المقفلة تُراجَع وتُقارن
            ويُقدَّم عنها الإقرار. والإقفالُ يمنع الكتابةَ لا القراءة؛ ومنعُ
            الكتابة باقٍ كما هو في `AccountingPeriodService::check()`.
        */
        if (static::explicitRangeRequested()) {
            return;
        }

        foreach (static::all() as [$from, $to]) {
            $after = Carbon::parse($to)->addDay()->toDateString();

            $query->where(function ($scope) use ($column, $from, $after) {
                $scope->where($column, '<', $from)
                    ->orWhere($column, '>=', $after);
            });
        }
    }

    /**
     * مفاتيحُ المدى في نماذج البحث — تختلف أسماؤها بين الشاشات والتقارير.
     * `period_id` منتقي الفترة المحاسبية، و`date_type`/`date_range` المدى السريع.
     */
    public const RANGE_INPUTS = [
        'from_date', 'to_date', 'date_from', 'date_to',
        'start_date_from', 'start_date_to', 'start_date', 'end_date',
        'from', 'to', 'date_type', 'date_range', 'period_id',
    ];

    /** هل طلب المستخدمُ مدىً بعينه في هذا الطلب؟ (في الأوامر والمهامّ: طلبٌ فارغ ⇒ لا) */
    public static function explicitRangeRequested(): bool
    {
        $request = request();

        foreach (static::RANGE_INPUTS as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /** يُنسى المحفوظ — بعد إقفالٍ أو إعادةِ فتحٍ داخل الطلب نفسه. */
    public static function forget(): void
    {
        static::$memo = null;
    }
}
