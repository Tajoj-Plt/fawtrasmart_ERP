<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Models\Product;
use App\Models\Store\Brand;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * كنترولر إدارة العلامات التجارية (البراندات)
 * Brand Management Controller
 */
class BrandController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.brands.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:online_store.brands.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:online_store.brands.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:online_store.brands.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:online_store.brands.toggle_status', ['only' => ['toggleStatus', 'toggleFeatured']]);
    }

    /** أسماء الحقول بالعربية في رسائل التحقق. */
    private const ATTRIBUTES = [
        'name' => 'اسم العلامة التجارية',
        'slug' => 'الرابط (Slug)',
        'description' => 'الوصف',
        'logo' => 'الشعار',
        'website' => 'الموقع الإلكتروني',
        'is_active' => 'حالة التفعيل',
        'is_featured' => 'علامة مميزة',
        'sort_order' => 'الترتيب',
        'meta_title' => 'عنوان SEO',
        'meta_keywords' => 'الكلمات المفتاحية',
        'meta_description' => 'وصف SEO',
    ];

    /**
     * رابطٌ فريد للعلامة. `brands.slug` فريدٌ في القاعدة، والرابط المولَّد من
     * الاسم لم يكن يُفحص: علامتان بالاسم نفسه = خطأ 500 بدل رسالة.
     */
    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = Str::slug($base) ?: 'brand';
        $slug = $base;
        $i = 2;

        while (Brand::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * عرض قائمة جميع البراندات
     */
    public function index(Request $request)
    {
        // `?search[]=x` في الرابط يصل مصفوفةً والقالب يطبعه نصّاً فيسقط بـ500
        $this->dropNonScalarFilters($request, array_keys($request->query()));

        $query = Brand::query();

        // البحث
        if ($request->filled('search') && is_string($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // فلترة حسب المميز
        if ($request->filled('featured')) {
            $query->where('is_featured', $request->featured === 'yes');
        }

        $brands = $query->withCount('products')
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('onlinestore::brands.index', compact('brands'));
    }

    /**
     * عرض صفحة إنشاء براند جديد
     */
    public function create()
    {
        return view('onlinestore::brands.create');
    }

    /**
     * حفظ براند جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'slug' => 'nullable|string|max:255|unique:brands,slug',
            'description' => 'nullable|string',
            'logo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,bmp,svg|max:2048',
            'website' => 'nullable|url|max:255',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ], [], self::ATTRIBUTES);

        // توليد slug إذا لم يتم تقديمه — فريداً دائماً
        if (empty($validated['slug'])) {
            $validated['slug'] = $this->uniqueSlug($validated['name']);
        }

        // التعامل مع رفع الشعار
        if ($request->hasFile('logo')) {
            $validated['logo'] = $this->uploadImage('assets/uploads/brands', $request->file('logo'));
        }

        // تعيين القيم الافتراضية
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_featured'] = $request->boolean('is_featured', false);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        Brand::create($validated);

        return redirect()->route('brands.index')
            ->with('success', 'تم إنشاء العلامة التجارية بنجاح');
    }

    /**
     * رفع الصورة وحفظها
     */
    private function uploadImage($folder, $image)
    {
        // التأكد من وجود المجلد
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
     * عرض تفاصيل براند
     */
    public function show(Brand $brand)
    {
        $brand->loadCount('products');

        return view('onlinestore::brands.show', compact('brand'));
    }

    /**
     * عرض صفحة تعديل براند
     */
    public function edit(Brand $brand)
    {
        return view('onlinestore::brands.edit', compact('brand'));
    }

    /**
     * تحديث براند
     */
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,'.$brand->id,
            'slug' => 'nullable|string|max:255|unique:brands,slug,'.$brand->id,
            'description' => 'nullable|string',
            'logo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,bmp,svg|max:2048',
            'website' => 'nullable|url|max:255',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ], [], self::ATTRIBUTES);

        // توليد slug إذا لم يتم تقديمه — فريداً دائماً
        if (empty($validated['slug'])) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $brand->id);
        }

        // التعامل مع رفع الشعار
        if ($request->hasFile('logo')) {
            // حذف الشعار القديم إذا وجد
            $this->deleteOldImage($brand->logo);
            $validated['logo'] = $this->uploadImage('assets/uploads/brands', $request->file('logo'));
        }

        // تعيين القيم — ما لم يُرسَل يبقى على حاله
        $validated['is_active'] = $request->boolean('is_active', (bool) $brand->is_active);
        $validated['is_featured'] = $request->boolean('is_featured', (bool) $brand->is_featured);
        $validated['sort_order'] = $validated['sort_order'] ?? ($brand->sort_order ?? 0);

        $brand->update($validated);

        return redirect()->route('brands.index')
            ->with('success', 'تم تحديث العلامة التجارية بنجاح');
    }

    /**
     * حذف براند
     */
    public function destroy(Brand $brand)
    {
        // علامةٌ عليها منتجات لا تُحذف — كانت تُحذف وتبقى المنتجات معلّقةً على معرّفٍ غير موجود
        $productsCount = Product::where('brand_id', $brand->id)->count();
        if ($productsCount > 0) {
            return redirect()->route('brands.index')
                ->with('error', 'لا يمكن حذف العلامة التجارية «'.$brand->name.'» لارتباط '.$productsCount.' منتج بها. انقل المنتجات إلى علامة أخرى أو عطّل العلامة بدل حذفها.');
        }

        // حذف الشعار إذا وجد
        $this->deleteOldImage($brand->logo);

        $brand->delete();

        return redirect()->route('brands.index')
            ->with('success', 'تم حذف العلامة التجارية بنجاح');
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(Brand $brand)
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return response()->json([
            'success' => true,
            'message' => $brand->is_active ? 'تم تفعيل العلامة التجارية' : 'تم تعطيل العلامة التجارية',
            'is_active' => $brand->is_active,
        ]);
    }

    /**
     * تبديل حالة المميز
     */
    public function toggleFeatured(Brand $brand)
    {
        $brand->update(['is_featured' => ! $brand->is_featured]);

        return response()->json([
            'success' => true,
            'message' => $brand->is_featured ? 'تم تمييز العلامة التجارية' : 'تم إلغاء تمييز العلامة التجارية',
            'is_featured' => $brand->is_featured,
        ]);
    }
}
