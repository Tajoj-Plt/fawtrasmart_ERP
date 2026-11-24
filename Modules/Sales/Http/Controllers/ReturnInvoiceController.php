<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\SalesPlanFeatures;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\CreditNotification;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\PaymentMethod;
use App\Models\PaymentsProcess;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductUnit;
use App\Models\SalesInvoiceSetting;
use App\Models\StoreHouse;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\Treasury;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Clients\ClientVisibilityScope;
use App\Services\PointsService;
use App\Services\Tax\DocumentTaxCalculator;
use App\Support\CurrencySettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\StockEligibility;
use App\Support\TreasuryAccessGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\DocumentAttachments;
use Modules\Sales\Support\ReturnLimits;
use Modules\Sales\Support\SalesWarehouse;

/**
 * فواتير المبيعات المرتجعة.
 *
 * مرتجع البيع فاتورةٌ بنوع `returned` في جدول `invoices` نفسه، فيرث
 * أعمدته كلَّها — العملة وطريقة الدفع والخزينة — ويجب أن يرث سلوكه:
 *
 *   • *الرقم*        ← «تسلسل أرقام المستندات»، القسم `return-invoice`
 *                      (`GeneratesDocumentNumbers`).
 *   • *العملة*       ← «العملات»، وتُجمَّد مع المستند
 *                      (`UsesDocumentCurrency`).
 *   • *الحسابات*     ← «الحسابات المحاسبية» وحدها، بلا احتياطٍ برمجي
 *                      (`ResolvesAccountingAccounts`).
 *   • *الإقفال*      ← «الفترات المحاسبية»: تاريخٌ في فترةٍ مقفلة يردّ
 *                      الحفظ (`RespectsAccountingPeriods`).
 *   • *القوالب*      ← «قوالب المبيعات»، النوع `sales_return`
 *                      (`RendersSalesDocumentTemplates`).
 *   • *طرق الدفع*    ← «طرق الدفع» في ردّ المبلغ للعميل.
 *   • *السجلّات*     ← `OperationLog`: النشاط والأحداث والأخطاء.
 *   • *اللغة والمظهر* ← عبر القوالب: `sl-*` مبنيّة على `--app-*`.
 */
class ReturnInvoiceController extends Controller
{
    use Concerns\AppliesDiscountOffers;
    use Concerns\ChecksClientProximity;
    use Concerns\ChecksSubscriptionLimits;
    use Concerns\GeneratesDocumentNumbers;
    use Concerns\RendersSalesDocumentTemplates;
    use Concerns\ResolvesAccountingAccounts;
    use Concerns\ResolvesSaleCost;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\ScopesSalesVisibility;
    use Concerns\SendsSalesDocumentEmails;
    use Concerns\UsesDocumentCurrency;

    /** قسم الترقيم المتسلسل الذي تُؤخذ منه أرقام المرتجعات */
    public const NUMBER_SECTION = 'return-invoice';

    /** نوع المستند في «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'sales_return';

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'SalesReturns';

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.returns.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.returns.view', ['only' => ['index', 'showPrintable', 'show']]);
        $this->middleware('permission:sales.returns.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.returns.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales.returns.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.returns.print', ['only' => ['print']]);
        $this->middleware('permission:sales.returns.send', ['only' => ['sendReturnInvoiceEmail']]);
    }

    /**
     * عدد السجلّات في الصفحة — من قائمة مغلقة.
     *
     * `per_page` كان يصل إلى `paginate()` كما أرسله المتصفّح: طلبٌ بـ
     * `per_page=9999999` يحمّل الجدول كلّه في الذاكرة (البند ٦٫٣).
     */
    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    /**
     * طرق الدفع المفعَّلة في شاشة «طرق الدفع».
     */
    protected function activePaymentMethods()
    {
        try {
            return PaymentMethod::query()
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'name_en', 'code', 'is_default']);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.ReturnInvoice', 'activePaymentMethods');

            return collect();
        }
    }

    /**
     * شاشة الفواتير المرتجعة — على قياس شاشة فواتير المبيعات.
     *
     * ما أُصلح هنا (بنودٌ من تقرير التحسين):
     *
     *   • *الصلاحيات من `job_roles` مباشرةً* — كانت تُقرأ من أعمدة جدول
     *     الأدوار (`$jobRole->sales_view_all_return_invoices`) بدل نظام
     *     الصلاحيات، فمن يملك الصلاحية عبر Spatie ولا دور له في
     *     `job_roles` يُردّ بـ403. صارت `hasPermission()` كبقيّة الشاشات.
     *   • *حدود المدى* (٢٫١٥) — `>` و`<` تستبعدان القيم الحدّية.
     *   • *`LIKE '%…%'` على المعرّف* (٥٫١) — يمنع الفهرس، ويُرجع فواتير
     *     لا علاقة لها بالمكتوب (بحث «1» يُرجع 1 و11 و21…). صار بحثاً
     *     على الرقم المطبوع `code` مع مطابقةٍ تامّة على المعرّف.
     *   • *`per_page` بلا تحقّق* (٦٫٣) و*`appends()` مفقودة* (٦٫٢).
     *   • *`whereDate` على `created_at`* (٥٫٤) — الدالّة على العمود تمنع
     *     الفهرس.
     *   • *بلا نطاق افتراضي* — الشاشة تفتح على تاريخ المنشأة كلّه.
     *   • *بلا فلتر عملة ولا فرع* — أُضيفا كما في شاشة الفواتير.
     */
    public function index(Request $request)
    {
        // ✅ صلاحية العرض محروسة أصلاً في المُنشئ (permission:sales.returns.view)

        // النطاق الافتراضي: الفترة المحاسبية الجارية
        $range = $this->reportDateRange($request);

        // العملة لم تعد في نموذج البحث: غيابُها «كل العملات» لا عملةُ النظام
        $currency = $request->filled('currency') ? $this->filterCurrency($request) : null;

        $query = Invoice::with(['client', 'createdByUser', 'employee', 'updatedByUser'])
            ->where('type', 'returned');

        /* نطاقُ الرؤية — السلَّمُ الخماسيّ: الكلّ، أو فرعُه، أو فريقُه،
           أو ما كتبه ونُسب إليه. كان «الكلّ أو ما كتبتُه» فحسب.
           انظر `ScopesSalesVisibility`. */
        $this->scopeSalesDocuments($query);

        /* الفرع النشط من الهيدر — مبدّل الفروع يكتب `users.branch_id`
           و`current_branch_id()` تقرؤه. المدير على «كل الفروع» قيمتُه
           `null` فلا تصفية. */
        /* والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها،
           فمن يقف عليه يرى المبيعات كلَّها. */
        $this->applyHeaderBranchScope($query);

        // قاعدةُ منسدلتَي «أُضيفت بواسطة» و«مسؤول المبيعات» — قبل فلاتر البحث
        $optionsBase = clone $query;

        $this->applySearchFilters($query, $request, $range, $currency);

        /* الإجمالي **قبل** التقطيع: `paginate()` يكتب `limit/offset` على
           الاستعلام نفسِه، فنسخةٌ تُؤخذ بعده تجمع بـ`OFFSET 10` — صفرٌ في
           بطاقة «إجمالي المرتجعات» من الصفحة الثانية فصاعداً. */
        $returnsTotal = (float) (clone $query)->sum('grand_total');

        $return = $query->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->appends($request->except('page'));
        $returnsCount = $return->total();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => view('sales::retend_invoice.partials.table', [
                    'return' => $return,
                    'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
                ])->render(),
                'current_page' => $return->currentPage(),
                'last_page' => $return->lastPage(),
                'total' => $return->total(),
                'from' => $return->firstItem(),
                'to' => $return->lastItem(),
                'per_page' => $return->perPage(),
                'returns_total' => CurrencySettings::format($returnsTotal),
                'returns_count' => $returnsCount,
            ]);
        }

        return view('sales::retend_invoice.index', [
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::RETURN_INVOICES),
            'return' => $return,
            /* مُنتقي العميل في الفلتر — على نطاق الرؤية كمُنتقي شاشة
               الإنشاء (`ClientVisibilityScope`). كانت القائمة كلَّ عملاء
               المنشأة، فتُفشي أسماءَ من لا يراهم المستخدم في «إدارة
               العملاء»، وتَعِد بنتيجةٍ فارغة لمن يضغط اسماً منها. */
            'clients' => ClientVisibilityScope::documentSelectClients(auth()->user()),
            /* كانتا كلَّ مستخدمي المنشأة وكلَّ موظفيها. صارتا من المرتجعات
               التي يراها المستخدم في هذا الفرع — انظر `creatorFilterOptions()`. */
            'users' => $this->creatorFilterOptions($optionsBase),
            // مسؤولو المرتجعات أنفسِها وفواتيرِها الأصلية — كما يطابقهم الفلتر
            'employees' => $this->salesPersonFilterOptions(Invoice::query()->where(function ($q) use ($optionsBase) {
                $q->whereIn('invoices.id', (clone $optionsBase)->toBase()->reorder()->select('invoices.id'))
                    ->orWhereIn('invoices.id', (clone $optionsBase)->toBase()->reorder()
                        ->select('invoices.reference_number')
                        ->whereNotNull('invoices.reference_number'));
            })),
            'account_setting' => AccountSetting::where('user_id', auth()->id())->first(),
            'range' => $range,
            'returnsTotal' => $returnsTotal,
            'returnsCount' => $returnsCount,
            'branches' => Branch::where('status', 0)->select('id', 'name')->get(),
            'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
        ]);
    }

    /**
     * شروط البحث في شاشة المرتجعات.
     *
     * @param  array{start: string, end: string}|null  $range
     * @param  string|null  $currency  رمز العملة، أو `null` لكل العملات
     */
    protected function applySearchFilters($query, Request $request, ?array $range = null, ?string $currency = null): void
    {
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // `code` هو ما يراه المستخدم ويطبعه، و`id` معرّفٌ داخلي
        if ($request->filled('invoice_number')) {
            $term = trim((string) $request->invoice_number);

            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term.'%');

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        /* الفاتورة الأصلية التي صدر عنها المرتجع.
           العمودُ يحفظ **معرّفَ** الفاتورة، والجدولُ يعرض **رمزَها**
           (`INV-00003`) — فمن كتب ما يراه لم يجد شيئاً. يُقبل الاثنان. */
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

        $this->applyCurrencyFilter($query, $currency);

        // الفرع من عمود المستند نفسه لا من فرع مُنشئه الحاليّ
        if ($request->filled('branch')) {
            $query->forBranch($request->branch);
        }

        // حدود شاملة: `>=` و`<=` لا `>` و`<`
        if ($request->filled('total_from')) {
            $query->where('grand_total', '>=', $request->total_from);
        }

        if ($request->filled('total_to')) {
            $query->where('grand_total', '<=', $request->total_to);
        }

        /* على **تاريخ المرتجع** (`invoice_date`) لا لحظة إدخاله: هو التاريخ
           المعروض في الجدول، وعليه تُستبعد الفترات المقفلة أدناه — فمرتجعٌ
           أُرِّخ بأمسِ وأُدخل اليوم كان يظهر في نطاقٍ غير الذي يُقرأ فيه. */
        if ($range) {
            $query->whereBetween('invoices.invoice_date', [
                Carbon::parse($range['start'])->toDateString(),
                Carbon::parse($range['end'])->toDateString(),
            ]);
        }

        /* ** الفترات المقفلة ** — تُستبعد من الشاشة كلّها لا من نطاقها
           الافتراضيّ وحده: من أقفل سنةً لا يريد مستنداتِها أن تعود بضبط
           التاريخ يدوياً. */
        $this->excludeClosedRanges($query, 'invoices.invoice_date');

        if ($request->filled('added_by_employee')) {
            $query->where('created_by', $request->added_by_employee);
        }

        /* مسؤولُ المبيعات — من المرتجع، وإلّا من فاتورته الأصلية.
           المرتجعُ لا يُختم عليه `employee_id` (صفرٌ من ١٦٥)، والفاتورةُ
           الأصلية تحمله دائماً — فكان الفلترُ لا يُطابق مرتجعاً واحداً. */
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

    public function showPrintable($id)
    {
        $credit = CreditNotification::with(['client', 'createdBy'])->findOrFail($id);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'credit')->get();

        return view('sales::creted_note.pdf', compact('credit', 'TaxsInvoice'));
    }

    public function create($id)
    {
        $user = auth()->user();

        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.returns.create)

        // العثور على الفاتورة
        $invoice = Invoice::findOrFail($id);

        // مرتجعٌ واحد للفاتورة — والإشعارُ الدائن الذي سوّاها يُعدّ مرتجعاً
        if ($blocked = $invoice->returnBlockReason()) {
            return redirect()->route('invoices.show', $invoice->id)->with('error', $blocked);
        }

        $selectedStoreHouseId = $invoice->items->first()->store_house_id ?? null;

        // جلب المستودعات المربوطة بالموظف
        $store_houses = collect();
        if ($user->employee_id) {
            /* مصدر المستودع من «إعدادات الفواتير» (مستودع الموظّف أو
               الرئيسي)، مصفّاةً على صلاحية «إنشاء فاتورة» المضبوطة على
               المستودع — كانت تُحفَظ في شاشة المستودع ولا يقرؤها أحد. */
            $store_houses = SalesWarehouse::optionsFor($user);

            $targetStorehouseId = $store_houses->first()->id ?? null;

            if ($targetStorehouseId) {
                $items = Product::with(['productDetails' => function ($query) use ($targetStorehouseId) {
                    $query->where('store_house_id', $targetStorehouseId);
                }])->where(function ($query) use ($targetStorehouseId) {
                    $query->whereHas('productDetails', function ($q) use ($targetStorehouseId) {
                        $q->where('store_house_id', $targetStorehouseId);
                    });

                    // الخدمي والرقمي بلا رصيد — يظهران بحسب «إعدادات الفواتير»
                    if ($stocklessTypes = SalesWarehouse::visibleStocklessTypes()) {
                        $query->orWhereIn('type', $stocklessTypes);
                    }
                })->get();
            } else {
                $items = collect();
            }
        } else {
            $store_houses = StoreHouse::all();
            $items = Product::with('productDetails')->get();
        }

        // حساب المبلغ المدفوع من المدفوعات الفعلية
        $paidAmount = PaymentsProcess::where('invoice_id', $invoice->id)
            ->where('type', 'client payments')
            ->sum('amount');

        // تحديث advance_payment في الفاتورة
        $invoice->advance_payment = $paidAmount;

        // تحديد خزينة الموظف الحالي
        $employee_treasury = null;
        $is_main_treasury = false; // علامة للتحقق من استخدام الخزينة الرئيسية

        if (auth()->user()->employee_id) {
            $treasuryEmployee = TreasuryEmployee::where('employee_id', auth()->user()->employee_id)->first();
            if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                $employee_treasury = Account::find($treasuryEmployee->treasury_id);
            }
        }

        // إذا لم يكن للموظف خزينة، استخدم الخزينة الرئيسية
        if (! $employee_treasury) {
            $employee_treasury = $this->accountingAccount('main_treasury');
            $is_main_treasury = true;
        }

        /* الخزائن المسموح لهذا المستخدم السحبُ منها، وخزينتُه هو مختارةً
           سلفاً.

           كانت القائمة تُعرض بلا أيّ خيارٍ مُختار، فتُفتح الشاشةُ على
           «اختر خزينة» وخزينةُ الموظف بين خياراتها — فيبدو الحقلُ فارغاً
           ويظنّ الموظفُ أنّ خزينته لا تظهر. والمُختار يُلتقَط من القائمة
           نفسِها لا من `$employee_treasury` مباشرةً: قيمُ الخيارات
           معرّفاتُ `treasuries` بينما `$employee_treasury` **حساب** —
           انظر `refundTreasuryAccount()`. وبهذا لا يُختار خيارٌ غيرُ
           معروضٍ أصلاً.

           والموظفُ يرى خزينتَه وحدها لا كلَّ خزينةٍ مفتوحةٍ «للجميع» —
           انظر `TreasuryAccessGuard::ownWithdrawable()`. */
        $treasuries = TreasuryAccessGuard::ownWithdrawable();

        $selectedTreasuryId = $employee_treasury
            ? $treasuries->firstWhere('account_id', $employee_treasury->id)?->id
            : null;

        $users = User::all();
        $taxs = TaxSitting::all();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // إضافة معرفات الضرائب لكل عنصر من عناصر الفاتورة
        foreach ($invoice->items as $item) {
            // البحث عن معرف الضريبة الأولى بناءً على قيمتها
            if ($item->tax_1 && $item->tax_1 > 0) {
                $tax1 = TaxSitting::where('tax', $item->tax_1)->first();
                $item->tax_1_id = $tax1 ? $tax1->id : null;
            }

            // البحث عن معرف الضريبة الثانية بناءً على قيمتها
            if ($item->tax_2 && $item->tax_2 > 0) {
                $tax2 = TaxSitting::where('tax', $item->tax_2)->first();
                $item->tax_2_id = $tax2 ? $tax2->id : null;
            }
        }

        // إعدادات المبيعات (إذا وجدت)
        $salesSettings = [];

        /* قائمةُ العميل بمحورَي النطاق معاً — الصلاحيةُ **وفرعُ الشريط
           العلويّ**. كانت بالصلاحية وحدها، فمن بدّل الشريط إلى فرعٍ بعينه
           يجد في المُنتقي عملاء المنشأة كلِّها. والفرعُ الرئيسيّ يعني
           المنشأة كلَّها فلا يُرشِّح — انظر `HeaderBranchScope`. */
        $clients = ClientVisibilityScope::documentSelectClients($user, [
            'account' => fn ($query) => $query->select('id', 'client_id', 'balance'),
        ]);

        // تمرير البيانات إلى العرض
        return view('sales::retend_invoice.create', compact(
            'account_setting', 'taxs', 'items', 'employee_treasury', 'is_main_treasury',
            'users', 'invoice', 'salesSettings', 'clients', 'store_houses', 'selectedStoreHouseId'
        ) + [
            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «تسلسل أرقام المستندات» — القسم `return-invoice`. كان الرقم
            // يُشتقّ من آخر صفٍّ في `invoices` أياً كان نوعه، فيأخذ المرتجع
            // رقمه من فاتورة بيع
            'return_number' => $this->previewDocumentNumber(self::NUMBER_SECTION),

            // «إعدادات الفواتير» — نفس الإعدادات التي تحكم شاشة الفاتورة
            'invoiceSettings' => SalesInvoiceSetting::current(),

            // «العملات»
            'documentCurrency' => $this->defaultCurrency(),
            'currencies' => $this->activeCurrencies(),

            // «طرق الدفع» — ردّ المبلغ للعميل
            'paymentMethods' => $this->activePaymentMethods(),

            /* «الخزائن» — ما يجوز لهذا المستخدم **السحب** منه وحده. كانت
               `Treasury::all()`: خزائنُ المنشأة كلُّها لكل موظف، رغم أنّ
               شرط السحب مضبوطٌ على الخزينة نفسها (موظفاً أو دوراً أو
               فرعاً) وتفرضه `TreasuryAccessGuard` عند الحفظ — فيرى الموظفُ
               خياراً يُرفض عليه بعد ملء الفاتورة كلِّها. */
            'treasuries' => $treasuries,
            'selectedTreasuryId' => $selectedTreasuryId,

            // «الفترات المحاسبية» — سببُ منع الحفظ بتاريخ اليوم إن وُجد
            'periodBlock' => $this->accountingPeriodBlock(now()),

            /* مقدّماتُ المستند من الفاتورة الأصلية — الشحنُ وخصمُ
               المستند. البنودُ كانت تأتي كاملةً والمستندُ لا، انظر
               `originalDocumentExtras()`. */
            'originalExtras' => $this->originalDocumentExtras($invoice),
        ]);
    }

    /**
     * ما يُقترح على المرتجع من متن الفاتورة الأصلية — لا من بنودها.
     *
     * ══════════════════════════════════════════════════════════════════
     *  ما كان
     * ══════════════════════════════════════════════════════════════════
     *
     * شاشةُ المرتجع تنسخ بنودَ الفاتورة كاملةً: الكميةَ والسعرَ وخصمَ
     * البند وضريبتَه ووحدتَه ومستودعَه. أمّا متنُ المستند — **رسومُ
     * الشحن وضريبتُها وخصمُ المستند** — فكان يبدأ من صفرٍ دائماً.
     *
     * فمن باع بفاتورةٍ فيها شحنٌ ثمّ ردّها كاملةً، خرج مرتجعُه أنقصَ من
     * فاتورته بقيمة الشحن: تُخصَم من ذمّة العميل قيمةُ البضاعة وحدها
     * ويبقى عليه الشحنُ الذي لم يُشحَن. والمستخدم لا يرى الفرق إلّا في
     * كشف الحساب.
     *
     * ══════════════════════════════════════════════════════════════════
     *  والمقترحُ اقتراحٌ لا قَدَر
     * ══════════════════════════════════════════════════════════════════
     *
     * الحقولُ تبقى قابلةً للتعديل: مرتجعٌ **جزئيّ** قد لا يستحقّ ردَّ
     * الشحن كلِّه — أو لا يستحقّه أصلاً. فالشاشةُ تملؤها وتقول من أين
     * جاءت، والقرارُ للمستخدم.
     *
     * **ضريبةُ الشحن**: `invoices.shipping_tax` يحفظ **المبلغ** لا معرّفَ
     * الضريبة، فيُستخرَج المعرّفُ بمطابقة النسبة — نفسُ ما يفعله تحويلُ
     * عرض السعر إلى فاتورة.
     *
     * **خصمُ المستند**: `invoices.discount_amount` مجموعٌ لثلاثة —
     * خصمِ البنود وخصمِ المستند وحسمِ الكوبون (`store()`: `$total_discount
     * + $invoice_discount + $coupon_discount`). وخصمُ البنود يعود مع
     * البنود نفسِها، والكوبونُ لا يُعاد على مرتجع. فلا يُقترح إلّا
     * الباقي — وإلّا حُسب خصمُ البنود مرّتين.
     *
     * @return array{shipping_cost: float, shipping_tax_id: int|null, discount_amount: float}
     */
    protected function originalDocumentExtras(Invoice $invoice): array
    {
        $shippingCost = round((float) ($invoice->shipping_cost ?? 0), 2);
        $shippingTax = (float) ($invoice->shipping_tax ?? 0);

        $itemsDiscount = (float) $invoice->items->sum(fn ($item) => (float) ($item->discount ?? 0));

        $documentDiscount = (float) ($invoice->discount_amount ?? 0)
            - $itemsDiscount
            - (float) ($invoice->coupon_discount ?? 0);

        return [
            'shipping_cost' => $shippingCost,
            'shipping_tax_id' => ($shippingCost > 0 && $shippingTax > 0)
                ? DocumentTaxCalculator::matchByRate(round($shippingTax / $shippingCost * 100, 2))
                : null,
            'discount_amount' => round(max(0, $documentDiscount), 2),
        ];
    }

    /**
     * خصم بندٍ من حمولة الطلب — مفتاحٌ واحد للحلقتين.
     *
     * النموذج يرسل `discount`، وبعض النداءات ترسل `discount_amount`.
     * فتُقبَل الاثنتان، وتُقرأ القيمة مرّةً واحدة بالمنطق نفسه — بدل
     * حلقتين تقرأ كلٌّ منهما مفتاحاً غير الآخر.
     *
     * @param  array<string, mixed>  $item
     */
    protected function itemDiscount(array $item, float $itemTotal): float
    {
        $raw = $item['discount'] ?? $item['discount_amount'] ?? 0;
        $value = floatval($raw);

        if ($value <= 0) {
            return 0.0;
        }

        $isPercentage = ($item['discount_type'] ?? null) === 'percentage'
            || ($item['discount_type'] ?? null) === 2;

        return $isPercentage ? ($itemTotal * $value) / 100 : $value;
    }

    /**
     * حسابُ الخزينة التي يُصرَف منها ردُّ المرتجع.
     *
     * **المصدر الوحيد لهذا القرار** — كان مكرّراً في فرعَي «مدفوعة
     * بالكامل» و«مدفوعة جزئياً» بنفس الخلل:
     *
     * الشاشةُ ترسل `treasury_id` من جدول `treasuries` (القائمة مبنيّةٌ من
     * صفوفه)، والشيفرةُ كانت تقرؤه `Account::find($request->treasury_id)`
     * — أي تعامله معرّفَ **حساب**. والمعرّفان لا يلتقيان: صفوف
     * `treasuries` أرقامُها من واحد إلى بضع عشرات، وحساباتُها في
     * `accounts` بالآلاف. فاختيارُ «خزينة الادريسي» (`treasuries.id = 2`)
     * كان يصرف من الحساب رقم ٢ في شجرة الحسابات — حسابٍ لا علاقة له
     * بالخزائن أصلاً — ويخصم رصيده.
     *
     * أمّا `treasury_employees.treasury_id` فمعرّفُ **حساب** فعلاً (تحقَّق
     * من الجدول)، فيبقى على حاله.
     */
    protected function refundTreasuryAccount(Request $request): ?Account
    {
        // ١) ما اختاره المستخدم في الشاشة — عبر `treasuries.account_id`
        if ($request->filled('treasury_id')) {
            $treasury = Treasury::find($request->treasury_id);

            if ($treasury && $treasury->account_id) {
                return Account::find($treasury->account_id);
            }
        }

        // ٢) خزينة الموظف — معرّفُ حسابٍ مباشرةً
        if (auth()->user()->employee_id) {
            $treasuryEmployee = TreasuryEmployee::where('employee_id', auth()->user()->employee_id)->first();

            if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                if ($account = Account::find($treasuryEmployee->treasury_id)) {
                    return $account;
                }
            }
        }

        // ٣) الخزينة الرئيسية من «الحسابات المحاسبية»
        return $this->accountingAccount('main_treasury');
    }

    /**
     * يمنع صرفَ ردٍّ لا تحتمله الخزينة.
     *
     * **لم يكن ثمّة فحصٌ إطلاقاً:** المتحكّم يخصم `$MainTreasury->balance
     * -= $amount` ويحفظ، فخزينةٌ رصيدُها صفرٌ (أو أربعون هللة كما في
     * خزينة مندوبٍ حقيقيّ) تُصرَف منها مئاتٌ فتصير بالسالب. والرصيدُ
     * السالب في خزينةٍ نقدية ليس رقماً خاطئاً وحده: هو مالٌ قيل إنّه
     * خرج ولم يكن موجوداً، فيَفسد به الجردُ النقديّ والقائمةُ معاً.
     *
     * والمنعُ عند الحفظ لا عند العرض: الرصيدُ يتحرّك بين فتح الشاشة
     * وحفظها، فقيمةٌ قُرئت قبل دقيقة لا تصلح إذناً بالصرف.
     *
     * @throws \RuntimeException إن لم يكفِ الرصيد
     */
    protected function assertTreasuryCanCover(?Account $treasury, float $amount): void
    {
        if (! $treasury || $amount <= 0) {
            return;
        }

        $balance = (float) $treasury->balance;

        if ($balance >= $amount) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'رصيد الخزينة «%s» لا يكفي لردّ المبلغ: الرصيد %s والمطلوب %s. اختر خزينةً أخرى، أو أودِع فيها أوّلاً، أو اختر «عدم إرجاع المبلغ».',
            $treasury->name,
            CurrencySettings::format($balance),
            CurrencySettings::format($amount)
        ));
    }

    /**
     * بنودُ المرتجع مقابل فاتورتها الأصلية — انظر `ReturnLimits`.
     *
     * المعامل من بند الفاتورة الأصليّ لا من الشاشة: حلقةُ الحفظ تقرؤه من هناك،
     * فيُفحص بالمعامل نفسِه الذي يُحرَّك به المخزون.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     */
    protected function returnLinesViolation(ReturnLimits $limits, array $items): ?string
    {
        $requested = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $line = $limits->lineFor($productId, $item['product_unit_id'] ?? null);

            $requested[] = [
                'product_id' => $productId,
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'factor' => $line ? ReturnLimits::factorOf($line) : 1.0,
            ];
        }

        return $limits->violation($requested);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        /* الضريبة الشاملة على المستند — الخانةُ العلوية `document_tax_id`
           تُفرَض على كلّ بند حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة.
           انظر `InvoicesController::persistInvoice()`. */
        DocumentTaxCalculator::applyComprehensive($request);

        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.returns.create)

        // ** حدُّ اشتراك فوترة ** — قبل أيّ كتابة، بالقياس الحيّ لا باللقطة
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::RETURN_INVOICES)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        /* ** مرتجعٌ واحد للفاتورة ** — الشاشةُ تمنعه أصلاً، والفحصُ هنا
           لأنّ الحفظ لا يثق بتبويبٍ فُتح قبل أن يصدر المرتجعُ الأوّل أو
           الإشعارُ الدائن. */
        $originalForGuard = Invoice::find($request->invoice_id);
        if ($originalForGuard && ($blocked = $originalForGuard->returnBlockReason())) {
            return redirect()->route('invoices.show', $originalForGuard->id)->with('error', $blocked);
        }

        /* ** نطاق القرب من العميل ** — بصلاحية التجاوز بالاسمين (الجديد
           والقديم) وبالنطاق المضبوط في «إعدادات وحدات العملاء» لا بـ`0.3`
           مكتوبةً في الشيفرة. انظر `ChecksClientProximity`. */
        if ($proximityBlock = $this->clientProximityBlock(
            $request,
            Invoice::find($request->invoice_id)?->client_id,
            'sales.returns.bypass_location'
        )) {
            return redirect()->back()->withInput()->with('error', $proximityBlock);
        }

        /* ** شرط السحب من الخزينة ** — القائمةُ مصفّاةٌ أصلاً، والفحصُ هنا
           لأنّ الحفظ لا يثق بما يصله من النموذج: تبويبٌ قديم أو طلبٌ
           مصنوع يحمل معرّفَ خزينةٍ لا يملك صاحبُه السحب منها. */
        if ($request->filled('treasury_id')) {
            $chosen = Treasury::find($request->treasury_id);

            if ($chosen && ($denied = TreasuryAccessGuard::ownWithdrawBlockReason($chosen->account_id))) {
                return redirect()->back()->withInput()->with('error', $denied);
            }
        }

        // المرفقات قبل أيّ كتابة — انظر `DocumentAttachments`
        DocumentAttachments::validate($request);

        /* ** حدود الردّ ** — قبل المعاملة وقبل حجز الرقم: الصنفُ من الفاتورة،
           والكميةُ موجبة ومجموعُها لكل صنف لا يتجاوز المتاح، والسعرُ لا يتجاوز
           سعر البيع. كان الفحصُ داخل الحلقة بـ`return back()` فيترك المعاملة
           مفتوحة، ويفحص كلَّ سطرٍ وحده. انظر `ReturnLimits`. */
        if (! $originalForGuard) {
            return redirect()->back()->withInput()->with('error', __('sales_invoice.return_limits.invoice_missing'));
        }

        $returnLimits = ReturnLimits::for($originalForGuard);

        if ($limitError = $this->returnLinesViolation($returnLimits, (array) $request->input('items', []))) {
            return redirect()->back()->withInput()->with('error', $limitError);
        }

        try {
            $invoice_orginal = Invoice::find($request->invoice_id);
            $invoice_code = $invoice_orginal->id;

            // ** الإقفال المحاسبي **
            //
            // المرتجع يُولّد قيداً عاكساً، والقيد لا يجوز في فترةٍ أُقفلت
            // وأُصدرت قوائمها. الفحص بتاريخ المستند لا بتاريخ اليوم
            $documentDate = $request->input('invoice_date') ?: now()->format('Y-m-d');

            if ($block = $this->accountingPeriodBlock($documentDate)) {
                return redirect()->back()->withInput()->with('error', $block);
            }

            // ** الخطوة الأولى: رقم المرتجع من «الترقيم المتسلسل» **
            //
            // كان `intval($last->code) + 1` مصفوفاً إلى خمس خانات، ويقرأ
            // آخر صفٍّ في `invoices` كائناً ما كان نوعه — فيأخذ المرتجع
            // رقمه من فاتورة بيع. والقسم `return-invoice` معرَّف في
            // «الترقيم المتسلسل» ببادئته وطوله ولم يكن يُقرأ
            //
            // والرقمُ يُحجز **داخل** المعاملة لا قبلها: `DocumentNumberService::next()`
            // يستهلكه في معاملةٍ متداخلة، فإن رُدّ المرتجعُ بعده عاد العدّادُ
            // معه — وكان يُحجز قبلها فيبقى مستهلَكاً ويترك ثغرةً في التسلسل.
            DB::beginTransaction(); // بدء المعاملة

            // من التسلسل وحده — انظر التعليق نفسه في `InvoicesController::persistInvoice()`
            $code = $this->resolveDocumentNumber(null, self::NUMBER_SECTION);

            // ** تجهيز المتغيرات الرئيسية لحساب الفاتورة **
            $total_amount = 0; // إجمالي المبلغ قبل الخصومات
            $total_discount = 0; // إجمالي الخصومات على البنود
            $items_data = []; // تجميع بيانات البنود
            // $invoiceItems = $invoice->items;
            // ** الخطوة الثانية: معالجة البنود (items) **
            if ($request->has('items') && count($request->items)) {
                foreach ($request->items as $itemKey => $item) {
                    // التحقق من وجود product_id في البند

                    if (! isset($item['product_id'])) {
                        throw new \Exception('معرف المنتج (product_id) مطلوب لكل بند.');
                    }

                    // جلب المنتج
                    $product = Product::find($item['product_id']);
                    if (! $product) {
                        throw new \Exception('المنتج غير موجود: '.$item['product_id']);
                    }
                    // الحصول على المستخدم الحالي
                    $user = Auth::user();

                    /* البند الأصليّ للصنف بوحدته — الحدودُ فُحصت قبل المعاملة
                       (`returnLinesViolation()`)، فالبند موجودٌ حتماً. */
                    $original_item = $returnLimits->lineFor((int) $item['product_id'], $item['product_unit_id'] ?? null);

                    /* المستودع: مستودعُ البند في الفاتورة الأصلية — البضاعة تعود من
                       حيث خرجت. كان المختارَ في رأس الشاشة أو مستودعَ الموظّف، فدخل
                       مرتجعٌ مستودعاً لم تخرج منه البضاعة (مرتجع ٦٩٣: خرجت من ٧
                       ودخلت ٩). والبندُ القديم بلا مستودع يأخذ المصدر المضبوط. */
                    $storeHouse = $original_item->store_house_id ? StoreHouse::find($original_item->store_house_id) : null;
                    $storeHouse = $storeHouse ?: SalesWarehouse::resolve($request->store_house_id, $user);
                    $store_house_id = $storeHouse ? $storeHouse->id : null;
                    $TreasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

                    if ($user && $user->employee_id) {
                        // تحقق مما إذا كان treasury_id فارغًا أو null
                        if ($TreasuryEmployee && $TreasuryEmployee->treasury_id) {
                            $MainTreasury = Account::where('id', $TreasuryEmployee->treasury_id)->first();
                        } else {
                            // إذا كان treasury_id null أو غير موجود، اختر الخزينة الرئيسية
                            $MainTreasury = Account::where('name', 'الخزينة الرئيسية')->first();
                        }
                    } else {
                        // إذا لم يكن المستخدم موجودًا أو لم يكن لديه employee_id، اختر الخزينة الرئيسية
                        $MainTreasury = Account::where('name', 'الخزينة الرئيسية')->first();
                    }
                    // if (!$storeHouse) {
                    //     throw new \Exception('المستودع غير موجود: ' . $item->store_house_id);
                    // }

                    // التحقق مما إذا كان للمستخدم employee_id
                    // الحصول على المستخدم الحالي

                    // حدودُ الكمية والسعر فُحصت قبل المعاملة — انظر `ReturnLimits`
                    // حساب تفاصيل الكمية والأسعار
                    $quantity = floatval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $item_total = $quantity * $unit_price;

                    /* خصم البند — `discount` أو `discount_amount`.
                     *
                     * كانت حلقةُ الإجماليات تقرأ `discount` وحلقةُ الضريبة
                     * تقرأ `discount_amount`، في الدالّة نفسها. والنموذج
                     * يرسل `discount`: فالخصم يدخل الإجمالي ولا يدخل وعاء
                     * الضريبة، فتُحتسب الضريبة على مبلغٍ قبل الخصم. */
                    /* الحسم المطبَّق على البيع يُطبَّق على ردّه: لولا ذلك
                       لرُدَّ للعميل سعرٌ لم يدفعه. وبندُ المرتجع المنقول من
                       فاتورةٍ يحمل خصمَها فيتقدّم على العرض. */
                    $item_discount = $this->lineDiscountWithOffers(
                        $item,
                        $item_total,
                        $this->itemDiscount($item, $item_total),
                        (int) $invoice_orginal->client_id,
                        $itemKey,
                    );

                    // تحديث الإجماليات
                    $total_amount += $item_total;
                    $total_discount += $item_discount;

                    // جلب product_unit_id من الطلب أو من الفاتورة الأصلية
                    $productUnitId = ! empty($item['product_unit_id']) ? $item['product_unit_id'] : ($original_item->product_unit_id ?? null);

                    // ===== استخدام القيم المحفوظة من الفاتورة الأصلية أو جلبها من جدول الوحدات =====
                    $conversionFactor = 1;
                    $unitName = '';

                    // أولاً: محاولة استخدام القيم من النموذج (إذا تم إرسالها)
                    if (! empty($item['unit_name'])) {
                        $unitName = $item['unit_name'];
                    }

                    // ثانياً: استخدام القيم المحفوظة من الفاتورة الأصلية
                    if (empty($unitName) && ! empty($original_item->unit_name)) {
                        $unitName = $original_item->unit_name;
                    }
                    if (! empty($original_item->conversion_factor) && $original_item->conversion_factor > 0) {
                        $conversionFactor = floatval($original_item->conversion_factor);
                    }

                    // ثالثاً: إذا لم تكن القيم موجودة، جلبها من جدول الوحدات
                    if ((empty($unitName) || $conversionFactor == 1) && $productUnitId) {
                        $productUnit = ProductUnit::with('categoryUnit.unit')->find($productUnitId);
                        if ($productUnit && $productUnit->categoryUnit) {
                            if (empty($unitName) && $productUnit->categoryUnit->unit) {
                                $unitName = $productUnit->categoryUnit->unit->name;
                            }
                            if ($conversionFactor == 1 && $productUnit->categoryUnit->conversion_factor > 0) {
                                $conversionFactor = floatval($productUnit->categoryUnit->conversion_factor);
                            }
                        }
                    }

                    // التأكد من أن معامل التحويل صالح
                    if ($conversionFactor <= 0) {
                        $conversionFactor = 1;
                    }

                    // الكمية بالوحدة الأساسية = الكمية المرتجعة × معامل التحويل
                    $quantityInBaseUnit = $quantity * $conversionFactor;

                    // تجهيز اسم المنتج مع الوحدة
                    $itemName = $product->name ?? 'المنتج '.$item['product_id'];
                    if (! empty($unitName)) {
                        $itemName = $product->name.' ('.$unitName.')';
                    }

                    Log::info('معلومات الوحدة للمرتجع', [
                        'product_id' => $item['product_id'],
                        'product_unit_id' => $productUnitId,
                        'unit_name' => $unitName,
                        'conversion_factor' => $conversionFactor,
                        'quantity' => $quantity,
                        'quantity_in_base_unit' => $quantityInBaseUnit,
                    ]);

                    // تجهيز بيانات البند
                    $items_data[] = [
                        'invoice_id' => null, // سيتم تعيينه لاحقًا بعد إنشاء الفاتورة
                        'product_id' => $item['product_id'],
                        'product_unit_id' => $productUnitId, // معرف الوحدة المستخدمة (من الطلب أو الفاتورة الأصلية)
                        'store_house_id' => $store_house_id,
                        'item' => $itemName,
                        'description' => $item['description'] ?? null,
                        'quantity' => $quantity,
                        'quantity_in_base_unit' => $quantityInBaseUnit, // الكمية بالوحدة الأساسية
                        'conversion_factor' => $conversionFactor, // معامل التحويل (من الفاتورة الأصلية)
                        'unit_name' => $unitName, // اسم الوحدة (من الفاتورة الأصلية)
                        'unit_price' => $unit_price,
                        'discount' => $item_discount,
                        'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                        'tax_1' => floatval($item['tax_1'] ?? 0),
                        'tax_2' => floatval($item['tax_2'] ?? 0),
                        'total' => $item_total - $item_discount,
                    ];
                }
            }

            /* ══════════ ترتيبُ الحساب ══════════
             *
             *     إجمالي الأصناف − خصومات الأصناف ⇒ صافي الأصناف
             *     − الخصم العامّ                   ⇒ صافي التوريد
             *     + الشحن                          ⇒ الوعاء الضريبي
             *     × النسبة                         ⇒ الضريبة
             *
             * فالخصمُ العامّ يسبق الوعاء الضريبي. والمرتجعُ يعكس فاتورةً
             * حُسبت بهذا الترتيب، فلو خالفه لعكس مبلغاً غير الذي قُيّد.
             */
            $lineNets = [];

            foreach ($request->items as $itemKey => $item) {
                $lineBase = floatval($item['quantity']) * floatval($item['unit_price']);

                $lineNets[$itemKey] = max(0.0, $lineBase - $this->lineDiscountWithOffers(
                    $item,
                    $lineBase,
                    $this->itemDiscount($item, $lineBase),
                    (int) $invoice_orginal->client_id,
                    $itemKey,
                ));
            }

            $net_items = round(array_sum($lineNets), 2);

            // ** الخطوة الثالثة: الخصم العامّ — قبل الوعاء الضريبي **
            $invoice_discount = 0;
            if ($request->has('discount_amount') && $request->discount_amount > 0) {
                if ($request->has('discount_type') && $request->discount_type === 'percentage') {
                    // النسبة من **صافي الأصناف**، كما في الفاتورة وعرض السعر
                    $invoice_discount = ($net_items * floatval($request->discount_amount)) / 100;
                } else {
                    $invoice_discount = floatval($request->discount_amount);
                }

                $invoice_discount = min($invoice_discount, max(0.0, $net_items));
            }

            // الخصومات الإجمالية
            $final_total_discount = $total_discount + $invoice_discount;

            // حساب المبلغ بعد الخصم
            $amount_after_discount = $total_amount - $final_total_discount;

            /* ** ضريبة البنود ** — النسبة **ونوعها** من «إعدادات الضريبة».
             *
             * كان الحساب `المبلغ × النسبة ÷ 100` دائماً، فيُضيف الضريبة فوق
             * السعر ولو كان نوعها «متضمَّنة» — والمرتجع يعكس فاتورةً
             * حُسبت بالنوع الصحيح، فيخرج بمبلغٍ لا يطابق ما يعكسه.
             */
            $discountShares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $invoice_discount);

            $tax_total = 0;
            $included_item_tax = 0;
            $documentTaxIds = [];

            foreach ($request->items as $itemKey => $item) {
                $taxableBase = max(0.0, $lineNets[$itemKey] - ($discountShares[$itemKey] ?? 0.0));

                $line = DocumentTaxCalculator::line(
                    1.0,
                    $taxableBase,
                    0.0,
                    [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null],
                    [floatval($item['tax_1'] ?? 0), floatval($item['tax_2'] ?? 0)],
                );

                $tax_total += $line['tax'];
                $included_item_tax += $line['base'] - $line['net'];

                $documentTaxIds[] = $item['tax_1_id'] ?? null;
                $documentTaxIds[] = $item['tax_2_id'] ?? null;
            }

            $tax_total = round($tax_total, 2);
            $included_item_tax = round($included_item_tax, 2);

            // الضريبة المتضمَّنة داخل الأسعار تُطرح من المجموع فلا تُحسب مرّتين
            $total_amount = round($total_amount - $included_item_tax, 2);
            $amount_after_discount = round($total_amount - $final_total_discount, 2);

            Log::info('إجمالي الضريبة المحسوبة:', [
                'tax_total' => $tax_total,
                'included_item_tax' => $included_item_tax,
            ]);

            // ** إضافة تكلفة الشحن (إذا وجدت) **
            $shipping_cost = floatval($request->shipping_cost ?? 0);

            /* ضريبة الشحن بالنسبة المضبوطة — كانت `* 0.15` مكتوبةً في
               الشيفرة، ومشروطةً بـ`$tax_total > 0` فمرتجعُ بضاعةٍ معفاة
               بشحنٍ خاضع لا ضريبة على شحنه */
            $shipping_tax = DocumentTaxCalculator::shippingTax(
                $shipping_cost,
                /* «بلا ضريبة» اختيارٌ لا سكوت — انظر `shippingTaxIds()` */
                DocumentTaxCalculator::shippingTaxIds(
                    $request->has('shipping_tax_id'),
                    $request->input('shipping_tax_id'),
                    $documentTaxIds
                ),
            );

            // ** إضافة ضريبة الشحن إلى tax_total **
            $tax_total = round($tax_total + $shipping_tax, 2);

            // ** الحساب النهائي للمجموع الكلي **
            $total_with_tax = $amount_after_discount + $tax_total + $shipping_cost;

            // ** حساب المبلغ المستحق (due_value) بعد خصم الدفعة المقدمة **
            $advance_payment = floatval($request->advance_payment ?? 0);
            $due_value = $total_with_tax - $advance_payment;

            // ** تحديد حالة الفاتورة بناءً على المدفوعات **
            $payment_status = 3; // الحالة الافتراضية (مسودة)
            $is_paid = false;

            if ($advance_payment > 0 || $request->has('is_paid')) {
                // حساب إجمالي المدفوعات
                $total_payments = $advance_payment;

                if ($request->has('is_paid') && $request->is_paid) {
                    $total_payments = $total_with_tax;
                    $advance_payment = $total_with_tax;
                    $due_value = 0;
                    $payment_status = 1; // مكتمل
                    $is_paid = true;
                } else {
                    // إذا كان هناك دفعة مقدمة لكن لم يتم اكتمال المبلغ
                    $payment_status = 2; // غير مكتمل
                    $is_paid = false;
                }
            }

            // إذا تم تحديد حالة دفع معينة في الطلب
            if ($request->has('payment_status')) {
                switch ($request->payment_status) {
                    case 4: // تحت المراجعة
                        $payment_status = 4;
                        $is_paid = false;
                        break;
                    case 5: // فاشلة
                        $payment_status = 5;
                        $is_paid = false;
                        break;
                }
            }

            $invoice = Invoice::create([
                /* العميلُ والفرعُ والموظّف من الفاتورة الأصلية. كان `client_id` من
                   الطلب — مرتجعٌ مصنوعٌ بعميلٍ آخر يُنزل ذمّةَ غير صاحب الفاتورة —
                   والفرعُ فرعَ رأس الشاشة، والموظّفُ فارغاً في المرتجعات كلّها. */
                'client_id' => $invoice_orginal->client_id,
                'branch_id' => $invoice_orginal->branch_id ?: current_branch_id(),
                'employee_id' => $request->employee_id ?: $invoice_orginal->employee_id,
                // العملة من «العملات»، وتُجمَّد مع المستند
                'currency' => $this->defaultCurrency(),
                'due_value' => $due_value,
                'reference_number' => $invoice_code,
                'code' => $code,
                'type' => 'returned',
                // رقم الفاتورة الأصلية — تُستبعد به الأصليةُ من شاشة السداد
                // المجمَّع (راجع SalesPaymentController::getClientUnpaidInvoices)
                // كان `$invoice_refrence->id ?? null` والمتغيّر لا يُعرَّف إلّا بعد الإنشاء — فارغاً دائماً
                'reference_id' => $invoice_orginal->id,
                'invoice_date' => $request->invoice_date,
                'issue_date' => $request->issue_date,
                'terms' => $request->terms ?? 0,
                'notes' => $request->notes,
                'payment_status' => 4,
                'is_paid' => $is_paid,
                'created_by' => Auth::id(),
                'account_id' => $request->account_id,
                /* الخصم كلُّه — خصمُ البنود وخصمُ المستند معاً.
                   كان `$invoice_discount` وحده (خصم المستند)، وخصمُ البنود
                   يُمرَّر تحت `total_discount` وهو ليس عموداً في `invoices`
                   ولا في `$fillable` فيُرمى بصمت. والقيدُ يقرأ
                   `$invoice->discount_amount` — أي `null` دائماً — فسطرُ
                   عكس الخصم لا يُكتب أبداً ويخرج القيد غير موازن. */
                'discount_amount' => $final_total_discount,
                'discount_type' => $request->has('discount_type') ? ($request->discount_type === 'percentage' ? 2 : 1) : 1,
                'advance_payment' => $advance_payment,
                'payment_type' => $request->payment_type ?? 1,
                'shipping_cost' => $shipping_cost,
                'shipping_tax' => $shipping_tax,
                'tax_type' => $request->tax_type ?? 1,
                'payment_method' => $request->payment_method,

                'received_date' => $request->received_date,
                'subtotal' => $total_amount,
                'tax_total' => $tax_total,
                'grand_total' => $total_with_tax,
                // 'advance_payment' => $advance_payment,
            ]);

            $invoice->save();
            $invoice_orginal->returned_payment += $invoice->grand_total;

            $invoice_orginal->save();
            // حساب الضريبة
            foreach ($request->items as $item) {
                // حساب الإجمالي لكل منتج (السعر × الكمية)
                $item_subtotal = $item['unit_price'] * $item['quantity'];

                // حساب الضرائب بناءً على البيانات القادمة من `request`
                $tax_ids = ['tax_1_id', 'tax_2_id'];
                foreach ($tax_ids as $tax_id) {
                    if (! empty($item[$tax_id])) {
                        // التحقق مما إذا كان هناك ضريبة
                        $tax = TaxSitting::find($item[$tax_id]);

                        if ($tax) {
                            $tax_value = ($tax->tax / 100) * $item_subtotal; // حساب قيمة الضريبة

                            // حفظ الضريبة في جدول TaxInvoice
                            TaxInvoice::create([
                                'name' => $tax->name,
                                'invoice_id' => $invoice->id,
                                'type' => $tax->type,
                                'rate' => $tax->tax,
                                'value' => $tax_value,
                                'type_invoice' => 'invoice',
                            ]);
                        }
                    }
                }
            }

            // ** تحديث رصيد حساب أبناء العميل **

            // إضافة المبلغ الإجمالي للفاتورة إلى رصيد أبناء العميل

            // ** الخطوة الخامسة: إنشاء سجلات البنود (items) للفاتورة **
            foreach ($items_data as $item) {
                $item['invoice_id'] = $invoice->id;
                $item_invoice = InvoiceItem::create($item);
                $client_name = Client::find($invoice->client_id);

                $proudect = Product::where('id', $item['product_id'])->first();

                /* الأصناف بلا رصيد — الخدمي والرقمي — لا تدخل المخزون.
                 *
                 * كان صفُّ الرصيد يُفتح لكل بند بلا نظرٍ إلى النوع، ومنذ
                 * أن صار `StockMovementGuardObserver` يحرس الجدول صار
                 * إرجاعُ خدمةٍ يرمي «لا يمكن إدخال ... في المخزون» ويُسقط
                 * المرتجع كلّه. وحركةُ المخزون أدناه محروسةٌ بالنوع أصلاً،
                 * فالصفُّ كان يُفتح ثمّ لا يُستعمَل. */
                $productDetails = null;

                if (StockEligibility::isStockable($proudect ?: $item['product_id'])) {
                    // ** تحديث المخزون بناءً على store_house_id المحدد في البند **
                    $productDetails = ProductDetails::where('store_house_id', $item['store_house_id'])
                        ->where('product_id', $item['product_id'])
                        ->first();

                    if (! $productDetails) {
                        $productDetails = ProductDetails::create([
                            'store_house_id' => $item['store_house_id'],
                            'product_id' => $item['product_id'],
                            'quantity' => 0,
                        ]);
                    }
                }

                if ($proudect->type == 'products') {
                    // ===== استخدام الكمية بالوحدة الأساسية المحفوظة =====
                    // ملاحظة: نستخدم القيم المحفوظة في items_data من الفاتورة الأصلية
                    $quantityInBaseUnit = $item['quantity_in_base_unit'] ?? $item['quantity'];
                    $conversionFactor = $item['conversion_factor'] ?? 1;

                    // ** حساب المخزون قبل وبعد التعديل (زيادة بسبب المرتجع) **
                    // استخدام الكمية بالوحدة الأساسية لتحديث المخزون
                    $stock_before = $productDetails->quantity;
                    $stock_after = $stock_before + $quantityInBaseUnit;

                    // ** تحديث المخزون بزيادة الكمية (بالوحدة الأساسية) **
                    $productDetails->increment('quantity', $quantityInBaseUnit);

                    // ** تسجيل حركة المخزون للإرجاع **
                    $wareHousePermits = new WarehousePermits;
                    $wareHousePermits->permission_source_id = PermissionSources::SALES_RETURN; // استخدام الثابت للمصدر
                    $wareHousePermits->permission_date = $documentDate;
                    $wareHousePermits->number = $invoice->id;
                    $wareHousePermits->grand_total = $invoice->grand_total;
                    // مستودعُ البند نفسِه — كان آخرَ مستودعٍ مرّت به حلقةُ البنود الأولى
                    $wareHousePermits->store_houses_id = $item['store_house_id'];
                    $wareHousePermits->client_id = $invoice->client_id;
                    $wareHousePermits->reference_type = 'SalesReturn';
                    $wareHousePermits->reference_id = $invoice->id;
                    $wareHousePermits->created_by = auth()->user()->id;
                    // إذنٌ صادرٌ عن مستند لا طلبٌ ينتظر موافقة: الحركة وقعت
                    // وقُيِّدت محاسبياً مع المستند، فيُولَد موافَقاً عليه.
                    // (`apporod` هي قيمة الموافقة المعتمدة في النظام.)
                    $wareHousePermits->status = 'apporod';
                    $wareHousePermits->save();

                    // ** تسجيل تفاصيل حركة المخزون (بالوحدة الأساسية) **
                    WarehousePermitsProducts::create([
                        'quantity' => $quantityInBaseUnit, // الكمية بالوحدة الأساسية
                        'total' => $item['total'],
                        'unit_price' => $item['unit_price'],
                        'product_id' => $item['product_id'],
                        'stock_before' => $stock_before,
                        'stock_after' => $stock_after,
                        'warehouse_permits_id' => $wareHousePermits->id,
                    ]);

                    Log::info('تحديث المخزون للمرتجع', [
                        'product_id' => $item['product_id'],
                        'product_unit_id' => $productUnitId,
                        'original_quantity' => $item['quantity'],
                        'conversion_factor' => $conversionFactor,
                        'quantity_in_base_unit' => $quantityInBaseUnit,
                        'stock_before' => $stock_before,
                        'stock_after' => $stock_after,
                    ]);
                }
            }

            // جلب بيانات الموظف والمستخدم
            $employee_name = Employee::where('id', $invoice->employee_id)->first();
            $user_name = User::where('id', $invoice->created_by)->first();
            $client_name = Client::find($invoice->client_id);
            // جلب جميع المنتجات المرتبطة بالفاتورة
            $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)->get();

            // تجهيز قائمة المنتجات
            $productsList = '';
            foreach ($invoiceItems as $item) {
                $product = Product::find($item['product_id']);
                $productName = $product ? $product->name : 'منتج غير معروف';
                $productsList .= "▫️ *{$productName}* - الكمية: {$item->quantity}, السعر: {$item->unit_price} \n";
            }

            /* المرفقات — في `assets/uploads/sales/returns` باسمٍ وامتدادٍ
               يولّدهما الخادم؛ انظر `DocumentAttachments`. */
            if ($attachmentFiles = DocumentAttachments::files($request)) {
                $invoice->attachments = DocumentAttachments::encode(
                    DocumentAttachments::storeMany($attachmentFiles, DocumentAttachments::SALES_RETURN)
                );
                $invoice->save();
            }

            // ====================================================================
            // القيود المحاسبية لمرتجع المبيعات (عكس فاتورة المبيعات)
            // ====================================================================

            /*
             * الحسابات من شاشة «الحسابات المحاسبية» وحدها.
             *
             * كانت تُقرأ بمعرّفاتها المكتوبة (45، 37، 50، 16)، فما يربطه
             * المحاسب في الشاشة لا يسري على قيدٍ واحد من قيود المرتجعات.
             * والرسالة عند الغياب صارت تسمّي الحساب وتدلّ على شاشته.
             */
            $salesReturnsAccount = $this->requireAccountingAccount('sales_returns');
            $vatAccount = $this->requireAccountingAccount('vat_payable');
            $costAccount = $this->requireAccountingAccount('cost_of_sales');

            // حساب المخزون: المضبوط في الإعدادات، وإلا حساب المستودع
            // الرئيسي — فالمنشأة التي تُنشئ حساباً لكل مستودع لا تربط
            // مفتاحاً عامّاً للمخزون
            $inventoryAccount = $this->accountingAccount('inventory');
            if (! $inventoryAccount) {
                $mainStoreHouse = StoreHouse::where('major', 1)->first();
                if ($mainStoreHouse) {
                    $inventoryAccount = Account::where('storehouse_id', $mainStoreHouse->id)->first();
                }
                if (! $inventoryAccount) {
                    $inventoryAccount = $this->requireAccountingAccount('inventory');
                }
            }

            // البحث عن حساب العميل
            $clientAccount = Account::ledger()->where('client_id', $invoice->client_id)->first();
            if (! $clientAccount) {
                throw new \Exception('حساب العميل غير موجود');
            }

            // جلب الفاتورة المرجعية للتحقق من حالة الدفع
            $invoice_refrence = Invoice::find($request->invoice_id);

            // ====================================================================
            // القيد الأول: قيد مرتجع المبيعات (عكس قيد المبيعات الأصلي)
            // ====================================================================
            $clientName = $clientAccount ? $clientAccount->name : 'العميل';
            // «نوع المستند رقمه - الطرف»؛ المبالغ في عمودَي مدين/دائن والتاريخ في `date`
            /* رقمُ المرتجع ورقمُ فاتورته الأصلية معاً في وصف كلّ قيد: من يقرأ
               دفتر اليومية أو كشف الحساب يرى «مرتجع RET-5» ولا يعرف أيَّ
               فاتورةٍ ردّ إلا بفتح المستند. */
            $returnRef = $invoice->code.($invoice_refrence?->code
                ? ' للفاتورة الأصلية رقم '.$invoice_refrence->code
                : '');
            $returnDescription = 'فاتورة مرتجع مبيعات رقم '.$returnRef.' - '.$clientName;

            $journalEntryReturn = JournalEntry::create([
                'reference_number' => $invoice->code,
                // بتاريخ المستند: الإقفالُ المحاسبيّ يُفحص به، والإيرادُ يُعكس في شهره
                'date' => $documentDate,
                'description' => $returnDescription,
                'status' => 1,
                'currency' => $this->documentCurrency($invoice),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'reference_type' => ReferenceType::SALES_RETURN,
                'linking_type' => LinkingType::SINGLE,
                'created_by_employee' => Auth::id(),
            ]);

            // 1. مردود المبيعات (مدين) - عكس حساب المبيعات (المبلغ الكامل قبل الخصم)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntryReturn->id,
                'account_id' => $salesReturnsAccount->id,
                'description' => 'مردود مبيعات - مرتجع رقم '.$returnRef,
                'debit' => $invoice->subtotal, // المبلغ الكامل قبل الخصم من الفاتورة
                'credit' => 0,
                'is_debit' => true,
            ]);

            // 2. خصم مسموح به (دائن) - عكس القيد (فقط إذا كان أكبر من صفر)
            if ($invoice->discount_amount > 0) {
                // استخدام ثابت للحسابات المتكررة
                // كان ٥٢ مكتوباً في الشيفرة، وهو مفتاحٌ في «الحسابات المحاسبية»
                $discountAccountId = $this->requireAccountingAccountId('allowed_discount');
                $discountAccountModel = Account::find($discountAccountId);
                if ($discountAccountModel) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journalEntryReturn->id,
                        'account_id' => $discountAccountId,
                        'description' => 'عكس خصم مسموح به - مرتجع رقم '.$returnRef,
                        'debit' => 0,
                        'credit' => $invoice->discount_amount,
                        'is_debit' => false,
                    ]);

                    Log::info('تم إنشاء قيد عكس الخصم المسموح به للمرتجع', [
                        'invoice_id' => $invoice->id,
                        'discount_amount' => $invoice->discount_amount,
                    ]);
                }
            }

            /* ٣. عكسُ القيمة المضافة (مدين) — **مركَّبةً لا مجموعة**،
             *    بالتركيب نفسِه الذي في قيد الفاتورة.
             *
             * كان `tax_total` كاملاً في سطرٍ واحد على `vat_payable`، وقد
             * ضُمَّت إليه ضريبةُ الشحن قبل الحفظ (`$tax_total + $shipping_tax`).
             * فالفاتورةُ تُرحّل ضريبةَ شحنها إلى «ضريبة شحن مبيعات» ومرتجعُها
             * يعكسها من «ضريبة المبيعات»: حسابٌ يُشحَن ولا يُفرَّغ أبداً،
             * وآخر يُفرَّغ بما لم يدخله. وإقرارُ الضريبة الذي يفصل الوعاءين
             * لا يُطابَق.
             *
             * السطران مجموعُهما `tax_total` نفسُه، فالتوازن لا يتغيّر —
             * الذي يتغيّر أنّ العكسَ يقع حيث وقع الأصل.
             * انظر `ResolvesAccountingAccounts::shippingVatAccountId()`.
             */
            $return_shipping_tax = round((float) ($invoice->shipping_tax ?? 0), 2);
            $return_items_tax = round((float) $invoice->tax_total - $return_shipping_tax, 2);

            if ($return_items_tax > 0) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntryReturn->id,
                    'account_id' => $vatAccount->id,
                    'description' => 'عكس ضريبة القيمة المضافة على البنود - مرتجع رقم '.$returnRef,
                    'debit' => $return_items_tax,
                    'credit' => 0,
                    'is_debit' => true,
                ]);
            }

            if ($return_shipping_tax > 0) {
                // حسابُ ضريبة الشحن إن رُبط، وإلّا حسابُ الضريبة نفسُه
                $shippingVatAccount = Account::find($this->shippingVatAccountId());

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntryReturn->id,
                    'account_id' => $shippingVatAccount->id,
                    'description' => 'عكس ضريبة شحن مبيعات - مرتجع رقم '.$returnRef,
                    'debit' => $return_shipping_tax,
                    'credit' => 0,
                    'is_debit' => true,
                ]);            }

            /* ٤. مردود إيراد الشحن (مدين).
             *
             * الشحن يدخل `grand_total` للمرتجع كما يدخله للفاتورة، ولم يكن
             * له طرفٌ في القيد: يُخصَم من ذمّة العميل ولا يُعكس من إيراد
             * الشحن. الحساب `shipping_revenue_returns` معرَّفٌ في «الحسابات
             * المحاسبية» لهذا الغرض ولم يكن يُقيَّد عليه شيء.
             */
            if (($invoice->shipping_cost ?? 0) > 0) {
                $shippingReturnsAccount = $this->requireAccountingAccount('shipping_revenue_returns');

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntryReturn->id,
                    'account_id' => $shippingReturnsAccount->id,
                    'description' => 'مردود إيراد شحن - مرتجع رقم '.$returnRef,
                    'debit' => $invoice->shipping_cost,
                    'credit' => 0,
                    'is_debit' => true,
                ]);
            }

            // 5. حساب العميل (دائن) - تخفيض المديونية بإجمالي المرتجع
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntryReturn->id,
                'account_id' => $clientAccount->id,
                'description' => 'تخفيض مديونية العميل - مرتجع رقم '.$returnRef,
                'debit' => 0,
                'credit' => $invoice->grand_total, // المبلغ النهائي من الفاتورة
                'is_debit' => false,
            ]);

            /* لا تعديلَ يدويّاً للأرصدة هنا ولا في بقيّة القيود: كلُّ سطرٍ يُعيد حسابَ
               رصيد حسابه من القيود عند إنشائه (`JournalEntryDetail::boot()`). وكانت
               `balance -= …` على نسخٍ حُمّلت قبل السطور، فتكتب فوق المحسوب قيمةً
               قديمة — وأيُّ انحرافٍ سابق في الرصيد يُعاد كتابتُه. */

            // ====================================================================
            // إنشاء قيد محاسبي منفصل لكل نوع ضريبة (عكس القيد الضريبي للمبيعات)
            // ====================================================================
            $taxJournalEntriesToCreate = []; // تعريف المتغير
            if (! empty($taxJournalEntriesToCreate)) {
                foreach ($taxJournalEntriesToCreate as $taxData) {
                    if ($taxData['total_value'] > 0) {
                        $taxJournalEntry = JournalEntry::create([
                            'reference_number' => $invoice->code,
                            'date' => now(),
                            'description' => 'عكس '.$taxData['tax_name'].' - مرتجع رقم '.$returnRef,
                            'status' => 1,
                            'currency' => $this->documentCurrency($invoice),
                            'client_id' => $invoice->client_id,
                            'invoice_id' => $invoice->id,
                            'reference_type' => ReferenceType::SALES_RETURN,
                            'linking_type' => LinkingType::SINGLE,
                            'created_by_employee' => Auth::id(),
                        ]);

                        // الحساب الضريبي (مدين) - عكس الضريبة المحصلة
                        JournalEntryDetail::create([
                            'journal_entry_id' => $taxJournalEntry->id,
                            'account_id' => $this->requireAccountingAccountId('vat_payable'),
                            'description' => 'عكس '.$taxData['tax_name'].' - مرتجع رقم '.$returnRef,
                            'debit' => $taxData['total_value'],
                            'credit' => 0,
                            'is_debit' => true,
                        ]);

                        // حساب العميل (دائن) - جزء من تخفيض المديونية
                        JournalEntryDetail::create([
                            'journal_entry_id' => $taxJournalEntry->id,
                            'account_id' => $clientAccount->id,
                            'description' => 'تخفيض مديونية العميل - مرتجع رقم '.$returnRef,
                            'debit' => 0,
                            'credit' => $taxData['total_value'],
                            'is_debit' => false,
                        ]);

                        Log::info('تم إنشاء قيد ضريبي منفصل للمرتجع', [
                            'invoice_id' => $invoice->id,
                            'tax_name' => $taxData['tax_name'],
                            'tax_value' => $taxData['total_value'],
                        ]);
                    }
                }
            }

            // ====================================================================
            // القيد الثاني: قيد تكلفة المبيعات والمخزون (منفصل وبسعر التكلفة)
            // ====================================================================

            try {
                Log::info('بدء حساب قيد المخزون وتكلفة المبيعات للمرتجع', ['invoice_id' => $invoice->id]);

                $costTotal = 0; // إجمالي تكلفة المرتجع بسعر التكلفة
                $lineCosts = []; // الاحتفاظ بتفاصيل التكلفة لكل بند
                $costByStore = []; // تكلفةُ ما عاد إلى كل مستودع — لكلٍّ حسابُ مخزونه
                $inventoryAccountId = null; // حساب المخزون المرتبط بالمستودع

                // جلب البنود من قاعدة البيانات
                $invoiceItemsForCost = InvoiceItem::where('invoice_id', $invoice->id)->get();

                foreach ($invoiceItemsForCost as $costItem) {
                    $productId = $costItem->product_id;
                    $storeHouseId = $costItem->store_house_id;
                    $quantity = $costItem->quantity;

                    $product = Product::find($productId);

                    // البحث عن حساب المخزون من جدول الحسابات باستخدام storehouse_id
                    if ($storeHouseId && $inventoryAccountId === null) {
                        $itemInventoryAccount = Account::where('storehouse_id', $storeHouseId)->first();
                        if ($itemInventoryAccount) {
                            $inventoryAccountId = $itemInventoryAccount->id;
                        }
                    }

                    // ===== استخدام القيم المحفوظة في عنصر الفاتورة =====
                    // ملاحظة مهمة: نستخدم القيم المحفوظة بدلاً من إعادة حسابها
                    // لأن البيانات (معامل التحويل، الوحدات) قد تتغير لاحقاً
                    $conversionFactor = floatval($costItem->conversion_factor ?? 1);
                    if ($conversionFactor <= 0) {
                        $conversionFactor = 1;
                    }

                    // استخدام الكمية بالوحدة الأساسية المحفوظة أو حسابها
                    $quantityInBaseUnit = $costItem->quantity_in_base_unit ?? ($quantity * $conversionFactor);

                    /* سعرُ التكلفة لا سعرُ الشراء: ما قُيّد على بند الفاتورة
                       الأصلية نفسه، وإلّا متوسّطُ التكلفة الآن (`ResolvesSaleCost`).
                       فيعكس المرتجعُ قيدَ البيع بالرقم ذاته. */
                    $unitCost = $this->returnUnitCost($request->invoice_id ? (int) $request->invoice_id : null, $product ?? $productId);

                    if ($unitCost <= 0) {
                        Log::warning('لم يتم العثور على سعر تكلفة للمنتج، تم التعيين إلى صفر', ['product_id' => $productId]);
                    }

                    $this->freezeLineCost($costItem, $unitCost);

                    // حساب تكلفة السطر باستخدام الكمية بالوحدة الأساسية (مثل البيع)
                    $lineCost = $quantityInBaseUnit * $unitCost;
                    $costTotal += $lineCost;
                    $costByStore[(int) $storeHouseId] = ($costByStore[(int) $storeHouseId] ?? 0) + $lineCost;

                    // سجل تفاصيل حساب التكلفة لكل بند
                    Log::info('حساب تكلفة المبيعات للمرتجع - بند', [
                        'product_id' => $productId,
                        'product_unit_id' => $costItem->product_unit_id,
                        'quantity_original' => $quantity,
                        'conversion_factor' => $costItem->product_unit_id ? ($productUnit->categoryUnit->conversion_factor ?? 1) : 1,
                        'quantity_in_base_unit' => $quantityInBaseUnit,
                        'unit_cost' => $unitCost,
                        'line_cost' => $lineCost,
                    ]);

                    $lineCosts[] = [
                        'product_id' => $productId,
                        'product_unit_id' => $costItem->product_unit_id,
                        'quantity' => $quantity, // الكمية الأصلية
                        'quantity_in_base_unit' => $quantityInBaseUnit, // الكمية بالوحدة الأساسية
                        'unit_cost' => $unitCost,
                        'line_cost' => $lineCost,
                    ];
                }

                /* حسابُ المخزون **لكل مستودع** — البنود تعود إلى مستودعاتها الأصلية،
                   فيُقيَّد كلُّ مستودعٍ على حسابه. كان حسابَ آخر مستودعٍ مرّت به الحلقة.
                   ومستودعٌ بلا حساب يُقيَّد على حساب الرئيسي، ثمّ على حساب «المخزون». */
                $mainStoreHouse = StoreHouse::where('major', 1)->first();
                $fallbackInventoryAccountId = ($mainStoreHouse ? Account::where('storehouse_id', $mainStoreHouse->id)->value('id') : null)
                    ?: ($inventoryAccount->id ?? null);

                $inventoryDebits = [];

                foreach ($costByStore as $storeId => $amount) {
                    $accountId = ($storeId ? Account::where('storehouse_id', $storeId)->value('id') : null) ?: $fallbackInventoryAccountId;

                    if ($accountId) {
                        $inventoryDebits[$accountId] = ($inventoryDebits[$accountId] ?? 0) + $amount;
                    }
                }

                // تقريبُ كلِّ سطرٍ ثمّ جمعُها — فالدائنُ يساوي مجموعَ المدين المقرَّب
                $inventoryDebits = array_filter(array_map(fn ($amount) => round($amount, 2), $inventoryDebits), fn ($amount) => $amount > 0);
                $costTotal = round(array_sum($inventoryDebits), 2);

                if ($inventoryDebits && $costTotal > 0) {
                    // إنشاء قيد المخزون وتكلفة المبيعات للمرتجع
                    $costJournalEntry = JournalEntry::create([
                        'reference_number' => $invoice->code,
                        'date' => $documentDate,
                        'description' => 'تكلفة مبيعات - مرتجع رقم '.$returnRef.' - '.$clientName,
                        'status' => 1,
                        'currency' => $this->documentCurrency($invoice),
                        'client_id' => $invoice->client_id,
                        'invoice_id' => $invoice->id,
                        'reference_type' => ReferenceType::SALES_RETURN,
                        'linking_type' => LinkingType::SINGLE,
                        'created_by_employee' => Auth::id(),
                    ]);

                    // 1. المخزون (مدين) - زيادة المخزون بسعر التكلفة، لكل مستودعٍ حسابُه
                    foreach ($inventoryDebits as $accountId => $amount) {
                        JournalEntryDetail::create([
                            'journal_entry_id' => $costJournalEntry->id,
                            'account_id' => $accountId,
                            'description' => 'إرجاع البضاعة للمخزون - مرتجع رقم '.$returnRef,
                            'debit' => $amount,
                            'credit' => 0,
                            'is_debit' => true,
                        ]);
                    }

                    // 2. تكلفة المبيعات (دائن) - عكس التكلفة بسعر التكلفة
                    JournalEntryDetail::create([
                        'journal_entry_id' => $costJournalEntry->id,
                        'account_id' => $this->requireAccountingAccountId('cost_of_sales'),
                        'description' => 'عكس تكلفة المبيعات - مرتجع رقم '.$returnRef,
                        'debit' => 0,
                        'credit' => $costTotal,
                        'is_debit' => false,
                    ]);

                    Log::info('تم إنشاء قيد المخزون وتكلفة المبيعات للمرتجع', [
                        'invoice_id' => $invoice->id,
                        'cost_total' => $costTotal,
                        'inventory_debits' => $inventoryDebits,
                        'lines' => $lineCosts,
                    ]);
                } else {
                    Log::warning('تم تخطي إنشاء قيد المخزون لعدم توفر حساب مخزون أو عدم وجود تكلفة موجبة', [
                        'invoice_id' => $invoice->id,
                        'cost_by_store' => $costByStore,
                        'cost_total' => $costTotal,
                    ]);
                }

            } catch (\Exception $eCost) {
                Log::error('خطأ أثناء إنشاء قيد المخزون وتكلفة المبيعات للمرتجع', [
                    'invoice_id' => $invoice->id ?? null,
                    'message' => $eCost->getMessage(),
                    'trace' => $eCost->getTraceAsString(),
                ]);

                /* لا يُبتلع: مرتجعٌ بلا عكس تكلفته يُدخل البضاعةَ المستودعَ ويُبقي
                   تكلفةَ بيعٍ رُدّ في الدفاتر — فالمرتجع كلُّه لا يُحفظ. */
                throw $eCost;
            }

            // ====================================================================
            // معالجة الخزينة والقيود العكسية للمبلغ المدفوع
            // ====================================================================

            /* ما يُردّ نقداً = قيمةُ المرتجع، بسقف ما قُبض فعلاً من الفاتورة (سوى
               السندات الفاشلة). كان فرعُ «مدفوعة جزئياً» يردّ **كلَّ** المقبوض مهما صغر
               المرتجع: مرتجعٌ بعشرين سحب خمسين من الخزينة. وما زاد من قيمة المرتجع
               على المردود يُخفّض المستحقَّ في حساب العميل — القرار المعتمد. */
            $paidAmountFromOriginal = round(min(
                (float) $invoice->grand_total,
                (float) PaymentsProcess::where('invoice_id', $invoice_refrence->id)
                    ->where('type', 'client payments')
                    ->where('payment_status', '!=', 5)
                    ->sum('amount')
            ), 2);

            // التحقق من خيار إرجاع المبلغ
            $shouldReturnAmount = $request->has('return_amount_option') && $request->return_amount_option === 'return';

            // إذا كانت الفاتورة مدفوعة بالكامل وتم تفعيل "إرجاع المبلغ للعميل"
            /* الفرعان بالمقبوض لا بحالة الدفع المخزَّنة: فاتورةٌ «مدفوعة» من المحفظة
               لا نقدَ فيها يُردّ، وحالةٌ متقادمة لا تقرّر صرفاً من الخزينة. */
            if ($invoice_refrence && $shouldReturnAmount && $paidAmountFromOriginal > 0 && abs($paidAmountFromOriginal - (float) $invoice->grand_total) < 0.005) {
                // تحديد الخزينة المستخدمة للإرجاع — قرارٌ واحد للفرعين
                $MainTreasury = $this->refundTreasuryAccount($request);

                $this->assertTreasuryCanCover($MainTreasury, (float) $invoice->grand_total);

                if ($MainTreasury) {
                    // إنشاء قيد الخزينة منفصل
                    $treasuryJournalEntry = JournalEntry::create([
                        'reference_number' => $invoice->code,
                        'date' => $documentDate,
                        'description' => 'صرف نقدية - مرتجع رقم '.$returnRef,
                        'status' => 1,
                        'currency' => $this->documentCurrency($invoice),
                        'client_id' => $invoice->client_id,
                        'invoice_id' => $invoice->id,
                        'reference_type' => ReferenceType::SALES_RETURN,
                        'linking_type' => LinkingType::SINGLE,
                        'created_by_employee' => Auth::id(),
                    ]);

                    // الخزينة (دائن) - صرف النقدية للعميل
                    JournalEntryDetail::create([
                        'journal_entry_id' => $treasuryJournalEntry->id,
                        'account_id' => $MainTreasury->id,
                        'description' => 'صرف نقدية للعميل - مرتجع رقم '.$returnRef,
                        'debit' => 0,
                        'credit' => $invoice->grand_total,
                        'is_debit' => false,
                    ]);

                    // حساب العميل (مدين) - موازنة القيد
                    JournalEntryDetail::create([
                        'journal_entry_id' => $treasuryJournalEntry->id,
                        'account_id' => $clientAccount->id,
                        'description' => 'تسوية حساب العميل - مرتجع رقم '.$returnRef,
                        'debit' => $invoice->grand_total,
                        'credit' => 0,
                        'is_debit' => true,
                    ]);


                    // ✅ تسجيل حركة صرف في الخزينة لتظهر في كشف حساب الخزينة
                    PaymentsProcess::create([
                        'invoice_id' => $invoice->id,
                        'branch_id' => $invoice->branch_id ?? current_branch_id(),
                        'amount' => $invoice->grand_total,
                        'payment_date' => now(),
                        'payment_method' => $request->payment_method ?? '1',
                        'reference_number' => $request->reference_number ?? $invoice->code,
                        'notes' => 'صرف مرتجع مبيعات - فاتورة رقم '.$invoice->code.' | العميل: '.$clientName.' | تم الصرف من الخزينة: '.$MainTreasury->name,
                        'type' => 'treasury_return',
                        'payment_status' => 1,
                        'created_by' => Auth::id(),
                    ]);

                    Log::info('تم تسجيل حركة صرف الخزينة للمرتجع (مدفوع كاملاً)', [
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->grand_total,
                        'treasury_id' => $MainTreasury->id,
                        'treasury_name' => $MainTreasury->name,
                        'treasury_balance' => $MainTreasury->balance,
                    ]);
                }
            }
            // إذا كانت الفاتورة مدفوعة جزئياً - إنشاء قيد عكسي للمبلغ المدفوع المسترجع
            elseif ($invoice_refrence && $shouldReturnAmount && $paidAmountFromOriginal > 0) {
                // تحديد الخزينة المستخدمة للإرجاع — قرارٌ واحد للفرعين
                $MainTreasury = $this->refundTreasuryAccount($request);

                $this->assertTreasuryCanCover($MainTreasury, (float) $paidAmountFromOriginal);

                if ($MainTreasury) {
                    // إنشاء قيد عكسي للمبلغ المدفوع
                    $paidReturnJournalEntry = JournalEntry::create([
                        'reference_number' => $invoice->code,
                        'date' => $documentDate,
                        'description' => 'إرجاع المبلغ المدفوع للعميل - مرتجع رقم '.$returnRef,
                        'status' => 1,
                        'currency' => $this->documentCurrency($invoice),
                        'client_id' => $invoice->client_id,
                        'invoice_id' => $invoice->id,
                        'reference_type' => ReferenceType::SALES_RETURN,
                        'linking_type' => LinkingType::SINGLE,
                        'created_by_employee' => Auth::id(),
                    ]);

                    // الخزينة (دائن) - صرف المبلغ المدفوع للعميل
                    JournalEntryDetail::create([
                        'journal_entry_id' => $paidReturnJournalEntry->id,
                        'account_id' => $MainTreasury->id,
                        'description' => 'صرف المبلغ المدفوع - مرتجع رقم '.$returnRef,
                        'debit' => 0,
                        'credit' => $paidAmountFromOriginal,
                        'is_debit' => false,
                    ]);

                    // حساب العميل (مدين) - تصفية المبلغ المدفوع
                    JournalEntryDetail::create([
                        'journal_entry_id' => $paidReturnJournalEntry->id,
                        'account_id' => $clientAccount->id,
                        'description' => 'استرجاع المبلغ المدفوع - مرتجع رقم '.$returnRef,
                        'debit' => $paidAmountFromOriginal,
                        'credit' => 0,
                        'is_debit' => true,
                    ]);


                    // ✅ تسجيل حركة صرف في الخزينة لتظهر في كشف حساب الخزينة
                    PaymentsProcess::create([
                        'invoice_id' => $invoice->id,
                        'branch_id' => $invoice->branch_id ?? current_branch_id(),
                        'amount' => $paidAmountFromOriginal,
                        'payment_date' => now(),
                        'payment_method' => $request->payment_method ?? 'cash',
                        'reference_number' => $request->reference_number ?? $invoice->code,
                        'notes' => 'صرف مرتجع مبيعات (دفع جزئي) - فاتورة رقم '.$invoice->code.' | العميل: '.$clientName.' | تم الصرف من الخزينة: '.$MainTreasury->name,
                        'type' => 'treasury_return',
                        'payment_status' => 1,
                        'created_by' => Auth::id(),
                    ]);

                    Log::info('تم إنشاء قيد عكسي للمبلغ المدفوع جزئياً', [
                        'invoice_id' => $invoice->id,
                        'original_invoice_id' => $invoice_refrence->id,
                        'paid_amount_returned' => $paidAmountFromOriginal,
                        'treasury_id' => $MainTreasury->id,
                    ]);
                }
            }
            // إذا كانت الفاتورة غير مدفوعة - لا يوجد مبلغ للإرجاع
            elseif ($invoice_refrence && $invoice_refrence->payment_status == 3) {
                Log::info('فاتورة غير مدفوعة - لا يوجد قيد إرجاع نقدي', [
                    'invoice_id' => $invoice->id,
                    'original_invoice_id' => $invoice_refrence->id,
                ]);
            }
            // إذا اختار المستخدم عدم إرجاع المبلغ
            elseif (! $shouldReturnAmount) {
                Log::info('تم اختيار عدم إرجاع المبلغ للعميل', [
                    'invoice_id' => $invoice->id,
                    'original_invoice_id' => $invoice_refrence->id,
                    'return_amount_option' => $request->return_amount_option,
                ]);
            }

            // ** إضافة ملاحظة تلقائية للعميل مع حساب العهدة التراكمية (خصم كمية المنتجات) **
            try {
                // حساب إجمالي كمية المنتجات في الفاتورة المرتجعة
                $totalQuantityInReturn = 0;
                if ($request->has('items') && is_array($request->items)) {
                    foreach ($request->items as $item) {
                        $totalQuantityInReturn += intval($item['quantity'] ?? 0);
                    }
                }

                // جلب آخر عهدة مسجلة للعميل
                $lastClientNote = ClientRelation::where('client_id', $invoice->client_id)
                    ->whereNotNull('deposit_count')
                    ->where('deposit_count', '>', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();

                // حساب العهدة الجديدة = آخر عهدة - كمية المنتجات المرتجعة
                $previousDeposit = $lastClientNote ? intval($lastClientNote->deposit_count) : 0;
                $newDeposit = max(0, $previousDeposit - $totalQuantityInReturn);

                // إنشاء الملاحظة التلقائية
                ClientRelation::create([
                    'client_id' => $invoice->client_id,
                    'employee_id' => auth()->id(),
                    'type' => 'invoice', // نستخدم invoice ليظهر في نفس السياق أو يمكن تغييرها
                    'invoice_id' => $invoice->id,
                    'description' => 'فاتورة مرتجعة رقم: '.$invoice->code.' - الكمية المرتجعة: '.$totalQuantityInReturn.' قطعة - قيمة المرتجع: '.number_format($invoice->grand_total, 2).' ر.س',
                    'deposit_count' => $newDeposit,
                    'date' => now()->toDateString(),
                    'time' => now()->toTimeString(),
                    'status' => 'completed',
                    'process' => 'مرتجع مبيعات',
                ]);

                Log::info('تم إنشاء ملاحظة تلقائية للفاتورة المرتجعة', [
                    'client_id' => $invoice->client_id,
                    'invoice_id' => $invoice->id,
                    'previous_deposit' => $previousDeposit,
                    'return_quantity' => $totalQuantityInReturn,
                    'new_deposit' => $newDeposit,
                ]);
            } catch (\Exception $noteException) {
                Log::warning('فشل في إنشاء ملاحظة الفاتورة المرتجعة: '.$noteException->getMessage());
            }

            // تعليم زيارة اليوم مكتملة — المرتجع دليلٌ على معاملة اليوم
            // كالفاتورة والسند والإشعار الدائن، ولم يكن يُعلِّم شيئاً قبل هذا
            if ($invoice->client_id) {
                EmployeeClientVisit::markCompletedFor(auth()->id(), (int) $invoice->client_id);
            }

            // === معالجة نقاط فاتورة المرتجع ===
            $pointsEmployeeId = $user->employee_id ?? null;
            $pointsClientId = $invoice->client_id ?? null;

            // لا يمكن إنشاء معاملة نقاط بدون معرف موظف أو معرف عميل
            if ($pointsEmployeeId || $pointsClientId) {
                try {
                    // خصم نقاط التسبب في المرتجع من الموظف الأصلي (سلبية)
                    // الموظف الذي أنشأ الفاتورة الأصلية هو المسؤول
                    $originalInvoice = Invoice::find($invoice->reference_number);
                    if ($originalInvoice && $originalInvoice->employee_id) {
                        app(PointsService::class)->processAction('return_caused', [
                            'employee_id' => $originalInvoice->employee_id,
                            'client_id' => $pointsClientId,
                            'amount' => $invoice->grand_total,
                            'branch_id' => $invoice->branch_id ?? null,
                            'reference_type' => Invoice::class,
                            'reference_id' => $invoice->id,
                            'description' => 'تسبب في مرتجع فاتورة رقم '.$invoice_code,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PointsService: فشل معالجة نقاط فاتورة المرتجع', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            // السجلّات الثلاثة معاً — نقطة تسجيل واحدة.
            //
            // كان المرتجع يُحفَظ ولا يُكتب له سطر في أيّ سجلّ، فلا يظهر في
            // شاشتَي «سجلّ النشاط» و«سجلّ الأحداث» ولا يُعرف من أرجع ولا متى
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $invoice,
                'إنشاء فاتورة مرتجعة رقم '.$invoice->code
                    .' — عن الفاتورة: '.($invoice_orginal->code ?? '—')
                    .' — العميل: '.($invoice->client->trade_name ?? '—')
                    .' — الإجمالي: '.CurrencySettings::format($invoice->grand_total),
            );

            // المرتجع إشعارٌ دائن عند الهيئة — انظر التعليق نفسه في
            // `InvoicesController::persistInvoice()`
            $zatcaNotice = \App\Services\Zatca\ZatcaInvoiceService::noticeFor($invoice);

            $response = redirect()->route('ReturnIInvoices.show', $invoice->id)->with('success', 'تم إرجاع الفاتورة بنجاح.');

            return $zatcaNotice
                ? $response->with('warning', __('zatca.invoice.saved_not_sent', ['reason' => $zatcaNotice]))
                : $response;
        } catch (\RuntimeException $e) {
            /* قاعدةُ عملٍ لا عطل: تُقال كما هي بلا «حدث خطأ» ولا تُكتب في
               سجلّ الأخطاء — رصيدٌ لا يكفي ليس خللاً في النظام. */
            DB::rollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            DB::rollback();

            // سجلّ الأخطاء وسجلّ الأحداث معاً — كان `Log::error` وحده،
            // فلا يظهر الخطأ في شاشة «سجلّ الأخطاء» التي بُنيت له
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء إرجاع الفاتورة: '.$e->getMessage());
        }
        // edit
    }

    public function edit($id)
    {
        return redirect()
            ->back()
            ->with('error', 'لا يمكنك تعديل الفاتورة رقم '.$id.'. طبقا لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقا لمتطلبات الفاتورة الإلكترونية، ولكن يمكن إصدار فاتورة مرتجعة أو إشعار دائن لإلغائها أو تعديلها.');
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy($id)
    {
        return redirect()->route('ReturnIInvoices.index')->with('error', 'لا يمكنك حذف الفاتورة. طبقا لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقا لمتطلبات الفاتورة الإلكترونية، ولكن يمكن إصدار فاتورة مرتجعة أو إشعار دائن لإلغائها أو تعديلها.');
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(Request $request, $id)
    {
        return redirect()->route('ReturnIInvoices.index')->with('error', 'لا يمكنك تعديل الفاتورة. طبقا لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقا لمتطلبات الفاتورة الإلكترونية، ولكن يمكن إصدار فاتورة مرتجعة أو إشعار دائن لإلغائها أو تعديلها.');
    }

    /**
     * عرض فاتورة مرتجعة.
     *
     * كانت الشاشة تُبنى على `$return_invoice` وحدها بلا بنودٍ ولا قيدٍ
     * ولا قوالب، فلا تعرض إلّا إطار طباعةٍ وسجلّ نشاط. صارت تُمرَّر لها
     * ما تعرضه شاشة الفاتورة: البنود بمنتجاتها، والفاتورة الأصلية،
     * والقيد المحاسبي، وقوالب الطباعة المصمَّمة في «قوالب المبيعات».
     */
    public function show($id)
    {
        $user = auth()->user();

        $return_invoice = Invoice::with([
            'client',
            'employee',
            'createdByUser',
            'journalEntry',
            'referenceInvoice',
            'items.product',
        ])->where('type', 'returned')->find($id);

        // كان `Invoice::find($id)` بلا قيد نوع: معرّفُ فاتورة بيعٍ عاديّة
        // يفتح شاشة المرتجع عليها. ومعرّفٌ لا وجود له يمرّ إلى القالب
        // `null` فينكسر عند أوّل `->client`
        if (! $return_invoice) {
            abort(404);
        }

        // ✅ صلاحية العرض محروسة أصلاً في المُنشئ (permission:sales.returns.view)؛
        // hasRolePermission('data_scope_all') بدلاً من دورٍ ثابت
        /* والحارسُ على نطاق الجدول نفسِه: مرتجعُ فريقي أراه في القائمة،
           فلا يصحّ أن يردّني عن فتحه. */
        if ($return_invoice) {
            $this->authorizeSalesDocumentScope(
                $return_invoice->created_by,
                $return_invoice->employee_id,
                $return_invoice->branch_id
            );
        }

        $clients = Client::all();
        $employees = Employee::all();
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        // كان يُقرأ بنوعٍ لا يُكتَب («return») بينما `OperationLog::record()`
        // يكتب دائماً بـ`self::LOG_MODULE` («SalesReturns» — انظر الإنشاء
        // والطباعة والإرسال أدناه)، فيخرج الخطّ الزمني فارغاً لكل مرتجع.
        // والقيمة القديمة تبقى في `whereIn` فلا تختفي سجلّاتٌ كُتبت بها فعلاً
        $logs = ModelsLog::whereIn('type', [self::LOG_MODULE, 'return'])
            ->where('type_id', $id)
            ->whereHas('sales')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('sales::retend_invoice.show', [
            'clients' => $clients,
            'TaxsInvoice' => $TaxsInvoice,
            'logs' => $logs,
            'id' => $id,
            'employees' => $employees,
            'account_setting' => $account_setting,
            'return_invoice' => $return_invoice,

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «قوالب المبيعات» — قوالب الطباعة المصمَّمة لنوع «مرتجع»
            'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
            'defaultTemplate' => $this->defaultSalesTemplate(self::DOCUMENT_TYPE),
        ]);
    }

    /**
     * طباعة المرتجع — بالقالب المصمَّم في «قوالب المبيعات».
     *
     * `$template` معرّفُ قالبٍ من الإعدادات فيُصيَّر، أو لا شيء فيُعرَض
     * القالب المدمج كما كان — فمنشأةٌ لم تصمّم قالباً بعد تطبع مرتجعها.
     */
    public function print($id, $template = null)
    {
        // ✅ صلاحية الطباعة محروسة أصلاً في المُنشئ (permission:sales.returns.print)

        if (! $this->wantsBuiltinPrint($template) && ($html = $this->renderSalesTemplate(self::DOCUMENT_TYPE, $id, $template))) {
            OperationLog::record('printed', self::LOG_MODULE, (int) $id, 'طباعة فاتورة مرتجعة رقم '.$id);

            return response(DocumentPrintPage::wrap($html));
        }

        $return_invoice = Invoice::find($id);

        // إذا كان المستخدم يملك صلاحية عرض فواتيره فقط، تحقق من أنه صاحب الفاتورة
        // if ($jobRole->sales_view_own_return_invoices == 1 && $jobRole->sales_view_all_return_invoices != 1) {
        //     if ($return_invoice->created_by != $user->id) {
        //         abort(403, 'ليس لديك صلاحية لطباعة هذه الفاتورة');
        //     }
        // }

        $clients = Client::all();
        $employees = Employee::all();
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // $invoice_number = $this->generateInvoiceNumber();
        return view('sales::retend_invoice.pdf', compact('clients', 'id', 'TaxsInvoice', 'employees', 'account_setting', 'return_invoice'));
    }

    /**
     * إرسال المرتجع إلى بريد العميل.
     *
     * ── ما كان ──
     *
     *   • الرابط يُبنى بـ`route('return.print', …)` — اسمُ مسارٍ لا وجود
     *     له، فيرمي `RouteNotFoundException`: خطأ ٥٠٠ في كل ضغطة.
     *   • ثمّ حين صُحّح الاسم، بقي المُرسَل **رابطاً** ونصَّ رسالةٍ مكتوباً
     *     في المتحكّم حرفياً — لا يصل إليه صاحبُ المنشأة من أي شاشة، ولا
     *     يحمل المستندَ نفسَه.
     *   • والزرُّ في الشاشة لم يكن يصل إلى هنا أصلاً: اسمُه «إرسال
     *     بالبريد» وتحته `https://wa.me/…`.
     *
     * ── وما صار ──
     *
     * `SalesDocumentMailer`: المستندُ بقالبه المصمَّم مرفقاً PDF، ونصُّ
     * الرسالة من «قوالب البريد»، والمستلِمُ يقبل التعديل في النافذة.
     */
    public function sendReturnInvoiceEmail(Request $request, $id)
    {
        $invoice = Invoice::with('client')->where('type', 'returned')->findOrFail($id);

        $client = $invoice->client;

        $response = $this->mailSalesDocument($request, 'sales_return', $invoice->id, [
            'number' => (string) ($invoice->code ?: $invoice->id),
            'client' => $client?->display_name ?? $client?->trade_name ?? '',
            'email' => $client->email ?? null,
            'client_id' => $invoice->client_id,
            'total' => $invoice->grand_total,
            'date' => $invoice->invoice_date ?? $invoice->created_at,
            'link' => route('ReturnIInvoices.print', $invoice->id),
        ], $this->builtInReturnHtml($invoice));

        if ($this->documentMailSucceeded()) {
            OperationLog::record('emailed', self::LOG_MODULE, (int) $invoice->id, 'إرسال فاتورة مرتجعة رقم '.$invoice->id.' بالبريد');
        }

        return $response;
    }

    /**
     * قالبُ الوحدة المدمج نصّاً — احتياطُ المرفق حين لا قالبَ مصمَّماً للنوع.
     *
     * `null` عند أي تعثّر: رسالةٌ بلا مرفق خيرٌ من رسالةٍ لا تخرج.
     */
    protected function builtInReturnHtml(Invoice $return_invoice): ?string
    {
        try {
            return view('sales::retend_invoice.pdf', [
                'id' => $return_invoice->id,
                'return_invoice' => $return_invoice,
                'clients' => Client::all(),
                'employees' => Employee::all(),
                'TaxsInvoice' => TaxInvoice::where('invoice_id', $return_invoice->id)->get(),
                'account_setting' => AccountSetting::where('user_id', auth()->id())->first(),
            ])->render();
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'email_builtin_html', (int) $return_invoice->id);

            return null;
        }
    }
}
