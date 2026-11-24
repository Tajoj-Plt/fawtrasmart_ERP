<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\InvoiceItem;
use App\Models\Log as ModelsLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationSupplier;
use App\Models\Supplier;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\AccountingPeriodGuard;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\ConvertedLines;
use Modules\Purchases\Support\ProductUnits;

class QuotationsController extends Controller
{
    use Concerns\ServesDesignedTemplatePage;
    use ScopesToEmployeeHierarchy;

    /** نوع المستند في شاشة «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'quotation_request';

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'quotation-request';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمستند */
    public const LOG_MODULE = 'purchase_quotation';

    public function __construct()
    {
        // صلاحيات طلبات عروض الأسعار — كتالوج app\Constants\PurchasesPermissions
        // (مجموعة 'quotations')، لا الأسماء القديمة بنمط الشرطة السفلية.
        // النمط مطابق لـ PurchaseRequestsController تماماً: تصدير/طباعة PDF
        // بصلاحية .export مستقلّة لا ضمن .view.
        $this->middleware('permission:purchases.quotations.view', ['only' => ['index', 'paginate', 'show']]);
        // duplicate() تُنشئ سجلاً جديداً فتُصنَّف صلاحية إنشاء لا عرض
        $this->middleware('permission:purchases.quotations.create', ['only' => ['create', 'store']]);
        // النسخُ صلاحيتُه المستقلّة
        $this->middleware('permission:purchases.quotations.duplicate', ['only' => ['duplicate']]);
        $this->middleware('permission:purchases.quotations.edit', ['only' => ['edit', 'update']]);
        // `bulkDelete` معها: كان محروساً على مستوى المسار وحده بينما نظيراه
        // في PurchaseRequestsController وViewPurchasePriceController يضمّانه
        // إلى مجموعة الحذف هنا أيضاً. والطبقتان مقصودتان: المسار قد يُنسخ
        // أو يُنقل بلا وسيطه، والمنشئ يحرس الدالّة أياً كان المسار الذي بلغها.
        $this->middleware('permission:purchases.quotations.delete', ['only' => ['destroy', 'bulkDelete']]);
        $this->middleware('permission:purchases.quotations.approve_reject', ['only' => ['approve', 'reject']]);
        $this->middleware('permission:purchases.quotations.export', ['only' => ['generatePdf', 'printView', 'exportCsv']]);
    }

    public function index(Request $request)
    {
        // فلترُ المورِّد يتبع فرعَ الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers()->get();

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax()) {
            return $this->getFilteredData($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $purchaseQuotation = $this->getFilteredData($request, false);

        /* عدّاد الحالات لشريط الملخّص — استعلام واحد لا ثلاثة.
           ويُقيَّد بنطاق المستخدم نفسِه الذي يُقيَّد به الجدول
           (`getFilteredData`): كان استعلاماً عارياً فيقرأ صاحبُ الفرع
           عدّاد المنشأة كلِّها بينما لا يرى تحته إلّا سجلّاته. */
        $counts = $this->scopeToBranchNode($this->scopeToCreator(PurchaseQuotation::query()), $request)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $counts->sum(),
            'review' => (int) ($counts['Under Review'] ?? 0),
            'approved' => (int) ($counts['approval'] ?? 0),
            'rejected' => (int) ($counts['disagree'] ?? 0),
        ];

        // حالة حدّ اشتراك فوترة لميزة «طلبات عروض الأسعار» — known يبقى false
        // حتى تُعرِّف فوترة هذه الميزة في كتالوجها (انظر تعليق
        // UsageReporter::catalog())، فلا تظهر الشارة في الواجهة قبل ذلك.
        $subscriptionLimit = app(SubscriptionGate::class)->limit('purchase_quotations');

        return view('purchases::purchases.quotations.index', compact('suppliers', 'purchaseQuotation', 'stats', 'subscriptionLimit'));
    }

    private function getFilteredData(Request $request, $returnJson = true)
    {
        // ما يعرضه الجدول يُحمَّل مع الاستعلام لا صفاً صفاً
        $query = PurchaseQuotation::query()->with(['creator', 'suppliers'])->withCount('items');

        // تطبيق تصفية نطاق البيانات حسب دور المستخدم — انظر App\Support\Scopes
        $query = $this->scopeToCreator($query);

        /* «تابع لـ» — العقدةُ المختارة في الشاشة، وإلّا العقدةُ النشطة من
           الهيدر، وهي تعني العقدةَ وكلَّ ما تحتها. كانت هذه الشاشة لا تقرأ
           الفرع إطلاقاً، فيرى صاحبُ المنطقة مستنداتِ المنشأة كلَّها. */
        $query = $this->scopeToBranchNode($query, $request);

        // البحث بالكود
        if ($request->filled('code')) {
            $query->where('code', 'LIKE', '%'.$request->code.'%');
        }

        // البحث بالمورد
        if ($request->filled('supplier_id')) {
            $query->whereHas('suppliers', function ($q) use ($request) {
                $q->where('supplier_id', $request->supplier_id);
            });
        }

        // البحث بتاريخ الطلب (من)
        if ($request->filled('order_date_from')) {
            $query->whereDate('order_date', '>=', $request->order_date_from);
        }

        // البحث بتاريخ الطلب (إلى)
        if ($request->filled('order_date_to')) {
            $query->whereDate('order_date', '<=', $request->order_date_to);
        }

        // البحث بتاريخ الاستحقاق (من)
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }

        // البحث بتاريخ الاستحقاق (إلى)
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }

        // البحث بالحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // البحث بحالة المتابعة
        if ($request->filled('follow_status')) {
            $query->where('follow_status', $request->follow_status);
        }

        // تنفيذ الاستعلام وترتيب النتائج
        $purchaseQuotation = $query->latest()->paginate(10);

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'data' => view('purchases::purchases.quotations.partials.table', compact('purchaseQuotation'))->render(),
                'pagination' => view('purchases::purchases.quotations.partials.pagination', compact('purchaseQuotation'))->render(),
                'total' => $purchaseQuotation->total(),
                'current_page' => $purchaseQuotation->currentPage(),
                'last_page' => $purchaseQuotation->lastPage(),
            ]);
        }

        return $purchaseQuotation;
    }

    // دالة للتعامل مع طلبات الـ pagination عبر Ajax
    public function paginate(Request $request)
    {
        if ($request->ajax()) {
            return $this->getFilteredData($request);
        }

        return redirect()->route('Quotations.index');
    }

    public function show($id)
    {
        $productDetails = InvoiceItem::where('purchase_quotation_id', $id)->get();

        $purchaseQuotation = PurchaseQuotation::with([
            'suppliers', 'creator', 'updater',
            'generatedQuotations', // <-- نضيف هذه
        ])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

        // المورِّدون على فرع الشريط العلويّ، ومورِّدو هذا الطلب يبقون
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers(
            null,
            $purchaseQuotation->suppliers->pluck('id')->all()
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

        return view('purchases::purchases.quotations.show', compact(
            'purchaseQuotation', 'productDetails', 'suppliers', 'logs',
            'documentTemplates', 'defaultTemplate', 'prefersPdf'
        ));
    }

    public function create(Request $request)
    {
        $products = Product::all();
        // المورِّدون على فرع الشريط العلويّ وما يتبعه — {@see \App\Support\HeaderBranchScope}
        $suppliers = Supplier::query()
            ->when(\App\Support\HeaderBranchScope::nodeId(), fn ($q, $node) => $q->forBranch($node))
            ->get();

        // جلب طلب الشراء وبنوده إذا تم تمرير order_id
        $purchaseOrder = null;
        $orderItems = collect();

        if ($request->filled('order_id')) {
            $purchaseOrder = PurchaseOrder::find($request->query('order_id'));
            if ($purchaseOrder) {
                $orderItems = $purchaseOrder->items ?? collect();
            }
        }

        // معاينة الرقم التالي بلا استهلاك، ليُعرض في نموذج الإنشاء
        $code = peek_document_number(self::NUMBER_SECTION);

        $branches = \App\Models\Branch::orderBy('name')->get();

        return view('purchases::purchases.quotations.create', compact('suppliers', 'products', 'purchaseOrder', 'orderItems', 'code', 'branches'));
    }

    public function store(Request $request)
    {
        try {
            // التحقق من البيانات المدخلة
            $validatedData = $request->validate(
                [
                    'title' => 'required|string|max:255',
                    'order_date' => 'required|date',
                    'due_date' => 'nullable|date|after_or_equal:order_date',
                    'supplier_id' => 'required|array|min:1',
                    'supplier_id.*' => 'exists:suppliers,id',
                    'notes' => 'nullable|string',
                    'attachments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
                    'items' => 'required|array|min:1',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.quantity' => 'required|numeric|min:1',
                    // الوحدة: `validate()` يُسقط كلّ مفتاحٍ بلا قاعدة، والبنود تُحفظ من
                    // `$validatedData` — فبدون هذه كان «عبوة» يصل عرضَ السعر «حبّة»
                    'items.*.product_unit_id' => 'nullable',
                    'items.*.unit_name' => 'nullable|string|max:255',
                    'items.*.conversion_factor' => 'nullable|numeric|min:0',
                    'order_id' => 'nullable|exists:purchase_orders,id',
                    // يُعتمَد فقط لمن يملك صلاحية اختيار الفرع — انظر Scopes::canSelectBranch()
                    'branch_id' => 'nullable|integer|exists:branches,id',
                ],
                [
                    'supplier_id.required' => 'يجب اختيار مورد واحد على الأقل',
                    'supplier_id.min' => 'يجب اختيار مورد واحد على الأقل',
                    'supplier_id.*.exists' => 'أحد الموردين المحددين غير موجود',
                    'items.required' => 'يجب إضافة بند واحد على الأقل',
                    'items.*.product_id.required' => 'يجب اختيار المنتج',
                    'items.*.quantity.required' => 'يجب تحديد الكمية',
                ],
            );

            // الفترة المحاسبية — لا قيد يُرحَّل من هذا الكنترولر، لكن الفحص
            // عند الحفظ يمنع توثيق طلب بتاريخٍ من فترة أُقفلت.
            if ($blocked = AccountingPeriodGuard::blockReason($validatedData['order_date'])) {
                return back()->withInput()->with('error', $blocked);
            }

            // بنود التحويل: الشاشة تقفلها، وهذا ما يجعل القفل قفلاً.
            // `readonly` سمةٌ في المتصفّح تُنزَع بأدوات المطوّر في ثوانٍ،
            // فما اعتُمد في طلب الشراء يُفحَص هنا قبل الحفظ لا هناك.
            if (! empty($validatedData['order_id'])) {
                $sourceOrder = PurchaseOrder::with('items')->find($validatedData['order_id']);

                if ($sourceOrder && ($tampered = ConvertedLines::guard($sourceOrder->items, $validatedData['items']))) {
                    return back()->withInput()->with('error', $tampered);
                }
            }

            // اشتراك فوترة وحدوده — لا يحظر شيئاً اليوم فعلياً لأن كتالوج
            // فوترة الحالي لا يذكر ميزة «purchase_quotations» بعد (انظر
            // تعليق UsageReporter::catalog())، فـ allows()/check() يُعيدان
            // "مسموح" دائماً لميزة غير معروفة لديها — جاهزٌ ليعمل فوراً بلا
            // تعديل كودٍ آخر يوم تُعرَّف الميزة هناك.
            $subscriptionGate = app(SubscriptionGate::class);
            if (! $subscriptionGate->allows('purchase_quotations')) {
                return back()->withInput()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «طلبات عروض الأسعار». راجع لوحة تحكّم فوترة للترقية.');
            }
            $quotationQuota = $subscriptionGate->check('purchase_quotations');
            if (! $quotationQuota['allowed']) {
                return back()->withInput()->with('error', $quotationQuota['reason']);
            }

            DB::beginTransaction();

            // الرقم من إعدادات «تسلسل أرقام المستندات» — كان `max(id) + 1`
            // فيتجاهل البادئة والصيغة المضبوطتين هناك
            $nextCode = next_document_number(self::NUMBER_SECTION);

            // الفرع يُعيّن تلقائياً من فرع المستخدم الحالي (موظف: فرعه المثبَّت،
            // وإلا فرع المستخدم) — إلا لمن يملك صلاحية اختيار فرع آخر صراحة
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $validatedData['branch_id'] ?? null);

            // إنشاء عرض السعر
            $purchaseQuotation = PurchaseQuotation::create([
                'title' => $validatedData['title'],
                'code' => $nextCode,
                'order_date' => $validatedData['order_date'],
                'due_date' => $validatedData['due_date'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'total_amount' => array_reduce(
                    $validatedData['items'],
                    function ($carry, $item) {
                        $product = Product::find($item['product_id']);

                        return $carry + ($item['quantity'] * ($product ? ProductUnits::linePrice($item, $product) : 0));
                    },
                    0,
                ),

                // خيار «حالة يدوية لطلبات عروض الأسعار» يسمح بتحديد الحالة
                // عند الحفظ؛ ودونه تُثبَّت على «تحت المراجعة» كما كانت.
                // القائمة صريحة فلا يكتب الطلب في العمود ما يشاء
                'status' => $this->resolvedStatus($request),
                'created_by' => auth()->id(),
                'order_id' => $validatedData['order_id'] ?? null,
                'branch_id' => $branchId,
            ]);

            // رفع المرفقات إن وُجدت
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                $fileName = time().'_'.$file->getClientOriginalName();
                $filePath = $file->storeAs('purchase_quotations/attachments', $fileName, 'public');
                $purchaseQuotation->update(['attachments' => $filePath]);
            }

            // ربط الموردين
            foreach ($validatedData['supplier_id'] as $supplierId) {
                PurchaseQuotationSupplier::create([
                    'purchase_quotation_id' => $purchaseQuotation->id,
                    'supplier_id' => $supplierId,
                    'created_by' => auth()->id(),
                ]);
            }

            // إضافة البنود
            foreach ($validatedData['items'] as $item) {
                $product = Product::find($item['product_id']);

                InvoiceItem::create(ProductUnits::fieldsFor($item, (float) $item['quantity']) + [
                    'purchase_quotation_id' => $purchaseQuotation->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'item' => $product->name,
                    'price' => ProductUnits::linePrice($item, $product),
                    'total' => round($item['quantity'] * ProductUnits::linePrice($item, $product), 2),
                ]);
            }

            // تسجيل العملية في سجل النظام
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $purchaseQuotation->id,
                $this->logLine('created', $purchaseQuotation->code),
            );

            // ✅ تحديث حالة طلب الشراء إن وُجد
            if (! empty($validatedData['order_id'])) {
                $purchaseOrder = PurchaseOrder::find($validatedData['order_id']);
                if ($purchaseOrder) {
                    $purchaseOrder->status = 'Convert to Quotation'; // أو رقم مثل: 2
                    $purchaseOrder->save();
                }
            }

            DB::commit();

            return redirect()->route('Quotations.show', $purchaseQuotation->id)->with('success', __('purchase_cycle.messages.created', ['document' => $this->documentLabel()]));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'save', $id ?? null);

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء عرض السعر: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $existing = PurchaseQuotation::findOrFail($id);
            $this->authorizeCreatorScope($existing->created_by, null, $existing->branch_id);

            // الاعتماد نهائي: لا تعديل بعده، ولو أُرسل الطلب مباشرة بلا
            // مرور بشاشة التعديل (زرّها مخفيّ أصلاً بعد الاعتماد)
            if ($existing->status !== 'Under Review') {
                return redirect()->back()->with('error', __('purchase_orders.messages.edit_locked'));
            }

            // التحقق من البيانات المدخلة
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'order_date' => 'required|date',
                'due_date' => 'nullable|date|after_or_equal:order_date',
                'supplier_id' => 'required|array|min:1',
                'supplier_id.*' => 'exists:suppliers,id',
                'notes' => 'nullable|string',
                'attachments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:1',
                // الوحدة: `validate()` يُسقط كلّ مفتاحٍ بلا قاعدة، والبنود تُحفظ من
                // `$validatedData` — فبدون هذه كان «عبوة» يصل عرضَ السعر «حبّة»
                'items.*.product_unit_id' => 'nullable',
                'items.*.unit_name' => 'nullable|string|max:255',
                'items.*.conversion_factor' => 'nullable|numeric|min:0',
                // يُعتمَد فقط لمن يملك صلاحية اختيار الفرع — انظر Scopes::canSelectBranch()
                'branch_id' => 'nullable|integer|exists:branches,id',
            ], [
                'supplier_id.required' => 'يجب اختيار مورد واحد على الأقل',
                'supplier_id.min' => 'يجب اختيار مورد واحد على الأقل',
                'supplier_id.*.exists' => 'أحد الموردين المحددين غير موجود',
                'items.required' => 'يجب إضافة بند واحد على الأقل',
                'items.*.product_id.required' => 'يجب اختيار المنتج',
                'items.*.quantity.required' => 'يجب تحديد الكمية',
            ]);

            // الفترة المحاسبية — انظر التعليق في store()
            if ($blocked = AccountingPeriodGuard::blockReason($validatedData['order_date'])) {
                return back()->withInput()->with('error', $blocked);
            }

            // بنود التحويل — انظر التعليق في store(). القفل على أصل البند
            // لا على شاشة الإنشاء وحدها، وإلا كان التعديل بابَه الخلفي
            $sourceOrder = $existing->purchaseOrder;

            if ($sourceOrder && ($tampered = ConvertedLines::guardUpdate($sourceOrder->items, $existing->items, $validatedData['items']))) {
                return back()->withInput()->with('error', $tampered);
            }

            DB::beginTransaction();

            // نفس السجلّ الذي فُحصت حالته أعلاه — لا استعلامٌ إضافي
            $purchaseQuotation = $existing;

            // تحديث البيانات الأساسية
            $purchaseQuotation->update([
                'title' => $validatedData['title'],
                'order_date' => $validatedData['order_date'],
                'due_date' => $validatedData['due_date'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'total_amount' => array_reduce(
                    $validatedData['items'],
                    function ($carry, $item) {
                        $product = Product::find($item['product_id']);

                        return $carry + ($item['quantity'] * ($product ? ProductUnits::linePrice($item, $product) : 0));
                    },
                    0,
                ),
                'updated_by' => auth()->id(),
            ]);

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحة
            // ويملك صلاحية ذلك — وإلا يُعاد تثبيته من فرعه الحالي
            // العقدةُ المحفوظة تبقى في التعديل — لا تُعاد وسمُها بعقدة المحرِّر
            $purchaseQuotation->branch_id = \App\Support\Scopes::chosenBranchId(auth()->user(), $validatedData['branch_id'] ?? null, $purchaseQuotation->branch_id);
            $purchaseQuotation->save();

            // معالجة المرفقات
            if ($request->hasFile('attachments')) {
                // حذف المرفق القديم إذا كان موجوداً
                if ($purchaseQuotation->attachments) {
                    Storage::disk('public')->delete($purchaseQuotation->attachments);
                }

                $file = $request->file('attachments');
                $fileName = time().'_'.$file->getClientOriginalName();
                $filePath = $file->storeAs('purchase_quotations/attachments', $fileName, 'public');
                $purchaseQuotation->update(['attachments' => $filePath]);
            }

            // تحديث علاقات الموردين
            PurchaseQuotationSupplier::where('purchase_quotation_id', $id)->delete();
            foreach ($validatedData['supplier_id'] as $supplierId) {
                PurchaseQuotationSupplier::create([
                    'purchase_quotation_id' => $purchaseQuotation->id,
                    'supplier_id' => $supplierId,
                    'created_by' => auth()->id(),
                ]);
            }

            // تحديث تفاصيل الفاتورة
            InvoiceItem::where('purchase_quotation_id', $id)
                ->where('type', 'purchase_quotation')
                ->delete();

            foreach ($validatedData['items'] as $item) {
                $product = Product::find($item['product_id']);

                InvoiceItem::create(ProductUnits::fieldsFor($item, (float) $item['quantity']) + [
                    'purchase_quotation_id' => $purchaseQuotation->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'item' => $product->name,
                    'price' => ProductUnits::linePrice($item, $product),
                    'total' => round($item['quantity'] * ProductUnits::linePrice($item, $product), 2),
                    'type' => 'purchase_quotation',
                ]);
            }

            // تسجيل التعديل في السجلات
            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $purchaseQuotation->id,
                $this->logLine('updated', $purchaseQuotation->code),
            );

            DB::commit();

            return redirect()->route('Quotations.show', $purchaseQuotation->id)
                ->with('success', __('purchase_cycle.messages.updated', ['document' => $this->documentLabel()]));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'save', $id ?? null);

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث عرض السعر: '.$e->getMessage());
        }
    }

    public function edit($id)
    {
        $purchaseQuotation = PurchaseQuotation::findOrFail($id);
        $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

        // الاعتماد نهائي: لا تعديل بعده — انظر نفس القيد في update() وفي
        // PurchaseRequestsController::edit()
        if ($purchaseQuotation->status !== 'Under Review') {
            return redirect()->back()->with('error', __('purchase_orders.messages.edit_locked'));
        }

        // جلب الموردين المرتبطين

        // جلب قوائم البيانات المطلوبة
        // فرعُ الشريط العلويّ، ومورِّدو الطلب المحفوظون يبقون ولو كانوا على جهةٍ أخرى
        $suppliers = Supplier::query()
            ->when(\App\Support\HeaderBranchScope::nodeId(), fn ($q, $node) => $q
                ->where(fn ($w) => $w->forBranch($node))
                ->orWhereIn('id', $purchaseQuotation->suppliers->pluck('id')->all()))
            ->get();
        $products = Product::all();
        $branches = \App\Models\Branch::orderBy('name')->get();

        return view('purchases::purchases.quotations.edit', compact('purchaseQuotation', 'products', 'suppliers', 'branches'));
    }

    public function destroy($id)
    {
        $purchaseQuotation = PurchaseQuotation::findOrFail($id);
        $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

        try {
            DB::beginTransaction();

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $purchaseQuotation,
                $this->logLine('deleted', $purchaseQuotation->code),
            );

            $purchaseQuotation->items()->delete();
            $purchaseQuotation->delete();

            DB::commit();

            return redirect()->route('Quotations.index')
                ->with('success', __('purchase_cycle.messages.deleted', ['document' => $this->documentLabel()]));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'delete', $id);

            return redirect()->back()->with('error', __('purchase_cycle.messages.delete_failed', ['document' => $this->documentLabel()]));
        }
    }

    /**
     * حذف جماعي — يقابل زرّ «حذف المحدَّد» في الفهرس، بنفس نمط
     * PurchaseRequestsController::bulkDelete().
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:purchase_quotations,id',
        ]);

        try {
            DB::beginTransaction();

            /* النطاقُ يُطبَّق على الجلب لا على الواجهة وحدَها: المعرّفات
               تصل في متن الطلب، فمن لا يرى مستنداً كان يحذفه بإرسال
               رقمه. والقيدُ على محور التخويل (`created_by`) لا على فرع
               الهيدر — هذا الأخير فلترُ عرضٍ يتبدّل بالمُنتقي، فلا يصحّ
               أن يتبدّل به ما يملك المستخدمُ حذفَه. */
            $quotations = $this->scopeToCreator(PurchaseQuotation::whereIn('id', $data['ids']))->get();

            foreach ($quotations as $quotation) {
                OperationLog::record(
                    'deleted',
                    self::LOG_MODULE,
                    $quotation,
                    $this->logLine('deleted', $quotation->code),
                );

                $quotation->items()->delete();
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

    public function approve(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $purchaseQuotation = PurchaseQuotation::findOrFail($id);
            $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

            // تحديث حالة الطلب
            $purchaseQuotation->update([
                'status' => 'approval', // موافق عليه
                'updated_by' => Auth::id(),
            ]);

            // إضافة ملاحظة الموافقة إذا وجدت
            if ($request->has('note')) {
                OperationLog::record(
                    'approved',
                    self::LOG_MODULE,
                    $purchaseQuotation->id,
                    'تمت الموافقة على عرض السعر. ملاحظة: '.$request->note,
                );
            } else {
                OperationLog::record(
                    'approved',
                    self::LOG_MODULE,
                    $purchaseQuotation->id,
                    'تمت الموافقة على عرض السعر',
                );
            }

            DB::commit();

            return redirect()->back()->with('success', 'تمت الموافقة على طلب عرض السعر بنجاح');
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'save', $id ?? null);

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء الموافقة على الطلب: '.$e->getMessage());
        }
    }

    /**
     * رفض طلب عرض السعر
     */
    public function reject(Request $request, $id)
    {
        try {
            $request->validate(
                [
                    'note' => 'required|string|max:500',
                ],
                [
                    'note.required' => 'يجب إدخال سبب الرفض',
                ],
            );

            DB::beginTransaction();

            $purchaseQuotation = PurchaseQuotation::findOrFail($id);
            $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

            // تحديث حالة الطلب
            $purchaseQuotation->update([
                'status' => 'disagree', // مرفوض
                'updated_by' => Auth::id(),
            ]);

            // إضافة سبب الرفض
            OperationLog::record(
                'rejected',
                self::LOG_MODULE,
                $purchaseQuotation->id,
                $this->logLine('rejected', $purchaseQuotation->code),
            );

            DB::commit();

            return redirect()->back()->with('success', __('purchase_cycle.messages.rejected', ['document' => $this->documentLabel()]));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'save', $id ?? null);

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء رفض الطلب: '.$e->getMessage());
        }
    }

    /*
     * تنويه: cancelApproval() أُزيلت عمداً — اعتماد طلب عرض السعر صار
     * نهائياً لا رجعة فيه (لا تعديل ولا إلغاء اعتماد بعد الموافقة)، بنفس
     * منطق PurchaseRequestsController تماماً — اعتماد أمر الشراء صار
     * نهائياً هو الآخر ولا زرّ ولا مسار لإلغائه.
     */

    /**
     * نسخ طلب عرض السعر
     */
    public function duplicate($id)
    {
        try {
            // نسخ الطلب يُنشئ سجلاً جديداً فوراً بلا شاشة تأكيد — يستهلك حصّة
            // «طلبات عروض الأسعار» تماماً مثل store()، فيُحرَس بنفس الفحص.
            $subscriptionGate = app(SubscriptionGate::class);
            if (! $subscriptionGate->allows('purchase_quotations')) {
                return back()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «طلبات عروض الأسعار». راجع لوحة تحكّم فوترة للترقية.');
            }
            $quotationQuota = $subscriptionGate->check('purchase_quotations');
            if (! $quotationQuota['allowed']) {
                return back()->with('error', $quotationQuota['reason']);
            }

            DB::beginTransaction();

            $originalQuotation = PurchaseQuotation::with(['items', 'suppliers'])->findOrFail($id);
            $this->authorizeCreatorScope($originalQuotation->created_by, null, $originalQuotation->branch_id);

            // إنشاء نسخة جديدة
            $newQuotation = $originalQuotation->replicate();
            $newQuotation->code = $this->generateQuotationCode();
            $newQuotation->status = 'Under Review'; // تحت المراجعة
            $newQuotation->created_by = Auth::id();
            $newQuotation->updated_by = null;
            $newQuotation->save();

            // نسخ المنتجات
            foreach ($originalQuotation->items as $item) {
                $newItem = $item->replicate();
                $newItem->purchase_quotation_id = $newQuotation->id;
                $newItem->save();
            }

            // نسخ الموردين
            $newQuotation->suppliers()->attach($originalQuotation->suppliers->pluck('id')->toArray());

            OperationLog::record(
                'duplicated',
                self::LOG_MODULE,
                $newQuotation,
                $this->logLine('duplicated', $newQuotation->code),
            );

            DB::commit();

            return redirect()->route('Quotations.edit', $newQuotation->id)->with('success', __('purchase_cycle.messages.duplicated', ['document' => $this->documentLabel()]));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'save', $id ?? null);

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء نسخ الطلب: '.$e->getMessage());
        }
    }

    public function generatePdf($id, $template = 'default')
    {
        // الصلاحية محروسة فعلاً بـ middleware('permission:purchases.quotations.export')
        // في __construct() — لا حاجة لفحصٍ مكرَّر هنا.
        try {
            $purchaseQuotation = PurchaseQuotation::with(['items.product', 'suppliers', 'creator'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

            /* القالب المصمَّم يُعرض صفحةَ طباعة تُحفظ PDF من المتصفّح — Dompdf
               كان يقلب العربية ويُسقط الجدول (انظر ServesDesignedTemplatePage). */
            $page = $this->configuredTemplatePage($id, $template, $purchaseQuotation->code);

            if ($page === null) {
                return redirect()->back()->with('error', __('purchase_cycle.messages.no_template'));
            }

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseQuotation,
                $this->logLine('exported', $purchaseQuotation->code),
            );

            return $page;
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return redirect()->back()->with('error', __('purchase_cycle.messages.pdf_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * تصدير قائمة الفهرس — بنفس الفلاتر المطبَّقة فيها — إلى CSV يُفتح في
     * Excel مباشرة. بلا مكتبة خارجية: القائمة نصّية بحتة فلا تستحقّ وزن
     * PhpSpreadsheet، و`fputcsv` كافٍ.
     */
    public function exportCsv(Request $request)
    {
        // الصلاحية محروسة فعلاً بـ middleware('permission:purchases.quotations.export')
        // في __construct() — لا حاجة لفحصٍ مكرَّر هنا.
        // نفس فلاتر getFilteredData() مكرَّرة هنا عمداً — غرضان مختلفان
        // (JSON مقسَّم صفحات للعرض، وCSV كامل للتنزيل)، ودمجهما بوسيط
        // "نوع الإخراج" يُعقّد الدالة الأصلية بلا داعٍ لقسمٍ بهذا الحجم.
        $query = PurchaseQuotation::query()->with(['creator', 'suppliers'])->withCount('items');
        $query = $this->scopeToCreator($query);

        /* ومحورُ الفرع معه — كان التصديرُ يقتصر على `scopeToCreator` بينما
           تضمّ `getFilteredData()` الاثنين، فيخرج في الملفّ ما لا يظهر في
           الجدول الذي صُدِّر عنه. */
        $query = $this->scopeToBranchNode($query, $request);

        if ($request->filled('code')) {
            $query->where('code', 'LIKE', '%'.$request->code.'%');
        }
        if ($request->filled('supplier_id')) {
            $query->whereHas('suppliers', fn ($q) => $q->where('supplier_id', $request->supplier_id));
        }
        if ($request->filled('order_date_from')) {
            $query->whereDate('order_date', '>=', $request->order_date_from);
        }
        if ($request->filled('order_date_to')) {
            $query->whereDate('order_date', '<=', $request->order_date_to);
        }
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('follow_status')) {
            $query->where('follow_status', $request->follow_status);
        }

        $rows = $query->latest()->get();

        OperationLog::record(
            'exported',
            self::LOG_MODULE,
            null,
            'تم تصدير قائمة طلبات عروض الأسعار ('.$rows->count().' سجلّ)',
        );

        $filename = 'quotation-requests-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // BOM حتى يفتح Excel الملف UTF-8 صحيحاً لا حروفاً مبعثرة
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('purchase_cycle.fields.code'),
                __('purchase_cycle.fields.title'),
                __('purchase_cycle.fields.suppliers'),
                __('purchase_cycle.fields.status'),
                __('purchase_cycle.fields.items_count'),
                __('purchase_cycle.fields.date'),
                __('purchase_cycle.fields.due_date'),
                __('purchase_cycle.fields.creator'),
            ]);

            foreach ($rows as $quotation) {
                fputcsv($handle, [
                    $quotation->code,
                    $quotation->title,
                    $quotation->suppliers->pluck('name')->implode('، '),
                    __('purchase_cycle.statuses.'.$quotation->status),
                    $quotation->items_count ?? 0,
                    optional($quotation->order_date)->format('Y-m-d'),
                    optional($quotation->due_date)->format('Y-m-d'),
                    $quotation->creator->name ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * معاينة الطباعة في المتصفّح بقالب الإعدادات.
     */
    public function printView($id, $template = 'default')
    {
        // الصلاحية محروسة فعلاً بـ middleware('permission:purchases.quotations.export')
        // في __construct() — لا حاجة لفحصٍ مكرَّر هنا.
        $purchaseQuotation = PurchaseQuotation::with(['items.product', 'suppliers', 'creator'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseQuotation->created_by, null, $purchaseQuotation->branch_id);

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderConfiguredTemplate($id, $fallback->id) : '';
        }

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $purchaseQuotation,
            $this->logLine('printed', $purchaseQuotation->code),
        );

        return response(DocumentPrintPage::wrap($html));
    }

    /**
     * يصيّر قالباً مصمَّماً في «قوالب المستندات» — أو null إن لم يكن
     * `$template` معرّفَ قالبٍ صالح لهذا النوع.
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
            $service = app(DocumentTemplateDataService::class);
            $renderer = app(DocumentTemplateRenderer::class);

            /* نسخةٌ لكلّ مورّد: الطلبُ يُرسَل إلى كلٍّ منهم على حدة، فيَقرأ
               كلُّ مورّدٍ ورقةً معنونةً باسمه وبياناته وحدَه — لا ورقةً
               تسرد منافسيه معه. والبنودُ والكمّيات تتكرّر كما هي في كلّ
               نسخة، فهي المطلوبُ تسعيرُه نفسُه.

               وبلا مورّدين (طلبٌ لم يُرشَّح له أحدٌ بعد) تبقى نسخةٌ واحدة
               منسوبةٌ إلى منشئه — وهي حالةُ `[null]` أدناه. */
            $supplierIds = PurchaseQuotation::findOrFail($documentId)
                ->suppliers->pluck('id');

            /* `?supplier=` يقصر المطبوعةَ على مورّدٍ واحد — لإرسال نسخته
               إليه وحدَه. ومعرّفٌ خارج موردي الطلب يُتجاهَل فتُطبَع النسخ
               كلُّها، فلا يُسرَّب بالوسيط مستندٌ معنونٌ لمن ليس طرفاً فيه. */
            $only = request('supplier');

            if ($only !== null && $supplierIds->contains((int) $only)) {
                $supplierIds = collect([(int) $only]);
            }

            if ($supplierIds->isEmpty()) {
                $supplierIds = collect([null]);
            }

            $pages = $supplierIds->map(fn ($supplierId) => $renderer->render(
                $model,
                $service->getDocumentData(self::DOCUMENT_TYPE, $documentId, $supplierId)
            ));

            /* الفاصلُ بين النسخ لا بعد آخرها — وإلّا خرجت ورقةٌ بيضاء
               زائدة في آخر المطبوعة. و`page-break-after` للمتصفّحات
               القديمة و`break-after` للحديثة. */
            return $pages->implode(
                '<div style="break-after:page;page-break-after:always;"></div>'
            );
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

    /**
     * تراجع آمن — لا يمسّ إلا معاملةً فتحها هذا المتحكّم فعلاً.
     */
    private function rollBackIfOpen(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    /**
     * حالة الحفظ: من الطلب إن أُذن بذلك، وإلّا «تحت المراجعة».
     *
     * الإذن خيار «حالة يدوية لطلبات عروض الأسعار» في خيارات المشتريات،
     * والقائمة صريحة لا مفتوحة — يطابق نمط `ViewPurchasePriceController::resolvedStatus()`.
     */
    private function resolvedStatus(Request $request): string
    {
        $default = 'Under Review';

        if (! PurchaseSettings::enabled('manual_quote_orders')) {
            return $default;
        }

        $submitted = (string) $request->input('status', '');

        return in_array($submitted, ['Under Review', 'approval', 'disagree'], true)
            ? $submitted
            : $default;
    }

    // توليد كود عرض السعر الجديد (رقم تسلسلي)
    private function generateQuotationCode()
    {
        return next_document_number(self::NUMBER_SECTION);
    }

    // إضافة route في web.php
    //
}
