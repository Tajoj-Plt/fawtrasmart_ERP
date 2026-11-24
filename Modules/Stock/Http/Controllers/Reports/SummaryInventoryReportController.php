<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Constants\permissions\PermissionSources;
use App\Models\Category;
use App\Models\StoreHouse;
use App\Models\WarehousePermitsProducts;
use App\Support\AccountingPeriodFilter;
use Illuminate\Http\Request;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;

class SummaryInventoryReportController extends ReportController
{
    /*
        الفترة المحاسبية: النطاقُ الافتراضي للتقرير هو السنةُ المالية الجارية
        لا مدًى مكتوبٌ في الشيفرة.
    */
    use RespectsAccountingPeriods;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.summary_movement');
    }

    public function index(Request $request)
    {
        $reportData = $this->getInventoryData($request);
        $warehouseIds = $this->allowedWarehouseIds();
        $storehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();
        $categories = Category::all();

        return view('stock::report.stock_report.summary_inventory', [
            'reportData' => $reportData['storehouses'],
            'totals' => $reportData['totals'],
            'storehouses' => $storehouses,
            'categories' => $categories,
        ]);
    }

    private function getInventoryData(Request $request)
    {
        $storehouseId = $request->input('storehouse_id');
        $categoryId = $request->input('category_id');
        $keywords = $request->input('keywords');

        if ($storehouseId) {
            $this->authorizeWarehouseScope((int) $storehouseId);
        }

        // نطاق البيانات: بلا فلتر مستودعٍ صريح، تُحصَر الحلقة بمستودعات نطاق المستخدم
        $allowedWarehouseIds = $this->allowedWarehouseIds();

        // Get all storehouses that should be included in the report
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
            'incoming' => 0,
            'outgoing' => 0,
            'balance' => 0,
        ];

        foreach ($storehouses as $sh) {
            $sId = $sh->id;

            // Strictly follow WarehouseStatementController::generateStatement query logic
            $query = WarehousePermitsProducts::query()
                ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->join('products', 'warehouse_permits_products.product_id', '=', 'products.id')
                ->select(
                    'warehouse_permits_products.*',
                    'warehouse_permits.permission_source_id',
                    'warehouse_permits.store_houses_id',
                    'warehouse_permits.from_store_houses_id',
                    'warehouse_permits.to_store_houses_id',
                    'products.name as product_name',
                    'products.category_id'
                )
                ->whereNotNull('warehouse_permits_products.quantity')
                ->where('warehouse_permits_products.quantity', '!=', 0)
                // عرض الأذونات الموافقة عليها فقط (apporod أو approved) لضمان دقة المخزون الفعلي المعتمد
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

            // Apply WarehouseStatement storehouse/transfer filtering logic
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

            if ($keywords) {
                $query->where('products.name', 'like', '%'.$keywords.'%');
            }

            $movements = $query->get();

            if ($movements->isEmpty() && $storehouseId) {
                // If filtering by a specific warehouse and no movements, we still want it shown if it exists
                $storehousesData[$sId] = [
                    'storehouse' => $sh,
                    'products' => [],
                    'totals' => ['incoming' => 0, 'outgoing' => 0, 'balance' => 0],
                ];

                continue;
            }

            if ($movements->isEmpty()) {
                continue;
            }

            if (! isset($storehousesData[$sId])) {
                $storehousesData[$sId] = [
                    'storehouse' => $sh,
                    'products' => [],
                    'totals' => ['incoming' => 0, 'outgoing' => 0, 'balance' => 0],
                ];
            }

            foreach ($movements as $mov) {
                $productId = $mov->product_id;
                if (! isset($storehousesData[$sId]['products'][$productId])) {
                    $storehousesData[$sId]['products'][$productId] = [
                        'name' => $mov->product_name,
                        'incoming_purchase' => 0,
                        'incoming_return' => 0,
                        'incoming_transfer' => 0,
                        'incoming_manual' => 0,
                        'incoming_total' => 0,
                        'outgoing_sales' => 0,
                        'outgoing_return' => 0,
                        'outgoing_transfer' => 0,
                        'outgoing_manual' => 0,
                        'outgoing_total' => 0,
                        'balance' => 0,
                    ];
                }

                $sourceId = $mov->permission_source_id;
                $qty = $mov->quantity;

                // Mirroring isIncomingMovement logic from WarehouseStatementController
                $isIncoming = false;
                if ($sourceId == PermissionSources::MANUAL_TRANSFER) {
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
                    $isIncoming = in_array($sourceId, $incomingTypes);
                }

                $absQty = abs($qty);

                if ($isIncoming) {
                    if ($sourceId == PermissionSources::PURCHASE_INVOICE) {
                        $storehousesData[$sId]['products'][$productId]['incoming_purchase'] += $absQty;
                    } elseif ($sourceId == PermissionSources::SALES_RETURN || $sourceId == PermissionSources::SALES_CREDIT_NOTE) {
                        $storehousesData[$sId]['products'][$productId]['incoming_return'] += $absQty;
                    } elseif ($sourceId == PermissionSources::MANUAL_TRANSFER || $sourceId == PermissionSources::POS_SHIFT_INBOUND) {
                        $storehousesData[$sId]['products'][$productId]['incoming_transfer'] += $absQty;
                    } else {
                        $storehousesData[$sId]['products'][$productId]['incoming_manual'] += $absQty;
                    }
                    $storehousesData[$sId]['products'][$productId]['incoming_total'] += $absQty;
                    $storehousesData[$sId]['totals']['incoming'] += $absQty;
                    $globalTotals['incoming'] += $absQty;
                } else {
                    if ($sourceId == PermissionSources::SALES_INVOICE) {
                        $storehousesData[$sId]['products'][$productId]['outgoing_sales'] += $absQty;
                    } elseif ($sourceId == PermissionSources::PURCHASE_RETURN || $sourceId == PermissionSources::PURCHASE_DEBIT_NOTE) {
                        $storehousesData[$sId]['products'][$productId]['outgoing_return'] += $absQty;
                    } elseif ($sourceId == PermissionSources::MANUAL_TRANSFER || $sourceId == PermissionSources::POS_SHIFT_OUTBOUND) {
                        $storehousesData[$sId]['products'][$productId]['outgoing_transfer'] += $absQty;
                    } else {
                        $storehousesData[$sId]['products'][$productId]['outgoing_manual'] += $absQty;
                    }
                    $storehousesData[$sId]['products'][$productId]['outgoing_total'] += $absQty;
                    $storehousesData[$sId]['totals']['outgoing'] += $absQty;
                    $globalTotals['outgoing'] += $absQty;
                }

                $storehousesData[$sId]['products'][$productId]['balance'] =
                    $storehousesData[$sId]['products'][$productId]['incoming_total'] -
                    $storehousesData[$sId]['products'][$productId]['outgoing_total'];
            }

            $storehousesData[$sId]['totals']['balance'] = $storehousesData[$sId]['totals']['incoming'] - $storehousesData[$sId]['totals']['outgoing'];
            $globalTotals['products_count'] += count($storehousesData[$sId]['products']);

            uasort($storehousesData[$sId]['products'], function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        $globalTotals['balance'] = $globalTotals['incoming'] - $globalTotals['outgoing'];

        return [
            'storehouses' => $storehousesData,
            'totals' => $globalTotals,
        ];
    }
}
