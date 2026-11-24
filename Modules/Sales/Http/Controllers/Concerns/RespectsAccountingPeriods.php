<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\ClosedPeriodRanges;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * الفترات المحاسبية والإقفال السنوي في وحدة المبيعات.
 *
 * **وجهان لنفس الإعداد**
 *
 * 1. *الكتابة*: كل عملية مبيعات تُولّد قيداً — حفظ فاتورة، سند قبض،
 *    مرتجع — تُفحَص بتاريخها. الفترة المقفلة تردّ العملية برسالة تسمّي
 *    السبب، إلّا لمن يملك صلاحية التجاوز المضبوطة في الشاشة.
 *
 *    كانت الفاتورة تُحفَظ بأيّ تاريخ، فيُرحَّل قيدٌ إلى سنةٍ أُقفلت
 *    وأُصدرت قوائمها المالية — وهذا ما يمنعه الإقفال أصلاً.
 *
 * 2. *العرض*: شاشة الفواتير كانت تبدأ بلا نطاق تاريخ إطلاقاً فتعرض
 *    تاريخ المنشأة كلّه، وإحصاءاتها تجمع سنواتٍ مضت. صارت تبدأ من الفترة
 *    المحاسبية الجارية.
 *
 * **ما دام القفل غير مفروض**
 *
 * `enforce_lock` مطفأً يعني أن `check()` تسمح دائماً — الفترات سجلٌّ
 * تنظيمي حتى تقرّر المنشأة فرضها. فالربط هنا لا يغيّر سلوك النظام اليوم،
 * ويغيّره كلّه فور تفعيل الضابط، بلا سطر واحد يُعدَّل.
 *
 * @see \Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods النسخة الأمّ
 */
trait RespectsAccountingPeriods
{
    /**
     * سبب منع القيد بهذا التاريخ — أو `null` إن كان مسموحاً.
     *
     * تُبتلع أخطاء الخدمة عمداً: خدمة الفترات غير مهيّأة يجب ألّا تُعطّل
     * حركة المبيعات — لكن الابتلاع يُكتب في السجلّ فلا يمرّ فحصٌ معطوب
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
            Log::warning('فحص الفترة المحاسبية أخفق — سُمح بعملية المبيعات', [
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
     * النطاق الافتراضي لشاشات المبيعات وتقاريرها — بترتيب التفضيل:
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
               واحد، فيرى من مبيعات سنته أيّامَها الأخيرة ويظنّ الباقي
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

            /* **تقويمٌ غير مضبوطٍ أصلاً ← التاريخ كلُّه، لا الشهرُ الجاري.**

               منشأةٌ لم تُنشئ سنةً ماليةً ولا فترةً واحدة لم تختر مدىً، فلا
               يُخترع لها. كانت تقع على `default_period_type` (شهريّ افتراضياً)
               فتفتح شاشاتُ الفواتير والمرتجعات والإشعارات الدائنة على الشهر
               الجاري وحده، فتبدو المبيعات ضائعة. نفسُ ما صُحّح في المشتريات.

               المدى من أوّل مستند بيعٍ إلى آخر السنة الجارية — والشاشات
               تُصفّي بـ`created_at` فمنه يُقرأ الأوّل. */
            if (! FiscalYear::query()->exists() && ! AccountingPeriod::query()->exists()) {
                $first = collect([
                    DB::table('invoices')->min('created_at'),
                    DB::table('credit_notifications')->min('created_at'),
                ])->filter()->min();

                return [
                    'start' => $first ? Carbon::parse($first)->startOfDay() : $today->copy()->startOfYear(),
                    'end' => $today->copy()->endOfYear(),
                    'period' => null,
                ];
            }

            $type = app(AccountingPeriodService::class)->settings()->default_period_type;

            return $this->rangeFromType($type, $today) + ['period' => null];
        } catch (\Throwable $e) {
            Log::warning('تعذّر اشتقاق الفترة الافتراضية لشاشة المبيعات — استُعمل الشهر الجاري', [
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
     * المدَيات المقفلة — التعريفُ في `ClosedPeriodRanges` لتشترك فيه
     * شاشاتُ المستندات ولوحةُ المبيعات معاً.
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
     * أهذا هو الفرع الرئيسيّ — أي أتُعرض له المبيعات كلُّها بلا تقييد فرع؟
     *
     * `null` تعني «جميع الفروع» من مبدّل الهيدر، وهي بلا تقييدٍ أصلاً —
     * تُعامَل معاملته فلا يُبنى لها شرطٌ لا أثر له.
     *
     * القاعدةُ نفسُها التي تعمل بها لوحةُ المبيعات في `resolveDashboardBranch`
     * منذ كُتبت — نُقلت هنا لتشملَ شاشاتِ المستندات معها.
     */
    protected function isMainBranch(?int $branchId): bool
    {
        if (! $branchId) {
            return true;
        }

        return (bool) Branch::where('id', $branchId)->value('is_main');
    }

    /*
     * `seesAllSalesDocuments()` كانت هنا — ونُقلت إلى
     * {@see ScopesSalesVisibility}: سؤالُها «على أيّ صفوفٍ أرى؟» لا «هل
     * الفترةُ مقفلة؟»، وقد صارت هناك رأسَ سلَّمٍ خماسيٍّ لا حالتين.
     */

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
