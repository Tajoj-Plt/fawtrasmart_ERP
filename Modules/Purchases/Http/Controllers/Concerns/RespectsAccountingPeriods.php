<?php

namespace Modules\Purchases\Http\Controllers\Concerns;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\AccountingPeriodGuard;
use App\Support\ClosedPeriodRanges;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * الفترات المحاسبية والإقفال السنوي في وحدة المشتريات.
 *
 * **ثلاثةُ أوجهٍ لنفس الإعداد**
 *
 * 1. *الكتابة*: كل عملية مشتريات تُولّد قيداً — فاتورة شراء، مرتجع، إشعار
 *    مدين، سند صرف — تُفحَص بتاريخها. الفترة المقفلة تردّ العملية برسالة
 *    تسمّي السبب، إلّا لمن يملك صلاحية التجاوز المضبوطة في الشاشة.
 *
 *    هذا الوجه كان موجوداً في المشتريات قبل هذه السمة عبر
 *    `App\Support\AccountingPeriodGuard` — ويبقى هو المرجع، فلا يُنسخ
 *    المنطق مرّتين. السمةُ تُغلّفه ليُنادى بالاسم نفسه في الوحدتين.
 *
 * 2. *النطاق الافتراضي*: شاشاتُ المشتريات كانت تبدأ بلا نطاق تاريخ إطلاقاً
 *    فتعرض تاريخ المنشأة كلّه، وبطاقاتُ الملخّص تجمع سنواتٍ أُقفلت وأُصدرت
 *    قوائمها المالية. صارت تبدأ من السنة المالية الجارية.
 *
 * 3. *الإخفاء*: والنطاقُ الافتراضي وحدَه وعدٌ لا ضمان — من ضبط التاريخ
 *    يدوياً أعاد مستنداتِ سنةٍ مقفلة إلى الشاشة وإلى إحصائها. فما أُقفل
 *    يُستبعَد من الاستعلام نفسِه عبر `excludeClosedRanges()`، أياً كان
 *    النطاقُ المطلوب. وهذا ما نقص المشترياتِ حتى الآن: كان لها المنعُ
 *    كتابةً بلا إخفاءٍ قراءةً، فتظهر في شاشاتها مستنداتٌ لا تقبل هي نفسُها
 *    أن تُكتب اليوم.
 *
 * **ما دام القفل غير مفروض**
 *
 * `enforce_lock` مطفأً يعني أن الفحص يسمح دائماً وأن `ClosedPeriodRanges`
 * لا تستبعد شيئاً — الفترات سجلٌّ تنظيمي حتى تقرّر المنشأة فرضها. أمّا
 * نطاقُ العرض فيعمل في الحالتين: هو جوابٌ عن «على أي مدى أقرأ؟» لا عن
 * «أيُسمح لي بالكتابة؟».
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods النسخة المقابلة في المبيعات
 * @see \App\Support\AccountingPeriodGuard مرجعُ منع الكتابة
 * @see \App\Support\ClosedPeriodRanges تعريفُ «ما أُقفل»، مشتركٌ بين الوحدتين
 */
trait RespectsAccountingPeriods
{
    /** سبب منع القيد بهذا التاريخ — أو `null` إن كان مسموحاً. */
    protected function accountingPeriodBlock(Carbon|string|null $date): ?string
    {
        return AccountingPeriodGuard::blockReason($date);
    }

    /** أمسموح القيد بهذا التاريخ؟ */
    protected function accountingPeriodAllows(Carbon|string|null $date): bool
    {
        return AccountingPeriodGuard::allows($date);
    }

    /**
     * النطاق الافتراضي لشاشات المشتريات — بترتيب التفضيل:
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

               كانت الشاشة تفتح على الفترة التي يقع فيها اليوم، ومن قسّم
               سنته شهوراً — وهو الافتراضُ المولَّد — فُتحت له على شهرٍ
               واحد، فيرى من مشترياتِ سنته أيّامَها الأخيرة ويظنّ الباقي
               ضائعاً. وتقسيمُ السنة إلى شهورٍ قُصد به دقّةُ **الإقفال**
               لا ضيقُ **العرض**: من أراد إقفال فبراير وحده لا يريد أن
               تُخفى عنه أحد عشر شهراً معه.

               فالافتتاح على السنة، والإقفالُ يبقى شهرياً — ولا تظهر فترةٌ
               مقفلةٌ داخلها لأن `closedDateRanges()` تستبعدها. */
            $year = FiscalYear::forDate($today);

            if ($year) {
                return [
                    'start' => Carbon::parse($year->start_date),
                    'end' => Carbon::parse($year->end_date),
                    'period' => AccountingPeriod::forDate($today),
                    'source' => 'fiscal_year',
                ];
            }

            $period = AccountingPeriod::forDate($today);

            if ($period) {
                return [
                    'start' => Carbon::parse($period->start_date),
                    'end' => Carbon::parse($period->end_date),
                    'period' => $period,
                    'source' => 'period',
                ];
            }

            /* **تقويمٌ غير مضبوطٍ أصلاً ← التاريخ كلُّه، لا الشهرُ الجاري.**

               منشأةٌ لم تُنشئ سنةً ماليةً ولا فترةً واحدة لم تختر مدىً، فلا
               يُخترع لها. كانت تقع على `default_period_type` (شهريّ افتراضياً)
               فتفتح شاشاتُ الفواتير والمرتجعات والإشعارات على الشهر الجاري
               وحده، وتُسمّيه «السنة المالية الجارية» — فتبدو الفواتير ضائعة.
               والمبيعات لم تظهر عليها العلّة لأنها تُصفّي بـ`created_at` لا
               بتاريخ المستند.

               فالمدى من أول مستند شراءٍ إلى آخر السنة الجارية؛ ومن ضبط تقويمه
               يعود إلى السنة المالية أعلاه تلقائياً. */
            if (! FiscalYear::query()->exists() && ! AccountingPeriod::query()->exists()) {
                $first = \Illuminate\Support\Facades\DB::table('purchase_invoices')->min('date');

                return [
                    'start' => $first ? Carbon::parse($first)->startOfDay() : $today->copy()->startOfYear(),
                    'end' => $today->copy()->endOfYear(),
                    'period' => null,
                    'source' => 'all',
                ];
            }

            $type = app(AccountingPeriodService::class)->settings()->default_period_type;

            return $this->rangeFromType($type, $today) + ['period' => null, 'source' => 'type'];
        } catch (\Throwable $e) {
            Log::warning('تعذّر اشتقاق الفترة الافتراضية لشاشة المشتريات — استُعمل الشهر الجاري', [
                'error' => $e->getMessage(),
            ]);

            return [
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy()->endOfMonth(),
                'period' => null,
                'source' => 'type',
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
        string $startKey = 'start_date_from',
        string $endKey = 'start_date_to'
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
            'source' => $default['source'] ?? 'fiscal_year',
        ];
    }

    /**
     * سياقُ الفترة المحاسبية لتقريرٍ ما — النطاق، والفترة، والسنة، وحالتُها.
     *
     * منقولةٌ حرفياً من نظيرتها في وحدة العملاء
     * ({@see \Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods::reportPeriodContext()})
     * كي تُرسم في تقارير المشتريات مُنتقياتُ السنة المالية والفترة المحاسبية
     * وشريطُ حالة الفترة بالأجزاء المشتركة نفسِها
     * (`client::customers.CustomerReport.partials.period-selector` و`period-banner`).
     *
     * ══════════ ترتيبُ حسم النطاق ══════════
     *
     *   1. فترةٌ محاسبية اختِيرت صراحةً  ← حدودُها هي النطاق.
     *   2. سنةٌ مالية اختِيرت صراحةً      ← حدودُها هي النطاق.
     *   3. تاريخانِ كتبهما المستخدم       ← كما كتبهما.
     *   4. الافتراضي                      ← السنةُ المالية الجارية.
     *
     * والتقريرُ لا يُخفي ما أُقفل: الإقفال يمنع الكتابة لا القراءة، فلا
     * `excludeClosedRanges()` هنا — الحالةُ تُعلَن في الشاشة فقط.
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
           إلّا حين تُختار سنةٌ كاملة: فلا فترةَ تُدَّعى وتبقى حالةُ السنة هي الحاكمة. */
        $anchor = Carbon::parse($start);
        $wholeYearPicked = $request->filled('fiscal_year_id') && ! $request->filled('accounting_period_id');

        if (! $period && ! $wholeYearPicked) {
            $period = AccountingPeriod::forDate($anchor);
        }

        $year = $year ?: ($period?->fiscalYear ?: FiscalYear::forDate($anchor));

        // حالةُ المدى: أشدُّ ما فيه — سنةٌ مقفلةٌ تُقفل ما تحتها
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
            Log::warning('تعذّر جلب الفترات لمنتقي تقرير المشتريات', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /** السنوات المالية المعروضة في المنتقي — المقفلةُ تبقى قابلةً للاختيار. */
    protected function selectableFiscalYears()
    {
        try {
            return FiscalYear::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'status']);
        } catch (\Throwable $e) {
            Log::warning('تعذّر جلب السنوات المالية لمنتقي تقرير المشتريات', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /**
     * المدَيات المقفلة — التعريفُ في `ClosedPeriodRanges` لتشترك فيه
     * شاشاتُ المستندات ولوحةُ المشتريات ودفترُ المورّدين معاً.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function closedDateRanges(): array
    {
        return ClosedPeriodRanges::all();
    }

    /** استبعادُ ما أُقفل من استعلامٍ ما، على عمودِ تاريخِ المستند. */
    protected function excludeClosedRanges($query, string $column): void
    {
        ClosedPeriodRanges::exclude($query, $column);
    }

    /**
     * أهذا هو الفرع الرئيسيّ — أي أتُعرض له المشتريات كلُّها بلا تقييد فرع؟
     *
     * `null` تعني «جميع الفروع» من مبدّل الهيدر، وهي بلا تقييدٍ أصلاً —
     * تُعامَل معاملته فلا يُبنى لها شرطٌ لا أثر له.
     *
     * والفرعُ الرئيسيّ مركزُ المنشأة لا فرعٌ من فروعها: كان يُعامَل كأيّ
     * عقدةٍ أخرى فيرى ما وُسِم برقمه وحدَه — وهو في العادة أقلُّ العقد
     * وسماً، لأنّ المستندات تُنسَب إلى الفروع والمناطق العاملة لا إلى
     * المركز.
     *
     * القاعدةُ نفسُها التي تعمل بها لوحةُ المشتريات في `resolveBranch`
     * منذ كُتبت — نُقلت هنا لتشملَ شاشاتِ المستندات معها.
     */
    protected function isMainBranch(?int $branchId): bool
    {
        if (! $branchId) {
            return true;
        }

        return (bool) Branch::where('id', $branchId)->value('is_main');
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
