<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\planFeatures\SalesPlanFeatures;
use App\Http\Controllers\Controller;
use App\Models\AccountSetting;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\PeriodicInvoice;
use App\Models\PeriodicInvoiceInstance;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesInvoiceSetting;
use App\Models\StoreHouse;
use App\Models\TaxSitting;
use App\Models\Treasury;
use App\Services\Clients\ClientVisibilityScope;
use App\Services\Invoices\PeriodicInvoiceGenerator;
use App\Services\Tax\DocumentTaxCalculator;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Support\SalesWarehouse;

/**
 * الفواتير الدورية.
 *
 * الفاتورة الدورية قالبُ اشتراكٍ تُصدَر منه فواتيرُ على فترات، فتحتاج ما
 * تحتاجه الفاتورة من إعدادات:
 *
 *   • *الرقم*        ← «تسلسل أرقام المستندات»، القسم `periodic-invoice`
 *                      (`GeneratesDocumentNumbers`).
 *   • *العملة*       ← «العملات»، وتُجمَّد مع المستند
 *                      (`UsesDocumentCurrency`).
 *   • *الحسابات*     ← «الحسابات المحاسبية» وحدها
 *                      (`ResolvesAccountingAccounts`).
 *   • *الإقفال*      ← «الفترات المحاسبية»: تاريخُ أوّل فاتورة في فترةٍ
 *                      مقفلة يردّ الحفظ (`RespectsAccountingPeriods`).
 *   • *القوالب*      ← «قوالب المبيعات» (`RendersSalesDocumentTemplates`).
 *   • *طرق الدفع*    ← «طرق الدفع» وخزائنها.
 *   • *السلوك*       ← «إعدادات الفواتير» (`SalesInvoiceSetting`).
 *   • *السجلّات*     ← `OperationLog`: النشاط والأحداث والأخطاء.
 *   • *اللغة والمظهر* ← عبر القوالب: `sl-*` مبنيّة على `--app-*`.
 */
class PeriodicInvoicesController extends Controller
{
    use Concerns\ChecksSubscriptionLimits;
    use Concerns\GeneratesDocumentNumbers;
    use Concerns\GuardsClientScope;
    use Concerns\RendersSalesDocumentTemplates;
    use Concerns\ResolvesAccountingAccounts;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\ScopesSalesVisibility;
    use Concerns\UsesDocumentCurrency;

    /** قسم الترقيم المتسلسل الذي تُؤخذ منه أرقام الفواتير الدورية */
    public const NUMBER_SECTION = 'periodic-invoice';

    /** نوع المستند في «قوالب المستندات» */
    public const DOCUMENT_TYPE = 'sales_invoice';

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'SalesPeriodicInvoices';

    /**
     * «خاضع للقيمة المضافة» — قيمة `periodic_invoices.tax_type` الوحيدة.
     *
     * كان مُنتقياً في تبويب الشحن يختار بين خاضعٍ وصفريٍّ ومعفى، وأُسقط
     * بطلب المستخدم: ضريبةُ كلّ بندٍ مختارةٌ في صفّه من «إعدادات الضريبة»،
     * فمفتاحٌ على المستند كلِّه يُلغيها من فوقها بلا أن يظهر أثرُه في
     * الصفوف. والعمود باقٍ فتُقرأ الاشتراكات القديمة بقيمها.
     */
    public const TAX_TYPE_VAT = '1';

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.periodic_invoices.* — نفس نمط InvoicesPurchaseController
        // (كانت محروسةً بـ`auth` فقط؛ التحقّق الفعلي inline في index() وحدها)
        $this->middleware('permission:sales.periodic_invoices.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.periodic_invoices.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.periodic_invoices.edit', ['only' => ['edit', 'update', 'toggle', 'generateNow']]);
        $this->middleware('permission:sales.periodic_invoices.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.periodic_invoices.bulk_delete', ['only' => ['bulkDelete']]);
    }

    /**
     * عدد السجلّات في الصفحة — من قائمة مغلقة.
     *
     * كان `paginate(10)` ثابتاً لا يقرأ `per_page` إطلاقاً، فمُنتقي عدد
     * السجلّات في الجدول لا يفعل شيئاً.
     */
    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    /** طرق الدفع المفعَّلة في شاشة «طرق الدفع» */
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
            OperationLog::failure($e, 'Sales.PeriodicInvoices', 'activePaymentMethods');

            return collect();
        }
    }

    /**
     * شاشة الفواتير الدورية — على قياس شاشة فواتير المبيعات.
     *
     * ما أُصلح هنا:
     *
     *   • *`paginate(10)` ثابتة* — لا تقرأ `per_page` إطلاقاً، فمُنتقي
     *     عدد السجلّات في الجدول لا يفعل شيئاً.
     *   • *`appends()` مفقودة* — التنقّل بين الصفحات يُسقط الفلاتر.
     *   • *`hasPermissionTo()` مباشرةً* — ترمي `PermissionDoesNotExist`
     *     لاسمٍ لم يُزرع، فتُسقط الشاشة بخطأ 500 بدل أن تردّ بـ403.
     *   • *بلا فلتر عملة ولا فرع ولا حالة* — أُضيفت كما في شاشة الفواتير.
     *   • *بلا نطاق افتراضي* — تفتح على تاريخ المنشأة كلّه؛ صارت تبدأ من
     *     الفترة المحاسبية الجارية.
     *   • *بلا إحصاءات* — أُضيف صفّ البطاقات الثلاث.
     */
    public function index(Request $request)
    {
        // ✅ صلاحية الوصول محروسة أصلاً في المُنشئ (permission:sales.periodic_invoices.view)

        // النطاق الافتراضي: الفترة المحاسبية الجارية
        $range = $this->reportDateRange($request);

        // العملة تبدأ على عملة النظام، و«كل العملات» تُلغي التصفية
        $currency = $this->filterCurrency($request);

        /* «صدر منها» — النسخ التي لها فاتورة. الجدول يُكتب كاملاً عند الحفظ،
           فعدُّ الصفوف كلِّها يقول «صدرت ١٢ من ١٢» لاشتراكٍ لم يُصدر شيئاً. */
        $query = PeriodicInvoice::with(['client', 'creator'])
            ->withCount(['instances' => fn ($q) => $q->whereNotNull('invoice_id')]);

        /* الفرع النشط من الهيدر — نفس ما تطبّقه بقيّة شاشات المبيعات
           (الفواتير، عروض الأسعار، الإشعارات الدائنة، المدفوعات). */
        /* والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها،
           فمن يقف عليه يرى المبيعات كلَّها. */
        $this->applyHeaderBranchScope($query);

        /* نطاقُ الرؤية — السلَّمُ الخماسيّ: الكلّ، أو فرعُه، أو فريقُه، أو
           ما أنشأه ونُسب إليه. كان «الكلّ أو ما أنشأتُه». */
        $this->scopeSalesDocuments($query);

        $this->applySearchFilters($query, $request, $range, $currency);

        $periodicInvoices = $query->latest()
            ->paginate($this->perPage($request))
            ->appends($request->except('page'));

        // ── إحصاءات الشاشة ──
        $totalsQuery = clone $query;
        $periodicTotal = (float) $totalsQuery->sum('grand_total');
        $periodicCount = $periodicInvoices->total();

        $activeQuery = clone $query;
        $activeCount = (int) $activeQuery->where('is_active', true)->count();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => view('sales::periodic_invoices.partials.table', [
                    'periodicInvoices' => $periodicInvoices,
                    'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
                ])->render(),
                'current_page' => $periodicInvoices->currentPage(),
                'last_page' => $periodicInvoices->lastPage(),
                'total' => $periodicInvoices->total(),
                'from' => $periodicInvoices->firstItem(),
                'to' => $periodicInvoices->lastItem(),
                'per_page' => $periodicInvoices->perPage(),
                'periodic_total' => CurrencySettings::format($periodicTotal),
                'periodic_count' => $periodicCount,
                'active_count' => $activeCount,
            ]);
        }

        return view('sales::periodic_invoices.index', [
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::PERIODIC_INVOICES),
            'periodicInvoices' => $periodicInvoices,
            'clients' => $this->scopedClientsForSelect(),
            'items' => Product::all(),
            'account_setting' => AccountSetting::where('user_id', auth()->id())->first(),
            'range' => $range,
            'periodicTotal' => $periodicTotal,
            'periodicCount' => $periodicCount,
            'activeCount' => $activeCount,
            'currencies' => $this->activeCurrencies(),
            'selectedCurrency' => $currency,
            'branches' => Branch::where('status', 0)->select('id', 'name')->get(),
            'documentTemplates' => $this->salesTemplates(self::DOCUMENT_TYPE),
        ]);
    }

    /**
     * شروط البحث في شاشة الفواتير الدورية.
     *
     * @param  array{start: string, end: string}|null  $range
     * @param  string|null  $currency  رمز العملة، أو `null` لكل العملات
     */
    protected function applySearchFilters($query, Request $request, ?array $range = null, ?string $currency = null): void
    {
        if ($request->filled('name_subscription')) {
            $term = trim((string) $request->name_subscription);
            $query->where('details_subscription', 'like', $term.'%');
        }

        // بحث بادئة على الرقم المطبوع، ومطابقة تامّة على المعرّف
        if ($request->filled('invoice_number')) {
            $term = trim((string) $request->invoice_number);

            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term.'%');

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('repeat_type')) {
            $query->where('repeat_type', $request->repeat_type);
        }

        // الحالة: مفعَّلة أو موقوفة
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === '1');
        }

        $this->applyCurrencyFilter($query, $currency);

        // ✅ فرع المستند نفسه (مُجمَّدٌ وقت الإنشاء) — لا فرع مُنشئه الحاليّ
        if ($request->filled('branch')) {
            $query->where('branch_id', $request->branch);
        }

        if ($request->filled('min_total')) {
            $query->where('grand_total', '>=', $request->min_total);
        }

        if ($request->filled('max_total')) {
            $query->where('grand_total', '<=', $request->max_total);
        }

        /* النطاق على `first_invoice_date` لا على `created_at`: الفاتورة
           الدورية تُعرَف بموعد أوّل إصدارٍ لها لا بلحظة إدخالها. */
        if ($range) {
            $query->whereBetween('first_invoice_date', [$range['start'], $range['end']]);
        }

        /* ** الفترات المقفلة ** — تُستبعد من الشاشة كلّها لا من نطاقها
           الافتراضيّ وحده: من أقفل سنةً لا يريد مستنداتِها أن تعود بضبط
           التاريخ يدوياً. */
        $this->excludeClosedRanges($query, 'periodic_invoices.first_invoice_date');

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }
    }

    // دالة الحذف الجماعي
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:periodic_invoices,id',
        ]);

        try {
            PeriodicInvoice::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العناصر المحددة بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.PeriodicInvoices', 'bulkDelete');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف',
            ], 500);
        }
    }

    /**
     * وحدات كل منتج — لقائمة الوحدات في شاشتي الإنشاء والتعديل.
     *
     * لم يكن في نموذج الاشتراك حقلُ وحدةٍ إطلاقاً، فتصدر فواتيره بوحدةٍ
     * مجهولة ومعامل تحويلٍ واحد مهما كان المنتج يُباع بالكرتون. الخريطة
     * هنا مصدرُ القائمة في الواجهة.
     *
     * @return array<int, array<int, array{id:int, name:string, conversion_factor:float, is_base_unit:bool, sale_price:float}>>
     */
    protected function productUnitsMap($productIds = null): array
    {
        try {
            $products = Product::with(['productUnits.categoryUnit.unit'])
                ->when($productIds !== null, fn ($q) => $q->whereIn('id', collect($productIds)->all()))
                ->get();
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.PeriodicInvoices', 'productUnitsMap');

            return [];
        }

        $map = [];

        foreach ($products as $product) {
            $units = [];

            foreach ($product->productUnits ?? [] as $productUnit) {
                $categoryUnit = $productUnit->categoryUnit ?? null;

                if (! $categoryUnit || ! $categoryUnit->unit) {
                    continue;
                }

                $units[] = [
                    'id' => (int) $productUnit->id,
                    'name' => (string) $categoryUnit->unit->name,
                    'conversion_factor' => (float) ($categoryUnit->conversion_factor ?: 1),
                    'is_base_unit' => (bool) ($categoryUnit->is_base_unit ?? false),
                    // سعر بيع الوحدة — منه يتحدّث السعر عند اختيارها، كشاشة الفاتورة
                    'sale_price' => (float) ($productUnit->sale_price ?? 0),
                ];
            }

            if ($units !== []) {
                $map[(int) $product->id] = $units;
            }
        }

        return $map;
    }

    /**
     * عملاء مُنتقي العميل — بنفس الخدمة التي تقصر المُنتقي في الفاتورة
     * وعرض السعر والإشعار الدائن والمرتجع.
     *
     * كانت الشاشات الثلاث (الفهرس والإنشاء والتعديل) تقرأ `Client::all()`،
     * فمن لا يرى عميلاً في «إدارة العملاء» كان يجده هنا ويُنشئ له فاتورةً
     * دورية — والمُنتقي يُفشي أسماء عملاء المنشأة كلِّها لمن نطاقُه خطُّ
     * سيرٍ يوميّ أو عملاؤه هو.
     *
     * وعميلُ الفاتورة المفتوحة يبقى في قائمة شاشة التعديل مهما ضاق النطاق،
     * وإلّا فُتح المُنتقي فارغاً فحُفظ المستند بعميلٍ آخر.
     *
     * @see \App\Services\Clients\ClientVisibilityScope
     */
    protected function scopedClientsForSelect($keepClientId = null)
    {
        return ClientVisibilityScope::documentSelectClients(
            auth()->user(),
            [],
            null,
            $keepClientId,
        );
    }

    /**
     * قوائم النموذج — بالقواعد نفسها التي تبني بها شاشة «إنشاء فاتورة مبيعات» قوائمها.
     *
     * كانت الشاشتان تقرآن `Product::all()` و`StoreHouse::all()` و
     * `Employee::all()` و`Treasury::all()`: الموظّف يرى مستودعات المنشأة
     * وخزائنها وكلَّ موظّفيها وكلَّ أصنافها، والاشتراك يُصدر فواتير بيعٍ
     * تمرّ من حرّاس الفاتورة نفسها — فيُحفظ اشتراكٌ لا تصدر منه فاتورة.
     *
     *   • المستودعات ← `SalesWarehouse::optionsFor()` («مصدر مستودع البيع»)
     *   • الأصناف     ← `SalesWarehouse::productsQuery()` — أصناف مستودع الموظّف
     *   • الموظّفون   ← `DataScope::employeeOptions()` — سلَّم نطاق الرؤية
     *   • الخزائن     ← `TreasuryAccessGuard::depositable()`
     *
     * وفي التعديل يبقى ما حُفظ على الاشتراك ظاهراً ولو خرج من النطاق، وإلّا
     * فُتح المُنتقي فارغاً وحُفظ الاشتراك بغيره.
     *
     * @return array<string, mixed>
     */
    protected function formLists(?PeriodicInvoice $periodicInvoice = null): array
    {
        $user = auth()->user();

        $keepProductIds = $periodicInvoice ? $periodicInvoice->items->pluck('product_id')->filter()->all() : [];

        $items = SalesWarehouse::productsQuery([], $user, $keepProductIds)->get();

        $storeHouses = $user && $user->employee_id ? SalesWarehouse::optionsFor($user) : StoreHouse::all();

        if ($periodicInvoice && $periodicInvoice->store_house_id && ! $storeHouses->contains('id', $periodicInvoice->store_house_id)) {
            $storeHouses = $storeHouses->concat(StoreHouse::whereKey($periodicInvoice->store_house_id)->get());
        }

        $employees = DataScope::employeeOptions();

        if ($periodicInvoice && $periodicInvoice->employee_id && ! $employees->contains('id', $periodicInvoice->employee_id)) {
            $employees = $employees->concat(Employee::whereKey($periodicInvoice->employee_id)->get());
        }

        $treasuries = TreasuryAccessGuard::depositable();

        if ($periodicInvoice && $periodicInvoice->treasury_id && ! $treasuries->contains('id', $periodicInvoice->treasury_id)) {
            $treasuries = $treasuries->concat(Treasury::whereKey($periodicInvoice->treasury_id)->get());
        }

        return [
            'items' => $items,
            'store_houses' => $storeHouses,
            'employees' => $employees,
            'treasuries' => $treasuries,
            'productUnits' => $this->productUnitsMap($items->pluck('id')),
        ];
    }

    public function create()
    {
        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.periodic_invoices.create)

        return view('sales::periodic_invoices.create', $this->formLists() + [
            /* كانت `PeriodicInvoice::all()` تُمرَّر إلى شاشة الإنشاء بلا
               مستعمل: تحميلُ كل الفواتير الدورية لعرض نموذجٍ فارغ. */
            'clients' => $this->scopedClientsForSelect(),
            'taxs' => TaxSitting::all(),
            'account_setting' => AccountSetting::where('user_id', auth()->id())->first(),

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «تسلسل أرقام المستندات» — القسم `periodic-invoice`
            'invoice_number' => $this->previewDocumentNumber(self::NUMBER_SECTION),

            // «إعدادات الفواتير»
            'invoiceSettings' => SalesInvoiceSetting::current(),

            // «العملات»
            'documentCurrency' => $this->defaultCurrency(),
            'currencies' => $this->activeCurrencies(),

            // «طرق الدفع»
            'paymentMethods' => $this->activePaymentMethods(),

            // «الفترات المحاسبية» — سببُ منع الحفظ بتاريخ اليوم إن وُجد
            'periodBlock' => $this->accountingPeriodBlock(now()),
        ]);
    }

    /**
     * عرض اشتراكٍ دوريّ.
     *
     * كانت الشاشة تعرض حقولاً معدودة بتصميمٍ قديم، وتحسب «المدفوع» من
     * `payment_status === 'paid'` — والعمود رقميّ (١ مدفوعة، ٢ جزئية،
     * ٣ غير مدفوعة) لا نصّيّ، فالمقارنة كاذبةٌ دائماً ويظهر المدفوع صفراً
     * مهما سُدِّد.
     *
     * صارت على قياس شاشة عرض الفاتورة، وتعرض جدول النسخ: ما صدر منها وما
     * لم يحن موعده بعد.
     */
    public function show($id)
    {
        $periodicInvoice = PeriodicInvoice::with([
            'client',
            'creator',
            'employee',
            'items.product',
            'instances.invoice',
        ])->findOrFail($id);

        // فحص الصلاحيات — على نطاق الرؤية (`ScopesSalesVisibility`)
        $canView = $this->canAccessSalesDocument(
            $periodicInvoice->created_by, $periodicInvoice->employee_id, $periodicInvoice->branch_id
        );

        if (! $canView) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية لعرض هذه الفاتورة');
        }

        /* حالة السداد رقمٌ لا نصّ — `Invoice::$payment_status`:
           ١ مدفوعة، ٢ جزئية، ٣ غير مدفوعة، ٤ مستلمة. */
        $issuedInvoices = $periodicInvoice->instances
            ->map(fn ($instance) => $instance->invoice)
            ->filter();

        $periodicInvoice->paid_amount = $issuedInvoices
            ->where('payment_status', 1)
            ->sum('grand_total');

        $periodicInvoice->unpaid_amount = $issuedInvoices
            ->where('payment_status', '!=', 1)
            ->sum('grand_total');

        /* جدول النسخ من `periodic_invoice_instances` — يُكتب عند الحفظ،
           فيرى المستخدم يومَ إصدار كلّ نسخةٍ واستحقاقَها وحالتَها، وسببَ
           ما أخفق منها (نقصُ مخزون، حدٌّ ائتماني…). واشتراكٌ من قبل
           الترقية بلا جدولٍ يُكتب جدولُه هنا. */
        if (! $periodicInvoice->instances()->exists()) {
            $periodicInvoice->syncSchedule();
            $periodicInvoice->load('instances.invoice');
        }

        $schedule = $periodicInvoice->instances->sortBy('instance_number')->values();

        $logs = \App\Models\Log::where('type', self::LOG_MODULE)
            ->where('type_id', $id)
            ->with('user.branch')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($row) => $row->created_at->format('Y-m-d'));

        return view('sales::periodic_invoices.show', [
            'periodicInvoice' => $periodicInvoice,
            'schedule' => $schedule,
            'logs' => $logs,
            'account_setting' => AccountSetting::where('user_id', auth()->id())->first(),

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «طرق الدفع» — لعرض اسم الطريقة المضبوطة لا رقمها
            'paymentMethods' => $this->activePaymentMethods(),
        ]);
    }

    /**
     * الوحدة ومعامل تحويلها لبندٍ من حمولة الطلب.
     *
     * ثلاث صيغٍ ترسلها الشاشات، وهذه الدالّة تقبلها جميعاً:
     *
     *   • `product_unit_id` رقمياً  — وحدة منتجٍ مضبوطة، والمعامل والاسم
     *                                 من `category_units`.
     *   • `category_<id>`          — وحدة صنفٍ لا وحدة منتج، والمعامل
     *                                 يأتي مع الطلب.
     *   • لا شيء                   — الوحدة الأساسية، فالمعامل واحد.
     *
     * المعامل لا يكون صفراً أبداً: `quantity_in_base_unit` تُضرَب فيه،
     * وصفرٌ هنا يعني فاتورةً تُخصِم صفراً من المخزون.
     *
     * @param  array<string, mixed>  $item
     * @return array{product_unit_id: int|null, conversion_factor: float, unit_name: string}
     */
    protected function resolveUnit(array $item): array
    {
        $raw = $item['product_unit_id'] ?? null;
        $factor = floatval($item['conversion_factor'] ?? 0) ?: 1.0;
        $unitName = '';
        $productUnitId = null;

        if (is_string($raw) && str_starts_with($raw, 'category_')) {
            // وحدة صنف: المعامل من الطلب كما ترسله الشاشة
            $unitName = (string) ($item['unit_name'] ?? '');
        } elseif (! empty($raw) && is_numeric($raw)) {
            $productUnitId = (int) $raw;

            $productUnit = ProductUnit::with('categoryUnit.unit')->find($productUnitId);

            if ($productUnit && $productUnit->categoryUnit) {
                $factor = floatval($productUnit->categoryUnit->conversion_factor) ?: 1.0;
                $unitName = $productUnit->categoryUnit->unit->name ?? '';
            } else {
                // معرّفٌ لا يقابله صفّ: لا يُحفَظ معرّفٌ ميّت في البند
                $productUnitId = null;
            }
        }

        return [
            'product_unit_id' => $productUnitId,
            'conversion_factor' => $factor > 0 ? $factor : 1.0,
            'unit_name' => $unitName,
        ];
    }

    /**
     * الإيداع المقبوض عند إصدار كلّ نسخة — بالمبلغ لا بالنسبة.
     *
     * الشاشة تقبل الاثنين («﷼» أو «٪») كشاشة الفاتورة سواءً، والنسبة
     * تُحوَّل هنا إلى مبلغٍ من إجمالي الاشتراك: العمود يخزّن مبلغاً،
     * والمولّد يقرأ مبلغاً — فحفظُ «١٥» وهي نسبةٌ يجعل إيداعَ فاتورةٍ
     * بعشرة آلاف خمسةَ عشرَ ريالاً.
     *
     * ويُقصَّ على الإجمالي: إيداعٌ أكبر من قيمة الفاتورة يعني مستحقّاً
     * سالباً — دَيناً على المنشأة لعميلها يولد من خطأٍ مطبعي.
     *
     * @param  float  $grandTotal  إجمالي الاشتراك بعد الخصم والضريبة والشحن
     */
    protected function resolveAdvancePayment(Request $request, float $grandTotal): float
    {
        $grandTotal = round(max(0, $grandTotal), 2);

        // «مدفوعة بالكامل» تسبق المبلغ: الحقل مقفولٌ في الشاشة عندها
        if ($request->boolean('is_paid')) {
            return $grandTotal;
        }

        $value = (float) ($request->input('advance_payment') ?: 0);

        if ($value <= 0) {
            return 0.0;
        }

        if ($request->input('advance_payment_type') === 'percentage') {
            $value = $grandTotal * min($value, 100) / 100;
        }

        return round(min($value, $grandTotal), 2);
    }

    /**
     * أتصدر كلّ نسخةٍ مدفوعةً بالكامل؟
     *
     * إمّا بالخانة، وإمّا بإيداعٍ يغطّي الإجمالي — فمن كتب المبلغ كاملاً
     * ولم يؤشّر الخانة لا تُترَك فاتورتُه «غير مكتملة» بمستحقٍّ صفر.
     * والمقارنة بهامش قرشٍ لأنّ النسبة تُقرَّب.
     */
    protected function resolveFullyPaid(Request $request, float $grandTotal): bool
    {
        if ($request->boolean('is_paid')) {
            return true;
        }

        $grandTotal = round(max(0, $grandTotal), 2);

        return $grandTotal > 0
            && $this->resolveAdvancePayment($request, $grandTotal) >= $grandTotal - 0.01;
    }

    /**
     * Store a newly created periodic invoice in storage.
     */
    public function store(Request $request)
    {
        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.periodic_invoices.create)

        /* الإقفال المحاسبي — بتاريخ أوّل فاتورة لا بتاريخ اليوم: الفاتورة
           الدورية تُصدِر مستنداتٍ تُرحَّل قيودها، فلا يصحّ أن يبدأ جدولها
           داخل فترةٍ أُقفلت وأُصدرت قوائمها. */
        if ($block = $this->accountingPeriodBlock($request->input('first_invoice_date') ?: now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        /* ** سقف اشتراك فوترة ** — «الفواتير الدورية» سقفٌ متزامن يعدّ
           النشِطَ منها الآن لا ما أُنشئ هذا الشهر، فإيقافُ اشتراكٍ قديم
           يفرّغ مقعداً بلا ترقية. والقياسُ حيٌّ لا من اللقطة. */
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::PERIODIC_INVOICES)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        // التحقق من صحة البيانات باستخدام helper function
        $validated = validator($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'first_invoice_date' => 'required|date',
            'repeat_type' => 'required|integer|in:1,2,3,4,5',
            'repeat_interval' => 'nullable|integer|min:1',
            /* مدّة الاشتراك: عددُ فواتير، أو تاريخُ نهاية، أو كلاهما —
               أيُّهما وقع أوّلاً أنهاه. واشتراكٌ بلا أحدهما لا ينتهي. */
            'repeat_count' => 'nullable|integer|min:1|required_without:end_date',
            'end_date' => 'nullable|date|after_or_equal:first_invoice_date|required_without:repeat_count',
            'details_subscription' => 'nullable|string',
            /* اسمان لكل حقل عمداً.

               النموذج يرسل أسماء الأعمدة (`invoice_days_offset`,
               `show_from_to_dates`, `disable_partial_payment`) والمتحكّم
               كان يقرأ أسماءً أخرى (`before_days`, `show_dates`,
               `disable_partial`) — فلا الطرفان يلتقيان، ويُحفَظ الاشتراك
               بلا مهلةِ إصدار ولا إظهارِ تاريخ ولا منعِ سدادٍ جزئي.
               يُقبل الاسمان حتى لا ينكسر نموذجٌ قائم. */
            'before_days' => 'nullable|integer|min:0',
            'invoice_days_offset' => 'nullable|integer|min:0',
            'payment_terms' => 'nullable|string',
            'send_copy' => 'boolean',
            'show_dates' => 'boolean',
            'show_from_to_dates' => 'boolean',
            'disable_partial' => 'boolean',
            'disable_partial_payment' => 'boolean',
            'is_active' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            /* كميةٌ كسريّة كبنود الفاتورة سواءً: `invoice_items.quantity`
               عمودٌ عشريّ، و`min:1` كان يردّ نصفَ كيلو ورُبعَ ساعة */
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_type' => 'nullable|in:amount,percentage',
            'items.*.tax_1' => 'nullable|numeric|min:0',
            'items.*.tax_2' => 'nullable|numeric|min:0',

            /* معرّفا الضريبة — منهما يُعرَف **نوعها** (متضمَّنة/مضافة).
               كانت الشاشة ترسل النسبة رقماً حرّاً، والمتحكّم يتجاهلها
               ويحسب الضريبة من `tax_rate` على مستوى المستند. */
            'items.*.tax_1_id' => 'nullable|exists:tax_sittings,id',
            'items.*.tax_2_id' => 'nullable|exists:tax_sittings,id',

            /* الوحدات — الفاتورة الدورية تُصدِر فواتير بيعٍ يُخصَم بها من
               المخزون، والخصم بالوحدة الأساسية. */
            'items.*.product_unit_id' => 'nullable',
            'items.*.conversion_factor' => 'nullable|numeric|min:0',
            'items.*.store_house_id' => 'nullable|exists:store_houses,id',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_tax_id' => 'nullable|exists:tax_sittings,id',
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            /* «نوع الضريبة» (خاضع/صفري/معفى) أُسقط من الشاشة بطلب
               المستخدم: كان مُنتقياً في تبويب **الشحن** يحكم ضريبة المستند
               كلِّه لا شحنَه وحده، وضريبةُ كلّ بندٍ مختارةٌ في صفّه أصلاً
               من «إعدادات الضريبة». فصار المستند خاضعاً دائماً، وإعفاءُ
               بندٍ يكون باختيار «بلا ضريبة» له. العمود باقٍ فتُقرأ
               الاشتراكات القديمة بقيمتها. */
            'tax_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'employee_id' => 'nullable|exists:employees,id',
            'store_house_id' => 'nullable|exists:store_houses,id',
            'payment_method' => 'nullable|exists:payment_methods,id',
            'treasury_id' => 'nullable|integer',

            /* الإيداع — كما في شاشة «إنشاء فاتورة مبيعات»: مبلغٌ
               يُقبَض عند إصدار كلّ نسخة، أو «مدفوعة بالكامل». منه يعرف
               المولّد حالةَ الفاتورة ومستحقَّها.
               انظر `PeriodicInvoiceGenerator::issue()`. */
            'advance_payment' => 'nullable|numeric|min:0',
            'advance_payment_type' => 'nullable|in:amount,percentage',
            'is_paid' => 'nullable|boolean',

            'auto_generate' => 'boolean',
        ])->validate();

        /* المُنتقي وعدٌ لا حارس: `client_id` يصل من النموذج ويُكتب كما هو،
           فيكفي تبديلُ القيمة في المتصفّح لفتح اشتراكٍ دوريّ على عميلٍ
           خارج النطاق — ومنه تُصدَر فواتيرُ بيعٍ تُرحَّل قيودها. نفس حارس
           الإشعار الدائن (`CreditNotificationController`). */
        if ($clientBlock = $this->clientOutOfScopeBlock($validated['client_id'])) {
            return redirect()->back()->withInput()->with('error', $clientBlock);
        }

        // بدء العملية داخل ترانزاكشن
        DB::beginTransaction();

        try {
            /* ** الخطوة الأولى: الرقم من «الترقيم المتسلسل» **

               كان `intval($last->invoice_number) + 1` مصفوفاً إلى خمس
               خانات: يتجاهل البادئة والقالب المضبوطين في الشاشة،
               و`intval` على رقمٍ ببادئة (`PRD-000012`) يقرأ صفراً فيعيد
               التسلسل من أوّله. والرقم المحجوز كان يردّ الإدخال كلّه
               برسالة بدل أن يُسلَّم التاليَ الشاغر. */
            $invoice_number = $this->resolveDocumentNumber(
                $request->input('invoice_number'),
                self::NUMBER_SECTION
            );

            $totals = $this->documentTotals($validated['items'], $request);
            $items_data = $totals['items'];
            $total_with_tax = $totals['grand_total'];

            // ** الخطوة الرابعة: إنشاء الفاتورة في قاعدة البيانات **
            /*
             * حمولة الإنشاء.
             *
             * أسماء ثلاثة حقول كانت تخالف أعمدتها، والعمودُ الصحيح لا
             * يصله شيء — فتُحفَظ الفاتورة الدورية بلا مهلةِ إصدار ولا
             * إظهارِ نطاق تاريخ ولا منعِ سدادٍ جزئي، بلا خطأ ولا تحذير:
             *
             *     'before_days'     → invoice_days_offset
             *     'show_dates'      → show_from_to_dates
             *     'disable_partial' → disable_partial_payment
             *
             * و`send_copy` و`shipping_tax` و`tax_rate` لم تكن أعمدةً
             * أصلاً ولا في `$fillable`، فتُرمى قبل الاستعلام.
             */
            $periodicInvoice = PeriodicInvoice::create([
                'client_id' => $validated['client_id'],
                'invoice_number' => $invoice_number,
                // العملة من «العملات»، وتُجمَّد مع المستند
                'currency' => $this->defaultCurrency(),
                'first_invoice_date' => $validated['first_invoice_date'],
                'repeat_type' => $validated['repeat_type'],
                'repeat_interval' => $validated['repeat_interval'],
                'repeat_count' => $validated['repeat_count'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'details_subscription' => $validated['details_subscription'] ?? null,
                /* `?: 0` لا `?? null`: العمود `NOT NULL DEFAULT 0`، ونموذجٌ
                   لا يرسل الحقل — أو يرسله فارغاً — كان يُسقط الحفظ كلَّه
                   بـ«Column 'invoice_days_offset' cannot be null»، فتُردّ
                   الشاشةُ برسالةِ SQL خام. ومهلةُ الإصدار صفرٌ تعني
                   «تُصدَر يوم استحقاقها»، وهو المعنى الصحيح لغيابها. */
                'invoice_days_offset' => (int) ($validated['invoice_days_offset'] ?? $validated['before_days'] ?? 0),
                /* «شروط الدفع» على الاشتراك لم تعد حقلاً: الاستحقاقُ هو تاريخ
                   الجدول نفسُه، والمهلةُ بينه وبين الإصدار تصير شروطَ دفع
                   كلِّ فاتورةٍ تصدر. انظر `PeriodicInvoiceGenerator::payload()`. */
                'payment_terms' => null,
                'send_copy' => $request->boolean('send_copy'),
                'show_from_to_dates' => $request->boolean('show_from_to_dates') ?: $request->boolean('show_dates'),
                'disable_partial_payment' => $request->boolean('disable_partial_payment') ?: $request->boolean('disable_partial'),
                'auto_generate' => $request->boolean('auto_generate'),
                /* المربّع يُرسَل مؤشَّراً فقط — غيابُه «موقوف». كان `?? true`
                   فيُحفظ الاشتراك مفعَّلاً ولو أُزيل التأشير. */
                'is_active' => $request->boolean('is_active'),
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                // ✅ الفرع يُجمَّد مع المستند وقت إنشائه — نفس نمط بقيّة مستندات المبيعات
                'branch_id' => current_branch_id(),
                'employee_id' => $request->input('employee_id') ?: Auth::user()->employee_id,
                'store_house_id' => $request->input('store_house_id') ?: null,
                // الخصم كما كُتب (قيمةً أو نسبة) ليُعرض في التعديل كما أُدخل؛
                // ومبلغُه المحسوب في `total_discount`
                'discount_amount' => $totals['discount_input'],
                'discount_type' => $totals['discount_type'] === 'percentage' ? 2 : 1,
                'shipping_cost' => $totals['shipping_cost'],
                'shipping_tax' => $totals['shipping_tax'],
                'shipping_tax_id' => $totals['shipping_tax_id'],
                // `1` = خاضع — القيمة الوحيدة بعد إسقاط المُنتقي
                'tax_type' => self::TAX_TYPE_VAT,
                'tax_rate' => $totals['tax_rate'],
                'subtotal' => $totals['subtotal'],
                'total' => $totals['total'],
                'total_discount' => $totals['total_discount'],
                'total_tax' => $totals['total_tax'],
                'grand_total' => $total_with_tax,
                // ردّ المبلغ وطريقة السداد من «طرق الدفع» و«الخزائن»
                'payment_method' => $request->input('payment_method') ?: null,
                'treasury_id' => $request->input('treasury_id') ?: null,
                'advance_payment' => $this->resolveAdvancePayment($request, $total_with_tax),
                'is_paid' => $this->resolveFullyPaid($request, $total_with_tax),
                'status' => 1, // حالة الفاتورة (1: Draft)
            ]);

            // ** الخطوة الخامسة: إنشاء سجلات البنود (items) للفاتورة **
            foreach ($items_data as $item) {
                $item['periodic_invoice_id'] = $periodicInvoice->id;
                InvoiceItem::create($item);
            }

            // ** الخطوة السادسة: جدول النسخ — يُكتب الآن لا عند أوّل إصدار **
            $schedule = $periodicInvoice->syncSchedule();

            DB::commit();

            // السجلّات الثلاثة معاً — نقطة تسجيل واحدة.
            //
            // كانت الفاتورة الدورية تُحفَظ ولا يُكتب لها سطر في أيّ سجلّ
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $periodicInvoice,
                'إنشاء فاتورة دورية رقم '.$periodicInvoice->invoice_number
                    .' — العميل: '.($periodicInvoice->client->trade_name ?? '—')
                    .' — الإجمالي: '.CurrencySettings::format($periodicInvoice->grand_total),
            );

        } catch (\Exception $e) {
            DB::rollback();

            // سجلّ الأخطاء وسجلّ الأحداث معاً — كان `Log::error` وحده،
            // فلا يظهر الخطأ في شاشة «سجلّ الأخطاء» التي بُنيت له
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء حفظ فاتورة دورية: '.$e->getMessage());
        }

        // خارج المعاملة: الإصدار يفتح معاملة الفاتورة الخاصّة به
        return $this->afterScheduleSaved($periodicInvoice, __('sales_periodic.saved.created', ['count' => $schedule['scheduled']]));
    }

    /**
     * عرض نموذج تعديل الفاتورة الدورية
     */
    public function edit($id)
    {
        // جلب الفاتورة المحددة مع علاقاتها
        $periodicInvoice = PeriodicInvoice::with(['items.product', 'client'])->findOrFail($id);

        // فحص الصلاحيات — على نطاق الرؤية (`ScopesSalesVisibility`)
        $canEdit = $this->canModify($periodicInvoice);

        if (! $canEdit) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية لتعديل هذه الفاتورة');
        }

        /* الشاشة تُبنى الآن على قياس شاشة الإنشاء، فتحتاج ما تحتاجه:
         * المستودعات والضرائب والموظّفين وطرق الدفع والخزائن ووحدات
         * المنتجات. وكانت تُمرَّر ثلاثةٌ فقط، فالقالبُ القديم يكتب ما
         * ينقصه في الوسم: ضريبةً ١٥٪ ثابتة، ومستودعاً `value="2"`. */
        $periodicInvoice->loadMissing(['items.product']);

        return view('sales::periodic_invoices.edit', $this->formLists($periodicInvoice) + [
            'periodicInvoice' => $periodicInvoice,
            'clients' => $this->scopedClientsForSelect($periodicInvoice->client_id),
            'taxs' => TaxSitting::all(),
            'issuedCount' => $periodicInvoice->instances()->whereNotNull('invoice_id')->count(),
            'lastIssuedNumber' => (int) $periodicInvoice->instances()->whereNotNull('invoice_id')->max('instance_number'),
            'account_setting' => AccountSetting::where('user_id', auth()->id())->first(),

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «إعدادات الفواتير»
            'invoiceSettings' => SalesInvoiceSetting::current(),

            // «العملات»
            'documentCurrency' => $this->defaultCurrency(),
            'currencies' => $this->activeCurrencies(),

            // «طرق الدفع»
            'paymentMethods' => $this->activePaymentMethods(),

            // «الفترات المحاسبية» — سببُ منع الحفظ إن وُجد
            'periodBlock' => $this->accountingPeriodBlock(now()),
        ]);
    }

    /**
     * تحديث الفاتورة الدورية في قاعدة البيانات
     */
    public function update(Request $request, $id)
    {
        // جلب الفاتورة للتحقق من الصلاحيات
        $periodicInvoice = PeriodicInvoice::findOrFail($id);

        // فحص الصلاحيات — على نطاق الرؤية (`ScopesSalesVisibility`)
        $canUpdate = $this->canModify($periodicInvoice);

        if (! $canUpdate) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية لتعديل هذه الفاتورة');
        }

        // التحقق من صحة البيانات
        $validated = validator($request->all(), [
            'client_id' => 'nullable|exists:clients,id',
            'first_invoice_date' => 'nullable|date',
            'repeat_type' => 'nullable|integer|in:1,2,3,4,5',
            'repeat_interval' => 'nullable|integer|min:1',
            'repeat_count' => 'nullable|integer|min:1|required_without:end_date',
            'end_date' => 'nullable|date|after_or_equal:first_invoice_date|required_without:repeat_count',
            'details_subscription' => 'nullable|string',
            'before_days' => 'nullable|integer|min:0',
            'payment_terms' => 'nullable|string',
            'send_copy' => 'nullable|boolean',
            'show_dates' => 'nullable|boolean',
            'disable_partial' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            /* البنود إلزاميّةٌ كما في الإنشاء: طلبٌ بلا بنود كان يُبقي البنود
               القديمة ويحسب الإجماليات من صفر — فيُحفظ الاشتراك بمجموع صفر. */
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_type' => 'nullable|in:amount,percentage',
            'items.*.tax_1' => 'nullable|numeric|min:0',
            'items.*.tax_2' => 'nullable|numeric|min:0',

            /* المفاتيح نفسها التي يقبلها `store()` — القاعدة المفقودة
               هنا لا تردّ الطلب، بل تُسقط المفتاح من `$validated` بصمت:
               فمعرّف الضريبة والوحدة والمستودع تصل من الشاشة ولا تصل إلى
               الحفظ، وتُحسب الضريبة «مضافة» ولو كانت متضمَّنة. */
            'items.*.tax_1_id' => 'nullable|exists:tax_sittings,id',
            'items.*.tax_2_id' => 'nullable|exists:tax_sittings,id',
            'items.*.product_unit_id' => 'nullable',
            'items.*.conversion_factor' => 'nullable|numeric|min:0',
            'items.*.store_house_id' => 'nullable|exists:store_houses,id',

            'shipping_cost' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'advance_payment' => 'nullable|numeric|min:0',
            'advance_payment_type' => 'nullable|in:amount,percentage',
            'is_paid' => 'nullable|boolean',
            'notes' => 'nullable|string',

            /* حقولٌ تقبلها شاشةُ الإنشاء ويكتبها `store()`، ولم يكن
               `update()` يعرفها: فأوّلُ تعديلٍ يُسقط مسؤولَ المبيعات
               والمستودعَ وطريقةَ الدفع والخزينة ورقمَ الاشتراك، ويُطفئ
               «الإصدار الآلي» — لأنّ `boolean()` على مفتاحٍ غائبٍ
               تُرجع `false`. */
            'invoice_number' => 'nullable|string|max:255',
            'employee_id' => 'nullable|exists:employees,id',
            'store_house_id' => 'nullable|exists:store_houses,id',
            'payment_method' => 'nullable|exists:payment_methods,id',
            'treasury_id' => 'nullable|integer',
            'invoice_days_offset' => 'nullable|integer|min:0',
            'auto_generate' => 'nullable|boolean',
            'shipping_tax_id' => 'nullable|exists:tax_sittings,id',
        ])->validate();

        /* نقلُ الاشتراك إلى عميلٍ خارج النطاق يُردّ كما يُردّ إنشاؤه عليه.
           وعميلُه المحفوظ يبقى مقبولاً ولو خرج عن النطاق بعد حفظه. */
        if (! empty($validated['client_id'])
            && ($clientBlock = $this->clientOutOfScopeBlock($validated['client_id'], $periodicInvoice->client_id))) {
            return redirect()->back()->withInput()->with('error', $clientBlock);
        }

        /* الفترة المحاسبية — كما في `store()`: الاشتراك يُصدر فواتير
           تُرحَّل قيودُها بتواريخ استحقاقها، فتعديلُ موعد أوّلها إلى شهرٍ
           أُقفل يعني قيداً في فترةٍ مقفلة.

           كان `update()` بلا فحصٍ إطلاقاً بينما `store()` يفحص: فيُنشأ
           الاشتراك بتاريخٍ مسموح ثمّ يُنقَل بالتعديل إلى المقفل. */
        if ($block = $this->accountingPeriodBlock(
            $request->input('first_invoice_date') ?: $periodicInvoice->first_invoice_date
        )) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        /* ما صدر لا يُسحب من الجدول: عددُ فواتير أقلُّ من آخر نسخةٍ صدرت،
           أو نهايةٌ قبل استحقاقها، يترك فواتير صادرةً خارج اشتراكها. */
        $lastIssued = $periodicInvoice->instances()->whereNotNull('invoice_id')->orderByDesc('instance_number')->first();

        if ($lastIssued) {
            if (! empty($validated['repeat_count']) && (int) $validated['repeat_count'] < (int) $lastIssued->instance_number) {
                return redirect()->back()->withInput()->with('error', __('sales_periodic.saved.count_below_issued', [
                    'count' => $lastIssued->instance_number,
                ]));
            }

            if (! empty($validated['end_date']) && $lastIssued->due_date
                && \Carbon\Carbon::parse($validated['end_date'])->lt($lastIssued->due_date)) {
                return redirect()->back()->withInput()->with('error', __('sales_periodic.saved.end_before_issued', [
                    'date' => $lastIssued->due_date->format('Y-m-d'),
                ]));
            }
        }

        // بدء العملية داخل ترانزاكشن
        DB::beginTransaction();

        try {
            // البحث عن الفاتورة الدورية
            $periodicInvoice = PeriodicInvoice::findOrFail($id);

            $totals = $this->documentTotals($validated['items'], $request, $periodicInvoice);
            $items_data = $totals['items'];
            $total_with_tax = $totals['grand_total'];

            // ** تحديث الفاتورة في قاعدة البيانات **
            $periodicInvoice->update([
                'client_id' => $validated['client_id'] ?? $periodicInvoice->client_id,
                'first_invoice_date' => $validated['first_invoice_date'] ?? $periodicInvoice->first_invoice_date,
                'repeat_type' => $validated['repeat_type'] ?? $periodicInvoice->repeat_type,
                'repeat_interval' => $validated['repeat_interval'] ?? $periodicInvoice->repeat_interval,
                'repeat_count' => $validated['repeat_count'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'details_subscription' => $validated['details_subscription'] ?? $periodicInvoice->details_subscription,
                /* أسماء الأعمدة الصحيحة — `store()` أُصلح وبقي `update()`
                   يكتب `before_days` و`show_dates` و`disable_partial`،
                   وهي ليست أعمدةً ولا في `$fillable`، فتُرمى بصمت
                   وتُفقَد الإعدادات الثلاثة عند أوّل تعديل. */
                'invoice_days_offset' => $validated['invoice_days_offset']
                    ?? $validated['before_days']
                    ?? $periodicInvoice->invoice_days_offset,
                // الاستحقاق من الجدول — انظر `store()`
                'payment_terms' => null,
                'send_copy' => $request->boolean('send_copy'),
                'show_from_to_dates' => $request->boolean('show_from_to_dates') ?: $request->boolean('show_dates'),
                'disable_partial_payment' => $request->boolean('disable_partial_payment') ?: $request->boolean('disable_partial'),
                'is_active' => $request->boolean('is_active'),
                'auto_generate' => $request->boolean('auto_generate'),
                'notes' => $validated['notes'] ?? $periodicInvoice->notes,

                // ما كان يُفقَد عند أوّل تعديل
                'invoice_number' => ($validated['invoice_number'] ?? null) ?: $periodicInvoice->invoice_number,
                'employee_id' => $validated['employee_id'] ?? $periodicInvoice->employee_id,
                'store_house_id' => $validated['store_house_id'] ?? $periodicInvoice->store_house_id,
                'payment_method' => $validated['payment_method'] ?? $periodicInvoice->payment_method,
                'treasury_id' => $validated['treasury_id'] ?? $periodicInvoice->treasury_id,
                'advance_payment' => $this->resolveAdvancePayment($request, $total_with_tax),
                'is_paid' => $this->resolveFullyPaid($request, $total_with_tax),
                'discount_amount' => $totals['discount_input'],
                'discount_type' => $totals['discount_type'] === 'percentage' ? 2 : 1,
                'shipping_cost' => $totals['shipping_cost'],
                'shipping_tax' => $totals['shipping_tax'],
                'shipping_tax_id' => $totals['shipping_tax_id'],
                'tax_type' => self::TAX_TYPE_VAT,
                'tax_rate' => $totals['tax_rate'],
                'subtotal' => $totals['subtotal'],
                'total' => $totals['total'],
                'total_discount' => $totals['total_discount'],
                'total_tax' => $totals['total_tax'],
                'grand_total' => $total_with_tax,
            ]);

            // ** تحديث البنود ** — تسري على النسخ القادمة؛ الصادرة فواتيرُ قائمةٌ بذاتها
            $periodicInvoice->items()->delete();
            $periodicInvoice->items()->createMany($items_data);

            // ** الجدول ** — ما لم يصدر يُعاد حسابُه، وما صدر يبقى
            $schedule = $periodicInvoice->syncSchedule();

            DB::commit();

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $periodicInvoice ?? (int) $id,
                'تعديل الفاتورة الدورية رقم '.($periodicInvoice->invoice_number ?? $id),
            );

        } catch (\Exception $e) {
            DB::rollback();

            OperationLog::failure($e, self::LOG_MODULE, 'update', (int) $id);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'عذراً، حدث خطأ أثناء تحديث الفاتورة الدورية: '.$e->getMessage());
        }

        return $this->afterScheduleSaved($periodicInvoice, __('sales_periodic.saved.updated', [
            'count' => $schedule['scheduled'],
            'cancelled' => $schedule['cancelled'],
        ]));
    }

    /**
     * Generate a unique invoice number
     */
    public function destroy($id)
    {
        // جلب الفاتورة للتحقق من الصلاحيات
        $periodicInvoice = PeriodicInvoice::findOrFail($id);

        // فحص الصلاحيات — على نطاق الرؤية (`ScopesSalesVisibility`)
        $canDelete = $this->canModify($periodicInvoice);

        if (! $canDelete) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية لحذف هذه الفاتورة');
        }

        $number = $periodicInvoice->invoice_number;

        PeriodicInvoice::destroy($id);

        OperationLog::record('deleted', self::LOG_MODULE, (int) $id, 'حذف الفاتورة الدورية رقم '.$number);

        return redirect()->route('periodic_invoices.index')
            ->with('success', 'تم حذف الفاتورة الدورية بنجاح');

    }

    /**
     * إيقاف الاشتراك أو استئنافه.
     *
     * **لماذا** — الاشتراك يُصدر فواتيرَ بيعٍ على مواعيده، وقد يُطلَب
     * إيقافه مؤقّتاً: عميلٌ جمّد عقده، أو نزاعٌ على الخدمة، أو مراجعةُ
     * تسعير. وكانت الشاشة تعرض حالة «موقوف/مفعَّل» ولا سبيلَ إلى
     * تغييرها إلّا بفتح شاشة التعديل كلِّها والمرور على البنود — وهي
     * تحذف البنود وتعيد كتابتها في كل حفظ.
     *
     * الإيقاف لا يمسّ ما صدر: الفواتير المُصدَرة تبقى، ويتوقّف ما لم
     * يُصدَر. والاستئناف يُصدر ما فات موعده في أوّل تشغيلٍ للمهمّة —
     * وهو سلوك أنظمة الاشتراكات: لا تُهدَر دورةٌ استحقّت.
     */
    public function toggle($id)
    {
        $periodicInvoice = PeriodicInvoice::findOrFail($id);

        if (! $this->canModify($periodicInvoice)) {
            return redirect()->back()->with('error', __('sales_periodic.actions.no_permission'));
        }

        $periodicInvoice->is_active = ! $periodicInvoice->is_active;
        $periodicInvoice->save();

        $state = $periodicInvoice->is_active ? 'resumed' : 'paused';

        OperationLog::record(
            $periodicInvoice->is_active ? 'resumed' : 'paused',
            self::LOG_MODULE,
            $periodicInvoice,
            __('sales_periodic.log.'.$state, ['number' => $periodicInvoice->invoice_number]),
        );

        return redirect()
            ->route('periodic_invoices.show', $periodicInvoice->id)
            ->with('success', __('sales_periodic.actions.'.$state.'_done'));
    }

    /**
     * إصدار ما استحقّ من هذا الاشتراك الآن.
     *
     * **لماذا** — المهمّة المجدولة تعمل مرّةً في اليوم (٠٠:٣٠)، وقد
     * يُنشأ اشتراكٌ ودفعتُه الأولى مستحقّةٌ اليوم فينتظر المستخدمُ
     * الليلَ بلا سبب؛ أو تتعطّل المهمّة ليلةً فتتأخّر فواتيرُ شهر.
     * ولم يكن في الشاشة سبيلٌ إلى الإصدار إلّا سطرُ أمرٍ على الخادم.
     *
     * يمرّ بالقواعد نفسها بلا استثناء — `PeriodicInvoiceGenerator` هو
     * نفسه الذي تناديه المهمّة: النسخةُ الصادرة لا تُعاد، وما لم يحن
     * موعده لا يُقدَّم، والفترةُ المقفلة تردّ التاريخ.
     */
    public function generateNow($id, PeriodicInvoiceGenerator $generator)
    {
        $periodicInvoice = PeriodicInvoice::with(['items', 'client'])->findOrFail($id);

        if (! $this->canModify($periodicInvoice)) {
            return redirect()->back()->with('error', __('sales_periodic.actions.no_permission'));
        }

        // الموقوف لا يُصدِر — وإلّا صار الزرّ بابَ التفافٍ على الإيقاف
        if (! $periodicInvoice->is_active) {
            return redirect()->back()->with('error', __('sales_periodic.actions.paused_blocked'));
        }

        // النسخة المستحقّة التالية وحدها — لا كلُّ ما فات منذ أوّل فاتورة
        $result = $generator->runFor($periodicInvoice, null, false, 1);

        if ($result['created'] > 0) {
            OperationLog::record(
                'generated',
                self::LOG_MODULE,
                $periodicInvoice,
                __('sales_periodic.log.generated', [
                    'count' => $result['created'],
                    'number' => $periodicInvoice->invoice_number,
                ]),
            );

            return redirect()
                ->route('periodic_invoices.show', $periodicInvoice->id)
                ->with('success', __('sales_periodic.actions.generated', [
                    'count' => $result['created'],
                    'codes' => implode('، ', $result['invoices']),
                ]));
        }

        /* لا شيء مستحقّ ليس خطأً: يُقال سببه — «لا بنود»، أو «الفترة
           مقفلة»، أو ببساطة أنّ الموعد لم يحن.

           و`success` لا `info`: الشاشة تعرض `layouts.alerts.success` و
           `layouts.alerts.error` وحدهما، ووميضٌ بمفتاحٍ ثالث لا يظهر —
           فيضغط المستخدم الزرّ ولا يرى شيئاً. */
        $reason = $result['notes'][0] ?? __('sales_periodic.actions.nothing_due');

        return redirect()
            ->route('periodic_invoices.show', $periodicInvoice->id)
            ->with($result['failed'] > 0 ? 'error' : 'success', $reason);
    }

    /**
     * بعد حفظ الاشتراك: تُصدر نسخةٌ حان يومُها الآن لا في منتصف الليل.
     *
     * الجدول كُتب، والمهمّة المجدولة تُكمله كلَّ ليلة. لكنّ اشتراكاً يُحفظ
     * اليوم ويومُ إصدار نسخته الأولى اليوم لا ينتظر الليل: تصدر النسخة
     * المستحقّة التالية وحدها. وما فات قبل اليوم (تاريخ أوّل فاتورة في
     * الماضي) لا يصدر دفعةً من الحفظ — يُقال عدده، ويُصدر بزرّ «إصدار الآن»
     * نسخةً نسخة أو بالمهمّة المجدولة.
     */
    protected function afterScheduleSaved(PeriodicInvoice $periodicInvoice, string $message)
    {
        $generator = app(PeriodicInvoiceGenerator::class);
        $flash = 'success';

        if ($periodicInvoice->is_active && $periodicInvoice->auto_generate) {
            $overdue = $generator->overdueCount($periodicInvoice);

            /* نسخةٌ واحدة يومُ إصدارها اليوم تصدر الآن؛ أكثرُ من واحدة يعني
               تاريخاً في الماضي — تُذكر ولا تصدر من الحفظ */
            $todayOnly = PeriodicInvoiceInstance::where('periodic_invoice_id', $periodicInvoice->id)
                ->whereNull('invoice_id')
                ->whereDate('issue_date', now()->toDateString())
                ->exists();

            if ($overdue === 1 && $todayOnly) {
                $result = $generator->runFor($periodicInvoice->fresh(['items', 'client']), null, false, 1);

                if ($result['created'] > 0) {
                    $message .= ' — '.__('sales_periodic.saved.issued_now', ['codes' => implode('، ', $result['invoices'])]);
                } elseif ($result['failed'] > 0) {
                    $flash = 'error';
                    $message .= ' — '.__('sales_periodic.saved.issue_failed', ['reason' => $result['notes'][0] ?? '']);
                }
            } elseif ($overdue > 0) {
                $message .= ' — '.__('sales_periodic.saved.overdue', ['count' => $overdue]);
            }
        }

        return redirect()->route('periodic_invoices.show', $periodicInvoice->id)->with($flash, $message);
    }

    /**
     * إجماليات الاشتراك — بترتيب فاتورة المبيعات نفسِه.
     *
     *     إجمالي الأصناف − خصومات الأصناف ⇒ صافي الأصناف
     *     − الخصم العامّ (النسبة من صافي الأصناف) ⇒ صافي التوريد
     *     + الشحن (إضافةٌ خاضعة، ضريبتُه مستقلّة) ⇒ الإجمالي
     *
     * كان المتحكّم يأخذ نسبة الخصم من المجموع **قبل** خصوم البنود وقبل طرح
     * الضريبة المتضمَّنة، ويحسب الضريبة قبل الخصم العامّ لا بعده — والشاشة
     * (`invoice-calculator.js`) و`InvoicesController::persistInvoice()` يأخذانها
     * من صافي الأصناف ويُنقصان بها الوعاء الضريبي. فيُحفظ الاشتراك بإجماليٍّ
     * غير المعروض، وتصدر فاتورتُه بإجماليٍّ ثالث. صار الحساب حسابَ الفاتورة:
     * ما يُحفظ على الاشتراك هو ما تصدر به نسخُه.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function documentTotals(array $items, Request $request, ?PeriodicInvoice $current = null): array
    {
        $rows = [];
        $lineNets = [];
        $grossTotal = 0.0;
        $lineDiscountTotal = 0.0;

        foreach (array_values($items) as $key => $item) {
            $product = Product::findOrFail($item['product_id']);
            $unitDetails = $this->resolveUnit($item);

            $quantity = floatval($item['quantity']);
            $unitPrice = floatval($item['unit_price']);
            $itemTotal = $quantity * $unitPrice;

            /* خصم البند من الحقل الذي يخصّ نوعَه — `discount` للقيمة و
               `discount_percentage` للنسبة. كان يُقرأ `discount` وحده فتصل
               النسبة صفراً. */
            $isPercent = ($item['discount_type'] ?? null) === 'percentage';
            $input = $isPercent
                ? floatval(($item['discount_percentage'] ?? 0) ?: ($item['discount'] ?? 0))
                : floatval($item['discount'] ?? 0);
            $lineDiscount = $input > 0 ? min($itemTotal, $isPercent ? $itemTotal * min($input, 100) / 100 : $input) : 0.0;

            $grossTotal += $itemTotal;
            $lineDiscountTotal += $lineDiscount;
            $lineNets[$key] = max(0.0, $itemTotal - $lineDiscount);

            $rows[$key] = [
                'item' => $item,
                'record' => [
                    'periodic_invoice_id' => $current?->id,
                    'product_id' => $product->id,
                    'item' => $product->name,
                    'description' => $item['description'] ?? null,
                    /* المستودع من الاختيار لا من رقمٍ مكتوب في الوسم */
                    'store_house_id' => ($item['store_house_id'] ?? null) ?: ($request->input('store_house_id') ?: $current?->store_house_id),
                    'quantity' => $quantity,
                    'quantity_in_base_unit' => $quantity * $unitDetails['conversion_factor'],
                    'conversion_factor' => $unitDetails['conversion_factor'],
                    'product_unit_id' => $unitDetails['product_unit_id'],
                    'unit_name' => $unitDetails['unit_name'],
                    'unit_price' => $unitPrice,
                    // المبلغ لا النسبة — `InvoiceItem.discount` مبلغٌ في كل مستندات البيع
                    'discount' => round($lineDiscount, 2),
                    'discount_type' => 1,
                    'tax_1' => floatval($item['tax_1'] ?? 0),
                    'tax_1_id' => $item['tax_1_id'] ?? null,
                    'tax_2' => floatval($item['tax_2'] ?? 0),
                    'tax_2_id' => $item['tax_2_id'] ?? null,
                    'total' => round($itemTotal - $lineDiscount, 2),
                ],
            ];
        }

        $netItems = round(array_sum($lineNets), 2);

        // ── الخصم العامّ — قيمةً أو نسبةً من صافي الأصناف، لا يتجاوزه ──
        $discountType = $request->input('discount_type') === 'percentage' ? 'percentage' : 'amount';
        $discountInput = max(0.0, floatval($request->input('discount_amount', 0)));
        $generalDiscount = $discountType === 'percentage'
            ? $netItems * min($discountInput, 100) / 100
            : $discountInput;
        $generalDiscount = round(min($generalDiscount, $netItems), 2);

        $shares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $generalDiscount);

        // ── ضريبة البنود على صافي كلّ بندٍ بعد حصّته من الخصم العامّ ──
        $itemsTax = 0.0;
        $includedTax = 0.0;
        $documentTaxIds = [];

        foreach ($rows as $key => $row) {
            $line = DocumentTaxCalculator::line(
                1.0,
                max(0.0, $lineNets[$key] - ($shares[$key] ?? 0.0)),
                0.0,
                [$row['item']['tax_1_id'] ?? null, $row['item']['tax_2_id'] ?? null],
                [floatval($row['item']['tax_1'] ?? 0), floatval($row['item']['tax_2'] ?? 0)],
            );

            $itemsTax += $line['tax'];
            $includedTax += $line['base'] - $line['net'];

            $documentTaxIds[] = $row['item']['tax_1_id'] ?? null;
            $documentTaxIds[] = $row['item']['tax_2_id'] ?? null;
        }

        $itemsTax = round($itemsTax, 2);
        $includedTax = round($includedTax, 2);

        $subtotal = round($grossTotal - $includedTax, 2);
        $totalDiscount = round($lineDiscountTotal + $generalDiscount, 2);
        $afterDiscount = round($subtotal - $totalDiscount, 2);

        // ── الشحن وضريبته — سطرٌ مستقلّ، والمختارةُ تُحفظ لتصل كلَّ فاتورة ──
        $shippingCost = round(max(0.0, floatval($request->input('shipping_cost', 0))), 2);
        $shippingTaxId = $request->filled('shipping_tax_id') ? (int) $request->input('shipping_tax_id') : null;
        $shippingTax = round(DocumentTaxCalculator::shippingTax(
            $shippingCost,
            /* «بلا ضريبة» اختيارٌ لا سكوت — انظر `shippingTaxIds()` */
            DocumentTaxCalculator::shippingTaxIds($request->has('shipping_tax_id'), $request->input('shipping_tax_id'), $documentTaxIds),
        ), 2);

        $totalTax = round($itemsTax + $shippingTax, 2);

        $taxRate = 0.0;
        foreach ($documentTaxIds as $candidateTaxId) {
            if ($candidate = DocumentTaxCalculator::find($candidateTaxId)) {
                $taxRate = (float) $candidate->tax;

                break;
            }
        }

        return [
            'items' => array_map(fn ($row) => $row['record'], array_values($rows)),
            'subtotal' => $subtotal,
            'discount_input' => $discountInput,
            'discount_type' => $discountType,
            'total_discount' => $totalDiscount,
            'total' => $afterDiscount,
            'items_tax' => $itemsTax,
            'shipping_cost' => $shippingCost,
            'shipping_tax' => $shippingTax,
            'shipping_tax_id' => $shippingTaxId,
            'total_tax' => $totalTax,
            'tax_rate' => $taxRate,
            'grand_total' => round($afterDiscount + $totalTax + $shippingCost, 2),
        ];
    }

    /**
     * صلاحية التعديل على اشتراكٍ بعينه — الشرط نفسه في `edit()`
     * و`update()` و`destroy()`، وكان مكرّراً في أربعة مواضع.
     */
    protected function canModify(PeriodicInvoice $periodicInvoice): bool
    {
        // نطاقُ الرؤية نفسُه الذي يُصفّي الجدول — لا «الكلّ أو أنا».
        return $this->canAccessSalesDocument(
            $periodicInvoice->created_by, $periodicInvoice->employee_id, $periodicInvoice->branch_id
        );
    }
}
