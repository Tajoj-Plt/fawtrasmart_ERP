<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\SalesPlanFeatures;
use App\Constants\SystemAccountIds;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Client;
use App\Models\ClientRelation;
/* `SerialSetting` لم تعد تُقرأ هنا: الترقيم عبر `GeneratesDocumentNumbers`
   ومن ورائها `DocumentNumberService` تحت قفل صفّ */
use App\Models\CompiledProducts;
use App\Models\CreditLimit;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\GiftOffer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PermissionSource;
// إضافات لتحويل عرض السعر إلى فاتورة
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\Quote;
use App\Models\SalesInvoiceSetting;
use App\Models\Store\StoreCoupon;
use App\Models\StoreHouse;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Clients\ClientVisibilityScope;
use App\Services\Sales\SalesCouponService;
use App\Services\Tax\DocumentTaxCalculator;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use App\Support\StockEligibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramNotificationService;
use Modules\Sales\Support\ProductUnits;
use Modules\Sales\Support\SalesWarehouse;
use TCPDF;

/**
 * ══════════════════════════════════════════════════════════════════════
 * عروض الأسعار
 * ══════════════════════════════════════════════════════════════════════
 *
 * المستند الوحيد في وحدة المبيعات الذي يسبق البيع: يُرسَل إلى العميل،
 * فإن قَبِله صار فاتورة. ولذلك يمسّ من الإعدادات ما تمسّه الفاتورة نفسها.
 *
 * ── ما يسري عليه من الإعدادات ──
 *
 *   الترقيم المتسلسل  قسم `quotation` — رقم العرض، ورقم الفاتورة عند
 *                     التحويل من قسم `invoice`. كلاهما تحت قفل صفّ.
 *   العملات           الرمز والكسور والفواصل، وتُجمَّد العملة مع المستند.
 *   اللغات            صيغة التاريخ والاتجاه.
 *   إعدادات الضريبة   نِسَب `TaxSitting` وأنواعها.
 *   الحسابات المحاسبية قيد التحويل كلّه: العميل والمبيعات والضريبة والخصم
 *                     والتكلفة — لا رقم حسابٍ مكتوب في الشيفرة.
 *   الفترات المحاسبية  الحفظ والتعديل والتحويل تُفحَص بتواريخها.
 *   قوالب المستندات   نوع `quote` — الطباعة وPDF بما صُمِّم في الإعدادات.
 *   سجلّ النشاط       كل عملية تُكتب بـ`OperationLog` بنوع `quote`.
 *   مظهر النظام       عبر القوالب لا عبر المتحكّم.
 *
 * ── ما كان ──
 *
 * لا شيء من ذلك. الرقم من `id + 1`، والعملة «ر.س» نصّاً في اثني عشر
 * موضعاً، والضريبة ١٥٪ مثبَّتة في `update()`، وحساب الخصم `52` مكتوباً في
 * القيد، ورمز بوت تِلِغرام في الشيفرة، والتاريخ `Y/m/d` مرّةً و`Y-m-d`
 * مرّةً. وشاشة الطباعة تعرض قالب الوحدة مهما صمّم المستخدم من قوالب.
 *
 * @see \Modules\Sales\Http\Controllers\InvoicesController النظير الأكبر
 */
class QuoteController extends Controller
{
    use Concerns\AppliesDiscountOffers;
    use Concerns\AppliesSalesCoupons;
    use Concerns\ChecksSubscriptionLimits;
    use Concerns\GeneratesDocumentNumbers;
    use Concerns\GuardsClientScope;
    use Concerns\RendersSalesDocumentTemplates;
    use Concerns\ResolvesAccountingAccounts;

    /* تكلفةُ البيع في قيد التحويل — سعرُ التكلفة لا سعرُ الشراء.
       كان التحويل يحمل سلسلةَ الأسعار القديمة بنفسه بعد أن استُبدلت في
       شاشة الفاتورة والمرتجع والإشعار. */
    use Concerns\ResolvesSaleCost;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\ScopesSalesVisibility;
    use Concerns\SendsSalesDocumentEmails;
    use Concerns\UsesDocumentCurrency;

    /** قسم «الترقيم المتسلسل» الذي تُؤخذ منه أرقام عروض الأسعار */
    public const NUMBER_SECTION = 'quotation';

    /** قسم الترقيم الذي يُؤخذ منه رقم الفاتورة عند تحويل العرض */
    public const INVOICE_NUMBER_SECTION = 'invoice';

    /** نوع المستند في «قوالب المستندات» — به تُقرأ قوالب هذه الشاشة */
    public const DOCUMENT_TYPE = 'quote';

    /**
     * قيمة العمود `type` في سجلّ النشاط.
     *
     * `quote` لا `SalesQuotes`: `show()` تقرأ الخطّ الزمني بـ
     * `where('type', 'quote')`، فصفٌّ بقيمةٍ أخرى لا يظهر على المستند.
     */
    public const LOG_MODULE = 'quote';

    /** خيارات «عدد السجلّات» المسموحة — قائمة مغلقة لا رقمٌ من الطلب */
    protected const PER_PAGE = [10, 25, 50, 100];

    /**
     * الحالات القابلة للاختيار اليدوي عبر `manual_quote_status`.
     *
     * `Converted_to_Invoice` مستثناة عمداً: تلك القيمة تكتبها
     * `convertToInvoice()` وحدها لحظة إنشاء الفاتورة فعلاً — واختيارها
     * يدوياً يقفل عرضاً (`Quote::isLocked()`) لم يتحوّل فعلياً.
     */
    protected const MANUAL_STATUSES = [
        Quote::STATUS_DRAFT,
        Quote::STATUS_PENDING,
        Quote::STATUS_APPROVED,
        Quote::STATUS_CANCELLED,

        /* «منتهي الصلاحية» يكتبها الأمر `quotations:expire` من تلقائه،
           ووجودُها هنا يتيح الخروج منها: عرضٌ انتهى ثمّ مُدّ تاريخه يعود
           «مقبولاً» بيد صاحبه. ولولا ذلك لبقي منتهياً إلى الأبد. */
        Quote::STATUS_EXPIRED,
    ];

    /**
     * حالةُ المستند حين يُنشأ ولا يختار المستخدم حالةً.
     *
     * كانت `STATUS_DRAFT`: عرضٌ يُملأ ويُحفَظ ويُرسَل إلى العميل ثمّ يبقى
     * «مسودّة» في القوائم والتقارير حتى يفتحه صاحبُه ويبدّل حالته يدوياً —
     * وذلك المُنتقي لا يظهر أصلاً إلّا بتفعيل «حالات يدوية للعروض».
     * فالمسودّة حالةُ ما لم يُحفَظ بعد، والمحفوظُ معتمَد.
     *
     * ومن أراد المسودّة يختارها من المُنتقي متى فُعِّل المفتاح —
     * `MANUAL_STATUSES` تُبقيها متاحة.
     */
    public const DEFAULT_STATUS = Quote::STATUS_APPROVED;

    /** قيمة `quotes.type` — يبدّلها الوارث فيصير المتحكّم نفسه لمستندٍ آخر */
    public const DOCUMENT_KIND = Quote::TYPE_QUOTE;

    /**
     * ── متحكّمٌ واحد لمستندين ──
     *
     * `SalesOrderController` يرث هذا الصنف كلَّه ولا يغيّر منه إلّا ما
     * أدناه: نوعَ الصفّ في `quotes`، وقسمَ الترقيم، وأسماءَ المسارات،
     * وبادئةَ الترجمة، والصلاحيات. والشاشات نفسُها تخدم المستندين —
     * تقرأ ما يُمرَّر إليها في `$doc` ولا تعرف أيَّهما تعرض.
     *
     * والبديل كان نسخ `convertToInvoice()` — سبعمئة سطرٍ فيها القيدُ
     * المحاسبي وإذنُ الصرف وخصمُ المخزون وحدُّ الائتمان — فتفترق النسخة
     * عن أصلها عند أوّل تعديل، ويصير للنظام طريقتان لإصدار فاتورة.
     */
    protected function documentKind(): string
    {
        return static::DOCUMENT_KIND;
    }

    /** مجلّد الشاشات — مشترَكٌ بين المستندين */
    protected function viewPath(): string
    {
        return 'sales::qoution';
    }

    /** بادئة أسماء المسارات: `questions.index` أو `sales_orders.index` */
    protected function routePrefix(): string
    {
        return 'questions';
    }

    /**
     * بادئة الصلاحيات: `sales.quotes` أو `sales.sales_orders`.
     *
     * القوالب كانت تكتبها حرفياً (`@permission('sales.quotes.edit')`)،
     * فأزرارُ أمر البيع تُحكَم بصلاحيات عرض السعر — يراها من لا يملكها،
     * ولا يراها من يملك صلاحية الأوامر وحدها.
     */
    protected function permissionPrefix(): string
    {
        return 'sales.quotes';
    }

    /** اسم مسارٍ كامل */
    protected function routeName(string $action): string
    {
        return $this->routePrefix().'.'.$action;
    }

    /**
     * بادئة الترجمة الخاصّة بهذا المستند داخل `sales_documents`.
     *
     * المفتاح يُطلَب أوّلاً تحت هذه البادئة، فإن لم يُعرَّف قُرئ من الجذر —
     * انظر `documentTranslator()`. فلا يُكتب من نصوص أمر البيع إلّا ما
     * يخالف نصّ عرض السعر، والباقي مشترَكٌ بلا تكرار.
     */
    protected function langPrefix(): ?string
    {
        return null;
    }

    /**
     * ما تحتاجه الشاشات لتعرف أيّ مستندٍ تعرض.
     *
     * `t` مترجِمٌ يقبل المفتاح بعد `sales_documents.` ويردّ نصَّ هذا
     * المستند. و`routes` أسماءُ المسارات كاملةً — القوالب كانت تكتبها
     * حرفياً (`route('questions.index')`) فلا تصلح لمستندٍ ثانٍ.
     *
     * @return array{kind: string, is_sales_order: bool, t: callable, routes: array<string, string>}
     */
    protected function documentContext(): array
    {
        $prefix = $this->langPrefix();
        $permissionPrefix = $this->permissionPrefix();

        return [
            'kind' => $this->documentKind(),
            'is_sales_order' => $this->documentKind() === Quote::TYPE_SALES_ORDER,

            't' => static function (string $key, array $replace = []) use ($prefix) {
                if ($prefix) {
                    $specific = 'sales_documents.'.$prefix.'.'.$key;

                    if (\Illuminate\Support\Facades\Lang::has($specific)) {
                        return __($specific, $replace);
                    }
                }

                return __('sales_documents.'.$key, $replace);
            },

            'can' => static function (string $ability) use ($permissionPrefix): bool {
                return hasPermission($permissionPrefix.'.'.$ability);
            },

            'routes' => [
                'index' => $this->routeName('index'),
                'create' => $this->routeName('create'),
                'store' => $this->routeName('store'),
                'show' => $this->routeName('show'),
                'edit' => $this->routeName('edit'),
                'update' => $this->routeName('update'),
                'destroy' => $this->routeName('destroy'),
                'print' => $this->routeName('print'),
                'pdf' => $this->routeName('pdf'),
                'email' => $this->routeName('email'),
                'convert' => $this->routeName('convert-to-invoice'),
                'check_coupon' => $this->routeName('check_coupon'),
            ],
        ];
    }

    /**
     * أتُختار حالةُ المستند يدوياً؟
     *
     * لكلّ مستندٍ مفتاحُه في «إعدادات الفواتير»: `manual_quote_status`
     * لعرض السعر و`manual_sales_order_status` لأمر البيع. كان الثاني
     * مفتاحاً بلا قارئ.
     */
    protected function manualStatusEnabled(): bool
    {
        return (bool) SalesInvoiceSetting::current()->manual_quote_status;
    }

    /**
     * شاشةٌ من شاشات هذا المستند، ومعها سياقُه.
     *
     * `$doc` يصل إلى كل قالبٍ عبر هذه الدالّة وحدها: قالبٌ يُصيَّر بلا
     * سياقٍ يقرأ `$doc['routes']` غيرَ معرَّف فيسقط بـ«Undefined variable».
     */
    protected function docView(string $name, array $data = [])
    {
        return view($this->viewPath().'.'.$name, $data + ['doc' => $this->documentContext()]);
    }

    /** المترجِم وحده — للاستعمال داخل المتحكّم */
    protected function dt(string $key, array $replace = []): string
    {
        return ($this->documentContext()['t'])($key, $replace);
    }

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.quotes.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.quotes.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.quotes.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.quotes.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales.quotes.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.quotes.print', ['only' => ['print', 'downloadPdf']]);
        $this->middleware('permission:sales.quotes.send_link', ['only' => ['sendQuoteLink']]);
        $this->middleware('permission:sales.quotes.convert', ['only' => ['convertToInvoice']]);
    }

    /**
     * فهرس عروض الأسعار.
     *
     * نداء Ajax يعيد جزء الجدول نصّاً، والنداء العادي يصيّر الصفحة كاملةً
     * ومعها صفحتها الأولى — كانت تُرسَل بجدولٍ فارغ ثم تُملأ بعد ١٠٠
     * مِلّي، فمن أطفأ JS لم يرَ شيئاً أبداً.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        /* `withCount` لا `with('items')`: العدد وحده هو المعروض في العمود،
           وتحميل البنود كلّها لعشرين عرضاً يجرّ آلاف الصفوف بلا داعٍ. */
        $query = Quote::query()
            ->ofType($this->documentKind())
            ->with(['client:id,trade_name,first_name,last_name', 'creator:id,name'])
            ->withCount('items');

        /* نطاقُ الرؤية — السلَّمُ الخماسيّ: الكلّ، أو فرعُه، أو فريقُه، أو
           ما أنشأه ونُسب إليه. كان «الكلّ أو `created_by = أنا`»، فالمشرفُ
           لا يرى عروضَ مندوبيه ولا مديرُ الفرع عروضَ فرعه.
           انظر `ScopesSalesVisibility`. */
        $this->scopeSalesDocuments($query);

        /* الفرع النشط من الهيدر — مبدّل الفروع يكتب `users.branch_id`
           و`current_branch_id()` تقرؤه. المدير على «كل الفروع» قيمتُه
           `null` فلا تصفية. */
        /* والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها،
           فمن يقف عليه يرى الكلَّ — كما في شاشة الفواتير والمرتجعات. */
        $this->applyHeaderBranchScope($query);

        // قاعدةُ منسدلتَي «أُنشئ بواسطة» و«مسؤول المبيعات» — قبل فلاتر البحث
        $optionsBase = clone $query;

        $range = $this->applySearchFilters($query, $request);

        /* القائمة المغلقة تمنع `per_page=100000` — البند ٦.٣ في تقرير
           التحسين يرصده ثغرةَ إنهاك. */
        $perPage = (int) $request->input('per_page', static::PER_PAGE[0]);
        if (! in_array($perPage, static::PER_PAGE, true)) {
            $perPage = static::PER_PAGE[0];
        }

        $quotes = $query->orderByDesc('quote_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->except('page'));

        /* إحصاءات الشاشة — على نفس الاستعلام المفلتَر لا على الجدول كلّه،
           فالبطاقات تصف ما يراه المستخدم أمامه لا ما في القاعدة. */
        $totalsQuery = clone $query;
        $quotesTotal = (float) $totalsQuery->sum('grand_total');
        $quotesCount = $quotes->total();

        /* الردّ JSON كما في شاشة الفواتير، لا نصّاً خاماً: الجدول جزءٌ منه
           والإحصاءات معه، فتتحدّث البطاقات مع كل بحث بدل أن تبقى على أرقام
           أوّل تحميل. */
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $this->docView('partials.table', compact('quotes'))->render(),
                'current_page' => $quotes->currentPage(),
                'last_page' => $quotes->lastPage(),
                'total' => $quotes->total(),
                'from' => $quotes->firstItem(),
                'to' => $quotes->lastItem(),
                'per_page' => $quotes->perPage(),
                'quotes_total' => CurrencySettings::format($quotesTotal),
                'quotes_count' => $quotesCount,
            ]);
        }

        return $this->docView('index', [
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::QUOTES),
            'quotes' => $quotes,
            'range' => $range,
            'quotesTotal' => $quotesTotal,
            'quotesCount' => $quotesCount,
            /* مُنتقي العميل في الفلتر — على نطاق الرؤية كمُنتقي شاشة
               الإنشاء: القائمةُ كانت كلَّ عملاء المنشأة، فتُفشي أسماءَ من
               لا يراهم المستخدم في «إدارة العملاء»، وتَعِد بنتيجةٍ فارغة
               لمن يضغط اسماً منها. */
            'clients' => $this->scopedClientsForSelect(),
            /* مُنتقي «أُنشئ بواسطة» — على نطاق الرؤية كما في شاشة الفاتورة:
               منسدلةٌ تعرض أسماءً لا يُرى لها صفٌّ في الجدول تُفشي ما لا
               يحقّ، وتَعِد بنتيجةٍ فارغة. */
            /* والمصدرُ العروضُ التي يراها المستخدم في هذا الفرع — انظر
               `creatorFilterOptions()`. */
            'employees' => $this->creatorFilterOptions($optionsBase),
            // مُنتقي «مسؤول المبيعات» — على القاعدة نفسِها
            'salesPersons' => $this->salesPersonFilterOptions($optionsBase),
            'branches' => \App\Models\Branch::where('status', 0)->select('id', 'name')->get(),
        ]);
    }

    /**
     * شروط البحث — وتعيد نطاق التاريخ المطبَّق ليعرضه القالب.
     *
     * @return array{start: string, end: string, is_default: bool}
     */
    protected function applySearchFilters($query, Request $request): array
    {
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        /* البحث بالرقم على `quotes_number` لا على المفتاح الأساسي:
           `LIKE '%..%'` على `id` يُبطل الفهرس ويمسح الجدول كلّه. */
        if ($request->filled('id')) {
            $term = trim((string) $request->input('id'));

            /* وخانةُ البحث تَعِد في نصّها «رقم العرض أو اسم العميل» —
               فيُبحث في اسم العميل ورمزه أيضاً. */
            $query->where(function ($q) use ($term) {
                $like = '%'.$term.'%';

                $q->where('quote_number', 'LIKE', $like)
                    ->orWhereHas('client', function ($client) use ($like) {
                        $client->where('trade_name', 'LIKE', $like)
                            ->orWhere('first_name', 'LIKE', $like)
                            ->orWhere('last_name', 'LIKE', $like)
                            ->orWhere('code', 'LIKE', $like);
                    });

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('status')) {
            /* التعداد نصّ: `(int) 'Pending'` صفرٌ لا يطابق شيئاً.
               وحالةٌ لا يعرفها التعداد لا تُطبَّع إلى «مسوّدة» — `normalizeStatus`
               تفعل ذلك للعرض — وإلّا عرضت الشاشةُ المسوّداتِ لقيمةٍ لا معنى لها. */
            $status = $request->status;
            $known = is_numeric($status)
                ? isset(Quote::STATUSES[(int) $status])
                : in_array((string) $status, Quote::STATUSES, true);

            $known
                ? $query->where('status', Quote::normalizeStatus($status))
                : $query->whereRaw('1 = 0');
        }

        // مسؤول المبيعات — `employee_id` المحفوظ مع المستند، غير «المُنشئ»
        if ($request->filled('sales_person')) {
            $query->where('employee_id', (int) $request->sales_person);
        }

        if ($request->filled('total_from')) {
            $query->where('grand_total', '>=', (float) $request->total_from);
        }

        if ($request->filled('total_to')) {
            $query->where('grand_total', '<=', (float) $request->total_to);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // الفرع من عمود العرض نفسه لا من فرع مُنشئه الحاليّ
        if ($request->filled('branch')) {
            $query->forBranch($request->branch);
        }

        // العملة المخزَّنة مع المستند
        if ($request->filled('currency')) {
            $this->applyCurrencyFilter($query, $this->filterCurrency($request));
        }

        if ($request->filled('item_search')) {
            $term = $request->item_search;

            $query->whereHas('items', function ($q) use ($term) {
                $q->where('item', 'LIKE', '%'.$term.'%')
                    ->orWhere('description', 'LIKE', '%'.$term.'%');
            });
        }

        /* النطاق الافتراضي من الفترة المحاسبية الجارية، لا تاريخ المنشأة
           كلّه. والفلترة على `quote_date` لا `created_at`: المستخدم يسأل
           عن عروضٍ مؤرَّخةٍ في الفترة، لا عن صفوفٍ أُنشئت فيها. */
        $range = $this->reportDateRange($request, 'from_date_1', 'to_date_1');

        $query->whereBetween('quote_date', [
            Carbon::parse($range['start'])->startOfDay(),
            Carbon::parse($range['end'])->endOfDay(),
        ]);

        return [
            'start' => $range['start'],
            'end' => $range['end'],
            'is_default' => $range['is_default'],
        ];
    }

    /**
     * إرسال المستند إلى بريد العميل.
     *
     * ── ما كان ──
     *
     *   • الرابط يُبنى بـ`route('questions.print', ...)` — ومسارٌ بهذا
     *     الاسم لم يكن مسجَّلاً، فكل نداءٍ يرمي `RouteNotFoundException`.
     *     الميزة لم تعمل قطّ.
     *   • ثمّ حين سُجّل المسار، بقي المُرسَل **رابطاً** بقالب بلايد مثبَّت
     *     (`QuoteViewMail`) — لا المستندَ نفسَه، ولا بالقالب الذي صمّمه
     *     المستخدم في «قوالب المستندات».
     *   • وعميلٌ بلا بريد يردّ برسالة `flash.not_found` — «عرض السعر غير
     *     موجود». والعرضُ موجود، والناقصُ بريدُ العميل. فمن قرأها ذهب
     *     يبحث عن مستندٍ لم يضع.
     *   • والزرُّ في الشاشة لم يكن يصل إلى هنا أصلاً: اسمُه «إرسال
     *     بالبريد» وتحته `https://wa.me/…`.
     *
     * ── وما صار ──
     *
     * `SalesDocumentMailer`: المستندُ مصيَّراً من قالبه المختار ومرفقاً
     * PDF، ونصُّ الرسالة من «قوالب البريد»، وبريدُ المستلِم يقبل التعديل
     * في النافذة — فلا يُشترط تعديلُ بطاقة العميل لإرسالٍ واحد.
     */
    public function sendQuoteLink(Request $request, $id)
    {
        $quote = Quote::with('client')->ofType($this->documentKind())->findOrFail($id);

        $this->authorizeQuote($quote, 'view');

        $number = $quote->quote_number ?: $quote->id;

        $response = $this->mailSalesDocument($request, static::DOCUMENT_TYPE, $quote->id, [
            'number' => (string) $number,
            'client' => $this->clientDisplayName($quote->client),
            'email' => $quote->client->email ?? null,
            'client_id' => $quote->client_id,
            'total' => $quote->grand_total,
            'date' => $quote->quote_date,
            'link' => route($this->routeName('print'), $quote->id),
        ], $this->builtInDocumentHtml($quote));

        if ($this->documentMailSucceeded()) {
            OperationLog::record('emailed', static::LOG_MODULE, $quote, $this->dt('log.emailed', [
                'number' => $number,
            ]));
        }

        return $response;
    }

    /**
     * قالبُ الوحدة المدمج نصّاً — احتياطُ المرفق حين لا قالبَ مصمَّماً للنوع.
     *
     * `null` عند أي تعثّر: رسالةٌ بلا مرفق خيرٌ من رسالةٍ لا تخرج.
     */
    protected function builtInDocumentHtml(Quote $quote): ?string
    {
        try {
            return $this->docView('pdf', [
                'quote' => $quote,
                'TaxsInvoice' => $this->documentTaxes($quote->id),
                'qrPayload' => $this->quotePayload($quote),
                'account_setting' => AccountSetting::where('user_id', Auth::id())->first(),
            ])->render();
        } catch (\Throwable $e) {
            OperationLog::failure($e, static::LOG_MODULE, 'email_builtin_html', (int) $quote->id);

            return null;
        }
    }

    /** اسمُ العميل كما يُخاطَب في الرسالة */
    protected function clientDisplayName($client): string
    {
        if (! $client) {
            return '';
        }

        return $client->display_name
            ?? $client->trade_name
            ?? trim(($client->first_name ?? '').' '.($client->last_name ?? ''));
    }

    public function create()
    {
        $user = Auth::user();

        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.quotes.create)

        return $this->docView('create', $this->formData() + [
            'quoteNumber' => $this->previewDocumentNumber(static::NUMBER_SECTION),
            'rows' => old('items', []),
            'defaults' => $this->newDocumentCurrencyAttributes() + [
                'quote_date' => now()->format('Y-m-d'),
                'tax_type' => 1,
                'shipping_tax_id' => null,
                'discount_type' => 'amount',
                'discount_amount' => 0,
                'coupon_code' => null,
                'shipping_cost' => 0,
            ],
            // تنبيه الفترة يُعرَض قبل الملء لا بعد الحفظ
            'periodBlock' => $this->accountingPeriodBlock(now()),
        ]);
    }

    /**
     * قوائم الاختيار المشتركة بين شاشتَي الإنشاء والتعديل.
     *
     * `Product::all()` كانت تُحمّل الجدول كلّه بكل أعمدته لتملأ قائمةً
     * فيها الاسم والسعر.
     */
    protected function formData($keepClientId = null, array $keepProductIds = []): array
    {
        /* المنتجاتُ بعلاقات وحداتها — الأعمدةُ الأربعة السابقة لا تكفي:
           الوحدةُ تأتي من `product_units` أو من وحدات الصنف، وقراءتُها
           تحتاج العلاقتين محمَّلتين مسبقاً لا استعلاماً لكلّ منتج.

           ومقصورةٌ على مستودع البيع كما في شاشة الفاتورة — التحويلُ يصرف
           من `SalesWarehouse::resolve()`، فصنفٌ خارجه لا يُباع منه. */
        $products = SalesWarehouse::productsQuery(ProductUnits::relations(), Auth::user(), $keepProductIds)
            ->orderBy('name')->get();

        return [
            'clients' => $this->scopedClientsForSelect($keepClientId),
            'products' => $products,

            /* مُنتقي «مسؤول المبيعات» — على سلَّم نطاق الرؤية نفسِه الذي
               تقرؤه شاشةُ الفاتورة: الكلّ، أو فرعُه وما تحته، أو هو ومن
               يتبعه إدارياً، أو هو وحدَه. ولم يكن للمستند مُنتقٍ أصلاً رغم
               وجود العمود `employee_id`، فكان `convertToInvoice()` يجده
               فارغاً أبداً ويسقط إلى موظّف من ضغط زرَّ التحويل. */
            'salesPersons' => DataScope::employeeOptions(),

            // وحداتُ كلّ منتج — والقائمةُ لا تظهر لمنتجٍ بلا وحدات
            'productsWithUnits' => ProductUnits::map($products),
            'taxes' => TaxSitting::orderBy('name')->get(['id', 'name', 'tax', 'type']),
            // «إعدادات الفواتير» — تتحكّم بظهور مُنتقي الحالة اليدوية هنا
            'invoiceSettings' => SalesInvoiceSetting::current(),
            'manualStatusEnabled' => $this->manualStatusEnabled(),
            'manualStatuses' => static::MANUAL_STATUSES,
            // ما يحفظه `store()` حين لا تُرسَل حالة — فيختاره المُنتقي
            'defaultStatus' => static::DEFAULT_STATUS,
        ];
    }

    /**
     * عملاء سيلكت العميل — بنفس الخدمة التي تقصر سيلكت العميل على
     * فواتير المبيعات والإشعارات الدائنة والمرتجعات
     * (`InvoicesController`/`CreditNotificationController`/`ReturnInvoiceController`
     * تستدعيها كلُّها)، فلا يفترق عرضُ السعر وأمر البيع عنها بمنطقٍ مكتوبٍ
     * مرّةً خامسة.
     *
     * بلا هذا كان كل عرض سعرٍ أو أمر بيعٍ جديد يعرض العملاء كلَّهم —
     * فمن لا يرى عميلاً في شاشة العملاء كان يراه هنا ويُنشئ له مستنداً.
     *
     * @see \App\Services\Clients\ClientVisibilityScope
     */
    protected function scopedClientsForSelect($keepClientId = null)
    {
        /* محورا النطاق معاً: الصلاحيةُ **وفرعُ الشريط العلويّ**. كانت
           بالصلاحية وحدها، فمن بدّل الشريط إلى فرعٍ بعينه يجد في المُنتقي
           عملاء المنشأة كلِّها. والفرعُ الرئيسيّ يعني المنشأة كلَّها فلا
           يُرشِّح — انظر {@see \App\Support\HeaderBranchScope}. */
        /* وعميلُ المستند المفتوح يبقى خياراً في شاشة التعديل مهما ضاق
           النطاق: مُنتقٍ لا يحوي عميلَ العرض يُعرض فارغاً، فيحفظ المستخدمُ
           فينسب العرضَ إلى عميلٍ آخر أو يردّه التحقّق بلا سبب مفهوم. */
        /* `code` يُجلَب ليظهر بجوار الاسم كما في سيلكت الفاتورة — عملاءُ
           بالاسم نفسِه لا يُميَّزون إلّا به، والبحثُ في select2 يطابقه. */
        return ClientVisibilityScope::applyDocumentSelectScope(
            Client::orderBy('trade_name')->select(['id', 'trade_name', 'first_name', 'last_name', 'code']),
            Auth::user(),
            null,
            $keepClientId,
        )->get();
    }

    /**
     * صلاحية المستخدم على عرضٍ بعينه.
     *
     * كانت مكرّرةً حرفياً في `show` و`edit` و`update` و`destroy`، وغائبةً
     * عن `print` و`downloadPdf` و`convertToInvoice` — فمن يملك أيّ صلاحية
     * عروضٍ كان يحوّل عرضَ غيره إلى فاتورة.
     *
     * @param  string  $ability  view | edit
     */
    private function authorizeQuote(Quote $quote, string $ability = 'view'): void
    {
        /* نطاقُ الجدول نفسُه (`ScopesSalesVisibility`): ما أراه في القائمة
           أفتحه. والدخولُ إلى view/edit محروسٌ أصلاً بـ`sales.quotes.view`
           أو `.edit` في المُنشئ.

           و`employee_id` صار عموداً في `quotes` فعلاً — كان الفحصُ القديم
           يقرؤه وهو غيرُ موجود فيبقى ميّتاً، ثمّ أُضيف مع مُنتقي «مسؤول
           المبيعات». */
        abort_unless(
            $this->canAccessSalesDocument($quote->created_by, $quote->employee_id, $quote->branch_id),
            403,
            $this->dt('ui.no_permission')
        );
    }

    /**
     * حفظ عرض سعر جديد.
     *
     * ── ما تغيّر عن السابق ──
     *
     *   الرقم    كان يُقرأ من `SerialSetting` بلا قفل ثم يُكتب في **المفتاح
     *            الأساسي** (`Quote::create(['id' => $n])`)، ويُفحص تفرّده
     *            على `quotes.id` لا على `quote_number`. طلبان متزامنان
     *            يخرجان بالرقم نفسه. الآن `resolveDocumentNumber()` تحت قفل
     *            صفّ، والمفتاح الأساسي يتولّاه العدّاد التلقائي.
     *
     *   الرقم ٢  كان يُمرَّر باسم `quotes_number` — والعمود اسمه
     *            `quote_number` وليس الاسمُ الخاطئ في `$fillable`، فيسقط
     *            في التعيين الجَماعي **بلا خطأ**. لم يُحفظ رقم عرضٍ قطّ.
     *
     *   الضريبة  كانت تُحسب على المبلغ **قبل** خصم البند، وتُحسب مرّتين في
     *            حلقتين مستقلّتين، ولا تحترم «نسبة صفرية» و«معفى» إلّا في
     *            ضريبة الشحن. الآن حلقةٌ واحدة، والمعاملة تسري على الكلّ.
     *
     *   الفترة   لم تكن تُفحَص: عرضٌ بتاريخٍ في سنةٍ أُقفلت يُحفظ ويُحوَّل
     *            بعدها إلى فاتورة فيُرحَّل قيدٌ إلى فترةٍ مقفلة.
     *
     *   السجلّ   صفٌّ بخمسة أعمدة بيده. الآن `OperationLog` — يكتب في
     *            `logs` و`event_logs` معاً بالحقول كلّها.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.quotes.create)

        $validated = validator($request->all(), $this->rules())->validate();

        /* الضريبة الشاملة على المستند — الخانةُ العلوية `document_tax_id`
           تُفرَض على كلّ بند حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة،
           فتُخفى قوائمُ ضريبة الأسطر. المصدرُ الموثوق الخادمُ لا النموذج. */
        $validated['items'] = DocumentTaxCalculator::comprehensiveItems($validated['items'] ?? [], $request);

        /* المُنتقي وعدٌ لا حارس: `client_id` يصل من النموذج ويُكتب كما هو،
           فيكفي تبديلُ القيمة في المتصفّح لفتح مستندٍ على عميلٍ خارج
           النطاق. */
        if ($clientBlock = $this->clientOutOfScopeBlock($validated['client_id'])) {
            return redirect()->back()->withInput()->with('error', $clientBlock);
        }

        // ** حدُّ اشتراك فوترة ** — قبل أيّ كتابة، بالقياس الحيّ لا باللقطة
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::QUOTES)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        // الفترة تُفحَص قبل فتح المعاملة: لا معنى لبدء عملٍ سيُلغى
        if ($block = $this->accountingPeriodBlock($validated['quote_date'])) {
            return back()->withInput()
                ->with('error', $this->dt('notes.period_blocked', ['reason' => $block]));
        }

        DB::beginTransaction();

        try {
            $totals = $this->calculateTotals($validated);

            if (SalesInvoiceSetting::current()->apply_offers_to_quotes) {
                $totals['items'] = array_merge(
                    $totals['items'],
                    $this->matchingGiftOfferItems($validated['items'], (int) $validated['client_id'])
                );
            }

            $manualStatus = $this->manualStatusEnabled()
                ? $this->resolveManualStatus($request->input('status'))
                : null;

            $quote = Quote::create([
                // نوع المستند — به تُفرَز الشاشتان على الجدول الواحد
                'type' => $this->documentKind(),
                'client_id' => $validated['client_id'],
                // الفرع يُجمَّد مع العرض
                'branch_id' => current_branch_id(),
                'created_by' => $user->id,
                'quote_date' => $validated['quote_date'],
                // أمر البيع لا حقلَ تاريخِ تسليمٍ فيه — فلا يُحفَظ له تاريخ
                'expiry_date' => $this->documentKind() === Quote::TYPE_SALES_ORDER
                    ? null
                    : ($validated['expiry_date'] ?? null),
                'employee_id' => DataScope::clampEmployee($validated['employee_id'] ?? null),
                'payment_terms' => $this->normalizePaymentTerms($validated['terms'] ?? null),
                'quote_number' => $this->resolveDocumentNumber(
                    $request->input('quote_number'),
                    static::NUMBER_SECTION
                ),
                'notes' => $validated['notes'] ?? null,
                'currency' => $this->documentCurrency($request->input('currency')),
                'discount_type' => $totals['discount_type'],
                'discount_amount' => $totals['document_discount_input'],
                'coupon_id' => $totals['coupon_id'],
                'coupon_code' => $totals['coupon_code'],
                'coupon_discount' => $totals['coupon_discount'],
                'shipping_cost' => $totals['shipping_cost'],
                'shipping_tax' => $totals['shipping_tax'],
                'tax_type' => (string) $totals['tax_type'],
                'subtotal' => $totals['subtotal'],
                'total_discount' => $totals['total_discount'],
                'total_tax' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'status' => $manualStatus ?? static::DEFAULT_STATUS,
            ]);

            $this->syncItems($quote, $totals['items'], $totals['tax_rows']);

            OperationLog::record(
                'created',
                static::LOG_MODULE,
                $quote,
                $this->dt('log.created', [
                    'number' => $quote->quote_number,
                    'client' => $quote->client->trade_name ?? '',
                ]),
                ['new' => ['grand_total' => $quote->grand_total, 'status' => $quote->status]]
            );

            DB::commit();

            return redirect()->route($this->routeName('index'))
                ->with('success', $this->withDocumentNotices(
                    $this->dt('flash.created', ['number' => $quote->quote_number])
                ));
        } catch (\Throwable $e) {
            DB::rollback();

            OperationLog::failure($e, static::LOG_MODULE, 'store');

            return back()->withInput()
                ->with('error', $this->dt('flash.save_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * قواعد التحقّق — واحدةٌ للإنشاء والتعديل.
     *
     * كانت نسختين متقاربتين تباعدتا: نسخة `update()` تُسقط `tax_rate` من
     * التحقّق وتجعل كل حقلٍ `nullable`، فيصير تعديلُ عرضٍ بحذف عميله
     * وبنوده عمليةً صحيحة.
     */
    /**
     * مهلةُ السداد كما تُخزَّن — رقمٌ موجبٌ نصّاً، أو `null`.
     *
     * الصفرُ لا يُحفظ صفراً: «فوريّ» ليس مهلةً، وتمييزُه عن «لم يُحدَّد»
     * لا يفيد شاشةً ولا تقريراً. ونظيرُه في شاشة الفاتورة يفعل الشيء
     * نفسَه، فيقرأ {@see \App\Support\PaymentTerms} قيمةً واحدةَ المعنى
     * في الجدولين.
     */
    /**
     * رسالةُ الحفظ ومعها ما وقع في الخادم بعد الضغط على «حفظ».
     *
     * الحسمُ الآليّ (عروض الحسم) وخصمُ الكوبون — وسببُ رفضه — كلُّها تقع
     * بعد أن يترك المستخدمُ الشاشة، فمرورُها صامتةً يجعله يظنّ الإجماليَّ
     * قد اختلّ. وكانت `appliedOffersNotice()` موجودةً في `AppliesDiscountOffers`
     * ولا يناديها متحكّمُ عرض السعر إطلاقاً.
     */
    protected function withDocumentNotices(string $message): string
    {
        return trim(implode(' — ', array_filter([
            $message,
            $this->appliedOffersNotice(),
            $this->couponNotice(),
        ])));
    }

    private function normalizePaymentTerms($days): ?string
    {
        $days = is_numeric($days) ? (int) $days : 0;

        return $days > 0 ? (string) $days : null;
    }

    private function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'quote_date' => 'required|date_format:Y-m-d',
            'expiry_date' => 'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'quote_number' => 'nullable|string|max:50',
            /* مسؤول المبيعات — القيمةُ تُقصّ على النطاق بعد التحقّق
               (`DataScope::clampEmployee()`)، والقاعدةُ هنا تمنع قيمةً
               لا تشير إلى موظّفٍ أصلاً. */
            'employee_id' => 'nullable|exists:employees,id',
            // مهلةُ السداد بالأيام — صفرٌ وفراغٌ سواء: بيعٌ فوريّ
            'terms' => 'nullable|integer|min:0|max:3650',
            'currency' => 'nullable|string|max:8',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.description' => 'nullable|string|max:1000',
            /* `invoice_items.quantity` صار `decimal(15,4)`، فالكسر يُحفَظ
               كما يُكتب. كان `integer` لأنّ العمود كان `INTEGER` — يُبتَر
               الكسرُ فيه في الوضع المتساهل ويرمي `Data truncated` في
               الصارم. والحدّ الأدنى أصغرُ ما يمثّله العمود لا الواحدَ
               الصحيح: من يعرض بالكيلو أو بالساعة يعرض نصفاً وربعاً. */
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            /* الوحدة: القيمة إمّا معرّف صفٍّ في `product_units` وإمّا
               `category_<id>` لوحدةٍ من وحدات الصنف بلا صفٍّ خاصّ بالمنتج
               — ولذلك تعبيرٌ نمطيّ لا `exists` ولا `string` (المعرّف يصل
               رقماً من نداءٍ برمجيّ ونصّاً من المتصفّح).
               و`ProductUnits::resolve()` هي من تتحقّق من وجودها في القاعدة
               وتردّ الاسم والمعامل. */
            'items.*.product_unit_id' => ['nullable', 'regex:/^(\d+|category_\d+)$/'],
            'items.*.conversion_factor' => 'nullable|numeric|min:0.0001',
            'items.*.unit_name' => 'nullable|string|max:100',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:amount,percentage',
            'items.*.tax_1_id' => 'nullable|exists:tax_sittings,id',
            'items.*.tax_2_id' => 'nullable|exists:tax_sittings,id',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_tax_id' => 'nullable|exists:tax_sittings,id',
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            /* رمزُ الكوبون نصٌّ حرّ لا `exists`: رمزٌ خاطئ يُقال سببُه في
               رسالة الحفظ ولا يُسقِط المستند — انظر `AppliesSalesCoupons`. */
            'coupon_code' => 'nullable|string|max:64',
            'tax_type' => 'required|in:1,2,3',
            'notes' => 'nullable|string|max:5000',
        ];
    }

    /**
     * الضريبة التي حُمِّل بها شحنُ عرضٍ محفوظ.
     *
     * `quotes.shipping_tax` يحفظ **المبلغ** لا المعرّف، فإعادة اختيار
     * الضريبة في الشاشة تكون بمطابقة نسبتها — وهو ما وُضعت له
     * `DocumentTaxCalculator::matchByRate()` أصلاً. وإضافة عمودٍ للمعرّف
     * تغييرٌ في القاعدة لا يحتاجه إلّا هذا الحقل الواحد.
     */
    private function storedShippingTaxId($quote): ?int
    {
        $cost = (float) ($quote->shipping_cost ?? 0);
        $tax = (float) ($quote->shipping_tax ?? 0);

        if ($cost <= 0 || $tax <= 0) {
            return null;
        }

        return DocumentTaxCalculator::matchByRate(round($tax / $cost * 100, 2));
    }

    /**
     * حساب المستند كلّه — حلقةٌ واحدة على البنود.
     *
     * كان الحساب في ثلاث حلقات: واحدةٌ تبني البنود من `$validated`،
     * وثانيةٌ تعيد حساب الضريبة من `$request->items` **الخام** (متجاوزةً
     * التحقّق الذي جرى)، وثالثةٌ تبني صفوف `taxs_invoice` من الخام أيضاً.
     * فقيمة `tax_1` المحفوظة مع البند لم تكن هي التي دخلت في الإجمالي.
     *
     * ونسبة الضريبة تُقرأ من `TaxSitting` مرّةً واحدة قبل الحلقة:
     * `TaxSitting::find()` داخلها استعلامٌ لكل بندٍ لكل ضريبة.
     *
     * @return array{items: list<array>, tax_rows: list<array>, coupon_id: int|null, coupon_code: string|null,
     *               coupon_discount: float, subtotal: float, total_discount: float,
     *               document_discount: float, discount_type: string, tax_total: float, tax_type: int,
     *               shipping_cost: float, shipping_tax: float, grand_total: float}
     */
    private function calculateTotals(array $validated): array
    {
        $taxIds = collect($validated['items'])
            ->flatMap(fn ($item) => [$item['tax_1_id'] ?? null, $item['tax_2_id'] ?? null])
            ->filter()
            ->unique();

        $taxes = $taxIds->isEmpty()
            ? collect()
            : TaxSitting::whereIn('id', $taxIds)->get()->keyBy('id');

        /* «تطبيق العروض على عروض الأسعار» — مفتاحٌ واحدٌ للهدايا والحسوم
           معاً، انظر شرحه في `sales_settings`. */
        $offersApply = (bool) SalesInvoiceSetting::current()->apply_offers_to_quotes;

        $taxType = (int) $validated['tax_type'];

        // «نسبة صفرية» و«معفى» تُسقطان الضريبة عن المستند كلّه لا عن شحنه
        // وحده — وهو ما كان السابق يفعله ثم يجمع ضريبة البنود رغم ذلك
        $taxable = $taxType === 1;

        $productNames = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->pluck('name', 'id');

        $subtotal = 0.0;
        $lineDiscounts = 0.0;
        $taxTotal = 0.0;
        // الضريبة المتضمَّنة داخل الأسعار — تُطرح من المجموع فلا تُحسب مرّتين
        $includedTax = 0.0;
        $items = [];
        $taxRows = [];
        // صافي كلّ بندٍ بعد خصمه — عليه يُوزَّع الخصم العامّ
        $lineNets = [];

        foreach ($validated['items'] as $index => $item) {
            // `(float)` لا `(int)`: البترُ هنا كان يُسقط الكسر من
            // الإجماليات المعروضة كما يُسقطه العمود من المحفوظ
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $base = $quantity * $unitPrice;

            $discountType = $item['discount_type'] ?? 'amount';
            $discountInput = (float) ($item['discount'] ?? 0);

            $manualDiscount = $discountType === 'percentage'
                ? $base * $discountInput / 100
                : $discountInput;

            /* عرضُ الحسم المطابق حين لا خصمَ يدويّ — نفس المحرّك الذي
               تعمل به الفاتورة والمرتجع، فلا يعِد العرضُ بما لا يصل إلى
               العميل حين يتحوّل عرضُ السعر إلى فاتورة.

               و«تطبيق العروض على عروض الأسعار» يحكمه: شرحُ المفتاح يقول
               «تسري عروض **الهدايا والخصومات**»، وكان يحكم الهدايا وحدها
               بينما تسري الخصومات مهما أُطفئ. */
            $lineDiscount = $offersApply
                ? $this->lineDiscountWithOffers(
                    $item,
                    $base,
                    $manualDiscount,
                    ! empty($validated['client_id']) ? (int) $validated['client_id'] : null,
                    $index,
                )
                : $manualDiscount;

            /* هل ملأ العرضُ خصماً لم يكتبه المستخدم؟ يُحفَظ عندها **مبلغاً**
               على البند لا يبقى في الإجمالي وحده.
               ═══════════════════════════════════════════════════════════
               كان `'discount' => $discountInput` دائماً — أي ما كتبه
               المستخدم، صفراً حين يأتي الخصمُ من عرض حسم. فالعرضُ يظهر في
               `total` المحفوظ ولا يظهر في العمود الذي تقرؤه
               `convertToInvoice()`: تلك تعيد الحساب من `discount` فتجده
               صفراً، فتخرج الفاتورة بلا الحسم الذي وُعد به العميل — وهو
               بالضبط ما شكا منه المستخدم: «فعّلتُ تطبيق العروض على عروض
               الأسعار… حوّلتُه إلى فاتورة فما طبّقها».
               ونوعُه «مبلغ» لا «نسبة»: القيمة محسوبةٌ أصلاً، وتخزينُها
               نسبةً يعيد تطبيقها عند كلّ قراءة. */
            $offerFilled = $lineDiscount > $manualDiscount;

            // خصمٌ يفوق قيمة البند يقلب إجماليه سالباً
            $lineDiscount = min($lineDiscount, $base);

            $subtotal += $base;
            $lineDiscounts += $lineDiscount;

            /* صافي البند — والضريبةُ تُؤجَّل إلى ما بعد الخصم العامّ.
               ترتيبُ السلسلة: صافي الأصناف ← الخصم العامّ ← صافي التوريد
               ← الشحن ← الوعاء الضريبي. فحسابُ الضريبة هنا يسبق الخصمَ
               العامّ فلا يراه. */
            $lineNets[$index] = max(0.0, $base - $lineDiscount);

            $rates = ['tax_1' => 0.0, 'tax_2' => 0.0];

            foreach (['tax_1', 'tax_2'] as $slot) {
                $taxId = $item[$slot.'_id'] ?? null;
                $tax = $taxId ? $taxes->get($taxId) : null;

                if ($tax) {
                    $rates[$slot] = (float) $tax->tax;
                }
            }

            /* حقولُ الوحدة تُحلّ في الخادم لا تُؤخَذ كما أرسلتها الشاشة:
               الاسمُ والمعامل مصدرُهما `product_units`/`category_unit`،
               وما يصل من المتصفّح يُقبَل تلميحاً لا مصدراً. ونفسُ الصنف
               (`ProductUnits`) يخدم الفاتورة والإشعار الدائن، فلا يفترق
               عرضُ السعر عن الفاتورة التي يتحوّل إليها. */
            $unit = ProductUnits::resolve($item);

            $items[] = [
                'product_id' => $item['product_id'],
                'item' => $productNames[$item['product_id']] ?? '',
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'product_unit_id' => $unit['product_unit_id'],
                'unit_name' => $unit['unit_name'],
                'conversion_factor' => $unit['conversion_factor'],
                // ما يقابل الكمية بالوحدة الأساسية — يُحفظ مع العرض فلا
                // يُعاد حسابه عند التحويل إلى فاتورة، ولا يختلّ المخزون
                'quantity_in_base_unit' => round($quantity * $unit['conversion_factor'], 4),
                'unit_price' => $unitPrice,
                /* القيمة **كما أدخلها المستخدم** لا المبلغ المحسوب:
                   العمود يُقرأ مع `discount_type` في ثلاثة مواضع — إعادة
                   فتح الشاشة، والتحويل إلى فاتورة، والطباعة — وكلّها
                   تعيد تطبيق النسبة. تخزين المبلغ ونوعه «نسبة» يجعل
                   خصم ١٠٪ على ألفٍ يعود خصماً بمئةٍ **بالمئة** عند أوّل
                   إعادة حفظ، فيهوي الإجمالي إلى صفر. */
                'discount' => $offerFilled ? round($lineDiscount, 2) : $discountInput,
                // ١ = مبلغ، ٢ = نسبة — اصطلاح المتحكّم القائم، وهو معكوس
                // تعليق الهجرة (`1=percentage`). لا يُقلَب هنا: القلب يغيّر
                // معنى كل صفٍّ محفوظ من قبل.
                'discount_type' => $offerFilled ? 1 : ($discountType === 'percentage' ? 2 : 1),
                'tax_1' => $rates['tax_1'],
                'tax_1_id' => $item['tax_1_id'] ?? null,
                'tax_2' => $rates['tax_2'],
                'tax_2_id' => $item['tax_2_id'] ?? null,
                'total' => $base - $lineDiscount,
            ];
        }

        /* الأسعار أُدخلت شاملةً الضريبة المتضمَّنة، فلو بقيت داخل المجموع
           لحُسبت مرّتين حين تُضاف `tax_total` إليه: مرّةً داخل السعر ومرّةً
           فوقه. تُطرح هنا فيستقيم السطر المعروض:
           الإجمالي = المجموع − الخصم + الشحن + الضريبة.

           والطرح **قبل** الخصم العامّ لا بعده: نسبةُ خصمٍ على المستند
           تُحسب من المجموع الصافي كما تُحسب في فاتورة البيع وفاتورة
           الشراء. كان يُطرح بعده، فخصمُ ١٠٪ على عرضٍ بضريبةٍ متضمَّنة
           يخرج أكبر ممّا يخرج على الفاتورة نفسها بعد التحويل. */
        $documentDiscountType = $validated['discount_type'] ?? 'amount';
        $documentDiscountInput = (float) ($validated['discount_amount'] ?? 0);

        /* النسبة على **صافي الأصناف** — بعد خصوم البنود، وهو موضعُ الخصم
           العامّ في السلسلة. وكانت على المجموع قبل خصوم البنود، فخصمُ ١٠٪
           يخرج أكبر ممّا يقتضيه الترتيب، ويخالف ما تحسبه شاشة الفاتورة. */
        $netItems = max(0.0, $subtotal - $lineDiscounts);

        $documentDiscount = $documentDiscountType === 'percentage'
            ? $netItems * $documentDiscountInput / 100
            : $documentDiscountInput;

        $documentDiscount = min($documentDiscount, $netItems);

        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

        /* ══ كوبون الخصم ══
           على وعاءِ ما بقي بعد خصوم البنود وخصم المستند، و«الشحن المجانيّ»
           يُصفّر الشحن **قبل** حساب ضريبته لا بعده — وإلّا بقيت ضريبةُ شحنٍ
           أُعفي منه. انظر {@see \App\Services\Sales\SalesCouponService}. */
        $coupon = $this->documentCoupon(
            $validated['coupon_code'] ?? null,
            ! empty($validated['client_id']) ? (int) $validated['client_id'] : null,
            max(0.0, round($subtotal - $lineDiscounts - $documentDiscount, 2)),
            collect($validated['items'])->pluck('product_id')->all(),
            $shippingCost,
        );

        if ($coupon['free_shipping']) {
            $shippingCost = 0.0;
        }

        /* ══════════ الوعاء الضريبي — بعد الخصم العامّ ══════════
         *
         * الخصمُ العامّ (خصم المستند + الكوبون) يُوزَّع على البنود بنسبة
         * صافي كلٍّ منها، ثمّ تُحسب ضريبةُ كلّ بندٍ على ما بقي له. فبندٌ
         * معفى يأخذ حصّته ولا يُنتج ضريبة، وذو نسبةٍ أعلى يُخفَّض وعاؤه
         * بحصّته هو. انظر `DocumentTaxCalculator::allocateDocumentDiscount()`.
         */
        $generalDiscount = min(
            $documentDiscount + $coupon['discount'],
            max(0.0, array_sum($lineNets))
        );

        $discountShares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $generalDiscount);

        foreach ($validated['items'] as $index => $item) {
            $taxBase = max(0.0, ($lineNets[$index] ?? 0.0) - ($discountShares[$index] ?? 0.0));

            foreach (['tax_1', 'tax_2'] as $slot) {
                $taxId = $item[$slot.'_id'] ?? null;
                $tax = $taxId ? $taxes->get($taxId) : null;

                if (! $tax) {
                    continue;
                }

                /* النوع من «إعدادات الضريبة»: المتضمَّنة تُستخرَج من السعر
                   ولا تُضاف فوقه. كان الحساب `الوعاء × النسبة ÷ ١٠٠` دائماً،
                   فعرضُ سعرٍ بضريبةٍ متضمَّنة يخرج بإجماليٍّ أعلى ممّا يراه
                   المستخدم في الشاشة، ويورّث الخطأ للفاتورة عند التحويل. */
                $value = DocumentTaxCalculator::amount($taxBase, (float) $tax->tax, $tax->type === 'included');

                if ($taxable) {
                    $taxTotal += $value;

                    if ($tax->type === 'included') {
                        $includedTax += $value;
                    }
                }

                /* الاسم والنسبة والنوع تُنسَخ عن `TaxSitting` لحظةَ الحفظ:
                   تعديل النسبة في الإعدادات لاحقاً يجب ألّا يعيد كتابة
                   ضريبة مستندٍ صدر بها. */
                $taxRows[] = [
                    'name' => $tax->name,
                    'type' => $tax->type,
                    'rate' => (float) $tax->tax,
                    'value' => $value,
                    'type_invoice' => static::DOCUMENT_TYPE,
                    'product_id' => $item['product_id'],
                ];
            }
        }

        /* الضريبةُ المتضمَّنة تُطرح من المجموع بعد أن عُرفت — وكان الطرحُ
           أعلى الدالّة قبل حسابها، فيقرأ صفراً أبداً. */
        $subtotal = round($subtotal - $includedTax, 2);

        /* ضريبة الشحن يحسبها الخادم كما يحسبها في فاتورة البيع والمرتجع
           والإشعار الدائن: الضريبة المختارة للشحن أوّلاً، فإن لم تُختَر
           فأوّل ضريبةٍ على البنود.

           كانت تُؤخذ مبلغاً يكتبه المستخدم في الشاشة — فيمكن أن يُحفَظ
           عرضٌ بشحنٍ مئةً وضريبةِ شحنٍ خمسين، ولا يوافق ذلك نسبةً مضبوطةً
           في «إعدادات الضريبة»، ولا يوافق ما تحسبه الفاتورة بعد التحويل. */
        $shippingTax = $taxable
            ? DocumentTaxCalculator::shippingTax(
                $shippingCost,
                /* «بلا ضريبة» اختيارٌ لا سكوت: المفتاح موجودٌ في القواعد،
                   فوجودُه في `$validated` يعني أنّ الشاشة أرسلته — وفراغُه
                   يعني ألّا ضريبة على الشحن، لا أن يرث ضريبة البنود */
                DocumentTaxCalculator::shippingTaxIds(
                    array_key_exists('shipping_tax_id', $validated),
                    $validated['shipping_tax_id'] ?? null,
                    $taxIds->all()
                ),
            )
            : 0.0;

        $taxTotal += $shippingTax;

        $totalDiscount = $lineDiscounts + $documentDiscount + $coupon['discount'];

        return [
            'items' => $items,
            'tax_rows' => $taxRows,
            'coupon_id' => $coupon['coupon_id'],
            'coupon_code' => $coupon['code'],
            'coupon_discount' => round($coupon['discount'], 2),
            'subtotal' => round($subtotal, 2),
            'total_discount' => round($totalDiscount, 2),
            // المحسوب للإجماليات، والخام للتخزين — انظر تعليق خصم البند
            'document_discount' => round($documentDiscount, 2),
            'document_discount_input' => $documentDiscountInput,
            'discount_type' => $documentDiscountType,
            'tax_total' => round($taxTotal, 2),
            'tax_type' => $taxType,
            'shipping_cost' => round($shippingCost, 2),
            'shipping_tax' => round($shippingTax, 2),
            'grand_total' => round($subtotal - $totalDiscount + $shippingCost + $taxTotal, 2),
        ];
    }

    /**
     * كتابة البنود وصفوف الضريبة — استبدالٌ كامل، ودُفعةً واحدة.
     *
     * `InvoiceItem::create()` و`TaxInvoice::create()` في حلقة: استعلامٌ
     * لكل بندٍ ولكل ضريبةٍ فيه. عرضٌ بعشرين بنداً كان يُصدر ستّين استعلاماً.
     */
    private function syncItems(Quote $quote, array $items, array $taxRows): void
    {
        $quote->items()->delete();
        TaxInvoice::where('invoice_id', $quote->id)
            ->where('type_invoice', static::DOCUMENT_TYPE)
            ->delete();

        $now = now();

        if ($items) {
            InvoiceItem::insert(array_map(
                fn (array $item) => $item + [
                    'quotation_id' => $quote->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $items
            ));
        }

        if ($taxRows) {
            TaxInvoice::insert(array_map(
                fn (array $row) => $row + [
                    'invoice_id' => $quote->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $taxRows
            ));
        }
    }

    /**
     * الحالة اليدوية عبر `manual_quote_status` — القيمة المُرسَلة إن كانت
     * ضمن القائمة المسموحة، وإلّا `null` فيسري الاشتقاق التلقائي كما كان.
     */
    private function resolveManualStatus(?string $submitted): ?string
    {
        return in_array($submitted, static::MANUAL_STATUSES, true) ? $submitted : null;
    }

    /**
     * عروض الهدايا المطابقة لبنود عرض السعر — عبر `apply_offers_to_quotes`.
     *
     * نفس منطق المطابقة في `InvoicesController::store()` (المنتج المستهدَف
     * والكمية الدنيا وأفضل عرض بأعلى كمية هدية)، لكن بلا تحويل وحدات: بنود
     * عرض السعر لا تحمل `product_unit_id`/`conversion_factor` أصلاً
     * (انظر `rules()`)، فالكمية المُدخَلة هي كمية الوحدة الأساسية نفسها.
     *
     * تُعاد بالشكل الذي ينتجه `calculateTotals()` لكل بند — هو ما يتوقّعه
     * `syncItems()` عند الإدراج المباشر عبر `InvoiceItem::insert()`.
     */
    private function matchingGiftOfferItems(array $items, int $clientId): array
    {
        $giftOffers = GiftOffer::where('is_active', true)
            ->where(function ($q) use ($clientId) {
                $q->where('is_for_all_clients', true)
                    ->orWhereHas('clients', function ($q2) use ($clientId) {
                        $q2->where('client_id', $clientId);
                    });
            })
            ->where(function ($q) {
                $q->where('is_for_all_employees', true)
                    ->orWhereHas('users', function ($q2) {
                        $q2->where('user_id', auth()->id());
                    });
            })
            ->whereDoesntHave('excludedClients', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            })
            /* التاريخ الفارغ مفتوح: شاشة العروض تقبل عرضاً بلا بداية أو بلا
               نهاية وتعرضه «سارياً»، و`whereDate` على NULL يُسقطه فلا يُطبَّق
               أبداً. نظيرُ `OfferDiscountService` في عروض الحسم. */
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
            ->get();

        if ($giftOffers->isEmpty()) {
            return [];
        }

        $giftItems = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = (float) $item['quantity'];

            $validOffers = $giftOffers->filter(function ($offer) use ($productId, $quantity) {
                $matchesTarget = ! $offer->target_product_id || $offer->target_product_id == $productId;

                return $matchesTarget && $quantity >= $offer->min_quantity;
            });

            $bestOffer = $validOffers->sortByDesc('gift_quantity')->first();

            if (! $bestOffer) {
                continue;
            }

            $giftProduct = Product::find($bestOffer->gift_product_id);
            if (! $giftProduct) {
                continue;
            }

            /* `intdiv()` لا تقبل كسراً — والكمية صارت `float` منذ أن قَبِل
               العمودُ الكسور. والهديّة تُحسب بعدد المرّات الكاملة وحدها:
               من اشترى ٢٫٧٥ والعرضُ على كلّ واحدةٍ يأخذ هديّتين لا ٢٫٧٥ */
            $minQuantity = max(1, (int) $bestOffer->min_quantity);
            $giftMultiplier = (int) floor($quantity / $minQuantity);
            $totalGiftQuantity = $bestOffer->gift_quantity * $giftMultiplier;

            /* المفاتيح مطابقةٌ لما ينتجه `calculateTotals()` حرفاً بحرف:
               `syncItems()` يُدرج الصفوف بـ`InvoiceItem::insert()` دفعةً
               واحدة، وصفٌّ ينقصه مفتاحٌ يخرج بأعمدةٍ مزاحة. ولذلك حقول
               الوحدة هنا وإن كانت الهديّةُ دائماً بالوحدة الأساسية. */
            $giftItems[] = [
                'product_id' => $giftProduct->id,
                'item' => $giftProduct->name.' (هدية)',
                'description' => 'هدية عرض عند شراء '.$quantity.' من المنتج',
                'quantity' => $totalGiftQuantity,
                'product_unit_id' => null,
                'unit_name' => '',
                'conversion_factor' => 1,
                'quantity_in_base_unit' => $totalGiftQuantity,
                'unit_price' => 0,
                'discount' => 0,
                'discount_type' => 1,
                'tax_1' => 0,
                'tax_1_id' => null,
                'tax_2' => 0,
                'tax_2_id' => null,
                'total' => 0,
            ];
        }

        return $giftItems;
    }

    public function show($id)
    {
        $quote = Quote::with(['client', 'creator', 'items'])->ofType($this->documentKind())->findOrFail($id);

        $this->authorizeQuote($quote, 'view');

        return $this->docView('show', [
            'quote' => $quote,
            'TaxsInvoice' => $this->documentTaxes($quote->id),
            'logs' => $this->documentLogs($quote->id),
            // قوالب الطباعة المصمَّمة في «قوالب المستندات» لنوع `quote`
            'templates' => $this->salesTemplates(static::DOCUMENT_TYPE),
            'defaultTemplate' => $this->defaultSalesTemplate(static::DOCUMENT_TYPE),
            'prefersPdf' => PrintSettings::prefersPdf(),
            'account_setting' => AccountSetting::where('user_id', Auth::id())->first(),
        ]);
    }

    /** صفوف ضريبة المستند — مقصورةً على نوعه */
    private function documentTaxes($id)
    {
        /* `invoice_id` ليس مفتاحاً أجنبياً بل معرّفُ مستندٍ عامّ يفصله
           `type_invoice` وحده: عرض السعر ٥ والفاتورة ٥ يتصادمان بدونه. */
        return TaxInvoice::where('invoice_id', $id)
            ->where('type_invoice', static::DOCUMENT_TYPE)
            ->get();
    }

    /** الخطّ الزمني للمستند، مجموعاً باليوم */
    private function documentLogs($id)
    {
        return ModelsLog::where('type', static::LOG_MODULE)
            ->where('type_id', $id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($row) => $row->created_at->format('Y-m-d'));
    }

    /**
     * عرض المستند للطباعة.
     *
     * كانت الدالة موجودةً بلا مسارٍ يصل إليها، بينما `sendQuoteLink()`
     * تبني رابطها بـ`route('questions.print')` — فترمي
     * `RouteNotFoundException`. المسار مسجَّلٌ الآن.
     *
     * والقالب: إن كان المستخدم قد صمّم قالباً لعروض الأسعار في
     * «قوالب المستندات» فهو ما يُطبَع؛ وإلّا فقالب الوحدة المدمج.
     */
    public function print($id, $template = null)
    {
        $quote = Quote::with(['client', 'creator', 'items'])->ofType($this->documentKind())->findOrFail($id);

        $this->authorizeQuote($quote, 'view');

        $rendered = $this->renderSalesTemplate(
            static::DOCUMENT_TYPE,
            $quote->id,
            $template ?? request('template') ?? optional($this->defaultSalesTemplate(static::DOCUMENT_TYPE))->id
        );

        if ($rendered !== null) {
            return response(DocumentPrintPage::wrap($rendered));
        }

        return $this->docView('pdf', [
            'quote' => $quote,
            'TaxsInvoice' => $this->documentTaxes($quote->id),
            'qrPayload' => $this->quotePayload($quote),
            'account_setting' => Auth::check()
                ? AccountSetting::where('user_id', Auth::id())->first()
                : null,
        ]);
    }

    /**
     * تنزيل المستند PDF.
     *
     * ── ما كان ──
     *
     *   • `Quote::find()` لا `findOrFail()`: معرّفٌ غير موجود يعطي
     *     «محاولة قراءة خاصّية على null» لا صفحة ٤٠٤.
     *   • بلا أيّ فحص صلاحية: من يملك أيّ صلاحية عروضٍ ينزّل عرض غيره.
     *   • رمز QR يُبنى مرّتين بمكتبتين مختلفتين، وواحدةٌ منهما تُهمَل،
     *     والثالثة تنادي خدمةً خارجية (`api.qrserver.com`) — أي أن بيانات
     *     العميل والمبلغ تُرسَل إلى طرفٍ ثالث عند كل تنزيل.
     *   • «ر.س» و`Y/m/d` نصّاً.
     */
    public function downloadPdf($id)
    {
        $quote = Quote::with(['client', 'creator', 'items'])->ofType($this->documentKind())->findOrFail($id);

        $this->authorizeQuote($quote, 'view');

        $number = $quote->quote_number ?: $quote->id;

        $html = $this->renderSalesTemplate(
            static::DOCUMENT_TYPE,
            $quote->id,
            request('template') ?? optional($this->defaultSalesTemplate(static::DOCUMENT_TYPE))->id
        ) ?? $this->docView('pdf', [
            'quote' => $quote,
            'TaxsInvoice' => $this->documentTaxes($quote->id),
            'account_setting' => AccountSetting::where('user_id', Auth::id())->first(),
            'qrPayload' => $this->quotePayload($quote),
        ])->render();

        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor(config('app.name'));
        $pdf->SetTitle($this->dt('doc.show_title', ['number' => $number]));
        $pdf->SetMargins(15, 15, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->setRTL(\App\Support\LanguageSettings::isRtl());
        $pdf->SetFont('aealarabiya', '', 14);
        $pdf->writeHTML($html, true, false, true, false, '');

        /* الرمز يُرسَم هنا لا في القالب: القالب يرسمه بـ`QRCode.toCanvas`
           — وهي JavaScript، و`writeHTML` لا تُشغّل JavaScript. فكان كل
           PDF يخرج بمربّعٍ فارغ مكان الرمز منذ أن كُتبت الشاشة. */
        $pdf->write2DBarcode(
            $this->quotePayload($quote),
            'QRCODE,M',
            \App\Support\LanguageSettings::isRtl() ? 165 : 15,
            $pdf->GetY() + 4,
            32,
            32,
            ['border' => false, 'padding' => 1],
            'N'
        );

        OperationLog::record('printed', static::LOG_MODULE, $quote, $this->dt('log.printed', [
            'number' => $number,
        ]));

        return $pdf->Output('quote-'.$number.'.pdf', 'I');
    }

    /**
     * محتوى رمز الاستجابة السريعة — بلا نداءٍ خارجي.
     *
     * كان يُبنى برابطٍ إلى `api.qrserver.com`: كل تنزيلٍ يُرسل اسم العميل
     * ومبلغ العرض إلى خدمةٍ خارجية، ويعتمد ظهورُ الرمز على وصول الخادم
     * إليها.
     */
    private function quotePayload(Quote $quote): string
    {
        $client = $quote->client;

        return implode("\n", [
            $this->dt('doc.number').': '.($quote->quote_number ?: $quote->id),
            $this->dt('doc.date').': '.\App\Support\LanguageSettings::formatDate($quote->quote_date),
            $this->dt('doc.client').': '.($client->trade_name ?? trim(($client->first_name ?? '').' '.($client->last_name ?? ''))),
            __('sales_documents.totals.grand_total').': '.CurrencySettings::format($quote->grand_total),
        ]);
    }

    public function edit($id)
    {
        $quote = Quote::with('items')->ofType($this->documentKind())->findOrFail($id);

        $this->authorizeQuote($quote, 'edit');

        /* المستند المُحوَّل إلى فاتورة لا يُعدَّل: الفاتورة تشير إليه،
           وتعديله بعدها يجعل المستندين يرويان روايتين. كان `update()`
           يقبله ثم يعيد حالته إلى «مسوّدة». */
        if ($quote->isLocked()) {
            return redirect()->route($this->routeName('show'), $quote->id)
                ->with('error', $this->dt('ui.action_failed'));
        }

        return $this->docView('edit', $this->formData($quote->client_id, $quote->items->pluck('product_id')->all()) + [
            'quote' => $quote,
            'rows' => old('items', $this->itemRows($quote)),
            'defaults' => $this->documentDefaults($quote),
            'periodBlock' => $this->accountingPeriodBlock($quote->quote_date),
        ]);
    }

    /**
     * بنود العرض بالشكل الذي يقرأه نموذج الشاشة.
     *
     * `discount_type` يعود نصّاً كما يرسله النموذج، لا `1`/`2` كما هو
     * مخزَّن — وإلّا اختار المُنتقي «مبلغ» دائماً عند التعديل.
     */
    private function itemRows(Quote $quote): array
    {
        /* بنودُ الهدايا لا تعود إلى النموذج: `update()` يُعيد حسابها من
           العروض السارية، فبندُ هديّةٍ منقولٌ يُضاف ثانيةً — هديّتان بدل
           واحدة، ومخزونُهما يُصرف معاً عند التحويل إلى فاتورة. */
        return $quote->items->reject(fn ($item) => InvoiceItem::isGiftLine($item))->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            // الوحدةُ تعود كما حُفظت، وإلّا اختار المُنتقي «الوحدة
            // الأساسية» عند كل تعديل فتغيّر الكميةُ معناها
            'product_unit_id' => $item->product_unit_id,
            'conversion_factor' => $item->conversion_factor,
            'unit_name' => $item->unit_name,
            'unit_price' => $item->unit_price,
            'discount' => $item->discount,
            'discount_type' => (int) $item->discount_type === 2 ? 'percentage' : 'amount',
            'tax_1_id' => $item->tax_1_id,
            'tax_2_id' => $item->tax_2_id,
        ])->values()->all();
    }

    /** القيم الابتدائية للنموذج من مستندٍ محفوظ */
    private function documentDefaults(Quote $quote): array
    {
        /* الصفوف القديمة تحمل `1`/`2` في `quotes.discount_type` رغم أنّه
           عمود نصّي — تُترجَم هنا فلا تحتاج ترحيلاً. */
        $discountType = in_array($quote->discount_type, ['amount', 'percentage'], true)
            ? $quote->discount_type
            : ((string) $quote->discount_type === '2' ? 'percentage' : 'amount');

        return [
            'client_id' => $quote->client_id,
            'quote_number' => $quote->quote_number,
            'quote_date' => optional($quote->quote_date)->format('Y-m-d'),
            'expiry_date' => optional($quote->expiry_date)->format('Y-m-d'),
            'employee_id' => $quote->employee_id,
            'terms' => $quote->payment_terms,
            'notes' => $quote->notes,
            'currency' => $this->documentCurrency($quote),
            'tax_type' => (int) ($quote->tax_type ?: 1),
            'discount_type' => $discountType,
            'discount_amount' => $quote->discount_amount,
            // الرمزُ كما طُبِّق — لا يُعاد فحصُه عند فتح الشاشة
            'coupon_code' => $quote->coupon_code,
            'shipping_cost' => $quote->shipping_cost,
            'shipping_tax_id' => $this->storedShippingTaxId($quote),
        ];
    }

    /**
     * تحديث عرض سعر.
     *
     * ── ما كان ──
     *
     *   • `$tax_total = $amount_after_discount * 0.15` — نسبةٌ مثبَّتة في
     *     الشيفرة تتجاهل `tax_rate` الذي يقرأه `store()` وتتجاهل
     *     `TaxSitting` كلّه. عرضٌ يُحفظ بضريبة ٥٪ ثم يُعدَّل تعديلاً لا
     *     يمسّ الضريبة يخرج بـ١٥٪.
     *   • `'status' => 1` بلا شرط: تعديل عرضٍ حُوِّل إلى فاتورة يعيده
     *     «مسوّدة» بينما الفاتورة قائمة تشير إليه.
     *   • كل قاعدة تحقّقٍ `nullable`: حفظٌ بلا عميلٍ ولا بنود عمليةٌ صحيحة.
     */
    public function update(Request $request, $id)
    {
        $quote = Quote::ofType($this->documentKind())->findOrFail($id);

        $this->authorizeQuote($quote, 'edit');

        if ($quote->isLocked()) {
            return redirect()->route($this->routeName('show'), $quote->id)
                ->with('error', $this->dt('ui.action_failed'));
        }

        $validated = validator($request->all(), $this->rules())->validate();

        /* الضريبة الشاملة على المستند — كما في `store()` أعلاه. */
        $validated['items'] = DocumentTaxCalculator::comprehensiveItems($validated['items'] ?? [], $request);

        /* نقلُ المستند إلى عميلٍ خارج النطاق يُردّ كما يُردّ إنشاؤه عليه،
           وعميلُه المحفوظ يبقى مقبولاً ولو خرج عن النطاق بعد حفظه. */
        if ($clientBlock = $this->clientOutOfScopeBlock($validated['client_id'], $quote->client_id)) {
            return redirect()->back()->withInput()->with('error', $clientBlock);
        }

        // التاريخان يُفحصان: القديم لأنّ الصفّ يُنقَل منه، والجديد لأنّه يُنقَل إليه
        foreach ([$quote->quote_date, $validated['quote_date']] as $date) {
            if ($block = $this->accountingPeriodBlock($date)) {
                return back()->withInput()
                    ->with('error', $this->dt('notes.period_blocked', ['reason' => $block]));
            }
        }

        DB::beginTransaction();

        try {
            $before = $quote->only(['grand_total', 'total_tax', 'total_discount', 'status']);

            $totals = $this->calculateTotals($validated);

            if (SalesInvoiceSetting::current()->apply_offers_to_quotes) {
                $totals['items'] = array_merge(
                    $totals['items'],
                    $this->matchingGiftOfferItems($validated['items'], (int) $validated['client_id'])
                );
            }

            $manualStatus = $this->manualStatusEnabled()
                ? $this->resolveManualStatus($request->input('status'))
                : null;

            $quote->update([
                'client_id' => $validated['client_id'],
                'quote_date' => $validated['quote_date'],
                // الحقلُ مخفيّ في أمر البيع — فيبقى ما حُفظ قديماً ولا يُمسح بالتعديل
                'expiry_date' => $this->documentKind() === Quote::TYPE_SALES_ORDER
                    ? $quote->expiry_date
                    : ($validated['expiry_date'] ?? null),
                'employee_id' => DataScope::clampEmployee($validated['employee_id'] ?? null),
                'payment_terms' => $this->normalizePaymentTerms($validated['terms'] ?? null),
                /* رقمُه هو نفسه ليس تعارضاً.
                   `resolveDocumentNumber()` تفحص الحجز على الجدول كلّه —
                   والصفُّ المُعدَّل من الجدول — فكانت تعتبر رقمه محجوزاً
                   وتسلّم التالي: رقمٌ جديد عند كل حفظ، وثغرةٌ في التسلسل
                   عند كل تعديل، ورابطٌ مرسَلٌ للعميل يشير إلى رقمٍ زال. */
                'quote_number' => $this->resolveEditedNumber($quote, $request->input('quote_number')),
                'notes' => $validated['notes'] ?? null,
                'discount_type' => $totals['discount_type'],
                'discount_amount' => $totals['document_discount_input'],
                'coupon_id' => $totals['coupon_id'],
                'coupon_code' => $totals['coupon_code'],
                'coupon_discount' => $totals['coupon_discount'],
                'shipping_cost' => $totals['shipping_cost'],
                'shipping_tax' => $totals['shipping_tax'],
                'tax_type' => (string) $totals['tax_type'],
                'subtotal' => $totals['subtotal'],
                'total_discount' => $totals['total_discount'],
                'total_tax' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                // الحالة لا تُمَسّ افتراضياً: التعديل ليس إرجاعاً إلى المسوّدة.
                // تُمَسّ فقط حين تُفعَّل `manual_quote_status` ويُرسَل اختيارٌ صريح
                ...($manualStatus !== null ? ['status' => $manualStatus] : []),
            ]);

            $this->syncItems($quote, $totals['items'], $totals['tax_rows']);

            OperationLog::record(
                'updated',
                static::LOG_MODULE,
                $quote,
                $this->dt('log.updated', ['number' => $quote->quote_number]),
                ['old' => $before, 'new' => $quote->only(array_keys($before))]
            );

            DB::commit();

            return redirect()->route($this->routeName('index'))
                ->with('success', $this->withDocumentNotices(
                    $this->dt('flash.updated', ['number' => $quote->quote_number])
                ));
        } catch (\Throwable $e) {
            DB::rollback();

            OperationLog::failure($e, static::LOG_MODULE, 'update', $quote->id);

            return back()->withInput()
                ->with('error', $this->dt('flash.save_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * رقم العرض عند التعديل.
     *
     * ما لم يغيّره المستخدم يبقى كما هو: الصفّ يحمل رقمه، وفحصُ الحجز
     * على الجدول يراه فيظنّه تعارضاً. وما غيّره يمرّ على الفحص العادي.
     */
    private function resolveEditedNumber(Quote $quote, ?string $submitted): string
    {
        $submitted = trim((string) $submitted);

        if ($submitted === '' || $submitted === (string) $quote->quote_number) {
            return (string) $quote->quote_number;
        }

        return $this->resolveDocumentNumber($submitted, static::NUMBER_SECTION);
    }

    /**
     * حذف عرض سعر.
     *
     * كان يحذف بلا أثرٍ في السجلّ ويترك بنوده في `invoice_items` وصفوف
     * ضريبته في `taxs_invoice` — لا مفتاح أجنبي متتالياً عليهما، فتبقى
     * يتيمةً تُحسب في التقارير.
     */
    public function destroy($id)
    {
        $quote = Quote::ofType($this->documentKind())->findOrFail($id);

        $this->authorizeQuote($quote, 'edit');

        if ($quote->isConverted()) {
            return back()->with('error', $this->dt('ui.action_failed'));
        }

        if ($block = $this->accountingPeriodBlock($quote->quote_date)) {
            return back()->with('error', $this->dt('notes.period_blocked', ['reason' => $block]));
        }

        $number = $quote->quote_number ?: $quote->id;

        DB::transaction(function () use ($quote) {
            $quote->items()->delete();

            TaxInvoice::where('invoice_id', $quote->id)
                ->where('type_invoice', static::DOCUMENT_TYPE)
                ->delete();

            $quote->delete();
        });

        OperationLog::record('deleted', static::LOG_MODULE, null, $this->dt('log.deleted', [
            'number' => $number,
        ]));

        return redirect()->route($this->routeName('index'))
            ->with('success', $this->dt('flash.deleted', ['number' => $number]));
    }

    /**
     * تحويل عرض السعر إلى فاتورة مبيعات.
     *
     * ── ما كان ──
     *
     *   • **بلا أيّ فحص صلاحية**: بقيّة الدوالّ تفحص «كلّ العروض» أو
     *     «عروضي»، وهذه لا تفحص شيئاً — فمن يملك صلاحيةً واحدة من ستٍّ
     *     كان يحوّل عرض غيره إلى فاتورة، وهي عمليةٌ تخصم مخزوناً وتُرحّل
     *     قيداً وتُنشئ مديونيةً على عميل.
     *
     *   • **بلا فحص تكرار**: عرضٌ حُوِّل يُحوَّل ثانيةً وثالثة، فتخرج
     *     فاتورتان ببنودٍ واحدة ويُخصَم المخزون مرّتين.
     *
     *   • **رقم الفاتورة قبل المعاملة**: `Invoice::orderBy('id','desc')`
     *     ثم `+1` ثم حلقة فحص — كلّه خارج `beginTransaction()` وبلا قفل
     *     صفّ، فتحويلان متزامنان يخرجان بالرقم نفسه. وهو أيضاً مسارٌ
     *     ثالثٌ للترقيم يتجاهل «الترقيم المتسلسل» الذي تقرأه شاشة
     *     الفواتير.
     *
     *   • **الفترة المحاسبية** لم تكن تُفحَص رغم أنّ العملية تُرحّل قيداً.
     */
    /**
     * خصمُ بندٍ محوَّل — بما فيه حسمُ العرض الذي طُبِّق على المستند الأصل.
     *
     * ═══════════════════ لماذا ليست قسمةً بسيطة ═══════════════════
     *
     * `convertToInvoice()` كانت تقرأ `discount` و`discount_type` وحدهما.
     * وحتى وقتٍ قريب لم يكن عمودُ `discount` يحمل حسمَ **عرض الحسم** أصلاً:
     * `calculateTotals()` تحفظ فيه ما كتبه المستخدم (صفراً حين يأتي الحسمُ
     * من عرض)، بينما يظهر الحسم في `total` المحفوظ وحده. فالعرضُ يُرى في
     * إجمالي عرض السعر المطبوع، ثم تخرج الفاتورةُ المحوَّلة بلا حسم —
     * وهي شكوى المستخدم حرفياً: «فعّلتُ تطبيق العروض على عروض الأسعار،
     * وسوّيتُ عرض سعرٍ وحوّلتُه إلى فاتورة فما طبّقها».
     *
     * صار العمودُ يُملأ عند الحفظ، لكنّ العروضَ المحفوظة قبل ذلك التصحيح
     * لا تزال في القاعدة. فالفرقُ بين `الكمية × السعر` و`total` المحفوظ هو
     * الحسمُ كما رآه العميل يوم وافق — ويُستردّ منه بلا حاجةٍ إلى أن يكون
     * العرضُ ما زال سارياً اليوم. وهو الأصحّ: الفاتورةُ تنفّذ ما اتُّفق
     * عليه، لا ما تقوله شاشةُ العروض الآن.
     */
    private function convertedLineDiscount($item, float $lineTotal): float
    {
        $discount = 0.0;

        if ($item->discount > 0) {
            $discount = ($item->discount_type == 2 || $item->discount_type === 'percentage')
                ? ($lineTotal * floatval($item->discount)) / 100
                : floatval($item->discount);
        }

        if ($discount <= 0) {
            $storedTotal = (float) ($item->total ?? 0);

            if ($storedTotal > 0 && $storedTotal < $lineTotal) {
                $discount = round($lineTotal - $storedTotal, 2);
            }
        }

        // خصمٌ يفوق قيمة البند يقلب إجماليه سالباً
        return min(max(0.0, $discount), max(0.0, $lineTotal));
    }

    public function convertToInvoice($id)
    {
        try {
            $quote = Quote::with(['client', 'items'])->ofType($this->documentKind())->findOrFail($id);

            $this->authorizeQuote($quote, 'edit');

            /* المنتهي لا يُحوَّل — وهو معنى انتهاء الصلاحية أصلاً: أسعارٌ
               مضى وقتُها لا تصير فاتورة بضغطة، وقد تبدّلت التكلفة من
               يومها. ومن أراد إتمامه مدّ تاريخه وأعاد حالته، فيبقى
               التحويل قراراً لا سهواً. */
            if ($quote->blocksConversion()) {
                return back()->with('error', $quote->isExpired()
                    ? $this->dt('notes.expired_no_convert')
                    : $this->dt('ui.action_failed'));
            }

            if ($block = $this->accountingPeriodBlock(now())) {
                return back()->with('error', $this->dt('notes.period_blocked', ['reason' => $block]));
            }

            /* تسميةُ المستند ورقمُه المعروض.
             *
             * كان كلُّ نصٍّ في هذه الدالّة يقول «عرض سعر» ويطبع `$quote->id`.
             * والدالّةُ نفسُها تخدم **أمرَ البيع** بالوراثة، فكلُّ فاتورةٍ
             * محوَّلةٍ عن أمر بيع تحمل في ملاحظاتها وقيدها وسجلّها ورسالة
             * تِلِغرام أنّها «من عرض سعر». والمعرّفُ الرقميُّ ليس ما يراه
             * المستخدم — المستند معروضٌ برقمه (`quote_number`) في كل شاشة. */
            $sourceLabel = $this->dt('doc.singular');
            $sourceNumber = $quote->quote_number ?: $quote->id;
            $sourceRef = $sourceLabel.' رقم: '.$sourceNumber;

            DB::beginTransaction();

            /* داخل المعاملة وتحت قفل صفّ، ومن قسم `invoice` نفسه الذي
               تقرأه شاشة الفواتير — فلا يتفرّع تسلسلان للفواتير */
            $code = $this->resolveDocumentNumber(null, static::INVOICE_NUMBER_SECTION);

            $user = Auth::user();

            // جلب جميع المنتجات المطلوبة دفعة واحدة
            $productIds = $quote->items->pluck('product_id')->unique()->filter()->toArray();
            $productsCollection = Product::whereIn('id', $productIds)->get()->keyBy('id');

            // تحميل CompiledProducts للمنتجات المجمعة
            $compiledProductsMap = CompiledProducts::whereIn('compile_id', $productIds)->get()->groupBy('compile_id');

            /* المستودع الافتراضي من «إعدادات الفواتير»: مستودعُ الموظّف
               أو الرئيسي. الفاتورة المُحوَّلة عن عرض السعر تخرج من حيث
               تخرج الفاتورة المكتوبة مباشرةً — لا من مصدرٍ آخر. */
            $defaultStoreHouse = SalesWarehouse::resolve(null, $user);

            if (! $defaultStoreHouse) {
                throw new \Exception('لا يوجد أي مستودع في النظام. الرجاء إضافة مستودع واحد على الأقل.');
            }

            /* كانت هنا خزينةٌ تُحسب بثلاثة استعلامات ولا تُقرأ بعدها في
               الدالة كلّها — فحُذفت. حين يُضاف سند القبض التلقائي عند
               التحويل تُقرأ من «الحسابات المحاسبية»:
               `$this->accountingAccount('main_treasury', SystemAccountIds::MAIN_TREASURY)`. */

            // ** تجهيز المتغيرات الرئيسية لحساب الفاتورة **
            $total_amount = 0;
            $total_discount = 0;
            $items_data = [];
            $storeHouse = $defaultStoreHouse;
            $store_house_id = $storeHouse->id;

            /* الضريبةُ تنتقل كما حُفظت على العرض: إن كانت الضريبة الشاملة
               مفعَّلةً يومَ حُفظ العرض فبنودُه تحمل ما اختِير في خانته
               العلوية، وإلّا فضريبةُ كلّ سطر. فلا يُعاد فرضُها هنا. */

            // ** معالجة البنود من عرض السعر **
            foreach ($quote->items as $item) {
                $product = $productsCollection->get($item->product_id);
                if (! $product) {
                    throw new \Exception('المنتج غير موجود: '.$item->product_id);
                }

                $quantity = floatval($item->quantity);
                $unit_price = floatval($item->unit_price);
                $item_total = $quantity * $unit_price;

                // حساب الخصم للبند
                $item_discount = $this->convertedLineDiscount($item, $item_total);

                $total_amount += $item_total;
                $total_discount += $item_discount;

                $items_data[] = [
                    'invoice_id' => null,
                    'product_id' => $item->product_id,
                    'store_house_id' => $store_house_id,
                    'item' => $product->name ?? 'المنتج '.$item->product_id,
                    'description' => $item->description ?? null,
                    'quantity' => $quantity,
                    /* الوحدة تنتقل مع البند: عرضٌ بالكرتون يصير فاتورةً
                       بالكرتون، ويُخصَم المخزون بما يقابله بالوحدة
                       الأساسية. بدونها كانت الفاتورة تقرأ الكمية حبّاتٍ. */
                    'product_unit_id' => $item->product_unit_id,
                    'unit_name' => $item->unit_name,
                    'conversion_factor' => (float) ($item->conversion_factor ?: 1),
                    'quantity_in_base_unit' => (float) ($item->quantity_in_base_unit
                        ?: $quantity * (float) ($item->conversion_factor ?: 1)),
                    'unit_price' => $unit_price,
                    'discount' => $item_discount,
                    /* **قيمةً دائماً** لا نوعَ خصم البند الأصل:
                       `$item_discount` مبلغٌ محسوبٌ فوقه، فحفظُه بنوع
                       «نسبة» يجعل خصمَ ١٥٠ على بندٍ بألفٍ يُقرأ ١٥٠٪ عند
                       أوّل إعادة حفظٍ للفاتورة. وهو الاصطلاح نفسُه المتّبع
                       في نسخ الفاتورة (`InvoicesController::create()`). */
                    'discount_type' => 1,
                    'tax_1' => floatval($item->tax_1 ?? 0),
                    'tax_2' => floatval($item->tax_2 ?? 0),
                    'total' => $item_total - $item_discount,
                ];
            }

            /* ** الخصم العامّ على المستند ** — نسبتُه من **صافي الأصناف**
               كما تحسبها `calculateTotals()` و`InvoicesController::store()`.
               كانت من المجموع قبل خصوم البنود، فتخرج أكبر ممّا عُرض. */
            $net_items = max(0.0, $total_amount - $total_discount);

            $invoice_discount = 0;
            if ($quote->discount_amount > 0) {
                if ($quote->discount_type == 2 || $quote->discount_type == 'percentage') {
                    $invoice_discount = ($net_items * floatval($quote->discount_amount)) / 100;
                } else {
                    $invoice_discount = floatval($quote->discount_amount);
                }

                $invoice_discount = min($invoice_discount, $net_items);
            }

            /* خصمُ الكوبون ينتقل **كما جُمِّد** على عرض السعر لا يُعاد فحصُه:
               كوبونٌ بلغ سقفَه بين الاتفاق والتحويل يجب ألّا يرفع الفاتورةَ
               عن الإجمالي الذي وقّع عليه العميل. */
            $coupon_discount = round((float) ($quote->coupon_discount ?? 0), 2);

            $final_total_discount = $total_discount + $invoice_discount + $coupon_discount;
            $amount_after_discount = $total_amount - $final_total_discount;

            /* ** حساب الضرائب ** — على الوعاء بعد **الخصم العامّ** أيضاً.
             *
             * ترتيبُ السلسلة: صافي الأصناف ← الخصم العامّ ← صافي التوريد
             * ← الشحن ← الوعاء الضريبي. فالخصمُ العامّ (خصمُ المستند
             * وخصمُ الكوبون) يُوزَّع على البنود بنسبة صافي كلٍّ منها، ثمّ
             * تُحسب ضريبةُ كلّ بندٍ على ما بقي له — كما تفعل `calculateTotals()`
             * و`InvoicesController::store()` حرفاً بحرف، وإلّا خرجت
             * الفاتورةُ المحوَّلة بإجماليٍّ يخالف العرض الذي وافق عليه العميل.
             */
            $lineNets = [];

            foreach ($quote->items as $index => $item) {
                $lineBase = floatval($item->quantity) * floatval($item->unit_price);
                $lineNets[$index] = max(0.0, $lineBase - $this->convertedLineDiscount($item, $lineBase));
            }

            $generalDiscount = min(
                $invoice_discount + $coupon_discount,
                max(0.0, array_sum($lineNets))
            );

            $discountShares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $generalDiscount);

            $tax_total = 0;
            $included_item_tax = 0; // الضريبة المتضمَّنة داخل الأسعار

            foreach ($quote->items as $index => $item) {
                $taxableBase = max(0.0, ($lineNets[$index] ?? 0.0) - ($discountShares[$index] ?? 0.0));

                /* النوع من «إعدادات الضريبة» كما في `store()` — وإلّا خرجت
                   الفاتورة المحوَّلة بإجماليٍّ يخالف عرض السعر الذي وافق
                   عليه العميل. */
                $line = DocumentTaxCalculator::line(
                    1.0,
                    $taxableBase,
                    0.0,
                    [$item->tax_1_id ?? null, $item->tax_2_id ?? null],
                    [floatval($item->tax_1 ?? 0), floatval($item->tax_2 ?? 0)],
                );

                $tax_total += $line['tax'];
                $included_item_tax += $line['base'] - $line['net'];
            }

            $tax_total = round($tax_total, 2);
            $included_item_tax = round($included_item_tax, 2);

            /* الضريبة المتضمَّنة تُطرح من المجموع فلا تُحسب مرّتين —
               و`amount_after_discount` تُعاد بعدها لأنّها حُسبت قبل
               معرفة مقدارها */
            $total_amount = round($total_amount - $included_item_tax, 2);
            $amount_after_discount = round($total_amount - $final_total_discount, 2);

            // تكلفة الشحن وضريبتها
            $shipping_cost = floatval($quote->shipping_cost ?? 0);

            /* ضريبة الشحن كما حُفظت مع العرض. كانت `* 0.15` مثبَّتة —
               فالفاتورة تخالف العرض المعروض على العميل في كل منشأةٍ
               نسبتها غير خمسة عشر بالمئة. */
            $shipping_tax = $quote->tax_type == 1 ? floatval($quote->shipping_tax ?? 0) : 0.0;
            $tax_total += $shipping_tax;

            // الإجمالي الكلي
            $total_with_tax = $amount_after_discount + $tax_total + $shipping_cost;

            // الحالة والمدفوعات
            $advance_payment = 0;
            $due_value = $total_with_tax - $advance_payment;
            $payment_status = 3; // مسودة
            $is_paid = false;

            // التحقق من الحد الائتماني
            $clientAccount = Account::ledger()->where('client_id', $quote->client_id)->first();
            $client = Client::find($quote->client_id);

            if (in_array($payment_status, [2, 3, 4])) {
                $currentDebt = $clientAccount ? $clientAccount->balance : 0;
                $totalDebtAfterPayment = $currentDebt + $total_with_tax - $advance_payment;

                $clientCreditLimit = CreditLimit::getClientCreditLimit($quote->client_id);
                $creditLimitValue = $clientCreditLimit ? $clientCreditLimit->value : 0;

                if ($creditLimitValue > 0 && $totalDebtAfterPayment > $creditLimitValue) {
                    DB::rollBack();

                    /* المبالغ عبر «العملات»: كانت `number_format(.., 2)`
                       ثم رمزٌ مثبَّت في الشيفرة — كسرٌ وفاصلةٌ ورمز */
                    return redirect()->back()->with('error', sprintf(
                        '⚠️ عفوا، لقد تجاوز العميل الحد الائتماني المسموح به!
الحد الائتماني: %s
المديونية الحالية: %s
قيمة الفاتورة: %s
الرجاء سداد المديونية أولًا أو زيادة الحد الائتماني للعميل.',
                        CurrencySettings::format($creditLimitValue),
                        CurrencySettings::format($currentDebt),
                        CurrencySettings::format($total_with_tax)
                    ));
                }
            }

            // ** إنشاء الفاتورة **
            $invoice = Invoice::create([
                'client_id' => $quote->client_id,
                /* الفرع **من المستند الأصل** لا من الهيدر.
                 *
                 * لم يكن يُختَم إطلاقاً: `InvoicesController::store()` يكتب
                 * `current_branch_id()` منذ حين، و`QuoteController::store()`
                 * يختم فرعَ العرض، أمّا التحويل فيترك `invoices.branch_id`
                 * فارغاً. وأثرُه أنّ الفاتورة تسقط من كل تصفيةٍ بالفرع
                 * (`scopeForBranch`) ومن لوحات الفروع وتقاريرها.
                 *
                 * والأصلُ أولى من الهيدر: العرض جُمِّد بفرعه يوم كُتب، ومن
                 * يحوّله قد يكون مبدّلاً فرعَه النشط وقتها. و`current_branch_id()`
                 * احتياطٌ لعروضٍ سبقت عمود الفرع. */
                'branch_id' => $quote->branch_id ?: current_branch_id(),
                'employee_id' => $quote->employee_id ?? auth()->user()->employee_id,
                'due_value' => $due_value,
                'code' => $code,
                'type' => 'normal',
                'invoice_date' => now()->format('Y-m-d'),
                'issue_date' => now()->format('Y-m-d'),
                /* مهلةُ السداد تنتقل مع المستند.

                   كانت `'terms' => 0` — ومفتاحٌ لا عمودَ له في `invoices`
                   يُسقطه `$fillable` بصمت، فلا هي صفرٌ ولا هي المهلة
                   المتّفق عليها في العرض: كان المستخدم يُعيد كتابتها في
                   الفاتورة أو تضيع. والعمودُ الصحيح `payment_terms`. */
                'payment_terms' => $quote->payment_terms ?: null,
                // `copy_notes_on_conversion`: نسخ ملاحظات عرض السعر إلى الفاتورة،
                // أو الاقتصار على إشارة التحويل وحدها حين تُعطَّل
                'notes' => SalesInvoiceSetting::current()->copy_notes_on_conversion
                    ? trim(($quote->notes ? $quote->notes."\n" : '').'[تم التحويل من '.$sourceRef.']')
                    : '[تم التحويل من '.$sourceRef.']',
                'payment_status' => $payment_status,
                'is_paid' => $is_paid,
                'created_by' => Auth::id(),
                /* `discount_amount` هنا مبلغٌ **محسوب** (خصوم البنود
                   والمستند مجموعةً)، فنوعه «مبلغ» لا نوع خصم العرض:
                   تمرير `'percentage'` نصّاً إلى عمودٍ رقمي يرمي
                   `Incorrect integer value` ويُلغي التحويل كلّه. */
                'discount_amount' => $final_total_discount,
                'discount_type' => 1,
                /* الكوبون ينتقل مع المستند: الرمزُ يُطبَع على الفاتورة
                   والمبلغُ داخلٌ أصلاً في `discount_amount` أعلاه. */
                'coupon_id' => $quote->coupon_id,
                'coupon_code' => $quote->coupon_code,
                'coupon_discount' => $coupon_discount,
                'advance_payment' => $advance_payment,
                'payment_type' => 1,
                'shipping_cost' => $shipping_cost,
                'shipping_tax' => $shipping_tax,
                'tax_type' => $quote->tax_type ?? 1,
                'subtotal' => $total_amount,
                'tax_total' => $tax_total,
                'grand_total' => $total_with_tax,
                /* المستندُ الأصل. كان يُمرَّر منذ كُتبت الدالّة والعمودُ لا
                   وجودَ له في `invoices` ولا المفتاحُ في `$fillable`،
                   فيُسقَط بصمت — والأثرُ الوحيد جملةٌ في `notes`. */
                'quote_id' => $quote->id,
            ]);

            $invoice->qrcode = $this->generateTlvContent($invoice->created_at, $invoice->grand_total, $invoice->tax_total);
            $invoice->save();

            // تعليم زيارة اليوم مكتملة — لا «آخر زيارة» بلا تقييدٍ بالتاريخ
            if ($client) {
                EmployeeClientVisit::markCompletedFor(auth()->id(), $client->id);
            }

            // نسخ الضرائب من عرض السعر إلى الفاتورة
            $quoteTaxes = TaxInvoice::where('invoice_id', $quote->id)->where('type_invoice', 'quote')->get();
            foreach ($quoteTaxes as $tax) {
                TaxInvoice::create([
                    'name' => $tax->name,
                    'invoice_id' => $invoice->id,
                    'type' => $tax->type,
                    'rate' => $tax->rate,
                    'value' => $tax->value,
                    'type_invoice' => 'invoice',
                    'product_id' => $tax->product_id,
                ]);
            }

            // جلب مصدر إذن المخزون
            $permissionSource = PermissionSource::find(PermissionSources::SALES_INVOICE);
            if (! $permissionSource) {
                throw new \Exception("مصدر إذن 'فاتورة مبيعات' غير موجود في قاعدة البيانات.");
            }

            /* أرصدةُ البنود ومكوّناتِ المجمَّعات — مقفلةً حتى نهاية المعاملة،
               كما في `InvoicesController::store()`: بلا قفلٍ يحوّل موظّفان
               أمرَين على آخر الرصيد معاً، وبلا المكوّنات يُفتح للمكوّن صفٌّ
               جديدٌ بصفر بدل رصيده الحقيقي. */
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

            $logsToInsert = [];
            $notificationsToInsert = [];

            // ** إنشاء سجلات البنود وتحديث المخزون **
            foreach ($items_data as $item) {
                $item['invoice_id'] = $invoice->id;
                $item_invoice = InvoiceItem::create($item);

                $product = $productsCollection->get($item['product_id']);
                $productName = $product ? $product->name : 'منتج غير معروف';

                $logsToInsert[] = [
                    'type' => 'sales',
                    'type_id' => $invoice->id,
                    'type_log' => 'log',
                    'icon' => 'create',
                    'description' => sprintf(
                        'تم تحويل %s رقم **%s** إلى فاتورة مبيعات رقم **%s** للمنتج **%s** كمية **%s** بسعر **%s** للعميل **%s**',
                        $sourceLabel,
                        $sourceNumber,
                        $invoice->code ?? '',
                        $productName,
                        $item['quantity'] ?? '',
                        $item['unit_price'] ?? '',
                        $client->trade_name ?? ''
                    ),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                /* المخزون يُخصَم بالوحدة الأساسية لا بوحدة البند.
                   صار عرضُ السعر يحمل وحدةً، فبندٌ بكرتونين ينقص المخزونَ
                   بما يقابلهما حبّاتٍ لا باثنتين — وهو ما تفعله شاشة
                   الفاتورة أصلاً (`$quantityToDeduct` في
                   `InvoicesController`). و`?:` لا `??`: الصفر المخزَّن في
                   البنود القديمة يجب أن يسقط إلى الكمية كما يسقط الفراغ. */
                $quantityToDeduct = (float) ($item['quantity_in_base_unit'] ?: $item['quantity']);

                /* الأصناف بلا رصيد — الخدمي والرقمي — لا تدخل المخزون.
                 *
                 * كان صفُّ الرصيد يُفتح لكل بندٍ قبل السؤال عن نوعه، فيرمي
                 * `StockMovementGuardObserver` عند أوّل تحويلِ أمرِ بيعٍ
                 * فيه خدمة: «لا يمكن إدخال «خدمة استشارة» في المخزون»،
                 * ويسقط التحويل كلُّه لا بندُه وحده.
                 *
                 * وشاشةُ الفاتورة والمرتجعُ والإشعارُ الدائن تسأل عن النوع
                 * أوّلاً منذ حين — والتحويلُ وحده بقي خارج القاعدة، وهو
                 * الطريق الثاني إلى الفاتورة نفسِها. وحركةُ المخزون أدناه
                 * محروسةٌ بالنوع أصلاً، فالصفُّ كان يُفتح ثمّ لا يُستعمَل. */
                $productDetails = null;

                if (StockEligibility::isStockable($product ?: $item['product_id'])) {
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

                if ($productDetails && ($product->type == 'products' || ($product->type == 'compiled' && $product->compile_type !== 'Instant'))) {
                    /* `(float)` لا `(int)`: البترُ كان يمرّر نصفَ حبّةٍ من
                       مخزونٍ فارغ (`0.5 → 0`)، ويمرّر ٢٫٧٥ من مخزونٍ فيه
                       اثنتان (`2 > 2` كاذبة) */
                    if ($quantityToDeduct > (float) $productDetails->quantity) {
                        $shortageKey = (! empty($item['unit_name']) && (float) $item['conversion_factor'] != 1.0)
                            ? 'sales_invoice.ui.stock_short_unit'
                            : 'sales_invoice.ui.stock_short';

                        throw new \Exception(__($shortageKey, [
                            'item' => $productName,
                            'store' => $storeHouse->name ?? '—',
                            'quantity' => rtrim(rtrim(number_format((float) $item['quantity'], 4, '.', ''), '0'), '.'),
                            'unit' => $item['unit_name'] ?? '',
                            'needed' => rtrim(rtrim(number_format($quantityToDeduct, 4, '.', ''), '0'), '.'),
                            'available' => rtrim(rtrim(number_format((float) $productDetails->quantity, 4, '.', ''), '0'), '.'),
                        ]));
                    }
                }

                /* المجمَّعُ غيرُ الفوري يُخصم كالسلعة — كان يُفحص ثمّ لا يُخصم،
                   فيُباع من رصيده نفسِه بلا نهاية. */
                if ($productDetails && ($product->type == 'products' || ($product->type == 'compiled' && $product->compile_type !== 'Instant'))) {
                    $stock_before = $productDetails->quantity;
                    $stock_after = $stock_before - $quantityToDeduct;

                    $productDetails->decrement('quantity', $quantityToDeduct);

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
                    /* إذنٌ صادرٌ عن مستند لا طلبٌ ينتظر موافقة: الحركة
                       وقعت وقُيِّدت محاسبياً مع الفاتورة، فيُولَد موافَقاً
                       عليه — كما تفعل شاشةُ الفاتورة المباشرة.
                       (`apporod` هي قيمة الموافقة المعتمدة في النظام.)

                       وبدونه يأخذ العمودُ افتراضَه `pending`، فيخرج الإذنُ
                       «غير معتمد» والمخزونُ منقوصٌ فعلاً — إذنٌ معلَّقٌ
                       لحركةٍ تمّت. */
                    $wareHousePermits->status = 'apporod';
                    $wareHousePermits->save();

                    WarehousePermitsProducts::create(array_merge([
                        'product_id' => $item['product_id'],
                        'stock_before' => $stock_before,
                        'stock_after' => $stock_after,
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

                    // تنبيه انخفاض الكمية
                    if ($productDetails->quantity < $product->low_stock_alert) {
                        $notificationsToInsert[] = [
                            'type' => 'Products',
                            'title' => 'تنبيه الكمية',
                            'description' => 'كمية المنتج '.$product->name.' قاربت على الانتهاء.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // معالجة المنتجات المجمعة Instant
                if ($productDetails && $product->type == 'compiled' && $product->compile_type == 'Instant') {
                    $stock_before = $productDetails->quantity;

                    // إضافة الكمية
                    $wareHousePermitsAdd = new WarehousePermits;
                    $wareHousePermitsAdd->permission_source_id = PermissionSources::WAREHOUSE_ADD;
                    $wareHousePermitsAdd->permission_date = $invoice->created_at;
                    $wareHousePermitsAdd->number = $invoice->id;
                    $wareHousePermitsAdd->grand_total = $invoice->grand_total;
                    $wareHousePermitsAdd->store_houses_id = $storeHouse->id;
                    $wareHousePermitsAdd->created_by = auth()->user()->id;
                    // موافَقٌ عليه — انظر تعليق الإذن الأوّل أعلاه
                    $wareHousePermitsAdd->status = 'apporod';
                    $wareHousePermitsAdd->save();

                    $productDetails->increment('quantity', $quantityToDeduct);

                    WarehousePermitsProducts::create([
                        'quantity' => $quantityToDeduct,
                        'total' => $item['total'],
                        'unit_price' => $item['unit_price'],
                        'product_id' => $item['product_id'],
                        'stock_before' => $stock_before,
                        'stock_after' => $stock_before + $quantityToDeduct,
                        'warehouse_permits_id' => $wareHousePermitsAdd->id,
                    ]);

                    // خصم الكمية
                    $wareHousePermitsSub = new WarehousePermits;
                    $wareHousePermitsSub->permission_source_id = PermissionSources::SALES_INVOICE;
                    $wareHousePermitsSub->permission_date = $invoice->created_at;
                    $wareHousePermitsSub->number = $invoice->id;
                    $wareHousePermitsSub->grand_total = $invoice->grand_total;
                    $wareHousePermitsSub->store_houses_id = $storeHouse->id;
                    $wareHousePermitsSub->created_by = auth()->user()->id;
                    $wareHousePermitsSub->status = 'apporod';
                    $wareHousePermitsSub->save();

                    $productDetails->decrement('quantity', $quantityToDeduct);

                    WarehousePermitsProducts::create([
                        'quantity' => $quantityToDeduct,
                        'total' => $item['total'],
                        'unit_price' => $item['unit_price'],
                        'product_id' => $item['product_id'],
                        'stock_before' => $stock_before + $quantityToDeduct,
                        'stock_after' => $stock_before,
                        'warehouse_permits_id' => $wareHousePermitsSub->id,
                    ]);

                    // معالجة المنتجات التابعة
                    $CompiledProducts = $compiledProductsMap->get($item['product_id'], collect());

                    foreach ($CompiledProducts as $compiledProduct) {
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

                        // المكوّنُ هو ما يخرج فعلاً — ولم يكن يُفحص قبل خصمه
                        $componentNeeded = (float) $compiledProduct->qyt * $quantityToDeduct;

                        if ($componentNeeded > (float) $compiledProductDetails->quantity) {
                            throw new \Exception(__('sales_invoice.ui.stock_short', [
                                'item' => Product::whereKey($compiledProduct->product_id)->value('name') ?? '#'.$compiledProduct->product_id,
                                'store' => $storeHouse->name ?? '—',
                                'needed' => rtrim(rtrim(number_format($componentNeeded, 4, '.', ''), '0'), '.'),
                                'available' => rtrim(rtrim(number_format((float) $compiledProductDetails->quantity, 4, '.', ''), '0'), '.'),
                            ]));
                        }

                        $cStock_before = $compiledProductDetails->quantity;
                        $cStock_after = $cStock_before - $compiledProduct->qyt * $quantityToDeduct;

                        $wareHousePermitsComp = new WarehousePermits;
                        $wareHousePermitsComp->permission_source_id = PermissionSources::SALES_INVOICE;
                        $wareHousePermitsComp->permission_date = $invoice->created_at;
                        $wareHousePermitsComp->number = $invoice->id;
                        $wareHousePermitsComp->grand_total = $invoice->grand_total;
                        $wareHousePermitsComp->store_houses_id = $storeHouse->id;
                        $wareHousePermitsComp->created_by = auth()->user()->id;
                        $wareHousePermitsComp->status = 'apporod';
                        $wareHousePermitsComp->save();

                        WarehousePermitsProducts::create([
                            'quantity' => $compiledProduct->qyt * $quantityToDeduct,
                            'total' => $item['total'],
                            'unit_price' => $item['unit_price'],
                            'product_id' => $compiledProduct->product_id,
                            'stock_before' => $cStock_before,
                            'stock_after' => $cStock_after,
                            'warehouse_permits_id' => $wareHousePermitsComp->id,
                        ]);

                        $compiledProductDetails->decrement('quantity', $compiledProduct->qyt * $quantityToDeduct);
                    }
                }
            }

            // إدراج اللوجات والإشعارات
            if (! empty($logsToInsert)) {
                ModelsLog::insert($logsToInsert);
            }
            if (! empty($notificationsToInsert)) {
                notifications::insert($notificationsToInsert);
            }

            // ** القيود المحاسبية **
            $clientaccounts = Account::ledger()->where('client_id', $invoice->client_id)->first();
            if (! $clientaccounts) {
                throw new \Exception('حساب العميل غير موجود');
            }

            /* حسابات القيد من شاشة «الحسابات المحاسبية»: ما يربطه المحاسب
               هناك كان لا يسري على قيدٍ واحد من قيود المبيعات.
               `require*` ترمي برسالةٍ تسمّي الحساب بدل `null` يصير بعد
               أسطرٍ قيداً بطرفٍ واحد. */
            $vatAccount = $this->requireAccountingAccount('vat_payable', SystemAccountIds::VAT_PAYABLE);
            $salesAccount = $this->requireAccountingAccount('sales', SystemAccountIds::SALES);

            $clientName = $clientaccounts->name ?? 'العميل';
            $invoiceDescription = 'فاتورة مبيعات رقم: '.$invoice->code.' | العميل: '.$clientName.' | المبلغ الكلي: '.CurrencySettings::format($invoice->grand_total).' | [تم التحويل من '.$sourceRef.']';

            // إنشاء القيد المحاسبي
            $journalEntry = JournalEntry::create([
                'reference_number' => $invoice->code,
                'date' => now(),
                'description' => $invoiceDescription,
                'status' => 1,
                'currency' => $this->documentCurrency($quote),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'reference_type' => \App\Enums\ReferenceType::SALES_INVOICE,
                'linking_type' => \App\Enums\LinkingType::SINGLE,
                'created_by_employee' => Auth::id(),
            ]);

            /* حساب العميل (مدين) = **إجمالي الفاتورة** — بالشحن.
             *
             * كان `$amount_after_discount + $tax_total`، أي بلا `shipping_cost`.
             * والقيدُ يوازن مع ذلك لأنّ الشحن غائبٌ عن الطرفين معاً، فلا
             * يكشفه فحصُ توازن — لكنّ ذمّة العميل تخرج أقلَّ من فاتورته
             * بمقدار الشحن، ولا يظهر إيرادُ الشحن في أيّ حساب. وهو ما شكا
             * منه المستخدم: «لم يُحسَب مبلغ الشحن في القيد حين حُدِّدت
             * إيرادات الشحن في عرض السعر».
             *
             * وشاشةُ الفاتورة المباشرة تقيّد `total_with_tax` منذ حين —
             * فالتحويلُ وحده كان خارج القاعدة، وهو الطريق الثاني إلى
             * الفاتورة نفسِها. */
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $clientaccounts->id,
                'description' => 'مديونية العميل: '.$clientName.' - فاتورة رقم '.$invoice->code,
                'debit' => $total_with_tax,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // حساب المبيعات (دائن)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $salesAccount->id,
                'description' => 'إيرادات مبيعات - فاتورة رقم '.$invoice->code,
                'debit' => 0,
                'credit' => $total_amount,
                'is_debit' => false,
            ]);

            // حساب الخصم المسموح به (مدين)
            if ($final_total_discount > 0) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    // كان `52` رقماً مكتوباً في الشيفرة بلا فحص وجود
                    'account_id' => $this->requireAccountingAccountId('allowed_discount', SystemAccountIds::ALLOWED_DISCOUNT),
                    'description' => 'خصم مسموح به - فاتورة رقم '.$invoice->code,
                    'debit' => $final_total_discount,
                    'credit' => 0,
                    'is_debit' => true,
                ]);
            }

            /* ضريبة البنود (دائن) — **بلا ضريبة الشحن**.
             *
             * كانت `$tax_total` كاملةً في سطرٍ واحد، وقد جُمعت فيها ضريبةُ
             * الشحن أعلاه. فوعاءان مختلفان — بضاعةٌ وخدمةُ توصيل — يخرجان
             * في القيد رقماً واحداً لا يُفكّ: لا يُعرَف منه كم ضريبةِ
             * المبيعات وكم ضريبةِ الشحن، ولا يُطابَق مع الإقرار الضريبي
             * الذي يفصل بينهما. والسطران أدناه مركَّبان لا مجموعان. */
            $items_tax = round($tax_total - $shipping_tax, 2);

            if ($items_tax > 0) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $vatAccount->id,
                    'description' => 'ضريبة القيمة المضافة على البنود - فاتورة رقم '.$invoice->code,
                    'debit' => 0,
                    'credit' => $items_tax,
                    'is_debit' => false,
                ]);
            }

            // ضريبة الشحن (دائن) — سطرُها مستقلّ
            if ($shipping_tax > 0) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    // حسابُ ضريبة الشحن إن رُبط، وإلّا حسابُ الضريبة نفسُه
                    'account_id' => $this->shippingVatAccountId(),
                    'description' => 'ضريبة شحن مبيعات - فاتورة رقم '.$invoice->code,
                    'debit' => 0,
                    'credit' => $shipping_tax,
                    'is_debit' => false,
                ]);
            }

            /* إيراد الشحن (دائن).
             *
             * الحساب `shipping_revenue` معرَّفٌ في «الحسابات المحاسبية»
             * ومربوط، ولم يكن التحويلُ يقيّد عليه شيئاً: ما يُحصَّل من
             * العميل مقابل التوصيل كان يدخل `grand_total` ولا يظهر في أيّ
             * حساب إيراد. `require…` عند وجود شحنٍ فقط — منشأةٌ لا تشحن لا
             * يعنيها المفتاح، ومن يشحن يجب أن يُقال له إن كان غير مربوط. */
            if ($shipping_cost > 0) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $this->requireAccountingAccountId('shipping_revenue'),
                    'description' => 'إيراد شحن - فاتورة رقم '.$invoice->code,
                    'debit' => 0,
                    'credit' => $shipping_cost,
                    'is_debit' => false,
                ]);
            }

            // ** قيد تكلفة المبيعات (COGS) **
            try {
                $costTotal = 0;
                $inventoryAccountId = null;

                $invoiceItemsForCost = InvoiceItem::where('invoice_id', $invoice->id)->get();

                foreach ($invoiceItemsForCost as $costItem) {
                    $productId = $costItem->product_id;
                    $storeHouseId = $costItem->store_house_id;

                    // بندٌ حرٌّ بلا منتج: لا تكلفةَ له تُرحَّل
                    if (! $productId) {
                        continue;
                    }

                    /* الكمّية **بالوحدة الأساسية**.
                       كانت `$costItem->quantity` — أي كمّيةُ وحدة البند —
                       مضروبةً في تكلفة الوحدة الأساسية: عرضٌ بكرتونين
                       يُقيَّد بتكلفة حبّتين. */
                    $quantity = (float) ($costItem->quantity_in_base_unit
                        ?: $costItem->quantity * ($costItem->conversion_factor ?: 1));

                    $productForCost = Product::find($productId);

                    if ($storeHouseId && $inventoryAccountId === null) {
                        $itemInventoryAccount = Account::where('storehouse_id', $storeHouseId)->first();
                        if ($itemInventoryAccount) {
                            $inventoryAccountId = $itemInventoryAccount->id;
                        }
                    }

                    /* ══ سعرُ التكلفة لا سعرُ الشراء ══
                     *
                     * كانت هنا سلسلةُ الأسعار القديمة بحذافيرها:
                     * `product_details.unit_price` لآخر صفّ، ثمّ
                     * `products.purchase_price`، ثمّ `AVG(unit_price)` غيرُ
                     * مرجَّح — أي **سعرُ آخر شراء** في الغالب. وقد استُبدلت
                     * في شاشة الفاتورة والمرتجع والإشعار الدائن
                     * بـ`ResolvesSaleCost` (المتوسّطُ المرجَّح المعروض على
                     * بطاقة الصنف)، وبقي التحويلُ وحده على القديم — فالفاتورة
                     * نفسُها تُقيَّد بتكلفتين مختلفتين حسب الباب الذي دخلت
                     * منه. وهو ما طلب المستخدم تصحيحه: «عندما أحوّلها إلى
                     * فاتورة يُسجَّل القيد بسعر التكلفة لا بسعر الشراء».
                     *
                     * والتجميدُ على البند (`unit_cost_at_sale`) شرطُ أن يعكس
                     * المرتجعُ لاحقاً ما قُيّد على البيع بالضبط. */
                    $unitCost = $this->itemUnitCost($costItem, $productForCost);

                    $this->freezeLineCost($costItem, $unitCost);

                    $costTotal += $quantity * $unitCost;
                }

                if ($inventoryAccountId === null) {
                    $mainStoreHouse = StoreHouse::where('major', 1)->first();
                    if ($mainStoreHouse) {
                        $inventoryAccount = Account::where('storehouse_id', $mainStoreHouse->id)->first();
                        if ($inventoryAccount) {
                            $inventoryAccountId = $inventoryAccount->id;
                        }
                    }
                }

                if ($inventoryAccountId && $costTotal > 0) {
                    $costJournalEntry = JournalEntry::create([
                        'reference_number' => $invoice->code,
                        'date' => now(),
                        'description' => 'تكلفة مبيعات الفاتورة رقم '.$invoice->code.' [من '.$sourceRef.']',
                        'status' => 1,
                        'currency' => $this->documentCurrency($quote),
                        'client_id' => $invoice->client_id,
                        'invoice_id' => $invoice->id,
                        'reference_type' => \App\Enums\ReferenceType::SALES_INVOICE,
                        'linking_type' => \App\Enums\LinkingType::SINGLE,
                        'created_by_employee' => Auth::id(),
                    ]);

                    JournalEntryDetail::create([
                        'journal_entry_id' => $costJournalEntry->id,
                        'account_id' => $this->accountingAccountId('cost_of_sales', SystemAccountIds::COST_OF_SALES),
                        'description' => 'إثبات تكلفة مبيعات الفاتورة رقم '.$invoice->code,
                        'debit' => $costTotal,
                        'credit' => 0,
                        'is_debit' => true,
                    ]);

                    JournalEntryDetail::create([
                        'journal_entry_id' => $costJournalEntry->id,
                        'account_id' => $inventoryAccountId,
                        'description' => 'تخفيض المخزون للفاتورة رقم '.$invoice->code,
                        'debit' => 0,
                        'credit' => $costTotal,
                        'is_debit' => false,
                    ]);
                }
            } catch (\Exception $eCost) {
                Log::error('خطأ أثناء إنشاء قيد تكلفة المبيعات', ['message' => $eCost->getMessage()]);
            }

            // ** إضافة ملاحظة تلقائية للعميل **
            try {
                $totalQuantityInInvoice = $quote->items->sum('quantity');

                $lastClientNote = ClientRelation::where('client_id', $invoice->client_id)
                    ->whereNotNull('deposit_count')
                    ->where('deposit_count', '>', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $previousDeposit = $lastClientNote ? intval($lastClientNote->deposit_count) : 0;
                $newDeposit = $previousDeposit + $totalQuantityInInvoice;

                ClientRelation::create([
                    'client_id' => $invoice->client_id,
                    'employee_id' => auth()->id(),
                    'type' => 'invoice',
                    'invoice_id' => $invoice->id,
                    'description' => 'فاتورة رقم: '.$invoice->code.' - [من '.$sourceRef.'] - الكمية: '.$totalQuantityInInvoice.' قطعة - قيمة الفاتورة: '.CurrencySettings::format($total_with_tax),
                    'deposit_count' => $newDeposit,
                    'date' => now()->toDateString(),
                    'time' => now()->toTimeString(),
                    'status' => 'completed',
                    'process' => 'فاتورة مبيعات من '.$sourceLabel,
                ]);
            } catch (\Exception $noteException) {
                Log::warning('فشل في إنشاء ملاحظة الفاتورة: '.$noteException->getMessage());
            }

            /* ** إشعار Telegram ** — من «إعدادات Telegram» (قناة المبيعات
             * ومفتاح «فاتورة مبيعات جديدة»)، ويُرسَل بعد التزام التحويل:
             * تحويلٌ تراجعت معاملتُه لا يُعلَن. كان يقرأ `config('services.telegram')`
             * — مصدرٌ ثانٍ لا تضبطه أي شاشة، وكان الرمزُ قبله مكتوباً هنا. */
            try {
                $employee_name = Employee::where('id', $invoice->employee_id)->first();
                $user_name = User::where('id', $invoice->created_by)->first();

                app(TelegramNotificationService::class)
                    ->salesInvoiceCreated($invoice, $sourceLabel, (string) $sourceNumber);

                // إنشاء إشعار
                notifications::create([
                    'user_id' => auth()->user()->id,
                    'employee_id' => $employee_name->id ?? null,
                    'type' => 'invoice',
                    'title' => ($user_name->name ?? 'مستخدم').' حوّل '.$sourceLabel.' إلى فاتورة',
                    'description' => 'تم تحويل '.$sourceRef.' إلى فاتورة للعميل '.($client->trade_name ?? '').' بقيمة '.CurrencySettings::format($invoice->grand_total),
                    'read' => 0,
                    'is_scheduled' => 0,
                    'is_sent' => 1,
                    'scheduled_at' => null,
                ]);
            } catch (\Exception $telegramEx) {
                Log::warning('فشل إرسال إشعار تليجرام: '.$telegramEx->getMessage());
            }

            /* تحديث حالة المستند — **ورابطه بفاتورته**.
               القيمة نصٌّ لا رقم: العمود `enum`، انظر `Quote::STATUSES`.
               و`invoice_id` عمودٌ في `quotes` منذ إنشائه لم يكن يكتبه شيء:
               الحالةُ وحدها كانت أثرَ التحويل، فلا سبيل من المستند إلى
               فاتورته إلّا بالبحث في `notes`. */
            $quote->update([
                'status' => Quote::STATUS_CONVERTED,
                'invoice_id' => $invoice->id,
            ]);

            /* استعمالُ الكوبون يُحتسَب هنا لا عند حفظ عرض السعر: العرضُ
               وعدٌ قد لا يتحوّل، وعدُّه استعمالاً يستنفد حصّةَ العميل من
               كوبونٍ لم يشترِ به شيئاً. */
            if ($quote->coupon_id && $coupon_discount > 0) {
                $usedCoupon = StoreCoupon::find($quote->coupon_id);

                if ($usedCoupon) {
                    app(SalesCouponService::class)->recordUsage(
                        $usedCoupon,
                        $quote->client_id,
                        $invoice->id,
                        $coupon_discount,
                    );
                }
            }

            /* سجلّ النشاط بنوع المستند لا بـ`'quote'` مثبَّتاً، وبرقمه
               المعروض لا بمعرّفه: الخطّ الزمني في شاشة العرض يقرأ
               `where('type', static::LOG_MODULE)`، فسطرٌ بنوعٍ آخر لا
               يظهر على المستند الذي يخصّه. */
            ModelsLog::create([
                'type' => static::LOG_MODULE,
                'type_id' => $quote->id,
                'type_log' => 'log',
                'icon' => 'convert',
                'description' => $this->dt('log.converted', [
                    'number' => $quote->quote_number ?: $quote->id,
                    'code' => $invoice->code,
                ]),
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('invoices.show', $invoice->id)
                ->with('success', $this->dt('flash.converted', [
                    'number' => $quote->quote_number ?: $quote->id,
                    'code' => $invoice->code,
                ]));

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('حدث خطأ أثناء تحويل عرض الأسعار إلى فاتورة: '.$e->getMessage()."\n".$e->getTraceAsString());

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء التحويل: '.$e->getMessage());
        }
    }

    /**
     * محتوى رمز الفوترة الإلكترونية (TLV).
     *
     * كان اسم البائع ورقمه الضريبي مكتوبين في الشيفرة:
     * `'مؤسسة اعمال خاصة للتجارة'` و`'000000000000000'` — أي أن كل رمزٍ
     * يصدر عن هذا النظام، في أيّ منشأة، يحمل اسم منشأةٍ أخرى ورقماً
     * ضريبياً مزيَّفاً. وهو رمزٌ يقرأه المشتري والجهة الضريبية.
     *
     * الآن من «معلومات المنشأة» في الإعدادات.
     */
    private function generateTlvContent($timestamp, $totalAmount, $vatAmount)
    {
        // كان الوقت يُكتَب كما يخرج من القاعدة لا بصيغة ISO — انظر `ZatcaQr`
        return \App\Services\Zatca\ZatcaQr::phaseOne($timestamp, (float) $totalAmount, (float) $vatAmount);
    }

    private function getTlv($tag, $value)
    {
        $value = (string) $value;

        return pack('C', $tag).pack('C', strlen($value)).$value;
    }
    /*
     * `logsaction()` حُذفت.
     *
     * كانت `return view('test');` — قالب تجربةٍ لا صلة له بعروض الأسعار،
     * مربوطاً بمسارٍ حيّ (`questions.logs`) خلف فحص صلاحية. والخطّ الزمني
     * للمستند يعرضه `show()` عبر `documentLogs()`، وسجلّ النشاط العامّ
     * شاشةٌ قائمة في الإعدادات.
     *
     * والمساران `questions.template.edit` و`questions.template.update`
     * حُذفا من `routes/web.php`: كانا يشيران إلى `editTemplate()` و
     * `updateTemplate()` — دالّتان لا وجود لهما في هذا المتحكّم، فكل
     * نداءٍ لهما خطأٌ فادح. وقوالب الطباعة تتولّاها الآن
     * `RendersSalesDocumentTemplates`.
     */
}
