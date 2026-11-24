<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ClientRelation;
use App\Models\DocumentTemplate;
use App\Models\InvoiceItem;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationView;
use App\Models\Supplier;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\Purchases\PurchaseTaxCalculator;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\ConvertedLines;
use Modules\Purchases\Support\ProductUnits;
use App\Support\DocumentPrintPage;

class ViewPurchasePriceController extends Controller
{
    use Concerns\ServesDesignedTemplatePage;
    use ScopesToEmployeeHierarchy;

    /** نوع المستند في شاشة «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'purchase_quotation';

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'purchase-quotation';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمستند */
    public const LOG_MODULE = 'quotation_view';

    /** القوالب المدمجة — تظهر حين لا يُصمَّم قالب في الإعدادات */
    public const BUILTIN_TEMPLATES = ['default', 'detailed', 'simple', 'compact', 'summary'];

    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً — لا قديمة ولا من
     * كتالوج `PurchasesPermissions` رغم أن مجموعة `price_quotations` معرَّفة
     * فيه أصلاً وتُشير إليه صراحةً. الحراسة هنا أول تطبيق فعلي لها.
     */
    public function __construct()
    {
        $this->middleware('permission:purchases.price_quotations.view', ['only' => ['index', 'paginateQuotation', 'show', 'getNotes']]);
        $this->middleware('permission:purchases.price_quotations.create', ['only' => ['create', 'store']]);
        // النسخُ صلاحيتُه المستقلّة
        $this->middleware('permission:purchases.price_quotations.duplicate', ['only' => ['duplicate']]);
        $this->middleware('permission:purchases.price_quotations.edit', ['only' => ['edit', 'update', 'addNote']]);
        $this->middleware('permission:purchases.price_quotations.delete', ['only' => ['destroy', 'destroyNote', 'bulkDelete']]);
        $this->middleware('permission:purchases.price_quotations.approve_reject', ['only' => ['approve', 'reject', 'undoRejection', 'updateStatus']]);
        $this->middleware('permission:purchases.price_quotations.convert', ['only' => ['convertToPurchaseOrder']]);
        $this->middleware('permission:purchases.price_quotations.export', ['only' => ['exportPDF', 'printView']]);
    }

    /**
     * معاينة الطباعة بقالب مضبوط في «قوالب المستندات».
     */
    public function printView($id, $template = 'default')
    {
        $quotation = PurchaseQuotationView::with(['supplier', 'creator', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($quotation->created_by, null, $quotation->branch_id);

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $quotation,
            $this->logLine('printed', $quotation->code),
        );

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html !== null) {
            return response(DocumentPrintPage::wrap($html));
        }

        return view($this->builtinTemplateView($template), [
            'purchaseQuotation' => $quotation,
            'autoPrint' => true,
        ]);
    }

    /**
     * يصيّر قالباً من الإعدادات — أو null إن لم يكن `$template` معرّفَ قالبٍ
     * صالح لهذا النوع، فيتولّى النداءُ البديلَ.
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
     * اسم قالب Blade المدمج المطابق لاسم القالب — نفس نمط
     * `PurchaseRequestsController::builtinTemplateView()`.
     */
    private function builtinTemplateView($template): string
    {
        $base = 'purchases::purchases.view_purchase_price.pdf';

        return in_array($template, self::BUILTIN_TEMPLATES, true) && $template !== 'default'
            ? $base.'_'.$template
            : $base;
    }

    /** اسم المستند كما يظهر في الرسائل وسجلّ النشاط */
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

    public function index(Request $request)
    {
        // فلترُ المورِّد يتبع فرعَ الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers()->get();

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax() || $request->is_ajax) {
            return $this->getFilteredQuotationData($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $purchaseQuotation = $this->getFilteredQuotationData($request, false);

        /* عدّاد الحالات لشريط الملخّص — استعلام واحد لا ثلاثة، ومقيَّدٌ
           بنطاق المستخدم نفسِه الذي يُقيَّد به الجدول
           (`getFilteredQuotationData`): كان عارياً فيقرأ صاحبُ الفرع
           عدّاد المنشأة كلِّها بينما لا يرى تحته إلّا سجلّاته. */
        $counts = $this->scopeToBranchNode($this->scopeToCreator(PurchaseQuotationView::query()), $request)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $counts->sum(),
            'review' => (int) ($counts['Under Review'] ?? 0),
            'approved' => (int) ($counts['approval'] ?? 0),
            'rejected' => (int) ($counts['disagree'] ?? 0),
        ];

        // حالة حدّ اشتراك فوترة لميزة «عروض أسعار الموردين»
        $subscriptionLimit = app(\App\Services\Fawtra\SubscriptionGate::class)->limit('purchase_price_quotations');

        return view('purchases::purchases.view_purchase_price.index', compact('suppliers', 'purchaseQuotation', 'stats', 'subscriptionLimit'));
    }

    private function getFilteredQuotationData(Request $request, $returnJson = true)
    {
        // بناء الاستعلام
        // ما يعرضه الجدول يُحمَّل مع الاستعلام لا صفاً صفاً
        $query = PurchaseQuotationView::query()->with(['supplier', 'creator'])->withCount('items');

        // تطبيق تصفية نطاق البيانات حسب دور المستخدم — انظر App\Support\Scopes
        $query = $this->scopeToCreator($query);

        /* «تابع لـ» — العقدةُ المختارة في الشاشة، وإلّا العقدةُ النشطة من
           الهيدر، وهي تعني العقدةَ وكلَّ ما تحتها. كانت هذه الشاشة لا تقرأ
           الفرع إطلاقاً، فيرى صاحبُ المنطقة مستنداتِ المنشأة كلَّها. */
        $query = $this->scopeToBranchNode($query, $request);

        // البحث بالكود
        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.$request->code.'%');
        }

        // البحث بالتاريخ (من)
        if ($request->filled('order_date_from')) {
            $query->whereDate('date', '>=', $request->order_date_from);
        }

        // البحث بالتاريخ (إلى)
        if ($request->filled('order_date_to')) {
            $query->whereDate('date', '<=', $request->order_date_to);
        }

        /* فلترُ العملة أُزيل: لا عمود `currency` في `purchase_quotations_view`
           أصلاً — فلو وصله طلبٌ يحمل `currency` لرمى «Unknown column».
           والعملةُ واحدةٌ من إعدادات النظام فلا معنى للتصفية بها. */

        // البحث بالحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // البحث بالوسم
        if ($request->filled('tag')) {
            $query->where('tag', $request->tag);
        }

        // البحث بالنوع
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // البحث بالمورد
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // ترتيب النتائج حسب تاريخ الإنشاء (الأحدث أولًا)
        $query->latest('created_at');

        // تحميل العلاقات المطلوبة
        $query->with(['supplier', 'account', 'items.product']);

        // الحصول على النتائج مع التقسيم إلى صفحات
        $purchaseQuotation = $query->paginate(10);

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'data' => view('purchases::purchases.view_purchase_price.partials.table', compact('purchaseQuotation'))->render(),
                'pagination' => view('purchases::purchases.view_purchase_price.partials.pagination', compact('purchaseQuotation'))->render(),
                'total' => $purchaseQuotation->total(),
                'current_page' => $purchaseQuotation->currentPage(),
                'last_page' => $purchaseQuotation->lastPage(),
                'from' => $purchaseQuotation->firstItem(),
                'to' => $purchaseQuotation->lastItem(),
            ]);
        }

        return $purchaseQuotation;
    }

    // دالة للتعامل مع طلبات الـ pagination عبر Ajax
    public function paginateQuotation(Request $request)
    {
        if ($request->ajax()) {
            return $this->getFilteredQuotationData($request);
        }

        return redirect()->route('PurchaseQuotation.index');
    }

    public function show($id)
    {
        // جلب عرض السعر مع العلاقات المرتبطة
        $purchaseQuotation = PurchaseQuotationView::with(['supplier', 'account', 'items.product', 'creator'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

        // المورِّدون على فرع الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::select('id', 'trade_name'))->get();

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

        return view('purchases::purchases.view_purchase_price.show', compact(
            'purchaseQuotation', 'logs', 'suppliers',
            'documentTemplates', 'defaultTemplate', 'prefersPdf'
        ));
    }

    public function create()
    {
        // جلب البيانات الأساسية
        $accounts = Account::all();
        $items = Product::all();
        // المورِّدون على فرع الشريط العلويّ وما يتبعه — {@see \App\Support\HeaderBranchScope}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), [])->get();
        $taxs = TaxSitting::all();

        // جلب المورد المحدد إذا كان موجودًا في الرابط
        $selectedSupplier = null;
        if (request()->has('supplier_id')) {
            $selectedSupplier = Supplier::find(request('supplier_id'));
        }

        /* ══════════ قادمٌ من طلب عرض أسعار ══════════

           طلبُ التسعير يُرسَل إلى **عدّة** موردين، وجوابُ كلِّ مورّدٍ عرضُ
           سعرٍ مستقلّ — فالمستند هنا مورّدٌ واحد بالضرورة (`supplier_mode`
           مفرد، والمقارنة بين العروض هي الغاية أصلاً).

           وكانت الشاشة تُظهر قائمة الموردين **كاملة** وتملأ الحقل تلقائياً
           فقط إن كان للطلب مورّدٌ واحد. فمن اختار خمسةً في طلب التسعير
           يصل إلى شاشةٍ لا تذكر أحداً منهم، ولا تقول إنّ عليه تسجيل عرضٍ
           لكلّ واحد، ولا مَن سجَّل منهم ومن بقي. */
        $quotation = null;
        $quotationSuppliers = collect();
        $quotedSupplierIds = [];

        if (request()->has('quotation_id')) {
            $quotation = PurchaseQuotation::with(['items.product', 'suppliers', 'generatedQuotations'])
                ->find(request('quotation_id'));
            if ($quotation) {
                $this->authorizeCreatorScope($quotation->created_by, null, $quotation->branch_id);
            }

            // إذا لم يتم العثور على عرض السعر، إرجاع رسالة خطأ
            if (! $quotation) {
                return redirect()->back()->with('error', 'عرض السعر المحدد غير موجود');
            }

            $quotationSuppliers = $quotation->suppliers;

            // مَن سُجِّل عرضُه من هذا الطلب سابقاً — تُعرَض حالتُه ولا يُمنع
            // تكرارُه: قد يُعيد المورّد تسعيرَه، والمقارنة تحتمل عرضين
            $quotedSupplierIds = $quotation->generatedQuotations
                ->pluck('supplier_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            /* المُنتقي يقتصر على موردي الطلب: الاختيار من خارجهم يصنع
               عرضاً لا يقابله طلبُ تسعير، فتنكسر المقارنة. ونبقيهم ولو
               خرجوا عن فرع الشريط العلويّ — الطلبُ اختارهم فعلاً. */
            $suppliers = $quotationSuppliers;

            // الملء التلقائي: ما جاء في الرابط أوّلاً، وإلا المتبقّي الوحيد
            // الذي لم يُسجَّل عرضُه بعد
            if (! $selectedSupplier) {
                $pending = $quotationSuppliers->reject(
                    fn ($supplier) => in_array((int) $supplier->id, $quotedSupplierIds, true)
                )->values();

                $selectedSupplier = $pending->count() === 1
                    ? $pending->first()
                    : ($quotationSuppliers->count() === 1 ? $quotationSuppliers->first() : null);
            }
        }

        // الرقم من إعدادات «تسلسل أرقام المستندات» — معاينة بلا استهلاك،
        // بدل `str_pad(max(id)+1)` الذي كان يتجاهل البادئة والصيغة هناك
        $purchasePriceNumber = peek_document_number(self::NUMBER_SECTION);
        $nextId = $purchasePriceNumber;

        return view('purchases::purchases.view_purchase_price.create', [
            'suppliers' => $suppliers,
            'items' => $items,
            'taxs' => $taxs,
            'accounts' => $accounts,
            'selectedSupplier' => $selectedSupplier,
            'purchasePriceNumber' => $purchasePriceNumber,
            'nextId' => $nextId,
            'quotation' => $quotation,
            // موردو طلب التسعير ومَن سُجِّل عرضُه منهم — تعرضهما الشاشة
            // شريطاً فوق النموذج ليُعرف من بقي
            'quotationSuppliers' => $quotationSuppliers,
            'quotedSupplierIds' => $quotedSupplierIds,
        ]);
    }

    /**
     * حالة الحفظ: من الطلب إن أُذن بذلك، وإلّا «تحت المراجعة».
     *
     * الإذن خيار «حالة يدوية لعروض أسعار المشتريات» في خيارات المشتريات،
     * والقائمة صريحة لا مفتوحة — نفس قيود `update` التي كانت قائمة وحدها.
     */
    private function resolvedStatus(Request $request): string
    {
        $default = 'Under Review';

        if (! PurchaseSettings::enabled('manual_purchase_quotes')) {
            return $default;
        }

        $submitted = (string) $request->input('status', '');

        return in_array($submitted, ['Under Review', 'approval', 'disagree'], true)
            ? $submitted
            : $default;
    }

    public function store(Request $request)
    {
        try {
            // توليد الكود تلقائياً إذا لم يُرسَل — أو إذا كان الرقم المعروض في
            // الشاشة قد استُهلك بين فتحها والحفظ، فالتحقّق أدناه يرفضه برسالة
            // «الكود مستخدم من قبل» على رقم لا يملك المستخدم تغييره
            $submittedCode = trim((string) $request->code);

            if ($submittedCode === '' || PurchaseQuotationView::where('code', $submittedCode)->exists()) {
                // الرقم يُستهلَك من إعدادات التسلسل عند الحفظ الفعلي وحده
                $request->merge(['code' => next_document_number(self::NUMBER_SECTION)]);
            }

            // التحقق مما إذا كان هناك عرض سعر أصلي لتحديث حالته
            $originalQuotation = null;
            if ($request->has('quotation_id')) {
                $originalQuotation = PurchaseQuotation::find($request->quotation_id);
            }

            // التحقق من البيانات
            $rules = [
                'quotation_id' => 'nullable|integer',
                'supplier_id' => 'required|exists:suppliers,id',
                'code' => 'required|string|unique:purchase_quotations_view,code',
                'date' => 'required|date',
                'valid_days' => 'nullable|integer|min:0',

                // قواعد الخصم والتسوية
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:amount,percentage',
                'adjustment_label' => 'nullable|string|max:255',
                'adjustment_type' => 'nullable|in:discount,addition',
                'adjustment_value' => 'nullable|numeric',
                // قواعد الشحن
                'shipping_cost' => 'nullable|numeric|min:0',
                'tax_id' => 'nullable|exists:tax_sittings,id',
                // يُعتمَد فقط لمن يملك صلاحية اختيار الفرع — انظر Scopes::canSelectBranch()
                'branch_id' => 'nullable|integer|exists:branches,id',
            ];

            $validator = Validator::make($request->all(), $rules);
            $validatedData = $validator->validated();

            // بنود التحويل: ما طلبه طلبُ عرض الأسعار لا يُبدَّل هنا — هذه
            // الشاشة تُسعِّر ما طُلب. الشاشة تقفل الحقول، وهذا الفحص هو ما
            // يجعل القفل قفلاً: `readonly` سمةٌ تُنزَع من المتصفّح في ثوانٍ
            if ($originalQuotation && ($tampered = ConvertedLines::guard($originalQuotation->items, (array) $request->input('items', [])))) {
                return back()->withInput()->with('error', $tampered);
            }

            // اشتراك فوترة وحدوده — نفس فحص PurchaseRequestsController::store()
            $subscriptionGate = app(\App\Services\Fawtra\SubscriptionGate::class);
            if (! $subscriptionGate->allows('purchase_price_quotations')) {
                return back()->withInput()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «عروض أسعار الموردين». راجع لوحة تحكّم فوترة للترقية.');
            }
            $priceQuotationQuota = $subscriptionGate->check('purchase_price_quotations');
            if (! $priceQuotationQuota['allowed']) {
                return back()->withInput()->with('error', $priceQuotationQuota['reason']);
            }

            // الفرع يُعيّن تلقائياً من فرع المستخدم الحالي (موظف: فرعه المثبَّت،
            // وإلا فرع المستخدم) — إلا لمن يملك صلاحية اختيار فرع آخر صراحة
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $validatedData['branch_id'] ?? null);

            DB::beginTransaction();

            // إنشاء عرض السعر أولاً (بدون المبالغ المحسوبة)
            $quotation = PurchaseQuotationView::create([
                'id' => $request->id,
                'quotation_id' => $request->quotation_id ?? $request->id, // ✅ إضافة quotation_id
                'supplier_id' => $validatedData['supplier_id'],
                'code' => $validatedData['code'],
                // العمود NOT NULL بلا قيمة افتراضية ولم يكن يُضبط قطّ، فكان كل
                // حفظ يسقط بـ«Field 'purchase_price_number' doesn't have a default value»
                'purchase_price_number' => $validatedData['code'],
                'date' => $validatedData['date'],
                'valid_days' => $validatedData['valid_days'] ?? 0,
                'status' => $this->resolvedStatus($request),
                'created_by' => Auth::id(),
                'branch_id' => $branchId,
            ]);

            // متغيرات لحساب المبالغ
            $subtotal = 0; // المجموع الفرعي قبل الضريبة والخصم
            $total_item_tax = 0; // إجمالي ضرائب العناصر
            $included_item_tax = 0; // ما كان منها متضمَّناً في الأسعار
            $total_item_discount = 0; // إجمالي خصومات العناصر
            $taxDetails = []; // تفاصيل الضرائب المختارة

            // معالجة عناصر الفاتورة
            if ($request->has('items')) {
                $invoiceItems = [];

                foreach ($request->items as $item) {
                    $product = Product::find($item['product_id']);

                    // حساب المجموع الفرعي لكل منتج (الكمية × السعر)
                    $item_subtotal = floatval($item['quantity']) * floatval($item['unit_price']);
                    $subtotal += $item_subtotal;

                    // حساب خصم العنصر — النموذج يرسل `discount` كمبلغ أو
                    // كنسبة حسب `discount_type` فقط؛ لا حقل `discount_percentage`
                    // منفصلاً (لم يكن موجوداً أصلاً في الفورم، فكان خصم البند
                    // بالنسبة يُصفَّر دوماً ولا يظهر لا في جدول البنود ولا
                    // في إجمالي الخصم)
                    $item_discount = 0;
                    if (isset($item['discount']) && floatval($item['discount']) > 0) {
                        if (($item['discount_type'] ?? 'amount') === 'percentage') {
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
                            'invoice_id' => $quotation->id,
                            'type' => $setting->type,
                            'rate' => $detail['rate'],
                            'value' => $detail['amount'],
                            'type_invoice' => 'quotation_purchase',
                        ]);
                    }

                    $total_item_tax += $line['tax'];
                    // الجزء المتضمَّن داخل الأسعار — يُطرح لاحقاً من المجموع
                    // الفرعي حتى لا يُحسب مرّتين
                    $included_item_tax += $line['base'] - $line['net'];

                    $item_total = $line['total'];

                    // إضافة المنتج للمصفوفة
                    $invoiceItems[] = ProductUnits::fieldsFor($item, (float) $item['quantity']) + [
                        'quotes_purchase_order_id' => $quotation->id,
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount' => $item_discount,
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
                InvoiceItem::insert($invoiceItems);
            }

            // الأسعار أُدخلت شاملةً الضريبة المتضمَّنة، فلو بقيت داخل المجموع
            // الفرعي لحُسبت مرّتين حين يُضاف `total_tax` إليه
            $subtotal = round($subtotal - $included_item_tax, 2);

            /* ** تبويب التسوية ** — الخصم العامّ والتسوية والشحن، يحكمها
             * خيار «تفعيل تبويب التسوية» في خيارات المشتريات.
             *
             * النموذج المشترك يُخفي التبويبة متى أُطفئ الإعداد (انظر
             * `partials/form.blade.php`)، لكن الخادم كان يقبل حقولها على أي
             * حال — فطلبٌ من تبويبٍ فُتح قبل الإطفاء، أو مصنوع باليد، يطبّق
             * خصماً وشحناً أطفأهما المسؤول. نفس الحراسة القائمة في
             * `InvoicesPurchaseController`.
             */
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

            // حساب التسوية
            $adjustment_amount = 0;
            if ($enableSettlementSetting && $request->adjustment_value && $request->adjustment_type) {
                $adjustment_value = floatval($request->adjustment_value);
                if ($request->adjustment_type == 'discount') {
                    $adjustment_amount = -$adjustment_value; // خصم
                } else {
                    $adjustment_amount = $adjustment_value; // إضافة
                }
            }

            // حساب تكلفة الشحن وضريبتها
            $shipping_cost = $enableSettlementSetting ? floatval($request->shipping_cost ?? 0) : 0;
            $shipping_tax = 0;
            if ($shipping_cost > 0 && $request->tax_id) {
                $shipping_tax_rate = TaxSitting::find($request->tax_id);
                if ($shipping_tax_rate) {
                    $shipping_tax = ($shipping_cost * $shipping_tax_rate->tax) / 100;

                    // حفظ ضريبة الشحن
                    TaxInvoice::create([
                        'name' => $shipping_tax_rate->name.' (شحن)',
                        'invoice_id' => $quotation->id,
                        'type' => $shipping_tax_rate->type,
                        'rate' => $shipping_tax_rate->tax,
                        'value' => $shipping_tax,
                        'type_invoice' => 'quotation_purchase',
                    ]);
                }
            }

            // حساب إجمالي الخصومات
            $total_discount = $total_item_discount + $additional_discount;

            // حساب إجمالي الضرائب
            $total_tax = $total_item_tax + $shipping_tax;

            // حساب المجموع النهائي
            // المجموع النهائي = المجموع الفرعي - إجمالي الخصم + التسوية + تكلفة الشحن + إجمالي الضريبة
            $grand_total = $subtotal - $total_discount + $adjustment_amount + $shipping_cost + $total_tax;

            // تحديث عرض السعر بالمبالغ المحسوبة
            $quotation->update([
                'subtotal' => $subtotal,
                'total_discount' => $total_discount,
                'total_tax' => $total_tax,
                // حصّةُ الشحن وحدها كذلك: تسير مع العرض إلى أمر الشراء ثمّ
                // إلى الفاتورة، فتُرحَّل إلى «ضريبة شحن مشتريات» لا تندمج
                'shipping_tax' => $shipping_tax,
                'grand_total' => $grand_total,
                'shipping_cost' => $shipping_cost,

                /* الخصم العامّ بحقله لا مطويّاً في `total_discount` وحده.
                   كان يُحسَب ثمّ يُطرَح ولا يُكتب في `discount_amount` قطّ —
                   لا هنا ولا في `create()` — فيبقى العمود صفراً أبداً.
                   وثلاثةٌ تنكسر به: شاشةُ التعديل لا تستطيع إعادة عرضه،
                   والتحويلُ إلى أمر شراء ينسخ صفراً مع `total_discount`
                   غير الصفر فيصل الأمرُ متناقضاً، والاستلامُ يوزّع
                   `discount_amount` وحده على الفواتير فلا يصل خصمُ العرض
                   إلى الفاتورة ولا إلى قيدها. والمحفوظ **المبلغ المحسوب**
                   لا المُدخَل، نظيرَ `purchase_invoices.discount_amount`. */
                'discount_amount' => round($additional_discount, 2),
                'discount_type' => $request->discount_type === 'percentage' ? 'percentage' : 'amount',

                'adjustment_amount' => $adjustment_amount,
                'adjustment_label' => $request->adjustment_label,
                'notes' => $request->notes,
            ]);

            // حفظ تفاصيل الخصم الإضافي إذا وجد
            if ($additional_discount > 0) {
                TaxInvoice::create([
                    'name' => 'خصم إضافي',
                    'invoice_id' => $quotation->id,
                    'type' => 'discount',
                    'rate' => $request->discount_type == 'percentage' ? floatval($request->discount_amount) : 0,
                    'value' => $additional_discount,
                    'type_invoice' => 'quotation_purchase',
                ]);
            }

            // حفظ تفاصيل التسوية إذا وجدت
            if ($adjustment_amount != 0) {
                TaxInvoice::create([
                    'name' => $request->adjustment_label ?? 'تسوية',
                    'invoice_id' => $quotation->id,
                    'type' => $request->adjustment_type,
                    'rate' => 0,
                    'value' => abs($adjustment_amount),
                    'type_invoice' => 'quotation_purchase',
                ]);
            }

            // تحديث حالة عرض السعر الأصلي إلى مسعر (2) إذا كان موجودًا
            if ($originalQuotation) {
                $originalQuotation->update([
                    'status' => 'approval', // مسعر
                    'updated_by' => Auth::id(),
                ]);
            }

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $quotation,
                $this->logLine('created', $quotation->code),
            );

            DB::commit();

            return redirect()
                ->route('ViewPurchasePrice.index')
                ->with('success', 'تم إنشاء عرض السعر بنجاح'.($originalQuotation ? ' وتحديث حالة عرض السعر الأصلي' : ''));

        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'save');

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء عرض السعر: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $quotation = PurchaseQuotationView::findOrFail($id);
            $this->authorizeCreatorScope($quotation->created_by, null, $quotation->branch_id);

            // الاعتماد نهائي: لا تعديل بعده، بنفس قاعدة PurchaseRequestsController
            if ($quotation->status !== 'Under Review') {
                return redirect()->back()->with('error', __('purchase_orders.messages.edit_locked'));
            }

            // التحقق من البيانات
            $rules = [
                'supplier_id' => 'required|exists:suppliers,id',
                'code' => 'required|string|unique:purchase_quotations_view,code,'.$id,
                'date' => 'required|date',
                'valid_days' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:Under Review,approval,disagree',

                // قواعد الخصم والتسوية
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:amount,percentage',
                'adjustment_label' => 'nullable|string|max:255',
                'adjustment_type' => 'nullable|in:discount,addition',
                'adjustment_value' => 'nullable|numeric',
                // قواعد الشحن
                'shipping_cost' => 'nullable|numeric|min:0',
                'tax_id' => 'nullable|exists:tax_sittings,id',
                // يُعتمَد فقط لمن يملك صلاحية اختيار الفرع — انظر Scopes::canSelectBranch()
                'branch_id' => 'nullable|integer|exists:branches,id',
            ];

            $validator = Validator::make($request->all(), $rules);
            $validatedData = $validator->validated();

            // بنود التحويل — انظر التعليق في store(). القفل على أصل البند
            // لا على شاشة الإنشاء وحدها، وإلا كان التعديل بابَه الخلفي
            $sourceQuotation = $quotation->quotation_id
                ? PurchaseQuotation::with('items')->find($quotation->quotation_id)
                : null;

            if ($sourceQuotation && ($tampered = ConvertedLines::guardUpdate($sourceQuotation->items, $quotation->items, (array) $request->input('items', [])))) {
                return back()->withInput()->with('error', $tampered);
            }

            DB::beginTransaction();

            // تحديث بيانات عرض السعر الأساسية
            $quotation->fill([
                'supplier_id' => $validatedData['supplier_id'],
                'code' => $validatedData['code'],
                'date' => $validatedData['date'],
                'valid_days' => $validatedData['valid_days'] ?? 0,
                // بلا إذن يدويّ تبقى الحالة كما هي: الشاشة كانت تقبل `status`
                // من الطلب دائماً، فحفظُ تعديلٍ بحقلٍ فارغ يمحو الاعتماد
                'status' => PurchaseSettings::enabled('manual_purchase_quotes')
                    ? ($validatedData['status'] ?? $quotation->status)
                    : $quotation->status,
                'created_by' => Auth::id(),
            ]);

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحة
            // ويملك صلاحية ذلك — وإلا يُعاد تثبيته من فرعه الحالي
            // العقدةُ المحفوظة تبقى في التعديل — لا تُعاد وسمُها بعقدة المحرِّر
            $quotation->branch_id = \App\Support\Scopes::chosenBranchId(auth()->user(), $validatedData['branch_id'] ?? null, $quotation->branch_id);

            // الفرق يُلتقط قبل الحفظ — بعده تصير القيم الأصلية هي الجديدة
            $changes = OperationLog::diff($quotation, [
                'supplier_id', 'code', 'date', 'valid_days', 'status', 'branch_id',
            ]);

            $quotation->save();

            // متغيرات لحساب المبالغ
            $subtotal = 0;
            $total_item_tax = 0;
            $included_item_tax = 0; // ما كان منها متضمَّناً في الأسعار
            $total_item_discount = 0;
            $taxDetails = [];

            // حذف العناصر القديمة والضرائب المرتبطة بها
            InvoiceItem::where('quotes_purchase_order_id', $quotation->id)->delete();
            TaxInvoice::where('invoice_id', $quotation->id)->where('type_invoice', 'quotation_purchase')->delete();

            // معالجة عناصر الفاتورة الجديدة
            if ($request->has('items')) {
                $invoiceItems = [];

                foreach ($request->items as $item) {
                    $product = Product::find($item['product_id']);

                    // حساب المجموع الفرعي لكل منتج
                    $item_subtotal = floatval($item['quantity']) * floatval($item['unit_price']);
                    $subtotal += $item_subtotal;

                    // حساب خصم العنصر — انظر التعليق في store(): النموذج يرسل
                    // `discount` كمبلغ أو كنسبة حسب `discount_type` فقط، بلا
                    // حقل `discount_percentage` منفصل
                    $item_discount = 0;
                    if (isset($item['discount']) && floatval($item['discount']) > 0) {
                        if (($item['discount_type'] ?? 'amount') === 'percentage') {
                            $item_discount = ($item_subtotal * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }
                    $total_item_discount += $item_discount;

                    // ضرائب العنصر — انظر التعليق في store(): المتضمَّنة
                    // تُستخرَج من السعر ولا تُضاف فوقه
                    $tax_1_id = $item['tax_1_id'] ?? null;
                    $tax_2_id = $item['tax_2_id'] ?? null;
                    $tax_1_rate = $tax_1_id ? PurchaseTaxCalculator::rate($tax_1_id) : floatval($item['tax_1'] ?? 0);
                    $tax_2_rate = $tax_2_id ? PurchaseTaxCalculator::rate($tax_2_id) : floatval($item['tax_2'] ?? 0);

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
                            'invoice_id' => $quotation->id,
                            'type' => $setting->type,
                            'rate' => $detail['rate'],
                            'value' => $detail['amount'],
                            'type_invoice' => 'quotation_purchase',
                        ]);
                    }

                    $total_item_tax += $line['tax'];
                    $included_item_tax += $line['base'] - $line['net'];

                    $item_total = $line['total'];

                    $invoiceItems[] = ProductUnits::fieldsFor($item, (float) $item['quantity']) + [
                        'quotes_purchase_order_id' => $quotation->id,
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount' => $item_discount,
                        'tax_1' => $tax_1_rate,
                        'tax_1_id' => $tax_1_id ?: null,
                        'tax_2' => $tax_2_rate,
                        'tax_2_id' => $tax_2_id ?: null,
                        'total' => $item_total,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // إضافة البنود الجديدة
                InvoiceItem::insert($invoiceItems);
            }

            // بلا طرحها يُحسب المتضمَّن مرّتين — انظر store()
            $subtotal = round($subtotal - $included_item_tax, 2);

            // تبويب التسوية — انظر التعليق في store()
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

            // حساب التسوية
            $adjustment_amount = 0;
            if ($enableSettlementSetting && $request->adjustment_value && $request->adjustment_type) {
                $adjustment_value = floatval($request->adjustment_value);
                if ($request->adjustment_type == 'discount') {
                    $adjustment_amount = -$adjustment_value;
                } else {
                    $adjustment_amount = $adjustment_value;
                }
            }

            /* الشحن: مع الإعداد المطفأ لا يُقرأ من الطلب — ويبقى المحفوظ على
             * حاله فلا يُصفَّر شحنُ مستندٍ حُفظ قبل الإطفاء بمجرّد فتحه */
            $shipping_cost = $enableSettlementSetting
                ? floatval($request->shipping_cost ?? 0)
                : floatval($quotation->shipping_cost ?? 0);
            $shipping_tax = 0;
            if ($shipping_cost > 0 && $request->tax_id) {
                $shipping_tax_rate = TaxSitting::find($request->tax_id);
                if ($shipping_tax_rate) {
                    $shipping_tax = ($shipping_cost * $shipping_tax_rate->tax) / 100;

                    TaxInvoice::create([
                        'name' => $shipping_tax_rate->name.' (شحن)',
                        'invoice_id' => $quotation->id,
                        'type' => $shipping_tax_rate->type,
                        'rate' => $shipping_tax_rate->tax,
                        'value' => $shipping_tax,
                        'type_invoice' => 'quotation_purchase',
                    ]);
                }
            }

            // حساب إجمالي الخصومات
            $total_discount = $total_item_discount + $additional_discount;

            // حساب إجمالي الضرائب
            $total_tax = $total_item_tax + $shipping_tax;

            // حساب المجموع النهائي
            $grand_total = $subtotal - $total_discount + $adjustment_amount + $shipping_cost + $total_tax;

            // تحديث عرض السعر بالمبالغ المحسوبة
            $quotation->update([
                'subtotal' => $subtotal,
                'total_discount' => $total_discount,
                'total_tax' => $total_tax,
                // حصّةُ الشحن وحدها كذلك: تسير مع العرض إلى أمر الشراء ثمّ
                // إلى الفاتورة، فتُرحَّل إلى «ضريبة شحن مشتريات» لا تندمج
                'shipping_tax' => $shipping_tax,
                'grand_total' => $grand_total,
                'shipping_cost' => $shipping_cost,

                /* الخصم العامّ بحقله لا مطويّاً في `total_discount` وحده.
                   كان يُحسَب ثمّ يُطرَح ولا يُكتب في `discount_amount` قطّ —
                   لا هنا ولا في `create()` — فيبقى العمود صفراً أبداً.
                   وثلاثةٌ تنكسر به: شاشةُ التعديل لا تستطيع إعادة عرضه،
                   والتحويلُ إلى أمر شراء ينسخ صفراً مع `total_discount`
                   غير الصفر فيصل الأمرُ متناقضاً، والاستلامُ يوزّع
                   `discount_amount` وحده على الفواتير فلا يصل خصمُ العرض
                   إلى الفاتورة ولا إلى قيدها. والمحفوظ **المبلغ المحسوب**
                   لا المُدخَل، نظيرَ `purchase_invoices.discount_amount`. */
                'discount_amount' => round($additional_discount, 2),
                'discount_type' => $request->discount_type === 'percentage' ? 'percentage' : 'amount',

                'adjustment_amount' => $adjustment_amount,
                'adjustment_label' => $request->adjustment_label,
                'notes' => $request->notes,
            ]);

            // حفظ تفاصيل الخصم الإضافي إذا وجد
            if ($additional_discount > 0) {
                TaxInvoice::create([
                    'name' => 'خصم إضافي',
                    'invoice_id' => $quotation->id,
                    'type' => 'discount',
                    'rate' => $request->discount_type == 'percentage' ? floatval($request->discount_amount) : 0,
                    'value' => $additional_discount,
                    'type_invoice' => 'quotation_purchase',
                ]);
            }

            // حفظ تفاصيل التسوية إذا وجدت
            if ($adjustment_amount != 0) {
                TaxInvoice::create([
                    'name' => $request->adjustment_label ?? 'تسوية',
                    'invoice_id' => $quotation->id,
                    'type' => $request->adjustment_type,
                    'rate' => 0,
                    'value' => abs($adjustment_amount),
                    'type_invoice' => 'quotation_purchase',
                ]);
            }
            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $quotation,
                $this->logLine('updated', $quotation->code),
                $changes,
            );

            DB::commit();

            return redirect()->route('ViewPurchasePrice.show', $quotation->id)->with('success', 'تم تحديث عرض السعر بنجاح');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'update', $id);

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء تحديث عرض السعر: '.$e->getMessage())
                ->withInput();
        }
    }

    public function edit($id)
    {
        $purchaseQuotation = PurchaseQuotationView::with(['supplier', 'account', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

        // الاعتماد نهائي: لا تعديل بعده — نفس القيد في update() وفي PurchaseRequestsController::edit()
        if ($purchaseQuotation->status !== 'Under Review') {
            return redirect()->back()->with('error', __('purchase_orders.messages.edit_locked'));
        }

        // فرعُ الشريط العلويّ، ومورِّدُ العرض المحفوظ يبقى ولو كان على جهةٍ أخرى
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), array_filter([$purchaseQuotation->supplier_id]))->get();
        $taxs = TaxSitting::all();
        $accounts = Account::all();
        $items = Product::all();
        $branches = \App\Models\Branch::orderBy('name')->get();

        return view('purchases::purchases.view_purchase_price.edit', compact('purchaseQuotation', 'taxs', 'suppliers', 'accounts', 'items', 'branches'));
    }

    public function destroy($id)
    {
        $quotation = PurchaseQuotationView::findOrFail($id);
        $this->authorizeCreatorScope($quotation->created_by, null, $quotation->branch_id);

        try {
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $quotation,
                $this->logLine('deleted', $quotation->code),
            );

            $quotation->delete();

            return redirect()->route('ViewPurchasePrice.index')->with('success', 'تم حذف عرض السعر بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'delete', $id);

            return redirect()->back()->with('error', __('purchase_cycle.messages.delete_failed', ['document' => $this->documentLabel()]));
        }
    }

    /**
     * حذف جماعي — يقابل زرّ «حذف المحدَّد» في الفهرس، بنفس نمط
     * PurchaseRequestsController::bulkDelete()/QuotationsController::bulkDelete().
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:purchase_quotations_view,id',
        ]);

        try {
            DB::beginTransaction();

            /* النطاقُ يُطبَّق على الجلب لا على الواجهة وحدَها: المعرّفات
               تصل في متن الطلب، فمن لا يرى مستنداً كان يحذفه بإرسال
               رقمه. والقيدُ على محور التخويل (`created_by`) لا على فرع
               الهيدر — هذا الأخير فلترُ عرضٍ يتبدّل بالمُنتقي. */
            $quotations = $this->scopeToCreator(PurchaseQuotationView::whereIn('id', $data['ids']))->get();

            foreach ($quotations as $quotation) {
                OperationLog::record(
                    'deleted',
                    self::LOG_MODULE,
                    $quotation,
                    $this->logLine('deleted', $quotation->code),
                );

                $quotation->delete();
            }

            DB::commit();

            return redirect()->back()->with('success', __('purchase_orders.bulk.deleted', ['count' => $quotations->count()]));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'bulk_delete');

            return redirect()->back()->with('error', __('purchase_orders.bulk.delete_failed'));
        }
    }

    public function updateStatus($id, Request $request)
    {
        try {
            $quotation = PurchaseQuotationView::findOrFail($id);
            $this->authorizeCreatorScope($quotation->created_by, null, $quotation->branch_id);

            // التحقق من الحالة المرسلة
            if (! in_array($request->status, [2, 3])) {
                return back()->with('error', 'حالة غير صالحة');
            }

            // تحديث الحالة
            $oldStatus = $quotation->status;
            $quotation->status = $request->status;
            $quotation->save();

            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $quotation,
                $this->logLine('status_changed', $quotation->code),
                ['old' => ['status' => $oldStatus], 'new' => ['status' => $quotation->status]],
            );

            // رسالة نجاح مخصصة حسب الحالة
            $message = $request->status == 2 ? 'تمت الموافقة على عرض السعر بنجاح' : 'تم رفض عرض السعر';

            return redirect()->route('ViewPurchasePrice.show', $id)->with('success', $message);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update_status', $id);

            return back()->with('error', 'حدث خطأ أثناء تحديث الحالة: '.$e->getMessage());
        }
    }

    public function exportPDF($id, $template = 'default')
    {
        try {
            $purchaseQuotation = PurchaseQuotationView::with(['supplier', 'account', 'items.product', 'creator'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseQuotation,
                $this->logLine('exported', $purchaseQuotation->code),
            );

            /* القالب المصمَّم في الإعدادات يُعرض صفحةَ طباعة تُحفظ PDF من
               المتصفّح — Dompdf كان يقلب العربية ويُسقط الجدول (انظر
               ServesDesignedTemplatePage). المدمج احتياطٌ حين لا قالب للنوع. */
            if ($page = $this->configuredTemplatePage($id, $template, $purchaseQuotation->code)) {
                return $page;
            }

            $pdf = Pdf::loadView($this->builtinTemplateView($template), compact('purchaseQuotation'));

            return $pdf->download('عرض-سعر-'.$purchaseQuotation->code.'-'.$template.'.pdf');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return back()->with('error', 'حدث خطأ أثناء تصدير PDF: '.$e->getMessage());
        }
    }

    public function convertToPurchaseOrder($id, Request $request)
    {
        try {
            // جلب عرض السعر مع جميع البيانات المطلوبة
            $quotation = PurchaseQuotationView::with(['items', 'supplier'])->findOrFail($id);
            $this->authorizeCreatorScope($quotation->created_by, null, $quotation->branch_id);

            if ($quotation->status != 'approval') {
                return back()->with('error', 'لا يمكن تحويل عرض السعر غير المعتمد إلى أمر شراء');
            }

            if ($quotation->converted_to_po) {
                return back()->with('error', 'تم تحويل عرض السعر هذا إلى أمر شراء من قبل');
            }

            // استخدام مورد عرض السعر مباشرة
            $supplierId = $quotation->supplier_id;

            if (! $supplierId) {
                return back()->with('error', 'عرض السعر لا يحتوي على مورد');
            }

            DB::beginTransaction();

            /* ══════════ أمر الشراء الناتج ══════════

               ما كان هنا قبلُ لم يكن ينسخ «كلّ البيانات» رغم ما يقوله
               تعليقُه — ستّةٌ من حقوله لا وجود لها في `purchase_quotations_view`
               (`account_id`, `payment_terms`, `currency`, `adjustment_amount`)
               فتقرأ `null`، وستّةٌ ليست في `$fillable` الوجهة
               (`quotation_id`, `delivery_date`, `adjustment_*`, `payment_terms`,
               `currency`) فتُسقَط صامتةً. وثلاثةُ أخطاءٍ أثقل:

                 • `status => 1` — والعمود `ENUM('disagree','approval',
                   'Under Review', …)`، وMySQL يقرأ العدد في عمود ENUM فهرساً
                   لا قيمة: فكلّ أمرٍ ناتجٍ عن تحويل يُحفَظ **«مرفوض»**، لا
                   «تحت المراجعة» كما يَعِد التعليق. (نفس الخطأ أُصلح في
                   `PurchaseOrdersController::store()/update()` من قبل.)
                 • `code` مبنيٌّ باليد من `count()+1` — يتجاوز «تسلسل أرقام
                   المستندات» فيخالف بادئةَ الأمر وصيغتَه، ويتصادم متى حُذف
                   أمرٌ سابق أو حُوِّل اثنان معاً.
                 • `branch_id` لا يُكتب إطلاقاً — فالأمر يسقط خارج نطاق
                   الفرع، لا يراه صاحبُ الفرع في قائمته ولا في تقاريره.

               والتاريخ كان `now()` لا تاريخَ العرض، ومهلةُ الصلاحية
               (`valid_days`) تُهمَل فيصل الأمر بلا تاريخ استحقاق. */
            $orderDate = $quotation->date ?: now()->toDateString();
            $validDays = (int) ($quotation->valid_days ?? 0);

            $purchaseOrder = PurchaseInvoice::create([
                'supplier_id' => $supplierId,
                'code' => next_document_number(PurchaseOrdersController::NUMBER_SECTION),
                'type' => 'Requested', // نوع أمر شراء
                'status' => 'Under Review',

                // سياق المستند — الفرع من العرض، وإلا فرعُ المستخدم الحالي
                'branch_id' => $quotation->branch_id
                    ?: \App\Support\Scopes::resolveBranchId(auth()->user()),

                'date' => $orderDate,
                'terms' => $validDays,
                'due_date' => $validDays > 0
                    ? \Carbon\Carbon::parse($orderDate)->addDays($validDays)->toDateString()
                    : null,

                // نسخ جميع المبالغ المحسوبة من عرض السعر
                'subtotal' => $quotation->subtotal ?? 0,
                'total_discount' => $quotation->total_discount ?? 0,
                'total_tax' => $quotation->total_tax ?? 0,
                'shipping_tax' => $quotation->shipping_tax ?? 0,
                'grand_total' => $quotation->grand_total ?? 0,
                'shipping_cost' => $quotation->shipping_cost ?? 0,

                /* الخصم العامّ ونوعه — كانا يُهمَلان، فيصل الأمرُ
                   بـ`total_discount` غير صفر و`discount_amount` صفراً: أوّلُ
                   حفظٍ لشاشة التعديل يعيد الحساب بلا خصم فيقفز الإجمالي.

                   والنوعُ يُترجَم لا يُنسخ: الجدولان لا يتكلّمان لغةً واحدة —
                   `purchase_quotations_view.discount_type` هو
                   `ENUM('amount','percentage')`، و`purchase_invoices.discount_type`
                   عددٌ صغير (١ مبلغ، ٢ نسبة) كما تكتبه
                   `PurchaseOrdersController::store()`. والنسخُ الحرفيّ يرفعه
                   MySQL: «Incorrect integer value 'amount'».

                   و`tax_type` لا يُنسخ أصلاً: الاسمُ واحدٌ والمعنى مختلف —
                   في العرض تصنيفُ ضريبةٍ `ENUM('vat','zero','exempt')`، وفي
                   الأمر عددٌ صغير لا يكتبه النموذج إطلاقاً (يبقى على
                   الافتراضي ١، تماماً كما في الحفظ اليدوي). */
                'discount_amount' => $quotation->discount_amount ?? 0,
                'discount_type' => $quotation->discount_type === 'percentage' ? 2 : 1,

                'notes' => $quotation->notes,
                'created_by' => Auth::id(),
            ]);

            // نسخ عناصر عرض السعر إلى بنود أمر الشراء
            // حل المشكلة: استخدام unique() لضمان عدم تكرار العناصر
            $items = $quotation->items()->get()->unique('id');
            $invoiceItems = [];

            foreach ($items as $item) {
                $invoiceItems[] = [
                    'purchase_invoice_id' => $purchaseOrder->id,
                    'quotes_purchase_order_id' => $quotation->id,
                    'product_id' => $item->product_id,
                    'item' => $item->item,
                    'quantity' => $item->quantity,
                    // الوحدة تُنسخ مع البند، وإلا وصل أمرُ الشراء بـ«عبوة»
                    // كمّيتُها بالحبّة فيستلم المستودع أقلّ مما اشتُري
                    'product_unit_id' => $item->product_unit_id,
                    'unit_name' => $item->unit_name ?: null,
                    'conversion_factor' => $item->conversion_factor ?: 1,
                    'quantity_in_base_unit' => $item->quantity_in_base_unit,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount ?? 0,
                    // نوعُ خصم البند مع مقداره: بدونه يُقرأ خصمُ النسبة مبلغاً
                    'discount_type' => $item->discount_type ?: 1,
                    'tax_1' => $item->tax_1 ?? 0,
                    'tax_2' => $item->tax_2 ?? 0,

                    /* ومعرّفا الضريبة — وهما ما كان يسقط.

                       `PurchaseReceivingController::convertToInvoice()` يقرأ
                       `tax_1_id`/`tax_2_id` ليجد الإعداد في «إعدادات الضريبة»:
                       منه يعرف النسبة ونوعَها (متضمَّنة أم مضافة) واسمَها.
                       فبلا المعرّف:
                         • الضريبةُ المتضمَّنة تُضاف فوق السعر بدل أن تُستخرَج
                           منه — فيتغيّر **المبلغ** لا التسمية وحدها
                         • و`PurchaseTaxCalculator::find(null)` يردّ فراغاً
                           فيُتخطّى سطرُ `TaxInvoice`، فتصل الفاتورة بضريبةٍ
                           في إجماليها بلا تفصيلٍ لها — ومعه يضيع فصلُ ضريبة
                           الشحن عن ضريبة البنود في القيد */
                    'tax_1_id' => $item->tax_1_id ?: null,
                    'tax_2_id' => $item->tax_2_id ?: null,

                    'total' => $item->total,
                    'description' => $item->description ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // إدراج جميع العناصر مرة واحدة لتحسين الأداء
            if (! empty($invoiceItems)) {
                DB::table('invoice_items')->insert($invoiceItems);
            }

            // نسخ جميع الضرائب والخصومات من جدول TaxInvoice
            $taxInvoices = TaxInvoice::where('invoice_id', $quotation->id)
                ->where('type_invoice', 'quotation_purchase')
                ->get();

            foreach ($taxInvoices as $taxInvoice) {
                TaxInvoice::create([
                    'name' => $taxInvoice->name,
                    'invoice_id' => $purchaseOrder->id,
                    'type' => $taxInvoice->type,
                    'rate' => $taxInvoice->rate,
                    'value' => $taxInvoice->value,
                    'type_invoice' => 'purchase', // تغيير النوع إلى أمر شراء
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // تحديث حالة عرض السعر — كانت تُكتَب `'approval'`، أي نفس القيمة
            // التي يشترطها الشرط أعلاه قبل التحويل (`status != 'approval'`)،
            // فلا تتغيّر فعلياً ولا يميَّز عرضٌ حُوِّل عن عرضٍ لم يُحوَّل بعد
            // إلا بعمود `converted_to_po` المنطقي وحده
            $quotation->update([
                'converted_to_po' => true,
                'status' => 'Convert to Order',
                // 'updated_by' => Auth::id(),
            ]);

            // سطران: أحدهما في خطّ عرض السعر والآخر في خطّ أمر الشراء الناتج
            OperationLog::record(
                'converted',
                self::LOG_MODULE,
                $quotation,
                $this->logLine('converted', $quotation->code),
            );

            OperationLog::record(
                'created',
                PurchaseOrdersController::LOG_MODULE,
                $purchaseOrder,
                __('purchase_cycle.log.created', [
                    'document' => __('purchase_cycle.documents.purchase_order_request'),
                    'code' => $purchaseOrder->code,
                ]),
            );

            DB::commit();

            return redirect()
                ->route('PurchaseOrders.show', $purchaseOrder->id)
                ->with('success', 'تم تحويل عرض السعر إلى أمر شراء بنجاح');

        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'convert', $id);

            return back()->with('error', 'حدث خطأ أثناء تحويل عرض السعر: '.$e->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        $PurchaseQuotation = PurchaseQuotationView::findOrFail($id);
        $this->authorizeCreatorScope($PurchaseQuotation->created_by);

        DB::beginTransaction();
        try {
            $PurchaseQuotation->update([
                'status' => 'approval',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_note' => $request->note,
            ]);

            OperationLog::record(
                'approved',
                self::LOG_MODULE,
                $PurchaseQuotation,
                $this->logLine('approved', $PurchaseQuotation->code)
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
        $PurchaseQuotation = PurchaseQuotationView::findOrFail($id);
        $this->authorizeCreatorScope($PurchaseQuotation->created_by);

        DB::beginTransaction();
        try {
            $PurchaseQuotation->update([
                'status' => 'disagree',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);
            OperationLog::record(
                'rejected',
                self::LOG_MODULE,
                $PurchaseQuotation,
                $this->logLine('rejected', $PurchaseQuotation->code)
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

    /*
     * تنويه: cancelApproval() أُزيلت عمداً — اعتماد عرض سعر المورّد صار
     * نهائياً لا رجعة فيه (لا تعديل ولا إلغاء اعتماد بعد الموافقة)، بنفس
     * منطق PurchaseRequestsController/QuotationsController تماماً.
     */

    // دالة التراجع عن الرفض
    public function undoRejection(Request $request, $id)
    {
        $PurchaseQuotation = PurchaseQuotationView::findOrFail($id);
        $this->authorizeCreatorScope($PurchaseQuotation->created_by);

        // التحقق من أن الطلب مرفوض أصلاً
        if ($PurchaseQuotation->status !== 'disagree') {
            return redirect()->back()->with('error', 'لا يمكن التراجع عن الرفض، الطلب غير مرفوض');
        }

        DB::beginTransaction();
        try {
            $PurchaseQuotation->update([
                'status' => 'Under Review',
                'rejected_by' => null,
                'rejected_at' => null,
                'restored_by' => auth()->id(),
                'restored_at' => now(),
            ]);
            OperationLog::record(
                'rejection_undone',
                self::LOG_MODULE,
                $PurchaseQuotation,
                $this->logLine('rejection_undone', $PurchaseQuotation->code),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تم التراجع عن الرفض بنجاح وإعادة الطلب للمراجعة');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'undo_rejection', $id);

            return redirect()->back()->with('error', 'حدث خطأ أثناء التراجع عن الرفض');
        }
    }

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

            $purchaseQuotation = PurchaseQuotationView::findOrFail($id);
            $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

            // التعامل مع المرفق إذا تم رفعه
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time().'_'.$file->getClientOriginalName();
                $attachmentPath = $file->storeAs('purchase_quotations/notes', $fileName, 'public');
            }

            // إنشاء الملاحظة مع تحديد النوع مباشرة
            $clientRelation = ClientRelation::create([
                'process' => $request->process,
                'time' => $request->time,
                'date' => $request->date,
                'quotation_id' => $id,
                'employee_id' => auth()->user()->id,
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'type' => $request->input('type', 'quotation'), // قيمة افتراضية
            ]);
            // تسجيل النشاط في سجل الأنشطة
            OperationLog::record(
                'note_added',
                self::LOG_MODULE,
                $purchaseQuotation,
                __('purchase_cycle.log.note_added', [
                    'document' => $this->documentLabel(),
                    'code' => $purchaseQuotation->code,
                    'note' => $request->process,
                ]),
            );
            notifications::create([
                'user_id' => auth()->user()->id,
                'title' => 'ملاحظة جديدة',
                'message' => 'تم اضافة ملاحظة جديدة لعرض السعر رقم '.$purchaseQuotation->code,
                'description' => $request->description,
            ]);

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
                    'attachment' => $attachmentPath ? asset('storage/'.$attachmentPath) : null,
                ],
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'add_note', $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الملاحظة: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getNotes($id)
    {
        try {
            // المستندُ الأمّ بتخويله — كما في addNote؛ كان المعرّفُ يكفي لقراءة ملاحظات أيّ مستند
            $parent = \App\Models\PurchaseQuotationView::findOrFail($id);
            $this->authorizeCreatorScope($parent->created_by);
            $notes = ClientRelation::where('quotation_id', $id)
                ->where('type', 'quotation')
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->get();

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
                'notes' => $formattedNotes->toArray(), // تحويل إلى array
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.ViewPurchasePrice', 'getNotes');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الملاحظات: '.$e->getMessage(),
            ], 500);
        }

    }

    public function destroyNote($id)
    {
        try {
            $note = ClientRelation::findOrFail($id);
            // تخويلُ المستند الأمّ قبل الحذف — كان يُحذف بأيّ معرّف
            $parent = \App\Models\PurchaseQuotationView::findOrFail($note->quotation_id);
            $this->authorizeCreatorScope($parent->created_by);
            $quotationId = $note->quotation_id;
            $process = $note->process;

            $note->delete();

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
            OperationLog::failure($e, self::LOG_MODULE, 'delete_note', $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطاء اثناء حذف الملاحظة: '.$e->getMessage(),
            ], 500);
        }
    }

    // دالة تكرار عرض السعر
    public function duplicate($id)
    {
        try {
            // نسخ العرض يُنشئ سجلاً جديداً فوراً بلا شاشة تأكيد — يستهلك حصّة
            // «عروض أسعار الموردين» تماماً مثل store()، فيُحرَس بنفس الفحص
            $subscriptionGate = app(\App\Services\Fawtra\SubscriptionGate::class);
            if (! $subscriptionGate->allows('purchase_price_quotations')) {
                return back()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «عروض أسعار الموردين». راجع لوحة تحكّم فوترة للترقية.');
            }
            $priceQuotationQuota = $subscriptionGate->check('purchase_price_quotations');
            if (! $priceQuotationQuota['allowed']) {
                return back()->with('error', $priceQuotationQuota['reason']);
            }

            DB::beginTransaction();

            $originalQuotation = PurchaseQuotationView::with(['items'])->findOrFail($id);
            $this->authorizeCreatorScope($originalQuotation->created_by, null, $originalQuotation->branch_id);

            // إنشاء نسخة جديدة
            $newQuotation = $originalQuotation->replicate();
            $newQuotation->code = $this->generateQuotationCode();
            // العمود فريد في الجدول، و`replicate()` ينسخه كما هو من الأصل —
            // فبلا تحديثه هنا يسقط الحفظ بـ«Duplicate entry». وهو يساوي
            // الرقم نفسه كما في store()
            $newQuotation->purchase_price_number = $newQuotation->code;
            $newQuotation->status = 'Under Review'; // تحت المراجعة
            $newQuotation->created_by = Auth::id();
            // الفرع يُعاد تعيينه لفرع من يُنشئ النسخة، لا فرع العرض الأصلي
            // الذي نسخه replicate() تلقائياً
            $newQuotation->branch_id = \App\Support\Scopes::resolveBranchId(auth()->user());
            $newQuotation->save();

            // نسخ المنتجات
            foreach ($originalQuotation->items as $item) {
                $newItem = $item->replicate();
                $newItem->quotes_purchase_order_id = $newQuotation->id;
                $newItem->save();
            }

            OperationLog::record(
                'duplicated',
                self::LOG_MODULE,
                $newQuotation,
                $this->logLine('duplicated', $newQuotation->code),
            );

            DB::commit();

            return redirect()->route('ViewPurchasePrice.edit', $newQuotation->id)->with('success', 'تم نسخ طلب عرض السعر بنجاح');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'duplicate', $id);

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء نسخ الطلب: '.$e->getMessage());
        }
    }

    /**
     * رقم عرض سعر جديد من إعدادات «تسلسل أرقام المستندات» — لا
     * `max(code) + 1` الذي يتجاهل البادئة والصيغة المضبوطتين هناك.
     */
    private function generateQuotationCode()
    {
        return next_document_number(self::NUMBER_SECTION);
    }
}
