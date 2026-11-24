<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Store\Brand;
use App\Support\OperationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class BrandController extends Controller
{
    /*
        نطاقُ الرؤية: المنتجاتُ المعروضة هنا تتبع مستودعاتِ المستخدم كما
        تتبعها شاشةُ المنتجات — ومن يملك التجاوز أو ليس موظفاً يرى الكلّ.
    */
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        $this->middleware('permission.role:stock.brands.view', ['only' => ['index', 'show', 'search']]);
        $this->middleware('permission.role:stock.brands.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.brands.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.brands.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:stock.brands.toggle_status', ['only' => ['toggleStatus', 'toggleFeatured']]);
    }

    /**
     * عرض قائمة البراندات
     */
    public function index(Request $request): View
    {
        $query = Brand::query();

        // البحث
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // التصفية حسب الحالة
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        // التصفية حسب المميزة
        if ($request->filled('featured') && $request->featured === 'yes') {
            $query->featured();
        }

        // الترتيب
        $sortBy = $request->get('sort', 'sort_order');
        $sortDirection = $request->get('direction', 'asc');

        if ($sortBy === 'sort_order') {
            $query->ordered();
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $brands = $query->paginate(15);
        $statuses = ['active' => 'نشط', 'inactive' => 'غير نشط'];

        return view('stock::brands.index', compact('brands', 'statuses'));
    }

    /**
     * عرض نموذج الإنشاء
     */
    public function create(): View
    {
        $statuses = ['active' => 'نشط', 'inactive' => 'غير نشط'];

        return view('stock::brands.create', compact('statuses'));
    }

    /**
     * حفظ بيانات البراند الجديد
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:brands,name',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'website' => 'nullable|url|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
        ], [
            'name.required' => 'اسم البراند مطلوب',
            'name.unique' => 'هذا البراند موجود بالفعل',
            'logo.image' => 'يجب أن تكون الملف صورة',
            'logo.max' => 'حجم الصورة لا يجب أن يتجاوز 2 MB',
            'website.url' => 'رابط الموقع غير صحيح',
        ]);

        try {
            // رفع الشعار إذا تم اختياره
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $filename = time().'_'.Str::slug($request->name).'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('brands', $filename, 'public');
                $validated['logo'] = $path;
            }

            $validated['slug'] = Str::slug($validated['name']);
            $validated['is_active'] = $request->boolean('is_active');
            Brand::create($validated);

            return redirect()->route('stock.brands.index')
                ->with('success', 'تم إنشاء البراند بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'store');

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * عرض تفاصيل البراند
     */
    public function show(Brand $brand): View
    {
        /*
            **قيمةُ المخزون كانت تُجمع من المستودعات كلِّها.**

            فأمينُ مستودعٍ واحد يفتح صفحةَ البراند فيقرأ قيمةَ مخزون المنشأة
            منه وكمّيتَه — رقمٌ لا يخصّه ولا يراه في أيّ شاشةٍ أخرى.

            والحصرُ في الجمع يقع على `store_house_id` لا على المنتج وحده:
            صنفٌ في مستودعَين يُحسب منه ما في مستودعات المستخدم فقط.
        */
        $warehouseIds = $this->accessibleWarehouseIds();

        $products = $this->scopeToProductVisibility($brand->products())->paginate(10);

        $stockQuery = fn () => $brand->products()
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('product_details.store_house_id', $warehouseIds ?: [0]));

        $totalStockValue = $stockQuery()->sum(DB::raw('product_details.quantity * product_details.unit_price'));
        $totalStockQuantity = $stockQuery()->sum('product_details.quantity');

        return view('stock::brands.show', compact('brand', 'products', 'totalStockValue', 'totalStockQuantity'));
    }

    /**
     * عرض نموذج التعديل
     */
    public function edit(Brand $brand): View
    {
        $statuses = ['active' => 'نشط', 'inactive' => 'غير نشط'];

        return view('stock::brands.edit', compact('brand', 'statuses'));
    }

    /**
     * تحديث بيانات البراند
     */
    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:brands,name,'.$brand->id,
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'website' => 'nullable|url|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
        ], [
            'name.required' => 'اسم البراند مطلوب',
            'name.unique' => 'هذا اسم البراند مستخدم بالفعل',
            'logo.image' => 'يجب أن تكون الملف صورة',
            'logo.max' => 'حجم الصورة لا يجب أن يتجاوز 2 MB',
        ]);

        try {
            // حذف الشعار القديم إذا تم تحميل واحد جديد
            if ($request->hasFile('logo')) {
                if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
                    Storage::disk('public')->delete($brand->logo);
                }

                $file = $request->file('logo');
                $filename = time().'_'.Str::slug($request->name).'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('brands', $filename, 'public');
                $validated['logo'] = $path;
            } else {
                // الاحتفاظ بالشعار القديم
                $validated['logo'] = $brand->logo;
            }

            $brand->update($validated);

            return redirect()->route('stock.brands.index')
                ->with('success', 'تم تحديث البراند بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'update');

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * حذف البراند
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        try {
            // التحقق من وجود منتجات مرتبطة
            if ($brand->products()->exists()) {
                return redirect()->back()
                    ->with('error', 'لا يمكن حذف البراند لأنه يحتوي على منتجات مرتبطة به');
            }

            // حذف الشعار
            if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
                Storage::disk('public')->delete($brand->logo);
            }

            $brand->delete();

            return redirect()->route('stock.brands.index')
                ->with('success', 'تم حذف البراند بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'destroy');

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * تبديل حالة البراند
     */
    public function toggleStatus(Brand $brand): RedirectResponse
    {
        try {
            // `App\Models\Store\Brand` يحمل `is_active` منطقياً لا `status` نصّياً
            $brand->update(['is_active' => ! $brand->is_active]);

            $message = $brand->is_active ? 'تم تفعيل البراند' : 'تم تعطيل البراند';

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Brands', 'toggleStatus');

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * تبديل المميز
     */
    public function toggleFeatured(Brand $brand): RedirectResponse
    {
        try {
            $brand->update(['is_featured' => ! $brand->is_featured]);
            $message = $brand->is_featured ? 'تم تعليم البراند كمميز' : 'تم إلغاء تعليم البراند كمميز';

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Brands', 'toggleFeatured');

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * البحث عن البراندات (AJAX)
     */
    public function search(Request $request)
    {
        $search = $request->get('q', '');
        $brands = Brand::where('status', 'active')
            ->search($search)
            ->limit(10)
            ->get(['id', 'name', 'logo']);

        return response()->json($brands);
    }
}
