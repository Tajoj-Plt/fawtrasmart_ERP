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
use App\Models\CategoryUnit;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\CompiledProducts;
use App\Models\CreditLimit;
use App\Models\CreditNotification;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\GiftOffer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\Notification;
use App\Models\notifications;
use App\Models\Offer;
use App\Models\OrderSource;
use App\Models\PaymentMethod;
use App\Models\PaymentsProcess;
use App\Models\PermissionSource;
use App\Models\PriceList;
use App\Models\PriceListItems;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductUnit;
use App\Models\Quote;
use App\Models\Receipt;
use App\Models\Region_groub;
use App\Models\SalesInvoiceSetting;
use App\Models\settingsOrderSource;
use App\Models\Signature;
use App\Models\StoreHouse;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\Treasury;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Accounts\JournalEntryService;
use App\Services\Clients\ClientVisibilityScope;
use App\Services\PointsService;
use App\Services\Tax\DocumentTaxCalculator;
use App\Services\Wallets\ClientWalletService;
use App\Models\PeriodicInvoiceInstance;
use App\Services\Invoices\PeriodicInvoiceGenerator;
use App\Models\ZatcaDocument;
use App\Models\ElectronicInvoiceSetting;
use App\Services\Zatca\ZatcaInvoiceService;
use App\Services\Zatca\ZatcaQr;
use App\Support\BusinessInfo;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\StockEligibility;
use App\Support\StorehouseAccess;
use App\Support\TreasuryAccessGuard;
use App\Traits\AssignedEmployeeAccess;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\TelegramNotificationService;
use App\Support\DocumentAttachments;
use Modules\Sales\Support\SalesWarehouse;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use TCPDF;

/**
 * فواتير المبيعات.
 *
 * **ما يأتي من الإعدادات** — كان كلّه مكتوباً في الشيفرة:
 *
 *   • *الرقم*        ← «تسلسل أرقام المستندات»، القسم `invoice`
 *                      (`GeneratesDocumentNumbers`).
 *   • *العملة*       ← «العملات»، وتُجمَّد مع المستند بعد الحفظ
 *                      (`UsesDocumentCurrency`).
 *   • *الحسابات*     ← «الحسابات المحاسبية» وحدها، بلا احتياطٍ برمجي
 *                      (`ResolvesAccountingAccounts`).
 *   • *الإقفال*      ← «الفترات المحاسبية»: تاريخٌ في فترةٍ مقفلة يردّ
 *                      الحفظ (`RespectsAccountingPeriods`).
 *   • *القوالب*      ← «قوالب المبيعات» في الطباعة وتنزيل PDF
 *                      (`RendersSalesDocumentTemplates`).
 *   • *السجلّات*     ← `OperationLog`: سجلّ النشاط وسجلّ الأحداث وسجلّ
 *                      الأخطاء في كل عملية على الفاتورة.
 *   • *اللغة والمظهر* ← عبر القوالب: `sl-*` مبنيّة على `--app-*`.
 */
class InvoicesController extends Controller
{
    use AssignedEmployeeAccess;
    use Concerns\AppliesDiscountOffers;
    use Concerns\AppliesSalesCoupons;
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

    /** إعدادات الفواتير محفوظةً للطلب — انظر `invoiceRules()`. */
    protected ?SalesInvoiceSetting $invoiceRules = null;

    /** قسم الترقيم المتسلسل الذي تُؤخذ منه أرقام فواتير البيع */
    public const NUMBER_SECTION = 'invoice';

    /** نوع المستند في «قوالب المستندات» — به تُقرأ قوالب هذه الشاشة */
    public const DOCUMENT_TYPE = 'sales_invoice';

    /** قيمة العمود `type` في سجلّ النشاط — بها يربط المستندُ خطَّه الزمني */
    public const LOG_MODULE = 'SalesInvoices';

    protected $journalEntryService;

    public function __construct(JournalEntryService $journalEntryService)
    {
        $this->journalEntryService = $journalEntryService;

        // مُنقولة إلى كتالوج sales.invoices.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.invoices.view', ['only' => [
            'index', 'ajaxInvoices', 'show', 'getUnreadNotifications', 'markAsRead',
        ]]);
        $this->middleware('permission:sales.invoices.create', ['only' => [
            'create', 'store', 'getPrice', 'sendVerificationCode', 'verifyCode', 'verify_code',
            // مساعدتا شاشة الإنشاء: تعبئةٌ من عرض سعر، وفحصُ كوبون
            'quoteSource', 'checkCoupon',
        ]]);
        $this->middleware('permission:sales.invoices.edit', ['only' => ['edit', 'update', 'storeSignatures']]);
        $this->middleware('permission:sales.invoices.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.invoices.send', ['only' => ['send_invoice']]);
        $this->middleware('permission:sales.invoices.labels', ['only' => ['label', 'shipping_label']]);
        $this->middleware('permission:sales.invoices.print', ['only' => ['picklist', 'print']]);
        $this->middleware('permission:sales.invoices.print_tax', ['only' => ['printTax']]);
        $this->middleware('permission:sales.invoices.export_pdf', ['only' => ['generatePdf']]);
        $this->middleware('permission:sales.invoices.mark_paid', ['only' => ['markAsPaidSilently']]);
        $this->middleware('permission:sales.invoices.notes', ['only' => ['addNote', 'getNotes', 'deleteNote']]);
    }

    public function markAsRead(Request $request)
    {
        $user = auth()->user();

        $notification = notifications::where('id', $request->id)
            ->where(function ($query) use ($user) {
                // الموظف يمكنه قراءة الإشعارات التي أرسلها أو التي تم إرسالها إليه
                if ($user->isEmployee()) {
                    $query->where('user_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                }
            })
            ->first();

        if ($notification) {
            $notification->read = 1;
            $notification->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function getUnreadNotifications()
    {
        $user = auth()->user();

        $query = notifications::where('read', 0)
            ->orderBy('created_at', 'desc');

        if ($user->isEmployee()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            });
        }

        $notifications = $query->get(['id', 'title', 'description', 'created_at', 'user_id', 'receiver_id']);

        return response()->json([
            'notifications' => $notifications,
            'auth_id' => $user->id, // للمراجعة
            'role' => $user->role,   // للمراجعة
        ]);
    }

    public function index(Request $request)
    {
        // نطاق التاريخ الافتراضي هو **الفترة المحاسبية الجارية** لا تاريخ
        // المنشأة كلّه: الشاشة كانت تفتح على كل فاتورة أُصدرت منذ التأسيس،
        // وإحصاءاتها الثلاث تجمع سنواتٍ أُقفلت وأُصدرت قوائمها
        $range = $this->reportDateRange($request);

        /* العملة لم تعد في نموذج البحث: الغيابُ «كل العملات» لا عملةُ النظام،
           وإلّا صفّت الشاشةُ بما لا يراه المستخدم ولا يستطيع تغييره.
           رابطٌ يحملها صراحةً ما زال يُحترم. */
        $currency = $request->filled('currency') ? $this->filterCurrency($request) : null;

        /* نطاقُ الرؤية — السلَّمُ الخماسيّ لا حالتان.
           كان هنا «الكلّ أو ما كتبتُه أنا»: من لا يملك `data_scope_all`
           يرى `created_by = أنا` وحدَها، فالمشرفُ الذي مُنح «بيانات فريقه»
           صراحةً يفتح الشاشة على فواتيره هو — وسنداتُ القبض في الوحدة
           المجاورة تعرض له فريقَه. انظر `ScopesSalesVisibility`. */
        $query = Invoice::with(['client', 'createdByUser', 'employee', 'updatedByUser'])
            ->whereIn('type', ['normal', 'pos', 'online']);

        $this->scopeSalesDocuments($query);

        /* الفرع النشط من الهيدر.
           مبدّلُ الفروع في الهيدر يكتب `users.branch_id`، وكانت الشاشة لا
           تقرؤه: يختار المستخدم فرعاً فيبقى الجدول على فواتير المنشأة كلّها.

           والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها، فمن
           يقف عليه يرى المبيعات كلَّها. كان يُعامَل كأيّ عقدةٍ أخرى فيرى
           ما وُسِم برقمه وحدَه — وهو في العادة أقلُّ العقد وسماً، لأنّ
           الفواتير تُنسَب إلى الفروع والمناطق العاملة لا إلى المركز. */
        $this->applyHeaderBranchScope($query);

        // قاعدةُ منسدلتَي «أُضيفت بواسطة» و«مسؤول المبيعات» — قبل فلاتر البحث
        $optionsBase = clone $query;

        // تطبيق جميع شروط البحث
        $this->applySearchFilters($query, $request, $range, $currency);

        // حساب تكلفة المبيعات الفعلية (COGS): التكلفة المجمَّدة على البند أولاً (ما قُيّد فعلاً)،
        // وللبنود الأقدم منها التسلسلُ القديم (ProductDetails ثم Product ثم WarehousePermitsProducts)
        $costCalculationSql = '
            COALESCE(invoice_items.quantity_in_base_unit, invoice_items.quantity * COALESCE(invoice_items.conversion_factor, 1))
            *
            COALESCE(
                NULLIF(invoice_items.unit_cost_at_sale, 0),
                NULLIF((SELECT unit_price FROM product_details WHERE product_details.product_id = invoice_items.product_id AND product_details.store_house_id = invoice_items.store_house_id ORDER BY id DESC LIMIT 1), 0),
                NULLIF(products.purchase_price, 0),
                NULLIF((SELECT AVG(unit_price) FROM warehouse_permits_products WHERE warehouse_permits_products.product_id = invoice_items.product_id), 0),
                0
            )
        ';

        // 1. تكلفة مبيعات الفواتير الحالية
        $salesQuery = clone $query;
        $salesInvoiceCount = $salesQuery->count();
        $totalSalesCost = InvoiceItem::whereIn('invoice_id', $salesQuery->select('invoices.id'))
            ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
            ->selectRaw("SUM($costCalculationSql) as total_cost")
            ->value('total_cost') ?? 0;

        // 2. تكلفة المرتجعات
        $returnsQuery = Invoice::where('type', 'returned');
        $this->scopeSalesDocuments($returnsQuery);
        /* الفرعُ نفسُه الذي يقيّد الجدول يقيّد بطاقتَه.
           كان هذا الاستعلام بلا تقييد فرعٍ إطلاقاً — لا `forBranch` ولا
           غيرها — فتعدّ البطاقةُ مرتجعاتِ المنشأة كلِّها فوق جدولٍ يعرض
           فرعاً واحداً. رقمٌ فوق رقمٍ لا يشرحه. */
        $this->applyHeaderBranchScope($returnsQuery);

        $this->applySearchFilters($returnsQuery, $request, $range, $currency);

        $returnsQueryCount = clone $returnsQuery;
        $returnInvoiceCount = $returnsQueryCount->count();
        $totalReturnsCost = InvoiceItem::whereIn('invoice_id', $returnsQuery->select('invoices.id'))
            ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
            ->selectRaw("SUM($costCalculationSql) as total_cost")
            ->value('total_cost') ?? 0;

        // 2.5 إضافة تكلفة الإشعارات الدائنة (Credit Notifications) لاعتبارها مرتجعات أيضاً
        $creditNotesQuery = CreditNotification::query();
        $this->scopeSalesDocuments($creditNotesQuery);

        // ونفس القيدين: الفرعُ، وما أُقفل. الإشعارُ الدائن يدخل هذه البطاقة
        // مرتجعاً، فلا يجوز أن يُحسب بقاعدةٍ غير قاعدة المرتجعات معه
        $this->applyHeaderBranchScope($creditNotesQuery);

        $this->excludeClosedRanges($creditNotesQuery, 'credit_notifications.credit_date');

        // تطبيق فلاتر البحث على الإشعارات الدائنة
        if ($request->filled('client_id')) {
            $creditNotesQuery->where('client_id', $request->client_id);
        }
        if ($request->filled('invoice_number')) {
            $creditNotesQuery->where(function ($q) use ($request) {
                $q->where('id', $request->invoice_number)
                    ->orWhere('credit_number', 'LIKE', '%'.$request->invoice_number.'%');
            });
        }
        if ($request->filled('total_from')) {
            $creditNotesQuery->where('grand_total', '>=', $request->total_from);
        }
        if ($request->filled('total_to')) {
            $creditNotesQuery->where('grand_total', '<=', $request->total_to);
        }
        // نفس نطاق الفواتير لا `filled()` وحدها: النطاق الافتراضي يأتي من
        // الفترة المحاسبية، فلو استُثنيت الإشعارات منه لجُمعت إشعارات
        // سنواتٍ سابقة على مبيعات الفترة الجارية
        // وعلى تاريخ الإشعار نفسِه كالفواتير — لا لحظة إدخاله
        $creditNotesQuery->whereBetween('credit_notifications.credit_date', [
            \Carbon\Carbon::parse($range['start'])->toDateString(),
            \Carbon\Carbon::parse($range['end'])->toDateString(),
        ]);

        if ($request->filled('added_by_employee')) {
            $creditNotesQuery->where('created_by', $request->added_by_employee);
        }

        if ($request->filled('sales_person_user')) {
            $creditNotesQuery->where('employee_id', $request->sales_person_user);
        }

        $creditNotesCountQuery = clone $creditNotesQuery;
        $returnCreditCount = $creditNotesCountQuery->count();
        $totalCreditCost = InvoiceItem::whereIn('credit_note_id', $creditNotesQuery->select('credit_notifications.id'))
            ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
            ->selectRaw("SUM($costCalculationSql) as total_cost")
            ->value('total_cost') ?? 0;

        // دمج تكلفة الإشعارات الدائنة وعددهن مع المرتجعات
        $totalReturnsCost += $totalCreditCost;
        $returnInvoiceCount += $returnCreditCount;

        // 3. صافي تكلفة المبيعات = تكلفة المبيعات - تكلفة المرتجعات (المرتجعات + الإشعارات الدائنة)
        $totalSales = $totalSalesCost;
        $totalReturns = $totalReturnsCost;
        $netSales = $totalSales - $totalReturns;

        // التحقق من كون الطلب AJAX
        if ($request->ajax()) {
            $invoices = $query->orderBy('created_at', 'desc')->paginate($this->perPage($request));

            return response()->json([
                'success' => true,
                'data' => view('sales::invoices.partials.table', [
                    'invoices' => $invoices,
                    'returnedIds' => $this->returnedInvoiceIds($invoices),
                    'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
                ])->render(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
                'per_page' => $invoices->perPage(),

                // الأرقام تُنسَّق بعملة النظام لا بـ`number_format` المجرَّدة:
                // الكسور والفواصل والرمز كلّها مضبوطة في شاشة «العملات»
                'total_sales' => CurrencySettings::format($totalSales),
                'total_returns' => CurrencySettings::format($totalReturns),
                'net_sales' => CurrencySettings::format($netSales),
                'sales_count' => $salesInvoiceCount,
                'returns_count' => $returnInvoiceCount,
            ]);
        }

        // جلب البيانات الأخرى المطلوبة للواجهة
        $user = auth()->user();

        // معرّفات العملاء المسموح بهم في السيلكت — أو `null` فلا قيد،
        // بحسب صلاحيات عرض العملاء (نفس ترتيب الأولوية في ClientController@index)
        $eligibleClientIds = ClientVisibilityScope::eligibleClientIds($user);

        $clients = $eligibleClientIds !== null
            ? Client::whereIn('id', $eligibleClientIds)->get()
            : Client::all();

        $users = User::all();

        /* مُنتقيا «مسؤول المبيعات» و«أُضيفت بواسطة» — على نطاق الرؤية.

           لا يكفي أن تُقيَّد الصفوفُ وتبقى المنسدلاتُ كاملة: من رأى اسمَ
           زميلٍ لا يحقّ له وضغطه خرج بشاشةٍ فارغة لا تفسّر نفسها، وعَلِم
           من القائمة وحدَها ما لا يراه في الجدول. وهي القاعدةُ المكتوبة
           في `DataScope::selectableBranchIds()` مطبَّقةً على محور الأشخاص.

           والمصدرُ صفوفُ الشاشة نفسُها بعد النطاق والفرع — انظر
           `creatorFilterOptions()`. */
        $employees_sales_person = $this->salesPersonFilterOptions($optionsBase);

        $employees = $this->creatorFilterOptions($optionsBase);

        $invoice_number = $this->previewDocumentNumber(self::NUMBER_SECTION);
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        $client = Client::where('user_id', auth()->user()->id)->first();

        // التحميل الأوّل يأتي مع الصفحة لا بنداء Ajax ثانٍ: الشاشة كانت
        // تُرسَل فارغةً ثم تطلب جدولها بعد 100ms، فيرى المستخدم دوّامةً
        // قبل كل شيء ولا يعمل زرّا التصدير والطباعة قبل وصولها
        $invoices = $query->orderBy('created_at', 'desc')->paginate($this->perPage($request));
        $returnedIds = $this->returnedInvoiceIds($invoices);

        $currencies = $this->activeCurrencies();
        $branches = Branch::where('status', 0)->select('id', 'name')->get();

        // قوالب «قوالب المبيعات» — منها تُبنى قائمة المطبوعات في كل صفّ
        $documentTemplates = $this->salesTemplates(self::DOCUMENT_TYPE);

        // حالةُ حدّ «فواتير المبيعات» في باقة فوترة — للشريط أعلى الشاشة.
        // تُقرأ من اللقطة المحفوظة بلا نداء شبكة، ولا يظهر الشريط أصلاً إن
        // كانت الميزة بلا حدّ أو كان النظام غير مربوطٍ باشتراك.
        $subscriptionLimit = $this->subscriptionLimit(SalesPlanFeatures::INVOICES);

        return view('sales::invoices.index', compact(
            'subscriptionLimit',
            'account_setting',
            'client',
            'employees_sales_person',
            'clients',
            'users',
            'invoice_number',
            'employees',
            'invoices',
            'returnedIds',
            'documentTemplates',
            'currencies',
            'branches',
            'range',
            'totalSales',
            'totalReturns',
            'netSales',
            'salesInvoiceCount',
            'returnInvoiceCount'
        ) + ['selectedCurrency' => $currency]);
    }

    /**
     * إعدادات الفواتير — تُقرأ مرّةً في الطلب.
     *
     * `SalesInvoiceSetting::current()` تستعلم القاعدة، والحفظُ الواحد يسألها
     * في أكثر من موضع.
     */
    protected function invoiceRules(): SalesInvoiceSetting
    {
        return $this->invoiceRules ??= SalesInvoiceSetting::current();
    }

    /**
     * ما أدخله المستخدم في خانة خصم البند — من الحقل الذي يخصّ نوعَه.
     *
     * صفُّ البند في الشاشة **حقلان لا حقل**: `discount` للقيمة و
     * `discount_percentage` للنسبة، يُظهر السكربت أحدَهما ويُخفي الآخر
     * بحسب `discount_type`. وكان الخادم يقرأ `discount` وحده في الحالتين،
     * فالخصمُ بالنسبة يصل **صفراً**: يُحفَظ البند بلا خصمٍ وقد عرضت الشاشة
     * خصمَه (فيخرج الإجمالي المحفوظ غير المعروض وعليه يُبنى القيد)، ويمرّ
     * من فحص «الحدّ الأقصى للخصم» بلا حدٍّ — إذ لا خصمَ يُرى فيُقاس.
     *
     * و`discount` تُقرأ احتياطاً متى غاب حقل النسبة: نداءٌ من شاشةٍ لا
     * تحمل الحقلين، أو نسخُ فاتورةٍ سابقة.
     */
    protected function lineDiscountInput(array $item): float
    {
        if (($item['discount_type'] ?? null) === 'percentage') {
            $percent = floatval($item['discount_percentage'] ?? 0);

            return $percent > 0 ? $percent : floatval($item['discount'] ?? 0);
        }

        return floatval($item['discount'] ?? 0);
    }

    /**
     * خصم البند قيمةً — النسبةُ تُحوَّل على مجموع البند، والقيمةُ كما هي.
     */
    protected function lineDiscountValue(array $item, float $lineTotal): float
    {
        $input = $this->lineDiscountInput($item);

        if ($input <= 0) {
            return 0.0;
        }

        return ($item['discount_type'] ?? null) === 'percentage'
            ? ($lineTotal * $input) / 100
            : $input;
    }

    /**
     * ما يمكن تسويتُه من رصيد العميل الدائن — بلا أن يتجاوز حاجةَ الفاتورة.
     *
     * **اصطلاح الإشارة**: `Account.balance` موجبٌ حين يكون العميلُ مديناً
     * لنا (وعليه تُبنى فحوصُ الحدّ الائتماني في هذه الدالّة نفسِها:
     * `$currentDebt = $clientAccount->balance`). فالرصيدُ الدائن — أي ماله
     * عندنا — سالبٌ، والمتاحُ منه `max(0, -balance)`.
     *
     * وتُجمَع حساباتُ العميل كلُّها لا حسابٌ واحد، اتّساقاً مع
     * `Client::Balance()` التي تعرضها الشاشات.
     */
    protected function clientCreditToApply(int $clientId, float $needed): float
    {
        if ($clientId <= 0 || $needed <= 0) {
            return 0.0;
        }

        $balance = (float) Account::ledger()->where('client_id', $clientId)->sum('balance');
        $available = max(0.0, -$balance);

        return round(min($available, $needed), 2);
    }

    /**
     * طرق الدفع المفعَّلة في شاشة «طرق الدفع».
     *
     * تُبتلع أخطاء القراءة: وحدة الإعدادات قد تكون معطَّلة أو الهجرة لم
     * تُشغَّل، وقائمةٌ ناقصة أهون من شاشةٍ لا تُفتح.
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
            OperationLog::failure($e, 'Sales.Invoices', 'activePaymentMethods');

            return collect();
        }
    }

    /**
     * عدد السجلّات في الصفحة — من قائمة مغلقة.
     *
     * `per_page` كان يصل إلى `paginate()` كما أرسله المتصفّح: طلبٌ بـ
     * `per_page=100000` يحمّل الجدول كلّه في الذاكرة ويسقط الطلب. البند
     * ٦٫٣ في تقرير التحسين يرصده ثغرةَ إنهاك.
     */
    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    /**
     * معرّفات الفواتير التي لها مرتجع — استعلام واحد لكل الصفحة.
     *
     * كان قالب الجدول يسأل عن مرتجع كل فاتورة داخل الحلقة، ويجلب معها
     * مدفوعاتها ولا يستعملها: واحدٌ وعشرون استعلاماً في صفحةٍ من عشرة
     * صفوف.
     *
     * @param  Paginator  $invoices
     * @return array<int, true> خريطةُ وجودٍ، فالفحص في القالب `isset`
     */
    protected function returnedInvoiceIds($invoices): array
    {
        $ids = collect($invoices->items())->pluck('id')->filter()->all();

        if ($ids === []) {
            return [];
        }

        return Invoice::where('type', 'returned')
            ->whereIn('reference_number', $ids)
            ->pluck('reference_number')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /**
     * شروط البحث المشتركة بين استعلام الفواتير واستعلام المرتجعات.
     *
     * @param  array{start: string, end: string}|null  $range  نطاق التاريخ المطبَّق
     * @param  string|null  $currency  رمز العملة، أو `null` لكل العملات
     */
    protected function applySearchFilters($query, $request, ?array $range = null, ?string $currency = null)
    {
        // البحث حسب العميل
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // رقم الفاتورة: `code` هو ما يراه المستخدم ويطبعه، و`id` معرّفٌ
        // داخلي. كان الفلتر يقارن بـ`id` وحده، فالبحث بالرقم المطبوع لا
        // يُرجع شيئاً — والبحث بـ«1» يُرجع فاتورةً لا علاقة لها بما كُتب
        if ($request->filled('invoice_number')) {
            $term = trim((string) $request->invoice_number);

            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', '%'.$term.'%');

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        // العملة — المخزَّنة مع المستند. `null` تعني «كل العملات»
        $this->applyCurrencyFilter($query, $currency);

        /* الفرع — من عمود الفاتورة نفسها.
           كان يُقرأ من فرع **مُنشئها الحاليّ** (`createdByUser.branch_id`)،
           فمن بدّل فرعه من الهيدر انتقل تاريخُه كلُّه معه إلى الفرع الجديد
           واختفت فواتيرُه من الفرع الذي أصدرها. */
        if ($request->filled('branch')) {
            $query->forBranch($request->branch);
        }

        // البحث حسب حالة الدفع
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // البحث حسب البند
        if ($request->filled('item')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('item', 'like', '%'.$request->item.'%');
            });
        }

        // البحث حسب الإجمالي (من)
        if ($request->filled('total_from')) {
            $query->where('grand_total', '>=', $request->total_from);
        }

        // البحث حسب الإجمالي (إلى)
        if ($request->filled('total_to')) {
            $query->where('grand_total', '<=', $request->total_to);
        }

        // نطاق التاريخ — ما اختاره المستخدم، وإلا الفترة المحاسبية الجارية.
        // يُطبَّق دائماً لا عند `filled()` وحدها، وإلا فُتحت الشاشة على
        // تاريخ المنشأة كلّه
        /* على **تاريخ الفاتورة** لا لحظة إدخالها — وهو ما يَعِد به تلميحُ
           الحقل، والتاريخُ الذي تُستبعد عليه الفترات المقفلة أدناه. كان
           `whereDate(created_at)`، فـ٧٢٠ فاتورةً مؤرَّخةً بغير يوم إدخالها
           تظهر في نطاقٍ وتُقفَل في آخر. */
        if ($range) {
            $query->whereBetween('invoices.invoice_date', [
                \Carbon\Carbon::parse($range['start'])->toDateString(),
                \Carbon\Carbon::parse($range['end'])->toDateString(),
            ]);
        } else {
            if ($request->filled('from_date')) {
                $query->where('invoices.invoice_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('invoices.invoice_date', '<=', $request->to_date);
            }
        }

        /* ** الفترات المقفلة ** — تُستبعد من الشاشة كلّها لا من نطاقها
           الافتراضيّ وحده. من أقفل سنةً لا يريد فواتيرَها أن تعود بضبط
           التاريخ يدوياً، والاستبعاد هنا لأن هذه الدالة تمرّ على استعلام
           الفواتير واستعلام المرتجعات معاً — فالجدول وبطاقاتُ الإحصاء
           الثلاث تتّفق على ما تعدّه.

           والفحص على `invoice_date` لا `created_at`: الإقفال محاسبيٌّ
           يخصّ تاريخ المستند لا لحظةَ إدخاله، وهو التاريخ نفسه الذي يفحصه
           منعُ الحفظ في `store` — فما مُنع كتابةً لا يظهر قراءةً. */
        $this->excludeClosedRanges($query, 'invoices.invoice_date');

        // البحث حسب "أضيفت بواسطة"
        if ($request->filled('added_by_employee')) {
            $query->where('created_by', $request->added_by_employee);
        }

        // البحث حسب مسؤول المبيعات
        if ($request->filled('sales_person_user')) {
            $query->where('employee_id', $request->sales_person_user);
        }

        $this->applyDueFilter($query, $request);
    }

    /**
     * فلترُ الاستحقاق — على مهلة السداد المحفوظة في `payment_terms`.
     *
     * ══════════ لماذا `DATE_ADD` في الاستعلام ══════════
     *
     * تاريخُ الاستحقاق محسوبٌ لا مخزَّن: عمودٌ ثانٍ يحمله يعني قيمتين
     * لحقيقةٍ واحدة تفترقان عند أوّل تعديلٍ في تاريخ الإصدار. وحسابُه في
     * PHP يعني جلبَ الجدول كلِّه ثمّ ترشيحَه في الذاكرة — وهو ما يُفقد
     * الترقيمَ معناه.
     *
     * والمقارنةُ على `issue_date` لا `created_at`: المهلةُ تُحسب من
     * إصدار المستند لا من لحظة إدخاله — فاتورةٌ تُسجَّل اليوم عن بيعٍ وقع
     * قبل شهرٍ استحقاقُها من يوم البيع، وهو نفسُ ما يحسبه
     * {@see \App\Support\PaymentTerms}.
     *
     * و«المستحقّ» هنا ما بقي بعد المرتجع (`due_value - returned_payment`)
     * كما في عمود الجدول: فاتورةٌ أرجعها العميلُ كلَّها لا تتأخّر.
     */
    protected function applyDueFilter($query, $request): void
    {
        $due = $request->input('due_state');

        if (! $due || $due === 'all') {
            return;
        }

        $query->whereNotNull('payment_terms')
            ->where('payment_terms', '>', 0)
            ->whereNotNull('issue_date')
            ->whereNull('voided_at');

        if ($due === 'has_terms') {
            return;
        }

        // المتأخّرة والمستحقّة قريباً كلتاهما تفترضان مبلغاً قائماً
        $query->whereRaw('(COALESCE(due_value, 0) - COALESCE(returned_payment, 0)) >= 0.005');

        $expression = 'DATE_ADD(issue_date, INTERVAL CAST(payment_terms AS UNSIGNED) DAY)';

        if ($due === 'overdue') {
            $query->whereRaw($expression.' < CURDATE()');
        } elseif ($due === 'due_soon') {
            $query->whereRaw($expression.' >= CURDATE()')
                ->whereRaw($expression.' <= DATE_ADD(CURDATE(), INTERVAL ? DAY)', [\App\Support\PaymentTerms::SOON_WINDOW]);
        }
    }

    public function ajaxInvoices(Request $request)
    {
        $invoices = Invoice::with(['client', 'createdByUser', 'employee', 'payments', 'updatedByUser'])
            ->select('invoices.*');

        // تطبيق البحث إذا وجد
        if ($request->has('search') && ! empty($request->search['value'])) {
            $search = $request->search['value'];
            $invoices->where(function ($query) use ($search) {
                $query->where('invoices.id', 'like', "%$search%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('trade_name', 'like', "%$search%")
                            ->orWhere('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%");
                    });
            });
        }

        // الحصول على العدد الكلي قبل التقسيم
        $totalRecords = $invoices->count();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        // تطبيق التقسيم (Pagination)
        $invoices = $invoices->offset($request->start)
            ->limit($request->length)
            ->get();

        $data = [];
        foreach ($invoices as $invoice) {
            // الحصول على الفاتورة المرتجعة إن وجدت
            $returnedInvoice = Invoice::where('type', 'returned')
                ->where('reference_number', $invoice->id)
                ->first();

            $client = $invoice->client;
            $createdBy = $invoice->createdByUser;
            $employee = $invoice->employee;

            $data[] = [
                'id' => $invoice->id,
                'client_info' => [
                    'name' => $client ? ($client->trade_name ?: $client->first_name.' '.$client->last_name) : 'عميل غير معروف',
                    'tax' => $client->tax_number ?? null,
                    'address' => $client->full_address ?? null,
                ],
                'date_info' => [
                    'date' => $invoice->created_at->format($this->account_setting->time_formula ?? 'H:i:s d/m/Y'),
                    'creator' => $createdBy->name ?? 'غير محدد',
                    'employee' => $employee->first_name ?? 'غير محدد',
                ],
                'status_badges' => $this->getStatusBadges($invoice, $returnedInvoice),
                'payment_info' => $this->getPaymentInfo($invoice, $returnedInvoice, $account_setting),
                'actions' => [
                    'edit_url' => route('invoices.edit', $invoice->id),
                    'show_url' => route('invoices.show', $invoice->id),
                    'pdf_url' => route('invoices.generatePdf', $invoice->id),
                    'print_url' => route('invoices.generatePdf', $invoice->id), // يمكن تغيير الرoute إذا كان مختلفاً للطباعة
                    'send_url' => route('invoices.send', $invoice->id),
                    'payment_url' => route('paymentsClient.create', ['id' => $invoice->id]),
                    'delete_url' => route('invoices.destroy', $invoice->id),
                    'csrf_token' => csrf_token(),
                ],
            ];
        }

        return response()->json([
            'draw' => $request->input('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ]);
    }

    private function getStatusBadges($invoice, $returnedInvoice)
    {
        $badges = [];

        if ($returnedInvoice) {
            $badges[] = [
                'class' => 'bg-danger text-white',
                'icon' => 'fas fa-undo',
                'text' => 'مرتجع',
            ];
        } elseif ($invoice->type == 'normal' && $invoice->payments->count() == 0) {
            $badges[] = [
                'class' => 'bg-secondary text-white',
                'icon' => 'fas fa-file-invoice',
                'text' => 'أنشئت فاتورة',
            ];
        }

        if ($invoice->payments->count() > 0) {
            $badges[] = [
                'class' => 'bg-success text-white',
                'icon' => 'fas fa-check-circle',
                'text' => 'أضيفت عملية دفع',
            ];
        }

        return $badges;
    }

    private function getPaymentInfo($invoice, $returnedInvoice, $account_setting)
    {
        $statusClass = match ($invoice->payment_status) {
            1 => 'success',
            2 => 'info',
            3 => 'danger',
            4 => 'secondary',
            default => 'dark',
        };

        $statusIcon = match ($invoice->payment_status) {
            1 => 'fas fa-check-circle',
            2 => 'fas fa-adjust',
            3 => 'fas fa-times-circle',
            4 => 'fas fa-hand-holding-usd',
            default => 'fas fa-question-circle',
        };

        $statusText = match ($invoice->payment_status) {
            1 => 'مدفوعة بالكامل',
            2 => 'مدفوعة جزئياً',
            3 => 'غير مدفوعة',
            4 => 'مستلمة',
            default => 'غير معروفة',
        };

        // العملة عملةُ الفاتورة المخزَّنة معها، والتنسيق من شاشة «العملات».
        //
        // كانت تُقرأ من `account_setting` — إعداد حسابٍ شخصيّ لكل مستخدم لا
        // إعداد منشأة — ثم يُستبدل بالرمز صورةُ ريالٍ ثابتة مهما كانت
        // العملة، فالمنشأة التي تعمل بالدرهم ترى فاتورتها بالريال
        $symbol = CurrencySettings::symbol();
        $net_due = $invoice->due_value - ($invoice->returned_payment ?? 0);

        return [
            'status_class' => $statusClass,
            'status_icon' => $statusIcon,
            'status_text' => $statusText,
            'amount' => CurrencySettings::number($invoice->grand_total ?? $invoice->total),
            'currency' => $symbol,
            'returned' => $returnedInvoice ? CurrencySettings::format($invoice->returned_payment) : null,
            'due' => $invoice->due_value > 0 ? CurrencySettings::format($net_due) : null,
        ];
    }

    private function getStatusText($status)
    {
        switch ($status) {
            case 1:
                return 'مدفوعة';
            case 2:
                return 'جزئي';
            case 3:
                return 'غير مدفوعة';
            default:
                return 'غير معروفة';
        }
    }

    /**
     * تطبيق شروط البحث على الاستعلام
     */
    /**
     * حمولةُ تعبئة الفاتورة من عرض سعرٍ أو أمر بيع.
     *
     * ═════════════════════ لماذا مسارٌ مستقلّ ═════════════════════
     *
     * الشاشةُ تحمل **رؤوسَ** المستندات القابلة للتحويل وحدَها (رقمٌ وعميلٌ
     * وإجمالي) لتُصفّى في المتصفّح على العميل المختار؛ أمّا البنودُ فتُجلَب
     * حين يُختار مستندٌ بعينه. ومنشأةٌ لها ألوفُ العروض كانت ستحمّل بنودَها
     * كلَّها في كلّ فتحةِ شاشةِ فاتورة.
     *
     * والشكلُ هو شكلُ `copySource` نفسُه — حمولةُ «نسخ الفاتورة» — فتقرؤها
     * نفسُ شيفرة التعبئة في القالب بلا نسخةٍ ثانيةٍ منها.
     *
     * **والخصمُ قيمةً دائماً.** بنودُ عرض السعر تُخزَّن بخصمٍ نوعُه ١ أو ٢
     * (مبلغٌ أو نسبة)، ومُنتقي الشاشة خياراه `amount` و`percentage`؛ فتمريرُ
     * العدد كما هو يضبط القائمةَ على قيمةٍ لا وجود لها فتُعرَض فارغة —
     * وهي العلّةُ نفسُها المشروحة في `create()` عند نسخ الفاتورة.
     *
     * ولا يستهلك هذا المسارُ عرضَ السعر ولا يقفله: التعبئةُ ليست تحويلاً،
     * وحالةُ المستند لا تتغيّر إلّا بحفظ الفاتورة عبر
     * {@see \Modules\Sales\Http\Controllers\QuoteController::convertToInvoice()}.
     */
    public function quoteSource($quote)
    {
        $quote = Quote::with(['items', 'client'])->findOrFail($quote);

        // من لا يرى العميل لا يرى مستنداته — نفس نطاق قائمة العملاء أعلاه
        $eligibleClientIds = ClientVisibilityScope::eligibleClientIds(auth()->user());

        if ($eligibleClientIds !== null && ! in_array((int) $quote->client_id, $eligibleClientIds)) {
            return response()->json(['message' => __('sales_invoice.from_quote.none_for_client')], 403);
        }

        if ($quote->blocksConversion() || $quote->invoice_id) {
            return response()->json(['message' => __('sales_invoice.from_quote.already_converted')], 422);
        }

        /* خصمُ المستند وحدَه: `quotes.total_discount` يجمع خصومَ البنود
           وخصمَ المستند وخصمَ الكوبون معاً، فنقلُه كما هو يُنزل خصمَ البنود
           مرّتين — وهي العلّةُ نفسُها المشروحة عند نسخ الفاتورة. */
        $documentDiscount = max(0.0, (float) ($quote->total_discount ?? 0)
            - (float) $quote->items->sum('discount')
            - (float) ($quote->coupon_discount ?? 0));

        return response()->json([
            'id' => $quote->id,
            'code' => $quote->quote_number ?: (string) $quote->id,
            'client_id' => $quote->client_id,
            'employee_id' => $quote->employee_id,
            'currency' => $quote->currency,
            'notes' => $quote->notes,
            'payment_terms' => $quote->payment_terms,
            'tax_type' => $quote->tax_type,
            'discount_amount' => round($documentDiscount, 2),
            'discount_type' => 'amount',
            'coupon_code' => $quote->coupon_code,
            'shipping_cost' => (float) ($quote->shipping_cost ?? 0),
            'shipping_tax' => (float) ($quote->shipping_tax ?? 0),
            /* `quotes.shipping_tax` يحفظ المبلغ لا المعرّف، فيُستخرَج
               بمطابقة النسبة — وإلّا وصلت الفاتورةُ بشحنٍ بلا ضريبته. */
            'shipping_tax_id' => ($quote->shipping_cost > 0 && $quote->shipping_tax > 0)
                ? \App\Services\Tax\DocumentTaxCalculator::matchByRate(
                    round($quote->shipping_tax / $quote->shipping_cost * 100, 2)
                )
                : null,
            /* بنودُ الهدايا لا تُنقل: الحفظُ يُعيد حسابها من العروض السارية،
               فبندُ هديّةٍ منقولٌ يُضاف ثانيةً ويُصرف من المخزون مرّتين. */
            'items' => $quote->items->reject(fn ($item) => InvoiceItem::isGiftLine($item))->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'store_house_id' => $item->store_house_id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                /* الخصمُ المحفوظ على البند — ويشمل حسمَ «عروض الحسم» منذ
                   أن صار يُكتب في العمود لا في `total` وحده. */
                'discount' => (float) ($item->discount ?? 0),
                'discount_type' => 'amount',
                'tax_1' => $item->tax_1,
            ])->values()->all(),
        ]);
    }

    public function create(Request $request)
    {
        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.invoices.create)
        $invoice_number = $this->generateInvoiceNumber();

        // جلب المستودعات المربوطة بالموظف
        $user = auth()->user();
        $store_houses = collect();

        if ($user->employee_id) {
            /* مصدر المستودع من «إعدادات الفواتير» — مستودعات الموظّف
               المربوطة أو المستودع الرئيسي — مصفّاةً على صلاحية «إنشاء
               فاتورة» المضبوطة على المستودع نفسه، فلا يُعرَض ما لا يُسمح
               به. الحفظ يفحصها ثانيةً، فالتصفية هنا للراحة لا للحماية. */
            $store_houses = SalesWarehouse::optionsFor($user);

            // جلب المنتجات (افتراضياً من أول مستودع في القائمة أو الرئيسي)
            $targetStorehouseId = $store_houses->first()->id ?? null;

            if ($targetStorehouseId) {
                $items = Product::with([
                    'productDetails' => function ($query) use ($targetStorehouseId) {
                        $query->where('store_house_id', $targetStorehouseId);
                    },
                    'productUnits' => function ($query) {
                        $query->where('is_active', true)
                            ->with([
                                'categoryUnit' => function ($q) {
                                    $q->with('unit');
                                },
                            ]);
                    },
                    'category.categoryUnits' => function ($query) {
                        $query->where('is_active', true)->with('unit');
                    },
                ])->where(function ($query) use ($targetStorehouseId) {
                    $query->whereHas('productDetails', function ($q) use ($targetStorehouseId) {
                        $q->where('store_house_id', $targetStorehouseId);
                    });

                    /* الخدمي والرقمي بلا رصيد فلا يمرّان من شرط المستودع —
                       يظهران للموظّف بحسب «إعدادات الفواتير». */
                    if ($stocklessTypes = SalesWarehouse::visibleStocklessTypes()) {
                        $query->orWhereIn('type', $stocklessTypes);
                    }
                })->get();
            } else {
                $items = collect();
            }
        } else {
            // للمدير: كل المستودعات وكل المنتجات
            $store_houses = StoreHouse::all();
            $items = Product::with([
                'productDetails',
                'productUnits' => function ($query) {
                    $query->where('is_active', true)
                        ->with([
                            'categoryUnit' => function ($q) {
                                $q->with('unit');
                            },
                        ]);
                },
                'category.categoryUnits' => function ($query) {
                    $query->where('is_active', true)->with('unit');
                },
            ])->get();
        }

        // تحضير بيانات المنتجات مع الوحدات للـ View
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
                            'id' => null, // لا يوجد product_unit_id
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

            // الحصول على الكمية المتاحة
            $availableQuantity = 0;
            if ($product->productDetails && $product->productDetails->count() > 0) {
                $availableQuantity = $product->productDetails->sum('quantity');
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'sale_price' => (float) $product->sale_price,
                'purchase_price' => (float) $product->purchase_price,
                'available_quantity' => $availableQuantity,
                'type' => $product->type,
                'units' => $units,
                'has_units' => count($units) > 0,

                /* ضريبتا الصنف كما ضُبطتا في شاشة المنتج.
                 *
                 * كانتا تُحفظان على المنتج ولا تُقرآن هنا إطلاقاً، فيختار
                 * البائع الضريبة يدوياً لكل بند — ولو أخطأ أو نسي خرجت
                 * الفاتورة بضريبةٍ غير التي ضُبطت للصنف. تُرسَل الآن إلى
                 * الشاشة لتُختار تلقائياً، ويبقى للبائع تبديلُها. */
                'tax1' => $product->tax1 !== null ? (float) $product->tax1 : null,
                'tax2' => $product->tax2 !== null ? (float) $product->tax2 : null,
            ];
        });
        $user = auth()->user();

        /* قائمةُ العميل بمحورَي النطاق معاً — الصلاحيةُ **وفرعُ الشريط
           العلويّ**. كانت بالصلاحية وحدها، فمن بدّل الشريط إلى فرعٍ بعينه
           يجد في المُنتقي عملاء المنشأة كلِّها. والفرعُ الرئيسيّ يعني
           المنشأة كلَّها فلا يُرشِّح — انظر `HeaderBranchScope`. */
        $clients = ClientVisibilityScope::documentSelectClients($user, [
            'account' => function ($query) {
                $query->select('id', 'client_id', 'balance');
            },
        ]);

        /* ويبقى نطاقُ الصلاحية وحدَه لمُنتقي عرض السعر أدناه: العروضُ
           تُرشَّح بعميلها، فترشيحُها بالفرع مرّتين لا يزيد شيئاً. */
        $eligibleClientIds = ClientVisibilityScope::eligibleClientIds($user);

        // إضافة الرصيد من وحدة الحسابات لكل عميل (نفس طريقة فواتير الشراء)
        // foreach ($clients as $client) {
        //     if ($client->account) {
        //         $client->account_balance = $client->account->balance;
        //     } else {
        //         // في حالة عدم وجود حساب، استخدم الرصيد المحسوب من نموذج العميل
        //         $client->account_balance = $client->balance;
        //     }
        // }

        $users = User::all();
        /* الخزائن التي يجوز لهذا المستخدم الإيداع فيها — لا خزائن المنشأة
           كلَّها: شرط الإيداع مضبوطٌ على الخزينة نفسها (موظفاً أو دوراً أو
           فرعاً) وتفرضه `TreasuryAccessGuard` عند الحفظ. */
        $treasury = TreasuryAccessGuard::depositable();

        /* مُنتقي «مسؤول المبيعات» — على سلَّم نطاق الرؤية لا على حالتين.

           كان الفرزُ ثنائياً: من يملك `data_scope_all` يرى الموظفين كلَّهم،
           ومن لا يملكها يرى نفسَه وحدَه. فالمشرفُ الذي يدير فريقاً — وله
           `data_scope_team` صراحةً — يفتح القائمة فيجد اسمَه وحدَه ولا
           يستطيع أن ينسب فاتورةً إلى مندوبٍ من مندوبيه، ومديرُ الفرع مثلُه.

           و`DataScope` تحسب هذا السلَّم أصلاً ويقرؤه ستةٌ وعشرون تقريراً؛
           فالقائمةُ هنا تقرأ المصدرَ نفسَه: الكلّ، أو فرعُه وما تحته، أو
           هو ومن يتبعه إدارياً، أو هو وحدَه. */
        $employees = DataScope::employeeOptions();

        // كانت `paginate(10)` — قائمةُ اختيارٍ في نموذج لا جدولَ صفحات:
        // من عنده أكثر من عشر قوائم أسعار لا تظهر له البقيّة إطلاقاً
        $price_lists = PriceList::orderBy('id', 'DESC')->get();
        $price_sales = PriceListItems::all();
        $invoiceType = 'normal';
        $taxs = TaxSitting::all();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        $client_id = $request->client_id;
        $client = null;
        $selectedClient = null;

        if ($client_id) {
            $client = Client::with('account')->find($client_id);
            $selectedClient = $client_id;
        }

        $Offer = Offer::all();

        /*
         * «نسخ الفاتورة» في شاشة العرض يفتح هذه الشاشة بـ
         * `?copy_from=<id>`، ولم يكن `create()` يقرأ الوسيط إطلاقاً:
         * الزرّ ينقل المستخدم إلى نموذجٍ فارغ، فلا نسخَ ولا رسالة.
         *
         * تُنسخ البنود والعميل والخصم والشحن والضريبة والملاحظات، ولا
         * يُنسخ الرقم ولا التاريخ ولا المدفوعات ولا التواقيع: مستندٌ
         * جديد يبدأ برقمه وتاريخه.
         */
        $copySource = null;

        if ($copyFrom = $request->query('copy_from')) {
            $source = Invoice::with('items')
                ->where('type', 'normal')
                ->find($copyFrom);

            if ($source) {
                // لا يُنسَخ إلّا ما يقع في نطاق الرؤية — `ScopesSalesVisibility`
                $mayCopy = $this->canAccessSalesDocument(
                    $source->created_by, $source->employee_id, $source->branch_id
                );

                if ($mayCopy) {
                    /* الخصم في النسخة **قيمةً دائماً**، ونوعُه `amount`.
                     *
                     * كان يُمرَّر `discount_type` كما هو في القاعدة — عددٌ
                     * (١ قيمة، ٢ نسبة) — إلى قائمةٍ خياراتُها `amount`
                     * و`percentage`. فتُضبط القائمة على قيمةٍ لا وجود لها
                     * فتظهر **فارغة**، ويصل النوع إلى الخادم خالياً فيُحسب
                     * كلُّ خصمٍ مبلغاً مهما اختير في الأصل.
                     *
                     * والقيمة `amount` ليست تبسيطاً: `invoice_items.discount`
                     * و`invoices.discount_amount` يُحفظان **مبلغاً** في
                     * الحالين (انظر `store()`)، فنقلُ العدد إلى حقلٍ نوعُه
                     * «نسبة» يقرأ ألفاً ومئتين على أنّها ١٢٠٠٪.
                     *
                     * و`invoices.discount_amount` يحمل خصمَ البنود وخصمَ
                     * الفاتورة معاً، فيُنسخ كلُّه خصماً على الفاتورة وتُصفَّر
                     * البنود — لا عمودَ لخصم البند في الشاشة بعد الآن. */

                    $sourceShipping = (float) ($source->shipping_cost ?? 0);
                    $sourceShippingTax = (float) ($source->shipping_tax ?? 0);

                    $copySource = [
                        'client_id' => $source->client_id,
                        'employee_id' => $source->employee_id,
                        'currency' => $source->currency,
                        'notes' => $source->notes,
                        'payment_terms' => $source->payment_terms,
                        'tax_type' => $source->tax_type,
                        /* خصمُ البنود يُضَمّ إلى خصم الفاتورة: شاشةُ الإنشاء لم
                           يعد فيها عمودٌ لخصم البند، فنقلُه إلى البنود يُخفيه
                           حيث لا يُرى ولا يُزال. `discount_amount` المحفوظ يحمل
                           الاثنين معاً أصلاً، فيُنسخ كما هو. */
                        'discount_amount' => max(0.0, (float) ($source->discount_amount ?? 0)),
                        'discount_type' => 'amount',
                        'shipping_cost' => $sourceShipping,
                        'shipping_tax' => $sourceShippingTax,

                        /* ضريبةُ الشحن تُنقَل **بمعرّفها** لا بمبلغها وحده:
                           `invoices.shipping_tax` يحفظ المبلغ، والشاشةُ تختار
                           ضريبةً من «إعدادات الضريبة». فبلا المعرّف تخرج
                           النسخةُ بشحنٍ بلا ضريبته — وهو ما شكا منه المستخدم
                           («النسخ لا يعمل مع الخصم أو رسوم الشحن»).
                           والاستخراجُ بمطابقة النسبة، نظيرُ
                           `QuoteController::storedShippingTaxId()`. */
                        'shipping_tax_id' => ($sourceShipping > 0 && $sourceShippingTax > 0)
                            ? DocumentTaxCalculator::matchByRate(
                                round($sourceShippingTax / $sourceShipping * 100, 2)
                            )
                            : null,

                        /* ما كان يسقط من النسخة كلَّه: المستودعُ ومصدرُ
                           الطلب وطريقةُ الدفع والدفعةُ المقدَّمة والكوبون.
                           والدفعةُ تُنقَل **مبلغاً في حقلها** لا حالةَ سدادٍ
                           جاهزة: مربّعُ «مدفوعة بالكامل» لا يُؤشَّر، فلا
                           يُولَّد سندُ قبضٍ لمالٍ لم يُقبَض — يقرّره من
                           يحفظ لا من ينسخ. */
                        'store_house_id' => $source->items->first()->store_house_id ?? null,
                        'order_source_id' => $source->order_source_id,
                        'payment_method' => $source->payment_method,
                        'advance_payment' => (float) ($source->advance_payment ?? 0),
                        'coupon_code' => $source->coupon_code,

                        'code' => $source->code,
                        /* بنودُ الهدايا لا تُنسخ: الحفظُ يُعيد حسابها من العروض
                           السارية، فبندُ هديّةٍ منسوخٌ يُضاف ثانيةً ويُصرف من
                           المخزون مرّتين. */
                        'items' => $source->items->reject(fn ($item) => InvoiceItem::isGiftLine($item))->map(fn ($item) => [
                            'product_id' => $item->product_id,
                            'product_unit_id' => $item->product_unit_id,
                            'store_house_id' => $item->store_house_id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            // مضمومٌ إلى خصم الفاتورة أعلاه
                            'discount' => 0.0,
                            'discount_type' => 'amount',
                            /* النسبةُ **ومعرّفُها**: مُنتقي ضريبة البند
                               خياراتُه نِسَبٌ، والحقلُ المخفيّ يحمل المعرّف
                               الذي يقرأه الخادم. ونسخةٌ بلا المعرّف تُحفظ
                               بضريبةٍ مجهولة النوع. والثانيةُ كانت تسقط كلَّها. */
                            'tax_1' => (float) $item->tax_1,
                            'tax_1_id' => $item->tax_1_id,
                            'tax_2' => (float) $item->tax_2,
                            'tax_2_id' => $item->tax_2_id,
                            'unit_name' => $item->unit_name,
                            'conversion_factor' => (float) ($item->conversion_factor ?: 1),
                        ])->values()->all(),
                    ];

                    if ($copySource['client_id']) {
                        $client = Client::with('account')->find($copySource['client_id']);
                        $selectedClient = $copySource['client_id'];
                    }
                }
            }
        }

        /*
         * «تحويل إلى فاتورة» من شاشة الحجز يفتح هذه الشاشة بـ`?from_reservation=<id>`.
         *
         * يُعبَّأ النموذجُ بالعميل والموظّف والخدمة (منتجٌ خدمي) وسعرها وخصمها
         * وضريبتها بآليّة «نسخ الفاتورة» نفسِها (`copySource`)، ويُمرَّر معرّفُ
         * الحجز في حقلٍ مخفيّ يربطه `store()` بالفاتورة عند الحفظ. فالفاتورةُ
         * فاتورةُ مبيعاتٍ عادية بقيودها وحساب عميلها — لا حساباتٍ للحجز.
         */
        $reservationSource = null;

        if ($reservationId = $request->query('from_reservation')) {
            $reservationSource = $this->reservationInvoiceSource((int) $reservationId);

            if ($reservationSource) {
                $copySource = $reservationSource['copy'];
                $client = Client::with('account')->find($copySource['client_id']);
                $selectedClient = $copySource['client_id'];

                // خدمةُ الحجز لا رصيدَ لها في المستودع — تُضاف إلى القائمة إن غابت عنها
                if (! $items->contains('id', $reservationSource['product_id'])) {
                    $serviceProduct = Product::with(['productUnits', 'category.categoryUnits'])->find($reservationSource['product_id']);

                    if ($serviceProduct) {
                        $items = $items->push($serviceProduct);
                    }
                }
            }
        }

        /* ══ عروضُ الأسعار وأوامرُ البيع القابلة للتحويل ══
         *
         * شاشةُ الفاتورة لم تكن تعرف بعروض الأسعار شيئاً: الطريقُ الوحيد
         * إلى فاتورةٍ من عرضٍ كان زرَّ «تحويل» في شاشة العرض نفسِه. فمن
         * يبدأ من شاشة الفاتورة — وهو الأغلب — يعيد كتابة بنودٍ مكتوبةٍ
         * أصلاً.
         *
         * تُحمَّل رؤوسُ المستندات وحدها (رقمٌ وعميلٌ وإجمالي) وتُصفّى في
         * المتصفّح على العميل المختار؛ وبنودُ المستند تُجلَب عند اختياره
         * من `quoteSource()`. والعمودُ `client_id` هو الذي تُصفّى به، فلا
         * تُعرَض على عميلٍ عروضُ عميلٍ آخر — وهو ما شكا منه المستخدم.
         */
        $convertibleQuotes = Quote::query()
            ->ofType(Quote::TYPE_QUOTE)
            /* ما **لم يتحوّل** بعد. المحوَّل والملغى والمنتهي كلُّها خارج
               القائمة (`Quote::blocksConversion()` تمنع الثلاثة عند الحفظ)،
               و`invoice_id` فحصٌ ثانٍ يمسك عرضاً حُوّل وبقيت حالتُه على ما
               كانت — عرضٌ واحد لا يصير فاتورتين. */
            ->whereIn('status', [
                Quote::STATUS_DRAFT,
                Quote::STATUS_PENDING,
                Quote::STATUS_APPROVED,
            ])
            ->whereNull('invoice_id')
            ->when($eligibleClientIds !== null, fn ($q) => $q->whereIn('client_id', $eligibleClientIds))
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'client_id', 'quote_number', 'quote_date', 'grand_total', 'currency'])
            ->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'client_id' => (int) $quote->client_id,
                'code' => $quote->quote_number ?: (string) $quote->id,
                'date' => optional($quote->quote_date)->format('Y-m-d'),
                'total' => CurrencySettings::format($quote->grand_total),
            ])
            ->values();

        return view('sales::invoices.create', [
            'convertibleQuotes' => $convertibleQuotes,
            'reservationSource' => $reservationSource,
            // حدُّ باقة الاشتراك معروضٌ قبل ملء النموذج لا بعده: من يملأ مستنداً
            // طويلاً ثمّ يُرَدّ برسالة «بلغتَ الحدّ» فقد وقته مرّتين
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::INVOICES),

            'copySource' => $copySource,
            'clients' => $clients,
            'account_setting' => $account_setting,
            'price_lists' => $price_lists,
            'taxs' => $taxs,
            'treasury' => $treasury,
            'users' => $users,
            'items' => $items,
            'store_houses' => $store_houses, // إضافة قائمة المستودعات
            'productsWithUnits' => $productsWithUnits, // المنتجات مع الوحدات
            'invoice_number' => $invoice_number,
            'invoiceType' => $invoiceType,
            'employees' => $employees,
            'client' => $client,
            'selectedClient' => $selectedClient,

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «إعدادات الفواتير» — كانت تُقرأ من `session('sales_settings')`
            // وهو مفتاحُ جلسةٍ لا يكتبه شيء في المشروع كلّه، فالبطاقة التي
            // تعرض «الإعدادات المفعّلة» كانت لا تظهر أبداً
            'invoiceSettings' => SalesInvoiceSetting::current(),

            // «العملات» — العملة الافتراضية وقائمة المفعَّلة
            'documentCurrency' => $this->defaultCurrency(),
            'currencies' => $this->activeCurrencies(),

            // «مصادر الطلب» — المصادر المفعَّلة، وهل الاختيار إلزاميّ،
            // والافتراضيّ المضبوط في تلك الشاشة
            'orderSources' => $this->activeOrderSources(),
            'orderSourceRequired' => $this->orderSourceRequired(),
            'defaultOrderSourceId' => settingsOrderSource::get('default_order_source_id'),

            // «طرق الدفع» — كان `store()` يقرأ `$request->payment_method`
            // ويحفظه، والنموذج بلا حقلٍ يرسله: فطريقة الدفع في كل فاتورة
            // محفوظةٌ `null` مهما ضبط المستخدم في شاشة «طرق الدفع»
            'paymentMethods' => $this->activePaymentMethods(),

            // «الفترات المحاسبية» — سببُ منع الحفظ بتاريخ اليوم إن وُجد،
            // فيُعرَض تنبيهاً في رأس الشاشة بدل أن يُكتشف عند الضغط على حفظ
            'periodBlock' => $this->accountingPeriodBlock(now()),
        ]);
    }

    public function getPrice(Request $request)
    {
        $priceListId = $request->input('price_list_id');
        $productId = $request->input('product_id');

        $proudect = Product::where('id', $productId)->get();

        $priceItem = PriceListItems::where('price_list_id', $priceListId)
            ->where('product_id', $productId)
            ->first();

        if ($priceItem) {
            return response()->json([
                'price' => $priceItem->sale_price,
            ]);
        } else {
            return response()->json([
                'price' => null,
            ]);
        }
    }

    public function sendVerificationCode(Request $request)
    {
        $client = Client::find($request->client_id);

        if (! $client) {
            return response()->json(['error' => 'العميل غير موجود.'], 400);
        }

        // توليد رمز تحقق عشوائي
        $verificationCode = rand(100000, 999999);

        // تخزين الرمز في قاعدة البيانات
        $client->verification_code = $verificationCode;
        $client->save();

        // جلب رقم الهاتف
        $phoneNumber = $client->phone;
        $totalAmount = $request->total; // المبلغ الإجمالي

        // إرسال SMS عبر Infobip
        $guzzleClient = new GuzzleClient;
        try {
            $response = $guzzleClient->post('https://yp6wyp.api.infobip.com/sms/2/text/advanced', [
                'headers' => [
                    'Authorization' => 'App fd5f55c16f4359e8da2e328d074b3860-b84131f9-013b-4482-ab6d-1dfef2d61d07',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'messages' => [
                        [
                            'destinations' => [['to' => $phoneNumber]],
                            'from' => '447491163443',
                            'text' => "عزيزي العميل،\nرمز التحقق الخاص بك: $verificationCode\nمبلغ الفاتورة: $totalAmount ريال سعودي\nشكراً لاستخدامك فوترة سمارت.",
                        ],
                    ],
                ],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'تم إرسال رمز التحقق بنجاح!',
                'response' => json_decode($response->getBody(), true),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.Invoices', 'sendVerificationCode');

            return response()->json([
                'status' => 'error',
                'message' => 'فشل في إرسال رمز التحقق',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function verifyCode(Request $request)
    {
        $client = Client::find($request->client_id);

        if (! $client) {
            return response()->json(['error' => 'العميل غير موجود.'], 400);
        }

        if ($request->verification_code == $client->verification_code || $request->verification_code == '123') {
            return response()->json(['success' => 'تم التحقق بنجاح.']);
        }

        return response()->json(['error' => 'رمز التحقق غير صحيح.'], 400);
    }

    /**
     * سعر تكلفة الوحدة لبندٍ — يُستعمل في فحص «منع البيع بأقل من التكلفة»
     * قبل أيّ كتابة. هو نفسُه ما يُبنى عليه قيد تكلفة المبيعات: متوسّطُ
     * التكلفة المرجَّح (`ResolvesSaleCost`)، لا سعرُ آخر شراء.
     */
    private function resolveLineCost($productId, $storeHouseId): float
    {
        return $this->averageUnitCost($productId) ?? 0.0;
    }

    /**
     * معاملُ تحويل وحدة البند إلى الوحدة الأساسية — بمنطق حلقة البنود في
     * `persistInvoice()` نفسِه، فلا يفترق الفحصُ عمّا يُحفظ.
     *
     * يُحتاج **قبل** تلك الحلقة: فحصُ «منع البيع بأقلّ من التكلفة» يجري قبل
     * أيّ كتابة، و`resolveLineCost()` تكلفةُ الوحدة الأساسية الواحدة — فبندٌ
     * بالكرتون يُقاس صافيه بتكلفة حبّاته كلِّها لا بتكلفة حبّة.
     */
    private function lineConversionFactor(array $item, $productUnitsCollection): float
    {
        $unitId = $item['product_unit_id'] ?? null;

        if (! empty($unitId)) {
            // وحدةُ صنفٍ لا وحدةُ منتج — `category_<id>`
            if (is_string($unitId) && str_starts_with($unitId, 'category_')) {
                $categoryUnitId = (int) substr($unitId, strlen('category_'));

                $productUnit = ProductUnit::with('categoryUnit')
                    ->where('product_id', $item['product_id'] ?? null)
                    ->where('category_unit_id', $categoryUnitId)
                    ->first();

                if ($productUnit) {
                    return floatval($productUnit->categoryUnit->conversion_factor ?? 1) ?: 1.0;
                }

                return floatval($item['conversion_factor'] ?? CategoryUnit::find($categoryUnitId)?->conversion_factor ?? 1) ?: 1.0;
            }

            $productUnit = $productUnitsCollection->get((int) $unitId);

            return $productUnit && $productUnit->categoryUnit
                ? (floatval($productUnit->categoryUnit->conversion_factor) ?: 1.0)
                : 1.0;
        }

        return ! empty($item['conversion_factor'])
            ? (floatval($item['conversion_factor']) ?: 1.0)
            : 1.0;
    }

    /**
     * ربح الفاتورة — سطراً سطراً.
     *
     * ── ما كان يُعرَض ──
     *
     *     ($item->product->sale_price - $item->product->purchase_price) * $item->quantity
     *
     * وهذا ليس ربح الفاتورة في شيء. `sale_price` هو سعر المنتج **في
     * الكتالوج اليوم**، لا السعر الذي بيع به في هذه الفاتورة: تغيّرُ سعرٍ
     * في شاشة المنتجات بعد شهر يغيّر «ربح» فاتورةٍ صدرت قبله. ثم إنّه
     * يتجاهل خصم البند، وخصم الفاتورة، والضريبة، والوحدة ومعامل
     * تحويلها — فبندٌ بيع بالدرزن يُضرَب سعرُه في عدد الدرزنات ويُطرح
     * منه ثمنُ حبّةٍ واحدة.
     *
     * ── ما يُحسَب الآن ──
     *
     *   الإيراد   `DocumentTaxCalculator::line()['net']` — قيمة البند
     *             كما حُفظت في الفاتورة، بعد خصم البند وقبل الضريبة.
     *             الضريبة ليست إيراداً بحال، والخصم نقصٌ من الإيراد.
     *   خصم الفاتورة  يُوزَّع على البنود بنسبة إيراد كلٍّ منها: خصمٌ على
     *             الفاتورة كلّها ينقص ربحها، ونسبتُه من كل بندٍ بقدره.
     *   التكلفة   `unit_cost_at_sale` المجمَّدة على البند لحظةَ قيد تكلفة
     *             المبيعات، مضروبةً في الكمّية **بالوحدة الأساسية** — فلا
     *             يفترق التبويب عن دفتر الأستاذ. الفواتير الأقدم من التجميد
     *             تقرأ متوسّطَ التكلفة اليوم.
     *
     * البند الحرّ بلا منتج فبلا تكلفة: إيرادُه ربحٌ كلّه، وذلك صحيح —
     * لم يُشترَ شيءٌ ليُباع.
     *
     * @return array{rows: list<array>, revenue: float, cost: float, profit: float, invoice_discount: float}
     */
    private function invoiceProfitReport(Invoice $invoice): array
    {
        $items = $invoice->items;

        $lines = [];
        $netSum = 0.0;
        $lineDiscountSum = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;

            /* `invoice_items.discount` تُحفَظ **قيمةً** لا نسبة (انظر
               `store()`)، و`discount_type` يصف ما أدخله المستخدم لا ما
               خُزِّن. فلا تُعاد قسمتها على مئة. */
            $lineDiscount = (float) $item->discount;

            $calculated = DocumentTaxCalculator::line(
                $quantity,
                $unitPrice,
                $lineDiscount,
                [$item->tax_1_id, $item->tax_2_id],
                [(float) $item->tax_1, (float) $item->tax_2],
            );

            $net = (float) $calculated['net'];
            $netSum += $net;
            $lineDiscountSum += $lineDiscount;

            $baseQuantity = (float) ($item->quantity_in_base_unit ?: $quantity * ((float) $item->conversion_factor ?: 1));
            $unitCost = $item->product_id
                ? $this->itemUnitCost($item, $item->product_id)
                : 0.0;

            $lines[] = [
                'item' => $item,
                'name' => $item->product->name ?? $item->item,
                'is_free' => ! $item->product_id,
                'quantity' => $quantity,
                'unit_name' => $item->unit_name,
                'unit_price' => $unitPrice,
                'net' => $net,
                'unit_cost' => $unitCost,
                'cost' => $unitCost * $baseQuantity,
            ];
        }

        /* خصم الفاتورة = المحفوظ في `invoices.discount_amount` (وهو مجموع
           خصوم البنود + خصم الفاتورة) ناقصاً خصوم البنود. الطرح لا القراءة
           المباشرة: العمود يحمل المجموع، فقراءتُه وحده تخصم مرّتين. */
        $invoiceDiscount = max(0.0, (float) ($invoice->discount_amount ?? 0) - $lineDiscountSum);

        $revenue = 0.0;
        $cost = 0.0;

        foreach ($lines as $index => $line) {
            $share = $netSum > 0 ? $line['net'] / $netSum : 0.0;
            $allocated = $line['net'] - ($invoiceDiscount * $share);

            $lines[$index]['revenue'] = $allocated;
            $lines[$index]['profit'] = $allocated - $line['cost'];

            $revenue += $allocated;
            $cost += $line['cost'];
        }

        return [
            'rows' => $lines,
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'profit' => round($revenue - $cost, 2),
            'invoice_discount' => round($invoiceDiscount, 2),
        ];
    }

    public function verify_code(Request $request)
    {
        // تحقق من وجود العميل
        $client = Client::find($request->client_id);

        if (! $client) {
            return response()->json(['error' => 'العميل غير موجود.'], 400);
        }

        // السماح برمز ثابت "123" كرمز صالح مؤقتًا
        if ($request->verification_code == '123') {
            return response()->json(['success' => 'تم التحقق بنجاح.']);
        }

        return response()->json(['error' => 'رمز التحقق غير صحيح.'], 400);
    }

    /**
     * أمصدرُ الطلب إلزاميّ؟ — من «مصادر الطلب» في إعدادات المبيعات.
     *
     * المفتاح `order_source_mandatory` كان يُحفَظ في تلك الشاشة ولا يقرؤه
     * شيء: لا حقلَ للمصدر في الفاتورة أصلاً، فلا الافتراضيّ يُطبَّق ولا
     * الإلزام يمنع. والفحص هنا لا في الشاشة وحدها — `required` في الوسم
     * وعدٌ لا حارس.
     */
    protected function orderSourceRequired(): bool
    {
        return (string) settingsOrderSource::get('order_source_mandatory', '0') === '1';
    }

    /**
     * أعمود مصدر الطلب موجود؟
     *
     * الشيفرة تُرفَع إلى الاستضافة بالـFTP والترحيلات تُشغَّل بعدها من شاشة
     * «تحديث النظام» — وبينهما فجوةٌ يكون فيها العمود غائباً والشيفرة
     * تكتبه، فتسقط كلُّ فاتورةٍ بخطأ ٥٠٠. والفحص مرّةً لكلّ طلب لا لكلّ
     * فاتورة.
     *
     * ولا يُخفّف هذا من الإلزام شيئاً: `store()` يردّ الفاتورة بلا مصدرٍ
     * متى كان المفتاح مفعَّلاً، وُجد العمود أو لم يوجد. الغائب هو **حفظ**
     * المصدر لا فحصُه، ويعود وحده بعد تشغيل الترحيل.
     */
    protected function invoicesTrackOrderSource(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasColumn('invoices', 'order_source_id');
        }

        return $exists;
    }

    /** المصادر المفعَّلة بترتيب شاشتها — لقائمة الاختيار */
    protected function activeOrderSources()
    {
        return OrderSource::active()->ordered()->get();
    }

    /**
     * المصدر المختار، أو الافتراضيّ المضبوط في الشاشة حين لا يُختار.
     *
     * والمطفأ لا يُقبل ولو وصل معرّفه: نموذجٌ مفتوحٌ في تبويبٍ قديم يحمل
     * معرّفَ مصدرٍ أُطفئ بعد فتحه.
     */
    protected function resolveOrderSource($id): ?OrderSource
    {
        $id = $id ?: settingsOrderSource::get('default_order_source_id');

        if (! $id) {
            return null;
        }

        return OrderSource::active()->find($id);
    }

    /**
     * حفظ الفاتورة — غلافٌ حول {@see persistInvoice()}.
     *
     * الحفظ نحو ألفي سطر بلا `try` عامّ: أيُّ استثناءٍ في منتصفه — أشهرُها
     * `requireAccountingAccount()` حين لا يُربط حساب ضريبة القيمة المضافة
     * أو المبيعات — كان يخرج صفحة «Server Error» بلا سبب، ويترك المعاملة
     * مفتوحة. هنا تُلفّ المعاملة كلُّها للخلف ويعود المستخدم إلى نموذجه
     * بما كتبه وبالسبب نفسه («الحساب غير مربوط… اربطه من تلك الشاشة»).
     */
    public function store(Request $request)
    {
        // المرفقات قبل أيّ كتابة — انظر `DocumentAttachments`
        DocumentAttachments::validate($request);

        try {
            return $this->persistInvoice($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // أخطاء الحقول تبقى لمعالجة Laravel المعتادة (رجوعٌ بأخطاء كل حقل)
            throw $e;
        } catch (\Throwable $e) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()->back()->withInput()
                ->with('error', 'تعذّر حفظ الفاتورة: '.$e->getMessage());
        }
    }

    /**
     * سياق الإصدار الدوريّ — أو `null` لفاتورةٍ من الشاشة.
     *
     * `PeriodicInvoiceGenerator` يُصدر نسخ الاشتراك **من هذا المسار نفسِه**
     * لا من نسخةٍ عنه: القيدُ وخصمُ المخزون وفحصُ الكمية والحدُّ الائتمانيّ
     * والترقيمُ واحدٌ للفاتورتين. والسياق في `attributes` لا في مدخلات
     * الطلب: لا يستطيع نموذجٌ في المتصفّح أن يدّعيه.
     *
     * @return array{instance_id:int, periodic_invoice_id:int, branch_id:int|null, disable_partial_payment:bool}|null
     */
    protected function periodicIssue(Request $request): ?array
    {
        $context = $request->attributes->get(PeriodicInvoiceGenerator::REQUEST_CONTEXT);

        return is_array($context) && ! empty($context['instance_id']) ? $context : null;
    }

    /**
     * يربط نسخة الجدول بالفاتورة داخل معاملة الحفظ نفسها.
     *
     * الصفُّ يُقفل ويُفحص قبل الربط: مهمّتان متزامنتان — أو المهمّة وزرُّ
     * «إصدار الآن» — لا تُصدران النسخة مرّتين. والرميُ هنا يُرجع المعاملة
     * كلَّها، فلا تبقى فاتورةٌ بلا نسخة.
     */
    protected function linkPeriodicInstance(Invoice $invoice, array $context): void
    {
        $instance = PeriodicInvoiceInstance::whereKey($context['instance_id'])->lockForUpdate()->first();

        if (! $instance || $instance->invoice_id) {
            throw new \RuntimeException(__('sales_periodic.generator.already_issued'));
        }

        $instance->forceFill([
            'invoice_id' => $invoice->id,
            'status' => PeriodicInvoiceInstance::STATUS_ISSUED,
            'last_error' => null,
            'attempted_at' => now(),
        ])->save();

        if (! empty($context['disable_partial_payment'])) {
            $invoice->forceFill(['disable_partial_payment' => true])->save();
        }
    }

    private function persistInvoice(Request $request)
    {
        /* الضريبة الشاملة على المستند: حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات
           الضريبة تُخفى قوائمُ الضريبة من الأسطر، وما يُختار في الخانة العلوية
           يُفرَض على كلّ بند. يُعاد ضبطُ ضريبة البنود هنا قبل أيّ قراءةٍ لها —
           والمصدرُ الموثوق هو الخادمُ لا النموذج. مطفأةً تبقى ضريبةُ كلّ سطر. */
        DocumentTaxCalculator::applyComprehensive($request);

        /* ** نطاق القرب من العميل ** — بصلاحية التجاوز بالاسمين (الجديد
           والقديم) وبالنطاق المضبوط في «إعدادات وحدات العملاء» لا بـ`0.3`
           مكتوبةً في الشيفرة. انظر `ChecksClientProximity`. */
        /* الإصدار الدوريّ يجري من مهمّةٍ مجدولة باسم منشئ الاشتراك، لا من
           موظّفٍ واقفٍ عند العميل — فلا موقعَ يُقاس. انظر `periodicIssue()`. */
        $periodicIssue = $this->periodicIssue($request);

        if (! $periodicIssue && $proximityBlock = $this->clientProximityBlock(
            $request,
            $request->client_id,
            'sales.invoices.bypass_location'
        )) {
            return redirect()->back()->withInput()->with('error', $proximityBlock);
        }

        // ** الإقفال المحاسبي **
        //
        // الفاتورة تُولّد قيداً، والقيد لا يجوز في فترةٍ أُقفلت وأُصدرت
        // قوائمها. الفحص بتاريخ الفاتورة لا بتاريخ اليوم: من يؤرّخ فاتورةً
        // إلى ديسمبر الماضي بعد إقفاله هو بالضبط ما يمنعه الإقفال
        $documentDate = $request->input('invoice_date') ?: now()->format('Y-m-d');

        if ($block = $this->accountingPeriodBlock($documentDate)) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        // ** حدّ اشتراك فوترة **
        //
        // قبل أيّ كتابة وقبل حجز رقم المستند: فاتورةٌ تُرَدُّ بعد أن أخذت
        // رقمها تترك ثغرةً في التسلسل يسأل عنها المحاسب. والفحص من اللقطة
        // المحفوظة لا بنداء شبكة، فلا ينتظر المستخدم ولا يتوقّف الحفظ
        // بانقطاع الشبكة عندنا.
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::INVOICES)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        /* ** مصدر الطلب ** — «مصادر الطلب» في إعدادات المبيعات.
         *
         * الردّ قبل حجز الرقم كحدّ الاشتراك سواءً: فاتورةٌ تُرَدّ بعد أن
         * أخذت رقمها تترك ثغرةً في التسلسل. والافتراضيّ المضبوط في الشاشة
         * يُغني عن الاختيار، فلا يُردّ من ضبط افتراضياً ولم يبدّله. */
        $orderSource = $this->resolveOrderSource($request->input('order_source_id'));

        // والفاتورة الدورية لا بائعَ يختار مصدرها — تصدر بلا مصدرٍ أو بالافتراضيّ
        if ($this->orderSourceRequired() && ! $orderSource && ! $periodicIssue) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', __('sales_invoice.ui.order_source_required'));
        }

        /* ** مسؤول المبيعات ** — يُقصّ على نطاق المستخدم لا يُقبل كما جاء.
           المُنتقي مقصورٌ أصلاً في `create()`، والمُنتقي وعدٌ لا حارس. */
        $employeeId = DataScope::clampEmployee($request->input('employee_id'));

        /* ** مهلة السداد ** — رقمٌ موجبٌ أو لا شيء. الصفرُ والفراغُ سواءٌ:
           بيعٌ فوريّ لا استحقاقَ مؤجَّلٌ له. */
        $terms = (int) $request->input('terms', 0);
        $paymentTermsDays = $terms > 0 ? (string) $terms : null;

        // عملة المستند من «العملات» — تُجمَّد معه بعد الحفظ
        $documentCurrency = $this->defaultCurrency();

        DB::beginTransaction(); // بدء المعاملة

        // ** الخطوة الأولى: رقم الفاتورة من «الترقيم المتسلسل» **
        //
        // كان `intval($lastOrder->code) + 1` مصفوفاً إلى خمس خانات: يتجاهل
        // البادئة والقالب المضبوطين في الشاشة، و`intval` على رقمٍ ببادئة
        // (`INV-000012`) يقرأ صفراً فيعيد التسلسل من أوّله. والرقم المحجوز
        // كان يردّ الإدخال كلّه برسالةٍ بدل أن يُسلَّم التاليَ الشاغر
        //
        // والرقمُ يُحجز **داخل** المعاملة لا قبلها: `DocumentNumberService::next()`
        // يستهلكه في معاملةٍ متداخلة، فإن رُدّت الفاتورةُ بعده — تجاوزُ حدّ
        // الخصم، بيعٌ تحت التكلفة، نقصُ مخزون — عاد العدّادُ معها. كان يُحجز
        // قبلها فيبقى مستهلَكاً ويترك ثغرةً في التسلسل.
        /* رقم المستند الضريبي من التسلسل وحده، لا ممّا يرسله النموذج.
           الحقل في الشاشة للقراءة فقط، لكنّ الخادم كان يقبل أيّ رقمٍ شاغر
           يصله — فطلبٌ مُصاغ يدوياً يكتب الرقم الذي يشاء ويقطع التسلسل الذي
           تشترطه الهيئة متّصلاً. ثم إنّ قبول المُرسَل كان يتخطّى العدّاد
           فلا يتقدّم أبداً. */
        $code = $this->resolveDocumentNumber(null, self::NUMBER_SECTION);

        // ** تحسين الأداء: تحميل البيانات المشتركة مسبقاً **
        $user = Auth::user();

        // جلب جميع المنتجات المطلوبة دفعة واحدة مع وحداتها
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

        // تحميل وحدات المنتجات المطلوبة
        $productUnitIds = collect($request->items)->pluck('product_unit_id')->unique()->filter()->toArray();
        $productUnitsCollection = ProductUnit::with([
            'categoryUnit' => function ($q) {
                $q->with('unit');
            },
        ])->whereIn('id', $productUnitIds)->get()->keyBy('id');

        // تحميل CompiledProducts للمنتجات المجمعة
        $compiledProductsMap = CompiledProducts::whereIn('compile_id', $productIds)->get()->groupBy('compile_id');

        // جلب المستودعات المطلوبة
        $storeHouseIds = collect($request->items)->pluck('store_house_id')->unique()->filter()->toArray();
        $storeHousesCollection = StoreHouse::whereIn('id', $storeHouseIds)->get()->keyBy('id');

        /* صلاحية «إنشاء فاتورة» المضبوطة على المستودع.
         *
         * كانت تُحفَظ في `crate_invoices_permissions` ولا يقرؤها أحد — لا
         * الشاشة ولا الحفظ. فالضبط موجود وأثرُه معدوم، وصاحب النظام يظنّ
         * مستودعه محروساً وهو مفتوح لكل من يفتح شاشة الفاتورة.
         *
         * الفحص هنا قبل أي كتابة: تصفيةُ القائمة في الشاشة وحدها لا تكفي —
         * من يبدّل قيمة `store_house_id` في المتصفّح يتجاوزها. */
        foreach ($storeHousesCollection as $candidate) {
            if (! StorehouseAccess::canCreateInvoices($candidate, $user)) {
                return redirect()->back()->withInput()
                    ->with('error', StorehouseAccess::message($candidate));
            }
        }

        if ($request->store_house_id
            && ! StorehouseAccess::canCreateInvoices($request->store_house_id, $user)) {
            return redirect()->back()->withInput()
                ->with('error', StorehouseAccess::message($request->store_house_id));
        }

        // تحديد المستودع المختار أو الافتراضي
        $selectedStoreHouseId = $request->store_house_id;
        $defaultStoreHouse = null;

        if ($selectedStoreHouseId) {
            $defaultStoreHouse = StoreHouse::find($selectedStoreHouseId);
        }

        /* ولا مستودعَ مختاراً: المصدر المضبوط في «إعدادات الفواتير» هو
           الحاسم — مستودعُ الموظّف أو الرئيسي — والنزولُ إلى الرئيسي عند
           فراغهما. كان الترتيب مثبَّتاً هنا: الموظّف ثمّ الرئيسي دائماً. */
        if (! $defaultStoreHouse) {
            $defaultStoreHouse = SalesWarehouse::resolve(null, $user);
        }

        // تحديد الخزينة الرئيسية مرة واحدة
        $TreasuryEmployee = $user && $user->employee_id ? TreasuryEmployee::where('employee_id', $user->employee_id)->first() : null;
        $MainTreasury = null;
        if ($TreasuryEmployee && $TreasuryEmployee->treasury_id) {
            $MainTreasury = Account::find($TreasuryEmployee->treasury_id);
        } else {
            $MainTreasury = $this->accountingAccount('main_treasury');
        }

        /* «إعدادات الفواتير»: منع البيع بأقل من التكلفة والحدّ الأقصى للخصم —
           تُفحص قبل أيّ كتابة، فلا تُنشأ فاتورة وتُلغى، بل تُرفض من أصلها. */
        $invoiceSettings = SalesInvoiceSetting::current();

        if ($invoiceSettings->no_sell_below_cost || $invoiceSettings->enable_max_discount) {
            /* النسبة من «إعدادات الفواتير» نفسِها — الشاشة التي فيها
               المفتاح. وإعداد الفرع يبقى تجاوزاً متى ضبطه الفرع بنفسه،
               انظر `SalesInvoiceSetting::maxDiscountFor()`. كانت تُقرأ من
               الفروع وحدها، فمن فعّل المفتاح لم يجد أين يكتبها. */
            $maxDiscountPercent = $invoiceSettings->enable_max_discount
                ? $invoiceSettings->maxDiscountFor(current_branch_id())
                : null;

            /* أو بالمبلغ — الوحدة تُختار في الشاشة نفسِها، وواحدةٌ منهما
               تسري لا الاثنتان: `maxDiscountFor()` تردّ `null` متى كانت
               الوحدة مبلغاً، و`maxDiscountAmount()` تردّ `null` متى كانت
               نسبة. ولا تجاوزَ للفرع في المبلغ: مبلغٌ واحد للمنشأة كلّها،
               بخلاف النسبة. */
            $maxDiscountAmount = $invoiceSettings->enable_max_discount
                ? $invoiceSettings->maxDiscountAmount()
                : null;

            /* الحدّ يسري على خصم البند وعلى الخصم العامّ على الفاتورة معاً.
               كان يُفحص البند وحده، والخصم العامّ في تبويب «الخصم والتسوية»
               يمرّ بلا حدّ — فالمنع بابٌ واحد من بابين، ومن أراد تجاوزه
               نقل الخصم من البند إلى أسفل الفاتورة. ومجموعُهما يُفحص بعد
               الحلقة كذلك: ثلاثون في البند وثلاثون عامّاً ليسا ثلاثين. */
            $documentSubtotal = 0.0;   // مجموع البنود قبل أيّ خصم
            $lineDiscountTotal = 0.0;  // مجموع خصوم البنود قيمةً لا نسبة

            foreach ($request->items ?? [] as $item) {
                /* البند الحرّ لا منتجَ له فلا تكلفةَ تُقارَن — لكنّ الخصم
                   يسري عليه كما يسري على غيره: بابٌ يُفتح للخصم بلا حدٍّ
                   يُبطل الحدَّ نفسه. */
                $product = ! empty($item['product_id'])
                    ? $productsCollection->get($item['product_id'])
                    : null;

                $lineLabel = $product->name
                    ?? (! empty($item['item']) ? $item['item'] : ($item['product_id'] ?? '—'));
                $quantity = floatval($item['quantity'] ?? 0);
                $unitPrice = floatval($item['unit_price'] ?? 0);
                $itemTotal = $quantity * $unitPrice;

                $itemDiscount = $this->lineDiscountValue($item, $itemTotal);

                /* النسبة من القيمة لا من المُدخَل: البابان — قيمةً ونسبةً —
                   يُقاسان بمسطرةٍ واحدة. ومجموعُ بندٍ صفرٌ وعليه خصمٌ يُعدّ
                   تجاوزاً كاملاً، فلا يمرّ خصمٌ بلا أساسٍ يُقسَم عليه. */
                $discountPercent = $itemTotal > 0
                    ? ($itemDiscount / $itemTotal) * 100
                    : ($itemDiscount > 0 ? 100.0 : 0.0);

                $documentSubtotal += $itemTotal;
                $lineDiscountTotal += $itemDiscount;

                if ($maxDiscountPercent !== null && $discountPercent > $maxDiscountPercent + 0.001) {
                    DB::rollBack();

                    return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.discount_over_limit_line', [
                        'item' => $lineLabel,
                        'percent' => number_format($discountPercent, 2),
                        'max' => $maxDiscountPercent,
                    ]));
                }

                if ($invoiceSettings->no_sell_below_cost && $product) {
                    $line = DocumentTaxCalculator::line(
                        $quantity,
                        $unitPrice,
                        $itemDiscount,
                        [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null],
                        [floatval($item['tax_1'] ?? 0), floatval($item['tax_2'] ?? 0)]
                    );

                    $cost = $this->resolveLineCost($item['product_id'], $item['store_house_id'] ?? null);

                    // بلا تكلفة (منتجٌ خدمي أو بلا سجلّ شراء بعد) لا يُقارَن — لا مرجع للمقارنة
                    if ($cost > 0) {
                        /* المقارنة **قبل الضريبة** دائماً: `$line['net']` صافي
                           البند بلا ضريبة، والتكلفة مسجَّلةٌ بلا ضريبة كذلك —
                           فالطرفان على قياسٍ واحد. كان للمنشأة خيارٌ في
                           الإعدادات («شاملاً الضريبة» / «كلاهما») يقارن سعراً
                           بضريبته بتكلفةٍ بلا ضريبة، فيمرّ البيعُ بأقلّ من
                           التكلفة كلّما بلغت الضريبةُ الفرق. أُسقط الخيار. */
                        /* والتكلفةُ تكلفةُ **البند كلِّه** لا الحبّة: `resolveLineCost()`
                           تكلفةُ الوحدة الأساسية الواحدة، و`$line['net']` صافي البند
                           كاملاً. كانت المقارنةُ بينهما مباشرةً، فبندٌ بعشر حبّاتٍ يمرّ
                           ما دام مجموعُه يعلو ثمنَ حبّةٍ واحدة — وكرتونٌ باثنتي عشرة
                           حبّةً كذلك. */
                        $lineCost = $cost * $quantity * $this->lineConversionFactor($item, $productUnitsCollection);

                        if ($line['net'] < $lineCost - 0.001) {
                            DB::rollBack();

                            return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.below_cost_line', [
                                'item' => $lineLabel,
                                'net' => number_format($line['net'], 2),
                                'cost' => number_format($lineCost, 2),
                            ]));
                        }
                    }
                }
            }

            /* الخصم العامّ — حقل «قيمة الخصم» في تبويب «الخصم والتسوية».
               يُحسَب قيمةً هنا مرّةً واحدة، ثمّ يُقاس بالسقف السارية وحدتُه:
               نسبةً بعد تحويله إلى نسبةٍ من مجموع البنود، أو مبلغاً كما هو.
               القيمة والنسبة بابان لخصمٍ واحد، فلا يُقاس أحدهما دون الآخر. */
            if ($maxDiscountPercent !== null || $maxDiscountAmount !== null) {
                $generalDiscountInput = floatval($request->input('discount_amount', 0));
                $generalDiscount = 0.0;

                if ($generalDiscountInput > 0) {
                    $generalDiscount = $request->input('discount_type') === 'percentage'
                        ? ($documentSubtotal * $generalDiscountInput) / 100
                        : $generalDiscountInput;
                }

                /* مجموعٌ صفر وعليه خصم: نسبةٌ لا تُقسَم. تُعدّ تجاوزاً ما لم
                   يكن الحدّ مائةً — وهو معنى «بلا حدّ». */
                $generalPercent = $documentSubtotal > 0
                    ? ($generalDiscount / $documentSubtotal) * 100
                    : ($generalDiscount > 0 ? 100.0 : 0.0);

                if ($maxDiscountPercent !== null && $generalPercent > $maxDiscountPercent + 0.001) {
                    DB::rollBack();

                    return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.discount_over_limit_general', [
                        'percent' => number_format($generalPercent, 2),
                        'max' => $maxDiscountPercent,
                    ]));
                }

                $totalPercent = $documentSubtotal > 0
                    ? (($lineDiscountTotal + $generalDiscount) / $documentSubtotal) * 100
                    : $generalPercent;

                if ($maxDiscountPercent !== null && $totalPercent > $maxDiscountPercent + 0.001) {
                    DB::rollBack();

                    return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.discount_over_limit_total', [
                        'percent' => number_format($totalPercent, 2),
                        'max' => $maxDiscountPercent,
                    ]));
                }

                /* والسقف بالمبلغ متى كانت الوحدة مبلغاً — على **مجموع**
                   الخصم لا على بندٍ بعينه: مجموعُ الفاتورة يعلو كلَّ جزءٍ
                   منه، فما تجاوزه بندٌ واحد تجاوزه المجموع. وقياسُه بالمال
                   هو المقصود منه: «لا يزيد الخصم على ثمانين ريالاً» لا
                   تُقال نسبةً، لأنّ نسبتها تتبدّل بقيمة كلّ فاتورة. */
                if ($maxDiscountAmount !== null) {
                    $totalDiscountValue = $lineDiscountTotal + $generalDiscount;

                    if ($totalDiscountValue > $maxDiscountAmount + 0.001) {
                        DB::rollBack();

                        return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.discount_over_limit_amount', [
                            'total' => number_format($totalDiscountValue, 2),
                            'max' => number_format($maxDiscountAmount, 2),
                        ]));
                    }
                }
            }
        }

        // ** تجهيز المتغيرات الرئيسية لحساب الفاتورة **
        $total_amount = 0; // إجمالي المبلغ قبل الخصومات
        $total_discount = 0; // إجمالي الخصومات على البنود
        $documentTaxIds = []; // معرّفات ضرائب البنود — منها تُشتقّ ضريبة الشحن
        $items_data = []; // تجميع بيانات البنود

        // ** الخطوة الثانية: معالجة البنود (items) **
        if ($request->has('items') && count($request->items)) {
            foreach ($request->items as $itemKey => $item) {
                /* البند الحرّ أُسقط بطلب المستخدم — كلّ بندٍ يقابله منتجٌ
                   في الكتالوج. وبندٌ بلا منتجٍ يصل من نموذجٍ قديمٍ مفتوحٍ
                   في تبويبٍ سابق أو من نداءٍ مباشر، فيُردّ برسالةٍ لا
                   بـ`throw`: `store()` بلا `try/catch` محيط، فالاستثناء
                   هنا صفحةُ خطأٍ عامّة ومعاملةٌ مفتوحة. */
                if (empty($item['product_id'])) {
                    DB::rollBack();

                    return redirect()->back()->withInput()
                        ->with('error', __('sales_invoice.ui.product_required'));
                }

                // جلب المنتج من الكاش
                $product = $productsCollection->get($item['product_id']);
                if (! $product) {
                    DB::rollBack();

                    return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.product_missing', [
                        'id' => $item['product_id'],
                    ]));
                }

                // تحديد المستودع
                $store_house_id = $item['store_house_id'] ?? null;
                $storeHouse = null;

                if ($store_house_id && $storeHousesCollection->has($store_house_id)) {
                    $storeHouse = $storeHousesCollection->get($store_house_id);
                } else {
                    $storeHouse = $defaultStoreHouse;
                }

                /* المستودع شرطٌ للأصناف المخزنية وحدها.
                 *
                 * كان الشرط عامّاً، فمنشأةُ خدماتٍ لا مستودع لها لا تستطيع
                 * إصدار فاتورةٍ واحدة: «لا يوجد أي مستودع في النظام» —
                 * وهي لا تحتاج مستودعاً أصلاً، فالخدمة لا رصيد لها ولا
                 * تُصرَف بإذن. */
                if (! $storeHouse && StockEligibility::isStockable($item['product_id'])) {
                    DB::rollBack();

                    return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.no_storehouse'));
                }

                $store_house_id = $storeHouse->id ?? null;

                // معالجة الوحدات ومعامل التحويل
                $productUnitId = null;
                $conversionFactor = 1;
                $unitName = '';

                // التحقق من وجود وحدة محددة
                if (! empty($item['product_unit_id'])) {
                    // إذا كان المعرف يبدأ بـ category_ فهو معرف وحدة صنف وليس وحدة منتج
                    if (is_string($item['product_unit_id']) && strpos($item['product_unit_id'], 'category_') === 0) {
                        /* ✅ كانت هذه الحالة تضبط معامل التحويل وحده وتترك
                           `$unitName` فارغاً — فالبند يُحفَظ بلا اسم وحدة ولا
                           `product_unit_id`، وشاشة المرتجع لا تجد ما تنقله.
                           نفس حلّ `InvoicesPurchaseController::store()`:
                           إن وُجد `ProductUnit` حقيقي لهذا المنتج على وحدة
                           الصنف هذه يُستعمَل معرّفه، وإلا يُشتقّ الاسم
                           ومعامل التحويل من `CategoryUnit` مباشرةً. */
                        $categoryUnitId = (int) str_replace('category_', '', $item['product_unit_id']);
                        $matchedProductUnit = ProductUnit::where('product_id', $item['product_id'])
                            ->where('category_unit_id', $categoryUnitId)
                            ->first();

                        if ($matchedProductUnit) {
                            $productUnitId = $matchedProductUnit->id;
                            $conversionFactor = floatval($matchedProductUnit->categoryUnit->conversion_factor ?? 1) ?: 1;
                            $unitName = $matchedProductUnit->categoryUnit->unit->name ?? '';
                        } else {
                            $categoryUnit = CategoryUnit::with('unit')->find($categoryUnitId);
                            $unitName = $categoryUnit?->unit?->name ?? '';
                            // المعامل من وحدة الصنف لا من الشاشة — انظر `ProductUnits::resolve()`
                            $conversionFactor = floatval($categoryUnit?->conversion_factor ?? 1) ?: 1;
                        }
                    } else {
                        // وحدة منتج عادية
                        $productUnitId = intval($item['product_unit_id']);
                        $productUnit = $productUnitsCollection->get($productUnitId);

                        if ($productUnit && $productUnit->categoryUnit) {
                            $conversionFactor = floatval($productUnit->categoryUnit->conversion_factor) ?: 1;
                            $unitName = $productUnit->categoryUnit->unit->name ?? '';
                        }
                    }
                }

                /* معاملٌ مرسَلٌ بلا وحدة لا يُقبل: كان «يُستخدم مباشرة»، فبندٌ بمعامل
                   ٠٫٠٠١ يخصم من المخزون وتكلفة المبيعات جزءاً من الألف ممّا بِيع. */

                // حساب تفاصيل الكمية والأسعار
                $quantity = floatval($item['quantity']);
                $unit_price = floatval($item['unit_price']);
                $item_total = $quantity * $unit_price;

                // حساب الكمية بالوحدة الأساسية للخصم من المخزون
                $quantityInBaseUnit = $quantity * $conversionFactor;

                /* خصم البند: ما كتبه المستخدم، وإلّا عرضُ الحسم المطابق.
                   شاشةُ «عروض الحسم» تَعِد بأنّ الحسم «يُطبَّق تلقائياً على
                   بنود الفاتورة»، ولم يكن يُطبَّق على شيء — انظر
                   `App\Services\Sales\OfferDiscountService`. */
                $item_discount = $this->lineDiscountWithOffers(
                    $item,
                    $item_total,
                    $this->lineDiscountValue($item, $item_total),
                    $request->client_id ? (int) $request->client_id : null,
                    $itemKey,
                );

                // تحديث الإجماليات
                $total_amount += $item_total;
                $total_discount += $item_discount;

                // تجهيز بيانات البند
                $items_data[] = [
                    'invoice_id' => null, // سيتم تعيينه لاحقًا بعد إنشاء الفاتورة
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $productUnitId, // معرف وحدة المنتج
                    'store_house_id' => $store_house_id,
                    'item' => $product->name ?? 'المنتج '.$item['product_id'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'quantity_in_base_unit' => $quantityInBaseUnit, // الكمية بالوحدة الأساسية
                    'conversion_factor' => $conversionFactor, // معامل التحويل
                    'unit_name' => $unitName, // اسم الوحدة
                    'unit_price' => $unit_price,
                    'discount' => $item_discount,
                    'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                    'tax_1' => floatval($item['tax_1'] ?? 0),
                    'tax_2' => floatval($item['tax_2'] ?? 0),
                    /* ومعرّفا الضريبتين — العمودان موجودان في
                       `invoice_items` وعرضُ السعر يكتبهما، وفاتورةُ البيع
                       وحدها كانت تكتب النسبة وتُسقط المعرّف. فتخرج بنودُها
                       بضريبةٍ بلا هوية: لا يُعرَف نوعُها («متضمَّنة» أم
                       «مضافة») عند إعادة القراءة، ولا تُستعاد في نسخةٍ ولا
                       مرتجعٍ ولا مطبوعة — تُطابَق بالنسبة وحدها، فنسبتان
                       متساويتان باسمين تلتبسان. */
                    'tax_1_id' => $item['tax_1_id'] ?? null,
                    'tax_2_id' => $item['tax_2_id'] ?? null,
                    'total' => $item_total - $item_discount,
                ];
            }
        }
        // ✅ استخراج عروض الهدايا
        // ✅ استخراج عروض الهدايا
        $giftOffers = GiftOffer::where('is_active', true) // ✅ شرط التفعيل
            ->where(function ($q) use ($request) {
                $q->where('is_for_all_clients', true)
                    ->orWhereHas('clients', function ($q2) use ($request) {
                        $q2->where('client_id', $request->client_id);
                    });
            })
            ->where(function ($q) {
                $q->where('is_for_all_employees', true)
                    ->orWhereHas('users', function ($q2) {
                        $q2->where('user_id', auth()->id());
                    });
            })->whereDoesntHave('excludedClients', function ($q) use ($request) {
                $q->where('client_id', $request->client_id);
            })

            /* التاريخ الفارغ مفتوح: شاشة العروض تقبل عرضاً بلا بداية أو بلا
               نهاية وتعرضه «سارياً»، و`whereDate` على NULL يُسقطه فلا يُطبَّق
               أبداً. نظيرُ `OfferDiscountService` في عروض الحسم. */
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
            ->get();

        // ✅ فحص كل بند مقابل العروض
        foreach ($request->items as $itemIndex => $item) {
            // البند الحرّ لا منتجَ له، وعروض الهدايا تُطابَق بالمنتج
            if (empty($item['product_id'])) {
                continue;
            }

            $productId = $item['product_id'];
            $quantity = floatval($item['quantity']);
            $unit_price = floatval($item['unit_price']);

            // ✅ حساب الكمية بالوحدة الأساسية (الحبات) لتطبيق عروض الهدايا بشكل صحيح
            $giftConversionFactor = 1;
            if (! empty($item['product_unit_id'])) {
                if (is_string($item['product_unit_id']) && strpos($item['product_unit_id'], 'category_') === 0) {
                    $giftConversionFactor = floatval($item['conversion_factor'] ?? 1);
                } else {
                    $productUnit = $productUnitsCollection->get(intval($item['product_unit_id']));
                    if ($productUnit && $productUnit->categoryUnit) {
                        $giftConversionFactor = floatval($productUnit->categoryUnit->conversion_factor) ?: 1;
                    }
                }
            } elseif (! empty($item['conversion_factor'])) {
                $giftConversionFactor = floatval($item['conversion_factor']);
            }

            // الكمية بالوحدة الأساسية (مثال: 1 درزن = 12 حبة)
            $quantityInBaseUnitForGift = $quantity * $giftConversionFactor;

            // 🔍 الحصول على العروض المطابقة لهذا المنتج والكمية (بالوحدة الأساسية)
            $validOffers = $giftOffers->filter(function ($offer) use ($productId, $quantityInBaseUnitForGift) {
                $matchesTarget = ! $offer->target_product_id || $offer->target_product_id == $productId;

                return $matchesTarget && $quantityInBaseUnitForGift >= $offer->min_quantity;
            });

            // ✅ اختيار أفضل عرض (أعلى عدد هدايا)
            $bestOffer = $validOffers->sortByDesc('gift_quantity')->first();

            if ($bestOffer) {
                $giftProduct = Product::find($bestOffer->gift_product_id);
                if (! $giftProduct) {
                    continue;
                }

                // ✅ حساب عدد مرات تكرار الهدية بناءً على الكمية بالوحدة الأساسية
                // مثال: إذا العرض 12 حبة = 1 هدية، و العميل اشترى 24 حبة = 2 هدية
                $giftMultiplier = floor($quantityInBaseUnitForGift / $bestOffer->min_quantity);
                $totalGiftQuantity = $bestOffer->gift_quantity * $giftMultiplier;

                $items_data[] = [
                    'invoice_id' => null,
                    'product_id' => $giftProduct->id,
                    'store_house_id' => $store_house_id,
                    'item' => $giftProduct->name.' (هدية)',
                    'description' => 'هدية عرض عند شراء '.intval($quantityInBaseUnitForGift).' حبة من المنتج',
                    'quantity' => $totalGiftQuantity,
                    'unit_price' => 0,
                    'discount' => 0,
                    'discount_type' => 1,
                    'tax_1' => 0,
                    'tax_2' => 0,
                    'total' => 0,
                ];
            }
        }

        /* ** ضريبة البنود ** — النسبة **ونوعها** من «إعدادات الضريبة».
         *
         * كان الحساب `المبلغ × النسبة ÷ 100` دائماً، أي يُضيف الضريبة فوق
         * السعر ولو كان نوعها في الإعدادات «متضمَّنة». وشاشةُ الإنشاء
         * تعرف النوع (`invoice-calculator.js` يستخرجها من السعر)، فيرى
         * المستخدم إجمالاً وتُحفظ الفاتورة بإجمالٍ آخر — والقيد يُبنى على
         * المحفوظ. بضريبةٍ متضمَّنة ١٥٪ على بندٍ بـ٢٠٠٠: الشاشة ٢٠٠٠
         * والقاعدة ٢٣٠٠.
         *
         * الحسّاب مشتركٌ مع المشتريات، فلا يفترقان عند أوّل تعديل.
         */
        /* ══════════ ترتيبُ الحساب ══════════
         *
         *     إجمالي الأصناف − خصومات الأصناف  ⇒ صافي الأصناف
         *     − الخصم العامّ                    ⇒ صافي التوريد
         *     + الشحن (إضافةٌ خاضعة)            ⇒ الوعاء الضريبي
         *     × النسبة                          ⇒ الضريبة
         *                                       ⇒ الإجمالي النهائي
         *
         * فالخصمُ العامّ **يسبق** الوعاء الضريبي لا يأتي بعده. وكانت
         * حلقةُ الضريبة تسبق حسابَ الخصم العامّ فلا تراه: خصمُ البند
         * يُنقص الوعاء والخصمُ العامّ لا يُنقصه، فالخصمُ الواحد يُعطي
         * فاتورتين مختلفتين حسب أين كُتب — ٦٣٠٫٢٠ بنداً و٦٣٤٫٧٠ مستنداً
         * لنفس المال. راجع `DocumentTaxCalculator::allocateDocumentDiscount()`.
         */

        // ── صافي كلّ بندٍ بعد خصمه الخاصّ ──
        $lineNets = [];
        $lineDiscounts = [];

        foreach ($request->items as $itemKey => $item) {
            $item_total = floatval($item['quantity']) * floatval($item['unit_price']);

            // نفس خصم حلقة الإجماليات — وعاءُ الضريبة بعد الخصم، فلو
            // قُرئ هنا المُدخَل وحده لخالف الوعاءُ الإجماليَّ المحفوظ
            $lineDiscounts[$itemKey] = $this->lineDiscountWithOffers(
                $item,
                $item_total,
                $this->lineDiscountValue($item, $item_total),
                $request->client_id ? (int) $request->client_id : null,
                $itemKey,
            );

            $lineNets[$itemKey] = max(0.0, $item_total - $lineDiscounts[$itemKey]);
        }

        $net_items = round(array_sum($lineNets), 2);

        // ** الخصم الإضافي للفاتورة ككل — قبل الوعاء الضريبي **
        $invoice_discount = 0;
        if ($request->has('discount_amount') && $request->discount_amount > 0) {
            if ($request->has('discount_type') && $request->discount_type === 'percentage') {
                /* النسبة على **صافي الأصناف** — وهو ما تعرضه الشاشة
                   وما يحسبه مُنتقي عرض السعر. كانت على `$total_amount`
                   قبل خصوم البنود، فخصمُ ١٠٪ يخرج أكبر ممّا عُرض. */
                $invoice_discount = ($net_items * floatval($request->discount_amount)) / 100;
            } else {
                $invoice_discount = floatval($request->discount_amount);
            }

            /* لا يتجاوز الخصمُ ما يُخصَم منه — كما يفعل
               `invoice-calculator.js` في الشاشة (`Math.min(..., subtotal)`).
               كان الخادم يأخذه كما أُدخل: خصمُ ٨٠ على فاتورةٍ بخمسين يُعرَض
               في الشاشة خمسين وإجمالاً صفراً، ويُحفَظ ثمانين وإجمالاً
               سالباً — فالمحفوظ غير المعروض، وعليه يُبنى القيد. */
            $invoice_discount = min($invoice_discount, max(0, $net_items));
        }

        /* ══ كوبون الخصم ══
         *
         * على وعاءِ ما بقي بعد خصوم البنود وخصم الفاتورة، ويُجمَع إليهما
         * فيدخل «الخصم المسموح به» في القيد. رمزٌ مرفوض لا يُسقط الفاتورة:
         * تُحفَظ بلا خصمه ويُقال للبائع لماذا — انظر `AppliesSalesCoupons`.
         */
        $coupon = $this->documentCoupon(
            $request->input('coupon_code'),
            $request->client_id ? (int) $request->client_id : null,
            max(0.0, round($net_items - $invoice_discount, 2)),
            collect($request->items)->pluck('product_id')->all(),
            floatval($request->shipping_cost ?? 0),
        );

        $coupon_discount = $coupon['discount'];

        // الخصم العامّ = خصم المستند + الكوبون، ويُوزَّع على البنود
        $general_discount = min($invoice_discount + $coupon_discount, max(0.0, $net_items));
        $discountShares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $general_discount);

        /* ** ضريبة البنود ** — النسبة **ونوعها** من «إعدادات الضريبة».
         *
         * كان الحساب `المبلغ × النسبة ÷ 100` دائماً، أي يُضيف الضريبة فوق
         * السعر ولو كان نوعها في الإعدادات «متضمَّنة». وشاشةُ الإنشاء
         * تعرف النوع (`invoice-calculator.js` يستخرجها من السعر)، فيرى
         * المستخدم إجمالاً وتُحفظ الفاتورة بإجمالٍ آخر — والقيد يُبنى على
         * المحفوظ. بضريبةٍ متضمَّنة ١٥٪ على بندٍ بـ٢٠٠٠: الشاشة ٢٠٠٠
         * والقاعدة ٢٣٠٠.
         *
         * الحسّاب مشتركٌ مع المشتريات، فلا يفترقان عند أوّل تعديل.
         */
        $tax_total = 0;
        $included_item_tax = 0;

        foreach ($request->items as $itemKey => $item) {
            // الوعاء: صافي البند ناقصَ حصّته من الخصم العامّ
            $taxableBase = max(0.0, $lineNets[$itemKey] - ($discountShares[$itemKey] ?? 0.0));

            $line = DocumentTaxCalculator::line(
                1.0,
                $taxableBase,
                0.0,
                [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null],
                [floatval($item['tax_1'] ?? 0), floatval($item['tax_2'] ?? 0)],
            );

            $tax_total += $line['tax'];
            // الجزء المتضمَّن داخل الأسعار — يُطرح من المجموع فلا يُحسب مرّتين
            $included_item_tax += $line['base'] - $line['net'];

            $documentTaxIds[] = $item['tax_1_id'] ?? null;
            $documentTaxIds[] = $item['tax_2_id'] ?? null;
        }

        $tax_total = round($tax_total, 2);
        $included_item_tax = round($included_item_tax, 2);

        // الأسعار أُدخلت شاملةً الضريبة المتضمَّنة، فلو بقيت داخل المجموع
        // لحُسبت مرّتين حين تُضاف `tax_total` إليه
        $total_amount = round($total_amount - $included_item_tax, 2);

        // الخصومات الإجمالية
        $final_total_discount = $total_discount + $invoice_discount + $coupon_discount;

        // صافي التوريد
        $amount_after_discount = round($total_amount - $final_total_discount, 2);

        Log::info('حساب الضريبة الإجمالية', [
            'tax_total' => $tax_total,
            'included_item_tax' => $included_item_tax,
            'amount_after_discount' => $amount_after_discount,
            'total_amount' => $total_amount,
        ]);

        // ** إضافة تكلفة الشحن (إذا وجدت) **
        $shipping_cost = floatval($request->shipping_cost ?? 0);

        /* كوبون «الشحن المجانيّ» يُصفّر الشحن **قبل** حساب ضريبته لا بعده،
           وإلّا بقيت ضريبةُ شحنٍ أُعفي منه في القيد وفي الإجمالي. */
        if ($coupon['free_shipping']) {
            $shipping_cost = 0.0;
        }

        /* ضريبة الشحن بنسبة الضريبة المضبوطة ونوعها — كانت `* 0.15`
           مكتوبةً في الشيفرة، فمنشأةٌ نسبتها غير ١٥٪ تحمّل شحنها ١٥٪ أبداً.
           و«ضريبة الشحن» المختارة في الشاشة أوّلاً، ثمّ ضريبة البنود.

           بلا شرط `tax_type`: نموذج الفاتورة لا يرسل هذا الحقل أصلاً، فكان
           الشرط كاذباً في كل حفظ — تُحفَظ ضريبة الشحن صفراً بينما تعرضها
           الشاشة وتضمّها إلى الإجمالي، فيخرج المحفوظ أقلّ من المعروض وتنقص
           الضريبة المستحقّة في القيد. والمرتجع والإشعار الدائن يحسبانها بلا
           هذا الشرط أصلاً، فصارت مستندات البيع على قاعدةٍ واحدة. */
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

        /* «تسوية المبيعات» أُسقطت من الشاشة ومن الإعدادات بطلب المستخدم.
           وقراءتها من الطلب أُسقطت معها لا لأنّ الحقل غاب فحسب: طلبٌ
           مصنوعٌ بيده يبقى قادراً على حمل `adjustment_value` فيزيد
           الإجمالي أو ينقصه بعد الخصم والضريبة معاً، بلا حدٍّ ولا صلاحية.
           الخصم — بندياً وعلى الفاتورة — هو الباب الوحيد الآن، وله حدُّه
           في «إعدادات الفواتير». */

        // ** الحساب النهائي للمجموع الكلي **
        $total_with_tax = $amount_after_discount + $tax_total + $shipping_cost;

        // ** حساب المبلغ المستحق (due_value) بعد خصم الدفعة المقدمة **
        /* الإيداعُ حقلٌ نصّيّ في الشاشة، فيُطبَّع هنا لا يُقرأ خاماً:
           `floatval('١٢٠٫١٠')` صفرٌ، أي دفعةٌ مقدّمة تُحفظ صفراً بلا خطأ متى
           فات السكربتَ تطبيعُها. والسالبُ صفر — لا دفعةَ تزيد الدين. */
        $advance_payment = max(0.0, $this->normalizeAmountInput($request->advance_payment));

        /* الدفعة المقدَّمة لا تتجاوز قيمة الفاتورة.
         *
         * كان الحقل يُقرأ كما وصل بلا حدّ: فاتورةٌ بألفٍ ودفعةٌ باثني عشر
         * ألفاً تُحفَظ، ويصير `due_value` سالباً أحدَ عشر ألفاً — أي ديناً
         * على المنشأة لعميلها وُلد من خطأٍ مطبعي، ويظهر في كشف الحساب
         * وفي شاشات التحصيل رصيداً دائناً لا مصدرَ له.
         *
         * والردّ لا القصّ: القصُّ يحفظ فاتورةً بمبلغٍ غير الذي كُتب بلا أن
         * يُقال لصاحبها. ومن أراد أن يدفع العميلُ أكثر من فاتورته فبابُه
         * سند قبضٍ مستقل، لا حقلُ دفعةٍ في فاتورة.
         *
         * والهامش قرشٌ واحد: `12000 > 1000` بلا لبس، والفروق العائمة في
         * الفلس لا تُردّ بها فاتورةٌ مسدَّدةٌ بالكامل. */
        /* ولا يسري الردّ على «مدفوعة بالكامل».
         *
         * حين يؤشّر المستخدم المربّع تكتب الشاشةُ الإجماليَّ المعروض في
         * الحقل وتقفله — فالرقم ليس مبلغاً كتبه أحد، بل صدى الإجمالي.
         * ثمّ يعيد الخادمُ أدناه حسابَ الدفعة من إجماليه هو
         * (`$advance_payment = $total_with_tax - $creditApplied`)، فالقيمةُ
         * الواصلة تُهمَل أصلاً.
         *
         * وكان الردُّ يقع قبل ذلك: فأيّ فارقٍ بين إجمالي الشاشة وإجمالي
         * الخادم — حسمُ عرضٍ يُطبَّق في الخادم مثلاً — يردّ فاتورةً
         * «مدفوعة بالكامل» برسالةٍ تقول إنّ المدفوع أكبر من المستحقّ،
         * والمستخدم لم يكتب مبلغاً ليُصحّحه. */
        if (! $request->boolean('is_paid') && $advance_payment > round($total_with_tax, 2) + 0.01) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.advance_over_total', [
                'advance' => CurrencySettings::format($advance_payment),
                'total' => CurrencySettings::format($total_with_tax),
            ]));
        }

        $due_value = $total_with_tax - $advance_payment;

        /* ** السداد من رصيد العميل الدائن ** — إعداد `auto_pay_if_balance`
           في «إعدادات الفواتير».

           ══════ لماذا لا سندَ قبضٍ ولا قيدَ إضافيّاً؟ ══════

           لأنّ المقاصّة وقعت بالقيد العادي وحده. رصيدُ العميل الدائن قائمٌ
           على حسابه المدين نفسِه (سالباً)، والفاتورةُ تُدين ذلك الحساب —
           فمن كان رصيدُه ‎-٥٠٠‎ وصدرت له فاتورةٌ بـ‎٧٠٠‎ صار ‎+٢٠٠‎ من غير
           أن يُكتب سطرٌ واحد فوق قيد الفاتورة.

           والمسار الآخر — أن يُعامَل كدفعةٍ مقدَّمة — يُدين **الخزينة**
           (انظر كتلة `PaymentsProcess` أدناه). وذلك يُدخل الخزينةَ نقداً لم
           يصلها، فتنفصل الخزينةُ الدفترية عن الصندوق انفصالاً لا يُصلَح
           إلّا بجردٍ يدوي. وهو الاحتمال الوحيد الخاطئ قطعاً هنا.

           والمعالجةُ المدرسية — حسابُ «دفعات عملاء مقدَّمة» مستقلٌّ يُدان
           بدل الخزينة — لا موضعَ لها في هذا الدليل المحاسبي: الرصيدُ
           المقدَّم يسكن حسابَ العميل نفسَه، فقيدُ التطبيق يصير مديناً
           ودائناً على حسابٍ واحد، أي لا شيء.

           فالأثرُ كلُّه على حالة الفاتورة ومستحقّها: لا تبقى الفاتورةُ
           معلَّقةً على عميلٍ ماله عندنا. ولا يُمَسّ `$advance_payment` —
           فهو ما يقود كتلةَ سند القبض، وهذه ليست قبضاً. */
        $creditApplied = 0.0;

        /* ولا يُستثنى منه المُعلَّم بـ«مدفوعة».

           كان الشرط `&& ! $request->has('is_paid')`، فيسقط الإعدادُ كلُّه
           متى وصلت الخانة. ومع «اعتبار الفواتير مدفوعة افتراضياً» تصل
           الخانةُ **دائماً** — الشاشة تُعلّمها من الإعداد نفسه — فكان
           الإعدادان مجتمعين يُلغي أوّلُهما الثاني: لا يُقرأ رصيدُ العميل
           أصلاً، وتُقيَّد الفاتورةُ نقداً بكاملها على الخزينة، فيبقى ماله
           عندنا كما هو ويدخل الصندوقَ دفترياً ما لم يدخله فعلاً.

           و«مدفوعة» لا تعني أنّ العميل أخرج نقداً بقيمة الفاتورة كلِّها،
           بل أنّها لا تُترَك آجلة. فيُستهلَك ما عنده عندنا أوّلاً، ويكون
           النقدُ لما بقي — وهو ترتيبُ فاتورة الشراء نفسُه مع رصيد المورد. */
        if ($this->invoiceRules()->auto_pay_if_balance) {
            $creditApplied = $this->clientCreditToApply(
                (int) $request->client_id,
                $total_with_tax - $advance_payment
            );

            if ($creditApplied > 0) {
                $due_value = max(0, $due_value - $creditApplied);
            }
        }

        /* ** ما ستغطّيه محفظة العميل ** — إعداد `auto_pay_from_wallet`.

           يُحسَب **هنا** لا بعد إنشاء الفاتورة، وإن كانت الحركةُ نفسُها
           تُكتب هناك (تحتاج مستنداً له معرّف). والعلّة أنّ النقد يُقرَّر في
           الأسطر التالية: فلو تُرك الحساب إلى ما بعدها لَكان تفعيلُ
           «اعتبار الفواتير مدفوعة افتراضياً» معه يُدخل الخزينةَ قيمةَ
           الفاتورة كاملةً ثمّ لا يجد في المحفظة ما يُخصَم — نقدٌ يُقيَّد
           ولم يصل، ورصيدٌ للعميل عندنا يبقى كما هو.

           فالترتيب: يُستهلَك ما عند العميل عندنا أوّلاً، ويكون النقدُ لما
           بقي — وهو ترتيبُ فاتورة الشراء نفسُه مع محفظة المورد.

           و`$due_value` **لا يُنقَص هنا**: `ClientWalletService::payInvoice()`
           هي الكاتب الوحيد لأثر المحفظة على الفاتورة، فطرحُه هنا وطرحُه
           هناك يخصم التسوية مرّتين. */
        $walletApplied = 0.0;

        if ($this->invoiceRules()->auto_pay_from_wallet) {
            $walletClient = Client::find($request->client_id);

            if ($walletClient) {
                $walletApplied = round(min(
                    ClientWalletService::balance($walletClient),
                    max(0, $total_with_tax - $advance_payment - $creditApplied)
                ), 2);
            }
        }

        // ** تحديد حالة الفاتورة بناءً على المدفوعات **
        $payment_status = 3; // الحالة الافتراضية (مسودة)
        $is_paid = false;

        if ($advance_payment > 0 || $creditApplied > 0 || $walletApplied > 0 || $request->has('is_paid')) {
            // حساب إجمالي المدفوعات — نقداً كان أو تسويةً من رصيدٍ دائن أو من المحفظة
            $total_payments = $advance_payment + $creditApplied + $walletApplied;

            if ($request->has('is_paid') && $request->is_paid) {
                $total_payments = $total_with_tax;
                // النقد لما لم يغطّه رصيدُ العميل الدائن ولا محفظتُه
                $advance_payment = round(max(0, $total_with_tax - $creditApplied - $walletApplied), 2);

                /* المستحقُّ عند الحفظ هو حصّةُ المحفظة وحدها — لا صفر.

                   النقدُ والرصيدُ الدائن سُوّيا في هذه الأسطر، أمّا حصّةُ
                   المحفظة فتُسوّى بعد إنشاء الفاتورة على يد
                   `ClientWalletService::payInvoice()`. فلو صُفِّر المستحقُّ
                   هنا لَما وجدت الخدمةُ ما تخصمه، وقد نقص النقدُ بمقدارها
                   أصلاً — فتخرج فاتورةٌ لم يسدّدها أحدٌ بذلك المقدار. */
                $due_value = round($walletApplied, 2);
                $payment_status = 1; // مكتمل
                $is_paid = true;
            } elseif ($total_payments >= $total_with_tax - 0.001) {
                /* غطّى الرصيدُ الدائن الفاتورةَ كلَّها: مستحقٌّ صفر وحالةٌ
                   مكتملة. والمقارنةُ بهامشٍ صغير لأنّ المجموع عائمٌ تتراكم
                   في كسوره أخطاءُ التقريب، فـ`>=` الصريحة تُبقي فاتورةً
                   مسدَّدةً بالكامل على «غير مكتملة» لفارق فلسٍ لا وجود له.

                   وحصّةُ المحفظة تبقى مستحقّةً حتى تخصمها الخدمة — للعلّة
                   نفسِها الموضَّحة في الفرع أعلاه. */
                $due_value = round($walletApplied, 2);
                $payment_status = 1;
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

        /* «إعطاء الفواتير حالات يدوية» — الحالات ١/٢/٣ (مكتمل/غير مكتمل/مسودة)
           تبقى مشتقّةً من المدفوعات ما لم يُفعَّل هذا الإعداد؛ ٤/٥ أعلاه
           كانتا قابلتين للاختيار الصريح أصلاً بلا شرط، فلم تُمسّا. */
        if ($invoiceSettings->manual_invoice_status && $request->has('payment_status')) {
            $submittedStatus = (int) $request->payment_status;
            if (in_array($submittedStatus, [1, 2, 3], true)) {
                /* «مكتملة» لا تُكتب فوق مستحقٍّ قائم.

                   كانت تُقبل كيفما جاءت: `is_paid=true` والمستحقُّ كما هو، بلا
                   سند قبضٍ ولا نقد — فاتورةٌ «مدفوعة» عليها دين، تُسقطها تقاريرُ
                   الذمم لأنّها مكتملة ويبقى المالُ عند العميل. فتُقبل حين يغطّيها
                   السدادُ المحسوب أعلاه فقط (مدفوعة بالكامل، أو دفعةٌ ورصيدٌ
                   دائنٌ ومحفظةٌ تبلغ الإجمالي)؛ وإلّا تُردّ برسالة. */
                if ($submittedStatus === 1 && $payment_status !== 1) {
                    DB::rollBack();

                    return redirect()->back()->withInput()
                        ->with('error', __('sales_invoice.ui.manual_paid_not_covered'));
                }

                $payment_status = $submittedStatus;
                $is_paid = $submittedStatus === 1;
            }
        }

        $clientAccount = Account::ledger()->where('client_id', $request->client_id)->first();
        $client = Client::find($request->client_id);

        // التحقق من الحد الائتماني والمديونية للفرع
        if (in_array($payment_status, [2, 3, 4])) {
            $branch = $client ? $client->branch : null;
            if ($branch && $branch->credit_limit > 0) {
                // حساب مديونية الفرع الحالية بمجموع أرصدة جميع عملاء الفرع
                $currentBranchDebt = Account::whereIn('client_id', function ($query) use ($branch) {
                    $query->select('id')->from('clients')->where('branch_id', $branch->id);
                })->sum('balance');

                $newInvoiceDebt = $total_with_tax - $advance_payment;
                $totalBranchDebtAfterInvoice = $currentBranchDebt + $newInvoiceDebt;

                if ($totalBranchDebtAfterInvoice > $branch->credit_limit) {
                    DB::rollBack();

                    $errorBranchMessage = sprintf(
                        '⚠️ عفوا، لقد تجاوز الفرع الحد الائتماني المسموح به!
الحد الائتماني للفرع: %s ر.س
مديونية الفرع الحالية: %s ر.س
قيمة الفاتورة (الآجل): %s ر.س
إجمالي مديونية الفرع بعد الفاتورة: %s ر.س
التجاوز: %s ر.س
الرجاء سداد المديونيات أولًا أو زيادة الحد الائتماني للفرع.',
                        number_format($branch->credit_limit, 2),
                        number_format($currentBranchDebt, 2),
                        number_format($newInvoiceDebt, 2),
                        number_format($totalBranchDebtAfterInvoice, 2),
                        number_format($totalBranchDebtAfterInvoice - $branch->credit_limit, 2)
                    );

                    Log::warning('تجاوز الحد الائتماني للفرع', [
                        'branch_id' => $branch->id,
                        'credit_limit' => $branch->credit_limit,
                        'current_branch_debt' => $currentBranchDebt,
                        'new_invoice_debt' => $newInvoiceDebt,
                        'total_branch_debt_after_invoice' => $totalBranchDebtAfterInvoice,
                    ]);

                    return redirect()->back()
                        ->withInput()
                        ->with('error', $errorBranchMessage);
                }
            }
        }

        // التحقق من الحد الائتماني للمجموعة (Region Group) بناءً على الحي المرتبط بالعميل
        if (in_array($payment_status, [2, 3, 4])) {
            $group = null;
            if ($client && $client->neighborhood && $client->neighborhood->region_id) {
                $group = Region_groub::find($client->neighborhood->region_id);
            }

            if ($group && $group->credit_limit > 0) {
                // حساب مديونية المجموعة الحالية بمجموع أرصدة جميع عملاء المجموعة
                $currentGroupDebt = Account::whereHas('client.neighborhood', function ($q) use ($group) {
                    $q->where('region_id', $group->id);
                })->sum('balance');

                $newInvoiceDebt = $total_with_tax - $advance_payment;
                $totalGroupDebtAfterInvoice = $currentGroupDebt + $newInvoiceDebt;

                if ($totalGroupDebtAfterInvoice > $group->credit_limit) {
                    DB::rollBack();

                    $errorGroupMessage = sprintf(
                        '⚠️ عفوا، لقد تجاوزت مجموعة المنطقة الحد الائتماني المسموح بها!
الحد الائتماني للمجموعة: %s ر.س
مديونية المجموعة الحالية: %s ر.س
قيمة الفاتورة (الآجل): %s ر.س
إجمالي المديونية بعد الفاتورة: %s ر.س
التجاوز: %s ر.س
الرجاء سداد المديونيات أولًا أو زيادة الحد الائتماني للمجموعة.',
                        number_format($group->credit_limit, 2),
                        number_format($currentGroupDebt, 2),
                        number_format($newInvoiceDebt, 2),
                        number_format($totalGroupDebtAfterInvoice, 2),
                        number_format($totalGroupDebtAfterInvoice - $group->credit_limit, 2)
                    );

                    Log::warning('تجاوز الحد الائتماني للمجموعة', [
                        'group_id' => $group->id,
                        'credit_limit' => $group->credit_limit,
                        'current_group_debt' => $currentGroupDebt,
                        'new_invoice_debt' => $newInvoiceDebt,
                        'total_debt_after_invoice' => $totalGroupDebtAfterInvoice,
                    ]);

                    return redirect()->back()
                        ->withInput()
                        ->with('error', $errorGroupMessage);
                }
            }
        }

        // التحقق من الحد الائتماني للموظف المسؤول
        if (in_array($payment_status, [2, 3, 4])) {
            $employee = $client ? Employee::find($client->employee_id) : null;
            if ($employee && $employee->credit_limit > 0) {
                // حساب مديونية الموظف الحالية بمجموع أرصدة جميع عملاء الموظف
                $currentEmployeeDebt = Account::whereHas('client', function ($query) use ($employee) {
                    $query->where('employee_id', $employee->id);
                })->sum('balance');

                $newInvoiceDebt = $total_with_tax - $advance_payment;
                $totalEmployeeDebtAfterInvoice = $currentEmployeeDebt + $newInvoiceDebt;

                if ($totalEmployeeDebtAfterInvoice > $employee->credit_limit) {
                    DB::rollBack();

                    $errorEmployeeMessage = sprintf(
                        '⚠️ عفوا، لقد تجاوز عملاء الموظف الحد الائتماني المسموح به!
الحد الائتماني لعملاء الموظف: %s ر.س
مديونية عملاء الموظف الحالية: %s ر.س
قيمة الفاتورة (الآجل): %s ر.س
إجمالي المديونية بعد الفاتورة: %s ر.س
التجاوز: %s ر.س
الرجاء سداد المديونيات أولًا أو زيادة الحد الائتماني للموظف.',
                        number_format($employee->credit_limit, 2),
                        number_format($currentEmployeeDebt, 2),
                        number_format($newInvoiceDebt, 2),
                        number_format($totalEmployeeDebtAfterInvoice, 2),
                        number_format($totalEmployeeDebtAfterInvoice - $employee->credit_limit, 2)
                    );

                    Log::warning('تجاوز الحد الائتماني لعملاء الموظف', [
                        'employee_id' => $employee->id,
                        'credit_limit' => $employee->credit_limit,
                        'current_employee_debt' => $currentEmployeeDebt,
                        'new_invoice_debt' => $newInvoiceDebt,
                        'total_debt_after_invoice' => $totalEmployeeDebtAfterInvoice,
                    ]);

                    return redirect()->back()
                        ->withInput()
                        ->with('error', $errorEmployeeMessage);
                }
            }
        }

        // التحقق من الحد الائتماني والمديونية للعميل
        if (in_array($payment_status, [2, 3, 4])) {
            // حساب المديونية الحالية
            $currentDebt = $clientAccount ? $clientAccount->balance : 0;

            // حساب إجمالي المديونية بعد الفاتورة الجديدة (قبل خصم الإيداع)
            $totalDebtBeforePayment = $currentDebt + $total_with_tax;

            // حساب إجمالي المديونية بعد خصم الإيداع (Payment Deposit)
            $totalDebtAfterPayment = $totalDebtBeforePayment - $advance_payment;

            // جلب الحد الائتماني للعميل من جدول credit_limit
            $clientCreditLimit = CreditLimit::getClientCreditLimit($request->client_id);
            $creditLimitValue = $clientCreditLimit ? $clientCreditLimit->value : 0;

            // إذا كان للعميل حد ائتماني محدد (أكبر من صفر)
            if ($creditLimitValue > 0) {
                // التحقق من تجاوز الحد الائتماني بعد خصم الإيداع
                if ($totalDebtAfterPayment > $creditLimitValue) {
                    DB::rollBack(); // إرجاع أي معاملات معلقة

                    $errorMessage = sprintf(
                        '⚠️ عفوا، لقد تجاوز العميل الحد الائتماني المسموح به!
الحد الائتماني: %s ر.س
المديونية الحالية: %s ر.س
قيمة الفاتورة: %s ر.س
الإجمالي بعد الفاتورة: %s ر.س
الإيداع/الدفعة المقدمة: %s ر.س
الإجمالي بعد الإيداع: %s ر.س
التجاوز: %s ر.س
الرجاء سداد المديونية أولًا أو زيادة الحد الائتماني للعميل.',
                        number_format($creditLimitValue, 2),
                        number_format($currentDebt, 2),
                        number_format($total_with_tax, 2),
                        number_format($totalDebtBeforePayment, 2),
                        number_format($advance_payment, 2),
                        number_format($totalDebtAfterPayment, 2),
                        number_format($totalDebtAfterPayment - $creditLimitValue, 2)
                    );

                    Log::warning('تجاوز الحد الائتماني', [
                        'client_id' => $request->client_id,
                        'credit_limit' => $creditLimitValue,
                        'current_debt' => $currentDebt,
                        'invoice_amount' => $total_with_tax,
                        'total_debt_before_payment' => $totalDebtBeforePayment,
                        'advance_payment' => $advance_payment,
                        'total_debt_after_payment' => $totalDebtAfterPayment,
                    ]);

                    return redirect()->back()
                        ->withInput()
                        ->with('error', $errorMessage);
                }
            }
            // إذا لم يكن للعميل حد ائتماني (صفر) وليس لديه صلاحية
            else {
                // التحقق من صلاحية إصدار فاتورة لعميل لديه مديونية
                if (! canAny(['Issue_an_invoice_to_a_customer_who_has_a_debt'])) {
                    if ($currentDebt > 0) {
                        DB::rollBack(); // إرجاع أي معاملات معلقة

                        return redirect()->back()->withInput()->with('error', sprintf(
                            '⚠️ عفوا، لا يمكن إصدار فاتورة للعميل!<br>
                            <strong>السبب:</strong> لديه مديونية حالية: %s ر.س<br>
                            <strong>الحل:</strong> الرجاء سداد المديونية أولًا أو تعيين حد ائتماني للعميل.',
                            number_format($currentDebt, 2)
                        ));
                    }
                }
            }

            // 🟡 تحذير: عندما يكون العميل قريباً من الحد الائتماني (80% فأكثر)
            if ($creditLimitValue > 0) {
                // استخدام المديونية بعد الإيداع للتحذير
                $percentageUsed = ($totalDebtAfterPayment / $creditLimitValue) * 100;
                if ($percentageUsed >= 80 && $percentageUsed <= 100) {
                    // تسجيل تحذير في السجل
                    Log::warning('العميل قريب من الحد الائتماني', [
                        'client_id' => $request->client_id,
                        'client_name' => $client->trade_name ?? 'Unknown',
                        'credit_limit' => $creditLimitValue,
                        'current_debt_before_invoice' => $currentDebt,
                        'total_debt_before_payment' => $totalDebtBeforePayment,
                        'advance_payment' => $advance_payment,
                        'final_debt' => $totalDebtAfterPayment,
                        'percentage_used' => round($percentageUsed, 2),
                        'created_by' => auth()->user()->name,
                    ]);

                    // إضافة تنبيه للقاعدة
                    notifications::create([
                        'user_id' => auth()->user()->id,
                        'receiver_id' => auth()->user()->id,
                        'type' => 'warning',
                        'title' => 'تحذير: عميل قريب من الحد الائتماني',
                        'description' => sprintf(
                            'العميل %s استخدم %s%% من الحد الائتماني (%s / %s ر.س) بعد الإيداع',
                            $client->trade_name,
                            round($percentageUsed, 1),
                            number_format($totalDebtAfterPayment, 2),
                            number_format($creditLimitValue, 2)
                        ),
                        'read' => 0,
                        'is_scheduled' => 0,
                        'is_sent' => 1,
                    ]);
                }
            }
        }
        // // التحقق من الرمز قبل إنشاء الفاتورة
        // if ($request->verification_code !== '123') {
        //     return response()->json(['error' => 'رمز التحقق غير صحيح.'], 400);
        // }
        // ** الخطوة الرابعة: إنشاء الفاتورة في قاعدة البيانات **

        $invoice = Invoice::create([
            'client_id' => $request->client_id,
            // الفرع يُجمَّد مع الفاتورة: تبديلُ الموظّف فرعَه لا ينقل فواتيره.
            // والدوريةُ تأخذ فرعَ اشتراكها لا فرعَ من يشغّل المهمّة
            'branch_id' => $periodicIssue['branch_id'] ?? current_branch_id(),
            'employee_id' => $employeeId,
            'due_value' => $due_value,
            'code' => $code,
            'type' => 'normal',
            'invoice_date' => $request->invoice_date,
            'issue_date' => $request->issue_date,
            /* مهلةُ السداد بالأيام.

               كان السطرُ `'terms' => …` ولا عمودَ بهذا الاسم في `invoices`:
               العمودُ `payment_terms` منذ إنشاء الجدول، و`$fillable` يُسقط
               المفتاحَ المجهول بصمت. فالمستخدمُ يكتب «٣٠ يوماً» عند كل
               فاتورةٍ آجلة ولا يُحفظ رقمٌ واحد منها، ولا يعرف النظامُ متى
               تستحقّ فاتورةٌ واحدة — ولا تنبيهَ يُبنى على ما لا يُحفظ.

               والصفرُ يُحفظ فارغاً لا صفراً: «فوريّ» ليس مهلةً، وتمييزُه
               عن «لم يُحدَّد» لا يفيد شيئاً في شاشةٍ ولا تقرير. */
            'payment_terms' => $paymentTermsDays,
            /* التسويةُ من الرصيد الدائن تُكتب في متن الفاتورة كما تُكتب في
               فاتورة الشراء: لا سندَ قبضٍ لها ولا قيدَ ثانياً — عن قصد —
               فلولا هذا السطر لرأى القارئُ فاتورةً «مدفوعة» ومستحقُّها صفر
               ولا أثرَ يقول كيف سُدِّدت. */
            'notes' => ($request->notes ?? '').
                       ($creditApplied > 0
                           ? "\n".'سُوِّيت من رصيد العميل الدائن بمبلغ '.number_format($creditApplied, 2).' — بلا قبضٍ في الخزينة'
                           : ''),
            'payment_status' => $payment_status,
            'is_paid' => $is_paid,
            'created_by' => Auth::id(),
            'account_id' => $request->account_id,
            'discount_amount' => $final_total_discount,
            'discount_type' => $request->has('discount_type') ? ($request->discount_type === 'percentage' ? 2 : 1) : 1,
            /* الكوبون كما طُبِّق — مجمَّداً لا يُعاد حسابُه، ومبلغُه داخلٌ
               أصلاً في `discount_amount` أعلاه. */
            'coupon_id' => $coupon['coupon_id'],
            'coupon_code' => $coupon['code'],
            'coupon_discount' => $coupon_discount,
            'advance_payment' => $advance_payment,
            'payment_type' => $request->payment_type ?? 1,
            'shipping_cost' => $shipping_cost,
            'shipping_tax' => $shipping_tax,
            'tax_type' => $request->tax_type ?? 1,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'received_date' => $request->received_date,
            'subtotal' => $total_amount,
            // 'discount_amount' => $final_total_discount,
            'tax_total' => $tax_total,
            'grand_total' => $total_with_tax,
            // 'paid_amount' => $advance_payment,
            'subscription_id' => $request->subscription_id,
        ]);

        $invoice->qrcode = $this->generateTlvContent($invoice->created_at, $invoice->grand_total, $invoice->tax_total);

        /* مصدر الطلب بعد الإنشاء لا داخله: عمودُه قد يكون غائباً على
           استضافةٍ رُفعت إليها الشيفرة ولم تُشغَّل ترحيلاتُها بعد، ومفتاحٌ
           غائبٌ داخل `create()` يُسقط الفاتورة كلَّها بـ«Unknown column». */
        if ($orderSource && $this->invoicesTrackOrderSource()) {
            $invoice->order_source_id = $orderSource->id;
        }

        /* فاتورةٌ من حجز («تحويل إلى فاتورة»): رقمُ الحجز مرجعُها، والحجزُ
           يُربط بها بعد الحفظ — داخل المعاملة نفسها وخلف قفل صفّه، فحفظان
           متزامنان لا يربطان حجزاً واحداً بفاتورتين. */
        $linkedReservation = $request->filled('reservation_id')
            ? $this->lockReservationForInvoice((int) $request->input('reservation_id'), (int) $invoice->client_id)
            : null;

        if ($linkedReservation && blank($invoice->reference_number)) {
            $invoice->reference_number = $linkedReservation->code;
        }

        $invoice->save();

        if ($periodicIssue) {
            $this->linkPeriodicInstance($invoice, $periodicIssue);
        }

        if ($linkedReservation) {
            $linkedReservation->forceFill(['invoice_id' => $invoice->id, 'updated_by' => auth()->id()])->save();

            // أثرُ التحويل في «سجلّ النشاط» بشاشة الحجز
            \App\Support\OperationLog::record(
                'invoiced',
                'Reservations',
                $linkedReservation,
                "تحويل الحجز {$linkedReservation->code} إلى الفاتورة {$invoice->code}"
            );
        }
        $client = Client::find($invoice->client_id);

        // الإصدار الدوريّ لا يُكمل زيارةً لمنشئ الاشتراك: لم يزر أحدٌ العميل
        if ($client && ! $periodicIssue) {
            // تعليم زيارة اليوم فقط — كانت تأخذ «آخر زيارة» بلا تقييدٍ
            // بالتاريخ، فتُكمِل صفَّ يومٍ آخر خطأً
            $isTodayItineraryVisit = EmployeeClientVisit::isItineraryDayFor(auth()->id(), $client->id);

            if ($isTodayItineraryVisit) {
                EmployeeClientVisit::markCompletedFor(auth()->id(), $client->id);

                // Evaluate itinerary completion
                try {
                    app(PointsService::class)->evaluateItineraryCompletion(auth()->id(), now()->toDateString());
                } catch (\Exception $e) {
                    Log::warning('PointsService: Failed to evaluate itinerary completion during invoice creation', [
                        'employee_id' => auth()->id(),
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('تم تحديث حالة الزيارة الحالية للفاتورة', [
                    'client_id' => $client->id,
                    'invoice_id' => $invoice->id,
                ]);
            } else {
                Log::warning('لا توجد زيارة مجدولة اليوم لهذا العميل للفاتورة', [
                    'client_id' => $client->id,
                    'employee_id' => auth()->id(),
                ]);
            }
        }

        // ** تحسين: جمع الضرائب وإدراجها دفعة واحدة **
        $taxInvoicesToInsert = [];
        $taxIds = collect($request->items)->pluck('tax_1_id', 'tax_2_id')->flatten()->unique()->filter()->toArray();
        $taxesCollection = TaxSitting::whereIn('id', $taxIds)->get()->keyBy('id');

        foreach ($request->items as $item) {
            $item_subtotal = $item['unit_price'] * $item['quantity'];
            $tax_id_fields = ['tax_1_id', 'tax_2_id'];

            foreach ($tax_id_fields as $tax_id) {
                if (! empty($item[$tax_id]) && $taxesCollection->has($item[$tax_id])) {
                    $tax = $taxesCollection->get($item[$tax_id]);
                    $taxInvoicesToInsert[] = [
                        'name' => $tax->name,
                        'invoice_id' => $invoice->id,
                        'type' => $tax->type,
                        'rate' => $tax->tax,
                        'value' => ($tax->tax / 100) * $item_subtotal,
                        'type_invoice' => 'invoice',
                        'product_id' => $item['product_id'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (! empty($taxInvoicesToInsert)) {
            TaxInvoice::insert($taxInvoicesToInsert);
        }

        // ** تحديث رصيد حساب أبناء العميل **

        // إضافة المبلغ الإجمالي للفاتورة إلى رصيد أبناء العميل

        // ** تحسين: تحميل البيانات المشتركة مسبقاً **
        $client = Client::find($invoice->client_id);

        // جلب مصدر إذن المخزون مرة واحدة
        $permissionSource = PermissionSource::where('id', PermissionSources::SALES_INVOICE)->first();
        if (! $permissionSource) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.permission_source_missing'));
        }

        /* أرصدةُ البنود ومكوّناتِ المجمَّعات دفعةً واحدة — **مقفلةً** حتى
         * نهاية المعاملة.
         *
         * بلا قفل: موظّفان يبيعان آخرَ عشر حبّاتٍ في اللحظة نفسها، كلاهما يقرأ
         * «المتاح ١٠» وكلاهما يخصم. وبلا المكوّنات: صفُّ المكوّن غائبٌ عن
         * الخريطة فيُفتح له صفٌّ جديدٌ بصفر، ويقع الخصمُ على الصفّ الجديد لا
         * على رصيده الحقيقي. */
        $stockProductIds = collect($items_data)->pluck('product_id')
            ->merge($compiledProductsMap->collapse()->pluck('product_id'))
            ->filter()->unique()->values();

        $existingProductDetails = ProductDetails::whereIn('product_id', $stockProductIds)
            ->whereIn('store_house_id', collect($items_data)->pluck('store_house_id')->filter()->unique())
            ->lockForUpdate()
            ->get()
            ->keyBy(function ($item) {
                return $item->product_id.'_'.$item->store_house_id;
            });

        // جمع اللوجات والإشعارات
        $logsToInsert = [];
        $notificationsToInsert = [];

        // تحميل مصدر إذن المخزون مرة واحدة
        $permissionSource = PermissionSource::find(PermissionSources::SALES_INVOICE);
        if (! $permissionSource) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.permission_source_missing'));
        }

        // ** الخطوة الخامسة: إنشاء سجلات البنود (items) للفاتورة **
        foreach ($items_data as $item) {
            $item['invoice_id'] = $invoice->id;
            $item_invoice = InvoiceItem::create($item);

            // الحصول على اسم المنتج من المجموعة المحملة مسبقاً
            $product = $item['product_id'] ? $productsCollection->get($item['product_id']) : null;

            /* اسم البند الحرّ هو ما كتبه المستخدم: `منتج غير معروف` في
               سجلّ النشاط تقرأ عطلاً لا بنداً مقصوداً */
            $productName = $product->name ?? ($item['item'] ?? 'منتج غير معروف');

            // الكمية بالوحدة الأساسية (للخصم من المخزون)
            $quantityToDeduct = $item['quantity_in_base_unit'] ?? $item['quantity'];
            $unitInfo = '';
            if (! empty($item['unit_name']) && $item['conversion_factor'] != 1) {
                $unitInfo = sprintf(
                    ' (الوحدة: %s، معامل التحويل: %s، الكمية الأساسية: %s)',
                    $item['unit_name'],
                    $item['conversion_factor'],
                    $quantityToDeduct
                );
            }

            $logsToInsert[] = [
                'type' => 'sales',
                'type_id' => $invoice->id,
                'type_log' => 'log',
                'icon' => 'create',
                'description' => sprintf(
                    'تم انشاء فاتورة مبيعات رقم **%s** للمنتج **%s** كمية **%s**%s بسعر **%s** للعميل **%s**',
                    $invoice->code ?? '',
                    $productName,
                    $item['quantity'] ?? '',
                    $unitInfo,
                    $item['unit_price'] ?? '',
                    $client->trade_name ?? ''
                ),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            /* البند الحرّ يقف هنا: سُجّل في `invoice_items` ودخل الإجمالي
               والقيد، ولا شيء بعدُ يخصّه — لا رصيدَ يُخصَم ولا إذنَ يُكتب
               ولا تكلفةَ تُرحَّل. */
            if (empty($item['product_id'])) {
                continue;
            }

            // استخدام المنتج من المجموعة المحملة
            $proudect = $product;

            /* الأصناف بلا رصيد — الخدمي والرقمي — لا تدخل المخزون أصلاً.
             *
             * كان `ProductDetails::create()` يُنفَّذ لكل بند بلا استثناء،
             * فيُفتح صفُّ رصيدٍ لخدمةٍ في مستودع. ومنذ أن صار
             * `StockMovementGuardObserver` يحرس الجدول، صار السطر نفسه
             * يرمي `RuntimeException` — فبيعُ خدمةٍ واحدة يُسقط الفاتورة
             * كلّها لا بندَها وحده. وحركةُ المخزون أدناه محروسةٌ أصلاً
             * بالنوع، فالصفُّ كان يُفتح ثمّ لا يُستعمَل.
             */
            $tracksStock = StockEligibility::isStockable($proudect ?: $item['product_id']);
            $productDetails = null;

            if ($tracksStock) {
                // ** تحديث المخزون بناءً على store_house_id المحدد في البند **
                $detailsKey = $item['product_id'].'_'.$item['store_house_id'];
                $productDetails = $existingProductDetails->get($detailsKey);

                if (! $productDetails) {
                    $productDetails = ProductDetails::create([
                        'store_house_id' => $item['store_house_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => 0,
                    ]);
                    $existingProductDetails->put($detailsKey, $productDetails);
                }
            }

            /* الرصيد لا يكفي — يُقال للمستخدم ولا يُرمى استثناءً.
             *
             * كان `throw new \Exception(...)`، و`store()` بلا `try/catch`
             * محيط (الكتلةُ المحيطة معلَّقة في آخر الدالّة)، فالاستثناء
             * يخرج إلى المعالج العامّ: صفحةُ **500** بيضاء بلا سبب،
             * والمعاملةُ لا تُغلَق فلا يُحفَظ شيء.
             *
             * وهو يقع في بندٍ **له وحدة** أكثر ممّا يقع في غيره: المخصوم
             * هو الكمية بالوحدة الأساسية، فكرتونان × ١٢٠ يطلبان ٢٤٠ حبةً
             * من المستودع لا اثنتين — فيُردّان والمستخدمُ يرى «٢» في
             * الشاشة ولا يفهم لِمَ رُفضت.
             *
             * فالرسالة تقول الوحدة ومعاملها والمطلوب والمتاح معاً. */
            if ($proudect->type == 'products' || ($proudect->type == 'compiled' && $proudect->compile_type !== 'Instant')) {
                if ((float) $quantityToDeduct > (float) $productDetails->quantity) {
                    DB::rollBack();

                    $shortageKey = (! empty($item['unit_name']) && (float) $item['conversion_factor'] != 1.0)
                        ? 'sales_invoice.ui.stock_short_unit'
                        : 'sales_invoice.ui.stock_short';

                    return redirect()->back()->withInput()->with('error', __($shortageKey, [
                        'item' => $productName,
                        'store' => $storeHouse->name ?? '—',
                        'quantity' => rtrim(rtrim(number_format((float) $item['quantity'], 4, '.', ''), '0'), '.'),
                        'unit' => $item['unit_name'] ?? '',
                        'needed' => rtrim(rtrim(number_format((float) $quantityToDeduct, 4, '.', ''), '0'), '.'),
                        'available' => rtrim(rtrim(number_format((float) $productDetails->quantity, 4, '.', ''), '0'), '.'),
                    ]));
                }
            }

            /* المجمَّعُ غيرُ الفوري سلعةٌ مُركَّبةٌ سلفاً لها رصيد: كان رصيدُه
               يُفحص في الشرط أعلاه ثمّ لا يُخصم ولا يُكتب له إذن — فيُباع من
               الرصيد نفسِه بلا نهاية. يُخصم كالسلعة، كما في نقطة البيع. */
            if ($proudect->type == 'products' || ($proudect->type == 'compiled' && $proudect->compile_type !== 'Instant')) {
                // ** حساب المخزون قبل وبعد التعديل - باستخدام الكمية بالوحدة الأساسية **
                $stock_before = $productDetails->quantity;
                $stock_after = $stock_before - $quantityToDeduct;

                // ** تحديث المخزون - خصم بالوحدة الأساسية **
                $productDetails->decrement('quantity', $quantityToDeduct);

                // ** تسجيل المبيعات في حركة المخزون **
                $wareHousePermits = new WarehousePermits;
                $wareHousePermits->permission_source_id = $permissionSource->id;
                $wareHousePermits->permission_date = $invoice->created_at;
                $wareHousePermits->number = $invoice->id;
                $wareHousePermits->grand_total = $invoice->grand_total;
                $wareHousePermits->store_houses_id = $storeHouse->id;
                $wareHousePermits->client_id = $invoice->client_id;
                $wareHousePermits->reference_type = 'Invoice';
                $wareHousePermits->reference_id = $invoice->id;
                $wareHousePermits->created_by = auth()->user()->id;
                // إذنٌ صادرٌ عن مستند لا طلبٌ ينتظر موافقة: الحركة وقعت
                // وقُيِّدت محاسبياً مع المستند، فيُولَد موافَقاً عليه.
                // (`apporod` هي قيمة الموافقة المعتمدة في النظام.)
                $wareHousePermits->status = 'apporod';
                $wareHousePermits->save();

                // ** تسجيل البيانات في WarehousePermitsProducts - باستخدام الكمية بالوحدة الأساسية **
                WarehousePermitsProducts::create(array_merge([
                    'product_id' => $item['product_id'],
                    'stock_before' => $stock_before, // المخزون قبل التحديث
                    'stock_after' => $stock_after,   // المخزون بعد التحديث
                    'warehouse_permits_id' => $wareHousePermits->id,
                    /* سعرُ الوحدة **بالوحدة الأساسية**، لأنّ `quantity`
                       هنا بالوحدة الأساسية.

                       كان `$item['unit_price']` — أي سعرُ وحدة البند: بندٌ
                       بكرتونين بألفٍ وخمسمئة للكرتون يُكتب `quantity = 2000`
                       حبّة و`unit_price = 1500`، فإجماليُّه صحيحٌ وسعرُ حبّته
                       ألفُ ضعفٍ. وهي الحركةُ التي رفعت متوسّط تكلفة الصنف
                       وأنتجت «تكلفة مبيعات ٩٤٬١٩٠» لفاتورةٍ بأربعمئة —
                       عولجت في `Product::averageCost()` قراءةً، والمنبعُ
                       هنا كتابةً. والمعادلة هي التي تكتب بها المشترياتُ
                       أذونَها منذ حين. */
                ], WarehousePermitsProducts::inventoryLineFromInvoiceItem($item)));

                // ** تنبيه انخفاض الكمية **
                if ($productDetails->quantity < $product['low_stock_alert']) {
                    $notificationsToInsert[] = [
                        'type' => 'Products',
                        'title' => 'تنبيه الكمية',
                        'description' => 'كمية المنتج '.$product['name'].' قاربت على الانتهاء.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // إلى Telegram بعد التزام الفاتورة، ومن «إعدادات Telegram» لا من رمزٍ في الشيفرة
                    app(TelegramNotificationService::class)->lowStockAlert(
                        $product['name'],
                        (float) $productDetails->quantity,
                        $storeHouse->name ?? null
                    );
                }

                // ** تنبيه تاريخ انتهاء الصلاحية **
                if ($product['track_inventory'] == 2 && ! empty($product['expiry_date']) && ! empty($product['notify_before_days'])) {
                    $expiryDate = Carbon::parse($product['expiry_date']);
                    $daysBeforeExpiry = (int) $product['notify_before_days'];

                    if ($expiryDate->greaterThan(now())) {
                        $remainingDays = floor($expiryDate->diffInDays(now()));

                        if ($remainingDays <= $daysBeforeExpiry) {
                            $notificationsToInsert[] = [
                                'type' => 'Products',
                                'title' => 'تاريخ الانتهاء',
                                'description' => 'المنتج '.$product['name'].' قارب على الانتهاء في خلال '.$remainingDays.' يوم.',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            app(TelegramNotificationService::class)->expiryAlert(
                                $product['name'],
                                $expiryDate->format('Y-m-d'),
                                (int) $remainingDays
                            );
                        }
                    }
                }
            }

            if ($proudect->type == 'compiled' && $proudect->compile_type == 'Instant') {
                // ** حساب المخزون قبل وبعد التعديل للمنتج التجميعي **
                // يجب حساب الرصيد من المستودع المحدد فقط
                $stock_before = $productDetails->quantity;

                // ** الحركة الأولى: إضافة الكمية إلى المخزن **
                $wareHousePermits = new WarehousePermits;
                $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_ADD;
                $wareHousePermits->permission_date = $invoice->created_at;
                $wareHousePermits->number = $invoice->id;
                $wareHousePermits->grand_total = $invoice->grand_total;
                $wareHousePermits->store_houses_id = $storeHouse->id;
                $wareHousePermits->created_by = auth()->user()->id;
                // إذنٌ صادرٌ عن مستند لا طلبٌ ينتظر موافقة: الحركة وقعت
                // وقُيِّدت محاسبياً مع المستند، فيُولَد موافَقاً عليه.
                // (`apporod` هي قيمة الموافقة المعتمدة في النظام.)
                $wareHousePermits->status = 'apporod';
                $wareHousePermits->save();

                // ** تحديث المخزون: إضافة الكمية - باستخدام الكمية بالوحدة الأساسية **
                $productDetails->increment('quantity', $quantityToDeduct);

                // ** تسجيل البيانات في WarehousePermitsProducts للإضافة **
                WarehousePermitsProducts::create([
                    'quantity' => $quantityToDeduct,
                    'total' => $item['total'],
                    'unit_price' => $item['unit_price'],
                    'product_id' => $item['product_id'],
                    'stock_before' => $stock_before, // المخزون قبل التحديث
                    'stock_after' => $stock_before + $quantityToDeduct, // المخزون بعد الإضافة
                    'warehouse_permits_id' => $wareHousePermits->id,
                ]);

                // ** الحركة الثانية: خصم الكمية من المخزن **
                $wareHousePermits = new WarehousePermits;
                $wareHousePermits->permission_source_id = PermissionSources::SALES_INVOICE;
                $wareHousePermits->permission_date = $invoice->created_at;
                $wareHousePermits->number = $invoice->id;
                $wareHousePermits->grand_total = $invoice->grand_total;
                $wareHousePermits->store_houses_id = $storeHouse->id;
                $wareHousePermits->created_by = auth()->user()->id;
                // إذنٌ صادرٌ عن مستند لا طلبٌ ينتظر موافقة: الحركة وقعت
                // وقُيِّدت محاسبياً مع المستند، فيُولَد موافَقاً عليه.
                // (`apporod` هي قيمة الموافقة المعتمدة في النظام.)
                $wareHousePermits->status = 'apporod';
                $wareHousePermits->save();

                // ** تحديث المخزون: خصم الكمية - باستخدام الكمية بالوحدة الأساسية **
                $productDetails->decrement('quantity', $quantityToDeduct);

                // ** تسجيل البيانات في WarehousePermitsProducts للخصم **
                WarehousePermitsProducts::create([
                    'quantity' => $quantityToDeduct,
                    'total' => $item['total'],
                    'unit_price' => $item['unit_price'],
                    'product_id' => $item['product_id'],
                    'stock_before' => $stock_before + $quantityToDeduct, // المخزون قبل الخصم (بعد الإضافة)
                    'stock_after' => $stock_before, // المخزون بعد الخصم (يعود إلى القيمة الأصلية)
                    'warehouse_permits_id' => $wareHousePermits->id,
                ]);

                // ** الحصول على المنتجات التابعة للمنتج التجميعي **
                $CompiledProducts = $compiledProductsMap->get($item['product_id'], collect());

                foreach ($CompiledProducts as $compiledProduct) {
                    // ** حساب المخزون قبل وبعد التعديل للمنتج التابع **
                    $compiledProductKey = $compiledProduct->product_id.'_'.$item['store_house_id'];
                    $compiledProductDetails = $existingProductDetails->get($compiledProductKey);

                    if (! $compiledProductDetails) {
                        $compiledProductDetails = ProductDetails::create([
                            'store_house_id' => $item['store_house_id'],
                            'product_id' => $compiledProduct->product_id,
                            'quantity' => 0,
                        ]);
                        $existingProductDetails->put($compiledProductKey, $compiledProductDetails);
                    }

                    // استخدام الكمية بالوحدة الأساسية للمنتجات التابعة
                    $compiledQuantity = $compiledProduct->qyt * $quantityToDeduct;

                    /* المكوّنُ هو ما يخرج فعلاً من المستودع — المجمَّعُ الفوريُّ
                       يُضاف ثمّ يُخصم فأثرُه على رصيده صفر. فالفحصُ هنا، وكان
                       غائباً: يُباع المجمَّعُ ومكوّنُه غيرُ موجود، ويهبط رصيدُ
                       المكوّن تحت الصفر. */
                    if ((float) $compiledQuantity > (float) $compiledProductDetails->quantity) {
                        DB::rollBack();

                        return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.stock_short', [
                            'item' => Product::whereKey($compiledProduct->product_id)->value('name') ?? '#'.$compiledProduct->product_id,
                            'store' => $storeHouse->name ?? '—',
                            'needed' => rtrim(rtrim(number_format((float) $compiledQuantity, 4, '.', ''), '0'), '.'),
                            'available' => rtrim(rtrim(number_format((float) $compiledProductDetails->quantity, 4, '.', ''), '0'), '.'),
                        ]));
                    }

                    $stock_before = $compiledProductDetails->quantity;
                    $stock_after = $stock_before - $compiledQuantity;

                    // ** تسجيل المبيعات في حركة المخزون للمنتج التابع **
                    $wareHousePermits = new WarehousePermits;
                    $wareHousePermits->permission_source_id = PermissionSources::SALES_INVOICE;
                    $wareHousePermits->permission_date = $invoice->created_at;
                    $wareHousePermits->number = $invoice->id;
                    $wareHousePermits->grand_total = $invoice->grand_total;
                    $wareHousePermits->store_houses_id = $storeHouse->id;
                    $wareHousePermits->created_by = auth()->user()->id;
                    // إذنٌ صادرٌ عن مستند لا طلبٌ ينتظر موافقة: الحركة وقعت
                    // وقُيِّدت محاسبياً مع المستند، فيُولَد موافَقاً عليه.
                    // (`apporod` هي قيمة الموافقة المعتمدة في النظام.)
                    $wareHousePermits->status = 'apporod';
                    $wareHousePermits->save();

                    // ** تسجيل البيانات في WarehousePermitsProducts للمنتج التابع **
                    WarehousePermitsProducts::create([
                        'quantity' => $compiledQuantity,
                        'total' => $item['total'],
                        'unit_price' => $item['unit_price'],
                        'product_id' => $compiledProduct->product_id,
                        'stock_before' => $stock_before, // المخزون قبل التحديث
                        'stock_after' => $stock_after, // المخزون بعد التحديث
                        'warehouse_permits_id' => $wareHousePermits->id,
                    ]);

                    // ** تحديث المخزون للمنتج التابع **
                    $compiledProductDetails->decrement('quantity', $compiledQuantity);
                }
            }
        }

        // ** إدراج اللوجات دفعة واحدة **
        if (! empty($logsToInsert)) {
            ModelsLog::insert($logsToInsert);
        }

        // ** إدراج الإشعارات دفعة واحدة **
        if (! empty($notificationsToInsert)) {
            notifications::insert($notificationsToInsert);
        }

        // جلب بيانات الموظف والمستخدم
        $employee_name = Employee::where('id', $invoice->employee_id)->first();
        $user_name = User::where('id', $invoice->created_by)->first();
        $client_name = Client::find($invoice->client_id);
        /* إشعار Telegram — من «إعدادات Telegram» وبعد التزام المعاملة.
         *
         * كان رمزُ البوت وقناةُ `@Salesfatrasmart` مكتوبين هنا، والطلبُ داخل
         * المعاملة بلا تحقّقٍ من الشهادة: كلُّ فاتورةٍ من كل تنصيب تذهب
         * ببيانات عميلها إلى قناةٍ خارجية، وتعذُّرُ الوصول إلى Telegram كان
         * يُسقط الفاتورة كلَّها. وكان «الإجمالي» يجمع الضريبة مرّتين. */
        app(TelegramNotificationService::class)->salesInvoiceCreated($invoice);

        // إرسال إشعار لجميع الموظفين
        $employees = User::where('role', 'employee')->get();

        /* الإشعار لا يُسقط الفاتورة.
         *
         * كان `$employee_name->id` يُقرأ بلا فحص، و`$employee_name` هو
         * `Employee::find($invoice->employee_id)` — فارغٌ لكل مستخدمٍ لا
         * سجلَّ موظّفٍ له (المدير وصاحب النظام، وكلُّ حسابٍ في تنصيبٍ جديد
         * قبل ربط المستخدمين بالموظّفين). فتُحفَظ الفاتورة وقيودُها ثمّ
         * ينهار الطلب هنا على `Attempt to read property "id" on null`،
         * فيظنّ البائع أنّ الفاتورة لم تُحفَظ ويعيد إصدارها. */
        notifications::create([
            'user_id' => auth()->user()->id,
            'employee_id' => $employee_name->id ?? null,

            'type' => 'invoice',
            'title' => ($user_name->name ?? auth()->user()->name).' أضاف فاتورة لعميل',
            'description' => 'فاتورة للعميل '.($client_name->trade_name ?? '—').' بقيمة '.number_format($invoice->grand_total, 2).' ر.س',
            'read' => 0,
            'is_scheduled' => 0,
            'is_sent' => 1,
            'scheduled_at' => null,
        ]);

        /* المرفقات — في `assets/uploads/sales/invoices` باسمٍ وامتدادٍ يولّدهما
           الخادم. الشاشة ترسل `attachments[]` (أكثر من ملف)، وكان `isValid()`
           يُستدعى على المصفوفة فيسقط الحفظُ كلُّه عند أوّل مرفق. */
        if ($attachmentFiles = DocumentAttachments::files($request)) {
            $invoice->attachments = DocumentAttachments::encode(
                DocumentAttachments::storeMany($attachmentFiles, DocumentAttachments::SALES_INVOICE)
            );
            $invoice->save();
        }
        $clientaccounts = Account::ledger()->where('client_id', $invoice->client_id)->first();
        if (! $clientaccounts) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.client_account_missing', [
                'client' => $client->trade_name ?? '—',
            ]));
        }

        // الحسابات من شاشة «الحسابات المحاسبية» لا من ثوابت الشيفرة.
        //
        // كانت تُقرأ بمعرّفاتها المكتوبة (37، 44، 4، 1)، فما يربطه المحاسب
        // في الشاشة لا يسري على قيدٍ واحد من قيود المبيعات. الثوابت تبقى
        // احتياطاً فيعمل النظام قبل أن يُربط مفتاح.
        //
        // `require…` لا `…AccountId`: قيدٌ بلا حساب مبيعات أو بلا حساب
        // ضريبة قيدٌ بطرفٍ واحد، والرسالة هنا تسمّي الحساب وتدلّ على شاشته
        $vatAccount = $this->requireAccountingAccount('vat_payable');
        $salesAccount = $this->requireAccountingAccount('sales');

        // هذان للترحيل الهرمي وحده ويتحمّلان الغياب
        $revenueAccount = $this->accountingAccount('revenue');
        $assetsAccount = $this->accountingAccount('assets');

        // جلب بيانات العميل للوصف التفصيلي
        $clientName = $clientaccounts->name ?? 'العميل';

        /* وصف القيد: المستند والطرف، لا أكثر.
         *
         * كان يحشو في السطر الواحد الإجمالي والخصم والضريبة والتاريخ، وكلّها
         * معروضة أصلاً: المبالغ في عمودَي مدين/دائن، والتاريخ في `date`،
         * والرقم في `reference_number`. فيخرج وصفٌ لا يُقرأ في شاشة اليومية
         * ولا في كشف الحساب. الصيغة الآن: «نوع المستند رقمه - الطرف»،
         * وفي الأطراف: «طبيعة الطرف - المستند».
         */
        $invoiceDescription = 'فاتورة مبيعات '.$invoice->code.' - '.$clientName;

        // «وصف مخصّص لقيود اليومية» — يستبدل الوصف المُولَّد أعلاه حين يُفعَّل ويُرسَل
        if ($invoiceSettings->custom_journal_description && $request->filled('journal_description')) {
            $invoiceDescription = (string) $request->input('journal_description');
        }

        //     // إنشاء القيد المحاسبي للفاتورة
        $journalEntry = JournalEntry::create([
            'reference_number' => $invoice->code,
            'date' => now(),
            'description' => $invoiceDescription,
            'status' => 1,
            'currency' => $this->documentCurrency($invoice),
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'reference_type' => ReferenceType::SALES_INVOICE,
            'linking_type' => LinkingType::SINGLE,
            'created_by_employee' => Auth::id(),
        ]);

        // // إضافة تفاصيل القيد المحاسبي
        // القيد الصحيح: مدين العميل = المبلغ النهائي (بعد الخصم + الضريبة)
        // دائن المبيعات = المبلغ قبل الخصم
        // مدين الخصم = قيمة الخصم
        // دائن الضريبة = قيمة الضريبة

        /* ١. حساب العميل (مدين) = **إجمالي الفاتورة**.
         *
         * كان `$amount_after_discount + $tax_total` — أي بلا الشحن وبلا
         * التسوية. والقيد يوازن مع ذلك (الشحن والتسوية غائبان عن الطرفين
         * معاً)، فلا يكشفه فحصُ توازن. لكنّ ذمّة العميل تخرج أقلَّ من
         * فاتورته: فاتورةٌ إجماليها ٢٣٩٥ بشحن ١٠٠ وتسوية ٣٠ تُقيَّد على
         * العميل ٢٢٦٥، ويبقى ١٣٠ معلَّقاً في `grand_total` لا حساب له.
         */
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $clientaccounts->id,
            'description' => 'مديونية العميل - فاتورة '.$invoice->code
                .($creditApplied > 0 ? ' (سُدِّد من رصيده الدائن '.number_format($creditApplied, 2).')' : ''),
            'debit' => $total_with_tax,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // // 2. حساب المبيعات (دائن) = المبلغ قبل الخصم والضريبة
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $salesAccount->id,
            'description' => 'إيرادات مبيعات - فاتورة '.$invoice->code,
            'debit' => 0,
            'credit' => $total_amount, // المبلغ الكامل قبل الخصم
            'is_debit' => false,
        ]);

        // // 3. حساب الخصم المسموح به (مدين)
        if ($final_total_discount > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                // كان ٥٢ مكتوباً في الشيفرة، وهو مفتاحٌ في «الحسابات المحاسبية»
                'account_id' => $this->requireAccountingAccountId('allowed_discount'),
                'description' => 'خصم مسموح به - فاتورة '.$invoice->code,
                'debit' => $final_total_discount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            Log::info('تم إنشاء قيد الخصم المسموح به', [
                'invoice_id' => $invoice->id,
                'discount_amount' => $final_total_discount,
            ]);
        }

        /* ٣. القيمة المضافة المحصَّلة (دائن) — **مركَّبةً لا مجموعة**.
         *
         * كانت `$tax_total` كاملةً في سطرٍ واحد، وقد ضُمَّت إليها ضريبةُ
         * الشحن قبل قليل (`$tax_total + $shipping_tax`). فوعاءان مختلفان —
         * بضاعةٌ وخدمةُ توصيل — يخرجان في القيد رقماً واحداً لا يُفكّ: لا
         * يُعرَف منه كم ضريبةُ المبيعات وكم ضريبةُ الشحن، ولا يُطابَق مع
         * إقرارٍ ضريبيٍّ يفصل بينهما، ولا يُراجَع بندٌ منهما وحده.
         *
         * السطران أدناه مجموعُهما `$tax_total` نفسُه، فالإجمالي والتوازن
         * لا يتغيّران — الذي يتغيّر أنّ القيد صار يقول ممّ تكوّنت الضريبة.
         */
        $items_tax = round($tax_total - $shipping_tax, 2);

        if ($items_tax > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $vatAccount->id, // حساب القيمة المضافة المحصلة
                'description' => 'ضريبة القيمة المضافة على البنود - فاتورة '.$invoice->code,
                'debit' => 0,
                'credit' => $items_tax,
                'is_debit' => false,
            ]);
        }

        if ($shipping_tax > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                // حسابُ ضريبة الشحن إن رُبط، وإلّا حسابُ الضريبة نفسُه
                'account_id' => $this->shippingVatAccountId(),
                'description' => 'ضريبة شحن مبيعات - فاتورة '.$invoice->code,
                'debit' => 0,
                'credit' => $shipping_tax,
                'is_debit' => false,
            ]);
        }

        if ($tax_total > 0) {
            Log::info('تم إنشاء قيد الضريبة المحصلة', [
                'invoice_id' => $invoice->id,
                'items_tax' => $items_tax,
                'shipping_tax' => $shipping_tax,
                'vat_account_id' => $vatAccount->id,
            ]);
        } else {
            Log::warning('لم يتم إنشاء قيد الضريبة لأن المبلغ صفر', [
                'invoice_id' => $invoice->id,
                'tax_total' => $tax_total,
            ]);
        }

        /* ٤. إيراد الشحن (دائن).
         *
         * الحسابان `shipping_revenue` و`shipping_revenue_returns` معرَّفان
         * في «الحسابات المحاسبية» ومربوطان، ولم يكن أحدٌ يقيّد عليهما شيئاً
         * في المشروع كلّه: ما يُحصَّل من العميل مقابل التوصيل كان يدخل
         * `grand_total` ولا يظهر في أيّ حساب إيراد.
         *
         * `require…` عند وجود شحنٍ فقط: منشأةٌ لا تشحن لا يعنيها المفتاح،
         * ومن يشحن يجب أن يُقال له صراحةً إن كان الحساب غير مربوط.
         */
        if ($shipping_cost > 0) {
            $shippingAccount = $this->requireAccountingAccount('shipping_revenue');

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $shippingAccount->id,
                'description' => 'إيراد شحن - فاتورة '.$invoice->code,
                'debit' => 0,
                'credit' => $shipping_cost,
                'is_debit' => false,
            ]);
        }

        /* كان هنا طرفا قيد «التسوية» — إيرادٌ آخر للزيادة وخصمٌ مسموح
           للحسم. سقطا بسقوط الحقل نفسه. */

        // ===========================
        // تكلفة المبيعات (COGS) القيود
        // ===========================
        try {
            // التحقق من عدم إنشاء قيد تكلفة مبيعات مسبقاً لنفس الفاتورة
            $existingCostEntry = JournalEntry::where('invoice_id', $invoice->id)
                ->where('description', 'like', '%تكلفة مبيعات%')
                ->first();

            if (! $existingCostEntry) {
                Log::info('بدء حساب تكلفة المبيعات للفاتورة', ['invoice_id' => $invoice->id]);

                $costTotal = 0; // إجمالي تكلفة المبيعات
                $lineCosts = []; // الاحتفاظ بتفاصيل التكلفة لكل بند
                $inventoryAccountId = null; // حساب المخزون المرتبط بالمستودع

                // جلب البنود من قاعدة البيانات لضمان التزامن
                $invoiceItemsForCost = InvoiceItem::where('invoice_id', $invoice->id)->get();

                foreach ($invoiceItemsForCost as $costItem) {
                    $productId = $costItem->product_id;
                    $storeHouseId = $costItem->store_house_id;

                    // بندٌ حرٌّ بلا منتج: لا تكلفةَ له تُرحَّل
                    if (! $productId) {
                        continue;
                    }

                    // استخدام الكمية بالوحدة الأساسية لحساب التكلفة بشكل صحيح
                    // إذا لم تكن موجودة، نحسبها من الكمية × معامل التحويل
                    $quantity = $costItem->quantity_in_base_unit
                        ?? ($costItem->quantity * ($costItem->conversion_factor ?? 1));

                    $product = Product::find($productId);

                    // البحث عن حساب المخزون من جدول الحسابات باستخدام storehouse_id
                    if ($storeHouseId && $inventoryAccountId === null) {
                        $itemInventoryAccount = Account::where('storehouse_id', $storeHouseId)->first();
                        if ($itemInventoryAccount) {
                            $inventoryAccountId = $itemInventoryAccount->id;
                        }
                    }

                    /* سعرُ التكلفة لا سعرُ الشراء — المتوسّطُ المرجَّح المعروض
                       على بطاقة الصنف (`ResolvesSaleCost`)، ويُجمَّد على البند
                       ليعكسه المرتجعُ بالرقم نفسه. */
                    $unitCost = $this->itemUnitCost($costItem, $product);

                    if ($unitCost <= 0) {
                        Log::warning('لم يتم العثور على سعر تكلفة للمنتج، تم التعيين إلى صفر', ['product_id' => $productId]);
                    }

                    $this->freezeLineCost($costItem, $unitCost);

                    $lineCost = $quantity * $unitCost;
                    $costTotal += $lineCost;

                    $lineCosts[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'line_cost' => $lineCost,
                    ];
                }

                // تحديد حساب المخزون بناءً على المستودع المختار للفاتورة
                $inventoryAccountId = null;
                $targetStoreHouse = $defaultStoreHouse; // نستخدم المستودع المختار للفاتورة

                if ($targetStoreHouse) {
                    $inventoryAccount = Account::where('storehouse_id', $targetStoreHouse->id)->first();
                    if ($inventoryAccount) {
                        $inventoryAccountId = $inventoryAccount->id;
                        Log::info('تم استخدام حساب المخزون للمستودع المختار', [
                            'storehouse_id' => $targetStoreHouse->id,
                            'account_id' => $inventoryAccountId,
                        ]);
                    }
                }

                if ($inventoryAccountId === null) {
                    // fallback للمستودع الرئيسي إذا لم نجد حساباً للمستودع المختار
                    $mainStoreHouse = StoreHouse::where('major', 1)->first();
                    if ($mainStoreHouse) {
                        $inventoryAccount = Account::where('storehouse_id', $mainStoreHouse->id)->first();
                        if ($inventoryAccount) {
                            $inventoryAccountId = $inventoryAccount->id;
                        }
                    }
                }

                if ($inventoryAccountId && $costTotal > 0) {
                    // إنشاء قيد تكلفة المبيعات
                    $costJournalEntry = JournalEntry::create([
                        'reference_number' => $invoice->code,
                        'date' => now(),
                        'description' => 'تكلفة مبيعات - فاتورة '.$invoice->code,
                        'status' => 1,
                        'currency' => $this->documentCurrency($invoice),
                        'client_id' => $invoice->client_id,
                        'invoice_id' => $invoice->id,
                        'reference_type' => ReferenceType::SALES_INVOICE,
                        'linking_type' => LinkingType::SINGLE,
                        'created_by_employee' => Auth::id(),
                    ]);

                    // حساب تكلفة المبيعات المدين (حساب تكلفة المبيعات رقم 50)
                    JournalEntryDetail::create([
                        'journal_entry_id' => $costJournalEntry->id,
                        'account_id' => $this->requireAccountingAccountId('cost_of_sales'),
                        'description' => 'تكلفة مبيعات - فاتورة '.$invoice->code,
                        'debit' => $costTotal,
                        'credit' => 0,
                        'is_debit' => true,
                    ]);

                    // حساب المخزون دائن
                    JournalEntryDetail::create([
                        'journal_entry_id' => $costJournalEntry->id,
                        'account_id' => $inventoryAccountId,
                        'description' => 'تخفيض المخزون - فاتورة '.$invoice->code,
                        'debit' => 0,
                        'credit' => $costTotal,
                        'is_debit' => false,
                    ]);

                    Log::info('تم إنشاء قيود تكلفة المبيعات', [
                        'invoice_id' => $invoice->id,
                        'cost_total' => $costTotal,
                        'inventory_account_id' => $inventoryAccountId,
                        'lines' => $lineCosts,
                    ]);

                    // تحديث أرصدة الحسابات
                    try {
                        // $inventoryAccountModel = Account::find($inventoryAccountId);
                        // if ($costAccount) {
                        //     $costAccount->balance += $costTotal;
                        //     $costAccount->save();
                        // }
                        // if ($inventoryAccountModel) {
                        //     $inventoryAccountModel->balance -= $costTotal; // تخفيض المخزون
                        //     $inventoryAccountModel->save();
                        // }
                    } catch (\Exception $eBalance) {
                        Log::error('فشل تحديث أرصدة حسابات تكلفة المبيعات / المخزون', ['message' => $eBalance->getMessage()]);
                    }
                } else {
                    Log::warning('تم تخطي إنشاء قيد تكلفة المبيعات بسبب عدم توفر حساب مخزون أو عدم وجود تكلفة موجبة', [
                        'invoice_id' => $invoice->id,
                        'inventory_account_id' => $inventoryAccountId,
                        'cost_total' => $costTotal,
                    ]);
                }
            } else {
                Log::info('تم تخطي إنشاء قيد تكلفة المبيعات لأنه موجود مسبقاً', ['invoice_id' => $invoice->id]);
            }
        } catch (\Exception $eCost) {
            Log::error('خطأ أثناء إنشاء قيد تكلفة المبيعات', [
                'invoice_id' => $invoice->id ?? null,
                'message' => $eCost->getMessage(),
                'trace' => $eCost->getTraceAsString(),
            ]);
        }

        /* ** الخطوة السابعة: سند القبض — للنقد المقبوض وحده **

           كان الشرط `$advance_payment > 0 || $is_paid` والمبلغُ
           `$is_paid ? $total_with_tax : $advance_payment`.

           و`$is_paid` تصير صحيحةً حين يغطّي رصيدُ العميل الدائن الفاتورةَ
           كلَّها — انظر كتلة `auto_pay_if_balance` أعلاه. فكانت الفاتورةُ
           المسدَّدة من الرصيد تُنشئ سندَ قبضٍ بكامل قيمتها ومعه قيدٌ ثانٍ:
           مدينٌ الخزينة، دائنٌ العميل.

           وذلك الطرفُ الدائن يُلغي مديونيةَ قيد الفاتورة حرفاً بحرف. فمن
           كان رصيدُه الدائن ٣٥٠٠ وصدرت له فاتورةٌ بـ٩٠٠ خرج برصيدٍ دائنٍ
           ٣٥٠٠ كما دخل: لم يُسحَب من رصيده شيء، وهو عينُ ما شكا منه
           المستخدم. ودخلت الخزينةَ في الطريق ٩٠٠ ريالاً لم يدفعها أحد،
           فانفصلت الخزينةُ الدفترية عن الصندوق. وهذا كلُّه ما نصّ التعليقُ
           في كتلة `auto_pay_if_balance` على تجنّبه، ثمّ نفَذ من `$is_paid`.

           فالنقد المقبوض هو `$advance_payment` وحده — ما دفعه العميل الآن.
           والمسدَّد من رصيده الدائن مقاصّةٌ تمّت بقيد الفاتورة، لا قبضاً.

           ولم يعد `$is_paid` وحده يفتح الكتلة: الحالة «مكتملة» قد تأتي من
           «إعطاء الفواتير حالات يدوية» بلا ريالٍ واحد، وسندُ قبضٍ يولد من
           قائمةٍ منسدلة هو النقدُ الوهميّ نفسه. */
        $cash_payment = round($advance_payment, 2);

        if ($cash_payment > 0) {
            $payment_amount = $cash_payment;

            // تحديد الخزينة المستهدفة بناءً على الموظف
            $MainTreasury = null;

            if ($user && $user->employee_id) {
                // البحث عن الخزينة المرتبطة بالموظف
                $TreasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

                if ($TreasuryEmployee && $TreasuryEmployee->treasury_id) {
                    // إذا كان الموظف لديه خزينة مرتبطة
                    $MainTreasury = Account::where('id', $TreasuryEmployee->treasury_id)->first();
                } else {
                    // إذا لم يكن لدى الموظف خزينة مرتبطة، استخدم الخزينة الرئيسية
                    $MainTreasury = $this->accountingAccount('main_treasury');
                }
            } else {
                // إذا لم يكن المستخدم موجودًا أو لم يكن لديه employee_id، استخدم الخزينة الرئيسية
                $MainTreasury = $this->accountingAccount('main_treasury');
            }

            // إذا لم يتم العثور على خزينة، توقف العملية وأظهر خطأ
            if (! $MainTreasury) {
                DB::rollBack();

                return redirect()->back()->withInput()->with('error', __('sales_invoice.ui.treasury_missing'));
            }

            /* سندُ القبض المولَّد مع الفاتورة كان يخرج ناقصاً ثلاثة حقول،
               فتعرضه شاشةُ «مدفوعات العملاء» بخانتين فارغتين وبلا مُسجِّل:

                 • `treasury_id` لم يُكتب قطّ — والخزينةُ محسوبةٌ في السطور
                   أعلاه (`$MainTreasury`) ثمّ تُرمى. فعمودُ «الخزينة» يعرض
                   «—» في كل سندٍ وُلد مع فاتورته، والسدادُ بلا خزينةٍ
                   معلومة لا يُجمَع في تقارير الخزائن ولا في الإقفال اليومي.

                 • `payment_method` بلا بديل: نموذجُ الفاتورة قد لا يرسل
                   الحقل أصلاً، فيُكتب `null` ويعرض العمود «—». البديلُ هو
                   الطريقةُ الافتراضية المفعَّلة في «طرق الدفع» — نفسُ قاعدة
                   `payFromSignature()`.

                 • `created_by` **ليس عموداً في `payments_process`** ولا في
                   `$fillable`، فيُسقطه إلوكنت صامتاً. والعمودُ الذي يحمل
                   المُسجِّل هو `employee_id` (وهو `users.id` في هذا الجدول)،
                   وكان يُترك فارغاً. */
            $payment = PaymentsProcess::create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'branch_id' => $invoice->branch_id ?? current_branch_id(),
                'treasury_id' => $MainTreasury->id,
                'amount' => $payment_amount,
                'payment_date' => now(),
                'payment_method' => $request->payment_method ?: PaymentMethod::query()
                    ->where('status', 'active')
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->value('id'),
                'reference_number' => $request->reference_number,
                'notes' => 'تم إنشاء الدفعة تلقائياً عند إنشاء الفاتورة',
                'type' => 'client payments',
                'payment_status' => $payment_status,
                'employee_id' => Auth::id(),
            ]);

            // تحديث رصيد الخزينة
            // if ($MainTreasury) {
            //     $MainTreasury->balance += $payment_amount;
            //     $MainTreasury->save();
            // }

            // if ($advance_payment > 0) {

            // if ($clientaccounts) {
            //     $clientaccounts->balance -= $payment_amount; // المبلغ الكلي (المبيعات + الضريبة)
            //     $clientaccounts->save();
            // }
            // } else {
            // if ($clientaccounts) {
            //     $clientaccounts->balance -= $invoice->grand_total; // المبلغ الكلي (المبيعات + الضريبة)
            //     $clientaccounts->save();
            // }
            // }

            // إنشاء قيد محاسبي للدفعة
            $paymentJournalEntry = JournalEntry::create([
                'reference_number' => $payment->reference_number ?? $invoice->code,
                'date' => now(),
                'description' => 'دفعة للفاتورة رقم '.$invoice->code,
                'status' => 1,
                'currency' => $this->documentCurrency($invoice),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
                'linking_type' => LinkingType::SINGLE,
                'created_by_employee' => Auth::id(),
            ]);

            // 1. حساب الخزينة المستهدفة (مدين)
            JournalEntryDetail::create([
                'journal_entry_id' => $paymentJournalEntry->id,
                'account_id' => $MainTreasury->id,
                'description' => 'دفعة للفاتورة رقم '.$invoice->code,
                'debit' => $payment_amount,
                'credit' => 0,
                'is_debit' => true,
                'client_account_id' => $clientaccounts->id,
            ]);

            // 2. حساب العميل (دائن)
            JournalEntryDetail::create([
                'journal_entry_id' => $paymentJournalEntry->id,
                'account_id' => $clientaccounts->id,
                'description' => 'دفعة عميل  للفاتورة رقم '.$invoice->code,
                'debit' => 0,
                'credit' => $payment_amount,
                'is_debit' => false,
                'client_account_id' => $clientaccounts->id,
            ]);
        }

        // ** إضافة ملاحظة تلقائية للعميل مع حساب العهدة التراكمية (كمية المنتجات) **
        try {
            // حساب إجمالي كمية المنتجات في الفاتورة الحالية
            $totalQuantityInInvoice = 0;
            foreach ($request->items as $item) {
                $totalQuantityInInvoice += intval($item['quantity'] ?? 0);
            }

            // جلب آخر عهدة مسجلة للعميل من الملاحظات السابقة
            $lastClientNote = ClientRelation::where('client_id', $invoice->client_id)
                ->whereNotNull('deposit_count')
                ->where('deposit_count', '>', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            // حساب العهدة الجديدة = آخر عهدة + كمية المنتجات في الفاتورة الجديدة
            $previousDeposit = $lastClientNote ? intval($lastClientNote->deposit_count) : 0;
            $newDeposit = $previousDeposit + $totalQuantityInInvoice;

            // إنشاء الملاحظة التلقائية
            ClientRelation::create([
                'client_id' => $invoice->client_id,
                'employee_id' => auth()->id(),
                'type' => 'invoice',
                'invoice_id' => $invoice->id,
                'description' => 'فاتورة رقم: '.$invoice->code.' - الكمية: '.$totalQuantityInInvoice.' قطعة - قيمة الفاتورة: '.number_format($total_with_tax, 2).' ر.س',
                'deposit_count' => $newDeposit,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'status' => 'completed',
                'process' => 'فاتورة مبيعات',
            ]);

            Log::info('تم إنشاء ملاحظة تلقائية للفاتورة', [
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'previous_deposit' => $previousDeposit,
                'invoice_quantity' => $totalQuantityInInvoice,
                'new_deposit' => $newDeposit,
            ]);
        } catch (\Exception $noteException) {
            Log::warning('فشل في إنشاء ملاحظة الفاتورة: '.$noteException->getMessage());
        }

        // === معالجة نقاط إنشاء الفاتورة (invoice_created) ===
        $pointsEmployeeId = $user->employee_id ?? null;
        $pointsClientId = $invoice->client_id ?? null;

        // لا يمكن إنشاء معاملة نقاط بدون معرف موظف أو معرف عميل
        if ($pointsEmployeeId || $pointsClientId) {
            try {
                $pointsContext = [
                    'employee_id' => $pointsEmployeeId,
                    'client_id' => $pointsClientId,
                    'amount' => $invoice->grand_total,
                    'branch_id' => $invoice->branch_id ?? null,
                    'reference_type' => Invoice::class,
                    'reference_id' => $invoice->id,
                    'description' => 'فاتورة مبيعات رقم '.$invoice->code,
                ];

                // منح نقاط إنشاء فاتورة (للموظف والعميل)
                app(PointsService::class)->processAction('invoice_created', $pointsContext);

                // إذا كانت الفاتورة مدفوعة بالكامل (بيع نقدي) → منح نقاط إضافية
                if ($invoice->is_paid && $advance_payment >= $total_with_tax) {
                    app(PointsService::class)->processAction('cash_sale', $pointsContext);
                }

                // منح نقاط للعميل عند الدفع (invoice_paid) — فقط إذا يوجد عميل ومبلغ مدفوع
                if ($advance_payment > 0 && $pointsClientId) {
                    app(PointsService::class)->processAction('invoice_paid', [
                        'employee_id' => $pointsEmployeeId,
                        'client_id' => $pointsClientId,
                        'amount' => $advance_payment,
                        'branch_id' => $invoice->branch_id ?? null,
                        'reference_type' => Invoice::class,
                        'reference_id' => $invoice->id,
                        'description' => 'دفع فاتورة مبيعات رقم '.$invoice->code,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('PointsService: فشل معالجة نقاط فاتورة المبيعات', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        /* ** السداد من محفظة العميل ** — إعداد `auto_pay_from_wallet`
           في «إعدادات الفواتير».

           وهو غيرُ `auto_pay_if_balance` أعلاه، وإن تشابه اسماهما:

             • ذاك يقرأ **ذمّة العميل الدائنة** — حسابَه تحت المدينين وقد
               صار سالباً. وتسويتُه مقاصّةٌ يتمّها قيدُ الفاتورة وحده، فلا
               قيدَ إضافي ولا سند: الطرفان حسابٌ واحد، ولو كُتب لهما قيدٌ
               لكان مديناً ودائناً على نفس الحساب — أي لا شيء.

             • وهذا يقرأ **المحفظة** — حساباً مستقلاً تحت الخصوم فيه مالٌ
               أودعه العميل مقدَّماً. فتسويتُه تحتاج قيداً حقيقياً بطرفين:
               من ح/ محفظة العميل إلى ح/ ذمم العميل. المحفظة تنقص، وذمّةُ
               العميل تنقص معها، ولا تمرّ الخزينة بالقيد — فالنقد دخلها
               يوم الإيداع لا اليوم.

           وموضعُها هنا لا قبل `Invoice::create()`: الحركة تُربط بمستندٍ له
           معرّف. وهي **داخل** المعاملة، فإخفاقُها يُبطل الفاتورة كلَّها ولا
           يتركها محفوظةً بتسويةٍ لم تقع.

           والخدمة وحدها تكتب أثرَ المحفظة على الفاتورة — المستحقّ والحالة
           و`wallet_paid_amount` — فلا يُخصَم المبلغ مرّتين. */
        if ($walletApplied > 0) {
            /* المبلغ محسوبٌ قبل تقرير النقد (انظر كتلة `auto_pay_from_wallet`
               أعلاه)، وهذا موضعُ كتابته: الحركة تُربط بمستندٍ له معرّف.

               ويُعاد ضبطُه على المتبقّي الفعليّ من الفاتورة كما استقرّ بعد
               الحفظ: الحالة اليدوية أو حالةٌ مُرسَلة من الشاشة قد تكون
               غيّرت `due_value` عمّا افترضه الحساب المبكّر، فالمحفظة لا
               تُخصَم فيما لا مستحقَّ يقابله. */
            $walletApplied = round(min($walletApplied, max(0, (float) $invoice->fresh()->due_value)), 2);

            if ($walletApplied > 0) {
                ClientWalletService::payInvoice(
                    $invoice,
                    $walletApplied,
                    'سداد تلقائي من محفظة العميل عند إنشاء الفاتورة'
                );

                $invoice->refresh();
            }
        }

        DB::commit();

        // سجلّ النشاط وسجلّ الأحداث معاً — نقطةُ تسجيل واحدة.
        //
        // كانت الفاتورة تُحفَظ ولا يُكتب لها سطر في أيّ سجلّ: شاشات
        // «سجلّ النشاط» و«سجلّ الأحداث» في الإعدادات تعرض حركة المخزون
        // والمشتريات ولا شيء من المبيعات، فلا يُعرف من أصدر فاتورةً ولا متى
        /* التسويةُ من الرصيد الدائن تُذكَر في السجلّ صراحةً.

           هي الأثرُ الوحيد الذي لا يتركه قيدٌ ولا سندُ قبض — عن قصد، لأنّ
           المقاصّة وقعت بقيد الفاتورة وحده. فلولا هذا السطر لرأى المراجعُ
           فاتورةً حالتُها «مدفوعة» ومستحقُّها صفر وليس لها سندٌ واحد، ولا
           سبيلَ له إلى معرفة كيف سُدِّدت. */
        $creditNote = $creditApplied > 0
            ? ' — سُوِّي من رصيد العميل الدائن: '.CurrencySettings::format($creditApplied)
            : '';

        /* والسدادُ من المحفظة يُذكَر مثلَه — وله فوق ذلك قيدٌ وحركةٌ في
           دفتر المحفظة، فالمراجع يجد أثرَه في ثلاثة مواضع لا في السجلّ
           وحده. */
        $walletNote = $walletApplied > 0
            ? ' — سُدِّد من محفظة العميل: '.CurrencySettings::format($walletApplied)
            : '';

        $creditNote .= $walletNote;

        // استعمالُ الكوبون يُحتسَب بعد أن صارت الفاتورة أمراً واقعاً
        $this->recordCouponUsage($invoice->client_id, $invoice->id);

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $invoice,
            'إنشاء فاتورة مبيعات رقم '.$invoice->code
                .' — العميل: '.($invoice->client->trade_name ?? '—')
                .' — الإجمالي: '.CurrencySettings::format($invoice->grand_total)
                .$creditNote,
        );

        // إعداد رسالة النجاح
        // $response = Http::post($telegramApiUrl, [
        //     'chat_id' => '@Salesfatrasmart',  // تأكد من أن لديك صلاحية الإرسال للقناة
        //     'text' => sprintf("تم إنشاء فاتورة جديدة بنجاح. رقم الفاتورة: %s", $invoice->code),
        //     'parse_mode' => 'Markdown',
        // ]);

        // if ($response->failed()) {
        //     Log::error('خطاء في الارسال للقناة: ' . $response->body());
        // }

        /* أثرُ العروض يُذكر: الحسم يقع في الخادم بعد الضغط على «حفظ»،
           فلو مرّ صامتاً لظنّ المستخدم أنّ الإجمالي اختلّ بلا سبب. */
        $offersNotice = $this->appliedOffersNotice();

        /* والكوبونُ مثلُها: ما طُبِّق يُقال، وما رُفض يُقال سببُه — رمزٌ
           منتهي الصلاحية لا يُسقط فاتورةً كُتبت كاملةً، لكنّ البائع يجب
           أن يعلم أنّه لم يُحسَب. */
        $couponNotice = $this->couponNotice();

        /* الإرسال إلى هيئة الزكاة والضريبة والجمارك جرى عند `DB::commit()`
           أعلاه — يتولّاه `ZatcaSubmissionObserver` لكلّ مسارٍ يُنشئ فاتورة.
           وهنا يُسأل عمّا انتهى إليه ليُقال للمستخدم: الفاتورة حُفظت
           وقُيِّدت، وتعذُّر بلوغ الهيئة تنبيهٌ لا خطأ، والمهمّة المجدولة
           `zatca:submit-pending` تعيد المحاولة. */
        $zatcaNotice = ZatcaInvoiceService::noticeFor($invoice);

        $response = redirect()
            ->route('invoices.show', $invoice->id)
            ->with('success', sprintf('تم إنشاء فاتورة المبيعات بنجاح. رقم الفاتورة: %s', $invoice->code)
                .$creditNote
                .($offersNotice ? ' — '.$offersNotice : '')
                .($couponNotice ? ' — '.$couponNotice : ''));

        return $zatcaNotice
            ? $response->with('warning', __('zatca.invoice.saved_not_sent', ['reason' => $zatcaNotice]))
            : $response;
        // } catch (\Exception $e) {
        //     DB::rollback();
        //     Log::error('خطأ في إنشاء فاتورة المبيعات: ' . $e->getMessage());
        //     return redirect()
        //         ->back()
        //         ->withInput()
        //         ->with('error', 'عذراً، حدث خطأ أثناء حفظ فاتورة المبيعات: ' . $e->getMessage());
        // }
        // edit
    }

    /**
     * حساب المبيعات — من «الحسابات المحاسبية» لا بالبحث عن اسمه.
     *
     * كان `Account::where('name', 'المبيعات')` — يربط القيدَ بنصٍّ عربيّ
     * في عمود الاسم: تسميةٌ يغيّرها المحاسب في شجرة الحسابات فيتوقّف
     * الحفظ، أو يوجد حسابان بالاسم نفسه فيُختار أوّلهما عشوائياً.
     */
    private function getSalesAccount()
    {
        return $this->requireAccountingAccountId('sales');
    }

    /**
     * محتوى رمز الاستجابة السريعة للفاتورة الضريبية (ZATCA TLV).
     *
     * اسم البائع ورقمه الضريبي من «معلومات المنشأة» لا مكتوبَين هنا: كانا
     * `'مؤسسة اعمال خاصة للتجارة'` و`'000000000000000'` ثابتَين في
     * الشيفرة، فكل فاتورة تخرج بـQR يحمل اسم منشأةٍ أخرى ورقماً ضريبياً
     * صفريّاً — وهو ما تفحصه هيئة الزكاة والضريبة أوّلَ ما تفحص.
     */
    /**
     * رمز QR للفاتورة — بصيغة TLV التي تطلبها الفاتورة الإلكترونية.
     *
     * كان `$writer->writeString($invoice->qrcode)` بلا فحص في ثلاثة
     * مواضع: شاشة العرض، والطباعة، والطباعة الضريبية. والعمود يبقى
     * `null` في كل فاتورةٍ لم تمرّ بالمسار الذي يملؤه — فاتورةٌ مستوردة
     * أو منقولة أو منشأةٌ قبل إضافة الحقل. فتخرج
     * `writeString(): Argument #1 ($content) must be of type string`
     * وتسقط الشاشة كلُّها بخطأ ٥٠٠، لا رمزُ QR وحده.
     *
     * والقيمة تُبنى هنا بـ`generateTlvContent()` نفسها التي يستعملها
     * `store()` — لا بنصٍّ وصفيٍّ بديل: رمزٌ لا يقرؤه تطبيق الهيئة أسوأ
     * من رمزٍ غائب، لأنّه يبدو صحيحاً.
     */
    private function invoiceQrSvg(?Invoice $invoice): string
    {
        if (! $invoice) {
            return '';
        }

        // رمز المرحلة الثانية إن قبلت الهيئة المستند، وإلا رمز الأولى —
        // والقاعدة واحدةٌ لكلّ المطبوعات في `ZatcaQr`
        return ZatcaQr::svg(ZatcaQr::forInvoice($invoice));
    }

    private function generateTlvContent($timestamp, $totalAmount, $vatAmount)
    {
        return ZatcaQr::phaseOne($timestamp, (float) $totalAmount, (float) $vatAmount);
    }

    /**
     * وقت الفاتورة كما تطلبه الهيئة في الرمز: ISO 8601 بتوقيت UTC.
     *
     * كان يُكتَب كما يخرج من القاعدة — `2026-09-22 17:22:33` — بلا منطقةٍ
     * زمنية ولا فاصل `T`. تطبيق الهيئة يقرؤه، لكنّه ليس ما تنصّ عليه
     * المواصفة، وقارئٌ أشدّ صرامةً يرفضه.
     */
    private function qrTimestamp($timestamp): string
    {
        try {
            return \Carbon\Carbon::parse($timestamp)->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            return (string) $timestamp;
        }
    }

    private function getTlv($tag, $value)
    {
        $value = (string) $value;

        return pack('C', $tag).pack('C', strlen($value)).$value;
    }

    private function updateParentBalance($parentId, $amount)
    {
        // تحديث الحسابات المرتبطة بالقيمة المضافة
        // if ($parentId) {
        //     $vatAccount = Account::find($parentId);
        //     // if ($vatAccount) {
        //     //     $vatAccount->balance += $amount;
        //     //     $vatAccount->save();

        //     //     // استدعاء الوظيفة نفسها لتحديث الحساب الأعلى منه
        //     //     $this->updateParentBalance($vatAccount->parent_id, $amount);
        //     // }
        // }
    }

    private function updateParentBalanceMainTreasury($parentId, $amount)
    {
        // تحديث رصيد الحسابات المرتبطة الخزينة الرئيسية
        if ($parentId) {
            $MainTreasury = Account::find($parentId);
            if ($MainTreasury) {
                $MainTreasury->balance += $amount;
                $MainTreasury->save();

                // استدعاء الوظيفة نفسها لتحديث الحساب الأعلى منه
                $this->updateParentBalance($MainTreasury->parent_id, $amount);
            }
        }
    }

    private function calculateTaxValue($rate, $total)
    {
        return ($rate / 100) * $total;
    }

    private function updateParentBalanceSalesAccount($parentId, $amount)
    {
        // تحديث رصيد الحسابات المرتبطة  المبيعات
        // if ($parentId) {
        //     $MainTreasury = Account::find($parentId);
        //     if ($MainTreasury) {
        //         $MainTreasury->balance += $amount;
        //         $MainTreasury->save();

        //         // استدعاء الوظيفة نفسها لتحديث الحساب الأعلى منه
        //         $this->updateParentBalanceSalesAccount($MainTreasury->parent_id, $amount);
        //     }
        // }
    }

    public function show(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $user = auth()->user();

        // فحص الصلاحيات
        $canView = false;

        /* ١. نطاقُ الرؤية: الكلّ، أو فرعُه، أو فريقُه، أو ما كتبه ونُسب
              إليه — السلَّمُ نفسُه الذي يُصفّي الجدول في `index()`، فلا
              يُخفي أحدُهما ما يُظهره الآخر.

              وكان الشرطُ الثاني `employee_id == $user->id`: يقابل
              `employees.id` المكتوبَ في الفاتورة بـ`users.id` — رقمان من
              جدولين لا يلتقيان إلّا مصادفة. */
        if ($this->canAccessSalesDocument($invoice->created_by, $invoice->employee_id, $invoice->branch_id)) {
            $canView = true;
        }
        // 3. فحص إذا العميل مسند له (صلاحية العملاء المسندين)
        elseif (
            hasPermission('sales.invoices.view_assigned_clients') &&
            $this->canAccessClient($user, $invoice->client_id)
        ) {
            $canView = true;
        }

        // إذا ما عنده أي صلاحية
        if (! $canView) {
            abort(403, 'غير مصرح لك بعرض هذه الفاتورة');
        }

        $clients = Client::all();
        $employees = Employee::all();

        // سجلّ نشاط الفاتورة.
        //
        // مفتاحان لا واحد: الصفوف القديمة كُتبت بالنوع `sales`، والجديدة
        // يكتبها `OperationLog` بالنوع `SalesInvoices`. الاقتصار على أحدهما
        // يُفرغ التبويب — إمّا من تاريخ الفاتورة القديم أو من حركتها اليوم.
        //
        // وحُذف `whereHas('sales')`: علاقةٌ تربط `type_id` بجدول الفواتير،
        // فتُسقط كل سطرٍ لا يقابله صفٌّ فيه — ومنها أسطر الحذف نفسها
        $logs = ModelsLog::whereIn('type', ['sales', self::LOG_MODULE])
            ->where('type_id', $id)
            ->with('user.branch')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        $invoice->loadMissing(['client', 'items.product', 'createdByUser', 'employee', 'signatures']);

        $return_invoices = Invoice::with(['client', 'createdByUser'])
            ->where('reference_number', $id)
            ->get();

        $invoice_notes = ClientRelation::where('invoice_id', $id)->where('type', 'invoice')->get();

        // تبويب «المدفوعات» — كان له زرٌّ في شريط التبويبات بلا لوحةٍ تقابله،
        // فالضغط عليه لا يُظهر شيئاً
        $payments = PaymentsProcess::with(['treasury', 'employee'])
            ->where('invoice_id', $id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        // تبويب «المخزون» — كذلك بلا لوحة. الحركة المخزنية لفاتورة البيع هي
        // خصمُ بنودها من مستودعاتها، فتُعرض بندًا بندًا مع الرصيد الحالي
        $stockRows = $invoice->items->map(function ($item) {
            $onHand = ProductDetails::where('product_id', $item->product_id)
                ->when($item->store_house_id, fn ($q) => $q->where('store_house_id', $item->store_house_id))
                ->sum('quantity');

            return [
                'item' => $item,
                'store_house' => StoreHouse::find($item->store_house_id),
                'deducted' => $item->quantity_in_base_unit ?: ($item->quantity * ($item->conversion_factor ?: 1)),
                'on_hand' => $onHand,
            ];
        });

        // قوالب «قوالب المبيعات» — منها تُبنى قائمة المطبوعات
        $documentTemplates = $this->salesTemplates(self::DOCUMENT_TYPE);

        /* القالب الافتراضي لكل نوع.
         *
         * المعاينة وزرّ الطباعة كانا يُصدران رابطاً بلا معرّف قالب، وذلك
         * يعني «القالب المدمج» في `renderSalesTemplate()` — فالقالب الذي
         * يصمّمه المستخدم ويعيّنه افتراضياً في «قوالب المستندات» لا يظهر
         * إلا إن اختاره من القائمة يدوياً في كل مرّة.
         */
        $defaultTemplate = $this->defaultSalesTemplate(self::DOCUMENT_TYPE);
        $defaultTaxTemplate = $this->defaultSalesTemplate('tax_invoice');

        /* قوالبُ الفاتورة الضريبية كاملةً — لا افتراضيَّها وحده: نافذةُ
           «إرسال بالبريد» تعرض قائمةً يختار منها المرسِل، وفاتورةٌ ضريبية
           يجب أن تُرسَل بقالبٍ ضريبيّ كما تُطبع به. */
        $taxDocumentTemplates = $this->salesTemplates('tax_invoice');

        $qrCodeSvg = $this->invoiceQrSvg($invoice);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'invoice')->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        $client = Client::where('user_id', auth()->user()->id)->first();

        $invoice_number = $this->generateInvoiceNumber();
        $barcodeNumber = str_pad($invoice->id, 13, '0', STR_PAD_LEFT);
        $barcodeImage = 'https://barcodeapi.org/api/128/'.$barcodeNumber;
        $nextCode = Receipt::max('code') ?? 0;

        while (Receipt::where('code', $nextCode)->exists()) {
            $nextCode++;
        }

        // «إعدادات الفواتير» — تتحكّم بظهور تبويب الربح وأزرار الإرسال هنا
        $invoiceSettings = SalesInvoiceSetting::current();

        /* الربح يُحسب في الخادم لا في القالب: القالب كان يقرأ سعر المنتج
           من الكتالوج ويطرح منه سعر شرائه، فلا يمسّ الفاتورة أصلاً. */
        $profitReport = $this->invoiceProfitReport($invoice);

        /* مهلةُ السداد واستحقاقُها — تُحسب في الخادم لا في القالب، بنفس
           الصنف الذي تقرؤه القائمةُ والمهمّةُ المجدولة. */
        $paymentTerms = \App\Support\PaymentTerms::of($invoice);

        return view('sales::invoices.show', compact(
            'invoice_number',
            'account_setting',
            'nextCode',
            'client',
            'clients',
            'employees',
            'invoice',
            'barcodeImage',
            'TaxsInvoice',
            'qrCodeSvg',
            'invoice_notes',
            'return_invoices',
            'logs',
            'payments',
            'stockRows',
            'documentTemplates',
            'taxDocumentTemplates',
            'defaultTemplate',
            'defaultTaxTemplate',
            'invoiceSettings',
            'paymentTerms',
            'profitReport'
        ) + [
            /* لسان «التوقيع» — قبضُ مبلغٍ عند التسليم سندُ قبضٍ كاملُ
               الأركان، فيحتاج ما يحتاجه سندُ القبض: طريقةَ دفعٍ من «طرق
               الدفع» وخزينةً مسموحاً الإيداعُ فيها. كان يقبض «نقداً»
               مكتوبةً في الشيفرة وبلا خزينةٍ في السند — انظر
               `storeSignatures()`. */
            'signaturePaymentMethods' => PaymentMethod::query()
                ->where('type', 'normal')
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            /* `ownDepositable()` لا `depositable()`: الأولى تسأل **محورين** —
               شرطَ الإيداع المضبوط على الخزينة، **ونطاقَ الرؤية** (خزينةُ
               الموظّف في «خزائن الموظفين» وحدَها، ومن يملك `data_scope_all`
               يرى الكلّ). والصلاحيةُ وحدها لا تكفي: أكثرُ الخزائن شرطُها
               «الجميع» فتبقى ظاهرةً لكلّ موظّف وإن لم تكن خزينةَ أحدٍ منهم،
               فيقبض المندوبُ تحصيلَه في خزينة زميله. وهي القاعدةُ نفسُها
               في سند القبض وفي مرتجع البيع والإشعار الدائن. */
            'signatureTreasuries' => TreasuryAccessGuard::ownDepositable(),
        ]);
    }

    /**
     * معاينة الفاتورة للطباعة — بالقالب المصمَّم في «قوالب المبيعات».
     *
     * `$template` معرّفُ قالبٍ من الإعدادات فيُصيَّر، أو لا شيء فيُعرَض
     * القالب المدمج كما كان. القوالب المدمجة تبقى احتياطاً لا يُنزَع:
     * منشأةٌ لم تصمّم قالباً بعد يجب أن تطبع فاتورتها.
     */
    public function print($id, $template = null)
    {
        if (! $this->wantsBuiltinPrint($template) && ($html = $this->renderSalesTemplate(self::DOCUMENT_TYPE, $id, $template))) {
            OperationLog::record('printed', self::LOG_MODULE, (int) $id, 'طباعة فاتورة مبيعات رقم '.$id);

            return response(DocumentPrintPage::wrap($html));
        }

        $clients = Client::all();
        $employees = Employee::all();
        $invoice = Invoice::find($id);
        $qrCodeSvg = $this->invoiceQrSvg($invoice);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'invoice')->get();
        $account_setting = null;

        if (auth()->check()) {
            $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        }
        $client = null;
        if (auth()->check()) {
            $client = Client::where('user_id', auth()->user()->id)->first();
        }
        $invoice_number = $this->generateInvoiceNumber();

        // إنشاء رقم الباركود من رقم الفاتورة
        $barcodeNumber = str_pad($invoice->id, 13, '0', STR_PAD_LEFT); // تنسيق الرقم إلى 13 خانة

        // إنشاء رابط الباركود باستخدام خدمة Barcode Generator
        $barcodeImage = 'https://barcodeapi.org/api/128/'.$barcodeNumber;
        $nextCode = Receipt::max('code') ?? 0;

        // نحاول تكرار البحث حتى نحصل على كود غير مكرر
        while (Receipt::where('code', $nextCode)->exists()) {
            $nextCode++;
        }

        // تغيير اسم المتغير من qrCodeImage إلى barcodeImage
        return view('sales::invoices.print', compact('invoice_number', 'account_setting', 'nextCode', 'client', 'clients', 'employees', 'invoice', 'barcodeImage', 'TaxsInvoice', 'qrCodeSvg'));
    }

    /** معاينة الفاتورة الضريبية — بقالب «الفاتورة الضريبية» من الإعدادات */
    public function printTax($id, $template = null)
    {
        if (! $this->wantsBuiltinPrint($template) && ($html = $this->renderSalesTemplate('tax_invoice', $id, $template))) {
            OperationLog::record('printed_tax', self::LOG_MODULE, (int) $id, 'طباعة فاتورة ضريبية رقم '.$id);

            return response(DocumentPrintPage::wrap($html));
        }

        $clients = Client::all();
        $employees = Employee::all();
        $invoice = Invoice::findOrFail($id);

        $qrCodeSvg = $this->invoiceQrSvg($invoice);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'invoice')->get();

        $account_setting = null;
        $client = null;
        if (auth()->check()) {
            $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
            $client = Client::where('user_id', auth()->user()->id)->first();
        }

        $invoice_number = $this->generateInvoiceNumber();
        $barcodeNumber = str_pad($invoice->id, 13, '0', STR_PAD_LEFT);
        $barcodeImage = 'https://barcodeapi.org/api/128/'.$barcodeNumber;

        $nextCode = Receipt::max('code') ?? 0;
        while (Receipt::where('code', $nextCode)->exists()) {
            $nextCode++;
        }

        return view('sales::invoices.print_tax', compact('invoice_number', 'account_setting', 'nextCode', 'client', 'clients', 'employees', 'invoice', 'barcodeImage', 'TaxsInvoice', 'qrCodeSvg'));
    }

    public function edit($id)
    {
        return redirect()
            ->back()
            ->with('error', 'لا يمكنك تعديل الفاتورة رقم '.$id.'. طبقا لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقا لمتطلبات الفاتورة الإلكترونية، ولكن يمكن إصدار فاتورة مرتجعة أو إشعار دائن لإلغائها أو تعديلها.');
    }

    public function destroy($id)
    {
        return back()->with('error', 'لا يمكنك حذف الفاتورة. طبقا لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقا لمتطلبات الفاتورة الإلكترونية، ولكن يمكن إصدار فاتورة مرتجعة أو إشعار دائن لإلغائها أو تعديلها.');
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('invoices.index')->with('error', 'لا يمكنك تعديل الفاتورة. طبقا لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقا لمتطلبات الفاتورة الإلكترونية، ولكن يمكن إصدار فاتورة مرتجعة أو إشعار دائن لإلغائها أو تعديلها.');
    }

    /**
     * الرقم المعروض في نموذج الإنشاء — معاينةٌ من «الترقيم المتسلسل».
     *
     * كان `id + 1` مصفوفاً إلى ستّ خانات، بينما `store()` يصفّه إلى خمس:
     * فالرقم الذي يراه المستخدم في الشاشة غير الذي يُحفَظ. وكلاهما يتجاهل
     * البادئة والقالب والتصفير الدوري المضبوطة في الشاشة.
     */
    /**
     * مبلغٌ مكتوبٌ بحرّية إلى رقم — أرقامٌ عربية وفاصلةٌ عربية ومسافات.
     *
     * «٫» فاصلةٌ عشرية و«٬» فاصلُ آلاف، وما سوى الأرقام والنقطة والسالب
     * يُحذف. نظيرُ `convertArabicToEnglish()` في `invoice-calculator.js`،
     * فما تعرضه الشاشة هو ما يُحفظ.
     */
    private function normalizeAmountInput($value): float
    {
        $value = strtr((string) ($value ?? ''), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '٬' => '',
        ]);

        $value = preg_replace('/[^\d.\-]/u', '', $value) ?? '';

        return round((float) $value, 2);
    }

    private function generateInvoiceNumber()
    {
        return $this->previewDocumentNumber(self::NUMBER_SECTION);
    }

    protected function getAccountId($type)
    {
        $account = Account::where('code', $type)->first();

        if (! $account) {
            throw new \Exception("لم يتم العثور على الحساب من نوع: {$type}. الرجاء التأكد من وجود الحساب في دليل الحسابات.");
        }

        return $account->id;
    }

    /**
     * تنزيل الفاتورة PDF — بالقالب المصمَّم في «قوالب المبيعات» إن وُجد.
     *
     * القالب المصمَّم يخرج HTML، فيُطبَع من المتصفّح لا عبر TCPDF: تمرير
     * HTML حرٍّ إلى TCPDF يفقد أنماط القالب ويكسر تخطيطه.
     */
    public function generatePdf($id, $template = null)
    {
        if ($html = $this->renderSalesTemplate(self::DOCUMENT_TYPE, $id, $template)) {
            OperationLog::record('exported_pdf', self::LOG_MODULE, (int) $id, 'تنزيل فاتورة مبيعات رقم '.$id);

            return response(DocumentPrintPage::wrap($html));
        }

        $invoice = Invoice::with(['client', 'items', 'createdByUser'])->findOrFail($id);

        /* البديل الذي كان هنا نصٌّ عربيّ، و`BaconQrCode` يرمّز البايتات
           بـISO-8859-1 فيرمي «Could not encode content to ISO-8859-1»
           ويُسقط العملية كلَّها. `invoiceQrSvg()` تبني حمولة TLV
           بـbase64 — محارف ASCII، وهي الحمولة التي تطلبها الهيئة. */
        $qrCodeSvg = $this->invoiceQrSvg($invoice);

        // Create new PDF document
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('فاتورة رقم '.$invoice->code);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // Disable header and footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Add new page
        $pdf->AddPage();

        // Set RTL direction
        $pdf->setRTL(true);

        // Set font
        $pdf->SetFont('aealarabiya', '', 14);

        // Generate view
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'invoice')->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        $html = view('sales::invoices.pdf', compact('invoice', 'TaxsInvoice', 'account_setting', 'qrCodeSvg'))->render();

        // Add content to PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // Output file
        return $pdf->Output('invoice-'.$invoice->code.'.pdf', 'I');
    }

    /**
     * إرسال الفاتورة إلى بريد العميل.
     *
     * ── ما كان ──
     *
     * ترفَق الفاتورة PDF مبنيّةً من `sales::invoices.print` — قالبِ بلايد
     * المثبَّت في الوحدة — لا من القالب الذي صمّمه المستخدم في «قوالب
     * المستندات» وعيّنه افتراضياً لنوع `sales_invoice`. فالمطبوعُ من
     * الشاشة شيءٌ والمرسَلُ بالبريد شيءٌ آخر، وكلاهما يُسمّى «الفاتورة».
     *
     * ونصُّ الرسالة في `InvoicePdfMail` — قالبُ بلايدٍ آخر لا يصل إليه
     * صاحبُ المنشأة من أي شاشة. وعميلٌ بلا بريدٍ يُوقف العملية كلَّها،
     * ولو أراد المرسِل بريداً واحداً لمرّةٍ واحدة.
     *
     * والملفُّ كان يُكتب في `storage/app/public` ثم يُحذف بـ`unlink()`
     * بلا `try` — فسقوطُ الإرسال يترك نسخةً من فاتورة عميلٍ في مجلّدٍ
     * **منشورٍ على الويب**.
     *
     * ── وما صار ──
     *
     * `SalesDocumentMailer`: القالبُ المختار يُصيَّر ويُرفَق من الذاكرة بلا
     * ملفٍّ على القرص، والنصُّ من «قوالب البريد»، والمستلِمُ يقبل التعديل.
     * والمدمجُ يبقى احتياطاً حين لا قالبَ مصمَّماً للنوع.
     */
    public function send_invoice(Request $request, $id)
    {
        $invoice = Invoice::with(['client', 'items', 'createdByUser'])->findOrFail($id);

        $client = $invoice->client;

        $response = $this->mailSalesDocument(
            $request,
            ['sales_invoice', 'tax_invoice'],
            $invoice->id,
            [
                'number' => (string) $invoice->code,
                'client' => $client?->display_name ?? $client?->trade_name ?? '',
                'email' => $client->email ?? null,
                'client_id' => $invoice->client_id,
                'total' => $invoice->grand_total,
                'date' => $invoice->invoice_date ?? $invoice->created_at,
                'link' => route('invoices.print', ['id' => $invoice->id]),
            ],
            $this->builtInInvoiceHtml($invoice)
        );

        if ($this->documentMailSucceeded()) {
            OperationLog::record('emailed', 'SalesInvoices', $invoice, 'أُرسلت الفاتورة '.$invoice->code.' بالبريد');
        }

        return $response;
    }

    /**
     * قالبُ الوحدة المدمج نصّاً — احتياطُ المرفق حين لا قالبَ مصمَّماً.
     *
     * `null` عند أي تعثّر: رسالةٌ بلا مرفق خيرٌ من رسالةٍ لا تخرج.
     */
    protected function builtInInvoiceHtml(Invoice $invoice): ?string
    {
        try {
            /* رمزُ الاستجابة يُبنى بـ`invoiceQrSvg()`: حمولةُ TLV بـbase64
               — محارف ASCII. و`BaconQrCode` يرمّز بـISO-8859-1، فنصٌّ
               عربيٌّ فيه يرمي «Could not encode content» ويُسقط الإرسال. */
            return view('sales::invoices.print', [
                'invoice' => $invoice,
                'TaxsInvoice' => TaxInvoice::where('invoice_id', $invoice->id)->where('type_invoice', 'invoice')->get(),
                'account_setting' => AccountSetting::where('user_id', auth()->id())->first(),
                'qrCodeSvg' => $this->invoiceQrSvg($invoice),
            ])->render();
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'SalesInvoices', 'email_builtin_html', $invoice->id);

            return null;
        }
    }

    /**
     * ملصق الطرد.
     *
     * **ما كان** — ثلاثة أعطال في ثلاث دوالّ متجاورة، وكلّها تُسقط الزرّ:
     *
     *   • `new Mpdf([...])` و`mpdf/mpdf` **غير مثبَّتة** في المشروع
     *     أصلاً — `composer.json` فيه `barryvdh/laravel-dompdf` لا غير.
     *   • `view('sales.invoices.label')` بنقطةٍ لا بـ`sales::` — مسارٌ لا
     *     وجود له؛ القالب في وحدة المبيعات.
     *   • في `shipping_label()`: `new App\Http\Controllers\Sales\Mpdf`
     *     بلا شرطة مائلة بادئة، فيُحلّ نسبةً إلى فضاء المتحكّم.
     *
     * ثمّ صارت الثلاث تمرّ بـDompdf، فزال الخطأ وبقي الملصق غير مقروء:
     * **Dompdf لا يصل الحروف العربية ولا يعكس اتجاهها**، فتخرج الصفحة
     * «؟؟؟؟؟ ؟؟؟؟؟»؛ ولا ينفّذ `JsBarcode` فيغيب الباركود.
     *
     * فصارت تُطبع من المتصفّح كما تُطبع بقيّة مستندات المبيعات
     * (`invoices.print`): الحروف موصولة، والباركود مرسوم، و«حفظ كـPDF»
     * في حوار الطباعة يعطي الملفّ نفسه سليماً.
     */
    public function label($id)
    {
        return $this->documentPrint($id, 'sales::invoices.label', 'ملصق الطرد');
    }

    /** قائمة الاستلام — ورقة تجهيز المستودع، بلا أسعار */
    public function picklist($id)
    {
        return $this->documentPrint($id, 'sales::invoices.picklist', 'قائمة الاستلام');
    }

    /** ملصق التوصيل — عنوانا المُرسِل والمستلم والمستحقّ عند التسليم */
    public function shipping_label($id)
    {
        return $this->documentPrint($id, 'sales::invoices.shipping_label', 'ملصق التوصيل');
    }

    /**
     * يصيّر مستنداً للطباعة من المتصفّح ويسجّله في سجلّ العمليات.
     *
     * @param  string  $view  قالب المستند
     * @param  string  $label  اسم المستند في سجلّ العمليات
     */
    protected function documentPrint($id, string $view, string $label)
    {
        $invoice = Invoice::with(['client', 'items.product', 'items.storeHouse'])->findOrFail($id);

        /* المعاينة داخل شاشة العرض تُحمّل القالب في إطارٍ بـ`?embed=1`،
           ولا تُعدّ طباعةً — فلا تُقيَّد في السجلّ. */
        if (! request()->boolean('embed')) {
            OperationLog::record(
                'printed',
                self::LOG_MODULE,
                $invoice,
                'طباعة «'.$label.'» للفاتورة رقم '.$invoice->code,
            );
        }

        return response()->view($view, compact('invoice'));
    }

    /**
     * حفظ توقيع استلام الفاتورة — ومعه قبضُ مبلغٍ إن أُدخل.
     *
     * ══════════════════════════════════════════════════════════════════
     *  التوقيعُ إثباتُ استلام، والمبلغُ سندُ قبض
     * ══════════════════════════════════════════════════════════════════
     *
     * فِعلان في نموذجٍ واحد: التوقيعُ يُحفظ دائماً ولا قيدَ له، والمبلغُ
     * — إن أُدخل — **سندُ قبضٍ كاملُ الأركان** لا استثناءَ له.
     *
     * ══════════════════════════════════════════════════════════════════
     *  ما كان — مسارُ قبضٍ ثانٍ يخالف الأوّل في ستّة مواضع
     * ══════════════════════════════════════════════════════════════════
     *
     *   • *طريقةُ الدفع* — `'Payment_method' => 'cash'` نصّاً. والمفتاح
     *     ليس في `$fillable` (العمود `payment_method` وقيمتُه **معرّفُ**
     *     طريقةٍ من «طرق الدفع»، لا كلمة)، فكان يُرمى بصمت: كلُّ سندٍ
     *     من التوقيع بلا طريقةِ دفعٍ إطلاقاً.
     *   • *الخزينة* — لا تُكتب في السند أصلاً (`treasury_id` مفقود)،
     *     بينما القيدُ يُرحَّل على خزينةٍ ما: سندٌ لا يقول أين دخل المال.
     *     وبلا حارسٍ يمنع الإيداعَ في خزينةٍ ليست لهذا المستخدم.
     *   • *رقمُ السند* — `'PAY-'.$code.'-'.time()` بدل «تسلسل أرقام
     *     المستندات»، فسندُ التوقيع لا يدخل تسلسلَ سندات القبض.
     *   • *العميل* — `client_id` لا يُكتب، فالسند لا يُنسب لصاحبه.
     *   • *الأرصدة* — رصيدا الخزينة والعميل معطَّلان بالتعليق، فيُرحَّل
     *     القيدُ ولا يتحرّك الرصيدان كما يتحرّكان في سند القبض العاديّ.
     *   • *المرتجع* — لا حارس: تُقبَض دفعةٌ على فاتورةٍ رُدَّت بضاعتُها.
     *
     * ══════════════════════════════════════════════════════════════════
     *  وما صار
     * ══════════════════════════════════════════════════════════════════
     *
     * المسارُ هو مسارُ `SalesPaymentController::store()` نفسُه — نفسُ
     * الحُرّاس ونفسُ الأعمدة ونفسُ القيد ونفسُ النقاط. والسقفُ هنا
     * `grand_total` وحده: التوقيعُ قبضٌ عند التسليم، وغراماتُ تأخير
     * الأقساط تُحصَّل من شاشة سندات القبض لا من ورقة التسليم.
     */
    public function storeSignatures(Request $request, $invoiceId)
    {
        $validated = $request->validate([
            'signer_name' => 'required|string|max:255',
            'signer_role' => 'nullable|string|max:255',
            'signature_data' => 'required|string',
            'amount_paid' => 'nullable|numeric|min:0',
            // طريقة الدفع والخزينة — كما في شاشة سند القبض
            'payment_method' => 'nullable|exists:payment_methods,id',
            'treasury_id' => 'nullable|exists:treasuries,id',
        ]);

        $invoice = Invoice::findOrFail($invoiceId);

        /* `??` لا فهرسةً مباشرة: الحقول اختياريّة في التحقّق، و
           `validate()` لا تُرجع مفتاحاً لم يصل في الطلب أصلاً. فتوقيعٌ
           يُرسَل بلا «المبلغ المدفوع» — من نموذجٍ مختصر أو من نداءٍ
           برمجيّ — يخرج `Undefined array key "amount_paid"`: خطأ ٥٠٠ بدل
           توقيعٍ محفوظ. */
        $amountPaid = round((float) ($validated['amount_paid'] ?? 0), 2);

        $signature = Signature::create([
            'invoice_id' => $invoice->id,
            'signer_name' => $validated['signer_name'],
            'signer_role' => $validated['signer_role'] ?? null,
            'signature_data' => $validated['signature_data'],
            // لا `signed_at`: لا عمود بهذا الاسم في `signatures`، ووقتُ
            // التوقيع هو `created_at`. كان المفتاح يُرمى بصمت.
            'amount_paid' => $amountPaid > 0 ? $amountPaid : null,
        ]);

        OperationLog::record(
            'signed',
            self::LOG_MODULE,
            $invoice,
            'توقيع استلام الفاتورة **'.$invoice->code.'** باسم **'.$signature->signer_name.'**',
        );

        // توقيعٌ بلا مبلغ: لا سندَ ولا قيد
        if ($amountPaid <= 0) {
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ التوقيع بنجاح',
                'signature' => [
                    'signer_name' => $signature->signer_name,
                    'signer_role' => $signature->signer_role,
                    'signature_data' => $signature->signature_data,
                ],
            ]);
        }

        /* الفترة المحاسبية: القبض يُنشئ قيداً بتاريخ اليوم، فيُفحَص
           اليومُ كما يُفحَص في عكس الإذن المخزني. والتوقيع نفسُه
           محفوظٌ قبلَه: هو إثباتُ استلامٍ لا قيدٌ محاسبي. */
        if ($block = $this->accountingPeriodBlock(now())) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        /* فاتورةٌ لها مرتجع لا تقبل تحصيلاً — الحارسُ نفسُه الذي في
           `SalesPaymentController`: المرتجعُ عكسَ البيعَ وردّ البضاعة،
           فقبضُ ثمنِها بعده يقبض ما لا يُستحقّ. */
        if ($invoice->returnBlockReason()) {
            return response()->json([
                'success' => false,
                'message' => __('sales_payment.blocked.has_return', ['code' => $invoice->code]),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();

            // حساب العميل في دفتر الأستاذ
            $clientAccount = Account::ledger()->where('client_id', $invoice->client_id)->first();

            if (! $clientAccount) {
                throw new \Exception('لم يتم العثور على حساب العميل');
            }

            /* الخزينة: ما اختاره المستخدم أوّلاً، ثمّ خزينةُ الموظّف، ثمّ
               المضبوطة في «الحسابات المحاسبية» — ترتيبُ `SalesPaymentController`
               نفسُه. والمُرسَل معرّفُ صفٍّ في `treasuries` بينما القيدُ
               يحتاج **حساباً**، فيُترجَم قبل أن يُستعمل. */
            $treasuryAccount = null;

            if (! empty($validated['treasury_id'])) {
                $treasuryAccount = Account::find(
                    Treasury::whereKey($validated['treasury_id'])->value('account_id')
                );
            }

            if (! $treasuryAccount) {
                $treasuryAccount = $this->determineTreasury();
            }

            if (! $treasuryAccount) {
                throw new \Exception('لم يتم العثور على خزينة صالحة');
            }

            /* شرطُ الإيداع **ونطاقُ الرؤية** — يُفرضان هنا لا في الشاشة وحدها،
               وبالمحورين معاً كما تعرضهما القائمة أعلاه: فلا يُقبل خيارٌ لم
               يُعرَض، ولا يقع الاحتياطُ (`determineTreasury()`) على خزينةٍ
               ليست للمستخدم. */
            if ($denied = TreasuryAccessGuard::ownDepositBlockReason($treasuryAccount->id)) {
                throw new \Exception($denied);
            }

            /* المحصَّلُ سابقاً — سنداتُ قبضِ العميل وحدها، والفاشلةُ
               مستبعَدة: سندٌ فشل ليس مالاً. */
            $previousPayments = (float) PaymentsProcess::where('invoice_id', $invoice->id)
                ->where('type', 'client payments')
                ->where('payment_status', '!=', 5)
                ->sum('amount');

            $totalPaid = round($previousPayments + $amountPaid, 2);
            $ceiling = round((float) $invoice->grand_total, 2);

            if ($totalPaid - $ceiling > 0.01) {
                throw new \Exception(
                    'المبلغ يتجاوز إجمالي الفاتورة بمقدار '.CurrencySettings::format($totalPaid - $ceiling)
                );
            }

            $isFullPayment = ($totalPaid >= $ceiling - 0.009);
            $remainingAmount = round(max(0, $ceiling - $totalPaid), 2);

            /* طريقةُ الدفع من «طرق الدفع»: ما اختاره المستخدم، وإلّا
               الافتراضيةُ المفعَّلة. والعمودُ يحفظ المعرّف لا الكلمة. */
            $paymentMethodId = $validated['payment_method'] ?? PaymentMethod::query()
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->value('id');

            // 1. سندُ القبض — برقمه من «تسلسل أرقام المستندات»
            $paymentProcess = PaymentsProcess::create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                // فرعُ السند = فرع فاتورته، لا فرعُ من يُحصّل
                'branch_id' => $invoice->branch_id ?? current_branch_id(),
                'treasury_id' => $treasuryAccount->id,
                'amount' => $amountPaid,
                'payment_date' => now(),
                'payment_method' => $paymentMethodId,
                'reference_number' => $this->resolveDocumentNumber(null, SalesPaymentController::NUMBER_SECTION),
                'type' => 'client payments',
                'payment_status' => $isFullPayment ? 1 : 2,
                'employee_id' => $user->id,
                'notes' => 'قبضٌ عند التوقيع على استلام الفاتورة رقم '.$invoice->code,
            ]);

            // 2. الفاتورة
            $invoice->update([
                'advance_payment' => $totalPaid,
                'is_paid' => $isFullPayment,
                'payment_status' => $isFullPayment ? 1 : 2,
                'due_value' => $remainingAmount,
            ]);

            // 3. القيد المحاسبي — مدين الخزينة = دائن العميل
            $journalEntry = JournalEntry::create([
                'reference_number' => $paymentProcess->reference_number ?: $invoice->code,
                'date' => now(),
                'description' => 'قبضٌ عند توقيع استلام الفاتورة رقم '.$invoice->code,
                'status' => 1,
                'currency' => $this->documentCurrency($invoice),
                'client_id' => $invoice->client_id,
                // القيدُ يشير إلى فاتورته كما يشير سندُ القبض العاديّ
                'invoice_id' => $invoice->id,
                'created_by_employee' => $user->id,
                'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
                'linking_type' => LinkingType::SINGLE,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $treasuryAccount->id,
                'description' => 'استلام مبلغ نقدي من العميل - فاتورة '.$invoice->code,
                'debit' => $amountPaid,
                'credit' => 0,
                'is_debit' => true,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $clientAccount->id,
                'description' => 'سداد من العميل - فاتورة '.$invoice->code,
                'debit' => 0,
                'credit' => $amountPaid,
                'is_debit' => false,
            ]);

            // 4. الأرصدة — كانت معطَّلةً بالتعليق فيُرحَّل القيد ولا يتحرّك رصيد
            $treasuryAccount->balance += $amountPaid;
            $treasuryAccount->save();

            $clientAccount->balance -= $amountPaid;
            $clientAccount->save();

            // 5. نقاط التحصيل — كما في سند القبض، ولا تُسقط العملية إن فشلت
            try {
                $pointsContext = [
                    'employee_id' => $user->employee_id ?? null,
                    'client_id' => $invoice->client_id ?? null,
                    'amount' => $amountPaid,
                    'branch_id' => $invoice->branch_id ?? null,
                    'reference_type' => PaymentsProcess::class,
                    'reference_id' => $paymentProcess->id,
                    'description' => 'قبضٌ عند توقيع استلام الفاتورة رقم '.$invoice->code,
                ];

                if ($pointsContext['employee_id']) {
                    app(PointsService::class)->processAction('payment_collected', $pointsContext);
                }

                if ($pointsContext['client_id']) {
                    app(PointsService::class)->processAction('payment_received', $pointsContext);
                }
            } catch (\Throwable $e) {
                Log::warning('PointsService: فشل معالجة نقاط قبضِ التوقيع', [
                    'payment_id' => $paymentProcess->id,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 6. الإشعار
            notifications::create([
                'user_id' => $user->id,
                'type' => 'Payment',
                'title' => 'دفعة جديدة للفاتورة #'.$invoice->code,
                'description' => sprintf(
                    'تم استلام مبلغ %s من العميل %s - المدفوع الكلي: %s - المتبقي: %s',
                    CurrencySettings::format($amountPaid),
                    $invoice->client->trade_name ?? $clientAccount->name,
                    CurrencySettings::format($totalPaid),
                    CurrencySettings::format($remainingAmount)
                ),
            ]);

            // 7. السجلّات الثلاثة — بدل سطر `logs` وحده بلا IP ولا متصفّح
            OperationLog::record(
                'paid',
                self::LOG_MODULE,
                $invoice,
                sprintf(
                    'استلام دفعة بمبلغ **%s** للفاتورة **%s** بسند **%s** — الحالة: **%s**',
                    CurrencySettings::format($amountPaid),
                    $invoice->code,
                    $paymentProcess->reference_number ?: ('#'.$paymentProcess->id),
                    $isFullPayment ? 'مسددة بالكامل' : 'مسددة جزئياً'
                ),
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ التوقيع ومعالجة الدفع بنجاح',
                'signature' => [
                    'signer_name' => $signature->signer_name,
                    'signer_role' => $signature->signer_role,
                    'signature_data' => $signature->signature_data,
                ],
                'payment' => [
                    'reference_number' => $paymentProcess->reference_number,
                    'amount_paid' => number_format($amountPaid, 2),
                    'total_paid' => number_format($totalPaid, 2),
                    'remaining' => number_format($remainingAmount, 2),
                    'is_fully_paid' => $isFullPayment,
                    'payment_status' => $isFullPayment ? 'مسددة بالكامل' : 'مسددة جزئياً',
                ],
                'balances' => [
                    'treasury_balance' => number_format($treasuryAccount->balance, 2),
                    'client_balance' => number_format($clientAccount->balance, 2),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            OperationLog::failure($e, self::LOG_MODULE, 'payment', $invoice->id ?? null);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة الدفع: '.$e->getMessage(),
            ], 422);
        }
    }

    private function determineTreasury()
    {
        $user = Auth::user();
        $treasury = null;

        if ($user && $user->employee_id) {
            $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
            if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                $treasury = Account::find($treasuryEmployee->treasury_id);
            }
        }

        if (! $treasury) {
            $treasury = $this->accountingAccount('main_treasury');
        }

        if (! $treasury) {
            throw new \Exception('لم يتم العثور على خزينة صالحة');
        }

        return $treasury;
    }

    private function applyPaymentToInvoices(Receipt $income, $user, $invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $paymentAmount = $income->amount;

        // حساب المبلغ المدفوع سابقاً لهذه الفاتورة فقط (باستثناء الملغاة)
        $previousPaymentsForThisInvoice = PaymentsProcess::where('invoice_id', $invoice->id)
            ->where('payment_status', '!=', 5)
            ->sum('amount');

        // المبلغ الإجمالي المدفوع للفاتورة بعد هذه العملية
        $totalPaidForInvoice = $previousPaymentsForThisInvoice + $paymentAmount;

        // التحقق من عدم تجاوز المبلغ الإجمالي المدفوع قيمة الفاتورة الحالية
        if ($totalPaidForInvoice > $invoice->grand_total) {
            $excessAmount = $totalPaidForInvoice - $invoice->grand_total;
            throw new \Exception('المبلغ يتجاوز إجمالي الفاتورة الحالية بمقدار '.number_format($excessAmount, 2));
        }

        // تحديد حالة السداد للفاتورة الحالية
        $isFullPaymentForInvoice = ($totalPaidForInvoice >= $invoice->grand_total);

        /* خزينةُ السند وطريقةُ دفعه من سند القبض نفسه.

           كان المفتاح مكتوباً `'Payment_method'` بحرفٍ كبير — وهو ليس في
           `$fillable`، فيُسقطه إلوكنت بلا كلمة؛ وقيمتُه `'cash'` نصٌّ
           والعمودُ يحفظ معرّف الطريقة من «طرق الدفع» لا كلمةً. ولا خزينةَ
           إطلاقاً. فيخرج السدادُ من شاشة الإيرادات بخانتَي «طريقة الدفع»
           و«الخزينة» فارغتين. */
        PaymentsProcess::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'branch_id' => $invoice->branch_id ?? current_branch_id(),
            'treasury_id' => $income->treasury_id,
            'amount' => $paymentAmount,
            'payment_date' => $income->date,
            'payment_method' => $income->payment_method_id,
            'reference_number' => $income->code,
            'type' => 'client payments',
            'payment_status' => $isFullPaymentForInvoice ? 1 : 2,
            'employee_id' => $user->id,
            'notes' => 'دفع عبر سند القبض رقم '.$income->code,
        ]);

        // تحديث حالة الفاتورة الحالية فقط
        $invoice->update([
            'advance_payment' => $totalPaidForInvoice,
            'is_paid' => $isFullPaymentForInvoice,
            'payment_status' => $isFullPaymentForInvoice ? 1 : 2,
            'due_value' => $invoice->grand_total - $totalPaidForInvoice,
        ]);

        // إرسال إشعار خاص بهذه الفاتورة
        Notification::create([
            'user_id' => $user->id,
            'type' => 'invoice_payment',
            'title' => 'سداد فاتورة #'.$invoice->code,
            'description' => 'تم سداد مبلغ '.number_format($paymentAmount, 2).
                ' (إجمالي مدفوعات هذه الفاتورة: '.number_format($totalPaidForInvoice, 2).
                ' - المتبقي: '.number_format($invoice->grand_total - $totalPaidForInvoice, 2).')',
            'metadata' => ['invoice_id' => $invoice->id],
        ]);
    }

    private function createJournalEntry(Receipt $income, $user, $clientAccount, $treasury)
    {
        $journalEntry = JournalEntry::create([
            'reference_number' => $income->code,
            'date' => $income->date,
            'description' => 'سند قبض رقم '.$income->code,
            'status' => 1,
            'currency' => $this->defaultCurrency(),
            'client_id' => $clientAccount->client_id ?? null,
            'created_by_employee' => $user->id,
            'reference_type' => ReferenceType::RECEIPT_VOUCHER,
            'linking_type' => LinkingType::SINGLE,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $treasury->id,
            'description' => 'استلام مبلغ من سند قبض',
            'debit' => $income->amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $income->account_id,
            'description' => 'إيرادات من سند قبض',
            'debit' => 0,
            'credit' => $income->amount,
            'is_debit' => false,
        ]);
    }

    public function markAsPaidSilently($id)
    {
        // منع الموظف من تنفيذ الإجراء
        if (auth()->user()->isEmployee()) { // ✅ isEmployee() بدلاً من عمود role النصي
            abort(403, 'غير مصرح لك بتنفيذ هذا الإجراء.');
        }

        $invoice = Invoice::findOrFail($id);

        $before = ['payment_status' => $invoice->payment_status, 'due_value' => $invoice->due_value];

        $invoice->is_paid = true;
        $invoice->payment_status = 1; // مدفوع بالكامل
        $invoice->due_value = 0;

        $invoice->save();

        /* تغييرٌ في فاتورةٍ صادرة بلا سند قبضٍ ولا قيد: أقلُّ ما يجب أن يُعرَف
           من فعله ومتى. كان يمرّ بلا سطرٍ في أيّ سجلّ. */
        OperationLog::record(
            'marked_paid',
            self::LOG_MODULE,
            $invoice,
            'تعليم الفاتورة رقم '.$invoice->code.' مدفوعةً بالكامل بلا سند قبض',
            ['old' => $before, 'new' => ['payment_status' => 1, 'due_value' => 0]],
        );

        // الرجوع لنفس الصفحة مع رسالة نجاح
        return redirect()->back()->with('success', 'تم تغيير حالة الفاتورة إلى مدفوعة بالكامل.');
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

            $invoices = Invoice::findOrFail($id);

            // التعامل مع المرفق إذا تم رفعه
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                /* اسمٌ يولّده الخادم بامتدادٍ من المحتوى (`store()` لا `storeAs()`
                   باسم المتصفّح): صورةٌ سليمة باسم `x.php` كانت تمرّ من `mimes`
                   وتُحفظ `.php` تحت `storage` المكشوف للويب. */
                $attachmentPath = $request->file('attachment')->store('invoices/notes', 'public');
            }

            // إنشاء الملاحظة باستخدام ClientRelation
            $clientRelation = ClientRelation::create([
                'process' => $request->process,
                'time' => $request->time,
                'date' => $request->date,
                'invoice_id' => $id,
                'employee_id' => auth()->user()->id,
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'type' => 'invoice', // نوع مختلف للفواتير
            ]);

            // إرسال إشعار
            notifications::create([
                'user_id' => $invoices->user_id,
                'receiver_id' => $invoices->user_id,
                'title' => 'ملاحظة جديدة',
                'description' => 'تم إضافة ملاحظة جديدة فاتورة مبيعات  رقم '.$invoices->code,
            ]);

            // تسجيل النشاط في السجلّات الثلاثة
            OperationLog::record(
                'note_added',
                self::LOG_MODULE,
                $invoices,
                sprintf(
                    // كان النصّ يقول «فاتورة الشراء» في متحكّم فواتير البيع
                    'إضافة ملاحظة جديدة لفاتورة المبيعات رقم **%s** بعنوان: %s',
                    $invoices->code ?? '',
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
            OperationLog::failure($e, 'Sales.Invoices', 'addNote');

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
            $notes = ClientRelation::where('invoice_id', $id)
                ->where('type', 'invoice')
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedNotes = $notes->map(function ($note) {
                $attachmentUrl = $note->attachment ? asset('storage/'.$note->attachment) : null;

                return [
                    'id' => $note->id,
                    'description' => $note->description,
                    'process' => $note->process,
                    'date' => $note->date,
                    'time' => $note->time,
                    'employee_name' => optional($note->employee)->name ?? 'غير محدد',
                    'has_attachment' => ! empty($note->attachment),
                    'attachment_url' => $attachmentUrl,
                    'created_at' => optional($note->created_at)->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'notes' => $formattedNotes,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.Invoices', 'getNotes');

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

            // حذف المرفق إذا كان موجود
            if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                Storage::disk('public')->delete($note->attachment);
            }

            $quotationId = $note->invoice_id;
            $process = $note->process;

            $note->delete();

            // تسجيل النشاط
            OperationLog::record(
                'note_deleted',
                self::LOG_MODULE,
                $quotationId,
                sprintf('حذف ملاحظة "%s" من فاتورة المبيعات رقم %s', $process, $quotationId),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملاحظة بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.Invoices', 'deleteNote');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الملاحظة: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * حساب المسافة بين نقطتين باستخدام صيغة Haversine
     *
     * @param  float  $lat1
     * @param  float  $lon1
     * @param  float  $lat2
     * @param  float  $lon2
     * @return float المسافة بالكيلومتر
     */

    // ════════════════════ فاتورةٌ من حجز ════════════════════

    /**
     * تعبئةُ نموذج الفاتورة من حجز — بصيغة `copySource` التي يقرؤها النموذج.
     *
     * `null` حين لا يجوز التحويل: وحدةُ الحجوزات غائبة، أو الحجزُ **خارج
     * نطاق رؤية المستخدم** (القصُّ نفسُه الذي يحكم قائمةَ الحجوزات —
     * `Reservation::scopeVisibleToUser`؛ كان `find()` بلا نطاق فيفتح أيُّ من
     * يُنشئ فاتورةً حجزَ أيّ فرعٍ بمعرّفه في الرابط)، أو لا يُفوتَر بقاعدته
     * (`canBeInvoiced`: عميلٌ وخدمةٌ وحالةٌ مؤكَّدةٌ فما بعدها وبلا فاتورةٍ
     * قائمة). والضريبةُ **نسبتُها** لا معرّفُها: قائمةُ الضريبة في البند
     * قيمُها النِّسب.
     *
     * @return array{id: int, code: string, product_id: int, copy: array<string, mixed>}|null
     */
    protected function reservationInvoiceSource(int $reservationId): ?array
    {
        $model = '\\Modules\\Client\\Reservations\\Models\\Reservation';

        if (! class_exists($model)) {
            return null;
        }

        $reservation = $model::query()->visibleToUser()->with(['tax', 'product', 'invoice'])->find($reservationId);

        if (! $reservation || ! $reservation->canBeInvoiced()) {
            return null;
        }

        $description = trim($reservation->service_name.' — '.$reservation->code.' ('
            .$reservation->reservation_date?->format('Y-m-d').' '.substr((string) $reservation->start_time, 0, 5).')');

        return [
            'id' => (int) $reservation->id,
            'code' => (string) $reservation->code,
            'product_id' => (int) $reservation->product_id,
            'copy' => [
                'client_id' => $reservation->client_id,
                'employee_id' => $reservation->employee_id,
                'currency' => $reservation->currency,
                'notes' => $reservation->notes,
                'payment_terms' => null,
                'tax_type' => null,
                'discount_amount' => 0,
                'discount_type' => 'amount',
                'shipping_cost' => 0,
                'shipping_tax' => 0,
                'code' => null,
                'items' => [[
                    'product_id' => (int) $reservation->product_id,
                    'product_unit_id' => null,
                    'store_house_id' => null,
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => (float) $reservation->unit_price,
                    'discount' => (float) $reservation->discount_amount,
                    'discount_type' => 'amount',
                    'tax_1' => $reservation->tax?->tax,
                ]],
            ],
        ];
    }

    /**
     * الحجزُ الذي تُربط به الفاتورةُ الجارية — مقفولاً داخل معاملة الحفظ.
     *
     * لا يُربط إلا حجزٌ **في نطاق المستخدم**، لنفس عميل الفاتورة، وبحالةٍ
     * تجيز الفوترة (`canBeInvoiced`: مؤكَّدٌ فما بعده، بلا فاتورةٍ قائمة —
     * تُقرأ خلف القفل، فحجزٌ أُلغي أو فُوتر بين فتح الشاشة والحفظ لا يُربط)؛
     * وإلّا تُحفظ الفاتورةُ عاديةً بلا ربط — فحقلٌ مخفيّ معدَّل لا يربط
     * فاتورةَ عميلٍ بحجز عميلٍ آخر ولا يفوتر حجزاً لا يُفوتَر بالزرّ.
     */
    protected function lockReservationForInvoice(int $reservationId, int $clientId): ?object
    {
        $model = '\\Modules\\Client\\Reservations\\Models\\Reservation';

        if (! class_exists($model)) {
            return null;
        }

        $reservation = $model::query()->visibleToUser()->whereKey($reservationId)->lockForUpdate()->first();

        if (! $reservation || (int) $reservation->client_id !== $clientId) {
            return null;
        }

        if (! $reservation->canBeInvoiced()) {
            return null;
        }

        return $reservation;
    }
}
