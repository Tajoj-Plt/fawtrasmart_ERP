<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\SalesPlanFeatures;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentsProcess;
use App\Models\ProductDetails;
use App\Models\Treasury;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
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

/**
 * مرتجع المبيعات في تطبيق الجوال — إضافةٌ وعرضٌ وطباعة، نفس قواعد شاشة
 * الموقع (`Modules\Sales\Http\Controllers\ReturnInvoiceController`):
 * مرتجعٌ واحدٌ للفاتورة («المرتجع والإشعار الدائن رَدٌّ واحد» —
 * `Invoice::returnBlockReason()`)، حدود `ReturnLimits` (الكمية والسعر لا
 * يتجاوزان ما بِيع فعلاً)، عودة البضاعة إلى مستودع بند الفاتورة الأصلية
 * لا مستودعٍ من الطلب، وتكلفةُ المرتجع مجمَّدةٌ من بند البيع الأصلي.
 *
 * لا تعديل ولا حذف: قواعد الفوترة الإلكترونية تمنعهما — كما في الموقع.
 */
class SalesReturnController extends Controller
{
    use ChecksSubscriptionLimits;
    use GeneratesDocumentNumbers;
    use RendersSalesDocumentTemplates;
    use ResolvesAccountingAccounts;
    use ResolvesSaleCost;
    use RespectsAccountingPeriods;
    use ScopesSalesVisibility;
    use UsesDocumentCurrency;

    private const NUMBER_SECTION = 'return-invoice';

    public function __construct()
    {
        $this->middleware(function (Request $request, \Closure $next) {
            if (! ApplicationRegistry::isAvailable('sales')) {
                return response()->json(['success' => false, 'message' => __('settings.applications.blocked', ['module' => ApplicationRegistry::labelFor('sales')])], 403);
            }

            return $next($request);
        });
    }

    /**
     * قائمة المرتجعات — نفس فلاتر ونطاق شاشة فهرس المرتجعات في الموقع.
     *
     * الاستعلامات المقبولة: client_id, invoice_number (رمز المرتجع أو
     * معرّفه), reference_number (رمز أو معرّف الفاتورة الأصلية), branch,
     * total_from/total_to, from_date/to_date (الافتراضي: الفترة المحاسبية
     * الجارية)، added_by_employee, sales_person_user, per_page، page.
     *
     * ✅ صلاحية العرض محروسة في المسار (permission.role:sales.returns.view)
     */
    public function index(Request $request): JsonResponse
    {
        $range = $this->reportDateRange($request);

        $query = Invoice::with(['client', 'items.product'])->where('type', 'returned');

        $this->scopeSalesDocuments($query);
        $this->applyHeaderBranchScope($query);

        $this->applyIndexFilters($query, $request, $range);

        $returns = $query->orderByDesc('created_at')
            ->paginate($this->resolvePerPage($request))
            ->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => $returns->getCollection()->map(fn ($r) => $this->returnPayload($r)),
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ],
        ]);
    }

    /**
     * الحقول: invoice_id (الفاتورة الأصلية), items[] { product_id,
     * product_unit_id?, quantity, unit_price }, treasury_id?,
     * return_amount_option? ('return' لإعادة النقد، وإلّا بلا ردّ نقدي),
     * discount_amount?, discount_type?, shipping_cost?, shipping_tax_id?.
     */
    // ✅ صلاحية الإنشاء محروسة في المسار (permission.role:sales.returns.create)
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer',
                'items.*.product_unit_id' => 'nullable',
                'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'treasury_id' => 'nullable|integer|exists:treasuries,id',
                'return_amount_option' => 'nullable|in:return,none',
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:amount,percentage',
                'shipping_cost' => 'nullable|numeric|min:0',
                'shipping_tax_id' => 'nullable|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        }

        $original = Invoice::with('items')->find($validated['invoice_id']);
        if (! $original) {
            return response()->json(['success' => false, 'message' => 'الفاتورة الأصلية غير موجودة.'], 404);
        }

        $user = $request->user();
        if (! $this->canAccessSalesDocument($original->created_by, $original->employee_id, $original->branch_id)) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية الوصول إلى هذه الفاتورة.'], 403);
        }

        if ($blocked = $original->returnBlockReason()) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::RETURN_INVOICES)) {
            return response()->json(['success' => false, 'message' => $blocked], 402);
        }

        // ═══ تحقّق حدود المرتجع — قبل أي كتابة ═══
        $limitsInput = [];
        foreach ($validated['items'] as $row) {
            $line = ReturnLimits::for($original)->lineFor((int) $row['product_id'], $row['product_unit_id'] ?? null);
            $limitsInput[] = [
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'factor' => $line ? ReturnLimits::factorOf($line) : 1.0,
            ];
        }

        if ($violation = ReturnLimits::for($original)->violation($limitsInput)) {
            return response()->json(['success' => false, 'message' => $violation], 422);
        }

        $documentDate = now()->toDateString();
        if ($blocked = $this->accountingPeriodBlock($documentDate)) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        // ═══ تجهيز الأسطر ومطابقتها ببند الفاتورة الأصلية (المستودع والوحدة والتكلفة) ═══
        $lines = [];
        foreach ($validated['items'] as $row) {
            $originalLine = ReturnLimits::for($original)->lineFor((int) $row['product_id'], $row['product_unit_id'] ?? null);
            if (! $originalLine) {
                return response()->json(['success' => false, 'message' => 'أحد الأصناف ليس ضمن بنود الفاتورة الأصلية.'], 422);
            }

            $factor = ReturnLimits::factorOf($originalLine);
            $quantity = (float) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];
            $itemTotal = round($quantity * $unitPrice, 2);
            $quantityInBaseUnit = $quantity * $factor;

            $lines[] = [
                'original_item' => $originalLine,
                'product_id' => $row['product_id'],
                'product_unit_id' => $originalLine->product_unit_id,
                'store_house_id' => $originalLine->store_house_id,
                'unit_name' => $originalLine->unit_name,
                'conversion_factor' => $factor,
                'quantity' => $quantity,
                'quantity_in_base_unit' => $quantityInBaseUnit,
                'unit_price' => $unitPrice,
                'item_total' => $itemTotal,
                'net' => $itemTotal,
                'tax_1_id' => $originalLine->tax_1_id,
                'tax_2_id' => $originalLine->tax_2_id,
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
            $includedInLine = $taxLine['base'] - $taxLine['net'];
            $line['total'] = round($line['net'] - $includedInLine, 2);
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

            $return = Invoice::create([
                'client_id' => $original->client_id,
                'branch_id' => $original->branch_id,
                'employee_id' => $original->employee_id,
                'created_by' => $user->id,
                'code' => $code,
                'type' => 'returned',
                'reference_number' => (string) $original->id,
                'reference_id' => $original->id,
                'invoice_date' => $documentDate,
                'issue_date' => $documentDate,
                'subtotal' => $subtotal,
                'discount_amount' => $documentDiscount,
                'discount_type' => $docDiscountType === 'percentage' ? 2 : 1,
                'tax_total' => $taxTotal,
                'shipping_cost' => $shippingCost,
                'shipping_tax' => $shippingTax,
                'grand_total' => $grandTotal,
                'payment_status' => 1,
                'is_paid' => true,
            ]);

            foreach ($lines as $line) {
                $item = InvoiceItem::create([
                    'invoice_id' => $return->id,
                    'product_id' => $line['product_id'],
                    'product_unit_id' => $line['product_unit_id'],
                    'store_house_id' => $line['store_house_id'],
                    'item' => $line['original_item']->product->name ?? $line['original_item']->item,
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

                $product = $line['original_item']->product;
                if ($product && StockEligibility::isStockable($product)) {
                    $this->restockLine($return, $line, $user);
                    $unitCost = $this->returnUnitCost($original->id, $product);
                    InvoiceItem::whereKey($item->id)->update(['unit_cost_at_sale' => $unitCost]);
                }
            }

            $this->postReturnJournalEntry($return, $subtotal, $documentDiscount, $itemsTax, $shippingTax, $shippingCost, $grandTotal);
            $this->postReturnCogsJournalEntry($return, $original);

            $refunded = 0.0;
            if (($validated['return_amount_option'] ?? 'return') === 'return' && $original->payments()->exists()) {
                $refunded = $this->refundTreasury($return, $grandTotal, $original, $validated['treasury_id'] ?? null, $user);
            }

            // لا `increment()`: حسابُها SQL خامّ (`column = column + amount`)،
            // وNULL + رقمٌ في SQL يبقى NULL — والعمود بلا قيمةٍ افتراضية.
            $original->returned_payment = (float) ($original->returned_payment ?? 0) + $grandTotal;
            $original->save();

            OperationLog::record('created', 'SalesReturns', $return, 'إنشاء مرتجع «'.$return->code.'» للفاتورة «'.$original->code.'» — عبر تطبيق الجوال');

            try {
                app(PointsService::class)->processAction('return_caused', [
                    'employee_id' => $original->employee_id, 'client_id' => $original->client_id,
                    'amount' => $grandTotal, 'reference_type' => Invoice::class, 'reference_id' => $return->id,
                ]);
            } catch (\Throwable $e) {
                OperationLog::failure($e, 'SalesReturns', 'points_on_return', $return);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'SalesReturns', 'integration.store');

            return response()->json(['success' => false, 'message' => 'تعذّر حفظ المرتجع: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '✨ تم إنشاء المرتجع بنجاح!',
            'data' => $this->returnPayload($return->fresh(['client', 'items.product'])) + ['refunded_cash' => round($refunded, 2)],
        ], 201);
    }

    // ✅ صلاحية العرض محروسة في المسار (permission.role:sales.returns.view)
    public function show(Request $request, int $id): JsonResponse
    {
        $return = Invoice::with(['client', 'items.product', 'referenceInvoice'])->where('type', 'returned')->find($id);
        if (! $return) {
            return response()->json(['success' => false, 'message' => 'المرتجع غير موجود.'], 404);
        }

        if (! $this->canAccessSalesDocument($return->created_by, $return->employee_id, $return->branch_id)) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية عرض هذا المرتجع.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->returnPayload($return)]);
    }

    // ✅ صلاحية الطباعة محروسة في المسار (permission.role:sales.returns.print)
    public function print(Request $request, int $id): \Illuminate\Http\Response
    {
        $return = Invoice::where('type', 'returned')->findOrFail($id);
        abort_unless($this->canAccessSalesDocument($return->created_by, $return->employee_id, $return->branch_id), 403, 'لا تملك صلاحية عرض هذا المرتجع.');

        $template = $request->input('template');
        $html = $this->wantsBuiltinPrint($template) ? null : $this->renderSalesTemplate('sales_return', $id, $template);

        if ($html) {
            OperationLog::record('printed', 'SalesReturns', $return, 'طباعة المرتجع «'.$return->code.'» — عبر تطبيق الجوال');

            return response(DocumentPrintPage::wrap($html));
        }

        return response()->view('sales::retend_invoice.pdf', ['invoice' => $return->load(['client', 'items.product'])]);
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** شروط بحث الفهرس — نفس applySearchFilters() في ReturnInvoiceController (الموقع)، دون فلتر العملة. */
    private function applyIndexFilters($query, Request $request, array $range): void
    {
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('invoice_number')) {
            $term = trim((string) $request->invoice_number);
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term.'%');
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('reference_number')) {
            $term = trim((string) $request->reference_number);
            $query->where(function ($q) use ($term) {
                $q->where('reference_number', $term)
                    ->orWhereIn('reference_number', Invoice::query()
                        ->where('type', '!=', 'returned')
                        ->where('code', 'like', '%'.$term.'%')
                        ->selectRaw('CAST(id AS CHAR)'));
            });
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

        $query->whereBetween('invoices.invoice_date', [
            \Carbon\Carbon::parse($range['start'])->toDateString(),
            \Carbon\Carbon::parse($range['end'])->toDateString(),
        ]);

        $this->excludeClosedRanges($query, 'invoices.invoice_date');

        if ($request->filled('added_by_employee')) {
            $query->where('created_by', $request->added_by_employee);
        }

        if ($request->filled('sales_person_user')) {
            $employeeId = (int) $request->sales_person_user;
            $query->where(function ($q) use ($employeeId) {
                $q->where('invoices.employee_id', $employeeId)
                    ->orWhereIn('invoices.reference_number', Invoice::query()
                        ->where('type', '!=', 'returned')
                        ->where('employee_id', $employeeId)
                        ->selectRaw('CAST(id AS CHAR)'));
            });
        }
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    private function restockLine(Invoice $return, array $line, $user): void
    {
        $productDetails = ProductDetails::firstOrCreate(
            ['product_id' => $line['product_id'], 'store_house_id' => $line['store_house_id']],
            ['quantity' => 0]
        );
        $stockBefore = $productDetails->quantity;
        $productDetails->increment('quantity', $line['quantity_in_base_unit']);
        $stockAfter = $stockBefore + $line['quantity_in_base_unit'];

        $permit = WarehousePermits::create([
            'permission_type' => 2,
            'permission_date' => $return->invoice_date,
            'reference_type' => 'SalesReturn',
            'reference_id' => $return->id,
            'permission_source_id' => PermissionSources::SALES_RETURN,
            'client_id' => $return->client_id,
            'number' => $return->code,
            'store_houses_id' => $line['store_house_id'],
            'grand_total' => $line['total'],
            'currency' => $this->documentCurrency($return),
            'created_by' => $user->id,
            'status' => 'apporod',
        ]);

        $inventoryLine = WarehousePermitsProducts::inventoryLineFromInvoiceItem([
            'quantity_in_base_unit' => $line['quantity_in_base_unit'], 'total' => $line['total'],
            'unit_price' => $line['unit_price'], 'conversion_factor' => $line['conversion_factor'],
        ]);

        WarehousePermitsProducts::create([
            'warehouse_permits_id' => $permit->id, 'product_id' => $line['product_id'],
            'quantity' => $inventoryLine['quantity'], 'unit_price' => $inventoryLine['unit_price'], 'total' => $inventoryLine['total'],
            'stock_before' => $stockBefore, 'stock_after' => $stockAfter, 'store_house_id' => $line['store_house_id'],
        ]);
    }

    private function postReturnJournalEntry(Invoice $return, float $subtotal, float $discount, float $itemsTax, float $shippingTax, float $shippingCost, float $grandTotal): void
    {
        $clientAccount = Account::ledger()->where('client_id', $return->client_id)->first();
        if (! $clientAccount) {
            throw new \RuntimeException('لا يوجد حسابٌ محاسبيّ لهذا العميل.');
        }

        $entry = \App\Models\JournalEntry::create([
            'date' => $return->invoice_date, 'description' => 'مرتجع مبيعات رقم '.$return->code,
            'status' => true, 'currency' => $this->documentCurrency($return),
            'client_id' => $return->client_id, 'employee_id' => $return->employee_id, 'invoice_id' => $return->id,
            'reference_type' => ReferenceType::SALES_RETURN->value, 'reference_number' => $return->id,
            'branch_id' => $return->branch_id, 'is_posted' => true, 'linking_type' => LinkingType::SINGLE->value,
        ]);

        $rows = [];
        $rows[] = ['account_id' => $this->requireAccountingAccount('sales_returns')->id, 'debit' => $subtotal, 'credit' => 0, 'description' => 'مردودات مبيعات'];
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
        $rows[] = ['account_id' => $clientAccount->id, 'debit' => 0, 'credit' => $grandTotal, 'description' => 'دائن العميل — مرتجع '.$return->code];

        foreach ($rows as $row) {
            \App\Models\JournalEntryDetail::create($row + ['journal_entry_id' => $entry->id, 'currency' => $this->documentCurrency($return)]);
        }
    }

    private function postReturnCogsJournalEntry(Invoice $return, Invoice $original): void
    {
        $items = $return->items()->with('product')->get()->filter(fn ($i) => $i->product && StockEligibility::isStockable($i->product));
        if ($items->isEmpty()) {
            return;
        }

        $costByStore = [];
        $costTotal = 0.0;
        foreach ($items as $item) {
            $unitCost = (float) ($item->unit_cost_at_sale ?: $this->returnUnitCost($original->id, $item->product));
            $lineCost = round($unitCost * (float) $item->quantity_in_base_unit, 2);
            $costTotal += $lineCost;
            $costByStore[$item->store_house_id] = ($costByStore[$item->store_house_id] ?? 0) + $lineCost;
        }
        if ($costTotal <= 0) {
            return;
        }

        $entry = \App\Models\JournalEntry::create([
            'date' => $return->invoice_date, 'description' => 'تكلفة مرتجع — '.$return->code,
            'status' => true, 'currency' => $this->documentCurrency($return),
            'client_id' => $return->client_id, 'employee_id' => $return->employee_id, 'invoice_id' => $return->id,
            'reference_type' => ReferenceType::SALES_RETURN->value, 'reference_number' => $return->id,
            'branch_id' => $return->branch_id, 'is_posted' => true, 'linking_type' => LinkingType::SINGLE->value,
        ]);

        foreach ($costByStore as $storeHouseId => $amount) {
            $inventoryAccount = Account::where('storehouse_id', $storeHouseId)->first() ?? $this->requireAccountingAccount('inventory');
            \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $inventoryAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'المخزون', 'currency' => $this->documentCurrency($return)]);
        }
        \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $this->requireAccountingAccount('cost_of_sales')->id, 'debit' => 0, 'credit' => $costTotal, 'description' => 'إلغاء تكلفة المبيعات', 'currency' => $this->documentCurrency($return)]);
    }

    private function refundTreasury(Invoice $return, float $grandTotal, Invoice $original, ?int $treasuryId, $user): float
    {
        $paid = (float) $original->payments()->sum('amount');
        $refund = min($grandTotal, $paid);
        if ($refund <= 0) {
            return 0.0;
        }

        $treasury = $treasuryId ? Treasury::find($treasuryId) : null;
        $treasuryAccountId = $treasury?->account_id ?? $this->requireAccountingAccountId('main_treasury');

        $treasuryAccount = Account::find($treasuryAccountId);
        if ($treasuryAccount && (float) $treasuryAccount->balance < $refund) {
            throw new \RuntimeException('رصيد الخزينة لا يكفي لردّ هذا المبلغ نقداً.');
        }

        $clientAccount = Account::ledger()->where('client_id', $return->client_id)->first();

        $entry = \App\Models\JournalEntry::create([
            'date' => $return->invoice_date, 'description' => 'ردّ نقدي — مرتجع '.$return->code,
            'status' => true, 'currency' => $this->documentCurrency($return),
            'client_id' => $return->client_id, 'invoice_id' => $return->id,
            'reference_type' => ReferenceType::SALES_RETURN->value, 'reference_number' => $return->id,
            'branch_id' => $return->branch_id, 'is_posted' => true, 'linking_type' => LinkingType::SINGLE->value,
        ]);
        \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $clientAccount->id, 'debit' => $refund, 'credit' => 0, 'description' => 'ردّ نقدي', 'currency' => $this->documentCurrency($return)]);
        \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $treasuryAccountId, 'debit' => 0, 'credit' => $refund, 'description' => 'ردّ نقدي للعميل', 'currency' => $this->documentCurrency($return)]);

        PaymentsProcess::create([
            'invoice_id' => $return->id, 'client_id' => $return->client_id, 'employee_id' => $user->id,
            'treasury_id' => $treasuryId, 'amount' => $refund, 'type' => 'treasury_return',
            /* رقم المرتجع نفسه، كما تفعل شاشة المرتجع. كان يُطلَب رقمٌ من قسمٍ
               لا وجود له (`return-invoice-refund`)، فيُحوَّل إلى القسم
               الافتراضي — «فاتورة مبيعات» — ويُستهلَك **رقم فاتورة** لسند ردٍّ
               لا يظهر في الفواتير: ثغرةٌ دائمة في تسلسلها مع كلّ ردّ. */
            'reference_number' => $return->code,
            'payment_date' => $return->invoice_date,
        ]);

        return $refund;
    }

    private function returnPayload(Invoice $return): array
    {
        return [
            'id' => $return->id,
            'code' => $return->code,
            'original_invoice_id' => $return->reference_id,
            'client' => $return->client ? ['id' => $return->client->id, 'trade_name' => $return->client->trade_name] : null,
            'subtotal' => (float) $return->subtotal,
            'discount_amount' => (float) $return->discount_amount,
            'tax_total' => (float) $return->tax_total,
            'shipping_cost' => (float) $return->shipping_cost,
            'grand_total' => (float) $return->grand_total,
            'items' => $return->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'product_name' => $i->product->name ?? null,
                'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price, 'total' => (float) $i->total,
            ]),
            'print_url' => route('api.integration.returns.print', $return->id),
            'created_at' => $return->created_at?->toDateTimeString(),
        ];
    }
}
