<?php

namespace Modules\Integration\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\StoreHouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\DelegatesWebActions;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Reports\WarehouseStatementController;

/**
 * الكشف المخزني — employeeStatement() في الويب: مستودعات المستخدم (أو
 * مستودعاته ومستودعات من يشرف عليهم، أو الكل)، والتواريخ الافتراضية هي
 * الفترة المحاسبية الجارية من الإعدادات.
 */
class StatementController extends Controller
{
    use DelegatesWebActions, ScopesToEmployeeHierarchy;

    /** المستودعات المتاحة للكشف — نفس allowedWarehouseIds() التي يقرؤها الويب. */
    public function warehouses(): JsonResponse
    {
        $allowed = $this->allowedWarehouseIds();

        $warehouses = StoreHouse::when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed ?: [0]))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $warehouses]);
    }

    /** ?warehouse= &start_date= &end_date= &product= &category= &brand= &permission_source= */
    public function show(Request $request): JsonResponse
    {
        $data = $this->viewData(app(WarehouseStatementController::class)->employeeStatement($request));

        if (! empty($data['error'])) {
            return response()->json(['success' => false, 'message' => $data['error']], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => $data['warehouse'] ? ['id' => $data['warehouse']->id, 'name' => $data['warehouse']->name] : null,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'statement' => $data['statement'] ?? null,
                'products' => collect($data['products'] ?? [])->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'serial_number' => $p->serial_number,
                ])->values(),
            ],
        ]);
    }
}
