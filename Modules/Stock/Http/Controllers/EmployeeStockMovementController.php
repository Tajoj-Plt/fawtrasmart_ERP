<?php

namespace Modules\Stock\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\WarehousePermits;
use App\Models\DefaultWarehouses;
use Illuminate\Support\Facades\Auth;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class EmployeeStockMovementController extends Controller
{
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        $this->middleware('permission.role:stock.employee_stock.view');
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        /*
            نطاق الرؤية: مستودعاتي المعيَّنة (default_warehouses) — أو
            مستودعاتي ومستودعات كل من أديرهم إدارياً إن كنت أديرهم
            (Employee::direct_manager_id)، أو بلا قيدٍ إن لم أكن موظفاً
            أصلاً. كان `storehouse_id` في الطلب يُقبل كما هو من قائمةٍ غير
            مُقيَّدة، فيستطيع أي موظفٍ قراءة حركة مستودعٍ آخر بتغيير القيمة
            في الرابط مباشرة — والفحص السابق (`hasRoleName('manager')`) كان
            يُخطئ أصلاً: اسم الدور ليس مقياس الإدارة الفعلي.
        */
        $warehouseIds = $this->accessibleWarehouseIds();
        $allWarehouses = \App\Models\StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        // Determine default warehouse
        $storehouseId = $request->query('storehouse_id');
        $currentStorehouse = null;

        if ($storehouseId) {
            $currentStorehouse = $allWarehouses->firstWhere('id', $storehouseId);
        }

        if (!$currentStorehouse) {
            if ($user->employee_id) {
                $default = DefaultWarehouses::where('employee_id', $user->employee_id)->first();
                if ($default) {
                    $currentStorehouse = $allWarehouses->firstWhere('id', $default->storehouse_id);
                }
            }
        }

        if (!$currentStorehouse) {
            $currentStorehouse = $allWarehouses->first();
        }

        if (!$currentStorehouse) {
             return view('stock::employee_stock_movement.no_warehouse', ['message' => 'No warehouses found in the system.']);
        }

        $warehouseId = $currentStorehouse->id;

        // --- FETCHING DATA ---

        // 1. Warehouse Permits (Incoming & Outgoing)
        $permitsIn = WarehousePermits::with(['permissionSource', 'items'])
            ->withSum('items', 'quantity')
            ->where('to_store_houses_id', $warehouseId)
            ->get()
            ->map(function($item) {
                $item->source_type = 'permit';
                $item->direction = 'in';
                $item->type_label = $item->permissionSource->name ?? 'إذن مخزني';
                $item->doc_date = $item->permission_date;
                $item->doc_number = $item->number;
                $item->total_qty = $item->items_sum_quantity;
                return $item;
            });

        $permitsOut = WarehousePermits::with(['permissionSource', 'items'])
            ->withSum('items', 'quantity')
            ->where('from_store_houses_id', $warehouseId)
            ->get()
            ->map(function($item) {
                $item->source_type = 'permit';
                $item->direction = 'out';
                $item->type_label = $item->permissionSource->name ?? 'إذن مخزني';
                $item->doc_date = $item->permission_date;
                $item->doc_number = $item->number;
                $item->total_qty = $item->items_sum_quantity;
                return $item;
            });

        // 2. Sales Invoices (Outgoing) - Normal & POS
        $salesOut = \App\Models\Invoice::where(function($q) {
                $q->where('type', 'normal')->orWhere('type', 'pos');
            })
            ->whereHas('items', function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            })
            ->with(['items' => function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            }])
            ->get()
            ->map(function($item) {
                $item->source_type = 'invoice';
                $item->direction = 'out';
                $item->type_label = ($item->type == 'pos' ? 'فاتورة نقاط بيع' : 'فاتورة مبيعات');
                $item->doc_date = $item->date ?? $item->created_at->format('Y-m-d');
                $item->doc_number = $item->code ?? $item->id;
                $item->total_qty = $item->items->sum('quantity');
                $item->status = 'approved'; // Assumed
                return $item;
            });

        // 3. Sales Returns (Incoming)
        $salesReturnsIn = \App\Models\Invoice::where('type', 'return')
            ->whereHas('items', function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            })
            ->with(['items' => function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            }])
            ->get()
            ->map(function($item) {
                $item->source_type = 'invoice';
                $item->direction = 'in';
                $item->type_label = 'مردود مبيعات';
                $item->doc_date = $item->date ?? $item->created_at->format('Y-m-d');
                $item->doc_number = $item->code ?? $item->id;
                $item->total_qty = $item->items->sum('quantity');
                $item->status = 'approved';
                return $item;
            });

        // 4. Purchase Invoices (Incoming)
        $purchasesIn = \App\Models\PurchaseInvoice::where('type', '!=', 'return')
            ->whereHas('items', function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            })
            ->with(['items' => function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            }])
            ->get()
            ->map(function($item) {
                $item->source_type = 'purchase';
                $item->direction = 'in';
                $item->type_label = 'فاتورة مشتريات';
                $item->doc_date = $item->date ?? $item->created_at->format('Y-m-d');
                $item->doc_number = $item->code ?? $item->id;
                $item->total_qty = $item->items->sum('quantity');
                $item->status = 'approved';
                return $item;
            });

        // 5. Purchase Returns (Outgoing)
        $purchaseReturnsOut = \App\Models\PurchaseInvoice::where('type', 'return')
            ->whereHas('items', function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            })
            ->with(['items' => function($q) use ($warehouseId) {
                $q->where('store_house_id', $warehouseId);
            }])
            ->get()
            ->map(function($item) {
                $item->source_type = 'purchase';
                $item->direction = 'out';
                $item->type_label = 'مردود مشتريات';
                $item->doc_date = $item->date ?? $item->created_at->format('Y-m-d');
                $item->doc_number = $item->code ?? $item->id;
                $item->total_qty = $item->items->sum('quantity');
                $item->status = 'approved';
                return $item;
            });

        // Merge and Sort
        $incoming = collect()
            ->merge($permitsIn)
            ->merge($salesReturnsIn)
            ->merge($purchasesIn)
            ->sortByDesc('doc_date');

        $outgoing = collect()
            ->merge($permitsOut)
            ->merge($salesOut)
            ->merge($purchaseReturnsOut)
            ->sortByDesc('doc_date');

        return view('stock::employee_stock_movement.index', compact('incoming', 'outgoing', 'currentStorehouse', 'allWarehouses'));
    }
}
