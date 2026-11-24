<?php

namespace App\Http\Controllers\Mobile;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreHouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

/** قوائم الاختيار لنماذج الإنشاء في وحدة المخزون — من بيانات شاشة الإنشاء في الويب نفسها. */
class MobileStockFormsController extends Controller
{
    use ScopesToEmployeeHierarchy;

    private const LIMIT = 60;

    public function options(Request $request, string $form): JsonResponse
    {
        $builders = [
            'warehouses' => fn () => $this->warehouses($request),
            'products' => fn () => $this->products($request),
            'permits' => fn () => $this->permits($request),
        ];

        abort_unless(isset($builders[$form]), 404, 'نموذج غير معروف.');

        $lists = $builders[$form]();
        $q = trim((string) $request->query('q', ''));
        $field = (string) $request->query('field', '');

        if ($field !== '') {
            abort_unless(isset($lists['options'][$field]), 404, 'حقل غير معروف.');

            // المنتجات قائمةٌ كبيرةٌ محتملةً: بحثٌ حقيقيٌّ على القاعدة بدل تصفية القائمة المحمَّلة
            if ($field === 'product_id' && $q !== '') {
                return response()->json(['success' => true, 'data' => $this->searchProducts($q)]);
            }

            return response()->json(['success' => true, 'data' => $this->filter($lists['options'][$field], $q)]);
        }

        $lists['options'] = collect($lists['options'])->map(fn ($items) => $this->filter($items, ''))->all();

        return response()->json(['success' => true, 'data' => $lists]);
    }

    /* ─────────────────────────── مستودعٌ جديد ─────────────────────────── */

    private function warehouses(Request $request): array
    {
        return [
            'options' => ['branch_id' => $this->toOptions(Branch::select('id', 'name')->get())],
            'defaults' => [],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── منتجٌ جديد ─────────────────────────── */

    private function products(Request $request): array
    {
        return [
            'options' => ['category_id' => $this->toOptions(Category::select('id', 'name')->get())],
            'defaults' => [],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── إذنٌ مخزنيٌّ جديد ─────────────────────────── */

    private function permits(Request $request): array
    {
        $warehouseIds = $this->accessibleWarehouseIds();
        $storeHouses = StoreHouse::where('status', 'active')
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))
            ->select('id', 'name')->get();

        $user = $request->user();
        $can = fn (string $p) => (bool) ($user && $user->can($p));

        return [
            'options' => [
                'store_houses_id' => $this->toOptions($storeHouses),
                'product_id' => $this->toOptions(Product::whereIn('type', ProductType::stockedValues())->where('status', 'active')->limit(self::LIMIT)->get()),
            ],
            'defaults' => [],
            'flags' => [
                'can_create_addition' => $can('stock.permits.create_addition'),
                'can_create_disbursement' => $can('stock.permits.create_disbursement'),
                'can_create_transfer' => $can('stock.permits.create_transfer'),
                'can_create_opening' => $can('stock.permits.create_opening'),
            ],
        ];
    }

    private function searchProducts(string $q): array
    {
        return $this->toOptions(
            Product::whereIn('type', ProductType::stockedValues())
                ->where('status', 'active')
                ->where('name', 'like', "%{$q}%")
                ->limit(self::LIMIT)
                ->get()
        );
    }

    /* ─────────────────────────── أدوات ─────────────────────────── */

    private function toOptions(iterable $items, string $nameColumn = 'name'): array
    {
        return Collection::make($items)->map(function ($row) use ($nameColumn) {
            $get = fn (string $k) => is_array($row) ? ($row[$k] ?? null) : ($row->{$k} ?? null);
            $name = $get($nameColumn) ?? $get('name');
            $code = $get('code');

            return [
                'id' => (int) $get('id'),
                'label' => trim(($code !== null && $code !== '' && $code !== 0 && $code !== '0' ? $code.' — ' : '').$name),
            ];
        })->values()->all();
    }

    private function filter(array $items, string $q): array
    {
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $items = array_values(array_filter($items, fn ($o) => str_contains(mb_strtolower($o['label']), $needle)));
        }

        return array_slice($items, 0, self::LIMIT);
    }
}
