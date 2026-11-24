<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\ClientRelation;
use App\Models\DocumentTemplate;
use App\Models\InvoiceItem;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseQuotationView;
use App\Models\Supplier;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\User;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\Purchases\PurchaseTaxCalculator;
use App\Support\DocumentPrintPage;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use TCPDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\ProductUnits;
use Modules\Purchases\Support\PurchaseFulfillmentLedger;
use Modules\Purchases\Support\SupplierGuard;

class PurchaseOrdersController extends Controller
{
    use Concerns\ServesDesignedTemplatePage;
    use ScopesToEmployeeHierarchy;

    /** نوع المستند في شاشة «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'purchase_order_request';

    /**
     * قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات».
     *
     * أوامر الشراء تُحفظ في الجدول الموحّد `purchase_invoices`، فقسمها
     * `purchase-order-request` — والقسم القديم كان يشير إلى جدول آخر.
     */
    public const NUMBER_SECTION = 'purchase-order-request';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمستند */
    public const LOG_MODULE = 'purchase_request';

    /**
     * معاينة الطباعة بقالب مضبوط في «قوالب المستندات».
     */
    public function printView($id, $template = 'default')
    {
        $order = PurchaseInvoice::with(['supplier', 'creator', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($order->created_by, $order->employee_id, $order->branch_id);

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderConfiguredTemplate($id, $fallback->id) : '';
        }

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $order,
            __('purchase_cycle.log.printed', [
                'document' => __('purchase_cycle.documents.'.self::DOCUMENT_TYPE),
                'code' => $order->code,
            ]),
        );

        return response(DocumentPrintPage::wrap($html));
    }

    /**
     * يصيّر قالباً من الإعدادات — أو null إن لم يكن `$template` معرّفَ قالبٍ
     * صالح لهذا النوع.
     */
    private function renderConfiguredTemplate($documentId, $template): ?string
    {
        if (! is_numeric($template)) {
            return null;
        }

        $model = DocumentTemplate::where('id', (int) $template)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->first();

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->getDocumentData(self::DOCUMENT_TYPE, $documentId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'render_template', $documentId);

            return null;
        }
    }

    /**
     * اسم المستند كما يظهر في الرسائل وسجلّ النشاط — مصدر واحد فلا تتضارب
     * التسميات بين رسالة نجاح وسطر سجلّ.
     */
    private function documentLabel(): string
    {
        return __('purchase_cycle.documents.'.self::DOCUMENT_TYPE);
    }

    /** نصّ سطر سجلّ النشاط لهذا القسم */
    private function logLine(string $event, $code): string
    {
        return __('purchase_cycle.log.'.$event, [
            'document' => $this->documentLabel(),
            'code' => $code,
        ]);
    }

    /** تراجع آمن — لا يمسّ إلا معاملةً فتحها هذا المتحكّم فعلاً */
    private function rollBackIfOpen(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    /**
     * حالة الحفظ: من الطلب إن أُذن بذلك، وإلّا «تحت المراجعة».
     *
     * الإذن خيار «حالة يدوية لأمر الشراء» (`manual_purchase_orders_status`)
     * في خيارات المشتريات، والقائمة صريحة لا مفتوحة — نفس نمط
     * `ViewPurchasePriceController::resolvedStatus()` و
     * `QuotationsController::resolvedStatus()` تماماً.
     */
    private function resolvedStatus(Request $request): string
    {
        $default = 'Under Review';

        if (! PurchaseSettings::enabled('manual_purchase_orders_status')) {
            return $default;
        }

        $submitted = (string) $request->input('status', '');

        return in_array($submitted, ['Under Review', 'approval', 'disagree'], true)
            ? $submitted
            : $default;
    }

    public function __construct()
    {
        // مُنقولة إلى كتالوج purchases.order_requests.* — نفس نقل
        // Orders/Quotations/Invoices سابقاً في هذه الوحدة. الأسماء القديمة
        // (purchase_cycle_orders_manage/approve_reject) لم تعد تُستعمَل هنا؛
        // الأدوار التي كانت تملكها (owner/manager/admin) مُنحت الأسماء
        // الجديدة صراحةً في قاعدة البيانات عند هذا النقل.
        $this->middleware('permission:purchases.order_requests.view', ['only' => [
            'index', 'paginatePurchase', 'show', 'getNotes',
        ]]);
        $this->middleware('permission:purchases.order_requests.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:purchases.order_requests.edit', ['only' => [
            'edit', 'update', 'cancel', 'updateStatus', 'addNote', 'deleteNote', 'close', 'cancelOrder',
        ]]);
        $this->middleware('permission:purchases.order_requests.delete', ['only' => ['destroy']]);
        $this->middleware('permission:purchases.order_requests.approve_reject', ['only' => [
            'approve', 'reject', 'cancelApproval', 'undoRejection',
        ]]);
        $this->middleware('permission:purchases.order_requests.view', ['only' => ['convertToInvoice']]);
        $this->middleware('permission:purchases.order_requests.export', ['only' => ['generatePdf', 'printView']]);
    }

    public function index(Request $request)
    {
        // فلترُ المورِّد يتبع فرعَ الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers()->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax()) {
            return $this->getFilteredPurchaseData($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $purchaseOrdersRequests = $this->getFilteredPurchaseData($request, false);

        /* عدّاد الحالات لشريط الملخّص — استعلام واحد لا ثلاثة، ومقيَّدٌ
           بنطاق المستخدم نفسِه الذي يُقيَّد به الجدول
           (`getFilteredPurchaseData`): كان عارياً فيقرأ صاحبُ الفرع
           عدّاد المنشأة كلِّها بينما لا يرى تحته إلّا سجلّاته. */
        $counts = $this->scopeToBranchNode(
            $this->scopeToCreator(PurchaseInvoice::query()->where('type', 'Requested')),
            $request
        )
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $counts->sum(),
            'review' => (int) ($counts['Under Review'] ?? 0),
            'approved' => (int) ($counts['approval'] ?? 0),
            'rejected' => (int) ($counts['disagree'] ?? 0),
        ];

        return view('purchases::purchases.purchase_orders.index', compact('purchaseOrdersRequests', 'suppliers', 'account_setting', 'stats'));
    }

    private function getFilteredPurchaseData(Request $request, $returnJson = true)
    {
        $query = PurchaseInvoice::query()
            ->with(['supplier', 'creator'])
            ->withCount('items')
            ->where('type', 'Requested'); // أوامر الشراء

        // نطاق البيانات — نفس نمط PurchaseRequestsController::index()
        $query = $this->scopeToCreator($query);

        /* «تابع لـ» — العقدةُ المختارة في الشاشة، وإلّا العقدةُ النشطة من
           الهيدر، وهي تعني العقدةَ وكلَّ ما تحتها. كانت هذه الشاشة لا تقرأ
           الفرع إطلاقاً، فيرى صاحبُ المنطقة مستنداتِ المنشأة كلَّها. */
        $query = $this->scopeToBranchNode($query, $request);

        // البحث بواسطة المورد
        if ($request->filled('employee_search')) {
            $query->where('supplier_id', $request->employee_search);
        }

        // البحث برقم الفاتورة
        if ($request->filled('number_invoice')) {
            $query->where('code', 'LIKE', '%'.$request->number_invoice.'%');
        }

        // البحث بالحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // البحث بحالة الدفع
        if ($request->filled('payment_status')) {
            switch ($request->payment_status) {
                case 'paid':
                    $query->where('is_paid', 1);
                    break;
                case 'partial':
                    $query->where('is_paid', 0)->whereColumn('advance_payment', '<', 'grand_total')->where('advance_payment', '>', 0);
                    break;
                case 'unpaid':
                    $query->where('is_paid', 0)->where('advance_payment', 0);
                    break;
                case 'returned':
                    $query->where('type', 3);
                    break;
                case 'overpaid':
                    $query->whereColumn('advance_payment', '>', 'grand_total');
                    break;
                case 'draft':
                    $query->where('status', 0);
                    break;
            }
        }

        // البحث بواسطة المنشئ
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // البحث بواسطة الوسم
        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag);
            });
        }

        // البحث في الحقل المخصص
        if ($request->filled('contract')) {
            $query->where('reference_number', 'LIKE', '%'.$request->contract.'%');
        }

        // البحث في وصف البنود
        if ($request->filled('description')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('description', 'LIKE', '%'.$request->description.'%');
            });
        }

        // البحث بنوع المصدر
        if ($request->filled('source')) {
            if ($request->source === 'return') {
                $query->where('type', 1);
            } else {
                $query->where('type', 1);
            }
        }

        // البحث بحالة التسليم
        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        // البحث بالتاريخ
        if ($request->filled('start_date_from')) {
            $query->whereDate('date', '>=', $request->start_date_from);
        }
        if ($request->filled('start_date_to')) {
            $query->whereDate('date', '<=', $request->start_date_to);
        }

        // البحث بتاريخ الإنشاء
        if ($request->filled('created_at_from')) {
            $query->whereDate('created_at', '>=', $request->created_at_from);
        }
        if ($request->filled('created_at_to')) {
            $query->whereDate('created_at', '<=', $request->created_at_to);
        }

        // ترتيب النتائج
        $query->orderBy('created_at', 'desc');

        // الحصول على النتائج مع التقسيم إلى صفحات
        $purchaseOrdersRequests = $query->paginate(30);

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'data' => view('purchases::purchases.purchase_orders.partials.table', compact('purchaseOrdersRequests'))->render(),
                'pagination' => view('purchases::purchases.purchase_orders.partials.pagination', compact('purchaseOrdersRequests'))->render(),
                'total' => $purchaseOrdersRequests->total(),
                'current_page' => $purchaseOrdersRequests->currentPage(),
                'last_page' => $purchaseOrdersRequests->lastPage(),
            ]);
        }

        return $purchaseOrdersRequests;
    }

    // دالة للتعامل مع طلبات الـ pagination عبر Ajax
    public function paginatePurchase(Request $request)
    {
        if ($request->ajax()) {
            return $this->getFilteredPurchaseData($request);
        }

        return redirect()->route('PurchaseOrders.index'); // تغيير هذا إلى اسم المسار الصحيح
    }

    public function create(Request $request)
    {
        /* المورِّدون على فرع الشريط العلويّ — كقوائم العملاء في المبيعات.
           `forBranch()` شاملٌ لما تحت العقدة ويمرّر الصفوف القديمة بلا فرع،
           والرئيسيُّ يعني المنشأة كلَّها فلا يُرشِّح
           ({@see \App\Support\HeaderBranchScope}). */
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), [])->get();
        $items = Product::all();
        $accounts = Account::all();
        $taxs = TaxSitting::all();

        // الرقم من إعدادات «تسلسل أرقام المستندات» — معاينة بلا استهلاك،
        // بدل `max(code) + 1` الذي كان يتجاهل البادئة والصيغة المضبوطتين
        $code = peek_document_number(self::NUMBER_SECTION);

        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // التحقق من وجود مورد محدد في الرابط
        $selectedSupplier = null;
        if ($request->has('supplier_id')) {
            $selectedSupplier = Supplier::find($request->supplier_id);
            if ($selectedSupplier) {
                $this->authorizeCreatorScope($selectedSupplier->created_by);
            }
        }

        // التحقق من وجود عرض سعر محدد في الرابط
        $quotation = null;
        if ($request->has('quotation_id')) {
            $quotation = PurchaseQuotationView::with(['items.product', 'supplier'])->find($request->quotation_id);
            if ($quotation) {
                $this->authorizeCreatorScope($quotation->created_by);
            }

            // إذا لم يتم تحديد المورد من الرابط، استخدم مورد عرض السعر
            if (! $selectedSupplier && $quotation) {
                $selectedSupplier = $quotation->supplier;
            }
        }

        $branches = \App\Models\Branch::orderBy('name')->get();

        return view('purchases::purchases.purchase_orders.create',
            compact('suppliers', 'code', 'accounts', 'taxs', 'items', 'account_setting', 'selectedSupplier', 'quotation', 'branches'));
    }

    public function store(Request $request)
    {
        try {
            /* الضريبة الشاملة على المستند — الخانةُ العلوية `document_tax_id`
               تُفرَض على كلّ بند حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة. */
            PurchaseTaxCalculator::applyComprehensive($request);

            // ** الخطوة الأولى: الرقم من إعدادات التسلسل **
            // يُستهلَك عند الحفظ الفعلي وحده، ويتخطّى المحجوز في الجدول
            $code = $this->resolveCode($request->code);

            // «منع المستندات على مورد موقوف» — يُفحص قبل أول كتابة، بنفس
            // نمط InvoicesPurchaseController::store()/ReturnsInvoiceController.
            // هذا الكنترولر لم يكن يستدعي الحارس إطلاقاً رغم أن نص الإعداد
            // في شاشة إعدادات المشتريات يَعِد صراحةً بمنعه على «أمر الشراء»
            $supplier = Supplier::find($request->supplier_id);
            SupplierGuard::assertActive($supplier);

            DB::beginTransaction();

            // الفرع يُعيّن تلقائياً من فرع المستخدم الحالي — إلا لمن يملك
            // صلاحية اختيار فرع آخر صراحة — نفس نمط PurchaseRequestsController::store()
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'));

            // ** إنشاء الفاتورة أولاً (بدون المبالغ المحسوبة) **
            $purchaseOrder = PurchaseInvoice::create([
                'supplier_id' => $request->supplier_id,
                'code' => $code,
                'type' => 'Requested',
                'status' => $this->resolvedStatus($request),
                'branch_id' => $branchId,
                'date' => $request->date,
                'due_date' => $request->due_date,
                'terms' => $request->terms ?? 0,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'account_id' => $request->account_id,
                'advance_payment' => floatval($request->advance_payment ?? 0),
                'payment_type' => $request->payment_type ?? 1,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number,
                'received_date' => $request->received_date,
            ]);

            // ** متغيرات لحساب المبالغ **
            $subtotal = 0; // المجموع الفرعي قبل الضريبة والخصم
            $total_item_tax = 0; // إجمالي ضرائب العناصر
            $included_item_tax = 0; // ما كان منها متضمَّناً في الأسعار
            $total_item_discount = 0; // إجمالي خصومات العناصر
            $taxDetails = []; // تفاصيل الضرائب المختارة

            // ** معالجة عناصر الفاتورة **
            if ($request->has('items')) {
                $invoiceItems = [];
                // حل المشكلة: استخدام مصفوفة لتتبع العناصر المضافة لتجنب التكرار
                $processedItems = [];

                // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن، كما في
                // `InvoicesPurchaseController`
                $documentTaxIds = [];

                foreach ($request->items as $item) {
                    // التحقق من أن العنصر ليس فارغًا
                    if (empty($item['product_id']) || empty($item['quantity'])) {
                        continue;
                    }

                    // إنشاء مفتاح فريد للعنصر
                    $itemKey = $item['product_id'].'_'.($item['unit_price'] ?? 0).'_'.($item['quantity'] ?? 0);

                    // التحقق من أن العنصر لم تتم معالجته مسبقًا
                    if (in_array($itemKey, $processedItems)) {
                        continue;
                    }

                    // تسجيل العنصر كمعالج
                    $processedItems[] = $itemKey;

                    $product = Product::find($item['product_id']);

                    // حساب المجموع الفرعي لكل منتج (الكمية × السعر)
                    $item_subtotal = floatval($item['quantity']) * floatval($item['unit_price']);
                    $subtotal += $item_subtotal;

                    // حساب خصم العنصر
                    $item_discount = 0;
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        if (isset($item['discount_type']) && $item['discount_type'] === 'percentage') {
                            $item_discount = ($item_subtotal * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }
                    $total_item_discount += $item_discount;

                    // ضرائب العنصر — النسبة **ونوعها** من «إعدادات الضريبة»:
                    // المتضمَّنة تُستخرَج من السعر ولا تُضاف فوقه، والوعاء
                    // هو المبلغ بعد خصم البند لا قبله
                    $tax_1_id = $item['tax_1_id'] ?? null;
                    $tax_2_id = $item['tax_2_id'] ?? null;
                    $tax_1_rate = $tax_1_id ? PurchaseTaxCalculator::rate($tax_1_id) : floatval($item['tax_1'] ?? 0);
                    $tax_2_rate = $tax_2_id ? PurchaseTaxCalculator::rate($tax_2_id) : floatval($item['tax_2'] ?? 0);

                    // أوّلُ ضريبةٍ على البنود هي ضريبةُ الشحن حين لا يُختار له
                    // مُنتقٍ مستقلّ — نفس قاعدة فاتورة الشراء
                    foreach ([$tax_1_id, $tax_2_id] as $documentTaxId) {
                        if ($documentTaxId) {
                            $documentTaxIds[] = $documentTaxId;
                        }
                    }

                    $line = PurchaseTaxCalculator::line(
                        floatval($item['quantity']),
                        floatval($item['unit_price']),
                        $item_discount,
                        [$tax_1_id, $tax_2_id],
                        [$tax_1_rate, $tax_2_rate],
                    );

                    foreach ($line['taxes'] as $detail) {
                        $setting = PurchaseTaxCalculator::find($detail['id']);

                        if (! $setting) {
                            continue;
                        }

                        $taxDetails[$setting->name] = ($taxDetails[$setting->name] ?? 0) + $detail['amount'];

                        TaxInvoice::create([
                            'name' => $setting->name,
                            'invoice_id' => $purchaseOrder->id,
                            'type' => $setting->type,
                            'rate' => $detail['rate'],
                            'value' => $detail['amount'],
                            'type_invoice' => 'purchase',
                        ]);
                    }

                    $item_tax_total = $line['tax'];
                    $total_item_tax += $item_tax_total;
                    // الجزء المتضمَّن داخل الأسعار — يُطرح لاحقاً من المجموع
                    // الفرعي حتى لا يُحسب مرّتين
                    $included_item_tax += $line['base'] - $line['net'];

                    // حساب إجمالي العنصر
                    $item_total = $line['total'];

                    // إضافة المنتج للمصفوفة
                    $invoiceItems[] = ProductUnits::fieldsFor($item, (float) $item['quantity']) + [
                        'purchase_invoice_id' => $purchaseOrder->id,
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount' => $item_discount,
                        'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                        'tax_1' => $tax_1_rate,
                        'tax_1_id' => $tax_1_id ?: null,
                        'tax_2' => $tax_2_rate,
                        'tax_2_id' => $tax_2_id ?: null,
                        'total' => $item_total,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // إضافة البنود باستخدام insert لتحسين الأداء
                if (! empty($invoiceItems)) {
                    InvoiceItem::insert($invoiceItems);
                }
            }

            // الأسعار أُدخلت شاملةً الضريبة المتضمَّنة، فلو بقيت داخل المجموع
            // الفرعي لحُسبت مرّتين حين يُضاف `total_tax` إليه
            $subtotal = round($subtotal - $included_item_tax, 2);

            /* ** الخصم العامّ والشحن ** — «تفعيل تبويب التسوية» يحكمهما.
             *
             * النموذج المشترك يُخفي التبويبة متى أُطفئ الإعداد، لكن الخادم
             * كان يقبل الحقلين على أي حال: طلبٌ قديمٌ في تبويبٍ مفتوح، أو
             * مصنوع باليد، يطبّق خصماً وشحناً أطفأهما المسؤول. نفس الحراسة
             * القائمة في `InvoicesPurchaseController`.
             */
            $enableSettlementSetting = PurchaseSettings::enabled('enable_settlement');

            // ** حساب الخصم الإضافي **
            $additional_discount = 0;
            if ($enableSettlementSetting && $request->discount_amount && $request->discount_type) {
                if ($request->discount_type == 'percentage') {
                    $additional_discount = ($subtotal * floatval($request->discount_amount)) / 100;
                } else {
                    $additional_discount = floatval($request->discount_amount);
                }
            }

            // ** حساب تكلفة الشحن وضريبتها **
            $shipping_cost = $enableSettlementSetting ? floatval($request->shipping_cost ?? 0) : 0;
            $shipping_tax = 0;
            if ($shipping_cost > 0) {
                /* النسبة من «إعدادات الضريبة» — كانت `15` مكتوبةً في
                   الشيفرة، فمنشأةٌ نسبتها غير ذلك تحمّل شحنَها ١٥٪ أبداً،
                   ويخالف أمرُ الشراء الفاتورةَ التي يتحوّل إليها (وهي تقرأ
                   الإعداد منذ حين). والمصدر واحد: `PurchaseTaxCalculator`.

                   وشرطُ `tax_type == 1` أُسقِط: **النموذج لا يرسل الحقل**،
                   فكان الشرط كاذباً في كلّ حفظ وتُحفَظ ضريبةُ الشحن صفراً
                   بينما تعرضها الشاشة. والمختارةُ في تبويبة الشحن
                   (`tax_id`) أوّلاً — وكانت تُهمَل. */
                $shipping_tax = PurchaseTaxCalculator::shippingTax(
                    $shipping_cost,
                    PurchaseTaxCalculator::shippingTaxIds(
                        $request->filled('tax_id'),
                        $request->input('tax_id'),
                        $documentTaxIds
                    ),
                );

                // النسبة المعروضة في سطر الضريبة تُشتقّ من المبلغ نفسِه،
                // فلا يظهر «١٥٪» فوق مبلغٍ حُسب بغيرها
                $shipping_tax_rate = $shipping_cost > 0
                    ? round(($shipping_tax / $shipping_cost) * 100, 2)
                    : 0;

                // حفظ ضريبة الشحن
                TaxInvoice::create([
                    'name' => 'ضريبة الشحن ('.rtrim(rtrim(number_format($shipping_tax_rate, 2, '.', ''), '0'), '.').'%)',
                    'invoice_id' => $purchaseOrder->id,
                    'type' => 'excluded',
                    'rate' => $shipping_tax_rate,
                    'value' => $shipping_tax,
                    'type_invoice' => 'purchase',
                ]);
            }

            // ** حساب إجمالي الخصومات **
            $total_discount = $total_item_discount + $additional_discount;

            // ** حساب إجمالي الضرائب **
            $total_tax = $total_item_tax + $shipping_tax;

            // ** حساب المجموع قبل خصم الدفعة المقدمة **
            $total_with_tax = $subtotal - $total_discount + $shipping_cost + $total_tax;

            // ** حساب المبلغ المتبقي بعد الدفعة المقدمة **
            $advance_payment = floatval($request->advance_payment ?? 0);
            $grand_total = $total_with_tax - $advance_payment;

            // حالة أمر الشراء (Under Review/approval/disagree) لا تُشتقّ من
            // الدفع والاستلام إطلاقاً — هي مسؤولية أزرار الاعتماد/الرفض
            // وحدها (approve/reject) أو `resolvedStatus()` أعلاه عند
            // الإنشاء اليدوي. الفروع هنا كانت تكتب أعداداً صحيحة (2/3/4/5)
            // في عمود ENUM ترتيبه `disagree, approval, Under Review, convert
            // invoice` — وMySQL يفسّر أي عدد صحيح في عمود ENUM كفهرس
            // 1-based لا كقيمة حرفية، فكانت هذه القيم تُخزَّن فعلياً كحالات
            // مختلفة تماماً عمّا يقصده التعليق — انظر نفس الإصلاح في
            // update()، حيث ثبت أن هذا النمط يخزّن 'disagree' (مرفوضة)
            // خطأً. الدفع والاستلام يُسجَّلان في `is_paid`/`is_received`
            // وحدهما، بمعزل تام عن حالة الاعتماد — ولا يُعاد كتابة `status`
            // هنا كي لا يُطمَس ما ضبطه `resolvedStatus()` وقت الإنشاء
            $is_paid = false;
            $is_received = $request->has('is_received');

            if ($advance_payment > 0) {
                $is_paid = true;
                if ($advance_payment >= $total_with_tax) {
                    $grand_total = 0;
                }
            } elseif ($request->has('is_paid')) {
                $is_paid = true;
                $grand_total = 0;
            }

            // ** تحديث الفاتورة بالمبالغ المحسوبة **
            $purchaseOrder->update([
                'discount_amount' => $additional_discount,
                'discount_type' => $request->has('discount_type') ? ($request->discount_type === 'percentage' ? 2 : 1) : 1,
                'shipping_cost' => $shipping_cost,
                'tax_type' => $request->tax_type ?? 1,
                'is_paid' => $is_paid,
                'is_received' => $is_received,
                'subtotal' => $subtotal,
                'total_discount' => $total_discount,
                'total_tax' => $total_tax,
                /* حصّةُ الشحن محفوظةٌ وحدها كذلك: `total_tax` يبقى المجموع
                   (فلا تتبدّل المطبوعات)، وهذا العمود يحفظ ما يُطرَح منه ليُرحَّل
                   إلى «ضريبة شحن مشتريات». وبلا حفظه هنا يصل الأمرُ إلى الاستلام
                   ثمّ إلى الفاتورة بضريبةِ شحنٍ صفراً، فتندمج في سطر ضريبة البنود
                   ويضيع الحسابُ المستقلّ. */
                'shipping_tax' => $shipping_tax,
                'grand_total' => $grand_total,
            ]);

            // ** حفظ تفاصيل الخصم الإضافي إذا وجد **
            if ($additional_discount > 0) {
                TaxInvoice::create([
                    'name' => 'خصم إضافي',
                    'invoice_id' => $purchaseOrder->id,
                    'type' => 'discount',
                    'rate' => $request->discount_type == 'percentage' ? floatval($request->discount_amount) : 0,
                    'value' => $additional_discount,
                    'type_invoice' => 'purchase',
                ]);
            }

            // ** معالجة المرفقات (attachments) إذا وجدت **
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $purchaseOrder->attachments = $filename;
                    $purchaseOrder->save();
                }
            }

            // ** الحصول على بيانات المورد للسجل **
            $supplier = Supplier::find($request->supplier_id);
            notifications::create([
                'user_id' => $supplier->user_id,
                'receiver_id' => auth()->id(),
                'title' => 'تم انشاء امر شراء',
                'description' => 'تم انشاء امر شراء رقم '.$purchaseOrder->code.' للمورد '.$supplier->trade_name.', بمبلغ '.$grand_total.', يرجى التحقق منها',
            ]);

            // ** إنشاء سجل النشاط **
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $purchaseOrder,
                $this->logLine('created', $purchaseOrder->code),
            );

            DB::commit();

            return redirect()->route('PurchaseOrders.show', $purchaseOrder->id)->with('success', 'تم انشاء امر الشراء بنجاح');

        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'save');

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء حفظ امر الشراء: '.$e->getMessage());
        }
    }

    public function edit($id)
    {
        $purchaseOrdersRequests = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrdersRequests->created_by);
        /* المورِّدون على فرع الشريط العلويّ — كقوائم العملاء في المبيعات.
           `forBranch()` شاملٌ لما تحت العقدة ويمرّر الصفوف القديمة بلا فرع،
           والرئيسيُّ يعني المنشأة كلَّها فلا يُرشِّح
           ({@see \App\Support\HeaderBranchScope}). */
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), [])->get();
        $items = Product::all();
        $accounts = Account::all();
        $taxs = TaxSitting::all();
        $branches = \App\Models\Branch::orderBy('name')->get();

        return view('purchases::purchases.purchase_orders.edit', compact('purchaseOrdersRequests', 'taxs', 'suppliers', 'accounts', 'items', 'branches'));
    }

    public function show($id)
    {
        // جلب عرض السعر مع العلاقات المرتبطة
        $purchaseOrdersRequests = PurchaseInvoice::with(['supplier', 'account', 'items.product', 'creator'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrdersRequests->created_by);

        // المورِّدون على فرع الشريط العلويّ، ومورِّدُ هذا الأمر يبقى
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers(
            Supplier::select('id', 'trade_name'),
            [$purchaseOrdersRequests->supplier_id]
        )->get();

        $logs = ModelsLog::where('type', self::LOG_MODULE)
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        // قوالب المستندات المصمَّمة في الإعدادات لهذا النوع
        $documentTemplates = PrintSettings::templatesFor(self::DOCUMENT_TYPE);
        $defaultTemplate = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
        $prefersPdf = PrintSettings::prefersPdf();

        // دورة الاستلام: المطلوب/المستلم/المفوتر وما بقي من كلٍّ،
        // واستلامات هذا الأمر وفواتيره — قد تتعدّد الآن بعد أن صار
        // الاستلام الجزئي والفوترة الجزئية ممكنَين
        $fulfillment = PurchaseFulfillmentLedger::orderTotals($purchaseOrdersRequests);
        $receivings = $purchaseOrdersRequests->receivings()->latest('id')->get();
        $relatedInvoices = PurchaseInvoice::where('reference_id', $purchaseOrdersRequests->id)
            ->where('type', 'invoice')
            ->latest('id')
            ->get();

        return view('purchases::purchases.purchase_orders.show', compact(
            'purchaseOrdersRequests', 'logs', 'suppliers',
            'documentTemplates', 'defaultTemplate', 'prefersPdf',
            'fulfillment', 'receivings', 'relatedInvoices'
        ));
    }

    public function update(Request $request, $id)
    {
        try {
            // الضريبة الشاملة — كما في store()
            PurchaseTaxCalculator::applyComprehensive($request);

            DB::beginTransaction();

            $purchaseInvoice = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // ✅ منع التعديل على الأمر بعد الموافقة عليه
            if ($purchaseInvoice->status === 'approval') {
                return redirect()->back()->with('error', 'لا يمكن تعديل أمر الشراء بعد الموافقة عليه. يرجى إلغاء الموافقة أولاً إذا كان مطلوباً تعديل البيانات.');
            }

            // منع التعديل على الأمر المحول إلى فاتورة
            if ($purchaseInvoice->status === 'convert invoice') {
                return redirect()->back()->with('error', 'لا يمكن تعديل أمر شراء تم تحويله إلى فاتورة بالفعل.');
            }

            // «منع المستندات على مورد موقوف» — نفس فحص store()، يُطبَّق على
            // المورد الجديد إن غُيِّر، وإلا الحالي
            $supplier = Supplier::find($request->supplier_id ?? $purchaseInvoice->supplier_id);
            SupplierGuard::assertActive($supplier);

            // حالة أمر الشراء (`Under Review`/`approval`/`disagree`) لا تُشتقّ
            // من الدفع والاستلام إطلاقاً — هي مسؤولية أزرار الاعتماد/الرفض
            // وحدها (approve/reject)، إلا إن سمح إعداد «حالة يدوية لأمر
            // الشراء» (`manual_purchase_orders_status`) باختيارها صراحةً هنا
            // — نفس نمط `ViewPurchasePriceController::update()`. كان الفرع
            // الأخير هنا يكتب `status = 1` (عدد صحيح) في عمود ENUM ترتيبه
            // `disagree, approval, Under Review, convert invoice` — وMySQL
            // يفسّر عدداً صحيحاً في عمود ENUM كفهرس 1-based لا كقيمة حرفية،
            // فكان `1` يُخزَّن فعلياً كـ`disagree` (مرفوضة)! وبما أن نموذج
            // هذه الشاشة لا يعرض تبويبَي الدفع والاستلام أصلاً (`payment`/
            // `receiving` معطّلان في config)، كان هذا الفرع الأخير يُنفَّذ في
            // كل حفظ تعديل تقريباً، فيرفض الطلب صامتاً بلا أي فعل من المستخدم
            $is_paid = $request->has('is_paid') ? true : $purchaseInvoice->is_paid;
            $is_received = $request->has('is_received') ? true : $purchaseInvoice->is_received;

            if (PurchaseSettings::enabled('manual_purchase_orders_status')) {
                $submittedStatus = (string) $request->input('status', '');
                $status = in_array($submittedStatus, ['Under Review', 'approval', 'disagree'], true)
                    ? $submittedStatus
                    : $purchaseInvoice->status;
            } else {
                $status = $purchaseInvoice->status;
            }

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحةً ويملك
            // صلاحية ذلك — نفس نمط PurchaseRequestsController::update()
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'), $purchaseInvoice->branch_id);

            // تحديث بيانات الفاتورة الأساسية
            $updateData = [
                'supplier_id' => $request->supplier_id ?? $purchaseInvoice->supplier_id,
                'branch_id' => $branchId,
                'date' => $request->date ?? $purchaseInvoice->date,
                'due_date' => $request->due_date ?? $purchaseInvoice->due_date,
                'terms' => $request->terms ?? $purchaseInvoice->terms ?? 0,
                'notes' => $request->notes ?? $purchaseInvoice->notes,
                'status' => $status,
                'account_id' => $request->account_id ?? $purchaseInvoice->account_id,
                'discount_amount' => $request->discount_value ?? $purchaseInvoice->discount_amount ?? 0,
                'discount_type' => $request->discount_type === 'percentage' ? 2 : 1,
                'advance_payment' => $request->advance_payment ?? $purchaseInvoice->advance_payment ?? 0,
                'payment_type' => $request->payment_type ?? $purchaseInvoice->payment_type ?? 1,
                'shipping_cost' => $request->shipping_cost ?? $purchaseInvoice->shipping_cost ?? 0,
                'tax_type' => $request->tax_type ?? $purchaseInvoice->tax_type ?? 1,
                'payment_method' => $request->payment_method ?? $purchaseInvoice->payment_method,
                'reference_number' => $request->reference_number ?? $purchaseInvoice->reference_number,
                'received_date' => $request->received_date ?? $purchaseInvoice->received_date,
                'is_paid' => $is_paid,
                'is_received' => $is_received,
                'updated_at' => now(),
            ];

            $purchaseInvoice->fill($updateData);

            // الفرق يُلتقط قبل الحفظ — بعده تصير القيم الأصلية هي الجديدة
            $changes = OperationLog::diff($purchaseInvoice, [
                'supplier_id', 'date', 'terms', 'notes', 'status', 'account_id',
                'discount_amount', 'discount_type', 'advance_payment', 'payment_type',
                'shipping_cost', 'tax_type', 'payment_method', 'reference_number',
                'received_date', 'is_paid', 'is_received',
            ]);

            $purchaseInvoice->save();

            $subtotal = 0;
            $included_item_tax = 0; // ما كان من الضريبة متضمَّناً في الأسعار
            $total_item_discount = 0;
            $total_item_tax = 0;
            $taxDetails = [];

            // تحديث العناصر فقط إذا تم إرسالها
            if ($request->has('items')) {
                // حذف العناصر القديمة والضرائب المرتبطة بها
                $purchaseInvoice->invoiceItems()->delete();
                TaxInvoice::where('invoice_id', $purchaseInvoice->id)
                    ->where('type_invoice', 'purchase')
                    ->delete();

                $documentTaxIds = [];

                foreach ($request->items as $item) {
                    $product = Product::find($item['product_id'] ?? null);

                    // حساب المجموع الفرعي لكل منتج
                    $quantity = floatval($item['quantity'] ?? 0);
                    $unit_price = floatval($item['unit_price'] ?? 0);
                    $item_subtotal = $quantity * $unit_price;
                    $subtotal += $item_subtotal;

                    // حساب خصم العنصر
                    $item_discount = 0;
                    $item_discount_type = isset($item['discount_type']) ? ($item['discount_type'] === 'percentage' ? 2 : 1) : 1;
                    $discount_amount = floatval($item['discount_amount'] ?? 0);

                    if ($item_discount_type == 2) {
                        $item_discount = ($item_subtotal * $discount_amount) / 100;
                    } else {
                        $item_discount = $discount_amount;
                    }
                    $total_item_discount += $item_discount;

                    // ضرائب العنصر — النسبة **ونوعها** من «إعدادات الضريبة»:
                    // المتضمَّنة تُستخرَج من السعر ولا تُضاف فوقه، والوعاء
                    // هو المبلغ بعد خصم البند لا قبله
                    $tax_1_id = $item['tax_1_id'] ?? null;
                    $tax_2_id = $item['tax_2_id'] ?? null;
                    $tax_1_rate = $tax_1_id ? PurchaseTaxCalculator::rate($tax_1_id) : floatval($item['tax_1'] ?? 0);
                    $tax_2_rate = $tax_2_id ? PurchaseTaxCalculator::rate($tax_2_id) : floatval($item['tax_2'] ?? 0);

                    // أوّلُ ضريبةٍ على البنود هي ضريبةُ الشحن حين لا يُختار له
                    // مُنتقٍ مستقلّ — نفس قاعدة فاتورة الشراء
                    foreach ([$tax_1_id, $tax_2_id] as $documentTaxId) {
                        if ($documentTaxId) {
                            $documentTaxIds[] = $documentTaxId;
                        }
                    }

                    $line = PurchaseTaxCalculator::line(
                        floatval($quantity),
                        floatval($unit_price),
                        $item_discount,
                        [$tax_1_id, $tax_2_id],
                        [$tax_1_rate, $tax_2_rate],
                    );

                    foreach ($line['taxes'] as $detail) {
                        $setting = PurchaseTaxCalculator::find($detail['id']);

                        if (! $setting) {
                            continue;
                        }

                        $taxDetails[$setting->name] = ($taxDetails[$setting->name] ?? 0) + $detail['amount'];

                        TaxInvoice::create([
                            'name' => $setting->name,
                            'invoice_id' => $purchaseInvoice->id,
                            'type' => $setting->type,
                            'rate' => $detail['rate'],
                            'value' => $detail['amount'],
                            'type_invoice' => 'purchase',
                        ]);
                    }

                    $item_tax_total = $line['tax'];
                    $total_item_tax += $item_tax_total;
                    // الجزء المتضمَّن داخل الأسعار — يُطرح لاحقاً من المجموع
                    // الفرعي حتى لا يُحسب مرّتين
                    $included_item_tax += $line['base'] - $line['net'];

                    // حساب إجمالي العنصر
                    $item_total = $line['total'];

                    // إنشاء عنصر الفاتورة
                    $invoiceItem = $purchaseInvoice->invoiceItems()->create([
                        'purchase_invoice_id' => $purchaseInvoice->id,
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount' => $item_discount,
                        'discount_type' => $item_discount_type,
                        'tax_1' => $tax_1_rate,
                        'tax_1_id' => $tax_1_id ?: null,
                        'tax_2' => $tax_2_rate,
                        'tax_2_id' => $tax_2_id ?: null,
                        'total' => $item_total,
                    ]);

                }
            }

            // بلا طرحها يُحسب المتضمَّن مرّتين — انظر store()
            $subtotal = round($subtotal - $included_item_tax, 2);

            // الخصم العامّ والشحن — انظر التعليق في store()
            $enableSettlementSetting = PurchaseSettings::enabled('enable_settlement');

            // حساب الخصم الإضافي
            $additional_discount = 0;
            if ($enableSettlementSetting && $request->discount_amount && $request->discount_type) {
                if ($request->discount_type == 'percentage') {
                    $additional_discount = ($subtotal * floatval($request->discount_amount)) / 100;
                } else {
                    $additional_discount = floatval($request->discount_amount);
                }
            }

            /* الشحن: مع الإعداد المطفأ لا يُقرأ من الطلب — ويبقى المحفوظ على
             * حاله فلا يُصفَّر شحنُ مستندٍ حُفظ قبل الإطفاء بمجرّد فتحه */
            $shipping_cost = $enableSettlementSetting
                ? floatval($request->shipping_cost ?? $purchaseInvoice->shipping_cost ?? 0)
                : floatval($purchaseInvoice->shipping_cost ?? 0);
            $shipping_tax = 0;
            if ($shipping_cost > 0) {
                /* النسبة من «إعدادات الضريبة» لا مكتوبةً، وبلا شرط
                   `tax_type` — انظر `store()` */
                $shipping_tax = PurchaseTaxCalculator::shippingTax(
                    $shipping_cost,
                    PurchaseTaxCalculator::shippingTaxIds(
                        $request->filled('tax_id'),
                        $request->input('tax_id'),
                        $documentTaxIds
                    ),
                );

                $shipping_tax_rate = $shipping_cost > 0
                    ? round(($shipping_tax / $shipping_cost) * 100, 2)
                    : 0;

                TaxInvoice::create([
                    'name' => 'ضريبة الشحن ('.rtrim(rtrim(number_format($shipping_tax_rate, 2, '.', ''), '0'), '.').'%)',
                    'invoice_id' => $purchaseInvoice->id,
                    'type' => 'excluded',
                    'rate' => $shipping_tax_rate,
                    'value' => $shipping_tax,
                    'type_invoice' => 'purchase',
                ]);
            }

            // حساب إجمالي الخصومات والضرائب
            $total_discount = $total_item_discount + $additional_discount;
            $total_tax = $total_item_tax + $shipping_tax;

            // حساب المجموع النهائي
            $total_with_tax = $subtotal - $total_discount + $shipping_cost + $total_tax;
            $advance_payment = floatval($request->advance_payment ?? $purchaseInvoice->advance_payment ?? 0);
            $grand_total = $total_with_tax - $advance_payment;

            // تحديث الفاتورة بالمبالغ المحسوبة
            $purchaseInvoice->update([
                'subtotal' => $subtotal,
                'total_discount' => $total_discount,
                'total_tax' => $total_tax,
                'shipping_tax' => $shipping_tax,      // انظر التعليق في store()
                'shipping_cost' => $shipping_cost,
                'grand_total' => $grand_total,
            ]);

            // حفظ تفاصيل الخصم الإضافي إذا وجد
            if ($additional_discount > 0) {
                TaxInvoice::create([
                    'name' => 'خصم إضافي',
                    'invoice_id' => $purchaseInvoice->id,
                    'type' => 'discount',
                    'rate' => $request->discount_type == 'percentage' ? floatval($request->discount_amount) : 0,
                    'value' => $additional_discount,
                    'type_invoice' => 'purchase',
                ]);
            }

            // معالجة المرفقات
            if ($request->hasFile('attachments')) {
                if ($purchaseInvoice->attachments) {
                    $oldFile = public_path('assets/uploads/').$purchaseInvoice->attachments;
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $purchaseInvoice->attachments = $filename;
                    $purchaseInvoice->save();
                }
            }

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $purchaseInvoice,
                $this->logLine('updated', $purchaseInvoice->code),
                $changes,
            );

            DB::commit();

            return redirect()->route('PurchaseOrders.index')->with('success', 'تم تحديث امر شراء بنجاح');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'update', $id);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء تحديث امر شراء: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $purchaseOrdersRequests = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseOrdersRequests->created_by);

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $purchaseOrdersRequests,
                $this->logLine('deleted', $purchaseOrdersRequests->code),
            );

            $purchaseOrdersRequests->delete();

            return redirect()->route('PurchaseOrders.index')->with('success', 'تم حذف أمر الشراء بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'delete', $id);

            return redirect()
                ->back()
                ->with('error', 'حدث خطاء في حذف أمر الشراء: '.$e->getMessage());
        }
    }

    /**
     * التحويل المباشر من أمر شراء إلى فاتورة — مُعطَّل.
     *
     * الفاتورة لم تعد تُنشأ من أمر الشراء مباشرةً؛ يجب أن يمرّ الأمر أولاً
     * على «استلام المشتريات» (`PurchaseReceivingController`)، ومن الاستلام
     * المعتمَد وحده تُنشأ الفاتورة (`PurchaseReceivingController::
     * convertToInvoice()`) — بالكمّية المستلمة فعلياً، جزئياً كانت أو
     * كاملة. المسارُ باقٍ تفادياً لكسر أي رابطٍ أو زرٍّ قديم يشير إليه، لكنّه
     * صار على `purchases.order_requests.view`: صلاحيةٌ خاصّةٌ به
     * (`…order_requests.convert`) كانت خانةً في شاشة الأدوار تحرس دالّةً لا
     * تفعل إلا إظهارَ هذه الرسالة، فحُذفت ([الترحيل 2026_09_12_220000]).
     */
    public function convertToInvoice($id)
    {
        return redirect()->route('PurchaseOrders.show', $id)->with('error',
            'التحويل المباشر من أمر شراء إلى فاتورة لم يعد متاحاً. استلم البضاعة أولاً عبر «استلام المشتريات» ثم حوّل الاستلام إلى فاتورة.');
    }

    /**
     * الرقم المعتمد للأمر — ما عرضته الشاشة، وإلا التالي من إعدادات التسلسل.
     * حقل الرقم للقراءة فقط، فما يصل هنا هو ما عُرض لا ما كُتب.
     *
     * الرقم المعروض في الشاشة قد يكون استُهلك بين فتحها والحفظ (مستخدم آخر،
     * أو تبويب مفتوح منذ مدّة)، فيُستبدل بالتالي الشاغر بدل رفض الحفظ.
     */
    private function resolveCode($submitted): string
    {
        $submitted = trim((string) $submitted);

        if ($submitted === '' || PurchaseInvoice::where('code', $submitted)->exists()) {
            return next_document_number(self::NUMBER_SECTION);
        }

        return $submitted;
    }

    public function cancel($id)
    {
        try {
            $purchaseOrder = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseOrder->created_by, $purchaseOrder->employee_id, $purchaseOrder->branch_id);

            // إلغاء أمر الشراء
            $purchaseOrder->type = 3; // يمكنك تغيير هذا الرقم حسب حاجتك
            $purchaseOrder->save();

            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $purchaseOrder,
                $this->logLine('status_changed', $purchaseOrder->code),
            );

            return redirect()->back()->with('success', 'تم إلغاء أمر الشراء بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'cancel', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء إلغاء أمر الشراء');
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $purchaseOrder = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by, $purchaseOrder->employee_id, $purchaseOrder->branch_id);
        $newType = $request->input('type');

        DB::beginTransaction();
        try {
            $oldStatus = $purchaseOrder->type;

            $purchaseOrder->update([
                'type' => $newType,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            // تحديد نص الرسالة حسب نوع العملية
            $statusMessages = [
                'Under Review' => 'تحت المراجعة',
                'approval' => 'محول إلى فاتورة',
                'disagree' => 'ملغي',
            ];

            $description = sprintf('تم تغيير حالة أمر الشراء رقم **%s** من "%s" إلى "%s"', $purchaseOrder->code, $statusMessages[$oldStatus] ?? 'غير محدد', $statusMessages[$newType] ?? 'غير محدد');

            // إضافة ملاحظة إضافية إذا تم إدخالها
            if ($request->filled('note')) {
                $description .= "\n\n**ملاحظة:** ".$request->note;
            }

            // تسجيل النشاط
            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $purchaseOrder,
                $description,
                ['old' => ['status' => $oldStatus], 'new' => ['status' => $newType]],
            );

            DB::commit();

            $successMessage = match ($newType) {
                'approval' => 'تم تحويل أمر الشراء إلى فاتورة بنجاح',
                'disagree' => 'تم إلغاء أمر الشراء بنجاح',
                'Under Review' => 'تم إعادة تفعيل أمر الشراء بنجاح',
                default => 'تم تحديث حالة أمر الشراء بنجاح',
            };

            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'update_status', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء تحديث حالة أمر الشراء');
        }
    }

    // دالة إضافة ملاحظة
    public function addNote(Request $request, $id)
    {
        try {
            $request->validate([
                'description' => 'required|string|max:1000',
                'process' => 'required|string|max:255',
                'date' => 'required|date',
                'time' => 'required|string',
                'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120', // 5MB max
            ]);

            $purchaseOrder = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseOrder->created_by, $purchaseOrder->employee_id, $purchaseOrder->branch_id);

            // التعامل مع المرفق إذا تم رفعه
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time().'_'.$file->getClientOriginalName();
                $attachmentPath = $file->storeAs('purchase_requests/notes', $fileName, 'public');
            }

            // إنشاء الملاحظة باستخدام ClientRelation
            $clientRelation = ClientRelation::create([
                'process' => $request->process,
                'time' => $request->time,
                'date' => $request->date,
                'quotation_id' => $id, // نستخدم نفس الحقل
                'employee_id' => auth()->user()->id,
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'type' => 'purchase_Request', // نوع مختلف لتمييز أوامر الشراء
            ]);
            notifications::create([
                'user_id' => $purchaseOrder->user_id,
                'receiver_id' => $purchaseOrder->user_id,
                'title' => 'ملاحظة جديدة',
                'description' => 'تم اضافة ملاحظة جديدة لأمر الشراء رقم '.$purchaseOrder->code,
            ]);

            // تسجيل النشاط في سجل الأنشطة
            OperationLog::record(
                'note_added',
                self::LOG_MODULE,
                $purchaseOrder,
                __('purchase_cycle.log.note_added', [
                    'document' => $this->documentLabel(),
                    'code' => $purchaseOrder->code,
                    'note' => $request->process,
                ]),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ الملاحظة بنجاح',
                'note' => [
                    'id' => $clientRelation->id,
                    'description' => $clientRelation->description,
                    'process' => $clientRelation->process,
                    'date' => $clientRelation->date,
                    'time' => $clientRelation->time,
                    'employee_name' => auth()->user()->name,
                    'has_attachment' => $attachmentPath ? true : false,
                    'attachment_url' => $attachmentPath ? asset('storage/'.$attachmentPath) : null,
                ],
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'add_note', $id);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حفظ الملاحظة: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    // دالة جلب الملاحظات
    public function getNotes($id)
    {
        try {
            // المستندُ الأمّ بتخويله — كما في addNote؛ كان المعرّفُ يكفي لقراءة ملاحظات أيّ مستند
            $parent = \App\Models\PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($parent->created_by, $parent->employee_id, $parent->branch_id);
            $notes = ClientRelation::where('quotation_id', $id)->where('type', 'purchase_Request')->with('employee')->orderBy('created_at', 'desc')->get();

            // تنسيق البيانات لتتطابق مع JavaScript
            $formattedNotes = $notes->map(function ($note) {
                return [
                    'id' => $note->id,
                    'description' => $note->description,
                    'process' => $note->process,
                    'date' => $note->date,
                    'time' => $note->time,
                    'employee_name' => $note->employee->name ?? 'غير محدد',
                    'has_attachment' => ! empty($note->attachment),
                    'attachment_url' => $note->attachment ? asset('storage/'.$note->attachment) : null,
                    'created_at' => $note->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'notes' => $formattedNotes->toArray(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.PurchaseOrdersRequests', 'getNotes');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب الملاحظات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    // دالة حذف الملاحظة
    public function deleteNote($noteId)
    {
        try {
            $note = ClientRelation::findOrFail($noteId);
            // تخويلُ المستند الأمّ قبل الحذف — كان يُحذف بأيّ معرّف
            $parent = \App\Models\PurchaseInvoice::findOrFail($note->quotation_id);
            $this->authorizeCreatorScope($parent->created_by, $parent->employee_id, $parent->branch_id);

            // حذف المرفق إذا كان موجود
            if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                Storage::disk('public')->delete($note->attachment);
            }

            $quotationId = $note->quotation_id;
            $process = $note->process;

            $note->delete();

            // تسجيل النشاط — بنوع القسم لا بصيغة أخرى لا يقرأها الخطّ الزمني
            OperationLog::record(
                'note_deleted',
                self::LOG_MODULE,
                $quotationId,
                __('purchase_cycle.log.note_deleted', [
                    'document' => $this->documentLabel(),
                    'code' => $quotationId,
                    'note' => $process,
                ]),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملاحظة بنجاح',
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'delete_note', $noteId);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف الملاحظة: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Generate PDF for purchase order request
     */
    public function generatePdf($id, $template = 'default')
    {
        try {
            // تحميل أمر الشراء مع البيانات المرتبطة
            $purchaseOrdersRequests = PurchaseInvoice::with(['supplier', 'creator', 'items.product', 'account'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseOrdersRequests->created_by);

            /* القالب المصمَّم في «قوالب المستندات» أوّلاً — كان هذا المسار
               يتجاهل القالب المختار ويصيّر الـblade المدمج دائماً، ولم يكن
               TCPDF مستورداً أصلاً فينهار الطلب بـ«Class not found». */
            if ($page = $this->configuredTemplatePage($id, $template, $purchaseOrdersRequests->code)) {
                OperationLog::record(
                    'exported',
                    self::LOG_MODULE,
                    $purchaseOrdersRequests,
                    $this->logLine('exported', $purchaseOrdersRequests->code),
                );

                return $page;
            }

            // تحديد قالب PDF بناءً على المعامل
            $templateView = 'purchases::purchases.purchase_orders.pdf';

            switch ($template) {
                case 'detailed':
                    $templateView = 'purchases::purchases.purchase_orders.partials.pdf_detailed';
                    break;
                case 'simple':
                    $templateView = 'purchases::purchases.purchase_orders.partials.pdf_simple';
                    break;
                case 'compact':
                    $templateView = 'purchases::purchases.purchase_orders.partials.pdf_compact';
                    break;
                case 'summary':
                    $templateView = 'purchases::purchases.purchase_orders.partials.pdf_summary';
                    break;
                default:
                    $templateView = 'purchases::purchases.purchase_orders.pdf';
                    break;
            }

            // إنشاء PDF جديد باستخدام TCPDF
            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // تعيين معلومات الوثيقة
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('أمر شراء - '.$purchaseOrdersRequests->code);

            // تعيين الهوامش
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);

            // تعطيل رأس وتذييل الصفحة
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // تعيين الاتجاه من اليمين إلى اليسار
            $pdf->setRTL(true);

            // إضافة صفحة جديدة
            $pdf->AddPage();

            // تعيين الخط العربي
            $pdf->SetFont('aealarabiya', '', 12);

            // إنشاء محتوى HTML من الـ view
            $html = view($templateView, compact('purchaseOrdersRequests'))->render();

            // إضافة المحتوى للـ PDF
            $pdf->writeHTML($html, true, false, true, false, '');

            // إخراج الملف
            $filename = 'أمر_شراء_'.$purchaseOrdersRequests->code.'_'.$template.'.pdf';

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseOrdersRequests,
                $this->logLine('exported', $purchaseOrdersRequests->code),
            );

            return $pdf->Output($filename, 'D'); // D للتحميل، I للعرض المباشر

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء إنشاء ملف PDF: '.$e->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        $purchaseInvoice = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        DB::beginTransaction();
        try {
            $purchaseInvoice->update([
                'status' => 'approval',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_note' => $request->note,
            ]);

            OperationLog::record(
                'approved',
                self::LOG_MODULE,
                $purchaseInvoice,
                $this->logLine('approved', $purchaseInvoice->code)
                    .($request->filled('note') ? ' — '.$request->note : ''),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تمت الموافقة على الطلب بنجاح');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'approve', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء الموافقة على الطلب');
        }
    }

    // دالة رفض الطلب
    public function reject(Request $request, $id)
    {
        $purchaseInvoice = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        DB::beginTransaction();
        try {
            $purchaseInvoice->update([
                'status' => 'disagree',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);
            OperationLog::record(
                'rejected',
                self::LOG_MODULE,
                $purchaseInvoice,
                $this->logLine('rejected', $purchaseInvoice->code)
                    .($request->filled('note') ? ' — '.$request->note : ''),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تم رفض الطلب بنجاح');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'reject', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء رفض الطلب');
        }
    }

    // دالة إلغاء الموافقة
    public function cancelApproval(Request $request, $id)
    {
        $purchaseInvoice = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        // التحقق من أن الطلب معتمد أصلاً
        if ($purchaseInvoice->status !== 'approval') {
            return redirect()->back()->with('error', 'لا يمكن إلغاء الموافقة، الطلب غير معتمد');
        }

        // ✅ منع إلغاء الموافقة إذا تم تحويل الأمر إلى فاتورة
        $convertedInvoice = PurchaseInvoice::where('reference_id', $purchaseInvoice->id)
            ->where('type', 'invoice')
            ->first();

        if ($convertedInvoice) {
            return redirect()->back()->with('error', 'لا يمكن إلغاء الموافقة على هذا الأمر لأنه تم تحويله بالفعل إلى فاتورة رقم '.$convertedInvoice->code.' - يرجى التواصل مع الإدارة المالية');
        }

        DB::beginTransaction();
        try {
            $purchaseInvoice->update([
                'status' => 'Under Review',
                'approved_by' => null,
                'approved_at' => null,
                'approval_note' => null,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);

            OperationLog::record(
                'approval_cancelled',
                self::LOG_MODULE,
                $purchaseInvoice,
                $this->logLine('approval_cancelled', $purchaseInvoice->code),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تم إلغاء الموافقة بنجاح وإعادة الطلب للمراجعة');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'cancel_approval', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء إلغاء الموافقة');
        }
    }

    // دالة التراجع عن الرفض
    public function undoRejection(Request $request, $id)
    {
        $purchaseInvoice = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        // التحقق من أن الطلب مرفوض أصلاً
        if ($purchaseInvoice->status !== 'disagree') {
            return redirect()->back()->with('error', 'لا يمكن التراجع عن الرفض، الطلب غير مرفوض');
        }

        DB::beginTransaction();
        try {
            $purchaseInvoice->update([
                'status' => 'Under Review',
                'rejected_by' => null,
                'rejected_at' => null,
                'restored_by' => auth()->id(),
                'restored_at' => now(),
            ]);
            OperationLog::record(
                'rejection_undone',
                self::LOG_MODULE,
                $purchaseInvoice,
                $this->logLine('rejection_undone', $purchaseInvoice->code),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تم التراجع عن الرفض بنجاح وإعادة الطلب للمراجعة');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'undo_rejection', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء التراجع عن الرفض');
        }
    }

    /**
     * إغلاق أمر شراء استُلم بالكامل — طرفٌ نهائيٌّ في دورة الاستلام، يمنع
     * فتح استلاماتٍ أخرى عليه ولو بقيت كمّيةٌ اسمية بسبب فارق تقريب.
     *
     * يُشترط الاستلام الكامل: أمرٌ لا يزال ينتظر بضاعةً غير موصولة يُخفي
     * التزاماً حقيقياً لو أُغلق مبكراً.
     */
    public function close($id)
    {
        $purchaseOrder = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by, $purchaseOrder->employee_id, $purchaseOrder->branch_id);

        if ($purchaseOrder->status !== 'approval') {
            return redirect()->back()->with('error', 'لا يمكن إغلاق أمرٍ غير معتمَد.');
        }

        if ($purchaseOrder->receiving_status !== 'received') {
            return redirect()->back()->with('error', 'لا يمكن إغلاق أمرٍ لم يُستلم بالكامل بعد.');
        }

        $purchaseOrder->update(['status' => 'closed']);

        OperationLog::record('status_changed', self::LOG_MODULE, $purchaseOrder,
            'تم إغلاق أمر الشراء رقم '.$purchaseOrder->code);

        return redirect()->back()->with('success', 'تم إغلاق أمر الشراء.');
    }

    /**
     * إلغاء أمر شراء معتمَد لم يُستلم منه شيء بعد — يختلف عن {@see reject()}
     * الذي يرفض أمراً لم يُعتمد أصلاً.
     */
    public function cancelOrder($id)
    {
        $purchaseOrder = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by, $purchaseOrder->employee_id, $purchaseOrder->branch_id);

        if (! in_array($purchaseOrder->status, ['Under Review', 'approval'], true)) {
            return redirect()->back()->with('error', 'لا يمكن إلغاء أمرٍ بهذه الحالة.');
        }

        $totals = PurchaseFulfillmentLedger::orderTotals($purchaseOrder);
        if ($totals['received'] > 0) {
            return redirect()->back()->with('error', 'لا يمكن إلغاء أمرٍ استُلم منه بضاعة بالفعل — راجع استلاماته أولاً.');
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        OperationLog::record('status_changed', self::LOG_MODULE, $purchaseOrder,
            'تم إلغاء أمر الشراء رقم '.$purchaseOrder->code);

        return redirect()->back()->with('success', 'تم إلغاء أمر الشراء.');
    }
}
