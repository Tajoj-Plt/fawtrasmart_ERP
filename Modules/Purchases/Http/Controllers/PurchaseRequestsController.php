<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\InvoiceItem;
use App\Models\Log as ModelsLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\TaxSitting;
use App\Models\User;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\Fawtra\SubscriptionGate;
use App\Services\Purchases\PurchaseTaxCalculator;
use App\Support\DocumentPrintPage;
use App\Support\LanguageSettings;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\OrderLine;
use Modules\Purchases\Support\ProductUnits;
use TCPDF;

class PurchaseRequestsController extends Controller
{
    use Concerns\ServesDesignedTemplatePage;
    use ScopesToEmployeeHierarchy;

    /**
     * نوع المستند في شاشة «قوالب المستندات» — به تُربط القوالب المصمَّمة
     * في الإعدادات بهذا القسم.
     */
    public const DOCUMENT_TYPE = 'purchase_order';

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'purchase-order';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمستند */
    public const LOG_MODULE = 'OrdersPurchases';

    /** مفتاح النشاط لكل تغيّر حالة — يحدّد الأيقونة في شاشة السجلّات */
    public const STATUS_ACTIONS = [
        'approved' => 'approved',
        'rejected' => 'rejected',
        'approval_cancelled' => 'restored',
        'rejection_undone' => 'restored',
    ];

    /** القوالب المدمجة — تظهر حين لا يُصمَّم قالب في الإعدادات */
    public const BUILTIN_TEMPLATES = ['default', 'detailed', 'simple', 'compact', 'summary'];

    public function __construct()
    {
        // صلاحيات طلبات الشراء — كتالوج App\Constants\permissions\PurchasesPermissions::groups()['orders']
        $this->middleware('permission:purchases.orders.view', ['only' => ['index', 'paginate', 'show']]);
        $this->middleware('permission:purchases.orders.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:purchases.orders.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:purchases.orders.delete', ['only' => ['destroy', 'bulkDelete']]);
        $this->middleware('permission:purchases.orders.approve_reject', ['only' => ['approve', 'reject', 'undoRejection']]);
        $this->middleware('permission:purchases.orders.export', ['only' => ['generatePdf', 'printView']]);
    }

    public function index(Request $request)
    {
        /* فلترُ «الموظف» يطابق `created_by` — فالقائمةُ من أنشأ طلباً يظهر هنا،
           على فرع الشريط العلويّ. كانت `users.role IN (employee, manager)`،
           و`users.role` لا يكون «employee» أبداً فتظهر ناقصة. */
        $employees = $this->creatorOptions(\App\Models\PurchaseOrder::query());

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax()) {
            return $this->getFilteredData($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $purchaseOrders = $this->getFilteredData($request, false);

        /* عدّاد الحالات — استعلام واحد بدل ثلاثة، ومقيَّدٌ بنطاق المستخدم
           نفسِه الذي يُقيَّد به الجدول (`getFilteredData`): كان عارياً
           فيقرأ صاحبُ الفرع عدّاد المنشأة كلِّها بينما لا يرى تحته
           إلّا سجلّاته. */
        $counts = $this->scopeToBranchNode($this->scopeToCreator(PurchaseOrder::query()), $request)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $counts->sum(),
            'review' => (int) ($counts['Under Review'] ?? 0),
            'approved' => (int) ($counts['approval'] ?? 0),
            'rejected' => (int) ($counts['disagree'] ?? 0),
        ];

        // حالة حدّ اشتراك فوترة لميزة «طلبات الشراء» — bar['known'] يبقى
        // false حتى تُعرِّف فوترة هذه الميزة في كتالوجها، فلا تظهر الشارة
        // في الواجهة قبل ذلك (انظر تعليق UsageReporter::catalog()).
        $subscriptionLimit = app(SubscriptionGate::class)->limit('purchase_orders');

        return view('purchases::purchases.purchase_requests.index', compact('employees', 'purchaseOrders', 'stats', 'subscriptionLimit'));
    }

    private function getFilteredData(Request $request, $returnJson = true)
    {
        // بناء الاستعلام
        // الإجمالي يُجمَع في الاستعلام لا بتحميل البنود لكل صف
        $query = PurchaseOrder::query()
            ->with('creator')
            ->withCount('items')
            ->withSum('items', 'total');

        // تطبيق تصفية نطاق البيانات حسب دور المستخدم
        $query = $this->scopeToCreator($query);

        /* «تابع لـ» — العقدةُ المختارة في الشاشة، وإلّا العقدةُ النشطة من
           الهيدر، وهي تعني العقدةَ وكلَّ ما تحتها. كانت هذه الشاشة لا تقرأ
           الفرع إطلاقاً، فيرى صاحبُ المنطقة مستنداتِ المنشأة كلَّها. */
        $query = $this->scopeToBranchNode($query, $request);

        // البحث حسب حالة المتابعة
        if ($request->filled('follow_status')) {
            $query->where('follow_status', $request->follow_status);
        }
        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%'.$request->title.'%');
        }

        // البحث حسب الموظف
        if ($request->filled('employee_id')) {
            $query->where('created_by', $request->employee_id);
        }

        // البحث حسب الكود
        if ($request->filled('code')) {
            $query->where('code', 'LIKE', '%'.$request->code.'%');
        }

        // البحث حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // البحث حسب تاريخ الطلب (من)
        if ($request->filled('order_date_from')) {
            $query->whereDate('order_date', '>=', $request->order_date_from);
        }

        // البحث حسب تاريخ الطلب (إلى)
        if ($request->filled('order_date_to')) {
            $query->whereDate('order_date', '<=', $request->order_date_to);
        }

        // البحث حسب تاريخ الاستحقاق (من)
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }

        // البحث حسب تاريخ الاستحقاق (إلى)
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }

        // تنفيذ الاستعلام وترتيب النتائج
        $purchaseOrders = $query->latest()->paginate(10)->appends($request->query());

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'data' => view('purchases::purchases.purchase_requests.partials.table', compact('purchaseOrders'))->render(),
                'pagination' => view('purchases::purchases.purchase_requests.partials.pagination', compact('purchaseOrders'))->render(),
                'total' => $purchaseOrders->total(),
                'current_page' => $purchaseOrders->currentPage(),
                'last_page' => $purchaseOrders->lastPage(),
            ]);
        }

        return $purchaseOrders;
    }

    // دالة للتعامل مع طلبات الـ pagination عبر Ajax
    public function paginate(Request $request)
    {
        if ($request->ajax()) {
            return $this->getFilteredData($request);
        }

        return redirect()->route('PurchaseRequests.index');
    }

    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with(['creator', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by);

        // مجمَّعة بالأيام — الخطّ الزمني في شاشة العرض يقرأها هكذا
        $logs = ModelsLog::where('type', self::LOG_MODULE)
            ->where('type_id', $id)
            ->with(['user:id,name,branch_id', 'user.branch:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($log) => $log->created_at->format('Y-m-d'));

        return view('purchases::purchases.purchase_requests.show', [
            'purchaseOrder' => $purchaseOrder,
            'productDetails' => $purchaseOrder->items,
            'logs' => $logs,
            'totals' => $this->totalsFor($purchaseOrder),
            // القوالب المصمَّمة في الإعدادات — القائمة تُبنى منها لا من نصّ ثابت
            'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
            // المعاينة والتنزيل يتبعان القالب الافتراضي المضبوط في الإعدادات
            'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
            'prefersPdf' => PrintSettings::prefersPdf(),
        ]);
    }

    public function create()
    {
        return view('purchases::purchases.purchase_requests.create', [
            'products' => Product::orderBy('name')->get(),
            // موظّفو فرع الشريط العلويّ وما يتبعه، وبأقسام «موظّفي المشتريات»
            // من الإعدادات — القاعدةُ الواحدة في `HeaderPickers` لا نسخةٌ هنا
            'employees' => \Modules\Purchases\Support\HeaderPickers::employees()->get(),
            // الرقم من إعدادات التسلسل — معاينة بلا استهلاك حتى يُحفظ الطلب
            'code' => peek_document_number(self::NUMBER_SECTION),
            'taxes' => $this->taxOptions(),
        ]);
    }

    /**
     * حالة الحفظ: من الطلب إن أُذن بذلك، وإلّا «تحت المراجعة».
     *
     * خيار «حالة يدوية لطلبات الشراء» في خيارات المشتريات هو الإذن. وقائمة
     * الحالات المسموحة صريحة لا مفتوحة: بلا قيدٍ يستطيع الطلب كتابة أي نصّ
     * في العمود، فتظهر في الشاشة حالةٌ لا يعرفها فلترٌ ولا تُترجَم.
     */
    private function resolvedStatus(Request $request): string
    {
        $default = 'Under Review';

        if (! PurchaseSettings::enabled('manual_purchase_orders')) {
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
            $validatedData = $request->validate($this->rules());

            if ($blocked = \App\Support\AccountingPeriodGuard::blockReason($validatedData['order_date'])) {
                return back()->withInput()->with('error', $blocked);
            }

            // اشتراك فوترة وحدوده — لا يحظر شيئاً اليوم فعلياً لأن كتالوج
            // فوترة الحالي لا يذكر ميزة «purchase_orders» بعد (انظر تعليق
            // UsageReporter::catalog())، فـ allows()/check() يُعيدان "مسموح"
            // دائماً لميزة غير معروفة لديها — هذا الفحص جاهز ليعمل فوراً بلا
            // تعديل كودٍ آخر يوم تُعرَّف الميزة هناك.
            $subscriptionGate = app(SubscriptionGate::class);
            if (! $subscriptionGate->allows('purchase_orders')) {
                return back()->withInput()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «طلبات الشراء». راجع لوحة تحكّم فوترة للترقية.');
            }
            $orderQuota = $subscriptionGate->check('purchase_orders');
            if (! $orderQuota['allowed']) {
                return back()->withInput()->with('error', $orderQuota['reason']);
            }

            DB::beginTransaction();

            // الرقم يُستهلَك من إعدادات التسلسل عند الحفظ الفعلي وحده،
            // فلا تُحرق أرقام على كل فتح لشاشة الإنشاء.
            $code = $this->resolveCode($validatedData['code'] ?? null);

            // الفرع يُعيّن تلقائياً من فرع المستخدم الحالي (موظف: فرعه المثبَّت،
            // وإلا فرع المستخدم) — إلا لمن يملك صلاحية اختيار فرع آخر صراحة
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $validatedData['branch_id'] ?? null);

            $purchaseOrder = PurchaseOrder::create([
                'title' => $validatedData['title'],
                'code' => $code,
                'order_date' => $validatedData['order_date'],
                'due_date' => $validatedData['due_date'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'status' => $this->resolvedStatus($request),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'branch_id' => $branchId,
            ]);

            if ($request->hasFile('attachments')) {
                $purchaseOrder->update(['attachments' => $this->storeAttachment($request)]);
            }

            $count = $this->syncItems($purchaseOrder, $validatedData['items']);

            // نقطة واحدة تكتب في سجلّ النشاط وسجلّ الأحداث معاً
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $purchaseOrder,
                __('purchase_orders.log.created', ['code' => $code, 'count' => $count]),
                ['new' => $this->auditSnapshot($purchaseOrder, $count)],
            );

            DB::commit();

            return redirect()
                ->route('PurchaseRequests.show', $purchaseOrder->id)
                ->with('success', __('purchase_orders.messages.created', ['count' => $count]));
        } catch (ValidationException $e) {
            // بلا `rollBack()`: التحقّق يرمي قبل `beginTransaction()`، فالنداء
            // هنا يتراجع عن معاملةٍ لم يفتحها هذا المتحكّم — ويُسقط معاملةَ
            // المُنادي إن كان قد فتح واحدة.
            OperationLog::failure($e, self::LOG_MODULE, 'create');

            return back()
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', __('purchase_orders.messages.validation_failed'));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'create');

            return back()
                ->withInput()
                ->with('error', __('purchase_orders.messages.create_failed', ['error' => $e->getMessage()]));
        }
    }

    public function edit($id)
    {
        $purchaseOrder = PurchaseOrder::with('items.product')->findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by);

        // السماح بالتعديل فقط إذا كانت الحالة "تحت المعالجة"
        if ($purchaseOrder->status !== 'Under Review') {
            return redirect()->back()->with('error', __('purchase_orders.messages.edit_locked'));
        }

        return view('purchases::purchases.purchase_requests.edit', [
            'purchaseOrder' => $purchaseOrder,
            'products' => Product::orderBy('name')->get(),
            // فرعُ الشريط العلويّ وأقسامُ الإعدادات، وموظّفُ الطلب المحفوظ يبقى
            // ولو كان على جهةٍ أو قسمٍ آخر (`keepIds`)
            'employees' => \Modules\Purchases\Support\HeaderPickers::employees(null, [$purchaseOrder->employee_id])->get(),
            'taxes' => $this->taxOptions(),
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);
            $this->authorizeCreatorScope($purchaseOrder->created_by);
            $validatedData = $request->validate($this->rules($id));

            if ($blocked = \App\Support\AccountingPeriodGuard::blockReason($validatedData['order_date'])) {
                return back()->withInput()->with('error', $blocked);
            }

            DB::beginTransaction();

            $purchaseOrder->fill([
                'title' => $validatedData['title'],
                'code' => $validatedData['code'] ?: $purchaseOrder->code,
                'order_date' => $validatedData['order_date'],
                'due_date' => $validatedData['due_date'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحة
            // ويملك صلاحية ذلك — وإلا يُعاد تثبيته من فرعه الحالي
            // العقدةُ المحفوظة تبقى في التعديل — لا تُعاد وسمُها بعقدة المحرِّر
            $purchaseOrder->branch_id = \App\Support\Scopes::chosenBranchId(auth()->user(), $validatedData['branch_id'] ?? null, $purchaseOrder->branch_id);

            // الفرق يُلتقط قبل الحفظ — بعده تصير القيم الأصلية هي الجديدة
            $changes = OperationLog::diff($purchaseOrder, [
                'title', 'code', 'order_date', 'due_date', 'notes', 'branch_id',
            ]);

            $purchaseOrder->save();

            if ($request->hasFile('attachments')) {
                // حذف الملف القديم قبل تسجيل الجديد
                if ($purchaseOrder->attachments) {
                    Storage::disk('public')->delete($purchaseOrder->attachments);
                }

                $purchaseOrder->update(['attachments' => $this->storeAttachment($request)]);
            }

            // `invoiceItems()` لا وجود لها على هذا الموديل — العلاقة اسمها
            // `items()`، وكان النداء القديم يرمي BadMethodCallException
            // فيسقط كل تعديل قبل أن يُحفظ.
            $purchaseOrder->items()->delete();
            $count = $this->syncItems($purchaseOrder, $validatedData['items']);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $purchaseOrder,
                __('purchase_orders.log.updated', ['code' => $purchaseOrder->code, 'count' => $count]),
                $changes,
            );

            DB::commit();

            return redirect()
                ->route('PurchaseRequests.show', $purchaseOrder->id)
                ->with('success', __('purchase_orders.messages.updated'));
        } catch (ValidationException $e) {
            // انظر التعليق في store(): لا معاملة مفتوحة عند فشل التحقّق
            OperationLog::failure($e, self::LOG_MODULE, 'update', $id);

            return back()
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', __('purchase_orders.messages.validation_failed'));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'update', $id);

            return back()
                ->withInput()
                ->with('error', __('purchase_orders.messages.update_failed', ['error' => $e->getMessage()]));
        }
    }

    public function destroy($id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by);

        try {
            DB::beginTransaction();

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $purchaseOrder,
                __('purchase_orders.log.deleted', ['title' => $purchaseOrder->title]),
                ['old' => $this->auditSnapshot($purchaseOrder, $purchaseOrder->items()->count())],
            );

            $purchaseOrder->items()->delete();
            $purchaseOrder->productDetails()->delete();
            $purchaseOrder->delete();

            DB::commit();

            return redirect()->route('PurchaseRequests.index')
                ->with('success', __('purchase_orders.messages.deleted'));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'delete', $id);

            return redirect()->back()->with('error', __('purchase_orders.messages.delete_failed'));
        }
    }

    /**
     * حذف جماعي للطلبات المحدَّدة — يقابل زرّ «حذف المحدَّد» في شاشة
     * «سجلّ النشاطات» الذي نُقل تصميم جدولها إلى هنا.
     *
     * كل طلب يُسجَّل على حدة في السجلّات الثلاثة، فلا يضيع أثرُ ما حُذف.
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:purchase_orders,id',
        ]);

        try {
            DB::beginTransaction();

            /* النطاقُ يُطبَّق على الجلب لا على الواجهة وحدَها: المعرّفات
               تصل في متن الطلب، فمن لا يرى مستنداً كان يحذفه بإرسال
               رقمه. والقيدُ على محور التخويل (`created_by`) لا على فرع
               الهيدر — هذا الأخير فلترُ عرضٍ يتبدّل بالمُنتقي. */
            $orders = $this->scopeToCreator(PurchaseOrder::whereIn('id', $data['ids']))->get();

            foreach ($orders as $order) {
                OperationLog::record(
                    'deleted',
                    self::LOG_MODULE,
                    $order,
                    __('purchase_orders.log.deleted', ['title' => $order->title]),
                    ['old' => $this->auditSnapshot($order, $order->items()->count())],
                );

                $order->items()->delete();
                $order->productDetails()->delete();
                $order->delete();
            }

            DB::commit();

            return redirect()->back()->with('success', __('purchase_orders.bulk.deleted', ['count' => $orders->count()]));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();
            OperationLog::failure($e, self::LOG_MODULE, 'bulk_delete');

            return redirect()->back()->with('error', __('purchase_orders.bulk.delete_failed'));
        }
    }

    public function approve(Request $request, $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by);

        DB::beginTransaction();
        try {
            $purchaseOrder->update([
                'status' => 'approval',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_note' => $request->note,
            ]);

            $this->recordStatusChange($purchaseOrder, 'approved', 'Under Review', 'approval');

            DB::commit();

            return redirect()->back()->with('success', __('purchase_orders.messages.approved'));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();

            OperationLog::failure($e, self::LOG_MODULE, 'approve', $id);

            return redirect()->back()->with('error', __('purchase_orders.messages.approve_failed'));
        }
    }

    // دالة رفض الطلب
    public function reject(Request $request, $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by);

        DB::beginTransaction();
        try {
            $purchaseOrder->update([
                'status' => 'disagree',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);

            $this->recordStatusChange($purchaseOrder, 'rejected', 'Under Review', 'disagree');

            DB::commit();

            return redirect()->back()->with('success', __('purchase_orders.messages.rejected'));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();

            OperationLog::failure($e, self::LOG_MODULE, 'reject', $id);

            return redirect()->back()->with('error', __('purchase_orders.messages.reject_failed'));
        }
    }

    /*
     * تنويه: cancelApproval() أُزيلت عمداً — اعتماد طلب الشراء صار نهائياً
     * لا رجعة فيه (لا تعديل ولا إلغاء اعتماد بعد الموافقة)، بنفس منطق
     * QuotationsController تماماً. الزرّ والنموذج أُزيلا من show.blade.php،
     * والمسار من routes/web.php، والصلاحية من قائمة .approve_reject هنا —
     * إزالةٌ كاملة على الطبقات الثلاث لا إخفاءٌ في الواجهة وحدها.
     */

    // دالة التراجع عن الرفض
    public function undoRejection(Request $request, $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by);

        // التحقق من أن الطلب مرفوض أصلاً
        if ($purchaseOrder->status !== 'disagree') {
            return redirect()->back()->with('error', __('purchase_orders.messages.not_rejected'));
        }

        DB::beginTransaction();
        try {
            $purchaseOrder->update([
                'status' => 'Under Review',
                'rejected_by' => null,
                'rejected_at' => null,
                'restored_by' => auth()->id(),
                'restored_at' => now(),
            ]);

            $this->recordStatusChange($purchaseOrder, 'rejection_undone', 'disagree', 'Under Review');

            DB::commit();

            return redirect()->back()->with('success', __('purchase_orders.messages.rejection_undone'));
        } catch (\Exception $e) {
            $this->rollBackIfOpen();

            OperationLog::failure($e, self::LOG_MODULE, 'undo_rejection', $id);

            return redirect()->back()->with('error', __('purchase_orders.messages.undo_rejection_failed'));
        }
    }

    /**
     * معاينة الطباعة في المتصفّح — الوجه الآخر لخيار «طريقة الطباعة»
     * في إعدادات الحساب، ويستعمل نفس قالب المستند المختار.
     */
    public function printView($id, $template = 'default')
    {
        $purchaseOrder = PurchaseOrder::with(['creator', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseOrder->created_by);

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $purchaseOrder,
            __('purchase_orders.log.printed', ['code' => $purchaseOrder->code]),
        );

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html !== null) {
            return response(DocumentPrintPage::wrap($html));
        }

        return view($this->builtinTemplateView($template), $this->builtinTemplateData($purchaseOrder) + [
            'autoPrint' => true,
        ]);
    }

    /**
     * Generate PDF for purchase order.
     *
     * `$template` إمّا معرّف قالب مصمَّم في إعدادات «قوالب المستندات»،
     * وإمّا اسم أحد القوالب المدمجة.
     */
    public function generatePdf($id, $template = 'default')
    {
        try {
            $purchaseOrder = PurchaseOrder::with(['creator', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseOrder->created_by);

            /* القالب المصمَّم في الإعدادات يُعرض صفحةَ طباعة — TCPDF كان
               يبعثر تصميمه (انظر ServesDesignedTemplatePage). */
            if ($page = $this->configuredTemplatePage($id, $template, $purchaseOrder->code)) {
                OperationLog::record(
                    'exported',
                    self::LOG_MODULE,
                    $purchaseOrder,
                    __('purchase_orders.log.exported', ['code' => $purchaseOrder->code]),
                );

                return $page;
            }

            $html = view($this->builtinTemplateView($template), $this->builtinTemplateData($purchaseOrder))->render();

            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle(__('purchase_orders.pdf.title').' - '.$purchaseOrder->code);

            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // الاتجاه يتبع لغة الواجهة لا العربية دائماً
            $pdf->setRTL(LanguageSettings::isRtl());

            $pdf->AddPage();
            $pdf->SetFont('aealarabiya', '', 12);
            $pdf->writeHTML($html, true, false, true, false, '');

            $filename = 'purchase-order-'.$purchaseOrder->code.'.pdf';

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseOrder,
                __('purchase_orders.log.exported', ['code' => $purchaseOrder->code]),
            );

            return $pdf->Output($filename, 'D'); // D للتحميل، I للعرض المباشر
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return redirect()->back()->with('error', __('purchase_orders.messages.pdf_failed', ['error' => $e->getMessage()]));
        }
    }

    // ==================== دوالّ مساعدة ====================

    /**
     * تراجع آمن — لا يمسّ إلا معاملةً مفتوحة فعلاً.
     *
     * `DB::rollBack()` بلا شرط يتراجع عن معاملة المُنادي حين يكون النداء
     * مغلَّفاً بمعاملة أوسع (اختبار، أمر Artisan، عملية مركّبة).
     */
    private function rollBackIfOpen(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    /**
     * قواعد التحقّق — واحدة للإنشاء والتعديل حتى لا تتباعدا.
     */
    private function rules($ignoreId = null): array
    {
        return [
            'title' => 'required|string|max:255',
            // التفرّد يُفحص عند التعديل فقط. عند الإنشاء يتولّاه `resolveCode()`
            // بالقفز إلى أول رقم شاغر، فرفض الطلب برسالة «الكود مستخدم من قبل»
            // كان يعطّل الحفظ على رقم ولّدته الشاشة نفسها ولا يملك المستخدم تغييره.
            'code' => 'nullable|string|max:50'.($ignoreId ? '|unique:purchase_orders,code,'.$ignoreId : ''),
            'order_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
            // يُعتمَد فقط لمن يملك صلاحية اختيار الفرع — انظر Scopes::canSelectBranch()
            'branch_id' => 'nullable|integer|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            // العمود `quantity` صحيح في قاعدة البيانات، فالكسر يُبتر صامتاً
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            // نسبة% أو مبلغ ثابت — يحدَّد تفسير `discount` أعلاه
            'items.*.discount_type' => 'nullable|in:amount,percentage',
            // النسبة لا المبلغ: العمود `tax_1` من نوع decimal(5,2)
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:999.99',
            // المعرّف هو المصدر: منه تُقرأ النسبة **ونوعها** من «إعدادات
            // الضريبة»، والنسبة المرسلة معه لا تُصدَّق وحدها
            'items.*.tax_1_id' => 'nullable|exists:tax_sittings,id',
            'items.*.description' => 'nullable|string',
            // الوحدة: `validate()` يُسقط كلّ مفتاحٍ بلا قاعدة، فبدون هذه
            // الثلاثة كان البند يُحفظ بلا وحدة ويعود «عبوةً» «حبّةً» عند التحويل.
            // المعرّف رقمٌ أو `cu:<id>` لوحدة الصنف — انظر ProductUnits::fieldsFor()
            'items.*.product_unit_id' => 'nullable',
            'items.*.unit_name' => 'nullable|string|max:255',
            'items.*.conversion_factor' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * الرقم المعتمد للطلب — ما عرضته الشاشة، وإلا التالي من إعدادات التسلسل.
     * حقل الرقم للقراءة فقط، فما يصل هنا هو ما عُرض لا ما كُتب.
     */
    private function resolveCode(?string $submitted): string
    {
        $submitted = trim((string) $submitted);

        if ($submitted === '' || PurchaseOrder::where('code', $submitted)->exists()) {
            // الرقم المعروض في الشاشة قد يكون استُهلك بين الفتح والحفظ
            // (مستخدم آخر، أو تبويب مفتوح منذ مدّة) — التالي الشاغر بدله
            return next_document_number(self::NUMBER_SECTION);
        }

        return $submitted;
    }

    private function storeAttachment(Request $request): string
    {
        $file = $request->file('attachments');

        return $file->storeAs(
            'purchase_orders/attachments',
            time().'_'.$file->getClientOriginalName(),
            'public'
        );
    }

    /**
     * كتابة بنود الطلب — يُفترض أن الجدول أُفرغ قبل النداء عند التعديل.
     *
     * @return int عدد البنود المكتوبة فعلاً
     */
    private function syncItems(PurchaseOrder $purchaseOrder, array $items): int
    {
        $written = 0;

        foreach ($items as $itemData) {
            if (empty($itemData['product_id']) || ! isset($itemData['quantity'])) {
                continue;
            }

            $product = Product::find($itemData['product_id']);

            $quantity = (int) $itemData['quantity'];
            $unitPrice = (float) ($itemData['unit_price'] ?? 0);
            $rawDiscount = (float) ($itemData['discount'] ?? 0);
            $isPercentageDiscount = ($itemData['discount_type'] ?? 'amount') === 'percentage';

            // نسبةٌ من إجمالي البند قبل الخصم، أو مبلغٌ ثابتٌ كما هو — نفس
            // اصطلاح InvoicesPurchaseController على الجدول نفسه
            $discount = $isPercentageDiscount
                ? round($quantity * $unitPrice * $rawDiscount / 100, 2)
                : $rawDiscount;

            // النسبة من الإعداد نفسه لا مما أرسلته الشاشة، ومعها نوعها:
            // المتضمَّنة تُستخرَج من السعر فلا تزيد إجمالي البند
            $taxId = $itemData['tax_1_id'] ?? null;
            $taxRate = $taxId
                ? PurchaseTaxCalculator::rate($taxId)
                : (float) ($itemData['tax_rate'] ?? 0);

            $line = PurchaseTaxCalculator::line($quantity, $unitPrice, $discount, [$taxId], [$taxRate]);

            InvoiceItem::create(ProductUnits::fieldsFor($itemData, (float) $quantity) + [
                'purchase_order_id' => $purchaseOrder->id,
                'invoice_id' => null,
                'product_id' => $itemData['product_id'],
                'item' => $product->name ?? '',
                'description' => $itemData['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                // اصطلاح invoice_items الحيّ في وحدة المشتريات: 2=نسبة، 1=مبلغ
                // (انظر InvoicesPurchaseController — نفس الجدول نفس الاصطلاح)
                'discount_type' => $isPercentageDiscount ? 2 : 1,
                // النسبة والمعرّف معاً: المبلغ يُشتقّ منهما — انظر OrderLine
                'tax_1' => $taxRate,
                'tax_1_id' => $taxId ?: null,
                'total' => $line['total'],
            ]);

            $written++;
        }

        return $written;
    }

    /**
     * إجماليات الطلب — تُحسب من البنود لأن الجدول لا يخزّنها.
     *
     * @return array{subtotal: float, discount: float, tax: float, grand_total: float}
     */
    private function totalsFor(PurchaseOrder $purchaseOrder): array
    {
        return OrderLine::totals($purchaseOrder->items);
    }

    /**
     * نسب الضريبة المعرَّفة في شاشة «إعدادات الضريبة» — لا نِسَب مكتوبة
     * في القالب.
     *
     * @return Collection
     */
    private function taxOptions()
    {
        try {
            return TaxSitting::orderBy('name')->get();
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.OrdersPurchase', 'taxOptions');

            return collect();
        }
    }

    /**
     * يصيّر قالباً مصمَّماً في الإعدادات — أو null إن لم يكن `$template`
     * معرّفَ قالبٍ صالح، فيتولّى النداءُ القوالبَ المدمجة.
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
     * مسار القالب المدمج — القيم المجهولة ترجع إلى الافتراضي بدل أن
     * ترمي `View not found`.
     */
    private function builtinTemplateView($template): string
    {
        $base = 'purchases::purchases.purchase_requests.partials.pdf';

        return in_array($template, self::BUILTIN_TEMPLATES, true) && $template !== 'default'
            ? $base.'_'.$template
            : $base;
    }

    /**
     * بيانات القوالب المدمجة — الترويسة تأتي من «معلومات المنشأة» في
     * الإعدادات لا من نصّ ثابت في القالب.
     */
    private function builtinTemplateData(PurchaseOrder $purchaseOrder): array
    {
        return [
            'purchaseOrder' => $purchaseOrder,
            'totals' => $this->totalsFor($purchaseOrder),
            'business' => app(DocumentTemplateDataService::class)->getBusinessData(),
        ];
    }

    /**
     * تسجيل تغيّر حالة — الحالة القديمة والجديدة تُحفظان في فرق القيم
     * فتظهران في تفاصيل السجلّ.
     */
    private function recordStatusChange(PurchaseOrder $purchaseOrder, string $event, string $from, string $to): void
    {
        OperationLog::record(
            self::STATUS_ACTIONS[$event] ?? 'updated',
            self::LOG_MODULE,
            $purchaseOrder,
            __('purchase_orders.log.'.$event, ['title' => $purchaseOrder->title]),
            ['old' => ['status' => $from], 'new' => ['status' => $to]],
        );
    }

    /**
     * لقطة مختصرة من الطلب — تُحفظ في فرق القيم عند الإنشاء والحذف.
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(PurchaseOrder $purchaseOrder, int $itemsCount): array
    {
        return [
            'code' => $purchaseOrder->code,
            'title' => $purchaseOrder->title,
            'order_date' => (string) $purchaseOrder->order_date,
            'due_date' => (string) $purchaseOrder->due_date,
            'status' => $purchaseOrder->status,
            'items' => $itemsCount,
        ];
    }
}
