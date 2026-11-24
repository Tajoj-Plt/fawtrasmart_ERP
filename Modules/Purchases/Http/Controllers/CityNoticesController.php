<?php

namespace Modules\Purchases\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
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
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Purchases\PurchaseDocumentTotals;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use App\Services\Purchases\PurchaseTaxCalculator;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\ProductUnits;
use Modules\Purchases\Support\PurchaseOutboundStock;
use Modules\Purchases\Support\ReturnableInvoiceLines;
use Modules\Purchases\Support\SupplierGuard;

class CityNoticesController extends Controller
{
    use Concerns\ResolvesPurchaseAccounts;

    use Concerns\PreparesPurchaseDocumentLines;
    use Concerns\RespectsAccountingPeriods;
    use ScopesToEmployeeHierarchy;

    /** نوع المستند في شاشة «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'debit_note';

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'purchase-debit-note';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للمستند */
    public const LOG_MODULE = 'purchase_debit_note';

    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً — لا قديمة ولا من
     * كتالوج `PurchasesPermissions` رغم أن مجموعة `debit_notices` معرَّفة
     * فيه أصلاً وتُشير إليه صراحةً. الحراسة هنا أول تطبيق فعلي لها.
     */
    public function __construct()
    {
        $this->middleware('permission:purchases.debit_notices.view', ['only' => ['index', 'paginatePurchase', 'show', 'getInvoiceDetails']]);
        $this->middleware('permission:purchases.debit_notices.create', ['only' => ['create', 'createFromInvoice', 'store', 'invoicesForSupplier']]);
        $this->middleware('permission:purchases.debit_notices.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:purchases.debit_notices.delete', ['only' => ['destroy']]);
        $this->middleware('permission:purchases.debit_notices.approve', ['only' => ['markAsApproved']]);
        $this->middleware('permission:purchases.debit_notices.export', ['only' => ['pdf', 'printView']]);
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
            PurchaseInvoice::where('type', 'City Notice')
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
     * @throws Exception إن لم يكن المفتاح مربوطاً أو كان حسابه محذوفاً
     */
    private function settingsAccount(string $key): Account
    {
        $account = AccountingSettings::account($key);

        if (! $account) {
            throw new Exception(__('purchase_cycle.messages.account_missing', [
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
           شاشة فواتير المبيعات. الإشعارُ المدين يكتب قيداً كالفاتورة، فيُقرأ
           على المدى الذي تعمل عليه المنشأة لا على تاريخها كلّه. */
        $range = $this->reportDateRange($request);

        // فلاترُ القائمة تتبع فرعَ الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers()->get();
        $users = $this->creatorOptions(\App\Models\PurchaseInvoice::where('type', 'City Notice'));
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
        $noticesData = $this->getFilteredData($request, false);

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

        return view('purchases::purchases.city_notices.index', compact('scopeNotice', 'noticesData', 'taxes', 'suppliers', 'users', 'account_setting', 'accounts', 'stats', 'range', 'branches', 'employees'));
    }

    private function getFilteredData(Request $request, $returnJson = true)
    {
        $query = PurchaseInvoice::query()
            ->with(['supplier', 'creator', 'originalDocument'])
            ->where('type', 'City Notice');

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
        $noticesData = $query->paginate($perPage)->withQueryString();

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'data' => view('purchases::purchases.city_notices.partials.table', compact('noticesData'))->render(),
                'pagination' => view('purchases::purchases.city_notices.partials.pagination', compact('noticesData'))->render(),
                'total' => $noticesData->total(),
                'current_page' => $noticesData->currentPage(),
                'last_page' => $noticesData->lastPage(),
                'from' => $noticesData->firstItem(),
                'to' => $noticesData->lastItem(),
            ]);
        }

        return $noticesData;
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

        // البحث بالتاريخ
        /* النطاقُ مطبَّقٌ دائماً: حقلا التاريخ فارغَين كانا يعنيان «بلا حدّ»،
           فتُقرأ الشاشة على تاريخ المنشأة كلّه. صارا يعنيان الفترة المحاسبية
           الجارية. */
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

        return redirect()->route('CityNotices.index');
    }

    public function create()
    {
        // المورِّدون على فرع الشريط العلويّ وما يتبعه — {@see \App\Support\HeaderBranchScope}
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), [])->get();
        $items = Product::all();
        $taxs = TaxSitting::all();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        /* الفواتيرُ المرشَّحة للربط — فارغةٌ ابتداءً: لا مورّد مختاراً بعد
           في نموذجٍ فارغ، وسردُ فواتير المنشأة كلِّها معاً كان يسمح بربط
           إشعارٍ بفاتورةٍ من مورّدٍ آخر تماماً. الشاشةُ تطلبها عبر
           `CityNotices.invoicesForSupplier` بمجرّد اختيار المورد — انظر
           سكربت `partials/form.blade.php`. */
        $availableInvoices = collect();

        // الرقم من «تسلسل أرقام المستندات» — محجوزٌ لا مُثبَّت، فلا تُستهلك
        // أرقام على شاشةٍ تُفتَح ولا تُحفَظ
        $code = peek_document_number(self::NUMBER_SECTION);

        // الخزائن من «الحسابات المحاسبية»
        $treasuries = $this->treasuryAccounts();
        $MainTreasury = $this->defaultTreasury();
        $treasury_id = $MainTreasury->id ?? null;
        $branches = \App\Models\Branch::orderBy('name')->get();

        /* الفترة المحاسبية تمنع الحفظ بتاريخ اليوم: يُقال قبل ملء المستند
           كلّه لا بعد الضغط على حفظ — كما في شاشات المبيعات. */
        $periodBlock = $this->accountingPeriodBlock(now());

        return view('purchases::purchases.city_notices.create', compact('suppliers', 'taxs', 'account_setting', 'items', 'availableInvoices', 'treasuries', 'MainTreasury', 'treasury_id', 'code', 'branches', 'periodBlock'));
    }

    public function createFromInvoice($invoiceId)
    {
        $originalInvoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($invoiceId);
        $this->authorizeCreatorScope($originalInvoice->created_by, $originalInvoice->employee_id, $originalInvoice->branch_id);

        if ($originalInvoice->type !== 'invoice') {
            return redirect()->back()->with('error', 'يمكن إنشاء اشعار مدين فقط من الفواتير العادية');
        }

        // الفاتورة التي رُدَّت لا يُصدَر لها إشعار: أثرها عُكِس بالمرتجع
        $hasReturnInvoice = PurchaseInvoice::where('reference_id', $invoiceId)
            ->where('type', 'Return')
            ->exists();

        if ($hasReturnInvoice) {
            return redirect()->back()->with('error', __('purchase_cycle.debit_note.blocked_by_return', [
                'invoice' => $originalInvoice->code,
            ]));
        }

        // فرعُ الشريط العلويّ، ومورِّدُ الفاتورة الأصلية يبقى ولو كان على جهةٍ أخرى
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers($this->scopeSuppliers(Supplier::query()), array_filter([$originalInvoice->supplier_id]))->get();
        $items = Product::all();
        $taxs = TaxSitting::all();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        $code = peek_document_number(self::NUMBER_SECTION);

        // خزينة الإشعار: خزينة الفاتورة الأصلية أولاً، ثم خزينة الموظّف،
        // ثم الخزينة الرئيسية من «الحسابات المحاسبية»
        $originalPayment = PaymentsProcess::where('purchases_id', $originalInvoice->id)
            ->where('type', 'supplier payments')
            ->orderBy('created_at', 'desc')
            ->first();

        $originalTreasury = $originalPayment?->treasury_id ? Account::find($originalPayment->treasury_id) : null;

        $treasuries = $this->treasuryAccounts();
        $MainTreasury = $originalTreasury ?: $this->defaultTreasury();
        $treasury_id = $MainTreasury->id ?? null;
        $branches = \App\Models\Branch::orderBy('name')->get();

        /* الفترة المحاسبية تمنع الحفظ بتاريخ اليوم: يُقال قبل ملء المستند
           كلّه لا بعد الضغط على حفظ — كما في شاشات المبيعات. */
        $periodBlock = $this->accountingPeriodBlock(now());

        return view('purchases::purchases.city_notices.create', compact('suppliers', 'taxs', 'account_setting', 'items', 'originalInvoice', 'treasuries', 'MainTreasury', 'treasury_id', 'code', 'branches', 'periodBlock'));
    }

    /**
     * فواتيرُ مورّدٍ بعينه القابلة للارتجاع — لتعبئة قائمة «الفاتورة
     * المرجعية» بعد اختيار المورد في نموذج الإنشاء (طلب المستخدم
     * 2026-09-21): كانت القائمة تُبنى مرّةً واحدة عند فتح الشاشة بفواتير
     * المنشأة كلِّها بلا صلةٍ بالمورد المختار.
     */
    public function invoicesForSupplier($supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorizeCreatorScope($supplier->created_by);

        $invoices = ReturnableInvoiceLines::forSupplier((int) $supplierId);

        return response()->json([
            'invoices' => $invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'label' => $invoice->code,
            ])->values(),
        ]);
    }

    public function getInvoiceDetails($invoiceId)
    {
        try {
            $invoice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($invoiceId);
            $this->authorizeCreatorScope($invoice->created_by, $invoice->employee_id, $invoice->branch_id);

            if ($invoice->type !== 'invoice') {
                return response()->json(['error' => 'يمكن إنشاء اشعار مدين فقط من الفواتير العادية'], 400);
            }

            /* كان الفحصُ «هل يوجد مرتجعٌ للفاتورة؟» فقط — يستبعدها كلَّها
               بمجرّد مرتجعٍ جزئيّ واحد. والمعيارُ الآن الكمّيةُ المتبقّية،
               فتبقى الفاتورةُ مؤهَّلةً ما دام فيها صنفٌ لم يُستنفَد بعد —
               نفسُ ما تُبنى به قائمةُ الفواتير في الشاشة
               ({@see \Modules\Purchases\Support\ReturnableInvoiceLines}). */
            if (! ReturnableInvoiceLines::hasRemainingQuantity($invoice->id)) {
                return response()->json(['error' => 'لا يمكن إنشاء إشعار دائن لهذه الفاتورة — لم تعد فيها كمّيةٌ قابلة للارتجاع'], 400);
            }

            return response()->json([
                'success' => true,
                'invoice' => $invoice,
            ]);
        } catch (Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.CityNotices', 'getInvoiceDetails');

            return response()->json(['error' => 'الفاتورة غير موجودة'], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            /* الضريبة الشاملة على المستند — الخانةُ العلوية `document_tax_id`
               تُفرَض على كلّ بند حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة. */
            PurchaseTaxCalculator::applyComprehensive($request);

            // التحقق من تفعيل إعداد الإشعار الدائن

            // المرجع اختياري هنا — بخلاف المرتجع، الإشعار المدين قد يصدر مستقلاً
            $originalInvoice = null;
            if ($request->filled('reference_id')) {
                $originalInvoice = PurchaseInvoice::find($request->reference_id);
                if (! $originalInvoice) {
                    return redirect()->back()->withInput()->with('error', 'الفاتورة المرجعية غير موجودة');
                }
                if ($originalInvoice->type !== 'invoice') {
                    return redirect()->back()->withInput()->with('error', 'يمكن إنشاء اشعار مدين فقط للفواتير العادية');
                }

                /* الفاتورةُ لموردٍ آخر — الشاشةُ تُتجاوَز بطلبٍ مباشر، وقائمةُ
                   الفواتير في الشاشة مقيَّدةٌ بالمورد المختار أصلاً */
                if ((string) $originalInvoice->supplier_id !== (string) $request->supplier_id) {
                    return redirect()->back()->withInput()->with('error', 'الفاتورة المرجعية لا تخصّ المورد المختار.');
                }

                /* نفس فحص الكمّية المتبقّية الذي تُبنى به قائمةُ الشاشة —
                   يُعاد هنا لأن الشاشة تُتجاوَز بطلبٍ مباشر، والفاتورةُ قد
                   تُستنفَد كمّيتُها (مرتجعاً أو إشعاراً سابقاً) بين فتح
                   النموذج وحفظه. */
                if (! ReturnableInvoiceLines::hasRemainingQuantity($originalInvoice->id)) {
                    return redirect()->back()->withInput()->with('error', __('purchase_cycle.debit_note.blocked_by_return', [
                        'invoice' => $originalInvoice->code,
                    ]));
                }
            }

            if (! $request->supplier_id) {
                throw new Exception('يجب تحديد المورد');
            }

            $supplier = Supplier::find($request->supplier_id);
            if (! $supplier) {
                throw new Exception('المورد المحدد غير موجود');
            }

            /* «منع المستندات على مورد موقوف» — كان هذا المتحكّم وحده بلا
               هذا الفحص: المرتجع وفاتورة الشراء يردّان المورد الموقوف،
               والإشعار المدين يمرّ. فبابٌ واحدٌ مفتوح يُبطل المنع كلّه. */
            SupplierGuard::assertActive($supplier);

            // خيار «اعتبار الإشعار مسلَّماً للمورد افتراضياً» — نظير
            // `return_default_received` للمرتجع: يُغني الموظّف عن تأشير
            // الاستلام، وبه تُخرَج الأصناف من المخزن عند الحفظ نفسه
            $notice_received = $request->has('is_received')
                || PurchaseSettings::enabled('notice_default_received');

            // ** الرقم من إعدادات «تسلسل أرقام المستندات» **
            // ** الفترة المحاسبية ** — الإشعار المدين يكتب قيداً محاسبياً،
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

            DB::beginTransaction();

            $total_amount = 0;
            $total_discount = 0;
            $items_data = [];
            $lineNets = []; // صافي كلّ بندٍ بعد خصمه الخاصّ
            $lineTaxes = []; // ضرائب كلّ بندٍ: المعرّفات والنسب
            $documentTaxIds = []; // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن

            if ($request->has('items') && count($request->items)) {
                foreach ($request->items as $item) {
                    $product = $this->resolveLineProduct($lineData['products'], $item);

                    // مستودع البند — منه تُخرَج الكمية في إذن الإخراج
                    $store_house_id = $this->resolveLineStoreHouse(
                        $item,
                        $lineData['storeHouses'],
                        $defaultStoreHouse,
                        $originalStoreHouses
                    );

                    $quantity = floatval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $item_total = $quantity * $unit_price;

                    $item_discount = 0;
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        if (isset($item['discount_type']) && $item['discount_type'] === 'percentage') {
                            $item_discount = ($item_total * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }

                    /* صافي البند وضرائبه — انظر PurchaseDocumentTotals::compute().
                       وكانت حلقةُ الضريبة أدناه تمرّر الخصمَ `0.0`: تحسب
                       الضريبةَ على السعر كاملاً وتتجاهل خصمَ البند. */
                    $lineNets[] = max(0.0, round($item_total - $item_discount, 2));
                    $lineTaxes[] = [
                        'ids' => [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null],
                        'rates' => [floatval($item['tax_1'] ?? 0), floatval($item['tax_2'] ?? 0)],
                    ];
                    $documentTaxIds[] = $item['tax_1_id'] ?? null;
                    $documentTaxIds[] = $item['tax_2_id'] ?? null;

                    $total_amount += $item_total;
                    $total_discount += $item_discount;

                    // وحدة البند تُرافقه من الفاتورة الأصلية، ومنها معامل التحويل
                    // والكمية بالوحدة الأساسية — بها يُخصَم من المستودع لاحقاً
                    // كما دخل بالضبط (انظر نظيرها في ReturnsInvoiceController)
                    $items_data[] = ProductUnits::fieldsFor($item, $quantity) + [
                        'purchase_invoice_id' => null,
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
                        // البند يحمل مستودعه كما في فاتورة الشراء — كان يُحفَظ
                        // فارغاً، فإذن الإخراج يضع الجميع في مستودعٍ واحد
                        'store_house_id' => $store_house_id,
                    ];
                }
            }

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

            // مقرَّبٌ قبل المقارنات — انظر النظير في `ReturnsInvoiceController`
            $total_with_tax = round($amount_after_discount + $total_tax + $shipping_cost + $shipping_tax, 2);

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

            // الخزينة: ما اختاره المستخدم في النموذج أولاً، ثم خزينته الموكَّلة،
            // ثم «الخزينة الرئيسية» من «الحسابات المحاسبية».
            //
            // كان اختيار المستخدم يُهمل كلّياً — يظهر الحقل في الشاشة ويُرسَل ثم
            // يُقيَّد الإشعار على خزينةٍ أخرى.
            $mainTreasuryAccount = null;
            $treasury_id = $request->input('treasury_id') ?: $request->input('advance_payment_treasury_id');

            if ($treasury_id) {
                $mainTreasuryAccount = Account::find($treasury_id);
            }

            if (! $mainTreasuryAccount) {
                $mainTreasuryAccount = $this->defaultTreasury();
                $treasury_id = $mainTreasuryAccount->id ?? null;
            }

            if (! $mainTreasuryAccount) {
                throw new Exception(__('purchase_cycle.messages.account_missing', [
                    'account' => __('purchase_cycle.accounts.main_treasury'),
                ]));
            }

            $reference_id = null;
            if ($originalInvoice) {
                $reference_id = $originalInvoice->id;
            } elseif ($request->filled('reference_id')) {
                $reference_id = $request->reference_id;
            }

            // الفرع: فرع الفاتورة المرجعية إن وُجدت (الإشعار تسويةٌ عليها)،
            // وإلا فرع المستخدم الحالي — إلا لمن يملك صلاحية اختيار فرع آخر
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'), $originalInvoice?->branch_id);

            $cityNotices = PurchaseInvoice::create([
                'supplier_id' => $request->supplier_id,
                'code' => $code,
                /* النوع بقيمته لا بفهرسه: العمود `ENUM('invoice','Return',
                   'Requested','City Notice')`، و`4` كان يصيب «City Notice»
                   مصادفةً بترتيب الفهرس — وأيّ إعادة ترتيب للقائمة تحوّل
                   كلّ إشعارٍ جديد إلى نوعٍ آخر. والتقاريرُ التي تقارن
                   `type == 4` لا تجد شيئاً أصلاً لأنّ المخزون نصّ. */
                'type' => \App\Enums\PurchaseDocumentType::DEBIT_NOTE->value,
                'reference_id' => $reference_id,
                'branch_id' => $branchId,
                'date' => $request->date,
                'terms' => $request->terms ?? 0,
                'notes' => ($request->notes ?? '').($originalInvoice ? "\nاشعار مدين للفاتورة رقم: ".$originalInvoice->code : '')."\nالخزينة المستخدمة: ".$mainTreasuryAccount->name,
                /* `status` لا يُكتب: كان `1`/`2`/`3` في عمود
                   `ENUM('disagree','approval','Under Review',…)` — وMySQL
                   يقرأ العدد فهرساً، فالإشعارُ المدفوع يُحفَظ «مرفوضاً»
                   والمدفوع جزئياً «معتمَداً». حالةُ الدفع لها عمودُها
                   `payment_status`، والاعتماد يبقى على افتراضي العمود
                   «تحت المراجعة» كما في المرتجع تماماً. */
                'created_by' => Auth::id(),
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
                'is_received' => $notice_received,
                'receiving_status' => $notice_received ? 'received' : 'not_received',
                'subtotal' => $total_amount,
                'total_discount' => $final_total_discount,
                'total_tax' => $total_tax + $shipping_tax,
                'shipping_tax' => $shipping_tax,      // حصّةُ الشحن محفوظةٌ وحدها لتُرحَّل إلى حسابها
                'grand_total' => $total_with_tax,
                'due_value' => $due_value,
            ]);

            // سطورُ الضريبة من الحساب نفسه الذي بُنيت عليه الإجماليات، لا
            // `النسبة × الكمية × السعر` بلا خصم — انظر `PurchaseOutboundStock`
            PurchaseOutboundStock::writeTaxRows($cityNotices->id, $totals['tax_rows'], 'cityNotices_purchase', (float) $totals['shipping_tax'], (float) $totals['shipping_cost']);

            foreach ($items_data as $item) {
                $item['purchase_invoice_id'] = $cityNotices->id;
                $invoice_purhase = InvoiceItem::create($item);

                $invoice_purhase->load('product');

                OperationLog::record(
                    'created',
                    self::LOG_MODULE,
                    $cityNotices,
                    $this->logLine('created', $cityNotices->code),
                );
            }

            if ($paid_amount > 0) {
                $payment = PaymentsProcess::create([
                    'purchases_id' => $cityNotices->id,
                    'supplier_id' => $request->supplier_id,
                    'amount' => $paid_amount,
                    'payment_date' => $request->date ?? now(),
                    'payment_method' => $request->payment_method ?? ($request->advance_payment_method ?? 1),
                    'type' => 'supplier city notice payment',
                    'payment_status' => 1,
                    'employee_id' => Auth::id(),
                    'treasury_id' => $treasury_id,
                    'notes' => $payment_status === 'paid' ? 'دفع كامل لاشعار مدين رقم '.$cityNotices->code.' إلى خزينة '.$mainTreasuryAccount->name : 'دفع جزئي بمبلغ '.number_format($paid_amount, 2).' لاشعار مدين رقم '.$cityNotices->code.' إلى خزينة '.$mainTreasuryAccount->name,
                ]);
            }

            // ** الخطوة السادسة: القيود المحاسبية وتحديث المخزون **

            // 1. إنشاء قيد الإشعار المدين (دائماً عند الحفظ - لإثبات تقليل المديونية)
            $this->createCityNoticeAccountingEntries($cityNotices, $supplier, $paid_amount, $mainTreasuryAccount);

            // 2. تحديث المخزون وقيد الإخراج — فوراً إن أُشِّر «تم الاستلام»
            // (أو فعَّلَ الإعداد ذلك افتراضياً)، أي أن إخراجاً فعلياً تمّ عند
            // الحفظ نفسه؛ وإلا يبقى الإذن «معلَّقاً» حتى تأكيدٍ لاحق من شاشة
            // العرض عبر markAsApproved()
            if ($notice_received) {
                $this->approveWarehousePermitForDebitNote($cityNotices);
            } else {
                $this->createWarehousePermitForDebitNote($cityNotices, $request, $defaultStoreHouse->id ?? null);
            }

            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $cityNotices->attachments = $filename;
                    $cityNotices->save();
                }
            }

            DB::commit();

            notifications::create([
                'type' => 'city_notice_creation',
                'title' => Auth::user()->name.' أنشأ اشعار مدين جديد',
                'description' => 'تم إنشاء اشعار مدين رقم '.$cityNotices->code.' للمورد '.($supplier->trade_name ?? '').' بقيمة '.number_format($cityNotices->grand_total, 2).' ر.س'.($originalInvoice ? ' مرتبط بالفاتورة رقم '.$originalInvoice->code : '').' من خزينة '.$mainTreasuryAccount->name.($paid_amount > 0 ? ' - تم إيداع '.number_format($paid_amount, 2).' ر.س' : '').' - حالة الدفع: '.$this->getPaymentStatusText($payment_status),
            ]);

            $message = $this->generateCityNoticeSuccessMessage($payment_status, $paid_amount, $due_value, $mainTreasuryAccount->name, $originalInvoice);

            return redirect()->route('CityNotices.show', $cityNotices->id)->with('success', $message);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('خطأ في إنشاء اشعار مدين: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء حفظ اشعار مدين: '.$e->getMessage());
        }
    }

    private function createCityNoticeAccountingEntries($cityNotices, $supplier, $paid_amount, $mainTreasuryAccount)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // تحضير بيانات الوصف التفصيلي للتدقيق والمراجعة
        $supplierName = $supplier->trade_name ?? $supplier->name ?? 'مورد غير معروف';
        $referenceInvoice = $cityNotices->reference_id ? PurchaseInvoice::find($cityNotices->reference_id) : null;
        $referenceCode = $referenceInvoice ? $referenceInvoice->code : 'بدون مرجع';
        /* صافي البضاعة وحدها — كما تحسبه فاتورةُ الشراء بالضبط.
         *
         * كان `grand_total − total_tax` فقط: يدفن تكلفةَ الشحن داخل ح/
         * المشتريات ويبتلع الخصم بلا سطر، فلا يعكس الإشعارُ ما رحّلته
         * الفاتورة. انظر `InvoicesPurchaseController::writeInvoiceEntryDetails()`. */
        $shippingCost = round((float) ($cityNotices->shipping_cost ?? 0), 2);
        $totalDiscount = round((float) ($cityNotices->total_discount ?? 0), 2);
        $totalTax = round((float) ($cityNotices->total_tax ?? 0), 2);
        $grandTotal = round((float) $cityNotices->grand_total, 2);

        $subtotal = round($grandTotal - $totalTax - $shippingCost + $totalDiscount, 2);

        // «نوع المستند رقمه - الطرف»؛ المبالغ في عمودَي مدين/دائن والتاريخ في `date`
        $mainEntryDescription = 'إشعار مدين '.$cityNotices->code.' - '.$supplierName.
            ($referenceInvoice ? ' (فاتورة '.$referenceCode.')' : '');

        // القيد الأول: تسجيل الإشعار المدين
        $journalEntry1 = JournalEntry::create([
            'reference_number' => $cityNotices->code,
            'date' => now(),
            'description' => $mainEntryDescription,
            'status' => 1,
            'currency' => $this->documentCurrency(),
            'client_id' => $cityNotices->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::DEBIT_NOTE,
            'linking_type' => LinkingType::SINGLE,

            /* الربط بالمستند كان غائباً عن هذا القيد وحده — بينما قيدُ إذن
               المخزن للإشعار نفسه يضعه. فالقيد يُكتب ولا يُعثَر عليه من
               الإشعار: شاشةُ العرض لا تجد له قيداً، وكلُّ تقريرٍ يربط
               بـ`purchase_invoice_id` يُسقطه. */
            'purchase_invoice_id' => $cityNotices->id,
        ]);

        // جلب أو إنشاء حساب المورد
        $supplierAccount = Account::ledger()->where('supplier_id', $cityNotices->supplier_id)->first();
        if (! $supplierAccount) {
            $supplierAccount = Account::create([
                'name' => 'حساب المورد - '.$supplierName,
                'supplier_id' => $cityNotices->supplier_id,
                'account_type' => 'supplier',
                'balance' => 0,
                'status' => 1,
            ]);
        }

        // جلب حساب المشتريات من الثوابت
        $purchasesAccount = AccountingSettings::account('purchases');
        if (! $purchasesAccount) {
            throw new Exception('لم يتم العثور على حساب المشتريات');
        }

        /* ضريبة **المدخلات** لا المخرجات.
         *
         * كان `vat_payable` — وهو حساب الضريبة المحصَّلة على المبيعات. والإشعار
         * المدين مستندُ مشترياتٍ يعكس ضريبةً **دُفعت** للمورّد وطُولب بها
         * استرداداً، فمكانها `vat_paid`. وكلُّ مستندات المشتريات الأخرى —
         * الفاتورة والمرتجع وأمر الشراء — تستعمل `vat_paid`؛ هذا وحده كان
         * يشذّ.
         *
         * وأثرُه على الإقرار الضريبي في اتجاهين معاً: ضريبةُ مدخلاتٍ تبقى
         * مطالَباً بها بعد إلغائها، وضريبةُ مخرجاتٍ تنقص بلا بيع يقابلها.
         */
        $taxAccount = AccountingSettings::account('vat_paid');
        if (! $taxAccount) {
            throw new Exception('لم يتم العثور على حساب ضريبة القيمة المضافة المدفوعة');
        }

        // 1. حساب المورد (مدين) - بكامل مبلغ الإشعار المدين
        $supplierDebitDesc = 'تخفيض ذمم الموردين - إشعار مدين '.$cityNotices->code;

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry1->id,
            'account_id' => $supplierAccount->id,
            'description' => $supplierDebitDesc,
            'debit' => $cityNotices->grand_total,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // 2. حساب المشتريات (دائن) - بالمبلغ بدون ضريبة
        $purchasesCreditDesc = 'تخفيض المشتريات - إشعار مدين '.$cityNotices->code;

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry1->id,
            'account_id' => $purchasesAccount->id,
            'description' => $purchasesCreditDesc,
            'debit' => 0,
            'credit' => $subtotal,
            'is_debit' => false,
        ]);

        // 3. ضريبة المدخلات (دائن) — البنود والشحن كلٌّ إلى حسابه
        if ($totalTax > 0) {
            $shippingTax = round((float) ($cityNotices->shipping_tax ?? 0), 2);
            $splits = $this->splitsShippingVat($shippingTax);
            $itemsTax = $splits ? round($totalTax - $shippingTax, 2) : $totalTax;

            if ($itemsTax > 0) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry1->id,
                    'account_id' => $taxAccount->id,
                    'description' => 'استرداد ضريبة القيمة المضافة - إشعار مدين '.$cityNotices->code,
                    'debit' => 0,
                    'credit' => $itemsTax,
                    'is_debit' => false,
                ]);

                $taxAccount->balance -= $itemsTax;
                $taxAccount->save();
            }

            if ($splits) {
                $shippingVatAccount = $this->shippingVatPaidAccount();

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry1->id,
                    'account_id' => $shippingVatAccount->id,
                    'description' => 'استرداد ضريبة شحن مشتريات - إشعار مدين '.$cityNotices->code,
                    'debit' => 0,
                    'credit' => $shippingTax,
                    'is_debit' => false,
                ]);

                $shippingVatAccount->balance -= $shippingTax;
                $shippingVatAccount->save();
            }
        }

        // 3.1 تكاليف الشحن (دائن) — عكسُ ما حمّلته الفاتورة على الكلفة
        if ($shippingCost > 0) {
            $shippingAccount = $this->settingsAccount('purchase_shipping');

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry1->id,
                'account_id' => $shippingAccount->id,
                'description' => 'استرداد تكاليف الشحن - إشعار مدين '.$cityNotices->code,
                'debit' => 0,
                'credit' => $shippingCost,
                'is_debit' => false,
            ]);

            $shippingAccount->balance -= $shippingCost;
            $shippingAccount->save();
        }

        // 3.2 الخصم المكتسب (مدين) — يُردّ الخصمُ بردّ البضاعة التي كُسب عليها
        if ($totalDiscount > 0) {
            $discountAccount = $this->settingsAccount('purchase_discount');

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry1->id,
                'account_id' => $discountAccount->id,
                'description' => 'ردّ خصم مكتسب - إشعار مدين '.$cityNotices->code,
                'debit' => $totalDiscount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            $discountAccount->balance -= $totalDiscount;
            $discountAccount->save();
        }

        // تحديث أرصدة الحسابات
        // تقليل رصيد المورد (تقليل الدين علينا)
        $supplierAccount->balance -= $cityNotices->grand_total;
        $supplierAccount->save();

        // تقليل رصيد المشتريات
        $purchasesAccount->balance -= $subtotal;
        $purchasesAccount->save();

        // القيد الثاني: تسجيل الدفع من المورد إلى الخزينة (إذا كان هناك دفع)
        if ($paid_amount > 0) {
            $paymentEntryDescription = 'تحصيل من المورد - إشعار مدين '.$cityNotices->code.' - '.$supplierName;

            $journalEntry2 = JournalEntry::create([
                'reference_number' => $cityNotices->code.'_تحصيل',
                'date' => now(),
                'description' => $paymentEntryDescription,
                'status' => 1,
                'currency' => $this->documentCurrency(),
                'client_id' => $cityNotices->supplier_id,
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
                'linking_type' => LinkingType::SINGLE,
            ]);

            // حساب الخزينة (مدين) - دخول نقد
            $treasuryDebitDesc = 'تحصيل نقدي من المورد - إشعار مدين '.$cityNotices->code;

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $mainTreasuryAccount->id,
                'description' => $treasuryDebitDesc,
                'debit' => $paid_amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // حساب المورد (دائن) - زيادة الدين عليه
            $supplierCreditDesc = 'تسوية مع المورد - إشعار مدين '.$cityNotices->code;

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry2->id,
                'account_id' => $supplierAccount->id,
                'description' => $supplierCreditDesc,
                'debit' => 0,
                'credit' => $paid_amount,
                'is_debit' => false,
            ]);

            // تحديث أرصدة الحسابات
            $mainTreasuryAccount->balance += $paid_amount;
            $mainTreasuryAccount->save();

            $supplierAccount->balance += $paid_amount;
            $supplierAccount->save();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * لا تعديل بعد الإنشاء.
     *
     * الإشعار يكتب قيداً محاسبياً لحظةَ حفظه، فتعديله بعد ذلك يترك القيد على
     * حاله ويخالف بينه وبين المستند. الفرق يُعالَج بإشعارٍ جديد له قيده.
     *
     * **كان النداء بلا `return`** فيسقط في `null`، ولارافيل يترجمه صفحةً
     * بيضاء: يضغط المستخدم «تعديل» فلا يرى رسالةً ولا صفحة.
     */
    public function edit($id)
    {
        return redirect()
            ->route('CityNotices.show', $id)
            ->with('error', __('purchase_cycle.debit_note.no_edit'));
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

            $cityNotices = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($cityNotices->created_by, $cityNotices->employee_id, $cityNotices->branch_id);

            if ($cityNotices->is_received) {
                throw new Exception('لا يمكن تعديل اشعار مدين مستلم بالفعل');
            }

            $originalInvoice = null;
            if ($request->has('reference_id') && $request->reference_id) {
                $originalInvoice = PurchaseInvoice::find($request->reference_id);
                if (! $originalInvoice) {
                    return redirect()->back()->withInput()->with('error', 'الفاتورة المرجعية غير موجودة');
                }
                if ($originalInvoice->type !== 'invoice') {
                    return redirect()->back()->withInput()->with('error', 'يمكن إنشاء اشعار مدين فقط للفواتير العادية');
                }
            }

            if (! $request->supplier_id) {
                throw new Exception('يجب تحديد المورد');
            }

            $supplier = Supplier::find($request->supplier_id);
            if (! $supplier) {
                throw new Exception('المورد المحدد غير موجود');
            }

            /* «منع المستندات على مورد موقوف» — كان هذا المتحكّم وحده بلا
               هذا الفحص: المرتجع وفاتورة الشراء يردّان المورد الموقوف،
               والإشعار المدين يمرّ. فبابٌ واحدٌ مفتوح يُبطل المنع كلّه. */
            SupplierGuard::assertActive($supplier);

            // نفس منطق store(): الإعداد يُغني الموظّف عن تأشير الاستلام يدوياً
            $notice_received = $request->has('is_received')
                || PurchaseSettings::enabled('notice_default_received');

            $code = $request->code;
            if ($code && $code !== $cityNotices->code) {
                $existingCode = PurchaseInvoice::where('code', $code)->exists();
                if ($existingCode) {
                    return redirect()->back()->withInput()->with('error', 'رقم الفاتورة موجود مسبقاً، الرجاء استخدام رقم آخر');
                }
            } else {
                $code = $cityNotices->code;
            }

            $total_amount = 0;
            $total_discount = 0;
            $items_data = [];
            $lineNets = []; // صافي كلّ بندٍ بعد خصمه الخاصّ
            $lineTaxes = []; // ضرائب كلّ بندٍ: المعرّفات والنسب
            $documentTaxIds = []; // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن

            if ($request->has('items') && count($request->items)) {
                foreach ($request->items as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    $quantity = floatval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $item_total = $quantity * $unit_price;

                    $item_discount = 0;
                    if (isset($item['discount']) && $item['discount'] > 0) {
                        if (isset($item['discount_type']) && $item['discount_type'] === 'percentage') {
                            $item_discount = ($item_total * floatval($item['discount'])) / 100;
                        } else {
                            $item_discount = floatval($item['discount']);
                        }
                    }

                    /* صافي البند وضرائبه — انظر PurchaseDocumentTotals::compute().
                       وكانت حلقةُ الضريبة أدناه تمرّر الخصمَ `0.0`: تحسب
                       الضريبةَ على السعر كاملاً وتتجاهل خصمَ البند. */
                    $lineNets[] = max(0.0, round($item_total - $item_discount, 2));
                    $lineTaxes[] = [
                        'ids' => [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null],
                        'rates' => [floatval($item['tax_1'] ?? 0), floatval($item['tax_2'] ?? 0)],
                    ];
                    $documentTaxIds[] = $item['tax_1_id'] ?? null;
                    $documentTaxIds[] = $item['tax_2_id'] ?? null;

                    $total_amount += $item_total;
                    $total_discount += $item_discount;

                    $items_data[] = ProductUnits::fieldsFor($item, $quantity) + [
                        'purchase_invoice_id' => $cityNotices->id,
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

            // مقرَّبٌ قبل المقارنات — انظر النظير في `ReturnsInvoiceController`
            $total_with_tax = round($amount_after_discount + $total_tax + $shipping_cost + $shipping_tax, 2);

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
                throw new Exception(__('purchase_cycle.messages.account_missing', [
                    'account' => __('purchase_cycle.accounts.main_treasury'),
                ]));
            }

            $reference_id = null;
            if ($originalInvoice) {
                $reference_id = $originalInvoice->id;
            } elseif ($request->filled('reference_id')) {
                $reference_id = $request->reference_id;
            }

            // الفرع يبقى كما هو إلا إن اختار المستخدم فرعاً آخر صراحةً ويملك
            // صلاحية ذلك — نفس نمط store()
            $branchId = \App\Support\Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'), $cityNotices->branch_id);

            $cityNotices->update([
                'supplier_id' => $request->supplier_id,
                'code' => $code,
                'reference_id' => $reference_id,
                'branch_id' => $branchId,
                'date' => $request->date,
                'terms' => $request->terms ?? 0,
                'notes' => ($request->notes ?? '').($originalInvoice ? "\nاشعار مدين للفاتورة رقم: ".$originalInvoice->code : '')."\nالخزينة المستخدمة: ".$mainTreasuryAccount->name,
                /* `status` لا يُكتب: كان `1`/`2`/`3` في عمود
                   `ENUM('disagree','approval','Under Review',…)` — وMySQL
                   يقرأ العدد فهرساً، فالإشعارُ المدفوع يُحفَظ «مرفوضاً»
                   والمدفوع جزئياً «معتمَداً». حالةُ الدفع لها عمودُها
                   `payment_status`، والاعتماد يبقى على افتراضي العمود
                   «تحت المراجعة» كما في المرتجع تماماً. */
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
                'is_received' => $notice_received,
                'receiving_status' => $notice_received ? 'received' : 'not_received',
                'subtotal' => $total_amount,
                'total_discount' => $final_total_discount,
                'total_tax' => $total_tax + $shipping_tax,
                'shipping_tax' => $shipping_tax,      // حصّةُ الشحن محفوظةٌ وحدها لتُرحَّل إلى حسابها
                'grand_total' => $total_with_tax,
                'due_value' => $due_value,
            ]);

            $cityNotices->invoiceItems()->delete();
            foreach ($items_data as $item) {
                InvoiceItem::create($item);
            }

            TaxInvoice::where('invoice_id', $cityNotices->id)
                ->where('type_invoice', 'cityNotices_purchase')
                ->delete();

            // من الحساب نفسه الذي بُنيت عليه الإجماليات — انظر `store()`
            PurchaseOutboundStock::writeTaxRows($cityNotices->id, $totals['tax_rows'], 'cityNotices_purchase', (float) $totals['shipping_tax'], (float) $totals['shipping_cost']);

            if ($request->hasFile('attachments')) {
                if ($cityNotices->attachments) {
                    $oldFile = public_path('assets/uploads/').$cityNotices->attachments;
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $cityNotices->attachments = $filename;
                    $cityNotices->save();
                }
            }

            DB::commit();

            // ** الخطوة السابعة: القيود المحاسبية وتحديث المخزون **
            // ملاحظة: نحتاج لإضافة دالة تحديث القيود أو التعامل معها ببساطة كحذف وإنشاء في هذه المرحلة إذا لم تكن موجودة
            // للتبسيط ولضمان الدقة، سنقوم بإعادة إنشاء القيد (Liability)
            JournalEntry::where('reference_number', $cityNotices->code)->where('reference_type', ReferenceType::DEBIT_NOTE)->delete();
            $this->createCityNoticeAccountingEntries($cityNotices, $supplier, $paid_amount, $mainTreasuryAccount);

            // 2. تحديث المخزون وقيد الإخراج — فوراً إن أُشِّر «تم الاستلام»
            // (أو فعَّلَ الإعداد ذلك افتراضياً)؛ وإلا يبقى الإذن محدَّثاً بلا
            // اعتماد (انظر الشرح في store())
            if ($notice_received) {
                $this->approveWarehousePermitForDebitNote($cityNotices);
            } else {
                $this->updateOrCreateWarehousePermitForDebitNote($cityNotices, $request);
            }

            DB::commit();

            return redirect()->route('CityNotices.show', $cityNotices->id)
                ->with('success', 'تم تحديث اشعار مدين وتحديث القيود والمخزون بنجاح');
        } catch (Exception $e) {
            DB::rollback();
            Log::error('خطأ في تحديث اشعار مدين: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء تحديث اشعار مدين: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $cityNotice = PurchaseInvoice::findOrFail($id);
            $this->authorizeCreatorScope($cityNotice->created_by, $cityNotice->employee_id, $cityNotice->branch_id);
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $cityNotice,
                $this->logLine('deleted', $cityNotice->code),
            );
            $cityNotice->delete();

            // إذا كان الطلب AJAX، نرجع استجابة JSON
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف اشعار مدين بنجاح',
                ]);
            }

            return redirect()->route('CityNotices.index')->with('success', 'تم حذف اشعار مدين بنجاح');
        } catch (Exception $e) {
            Log::error('خطأ في حذف اشعار مدين: '.$e->getMessage());

            // إذا كان الطلب AJAX، نرجع استجابة JSON للخطأ
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف اشعار مدين: '.$e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء حذف اشعار مدين: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $cityNotice = PurchaseInvoice::with(['supplier', 'items.product', 'payments', 'creator', 'user', 'originalDocument'])
            ->findOrFail($id);
        $this->authorizeCreatorScope($cityNotice->created_by, $cityNotice->employee_id, $cityNotice->branch_id);

        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)
            ->whereIn('type_invoice', ['cityNotices_purchase', 'purchase'])
            ->get();

        // سجلّ النشاط — بالنوع الذي يكتبه `OperationLog` فعلاً (LOG_MODULE).
        // كانت القراءة بنوعٍ لا يُكتَب (`purchase_city_notices_log`) فيخرج
        // الخطّ الزمني فارغاً مهما جرى على المستند.
        $logs = ModelsLog::where('type', self::LOG_MODULE)
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        return view('purchases::purchases.city_notices.show', [
            'cityNotice' => $cityNotice,
            'logs' => $logs,
            'TaxsInvoice' => $TaxsInvoice,
            // القوالب المصمَّمة في «قوالب المستندات»
            'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
            'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
            // المعاينة والتنزيل يتبعان «طريقة الطباعة» في إعدادات الحساب
            'prefersPdf' => PrintSettings::prefersPdf(),
        ]);
    }

    public function markAsApproved($id)
    {
        try {
            DB::beginTransaction();

            $cityNotice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
            $this->authorizeCreatorScope($cityNotice->created_by, $cityNotice->employee_id, $cityNotice->branch_id);

            // مصدر الحقيقة لـ"هل اعتُمِد فعلياً؟" هو حالة الإذن المخزني نفسه،
            // لا عمود is_received على الفاتورة (الذي بات مجرّد تدوين
            // معلوماتي عند الحفظ منذ فصل الاعتماد عن الحفظ)
            $permit = WarehousePermits::where('reference_type', 'debit_note')
                ->where('reference_id', $cityNotice->id)
                ->first();

            if ($permit && $permit->status === 'apporod') {
                return redirect()->back()->with('info', 'الإشعار مستلم بالفعل والقيود منشأة مسبقاً');
            }

            // تحديث الحالة
            $cityNotice->update([
                'receiving_status' => 'received',
                'is_received' => 1,
                'received_date' => now(),
            ]);

            // ✅ تحديث المخزون وتفعيل الإذن المخزني وتوليد قيد الإخراج تلقائياً
            // ملاحظة: قيد الإشعار المدين (المورد) تم إنشاؤه مسبقاً عند حفظ الإشعار
            $this->approveWarehousePermitForDebitNote($cityNotice);

            // تسجيل النشاط
            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $cityNotice,
                $this->logLine('status_changed', $cityNotice->code),
            );

            DB::commit();

            return redirect()->back()->with('success', 'تم تأكيد الإشعار وتحديث المخزون وتوليد قيد الإخراج بنجاح');

        } catch (Exception $e) {
            DB::rollback();
            Log::error('خطأ في استلام إشعار مدين: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء استلام الإشعار: '.$e->getMessage());
        }
    }

    /**
     * تنزيل المستند PDF بقالبٍ من «قوالب المستندات».
     *
     * `$template` معرّف قالبٍ مضبوط في الإعدادات؛ ومتى لم يكن كذلك يُؤخَذ
     * القالب الافتراضي لهذا النوع، ثم القالب المدمج آخِراً. وكان التوقيع
     * يُهمل الوسيط الثاني الذي يمرّره المسار، فتخرج المطبوعة بقالبٍ واحدٍ
     * مهما اختار المستخدم من القائمة.
     */
    public function pdf($id, $template = null)
    {
        $cityNotice = PurchaseInvoice::with(['supplier', 'items.product'])->findOrFail($id);
        $this->authorizeCreatorScope($cityNotice->created_by, $cityNotice->employee_id, $cityNotice->branch_id);

        OperationLog::record(
            'exported',
            self::LOG_MODULE,
            $cityNotice,
            $this->logLine('exported', $cityNotice->code),
        );

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderConfiguredTemplate($id, $fallback->id) : null;
        }

        if ($html !== null) {
            return Pdf::loadHTML($html)->download($cityNotice->code.'.pdf');
        }

        $pdf = Pdf::loadView('purchases::purchases.city_notices.pdf', compact('cityNotice'));

        return $pdf->download($cityNotice->code.'.pdf');
    }

    private function getPaymentStatusText($status)
    {
        switch ($status) {
            case 'paid':
            case 1:
                return 'مدفوعة بالكامل';
            case 'partially_paid':
            case 2:
                return 'مدفوعة جزئياً';
            case 'unpaid':
            case 3:
                return 'غير مدفوعة';
            default:
                return 'غير محددة';
        }
    }

    /**
     * تحديث أو إنشاء إذن مخزني للإشعار المدين (Debit Note)
     */
    private function updateOrCreateWarehousePermitForDebitNote($cityNotice, $request)
    {
        // البحث عن الإذن المخزني المرتبط
        $warehousePermit = WarehousePermits::where('reference_id', $cityNotice->id)
            ->where('permission_source_id', PermissionSources::PURCHASE_DEBIT_NOTE)
            ->first();

        if ($warehousePermit) {
            // تحديث الإذن الموجود
            $warehousePermit->update([
                'permission_date' => $request->date ?? now(),
                'sub_account' => $cityNotice->supplier_id,
                'grand_total' => $cityNotice->grand_total,
                'details' => 'تحديث إذن إخراج بضاعة لإشعار مدين رقم: '.$cityNotice->code,
            ]);

            // تحديث سجلات المنتجات
            WarehousePermitsProducts::where('warehouse_permits_id', $warehousePermit->id)->delete();
        } else {
            // إنشاء رقم الإذن المخزني
            $lastPermit = WarehousePermits::orderBy('id', 'desc')->first();
            $nextPermitNumber = $lastPermit ? intval($lastPermit->number) + 1 : 1;
            $permitNumber = str_pad($nextPermitNumber, 6, '0', STR_PAD_LEFT);

            $warehousePermit = WarehousePermits::create([
                'permission_source_id' => PermissionSources::PURCHASE_DEBIT_NOTE,
                'permission_date' => $request->date ?? now(),
                'sub_account' => $cityNotice->supplier_id,
                'number' => $permitNumber,
                'store_houses_id' => $request->store_house_id ?: $this->resolveDefaultStoreHouse($request)?->id,
                'from_store_houses_id' => $request->store_house_id ?: $this->resolveDefaultStoreHouse($request)?->id,
                'grand_total' => $cityNotice->grand_total,
                'details' => 'إذن إخراج بضاعة لإشعار مدين رقم: '.$cityNotice->code,
                'created_by' => Auth::id(),
                'status' => 'pending',
                'reference_id' => $cityNotice->id,
            ]);
        }

        // إنشاء سجلات المنتجات في إذن المخزن — للأصناف ذات الرصيد وحدها،
        // بصافي التكلفة، وكلٌّ من مستودعه (انظر `PurchaseOutboundStock`)
        foreach (PurchaseOutboundStock::permitLines($cityNotice, (int) $warehousePermit->store_houses_id) as $line) {
            WarehousePermitsProducts::create($line + [
                'warehouse_permits_id' => $warehousePermit->id,
                'details' => 'إشعار مدين رقم: '.$cityNotice->code,
            ]);
        }

        return $warehousePermit;
    }

    /**
     * إنشاء إذن مخزني للإشعار المدين (Debit Note)
     */
    private function createWarehousePermitForDebitNote($cityNotice, $request, $resolvedStoreHouseId = null)
    {
        /* ** الإذن للأصناف ذات الرصيد وحدها **
         *
         * كان يُكتب بندٌ لكل صنف بلا استثناء، فإن كان في الإشعار صنفٌ خدمي
         * أو رقمي رمى `StockMovementGuardObserver` وسقط الإشعار كلّه.
         * والخدمة لا تُسحَب من مستودع أصلاً: قيمتها تدخل الدفتر ولا تدخل
         * الرصيد. (نفس علاج `InvoicesPurchaseController::store()`) */
        $stockableItems = collect($cityNotice->items ?? [])
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
                throw new Exception('لا يوجد أي مستودع في النظام، يرجى إضافة مستودع أولاً.');
            }

            $storeHouseId = $mainStoreHouse->id;
        }

        // إنشاء الإذن المخزني (نوع الإذن: إشعار مدين مشتريات)
        $warehousePermit = WarehousePermits::create([
            'permission_source_id' => PermissionSources::PURCHASE_DEBIT_NOTE, // نوع الإذن: إشعار مدين مشتريات (12)
            'permission_date' => $request->date ?? now(),
            'sub_account' => $cityNotice->supplier_id, // المورد
            'number' => $permitNumber,
            'store_houses_id' => $storeHouseId,
            'from_store_houses_id' => $storeHouseId, // يُعتبر سحب/إخراج
            'to_store_houses_id' => null,
            'grand_total' => $cityNotice->grand_total,
            'details' => 'إذن إخراج بضاعة لإشعار مدين رقم: '.$cityNotice->code,
            'created_by' => Auth::id(),
            'status' => 'pending',
            'reference_type' => 'debit_note',
            'reference_id' => $cityNotice->id,
        ]);

        // إنشاء سجلات المنتجات في إذن المخزن — للأصناف ذات الرصيد وحدها،
        // وكلٌّ من مستودعه هو لا من مستودع الإذن وحده
        // بصافي التكلفة وكلٌّ من مستودعه — انظر `PurchaseOutboundStock`
        foreach (PurchaseOutboundStock::permitLines($cityNotice, (int) $storeHouseId) as $line) {
            WarehousePermitsProducts::create($line + [
                'warehouse_permits_id' => $warehousePermit->id,
                'details' => 'إشعار مدين رقم: '.$cityNotice->code,
            ]);
        }

        return $warehousePermit;
    }

    private function generateCityNoticeSuccessMessage($payment_status, $paid_amount, $due_value, $treasury_name, $originalInvoice)
    {
        $payment_text = $this->getPaymentStatusText($payment_status);

        $message = 'تم إنشاء اشعار مدين بنجاح من خزينة '.$treasury_name;

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
     * دالة مساعدة للموافقة على إذن الإشعار المدين وتحديث كميات المنتجات (بالنقصان)
     */
    private function approveWarehousePermitForDebitNote($cityNotice)
    {
        // البحث عن الإذن المخزني المرتبط
        $permit = WarehousePermits::where('reference_id', $cityNotice->id)
            ->where('reference_type', 'debit_note')
            ->first();

        if (! $permit) {
            // إذا لم يوجد إذن، نقوم بإنشائه أولاً (حالة احتياطية)
            $permit = $this->createWarehousePermitForDebitNote($cityNotice, request());
        }

        /* إشعارٌ خدماتٍ كلّه لا إذن له ولا كمّية تُخصَم — قيمتُه وحدها تدخل
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
        PurchaseOutboundStock::deduct($cityNotice, $permit);

        // تحديث حالة الإذن إلى "موافق عليه"
        $permit->update(['status' => 'apporod']);

        // ✅ إنشاء قيد الإخراج (من المخزن إلى المشتريات)
        $this->createDebitNoteStockAccountingEntry($cityNotice, $permit);
    }

    /**
     * دالة إنشاء قيد إخراج بضاعة لإشعار مدين (من حساب المستودع إلى حساب المشتريات)
     */
    private function createDebitNoteStockAccountingEntry($cityNotice, $permit)
    {
        try {
            /* من ح/ المشتريات إلى ح/ كلِّ مستودعٍ بصافي ما خرج منه — كان
               `الكمية × السعر` الخام على حساب مستودع رأس الإذن وحده، فيخرج
               من الدفتر أكثرُ ممّا دخله، ومن المستودع الخطأ. انظر
               `PurchaseOutboundStock::postExitEntry()`.

               والتعديلُ اليدويّ للأرصدة الذي كان هنا أُسقط: مُراقِب
               `JournalEntryDetail` يعيد حساب رصيد الحساب من قيوده عند كلّ
               سطر، فطرحُ المبلغ بعده مرّةً ثانية كان يُفسد الرصيد المخبَّأ. */
            PurchaseOutboundStock::postExitEntry(
                $cityNotice,
                $permit,
                'قيد إخراج بضاعة (إشعار مدين) - رقم '.$cityNotice->code,
                'إشعار مدين # '.$cityNotice->code,
                $this->documentCurrency(),
            );

        } catch (Exception $e) {
            Log::error('خطأ في إنشاء قيد إخراج المخزن للإشعار المدين: '.$e->getMessage());
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
