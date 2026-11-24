<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * كنترولر إدارة أصناف المنتجات
 * Store Category Management Controller
 */
class StoreCategoryController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.categories.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:online_store.categories.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.categories.edit', ['only' => ['edit', 'update', 'removeImage', 'toggleStatus']]);
        $this->middleware('permission.role:online_store.categories.delete', ['only' => ['destroy']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'name' => 'اسم الصنف',
        'description' => 'الوصف',
        'main_category' => 'الصنف الرئيسي',
        'image' => 'صورة الصنف',
    ];

    /** الأصناف الرئيسية (بلا أب) — مجموعةً بين قوسين كي لا يفلت `orWhere` من بقية الشروط. */
    private function mainCategoriesQuery()
    {
        return Category::where(function ($q) {
            $q->whereNull('main_category')->orWhere('main_category', 0);
        });
    }

    /** معرّفات الصنف وكل ما تحته — صنفٌ لا يُجعل ابناً لنفسه ولا لأحد أحفاده. */
    private function selfAndDescendantIds(Category $category): array
    {
        $ids = [$category->id];
        $frontier = [$category->id];

        while ($frontier !== []) {
            $children = Category::whereIn('main_category', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * عرض قائمة جميع الأصناف
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = Category::query();

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('discretion', 'like', "%{$search}%");
            });
        }

        // فلترة حسب الصنف الرئيسي — `orWhere` بلا أقواس كان يُسقط شرط البحث
        if ($request->filled('main_category') && is_scalar($request->main_category)) {
            if ($request->main_category === 'main') {
                $query->where(function ($q) {
                    $q->whereNull('main_category')->orWhere('main_category', 0);
                });
            } else {
                $query->where('main_category', (int) $request->main_category);
            }
        }

        $categories = $query->withCount('products')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // إحصائيات
        $stats = [
            'total' => Category::count(),
            'main_categories' => $this->mainCategoriesQuery()->count(),
            'sub_categories' => Category::whereNotNull('main_category')->where('main_category', '>', 0)->count(),
            'with_products' => Category::has('products')->count(),
        ];

        // الأصناف الرئيسية للفلترة
        $mainCategories = $this->mainCategoriesQuery()->select('id', 'name')->get();

        return view('onlinestore::categories.index', compact('categories', 'stats', 'mainCategories'));
    }

    /**
     * عرض صفحة إنشاء صنف جديد
     */
    public function create()
    {
        $mainCategories = $this->mainCategoriesQuery()->select('id', 'name')->get();

        return view('onlinestore::categories.create', compact('mainCategories'));
    }

    /**
     * حفظ صنف جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'main_category' => 'nullable|integer|exists:categories,id',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ], [], self::ATTRIBUTES);

        // الاسم نفسه تحت الأب نفسه مرتين = إرسالٌ مكرَّر للنموذج في الغالب
        if (Category::where('name', $validated['name'])
            ->where(fn ($q) => empty($validated['main_category'])
                ? $q->whereNull('main_category')->orWhere('main_category', 0)
                : $q->where('main_category', $validated['main_category']))
            ->exists()) {
            return redirect()->back()->withInput()->withErrors(['name' => 'يوجد صنف بهذا الاسم في المستوى نفسه.']);
        }

        // تجهيز البيانات للحفظ
        $data = [
            'name' => $validated['name'],
            'discretion' => $validated['description'] ?? null,
            'main_category' => $validated['main_category'] ?? null,
        ];

        // التعامل مع رفع الصورة
        if ($request->hasFile('image')) {
            $data['attachments'] = $this->uploadImage('assets/uploads/categories', $request->file('image'));
        }

        $category = Category::create($data);

        OperationLog::record('created', 'category', $category, 'تم إنشاء صنف **'.$category->name.'**');

        return redirect()->route('store_categories.index')
            ->with('success', 'تم إضافة الصنف بنجاح');
    }

    /**
     * رفع الصورة وحفظها
     */
    private function uploadImage($folder, $image)
    {
        $path = public_path($folder);
        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }

        // الامتداد من نوع الملف الفعلي لا من اسمه — ملفٌ باسم `x.php` لا يُحفظ `.php`
        $fileExtension = $image->extension() ?: 'png';
        $fileName = time().rand(1, 99).'.'.$fileExtension;
        $image->move($path, $fileName);

        return $folder.'/'.$fileName;
    }

    /**
     * حذف الصورة القديمة
     */
    private function deleteOldImage($imagePath)
    {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }

    /**
     * عرض تفاصيل صنف
     */
    public function show(Category $category)
    {
        $category->loadCount('products');

        // الصنف الرئيسي
        $parent = null;
        if ($category->main_category) {
            $parent = Category::find($category->main_category);
        }

        // المنتجات في هذا الصنف
        $products = Product::where('category_id', $category->id)
            ->latest()
            ->paginate(10);

        // الأصناف الفرعية
        $subCategories = Category::where('main_category', $category->id)
            ->withCount('products')
            ->get();

        // إحصائيات
        $stats = [
            'products_count' => Product::where('category_id', $category->id)->count(),
            'sub_categories_count' => $subCategories->count(),
            'active_products' => Product::where('category_id', $category->id)->where('status', 'active')->count(),
        ];

        return view('onlinestore::categories.show', compact('category', 'parent', 'products', 'subCategories', 'stats'));
    }

    /**
     * عرض صفحة تعديل صنف
     */
    public function edit(Category $category)
    {
        // بلا الصنف نفسه وبلا أحفاده — وإلا صار أباً لنفسه
        $mainCategories = $this->mainCategoriesQuery()
            ->whereNotIn('id', $this->selfAndDescendantIds($category))
            ->select('id', 'name')
            ->get();

        return view('onlinestore::categories.edit', compact('category', 'mainCategories'));
    }

    /**
     * تحديث صنف
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'main_category' => 'nullable|integer|exists:categories,id',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ], [], self::ATTRIBUTES);

        // صنفٌ أبوه هو نفسه (أو أحد أحفاده) حلقةٌ تُسقط كل شاشةٍ تمشي في الشجرة
        if (! empty($validated['main_category'])
            && in_array((int) $validated['main_category'], $this->selfAndDescendantIds($category), true)) {
            return redirect()->back()->withInput()
                ->withErrors(['main_category' => 'لا يمكن جعل الصنف تابعاً لنفسه أو لأحد أصنافه الفرعية.']);
        }

        // تجهيز البيانات للتحديث
        $data = [
            'name' => $validated['name'],
            'discretion' => $validated['description'] ?? null,
            'main_category' => $validated['main_category'] ?? null,
        ];

        // التعامل مع رفع الصورة الجديدة
        if ($request->hasFile('image')) {
            // حذف الصورة القديمة
            $this->deleteOldImage($category->attachments);
            $data['attachments'] = $this->uploadImage('assets/uploads/categories', $request->file('image'));
        }

        $category->update($data);

        OperationLog::record('updated', 'category', $category, 'تم تعديل صنف **'.$category->name.'**');

        return redirect()->route('store_categories.index')
            ->with('success', 'تم تحديث الصنف بنجاح');
    }

    /**
     * حذف صنف
     */
    public function destroy(Category $category)
    {
        // التحقق من وجود منتجات مرتبطة
        if (Product::where('category_id', $category->id)->exists()) {
            return redirect()->route('store_categories.index')
                ->with('error', 'لا يمكن حذف الصنف لوجود منتجات مرتبطة به');
        }

        // التحقق من وجود أصناف فرعية (الصنف نفسه لا يُحسب ابناً لنفسه)
        if (Category::where('main_category', $category->id)->where('id', '!=', $category->id)->exists()) {
            return redirect()->route('store_categories.index')
                ->with('error', 'لا يمكن حذف الصنف لوجود أصناف فرعية مرتبطة به');
        }

        // حذف الصورة
        $this->deleteOldImage($category->attachments);

        OperationLog::record('deleted', 'category', $category, 'تم حذف صنف **'.$category->name.'**');

        $category->delete();

        return redirect()->route('store_categories.index')
            ->with('success', 'تم حذف الصنف بنجاح');
    }

    /**
     * تبديل حالة التفعيل — المسار `store_categories.toggle-status` كان معرَّفاً
     * بلا دالةٍ تقابله، فأيُّ نداءٍ له خطأ 500.
     */
    public function toggleStatus(Category $category)
    {
        $category->is_active = ! $category->is_active;
        $category->save();

        OperationLog::record('updated', 'category', $category, ($category->is_active ? 'تم تفعيل صنف **' : 'تم تعطيل صنف **').$category->name.'**');

        return response()->json([
            'success' => true,
            'message' => $category->is_active ? 'تم تفعيل الصنف' : 'تم تعطيل الصنف',
            'is_active' => (bool) $category->is_active,
        ]);
    }

    /**
     * حذف صورة الصنف
     */
    public function removeImage(Category $category)
    {
        if ($category->attachments) {
            $this->deleteOldImage($category->attachments);
            $category->update(['attachments' => null]);

            OperationLog::record('updated', 'category', $category, 'تم حذف صورة صنف **'.$category->name.'**');

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الصورة بنجاح',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'لا توجد صورة لحذفها',
        ], 404);
    }
}
