<?php

namespace App\Support;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * فلترُ الفترة المحاسبية — مصدرٌ واحد لكلّ شاشةٍ تُقرأ على فترة.
 *
 * **ما كان**
 *
 * كلُّ شاشةٍ تسأل عن الفترة بالتاريخ، وكلٌّ تسأل على وجهها: واحدةٌ تقرأ
 * `from_date` وأخرى `date_from`، وواحدةٌ تقصّ عند اليوم وأخرى لا. فالفترةُ
 * نفسُها تُقرأ رقمَين مختلفَين في شاشتَين، ولا يُعرف أيُّهما الصواب.
 *
 * **ما صار**
 *
 * الفترةُ **علاقة**: `accounting_period_id` مختومٌ في المستند لحظةَ إنشائه
 * (انظر `App\Models\Concerns\BelongsToAccountingPeriod`). والفلترُ هنا يترجم
 * ما طلبه المستخدم إلى شرطٍ على تلك العلاقة لا إلى مدىً يُحسب.
 *
 * **والنطاقُ الافتراضي هو السنة المالية الجارية لا الفترة وحدها.**
 *
 * المحاسبُ يعمل على سنته: يقارن الشهرَ بالشهر، ويراجع ما مضى منها، ويقفلها
 * في آخرها. وحصرُ الشاشة في فترةٍ واحدة يُخفي عنه بقيّةَ سنته بلا سبب —
 * فالفترةُ وحدةُ إقفالٍ لا وحدةُ عرض. ومن أراد فترةً بعينها اختارها.
 *
 * **الأولوية:**
 *
 *   1. `period_id` صريحاً — فترةٌ بعينها، أو `year:<id>` لسنةٍ كاملة، أو
 *      `all` لرفع الحصر، أو `none` لعرض ما خرج عن التقويم.
 *   2. السنةُ المالية الجارية — التي تحوي اليوم، بفتراتها كلِّها.
 *   3. **لا سنةَ مالية أصلاً ⇒ لا حصر**: تُعرض العملياتُ كلُّها. فالنظامُ
 *      الذي لم يُرسَم تقويمُه بعد لا معيارَ فيه يُحتكم إليه، وإخفاءُ البيانات
 *      بمعيارٍ لا وجود له أسوأُ من عرضها.
 */
class AccountingPeriodFilter
{
    public const ALL = 'all';

    public const NONE = 'none';

    /** بادئةُ اختيار سنةٍ كاملة: `year:12` */
    public const YEAR_PREFIX = 'year:';

    /**
     * ما طُلب من فترة — جاهزاً للتطبيق والعرض.
     *
     * @return array{
     *     period_id: int|string|null, period: ?AccountingPeriod, year: ?FiscalYear,
     *     period_ids: array<int>, label: string, is_default: bool, all: bool,
     *     none: bool, unconfigured: bool, no_calendar: bool, from: ?string, to: ?string
     * }
     */
    public static function resolve(Request $request, string $key = 'period_id'): array
    {
        $requested = $request->input($key);

        // ١) رفعُ الحصر — باختيارٍ صريح من المستخدم
        if ($requested === self::ALL) {
            return self::shape(null, null, self::ALL, 'كل الفترات');
        }

        // ٢) ما خرج عن التقويم — ليُرى فيُصحَّح
        if ($requested === self::NONE) {
            return self::shape(null, null, self::NONE, 'بلا فترة');
        }

        // ٣) سنةٌ كاملة
        if (is_string($requested) && str_starts_with($requested, self::YEAR_PREFIX)) {
            $year = self::findYear(substr($requested, strlen(self::YEAR_PREFIX)));

            if ($year) {
                return self::shape(null, $year, self::YEAR_PREFIX.$year->id, $year->name);
            }
        }

        // ٤) فترةٌ بعينها
        if ($requested !== null && $requested !== '') {
            $period = self::find($requested);

            if ($period) {
                return self::shape($period, $period->fiscalYear, $period->id, $period->name);
            }
        }

        // ٥) الافتراضي: السنة المالية الجارية بفتراتها كلِّها
        $currentYear = self::currentYear();

        if ($currentYear) {
            return self::shape(
                null,
                $currentYear,
                self::YEAR_PREFIX.$currentYear->id,
                $currentYear->name,
                isDefault: true,
                // سنةٌ جاريةٌ بلا فترةٍ تحوي اليوم: التقويمُ ناقص، فيُعلَن
                unconfigured: self::current() === null,
            );
        }

        /*
            ٦) لا سنةَ مالية في النظام — لا معيارَ يُحتكم إليه.

            فتُعرض العملياتُ كلُّها، ويُعلَن الغياب في الشاشة ليُضبط. وكان
            العرضُ هنا يُحصَر في «أحدث فترة» أو «الشهر الجاري» — وذاك حصرٌ
            بمعيارٍ لا وجود له، يُخفي بياناتٍ قائمةً بلا سببٍ يقوله للمستخدم.
        */
        return self::shape(
            null,
            null,
            null,
            'لا سنة مالية معرَّفة',
            isDefault: true,
            unconfigured: true,
            noCalendar: true,
        );
    }

    /**
     * تطبيقُ الفلتر على استعلام — الشرطُ على العلاقة لا على التاريخ.
     *
     * @param  Builder  $query  استعلامُ مودلٍ يستعمل `BelongsToAccountingPeriod`
     */
    public static function apply(Builder $query, array $resolved): Builder
    {
        $column = $query->qualifyColumn('accounting_period_id');

        if (! empty($resolved['all']) || ! empty($resolved['no_calendar'])) {
            return $query;
        }

        if (! empty($resolved['none'])) {
            return $query->whereNull($column);
        }

        // سنةٌ كاملة: فتراتُها كلُّها
        if (! empty($resolved['period_ids'])) {
            return $query->whereIn($column, $resolved['period_ids']);
        }

        if ($resolved['period_id'] === null) {
            return $query;
        }

        return $query->where($column, $resolved['period_id']);
    }

    /**
     * الشرطُ نفسُه على استعلامٍ خام (`DB::table`) بلقبِ جدولٍ صريح.
     *
     * تُستعمل حيث يُبنى الاستعلام بالانضمام لا بالمودل — كحركة مركز التكلفة
     * ولوحة المالية.
     */
    public static function applyRaw($query, array $resolved, string $column = 'e.accounting_period_id')
    {
        if (! empty($resolved['all']) || ! empty($resolved['no_calendar'])) {
            return $query;
        }

        if (! empty($resolved['none'])) {
            return $query->whereNull($column);
        }

        if (! empty($resolved['period_ids'])) {
            return $query->whereIn($column, $resolved['period_ids']);
        }

        if ($resolved['period_id'] !== null) {
            return $query->where($column, $resolved['period_id']);
        }

        return $query;
    }

    /** هل في النظام سنةٌ مالية أصلاً؟ */
    public static function hasCalendar(): bool
    {
        try {
            return FiscalYear::exists();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** الفترةُ التي تحوي اليوم. */
    public static function current(): ?AccountingPeriod
    {
        try {
            return AccountingPeriod::forDate(Carbon::today());
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * السنةُ المالية الجارية — التي تحوي اليوم.
     *
     * ولا يُعوَّل على `FiscalYear::current()` وحدها: تعيد السنةَ المعلَّمة
     * جاريةً وقد لا تحوي اليوم (سنةٌ لم تبدأ بعد، أو انتهت ولم تُقفل). فالحاوية
     * لليوم أوّلاً، ثمّ المعلَّمة إن حوته.
     */
    public static function currentYear(): ?FiscalYear
    {
        try {
            $today = Carbon::today();

            $containing = FiscalYear::whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->orderByDesc('start_date')
                ->first();

            if ($containing) {
                return $containing;
            }

            $marked = FiscalYear::current();

            if ($marked && $marked->start_date && $marked->end_date
                && $today->betweenIncluded(
                    Carbon::parse($marked->start_date)->startOfDay(),
                    Carbon::parse($marked->end_date)->endOfDay()
                )) {
                return $marked;
            }

            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected static function find($id): ?AccountingPeriod
    {
        try {
            return AccountingPeriod::with('fiscalYear')->find($id);
        } catch (Throwable $e) {
            return null;
        }
    }

    protected static function findYear($id): ?FiscalYear
    {
        try {
            return FiscalYear::find($id);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** صياغةٌ موحّدة — الشاشاتُ تقرأ المفاتيحَ نفسَها أينما كانت. */
    protected static function shape(
        ?AccountingPeriod $period,
        ?FiscalYear $year,
        $periodId,
        string $label,
        bool $isDefault = false,
        bool $unconfigured = false,
        bool $noCalendar = false,
    ): array {
        // سنةٌ مختارة: فتراتُها كلُّها هي النطاق
        $periodIds = [];

        if (! $period && $year) {
            try {
                $periodIds = $year->periods()->pluck('id')->all();
            } catch (Throwable $e) {
                $periodIds = [];
            }
        }

        return [
            'period_id' => $periodId,
            'period' => $period,
            'year' => $year,
            'period_ids' => $periodIds,
            'label' => $label,
            'is_default' => $isDefault,
            'all' => $periodId === self::ALL,
            'none' => $periodId === self::NONE,
            'unconfigured' => $unconfigured,
            'no_calendar' => $noCalendar,
            // حدّا النطاق — للعرض وللتقارير التي تقبل مدىً تاريخياً
            'from' => $period?->start_date?->toDateString()
                ?? ($year?->start_date ? Carbon::parse($year->start_date)->toDateString() : null),
            'to' => $period?->end_date?->toDateString()
                ?? ($year?->end_date ? Carbon::parse($year->end_date)->toDateString() : null),
        ];
    }
}
