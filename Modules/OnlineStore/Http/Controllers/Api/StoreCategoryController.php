<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;

/**
 * متحكم الفئات للمتجر الإلكتروني
 * Store Categories Controller
 *
 * يدير عرض الفئات وتصفحها.
 *
 * كان المتحكم يعتمد على موديل `App\Models\Store\StoreCategory` وأعمدة (`slug`، `parent_id`،
 * `show_in_menu`، `is_featured`، `name_ar`…) لا وجود لها في النظام، فكل مسارات `/categories`
 * تسقط بخطأ 500 ("Class not found"). الأصناف الفعلية في `App\Models\Category` (جدول
 * `categories`: الأب في `main_category`، الوصف `discretion`، الصورة `attachments`) — وهي
 * نفسها التي تعتمدها الصفحة الرئيسية وقائمة الأصناف في المتجر.
 */
class StoreCategoryController extends Controller
{
    use FormatsImages;

    /** سقفٌ لأي `limit` يُرسل — قيمة سالبة أو نصّية أو ضخمة لا تُسقط الاستعلام. */
    private function limitParam(Request $request, int $default, int $max): int
    {
        $value = $request->query('limit', $default);

        return is_numeric($value) ? max(1, min((int) $value, $max)) : $default;
    }

    /** منتجات الفئة المتاحة للشراء أونلاين — نفس شرط قائمة الأصناف في الصفحة الرئيسية. */
    private function withProductsCount(Builder $query): Builder
    {
        return $query->withCount(['products' => function ($q) {
            $q->where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active');
        }]);
    }

    /** الفئات الرئيسية: بلا أب (NULL أو 0). */
    private function rootOnly(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('main_category')->orWhere('main_category', 0);
        });
    }

    private function active(): Builder
    {
        return Category::query()->where('is_active', true);
    }

    /**
     * عرض قائمة الفئات
     * List categories
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->active()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name');

        // فقط الفئات الرئيسية
        if ($request->boolean('root_only', true)) {
            $this->rootOnly($query);
        }

        // مع عدد المنتجات
        if ($request->boolean('with_count')) {
            $this->withProductsCount($query);
        }

        $categories = $query->get();

        return response()->json([
            'success' => true,
            'categories' => $categories->map(fn ($category) => $this->formatCategory($category, $request->boolean('with_count'))),
        ]);
    }

    /**
     * شجرة الفئات الكاملة
     * Full categories tree
     */
    public function tree(Request $request): JsonResponse
    {
        $depthParam = $request->query('depth', 3);
        $depth = is_numeric($depthParam) ? max(1, min((int) $depthParam, 5)) : 3;
        $withCount = $request->boolean('with_count');

        $query = $this->rootOnly($this->active())->orderBy('name');

        if ($withCount) {
            $this->withProductsCount($query);
        }

        $tree = $this->buildTree($query->get(), $depth, $withCount);

        return response()->json([
            'success' => true,
            'categories' => $tree,
        ]);
    }

    /**
     * عرض فئة بالمعرف أو الكود
     * Show category
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        $query = $this->withProductsCount(
            $this->active()->with(['parent', 'children' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
        );

        // البحث بالمعرف أو الكود
        if (ctype_digit($identifier)) {
            $category = $query->find((int) $identifier);
        } else {
            $category = $query->where('code', $identifier)->first();
        }

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'category' => $this->formatCategoryDetails($category),
        ]);
    }

    /**
     * الفئات الرئيسية (للقائمة)
     * Main categories for menu
     */
    public function menu(Request $request): JsonResponse
    {
        $limit = $this->limitParam($request, 10, 20);

        $categories = $this->rootOnly($this->active())
            ->orderBy('name')
            ->limit($limit)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('name')->limit(10)])
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $categories->map(fn ($category) => $this->formatMenuCategory($category)),
        ]);
    }

    /**
     * الفئات المميزة — لا يوجد عمود «مميزة» للفئات، فتُعرض أكثر الفئات منتجاتٍ متاحة.
     * Featured categories
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $this->limitParam($request, 8, 20);

        $categories = $this->withProductsCount($this->active())
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->filter(fn ($category) => (int) $category->products_count > 0)
            ->values();

        return response()->json([
            'success' => true,
            'categories' => $categories->map(fn ($category) => $this->formatCategory($category, true)),
        ]);
    }

    /**
     * الفئات الفرعية لفئة
     * Subcategories
     */
    public function children(Request $request, int $parentId): JsonResponse
    {
        $parent = $this->active()->find($parentId);

        if (! $parent) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة الأم غير موجودة',
            ], 404);
        }

        $children = $this->withProductsCount($this->active())
            ->where('main_category', $parentId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'parent' => [
                'id' => $parent->id,
                'name' => $parent->name,
                'slug' => $parent->code ?: (string) $parent->id,
            ],
            'children' => $children->map(fn ($category) => $this->formatCategory($category, true)),
        ]);
    }

    /**
     * مسار الفئة (التنقل)
     * Category breadcrumb
     */
    public function breadcrumb(Request $request, int $id): JsonResponse
    {
        $category = $this->active()->find($id);

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'breadcrumb' => $this->buildBreadcrumb($category),
        ]);
    }

    /**
     * البحث في الفئات
     * Search categories
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $limit = $this->limitParam($request, 10, 30);

        if (! is_string($query) || mb_strlen(trim($query)) < 2) {
            return response()->json([
                'success' => true,
                'categories' => [],
            ]);
        }

        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr(trim($query), 0, 100));

        $categories = $this->withProductsCount($this->active())
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('discretion', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $categories->map(fn ($category) => $this->formatCategory($category, true)),
        ]);
    }

    /**
     * بناء شجرة الفئات
     */
    private function buildTree($categories, int $depth, bool $withCount = false, int $currentDepth = 1, array $seen = []): array
    {
        return $categories->map(function ($category) use ($depth, $withCount, $currentDepth, $seen) {
            $formatted = $this->formatCategory($category, $withCount);
            $seen[] = $category->id;

            if ($currentDepth < $depth) {
                $childrenQuery = $this->active()->where('main_category', $category->id)
                    ->whereNotIn('id', $seen) // فئةٌ أبوها نفسها (حلقة) لا تُكرَّر
                    ->orderBy('name');

                if ($withCount) {
                    $this->withProductsCount($childrenQuery);
                }

                $children = $childrenQuery->get();

                if ($children->isNotEmpty()) {
                    $formatted['children'] = $this->buildTree($children, $depth, $withCount, $currentDepth + 1, $seen);
                }
            }

            return $formatted;
        })->toArray();
    }

    /**
     * بناء مسار التنقل (محميٌّ من الحلقات: فئةٌ أبوها نفسها أو سلسلةٌ دائرية)
     */
    private function buildBreadcrumb(Category $category): array
    {
        $breadcrumb = [];
        $current = $category;
        $seen = [];

        while ($current && ! in_array($current->id, $seen, true) && count($seen) < 20) {
            $seen[] = $current->id;
            array_unshift($breadcrumb, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->code ?: (string) $current->id,
            ]);
            $current = $current->parent;
        }

        return $breadcrumb;
    }

    /**
     * تنسيق الفئة
     */
    private function formatCategory(Category $category, bool $withCount = false): array
    {
        $formatted = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->code ?: (string) $category->id,
            'image' => $this->categoryImage($category),
            'icon' => null,
            'is_featured' => false,
            'parent_id' => $category->main_category ?: null,
        ];

        if ($withCount && isset($category->products_count)) {
            $formatted['products_count'] = (int) $category->products_count;
        }

        if ($category->relationLoaded('children') && $category->children->isNotEmpty()) {
            $formatted['children'] = $category->children->map(fn ($child) => $this->formatCategory($child, $withCount));
        }

        return $formatted;
    }

    /** الصورة من `attachments` (مسارٌ أو رابط) إن كانت نصّاً صالحاً للعرض. */
    private function categoryImage(Category $category): ?string
    {
        $path = $category->attachments;

        return is_string($path) && $path !== '' && ! str_starts_with($path, '[') && ! str_starts_with($path, '{')
            ? $this->formatImageUrl($path)
            : null;
    }

    /**
     * تنسيق الفئة للقائمة
     */
    private function formatMenuCategory(Category $category): array
    {
        $formatted = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->code ?: (string) $category->id,
            'icon' => null,
        ];

        if ($category->relationLoaded('children') && $category->children->isNotEmpty()) {
            $formatted['children'] = $category->children->map(fn ($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'slug' => $child->code ?: (string) $child->id,
            ]);
        }

        return $formatted;
    }

    /**
     * تنسيق تفاصيل الفئة
     */
    private function formatCategoryDetails(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->code ?: (string) $category->id,
            'description' => $category->discretion,
            'image' => $this->categoryImage($category),
            'banner' => null,
            'icon' => null,
            'is_featured' => false,
            'meta_title' => $category->name,
            'meta_description' => $category->discretion,
            'products_count' => (int) ($category->products_count ?? 0),
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->code ?: (string) $category->parent->id,
            ] : null,
            'children' => $category->children->map(fn ($child) => $this->formatCategory($child, false)),
            'breadcrumb' => $this->buildBreadcrumb($category),
        ];
    }
}
