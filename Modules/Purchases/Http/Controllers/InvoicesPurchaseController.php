<?php

namespace Modules\Purchases\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Models\AccountSetting;
use App\Models\CategoryUnit;
use App\Models\ClientRelation;
use App\Models\CostCenter;
use App\Models\DefaultWarehouses;
use App\Models\DocumentTemplate;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PaymentsProcess;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoice;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\Template;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\Fawtra\SubscriptionGate;
use App\Services\Purchases\PurchaseDocumentTotals;
use App\Services\Purchases\PurchaseTaxCalculator;
use App\Services\Wallets\SupplierWalletService;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\OrganizationInfo;
use App\Support\PrintSettings;
use App\Support\StockEligibility;
use App\Support\StorehouseAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Support\PurchaseInvoiceJournal;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\PurchaseWarehouse;
use Modules\Purchases\Support\SupplierGuard;
use TCPDF;

class InvoicesPurchaseController extends Controller
{
    use Concerns\ResolvesPurchaseAccounts;

    use Concerns\RespectsAccountingPeriods;
    use Concerns\ServesDesignedTemplatePage;
    use ScopesToEmployeeHierarchy;

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'purchase-invoice';

    /** نوع المستند في شاشة «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'purchase_invoice';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمستند */
    public const LOG_MODULE = 'purchase_invoice';

    public function __construct()
    {
        // مُنقولة إلى كتالوج purchases.invoices.* — نفس نقل
        // PurchaseRequestsController/QuotationsController سابقاً في هذه الوحدة.
        // الأسماء القديمة (inv_manage_purchase_invoice_*) لم تعد تُستعمَل هنا؛
        // الأدوار الثلاثة التي كانت تملكها (owner/manager/admin) مُنحت
        // الأسماء الجديدة صراحةً في قاعدة البيانات عند هذا النقل.
        /* `paginatePurchase` و`markAsUnpaid` مضافتان هنا: كانتا الدالّتين
           العامّتين الوحيدتين في الوحدة بلا حارس — والنظائرُ الثلاث
           (المرتجع، الأوامر، الإشعار المدين) تحرس `paginatePurchase`
           منذ البداية. ولا مسارَ لهما اليوم، فالحرسُ سبقٌ لا سدُّ ثغرة:
           إضافةُ مسارٍ لاحقاً لا تفتح بابَهما بالسكوت. */
        $this->middleware('permission:purchases.invoices.view', ['only' => [
            'index', 'paginatePurchase', 'show', 'getNotes',
        ]]);
        $this->middleware('permission:purchases.invoices.export', ['only' => [
            'exportPDF', 'exportExcel', 'print', 'generateNotesPdf', 'printPdf', 'compactPdf', 'generatePdf', 'printView',
        ]]);
        // `copy` تُنشئ فاتورةً جديدة وتستهلك حصّة الاشتراك تماماً كـ`store`،
        // فهي إنشاءٌ لا تعديل — وكانت مصنَّفةً مع `edit` فيَنسخ من لا يملك
        // الإنشاء. وهو تصنيفُ النسخ في بقيّة الوحدة (`Quotations.duplicate`
        // و`ViewPurchasePrice.duplicate` كلتاهما على `.create`).
        $this->middleware('permission:purchases.invoices.create', ['only' => ['create', 'store']]);
        // النسخُ صلاحيتُه المستقلّة — انظر PurchasesPermissions::groups()['invoices']
        $this->middleware('permission:purchases.invoices.duplicate', ['only' => ['copy']]);
        $this->middleware('permission:purchases.invoices.edit', ['only' => [
            'edit', 'update',
            'markAsPending', 'markAsPaid', 'markAsUnpaid', 'markAsCancelled', 'updatePaymentStatus',
            'addNote', 'deleteNote', 'assignCostCenter', 'updateCostCenter', 'convertToCreditMemo',
        ]]);
        $this->middleware('permission:purchases.invoices.delete', ['only' => ['destroy']]);
        $this->middleware('permission:purchases.invoices.approve', ['only' => ['markAsApproved']]);
    }

    public function index(Request $request)
    {
        /* نطاق التاريخ الافتراضي هو **الفترة المحاسبية الجارية** لا تاريخ
           المنشأة كلّه — كما في شاشة فواتير المبيعات. كانت الشاشة تفتح على
           كل فاتورة شراء سُجّلت منذ التأسيس، وبطاقاتُ الملخّص تجمع سنواتٍ
           أُقفلت وأُصدرت قوائمها المالية. */
        $range = $this->reportDateRange($request);

        // فلاترُ القائمة تتبع فرعَ الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers()->get();
        $users = $this->creatorOptions(\App\Models\PurchaseInvoice::where('type', 'invoice'));
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax()) {
            return $this->getFilteredData($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $purchaseData = $this->getFilteredData($request, false);

        // عدّاد حالات الدفع لشريط الملخّص — استعلام واحد لا أربعة،
        // وعلى نطاق الشاشة نفسِه لا على التاريخ كلّه
        // ونطاقُ العدّاد هو نطاقُ الجدول: الفرعُ النشط ونطاقُ الرؤية معاً،
        // وإلّا عدّت البطاقةُ فواتيرَ لا تظهر في الجدول تحتها
        $countsQuery = $this->scopeToPeople(
            PurchaseInvoice::where('type', 'invoice')
                ->whereDate('date', '>=', $range['start'])
                ->whereDate('date', '<=', $range['end'])
        );

        /* الفرعُ الرئيسيّ مستثنىً — وكذلك محورُ الأشخاص، انظر
           `applyHeaderBranchScope()`. */
        $this->applyHeaderBranchScope($countsQuery);

        // وما أُقفل لا يُعَدّ: البطاقةُ فوق الجدول تعدّ ما يعدّه الجدول
        $this->excludeClosedRanges($countsQuery, 'purchase_invoices.date');

        $counts = $countsQuery
            ->selectRaw('payment_status, COUNT(*) as aggregate')
            ->groupBy('payment_status')
            ->pluck('aggregate', 'payment_status');

        $stats = [
            'total' => (int) $counts->sum(),
            'paid' => (int) ($counts['paid'] ?? 0),
            // الصيغتان تُجمعان: `store()`/`update()` تكتبان `partially_paid`،
            // بينما `partial` قيمةٌ موروثة من شيفرةٍ أقدم لا تزال في صفوفٍ
            // محفوظة (ولذلك تقبل خرائط الحالات في `partials/table` وشاشة
            // العرض كلتيهما). وقراءة `partial` وحدها كانت تُبقي بطاقة
            // «مدفوعة جزئياً» على صفرٍ أبداً مهما بلغ عدد تلك الفواتير.
            'partial' => (int) ($counts['partially_paid'] ?? 0) + (int) ($counts['partial'] ?? 0),
            'unpaid' => (int) ($counts['unpaid'] ?? 0),
        ];

        // خيارات البحث المتقدّم — كانت القائمتان تُبنيان من متغيّرات لا
        // يمرّرها أحد، فتخرجان فارغتين دائماً
        $accounts = Account::orderBy('name')->get(['id', 'name']);
        $taxes = TaxSitting::orderBy('name')->get(['id', 'name', 'tax', 'type']);

        // حالة حدّ اشتراك فوترة لميزة «فواتير المشتريات» — نفس نمط
        // PurchaseRequestsController::index(). تبقى known === false حتى تُعرِّف
        // فوترة الميزة في كتالوجها، فلا تظهر الشارة في الواجهة قبل ذلك
        // (انظر تعليق UsageReporter::catalog()).
        $subscriptionLimit = app(SubscriptionGate::class)->limit('purchase_invoices');

        /* خيارات التصفية بالفرع والموظّف — الفرعُ والموظّفُ مختومان على
           المستند نفسِه (`branch_id` و`employee_id`)، فالتصفيةُ بهما تقرأ
           عمودَه لا سياقَ منشئه اليوم.

           والفروعُ كلُّها لا النشطةُ وحدها: الشاشة تقرأ تاريخاً، ومستندٌ
           صدر من فرعٍ أُوقف لاحقاً يجب أن يبقى قابلاً للتصفية. */
        $branches = \App\Models\Branch::orderBy('name')->get(['id', 'name']);
        $employees = \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::orderBy('first_name'))->get();

        // ما يضيّق القائمة — يُعرض فوقها فلا تبدو شاشةٌ فارغةٌ عطلاً
        $scopeNotice = $this->listScopeNotice($range);

        return view('purchases::purchases.invoices_purchase.index', compact(
            'scopeNotice',
            'purchaseData',
            'suppliers',
            'users',
            'account_setting',
            'stats',
            'accounts',
            'taxes',
            'subscriptionLimit',
            'range',
            'branches',
            'employees'
        ));
    }

    private function getFilteredData(Request $request, $returnJson = true)
    {
        $user = auth()->user();
        $query = PurchaseInvoice::query()
            // `relatedDocuments` يقرأها الجدول ليعرف الفاتورة المرتجعة، فتُحمَّل
            // مسبقاً بدل استعلامٍ لكل صفّ
            ->with(['supplier', 'creator', 'relatedDocuments'])
            ->whereIn('type', ['invoice', 'Return']);

        // نطاق البيانات — نفس نمط PurchaseRequestsController::index() بعد
        // الانتقال إلى purchases.invoices.view (صلاحية واحدة ثنائية لا
        // "كل/خاصة"): من يملك data_scope_all يبقى بلا تقييد؛ غيره يُقيَّد
        // بنطاقه حسب صلاحيات data_scope_* الأخرى، وإلا created_by فقط
        /* والمنشئُ **أو** المسؤول: محاسبٌ يُدخل فاتورةً عن مندوب يكتب
           `created_by` باسمه و`employee_id` باسم المندوب، فالقيدُ على
           الأوّل وحده كان يُخفيها عن المندوب — انظر `scopeToPeople()`. */
        $query = $this->scopeToPeople($query);

        /* الفرع النشط من الهيدر — كما تفعل شاشة فواتير المبيعات.
           مبدّلُ الفروع يكتب `users.branch_id` و`current_branch_id()` تقرؤه،
           وكانت الشاشة لا تقرؤه: يختار المستخدم فرعاً فيبقى الجدول على
           مستندات المنشأة كلّها. المديرُ على «كل الفروع» قيمتُه `null` فلا
           تصفية، والمستنداتُ التي سبقت عمودَ الفرع تبقى ظاهرةً (`forBranch`).

           والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها، فمن
           يقف عليه يرى المشتريات كلَّها. كان يُعامَل كأيّ عقدةٍ أخرى فيرى
           ما وُسِم برقمه وحدَه — وهو في العادة أقلُّ العقد وسماً، لأنّ
           المستندات تُنسَب إلى الفروع والمناطق العاملة لا إلى المركز. */
        $this->applyHeaderBranchScope($query, $request);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        // استبعاد الفواتير التي لها فواتير مرتجع
        $query->whereDoesntHave('returns', function ($subQuery) {
            $subQuery->where('type', 'Return');
        });

        // ترتيب النتائج
        $query->orderBy('created_at', 'desc');

        // الحصول على النتائج مع التقسيم إلى صفحات
        $purchaseData = $query->paginate(30);

        if ($returnJson) {
            $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

            return response()->json([
                'success' => true,
                'data' => view('purchases::purchases.invoices_purchase.partials.table', compact('purchaseData', 'account_setting'))->render(),
                // الشاشة تحدّث شريط الترقيم من هذا المفتاح — كان غائباً فيُفرَّغ
                'pagination' => view('purchases::purchases.invoices_purchase.partials.pagination', compact('purchaseData'))->render(),
                'total' => $purchaseData->total(),
                'current_page' => $purchaseData->currentPage(),
                'last_page' => $purchaseData->lastPage(),
                'from' => $purchaseData->firstItem(),
                'to' => $purchaseData->lastItem(),
            ]);
        }

        return $purchaseData;
    }

    private function applyFilters($query, $request)
    {
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
            $this->applyPaymentStatusFilter($query, $request->payment_status);
        }

        // البحث بواسطة المنشئ
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        /* الفرع — من عمود المستند نفسِه لا من فرع مُنشئه الحاليّ.
           فمن بدّل فرعه من الهيدر لا ينتقل تاريخُه معه إلى الفرع الجديد. */
        if ($request->filled('branch')) {
            $query->forBranch($request->branch);
        }

        /* الموظّف المسؤول — مختومٌ على المستند لحظةَ إنشائه
           (`StampsOwnerContext`)، لا مستنتجاً بقفزةٍ إلى `users` ثمّ
           `employees` تُعيد كتابةَ التاريخ كلّما نُقل الموظّف. */
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
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
            $query->where('type', $request->source);
        }

        // البحث بحالة التسليم
        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        /* البحث بالتاريخ — والنطاقُ مطبَّقٌ دائماً.
           حقلا التاريخ فارغَين كانا يعنيان «بلا حدّ»، فتُقرأ الشاشة على
           تاريخ المنشأة كلّه. صارا يعنيان الفترة المحاسبية الجارية. */
        $range = $this->reportDateRange($request);
        $query->whereDate('date', '>=', $range['start'])
            ->whereDate('date', '<=', $range['end']);

        /* ** الفترات المقفلة ** — تُستبعد من الشاشة كلّها لا من نطاقها
           الافتراضيّ وحده. من أقفل سنةً لا يريد فواتيرَها أن تعود بضبط
           التاريخ يدوياً.

           والفحص على `date` — تاريخِ المستند — لا `created_at`: الإقفال
           محاسبيٌّ يخصّ تاريخ الفاتورة لا لحظةَ إدخالها، وهو التاريخ نفسه
           الذي يفحصه منعُ الحفظ في `store` — فما مُنع كتابةً لا يظهر
           قراءةً. */
        $this->excludeClosedRanges($query, 'purchase_invoices.date');

        // البحث بتاريخ الإنشاء
        if ($request->filled('created_at_from')) {
            $query->whereDate('created_at', '>=', $request->created_at_from);
        }
        if ($request->filled('created_at_to')) {
            $query->whereDate('created_at', '<=', $request->created_at_to);
        }

        // الفلاتر المضافة حديثاً بناءً على قاعدة البيانات:

        // البحث بحالة الاستلام (receiving_status)
        if ($request->filled('receiving_status')) {
            $query->where('receiving_status', $request->receiving_status);
        }

        // البحث برقم الحساب (account_id)
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // البحث بعدد البنود (terms)
        if ($request->filled('terms_min')) {
            $query->where('terms', '>=', $request->terms_min);
        }
        if ($request->filled('terms_max')) {
            $query->where('terms', '<=', $request->terms_max);
        }

        // البحث بمبلغ الخصم (discount_amount)
        if ($request->filled('discount_amount_min')) {
            $query->where('discount_amount', '>=', $request->discount_amount_min);
        }
        if ($request->filled('discount_amount_max')) {
            $query->where('discount_amount', '<=', $request->discount_amount_max);
        }

        // البحث بنسبة الخصم (discount_percentage)
        if ($request->filled('discount_percentage_min')) {
            $query->where('discount_percentage', '>=', $request->discount_percentage_min);
        }
        if ($request->filled('discount_percentage_max')) {
            $query->where('discount_percentage', '<=', $request->discount_percentage_max);
        }

        // البحث بنوع الخصم (discount_type)
        if ($request->filled('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }

        // البحث بالدفعة المقدمة (advance_payment)
        if ($request->filled('advance_payment_min')) {
            $query->where('advance_payment', '>=', $request->advance_payment_min);
        }
        if ($request->filled('advance_payment_max')) {
            $query->where('advance_payment', '<=', $request->advance_payment_max);
        }

        // البحث بنوع الدفعة المقدمة (advance_payment_type)
        if ($request->filled('advance_payment_type')) {
            $query->where('advance_payment_type', $request->advance_payment_type);
        }

        // البحث بحالة الدفع المنطقية (is_paid)
        if ($request->filled('is_paid')) {
            $query->where('is_paid', $request->is_paid);
        }

        // البحث بطريقة الدفع (payment_method)
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // البحث بنوع الضريبة (tax_type)
        if ($request->filled('tax_type')) {
            $query->where('tax_type', $request->tax_type);
        }

        // البحث بتكلفة الشحن (shipping_cost)
        if ($request->filled('shipping_cost_min')) {
            $query->where('shipping_cost', '>=', $request->shipping_cost_min);
        }
        if ($request->filled('shipping_cost_max')) {
            $query->where('shipping_cost', '<=', $request->shipping_cost_max);
        }

        // البحث بالمجموع الفرعي (subtotal)
        if ($request->filled('subtotal_min')) {
            $query->where('subtotal', '>=', $request->subtotal_min);
        }
        if ($request->filled('subtotal_max')) {
            $query->where('subtotal', '<=', $request->subtotal_max);
        }

        // البحث بالقيمة المستحقة (due_value)
        if ($request->filled('due_value_min')) {
            $query->where('due_value', '>=', $request->due_value_min);
        }
        if ($request->filled('due_value_max')) {
            $query->where('due_value', '<=', $request->due_value_max);
        }

        // البحث بإجمالي الخصم (total_discount)
        if ($request->filled('total_discount_min')) {
            $query->where('total_discount', '>=', $request->total_discount_min);
        }
        if ($request->filled('total_discount_max')) {
            $query->where('total_discount', '<=', $request->total_discount_max);
        }

        // البحث بإجمالي الضريبة (total_tax)
        if ($request->filled('total_tax_min')) {
            $query->where('total_tax', '>=', $request->total_tax_min);
        }
        if ($request->filled('total_tax_max')) {
            $query->where('total_tax', '<=', $request->total_tax_max);
        }

        // البحث بالإجمالي الكلي (grand_total)
        if ($request->filled('grand_total_min')) {
            $query->where('grand_total', '>=', $request->grand_total_min);
        }
        if ($request->filled('grand_total_max')) {
            $query->where('grand_total', '<=', $request->grand_total_max);
        }

        // البحث في الملاحظات (notes)
        if ($request->filled('notes')) {
            $query->where('notes', 'LIKE', '%'.$request->notes.'%');
        }
    }

    // دالة مساعدة لفلترة حالة الدفع
    private function applyPaymentStatusFilter($query, $paymentStatus)
    {
        switch ($paymentStatus) {
            case 'paid':
                $query->where('payment_status', 'paid');
                break;
            case 'partial':
                $query->where('payment_status', 'partially_paid');
                break;
            case 'unpaid':
                $query->where('payment_status', 'unpaid');
                break;
            case 'returned':
                $query->where('payment_status', 'returned');
                break;
            case 'overpaid':
                $query->where('payment_status', 'overpaid');
                break;
            case 'draft':
                $query->where('payment_status', 'draft');
                break;
        }
    }

    // دالة للتعامل مع طلبات الـ pagination عبر Ajax
    public function paginatePurchase(Request $request)
    {
        if ($request->ajax()) {
            return $this->getFilteredPurchaseData($request);
        }

        return redirect()->route('invoicePurchases.index');
    }

    // دالة حذف الفاتورة مع دعم Ajax

    public function create(Request $request)
    {
        $user = auth()->user();

        $selectedSupplierId = $request->supplier_id;

        /* المورِّدون على فرع الشريط العلويّ وما يتبعه — الرئيسيُّ يعني المنشأة
           كلَّها ({@see \App\Support\HeaderBranchScope}). والمورِّدُ المُمرَّر
           في الرابط يبقى ولو كان على جهةٍ أخرى. */
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), array_filter([$selectedSupplierId]))->get();

        // ✅ جلب المنتجات مع الوحدات
        $items = Product::with([
            'productUnits' => function ($query) {
                $query->where('is_active', true)
                    ->with(['categoryUnit' => function ($q) {
                        $q->with('unit');
                    }]);
            },
            'category.categoryUnits' => function ($query) {
                $query->where('is_active', true)->with('unit');
            },
        ])->get();

        // ✅ تحضير بيانات المنتجات مع الوحدات للـ View
        $productsWithUnits = $items->map(function ($product) {
            $units = [];

            // إذا كان للمنتج وحدات خاصة به
            if ($product->productUnits && $product->productUnits->count() > 0) {
                foreach ($product->productUnits as $productUnit) {
                    if ($productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                        $units[] = [
                            'id' => $productUnit->id,
                            'name' => $productUnit->categoryUnit->unit->name,
                            'conversion_factor' => (float) $productUnit->categoryUnit->conversion_factor,
                            'is_base_unit' => $productUnit->categoryUnit->is_base_unit ?? false,
                            'is_default' => $productUnit->is_default,
                            'sale_price' => (float) $productUnit->sale_price,
                            'purchase_price' => (float) $productUnit->purchase_price,
                            'barcode' => $productUnit->barcode,
                        ];
                    }
                }
            }
            // إذا لم يكن للمنتج وحدات خاصة، نستخدم وحدات الصنف
            elseif ($product->category && $product->category->categoryUnits) {
                foreach ($product->category->categoryUnits as $categoryUnit) {
                    if ($categoryUnit->unit) {
                        $units[] = [
                            'id' => null,
                            'category_unit_id' => $categoryUnit->id,
                            'name' => $categoryUnit->unit->name,
                            'conversion_factor' => (float) $categoryUnit->conversion_factor,
                            'is_base_unit' => $categoryUnit->is_base_unit ?? false,
                            'is_default' => $categoryUnit->is_base_unit ?? false,
                            'sale_price' => (float) $product->sale_price,
                            'purchase_price' => (float) $product->purchase_price,
                            'barcode' => null,
                        ];
                    }
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'purchase_price' => (float) $product->purchase_price,
                'sale_price' => (float) $product->sale_price,
                'units' => $units,
                'has_units' => count($units) > 0,
            ];
        });

        $accounts = Account::all();

        /* المستودعات مصفّاةً على صلاحية «إنشاء فاتورة» المضبوطة على المستودع
           نفسه — نفس ما تفعله شاشة فاتورة المبيعات، فلا يُعرَض ما لا يُسمح
           به. الحفظ يفحصها ثانيةً، فالتصفية هنا للراحة لا للحماية. */
        $storeHouses = StorehouseAccess::scopeCreateInvoices(\Modules\Purchases\Support\HeaderPickers::storeHouses())->get();

        /* قائمةُ حقل المستودع في تبويبة التسليم — مستودعاتُ الموظّف
           المربوطة أوّلاً. كان الحقلُ غائباً عن النموذج كلِّه والخادمُ
           يحسم المستودعَ وحدَه، فتدخل البضاعةُ الرئيسيَّ دائماً بلا أن
           يعرف المُدخِل أين ذهبت — انظر PurchaseWarehouse. */
        $warehouses = PurchaseWarehouse::optionsFor($user);
        $defaultWarehouseId = PurchaseWarehouse::defaultId(null, $user);

        $taxs = TaxSitting::all();
        $account_setting = AccountSetting::where('user_id', $user->id)->first();

        // الحصول على الخزائن
        // الحسابات الأب من شاشة «الحسابات المحاسبية» — المفتاح غير المربوط
        // يُستبعَد من القائمة بدل أن يُجلب حساب برقم مكتوب في الشيفرة
        $treasuries = Account::whereIn('parent_id', array_filter([
            AccountingSettings::id('bank_accounts_parent'),
            AccountingSettings::id('cash_treasuries_parent'),
        ]))->get();

        // تحديد الخزينة الرئيسية للمستخدم
        $MainTreasury = null;
        $treasury_id = null;
        // ✅ التحقق من الصلاحيات بدلاً من دور ثابت
        // من يملك صلاحية data_scope_all يستطيع اختيار أي خزينة
        $isAdmin = $user && $user->hasRolePermission('data_scope_all');

        if ($user && $user->employee_id) {
            $TreasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

            if ($TreasuryEmployee && $TreasuryEmployee->treasury_id) {
                $MainTreasury = Account::where('id', $TreasuryEmployee->treasury_id)->first();
                $treasury_id = $TreasuryEmployee->treasury_id;
            } else {
                $MainTreasury = AccountingSettings::account('main_treasury');
                $treasury_id = $MainTreasury->id ?? null;
            }
        } else {
            $MainTreasury = AccountingSettings::account('main_treasury');
            $treasury_id = $MainTreasury->id ?? null;
        }

        // الرقم من إعدادات «تسلسل أرقام المستندات» — معاينة بلا استهلاك
        $code = peek_document_number(self::NUMBER_SECTION);

        // جلب طرق الدفع النشطة
        $paymentMethods = \App\Models\PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $branches = \App\Models\Branch::orderBy('name')->get();

        /* الفترة المحاسبية تمنع الحفظ بتاريخ اليوم: يُقال الآن قبل ملء
           الفاتورة كلّها لا بعد الضغط على حفظ — كما في شاشة إنشاء فاتورة
           المبيعات. */
        $periodBlock = $this->accountingPeriodBlock(now());

        return view('purchases::purchases.invoices_purchase.create', compact(
            'suppliers',
            'code',
            'items',
            'productsWithUnits',
            'taxs',
            'accounts',
            'storeHouses',
            'account_setting',
            'selectedSupplierId',
            'treasuries',
            'MainTreasury',
            'treasury_id',
            'isAdmin',
            'paymentMethods',
            'branches',
            'periodBlock',
            'warehouses',
            'defaultWarehouseId'
        ));
    }

    public function store(Request $request)
    {
        try {
            /* الضريبة الشاملة على المستند — الخانةُ العلوية `document_tax_id`
               تُفرَض على كلّ بندِ شراءٍ حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات
               الضريبة، فتُخفى قوائمُ ضريبة الأسطر. */
            PurchaseTaxCalculator::applyComprehensive($request);

            // ** الفترة المحاسبية ** — تاريخ داخل فترة مقفلة يُرَدّ قبل أي كتابة
            if ($blocked = $this->accountingPeriodBlock($request->date)) {
                return redirect()->back()->withInput()->with('error', $blocked);
            }

            // اشتراك فوترة وحدوده — نفس نمط PurchaseRequestsController::store()
            $subscriptionGate = app(SubscriptionGate::class);
            if (! $subscriptionGate->allows('purchase_invoices')) {
                return back()->withInput()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «فواتير المشتريات». راجع لوحة تحكّم فوترة للترقية.');
            }
            $invoiceQuota = $subscriptionGate->check('purchase_invoices');
            if (! $invoiceQuota['allowed']) {
                return back()->withInput()->with('error', $invoiceQuota['reason']);
            }

            // الفرع يُعيّن تلقائياً من فرع المستخدم الحالي (موظف: فرعه المثبَّت،
            // وإلا فرع المستخدم) — إلا لمن يملك صلاحية اختيار فرع آخر صراحة
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'));

            /* ** التحقق من وجود المورد ** — قبل حجز رقم المستند لا بعده.
             *
             * نفس ترتيب `InvoicesController::store()`: فاتورةٌ تُرَدّ بعد أن
             * أخذت رقمها تترك ثغرةً في التسلسل يسأل عنها المحاسب. وكان
             * الفحص هنا يقع بعد `resolveCode()` وبعد فتح المعاملة معاً. */
            if (! $request->supplier_id) {
                throw new \Exception('يجب تحديد المورد');
            }

            $supplier = Supplier::find($request->supplier_id);
            if (! $supplier) {
                throw new \Exception('المورد المحدد غير موجود');
            }

            // «منع المستندات على مورد موقوف» — يُفحص قبل أول كتابة
            SupplierGuard::assertActive($supplier);

            $user = Auth::user();

            /* ** تحميل البيانات المشتركة دفعة واحدة ** — نفس ما تفعله
             * `InvoicesController::store()`.
             *
             * كان كل بند يستدعي `Product::findOrFail()` في حلقة الحساب ثمّ
             * `Product::find()` مرّةً أخرى في حلقة الكتابة، فتُستهلَك
             * ضِعفُ عدد البنود استعلاماتٍ على صفوفٍ مقروءةٍ سلفاً. */
            $productIds = collect($request->items)->pluck('product_id')->unique()->filter()->toArray();
            $productsCollection = Product::with([
                'productUnits' => function ($query) {
                    $query->where('is_active', true)->with([
                        'categoryUnit' => function ($q) {
                            $q->with('unit');
                        },
                    ]);
                },
            ])->whereIn('id', $productIds)->get()->keyBy('id');

            $productUnitIds = collect($request->items)->pluck('product_unit_id')->unique()->filter()->toArray();
            $productUnitsCollection = ProductUnit::with([
                'categoryUnit' => function ($q) {
                    $q->with('unit');
                },
            ])->whereIn('id', $productUnitIds)->get()->keyBy('id');

            $storeHouseIds = collect($request->items)->pluck('store_house_id')->unique()->filter()->toArray();
            $storeHousesCollection = StoreHouse::whereIn('id', $storeHouseIds)->get()->keyBy('id');

            /* صلاحية «إنشاء فاتورة» المضبوطة على المستودع — تُفحص قبل أي
             * كتابة، كما في المبيعات.
             *
             * كانت تُحفَظ في `crate_invoices_permissions` ولا يقرؤها أحد في
             * هذه الشاشة: لا قائمةُ المستودعات ولا الحفظ. وتصفيةُ القائمة
             * وحدها لا تكفي — من يبدّل قيمة `store_house_id` في المتصفّح
             * يتجاوزها. */
            foreach ($storeHousesCollection as $candidate) {
                if (! StorehouseAccess::canCreateInvoices($candidate, $user)) {
                    throw new \Exception(StorehouseAccess::message($candidate));
                }
            }

            if ($request->store_house_id
                && ! StorehouseAccess::canCreateInvoices($request->store_house_id, $user)) {
                throw new \Exception(StorehouseAccess::message($request->store_house_id));
            }

            /* ** المستودع المختار أو الافتراضي ** — نفس ترتيب المبيعات:
             * المُرسَل من الشاشة، ثمّ مستودع الموظّف الافتراضي، ثمّ الرئيسي.
             * كان الاشتقاق مقصوراً على `createWarehousePermit()` فيقفز من
             * المُرسَل إلى الرئيسي مباشرةً ويتجاهل مستودع الموظّف. */
            $defaultStoreHouse = $request->store_house_id
                ? StoreHouse::find($request->store_house_id)
                : null;

            // مستودعُ الموظّف يسبق الرئيسيَّ — ما لم يُضبط «استلام المشتريات في المستودع الرئيسي»
            if (! $defaultStoreHouse && $user && $user->employee_id && ! PurchaseWarehouse::mainFirst()) {
                $defaultWarehouse = DefaultWarehouses::where('employee_id', $user->employee_id)->first();
                if ($defaultWarehouse && $defaultWarehouse->storehouse_id) {
                    $defaultStoreHouse = StoreHouse::find($defaultWarehouse->storehouse_id);
                }
            }

            if (! $defaultStoreHouse) {
                $defaultStoreHouse = StoreHouse::where('major', 1)->first();
            }

            // ** الخطوة الأولى: إنشاء كود للفاتورة **
            // ** الرقم من إعدادات «تسلسل أرقام المستندات» **
            $code = $this->resolveCode($request->code);

            DB::beginTransaction(); // بدء المعاملة

            // ✅ جلب جميع إعدادات فواتير الشراء
            $autoPaidSetting = PurchaseSettings::enabled('default_paid_invoices');
            $autoReceivedSetting = PurchaseSettings::enabled('default_received_invoices');
            $autoPaymentSetting = PurchaseSettings::enabled('auto_payment');
            $updatePricesSetting = PurchaseSettings::enabled('update_product_prices');
            $manualInvoiceStatusSetting = PurchaseSettings::enabled('manual_invoice_status');
            $enableSettlementSetting = PurchaseSettings::enabled('enable_settlement');
            // `total_discounts` عرضٌ لا حساب: يقرّر إظهار سطر «إجمالي الخصومات»
            // في ملخّص الإجماليات والمطبوعات لا مقدارَ الخصم نفسه، فمكانه
            // القوالب (`partials/totals` وملفّات الطباعة) لا هذا المتحكّم —
            // كان يُقرأ هنا في متغيّر لا يستعمله أحد.

            // ** تجهيز المتغيرات الرئيسية لحساب الفاتورة **
            $total_amount = 0; // إجمالي المبلغ قبل الخصومات
            $total_discount = 0; // إجمالي الخصومات على البنود
            $documentTaxIds = []; // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن
            $items_data = []; // تجميع بيانات البنود
            // الضريبةُ تُحسب بعد الحلقة لا فيها: وعاؤها يحتاج الخصمَ العامّ
            // وهو غيرُ معروفٍ بعد — انظر documentTotals()
            $lineNets = []; // صافي كلّ بندٍ بعد خصمه الخاصّ
            $lineTaxes = []; // ضرائب كلّ بندٍ: المعرّفات والنسب

            // ** الخطوة الثانية: معالجة البنود (items) **
            if ($request->has('items') && count($request->items)) {
                foreach ($request->items as $itemKey => $item) {
                    // التحقق من وجود product_id في البند
                    if (! isset($item['product_id'])) {
                        throw new \Exception('الرجاء اختيار المنتج');
                    }

                    // جلب المنتج من الدفعة المحمَّلة مسبقاً
                    $product = $productsCollection->get($item['product_id']);
                    if (! $product) {
                        throw new \Exception('المنتج غير موجود: '.$item['product_id']);
                    }

                    /* ** تحديد المستودع ** — المُرسَل مع البند، وإلا الافتراضي.
                     *
                     * والمستودع شرطٌ للأصناف المخزنية وحدها: الخدمي والرقمي
                     * لا يدخل مستودعاً أصلاً، فمنشأةُ خدماتٍ بلا مستودعٍ
                     * رئيسي تستطيع إصدار فاتورة شراء. */
                    $store_house_id = $item['store_house_id'] ?? null;
                    $storeHouse = ($store_house_id && $storeHousesCollection->has($store_house_id))
                        ? $storeHousesCollection->get($store_house_id)
                        : $defaultStoreHouse;

                    if (! $storeHouse && StockEligibility::isStockable($item['product_id'])) {
                        throw new \Exception('لا يوجد أي مستودع في النظام. الرجاء إضافة مستودع واحد على الأقل.');
                    }

                    $store_house_id = $storeHouse->id ?? null;

                    // حساب تفاصيل الكمية والأسعار
                    $quantity = floatval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $item_total = $quantity * $unit_price;

                    // ✅ معالجة بيانات الوحدة
                    $product_unit_id = $item['product_unit_id'] ?? null;
                    $conversion_factor = floatval($item['conversion_factor'] ?? 1);
                    $quantity_in_base_unit = floatval($item['quantity_in_base_unit'] ?? ($quantity * $conversion_factor));
                    $unit_name = $item['unit_name'] ?? null;

                    // ✅ معالجة product_unit_id إذا كان يحتوي على قيمة غير رقمية مثل "category_10"
                    if ($product_unit_id && ! is_numeric($product_unit_id)) {
                        if (str_starts_with((string) $product_unit_id, 'category_')) {
                            $categoryUnitId = (int) str_replace('category_', '', $product_unit_id);
                            $productUnit = ProductUnit::where('product_id', $item['product_id'])
                                ->where('category_unit_id', $categoryUnitId)
                                ->first();
                            if ($productUnit) {
                                $product_unit_id = $productUnit->id;
                            } else {
                                $categoryUnit = CategoryUnit::with('unit')->find($categoryUnitId);
                                if ($categoryUnit) {
                                    $unit_name = $categoryUnit->unit->name ?? $unit_name;
                                    $conversion_factor = $categoryUnit->conversion_factor ?? $conversion_factor;
                                    $quantity_in_base_unit = $quantity * $conversion_factor;
                                }
                                $product_unit_id = null;
                            }
                        } else {
                            $product_unit_id = null;
                        }
                    }

                    // جلب اسم الوحدة من ProductUnit إذا وجد ولم يتم تعيينه
                    if ($product_unit_id && is_numeric($product_unit_id) && ! $unit_name) {
                        $productUnit = $productUnitsCollection->get($product_unit_id)
                            ?: ProductUnit::with('categoryUnit.unit')->find($product_unit_id);
                        if ($productUnit && $productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                            $unit_name = $productUnit->categoryUnit->unit->name;
                            $conversion_factor = $productUnit->categoryUnit->conversion_factor ?? $conversion_factor;
                            $quantity_in_base_unit = $quantity * $conversion_factor;
                        }
                    }

                    // حساب الخصم للبند
                    $item_discount = 0; // قيمة الخصم المبدئية
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        if (isset($item['discount_type']) && $item['discount_type'] === 'percentage') {
                            $item_discount = ($item_total * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }

                    // ** ضريبة البند **
                    // النسبة **ونوعها** من «إعدادات الضريبة»: المتضمَّنة في السعر
                    // تُستخرَج منه ولا تُضاف فوقه، فلا يتضخّم إجمالي البند
                    $tax_1_id = $item['tax_1_id'] ?? null;
                    $tax_2_id = $item['tax_2_id'] ?? null;
                    $tax_1_rate = $tax_1_id ? PurchaseTaxCalculator::rate($tax_1_id) : floatval($item['tax_1'] ?? 0);
                    $tax_2_rate = $tax_2_id ? PurchaseTaxCalculator::rate($tax_2_id) : floatval($item['tax_2'] ?? 0);

                    $documentTaxIds[] = $item['tax_1_id'] ?? null;
                    $documentTaxIds[] = $item['tax_2_id'] ?? null;

                    $line = PurchaseTaxCalculator::line(
                        $quantity,
                        $unit_price,
                        $item_discount,
                        [$tax_1_id, $tax_2_id],
                        [$tax_1_rate, $tax_2_rate]
                    );

                    /* صافي البند وضرائبه — تُجمَّع وتُؤجَّل الضريبةُ إلى ما
                       بعد الحلقة: وعاؤها هو صافي البند ناقصاً حصّته من
                       الخصم العامّ، والخصمُ العامّ لا يُعرَف إلّا بعد
                       اكتمال البنود. انظر documentTotals(). */
                    $lineNets[$itemKey] = $line['base'];
                    $lineTaxes[$itemKey] = [
                        'ids' => [$tax_1_id, $tax_2_id],
                        'rates' => [$tax_1_rate, $tax_2_rate],
                    ];

                    // تحديث الإجماليات
                    $total_amount += $item_total;
                    $total_discount += $item_discount;

                    // تجهيز بيانات البند
                    $items_data[$itemKey] = [
                        'purchase_invoice_id' => null, // سيتم تعيينه لاحقًا بعد إنشاء الفاتورة
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount' => $item_discount, // تخزين الخصم للبند
                        'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                        'tax_1' => $tax_1_rate,
                        'tax_1_id' => $tax_1_id ?: null,
                        'tax_2' => $tax_2_rate,
                        'tax_2_id' => $tax_2_id ?: null,
                        /* صافي البند بلا ضريبة — لا `$line['total']`، الذي يضيف
                           ضريبةَ الأنواع «المضافة» (`type = excluded`) فوق
                           القيمة. `invoice_items.total` عمودٌ صافٍ في كل مكانٍ
                           يقرؤه (تقرير الأصناف، تكلفة الإذن المخزني، فاتورة
                           المبيعات المناظرة) — وكان هذا الموضع وحده يكتب فيه
                           القيمة شاملةً الضريبة كلّما حُدِّد معرّف ضريبةٍ للبند،
                           فيتضخّم إجمالي الصنف في التقرير بقدر الضريبة. */
                        'total' => $line['net'],
                        'store_house_id' => $store_house_id, // المستودع المشتقّ أعلاه لا المُرسَل خاماً
                        // ✅ إضافة حقول الوحدات
                        'product_unit_id' => $product_unit_id,
                        'quantity_in_base_unit' => $quantity_in_base_unit,
                        'conversion_factor' => $conversion_factor,
                        'unit_name' => $unit_name,
                    ];

                    // ✅ تحديث أسعار المنتجات إذا كان الإعداد مفعل
                    if ($updatePricesSetting) {
                        $product->update([
                            'purchase_price' => $unit_price,
                            'price' => $unit_price, // أو يمكن حساب سعر البيع بناءً على هامش ربح
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            /* ** الخطوة الثالثة: الخصم العامّ والضريبة والشحن **
               ترتيبُ مستندات البيع نفسُه، وفي موضعٍ واحد — انظر
               documentTotals() لما كان يفترق قبل ذلك. */
            $totals = PurchaseDocumentTotals::compute($request, $lineNets, $lineTaxes, $enableSettlementSetting, $documentTaxIds);

            $invoice_discount = $totals['invoice_discount'];
            $included_item_tax = $totals['included_item_tax'];
            $tax_rows = $totals['tax_rows'];
            $shipping_cost = $totals['shipping_cost'];
            $shipping_tax = $totals['shipping_tax'];

            // الأسعار أُدخلت شاملةً الضريبة المتضمَّنة، فلو بقيت داخل المجموع
            // لحُسبت مرّتين حين تُضاف `total_tax` إليه
            $total_amount = round($total_amount - $included_item_tax, 2);

            // ✅ إجمالي الخصومات = خصم البنود + خصم الفاتورة (دائماً يُخصم)
            $final_total_discount = $total_discount + $invoice_discount;

            // حساب المبلغ بعد الخصم
            $amount_after_discount = round($total_amount - $final_total_discount, 2);

            // ** الضرائب ** — ضريبةُ البنود على وعائها بعد الخصمَين، وضريبةُ
            // الشحن معها في `total_tax` المحفوظ
            $total_tax = $totals['item_tax'];

            // ** الحساب النهائي للمجموع الكلي **
            $total_with_tax = round($amount_after_discount + $total_tax + $shipping_cost + $shipping_tax, 2);

            // *** تحديد حالات الدفع والاستلام مع جميع الإعدادات ***
            $paid_amount = 0;
            $advance_payment = 0; // الدفعة المقدمة المنفصلة
            $deposit_fee = 0; // حق الإيداع
            $payment_status = 'unpaid'; // الحالة الافتراضية للدفع
            $receiving_status = 'not_received'; // الحالة الافتراضية للاستلام

            /* ** حالة الاستلام ** — تأشيرُ المستخدم، أو خيار «اعتبار فواتير
             * الشراء مستلمة بالكامل افتراضياً».
             *
             * كان الخيار يُقرأ في `$autoReceivedSetting` أعلاه ثم لا يُستعمَل
             * هنا إطلاقاً: يدخل في الملاحظات وفي `received_date` وفي رسالة
             * النجاح («استلام تلقائي») — ولا يمسّ `receiving_status`. فتُحفظ
             * الفاتورة «غير مستلمة»، ويبقى إذنها المخزني معلَّقاً بلا اعتماد،
             * ولا يتحرّك المخزون، بينما الشاشة تقول إن الاستلام التلقائي جرى.
             *
             * و`update()` كان يطبّقه (انظر سطر حالة الاستلام هناك)، فكان
             * الخيار يسري متى فُتحت الفاتورة وحُفظت من جديد لا عند إنشائها —
             * وهذا ما بلّغ عنه المستخدم. الشرط هنا نسخةٌ من نظيره هناك.
             */
            if ($autoReceivedSetting || ($request->has('is_received') && $request->is_received == '1')) {
                $receiving_status = 'received';
            }

            /* ** التسوية من رصيد المورد ** — «عنده فلوس لي».
             *
             * كان هنا فرعٌ ثالث معطَّل بالتعليق يقول
             * `$supplier->account->balance > 0` فيدفع الفاتورة نقداً تلقائياً.
             * وهو مقلوب: حساب المورد التزام، فرصيده الموجب يعني أننا **ندين
             * له** لا العكس — فكان الإعداد يصرف من الخزينة كلّما كثُر ما عليه
             * لنا، ولهذا عُطِّل. الرصيد السالب وحده هو المال الذي يحتفظ به
             * المورد لنا (سُلفة سُدِّدت قبل البضاعة).
             *
             * والتسوية ليست دفعاً: لا سند دفع، ولا قيد ثانٍ، ولا ريال يخرج من
             * الخزينة. قيدُ الفاتورة نفسه يجعل المورد دائناً بقيمتها فتُقاصّ
             * رصيدَه المدين تلقائياً — يكفي أن تُحسب الفاتورة مسدَّدة بمقدار
             * ما غطّاه رصيده.
             *
             * وتُحسب **قبل** الدفع التلقائي وتُخصم منه: لا معنى لصرف نقدٍ
             * جديد على مورد يحتفظ بمالنا أصلاً.
             */
            $credit_settled = 0.0;

            /* الرصيد يُقرأ من **محفظة المورد** لا من حساب ذممه.
             *
             * كان يُقرأ `supplier->account->balance` ويُشترط أن يكون سالباً —
             * أي حسابُ دائنٍ برصيدٍ مدين. وذلك موضعٌ لا يصف «مالاً لنا لدى
             * المورد»: هو دفعةٌ مقدَّمة، محلُّها المحفظة تحت المدينين.
             * وقراءةُ الذمّة تلتقط كذلك أرصدةً سالبةً نشأت لأسبابٍ أخرى
             * فتُسوّي بها فاتورةً بلا وجهٍ صحيح.
             */
            if ($autoPaymentSetting) {
                $walletBalance = SupplierWalletService::balance($supplier);

                if ($walletBalance > 0) {
                    $credit_settled = min($walletBalance, $total_with_tax);
                }
            }

            // ✅ حالة الدفع بناءً على الإعدادات المختلفة
            $shouldAutoPay = false;

            // 1. إعداد الدفع الكامل التلقائي
            if ($autoPaidSetting) {
                $shouldAutoPay = true;
            }
            // 2. دفع يدوي من المستخدم (فقط إذا تم تحديد is_paid = 1)
            elseif ($request->has('is_paid') && $request->is_paid == '1') {
                $shouldAutoPay = true;
            }
            // 3. رصيد المورد وحده يكفي: الفاتورة مسدَّدة بلا دفعٍ ولا خزينة
            elseif ($credit_settled > 0) {
                $payment_status = $credit_settled >= $total_with_tax ? 'paid' : 'partially_paid';
            }

            if ($shouldAutoPay) {
                // النقد يُصرف على ما لم يغطّه رصيد المورد فقط
                $paid_amount = max(0, $total_with_tax - $credit_settled);
                $advance_payment = $paid_amount; // نفس المبلغ للدفعة المقدمة
                $payment_status = 'paid'; // مدفوعة بالكامل
            }
            // أو إذا كان checkbox الدفعة الكاملة مفعلاً
            elseif (! $shouldAutoPay && $request->has('is_full_advance_payment') && $request->is_full_advance_payment) {
                $paid_amount = $total_with_tax; // دفع كامل
                $advance_payment = $total_with_tax; // نفس المبلغ للدفعة المقدمة
                $payment_status = 'paid'; // مدفوعة بالكامل
            }
            // أو إذا كان هناك دفعة مقدمة من حقل advance_payment (فقط إذا لم يكن الدفع التلقائي مفعل)
            elseif (! $shouldAutoPay && $request->has('advance_payment') && floatval($request->advance_payment) > 0) {
                $advance_payment = floatval($request->advance_payment);
                $paid_amount = $advance_payment;

                // التحقق من نوع الدفعة المقدمة (مبلغ أو نسبة مئوية)
                if ($request->has('advance_payment_type') && $request->advance_payment_type === 'percentage') {
                    $advance_payment = ($total_with_tax * $advance_payment) / 100;
                    $paid_amount = $advance_payment;
                }

                // ✅ حساب حق الإيداع إن وجد
                if ($request->has('deposit_fee') && floatval($request->deposit_fee) > 0) {
                    $deposit_fee = floatval($request->deposit_fee);

                    // التحقق من نوع حق الإيداع (مبلغ أو نسبة مئوية)
                    if ($request->has('deposit_fee_type') && $request->deposit_fee_type === 'percentage') {
                        $deposit_fee = ($advance_payment * $deposit_fee) / 100;
                    }

                    // إضافة حق الإيداع إلى المبلغ المدفوع
                    $paid_amount += $deposit_fee;
                }

                // التأكد من أن المبلغ المدفوع لا يتجاوز المبلغ الإجمالي + رسوم الإيداع
                if ($paid_amount >= ($total_with_tax + $deposit_fee)) {
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid'; // مدفوعة بالكامل
                } else {
                    $payment_status = 'partially_paid'; // مدفوعة جزئياً
                }
            }
            // أو إذا كان هناك مبلغ مدفوع من الحقل العام paid_amount (فقط إذا لم يكن الدفع التلقائي مفعل)
            elseif (! $shouldAutoPay && $request->has('paid_amount') && floatval($request->paid_amount) > 0) {
                $paid_amount = floatval($request->paid_amount);
                $advance_payment = $paid_amount;

                // التأكد من أن المبلغ المدفوع لا يتجاوز المبلغ الإجمالي
                if ($paid_amount >= $total_with_tax) {
                    $paid_amount = $total_with_tax;
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid'; // مدفوعة بالكامل
                } else {
                    $payment_status = 'partially_paid'; // مدفوعة جزئياً
                }
            }

            /* دفعةٌ صريحة من المُدخِل تُلغي التسوية من الرصيد: هو يقرّر أين
               يذهب ماله، فلا يُخصم من رصيدٍ لم يطلب المساس به */
            if ($paid_amount > 0 && ! $shouldAutoPay) {
                $credit_settled = 0.0;
            }

            /*
             * «حالة يدوية لفاتورة الشراء» — الحالة تبقى كما يضعها المُدخِل.
             *
             * الحالة أعلاه مشتقّة من المبالغ: من دفع كل شيء «مدفوعة»، ومن دفع
             * بعضه «مدفوعة جزئياً». وهذا الإعداد يوقف ذلك الاشتقاق ويجعل
             * القيمة المرسَلة من الشاشة هي المعتمدة — وهو غرضه المكتوب في
             * شاشة الخيارات نصّاً: «النظام لا يشتقّ حالة الفاتورة من الدفع
             * والاستلام؛ تبقى كما يضبطها المُدخِل».
             *
             * والمبالغ نفسها (`paid_amount` و`due_value`) تبقى محسوبة كما هي:
             * الإعداد يحرّر التسمية لا الأرقام، فلا يُفسِد القيد المحاسبي ولا
             * سندات الدفع. ولهذا يقتصر على `payment_status` دون حالة الاستلام:
             * تلك مربوطة بخانة «تمّ الاستلام» التي يتبعها إذن الإدخال المخزني،
             * فتغييرها وحدها يجعل الحالة تقول «مستلمة» بلا إذن إدخال.
             */
            if ($manualInvoiceStatusSetting && $request->filled('payment_status')
                && in_array($request->payment_status, ['unpaid', 'partially_paid', 'paid'], true)) {
                $payment_status = $request->payment_status;
            }

            /* المستحقُّ هنا **بلا** خصم المحفظة.

               كان يُطرح منه `$credit_settled` هنا، ثمّ يُنادى
               `SupplierWalletService::payInvoice()` بعد الحفظ فيطرحه ثانيةً
               (انظر `applyToInvoice` في الخدمة) — فتخرج فاتورةٌ مستحقُّها
               أقلّ من الحقيقة بمقدار التسوية مرّتين.

               والخدمة هي الكاتب الوحيد لأثر المحفظة على الفاتورة: المستحقّ
               والحالة و`wallet_paid_amount` كلُّها من موضع واحد، فلا يقع
               الخصم مرّتين ولا يسقط حين تفشل الحركة. */
            $due_value = $total_with_tax - ($paid_amount - $deposit_fee);

            // ✅ تحديد الخزينة المستهدفة بناءً على المستخدم والتحديد من الفورم
            $mainTreasuryAccount = null;
            $treasury_id = null;
            $advanceTreasuryAccount = null; // ✅ خزينة منفصلة للدفعة المقدمة
            $advance_treasury_id = null;

            // أولاً: التحقق من اختيار المستخدم في الفورم للدفع الكامل
            if ($request->has('treasury_id') && ! empty($request->treasury_id)) {
                // إذا اختار المستخدم خزينة من الفورم، استخدمها
                $treasury_id = $request->treasury_id;
                $mainTreasuryAccount = Account::where('id', $treasury_id)->first();
            } else {
                // إذا لم يختر، استخدم الخزينة المعينة له
                if ($user && $user->employee_id) {
                    // البحث عن الخزينة المرتبطة بالموظف
                    $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

                    if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                        $mainTreasuryAccount = Account::where('id', $treasuryEmployee->treasury_id)->first();
                        $treasury_id = $treasuryEmployee->treasury_id;
                    } else {
                        $mainTreasuryAccount = AccountingSettings::account('main_treasury');
                        $treasury_id = $mainTreasuryAccount->id ?? null;
                    }
                } else {
                    $mainTreasuryAccount = AccountingSettings::account('main_treasury');
                    $treasury_id = $mainTreasuryAccount->id ?? null;
                }
            }

            // ✅ ثانياً: معالجة منفصلة لخزينة الدفعة المقدمة
            if ($advance_payment > 0) {
                if ($request->has('advance_payment_treasury_id') && ! empty($request->advance_payment_treasury_id)) {
                    // إذا اختار المستخدم خزينة للدفعة المقدمة، استخدمها مباشرة
                    $advance_treasury_id = $request->advance_payment_treasury_id;
                    $advanceTreasuryAccount = Account::find($advance_treasury_id);

                    if (! $advanceTreasuryAccount) {
                        throw new \Exception('الخزينة المختارة للدفعة المقدمة غير موجودة.');
                    }
                } else {
                    // إذا لم يختر خزينة للدفعة المقدمة، استخدم الخزينة الرئيسية
                    $advanceTreasuryAccount = $mainTreasuryAccount;
                    $advance_treasury_id = $treasury_id;
                }
            }

            // إذا لم يتم العثور على خزينة، توقف العملية وأظهر خطأ
            if (! $mainTreasuryAccount) {
                throw new \Exception('لا توجد خزينة متاحة. يرجى التحقق من إعدادات الخزينة.');
            }

            // ✅ التحقق من وجود رصيد كافي في الخزينة المختارة
            // إذا كانت هناك دفعة مقدمة فقط (بدون دفع كامل)
            if ($advance_payment > 0 && $advanceTreasuryAccount) {
                $advanceAmountWithFee = $advance_payment + $deposit_fee;
                // تحقق من أن الرصيد يغطي الدفعة المقدمة + حق الإيداع
                if ($advanceTreasuryAccount->balance < $advanceAmountWithFee) {
                    $treasuryName = $advanceTreasuryAccount->name ?? 'غير محددة';
                    throw new \Exception('رصيد خزينة الدفعة المقدمة ('.$treasuryName.') غير كافي. الرصيد الحالي: '.number_format($advanceTreasuryAccount->balance, 2).' والمطلوب: '.number_format($advanceAmountWithFee, 2));
                }
            }

            // إذا كانت هناك دفعة كاملة (بدون دفعة مقدمة أو بعدها)
            if ($payment_status === 'paid' && $advance_payment <= 0 && $mainTreasuryAccount && $mainTreasuryAccount->balance < $total_with_tax) {
                throw new \Exception('رصيد الخزينة غير كافي. الرصيد الحالي: '.number_format($mainTreasuryAccount->balance, 2).' والمطلوب: '.number_format($total_with_tax, 2));
            }

            // ✅ التحقق من رصيد الخزينة عند الدفع الكامل من خزينة محددة
            if ($request->has('is_paid') && $request->is_paid == '1' && $mainTreasuryAccount && $mainTreasuryAccount->balance < $total_with_tax) {
                throw new \Exception('رصيد الخزينة ('.$mainTreasuryAccount->name.') غير كافي. الرصيد الحالي: '.number_format($mainTreasuryAccount->balance, 2).' والمطلوب: '.number_format($total_with_tax, 2));
            }

            // لا حاجة لحساب حالة عامة للفاتورة هنا: `PurchaseInvoice::create()`
            // أدناه لا يكتب عمود `status` إطلاقاً (يبقى على افتراضه في
            // الجدول)، وما يُعرض فعلياً للفاتورة هو `payment_status`/
            // `receiving_status` وحدهما — انظر تعليق update() لتفصيل لماذا
            // كانت محاولة الكتابة هناك خطأً (عمود ENUM بقيم لا تحتملها)

            // ** الخطوة الرابعة: إنشاء الفاتورة في قاعدة البيانات **
            $purchaseInvoice = PurchaseInvoice::create([
                'supplier_id' => $request->supplier_id,
                'code' => $code,
                'type' => 'invoice', // نوع الفاتورة: مشتريات
                'date' => $request->date,
                'terms' => $request->terms ?? 0,
                'notes' => ($request->notes ?? '').
                          "\nالخزينة المستخدمة: ".$mainTreasuryAccount->name.
                          ($autoPaidSetting ? "\n(دفع تلقائي بالكامل مفعل)" : '').
                          ($autoReceivedSetting ? "\n(استلام تلقائي مفعل)" : '').
                          ($autoPaymentSetting ? "\n(دفع تلقائي حسب رصيد المورد مفعل)" : '').
                          ($credit_settled > 0 ? "\nسُوِّيت من رصيد المورد بمبلغ ".number_format($credit_settled, 2).' — بلا صرفٍ من الخزينة' : '').
                          ($updatePricesSetting ? "\n(تحديث أسعار المنتجات مفعل)" : ''),
                'payment_status' => $payment_status, // ✅ حالة الدفع الجديدة (enum)
                'receiving_status' => $receiving_status, // ✅ حالة الاستلام

                'created_by' => Auth::id(),
                'branch_id' => $branchId,
                // حقل الحساب أُزيل من الشاشة: يُشتقّ من الخزينة المستعملة
                // فعلاً، وهو ما يقرأه سند الدفع والمرتجع بعد ذلك
                'account_id' => $request->account_id ?: ($mainTreasuryAccount->id ?? null),
                'discount_amount' => $invoice_discount, // تخزين الخصم الإضافي للفاتورة
                'discount_type' => $request->has('discount_type') ? ($request->discount_type === 'percentage' ? 2 : 1) : 1,
                'payment_type' => $request->payment_type ?? 1,
                'shipping_cost' => $shipping_cost,
                'tax_type' => $request->tax_type ?? 1,
                'payment_method' => $request->payment_method ?? $request->advance_payment_method,
                'reference_number' => $request->reference_number ?? $request->advance_reference_number,
                'received_date' => $autoReceivedSetting ? now() : $request->received_date,
                'is_paid' => $payment_status === 'paid', // للتوافق مع النظام القديم
                'is_received' => $receiving_status === 'received', // للتوافق مع النظام القديم
                'subtotal' => $total_amount,
                'total_discount' => $final_total_discount, // تخزين الخصم الإجمالي
                'total_tax' => $total_tax + $shipping_tax, // إضافة ضريبة التوصيل إلى مجموع الضرائب
                /* وحصّةُ الشحن محفوظةٌ وحدها كذلك: `total_tax` يبقى المجموع
                   (فلا تتبدّل المطبوعات ولا التقارير)، وهذا العمود يحفظ ما
                   يُطرَح منه ليُرحَّل إلى «ضريبة شحن مشتريات». وبلا حفظه
                   يضيع الفصلُ عند الحفظ فلا يُستردّ عند بناء القيد. */
                'shipping_tax' => $shipping_tax,
                'grand_total' => $total_with_tax, // تخزين المبلغ الإجمالي الكامل
                'due_value' => $due_value, // القيمة المستحقة (بعد خصم الدفعة المقدمة)
                'advance_payment' => $advance_payment, // ✅ استخدام المتغير المنفصل للدفعة المقدمة
                'deposit_fee' => $deposit_fee, // ✅ حق الإيداع
            ]);

            /* ** إنشاء الإذن المخزني ** — للأصناف ذات الرصيد وحدها.
             *
             * كان الإذن يُفتح لكل فاتورة بلا استثناء، ثم يُكتب له بندٌ لكل
             * صنف. فإن كان في الفاتورة صنفٌ خدمي أو رقمي رمى
             * `StockMovementGuardObserver` وسقطت الفاتورة كلّها برسالة
             * «لا يمكن إدخال … في المخزون». والخدمة لا تُشترى إلى مستودع
             * أصلاً: قيمتها تدخل الدفتر ولا تدخل الرصيد.
             *
             * فإن كانت الفاتورة خدماتٍ كلّها فلا إذن لها — وبذلك تستطيع
             * منشأةُ خدماتٍ بلا مستودعٍ رئيسي أن تُصدر فاتورة شراء. */
            $stockableItems = array_values(array_filter(
                $items_data,
                fn ($item) => ! empty($item['product_id']) && StockEligibility::isStockable($item['product_id'])
            ));

            $warehousePermit = $stockableItems
                ? $this->createWarehousePermit($purchaseInvoice, $request, $defaultStoreHouse->id ?? null)
                : null;

            // ** معالجة الضرائب ** — من الحساب نفسه الذي بُنيت عليه الإجماليات،
            // فلا يختلف ما يُعرض في تفاصيل الضريبة عمّا دخل المجموع
            foreach ($tax_rows as $detail) {
                $tax = PurchaseTaxCalculator::find($detail['id']);

                if (! $tax) {
                    continue;
                }

                TaxInvoice::create([
                    'name' => $tax->name,
                    'invoice_id' => $purchaseInvoice->id,
                    'type' => $tax->type,
                    'rate' => $detail['rate'],
                    'value' => $detail['amount'],
                    'type_invoice' => 'purchase',
                ]);
            }

            // ** الخطوة الخامسة: إنشاء سجلات البنود (items) للفاتورة والإذن المخزني **
            foreach ($items_data as $itemKey => $item) {
                $item['purchase_invoice_id'] = $purchaseInvoice->id; // تعيين purchase_invoice_id
                $invoiceItem = InvoiceItem::create($item); // تخزين البند مع purchase_invoice_id

                // إنشاء بند في الإذن المخزني — للصنف ذي الرصيد وحده
                // سعر الوحدة هنا سعر الوحدة الفوترة (كرتون) لا الوحدة الأساسية
                // المخزَّنة في `quantity` — تحويله عبر الدالة المُعدَّة لذلك، وإلا
                // ضُرب سعر الكرتون كاملاً في عدد القطع فتضخّمت قيمة المخزون
                if ($warehousePermit && StockEligibility::isStockable($item['product_id'])) {
                    WarehousePermitsProducts::create(array_merge([
                        'warehouse_permits_id' => $warehousePermit->id,
                        'product_id' => $item['product_id'],
                        'notes' => $item['description'],
                        // المستودع لم يعد يُختار لكل بند: يرث مستودع الإذن،
                        // وهو المستودع الرئيسي ما لم يُحدَّد غيره
                        'store_house_id' => $item['store_house_id'] ?? $warehousePermit->store_houses_id,
                    ], WarehousePermitsProducts::inventoryLineFromInvoiceItem(
                        // التكلفةُ الصافية لا `total` البند — انظر documentTotals()
                        array_merge($item, ['total' => $totals['line_costs'][$itemKey] ?? $item['total']])
                    )));
                }

                // من الدفعة المحمَّلة أعلاه — لا استعلام ثانٍ لكل بند
                $product = $productsCollection->get($item['product_id']);

                // تسجيل اشعار نظام جديد لكل منتج
                OperationLog::record(
                    'created',
                    self::LOG_MODULE,
                    $purchaseInvoice->id,
                    sprintf(
                        'تم انشاء فاتورة شراء رقم **%s** للمنتج **%s** كمية **%s** بسعر **%s** للمورد **%s** وإذن مخزني رقم **%s** من خزينة **%s** - حالة الدفع: **%s** - حالة الاستلام: **%s**%s%s%s',
                        $purchaseInvoice->code ?? '',
                        $product->name ?? '',
                        $item['quantity'] ?? '',
                        $item['unit_price'] ?? '',
                        $supplier->trade_name ?? '',
                        $warehousePermit?->number ?? '',
                        $mainTreasuryAccount->name ?? '',
                        $this->getPaymentStatusText($payment_status),
                        $this->getReceivingStatusText($receiving_status),
                        $autoPaidSetting ? ' (دفع تلقائي)' : '',
                        $autoReceivedSetting ? ' (استلام تلقائي)' : '',
                        $updatePricesSetting ? ' (تحديث أسعار)' : ''
                    ),
                );
            }

            // ** معالجة المرفقات (attachments) إذا وجدت **
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $purchaseInvoice->attachments = $filename;
                    $purchaseInvoice->save();
                }
            }

            // *** إنشاء عملية الدفع إذا تم دفع مبلغ ***
            if ($paid_amount > 0) {
                $paymentNotes = '';
                if ($autoPaidSetting) {
                    $paymentNotes = 'دفعة كاملة تلقائية (إعداد الدفع التلقائي مفعل)';
                } elseif ($autoPaymentSetting) {
                    $paymentNotes = 'دفعة كاملة تلقائية (رصيد المورد صالح)';
                } else {
                    $paymentNotes = $payment_status === 'paid' ? 'دفعة كاملة' : 'دفعة مقدمة بمبلغ '.number_format($paid_amount, 2);
                }

                // ✅ تحديد الخزينة والمبلغ حسب نوع الدفعة
                // إذا كانت هناك دفعة مقدمة، استخدم خزينة الدفعة المقدمة
                if ($advance_payment > 0 && $advance_treasury_id) {
                    $payment_treasury_id = $advance_treasury_id; // استخدم خزينة الدفعة المقدمة
                    $payment_amount = $advance_payment + $deposit_fee; // شامل حق الإيداع
                    $paymentNotes = 'دفعة مقدمة بمبلغ '.number_format($advance_payment, 2).
                                   ($deposit_fee > 0 ? ' + حق إيداع '.number_format($deposit_fee, 2) : '');
                } else {
                    // إذا لم تكن هناك دفعة مقدمة، استخدم خزينة الدفع الكامل
                    $payment_treasury_id = $treasury_id;
                    $payment_amount = $paid_amount;
                }

                $payment = PaymentsProcess::create([
                    'purchases_id' => $purchaseInvoice->id,
                    'supplier_id' => $request->supplier_id,
                    'amount' => $payment_amount,
                    'payment_date' => $request->date ?? now(),
                    'payment_method' => $request->payment_method ?? $request->advance_payment_method ?? 1,
                    'type' => 'supplier payments',
                    'payment_status' => 1, // مكتمل
                    'employee_id' => Auth::id(),
                    'treasury_id' => $payment_treasury_id, // ✅ الخزينة المستخدمة (قد تكون مختلفة للدفعة المقدمة)
                    'notes' => $paymentNotes.' لفاتورة المشتريات رقم '.$purchaseInvoice->code.
                              ' من خزينة '.
                              ($advance_payment > 0 && $advanceTreasuryAccount ? $advanceTreasuryAccount->name : $mainTreasuryAccount->name),
                ]);

                // ✅ إذا كانت هناك دفعة كاملة إضافية بعد الدفعة المقدمة من خزينة مختلفة
                if ($payment_status === 'paid' && $advance_payment > 0 && $advance_treasury_id && $advance_treasury_id != $treasury_id) {
                    // إنشاء سجل دفع منفصل للدفع الكامل من الخزينة الأساسية
                    $remaining_amount = $total_with_tax - $advance_payment;
                    if ($remaining_amount > 0) {
                        PaymentsProcess::create([
                            'purchases_id' => $purchaseInvoice->id,
                            'supplier_id' => $request->supplier_id,
                            'amount' => $remaining_amount,
                            'payment_date' => $request->date ?? now(),
                            'payment_method' => $request->payment_method ?? 1,
                            'type' => 'supplier payments',
                            'payment_status' => 1,
                            'employee_id' => Auth::id(),
                            'treasury_id' => $treasury_id,
                            'notes' => 'دفع المتبقي '.number_format($remaining_amount, 2).
                                     ' لفاتورة المشتريات رقم '.$purchaseInvoice->code.
                                     ' من خزينة '.$mainTreasuryAccount->name,
                        ]);
                    }
                }
            }

            // ** الخطوة السادسة: القيود المحاسبية وتحديث المخزون **

            // 1. إنشاء قيد فاتورة المشتريات (دائماً عند الحفظ - لإثبات المديونية)
            // ✅ تمرير خزينة الدفعة المقدمة إذا كانت موجودة ومختلفة عن الخزينة الرئيسية
            $treasuryForPayment = ($advance_payment > 0 && $advanceTreasuryAccount) ? $advanceTreasuryAccount : $mainTreasuryAccount;
            $this->createAccountingEntriesFixed($purchaseInvoice, $total_with_tax, $total_tax + $shipping_tax, $paid_amount, $treasuryForPayment);

            // 2. تحديث المخزون وقيد الاستلام (فقط في حالة الاستلام)
            if ($receiving_status === 'received') {
                $this->approveWarehousePermit($purchaseInvoice);
            }

            /* 3. التسوية من محفظة المورد — **داخل** المعاملة.

               كانت تقع بعد `DB::commit()`، فأيّ خطأ فيها (الحساب الأب غير
               مربوط، أو رصيدٌ سحبه طلبٌ آخر في الأثناء) يترك فاتورةً
               محفوظةً بلا تسوية ورسالةَ فشلٍ للمستخدم — و`rollback` في
               كتلة الالتقاط لا يجد ما يتراجع عنه.

               وموضعُها بعد إنشاء الفاتورة لا قبله: الحركة تُربط بمستندٍ له
               معرّف، وقيدُها (من ح/ المورد إلى ح/ محفظة المورد) يُكتب
               عليه. والخدمة وحدها تُحدِّث `wallet_paid_amount` والمستحقّ
               والحالة — انظر `SupplierWalletService::payInvoice()`. */
            if ($credit_settled > 0) {
                SupplierWalletService::payInvoice(
                    $purchaseInvoice,
                    $credit_settled,
                    'سداد تلقائي من محفظة المورد عند إنشاء الفاتورة'
                );

                $purchaseInvoice->refresh();
                $due_value = (float) $purchaseInvoice->due_value;
                $payment_status = $purchaseInvoice->payment_status;
            }

            DB::commit(); // تأكيد التغييرات

            // ✅ إنشاء إشعار للنظام
            $activeSettings = [];
            if ($autoPaidSetting) {
                $activeSettings[] = 'دفع تلقائي';
            }
            if ($autoReceivedSetting) {
                $activeSettings[] = 'استلام تلقائي';
            }
            if ($autoPaymentSetting) {
                $activeSettings[] = 'دفع حسب رصيد المورد';
            }
            if ($updatePricesSetting) {
                $activeSettings[] = 'تحديث أسعار';
            }

            notifications::create([
                'type' => 'purchase_creation',
                'title' => $user->name.' أنشأ فاتورة شراء جديدة',
                'description' => 'تم إنشاء فاتورة الشراء رقم '.$purchaseInvoice->code.' للمورد '.($supplier->trade_name ?? '').' بقيمة '.number_format($purchaseInvoice->grand_total, 2).' ر.س من خزينة '.$mainTreasuryAccount->name.($paid_amount > 0 ? ' - تم سحب '.number_format($paid_amount, 2).' ر.س' : '').' - حالة الدفع: '.$this->getPaymentStatusText($payment_status).' - حالة الاستلام: '.$this->getReceivingStatusText($receiving_status).(! empty($activeSettings) ? ' (الإعدادات المفعلة: '.implode(', ', $activeSettings).')' : ''),
            ]);

            // *** تحسين رسائل النجاح ***
            $message = $this->generateEnhancedSuccessMessage($payment_status, $receiving_status, $warehousePermit?->number, $paid_amount, $due_value, $mainTreasuryAccount->name, [
                'auto_paid' => $autoPaidSetting,
                'auto_received' => $autoReceivedSetting,
                'auto_payment' => $autoPaymentSetting,
                'update_prices' => $updatePricesSetting,
            ]);

            // التسوية نفسُها وقعت قبل `DB::commit()` أعلاه — وهذا ذكرُها للمستخدم
            if ($credit_settled > 0) {
                $message .= ' | سُوِّيت من محفظة المورد بمبلغ '.number_format($credit_settled, 2).' ر.س بلا صرفٍ من الخزينة';
            }

            return redirect()
                ->route('invoicePurchases.show', $purchaseInvoice->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback(); // تراجع عن التغييرات في حالة حدوث خطأ

            // يُكتب في «سجلّ الأخطاء» و«سجلّ الأحداث» معاً بدل سطر في ملفّ لارافيل
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()
                ->back()
                ->withInput()
                ->with('error', __('purchase_cycle.messages.create_failed', [
                    'document' => __('purchase_cycle.documents.purchase_invoice'),
                    'error' => $e->getMessage(),
                ]));
        }
    }

    /**
     * دالة مساعدة لتحويل حالة الاستلام إلى نص عربي
     */
    private function getReceivingStatusText($status)
    {
        switch ($status) {
            case 'received':
                return 'مستلمة';
            case 'pending':
                return 'في الانتظار';
            case 'partial':
                return 'مستلمة جزئياً';
            default:
                return 'غير محددة';
        }
    }

    /**
     * دالة مساعدة محدثة لتوليد رسالة النجاح بناءً على جميع الإعدادات
     */
    private function generateEnhancedSuccessMessage($payment_status, $receiving_status, $warehouse_number, $paid_amount, $due_value, $treasury_name, $settings = [])
    {
        $payment_text = $this->getPaymentStatusText($payment_status);
        $receiving_text = $this->getReceivingStatusText($receiving_status);

        $message = 'تم إنشاء فاتورة المشتريات  ';

        // إضافة معلومات الإعدادات المفعلة
        $activeSettings = [];
        if ($settings['auto_paid']) {
            $activeSettings[] = 'دفع تلقائي';
        }
        if ($settings['auto_received']) {
            $activeSettings[] = 'استلام تلقائي';
        }
        if ($settings['auto_payment']) {
            $activeSettings[] = 'دفع حسب رصيد المورد';
        }
        if ($settings['update_prices']) {
            $activeSettings[] = 'تحديث أسعار';
        }

        if (! empty($activeSettings)) {
            $message .= ' ('.implode(', ', $activeSettings).')';
        }

        switch ($payment_status) {
            case 'paid':
                $message .= ' - تم تسجيل الدفع الكامل';
                if ($settings['auto_paid'] || $settings['auto_payment']) {
                    $message .= ' تلقائياً';
                }
                break;
            case 'partially_paid':
                $message .= ' - تم تسجيل دفعة مقدمة بمبلغ '.number_format($paid_amount).' ريال. المتبقي: '.number_format($due_value).' ريال';
                break;
            case 'unpaid':
                $message .= ' - بدون دفعة مقدمة';
                break;
        }

        // إضافة معلومات الاستلام
        switch ($receiving_status) {
            case 'received':
                $message .= ' - تم تسجيل الاستلام';
                if ($settings['auto_received']) {
                    $message .= ' تلقائياً';
                }
                break;
            case 'pending':
                $message .= ' - في انتظار الاستلام';
                break;
            case 'partial':
                $message .= ' - استلام جزئي';
                break;
        }

        $message .= ' | حالة الدفع: '.$payment_text.' | حالة الاستلام: '.$receiving_text;

        return $message;
    }

    /**
     * دالة مساعدة لتحويل حالة الدفع إلى نص عربي
     */
    private function getPaymentStatusText($status)
    {
        switch ($status) {
            case 'paid':
                return 'مدفوعة بالكامل';
            case 'partially_paid':
                return 'مدفوعة جزئياً';
            case 'unpaid':
                return 'غير مدفوعة';
            default:
                return 'غير محددة';
        }
    }

    // private function generateSuccessMessage($payment_status,  $warehouse_number, $paid_amount, $due_value, $treasury_name)
    // {
    //     $payment_text = $this->getPaymentStatusText($payment_status);

    //     $message = 'تم إنشاء فاتورة المشتريات والإذن المخزني رقم ' . $warehouse_number . ' بنجاح من خزينة ' . $treasury_name;

    //     switch ($payment_status) {
    //         case 'paid':
    //             $message .= ' - تم تسجيل الدفع الكامل';
    //             break;
    //         case 'partially_paid':
    //             $message .= ' - تم تسجيل دفعة مقدمة بمبلغ ' . number_format($paid_amount) . ' ريال. المتبقي: ' . number_format($due_value) . ' ريال';
    //             break;
    //         case 'unpaid':
    //             $message .= ' - بدون دفعة مقدمة';
    //             break;
    //     }

    //     $message .= ' | حالة الدفع: ' . $payment_text  ;

    //     return $message;
    // }

    /**
     * @param  int|null  $resolvedStoreHouseId  المستودع المشتقّ في `store()`
     *                                          (المُرسَل ← افتراضي الموظّف ← الرئيسي)
     */
    private function createWarehousePermit($purchaseInvoice, $request, $resolvedStoreHouseId = null)
    {
        // إنشاء رقم الإذن المخزني
        $lastPermit = WarehousePermits::orderBy('id', 'desc')->first();
        $nextPermitNumber = $lastPermit ? intval($lastPermit->number) + 1 : 1;
        $permitNumber = str_pad($nextPermitNumber, 6, '0', STR_PAD_LEFT);

        /* المستودع: ما اشتقّه `store()` أوّلاً كي لا يختلف مستودع الإذن عن
           مستودع البنود، ثمّ المُرسَل، ثمّ الرئيسي */
        $storeHouseId = $resolvedStoreHouseId ?: ($request->store_house_id ?? null);

        if (! $storeHouseId) {
            $mainStoreHouse = StoreHouse::where('major', 1)->first();

            if (! $mainStoreHouse) {
                throw new \Exception('لا يوجد مستودع رئيسي محدد في النظام، يرجى إنشاء مستودع رئيسي أولاً.');
            }

            $storeHouseId = $mainStoreHouse->id;
        }

        // إنشاء الإذن المخزني
        $warehousePermit = WarehousePermits::create([
            'permission_source_id' => PermissionSources::PURCHASE_INVOICE, // نوع الإذن: فاتورة شراء

            'permission_date' => $request->date ?? now(),
            'sub_account' => $purchaseInvoice->supplier_id, // المورد
            'number' => $permitNumber,
            'store_houses_id' => $storeHouseId, // المستودع المستهدف إجباري
            'from_store_houses_id' => null, // لا يوجد مستودع مصدر للمشتريات
            'to_store_houses_id' => $storeHouseId, // المستودع المستهدف
            'grand_total' => $purchaseInvoice->grand_total,
            'details' => 'إذن إدخال بضاعة لفاتورة شراء رقم: '.$purchaseInvoice->code,
            'attachments' => null,
            'created_by' => Auth::id(),
            'status' => 'pending', // بانتظار الموافقة
            'reference_type' => 'purchase_invoice', // نوع المرجع
            'reference_id' => $purchaseInvoice->id, // معرف الفاتورة
        ]);

        return $warehousePermit;
    }

    // ✅ دالة مُصححة للقيود المحاسبية (نفس منطق التحويل)
    // ✅ دالة مُصححة للقيود المحاسبية - قيد للفاتورة + قيد للدفع (عند الدفع)
    // ✅ ملاحظة: $paymentTreasuryAccount هي الخزينة المستخدمة للدفع (قد تكون خزينة الدفعة المقدمة أو الخزينة الرئيسية)
    private function createAccountingEntriesFixed($purchaseInvoice, $total_with_tax, $tax_total, $paid_amount, $paymentTreasuryAccount)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // 1️⃣ حساب المورد
        $supplier = Supplier::find($purchaseInvoice->supplier_id);
        $supplierName = $supplier->trade_name ?? $supplier->name ?? 'مورد غير معروف';

        $supplierAccount = Account::ledger()->where('supplier_id', $purchaseInvoice->supplier_id)->first();
        if (! $supplierAccount) {
            $supplierAccount = Account::create([
                'name' => 'حساب المورد - '.$supplierName,
                'supplier_id' => $purchaseInvoice->supplier_id,
                'account_type' => 'supplier',
                'balance' => 0,
                'status' => 1,
            ]);
        }

        // 2️⃣ حساب المشتريات باستخدام الثابت
        $mainStore = $this->settingsAccount('purchases');
        if (! $mainStore) {
            throw new \Exception('حساب المشتريات برقم 25 غير موجود في النظام!');
        }

        // 3️⃣ حساب القيمة المضافة المحصلة باستخدام الثابت (رقم 37)
        $taxAccount = null;
        if ($purchaseInvoice->total_tax > 0) {
            $taxAccount = $this->settingsAccount('vat_paid');
            if (! $taxAccount) {
                throw new \Exception('حساب القيمة المضافة المحصلة برقم 37 غير موجود في النظام!');
            }
        }

        // 3.1️⃣ حساب الخصم على المشتريات
        $discountAccount = null;
        if ($purchaseInvoice->total_discount > 0) {
            $discountAccount = $this->settingsAccount('purchase_discount');
            if (! $discountAccount) {
                throw new \Exception('حساب خصم المشتريات غير موجود في النظام!');
            }
        }

        // 3.2️⃣ حساب تكاليف الشحن على المشتريات
        $shippingAccount = null;
        if ($purchaseInvoice->shipping_cost > 0) {
            $shippingAccount = $this->settingsAccount('purchase_shipping');
            if (! $shippingAccount) {
                throw new \Exception('حساب تكاليف الشحن على المشتريات غير موجود في النظام!');
            }
        }

        // حساب الإجمالي بدون ضريبة وبدون خصم وشحن
        $baseSubtotal = $purchaseInvoice->grand_total - $purchaseInvoice->total_tax - ($purchaseInvoice->shipping_cost ?? 0) + ($purchaseInvoice->total_discount ?? 0);
        $subtotal = $baseSubtotal;

        // «نوع المستند رقمه - الطرف»؛ المبالغ في عمودَي مدين/دائن والتاريخ في `date`
        $mainEntryDescription = 'فاتورة شراء '.$purchaseInvoice->code.' - '.$supplierName;

        // 4️⃣ إنشاء القيد الأول: قيد الفاتورة
        $journalEntry1 = JournalEntry::create([
            'reference_number' => $purchaseInvoice->code,
            'purchase_invoice_id' => $purchaseInvoice->id,
            'date' => now(),
            'description' => $mainEntryDescription,
            'status' => 1,
            'currency' => $this->documentCurrency(),
            'client_id' => $purchaseInvoice->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PURCHASE_INVOICE,
            'linking_type' => LinkingType::SINGLE,
        ]);

        // سطور القيد الخمسة — كاتبٌ واحد يشاركه التعديل
        $this->applyEntryDeltas(
            $this->writeInvoiceEntryDetails($journalEntry1->id, $purchaseInvoice, $supplierAccount)
        );

        // 5️⃣ القيد الثاني: قيد الدفع إذا كان هناك دفع
        if ($paid_amount > 0) {
            $paymentEntryDescription = 'سداد للمورد - فاتورة شراء '.$purchaseInvoice->code.' - '.$supplierName;

            $journalEntry2 = JournalEntry::create([
                'reference_number' => $purchaseInvoice->code.'_سداد',
                'date' => now(),
                'description' => $paymentEntryDescription,
                'status' => 1,
                'currency' => $this->documentCurrency(),
                'client_id' => $purchaseInvoice->supplier_id,
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
                'linking_type' => LinkingType::SINGLE,
            ]);

            // 5.1 حساب المورد/المؤسسة (مدين) - تسديد الدين
            $supplierDebitDesc = 'تخفيض ذمم الموردين - فاتورة شراء '.$purchaseInvoice->code;

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $supplierAccount->id,
                'description' => $supplierDebitDesc,
                'debit' => $paid_amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // 5.2 حساب الخزينة المستخدمة للدفع (دائن) - خروج نقد
            $treasuryCreditDesc = 'صرف نقدي للمورد - فاتورة شراء '.$purchaseInvoice->code;

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $paymentTreasuryAccount->id,
                'description' => $treasuryCreditDesc,
                'debit' => 0,
                'credit' => $paid_amount,
                'is_debit' => false,
            ]);

            // تحديث رصيد الخزينة المستخدمة للدفع
            $paymentTreasuryAccount->balance -= $paid_amount;
            $paymentTreasuryAccount->save();

            // تحديث رصيد المورد
            $supplierAccount->balance -= $paid_amount;
            $supplierAccount->save();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }


    /**
     * سطورُ قيد فاتورة الشراء الخمسة — كاتبٌ واحد للإنشاء والتعديل.
     *
     * ══════════════════ لماذا واحدٌ لا اثنان ══════════════════
     *
     * كان الإنشاءُ يكتب خمسة سطور، والتعديلُ يمرّ على الموجود فيصحّح
     * **ثلاثةً** منها (المشتريات والضريبة والمورد) ويترك الشحنَ والخصم
     * على قيمتيهما القديمتين. وكان يعيد حساب سطر المشتريات بمعادلةٍ غير
     * معادلة الإنشاء: `grand_total − total_tax` بلا طرح الشحن ولا ردّ
     * الخصم. فيخرج القيدُ عن التوازن بفارق (الخصم − الشحن) بمجرّد أيّ
     * تعديل، ولو لم يمسّ المستخدم رقماً:
     *
     *     PINV-00069 قبل التعديل   مدين 1179 = دائن 1179
     *     PINV-00069 بعد التعديل   مدين 1039 ≠ دائن 1179     فرق 140
     *
     * فصار التعديلُ يحذف السطور ويستدعي هذا الكاتب — ولا سبيلَ بعدها إلى
     * تباعد المسارين، لأنّ المعادلة موضعُها واحد.
     *
     * لا يمسّ أرصدةَ الحسابات: يُعيد ما تحرّك ليطبّقه المستدعي، فالإنشاء
     * يضيفه كاملاً والتعديلُ يضيف الفرق عن السطور المحذوفة.
     *
     * @return array<int, float> معرّف الحساب => المبلغ المرحَّل إليه
     *
     * @throws \Exception إن كان مفتاحٌ واجبٌ غير مربوط
     */
    /**
     * سطورُ قيد فاتورة الشراء — تفويضٌ إلى الكاتب المشترك.
     *
     * الشيفرةُ انتقلت إلى `Modules\Purchases\Support\PurchaseInvoiceJournal`
     * ليشاركها **تحويلُ الاستلام إلى فاتورة** أيضاً: كان التحويل يبني
     * القيد بنفسه بثلاثة سطور ومعادلةٍ أخرى، فتختلف فاتورةٌ محوَّلة عن
     * فاتورةٍ أُنشئت بنفس الأرقام. انظر تعليق ذلك الصنف.
     *
     * @return array<int, float> معرّف الحساب => المبلغ المرحَّل إليه
     *
     * @throws \Exception إن كان مفتاحٌ واجبٌ غير مربوط
     */
    private function writeInvoiceEntryDetails(int $journalEntryId, PurchaseInvoice $purchaseInvoice, Account $supplierAccount): array
    {
        return PurchaseInvoiceJournal::writeDetails($journalEntryId, $purchaseInvoice, $supplierAccount);
    }

    /**
     * تطبيقُ ما ردّه الكاتبُ على أرصدة الحسابات.
     *
     * بالاصطلاح القائم في هذا المتحكّم: الرصيد يزيد بمقدار ما رُحِّل إليه،
     * في اتجاه طبيعة الحساب — لا بإشارة مدين/دائن.
     *
     * @param  array<int, float>  $deltas
     */
    private function applyEntryDeltas(array $deltas): void
    {
        PurchaseInvoiceJournal::applyDeltas($deltas);
    }

    public function edit($id)
    {
        PurchaseInvoice::findOrFail($id);

        // التعديل ممنوع بعد الاعتماد سياسةً. كان النداء بلا `return` فتُعيد
        // الدالّة null — أي صفحة بيضاء بدل الرسالة التي كُتبت لتُعرض
        return redirect()->back()->with('error',
            'وفقًا للأنظمة المعتمدة، لا يمكن تعديل فاتورة الشراء بعد اعتمادها. '.
            'يمكنك إصدار فاتورة مرتجع أو إشعار مدين لمعالجة التعديل المطلوب.'
        );
    }

    public function update(Request $request, $id)
    {
        try {
            // الضريبة الشاملة — كما في store()
            PurchaseTaxCalculator::applyComprehensive($request);

            // ** الفترة المحاسبية ** — انظر store()
            if ($blocked = $this->accountingPeriodBlock($request->date)) {
                return redirect()->back()->withInput()->with('error', $blocked);
            }

            DB::beginTransaction(); // بدء المعاملة

            $user = auth()->user();
            $purchaseInvoice = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // فحص "خاصة/كل" أُزيل مع الانتقال إلى purchases.invoices.edit
            // (صلاحية ثنائية واحدة) — الفهرس هو ما يُقيَّد بنطاق البيانات
            // (Scopes::applyDataScope في getFilteredData())، لا التعديل نفسه؛
            // نفس نمط PurchaseRequestsController::update()

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحةً
            // ويملك صلاحية اختيار الفرع — نفس نمط باقي كنترولرات المشتريات
            $branchId = \App\Support\Scopes::chosenBranchId($user, $request->input('branch_id'), $purchaseInvoice->branch_id);

            // ** حفظ القيم القديمة للمقارنة **
            $old_grand_total = $purchaseInvoice->grand_total;
            $old_paid_amount = $purchaseInvoice->advance_payment;
            $old_total_tax = $purchaseInvoice->total_tax;

            // ** التحقق من وجود المورد **
            if (! $request->supplier_id) {
                throw new \Exception('يجب تحديد المورد');
            }

            $supplier = Supplier::find($request->supplier_id);
            if (! $supplier) {
                throw new \Exception('المورد المحدد غير موجود');
            }

            // «منع المستندات على مورد موقوف» — يُفحص قبل أول كتابة
            SupplierGuard::assertActive($supplier);

            // ✅ جلب جميع إعدادات فواتير الشراء (تصحيح اسم المفتاح)
            $autoPaidSetting = PurchaseSettings::enabled('default_paid_invoices');
            $autoReceivedSetting = PurchaseSettings::enabled('default_received_invoices');
            $autoPaymentSetting = PurchaseSettings::enabled('auto_payment');
            $updatePricesSetting = PurchaseSettings::enabled('update_product_prices');
            $manualInvoiceStatusSetting = PurchaseSettings::enabled('manual_invoice_status');
            $enableSettlementSetting = PurchaseSettings::enabled('enable_settlement');
            // `total_discounts` عرضٌ لا حساب: يقرّر إظهار سطر «إجمالي الخصومات»
            // في ملخّص الإجماليات والمطبوعات لا مقدارَ الخصم نفسه، فمكانه
            // القوالب (`partials/totals` وملفّات الطباعة) لا هذا المتحكّم —
            // كان يُقرأ هنا في متغيّر لا يستعمله أحد.

            // ** تجهيز المتغيرات الرئيسية لحساب الفاتورة **
            $total_amount = 0; // إجمالي المبلغ قبل الخصومات
            $total_discount = 0; // إجمالي الخصومات على البنود
            $documentTaxIds = []; // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن
            $items_data = []; // تجميع بيانات البنود
            // الضريبةُ تُحسب بعد الحلقة لا فيها: وعاؤها يحتاج الخصمَ العامّ
            // وهو غيرُ معروفٍ بعد — انظر documentTotals()
            $lineNets = []; // صافي كلّ بندٍ بعد خصمه الخاصّ
            $lineTaxes = []; // ضرائب كلّ بندٍ: المعرّفات والنسب

            // ** الخطوة الثانية: معالجة البنود (items) **
            if ($request->has('items') && count($request->items)) {
                // حل المشكلة: استخدام مصفوفة لتتبع العناصر المضافة لتجنب التكرار
                $processedItems = [];

                foreach ($request->items as $itemKey => $item) {
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

                    // جلب المنتج
                    $product = Product::findOrFail($item['product_id']);

                    // حساب تفاصيل الكمية والأسعار
                    $quantity = floatval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $item_total = $quantity * $unit_price;

                    // ✅ معالجة بيانات الوحدة
                    $product_unit_id = $item['product_unit_id'] ?? null;
                    $conversion_factor = floatval($item['conversion_factor'] ?? 1);
                    $quantity_in_base_unit = floatval($item['quantity_in_base_unit'] ?? ($quantity * $conversion_factor));
                    $unit_name = $item['unit_name'] ?? null;

                    // ✅ معالجة product_unit_id إذا كان يحتوي على قيمة غير رقمية مثل "category_10"
                    if ($product_unit_id && ! is_numeric($product_unit_id)) {
                        if (str_starts_with((string) $product_unit_id, 'category_')) {
                            $categoryUnitId = (int) str_replace('category_', '', $product_unit_id);
                            $productUnit = ProductUnit::where('product_id', $item['product_id'])
                                ->where('category_unit_id', $categoryUnitId)
                                ->first();
                            if ($productUnit) {
                                $product_unit_id = $productUnit->id;
                            } else {
                                $categoryUnit = CategoryUnit::with('unit')->find($categoryUnitId);
                                if ($categoryUnit) {
                                    $unit_name = $categoryUnit->unit->name ?? $unit_name;
                                    $conversion_factor = $categoryUnit->conversion_factor ?? $conversion_factor;
                                    $quantity_in_base_unit = $quantity * $conversion_factor;
                                }
                                $product_unit_id = null;
                            }
                        } else {
                            $product_unit_id = null;
                        }
                    }

                    // جلب اسم الوحدة من ProductUnit إذا وجد ولم يتم تعيينه
                    if ($product_unit_id && is_numeric($product_unit_id) && ! $unit_name) {
                        $productUnit = ProductUnit::with('categoryUnit.unit')->find($product_unit_id);
                        if ($productUnit && $productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                            $unit_name = $productUnit->categoryUnit->unit->name;
                            $conversion_factor = $productUnit->categoryUnit->conversion_factor ?? $conversion_factor;
                            $quantity_in_base_unit = $quantity * $conversion_factor;
                        }
                    }

                    // حساب الخصم للبند
                    $item_discount = 0; // قيمة الخصم المبدئية
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        if (isset($item['discount_type']) && $item['discount_type'] === 'percentage') {
                            $item_discount = ($item_total * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }

                    // ** ضريبة البند **
                    // النسبة **ونوعها** من «إعدادات الضريبة»: المتضمَّنة في السعر
                    // تُستخرَج منه ولا تُضاف فوقه، فلا يتضخّم إجمالي البند
                    $tax_1_id = $item['tax_1_id'] ?? null;
                    $tax_2_id = $item['tax_2_id'] ?? null;
                    $tax_1_rate = $tax_1_id ? PurchaseTaxCalculator::rate($tax_1_id) : floatval($item['tax_1'] ?? 0);
                    $tax_2_rate = $tax_2_id ? PurchaseTaxCalculator::rate($tax_2_id) : floatval($item['tax_2'] ?? 0);

                    $documentTaxIds[] = $item['tax_1_id'] ?? null;
                    $documentTaxIds[] = $item['tax_2_id'] ?? null;

                    $line = PurchaseTaxCalculator::line(
                        $quantity,
                        $unit_price,
                        $item_discount,
                        [$tax_1_id, $tax_2_id],
                        [$tax_1_rate, $tax_2_rate]
                    );

                    /* صافي البند وضرائبه — تُجمَّع وتُؤجَّل الضريبةُ إلى ما
                       بعد الحلقة: وعاؤها هو صافي البند ناقصاً حصّته من
                       الخصم العامّ، والخصمُ العامّ لا يُعرَف إلّا بعد
                       اكتمال البنود. انظر documentTotals(). */
                    $lineNets[$itemKey] = $line['base'];
                    $lineTaxes[$itemKey] = [
                        'ids' => [$tax_1_id, $tax_2_id],
                        'rates' => [$tax_1_rate, $tax_2_rate],
                    ];

                    // تحديث الإجماليات
                    $total_amount += $item_total;
                    $total_discount += $item_discount;

                    // تجهيز بيانات البند
                    $items_data[$itemKey] = [
                        'purchase_invoice_id' => null, // سيتم تعيينه لاحقًا بعد إنشاء الفاتورة
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount' => $item_discount, // تخزين الخصم للبند
                        'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                        'tax_1' => $tax_1_rate,
                        'tax_1_id' => $tax_1_id ?: null,
                        'tax_2' => $tax_2_rate,
                        'tax_2_id' => $tax_2_id ?: null,
                        /* صافي البند بلا ضريبة — لا `$line['total']`، الذي يضيف
                           ضريبةَ الأنواع «المضافة» (`type = excluded`) فوق
                           القيمة. `invoice_items.total` عمودٌ صافٍ في كل مكانٍ
                           يقرؤه (تقرير الأصناف، تكلفة الإذن المخزني، فاتورة
                           المبيعات المناظرة) — وكان هذا الموضع وحده يكتب فيه
                           القيمة شاملةً الضريبة كلّما حُدِّد معرّف ضريبةٍ للبند،
                           فيتضخّم إجمالي الصنف في التقرير بقدر الضريبة. */
                        'total' => $line['net'],
                        'store_house_id' => $item['store_house_id'] ?? null, // إضافة معرف المستودع
                        // ✅ إضافة حقول الوحدات
                        'product_unit_id' => $product_unit_id,
                        'quantity_in_base_unit' => $quantity_in_base_unit,
                        'conversion_factor' => $conversion_factor,
                        'unit_name' => $unit_name,
                    ];

                    // ✅ تحديث أسعار المنتجات إذا كان الإعداد مفعل
                    if ($updatePricesSetting) {
                        $product->update([
                            'purchase_price' => $unit_price,
                            'price' => $unit_price, // أو يمكن حساب سعر البيع بناءً على هامش ربح
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            /* ** الخصم العامّ والضريبة والشحن ** — نفسُ الدالّة التي يناديها
               `store()`، فلا يعود التعديلُ يحسب غيرَ ما حسبه الإنشاء. وكانت
               نسبةُ الخصم هنا تُحسب على `$total_amount` **قبل** طرح الضريبة
               المتضمَّنة — أي على مبلغٍ يحمل ضريبته — بخلاف `store()` نفسِه. */
            $totals = PurchaseDocumentTotals::compute($request, $lineNets, $lineTaxes, $enableSettlementSetting, $documentTaxIds);

            $invoice_discount = $totals['invoice_discount'];
            $included_item_tax = $totals['included_item_tax'];
            $tax_rows = $totals['tax_rows'];
            $shipping_cost = $totals['shipping_cost'];
            $shipping_tax = $totals['shipping_tax'];

            // بلا طرحها يُحسب المتضمَّن مرّتين — انظر store()
            $total_amount = round($total_amount - $included_item_tax, 2);

            // ✅ إجمالي الخصومات = خصم البنود + خصم الفاتورة (دائماً يُخصم)
            $final_total_discount = $total_discount + $invoice_discount;

            // حساب المبلغ بعد الخصم
            $amount_after_discount = round($total_amount - $final_total_discount, 2);

            // ** الضرائب ** — انظر store()
            $total_tax = $totals['item_tax'];

            // ** الحساب النهائي للمجموع الكلي **
            $total_with_tax = round($amount_after_discount + $total_tax + $shipping_cost + $shipping_tax, 2);

            // *** تحديد حالات الدفع والاستلام مع جميع الإعدادات ***
            $paid_amount = 0;
            $advance_payment = 0; // الدفعة المقدمة المنفصلة
            $payment_status = 'unpaid'; // الحالة الافتراضية للدفع
            $receiving_status = 'not_received'; // الحالة الافتراضية للاستلام

            // ✅ حالة الاستلام بناءً على الإعداد
            if ($autoReceivedSetting || ($request->has('is_received') && $request->is_received == '1')) {
                $receiving_status = 'received'; // مستلمة
            } else {
                // الاحتفاظ بالحالة السابقة
                $receiving_status = $purchaseInvoice->receiving_status ?? 'pending';
            }

            /* التسوية من محفظة المورد — انظر شرحها في store().
             *
             * كانت هنا نسخةٌ من المنطق القديم المقلوب: تقرأ
             * `supplier->account->balance` وتشترط أن يكون سالباً، ثمّ
             * **تكتفي بتغيير حالة الفاتورة**. فلا حركةَ محفظة، ولا قيد،
             * ولا `wallet_paid_amount` — فاتورةٌ تُقرأ «مدفوعة» ولا شيء
             * نقَص في الدفاتر مقابلها. وكان `store()` قد أُصلح وبقيت هذه.
             *
             * والرصيد يُقرأ من المحفظة، ويُطرح منه ما سبق أن سُوِّي على
             * هذه الفاتورة نفسها: تعديلُ الفاتورة مرّتين لا يخصم مرّتين.
             */
            $wallet_already_settled = round((float) ($purchaseInvoice->wallet_paid_amount ?? 0), 2);
            $credit_to_settle = 0.0;

            if ($autoPaymentSetting && $supplier) {
                $walletBalance = SupplierWalletService::balance($supplier);

                if ($walletBalance > 0) {
                    $credit_to_settle = round(min($walletBalance, max(0, $total_with_tax - $wallet_already_settled)), 2);
                }
            }

            // ما ستنتهي إليه الفاتورة من تسويةٍ إجمالاً — تقوده الحالةُ أدناه
            $credit_settled = round($wallet_already_settled + $credit_to_settle, 2);

            // ✅ حالة الدفع بناءً على الإعدادات المختلفة
            $shouldAutoPay = false;

            // 1. إعداد الدفع الكامل التلقائي
            if ($autoPaidSetting) {
                $shouldAutoPay = true;
            }
            // 2. دفع يدوي من المستخدم
            elseif ($request->has('is_paid') && $request->is_paid == '1') {
                $shouldAutoPay = true;
            }
            /* 3. رصيد المورد وحده يكفي.
             *
             * كان الشرط هنا `balance > 0` فيصرف نقداً كلّما كثُر ما **علينا**
             * للمورد — مقلوباً تماماً. وهو الفرع نفسه الذي عُطِّل في store()
             * لهذا السبب وبقي حيّاً هنا. */
            elseif ($credit_settled > 0) {
                $payment_status = $credit_settled >= $total_with_tax ? 'paid' : 'partially_paid';
            }

            if ($shouldAutoPay) {
                // النقد يُصرف على ما لم يغطّه رصيد المورد فقط
                $paid_amount = max(0, $total_with_tax - $credit_settled);
                $advance_payment = $paid_amount; // نفس المبلغ للدفعة المقدمة
                $payment_status = 'paid'; // مدفوعة بالكامل
            }
            // أو إذا كان checkbox الدفعة الكاملة مفعلاً
            elseif (! $shouldAutoPay && $request->has('is_full_advance_payment') && $request->is_full_advance_payment) {
                $paid_amount = $total_with_tax; // دفع كامل
                $advance_payment = $total_with_tax; // نفس المبلغ للدفعة المقدمة
                $payment_status = 'paid'; // مدفوعة بالكامل
            }
            // أو إذا كان هناك دفعة مقدمة من حقل advance_payment (فقط إذا لم يكن الدفع التلقائي مفعل)
            elseif (! $shouldAutoPay && $request->has('advance_payment') && floatval($request->advance_payment) > 0) {
                $advance_payment = floatval($request->advance_payment);
                $paid_amount = $advance_payment;

                // التحقق من نوع الدفعة المقدمة (مبلغ أو نسبة مئوية)
                if ($request->has('advance_payment_type') && $request->advance_payment_type === 'percentage') {
                    $advance_payment = ($total_with_tax * $advance_payment) / 100;
                    $paid_amount = $advance_payment;
                }

                // ✅ حساب حق الإيداع إن وجد
                if ($request->has('deposit_fee') && floatval($request->deposit_fee) > 0) {
                    $deposit_fee = floatval($request->deposit_fee);

                    // التحقق من نوع حق الإيداع (مبلغ أو نسبة مئوية)
                    if ($request->has('deposit_fee_type') && $request->deposit_fee_type === 'percentage') {
                        $deposit_fee = ($advance_payment * $deposit_fee) / 100;
                    }

                    // إضافة حق الإيداع إلى المبلغ المدفوع
                    $paid_amount += $deposit_fee;
                }

                // التأكد من أن المبلغ المدفوع لا يتجاوز المبلغ الإجمالي + رسوم الإيداع
                if ($paid_amount >= ($total_with_tax + $deposit_fee)) {
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid'; // مدفوعة بالكامل
                } else {
                    $payment_status = 'partially_paid'; // مدفوعة جزئياً
                }
            }
            // أو إذا كان هناك مبلغ مدفوع من الحقل العام paid_amount (فقط إذا لم يكن الدفع التلقائي مفعل)
            elseif (! $shouldAutoPay && $request->has('paid_amount') && floatval($request->paid_amount) > 0) {
                $paid_amount = floatval($request->paid_amount);
                $advance_payment = $paid_amount;

                // التأكد من أن المبلغ المدفوع لا يتجاوز المبلغ الإجمالي
                if ($paid_amount >= $total_with_tax) {
                    $paid_amount = $total_with_tax;
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid'; // مدفوعة بالكامل
                } else {
                    $payment_status = 'partially_paid'; // مدفوعة جزئياً
                }
            } else {
                // الاحتفاظ بالدفعة المقدمة القديمة إذا لم يتم تغييرها ولم يكن الدفع التلقائي مفعل
                if (! $shouldAutoPay) {
                    $advance_payment = $old_paid_amount;
                    $paid_amount = $old_paid_amount;
                    $deposit_fee = $purchaseInvoice->deposit_fee ?? 0; // استعادة حق الإيداع القديم

                    if (($paid_amount - $deposit_fee) >= $total_with_tax) {
                        $payment_status = 'paid';
                    } elseif ($paid_amount > 0) {
                        $payment_status = 'partially_paid';
                    } else {
                        $payment_status = 'unpaid';
                    }
                } else {
                    // إذا كان الدفع التلقائي مفعل
                    $paid_amount = $total_with_tax;
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid';
                }
            }

            /* دفعةٌ صريحة من المُدخِل تُلغي **التسوية الجديدة** من الرصيد —
               نظيرُ الشرط في `store()` وبنفس علّته: هو يقرّر أين يذهب ماله.

               والملغى `$credit_to_settle` وحده: ما سُوِّي على هذه الفاتورة
               من قبل حركةٌ وقعت وقيدٌ كُتب، فلا يُلغى بتعديلٍ لاحق. */
            if ($paid_amount > 0 && ! $shouldAutoPay) {
                $credit_to_settle = 0.0;
                $credit_settled = $wallet_already_settled;
            }

            /*
             * «حالة يدوية لفاتورة الشراء» — الحالة تبقى كما يضعها المُدخِل.
             *
             * الحالة أعلاه مشتقّة من المبالغ: من دفع كل شيء «مدفوعة»، ومن دفع
             * بعضه «مدفوعة جزئياً». وهذا الإعداد يوقف ذلك الاشتقاق ويجعل
             * القيمة المرسَلة من الشاشة هي المعتمدة — وهو غرضه المكتوب في
             * شاشة الخيارات نصّاً: «النظام لا يشتقّ حالة الفاتورة من الدفع
             * والاستلام؛ تبقى كما يضبطها المُدخِل».
             *
             * انظر store() لتفصيل لماذا يقتصر على `payment_status` دون المبالغ
             * ودون حالة الاستلام.
             */
            if ($manualInvoiceStatusSetting && $request->filled('payment_status')
                && in_array($request->payment_status, ['unpaid', 'partially_paid', 'paid'], true)) {
                $payment_status = $request->payment_status;
            }

            /* المستحقُّ بلا خصم المحفظة — الخدمة تخصمها بنفسها بعد الحفظ.
               انظر التعليق المطابق في `store()`: طرحُه هنا وطرحُه هناك
               يخصم التسوية مرّتين. وما سبق أن سُوِّي على هذه الفاتورة
               يُطرح مرّةً واحدة لأنّ الحفظ يكتب `due_value` من جديد. */
            $due_value = $total_with_tax - ($paid_amount - $deposit_fee) - $wallet_already_settled;
            $payment_difference = $paid_amount - $old_paid_amount; // الفرق في الدفع

            // ✅ تحديد الخزينة المستهدفة بناءً على الموظف والتحديد من الفورم
            $mainTreasuryAccount = null;
            $treasury_id = null;
            $advanceTreasuryAccount = null; // ✅ خزينة منفصلة للدفعة المقدمة
            $advance_treasury_id = null;
            $user = Auth::user();

            // أولاً: التحقق من اختيار المستخدم في الفورم للدفع الكامل
            if ($request->has('treasury_id') && ! empty($request->treasury_id)) {
                $treasury_id = $request->treasury_id;
                $mainTreasuryAccount = Account::where('id', $treasury_id)->first();
            } else {
                if ($user && $user->employee_id) {
                    $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
                    if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                        $mainTreasuryAccount = Account::where('id', $treasuryEmployee->treasury_id)->first();
                        $treasury_id = $treasuryEmployee->treasury_id;
                    } else {
                        $mainTreasuryAccount = AccountingSettings::account('main_treasury');
                        $treasury_id = $mainTreasuryAccount->id ?? null;
                    }
                } else {
                    $mainTreasuryAccount = AccountingSettings::account('main_treasury');
                    $treasury_id = $mainTreasuryAccount->id ?? null;
                }
            }

            // ✅ ثانياً: معالجة منفصلة لخزينة الدفعة المقدمة
            if ($advance_payment > 0) {
                if ($request->has('advance_payment_treasury_id') && ! empty($request->advance_payment_treasury_id)) {
                    $advance_treasury_id = $request->advance_payment_treasury_id;
                    $advanceTreasuryAccount = Account::find($advance_treasury_id);
                } else {
                    $advanceTreasuryAccount = $mainTreasuryAccount;
                    $advance_treasury_id = $treasury_id;
                }
            }

            if (! $mainTreasuryAccount) {
                throw new \Exception('لا توجد خزينة متاحة. يرجى التحقق من إعدادات الخزينة.');
            }

            // ✅ التحقق من وجود رصيد كافي للفرق الإضافي
            if ($payment_difference > 0 && $mainTreasuryAccount->balance < $payment_difference) {
                throw new \Exception('رصيد الخزينة غير كافي للفرق الإضافي. الرصيد الحالي: '.number_format($mainTreasuryAccount->balance, 2).' والمطلوب: '.number_format($payment_difference, 2));
            }

            // عمود `status` على `purchase_invoices` هو ENUM مشترك مع طلبات
            // الشراء وقيمه الوحيدة `disagree`/`approval`/`Under Review`/
            // `convert invoice` — لا `completed`/`pending`/`paid_pending_delivery`/
            // `delivered_pending_payment` كما كان يُحسَب هنا. كتابة أيٍّ من
            // هذه القيم كانت تحاول إدخال نصٍّ غير مسموح به في عمود ENUM
            // صارم، فيسقط تحديث الفاتورة كلّه (أو يُخزَّن فارغاً بصمت) —
            // ولا شاشة تعرض `status` الفاتورة أصلاً؛ المعروض فعلاً هو
            // `payment_status`/`receiving_status` أدناه، وهما ما تتحكّم به
            // إعدادات «الاستلام/الدفع التلقائي» بمعزل تام عن هذا الإعداد
            $invoice_status = $purchaseInvoice->status;

            // ** تحديث الفاتورة في قاعدة البيانات **
            $purchaseInvoice->update([
                'supplier_id' => $request->supplier_id,
                'date' => $request->date,
                'terms' => $request->terms ?? 0,
                'notes' => ($request->notes ?? '').
                          "\nتم التحديث بتاريخ: ".now()->format('Y-m-d H:i:s').
                          ' - الخزينة المستخدمة: '.$mainTreasuryAccount->name.
                          ($autoPaidSetting ? "\n(دفع تلقائي بالكامل مفعل)" : '').
                          ($autoReceivedSetting ? "\n(استلام تلقائي مفعل)" : '').
                          ($autoPaymentSetting ? "\n(دفع تلقائي حسب رصيد المورد مفعل)" : '').
                          ($updatePricesSetting ? "\n(تحديث أسعار المنتجات مفعل)" : ''),
                'payment_status' => $payment_status, // ✅ حالة الدفع الجديدة (enum)
                'receiving_status' => $receiving_status, // ✅ حالة الاستلام
                'status' => $invoice_status, // ✅ حالة الفاتورة العامة
                // حقل الحساب أُزيل من الشاشة — انظر store()
                'account_id' => $request->account_id ?: ($mainTreasuryAccount->id ?? $purchaseInvoice->account_id),
                'discount_amount' => $invoice_discount,
                'discount_type' => $request->has('discount_type') ? ($request->discount_type === 'percentage' ? 2 : 1) : 1,
                'payment_type' => $request->payment_type ?? 1,
                'shipping_cost' => $shipping_cost,
                'tax_type' => $request->tax_type ?? 1,
                'payment_method' => $request->payment_method ?? $request->advance_payment_method,
                'reference_number' => $request->reference_number ?? $request->advance_reference_number,
                'received_date' => $autoReceivedSetting ? now() : $request->received_date,
                'is_paid' => $payment_status === 'paid', // للتوافق مع النظام القديم
                'is_received' => $receiving_status === 'received', // للتوافق مع النظام القديم
                'subtotal' => $total_amount,
                'total_discount' => $final_total_discount,
                'total_tax' => $total_tax + $shipping_tax,
                'shipping_tax' => $shipping_tax,      // انظر التعليق في store()
                'grand_total' => $total_with_tax,
                'due_value' => $due_value,
                'advance_payment' => $advance_payment, // ✅ استخدام المتغير المنفصل للدفعة المقدمة
                'deposit_fee' => $deposit_fee, // ✅ حق الإيداع
                'branch_id' => $branchId,
            ]);

            /* ** الإذن المخزني ** — يتبع بنود الفاتورة بعد التعديل لا قبله.
             *
             * الفاتورة قد تُحفَظ خدماتٍ كلّها فلا إذن لها، ثم يُضاف إليها
             * صنفٌ مخزني بالتعديل — فيُفتح الإذن حينئذ. والعكس بالعكس:
             * إذنٌ قديم صارت بنوده كلّها خدمية يبقى قائماً فارغاً كأثرٍ
             * للمستند، ولا كمّية فيه تدخل الرصيد. */
            $stockableItems = array_values(array_filter(
                $items_data,
                fn ($item) => ! empty($item['product_id']) && StockEligibility::isStockable($item['product_id'])
            ));

            $warehousePermit = WarehousePermits::where('reference_type', 'purchase_invoice')
                ->where('reference_id', $purchaseInvoice->id)
                ->first();

            if (! $warehousePermit && $stockableItems) {
                $warehousePermit = $this->createWarehousePermit($purchaseInvoice, $request);
            }

            if ($warehousePermit) {
                $warehousePermit->update([
                    'grand_total' => $purchaseInvoice->grand_total,
                    'details' => 'إذن إدخال بضاعة محدَّث لفاتورة شراء رقم: '.$purchaseInvoice->code,
                ]);

                // حذف منتجات الإذن المخزني القديمة
                WarehousePermitsProducts::where('warehouse_permits_id', $warehousePermit->id)->delete();
            }

            // ** حذف البنود والضرائب القديمة **
            $purchaseInvoice->invoiceItems()->delete();
            TaxInvoice::where('invoice_id', $purchaseInvoice->id)
                ->where('type_invoice', 'purchase')
                ->delete();

            // ** معالجة الضرائب ** — من الحساب نفسه الذي بُنيت عليه الإجماليات،
            // فلا يختلف ما يُعرض في تفاصيل الضريبة عمّا دخل المجموع
            foreach ($tax_rows as $detail) {
                $tax = PurchaseTaxCalculator::find($detail['id']);

                if (! $tax) {
                    continue;
                }

                TaxInvoice::create([
                    'name' => $tax->name,
                    'invoice_id' => $purchaseInvoice->id,
                    'type' => $tax->type,
                    'rate' => $detail['rate'],
                    'value' => $detail['amount'],
                    'type_invoice' => 'purchase',
                ]);
            }

            // ** إنشاء سجلات البنود المُحدَّثة **
            foreach ($items_data as $itemKey => $item) {
                $invoiceItem = InvoiceItem::create($item);

                // إضافة منتج جديد للإذن المخزني إذا وجد، وكان الصنف ذا رصيد
                // — نفس تحويل سعر الوحدة الأساسية المستخدَم في store()
                if ($warehousePermit && ! empty($item['product_id']) && StockEligibility::isStockable($item['product_id'])) {
                    WarehousePermitsProducts::create(array_merge([
                        'warehouse_permits_id' => $warehousePermit->id,
                        'product_id' => $item['product_id'],
                        'notes' => $item['description'],
                        // يرث مستودع الإذن — انظر store()
                        'store_house_id' => $item['store_house_id'] ?? $warehousePermit->store_houses_id,
                    ], WarehousePermitsProducts::inventoryLineFromInvoiceItem(
                        // التكلفةُ الصافية لا `total` البند — انظر documentTotals()
                        array_merge($item, ['total' => $totals['line_costs'][$itemKey] ?? $item['total']])
                    )));
                }

                $product = Product::find($item['product_id']);

                // تسجيل اشعار نظام جديد لكل منتج
                OperationLog::record(
                    'updated',
                    self::LOG_MODULE,
                    $purchaseInvoice->id,
                    sprintf(
                        'تم تحديث فاتورة شراء رقم **%s** للمنتج **%s** كمية **%s** بسعر **%s** للمورد **%s** وإذن مخزني رقم **%s** من خزينة **%s** - حالة الدفع: **%s** - حالة الاستلام: **%s**%s%s%s',
                        $purchaseInvoice->code ?? '',
                        $product->name ?? '',
                        $item['quantity'] ?? '',
                        $item['unit_price'] ?? '',
                        $supplier->trade_name ?? '',
                        $warehousePermit?->number ?? '',
                        $mainTreasuryAccount->name ?? '',
                        $this->getPaymentStatusText($payment_status),
                        $this->getReceivingStatusText($receiving_status),
                        $autoPaidSetting ? ' (دفع تلقائي)' : '',
                        $autoReceivedSetting ? ' (استلام تلقائي)' : '',
                        $updatePricesSetting ? ' (تحديث أسعار)' : ''
                    ),
                );
            }

            // ** معالجة المرفقات **
            if ($request->hasFile('attachments')) {
                // حذف الملف القديم إذا وجد
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

            // *** تحديث أو إنشاء عملية الدفع ***
            if ($payment_difference != 0) {
                // البحث عن عملية دفع موجودة
                $existingPayment = PaymentsProcess::where('purchases_id', $purchaseInvoice->id)->first();

                if ($payment_difference > 0) {
                    // زيادة في الدفع - إنشاء دفعة إضافية أو تحديث الموجودة
                    $paymentNotes = '';
                    if ($autoPaidSetting) {
                        $paymentNotes = 'دفعة محدَّثة تلقائية (إعداد الدفع التلقائي مفعل)';
                    } elseif ($autoPaymentSetting) {
                        $paymentNotes = 'دفعة محدَّثة تلقائية (رصيد المورد صالح)';
                    } else {
                        $paymentNotes = $payment_status === 'paid' ? 'دفعة كاملة محدَّثة' : 'دفعة مقدمة محدَّثة بمبلغ '.number_format($paid_amount, 2);
                    }

                    // ✅ تحديد الخزينة والمبلغ حسب نوع الدفعة
                    $payment_treasury_id = ($advance_payment > 0 && $advance_treasury_id) ? $advance_treasury_id : $treasury_id;

                    if ($existingPayment) {
                        $existingPayment->update([
                            'amount' => $paid_amount,
                            'payment_date' => $request->date ?? now(),
                            'payment_method' => $request->payment_method ?? $request->advance_payment_method ?? $existingPayment->payment_method,
                            'treasury_id' => $payment_treasury_id,
                            'notes' => $paymentNotes.' لفاتورة المشتريات رقم '.$purchaseInvoice->code.' من خزينة '.($advanceTreasuryAccount ? $advanceTreasuryAccount->name : $mainTreasuryAccount->name),
                        ]);
                    } elseif ($paid_amount > 0) {
                        PaymentsProcess::create([
                            'purchases_id' => $purchaseInvoice->id,
                            'supplier_id' => $request->supplier_id,
                            'amount' => $paid_amount,
                            'payment_date' => $request->date ?? now(),
                            'payment_method' => $request->payment_method ?? $request->advance_payment_method ?? 1,
                            'type' => 'supplier payments',
                            'payment_status' => 1,
                            'employee_id' => Auth::id(),
                            'treasury_id' => $payment_treasury_id,
                            'notes' => $paymentNotes.' لفاتورة المشتريات رقم '.$purchaseInvoice->code.' من خزينة '.($advanceTreasuryAccount ? $advanceTreasuryAccount->name : $mainTreasuryAccount->name),
                        ]);
                    }

                    // تحديث رصيد الخزينة (سحب الفرق الإضافي فقط)
                    // ملاحظة: قد نحتاج لمنطق أكثر تعقيداً إذا تغيرت الخزينة، لكن للتبسيط حالياً:
                    if ($payment_treasury_id == $treasury_id) {
                        $mainTreasuryAccount->balance -= $payment_difference;
                        $mainTreasuryAccount->save();
                    } elseif ($advanceTreasuryAccount) {
                        $advanceTreasuryAccount->balance -= $payment_difference;
                        $advanceTreasuryAccount->save();
                    }

                } elseif ($payment_difference < 0) {
                    // تقليل في الدفع - إرجاع الفرق للخزينة
                    if ($existingPayment) {
                        $existingPayment->update([
                            'amount' => $paid_amount,
                            'notes' => $existingPayment->notes.' - تم تعديل المبلغ وإرجاع '.number_format(abs($payment_difference), 2).' للخزينة',
                        ]);
                    }

                    // إرجاع الفرق للخزينة
                    $mainTreasuryAccount->balance += abs($payment_difference);
                    $mainTreasuryAccount->save();
                }
            }

            // ** الخطوة السابعة: القيود المحاسبية وتحديث المخزون **

            // 1. تحديث قيد فاتورة المشتريات (دائماً عند التحديث)
            $existingEntry = JournalEntry::where('purchase_invoice_id', $purchaseInvoice->id)
                ->where('reference_type', ReferenceType::PURCHASE_INVOICE)
                ->exists();

            if (! $existingEntry) {
                $this->createAccountingEntriesFixed($purchaseInvoice, $total_with_tax, $total_tax + $shipping_tax, $paid_amount, $mainTreasuryAccount);
            } else {
                $this->updateAccountingEntriesFixed($purchaseInvoice, $old_grand_total, $old_paid_amount, $old_total_tax,
                    $total_with_tax, $paid_amount, $total_tax + $shipping_tax, $mainTreasuryAccount);
            }

            // 2. تحديث المخزون وقيد الاستلام (فقط في حالة الاستلام)
            if ($receiving_status === 'received') {
                $this->approveWarehousePermit($purchaseInvoice);
            }

            /* 3. ما استُجدّ من التسوية من محفظة المورد — بحركةٍ وقيد.
               نظيرُ الكتلة في `store()`، وهي ما كان ينقص هنا: كانت الحالة
               وحدها تتغيّر. و`$credit_to_settle` هو الجديد دون ما سبق. */
            if ($credit_to_settle > 0) {
                SupplierWalletService::payInvoice(
                    $purchaseInvoice,
                    $credit_to_settle,
                    'سداد تلقائي من محفظة المورد عند تعديل الفاتورة'
                );

                $purchaseInvoice->refresh();
                $due_value = (float) $purchaseInvoice->due_value;
                $payment_status = $purchaseInvoice->payment_status;
            }

            DB::commit(); // تأكيد التغييرات

            // ✅ إنشاء إشعار للنظام
            $activeSettings = [];
            if ($autoPaidSetting) {
                $activeSettings[] = 'دفع تلقائي';
            }
            if ($autoReceivedSetting) {
                $activeSettings[] = 'استلام تلقائي';
            }
            if ($autoPaymentSetting) {
                $activeSettings[] = 'دفع حسب رصيد المورد';
            }
            if ($updatePricesSetting) {
                $activeSettings[] = 'تحديث أسعار';
            }

            notifications::create([
                'type' => 'purchase_update',
                'title' => $user->name.' حدَّث فاتورة شراء',
                'description' => 'تم تحديث فاتورة الشراء رقم '.$purchaseInvoice->code.' للمورد '.($supplier->trade_name ?? '').' بقيمة '.number_format($purchaseInvoice->grand_total, 2).' ر.س من خزينة '.$mainTreasuryAccount->name.($payment_difference > 0 ? ' - تم سحب إضافي '.number_format($payment_difference, 2).' ر.س' : ($payment_difference < 0 ? ' - تم إرجاع '.number_format(abs($payment_difference), 2).' ر.س' : '')).' - حالة الدفع: '.$this->getPaymentStatusText($payment_status).' - حالة الاستلام: '.$this->getReceivingStatusText($receiving_status).(! empty($activeSettings) ? ' (الإعدادات المفعلة: '.implode(', ', $activeSettings).')' : ''),
            ]);

            // *** تحسين رسائل النجاح ***
            $message = $this->generateEnhancedUpdateSuccessMessage($payment_status, $receiving_status, $warehousePermit?->number ?? '',
                $payment_difference, $due_value, $mainTreasuryAccount->name, [
                    'auto_paid' => $autoPaidSetting,
                    'auto_received' => $autoReceivedSetting,
                    'auto_payment' => $autoPaymentSetting,
                    'update_prices' => $updatePricesSetting,
                ]);

            return redirect()
                ->route('invoicePurchases.show', $purchaseInvoice->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback(); // تراجع عن التغييرات في حالة حدوث خطأ

            OperationLog::failure($e, self::LOG_MODULE, 'update', $id);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', __('purchase_cycle.messages.update_failed', [
                    'document' => __('purchase_cycle.documents.purchase_invoice'),
                    'error' => $e->getMessage(),
                ]));
        }
    }

    /**
     * دالة مساعدة محدثة لتوليد رسالة النجاح للتحديث بناءً على جميع الإعدادات
     */
    private function generateEnhancedUpdateSuccessMessage($payment_status, $receiving_status, $warehouse_number, $payment_difference, $due_value, $treasury_name, $settings = [])
    {
        $payment_text = $this->getPaymentStatusText($payment_status);
        $receiving_text = $this->getReceivingStatusText($receiving_status);

        $message = 'تم تحديث فاتورة المشتريات والإذن المخزني';
        if ($warehouse_number) {
            $message .= ' رقم '.$warehouse_number;
        }
        $message .= ' بنجاح من خزينة '.$treasury_name;

        // إضافة معلومات الإعدادات المفعلة
        $activeSettings = [];
        if ($settings['auto_paid']) {
            $activeSettings[] = 'دفع تلقائي';
        }
        if ($settings['auto_received']) {
            $activeSettings[] = 'استلام تلقائي';
        }
        if ($settings['auto_payment']) {
            $activeSettings[] = 'دفع حسب رصيد المورد';
        }
        if ($settings['update_prices']) {
            $activeSettings[] = 'تحديث أسعار';
        }

        if (! empty($activeSettings)) {
            $message .= ' ('.implode(', ', $activeSettings).')';
        }

        if ($payment_difference > 0) {
            $message .= ' - تم سحب مبلغ إضافي '.number_format($payment_difference, 2).' ريال';
            if ($settings['auto_paid'] || $settings['auto_payment']) {
                $message .= ' (تلقائياً)';
            }
        } elseif ($payment_difference < 0) {
            $message .= ' - تم إرجاع مبلغ '.number_format(abs($payment_difference), 2).' ريال للخزينة';
        }

        switch ($payment_status) {
            case 'paid':
                $message .= ' - الفاتورة مدفوعة بالكامل';
                if ($settings['auto_paid'] || $settings['auto_payment']) {
                    $message .= ' (تلقائياً)';
                }
                break;
            case 'partially_paid':
                $message .= ' - المتبقي: '.number_format($due_value).' ريال';
                break;
            case 'unpaid':
                $message .= ' - بدون دفعة';
                break;
        }

        // إضافة معلومات الاستلام
        switch ($receiving_status) {
            case 'received':
                $message .= ' - مستلمة';
                if ($settings['auto_received']) {
                    $message .= ' (تلقائياً)';
                }
                break;
            case 'pending':
                $message .= ' - في انتظار الاستلام';
                break;
            case 'partial':
                $message .= ' - استلام جزئي';
                break;
        }

        $message .= ' | حالة الدفع: '.$payment_text.' | حالة الاستلام: '.$receiving_text;

        return $message;
    }

    // ✅ دالة محدثة للقيود المحاسبية - تحديث القيود الموجودة بدلاً من حذفها
    private function updateAccountingEntriesFixed($purchaseInvoice, $old_grand_total, $old_paid_amount, $old_total_tax,
        $new_grand_total, $new_paid_amount, $new_total_tax, $mainTreasuryAccount)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // 1️⃣ حساب المورد
        $supplierAccount = Account::ledger()->where('supplier_id', $purchaseInvoice->supplier_id)->first();
        if (! $supplierAccount) {
            $supplier = Supplier::find($purchaseInvoice->supplier_id);
            $supplierAccount = Account::create([
                'name' => 'حساب المورد - '.($supplier->trade_name ?? 'مورد غير معروف'),
                'supplier_id' => $purchaseInvoice->supplier_id,
                'account_type' => 'supplier',
                'balance' => 0,
                'status' => 1,
            ]);
        }

        // 2️⃣ حساب المشتريات باستخدام الثابت (رقم 25) - نفس الإنشاء
        $mainStore = $this->settingsAccount('purchases');
        if (! $mainStore) {
            throw new \Exception(__('purchase_cycle.messages.account_missing', [
                'account' => __('purchase_cycle.accounts.purchases'),
            ]));
        }

        // 3️⃣ حساب القيمة المضافة المحصلة باستخدام الثابت (رقم 37) - نفس الإنشاء
        $taxAccount = null;
        if ($new_total_tax > 0) {
            $taxAccount = $this->settingsAccount('vat_paid');
            if (! $taxAccount) {
                throw new \Exception(__('purchase_cycle.messages.account_missing', [
                    'account' => __('purchase_cycle.accounts.vat_paid'),
                ]));
            }
        }

        $payment_difference = $new_paid_amount - $old_paid_amount;

        /* 🔄 أرصدةُ حسابات قيد الفاتورة لا تُحسَب هنا بعد اليوم.

           كانت ثلاثةُ فروقٍ تُطبَّق يدوياً (المشتريات والضريبة والمورد)،
           فتُغفِل الشحنَ والخصم كما أغفلتهما سطورُ القيد نفسُها، وتحسب فرق
           المشتريات بمعادلةٍ تخالف معادلة الإنشاء. وصار الفرقُ يُشتقّ من
           السطور: ما كُتب ناقصاً ما حُذف — انظر كتلة إعادة البناء أدناه.

           ويبقى فرقُ الدفع هنا: قيدُ السداد قيدٌ ثانٍ مستقلّ لا يمرّ
           بذلك الكاتب. */

        // 4. تحديث حساب الخزينة والمورد بناءً على فرق الدفع
        if ($payment_difference != 0) {
            $mainTreasuryAccount->balance -= $payment_difference;
            $mainTreasuryAccount->save();

            $supplierAccount->balance -= $payment_difference;
            $supplierAccount->save();
        }

        // 📝 تحديث القيود المحاسبية

        // البحث عن قيد الفاتورة الرئيسي
        $invoiceJournalEntry = JournalEntry::where('reference_number', $purchaseInvoice->code)
            ->where('purchase_invoice_id', $purchaseInvoice->id)
            ->first();

        if ($invoiceJournalEntry) {
            // تحديث وصف القيد
            $invoiceJournalEntry->update([
                'description' => 'فاتورة شراء # '.$purchaseInvoice->code,
                'client_id' => $purchaseInvoice->supplier_id,
            ]);

            /* السطورُ تُبنى من جديد لا تُرقَّع.

               كان الترقيعُ يمسّ ثلاثةً من خمسة ويترك الشحنَ والخصم، وبمعادلةٍ
               تخالف معادلة الإنشاء — فيختلّ توازنُ القيد. انظر
               `writeInvoiceEntryDetails()`.

               وأثرُ السطور القديمة على الأرصدة يُقاس **قبل** حذفها ليُطرح من
               أثر الجديدة، فلا يبقى في الحساب أثرُ رقمٍ لم يعد له سطر. */
            $oldDeltas = [];

            foreach (JournalEntryDetail::where('journal_entry_id', $invoiceJournalEntry->id)->get() as $detail) {
                $oldDeltas[$detail->account_id] = ($oldDeltas[$detail->account_id] ?? 0)
                    + (float) $detail->debit + (float) $detail->credit;
            }

            JournalEntryDetail::where('journal_entry_id', $invoiceJournalEntry->id)->delete();

            $newDeltas = $this->writeInvoiceEntryDetails($invoiceJournalEntry->id, $purchaseInvoice, $supplierAccount);

            $net = $newDeltas;

            foreach ($oldDeltas as $accountId => $amount) {
                $net[$accountId] = ($net[$accountId] ?? 0) - $amount;
            }

            $this->applyEntryDeltas($net);
        }

        // 💰 تحديث قيد الدفع

        if ($new_paid_amount > 0) {
            // البحث عن قيد الدفع الموجود
            $paymentJournalEntry = JournalEntry::where('reference_number', $purchaseInvoice->code.'_دفع')
                ->first();

            if ($paymentJournalEntry) {
                // تحديث قيد الدفع الموجود
                $paymentJournalEntry->update([
                    'description' => 'دفع للمورد # '.$purchaseInvoice->code.' - '.$this->getPaymentStatusText($purchaseInvoice->payment_status),
                    'client_id' => $purchaseInvoice->supplier_id,
                ]);

                $paymentDetails = JournalEntryDetail::where('journal_entry_id', $paymentJournalEntry->id)->get();

                foreach ($paymentDetails as $detail) {
                    if ($detail->account_id == $supplierAccount->id && $detail->is_debit) {
                        $detail->update([
                            'debit' => $new_paid_amount,
                            'description' => 'دفع للمورد # '.$purchaseInvoice->code,
                        ]);
                    } elseif ($detail->account_id == $mainTreasuryAccount->id && ! $detail->is_debit) {
                        $detail->update([
                            'credit' => $new_paid_amount,
                            'description' => 'دفع للمورد # '.$purchaseInvoice->code,
                        ]);
                    }
                }
            } else {
                // إنشاء قيد دفع جديد إذا لم يكن موجوداً
                $newPaymentEntry = JournalEntry::create([
                    'reference_number' => $purchaseInvoice->code.'_دفع',
                    'date' => now(),
                    'description' => 'دفع للمورد # '.$purchaseInvoice->code.' - '.$this->getPaymentStatusText($purchaseInvoice->payment_status),
                    'status' => 1,
                    'currency' => $this->documentCurrency(),
                    'client_id' => $purchaseInvoice->supplier_id,
                    'created_by_employee' => Auth::id(),
                    'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
                    'linking_type' => LinkingType::SINGLE,
                ]);

                // حساب المورد (مدين)
                JournalEntryDetail::create([
                    'journal_entry_id' => $newPaymentEntry->id,
                    'account_id' => $supplierAccount->id,
                    'description' => 'دفع للمورد # '.$purchaseInvoice->code,
                    'debit' => $new_paid_amount,
                    'credit' => 0,
                    'is_debit' => true,
                ]);

                // حساب الخزينة (دائن)
                JournalEntryDetail::create([
                    'journal_entry_id' => $newPaymentEntry->id,
                    'account_id' => $mainTreasuryAccount->id,
                    'description' => 'دفع للمورد # '.$purchaseInvoice->code,
                    'debit' => 0,
                    'credit' => $new_paid_amount,
                    'is_debit' => false,
                ]);
            }
        } elseif ($new_paid_amount == 0 && $old_paid_amount > 0) {
            // حذف قيد الدفع إذا أصبح المبلغ المدفوع صفر
            $paymentJournalEntry = JournalEntry::where('reference_number', $purchaseInvoice->code.'_دفع')
                ->first();

            if ($paymentJournalEntry) {
                JournalEntryDetail::where('journal_entry_id', $paymentJournalEntry->id)->delete();
                $paymentJournalEntry->delete();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    // ✅ دالة مُصححة للقيود المحاسبية (نفس منطق دالة الـ store)

    // public function exportPDF($id)
    // {
    //     try {
    //         $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);

    //         $pdf = Pdf::loadView('purchases::purchases.invoices_purchase.pdf', compact('purchaseInvoice'));

    //         return $pdf->download('فاتورة-شراء-' . $purchaseInvoice->code . '.pdf');
    //     } catch (\Exception $e) {
    //         return back()->with('error', 'حدث خطأ أثناء تصدير PDF: ' . $e->getMessage());
    //     }
    // }

    public function markAsApproved($id)
    {
        try {
            DB::beginTransaction();

            $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // ** الفترة المحاسبية ** — الاستلام يكتب قيد المشتريات والضريبة
            if ($blocked = $this->accountingPeriodBlock($purchaseInvoice->date)) {
                DB::rollBack();

                return redirect()->back()->with('error', $blocked);
            }

            if ($purchaseInvoice->receiving_status === 'received') {
                // بلا `rollBack` تعود الدالّةُ والمعاملةُ مفتوحة: تبقى
                // معلَّقةً إلى آخر الطلب وتقفل صفوف الفاتورة في الأثناء
                DB::rollBack();

                return redirect()->back()->with('info', 'الفاتورة مستلمة بالفعل والقيود منشأة مسبقاً');
            }

            // تحديث الحالة إلى "مستلمة"
            $purchaseInvoice->update([
                'receiving_status' => 'received',
                'is_received' => 1,
                'received_date' => now(),
            ]);

            // جلب البيانات اللازمة للقيود
            $total_with_tax = $purchaseInvoice->grand_total;
            $tax_total = $purchaseInvoice->total_tax;
            $paid_amount = $purchaseInvoice->payments_process()->sum('amount');

            // جلب الخزينة المستخدمة في أول عملية دفع أو الخزينة الرئيسية
            $firstPayment = $purchaseInvoice->payments_process()->first();
            if ($firstPayment && $firstPayment->treasury_id) {
                $mainTreasuryAccount = Account::find($firstPayment->treasury_id);
            } else {
                $user = auth()->user();
                if ($user && $user->employee_id) {
                    $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
                    $mainTreasuryAccount = $treasuryEmployee ? Account::find($treasuryEmployee->treasury_id) : Account::where('name', 'الخزينة الرئيسية')->first();
                } else {
                    $mainTreasuryAccount = Account::where('name', 'الخزينة الرئيسية')->first();
                }
            }

            // ✅ تحديث المخزون وتفعيل الإذن المخزني وتوليد قيد الاستلام تلقائياً
            // ملاحظة: قيد المشتريات (المورد) تم إنشاؤه مسبقاً عند حفظ الفاتورة
            $this->approveWarehousePermit($purchaseInvoice);

            // تسجيل النشاط
            OperationLog::record(
                'approved',
                self::LOG_MODULE,
                $purchaseInvoice->id,
                sprintf('تم استلام فاتورة الشراء رقم **%s** وتوليد القيود المحاسبية', $purchaseInvoice->code),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تم استلام الفاتورة وتحديث المخزون وتوليد قيد الاستلام بنجاح');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('خطأ في استلام فاتورة المشتريات: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء استلام الفاتورة: '.$e->getMessage());
        }
    }

    /**
     * دالة مساعدة للموافقة على الإذن المخزني وتحديث كميات المنتجات
     */
    private function approveWarehousePermit($purchaseInvoice)
    {
        /* فاتورة خدماتٍ كلّها تُستلَم بلا مخزون.
         *
         * «الاستلام» هنا إقرارُ أداء الخدمة، لا إدخالُ بضاعة: لا إذن
         * يُفتح، ولا رصيد يُزاد، ولا قيد استلامٍ من المشتريات إلى
         * المستودع — قيدُ المشتريات نفسه كُتب عند حفظ الفاتورة. */
        $hasStockableItems = $purchaseInvoice->items
            ->contains(fn ($item) => $item->product_id && StockEligibility::isStockable($item->product_id));

        if (! $hasStockableItems) {
            return;
        }

        // البحث عن الإذن المخزني المرتبط
        $permit = WarehousePermits::where('reference_type', 'purchase_invoice')
            ->where('reference_id', $purchaseInvoice->id)
            ->first();

        if (! $permit) {
            // إذا لم يوجد إذن، نقوم بإنشائه أولاً (حالة احتياطية)
            $permit = $this->createWarehousePermit($purchaseInvoice, request());
        }

        // إذا كان معتمداً بالفعل، لا نفعل شيئاً
        if ($permit->status === 'apporod') {
            return;
        }

        // الحصول على المستودع
        $storeHouseId = $permit->store_houses_id;

        /* تكلفةُ الوحدة الصافية من سطور الإذن — لا `invoice_items.unit_price`.
         *
         * `product_details.unit_price` هو ما تقرؤه شاشاتُ المخزون وبطاقةُ
         * الصنف، وكان يُكتب سعرَ الوحدة المفوترة خاماً: بضريبتها، وقبل
         * الخصم العامّ، وبسعر العبوة إن كانت الفوترة بالكرتون والرصيد
         * بالحبّة. فيختلف عمّا في الإذن الذي عليه يُبنى متوسّطُ التكلفة.
         * نفسُ ما يفعله `PurchaseReceivingController::approve()`. */
        $permitCosts = WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)
            ->pluck('unit_price', 'product_id');

        // تحديث الكميات لكل بند في الفاتورة — الأصناف ذات الرصيد وحدها،
        // فالخدمي والرقمي لا صفَّ رصيدٍ له في مستودع
        foreach ($purchaseInvoice->items as $item) {
            if (! $item->product_id || StockEligibility::isStockless($item->product_id)) {
                continue;
            }

            $productDetail = ProductDetails::firstOrCreate(
                [
                    'store_house_id' => $storeHouseId,
                    'product_id' => $item->product_id,
                ],
                [
                    'quantity' => 0,
                ]
            );

            // زيادة الكمية بالوحدة الأساسية
            $productDetail->quantity += $item->quantity_in_base_unit ?? $item->quantity;

            // تحديث سعر الشراء الأخير في تفاصيل المنتج — الصافي من الإذن
            $unitCost = (float) ($permitCosts[$item->product_id] ?? 0);

            if ($unitCost > 0) {
                $productDetail->unit_price = $unitCost;
            }

            $productDetail->save();

            Log::info('تم تحديث المخزون تلقائياً لفاتورة شراء', [
                'invoice_id' => $purchaseInvoice->id,
                'product_id' => $item->product_id,
                'quantity_in_base_unit' => $item->quantity_in_base_unit ?? $item->quantity,
                'original_quantity' => $item->quantity,
                'store_house' => $storeHouseId,
            ]);
        }

        // تحديث حالة الإذن إلى "موافق عليه" (apporod حسب قيمة قاعدة البيانات)
        $permit->update(['status' => 'apporod']);

        // ✅ إنشاء قيد الاستلام (من المشتريات إلى المخزن)
        $this->createReceiptAccountingEntry($purchaseInvoice, $permit);
    }

    /**
     * دالة إنشاء قيد الاستلام (من حساب المشتريات إلى حساب المستودع)
     */
    private function createReceiptAccountingEntry($purchaseInvoice, $permit)
    {
        try {
            /* حساب إجمالي البنود في الإذن — الأصناف ذات الرصيد وحدها.
             *
             * القيد ينقل القيمة من «المشتريات» إلى «المستودع»، فما لا يدخل
             * المستودع لا يُنقَل إليه: قيمةُ الخدمة تبقى مصروفاً في حساب
             * المشتريات، وإلا تضخّم رصيد المستودع بما ليس فيه.
             *
             * والقيمةُ من **سطور الإذن** لا من `كمية × سعر` البند: تلك
             * تحمل الضريبةَ المضافة وتتجاهل الخصمَ العامّ، فيدخل المستودعَ
             * حسابياً أكثرُ ممّا دخله فعلاً — والفرقُ يبقى معلَّقاً في حساب
             * المشتريات بلا ما يفسّره. */
            $totalAmount = (float) WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)
                ->sum('total');

            if ($totalAmount <= 0) {
                return;
            }

            // الحصول على حساب المستودع
            $storeAccount = Account::where('storehouse_id', $permit->store_houses_id)->first();
            if (! $storeAccount) {
                $store = StoreHouse::find($permit->store_houses_id);
                $storeAccount = Account::create([
                    'name' => 'حساب المستودع - '.($store->name ?? 'مستودع غير معروف'),
                    'storehouse_id' => $permit->store_houses_id,
                    'account_type' => 'assets',
                    'balance' => 0,
                    'status' => 1,
                ]);
            }

            // فحص وجود القيد مسبقاً لمنع التكرار
            $existingReceiptEntry = JournalEntry::where('batch_reference', 'WAREHOUSE_'.$permit->id)->exists();
            if ($existingReceiptEntry) {
                return;
            }

            // إنشاء القيد المحاسبي للاستلام
            $journalEntry = JournalEntry::create([
                'date' => $purchaseInvoice->received_date ?? now(),
                'description' => 'قيد استلام بضاعة - فاتورة شراء رقم '.$purchaseInvoice->code,
                'reference_type' => ReferenceType::APPROVED_WAREHOUSE,
                'batch_reference' => 'WAREHOUSE_'.$permit->id,
                'status' => 1,
                'currency' => $this->documentCurrency(),
                'created_by_employee' => Auth::id(),
                'purchase_invoice_id' => $purchaseInvoice->id,
            ]);

            // 1. حساب المستودع (مدين) - زيادة الأصول
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $storeAccount->id,
                'description' => 'استلام بضاعة فاتورة # '.$purchaseInvoice->code,
                'debit' => $totalAmount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // 2. حساب المشتريات (دائن) - إقفال المشتريات في المخزن
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => AccountingSettings::id('purchases'),
                'description' => 'إقفال مشتريات في المخزن فاتورة # '.$purchaseInvoice->code,
                'debit' => 0,
                'credit' => $totalAmount,
                'is_debit' => false,
            ]);

            // تحديث الأرصدة
            $storeAccount->balance += $totalAmount;
            $storeAccount->save();

            $purchasesAccount = AccountingSettings::account('purchases');
            if ($purchasesAccount) {
                $purchasesAccount->balance -= $totalAmount;
                $purchasesAccount->save();
            }

            // تحديث هرمي للأرصدة
            Account::updateBalancesHierarchically($storeAccount->id);
            if ($purchasesAccount) {
                Account::updateBalancesHierarchically($purchasesAccount->id);
            }

        } catch (\Exception $e) {
            Log::error('خطأ في إنشاء قيد استلام المخزن: '.$e->getMessage());
        }
    }
    // public function Show($id)
    // {
    //     $purchaseInvoice = PurchaseInvoice::with([
    //         'invoiceItems' => function ($query) {
    //             $query->where('purchase_invoice_id');
    //         },
    //         'invoiceItems.product',
    //         'supplier',
    //     ])->findOrFail($id);
    //     $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'purchase')->get();
    //     return view('purchases::purchases.invoices_purchase.show', compact('purchaseInvoice', 'TaxsInvoice'));
    // }

    public function show($id)
    {
        $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product', 'payments', 'user'])
            ->findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'purchase')->get();

        // جلب سجل الأنشطة
        $logs = ModelsLog::where('type', 'purchase_invoice')
            ->where('type_id', $id)
            ->whereHas('purchase_invoice') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('purchases::purchases.invoices_purchase.show', [
            'purchaseInvoice' => $purchaseInvoice,
            'logs' => $logs,
            'TaxsInvoice' => $TaxsInvoice,
            // مرتجع هذه الفاتورة إن وُجد — الفاتورة الواحدة لا تُردّ مرتين،
            // فتُستبدَل «إنشاء مرتجع» برابطٍ إلى المرتجع القائم بدل أن يقود
            // الزرُّ إلى شاشةٍ ترفض الطلب
            'existingReturn' => $purchaseInvoice->relatedDocuments()
                ->where('type', 'Return')
                ->first(),
            // القوالب المصمَّمة في الإعدادات — القائمة تُبنى منها لا من نصّ ثابت
            'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
            'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
            // المعاينة والتنزيل يتبعان «طريقة الطباعة» في إعدادات الحساب
            'prefersPdf' => PrintSettings::prefersPdf(),
        ]);
    }

    // تعليم الفاتورة كمدفوعة

    // إلغاء الدفع (العودة لغير مدفوعة)
    public function markAsUnpaid($id)
    {
        try {
            $purchaseInvoice = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // `status` عمودُ سير عملٍ (enum) لا علَمَ دفعٍ — كتابة `0` هنا
            // كانت تكسر بخطأ «Data truncated» دائماً. المعروض فعلياً في
            // الشاشات هو `payment_status`/`is_paid` (انظر تعليق update() أعلاه)
            $purchaseInvoice->update([
                'payment_status' => 'unpaid',
                'is_paid' => false,
                'due_value' => $purchaseInvoice->grand_total,
            ]);

            // تسجيل النشاط
            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $purchaseInvoice->id,
                sprintf('تم إلغاء دفع فاتورة الشراء رقم **%s**', $purchaseInvoice->code),
            );

            return redirect()->back()->with('success', 'تم إلغاء دفع الفاتورة بنجاح');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.InvoicesPurchase', 'markAsUnpaid');

            return redirect()->back()->with('error', 'حدث خطأ أثناء تحديث حالة الفاتورة: '.$e->getMessage());
        }
    }

    // حذف الفاتورة
    public function destroy($id)
    {
        try {
            $purchaseInvoice = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // ** الفترة المحاسبية ** — الحذف يعكس القيود ويعيد الأرصدة
            if ($blocked = $this->accountingPeriodBlock($purchaseInvoice->date)) {
                return redirect()->back()->with('error', $blocked);
            }

            // فحص "خاصة/كل" أُزيل مع الانتقال إلى purchases.invoices.delete —
            // انظر التعليق المطابق في update()
            $invoiceCode = $purchaseInvoice->code;

            /* ردُّ ما سُوِّي من محفظة المورد قبل حذف الفاتورة.
               بلا هذا يبقى المال مستهلَكاً من دفعةٍ مقدَّمة لمستندٍ لم يعد
               له وجود، وتبقى في دفتر المحفظة حركةٌ تشير إلى فاتورةٍ محذوفة
               — فلا يُعرف أين ذهب الرصيد. والردّ قيدٌ معاكس لا حذفُ قيد:
               من ح/ محفظة المورد إلى ح/ المورد. */
            if ((float) ($purchaseInvoice->wallet_paid_amount ?? 0) > 0) {
                SupplierWalletService::refundInvoice(
                    $purchaseInvoice,
                    null,
                    'ردّ التسوية إلى المحفظة عند حذف الفاتورة '.$invoiceCode
                );
            }

            // حذف المدفوعات المرتبطة
            $purchaseInvoice->payments()->delete();

            // حذف العناصر المرتبطة
            $purchaseInvoice->items()->delete();

            // حذف الملاحظات المرتبطة
            $notes = ClientRelation::where('quotation_id', $id)
                ->where('type', 'purchase_invoice')
                ->get();

            foreach ($notes as $note) {
                // حذف المرفقات
                if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                    Storage::disk('public')->delete($note->attachment);
                }
                $note->delete();
            }

            // حذف الفاتورة
            $purchaseInvoice->delete();

            // تسجيل النشاط
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $id,
                sprintf('تم حذف فاتورة الشراء رقم **%s**', $invoiceCode),
            );

            return redirect()->route('invoicePurchases.index')
                ->with('success', __('purchase_cycle.messages.deleted'));

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $id);

            return redirect()->back()->with('error', __('purchase_cycle.messages.delete_failed', ['error' => $e->getMessage()]));
        }
    }

    // إضافة ملاحظة للفاتورة
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

            $purchaseInvoice = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // التعامل مع المرفق إذا تم رفعه
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time().'_'.$file->getClientOriginalName();
                $attachmentPath = $file->storeAs('purchase_invoices/notes', $fileName, 'public');
            }

            // إنشاء الملاحظة باستخدام ClientRelation
            $clientRelation = ClientRelation::create([
                'process' => $request->process,
                'time' => $request->time,
                'date' => $request->date,
                'quotation_id' => $id,
                'employee_id' => auth()->user()->id,
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'type' => 'purchase_invoice', // نوع مختلف للفواتير
            ]);

            // إرسال إشعار
            notifications::create([
                'user_id' => $purchaseInvoice->user_id,
                'receiver_id' => $purchaseInvoice->user_id,
                'title' => 'ملاحظة جديدة',
                'description' => 'تم إضافة ملاحظة جديدة لفاتورة الشراء رقم '.$purchaseInvoice->code,
            ]);

            // تسجيل النشاط في سجل الأنشطة
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $purchaseInvoice->id,
                sprintf(
                    'تم إضافة ملاحظة جديدة لفاتورة الشراء رقم **%s** بعنوان: %s',
                    $purchaseInvoice->code ?? '',
                    $request->process
                ),
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
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.InvoicesPurchase', 'addNote');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الملاحظة: '.$e->getMessage(),
            ], 500);
        }
    }

    // جلب الملاحظات
    public function getNotes($id)
    {
        try {
            // المستندُ الأمّ بتخويله — كما في addNote؛ كان المعرّفُ يكفي لقراءة ملاحظات أيّ مستند
            $parent = \App\Models\PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($parent->created_by, $parent->employee_id, $parent->branch_id);
            $notes = ClientRelation::where('quotation_id', $id)
                ->where('type', 'purchase_invoice')
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
                'notes' => $formattedNotes->toArray(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.InvoicesPurchase', 'getNotes');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الملاحظات: '.$e->getMessage(),
            ], 500);
        }
    }

    // حذف الملاحظة
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

            // تسجيل النشاط
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $quotationId,
                sprintf('تم حذف ملاحظة "%s" من فاتورة الشراء رقم %s', $process, $quotationId),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملاحظة بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.InvoicesPurchase', 'deleteNote');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الملاحظة: '.$e->getMessage(),
            ], 500);
        }
    }

    // توليد PDF للملاحظات
    public function generateNotesPdf($id)
    {
        try {
            // تحميل فاتورة الشراء مع الملاحظات
            $purchaseInvoice = PurchaseInvoice::with(['supplier'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);
            $notes = ClientRelation::where('quotation_id', $id)
                ->where('type', 'purchase_invoice')
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->get();

            // إنشاء PDF جديد باستخدام TCPDF
            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // تعيين معلومات الوثيقة
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('ملاحظات فاتورة الشراء - '.$purchaseInvoice->code);

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

            // إنشاء محتوى HTML
            $html = view('purchases::purchases.invoices_purchase.partials.notes_pdf', compact('purchaseInvoice', 'notes'))->render();

            // إضافة المحتوى للـ PDF
            $pdf->writeHTML($html, true, false, true, false, '');

            // إخراج الملف
            $filename = 'ملاحظات_فاتورة_شراء_'.$purchaseInvoice->code.'.pdf';

            return $pdf->Output($filename, 'I');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.InvoicesPurchase', 'generateNotesPdf');

            return back()->with('error', 'حدث خطأ أثناء توليد PDF: '.$e->getMessage());
        }
    }

    // بيانات نافذة تعيين مركز التكلفة: القائمة والاختيار الحالي
    public function assignCostCenter($id)
    {
        $purchaseInvoice = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        $costCenters = CostCenter::orderBy('code')->get(['id', 'name', 'code', 'parent_id']);

        return response()->json([
            'success' => true,
            'costCenters' => $costCenters->map(fn ($center) => [
                'id' => $center->id,
                'label' => $center->parent_id ? '— '.$center->name : $center->name,
            ]),
            'current' => $purchaseInvoice->cost_center_id,
        ]);
    }

    // حفظ مركز التكلفة المختار — ويُطبَّق أيضاً على قيد الفاتورة المُرحَّل
    // إن وُجد، فلا يبقى التعيين واجهةً بلا أثرٍ محاسبي
    public function updateCostCenter(Request $request, $id)
    {
        $purchaseInvoice = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        $validated = $request->validate([
            'cost_center_id' => 'nullable|exists:cost_centers,id',
        ]);

        $purchaseInvoice->cost_center_id = $validated['cost_center_id'] ?? null;
        $purchaseInvoice->save();

        $entry = JournalEntry::where('purchase_invoice_id', $purchaseInvoice->id)->first();
        if ($entry) {
            JournalEntryDetail::where('journal_entry_id', $entry->id)
                ->update(['cost_center_id' => $purchaseInvoice->cost_center_id]);
        }

        $costCenterName = $purchaseInvoice->cost_center_id
            ? CostCenter::find($purchaseInvoice->cost_center_id)?->name
            : null;

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $purchaseInvoice->id,
            $costCenterName
                ? 'تم تعيين مركز التكلفة **'.$costCenterName.'** لفاتورة الشراء **'.$purchaseInvoice->code.'**'
                : 'تم إلغاء تعيين مركز التكلفة عن فاتورة الشراء **'.$purchaseInvoice->code.'**'
        );

        return response()->json(['success' => true]);
    }

    // نسخ الفاتورة
    public function copy($id)
    {
        try {
            // النسخ يُنشئ فاتورةً جديدة فوراً بلا شاشة تأكيد — يستهلك حصّة
            // «فواتير المشتريات» تماماً مثل store()، فيُحرَس بنفس الفحص. وبغيره
            // يبقى للحدّ بابٌ مفتوح: من بلغ حدّه يستمرّ بالنسخ بلا مانع.
            $subscriptionGate = app(SubscriptionGate::class);
            if (! $subscriptionGate->allows('purchase_invoices')) {
                return back()->with('error', 'باقتك الحالية في اشتراك فوترة لا تشمل ميزة «فواتير المشتريات». راجع لوحة تحكّم فوترة للترقية.');
            }
            $invoiceQuota = $subscriptionGate->check('purchase_invoices');
            if (! $invoiceQuota['allowed']) {
                return back()->with('error', $invoiceQuota['reason']);
            }

            $originalInvoice = PurchaseInvoice::with(['items'])->findOrFail($id);
            $this->authorizeCreatorScope($originalInvoice->created_by, $originalInvoice->employee_id, $originalInvoice->branch_id);

            // ** الفترة المحاسبية ** — النسخةُ قيدٌ محاسبيٌّ جديد بتاريخ
            // الأصل نفسه (انظر أسفله)، فتُحرَس بنفس ما يحرس store()/update()
            if ($blocked = $this->accountingPeriodBlock($originalInvoice->date)) {
                return back()->with('error', $blocked);
            }

            /* الترويسةُ وبنودُها في معاملةٍ واحدة: كان الحفظُ مفرَّقاً، فإن
               سقط بندٌ في منتصف الحلقة بقيت فاتورةٌ محفوظةٌ ناقصةَ البنود
               لا يدري المستخدمُ بنقصها — وهو نمطُ النسخ في بقيّة الوحدة
               (`QuotationsController::duplicate`). */
            DB::beginTransaction();

            // إنشاء فاتورة جديدة بنفس البيانات
            $newInvoice = $originalInvoice->replicate();
            $newInvoice->code = $this->generateInvoiceCode();
            // `status` عمودُ سير عملٍ (enum: disagree/approval/Under Review/…)
            // لا علَمَ دفعٍ رقميّ — الكتابةُ القديمة `status = 0` كانت تكسر
            // بخطأ «Data truncated» لأن العمود لا يقبل قيمةً رقمية. والنسخة
            // مستندٌ جديد فتبدأ سيرَ مراجعتها من أوّله، بنفس ما تفعله
            // `QuotationsController::duplicate` و`ViewPurchasePriceController::duplicate`
            $newInvoice->status = 'Under Review';
            // ولا سندات دفعٍ نُسخت للفاتورة الجديدة، فيُصفَّر ما ورثَتْه
            // بالنسخ من عمود `wallet_paid_amount` وإلا حسبته الحاسبةُ سداداً
            // لم يحدث لها هي (انظر PurchasePaymentCalculator::paidAmount)
            $newInvoice->wallet_paid_amount = 0;
            // النسخةُ لم تُستلَم بعد: استلامُ الأصل حرّك مخزونَه بإذنٍ خاصٍّ
            // به، ولا إذنَ للنسخة حتى تُعتمَد هي (نفس ما تفعله بالفعل
            // ReturnsInvoiceController::copy() للمرتجعات)
            $newInvoice->is_received = 0;
            $newInvoice->receiving_status = 'not_received';
            $newInvoice->received_date = null;
            // العمودُ يُملأ تلقائياً من `date` عند الإنشاء (BelongsToAccountingPeriod)
            // **إلا إذا كان معموراً مسبقاً** — و`replicate()` ينسخ قيمة الأصل
            // كما هي، فيبقى القيدُ الجديد منسوباً إلى فترة الأصل ولو تغيّرت
            // حدودُ الفترات أو أُقفلت منذ ذلك الحين
            $newInvoice->accounting_period_id = null;
            $newInvoice->created_at = now();
            $newInvoice->updated_at = now();
            $newInvoice->save();

            // نسخ العناصر
            foreach ($originalInvoice->items as $item) {
                $newItem = $item->replicate();
                $newItem->purchase_invoice_id = $newInvoice->id;
                $newItem->save();
            }

            // تحديث حالة الدفع فعلياً إلى «غير مدفوعة»: لا مدفوعات ولا
            // قيود محاسبية على النسخة الجديدة
            $newInvoice->updatePaymentStatus();

            /* ** الإذنُ المخزني — بنفس بنود إذن الفاتورة الأصلية، لا بإعادة
             * حسابٍ من `invoice_items.total` **
             *
             * كانت الدالّةُ لا تُنشئ إذناً ولا قيداً محاسبياً إطلاقاً: الفاتورةُ
             * المنسوخة توجد وحدها بلا أثرٍ في المخزون ولا في دفتر الأستاذ،
             * خلافاً لكل فاتورةٍ تصل عبر store() (تُنشئ الاثنين دائماً — انظر
             * تعليق «الخطوة السادسة» هناك).
             *
             * والتكلفةُ المخزَّنة في بنود الإذن الأصلي (`line_costs`، بعد
             * الضريبة المتضمَّنة والخصم العامّ) **تختلف** عن `invoice_items.total`
             * المخزَّن على البند (بضريبته المضافة وقبل الخصم العامّ — انظر
             * PurchaseDocumentTotals::compute())، فحسابُها من جديد هنا يُخرج
             * تكلفة مخزونٍ مختلفة عمّا سجّله الإذنُ الأصلي لنفس الأصناف
             * والكمّيات. نسخُ بنود الإذن كما هي يضمن تطابق التكلفة تماماً.
             */
            $originalPermit = WarehousePermits::where('reference_type', 'purchase_invoice')
                ->where('reference_id', $originalInvoice->id)
                ->first();

            if ($originalPermit) {
                $lastPermit = WarehousePermits::orderBy('id', 'desc')->first();
                $nextPermitNumber = $lastPermit ? intval($lastPermit->number) + 1 : 1;

                $newPermit = $originalPermit->replicate();
                $newPermit->number = str_pad($nextPermitNumber, 6, '0', STR_PAD_LEFT);
                $newPermit->reference_id = $newInvoice->id;
                $newPermit->grand_total = $newInvoice->grand_total;
                $newPermit->details = 'إذن إدخال بضاعة لفاتورة شراء رقم: '.$newInvoice->code;
                // معلَّقٌ بانتظار الاعتماد — كالإذن الذي تُنشئه store() لفاتورةٍ
                // لم تُستلَم بعد؛ اعتمادُه لاحقاً (markAsApproved) هو ما يحرّك
                // المخزون فعلياً، تماماً كأيّ فاتورةٍ جديدة تُدخَل يدوياً
                $newPermit->status = 'pending';
                $newPermit->rejection_reason = null;
                $newPermit->rejected_by = null;
                $newPermit->rejected_at = null;
                $newPermit->created_by = Auth::id();
                $newPermit->accounting_period_id = null;
                $newPermit->created_at = now();
                $newPermit->updated_at = now();
                $newPermit->save();

                foreach ($originalPermit->items as $permitLine) {
                    $newLine = $permitLine->replicate();
                    $newLine->warehouse_permits_id = $newPermit->id;
                    $newLine->save();
                }
            }

            // ** القيد المحاسبي — دائماً عند الحفظ لإثبات المديونية، بلا دفعٍ
            // (paid_amount = 0، فلا يُنشأ قيدُ سداد ولا تُمسّ خزينة) — نفس
            // ما يفعله store() في «الخطوة السادسة» **
            $this->createAccountingEntriesFixed($newInvoice, $newInvoice->grand_total, $newInvoice->total_tax, 0, null);

            DB::commit();

            // تسجيل النشاط
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $newInvoice->id,
                sprintf(
                    'تم إنشاء فاتورة شراء جديدة رقم **%s** بنسخ البيانات من الفاتورة رقم **%s**',
                    $newInvoice->code,
                    $originalInvoice->code
                ),
            );

            return redirect()->route('invoicePurchases.show', $newInvoice->id)
                ->with('success', 'تم نسخ الفاتورة بنجاح');

        } catch (\Exception $e) {
            // الفحصُ قبل التراجع: الاستثناء قد يقع قبل فتح المعاملة
            // (بوّابةُ الاشتراك، أو `findOrFail`) فيرمي `rollBack` حينها
            // خطأً ثانياً يحجب الأوّل عن السجلّ
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.InvoicesPurchase', 'copy');

            return redirect()->back()->with('error', 'حدث خطأ أثناء نسخ الفاتورة: '.$e->getMessage());
        }
    }

    /**
     * رقم فاتورة جديد من إعدادات «تسلسل أرقام المستندات».
     *
     * كانت الصيغة `INV-YYYYMM0001` مكتوبةً هنا فتتجاهل البادئة والصيغة
     * ودورة التصفير المضبوطة في الإعدادات — ولا يملك المسؤول تغييرها.
     */
    private function generateInvoiceCode()
    {
        return next_document_number(self::NUMBER_SECTION);
    }

    /**
     * معاينة الطباعة بقالب مضبوط في «قوالب المستندات».
     *
     * القوالب المدمجة (تفصيلي/مختصر/حراري) تبقى احتياطاً حين لا يكون
     * المسؤول قد صمّم قالباً بعد.
     */
    public function printView($id, $template = 'default')
    {
        $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $purchaseInvoice->id,
            __('purchase_cycle.log.printed', [
                'document' => __('purchase_cycle.documents.purchase_invoice'),
                'code' => $purchaseInvoice->code,
            ]),
        );

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html !== null) {
            return response(DocumentPrintPage::wrap($html));
        }

        return view($this->builtinTemplateView($template), compact('purchaseInvoice'));
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

    /** القالب المدمج المقابل للمفتاح — الافتراضي هو التفصيلي */
    private function builtinTemplateView($template): string
    {
        return match ($template) {
            'thermal' => 'purchases::purchases.invoices_purchase.thermal',
            'compact' => 'purchases::purchases.invoices_purchase.compact',
            default => 'purchases::purchases.invoices_purchase.pdf',
        };
    }

    /**
     * حساب مضبوط في شاشة «الحسابات المحاسبية» — بالمفتاح وحده.
     *
     * لا ثابت برمجي احتياطياً هنا: الإعدادات هي المصدر الوحيد لحسابات
     * فواتير الشراء. والمفتاح غير المربوط يُرَدّ برسالة تسمّيه وتدلّ على
     * الشاشة، لا بـ`null` يتحوّل بعد أسطر إلى خطأ غامض في القيد.
     *
     * @throws \Exception إن لم يكن المفتاح مربوطاً أو كان حسابه محذوفاً
     */
    private function settingsAccount(string $key): Account
    {
        $account = AccountingSettings::account($key);

        if (! $account) {
            throw new \Exception(__('purchase_cycle.messages.account_missing', [
                'account' => __('purchase_cycle.accounts.'.$key),
            ]).' '.$this->accountMissingReason($key));
        }

        return $account;
    }

    /**
     * لماذا لم يُعثر على الحساب — بعينه لا بالعموم.
     *
     * «غير مربوط» وحدها تُرسل المستخدم إلى الشاشة يبحث عن صفٍّ يراه أمامه
     * مربوطاً فعلاً، لأن أسباب الـ`null` أربعة لا واحد: لا صفَّ للمفتاح
     * أصلاً، أو الصفّ بلا حساب، أو الصفّ معطَّل، أو الحساب المربوط حُذف من
     * شجرة الحسابات. ولكلٍّ إصلاحٌ مختلف، فتُسمّى الحالة كما هي.
     */
    private function accountMissingReason(string $key): string
    {
        $setting = AccountingSetting::where('key', $key)->first();

        if (! $setting) {
            return __('purchase_cycle.messages.account_reason.no_row', ['key' => $key]);
        }

        if (! $setting->account_id) {
            return __('purchase_cycle.messages.account_reason.unlinked');
        }

        if (! $setting->is_active) {
            return __('purchase_cycle.messages.account_reason.inactive');
        }

        return __('purchase_cycle.messages.account_reason.deleted_account', ['id' => $setting->account_id]);
    }

    /**
     * عملة المستند من شاشة «العملات» — لا «SAR» مكتوبةً في الشيفرة.
     *
     * كانت أربعة مواضع في هذا المتحكّم تكتب `'currency' => 'SAR'` نصّاً في
     * القيد المحاسبي، فمن ضبط عملة النظام على غير الريال يرى فاتورته
     * بعملته في الشاشة وبـ`SAR` في القيد. نفس ما عولج في
     * `Modules\Sales\...\Concerns\UsesDocumentCurrency`.
     */
    private function documentCurrency(): string
    {
        return CurrencySettings::code();
    }

    /**
     * الرقم المعتمد للفاتورة — ما عرضته الشاشة، وإلا التالي من الإعدادات.
     * حقل الرقم للقراءة فقط، فما يصل هنا هو ما عُرض لا ما كُتب.
     *
     * الرقم المحجوز لا يُرفض بل يُستبدل بالتالي الشاغر، فلا يضيع إدخال
     * المستخدم على تضارب رقم ولّدته الشاشة نفسها.
     */
    private function resolveCode($submitted): string
    {
        $submitted = trim((string) $submitted);

        if ($submitted === '' || PurchaseInvoice::where('code', $submitted)->exists()) {
            return next_document_number(self::NUMBER_SECTION);
        }

        return $submitted;
    }

    // تصدير PDF
    public function exportPDF($id)
    {
        try {
            $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // إنشاء PDF جديد باستخدام TCPDF
            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // تعيين معلومات الوثيقة
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('فاتورة شراء - '.$purchaseInvoice->code);

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

            // إنشاء محتوى HTML
            $html = view('purchases::purchases.invoices_purchase.pdf', compact('purchaseInvoice'))->render();

            // إضافة المحتوى للـ PDF
            $pdf->writeHTML($html, true, false, true, false, '');

            // إخراج الملف
            $filename = 'فاتورة-شراء-'.$purchaseInvoice->code.'.pdf';

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseInvoice,
                __('purchase_cycle.log.exported', [
                    'document' => __('purchase_cycle.documents.purchase_invoice'),
                    'code' => $purchaseInvoice->code,
                ]),
            );

            return $pdf->Output($filename, 'D');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return back()->with('error', 'حدث خطأ أثناء تصدير PDF: '.$e->getMessage());
        }
    }

    public function printPdf($id)
    {
        try {
            $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // إنشاء PDF جديد باستخدام TCPDF
            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // تعيين معلومات الوثيقة
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('فاتورة شراء - طباعة - '.$purchaseInvoice->code);

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

            // إنشاء محتوى HTML
            $html = view('purchases::purchases.invoices_purchase.print', compact('purchaseInvoice'))->render();

            // إضافة المحتوى للـ PDF
            $pdf->writeHTML($html, true, false, true, false, '');

            // إخراج الملف
            $filename = 'فاتورة شراء - طباعة - '.$purchaseInvoice->code.'.pdf';

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseInvoice,
                __('purchase_cycle.log.exported', [
                    'document' => __('purchase_cycle.documents.purchase_invoice'),
                    'code' => $purchaseInvoice->code,
                ]),
            );

            return $pdf->Output($filename, 'D');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return back()->with('error', 'حدث خطأ أثناء تصدير PDF: '.$e->getMessage());
        }
    }

    public function compactPdf($id)
    {
        try {
            $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            // إنشاء PDF جديد باستخدام TCPDF
            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // تعيين معلومات الوثيقة
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('فاتورة شراء - مختصرة - '.$purchaseInvoice->code);

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

            // إنشاء محتوى HTML
            $html = view('purchases::purchases.invoices_purchase.compact', compact('purchaseInvoice'))->render();

            // إضافة المحتوى للـ PDF
            $pdf->writeHTML($html, true, false, true, false, '');

            // إخراج الملف
            $filename = 'فاتورة شراء - مختصرة - '.$purchaseInvoice->code.'.pdf';

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseInvoice,
                __('purchase_cycle.log.exported', [
                    'document' => __('purchase_cycle.documents.purchase_invoice'),
                    'code' => $purchaseInvoice->code,
                ]),
            );

            return $pdf->Output($filename, 'D');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return back()->with('error', 'حدث خطأ أثناء تصدير PDF: '.$e->getMessage());
        }
    }

    public function generatePdf($id, $template = null)
    {
        try {
            $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

            /* القالب المصمَّم في «قوالب المستندات» يُعرض صفحةَ طباعة تُحفظ PDF
               من المتصفّح — TCPDF كان يبعثر تصميمه على أربع صفحات (انظر
               ServesDesignedTemplatePage). المدمج احتياطٌ حين لا قالب للنوع. */
            if ($page = $this->configuredTemplatePage($id, $template, $purchaseInvoice->code)) {
                OperationLog::record(
                    'exported',
                    self::LOG_MODULE,
                    $purchaseInvoice,
                    __('purchase_cycle.log.exported', [
                        'document' => __('purchase_cycle.documents.purchase_invoice'),
                        'code' => $purchaseInvoice->code,
                    ]),
                );

                return $page;
            }

            $viewTemplate = $this->builtinTemplateView($template);

            // إنشاء PDF جديد باستخدام TCPDF
            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // تعيين معلومات الوثيقة
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('فاتورة شراء - '.$purchaseInvoice->code);

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

            // إنشاء محتوى HTML
            $html = view($viewTemplate, compact('purchaseInvoice'))->render();

            // إضافة المحتوى للـ PDF
            $pdf->writeHTML($html, true, false, true, false, '');

            // إخراج الملف
            $filename = 'فاتورة شراء - '.$purchaseInvoice->code.'.pdf';

            OperationLog::record(
                'exported',
                self::LOG_MODULE,
                $purchaseInvoice,
                __('purchase_cycle.log.exported', [
                    'document' => __('purchase_cycle.documents.purchase_invoice'),
                    'code' => $purchaseInvoice->code,
                ]),
            );

            return $pdf->Output($filename, 'D');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'export', $id);

            return back()->with('error', 'حدث خطأ أثناء تصدير PDF: '.$e->getMessage());
        }
    }

    // تصدير Excel
    public function exportExcel($id)
    {
        $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        // هنا يمكن إضافة منطق تصدير Excel
        return redirect()->back()->with('info', 'تصدير Excel قيد التطوير');
    }

    // إرسال للطباعة
    public function print($id, Request $request)
    {
        $purchaseInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseInvoice->created_by, $purchaseInvoice->employee_id, $purchaseInvoice->branch_id);

        // تسجيل النشاط
        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $purchaseInvoice->id,
            sprintf('تم طباعة فاتورة الشراء رقم **%s**', $purchaseInvoice->code),
        );

        // Get template from database
        $templateId = $request->get('template_id');

        if ($templateId) {
            $template = Template::where('type', 'purchase_invoice')->findOrFail($templateId);
        } else {
            // Get default template or first available
            $template = Template::where('type', 'purchase_invoice')
                ->where('is_default', true)
                ->first();

            if (! $template) {
                $template = Template::where('type', 'purchase_invoice')->first();
            }
        }

        // If template exists, render it
        if ($template) {
            try {
                // Prepare data in the format templates expect
                $invoice = (object) [
                    'invoice_number' => $purchaseInvoice->code,
                    'invoice_date' => Carbon::parse($purchaseInvoice->date),
                    'due_date' => Carbon::parse($purchaseInvoice->date)->addDays($purchaseInvoice->terms ?? 0),
                    'supplier' => $purchaseInvoice->supplier,
                    'items' => $purchaseInvoice->items,
                    'sub_total' => $purchaseInvoice->subtotal,
                    'total_tax' => $purchaseInvoice->total_tax,
                    'grand_total' => $purchaseInvoice->grand_total,
                    'notes' => $purchaseInvoice->notes,
                ];

                /*
                    بيانات المنشأة كانت مكتوبة هنا: الاسم والعنوان والهاتف
                    والرقم الضريبي أربعةَ نصوصٍ ثابتة تُمرَّر إلى قالب
                    المستند. فالعميل يغيّر رقمه الضريبي في الإعدادات وتخرج
                    فواتير الشراء بالرقم القديم — وهو رقمُ منشأةٍ أخرى.
                */
                $organization = OrganizationInfo::all();

                $company = (object) [
                    'name' => $organization['name'],
                    'address' => $organization['address'],
                    'phone' => $organization['phone'],
                    'vat_number' => $organization['tax_number'],
                    'email' => $organization['email'],
                    'logo' => $organization['logo'],
                    'commercial_registration' => $organization['commercial_registration'],
                ];

                // Render the template using Blade
                $html = Blade::render($template->content, [
                    'invoice' => $invoice,
                    'company' => $company,
                ]);

                return response(DocumentPrintPage::wrap($html));

            } catch (\Exception $e) {
                // Log error and fallback to default view
                Log::error('Template rendering failed: '.$e->getMessage());

                return view('purchases::purchases.invoices_purchase.print', compact('purchaseInvoice'));
            }
        }

        // Fallback to default hardcoded view if no template found
        return view('purchases::purchases.invoices_purchase.print', compact('purchaseInvoice'));
    }
}
