<?php

namespace Modules\Stock\Http\Controllers\Concerns;

use App\Models\AccountingPeriod;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\AccountingPeriodFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * الفترات المحاسبية في وحدة المخزون — منعُ القيد المقفل، وافتراضُ نطاق العرض.
 *
 * **وجهان لنفس الإعداد**
 *
 * 1. *الكتابة*: أيّ عملية مخزنية تُولّد قيداً — اعتماد إذن، إنهاء أمر تصنيع،
 *    تسوية جرد — تُفحَص بتاريخها. الفترة المقفلة تردّ العملية برسالة تسمّي
 *    السبب، إلّا لمن يملك صلاحية التجاوز المضبوطة في الشاشة.
 *
 * 2. *العرض*: **الفترةُ علاقةٌ لا مدًى.** إذنُ المخزن وتسويةُ الجرد يُختَمان
 *    بـ`accounting_period_id` لحظةَ الإنشاء، فالسؤالُ عن فترةٍ صار مقارنةَ
 *    عمودٍ بمعرّف. والنطاقُ الافتراضي **السنةُ المالية الجارية** — لا فترةٌ
 *    واحدة: المخزنُ يُجرَد على السنة وتُقارَن أشهرُها.
 *
 *    وكانت التقارير تبدأ من `startOfMonth()` مكتوبةً في الشيفرة، فلا علاقة
 *    لما تعرضه بالفترة التي تعمل المنشأة عليها.
 *
 *    والتقاريرُ وحدها تقبل مدىً تاريخياً حرّاً إلى جانب العلاقة: تقريرُ
 *    حركةٍ قد يُطلب على أسبوعٍ يقطع الشهرين، والقائمةُ التشغيلية لا.
 *
 * **ما دام القفل غير مفروض**
 *
 * `enforce_lock` مطفأً يعني أن `check()` تسمح دائماً — الفترات سجلٌّ تنظيمي
 * حتى تقرّر المنشأة فرضها. فالربط هنا لا يغيّر سلوك النظام اليوم، ويغيّره
 * كلّه فور تفعيل الضابط، بلا سطر واحد يُعدَّل.
 */
trait RespectsAccountingPeriods
{
    /**
     * سبب منع القيد بهذا التاريخ — أو `null` إن كان مسموحاً.
     *
     * تُبتلع أخطاء الخدمة عمداً: خدمة الفترات غير مهيّأة يجب ألّا تُعطّل
     * حركة المخزون.
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
            // خدمة الفترات غير مهيّأة يجب ألّا تُعطّل حركة المخزون — لكنّ
            // الابتلاع الصامت أخفى من قبلُ خطأَ نوعٍ في هذه السمة نفسها،
            // فصار كل فحص يمرّ بلا فحص. يُكتب الآن ويُرى
            Log::warning('فحص الفترة المحاسبية أخفق — سُمح بالعملية', [
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
     * فترةُ العرض في المخزون — علاقةً، بالقاعدة نفسِها في المالية.
     *
     * مصدرٌ واحدٌ للوحدتين (`App\Support\AccountingPeriodFilter`): وحدتان
     * تُجيبان عن «أيّ فترة؟» بجوابين مختلفين تُخرجان مخزوناً لا يوافق دفترَه.
     *
     * @return array ناتج `AccountingPeriodFilter::resolve()`
     */
    protected function stockPeriod(?Request $request = null): array
    {
        return AccountingPeriodFilter::resolve($request ?: request());
    }

    /**
     * تقييدُ استعلامٍ بفترة العرض — لمستندٍ يحمل `accounting_period_id`.
     *
     * @param  Builder  $query
     */
    protected function scopeToStockPeriod($query, ?array $period = null)
    {
        return AccountingPeriodFilter::apply($query, $period ?: $this->stockPeriod());
    }

    /**
     * فترةُ تقريرٍ مخزنيّ: العلاقةُ أصلاً، والمدى التاريخيّ حين يُطلب صراحةً.
     *
     * @return array{mode: string, start: ?string, end: ?string, period: array, is_default: bool}
     */
    protected function stockReportScope(
        Request $request,
        string $startKey = 'start_date',
        string $endKey = 'end_date'
    ): array {
        $period = $this->stockPeriod($request);

        $hasStart = $request->filled($startKey);
        $hasEnd = $request->filled($endKey);

        // مدًى كتبه المستخدم يتقدّم: من كتب تاريخين فقد طلبهما
        if ($hasStart || $hasEnd) {
            $start = $hasStart ? $request->input($startKey) : $request->input($endKey);
            $end = $hasEnd ? $request->input($endKey) : $request->input($startKey);

            // نطاق مقلوب يُصحَّح بدل أن يُرجع تقريراً فارغاً بلا تفسير
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }

            return [
                'mode' => 'dates', 'start' => $start, 'end' => $end,
                'period' => $period, 'is_default' => false,
            ];
        }

        /*
            لا حصرَ في حالَين: «كل الفترات» يختارها المستخدمُ صراحةً، ولا
            تقويمَ أصلاً فلا معيارَ يُحتكم إليه.

            وكان الشرطُ على `no_calendar` وحدها، فاختيارُ «كل الفترات» يُترجَم
            إلى حدَّي السنة الجارية — أي عكسَ ما طُلب بالضبط.
        */
        if ($period['all'] || $period['no_calendar']) {
            return [
                'mode' => 'all', 'start' => null, 'end' => null,
                'period' => $period, 'is_default' => true,
            ];
        }

        return [
            'mode' => 'period',
            'start' => $period['from'],
            'end' => $period['to'],
            'period' => $period,
            'is_default' => true,
        ];
    }

    /**
     * النطاق الافتراضي لتقارير المخزون — بترتيب التفضيل:
     *
     *   1. الفترة المحاسبية التي يقع فيها اليوم.
     *   2. نطاق مشتقّ من `default_period_type` حول اليوم (شهري/ربعي/نصفي/سنوي).
     *   3. الشهر الجاري — حين لا تكون الفترات مهيّأة أصلاً.
     *
     * @return array{start: Carbon, end: Carbon, period: ?AccountingPeriod}
     */
    protected function defaultReportRange(): array
    {
        $today = Carbon::today();

        try {
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
            Log::warning('تعذّر اشتقاق الفترة الافتراضية للتقرير — استُعمل الشهر الجاري', [
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
     * نطاق التقرير المطبَّق — ما اختاره المستخدم، وإلا الافتراضي أعلاه.
     *
     * @return array{start: string, end: string, period: ?AccountingPeriod, is_default: bool}
     */
    protected function reportDateRange(
        Request $request,
        string $startKey = 'start_date',
        string $endKey = 'end_date'
    ): array {
        /*
            تُبقى للتوافق: شاشاتٌ كثيرة تنتظر `start`/`end` نصّاً. والمصدرُ
            صار `stockReportScope` — فالفترةُ تُحلَّل مرّةً بالقاعدة نفسِها،
            ويبقى للشاشة حدّاها كما كانت.
        */
        $scope = $this->stockReportScope($request, $startKey, $endKey);

        $fallback = $this->defaultReportRange();

        return [
            'start' => $scope['start'] ?: $fallback['start']->format('Y-m-d'),
            'end' => $scope['end'] ?: $fallback['end']->format('Y-m-d'),
            'period' => $scope['period']['period'] ?? $fallback['period'],
            'accounting_period' => $scope['period'],
            'mode' => $scope['mode'],
            'is_default' => $scope['is_default'],
        ];
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

        // بداية الشريحة التي يقع فيها الشهر: الربع الثاني يبدأ من أبريل لا من الشهر الجاري
        $index = intdiv($date->month - 1, $months);
        $start = $date->copy()->startOfYear()->addMonths($index * $months);

        return [
            'start' => $start,
            'end' => $start->copy()->addMonths($months)->subDay()->endOfDay(),
        ];
    }
}
