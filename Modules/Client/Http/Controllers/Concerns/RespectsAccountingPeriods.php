<?php

namespace Modules\Client\Http\Controllers\Concerns;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * الفترات المحاسبية والإقفال السنوي في وحدة العملاء.
 *
 * **وجهان لنفس الإعداد**
 *
 * 1. *الكتابة*: كل عملية عميل تُولّد قيداً — تحويل رصيد إلى دينٍ مشكوك
 *    فيه، وشطبُ دينٍ معدوم، وعكسُ أيٍّ منهما، وتسويةُ رصيدٍ افتتاحي —
 *    تُفحَص بتاريخها. الفترة المقفلة تردّ العملية برسالة تسمّي السبب،
 *    إلّا لمن يملك صلاحية التجاوز المضبوطة في الشاشة.
 *
 *    كان الشطب يُحفَظ بأيّ تاريخ، فيُرحَّل قيدٌ إلى سنةٍ أُقفلت وأُصدرت
 *    قوائمها المالية — وهذا ما يمنعه الإقفال أصلاً.
 *
 * 2. *العرض*: شاشات كشف الحساب وتقارير العميل كانت تبدأ بلا نطاق تاريخ
 *    فتعرض تاريخ المنشأة كلَّه. صارت تبدأ من الفترة المحاسبية الجارية.
 *
 * **ما دام القفل غير مفروض**
 *
 * `enforce_lock` مطفأً يعني أن `check()` تسمح دائماً — الفترات سجلٌّ
 * تنظيمي حتى تقرّر المنشأة فرضها. فالربط هنا لا يغيّر سلوك النظام اليوم،
 * ويغيّره كلَّه فور تفعيل الضابط، بلا سطر واحد يُعدَّل.
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods النسخة الأمّ
 */
trait RespectsAccountingPeriods
{
    /**
     * سبب منع القيد بهذا التاريخ — أو `null` إن كان مسموحاً.
     *
     * تُبتلع أخطاء الخدمة عمداً: خدمة الفترات غير مهيّأة يجب ألّا تُعطّل
     * حركة العملاء — لكن الابتلاع يُكتب في السجلّ فلا يمرّ فحصٌ معطوب
     * بلا أثر.
     */
    protected function accountingPeriodBlock(Carbon|string|null $date): ?string
    {
        try {
            $service = app(AccountingPeriodService::class);

            // السنةُ المالية المقفلة قفلٌ نهائيّ — لا تتجاوزها الصلاحية
            if ($closedYear = $service->closedYearReason($date ?: now())) {
                return $closedYear;
            }

            $override = $service->settings()->override_permission;

            if ($override && auth()->user()?->can($override)) {
                return null;
            }

            return $service->blockReason($date ?: now());
        } catch (\Throwable $e) {
            Log::warning('فحص الفترة المحاسبية أخفق — سُمح بعملية العميل', [
                'date' => is_object($date) ? (string) $date : $date,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** أمسموح القيد بهذا التاريخ؟ */
    protected function accountingPeriodAllows(Carbon|string|null $date): bool
    {
        return $this->accountingPeriodBlock($date) === null;
    }

    /**
     * النطاق الافتراضي لشاشات العملاء وتقاريرها — بترتيب التفضيل:
     *
     *   1. السنة المالية التي يقع فيها اليوم.
     *   2. الفترة المحاسبية التي يقع فيها اليوم — حين لا سنةَ تغطّيه.
     *   3. نطاق مشتقّ من `default_period_type` حول اليوم.
     *   4. الشهر الجاري — حين لا يكون التقويم مهيّأً أصلاً.
     *
     * @return array{start: Carbon, end: Carbon, period: ?AccountingPeriod}
     */
    protected function defaultReportRange(): array
    {
        $today = Carbon::today();

        try {
            /* **السنةُ المالية لا الفترةُ الشهرية.**

               كان التقرير يفتح على الفترة التي يقع فيها اليوم، ومن قسّم سنته
               شهوراً — وهو الافتراضُ المولَّد — فُتح له على شهرٍ واحد، فيقرأ
               من حساب عميله أيّامَه الأخيرة ويظنّ الباقي ضائعاً. وتقسيمُ
               السنة شهوراً قُصد به دقّةُ **الإقفال** لا ضيقُ **العرض**.

               وهي القاعدةُ نفسُها في تقارير المبيعات والمشتريات. */
            $year = FiscalYear::forDate($today);

            if ($year) {
                return [
                    'start' => Carbon::parse($year->start_date),
                    'end' => Carbon::parse($year->end_date),
                    'period' => AccountingPeriod::forDate($today),
                ];
            }

            $period = AccountingPeriod::forDate($today);

            if ($period) {
                return [
                    'start' => Carbon::parse($period->start_date),
                    'end' => Carbon::parse($period->end_date),
                    'period' => $period,
                ];
            }

            $type = app(AccountingPeriodService::class)->settings()->default_period_type;

            return $this->rangeFromType($type, $today) + ['period' => null];
        } catch (\Throwable $e) {
            Log::warning('تعذّر اشتقاق الفترة الافتراضية لشاشة العملاء — استُعمل الشهر الجاري', [
                'error' => $e->getMessage(),
            ]);

            return [
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy()->endOfMonth(),
                'period' => null,
            ];
        }
    }

    /**
     * نطاق التاريخ المطبَّق — ما اختاره المستخدم، وإلا الافتراضي أعلاه.
     *
     * @return array{start: string, end: string, period: ?AccountingPeriod, is_default: bool}
     */
    protected function reportDateRange(
        Request $request,
        string $startKey = 'from_date',
        string $endKey = 'to_date'
    ): array {
        $default = $this->defaultReportRange();

        $hasStart = $request->filled($startKey);
        $hasEnd = $request->filled($endKey);

        $start = $hasStart ? $request->input($startKey) : $default['start']->format('Y-m-d');
        $end = $hasEnd ? $request->input($endKey) : $default['end']->format('Y-m-d');

        // نطاق مقلوب يُصحَّح بدل أن يُرجع شاشةً فارغة بلا تفسير
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start,
            'end' => $end,
            'period' => $default['period'],
            'is_default' => ! $hasStart && ! $hasEnd,
        ];
    }

    /**
     * سياقُ الفترة المحاسبية لتقريرٍ ما — النطاق، والفترة، والسنة، وحالتُها.
     *
     * ══════════ التقاريرُ تُخالف شاشاتِ المستندات ══════════
     *
     * شاشةُ الفواتير **تُخفي** ما وقع في فترةٍ مقفلة. والتقريرُ **لا
     * يُخفيه**: الإقفال يمنع الكتابة لا القراءة، وتقريرُ سنةٍ أُقفلت وأُصدرت
     * قوائمُها هو أوّلُ ما يُطلب بعد إقفالها — للمراجعة والطباعة والتصدير.
     * فلا `ClosedPeriodRanges::exclude()` في هذا الملف ولا فيما يستعمله،
     * وإخفاءُ صفٍّ تاريخيٍّ من تقرير عطبٌ لا ميزة.
     *
     * ما يفعله الإقفال في التقرير أمران فقط: يُعلن نفسه في الشاشة
     * (`status`)، ويمنع أيّ إجراءٍ يكتب من داخلها (`is_locked`).
     *
     * ══════════ ترتيبُ حسم النطاق ══════════
     *
     *   1. فترةٌ محاسبية اختِيرت صراحةً  ← حدودُها هي النطاق.
     *   2. سنةٌ مالية اختِيرت صراحةً      ← حدودُها هي النطاق.
     *   3. تاريخانِ كتبهما المستخدم       ← كما كتبهما.
     *   4. الافتراضي                      ← السنةُ المالية الجارية.
     *
     * واختيارُ الفترة يغلب حقلَي التاريخ عمداً: هما وجهان لسؤالٍ واحد، فلو
     * تُركا يتنازعان لأمكن أن تقول الشاشةُ «فبراير» ويقرأ الاستعلامُ مارس.
     * ولذلك لا يُضاف فلترُ تاريخٍ ثانٍ: القائمةُ تملأ الحقلين القائمَين.
     *
     * @return array{
     *     start: string, end: string, period: ?AccountingPeriod, fiscal_year: ?FiscalYear,
     *     status: string, is_locked: bool, is_default: bool, enforced: bool,
     *     label: string, periods: \Illuminate\Support\Collection, fiscal_years: \Illuminate\Support\Collection
     * }
     */
    protected function reportPeriodContext(
        Request $request,
        string $startKey = 'from_date',
        string $endKey = 'to_date'
    ): array {
        $period = $request->filled('accounting_period_id')
            ? AccountingPeriod::find($request->input('accounting_period_id'))
            : null;

        $year = $request->filled('fiscal_year_id')
            ? FiscalYear::find($request->input('fiscal_year_id'))
            : null;

        if ($period) {
            $start = Carbon::parse($period->start_date)->format('Y-m-d');
            $end = Carbon::parse($period->end_date)->format('Y-m-d');
            $year = $period->fiscalYear;
            $isDefault = false;
        } elseif ($year) {
            $start = Carbon::parse($year->start_date)->format('Y-m-d');
            $end = Carbon::parse($year->end_date)->format('Y-m-d');
            $isDefault = false;
        } else {
            $range = $this->reportDateRange($request, $startKey, $endKey);
            $start = $range['start'];
            $end = $range['end'];
            $isDefault = $range['is_default'];
        }

        /* ما لم تُختَر فترةٌ صراحةً، تُستنتج من بداية النطاق — لتُعرض حالتُها.

           **إلّا حين تُختار سنةٌ كاملة**: المدى حينئذٍ اثنا عشر شهراً، ونسبتُه
           إلى الفترة التي يبدأ بها تجعل الشريطَ يقول «يناير» فوق تقريرٍ يعرض
           السنةَ كلَّها — ويأخذ حالةَ يناير وحده حكماً على السنة. فلا فترةَ
           تُدَّعى، وتبقى حالةُ السنة هي الحاكمة. */
        $anchor = Carbon::parse($start);
        $wholeYearPicked = $request->filled('fiscal_year_id') && ! $request->filled('accounting_period_id');

        if (! $period && ! $wholeYearPicked) {
            $period = AccountingPeriod::forDate($anchor);
        }

        $year = $year ?: ($period?->fiscalYear ?: FiscalYear::forDate($anchor));

        /* حالةُ المدى: أشدُّ ما فيه. سنةٌ مقفلةٌ تُقفل ما تحتها ولو بقيت
           فترةٌ فيها مفتوحةً في عمودها، فلا يُعلَن «مفتوح» داخل سنةٍ أُقفلت. */
        $status = AccountingPeriod::STATUS_OPEN;

        if ($period?->status === AccountingPeriod::STATUS_LOCKED) {
            $status = AccountingPeriod::STATUS_LOCKED;
        } elseif ($period?->status === AccountingPeriod::STATUS_CLOSED
            || $year?->status === FiscalYear::STATUS_CLOSED) {
            $status = AccountingPeriod::STATUS_CLOSED;
        }

        return [
            'start' => $start,
            'end' => $end,
            'period' => $period,
            'fiscal_year' => $year,
            'status' => $status,
            'is_locked' => $status !== AccountingPeriod::STATUS_OPEN,
            'is_default' => $isDefault,
            'enforced' => $this->lockIsEnforced(),
            'label' => $period?->name ?: ($year?->name ?: ''),
            'periods' => $this->selectablePeriods(),
            'fiscal_years' => $this->selectableFiscalYears(),
        ];
    }

    /** أمفروضٌ القفلُ فعلاً؟ — قبل تفعيله الحالةُ سجلٌّ تنظيمي لا قيد. */
    protected function lockIsEnforced(): bool
    {
        try {
            return (bool) app(AccountingPeriodService::class)->settings()->enforce_lock;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** الفترات المعروضة في مُنتقي التقرير — كلُّها، مقفلةً كانت أو مفتوحة. */
    protected function selectablePeriods()
    {
        try {
            return AccountingPeriod::with('fiscalYear')
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date', 'status', 'fiscal_year_id']);
        } catch (\Throwable $e) {
            Log::warning('تعذّر جلب الفترات لمنتقي التقرير', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /** السنوات المالية المعروضة في المنتقي — المقفلةُ تبقى قابلةً للاختيار. */
    protected function selectableFiscalYears()
    {
        try {
            return FiscalYear::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'status']);
        } catch (\Throwable $e) {
            Log::warning('تعذّر جلب السنوات المالية لمنتقي التقرير', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /**
     * نطاق التقويم المقابل لنوع الفترة، محسوباً حول تاريخ.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function rangeFromType(?string $type, Carbon $date): array
    {
        $months = AccountingPeriod::TYPES[$type] ?? 1;

        if ($months >= 12) {
            return ['start' => $date->copy()->startOfYear(), 'end' => $date->copy()->endOfYear()];
        }

        // بداية الشريحة التي يقع فيها الشهر: الربع الثاني يبدأ من أبريل
        $index = intdiv($date->month - 1, $months);
        $start = $date->copy()->startOfYear()->addMonths($index * $months);

        return [
            'start' => $start,
            'end' => $start->copy()->addMonths($months)->subDay()->endOfDay(),
        ];
    }
}
