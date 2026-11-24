<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\SalesPlanFeatures;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\CreditLimit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentsProcess;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\ClientAccessService;
use App\Services\PointsService;
use App\Services\Settings\ApplicationRegistry;
use App\Services\Tax\DocumentTaxCalculator;
use App\Support\DataScope;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\StockEligibility;
use App\Support\StorehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Controllers\Concerns\ChecksClientProximity;
use Modules\Sales\Http\Controllers\Concerns\ChecksSubscriptionLimits;
use Modules\Sales\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Sales\Http\Controllers\Concerns\RendersSalesDocumentTemplates;
use Modules\Sales\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Sales\Http\Controllers\Concerns\ResolvesSaleCost;
use Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Sales\Http\Controllers\Concerns\ScopesSalesVisibility;
use Modules\Sales\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Sales\Support\SalesWarehouse;

/**
 * فاتورة المبيعات في تطبيق الجوال — إضافةٌ وعرضٌ وطباعة، بنفس القواعد
 * الحرفية لشاشة الموقع (`Modules\Sales\Http\Controllers\InvoicesController`):
 * سلسلة الحسابات (الخصم العام قبل الوعاء الضريبي)، خصم السطر بحقلين
 * (قيمة/نوع)، خصمُ المخزون بالوحدة الأساسية، تكلفة البيع بالمتوسّط
 * المرجَّح لا سعر الشراء، وقيدان محاسبيّان (البيع + تكلفة المبيعات).
 *
 * **مُستبعَدٌ عمداً من هذا الإصدار** (تعقيدٌ إضافي لم يُطلب صراحةً؛ راجع
 * SALES_API.md): كوبونات الخصم، عروض الخصم التلقائية، التسديد الآلي من
 * رصيد العميل الدائن أو من المحفظة، override الحالة اليدوي (منشأةٌ تسمح
 * بحالة «مدفوعة» يدويّاً)، ومسار «التوقيع = دفعة» (`storeSignatures`) —
 * تحصيل الدفعات له مساره الخاص في `SalesPaymentController` ولم يُبنَ هنا.
 * كلُّ ما عداها — الحسابات والمخزون والصلاحيات والحدود الائتمانية —
 * منفَّذٌ بالحرف.
 */
class InvoiceController extends Controller
{
    use ChecksClientProximity;
    use ChecksSubscriptionLimits;
    use GeneratesDocumentNumbers;
    use RendersSalesDocumentTemplates;
    use ResolvesAccountingAccounts;
    use ResolvesSaleCost;
    use RespectsAccountingPeriods;
    use ScopesSalesVisibility;
    use UsesDocumentCurrency;

    private const NUMBER_SECTION = 'invoice';

    public function __construct(private readonly ClientAccessService $clientAccess)
    {
        $this->middleware(function (Request $request, \Closure $next) {
            if (! ApplicationRegistry::isAvailable('sales')) {
                return response()->json([
                    'success' => false,
                    'message' => __('settings.applications.blocked', ['module' => ApplicationRegistry::labelFor('sales')]),
                ], 403);
            }

            return $next($request);
        });
    }

    /**
     * قائمة الفواتير — نفس فلاتر ونطاق شاشة فهرس الفواتير في الموقع.
     *
     * الاستعلامات المقبولة: client_id, invoice_number (الرقم أو المعرّف),
     * branch, payment_status (1=مدفوعة 2=جزئية 3=غير مدفوعة), item (اسم
     * صنفٍ في البنود), total_from/total_to, from_date/to_date (الافتراضي:
     * الفترة المحاسبية الجارية، وتُستبعد الفتراتُ المقفلة دائماً),
     * added_by_employee, sales_person_user, per_page (10|25|50|100)، page.
     *
     * ✅ صلاحية العرض محروسة في المسار (permission.role:sales.invoices.view)
     */
    public function index(Request $request): JsonResponse
    {
        $range = $this->reportDateRange($request);

        $query = Invoice::with(['client', 'employee', 'items.product'])
            ->whereIn('type', ['normal', 'pos', 'online']);

        $this->scopeSalesDocuments($query);
        $this->applyHeaderBranchScope($query);

        $this->applyIndexFilters($query, $request, $range);

        $invoices = $query->orderByDesc('created_at')
            ->paginate($this->resolvePerPage($request))
            ->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => $invoices->getCollection()->map(fn ($i) => $this->invoicePayload($i)),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * الحقول: client_id, invoice_date?, store_house_id?, treasury_id?,
     * terms?, notes?, advance_payment?, shipping_cost?, shipping_tax_id?,
     * discount_amount?, discount_type? (amount|percentage),
     * current_latitude/current_longitude (بحسب صلاحية تجاوز الموقع),
     * items[] { product_id, product_unit_id?, store_house_id?, quantity,
     *           unit_price, discount?, discount_type? (amount|percentage),
     *           tax_1_id?, tax_2_id? }.
     */
    // ✅ صلاحية الإنشاء محروسة في المسار (permission.role:sales.invoices.create)
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'client_id' => 'required|integer|exists:clients,id',
                'invoice_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'store_house_id' => 'nullable|integer|exists:store_houses,id',
                'treasury_id' => 'nullable|integer|exists:treasuries,id',
                'terms' => 'nullable|integer|min:0',
                'advance_payment' => 'nullable|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'shipping_tax_id' => 'nullable|integer',
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:amount,percentage',
                'current_latitude' => 'nullable|numeric|between:-90,90',
                'current_longitude' => 'nullable|numeric|between:-180,180',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.product_unit_id' => 'nullable',
                'items.*.store_house_id' => 'nullable|integer|exists:store_houses,id',
                'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
                'items.*.discount_type' => 'nullable|in:amount,percentage',
                'items.*.tax_1_id' => 'nullable|integer',
                'items.*.tax_2_id' => 'nullable|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        }

        if (! $this->clientAccess->canAccessClient($user, (int) $validated['client_id'])) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية الوصول إلى هذا العميل.'], 403);
        }

        $documentDate = $validated['invoice_date'] ?? now()->toDateString();

        if ($blocked = $this->accountingPeriodBlock($documentDate)) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::INVOICES)) {
            return response()->json(['success' => false, 'message' => $blocked], 402);
        }

        if ($blocked = $this->clientProximityBlock($request, $validated['client_id'], 'sales.invoices.bypass_location')) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        $employeeId = DataScope::clampEmployee($user->employee_id);

        $storeHouse = SalesWarehouse::resolve($validated['store_house_id'] ?? null, $user);
        if ($storeHouse && ! StorehouseAccess::canCreateInvoices($storeHouse, $user)) {
            return response()->json(['success' => false, 'message' => StorehouseAccess::message($storeHouse)], 403);
        }

        $products = Product::with('productUnits.categoryUnit.unit')
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()->keyBy('id');

        $settings = \App\Models\SalesInvoiceSetting::current();

        // ═══ تجهيز الأسطر: الوحدة، خصم السطر بحقلين، ما دون التكلفة، سقف الخصم ═══
        $lines = [];
        $branchIdForCaps = current_branch_id();
        $maxDiscountPercent = $settings->maxDiscountFor($branchIdForCaps);
        $maxDiscountAmount = $settings->maxDiscountAmount();

        foreach ($validated['items'] as $index => $row) {
            $product = $products->get($row['product_id']);
            if (! $product) {
                return response()->json(['success' => false, 'message' => "المنتج في السطر $index غير موجود."], 422);
            }

            $lineStoreHouse = $row['store_house_id'] ?? optional($storeHouse)->id;
            if (StockEligibility::isStockable($product) && ! $lineStoreHouse) {
                return response()->json(['success' => false, 'message' => "حدِّد المستودع للسطر $index."], 422);
            }

            [$productUnitId, $conversionFactor, $unitName] = $this->resolveLineUnit($product, $row['product_unit_id'] ?? null);

            $quantity = (float) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];
            $itemTotal = $quantity * $unitPrice;

            $discountType = ($row['discount_type'] ?? 'amount') === 'percentage' ? 'percentage' : 'amount';
            $rawDiscount = (float) ($row['discount'] ?? 0);
            $lineDiscount = $discountType === 'percentage'
                ? round($itemTotal * min($rawDiscount, 100) / 100, 2)
                : min($rawDiscount, $itemTotal);

            if ($settings->enable_max_discount) {
                $pct = $itemTotal > 0 ? ($lineDiscount / $itemTotal) * 100 : 0;
                if ($maxDiscountPercent !== null && $pct > $maxDiscountPercent + 0.01) {
                    return response()->json(['success' => false, 'message' => "خصم السطر $index يتجاوز الحدّ الأقصى المسموح ({$maxDiscountPercent}%)."], 422);
                }
                if ($maxDiscountAmount !== null && $lineDiscount > $maxDiscountAmount + 0.01) {
                    return response()->json(['success' => false, 'message' => "خصم السطر $index يتجاوز الحدّ الأقصى المسموح ($maxDiscountAmount)."], 422);
                }
            }

            $lineNet = $itemTotal - $lineDiscount;
            $quantityInBaseUnit = $quantity * $conversionFactor;

            if ($settings->no_sell_below_cost && StockEligibility::isStockable($product)) {
                $cost = $this->averageUnitCost($product) ?? 0.0;
                $costTotal = $cost * $quantityInBaseUnit;
                if ($lineNet < $costTotal - 0.001) {
                    return response()->json(['success' => false, 'message' => "سعر السطر $index أقل من التكلفة — البيع دون التكلفة ممنوعٌ في الإعدادات."], 422);
                }
            }

            $lines[] = [
                'product' => $product,
                'product_unit_id' => $productUnitId,
                'store_house_id' => $lineStoreHouse,
                'unit_name' => $unitName,
                'conversion_factor' => $conversionFactor,
                'quantity' => $quantity,
                'quantity_in_base_unit' => $quantityInBaseUnit,
                'unit_price' => $unitPrice,
                'item_total' => $itemTotal,
                'discount' => $lineDiscount,
                'discount_type' => $discountType,
                'net' => $lineNet,
                'tax_1_id' => $row['tax_1_id'] ?? null,
                'tax_2_id' => $row['tax_2_id'] ?? null,
            ];
        }

        // ═══ سلسلة الحسابات: إجمالي الأصناف → خصومات الأصناف → صافي الأصناف
        //     → الخصم العام → صافي التوريد → الشحن → الوعاء الضريبي → VAT → الإجمالي ═══
        $netItems = array_sum(array_column($lines, 'net'));
        $lineDiscountsSum = array_sum(array_column($lines, 'discount'));

        $docDiscountType = $validated['discount_type'] ?? 'amount';
        $rawDocDiscount = (float) ($validated['discount_amount'] ?? 0);
        $documentDiscount = $docDiscountType === 'percentage'
            ? min($netItems * min($rawDocDiscount, 100) / 100, $netItems)
            : min($rawDocDiscount, $netItems);

        if ($settings->enable_max_discount) {
            $pct = $netItems > 0 ? ($documentDiscount / $netItems) * 100 : 0;
            if ($maxDiscountPercent !== null && $pct > $maxDiscountPercent + 0.01) {
                return response()->json(['success' => false, 'message' => "الخصم العام يتجاوز الحدّ الأقصى المسموح ({$maxDiscountPercent}%)."], 422);
            }
            if ($maxDiscountAmount !== null && $documentDiscount > $maxDiscountAmount + 0.01) {
                return response()->json(['success' => false, 'message' => "الخصم العام يتجاوز الحدّ الأقصى المسموح ($maxDiscountAmount)."], 422);
            }
        }

        $lineNets = array_column($lines, 'net');
        $discountShares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $documentDiscount);

        $itemsTax = 0.0;
        foreach ($lines as $i => &$line) {
            // الوعاء الضريبي = صافي السطر بعد حصّته من الخصم العام — الخصم يُنقِص الضريبة
            $taxableBase = max(0, $line['net'] - ($discountShares[$i] ?? 0));
            $taxLine = DocumentTaxCalculator::line(1, $taxableBase, 0, array_filter([$line['tax_1_id'], $line['tax_2_id']]), []);
            $line['line_tax'] = $taxLine['tax'];
            $itemsTax += $taxLine['tax'];

            // إجمالي السطر المخزَّن على invoice_items.total: صافي السطر بخصم
            // سطره وحده (لا حصّة الخصم العام) ناقص الضريبة المتضمَّنة فيه —
            // الخصم العام يبقى في discount_amount على مستوى المستند
            $includedInLine = $taxLine['base'] - $taxLine['net'];
            $line['total'] = round($line['net'] - $includedInLine, 2);
        }
        unset($line);

        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
        $shippingTaxIds = DocumentTaxCalculator::shippingTaxIds((bool) ($validated['shipping_tax_id'] ?? false), $validated['shipping_tax_id'] ?? null, []);
        $shippingTax = DocumentTaxCalculator::shippingTax($shippingCost, $shippingTaxIds);

        $subtotal = array_sum(array_column($lines, 'total')); // ex-included-tax subtotal
        $totalDiscount = $lineDiscountsSum + $documentDiscount;
        $taxTotal = $itemsTax + $shippingTax;
        $amountAfterDiscount = $netItems - $documentDiscount;
        $grandTotal = round($amountAfterDiscount + $taxTotal + $shippingCost, 2);

        $advancePayment = round((float) ($validated['advance_payment'] ?? 0), 2);
        if ($advancePayment > $grandTotal + 0.01) {
            return response()->json(['success' => false, 'message' => 'المبلغ المقدَّم أكبر من إجمالي الفاتورة.'], 422);
        }
        $dueValue = round($grandTotal - $advancePayment, 2);
        $paymentStatus = $dueValue <= 0.01 ? 1 : ($advancePayment > 0 ? 2 : 3); // 1=paid 2=partial 3=unpaid
        $isPaid = $paymentStatus === 1;

        // ═══ الحدّ الائتماني — فقط إن بقي على الفاتورة مبلغٌ غير مسدَّد ═══
        if ($dueValue > 0.01) {
            $client = Client::find($validated['client_id']);
            $clientLimit = CreditLimit::getClientCreditLimit($validated['client_id']);
            if ($clientLimit !== null && $clientLimit > 0) {
                $currentDebt = (float) Account::ledger()->where('client_id', $validated['client_id'])->sum('balance');
                if ($currentDebt + $dueValue > $clientLimit + 0.01) {
                    return response()->json(['success' => false, 'message' => 'تجاوز العميل حدَّه الائتماني.'], 422);
                }
            } elseif (! hasPermission('Issue_an_invoice_to_a_customer_who_has_a_debt')) {
                $currentDebt = (float) Account::ledger()->where('client_id', $validated['client_id'])->sum('balance');
                if ($currentDebt > 0) {
                    return response()->json(['success' => false, 'message' => 'على العميل مديونيةٌ سابقة ولا تملك صلاحية إصدار فاتورةٍ لعميلٍ مدين.'], 403);
                }
            }
        }

        DB::beginTransaction();

        try {
            $code = $this->resolveDocumentNumber(null, self::NUMBER_SECTION);

            $invoice = Invoice::create([
                'client_id' => $validated['client_id'],
                'branch_id' => current_branch_id(),
                'employee_id' => $employeeId,
                'created_by' => $user->id,
                'code' => $code,
                'invoice_date' => $documentDate,
                'issue_date' => $documentDate,
                'payment_terms' => ($validated['terms'] ?? 0) > 0 ? (string) $validated['terms'] : null,
                'notes' => $validated['notes'] ?? null,
                'type' => 'normal',
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'discount_type' => $docDiscountType === 'percentage' ? 2 : 1,
                'tax_total' => $taxTotal,
                'shipping_cost' => $shippingCost,
                'shipping_tax' => $shippingTax,
                'grand_total' => $grandTotal,
                'advance_payment' => $advancePayment,
                'due_value' => $dueValue,
                'payment_status' => $paymentStatus,
                'is_paid' => $isPaid,
            ]);

            foreach ($lines as $line) {
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $line['product']->id,
                    'product_unit_id' => $line['product_unit_id'],
                    'store_house_id' => $line['store_house_id'],
                    'item' => $line['product']->name ?? null,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'quantity_in_base_unit' => $line['quantity_in_base_unit'],
                    'conversion_factor' => $line['conversion_factor'],
                    'unit_name' => $line['unit_name'],
                    'discount' => $line['discount'],
                    'discount_type' => $line['discount_type'] === 'percentage' ? 2 : 1,
                    'tax_1' => $line['tax_1_id'] ? DocumentTaxCalculator::rate($line['tax_1_id']) : 0,
                    'tax_1_id' => $line['tax_1_id'],
                    'tax_2' => $line['tax_2_id'] ? DocumentTaxCalculator::rate($line['tax_2_id']) : 0,
                    'tax_2_id' => $line['tax_2_id'],
                    'total' => $line['total'],
                ]);

                if (StockEligibility::isStockable($line['product'])) {
                    $this->deductStock($invoice, $item, $line, $user);
                    $unitCost = $this->itemUnitCost($item, $line['product']);
                    $this->freezeLineCost($item, $unitCost);
                }
            }

            $this->postSalesJournalEntry($invoice, $subtotal, $totalDiscount, $itemsTax, $shippingTax, $shippingCost, $grandTotal);
            $this->postCogsJournalEntry($invoice);

            if ($advancePayment > 0) {
                $this->postCashReceiptJournalEntry($invoice, $advancePayment, $validated['treasury_id'] ?? null, $user);
            }

            OperationLog::record('created', 'SalesInvoices', $invoice, 'إنشاء فاتورة «'.$invoice->code.'» — عبر تطبيق الجوال', [
                'new' => $invoice->only(['code', 'client_id', 'grand_total', 'payment_status']),
            ]);

            try {
                \App\Models\EmployeeClientVisit::markCompletedFor((int) $employeeId, (int) $validated['client_id'], now());
                app(PointsService::class)->processAction('invoice_created', [
                    'employee_id' => $employeeId, 'client_id' => $validated['client_id'],
                    'amount' => $grandTotal, 'reference_type' => Invoice::class, 'reference_id' => $invoice->id,
                ]);
                if ($isPaid) {
                    app(PointsService::class)->processAction('cash_sale', [
                        'employee_id' => $employeeId, 'client_id' => $validated['client_id'],
                        'amount' => $grandTotal, 'reference_type' => Invoice::class, 'reference_id' => $invoice->id,
                    ]);
                }
            } catch (\Throwable $e) {
                OperationLog::failure($e, 'SalesInvoices', 'points_on_invoice', $invoice);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'SalesInvoices', 'integration.store');

            return response()->json(['success' => false, 'message' => 'تعذّر حفظ الفاتورة: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '✨ تم إنشاء الفاتورة بنجاح!',
            'data' => $this->invoicePayload($invoice->fresh(['client', 'items.product', 'employee'])),
        ], 201);
    }

    // ✅ صلاحية العرض محروسة في المسار (permission.role:sales.invoices.view)
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with(['client', 'items.product', 'employee', 'createdByUser', 'payments'])->find($id);
        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'الفاتورة غير موجودة.'], 404);
        }

        $user = $request->user();
        $canView = $this->canAccessSalesDocument($invoice->created_by, $invoice->employee_id, $invoice->branch_id)
            || (hasPermission('sales.invoices.view_assigned_clients') && $this->clientAccess->canAccessClient($user, $invoice->client_id));

        if (! $canView) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية عرض هذه الفاتورة.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->invoicePayload($invoice, detailed: true)]);
    }

    /**
     * رابط طباعة الفاتورة — نفس القالب المصمَّم إن وُجد، وإلا القالب المدمج، مغلَّفاً بصفحة طباعة كاملة (HTML، لا PDF).
     *
     * ✅ صلاحية الطباعة محروسة في المسار (permission.role:sales.invoices.print)
     */
    public function print(Request $request, int $id): \Illuminate\Http\Response
    {
        $invoice = Invoice::findOrFail($id);
        $user = $request->user();
        $canView = $this->canAccessSalesDocument($invoice->created_by, $invoice->employee_id, $invoice->branch_id)
            || (hasPermission('sales.invoices.view_assigned_clients') && $this->clientAccess->canAccessClient($user, $invoice->client_id));
        abort_unless($canView, 403, 'لا تملك صلاحية عرض هذه الفاتورة.');

        $template = $request->input('template');
        $html = $this->wantsBuiltinPrint($template) ? null : $this->renderSalesTemplate('sales_invoice', $id, $template);

        if ($html) {
            OperationLog::record('printed', 'SalesInvoices', $invoice, 'طباعة الفاتورة «'.$invoice->code.'» — عبر تطبيق الجوال');

            return response(DocumentPrintPage::wrap($html));
        }

        // لا قالبٌ مصمَّم — القالب المدمج نفسه الذي تستعمله الشاشة
        return response()->view('sales::invoices.print', ['invoice' => $invoice->load(['client', 'items.product', 'employee'])]);
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** شروط بحث الفهرس — نفس applySearchFilters() في InvoicesController (الموقع)، دون فلتر العملة (لا سيلكت عملةٍ في الجوال). */
    private function applyIndexFilters($query, Request $request, array $range): void
    {
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('invoice_number')) {
            $term = trim((string) $request->invoice_number);
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('branch')) {
            $query->forBranch($request->branch);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('item')) {
            $query->whereHas('items', fn ($q) => $q->where('item', 'like', '%'.$request->item.'%'));
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
            $query->where('employee_id', $request->sales_person_user);
        }
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    /** @return array{0: int|null, 1: float, 2: string} [product_unit_id, conversion_factor, unit_name] */
    private function resolveLineUnit(Product $product, $requestedUnitId): array
    {
        if ($requestedUnitId && ! str_starts_with((string) $requestedUnitId, 'category_')) {
            $unit = $product->productUnits->firstWhere('id', (int) $requestedUnitId);
            if ($unit) {
                return [$unit->id, (float) ($unit->conversion_factor ?: 1), $unit->categoryUnit?->unit?->name ?? ''];
            }
        }

        return [null, 1.0, $product->unit_name ?? ''];
    }

    private function deductStock(Invoice $invoice, InvoiceItem $item, array $line, $user): void
    {
        $productDetails = ProductDetails::where('product_id', $line['product']->id)
            ->where('store_house_id', $line['store_house_id'])
            ->lockForUpdate()->first();

        $available = $productDetails->quantity ?? 0;
        if ($available < $line['quantity_in_base_unit']) {
            throw new \RuntimeException("الكمية المتاحة من «{$line['product']->name}» غير كافية — المطلوب {$line['quantity_in_base_unit']}، المتاح $available.");
        }

        $stockBefore = $available;
        $productDetails->decrement('quantity', $line['quantity_in_base_unit']);
        $stockAfter = $stockBefore - $line['quantity_in_base_unit'];

        $permit = WarehousePermits::create([
            'permission_type' => 1,
            'permission_date' => $invoice->invoice_date,
            'reference_type' => 'Invoice',
            'reference_id' => $invoice->id,
            'permission_source_id' => PermissionSources::SALES_INVOICE,
            'client_id' => $invoice->client_id,
            'number' => $invoice->code,
            'store_houses_id' => $line['store_house_id'],
            'grand_total' => $line['total'],
            'currency' => $this->documentCurrency($invoice),
            'created_by' => $user->id,
            'status' => 'apporod',
        ]);

        $inventoryLine = WarehousePermitsProducts::inventoryLineFromInvoiceItem([
            'quantity_in_base_unit' => $line['quantity_in_base_unit'],
            'total' => $line['total'],
            'unit_price' => $line['unit_price'],
            'conversion_factor' => $line['conversion_factor'],
        ]);

        WarehousePermitsProducts::create([
            'warehouse_permits_id' => $permit->id,
            'product_id' => $line['product']->id,
            'quantity' => $inventoryLine['quantity'],
            'unit_price' => $inventoryLine['unit_price'],
            'total' => $inventoryLine['total'],
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'store_house_id' => $line['store_house_id'],
        ]);
    }

    private function postSalesJournalEntry(Invoice $invoice, float $subtotal, float $totalDiscount, float $itemsTax, float $shippingTax, float $shippingCost, float $grandTotal): void
    {
        $clientAccount = Account::ledger()->where('client_id', $invoice->client_id)->first();
        if (! $clientAccount) {
            throw new \RuntimeException('لا يوجد حسابٌ محاسبيّ لهذا العميل.');
        }

        $entry = \App\Models\JournalEntry::create([
            'date' => $invoice->invoice_date,
            'description' => 'فاتورة مبيعات رقم '.$invoice->code,
            'status' => true,
            'currency' => $this->documentCurrency($invoice),
            'client_id' => $invoice->client_id,
            'employee_id' => $invoice->employee_id,
            'invoice_id' => $invoice->id,
            'reference_type' => ReferenceType::SALES_INVOICE->value,
            'reference_number' => $invoice->id,
            'branch_id' => $invoice->branch_id,
            'is_posted' => true,
            'linking_type' => LinkingType::SINGLE->value,
        ]);

        $lines = [];
        $lines[] = ['account_id' => $clientAccount->id, 'debit' => $grandTotal, 'credit' => 0, 'description' => 'مدين العميل — فاتورة '.$invoice->code];
        $lines[] = ['account_id' => $this->requireAccountingAccount('sales')->id, 'debit' => 0, 'credit' => $subtotal, 'description' => 'إيراد المبيعات'];

        if ($totalDiscount > 0.005) {
            $lines[] = ['account_id' => $this->requireAccountingAccount('allowed_discount')->id, 'debit' => $totalDiscount, 'credit' => 0, 'description' => 'خصم مسموح به'];
        }
        if ($itemsTax > 0.005) {
            $lines[] = ['account_id' => $this->requireAccountingAccount('vat_payable')->id, 'debit' => 0, 'credit' => $itemsTax, 'description' => 'ضريبة القيمة المضافة'];
        }
        if ($shippingTax > 0.005) {
            $lines[] = ['account_id' => $this->shippingVatAccountId(), 'debit' => 0, 'credit' => $shippingTax, 'description' => 'ضريبة شحن'];
        }
        if ($shippingCost > 0.005) {
            $lines[] = ['account_id' => $this->requireAccountingAccount('shipping_revenue')->id, 'debit' => 0, 'credit' => $shippingCost, 'description' => 'إيراد شحن'];
        }

        foreach ($lines as $line) {
            \App\Models\JournalEntryDetail::create($entry->id ? array_merge($line, ['journal_entry_id' => $entry->id, 'currency' => $this->documentCurrency($invoice)]) : $line);
        }
    }

    private function postCogsJournalEntry(Invoice $invoice): void
    {
        $items = $invoice->items()->with('product')->get()->filter(fn ($i) => StockEligibility::isStockable($i->product));
        if ($items->isEmpty()) {
            return;
        }

        $costByStore = [];
        $costTotal = 0.0;
        foreach ($items as $item) {
            $unitCost = (float) ($item->unit_cost_at_sale ?: $this->itemUnitCost($item, $item->product));
            $lineCost = round($unitCost * (float) $item->quantity_in_base_unit, 2);
            $costTotal += $lineCost;
            $storeHouseId = $item->store_house_id;
            $costByStore[$storeHouseId] = ($costByStore[$storeHouseId] ?? 0) + $lineCost;
        }

        if ($costTotal <= 0) {
            return;
        }

        $entry = \App\Models\JournalEntry::create([
            'date' => $invoice->invoice_date,
            'description' => 'تكلفة المبيعات — فاتورة '.$invoice->code,
            'status' => true,
            'currency' => $this->documentCurrency($invoice),
            'client_id' => $invoice->client_id,
            'employee_id' => $invoice->employee_id,
            'invoice_id' => $invoice->id,
            'reference_type' => ReferenceType::SALES_INVOICE->value,
            'reference_number' => $invoice->id,
            'branch_id' => $invoice->branch_id,
            'is_posted' => true,
            'linking_type' => LinkingType::SINGLE->value,
        ]);

        \App\Models\JournalEntryDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->requireAccountingAccount('cost_of_sales')->id,
            'debit' => $costTotal, 'credit' => 0, 'description' => 'تكلفة المبيعات', 'currency' => $this->documentCurrency($invoice),
        ]);

        foreach ($costByStore as $storeHouseId => $amount) {
            $inventoryAccount = Account::where('storehouse_id', $storeHouseId)->first() ?? $this->requireAccountingAccount('inventory');
            \App\Models\JournalEntryDetail::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $inventoryAccount->id,
                'debit' => 0, 'credit' => $amount, 'description' => 'المخزون', 'currency' => $this->documentCurrency($invoice),
            ]);
        }
    }

    private function postCashReceiptJournalEntry(Invoice $invoice, float $amount, ?int $treasuryId, $user): void
    {
        $treasuryAccount = $treasuryId
            ? \App\Models\Treasury::find($treasuryId)?->account_id
            : (\App\Models\TreasuryEmployee::where('employee_id', $user->employee_id)->value('treasury_id'));
        $treasuryAccountId = is_numeric($treasuryAccount) ? (int) $treasuryAccount : (\App\Models\Treasury::find($treasuryAccount)?->account_id);
        $treasuryAccountId ??= $this->requireAccountingAccountId('main_treasury');

        $clientAccount = Account::ledger()->where('client_id', $invoice->client_id)->first();

        $entry = \App\Models\JournalEntry::create([
            'date' => $invoice->invoice_date,
            'description' => 'تحصيل نقدي عند إصدار الفاتورة '.$invoice->code,
            'status' => true,
            'currency' => $this->documentCurrency($invoice),
            'client_id' => $invoice->client_id,
            'employee_id' => $invoice->employee_id,
            'invoice_id' => $invoice->id,
            'reference_type' => ReferenceType::CUSTOMER_COLLECTION->value,
            'reference_number' => $invoice->id,
            'branch_id' => $invoice->branch_id,
            'is_posted' => true,
            'linking_type' => LinkingType::SINGLE->value,
        ]);

        \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $treasuryAccountId, 'debit' => $amount, 'credit' => 0, 'description' => 'تحصيل نقدي', 'currency' => $this->documentCurrency($invoice)]);
        \App\Models\JournalEntryDetail::create(['journal_entry_id' => $entry->id, 'account_id' => $clientAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'تحصيل نقدي من العميل', 'currency' => $this->documentCurrency($invoice)]);

        PaymentsProcess::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'employee_id' => $user->id,
            'treasury_id' => $treasuryId,
            'amount' => $amount,
            'type' => 'client payments',
            'reference_number' => $this->resolveDocumentNumber(null, \Modules\Sales\Http\Controllers\SalesPaymentController::NUMBER_SECTION),
            'payment_date' => $invoice->invoice_date,
        ]);
    }

    private function invoicePayload(Invoice $invoice, bool $detailed = false): array
    {
        return [
            'id' => $invoice->id,
            'code' => $invoice->code,
            'client' => $invoice->client ? ['id' => $invoice->client->id, 'trade_name' => $invoice->client->trade_name] : null,
            'invoice_date' => optional($invoice->invoice_date)->toDateString(),
            'subtotal' => (float) $invoice->subtotal,
            'discount_amount' => (float) $invoice->discount_amount,
            'shipping_cost' => (float) $invoice->shipping_cost,
            'shipping_tax' => (float) $invoice->shipping_tax,
            'tax_total' => (float) $invoice->tax_total,
            'grand_total' => (float) $invoice->grand_total,
            'advance_payment' => (float) $invoice->advance_payment,
            'due_value' => (float) $invoice->due_value,
            'payment_status' => $invoice->payment_status,
            'is_paid' => (bool) $invoice->is_paid,
            'notes' => $invoice->notes,
            'items' => $invoice->items->map(fn ($i) => [
                'id' => $i->id, 'product_id' => $i->product_id, 'product_name' => $i->product->name ?? $i->item,
                'unit_name' => $i->unit_name, 'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price,
                'discount' => (float) $i->discount, 'discount_type' => $i->discount_type == 2 ? 'percentage' : 'amount',
                'tax_1' => (float) $i->tax_1, 'tax_2' => (float) $i->tax_2, 'total' => (float) $i->total,
            ]),
            'can_return' => $invoice->returnBlockReason() === null,
            'can_credit_note' => $invoice->creditNoteBlockReason() === null,
            'print_url' => route('api.integration.invoices.print', $invoice->id),
            'created_at' => $invoice->created_at?->toDateTimeString(),
            'employee' => $detailed && $invoice->employee ? ['id' => $invoice->employee->id, 'name' => $invoice->employee->name] : null,
            'payments' => $detailed ? $invoice->payments->map(fn ($p) => ['id' => $p->id, 'amount' => (float) $p->amount, 'created_at' => $p->created_at?->toDateTimeString()]) : null,
        ];
    }
}
