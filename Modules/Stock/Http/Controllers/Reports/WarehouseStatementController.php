<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Constants\permissions\PermissionSources;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\CreditNotification;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductExchangeItem;
use App\Models\Store\Brand;
use App\Models\StoreHouse;
use App\Models\WarehousePermitsProducts;
use App\Support\OperationLog;
use Illuminate\Http\Request;

class WarehouseStatementController extends ReportController
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.warehouse_statement', ['only' => ['index', 'getData']]);
        $this->middleware('permission.role:stock.reports.employee_statement', ['only' => ['employeeStatement']]);
    }

    /**
     * عرض صفحة كشف حساب المستودع
     */
    public function index(Request $request)
    {
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();
        $categories = Category::all();
        $brands = Brand::all();
        // الأصناف المخزنية وحدها — الخدمات والرقميّات لا رصيد لها
        $products = Product::whereIn('type', ProductType::stockedValues())
            ->select('id', 'name', 'serial_number')->get();
        $permissionSources = PermissionSources::getAll();

        // إذا لم يتم تحديد مستودع، اختر أول مستودع تلقائياً
        $warehouseId = $request->input('warehouse');
        if (! $warehouseId && $warehouses->count() > 0) {
            $warehouseId = $warehouses->first()->id;
            // دمج المستودع الافتراضي مع الطلب
            $request->merge(['warehouse' => $warehouseId]);
        }

        // التواريخ الافتراضية = الفترة المحاسبية الجارية من الإعدادات
        $reportRange = $this->applyDefaultReportDates($request);

        $warehouse = null;
        $statement = null;

        if ($warehouseId) {
            $warehouse = StoreHouse::find($warehouseId);
            $statement = $this->generateStatement($request);
        }

        return view('stock::report.stock_report.warehouse_statement', compact(
            'reportRange',
            'warehouses',
            'categories',
            'brands',
            'products',
            'warehouse',
            'statement',
            'permissionSources'
        ));
    }

    /**
     * جلب بيانات كشف الحساب عبر AJAX
     */
    public function getData(Request $request)
    {
        try {
            $statement = $this->generateStatement($request);

            return response()->json([
                'success' => true,
                'statement' => $statement,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'getData');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء كشف الحساب
     */
    private function generateStatement(Request $request)
    {
        $warehouseId = $request->input('warehouse');
        $productId = $request->input('product');
        $categoryId = $request->input('category');
        $brand = $request->input('brand');
        // النطاقُ المطبَّق: مدًى مكتوبٌ، وإلا الفترةُ المختارة من القائمة —
        // كان الحقلان الخامّان وحدهما، فاختيارُ فترةٍ بلا تاريخين لا أثر له
        $scope = $this->stockReportScope($request, 'start_date', 'end_date');
        $startDate = $scope['start'];
        $endDate = $scope['end'];
        $permissionSourceId = $request->input('permission_source');

        if (! $warehouseId) {
            return null;
        }

        $warehouseIds = $this->allowedWarehouseIds();
        if ($warehouseIds !== null && ! in_array((int) $warehouseId, $warehouseIds, true)) {
            return null;
        }

        $warehouse = StoreHouse::find($warehouseId);

        // تحديد المستودعات الرئيسية (لاستخدامها في logic التالف)
        $mainWarehouseIds = StoreHouse::where('major', 1)->pluck('id')->toArray();

        // بناء الاستعلام
        $query = WarehousePermitsProducts::query()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->join('products', 'warehouse_permits_products.product_id', '=', 'products.id')
            ->leftJoin('permission_sources', 'warehouse_permits.permission_source_id', '=', 'permission_sources.id')
            ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
            ->leftJoin('clients', 'warehouse_permits.client_id', '=', 'clients.id')
            ->select(
                'warehouse_permits_products.*',
                'warehouse_permits.id as permit_id',
                'warehouse_permits.number as permit_number',
                'warehouse_permits.permission_date',
                'warehouse_permits.created_at as permit_created_at',
                'warehouse_permits.permission_source_id',
                'warehouse_permits.permission_type',
                'warehouse_permits.reference_id',
                'warehouse_permits.reference_type',
                'warehouse_permits.store_houses_id',
                'warehouse_permits.from_store_houses_id',
                'warehouse_permits.to_store_houses_id',
                'warehouse_permits.details as permit_details',
                'warehouse_permits.client_id',
                'products.name as product_name',
                'products.serial_number as product_code',
                'permission_sources.name as source_name',
                'users.name as user_name',
                'clients.trade_name as client_trade_name',
                'clients.code as client_code'
            )
            ->whereNotNull('warehouse_permits_products.quantity')
            ->where('warehouse_permits_products.quantity', '!=', 0)
            // عرض الأذونات الموافق عليها أو المكتملة فقط لضمان دقة المخزون الفعلي المعتمد
            ->whereIn('warehouse_permits.status', ['approved', 'apporod', 'delivered', 'done']);

        $query->where(function ($q) use ($warehouseId) {
            $q->where(function ($sub) use ($warehouseId) {
                // الحركات العادية: المستودع هو المستودع الأساسي للإذن وليس هناك تحويل (المستودع المصدر والجهة فارغين أو أحدهما هو نفسه)
                // في الحالات العادية، نأخذ السجل كما هو
                $sub->where('warehouse_permits.store_houses_id', $warehouseId)
                    ->whereNull('warehouse_permits.from_store_houses_id')
                    ->whereNull('warehouse_permits.to_store_houses_id');
            })
                ->orWhere(function ($sub) use ($warehouseId) {
                    // التحويل - المستودع هو المصدر (الخارج): نختار السجل الذي كميته سالبة فقط
                    $sub->where('warehouse_permits.from_store_houses_id', $warehouseId)
                        ->where('warehouse_permits_products.quantity', '<', 0);
                })
                ->orWhere(function ($sub) use ($warehouseId) {
                    // التحويل - المستودع هو الوجهة (الوارد): نختار السجل الذي كميته موجبة فقط
                    $sub->where('warehouse_permits.to_store_houses_id', $warehouseId)
                        ->where('warehouse_permits_products.quantity', '>', 0);
                })
                ->orWhere(function ($sub) use ($warehouseId) {
                    // حالة خاصة: إذا كان الإذن يحمل معرف المستودع ولكن لم يتم تصنيفه كتحويل (من/إلى فارغين)
                    // ولكن هناك سورس يربطه بعمليات أخرى - نضمن عدم ضياع أي حركة
                    $sub->where('warehouse_permits.store_houses_id', $warehouseId)
                        ->where(function ($qq) {
                            $qq->whereNull('warehouse_permits.from_store_houses_id')
                                ->whereNull('warehouse_permits.to_store_houses_id');
                        });
                });
        });

        // فلتر المنتج
        if ($productId) {
            $query->where('warehouse_permits_products.product_id', $productId);
        }

        // فلتر الفئة
        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        // فلتر العلامة التجارية
        if ($brand) {
            $query->where('products.brand_id', $brand);
        }

        // فلتر مصدر الإذن
        if ($permissionSourceId) {
            $query->where('warehouse_permits.permission_source_id', $permissionSourceId);
        }

        // فلتر التاريخ
        if ($startDate && $endDate) {
            $query->whereBetween('warehouse_permits.permission_date', [
                $startDate.' 00:00:00',
                $endDate.' 23:59:59',
            ]);
        } elseif ($startDate) {
            $query->where('warehouse_permits.permission_date', '>=', $startDate.' 00:00:00');
        } elseif ($endDate) {
            $query->where('warehouse_permits.permission_date', '<=', $endDate.' 23:59:59');
        }

        // ترتيب: الرصيد الافتتاحي أولاً، ثم حسب تاريخ الإذن (اليوم فقط)، ثم وقت الإنشاء الفعلي لضمان الترتيب الصحيح
        $query->orderByRaw('CASE WHEN warehouse_permits.permission_source_id = '.PermissionSources::WAREHOUSE_OPENING_BALANCE.' THEN 0 ELSE 1 END ASC')
            ->orderByRaw('DATE(warehouse_permits.permission_date) ASC')
            ->orderBy('warehouse_permits.created_at', 'asc')
            ->orderBy('warehouse_permits.id', 'asc')
            // بنود الإذن الواحد بترتيب إدخالها — بلا هذا يتبع ترتيبُها خطّةَ الاستعلام فيتبدّل الرصيد المتتابع
            ->orderBy('warehouse_permits_products.id', 'asc');

        $movements = $query->get();

        // حساب الرصيد الافتتاحي (الكمية قبل الفترة)
        $openingBalance = ['total' => 0, 'good' => 0, 'damaged' => 0];
        if ($startDate) {
            $openingBalance = $this->calculateOpeningBalance($warehouseId, $productId, $categoryId, $brand, $startDate, $permissionSourceId);
        }

        // معالجة الحركات وحساب الرصيد المتتابع
        $processedMovements = [];
        $runningBalance = $openingBalance['total'];
        $runningGoodBalance = $openingBalance['good'];
        $runningDamagedBalance = $openingBalance['damaged'];

        $totalIncoming = 0;
        $totalOutgoing = 0;

        $totalDamagedIncoming = 0;
        $totalDamagedOutgoing = 0;

        foreach ($movements as $movement) {
            $balanceBefore = $runningBalance;
            $isIncoming = $this->isIncomingMovement($movement, $warehouseId);
            $quantity = abs($movement->quantity);

            // تحديد هل الحركة تخص التالف أم السليم
            $isDamaged = $this->isDamagedMovement($movement, $mainWarehouseIds);

            // جلب بيانات العميل من الإذن أولاً
            $clientTradeName = $movement->client_trade_name;
            $clientCode = $movement->client_code;

            // إذا لم نجد العميل من الإذن مباشرة، ابحث من خلال reference
            if (! $clientTradeName && ! $clientCode && $movement->reference_id && $movement->reference_type) {
                // محاولة جلب العميل من الفاتورة
                if ($movement->reference_type === 'Invoice') {
                    $invoice = Invoice::find($movement->reference_id);
                    if ($invoice && $invoice->client) {
                        $clientTradeName = $invoice->client->trade_name;
                        $clientCode = $invoice->client->code;
                    }
                }
                // محاولة جلب العميل من مرتجع المبيعات
                elseif ($movement->reference_type === 'SalesReturn') {
                    $salesReturn = Invoice::find($movement->reference_id);
                    if ($salesReturn && $salesReturn->client) {
                        $clientTradeName = $salesReturn->client->trade_name;
                        $clientCode = $salesReturn->client->code;
                    }
                }
                // محاولة جلب العميل من الإشعار الدائن
                elseif ($movement->reference_type === 'CreditNote') {
                    $creditNote = CreditNotification::find($movement->reference_id);
                    if ($creditNote && $creditNote->client) {
                        $clientTradeName = $creditNote->client->trade_name;
                        $clientCode = $creditNote->client->code;
                    }
                }
            }

            // إذا لم نجد العميل بعد، حاول من خلال آخر فاتورة مبيعات
            if (! $clientTradeName && ! $clientCode) {
                $lastInvoice = Invoice::where('type', 'sales')
                    ->whereHas('items', function ($q) use ($movement) {
                        $q->where('product_id', $movement->product_id);
                    })
                    ->where('invoice_date', '<=', $movement->permission_date)
                    ->orderBy('invoice_date', 'desc')
                    ->first();

                if ($lastInvoice && $lastInvoice->client) {
                    $clientTradeName = $lastInvoice->client->trade_name;
                    $clientCode = $lastInvoice->client->code;
                }
            }

            $movementGood = 0;
            $movementDamaged = 0;

            if ($isIncoming) {
                $runningBalance += $quantity;
                $totalIncoming += $quantity;
                if ($isDamaged) {
                    $runningDamagedBalance += $quantity;
                    $totalDamagedIncoming += $quantity;
                } else {
                    $runningGoodBalance += $quantity;
                }
            } else {
                $runningBalance -= $quantity;
                $totalOutgoing += $quantity;

                // خصم مع مراعاة الأولوية (التالف أولاً ثم السليم)
                $deduction = $this->applyPrioritizedDeduction($quantity, $runningGoodBalance, $runningDamagedBalance, $isDamaged);
                $runningGoodBalance = $deduction['good'];
                $runningDamagedBalance = $deduction['damaged'];

                if ($isDamaged) {
                    $totalDamagedOutgoing += $deduction['deducted_from_damaged'];
                }
            }

            // جلب تفاصيل التبديل (السبب) إذا وجد
            $exchangeReason = null;
            if (in_array($movement->permission_source_id, [PermissionSources::PRODUCT_EXCHANGE, PermissionSources::PRODUCT_EXCHANGE_RETURN])) {
                $exchangeItem = ProductExchangeItem::whereHas('productExchange', function ($q) use ($movement) {
                    $q->where('id', $movement->reference_id);
                })
                    ->where('product_id', $movement->product_id)
                    ->first();

                if ($exchangeItem) {
                    $exchangeReason = $exchangeItem->reason_text;
                }
            }

            // جلب معلومات التحويل بين المستودعات
            $transferInfo = null;
            if ($movement->permission_source_id == PermissionSources::MANUAL_TRANSFER) {
                $fromWarehouse = StoreHouse::find($movement->from_store_houses_id);
                $toWarehouse = StoreHouse::find($movement->to_store_houses_id);
                $transferInfo = [
                    'from' => $fromWarehouse ? $fromWarehouse->name : 'غير محدد',
                    'to' => $toWarehouse ? $toWarehouse->name : 'غير محدد',
                ];
            }

            $movementData = [
                'id' => $movement->permit_id, // Assuming permit_id is the correct ID for the movement record
                'date' => $movement->permission_date,
                'type' => $this->getMovementType($movement),
                'reference' => '#'.$movement->permit_number,
                'product_name' => $movement->product_name,
                'product_code' => $movement->product_code,
                'quantity' => $quantity,
                'incoming' => $isIncoming ? $quantity : 0,
                'outgoing' => ! $isIncoming ? $quantity : 0,
                'balance_before' => $balanceBefore,
                'balance_after' => $runningBalance,
                'balance' => $runningBalance,
                'good_balance' => $runningGoodBalance,
                'damaged_balance' => $runningDamagedBalance,
                'is_damaged' => $isDamaged,
                'exchange_reason' => $exchangeReason,
                'user_name' => $movement->user_name,
                'notes' => $movement->permit_details,
                'client_trade_name' => $clientTradeName,
                'client_code' => $clientCode,
                'transfer_info' => $movement->permission_source_id == PermissionSources::MANUAL_TRANSFER ? [
                    'from' => StoreHouse::find($movement->from_store_houses_id)->name ?? '-',
                    'to' => StoreHouse::find($movement->to_store_houses_id)->name ?? '-',
                ] : null,
            ];
            $processedMovements[] = $movementData;
        }

        // الرصيد الحالي
        $currentBalance = ProductDetails::where('store_house_id', $warehouseId);
        if ($productId) {
            $currentBalance->where('product_id', $productId);
        }
        $currentStock = $currentBalance->sum('quantity');

        return [
            'warehouse' => $warehouse,
            'opening_balance' => $openingBalance['total'],
            'total_incoming' => $totalIncoming,
            'total_outgoing' => $totalOutgoing,
            'closing_balance' => $runningBalance,
            'good_stock' => $runningGoodBalance,
            'damaged_stock' => $runningDamagedBalance,
            'current_stock' => $currentStock,
            'movements' => $processedMovements,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * حساب الرصيد الافتتاحي
     */
    private function calculateOpeningBalance($warehouseId, $productId, $categoryId, $brand, $beforeDate, $permissionSourceId = null)
    {
        $query = WarehousePermitsProducts::query()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->join('products', 'warehouse_permits_products.product_id', '=', 'products.id')
            ->where('warehouse_permits.permission_date', '<', $beforeDate.' 00:00:00')
            ->whereNotNull('warehouse_permits_products.quantity')
            ->where('warehouse_permits_products.quantity', '!=', 0)
            // عرض الأذونات الموافق عليها أو المكتملة لضمان دقة المخزون الفعلي المعتمد
            ->whereIn('warehouse_permits.status', ['approved', 'apporod', 'delivered', 'done']);

        $query->where(function ($q) use ($warehouseId) {
            $q->where(function ($sub) use ($warehouseId) {
                // الحركات العادية
                $sub->where('warehouse_permits.store_houses_id', $warehouseId)
                    ->whereNull('warehouse_permits.from_store_houses_id')
                    ->whereNull('warehouse_permits.to_store_houses_id');
            })
                ->orWhere(function ($sub) use ($warehouseId) {
                    // التحويل - المستودع هو المصدر
                    $sub->where('warehouse_permits.from_store_houses_id', $warehouseId)
                        ->where('warehouse_permits_products.quantity', '<', 0);
                })
                ->orWhere(function ($sub) use ($warehouseId) {
                    // التحويل - المستودع هو الوجهة
                    $sub->where('warehouse_permits.to_store_houses_id', $warehouseId)
                        ->where('warehouse_permits_products.quantity', '>', 0);
                })
                ->orWhere(function ($sub) use ($warehouseId) {
                    // حالة خاصة: سورس خارجي بدون تحديد من/إلى
                    $sub->where('warehouse_permits.store_houses_id', $warehouseId)
                        ->whereNull('warehouse_permits.from_store_houses_id')
                        ->whereNull('warehouse_permits.to_store_houses_id');
                });
        });

        if ($productId) {
            $query->where('warehouse_permits_products.product_id', $productId);
        }
        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }
        if ($brand) {
            $query->where('products.brand_id', $brand);
        }
        if ($permissionSourceId) {
            $query->where('warehouse_permits.permission_source_id', $permissionSourceId);
        }

        $movements = $query->get();

        $balance = 0;
        $goodBalance = 0;
        $damagedBalance = 0;

        // جلب المستودعات الرئيسية لحساب التالف في الرصيد الافتتاحي
        $mainWarehouseIds = StoreHouse::where('major', 1)->pluck('id')->toArray();

        foreach ($movements as $movement) {
            $isIncoming = $this->isIncomingMovement($movement, $warehouseId);
            $isDamaged = $this->isDamagedMovement($movement, $mainWarehouseIds);

            $quantity = abs($movement->quantity);

            if ($isIncoming) {
                $balance += $quantity;
                if ($isDamaged) {
                    $damagedBalance += $quantity;
                } else {
                    $goodBalance += $quantity;
                }
            } else {
                $balance -= $quantity;

                // تطبيق منطق الخصم ذو الأولوية
                $deduction = $this->applyPrioritizedDeduction($quantity, $goodBalance, $damagedBalance, $isDamaged);
                $goodBalance = $deduction['good'];
                $damagedBalance = $deduction['damaged'];
            }
        }

        return [
            'total' => $balance,
            'good' => $goodBalance,
            'damaged' => $damagedBalance,
        ];
    }

    /**
     * تحديد إذا كانت الحركة واردة للمستودع المحدد
     */
    private function isIncomingMovement($movement, $warehouseId)
    {
        // استخدام نوع الإذن المباشر إذا توفر لضمان الدقة
        if (isset($movement->permission_type)) {
            // حسب WarehousePermits: 1=وارد/إضافة، 2=صادر/صرف، 3=تحويل
            if ($movement->permission_type == 1) {
                return true;
            }
            if ($movement->permission_type == 2) {
                return false;
            }
        }

        $sourceId = $movement->permission_source_id;

        // التحويلات: إذا كان المستودع الحالي هو مستودع الوجهة، فهي حركة واردة
        if ($movement->to_store_houses_id == $warehouseId) {
            return true;
        }

        // إذا كان المستودع الحالي هو مستودع المصدر، فهي حركة صادرة
        if ($movement->from_store_houses_id == $warehouseId) {
            return false;
        }

        // الوارد (Incoming)
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
            PermissionSources::PRODUCT_EXCHANGE_RETURN, // عودة تبديل المنتجات (منتجات جديدة تعود للمخزون)
        ];

        if (in_array($sourceId, $incomingTypes)) {
            return true;
        }

        // المنصرف (Outgoing) - القيمة الافتراضية
        return false;
    }

    /**
     * الحصول على نوع الحركة
     */
    private function getMovementType($movement)
    {
        $name = PermissionSources::getName($movement->permission_source_id);

        // إضافة كلمة "إذن" للتبديلات لتمييزها كأذونات مخزنية بناءً على طلب المستخدم
        $isExchange = in_array($movement->permission_source_id, [PermissionSources::PRODUCT_EXCHANGE, PermissionSources::PRODUCT_EXCHANGE_RETURN]);
        $hasExchangeRef = isset($movement->reference_type) && str_contains($movement->reference_type, 'تبديل');

        if ($isExchange || $hasExchangeRef) {
            return 'إذن '.$name;
        }

        return $name;
    }

    private function isDamagedMovement($movement, $mainWarehouseIds = [])
    {
        // التحقق من نوع المرجع لضمان تصنيف التبديليات بدقة
        if (isset($movement->reference_type)) {
            if ($movement->reference_type === 'ارجاع تبديل تالف') {
                return true; // البضاعة المعادة من العميل تالفة
            }
            if ($movement->reference_type === 'تبديل تالف') {
                return false; // البضاعة الخارجة للعميل سليمة
            }
        }

        // المصادر التي تعني أن الحركة تخص بضاعة تالفة
        $damagedSources = [
            PermissionSources::PRODUCT_EXCHANGE_RETURN,    // ارجاع تبديل بضاعة (وارد) - تالف
            PermissionSources::INVENTORY_DAMAGE,           // إتلاف مخزون - ينقص من التالف
            PermissionSources::MANUFACTURING_DAMAGED_MATERIALS, // مواد تالفة من التصنيع - تدخل كتالف
            PermissionSources::MANUFACTURING_RETURNS,      // مرتجع تصنيع
        ];

        if (in_array($movement->permission_source_id, $damagedSources)) {
            return true;
        }

        // التحويلات إلى المستودع الرئيسي = صرف التالف أولاً ثم السليم
        if ($movement->permission_source_id == PermissionSources::MANUAL_TRANSFER) {
            if (in_array($movement->to_store_houses_id, $mainWarehouseIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * تطبيق منطق الخصم ذو الأولوية
     * يخصم من التالف أولاً إذا كانت الحركة تالفة أو تحويل للمستودع الرئيسي
     */
    private function applyPrioritizedDeduction($quantity, $currentGood, $currentDamaged, $isDamagedPriority = false)
    {
        $deductedFromDamaged = 0;
        $deductedFromGood = 0;

        if ($isDamagedPriority) {
            // إذا كان هناك رصيد تالف، اخصم منه أولاً
            if ($currentDamaged >= $quantity) {
                $currentDamaged -= $quantity;
                $deductedFromDamaged = $quantity;
            } else {
                // اخصم المتبقي من السليم
                $deductedFromDamaged = $currentDamaged;
                $remainingToDeduct = $quantity - $currentDamaged;
                $currentDamaged = 0;
                $currentGood -= $remainingToDeduct;
                $deductedFromGood = $remainingToDeduct;
            }
        } else {
            // الحركة عادية (سليمة)، اخصم من السليم مباشرة
            // ملاحظة: يمكن إضافة منطق هنا للخصم من التالف إذا صفر السليم، لكن المطلب الحالي هو العكس
            $currentGood -= $quantity;
            $deductedFromGood = $quantity;
        }

        return [
            'good' => $currentGood,
            'damaged' => $currentDamaged,
            'deducted_from_damaged' => $deductedFromDamaged,
            'deducted_from_good' => $deductedFromGood,
        ];
    }

    /**
     * عرض صفحة كشف حساب المستودع
     *
     * النطاق وفق `default_warehouses`/التسلسل الإداري لا شرط «مدير» صريح:
     * موظفٌ يرى مستودعاته المعيَّنة، ومن يدير غيره يرى مستودعاته ومستودعات
     * كل من يديرهم أيضاً، ومن ليس موظفاً يرى الكل.
     */
    public function employeeStatement(Request $request)
    {
        $allowedWarehouseIds = $this->allowedWarehouseIds();
        $seesAllWarehouses = $allowedWarehouseIds === null;

        $warehouses = StoreHouse::when(! $seesAllWarehouses, fn ($q) => $q->whereIn('id', $allowedWarehouseIds ?: [0]))
            ->orderBy('name')
            ->get();

        $emptyMessage = $seesAllWarehouses
            ? 'لا توجد مستودعات مسجَّلة في النظام بعد.'
            : 'لا توجد مستودعات ضمن نطاقك حالياً.';

        if ($warehouses->isEmpty()) {
            return view('stock::report.stock_report.employee_warehouse_statement', [
                'warehouses' => collect(),
                'warehouse' => null,
                'statement' => null,
                'products' => collect(),
                'seesAllWarehouses' => $seesAllWarehouses,
                'error' => $emptyMessage,
            ]);
        }

        $warehouseId = $request->input('warehouse') ?: $warehouses->first()->id;

        // مستودعٌ خارج النطاق المسموح يعود إلى أول مستودعٍ متاح
        if (! $seesAllWarehouses && ! in_array((int) $warehouseId, $allowedWarehouseIds, true)) {
            $warehouseId = $warehouses->first()->id;
        }

        $request->merge(['warehouse' => $warehouseId]);

        // التواريخ الافتراضية = الفترة المحاسبية الجارية من الإعدادات
        $reportRange = $this->applyDefaultReportDates($request);

        $warehouse = StoreHouse::find($warehouseId);
        $statement = $this->generateStatement($request);

        // جلب المنتجات التي لها حركات أو رصيد في هذا المستودع لتعبئة الفلتر
        $products = Product::whereIn('type', ProductType::stockedValues())
            ->whereHas('productDetails', function ($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            })->select('id', 'name', 'serial_number')->orderBy('name')->get();

        return view('stock::report.stock_report.employee_warehouse_statement', compact(
            'warehouses',
            'warehouse',
            'statement',
            'products',
            'seesAllWarehouses'
        ));
    }
}
