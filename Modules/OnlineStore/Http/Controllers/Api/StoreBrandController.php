<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Store\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;

/**
 * متحكم البراندات للمتجر الإلكتروني
 * Store Brands Controller
 *
 * يدير عرض العلامات التجارية
 */
class StoreBrandController extends Controller
{
    use FormatsImages;

    /**
     * عرض قائمة البراندات
     * List brands
     */
    public function index(Request $request): JsonResponse
    {
        $query = Brand::active();

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // البراندات المميزة فقط
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // إضافة عدد المنتجات
        $query->withCount(['products' => function ($q) {
            $q->where('available_online', true)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', ProductStatus::ACTIVE);
        }]);

        $query->ordered();

        $perPage = min($request->get('per_page', 20), 100);
        $brands = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'brands' => $brands->map(fn ($brand) => $this->formatBrand($brand)),
            'pagination' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
            ],
        ]);
    }

    /**
     * عرض تفاصيل براند
     * Show brand
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $brand = Brand::active()
            ->withCount(['products' => function ($q) {
                $q->where('available_online', true)
                    ->where('type', ProductType::PRODUCTS->value)
                    ->where('status', ProductStatus::ACTIVE);
            }])
            ->find($id);

        if (! $brand) {
            return response()->json([
                'success' => false,
                'message' => 'البراند غير موجود',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'brand' => $this->formatBrandDetails($brand),
        ]);
    }

    /**
     * البراندات المميزة
     * Featured brands
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 20);

        $brands = Brand::active()
            ->featured()
            ->withCount(['products' => function ($q) {
                $q->where('available_online', true)
                    ->where('type', ProductType::PRODUCTS->value)
                    ->where('status', ProductStatus::ACTIVE);
            }])
            ->ordered()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'brands' => $brands->map(fn ($brand) => $this->formatBrand($brand)),
        ]);
    }

    /**
     * البحث عن براند بالاسم
     * Search brands
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'brands' => [],
            ]);
        }

        $brands = Brand::active()
            ->where('name', 'like', "%{$search}%")
            ->withCount(['products' => function ($q) {
                $q->where('available_online', true)
                    ->where('type', ProductType::PRODUCTS->value)
                    ->where('status', ProductStatus::ACTIVE);
            }])
            ->ordered()
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'brands' => $brands->map(fn ($brand) => $this->formatBrand($brand)),
        ]);
    }

    /**
     * تنسيق بيانات البراند
     */
    private function formatBrand(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'logo' => $this->formatImageUrl($brand->logo),
            'is_featured' => $brand->is_featured,
            'products_count' => $brand->products_count ?? 0,
        ];
    }

    /**
     * تنسيق تفاصيل البراند
     */
    private function formatBrandDetails(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'description' => $brand->description,
            'logo' => $this->formatImageUrl($brand->logo),
            'website' => $brand->website,
            'is_featured' => $brand->is_featured,
            'products_count' => $brand->products_count ?? 0,
            'meta_title' => $brand->meta_title,
            'meta_description' => $brand->meta_description,
        ];
    }
}
