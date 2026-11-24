<?php

namespace Modules\Integration\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehousePermitsRequest;
use App\Models\WarehousePermits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\ChecksPermissions;
use Modules\Integration\Http\Controllers\Api\Concerns\DelegatesWebActions;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController;

/**
 * الأذون المخزنية بأنواعها الأربعة — تفويضٌ إلى
 * {@see StorePermitsManagementController}: رقم الإذن من «الترقيم
 * المتسلسل»، حساب المستودع والطرف المقابل من «الحسابات المحاسبية»،
 * حارس الفترة المحاسبية، فحص الأصناف المخزنية والرصيد، ونطاق المستودعات
 * — كلّها في دوال الويب نفسها. والكمية لا تتحرّك إلا عند الاعتماد.
 */
class PermitController extends Controller
{
    use ChecksPermissions, DelegatesWebActions, ScopesToEmployeeHierarchy;

    /** الأنواع الأربعة وصلاحية إنشاء كلٍّ منها — مطابقةٌ لـ CREATE_PERMISSION_BY_TYPE في الويب. */
    private const TYPES = [
        1 => ['key' => 'addition', 'label' => 'إذن إضافة', 'permission' => 'stock.permits.create_addition', 'screen' => 'create'],
        2 => ['key' => 'disbursement', 'label' => 'إذن صرف', 'permission' => 'stock.permits.create_disbursement', 'screen' => 'manual_disbursement'],
        3 => ['key' => 'transfer', 'label' => 'إذن تحويل', 'permission' => 'stock.permits.create_transfer', 'screen' => 'manual_conversion'],
        4 => ['key' => 'opening', 'label' => 'رصيد افتتاحي', 'permission' => 'stock.permits.create_opening', 'screen' => 'opening_balance'],
    ];

    /** حالات الإذن كما تُخزَّن في الجدول. */
    private const STATUSES = ['pending' => 'pending', 'approved' => 'apporod', 'rejected' => 'rejec'];

    /** ?status=pending|approved|rejected ومعها فلاتر شاشة الويب نفسها. */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('status')) {
            $request->merge(['status' => self::STATUSES[$request->input('status')] ?? $request->input('status')]);
        }

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $permits = app(StorePermitsManagementController::class)->permitsListQuery($request)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($permits->items())->map(fn ($p) => $this->permitPayload($p))->values(),
            'pagination' => [
                'current_page' => $permits->currentPage(),
                'last_page' => $permits->lastPage(),
                'per_page' => $permits->perPage(),
                'total' => $permits->total(),
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $data = $this->viewData(app(StorePermitsManagementController::class)->show($id));
        $permit = $data['permit'];

        $logs = collect($data['logs'] ?? [])->flatten()->map(fn ($log) => [
            'date' => optional($log->created_at)->toDateTimeString(),
            'description' => $log->description,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->permitPayload($permit), [
                'items' => $permit->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? null,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ])->values(),
                'attachments' => $permit->attachments,
                'rejection_reason' => $permit->rejection_reason,
                'logs' => $logs,
            ]),
        ]);
    }

    /**
     * بيانات شاشة الإنشاء لنوعٍ بعينه (?type=1..4) — من شاشة الويب نفسها:
     * المستودعات المتاحة للمستخدم، الأصناف المخزنية، ومعاينة الرقم التالي.
     */
    public function createData(Request $request): JsonResponse
    {
        $type = self::TYPES[(int) $request->input('type')] ?? null;

        if (! $type) {
            return response()->json(['success' => false, 'message' => 'نوع إذنٍ غير معروف.'], 422);
        }

        if (! $this->userCan($type['permission'])) {
            abort(403, 'لا تملك صلاحية إنشاء هذا النوع من الأذون المخزنية.');
        }

        $data = $this->viewData(app(StorePermitsManagementController::class)->{$type['screen']}());

        return response()->json([
            'success' => true,
            'data' => [
                'type' => (int) $request->input('type'),
                'serial_number' => $data['serial_number'] ?? null,
                'warehouses' => collect($data['storeHouses'] ?? [])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                'products' => collect($data['products'] ?? [])->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'barcode' => $p->barcode,
                    'purchase_price' => (float) $p->purchase_price,
                    'sale_price' => (float) $p->sale_price,
                ])->values(),
            ],
        ]);
    }

    /** رصيد صنفٍ في مستودع — getProductStock() في الويب (JSON أصلاً، بحارس النطاق). */
    public function productStock($warehouseId, $productId): JsonResponse
    {
        return app(StorePermitsManagementController::class)->getProductStock($warehouseId, $productId);
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->webFormRequest(WarehousePermitsRequest::class);
        $lastId = (int) WarehousePermits::max('id');

        $response = $this->delegateWebAction(
            fn () => app(StorePermitsManagementController::class)->store($form),
            'تم إنشاء الإذن المخزني بنجاح'
        );

        if ($response->getData(true)['success'] ?? false) {
            $permit = WarehousePermits::where('id', '>', $lastId)
                ->where('created_by', $request->user()->id)
                ->latest('id')
                ->first();

            return response()->json(array_merge($response->getData(true), [
                'data' => $permit ? ['id' => $permit->id, 'number' => $permit->number, 'status' => $this->statusKey($permit->status)] : null,
            ]));
        }

        return $response;
    }

    /** تعديل إذنٍ معلَّق — الويب يرفض غير المعلَّق ويفحص صلاحية إنشاء نوعه ونطاق مستودعاته. */
    public function update(Request $request, $id): JsonResponse
    {
        $form = $this->webFormRequest(WarehousePermitsRequest::class);

        return $this->delegateWebAction(
            fn () => app(StorePermitsManagementController::class)->update($form, $id),
            'تم تعديل الإذن المخزني بنجاح'
        );
    }

    /**
     * حذف إذنٍ غير معتمد.
     *
     * delete() في الويب لا تنظر في حالة الإذن، فيُحذف المعتمَد وتبقى
     * كمّياته وقيوده قائمةً بلا مستند — فالحارس هنا فوق التفويض. ورسالة
     * نجاحها في الويب تُكتب تحت `error`، فالنجاح يُقرأ من زوال الإذن نفسه.
     */
    public function destroy($id): JsonResponse
    {
        $permit = WarehousePermits::findOrFail($id);
        $this->authorizeCreatorScope($permit->created_by);

        if ($permit->status === 'apporod') {
            return response()->json([
                'success' => false,
                'message' => 'لا يُحذف إذنٌ معتمد — ألغِ اعتماده أولاً.',
            ], 422);
        }

        app(StorePermitsManagementController::class)->delete($id);
        session()->forget(['error', 'success']);

        return WarehousePermits::whereKey($id)->exists()
            ? response()->json(['success' => false, 'message' => 'تعذّر حذف الإذن.'], 422)
            : response()->json(['success' => true, 'message' => 'تم حذف الإذن المخزني بنجاح']);
    }

    public function approve($id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(StorePermitsManagementController::class)->approve($id),
            'تم اعتماد الإذن المخزني بنجاح'
        );
    }

    /** ?reason= سبب الرفض (اختياري). */
    public function reject(Request $request, $id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(StorePermitsManagementController::class)->reject($request, $id),
            'تم رفض الإذن المخزني'
        );
    }

    public function cancelApproval($id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(StorePermitsManagementController::class)->cancelApproval($id),
            'تم إلغاء اعتماد الإذن المخزني'
        );
    }

    private function permitPayload(WarehousePermits $permit): array
    {
        $type = (int) $permit->permission_type;
        $status = $this->statusKey($permit->status);
        $pending = $status === 'pending';
        $inScope = $this->canAccessCreator($permit->created_by);

        return [
            'id' => $permit->id,
            'number' => $permit->number,
            'type' => $type,
            'type_label' => self::TYPES[$type]['label'] ?? null,
            'status' => $status,
            'date' => $permit->permission_date,
            'warehouse' => $permit->storeHouse->name ?? null,
            'from_warehouse' => $permit->fromStoreHouse->name ?? null,
            'to_warehouse' => $permit->toStoreHouse->name ?? null,
            'grand_total' => (float) $permit->grand_total,
            'currency' => $permit->currency,
            'details' => $permit->details,
            'created_by' => $permit->user->name ?? null,
            // الصلاحية وحدها لا تكفي — حالة الإذن تحسم ما يجوز عليه
            'can_edit' => $inScope && $pending && $this->userCan('stock.permits.edit')
                && isset(self::TYPES[$type]) && $this->userCan(self::TYPES[$type]['permission']),
            'can_delete' => $inScope && $status !== 'approved' && $this->userCan('stock.permits.delete'),
            'can_approve' => $inScope && $pending && $this->userCan('stock.permits.approve'),
            'can_reject' => $inScope && $pending && $this->userCan('stock.permits.reject'),
            'can_cancel_approval' => $inScope && $status === 'approved' && $this->userCan('stock.permits.cancel_approval'),
        ];
    }

    private function statusKey(?string $stored): ?string
    {
        return array_search($stored, self::STATUSES, true) ?: $stored;
    }
}
