<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StoreHouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Support\SalesLedger;

/**
 * تقريرا البنود والأصناف — مصدرُ بياناتٍ واحد.
 *
 * ══════════ الشاشتان ══════════
 *
 *   «مبيعات البنود»   (`salesReports.byItemNew`، هذا المتحكّم)
 *       تفصيليّ: سطرٌ لكلّ بندٍ في فاتورةٍ أو مرتجعٍ أو إشعارٍ دائن.
 *
 *   «مبيعات الأصناف»  (`salesReports.byProductNew`، {@see SalesByProductController})
 *       مجمَّع: سطرٌ لكلّ صنف، أو لكلّ تصنيفٍ/مجموعةٍ/وحدةٍ/مستودعٍ… بحسب
 *       «التجميع». وتقريرُ «المجموعات والوحدات» القديم صار خيارَ تجميعٍ فيه
 *       (`salesReports.byProductGroup` يُحوِّل إليه).
 *
 * كلاهما يقرأ السطورَ نفسَها من {@see buildLines()}، فمجموعُ البنود في
 * الأولى يساوي مجموعَ الأصناف في الثانية لأيّ تجميع.
 *
 * ══════════ المبالغ ══════════
 *
 * من القيد لا من الفاتورة، وعلى أساس لوحة المبيعات نفسِه: حصّةُ البند من
 * **طرف العميل** في قيد مستنده — مدينُه للفاتورة (إجماليُّها شاملَ الضريبة)
 * ودائنُه للمرتجع والإشعار — بنسبة إجمالي البند إلى إجمالي بنود المستند.
 * فمجموعُ المبيعات هنا هو بطاقةُ «المبيعات» في اللوحة، ومجموعُ المرتجعات
 * بطاقتُها (انظر `SalesReportTrait::clientLeg()` لماذا لا حسابُ 44/45).
 * والمستندُ يُصنَّف بنوع قيده (`SI`/`SR`/`CN`) كما تصنّفه اللوحة، وقيدُ
 * تكلفة المبيعات — توأمُ الفاتورة بالنوع نفسِه بلا طرفِ عميل — ليس مبيعاً.
 *
 * ══════════ ما كان يسقط ══════════
 *
 * مستندٌ بلا بنود (فاتورة غرامة، إشعارٌ حُذف مستندُه وبقي قيدُه) وبندٌ
 * حُذف صنفُه كانا يُتخطَّيان صامتَين، فينقص الإجماليُّ عن الدفتر. يظهران
 * الآن سطراً باسمه («مستند بلا بنود» / «صنف محذوف») ما دام لا فلترَ على
 * البنود — فالفلترُ على صنفٍ أو تصنيفٍ يُخرجهما بحقّ.
 */
class SalesByItemController extends Controller
{
    use SalesReportTrait;

    public function __construct()
    {
        $this->middleware('permission:sales.reports.by_item');
    }

    public function searchClients(Request $request)
    {
        return $this->searchReportClients($request);
    }

    /** «مبيعات البنود» — تفصيليّ */
    public function index(Request $request)
    {
        $data = $this->reportContext($request);
        $built = $this->buildLines($request, $data);

        $lines = $built['lines']->sortByDesc(fn ($l) => $l['date'].'#'.str_pad((string) $l['entry_id'], 10, '0', STR_PAD_LEFT))->values();
        $totals = $this->totalsOf($lines, $built);

        /* «تفاصيل» (الافتراض) سطرٌ لكلّ بند، و«ملخص» سطرٌ لكلّ مستند بمجموع بنوده */
        $isSummary = $request->input('view_type', 'details') === 'summary';
        $documents = $isSummary ? $this->documentsOf($lines) : collect();

        if ($request->ajax() || $request->boolean('_ajax')) {
            return response()->json(['success' => true, 'lines' => $lines, 'documents' => $documents, 'totals' => $totals]);
        }

        return view('sales::sals_report.salesRport.Sales_Item_Lines', $data + [
            'lines' => $lines,
            'documents' => $documents,
            'isSummary' => $isSummary,
            'totals' => $totals,
            'pageTitle' => __('sales_reports.by_item.title_lines'),
            'formAction' => route('salesReports.byItemNew'),
        ]);
    }

    /** ملخّص «البنود»: مستندٌ واحدٌ في كلّ سطر، بعدد بنوده ومجموع كمّيتها ومبلغها */
    protected function documentsOf(Collection $lines): Collection
    {
        return $lines->groupBy('entry_id')->map(function (Collection $group) {
            $first = $group->first();

            return [
                'entry_id' => $first['entry_id'],
                'date' => $first['date'],
                'reference_number' => $first['reference_number'],
                'kind' => $first['kind'],
                'entry_type_label' => $first['entry_type_label'],
                'entry_type_class' => $first['entry_type_class'],
                'client_id' => $first['client_id'],
                'client_name' => $first['client_name'],
                'employee_name' => $first['employee_name'],
                'branch_name' => $first['branch_name'],
                'lines' => $group->count(),
                'products' => $group->pluck('product_name')->unique()->count(),
                'base_quantity' => $group->sum('base_quantity'),
                'discount' => $group->sum('discount'),
                'amount' => $group->sum('amount'),
            ];
        })->values();
    }

    /** مغذّي المسار `…Ajax` — الأرقام نفسُها بصيغة JSON */
    public function ajax(Request $request)
    {
        $request->merge(['_ajax' => 1]);

        return $this->index($request);
    }

    public function getData(Request $request)
    {
        return $this->ajax($request);
    }

    // =====================================================================
    //  السياق المشترك: المدى، العقدة، القوائم
    // =====================================================================

    protected function reportContext(Request $request): array
    {
        [$fromDate, $toDate] = $this->parseDateRangeRequest($request);

        $user = User::find(auth()->id());
        $userBranchId = $user->branch_id;
        $currentBranch = Branch::find($userBranchId);
        $isMainBranch = $currentBranch && $currentBranch->is_main == 1;

        $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
        $branchIds = $effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null;

        $dropdowns = $this->getFilteredDropdowns($branchIds, withClientList: false);

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'isMainBranch' => $isMainBranch,
            'effectiveBranchId' => $effectiveBranchId,
            'branchIds' => $branchIds,
            'users' => $dropdowns['employees'],
            'employees' => $dropdowns['employees'],
            'branches' => $dropdowns['branches'] ?? collect(),
            'regionGroups' => $dropdowns['regionGroups'] ?? collect(),
            'cascadeBranches' => $dropdowns['cascadeBranches'] ?? [],
            'treeNodes' => $dropdowns['treeNodes'] ?? [],
            'statuses' => $dropdowns['statuses'] ?? collect(),
            'registrationStages' => $dropdowns['registrationStages'] ?? collect(),
            'selectedClient' => $request->filled('client_id') ? \App\Models\Client::find($request->input('client_id')) : null,
            'categories' => Category::orderBy('name')->get(['id', 'name', 'product_group_id']),
            'productGroups' => ProductGroup::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name', 'barcode', 'category_id']),
            'storehouses' => StoreHouse::all(['id', 'name']),
            'unitsList' => \App\Models\Unit::active()->get(['id', 'name']),
        ];
    }

    // =====================================================================
    //  السطور
    // =====================================================================

    protected function hasItemFilters(Request $request): bool
    {
        foreach (['product_id', 'storehouse_id', 'category_id', 'product_group_id', 'unit_id', 'item_type'] as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{lines: Collection, sales_entries: int, return_entries: int, credit_note_entries: int}
     */
    protected function buildLines(Request $request, array $ctx): array
    {
        // ─────────── القيود: بنوع المستند وبطرف عميل — كما تقرؤها اللوحة ───────────
        $query = JournalEntry::query()
            ->select(['id', 'date', 'reference_type', 'reference_number', 'invoice_id', 'client_id', 'created_by_employee', 'branch_id'])
            ->whereBetween('date', [$ctx['fromDate']->format('Y-m-d'), $ctx['toDate']->format('Y-m-d')])
            ->where('status', 1)
            ->whereIn('reference_type', array_merge(SalesLedger::SALE_TYPES, SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES))
            // قيدُ تكلفة المبيعات توأمُ الفاتورة (النوعُ نفسُه بلا طرفِ عميل) ليس مبيعاً
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_details as d')
                ->join('accounts as da', 'da.id', '=', 'd.account_id')
                ->whereColumn('d.journal_entry_id', 'journal_entries.id')
                ->whereNotNull('da.client_id')
                ->tap(fn ($w) => SalesLedger::excludeWalletAccounts($w, 'da')))
            ->with([
                'client:id,trade_name,code,branch_id,status_id,registration_stage_id',
                'client.branch:id,name',
                'client.status_client:id,name,color',
                'client.registrationStage:id,name',
                'createdByEmployee:id,name',
                'branch:id,name',
            ]);

        $this->excludeClosedRanges($query, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($query);

        if ($ctx['branchIds'] && $ctx['effectiveBranchId']) {
            $node = $ctx['effectiveBranchId'];
            $withDescendants = ! $request->boolean('exact_node');
            $branchIds = $ctx['branchIds'];
            $query->where(function ($q) use ($node, $withDescendants, $branchIds) {
                $q->whereHas('client', fn ($c) => $c->inTreeNode($node, $withDescendants))
                    ->orWhere(fn ($s) => $s->whereNull('client_id')->whereIn('branch_id', $branchIds));
            });
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('employee_id')) {
            $query->where('created_by_employee', $request->employee_id);
        }
        if ($request->filled('status')) {
            $query->whereHas('client', fn ($q) => $request->status === 'no_status'
                ? $q->whereNull('status_id') : $q->where('status_id', $request->status));
        }
        if ($request->filled('registration_stage')) {
            $query->whereHas('client', fn ($q) => $request->registration_stage === 'no_stage'
                ? $q->whereNull('registration_stage_id') : $q->where('registration_stage_id', $request->registration_stage));
        }

        $entries = $query->get()->keyBy('id');
        $entryIds = $entries->keys()->all();

        // ─────────── مبالغ القيود: طرفُ العميل (مدين/دائن) — أساسُ اللوحة ───────────
        $clientLegs = [];
        foreach (array_chunk($entryIds, 1000) as $chunk) {
            $rows = DB::table('journal_entry_details as jed')
                ->join('accounts as a', 'a.id', '=', 'jed.account_id')
                ->whereIn('jed.journal_entry_id', $chunk)
                ->whereNotNull('a.client_id')
                ->tap(fn ($q) => SalesLedger::excludeWalletAccounts($q, 'a'))
                ->groupBy('jed.journal_entry_id')
                ->get(['jed.journal_entry_id', DB::raw('SUM(jed.debit) as dr'), DB::raw('SUM(jed.credit) as cr')]);
            foreach ($rows as $r) {
                $clientLegs[$r->journal_entry_id] = ['dr' => (float) $r->dr, 'cr' => (float) $r->cr];
            }
        }

        // ─────────── بنود المستندات ───────────
        $invoiceIds = [];
        $creditNoteIds = [];
        $kinds = [];
        foreach ($entries as $id => $entry) {
            $refType = $entry->getRawOriginal('reference_type');
            /* التصنيفُ بنوع القيد كما في `SalesLedger`: فاتورةٌ `SI`، مرتجعٌ `SR`،
               وإشعارٌ دائن `CN`. */
            $kind = in_array($refType, SalesLedger::SALE_TYPES, true) ? 'sale'
                : (in_array($refType, SalesLedger::RETURN_TYPES, true) ? 'return' : 'credit_note');
            $kinds[$id] = $kind;

            if ($refType === 'CN' && $entry->reference_number) {
                $creditNoteIds[] = (int) $entry->reference_number;
            } elseif ($entry->invoice_id) {
                $invoiceIds[] = (int) $entry->invoice_id;
            }
        }

        $itemsByInvoice = collect();
        $itemsByCreditNote = collect();
        $itemRelations = ['productUnit:id,category_unit_id', 'productUnit.categoryUnit:id,unit_id', 'productUnit.categoryUnit.unit:id,name'];
        foreach (array_chunk(array_values(array_unique($invoiceIds)), 1000) as $chunk) {
            $itemsByInvoice = $itemsByInvoice->concat(InvoiceItem::with($itemRelations)->whereIn('invoice_id', $chunk)->get());
        }
        foreach (array_chunk(array_values(array_unique($creditNoteIds)), 1000) as $chunk) {
            $itemsByCreditNote = $itemsByCreditNote->concat(InvoiceItem::with($itemRelations)->whereIn('credit_note_id', $chunk)->get());
        }
        $itemsByInvoice = $itemsByInvoice->groupBy('invoice_id');
        $itemsByCreditNote = $itemsByCreditNote->groupBy('credit_note_id');

        $products = Product::with(['category:id,name,product_group_id', 'category.productGroup:id,name'])->get(['id', 'name', 'barcode', 'category_id'])->keyBy('id');
        $storehouses = StoreHouse::pluck('name', 'id');
        $unitFilterName = $request->filled('unit_id') ? optional(\App\Models\Unit::find($request->unit_id))->name : null;
        $filtersOn = $this->hasItemFilters($request);

        $typeLabels = [
            'sale' => [__('sales_reports.status.sales_invoice'), 'success'],
            'return' => [__('sales_reports.status.return'), 'danger'],
            'credit_note' => [__('sales_reports.status.credit_note'), 'info'],
        ];

        $lines = collect();
        $counted = ['sale' => [], 'return' => [], 'credit_note' => []];

        foreach ($entries as $id => $entry) {
            $kind = $kinds[$id];
            $sign = $kind === 'sale' ? 1 : -1;
            $leg = $clientLegs[$id] ?? ['dr' => 0.0, 'cr' => 0.0];
            /* الفاتورةُ مدينُ العميل (صافيَ ما قُيِّد عليه دائناً في القيد نفسِه)،
               والمرتجعُ والإشعارُ دائنُه وحده — ومدينُهما ردُّ نقدٍ يخصّ التحصيل لا
               المرتجعات. المبلغ موجبٌ هنا ثمّ تُعطى الإشارةُ بنوع المستند. */
            $entryNet = $kind === 'sale' ? $leg['dr'] - $leg['cr'] : $leg['cr'];

            if ($kind !== 'sale' && $entryNet <= 0.009) {
                // قيدُ ردِّ نقدٍ بلا قيمةِ مرتجع — يُحتسب في التحصيل كما في اللوحة
                continue;
            }

            $refType = $entry->getRawOriginal('reference_type');
            $items = ($refType === 'CN' && $entry->reference_number)
                ? ($itemsByCreditNote->get((int) $entry->reference_number) ?? collect())
                : ($entry->invoice_id ? ($itemsByInvoice->get((int) $entry->invoice_id) ?? collect()) : collect());

            $base = [
                'entry_id' => $id,
                'kind' => $kind,
                'is_return' => $kind === 'return',
                'is_credit_note' => $kind === 'credit_note',
                'entry_type_label' => $typeLabels[$kind][0],
                'entry_type_class' => $typeLabels[$kind][1],
                'reference_number' => $entry->reference_number ?: $id,
                'date' => Carbon::parse($entry->date)->format('Y-m-d'),
                'employee_id' => $entry->created_by_employee,
                'employee_name' => $entry->createdByEmployee->name ?? '-',
                'client_id' => $entry->client_id,
                'client_name' => $entry->client->trade_name ?? '-',
                'client_code' => $entry->client->code ?? '-',
                'branch_id' => $entry->client->branch_id ?? $entry->branch_id,
                'branch_name' => $entry->client->branch->name ?? $entry->branch->name ?? '-',
                'client_status_name' => $entry->client->status_client->name ?? null,
                'client_status_color' => $entry->client->status_client->color ?? null,
                'client_stage_name' => $entry->client->registrationStage->name ?? null,
            ];

            $docTotal = (float) $items->sum('total');

            if ($items->isEmpty() || $docTotal <= 0.0001 && abs($entryNet) > 0.005) {
                /* مستندٌ بلا بنودٍ تحمل قيمته — يُعرض بمبلغه كي يطابق الإجماليُّ الدفتر */
                if ($filtersOn || abs($entryNet) < 0.005) {
                    continue;
                }
                $lines->push($base + $this->blankProduct('no_items', 'مستند بلا بنود') + [
                    'line_id' => 'e'.$id,
                    'storehouse_name' => '-', 'store_house_id' => null,
                    'unit_name' => '-', 'unit_key' => 'none',
                    'quantity' => 0, 'base_quantity' => 0, 'unit_price' => 0, 'discount' => 0,
                    'amount' => $sign * $entryNet,
                ]);
                $counted[$kind][$id] = true;

                continue;
            }

            $any = false;
            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if ($request->filled('product_id') && (int) $item->product_id !== (int) $request->product_id) {
                    continue;
                }
                if ($request->filled('storehouse_id') && (int) $item->store_house_id !== (int) $request->storehouse_id) {
                    continue;
                }
                if ($request->filled('category_id') && (! $product || (int) $product->category_id !== (int) $request->category_id)) {
                    continue;
                }
                if ($request->filled('product_group_id') && (! $product || ! $product->category || (int) $product->category->product_group_id !== (int) $request->product_group_id)) {
                    continue;
                }
                $unitName = trim((string) $item->unit_name);
                if ($request->filled('unit_id')) {
                    $unitId = optional(optional($item->productUnit)->categoryUnit)->unit_id;
                    if ((int) $unitId !== (int) $request->unit_id && ($unitFilterName === null || $unitName !== $unitFilterName)) {
                        continue;
                    }
                }
                $isGift = str_contains((string) $item->item, '(هدية)') || str_contains((string) $item->description, 'هدية');
                if ($request->input('item_type') === 'gift' && ! $isGift) {
                    continue;
                }
                if ($request->input('item_type') === 'normal' && $isGift) {
                    continue;
                }

                $ratio = $docTotal > 0 ? ((float) $item->total / $docTotal) : 0;
                $qty = (float) $item->quantity;
                $baseQty = (float) ($item->getRawOriginal('quantity_in_base_unit') ?: $item->quantity_in_base_unit ?: $qty);

                $productPart = $product
                    ? [
                        'item_id' => $product->id,
                        'product_name' => $product->name ?? '-',
                        'product_code' => $product->barcode ?: '-',
                        'category_id' => $product->category_id ?? 'none',
                        'category_name' => $product->category->name ?? 'بلا تصنيف',
                        'product_group_id' => $product->category->product_group_id ?? 'none',
                        'product_group_name' => $product->category->productGroup->name ?? 'بلا مجموعة',
                    ]
                    : $this->blankProduct('deleted', $item->item ? $item->item.' (صنف محذوف)' : 'صنف محذوف');

                $lines->push($base + $productPart + [
                    'line_id' => $item->id,
                    'item_label' => $item->item,
                    'is_gift' => $isGift,
                    'storehouse_name' => $storehouses[$item->store_house_id] ?? '-',
                    'store_house_id' => $item->store_house_id,
                    'unit_name' => $unitName !== '' ? $unitName : 'الأساسية',
                    'unit_key' => $unitName !== '' ? $unitName : 'الأساسية',
                    'quantity' => $sign * $qty,
                    'base_quantity' => $sign * $baseQty,
                    'unit_price' => (float) $item->unit_price,
                    'discount' => $sign * (float) ($item->discount ?? 0),
                    'amount' => $sign * $entryNet * $ratio,
                ]);
                $any = true;
            }

            if ($any) {
                $counted[$kind][$id] = true;
            }
        }

        return [
            'lines' => $lines,
            'sales_entries' => count($counted['sale']),
            'return_entries' => count($counted['return']),
            'credit_note_entries' => count($counted['credit_note']),
        ];
    }

    private function blankProduct(string $key, string $label): array
    {
        return [
            'item_id' => $key,
            'item_label' => $label,
            'is_gift' => false,
            'product_name' => $label,
            'product_code' => '-',
            'category_id' => $key,
            'category_name' => $label,
            'product_group_id' => $key,
            'product_group_name' => $label,
        ];
    }

    /** إجماليّات السطور — المصدرُ الوحيد لأرقام البطاقات في الشاشتين */
    protected function totalsOf(Collection $lines, array $built): array
    {
        $t = [
            'count' => $built['sales_entries'],
            'returns_count' => $built['return_entries'],
            'credit_notes_count' => $built['credit_note_entries'],
            'lines' => $lines->count(),
            'sales_qty' => 0, 'returns_qty' => 0,
            'sales_base_qty' => 0, 'returns_base_qty' => 0,
            'quantities_by_unit' => [],
            'sales_amount' => 0, 'returns_amount' => 0, 'credit_notes_amount' => 0,
            'discount' => 0,
        ];

        foreach ($lines as $l) {
            $unit = $l['unit_name'];
            if ($l['kind'] === 'sale') {
                $t['sales_qty'] += $l['quantity'];
                $t['sales_base_qty'] += $l['base_quantity'];
                $t['sales_amount'] += $l['amount'];
            } else {
                $t['returns_qty'] += abs($l['quantity']);
                $t['returns_base_qty'] += abs($l['base_quantity']);
                $t[$l['kind'] === 'return' ? 'returns_amount' : 'credit_notes_amount'] += abs($l['amount']);
            }
            if ($unit !== '-') {
                $t['quantities_by_unit'][$unit] = ($t['quantities_by_unit'][$unit] ?? 0) + $l['quantity'];
            }
            $t['discount'] += $l['discount'];
        }

        $t['net_qty'] = $t['sales_qty'] - $t['returns_qty'];
        $t['net_base_qty'] = $t['sales_base_qty'] - $t['returns_base_qty'];
        // المبالغُ شاملةُ الضريبة (طرفُ العميل) — كبطاقات اللوحة
        $t['net_sales'] = $t['sales_amount'] - $t['returns_amount'] - $t['credit_notes_amount'];

        return $t;
    }
}
