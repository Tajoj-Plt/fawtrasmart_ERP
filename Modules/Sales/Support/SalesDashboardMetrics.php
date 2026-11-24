<?php

namespace Modules\Sales\Support;

use App\Models\Branch;
use App\Services\ClientDashboardService;
use App\Models\TargetSetting;
use App\Services\TargetCalculationService;
use App\Support\ClosedPeriodRanges;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * أرقام لوحة المبيعات — مشتقّةٌ من دفتر أستاذ العملاء نفسه.
 *
 * لا رقم يُحسب هنا ابتداءً: كلٌّ منها يأتي من `SalesLedger`، فما تعرضه
 * اللوحة وما يعرضه أي تقرير مبيعات شيءٌ واحد بالبناء لا بالمصادفة.
 *
 * **الفرع في المبيعات من العميل:** انتماءُ العميل التنظيمي في
 * `clients.tree_node_id` (وإلا فرعُه المحاسبي `clients.branch_id` لمن
 * سبقوا العمود) — وهو ما يراعيه `SalesLedger::matchClientNodes()`، لا
 * `clients.branch_id` وحده الذي صار فرعاً محاسبياً مشتقّاً لا موقعاً في
 * الشجرة.
 */
class SalesDashboardMetrics
{
    /** ترميز `invoices.payment_status` كما تكتبه `Invoice::updatePaymentStatus()` */
    public const STATUS_PAID = 1;

    public const STATUS_PARTIALLY_PAID = 2;

    public const STATUS_UNPAID = 3;

    /** أسماء أنواع مستندات المبيعات — مفاتيح ترجمة */
    protected const DOCUMENT_KEYS = [
        'sale' => 'sales_dashboard.doc.invoice',
        'return' => 'sales_dashboard.doc.return',
        'credit_note' => 'sales_dashboard.doc.credit_note',
        'collection' => 'sales_dashboard.doc.collection',
        'cancellation' => 'sales_dashboard.doc.cancellation',
        'cancelled_collection' => 'sales_dashboard.doc.cancelled_collection',
        'adjustment' => 'sales_dashboard.doc.adjustment',
    ];

    protected SalesLedger $ledger;

    /** @var array<int, int> معرّفات الفروع المشمولة — فارغةٌ تعني كل الفروع */
    protected array $branchIds;

    /**
     * @param  int|array<int, int>|null  $branchId
     */
    public function __construct(int|array|null $branchId = null)
    {
        $this->branchIds = collect(is_array($branchId) ? $branchId : [$branchId])
            ->reject(fn ($id) => $id === null || $id === '' || $id === 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->ledger = new SalesLedger(null, null, $this->branchIds);
    }

    /**
     * @param  int|array<int, int>|null  $branchId
     */
    public static function forBranch(int|array|null $branchId): self
    {
        return new self($branchId);
    }

    /**
     * مجاميع الفترة على مستوى المنشأة — حاصلُ جمع مجاميع العملاء.
     *
     * @return array<string, float|int>
     */
    public function totals(ReportPeriod $period): array
    {
        $totals = SalesLedger::emptyTotals();

        foreach ($this->ledger->periodTotals($period) as $clientTotals) {
            foreach ($totals as $key => $value) {
                if (isset($clientTotals[$key]) && is_numeric($clientTotals[$key])) {
                    $totals[$key] += $clientTotals[$key];
                }
            }
        }

        /*
            «المرتجعات» مستندان لا مستندٌ واحد: مرتجعُ المبيعات `SR`
            والإشعارُ الدائن `CN`. كلاهما يردّ بضاعةً أو قيمةً إلى العميل
            وينقص المبيعات، والفرقُ بينهما إجرائيٌّ لا معنويّ — ولهذا
            يجمعهما `CustomerLedgerService::TYPES_RETURN` في شاشة العملاء
            منذ البداية. وكانت اللوحةُ تعرض `SR` وحده في بطاقة المرتجعات
            وتدسّ `CN` حاشيةً على بطاقةٍ أخرى، فيقرأ الناظرُ نصفَ المرتجع.

            والتفصيلُ باقٍ: `returns` و`credit_notes` كلٌّ على حدة لشريط
            المطابقة ولحاشية البطاقة، و`returns_total` هو ما يُعرَض.
        */
        $totals['returns_total'] = $totals['returns'] + $totals['credit_notes'];
        $totals['returns_total_count'] = $totals['returns_count'] + $totals['credit_notes_count'];

        $totals['net_sales'] = $totals['sales'] - $totals['returns_total'];

        // متوسّط الفاتورة من صافي المبيعات لا إجماليها — بعد طرح المرتجعات
        // والإشعارات الدائنة، على عدد فواتير الفترة نفسِه
        $totals['average_invoice'] = $totals['sales_count'] > 0
            ? $totals['net_sales'] / $totals['sales_count']
            : 0.0;

        // النسبتان على أساسٍ واحد: كلاهما شامل الضريبة
        $totals['return_rate'] = $totals['sales'] > 0
            ? ($totals['returns_total'] / $totals['sales']) * 100
            : 0.0;

        $totals['collection_rate'] = $totals['net_sales'] > 0
            ? ($totals['collections'] / $totals['net_sales']) * 100
            : 0.0;

        return array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals);
    }

    /**
     * الذمم المدينة في نهاية الفترة — مدين موجب، أي «لنا على العميل».
     *
     * @return array{receivable: float, credit: float, clients: int}
     */
    public function receivables(ReportPeriod $period): array
    {
        $balances = $this->ledger->balancesAsOf($period->to);

        $receivable = 0.0;   // لنا على العميل
        $credit = 0.0;       // دفع زيادةً — له علينا
        $clients = 0;
        $creditClients = 0;  // عددُهم — يُعرض مع المبلغ كي لا يُقرأ رقماً مجهولَ المصدر

        foreach ($balances as $balance) {
            if ($balance > 0.009) {
                $receivable += $balance;
                $clients++;
            } elseif ($balance < -0.009) {
                $credit += abs($balance);
                $creditClients++;
            }
        }

        return [
            'receivable' => round($receivable, 2),
            'credit' => round($credit, 2),
            'clients' => $clients,
            'credit_clients' => $creditClients,
        ];
    }

    /**
     * @return array{labels: array<int, string>, sales: array<int, float>, collections: array<int, float>}
     */
    public function trend(ReportPeriod $period): array
    {
        return $this->ledger->trend($period);
    }

    /**
     * الفترة السابقة المكافئة — تقويميةٌ للمفاتيح المعروفة، ومزاحةٌ بطولها
     * لما عداها. مقارنة «الشهر الحالي» تقع على الشهر الماضي كاملاً.
     */
    public function previousPeriod(ReportPeriod $period): ReportPeriod
    {
        $from = $period->from->copy();

        $shifted = match ($period->key) {
            'today', 'yesterday' => [$from->copy()->subDay()->startOfDay(), $from->copy()->subDay()->endOfDay()],
            'week' => [$from->copy()->subWeek()->startOfWeek(), $from->copy()->subWeek()->endOfWeek()],
            'month' => [$from->copy()->subMonthNoOverflow()->startOfMonth(), $from->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$from->copy()->subQuarterNoOverflow()->startOfQuarter(), $from->copy()->subQuarterNoOverflow()->endOfQuarter()],
            'year' => [$from->copy()->subYearNoOverflow()->startOfYear(), $from->copy()->subYearNoOverflow()->endOfYear()],
            default => $this->shiftedByLength($period),
        };

        return new ReportPeriod($period->key.'_previous', $shifted[0], $shifted[1], __('sales_dashboard.filters.previous_period'));
    }

    /**
     * إزاحةٌ بطول الفترة نفسها — للفترات غير التقويمية.
     *
     * `diffInDays` يعيد كسراً لأن النهاية آخر لحظةٍ من يومها، فيُقتطع.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function shiftedByLength(ReportPeriod $period): array
    {
        $days = (int) $period->from->copy()->startOfDay()
            ->diffInDays($period->to->copy()->startOfDay()) + 1;

        return [
            $period->from->copy()->subDays($days)->startOfDay(),
            $period->from->copy()->subDay()->endOfDay(),
        ];
    }

    /**
     * أعلى العملاء بصافي المبيعات — مع رصيد كلٍّ منهم.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topClients(ReportPeriod $period, int $limit = 8): Collection
    {
        $totals = $this->ledger->periodTotals($period);

        if ($totals === []) {
            return collect();
        }

        $balances = $this->ledger->balancesAsOf($period->to);
        $names = $this->clientNames();

        return collect($totals)
            ->map(function ($row, $clientId) use ($names, $balances) {
                $net = $row['sales'] - $row['returns'] - $row['credit_notes'];

                return [
                    'client_id' => (int) $clientId,
                    'name' => $names[(int) $clientId] ?? '—',
                    'sales' => round($row['sales'], 2),
                    'returns' => round($row['returns'], 2),
                    'credit_notes' => round($row['credit_notes'], 2),
                    // المعروض في العمود: المرتجع والإشعار معاً
                    'returns_total' => round($row['returns'] + $row['credit_notes'], 2),
                    'net_sales' => round($net, 2),
                    'collections' => round($row['collections'], 2),
                    'invoices' => (int) $row['sales_count'],
                    'balance' => round($balances[(int) $clientId] ?? 0, 2),
                ];
            })
            ->filter(fn ($row) => abs($row['net_sales']) > 0.009)
            ->sortByDesc('net_sales')
            ->take($limit)
            ->values();
    }

    /**
     * أعلى العملاء مديونيةً في نهاية الفترة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topDebtors(ReportPeriod $period, int $limit = 8): Collection
    {
        $names = $this->clientNames();

        return collect($this->ledger->balancesAsOf($period->to))
            ->filter(fn ($balance) => $balance > 0.009)
            ->map(fn ($balance, $clientId) => [
                'client_id' => (int) $clientId,
                'name' => $names[(int) $clientId] ?? '—',
                'balance' => round($balance, 2),
            ])
            ->sortByDesc('balance')
            ->take($limit)
            ->values();
    }

    /**
     * حركة كل عقدة فرعٍ على حدة — أساسُ التجميع الهرمي.
     *
     * صفٌّ لكل فرعٍ بحركته **المباشرة** وحدها (بلا نسله)، ومفتاح `null`
     * لما لا فرع له. `branchBreakdown()` هي التي تطويه على الشجرة.
     *
     * @return array<int|string, array<string, float|int>>
     */
    protected function rawByBranch(ReportPeriod $period): array
    {
        $sale = $this->quoted(SalesLedger::SALE_TYPES);
        $return = $this->quoted(SalesLedger::RETURN_TYPES);
        $creditNote = $this->quoted(SalesLedger::CREDIT_NOTE_TYPES);
        $collection = $this->quoted(SalesLedger::COLLECTION_TYPES);
        $refund = $this->quoted(array_merge(SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES));
        $cancelled = SalesLedger::cancellationSql('je');

        /* عقدةُ العميل التنظيمية — لا `je.branch_id`: ذاك فرعٌ محاسبيٌّ
           حقيقيٌّ دائماً (لا يكون مجموعةً ولا منطقةً ولا منفذاً)، فتجميعُ
           الحركة عليه يطمس أيَّ عقدةٍ تحت الفرع في الشجرة — وهو بالضبط ما
           كان يُفرغ بطاقة «الفروع تحت :name» من محتواها عند النزول إلى
           مجموعةٍ أو منطقة: حركتُها موجودةٌ لكنها مجمَّعةٌ تحت فرعها لا
           تحتها هي. */
        $nodeExpr = SalesLedger::clientNodeExpr('c');

        $rows = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->leftJoin('clients as c', 'c.id', '=', 'a.client_id')
            ->whereNotNull('a.client_id')
            ->where('je.status', 1)
            ->tap(fn ($q) => SalesLedger::excludeWalletAccounts($q, 'a'))
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->groupBy(DB::raw($nodeExpr))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->select([
                DB::raw("$nodeExpr as branch_id"),
                DB::raw("SUM(CASE WHEN je.reference_type IN ($sale) THEN jed.debit - jed.credit ELSE 0 END) as sales"),
                // الطرفُ الدائن وحده — كما في `SalesLedger::periodTotals()`
                DB::raw("SUM(CASE WHEN je.reference_type IN ($return) THEN jed.credit ELSE 0 END) as returns"),
                DB::raw("SUM(CASE WHEN je.reference_type IN ($creditNote) THEN jed.credit ELSE 0 END) as credit_notes"),
                // المحصَّل صافٍ مما رُدّ نقداً للعميل (مدينُ المرتجع والإشعار) — كما في `SalesLedger::periodTotals()`
                DB::raw("SUM(CASE WHEN je.reference_type IN ($collection) AND NOT $cancelled THEN jed.credit - jed.debit WHEN je.reference_type IN ($refund) THEN -jed.debit ELSE 0 END) as collections"),
                DB::raw("COUNT(DISTINCT CASE WHEN je.reference_type IN ($sale) AND jed.debit > 0 THEN je.id END) as invoices"),
            ])
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $key = $row->branch_id === null ? 'none' : (int) $row->branch_id;

            $totals[$key] = [
                'sales' => (float) $row->sales,
                'returns' => (float) $row->returns,
                'credit_notes' => (float) $row->credit_notes,
                'collections' => (float) $row->collections,
                'invoices' => (int) $row->invoices,
            ];
        }

        return $totals;
    }

    /**
     * مقارنة مستوىً واحد من الشجرة — أبناءُ العقدة المختارة، كلٌّ مطويّاً
     * على نسله.
     *
     * **لماذا مستوىً واحداً؟** لأن سرد كل النسل مسطَّحاً يضع الأب وأبناءه
     * في صفوفٍ متجاورة، فيُقرأ المبلغ مرّتين ولا يصحّ جمع العمود. وهذا ما
     * كانت تفعله `branchComparison()` قبلها: «فرع الرياض 77,820» ثم
     * «نقطة بيع العليا 167,440» وهي ابنته — رقمان لا يُقارَنان.
     *
     * فالصفوف هنا إخوةٌ في مستوىً واحد:
     *
     *     لا عقدة مختارة  ← الجذور، كلٌّ بنسله + «غير محدّد»
     *     عقدة مختارة     ← أبناؤها المباشرون، كلٌّ بنسله
     *                       + صفٌّ لحركتها المباشرة إن وُجدت
     *
     * @param  int|null  $parentId  العقدة المختارة — أو `null` للجذور
     * @return Collection<int, array<string, mixed>>
     */
    public function branchBreakdown(ReportPeriod $period, ?int $parentId = null): Collection
    {
        $raw = $this->rawByBranch($period);

        $children = Branch::where('parent_id', $parentId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // بلا عقدةٍ مختارة: الجذور. وإلا: أبناء العقدة المباشرون.
        $rows = collect();

        foreach ($children as $child) {
            $subtree = Branch::find($child->id)?->descendantBranchIds()->push($child->id)
                ->map(fn ($id) => (int) $id)->unique()->values()->all() ?? [$child->id];

            $rows->push($this->foldSubtree($raw, $subtree, $child->name, (int) $child->id, false));
        }

        if ($parentId !== null) {
            // حركة العقدة نفسها لا تُنسب إلى أيٍّ من أبنائها
            $own = $this->foldSubtree($raw, [$parentId], __('sales_dashboard.common.direct_activity'), $parentId, true);

            if ($own['sales'] != 0.0 || $own['collections'] != 0.0) {
                $rows->push($own);
            }
        } elseif (isset($raw['none'])) {
            $rows->push($this->foldSubtree($raw, ['none'], __('sales_dashboard.common.unassigned'), null, false));
        }

        return $rows
            ->filter(fn ($row) => $row['sales'] != 0.0 || $row['collections'] != 0.0)
            ->sortByDesc('net_sales')
            ->values();
    }

    /**
     * الشجرة كاملةً — كلُّ أبٍ بمجموعه شاملاً نسلَه، وأبناؤه تحته.
     *
     * `branchBreakdown()` مستوىً واحد لا يُجمع عمودُه مرّتين؛ وهذه تعرض
     * الآباءَ والأبناءَ معاً لمن يريد أن يرى أين تقع الحركة في العمق دون أن
     * ينزل مستوىً مستوىً. فالصفوفُ بترتيب الشجرة (أبٌ ثم أبناؤه) ولكلٍّ عمقُه،
     * ورقمُ الأب مجموعُه **شاملاً** ما تحته — فلا يُجمع العمود، ويُقرأ كلُّ
     * صفٍّ وحده. وحركةُ العقدة نفسِها (`own_*`) منفصلةٌ لمن له أبناء.
     *
     * المحورُ `rawByBranch()` نفسُه — عقدةُ العميل — فمجموعُ الجذور هنا يساوي
     * بطاقاتِ المؤشّرات فوقه. والعقدةُ التي لا حركة في شجرتها لا تظهر.
     *
     * @param  int|null  $rootId  العقدة المختارة — أو `null` لكل الجذور
     * @return Collection<int, array<string, mixed>>
     */
    public function branchTree(ReportPeriod $period, ?int $rootId = null): Collection
    {
        $raw = $this->rawByBranch($period);

        $nodes = Branch::withoutGlobalScopes()
            ->get(['id', 'name', 'parent_id', 'node_type', 'is_territory'])
            ->keyBy('id');
        $children = $nodes->groupBy(fn ($node) => (int) ($node->parent_id ?? 0));

        $figures = fn ($row) => [
            'sales' => (float) ($row['sales'] ?? 0),
            'returns' => (float) ($row['returns'] ?? 0),
            'credit_notes' => (float) ($row['credit_notes'] ?? 0),
            'collections' => (float) ($row['collections'] ?? 0),
            'invoices' => (int) ($row['invoices'] ?? 0),
        ];
        $active = fn (array $f) => abs($f['sales']) > 0.009 || abs($f['collections']) > 0.009;

        // مجموعُ كل عقدةٍ شاملاً نسلَها — محفوظاً فلا تُحسب عقدةٌ مرّتين،
        // ومحروساً من حلقةٍ في `parent_id` لو وُجدت
        $subtotals = [];
        $sum = function (int $id, array $seen = []) use (&$sum, &$subtotals, $raw, $children, $figures) {
            if (isset($subtotals[$id])) {
                return $subtotals[$id];
            }

            $total = $figures($raw[$id] ?? null);

            if (isset($seen[$id])) {
                return $total;
            }
            $seen[$id] = true;

            foreach ($children->get($id, collect()) as $child) {
                foreach ($sum((int) $child->id, $seen) as $key => $value) {
                    $total[$key] += $value;
                }
            }

            return $subtotals[$id] = $total;
        };

        $rows = collect();
        $emit = function (int $id, int $depth, ?int $parentId, array $seen = []) use (&$emit, &$rows, $nodes, $children, $sum, $raw, $figures, $active) {
            if (isset($seen[$id]) || ! isset($nodes[$id])) {
                return;
            }
            $seen[$id] = true;

            $total = $sum($id);
            if (! $active($total)) {
                return;
            }

            $kids = $children->get($id, collect())
                ->filter(fn ($child) => $active($sum((int) $child->id)))
                ->sortByDesc(fn ($child) => $sum((int) $child->id)['sales'] - $sum((int) $child->id)['returns'] - $sum((int) $child->id)['credit_notes']);

            $own = $figures($raw[$id] ?? null);
            $node = $nodes[$id];

            $rows->push([
                'branch_id' => $id,
                'parent_id' => $parentId,
                'depth' => $depth,
                'name' => $node->name,
                // نوعُ العرض لا المخزَّن: المناطق والمنافذ القديمة مخزَّنةٌ «مجموعة»
                'type' => Branch::nodeTypeMeta(Branch::displayNodeType($node, $children->get($id, collect())))['label'] ?? '',
                'has_children' => $kids->isNotEmpty(),
                'invoices' => $total['invoices'],
                'sales' => round($total['sales'], 2),
                'returns_total' => round($total['returns'] + $total['credit_notes'], 2),
                'net_sales' => round($total['sales'] - $total['returns'] - $total['credit_notes'], 2),
                'collections' => round($total['collections'], 2),
                'own_net' => round($own['sales'] - $own['returns'] - $own['credit_notes'], 2),
                'own_collections' => round($own['collections'], 2),
            ]);

            foreach ($kids as $kid) {
                $emit((int) $kid->id, $depth + 1, $id, $seen);
            }
        };

        $roots = $rootId !== null
            ? collect([$rootId])
            : $nodes->filter(fn ($node) => $node->parent_id === null || ! isset($nodes[$node->parent_id]))
                ->sortByDesc(fn ($node) => $sum((int) $node->id)['sales'])
                ->keys();

        foreach ($roots as $root) {
            $emit((int) $root, 0, null);
        }

        // ما لا عقدةَ لعميله — جذرٌ مستقلّ، يظهر فقط في عرض المنشأة كلّها
        if ($rootId === null && isset($raw['none']) && $active($figures($raw['none']))) {
            $none = $figures($raw['none']);
            $rows->push([
                'branch_id' => null, 'parent_id' => null, 'depth' => 0,
                'name' => __('sales_dashboard.common.unassigned'), 'type' => '', 'has_children' => false,
                'invoices' => $none['invoices'],
                'sales' => round($none['sales'], 2),
                'returns_total' => round($none['returns'] + $none['credit_notes'], 2),
                'net_sales' => round($none['sales'] - $none['returns'] - $none['credit_notes'], 2),
                'collections' => round($none['collections'], 2),
                'own_net' => 0.0, 'own_collections' => 0.0,
            ]);
        }

        return $rows->values();
    }

    /**
     * طيُّ حركة شجرةٍ فرعية في صفٍّ واحد.
     *
     * @param  array<int|string, array<string, float|int>>  $raw
     * @param  array<int, int|string>  $ids
     * @return array<string, mixed>
     */
    protected function foldSubtree(array $raw, array $ids, string $name, ?int $branchId, bool $isSelf): array
    {
        $sales = $returns = $creditNotes = $collections = 0.0;
        $invoices = 0;

        foreach ($ids as $id) {
            if (! isset($raw[$id])) {
                continue;
            }

            $sales += $raw[$id]['sales'];
            $returns += $raw[$id]['returns'];
            $creditNotes += $raw[$id]['credit_notes'];
            $collections += $raw[$id]['collections'];
            $invoices += $raw[$id]['invoices'];
        }

        return [
            'branch_id' => $branchId,
            'name' => $name,
            'is_self' => $isSelf,
            'nodes' => count($ids),
            'sales' => round($sales, 2),
            'returns' => round($returns, 2),
            'credit_notes' => round($creditNotes, 2),
            'returns_total' => round($returns + $creditNotes, 2),
            'net_sales' => round($sales - $returns - $creditNotes, 2),
            'collections' => round($collections, 2),
            'invoices' => $invoices,
        ];
    }

    /**
     * أعمدةُ ترتيب المندوبين — ما يُحسب قبل الإثراء (المصروفاتُ والأهدافُ
     * تُجلب للمعروضين وحدهم بعد الترتيب، فلا يُرتَّب بهما).
     */
    public const REP_SORTS = ['collections', 'sales', 'net_sales', 'returns_total', 'invoices', 'visits'];

    /**
     * أداء المندوبين — كلُّ ما يخصّ الموظّف في صفٍّ واحد.
     *
     *     المبيعات والمرتجعات والتحصيل  ← الدفتر، ومنشئُ القيد هو الموظّف
     *     المصروفات                    ← سنداتُ الصرف التي أنشأها، غيرُ المعكوسة
     *     الزيارات                     ← `visits` المنفَّذة + خطُّ السير حتى اليوم
     *     الهدف الشهري                 ← `TargetCalculationService` نفسُها
     *
     * لا يظهر موظّفٌ إلا بعمليةٍ على عملاء النطاق: قيدٌ، أو زيارةٌ يدوية، أو
     * خطُّ سير. الزيارةُ التلقائيةُ بالموقع وحدها لا تُظهره، ولا المصروفُ وحده
     * (ظهر «صادق الصومعة» من الرياض في لوحة منطقة أبها بـ16 زيارة GPS لعميلٍ
     * واحد ومصروفاتِه كلِّها خارجها). والمصروفاتُ بختم السند على العنصر
     * المختار أو ما تحته، والهدفُ وحده للموظّف كلِّه — كما في شاشة الأهداف.
     *
     * الترتيبُ بأيّ عمودٍ من {@see self::REP_SORTS} — اللوحةُ بالمبيعات وأعلى
     * ٢٥، وشاشةُ «أداء المندوبين» بالتحصيل والكلُّ (`$limit = null`).
     * والمحصَّل صافٍ مما ردّه المندوبُ نقداً للعميل مع مرتجعٍ أو إشعار.
     *
     * @param  int|null  $limit  `null` = كلُّ المندوبين
     * @return Collection<int, array<string, mixed>>
     */
    public function salesByEmployee(ReportPeriod $period, ?int $limit = 25, string $sortBy = 'sales'): Collection
    {
        if (! in_array($sortBy, self::REP_SORTS, true)) {
            $sortBy = 'sales';
        }

        $visits = $this->visitsByEmployee($period);

        $rows = $this->ledgerByEmployee($period)->keyBy(fn ($row) => $row['employee_id'] ?? 0);

        foreach ($visits as $userId => $figures) {
            // الزيارةُ التلقائيةُ بالموقع وحدها ليست عمليةً على الفرع: مندوبٌ من
            // منطقةٍ أخرى مرّ بجوار عميلٍ فسُجِّلت له — فلا تُظهره وحدها
            if (! $rows->has($userId) && ($figures['visits'] > 0 || $figures['planned'] > 0)) {
                $rows->put($userId, [
                    'employee_id' => $userId, 'name' => null, 'invoices' => 0,
                    'sales' => 0.0, 'returns_total' => 0.0, 'net_sales' => 0.0, 'collections' => 0.0,
                    'refunds' => 0.0, 'refunds_count' => 0,
                    'cancelled_receipts' => 0.0, 'cancelled_count' => 0,
                ]);
            }
        }

        $rows = $rows
            ->map(fn ($row, $key) => $row + ($visits[$key] ?? self::emptyVisits()))
            ->filter(fn ($row) => $row['sales'] != 0.0 || $row['collections'] != 0.0 || $row['returns_total'] != 0.0
                || $row['cancelled_count'] > 0 || $row['visits'] > 0 || $row['planned'] > 0)
            // العمودُ المختار أوّلاً، ثم المبيعاتُ فالتحصيلُ فالزيارات لفضّ التعادل
            ->sortByDesc(fn ($row) => [$row[$sortBy], $row['sales'], $row['collections'], $row['visits']])
            ->when($limit !== null, fn ($sorted) => $sorted->take($limit));

        // الإثراءُ للصفوف المعروضة وحدها — الهدفُ استعلاماتٌ لكل موظّف
        $userIds = $rows->pluck('employee_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $names = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');
        $expenses = $this->expensesByEmployee($userIds, $period);
        $targets = $this->monthlyTargets($userIds, $this->targetMonth($period));

        return $rows
            ->map(function ($row) use ($names, $expenses, $targets) {
                $id = $row['employee_id'];

                return array_merge($row, [
                    'name' => $row['name'] ?? ($id ? ($names[$id] ?? null) : null) ?? __('sales_dashboard.common.unassigned'),
                    'expenses' => $id ? ($expenses[$id]['amount'] ?? 0.0) : 0.0,
                    'expense_documents' => $id ? ($expenses[$id]['documents'] ?? 0) : 0,
                    'sales_target' => $id ? ($targets[$id]['sales'] ?? null) : null,
                    'collections_target' => $id ? ($targets[$id]['collections'] ?? null) : null,
                ]);
            })
            ->values();
    }

    /**
     * الشهرُ الذي يُقاس عليه الهدف — شهرُ نهاية الفترة، أو الشهرُ الجاري إن
     * امتدّت الفترةُ إلى ما بعد اليوم (فترةُ «السنة» تنتهي في ديسمبر).
     */
    public function targetMonth(ReportPeriod $period): Carbon
    {
        $anchor = $period->to->lessThan(now()) ? $period->to->copy() : now();

        return $anchor->startOfMonth();
    }

    /**
     * حركةُ الدفتر لكل منشئ قيد — المرتجعُ والإشعارُ طرفُهما الدائن وحده،
     * كما في `totals()`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function ledgerByEmployee(ReportPeriod $period): Collection
    {
        $sale = $this->quoted(SalesLedger::SALE_TYPES);
        $return = $this->quoted(array_merge(SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES));
        $collection = $this->quoted(SalesLedger::COLLECTION_TYPES);
        $cancellation = $this->quoted(SalesLedger::CANCELLATION_TYPES);
        $cancelled = SalesLedger::cancellationSql('je');

        return DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->leftJoin('clients as c', 'c.id', '=', 'a.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by_employee')
            ->whereNotNull('a.client_id')
            ->where('je.status', 1)
            ->tap(fn ($q) => SalesLedger::excludeWalletAccounts($q, 'a'))
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            // الفرعُ محورٌ واحد: عقدةُ العميل — كما في `SalesLedger::baseQuery()`
            ->when($this->branchIds !== [], fn ($q) => $q->where(
                fn ($w) => SalesLedger::matchClientNodes($w, 'c', $this->branchIds)
            ))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy('je.created_by_employee', 'u.name')
            ->select([
                'je.created_by_employee as employee_id',
                'u.name as employee_name',
                DB::raw("SUM(CASE WHEN je.reference_type IN ($sale) THEN jed.debit - jed.credit ELSE 0 END) as sales"),
                DB::raw("SUM(CASE WHEN je.reference_type IN ($return) THEN jed.credit ELSE 0 END) as returns_total"),
                // المحصَّل صافٍ مما ردّه للعميل نقداً — والمردودُ وحده بجانبه للعرض
                DB::raw("SUM(CASE WHEN je.reference_type IN ($collection) AND NOT $cancelled THEN jed.credit - jed.debit WHEN je.reference_type IN ($return) THEN -jed.debit ELSE 0 END) as collections"),
                DB::raw("SUM(CASE WHEN je.reference_type IN ($return) THEN jed.debit ELSE 0 END) as refunds"),
                DB::raw("COUNT(DISTINCT CASE WHEN je.reference_type IN ($return) AND jed.debit > 0 THEN je.id END) as refunds_count"),
                // من ألغى سندَ قبض — العكسيُّ بمنشئه، لا سالباً في تحصيله
                DB::raw("SUM(CASE WHEN je.reference_type IN ($cancellation) THEN jed.debit - jed.credit ELSE 0 END) as cancelled_receipts"),
                DB::raw("COUNT(DISTINCT CASE WHEN je.reference_type IN ($cancellation) THEN je.id END) as cancelled_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN je.reference_type IN ($sale) THEN je.id END) as invoices"),
            ])
            ->get()
            ->map(function ($row) {
                $sales = (float) $row->sales;
                $returns = (float) $row->returns_total;

                return [
                    'employee_id' => $row->employee_id ? (int) $row->employee_id : null,
                    'name' => $row->employee_name ?: null,
                    'invoices' => (int) $row->invoices,
                    'sales' => round($sales, 2),
                    'returns_total' => round($returns, 2),
                    'net_sales' => round($sales - $returns, 2),
                    'collections' => round((float) $row->collections, 2),
                    'refunds' => round((float) $row->refunds, 2),
                    'refunds_count' => (int) $row->refunds_count,
                    'cancelled_receipts' => round((float) $row->cancelled_receipts, 2),
                    'cancelled_count' => (int) $row->cancelled_count,
                ];
            });
    }

    /** صفُّ زياراتٍ فارغ — لمن له حركةٌ في الدفتر ولا زيارة له */
    protected static function emptyVisits(): array
    {
        return [
            'visits' => 0, 'visit_success' => 0, 'visit_negative' => 0, 'visit_no_result' => 0, 'visited_clients' => 0,
            'planned' => 0, 'plan_completed' => 0, 'plan_in_progress' => 0, 'plan_missed' => 0, 'plan_justified' => 0, 'plan_pending' => 0,
        ];
    }

    /**
     * خاناتُ «الزيارات» — مفاتيحُ `ClientDashboardService::visitRows()` مجمَّعةً.
     * ناجحة + سلبية + بلا نتيجة = كلُّ الزيارات، كبطاقة «نتائج الزيارات» في
     * لوحة العملاء (السلبيةُ تضمّ «سأعود لاحقاً» كـ`visitOutcomeCounts()`).
     */
    public const VISIT_RESULT_GROUPS = [
        'success' => ['success'],
        'negative' => ['failed', 'return_later'],
        'no_result' => ['in_progress', 'open', 'cancelled', 'no_outcome'],
    ];

    /**
     * خاناتُ «خط السير» — مفاتيحُ `ClientDashboardService::planRows()` مجمَّعةً
     * كـ`visitStateRows()` هناك: مكتملة + جارية + فائتة + لم يُزَر = المخطَّط.
     * الملغاةُ خارجها، والمبرَّرةُ داخل «فائتة» ويُعدّ عددُها.
     */
    public const PLAN_STATE_GROUPS = [
        'completed' => ['visited'],
        'in_progress' => ['in_progress'],
        'missed' => ['not_visited', 'justified'],
        'pending' => ['upcoming'],
    ];

    /** الخانةُ التي يقع فيها مفتاح — أو `null` لما هو خارجها (الملغاةُ من خطّ السير) */
    public static function groupOf(array $groups, string $key): ?string
    {
        foreach ($groups as $group => $keys) {
            if (in_array($key, $keys, true)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * صفوفُ الزيارات وخطّ السير لعملاء النطاق — من خدمة لوحة العملاء نفسِها.
     *
     * لا عدَّ موازياً هنا: كانت الخانة تعدّ كلَّ `visits` (والتسجيلُ التلقائي
     * بالموقع 1,950 من 1,952 عند مندوبٍ واحد) وخطَّ السير بعمود `visit_status`
     * وحده، فلا تتّفق مع لوحة العملاء ولا تُفهم («خط السير 858 من 1323»).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    public static function visitAndPlanRows(array $branchIds, ReportPeriod $period): array
    {
        $clientIds = SalesLedger::clientIdsInNodes($branchIds);

        if ($clientIds === []) {
            return [[], []];
        }

        $service = app(ClientDashboardService::class);
        $from = $period->from->copy()->startOfDay();
        $to = $period->to->copy()->endOfDay();

        return [$service->visitRows($clientIds, $from, $to), $service->planRows($clientIds, $from, $to)];
    }

    /**
     * الزيارات لكل موظّف — نتائجُها وحالاتُ خطّ سيره، بقاعدة لوحة العملاء.
     *
     * @return array<int, array<string, int>>
     */
    protected function visitsByEmployee(ReportPeriod $period): array
    {
        [, $planRows] = self::visitAndPlanRows($this->branchIds, $period);

        $result = [];
        $clients = [];

        /* «الزيارات» = المكتملةُ من خطّ السير بنتيجتها (`planRows()['outcome']`):
           ناجحة + سلبية = مكتملة، كبطاقة لوحة العملاء. كانت تُعدّ من جدول
           `visits` وحده فلا تساوي «مكتملة» ولا يُعرف كم زار المندوبُ فعلاً. */
        foreach ($planRows as $plan) {
            $userId = (int) ($plan['employee_id'] ?? 0);
            $group = self::groupOf(self::PLAN_STATE_GROUPS, $plan['key']);
            if (! $userId || $group === null) {
                continue;
            }

            $result[$userId] ??= self::emptyVisits();
            $result[$userId]['planned']++;
            $result[$userId]['plan_'.$group]++;
            $result[$userId]['plan_justified'] += $plan['key'] === 'justified' ? 1 : 0;

            if (($plan['outcome'] ?? null) !== null) {
                $result[$userId]['visits']++;
                $result[$userId]['visit_'.$plan['outcome']]++;
                $clients[$userId][$plan['client_id']] = true;
            }
        }

        foreach ($clients as $userId => $set) {
            $result[$userId]['visited_clients'] = count($set);
        }

        return $result;
    }

    /**
     * مصروفاتُ الموظّف — سنداتُ الصرف التي أنشأها في الفترة، غيرُ المعكوسة
     * (`Expense::scopeNotReversed()`: المعكوسُ ثمّ المُعاد يُحسب مرّتين).
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{amount: float, documents: int}>
     */
    protected function expensesByEmployee(array $userIds, ReportPeriod $period): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('expenses')
            ->whereIn('created_by', $userIds)
            ->whereNull('reversed_at')
            // السندُ لا عميلَ له، فالفرعُ ختمُه — العنصرُ المختار أو ما تحته
            ->when($this->branchIds !== [], fn ($q) => $q->whereIn('branch_id', $this->branchIds))
            ->whereBetween('date', [$period->from->copy()->startOfDay()->toDateTimeString(), $period->to->copy()->endOfDay()->toDateTimeString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'date'))
            ->groupBy('created_by')
            ->select(['created_by', DB::raw('SUM(amount) as amount'), DB::raw('COUNT(*) as documents')])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->created_by => [
                'amount' => round((float) $row->amount, 2),
                'documents' => (int) $row->documents,
            ]])
            ->all();
    }

    /**
     * نسبةُ الهدف الشهري — بالخدمة التي تحتسب بها شاشةُ الأهداف وتسويتُها،
     * وبطريقة الاحتساب المضبوطة في «إعدادات الأهداف»، فالنسبةُ هنا هي
     * نسبةُ التقرير هناك.
     *
     * **أيُّ هدف؟** الموظّفُ قد يُسنَد إليه أكثرُ من هدفٍ شهريٍّ نشط (مستوياتٌ
     * متدرّجة)، وجمعُها يصنع هدفاً لم يضعه أحد. فالمعروضُ أكبرُها — الهدفُ
     * الكامل. ومن لا هدفَ له في وحدة الأهداف يُقرأ له `employee_targets`
     * للمبيعات، وهو ما تعرضه شاشةُ أداء الموظفين. ومن لا هدفَ له أصلاً:
     * `null` لا صفر، فلا يُعرض مقصّراً في هدفٍ لم يُسنَد إليه.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{sales: array|null, collections: array|null}>
     */
    protected function monthlyTargets(array $userIds, Carbon $month): array
    {
        if ($userIds === []) {
            return [];
        }

        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $service = app(TargetCalculationService::class);

        $salesTargets = $this->largestMonthlyTargets('sales_target_achievements', 'sales_targets', 'sales_target_id', $userIds);
        $collectionsTargets = $this->largestMonthlyTargets('collections_target_achievements', 'collections_targets', 'collections_target_id', $userIds);
        $legacySales = DB::table('employee_targets')
            ->whereIn('user_id', $userIds)
            ->where('monthly_target', '>', 0)
            ->pluck('monthly_target', 'user_id');

        $salesMethod = TargetSetting::for(TargetSetting::TYPE_SALES)->calculation_method ?: 'net_sales';
        $collectionsMethod = TargetSetting::for(TargetSetting::TYPE_COLLECTIONS)->calculation_method ?: 'total_collected';

        $progress = fn (float $target, float $achieved) => [
            'target' => round($target, 2),
            'achieved' => round($achieved, 2),
            'percent' => round(($achieved / $target) * 100, 1),
        ];

        $result = [];

        foreach ($userIds as $userId) {
            $salesTarget = (float) ($salesTargets[$userId] ?? $legacySales[$userId] ?? 0);
            $collectionsTarget = (float) ($collectionsTargets[$userId] ?? 0);

            $result[$userId] = [
                'sales' => $salesTarget > 0
                    ? $progress($salesTarget, (float) $service->getEmployeeNetSales($userId, $from, $to, $salesMethod))
                    : null,
                'collections' => $collectionsTarget > 0
                    ? $progress($collectionsTarget, (float) $service->getEmployeeCollections($userId, $from, $to, $collectionsMethod))
                    : null,
            ];
        }

        return $result;
    }

    /**
     * أكبرُ هدفٍ شهريٍّ نشطٍ لكل موظّف — الجدولان توأمان (`TargetScreen`).
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, float>
     */
    protected function largestMonthlyTargets(string $achievements, string $targets, string $foreignKey, array $userIds): Collection
    {
        return DB::table("$achievements as a")
            ->join("$targets as t", 't.id', '=', "a.$foreignKey")
            ->where('t.status', 'active')
            ->where('t.commission_period', 'monthly')
            ->whereIn('a.employee_id', $userIds)
            ->groupBy('a.employee_id')
            ->select(['a.employee_id', DB::raw('MAX(a.target_amount) as target')])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->employee_id => (float) $row->target]);
    }

    /**
     * توزيع فواتير الفترة على حالات السداد.
     *
     * تُقرأ من المستندات لا من القيود: حالة السداد خاصّية فاتورة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function paymentStatusMix(ReportPeriod $period): Collection
    {
        /*
         * `invoices.payment_status` عمودٌ رقمي (tinyint) لا نصّي — على خلاف
         * `purchase_invoices.payment_status` الذي هو enum نصّي. والترميز
         * يُقرأ من `Invoice::updatePaymentStatus()`: ١ مسدَّدة، ٢ جزئياً،
         * ٣ غير مسدَّدة. ومقارنته بنصوصٍ كانت تُرجع أصفاراً صامتة.
         */
        $labels = [
            self::STATUS_PAID => 'sales_dashboard.status.paid',
            self::STATUS_PARTIALLY_PAID => 'sales_dashboard.status.partially_paid',
            self::STATUS_UNPAID => 'sales_dashboard.status.unpaid',
        ];

        $rows = DB::table('invoices as i')
            ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
            ->whereBetween('i.invoice_date', [$period->from->toDateString(), $period->to->toDateString()])
            /* الفرعُ بقاعدة الدفتر نفسِها: عقدةُ العميل وحدها.
               كان الشرطُ `COALESCE(i.branch_id, c.branch_id)` فيغلب ختمُ
               المستند انتماءَ العميل، ثم صار `ختمُ المستند أو عقدةُ عميله`
               فجمع الاثنين — وهو أسوأ: بطاقةُ الفرع تعدّ فواتيرَ عملاءَ
               ليسوا له. والمحورُ الآن واحدٌ في كل أرقام اللوحة. */
            ->when($this->branchIds !== [], fn ($q) => $q->where(
                fn ($w) => SalesLedger::matchClientNodes($w, 'c', $this->branchIds)
            ))
            ->groupBy('i.payment_status')
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'i.invoice_date'))
            ->select([
                'i.payment_status',
                DB::raw('COUNT(*) as documents'),
                DB::raw('SUM(i.grand_total) as amount'),
            ])
            ->get()
            ->keyBy('payment_status');

        return collect($labels)
            ->map(fn ($key, $status) => [
                'key' => (int) $status,
                'label' => __($key),
                'documents' => (int) ($rows[$status]->documents ?? 0),
                'amount' => round((float) ($rows[$status]->amount ?? 0), 2),
            ])
            ->values();
    }

    /**
     * أعلى الأصناف مبيعاً — من بنود الفواتير المرحّلة.
     *
     * القيمة صافية من الضريبة (`invoice_items.total`)، والضريبة تُعرض في
     * المؤشّرات منفصلةً فلا يختلط الأساسان.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topProducts(ReportPeriod $period, int $limit = 8): Collection
    {
        $returned = "i.type = 'returned'";

        /*
            فاتورةُ المرتجع صفٌّ في `invoices` نفسِه (`type = 'returned'`)
            وكميّاتُ بنودها موجبة. كانت تُجمع مع البيع فيزيد الصنفُ على
            المبيعات كلِّها: «عطر 50 مل» 22,320.15 في منطقة الشرقية لأغسطس
            ومبيعاتُها 20,784.15 — والفرقُ مرتجعاتُها 1,536.00 بالضبط.
            فالبيعُ والمرتجعُ عمودان، والصافي فرقُهما.
        */
        $salesRows = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->whereBetween('i.invoice_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->whereRaw('EXISTS (SELECT 1 FROM journal_entries e WHERE e.invoice_id = i.id AND e.status = 1)')
            // الفرعُ بقاعدة الدفتر نفسِها: عقدةُ العميل وحدها
            ->when($this->branchIds !== [], fn ($q) => $q->where(
                fn ($w) => SalesLedger::matchClientNodes($w, 'c', $this->branchIds)
            ))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'i.invoice_date'))
            /* الوحدةُ في التجميع لا الصنفُ وحده: صنفٌ يُباع أحياناً بالكرتون
               وأحياناً بالحبّة كان يجمع الكمّيتين رقماً واحداً بلا معنى —
               ١٠ كراتين + ٥ حبّات = ١٥! فالكمّيةُ هنا لكلّ وحدةٍ على حِدة،
               وتُعرض في الشاشة سطراً لكلّ وحدة، والمبلغُ (مالٌ لا كمّية)
               وحده يُجمع عبر الوحدات للترتيب والإجمالي. */
            ->groupBy('ii.product_id', 'p.name', 'ii.unit_name')
            ->select([
                'ii.product_id',
                DB::raw("COALESCE(p.name, '—') as product_name"),
                DB::raw("NULLIF(ii.unit_name, '') as unit_name"),
                DB::raw("SUM(CASE WHEN $returned THEN 0 ELSE ii.quantity END) as quantity"),
                DB::raw("SUM(CASE WHEN $returned THEN 0 ELSE ii.total END) as amount"),
                DB::raw("SUM(CASE WHEN $returned THEN ii.quantity ELSE 0 END) as returned_quantity"),
                DB::raw("SUM(CASE WHEN $returned THEN ii.total ELSE 0 END) as returned_amount"),
                DB::raw("COUNT(DISTINCT CASE WHEN $returned THEN NULL ELSE i.id END) as documents"),
            ])
            ->get();

        $rows = [];

        foreach ($salesRows as $row) {
            $id = (int) $row->product_id;
            $rows[$id] ??= [
                'product_id' => $id, 'name' => $row->product_name, 'quantity' => 0.0, 'amount' => 0.0,
                'returned_quantity' => 0.0, 'returned_amount' => 0.0, 'documents' => 0, 'units' => [],
            ];

            $rows[$id]['quantity'] += (float) $row->quantity;
            $rows[$id]['amount'] += (float) $row->amount;
            $rows[$id]['returned_quantity'] += (float) $row->returned_quantity;
            $rows[$id]['returned_amount'] += (float) $row->returned_amount;
            $rows[$id]['documents'] += (int) $row->documents;

            $unit = $row->unit_name ?? '—';
            $rows[$id]['units'][$unit] ??= ['unit' => $unit, 'quantity' => 0.0, 'amount' => 0.0, 'returned_quantity' => 0.0];
            $rows[$id]['units'][$unit]['quantity'] += (float) $row->quantity;
            $rows[$id]['units'][$unit]['amount'] += (float) $row->amount;
            $rows[$id]['units'][$unit]['returned_quantity'] += (float) $row->returned_quantity;
        }

        /*
            والإشعارُ الدائن مرتجعٌ أيضاً — بنودُه في `invoice_items` بـ`credit_note_id`
            لا `invoice_id`، فكان الربطُ بالفواتير يُسقطها: بطاقةُ «المرتجعات»
            أعلى اللوحة تجمع المرتجعَ والإشعارَ معاً (136,687 في 2026) بينما
            كانت لوحةُ الأصناف تعرض المرتجعَ وحده (59,140 لصنف العطر مقابل
            132,880 في تقرير الأصناف). المصدرُ نفسُه الذي تقرأ به «أرباح
            المبيعات» و«مبيعات الأصناف» بنودَ الإشعار.
        */
        $creditNotes = DB::table('invoice_items as ii')
            ->join('credit_notifications as cn', 'cn.id', '=', 'ii.credit_note_id')
            ->leftJoin('clients as c', 'c.id', '=', 'cn.client_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->whereBetween('cn.credit_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->whereRaw("EXISTS (SELECT 1 FROM journal_entries cje WHERE cje.reference_type = 'CN' AND cje.reference_number = cn.id AND cje.status = 1)")
            ->when($this->branchIds !== [], fn ($q) => $q->where(
                fn ($w) => SalesLedger::matchClientNodes($w, 'c', $this->branchIds)
            ))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'cn.credit_date'))
            ->groupBy('ii.product_id', 'p.name', 'ii.unit_name')
            ->select([
                'ii.product_id',
                DB::raw("COALESCE(p.name, '—') as product_name"),
                DB::raw("NULLIF(ii.unit_name, '') as unit_name"),
                DB::raw('SUM(ii.quantity) as returned_quantity'),
                DB::raw('SUM(ii.total) as returned_amount'),
            ])
            ->get();

        foreach ($creditNotes as $cn) {
            $id = (int) $cn->product_id;
            $rows[$id] ??= [
                'product_id' => $id, 'name' => $cn->product_name, 'quantity' => 0.0, 'amount' => 0.0,
                'returned_quantity' => 0.0, 'returned_amount' => 0.0, 'documents' => 0, 'units' => [],
            ];
            $rows[$id]['returned_quantity'] += (float) $cn->returned_quantity;
            $rows[$id]['returned_amount'] += (float) $cn->returned_amount;

            $unit = $cn->unit_name ?? '—';
            $rows[$id]['units'][$unit] ??= ['unit' => $unit, 'quantity' => 0.0, 'amount' => 0.0, 'returned_quantity' => 0.0];
            $rows[$id]['units'][$unit]['returned_quantity'] += (float) $cn->returned_quantity;
        }

        return collect($rows)
            ->map(function ($row) {
                // وحدةٌ واحدة لا تُعرض تفصيلاً — نفس ما يظهر في الإجمالي
                $units = collect($row['units'])
                    ->map(fn ($u) => [
                        'unit' => $u['unit'],
                        'quantity' => round($u['quantity'], 2),
                        'returned_quantity' => round($u['returned_quantity'], 2),
                        'net_quantity' => round($u['quantity'] - $u['returned_quantity'], 2),
                        'avg_price' => $u['quantity'] > 0 ? round($u['amount'] / $u['quantity'], 2) : 0.0,
                    ])
                    ->values();

                return [
                    'product_id' => $row['product_id'],
                    'name' => $row['name'],
                    'quantity' => round($row['quantity'], 2),
                    'amount' => round($row['amount'], 2),
                    'returned_quantity' => round($row['returned_quantity'], 2),
                    'returned_amount' => round($row['returned_amount'], 2),
                    'net_quantity' => round($row['quantity'] - $row['returned_quantity'], 2),
                    'net_amount' => round($row['amount'] - $row['returned_amount'], 2),
                    'documents' => $row['documents'],
                    'avg_price' => $row['quantity'] > 0 ? round($row['amount'] / $row['quantity'], 2) : 0.0,
                    // كمّيةُ كلّ وحدةٍ على حِدة — صنفٌ بيع بالكرتون وبالحبّة معاً
                    // يظهر هنا سطرين لا رقماً واحداً مجموعاً بلا معنى
                    'units' => $units,
                    'has_multiple_units' => $units->count() > 1,
                ];
            })
            ->sortByDesc('net_amount')
            ->take($limit)
            ->values();
    }

    /**
     * آخر حركات العملاء — فواتير وتحصيلات ومرتجعات الفترة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentMovements(ReportPeriod $period, int $limit = 8): Collection
    {
        $names = $this->clientNames();

        $rows = $this->ledger->movements($period)
            ->sortByDesc(fn ($row) => [$row->date, $row->entry_id])
            ->take($limit)
            ->map(function ($row) use ($names) {
                $kind = SalesLedger::classify($row->reference_type, (bool) $row->is_cancellation);
                $amount = in_array($kind, ['sale', 'adjustment', 'cancellation'], true)
                    ? (float) $row->debit - (float) $row->credit
                    : (float) $row->credit - (float) $row->debit;

                return [
                    'entry_id' => (int) $row->entry_id,
                    'client_id' => (int) $row->client_id,
                    'reference_type' => (string) $row->reference_type,
                    'invoice_id' => $row->invoice_id ? (int) $row->invoice_id : null,
                    'code' => $row->reference_number ?: '#'.$row->entry_id,
                    'kind' => $kind,
                    'kind_label' => __(self::DOCUMENT_KEYS[$kind] ?? 'sales_dashboard.doc.adjustment'),
                    'date' => (string) $row->date,
                    'amount' => round($amount, 2),
                    'client_name' => $names[(int) $row->client_id] ?? '—',
                    'branch_name' => $row->branch_name ?: __('sales_dashboard.common.unassigned'),
                    'employee_name' => $row->employee_name ?: '—',
                ];
            });

        // رمزُ المستند رابطٌ إلى شاشته — بالربط نفسِه في قوائم التفاصيل
        return SalesDashboardDrillDown::attachDocumentUrls($rows)->values();
    }

    /**
     * أسماء العملاء — الاسم التجاري وإلا الاسم الشخصي.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function clientNames(): Collection
    {
        return DB::table('clients')
            ->get(['id', 'trade_name', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($c) => [
                (int) $c->id => $c->trade_name
                    ?: trim(($c->first_name ?? '').' '.($c->last_name ?? ''))
                    ?: '—',
            ]);
    }

    /** أنواعُ قيدٍ في صيغة قائمة SQL — الثوابت مغلقة فلا مدخل لمستخدم هنا */
    protected function quoted(array $types): string
    {
        return implode(',', array_map(fn ($type) => "'".$type."'", $types));
    }
}
