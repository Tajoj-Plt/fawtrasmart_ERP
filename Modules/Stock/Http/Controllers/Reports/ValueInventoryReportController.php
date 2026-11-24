<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Constants\permissions\PermissionSources;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\WarehousePermitsProducts;
use App\Support\AccountingPeriodFilter;
use Illuminate\Http\Request;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;

class ValueInventoryReportController extends ReportController
{
    /*
        الفترة المحاسبية: النطاقُ الافتراضي للتقرير هو السنةُ المالية الجارية
        لا مدًى مكتوبٌ في الشيفرة.
    */
    use RespectsAccountingPeriods;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.value');
    }

    public function index(Request $request)
    {
        $reportData = $this->getValueInventoryData($request);
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();
        $categories = Category::all();
        $suppliers = Supplier::all();

        return view('stock::report.stock_report.value_inventory', [
            'reportData' => $reportData['storehouses'],
            'totals' => $reportData['totals'],
            'warehouses' => $warehouses,
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * المحرك الرئيسي لجلب بيانات المخزون والقيم
     */
    private function getValueInventoryData(Request $request)
    {
        $storehouseId = $request->input('warehouse');
        $categoryId = $request->input('category');
        $supplierId = $request->input('supplier');
        $keywords = $request->input('search');

        if ($storehouseId) {
            $this->authorizeWarehouseScope((int) $storehouseId);
        }

        // نطاق البيانات: بلا فلتر مستودعٍ صريح، تُحصَر الحلقة بمستودعات نطاق المستخدم
        $allowedWarehouseIds = $this->allowedWarehouseIds();

        // Get all storehouses that should be included
        $storehouses = StoreHouse::when($storehouseId, function ($q) use ($storehouseId) {
            $q->where('id', $storehouseId);
        })
            ->when(! $storehouseId && $allowedWarehouseIds !== null, function ($q) use ($allowedWarehouseIds) {
                $q->whereIn('id', $allowedWarehouseIds ?: [0]);
            })
            ->get();

        $storehousesData = [];
        $globalTotals = [
            'products_count' => 0,
            'total_cost_value' => 0,
            'total_sale_value' => 0,
            'total_profit' => 0,
            'quantity' => 0,
        ];

        foreach ($storehouses as $sh) {
            $sId = $sh->id;

            // Build movement-based query for accuracy
            $query = WarehousePermitsProducts::query()
                ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->join('products', 'warehouse_permits_products.product_id', '=', 'products.id')
                ->select(
                    'warehouse_permits_products.product_id',
                    'warehouse_permits_products.quantity',
                    'warehouse_permits.permission_source_id',
                    'warehouse_permits.store_houses_id',
                    'warehouse_permits.from_store_houses_id',
                    'warehouse_permits.to_store_houses_id',
                    'products.name as product_name',
                    'products.serial_number',
                    'products.purchase_price',
                    'products.sale_price',
                    'products.category_id',
                    'products.supplier_id'
                )
                ->whereIn('warehouse_permits.status', ['approved', 'apporod']);

            /*
                **الحصرُ بالفترة عبر العلاقة.**

                التقريرُ يجمع بنودَ الأذون، والإذنُ مختومٌ بفترته لحظةَ إنشائه —
                فالحصرُ شرطٌ على عموده لا مدًى يُقارَن بتاريخه. وكان التقرير
                يجمع الأذونَ كلَّها منذ أوّل إذنٍ في النظام، فيُقرأ رصيدُ سنواتٍ
                على أنّه رصيدُ الفترة.
            */
            AccountingPeriodFilter::applyRaw(
                $query,
                $this->stockPeriod($request),
                'warehouse_permits.accounting_period_id'
            );

            // Warehouse handling (similar to summary report)
            $query->where(function ($q) use ($sId) {
                $q->where(function ($sub) use ($sId) {
                    $sub->whereNotIn('warehouse_permits.permission_source_id', [PermissionSources::MANUAL_TRANSFER])
                        ->where('warehouse_permits.store_houses_id', $sId);
                })
                    ->orWhere(function ($sub) use ($sId) {
                        $sub->where('warehouse_permits.permission_source_id', PermissionSources::MANUAL_TRANSFER)
                            ->where('warehouse_permits.from_store_houses_id', $sId)
                            ->where('warehouse_permits_products.quantity', '<', 0);
                    })
                    ->orWhere(function ($sub) use ($sId) {
                        $sub->where('warehouse_permits.permission_source_id', PermissionSources::MANUAL_TRANSFER)
                            ->where('warehouse_permits.to_store_houses_id', $sId)
                            ->where('warehouse_permits_products.quantity', '>', 0);
                    });
            });

            if ($categoryId) {
                $query->where('products.category_id', $categoryId);
            }
            if ($supplierId) {
                $query->where('products.supplier_id', $supplierId);
            }
            if ($keywords) {
                $query->where('products.name', 'like', '%'.$keywords.'%');
            }

            $movements = $query->get();
            if ($movements->isEmpty() && ! $storehouseId) {
                continue;
            }

            $shProducts = [];
            $productIds = $movements->pluck('product_id')->unique()->toArray();
            $productsModels = Product::whereIn('id', $productIds)->get()->keyBy('id');
            $shTotals = ['quantity' => 0, 'cost_value' => 0, 'sale_value' => 0, 'profit' => 0];

            foreach ($movements as $mov) {
                $pId = $mov->product_id;

                // Determine direction (Strictly matching SummaryInventoryReportController logic)
                $isIncoming = false;
                if ($mov->permission_source_id == PermissionSources::MANUAL_TRANSFER) {
                    $isIncoming = $mov->to_store_houses_id == $sId;
                } else {
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
                        PermissionSources::PRODUCT_EXCHANGE_RETURN,
                    ];
                    $isIncoming = in_array($mov->permission_source_id, $incomingTypes);
                }

                $qtyEffect = $isIncoming ? abs($mov->quantity) : -abs($mov->quantity);

                if (! isset($shProducts[$pId])) {
                    $productModel = $productsModels[$pId] ?? null;
                    $shProducts[$pId] = [
                        'id' => $pId,
                        'name' => $mov->product_name,
                        'code' => $mov->serial_number ?? $pId,
                        'current_quantity' => 0,
                        'unit_cost' => $productModel ? $productModel->averageCost() : ($mov->purchase_price ?? 0),
                        'unit_sale' => $mov->sale_price ?? 0,
                    ];
                }
                $shProducts[$pId]['current_quantity'] += $qtyEffect;
            }

            // Clean up zero quantity items and calculate final values
            foreach ($shProducts as $pId => $p) {
                if ($p['current_quantity'] <= 0) {
                    unset($shProducts[$pId]);

                    continue;
                }

                $qty = $p['current_quantity'];
                $cost = $p['unit_cost'];
                $sale = $p['unit_sale'];

                $shProducts[$pId]['total_cost'] = $qty * $cost;
                $shProducts[$pId]['total_sale'] = $qty * $sale;
                $shProducts[$pId]['profit_value'] = ($qty * $sale) - ($qty * $cost);

                $shTotals['quantity'] += $qty;
                $shTotals['cost_value'] += $shProducts[$pId]['total_cost'];
                $shTotals['sale_value'] += $shProducts[$pId]['total_sale'];
                $shTotals['profit'] += $shProducts[$pId]['profit_value'];
            }

            if (! empty($shProducts) || $storehouseId) {
                $storehousesData[$sId] = [
                    'storehouse' => $sh,
                    'products' => $shProducts,
                    'totals' => $shTotals,
                ];

                $globalTotals['products_count'] += count($shProducts);
                $globalTotals['quantity'] += $shTotals['quantity'];
                $globalTotals['total_cost_value'] += $shTotals['cost_value'];
                $globalTotals['total_sale_value'] += $shTotals['sale_value'];
                $globalTotals['total_profit'] += $shTotals['profit'];

                uasort($storehousesData[$sId]['products'], function ($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
            }
        }

        return [
            'storehouses' => $storehousesData,
            'totals' => $globalTotals,
        ];
    }
}
