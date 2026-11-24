<?php

namespace Modules\Integration\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListItems;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\ChecksPermissions;
use Modules\Integration\Http\Controllers\Api\Concerns\DelegatesWebActions;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Stock\PriceListController as WebPriceListController;

/**
 * قوائم الأسعار — تفويضٌ إلى {@see WebPriceListController}. والأصناف
 * المعروضة للإضافة بنطاق `scopeToProductVisibility()` نفسه: الموظف لا
 * يُسعّر صنفاً لا يراه في مستودعاته.
 */
class PriceListController extends Controller
{
    use ChecksPermissions, DelegatesWebActions, ScopesToEmployeeHierarchy;

    /** فلاتر شاشة الويب: keywords / product / category / brand / status. */
    public function index(Request $request): JsonResponse
    {
        $this->forceDataListResponse($request);

        $priceLists = $this->viewData(app(WebPriceListController::class)->search($request))['price_lists'];

        return response()->json([
            'success' => true,
            'data' => collect($priceLists->items())->map(fn ($list) => $this->listPayload($list))->values(),
            'pagination' => [
                'current_page' => $priceLists->currentPage(),
                'last_page' => $priceLists->lastPage(),
                'per_page' => $priceLists->perPage(),
                'total' => $priceLists->total(),
            ],
        ]);
    }

    /** القائمة بأصنافها، والأصناف المتاحة للإضافة (بنطاق المستخدم). */
    public function show($id): JsonResponse
    {
        $data = $this->viewData(app(WebPriceListController::class)->show($id));

        return response()->json([
            'success' => true,
            'data' => array_merge($this->listPayload($data['price_list']), [
                'items' => collect($data['list_products'] ?? [])->map(fn ($product) => [
                    'item_id' => $product->pivot->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sale_price' => (float) $product->sale_price,
                ])->values(),
                'available_products' => $this->userCan('stock.price_lists.manage_products')
                    ? collect($data['products'] ?? [])->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sale_price' => (float) $p->sale_price,
                    ])->values()
                    : [],
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $lastId = (int) PriceList::max('id');

        $response = $this->delegateWebAction(
            fn () => app(WebPriceListController::class)->store($request),
            'تمت إضافة قائمة الأسعار بنجاح'
        );

        if ($response->getData(true)['success'] ?? false) {
            $list = PriceList::where('id', '>', $lastId)->latest('id')->first();

            return response()->json(array_merge($response->getData(true), [
                'data' => $list ? $this->listPayload($list) : null,
            ]));
        }

        return $response;
    }

    public function update(Request $request, $id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(WebPriceListController::class)->update($request, $id),
            'تم تعديل قائمة الأسعار بنجاح'
        );
    }

    /** رسالة نجاح الحذف في الويب تُكتب تحت `error` — فالنجاح يُقرأ من زوال القائمة. */
    public function destroy($id): JsonResponse
    {
        PriceList::findOrFail($id);

        app(WebPriceListController::class)->delete($id);
        session()->forget(['error', 'success']);

        return PriceList::whereKey($id)->exists()
            ? response()->json(['success' => false, 'message' => 'تعذّر حذف قائمة الأسعار.'], 422)
            : response()->json(['success' => true, 'message' => 'تم حذف قائمة الأسعار بنجاح']);
    }

    /**
     * إضافة صنفٍ بسعره. add_product() في الويب تكتب `sale_price` على الصنف
     * نفسه بلا تحقّق — فسعرٌ غائب يمحو سعر الصنف. فيُشترط هنا، ويُشترط أن
     * يكون الصنف ضمن ما يراه المستخدم.
     */
    public function addProduct(Request $request, $id): JsonResponse
    {
        PriceList::findOrFail($id);

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'sale_price' => 'required|numeric|min:0',
        ]);

        $visible = $this->scopeToProductVisibility(Product::query())->whereKey($request->integer('product_id'))->exists();
        if (! $visible) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا الصنف.');
        }

        return $this->delegateWebAction(
            fn () => app(WebPriceListController::class)->add_product($request, $id),
            'تمت إضافة الصنف إلى قائمة الأسعار'
        );
    }

    /** {itemId} هو item_id من تفاصيل القائمة (سطر الربط لا الصنف). */
    public function removeProduct($itemId): JsonResponse
    {
        PriceListItems::findOrFail($itemId);

        return $this->delegateWebAction(
            fn () => app(WebPriceListController::class)->delete_product($itemId),
            'حُذف الصنف من قائمة الأسعار'
        );
    }

    private function listPayload(PriceList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'status' => $list->status,
            'created_at' => optional($list->created_at)->toDateTimeString(),
            'can_edit' => $this->userCan('stock.price_lists.edit'),
            'can_delete' => $this->userCan('stock.price_lists.delete'),
            'can_manage_products' => $this->userCan('stock.price_lists.manage_products'),
        ];
    }
}
