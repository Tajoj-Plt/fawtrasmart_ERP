<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientStatusRequest;
use App\Models\DoubtfulDebt;
use App\Models\InvoiceTransfer;
use App\Models\notifications;
use App\Models\Statuses;
use App\Models\Visit;
use App\Models\VisitCompetitorObservation;
use App\Support\ClientDashboardSegments;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Client\Services\CustomerLedgerService;

/**
 * حسابات لوحة تحكّم العملاء — وكلُّ رقمٍ فيها يُعاد إلى **قائمة عملائه**.
 *
 * ══════════ لماذا أُعيدت كتابتها (2026-09-13) ══════════
 *
 * كانت الأرقامُ تُعدّ ولا تُسأل: لا طريقَ من شريحةٍ في مخطَّط إلى العملاء
 * الذين فيها، فلم يكن ممكناً اكتشافُ أنّها خاطئة. وكانت خاطئةً فعلاً:
 *
 *   - **تقادم المديونية** يُلقي رصيدَ العميل **كلَّه** في شريحةٍ واحدة
 *     بعمر `Account::getDaysLate()` — وهي تقرأ «أوّل» قيدٍ من علاقةٍ بلا
 *     ترتيب، وبـ`created_at` (وقت الإدخال) لا بتاريخ القيد. فرصيدٌ افتتاحيٌّ
 *     قديم يسحب كلَّ ما بعده إلى «+90». صارت FIFO على البنود المفتوحة من
 *     `CustomerLedgerService::openItems()` — نفسُ ما يقرؤه تقريرُ أعمار الديون،
 *     فتتطابق الشاشتان.
 *   - **«متأخرون»** كان `count()` قائمةٍ مقصوصةٍ بـ`take(10)` — لا يتجاوز ١٠ أبداً.
 *   - **«بحاجة زيارة»** ١٤ يوماً مكتوبةً في الكود، وتَعُدّ **الزيارةَ المخطَّطة**
 *     التي لم تقع زيارةً. صارت من الإعدادات، ومن آخر زيارةٍ **وقعت** فعلاً.
 *   - **دورة الحياة وRFM** كانتا تعدّان المرتجعَ (`invoices.type = returned`)
 *     فاتورةَ شراءٍ وتجمعان قيمتَه مع المبيعات، وتضعان عميلاً لم يُسجَّل له
 *     أيُّ نشاطٍ أبداً في «مفقود» كأنّه كان ثم رحل.
 *
 * ══════════ القاعدة ══════════
 *
 * كلُّ مقياسٍ يُبنى أوّلاً **خريطةً لكل عميل** (`clientId => …`)، ثم يُعدّ
 * منها. فالرقمُ في البطاقة وطولُ القائمة التي تفتحها البطاقةُ ناتجان من
 * الخريطة نفسها — لا يمكن أن يختلفا. انظر {@see self::drillDown()}.
 *
 * كلُّ دالّةٍ تأخذ `array $clientIds` — نطاقَ العملاء بعد فلاتر اللوحة،
 * يحسبه المتحكّم مرّةً واحدة.
 */
class ClientDashboardService
{
    /**
     * نتائجُ الزيارات الفعلية — من جدول `visits` كما تكتبها مساحةُ عمل الزيارة:
     * «إنهاء الزيارة» يكتب `outcome` (فاتورة أو تحصيل ⇒ `success`، وإلّا
     * `failed`)، و«الإلغاء» يكتب `stage = cancelled`. والتسجيلُ التلقائي من
     * تتبّع الموقع (`recording_method = auto` بلا `started_at`) حضورٌ لا نتيجة له.
     */
    public const VISIT_OUTCOMES = [
        'success' => ['label' => 'ناجحة', 'color' => '#28c76f', 'hint' => 'أُنهيت وفيها فاتورة أو تحصيل'],
        'return_later' => ['label' => 'سأعود لاحقاً', 'color' => '#ff9f43', 'hint' => 'أُنهيت بلا بيع على أن يعود المندوب للعميل'],
        'failed' => ['label' => 'سلبية', 'color' => '#ea5455', 'hint' => 'أُنهيت بلا فاتورة ولا تحصيل'],
        'in_progress' => ['label' => 'جارية الآن', 'color' => '#7367f0', 'hint' => 'بدأها المندوب اليوم ولم تُنهَ بعد'],
        'open' => ['label' => 'لم تُنهَ', 'color' => '#a05a00', 'hint' => 'بدأت في يومٍ سابق ولم يُضغط «إنهاء الزيارة» ولا «إلغاء»'],
        'cancelled' => ['label' => 'ملغاة', 'color' => '#4b4b4b', 'hint' => 'أُلغيت من مساحة الزيارة'],
        'no_outcome' => ['label' => 'منتهية بلا نتيجة', 'color' => '#b9b9c3', 'hint' => 'أُغلقت قبل أن تُحسب النتيجة آلياً (زيارات قديمة)'],
    ];

    /** تغطيةُ خطّ السير — هل زِير العميلُ في اليوم المخطَّط له؟ مقياسٌ غيرُ نتائج الزيارات */
    public const PLAN_COVERAGE = [
        'visited' => ['label' => 'تمّت زيارته', 'color' => '#28c76f', 'hint' => 'مكتملة في خطّ السير كما تعدّها شاشته، أو للعميل في يومها زيارة يدوية أو ملاحظة متابعة أو فاتورة'],
        'in_progress' => ['label' => 'جارية', 'color' => '#7367f0', 'hint' => 'بدأت اليوم ولم تُنهَ بعد — المندوب عند العميل الآن'],
        'not_visited' => ['label' => 'لم يُزَر', 'color' => '#ea5455', 'hint' => 'فات يومها بلا إتمام ولا زيارة يدوية ولا ملاحظة ولا فاتورة، وبلا تبرير معتمد'],
        'justified' => ['label' => 'مبرَّرة', 'color' => '#00cfe8', 'hint' => 'لم تتم لكن تبريرها معتمد — للزيارة نفسها أو لفترةٍ تغطّي يومها'],
        'upcoming' => ['label' => 'اليوم وما بعده', 'color' => '#82868b', 'hint' => 'مخطَّطةٌ لليوم أو لما بعده ولم يُسجَّل لها شيءٌ بعد'],
        'cancelled' => ['label' => 'ملغاة', 'color' => '#4b4b4b', 'hint' => 'أُلغيت من خطّ السير'],
    ];

    /** فوقه يُستبدل `IN` بمسحٍ واحد — انظر {@see self::scopedChunks()} */
    private const WIDE_SCOPE = 500;

    /**
     * حالاتُ بطاقة «نتائج الزيارات» — من صفوف خطّ السير (`planRows()`).
     *
     * طلب المستخدم ثلاثاً فقط بعد أن طالت البطاقة بثماني حالاتٍ يدوية وخمسٍ
     * لتغطية خطّ السير، ثم أضاف «جارية». الملغاةُ خارجها، والمبرَّرةُ داخل
     * «فائتة» مع عدِّها.
     */
    public const VISIT_STATES = [
        'completed' => ['label' => 'مكتملة', 'color' => '#28c76f', 'hint' => 'تمّت: مكتملة في خطّ السير، أو للعميل في يومها زيارة يدوية أو ملاحظة متابعة أو فاتورة'],
        'in_progress' => ['label' => 'جارية', 'color' => '#7367f0', 'hint' => 'بدأت اليوم ولم تُنهَ بعد — ضغط المندوب «بدء الزيارة» ولم يضغط «إنهاء الزيارة»'],
        'missed' => ['label' => 'فائتة', 'color' => '#ea5455', 'hint' => 'فات يومها ولم تتم'],
        'pending' => ['label' => 'لم يُزَر', 'color' => '#82868b', 'hint' => 'مخطَّطة لليوم أو لما بعده ولم تتم بعد'],
    ];

    /** @var array<string, mixed> نتائج خامّة لعمر الطلب — بطاقاتٌ عدّة تقرأ الخريطة نفسها */
    private array $memo = [];

    public function __construct(private readonly CustomerLedgerService $ledger) {}

    // ═════════════════════════════ دورة الحياة و RFM ═════════════════════════════

    /**
     * شريحةُ دورة الحياة لكل عميل.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{key: string, last_activity: ?string, days: ?int, invoices: int}>
     */
    public function lifecycleMap(array $clientIds): array
    {
        return $this->remember('lifecycle', $clientIds, function () use ($clientIds) {
            // «آخر نشاط» = آخر فاتورة مبيعات وحدها بطلب المستخدم: الزيارةُ بلا بيعٍ
            // كانت تُبقي العميلَ «نشطاً» وهو لم يشترِ منذ شهور.
            $invoices = $this->invoiceStats($clientIds);
            $definition = ClientDashboardSegments::lifecycle();
            $map = [];

            foreach ($clientIds as $id) {
                $inv = $invoices[$id] ?? null;

                if (! $inv || ! $inv['last']) {
                    $map[$id] = ['key' => 'never', 'last_activity' => null, 'days' => null, 'invoices' => 0];

                    continue;
                }

                $last = $inv['last'];
                $days = self::daysSince($last);
                $invoiceDays = $days;
                $count = (int) ($inv['count'] ?? 0);
                $gap = ($inv && $inv['previous']) ? self::daysBetween($inv['previous'], $inv['last']) : null;

                // أوّلُ شريحةٍ تنطبق — بترتيب الإعدادات
                $key = 'other';
                foreach ($definition as $segmentKey => $segment) {
                    if ($segmentKey === 'never' || $segmentKey === 'other') {
                        continue;
                    }

                    $measured = $segment['min_gap'] !== null ? $invoiceDays : $days;

                    if (ClientDashboardSegments::inRange($measured, $segment)
                        && $count >= (int) ($segment['min_invoices'] ?? 0)
                        && ($segment['min_gap'] === null || ($gap !== null && $gap >= $segment['min_gap']))) {
                        $key = $segmentKey;
                        break;
                    }
                }

                $map[$id] = ['key' => $key, 'last_activity' => $last, 'days' => $days, 'invoices' => $count];
            }

            return $map;
        });
    }

    /** @return array<string, array{key:string, label:string, count:int, color:string, hint:string}> */
    public function lifecycleSegments(array $clientIds): array
    {
        return $this->countSegments(ClientDashboardSegments::lifecycle(), $this->lifecycleMap($clientIds));
    }

    /**
     * فواتير المبيعات لكل عميل: عددُها، قيمتُها، آخرُ تاريخٍ وما قبله.
     *
     * المرتجعُ (`type = returned`) والملغاةُ خارجها — كلاهما ليس شراءً.
     *
     * @return array<int, array{count:int, total:float, last:?string, previous:?string}>
     */
    private function invoiceStats(array $clientIds): array
    {
        return $this->remember('invoices', $clientIds, function () use ($clientIds) {
            $stats = [];

            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $rows = DB::table('invoices')
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->whereNull('voided_at')
                    ->whereNotNull('invoice_date')
                    ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'returned'))
                    // فاتورةٌ بتاريخٍ قادم نشاطٌ يومَ أُدخلت لا يومَ تاريخها — وإلّا صار «منذ» سالباً
                    ->orderByDesc('activity_date')
                    ->get(['client_id', 'grand_total', DB::raw('LEAST(DATE(invoice_date), DATE(COALESCE(created_at, invoice_date))) as activity_date')]);

                foreach ($rows as $row) {
                    $id = (int) $row->client_id;
                    if ($wanted !== null && ! isset($wanted[$id])) {
                        continue;
                    }
                    $date = substr((string) $row->activity_date, 0, 10);

                    if (! isset($stats[$id])) {
                        $stats[$id] = ['count' => 0, 'total' => 0.0, 'last' => $date, 'previous' => null];
                    } elseif ($stats[$id]['previous'] === null && $date < $stats[$id]['last']) {
                        $stats[$id]['previous'] = $date;
                    }

                    $stats[$id]['count']++;
                    $stats[$id]['total'] += (float) $row->grand_total;
                }
            }

            return $stats;
        });
    }

    // ═════════════════════════════ الزيارات ═════════════════════════════

    /**
     * تاريخُ آخر زيارةٍ **وقعت** لكل عميل.
     *
     * مصدران لأن كليهما يسجّل زيارةً حقيقية ولا يغني أحدُهما عن الآخر:
     * `visits` (مساحة عمل الزيارة — ما سُجِّل فعلاً في الميدان) وصفوف خطّ
     * السير المكتملة في `employee_client_visits`. والمخطَّطةُ التي لم تُنفَّذ
     * **ليست** زيارة — كانت تُحسب كذلك فتُخفي العميلَ عن «بحاجة زيارة».
     *
     * @param  array<int>  $clientIds
     * @return array<int, string> `[clientId => 'Y-m-d']`
     */
    public function lastVisitDates(array $clientIds): array
    {
        return $this->remember('last_visit', $clientIds, function () use ($clientIds) {
            $out = [];
            $take = function ($rows, ?array $wanted) use (&$out) {
                foreach ($rows as $row) {
                    $id = (int) $row->client_id;
                    if ($wanted !== null && ! isset($wanted[$id])) {
                        continue;
                    }
                    $date = substr((string) $row->last_date, 0, 10);
                    if ($date !== '' && (! isset($out[$id]) || $date > $out[$id])) {
                        $out[$id] = $date;
                    }
                }
            };

            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $take(DB::table('visits')
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->where('visit_date', '<=', Carbon::now())
                    // اليدويةُ وحدها — التسجيلُ التلقائي بالموقع لا يُعدّ زيارة (بطلب المستخدم)
                    ->where(fn ($q) => Visit::constrainActualVisit($q))
                    ->where(fn ($q) => $q->whereNull('stage')->orWhere('stage', '!=', Visit::STAGE_CANCELLED))
                    ->groupBy('client_id')
                    ->get(['client_id', DB::raw('MAX(visit_date) as last_date')]), $wanted);

                /* يومُ الزيارة الفعلي = الأسبقُ من يومها المخطَّط ويومِ إكمالها:
                   - «إنهاء الزيارة» من شاشة خطّ السير يُكمل صفَّ يومٍ قادم فيبقى
                     `visit_date` في المستقبل — فظهر «آخر نشاط» بعد أيام و«منذ -4 يوم»
                     ووقع العميل في «غير مصنَّف»؛
                   - ١٨ ألف صفٍّ مكتملٍ قديم (2025) بلا `visit_date` لكن بـ`completed_at`:
                     كان MAX يتجاهلها فيُعدّ ٤٦٧ عميلاً «لم يُزَر قط» وقد زيروا. */
                $visitDay = 'LEAST(COALESCE(visit_date, DATE(completed_at)), COALESCE(DATE(completed_at), visit_date))';
                $take(DB::table('employee_client_visits')
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->where('visit_status', VisitStatus::COMPLETED->value)
                    ->whereRaw($visitDay.' <= ?', [Carbon::today()->toDateString()])
                    ->groupBy('client_id')
                    ->get(['client_id', DB::raw('MAX('.$visitDay.') as last_date')]), $wanted);
            }

            return $out;
        });
    }

    /**
     * عملاءُ بحاجة زيارة: مضى على آخر زيارةٍ لهم `$days` يوماً فأكثر، أو لم
     * يُزاروا قط. العميلُ الموقوف خارجها — لا يُزار من توقّف التعامل معه.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{last_visit: ?string, days: ?int}>
     */
    public function needsVisitMap(array $clientIds, int $days): array
    {
        return $this->remember('needs_visit:'.$days, $clientIds, function () use ($clientIds, $days) {
            $lastVisits = $this->lastVisitDates($clientIds);
            $stopped = array_flip(array_map('intval', Statuses::stoppedClientStatusIds()));
            $statuses = $this->clientColumn($clientIds, 'status_id');

            $map = [];
            foreach ($clientIds as $id) {
                if (isset($statuses[$id]) && isset($stopped[(int) $statuses[$id]])) {
                    continue;
                }

                $last = $lastVisits[$id] ?? null;
                $since = self::daysSince($last);

                if ($last === null || $since >= $days) {
                    $map[$id] = ['last_visit' => $last, 'days' => $since];
                }
            }

            return $map;
        });
    }

    /**
     * الزياراتُ الفعلية في المدى صفّاً صفّاً: `[key, client_id, date, employee_id]`.
     *
     * **النتيجةُ لا تُخمَّن من خطّ السير.** كانت هذه البطاقةُ تعدّ صفوفَ
     * `employee_client_visits` — أي **الخطّة** — وتسمّيها نتائج: مخطَّطةٌ لم
     * تُعلَّم مكتملة تُقرأ «لم تُنفَّذ» ولو زار المندوبُ وباع. والزيارةُ نفسُها
     * تحمل نتيجتَها في `visits.outcome` منذ صارت مساحةُ العمل تحسبها.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{key: string, client_id: int, date: string, employee_id: ?int}>
     */
    public function visitRows(array $clientIds, Carbon $from, Carbon $to): array
    {
        $tag = 'visit_rows:'.$from->toDateString().':'.$to->toDateString();

        return $this->remember($tag, $clientIds, function () use ($clientIds, $from, $to) {
            $rows = [];
            $today = Carbon::today()->toDateString();
            $hasReturnLater = Schema::hasColumn('visits', 'return_later');
            $columns = ['client_id', 'employee_id', 'visit_date', 'stage', 'outcome', 'departure_time', 'started_at', 'recording_method'];
            if ($hasReturnLater) {
                $columns[] = 'return_later';
            }

            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $visits = DB::table('visits')
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->whereBetween('visit_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    // يدويةٌ فقط: فُتحت من مساحة الزيارة — التسجيلُ التلقائي بالموقع حضورٌ لا زيارة
                    ->where(fn ($q) => Visit::constrainActualVisit($q))
                    ->get($columns);

                foreach ($visits as $visit) {
                    $clientId = (int) $visit->client_id;
                    if ($wanted !== null && ! isset($wanted[$clientId])) {
                        continue;
                    }

                    $date = substr((string) $visit->visit_date, 0, 10);

                    $key = match (true) {
                        $visit->stage === Visit::STAGE_CANCELLED => 'cancelled',
                        $visit->stage === Visit::STAGE_COMPLETED || $visit->departure_time !== null => match (true) {
                            $visit->outcome === 'success' => 'success',
                            $hasReturnLater && (bool) $visit->return_later => 'return_later',
                            $visit->outcome === 'failed' => 'failed',
                            default => 'no_outcome',
                        },
                        $date >= $today => 'in_progress',
                        default => 'open',
                    };

                    $rows[] = ['key' => $key, 'client_id' => $clientId, 'date' => $date, 'employee_id' => $visit->employee_id ? (int) $visit->employee_id : null];
                }
            }

            return $rows;
        });
    }

    /**
     * صفوفُ خطّ السير في المدى، كلٌّ بتغطيته: `[key, client_id, date, employee_id]`.
     *
     * `visit_status` لا يصير `completed` إلا بـ«إنهاء الزيارة»، والمندوبُ الذي
     * زار وسجّل ملاحظته أو فاتورته ثم خرج يترك الصفَّ `planned`. فالصفُّ غيرُ
     * المكتمل يُسأل عن **دليل** للعميل في يومه: زيارةٌ في `visits`، أو ملاحظةٌ
     * في `client_relations`، أو فاتورةُ مبيعات.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{key: string, client_id: int, date: string, employee_id: ?int}>
     */
    public function planRows(array $clientIds, Carbon $from, Carbon $to): array
    {
        $tag = 'plan_rows:'.$from->toDateString().':'.$to->toDateString();

        return $this->remember($tag, $clientIds, function () use ($clientIds, $from, $to) {
            $rows = [];
            $today = Carbon::today()->toDateString();
            $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $keep = fn (int $clientId) => $wanted === null || isset($wanted[$clientId]);
                $inChunk = fn ($q) => $q->when($chunk !== null, fn ($w) => $w->whereIn('client_id', $chunk));

                /* دليلُ الحضور: «عميل|تاريخ|موظف» — الدليلُ لصاحبه وحده. كان «عميل|تاريخ»
                   فقط، فملاحظةُ موظفٍ آخر أو فاتورةٌ أصدرها المكتب للعميل في يومه تُكمل صفَّ
                   المندوب وهو لم يزره («زار أربعة ويظهر 41 مكتملة»). */
                $evidence = [];
                $collect = function ($items, string $column, string $author) use (&$evidence, $keep) {
                    foreach ($items as $item) {
                        if ($keep((int) $item->client_id)) {
                            $evidence[(int) $item->client_id.'|'.substr((string) $item->{$column}, 0, 10).'|'.(int) $item->{$author}] = true;
                        }
                    }
                };

                // وزيارةُ اليوم المفتوحة (لم تُنهَ ولم تُلغَ) = «جارية» — كـ`DailyItineraryService::stateOf()`
                $open = [];
                // نتيجةُ مساحة العمل لزيارةٍ أُنهيت: «عميل|تاريخ» ⇐ success / return_later / failed
                $workspaceOutcome = [];
                $outcomeRank = ['failed' => 1, 'return_later' => 2, 'success' => 3];
                $hasReturnLater = Schema::hasColumn('visits', 'return_later');
                $visits = $inChunk(DB::table('visits'))
                    ->whereBetween('visit_date', $range)
                    ->where(fn ($q) => $q->whereNull('stage')->orWhere('stage', '!=', Visit::STAGE_CANCELLED))
                    ->where(fn ($q) => Visit::constrainActualVisit($q))
                    ->get(array_merge(['client_id', 'employee_id', 'visit_date', 'stage', 'departure_time', 'started_at', 'outcome'], $hasReturnLater ? ['return_later'] : []));
                $collect($visits, 'visit_date', 'employee_id');
                foreach ($visits as $visit) {
                    $date = substr((string) $visit->visit_date, 0, 10);
                    $slot = (int) $visit->client_id.'|'.$date;
                    // «جارية» لما بدأه المندوب فقط — لا مساحةُ عملٍ فُتحت بلا «بدء الزيارة»
                    if ($date === $today && $visit->started_at !== null && $visit->departure_time === null && $visit->stage !== Visit::STAGE_COMPLETED && $keep((int) $visit->client_id)) {
                        $open[$slot.'|'.(int) $visit->employee_id] = true;
                    }

                    if ($visit->stage === Visit::STAGE_COMPLETED || $visit->departure_time !== null) {
                        $result = match (true) {
                            $visit->outcome === 'success' => 'success',
                            $hasReturnLater && (bool) $visit->return_later => 'return_later',
                            $visit->outcome === 'failed' => 'failed',
                            default => null,
                        };
                        // زيارتان للعميل في يومه: الأفضلُ يغلب (ناجحة ثم «سأعود» ثم سلبية)
                        if ($result !== null && $outcomeRank[$result] > ($outcomeRank[$workspaceOutcome[$slot] ?? ''] ?? 0)) {
                            $workspaceOutcome[$slot] = $result;
                        }
                    }
                }
                $collect($this->realNotesQuery($inChunk(DB::table('client_relations')))
                    ->whereBetween('created_at', $range)
                    ->get(['client_id', 'employee_id', 'created_at']), 'created_at', 'employee_id');

                // فاتورةٌ أو سندُ قبضٍ للعميل في يومه — قاعدةُ «الزيارة الناجحة» في مساحة العمل
                $sold = [];
                $invoices = $inChunk(DB::table('invoices'))
                    ->whereBetween('invoice_date', $range)
                    ->whereNull('voided_at')
                    ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'returned'))
                    ->get(['client_id', 'created_by', 'invoice_date']);
                $collect($invoices, 'invoice_date', 'created_by');
                foreach ($invoices as $invoice) {
                    $sold[(int) $invoice->client_id.'|'.substr((string) $invoice->invoice_date, 0, 10)] = true;
                }
                $receipts = DB::table('receipts')
                    ->leftJoin('accounts', 'accounts.id', '=', 'receipts.account_id')
                    ->whereBetween('receipts.date', $range)
                    ->whereNull('receipts.cancelled_at')
                    ->when($chunk !== null, fn ($q) => $q->where(fn ($w) => $w
                        ->whereIn('receipts.client_id', $chunk)
                        ->orWhereIn('accounts.client_id', $chunk)))
                    ->get([DB::raw('COALESCE(receipts.client_id, accounts.client_id) as client_id'), 'receipts.date']);
                foreach ($receipts as $receipt) {
                    if ($receipt->client_id) {
                        $sold[(int) $receipt->client_id.'|'.substr((string) $receipt->date, 0, 10)] = true;
                    }
                }

                $itinerary = $inChunk(DB::table('employee_client_visits'))
                    ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
                    ->get(['id', 'client_id', 'employee_id', 'status', 'visit_status', 'visit_date', 'justification_approved']);

                // فتراتُ التبرير المعتمدة — نفس استثناء `EmployeeClientVisit::excludePeriodCoveredDates()`
                $periods = [];
                foreach ($inChunk(DB::table('visit_justifications'))
                    ->where('status', \App\Models\VisitJustification::STATUS_APPROVED)
                    ->where('date_from', '<=', $to->toDateString())
                    ->where('date_to', '>=', $from->toDateString())
                    ->get(['client_id', 'employee_id', 'date_from', 'date_to']) as $period) {
                    $periods[(int) $period->client_id.'|'.(int) $period->employee_id][] = [substr((string) $period->date_from, 0, 10), substr((string) $period->date_to, 0, 10)];
                }
                $covered = function (int $clientId, int $employeeId, string $date) use ($periods): bool {
                    foreach ($periods[$clientId.'|'.$employeeId] ?? [] as [$fromDate, $toDate]) {
                        if ($fromDate <= $date && $toDate >= $date) {
                            return true;
                        }
                    }

                    return false;
                };

                foreach ($itinerary as $row) {
                    $clientId = (int) $row->client_id;
                    if (! $keep($clientId)) {
                        continue;
                    }

                    $date = substr((string) $row->visit_date, 0, 10);
                    $own = $clientId.'|'.$date.'|'.(int) $row->employee_id;

                    $key = match (true) {
                        $row->visit_status === VisitStatus::CANCELLED->value => 'cancelled',
                        /* «تمّت» كما تعدّها شاشةُ خطّ السير (`ItineraryController::VISIT_COMPLETED_SQL`):
                           مكتملة، أو `status = active` لصفٍّ قديمٍ بلا `visit_status` — صفُّ
                           «سأعود» (active + planned) لم يُزَر بعد. */
                        $row->visit_status === VisitStatus::COMPLETED->value, $row->visit_status === null && $row->status === 'active' => 'visited',
                        // بدأت اليوم ولم تُنهَ: `visit_status = started` أو زيارةٌ مفتوحة للعميل اليوم
                        $date === $today && ($row->visit_status === VisitStatus::STARTED->value || isset($open[$own])) => 'in_progress',
                        $row->visit_status === VisitStatus::STARTED->value => 'visited',
                        isset($evidence[$own]) => 'visited',
                        $date >= $today => 'upcoming',
                        // تبريرٌ معتمد للزيارة نفسها أو لفترةٍ تغطّي يومها — لا تُحسب «لم تتم»، كشاشة التبريرات
                        (int) $row->justification_approved === 1 || $covered($clientId, (int) $row->employee_id, $date) => 'justified',
                        default => 'not_visited',
                    };

                    /* نتيجةُ الزيارة المكتملة — كلُّ مكتملةٍ إمّا ناجحة وإمّا سلبية، فمجموعُهما
                       = «مكتملة». ما أنهته مساحةُ العمل يحمل نتيجتَه؛ وما أُكمل من شاشة خطّ
                       السير أو بدليلٍ يُستنتج: فاتورةٌ أو سندُ قبضٍ للعميل في يومه ⇐ ناجحة
                       (كـ`DailyClosingLedger::visitOutcomeFields()`). */
                    $slot = $clientId.'|'.$date;
                    $outcome = $key !== 'visited' ? null : match ($workspaceOutcome[$slot] ?? null) {
                        'success' => 'success',
                        'return_later', 'failed' => 'negative',
                        default => isset($sold[$slot]) ? 'success' : 'negative',
                    };

                    $rows[] = [
                        'id' => (int) $row->id, 'key' => $key, 'client_id' => $clientId, 'date' => $date,
                        'employee_id' => $row->employee_id ? (int) $row->employee_id : null,
                        'outcome' => $outcome,
                        'return_later' => $outcome === 'negative' && ($workspaceOutcome[$slot] ?? null) === 'return_later',
                    ];
                }
            }

            return $rows;
        });
    }

    /** شاراتُ حالة الزيارة في صفحة العميل — بمفاتيح `planRows()` نفسها. */
    public const CLIENT_VISIT_BADGES = [
        'visited' => ['label' => 'مكتملة', 'badge' => 'bg-success text-white', 'dot' => 'is-success', 'text' => 'text-success'],
        'in_progress' => ['label' => 'جارية', 'badge' => 'bg-primary text-white', 'dot' => 'is-primary', 'text' => 'text-primary'],
        'not_visited' => ['label' => 'فائتة', 'badge' => 'bg-danger text-white', 'dot' => 'is-danger', 'text' => 'text-danger'],
        'justified' => ['label' => 'فائتة — مبرَّرة', 'badge' => 'bg-info text-white', 'dot' => 'is-info', 'text' => 'text-info'],
        'upcoming' => ['label' => 'لم يُزَر بعد', 'badge' => 'bg-secondary text-white', 'dot' => 'is-secondary', 'text' => 'text-secondary'],
        'cancelled' => ['label' => 'ملغاة', 'badge' => 'bg-dark text-white', 'dot' => 'is-secondary', 'text' => 'text-muted'],
    ];

    /**
     * حالةُ كل صفٍّ في خطّ سير عميلٍ واحد — لصفحة العميل، بقاعدة اللوحة نفسها.
     *
     * كانت الصفحة تقرأ العمود القديم `status` (active/unactive) وحده، فالجاريةُ
     * والفائتةُ والقادمةُ والمبرَّرةُ كلُّها «غير مكتملة». والصفُّ بلا
     * `visit_date` (قديمٌ أُكمل قبل العمود) يُقرأ من حالته وحدها.
     *
     * @return array<int, array{key: string, label: string, badge: string, dot: string, text: string, hint: string}>
     */
    public function clientVisitStates(int $clientId): array
    {
        $bounds = DB::table('employee_client_visits')
            ->where('client_id', $clientId)
            ->selectRaw('MIN(visit_date) as first_date, MAX(visit_date) as last_date')
            ->first();

        $keys = [];
        if ($bounds && $bounds->first_date) {
            foreach ($this->planRows([$clientId], Carbon::parse($bounds->first_date), Carbon::parse($bounds->last_date)) as $row) {
                $keys[$row['id']] = $row['key'];
            }
        }

        foreach (DB::table('employee_client_visits')->where('client_id', $clientId)->whereNull('visit_date')->get(['id', 'status', 'visit_status']) as $row) {
            $keys[(int) $row->id] = match (true) {
                $row->visit_status === VisitStatus::CANCELLED->value => 'cancelled',
                $row->visit_status === VisitStatus::COMPLETED->value, $row->visit_status === null && $row->status === 'active' => 'visited',
                default => null,
            };
        }

        $states = [];
        foreach (array_filter($keys) as $id => $key) {
            $states[$id] = self::CLIENT_VISIT_BADGES[$key] + ['key' => $key, 'hint' => self::PLAN_COVERAGE[$key]['hint'] ?? ''];
        }

        return $states;
    }

    /**
     * قيمُ قائمة الزيارات لكل عميل: العدد، والتواريخ، والموظف.
     *
     * @param  array<int, array{key: string, client_id: int, date: string, employee_id: ?int}>  $rows
     * @param  array<int, string>  $keys
     * @return array{0: array<int, int>, 1: array<int, array<string, mixed>>}
     */
    private function visitDrillValues(array $rows, array $keys): array
    {
        $wantedKeys = array_flip($keys);
        $byClient = [];
        $employeeIds = [];

        foreach ($rows as $row) {
            if (! isset($wantedKeys[$row['key']])) {
                continue;
            }

            $clientId = $row['client_id'];
            $byClient[$clientId]['count'] = ($byClient[$clientId]['count'] ?? 0) + 1;
            $byClient[$clientId]['dates'][$row['date']] = true;

            if ($row['employee_id']) {
                $byClient[$clientId]['employees'][$row['employee_id']] = true;
                $employeeIds[$row['employee_id']] = true;
            }
        }

        $names = $employeeIds === []
            ? []
            : DB::table('users')->whereIn('id', array_keys($employeeIds))->pluck('name', 'id')->all();

        $values = [];
        foreach ($byClient as $clientId => $client) {
            $dates = array_keys($client['dates']);
            rsort($dates);

            $values[$clientId] = [
                'visit_count' => $client['count'],
                'visit_dates' => implode('، ', $dates),
                'visit_employees' => implode('، ', array_map(
                    fn ($id) => $names[$id] ?? '#'.$id,
                    array_keys($client['employees'] ?? [])
                )),
            ];
        }

        return [array_keys($byClient), $values];
    }

    /** @return array<int, array{key: string, label: string, type: string}> */
    private static function visitColumns(): array
    {
        return [
            ['key' => 'visit_count', 'label' => 'عدد الزيارات', 'type' => 'number'],
            ['key' => 'visit_dates', 'label' => 'التواريخ', 'type' => 'text'],
            ['key' => 'visit_employees', 'label' => 'الموظف', 'type' => 'text'],
        ];
    }

    /**
     * نتيجةُ الزيارات **المكتملة** في المدى: ناجحة / سلبية — من `planRows()`
     * نفسها، فمجموعُهما = «مكتملة» في البطاقة دائماً.
     *
     * كانت تُعدّ من جدول `visits` وحده (ما أنهته مساحةُ العمل)، و«مكتملة» من
     * خطّ السير — فظهر 82 مكتملة و41 نتيجة. «سلبية» تضمّ «سأعود لاحقاً»
     * (`return_later`) ويُعدّ داخلها مستقلاً.
     *
     * @param  array<int>  $clientIds
     * @return array{success: int, negative: int, return_later: int}
     */
    public function visitOutcomeCounts(array $clientIds, Carbon $from, Carbon $to): array
    {
        $counts = ['success' => 0, 'negative' => 0, 'return_later' => 0];

        foreach ($this->planRows($clientIds, $from, $to) as $row) {
            if (($row['outcome'] ?? null) !== null) {
                $counts[$row['outcome']]++;
                $counts['return_later'] += $row['return_later'] ? 1 : 0;
            }
        }

        return $counts;
    }

    /**
     * صفوفُ الزيارات المكتملة بمفتاح نتيجتها (`success|negative`) — لقائمة البطاقة.
     *
     * @return array<int, array<string, mixed>>
     */
    private function visitResultRows(array $clientIds, Carbon $from, Carbon $to): array
    {
        $rows = [];

        foreach ($this->planRows($clientIds, $from, $to) as $row) {
            if (($row['outcome'] ?? null) !== null) {
                $rows[] = ['key' => $row['outcome']] + $row;
            }
        }

        return $rows;
    }

    /**
     * «نتائج الزيارات»: مكتملة / فائتة / لم يُزَر — وعددُ المبرَّر داخل الفائتة.
     *
     * @param  array<int>  $clientIds
     * @return array{rows: array<string, array{key: string, label: string, color: string, hint: string, count: int}>, justified: int}
     */
    public function visitStates(array $clientIds, Carbon $from, Carbon $to): array
    {
        $counts = array_fill_keys(array_keys(self::VISIT_STATES), 0);
        $justified = 0;

        foreach ($this->visitStateRows($clientIds, $from, $to) as $row) {
            $counts[$row['key']]++;
            $justified += $row['justified'] ? 1 : 0;
        }

        $rows = [];
        foreach (self::VISIT_STATES as $key => $meta) {
            $rows[$key] = $meta + ['key' => $key, 'count' => $counts[$key]];
        }

        return ['rows' => $rows, 'justified' => $justified];
    }

    /**
     * صفوفُ خطّ السير بحالات البطاقة: تمّت ⇐ مكتملة، لم يُزَر ومبرَّرة ⇐ فائتة،
     * قادمة ⇐ لم يُزَر. الملغاة تُسقط.
     *
     * @return array<int, array{key: string, client_id: int, date: string, employee_id: ?int, justified: bool}>
     */
    private function visitStateRows(array $clientIds, Carbon $from, Carbon $to): array
    {
        $map = ['visited' => 'completed', 'in_progress' => 'in_progress', 'not_visited' => 'missed', 'justified' => 'missed', 'upcoming' => 'pending'];
        $rows = [];

        foreach ($this->planRows($clientIds, $from, $to) as $row) {
            if (isset($map[$row['key']])) {
                $rows[] = ['key' => $map[$row['key']], 'justified' => $row['key'] === 'justified'] + $row;
            }
        }

        return $rows;
    }

    /**
     * أرقامُ الشهر الجاري — من نفس صفوف الزيارات أعلاه، لا عدٍّ موازٍ.
     *
     * @param  array<int>  $clientIds
     */
    public function thisMonthVisitResults(array $clientIds): array
    {
        $visits = $this->visitRows($clientIds, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
        $invoices = $this->monthInvoicesByClient($clientIds);
        $notes = $this->monthNotesByClient($clientIds);

        return [
            'total_visits' => count($visits),
            'success_visits' => count(array_filter($visits, fn ($row) => $row['key'] === 'success')),
            'invoices_count' => (int) array_sum(array_column($invoices, 'count')),
            'notes_count' => (int) array_sum($notes),
        ];
    }

    /** @return array<int, array{count:int, total:float}> */
    private function monthInvoicesByClient(array $clientIds): array
    {
        return $this->remember('month_invoices', $clientIds, function () use ($clientIds) {
            $out = [];
            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $rows = DB::table('invoices')
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->whereNull('voided_at')
                    ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'returned'))
                    ->whereBetween('invoice_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                    ->groupBy('client_id')
                    ->get(['client_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(grand_total) as total')]);

                foreach ($rows as $row) {
                    if ($wanted !== null && ! isset($wanted[(int) $row->client_id])) {
                        continue;
                    }
                    $out[(int) $row->client_id] = ['count' => (int) $row->cnt, 'total' => (float) $row->total];
                }
            }

            return $out;
        });
    }

    /** @return array<int, int> */
    private function monthNotesByClient(array $clientIds): array
    {
        return $this->remember('month_notes', $clientIds, function () use ($clientIds) {
            $out = [];
            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $rows = $this->realNotesQuery(DB::table('client_relations'))
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                    ->groupBy('client_id')
                    ->get(['client_id', DB::raw('COUNT(*) as cnt')]);

                foreach ($rows as $row) {
                    if ($wanted !== null && ! isset($wanted[(int) $row->client_id])) {
                        continue;
                    }
                    $out[(int) $row->client_id] = (int) $row->cnt;
                }
            }

            return $out;
        });
    }

    // ═════════════════════════════ المديونية ═════════════════════════════

    /**
     * البنودُ المفتوحة لكل عميلٍ مدين — FIFO من دفتر الأستاذ.
     *
     * التحصيلُ يُطفئ أقدمَ المستحقّات أوّلاً، فيبقى القديمُ قديماً ولا يسحب
     * رصيدٌ افتتاحيٌّ قديم كلَّ المشتريات الحديثة معه. ومجموعُ البنود = رصيدُ
     * الحساب، فمجموعُ الشرائح يساوي إجماليَّ المديونية حتماً.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{balance: float, items: array<int, array{date:string, days:int, age:int, amount:float}>, oldest_days: int, weighted_age: float}>
     */
    public function debtPositions(array $clientIds): array
    {
        return $this->remember('debt', $clientIds, function () use ($clientIds) {
            $accountToClient = [];
            foreach (array_chunk($clientIds, 1000) as $chunk) {
                foreach (Account::ledger()->whereIn('client_id', $chunk)->get(['id', 'client_id']) as $account) {
                    $accountToClient[(int) $account->id] = (int) $account->client_id;
                }
            }

            if ($accountToClient === []) {
                return [];
            }

            $creditPeriods = $this->clientColumn($clientIds, 'credit_period');
            $periodsByAccount = [];
            foreach ($accountToClient as $accountId => $clientId) {
                $periodsByAccount[$accountId] = $creditPeriods[$clientId] ?? null;
            }

            $open = $this->ledger->openItems(array_keys($accountToClient), Carbon::today()->toDateString(), $periodsByAccount);

            $out = [];
            foreach ($open as $accountId => $position) {
                $clientId = $accountToClient[$accountId] ?? null;

                if (! $clientId || $position['balance'] <= 0.005 || $position['items'] === []) {
                    continue;
                }

                $items = [];
                $sum = 0.0;
                $weighted = 0.0;
                foreach ($position['items'] as $item) {
                    $amount = (float) $item['amount'];
                    $age = (int) self::daysSince($item['date']);
                    $items[] = ['date' => $item['date'], 'days' => (int) $item['days'], 'age' => $age, 'amount' => $amount];
                    $sum += $amount;
                    $weighted += $age * $amount;
                }

                $out[$clientId] = [
                    'balance' => round($sum, 2),
                    'items' => $items,
                    'oldest_days' => max(array_column($items, 'days')),
                    'weighted_age' => $sum > 0 ? $weighted / $sum : 0.0,
                ];
            }

            return $out;
        });
    }

    /**
     * تقادمُ المديونية: مبلغُ كل شريحة وعددُ عملائها.
     *
     * @param  array<int>  $clientIds
     * @return array{buckets: array<string, array{key:string,label:string,total:float,clients:int,color:string,hint:string}>, total_debt: float, debtors: int}
     */
    public function debtAgingBuckets(array $clientIds): array
    {
        $buckets = [];
        $aging = ClientDashboardSegments::aging();

        foreach ($aging as $key => $meta) {
            $buckets[$key] = $meta + ['key' => $key, 'total' => 0.0, 'clients' => 0];
        }

        $total = 0.0;
        $positions = $this->debtPositions($clientIds);

        foreach ($positions as $position) {
            $seen = [];
            foreach ($position['items'] as $item) {
                $bucket = ClientDashboardSegments::bucketFor($item['days'], $aging);
                $buckets[$bucket]['total'] += $item['amount'];
                $seen[$bucket] = true;
                $total += $item['amount'];
            }
            foreach (array_keys($seen) as $bucket) {
                $buckets[$bucket]['clients']++;
            }
        }

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['total'] = round($bucket['total'], 2);
        }

        return ['buckets' => $buckets, 'total_debt' => round($total, 2), 'debtors' => count($positions)];
    }

    /**
     * العملاءُ المتأخّرون: لهم مبلغٌ تجاوز استحقاقُه `$days` يوماً.
     *
     * المبلغُ المتأخّر هو ذلك الجزءُ وحده — لا الرصيدُ كلُّه — مرتَّبين تنازلياً.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{overdue: float, oldest_days: int, balance: float}>
     */
    public function overdueMap(array $clientIds, int $days): array
    {
        return $this->remember('overdue:'.$days, $clientIds, function () use ($clientIds, $days) {
            $map = [];
            foreach ($this->debtPositions($clientIds) as $clientId => $position) {
                $late = array_filter($position['items'], fn ($item) => $item['days'] > $days);

                if ($late === []) {
                    continue;
                }

                $map[$clientId] = [
                    'overdue' => round(array_sum(array_column($late, 'amount')), 2),
                    'oldest_days' => max(array_column($late, 'days')),
                    'balance' => $position['balance'],
                ];
            }

            // الأقدمُ تأخّراً أوّلاً — هو ما يُسأل عنه — ثم الأكبرُ مبلغاً بين المتساوين
            uasort($map, fn ($a, $b) => [$b['oldest_days'], $b['overdue']] <=> [$a['oldest_days'], $a['overdue']]);

            return $map;
        });
    }

    /**
     * أرصدةٌ دائنة لصالح العملاء — دفعوا أكثرَ ممّا عليهم أو مقدَّماً.
     *
     * ليست ديناً فلا تدخل «إجمالي المديونية» ولا تُطرح منه: تُعرض وحدَها
     * بعددها ومجموعها (2026-09-16، بطلب المستخدم: «الأرصدة الدائنة التي
     * بالسالب من المفروض تكون لوحدها وتكون موضحة»). نفسُ تعريف
     * `SalesDashboardMetrics::receivables()['credit']` وتقرير أرصدة العملاء.
     *
     * @param  array<int>  $clientIds
     * @return array<int, float>  [client_id => المبلغ لصالح العميل، موجباً]، الأكبرُ أوّلاً
     */
    public function creditPositions(array $clientIds): array
    {
        return $this->remember('credit', $clientIds, function () use ($clientIds) {
            $accountToClient = [];
            foreach (array_chunk($clientIds, 1000) as $chunk) {
                foreach (Account::ledger()->whereIn('client_id', $chunk)->get(['id', 'client_id']) as $account) {
                    $accountToClient[(int) $account->id] = (int) $account->client_id;
                }
            }

            if ($accountToClient === []) {
                return [];
            }

            $out = [];
            foreach ($this->ledger->balancesAt(array_keys($accountToClient), Carbon::today()->toDateString()) as $accountId => $balance) {
                $clientId = $accountToClient[(int) $accountId] ?? null;

                if ($clientId && $balance < -0.005) {
                    $out[$clientId] = round(($out[$clientId] ?? 0.0) + abs($balance), 2);
                }
            }

            arsort($out);

            return $out;
        });
    }

    // ═════════════════════════════ القوائم ═════════════════════════════

    /**
     * عملاءُ شريحةٍ من اللوحة — ما يفتحه النقرُ على بطاقةٍ أو جزءٍ من مخطَّط.
     *
     * يُبنى من **الخريطة نفسها** التي عُدّ منها الرقم، فطولُ القائمة يساوي
     * الرقمَ المعروض بالضرورة. يعيد `null` لشريحةٍ لا يعرفها.
     *
     * `$ctx`: `visit_gap`، `overdue_days`، `from`/`to` (مدى الحركة المالية
     * والزيارات)، `new_from`/`new_to` (العملاء الجدد)، `range_label`، و`title`
     * للقائمة العامة `list` التي يضيّقها المتحكّم بنفسه (حالة/تصنيف/تابع لـ…).
     *
     * @param  array<int>  $clientIds
     * @return array{title:string, hint:string, columns: array<int, array{key:string,label:string,type:string}>, rows: array<int, array<string, mixed>>}|null
     */
    public function drillDown(string $segment, array $clientIds, array $ctx): ?array
    {
        [$group, $key] = array_pad(explode('.', $segment, 2), 2, null);
        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));

        $values = [];
        $columns = [];
        $omit = [];
        $sort = null;

        switch ($group) {
            case 'list':
                $title = (string) ($ctx['title'] ?? 'العملاء');
                $hint = (string) ($ctx['hint'] ?? '');
                $ids = $clientIds;
                break;

            case 'kpi':
                switch ($key) {
                    case 'total':
                        [$title, $hint, $ids] = ['كل العملاء', 'كل عملاء النطاق المختار بفلاتر اللوحة', $clientIds];
                        break;
                    case 'active':
                    case 'stopped':
                        $wanted = array_flip(array_map('intval', $key === 'active' ? Statuses::activeClientStatusIds() : Statuses::stoppedClientStatusIds()));
                        $statuses = $this->clientColumn($clientIds, 'status_id');
                        $ids = array_values(array_filter($clientIds, fn ($id) => isset($statuses[$id]) && isset($wanted[(int) $statuses[$id]])));
                        $title = $key === 'active' ? 'العملاء النشطون' : 'العملاء الموقوفون';
                        $hint = 'حالتُهم الحالية كما ضُبطت في شاشة «حالات العملاء»';
                        break;
                    case 'new':
                        $created = $this->clientColumn($clientIds, 'created_at');
                        $from = $ctx['new_from']->toDateTimeString();
                        $to = $ctx['new_to']->toDateTimeString();
                        $ids = array_values(array_filter($clientIds, fn ($id) => isset($created[$id]) && $created[$id] >= $from && $created[$id] <= $to));
                        foreach ($ids as $id) {
                            $values[$id] = ['created_at' => substr((string) $created[$id], 0, 10)];
                        }
                        $columns = [['key' => 'created_at', 'label' => 'تاريخ الإضافة', 'type' => 'date']];
                        $sort = ['created_at', 'desc'];
                        $title = 'العملاء الجدد';
                        $hint = 'أُضيفوا بين '.$ctx['new_from']->toDateString().' و'.$ctx['new_to']->toDateString();
                        break;
                    case 'needs_visit':
                        $map = $this->needsVisitMap($clientIds, (int) $ctx['visit_gap']);
                        $ids = array_keys($map);
                        foreach ($map as $id => $row) {
                            $values[$id] = ['last_visit' => $row['last_visit'], 'last_visit_days' => $row['days']];
                        }
                        $omit = ['last_visit'];
                        $columns = [['key' => 'last_visit_days', 'label' => 'آخر زيارة', 'type' => 'visit']];
                        $sort = ['last_visit_days', 'desc_nulls_first'];
                        $title = 'بحاجة زيارة';
                        $hint = 'مضى على آخر زيارةٍ لهم '.$ctx['visit_gap'].' يوماً فأكثر أو لم يُزاروا قط (دون الموقوفين)';
                        break;
                    case 'overdue':
                        $map = $this->overdueMap($clientIds, (int) $ctx['overdue_days']);
                        $ids = array_keys($map);
                        foreach ($map as $id => $row) {
                            $values[$id] = ['overdue' => $row['overdue'], 'oldest_days' => $row['oldest_days']];
                        }
                        $columns = [
                            ['key' => 'overdue', 'label' => 'المبلغ المتأخّر', 'type' => 'money'],
                            ['key' => 'oldest_days', 'label' => 'أقدم تأخّر', 'type' => 'days'],
                        ];
                        $sort = ['oldest_days', 'desc'];
                        $title = 'العملاء المتأخّرون';
                        $hint = 'لهم مبلغٌ تجاوز استحقاقُه '.$ctx['overdue_days'].' يوماً — المبلغ المتأخّر هو ذلك الجزء وحده';
                        break;
                    case 'debtors':
                        $positions = $this->debtPositions($clientIds);
                        $ids = array_keys($positions);
                        foreach ($positions as $id => $p) {
                            $values[$id] = ['oldest_days' => $p['oldest_days'], 'weighted_age' => (int) round($p['weighted_age'])];
                        }
                        $columns = [
                            ['key' => 'oldest_days', 'label' => 'أقدم تأخّر', 'type' => 'days'],
                            ['key' => 'weighted_age', 'label' => 'متوسط عمر الدين', 'type' => 'days'],
                        ];
                        $sort = ['balance', 'desc'];
                        $title = 'العملاء المدينون';
                        $hint = 'كل عميلٍ رصيدُ حسابه مدين';
                        break;
                    case 'creditors':
                        $credits = $this->creditPositions($clientIds);
                        $ids = array_keys($credits);
                        foreach ($credits as $id => $amount) {
                            $values[$id] = ['credit' => $amount];
                        }
                        $columns = [['key' => 'credit', 'label' => 'المبلغ لصالح العميل', 'type' => 'money']];
                        $sort = ['credit', 'desc'];
                        $title = 'أرصدة دائنة للعملاء';
                        $hint = 'دفعوا أكثرَ ممّا عليهم أو مقدَّماً — مبالغُ لهم علينا، لا تدخل المديونية ولا تُطرح منها';
                        break;
                    default:
                        return null;
                }
                break;

            case 'lifecycle':
                $meta = ClientDashboardSegments::lifecycle()[$key] ?? null;
                if (! $meta) {
                    return null;
                }
                $map = $this->lifecycleMap($clientIds);
                $ids = array_keys(array_filter($map, fn ($row) => $row['key'] === $key));
                foreach ($ids as $id) {
                    $row = $map[$id];
                    $values[$id] = ['last_activity' => $row['last_activity'], 'activity_days' => $row['days'], 'invoices' => $row['invoices']];
                }
                $columns = [
                    ['key' => 'last_activity', 'label' => 'آخر فاتورة', 'type' => 'date'],
                    ['key' => 'activity_days', 'label' => 'منذ', 'type' => 'days'],
                    ['key' => 'invoices', 'label' => 'عدد الفواتير', 'type' => 'number'],
                ];
                $sort = ['activity_days', 'asc'];
                $title = 'دورة حياة العميل: '.$meta['label'];
                $hint = $meta['hint'];
                break;

            case 'target':
                $info = $this->customerTargetInfo($clientIds);

                if ($key === 'downgraded') {
                    $ids = array_keys(array_filter($info['clients'], fn ($row) => $row['downgraded']));
                    $title = 'عملاء تراجع هدفهم';
                    $hint = 'انتقلوا في آخر تقييم إلى هدفٍ أدنى أولويةً من هدفهم السابق';
                    $columns = [['key' => 'current_target', 'label' => 'الهدف الحالي', 'type' => 'text']];
                } else {
                    $meta = $info['rows'][$key] ?? null;
                    if (! $meta) {
                        return null;
                    }
                    $ids = array_keys(array_filter($info['clients'], fn ($row) => $row['key'] === $key));
                    $title = 'أهداف العملاء: '.$meta['label'];
                    $hint = $meta['hint'];
                    $columns = [];
                }

                foreach ($ids as $id) {
                    $values[$id] = [
                        'current_target' => $info['clients'][$id]['current_name'],
                        'previous_target' => $info['clients'][$id]['previous_name'],
                        'evaluated_at' => $info['clients'][$id]['evaluated_at'],
                    ];
                }
                $columns[] = ['key' => 'previous_target', 'label' => 'الهدف السابق', 'type' => 'text'];
                $columns[] = ['key' => 'evaluated_at', 'label' => 'آخر تقييم', 'type' => 'date'];
                break;

            case 'aging':
                $aging = ClientDashboardSegments::aging();
                $meta = $aging[$key] ?? null;
                if (! $meta) {
                    return null;
                }
                $ids = [];
                foreach ($this->debtPositions($clientIds) as $id => $position) {
                    $inBucket = array_filter($position['items'], fn ($item) => ClientDashboardSegments::bucketFor($item['days'], $aging) === $key);
                    if ($inBucket === []) {
                        continue;
                    }
                    $ids[] = $id;
                    $values[$id] = [
                        'bucket_amount' => round(array_sum(array_column($inBucket, 'amount')), 2),
                        'oldest_days' => max(array_column($inBucket, 'days')),
                    ];
                }
                $columns = [
                    ['key' => 'bucket_amount', 'label' => 'المبلغ في الشريحة', 'type' => 'money'],
                    ['key' => 'oldest_days', 'label' => 'أقدم تأخّر فيها', 'type' => 'days'],
                ];
                $sort = ['bucket_amount', 'desc'];
                $title = 'تقادم المديونية: '.$meta['label'];
                $hint = $meta['hint'].'. العميل قد يظهر في أكثر من شريحة: ديونه موزَّعة بأعمارها.';
                break;

            case 'visit_state':
                $meta = self::VISIT_STATES[$key] ?? null;
                if (! $meta) {
                    return null;
                }
                [$ids, $values] = $this->visitDrillValues($this->visitStateRows($clientIds, $ctx['from'], $ctx['to']), [$key]);
                $columns = self::visitColumns();
                $sort = ['visit_count', 'desc'];
                $title = 'نتائج الزيارات: '.$meta['label'];
                $hint = $meta['hint'].' — '.$ctx['range_label'];
                break;

            case 'visit_result':
                $resultMeta = [
                    'success' => ['label' => 'ناجحة', 'hint' => 'زيارة مكتملة وللعميل في يومها فاتورة أو سند قبض'],
                    'negative' => ['label' => 'سلبية', 'hint' => 'زيارة مكتملة بلا فاتورة ولا سند قبض — ومنها «سأعود لاحقاً»'],
                ];
                $meta = $resultMeta[$key] ?? null;
                if (! $meta) {
                    return null;
                }
                [$ids, $values] = $this->visitDrillValues($this->visitResultRows($clientIds, $ctx['from'], $ctx['to']), [$key]);
                $columns = self::visitColumns();
                $sort = ['visit_count', 'desc'];
                $title = 'نتائج الزيارات المكتملة: '.$meta['label'];
                $hint = $meta['hint'].' — '.$ctx['range_label'];
                break;

            case 'visits':
            case 'plan':
            case 'plan_week':
                $definition = $group === 'visits' ? self::VISIT_OUTCOMES : self::PLAN_COVERAGE;
                // «سلبية» في بطاقة نتائج الزيارات = سلبية + «سأعود لاحقاً» معاً — `visitOutcomeCounts()`
                $negative = $group === 'visits' && $key === 'negative';
                $drillKeys = $negative ? ['failed', 'return_later'] : [$key];
                $meta = $negative
                    ? ['label' => 'سلبية', 'hint' => 'أُنهيت بلا فاتورة ولا سند قبض — ومنها «سأعود لاحقاً»']
                    : ($definition[$key] ?? null);
                if (! $meta) {
                    return null;
                }
                // `plan_week` = بندُ مركز التنبيهات: آخر 7 أيام حتى أمس
                [$from, $to, $rangeLabel] = $group === 'plan_week'
                    ? [Carbon::today()->subDays(7), Carbon::yesterday(), 'آخر 7 أيام حتى أمس']
                    : [$ctx['from'], $ctx['to'], $ctx['range_label']];
                $rows = $group === 'visits'
                    ? $this->visitRows($clientIds, $from, $to)
                    : $this->planRows($clientIds, $from, $to);
                [$ids, $values] = $this->visitDrillValues($rows, $drillKeys);
                $columns = self::visitColumns();
                $sort = ['visit_count', 'desc'];
                $title = ($group === 'visits' ? 'نتائج الزيارات: ' : 'تغطية خطّ السير: ').$meta['label'];
                $hint = $meta['hint'].' — '.$rangeLabel;
                break;

            case 'month':
                $from = Carbon::now()->startOfMonth();
                $to = Carbon::now()->endOfMonth();
                if ($key === 'visits' || $key === 'success') {
                    $keys = $key === 'visits' ? array_keys(self::VISIT_OUTCOMES) : ['success'];
                    [$ids, $values] = $this->visitDrillValues($this->visitRows($clientIds, $from, $to), $keys);
                    $columns = self::visitColumns();
                    $sort = ['visit_count', 'desc'];
                    $title = $key === 'visits' ? 'زيارات هذا الشهر' : 'زيارات ناجحة هذا الشهر';
                    $hint = $key === 'visits' ? 'كل الزيارات المسجَّلة هذا الشهر بكل نتائجها' : 'أُنهيت وفيها فاتورة أو تحصيل';
                } elseif ($key === 'invoices') {
                    $inv = $this->monthInvoicesByClient($clientIds);
                    $ids = array_keys($inv);
                    foreach ($inv as $id => $row) {
                        $values[$id] = ['invoices' => $row['count'], 'invoice_total' => $row['total']];
                    }
                    $columns = [
                        ['key' => 'invoices', 'label' => 'عدد الفواتير', 'type' => 'number'],
                        ['key' => 'invoice_total', 'label' => 'قيمتها', 'type' => 'money'],
                    ];
                    $sort = ['invoice_total', 'desc'];
                    $title = 'فواتير هذا الشهر';
                    $hint = 'فواتير مبيعاتٍ غير ملغاة، دون المرتجعات';
                } elseif ($key === 'notes') {
                    $notes = $this->monthNotesByClient($clientIds);
                    $ids = array_keys($notes);
                    foreach ($notes as $id => $n) {
                        $values[$id] = ['notes' => $n];
                    }
                    $columns = [['key' => 'notes', 'label' => 'عدد الملاحظات', 'type' => 'number']];
                    $sort = ['notes', 'desc'];
                    $title = 'ملاحظات المتابعة هذا الشهر';
                    $hint = '';
                } else {
                    return null;
                }
                break;

            case 'pending':
                return $this->pendingDrillDown((string) $key, $clientIds);

            case 'emp_assigned':
            case 'emp_route':
            case 'emp_manual':
            case 'unassigned':
                $activity = $this->employeeActivityInternal($clientIds, $ctx['from'], $ctx['to']);

                if ($group === 'unassigned') {
                    $ids = array_values(array_diff($clientIds, array_keys($activity['assigned_clients'])));
                    $title = 'عملاء بلا موظف مسؤول';
                    $hint = 'لا موظفَ مُسنَدٌ إليهم في ملف العميل (قد يكونون في خطّ سير أحدٍ رغم ذلك)';
                    break;
                }

                $employee = $activity['rows'][$key] ?? null;
                if (! $employee) {
                    return null;
                }

                if ($group === 'emp_assigned' || $group === 'emp_route') {
                    $ids = array_keys($employee[$group === 'emp_assigned' ? 'assigned' : 'route']);
                    $title = ($group === 'emp_assigned' ? 'العملاء المُسنَدون إلى: ' : 'عملاء خطّ سير: ').$employee['name'];
                    $hint = $group === 'emp_assigned' ? 'الموظف المسؤول في ملف العميل' : 'في خطّ سيره خلال '.$ctx['range_label'];
                } else {
                    $source = $this->visitRows($clientIds, $ctx['from'], $ctx['to']);
                    $mine = array_map(
                        fn ($row) => ['key' => 'v'] + $row,
                        array_values(array_filter($source, fn ($row) => $row['employee_id'] === $employee['user_id']))
                    );
                    [$ids, $values] = $this->visitDrillValues($mine, ['v']);
                    $columns = self::visitColumns();
                    $sort = ['visit_count', 'desc'];
                    $title = ($group === 'emp_manual' ? 'الزيارات اليدوية لـ: ' : 'زيارات الموقع لـ: ').$employee['name'];
                    $hint = $ctx['range_label'];
                }
                break;

            case 'notif':
                return in_array($key, ['unanswered', 'answered', 'all'], true)
                    ? $this->notificationDrillDown($key, $clientIds)
                    : null;

            case 'movement':
                if (! in_array($key, ['sales', 'returns', 'collections'], true)) {
                    return null;
                }
                $field = ['sales' => 'invoices', 'returns' => 'returns', 'collections' => 'receipts'][$key];
                $accountToClient = [];
                foreach (array_chunk($clientIds, 1000) as $chunk) {
                    foreach (Account::ledger()->whereIn('client_id', $chunk)->get(['id', 'client_id']) as $account) {
                        $accountToClient[(int) $account->id] = (int) $account->client_id;
                    }
                }
                $totals = $accountToClient === [] ? [] : $this->ledger->movementTotals(
                    array_keys($accountToClient), $ctx['from']->toDateString(), $ctx['to']->toDateString()
                );
                $ids = [];
                foreach ($totals as $accountId => $row) {
                    $amount = round((float) $row[$field], 2);
                    $clientId = $accountToClient[$accountId] ?? null;
                    if (! $clientId || abs($amount) < 0.005) {
                        continue;
                    }
                    $ids[] = $clientId;
                    $values[$clientId] = ['amount' => $amount];
                }
                $label = ['sales' => 'المبيعات', 'returns' => 'المرتجعات والإشعارات', 'collections' => 'التحصيل'][$key];
                $columns = [['key' => 'amount', 'label' => $label, 'type' => 'money']];
                $sort = ['amount', 'desc'];
                $title = $label.' حسب العميل';
                $hint = 'من دفتر الأستاذ — '.$ctx['range_label'];
                break;

            default:
                return null;
        }

        return [
            'title' => $title,
            'hint' => $hint,
            'columns' => array_merge(
                [
                    ['key' => 'code', 'label' => 'الكود', 'type' => 'text'],
                    ['key' => 'name', 'label' => 'العميل', 'type' => 'client'],
                    ['key' => 'phone', 'label' => 'الجوال', 'type' => 'text'],
                    ['key' => 'status', 'label' => 'الحالة', 'type' => 'status'],
                    // الحالة نشط/موقوف وحدها؛ «الوضع» تصنيفُ العميل نفسه
                    // (ضريبي، بلدية…) وهو ما يُميّز صفوف القائمة
                    ['key' => 'stage', 'label' => 'وضع العميل', 'type' => 'status'],
                ],
                $columns,
                in_array('last_visit', $omit, true) ? [] : [['key' => 'last_visit_days', 'label' => 'آخر زيارة', 'type' => 'visit']],
                [['key' => 'balance', 'label' => 'الرصيد', 'type' => 'money']],
            ),
            'rows' => $this->rows($ids, $values, $omit, $sort),
        ];
    }

    /**
     * صفوفُ القائمة: بياناتُ العميل الأساسية + قيمُ الشريحة + آخرُ زيارة ورصيد.
     *
     * @param  array<int>  $ids
     * @param  array<int, array<string, mixed>>  $values
     * @param  array{0:string, 1:string}|null  $sort
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $ids, array $values, array $omit, ?array $sort): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $lastVisits = in_array('last_visit', $omit, true) ? [] : $this->lastVisitDates($ids);
        $balances = [];
        $rows = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (Account::ledger()->whereIn('client_id', $chunk)->get(['client_id', 'balance']) as $account) {
                $balances[(int) $account->client_id] = (float) $account->balance;
            }

            $clients = Client::query()
                ->whereIn('id', $chunk)
                ->with(['status_client:id,name,color', 'registrationStage:id,name,color'])
                ->get(['id', 'code', 'trade_name', 'first_name', 'last_name', 'phone', 'mobile', 'status_id', 'registration_stage_id']);

            foreach ($clients as $client) {
                $row = [
                    'id' => (int) $client->id,
                    'url' => route('clients.show', $client->id),
                    'code' => (string) ($client->code ?? ''),
                    // الأسماءُ والأرقامُ مُدخلةٌ يدوياً وفيها «,» ومسافاتٌ وحدها
                    'name' => preg_replace('/\s+/u', ' ', trim((string) $client->display_name)) ?? (string) $client->display_name,
                    'phone' => trim((string) $client->mobile, " ,\t") ?: trim((string) $client->phone, " ,\t"),
                    'status' => $client->status_client->name ?? 'بدون حالة',
                    'status_color' => $client->status_client->color ?? '#999999',
                    'stage' => $client->registrationStage->name ?? 'بدون وضع',
                    'stage_color' => $client->registrationStage->color ?? '#999999',
                    'balance' => round($balances[$client->id] ?? 0.0, 2),
                ];

                if (! in_array('last_visit', $omit, true)) {
                    $last = $lastVisits[$client->id] ?? null;
                    $row['last_visit'] = $last;
                    $row['last_visit_days'] = self::daysSince($last);
                }

                $rows[] = array_merge($row, $values[$client->id] ?? []);
            }
        }

        [$sortKey, $direction] = $sort ?? ['name', 'asc'];

        usort($rows, function ($a, $b) use ($sortKey, $direction) {
            $x = $a[$sortKey] ?? null;
            $y = $b[$sortKey] ?? null;

            if ($direction === 'desc_nulls_first') {
                if ($x === null || $y === null) {
                    return ($x === null ? 0 : 1) <=> ($y === null ? 0 : 1);
                }

                return $y <=> $x;
            }

            return $direction === 'desc' ? $y <=> $x : $x <=> $y;
        });

        return $rows;
    }

    // ═════════════════════════════ ما بقي كما هو ═════════════════════════════

    /**
     * مركز التنبيهات — عناصر حقيقية معلَّقة تحتاج إجراءً: طلبات تغيير حالة
     * معلَّقة، ديون مشكوك فيها معلَّقة، تحويلات فواتير معلَّقة، زيارات فائتة
     * هذا الأسبوع، عملاء تجاوزوا حدّهم الائتماني، وإشعارات عملاء لم يصل
     * عليها ردٌّ بعد. كل بندٍ من جدولٍ حقيقي —
     * لا بند «شكاوى SLA» أو «اختلاف جرد» هنا: لا نموذج لهما في المشروع.
     *
     * كل بندٍ يحمل `route`/`params` نحو شاشة تفصيله الحقيقية — لا رقماً
     * معلَّقاً بلا وجهة — لكن فقط إن ملك المستخدم الحالي صلاحية تلك الشاشة؛
     * غياب الصلاحية يترك الرقم كما هو بلا رابط بدل توجيهه إلى 403.
     * «زيارات لم تُنفَّذ» تفتح قائمةَ عملائها داخل اللوحة (`segment`).
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{key:string, label:string, count:int, icon:string, color:string, route:?string, params:array, segment?:string}>
     */
    public function notificationsCenter(array $clientIds): array
    {
        $items = [];

        if (! empty($clientIds)) {
            $pendingStatus = ClientStatusRequest::whereIn('client_id', $clientIds)->where('status', 'pending')->count();
            if ($pendingStatus > 0) {
                $items[] = [
                    'key' => 'pending_status', 'label' => 'طلبات تغيير حالة معلَّقة', 'count' => $pendingStatus,
                    'icon' => 'fa-user-clock', 'color' => 'warning',
                    'route' => null, 'params' => [], 'segment' => 'pending.status_requests',
                ];
            }

            $pendingDebt = DoubtfulDebt::whereIn('client_id', $clientIds)->where('status', 'pending')->count();
            if ($pendingDebt > 0) {
                $items[] = [
                    'key' => 'pending_doubtful_debt', 'label' => 'ديون مشكوك فيها بانتظار المراجعة', 'count' => $pendingDebt,
                    'icon' => 'fa-file-invoice-dollar', 'color' => 'danger',
                    'route' => null, 'params' => [], 'segment' => 'pending.doubtful_debts',
                ];
            }

            // إشعارات العملاء التي لم يصل عليها ردٌّ — نفس قاعدة صفحة
            // الإشعارات (شرط الظهور + قصر الموظف على إشعاراته) كي يفتح
            // البندُ قائمةً بنفس العدد الذي يعرضه
            // بندان من صفوف `clientNotificationRows()` نفسها التي تفتحها القائمة
            $notificationRows = $this->clientNotificationRows($clientIds);
            $unanswered = count(array_filter($notificationRows, fn ($row) => ! $row['answered']));
            $answered = count($notificationRows) - $unanswered;

            if ($unanswered > 0) {
                $items[] = [
                    'key' => 'unanswered_client_notifications', 'label' => 'إشعارات عملاء بلا رد', 'count' => $unanswered,
                    'icon' => 'fa-comment-slash', 'color' => 'warning', 'route' => null, 'params' => [],
                    'segment' => 'notif.unanswered',
                ];
            }
            if ($answered > 0) {
                $items[] = [
                    'key' => 'answered_client_notifications', 'label' => 'إشعارات عملاء تم الرد عليها', 'count' => $answered,
                    'icon' => 'fa-comments', 'color' => 'success', 'route' => null, 'params' => [],
                    'segment' => 'notif.answered',
                ];
            }

            // نفس «تغطية خطّ السير» — صفٌّ مخطَّطٌ للعميل فيه دليلُ حضورٍ ليس فائتاً
            $missedVisits = count(array_filter(
                $this->planRows($clientIds, Carbon::today()->subDays(7), Carbon::yesterday()),
                fn ($row) => $row['key'] === 'not_visited'
            ));
            if ($missedVisits > 0) {
                $items[] = [
                    'key' => 'missed_visits', 'label' => 'زيارات خطّ سير لم تتم خلال آخر 7 أيام', 'count' => $missedVisits,
                    'icon' => 'fa-calendar-times', 'color' => 'danger', 'route' => null, 'params' => [],
                    'segment' => 'plan_week.not_visited',
                ];
            }

            $overCreditLimit = Client::whereIn('id', $clientIds)
                ->whereNotNull('credit_limit')
                ->where('credit_limit', '>', 0)
                ->whereHas('account', function ($q) {
                    $q->ledger()->whereColumn('accounts.balance', '>', 'clients.credit_limit');
                })
                ->count();
            if ($overCreditLimit > 0) {
                $items[] = [
                    'key' => 'over_credit_limit', 'label' => 'عملاء تجاوزوا الحدّ الائتماني', 'count' => $overCreditLimit,
                    'icon' => 'fa-credit-card', 'color' => 'danger',
                    'route' => canAny(['clients_view_all_clients', 'clients_view_own_clients', 'clients_view_by_employee_groups', 'clients_view_by_daily_itinerary']) ? 'clients.index' : null,
                    'params' => ['over_credit_limit' => 1],
                ];
            }
        }

        $pendingTransfers = InvoiceTransfer::whereIn('client_id', $clientIds)->where('status', 'pending')->count();
        if ($pendingTransfers > 0) {
            $items[] = [
                'key' => 'pending_transfers', 'label' => 'تحويلات فواتير معلَّقة', 'count' => $pendingTransfers,
                'icon' => 'fa-exchange-alt', 'color' => 'info',
                'route' => hasPermission('clients_invoice_transfer_view') ? 'invoice-transfers.index' : null,
                'params' => [],
            ];
        }

        return $items;
    }

    /**
     * المنافسون المُلاحَظون خلال الفترة — من `VisitCompetitorObservation`
     * الحرّة النص (لا نموذج «نوع منافس» منفصل في المشروع)، مُجمَّعةً بالاسم.
     *
     * @param  array<int>  $clientIds
     * @return array{top: Collection, new_this_period_count: int}
     */
    public function competitorsSummary(array $clientIds, Carbon $since): array
    {
        if (empty($clientIds)) {
            return ['top' => collect(), 'new_this_period_count' => 0];
        }

        $observations = VisitCompetitorObservation::whereIn('client_id', $clientIds)->get(['competitor_name', 'price', 'created_at']);

        $top = $observations->groupBy('competitor_name')
            ->map(function ($rows, $name) {
                return [
                    'name' => $name,
                    'mentions' => $rows->count(),
                    'avg_price' => round((float) $rows->avg('price'), 2),
                ];
            })
            ->sortByDesc('mentions')
            ->take(5)
            ->values();

        $firstSeenByName = $observations->groupBy('competitor_name')->map(fn ($rows) => $rows->min('created_at'));
        $newCount = $firstSeenByName->filter(fn ($date) => $date && Carbon::parse($date)->gte($since))->count();

        return ['top' => $top, 'new_this_period_count' => $newCount];
    }

    // ═════════════════════════════ أدوات ═════════════════════════════

    /** أيامٌ كاملة من تاريخٍ حتى اليوم — `null` لتاريخٍ غائب */
    private static function daysSince(?string $date): ?int
    {
        return $date ? self::daysBetween($date, Carbon::today()->toDateString()) : null;
    }

    private static function daysBetween(string $from, string $to): int
    {
        $a = Carbon::parse(substr($from, 0, 10))->startOfDay()->getTimestamp();
        $b = Carbon::parse(substr($to, 0, 10))->startOfDay()->getTimestamp();

        return (int) round(($b - $a) / 86400);
    }

    /**
     * @param  array<string, array{label:string, color:string, hint:string}>  $definition
     * @param  array<int, array{key:string}>  $map
     */
    private function countSegments(array $definition, array $map): array
    {
        $counts = array_count_values(array_column($map, 'key'));
        $result = [];

        foreach ($definition as $key => $meta) {
            // «غير مصنَّف» لا يظهر إلا إن وقع فيه عميل — وجودُه تنبيهٌ لثغرةٍ في الشرائح
            if ($key === 'other' && empty($counts[$key])) {
                continue;
            }

            $result[$key] = $meta + ['key' => $key, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $result;
    }

    /** عمودٌ واحد من `clients` لكل العملاء: `[id => value]` */
    private function clientColumn(array $clientIds, string $column): array
    {
        return $this->remember('col:'.$column, $clientIds, function () use ($clientIds, $column) {
            $out = [];
            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $values = DB::table('clients')->when($chunk !== null, fn ($q) => $q->whereIn('id', $chunk))->pluck($column, 'id');
                foreach ($values as $id => $value) {
                    if ($wanted !== null && ! isset($wanted[(int) $id])) {
                        continue;
                    }
                    $out[(int) $id] = $value;
                }
            }

            return $out;
        });
    }

    /**
     * أجزاءُ الاستعلام لنطاق عملاء: `[[chunk, wanted], …]`.
     *
     * نطاقٌ ضيّق ⇒ `IN` مجزّأٌ بألف (`wanted = null`). نطاقٌ واسع ⇒ جزءٌ
     * واحد بلا `IN` (`chunk = null`) وخريطةُ المطلوبين للتصفية في PHP:
     * أربعةُ `IN` بألف معرّفٍ على `employee_client_visits` كانت ٢٦٠ مللي
     * ثانية للواحد، ومسحُ الجدول مرّةً أرخص منها — نفسُ علّة
     * `CustomerLedgerService::WIDE_SCOPE`.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{0: ?array<int>, 1: ?array<int, int>}>
     */
    private function scopedChunks(array $clientIds): array
    {
        if (count($clientIds) > self::WIDE_SCOPE) {
            return [[null, array_flip($clientIds)]];
        }

        return array_map(fn ($chunk) => [$chunk, null], array_chunk($clientIds, 1000));
    }

    private function remember(string $name, array $clientIds, callable $build): mixed
    {
        $key = $name.':'.md5(implode(',', $clientIds));

        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $build();
        }

        return $this->memo[$key];
    }

    // ═════════════════════════════ إشعارات العملاء ═════════════════════════════

    /**
     * إشعاراتُ العملاء الظاهرة لهذا المستخدم، كلٌّ بحالة ردّه وآخر ردٍّ عليه.
     *
     * «مردودٌ عليه» يُقرَّر بـ`notifications::scopeAwaitingReply()` نفسها التي
     * تقرؤها صفحةُ الإشعارات — فالعددُ هنا لا يخالف فلتر «بلا رد» هناك. ونصُّ
     * الردّ يُقرأ بنفس القاعدة: الردُّ الحديث بـ`parent_id`، والقديمُ بعنوان
     * «رد على: …» أو «رد عميل: …» لنفس العميل بعد تاريخ الأصل.
     *
     * والموظفُ يرى ما أرسله أو استقبله وحده — كما في صفحة الإشعارات وتفاصيلها.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    public function clientNotificationRows(array $clientIds): array
    {
        return $this->remember('client_notifications:'.auth()->id(), $clientIds, function () use ($clientIds) {
            if ($clientIds === []) {
                return [];
            }

            $base = fn () => notifications::visibleInLists()
                ->forClients()
                ->whereIn('client_id', $clientIds)
                ->when(auth()->user()?->isEmployee(), function ($q) {
                    $userId = auth()->id();
                    $q->where(fn ($w) => $w->where('user_id', $userId)->orWhere('receiver_id', $userId));
                });

            $answeredIds = array_flip($base()->awaitingReply(false)->pluck('notifications.id')->all());
            $list = $base()->with(['user:id,name', 'receiver:id,name', 'client'])->orderByDesc('created_at')->get();

            if ($list->isEmpty()) {
                return [];
            }

            // السلاسلُ من المصدر نفسه الذي قرّر «مردودٌ عليه» — لا مطابقةٌ ثانية هنا
            $threads = notifications::replyThreads();
            // وإشعاراتُ «طلب تغيير الحالة» ردُّها القرارُ نفسُه — اعتمادٌ أو رفض
            $decisions = notifications::statusDecisionReplies();
            $replyIds = [];
            foreach ($list as $notification) {
                array_push($replyIds, ...($threads[$notification->id] ?? []));
            }

            $replies = notifications::query()
                ->with('user:id,name')
                ->whereIn('id', $replyIds ?: [0])
                ->get()
                ->keyBy('id');

            $rows = [];
            foreach ($list as $notification) {
                $thread = collect($threads[$notification->id] ?? [])
                    ->map(fn ($id) => $replies->get($id))
                    ->filter()
                    ->values();

                $last = $thread->last();
                $decision = $last === null ? ($decisions[$notification->id] ?? null) : null;

                $rows[] = [
                    'id' => (int) $notification->id,
                    'client_id' => (int) $notification->client_id,
                    'client_name' => $notification->client?->display_name ?? ('#'.$notification->client_id),
                    'client_code' => (string) ($notification->client?->code ?? ''),
                    'title' => (string) $notification->title,
                    'description' => trim(strip_tags((string) $notification->description)),
                    'sender' => $notification->user?->name,
                    'receiver' => $notification->receiver?->name,
                    'date' => $notification->created_at?->format('Y-m-d H:i'),
                    'answered' => isset($answeredIds[$notification->id]),
                    'reply' => $last ? trim(strip_tags((string) $last->description)) : ($decision['text'] ?? null),
                    'reply_by' => $last ? $last->user?->name : ($decision['by_name'] ?? null),
                    'reply_date' => $last ? $last->created_at?->format('Y-m-d H:i') : ($decision['at'] ?? null),
                    'replies' => $thread->count() + ($decision ? 1 : 0),
                ];
            }

            return $rows;
        });
    }

    /**
     * قائمةُ الإشعارات (لا العملاء) — صفٌّ لكل إشعار بنصّه وردّه ورابط تفاصيله.
     *
     * رابطُ «التفاصيل» يفتح `notifications.show` — صفحةُ الإشعار بسلسلة
     * ردوده — لمن يملك صلاحيتها وحده، كي لا يقود إلى 403.
     *
     * @param  array<int>  $clientIds
     */
    private function notificationDrillDown(string $key, array $clientIds): array
    {
        $canOpen = hasPermission('sales.notifications.view');
        $rows = [];

        foreach ($this->clientNotificationRows($clientIds) as $n) {
            if (($key === 'answered' && ! $n['answered']) || ($key === 'unanswered' && $n['answered'])) {
                continue;
            }

            $rows[] = [
                'id' => $n['id'],
                'url' => route('clients.show', $n['client_id']),
                'name' => $n['client_name'],
                'code' => $n['client_code'],
                'phone' => '',
                'title' => $n['title'],
                'description' => $n['description'],
                'sender' => ($n['sender'] ?? '—').' ← '.($n['receiver'] ?? '—'),
                'date' => $n['date'],
                'status' => $n['answered'] ? 'تم الرد' : 'بلا رد',
                'status_color' => $n['answered'] ? '#28c76f' : '#ff9f43',
                'reply' => $n['reply'],
                'reply_meta' => $n['reply'] !== null
                    ? ($n['reply_by'] ?? '—').' — '.$n['reply_date'].($n['replies'] > 1 ? ' · '.$n['replies'].' ردود' : '')
                    : null,
                'details_url' => $canOpen ? route('notifications.show', $n['id']) : null,
            ];
        }

        return [
            'title' => ['unanswered' => 'إشعارات عملاء بلا رد', 'answered' => 'إشعارات عملاء تم الرد عليها', 'all' => 'إشعارات العملاء'][$key],
            'hint' => 'كل إشعارٍ بنصّه وآخر ردٍّ عليه، الأحدث أولاً. طلب اعتماد الحالة ردُّه الاعتماد أو الرفض. «التفاصيل» تفتح صفحة الإشعار بسلسلة ردوده.',
            'columns' => [
                ['key' => 'name', 'label' => 'العميل', 'type' => 'client'],
                ['key' => 'title', 'label' => 'الإشعار', 'type' => 'longtext'],
                ['key' => 'description', 'label' => 'نصّه', 'type' => 'longtext'],
                ['key' => 'sender', 'label' => 'من ← إلى', 'type' => 'text'],
                ['key' => 'date', 'label' => 'التاريخ', 'type' => 'date'],
                ['key' => 'status', 'label' => 'الحالة', 'type' => 'status'],
                ['key' => 'reply', 'label' => 'الرد', 'type' => 'reply'],
                ['key' => 'details_url', 'label' => '', 'type' => 'link'],
            ],
            'rows' => $rows,
        ];
    }

    // ═════════════════════════════ المعلَّقات ═════════════════════════════

    /**
     * طلباتُ تغيير الحالة المعلَّقة والديونُ المشكوك فيها — **داخل نطاق اللوحة**.
     *
     * كان البندان يفتحان صفحتيهما، وكلتاهما بلا حصرٍ بالفرع ولا بالعملاء: مستخدمٌ
     * يرى في اللوحة «2» لعملاء فرعه فيفتح صفحةً فيها «16» لكل المنشأة. صارت
     * القائمةُ من الاستعلام نفسه الذي عُدّ منه الرقم، ورابطُ كلِّ صفٍّ يفتح
     * صفحتَه لمن يملك صلاحيتها.
     *
     * @param  array<int>  $clientIds
     */
    private function pendingDrillDown(string $key, array $clientIds): ?array
    {
        if ($key === 'status_requests') {
            $canOpen = hasPermission('clients_approve_status');
            $rows = [];

            $requests = ClientStatusRequest::with(['client', 'oldStatus', 'newStatus', 'requester'])
                ->whereIn('client_id', $clientIds)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get();

            foreach ($requests as $request) {
                $rows[] = [
                    'id' => (int) $request->id,
                    'url' => route('clients.show', $request->client_id),
                    'name' => $request->client?->display_name ?? ('#'.$request->client_id),
                    'code' => (string) ($request->client?->code ?? ''),
                    'phone' => '',
                    'change' => ($request->oldStatus?->name ?? 'بدون حالة').' ← '.($request->newStatus?->name ?? '—'),
                    'requester' => $request->requester?->name ?? '—',
                    'date' => $request->created_at?->format('Y-m-d H:i'),
                    'notes' => $request->notes,
                    'details_url' => $canOpen
                        ? route('clients.status-requests.index', ['status' => 'pending', 'search' => $request->client?->code])
                        : null,
                ];
            }

            return [
                'title' => 'طلبات تغيير حالة معلَّقة',
                'hint' => 'طلبات عملاء هذا النطاق التي تنتظر الاعتماد. «التفاصيل» تفتح شاشة الطلبات على هذا العميل.',
                'columns' => [
                    ['key' => 'name', 'label' => 'العميل', 'type' => 'client'],
                    ['key' => 'change', 'label' => 'التغيير المطلوب', 'type' => 'text'],
                    ['key' => 'requester', 'label' => 'طلبه', 'type' => 'text'],
                    ['key' => 'date', 'label' => 'التاريخ', 'type' => 'date'],
                    ['key' => 'notes', 'label' => 'الملاحظات', 'type' => 'longtext'],
                    ['key' => 'details_url', 'label' => '', 'type' => 'link'],
                ],
                'rows' => $rows,
            ];
        }

        if ($key === 'doubtful_debts') {
            $canOpen = hasPermission('clients_manage_debts') && \Illuminate\Support\Facades\Route::has('doubtful-debts.show');
            $rows = [];

            $debts = DoubtfulDebt::with(['client', 'createdByUser'])
                ->whereIn('client_id', $clientIds)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get();

            foreach ($debts as $debt) {
                $rows[] = [
                    'id' => (int) $debt->id,
                    'url' => route('clients.show', $debt->client_id),
                    'name' => $debt->client?->display_name ?? ('#'.$debt->client_id),
                    'code' => (string) ($debt->client?->code ?? ''),
                    'phone' => '',
                    'original_balance' => round((float) $debt->original_balance, 2),
                    'provision_amount' => round((float) $debt->provision_amount, 2),
                    'provision_percentage' => $debt->provision_percentage !== null ? rtrim(rtrim(number_format((float) $debt->provision_percentage, 2), '0'), '.').'%' : '—',
                    'creator' => $debt->createdByUser?->name ?? '—',
                    'date' => $debt->created_at?->format('Y-m-d'),
                    'notes' => $debt->notes,
                    'details_url' => $canOpen ? route('doubtful-debts.show', $debt->id) : null,
                ];
            }

            return [
                'title' => 'ديون مشكوك فيها بانتظار المراجعة',
                'hint' => 'ديون عملاء هذا النطاق المصنَّفة مشكوكاً فيها ولم تُحصَّل أو تُعدَم بعد.',
                'columns' => [
                    ['key' => 'name', 'label' => 'العميل', 'type' => 'client'],
                    ['key' => 'original_balance', 'label' => 'الرصيد عند التصنيف', 'type' => 'money'],
                    ['key' => 'provision_amount', 'label' => 'المخصَّص', 'type' => 'money'],
                    ['key' => 'provision_percentage', 'label' => 'النسبة', 'type' => 'text'],
                    ['key' => 'creator', 'label' => 'صنّفه', 'type' => 'text'],
                    ['key' => 'date', 'label' => 'التاريخ', 'type' => 'date'],
                    ['key' => 'notes', 'label' => 'الملاحظات', 'type' => 'longtext'],
                    ['key' => 'details_url', 'label' => '', 'type' => 'link'],
                ],
                'rows' => $rows,
            ];
        }

        return null;
    }

    // ═════════════════════════════ أهداف العملاء ═════════════════════════════

    /**
     * توزيعُ عملاء النطاق على «أهداف العملاء» — الأهداف كما عُرّفت في الإعدادات.
     *
     * لا يُعاد التصنيف هنا: `customer_target_id` يكتبه `CustomerTargetService`
     * في مهمة «التقييم الديناميكي للعملاء» (`clients:evaluate-targets`)، واللوحة
     * تعرض ما كتبه. عميلٌ بلا هدف — أو بهدفٍ حُذف — يقع في «غير مقيَّم» فيبقى
     * المجموعُ = إجمالي العملاء. و«تراجع» = هدفُه السابق أعلى أولويةً من الحالي،
     * بنفس مقارنة الخدمة.
     *
     * @param  array<int>  $clientIds
     * @return array{rows: array<string, array{key: string, label: string, color: string, hint: string, count: int, active: int}>, evaluated_at: ?string, downgraded: int, clients: array<int, array<string, mixed>>}
     */
    public function customerTargetInfo(array $clientIds): array
    {
        return $this->remember('customer_targets', $clientIds, function () use ($clientIds) {
            $targets = DB::table('customer_targets')
                ->orderBy('sort_order')
                ->orderByDesc('priority')
                ->get(['id', 'name', 'color', 'description', 'is_active', 'priority'])
                ->keyBy('id');

            $current = $this->clientColumn($clientIds, 'customer_target_id');
            $previous = $this->clientColumn($clientIds, 'previous_target_id');
            $evaluated = $this->clientColumn($clientIds, 'target_evaluated_at');
            // عدد العملاء «النشطين» (حالتهم الحالية) داخل كل هدف — لا علاقة له
            // بأولوية الهدف نفسه، فبطاقة اللوحة تعرضه بجانب إجمالي كل شريحة
            $status = $this->clientColumn($clientIds, 'status_id');
            $activeStatusIds = array_flip(array_map('intval', Statuses::activeClientStatusIds()));

            $clients = [];
            $counts = [];
            $activeCounts = [];
            $evaluatedAt = null;
            $downgraded = 0;

            foreach ($clientIds as $id) {
                $currentId = $current[$id] ?? null;
                $previousId = $previous[$id] ?? null;
                $key = $currentId !== null && $targets->has($currentId) ? (string) $currentId : 'none';
                $isActive = isset($status[$id]) && isset($activeStatusIds[(int) $status[$id]]);

                $isDowngraded = $previousId !== null && (string) $previousId !== (string) $currentId
                    && (int) ($targets->get($previousId)?->priority ?? 0) > (int) ($targets->get($currentId)?->priority ?? 0);

                $clients[$id] = [
                    'key' => $key,
                    'downgraded' => $isDowngraded,
                    'current_name' => $key === 'none' ? 'غير مقيَّم' : $targets[$currentId]->name,
                    'previous_name' => $previousId !== null ? ($targets->get($previousId)?->name ?? ('#'.$previousId)) : null,
                    'evaluated_at' => isset($evaluated[$id]) ? substr((string) $evaluated[$id], 0, 10) : null,
                ];

                $counts[$key] = ($counts[$key] ?? 0) + 1;
                if ($isActive) {
                    $activeCounts[$key] = ($activeCounts[$key] ?? 0) + 1;
                }
                $downgraded += $isDowngraded ? 1 : 0;

                if (! empty($evaluated[$id]) && ($evaluatedAt === null || (string) $evaluated[$id] > $evaluatedAt)) {
                    $evaluatedAt = (string) $evaluated[$id];
                }
            }

            $rows = [];
            foreach ($targets as $target) {
                $count = (int) ($counts[(string) $target->id] ?? 0);

                // هدفٌ مطفأ لا يُعرض إلّا إن بقي عليه عملاء من تقييمٍ سابق
                if (! $target->is_active && $count === 0) {
                    continue;
                }

                $rows[(string) $target->id] = [
                    'key' => (string) $target->id,
                    'label' => $target->name.($target->is_active ? '' : ' (غير نشط)'),
                    'color' => $target->color ?: '#7367f0',
                    'hint' => (string) ($target->description ?? ''),
                    'count' => $count,
                    'active' => (int) ($activeCounts[(string) $target->id] ?? 0),
                ];
            }

            if (! empty($counts['none'])) {
                $rows['none'] = [
                    'key' => 'none', 'label' => 'غير مقيَّم', 'color' => '#b9b9c3',
                    'hint' => 'لم يطابق أيَّ هدفٍ نشط في آخر تقييم، أو لم يُقيَّم بعد',
                    'count' => (int) $counts['none'],
                    'active' => (int) ($activeCounts['none'] ?? 0),
                ];
            }

            return ['rows' => $rows, 'evaluated_at' => $evaluatedAt, 'downgraded' => $downgraded, 'clients' => $clients];
        });
    }

    // ═════════════════════════════ زيارات الموقع والموظفون ═════════════════════════════

    /**
     * ملاحظةُ متابعةٍ حقيقية — ما كتبه موظف.
     *
     * `client_relations` يحمل معها قيوداً آلية: كلُّ فاتورةٍ وسندِ قبضٍ وإشعارٍ
     * دائن وتسليمِ فاتورة يُكتب صفّاً بنوع `invoice`، وكلُّ إشعارٍ يُرسَل للعميل
     * صفّاً عمليتُه «إشعار للعميل». عدُّها «ملاحظات متابعة» كان يضخّم البطاقة.
     */
    private function realNotesQuery($query)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'invoice'))
            ->where(fn ($q) => $q->whereNull('process')->orWhere('process', 'not like', 'إشعار%'));
    }

    /**
     * الموظفون وعملاؤهم في النطاق — بأربعة مقاييس لا واحد.
     *
     * كانت البطاقة تعدّ الإسنادَ في `client_employee` وحده. و٩٢٨ من صفوفه بلا
     * `employee_id`، ففرعٌ عملاؤه ٢٤١ ويغطّيهم مندوبان بخطّ سيرٍ يومي ظهر «لا
     * توجد بيانات موظفين». صارت لكل موظف:
     *   - **مُسنَدون**: الموظف المسؤول في ملف العميل (`client_employee`)؛
     *   - **في خطّ السير**: عملاؤه في `employee_client_visits` خلال المدى؛
     *   - **زيارات يدوية** و**زيارات بالموقع** خلال المدى.
     *
     * الإسنادُ بمعرّف الموظف (`employees.id`) وخطُّ السير والزياراتُ بمعرّف
     * المستخدم (`users.id`) — يُوحَّدان عبر `users.employee_id`.
     *
     * @param  array<int>  $clientIds
     * @return array{rows: array<int, array{key: string, name: string, assigned: int, route: int, manual: int, gps: int}>, unassigned: int}
     */
    public function employeeActivity(array $clientIds, Carbon $from, Carbon $to): array
    {
        $internal = $this->employeeActivityInternal($clientIds, $from, $to);

        $rows = array_map(fn ($row) => [
            'key' => $row['key'],
            'name' => $row['name'],
            'assigned' => count($row['assigned']),
            'route' => count($row['route']),
            'manual' => $row['manual'],
        ], array_values($internal['rows']));

        return ['rows' => $rows, 'unassigned' => count($clientIds) - count($internal['assigned_clients'])];
    }

    /** @return array{rows: array<string, array<string, mixed>>, assigned_clients: array<int, true>} */
    private function employeeActivityInternal(array $clientIds, Carbon $from, Carbon $to): array
    {
        $tag = 'employee_activity:'.$from->toDateString().':'.$to->toDateString();

        return $this->remember($tag, $clientIds, function () use ($clientIds, $from, $to) {
            $userByEmployee = DB::table('users')->whereNotNull('employee_id')->pluck('id', 'employee_id')->map(fn ($id) => (int) $id)->all();
            $employeeByUser = array_flip($userByEmployee);

            $rows = [];
            $assignedClients = [];
            $touch = function (?int $userId, ?int $employeeId) use (&$rows): string {
                $key = $userId ? 'u'.$userId : 'e'.$employeeId;
                $rows[$key] ??= ['key' => $key, 'user_id' => $userId, 'employee_id' => $employeeId, 'name' => null, 'assigned' => [], 'route' => [], 'manual' => 0];

                return $key;
            };

            foreach ($this->scopedChunks($clientIds) as [$chunk, $wanted]) {
                $assignments = DB::table('client_employee')
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->whereNotNull('employee_id')
                    ->get(['client_id', 'employee_id']);

                foreach ($assignments as $assignment) {
                    $clientId = (int) $assignment->client_id;
                    if ($wanted !== null && ! isset($wanted[$clientId])) {
                        continue;
                    }
                    $employeeId = (int) $assignment->employee_id;
                    $key = $touch($userByEmployee[$employeeId] ?? null, $employeeId);
                    $rows[$key]['assigned'][$clientId] = true;
                    $assignedClients[$clientId] = true;
                }

                $route = DB::table('employee_client_visits')
                    ->when($chunk !== null, fn ($q) => $q->whereIn('client_id', $chunk))
                    ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
                    ->whereNotNull('employee_id')
                    ->distinct()
                    ->get(['client_id', 'employee_id']);

                foreach ($route as $entry) {
                    $clientId = (int) $entry->client_id;
                    if ($wanted !== null && ! isset($wanted[$clientId])) {
                        continue;
                    }
                    $userId = (int) $entry->employee_id;
                    $key = $touch($userId, $employeeByUser[$userId] ?? null);
                    $rows[$key]['route'][$clientId] = true;
                }
            }

            foreach ([['manual', $this->visitRows($clientIds, $from, $to)]] as [$field, $visits]) {
                foreach ($visits as $visit) {
                    if ($visit['employee_id']) {
                        $key = $touch($visit['employee_id'], $employeeByUser[$visit['employee_id']] ?? null);
                        $rows[$key][$field]++;
                    }
                }
            }

            $userNames = DB::table('users')->whereIn('id', array_filter(array_column($rows, 'user_id')))->pluck('name', 'id');
            $employeeNames = \App\Models\Employee::whereIn('id', array_filter(array_column($rows, 'employee_id')))->get()
                ->mapWithKeys(fn ($employee) => [$employee->id => $employee->full_name]);

            foreach ($rows as $key => $row) {
                $rows[$key]['name'] = trim((string) ($userNames[$row['user_id']] ?? $employeeNames[$row['employee_id']] ?? ('#'.($row['user_id'] ?: $row['employee_id']))));
            }

            uasort($rows, fn ($a, $b) => [count($b['assigned']) + count($b['route']), $b['manual']]
                <=> [count($a['assigned']) + count($a['route']), $a['manual']]);

            return ['rows' => $rows, 'assigned_clients' => $assignedClients];
        });
    }
}
