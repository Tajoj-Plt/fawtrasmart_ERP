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
use App\Models\EmployeeGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\JournalEntryInvoice;
use App\Models\Log as ModelsLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductDetails;
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
use App\Services\Invoices\ClientDebtSettlement;
use App\Services\PointsService;
use App\Services\Tax\DocumentTaxCalculator;
use App\Support\BusinessInfo;
use App\Support\CurrencySettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\StockEligibility;
use App\Support\TreasuryAccessGuard;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Sales\Support\ProductUnits;
use Modules\Sales\Support\SalesWarehouse;

/**
 * الإشعارات الدائنة.
 *
 * **ما يأتي من الإعدادات** — كان كلّه مكتوباً في الشيفرة:
 *
 *   • *الرقم*        ← «تسلسل أرقام المستندات»، القسم `credit-note`
 *                      (`GeneratesDocumentNumbers`).
 *   • *العملة*       ← «العملات»، وتُجمَّد مع المستند بعد الحفظ
 *                      (`UsesDocumentCurrency`).
 *   • *الحسابات*     ← «الحسابات المحاسبية» وحدها، بلا احتياطٍ برمجي
 *                      (`ResolvesAccountingAccounts`).
 *   • *الإقفال*      ← «الفترات المحاسبية»: تاريخٌ في فترةٍ مقفلة يردّ
 *                      الحفظ (`RespectsAccountingPeriods`).
 *   • *القوالب*      ← «قوالب المبيعات» في الطباعة
 *                      (`RendersSalesDocumentTemplates`).
 *   • *طرق الدفع*    ← «طرق الدفع» في ردّ المبلغ للعميل.
 *   • *السلوك*       ← «إعدادات الفواتير» (`SalesInvoiceSetting`).
 *   • *السجلّات*     ← `OperationLog`: النشاط والأحداث والأخطاء.
 *   • *اللغة والمظهر* ← عبر القوالب: `sl-*` مبنيّة على `--app-*`.
 */
class CreditNotificationController extends Controller
{
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

    /** قسم الترقيم المتسلسل الذي تُؤخذ منه أرقام الإشعارات الدائنة */
    public const NUMBER_SECTION = 'credit-note';

    /** نوع المستند في «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'credit_note';

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'SalesCreditNotes';

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.credit_notes.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.credit_notes.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.credit_notes.create', ['only' => ['create', 'store', 'settlementPreview', 'clientInvoices']]);
        $this->middleware('permission:sales.credit_notes.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales.credit_notes.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.credit_notes.cancel', ['only' => ['cancel']]);
        $this->middleware('permission:sales.credit_notes.send', ['only' => ['sendCreditNotification']]);
        $this->middleware('permission:sales.credit_notes.print', ['only' => ['print']]);
    }

    /**
     * عدد السجلّات في الصفحة — من قائمة مغلقة.
     *
     * `per_page` كان يصل إلى `paginate()` كما أرسله المتصفّح: طلبٌ بـ
     * `per_page=9999999` يحمّل الجدول كلّه في الذاكرة. البند ٦٫٣ في
     * تقرير التحسين يرصده ثغرةَ إنهاك.
     */
    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    /**
     * شاشة الإشعارات الدائنة — على قياس شاشة فواتير المبيعات.
     *
     * ما أُصلح هنا (بنودٌ من تقرير التحسين):
     *
     *   • *فلاتر على أعمدة لا وجود لها* — كان يفلتر على `currency`
     *     و`due_date` و`source` و`custom_field` و`shipping_option`
     *     و`post_shift` و`order_source`، وسبعتها ليست أعمدةً في
     *     `credit_notifications`: أيّ واحدٍ منها يُسقط الشاشة بخطأ SQL.
     *     ما له معنى منها صار عموداً (`currency`)، والباقي حُذف.
     *   • *حدود المدى* (٢٫١٥) — `>` و`<` على الإجمالي تستبعد القيم
     *     الحدّية: من يبحث «من 1000 إلى 5000» لا يرى إشعاراً بـ1000.
     *     صارت `>=` و`<=` كما في شاشة الفواتير.
     *   • *`per_page` بلا تحقّق* (٦٫٣) — ثغرة إنهاك.
     *   • *`appends()` مفقودة* (٦٫٢) — التنقّل بين الصفحات كان يُسقط
     *     الفلاتر كلَّها.
     *   • *`LIKE '%…%'` على الرقم* (٥٫١) — يمنع استعمال الفهرس. صار
     *     بحث بادئة، ومطابقةً تامّة على المعرّف حين يكون المُدخل رقماً.
     *   • *تسميات نطاق التاريخ* (٥٫٣) — `from_date_1`/`to_date_1`
     *     و`from_date_2`/`to_date_2`: أربع تسميات لمفهومٍ واحد داخل
     *     الوحدة. صارت `from_date`/`to_date` كما في بقيّة الشاشات.
     *   • *بلا نطاق افتراضي* — تفتح الشاشة على تاريخ المنشأة كلّه.
     *     صارت تبدأ من الفترة المحاسبية الجارية.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // نطاق التاريخ الافتراضي: الفترة المحاسبية الجارية
        $range = $this->reportDateRange($request);

        // العملة لم تعد في نموذج البحث: غيابُها «كل العملات» لا عملةُ النظام
        $currency = $request->filled('currency') ? $this->filterCurrency($request) : null;

        $query = CreditNotification::with(['client', 'createdBy', 'invoice']);

        /* ── نطاق ما يراه المستخدم — الدخول أصلاً محروسٌ بـ sales.credit_notes.view ──

           السلَّمُ الخماسيّ: الكلّ، أو فرعُه، أو فريقُه، أو ما أنشأه ونُسب
           إليه — كان «الكلّ أو ما أنشأتُه أنا» وحدَه. و«أنا مسؤوله» باقٍ
           مع «أنا أنشأته»: إشعارٌ سجّله محاسبٌ عن مندوب يبقى مرئياً
           لصاحبه. انظر `ScopesSalesVisibility`. */
        $this->scopeSalesDocuments($query);

        /* ومحورُ المجموعات لمن عليه قيدُ نطاقٍ أصلاً.
         *
         * كان `if ($user->isEmployee())` بلا شرطٍ آخر: «حتى لو ملك النطاق
         * الكامل». ومديرُ النظام في هذه المنشأة له سجلُّ موظفٍ أيضاً
         * (`users.employee_id`) ولا مجموعةَ له — فيقع على `1 = 0` وتفتح
         * الشاشةُ فارغةً على ٤٦٣ إشعاراً موجودة، بلا خطأٍ ولا رسالة.
         *
         * والحَكَمُ `salesScopeApplies()`: من لا قيدَ عليه (نطاقٌ كامل، أو
         * مستخدمٌ ليس موظفاً) لا يُقيَّد بمجموعاته.
         * انظر «Itinerary guard is scope, not isEmployee».
         */
        if ($user->isEmployee() && $this->salesScopeApplies()) {
            $this->scopeToEmployeeGroups($query, $user);
        }

        /* الفرع النشط من الهيدر — مبدّل الفروع يكتب `users.branch_id`
           و`current_branch_id()` تقرؤه. المدير على «كل الفروع» قيمتُه
           `null` فلا تصفية. */
        /* والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها،
           فمن يقف عليه يرى المبيعات كلَّها. */
        $this->applyHeaderBranchScope($query);

        // قاعدةُ منسدلتَي «أُضيفت بواسطة» و«مسؤول المبيعات» — قبل فلاتر البحث
        $optionsBase = clone $query;

        $this->applySearchFilters($query, $request, $range, $currency);

        /* ── إحصاءات الشاشة — **قبل** التقطيع ──
           `paginate()` يكتب `limit/offset` على الاستعلام نفسِه، فنسخةٌ تُؤخذ
           بعده تجمع بـ`OFFSET` — صفرٌ في البطاقة من الصفحة الثانية. */
        $creditsTotal = (float) (clone $query)->sum('grand_total');

        $credits = $query->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->appends($request->except('page'));
        $creditsCount = $credits->total();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => view('sales::creted_note.partials.table', [
                    'credits' => $credits,
                    'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
                ])->render(),
                'current_page' => $credits->currentPage(),
                'last_page' => $credits->lastPage(),
                'total' => $credits->total(),
                'from' => $credits->firstItem(),
                'to' => $credits->lastItem(),
                'per_page' => $credits->perPage(),
                'credits_total' => CurrencySettings::format($creditsTotal),
                'credits_count' => $creditsCount,
            ]);
        }

        $Credits_number = $this->previewDocumentNumber(self::NUMBER_SECTION);
        $clients = $this->visibleClients($user);
        // من الإشعارات التي يراها المستخدم في هذا الفرع — انظر `creatorFilterOptions()`
        $users = $this->creatorFilterOptions($optionsBase);
        $account_setting = AccountSetting::where('user_id', $user->id)->first();

        // حالةُ حدّ «الإشعارات الدائنة» في باقة فوترة — للشريط أعلى الشاشة
        $subscriptionLimit = $this->subscriptionLimit(SalesPlanFeatures::CREDIT_NOTES);

        return view('sales::creted_note.index', compact(
            'subscriptionLimit',
            'credits',
            'account_setting',
            'users',
            'Credits_number',
            'clients',
            'range',
            'creditsTotal',
            'creditsCount'
        ) + [
            'branches' => Branch::where('status', 0)->select('id', 'name')->get(),
            // خيارات «الموظف المسؤول» — تصفيةٌ على `employee_id` لا على المنشئ
            'employees_sales_person' => $this->salesPersonFilterOptions($optionsBase),
            'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
        ]);
    }

    /**
     * يقصر الاستعلام على عملاء مجموعات الموظّف.
     *
     * كان هذا الشرط مكتوباً مرّتين حرفياً في `index()` — فرعُ «يرى الكلّ»
     * وفرعُ «يرى إشعاراته».
     */
    protected function scopeToEmployeeGroups($query, $user): void
    {
        $groupIds = EmployeeGroup::where('employee_id', $user->employee_id)->pluck('group_id');

        if ($groupIds->isEmpty()) {
            /* موظّفٌ بلا مجموعة: يبقى على محوره الأوّل — ما أنشأه أو ما
               هو مسؤولُه — ولا يُقفَل عليه كلُّ شيء.
               كان `whereRaw('1 = 0')` يمحو حتى إشعاراتِه هو، فيرى شاشةً
               فارغةً على مستنداتٍ كتبها بيده. */
            return;
        }

        $query->whereHas('client.Neighborhoodname.Region', function ($q) use ($groupIds) {
            $q->whereIn('id', $groupIds);
        });
    }

    /**
     * شروط البحث في شاشة الإشعارات الدائنة.
     *
     * @param  array{start: string, end: string}|null  $range
     * @param  string|null  $currency  رمز العملة، أو `null` لكل العملات
     */
    protected function applySearchFilters($query, Request $request, ?array $range = null, ?string $currency = null): void
    {
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // جزءٌ من الرقم المطبوع — كما يقول تلميح الحقل — ومطابقة تامّة على المعرّف
        if ($request->filled('credit_number')) {
            $term = trim((string) $request->credit_number);

            $query->where(function ($q) use ($term) {
                $q->where('credit_number', 'like', '%'.$term.'%');

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        /* «ملغى» في المُنتقي قيمتُه 5، و`cancel()` يكتب 0 — والجدول يعرض
           الاثنين ملغيَين. فكان اختيارُ «ملغى» يُخفي كلَّ إشعارٍ أُلغي من
           هذه الشاشة. */
        if ($request->filled('status')) {
            (string) $request->status === '5'
                ? $query->whereIn('status', [0, 5])
                : $query->where('status', $request->status);
        }

        $this->applyCurrencyFilter($query, $currency);

        // الفرع من عمود الإشعار نفسه لا من فرع مُنشئه الحاليّ
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

        // `where` على النطاق لا `whereDate`: الدالّة على العمود تمنع
        // استعمال الفهرس (البند ٥٫٤)
        /* على **تاريخ الإشعار** (`credit_date`) — المعروض في الجدول، والذي
           تُستبعد عليه الفترات المقفلة أدناه، وما يَعِد به تلميحُ الحقل.
           كان `created_at`، فإشعارٌ مؤرَّخٌ بغير يوم إدخاله يظهر في نطاقٍ
           غير نطاقه. وإشعارٌ بلا تاريخٍ يُقرأ بلحظة إدخاله. */
        if ($range) {
            $start = Carbon::parse($range['start']);
            $end = Carbon::parse($range['end']);

            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('credit_notifications.credit_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($legacy) use ($start, $end) {
                        $legacy->whereNull('credit_notifications.credit_date')
                            ->whereBetween('credit_notifications.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                    });
            });
        }

        /* ** الفترات المقفلة ** — تُستبعد من الشاشة كلّها لا من نطاقها
           الافتراضيّ وحده: من أقفل سنةً لا يريد مستنداتِها أن تعود بضبط
           التاريخ يدوياً. */
        $this->excludeClosedRanges($query, 'credit_notifications.credit_date');

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        /* الموظّف المسؤول — العمود `employee_id` المختوم على الإشعار.
           كانت الشاشة تصفّي بمن أدخله وحده، وهما ليسا واحداً: المحاسبُ
           يُدخل عن المندوب فيُنسب الإشعارُ للمندوب ويُسجَّل باسم المحاسب. */
        if ($request->filled('sales_person_user')) {
            $query->where('employee_id', $request->sales_person_user);
        }

        if ($request->filled('item_search')) {
            $term = trim((string) $request->item_search);

            $query->whereHas('items', function ($q) use ($term) {
                $q->where('item', 'like', $term.'%')
                    ->orWhere('description', 'like', $term.'%');
            });
        }
    }

    /**
     * العملاء المعروضون في قوائم الاختيار.
     *
     * مندوبُ خطّ السير يرى عملاء زياراته اليوم وحدهم. كان هذا الاشتقاق
     * مكتوباً مرّتين — في `index()` و`create()` — بنسختين متطابقتين.
     */
    /**
     * الفواتير التي تقبل إشعاراً دائناً لعميلٍ بعينه — مُنتقي «الفاتورة المرجعية».
     *
     * فواتيرُ العميل المختار وحده، وبلا عميلٍ لا شيء: كانت القائمة آخرَ مئتي
     * فاتورة من العملاء كلّهم حين تُفتح الشاشة من القائمة، ولا تتبدّل حين يُختار
     * العميل — فتُختار فاتورةُ غيره. وتُستبعد ما يرفضه الحفظ نفسُه
     * (`Invoice::creditNoteBlockReason()`): الملغاة، وما صدر لها مرتجع، وما صدر
     * لها إشعارٌ دائنٌ غيرُ ملغى.
     */
    protected function referenceInvoicesFor(?int $clientId)
    {
        if (! $clientId) {
            return collect();
        }

        return Invoice::query()
            ->where('client_id', $clientId)
            ->whereIn('type', ['normal', 'pos', 'online'])
            ->whereNull('voided_at')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('invoices as r')
                ->where('r.type', 'returned')
                ->whereColumn('r.reference_number', 'invoices.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('credit_notifications as c')
                ->whereColumn('c.invoice_id', 'invoices.id')
                ->whereNotIn('c.status', [0, 5]))
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'code', 'client_id', 'grand_total', 'created_at']);
    }

    /**
     * فواتيرُ العميل المرجعية بصيغة JSON — تُملأ بها القائمة حين يُختار العميل.
     * والعميلُ مقيَّدٌ بما يراه المستخدم كما في `create()`.
     */
    public function clientInvoices(Request $request)
    {
        $user = Auth::user();
        $clientId = (int) $request->query('client_id');

        if ($clientId && $user->isEmployee() && ! $this->visibleClients($user)->contains('id', $clientId)) {
            abort(403);
        }

        return response()->json(
            $this->referenceInvoicesFor($clientId ?: null)
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'label' => $invoice->code.' — '.CurrencySettings::format($invoice->grand_total),
                ])
                ->values()
        );
    }

    protected function visibleClients($user)
    {
        /* قائمةُ العميل بمحورَي النطاق معاً — الصلاحيةُ **وفرعُ الشريط
           العلويّ**. كانت بالصلاحية وحدها، فمن بدّل الشريط إلى فرعٍ بعينه
           يجد في المُنتقي عملاء المنشأة كلِّها. والفرعُ الرئيسيّ يعني
           المنشأة كلَّها فلا يُرشِّح — انظر `HeaderBranchScope`. */
        return ClientVisibilityScope::documentSelectClients($user, [
            'account' => fn ($query) => $query->select('id', 'client_id', 'balance'),
        ]);
    }

    // public function sendCreditNotification($id)
    // {
    //     $credit = CreditNotification::with(['client', 'createdBy'])->findOrFail($id);

    //     // تحقق من وجود بريد إلكتروني صالح
    //     if (!$credit->client || !$credit->client->email || !filter_var($credit->client->email, FILTER_VALIDATE_EMAIL)) {
    //         return redirect()->back()->with('error', 'هذا العميل لا يحتوي على بريد إلكتروني صالح.');
    //     }

    //     // توليد رابط العرض
    //     $url = route('credits.print', $credit->id);

    //     // إرسال البريد
    //     Mail::to($credit->client->email)->send(new CreditNotificationLinkMail($credit, $url));

    //     return redirect()->back()->with('success', 'تم إرسال رابط إشعار الدائن إلى بريد العميل.');
    // }
    private function generateInvoiceNumber()
    {
        $lastCredit = CreditNotification::latest()->first();

        return $lastCredit ? $lastCredit->id + 1 : 1;
    }

    /**
     * شاشة إنشاء إشعار دائن — على قياس «إنشاء إشعار مدين» في المشتريات.
     *
     * الإشعار الدائن — كنظيره المدين — قد يصدر عن فاتورة وقد يصدر
     * مستقلاً:
     *
     *   • جاء من فاتورة (`?id=`) ← المرجع مقفل وبنوده منسوخة عنها
     *   • فُتِح من القائمة       ← المرجع اختياري من قائمة فواتير العميل
     *
     * وكان الرابط في شاشة الفاتورة يمرّر `id` منذ البداية
     * (`route('CreditNotes.create', ['id' => $invoice->id])`)، والشاشة
     * تقرأ `client_id` وحده — فيصل المستخدم إلى نموذجٍ فارغ ويعيد إدخال
     * البنود بيده.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.credit_notes.create)

        // ── المستودعات ومنتجاتها ──
        $store_houses = collect();

        if ($user->employee_id) {
            /* مصدر المستودع من «إعدادات الفواتير» (مستودع الموظّف أو
               الرئيسي)، مصفّاةً على صلاحية «إنشاء فاتورة» المضبوطة على
               المستودع — كانت تُحفَظ في شاشة المستودع ولا يقرؤها أحد. */
            $store_houses = SalesWarehouse::optionsFor($user);

            $targetStorehouseId = $store_houses->first()->id ?? null;

            /* علاقاتُ الوحدات تُحمَّل هنا لا داخل `ProductUnits::map()`:
               الخريطةُ تمرّ على المنتجات كلِّها، فقراءةُ العلاقة داخلها
               تعني استعلاماً لكلّ منتج. */
            $items = $targetStorehouseId
                ? Product::with(array_merge(
                    ['productDetails' => fn ($q) => $q->where('store_house_id', $targetStorehouseId)],
                    ['productUnits.categoryUnit.unit', 'category.categoryUnits.unit'],
                ))
                    ->where(function ($query) use ($targetStorehouseId) {
                        $query->whereHas('productDetails', fn ($q) => $q->where('store_house_id', $targetStorehouseId));

                        // الخدمي والرقمي بلا رصيد — يظهران بحسب «إعدادات الفواتير»
                        if ($stocklessTypes = SalesWarehouse::visibleStocklessTypes()) {
                            $query->orWhereIn('type', $stocklessTypes);
                        }
                    })
                    ->get()
                : collect();
        } else {
            $store_houses = StoreHouse::all();
            $items = Product::with(ProductUnits::relations())->get();
        }

        $clients = $this->visibleClients($user);

        // ── الفاتورة المرجعية ──
        $originalInvoice = null;
        $selectedClientId = $request->query('client_id');

        if ($invoiceId = $request->query('id')) {
            $originalInvoice = Invoice::with(['items.product', 'client'])->find($invoiceId);

            if ($originalInvoice) {
                /* فاتورةٌ رُدَّت لا تقبل إشعاراً دائناً — والقائمةُ أدناه
                   تستبعدها أصلاً، لكنّ القدومَ من شاشة الفاتورة بـ`?id=`
                   كان يتخطّى الاستبعاد: الزرّ حيٌّ في شاشة فاتورةٍ لها
                   مرتجع، فيُنشَأ إشعارٌ يردّ البضاعةَ مرّةً ثانية. انظر
                   `Invoice::creditNoteBlockReason()`. */
                if ($blocked = $originalInvoice->creditNoteBlockReason()) {
                    return redirect()->route('invoices.show', $originalInvoice->id)->with('error', $blocked);
                }

                $selectedClientId = $originalInvoice->client_id;
            }
        }

        // التحقّق من صلاحية الوصول للعميل المحدَّد
        if ($selectedClientId && $user->isEmployee() && ! $clients->contains('id', $selectedClientId)) { // ✅ isEmployee() بدلاً من عمود role النصي
            abort(403, 'ليس لديك صلاحية الوصول لهذا العميل');
        }

        /* فواتيرُ العميل المختار المتاحة مرجعاً — وتتبدّل في الشاشة حين يُختار
           عميلٌ آخر (`clientInvoices()`). والعميلُ من `old()` بعد رفض الحفظ، وإلّا
           عادت القائمة فارغةً والعميلُ ما زال مختاراً. */
        $availableInvoices = $this->referenceInvoicesFor(
            (int) ($selectedClientId ?: old('client_id')) ?: null
        );

        /* وحداتُ المنتجات — كانت الشاشة تسأل عن الكمية بلا وحدة، فلا
           يُعرَف أهي حبّةٌ أم كرتون، ولا يُحسَب ما يقابلها بالوحدة الأساسية.
           والقائمةُ لا تظهر لمنتجٍ بلا وحدات. */
        $productsWithUnits = ProductUnits::map($items);

        /* الخزائن المسموح السحبُ منها، وخزينةُ الموظف مختارةً سلفاً —
           المُختار يُلتقَط من القائمة نفسها فلا يُختار خيارٌ غيرُ معروض:
           قيمُ الخيارات معرّفاتُ `treasuries` بينما خزينةُ الموظف
           (`treasury_employees.treasury_id`) معرّفُ **حساب**. والموظفُ
           يرى خزينتَه وحدها — `TreasuryAccessGuard::ownWithdrawable()`. */
        $treasuries = TreasuryAccessGuard::ownWithdrawable();
        $selectedTreasuryId = null;

        if ($user->employee_id) {
            $employeeTreasuryAccountId = TreasuryEmployee::where('employee_id', $user->employee_id)->value('treasury_id');

            if ($employeeTreasuryAccountId) {
                $selectedTreasuryId = $treasuries->firstWhere('account_id', $employeeTreasuryAccountId)?->id;
            }
        }

        return view('sales::creted_note.create', [
            'productsWithUnits' => $productsWithUnits,
            // حدُّ باقة الاشتراك معروضٌ قبل ملء النموذج لا بعده: من يملأ مستنداً
            // طويلاً ثمّ يُرَدّ برسالة «بلغتَ الحدّ» فقد وقته مرّتين
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::CREDIT_NOTES),

            'clients' => $clients,
            'taxs' => TaxSitting::all(),
            'users' => User::all(),
            'Credits_number' => $this->previewDocumentNumber(self::NUMBER_SECTION),
            'items' => $items,
            'account_setting' => AccountSetting::where('user_id', $user->id)->first(),
            'selectedClientId' => $selectedClientId,
            'store_houses' => $store_houses,
            'originalInvoice' => $originalInvoice,
            'availableInvoices' => $availableInvoices,

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «إعدادات الفواتير» — نفس الإعدادات التي تحكم شاشة الفاتورة
            'invoiceSettings' => SalesInvoiceSetting::current(),

            // «العملات»
            'documentCurrency' => $this->defaultCurrency(),
            'currencies' => $this->activeCurrencies(),

            // «طرق الدفع» — ردّ المبلغ للعميل. كانت الشاشة بلا حقل طريقة
            // دفعٍ إطلاقاً، فالإشعار يُحفَظ ولا يُعرف كيف رُدّ مبلغه
            'paymentMethods' => $this->activePaymentMethods(),

            /* «الخزائن» — ما يجوز لهذا المستخدم **السحب** منه وحده، لا
               `Treasury::all()`: شرط السحب مضبوطٌ على الخزينة (موظفاً أو
               دوراً أو فرعاً) وتفرضه `TreasuryAccessGuard` عند الحفظ.
               وخزينةُ الموظف مختارةٌ سلفاً كما في شاشة المرتجع. */
            'treasuries' => $treasuries,
            'selectedTreasuryId' => $selectedTreasuryId,

            // «الفترات المحاسبية» — سببُ منع الحفظ بتاريخ اليوم إن وُجد
            'periodBlock' => $this->accountingPeriodBlock(now()),
        ]);
    }

    /**
     * معاينةُ ما سيُسدّده الإشعار — الخطّةُ نفسُها التي ينفّذها `store()`.
     *
     * لا يُكتب شيء. والعميلُ مقيَّدٌ بما يراه المستخدم كما في `create()`:
     * المعاينةُ تكشف ديونَ العميل وفواتيرَه.
     */
    public function settlementPreview(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'amount' => 'required|numeric|min:0',
            'invoice_id' => 'nullable|integer',
        ]);

        $user = Auth::user();

        if ($user->isEmployee() && ! $this->visibleClients($user)->contains('id', (int) $data['client_id'])) {
            abort(403);
        }

        return response()->json(app(ClientDebtSettlement::class)->plan(
            (int) $data['client_id'],
            (float) $data['amount'],
            ! empty($data['invoice_id']) ? (int) $data['invoice_id'] : null,
        ));
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
            OperationLog::failure($e, 'Sales.CreditNotification', 'activePaymentMethods');

            return collect();
        }
    }

    /**
     * عرض إشعار دائن.
     *
     * كانت الشاشة تعرض نسخةً من قالب الطباعة مصغَّرةً بـ`transform`
     * وسجلَّ نشاط، بلا بنودٍ ولا إجمالياتٍ ولا قيدٍ ولا فاتورةٍ مرجعية.
     * صارت تُمرَّر لها ما تعرضه شاشة الفاتورة.
     */
    public function show($id)
    {
        $user = Auth::user();

        $credit = CreditNotification::with([
            'client',
            'createdBy',
            'employee',
            'invoice',
            'treasury',
            'storeHouse',
            'items.product',
        ])->findOrFail($id);

        // التحقق من صلاحية العرض — الدخول أصلاً محروسٌ بـ sales.credit_notes.view؛
        // من يملك النطاق الكامل يرى كل الإشعارات، وإلا فإشعاراته هو وحدها
        /* نطاقُ الجدول نفسُه: إشعارُ فريقي أراه في القائمة فأفتحه. */
        $this->authorizeSalesDocumentScope($credit->created_by, $credit->employee_id, $credit->branch_id);

        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'credit')->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // `OperationLog::record()` يكتب دائماً بـ`self::LOG_MODULE`
        // («SalesCreditNotes»)، لا بالحرف الثابت «CreditNotification» —
        // فكان الخطّ الزمني فارغاً لكل إشعار كُتب بعد ذلك التحويل. والقيمة
        // القديمة تبقى في `whereIn` فلا تختفي سجلّاتٌ سابقة كُتبت بها فعلاً
        $logs = ModelsLog::whereIn('type', [self::LOG_MODULE, 'CreditNotification'])
            ->where('type_id', $id)
            ->whereHas('CreditNotification')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });
        /* القيد المحاسبي: `JournalEntry` لا يربطه عمودٌ بالإشعار، بل
           `reference_number` = معرّف الإشعار مع `reference_type`. من دون
           قيد النوع يلتقط الاستعلامُ قيدَ فاتورةٍ رقمُها هو الرقم نفسه. */
        $journalEntry = JournalEntry::where('reference_number', $credit->id)
            ->where('reference_type', ReferenceType::CREDIT_NOTE)
            ->orderBy('id')
            ->first();

        return view('sales::creted_note.show', [
            'credit' => $credit,
            'TaxsInvoice' => $TaxsInvoice,
            'account_setting' => $account_setting,
            'logs' => $logs,
            'journalEntry' => $journalEntry,
            // ما سدّده الإشعار: الرصيد السابق والفواتير
            'settlements' => app(ClientDebtSettlement::class)->settlementsFor($credit),

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «قوالب المبيعات» — قوالب الطباعة المصمَّمة لنوع «إشعار دائن»
            'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
            'defaultTemplate' => $this->defaultSalesTemplate(self::DOCUMENT_TYPE),
        ]);
    }

    public function edit($id)
    {
        return redirect()
            ->back()
            ->with('error', 'لا يمكنك تعديل الفاتورة رقم '.$id.'. طبقاً لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقاً لمتطلبات الفاتورة الإلكترونية.');
    }

    public function destroy($id)
    {
        return redirect()->route('CreditNotes.index')->with('error', 'لا يمكنك حذف الفاتورة. طبقاً لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل الفاتورة بعد إصدارها وفقاً لمتطلبات الفاتورة الإلكترونية.');
    }

    /**
     * المسار `CreditNotes.update` كان يشير إلى دالّةٍ غير معرَّفة، فأيّ
     * طلب PUT عليه يُرجع خطأً فادحاً بدل الرسالة. صار يُجيب كما تُجيب
     * `edit()` و`destroy()`: الإشعار لا يُعدَّل بعد إصداره، ويُلغى
     * بـ«الإلغاء» فيُعكَس قيده.
     */
    public function update(Request $request, $id)
    {
        return redirect()->route('CreditNotes.index')->with('error', 'لا يمكنك تعديل الإشعار الدائن. طبقاً لتعليمات هيئة الزكاة والدخل يمنع حذف أو تعديل المستند بعد إصداره، ويمكن إلغاؤه فيُعكَس قيده.');
    }

    /**
     * حفظ الإشعار الدائن — غلافٌ حول {@see persistCreditNote()}.
     *
     * كانت كتلةُ `try/catch` معلَّقة: أيُّ استثناءٍ بعد فتح المعاملة (حسابٌ غير
     * مربوط، حارسُ المخزون) يُخرج صفحة خطأ ويترك المعاملة مفتوحة.
     */
    public function store(Request $request)
    {
        try {
            return $this->persistCreditNote($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()->back()->withInput()->with('error', $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'عذراً، حدث خطأ أثناء حفظ الإشعار الدائن: '.$e->getMessage());
        }
    }

    /**
     * بنودُ إشعارٍ دائنٍ على فاتورة — مقابل الفاتورة نفسها. انظر `ReturnLimits`.
     * المعامل من الوحدة المختارة (`ProductUnits::resolve()`) لا من الشاشة.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     */
    protected function creditLinesViolation(Invoice $invoice, array $items): ?string
    {
        return \Modules\Sales\Support\ReturnLimits::for($invoice)->violation(array_map(fn (array $item) => [
            'product_id' => (int) ($item['product_id'] ?? 0),
            'quantity' => (float) ($item['quantity'] ?? 0),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'factor' => (float) (ProductUnits::resolve($item)['conversion_factor'] ?? 1),
        ], array_values($items)));
    }

    private function persistCreditNote(Request $request)
    {
        $user = Auth::user();

        /* الضريبة الشاملة على المستند — الخانةُ العلوية `document_tax_id`
           تُفرَض على كلّ بند حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة.
           انظر `InvoicesController::persistInvoice()`. */
        DocumentTaxCalculator::applyComprehensive($request);

        /* ** نطاق القرب من العميل ** — بصلاحية التجاوز بالاسمين (الجديد
           والقديم) وبالنطاق المضبوط في «إعدادات وحدات العملاء» لا بـ`0.3`
           مكتوبةً في الشيفرة. انظر `ChecksClientProximity`. */
        if ($proximityBlock = $this->clientProximityBlock(
            $request,
            $request->client_id,
            'sales.credit_notes.bypass_location'
        )) {
            return redirect()->back()->withInput()->with('error', $proximityBlock);
        }

        /* ** شرط السحب من الخزينة ** — القائمةُ مصفّاةٌ أصلاً، والفحصُ هنا
           لأنّ الحفظ لا يثق بما يصله من النموذج. */
        if ($request->filled('treasury_id')) {
            $chosen = Treasury::find($request->treasury_id);

            if ($chosen && ($denied = TreasuryAccessGuard::ownWithdrawBlockReason($chosen->account_id))) {
                return redirect()->back()->withInput()->with('error', $denied);
            }
        }

        // التحقق من صحة البيانات باستخدام helper function
        $validated = validator($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'credit_date' => 'required|date_format:Y-m-d',
            'release_date' => 'required|date_format:Y-m-d',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            /* الوحدة ووصفُ البند — كانا بلا قاعدة فتُسقطهما `validate()`: كرتونٌ يُردّ
               فيعود إلى المخزون حبّةً واحدة وتُعكس تكلفةُ حبّة. والمعاملُ نفسُه لا
               يُقبل من الشاشة: يُقرأ من الوحدة في `ProductUnits::resolve()`. */
            'items.*.product_unit_id' => 'nullable',
            'items.*.unit_name' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            /* `min:1` كان يردّ نصف كيلو وربع متر — و`invoice_items.quantity`
               صار `decimal(15,4)`، فالحدّ الصحيح أصغرُ ما يمثّله العمود لا
               الواحد الصحيح */
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:amount,percentage',
            'items.*.tax_1' => 'nullable|numeric|min:0',
            'items.*.tax_2' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            /* لم يعد في الشاشة مُنتقٍ له — الضريبةُ تُختار على البند.
               `nullable` لا `required` وإلّا رُدَّ كلُّ حفظ، والافتراضيُّ
               «خاضعة» أدناه كما في فاتورة البيع والمرتجع. */
            'tax_type' => 'nullable|in:1,2,3', // 1=vat, 2=zero, 3=exempt
            'tax_rate' => 'nullable|numeric|min:0', // إضافة حقل tax_rate
            'notes' => 'nullable|string',
        ])->validate();

        /* غيابُه يعني «خاضعة» — الشاشةُ لا تسأله، ويبقى مقروءاً لمن
           يرسله (نداءٌ برمجيّ، أو إشعارٌ قديم يُعاد حفظه). */
        $validated['tax_type'] = $validated['tax_type'] ?? '1';

        // تحويل tax_type إلى النص المناسب
        $tax_type_map = [
            '1' => 'vat',
            '2' => 'zero',
            '3' => 'exempt',
        ];

        /* ** فاتورةٌ رُدَّت لا تقبل إشعاراً دائناً **
         *
         * الحارسُ في `create()` يمنع فتحَ الشاشة، وهذا يمنع الحفظَ:
         * النموذجُ قد يكون فُتِح قبل المرتجع ثمّ حُفظ بعده، أو أُرسل
         * الطلبُ مباشرةً. انظر `Invoice::creditNoteBlockReason()`.
         */
        $referenced = $request->filled('invoice_id') ? Invoice::find($request->input('invoice_id')) : null;

        if ($referenced) {
            if ($blocked = $referenced->creditNoteBlockReason()) {
                return redirect()->back()->withInput()->with('error', $blocked);
            }

            // فاتورةُ عميلٍ آخر تُطفئ الإشعارُ دَينها وتُردّ بضاعتُها باسم هذا العميل
            if ((int) $referenced->client_id !== (int) $validated['client_id']) {
                return redirect()->back()->withInput()->with('error', __('sales_invoice.return_limits.client_mismatch', ['code' => $referenced->code]));
            }

            /* بنودُ الإشعار من الفاتورة، بكمياتٍ لا تتجاوز المتاح وأسعارٍ لا تتجاوز
               سعر البيع. كان يُقبل إشعارٌ بـ٩٩٩ حبّة على فاتورةٍ باعت ثلاث عشرة. */
            if ($limitError = $this->creditLinesViolation($referenced, $validated['items'])) {
                return redirect()->back()->withInput()->with('error', $limitError);
            }
        }

        // ** الإقفال المحاسبي **
        //
        // الإشعار الدائن يُولّد قيداً، والقيد لا يجوز في فترةٍ أُقفلت
        // وأُصدرت قوائمها. الفحص بتاريخ المستند لا بتاريخ اليوم
        if ($block = $this->accountingPeriodBlock($validated['credit_date'] ?? now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        // ** حدّ اشتراك فوترة ** — قبل أيّ كتابة وقبل حجز رقم المستند
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::CREDIT_NOTES)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        // بدء العملية داخل ترانزاكشن — الغلاف `store()` يتراجع عنها عند أيّ استثناء
        DB::beginTransaction();

        // ** الخطوة الأولى: رقم الإشعار من «الترقيم المتسلسل» **
        //
        // كان `intval($last->credit_number) + 1` مصفوفاً إلى خمس خانات:
        // يتجاهل البادئة والقالب المضبوطين في الشاشة، و`intval` على
        // رقمٍ ببادئة (`CN-000012`) يقرأ صفراً فيعيد التسلسل من أوّله.
        // والرقم المحجوز كان يردّ الإدخال كلّه برسالة بدل أن يُسلَّم
        // التاليَ الشاغر
        // من التسلسل وحده — انظر التعليق نفسه في `InvoicesController::persistInvoice()`
        $credit_number = $this->resolveDocumentNumber(null, self::NUMBER_SECTION);

        // ** الخطوة الثانية: تحديد المستودع المختار أو الافتراضي **
        $user = Auth::user();

        /* الشاشة لم تعد تعرض اختيار المستودع. فالبضاعةُ تُردّ إلى مستودع
           الفاتورة المرجعية إن وُجدت، وإلّا فالمصدر المضبوط في «إعدادات
           الفواتير»: مستودعُ الموظّف أو الرئيسي. `store_house_id` يبقى
           مقروءاً لمن يرسله صراحةً. */
        $selectedStoreHouseId = $request->input('store_house_id')
            ?: ($request->filled('invoice_id')
                ? InvoiceItem::where('invoice_id', $request->invoice_id)->whereNotNull('store_house_id')->value('store_house_id')
                : null);

        $storeHouse = SalesWarehouse::resolve($selectedStoreHouseId, $user);
        $store_house_id = $storeHouse ? $storeHouse->id : null;

        $items_data = []; // تجميع بيانات البنود

        // ** الخطوه الثالثه: معالجة البنود (items) وتحديث المخزون **
        $total_amount = 0;
        $total_discount = 0;
        foreach ($validated['items'] as $item) {
            // جلب المنتج
            $product = Product::findOrFail($item['product_id']);

            // حساب تفاصيل الكمية والأسعار
            $quantity = floatval($item['quantity']);
            $unit_price = floatval($item['unit_price']);
            $item_total = $quantity * $unit_price;

            // حساب الخصم للبند
            $item_discount = 0; // قيمة الخصم المبدئية
            if (isset($item['discount']) && $item['discount'] > 0) {
                $discountType = $item['discount_type'] ?? 'amount';
                if ($discountType === 'percentage') {
                    $item_discount = ($item_total * floatval($item['discount'])) / 100;
                } else {
                    $item_discount = floatval($item['discount']);
                }
            }

            // تحديث الإجماليات
            $total_amount += $item_total;
            $total_discount += $item_discount;

            // تجهيز بيانات البند
            /* ** الوحدة ومعامل تحويلها **
               الكميةُ تُحفَظ كما أدخلها المستخدم، ويُحفَظ معها ما يقابلها
               بالوحدة الأساسية. وبغير ذلك يعود إلى المخزون عددُ **الكراتين**
               لا عددُ الحبّات حين تُردّ البضاعة بالكرتون. */
            $unit = ProductUnits::resolve($item);
            $quantityInBaseUnit = $quantity * $unit['conversion_factor'];

            $items_data[] = [
                'credit_note_id' => null, // سيتم تعيينه لاحقًا بعد إنشاء العرض
                'product_id' => $item['product_id'],
                // المستودع الذي دخلته البضاعة — يقرؤه الإلغاءُ وقيدُ التكلفة
                'store_house_id' => $store_house_id,
                'item' => $product->name,
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'product_unit_id' => $unit['product_unit_id'],
                'unit_name' => $unit['unit_name'],
                'conversion_factor' => $unit['conversion_factor'],
                'quantity_in_base_unit' => $quantityInBaseUnit,
                'unit_price' => $unit_price,
                'discount' => $item_discount,
                'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                'tax_1' => floatval($item['tax_1'] ?? 0),
                'tax_2' => floatval($item['tax_2'] ?? 0),
                'total' => $item_total - $item_discount,
            ];

            /* ** تحديث المخزون لكل بند بناءً على المستودع المختار **
             *
             * الخدمي والرقمي لا رصيد لهما: كان صفُّ الرصيد يُفتح لكل بند،
             * فيرمي حارسُ `StockMovementGuardObserver` عند أوّل إشعارٍ
             * دائنٍ لخدمة ويُسقط الإشعار كلّه. */
            if ($storeHouse && StockEligibility::isStockable($product ?: $item['product_id'])) {
                $productDetails = ProductDetails::where('store_house_id', $storeHouse->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (! $productDetails) {
                    $productDetails = ProductDetails::create([
                        'store_house_id' => $storeHouse->id,
                        'product_id' => $item['product_id'],
                        'quantity' => 0,
                    ]);
                }

                if ($product->type == 'products') {
                    /* المخزونُ يُحرَّك بالوحدة الأساسية لا بالمُدخَلة: من ردَّ
                       كرتوناً فيه اثنتا عشرة حبّة يعيد إلى الرفّ اثنتي عشرة
                       لا واحدة. و`$quantityInBaseUnit` تساوي `$quantity`
                       تماماً حين لا وحدةَ للمنتج (المعامل ١). */
                    $stock_before = $productDetails->quantity;
                    $stock_after = $stock_before + $quantityInBaseUnit;

                    // زيادة كمية المنتج في المخزون
                    $productDetails->increment('quantity', $quantityInBaseUnit);

                    // تسجيل حركة المخزون
                    $wareHousePermits = new WarehousePermits;
                    $wareHousePermits->permission_source_id = PermissionSources::SALES_CREDIT_NOTE;
                    $wareHousePermits->permission_date = $validated['credit_date'];
                    $wareHousePermits->number = $credit_number;
                    $wareHousePermits->grand_total = $item_total;
                    $wareHousePermits->store_houses_id = $storeHouse->id;
                    $wareHousePermits->client_id = $validated['client_id'];
                    $wareHousePermits->reference_type = 'CreditNote';
                    $wareHousePermits->reference_id = null; // سيتم تحديثه لاحقاً
                    $wareHousePermits->created_by = auth()->user()->id;
                    // إذنٌ صادرٌ عن مستند لا طلبٌ ينتظر موافقة: الحركة وقعت
                    // وقُيِّدت محاسبياً مع المستند، فيُولَد موافَقاً عليه.
                    // (`apporod` هي قيمة الموافقة المعتمدة في النظام.)
                    $wareHousePermits->status = 'apporod';
                    $wareHousePermits->save();

                    /* الإذن بالوحدة الأساسية كحركة المخزون نفسها — كان بالكمية المُدخَلة
                       فيقول «كرتون» والمخزونُ زاد اثنتي عشرة حبّة، وسعرُه سعرُ الحبّة */
                    WarehousePermitsProducts::create([
                        'quantity' => $quantityInBaseUnit,
                        'total' => $item_total,
                        'unit_price' => $unit_price / ((float) $unit['conversion_factor'] ?: 1),
                        'product_id' => $item['product_id'],
                        'stock_before' => $stock_before,
                        'stock_after' => $stock_after,
                        'warehouse_permits_id' => $wareHousePermits->id,
                    ]);
                }
            }
        }

        /* ══════════ ترتيبُ الحساب ══════════
         *
         *     إجمالي الأصناف − خصومات الأصناف ⇒ صافي الأصناف
         *     − الخصم العامّ                   ⇒ صافي التوريد
         *     + الشحن                          ⇒ الوعاء الضريبي
         *     × النسبة                         ⇒ الضريبة
         *
         * والإشعارُ يعكس فاتورةً حُسبت بهذا الترتيب، فلو خالفه لعكس ضريبةً
         * غير التي حُصِّلت.
         */
        $lineNets = [];

        foreach ($request->items as $itemKey => $item) {
            $lineBase = floatval($item['quantity']) * floatval($item['unit_price']);

            $lineDiscount = 0;
            if (isset($item['discount']) && $item['discount'] > 0) {
                $lineDiscount = (isset($item['discount_type']) && $item['discount_type'] === 'percentage')
                    ? ($lineBase * floatval($item['discount'])) / 100
                    : floatval($item['discount']);
            }

            $lineNets[$itemKey] = max(0.0, $lineBase - $lineDiscount);
        }

        $net_items = round(array_sum($lineNets), 2);

        // ** الخطوة الثالثة: الخصم العامّ — قبل الوعاء الضريبي **
        $quote_discount = floatval($validated['discount_amount'] ?? 0);
        $discountType = $validated['discount_type'] ?? 'amount';
        if ($discountType === 'percentage') {
            // النسبة من **صافي الأصناف**، كما في الفاتورة وعرض السعر
            $quote_discount = ($net_items * $quote_discount) / 100;
        }

        $quote_discount = min($quote_discount, max(0.0, $net_items));

        // الخصومات الإجمالية
        $final_total_discount = $total_discount + $quote_discount;

        // حساب المبلغ بعد الخصم
        $amount_after_discount = $total_amount - $final_total_discount;

        $tax_type = $tax_type_map[$validated['tax_type']];
        $tax_rate = floatval($validated['tax_rate'] ?? 0); // الحصول على نسبة الضريبة من المستخدم

        /* ** ضريبة البنود ** — النسبة **ونوعها** من «إعدادات الضريبة»،
         * والوعاء بعد خصم البند **وبعد حصّته من الخصم العامّ**.
         *
         * كان الحساب `المبلغ × النسبة ÷ 100` على `$item_total` — أي
         * **قبل** خصم البند، خلافاً لفاتورة البيع التي تحسبها بعده.
         * فالإشعار الذي يعكس فاتورةً بخصمٍ على بنودها يعكس ضريبةً أكبر
         * ممّا حُصِّل. ويتجاهل النوع «متضمَّنة» فيُضيفها فوق السعر.
         */
        $discountShares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $quote_discount);

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

        // الضريبة المتضمَّنة تُطرح من المجموع فلا تُحسب مرّتين
        $total_amount = round($total_amount - $included_item_tax, 2);
        $amount_after_discount = round($total_amount - $final_total_discount, 2);

        // ** إضافة تكلفة الشحن (إذا وجدت) **
        $shipping_cost = floatval($validated['shipping_cost'] ?? 0);

        // ** حساب ضريبة الشحن (إذا كانت الضريبة مفعلة) **
        $shipping_tax = 0;
        if ($tax_type === 'vat') {
            // النسبة من «إعدادات الضريبة» — و`tax_rate` المرسلة احتياطاً
            $shipping_tax = DocumentTaxCalculator::shippingTax(
                $shipping_cost,
                /* «بلا ضريبة» اختيارٌ لا سكوت — انظر `shippingTaxIds()` */
                DocumentTaxCalculator::shippingTaxIds(
                    $request->has('shipping_tax_id'),
                    $request->input('shipping_tax_id'),
                    $documentTaxIds
                ),
                $tax_rate,
            );
        }

        // ** إضافة ضريبة الشحن إلى tax_total **
        $tax_total = round($tax_total + $shipping_tax, 2);

        // ** الحساب النهائي للمجموع الكلي **
        $total_with_tax = $amount_after_discount + $tax_total + $shipping_cost;

        // ** الخطوة الرابعة: إنشاء العرض في قاعدة البيانات **
        /*
         * حمولة الإنشاء.
         *
         * كانت الضريبة تُمرَّر باسم `tax_total` — وهو ليس عموداً؛
         * العمود `total_tax` ولا يصله شيء. و`shipping_tax` و`tax_rate`
         * لم تكونا عمودين ولا في `$fillable`، فتُرمى الثلاثة بصمت.
         * والنتيجة أنّ ضريبة كل إشعار دائن محفوظةٌ `null`.
         */
        $creditNot = CreditNotification::create([
            'client_id' => $validated['client_id'],
            // الفرع يُجمَّد مع الإشعار
            'branch_id' => current_branch_id(),
            // الفاتورة التي صدر عنها الإشعار — كان الربط مفقوداً
            'invoice_id' => $request->input('invoice_id') ?: null,
            'credit_number' => $credit_number,
            // العملة من «العملات»، وتُجمَّد مع المستند
            'currency' => $this->defaultCurrency(),
            'release_date' => $validated['release_date'],
            'credit_date' => $validated['credit_date'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
            'employee_id' => $request->input('employee_id') ?: Auth::user()->employee_id,
            'store_house_id' => $storeHouse->id ?? null,
            'discount_amount' => $quote_discount,
            'discount_type' => $discountType === 'percentage' ? 2 : 1,
            'shipping_cost' => $shipping_cost,
            'shipping_tax' => $shipping_tax,
            'tax_type' => $validated['tax_type'], // نحفظ الرقم في قاعدة البيانات
            'tax_rate' => $tax_rate, // حفظ نسبة الضريبة
            'subtotal' => $total_amount,
            'total_discount' => $final_total_discount,
            'total_tax' => $tax_total,
            'grand_total' => $total_with_tax,
            // ردّ المبلغ: طريقته وخزينته من «طرق الدفع» و«الخزائن»
            'payment_method' => $request->input('payment_method') ?: null,
            'treasury_id' => $request->input('treasury_id') ?: null,
            // الإشعار يأخذ أثره الكامل فور الحفظ — قيدُه المحاسبي وحركةُ
            // مخزونه تُنشآن هنا مباشرةً بلا خطوة اعتمادٍ لاحقة، فحالتُه
            // الطبيعية «معتمَد» (3) لا «مسودة» (1). راجع تعليق الإلغاء
            // أدناه: يعامل الحالة 3 على أنها الحالة السَّوِيّة غير الملغاة.
            'status' => 3,
        ]);

        // تعليم زيارة اليوم مكتملة — إشعارٌ دائن دليلٌ على معاملة اليوم
        // كالفاتورة والسند تماماً، ولم يكن يُعلِّم شيئاً قبل هذا
        EmployeeClientVisit::markCompletedFor(Auth::id(), (int) $validated['client_id']);

        // تحديث reference_id في warehouse_permits
        WarehousePermits::where('number', $credit_number)
            ->where('permission_source_id', PermissionSources::SALES_CREDIT_NOTE)
            ->update(['reference_id' => $creditNot->id]);

        foreach ($request->items as $item) {
            // حساب الإجمالي لكل منتج (السعر × الكمية)
            $item_subtotal = $item['unit_price'] * $item['quantity'];

            // حساب قيمة الضريبة 1 إن وجدت
            if (! empty($item['tax_1_id'])) {
                $tax1 = TaxSitting::find($item['tax_1_id']);
                if ($tax1) {
                    $tax_value1 = ($tax1->tax / 100) * $item_subtotal; // حساب قيمة الضريبة كنسبة مئوية من المجموع الجزئي للمنتج
                    TaxInvoice::create([
                        'name' => $tax1->name,
                        'invoice_id' => $creditNot->id,
                        'type' => $tax1->type,
                        'rate' => $tax1->tax,
                        'value' => $tax_value1,
                        'type_invoice' => 'credit',
                    ]);
                }
            }

            // حساب قيمة الضريبة 2 إن وجدت
            if (! empty($item['tax_2_id'])) {
                $tax2 = TaxSitting::find($item['tax_2_id']);
                if ($tax2) {
                    $tax_value2 = ($tax2->tax / 100) * $item_subtotal; // حساب قيمة الضريبة كنسبة مئوية من المجموع الجزئي للمنتج
                    TaxInvoice::create([
                        'name' => $tax2->name,
                        'invoice_id' => $creditNot->id,
                        'type' => $tax2->type,
                        'rate' => $tax2->tax,
                        'value' => $tax_value2,
                        'type_invoice' => 'credit',
                    ]);
                }
            }
        }

        // ** الخطوة الخامسة: إنشاء سجلات البنود (items) للعرض **
        foreach ($items_data as $item) {
            $item['credit_note_id'] = $creditNot->id;
            InvoiceItem::create($item);
        }

        /*
         * الحسابات من شاشة «الحسابات المحاسبية» وحدها.
         *
         * كانت تُقرأ بمعرّفاتها المكتوبة (37، 16، 50، 45، 14)، فما
         * يربطه المحاسب في الشاشة لا يسري على قيدٍ واحد من قيود
         * الإشعارات الدائنة. والرسالة عند الغياب صارت تسمّي الحساب
         * وتدلّ على شاشته بدل «حساب كذا غير موجود».
         */
        $vatAccount = $this->requireAccountingAccount('vat_payable');
        $costAccount = $this->requireAccountingAccount('cost_of_sales');
        $salesReturnsAccount = $this->requireAccountingAccount('sales_returns');
        $mainAccount = $this->requireAccountingAccount('main_treasury');

        // حساب المخزون: المضبوط في الإعدادات، وإلا حساب المستودع
        // الرئيسي — فالمنشأة التي تُنشئ حساباً لكل مستودع لا تربط
        // مفتاحاً عامّاً للمخزون
        $inventoryAccount = $this->accountingAccount('inventory');

        if (! $inventoryAccount) {
            $mainStoreHouse = StoreHouse::where('major', 1)->first();

            $inventoryAccount = $mainStoreHouse
                ? Account::where('storehouse_id', $mainStoreHouse->id)->first()
                : null;

            if (! $inventoryAccount) {
                $inventoryAccount = $this->requireAccountingAccount('inventory');
            }
        }

        $clientaccounts = Account::ledger()->where('client_id', $creditNot->client_id)->first();

        /* ** خطّةُ التسوية ** — الرصيدُ السابق ثمّ الفواتير من الأقدم.
           تُحسب هنا **قبل** قيد الإشعار: الرصيدُ السابق مشتقٌّ من رصيد
           الحساب، والقيدُ التالي يُنزله. وتُنفَّذ بعد القيد لتُربط به. */
        $settlement = app(ClientDebtSettlement::class);
        $settlementPlan = $settlement->plan(
            (int) $creditNot->client_id,
            (float) $creditNot->grand_total,
            $creditNot->invoice_id ? (int) $creditNot->invoice_id : null,
        );

        // القيد الاول: قيد مردود المبيعات والعميل
        $creditClientName = $clientaccounts ? ($clientaccounts->name ?? 'العميل') : 'العميل';
        // «نوع المستند رقمه - الطرف»؛ المبالغ في عمودَي مدين/دائن والتاريخ في `date`
        /* رقمُ الإشعار ورقمُ فاتورته المرجعية معاً في وصف كلّ قيد — كما في
           مرتجع المبيعات. والإشعارُ المستقلّ (بلا فاتورة) يبقى برقمه وحده. */
        $creditRef = $creditNot->credit_number.($referenced?->code
            ? ' للفاتورة الأصلية رقم '.$referenced->code
            : '');
        $creditDescription = 'إشعار دائن رقم '.$creditRef.' - '.$creditClientName;

        $journalEntry = JournalEntry::create([
            'reference_number' => $creditNot->id,
            // بتاريخ المستند: الإقفالُ المحاسبيّ يُفحص به، والإيرادُ يُعكس في شهره
            'date' => $validated['credit_date'],
            'description' => $creditDescription,
            'status' => 1,
            'currency' => $this->documentCurrency($creditNot),
            'client_id' => $creditNot->client_id,
            'reference_type' => ReferenceType::CREDIT_NOTE,
            'linking_type' => LinkingType::SINGLE,
            'created_by_employee' => Auth::id(),
        ]);

        // 1. حساب مردود المبيعات (مدين)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $salesReturnsAccount->id,
            'description' => 'مردود مبيعات - إشعار دائن رقم '.$creditRef,
            'debit' => $creditNot->subtotal, // المبلغ الكامل قبل الخصم
            'credit' => 0,
            'is_debit' => true,
        ]);

        // 2. حساب العميل (دائن)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $clientaccounts->id,
            'description' => 'تخفيض مديونية العميل - إشعار دائن رقم '.$creditRef,
            'debit' => 0,
            'credit' => $creditNot->grand_total, // المبلغ بعد الخصم والضريبة
            'is_debit' => false,
        ]);

        // 3. خصم مسموح به (دائن) - عكس الخصم الذي حمله البيع
        if ($creditNot->total_discount > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                // كان ٥٢ مكتوباً في الشيفرة، وهو مفتاحٌ في «الحسابات المحاسبية»
                'account_id' => $this->requireAccountingAccountId('allowed_discount'),
                'description' => 'عكس خصم مسموح به - إشعار دائن رقم '.$creditRef,
                'debit' => 0,
                'credit' => $creditNot->total_discount,
                'is_debit' => false,
            ]);
        }

        /* 4. القيمة المضافة — **مدينة** لا دائنة، وبالعمود `total_tax`.
         *
         * علّتان في سطرٍ واحد:
         *   • `$creditNot->tax_total` ليس عموداً في `credit_notifications`
         *     — العمود `total_tax`. فالشرط `> 0` كان يُقيَّم على `null`
         *     ولا يُكتب سطر الضريبة **أبداً**.
         *   • واتجاهه دائن، والإشعار يعكس بيعاً قُيِّدت ضريبتُه دائنةً؛
         *     فعكسُها مدين كما في المرتجع.
         *
         * وأثرُهما معاً أنّ القيد كان يخرج غير موازن بمقدار
         * (الضريبة + الشحن) في كل إشعارٍ خاضع للضريبة.
         */
        /* وهي **مركَّبةٌ لا مجموعة**: ضريبةُ البنود على `vat_payable`
           وضريبةُ الشحن على «ضريبة شحن مبيعات» — بالتركيب نفسِه الذي في
           قيد الفاتورة. كان `total_tax` كاملاً في سطرٍ واحد (والشحنُ
           مضمومٌ إليه قبل الحفظ)، فيُفرَّغ من «ضريبة المبيعات» ما لم
           يدخلها ويبقى في «ضريبة شحن مبيعات» ما لا يخرج منه أبداً.
           انظر `ResolvesAccountingAccounts::shippingVatAccountId()`. */
        $credit_shipping_tax = round((float) ($creditNot->shipping_tax ?? 0), 2);
        $credit_items_tax = round((float) ($creditNot->total_tax ?? 0) - $credit_shipping_tax, 2);

        if ($credit_items_tax > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $vatAccount->id,
                'description' => 'عكس ضريبة القيمة المضافة على البنود - إشعار دائن رقم '.$creditRef,
                'debit' => $credit_items_tax,
                'credit' => 0,
                'is_debit' => true,
            ]);
        }

        if ($credit_shipping_tax > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                // حسابُ ضريبة الشحن إن رُبط، وإلّا حسابُ الضريبة نفسُه
                'account_id' => $this->shippingVatAccountId(),
                'description' => 'عكس ضريبة شحن مبيعات - إشعار دائن رقم '.$creditRef,
                'debit' => $credit_shipping_tax,
                'credit' => 0,
                'is_debit' => true,
            ]);
        }

        /* 5. مردود إيراد الشحن (مدين) — الشحن يدخل إجمالي الإشعار
           ولم يكن له طرفٌ في القيد */
        if (($creditNot->shipping_cost ?? 0) > 0) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $this->requireAccountingAccountId('shipping_revenue_returns'),
                'description' => 'مردود إيراد شحن - إشعار دائن رقم '.$creditRef,
                'debit' => $creditNot->shipping_cost,
                'credit' => 0,
                'is_debit' => true,
            ]);
        }

        // ** تنفيذُ التسوية ** — صفوفُها وحالةُ الفواتير وربطُ القيد بها
        $settlement->apply($creditNot, $settlementPlan, $journalEntry->id);

        // if ($clientaccounts) {
        //     $clientaccounts->balance -= $creditNot->grand_total;
        //     $clientaccounts->save();
        // }

        // if ($inventoryAccount) {
        //     $inventoryAccount->balance += $creditNot->subtotal;
        //     $inventoryAccount->save();
        // }

        // if ($salesReturnsAccount) {
        //     $salesReturnsAccount->balance += $creditNot->subtotal;
        //     $salesReturnsAccount->save();
        // }

        // ====================================================================
        // قيد المخزون وتكلفة المبيعات (منفصل وبسعر التكلفة)
        // ====================================================================
        try {
            Log::info('بدء حساب قيد المخزون وتكلفة المبيعات للإشعار الدائن', ['credit_note_id' => $creditNot->id]);

            $costTotal = 0; // إجمالي التكلفة بسعر التكلفة
            $lineCosts = []; // الاحتفاظ بتفاصيل التكلفة لكل بند
            $inventoryAccountId = null; // حساب المخزون المرتبط بالمستودع

            // جلب البنود من قاعدة البيانات
            $creditItemsForCost = InvoiceItem::where('credit_note_id', $creditNot->id)->get();

            foreach ($creditItemsForCost as $costItem) {
                $productId = $costItem->product_id;
                $storeHouseId = $costItem->store_house_id ?? $storeHouse->id;
                // بالوحدة الأساسية — سعرُ التكلفة سعرُ الحبّة، والكميةُ قد تكون كراتين
                $quantity = $costItem->quantity_in_base_unit
                    ?? ($costItem->quantity * ((float) $costItem->conversion_factor ?: 1));

                $product = Product::find($productId);

                // البحث عن حساب المخزون من جدول الحسابات باستخدام storehouse_id
                if ($storeHouseId && $inventoryAccountId === null) {
                    $itemInventoryAccount = Account::where('storehouse_id', $storeHouseId)->first();
                    if ($itemInventoryAccount) {
                        $inventoryAccountId = $itemInventoryAccount->id;
                    }
                }

                /* سعرُ التكلفة لا سعرُ الشراء: ما قُيّد على بند الفاتورة
                   الأصلية إن كان الإشعارُ عليها، وإلّا متوسّطُ التكلفة الآن
                   (`ResolvesSaleCost`). */
                $unitCost = $this->returnUnitCost($creditNot->invoice_id ? (int) $creditNot->invoice_id : null, $product ?? $productId);

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

            // تحديد حساب المخزون بناءً على المستودع المختار للإشعار الدائن
            $inventoryAccountId = null;
            $targetStoreHouseId = $store_house_id;

            if ($targetStoreHouseId) {
                $inventoryAccount = Account::where('storehouse_id', $targetStoreHouseId)->first();
                if ($inventoryAccount) {
                    $inventoryAccountId = $inventoryAccount->id;
                    Log::info('تم استخدام حساب المخزون للمستودع المختار للإشعار الدائن', [
                        'storehouse_id' => $targetStoreHouseId,
                        'account_id' => $inventoryAccountId,
                    ]);
                }
            }

            if ($inventoryAccountId === null) {
                // جلب حساب المخزون من جدول الحسابات عبر المستودع الرئيسي (major = 1)
                $mainStoreHouse = StoreHouse::where('major', 1)->first();
                if ($mainStoreHouse) {
                    $inventoryAccount = Account::where('storehouse_id', $mainStoreHouse->id)->first();
                    if ($inventoryAccount) {
                        $inventoryAccountId = $inventoryAccount->id;
                    }
                }
            }

            if ($inventoryAccountId && $costTotal > 0) {
                // إنشاء قيد المخزون وتكلفة المبيعات للإشعار الدائن
                $costJournalEntry = JournalEntry::create([
                    'reference_number' => $creditNot->id,
                    'date' => $validated['credit_date'],
                    'description' => 'تكلفة مبيعات - إشعار دائن رقم '.$creditRef.' - '.$creditClientName,
                    'status' => 1,
                    'currency' => $this->documentCurrency($creditNot),
                    'client_id' => $creditNot->client_id,
                    'reference_type' => ReferenceType::CREDIT_NOTE,
                    'linking_type' => LinkingType::SINGLE,
                    'created_by_employee' => Auth::id(),
                ]);

                // 1. المخزون (مدين) - زيادة المخزون بسعر التكلفة
                JournalEntryDetail::create([
                    'journal_entry_id' => $costJournalEntry->id,
                    'account_id' => $inventoryAccountId,
                    'description' => 'إرجاع البضاعة للمخزون - إشعار دائن رقم '.$creditRef,
                    'debit' => $costTotal,
                    'credit' => 0,
                    'is_debit' => true,
                ]);

                // 2. تكلفة المبيعات (دائن) - عكس التكلفة بسعر التكلفة
                JournalEntryDetail::create([
                    'journal_entry_id' => $costJournalEntry->id,
                    'account_id' => $this->requireAccountingAccountId('cost_of_sales'),
                    'description' => 'عكس تكلفة المبيعات - إشعار دائن رقم '.$creditRef,
                    'debit' => 0,
                    'credit' => $costTotal,
                    'is_debit' => false,
                ]);

                Log::info('تم إنشاء قيد المخزون وتكلفة المبيعات للإشعار الدائن', [
                    'credit_note_id' => $creditNot->id,
                    'cost_total' => $costTotal,
                    'inventory_account_id' => $inventoryAccountId,
                    'lines' => $lineCosts,
                ]);

                /* لا تعديلَ يدويّاً للأرصدة: كلُّ سطرٍ يُعيد حساب رصيد حسابه من القيود
                   عند إنشائه (`JournalEntryDetail::boot()`). وكان `+=` هنا على حسابٍ
                   حُمِّل **بعد** ذلك الحساب، فتُضاعَف حركةُ المخزون وتكلفة المبيعات. */

            } else {
                Log::warning('تم تخطي إنشاء قيد المخزون لعدم توفر حساب مخزون أو عدم وجود تكلفة موجبة', [
                    'credit_note_id' => $creditNot->id,
                    'inventory_account_id' => $inventoryAccountId,
                    'cost_total' => $costTotal,
                ]);
            }

        } catch (\Exception $eCost) {
            Log::error('خطأ أثناء إنشاء قيد المخزون وتكلفة المبيعات للإشعار الدائن', [
                'credit_note_id' => $creditNot->id ?? null,
                'message' => $eCost->getMessage(),
                'trace' => $eCost->getTraceAsString(),
            ]);

            // لا يُبتلع: إشعارٌ بلا عكس تكلفته يُدخل البضاعةَ ويُبقي تكلفةَ بيعٍ رُدّ
            throw $eCost;
        }
        // ** إضافة ملاحظة تلقائية للعميل مع حساب العهدة التراكمية (خصم كمية المنتجات) **
        try {
            // حساب إجمالي كمية المنتجات في اشعار الدائن
            $totalQuantityInCredit = 0;
            foreach ($items_data as $item) {
                $totalQuantityInCredit += intval($item['quantity'] ?? 0);
            }

            // جلب آخر عهدة مسجلة للعميل
            $lastClientNote = ClientRelation::where('client_id', $creditNot->client_id)
                ->whereNotNull('deposit_count')
                ->where('deposit_count', '>', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            // حساب العهدة الجديدة = آخر عهدة - كمية المنتجات في الاشعار
            $previousDeposit = $lastClientNote ? intval($lastClientNote->deposit_count) : 0;
            $newDeposit = max(0, $previousDeposit - $totalQuantityInCredit);

            // إنشاء الملاحظة التلقائية
            ClientRelation::create([
                'client_id' => $creditNot->client_id,
                'employee_id' => auth()->id(),
                'type' => 'invoice', // تم التعديل ليظهر في سجل العميل كفاتورة
                'invoice_id' => null,
                'description' => 'اشعار دائن رقم: '.$creditNot->credit_number.' - الكمية: '.$totalQuantityInCredit.' قطعة - القيمة: '.number_format($creditNot->grand_total, 2).' ر.س',
                'deposit_count' => $newDeposit,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'status' => 'completed',
                'process' => 'اشعار دائن',
                'additional_data' => ['credit_notification_id' => $creditNot->id],
            ]);

            Log::info('تم إنشاء ملاحظة تلقائية لاشعار الدائن', [
                'client_id' => $creditNot->client_id,
                'credit_note_id' => $creditNot->id,
                'previous_deposit' => $previousDeposit,
                'credit_quantity' => $totalQuantityInCredit,
                'new_deposit' => $newDeposit,
            ]);

        } catch (\Exception $noteException) {
            Log::warning('فشل في إنشاء ملاحظة اشعار الدائن: '.$noteException->getMessage());
        }

        // ** معالجة نقاط معالجة المرتجع للموظف الحالي **
        try {
            app(PointsService::class)->processAction('return_processed', [
                'employee_id' => Auth::id(),
                'amount' => $creditNot->grand_total,
                'metadata' => [
                    'credit_note_id' => $creditNot->id,
                    'credit_number' => $creditNot->credit_number,
                    'client_id' => $creditNot->client_id,
                    'processed_by' => Auth::id(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('فشل في معالجة نقاط معالجة المرتجع: '.$e->getMessage());
        }

        DB::commit();

        // السجلّات الثلاثة معاً — نقطة تسجيل واحدة.
        //
        // كان الإشعار يُحفَظ ولا يُكتب له سطر في أيّ سجلّ: شاشات
        // «سجلّ النشاط» و«سجلّ الأحداث» في الإعدادات لا تعرض من
        // الإشعارات الدائنة شيئاً، فلا يُعرف من أصدر إشعاراً ولا متى
        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $creditNot,
            'إنشاء إشعار دائن رقم '.$creditNot->credit_number
                .' — العميل: '.($creditNot->client->trade_name ?? '—')
                .' — الإجمالي: '.CurrencySettings::format($creditNot->grand_total),
        );

        // انظر التعليق نفسه في `InvoicesController::persistInvoice()`
        $zatcaNotice = \App\Services\Zatca\ZatcaInvoiceService::noticeFor($creditNot);

        $response = redirect()->route('CreditNotes.index')->with('success', 'تم إنشاء اشعار دائن  بنجاح');

        return $zatcaNotice
            ? $response->with('warning', __('zatca.invoice.saved_not_sent', ['reason' => $zatcaNotice]))
            : $response;
    }

    public function cancel($id)
    {
        DB::beginTransaction();

        try {
            // جلب إشعار الدائن
            $creditNote = CreditNotification::findOrFail($id);

            /* إشعارٌ قبلته هيئة الزكاة والضريبة والجمارك لا يُلغى: يبقى
               قائماً عندها وقد مُحي أثره عندنا. */
            if (\App\Services\Zatca\ZatcaGuard::isAccepted(\App\Models\ZatcaDocument::TYPE_CREDIT_NOTE, (int) $creditNote->id)) {
                DB::rollBack();

                return redirect()->back()->with('error', __('zatca.errors.cancel_accepted'));
            }

            /* الفترة المحاسبية: القيد العكسي يُرحَّل بتاريخ **اليوم** لا
               بتاريخ الإشعار — فالمفحوص هو اليوم. وهو نظير ما يفعله
               `StorePermitsManagementController::destroy()` عند عكس إذن.

               وكان الإلغاء يمرّ بلا فحصٍ إطلاقاً: يُعكَس قيدُ إشعارٍ في
               سنةٍ أُقفلت وأُصدرت قوائمها، وهو عين ما يمنعه الإقفال. */
            if ($block = $this->accountingPeriodBlock(now())) {
                DB::rollBack();

                return redirect()->back()->with('error', $block);
            }

            // التحقق من صلاحيات المستخدم - منع من دون صلاحية النطاق الكامل من الإلغاء
            $user = Auth::user();

            // ✅ hasRolePermission('data_scope_all') بدلاً من دور ثابت — من يُمنح النطاق الكامل صراحةً يستطيع الإلغاء
            // الرفضُ يُغلق المعاملة التي فُتحت أعلاه — كان يعود وهي مفتوحة
            if (! $user->hasRolePermission('data_scope_all')) {
                DB::rollBack();

                return redirect()->back()->with('error', 'عذراً، لا يمكن للموظفين إلغاء الإشعارات');
            }

            /* الحالات الملغاة `0` و`5` لا `0` و`3`.
               كان الشرط يمنع إلغاء إشعارٍ حالته `3` — و`3` «معتمَد» في
               `getStatusTextAttribute()` لا «محذوف»: أي أنّ الحالة
               الطبيعية للإشعار المعتمَد كانت تُرفض بحجّة أنّه ملغيّ
               مسبقاً. وفي المقابل يمرّ إشعارٌ حالته `5` (ملغيّ) فيُعكَس
               قيدُه مرّةً ثانية وتُرجَع كمّياتُه مرّتين. */
            if (in_array((int) $creditNote->status, [0, 5], true)) {
                DB::rollBack();

                return redirect()->back()->with('error', 'هذا الإشعار ملغي مسبقاً');
            }

            /* ** الخطوة 1: عكس المخزون — بالوحدة الأساسية، ولا يُلغى ما خرج من المستودع بعده **
             *
             * كان يخصم `quantity` (فالكرتون حبّة)، ويرمي حين لا إذنَ أصليّاً فلا
             * يُلغى إشعارُ خدماتٍ أبداً، ويسجّل الإذنَ العكسيَّ بمصدر «إشعار مدين
             * مشتريات» (١٢)، ويهبط بالرصيد تحت الصفر إن بيعت البضاعةُ بعد الإشعار.
             */
            $items = InvoiceItem::where('credit_note_id', $creditNote->id)->get();

            $storeHouse = StoreHouse::find($creditNote->store_house_id)
                ?: StoreHouse::find(WarehousePermits::where('number', $creditNote->credit_number)
                    ->where('permission_source_id', PermissionSources::SALES_CREDIT_NOTE)
                    ->value('store_houses_id'));

            foreach ($items as $item) {
                $product = Product::find($item->product_id);

                if (! $product || $product->type !== 'products') {
                    continue;
                }

                if (! $storeHouse) {
                    throw new \RuntimeException(__('sales_invoice.ui.no_storehouse'));
                }

                $factor = (float) $item->conversion_factor ?: 1.0;
                $baseQuantity = (float) ($item->quantity_in_base_unit ?: (float) $item->quantity * $factor);

                $productDetails = ProductDetails::where('store_house_id', $storeHouse->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                $stock_before = (float) ($productDetails->quantity ?? 0);

                if ($baseQuantity - $stock_before > 0.0001) {
                    throw new \RuntimeException(__('sales_invoice.return_limits.cancel_stock_short', [
                        'item' => $product->name,
                        'store' => $storeHouse->name,
                        'available' => rtrim(rtrim(number_format($stock_before, 4, '.', ''), '0'), '.') ?: '0',
                        'needed' => rtrim(rtrim(number_format($baseQuantity, 4, '.', ''), '0'), '.'),
                    ]));
                }

                $productDetails->decrement('quantity', $baseQuantity);

                // إذنُ صرفٍ مخزنيّ — الإلغاءُ يُخرج ما أدخله الإشعار
                $wareHousePermits = new WarehousePermits;
                $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_DISBURSEMENT;
                $wareHousePermits->permission_date = now();
                $wareHousePermits->number = 'CANCEL-'.$creditNote->credit_number;
                $wareHousePermits->grand_total = (float) $item->total;
                $wareHousePermits->store_houses_id = $storeHouse->id;
                $wareHousePermits->client_id = $creditNote->client_id;
                $wareHousePermits->reference_type = 'CreditNoteCancel';
                $wareHousePermits->reference_id = $creditNote->id;
                $wareHousePermits->created_by = auth()->user()->id;
                // الحركة وقعت مع الإلغاء، فيُولَد الإذنُ موافَقاً عليه (`apporod`)
                $wareHousePermits->status = 'apporod';
                $wareHousePermits->save();

                WarehousePermitsProducts::create([
                    'quantity' => $baseQuantity,
                    'total' => (float) $item->total,
                    'unit_price' => (float) $item->unit_price / $factor,
                    'product_id' => $item->product_id,
                    'stock_before' => $stock_before,
                    'stock_after' => $stock_before - $baseQuantity,
                    'warehouse_permits_id' => $wareHousePermits->id,
                ]);
            }

            // ** الخطوة 1ب: إلغاءُ التسوية — يُطرح ما أضافه الإشعار إلى «مرتجع» فواتيره **
            // قبل القيد العكسي: أنصبةُ الفواتير تُقرأ من روابط قيد الإشعار
            app(ClientDebtSettlement::class)->reverse($creditNote);

            /* ** الخطوة 2: قيدٌ عكسيّ لكل قيد — لا حذف **
             *
             * كانت سطورُ القيود تُحذف بـ`delete()` على مستوى الاستعلام — لا تُطلق
             * `JournalEntryDetail::boot()` فلا يُعاد حسابُ رصيد — ثمّ تُعدَّل الأرصدة
             * يدوياً بمبالغ خاطئة (المخزون بالإجمالي قبل الخصم، والتكلفة بالإجمالي مع
             * الضريبة، والضريبةُ لا تُعكس). وكان الحذفُ يمحو الإشعار من الدفاتر كأنه
             * لم يكن. القيدُ العكسيّ يُعيد كلَّ حسابٍ بالمبلغ نفسه ويبقى الأصلُ مقروءاً،
             * بنمط «قيد عكسي لإلغاء دفعة» في `SalesPaymentController`.
             *
             * وبنوع القيد: فلترُ `reference_number` وحده كان يلتقط قيدَ فاتورة بيعٍ
             * رقمُ مرجعها رقمُ الإشعار نفسه.
             */
            $journalEntries = JournalEntry::where('reference_number', $creditNote->id)
                ->where('client_id', $creditNote->client_id)
                ->where('reference_type', ReferenceType::CREDIT_NOTE)
                ->get();

            foreach ($journalEntries as $journalEntry) {
                $details = JournalEntryDetail::where('journal_entry_id', $journalEntry->id)->get();

                if ($details->isEmpty()) {
                    continue;
                }

                $reversalEntry = JournalEntry::create([
                    'reference_number' => 'REV-'.$creditNote->id,
                    'date' => now(),
                    'description' => 'قيد عكسي لإلغاء إشعار دائن '.$creditNote->credit_number.' - من القيد رقم #'.$journalEntry->id,
                    'status' => 1,
                    'currency' => $journalEntry->currency,
                    'client_id' => $creditNote->client_id,
                    'reference_type' => ReferenceType::CREDIT_NOTE,
                    'linking_type' => LinkingType::SINGLE,
                    'created_by_employee' => Auth::id(),
                ]);

                foreach ($details as $detail) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $reversalEntry->id,
                        'account_id' => $detail->account_id,
                        'description' => 'عكس: '.$detail->description,
                        'debit' => (float) $detail->credit,
                        'credit' => (float) $detail->debit,
                        'is_debit' => (float) $detail->credit > 0,
                    ]);
                }
            }

            // ** الخطوة 4: حذف سجلات الضرائب المرتبطة **
            TaxInvoice::where('invoice_id', $creditNote->id)
                ->where('type_invoice', 'credit')
                ->delete();

            // ** الخطوة 5: تحديث حالة إشعار الدائن إلى ملغي **
            $creditNote->status = 0; // 0 = ملغي
            $creditNote->created_by = Auth::id();
            // $creditNote->cancelled_at = now();
            $creditNote->save();

            DB::commit();

            OperationLog::record(
                'cancelled',
                self::LOG_MODULE,
                $creditNote,
                'إلغاء إشعار دائن رقم '.$creditNote->credit_number
                    .' — عُكست قيوده وأُرجعت كمّياته',
            );

            return redirect()->route('CreditNotes.index')
                ->with('success', 'تم إلغاء إشعار الدائن رقم '.$creditNote->credit_number.' بنجاح');
        } catch (\RuntimeException $e) {
            // قاعدةُ عملٍ لا عطل (رصيدٌ لا يسمح بالإلغاء) — تُقال كما هي
            DB::rollback();

            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            DB::rollback();

            // سجلّ الأخطاء وسجلّ الأحداث معاً — كان `Log::error` وحده،
            // فلا يظهر الخطأ في شاشة «سجلّ الأخطاء» التي بُنيت له
            OperationLog::failure($e, self::LOG_MODULE, 'cancel', (int) $id);

            return redirect()->back()
                ->with('error', 'عذراً، حدث خطأ أثناء إلغاء الإشعار: '.$e->getMessage());
        }
    }

    /**
     * طباعة الإشعار الدائن — بالقالب المصمَّم في «قوالب المبيعات».
     *
     * `$template` معرّفُ قالبٍ من الإعدادات فيُصيَّر، أو لا شيء فيُبنى
     * PDF بالقالب المدمج كما كان. القالب المدمج يبقى احتياطاً لا يُنزَع:
     * منشأةٌ لم تصمّم قالباً بعد يجب أن تطبع إشعارها.
     */
    public function print($id, $template = null)
    {
        /* المسار `/print/{id}` بلا مقطعٍ للقالب — فالقالب يصل في
           `?template=`. من دون هذه القراءة يبقى `$template` فارغاً دائماً
           وتُطبَع كلُّ الإشعارات بالقالب المدمج مهما اختار المستخدم في
           «قوالب المستندات». */
        $template = $template
            ?? request('template')
            ?? optional($this->defaultSalesTemplate(self::DOCUMENT_TYPE))->id;

        if (! $this->wantsBuiltinPrint($template) && ($html = $this->renderSalesTemplate(self::DOCUMENT_TYPE, $id, $template))) {
            OperationLog::record('printed', self::LOG_MODULE, (int) $id, 'طباعة إشعار دائن رقم '.$id);

            return response(DocumentPrintPage::wrap($html));
        }

        // try {
        $credit = CreditNotification::with(['client', 'createdBy'])->findOrFail($id);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'credit')->get();

        // تكوين خيارات PDF
        $config = new Options;
        $config->set('defaultFont', 'DejaVu Sans');
        $config->set('isRemoteEnabled', true);
        $config->set('isHtml5ParserEnabled', true);
        $config->set('isFontSubsettingEnabled', true);
        $config->set('isPhpEnabled', true);
        $config->set('chroot', [
            public_path(),
            base_path('vendor/dompdf/dompdf/lib/fonts/'),
            storage_path('fonts/'),
        ]);

        // إنشاء كائن Dompdf
        $dompdf = new Dompdf($config);

        // تحميل محتوى HTML مع ترميز UTF-8
        $data = [
            'credit' => $credit,
            'TaxsInvoice' => $TaxsInvoice,
            // بيانات المنشأة من «معلومات المنشأة» لا مكتوبةً هنا: كانت
            // «اسم شركتك» و«عنوان الشركة» و`path/to/your/logo.png` تخرج
            // على كل إشعار مطبوع
            'company_data' => [
                'company_name' => BusinessInfo::name(),
                'company_address' => BusinessInfo::address(),
                'company_phone' => BusinessInfo::phone(),
                'company_email' => BusinessInfo::email(),
                'company_logo' => BusinessInfo::logo(),
                'tax_number' => BusinessInfo::taxNumber(),
            ],
        ];
        $html = view('sales::creted_note.pdf', $data)->render();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        // تحميل HTML
        $dompdf->loadHtml($html);

        // تعيين حجم الصفحة
        $dompdf->setPaper('A4', 'portrait');

        // تنفيذ PDF
        $dompdf->render();

        // إرجاع الملف للتحميل
        return $dompdf->stream('credit_note_'.$credit->credit_number.'.pdf', [
            'Attachment' => false,
        ]);

        // } catch (\Exception $e) {
        return redirect()->back()->with('error', 'حدث خطأ أثناء إنشاء PDF: '.$e->getMessage());
        // }
    }

    /**
     * إرسال الإشعار الدائن إلى بريد العميل.
     *
     * ── ما كان ──
     *
     *   • الرابط يُبنى بـ`route('credits.print', …)` — اسمُ مسارٍ لا وجود
     *     له، فيرمي `RouteNotFoundException` قبل أن يُرسَل شيء.
     *   • ثمّ حين صُحّح الاسم، بقي المُرسَل **رابطاً** بقالب بلايدٍ مثبَّت
     *     (`CreditNotificationLinkMail`) — لا الإشعارَ نفسَه، ولا بالقالب
     *     الذي صُمّم له في «قوالب المستندات».
     *   • والزرُّ في الشاشة اسمُه «إرسال بالبريد» وتحته `https://wa.me/…`.
     *
     * ── وما صار ──
     *
     * `SalesDocumentMailer`: الإشعارُ بقالبه مرفقاً PDF، ونصُّ الرسالة من
     * «قوالب البريد»، والمستلِمُ يقبل التعديل في النافذة.
     */
    public function sendCreditNotification(Request $request, $id)
    {
        $credit = CreditNotification::with(['client', 'createdBy'])->findOrFail($id);

        $client = $credit->client;

        $response = $this->mailSalesDocument($request, 'credit_note', $credit->id, [
            'number' => (string) ($credit->credit_number ?: $credit->id),
            'client' => $client?->display_name ?? $client?->trade_name ?? '',
            'email' => $client->email ?? null,
            'client_id' => $credit->client_id,
            'total' => $credit->grand_total,
            'date' => $credit->credit_date ?? $credit->created_at,
            'link' => route('CreditNotes.print', $credit->id),
        ], $this->builtInCreditHtml($credit));

        if ($this->documentMailSucceeded()) {
            OperationLog::record('emailed', self::LOG_MODULE, (int) $credit->id, 'إرسال إشعار دائن رقم '.$credit->id.' بالبريد');
        }

        return $response;
    }

    /**
     * قالبُ الوحدة المدمج نصّاً — احتياطُ المرفق حين لا قالبَ مصمَّماً للنوع.
     *
     * `null` عند أي تعثّر: رسالةٌ بلا مرفق خيرٌ من رسالةٍ لا تخرج.
     */
    protected function builtInCreditHtml(CreditNotification $credit): ?string
    {
        try {
            return view('sales::creted_note.pdf', [
                'credit' => $credit,
                'TaxsInvoice' => TaxInvoice::where('invoice_id', $credit->id)->where('type_invoice', 'credit')->get(),
            ])->render();
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'email_builtin_html', (int) $credit->id);

            return null;
        }
    }

    public function showPrintable($id)
    {
        $credit = CreditNotification::with(['client', 'createdBy'])->findOrFail($id);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $id)->where('type_invoice', 'credit')->get();

        return view('sales::creted_note.pdf', compact('credit', 'TaxsInvoice'));
    }

    private function convertNumberToArabicWords($number)
    {
        $digit1 = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
        $digit2 = ['', 'عشر', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $digit3 = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

        // تنفيذ المنطق الخاص بتحويل الرقم إلى كلمات
        // يمكنك استخدام مكتبة جاهزة مثل khaled.alshamaa/ar-php

        return ''; // قم بتنفيذ المنطق المناسب هنا
    }
}
