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
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\ExchangeReason;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductExchange;
use App\Models\ProductExchangeItem;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Clients\ClientVisibilityScope;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Sales\Http\Controllers\Concerns\ScopesSalesVisibility;
use Modules\Sales\Http\Controllers\Concerns\UsesDocumentCurrency;
use RuntimeException;

/**
 * إدارة التبديلات — تبديل الأصناف التالفة أو الناقصة للعملاء.
 *
 * التبديل مستندٌ مزدوج الأثر: يُخرج الصنف السليم من مستودعٍ إلى العميل،
 * ويُدخل التالف الوارد منه إلى مستودعٍ آخر. فيولّد لكلّ عملية **إذنين
 * مخزنيّين** (صادر ووارد) و**قيدين محاسبيّين** — وهذا ما يجعل تعديله
 * وحذفه عمليتين محاسبيتين لا تحريرَ صفٍّ في جدول.
 *
 * **ما كان ناقصاً**
 *
 * 1. *لا تعديل إطلاقاً.* المسارات ستّة — عرضٌ وإنشاءٌ وحفظٌ وتفاصيلُ
 *    وطباعةٌ وحذف — ولا `edit` ولا `update`. فمن أخطأ في صنفٍ أو كمية أو
 *    مستودع لا سبيل له إلّا حذفُ العملية وإعادتُها برقمٍ جديد.
 *
 * 2. *الرقم خارج نظام الترقيم.* `ProductExchange::generateCode()` تبني
 *    `EX-00001` من `id` الأخير: بادئةٌ مثبَّتة في الموديل لا تُضبط من
 *    شاشة «تسلسل أرقام المستندات»، وحذفُ آخر سجلٍّ يُعيد الرقم فيتكرّر.
 *    صار القسم مسجَّلاً في `DocumentSections` والرقمُ من `next_document_number()`.
 *
 * 3. *لا فحص للفترة المحاسبية.* التبديل يكتب قيدين، وكان يُرحَّل إلى سنةٍ
 *    أُقفلت وأُصدرت قوائمها المالية بلا مانع.
 *
 * 4. *لا أثر في السجلّات.* لا سطر في «سجلّ النشاط» ولا «سجلّ الأحداث» ولا
 *    «سجلّ الأخطاء» — عمليةٌ تُنشئ قيدين وتُحرّك المخزون وتمرّ بلا خبر.
 *
 * 5. *عملة القيد `'SAR'` مثبَّتة* في الوسم، فيُكتب القيد بالريال ولو كانت
 *    عملة المنشأة غيره.
 *
 * 6. *منطق الأثر مكتوبٌ في `store()` وحده*، ومنطقُ عكسه في `destroy()` —
 *    باشتقاقٍ لحساب المستودع مختلفٍ بين الاثنين، فقد يُرحَّل القيد إلى
 *    حسابٍ ويُعكَس عن آخر. صارا `applyExchangeEffects()` و
 *    `reverseExchangeEffects()`، يستعملهما الإنشاءُ والتعديلُ والحذف معاً.
 *
 * @see RespectsAccountingPeriods
 * @see OperationLog
 */
class ProductExchangeController extends Controller
{
    use Concerns\ChecksSubscriptionLimits;
    use Concerns\ResolvesAccountingAccounts;
    use Concerns\ResolvesSaleCost;
    use RespectsAccountingPeriods;
    use ScopesSalesVisibility;
    use UsesDocumentCurrency;

    /** قيمة العمود `type` في السجلّات — بها يربط المستندُ خطَّه الزمني */
    private const LOG_MODULE = 'ProductExchanges';

    /** خيارات عدد السجلّات — نفس ما تعرضه بقيّة شاشات المبيعات */
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.exchanges.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.exchanges.view', ['only' => ['index', 'show', 'getDetails', 'getProductPrice']]);
        $this->middleware('permission:sales.exchanges.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.exchanges.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales.exchanges.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.exchanges.print', ['only' => ['print']]);
        $this->middleware('permission:sales.exchanges.dashboard', ['only' => ['dashboard']]);
        $this->middleware('permission:sales.exchanges.manage_limits', ['only' => [
            'getEmployeeLimits', 'updateLimits', 'getClientLimits', 'updateClientLimits',
        ]]);
    }

    /**
     * قائمة عمليات التبديل.
     *
     * **ما أُصلح**: كان `per_page` يُؤخذ من الطلب كما هو، فـ`?per_page=999999`
     * يسحب الجدول كلَّه في استعلامٍ واحد؛ وكان الترقيم يُسقط الفلاتر عند
     * الانتقال إلى الصفحة الثانية لغياب `appends()`.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = ProductExchange::with(['client', 'createdByUser', 'items', 'storehouse', 'returnStorehouse'])
            ->orderByDesc('exchange_date')
            ->orderByDesc('id');

        /* الفرع النشط من الهيدر — نفس ما تطبّقه بقيّة شاشات المبيعات. */
        /* والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها،
           فمن يقف عليه يرى الكلَّ — كما في شاشة الفواتير والمرتجعات. */
        $this->applyHeaderBranchScope($query);

        /* نطاقُ الرؤية — الكلّ، أو فرعُه، أو فريقُه، أو ما أنشأه ونُسب
           إليه. كان «الكلّ أو عملياتي أنا». */
        $this->scopeSalesDocuments($query);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.$request->input('code').'%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('exchange_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('exchange_date', '<=', $request->input('to_date'));
        }

        // ✅ فرع عملية التبديل نفسها (مُجمَّدٌ وقت الإنشاء)
        if ($request->filled('branch')) {
            $query->where('branch_id', $request->input('branch'));
        }

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PAGE_SIZES, true)) {
            $perPage = 25;
        }

        $exchanges = $query->paginate($perPage)->appends($request->query());

        $clients = Client::orderBy('trade_name')->get();
        $employees = Employee::all();
        $account_setting = AccountSetting::where('user_id', $user->id)->first();

        if ($request->ajax()) {
            $html = view('sales::product_exchange.partials.table', compact('exchanges', 'account_setting'))->render();

            return response()->json([
                'success' => true,
                'data' => $html,
                'current_page' => $exchanges->currentPage(),
                'last_page' => $exchanges->lastPage(),
                'total' => $exchanges->total(),
                'from' => $exchanges->firstItem(),
                'to' => $exchanges->lastItem(),
            ]);
        }

        return view('sales::product_exchange.index', [
            'subscriptionLimit' => $this->subscriptionLimit(SalesPlanFeatures::PRODUCT_EXCHANGES),
            'exchanges' => $exchanges,
            'account_setting' => $account_setting,
            'clients' => $clients,
            'employees' => $employees,
            'stats' => $this->exchangeStats(),
            'pageSizes' => self::PAGE_SIZES,
            'branches' => Branch::where('status', 0)->select('id', 'name')->get(),
        ]);
    }

    /**
     * إحصاء الشاشة — استعلاماتٌ مجمَّعة، لا مرورٌ على السجلّات.
     *
     * تُقصر على عمليات المستخدم حين لا يكون مديراً، وإلّا عرض الشريطُ
     * أرقام المنشأة كلِّها فوق جدولٍ لا يعرض إلّا عملياته.
     *
     * @return array{total:int, items:int, value:float, clients:int, today:int}
     */
    private function exchangeStats(): array
    {
        /* البطاقاتُ على نطاق الجدول نفسِه: كانت «الكلّ أو ما أنشأتُه أنا»،
           فيقرأ المشرفُ جدولاً فيه عملياتُ فريقه فوق بطاقاتٍ لا تعدّ إلّا
           عملياتِه هو. */
        $scope = fn () => $this->scopeSalesDocuments(ProductExchange::query());

        return [
            'total' => (int) $scope()->count(),
            'value' => (float) $scope()->sum('total_value'),
            'clients' => (int) $scope()->distinct()->count('client_id'),
            'today' => (int) $scope()->whereDate('exchange_date', today())->count(),
            'items' => (int) ProductExchangeItem::query()
                ->whereIn('product_exchange_id', $scope()->select('id'))
                ->sum('quantity'),
        ];
    }

    /**
     * شاشة إنشاء عملية تبديل جديدة.
     *
     * تسعون سطراً من بناء المصادر — العملاءُ المجدولون لليوم، ومستودعاتُ
     * الموظف، وأصنافُها — كانت مكتوبةً هنا وحدها، فلم يكن لشاشة التعديل
     * ما تبني به نفسها. صارت في `formContext()` يشتركان فيها.
     */
    public function create()
    {
        return view('sales::product_exchange.create', $this->formContext() + [
            'exchange' => null,
        ]);
    }

    /**
     * حفظ عملية التبديل
     */
    public function store(Request $request)
    {
        // ** حدُّ اشتراك فوترة ** — قبل أيّ كتابة، بالقياس الحيّ لا باللقطة
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::PRODUCT_EXCHANGES)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        // تصحيح: طباعة البيانات المستلمة
        \Log::info('Product Exchange - Received Data:', $request->all());

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'storehouse_id' => 'required|exists:store_houses,id',
            'return_storehouse_id' => 'required|exists:store_houses,id|different:storehouse_id',
            'exchange_date' => 'required|date',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reason' => 'required|exists:exchange_reasons,id',
        ], [
            'client_id.required' => 'يجب اختيار العميل',
            'storehouse_id.required' => 'يجب اختيار مستودع التبديل',
            'return_storehouse_id.required' => 'يجب اختيار مستودع الاسترجاع',
            'return_storehouse_id.different' => 'مستودع الاسترجاع يجب أن يكون مختلفاً عن مستودع التبديل',
            'exchange_date.required' => 'يجب تحديد تاريخ التبديل',
            'image.required' => 'يجب تحميل صورة للتبديل',
            'image.image' => 'الملف يجب أن يكون صورة',
            'image.mimes' => 'صيغة الصورة غير مدعومة (jpeg, png, jpg, gif, webp)',
            'image.max' => 'حجم الصورة يجب أن لا يتجاوز 5 ميجا بايت',
            'items.required' => 'يجب إضافة منتج واحد على الأقل',
            'items.*.product_id.required' => 'يجب اختيار المنتج',
            'items.*.quantity.required' => 'يجب تحديد الكمية',
            'items.*.reason.required' => 'يجب اختيار سبب التبديل',
        ]);

        try {
            DB::beginTransaction();

            $user = Auth::user();

            // ==================== الرقابة على الحد اليومي للكميات ====================
            // استخراج الموظف المستهدف (إما المختار من قبل المدير أو الموظف المسجل دخوله)
            $targetEmployeeId = $request->employee_id ?: $user->employee_id;

            if ($targetEmployeeId) {
                $employee = Employee::find($targetEmployeeId);

                // التحقق فقط إذا كان هناك حد معين (بما في ذلك الصفر)
                if ($employee && $employee->daily_qty_limit !== null) {
                    $limit = (int) $employee->daily_qty_limit;

                    // 1. حساب الكمية في الطلب الحالي
                    $currentRequestQty = 0;
                    foreach ($request->items as $item) {
                        $currentRequestQty += (int) $item['quantity'];
                    }

                    // 2. حساب إجمالي الكميات المبدلة لهذا "الموظف" في "التاريخ المختار"
                    // ملاحظة: نحسب بناءً على employee_id لضمان الدقة حتى لو قام المدير بالإيقاع للموظف
                    $targetDate = $request->exchange_date ?: Carbon::now()->toDateString();

                    $todayTotalQty = ProductExchangeItem::join('product_exchanges', 'product_exchange_items.product_exchange_id', '=', 'product_exchanges.id')
                        ->where('product_exchanges.employee_id', $targetEmployeeId)
                        ->whereDate('product_exchanges.exchange_date', $targetDate)
                        ->sum('product_exchange_items.quantity');

                    Log::info('فحص الحد اليومي للتبديل (نظام الإدارة)', [
                        'manager_id' => $user->id,
                        'target_employee_id' => $targetEmployeeId,
                        'target_date' => $targetDate,
                        'limit_set' => $limit,
                        'used_so_far' => $todayTotalQty,
                        'current_request' => $currentRequestQty,
                    ]);

                    // 3. التحقق من التجاوز
                    if (($todayTotalQty + $currentRequestQty) > $limit) {
                        $remaining = $limit - $todayTotalQty;

                        return response()->json([
                            'success' => false,
                            'message' => "عذراً، الموظف ({$employee->first_name}) تجاوز الحد اليومي المسموح له في تاريخ {$targetDate}. الحد: {$limit}، المتبقي: ".($remaining > 0 ? $remaining : 0),
                        ], 422);
                    }
                }
            }
            // ==================== الرقابة على الحد اليومي للعملاء ====================
            $clientId = $request->client_id;
            $client = Client::find($clientId);

            if ($client) {
                // تحديد الحد المطبق: الخاص بالعميل أولاً، ثم العام
                $clientLimit = $client->daily_exchange_limit;

                if ($clientLimit === null) {
                    $setting = AccountSetting::where('user_id', auth()->user()->id)->first();
                    if ($setting) {
                        $clientLimit = $setting->global_client_exchange_limit;
                    }
                }

                if ($clientLimit !== null) {
                    $cLimit = (int) $clientLimit;
                    $targetDate = $request->exchange_date ?: Carbon::now()->toDateString();

                    // حساب إجمالي الكميات المبدلة لهذا "العميل" في "التاريخ المختار"
                    $clientTodayQty = ProductExchangeItem::join('product_exchanges', 'product_exchange_items.product_exchange_id', '=', 'product_exchanges.id')
                        ->where('product_exchanges.client_id', $clientId)
                        ->whereDate('product_exchanges.exchange_date', $targetDate)
                        ->sum('product_exchange_items.quantity');

                    $currentRequestQty = 0;
                    foreach ($request->items as $item) {
                        $currentRequestQty += (int) $item['quantity'];
                    }

                    if (($clientTodayQty + $currentRequestQty) > $cLimit) {
                        $remaining = $cLimit - $clientTodayQty;

                        return response()->json([
                            'success' => false,
                            'message' => "عذراً، العميل ({$client->trade_name}) تجاوز الحد اليومي المسموح له بالتبديل في تاريخ {$targetDate}. الحد: {$cLimit}، المتبقي: ".($remaining > 0 ? $remaining : 0),
                        ], 422);
                    }
                }
            }
            // =========================================================================

            // ── المستودعات ──
            [$storehouseId, $returnStorehouseId] = $this->resolveStorehouses($request, $user);

            // ** الفترة المحاسبية ** — التبديل يكتب قيدين، فيخضع لقفل
            // الفترة كما تخضع الفاتورة. لم يكن يُفحَص إطلاقاً: كان التبديل
            // يُرحَّل قيداً إلى سنةٍ أُقفلت وأُصدرت قوائمها المالية
            if ($blocked = $this->accountingPeriodBlock($request->exchange_date)) {
                return response()->json([
                    'success' => false,
                    'message' => __('sales_exchanges.messages.period_blocked', ['reason' => $blocked]),
                ], 422);
            }

            // ** الرقم من «تسلسل أرقام المستندات» ** — كانت
            // `ProductExchange::generateCode()` تبني الرقم من `id` الأخير
            // ببادئة `EX-` مثبَّتة في الموديل: لا تُضبط من شاشة الترقيم،
            // وحذفُ آخر سجلٍّ يُعيد الرقم فيتكرّر
            $code = next_document_number('product-exchange');

            $exchange = ProductExchange::create([
                'code' => $code,
                'client_id' => $request->client_id,
                // ✅ الفرع يُجمَّد مع المستند وقت إنشائه — نفس نمط بقيّة مستندات المبيعات
                'branch_id' => current_branch_id(),
                'employee_id' => $targetEmployeeId,
                'storehouse_id' => $storehouseId,
                'return_storehouse_id' => $returnStorehouseId,
                'created_by' => $user->id,
                'exchange_date' => $request->exchange_date,
                'notes' => $request->notes,
                'image' => $request->hasFile('image')
                    ? $this->processAndSaveImage($request->file('image'))
                    : null,
                'total_value' => 0,   // يُحسب في applyExchangeEffects
                'status' => 1,
            ]);

            $this->applyExchangeEffects($exchange, $request->items);

            DB::commit();

            OperationLog::record('created', self::LOG_MODULE, $exchange, __('sales_exchanges.log.created', [
                'code' => $exchange->code,
                'client' => $exchange->client->trade_name ?? $exchange->client->name ?? '—',
                'value' => CurrencySettings::format((float) $exchange->total_value),
            ]));

            return response()->json([
                'success' => true,
                'message' => __('sales_exchanges.messages.created', ['code' => $code]),
                'redirect' => route('product-exchanges.show', $exchange->id),
            ]);
        } catch (RuntimeException $e) {
            // خطأٌ متوقَّع بنصٍّ مُعدٍّ للعرض: نقص مخزون، أو مستودعٌ مكرَّر
            DB::rollback();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollback();
            OperationLog::failure($e, self::LOG_MODULE, 'store');
            Log::error('تعذّر حفظ عملية التبديل: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('sales_exchanges.messages.save_failed'),
            ], 500);
        }
    }

    /**
     * عرض تفاصيل عملية التبديل
     */
    public function show($id)
    {
        $exchange = ProductExchange::with(['client', 'createdByUser', 'employee', 'storehouse', 'items.product', 'items.exchangeReason'])
            ->findOrFail($id);

        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        return view('sales::product_exchange.show', compact('exchange', 'account_setting'));
    }

    /**
     * جلب تفاصيل التبديل بتنسيق JSON/HTML للـ Modal
     */
    public function getDetails($id)
    {
        try {
            $exchange = ProductExchange::with(['client', 'createdByUser', 'employee', 'storehouse', 'items.product', 'items.exchangeReason'])
                ->findOrFail($id);

            $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

            $html = view('sales::product_exchange.partials.details-modal', compact('exchange', 'account_setting'))->render();

            return response()->json([
                'success' => true,
                'html' => $html,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching exchange details: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
            ], 500);
        }
    }

    /**
     * طباعة عملية التبديل
     */
    public function print($id)
    {
        $exchange = ProductExchange::with(['client', 'createdByUser', 'employee', 'storehouse', 'items.product', 'items.exchangeReason'])
            ->findOrFail($id);

        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        return view('sales::product_exchange.pdf', compact('exchange', 'account_setting'));
    }

    /**
     * حذف عملية التبديل — بعكس أثرها المخزني والمحاسبي أولاً.
     *
     * العكسُ نفسه صار في `reverseExchangeEffects()` ليستعمله التعديل
     * أيضاً؛ وأُضيف فحصُ الفترة المحاسبية: حذفُ التبديل يحذف قيديه، ولا
     * يجوز أن يُمسّ قيدٌ في فترةٍ أُقفلت.
     */
    public function destroy($id)
    {
        $exchange = ProductExchange::with('items')->findOrFail($id);
        $code = $exchange->code;

        if ($blocked = $this->accountingPeriodBlock($exchange->exchange_date)) {
            return redirect()->back()->with('error', __('sales_exchanges.messages.period_blocked', [
                'reason' => $blocked,
            ]));
        }

        try {
            DB::beginTransaction();

            $this->reverseExchangeEffects($exchange);
            $this->deleteImage($exchange->image);
            $exchange->delete();   // البنود تُحذف بالسلسلة

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $id);
            Log::error('تعذّر حذف عملية التبديل: '.$e->getMessage());

            return redirect()->back()->with('error', __('sales_exchanges.messages.delete_failed'));
        }

        OperationLog::record('deleted', self::LOG_MODULE, $id, __('sales_exchanges.log.deleted', [
            'code' => $code,
        ]));

        return redirect()->route('product-exchanges.index')
            ->with('success', __('sales_exchanges.messages.deleted', ['code' => $code]));
    }

    /**
     * جلب سعر المنتج بـ AJAX
     */
    public function getProductPrice(Request $request)
    {
        $product = Product::find($request->product_id);

        if ($product) {
            return response()->json([
                'success' => true,
                'price' => $product->price ?? 0,
                'name' => $product->name,
            ]);
        }

        return response()->json(['success' => false]);
    }

    /**
     * معالجة وحفظ الصورة المضغوطة
     *
     * @param  UploadedFile  $imageFile
     * @return string|null المسار المحفوظ
     */
    private function processAndSaveImage($imageFile)
    {
        try {
            // إنشاء مجلد التخزين إن لم يكن موجوداً
            $uploadPath = public_path('uploads/product_exchanges');
            if (! file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // توليد اسم فريد للملف
            $fileName = 'exchange_'.time().'_'.uniqid().'.jpg';
            $fullPath = $uploadPath.'/'.$fileName;

            // قراءة الصورة الأصلية
            $originalImage = $imageFile->getRealPath();

            // معلومات الصورة الأصلية
            $imageInfo = getimagesize($originalImage);
            $mimeType = $imageInfo['mime'];
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];

            // تحديد الحد الأقصى للأبعاد (800px للعرض أو الارتفاع)
            $maxDimension = 800;

            // حساب الأبعاد الجديدة مع الحفاظ على النسبة
            $ratio = 1;
            if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
                if ($originalWidth > $originalHeight) {
                    $ratio = $maxDimension / $originalWidth;
                } else {
                    $ratio = $maxDimension / $originalHeight;
                }
            }
            $newWidth = (int) ($originalWidth * $ratio);
            $newHeight = (int) ($originalHeight * $ratio);

            // إنشاء الصورة من الملف الأصلي بناءً على النوع
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = imagecreatefromjpeg($originalImage);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($originalImage);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($originalImage);
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($originalImage);
                    break;
                default:
                    // محاولة استخدام imagecreatefromstring للأنواع الأخرى
                    $sourceImage = imagecreatefromstring(file_get_contents($originalImage));
            }

            if (! $sourceImage) {
                return null;
            }

            // إنشاء صورة جديدة بالأبعاد المطلوبة
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

            // الحفاظ على الشفافية للصور PNG
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);

            // تغيير حجم الصورة
            imagecopyresampled(
                $resizedImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight, $originalWidth, $originalHeight
            );

            // حفظ الصورة كـ JPEG بجودة 70% (ضغط جيد)
            imagejpeg($resizedImage, $fullPath, 70);

            // تحرير الذاكرة
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);

            // إرجاع المسار النسبي للحفظ في قاعدة البيانات
            return 'uploads/product_exchanges/'.$fileName;

        } catch (\Exception $e) {
            Log::error('خطأ في معالجة الصورة: '.$e->getMessage());

            return null;
        }
    }

    /**
     * حذف صورة من المسار
     */
    /**
     * حذف صورة من المسار
     */
    private function deleteImage($imagePath)
    {
        if ($imagePath) {
            $fullPath = public_path($imagePath);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    /**
     * داشبورد عمليات التبديل - النسخة النهائية القصوى (Ultimate Version)
     */
    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $isManager = $user->hasRolePermission('data_scope_all'); // ✅ hasRolePermission() بدلاً من دورٍ ثابت

        // 1. إعدادات الفروع والفترة الزمنية
        // منسدلةُ الفرع على نطاقه هو — `DataScope::branchOptions()` تقصّها
        $branches = $isManager ? DataScope::branchOptions() : [];

        $selectedBranchId = $request->get('branch_id');
        $period = $request->get('period', '30');
        $customStartDate = $request->get('start_date');
        $customEndDate = $request->get('end_date');
        $endDate = Carbon::now()->endOfDay();

        switch ($period) {
            case '1': $startDate = Carbon::now()->startOfDay();
                break;
            case '7': $startDate = Carbon::now()->subDays(6)->startOfDay();
                break;
            case '30': $startDate = Carbon::now()->subDays(29)->startOfDay();
                break;
            case '90': $startDate = Carbon::now()->subDays(89)->startOfDay();
                break;
            case 'custom':
                $startDate = $customStartDate ? Carbon::parse($customStartDate)->startOfDay() : Carbon::now()->startOfMonth();
                $endDate = $customEndDate ? Carbon::parse($customEndDate)->endOfDay() : Carbon::now()->endOfDay();
                break;
            default: $startDate = Carbon::now()->subDays(29)->startOfDay();
        }

        // 2. الاستعلام الأساسي مع الفلاتر
        /* الأعمدةُ مؤهَّلةٌ باسم الجدول: أقسامٌ أدناه تضمّ `users` و`clients`
           إلى هذا الاستعلام، وفي كليهما `employee_id` و`created_by` و`branch_id`.
           كان نطاقُ الرؤية يكتبها بلا جدول، فتسقط اللوحةُ كلُّها بـ«Column
           'employee_id' in where clause is ambiguous» لكلّ مستخدمٍ نطاقُه
           فريقُه أو بياناتُه — أي لكلّ مندوب. */
        $query = ProductExchange::query()->whereBetween('product_exchanges.exchange_date', [$startDate, $endDate]);

        /* نطاقُ الرؤية أوّلاً — كان «الكلّ أو عملياتي أنا»، فلوحةُ المشرف
           لا تعدّ عملياتِ فريقه. ثمّ فلترُ الفرع المختار فوقه. */
        $this->scopeSalesDocuments($query, 'product_exchanges.created_by', 'product_exchanges.employee_id', 'employee', 'product_exchanges.branch_id');

        if ($selectedBranchId && $selectedBranchId !== 'all') {
            // ✅ فرع عملية التبديل نفسها (مُجمَّدٌ وقت الإنشاء) — لا فرع العميل، بما تحته
            $query->forBranch((int) $selectedBranchId);
        } else {
            /* فرعُ الشريط العلويّ — كقائمة التبديلات. كانت اللوحةُ لا تقرؤه،
               فيرى مديرُ فرع القصيم أرقامَ المنشأة كلِّها (٣٣٣٥ عملية) فوق
               قائمةٍ لا تعرض له إلّا عملياتِ فرعه (٥٧٥). */
            $this->applyHeaderBranchScope($query);
        }

        // 3. الإحصائيات العامة (Hero Section)
        // نستخدم استعلامات منفصلة لضمان دقة البيانات 100% وعدم تكرار القيم بسبب الـ Join
        $stats = (clone $query)->selectRaw('COUNT(*) as count, SUM(total_value) as total')->first();

        $totalQuantity = ProductExchangeItem::whereIn('product_exchange_id', (clone $query)->pluck('id'))->sum('quantity');

        $periodClientsCount = (clone $query)->distinct('client_id')->count('client_id');

        // 4. بيانات الاتجاه (Trend Analysis) - قيمة وكمية يومياً (استعلامات منفصلة للدقة)
        $dailyValues = (clone $query)
            ->select(DB::raw('DATE(exchange_date) as date'), DB::raw('SUM(total_value) as daily_value'))
            ->groupBy('date')
            ->pluck('daily_value', 'date');

        $dailyQuantities = ProductExchangeItem::join('product_exchanges', 'product_exchange_items.product_exchange_id', '=', 'product_exchanges.id')
            ->whereIn('product_exchanges.id', (clone $query)->pluck('id'))
            ->select(DB::raw('DATE(product_exchanges.exchange_date) as date'), DB::raw('SUM(product_exchange_items.quantity) as daily_qty'))
            ->groupBy('date')
            ->pluck('daily_qty', 'date');

        // دمج البيانات للرسم البياني
        $allDates = $dailyValues->keys()->merge($dailyQuantities->keys())->unique()->sort();
        $trendData = $allDates->map(function ($date) use ($dailyValues, $dailyQuantities) {
            return [
                'date' => $date,
                'daily_value' => $dailyValues[$date] ?? 0,
                'daily_qty' => $dailyQuantities[$date] ?? 0,
            ];
        });

        // 5. أفضل المنتجات مبادلة (Top Products)
        $topProducts = ProductExchangeItem::join('product_exchanges', 'product_exchange_items.product_exchange_id', '=', 'product_exchanges.id')
            ->join('products', 'product_exchange_items.product_id', '=', 'products.id')
            ->whereIn('product_exchanges.id', (clone $query)->pluck('id'))
            ->select('products.name', DB::raw('SUM(product_exchange_items.quantity) as total_qty'))
            ->groupBy('product_exchange_items.product_id', 'products.name')
            ->orderByDesc('total_qty')
            ->take(8)
            ->get();

        // 6. توزيع أسباب التبديل (Reason Distribution)
        $reasonStats = ProductExchangeItem::join('product_exchanges', 'product_exchange_items.product_exchange_id', '=', 'product_exchanges.id')
            // العمود في `product_exchange_items` اسمُه `reason_id` لا
            // `exchange_reason_id` — والاسم الخاطئ كان يُسقط اللوحةَ كلَّها
            // بـ«Unknown column» عند كل فتحة، لا يُخفي قسماً واحداً
            ->join('exchange_reasons', 'product_exchange_items.reason_id', '=', 'exchange_reasons.id')
            ->whereIn('product_exchanges.id', (clone $query)->pluck('id'))
            ->select('exchange_reasons.name', DB::raw('COUNT(*) as count'))
            ->groupBy('exchange_reasons.id', 'exchange_reasons.name')
            ->get();

        // 7. أداء الموظفين التفصيلي (محسن لضمان الدقة)
        $detailedEmployees = (clone $query)
            ->join('users', 'product_exchanges.created_by', '=', 'users.id')
            ->select('users.name', 'product_exchanges.created_by', DB::raw('COUNT(DISTINCT product_exchanges.id) as count'), DB::raw('SUM(product_exchanges.total_value) as total_val'))
            ->groupBy('product_exchanges.created_by', 'users.name')
            ->orderByDesc('total_val')
            ->take(20)
            ->get();

        foreach ($detailedEmployees as $emp) {
            $emp->total_qty = ProductExchangeItem::whereIn('product_exchange_id', (clone $query)->where('created_by', $emp->created_by)->pluck('id'))->sum('quantity');
        }

        // 8. أحدث التبديلات اليوم (نعرض الكل لليوم لضمان المتابعة الصحيحة)
        $todayExchanges = (clone $query)
            ->with(['client', 'items.product', 'items.exchangeReason', 'createdByUser'])
            ->whereDate('exchange_date', Carbon::now()->toDateString())
            ->orderBy('created_at', 'desc')
            ->get();

        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // حساب حالة الحد اليومي (للموظفين فقط)
        $dailyLimitInfo = null;
        if ($user->employee_id) {
            $employee = Employee::find($user->employee_id);
            if ($employee && ! empty($employee->daily_qty_limit)) {
                $todayUsedQty = ProductExchangeItem::join('product_exchanges', 'product_exchange_items.product_exchange_id', '=', 'product_exchanges.id')
                    ->where('product_exchanges.created_by', $user->id)
                    ->whereDate('product_exchanges.exchange_date', Carbon::now()->toDateString())
                    ->sum('product_exchange_items.quantity');

                $dailyLimitInfo = [
                    'limit' => $employee->daily_qty_limit,
                    'used' => $todayUsedQty,
                    'remaining' => max(0, $employee->daily_qty_limit - $todayUsedQty),
                    'percentage' => min(100, ($todayUsedQty / $employee->daily_qty_limit) * 100),
                ];
            }
        }

        // 9. أكثر العملاء تبديلاً بالكمية
        $topClients = ProductExchangeItem::join('product_exchanges', 'product_exchange_items.product_exchange_id', '=', 'product_exchanges.id')
            ->join('clients', 'product_exchanges.client_id', '=', 'clients.id')
            ->whereIn('product_exchanges.id', (clone $query)->pluck('id'))
            ->select(
                'clients.id',
                'clients.trade_name',
                'clients.first_name',
                'clients.last_name',
                DB::raw('SUM(product_exchange_items.quantity) as total_qty'),
                DB::raw('COUNT(DISTINCT product_exchanges.id) as exchange_count')
            )
            ->groupBy('clients.id', 'clients.trade_name', 'clients.first_name', 'clients.last_name')
            ->orderByDesc('total_qty')
            ->take(20)
            ->get();

        return view('sales::product_exchange.dashboard', compact(
            'stats',
            'totalQuantity',
            'periodClientsCount',
            'trendData',
            'topProducts',
            'reasonStats',
            'detailedEmployees',
            'todayExchanges',
            'branches',
            'selectedBranchId',
            'period',
            'startDate',
            'endDate',
            'account_setting',
            'isManager',
            'dailyLimitInfo',
            'topClients'
        ));
    }

    /**
     * جلب قائمة الموظفين وحدودهم الحالية لغرض الإدارة
     */
    public function getEmployeeLimits()
    {
        try {
            // ✅ صلاحية إدارة الحدود محروسة أصلاً في المُنشئ (permission:sales.exchanges.manage_limits)

            $employees = Employee::select('id', 'first_name', 'middle_name', 'nickname', 'daily_qty_limit')
                ->where('status', 1)
                ->get()
                ->map(function ($emp) {
                    return [
                        'id' => $emp->id,
                        'name' => $emp->first_name.' '.$emp->middle_name.' '.$emp->nickname,
                        'limit' => $emp->daily_qty_limit,
                    ];
                });

            return response()->json([
                'success' => true,
                'employees' => $employees,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.ProductExchange', 'getEmployeeLimits');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * تحديث حدود التبديل اليومية للموظفين (Bulk Update)
     */
    public function updateLimits(Request $request)
    {
        try {
            // ✅ صلاحية إدارة الحدود محروسة أصلاً في المُنشئ (permission:sales.exchanges.manage_limits)

            $limits = $request->input('limits', []); // مصفوفة تحتوي على [employee_id => limit]

            foreach ($limits as $empId => $limit) {
                // نستخدم is_numeric لنسمح بالرقم 0 كحد صارم، والـ null/فارغ كـ "غير محدود"
                $value = (is_numeric($limit) && $limit >= 0) ? (int) $limit : null;

                Employee::where('id', $empId)->update([
                    'daily_qty_limit' => $value,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الحدود بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.ProductExchange', 'updateLimits');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * جلب قائمة العملاء وحدودهم الحالية لغرض الإدارة
     */
    public function getClientLimits()
    {
        try {
            $user = auth()->user();
            // ✅ صلاحية إدارة الحدود محروسة أصلاً في المُنشئ (permission:sales.exchanges.manage_limits)

            $setting = AccountSetting::where('user_id', $user->id)->first();
            $globalLimit = $setting ? $setting->global_client_exchange_limit : null;

            $clients = Client::select('id', 'trade_name', 'first_name', 'last_name', 'daily_exchange_limit')
                ->orderBy('trade_name', 'asc')
                ->get()
                ->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->trade_name ?: ($client->first_name.' '.$client->last_name),
                        'limit' => $client->daily_exchange_limit,
                    ];
                });

            return response()->json([
                'success' => true,
                'clients' => $clients,
                'globalLimit' => $globalLimit,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.ProductExchange', 'getClientLimits');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * تحديث حدود التبديل اليومية للعملاء (عام + خاص)
     */
    public function updateClientLimits(Request $request)
    {
        try {
            $user = auth()->user();
            // ✅ صلاحية إدارة الحدود محروسة أصلاً في المُنشئ (permission:sales.exchanges.manage_limits)

            $globalLimit = $request->input('global_limit');
            $clientLimits = $request->input('limits', []);

            Log::info('Update Client Limits request received', [
                'globalLimit' => $globalLimit,
                'clientLimits' => $clientLimits,
            ]);

            DB::beginTransaction();

            // حفظ الحد العام
            $setting = AccountSetting::where('user_id', $user->id)->first();
            if ($setting) {
                $setting->update([
                    'global_client_exchange_limit' => (is_numeric($globalLimit) && $globalLimit >= 0) ? (int) $globalLimit : null,
                ]);
            }

            // حفظ حدود العملاء المحددين
            foreach ($clientLimits as $clientId => $limit) {
                $value = (is_numeric($limit) && $limit >= 0) ? (int) $limit : null;
                Client::where('id', $clientId)->update([
                    'daily_exchange_limit' => $value,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حدود جميع العملاء بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.ProductExchange', 'updateClientLimits');

            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  التعديل — لم يكن للتبديل شاشةُ تعديلٍ ولا مسارٌ ولا تابع
    // ══════════════════════════════════════════════════════════════════

    /**
     * شاشة تعديل عملية تبديل.
     *
     * كان للتبديل إنشاءٌ وعرضٌ وحذفٌ ولا تعديل: من أخطأ في صنفٍ أو كمية
     * أو مستودع لا سبيل له إلّا حذف العملية كلَّها وإعادتها برقمٍ جديد،
     * فينقطع تسلسل الأرقام ويضيع أثرُ ما جرى.
     */
    public function edit($id)
    {
        $exchange = ProductExchange::with(['items.product', 'items.exchangeReason', 'client'])
            ->findOrFail($id);

        return view('sales::product_exchange.edit', $this->formContext($exchange) + [
            'exchange' => $exchange,
        ]);
    }

    /**
     * حفظ التعديل: يُعكس أثرُ العملية القديم بالكامل ثم يُعاد تطبيقه
     * بالبيانات الجديدة.
     *
     * **لماذا العكسُ ثمّ الإعادة لا التعديلُ الموضعي؟** لأنّ التبديل ليس
     * صفَّ بياناتٍ بل حركة: كميّاتٌ نقصت من مستودعٍ وزادت في آخر، وإذنان
     * مخزنيّان ببنودهما، وقيدان بطرفيهما. تعديلُ صنفٍ واحدٍ موضعياً يعني
     * مطابقةَ ستّة جداول سطراً سطراً — والفرقُ الذي يُخطئ فيه سطرٌ واحد
     * يظهر جرداً لا يوازن. العكسُ ثمّ الإعادة عمليةٌ واحدة داخل معاملة:
     * إمّا أن تتمّ كلُّها أو لا يتغيّر شيء.
     *
     * **الفترتان تُفحصان معاً**: فترةُ التاريخ الأصلي — لأنّ العكس يمسّ
     * قيداً مُرحَّلاً فيها — وفترةُ التاريخ الجديد.
     */
    public function update(Request $request, $id)
    {
        $exchange = ProductExchange::with('items')->findOrFail($id);
        $user = Auth::user();

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'storehouse_id' => 'required|exists:store_houses,id',
            'return_storehouse_id' => 'required|exists:store_houses,id|different:storehouse_id',
            'exchange_date' => 'required|date',
            // الصورة اختيارية هنا: الإبقاء على المرفوعة سابقاً هو الأصل
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reason' => 'required|exists:exchange_reasons,id',
        ], [
            'return_storehouse_id.different' => __('sales_exchanges.messages.same_storehouse'),
        ]);

        $originalDate = $exchange->exchange_date;

        if ($blocked = $this->accountingPeriodBlock($originalDate)) {
            return back()->withInput()->with('error', __('sales_exchanges.messages.period_blocked_original', [
                'date' => $originalDate instanceof Carbon
                    ? $originalDate->format('Y-m-d')
                    : (string) $originalDate,
                'reason' => $blocked,
            ]));
        }

        if ($blocked = $this->accountingPeriodBlock($request->exchange_date)) {
            return back()->withInput()->with('error', __('sales_exchanges.messages.period_blocked', [
                'reason' => $blocked,
            ]));
        }

        $before = [
            'exchange_date' => (string) $originalDate,
            'storehouse_id' => $exchange->storehouse_id,
            'return_storehouse_id' => $exchange->return_storehouse_id,
            'total_value' => $exchange->total_value,
            'items' => $exchange->items->count(),
        ];

        try {
            DB::beginTransaction();

            // 1) عكسُ الأثر القديم بالكامل
            $this->reverseExchangeEffects($exchange);
            $exchange->items()->delete();

            // 2) الصورة الجديدة تحلّ محلّ القديمة، وإلّا تبقى القديمة
            $image = $exchange->image;
            if ($request->hasFile('image')) {
                $this->deleteImage($image);
                $image = $this->processAndSaveImage($request->file('image'));
            }

            [$storehouseId, $returnStorehouseId] = $this->resolveStorehouses($request, $user);

            $exchange->update([
                'client_id' => $request->client_id,
                'employee_id' => $request->employee_id ?: $exchange->employee_id,
                'storehouse_id' => $storehouseId,
                'return_storehouse_id' => $returnStorehouseId,
                'exchange_date' => $request->exchange_date,
                'notes' => $request->notes,
                'image' => $image,
                'total_value' => 0,
                // المعرّفان يُعاد ضبطهما في التطبيق؛ تصفيرهما هنا يمنع
                // بقاءَ إشارةٍ إلى إذنٍ حُذف لو أخفق ما بعده
                'outgoing_permit_id' => null,
                'incoming_permit_id' => null,
            ]);

            // 3) إعادةُ التطبيق بالبيانات الجديدة
            $this->applyExchangeEffects($exchange->refresh(), $request->items);

            DB::commit();
        } catch (RuntimeException $e) {
            // خطأٌ متوقَّع بنصٍّ مُعدٍّ للعرض: نقصُ مخزون، أو مستودعٌ مكرَّر
            DB::rollback();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollback();
            OperationLog::failure($e, self::LOG_MODULE, 'update', $exchange);
            Log::error('تعذّر تعديل عملية التبديل: '.$e->getMessage());

            return back()->withInput()->with('error', __('sales_exchanges.messages.save_failed'));
        }

        $exchange->refresh();

        OperationLog::record('updated', self::LOG_MODULE, $exchange, __('sales_exchanges.log.updated', [
            'code' => $exchange->code,
        ]), [
            'old' => $before,
            'new' => [
                'exchange_date' => (string) $exchange->exchange_date,
                'storehouse_id' => $exchange->storehouse_id,
                'return_storehouse_id' => $exchange->return_storehouse_id,
                'total_value' => $exchange->total_value,
                'items' => $exchange->items()->count(),
            ],
        ]);

        return redirect()->route('product-exchanges.show', $exchange->id)
            ->with('success', __('sales_exchanges.messages.updated', ['code' => $exchange->code]));
    }

    // ══════════════════════════════════════════════════════════════════
    //  الأثر المخزني والمحاسبي — مشترَكٌ بين الإنشاء والتعديل والحذف
    // ══════════════════════════════════════════════════════════════════

    /**
     * تطبيقُ أثر التبديل: إذنان مخزنيّان، وحركةُ مخزونٍ في الاتجاهين،
     * وبنودُ العملية، وقيدان محاسبيّان — ثمّ إجماليُّ العملية.
     *
     * كان هذا كلُّه مكتوباً داخل `store()` وحده، فلم يكن للتعديل سبيلٌ
     * إلى إعادة تطبيقه.
     *
     * كفايةُ المخزون تُفحَص للأصناف كلِّها **قبل** أوّل كتابة: الفحصُ
     * داخل حلقة الكتابة كان يُنقص مخزون الأصناف الأولى ثمّ يتراجع، وهو
     * صحيحٌ داخل المعاملة لكنّه يُطيل قفل الصفوف بلا داعٍ.
     *
     * @param  array<int, array<string, mixed>>  $items  بنود الطلب الخام
     *
     * @throws RuntimeException بنصٍّ مُعدٍّ للعرض حين لا يكفي المخزون
     */
    private function applyExchangeEffects(ProductExchange $exchange, array $items): void
    {
        $outStore = (int) $exchange->storehouse_id;
        $inStore = (int) $exchange->return_storehouse_id;

        if ($outStore === $inStore) {
            throw new RuntimeException(__('sales_exchanges.messages.same_storehouse'));
        }

        // ── 1) التسعير وفحص الكفاية، بلا كتابةٍ بعد ──
        $rows = [];
        $total = 0.0;

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];
            $product = Product::find($productId);

            $detailsOut = ProductDetails::where('product_id', $productId)
                ->where('store_house_id', $outStore)
                ->first();

            $available = $detailsOut ? (int) $detailsOut->quantity : 0;

            if ($available < $quantity) {
                throw new RuntimeException(__('sales_exchanges.messages.insufficient_stock', [
                    'product' => $product->name_ar ?? $product->name ?? '—',
                    'available' => $available,
                ]));
            }

            /* **سعرُ التكلفة** لا سعرُ الشراء — المتوسّطُ المرجَّح نفسُه الذي
               تُقيَّد به تكلفةُ الفاتورة والمرتجع (`ResolvesSaleCost`).

               كان `products.purchase_price` ثم `product_details.unit_price` —
               أي سعرَ الشراء المسجَّل على البطاقة أو سعرَ آخر وارد. فقيدُ
               التبديل يُخرج البضاعةَ من المستودع بسعرٍ غير الذي دخلت به
               حسابَه، ويبقى الفرقُ عالقاً في رصيد المستودع. */
            $price = $this->averageUnitCost($product) ?? 0.0;

            $lineTotal = $price * $quantity;
            $total += $lineTotal;

            $rows[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'line_total' => $lineTotal,
                'details_out' => $detailsOut,
                'reason_id' => $item['reason'] ?? null,
                'reason_notes' => $item['reason_notes'] ?? null,
            ];
        }

        $date = $exchange->exchange_date;
        $code = $exchange->code;

        // ── 2) الإذنان المخزنيّان ──
        //
        // ** موافَقٌ عليهما من لحظة الإنشاء ** — الكمّية تتحرّك والقيدان
        // يُرحَّلان هنا نفسه، فالإذن سجلُّ حركةٍ وقعت لا طلبٌ ينتظر. كانا
        // يُنشآن بلا `status` فيأخذان «معلَّق» من النموذج: يظهران في طابور
        // الموافقة، وتسقطهما تقارير المخزن التي تقرأ الموافَق عليه، واعتمادُهما
        // من الشاشة يُحرّك المخزون مرّةً ثانية. ولا يلتقطهما
        // `JournalEntryObserver` لأنّ قيد التبديل ليس من مراجع المخزن.
        // `apporod` هي قيمة الموافقة في هذا النظام، كما تكتبها الفاتورة.
        $outgoingPermit = WarehousePermits::create([
            'permission_date' => $date,
            'permission_source_id' => PermissionSources::PRODUCT_EXCHANGE,
            'reference_type' => 'تبديل منتج',
            'reference_id' => $exchange->id,
            'client_id' => $exchange->client_id,
            'number' => $code.'-OUT',
            'store_houses_id' => $outStore,
            'grand_total' => $total,
            'details' => 'إذن صرف مخزني - تبديل بضاعة رقم '.$code,
            'created_by' => $exchange->created_by,
            'permission_type' => 2,   // صادر
            'status' => 'apporod',
        ]);

        $incomingPermit = WarehousePermits::create([
            'permission_date' => $date,
            'permission_source_id' => PermissionSources::PRODUCT_EXCHANGE_RETURN,
            'reference_type' => 'إرجاع تبديل',
            'reference_id' => $exchange->id,
            'client_id' => $exchange->client_id,
            'number' => $code.'-IN',
            'store_houses_id' => $inStore,
            'grand_total' => $total,
            'details' => 'إذن إضافة مخزني - تبديل بضاعة رقم '.$code,
            'created_by' => $exchange->created_by,
            'permission_type' => 1,   // وارد
            'status' => 'apporod',
        ]);

        // ── 3) حركة المخزون وبنود الإذنين وبنود العملية ──
        foreach ($rows as $row) {
            $detailsOut = $row['details_out'];
            $quantity = $row['quantity'];

            $stockBeforeOut = $detailsOut ? (int) $detailsOut->quantity : 0;

            if ($detailsOut) {
                $detailsOut->decrement('quantity', $quantity);
            }

            WarehousePermitsProducts::create([
                'warehouse_permits_id' => $outgoingPermit->id,
                'product_id' => $row['product_id'],
                'quantity' => $quantity,
                'unit_price' => $row['price'],
                'total' => $row['line_total'],
                'stock_before' => $stockBeforeOut,
                'stock_after' => $stockBeforeOut - $quantity,
            ]);

            $detailsIn = ProductDetails::where('product_id', $row['product_id'])
                ->where('store_house_id', $inStore)
                ->first();

            $stockBeforeIn = $detailsIn ? (int) $detailsIn->quantity : 0;

            if (! $detailsIn) {
                $detailsIn = ProductDetails::create([
                    'product_id' => $row['product_id'],
                    'store_house_id' => $inStore,
                    'quantity' => 0,
                    'unit_price' => $row['price'],
                ]);
            }

            $detailsIn->increment('quantity', $quantity);

            WarehousePermitsProducts::create([
                'warehouse_permits_id' => $incomingPermit->id,
                'product_id' => $row['product_id'],
                'quantity' => $quantity,
                'unit_price' => $row['price'],
                'total' => $row['line_total'],
                'stock_before' => $stockBeforeIn,
                'stock_after' => $stockBeforeIn + $quantity,
            ]);

            ProductExchangeItem::create([
                'product_exchange_id' => $exchange->id,
                'product_id' => $row['product_id'],
                'quantity' => $quantity,
                'unit_price' => $row['price'],
                'total' => $row['line_total'],
                'reason_id' => $row['reason_id'],
                'reason_notes' => $row['reason_notes'],
            ]);
        }

        $exchange->update([
            'total_value' => $total,
            'outgoing_permit_id' => $outgoingPermit->id,
            'incoming_permit_id' => $incomingPermit->id,
        ]);

        // ── 4) القيدان المحاسبيّان ──
        if ($total <= 0) {
            return;
        }

        // تكلفةُ المبيعات من «الحسابات المحاسبية» — لا `SystemAccountIds::COST_OF_SALES`
        $costAccount = $this->accountingAccount('cost_of_sales');
        $outStorehouse = StoreHouse::find($outStore);
        $inStorehouse = StoreHouse::find($inStore);

        $outAccount = $this->warehouseAccount($outStorehouse);
        $inAccount = $this->warehouseAccount($inStorehouse);

        /* حسابٌ ناقص يُوقف الحفظ كلَّه برسالةٍ تسمّيه.

           كانت العمليةُ تُحفظ ويتحرّك المخزونُ بلا قيد، مع `Log::warning` لا
           يراه أحد — فينفصل رصيدُ المستودع في الدفاتر عن كميته. و`RuntimeException`
           يُعرَض نصُّه للمستخدم كما هو، والمعاملةُ تُلغي معه الإذنين وحركةَ
           المخزون التي سبقته. */
        $missing = [];

        if (! $costAccount) {
            $missing[] = $this->accountingAccountLabel('cost_of_sales');
        }
        if (! $outAccount) {
            $missing[] = __('sales_exchanges.messages.warehouse_account', ['name' => $outStorehouse->name ?? '—']);
        }
        if (! $inAccount) {
            $missing[] = __('sales_exchanges.messages.warehouse_account', ['name' => $inStorehouse->name ?? '—']);
        }

        if ($missing !== []) {
            throw new RuntimeException(__('sales_exchanges.messages.accounts_missing', [
                'accounts' => implode('، ', $missing),
            ]));
        }

        // القيد الأول: خروج البضاعة السليمة
        //   من حـ/ تكلفة المبيعات   إلى حـ/ مستودع الصرف
        $entryOut = JournalEntry::create([
            'reference_number' => $code.'-OUT',
            'date' => $date,
            'description' => 'قيد خروج بضاعة تبديل - رقم '.$code.' - من '.($outStorehouse->name ?? ''),
            'status' => 1,
            // عملة النظام لا 'SAR' مثبَّتة: كان القيد يُكتب بالريال ولو
            // كانت عملة المنشأة غيره
            'currency' => $this->defaultCurrency(),
            'client_id' => $exchange->client_id,
            'reference_type' => ReferenceType::PRODUCT_EXCHANGE ?? 'ProductExchange',
            'linking_type' => LinkingType::SINGLE ?? 1,
            'created_by_employee' => Auth::id(),
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entryOut->id,
            'account_id' => $costAccount->id,
            'description' => 'تكلفة بضاعة مبدلة (خروج) - رقم '.$code,
            'debit' => $total,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entryOut->id,
            'account_id' => $outAccount->id,
            'description' => 'تخفيض مخزون مستودع '.($outStorehouse->name ?? '').' - تبديل رقم '.$code,
            'debit' => 0,
            'credit' => $total,
            'is_debit' => false,
        ]);

        // القيد الثاني: ورود البضاعة التالفة
        //   من حـ/ مستودع الاسترجاع   إلى حـ/ تكلفة المبيعات
        $entryIn = JournalEntry::create([
            'reference_number' => $code.'-IN',
            'date' => $date,
            'description' => 'قيد ورود بضاعة مرجعة - رقم '.$code.' - إلى '.($inStorehouse->name ?? ''),
            'status' => 1,
            'currency' => $this->defaultCurrency(),
            'client_id' => $exchange->client_id,
            'reference_type' => ReferenceType::PRODUCT_EXCHANGE ?? 'ProductExchange',
            'linking_type' => LinkingType::SINGLE ?? 1,
            'created_by_employee' => Auth::id(),
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entryIn->id,
            'account_id' => $inAccount->id,
            'description' => 'إضافة مخزون (مرتجع تبديل) لمستودع '.($inStorehouse->name ?? '').' - رقم '.$code,
            'debit' => $total,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entryIn->id,
            'account_id' => $costAccount->id,
            'description' => 'عكس تكلفة بضاعة مرجعة - رقم '.$code,
            'debit' => 0,
            'credit' => $total,
            'is_debit' => false,
        ]);

        Account::updateBalancesHierarchically($outAccount->id);
        Account::updateBalancesHierarchically($inAccount->id);
        Account::updateBalancesHierarchically($costAccount->id);
    }

    /**
     * عكسُ أثر التبديل: تُعاد الكميّات، وتُحذف الإذون وبنودها والقيدان،
     * وتُعاد أرصدة الحسابات هرمياً.
     *
     * لا تُحذف العملية نفسها هنا — الحذف يفعل ذلك بعده، والتعديل يعيد
     * التطبيق مكانه.
     */
    private function reverseExchangeEffects(ProductExchange $exchange): void
    {
        foreach ($exchange->items as $item) {
            // ما نقص من مستودع الصرف يُعاد
            ProductDetails::where('product_id', $item->product_id)
                ->where('store_house_id', $exchange->storehouse_id)
                ->first()?->increment('quantity', $item->quantity);

            // وما زاد في مستودع الاسترجاع يُخصم
            ProductDetails::where('product_id', $item->product_id)
                ->where('store_house_id', $exchange->return_storehouse_id)
                ->first()?->decrement('quantity', $item->quantity);
        }

        foreach ([$exchange->outgoing_permit_id, $exchange->incoming_permit_id] as $permitId) {
            if (! $permitId) {
                continue;
            }

            WarehousePermitsProducts::where('warehouse_permits_id', $permitId)->delete();
            WarehousePermits::where('id', $permitId)->delete();

            // حذفٌ عبر المُنشئ لا النموذج، فلا حدث `deleted` — يُعلَن عنه ليُنقص الرقم
            \App\Services\Fawtra\UsagePush::markBulkChange(\App\Constants\planFeatures\StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS);
        }

        // القيدان يُحذفان قبل إعادة الحساب، فيُبنى الرصيد ممّا بقي
        $entries = JournalEntry::whereIn('reference_number', [
            $exchange->code.'-OUT',
            $exchange->code.'-IN',
        ])->get();

        /* الأرصدةُ تُعاد للحسابات التي رُحّل إليها القيدان **فعلاً** — تُقرأ من
           سطورهما قبل الحذف. كانت تُعاد لحساب التكلفة المكتوب في الشيفرة
           ولحساب المستودع كما هو اليوم، وقد يكون القيدُ رُحّل إلى غيرهما. */
        $touchedAccounts = JournalEntryDetail::whereIn('journal_entry_id', $entries->pluck('id'))
            ->distinct()
            ->pluck('account_id')
            ->filter()
            ->all();

        foreach ($entries as $entry) {
            JournalEntryDetail::where('journal_entry_id', $entry->id)->delete();
            $entry->delete();
        }

        foreach ($touchedAccounts as $accountId) {
            Account::updateBalancesHierarchically((int) $accountId);
        }
    }

    /**
     * حسابُ المستودع: الربط المباشر أوّلاً، ثمّ البحث بـ`storehouse_id`،
     * ثمّ حساب المخزون العام.
     *
     * كان هذا الاشتقاقُ مكتوباً ثلاث مرّات — مرّتين في `store()` ومرّةً في
     * `destroy()` — بثلاث صياغاتٍ متقاربة لا متطابقة: نسخةُ الحذف تبدأ
     * بـ`Account::where('storehouse_id', …)` ونسخةُ الإنشاء تبدأ بـ
     * `$storehouse->account_id`. فقد يُرحَّل القيد إلى حسابٍ ويُعكَس عن
     * آخر إن اختلف الاثنان.
     */
    private function warehouseAccount(?StoreHouse $storehouse): ?Account
    {
        if ($storehouse) {
            if ($storehouse->account_id && $account = Account::find($storehouse->account_id)) {
                return $account;
            }

            if ($account = Account::where('storehouse_id', $storehouse->id)->first()) {
                return $account;
            }
        }

        // حسابُ المخزون العامّ من «الحسابات المحاسبية» — لا `SystemAccountIds::INVENTORY`
        return $this->accountingAccount('inventory');
    }

    /**
     * المستودعان بعد تطبيق نطاق الموظف.
     *
     * الموظّف مقصورٌ على مستودعاته في `default_warehouses`؛ وما خرج عنها
     * يُردّ إلى واحدٍ منها.
     *
     * **العطب الذي كان هنا** — وهو أخطر ما في الشاشة: النسخة القديمة
     * تحسب `$fallback` واحداً (أوّل مستودعات الموظف، وإلّا الرئيسي) ثمّ
     * تسنده إلى **كليهما** حين لا يكون أيٌّ منهما ضمن المسموح:
     *
     *     if (!in_array($storehouse_id, $allowed))        $storehouse_id = $fallback;
     *     if (!in_array($return_storehouse_id, $allowed)) $return_storehouse_id = $fallback;
     *
     * فموظّفٌ لم تُضبط له مستودعات — و`$allowed` فارغة — يخرج بمستودعٍ
     * واحدٍ للطرفين. والتحقّق `different:storehouse_id` لا يمنع ذلك: هو
     * يفحص ما أُرسل، والدمجُ يقع بعده. فتُحفظ عملية تبديلٍ تُخرج البضاعة
     * من مستودعٍ وتُدخلها إليه هو نفسه، بإذنين متقابلين وقيدين يُلغي
     * أحدهما الآخر — أي سجلٌّ لا معنى له، بلا أيّ رسالة خطأ.
     *
     * الآن: من لا مستودعات مضبوطة له يُحترم اختياره كما صادق عليه
     * التحقّق؛ ومن له مستودعات يُقصَر عليها مع الإبقاء على تغايرهما.
     *
     * @return array{0:int, 1:int}
     */
    private function resolveStorehouses(Request $request, $user): array
    {
        $out = $request->storehouse_id;
        $in = $request->return_storehouse_id;

        // `null` بلا قيد: من لا سجلَّ موظفٍ له، والمديرُ الذي نطاقُه الكلّ
        $allowed = $this->allowedStorehouseIds($user);

        if ($allowed !== null) {
            // لا قصرَ بلا نطاقٍ مضبوط
            if ($allowed !== []) {
                if (! in_array($out, $allowed)) {
                    $out = $allowed[0];
                }

                if (! in_array($in, $allowed)) {
                    // مستودعٌ مغايرٌ للأوّل إن كان في نطاقه غيرُه؛ وإلّا
                    // يبقى المطلوب ويردّه فحصُ التطابق برسالةٍ مفهومة
                    $in = collect($allowed)->first(fn ($id) => (int) $id !== (int) $out) ?? $in;
                }
            }
        }

        $major = fn () => optional(StoreHouse::where('major', 1)->first())->id;

        return [(int) ($out ?: $major()), (int) ($in ?: $major())];
    }

    /**
     * المستودعاتُ التي يُقصَر عليها هذا المستخدم في التبديل — أو `null` بلا قيد.
     *
     * **العطب الذي كان**: السؤالُ كان «هل له سجلُّ موظف؟» (`employee_id`)،
     * والمديرُ له سجلُّ موظفٍ كغيره. فيُعامَل مندوباً، ولا مستودعاتٍ مربوطةً
     * له في `default_warehouses`، فتنزل قائمتُه إلى المستودع الرئيسي وحده —
     * والتبديلُ يشترط مستودعين متغايرين. فشاشتا الإنشاء والتعديل عنده
     * بقائمتين فيهما خيارٌ واحدٌ هو نفسُه: لا يُحفظ منهما شيء.
     *
     * السؤالُ الآن عن **النطاق** لا عن السجلّ — السلَّمُ نفسُه الذي تقرؤه
     * {@see \Modules\Sales\Support\SalesWarehouse} لشاشات الفواتير:
     * من نطاقُه الكلّ يرى المستودعات كلَّها، ومن يدير فريقاً يرى مستودعاتِ
     * فريقه مع مستودعاته، والمندوبُ مستودعاتِه هو.
     *
     * @return array<int, int>|null
     */
    private function allowedStorehouseIds($user): ?array
    {
        if (! $user || ! $user->employee_id) {
            return null;
        }

        $employeeIds = DataScope::documentEmployeeIds();

        if ($employeeIds === null) {
            return null;
        }

        return DefaultWarehouses::whereIn('employee_id', $employeeIds ?: [$user->employee_id])
            ->pluck('storehouse_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * مصادرُ نموذج التبديل — عملاءُ ومستودعاتُ وأصنافُ وأسبابُ الشاشة.
     *
     * يُستدعى من الإنشاء والتعديل معاً؛ كان محتواه مكتوباً داخل `create()`
     * وحده فلم يكن للتعديل ما يبني به شاشته.
     *
     * وحين يُمرَّر تبديلٌ قائم تُضمَّ إليه بياناتُه ولو خرجت عن نطاق
     * الموظف اليوم: العميل قد لا يكون مجدولاً لهذا الأسبوع، والمستودع قد
     * يكون نُزع من الموظف بعد العملية — وشاشةُ تعديلٍ لا تعرض ما هو محفوظ
     * فيها تُفسد السجلّ عند أوّل حفظ.
     *
     * @return array<string, mixed>
     */
    private function formContext(?ProductExchange $exchange = null): array
    {
        $user = auth()->user();
        $allowedStorehouses = $this->allowedStorehouseIds($user);

        // المقصورُ هو من له نطاقُ مستودعات — لا كلُّ من له سجلُّ موظف
        $isEmployee = $allowedStorehouses !== null;

        if (! $isEmployee) {
            return [
                'clients' => Client::with(['account' => fn ($q) => $q->select('id', 'client_id', 'balance')])->get(),
                'products' => Product::with('productDetails')->get(),
                'storehouses' => StoreHouse::all(),
                'storehouse' => StoreHouse::where('major', 1)->first(),
                'reasons' => ExchangeReason::getForDropdown(),
                'account_setting' => AccountSetting::where('user_id', $user->id)->first(),
                'isEmployee' => false,
                // شاشتا الإنشاء والتعديل تعرضان قائمة اختيار الموظف المستهدف حين لا يكون المستخدم نفسه موظفاً
                'employees' => Employee::active()->get(),
            ];
        }

        // معرّفات العملاء المسموح بهم في السيلكت — أو `null` فلا قيد،
        // بحسب صلاحيات عرض العملاء (نفس ترتيب الأولوية في ClientController@index)
        $visits = ClientVisibilityScope::eligibleClientIds($user);

        $requested = request()->input('client_id');
        $pinned = $exchange?->client_id;

        $exemptExchangeEnabled = \App\Models\ClientModuleSetting::current()->exempt_exchange_enabled;

        $clientsQuery = Client::query()
            ->with(['account' => fn ($q) => $q->select('id', 'client_id', 'balance')]);

        if ($visits !== null) {
            $clientsQuery->where(function ($q) use ($visits, $requested, $pinned, $exemptExchangeEnabled) {
                $visits->isNotEmpty() ? $q->whereIn('id', $visits) : $q->whereRaw('1 = 0');

                if ($exemptExchangeEnabled) {
                    $q->orWhere('exempt_exchange_transaction', 1);
                }

                if ($requested) {
                    $q->orWhere('id', $requested);
                }

                if ($pinned) {
                    $q->orWhere('id', $pinned);
                }
            });
        }

        $clients = $clientsQuery->get();

        $defaults = DefaultWarehouses::where('employee_id', $user->employee_id)->get();
        $storehouseIds = $allowedStorehouses;

        // مستودعا العملية المعدَّلة يُضمّان ولو نُزعا عن الموظف
        if ($exchange) {
            $storehouseIds = array_values(array_unique(array_filter(array_merge(
                $storehouseIds,
                [$exchange->storehouse_id, $exchange->return_storehouse_id]
            ))));
        }

        if ($storehouseIds !== []) {
            $storehouses = StoreHouse::whereIn('id', $storehouseIds)->get();
            $storehouse = $defaults->first()?->storehouse ?? $storehouses->first();
            $products = Product::with(['productDetails' => fn ($q) => $q->whereIn('store_house_id', $storehouseIds)])
                ->whereHas('productDetails', fn ($q) => $q->whereIn('store_house_id', $storehouseIds))
                ->get();
        } else {
            $storehouse = StoreHouse::where('major', 1)->first();
            $storehouses = collect([$storehouse])->filter();
            $products = Product::with('productDetails')->get();
        }

        return [
            'clients' => $clients,
            'products' => $products,
            'storehouses' => $storehouses,
            'storehouse' => $storehouse,
            'reasons' => ExchangeReason::getForDropdown(),
            'account_setting' => AccountSetting::where('user_id', $user->id)->first(),
            'isEmployee' => true,
        ];
    }
}
