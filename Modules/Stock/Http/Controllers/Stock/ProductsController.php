<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\StockPlanFeatures;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ReferenceType;
use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductsRequest;
use App\Imports\ProductsImport;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Category;
use App\Models\CategoryUnit;
use App\Models\CompiledProducts;
use App\Models\GeneralSettings;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\PermissionSource;
use App\Models\PriceList;
use App\Models\PriceListItems;
use App\Models\Product;
use App\Models\ProductBarcodeSettings as ModelsProductBarcodeSettings;
use App\Models\ProductDetails;
use App\Models\ProductUnit;
use App\Models\Store\Brand;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\TaxSitting;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Fawtra\SubscriptionGate;
use App\Services\Fawtra\UsagePush;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Stock\Http\Controllers\Concerns\BuildsAccountTree;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;
use TCPDF_FONTS;

class ProductsController extends Controller
{
    use BuildsAccountTree;
    use GeneratesDocumentNumbers, ResolvesAccountingAccounts, RespectsAccountingPeriods, UsesDocumentCurrency;
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        // صلاحيات العرض — نفس أسماء `stock.products.*` المستعملة في الراوتات
        // وشاشة إدارة الصلاحيات؛ الأسماء القديمة (`products_view_*`) لا
        // تُقرأ من هناك، فتعطيل صلاحيةٍ جديدة كان بلا أثر
        $this->middleware('permission.role:stock.products.view', ['only' => ['index', 'search']]);
        $this->middleware('permission.role:stock.products.show', ['only' => ['show']]);

        // صلاحية الإضافة/التعديل — لا صلاحية «إنشاء» مستقلة في الكتالوج الجديد
        $this->middleware('permission.role:stock.products.edit', ['only' => ['create', 'store', 'edit', 'update']]);

        // إنشاء الخدمات/المنتجات الرقمية — صلاحيتها المستقلة
        $this->middleware('permission.role:stock.products.services', ['only' => ['create_services', 'store_services']]);

        // صلاحية الحذف
        $this->middleware('permission.role:stock.products.delete', ['only' => ['delete']]);
        $this->middleware('permission.role:stock.products.bulk_delete', ['only' => ['bulkDelete']]);

        // تفعيل/تعطيل المنتج
        $this->middleware('permission.role:stock.products.toggle_status', ['only' => ['updateStatus']]);

        // صلاحية عرض مجموعات الأسعار
        $this->middleware('permission.role:stock.price_lists.view', ['only' => ['showPrices']]);
    }

    /**
     * نطاق رؤية المنتجات — يستبدل منطق النظام القديم الرباعي
     * (`products_view_all/own/warehouse/other/system_products`) بثلاث مراتب،
     * كلّها عبر `ScopesToEmployeeHierarchy` وحدها — لا أسماء نظامٍ قديم أبداً:
     *
     *   ١. **غير مقيَّد** — `data_scope_all` (`accessibleWarehouseIds()` تُرجع
     *      `null`)، أو من ليس موظفاً أصلاً.
     *   ٢. **مستودعه** — عبر `default_warehouses`، ويشمل الآن مستودعات من
     *      يديرهم أيضاً (كان النظام القديم يقتصر على مستودع الموظف نفسه فقط).
     *   ٣. **ملكه هو أو من يديرهم** — عبر `created_by`، بدل «ملكه هو» فقط.
     */
    private function scopeProductsQuery($query, $user): void
    {
        /*
            ═══════════ الصنفُ يُعرض، والكمّيةُ تُقيَّد ═══════════

            **ما كان**: `scopeToProductVisibility()` تُخفي الصنفَ كلَّه إن لم
            يكن له رصيدٌ في مستودعات المستخدم.

            وهو خلطٌ بين بُعدين: **الصنفُ بيانٌ مرجعيٌّ للمنشأة** — تعريفٌ
            واسمٌ وباركود وسعر — لا يرتبط بموظّفٍ بحال. أمّا **الرصيدُ** فهو
            المرتبط بمستودعٍ، والمستودعُ بموظّف.

            وأثرُ الخلط عمليّ: كاشيرٌ يمسح باركودَ صنفٍ نفد من مستودعه فلا
            يجده أصلاً — فيظنّه غيرَ مسجَّل ويُنشئه من جديد، فيصير للصنف
            الواحد سجلّان وباركودٌ مكرَّر. ومن ينقل بضاعةً إلى مستودعه لا
            يجد الصنفَ ليطلبه.

            **وما صار**: كلُّ الأصناف تُعرض، و`productDetails` وحدها مقيَّدةٌ
            بمستودعات المستخدم — فالكمّيةُ المعروضة كمّيتُه هو لا مجموعَ
            المنشأة، وهو المقصودُ من التقييد أصلاً.
        */
        $warehouseIds = $this->accessibleWarehouseIds();

        /*
            `[]` غير `null`: الفارغة تعني «موظفٌ مقيَّد بلا مستودعٍ في نطاقه»
            فلا يرى كمّيةً أصلاً — كما في بقيّة حرّاس الوحدة (`$ids ?: [0]`).
            وكان الشرط `!== []` يقلبها إلى «بلا قيد»، فموظفٌ لم تُربط له
            مستودعات (أو تقاطُعُ نطاقه مع فرع الشريط فارغ) يرى كمّيات
            المنشأة كلِّها.
        */
        if ($warehouseIds !== null) {
            $ids = $warehouseIds ?: [0];
            $query->with(['productDetails' => fn ($q) => $q->whereIn('store_house_id', $ids)]);
        }
    }

    /**
     * هل يجوز لهذا المستخدم عرض هذا المنتج بعينه؟ **نعم دائماً.**
     *
     * القاعدةُ نفسُها في `scopeProductsQuery()`: الصنفُ بيانٌ مرجعيٌّ لا
     * عهدةُ أحد، والمقيَّدُ رصيدُه لا تعريفُه.
     *
     * وكان الفحصُ يحجب صفحةَ الصنف عمّن لا رصيدَ له فيه — ولا يحمي شيئاً:
     * الصنفُ نفسُه معروضٌ في القائمة باسمه وسعره. والتعديلُ يبقى محروساً
     * بـ`canEditProductRecord()`، وهو الحارسُ الذي يعني شيئاً.
     *
     * والدالّةُ تبقى موضعاً واحداً للقرار — تناديها `show()` و`edit()` —
     * فإن لزم تقييدٌ لاحقاً فمكانُه هنا لا متفرّقاً في المتحكّم.
     */
    private function canViewProductRecord(Product $product, $user): bool
    {
        return true;
    }

    /** هل يجوز لهذا المستخدم تعديل/حذف هذا المنتج بعينه؟ `canAccessCreator()` تتجاوز تلقائياً لمن يملك `data_scope_all`. */
    private function canEditProductRecord(Product $product, $user): bool
    {
        return $this->canAccessCreator($product->created_by);
    }

    /**
     * Check if product is active before performing operations
     */
    private function checkProductStatus(Product $product, $operation = 'العملية')
    {
        if (! $product->isActive()) {
            return [
                'error' => true,
                'message' => "لا يمكن {$operation} على منتج غير نشط. يرجى تنشيط المنتج أولاً.",
            ];
        }

        return ['error' => false];
    }

    /**
     * رسالةٌ واضحة بدل تسريب نص SQL الخام للمستخدم.
     *
     * كانت رسالة الفشل `$e->getMessage()` مباشرةً — فمن يكرّر باركوداً
     * مستعملاً يقرأ «SQLSTATE[23000]: Integrity constraint violation: 1062
     * Duplicate entry ... for key 'product_units_barcode_unique'» بدل أن
     * يُقال له ببساطة إن هذا الباركود مستعمل. الحالات الأخرى غير المتوقَّعة
     * تبقى برسالةٍ عامة آمنة — التفصيل الحقيقي مسجَّلٌ عبر `OperationLog`
     * في موضع كل نداء لهذه الدالة، لا مفقوداً.
     */
    private function friendlyStoreErrorMessage(\Throwable $e, string $fallback): string
    {
        if (! $e instanceof QueryException || $e->getCode() !== '23000') {
            return $fallback;
        }

        $message = $e->getMessage();

        if (str_contains($message, 'product_units_barcode_unique')) {
            if (preg_match("/Duplicate entry '([^']+)'/", $message, $matches)) {
                return "الباركود «{$matches[1]}» مستخدَمٌ بالفعل لمنتجٍ أو وحدةٍ أخرى — يرجى اختيار باركودٍ مختلف.";
            }

            return 'هذا الباركود مستخدَمٌ بالفعل لمنتجٍ أو وحدةٍ أخرى — يرجى اختيار باركودٍ مختلف.';
        }

        return 'تعذّر الحفظ: البيانات المُدخَلة تتعارض مع بياناتٍ موجودة مسبقاً.';
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Product::query();
        $this->scopeProductsQuery($query, $user);

        // جلب المنتجات مع التصفح
        $products = $query->orderBy('id', 'DESC')->paginate(20);

        $products->getCollection()->transform(function ($product) {
            $product->can_edit = $this->canAccessCreator($product->created_by);

            return $product;
        });

        // إعدادات الحساب والإعدادات العامة
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        $generalSettings = GeneralSettings::select()->first();
        $categories = Category::select('id', 'name')->get();

        $role = $generalSettings ? $generalSettings->enable_assembly_and_compound_units == 1 : false;

        // لعرض الكمية بحدود مستودعات نطاقه فقط — `[]` هنا تعني «بلا قيد»
        // (القالب يتخطّى التصفية)، والمقيَّدُ بلا مستودعات يُمرَّر له `[0]`
        // فيُصفّى إلى لا شيء بدل أن ينقلب بلا قيد
        $user_warehouse_ids = $this->viewWarehouseIds();

        $showWarehouseQuantity = $this->showWarehouseQuantity();

        return view('stock::products.index', compact('products', 'categories', 'account_setting', 'role', 'user_warehouse_ids', 'showWarehouseQuantity'));
    }

    /**
     * قائمة المستودعات كما تنتظرها قوالب المنتجات: `[]` = بلا قيد،
     * و`[0]` = مقيَّدٌ بلا مستودعات (تصفيةٌ إلى لا شيء).
     */
    private function viewWarehouseIds(): array
    {
        $ids = $this->accessibleWarehouseIds();

        return $ids === null ? [] : ($ids ?: [0]);
    }

    /**
     * الصلاحيات التي تُبقي الكمّية ظاهرة ولو أُطفئ إعدادُ إظهارها:
     * من يضيف أو يعدّل مخزوناً — أذوناً أو جرداً — لا يعمل أعمى عن الرصيد.
     */
    private const STOCK_MANAGE_PERMISSIONS = 'stock.permits.create_addition'
        .'|stock.permits.create_disbursement'
        .'|stock.permits.create_transfer'
        .'|stock.permits.create_opening'
        .'|stock.permits.edit'
        .'|stock.adjustments.create'
        .'|stock.adjustments.edit'
        .'|stock.adjustments.count'
        .'|stock.adjustments.approve';

    /**
     * إعداد «إظهار الكمية في المخزن» من شاشة إعدادات المنتجات.
     *
     * كان يُحفَظ ولا يقرؤه سطرٌ واحد في النظام كله — فإيقافُه لا يُخفي
     * شيئاً ويظنّه المستخدم معطوباً. صار يحكم أعمدة الكمية وبطاقاتها في
     * شاشات المنتجات — **بحسب صلاحيات المستخدم**: الإيقاف يُخفيها عمّن
     * لا يملك صلاحيات إدارة المخزون، ويُبقيها لمن يملكها (عملُه يتطلبها).
     * ونظامٌ بلا صفّ إعداداتٍ بعدُ يُظهر للجميع — الإخفاء قرارٌ يُتَّخذ
     * لا افتراضٌ يُفرَض.
     */
    private function showWarehouseQuantity(): bool
    {
        try {
            $row = GeneralSettings::query()->first(['show_available_quantity_in_warehouse']);
            $enabled = $row === null ? true : (bool) $row->show_available_quantity_in_warehouse;
        } catch (\Throwable $e) {
            return true;
        }

        if ($enabled) {
            return true;
        }

        return (bool) auth()->user()?->hasRolePermission(self::STOCK_MANAGE_PERMISSIONS);
    }

    public function search(Request $request)
    {
        $user = auth()->user();

        $query = Product::query();
        $this->scopeProductsQuery($query, $user);

        // تطبيق فلاتر البحث
        $this->applyFilters($query, $request);

        // جلب النتائج مع التصفح (زيادة عدد المنتجات لكل صفحة)
        $products = $query->orderBy('id', 'DESC')->paginate(24);

        $products->getCollection()->transform(function ($product) {
            $product->can_edit = $this->canAccessCreator($product->created_by);

            return $product;
        });

        // إعدادات الحساب والإعدادات العامة
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        $generalSettings = GeneralSettings::select()->first();
        $categories = Category::select('id', 'name')->get();

        $role = $generalSettings ? $generalSettings->enable_assembly_and_compound_units == 1 : false;

        $user_warehouse_ids = $this->viewWarehouseIds();

        $showWarehouseQuantity = $this->showWarehouseQuantity();

        // إذا كان الطلب AJAX، إرجاع العرض الجزئي فقط
        if ($request->ajax()) {
            return response()->json([
                'html' => view('stock::products.partials.products_list', compact('products', 'user_warehouse_ids', 'account_setting', 'showWarehouseQuantity'))->render(),
                'pagination' => view('stock::products.partials.pagination', compact('products'))->render(),
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ]);
        }

        // إذا لم يكن AJAX، إرجاع الصفحة كاملة
        return view('stock::products.index', compact('products', 'categories', 'account_setting', 'generalSettings', 'role', 'user_warehouse_ids', 'showWarehouseQuantity'));
    }

    /**
     * تطبيق قيود الفرع على الاستعلام
     */
    private function applyBranchRestrictions($query, $user)
    {
        // إذا كان المستخدم فرع رئيسي (main) يرى كل المنتجات
        if ($user->hasRoleName('main') || ! $user->branch_id) { // ✅ hasRoleName() بدلاً من عمود role النصي
            return; // لا تطبيق أي قيود
        }

        // إذا كان المستخدم من فرع عادي، تحقق من إعدادات المشاركة
        if ($user->branch) {
            $branch = $user->branch;
            $shareProductsStatus = $branch->settings()->where('key', 'share_products')->first();

            // إذا كانت المشاركة معطلة (status == 0)، اعرض المنتجات من الفرع الحالي فقط
            if ($shareProductsStatus && $shareProductsStatus->pivot->status == 0) {
                $query->whereHas('creator', function ($q) use ($branch) {
                    $q->where('branch_id', $branch->id);
                });
            }
            // إذا كانت المشاركة مفعلة أو لا توجد إعدادات، اعرض كل المنتجات
        }
    }

    /**
     * تطبيق فلاتر البحث على الاستعلام
     */
    private function applyFilters($query, Request $request)
    {
        // البحث بكلمة مفتاحية
        if ($request->filled('keywords')) {
            $keywords = $request->keywords;
            $query->where(function ($q) use ($keywords) {
                $q->where('name', 'LIKE', "%{$keywords}%")
                    ->orWhere('barcode', 'LIKE', "%{$keywords}%")
                    ->orWhere('serial_number', 'LIKE', "%{$keywords}%")
                    ->orWhere('id', 'LIKE', "%{$keywords}%");
            });
        }

        /*
            الماركة — كان الشرط على `products.brand`، ولا عمود بهذا الاسم:
            العمود `brand_id`. فكل بحثٍ بالماركة يرمي «Unknown column» أي
            500، لا نتيجةً فارغة.
        */
        if ($request->filled('brand')) {
            $query->where('brand_id', $request->brand);
        }

        // فلتر التصنيف
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // فلتر الباركود
        if ($request->filled('barcode')) {
            $query->where('barcode', 'LIKE', "%{$request->barcode}%");
        }

        // فلتر نوع التتبع
        if ($request->filled('track_inventory')) {
            $query->where('track_inventory', $request->track_inventory);
        }

        // فلتر الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلتر نوع المنتج
        if ($request->filled('tab_product_type') && $request->tab_product_type != '') {
            $productType = $request->tab_product_type;
            switch ($productType) {
                case 'products':
                    $query->where('type', 'products');
                    break;
                case 'services':
                    $query->where('type', 'services');
                    break;
                case 'compiled':
                    $query->where('type', 'compiled');
                    break;
            }
        } elseif ($request->filled('product_type') && $request->product_type != '') {
            // استخدام الفلتر من النموذج المتقدم إذا لم يكن هناك قيمة من التبويبات
            $productType = $request->product_type;
            switch ($productType) {
                case 'products':
                    $query->where('type', 'products');
                    break;
                case 'services':
                    $query->where('type', 'services');
                    break;
                case 'compiled':
                    $query->where('type', 'compiled');
                    break;
            }
        }

        // فلتر التاريخ
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [
                $request->from_date.' 00:00:00',
                $request->to_date.' 23:59:59',
            ]);
        } elseif ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date.' 00:00:00');
        } elseif ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date.' 23:59:59');
        }

        /*
            كود المنتج — كان على `products.product_code`، ولا وجود لهذا
            العمود. الكود المعروض في كل شاشة هو `serial_number`.
        */
        if ($request->filled('product_code')) {
            $query->where('serial_number', 'LIKE', "%{$request->product_code}%");
        }

        /*
            حالة المخزون.

            **ما كان**

            الشروط الثلاثة على `products.quantity` — **ولا عمود بهذا الاسم
            في الجدول**. الكمّيات في `product_details`، سطرٌ لكل مستودع.
            فكل اختيارٍ من «متوفر/منخفض/نفد» يرمي «Unknown column: quantity»
            أي صفحة 500، لا نتيجةً فارغة يظنّها المستخدم «لا يوجد».

            وحتى المعنى كان خاطئاً: «منخفض» عُرِّف بالرقم الثابت 10 لكل
            الأصناف، بينما لكل صنفٍ حدُّه في `low_stock_thershold`.

            **ما صار**

            الرصيد مجموع أسطر المستودعات، والحدّ حدُّ الصنف نفسه — وهو
            التعريف نفسه المستعمل في لوحة المخزون، فلا يختلف رقمٌ عن رقم.
            وصنفٌ لا مخزون له (خدمة) لا يدخل في هذه التصفية أصلاً.
        */
        if ($request->filled('stock_status')) {
            $stocked = ['products', 'compiled'];

            // الرصيد كاستعلامٍ فرعيّ: مجموع أسطر المستودعات لهذا الصنف —
            // **ضمن نطاق المستخدم**: فلترةُ «متوفر/نفد» بمجموع المنشأة كلِّها
            // كانت تكشف لمن يرى في العمود صفراً أن الصنف «متوفر» في مستودعات
            // غيره، وتناقض الرقم المعروض أمامه
            $warehouseIds = $this->accessibleWarehouseIds();
            $warehouseFilter = $warehouseIds === null
                ? ''
                : ' and product_details.store_house_id in ('.implode(',', array_map('intval', $warehouseIds ?: [0])).')';

            $balance = 'COALESCE((select sum(quantity) from product_details'
                .' where product_details.product_id = products.id'.$warehouseFilter.'), 0)';

            switch ($request->stock_status) {
                case 'in_stock':
                    $query->whereIn('type', $stocked)
                        ->whereRaw("{$balance} > 0");
                    break;

                case 'low_stock':
                    $query->whereIn('type', $stocked)
                        ->where('low_stock_thershold', '>', 0)
                        ->whereRaw("{$balance} > 0")
                        ->whereRaw("{$balance} <= products.low_stock_thershold");
                    break;

                case 'out_of_stock':
                    $query->whereIn('type', $stocked)
                        ->whereRaw("{$balance} <= 0");
                    break;
            }
        }

        // فلتر متاح نقاط بيع
        if ($request->filled('available_pos')) {
            $query->where('available_pos', $request->available_pos);
        }
    }

    /**
     * تصدير المنتجات
     */

    /**
     * جلب المنتجات مع إحصائيات سريعة
     */
    public function getProductsStats(Request $request)
    {
        $query = Product::query();

        /*
            **الإحصاءُ يتبع نطاقَ الرؤية كما تتبعه القائمة.**

            كان يَعُدّ المنتجاتِ كلَّها بلا قيد، فيقرأ أمينُ مستودعٍ رقماً لا
            يوافق ما تحته: القائمةُ مقيَّدةٌ بمستودعاته والبطاقةُ فوقها تعدّ
            مخزونَ المنشأة كلِّها — فلا يُعرف أيُّ الرقمين رقمُه.

            والقاعدةُ واحدةٌ في `scopeProductsQuery()`: من يملك `data_scope_all`
            أو ليس موظفاً يرى الكلَّ، ومن سواه يرى مستودعاتِه ومن يديرهم.
        */
        $this->scopeProductsQuery($query, auth()->user());
        $this->applyFilters($query, $request);

        /*
            «متوفر/نفد» بمستودعات نطاق المستخدم لا بمستودعات المنشأة:
            `scopeProductsQuery()` تقيّد التحميل المسبق وحده ولا تصل إلى
            `whereHas`، فكانت البطاقتان تعدّان على المخزون كلِّه بينما
            القائمة تحتهما مقيَّدة. والصنف «نافد» هو من **لا** رصيد موجباً
            له في نطاقي — صيغةٌ واحدة تغني عن فرعَي «بلا صفوف/صفوف صفرية».
        */
        $warehouseIds = $this->accessibleWarehouseIds();
        $inMyWarehouses = function ($q) use ($warehouseIds) {
            $q->where('quantity', '>', 0);

            if ($warehouseIds !== null) {
                $q->whereIn('store_house_id', $warehouseIds ?: [0]);
            }
        };

        $stats = [
            'total' => $query->clone()->count(),
            'active' => $query->clone()->where('status', 'active')->count(),
            'inactive' => $query->clone()->where('status', '!=', 'active')->count(),
            // العلاقة اسمها `productDetails` لا `inventory` — الاسم الخاطئ كان
            // يرفع BadMethodCallException في كل نداء
            'in_stock' => $query->clone()->whereHas('productDetails', $inMyWarehouses)->count(),
            'out_of_stock' => $query->clone()->whereDoesntHave('productDetails', $inMyWarehouses)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * تصدير المنتجات إلى Excel أو PDF
     */
    public function export(Request $request)
    {
        $type = $request->get('type', 'excel');

        // بناء الاستعلام الأساسي
        $query = Product::query();

        /*
            **التصديرُ أخطرُ من العرض.**

            كان يُخرج جدولَ المنتجات كلَّه بلا نطاق: موظفٌ لا يرى في الشاشة
            إلّا مستودعَه يُنزّل ملفاً فيه مخزونُ المنشأة كلِّها بأسعاره —
            تسريبٌ صامتٌ لا أثرَ له في الشاشة.
        */
        $this->scopeProductsQuery($query, auth()->user());
        $this->applyFilters($query, $request);

        if ($type === 'pdf') {
            return $this->exportPdf($request, $query);
        } else {
            // تصدير إلى Excel
            $export = new ProductsExport($query);

            return Excel::download($export, 'products_export_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        }
    }

    /**
     * تصدير المنتجات إلى PDF
     */
    public function exportPdf(Request $request, $query)
    {
        // جلب المنتجات مع العلاقات المطلوبة
        $products = $query->with(['category', 'user'])->get();

        // إنشاء PDF جديد باستخدام TCPDF
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // تعيين معلومات الوثيقة
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('تقرير المنتجات');
        $pdf->SetSubject('تقرير المنتجات');
        $pdf->SetKeywords('Products, Report, Fawtra');

        // تعيين الهوامش
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // تعيين الاتجاه من اليمين إلى اليسار
        $pdf->setRTL(true);

        // إضافة صفحة جديدة
        $pdf->AddPage();

        // تعيين الخط العربي
        $fontname = TCPDF_FONTS::addTTFfont(public_path('assets/fonts/aealarabiya.ttf'), 'TrueTypeUnicode', '', 32);
        $pdf->SetFont($fontname, '', 10);

        // إنشاء محتوى HTML
        $html = view('stock::products.partials.products_pdf', compact('products'))->render();

        // إضافة المحتوى للـ PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // إخراج الملف
        $filename = 'تقرير_المنتجات_'.now()->format('Y-m-d').'.pdf';

        return $pdf->Output($filename, 'D');
    }

    /**
     * حذف متعدد للمنتجات
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        try {
            Product::whereIn('id', $request->product_ids)->delete();

            // حذفٌ جماعي لا يُطلق حدث `deleted`، فلا يراه PlanLimitObserver — يُعلَن عنه
            // صراحةً كي يُنقص الرقم عند فوترة ولا يبقى العميل ممنوعاً عند سقفٍ أفرغه
            UsagePush::markBulkChange(StockPlanFeatures::LIMIT_PRODUCTS);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المنتجات المحددة بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Products', 'bulkDelete');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف',
            ], 500);
        }
    }

    /**
     * تحديث سريع لحالة المنتج
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,unactive',
        ]);

        try {
            $product = Product::findOrFail($id);
            $product->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة المنتج بنجاح',
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'updateStatus');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * البحث السريع للمنتجات (للـ autocomplete)
     */
    public function quickSearch(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $products = Product::query();

        // نطاقُ الرؤية يسبق البحث: الإكمالُ التلقائي مخرجٌ كغيره
        $this->scopeProductsQuery($products, auth()->user());

        /*
            شروطُ البحث تُلَفّ في مجموعة: `where(...)->orWhere(...)` بلا لفٍّ
            يجعل الـ`OR` يعلو على شرط النطاق، فيُكمل الحقلُ بأسماء منتجاتٍ
            لا يملك المستخدمُ رؤيتها.
        */
        $products = $products->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
                ->orWhere('barcode', 'LIKE', "%{$term}%")
                ->orWhere('serial_number', 'LIKE', "%{$term}%");
        })
            ->limit(10)
            ->get(['id', 'name', 'barcode', 'serial_number']);

        $results = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'label' => $product->name,
                'value' => $product->name,
                'barcode' => $product->barcode,
                'serial' => $product->serial_number,
            ];
        });

        return response()->json($results);
    }

    /**
     * الحسابات الطرفية تحت جذرٍ مضبوط في «الحسابات المحاسبية».
     *
     * تُعرَض في اختيار حساب المنتج: الترحيل يقع على حسابٍ طرفي لا على أب،
     * فرصيد الأب مجموع أبنائه والترحيل المباشر إليه يُفسد الميزان.
     *
     * @param  string  $key  مفتاح الإعداد
     * @param  int|null  $fallback  الثابت الاحتياطي قبل الزرع
     */
    private function postingAccounts(string $key, ?int $fallback = null)
    {
        $rootId = AccountingSettings::id($key, $fallback);

        if (! $rootId) {
            return collect();
        }

        // كل الأحفاد ثمّ الطرفيّ منها وحده
        $all = [(int) $rootId];
        $current = [$rootId];
        $safety = 0;

        while (! empty($current) && $safety < 20) {
            $children = Account::whereIn('parent_id', $current)->pluck('id')->all();

            if (empty($children)) {
                break;
            }

            $all = array_merge($all, array_map('intval', $children));
            $current = $children;
            $safety++;
        }

        return Account::select('id', 'code', 'name')
            ->whereIn('id', $all)
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('accounts as child')
                    ->whereColumn('child.parent_id', 'accounts.id');
            })
            ->orderBy('code')
            ->get();
    }

    /**
     * المورّدون لقائمة اختيار المنتج — بالاسم التجاري وإلّا بالاسم الشخصي.
     */
    /** اسم الوحدة من معرّفها — والوحدات في `category_units` عبر `units`. */
    private function unitLabel($id)
    {
        if (! $id) {
            return null;
        }

        $categoryUnit = CategoryUnit::with('unit')->find($id);

        if ($categoryUnit) {
            return optional($categoryUnit->unit)->name ?: $categoryUnit->name ?? null;
        }

        return optional(Unit::find($id))->name;
    }

    /** اسم الضريبة ونسبتها. */
    private function taxLabel($id)
    {
        if (! $id) {
            return null;
        }

        $tax = TaxSitting::find($id);

        if (! $tax) {
            return null;
        }

        return $tax->name.' ('.rtrim(rtrim(number_format((float) $tax->value, 2), '0'), '.').'%)';
    }

    /** اسم الحساب المحاسبي ورقمه. */
    private function accountLabel($id)
    {
        if (! $id) {
            return null;
        }

        $account = Account::find($id);

        return $account ? trim($account->code.' — '.$account->name, ' —') : null;
    }

    private function supplierOptions()
    {
        return Supplier::query()
            ->select('id', 'trade_name', 'first_name', 'last_name')
            ->orderBy('trade_name')
            ->get()
            ->map(function ($supplier) {
                $supplier->label = $supplier->trade_name
                    ?: trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? ''))
                    ?: ('مورّد #'.$supplier->id);

                return $supplier;
            });
    }

    public function create()
    {
        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('products');
        $generalSettings = GeneralSettings::select()->first();
        $role = $generalSettings ? $generalSettings->enable_multi_units_system == 1 : false;

        $categories = Category::select('id', 'name')->get();
        $brands = Brand::active()->ordered()->get();
        $price_lists = PriceList::orderBy('id', 'DESC')->paginate(10);

        /*
            الضرائب وحسابا الترحيل — من الإعدادات لا من ثوابت في القالب.

            كان القالب يكتب `$taxOptions = [1 => 'القيمة المضافة', 2 => 'صفرية',
            3 => 'قيمة مضافة']` بيده: ثلاثةُ خيارات لا صلة لها بشاشة «إعدادات
            الضرائب»، واثنان منها بالتسمية نفسها تقريباً. فمن عرّف ضريبةً
            بنسبةٍ أخرى لا يجدها هنا.

            وحسابا المبيعات وتكلفة المبيعات كانا حقلَي نصٍّ حرّ، بينما
            يُقرأ `sales_cost_account` في `OrdersController` **معرّفَ حساب**
            يُرحَّل إليه — فمن يكتب اسماً بدل رقم يُنشئ سطر قيدٍ إلى حسابٍ
            لا وجود له.
        */
        $taxOptions = TaxSitting::orderBy('id')->get();
        $salesAccounts = $this->postingAccounts('revenue', SystemAccountIds::REVENUE);
        $costAccounts = $this->postingAccounts('cost_of_sales_root', SystemAccountIds::COST_OF_SALES_ROOT);

        /*
            المورّدون — كان الحقل `<input type="number" name="supplier_id">`
            أي أن على المستخدم أن يعرف **رقم** المورّد ويكتبه بيده. ولا أحد
            يحفظ أرقام مورّديه، فبقي الحقل فارغاً في كل منتج.
        */
        $suppliers = $this->supplierOptions();

        return view('stock::products.create', compact(
            'categories', 'brands', 'price_lists', 'role', 'serial_number',
            'taxOptions', 'salesAccounts', 'costAccounts', 'suppliers'
        ));
    }

    /**
     * جلب وحدات الصنف المحددة
     */
    public function getCategoryUnits(Request $request)
    {
        try {
            $categoryId = $request->category_id;

            if (! $categoryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى تحديد الصنف',
                    'units' => [],
                ]);
            }

            // جلب الوحدات المرتبطة بالصنف مع معلومات الوحدة
            $categoryUnits = CategoryUnit::where('category_id', $categoryId)
                ->where('is_active', true)
                ->with('unit')
                ->orderBy('is_base_unit', 'desc')
                ->orderBy('conversion_factor', 'asc')
                ->get();

            $units = $categoryUnits->map(function ($categoryUnit) {
                return [
                    'id' => $categoryUnit->id,
                    'unit_id' => $categoryUnit->unit_id,
                    'unit_name' => $categoryUnit->unit->name ?? '',
                    'unit_symbol' => $categoryUnit->unit->symbol ?? '',
                    'conversion_factor' => $categoryUnit->conversion_factor,
                    'is_base_unit' => $categoryUnit->is_base_unit,
                    'full_name' => ($categoryUnit->unit->name ?? '').
                                  ($categoryUnit->unit->symbol ? " ({$categoryUnit->unit->symbol})" : '').
                                  ($categoryUnit->is_base_unit ? ' - أساسية' : ''),
                ];
            });

            return response()->json([
                'success' => true,
                'units' => $units,
                'count' => $units->count(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Products', 'getCategoryUnits');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
                'units' => [],
            ]);
        }
    }

    public function traking()
    {
        $query = Product::whereNotNull('track_inventory');

        // شاشةُ التتبّع تعرض أصنافاً بأرصدتها — فتلزمها حدودُ النطاق
        $this->scopeProductsQuery($query, auth()->user());

        $Products = $query->get();

        return view('stock::products.track', compact('Products'));
    }

    /**
     * إنشاء صنفٍ بلا رصيد — خدمةً كان أو منتجاً رقمياً.
     *
     * الشاشة واحدة لأن الحقول واحدة: كلاهما بلا مستودع ولا وزن ولا أبعاد
     * ولا حدّ إعادة طلب. النوع يُمرَّر لا يُفترَض، فلا تُنسخ الصفحة لكل
     * نوعٍ جديد بلا رصيد.
     */
    public function create_services(Request $request)
    {
        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('products');

        $categories = Category::select('id', 'name')->get();
        $suppliers = $this->supplierOptions();

        // نوعٌ خارج «بلا رصيد» يُردّ إلى الخدمة: هذه الشاشة لا تسأل عن مخزون
        $productType = ProductType::from_value($request->query('type', 'services'));

        if ($productType->tracksStock()) {
            $productType = ProductType::SERVICES;
        }

        /*
            الضرائبُ من جدولها — لا `[1 => 'القيمة المضافة', 2 => 'صفرية']`
            مكتوبةً في القالب: في هذه القاعدة الصفُّ 1 هو «صفرية» (0%) والصفُّ
            2 «القيمة المضافة» (15%)، فخدمةٌ حُفظت «قيمة مضافة» من القالب
            القديم صارت `tax1 = 1` وسُعّرت في الحجز والفاتورة بلا ضريبة.
            القائمةُ نفسُها التي تعرضها شاشةُ المنتج المخزني.
        */
        $taxOptions = TaxSitting::orderBy('id')->get();

        return view('stock::products.create_services', compact(
            'categories', 'serial_number', 'suppliers', 'productType', 'taxOptions'
        ));
    }

    public function store_services(ProductsRequest $request)
    {
        try {
            DB::beginTransaction();

            $product = new Product;

            $product->name = $request->name;
            $product->description = $request->description;
            $product->category_id = $request->category_id;
            /*
                الرقم التسلسلي — حقلٌ تلقائي لا يُملى من النموذج.

                كانت الشاشة تعاين الرقم التالي (`previewDocumentNumber`) —
                معاينةً بلا استهلاك عمداً — ثم يأخذ الحفظ ما أُرسل **كما هو**
                ولا يستهلك شيئاً. فالعدّاد لا يتقدّم أبداً، ويخرج كل منتجٍ
                جديد بالرقم نفسه.

                ثم صار يَقبل ما كتبه المستخدم إن كان الرقم شاغراً — وذاك بابٌ
                لتخطّي التسلسل وانتحال رقم صنفٍ محذوف. الرقم الآن من إعدادات
                «الترقيم المتسلسل» وحدها، يُستهلك تحت قفل، وما يصل من الطلب
                يُهمَل — والشاشة تعرضه للقراءة فقط.
            */
            $product->serial_number = $this->issueDocumentNumber('products');
            // $product->brand = $request->brand;
            $product->supplier_id = $request->supplier_id;

            // توليد الباركود تلقائيًا إذا لم يتم إدخاله
            if ($request->filled('barcode')) {
                $product->barcode = $request->barcode;
            } else {
                // توليد باركود فريد من نوع CODE128
                $barcode = $this->generateUniqueBarcode();
                $product->barcode = $barcode;
            }

            $product->Internal_notes = $request->Internal_notes;
            $product->tags = $request->tags;
            $product->status = $request->status;
            $product->purchase_price = $request->purchase_price;
            $product->sale_price = $request->sale_price;
            $product->tax1 = $request->tax1;
            $product->tax2 = $request->tax2;
            $product->min_sale_price = $request->min_sale_price;
            $product->discount = $request->discount;
            $product->discount_type = $request->discount_type;
            /*
                النوع من الطلب لا ثابتاً `'services'`.

                الشاشة نفسها تخدم الخدمة والمنتج الرقمي، وكلاهما بلا رصيد.
                وأيّ قيمةٍ ذات رصيد تُردّ إلى الخدمة هنا لا في الشاشة وحدها
                — فلا يدخل صنفٌ مخزنيّ من بابٍ لا يسأل عن مستودعه.
            */
            $submittedType = ProductType::from_value($request->input('type', 'services'));
            $product->type = $submittedType->tracksStock()
                ? ProductType::SERVICES->value
                : $submittedType->value;
            $product->profit_margin = $request->profit_margin;
            $product->created_by = Auth::user()->id;

            if ($request->has('available_online')) {
                $product->available_online = 1;
            }

            if ($request->has('featured_product')) {
                $product->featured_product = 1;
            }

            if ($request->hasFile('images')) {
                $product->images = $this->UploadImage('assets/uploads/product', $request->images);
            }

            $product->save();

            // تسجيل نشاط جديد
            OperationLog::record('created', 'product', $product->id, 'تم اضافة خدمة جديدة');

            // تسجيل اشعار نظام جديد
            OperationLog::record(
                'logged',
                'product_log',
                $product->id,
                'تم اضافة خدمة جديدة **'.$product->name.'**',
            );

            DB::commit();

            // جاء من «خدمات الحجز ← خدمة جديدة»: يعود إلى ملفّ حجز الخدمة الجديدة
            if ($request->input('return_to') === 'reservations' && \Illuminate\Support\Facades\Route::has('Reservations.services.create')) {
                return redirect()
                    ->route('Reservations.services.create', ['product_id' => $product->id])
                    ->with(['success' => 'تم إضافة الخدمة بنجاح — اضبط مدّة حجزها وموظفيها أو احفظ بالافتراضي.']);
            }

            return redirect()
                ->route('products.index')
                ->with(['success' => 'تم إضافة الخدمة بنجاح !!']);
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'store_services');
            DB::rollBack();

            return redirect()
                ->back()
                ->with(['error' => 'حدث خطأ أثناء إضافة الخدمة: '.$this->friendlyStoreErrorMessage($e, 'يرجى المحاولة مرة أخرى، وإن تكرّر الخطأ فأبلغ الدعم الفني.')]);
        }
    }

    public function edit($id)
    {
        $user = auth()->user();
        $product = Product::findOrFail($id);

        if (! $this->canEditProductRecord($product, $user)) {
            abort(403, 'غير مصرح لك بتعديل هذا المنتج');
        }

        $categories = Category::select('id', 'name')->get();
        $brands = Brand::active()->ordered()->get();
        $product_details = ProductDetails::where('product_id', $id)->first();

        // الضرائب وحسابا الترحيل من الإعدادات — كما في شاشة الإضافة
        $taxOptions = TaxSitting::orderBy('id')->get();
        $salesAccounts = $this->postingAccounts('revenue', SystemAccountIds::REVENUE);
        $costAccounts = $this->postingAccounts('cost_of_sales_root', SystemAccountIds::COST_OF_SALES_ROOT);
        $suppliers = $this->supplierOptions();

        return view('stock::products.edit', compact(
            'product', 'product_details', 'categories', 'brands',
            'taxOptions', 'salesAccounts', 'costAccounts', 'suppliers'
        ));
    }

    public function show($id)
    {
        $user = auth()->user();
        $product = Product::findOrFail($id);

        if (! $this->canViewProductRecord($product, $user)) {
            abort(403, 'غير مصرح لك بعرض هذا المنتج');
        }

        // جلب المستودع الافتراضي للموظف وجميع مستودعاته المربوطة (ونطاق من يديرهم)
        $user_warehouse_id = null;
        $user_warehouses = collect(); // جميع مستودعات نطاقه
        $user_warehouse_ids = []; // قائمة IDs فقط

        $accessibleWarehouseIds = $this->accessibleWarehouseIds();
        if ($accessibleWarehouseIds !== null) {
            $user_warehouse_ids = $accessibleWarehouseIds;
            $user_warehouse_id = $user_warehouse_ids[0] ?? null;

            // جلب جميع المستودعات ضمن نطاقه
            $user_warehouses = StoreHouse::whereIn('id', $user_warehouse_ids)->get();

            $user_warehouse_ids = $user_warehouses->pluck('id')->toArray();
        }

        // قائمة فلتر المستودعات في تبويب الحركات — مستودعات نطاقه للمقيَّد،
        // والكل لغير المقيَّد. القالب كان يسقط إلى StoreHouse::all() للجميع
        $warehouses = $accessibleWarehouseIds === null ? StoreHouse::all() : $user_warehouses;

        // إعداد «إظهار الكمية في المخزن» يحكم بطاقة الإجمالي وجدول المستودعات
        $showWarehouseQuantity = $this->showWarehouseQuantity();

        /*
            الكمّيات ومستودعاتها بنطاق المستخدم:

              • `null` وحدها تعني «بلا قيد» (`data_scope_all` أو غير موظف) —
                فتُعرض كل المستودعات.
              • المقيَّد يرى مستودعاتِه ومستودعاتِ من يديرهم فقط
                (`accessibleWarehouseIds()` = موظف↔مستودع + التسلسل الإداري
                ∩ فرع الشريط العلوي).
              • **والفارغةُ لا تعني مديراً**: كان `!empty(...)` يقلب موظفاً
                بلا مستودعٍ مربوط إلى «بدون تقيد» فيعرض له مستودعات المنشأة
                كلَّها — وهو عكس بقيّة حرّاس الوحدة (`$ids ?: [0]`).
        */
        if ($accessibleWarehouseIds !== null) {
            $scopedIds = $user_warehouse_ids ?: [0];

            $total_quantity = DB::table('product_details')
                ->where('product_id', $id)
                ->whereIn('store_house_id', $scopedIds)
                ->sum('quantity');
            $storeQuantities = ProductDetails::where('product_id', $id)
                ->whereIn('store_house_id', $scopedIds)
                ->selectRaw('store_house_id, SUM(quantity) as total_quantity')
                ->groupBy('store_house_id')
                ->with('storeHouse')
                ->get();
        } else {
            // بلا قيد (data_scope_all أو غير موظف) — كل المستودعات
            $total_quantity = DB::table('product_details')->where('product_id', $id)->sum('quantity');
            $storeQuantities = ProductDetails::where('product_id', $id)
                ->selectRaw('store_house_id, SUM(quantity) as total_quantity')
                ->groupBy('store_house_id')
                ->with('storeHouse')
                ->get();
        }

        $total_sold = $product->totalSold();
        $sold_last_28_days = $product->totalSoldLast28Days();
        $sold_last_7_days = $product->totalSoldLast7Days();
        $average_cost = $product->averageCost();
        $logs = ModelsLog::where('type', 'product')
            ->where('type_id', $id)
            ->with(['user', 'Product'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // جلب متغيرات المنتج (إن وُجدت) مع كميات كل متغير في كل مستودع
        $productVariants = collect();
        $variantWarehouses = collect();
        if (method_exists($product, 'hasVariants') && $product->hasVariants()) {
            $productVariants = $product->variants()
                ->with([
                    'attributeValues.attribute',
                    'warehouseStock' => function ($q) use ($accessibleWarehouseIds, $user_warehouse_ids) {
                        // `[]` = مقيَّد بلا مستودعات ⇐ لا صفوف، لا «بلا قيد»
                        if ($accessibleWarehouseIds !== null) {
                            $q->whereIn('storehouse_id', $user_warehouse_ids ?: [0]);
                        }
                    },
                    'warehouseStock.storehouse',
                ])
                ->get();

            // المستودعات التي يظهر فيها أي متغير (لاستخدامها كأعمدة في الجدول)
            $warehouseIds = $productVariants
                ->flatMap(fn ($v) => $v->warehouseStock->pluck('storehouse_id'))
                ->unique()
                ->filter()
                ->values();
            if ($warehouseIds->isNotEmpty()) {
                $variantWarehouses = StoreHouse::whereIn('id', $warehouseIds)->orderBy('name')->get();
            }
        }

        // جلب وحدات المنتج
        $productUnits = $product->productUnits()->with('categoryUnit.unit')->get();

        if ($product->type == 'compiled') {
            $CompiledProducts = CompiledProducts::where('compile_id', $id)->get();
        } else {
            $CompiledProducts = collect();
        }

        // جلب حركات المخزون - استخدام leftJoin للحصول على البيانات الصحيحة
        $stock_movements_query = WarehousePermitsProducts::where('warehouse_permits_products.product_id', $id)
            ->leftJoin('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->leftJoin('store_houses', 'warehouse_permits.store_houses_id', '=', 'store_houses.id')
            ->leftJoin('store_houses as from_stores', 'warehouse_permits.from_store_houses_id', '=', 'from_stores.id')
            ->leftJoin('store_houses as to_stores', 'warehouse_permits.to_store_houses_id', '=', 'to_stores.id')
            ->leftJoin('permission_sources', 'warehouse_permits.permission_source_id', '=', 'permission_sources.id')
            ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
            ->select(
                'warehouse_permits_products.*',
                'warehouse_permits.id as permit_id',
                'warehouse_permits.number as permit_number',
                'warehouse_permits.permission_date',
                'warehouse_permits.permission_type',
                'warehouse_permits.store_houses_id',
                'warehouse_permits.from_store_houses_id',
                'warehouse_permits.to_store_houses_id',
                'warehouse_permits.permission_source_id',
                'warehouse_permits.created_by',
                'store_houses.name as store_name',
                'from_stores.name as from_store_name',
                'to_stores.name as to_store_name',
                'permission_sources.name as source_name',
                'users.name as user_name',
                'users.email as user_email'
            );

        // تصفية المشتريات - لا نعرضها أبداً
        $stock_movements_query->where('warehouse_permits.permission_source_id', '!=', PermissionSources::PURCHASE_INVOICE);

        /*
            فلتر حركات المخزون بنطاق المستخدم **كاملاً** لا بأول مستودعٍ فيه:
            كان الشرط على `$user_warehouse_id` (العنصر الأول وحده)، فمشرفٌ
            نطاقُه خمسة مستودعات يقرأ حركات واحدٍ منها ويظنّ البقيّة بلا
            حركة. والفارغُ يُصفّى إلى لا شيء كسائر الحرّاس.
        */
        if ($accessibleWarehouseIds !== null) {
            $movementWarehouseIds = $user_warehouse_ids ?: [0];

            $stock_movements_query->where(function ($q) use ($movementWarehouseIds) {
                $q->where(function ($q2) use ($movementWarehouseIds) {
                    $q2->whereNotIn('warehouse_permits.permission_type', [3, 13])
                        ->whereIn('warehouse_permits.store_houses_id', $movementWarehouseIds);
                })->orWhere(function ($q2) use ($movementWarehouseIds) {
                    $q2->whereIn('warehouse_permits.permission_type', [3, 13])
                        ->where(function ($q3) use ($movementWarehouseIds) {
                            $q3->where(function ($q4) use ($movementWarehouseIds) {
                                $q4->whereIn('warehouse_permits.from_store_houses_id', $movementWarehouseIds)
                                    ->whereRaw('warehouse_permits_products.stock_after < warehouse_permits_products.stock_before');
                            })->orWhere(function ($q4) use ($movementWarehouseIds) {
                                $q4->whereIn('warehouse_permits.to_store_houses_id', $movementWarehouseIds)
                                    ->whereRaw('warehouse_permits_products.stock_after > warehouse_permits_products.stock_before');
                            });
                        });
                });
            });
        }

        $stock_movements = $stock_movements_query->orderBy('warehouse_permits_products.created_at', 'desc')
            ->paginate(100);

        $permission_sources = PermissionSource::all();

        // متغيرات فارغة للـ view (تُستخدم عند الفلترة)
        $source_id = null;
        $warehouse_id = null;
        $user_id = null;

        /*
            مراجع حقول المنتج.

            كانت صفحة العرض تُظهر 20 حقلاً من 50: لا مورّد، ولا وحدة شراء أو
            بيع، ولا ضريبة، ولا حساب إيراد أو تكلفة، ولا خصم، ولا هامش ربح،
            ولا حدّ تنبيه، ولا تاريخ انتهاء، ولا ملاحظات داخلية. وأكثرها
            معرّفاتٌ رقمية لا تُقرأ وحدها، فتُحلّ هنا إلى أسماء.
        */
        $lookups = [
            'supplier' => $product->supplier_id
                ? optional(Supplier::find($product->supplier_id))->trade_name
                : null,
            'creator' => $product->created_by
                ? optional(User::find($product->created_by))->name
                : null,
            'storehouse' => $product->storehouse_id
                ? optional(StoreHouse::find($product->storehouse_id))->name
                : null,
            'purchase_unit' => $this->unitLabel($product->purchase_unit_id),
            'sales_unit' => $this->unitLabel($product->sales_unit_id),
            'sub_unit' => $this->unitLabel($product->sub_unit_id),
            'tax1' => $this->taxLabel($product->tax1),
            'tax2' => $this->taxLabel($product->tax2),
            'sales_account' => $this->accountLabel($product->sales_account),
            'sales_cost_account' => $this->accountLabel($product->sales_cost_account),
        ];

        return view('stock::products.show', compact(
            'product',
            'lookups',
            'CompiledProducts',
            'productUnits',
            'total_quantity',
            'logs',
            'storeQuantities',
            'productVariants',
            'variantWarehouses',
            'total_sold',
            'sold_last_28_days',
            'sold_last_7_days',
            'average_cost',
            'stock_movements',
            'permission_sources',
            'user_warehouse_id',
            'user_warehouses',
            'user_warehouse_ids',
            'warehouses',
            'showWarehouseQuantity',
            'source_id',
            'warehouse_id',
            'user_id'
        ));
    }

    // دالة جديدة لجلب حركة المخزون
    public function getStockMovements(Request $request, $id)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = 100;

            // التحقق من وجود المنتج
            $product = Product::find($id);

            // إذا لم يكن المنتج موجود، نرجع رسالة خطأ
            if (! $product) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'المنتج غير موجود',
                    ], 404);
                }

                return back()->with('error', 'المنتج غير موجود');
            }

            // استلام الفلاتر
            $source_id = $request->get('source_id');
            $date_from = $request->get('date_from');
            $date_to = $request->get('date_to');
            $warehouse_id = $request->get('warehouse_id');
            $user_id = $request->get('user_id');
            $transfer_type = $request->get('transfer_type');

            // بناء الاستعلام الأساسي - نستخدم leftJoin بدلاً من with لتجنب مشاكل العلاقات المفقودة
            $query = WarehousePermitsProducts::where('warehouse_permits_products.product_id', $id)
                ->leftJoin('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->leftJoin('store_houses', 'warehouse_permits.store_houses_id', '=', 'store_houses.id')
                ->leftJoin('store_houses as from_stores', 'warehouse_permits.from_store_houses_id', '=', 'from_stores.id')
                ->leftJoin('store_houses as to_stores', 'warehouse_permits.to_store_houses_id', '=', 'to_stores.id')
                ->leftJoin('permission_sources', 'warehouse_permits.permission_source_id', '=', 'permission_sources.id')
                ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
                ->select(
                    'warehouse_permits_products.*',
                    'warehouse_permits.id as permit_id',
                    'warehouse_permits.number as permit_number',
                    'warehouse_permits.permission_date',
                    'warehouse_permits.permission_type',
                    'warehouse_permits.store_houses_id',
                    'warehouse_permits.from_store_houses_id',
                    'warehouse_permits.to_store_houses_id',
                    'warehouse_permits.permission_source_id',
                    'warehouse_permits.created_by',
                    'store_houses.name as store_name',
                    'from_stores.name as from_store_name',
                    'to_stores.name as to_store_name',
                    'permission_sources.name as source_name',
                    'users.name as user_name',
                    'users.email as user_email'
                );

            // تطبيق الفلاتر
            if ($source_id) {
                $query->where('warehouse_permits.permission_source_id', $source_id);
            }

            if ($date_from) {
                $query->whereDate('warehouse_permits.permission_date', '>=', $date_from);
            }

            if ($date_to) {
                $query->whereDate('warehouse_permits.permission_date', '<=', $date_to);
            }

            /*
                فلتر المستودع — **داخل نطاق المستخدم دائماً**.

                كانت النقطة بلا نطاقٍ إطلاقاً: الشاشة تعرض حركات مستودعات
                نطاقه، وطلبُ الفلترة نفسُه (Ajax) يجيب عن أي مستودعٍ يُكتب
                في `warehouse_id` — التفافٌ كامل على الحجب.

                القاعدة الآن: المقيَّد تُحصر حركاتُه في مستودعات نطاقه
                (هو + من يديرهم ∩ فرع الشريط)، وفلترُ مستودعٍ من خارج النطاق
                يُردّ نتيجةً فارغة لا بياناتِ غيره. `data_scope_all` وغيرُ
                الموظف بلا قيد كما هو مطبَّق في بقية الوحدة.
            */
            $accessibleIds = $this->accessibleWarehouseIds();

            $filterIds = null;      // null = بلا فلتر مستودع إطلاقاً

            if ($accessibleIds !== null) {
                $scoped = $accessibleIds ?: [0];
                $filterIds = $warehouse_id
                    ? (in_array((int) $warehouse_id, $scoped, true) ? [(int) $warehouse_id] : [0])
                    : $scoped;
            } elseif ($warehouse_id) {
                $filterIds = [(int) $warehouse_id];
            }

            if ($filterIds !== null) {
                $query->where(function ($q) use ($filterIds) {
                    // للعمليات العادية (إضافة/صرف)
                    $q->where(function ($q2) use ($filterIds) {
                        $q2->whereNotIn('warehouse_permits.permission_type', [3, 13])
                            ->whereIn('warehouse_permits.store_houses_id', $filterIds);
                    });

                    // لعمليات التحويل (نقل بين المستودعات)
                    $q->orWhere(function ($q2) use ($filterIds) {
                        $q2->whereIn('warehouse_permits.permission_type', [3, 13])
                            ->where(function ($q3) use ($filterIds) {
                                // إذا كان هو المستودع المصدر، نأخذ سجل السحب (stock_after < stock_before)
                                $q3->where(function ($q4) use ($filterIds) {
                                    $q4->whereIn('warehouse_permits.from_store_houses_id', $filterIds)
                                        ->whereRaw('warehouse_permits_products.stock_after < warehouse_permits_products.stock_before');
                                })
                                // إذا كان هو المستودع الهدف، نأخذ سجل الإضافة (stock_after > stock_before)
                                    ->orWhere(function ($q4) use ($filterIds) {
                                        $q4->whereIn('warehouse_permits.to_store_houses_id', $filterIds)
                                            ->whereRaw('warehouse_permits_products.stock_after > warehouse_permits_products.stock_before');
                                    });
                            });
                    });
                });
            }

            // فلتر المستخدم
            if ($user_id) {
                $query->where('warehouse_permits.created_by', $user_id);
            }

            // تصفية المشتريات - لا نعرضها أبداً
            $query->where('warehouse_permits.permission_source_id', '!=', PermissionSources::PURCHASE_INVOICE);

            // جلب البيانات مع الترتيب
            $stock_movements = $query
                ->orderBy('warehouse_permits_products.created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // جلب مصادر الأذونات للفلتر
            $permission_sources = PermissionSource::all();

            if ($request->ajax()) {
                try {
                    $html = view('stock::products.partials.stock_movements',
                        compact('stock_movements', 'product', 'permission_sources', 'source_id', 'date_from', 'date_to', 'warehouse_id', 'user_id'))->render();

                    return response()->json([
                        'success' => true,
                        'html' => $html,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Stock movements view error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

                    return response()->json([
                        'success' => false,
                        'error' => 'خطأ في تحميل البيانات: '.$e->getMessage(),
                    ], 500);
                }
            }

            // قائمة فلتر المستودعات بنطاق المستخدم — القالب كان يسقط إلى
            // StoreHouse::all() فيعرض للمقيَّد مستودعات المنشأة كلَّها
            $warehouses = $accessibleIds === null
                ? StoreHouse::all()
                : StoreHouse::whereIn('id', $accessibleIds ?: [0])->get();

            return view('stock::products.partials.stock_movements',
                compact('stock_movements', 'product', 'permission_sources', 'source_id', 'date_from', 'date_to', 'warehouse_id', 'user_id', 'warehouses'));

        } catch (\Exception $e) {
            \Log::error('Get stock movements error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'حدث خطأ في جلب البيانات: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // دالة جديدة لجلب الجدول الزمني - بدون AJAX
    public function getTimeline(Request $request, $id)
    {
        $page = $request->get('page', 1);
        $perPage = 50;

        $query = WarehousePermitsProducts::where('product_id', $id)
            ->with([
                'warehousePermits' => function ($query) {
                    $query->with(['storeHouse', 'user', 'permissionSource']);
                },
            ]);

        // تصفية المشتريات - لا نعرضها أبداً
        $query = $query->where(function ($q) {
            $q->whereHas('warehousePermits', function ($permit) {
                // عرض كل المصادر الأخرى فقط، ولا نعرض فاتورة الشراء على الإطلاق
                $permit->where('permission_source_id', '!=', PermissionSources::PURCHASE_INVOICE);
            });
        });

        // الجدول الزمني بنطاق مستودعات المستخدم — كان بلا نطاقٍ إطلاقاً
        $accessibleIds = $this->accessibleWarehouseIds();

        if ($accessibleIds !== null) {
            $ids = $accessibleIds ?: [0];

            $query->whereHas('warehousePermits', function ($permit) use ($ids) {
                $permit->where(function ($q) use ($ids) {
                    $q->whereIn('store_houses_id', $ids)
                        ->orWhereIn('from_store_houses_id', $ids)
                        ->orWhereIn('to_store_houses_id', $ids);
                });
            });
        }

        $stock_movements = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $product = Product::findOrFail($id);
        $average_cost = $product->averageCost();

        $html = view('stock::products.partials.timeline', compact('stock_movements', 'product', 'average_cost'))->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'pagination' => [
                'current_page' => $stock_movements->currentPage(),
                'last_page' => $stock_movements->lastPage(),
                'total' => $stock_movements->total(),
            ],
        ]);
    }

    // دالة جديدة لجلب سجل النشاطات
    public function getActivityLogs(Request $request, $id)
    {
        $page = $request->get('page', 1);
        $perPage = 50;

        $logs = ModelsLog::where('type', 'product')
            ->where('type_id', $id)
            ->with(['user', 'Product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $html = view('stock::products.partials.activity_logs', compact('logs'))->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function categories(Request $request)
    {
        $search = $request->input('search');

        // البحث في قاعدة البيانات
        $categories = Category::where('name', 'like', '%'.$search.'%')->get();

        // تحضير النتائج لتتوافق مع Select2
        $results = [];
        foreach ($categories as $category) {
            $results[] = [
                'id' => $category->id,
                'text' => $category->name,
            ];
        }

        return response()->json(['results' => $results]);
    }

    // ============================================
    // 1. تعديل الـ Controller - store function
    // ============================================

    public function store(ProductsRequest $request)
    {
        try {
            DB::beginTransaction();

            $product = new Product;

            $product->name = $request->name;
            $product->material_type = $request->material_type;
            $product->description = $request->description;
            $product->category_id = $request->category_id;
            $product->brand_id = $request->brand_id;
            $product->sub_unit_id = $request->sub_unit_id;
            /*
                الرقم التسلسلي — حقلٌ تلقائي لا يُملى من النموذج.

                كانت الشاشة تعاين الرقم التالي (`previewDocumentNumber`) —
                معاينةً بلا استهلاك عمداً — ثم يأخذ الحفظ ما أُرسل **كما هو**
                ولا يستهلك شيئاً. فالعدّاد لا يتقدّم أبداً، ويخرج كل منتجٍ
                جديد بالرقم نفسه.

                ثم صار يَقبل ما كتبه المستخدم إن كان الرقم شاغراً — وذاك بابٌ
                لتخطّي التسلسل وانتحال رقم صنفٍ محذوف. الرقم الآن من إعدادات
                «الترقيم المتسلسل» وحدها، يُستهلك تحت قفل، وما يصل من الطلب
                يُهمَل — والشاشة تعرضه للقراءة فقط.
            */
            $product->serial_number = $this->issueDocumentNumber('products');

            $product->supplier_id = $request->supplier_id;
            /*
                تتبّع المخزون.

                **ما كان**

                حقل «نوع التتبع» يُرسَل `disabled` ما لم يُفعَّل مفتاحٌ لا اسم
                له — والمتصفّح **لا يرسل الحقول المعطَّلة**. فمن اختار نوعاً ثمّ
                لم يلمس المفتاح، أو لمسه ثمّ أعاده، حُفظ منتجه بلا نوع تتبّع.

                و`inventory_type` **لا حقل له في النموذج أصلاً** — لا في الإضافة
                ولا في التعديل — فكان يُكتب `null` في كل منتج منذ اليوم الأول.

                **ما صار**

                المفتاح صار حقلاً باسمٍ (`track_stock`) يُرسَل، والنوع يُقرأ منه:
                مفتاحٌ مطفأ يعني «الكمية فقط» (4) لا قيمةً فارغة. و`inventory_type`
                صار حقلاً حقيقياً في النموذج.
            */
            $tracksStock = $request->boolean('track_stock');

            $product->track_inventory = $tracksStock
                ? (int) $request->input('track_inventory', 4)
                : 4;

            $product->inventory_type = $request->input('inventory_type');
            $product->low_stock_alert = $request->low_stock_alert;
            $product->sales_cost_account = $request->sales_cost_account;
            $product->sale_price = $request->sale_price;
            $product->Internal_notes = $request->Internal_notes;
            $product->tags = $request->tags;
            $product->status = $request->status;
            $product->purchase_price = $request->purchase_price;
            $product->sale_price = $request->sale_price;
            $product->purchase_unit_id = $request->purchase_unit_id;
            $product->sales_unit_id = $request->sales_unit_id;
            $product->tax1 = $request->tax1;
            $product->tax2 = $request->tax2;
            $product->min_sale_price = $request->min_sale_price;
            $product->discount = $request->discount;
            $product->discount_type = $request->discount_type;
            $product->type = $request->type;
            $product->profit_margin = $request->profit_margin;
            $product->expiry_date = $request->expiry_date;
            $product->notify_before_days = $request->notify_before_days;

            // الحقول الجديدة للمتجر الإلكتروني
            $product->weight = $request->weight;
            $product->dimensions = $request->dimensions ? json_encode($request->dimensions) : null;
            $product->is_returnable = $request->has('is_returnable') ? 1 : 0;
            $product->return_days = $request->return_days ?? 14;

            $product->created_by = Auth::user()->id;

            // توليد الباركود تلقائيًا إذا لم يتم إدخاله
            if ($request->filled('barcode')) {
                $product->barcode = $request->barcode;
            } else {
                // توليد باركود فريد من نوع CODE128
                $barcode = $this->generateUniqueBarcode();
                $product->barcode = $barcode;
            }

            if ($request->has('available_online')) {
                $product->available_online = 1;
            }

            if ($request->has('available_pos')) {
                $product->available_pos = 1;
            }

            if ($request->has('featured_product')) {
                $product->featured_product = 1;
            }

            if ($request->hasFile('images')) {
                $product->images = $this->UploadImage('assets/uploads/product', $request->images);
            }

            $product->save();

            // حفظ وحدات المنتج
            $baseUnitPrices = null; // لتخزين أسعار الوحدة الأساسية

            if ($request->has('units') && is_array($request->units)) {
                foreach ($request->units as $unitData) {
                    if (! empty($unitData['category_unit_id'])) {
                        $productUnit = ProductUnit::create([
                            'product_id' => $product->id,
                            'category_unit_id' => $unitData['category_unit_id'],
                            'barcode' => $unitData['barcode'] ?? null,
                            'purchase_price' => $unitData['purchase_price'] ?? null,
                            'sale_price' => $unitData['sale_price'] ?? null,
                            'min_sale_price' => $unitData['min_sale_price'] ?? null,
                            'wholesale_price' => $unitData['wholesale_price'] ?? null,
                            'is_default' => isset($unitData['is_default']) ? true : false,
                            'is_active' => true,
                        ]);

                        // حفظ أسعار الوحدة الأساسية
                        if (isset($unitData['is_base_unit']) && $unitData['is_base_unit'] == '1') {
                            $baseUnitPrices = [
                                'barcode' => $unitData['barcode'] ?? null,
                                'purchase_price' => $unitData['purchase_price'] ?? null,
                                'sale_price' => $unitData['sale_price'] ?? null,
                                'min_sale_price' => $unitData['min_sale_price'] ?? null,
                            ];
                        }
                    }
                }

                // تحديث أسعار وباركود المنتج من الوحدة الأساسية إذا وجدت
                if ($baseUnitPrices) {
                    if (! empty($baseUnitPrices['barcode'])) {
                        $product->barcode = $baseUnitPrices['barcode'];
                    }
                    $product->purchase_price = $baseUnitPrices['purchase_price'];
                    $product->sale_price = $baseUnitPrices['sale_price'];
                    $product->min_sale_price = $baseUnitPrices['min_sale_price'];
                    $product->save();
                }
            }

            ProductDetails::create([
                'quantity' => 0,
                'product_id' => $product->id,
            ]);

            if ($request->has('price_list_id') && ! empty($request->price_list_id)) {
                PriceListItems::create([
                    'product_id' => $product->id,
                    'price_list_id' => $request->price_list_id,
                    'sale_price' => $request->price_list,
                ]);
            }

            // تسجيل نشاط جديد
            OperationLog::record('created', 'product', $product->id, 'تم اضافة منتج جديد');

            // تسجيل اشعار نظام جديد
            OperationLog::record('logged', 'product_log', $product->id, 'تم اضافة منتج جديد **'.$product->name.'**');

            DB::commit();

            // إذا كان الطلب AJAX، إرجع JSON بدلاً من redirect
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $product->type == 'services' ? 'تم إضافة الخدمة بنجاح!!' : 'تم إضافة المنتج بنجاح!!',
                    'product_id' => $product->id,
                    'redirect_url' => route('products.index'),
                ], 200);
            }

            // إذا لم يكن AJAX، إرجع redirect عادي
            if ($product->type == 'services') {
                return redirect()
                    ->route('products.index')
                    ->with(['success' => 'تم إضافة الخدمة بنجاح !!']);
            }

            return redirect()
                ->route('products.index')
                ->with(['success' => 'تم إضافة المنتج بنجاح !!']);

        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'store');
            DB::rollBack();

            $message = $this->friendlyStoreErrorMessage($e, 'يرجى المحاولة مرة أخرى، وإن تكرّر الخطأ فأبلغ الدعم الفني.');

            // إذا كان الطلب AJAX، إرجع JSON بدلاً من redirect
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء إضافة المنتج: '.$message,
                ], 422);
            }

            return redirect()
                ->back()
                ->with(['error' => 'حدث خطأ أثناء إضافة المنتج: '.$message]);
        }
    } // End Stor

    // دالة لتوليد باركود فريد
    private function generateUniqueBarcode()
    {
        $maxAttempts = 10; // الحد الأقصى لمحاولات التوليد
        $attempt = 0;

        do {
            // توليد رقم عشوائي من 12 رقم
            $barcode = '2'.str_pad(rand(0, 99999999999), 11, '0', STR_PAD_LEFT);
            $attempt++;

            // منع الحلقة اللانهائية
            if ($attempt > $maxAttempts) {
                // في حالة تجاوز الحد الأقصى، نستخدم timestamp كجزء من الباركود
                $barcode = '2'.substr(time().rand(1000, 9999), 0, 11);
                break;
            }
        } while (Product::where('barcode', $barcode)->exists());

        return $barcode;
    }

    // اضافة الخدمة
    public function update(ProductsRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = auth()->user();
            $product = Product::findOrFail($id);

            if (! $this->canEditProductRecord($product, $user)) {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'غير مصرح لك بتعديل هذا المنتج'], 403);
            }

            // فحص حالة المنتج - يُسمح بالتعديل حتى للمنتجات غير النشطة
            // لكن سنضيف تحذير للمستخدم
            $statusWarning = '';
            if (! $product->isActive() && $request->status != ProductStatus::ACTIVE->value) {
                $statusWarning = ' (تحذير: المنتج غير نشط ولن يتم استخدامه في العمليات)';
            }

            $oldName = $product->name;

            // تحديث الحقول الأساسية
            $product->name = $request->name;
            $product->material_type = $request->material_type;
            $product->description = $request->description;
            $product->category_id = $request->category_id;
            $product->brand_id = $request->brand_id;
            /*
                الرقم التسلسلي في التعديل — لا يُمسّ.

                رقم الصنف مرجعٌ تحمله حركاتُ المخزون والأذونُ والمطبوعاتُ
                والباركود على الرفّ؛ فتغييرُه من شاشة التعديل يفصل الصنف عن
                أثره كلّه. وكان النموذج يستطيع تغييره: رقمٌ جديد يمرّ كما
                كُتب، فيصير للصنف رقمان في السجلّ ورقمٌ ثالث في التقرير.
                يبقى كما صدر، ولا يُستهلك تالٍ إلّا لصنفٍ قديم وصل بلا رقم.
            */
            $product->serial_number = $this->keepDocumentNumber($product->serial_number, 'products');

            $product->supplier_id = $request->supplier_id;

            // تحديث الباركود مع التحقق من عدم كونه فارغًا
            if ($request->filled('barcode')) {
                $product->barcode = $request->barcode;
            } elseif (empty($product->barcode)) {
                // توليد باركود تلقائيًا إذا لم يكن موجودًا
                $product->barcode = $this->generateUniqueBarcode();
            }

            $product->Internal_notes = $request->Internal_notes;
            $product->tags = $request->tags;
            $product->status = $request->status;
            $product->purchase_price = $request->purchase_price;
            $product->sale_price = $request->sale_price;
            $product->tax1 = $request->tax1;
            $product->tax2 = $request->tax2;
            $product->min_sale_price = $request->min_sale_price;
            $product->discount = $request->discount;
            $product->discount_type = $request->discount_type;
            $product->profit_margin = $request->profit_margin;

            // معالجة الحقول الاختيارية بطريقة آمنة
            if ($request->filled('sub_unit_id')) {
                $product->sub_unit_id = $request->sub_unit_id;
            }

            if ($request->filled('purchase_unit_id')) {
                $product->purchase_unit_id = $request->purchase_unit_id;
            }

            if ($request->filled('sales_unit_id')) {
                $product->sales_unit_id = $request->sales_unit_id;
            }

            if ($request->filled('sales_account')) {
                $product->sales_account = $request->sales_account;
            }

            if ($request->filled('sales_cost_account')) {
                $product->sales_cost_account = $request->sales_cost_account;
            }

            if ($request->filled('track_inventory')) {
                $product->track_inventory = $request->track_inventory;
            }

            if ($request->filled('inventory_type')) {
                $product->inventory_type = $request->inventory_type;
            }

            if ($request->filled('low_stock_alert')) {
                $product->low_stock_alert = $request->low_stock_alert;
            }

            if ($request->filled('expiry_date')) {
                $product->expiry_date = $request->expiry_date;
            }

            if ($request->filled('notify_before_days')) {
                $product->notify_before_days = $request->notify_before_days;
            }

            // معالجة نوع المنتج (إذا كان موجود في الطلب)
            if ($request->filled('type')) {
                $product->type = $request->type;
            }

            // إعادة تعيين القيم المنطقية
            $product->available_online = $request->has('available_online') ? 1 : 0;
            $product->available_pos = $request->has('available_pos') ? 1 : 0;
            $product->featured_product = $request->has('featured_product') ? 1 : 0;

            // الحقول الجديدة للمتجر الإلكتروني
            $product->weight = $request->weight;
            $product->dimensions = $request->dimensions ? json_encode($request->dimensions) : null;
            $product->is_returnable = $request->has('is_returnable') ? 1 : 0;
            $product->return_days = $request->return_days ?? 14;

            // معالجة رفع الصور
            if ($request->hasFile('images')) {
                // حذف الصورة القديمة إذا كانت موجودة
                if (! empty($product->images) && file_exists(public_path($product->images))) {
                    unlink(public_path($product->images));
                }
                $product->images = $this->UploadImage('assets/uploads/product', $request->images);
            }

            // حفظ التحديثات
            $product->save();

            // حفظ وحدات المنتج (Product Units)
            $baseUnitPrices = null; // لتخزين أسعار الوحدة الأساسية

            if ($request->has('units') && is_array($request->units)) {
                // الحصول على IDs الوحدات الموجودة حالياً
                $existingUnitsIds = $product->productUnits->pluck('id')->toArray();
                $submittedUnitsIds = [];

                // التأكد من وجود وحدة افتراضية واحدة فقط
                $hasDefault = false;

                foreach ($request->units as $unitData) {
                    // تحقق من البيانات الأساسية
                    if (! isset($unitData['category_unit_id'])) {
                        continue;
                    }

                    // تحقق من is_default
                    $isDefault = isset($unitData['is_default']) && $unitData['is_default'] == '1';

                    // إذا كانت هذه الوحدة افتراضية، قم بإلغاء الافتراضية من جميع الوحدات الأخرى
                    if ($isDefault) {
                        if ($hasDefault) {
                            // إذا كان هناك وحدة افتراضية سابقة، اجعل هذه غير افتراضية
                            $isDefault = false;
                        } else {
                            $hasDefault = true;
                        }
                    }

                    // تحضير بيانات الوحدة
                    $unitDataToSave = [
                        'category_unit_id' => $unitData['category_unit_id'],
                        'barcode' => $unitData['barcode'] ?? null,
                        'purchase_price' => $unitData['purchase_price'] ?? 0,
                        'sale_price' => $unitData['sale_price'] ?? 0,
                        'min_sale_price' => $unitData['min_sale_price'] ?? 0,
                        'wholesale_price' => $unitData['wholesale_price'] ?? 0,
                        'is_default' => $isDefault,
                    ];

                    // حفظ أسعار الوحدة الأساسية
                    if (isset($unitData['is_base_unit']) && $unitData['is_base_unit'] == '1') {
                        $baseUnitPrices = [
                            'barcode' => $unitData['barcode'] ?? null,
                            'purchase_price' => $unitData['purchase_price'] ?? 0,
                            'sale_price' => $unitData['sale_price'] ?? 0,
                            'min_sale_price' => $unitData['min_sale_price'] ?? 0,
                        ];
                    }

                    // إذا كان هناك ID، قم بالتحديث، وإلا أنشئ جديد
                    if (isset($unitData['id']) && in_array($unitData['id'], $existingUnitsIds)) {
                        $productUnit = ProductUnit::find($unitData['id']);
                        if ($productUnit) {
                            $productUnit->update($unitDataToSave);
                            $submittedUnitsIds[] = $productUnit->id;
                        }
                    } else {
                        // إنشاء وحدة جديدة
                        $newUnit = ProductUnit::create(array_merge($unitDataToSave, [
                            'product_id' => $product->id,
                        ]));
                        $submittedUnitsIds[] = $newUnit->id;
                    }
                }

                // حذف الوحدات التي لم تعد موجودة في النموذج
                $unitsToDelete = array_diff($existingUnitsIds, $submittedUnitsIds);
                if (! empty($unitsToDelete)) {
                    ProductUnit::whereIn('id', $unitsToDelete)->delete();
                }

                // إذا لم يتم تحديد أي وحدة كافتراضية، اجعل أول وحدة هي الافتراضية
                if (! $hasDefault && $product->productUnits()->count() > 0) {
                    $product->productUnits()->first()->update(['is_default' => true]);
                }

                // تحديث أسعار وباركود المنتج من الوحدة الأساسية إذا وجدت
                if ($baseUnitPrices) {
                    if (! empty($baseUnitPrices['barcode'])) {
                        $product->barcode = $baseUnitPrices['barcode'];
                    }
                    $product->purchase_price = $baseUnitPrices['purchase_price'];
                    $product->sale_price = $baseUnitPrices['sale_price'];
                    $product->min_sale_price = $baseUnitPrices['min_sale_price'];
                    $product->save();
                }
            }

            // معالجة قائمة الأسعار (إذا كانت موجودة)
            if ($request->filled('price_list_id') && $request->filled('price_list')) {
                $priceListItem = PriceListItems::where('product_id', $product->id)
                    ->where('price_list_id', $request->price_list_id)
                    ->first();

                if ($priceListItem) {
                    $priceListItem->update([
                        'sale_price' => $request->price_list,
                    ]);
                } else {
                    PriceListItems::create([
                        'product_id' => $product->id,
                        'price_list_id' => $request->price_list_id,
                        'sale_price' => $request->price_list,
                    ]);
                }
            }

            // تسجيل نشاط التعديل
            OperationLog::record('updated', 'product', $product->id, 'تم تعديل المنتج');

            // تسجيل إشعار نظام للتعديل
            OperationLog::record(
                'logged',
                'product_log',
                $product->id,
                'تم تعديل المنتج من **'.$oldName.'** إلى **'.$product->name.'**',
            );

            DB::commit();

            // إرجاع استجابة JSON للـ Ajax
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => ($product->type == 'services') ? 'تم تحديث الخدمة بنجاح !!' : 'تم تحديث المنتج بنجاح !!',
                    'redirect' => route('products.index'),
                ]);
            }

            // الاستجابة العادية
            $successMessage = ($product->type == 'services') ? 'تم تحديث الخدمة بنجاح !!' : 'تم تحديث المنتج بنجاح !!';

            return redirect()
                ->route('products.index')
                ->with(['success' => $successMessage]);

        } catch (\Exception $ex) {
            OperationLog::failure($ex, 'product_log', 'update');
            DB::rollback();

            // تسجيل الخطأ في اللوج للتشخيص
            \Log::error('Product Update Error: '.$ex->getMessage(), [
                'product_id' => $id,
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'trace' => $ex->getTraceAsString(),
            ]);

            // إرجاع استجابة JSON للـ Ajax في حالة الخطأ
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث المنتج',
                    'error_details' => config('app.debug') ? $ex->getMessage() : null,
                    'errors' => [],
                ], 422);
            }

            // الاستجابة العادية للطلبات غير Ajax
            return redirect()
                ->back()
                ->with(['error' => 'حدث خطأ أثناء تحديث المنتج. يرجى المحاولة مرة أخرى.'])
                ->withInput();
        }
    }

    // دالة مساعدة لتشخيص المشاكل - يمكن إضافتها مؤقتاً
    public function debugUpdate(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            return response()->json([
                'product_data' => $product->toArray(),
                'request_data' => $request->all(),
                'filled_fields' => array_filter($request->all(), function ($value) {
                    return ! is_null($value) && $value !== '';
                }),
            ]);

        } catch (\Exception $ex) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($ex, 'Products', 'debugUpdate');

            return response()->json([
                'error' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
            ]);
        }
    }

    public function compiled()
    {
        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('products');
        $generalSettings = GeneralSettings::select()->first();
        $role = $generalSettings ? $generalSettings->enable_multi_units_system == 1 : false;
        // نطاقُ الرؤية: مستودعاتُه ومستودعاتُ من يديرهم لا مستودعات المنشأة
        $storehouses = $this->accessibleWarehouses(['id', 'name'])->get();
        $products = Product::where('type', 'products')->get();
        $categories = Category::select('id', 'name')->get();

        return view('stock::products.compiled', compact('categories', 'storehouses', 'products', 'role', 'serial_number'));
    }

    public function compiled_store(Request $request)
    {
        // الفترة المحاسبية: إنشاء المنتج المجمَّع يُرحِّل قيد إضافة للمخزون
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        try {
            DB::beginTransaction();

            $product = new Product;

            $product->name = $request->name;
            $product->description = $request->description;
            $product->category_id = $request->category_id;
            $product->sub_unit_id = $request->sub_unit_id;
            /*
                الرقم التسلسلي — حقلٌ تلقائي لا يُملى من النموذج.

                كانت الشاشة تعاين الرقم التالي (`previewDocumentNumber`) —
                معاينةً بلا استهلاك عمداً — ثم يأخذ الحفظ ما أُرسل **كما هو**
                ولا يستهلك شيئاً. فالعدّاد لا يتقدّم أبداً، ويخرج كل منتجٍ
                جديد بالرقم نفسه.

                ثم صار يَقبل ما كتبه المستخدم إن كان الرقم شاغراً — وذاك بابٌ
                لتخطّي التسلسل وانتحال رقم صنفٍ محذوف. الرقم الآن من إعدادات
                «الترقيم المتسلسل» وحدها، يُستهلك تحت قفل، وما يصل من الطلب
                يُهمَل — والشاشة تعرضه للقراءة فقط.
            */
            $product->serial_number = $this->issueDocumentNumber('products');

            $product->supplier_id = $request->supplier_id;

            // توليد الباركود تلقائيًا إذا لم يتم إدخاله
            if ($request->filled('barcode')) {
                $product->barcode = $request->barcode;
            } else {
                // توليد باركود فريد من نوع CODE128
                $barcode = $this->generateUniqueBarcode();
                $product->barcode = $barcode;
            }

            $product->track_inventory = $request->track_inventory;
            $product->inventory_type = $request->inventory_type;
            $product->low_stock_alert = $request->low_stock_alert;
            $product->sales_cost_account = $request->sales_cost_account;
            $product->sale_price = $request->sale_price;
            $product->Internal_notes = $request->Internal_notes;
            $product->tags = $request->tags;
            $product->status = $request->status;
            $product->purchase_price = $request->purchase_price;
            $product->purchase_unit_id = $request->purchase_unit_id;
            $product->sales_unit_id = $request->sales_unit_id;
            $product->tax1 = $request->tax1;
            $product->tax2 = $request->tax2;
            $product->min_sale_price = $request->min_sale_price;
            $product->discount = $request->discount;
            $product->discount_type = $request->discount_type;
            $product->type = $request->type;
            $product->profit_margin = $request->profit_margin;
            $product->storehouse_id = $request->storehouse_id; // مخزن المنتجات الاوليه للمنتج التجميعي
            $product->compile_type = $request->compile_type ?? 'Instant'; // نوع التجميعه معد مسبقا او فوري
            $product->created_by = Auth::user()->id;

            if ($request->has('available_online')) {
                $product->available_online = 1;
            }

            if ($request->has('featured_product')) {
                $product->featured_product = 1;
            }

            if ($request->hasFile('images')) {
                $product->images = $this->UploadImage('assets/uploads/product', $request->images);
            }

            $product->save();

            // تحقق من صحة البيانات
            $request->validate([
                'products' => 'required|array', // تأكد من وجود بيانات المنتجات
                'products.*.product_id' => 'required|exists:products,id', // تأكد من وجود product_id في جدول products
                'products.*.quantity' => 'required|numeric|min:1', // تأكد من أن الكمية رقم صحيح أكبر من 0
            ]);

            foreach ($request->products as $productData) {
                $compiledProduct = new CompiledProducts;

                // تعيين compile_id إلى المنتج التجميعي
                $compiledProduct->compile_id = $product->id; // هذا هو المنتج التجميعي ويجب أن يتكرر لجميع المنتجات المرتبطة به

                // تعيين product_id إلى المنتج الفرعي
                $compiledProduct->product_id = $productData['product_id']; // معرّف المنتج الفردي

                // تعيين الكمية
                $compiledProduct->qyt = $productData['quantity']; // الكمية الخاصة بالمنتج

                // حفظ البيانات في جدول CompiledProducts
                $compiledProduct->save();
            }

            $quantity = $request->filled('quantity') ? $request->quantity : 0;

            // إنشاء سجل جديد في `ProductDetails`
            ProductDetails::create([
                'quantity' => $quantity,
                'product_id' => $product->id,
                'store_house_id' => $request->storehouse_id,
            ]);

            // ✅ استخدام المصدر الثابت: اذن اضافة مخزن
            $permissionSourceId = PermissionSources::WAREHOUSE_ADD;

            // رقم الإذن يُستهلك من إعدادات «الترقيم المتسلسل»: الإذن يُحفَظ فعلاً،
            // والمعاينةُ لا تُقدّم العدّاد — فكانت كل حركةٍ تأخذ الرقم نفسه
            $serial_number = $this->issueDocumentNumber('warehouse-add');

            // حساب المبلغ - استخدام sale_price أو purchase_price أيهما متوفر
            $unitPrice = $product->sale_price ?? $product->purchase_price ?? 0;
            $amount = $quantity * $unitPrice;

            // ✅ التأكد من وجود حساب المستودع
            // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في
            // «الحسابات المحاسبية»، بنوعٍ وكودٍ موحَّدين
            $storeAccount = $this->warehouseAccount($request->storehouse_id, optional(StoreHouse::find($request->storehouse_id))->name);

            // إنشاء الإذن المخزني
            $warehousePermit = WarehousePermits::create([
                'store_houses_id' => $request->storehouse_id,
                'permission_source_id' => $permissionSourceId,
                'number' => $serial_number,
                'permission_date' => now(),
                'grand_total' => $amount,
                'created_by' => Auth::id(),
                'details' => 'إضافة منتج تجميعي جديد: '.$product->name,
            ]);

            // حفظ تفاصيل المنتج في الإذن المخزني
            WarehousePermitsProducts::create([
                'quantity' => $quantity,
                'total' => $amount,
                'unit_price' => $unitPrice,
                'product_id' => $product->id,
                'warehouse_permits_id' => $warehousePermit->id,
                'stock_before' => 0,
                'stock_after' => $quantity,
            ]);

            // ✅ إنشاء قيد اليومية
            $journalEntry = JournalEntry::create([
                'reference_number' => 'COMPILED_'.now()->timestamp,
                'warehouse_permit_id' => $warehousePermit->id,
                'date' => now(),
                'description' => "إضافة منتج تجميعي جديد: {$product->name} - كمية: {$quantity} وحدة",
                'status' => 1,
                'currency' => $this->defaultCurrency(),
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
                'linking_type' => LinkingType::SINGLE,
                'reference_id' => $product->id,
                'amount' => $amount,
            ]);

            // 1️⃣ تفصيل القيد - حساب المستودع (مدين)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $storeAccount->id,
                'description' => $journalEntry->description,
                'debit' => $amount,
                'credit' => 0,
            ]);

            // 2️⃣ تفصيل القيد - أطراف دائنة أخرى (دائن)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $this->requireAccountingAccountId('other_creditor_parties', SystemAccountIds::OTHER_CREDITOR_PARTIES),
                'description' => $journalEntry->description,
                'debit' => 0,
                'credit' => $amount,
            ]);

            // ✅ تحديث رصيد حساب المستودع
            $storeAccount->balance += $amount;
            $storeAccount->save();

            // تحديث أرصدة الحسابات بشكل هرمي لضمان التوازن المحاسبي
            Account::updateBalancesHierarchically($storeAccount->id);

            // تسجيل إشعار نظام
            OperationLog::record(
                'logged',
                'product_log',
                $product->id,
                'تم إضافة منتج تجميعي جديد **'.$product->name.'** بكمية **'.$quantity.'** وحدة',
            );

            DB::commit();

            if ($product->type == 'services') {
                return redirect()
                    ->route('products.index')
                    ->with(['success' => 'تم إضافة الخدمة بنجاح !!']);
            }

            return redirect()
                ->route('products.index')
                ->with(['success' => 'تم إضافة المنتج بنجاح !! وتم إنشاء قيد يومية وإذن مخزني تلقائياً']);
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'compiled_store');
            DB::rollBack();

            return redirect()
                ->back()
                ->with(['error' => 'حدث خطأ أثناء إضافة المنتج: '.$this->friendlyStoreErrorMessage($e, 'يرجى المحاولة مرة أخرى، وإن تكرّر الخطأ فأبلغ الدعم الفني.')]);
        }
    }

    public function delete($id)
    {
        $user = auth()->user();
        $product = Product::findOrFail($id);

        if (! $this->canEditProductRecord($product, $user)) {
            return redirect()->back()->with(['error' => 'غير مصرح لك بحذف هذا المنتج']);
        }

        // تسجيل اشعار نظام جديد
        OperationLog::record('logged', 'product_log', $product->id, 'تم حذف المنتج **'.$product->name.'**');
        ProductDetails::where('product_id', $id)->delete();
        Product::findOrFail($id)->delete();

        return redirect()
            ->route('products.index')
            ->with(['error' => 'تم حذف المنتج بنجاح المنتج بنجاج !!']);
    }

    public function manual_stock_adjust($id)
    {
        $product = Product::findOrFail($id);

        /*
            **المستودعاتُ تتبع نطاقَ الرؤية كما تتبعه أذونُ المخزن.**

            كانت القائمةُ `StoreHouse::...->get()` — كلَّ مستودعات المنشأة.
            فأمينُ مستودعٍ واحد يفتح شاشةَ التسوية فيجد أمامه مستودعاتٍ لا
            يملكها، وقد يُسوّي رصيداً فيها بلا أن يمنعه شيء.
        */
        $storehouses = $this->accessibleWarehouses(['name', 'id'])->get();
        $generalSettings = GeneralSettings::select()->first();
        $role = $generalSettings ? $generalSettings->enable_multi_units_system == 1 : false;

        $SubUnits = $product->sub_unit_id ? SubUnit::where('template_unit_id', $product->sub_unit_id)->get() : collect();

        // «الحساب الفرعي» صار حساباً من شجرة الحسابات لا نصّاً حرّاً
        $accounts = $this->accountTreeOptions();

        return view('stock::products.manual_stock_adjust', compact('product', 'role', 'storehouses', 'SubUnits', 'accounts'));
    }

    public function add_manual_stock_adjust(Request $request, $id)
    {
        // الفترة المحاسبية: التسوية اليدوية تُرحِّل قيداً بتاريخها
        if ($block = $this->accountingPeriodBlock($request->date ?? now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        /*
            كان التحقّق ناقصاً ومخالفاً للنموذج:
            • `quantity` بحدٍّ أدنى 1 بينما الحقل يقبل 0.01 — فالكسور تُرفض بلا سبب ظاهر.
            • `unit_price` بلا حدٍّ أدنى، فسعرٌ سالب يمرّ ويقلب القيد المحاسبي.
            • المرفق: النموذج يعرض doc/docx/gif وحتى 10 ميغابايت، والتحقّق يقبل
              jpeg/png/pdf وحتى 2 ميغابايت فقط.
            • `type_of_operation` و`status` و`duration` و`sub_unit_id` و`comments`
              بلا تحقّقٍ أصلاً، فتُكتب في القاعدة كما جاءت.
            • التاريخ والوقت اختياريان هنا وإلزاميّان في النموذج، ثم يُبنى منهما
              `permission_date` بـ`createFromFormat` فيسقط الطلب إن غابا.
        */
        $rules = [
            'type' => 'required|in:1,2', // 1 = إضافة ، 2 = سحب
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'store_house_id' => 'required|integer|exists:store_houses,id',
            'type_of_operation' => 'nullable|in:1,2,3',
            'status' => 'nullable|in:1,2,3,4',
            'duration' => 'nullable|integer|min:0|max:3650',
            'sub_unit_id' => 'nullable|integer|exists:sub_units,id',
            'subaccount' => 'nullable|integer|exists:accounts,id',
            'comments' => 'nullable|string|max:1000',
            'attachments' => 'nullable|file|mimes:jpeg,jpg,png,gif,pdf,doc,docx|max:10240',
        ];

        $messages = [
            'type.required' => 'اختر نوع الحركة: إضافة أو سحب.',
            'type.in' => 'نوع الحركة غير صحيح.',
            'quantity.required' => 'أدخل الكمية.',
            'quantity.numeric' => 'الكمية يجب أن تكون رقماً.',
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'unit_price.required' => 'أدخل سعر الوحدة.',
            'unit_price.numeric' => 'سعر الوحدة يجب أن يكون رقماً.',
            'unit_price.min' => 'سعر الوحدة لا يكون سالباً.',
            'date.required' => 'حدّد تاريخ الحركة.',
            'date.date' => 'تاريخ الحركة غير صحيح.',
            'time.required' => 'حدّد وقت الحركة.',
            'time.date_format' => 'صيغة الوقت يجب أن تكون ساعة:دقيقة.',
            'store_house_id.required' => 'اختر المستودع.',
            'store_house_id.exists' => 'المستودع المختار غير موجود.',
            'type_of_operation.in' => 'تصنيف العملية غير صحيح.',
            'status.in' => 'الحالة المختارة غير صحيحة.',
            'sub_unit_id.exists' => 'الوحدة المختارة غير موجودة.',
            'subaccount.integer' => 'الحساب الفرعي غير صحيح.',
            'subaccount.exists' => 'الحساب الفرعي المختار غير موجود في شجرة الحسابات.',
            'duration.integer' => 'المدة يجب أن تكون عدد أيام صحيحاً.',
            'duration.max' => 'المدة أطول مما هو مسموح.',
            'comments.max' => 'الملاحظات أطول من 1000 حرف.',
            'attachments.mimes' => 'صيغة المرفق غير مدعومة: PDF أو DOC أو صورة.',
            'attachments.max' => 'حجم المرفق يتجاوز 10 ميغابايت.',
        ];

        // الحساب الفرعي: حسابٌ فرعي نشط من شجرة الحسابات — لا حسابٌ رئيسي
        $validator = Validator::make($request->all(), $rules, $messages);

        /*
            **النطاقُ يُفرَض على الكتابة كذلك.**

            حصرُ القائمة المنسدلة إخفاءٌ لا منع: الحقلُ `store_house_id` يأتي
            من الطلب، ومن غيّر قيمته بأدوات المتصفّح سوّى رصيداً في مستودعٍ
            لا يملكه. فالفحصُ هنا على ما وصل فعلاً.
        */
        $validator->after(function ($validator) use ($request) {
            $warehouseId = $request->integer('store_house_id');
            $allowed = $this->accessibleWarehouseIds();

            if ($warehouseId && $allowed !== null && ! in_array($warehouseId, $allowed, true)) {
                $validator->errors()->add('store_house_id', 'لا تملك صلاحية الوصول إلى هذا المستودع.');
            }
        });

        $validator->after(function ($validator) use ($request) {
            if (! $request->filled('subaccount')) {
                return;
            }

            $account = Account::find($request->subaccount);

            if (! $account) {
                return; // قاعدة exists تتكفّل بالرسالة
            }

            if ($account->type !== 'sub') {
                $validator->errors()->add('subaccount', 'اختر حساباً فرعياً؛ لا يجوز الترحيل على حسابٍ رئيسي.');
            } elseif (! $account->is_active) {
                $validator->errors()->add('subaccount', 'الحساب الفرعي المختار غير نشط.');
            }
        });

        $validator->validate();

        try {
            DB::beginTransaction();

            $product = Product::findOrFail($id);

            // فحص حالة المنتج قبل التعديل اليدوي
            $statusCheck = $this->checkProductStatus($product, 'تعديل المخزون');
            if ($statusCheck['error']) {
                DB::rollBack();

                return redirect()->back()->with('error', $statusCheck['message']);
            }

            // جلب تفاصيل المنتج للمستودع المحدد
            $productDetails = ProductDetails::where('product_id', $id)
                ->where('store_house_id', $request->store_house_id)
                ->first();

            if ($request->type == 2) {
                // عملية سحب - يجب أن يكون المنتج موجود في المستودع
                if (! $productDetails) {
                    // كان الخروج بلا rollBack والمعاملة مفتوحة
                    DB::rollBack();

                    return redirect()
                        ->back()
                        ->withInput()
                        ->withErrors(['store_house_id' => 'المنتج غير موجود في المستودع المحدَّد.'])
                        ->with('error', 'المنتج غير موجود في المستودع المحدَّد.');
                }
            } else {
                // عملية إضافة - إنشاء سجل جديد للمستودع إذا لم يكن موجوداً
                if (! $productDetails) {
                    $productDetails = ProductDetails::create([
                        'product_id' => $id,
                        'store_house_id' => $request->store_house_id,
                        'quantity' => 0,
                    ]);
                }
            }

            // استخدام المتغير الصحيح
            $product = $productDetails;

            $old_quantity_in_stock = $product->quantity;

            // التحقق من توفر الكمية قبل السحب
            if ($request->type == 2 && $old_quantity_in_stock < $request->quantity) {
                DB::rollBack();

                $shortage = sprintf(
                    'الكمية غير متوفّرة: المتاح %s والمطلوب سحبه %s.',
                    rtrim(rtrim(number_format((float) $old_quantity_in_stock, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float) $request->quantity, 2, '.', ''), '0'), '.')
                );

                return redirect()
                    ->route('products.manual_stock_adjust', $id)
                    ->withInput()
                    ->withErrors(['quantity' => $shortage])
                    ->with('error', $shortage);
            }

            // تحديث المنتج + إنشاء إذن
            try {
                $this->updateProductAndCreatePermit($product, $request, $request->type, $id);
            } catch (\Exception $ex) {
                OperationLog::failure($ex, 'product_log', 'add_manual_stock_adjust');
                DB::rollback();

                return redirect()
                    ->back()
                    ->with(['error' => 'خطأ أثناء إنشاء إذن المخزن: '.$ex->getMessage()])
                    ->withInput();
            }

            // حساب المبلغ
            $amount = $request->quantity * $request->unit_price;

            // ✅ التأكد من حساب المستودع
            // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في
            // «الحسابات المحاسبية»، بنوعٍ وكودٍ موحَّدين
            $storeAccount = $this->warehouseAccount($request->store_house_id, optional(StoreHouse::find($request->store_house_id))->name);

            /*
                الطرف المقابل يتبع تصنيف العملية لا نوعها:
                  1 رصيد افتتاحي  → حساب الرصيد الافتتاحي
                  2 تسوية مخزونية → حساب تسوية الجرد (عجز وزيادة المخزون)
                  3 عملية يدوية   → أطراف دائنة أخرى
                كان القيد يُرحَّل على «أطراف دائنة أخرى» في الحالات الثلاث،
                فيظهر الرصيد الافتتاحي وفروق الجرد كأنّها ذممٌ على الشركة.
            */
            $operationClass = (int) ($request->type_of_operation ?: 3);

            [$counterKey, $counterFallback, $operationLabel] = match ($operationClass) {
                1 => ['opening_balance', SystemAccountIds::OPENING_BALANCE, 'رصيد افتتاحي'],
                2 => ['inventory_adjustment', SystemAccountIds::COST_OF_SALES, 'تسوية مخزونية'],
                default => ['other_creditor_parties', SystemAccountIds::OTHER_CREDITOR_PARTIES, 'حركة يدوية'],
            };

            $counterAccountId = $this->requireAccountingAccountId($counterKey, $counterFallback);

            $description = sprintf(
                '%s — %s %s من المنتج %s (%s)',
                $operationLabel,
                $request->type == 1 ? 'إضافة' : 'سحب',
                rtrim(rtrim(number_format((float) $request->quantity, 2, '.', ''), '0'), '.'),
                $product->product->name ?? '',
                optional(StoreHouse::find($request->store_house_id))->name ?? 'مستودع'
            );

            // إنشاء قيد اليومية
            $journalEntry = JournalEntry::create([
                'reference_number' => 'MANUAL_'.now()->timestamp,
                'warehouse_permit_id' => $request->warehouse_permit_id ?? null,
                'date' => $request->date ?? now(),
                'description' => $description,
                'status' => 1,
                'currency' => $this->defaultCurrency(),
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
                'linking_type' => LinkingType::SINGLE,
                'reference_id' => $id,
                'amount' => $amount,
            ]);

            // 1️⃣ تفصيل القيد - حساب المستودع
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $storeAccount->id,
                'description' => $journalEntry->description,
                'debit' => $request->type == 1 ? $amount : 0,
                'credit' => $request->type == 2 ? $amount : 0,
            ]);

            // 2️⃣ تفصيل القيد - الطرف المقابل حسب تصنيف العملية
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $counterAccountId,
                'description' => $journalEntry->description,
                'debit' => $request->type == 2 ? $amount : 0,
                'credit' => $request->type == 1 ? $amount : 0,
            ]);

            // الطرف المقابل يتحرّك مثل حساب المستودع وبعكس اتجاهه
            Account::updateBalancesHierarchically($counterAccountId);

            // تحديث أرصدة المستودع
            if ($request->type == 1) {
                $storeAccount->balance += $amount; // إضافة
            } else {
                $storeAccount->balance -= $amount; // سحب
            }
            $storeAccount->save();

            // تحديث أرصدة الحسابات بشكل هرمي لضمان التوازن المحاسبي
            Account::updateBalancesHierarchically($storeAccount->id);

            DB::commit();

            $message = $request->type == 2
                ? 'تم سحب الكميه من المخزون بنجاح'
                : 'تم اضافه الكميه الي المخزون بنجاح';

            return redirect()
                ->route('products.show', $id)
                ->withInput()
                ->with(['success' => $message]);

        } catch (\Exception $ex) {
            OperationLog::failure($ex, 'product_log', 'add_manual_stock_adjust');
            DB::rollback();

            return redirect()
                ->back()
                ->with(['error' => $ex->getMessage()])
                ->withInput();
        }
    }

    private function updateProductAndCreatePermit($product, $request, $type, $id)
    {
        // التحقق من المخزون قبل التحديث.
        // كانت الدالة «تُرجع» إعادة توجيه ومُناديها يهملها فيمضي في التحديث —
        // أي أنّ الحارس لم يكن يمنع شيئاً. صار استثناءً يلتقطه المُنادي.
        if ($type == 2 && $product->quantity < $request->quantity) {
            throw new \RuntimeException('الكمية غير متوفّرة في المستودع المحدَّد.');
        }

        // حفظ الكمية القديمة قبل التحديث
        $oldQuantity = $product->quantity;

        // تحديث الكمية فقط - بدون تغيير المستودع
        $product->quantity = $type == 2 ? $product->quantity - $request->quantity : $product->quantity + $request->quantity;

        // تحديث الحقول الإضافية إذا كانت مطلوبة
        if ($request->has('unit_price')) {
            $product->unit_price = $request->unit_price;
        }
        if ($request->has('date')) {
            $product->date = $request->date;
        }
        if ($request->has('time')) {
            $product->time = $request->time;
        }
        if ($request->has('type_of_operation')) {
            $product->type_of_operation = $request->type_of_operation;
        }
        if ($request->has('comments')) {
            $product->comments = $request->comments;
        }
        // الحساب الفرعي: معرّف حسابٍ من شجرة الحسابات، وفراغه يعني «بلا حساب»
        $product->subaccount = $request->filled('subaccount') ? (int) $request->subaccount : null;
        if ($request->hasFile('attachments')) {
            $product->attachments = $this->UploadImage('assets/uploads/product/attachments', $request->attachments);
        }

        $product->save();

        // تحديد نوع إذن المخزن (إضافة أو سحب) باستخدام الثوابت
        $permissionSourceId = $type == 2 ? PermissionSources::WAREHOUSE_WITHDRAWAL : PermissionSources::WAREHOUSE_ADD;

        $wareHousePermits = new WarehousePermits;
        $wareHousePermits->store_houses_id = $request->store_house_id;
        $wareHousePermits->permission_source_id = $permissionSourceId;
        // كان الإذن يُحفظ بلا حالة، و`status` عمودٌ إلزامي بلا قيمة افتراضية —
        // فكانت كل حركةٍ يدوية تفشل بخطأ SQL خام. والحركة اليدوية تُطبَّق فوراً
        // على المخزون، فحالتها «معتمدة».
        $wareHousePermits->status = 'approved';
        $wareHousePermits->permission_type = $type == 2 ? 2 : 1;
        // رقم الإذن يُستهلك من إعدادات «الترقيم المتسلسل»: الإذن يُحفَظ فعلاً،
        // والمعاينةُ لا تُقدّم العدّاد — فكانت كل حركةٍ تأخذ الرقم نفسه
        $serial_number = $this->issueDocumentNumber('warehouse-add');
        $wareHousePermits->number = $serial_number;
        $wareHousePermits->grand_total = $request->quantity * $request->unit_price;
        $wareHousePermits->created_by = auth()->user()->id;
        $wareHousePermits->sub_account = $request->filled('subaccount') ? (int) $request->subaccount : null;
        $wareHousePermits->details = $request->comments;
        $wareHousePermits->permission_date = $request->has('date') && $request->has('time') ? Carbon::createFromFormat('Y-m-d H:i', $request->date.' '.$request->time) : Carbon::now();
        $wareHousePermits->save();

        // حفظ تفاصيل المنتج في إذن المخزن
        $warehousePermitProduct = WarehousePermitsProducts::create([
            'quantity' => $request->quantity,
            'total' => $request->quantity * $request->unit_price,
            'unit_price' => $request->unit_price,
            'product_id' => $id,
            'warehouse_permits_id' => $wareHousePermits->id,
            'stock_before' => $oldQuantity, // المخزون قبل التحديث
            'stock_after' => $product->quantity, // المخزون بعد التحديث
        ]);

        // إذا كان المنتج تجميعيًا
        if ($product->type == 'compiled' && $product->compile_type !== 'Instant') {
            // ** الحصول على المنتجات التابعة للمنتج التجميعي **
            $CompiledProducts = CompiledProducts::where('compile_id', $id)->get();

            foreach ($CompiledProducts as $compiledProduct) {
                // ** حساب المخزون قبل وبعد التعديل للمنتج التابع **
                // يجب حساب الرصيد من المستودع المحدد فقط
                $compiledProductDetails = ProductDetails::where('store_house_id', $request->store_house_id)
                    ->where('product_id', $compiledProduct->product_id)
                    ->first();

                if (! $compiledProductDetails) {
                    $compiledProductDetails = ProductDetails::create([
                        'store_house_id' => $request->store_house_id,
                        'product_id' => $compiledProduct->product_id,
                        'quantity' => 0,
                    ]);
                }

                $stock_before = $compiledProductDetails->quantity;
                $stock_after = $stock_before - $compiledProduct->qyt * $request->quantity; // خصم الكمية المطلوبة

                // ** تسجيل المبيعات في حركة المخزون للمنتج التابع **
                $wareHousePermits = new WarehousePermits;
                $wareHousePermits->permission_source_id = $permissionSourceId; // استخدام نفس نوع الإذن (سحب أو إضافة)
                $wareHousePermits->permission_date = $request->has('date') && $request->has('time') ? Carbon::createFromFormat('Y-m-d H:i', $request->date.' '.$request->time) : Carbon::now();
                $wareHousePermits->number = $product->id;
                $wareHousePermits->grand_total = $request->quantity * $request->unit_price;
                $wareHousePermits->store_houses_id = $request->store_house_id;
                $wareHousePermits->created_by = auth()->user()->id;
                $wareHousePermits->save();

                // ** تسجيل البيانات في WarehousePermitsProducts للمنتج التابع **
                WarehousePermitsProducts::create([
                    'quantity' => $compiledProduct->qyt * $request->quantity,
                    'total' => $request->quantity * $request->unit_price,
                    'unit_price' => $request->unit_price,
                    'product_id' => $compiledProduct->product_id,
                    'stock_before' => $stock_before, // المخزون قبل التحديث
                    'stock_after' => $stock_after, // المخزون بعد التحديث
                    'warehouse_permits_id' => $wareHousePermits->id,
                ]);

                // ** تحديث المخزون للمنتج التابع **
                $compiledProductDetails = ProductDetails::where('store_house_id', $request->store_house_id)->where('product_id', $compiledProduct->product_id)->first();

                if (! $compiledProductDetails) {
                    $compiledProductDetails = ProductDetails::create([
                        'store_house_id' => $request->store_house_id,
                        'product_id' => $compiledProduct->product_id,
                        'quantity' => 0,
                    ]);
                }

                $compiledProductDetails->decrement('quantity', $compiledProduct->qyt * $request->quantity);
            }
        }

        // تسجيل الإشعار في جدول logs
        $product = Product::findOrFail($id);
        $movement = $warehousePermitProduct; // استخدام البيانات المسجلة حديثًا
        $average_cost = $product->averageCost();

        $description = '';

        if ($type == 2) {
            $description = sprintf('أنقص **%s** **%d** وحدة من مخزون **[#%s (%s)](%s)** يدويا (رقم العملية: **#%s**)، وسعر الوحدة: **%s ر.س**، وأصبح المخزون الباقي من المنتج: **%d** وأصبح المخزون **%s** رصيده **%d**, متوسط السعر: **%s ر.س**', $movement->warehousePermits->user->name, $movement->quantity, $product->serial_number, $product->name, route('products.show', $id), $movement->warehousePermits->number, $movement->unit_price, $movement->stock_after, $movement->warehousePermits->storeHouse->name, $movement->stock_after, $average_cost);
        } else {
            $description = sprintf('أضاف **%s** **%d** وحدة إلى مخزون **[#%s (%s)](%s)** يدويا (رقم العملية: **#%s**)، وسعر الوحدة: **%s ر.س**، وأصبح المخزون الباقي من المنتج: **%d** وأصبح المخزون **%s** رصيده **%d**, متوسط السعر: **%s ر.س**', $movement->warehousePermits->user->name, $movement->quantity, $product->serial_number, $product->name, route('products.show', $id), $movement->warehousePermits->number, $movement->unit_price, $product->quantity, $movement->warehousePermits->storeHouse->name, $product->quantity, $average_cost);
        }

        OperationLog::record('logged', 'product_log', $id, $description);
    }

    // Helper Function
    public function GenerateImage($image, $imageName)
    {
        $destinationsPath = public_path('assets/uploads');
        $this->resizeImageGD($image->path(), $destinationsPath.'/'.$imageName, 124, 124);
    }

    public function uploadImage($folder, $image)
    {
        $fileExtension = $image->getClientOriginalExtension();
        $fileName = time().rand(1, 99).'.'.$fileExtension;
        $image->move($folder, $fileName);

        return $fileName;
    }

    // end of uploadImage
    public function GenerateProductImage($image, $imageName)
    {
        $destinationsPath = public_path('assets/uploads/product');
        $this->resizeImageGD($image->path(), $destinationsPath.'/'.$imageName, 540, 689);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv,txt',
        ]);

        /*
            حدّ باقة فوترة على **عدد أسطر الملفّ**، لا على واحد.

            هذا المسار الوحيد في الوحدة الذي لا يصلح له وسيط `plan.limit`:
            الكمّية لا تُعرف قبل قراءة الملفّ. و[\App\Observers\PlanLimitObserver]
            يمنع تجاوز السقف على كل حال، لكنه يرمي عند السطر الذي يتجاوزه —
            و`Excel::import` لا يلفّ الاستيراد بمعاملة، فيبقى ما سبقه محفوظاً
            ويُترك المستخدم أمام استيرادٍ نصفُه تمّ ونصفُه لا، بلا معرفة أين
            توقّف.

            فالعدّ هنا يجعلها إمّا كلّها أو لا شيء، والرسالة تقول كم يتّسع
            الحدّ فعلاً.

            وقراءة الملفّ مرّتين مقبولة: الاستيراد عمليةٌ نادرة وثقيلة أصلاً،
            والبديل استيرادٌ ممزَّق.
        */
        $gate = app(SubscriptionGate::class);

        if (! $gate->liveLimit(StockPlanFeatures::LIMIT_PRODUCTS)['unlimited']) {
            try {
                $sheets = Excel::toArray(new ProductsImport, $request->file('file'));
                // صفّ العناوين تحسمه `WithHeadingRow` قبل أن يصل إلينا،
                // فما يُعدّ هنا أسطرُ البيانات وحدها
                $rows = collect($sheets)->flatten(1)->count();
            } catch (\Throwable $e) {
                // ملفٌّ تعذّرت قراءته لا يُستورد أصلاً — يُترك للاستيراد
                // نفسه ليرفع خطأه المفصَّل بدل رسالةٍ عامّة من هنا
                $rows = 1;
            }

            $quota = $gate->checkLive(StockPlanFeatures::LIMIT_PRODUCTS, max(1, $rows));

            if (! $quota['allowed']) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $quota['reason'],
                        'code' => 'PLAN_LIMIT_REACHED',
                    ], 402);
                }

                return redirect()->back()->with('error', $quota['reason']);
            }
        }

        Excel::import(new ProductsImport, $request->file('file'));

        // إذا كان الطلب AJAX، إرجاع استجابة JSON
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم استيراد المنتجات بنجاح!',
            ]);
        }

        return redirect()->back()->with('success', 'تم استيراد المنتجات بنجاح!');
    }
    // في ProductsController.php

    /**
     * جلب الباركود كـ JSON
     */
    public function getBarcode(Request $request, $id)
    {

        try {
            $product = Product::findOrFail($id);

            if (empty($product->barcode)) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'المنتج لا يملك باركود',
                    ], 404);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'المنتج لا يملك باركود',
                ], 404);
            }

            // جلب إعدادات الباركود
            $settings = ModelsProductBarcodeSettings::getCurrent();

            // إنشاء الباركود
            $generator = new BarcodeGeneratorPNG;
            $barcodeImage = $generator->getBarcode(
                $product->barcode,
                BarcodeGenerator::TYPE_CODE_128,
                2,
                50
            );

            // تحويل الصورة إلى base64
            $base64 = base64_encode($barcodeImage);
            $imageSrc = 'data:image/png;base64,'.$base64;

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'barcode_image' => $imageSrc,
                    'barcode_number' => $product->barcode,
                    'product_name' => $product->name,
                    'serial_number' => $product->serial_number,
                    'settings' => $settings,
                ]);
            }

            return view('stock::products.partials.barcode', compact('product', 'imageSrc'));

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Products', 'getBarcode');

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تنزيل صورة الباركود.
     *
     * الطريق `products.download_barcode` كان يشير إلى دالة `downloadBarcode`
     * غير موجودة في المتحكّم أصلاً، فيسقط الرابط بخطأ.
     */
    public function downloadBarcode($id)
    {
        try {
            $product = Product::findOrFail($id);

            if (empty($product->barcode)) {
                return redirect()
                    ->route('products.show', $product->id)
                    ->with('error', 'لا يوجد باركود لهذا المنتج.');
            }

            $settings = ModelsProductBarcodeSettings::getCurrent();

            $image = (new BarcodeGeneratorPNG)->getBarcode(
                $product->barcode,
                BarcodeGenerator::TYPE_CODE_128,
                (int) ($settings->bar_width ?? 2),
                (int) ($settings->bar_height ?? 50)
            );

            // اسم الملف: رقم المنتج التسلسلي أو الباركود — بلا محارف تكسر الترويسة
            $name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $product->serial_number ?: $product->barcode);

            return response($image, 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="barcode-'.$name.'.png"',
                'Content-Length' => strlen($image),
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'product_log', 'downloadBarcode');

            return redirect()
                ->route('products.show', $id)
                ->with('error', 'تعذّر تنزيل الباركود: '.$e->getMessage());
        }
    }

    /**
     * عرض صفحة طباعة الباركود
     */
    public function printBarcode($id)
    {
        try {
            $product = Product::findOrFail($id);

            // جلب إعدادات الباركود
            $settings = ModelsProductBarcodeSettings::getCurrent();

            return view('stock::products.partials.print_barcode', compact('product', 'settings'));

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Products', 'printBarcode');

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * طباعة الباركود بصيغة PDF
     */
    public function printBarcodePDF($id)
    {
        try {
            $product = Product::findOrFail($id);

            if (empty($product->barcode)) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنتج لا يملك باركود',
                ], 404);
            }

            // جلب إعدادات الباركود
            $settings = ModelsProductBarcodeSettings::getCurrent();

            // إعداد PDF
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Fawtra System');
            $pdf->SetAuthor('Fawtra');
            $pdf->SetTitle('طباعة الباركود');
            $pdf->setRTL(true);
            $pdf->SetMargins(0, 0, 0);
            $pdf->AddPage();

            /*
                **الباركود كان يخرج مقطوعاً — جزء داخل الصفحة وجزء خارجها.**

                سببان اجتمعا:

                  • `setRTL(true)` يعكس إحداثية X في `Image()`، والحساب اليدوي
                    للمنتصف كان يُكتب على افتراض اتجاهٍ واحد — فتنزلق الصورة
                    نحو الحافة ويُبتر طرفها.
                  • أبعاد ثابتة 80×40 تقسر صورة PNG مهما كانت نسبتها، وتتجاهل
                    «إعدادات الباركود» (عرض الخط وارتفاعه) التي تحترمها
                    دالة التنزيل PNG المجاورة.

                فصار الرسم بدالة TCPDF الأصلية `write1DBarcode`: خطوطٌ
                متجهية حادة لا صورة نقطية، والتمركز بـ`position => C` من
                المكتبة نفسها فلا يتأثر بجهة الصفحة، والمقاسات من إعدادات
                شاشة الباركود — ولا ملف مؤقت أصلاً.
            */
            $pageWidth = $pdf->GetPageWidth();
            $pageHeight = $pdf->GetPageHeight();

            // عرض المنطقة المرسومة: بهامش 15مم من كل جهة، وارتفاعها من الإعدادات
            // (البكسل ≈ 0.26مم عند 96dpi — نفس نسب دالة التنزيل PNG)
            $barcodeWidth = $pageWidth - 30;
            $barcodeHeight = max(15, (int) ($settings->bar_height ?? 50) * 0.26);

            $yPosition = ($pageHeight - $barcodeHeight) / 2;

            $style = [
                'position' => 'C',          // التمركز من المكتبة — صحيح في الاتجاهين
                'align' => 'C',
                'stretch' => false,
                'fitwidth' => true,         // يتقلّص ليدخل العرض المتاح فلا يُقطع أبداً
                'border' => false,
                'padding' => 0,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'text' => false,            // الرقم يُكتب أسفله بخطنا العربي
            ];

            $pdf->write1DBarcode($product->barcode, 'C128', 15, $yPosition, $barcodeWidth, $barcodeHeight, 0.4, $style, 'N');

            // كتابة الرقم أسفل الباركود
            $pdf->SetY($yPosition + $barcodeHeight + 5);
            $pdf->SetFont('aealarabiya', '', 12);
            $pdf->Cell(0, 10, 'الباركود: '.$product->barcode, 0, 1, 'C');

            // إخراج الملف PDF
            return $pdf->Output('barcode_'.$product->barcode.'.pdf', 'I');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Products', 'printBarcodePDF');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * طباعة عدة باركودات
     */
    public function printMultipleBarcodes(Request $request)
    {
        try {
            $request->validate([
                'product_ids' => 'required|array',
                'product_ids.*' => 'exists:products,id',
            ]);

            // التأكد من وجود مجلد التخزين المؤقت
            $tempDir = storage_path('temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            $pdf->SetCreator('Fawtra System');
            $pdf->SetAuthor('Fawtra');
            $pdf->SetTitle('طباعة الباركودات');
            $pdf->SetMargins(10, 10, 10);
            $pdf->setRTL(true);

            $products = Product::whereIn('id', $request->product_ids)->get();
            $count = 0;

            foreach ($products as $product) {
                if (empty($product->barcode)) {
                    continue;
                }

                if ($count % 4 == 0) {
                    $pdf->AddPage();
                }

                $y = 30 + (($count % 4) * 60);

                // عنوان
                $pdf->SetY($y);
                $pdf->SetFont('aealarabiya', 'B', 10);
                $pdf->Cell(0, 5, $product->name, 0, 1, 'R');

                // إنشاء الباركود
                $generator = new BarcodeGeneratorPNG;
                $barcode = $generator->getBarcode(
                    $product->barcode,
                    BarcodeGenerator::TYPE_CODE_128,
                    2,
                    40
                );

                // حفظ مؤقت
                $tempPath = storage_path('temp/barcode_'.time().'_'.$count.'.png');
                file_put_contents($tempPath, $barcode);

                // إضافة الصورة
                $pdf->Image($tempPath, 50, $y + 5, 100, 35);

                // رقم الباركود
                $pdf->SetY($y + 40);
                $pdf->SetFont('aealarabiya', '', 9);
                $pdf->Cell(0, 5, $product->barcode, 0, 1, 'C');

                // حذف الملف المؤقت
                @unlink($tempPath);

                $count++;
            }

            return $pdf->Output('barcodes_'.now()->format('Y-m-d_H-i-s').'.pdf', 'D');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Products', 'printMultipleBarcodes');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * دالة مساعدة لتحجيم الصور باستخدام GD Library
     */
    private function resizeImageGD($sourcePath, $destinationPath, $targetWidth, $targetHeight)
    {
        try {
            // الحصول على معلومات الصورة
            $imageInfo = getimagesize($sourcePath);
            if (! $imageInfo) {
                return false;
            }

            $mime = $imageInfo['mime'];

            // إنشاء الصورة الأصلية
            switch ($mime) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    $sourceImage = imagecreatefromjpeg($sourcePath);
            }

            // الحصول على أبعاد الصورة الأصلية
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);

            // حساب النسبة المئوية للتحجيم
            $ratio = min($targetWidth / $originalWidth, $targetHeight / $originalHeight);
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);

            // إنشاء صورة جديدة للنتيجة
            $destinationImage = imagecreatetruecolor($targetWidth, $targetHeight);

            // ملء الخلفية باللون الأبيض (لتجنب الخلفية السوداء)
            $white = imagecolorallocate($destinationImage, 255, 255, 255);
            imagefill($destinationImage, 0, 0, $white);

            // حساب موضع الصورة المحجمة لتكون في المركز
            $x = intval(($targetWidth - $newWidth) / 2);
            $y = intval(($targetHeight - $newHeight) / 2);

            // تحجيم ونسخ الصورة
            imagecopyresampled(
                $destinationImage,
                $sourceImage,
                $x, $y, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );

            // حفظ الصورة
            imagejpeg($destinationImage, $destinationPath, 80);

            // تحرير الذاكرة
            imagedestroy($sourceImage);
            imagedestroy($destinationImage);

            return true;
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Products', 'resizeImageGD');

            return false;
        }
    }
}
