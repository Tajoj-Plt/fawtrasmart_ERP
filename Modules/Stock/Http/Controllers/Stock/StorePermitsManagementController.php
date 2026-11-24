<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Constants\permissions\PermissionSources;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\ProductType;
use App\Enums\ReferenceType;
use App\Exports\StorePermitsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\WarehousePermitsRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\InventoryAdjustment;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PermissionSource;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\PurchaseInvoice;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\AccountingPeriodFilter;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use App\Support\StockEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

class StorePermitsManagementController extends Controller
{
    use GeneratesDocumentNumbers, ResolvesAccountingAccounts, RespectsAccountingPeriods, ScopesToEmployeeHierarchy, UsesDocumentCurrency;

    /**
     * صلاحية إنشاء كل نوع إذن — تُفحص داخل `store()`/`update()` وفق
     * `permission_type` الفعلي في الطلب، إذ يخدم إجراءٌ واحدٌ أربعة أنواع
     * ولا تميّز بينها الميدل وير على مستوى الراوت.
     */
    private const CREATE_PERMISSION_BY_TYPE = [
        1 => 'stock.permits.create_addition',
        2 => 'stock.permits.create_disbursement',
        3 => 'stock.permits.create_transfer',
        4 => 'stock.permits.create_opening',
    ];

    public function __construct()
    {
        $this->middleware('permission.role:stock.permits.view', ['only' => ['index', 'pendingPermits', 'approvedPermits', 'rejectedPermits']]);
        $this->middleware('permission.role:stock.permits.show', ['only' => ['show']]);
        $this->middleware('permission.role:stock.permits.create_addition', ['only' => ['create']]);
        $this->middleware('permission.role:stock.permits.create_disbursement', ['only' => ['manual_disbursement']]);
        $this->middleware('permission.role:stock.permits.create_transfer', ['only' => ['manual_conversion']]);
        $this->middleware('permission.role:stock.permits.create_opening', ['only' => ['opening_balance']]);
        $this->middleware('permission.role:stock.permits.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.permits.delete', ['only' => ['delete']]);
        $this->middleware('permission.role:stock.permits.approve', ['only' => ['approve']]);
        $this->middleware('permission.role:stock.permits.reject', ['only' => ['reject']]);
        $this->middleware('permission.role:stock.permits.cancel_approval', ['only' => ['cancelApproval']]);
    }

    /**
     * فحص صلاحية إنشاء/تعديل النوع الفعلي — `store`/`update` يخدمان أربعة
     * أنواع أذون بصلاحياتٍ منفصلة، فلا يكفي فحص الميدل وير الموحّد.
     */
    private function authorizeCreatePermissionType($permissionType): void
    {
        $permission = self::CREATE_PERMISSION_BY_TYPE[(int) $permissionType] ?? null;

        if (! $permission) {
            abort(422, 'نوع إذنٍ غير معروف.');
        }

        if (! $this->scopeUser()->hasRolePermission($permission)) {
            abort(403, 'لا تملك صلاحية إنشاء هذا النوع من الأذون المخزنية.');
        }
    }

    /**
     * فحص ما إذا كانت جميع المنتجات نشطة
     */
    private function checkProductsStatus($productIds)
    {
        $inactiveProducts = Product::whereIn('id', $productIds)
            ->where('status', '!=', 'active')
            ->select('id', 'name', 'status')
            ->get();

        if ($inactiveProducts->isNotEmpty()) {
            $productNames = $inactiveProducts->pluck('name')->implode(', ');

            return [
                'valid' => false,
                'message' => "لا يمكن إجراء العملية على المنتجات التالية لأنها غير نشطة: {$productNames}",
            ];
        }

        return ['valid' => true];
    }

    /**
     * استعلام قائمة الأذون بنطاقها وفلاترها — مصدرٌ واحد لشاشة الويب
     * وواجهة الجوال، فلا يفترق ما يراه المستخدم هنا عمّا يراه هناك.
     */
    public function permitsListQuery(Request $request)
    {
        $query = WarehousePermits::with(['user.branch', 'fromStoreHouse', 'toStoreHouse', 'storeHouse'])->orderBy('id', 'DESC');

        // نطاق البيانات: منشئو الإذن هو نفسه أو من يديرهم إدارياً (default_warehouses/direct_manager_id)
        $this->scopeToCreator($query);

        /*
            وسياقُ الفرع: الإذن يُنسب إلى فرعه عبر مستودعه.

            كانت القائمة تُقيَّد بمنشئ الإذن وحده، فتبديلُ الفرع في الشريط
            العلوي لا يغيّر فيها حرفاً: يقف المستخدم على فرعٍ ويرى أذون
            فرعٍ آخر بينها ولا شيء يميّزها. والتحويل يُدرَج إن كان أحد
            طرفيه — مصدرُه أو هدفُه — في نطاقي.
        */
        $this->scopeToWarehouseColumns($query);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->permitsListQuery($request);

        $perPage = $request->input('per_page', 10);
        $wareHousePermits = $query->paginate($perPage);

        if ($request->ajax()) {
            return view('stock::store_permits_management.partials.table', compact('wareHousePermits'))->render();
        }

        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        /*
            `when()` تختبر الصدقَ لا الوجود، والمصفوفةُ الفارغة كاذبة: فموظفٌ
            **لا مستودعَ له** لم يكن يُطبَّق عليه الشرطُ أصلاً فيرى المستودعات
            كلَّها — عكسُ المقصود. والفرقُ بين «بلا قيد» و«لا شيء» محسومٌ الآن
            في `accessibleWarehouses()`.
        */
        $storeHouses = $this->accessibleWarehouses(['id', 'name'])->get();
        $currencies = $this->activeCurrencies();
        $selectedCurrency = $this->filterCurrency($request);
        $branches = Branch::where('status', 0)->select('id', 'name')->get();
        $clients = Client::all();
        $permissionSources = PermissionSource::all();
        $suppliers = Supplier::all();
        // قائمة «أنشأه» في الفلاتر تُحصَر بنطاقي أيضاً — لا فائدة من عرض زملاء لا أراهم أصلاً
        $users = User::whereNotNull('employee_id')
            ->when($this->accessibleUserIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
            ->get();
        // الأصناف المخزنية وحدها — الخدمات والرقميّات لا رصيد لها
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        $period = $this->stockPeriod($request);

        return view('stock::store_permits_management.index', compact('wareHousePermits', 'permissionSources', 'storeHouses', 'branches', 'clients', 'suppliers', 'users', 'products', 'currencies', 'selectedCurrency', 'period'));
    }

    public function create()
    {
        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $warehouseIds = $this->accessibleWarehouseIds();
        $storeHouses = StoreHouse::where('status', 'active')
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))
            ->select('id', 'name')->get();
        /*
            الأصناف المخزنية وحدها.

            كانت القائمة `Product::select()->get()` — كلَّ ما في النظام،
            والخدمات والمنتجات الرقمية بينها. فيختار المستخدم خدمةً ويكتب
            لها كمّية ويحفظ، فتظهر «ساعة استشارة» في رصيد المستودع وفي
            ورقة الجرد وقيمة المخزون.

            المنعُ هنا في الشاشة، وحارسُ `StockEligibility` على الموديل —
            فلا يمرّ سطرٌ من طريقٍ آخر.
        */
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('warehouse-add');

        return view('stock::store_permits_management.create', compact('storeHouses', 'products', 'serial_number'));
    }

    public function manual_disbursement()
    {
        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $storeHouses = $this->accessibleWarehouses(['id', 'name'])->get();
        /*
            الأصناف المخزنية وحدها.

            كانت القائمة `Product::select()->get()` — كلَّ ما في النظام،
            والخدمات والمنتجات الرقمية بينها. فيختار المستخدم خدمةً ويكتب
            لها كمّية ويحفظ، فتظهر «ساعة استشارة» في رصيد المستودع وفي
            ورقة الجرد وقيمة المخزون.

            المنعُ هنا في الشاشة، وحارسُ `StockEligibility` على الموديل —
            فلا يمرّ سطرٌ من طريقٍ آخر.
        */
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('warehouse-dispose');

        return view('stock::store_permits_management.manual_disbursement', compact('storeHouses', 'products', 'serial_number'));
    }

    public function manual_conversion()
    {
        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $storeHouses = $this->accessibleWarehouses(['id', 'name'])->get();
        /*
            الأصناف المخزنية وحدها.

            كانت القائمة `Product::select()->get()` — كلَّ ما في النظام،
            والخدمات والمنتجات الرقمية بينها. فيختار المستخدم خدمةً ويكتب
            لها كمّية ويحفظ، فتظهر «ساعة استشارة» في رصيد المستودع وفي
            ورقة الجرد وقيمة المخزون.

            المنعُ هنا في الشاشة، وحارسُ `StockEligibility` على الموديل —
            فلا يمرّ سطرٌ من طريقٍ آخر.
        */
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber('transfer-request');

        return view('stock::store_permits_management.manual_conversion', compact('storeHouses', 'products', 'serial_number'));
    }

    public function opening_balance()
    {
        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $warehouseIds = $this->accessibleWarehouseIds();
        $storeHouses = StoreHouse::where('status', 'active')
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))
            ->select('id', 'name')->get();
        /*
            الأصناف المخزنية وحدها.

            كانت القائمة `Product::select()->get()` — كلَّ ما في النظام،
            والخدمات والمنتجات الرقمية بينها. فيختار المستخدم خدمةً ويكتب
            لها كمّية ويحفظ، فتظهر «ساعة استشارة» في رصيد المستودع وفي
            ورقة الجرد وقيمة المخزون.

            المنعُ هنا في الشاشة، وحارسُ `StockEligibility` على الموديل —
            فلا يمرّ سطرٌ من طريقٍ آخر.
        */
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        // الرصيد الافتتاحي إذنٌ وارد، فيشترك مع «إذن إضافة مخزون» في تسلسله
        $serial_number = $this->previewDocumentNumber('warehouse-add');

        return view('stock::store_permits_management.opening_balance', compact('storeHouses', 'products', 'serial_number'));
    }

    public function show($id)
    {
        $permit = WarehousePermits::with(['items.product.defaultUnit.categoryUnit', 'storeHouse', 'branch', 'user'])->findOrFail($id);
        $this->authorizeCreatorScope($permit->created_by);

        // Get activity logs for this permit
        $logs = ModelsLog::where('type', 'warehouse_permit_log')
            ->where('type_id', $permit->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            });

        return view('stock::store_permits_management.show', compact('permit', 'logs'));
    }

    // في WarehousePermitsController - قم بتحديث دالة store

    public function store(WarehousePermitsRequest $request)
    {
        $this->authorizeCreatePermissionType($request->permission_type);

        // نطاق البيانات: لا يُنشأ إذنٌ لمستودعٍ خارج مستودعاتي (أو مستودعات من أديرهم)
        $this->assertWarehouseAccess($request->store_houses_id ? (int) $request->store_houses_id : null, 'store_houses_id');
        $this->assertWarehouseAccess($request->from_store_houses_id ? (int) $request->from_store_houses_id : null, 'from_store_houses_id');
        $this->assertWarehouseAccess($request->to_store_houses_id ? (int) $request->to_store_houses_id : null, 'to_store_houses_id');

        // ✅ فحص حالة المنتجات أولاً
        $productIds = $request->input('product_id', []);
        $statusCheck = $this->checkProductsStatus($productIds);

        if (! $statusCheck['valid']) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', $statusCheck['message']);
        }

        /*
            صنفٌ بلا رصيد لا يدخل إذناً مخزنياً.

            لم يكن شيء يمنع اختيار خدمةٍ في الإذن وكتابة كمّية لها — فتظهر
            في رصيد المستودع وورقة الجرد وقيمة المخزون بأرقامٍ لا معنى لها.

            الفحص هنا **قبل فتح المعاملة**: لو تُرك للمراقب على الموديل
            لانفجر بعد حفظ ترويسة الإذن، فيُرَدّ المستخدم باستثناءٍ خام بدل
            رسالةٍ تسمّي له الصنف ونوعه.
        */
        if ($blockedId = StockEligibility::firstStockless((array) $productIds)) {
            return redirect()->back()
                ->withInput()
                ->with('error', StockEligibility::message($blockedId));
        }

        // الفترة المحاسبية: لا يُفتح مستند بتاريخ داخل فترة مقفلة
        if ($block = $this->accountingPeriodBlock($request->permission_date)) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        DB::beginTransaction();
        try {
            $wareHousePermits = new WarehousePermits;

            if ($request->hasFile('attachments')) {
                $wareHousePermits->attachments = $this->UploadImage('assets/uploads/warehouse', $request->attachments);
            }

            if ($request->permission_type == 3) {
                $wareHousePermits->store_houses_id = $request->from_store_houses_id;
            } elseif ($request->permission_type == 2) {
                // شاشة الصرف صارت تسأل عن مستودعٍ واحد؛ ويبقى `from` مقبولاً
                $wareHousePermits->store_houses_id = $request->store_houses_id ?: $request->from_store_houses_id;
            } else {
                $wareHousePermits->store_houses_id = $request->store_houses_id;
            }

            $wareHousePermits->permission_type = $request->permission_type;
            $wareHousePermits->permission_date = $request->permission_date;
            $wareHousePermits->sub_account = $request->sub_account;
            /*
                رقم الإذن حقلٌ تلقائي: يُستهلك عند الحفظ لا عند العرض، ومن
                إعدادات «الترقيم المتسلسل» وحدها. وكان ما يرسله النموذج
                يُقبل إن كان شاغراً — والحقل مفتوحٌ في الشاشة، فيكتب المستخدم
                ما شاء ويتخطّى التسلسل أو يعيد استعمال رقم إذنٍ محذوف؛ والرقم
                هو ما يربط الإذن بقيده المحاسبي وبسجلّ العمليات وبالمطبوعات.
                قسم التسلسل يتبع نوع الإذن: وارد (إضافة/رصيد افتتاحي) أو
                منصرف أو تحويل.
            */
            $numberSection = match ((int) $request->permission_type) {
                2 => 'warehouse-dispose',
                3 => 'transfer-request',
                default => 'warehouse-add',
            };
            $wareHousePermits->number = $this->issueDocumentNumber($numberSection);
            $wareHousePermits->details = $request->details;
            // Calculate grand total as sum of all item totals
            $wareHousePermits->grand_total = array_sum($request->total ?? []);
            /*
                مستودعا «من» و«إلى» للتحويل وحده. الصرف يُخرج البضاعة من
                المستودع ولا وجهة له، والإضافة تُدخلها إليه — فتخزين مستودعٍ
                آخر معهما يُضلّل التقارير ويوحي بنقلٍ لم يحدث.
            */
            if ($request->permission_type == 3) {
                $wareHousePermits->from_store_houses_id = $request->from_store_houses_id;
                $wareHousePermits->to_store_houses_id = $request->to_store_houses_id;
            } else {
                $wareHousePermits->from_store_houses_id = null;
                $wareHousePermits->to_store_houses_id = null;
            }
            $wareHousePermits->created_by = auth()->user()->id;

            // تحديد مصدر الأذن بناءً على نوع الإذن
            if ($request->permission_type == 1) {
                $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_ADD;
            } elseif ($request->permission_type == 2) {
                $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_DISBURSEMENT;
            } elseif ($request->permission_type == 3) {
                $wareHousePermits->permission_source_id = PermissionSources::MANUAL_TRANSFER;
            } elseif ($request->permission_type == 4) {
                $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_OPENING_BALANCE;
            }

            /*
                حارسٌ ثانٍ: إذنٌ بلا بنود لا يُحفظ.
                (`foreach` على مصفوفٍ غائب كان يُطلق تحذيراً ثم يمرّ بهدوء
                فيُحفظ مستندٌ فارغ.)
            */
            $quantities = $request->input('quantity', []);

            if (! is_array($quantities) || $quantities === []) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->withErrors(['product_id' => 'أضف صنفاً واحداً على الأقل إلى الإذن.'])
                    ->with('error', 'أضف صنفاً واحداً على الأقل إلى الإذن.');
            }

            $wareHousePermits->save();

            $products = new WarehousePermitsProducts;

            foreach ($quantities as $index => $quantity) {
                /*
                    التحويل وحده هو ما يُنشئ بندين (خصمٌ من مصدر وإضافةٌ لهدف).
                    كان «الصرف» يمرّ من هنا أيضاً، فيُنشئ لكل صنفٍ بندين:
                    واحدٌ بكمية سالبة وآخر موجب. وعند الاعتماد يُخصم البندان
                    فيلغي أحدهما الآخر — أي أنّ إذن الصرف لم يكن يُنقص المخزون
                    إطلاقاً، ولا يجد قيداً يُرحَّل. الصرف بندٌ واحد موجب.
                */
                if ($request->permission_type == 3) {
                    /*
                        بندٌ واحد لكل صنف بكميةٍ موجبة.

                        كان يُنشأ بندان: واحدٌ بكمية سالبة للمصدر وآخر موجب
                        للهدف. و`approve()` يمرّ على البنود وينفّذ لكلٍّ منها
                        تحويلاً كاملاً (خصمٌ من المصدر وإضافةٌ للهدف) — فالبند
                        السالب يعكس الموجب ولا يتحرّك شيء، ولا يُرحَّل قيد.
                        و`cancelApproval()` يمرّ عليها مرّةً واحدة لكلٍّ أيضاً.
                    */
                    $stockBeforeFrom = ProductDetails::where('store_house_id', $request->from_store_houses_id)
                        ->where('product_id', $request['product_id'][$index])
                        ->sum('quantity');

                    if ($stockBeforeFrom < $quantity) {
                        DB::rollBack();

                        return back()
                            ->withInput()
                            ->with('error', 'الكمية غير متوفّرة في المستودع المصدر: المتاح '.$stockBeforeFrom.' والمطلوب '.$quantity.'.');
                    }

                    $warehousePermitProduct = $products->create([
                        'quantity' => $quantity,
                        'total' => $request['total'][$index],
                        'unit_price' => $request['unit_price'][$index],
                        'product_id' => $request['product_id'][$index],
                        'stock_before' => $stockBeforeFrom,
                        'stock_after' => $stockBeforeFrom - $quantity,
                        'warehouse_permits_id' => $wareHousePermits->id,
                    ]);

                    $from_store = StoreHouse::find($request->from_store_houses_id);
                    $to_store = StoreHouse::find($request->to_store_houses_id);
                    $warehousePermitProduct->load('product');

                    OperationLog::record(
                        'logged',
                        'warehouse_permit_log',
                        $wareHousePermits->id,
                        sprintf(
                            'أُنشئ إذن تحويل (بانتظار الاعتماد) كمية **%d** من **%s** إلى **%s** من المنتج **%s**',
                            $quantity,
                            $from_store->name ?? '',
                            $to_store->name ?? '',
                            $warehousePermitProduct->product->name,
                        ),
                    );

                } else {
                    // ✅ العمليات العادية (إضافة أو رصيد افتتاحي أو صرف)
                    $stockBefore = ProductDetails::where('store_house_id', $wareHousePermits->store_houses_id)
                        ->where('product_id', $request['product_id'][$index])
                        ->sum('quantity');

                    $direction = $request->permission_type == 2 ? -1 : 1;

                    $warehousePermitProduct = $products->create([
                        'quantity' => $quantity,
                        'total' => $request['total'][$index],
                        'unit_price' => $request['unit_price'][$index],
                        'product_id' => $request['product_id'][$index],
                        // العمودان NOT NULL: قيمةٌ غائبة كانت تُسقط الحفظ بخطأ SQL خام
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore + ($direction * $quantity),
                        'warehouse_permits_id' => $wareHousePermits->id,
                    ]);

                    // إذن صرف: تأكّدٌ مبكّر من توفّر الكمية (الخصم عند الاعتماد)
                    if ($request->permission_type == 2 && $stockBefore < $quantity) {
                        DB::rollBack();

                        return back()
                            ->withInput()
                            ->with('error', 'الكمية غير متوفّرة في المستودع: المتاح '.$stockBefore.' والمطلوب '.$quantity.'.');
                    }

                    if ($request->permission_type == 1 || $request->permission_type == 4) {
                        $store = StoreHouse::find($request->store_houses_id);
                        $warehousePermitProduct->load('product');

                        $logMsg = $request->permission_type == 4
                            ? 'أُنشئ إذن رصيد افتتاحي (بانتظار الاعتماد) كمية **%d** من المنتج **%s** إلى **%s**'
                            : 'أُنشئ إذن إضافة (بانتظار الاعتماد) كمية **%d** من المنتج **%s** إلى **%s**';

                        OperationLog::record(
                            'logged',
                            'warehouse_permit_log',
                            $wareHousePermits->id,
                            sprintf(
                                $logMsg,
                                $quantity,
                                $warehousePermitProduct->product->name,
                                $store->name ?? '',
                            ),
                        );
                    }
                }
            }

            /*
                الرصيد الافتتاحي يُعتمد فور حفظه.

                هو إثباتٌ لرصيدٍ قائم في المستودع لا طلبُ حركةٍ تنتظر موافقة،
                فإبقاؤه «معلَّقاً» يعني أنّ الكمية لا تدخل المخزون ولا يُرحَّل
                قيدها حتى يتذكّر أحدهم اعتماده — وهو ما بدا كأنّ الشاشة
                «لا تضيف الكمية».

                **والاعتماد داخل معاملة الحفظ نفسها:** كان بعد `commit`، فإن
                فشل (فترةٌ مقفلة، حدّ الباقة، حسابٌ غير مضبوط) بقي إذنٌ معلَّق
                لم يطلبه أحد — «حُفظ لكن تعذّر اعتماده». الآن يُحفظ معتمَداً أو
                لا يُحفظ أصلاً، ويعود رقم التسلسل معه.
            */
            if ((int) $request->permission_type === 4) {
                session()->forget('error');
                $approval = $this->approve($wareHousePermits->id);

                $error = $approval instanceof JsonResponse
                    ? (($approval->getData(true)['success'] ?? true) ? null : ($approval->getData(true)['message'] ?? 'تعذّر الاعتماد'))
                    : (method_exists($approval, 'getSession') && $approval->getSession() ? $approval->getSession()->get('error') : null);

                if ($error) {
                    if (DB::transactionLevel() > 0) {
                        DB::rollBack();
                    }

                    return redirect()->back()->withInput()
                        ->with('error', 'لم يُحفظ الرصيد الافتتاحي: '.$error);
                }

                DB::commit();

                return redirect()
                    ->route('store_permits_management.index')
                    ->with('success', 'تم إنشاء الرصيد الافتتاحي واعتماده وإضافة الكميات إلى المستودع');
            }

            DB::commit();

            return redirect()
                ->route('store_permits_management.index')
                ->with(['success' => 'تم انشاء اذن المخزن بنجاح']);

        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'store');
            DB::rollBack();
            Log::error('Warehouse Permit Error: '.$e->getMessage());

            return redirect()
                ->route('store_permits_management.index')
                ->with(['error' => 'حدث خطا ما برجاء المحاوله لاحقا: '.$e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $permit = WarehousePermits::findOrFail($id);
        $this->authorizeCreatorScope($permit->created_by);
        /*
            الأصناف المخزنية وحدها.

            كانت القائمة `Product::select()->get()` — كلَّ ما في النظام،
            والخدمات والمنتجات الرقمية بينها. فيختار المستخدم خدمةً ويكتب
            لها كمّية ويحفظ، فتظهر «ساعة استشارة» في رصيد المستودع وفي
            ورقة الجرد وقيمة المخزون.

            المنعُ هنا في الشاشة، وحارسُ `StockEligibility` على الموديل —
            فلا يمرّ سطرٌ من طريقٍ آخر.
        */
        $products = Product::whereIn('type', ProductType::stockedValues())->get();
        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $warehouseIds = $this->accessibleWarehouseIds();
        $storeHouses = StoreHouse::where('status', 'active')
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))
            ->select('id', 'name')->get();

        if ($permit->permission_type == 1 || $permit->permission_type == 4) {
            $view = $permit->permission_type == 4 ? 'stock::store_permits_management.opening_balance_edit' : 'stock::store_permits_management.edit';

            return view($view, compact('permit', 'storeHouses', 'products'));
        }

        if ($permit->permission_type == 2) {
            return view('stock::store_permits_management.manual_disbursement_edit', compact('permit', 'storeHouses', 'products'));
        }

        if ($permit->permission_type == 3) {
            return view('stock::store_permits_management.manual_conversion_edit', compact('permit', 'storeHouses', 'products'));
        }
    }

    public function update(WarehousePermitsRequest $request, $id)
    {
        // ✅ فحص حالة المنتجات أولاً
        $productIds = $request->input('product_id', []);
        $statusCheck = $this->checkProductsStatus($productIds);

        if (! $statusCheck['valid']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $statusCheck['message']);
        }

        $wareHousePermits = WarehousePermits::findOrFail($id);
        $this->authorizeCreatorScope($wareHousePermits->created_by);
        $this->authorizeCreatePermissionType($request->permission_type);

        // نطاق البيانات: لا يُنقَل الإذن إلى مستودعٍ خارج مستودعاتي (أو مستودعات من أديرهم)
        $this->assertWarehouseAccess($request->store_houses_id ? (int) $request->store_houses_id : null, 'store_houses_id');
        $this->assertWarehouseAccess($request->from_store_houses_id ? (int) $request->from_store_houses_id : null, 'from_store_houses_id');
        $this->assertWarehouseAccess($request->to_store_houses_id ? (int) $request->to_store_houses_id : null, 'to_store_houses_id');

        /*
            الإذن المعتمَد لا يُعدَّل: قيده مُرحَّل وكمياته منقولة، وتعديله
            يفكّ الارتباط بين المستند والقيد. أُلغِ الاعتماد أولاً ثم عدّل.
        */
        if ($wareHousePermits->status !== 'pending') {
            return redirect()
                ->route('store_permits_management.index')
                ->with('error', $wareHousePermits->status === 'apporod'
                    ? 'لا يمكن تعديل إذن معتمَد — ألغِ الاعتماد أولاً.'
                    : 'لا يمكن تعديل إذن مرفوض.');
        }

        // 🔹 تحديث بيانات الإذن
        if ($request->hasFile('attachments')) {
            $wareHousePermits->attachments = $this->UploadImage('assets/uploads/warehouse', $request->attachments);
        }

        /*
            كما في `store()`: شاشة تعديل الصرف تُرسل مستودعاً واحداً
            (`store_houses_id`) — وكان يُقرأ هنا من `from_store_houses_id`
            الغائب، فيُحفظ إذنُ الصرف المعدَّل بلا مستودع ثم يسقط اعتمادُه.
        */
        if ($request->permission_type == 3) {
            $wareHousePermits->store_houses_id = $request->from_store_houses_id;
        } elseif ($request->permission_type == 2) {
            $wareHousePermits->store_houses_id = $request->store_houses_id ?: $request->from_store_houses_id;
        } else {
            $wareHousePermits->store_houses_id = $request->store_houses_id;
        }

        $wareHousePermits->permission_type = $request->permission_type;
        $wareHousePermits->permission_date = $request->permission_date;
        $wareHousePermits->sub_account = $request->sub_account;
        /*
            رقم الإذن لا يُعاد ترقيمه في التعديل.

            كان يُؤخذ من الطلب كما هو — بلا فحصٍ لتفرّده ولا لموافقته
            التسلسل — فيكفي تحرير الحقل في الشاشة ليحمل إذنان الرقمَ نفسه،
            أو ليتغيّر رقمٌ سبق أن طُبع وأُشير إليه في سجلّ العمليات.
        */
        $wareHousePermits->number = $this->keepDocumentNumber(
            $wareHousePermits->number,
            match ((int) $request->permission_type) {
                2 => 'warehouse-dispose',
                3 => 'transfer-request',
                default => 'warehouse-add',
            }
        );
        $wareHousePermits->details = $request->details;
        // Calculate grand total as sum of all item totals
        $wareHousePermits->grand_total = array_sum($request->total ?? []);
        // «من/إلى» للتحويل وحده — كما في `store()`
        $isTransfer = (int) $request->permission_type === 3;
        $wareHousePermits->from_store_houses_id = $isTransfer ? $request->from_store_houses_id : null;
        $wareHousePermits->to_store_houses_id = $isTransfer ? $request->to_store_houses_id : null;
        $wareHousePermits->created_by = auth()->user()->id;

        // تحديث مصدر الأذن بناءً على نوع الإذن
        if ($request->permission_type == 1) {
            $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_ADD;
        } elseif ($request->permission_type == 2) {
            $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_DISBURSEMENT;
        } elseif ($request->permission_type == 3) {
            $wareHousePermits->permission_source_id = PermissionSources::MANUAL_TRANSFER;
        } elseif ($request->permission_type == 4) {
            $wareHousePermits->permission_source_id = PermissionSources::WAREHOUSE_OPENING_BALANCE;
        }

        $wareHousePermits->save();

        // 🔹 حذف المنتجات القديمة المرتبطة بالإذن
        WarehousePermitsProducts::where('warehouse_permits_id', $id)->delete();

        /*
            لا استعادةَ لكميات: الإذن المعلَّق لم يمسّ المخزون أصلاً.
            (كانت الكميات تُخصم عند الإنشاء ثم تُخصم ثانيةً عند الاعتماد.)
        */
        // 🔹 بنود الإذن الجديدة — بلا قيدٍ ولا حركة مخزون قبل الاعتماد
        foreach ($request['quantity'] as $index => $quantity) {
            $stockBefore = ProductDetails::where('product_id', $request['product_id'][$index])
                ->where('store_house_id', $wareHousePermits->store_houses_id)
                ->sum('quantity');

            $direction = in_array((int) $request->permission_type, [1, 4], true) ? 1 : -1;

            WarehousePermitsProducts::create([
                'quantity' => $quantity,
                'total' => $request['total'][$index],
                'unit_price' => $request['unit_price'][$index],
                'product_id' => $request['product_id'][$index],
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + ($direction * $quantity),
                'warehouse_permits_id' => $wareHousePermits->id,
            ]);
        }

        return redirect()
            ->route('store_permits_management.index')
            ->with(['success' => 'تم تحديث إذن المخزن — يبقى بانتظار الاعتماد']);
    }

    public function delete($id)
    {
        $wareHousePermits = WarehousePermits::findOrFail($id);
        $this->authorizeCreatorScope($wareHousePermits->created_by);

        /*
            الإذنُ المعتمد لا يُحذف: اعتمادُه حرّك الكميات ورحّل قيده، فحذفُه
            يُبقي المخزون والقيد قائمَين بلا مستندٍ يقابلهما. يُلغى اعتمادُه
            أولاً («إلغاء الاعتماد» يعكس الكميات والقيد) ثم يُحذف.
        */
        if ($wareHousePermits->status === 'apporod') {
            $message = 'لا يُحذف إذنٌ معتمد — ألغِ اعتماده أولاً ثم احذفه.';

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        WarehousePermitsProducts::where('warehouse_permits_id', $id)->delete();
        $wareHousePermits->delete();

        // كانت رسالة النجاح تُكتب تحت `error` فتظهر بلون الخطأ
        return redirect()
            ->route('store_permits_management.index')
            ->with(['success' => 'تم حذف أذن المخزن بنجاح']);
    }

    // public function getProductsByStore($storeId)
    // {
    //     $products = DB::table('product_details')
    //         ->join('products', 'product_details.product_id', '=', 'products.id')
    //         ->where('product_details.store_house_id', $storeId)
    //         ->select('products.id', 'products.name', 'products.sale_price', 'product_details.quantity')
    //         ->get();

    //     return response()->json($products);
    // }

    /**
     * رصيد صنفٍ في مستودع — تقرأه شاشة الإذن لحظة اختيار الصنف.
     *
     * **الجمع لا القراءة الأولى.** كانت `->value('quantity')` تُرجع أوّل صفٍّ
     * يصادفه الاستعلام في `product_details`، والصنف قد يكون له أكثر من صفٍّ
     * في المستودع الواحد. فيرى المستخدم رصيداً أقلّ من الحقيقة ويظنّ الصنف
     * ناقصاً، بينما `store()` و`approve()` تجمعان (`sum`) — فالرقم المعروض
     * غير الرقم الذي يُحاسَب عليه. `sum` هنا توحّدها.
     *
     * ورصيد مستودعٍ خارج نطاقي لا يُقرأ: هذا العنوان مباشرٌ ولا يمرّ بقائمة
     * المستودعات في الشاشة، فبلا هذا الحارس يكفي تخمين معرّفٍ لقراءة أرصدة
     * مستودعٍ لا أراه أصلاً.
     */
    public function getProductStock($storeId, $productId)
    {
        $storeId = (int) $storeId;
        $productId = (int) $productId;

        // ٤٠٣ لا ٤٢٢: هذه قراءةٌ بـAJAX، والشاشة تُفرّق في رسالتها بين
        // «ممنوع» و«تعذّرت القراءة». (`assertWarehouseAccess` تُطلق
        // `ValidationException` — رسالةُ حقلٍ في نموذجٍ لا جوابَ قراءة.)
        $allowed = $this->accessibleWarehouseIds();

        if ($allowed !== null && ! in_array($storeId, array_map('intval', $allowed), true)) {
            return response()->json(['message' => 'لا تملك صلاحية الوصول إلى هذا المستودع.'], 403);
        }

        if (! Product::whereKey($productId)->exists()) {
            return response()->json(['message' => 'الصنف غير موجود.'], 404);
        }

        $stock = (float) ProductDetails::where('store_house_id', $storeId)
            ->where('product_id', $productId)
            ->sum('quantity');

        return response()->json([
            'stock' => $stock,
            'formatted' => number_format($stock, 2),
        ]);
    }

    // Helper Function
    public function uploadImage($folder, $image)
    {
        $fileExtension = $image->getClientOriginalExtension();
        $fileName = time().rand(1, 99).'.'.$fileExtension;
        $image->move($folder, $fileName);

        return $fileName;
    }

    /**
     * إنشاء قيد محاسبي لعملية التحويل بين المستودعات
     */
    private function createTransferJournalEntry($request, $index, $quantity, $fromStore, $toStore, $warehousePermitProduct)
    {
        $amount = $request['total'][$index];

        // البحث عن الحسابات الموجودة للمستودعات
        $fromAccount = Account::where('storehouse_id', $request->from_store_houses_id)->first();
        $toAccount = Account::where('storehouse_id', $request->to_store_houses_id)->first();

        // التحقق من وجود الحسابات - إذا لم توجد، نرمي استثناء
        if (! $fromAccount) {
            throw new \Exception("لا يوجد حساب مرتبط بالمستودع المصدر: {$fromStore->name}");
        }

        if (! $toAccount) {
            throw new \Exception("لا يوجد حساب مرتبط بالمستودع الهدف: {$toStore->name}");
        }

        try {
            // إنشاء قيد اليومية
            $journalEntry = JournalEntry::create([
                'reference_number' => 'TRANSFER_'.$request->number.'_'.time(),
                'date' => $request->permission_date ?? now(),
                'description' => "تحويل كمية {$quantity} من المنتج ".$warehousePermitProduct->product->name.
                                 " من المستودع {$fromStore->name} إلى {$toStore->name}",
                'status' => 1,
                'currency' => $this->documentCurrency($warehousePermitProduct->warehousePermits),
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
                'linking_type' => LinkingType::SINGLE,
                'reference_id' => $warehousePermitProduct->warehouse_permits_id,
            ]);

            // تفصيل القيد - حساب المستودع المصدر (دائن - يقل الرصيد)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $fromAccount->id,
                'description' => $journalEntry->description,
                'debit' => 0,
                'credit' => $amount,
            ]);

            // تفصيل القيد - حساب المستودع الهدف (مدين - يزيد الرصيد)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $toAccount->id,
                'description' => $journalEntry->description,
                'debit' => $amount,
                'credit' => 0,
            ]);

            // تحديث أرصدة الحسابات
            $fromAccount->balance -= $amount;
            $fromAccount->save();

            $toAccount->balance += $amount;
            $toAccount->save();

            // تحديث أرصدة الحسابات الأب (إذا كانت موجودة)
            if ($fromAccount->parent_id) {
                Account::updateBalancesHierarchically($fromAccount->parent_id);
            }

            if ($toAccount->parent_id) {
                Account::updateBalancesHierarchically($toAccount->parent_id);
            }

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'WarehousePermits', 'createTransferJournalEntry');

            throw new \Exception('فشل في إنشاء قيد التحويل: '.$e->getMessage());
        }
    }

    /**
     * إنشاء كود حساب فريد
     */
    private function generateAccountCode()
    {
        $prefix = 'ACC'; // أو أي بادئة تناسب نظامك
        $timestamp = time();
        $random = rand(1000, 9999);

        return $prefix.'_'.$timestamp.'_'.$random;
    }

    /**
     * البحث في الأذون المخزنية
     */
    public function search(Request $request)
    {
        $query = WarehousePermits::with(['user.branch', 'fromStoreHouse', 'toStoreHouse', 'storeHouse'])->orderBy('id', 'DESC');

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        // تطبيق الترقيم
        $perPage = $request->input('per_page', 10);
        $wareHousePermits = $query->paginate($perPage);

        return view('stock::store_permits_management.partials.table', compact('wareHousePermits'));
    }

    /**
     * إنشاء كود حساب فريد
     */
    /**
     * إنشاء قيد محاسبي لعملية الإضافة إلى المستودع
     */
    private function createAdditionJournalEntry($request, $index, $quantity, $store, $warehousePermitProduct)
    {
        $amount = $request['total'][$index];

        // التأكد من وجود حساب للمستودع
        // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في
        // «الحسابات المحاسبية»، بنوعٍ وكودٍ موحَّدين
        $storeAccount = $this->warehouseAccount($request->store_houses_id, $store->name ?? null);

        // إنشاء قيد اليومية
        $journalEntry = JournalEntry::create([
            'reference_number' => 'ADD_'.$request->number.'_'.time(),
            'date' => $request->permission_date ?? now(),
            'description' => "إضافة كمية {$quantity} من المنتج ".$warehousePermitProduct->product->name.
                             " إلى المستودع {$store->name}",
            'status' => 1, // معتمد
            'currency' => $this->documentCurrency($warehousePermitProduct->warehousePermits),
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $warehousePermitProduct->warehouse_permits_id,
        ]);

        // تفصيل القيد - حساب المستودع (مدين)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $storeAccount->id,
            'description' => $journalEntry->description,
            'debit' => $amount,
            'credit' => 0,
        ]);

        // تفصيل القيد - حساب أطراف دائنة أخرى (دائن)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->requireAccountingAccountId('other_creditor_parties', SystemAccountIds::OTHER_CREDITOR_PARTIES),
            'description' => $journalEntry->description,
            'debit' => 0,
            'credit' => $amount,
        ]);

        // تحديث رصيد الحساب
        $storeAccount->balance += $amount;
        $storeAccount->save();

        // تحديث أرصدة الحسابات بشكل هرمي
        Account::updateBalancesHierarchically($storeAccount->id);
    }

    /**
     * إنشاء قيد محاسبي لرصيد افتتاح المخزون
     */
    private function createOpeningBalanceJournalEntry($request, $index, $quantity, $store, $warehousePermitProduct)
    {
        $amount = $request['total'][$index];

        // التأكد من وجود حساب للمستودع
        // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في
        // «الحسابات المحاسبية»، بنوعٍ وكودٍ موحَّدين
        $storeAccount = $this->warehouseAccount($request->store_houses_id, $store->name ?? null);

        // إنشاء قيد اليومية
        $journalEntry = JournalEntry::create([
            'reference_number' => 'OPB_'.$request->number.'_'.time(),
            'date' => $request->permission_date ?? now(),
            'description' => 'رصيد افتتاحي للمنتج '.$warehousePermitProduct->product->name.
                             " - كمية {$quantity} في المستودع {$store->name}",
            'status' => 1, // معتمد
            'currency' => $this->documentCurrency($warehousePermitProduct->warehousePermits),
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::OPENING_BALANCE,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $warehousePermitProduct->warehouse_permits_id,
        ]);

        // تفصيل القيد - حساب المستودع (مدين)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $storeAccount->id,
            'description' => $journalEntry->description,
            'debit' => $amount,
            'credit' => 0,
        ]);

        // تفصيل القيد - حساب رأس المال / أرصدة افتتاحية (دائن)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->requireAccountingAccountId('opening_balance', SystemAccountIds::OPENING_BALANCE),
            'description' => $journalEntry->description,
            'debit' => 0,
            'credit' => $amount,
        ]);

        // تحديث رصيد الحساب
        $storeAccount->balance += $amount;
        $storeAccount->save();

        // تحديث أرصدة الحسابات بشكل هرمي
        Account::updateBalancesHierarchically($storeAccount->id);
    }

    /**
     * إنشاء قيد محاسبي لعملية الصرف من المستودع
     */
    private function createDisbursementJournalEntry($request, $index, $quantity, $store, $warehousePermitProduct)
    {
        $amount = $request['total'][$index];

        // التأكد من وجود حساب للمستودع
        // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في
        // «الحسابات المحاسبية»، بنوعٍ وكودٍ موحَّدين
        $storeAccount = $this->warehouseAccount($request->from_store_houses_id, $store->name ?? null);

        // حساب أطراف مدينون آخرون كما ضُبط في «الحسابات المحاسبية»
        $othersAccount = $this->requireAccountingAccount('other_debtor_parties', SystemAccountIds::OTHER_DEBITOR_PARTIES);

        // إنشاء قيد اليومية
        $journalEntry = JournalEntry::create([
            'reference_number' => 'DISBURSE_'.$request->number.'_'.time(),
            'date' => $request->permission_date ?? now(),
            'description' => "صرف كمية {$quantity} من المنتج ".$warehousePermitProduct->product->name.
                             " من المستودع {$store->name}",
            'status' => 1,
            'currency' => $this->documentCurrency($warehousePermitProduct->warehousePermits),
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $warehousePermitProduct->warehouse_permits_id,
        ]);

        // تفصيل القيد - حساب أطراف مدينون آخرون (مدين)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $othersAccount->id,
            'description' => $journalEntry->description,
            'debit' => $amount,
            'credit' => 0,
        ]);

        // تفصيل القيد - حساب المستودع (دائن)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $storeAccount->id,
            'description' => $journalEntry->description,
            'debit' => 0,
            'credit' => $amount,
        ]);

        // تحديث رصيد حساب المستودع (النقص لأنه دائن)
        $storeAccount->balance -= $amount;
        $storeAccount->save();

        // تحديث رصيد حساب أطراف مدينون آخرون (الزيادة لأنه مدين)
        $othersAccount->balance += $amount;
        $othersAccount->save();

        // تحديث أرصدة الحسابات بشكل هرمي
        Account::updateBalancesHierarchically($storeAccount->id);
        Account::updateBalancesHierarchically($othersAccount->id);
    }

    /**
     * تصدير الأذون المخزنية إلى Excel
     */
    public function exportPermits(Request $request)
    {
        // إذا تم توفير معرف إذن محدد، نصدر ذلك الإذن فقط
        if ($request->filled('id')) {
            $permit = WarehousePermits::findOrFail($request->id);
            $permits = collect([$permit]);
        } else {
            // بناء الاستعلام الأساسي
            $query = WarehousePermits::query();

            // تطبيق الفلاتر الموجودة
            $this->applyFilters($query, $request);

            $permits = $query->get();
        }

        // تصدير إلى Excel
        $export = new StorePermitsExport($permits);

        return Excel::download($export, 'store_permits_export_'.now()->format('Y-m-d_H-i-s').'.xlsx');
    }

    /**
     * الموافقة على إذن المخزن وإنشاء قيد محاسبي
     */
    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $wareHousePermit = WarehousePermits::with(['items'])
                ->lockForUpdate()
                ->findOrFail($id);
            $this->authorizeCreatorScope($wareHousePermit->created_by);

            // إذن استلام المشتريات يُعتمَد من شاشته الخاصّة
            // (PurchaseReceivingController::approve) لا من هنا: تلك الشاشة
            // وحدها تُحدِّث حالة الاستلام وبنوده وتُزامن حالة أمر الشراء،
            // وهذه الشاشة العامّة تكتب فوق ذلك قيداً محاسبياً لا محلّ له
            // في دورة الاستلام (الفاتورة وحدها تُثبت الالتزام المالي).
            if ($wareHousePermit->reference_type === 'purchase_receiving') {
                DB::rollBack();

                $message = 'يُعتمد إذن استلام المشتريات من شاشة «استلام المشتريات» لا من هنا.';

                if (request()->ajax()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }

                return redirect()->back()->with('error', $message);
            }

            // الفترة المحاسبية: الاعتماد هو ما يُرحِّل القيد، فالتاريخ يُفحص هنا
            if ($block = $this->accountingPeriodBlock($wareHousePermit->permission_date)) {
                DB::rollBack();

                if (request()->ajax()) {
                    return response()->json(['success' => false, 'message' => $block], 422);
                }

                return redirect()->back()->with('error', $block);
            }

            // ✅ فحص حالة المنتجات في الإذن أولاً
            $productIds = $wareHousePermit->items->pluck('product_id')->toArray();
            $statusCheck = $this->checkProductsStatus($productIds);

            if (! $statusCheck['valid']) {
                DB::rollBack();

                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $statusCheck['message'],
                    ], 403);
                }

                return redirect()->back()->with('error', $statusCheck['message']);
            }

            // التحقق من حالة الإذن - حماية إضافية
            if ($wareHousePermit->status !== 'pending') {
                DB::rollBack();

                $statusText = $wareHousePermit->status === 'apporod' ? 'تمت الموافقة عليه مسبقاً' : 'تم رفضه مسبقاً';

                if (request()->ajax()) {
                    return response()->json(
                        [
                            'success' => false,
                            'message' => 'هذا الإذن '.$statusText,
                        ],
                        400,
                    );
                }

                return redirect()
                    ->back()
                    ->with('error', 'هذا الإذن '.$statusText);
            }

            // الحصول على المستودع المحدد في الإذن أو المستودع الرئيسي
            $targetStoreHouse = null;

            if ($wareHousePermit->permission_type == 3) {
                // إذن تحويل - استخدم المستودع المحدد في الإذن
                $targetStoreHouse = StoreHouse::find($wareHousePermit->to_store_houses_id);
            } else {
                // إذن إضافة أو صرف - استخدم المستودع المحدد في الإذن أو الرئيسي
                $targetStoreHouse = StoreHouse::find($wareHousePermit->store_houses_id);

                // إذا لم يجد المستودع المحدد، استخدم الرئيسي
                if (! $targetStoreHouse) {
                    $targetStoreHouse = StoreHouse::where('major', 1)->first();
                }
            }

            if (! $targetStoreHouse) {
                DB::rollBack();

                if (request()->ajax()) {
                    return response()->json(
                        [
                            'success' => false,
                            'message' => 'لم يتم العثور على المستودع المطلوب',
                        ],
                        400,
                    );
                }

                return redirect()->back()->with('error', 'لم يتم العثور على المستودع المطلوب');
            }

            // معالجة كل بند في الإذن
            foreach ($wareHousePermit->items as $item) {
                $quantity = $item->quantity;
                $productId = $item->product_id;

                // التحقق من وجود المنتج
                $product = Product::find($productId);
                if (! $product) {
                    DB::rollBack();

                    if (request()->ajax()) {
                        return response()->json(
                            [
                                'success' => false,
                                'message' => 'المنتج غير موجود',
                            ],
                            400,
                        );
                    }

                    return redirect()->back()->with('error', 'المنتج غير موجود');
                }

                // 🔥 تحديث الكمية في المستودع - إلزامي
                // النوع 4 «رصيد افتتاحي» يزيد المخزون مثل الإضافة تماماً،
                // وكان خارج الشروط كلّها فيُعتمد الإذن بلا أن تتحرّك كمّية
                if ($wareHousePermit->permission_type == 1 || $wareHousePermit->permission_type == 4) {
                    // 🟢 إذن إضافة للمستودع - زيادة الكمية
                    $storeHouseProduct = ProductDetails::firstOrCreate(
                        [
                            'store_house_id' => $targetStoreHouse->id,
                            'product_id' => $productId,
                        ],
                        [
                            'quantity' => 0,
                        ]
                    );

                    // حفظ الكمية القديمة للتحقق
                    $oldQuantity = $storeHouseProduct->quantity;

                    // زيادة الكمية
                    $storeHouseProduct->quantity += $quantity;

                    // تحديث السعر إذا كان موجود في الإذن
                    if (isset($item->price) && $item->price > 0) {
                        $storeHouseProduct->price = $item->price;
                    }

                    $saveResult = $storeHouseProduct->save();

                    // التحقق من نجاح حفظ الكمية
                    if (! $saveResult) {
                        DB::rollBack();
                        $errorMsg = 'فشل في إضافة الكمية للمستودع للمنتج: '.$product->name;
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMsg,
                            ], 400);
                        }

                        return redirect()->back()->with('error', $errorMsg);
                    }

                    // التحقق من أن الكمية تم تحديثها بنجاح
                    $storeHouseProduct->refresh();
                    if (abs((float) $storeHouseProduct->quantity - (float) ($oldQuantity + $quantity)) > 0.0001) {
                        DB::rollBack();
                        $errorMsg = 'فشل التحقق من إضافة الكمية للمستودع. الكمية المتوقعة: '.($oldQuantity + $quantity).'، الكمية الفعلية: '.$storeHouseProduct->quantity;
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMsg,
                            ], 400);
                        }

                        return redirect()->back()->with('error', $errorMsg);
                    }

                    Log::info('تمت إضافة كمية للمستودع', [
                        'store_house' => $targetStoreHouse->name,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'new_total' => $storeHouseProduct->quantity,
                    ]);

                } elseif ($wareHousePermit->permission_type == 2) {
                    // 🔴 إذن صرف من المستودع - تقليل الكمية
                    $storeHouseProduct = ProductDetails::where('store_house_id', $targetStoreHouse->id)
                        ->where('product_id', $productId)
                        ->first();

                    if (! $storeHouseProduct) {
                        DB::rollBack();
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'المنتج غير موجود في المستودع',
                            ], 400);
                        }

                        return redirect()->back()->with('error', 'المنتج غير موجود في المستودع');
                    }

                    // التحقق من الكمية المتاحة
                    if ($storeHouseProduct->quantity < $quantity) {
                        DB::rollBack();
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'الكمية المطلوبة ('.$quantity.') أكبر من الكمية المتاحة ('.$storeHouseProduct->quantity.')',
                            ], 400);
                        }

                        return redirect()->back()->with('error', 'الكمية المطلوبة أكبر من الكمية المتاحة');
                    }

                    // حفظ الكمية القديمة
                    $oldQuantity = $storeHouseProduct->quantity;

                    // تقليل الكمية
                    $storeHouseProduct->quantity -= $quantity;
                    $saveResult = $storeHouseProduct->save();

                    // التحقق من نجاح حفظ الكمية
                    if (! $saveResult) {
                        DB::rollBack();
                        $errorMsg = 'فشل في صرف الكمية من المستودع للمنتج: '.$product->name;
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMsg,
                            ], 400);
                        }

                        return redirect()->back()->with('error', $errorMsg);
                    }

                    // التحقق من أن الكمية تم تحديثها بنجاح
                    $storeHouseProduct->refresh();
                    if (abs((float) $storeHouseProduct->quantity - (float) ($oldQuantity - $quantity)) > 0.0001) {
                        DB::rollBack();
                        $errorMsg = 'فشل التحقق من صرف الكمية من المستودع. الكمية المتوقعة: '.($oldQuantity - $quantity).'، الكمية الفعلية: '.$storeHouseProduct->quantity;
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMsg,
                            ], 400);
                        }

                        return redirect()->back()->with('error', $errorMsg);
                    }

                    Log::info('تم صرف كمية من المستودع', [
                        'store_house' => $targetStoreHouse->name,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'remaining' => $storeHouseProduct->quantity,
                    ]);

                } elseif ($wareHousePermit->permission_type == 3) {
                    // 🔄 إذن تحويل من مستودع إلى مستودع
                    $fromStoreHouse = StoreHouse::find($wareHousePermit->from_store_houses_id);

                    if (! $fromStoreHouse) {
                        $fromStoreHouse = StoreHouse::where('major', 1)->first();
                    }

                    if (! $fromStoreHouse) {
                        DB::rollBack();
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'لم يتم العثور على المستودع المصدر',
                            ], 400);
                        }

                        return redirect()->back()->with('error', 'لم يتم العثور على المستودع المصدر');
                    }

                    // التحقق من الكمية في المستودع المصدر
                    $fromStoreHouseProduct = ProductDetails::where('store_house_id', $fromStoreHouse->id)
                        ->where('product_id', $productId)
                        ->first();

                    if (! $fromStoreHouseProduct) {
                        DB::rollBack();
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'المنتج غير موجود في المستودع المصدر',
                            ], 400);
                        }

                        return redirect()->back()->with('error', 'المنتج غير موجود في المستودع المصدر');
                    }

                    if ($fromStoreHouseProduct->quantity < $quantity) {
                        DB::rollBack();
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'الكمية المطلوبة ('.$quantity.') أكبر من الكمية المتاحة في المستودع المصدر ('.$fromStoreHouseProduct->quantity.')',
                            ], 400);
                        }

                        return redirect()->back()->with('error', 'الكمية المطلوبة أكبر من الكمية المتاحة في المستودع المصدر');
                    }

                    // حفظ الكميات القديمة للتحقق
                    $fromOldQuantity = $fromStoreHouseProduct->quantity;

                    // تقليل الكمية من المستودع المصدر
                    $fromStoreHouseProduct->quantity -= $quantity;
                    $fromSaveResult = $fromStoreHouseProduct->save();

                    // التحقق من نجاح تقليل الكمية من المصدر
                    if (! $fromSaveResult) {
                        DB::rollBack();
                        $errorMsg = 'فشل في تقليل الكمية من المستودع المصدر للمنتج: '.$product->name;
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMsg,
                            ], 400);
                        }

                        return redirect()->back()->with('error', $errorMsg);
                    }

                    // زيادة الكمية في المستودع المستهدف
                    $toStoreHouseProduct = ProductDetails::firstOrCreate(
                        [
                            'store_house_id' => $targetStoreHouse->id,
                            'product_id' => $productId,
                        ],
                        [
                            'quantity' => 0,
                        ]
                    );

                    $toOldQuantity = $toStoreHouseProduct->quantity;

                    $toStoreHouseProduct->quantity += $quantity;
                    $toSaveResult = $toStoreHouseProduct->save();

                    // التحقق من نجاح إضافة الكمية إلى الهدف
                    if (! $toSaveResult) {
                        DB::rollBack();
                        $errorMsg = 'فشل في إضافة الكمية إلى المستودع الهدف للمنتج: '.$product->name;
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMsg,
                            ], 400);
                        }

                        return redirect()->back()->with('error', $errorMsg);
                    }

                    // التحقق من تحديث الكميات بنجاح
                    $fromStoreHouseProduct->refresh();
                    $toStoreHouseProduct->refresh();

                    if (abs((float) $fromStoreHouseProduct->quantity - (float) ($fromOldQuantity - $quantity)) > 0.0001 ||
                        abs((float) $toStoreHouseProduct->quantity - (float) ($toOldQuantity + $quantity)) > 0.0001) {
                        DB::rollBack();
                        $errorMsg = 'فشل التحقق من تحويل الكمية بين المستودعات';
                        if (request()->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMsg,
                            ], 400);
                        }

                        return redirect()->back()->with('error', $errorMsg);
                    }

                    Log::info('تم تحويل كمية بين المستودعات', [
                        'from_store' => $fromStoreHouse->name,
                        'to_store' => $targetStoreHouse->name,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ]);
                }
            }

            // ✅ تحديث حالة الإذن إلى موافق عليه
            $wareHousePermit->status = 'apporod';
            $wareHousePermit->save();

            // ✅ تحديث حالة الفاتورة المرتبطة إذا وجدت وتحديث المستودع من بياناتها
            if ($wareHousePermit->reference_type === 'purchase_invoice' && $wareHousePermit->reference_id) {
                $purchaseInvoice = PurchaseInvoice::with('items.product')->find($wareHousePermit->reference_id);
                if ($purchaseInvoice && $purchaseInvoice->receiving_status !== 'received') {
                    // ✅ تحديث المستودع من بنود فاتورة المشتريات
                    foreach ($purchaseInvoice->items as $invoiceItem) {
                        if (! $invoiceItem->product) {
                            DB::rollBack();
                            $errorMsg = 'المنتج غير موجود في بند فاتورة المشتريات';
                            if (request()->ajax()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => $errorMsg,
                                ], 400);
                            }

                            return redirect()->back()->with('error', $errorMsg);
                        }

                        // إضافة الكمية من بند الفاتورة إلى المستودع
                        $storeHouseProduct = ProductDetails::firstOrCreate(
                            [
                                'store_house_id' => $targetStoreHouse->id,
                                'product_id' => $invoiceItem->product_id,
                            ],
                            [
                                'quantity' => 0,
                            ]
                        );

                        $oldQty = $storeHouseProduct->quantity;
                        $storeHouseProduct->quantity += $invoiceItem->quantity;
                        $storeHouseProduct->save();

                        // التحقق من نجاح التحديث
                        $storeHouseProduct->refresh();
                        if (abs((float) $storeHouseProduct->quantity - (float) ($oldQty + $invoiceItem->quantity)) > 0.0001) {
                            DB::rollBack();
                            $errorMsg = 'فشل في تحديث كمية المنتج من فاتورة المشتريات: '.$invoiceItem->product->name;
                            if (request()->ajax()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => $errorMsg,
                                ], 400);
                            }

                            return redirect()->back()->with('error', $errorMsg);
                        }

                        Log::info('تم تحديث المستودع من فاتورة المشتريات', [
                            'purchase_invoice_id' => $purchaseInvoice->id,
                            'store_house' => $targetStoreHouse->name,
                            'product_id' => $invoiceItem->product_id,
                            'quantity_added' => $invoiceItem->quantity,
                            'total_quantity' => $storeHouseProduct->quantity,
                        ]);
                    }

                    // تحديث حالة فاتورة المشتريات
                    $purchaseInvoice->receiving_status = 'received';
                    $purchaseInvoice->received_date = now()->format('Y-m-d');
                    $purchaseInvoice->save();
                }
            }

            // 🔥 إنشاء القيد المحاسبي - إلزامي
            $journalResult = $this->createApprovalJournalEntry($wareHousePermit);

            /*
                ورقة الجرد تُقفل حين لا يبقى لها إذنٌ معلّق.

                كانت الشاشة تضع الورقة على «مُسوّاة» لحظةَ إصدار الأذون —
                قبل أن يعتمدها أحد. فتُقرأ منتهيةً وكمّياتها لم تتحرّك.
            */
            $this->closeAdjustmentIfSettled($wareHousePermit);

            // التحقق من نجاح إنشاء القيد
            if (! $journalResult || (is_array($journalResult) && isset($journalResult['success']) && ! $journalResult['success'])) {
                DB::rollBack();

                $errorMessage = 'فشل إنشاء القيد المحاسبي للإذن المخزني';
                if (is_array($journalResult) && isset($journalResult['message'])) {
                    $errorMessage = $journalResult['message'];
                }

                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                    ], 400);
                }

                return redirect()->back()->with('error', $errorMessage);
            }

            // إلى منشئ الإذن — كان `user_id`، ولا عمود بهذا الاسم في الأذون، فيُحفظ الإشعار بلا مستلِم
            notifications::create([
                'user_id' => $wareHousePermit->created_by,
                'receiver_id' => $wareHousePermit->created_by,
                'title' => 'تمت الموافقة على الإذن المخزني رقم '.$wareHousePermit->number,
                'description' => 'تمت الموافقة على الإذن المخزني رقم '.$wareHousePermit->number,
                'type' => 'success',
            ]);

            DB::commit();

            $successMessage = 'تمت الموافقة على الإذن المخزني رقم '.$wareHousePermit->number.' بنجاح وتم تنفيذه في المستودع: '.$targetStoreHouse->name.' وتم إنشاء القيد المحاسبي';

            // إذا كان الطلب AJAX
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'new_status' => 'apporod',
                ]);
            }

            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'approve');
            DB::rollback();
            Log::error('خطأ في الموافقة على الإذن المخزني: '.$e->getMessage(), [
                'permit_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = 'عذراً، حدث خطأ أثناء الموافقة على الإذن المخزني: '.$e->getMessage();

            if (request()->ajax()) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => $errorMessage,
                    ],
                    500,
                );
            }

            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * رفض إذن المخزن
     */
    public function reject(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $permit = WarehousePermits::findOrFail($id);
            $this->authorizeCreatorScope($permit->created_by);

            // التحقق من أن الإذن لم تتم الموافقة عليه بالفعل
            if ($permit->status == 'apporod') {
                DB::rollBack();

                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن رفض إذن تمت الموافقة عليه بالفعل',
                    ], 400);
                }

                return redirect()->back()->with('error', 'لا يمكن رفض إذن تمت الموافقة عليه بالفعل');
            }

            // التحقق من أن الإذن معلق (pending)
            if ($permit->status !== 'pending') {
                DB::rollBack();

                $statusText = $permit->status === 'rejec' ? 'تم رفضه مسبقاً' : 'حالة غير صالحة';

                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'هذا الإذن '.$statusText,
                    ], 400);
                }

                return redirect()->back()->with('error', 'هذا الإذن '.$statusText);
            }

            // تحديث حالة الإذن إلى مرفوض
            $permit->status = 'rejec';
            /*
                نافذتا الرفض — في الفهرس وفي شاشة العرض — تُرسلان الحقل باسم
                `rejection_reason`، وكان يُقرأ هنا باسم `reason`. فالسبب
                الذي يكتبه المستخدم يُهمَل ويُحفظ الإذن مرفوضاً بلا سبب،
                ولا شيء يشير إلى ضياعه. الاسم القديم يبقى مقبولاً لِما قد
                يناديها من مكانٍ آخر.
            */
            $permit->rejection_reason = $request->input('rejection_reason')
                ?: $request->input('reason');
            $permit->rejected_by = Auth::id();
            $permit->rejected_at = now();
            $permit->save();

            // تسجيل النشاط في سجل الأنشطة
            OperationLog::record(
                'rejected',
                'warehouse_permit_log',
                $permit->id,
                sprintf(
                    'تم رفض إذن المخزن رقم **%s**',
                    $permit->number ?? $permit->id
                ),
            );

            // إرسال إشعار للمستخدم
            // إلى منشئ الإذن — كان `user_id`، ولا عمود بهذا الاسم في الأذون، فيُحفظ الإشعار بلا مستلِم
            notifications::create([
                'user_id' => $permit->created_by,
                'receiver_id' => $permit->created_by,
                'title' => 'تم رفض الإذن المخزني رقم '.$permit->number,
                'description' => 'تم رفض الإذن المخزني رقم '.$permit->number,
                'type' => 'error',
            ]);

            DB::commit();

            $successMessage = 'تم رفض الإذن المخزني رقم '.$permit->number.' بنجاح';

            // إذا كان الطلب AJAX
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'new_status' => 'rejec',
                ]);
            }

            // الرجوع لنفس الصفحة مع رسالة النجاح
            return redirect()->back()->with('success', $successMessage);

        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'reject');
            DB::rollBack();
            Log::error('Warehouse Permit Rejection Error: '.$e->getMessage(), [
                'permit_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = 'حدث خطأ أثناء رفض الإذن: '.$e->getMessage();

            // إذا كان الطلب AJAX
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 500);
            }

            // الرجوع لنفس الصفحة مع رسالة الخطأ
            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * إنشاء قيد الموافقة على عملية إضافة للمخزن
     */
    // private function createAdditionApprovalEntry($journalEntry, $permit)
    // {
    //     // الحصول على حساب المستودع
    //     $storeAccount = Account::where('storehouse_id', $permit->store_houses_id)->first();

    //     if (!$storeAccount) {
    //         // إنشاء الحساب إذا لم يوجد
    //         $store = StoreHouse::find($permit->store_houses_id);
    //         $storeAccount = Account::create([
    //             'name' => 'حساب المستودع - ' . ($store->name ?? 'مستودع غير معروف'),
    //             'storehouse_id' => $permit->store_houses_id,
    //             'account_type' => 'assets',
    //             'balance_type' => Account::DEBIT,
    //             'balance' => 0,
    //             'status' => 1,
    //         ]);
    //     }

    //     // تفصيل القيد - حساب المستودع (مدين)
    //     JournalEntryDetail::create([
    //         'journal_entry_id' => $journalEntry->id,
    //         'account_id' => $storeAccount->id,
    //         'description' => $journalEntry->description,
    //         'debit' => $permit->grand_total,
    //         'credit' => 0,
    //     ]);

    //     // تفصيل القيد - حساب أطراف دائنة أخرى (دائن)
    //     JournalEntryDetail::create([
    //         'journal_entry_id' => $journalEntry->id,
    //         'account_id' => SystemAccountIds::OTHER_CREDITOR_PARTIES, // حساب "أطراف دائنة أخرى"
    //         'description' => $journalEntry->description,
    //         'debit' => 0,
    //         'credit' => $permit->grand_total,
    //     ]);

    //     // تحديث رصيد الحساب
    //     $storeAccount->balance += $permit->grand_total;
    //     $storeAccount->save();

    //     // تحديث أرصدة الحسابات بشكل هرمي
    //     Account::updateBalancesHierarchically($storeAccount->id);
    // }

    // /**
    //  * إنشاء قيد الموافقة على عملية صرف من المخزن
    //  */
    // private function createDisbursementApprovalEntry($journalEntry, $permit)
    // {
    //     // الحصول على حساب المستودع
    //     $storeAccount = Account::where('storehouse_id', $permit->from_store_houses_id)->first();

    //     if (!$storeAccount) {
    //         // إنشاء الحساب إذا لم يوجد
    //         $store = StoreHouse::find($permit->from_store_houses_id);
    //         $storeAccount = Account::create([
    //             'name' => 'حساب المستودع - ' . ($store->name ?? 'مستودع غير معروف'),
    //             'storehouse_id' => $permit->from_store_houses_id,
    //             'account_type' => 'assets',
    //             'balance_type' => Account::DEBIT,
    //             'balance' => 0,
    //             'status' => 1,
    //         ]);
    //     }

    //     // الحصول على حساب أطراف مدينون آخرون
    //     // كان SystemAccountIds::OTHERS_ACCOUNT — حُذف الثابت لأنه لم يشر إلى حساب موجود
    //     $othersAccount = Account::find(SystemAccountIds::OTHER_DEBITOR_PARTIES);

    //     if (!$othersAccount) {
    //         throw new \Exception('حساب أطراف مدينون آخرون غير موجود');
    //     }

    //     // تفصيل القيد - حساب أطراف مدينون آخرون (مدين)
    //     JournalEntryDetail::create([
    //         'journal_entry_id' => $journalEntry->id,
    //         'account_id' => $othersAccount->id,
    //         'description' => $journalEntry->description,
    //         'debit' => $permit->grand_total,
    //         'credit' => 0,
    //     ]);

    //     // تفصيل القيد - حساب المستودع (دائن)
    //     JournalEntryDetail::create([
    //         'journal_entry_id' => $journalEntry->id,
    //         'account_id' => $storeAccount->id,
    //         'description' => $journalEntry->description,
    //         'debit' => 0,
    //         'credit' => $permit->grand_total,
    //     ]);

    //     // تحديث رصيد حساب المستودع (النقص لأنه دائن)
    //     $storeAccount->balance -= $permit->grand_total;
    //     $storeAccount->save();

    //     // تحديث رصيد حساب أطراف مدينون آخرون (الزيادة لأنه مدين)
    //     $othersAccount->balance += $permit->grand_total;
    //     $othersAccount->save();

    //     // تحديث أرصدة الحسابات بشكل هرمي
    //     Account::updateBalancesHierarchically($storeAccount->id);
    //     Account::updateBalancesHierarchically($othersAccount->id);
    // }

    /**
     * استيراد الأذون المخزنية من ملف Excel
     */
    public function importPermits(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv,txt',
        ]);

        // For now, we'll just return a success response
        // In a real implementation, you would process the import here
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم استيراد الأذون المخزنية بنجاح!',
            ]);
        }

        return redirect()->back()->with('success', 'تم استيراد الأذون المخزنية بنجاح!');
    }

    /**
     * عرض الأذون المخزنية المعلقة (بانتظار الموافقة)
     */
    public function pendingPermits(Request $request)
    {
        $query = WarehousePermits::where('status', 'pending') // pending = معلق
            ->orderBy('id', 'DESC');

        // نطاق البيانات: منشئو الإذن هو نفسه أو من يديرهم إدارياً (default_warehouses/direct_manager_id)
        $this->scopeToCreator($query);

        // وسياقُ الفرع كما في `index()` — الإذن يُنسب إلى فرعه عبر مستودعه
        $this->scopeToWarehouseColumns($query);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        $perPage = $request->input('per_page', 10);
        $permits = $query->paginate($perPage);

        if ($request->ajax()) {
            // الجزئية تقرأ `$wareHousePermits`؛ كان يصلها `$permits` فتسقط
            return view('stock::store_permits_management.partials.table', [
                'wareHousePermits' => $permits,
            ])->render();
        }

        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $storeHouses = $this->accessibleWarehouses(['id', 'name'])->get();
        $branches = Branch::where('status', 0)->select('id', 'name')->get();
        $clients = Client::all();
        $permissionSources = PermissionSource::all();
        $suppliers = Supplier::all();
        // قائمة «أنشأه» في الفلاتر تُحصَر بنطاقي أيضاً — لا فائدة من عرض زملاء لا أراهم أصلاً
        $users = User::whereNotNull('employee_id')
            ->when($this->accessibleUserIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
            ->get();
        // الأصناف المخزنية وحدها — الخدمات والرقميّات لا رصيد لها
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        return view('stock::store_permits_management.pending', compact('permits', 'permissionSources', 'storeHouses', 'branches', 'clients', 'suppliers', 'users', 'products'));
    }

    /**
     * عرض الأذون المخزنية الموافق عليها
     */
    public function approvedPermits(Request $request)
    {
        $query = WarehousePermits::where('status', 'apporod') // approved = موافق عليه
            ->orderBy('id', 'DESC');

        // نطاق البيانات: منشئو الإذن هو نفسه أو من يديرهم إدارياً (default_warehouses/direct_manager_id)
        $this->scopeToCreator($query);

        // وسياقُ الفرع كما في `index()` — الإذن يُنسب إلى فرعه عبر مستودعه
        $this->scopeToWarehouseColumns($query);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        $perPage = $request->input('per_page', 10);
        $permits = $query->paginate($perPage);

        if ($request->ajax()) {
            // الجزئية تقرأ `$wareHousePermits`؛ كان يصلها `$permits` فتسقط
            return view('stock::store_permits_management.partials.table', [
                'wareHousePermits' => $permits,
            ])->render();
        }

        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $storeHouses = $this->accessibleWarehouses(['id', 'name'])->get();
        $branches = Branch::where('status', 0)->select('id', 'name')->get();
        $clients = Client::all();
        $permissionSources = PermissionSource::all();
        $suppliers = Supplier::all();
        // قائمة «أنشأه» في الفلاتر تُحصَر بنطاقي أيضاً — لا فائدة من عرض زملاء لا أراهم أصلاً
        $users = User::whereNotNull('employee_id')
            ->when($this->accessibleUserIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
            ->get();
        // الأصناف المخزنية وحدها — الخدمات والرقميّات لا رصيد لها
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        return view('stock::store_permits_management.approved', compact('permits', 'permissionSources', 'storeHouses', 'branches', 'clients', 'suppliers', 'users', 'products'));
    }

    /**
     * عرض الأذون المخزنية المرفوضة
     */
    public function rejectedPermits(Request $request)
    {
        $query = WarehousePermits::where('status', 'rejec') // rejected = مرفوض
            ->orderBy('id', 'DESC');

        // نطاق البيانات: منشئو الإذن هو نفسه أو من يديرهم إدارياً (default_warehouses/direct_manager_id)
        $this->scopeToCreator($query);

        // وسياقُ الفرع كما في `index()` — الإذن يُنسب إلى فرعه عبر مستودعه
        $this->scopeToWarehouseColumns($query);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        $perPage = $request->input('per_page', 10);
        $permits = $query->paginate($perPage);

        if ($request->ajax()) {
            // الجزئية تقرأ `$wareHousePermits`؛ كان يصلها `$permits` فتسقط
            return view('stock::store_permits_management.partials.table', [
                'wareHousePermits' => $permits,
            ])->render();
        }

        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) فقط عبر default_warehouses
        $storeHouses = $this->accessibleWarehouses(['id', 'name'])->get();
        $branches = Branch::where('status', 0)->select('id', 'name')->get();
        $clients = Client::all();
        $permissionSources = PermissionSource::all();
        $suppliers = Supplier::all();
        // قائمة «أنشأه» في الفلاتر تُحصَر بنطاقي أيضاً — لا فائدة من عرض زملاء لا أراهم أصلاً
        $users = User::whereNotNull('employee_id')
            ->when($this->accessibleUserIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
            ->get();
        // الأصناف المخزنية وحدها — الخدمات والرقميّات لا رصيد لها
        $products = Product::whereIn('type', ProductType::stockedValues())->get();

        return view('stock::store_permits_management.rejected', compact('permits', 'permissionSources', 'storeHouses', 'branches', 'clients', 'suppliers', 'users', 'products'));
    }

    /**
     * تطبيق فلاتر البحث على الاستعلام
     */
    private function applyFilters($query, Request $request)
    {
        // فلترة حسب الفرع
        if ($request->filled('branch')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('branch_id', $request->branch);
            });
        }

        // فلترة حسب كلمات البحث (الاسم أو الكود)
        if ($request->filled('keywords')) {
            $keywords = '%'.$request->keywords.'%';
            $query->where(function ($q) use ($keywords) {
                $q->where('number', 'like', $keywords)
                    ->orWhere('details', 'like', $keywords);
            });
        }

        // فلترة حسب نوع الإذن
        if ($request->filled('permission_type')) {
            $query->where('permission_type', $request->permission_type);
        }

        // فلترة حسب مصدر الإذن
        if ($request->filled('permission_source')) {
            $query->where('permission_source_id', $request->permission_source);
        }

        // فلترة حسب المستودع
        if ($request->filled('store_house')) {
            $query->where('store_houses_id', $request->store_house);
        }

        // فلترة حسب العميل
        if ($request->filled('client')) {
            $query->where('sub_account', $request->client);
        }

        // فلترة حسب المورد
        if ($request->filled('supplier')) {
            $query->where('sub_account', $request->supplier);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب العملة — الافتراضية هي عملة النظام، والقيمة الفارغة
        // تعني «كل العملات» فلا تُقصي شيئاً
        $currency = $this->filterCurrency($request);
        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        // فلترة حسب المستخدم الذي أضاف الإذن
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // فلترة حسب المنتج
        if ($request->filled('product')) {
            $query->whereHas('products', function ($q) use ($request) {
                $q->where('product_id', $request->product);
            });
        }

        /*
            **الفلترة بالعلاقة لا بالتاريخ.**

            الإذنُ يُختَم بـ`accounting_period_id` لحظةَ إنشائه، والنطاقُ
            الافتراضي السنةُ المالية الجارية. وكانت الشاشة تفتح على الأذون
            كلِّها منذ أوّل إذنٍ في النظام.

            والتاريخُ يبقى فلتراً حرّاً إلى جانبها لمن أراد مدىً بعينه.
        */
        AccountingPeriodFilter::apply($query, $this->stockPeriod($request));

        if ($request->filled('from_date')) {
            $query->whereDate('permission_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('permission_date', '<=', $request->to_date);
        }
    }

    /**
     * عرض صفحة الإحصائيات
     */
    public function getStats()
    {
        $totalPermits = WarehousePermits::count();
        $pendingPermits = WarehousePermits::where('status', 'pending')->count();
        $approvedPermits = WarehousePermits::where('status', 'apporod')->count();
        $rejectedPermits = WarehousePermits::where('status', 'rejec')->count();

        $permitTypes = [
            'addition' => WarehousePermits::where('permission_type', 1)->count(),
            'disbursement' => WarehousePermits::where('permission_type', 2)->count(),
            'transfer' => WarehousePermits::where('permission_type', 3)->count(),
        ];

        return response()->json([
            'total' => $totalPermits,
            'pending' => $pendingPermits,
            'approved' => $approvedPermits,
            'rejected' => $rejectedPermits,
            'types' => $permitTypes,
        ]);
    }

    public function print($id, $type = 'with-prices')
    {
        $permit = WarehousePermits::with(['fromStoreHouse', 'toStoreHouse', 'products'])->findOrFail($id);
        $this->authorizeCreatorScope($permit->created_by);

        /*
            **قالب المستند من الإعدادات أولاً.**

            نوعا «إذن صرف/إذن استلام» معرَّفان في شاشة «قوالب المستندات»
            (`warehouse_permit_issue`/`warehouse_permit_receipt`) والمستخدم
            يصمّم قالبه ويعيّنه افتراضياً — وكانت هذه الدالة تتجاهله كلياً
            وتطبع قالب TCPDF المدمج، فلا أثر لما صُمِّم.

            الترتيب كنظيره في المبيعات والعملاء: قالبٌ طُلب صراحةً
            (`?template=<id>`) ← وإلا افتراضيُّ النوع من `PrintSettings` ←
            وإلا القالبُ المدمج أدناه احتياطاً لا يُنزَع — منشأةٌ لم تصمّم
            قالباً يجب أن تطبع إذنها. وإظهار الأسعار من عدمه في القالب
            المصمَّم تحكمه إعداداتُ القالب نفسه (شاشة التصميم)، لا لاحقة
            المسار — تلك تخصّ القالب المدمج وحده.
        */
        $documentType = (int) $permit->permission_type === 2
            ? 'warehouse_permit_issue'
            : 'warehouse_permit_receipt';

        $template = null;

        if (is_numeric(request('template'))) {
            $template = DocumentTemplate::where('id', (int) request('template'))
                ->where('document_type', $documentType)
                ->first();
        }

        $template = $template ?: PrintSettings::defaultTemplateFor($documentType);

        if ($template) {
            try {
                $data = app(DocumentTemplateDataService::class)->getDocumentData($documentType, $permit->id);
                $html = app(DocumentTemplateRenderer::class)->render($template, $data);

                return response(DocumentPrintPage::wrap(
                    $html,
                    'إذن مستودع '.$permit->number,
                    route('store_permits_management.index')
                ));
            } catch (\Throwable $e) {
                // تعذُّر تصيير القالب لا يمنع الطباعة: يُسجَّل ويعود المدمج
                OperationLog::failure($e, 'warehouse_permit_log', 'print_template', $permit->id);
            }
        }

        // إنشاء PDF جديد باستخدام TCPDF
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // تعيين معلومات الوثيقة
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('إذن مستودع');
        $pdf->SetSubject('Warehouse Permit');
        $pdf->SetKeywords('Warehouse, Permit, Fawtra');

        // تعيين الهوامش
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // تعيين الاتجاه من اليمين إلى اليسار
        $pdf->setRTL(true);

        // إضافة صفحة جديدة
        $pdf->AddPage();

        // ✅ تعيين الخط العربي المستخدم في جميع المطبوعات
        $pdf->SetFont('aealarabiya', '', 12);

        // اختيار القالب المناسب
        if ($type === 'without-prices') {
            $html = view('stock::store_permits_management.partials.pdf_no_prices', compact('permit'))->render();
        } else {
            $html = view('stock::store_permits_management.partials.pdf', compact('permit'))->render();
        }

        // إضافة المحتوى للـ PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // إنشاء اسم الملف
        $filename = 'إذن_مستودع_'.$permit->code.'_'.now()->format('Y-m-d').'.pdf';

        // إخراج الملف (تحميل مباشر)
        return $pdf->Output($filename, 'I'); // I = عرض داخل المتصفح
    }

    /**
     * إلغاء الموافقة على الإذن المخزني
     * يقوم بعكس الكميات وعكس القيود المحاسبية
     */
    public function cancelApproval($id)
    {
        try {
            DB::beginTransaction();

            $permit = WarehousePermits::with(['items', 'fromStoreHouse', 'toStoreHouse', 'storeHouse'])->findOrFail($id);
            $this->authorizeCreatorScope($permit->created_by);

            // الفترة المحاسبية: القيد العكسي يُرحَّل بتاريخ اليوم لا بتاريخ الإذن
            if ($block = $this->accountingPeriodBlock(now())) {
                DB::rollBack();

                return redirect()->back()->with('error', $block);
            }

            if ($permit->status !== 'apporod') {
                return response()->json(['success' => false, 'message' => 'هذا الإذن غير موافق عليه مسبقاً'], 400);
            }

            // 1. عكس الكميات في المخزن
            foreach ($permit->items as $item) {
                $productId = $item->product_id;
                $quantity = $item->quantity;

                if ($permit->permission_type == 1 || $permit->permission_type == 4) { // 🟢 إضافة أو رصيد افتتاحي -> نقوم بالخصم
                    $targetStoreHouseId = $permit->store_houses_id;
                    $storeHouseProduct = ProductDetails::where('store_house_id', $targetStoreHouseId)
                        ->where('product_id', $productId)
                        ->first();
                    if ($storeHouseProduct) {
                        $storeHouseProduct->quantity -= $quantity;
                        $storeHouseProduct->save();
                    }
                } elseif ($permit->permission_type == 2) { // 🔴 صرف -> نقوم بالإرجاع
                    $targetStoreHouseId = $permit->store_houses_id;
                    $storeHouseProduct = ProductDetails::where('store_house_id', $targetStoreHouseId)
                        ->where('product_id', $productId)
                        ->first();
                    if ($storeHouseProduct) {
                        $storeHouseProduct->quantity += $quantity;
                        $storeHouseProduct->save();
                    }
                } elseif ($permit->permission_type == 3) { // 🔵 تحويل
                    // إرجاع من المستودع المحول إليه إلى المحول منه
                    // to_store_houses_id -> نقوم بالخصم منه
                    // from_store_houses_id -> نقوم بالإرجاع إليه

                    $toStoreProduct = ProductDetails::where('store_house_id', $permit->to_store_houses_id)
                        ->where('product_id', $productId)->first();
                    if ($toStoreProduct) {
                        $toStoreProduct->quantity -= $quantity;
                        $toStoreProduct->save();
                    }

                    $fromStoreProduct = ProductDetails::where('store_house_id', $permit->from_store_houses_id)
                        ->where('product_id', $productId)->first();
                    if ($fromStoreProduct) {
                        $fromStoreProduct->quantity += $quantity;
                        $fromStoreProduct->save();
                    }
                }
            }

            // 2. عكس القيود المحاسبية
            $this->createReverseJournalEntries($permit);

            /*
                3. حالة الإذن بعد إلغاء الموافقة — تختلف بمصدره:

                **إذن تسوية جرد يُلغى نهائياً** (`rejec`) لا يُعاد معلَّقاً:
                الإذن وليدُ التسوية لا مستندَ إدخالٍ يدوي، فالتراجع عن
                اعتماده تراجعٌ عن التسوية نفسها — قيدُه عُكس وكمّيتُه
                أُرجعت، ويبقى في السجلّ ملغياً أثراً مقروءاً. وورقتُه تعود
                «مكتملة» فيُعاد تسويتُها متى شاء المستخدم **بأذونٍ جديدة
                بأرقامٍ جديدة** — لا بإحياء إذنٍ عُكس قيدُه.

                أمّا الإذن اليدوي فيعود «معلَّقاً» كما كان: كتبه مستخدمٌ
                بيده وقد يُراجَع ويُعتمد ثانية.
            */
            $isSettlementPermit = intval($permit->permission_source_id) === PermissionSources::INVENTORY_ADJUSTMENT;

            $permit->status = $isSettlementPermit ? 'rejec' : 'pending';
            $permit->save();

            if ($isSettlementPermit && $permit->reference_id) {
                InventoryAdjustment::whereKey($permit->reference_id)
                    ->where('status', 'adjusted')
                    ->update(['status' => 'completed', 'adjusted_at' => null]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'تم إلغاء الموافقة بنجاح وإرجاع الكميات وعكس القيود']);

        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'cancelApproval');
            DB::rollBack();
            Log::error('Cancel Approval Error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء إلغاء الموافقة: '.$e->getMessage()], 500);
        }
    }

    /**
     * إنشاء قيود عكسية عند رفض أو إلغاء إذن المخزن
     */
    private function createReverseJournalEntries($permit)
    {
        try {
            // البحث عن القيود المحاسبية المرتبطة بالإذن (بمختلف أنواعها المحتملة وعن طريق الـ id أو الـ batch_reference)
            $journalEntries = JournalEntry::where(function ($q) use ($permit) {
                $q->where('reference_id', $permit->id)
                    ->orWhere('batch_reference', 'WAREHOUSE_'.$permit->id);
            })
                ->whereIn('reference_type', [
                    ReferenceType::STOCK_ADJUSTMENT,
                    ReferenceType::APPROVED_WAREHOUSE,
                    ReferenceType::OPENING_BALANCE,
                ])
                // القيد الذي عُكس مرّةً لا يُعكس ثانية، والقيد العكسي نفسه لا يُعكس
                ->where(function ($q) {
                    $q->whereNull('is_reversed')->orWhere('is_reversed', false);
                })
                ->where('reference_number', 'not like', 'REVERSE\_%')
                ->get();

            foreach ($journalEntries as $entry) {
                // إنشاء قيد عكسي
                $reverseEntry = JournalEntry::create([
                    'reference_number' => 'REVERSE_'.$entry->reference_number.'_'.time(),
                    'date' => now(),
                    'description' => 'قيد عكسي لـ '.$entry->description,
                    'status' => 1, // معتمد
                    'currency' => $this->documentCurrency($permit),
                    'created_by_employee' => Auth::id(),
                    'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
                    'linking_type' => LinkingType::SINGLE,
                    'reference_id' => $permit->id,
                ]);

                // إنشاء تفاصيل القيد العكسي
                $entryDetails = JournalEntryDetail::where('journal_entry_id', $entry->id)->get();

                foreach ($entryDetails as $detail) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $reverseEntry->id,
                        'account_id' => $detail->account_id,
                        'description' => 'عكس - '.$detail->description,
                        'debit' => $detail->credit, // عكس القيم
                        'credit' => $detail->debit, // عكس القيم
                    ]);

                    // تحديث رصيد الحساب بشكل عكسي
                    $account = Account::find($detail->account_id);
                    if ($account) {
                        $account->balance += $detail->credit - $detail->debit;
                        $account->save();

                        // تحديث أرصدة الحسابات بشكل هرمي
                        Account::updateBalancesHierarchically($account->id);
                    }
                }

                // تمييز القيد الأصلي بأنه معكوس
                $entry->update(['is_reversed' => true]);
            }

        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'cancelApproval');
            Log::error('Error creating reverse journal entries: '.$e->getMessage());
            throw new \Exception('فشل في إنشاء القيود العكسية: '.$e->getMessage());
        }
    }

    /**
     * إنشاء قيد من حساب المشتريات إلى حساب المستودع
     */
    /**
     * يقفل ورقة الجرد متى اعتُمدت أذونها كلّها.
     *
     * الربط بـ`reference_id` لا بتفكيك نصّ رقم الإذن — الصيغة القديمة
     * `INV-<ورقة>-<بند>` كانت الرابط الوحيد، وتتكسّر بأوّل تغيير في الترقيم.
     */
    protected function closeAdjustmentIfSettled($wareHousePermit): void
    {
        if (intval($wareHousePermit->permission_source_id) !== PermissionSources::INVENTORY_ADJUSTMENT) {
            return;
        }

        $adjustmentId = $wareHousePermit->reference_id;

        if (! $adjustmentId) {
            return;
        }

        $stillPending = WarehousePermits::where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
            ->where('reference_id', $adjustmentId)
            ->whereNotIn('status', ['approved', 'apporod', 'rejected', 'rejec'])
            ->exists();

        if ($stillPending) {
            return;
        }

        InventoryAdjustment::whereKey($adjustmentId)->update([
            'status' => 'adjusted',
            'adjusted_at' => now(),
        ]);
    }

    protected function createApprovalJournalEntry($wareHousePermit)
    {
        try {
            // حساب الإجمالي من البنود
            $totalAmount = 0;
            foreach ($wareHousePermit->items as $item) {
                $totalAmount += ($item->quantity * floatval($item->unit_price ?? 0));
            }

            // إذا المبلغ صفر، لا داعي لإنشاء قيد
            if ($totalAmount == 0) {
                return ['success' => true, 'message' => 'لا يوجد مبلغ لإنشاء قيد'];
            }

            // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في «الحسابات
            // المحاسبية». كانت النسخة هنا تكتب `is_active` و`type` — وليس
            // أيٌّ منهما عموداً في جدول الحسابات، فيُسقطهما Eloquent بصمت
            $store = StoreHouse::find($wareHousePermit->store_houses_id);

            if (! $store) {
                return [
                    'success' => false,
                    'message' => 'المستودع غير موجود',
                ];
            }

            $storeAccount = $this->warehouseAccount($store->id, $store->name ?? null);

            // تحديد نوع القيد وفقاً لـ permission_source_id
            $permissionSourceId = intval($wareHousePermit->permission_source_id ?? 0);

            // مصادر الأذونات التي تمثل إضافة (وارد)
            $additionSources = [
                PermissionSources::WAREHOUSE_ADD,           // 1 - اذن اضافة مخزن - إضافة
                PermissionSources::PURCHASE_INVOICE,        // 7 - فاتورة شراء
                PermissionSources::INVENTORY_RECEIVED,      // 6 - ورقة جرد وارد
                PermissionSources::SALES_RETURN,            // 8 - مرتجع مبيعات          // 9 - إشعار دائن
                PermissionSources::PURCHASE_RETURN,         // 11 - مرتجع مشتريات
            ];

            // مصادر الأذونات التي تمثل صرف (منصرف)
            $disbursementSources = [
                PermissionSources::WAREHOUSE_DISBURSEMENT,  // 2 - اذن اضافة مخزن - صرف
                PermissionSources::INVENTORY_WITHDRAWN,     // 5 - ورقة جرد منصرف
                PermissionSources::SALES_INVOICE,           // 10 - فاتورة مبيعات
                PermissionSources::POS_SHIFT_OUTBOUND,      // 14 - POS Shift Outbound
                PermissionSources::MANUFACTURING_DISBURSEMENT, // 17 - أمر تصنيع لصرف المواد
            ];

            if ($permissionSourceId === PermissionSources::WAREHOUSE_OPENING_BALANCE) {
                /*
                    الرصيد الافتتاحي لم يكن مدعوماً هنا إطلاقاً، فيفشل اعتماد
                    كل إذن رصيد افتتاحي برسالة «نوع مصدر الإذن غير مدعوم».
                    طرفه المقابل حساب «الأرصدة الافتتاحية» لا حساب المشتريات:
                    البضاعة الافتتاحية لم تُشترَ في هذه الفترة.
                */
                $description = 'قيد رصيد افتتاحي للمخزون رقم '.$wareHousePermit->number;
                $debitAccountId = $storeAccount->id;
                $creditAccountId = $this->requireAccountingAccountId('opening_balance', SystemAccountIds::OPENING_BALANCE);

            } elseif ($permissionSourceId === PermissionSources::INVENTORY_ADJUSTMENT) {
                /*
                    تسوية الجرد (25) لم تكن في قائمة الوارد ولا المنصرف، فتسقط
                    إلى `else` وتُرفض برسالة «نوع مصدر الإذن غير مدعوم» — أي
                    أن **اعتماد أي إذن جردٍ كان يفشل**.

                    اتّجاهه من نوع الإذن لا من مصدره: النوع 1 زيادةُ جرد
                    (بضاعة وُجدت ولم تكن مسجّلة) والنوع 2 عجزُ جرد. وطرفه
                    المقابل حساب «تسوية المخزون» كما ضُبط في «الحسابات
                    المحاسبية» — وهو الحساب نفسه الذي كانت تستعمله شاشة
                    الجرد، فلا يختلف التوجيه باختلاف الطريق.
                */
                $adjustmentAccountId = $this->requireAccountingAccountId(
                    'inventory_adjustment',
                    SystemAccountIds::COST_OF_SALES
                );

                $isSurplus = intval($wareHousePermit->permission_type) === 1;

                $description = ($isSurplus ? 'قيد زيادة جرد رقم ' : 'قيد عجز جرد رقم ').$wareHousePermit->number;
                $debitAccountId = $isSurplus ? $storeAccount->id : $adjustmentAccountId;
                $creditAccountId = $isSurplus ? $adjustmentAccountId : $storeAccount->id;

            } elseif ($permissionSourceId === PermissionSources::WAREHOUSE_ADD) {
                /*
                    إذن الإضافة اليدوي ليس شراءً: لا مورّد ولا فاتورة خلفه،
                    فتحميله على «حساب المشتريات» يضخّم المشتريات بكمياتٍ لم
                    تُشترَ ويكسر مطابقتها مع فواتير الموردين.
                    طرفه المقابل «أطراف دائنة أخرى» — كما في الحركة اليدوية
                    على المخزون تماماً.
                */
                $description = 'قيد إضافة مخزنية يدوية رقم '.$wareHousePermit->number;
                $debitAccountId = $storeAccount->id;
                $creditAccountId = $this->requireAccountingAccountId('other_creditor_parties', SystemAccountIds::OTHER_CREDITOR_PARTIES);

            } elseif (in_array($permissionSourceId, $additionSources)) {
                // وارد من مستندٍ شرائي (فاتورة شراء، مرتجع مبيعات، جرد وارد…)
                $description = 'قيد إضافة بضاعة - '.PermissionSources::getName($permissionSourceId).' رقم '.$wareHousePermit->number;
                $debitAccountId = $storeAccount->id;
                // حساب المشتريات كما ضُبط في «الحسابات المحاسبية»
                $creditAccount = $this->requireAccountingAccount('purchases', SystemAccountIds::PURCHASES);
                $creditAccountId = $creditAccount->id;

            } elseif ($permissionSourceId === PermissionSources::WAREHOUSE_DISBURSEMENT) {
                /*
                    الصرف اليدوي ليس بيعاً: لا فاتورة ولا عميل خلفه، فتحميله
                    على «تكلفة البضاعة المباعة» يضخّم تكلفة المبيعات ببضاعةٍ
                    لم تُبَع ويُفسد هامش الربح. طرفه المقابل «أطراف مدينة
                    أخرى» — مقابلاً لإذن الإضافة اليدوي الذي يُرحَّل على
                    «أطراف دائنة أخرى».
                */
                $description = 'قيد صرف مخزني يدوي رقم '.$wareHousePermit->number;
                $debitAccountId = $this->requireAccountingAccountId('other_debtor_parties', SystemAccountIds::OTHER_DEBITOR_PARTIES);
                $creditAccountId = $storeAccount->id;

            } elseif (in_array($permissionSourceId, $disbursementSources)) {
                // إذن صرف (منصرف)
                $description = 'قيد صرف بضاعة - '.PermissionSources::getName($permissionSourceId).' رقم '.$wareHousePermit->number;
                // حساب تكلفة المبيعات كما ضُبط في «الحسابات المحاسبية»
                $debitAccount = $this->requireAccountingAccount('cost_of_sales', SystemAccountIds::COST_OF_SALES);
                $debitAccountId = $debitAccount->id;
                $creditAccountId = $storeAccount->id;

            } elseif ($permissionSourceId === PermissionSources::MANUAL_TRANSFER) {
                /*
                    التحويل بين مستودعين لا يغيّر إجمالي المخزون، لكنّ لكل
                    مستودع حساباً مستقلّاً في الشجرة — فبلا قيدٍ يبقى رصيد
                    الحسابين مخالفاً للكميات الفعليّة إلى الأبد.
                    القيد: حساب المستودع المستلِم مدين، والمُرسِل دائن.
                */
                $fromStore = StoreHouse::find($wareHousePermit->from_store_houses_id);
                $toStore = StoreHouse::find($wareHousePermit->to_store_houses_id);

                if (! $fromStore || ! $toStore) {
                    return ['success' => false, 'message' => 'مستودع التحويل (المصدر أو الهدف) غير موجود'];
                }

                $description = 'قيد تحويل مخزني من '.$fromStore->name.' إلى '.$toStore->name
                    .' — إذن رقم '.$wareHousePermit->number;

                $debitAccountId = $this->warehouseAccount($toStore->id, $toStore->name)->id;
                $creditAccountId = $this->warehouseAccount($fromStore->id, $fromStore->name)->id;

            } else {
                // مصادر غير مدعومة
                return [
                    'success' => false,
                    'message' => 'نوع مصدر الإذن غير مدعوم للقيود المحاسبية: '.$permissionSourceId.' ('.PermissionSources::getName($permissionSourceId).')',
                ];
            }            // التحقق النهائي من الحسابات
            if (! $debitAccountId || ! $creditAccountId) {
                return [
                    'success' => false,
                    'message' => 'فشل تحديد الحسابات المطلوبة. Debit: '.$debitAccountId.', Credit: '.$creditAccountId,
                ];
            }

            // إنشاء القيد المحاسبي
            $journalEntry = JournalEntry::create([
                'date' => $wareHousePermit->permission_date ?? now(),
                'description' => $description,
                'reference_type' => ReferenceType::APPROVED_WAREHOUSE,
                'batch_reference' => 'WAREHOUSE_'.$wareHousePermit->id,
                'reference_id' => $wareHousePermit->id,
                'status' => 1,
                'currency' => $this->documentCurrency($wareHousePermit),
                'created_by_employee' => Auth::id(),
            ]);

            // إنشاء تفاصيل القيد
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => intval($debitAccountId),
                'description' => $description,
                'debit' => floatval($totalAmount),
                'credit' => 0,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => intval($creditAccountId),
                'description' => $description,
                'debit' => 0,
                'credit' => floatval($totalAmount),
            ]);

            // تحديث الأرصدة
            $this->updateAccountBalances($permissionSourceId, $storeAccount, $totalAmount);

            // تحديث الأرصدة الهرمية
            Account::updateBalancesHierarchically($storeAccount->id);

            return [
                'success' => true,
                'message' => 'تم إنشاء القيد المحاسبي بنجاح',
                'journal_entry_id' => $journalEntry->id,
            ];

        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'cancelApproval');
            Log::error('خطأ في إنشاء القيد المحاسبي: '.$e->getMessage(), [
                'permit_id' => $wareHousePermit->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'فشل إنشاء القيد المحاسبي: '.$e->getMessage(),
            ];
        }
    }

    /**
     * تحديث أرصدة الحسابات بناءً على نوع العملية
     */
    private function updateAccountBalances($permissionSourceId, $storeAccount, $totalAmount)
    {
        // مصادر الأذونات التي تمثل إضافة (وارد)
        $additionSources = [
            PermissionSources::WAREHOUSE_ADD,
            PermissionSources::PURCHASE_INVOICE,
            PermissionSources::INVENTORY_RECEIVED,
            PermissionSources::SALES_RETURN,
            PermissionSources::PURCHASE_RETURN,
        ];

        // مصادر الأذونات التي تمثل صرف (منصرف)
        $disbursementSources = [
            PermissionSources::WAREHOUSE_DISBURSEMENT,
            PermissionSources::INVENTORY_WITHDRAWN,
            PermissionSources::SALES_INVOICE,
            PermissionSources::POS_SHIFT_OUTBOUND,
            PermissionSources::MANUFACTURING_DISBURSEMENT,
        ];

        if (in_array($permissionSourceId, $additionSources)) {
            // إضافة: زيادة رصيد المستودع، تقليل رصيد المشتريات
            $storeAccount->balance = floatval($storeAccount->balance) + floatval($totalAmount);
            $storeAccount->save();
            $storeAccount->refresh();

            $purchasesAccount = $this->accountingAccount('purchases', SystemAccountIds::PURCHASES);
            if ($purchasesAccount) {
                $purchasesAccount->balance = floatval($purchasesAccount->balance) - floatval($totalAmount);
                $purchasesAccount->save();
                $purchasesAccount->refresh();
                Account::updateBalancesHierarchically($purchasesAccount->id);
            }

        } elseif (in_array($permissionSourceId, $disbursementSources)) {
            // صرف: تقليل رصيد المستودع، زيادة رصيد تكلفة المبيعات
            $storeAccount->balance = floatval($storeAccount->balance) - floatval($totalAmount);
            $storeAccount->save();
            $storeAccount->refresh();

            $costAccount = $this->accountingAccount('cost_of_sales', SystemAccountIds::COST_OF_SALES);
            if ($costAccount) {
                $costAccount->balance = floatval($costAccount->balance) + floatval($totalAmount);
                $costAccount->save();
                $costAccount->refresh();
                Account::updateBalancesHierarchically($costAccount->id);
            }
        }
    }

    /**
     * إضافة منتج للمستودع
     */
    private function addToWarehouse($storeHouseId, $productId, $quantity, $item)
    {
        try {
            // البحث عن سجل المنتج في المستودع
            $productDetail = ProductDetails::where('store_house_id', $storeHouseId)
                ->where('product_id', $productId)
                ->first();

            if ($productDetail) {
                // إذا كان المنتج موجوداً، زيادة الكمية
                $productDetail->quantity += $quantity;
                $productDetail->save();
            } else {
                // إذا لم يكن موجوداً، إنشاء سجل جديد
                ProductDetails::create([
                    'product_id' => $productId,
                    'store_house_id' => $storeHouseId,
                    'quantity' => $quantity,
                    'unit_price' => floatval($item->unit_price ?? 0),
                ]);
            }

            return ['success' => true, 'message' => 'تم إضافة المنتج بنجاح'];
        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'cancelApproval');

            return ['success' => false, 'message' => 'خطأ في إضافة المنتج: '.$e->getMessage()];
        }
    }

    /**
     * صرف منتج من المستودع
     */
    private function removeFromWarehouse($storeHouseId, $productId, $quantity, $item)
    {
        try {
            // البحث عن سجل المنتج في المستودع
            $productDetail = ProductDetails::where('store_house_id', $storeHouseId)
                ->where('product_id', $productId)
                ->first();

            if (! $productDetail) {
                return ['success' => false, 'message' => 'المنتج غير موجود في هذا المستودع'];
            }

            if ($productDetail->quantity < $quantity) {
                return ['success' => false, 'message' => 'الكمية المطلوبة أكثر من الموجود في المستودع'];
            }

            // تقليل الكمية
            $productDetail->quantity -= $quantity;
            $productDetail->save();

            return ['success' => true, 'message' => 'تم صرف المنتج بنجاح'];
        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'cancelApproval');

            return ['success' => false, 'message' => 'خطأ في صرف المنتج: '.$e->getMessage()];
        }
    }

    /**
     * تحويل منتج بين مستودعين
     */
    private function transferBetweenWarehouses($fromStoreHouseId, $toStoreHouseId, $productId, $quantity, $item)
    {
        try {
            // البحث عن سجل المنتج في المستودع المصدر
            $fromProductDetail = ProductDetails::where('store_house_id', $fromStoreHouseId)
                ->where('product_id', $productId)
                ->first();

            if (! $fromProductDetail) {
                return ['success' => false, 'message' => 'المنتج غير موجود في المستودع المصدر'];
            }

            if ($fromProductDetail->quantity < $quantity) {
                return ['success' => false, 'message' => 'الكمية المطلوبة أكثر من الموجود في المستودع المصدر'];
            }

            // تقليل الكمية من المستودع المصدر
            $fromProductDetail->quantity -= $quantity;
            $fromProductDetail->save();

            // البحث عن سجل المنتج في المستودع الهدف
            $toProductDetail = ProductDetails::where('store_house_id', $toStoreHouseId)
                ->where('product_id', $productId)
                ->first();

            if ($toProductDetail) {
                // إذا كان المنتج موجوداً، زيادة الكمية
                $toProductDetail->quantity += $quantity;
                $toProductDetail->save();
            } else {
                // إذا لم يكن موجوداً، إنشاء سجل جديد
                ProductDetails::create([
                    'product_id' => $productId,
                    'store_house_id' => $toStoreHouseId,
                    'quantity' => $quantity,
                    'unit_price' => $fromProductDetail->unit_price,
                ]);
            }

            return ['success' => true, 'message' => 'تم تحويل المنتج بنجاح'];
        } catch (\Exception $e) {
            OperationLog::failure($e, 'warehouse_permit_log', 'cancelApproval');

            return ['success' => false, 'message' => 'خطأ في تحويل المنتج: '.$e->getMessage()];
        }
    }

    /**
     * تحديد اتجاه التغيير في الكمية (موجب للإضافة، سالب للصرف)
     */
    private function getQuantityDirection($permissionType)
    {
        // 1 = إضافة (موجب)
        // 2 = صرف (سالب)
        // 3 = تحويل (يتم التعامل معه بشكل منفصل)
        if ($permissionType == 1) {
            return 1; // إضافة
        } elseif ($permissionType == 2) {
            return -1; // صرف
        } else {
            return 0; // تحويل
        }
    }
}
