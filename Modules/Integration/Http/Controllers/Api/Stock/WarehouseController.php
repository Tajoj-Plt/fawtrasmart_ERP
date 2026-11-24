<?php

namespace Modules\Integration\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DefaultWarehouses;
use App\Models\StoreHouse;
use App\Support\StorehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\ChecksPermissions;
use Modules\Integration\Http\Controllers\Api\Concerns\DelegatesWebActions;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Stock\StorehouseController;

/**
 * المستودعات — عرضٌ فقط: القائمة، التفاصيل بكميات كل صنف، والحركات.
 *
 * النطاق من `accessibleWarehouseIds()` الذي تقرؤه شاشات المخزون كلّها:
 * مستودعات الموظف، أو مستودعاته ومستودعات من يشرف عليهم، أو الكل لصاحب
 * `data_scope_all` — مع صلاحية «عرض المستودع» الخاصة بكل مستودع.
 */
class WarehouseController extends Controller
{
    use ChecksPermissions, DelegatesWebActions, ScopesToEmployeeHierarchy;

    public function index(Request $request): JsonResponse
    {
        $this->forceDataListResponse($request);

        $data = $this->viewData(app(StorehouseController::class)->index($request));
        $storehouses = $data['storehouses'];
        $meta = $data['storehouseMeta'] ?? [];

        $user = $request->user();
        $defaultIds = $user->employee_id
            ? DefaultWarehouses::where('employee_id', $user->employee_id)->pluck('storehouse_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return response()->json([
            'success' => true,
            'data' => collect($storehouses->items())->map(fn ($s) => $this->warehousePayload($s, $meta[$s->id] ?? [], $defaultIds))->values(),
            'pagination' => [
                'current_page' => $storehouses->currentPage(),
                'last_page' => $storehouses->lastPage(),
                'per_page' => $storehouses->perPage(),
                'total' => $storehouses->total(),
            ],
        ]);
    }

    /** بياناته، وكمية كل صنف (الحالية/الوارد/المنصرف/المباع) من show() في الويب، وسجل النشاط. */
    public function show(Request $request, $id): JsonResponse
    {
        $data = $this->viewData(app(StorehouseController::class)->show($id));
        $storehouse = $data['storehouse'];

        $logs = collect($data['logs'] ?? [])->flatten()->map(fn ($log) => [
            'date' => optional($log->created_at)->toDateTimeString(),
            'description' => $log->description,
            'user' => $log->user->name ?? null,
        ])->values();

        $user = $request->user();
        $defaultIds = $user->employee_id
            ? DefaultWarehouses::where('employee_id', $user->employee_id)->pluck('storehouse_id')->map(fn ($v) => (int) $v)->all()
            : [];

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => $this->warehousePayload($storehouse, $data['permissionsMeta'] ?? [], $defaultIds),
                'items' => $data['accountingItems'] ?? [],
                'totals' => $data['accountingTotals'] ?? [],
                'total_stock_value' => $data['accountingTotalStockValue'] ?? 0,
                'account_balance' => $data['accountingBalance'] ?? null,
                'value_difference' => $data['accountingDifference'] ?? null,
                'logs' => $logs,
            ],
        ]);
    }

    /**
     * حركات المستودع — searchSummaryInventoryOperations() في الويب (JSON
     * أصلاً) بفلاترها: keywords / category / operation_type. والويب لا
     * يفحص هنا نطاق المستودع، فالفحص إضافةٌ فوق التفويض.
     */
    public function movements(Request $request, $id): JsonResponse
    {
        $this->authorizeWarehouseAccess((int) $id);

        $payload = app(StorehouseController::class)->searchSummaryInventoryOperations($request, $id)->getData(true);
        $payload['categories'] = Category::select('id', 'name')->orderBy('name')->get();

        return response()->json($payload);
    }

    private function warehousePayload(StoreHouse $storehouse, array $meta, array $defaultIds): array
    {
        $user = auth()->user();

        return [
            'id' => $storehouse->id,
            'name' => $storehouse->name,
            'status' => $storehouse->status,
            'shipping_address' => $storehouse->shipping_address,
            'branch' => $storehouse->branch->name ?? null,
            'is_default' => in_array((int) $storehouse->id, $defaultIds, true),
            'products_count' => $meta['products_count'] ?? null,
            'total_quantity' => $meta['total_quantity'] ?? null,
            'stock_value' => $meta['stock_value'] ?? null,
            'responsible_employees' => collect($meta['responsible_employees'] ?? [])
                ->map(fn ($e) => trim(($e->first_name ?? '').' '.($e->middle_name ?? '')))
                ->values(),
            'can_view' => StorehouseAccess::canView($storehouse, $user),
            'can_create_invoices' => StorehouseAccess::canCreateInvoices($storehouse, $user),
            'can_edit_stock' => StorehouseAccess::canEditStock($storehouse, $user),
            'can_view_details' => $this->userCan('stock.warehouses.show'),
            'can_view_movements' => $this->userCan('stock.reports.summary_movement'),
            'can_view_statement' => $this->userCan('stock.reports.employee_statement'),
        ];
    }
}
