<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ExchangeReason;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\ProductExchangeItem;
use App\Models\StoreHouse;
use App\Models\User;
use App\Support\AffiliationTree;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Sales\Http\Controllers\Concerns\ScopesSalesVisibility;

/**
 * «تقرير التبديلات» — تبديلُ الأصناف التالفة أو الناقصة للعملاء، بنداً بنداً.
 *
 * ══════════ المصدر ══════════
 *
 * `product_exchanges` (المستند) و`product_exchange_items` (بنودُه). قيمةُ
 * البند = الكمية × سعر الوحدة كما كُتبت في المستند، ومجموعُ بنود المستند
 * = `total_value`. التبديلُ ليس بيعاً: لا طرفَ عميلٍ في قيده (يُخرج السليم
 * ويُدخل التالف بتكلفتهما)، فلا يُقرأ من دفتر العميل كتقارير المبيعات.
 *
 * ══════════ العرضان ══════════
 *
 *   «تفاصيل» — سطرٌ لكلّ بند: الصنفُ والكميةُ والسببُ والمستودعان.
 *   «ملخص»   — سطرٌ لكلّ مجموعة بحسب «التجميع»: مستند، صنف، سبب، عميل،
 *              موظف، فرع، مستودع، يوم، شهر. والأرقامُ من السطور نفسِها،
 *              فمجموعُ الملخّص يساوي مجموعَ التفاصيل لأيّ تجميع.
 *
 * ══════════ النطاق ══════════
 *
 * على **فرع المستند** (`product_exchanges.branch_id`، مجمَّداً وقت الإنشاء)
 * كشاشة التبديلات ولوحتها — لا على عقدة العميل. كان التقرير على عقدة العميل
 * كبقيّة تقارير المبيعات، والفرعان يختلفان في ٢٩٤٤ من ٣٣٣٥ عملية: منطقةُ
 * أبها ٩١٥ عمليةً في الشاشة و٧٩٦ في التقرير، فبدا التقريرُ «لا يجلب صحيحاً».
 *
 * وفرعُ المستند يُكتب على مستوى المنطقة غالباً، فاختيارُ فرعٍ تحتها في
 * «تابع لـ» (فرع الخميس مثلاً) لا يطابق مستنداً بفرعه — فيُطابَق حينها
 * بعقدة العميل، بشرط أن يكون فرعُ المستند فوق العقدة المختارة.
 * {@see constrainToNode()}
 *
 * ونطاقُ الرؤية {@see ScopesSalesVisibility::scopeSalesDocuments()} نفسُه الذي
 * تقرأ به شاشةُ التبديلات — كي لا يرى المشرفُ في التقرير غيرَ ما يراه فيها.
 */
class ExchangesReportController extends Controller
{
    use SalesReportTrait;
    use ScopesSalesVisibility;

    public function __construct()
    {
        $this->middleware('permission:sales.reports.exchanges');
    }

    public function searchClients(Request $request)
    {
        return $this->searchReportClients($request);
    }

    public function index(Request $request)
    {
        $ctx = $this->reportContext($request);
        $lines = $this->buildLines($request, $ctx);

        $groupOptions = $this->groupOptions();
        $groupBy = array_key_exists($request->input('group_by'), $groupOptions) ? $request->input('group_by') : 'document';
        $isSummary = $request->input('view_type', 'details') === 'summary';

        $totals = $this->totalsOf($lines);
        $groups = $isSummary ? $this->groupLines($lines, $groupBy, $totals['value']) : collect();

        if ($request->ajax() || $request->boolean('_ajax')) {
            return response()->json(['success' => true, 'lines' => $lines, 'groups' => $groups, 'totals' => $totals]);
        }

        return view('sales::sals_report.salesRport.Exchanges_Report', $ctx + [
            'lines' => $lines,
            'groups' => $groups,
            'groupBy' => $groupBy,
            'groupOptions' => $groupOptions,
            'isSummary' => $isSummary,
            'totals' => $totals,
            'reasonMix' => $this->reasonMix($lines),
            'pageTitle' => __('sales_reports.exchanges.title'),
            'formAction' => route('salesReports.exchanges'),
        ]);
    }

    // =====================================================================
    //  السياق: المدى، العقدة، القوائم
    // =====================================================================

    protected function reportContext(Request $request): array
    {
        [$fromDate, $toDate] = $this->parseDateRangeRequest($request);

        $user = User::find(auth()->id());
        $userBranchId = $user->branch_id;
        $isMainBranch = (bool) optional(Branch::find($userBranchId))->is_main;

        $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
        $branchIds = $effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null;

        $dropdowns = $this->getFilteredDropdowns($branchIds, withClientList: false);

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'effectiveBranchId' => $effectiveBranchId,
            'branchIds' => $branchIds,
            'users' => $dropdowns['employees'],
            'treeNodes' => $dropdowns['treeNodes'] ?? [],
            'cascadeBranches' => $dropdowns['cascadeBranches'] ?? [],
            'statuses' => $dropdowns['statuses'] ?? collect(),
            'selectedClient' => $request->filled('client_id') ? Client::find($request->input('client_id')) : null,
            'products' => Product::orderBy('name')->get(['id', 'name', 'barcode']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            // سببٌ واحدٌ في القائمة لكلّ اسم — والفلترُ يطابق بالاسم
            'reasons' => ExchangeReason::orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active'])->unique('name')->values(),
            'storehouses' => StoreHouse::orderBy('name')->get(['id', 'name']),
        ];
    }

    /** خياراتُ «التجميع» في الملخّص — المفتاحُ اسمُ حقلٍ في السطر */
    protected function groupOptions(): array
    {
        return [
            'document' => __('sales_reports.exchanges.group.document'),
            'product' => __('sales_reports.exchanges.group.product'),
            'category' => __('sales_reports.exchanges.group.category'),
            'reason' => __('sales_reports.exchanges.group.reason'),
            'client' => __('sales_reports.exchanges.group.client'),
            'employee' => __('sales_reports.exchanges.group.employee'),
            'branch' => __('sales_reports.exchanges.group.branch'),
            'storehouse' => __('sales_reports.exchanges.group.storehouse'),
            'return_storehouse' => __('sales_reports.exchanges.group.return_storehouse'),
            'day' => __('sales_reports.exchanges.group.day'),
            'month' => __('sales_reports.exchanges.group.month'),
        ];
    }

    // =====================================================================
    //  السطور
    // =====================================================================

    protected function buildLines(Request $request, array $ctx): Collection
    {
        $query = ProductExchange::query()
            ->select(['id', 'code', 'client_id', 'branch_id', 'employee_id', 'storehouse_id', 'return_storehouse_id',
                'created_by', 'exchange_date', 'notes', 'total_value', 'outgoing_permit_id', 'incoming_permit_id'])
            ->whereBetween('exchange_date', [$ctx['fromDate']->format('Y-m-d'), $ctx['toDate']->format('Y-m-d')])
            ->with([
                'client:id,trade_name,first_name,last_name,code,branch_id,status_id',
                'client.branch:id,name',
                'branch:id,name',
                'client.status_client:id,name,color',
                'employee:id,first_name,middle_name,nickname',
                'createdByUser:id,name',
                'storehouse:id,name',
                'returnStorehouse:id,name',
            ]);

        // نطاقُ الرؤية — نفسُ شاشة التبديلات: الكلّ، أو فرعُه، أو فريقُه، أو ما أنشأه
        $this->scopeSalesDocuments($query);

        if ($ctx['effectiveBranchId']) {
            $this->constrainToNode($query, (int) $ctx['effectiveBranchId'], ! $request->boolean('exact_node'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('employee_id')) {
            /* القائمةُ مستخدمون (`users.id`)، والمستند يحمل منشئَه مستخدماً
               ومسؤولَه موظفاً (`employees.id`) — يُطابَق أيٌّ منهما. */
            $employeeOfUser = User::whereKey($request->input('employee_id'))->value('employee_id');
            $query->where(fn ($q) => $q->where('created_by', $request->input('employee_id'))
                ->when($employeeOfUser, fn ($w) => $w->orWhere('employee_id', $employeeOfUser)));
        }
        if ($request->filled('storehouse_id')) {
            $query->where('storehouse_id', $request->input('storehouse_id'));
        }
        if ($request->filled('return_storehouse_id')) {
            $query->where('return_storehouse_id', $request->input('return_storehouse_id'));
        }
        if ($request->filled('status')) {
            $query->whereHas('client', fn ($q) => $request->input('status') === 'no_status'
                ? $q->whereNull('status_id') : $q->where('status_id', $request->input('status')));
        }
        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.trim((string) $request->input('code')).'%');
        }

        $exchanges = $query->orderByDesc('exchange_date')->orderByDesc('id')->get()->keyBy('id');

        if ($exchanges->isEmpty()) {
            return collect();
        }

        $items = collect();
        foreach (array_chunk($exchanges->keys()->all(), 1000) as $chunk) {
            $items = $items->concat(
                ProductExchangeItem::query()
                    ->whereIn('product_exchange_id', $chunk)
                    ->get(['id', 'product_exchange_id', 'product_id', 'quantity', 'unit_price', 'total', 'reason_id', 'reason_notes'])
            );
        }

        $products = Product::with('category:id,name')->whereIn('id', $items->pluck('product_id')->unique()->filter()->all() ?: [0])
            ->get(['id', 'name', 'barcode', 'category_id'])->keyBy('id');
        $reasons = ExchangeReason::pluck('name', 'id');
        $reasonFilter = $request->filled('reason_id') ? ($reasons[(int) $request->input('reason_id')] ?? '') : null;

        $lines = collect();

        foreach ($items->sortByDesc('id') as $item) {
            $exchange = $exchanges->get($item->product_exchange_id);
            if (! $exchange) {
                continue;
            }

            $product = $products->get($item->product_id);

            if ($request->filled('product_id') && (int) $item->product_id !== (int) $request->input('product_id')) {
                continue;
            }
            if ($request->filled('category_id') && (! $product || (int) $product->category_id !== (int) $request->input('category_id'))) {
                continue;
            }
            // السببُ بالاسم — للسبب الواحد صفّان في `exchange_reasons` (انظر `groupLines`)
            if ($reasonFilter !== null && ($reasons[$item->reason_id] ?? null) !== $reasonFilter) {
                continue;
            }

            $client = $exchange->client;
            $employeeName = $exchange->employee
                ? trim($exchange->employee->full_name)
                : ($exchange->createdByUser->name ?? '-');
            $qty = (float) $item->quantity;
            $value = (float) $item->total ?: $qty * (float) $item->unit_price;
            $date = Carbon::parse($exchange->exchange_date)->format('Y-m-d');

            $lines->push([
                'line_id' => (int) $item->id,
                'exchange_id' => (int) $exchange->id,
                'code' => $exchange->code ?: ('#'.$exchange->id),
                'date' => $date,
                'month' => substr($date, 0, 7),
                'client_id' => $exchange->client_id,
                'client_name' => $client ? ($client->trade_name ?: trim($client->first_name.' '.$client->last_name)) : '-',
                'client_code' => $client->code ?? null,
                'client_status_name' => $client->status_client->name ?? null,
                'client_status_color' => $client->status_client->color ?? null,
                'employee_key' => $exchange->employee_id ? 'e'.$exchange->employee_id : 'u'.$exchange->created_by,
                'employee_name' => $employeeName !== '' ? $employeeName : '-',
                'created_by_name' => $exchange->createdByUser->name ?? '-',
                // فرعُ المستند — المحورُ نفسُه الذي تُصفّى به الشاشةُ والتقرير
                'branch_id' => $exchange->branch_id ?: 'none',
                'branch_name' => $exchange->branch->name ?? '-',
                'client_branch_name' => $client->branch->name ?? null,
                'product_id' => $product->id ?? 'deleted',
                'product_name' => $product->name ?? __('sales_reports.exchanges.deleted_product'),
                'product_code' => $product->barcode ?? null,
                'category_id' => $product->category_id ?? 'none',
                'category_name' => $product->category->name ?? __('sales_reports.exchanges.no_category'),
                'reason_id' => $item->reason_id ?: 'none',
                'reason_name' => $reasons[$item->reason_id] ?? __('sales_reports.exchanges.no_reason'),
                'reason_notes' => $item->reason_notes,
                'storehouse_id' => $exchange->storehouse_id ?: 'none',
                'storehouse_name' => $exchange->storehouse->name ?? '-',
                'return_storehouse_id' => $exchange->return_storehouse_id ?: 'none',
                'return_storehouse_name' => $exchange->returnStorehouse->name ?? '-',
                'quantity' => $qty,
                'unit_price' => (float) $item->unit_price,
                'value' => $value,
                'notes' => $exchange->notes,
            ]);
        }

        return $lines->values();
    }

    /**
     * قصرُ المستندات على عقدة «تابع لـ».
     *
     * فرعُ المستند في العقدة أو تحتها — كشاشة التبديلات. أو: المستندُ مكتوبٌ
     * على فرعٍ **فوق** العقدة (المنطقة) وعميلُه في العقدة — وهي حالُ من يختار
     * فرعاً تحت المنطقة، إذ لا مستندَ يحمل ذلك الفرعَ بعينه.
     */
    protected function constrainToNode($query, int $node, bool $withDescendants): void
    {
        $nodeIds = $withDescendants ? (AffiliationTree::descendantIds($node) ?: [$node]) : [$node];

        $ancestors = [];
        $parent = Branch::whereKey($node)->value('parent_id');
        for ($guard = 0; $parent && $guard < 20; $guard++) {
            $ancestors[] = (int) $parent;
            $parent = Branch::whereKey($parent)->value('parent_id');
        }

        $query->where(function ($q) use ($nodeIds, $ancestors, $node, $withDescendants) {
            $q->whereIn('branch_id', $nodeIds);

            if ($ancestors !== []) {
                $q->orWhere(fn ($s) => $s->whereIn('branch_id', $ancestors)
                    ->whereHas('client', fn ($c) => $c->inTreeNode($node, $withDescendants)));
            }
        });
    }

    /** إجماليّاتُ السطور — المصدرُ الوحيد لأرقام البطاقات والتذييل */
    protected function totalsOf(Collection $lines): array
    {
        $documents = $lines->pluck('exchange_id')->unique()->count();
        $value = (float) $lines->sum('value');
        $topReason = $lines->groupBy('reason_name')->map->sum('quantity')->sortDesc();
        $topProduct = $lines->groupBy('product_name')->map->sum('quantity')->sortDesc();

        return [
            'documents' => $documents,
            'lines' => $lines->count(),
            'quantity' => (float) $lines->sum('quantity'),
            'value' => $value,
            'avg_value' => $documents ? $value / $documents : 0.0,
            'clients' => $lines->pluck('client_id')->filter()->unique()->count(),
            'products' => $lines->pluck('product_id')->unique()->count(),
            'employees' => $lines->pluck('employee_key')->unique()->count(),
            'top_reason' => $topReason->keys()->first(),
            'top_reason_qty' => (float) ($topReason->first() ?? 0),
            'top_product' => $topProduct->keys()->first(),
            'top_product_qty' => (float) ($topProduct->first() ?? 0),
        ];
    }

    /**
     * الملخّص: سطرٌ لكلّ مجموعة بعدد مستنداتها وبنودها وكمّيتها وقيمتها.
     */
    protected function groupLines(Collection $lines, string $groupBy, float $grandValue): Collection
    {
        [$keyField, $labelField] = match ($groupBy) {
            'document' => ['exchange_id', 'code'],
            'product' => ['product_id', 'product_name'],
            'category' => ['category_id', 'category_name'],
            // بالاسم لا بالمعرّف: في `exchange_reasons` سببان باسمٍ واحد (القديم والمُعاد
            // زرعُه)، فالتجميعُ بالمعرّف يُخرج سطرين متطابقين في العين
            'reason' => ['reason_name', 'reason_name'],
            'client' => ['client_id', 'client_name'],
            'employee' => ['employee_key', 'employee_name'],
            'branch' => ['branch_id', 'branch_name'],
            'storehouse' => ['storehouse_id', 'storehouse_name'],
            'return_storehouse' => ['return_storehouse_id', 'return_storehouse_name'],
            'day' => ['date', 'date'],
            'month' => ['month', 'month'],
        };

        return $lines->groupBy(fn ($l) => (string) ($l[$keyField] ?? 'none'))
            ->map(function (Collection $group) use ($labelField, $groupBy, $grandValue) {
                $first = $group->first();
                $value = (float) $group->sum('value');
                $documents = $group->pluck('exchange_id')->unique()->count();
                $topReason = $group->groupBy('reason_name')->map->sum('quantity')->sortDesc();

                return [
                    'label' => (string) ($first[$labelField] ?? '-'),
                    'sub' => match ($groupBy) {
                        'document' => $first['date'].' · '.$first['client_name'],
                        'product' => $first['product_code'] ?: $first['category_name'],
                        'client' => $first['client_code'],
                        default => null,
                    },
                    'exchange_id' => $groupBy === 'document' ? $first['exchange_id'] : null,
                    'client_id' => $groupBy === 'client' ? $first['client_id'] : null,
                    'documents' => $documents,
                    'lines' => $group->count(),
                    'products' => $group->pluck('product_id')->unique()->count(),
                    'clients' => $group->pluck('client_id')->filter()->unique()->count(),
                    'quantity' => (float) $group->sum('quantity'),
                    'value' => $value,
                    'avg_value' => $documents ? $value / $documents : 0.0,
                    'share' => $grandValue > 0.005 ? round($value / $grandValue * 100, 1) : 0.0,
                    'top_reason' => $groupBy === 'reason' ? null : $topReason->keys()->first(),
                    'first_date' => $group->min('date'),
                    'last_date' => $group->max('date'),
                ];
            })
            ->sortBy(fn ($g) => in_array($groupBy, ['day', 'month'], true) ? $g['label'] : -$g['value'])
            ->values();
    }

    /** توزيعُ الأسباب بالكمية — للرسم الدائريّ */
    protected function reasonMix(Collection $lines): array
    {
        $palette = ['#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4', '#10b981', '#3b82f6', '#ec4899', '#64748b'];
        $total = (float) $lines->sum('quantity');

        return $lines->groupBy('reason_name')
            ->map(fn (Collection $g, $name) => ['label' => (string) $name, 'quantity' => (float) $g->sum('quantity'), 'value' => (float) $g->sum('value')])
            ->sortByDesc('quantity')
            ->values()
            ->map(fn ($s, $i) => $s + [
                'color' => $palette[$i % count($palette)],
                'pct' => $total > 0 ? round($s['quantity'] / $total * 100) : null,
            ])
            ->all();
    }
}
