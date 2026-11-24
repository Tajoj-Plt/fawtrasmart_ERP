<?php

namespace Modules\Purchases\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\ClientRelation;
use App\Models\DocumentTemplate;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PaymentsProcess;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\PurchaseInvoice;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\AccountingSettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use App\Support\StockEligibility;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\Purchases\PurchaseDocumentTotals;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use App\Services\Purchases\PurchaseTaxCalculator;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\ProductUnits;
use Modules\Purchases\Support\PurchaseOutboundStock;
use Modules\Purchases\Support\SupplierGuard;

class ReturnsInvoiceController extends Controller
{
    use Concerns\ResolvesPurchaseAccounts;

    use Concerns\PreparesPurchaseDocumentLines;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\ServesDesignedTemplatePage;
    use ScopesToEmployeeHierarchy;

    /** نوع المستند في شاشة «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'purchase_return';

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'purchase-return';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمستند */
    public const LOG_MODULE = 'purchase_return';

    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً — لا قديمة ولا من
     * كتالوج `PurchasesPermissions` رغم أن مجموعة `returns` معرَّفة فيه
     * أصلاً وتُشير إليه صراحةً. الحراسة هنا أول تطبيق فعلي لها.
     */
    public function __construct()
    {
        $this->middleware('permission:purchases.returns.view', ['only' => ['index', 'paginatePurchase', 'show', 'getInvoiceDetails', 'getNotes']]);
        $this->middleware('permission:purchases.returns.create', ['only' => ['create', 'createFromInvoice', 'store']]);
        // النسخُ صلاحيتُه المستقلّة
        $this->middleware('permission:purchases.returns.duplicate', ['only' => ['copy']]);
        $this->middleware('permission:purchases.returns.edit', ['only' => ['edit', 'update', 'addNote']]);
        $this->middleware('permission:purchases.returns.delete', ['only' => ['destroy', 'deleteNote']]);
        $this->middleware('permission:purchases.returns.approve', ['only' => ['markAsApproved']]);
        $this->middleware('permission:purchases.returns.export', ['only' => ['printView', 'pdf']]);
    }

    /**
     * معاينة الطباعة بقالب مضبوط في «قوالب المستندات».
     */
    public function printView($id, $template = 'default')
    {
        $document = PurchaseInvoice::with(['supplier', 'creator', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($document->created_by);

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderConfiguredTemplate($id, $fallback->id) : '';
        }

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $document,
            $this->logLine('printed', $document->code),
        );

        return response(DocumentPrintPage::wrap($html));
    }

    /**
     * يصيّر قالباً من الإعدادات — أو `null` إن لم يكن `$template` معرّفَ
     * قالبٍ صالح لهذا النوع، فيتولّى النداءُ البديلَ.
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

    /**
     * أعداد بطاقات الملخّص لهذا النوع من المستندات.
     *
     * الجدول `purchase_invoices` يحمل أربعة أنواع، فالعدّ يُقيَّد بـ`type`
     * وإلا عدّ كلُّ شاشةٍ مستنداتِ الشاشات الأخرى.
     *
     * @return array<string, int>
     */
    private function summary(?array $range = null): array
    {
        /* بطاقاتُ الملخّص على نطاق الشاشة نفسِه: الفرعُ النشط ونطاقُ
           الرؤية كما يُطبَّقان على الجدول. وإلّا عدّت البطاقةُ مستنداتٍ
           لا يراها صاحبُ الشاشة في الجدول تحتها. */
        $base = fn () => $this->scopeToPeople(
            PurchaseInvoice::where('type', 'Return')
                ->when($range, fn ($q) => $q->whereDate('date', '>=', $range['start'])
                    ->whereDate('date', '<=', $range['end']))
        )
            // والفرعُ الرئيسيّ مستثنىً — مركزُ المنشأة يرى مستنداتِها كلَّها،
            // وكذلك محورُ الأشخاص، انظر `applyHeaderBranchScope()`
            ->tap(fn ($q) => $this->applyHeaderBranchScope($q))
            // وما أُقفل لا يُعَدّ: البطاقةُ تعدّ ما يعدّه الجدول تحتها
            ->tap(fn ($q) => $this->excludeClosedRanges($q, 'purchase_invoices.date'));

        return [
            'total' => $base()->count(),
            'paid' => $base()->where('payment_status', 'paid')->count(),
            'partial' => $base()->whereIn('payment_status', ['partial', 'partially_paid'])->count(),
            'unpaid' => $base()->where('payment_status', 'unpaid')->count(),
        ];
    }

    /** تراجع آمن — لا يمسّ إلا معاملةً فتحها هذا المتحكّم فعلاً */
    private function rollBackIfOpen(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    /**
     * مرتجع الفاتورة إن وُجد — الفاتورة الواحدة لا تُردّ مرتين.
     *
     * القاعدة تُطبَّق في ثلاثة مداخل (فتح الشاشة، جلب التفاصيل، الحفظ)
     * لأن أيّها وحده لا يكفي: الشاشة تُفتَح برابط مباشر، والحفظ يُرسَل من
     * نافذتين مفتوحتين معاً.
     */
    private function existingReturnFor($invoiceId): ?PurchaseInvoice
    {
        return PurchaseInvoice::where('reference_id', $invoiceId)
            ->where('type', 'Return')
            ->first();
    }

    /** نصّ رفض التكرار — يسمّي الفاتورة ومرتجعها فلا يبقى السبب مبهماً */
    private function duplicateReturnMessage(PurchaseInvoice $invoice, PurchaseInvoice $existing): string
    {
        return __('purchase_cycle.return.already_exists', [
            'invoice' => $invoice->code,
            'code' => $existing->code,
        ]);
    }

    /**
     * الخزائن المتاحة — أبناء الحسابين الأبوين المربوطين في شاشة
     * «الحسابات المحاسبية».
     *
     * **الإعدادات هي المصدر الوحيد.** لا ثابت برمجي احتياطياً هنا: مفتاحٌ
     * غير مربوط يعني قائمةً فارغة تدلّ على الشاشة، لا قيداً يذهب صامتاً
     * إلى حسابٍ مكتوبٍ في الشيفرة لا يعرفه المحاسب.
     */
    private function treasuryAccounts()
    {
        return Account::whereIn('parent_id', array_filter([
            AccountingSettings::id('bank_accounts_parent'),
            AccountingSettings::id('cash_treasuries_parent'),
        ]))->get();
    }

    /**
     * حساب مربوط في شاشة «الحسابات المحاسبية» — بالمفتاح وحده.
     *
     * المفتاح غير المربوط يُرَدّ برسالة تسمّيه وتدلّ على الشاشة، لا بـ`null`
     * يتحوّل بعد أسطر إلى خطأ غامض في القيد.
     *
     * @throws \Exception إن لم يكن المفتاح مربوطاً أو كان حسابه محذوفاً
     */
    private function settingsAccount(string $key): Account
    {
        $account = AccountingSettings::account($key);

        if (! $account) {
            throw new \Exception(__('purchase_cycle.messages.account_missing', [
                'account' => __('purchase_cycle.accounts.'.$key),
            ]));
        }

        return $account;
    }

    /**
     * خزينة المستخدم الافتراضية: خزينته الموكَّلة، وإلا «الخزينة الرئيسية»
     * كما تربطها شاشة «الحسابات المحاسبية» — وإلا `null`، فيتولّى نداءُ
     * الحفظ إظهارَ الرسالة التي تسمّي المفتاح غير المربوط.
     */
    private function defaultTreasury(): ?Account
    {
        $user = Auth::user();

        if ($user && $user->employee_id) {
            $assigned = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

            if ($assigned && $assigned->treasury_id) {
                return Account::find($assigned->treasury_id);
            }
        }

        return AccountingSettings::account('main_treasury');
    }

    public function index(Request $request)
    {
        /* نطاق التاريخ الافتراضي هو **الفترة المحاسبية الجارية** — نفس قاعدة
           شاشة فواتير المبيعات. مرتجعُ الشراء يكتب قيداً كالفاتورة، فيُقرأ
           على المدى الذي تعمل عليه المنشأة لا على تاريخها كلّه. */
        $range = $this->reportDateRange($request);

        // فلاترُ القائمة تتبع فرعَ الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers()->get();
        $users = $this->creatorOptions(\App\Models\PurchaseInvoice::where('type', 'Return'));
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        $taxes = TaxInvoice::all();
        // قائمة الحسابات في التصفية المتقدّمة — كانت تُبنى من متغيّر لا
        // يمرّره المتحكّم فتخرج فارغة دائماً
        $accounts = Account::orderBy('name')->get(['id', 'name']);

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax()) {
            return $this->getFilteredData($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $returnsData = $this->getFilteredData($request, false);

        /* خيارات التصفية بالفرع والموظّف — الفرعُ والموظّفُ مختومان على
           المستند نفسِه (`branch_id` و`employee_id`)، فالتصفيةُ بهما تقرأ
           عمودَه لا سياقَ منشئه اليوم.

           والفروعُ كلُّها لا النشطةُ وحدها: الشاشة تقرأ تاريخاً، ومستندٌ
           صدر من فرعٍ أُوقف لاحقاً يجب أن يبقى قابلاً للتصفية. */
        $branches = \App\Models\Branch::orderBy('name')->get(['id', 'name']);
        $employees = \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::orderBy('first_name'))->get();

        // ملخّص الشاشة — على النوع وحده لا على الجدول المشترك، وعلى نطاق
        // الشاشة نفسِه لا على التاريخ كلّه
        $stats = $this->summary($range);

        // ما يضيّق القائمة — يُعرض فوقها فلا تبدو شاشةٌ فارغةٌ عطلاً
        $scopeNotice = $this->listScopeNotice($range);

        return view('purchases::purchases.returns.index', compact('scopeNotice', 'returnsData', 'taxes', 'suppliers', 'users', 'account_setting', 'accounts', 'stats', 'range', 'branches', 'employees'));
    }

    private function getFilteredData(Request $request, $returnJson = true)
    {
        $query = PurchaseInvoice::query()
            ->with(['supplier', 'creator', 'originalDocument'])
            ->where('type', 'Return');

        // نطاق البيانات — نفس نمط PurchaseRequestsController::index()
        $query = $this->scopeToPeople($query);

        /* الفرع النشط من الهيدر — كما تفعل شاشة فواتير المبيعات.
           مبدّلُ الفروع يكتب `users.branch_id` و`current_branch_id()` تقرؤه،
           وكانت الشاشة لا تقرؤه: يختار المستخدم فرعاً فيبقى الجدول على
           مستندات المنشأة كلّها. المديرُ على «كل الفروع» قيمتُه `null` فلا
           تصفية، والمستنداتُ التي سبقت عمودَ الفرع تبقى ظاهرةً (`forBranch`).

           والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها، فمن
           يقف عليه يرى المشتريات كلَّها. */
        $this->applyHeaderBranchScope($query, $request);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        // ترتيب النتائج
        $query->orderBy('created_at', 'desc');

        // عدد السجلات في الصفحة يتبع اختيار المستخدم، وكان ثابتاً عند 30
        // فلا يفعل مُنتقي «عرض» شيئاً
        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        // اسم المتغيّر هو نفسه في التصيير الأول وفي تصيير AJAX، وإلا خرج
        // الجدول فارغاً بعد أول بحث
        $returnsData = $query->paginate($perPage)->withQueryString();

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'data' => view('purchases::purchases.returns.partials.table', compact('returnsData'))->render(),
                'pagination' => view('purchases::purchases.returns.partials.pagination', compact('returnsData'))->render(),
                'total' => $returnsData->total(),
                'current_page' => $returnsData->currentPage(),
                'last_page' => $returnsData->lastPage(),
                'from' => $returnsData->firstItem(),
                'to' => $returnsData->lastItem(),
            ]);
        }

        return $returnsData;
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
           الافتراضيّ وحده: من أقفل سنةً لا يريد مستنداتِها أن تعود بضبط
           التاريخ يدوياً. والفحصُ على تاريخ المستند لا لحظةِ إدخاله. */
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

        return redirect()->route('ReturnsInvoice.index');
    }

    /**
     * لا نموذج فارغ للمرتجع.
     *
     * المرتجع مستندٌ تابع: يعكس أثر فاتورةٍ بعينها في المخزون وفي حساب
     * المورّد، فلا معنى لإنشائه من فراغ ثم اختيار فاتورته من قائمة — كان ذلك
     * يسمح بمرتجعٍ بلا أصل، أو بمرتجعٍ ثانٍ لفاتورةٍ رُدَّت. المدخل الوحيد
     * صار `createFromInvoice()` من شاشة الفاتورة، وزرّ «إضافة جديد» أُزيل من
     * القائمة تبعاً لذلك.
     */
    public function create()
    {
        return redirect()
            ->route('invoicePurchases.index')
            ->with('info', __('purchase_cycle.return.from_invoice_only'));
    }

    /**
     * إنشاء مرتجع من فاتورة موجودة — المدخل الوحيد لشاشة الإنشاء.
     */
    public function createFromInvoice($invoiceId)
    {
        $originalInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($invoiceId);
        $this->authorizeCreatorScope($originalInvoice->created_by, $originalInvoice->employee_id, $originalInvoice->branch_id);

        if ($originalInvoice->type !== 'invoice') {
            return redirect()->back()->with('error', 'يمكن إنشاء مرتجع فقط من الفواتير العادية');
        }

        // الفاتورة الواحدة لا تُردّ مرتين — الرسالة تسمّي المرتجع القائم
        // وتنقل إليه بدل أن تكتفي بالمنع
        if ($existing = $this->existingReturnFor($originalInvoice->id)) {
            return redirect()
                ->route('ReturnsInvoice.show', $existing->id)
                ->with('error', $this->duplicateReturnMessage($originalInvoice, $existing));
        }

        // فرعُ الشريط العلويّ، ومورِّدُ الفاتورة الأصلية يبقى ولو كان على جهةٍ أخرى
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), array_filter([$originalInvoice->supplier_id]))->get();
        $items = Product::all();
        $taxs = TaxSitting::all();
        $user = Auth::user();
        $account_setting = AccountSetting::where('user_id', $user->id)->first();

        // الرقم من «تسلسل أرقام المستندات» — يُعرَض محجوزاً ولا يُثبَّت إلا
        // عند الحفظ، فلا تُستهلك أرقام على شاشةٍ تُفتَح ولا تُحفَظ
        $code = peek_document_number(self::NUMBER_SECTION);

        // الخزائن من «الحسابات المحاسبية»
        $treasuries = $this->treasuryAccounts();

        // خزينة الاسترداد: خزينة الفاتورة الأصلية أولاً — فالمال يعود من حيث
        // خرج — ثم خزينة الموظّف، ثم الخزينة الرئيسية من الإعدادات
        $originalPayment = PaymentsProcess::where('purchases_id', $originalInvoice->id)
            ->where('type', 'supplier payments')
            ->orderBy('created_at', 'desc')
            ->first();

        $originalTreasury = $originalPayment?->treasury_id ? Account::find($originalPayment->treasury_id) : null;

        $MainTreasury = $originalTreasury ?: $this->defaultTreasury();
        $treasury_id = $MainTreasury->id ?? null;
        $branches = \App\Models\Branch::orderBy('name')->get();

        /* الفترة المحاسبية تمنع الحفظ بتاريخ اليوم: يُقال قبل ملء المستند
           كلّه لا بعد الضغط على حفظ — كما في شاشات المبيعات. */
        $periodBlock = $this->accountingPeriodBlock(now());

        return view('purchases::purchases.returns.create', compact('suppliers', 'taxs', 'account_setting', 'items', 'originalInvoice', 'treasuries', 'originalPayment', 'originalTreasury', 'MainTreasury', 'treasury_id', 'code', 'branches', 'periodBlock'));
    }

    /**
     * جلب تفاصيل فاتورة عبر Ajax
     */
    public function getInvoiceDetails($invoiceId)
    {
        try {
            $invoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($invoiceId);
            $this->authorizeCreatorScope($invoice->created_by, $invoice->employee_id, $invoice->branch_id);

            if ($invoice->type !== 'invoice') {
                return response()->json(['error' => 'يمكن إنشاء مرتجع فقط من الفواتير العادية'], 400);
            }

            // نفس قاعدة الشاشة: لا مرتجع ثانٍ لفاتورةٍ رُدَّت — وإلا بنَت
            // الواجهة نموذجاً لمستندٍ سيرفضه الحفظ
            if ($existing = $this->existingReturnFor($invoice->id)) {
                return response()->json([
                    'error' => strip_tags($this->duplicateReturnMessage($invoice, $existing)),
                ], 409);
            }

            return response()->json([
                'success' => true,
                'invoice' => [
                    'id' => $invoice->id,
                    'code' => $invoice->code,
                    'supplier_id' => $invoice->supplier_id,
                    'date' => $invoice->date,
                    'terms' => $invoice->terms,
                    'account_id' => $invoice->account_id,
                    'discount_amount' => $invoice->discount_amount,
                    'discount_type' => $invoice->discount_type,
                    'advance_payment' => $invoice->advance_payment,
                    'payment_type' => $invoice->payment_type,
                    'shipping_cost' => $invoice->shipping_cost,
                    'tax_type' => $invoice->tax_type,
                    'payment_method' => $invoice->payment_method,
                    'reference_number' => $invoice->reference_number,
                    'received_date' => $invoice->received_date,
                    'is_paid' => $invoice->is_paid,
                    'is_received' => $invoice->is_received,
                    'notes' => $invoice->notes,
                    'payment_status' => $invoice->payment_status,
                    'receiving_status' => $invoice->receiving_status,
                    'subtotal' => $invoice->subtotal,
                    'total_discount' => $invoice->total_discount,
                    'total_tax' => $invoice->total_tax,
                    'grand_total' => $invoice->grand_total,
                    'due_value' => $invoice->due_value,
                    // البيانات المحسوبة
                    'supplier' => [
                        'id' => $invoice->supplier->id,
                        'trade_name' => $invoice->supplier->trade_name,
                        'balance' => $invoice->supplier->account ? $invoice->supplier->account->balance : 0,
                    ],
                    // البنود مع البيانات المدخلة
                    'items' => $invoice->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name ?? 'منتج غير معروف',
                            'item' => $item->item,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'discount' => $item->discount,
                            'discount_type' => $item->discount_type,
                            'tax_1' => $item->tax_1,
                            'tax_2' => $item->tax_2,
                            'tax_1_id' => $item->tax_1_id, // ✅ إضافة ID الضريبة 1
                            'tax_2_id' => $item->tax_2_id, // ✅ إضافة ID الضريبة 2
                            'total' => $item->total,
                            'store_house_id' => $item->store_house_id,
                        ];
                    })->toArray(),
                    // ✅ إضافة بيانات الدفع من الفاتورة الأصلية
                    'paid_amount' => $invoice->grand_total - $invoice->due_value, // المبلغ المدفوع فعلياً
                    'advance_payment_from_original' => $invoice->advance_payment ?? 0,
                ],
                // ✅ إضافة بيانات الخزينة من PaymentsProcess
                'payment_info' => $this->getInvoicePaymentInfo($invoiceId),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.ReturnsInvoice', 'getInvoiceDetails');

            return response()->json(['error' => 'الفاتورة غير موجودة'], 404);
        }

    }

    /**
     * ✅ جلب معلومات الدفع والخزينة من الفاتورة
     */
    private function getInvoicePaymentInfo($invoiceId)
    {
        // جلب جميع عمليات الدفع المرتبطة بالفاتورة
        $payments = PaymentsProcess::where('purchases_id', $invoiceId)
            ->where('type', 'supplier payments')
            ->orderBy('created_at', 'asc')
            ->get();

        $paymentInfo = [
            'payments' => [],
            'total_paid' => 0,
            'main_treasury_id' => null,
            'main_treasury_name' => null,
            'advance_treasury_id' => null,
            'advance_treasury_name' => null,
        ];

        foreach ($payments as $payment) {
            $treasury = Account::find($payment->treasury_id);
            $paymentData = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'treasury_id' => $payment->treasury_id,
                'treasury_name' => $treasury ? $treasury->name : 'غير محدد',
                'treasury_balance' => $treasury ? $treasury->balance : 0,
                'payment_date' => $payment->payment_date,
                'payment_method' => $payment->payment_method,
                'notes' => $payment->notes,
            ];
            $paymentInfo['payments'][] = $paymentData;
            $paymentInfo['total_paid'] += $payment->amount;

            // تحديد الخزينة الرئيسية والدفعة المقدمة
            if (strpos($payment->notes, 'دفعة مقدمة') !== false) {
                $paymentInfo['advance_treasury_id'] = $payment->treasury_id;
                $paymentInfo['advance_treasury_name'] = $treasury ? $treasury->name : null;
            } else {
                $paymentInfo['main_treasury_id'] = $payment->treasury_id;
                $paymentInfo['main_treasury_name'] = $treasury ? $treasury->name : null;
            }
        }

        // إذا لم تكن هناك خزينة رئيسية ولكن هناك دفعة مقدمة فقط
        if (! $paymentInfo['main_treasury_id'] && $paymentInfo['advance_treasury_id']) {
            $paymentInfo['main_treasury_id'] = $paymentInfo['advance_treasury_id'];
            $paymentInfo['main_treasury_name'] = $paymentInfo['advance_treasury_name'];
        }

        // ✅ جلب قائمة الخزائن المتاحة بنفس طريقة فاتورة الشراء
        $treasuries = Account::whereIn('parent_id', [
            AccountingSettings::id('bank_accounts_parent'),
            AccountingSettings::id('cash_treasuries_parent'),
        ])->get(['id', 'name', 'balance']);

        $paymentInfo['available_treasuries'] = $treasuries->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'balance' => $t->balance,
            ];
        })->toArray();

        return $paymentInfo;
    }

    public function store(Request $request)
    {
        try {
            /* الضريبة الشاملة على المستند — الخانةُ العلوية `document_tax_id`
               تُفرَض على كلّ بند حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة. */
            PurchaseTaxCalculator::applyComprehensive($request);

            // ** الفاتورة الأصلية — لازمةٌ لا اختيارية **
            //
            // المرتجع يعكس أثر فاتورةٍ بعينها، فبلا مرجعٍ لا يُعرَف ما الذي
            // يعكسه ولا كم يُستردّ منه. والفحص يُعاد هنا لا في الشاشة وحدها،
            // لأن الحفظ يصل من نافذتين مفتوحتين معاً ومن طلبٍ مباشر.
            if (! $request->filled('reference_id')) {
                return redirect()
                    ->route('invoicePurchases.index')
                    ->withInput()
                    ->with('error', __('purchase_cycle.return.from_invoice_only'));
            }

            $originalInvoice = PurchaseInvoice::find($request->reference_id);

            if (! $originalInvoice) {
                return redirect()->back()->withInput()->with('error', 'الفاتورة المرجعية غير موجودة');
            }

            if ($originalInvoice->type !== 'invoice') {
                return redirect()->back()->withInput()->with('error', 'يمكن إنشاء مرتجع فقط للفواتير العادية');
            }

            // خيار «السماح بأكثر من مرتجع للفاتورة الواحدة» يرفع هذا المنع،
            // فيصير الإرجاع الجزئي على دفعات ممكناً
            if (! PurchaseSettings::enabled('allow_multiple_returns')
                && ($existing = $this->existingReturnFor($originalInvoice->id))) {
                return redirect()
                    ->route('ReturnsInvoice.show', $existing->id)
                    ->with('error', $this->duplicateReturnMessage($originalInvoice, $existing));
            }

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

            // خيار «اعتبار المرتجع مسلَّماً للمورد افتراضياً»: يُغني الموظّف
            // عن تأشير الاستلام، وبه تُخرَج الأصناف من المخزن عند الحفظ
            $return_received = $request->has('is_received')
                || PurchaseSettings::enabled('return_default_received');

            // ** الخطوة الأولى: إنشاء كود للفاتورة **
            // ** الرقم من إعدادات «تسلسل أرقام المستندات» **
            // ** الفترة المحاسبية ** — المرتجع يكتب قيداً محاسبياً،
            // فيخضع لقفل الفترة كما تخضع فاتورة الشراء
            if ($blocked = $this->accountingPeriodBlock($request->date)) {

                return redirect()->back()->withInput()->with('error', $blocked);
            }

            $user = Auth::user();

            /* ** تحميل بيانات البنود دفعةً واحدة، وفحص صلاحية المستودع **
             *
             * نفس خطوات `InvoicesPurchaseController::store()` — انظر
             * `PreparesPurchaseDocumentLines`. وكلاهما قبل حجز الرقم وفتح
             * المعاملة: مستندٌ يُرَدّ بعد أن أخذ رقمه يترك ثغرةً في التسلسل.
             */
            $lineData = $this->preloadLineData($request);
            $this->assertStorehouseAccess($lineData['storeHouses'], $request, $user);
            $defaultStoreHouse = $this->resolveDefaultStoreHouse($request, $user);

            // مستودعات الفاتورة الأصلية — البضاعة تخرج من حيث دخلت
            $originalStoreHouses = $this->originalLineStoreHouses($originalInvoice);

            $code = $this->resolveCode($request->code);

            DB::beginTransaction(); // بدء المعاملة

            // ** تجهيز المتغيرات الرئيسية لحساب الفاتورة **
            $total_amount = 0; // إجمالي المبلغ قبل الخصومات
            $total_discount = 0; // إجمالي الخصومات على البنود
            $items_data = []; // تجميع بيانات البنود
            $lineNets = []; // صافي كلّ بندٍ بعد خصمه الخاصّ
            $lineTaxes = []; // ضرائب كلّ بندٍ: المعرّفات والنسب
            $documentTaxIds = []; // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن

            // ** الخطوة الثانية: معالجة البنود (items) **
            if ($request->has('items') && count($request->items)) {
                foreach ($request->items as $item) {
                    // جلب المنتج من الدفعة المحمَّلة مسبقاً
                    $product = $this->resolveLineProduct($lineData['products'], $item);

                    // مستودع البند — منه تُخرَج الكمية في إذن الإخراج
                    $store_house_id = $this->resolveLineStoreHouse(
                        $item,
                        $lineData['storeHouses'],
                        $defaultStoreHouse,
                        $originalStoreHouses
                    );

                    // حساب تفاصيل الكمية والأسعار
                    $quantity = floatval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $item_total = $quantity * $unit_price;

                    // حساب الخصم للبند
                    $item_discount = 0; // قيمة الخصم المبدئية
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        if (isset($item['discount_type']) && $item['discount_type'] === 'percentage') {
                            $item_discount = ($item_total * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }

                    /* صافي البند وضرائبه — وعاءُ الضريبة يُحسب بعد الحلقة
                       لأنه صافي البند ناقصاً حصّته من الخصم العامّ. وكانت
                       حلقةُ الضريبة أدناه تمرّر الخصمَ `0.0`: أي تحسب
                       الضريبةَ على السعر كاملاً وتتجاهل خصمَ البند تماماً،
                       فتخرج ضريبةٌ أكبر من المستحقّ.
                       انظر PurchaseDocumentTotals::compute(). */
                    $lineNets[] = max(0.0, round($item_total - $item_discount, 2));
                    $lineTaxes[] = [
                        'ids' => [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null],
                        'rates' => [floatval($item['tax_1'] ?? 0), floatval($item['tax_2'] ?? 0)],
                    ];
                    $documentTaxIds[] = $item['tax_1_id'] ?? null;
                    $documentTaxIds[] = $item['tax_2_id'] ?? null;

                    // تحديث الإجماليات
                    $total_amount += $item_total;
                    $total_discount += $item_discount;

                    // تجهيز بيانات البند
                    // ✅ وحدة البند تُرافقه من الفاتورة الأصلية (`product_unit_id`
                    // مُمرَّر من create.blade.php)، ومنها معامل التحويل والكمية
                    // بالوحدة الأساسية — بها يُخصَم من المستودع لاحقاً كما دخل
                    // بالضبط، بدل خصم الكمية كما أُدخِلت بوحدتها الظاهرة فقط
                    $items_data[] = ProductUnits::fieldsFor($item, $quantity) + [
                        'purchase_invoice_id' => null, // سيتم تعيينه لاحقًا بعد إنشاء الفاتورة
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount' => $item_discount, // تخزين الخصم للبند
                        'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                        'tax_1' => floatval($item['tax_1'] ?? 0),
                        'tax_1_id' => $item['tax_1_id'] ?? null,
                        'tax_2' => floatval($item['tax_2'] ?? 0),
                        'tax_2_id' => $item['tax_2_id'] ?? null,
                        'total' => $item_total - $item_discount,
                        // البند يحمل مستودعه كما في فاتورة الشراء — كان يُحفَظ
                        // فارغاً، فإذن الإخراج يضع الجميع في مستودعٍ واحد
                        'store_house_id' => $store_house_id,
                    ];
                }
            }

            // ** الخطوة الثالثة: حساب الخصم الإضافي للفاتورة ككل **
            // «تبويب التسوية» هو ما يحمل الخصم العامّ والشحن، وإطفاء الإعداد
            // يُخفيه من النموذج — فالخادم يتجاهل الحقلين أيضاً ولا يكتفي
            // بغيابهما من الطلب: طلبٌ قديم أو مُلفَّق يظلّ يحملهما، فيُطبَّق
            // خصمٌ أَقفلَه المسؤول في الإعدادات. (نفس نمط InvoicesPurchaseController)
            $enableSettlementSetting = PurchaseSettings::enabled('enable_settlement');

            $totals = PurchaseDocumentTotals::compute(
                $request, $lineNets, $lineTaxes, $enableSettlementSetting, $documentTaxIds
            );

            $invoice_discount = $totals['invoice_discount'];
            $total_tax = $totals['item_tax'];
            $included_item_tax = $totals['included_item_tax'];
            $shipping_cost = $totals['shipping_cost'];
            $shipping_tax = $totals['shipping_tax'];

            // الأسعار أُدخلت شاملةً المتضمَّنة، فتُطرح من المجموع قبل جمع الضريبة إليه
            $total_amount = round($total_amount - $included_item_tax, 2);

            // الخصومات الإجمالية
            $final_total_discount = $total_discount + $invoice_discount;

            // حساب المبلغ بعد الخصم
            $amount_after_discount = round($total_amount - $final_total_discount, 2);

            // ** الحساب النهائي للمجموع الكلي **
            // مقرَّبٌ قبل المقارنات: استردادٌ كامل كان يُقرأ «جزئياً» لأنّ
            // `2770.12 >= 2770.1200000001` كاذبة
            $total_with_tax = round($amount_after_discount + $total_tax + $shipping_cost + $shipping_tax, 2);

            // *** تحديد حالات الدفع والاستلام (نفس منطق الفاتورة العادية) ***
            $paid_amount = 0;
            $advance_payment = 0; // الدفعة المقدمة المنفصلة
            $payment_status = 'unpaid'; // الحالة الافتراضية للدفع

            // ✅ إذا كانت هناك فاتورة أصلية مدفوعة أو مدفوعة جزئياً، يتم استرداد المبلغ المدفوع منها
            $original_paid_amount = 0;
            $refund_amount = 0;

            // ✅ أولاً: نتحقق من وجود refund_amount من النموذج (الأولوية له)
            if ($request->has('refund_amount') && floatval($request->refund_amount) > 0) {
                $refund_amount = floatval($request->refund_amount);
                Log::info('استخدام refund_amount من النموذج', ['refund_amount' => $refund_amount]);
            }
            // ✅ ثانياً: إذا لم يوجد، نحسبه من الفاتورة الأصلية باستخدام advance_payment
            elseif ($originalInvoice) {
                // ✅ استخدام advance_payment مباشرة (المبلغ المدفوع الفعلي عند إنشاء الفاتورة)
                $original_advance_payment = floatval($originalInvoice->advance_payment ?? 0);

                // حساب المبلغ المدفوع من due_value كاحتياط
                $calculated_paid = $originalInvoice->grand_total - ($originalInvoice->due_value ?? 0);

                // ✅ إذا كانت الفاتورة "مدفوعة" لكن advance_payment أقل من الإجمالي
                // فهذا يعني أن الدفعة الفعلية هي advance_payment فقط
                if ($originalInvoice->payment_status === 'paid' && $original_advance_payment > 0 && $original_advance_payment < $originalInvoice->grand_total) {
                    $original_paid_amount = $original_advance_payment;
                    Log::info('استخدام advance_payment لأن الفاتورة paid لكن الدفعة المقدمة أقل من الإجمالي', [
                        'advance_payment' => $original_advance_payment,
                        'grand_total' => $originalInvoice->grand_total,
                    ]);
                } else {
                    // استخدام الحساب العادي
                    $original_paid_amount = $calculated_paid;
                }

                // حساب نسبة المرتجع من الفاتورة الأصلية
                $return_ratio = $total_with_tax / $originalInvoice->grand_total;

                // حساب المبلغ المستحق للاسترداد بناءً على نسبة المرتجع
                $refund_amount = min($original_paid_amount * $return_ratio, $total_with_tax);

                Log::info('حساب مبلغ الاسترداد للمرتجع', [
                    'original_grand_total' => $originalInvoice->grand_total,
                    'original_due_value' => $originalInvoice->due_value,
                    'original_advance_payment' => $original_advance_payment,
                    'original_paid_amount' => $original_paid_amount,
                    'original_payment_status' => $originalInvoice->payment_status,
                    'return_total' => $total_with_tax,
                    'return_ratio' => $return_ratio,
                    'refund_amount' => $refund_amount,
                ]);
            }

            // فحص إذا كان هناك دفع كامل (من checkbox)
            if ($request->has('is_paid') && $request->is_paid == '1') {
                $paid_amount = $total_with_tax; // دفع كامل
                $advance_payment = $total_with_tax; // نفس المبلغ للدفعة المقدمة
                $payment_status = 'paid'; // مدفوعة بالكامل
            }
            // ✅ إذا كانت الفاتورة الأصلية مدفوعة أو مدفوعة جزئياً، يتم استرداد نسبة المدفوع فقط
            elseif ($originalInvoice && in_array($originalInvoice->payment_status, ['paid', 'partially_paid']) && isset($refund_amount) && $refund_amount > 0) {
                // استرداد المبلغ المدفوع فعلياً × نسبة المرتجع
                // مثال: فاتورة 1000، دفعة مقدمة 500، مرتجع كامل = استرداد 500 فقط
                $paid_amount = round($refund_amount, 2);
                $advance_payment = $paid_amount;

                // تحديد حالة الدفع بناءً على نسبة الاسترداد للمرتجع
                if ($paid_amount >= $total_with_tax) {
                    $payment_status = 'paid'; // المرتجع مدفوع بالكامل
                } elseif ($paid_amount > 0) {
                    $payment_status = 'partially_paid'; // المرتجع مدفوع جزئياً
                } else {
                    $payment_status = 'unpaid';
                }

                Log::info('حالة دفع المرتجع', [
                    'total_with_tax' => $total_with_tax,
                    'refund_amount' => $refund_amount,
                    'paid_amount' => $paid_amount,
                    'payment_status' => $payment_status,
                ]);
            }
            // أو إذا كان هناك دفعة مقدمة من حقل advance_payment
            elseif ($request->has('advance_payment') && floatval($request->advance_payment) > 0) {
                $advance_payment = floatval($request->advance_payment);
                $paid_amount = $advance_payment;

                // التحقق من نوع الدفعة المقدمة (مبلغ أو نسبة مئوية)
                if ($request->has('advance_payment_type') && $request->advance_payment_type === 'percentage') {
                    $advance_payment = ($total_with_tax * $advance_payment) / 100;
                    $paid_amount = $advance_payment;
                }

                // التأكد من أن المبلغ المدفوع لا يتجاوز المبلغ الإجمالي
                if ($paid_amount >= $total_with_tax) {
                    $paid_amount = $total_with_tax;
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid'; // مدفوعة بالكامل
                } else {
                    $payment_status = 'partially_paid'; // مدفوعة جزئياً
                }
            }
            // أو إذا كان هناك مبلغ مدفوع من الحقل العام paid_amount
            elseif ($request->has('paid_amount') && floatval($request->paid_amount) > 0) {
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

            $due_value = $total_with_tax - $paid_amount;

            // ✅ تحديد الخزينة المستهدفة - الأولوية للخزينة المختارة من المستخدم
            $mainTreasuryAccount = null;
            $treasury_id = null;
            $user = Auth::user();

            // ✅ أولاً: التحقق من الخزينة المختارة من المستخدم
            if ($request->has('refund_treasury_id') && ! empty($request->refund_treasury_id)) {
                $treasury_id = $request->refund_treasury_id;
                $mainTreasuryAccount = Account::find($treasury_id);
            }
            // ✅ ثانياً: التحقق من الخزينة المرسلة في treasury_id
            elseif ($request->has('treasury_id') && ! empty($request->treasury_id)) {
                $treasury_id = $request->treasury_id;
                $mainTreasuryAccount = Account::find($treasury_id);
            }
            // ✅ ثالثاً: استخدام خزينة الموظف الافتراضية
            elseif ($user && $user->employee_id) {
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

            // إذا لم يتم العثور على خزينة، توقف العملية وأظهر خطأ
            if (! $mainTreasuryAccount) {
                throw new \Exception(__('purchase_cycle.messages.account_missing', [
                    'account' => __('purchase_cycle.accounts.main_treasury'),
                ]));
            }

            // ✅ تحديد reference_id بشكل صحيح
            $reference_id = null;
            if ($originalInvoice) {
                // إذا كانت الفاتورة الأصلية موجودة، استخدم ID الخاص بها
                $reference_id = $originalInvoice->id;
            } elseif ($request->filled('reference_id')) {
                // إذا كان reference_id موجود في الطلب وليس فارغ
                $reference_id = $request->reference_id;
            }

            // الفرع: فرع الفاتورة الأصلية إن وُجدت (المرتجع يعكس أثرها)،
            // وإلا فرع المستخدم الحالي — إلا لمن يملك صلاحية اختيار فرع آخر
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'), $originalInvoice->branch_id);

            // ** الخطوة الرابعة: إنشاء مرتجع المشتريات في قاعدة البيانات **
            $purchaseInvoiceReturn = PurchaseInvoice::create([
                'supplier_id' => $request->supplier_id,
                'code' => $code,
                'type' => 'Return', // نوع المرتجع
                'reference_id' => $reference_id, // ✅ سيتم حفظه بشكل صحيح
                'branch_id' => $branchId,
                'date' => $request->date,
                'terms' => $request->terms ?? 0,
                'notes' => ($request->notes ?? '').($originalInvoice ? "\nمرتجع للفاتورة رقم: ".$originalInvoice->code : '')."\nالخزينة المستخدمة: ".$mainTreasuryAccount->name,
                'payment_status' => $payment_status, // ✅ حالة الدفع الجديدة (enum)
                'receiving_status' => $return_received ? 'received' : 'not_received', // ✅ حالة الاستلام
                'created_by' => Auth::id(),
                'account_id' => $request->account_id,
                'discount_amount' => $invoice_discount, // تخزين الخصم الإضافي للفاتورة
                'discount_type' => $request->has('discount_type') ? ($request->discount_type === 'percentage' ? 2 : 1) : 1,
                'advance_payment' => $advance_payment,
                'payment_type' => $request->payment_type ?? 1,
                'shipping_cost' => $shipping_cost,
                'tax_type' => $request->tax_type ?? 1,
                'payment_method' => $request->payment_method ?? $request->advance_payment_method,
                'reference_number' => $request->reference_number ?? $request->advance_reference_number,
                'received_date' => $request->received_date,
                'is_paid' => $payment_status === 'paid', // للتوافق مع النظام القديم
                'is_received' => $return_received,
                'subtotal' => $total_amount,
                'total_discount' => $final_total_discount, // تخزين الخصم الإجمالي
                'total_tax' => $total_tax + $shipping_tax,
                'shipping_tax' => $shipping_tax,      // حصّةُ الشحن محفوظةٌ وحدها لتُرحَّل إلى حسابها // إضافة ضريبة التوصيل إلى مجموع الضرائب
                'grand_total' => $total_with_tax, // المبلغ الإجمالي الكامل
                'due_value' => $due_value, // المبلغ المستحق
            ]);

            // ✅ إضافة تأكيد إضافي للتأكد من الحفظ
            if ($reference_id) {
                Log::info('Reference ID Set Successfully', [
                    'return_id' => $purchaseInvoiceReturn->id,
                    'reference_id' => $reference_id,
                    'original_invoice_code' => $originalInvoice ? $originalInvoice->code : 'N/A',
                ]);
            }

            // ** تحديث نوع الفاتورة الأصلية إذا كانت موجودة **
            if ($originalInvoice) {
                $originalInvoice->update([
                    'type' => 'invoice', // تغيير نوع الفاتورة الأصلية
                ]);
            }

            // ** معالجة الضرائب ** — من الحساب نفسه الذي بُنيت عليه
            // الإجماليات، لا `النسبة × الكمية × السعر` بلا خصم
            PurchaseOutboundStock::writeTaxRows($purchaseInvoiceReturn->id, $totals['tax_rows'], 'InvoiceReturn_purchase', (float) $totals['shipping_tax'], (float) $totals['shipping_cost']);

            // ** الخطوة الخامسة: إنشاء سجلات البنود (items) للفاتورة **
            foreach ($items_data as $item) {
                $item['purchase_invoice_id'] = $purchaseInvoiceReturn->id; // تعيين purchase_invoice_id
                $invoiceItem = InvoiceItem::create($item); // تخزين البند مع purchase_invoice_id

                $invoiceItem->load('product');

                // تسجيل اشعار نظام جديد لكل منتج
                OperationLog::record(
                    'created',
                    self::LOG_MODULE,
                    $purchaseInvoiceReturn,
                    $this->logLine('created', $purchaseInvoiceReturn->code),
                );
            }

            // *** إنشاء عملية الدفع إذا تم دفع مبلغ (في المرتجع هذا يعني أن المورد دفع للشركة) ***
            if ($paid_amount > 0) {
                $payment = PaymentsProcess::create([
                    'purchases_id' => $purchaseInvoiceReturn->id,
                    'supplier_id' => $request->supplier_id,
                    'amount' => $paid_amount,
                    'payment_date' => $request->date ?? now(),
                    'payment_method' => $request->payment_method ?? ($request->advance_payment_method ?? 1),
                    'type' => 'supplier return payment', // نوع دفع المرتجع
                    'payment_status' => 1, // مكتمل
                    'employee_id' => Auth::id(),
                    'treasury_id' => $treasury_id, // ✅ إضافة ID الخزينة المستخدمة
                    'notes' => $payment_status === 'paid' ? 'دفع كامل لمرتجع المشتريات رقم '.$purchaseInvoiceReturn->code.' إلى خزينة '.$mainTreasuryAccount->name : 'دفع جزئي بمبلغ '.number_format($paid_amount, 2).' لمرتجع المشتريات رقم '.$purchaseInvoiceReturn->code.' إلى خزينة '.$mainTreasuryAccount->name,
                ]);
            }

            // ** الخطوة السادسة: القيود المحاسبية وتحديث المخزون **

            // 1. إنشاء قيد المرتجع (دائماً عند الحفظ - لإثبات تقليل المديونية)
            $this->createReturnAccountingEntriesFixed($purchaseInvoiceReturn, $supplier, $paid_amount, $mainTreasuryAccount);

            // 2. تحديث المخزون وقيد الإخراج — فوراً إن أُشِّر «تم الاستلام»
            // (أو فعَّلَ الإعداد ذلك افتراضياً)، أي أن ارتجاعاً فعلياً تمّ عند
            // الحفظ نفسه؛ وإلا يبقى الإذن «معلَّقاً» حتى تأكيدٍ لاحق من شاشة
            // العرض عبر markAsApproved()
            if ($return_received || $purchaseInvoiceReturn->receiving_status === 'received') {
                $this->approveWarehousePermitForReturn($purchaseInvoiceReturn);
            } else {
                $this->createWarehousePermitForReturn($purchaseInvoiceReturn, $request, $defaultStoreHouse->id ?? null);
            }

            // ** معالجة المرفقات (attachments) إذا وجدت **
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $purchaseInvoiceReturn->attachments = $filename;
                    $purchaseInvoiceReturn->save();
                }
            }

            // ✅ Debug: تحقق من حفظ reference_id
            Log::info('Purchase Return Created', [
                'return_id' => $purchaseInvoiceReturn->id,
                'return_code' => $purchaseInvoiceReturn->code,
                'reference_id' => $purchaseInvoiceReturn->reference_id,
                'original_invoice' => $originalInvoice ? $originalInvoice->code : 'none',
            ]);

            DB::commit(); // تأكيد التغييرات

            // إنشاء إشعار للنظام
            $user = Auth::user();
            notifications::create([
                'type' => 'purchase_return_creation',
                'title' => $user->name.' أنشأ مرتجع شراء جديد',
                'description' => 'تم إنشاء مرتجع الشراء رقم '.$purchaseInvoiceReturn->code.' للمورد '.($supplier->trade_name ?? '').' بقيمة '.number_format($purchaseInvoiceReturn->grand_total, 2).' ر.س'.($originalInvoice ? ' مرتبط بالفاتورة رقم '.$originalInvoice->code : '').' من خزينة '.$mainTreasuryAccount->name.($paid_amount > 0 ? ' - تم إيداع '.number_format($paid_amount, 2).' ر.س' : '').' - حالة الدفع: '.$this->getPaymentStatusText($payment_status),
            ]);

            // *** تحسين رسائل النجاح ***
            $message = $this->generateReturnSuccessMessage($payment_status, $paid_amount, $due_value, $mainTreasuryAccount->name, $originalInvoice);

            return redirect()->route('ReturnsInvoice.show', $purchaseInvoiceReturn->id)->with('success', $message);
        } catch (\Exception $e) {
            DB::rollback(); // تراجع عن التغييرات في حالة حدوث خطأ
            Log::error('خطأ في إنشاء مرتجع المشتريات: '.$e->getMessage()); // تسجيل الخطأ

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء حفظ مرتجع المشتريات: '.$e->getMessage());
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
                return 'غير محدد';
        }
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

    /**
     * دالة مساعدة محدثة لتوليد رسالة النجاح بناءً على جميع الإعدادات
     */
    private function generateEnhancedSuccessMessage($payment_status, $receiving_status, $warehouse_number, $paid_amount, $due_value, $treasury_name, $settings = [])
    {
        $payment_text = $this->getPaymentStatusText($payment_status);
        $receiving_text = $this->getReceivingStatusText($receiving_status);

        $message = 'تم إنشاء مرتجع المشتريات بنجاح';

        if ($warehouse_number) {
            $message .= ' (رقم الإذن المخزني: '.$warehouse_number.')';
        }

        $message .= ' من خزينة '.$treasury_name;

        // إضافة معلومات الإعدادات المفعلة
        $activeSettings = [];
        if ($settings['auto_paid']) {
            $activeSettings[] = 'دفع تلقائي';
        }
        if ($settings['auto_received']) {
            $activeSettings[] = 'استلام تلقائي';
        }

        if (! empty($activeSettings)) {
            $message .= ' ('.implode(', ', $activeSettings).')';
        }

        switch ($payment_status) {
            case 'paid':
                $message .= ' - تم تسجيل الإيداع الكامل';
                break;
            case 'partially_paid':
                $message .= ' - تم تسجيل إيداع جزئي بمبلغ '.number_format($paid_amount, 2).' ريال. المتبقي: '.number_format($due_value, 2).' ريال';
                break;
            case 'unpaid':
                $message .= ' - بدون إيداع';
                break;
        }

        // إضافة معلومات الاستلام
        switch ($receiving_status) {
            case 'received':
                $message .= ' | تم استلام المرتجع بالكامل';
                break;
            case 'not_received':
                $message .= ' | في انتظار استلام المرتجع';
                break;
            case 'partial':
                $message .= ' | تم استلام المرتجع جزئياً';
                break;
        }

        $message .= ' | حالة الدفع: '.$payment_text.' | حالة الاستلام: '.$receiving_text;

        return $message;
    }

    /**
     * دالة مساعدة لتوليد رسالة النجاح للمرتجع بناءً على الحالات
     */
    private function generateReturnSuccessMessage($payment_status, $paid_amount, $due_value, $treasury_name, $originalInvoice)
    {
        $payment_text = $this->getPaymentStatusText($payment_status);

        $message = 'تم إنشاء مرتجع المشتريات بنجاح من خزينة '.$treasury_name;

        if ($originalInvoice) {
            $message .= ' وتم تحديث نوع الفاتورة الأصلية رقم '.$originalInvoice->code;
        }

        switch ($payment_status) {
            case 'paid':
                $message .= ' - تم تسجيل الإيداع الكامل في الخزينة';
                break;
            case 'partially_paid':
                $message .= ' - تم تسجيل إيداع جزئي بمبلغ '.number_format($paid_amount).' ريال في الخزينة. المتبقي: '.number_format($due_value).' ريال';
                break;
            case 'unpaid':
                $message .= ' - بدون إيداع في الخزينة';
                break;
        }

        $message .= ' | حالة الدفع: '.$payment_text;

        return $message;
    }

    /**
     * لا تعديل بعد الإنشاء.
     *
     * المرتجع يكتب قيداً محاسبياً ويحرّك المخزون لحظةَ حفظه، فتعديله بعد ذلك
     * يترك القيدَ والحركةَ على حالهما ويخالف بينهما وبين المستند. الفرق
     * يُعالَج بإشعارٍ مدين — مستندٍ جديدٍ له قيده.
     *
     * **كان النداء بلا `return`** فيسقط في `null`، ولارافيل يترجمه صفحةً
     * بيضاء: يضغط المستخدم «تعديل» فلا يرى رسالةً ولا صفحة.
     */
    public function edit($id)
    {
        return redirect()
            ->route('ReturnsInvoice.show', $id)
            ->with('error', __('purchase_cycle.return.no_edit'));
    }

    public function update(Request $request, $id)
    {
        try {
            // الضريبة الشاملة — كما في store()
            PurchaseTaxCalculator::applyComprehensive($request);

            // ** الفترة المحاسبية ** — التعديل يعيد كتابة القيد
            if ($blocked = $this->accountingPeriodBlock($request->date)) {
                return redirect()->back()->withInput()->with('error', $blocked);
            }

            DB::beginTransaction();

            $purchaseInvoiceReturn = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseInvoiceReturn->created_by, $purchaseInvoiceReturn->employee_id, $purchaseInvoiceReturn->branch_id);

            // التحقق من حالة التسليم - لا تسمح بالتعديل إذا كانت مستلمة
            if ($purchaseInvoiceReturn->receiving_status === 'received') {
                throw new \Exception('لا يمكن تعديل مرتجع مشتريات مستلم بالفعل');
            }

            // ** التحقق من وجود الفاتورة الأصلية إذا تم تمرير reference_id **
            $originalInvoice = null;
            if ($request->has('reference_id') && $request->reference_id) {
                $originalInvoice = PurchaseInvoice::find($request->reference_id);
                if (! $originalInvoice) {
                    return redirect()->back()->withInput()->with('error', 'الفاتورة المرجعية غير موجودة');
                }
                if ($originalInvoice->type !== 'invoice') {
                    return redirect()->back()->withInput()->with('error', 'يمكن إنشاء مرتجع فقط للفواتير العادية');
                }
            }

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

            // خيار «اعتبار المرتجع مسلَّماً للمورد افتراضياً»: يُغني الموظّف
            // عن تأشير الاستلام، وبه تُخرَج الأصناف من المخزن عند الحفظ
            $return_received = $request->has('is_received')
                || PurchaseSettings::enabled('return_default_received');

            // ** التحقق من عدم تكرار الكود إذا تم تغييره **
            $code = $request->code;
            if ($code && $code !== $purchaseInvoiceReturn->code) {
                $existingCode = PurchaseInvoice::where('code', $code)->exists();
                if ($existingCode) {
                    return redirect()->back()->withInput()->with('error', 'رقم الفاتورة موجود مسبقاً، الرجاء استخدام رقم آخر');
                }
            } else {
                $code = $purchaseInvoiceReturn->code;
            }

            // ** تجهيز المتغيرات الرئيسية لحساب الفاتورة **
            $total_amount = 0; // إجمالي المبلغ قبل الخصومات
            $total_discount = 0; // إجمالي الخصومات على البنود
            $items_data = []; // تجميع بيانات البنود
            $lineNets = []; // صافي كلّ بندٍ بعد خصمه الخاصّ
            $lineTaxes = []; // ضرائب كلّ بندٍ: المعرّفات والنسب
            $documentTaxIds = []; // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن

            // ** معالجة البنود (items) **
            if ($request->has('items') && count($request->items)) {
                foreach ($request->items as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    // حساب تفاصيل الكمية والأسعار
                    $quantity = floatval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $item_total = $quantity * $unit_price;

                    // حساب الخصم للبند
                    $item_discount = 0;
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        if (isset($item['discount_type']) && $item['discount_type'] === 'percentage') {
                            $item_discount = ($item_total * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }

                    /* صافي البند وضرائبه — وعاءُ الضريبة يُحسب بعد الحلقة
                       لأنه صافي البند ناقصاً حصّته من الخصم العامّ. وكانت
                       حلقةُ الضريبة أدناه تمرّر الخصمَ `0.0`: أي تحسب
                       الضريبةَ على السعر كاملاً وتتجاهل خصمَ البند تماماً،
                       فتخرج ضريبةٌ أكبر من المستحقّ.
                       انظر PurchaseDocumentTotals::compute(). */
                    $lineNets[] = max(0.0, round($item_total - $item_discount, 2));
                    $lineTaxes[] = [
                        'ids' => [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null],
                        'rates' => [floatval($item['tax_1'] ?? 0), floatval($item['tax_2'] ?? 0)],
                    ];
                    $documentTaxIds[] = $item['tax_1_id'] ?? null;
                    $documentTaxIds[] = $item['tax_2_id'] ?? null;

                    // تحديث الإجماليات
                    $total_amount += $item_total;
                    $total_discount += $item_discount;

                    // تجهيز بيانات البند — وحدته ومعامل تحويلها كما في الإنشاء،
                    // فيبقى خصم المستودع عند الاعتماد متّسقاً مع ما عُدِّل إليه
                    $items_data[] = ProductUnits::fieldsFor($item, $quantity) + [
                        'purchase_invoice_id' => $purchaseInvoiceReturn->id,
                        'product_id' => $item['product_id'],
                        'item' => $product->name ?? 'المنتج '.$item['product_id'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount' => $item_discount,
                        'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                        'tax_1' => floatval($item['tax_1'] ?? 0),
                        'tax_1_id' => $item['tax_1_id'] ?? null,
                        'tax_2' => floatval($item['tax_2'] ?? 0),
                        'tax_2_id' => $item['tax_2_id'] ?? null,
                        'total' => $item_total - $item_discount,
                    ];
                }
            }

            // ** حساب الخصم الإضافي للفاتورة ككل **
            // «تبويب التسوية» هو ما يحمل الخصم العامّ والشحن، وإطفاء الإعداد
            // يُخفيه من النموذج — فالخادم يتجاهل الحقلين أيضاً ولا يكتفي
            // بغيابهما من الطلب: طلبٌ قديم أو مُلفَّق يظلّ يحملهما، فيُطبَّق
            // خصمٌ أَقفلَه المسؤول في الإعدادات. (نفس نمط InvoicesPurchaseController)
            $enableSettlementSetting = PurchaseSettings::enabled('enable_settlement');

            $totals = PurchaseDocumentTotals::compute(
                $request, $lineNets, $lineTaxes, $enableSettlementSetting, $documentTaxIds
            );

            $invoice_discount = $totals['invoice_discount'];
            $total_tax = $totals['item_tax'];
            $included_item_tax = $totals['included_item_tax'];
            $shipping_cost = $totals['shipping_cost'];
            $shipping_tax = $totals['shipping_tax'];

            // الأسعار أُدخلت شاملةً المتضمَّنة، فتُطرح من المجموع قبل جمع الضريبة إليه
            $total_amount = round($total_amount - $included_item_tax, 2);

            // الخصومات الإجمالية
            $final_total_discount = $total_discount + $invoice_discount;

            // حساب المبلغ بعد الخصم
            $amount_after_discount = round($total_amount - $final_total_discount, 2);

            // ** الحساب النهائي للمجموع الكلي **
            // مقرَّبٌ قبل المقارنات: استردادٌ كامل كان يُقرأ «جزئياً» لأنّ
            // `2770.12 >= 2770.1200000001` كاذبة
            $total_with_tax = round($amount_after_discount + $total_tax + $shipping_cost + $shipping_tax, 2);

            // *** تحديد حالات الدفع والاستلام ***
            $paid_amount = 0;
            $advance_payment = 0;
            $payment_status = 'unpaid';

            if ($request->has('is_paid') && $request->is_paid == '1') {
                $paid_amount = $total_with_tax;
                $advance_payment = $total_with_tax;
                $payment_status = 'paid';
            } elseif ($request->has('advance_payment') && floatval($request->advance_payment) > 0) {
                $advance_payment = floatval($request->advance_payment);
                $paid_amount = $advance_payment;

                if ($request->has('advance_payment_type') && $request->advance_payment_type === 'percentage') {
                    $advance_payment = ($total_with_tax * $advance_payment) / 100;
                    $paid_amount = $advance_payment;
                }

                if ($paid_amount >= $total_with_tax) {
                    $paid_amount = $total_with_tax;
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid';
                } else {
                    $payment_status = 'partially_paid';
                }
            } elseif ($request->has('paid_amount') && floatval($request->paid_amount) > 0) {
                $paid_amount = floatval($request->paid_amount);
                $advance_payment = $paid_amount;

                if ($paid_amount >= $total_with_tax) {
                    $paid_amount = $total_with_tax;
                    $advance_payment = $total_with_tax;
                    $payment_status = 'paid';
                } else {
                    $payment_status = 'partially_paid';
                }
            }

            $due_value = $total_with_tax - $paid_amount;

            // ✅ تحديد الخزينة المستهدفة بناءً على الموظف
            $mainTreasuryAccount = null;
            $treasury_id = null;
            $user = Auth::user();

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

            if (! $mainTreasuryAccount) {
                throw new \Exception(__('purchase_cycle.messages.account_missing', [
                    'account' => __('purchase_cycle.accounts.main_treasury'),
                ]));
            }

            // ✅ تحديد reference_id بشكل صحيح
            $reference_id = null;
            if ($originalInvoice) {
                $reference_id = $originalInvoice->id;
            } elseif ($request->filled('reference_id')) {
                $reference_id = $request->reference_id;
            }

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحةً ويملك
            // صلاحية ذلك — نفس نمط store()
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'), $purchaseInvoiceReturn->branch_id);

            // ** تحديث بيانات المرتجع **
            $purchaseInvoiceReturn->update([
                'supplier_id' => $request->supplier_id,
                'code' => $code,
                'reference_id' => $reference_id,
                'branch_id' => $branchId,
                'date' => $request->date,
                'terms' => $request->terms ?? 0,
                'notes' => ($request->notes ?? '').($originalInvoice ? "\nمرتجع للفاتورة رقم: ".$originalInvoice->code : '')."\nالخزينة المستخدمة: ".$mainTreasuryAccount->name,
                'payment_status' => $payment_status,
                'receiving_status' => $return_received ? 'received' : 'not_received',
                'account_id' => $request->account_id,
                'discount_amount' => $invoice_discount,
                'discount_type' => $request->has('discount_type') ? ($request->discount_type === 'percentage' ? 2 : 1) : 1,
                'advance_payment' => $advance_payment,
                'payment_type' => $request->payment_type ?? 1,
                'shipping_cost' => $shipping_cost,
                'tax_type' => $request->tax_type ?? 1,
                'payment_method' => $request->payment_method ?? $request->advance_payment_method,
                'reference_number' => $request->reference_number ?? $request->advance_reference_number,
                'received_date' => $request->received_date,
                'is_paid' => $payment_status === 'paid',
                'is_received' => $return_received,
                'subtotal' => $total_amount,
                'total_discount' => $final_total_discount,
                'total_tax' => $total_tax + $shipping_tax,
                'shipping_tax' => $shipping_tax,      // حصّةُ الشحن محفوظةٌ وحدها لتُرحَّل إلى حسابها
                'grand_total' => $total_with_tax,
                'due_value' => $due_value,
            ]);

            // ** حذف البنود القديمة وإنشاء البنود الجديدة **
            $purchaseInvoiceReturn->invoiceItems()->delete();
            foreach ($items_data as $item) {
                InvoiceItem::create($item);
            }

            // ** معالجة الضرائب **
            TaxInvoice::where('invoice_id', $purchaseInvoiceReturn->id)->where('type_invoice', 'InvoiceReturn_purchase')->delete();

            // من الحساب نفسه الذي بُنيت عليه الإجماليات — انظر `store()`
            PurchaseOutboundStock::writeTaxRows($purchaseInvoiceReturn->id, $totals['tax_rows'], 'InvoiceReturn_purchase', (float) $totals['shipping_tax'], (float) $totals['shipping_cost']);

            // ** معالجة المرفقات **
            if ($request->hasFile('attachments')) {
                if ($purchaseInvoiceReturn->attachments) {
                    $oldFile = public_path('assets/uploads/').$purchaseInvoiceReturn->attachments;
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $purchaseInvoiceReturn->attachments = $filename;
                    $purchaseInvoiceReturn->save();
                }
            }

            // ** الخطوة السابعة: القيود المحاسبية وتحديث المخزون **

            // 1. تحديث قيد المرتجع (دائماً عند التحديث)
            $this->updateAccountingEntriesFixed($purchaseInvoiceReturn, $old_grand_total, $old_paid_amount, $old_total_tax,
                $total_with_tax, $paid_amount, $total_tax + $shipping_tax, $mainTreasuryAccount);

            // 2. تحديث المخزون وقيد الإخراج — فوراً إن أُشِّر «تم الاستلام»؛
            // وإلا يبقى الإذن محدَّثاً بلا اعتماد (انظر الشرح في store())
            if ($return_received || $purchaseInvoiceReturn->receiving_status === 'received') {
                $this->approveWarehousePermitForReturn($purchaseInvoiceReturn);
            } else {
                $this->updateOrCreateWarehousePermitForReturn($purchaseInvoiceReturn, $request);
            }

            DB::commit();

            return redirect()->route('ReturnsInvoice.show', $purchaseInvoiceReturn->id)->with('success', 'تم تحديث مرتجع المشتريات بنجاح');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('خطأ في تحديث مرتجع المشتريات: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء تحديث مرتجع المشتريات: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $purchaseOrder = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseOrder->created_by);
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $purchaseOrder,
                $this->logLine('deleted', $purchaseOrder->code),
            );
            $purchaseOrder->delete();

            return redirect()->route('ReturnsInvoice.index')->with('success', 'تم حذف مرتجع المشتريات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في حذف مرتجع المشتريات: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء حذف مرتجع المشتريات: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $purchaseInvoiceReturn = PurchaseInvoice::with(['supplier', 'items.product', 'payments', 'creator', 'user', 'originalDocument'])
            ->findOrFail($id);

        /* المحاورُ الثلاثة كما في `markAsApproved()` و`CityNoticesController::show()`
           — كان القياسُ على المنشئ وحدَه، فيُردّ مديرُ فرعٍ بـ403 عن مرتجعٍ
           في فرعه كتبه غيرُه، رغم ظهوره له في الفهرس بنفس النطاق الثلاثي. */
        $this->authorizeCreatorScope(
            $purchaseInvoiceReturn->created_by,
            $purchaseInvoiceReturn->employee_id,
            $purchaseInvoiceReturn->branch_id
        );

        // ضرائب المرتجع تُحفَظ بنوعٍ خاص بها عند الإنشاء، وقراءتها بنوع
        // الفاتورة كانت تُرجع لا شيء فيخرج جدول الضرائب فارغاً دائماً
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)
            ->whereIn('type_invoice', ['InvoiceReturn_purchase', 'purchase'])
            ->get();

        // سجلّ النشاط — بالنوع الذي يكتبه `OperationLog` فعلاً (LOG_MODULE).
        // كانت القراءة بنوعٍ لا يُكتَب (`purchase_return_log`) وبشرط علاقةٍ
        // لا وجود لها، فيخرج الخطّ الزمني فارغاً مهما جرى على المستند.
        $logs = ModelsLog::where('type', self::LOG_MODULE)
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        return view('purchases::purchases.returns.show', [
            'purchaseInvoiceReturn' => $purchaseInvoiceReturn,
            'logs' => $logs,
            'TaxsInvoice' => $TaxsInvoice,
            // القوالب المصمَّمة في «قوالب المستندات» — القائمة تُبنى منها
            // لا من ثلاثة أسماء ثابتة في الشيفرة
            'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
            'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
            // المعاينة والتنزيل يتبعان «طريقة الطباعة» في إعدادات الحساب
            'prefersPdf' => PrintSettings::prefersPdf(),
        ]);
    }

    /**
     * تنزيل المستند PDF بقالبٍ من «قوالب المستندات».
     *
     * `$template` معرّف قالبٍ مضبوط في الإعدادات؛ ومتى لم يكن كذلك يُؤخَذ
     * القالب الافتراضي لهذا النوع، ثم القالب المدمج آخِراً — فلا تبقى
     * الشاشة رهينةَ ثلاثة ملفّات مكتوبة بأسماء ثابتة.
     */
    public function pdf($id, $template = null)
    {
        $purchaseRentalInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($purchaseRentalInvoice->created_by);
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        OperationLog::record(
            'exported',
            self::LOG_MODULE,
            $purchaseRentalInvoice,
            $this->logLine('exported', $purchaseRentalInvoice->code),
        );

        /* القالب المصمَّم في الإعدادات يُعرض صفحةَ طباعة تُحفظ PDF من المتصفّح —
           Dompdf كان يقلب العربية ويُسقط الجدول (انظر ServesDesignedTemplatePage). */
        if ($page = $this->configuredTemplatePage($id, $template, $purchaseRentalInvoice->code)) {
            return $page;
        }

        // القوالب المدمجة — احتياطٌ متى لم يُضبط للنوع قالبٌ في الإعدادات
        $viewName = match ($template) {
            'detailed' => 'purchases::purchases.returns.pdf_detailed',
            'compact' => 'purchases::purchases.returns.pdf_compact',
            default => 'purchases::purchases.returns.pdf',
        };

        if (! view()->exists($viewName)) {
            $viewName = 'purchases::purchases.returns.pdf';
        }

        $pdf = Pdf::loadView($viewName, compact('purchaseRentalInvoice', 'account_setting'));

        return $pdf->download($purchaseRentalInvoice->code.'.pdf');
    }

    /**
     * دالة مساعدة محدثة لتوليد رسالة النجاح للتحديث بناءً على جميع الإعدادات
     */
    private function generateEnhancedUpdateSuccessMessage($payment_status, $receiving_status, $warehouse_number, $payment_difference, $due_value, $treasury_name, $settings = [])
    {
        $payment_text = $this->getPaymentStatusText($payment_status);
        $receiving_text = $this->getReceivingStatusText($receiving_status);

        $message = 'تم تحديث مرتجع المشتريات';
        if ($warehouse_number) {
            $message .= ' (رقم الإذن المخزني: '.$warehouse_number.')';
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

        if (! empty($activeSettings)) {
            $message .= ' ('.implode(', ', $activeSettings).')';
        }

        if ($payment_difference > 0) {
            $message .= ' - تم إضافة إيداع بمبلغ '.number_format($payment_difference, 2).' ريال';
        } elseif ($payment_difference < 0) {
            $message .= ' - تم سحب مبلغ '.number_format(abs($payment_difference), 2).' ريال';
        }

        switch ($payment_status) {
            case 'paid':
                $message .= ' | الدفع: مدفوعة بالكامل';
                break;
            case 'partially_paid':
                $message .= ' | الدفع: مدفوعة جزئياً - المتبقي: '.number_format($due_value, 2).' ريال';
                break;
            case 'unpaid':
                $message .= ' | الدفع: غير مدفوعة';
                break;
        }

        // إضافة معلومات الاستلام
        switch ($receiving_status) {
            case 'received':
                $message .= ' | مستلمة بالكامل';
                break;
            case 'not_received':
                $message .= ' | في انتظار الاستلام';
                break;
            case 'partial':
                $message .= ' | مستلمة جزئياً';
                break;
        }

        $message .= ' | حالة الدفع: '.$payment_text.' | حالة الاستلام: '.$receiving_text;

        return $message;
    }

    /**
     * دالة مساعدة محدثة للقيود المحاسبية - تحديث القيود الموجودة بدلاً من حذفها
     */

    /**
     * دالة لإنشاء إذن المخزن للمرتجع
     */
    /**
     * @param  int|null  $resolvedStoreHouseId  المستودع المشتقّ في `store()`
     *                                          (المُرسَل ← افتراضي الموظّف ← الرئيسي)
     */
    private function createWarehousePermitForReturn($purchaseInvoiceReturn, $request, $resolvedStoreHouseId = null)
    {
        /* ** الإذن للأصناف ذات الرصيد وحدها **
         *
         * كان يُكتب بندٌ لكل صنف بلا استثناء، فإن كان في المرتجع صنفٌ خدمي
         * أو رقمي رمى `StockMovementGuardObserver` وسقط المرتجع كلّه.
         * والخدمة لا تُرَدّ إلى مستودع أصلاً: قيمتها تدخل الدفتر ولا تدخل
         * الرصيد. (نفس علاج `InvoicesPurchaseController::store()`) */
        $stockableItems = $purchaseInvoiceReturn->items
            ->filter(fn ($item) => $item->product_id && StockEligibility::isStockable($item->product_id))
            ->values();

        if ($stockableItems->isEmpty()) {
            return null;
        }

        // إنشاء رقم الإذن المخزني
        $lastPermit = WarehousePermits::orderBy('id', 'desc')->first();
        $nextPermitNumber = $lastPermit ? intval($lastPermit->number) + 1 : 1;
        $permitNumber = str_pad($nextPermitNumber, 6, '0', STR_PAD_LEFT);

        /* المستودع: ما اشتقّه `store()` أوّلاً كي لا يختلف مستودع الإذن عن
           مستودع البنود، ثمّ المُرسَل، ثمّ الرئيسي */
        $storeHouseId = $resolvedStoreHouseId ?: ($request->store_house_id ?? null);

        if (! $storeHouseId) {
            $mainStoreHouse = $this->resolveDefaultStoreHouse($request);

            if (! $mainStoreHouse) {
                throw new \Exception('لا يوجد أي مستودع في النظام، يرجى إضافة مستودع أولاً.');
            }

            $storeHouseId = $mainStoreHouse->id;
        }

        // إنشاء الإذن المخزني للمرتجع (نوع الإذن: إخراج)
        $warehousePermit = WarehousePermits::create([
            'permission_source_id' => PermissionSources::PURCHASE_RETURN, // نوع الإذن: مرتجع مشتريات
            'permission_date' => $request->date ?? now(),
            'sub_account' => $purchaseInvoiceReturn->supplier_id, // المورد
            'number' => $permitNumber,
            'store_houses_id' => $storeHouseId, // المستودع المستهدف إجباري
            'from_store_houses_id' => $storeHouseId, // المستودع المصدر
            'to_store_houses_id' => null, // لا يوجد مستودع مستهدف للمرتجعات
            'grand_total' => $purchaseInvoiceReturn->grand_total,
            'details' => 'إذن إخراج بضاعة لمرتجع شراء رقم: '.$purchaseInvoiceReturn->code,
            'attachments' => null,
            'created_by' => Auth::id(),
            'status' => 'pending', // بانتظار الموافقة
            // النوع لازم: `updateOrCreateWarehousePermitForReturn()` تبحث به
            // عن الإذن، وكان معطَّلاً فيُنشأ الإذن بالقيمة الافتراضية `normal`
            // ولا يجده البحث أبداً — فيتولّد إذنٌ جديدٌ عند كل تعديل
            'reference_type' => 'purchase_return',
            'reference_id' => $purchaseInvoiceReturn->id, // معرف المرتجع
        ]);

        // إنشاء سجلات المنتجات في إذن المخزن — للأصناف ذات الرصيد وحدها،
        // وكلٌّ من مستودعه هو لا من مستودع الإذن وحده
        // بصافي التكلفة وكلٌّ من مستودعه — انظر `PurchaseOutboundStock`
        foreach (PurchaseOutboundStock::permitLines($purchaseInvoiceReturn, (int) $storeHouseId) as $line) {
            WarehousePermitsProducts::create($line + [
                'warehouse_permits_id' => $warehousePermit->id,
                'details' => 'مرتجع شراء رقم: '.$purchaseInvoiceReturn->code,
            ]);
        }

        return $warehousePermit;
    }

    public function markAsApproved($id)
    {
        try {
            DB::beginTransaction();

            $purchaseReturn = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($purchaseReturn->created_by, $purchaseReturn->employee_id, $purchaseReturn->branch_id);

            // مصدر الحقيقة لـ"هل اعتُمِد فعلياً؟" هو حالة الإذن المخزني نفسه،
            // لا عمود receiving_status على الفاتورة (الذي بات مجرّد تدوين
            // معلوماتي عند الحفظ) — وإلا فحصُ قيدٍ محاسبي «سابق» كان يطابق
            // دوماً قيد تخفيض المديونية المنشأ عند كل حفظ، فيُقفَل هذا
            // الإجراء دون أن يعتمد الإذن أو يخصم المخزون أبداً
            $permit = WarehousePermits::where('reference_type', 'purchase_return')
                ->where('reference_id', $purchaseReturn->id)
                ->first();

            if ($permit && $permit->status === 'apporod') {
                return redirect()->back()->with('info', 'المرتجع مستلم بالفعل والقيود منشأة مسبقاً');
            }

            // تحديث الحالة
            $purchaseReturn->update([
                'receiving_status' => 'received',
                'is_received' => 1,
                'received_date' => now(),
            ]);

            // ✅ تحديث المخزون وتفعيل الإذن المخزني وتوليد قيد الإخراج تلقائياً
            // ملاحظة: قيد المرتجع (المورد) تم إنشاؤه مسبقاً عند حفظ الفاتورة
            $this->approveWarehousePermitForReturn($purchaseReturn);

            // تسجيل النشاط
            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $purchaseReturn,
                $this->logLine('status_changed', $purchaseReturn->code),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تم تأكيد المرتجع وتحديث المخزون وتوليد قيد الإخراج بنجاح');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('خطأ في استلام مرتجع المشتريات: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء استلام المرتجع: '.$e->getMessage());
        }
    }

    /**
     * دالة لتحديث أو إنشاء إذن المخزن للمرتجع
     */
    private function updateOrCreateWarehousePermitForReturn($purchaseInvoiceReturn, $request)
    {
        // البحث عن إذن المخزن الموجود للمرتجع
        $existingPermit = WarehousePermits::where('reference_type', 'purchase_return')
            ->where('reference_id', $purchaseInvoiceReturn->id)
            ->first();

        if ($existingPermit) {
            // تحديث الإذن الموجود
            $existingPermit->update([
                'permission_date' => $request->date ?? now(),
                'sub_account' => $purchaseInvoiceReturn->supplier_id,
                'grand_total' => $purchaseInvoiceReturn->grand_total,
                'details' => 'إذن إخراج بضاعة لمرتجع شراء رقم: '.$purchaseInvoiceReturn->code,
                'status' => 'pending',
            ]);

            // تحديث أو إنشاء سجلات المنتجات في إذن المخزن
            // حذف السجلات القديمة
            WarehousePermitsProducts::where('warehouse_permits_id', $existingPermit->id)->delete();

            // إنشاء سجلات جديدة — للأصناف ذات الرصيد وحدها، بصافي التكلفة،
            // وكلٌّ من مستودعه (انظر `PurchaseOutboundStock`)
            foreach (PurchaseOutboundStock::permitLines($purchaseInvoiceReturn, (int) $existingPermit->store_houses_id) as $line) {
                WarehousePermitsProducts::create($line + [
                    'warehouse_permits_id' => $existingPermit->id,
                    'details' => 'مرتجع شراء رقم: '.$purchaseInvoiceReturn->code,
                ]);
            }
        } else {
            // إنشاء إذن مخزن جديد للمرتجع
            $this->createWarehousePermitForReturn($purchaseInvoiceReturn, $request);
        }
    }

    // إضافة ملاحظة للمرتجع
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

            $purchaseReturn = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($purchaseReturn->created_by, $purchaseReturn->employee_id, $purchaseReturn->branch_id);

            // التعامل مع المرفق إذا تم رفعه
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time().'_'.$file->getClientOriginalName();
                $attachmentPath = $file->storeAs('purchase_returns/notes', $fileName, 'public');
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
                'type' => 'purchase_return', // نوع مختلف للمرتجعات
            ]);

            // إرسال إشعار
            notifications::create([
                'user_id' => $purchaseReturn->user_id,
                'receiver_id' => $purchaseReturn->user_id,
                'title' => 'ملاحظة جديدة',
                'description' => 'تم إضافة ملاحظة جديدة لمرتجع الشراء رقم '.$purchaseReturn->code,
            ]);

            // تسجيل النشاط في سجل الأنشطة
            OperationLog::record(
                'note_added',
                self::LOG_MODULE,
                $purchaseReturn,
                __('purchase_cycle.log.note_added', [
                    'document' => $this->documentLabel(),
                    'code' => $purchaseReturn->code,
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
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.ReturnsInvoice', 'addNote');

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
                ->where('type', 'purchase_return')
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
            OperationLog::failure($e, 'Purchases.ReturnsInvoice', 'getNotes');

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

            $returnId = $note->quotation_id;
            $process = $note->process;

            $note->delete();

            // تسجيل النشاط
            OperationLog::record(
                'note_deleted',
                self::LOG_MODULE,
                $returnId,
                __('purchase_cycle.log.note_deleted', [
                    'document' => $this->documentLabel(),
                    'code' => $returnId,
                    'note' => $process,
                ]),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملاحظة بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.ReturnsInvoice', 'deleteNote');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الملاحظة: '.$e->getMessage(),
            ], 500);
        }
    }

    // نسخ المرتجع
    public function copy($id)
    {
        try {
            $originalReturn = PurchaseInvoice::with(['items'])->findOrFail($id);
            $this->authorizeCreatorScope($originalReturn->created_by, $originalReturn->employee_id, $originalReturn->branch_id);

            /* النسخةُ تبقى مرتبطةً بفاتورة الأصل — مرتجعٌ بلا فاتورةٍ لا يعكس
               شيئاً. لكنها بذلك مرتجعٌ **ثانٍ** على الفاتورة نفسها، فتخضع لما
               يخضع له الإنشاء من القائمة: إعدادُ «أكثر من مرتجع» وبقاءُ
               كمّيةٍ قابلة للارتجاع. كانت تُنشأ بلا هذين الفحصين ثم تصطدم
               بحارس التكرار في أوّل إجراءٍ بعدها. */
            $sourceInvoiceId = (int) $originalReturn->reference_id;

            if (! PurchaseSettings::enabled('allow_multiple_returns')
                && $sourceInvoiceId
                && ($existing = $this->existingReturnFor($sourceInvoiceId))) {
                return redirect()->back()->with('error', __('purchase_cycle.return.already_exists', [
                    'invoice' => $originalReturn->originalDocument?->code ?? $sourceInvoiceId,
                    'code' => $existing->code,
                ]));
            }

            if ($sourceInvoiceId && ! \Modules\Purchases\Support\ReturnableInvoiceLines::hasRemainingQuantity($sourceInvoiceId)) {
                return redirect()->back()->with('error', 'لا يمكن نسخ المرتجع — لم تعد في فاتورته الأصلية كمّيةٌ قابلة للارتجاع.');
            }

            // ** الفترة المحاسبية ** — النسخةُ قيدٌ محاسبيٌّ جديد بتاريخ
            // الأصل نفسه (انظر أسفله)، فتُحرَس بنفس ما يحرس store()
            if ($blocked = $this->accountingPeriodBlock($originalReturn->date)) {
                return redirect()->back()->with('error', $blocked);
            }

            DB::beginTransaction();

            // إنشاء مرتجع جديد بنفس البيانات
            $newReturn = $originalReturn->replicate();
            $newReturn->code = $this->generateReturnCode();
            // `status` عمودُ سير عملٍ (enum: disagree/approval/Under Review/…)
            // لا علَمَ دفعٍ رقميّ — الكتابةُ القديمة `status = 0` كانت تكسر
            // بخطأ «Data truncated» لأن العمود لا يقبل قيمةً رقمية، والمرتجع
            // أصلاً لا يُدفع (PurchaseDocumentRules::isPayable). والنسخة
            // مستندٌ جديد فتبدأ سيرَ مراجعتها من أوّله، بنفس ما تفعله
            // `QuotationsController::duplicate` و`ViewPurchasePriceController::duplicate`
            $newReturn->status = 'Under Review';
            // النسخةُ لم تُسلَّم بعد: تسليمُ الأصل أخرج بضاعتَه بإذنٍ مخزنيٍّ
            // خاصٍّ به، ولا إذنَ للنسخة حتى تُعتمَد هي
            $newReturn->is_received = 0;
            // العمود enum('not_received','received','partially_received') —
            // لا 'pending': كانت تكسر بنفس خطأ «Data truncated» أعلاه
            $newReturn->receiving_status = 'not_received';
            $newReturn->received_date = null;
            // ينسخه `replicate()` من الأصل — فيبقى القيدُ الجديد منسوباً إلى
            // فترة الأصل ولو أُقفلت منذ ذلك الحين؛ يُعاد حسابه من `date` عند
            // الإنشاء (BelongsToAccountingPeriod) إن تُرك فارغاً هنا
            $newReturn->accounting_period_id = null;
            $newReturn->created_at = now();
            $newReturn->updated_at = now();
            $newReturn->save();

            // نسخ العناصر
            foreach ($originalReturn->items as $item) {
                $newItem = $item->replicate();
                $newItem->purchase_invoice_id = $newReturn->id;
                $newItem->save();
            }

            /* ** الإذن المخزني (إخراج) — بنفس بنود إذن المرتجع الأصلي، لا
             * بإعادة حسابٍ من `invoice_items.total` — نفس سبب النسخ الحرفي
             * في InvoicesPurchaseController::copy() (تكلفةُ الإذن بعد الضريبة
             * المتضمَّنة والخصم العامّ تختلف عن العمود المخزَّن على البند). **
             */
            $originalPermit = WarehousePermits::where('reference_type', 'purchase_return')
                ->where('reference_id', $originalReturn->id)
                ->first();

            if ($originalPermit) {
                $lastPermit = WarehousePermits::orderBy('id', 'desc')->first();
                $nextPermitNumber = $lastPermit ? intval($lastPermit->number) + 1 : 1;

                $newPermit = $originalPermit->replicate();
                $newPermit->number = str_pad($nextPermitNumber, 6, '0', STR_PAD_LEFT);
                $newPermit->reference_id = $newReturn->id;
                $newPermit->grand_total = $newReturn->grand_total;
                $newPermit->details = 'إذن إخراج بضاعة لمرتجع شراء رقم: '.$newReturn->code;
                // معلَّقٌ بانتظار الاعتماد — كالإذن الذي تُنشئه store() لمرتجعٍ
                // لم يُسلَّم بعد؛ اعتمادُه لاحقاً هو ما يحرّك المخزون فعلياً
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

            // ** القيد المحاسبي — دائماً عند الحفظ لإثبات تقليل المديونية،
            // بلا دفعٍ (paid_amount = 0، فلا يُنشأ قيدُ تحصيل ولا تُمسّ خزينة) —
            // نفس ما يفعله store() في «الخطوة السادسة» **
            $this->createReturnAccountingEntriesFixed($newReturn, Supplier::find($newReturn->supplier_id), 0, null);

            DB::commit();

            // تسجيل النشاط
            OperationLog::record(
                'duplicated',
                self::LOG_MODULE,
                $newReturn,
                $this->logLine('duplicated', $newReturn->code),
            );

            return redirect()->route('ReturnsInvoice.show', $newReturn->id)
                ->with('success', 'تم نسخ المرتجع بنجاح');

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.ReturnsInvoice', 'copy');

            return redirect()->back()->with('error', 'حدث خطأ أثناء نسخ المرتجع: '.$e->getMessage());
        }
    }

    // دالة مساعدة لتوليد رقم المرتجع
    private function generateReturnCode()
    {
        $prefix = 'RET-';
        $year = date('Y');
        $month = date('m');

        $lastReturn = PurchaseInvoice::where('code', 'like', $prefix.$year.$month.'%')
            ->where('type', 'Return')
            ->orderBy('code', 'desc')
            ->first();

        if ($lastReturn) {
            $lastNumber = (int) substr($lastReturn->code, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.$year.$month.str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * سطورُ قيد مرتجع المشتريات — كاتبٌ واحد للإنشاء والتعديل.
     *
     * ══════════════════ عكسُ الفاتورة سطراً بسطر ══════════════════
     *
     * كان المرتجعُ يكتب ثلاثة سطور فقط: المشتريات بالصافي، والضريبةَ
     * مجموعةً، والمورد. فتُدفَن تكلفةُ الشحن داخل ح/ المشتريات ويُبتلَع
     * الخصمُ بلا سطر — فلا يعكس المرتجعُ ما رحّلته الفاتورة، ويمتلئ
     * «شحن مشتريات» و«خصم مكتسب» ولا يفرغان أبداً.
     *
     * ══════════════════ ولماذا كاتبٌ واحد ══════════════════
     *
     * `updateAccountingEntriesFixed()` في هذا المتحكّم كان **لا يفعل
     * شيئاً**: يحسب فروقاً ثمّ يتركها معلَّقةً في تعليقات تقول إنّ
     * الأرصدة «تتحدّث تلقائياً من القيود» — ولا أحد يمسّ القيود. فتعديلُ
     * مرتجعٍ يترك قيده مجمَّداً على أرقامه الأولى إلى الأبد.
     *
     * فصار التعديلُ يحذف السطور ويستدعي هذا الكاتب.
     *
     * @throws \Exception إن كان مفتاحٌ واجبٌ غير مربوط
     */
    private function writeReturnEntryDetails(int $journalEntryId, PurchaseInvoice $purchaseInvoiceReturn, Account $supplierAccount): void
    {
        $mainStore = $this->settingsAccount('purchases');
        $taxAccount = round((float) ($purchaseInvoiceReturn->total_tax ?? 0), 2) > 0
            ? $this->settingsAccount('vat_paid')
            : null;

        /* صافي البضاعة وحدها — كما تحسبه فاتورةُ الشراء بالضبط.
         *
         * كان `grand_total − total_tax` فقط: يدفن تكلفةَ الشحن داخل ح/
         * المشتريات ويبتلع الخصم بلا سطر. فمرتجعُ فاتورةٍ لا يعكس ما
         * رحّلته الفاتورة، ويبقى «شحن مشتريات» و«خصم مكتسب» يمتلئان ولا
         * يفرغان. انظر `InvoicesPurchaseController::writeInvoiceEntryDetails()`. */
        $shippingCost = round((float) ($purchaseInvoiceReturn->shipping_cost ?? 0), 2);
        $totalDiscount = round((float) ($purchaseInvoiceReturn->total_discount ?? 0), 2);
        $totalTax = round((float) ($purchaseInvoiceReturn->total_tax ?? 0), 2);
        $grandTotal = round((float) $purchaseInvoiceReturn->grand_total, 2);

        $subtotal = round($grandTotal - $totalTax - $shippingCost + $totalDiscount, 2);

        // 4.1 حساب المشتريات (دائن) - استرجاع المشتريات
        $purchasesCreditDesc = 'تخفيض المشتريات - مرتجع مشتريات '.$purchaseInvoiceReturn->code;

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntryId,
            'account_id' => $mainStore->id,
            'description' => $purchasesCreditDesc,
            'debit' => 0,
            'credit' => $subtotal,
            'is_debit' => false,
        ]);

        // 4.2 ضريبة المدخلات (دائن) — البنود والشحن كلٌّ إلى حسابه
        if ($totalTax > 0 && $taxAccount) {
            $shippingTax = round((float) ($purchaseInvoiceReturn->shipping_tax ?? 0), 2);
            $splits = $this->splitsShippingVat($shippingTax);
            $itemsTax = $splits ? round($totalTax - $shippingTax, 2) : $totalTax;

            if ($itemsTax > 0) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntryId,
                    'account_id' => $taxAccount->id,
                    'description' => 'استرداد ضريبة القيمة المضافة - مرتجع مشتريات '.$purchaseInvoiceReturn->code,
                    'debit' => 0,
                    'credit' => $itemsTax,
                    'is_debit' => false,
                ]);
            }

            if ($splits) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntryId,
                    'account_id' => $this->shippingVatPaidAccount()->id,
                    'description' => 'استرداد ضريبة شحن مشتريات - مرتجع مشتريات '.$purchaseInvoiceReturn->code,
                    'debit' => 0,
                    'credit' => $shippingTax,
                    'is_debit' => false,
                ]);
            }

            // رصيد ضريبة القيمة المضافة يتحدث تلقائياً من القيود المحاسبية
        }

        // 4.2.1 تكاليف الشحن (دائن) — عكسُ ما حمّلته الفاتورة على الكلفة
        if ($shippingCost > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntryId,
                'account_id' => $this->settingsAccount('purchase_shipping')->id,
                'description' => 'استرداد تكاليف الشحن - مرتجع مشتريات '.$purchaseInvoiceReturn->code,
                'debit' => 0,
                'credit' => $shippingCost,
                'is_debit' => false,
            ]);
        }

        // 4.2.2 الخصم المكتسب (مدين) — يُردّ الخصمُ بردّ البضاعة التي كُسب عليها
        if ($totalDiscount > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntryId,
                'account_id' => $this->settingsAccount('purchase_discount')->id,
                'description' => 'ردّ خصم مكتسب - مرتجع مشتريات '.$purchaseInvoiceReturn->code,
                'debit' => $totalDiscount,
                'credit' => 0,
                'is_debit' => true,
            ]);
        }

        // 4.3 حساب المورد/المؤسسة (مدين) - تقليل ما علينا للمورد
        $supplierDebitDesc = 'تخفيض ذمم الموردين - مرتجع مشتريات '.$purchaseInvoiceReturn->code;

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntryId,
            'account_id' => $supplierAccount->id,
            'description' => $supplierDebitDesc,
            'debit' => $purchaseInvoiceReturn->grand_total,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // أرصدة المشتريات والمورد تتحدث تلقائياً من القيود المحاسبية
    }

    private function updateAccountingEntriesFixed($purchaseInvoiceReturn, $old_grand_total, $old_paid_amount, $old_total_tax,
        $new_grand_total, $new_paid_amount, $new_total_tax, $mainTreasuryAccount)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // 1️⃣ حساب المورد
        $supplierAccount = Account::ledger()->where('supplier_id', $purchaseInvoiceReturn->supplier_id)->first();
        if (! $supplierAccount) {
            $supplier = Supplier::find($purchaseInvoiceReturn->supplier_id);
            $supplierAccount = Account::create([
                'name' => $supplier->trade_name ?? 'مورد - '.$supplier->id,
                'supplier_id' => $purchaseInvoiceReturn->supplier_id,
                'account_type' => 'supplier',
                'status' => 1,
            ]);
        }

        /* ══════════ إعادةُ بناء سطور القيد ══════════
         *
         * كان ما هنا حساباتِ فروقٍ لا تُستعمَل، وأربعةَ تعليقات تقول إنّ
         * الأرصدة «تتحدّث تلقائياً من القيود المحاسبية» — وهذا صحيح، لكنّ
         * **القيودَ نفسَها لم يكن أحدٌ يحدّثها**. فتعديلُ مرتجعٍ يغيّر
         * أرقامَ المستند ويترك قيده على أرقامه الأولى: المستند يقول شيئاً
         * ودفترُ الأستاذ يقول غيرَه.
         *
         * والسطورُ تُبنى من جديد لا تُرقَّع — بالكاتب نفسِه الذي يستعمله
         * الإنشاء، فلا يتباعد المساران. */
        $entry = JournalEntry::where('purchase_invoice_id', $purchaseInvoiceReturn->id)
            ->where('reference_number', $purchaseInvoiceReturn->code)
            ->first();

        if ($entry) {
            $entry->update([
                'description' => 'مرتجع مشتريات '.$purchaseInvoiceReturn->code,
                'client_id' => $purchaseInvoiceReturn->supplier_id,
            ]);

            JournalEntryDetail::where('journal_entry_id', $entry->id)->delete();

            $this->writeReturnEntryDetails($entry->id, $purchaseInvoiceReturn->fresh(), $supplierAccount);
        }

        /* أرصدةُ الحسابات تُشتقّ من القيود في هذا المتحكّم (بخلاف
           `InvoicesPurchaseController` الذي يحرّك العمود بيده)، فلا حساب
           فروقٍ هنا. ويبقى فرقُ الدفع لقيد السداد وحده. */

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    private function createReturnAccountingEntriesFixed($purchaseInvoiceReturn, $supplier, $paid_amount, $mainTreasuryAccount)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // تحضير بيانات الوصف التفصيلي للتدقيق والمراجعة
        $supplierName = $supplier->trade_name ?? $supplier->name ?? 'مورد غير معروف';
        $referenceInvoice = $purchaseInvoiceReturn->reference_id ? PurchaseInvoice::find($purchaseInvoiceReturn->reference_id) : null;
        $referenceCode = $referenceInvoice ? $referenceInvoice->code : 'بدون مرجع';

        // 1️⃣ حساب المورد
        $supplierAccount = Account::ledger()->where('supplier_id', $purchaseInvoiceReturn->supplier_id)->first();
        if (! $supplierAccount) {
            $supplierAccount = Account::create([
                'name' => 'حساب المورد - '.$supplierName,
                'supplier_id' => $purchaseInvoiceReturn->supplier_id,
                'account_type' => 'supplier',
                'balance' => 0,
                'status' => 1,
            ]);
        }

        // 2️⃣ حساب المشتريات باستخدام الثابت (رقم 25)
        $mainStore = AccountingSettings::account('purchases');
        if (! $mainStore) {
            throw new \Exception('حساب المشتريات برقم 25 غير موجود في النظام!');
        }

        // 3️⃣ حساب القيمة المضافة المحصلة باستخدام الثابت (رقم 2996)
        $taxAccount = null;
        if ($purchaseInvoiceReturn->total_tax > 0) {
            $taxAccount = AccountingSettings::account('vat_paid');
            if (! $taxAccount) {
                throw new \Exception('حساب القيمة المضافة المحصلة برقم 2996 غير موجود في النظام!');
            }
        }

        // «نوع المستند رقمه - الطرف»؛ المبالغ في عمودَي مدين/دائن والتاريخ في `date`
        $mainEntryDescription = 'مرتجع مشتريات '.$purchaseInvoiceReturn->code.' - '.$supplierName.
            ($referenceInvoice ? ' (فاتورة '.$referenceCode.')' : '');

        // 4️⃣ إنشاء القيد الأول: قيد المرتجع
        $journalEntry1 = JournalEntry::create([
            'reference_number' => $purchaseInvoiceReturn->code,
            'purchase_invoice_id' => $purchaseInvoiceReturn->id,
            'date' => now(),
            'description' => $mainEntryDescription,
            'status' => 1,
            'currency' => $this->documentCurrency(),
            'client_id' => $purchaseInvoiceReturn->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PURCHASE_RETURN,
            'linking_type' => LinkingType::SINGLE,
        ]);

        $this->writeReturnEntryDetails($journalEntry1->id, $purchaseInvoiceReturn, $supplierAccount);

        // 5️⃣ القيد الثاني: قيد الدفع إذا كان هناك دفع
        if ($paid_amount > 0) {
            $paymentEntryDescription = 'تحصيل من المورد - مرتجع مشتريات '.$purchaseInvoiceReturn->code.' - '.$supplierName;

            $journalEntry2 = JournalEntry::create([
                'reference_number' => $purchaseInvoiceReturn->code.'_تحصيل',
                'date' => now(),
                'description' => $paymentEntryDescription,
                'status' => 1,
                'currency' => $this->documentCurrency(),
                'client_id' => $purchaseInvoiceReturn->supplier_id,
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
                'linking_type' => LinkingType::SINGLE,
            ]);

            // 5.1 حساب الخزينة (مدين) - دخول نقد
            $treasuryDebitDesc = 'تحصيل نقدي من المورد - مرتجع مشتريات '.$purchaseInvoiceReturn->code;

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $mainTreasuryAccount->id,
                'description' => $treasuryDebitDesc,
                'debit' => $paid_amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // 5.2 حساب المورد (دائن) - تسوية المستحق
            $supplierCreditDesc = 'تسوية مع المورد - مرتجع مشتريات '.$purchaseInvoiceReturn->code;

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $supplierAccount->id,
                'description' => $supplierCreditDesc,
                'debit' => 0,
                'credit' => $paid_amount,
                'is_debit' => false,
            ]);

            // أرصدة الخزينة والمورد تتحدث تلقائياً من القيود المحاسبية
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * دالة مساعدة للموافقة على إذن المرتجع وتحديث كميات المنتجات (بالنقصان)
     */
    private function approveWarehousePermitForReturn($purchaseReturn)
    {
        // البحث عن الإذن المخزني المرتبط
        $permit = WarehousePermits::where('reference_id', $purchaseReturn->id)
            ->where(function ($q) {
                $q->where('permission_source_id', PermissionSources::PURCHASE_RETURN);
            })->first();

        if (! $permit) {
            // إذا لم يوجد إذن، نقوم بإنشائه أولاً (حالة احتياطية)
            $permit = $this->createWarehousePermitForReturn($purchaseReturn, request());
        }

        /* مرتجعٌ خدماتٍ كلّه لا إذن له ولا كمّية تُخصَم — قيمتُه وحدها تدخل
           الدفتر. وبلا هذا الفحص يُقرأ `status` من `null`. */
        if (! $permit) {
            return;
        }

        // إذا كان معتمداً بالفعل، لا نفعل شيئاً
        if ($permit->status === 'apporod') {
            return;
        }

        /* الخصمُ من مستودع **كلّ بند** لا من مستودع رأس الإذن: البضاعة تخرج
           من حيث دخلت. كان يُخصَم الجميعُ من مستودع الرأس — وهو الرئيسي
           افتراضاً — فيبقى المستودعُ الذي استلم ممتلئاً بما خرج منه.
           بالوحدة الأساسية، كما زادت عند الاستلام. */
        PurchaseOutboundStock::deduct($purchaseReturn, $permit);

        // تحديث حالة الإذن إلى "موافق عليه"
        $permit->update(['status' => 'apporod']);

        // ✅ إنشاء قيد الإخراج (من المخزن إلى المشتريات)
        $this->createReturnStockAccountingEntry($purchaseReturn, $permit);
    }

    /**
     * دالة إنشاء قيد إخراج بضاعة لمرتجع (من حساب المستودع إلى حساب المشتريات)
     */
    private function createReturnStockAccountingEntry($purchaseReturn, $permit)
    {
        try {
            /* من ح/ المشتريات إلى ح/ كلِّ مستودعٍ بصافي ما خرج منه — كان
               `الكمية × السعر` الخام على حساب مستودع رأس الإذن وحده، فيخرج
               من الدفتر أكثرُ ممّا دخله، ومن المستودع الخطأ. انظر
               `PurchaseOutboundStock::postExitEntry()`. */
            PurchaseOutboundStock::postExitEntry(
                $purchaseReturn,
                $permit,
                'قيد إخراج بضاعة (مرتجع) - فاتورة رقم '.$purchaseReturn->code,
                'مرتجع # '.$purchaseReturn->code,
                $this->documentCurrency(),
            );

            // الأرصدة تتحدث تلقائياً من القيود المحاسبية
            // لا حاجة لتحديث يدوي - JournalEntryDetail يحدث الأرصدة

        } catch (\Exception $e) {
            Log::error('خطأ في إنشاء قيد إخراج المخزن للمرتجع: '.$e->getMessage());
        }
    }

    /**
     * الرقم المعتمد للمستند — ما عرضته الشاشة، وإلا التالي من إعدادات
     * «تسلسل أرقام المستندات». حقل الرقم للقراءة فقط منذ أن صار يُولَّد
     * من الإعدادات، فما يصل هنا هو ما عُرض لا ما كُتب.
     *
     * كان الرقم يُولَّد بـ`str_pad(max(code) + 1)` فيتجاهل البادئة والصيغة
     * المضبوطتين في الإعدادات. والرقم المحجوز لا يُرفض بل يُستبدل بالتالي
     * الشاغر، فلا يضيع إدخال المستخدم على تضارب رقم.
     */
    private function resolveCode($submitted): string
    {
        $submitted = trim((string) $submitted);

        if ($submitted === '' || PurchaseInvoice::where('code', $submitted)->exists()) {
            return next_document_number(self::NUMBER_SECTION);
        }

        return $submitted;
    }
}
