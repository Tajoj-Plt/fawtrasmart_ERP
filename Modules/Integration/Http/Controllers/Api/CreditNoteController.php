<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\SalesPlanFeatures;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CreditNotification;
use App\Models\EmployeeGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Invoices\ClientDebtSettlement;
use App\Services\PointsService;
use App\Services\Settings\ApplicationRegistry;
use App\Services\Tax\DocumentTaxCalculator;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\StockEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Controllers\Concerns\ChecksSubscriptionLimits;
use Modules\Sales\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Sales\Http\Controllers\Concerns\RendersSalesDocumentTemplates;
use Modules\Sales\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Sales\Http\Controllers\Concerns\ResolvesSaleCost;
use Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Sales\Http\Controllers\Concerns\ScopesSalesVisibility;
use Modules\Sales\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Sales\Support\ReturnLimits;
use Modules\Sales\Support\SalesWarehouse;

/**
 * الإشعار الدائن في تطبيق الجوال — إضافةٌ وعرضٌ وطباعة، نفس قواعد شاشة
 * الموقع (`Modules\Sales\Http\Controllers\CreditNotificationController`):
 * توأمُ المرتجع («الإشعار الدائن والمرتجع رَدٌّ واحد») لكن بجدولٍ مستقلّ
 * (`credit_notifications`)، مرتبطٌ اختيارياً بفاتورة (`invoice_id` قابلٌ
 * للفراغ)، ويُسدِّد ديون العميل تلقائياً عبر `ClientDebtSettlement`
 * (الفاتورة المرجعية ← الرصيد السابق ← الفواتير من الأقدم).
 */
class CreditNoteController extends Controller
{
    use ChecksSubscriptionLimits;
    use GeneratesDocumentNumbers;
    use RendersSalesDocumentTemplates;
    use ResolvesAccountingAccounts;
    use ResolvesSaleCost;
    use RespectsAccountingPeriods;
    use ScopesSalesVisibility;
    use UsesDocumentCurrency;

    private const NUMBER_SECTION = 'credit-note';

    public function __construct(private readonly ClientDebtSettlement $settlement)
    {
        $this->middleware(function (Request $request, \Closure $next) {
            if (! ApplicationRegistry::isAvailable('sales')) {
                return response()->json(['success' => false, 'message' => __('settings.applications.blocked', ['module' => ApplicationRegistry::labelFor('sales')])], 403);
            }

            return $next($request);
        });
    }

    /**
     * قائمة الإشعارات الدائنة — نفس فلاتر ونطاق شاشة الفهرس في الموقع.
     *
     * الاستعلامات المقبولة: client_id, credit_number, invoice_id, status
     * (5 يعني «ملغى» وتُطابق 0 أو 5 معاً كما في الشاشة), branch,
     * total_from/total_to, from_date/to_date (الافتراضي: الفترة المحاسبية
     * الجارية)، created_by, sales_person_user, item_search، per_page، page.
     *
     * ✅ صلاحية العرض محروسة في المسار (permission.role:sales.credit_notes.view)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $range = $this->reportDateRange($request);

        $query = CreditNotification::with(['client', 'items.product', 'invoice']);

        $this->scopeSalesDocuments($query);

        // محورُ المجموعات فوق سلَّم النطاق — لمن عليه قيدٌ أصلاً (نفس CreditNotificationController)
        if ($user->isEmployee() && $this->salesScopeApplies()) {
            $this->scopeToEmployeeGroups($query, $user);
        }

        $this->applyHeaderBranchScope($query);

        $this->applyIndexFilters($query, $request, $range);

        $notes = $query->orderByDesc('created_at')
            ->paginate($this->resolvePerPage($request))
            ->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => $notes->getCollection()->map(fn ($n) => $this->notePayload($n)),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
            ],
        ]);
    }

    /** خطّةُ التسديد دون كتابةٍ — لعرضها قبل الحفظ. الحقول: client_id, amount, invoice_id؟ */
    // ✅ صلاحية الإنشاء محروسة في المسار (permission.role:sales.credit_notes.create)
    public function settlementPreview(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|integer|exists:clients,id',
                'amount' => 'required|numeric|min:0.01',
                'invoice_id' => 'nullable|integer|exists:invoices,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first()], 422);
        }

        $plan = $this->settlement->plan((int) $validated['client_id'], (float) $validated['amount'], $validated['invoice_id'] ?? null);

        return response()->json(['success' => true, 'data' => $plan]);
    }

    /**
     * الحقول: client_id, invoice_id? (فاتورةٌ مرجعية اختيارية), credit_date?,
     * store_house_id? (إن لم تُرسَل فاتورة), treasury_id?, discount_amount?,
     * discount_type?, shipping_cost?, shipping_tax_id?,
     * items[] { product_id, product_unit_id?, quantity, unit_price, tax_1_id?, tax_2_id? }.
     */
    // ✅ صلاحية الإنشاء محروسة في المسار (permission.role:sales.credit_notes.create)
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|integer|exists:clients,id',
                'invoice_id' => 'nullable|integer|exists:invoices,id',
                'credit_date' => 'nullable|date',
                'store_house_id' => 'nullable|integer|exists:store_houses,id',
                'treasury_id' => 'nullable|integer|exists:treasuries,id',
                'notes' => 'nullable|string',
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:amount,percentage',
                'shipping_cost' => 'nullable|numeric|min:0',
                'shipping_tax_id' => 'nullable|integer',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.product_unit_id' => 'nullable',
                'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.tax_1_id' => 'nullable|integer',
                'items.*.tax_2_id' => 'nullable|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        }

        $user = $request->user();
        $original = ! empty($validated['invoice_id']) ? Invoice::find($validated['invoice_id']) : null;

        if ($original) {
            if (! $this->canAccessSalesDocument($original->created_by, $original->employee_id, $original->branch_id)) {
                return response()->json(['success' => false, 'message' => 'لا تملك صلاحية الوصول إلى هذه الفاتورة.'], 403);
            }
            if ($original->client_id != $validated['client_id']) {
                return response()->json(['success' => false, 'message' => 'عميل الفاتورة المرجعية يختلف عن العميل المختار.'], 422);
            }
            if ($blocked = $original->creditNoteBlockReason()) {
                return response()->json(['success' => false, 'message' => $blocked], 422);
            }
        }

        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::CREDIT_NOTES)) {
            return response()->json(['success' => false, 'message' => $blocked], 402);
        }

        $documentDate = $validated['credit_date'] ?? now()->toDateString();
        if ($blocked = $this->accountingPeriodBlock($documentDate)) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        // ═══ حدود الردّ — فقط إن كانت مرتبطةً بفاتورة ═══
        if ($original) {
            $limitsInput = [];
            foreach ($validated['items'] as $row) {
                $line = ReturnLimits::for($original)->lineFor((int) $row['product_id'], $row['product_unit_id'] ?? null);
                $limitsInput[] = ['product_id' => $row['product_id'], 'quantity' => $row['quantity'], 'unit_price' => $row['unit_price'], 'factor' => $line ? ReturnLimits::factorOf($line) : 1.0];
            }
            if ($violation = ReturnLimits::for($original)->violation($limitsInput)) {
                return response()->json(['success' => false, 'message' => $violation], 422);
            }
        }

        $defaultStoreHouse = SalesWarehouse::resolve($validated['store_house_id'] ?? null, $user);

        // ═══ تجهيز الأسطر ═══
        $lines = [];
        foreach ($validated['items'] as $row) {
            $product = Product::find($row['product_id']);
            if (! $product) {
                return response()->json(['success' => false, 'message' => 'أحد الأصناف غير موجود.'], 422);
            }

            $originalLine = $original ? ReturnLimits::for($original)->lineFor((int) $row['product_id'], $row['product_unit_id'] ?? null) : null;
            $factor = $originalLine ? ReturnLimits::factorOf($originalLine) : 1.0;
            $storeHouseId = $originalLine?->store_house_id ?? optional($defaultStoreHouse)->id;

            if (StockEligibility::isStockable($product) && ! $storeHouseId) {
                return response()->json(['success' => false, 'message' => 'حدِّد المستودع.'], 422);
            }

            $quantity = (float) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];
            $itemTotal = round($quantity * $unitPrice, 2);

            $lines[] = [
                'product' => $product,
                'original_item' => $originalLine,
                'product_unit_id' => $originalLine?->product_unit_id,
                'store_house_id' => $storeHouseId,
                'unit_name' => $originalLine?->unit_name ?? ($product->unit_name ?? ''),
                'conversion_factor' => $factor,
                'quantity' => $quantity,
                'quantity_in_base_unit' => $quantity * $factor,
                'unit_price' => $unitPrice,
                'item_total' => $itemTotal,
                'net' => $itemTotal,
                'tax_1_id' => $row['tax_1_id'] ?? $originalLine?->tax_1_id,
                'tax_2_id' => $row['tax_2_id'] ?? $originalLine?->tax_2_id,
            ];
        }

        $netItems = array_sum(array_column($lines, 'net'));
        $docDiscountType = $validated['discount_type'] ?? 'amount';
        $rawDocDiscount = (float) ($validated['discount_amount'] ?? 0);
        $documentDiscount = $docDiscountType === 'percentage'
            ? min($netItems * min($rawDocDiscount, 100) / 100, $netItems)
            : min($rawDocDiscount, $netItems);

        $discountShares = DocumentTaxCalculator::allocateDocumentDiscount(array_column($lines, 'net'), $documentDiscount);

        $itemsTax = 0.0;
        foreach ($lines as $i => &$line) {
            $taxableBase = max(0, $line['net'] - ($discountShares[$i] ?? 0));
            $taxLine = DocumentTaxCalculator::line(1, $taxableBase, 0, array_filter([$line['tax_1_id'], $line['tax_2_id']]), []);
            $line['line_tax'] = $taxLine['tax'];
            $itemsTax += $taxLine['tax'];
            $line['total'] = round($line['net'] - ($taxLine['base'] - $taxLine['net']), 2);
        }
        unset($line);

        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
        $shippingTaxIds = DocumentTaxCalculator::shippingTaxIds((bool) ($validated['shipping_tax_id'] ?? false), $validated['shipping_tax_id'] ?? null, []);
        $shippingTax = DocumentTaxCalculator::shippingTax($shippingCost, $shippingTaxIds);

        $subtotal = array_sum(array_column($lines, 'total'));
        $taxTotal = $itemsTax + $shippingTax;
        $grandTotal = round(($netItems - $documentDiscount) + $taxTotal + $shippingCost, 2);

        DB::beginTransaction();

        try {
            $code = $this->resolveDocumentNumber(null, self::NUMBER_SECTION);

            $note = CreditNotification::create([
                'client_id' => $validated['client_id'],
                'branch_id' => current_branch_id(),
                'invoice_id' => $validated['invoice_id'] ?? null,
                'created_by' => $user->id,
                'employee_id' => $user->employee_id,
                'store_house_id' => optional($defaultStoreHouse)->id,
                'credit_number' => $code,
                'currency' => $this->defaultCurrency(),
                'credit_date' => $documentDate,
                'release_date' => $documentDate,
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $subtotal,
                'total_discount' => $documentDiscount,
                'discount_amount' => $documentDiscount,
                'discount_type' => $docDiscountType === 'percentage' ? 2 : 1,
                'total_tax' => $taxTotal,
                'shipping_cost' => $shippingCost,
                'shipping_tax' => $shippingTax,
                'grand_total' => $grandTotal,
                'treasury_id' => $validated['treasury_id'] ?? null,
                'status' => 3,
            ]);

            foreach ($lines as $line) {
                $item = InvoiceItem::create([
                    'credit_note_id' => $note->id,
                    'product_id' => $line['product']->id,
                    'product_unit_id' => $line['product_unit_id'],
                    'store_house_id' => $line['store_house_id'],
                    'item' => $line['product']->name,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'quantity_in_base_unit' => $line['quantity_in_base_unit'],
                    'conversion_factor' => $line['conversion_factor'],
                    'unit_name' => $line['unit_name'],
                    'tax_1_id' => $line['tax_1_id'],
                    'tax_1' => $line['tax_1_id'] ? DocumentTaxCalculator::rate($line['tax_1_id']) : 0,
                    'tax_2_id' => $line['tax_2_id'],
                    'tax_2' => $line['tax_2_id'] ? DocumentTaxCalculator::rate($line['tax_2_id']) : 0,
                    'total' => $line['total'],
                ]);

                if (StockEligibility::isStockable($line['product'])) {
                    $this->restockLine($note, $line, $user);
                    $unitCost = $original
                        ? $this->returnUnitCost($original->id, $line['product'])
                        : ($this->averageUnitCost($line['product']) ?? 0.0);
                    InvoiceItem::whereKey($item->id)->update(['unit_cost_at_sale' => $unitCost]);
                }
            }

            $journalEntryId = $this->postCreditNoteJournalEntry($note, $subtotal, $documentDiscount, $itemsTax, $shippingTax, $shippingCost, $grandTotal);
            $this->postCreditNoteCogsJournalEntry($note, $original);

            // ═══ تسديد ديون العميل تلقائياً ═══
            $plan = $this->settlement->plan((int) $validated['client_id'], $grandTotal, $validated['invoice_id'] ?? null);
            $this->settlement->apply($note, $plan, $journalEntryId);

            OperationLog::record('created', 'SalesCreditNotes', $note, 'إنشاء إشعار دائن «'.$note->credit_number.'» — عبر تطبيق الجوال');

            try {
                app(PointsService::class)->processAction('return_processed', [
                    'employee_id' => $note->employee_id, 'client_id' => $note->client_id,
                    'amount' => $grandTotal, 'reference_type' => CreditNotification::class, 'reference_id' => $note->id,
                ]);
            } catch (\Throwable $e) {
                OperationLog::failure($e, 'SalesCreditNotes', 'points_on_credit_note', $note);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'SalesCreditNotes', 'integration.store');

            return response()->json(['success' => false, 'message' => 'تعذّر حفظ الإشعار الدائن: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '✨ تم إنشاء الإشعار الدائن بنجاح!',
            'data' => $this->notePayload($note->fresh(['client', 'items.product'])) + ['settlement' => $plan],
        ], 201);
    }

    // ✅ صلاحية العرض محروسة في المسار (permission.role:sales.credit_notes.view)
    public function show(Request $request, int $id): JsonResponse
    {
        $note = CreditNotification::with(['client', 'items.product', 'invoice'])->find($id);
        if (! $note) {
            return response()->json(['success' => false, 'message' => 'الإشعار الدائن غير موجود.'], 404);
        }

        if (! $this->canAccessSalesDocument($note->created_by, $note->employee_id, $note->branch_id)) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية عرض هذا الإشعار.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->notePayload($note) + [
            'settlements' => $this->settlement->settlementsFor($note),
        ]]);
    }

    // ✅ صلاحية الطباعة محروسة في المسار (permission.role:sales.credit_notes.print)
    public function print(Request $request, int $id): \Illuminate\Http\Response
    {
        $note = CreditNotification::findOrFail($id);
        abort_unless($this->canAccessSalesDocument($note->created_by, $note->employee_id, $note->branch_id), 403, 'لا تملك صلاحية عرض هذا الإشعار.');

        $template = $request->input('template');
        $html = $this->wantsBuiltinPrint($template) ? null : $this->renderSalesTemplate('credit_note', $id, $template);

        if ($html) {
            OperationLog::record('printed', 'SalesCreditNotes', $note, 'طباعة الإشعار الدائن «'.$note->credit_number.'» — عبر تطبيق الجوال');

            return response(DocumentPrintPage::wrap($html));
        }

        return response()->view('sales::creted_note.pdf', ['creditNot' => $note->load(['client', 'items.product'])]);
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** يقصر الاستعلام على عملاء مجموعات الموظّف — نفس CreditNotificationController::scopeToEmployeeGroups(). */
    private function scopeToEmployeeGroups($query, $user): void
    {
        $groupIds = EmployeeGroup::where('employee_id', $user->employee_id)->pluck('group_id');

        if ($groupIds->isEmpty()) {
            return;
        }

        $query->whereHas('client.Neighborhoodname.Region', fn ($q) => $q->whereIn('id', $groupIds));
    }

    /** شروط بحث الفهرس — نفس applySearchFilters() في CreditNotificationController (الموقع)، دون فلتر العملة. */
    private function applyIndexFilters($query, Request $request, array $range): void
    {
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('credit_number')) {
            $term = trim((string) $request->credit_number);
            $query->where(function ($q) use ($term) {
                $q->where('credit_number', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        if ($request->filled('status')) {
            (string) $request->status === '5'
                ? $query->whereIn('status', [0, 5])
                : $query->where('status', $request->status);
        }

        if ($request->filled('branch')) {
            $query->forBranch($request->branch);
        }

        if ($request->filled('total_from')) {
            $query->where('grand_total', '>=', $request->total_from);
        }
        if ($request->filled('total_to')) {
            $query->where('grand_total', '<=', $request->total_to);
        }

        $start = \Carbon\Carbon::parse($range['start']);
        $end = \Carbon\Carbon::parse($range['end']);
        $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('credit_notifications.credit_date', [$start->toDateString(), $end->toDateString()])
                ->orWhere(function ($legacy) use ($start, $end) {
                    $legacy->whereNull('credit_notifications.credit_date')
                        ->whereBetween('credit_notifications.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                });
        });

        $this->excludeClosedRanges($query, 'credit_notifications.credit_date');

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('sales_person_user')) {
            $query->where('employee_id', $request->sales_person_user);
        }

        if ($request->filled('item_search')) {
            $term = trim((string) $request->item_search);
            $query->whereHas('items', function ($q) use ($term) {
                $q->where('item', 'like', $term.'%')->orWhere('description', 'like', $term.'%');
            });
        }
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    private function restockLine(CreditNotification $note, array $line, $user): void
    {
        $productDetails = ProductDetails::firstOrCreate(
            ['product_id' => $line['product']->id, 'store_house_id' => $line['store_house_id']],
            ['quantity' => 0]
        );
        $stockBefore = $productDetails->quantity;
        $productDetails->increment('quantity', $line['quantity_in_base_unit']);
        $stockAfter = $stockBefore + $line['quantity_in_base_unit'];

        $permit = WarehousePermits::create([
            'permission_type' => 2,
            'permission_date' => $note->credit_date,
            'reference_type' => 'CreditNote',
            'reference_id' => $note->id,
            'permission_source_id' => PermissionSources::SALES_CREDIT_NOTE,
            'client_id' => $note->client_id,
            'number' => $note->credit_number,
            'store_houses_id' => $line['store_house_id'],
            'grand_total' => $line['total'],
            'currency' => $note->currency,
            'created_by' => $user->id,
            'status' => 'apporod',
        ]);

        $inventoryLine = WarehousePermitsProducts::inventoryLineFromInvoiceItem([
            'quantity_in_base_unit' => $line['quantity_in_base_unit'], 'total' => $line['total'],
            'unit_price' => $line['unit_price'], 'conversion_factor' => $line['conversion_factor'],
        ]);

        WarehousePermitsProducts::create([
            'warehouse_permits_id' => $permit->id, 'product_id' => $line['product']->id,
            'quantity' => $inventoryLine['quantity'], 'unit_price' => $inventoryLine['unit_price'], 'total' => $inventoryLine['total'],
            'stock_before' => $stockBefore, 'stock_after' => $stockAfter, 'store_house_id' => $line['store_house_id'],
        ]);
    }

    private function postCreditNoteJournalEntry(CreditNotification $note, float $subtotal, float $discount, float $itemsTax, float $shippingTax, float $shippingCost, float $grandTotal): int
    {
        $clientAccount = Account::ledger()->where('client_id', $note->client_id)->first();
        if (! $clientAccount) {
            throw new \RuntimeException('لا يوجد حسابٌ محاسبيّ لهذا العميل.');
        }

        $entry = \App\Models\JournalEntry::create([
            'date' => $note->credit_date, 'description' => 'إشعار دائن رقم '.$note->credit_number,
            'status' => true, 'currency' => $note->currency,
            'client_id' => $note->client_id, 'employee_id' => $note->employee_id,
            'reference_type' => ReferenceType::CREDIT_NOTE->value, 'reference_number' => $note->id,
            'branch_id' => $note->branch_id, 'is_posted' => true, 'linking_type' => LinkingType::SINGLE->value,
        ]);

        $rows = [];
        $rows[] = ['account_id' => $this->requireAccountingAccount('sales_returns')->id, 'debit' => $subtotal, 'credit' => 0, 'description' => 'مردودات مبيعات — إشعار دائن'];
        if ($discount > 0.005) {
            $rows[] = ['account_id' => $this->requireAccountingAccount('allowed_discount')->id, 'debit' => 0, 'credit' => $discount, 'description' => 'إلغاء خصم مسموح به'];
        }
        if ($itemsTax > 0.005) {
            $rows[] = ['account_id' => $this->requireAccountingAccount('vat_payable')->id, 'debit' => $itemsTax, 'credit' => 0, 'description' => 'إلغاء ضريبة القيمة المضافة'];
        }
        if ($shippingTax > 0.005) {
            $rows[] = ['account_id' => $this->shippingVatAccountId(), 'debit' => $shippingTax, 'credit' => 0, 'description' => 'إلغاء ضريبة شحن'];
        }
        if ($shippingCost > 0.005) {
            $rows[] = ['account_id' => $this->requireAccountingAccount('shipping_revenue')->id, 'debit' => $shippingCost, 'credit' => 0, 'description' => 'إلغاء إيراد شحن'];
        }
        $rows[] = ['account_id' => $clientAccount->id, 'debit' => 0, 'credit' => $grandTotal, 'description' => 'دائن العميل — إشعار '.$note->credit_number];

        foreach ($rows as $row) {
            \App\Models\JournalEntryDetail::create($row + ['journal_entry_id' => $entry->id, 'currency' => $note->currency]);
        }

        return $entry->id;
    }

    private function postCreditNoteCogsJournalEntry(CreditNotification $note, ?Invoice $original): void
    {
        $items = InvoiceItem::where('credit_note_id', $note->id)->with('product')->get()->filter(fn ($i) => $i->product && StockEligibility::isStockable($i->product));
        if ($items->isEmpty()) {
            return;
        }

        $costByStore = [];
        $costTotal = 0.0;
        foreach ($items as $item) {
            $unitCost = (float) ($item->unit_cost_at_sale ?: ($original ? $this->returnUnitCost($original->id, $item->product) : ($this->averageUnitCost($item->product) ?? 0.0)));
            $lineCost = round($unitCost * (float) $item->quantity_in_base_unit, 2);
            $costTotal += $lineCost;
            $costByStore[$item->store_house_id] = ($costByStore[$item->store_house_id] ?? 0) + $lineCost;
        }
        if ($costTotal <= 0) {
            return;
        }

        $entry = \App\Models\JournalEntry::create([
            'date' => $note->credit_date, 'description' => 'تكلفة إشعار دائن — '.$note->credit_number,
            'status' => true, 'currency' => $note->currency,
            'client_id' => $note->client_id, 'employee_id' => $note->employee_id,
            'reference_type' => ReferenceType::CREDIT_NOTE->value, 'reference_number' => $note->id,
            'branch_id' => $note->branch_id, 'is_posted' => true, 'linking_type' => LinkingType::SINGLE->value,
        ]);

        foreach ($costByStore as $storeHouseId => $amount) {
            $inventoryAccount = Account::where('storehouse_id', $storeHouseId)->first() ?? $this->requireAccountingAccount('inventory');
            \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $inventoryAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'المخزون', 'currency' => $note->currency]);
        }
        \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $this->requireAccountingAccount('cost_of_sales')->id, 'debit' => 0, 'credit' => $costTotal, 'description' => 'إلغاء تكلفة المبيعات', 'currency' => $note->currency]);
    }

    private function notePayload(CreditNotification $note): array
    {
        return [
            'id' => $note->id,
            'credit_number' => $note->credit_number,
            'invoice_id' => $note->invoice_id,
            'client' => $note->client ? ['id' => $note->client->id, 'trade_name' => $note->client->trade_name] : null,
            'subtotal' => (float) $note->subtotal,
            'discount_amount' => (float) $note->discount_amount,
            'tax_total' => (float) $note->total_tax,
            'shipping_cost' => (float) $note->shipping_cost,
            'grand_total' => (float) $note->grand_total,
            'status' => $note->status,
            'items' => $note->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'product_name' => $i->product->name ?? null,
                'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price, 'total' => (float) $i->total,
            ]),
            'print_url' => route('api.integration.credit-notes.print', $note->id),
            'created_at' => $note->created_at?->toDateTimeString(),
        ];
    }
}
