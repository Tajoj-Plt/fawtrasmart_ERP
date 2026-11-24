<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Models\AccountingPeriod;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\AccountingPeriodFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * الفترات المحاسبية في نقاط البيع — منعُ القيد المقفل، وافتراضُ نطاق التقرير.
 *
 * ─────────────────────── وجهان لنفس الإعداد ───────────────────────
 *
 * ١. *الكتابة*: كلُّ ما يُولّد قيداً — بيعٌ، مرتجع، حركةُ درج، إقفالُ جلسة —
 *    يُفحص بتاريخه. الفترة المقفلة تردّ العملية برسالةٍ تسمّي السبب، إلّا
 *    لمن يملك صلاحية التجاوز المضبوطة في الشاشة.
 *
 * ٢. *العرض*: **الفترةُ علاقةٌ لا مدًى.** الفاتورةُ والجلسةُ وحركةُ الدرج
 *    تُختَم بـ`accounting_period_id` لحظةَ الإنشاء، فالسؤالُ عن فترةٍ صار
 *    مقارنةَ عمودٍ بمعرّف لا مقارنةَ تواريخ.
 *
 * ─────────────────────── لماذا هذا مهمٌّ هنا خاصّةً ───────────────────────
 *
 * كانت تقاريرُ نقاط البيع تبدأ من `Carbon::now()->subDays(30)` مكتوبةً في
 * الشيفرة — ستَّ مرّات، في ستّة تقارير. فلا علاقةَ لما تعرضه بالفترة التي
 * تعمل المنشأة عليها، ومقارنةُ تقرير نقاط البيع بميزان المراجعة تُخرج
 * رقمين لا يلتقيان لأنّ كلاً منهما يقيس مدًى مختلفاً.
 *
 * والبيعُ نفسه كان يُرحَّل بلا أيّ فحص: فاتورةٌ بتاريخ اليوم تُقيَّد في فترةٍ
 * أقفلها المحاسب أمس، فينكسر الإقفال بعد اعتماده.
 *
 * ─────────────────────── ما دام القفل غير مفروض ───────────────────────
 *
 * `enforce_lock` مطفأً يعني أن الفحص يسمح دائماً — الفترات سجلٌّ تنظيمي حتى
 * تقرّر المنشأة فرضها. فالربط هنا لا يغيّر سلوك النظام اليوم، ويغيّره كلَّه
 * فور تفعيل الضابط، بلا سطرٍ واحد يُعدَّل.
 */
trait RespectsAccountingPeriods
{
    /**
     * سبب منع القيد بهذا التاريخ — أو `null` إن كان مسموحاً.
     *
     * تُبتلع أخطاء الخدمة عمداً: خدمةُ الفترات غير مهيّأة يجب ألّا تُوقف
     * كاشيراً عن البيع. لكنّ الابتلاع **يُكتب في اللوغ** — الصمتُ التام هو
     * ما جعل خطأً في هذه السمة نفسها يمرّ في وحدة المخزون فصار كلُّ فحصٍ
     * يمرّ بلا فحص.
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
            Log::warning('فحص الفترة المحاسبية أخفق في نقاط البيع — سُمح بالعملية', [
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
     * معرّف الفترة التي يقع فيها التاريخ — يُختَم به المستند لحظةَ إنشائه.
     *
     * الختمُ لحظةَ الإنشاء لا الاستنتاجُ لاحقاً: حدودُ الفترات قد تُعدَّل،
     * فمستندٌ يُسأل عن فترته بمقارنة تاريخه اليوم قد يُجيب بغير ما أُنشئ فيه.
     */
    protected function currentAccountingPeriodId(Carbon|string|null $date = null): ?int
    {
        try {
            return AccountingPeriod::forDate($date ?: now())?->id;
        } catch (\Throwable $e) {
            Log::warning('تعذّر تحديد الفترة المحاسبية لمستند نقاط البيع', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * فترةُ العرض — بالقاعدة نفسِها في المالية والمخزون.
     *
     * مصدرٌ واحدٌ للوحدات كلّها (`App\Support\AccountingPeriodFilter`):
     * وحدتان تُجيبان عن «أيّ فترة؟» بجوابين مختلفين تُخرجان تقريرَ مبيعاتٍ
     * لا يوافق دفترَه.
     *
     * @return array ناتج `AccountingPeriodFilter::resolve()`
     */
    protected function posPeriod(?Request $request = null): array
    {
        return AccountingPeriodFilter::resolve($request ?: request());
    }

    /**
     * أتُنتج هذه الفترةُ قيداً فعلياً على `accounting_period_id`؟
     *
     * ═══════════ لماذا يلزم السؤال ═══════════
     *
     * `period_id` **ليس رقمَ فترةٍ دائماً**. حين يختار المستخدم سنةً كاملة —
     * وهو الاختيار الافتراضي — تكون قيمتُه النصَّ `"year:55"`، وفتراتُها
     * الاثنتا عشرة في `period_ids`. وقد تكون `"all"` أو `"none"`.
     *
     * فمن كتب `where('accounting_period_id', $period['period_id'])` أنتج
     * `= 'year:55'` ولا يطابق صفّاً واحداً — **جدولٌ خاوٍ بلا خطأٍ يظهر**،
     * يُقرأ على أنه «لا مبيعات». وقد وقع ذلك في أربعة تقارير.
     *
     * @param  array  $resolved  ناتج `AccountingPeriodFilter::resolve()`
     */
    protected function periodConstrains(array $resolved): bool
    {
        return ! empty($resolved['period_ids'])
            || (isset($resolved['period_id']) && is_numeric($resolved['period_id']));
    }

    /**
     * تقييدُ استعلامٍ خامٍّ بفترةِ نطاقِ تقرير، وإلّا بالمدى التاريخيّ.
     *
     * موضعٌ واحدٌ لقاعدةٍ كُتبت أربعَ مرّاتٍ بأربعة أعمدةٍ مختلفة — وأخطأت
     * في الأربع.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     * @param  array  $scope  ناتج `posReportScope()`
     * @param  string  $periodColumn  عمودُ الفترة مؤهَّلاً بجدوله
     * @param  ?string  $dateColumn  عمودُ التاريخ للرجوع إليه — `null` يُسقط الرجوع
     */
    protected function scopeReportPeriod($query, array $scope, string $periodColumn, ?string $dateColumn = null)
    {
        if (($scope['mode'] ?? null) === 'all') {
            return $query;
        }

        $resolved = is_array($scope['period'] ?? null) ? $scope['period'] : [];

        if ($this->periodConstrains($resolved)) {
            return AccountingPeriodFilter::applyRaw($query, $resolved, $periodColumn);
        }

        // سنةٌ لم تُنشأ فتراتُها بعد — يبقى المدى التاريخيّ
        if ($dateColumn) {
            $query
                ->when($scope['start'] ?? null, fn ($q, $d) => $q->whereDate($dateColumn, '>=', $d))
                ->when($scope['end'] ?? null, fn ($q, $d) => $q->whereDate($dateColumn, '<=', $d));
        }

        return $query;
    }

    /**
     * تقييدُ استعلامٍ بفترة العرض — لمستندٍ يحمل `accounting_period_id`.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    protected function scopeToPosPeriod($query, ?array $period = null)
    {
        return AccountingPeriodFilter::apply($query, $period ?: $this->posPeriod());
    }

    /**
     * نطاقُ تقرير نقاط البيع: العلاقةُ أصلاً، والمدى التاريخيّ حين يُطلب صراحةً.
     *
     * وتقاريرُ نقاط البيع تحتاج المدى الحرّ أكثر من غيرها: «مبيعات أمس بعد
     * الظهر» سؤالٌ يوميّ للكاشير، ولا فترةَ محاسبية تُجيبه.
     *
     * @return array{mode: string, start: ?string, end: ?string, period: array, is_default: bool}
     */
    protected function posReportScope(
        Request $request,
        string $startKey = 'date_from',
        string $endKey = 'date_to'
    ): array {
        $period = $this->posPeriod($request);

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
     * حدّا التقرير نصّاً — ما اختاره المستخدم، وإلا الفترة الجارية، وإلا الشهر.
     *
     * تُبقى لأن شاشات التقارير تنتظر `fromDate`/`toDate` جاهزين للعرض.
     *
     * @return array{start: string, end: string, mode: string, is_default: bool, period: array}
     */
    protected function posReportRange(
        Request $request,
        string $startKey = 'date_from',
        string $endKey = 'date_to'
    ): array {
        $scope = $this->posReportScope($request, $startKey, $endKey);

        $fallback = $this->defaultPosReportRange();

        return [
            'start' => $scope['start'] ?: $fallback['start']->format('Y-m-d'),
            'end' => $scope['end'] ?: $fallback['end']->format('Y-m-d'),
            'mode' => $scope['mode'],
            'is_default' => $scope['is_default'],
            'period' => $scope['period'],
        ];
    }

    /**
     * النطاق الافتراضي — بترتيب التفضيل:
     *
     *   ١. الفترة المحاسبية التي يقع فيها اليوم.
     *   ٢. نطاقٌ مشتقّ من `default_period_type` حول اليوم.
     *   ٣. الشهر الجاري — حين لا تكون الفترات مهيّأة أصلاً.
     *
     * @return array{start: Carbon, end: Carbon, period: ?AccountingPeriod}
     */
    protected function defaultPosReportRange(): array
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

            return $this->posRangeFromType($type, $today) + ['period' => null];
        } catch (\Throwable $e) {
            Log::warning('تعذّر اشتقاق الفترة الافتراضية لتقرير نقاط البيع — استُعمل الشهر الجاري', [
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
     * نطاق التقويم المقابل لنوع الفترة، محسوباً حول تاريخ.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function posRangeFromType(?string $type, Carbon $date): array
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
