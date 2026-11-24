<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Constants\permissions\PermissionSources;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\StoreHouse;
use App\Models\WarehousePermitsProducts;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;

class DetailedMovementReportController extends ReportController
{
    /*
        الفترة المحاسبية: النطاقُ الافتراضي للتقرير هو السنةُ المالية الجارية
        لا مدًى مكتوبٌ في الشيفرة.
    */
    use RespectsAccountingPeriods;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.detailed_movement');
    }

    public function index(Request $request)
    {
        // جلب البيانات الأساسية للفلاتر — الأصناف المخزنية وحدها، لا رصيد للخدمات والرقميّات
        $products = Product::whereIn('type', ProductType::stockedValues())
            ->select('id', 'name', 'serial_number')->orderBy('name')->get();
        $categories = Category::all();
        $brands = Product::all();
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        // معالجة الفترة الزمنية
        $period = $request->input('period', '30');
        $dateRange = $this->getDateRange($period, $request);
        $startDate = $dateRange['from'];
        $endDate = $dateRange['to'];

        // جلب بيانات التقرير
        $reportData = $this->getMovementData($request, $startDate, $endDate);

        return view('stock::report.stock_report.detailed_movement_inventory',
            compact('products', 'categories', 'brands', 'warehouses', 'reportData', 'startDate', 'endDate', 'period'));
    }

    /**
     * معالجة نطاق التاريخ بناءً على الفترة المحددة
     */
    private function getDateRange($period, Request $request)
    {
        $today = now();

        /*
            الفترة المحاسبية تسبق كل شيء.

            قائمة المدى هنا كانت أعداد أيام — ٧، ٣٠، ٩٠ — لا صلة لها
            بالفترة التي تعمل المنشأة عليها. ووسيط `accounting.period`
            يكون قد حوّل `period_id` إلى تاريخين، فيكفي احترامُهما مهما
            كان المختار في قائمة الأيام.
        */
        /*
            **الفترة المحاسبية تسبق كل شيء — والنطاقُ الافتراضي منها.**

            كان الرجوعُ عند غياب التاريخين إلى «آخر N يوماً» (٧ أو ٣٠ أو ٩٠) —
            مدًى لا صلة له بالفترة التي تعمل المنشأة عليها، فيُقرأ تقريرُ
            الحركة على نافذةٍ لا توافق أيَّ إقفال.

            و`stockReportScope` هي القاعدةُ نفسُها في المالية: مدًى كتبه
            المستخدم يتقدّم، وإلّا فالسنةُ المالية الجارية، وإلّا — إن لم يكن
            ثمّة تقويم — فلا حصر.
        */
        $scope = $this->stockReportScope($request, 'start_date', 'end_date');

        if ($scope['mode'] !== 'all' && $scope['start'] && $scope['end']) {
            return [
                'from' => Carbon::parse($scope['start'])->startOfDay(),
                'to' => Carbon::parse($scope['end'])->endOfDay(),
            ];
        }

        /*
            لا تقويمَ معرَّفاً: يُعرض التاريخُ كلُّه بدل أن يُحصَر بمعيارٍ لا
            وجود له. والحدُّ الأدنى تاريخُ أوّل حركةٍ عملياً.
        */
        if ($scope['mode'] === 'all') {
            return [
                'from' => Carbon::parse('1970-01-01')->startOfDay(),
                'to' => $today->copy()->endOfDay(),
            ];
        }

        // إذا كانت فترة مخصصة، استخدم التواريخ المدخلة
        if ($period === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            return [
                'from' => Carbon::parse($request->start_date)->startOfDay(),
                'to' => Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        // الفترات المحددة مسبقاً
        $periodDays = is_numeric($period) ? (int) $period : 30;

        // إذا كان يوم واحد (اليوم)، لا نطرح أيام
        if ($periodDays == 1) {
            return [
                'from' => $today->copy()->startOfDay(),
                'to' => $today->copy()->endOfDay(),
            ];
        }

        return [
            'from' => $today->copy()->subDays($periodDays)->startOfDay(),
            'to' => $today->copy()->endOfDay(),
        ];
    }

    /**
     * جلب بيانات الحركة التفصيلية
     */
    private function getMovementData(Request $request, $startDate, $endDate)
    {
        try {
            // 1. حساب الرصيد الافتتاحي ما قبل الفترة
            $openingBalance = $this->calculateOpeningBalance($request, $startDate);

            // 2. بناء استعلام الحركات للفترة المحددة
            $query = WarehousePermitsProducts::query()
                ->leftJoin('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->leftJoin('products', 'warehouse_permits_products.product_id', '=', 'products.id')
                ->leftJoin('store_houses', 'warehouse_permits.store_houses_id', '=', 'store_houses.id')
                ->leftJoin('store_houses as from_stores', 'warehouse_permits.from_store_houses_id', '=', 'from_stores.id')
                ->leftJoin('store_houses as to_stores', 'warehouse_permits.to_store_houses_id', '=', 'to_stores.id')
                ->leftJoin('permission_sources', 'warehouse_permits.permission_source_id', '=', 'permission_sources.id')
                ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
                ->select(
                    'warehouse_permits_products.*',
                    'warehouse_permits.id as permit_id',
                    'warehouse_permits.number as permit_number',
                    'warehouse_permits.details as permit_details',
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
                    'users.email as user_email',
                    'products.name as product_name',
                    'products.serial_number as product_code',
                    'products.purchase_price as avg_cost',
                    'products.sale_price as selling_price',
                    'products.category_id as product_category_id'
                )
                ->whereNotNull('warehouse_permits_products.quantity')
                ->where('warehouse_permits_products.quantity', '!=', 0);

            // نطاق البيانات: منشئو الإذن ضمن ما يسمح به نطاق المستخدم
            $this->scopeToCreator($query, 'warehouse_permits.created_by');

            // تطبيق فلتر التاريخ
            $query->whereBetween('warehouse_permits.permission_date', [$startDate, $endDate]);

            // تطبيق الفلاتر
            $this->applyMovementFilters($query, $request);

            // تطبيق الترتيب (مطابق لكشف الحساب)
            $query->orderByRaw('CASE WHEN warehouse_permits.permission_source_id = '.PermissionSources::WAREHOUSE_OPENING_BALANCE.' THEN 0 ELSE 1 END ASC')
                ->orderByRaw('DATE(warehouse_permits.permission_date) ASC')
                ->orderBy('warehouse_permits.created_at', 'asc')
                ->orderBy('warehouse_permits.id', 'asc');

            // جلب البيانات
            $movements = $query->get();

            // معالجة البيانات للعرض وحساب الرصيد المتتابع
            $processedMovements = $this->processMovementData($movements, $request, $openingBalance);

            // حساب الإجماليات
            $totals = $this->calculateMovementTotals($processedMovements);

            // إعداد بيانات الرسم البياني
            $chartData = $this->prepareMovementChartData($processedMovements, $request);

            return [
                'movements' => array_values($processedMovements),
                'totals' => $totals,
                'chart_data' => $chartData,
            ];

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'getMovementData');

            return [
                'movements' => [],
                'totals' => ['total_movements' => 0, 'total_incoming' => 0, 'total_outgoing' => 0, 'total_products' => 0],
                'chart_data' => ['labels' => [], 'datasets' => []],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * حساب الرصيد الافتتاحي (مطابق لكشف الحساب)
     */
    private function calculateOpeningBalance(Request $request, $beforeDate)
    {
        $query = WarehousePermitsProducts::query()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->join('products', 'warehouse_permits_products.product_id', '=', 'products.id')
            ->where('warehouse_permits.permission_date', '<', $beforeDate)
            ->whereNotNull('warehouse_permits_products.quantity')
            ->where('warehouse_permits_products.quantity', '!=', 0);

        // نطاق البيانات: الرصيد الافتتاحي يُحسب من نفس الحركات المسموح رؤيتها
        $this->scopeToCreator($query, 'warehouse_permits.created_by');

        // تطبيق نفس الفلاتر المستخدمة في التقرير
        $this->applyMovementFilters($query, $request);

        // حساب الرصيد
        // نستخدم منطق بسيط: الكمية الموجبة - الكمية السالبة، مع مراعاة المنطق الخاص بالتحويلات إذا لزم
        // ولكن بما أن applyMovementFilters يعالج التحويلات، فالجمع المباشر للكمية يكفي إذا كانت الإشارات صحيحة في قاعدة البيانات
        // في WarehouseStatementController يتم حسابها عبر تكرار وحساب isIncoming.
        // للدقة القصوى، سنستخدم نفس التكرار.

        $movements = $query->get();
        $balance = 0;
        $warehouseId = $request->warehouse; // Get warehouse ID from request for isIncomingMovement

        foreach ($movements as $movement) {
            $isIncoming = $this->isIncomingMovement($movement, $warehouseId);
            $quantity = abs($movement->quantity);

            if ($isIncoming) {
                $balance += $quantity;
            } else {
                $balance -= $quantity;
            }
        }

        return $balance;
    }

    /**
     * تطبيق فلاتر الحركة التفصيلية
     */
    private function applyMovementFilters($query, Request $request)
    {
        // فلتر التصنيف
        if ($request->filled('category')) {
            $query->where('products.category_id', $request->category);
        }

        // فلتر العلامة التجارية
        if ($request->filled('brand')) {
            $query->where('products.brand', $request->brand);
        }

        // فلتر المستودع - مع التعامل الصحيح مع التحويلات
        if ($request->filled('warehouse')) {
            $warehouseId = $request->warehouse;
            $query->where(function ($q) use ($warehouseId) {
                $q->where(function ($sub) use ($warehouseId) {
                    // الحركات العادية (إضافة/صرف)
                    $sub->whereNotIn('warehouse_permits.permission_source_id', [PermissionSources::MANUAL_TRANSFER])
                        ->where('warehouse_permits.store_houses_id', $warehouseId);
                })
                    ->orWhere(function ($sub) use ($warehouseId) {
                        // التحويل - المستودع هو المصدر (الخارج)
                        $sub->where('warehouse_permits.permission_source_id', PermissionSources::MANUAL_TRANSFER)
                            ->where('warehouse_permits.from_store_houses_id', $warehouseId)
                            ->where('warehouse_permits_products.quantity', '<', 0);
                    })
                    ->orWhere(function ($sub) use ($warehouseId) {
                        // التحويل - المستودع هو الوجهة (الوارد)
                        $sub->where('warehouse_permits.permission_source_id', PermissionSources::MANUAL_TRANSFER)
                            ->where('warehouse_permits.to_store_houses_id', $warehouseId)
                            ->where('warehouse_permits_products.quantity', '>', 0);
                    });
            });
        }

        // فلتر نوع الإذن باستخدام PermissionSources
        if ($request->filled('type')) {
            $typeMap = [
                'purchase' => [PermissionSources::PURCHASE_INVOICE],
                'sale' => [PermissionSources::SALES_INVOICE],
                'purchase_return' => [PermissionSources::PURCHASE_RETURN],
                'sale_return' => [PermissionSources::SALES_RETURN],
                'transfer' => [PermissionSources::MANUAL_TRANSFER, PermissionSources::POS_SHIFT_INBOUND, PermissionSources::POS_SHIFT_OUTBOUND],
                'adjustment' => [PermissionSources::INVENTORY_WITHDRAWN, PermissionSources::INVENTORY_RECEIVED, PermissionSources::INVENTORY_GENERAL],
                'manufacturing' => [PermissionSources::MANUFACTURING_MAIN_PRODUCT, PermissionSources::MANUFACTURING_DAMAGED_MATERIALS, PermissionSources::MANUFACTURING_RETURNS, PermissionSources::MANUFACTURING_DISBURSEMENT],
            ];

            if (isset($typeMap[$request->type])) {
                $query->whereIn('warehouse_permits.permission_source_id', $typeMap[$request->type]);
            } elseif (is_numeric($request->type)) {
                $query->where('warehouse_permits.permission_source_id', $request->type);
            }
        }

        // فلتر التاريخ
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = $request->start_date.' 00:00:00';
            $endDate = $request->end_date.' 23:59:59';
            $query->whereBetween('warehouse_permits.permission_date', [$startDate, $endDate]);
        }

        // البحث النصي
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.serial_number', 'like', "%{$search}%")
                    ->orWhere('warehouse_permits.number', 'like', "%{$search}%")
                    ->orWhere('warehouse_permits.details', 'like', "%{$search}%");
            });
        }

        // فلتر إظهار الوارد/المنصرف
        if ($request->boolean('show_incoming') && ! $request->boolean('show_outgoing')) {
            $query->where('warehouse_permits_products.quantity', '>', 0);
        } elseif ($request->boolean('show_outgoing') && ! $request->boolean('show_incoming')) {
            $query->where('warehouse_permits_products.quantity', '<', 0);
        }
    }

    /**
     * تطبيق الترتيب - من الأقدم للأحدث مع الرصيد الافتتاحي أولاً
     */
    private function applyMovementSorting($query, Request $request)
    {
        $query->orderByRaw('CASE WHEN warehouse_permits.permission_source_id = '.PermissionSources::WAREHOUSE_OPENING_BALANCE.' THEN 0 ELSE 1 END ASC')
            ->orderByRaw('DATE(warehouse_permits.permission_date) ASC')
            ->orderBy('warehouse_permits.created_at', 'asc')
            ->orderBy('warehouse_permits.id', 'asc');
    }

    /**
     * معالجة البيانات لتطابق منطق كشف الحساب
     */
    private function processMovementData($movements, Request $request, $openingBalance)
    {
        $processedData = [];
        $runningBalance = $openingBalance;
        $warehouseId = $request->warehouse;

        // إضافة صف الرصيد الافتتاحي
        if ($openingBalance != 0) {
            $processedData[] = [
                'id' => 0,
                'date' => null,
                'permit_number' => '-',
                'product_code' => '-',
                'product_name' => 'الرصيد الافتتاحي',
                'avg_cost' => 0,
                'selling_price' => 0,
                'quantity' => 0,
                'unit_price' => 0,
                'total_price' => 0,
                'direction' => 'balance',
                'warehouse_display_html' => '-',
                'type_display_html' => '<span class="badge badge-secondary">رصيد افتتاحي</span>',
                'notes' => '-',
                'user_name' => '-',
                'stock_before' => 0,
                'stock_after' => $runningBalance,
                'stock_display' => $runningBalance, // للعرض المباشر
                'is_transfer' => false,
            ];
        }

        foreach ($movements as $movement) {
            $isIncoming = $this->isIncomingMovement($movement, $warehouseId);
            $quantity = abs($movement->quantity);

            $stockBefore = $runningBalance;

            if ($isIncoming) {
                $runningBalance += $quantity;
                $direction = 'in';
            } else {
                $runningBalance -= $quantity;
                $direction = 'out';
            }

            $stockAfter = $runningBalance;

            // تجهيز العرض
            $typeName = PermissionSources::getName($movement->permission_source_id);
            $typeClass = $isIncoming ? 'success' : 'danger';
            $type_display = '<span class="badge badge-'.$typeClass.'">'.htmlspecialchars($typeName).'</span>';

            $source_badge = ''; // يمكن إضافته إذا لزم
            $type_display_html = $type_display.$source_badge.
                ' <span class="badge badge-light text-dark border border-secondary ml-1">#'.$movement->permit_number.'</span>';

            $warehouse_display = '<span class="badge badge-light">'.htmlspecialchars($movement->store_name ?? 'غير محدد').'</span>';

            // معالجة خاصة للتحويلات إذا لم يكن هناك مستودع محدد (عرض من -> إلى)
            if ($movement->permission_source_id == PermissionSources::MANUAL_TRANSFER && ! $warehouseId) {
                $warehouse_display = '<span class="badge badge-danger">من: '.($movement->from_store_name ?? '').'</span> -> <span class="badge badge-success">إلى: '.($movement->to_store_name ?? '').'</span>';
            }

            $processedData[] = [
                'id' => $movement->id,
                'date' => $movement->permission_date,
                'permit_number' => $movement->permit_number,
                'product_code' => $movement->product_code,
                'product_name' => $movement->product_name,
                'avg_cost' => $movement->avg_cost ?? 0,
                'selling_price' => $movement->selling_price ?? 0,
                'quantity' => $quantity,
                'unit_price' => $movement->unit_price ?? 0,
                'total_price' => ($movement->unit_price ?? 0) * $quantity,
                'direction' => $direction,
                'warehouse_display_html' => $warehouse_display,
                'type_display_html' => $type_display_html,
                'notes' => $movement->permit_details ?? '',
                'user_name' => $movement->user_name,
                'quantity_signed' => $isIncoming ? $quantity : -$quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'stock_display' => $stockAfter, // للعرض المباشر
                'is_transfer' => $movement->permission_source_id == PermissionSources::MANUAL_TRANSFER,
            ];
        }

        return $processedData;
    }

    /**
     * منطق تحديد الوارد/المنصرف (مطابق لكشف الحساب)
     */
    private function isIncomingMovement($movement, $warehouseId = null)
    {
        $sourceId = $movement->permission_source_id;

        // التحويلات (نقل مخزني)
        if ($sourceId == PermissionSources::MANUAL_TRANSFER) {
            // إذا تم تحديد مستودع، نحدد بناءً عليه
            if ($warehouseId) {
                return $movement->to_store_houses_id == $warehouseId;
            }

            // إذا لم يتم تحديد مستودع (عرض عام)، نعتبره "وارد" إذا كان السجل الوارد (الكمية موجبة في DB)
            // في جدول warehouse_permits_products، عادةً هناك سجلان للتحويل: سالب للمصدر وموجب للوجهة.
            return $movement->quantity > 0;
        }

        // قائمة الوارد
        $incomingTypes = [
            PermissionSources::SALES_RETURN,
            PermissionSources::SALES_CREDIT_NOTE,
            PermissionSources::WAREHOUSE_ADD,
            PermissionSources::PURCHASE_INVOICE,
            PermissionSources::INVENTORY_RECEIVED,
            PermissionSources::WAREHOUSE_OPENING_BALANCE,
            PermissionSources::MANUFACTURING_MAIN_PRODUCT,
            PermissionSources::MANUFACTURING_RETURNS,
            PermissionSources::POS_SHIFT_INBOUND,
            PermissionSources::PRODUCT_EXCHANGE_RETURN, // تمت إضافته
        ];

        return in_array($sourceId, $incomingTypes);
    }

    private function calculateMovementTotals($movements)
    {
        $totals = [
            'total_movements' => count($movements),
            'total_incoming' => 0,
            'total_outgoing' => 0,
            'total_products' => 0,
        ];

        $uniqueProducts = [];

        foreach ($movements as $m) {
            // تجاهل الرصيد الافتتاحي في مجاميع الحركة
            if (($m['direction'] ?? '') === 'balance') {
                continue;
            }

            if ($m['direction'] == 'in') {
                $totals['total_incoming'] += $m['quantity'];
            } else {
                $totals['total_outgoing'] += $m['quantity'];
            }
            if (isset($m['product_code']) && $m['product_code'] !== '-') {
                $uniqueProducts[$m['product_code']] = true;
            }
        }
        $totals['total_products'] = count($uniqueProducts);

        return $totals;
    }

    private function prepareMovementChartData($movements, $request)
    {
        // 1. جلب مستودعات نطاق المستخدم
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        // 2. حساب إجمالي الحركات لكل مستودع من البيانات المعالجة
        $warehouseMovements = [];
        foreach ($warehouses as $wh) {
            $warehouseMovements[$wh->id] = [
                'name' => $wh->name,
                'total_movements' => 0,
                'current_stock' => 0,
            ];
        }

        // حساب الحركات من البيانات
        foreach ($movements as $m) {
            // استخراج معرف المستودع من HTML أو من البيانات الخام
            // نحتاج حقل warehouse_id في البيانات المعالجة، لكن حالياً نستخدم الاسم
            $warehouseName = strip_tags($m['warehouse_display_html'] ?? '');

            // البحث عن المستودع بالاسم
            foreach ($warehouseMovements as $whId => &$data) {
                if (strpos($warehouseName, $data['name']) !== false) {
                    $data['total_movements'] += abs($m['quantity'] ?? 0);
                    break;
                }
            }
        }

        // 3. جلب الرصيد الحالي لكل مستودع من ProductDetails
        $currentStocks = ProductDetails::select('store_house_id')
            ->selectRaw('SUM(quantity) as total_qty')
            ->groupBy('store_house_id')
            ->pluck('total_qty', 'store_house_id')
            ->toArray();

        foreach ($warehouseMovements as $whId => &$data) {
            $data['current_stock'] = $currentStocks[$whId] ?? 0;
        }

        // 4. ترتيب المستودعات حسب الحركات (الأكثر أولاً)
        uasort($warehouseMovements, function ($a, $b) {
            return $b['total_movements'] - $a['total_movements'];
        });

        // أخذ أفضل 10 مستودعات
        $top = array_slice($warehouseMovements, 0, 10, true);

        // 5. إعداد البيانات للرسم البياني
        $labels = [];
        $movementData = [];
        $stockData = [];

        foreach ($top as $data) {
            $labels[] = $data['name'];
            $movementData[] = $data['total_movements'];
            $stockData[] = $data['current_stock'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'إجمالي الحركات',
                    'data' => $movementData,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.7)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'الرصيد الحالي',
                    'data' => $stockData,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    private function getPermissionTypeName($permissionType)
    {
        $typeLabels = [
            1 => 'إدخال (وارد)',
            2 => 'إخراج (منصرف)',
            3 => 'نقل بين مستودعات',
            13 => 'نقل بين مستودعات',
            10 => 'فاتورة بيع',
        ];

        return $typeLabels[$permissionType] ?? 'حركة #'.$permissionType;
    }
}
