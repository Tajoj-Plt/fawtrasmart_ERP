<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\CategoryUnit;
use App\Models\ProductGroup;
use App\Models\Unit;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class CategoryController extends Controller
{
    /*
        نطاقُ الرؤية: المنتجاتُ المعروضة هنا تتبع مستودعاتِ المستخدم كما
        تتبعها شاشةُ المنتجات — ومن يملك التجاوز أو ليس موظفاً يرى الكلّ.
    */
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        $this->middleware('permission.role:stock.categories.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:stock.categories.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.categories.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.categories.delete', ['only' => ['delete', 'destroy']]);
        $this->middleware('permission.role:stock.categories.toggle_status', ['only' => ['toggleStatus']]);
    }

    /**
     * عرض قائمة الأصناف
     */
    public function index(Request $request)
    {
        $query = Category::with(['productGroup', 'parent', 'categoryUnits.unit', 'products']);

        // البحث بالاسم
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // الفلترة حسب المجموعة
        if ($request->filled('product_group_id')) {
            $query->where('product_group_id', $request->product_group_id);
        }

        // الفلترة حسب الصنف الرئيسي
        if ($request->filled('main_category')) {
            $query->where('main_category', $request->main_category);
        }

        // الفلترة حسب الحالة
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        $categories = /*
            عدّادُ المنتجات يتبع النطاق: «١٢٠ صنفاً في هذه الفئة» فوق شاشةٍ
            لا يرى فيها المستخدمُ إلّا اثني عشر يقول رقماً ليس رقمَه.
        */
        $query->withCount(['products' => fn ($q) => $this->scopeToProductVisibility($q), 'categoryUnits as units_count'])
            ->orderBy('name')
            ->paginate(15);

        $productGroups = ProductGroup::active()->orderBy('name')->get();
        $parentCategories = Category::whereNull('main_category')->orderBy('name')->get();

        return view('stock::category.index', compact('categories', 'productGroups', 'parentCategories'));
    }

    /**
     * صفحة إنشاء صنف جديد
     */
    public function create()
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        $productGroups = ProductGroup::active()->orderBy('name')->get();
        $units = Unit::active()->orderBy('name')->get();

        // معاينة بلا استهلاك: الرقم يُحجز عند الحفظ لا عند فتح الشاشة
        $nextCode = $this->nextCategoryCode(consume: false);

        return view('stock::category.create', compact('categories', 'productGroups', 'units', 'nextCode'));
    }

    /**
     * عرض تفاصيل صنف
     */
    public function show($id)
    {
        $category = Category::with([
            'productGroup',
            'parent',
            'children',
            'categoryUnits.unit',
            'products',
        ])->withCount(['products' => fn ($q) => $this->scopeToProductVisibility($q), 'categoryUnits as units_count'])->findOrFail($id);

        return view('stock::category.show', compact('category'));
    }

    /**
     * صفحة تعديل صنف
     */
    public function edit($id)
    {
        $category = Category::with(['productGroup', 'parent', 'categoryUnits.unit', 'products'])->findOrFail($id);
        $categories = Category::select('id', 'name')->where('id', '!=', $id)->orderBy('name')->get();
        $productGroups = ProductGroup::active()->orderBy('name')->get();
        $units = Unit::active()->orderBy('name')->get();

        return view('stock::category.edit', compact('category', 'categories', 'productGroups', 'units'));
    }

    /**
     * كود الصنف التالي من قسم «الأصناف» في شاشة ترقيم المستندات.
     *
     * `consume: false` تعاين بلا حجز — للعرض في نموذج الإضافة، فمن يفتح
     * الشاشة ثم يتراجع لا يحرق رقماً وتظهر فجوة في التسلسل.
     *
     * وتتخطّى ما حُجز فعلاً في الجدول: أصنافٌ استُوردت بأكواد سابقة أو
     * عدّادٌ أُعيد ضبطه يجعلان الرقم التالي مكرَّراً. والزيادة تحفظ البادئة
     * وطول الأرقام كما ضُبطا في الإعدادات.
     */
    private function nextCategoryCode(bool $consume = true): string
    {
        $code = $consume
            ? next_document_number('product-category')
            : peek_document_number('product-category');

        $safety = 0;

        while ($safety < 10000 && Category::where('code', $code)->exists()) {
            if (preg_match('/^(.*?)(\\d+)$/', $code, $parts)) {
                $code = $parts[1].str_pad((string) ((int) $parts[2] + 1), strlen($parts[2]), '0', STR_PAD_LEFT);
            } else {
                $code .= '-1';
            }

            $safety++;
        }

        return $code;
    }

    /**
     * حفظ صنف جديد
     */
    public function store(CategoryRequest $request)
    {
        $category = new Category;

        // رفع الصورة
        if ($request->hasFile('attachments')) {
            $category->attachments = $this->uploadImage('assets/uploads/category', $request->file('attachments'));
        }

        $category->name = $request->name;

        /*
            كود الصنف — حقلٌ تلقائي لا يُملى من النموذج.

            **ما كان**

            `$category->code = $request->code;` وحدها — بلا مولّد. والنموذج
            يقول في مكان الكتابة «يُترك فارغاً للتوليد التلقائي»، فمن
            صدّقه حصل على صنفٍ **بلا كود**: لا يظهر في البحث بالكود، ولا
            يُميَّز في التقارير، ولا يُستورَد إليه شيء.

            ثم صار يُولَّد عند الفراغ ويُقبل ما كُتب عند الامتلاء — فبقي
            الكود قابلاً للتخطّي وللتكرار من الشاشة نفسها.

            **ما صار**

            الكود من قسم «الأصناف» في «ترقيم المستندات» وحده — ببادئته وعدد
            خاناته وتصفيره الدوري — يُحجَز عند الحفظ، وما يصل من الطلب
            يُهمَل؛ والحقل في الشاشة للقراءة فقط.
        */
        $category->code = $this->nextCategoryCode();

        $category->product_group_id = $request->product_group_id;
        $category->main_category = $request->main_category;
        $category->discretion = $request->discretion;
        $category->is_active = $request->has('is_active');

        $category->save();

        // حفظ الوحدات
        if ($request->has('units') && is_array($request->units)) {
            foreach ($request->units as $unitData) {
                CategoryUnit::create([
                    'category_id' => $category->id,
                    'unit_id' => $unitData['unit_id'],
                    'conversion_factor' => $unitData['conversion_factor'] ?? 1,
                    'is_base_unit' => ($unitData['is_base_unit'] ?? '0') == '1',
                    'is_active' => true,
                ]);
            }
        }

        // تسجيل النشاط
        try {
            OperationLog::record(
                'logged',
                'product_log',
                $category->id,
                'تم إضافة صنف جديد **'.$request->name.'**',
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'store');
            // تجاهل خطأ التسجيل
        }

        return redirect()->route('category.index')->with(['success' => 'تم إضافة الصنف بنجاح!']);
    }

    /**
     * تحديث صنف
     */
    public function update(CategoryRequest $request, $id)
    {
        $category = Category::findOrFail($id);
        $oldName = $category->name;

        // رفع الصورة
        if ($request->hasFile('attachments')) {
            // حذف الصورة القديمة إن وجدت
            if ($category->attachments && file_exists(public_path($category->attachments))) {
                unlink(public_path($category->attachments));
            }
            $category->attachments = $this->uploadImage('assets/uploads/category', $request->file('attachments'));
        }

        $category->name = $request->name;

        // الكود لا يُعاد ترقيمه في التعديل: مرجعٌ في التقارير والاستيراد
        // وقوائم الاختيار. وصنفٌ قديم بلا كود يُولَّد له واحد.
        $category->code = $category->code ?: $this->nextCategoryCode();

        $category->product_group_id = $request->product_group_id;
        $category->main_category = $request->main_category;
        $category->discretion = $request->discretion;
        $category->is_active = $request->has('is_active');

        $category->save();

        // تحديث الوحدات
        if ($request->has('units') && is_array($request->units)) {
            // الحصول على الوحدات الحالية
            $currentUnitIds = $category->categoryUnits()->pluck('unit_id')->toArray();
            $newUnitIds = array_column($request->units, 'unit_id');

            // حذف الوحدات المزالة (فقط التي ليس لها منتجات مرتبطة)
            foreach ($currentUnitIds as $unitId) {
                if (! in_array($unitId, $newUnitIds)) {
                    $categoryUnit = CategoryUnit::where('category_id', $category->id)
                        ->where('unit_id', $unitId)
                        ->first();
                    if ($categoryUnit && $categoryUnit->productUnits()->count() == 0) {
                        $categoryUnit->delete();
                    }
                }
            }

            // إضافة أو تحديث الوحدات
            foreach ($request->units as $unitData) {
                CategoryUnit::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'unit_id' => $unitData['unit_id'],
                    ],
                    [
                        'conversion_factor' => $unitData['conversion_factor'] ?? 1,
                        'is_base_unit' => ($unitData['is_base_unit'] ?? '0') == '1',
                        'is_active' => true,
                    ]
                );
            }
        }

        // تسجيل النشاط
        try {
            OperationLog::record(
                'logged',
                'product_log',
                $category->id,
                'تم تعديل الصنف من **'.$oldName.'** إلى **'.$request->name.'**',
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'update');
            // تجاهل خطأ التسجيل
        }

        return redirect()->route('category.index')->with(['success' => 'تم تحديث الصنف بنجاح!']);
    }

    /**
     * حذف صنف
     */
    public function delete($id)
    {
        $category = Category::findOrFail($id);

        // التحقق من وجود منتجات مرتبطة
        if ($category->products()->count() > 0) {
            return redirect()->route('category.index')->with(['error' => 'لا يمكن حذف الصنف لوجود منتجات مرتبطة به!']);
        }

        // حذف الصورة
        if ($category->attachments && file_exists(public_path($category->attachments))) {
            unlink(public_path($category->attachments));
        }

        // تسجيل النشاط
        try {
            OperationLog::record('logged', 'product_log', $category->id, 'تم حذف الصنف **'.$category->name.'**');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'delete');
            // تجاهل خطأ التسجيل
        }

        $category->delete();

        return redirect()->route('category.index')->with(['success' => 'تم حذف الصنف بنجاح!']);
    }

    /**
     * تبديل حالة التفعيل
     */
    public function toggleStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->is_active = ! $category->is_active;
        $category->save();

        // تسجيل النشاط
        try {
            OperationLog::record(
                'logged',
                'product_log',
                $category->id,
                ($category->is_active ? 'تم تفعيل' : 'تم تعطيل').' الصنف **'.$category->name.'**',
            );
        } catch (\Exception $e) {
            // تجاهل خطأ التسجيل
        }

        return response()->json([
            'success' => true,
            'is_active' => $category->is_active,
            'message' => $category->is_active ? 'تم تفعيل الصنف' : 'تم تعطيل الصنف',
        ]);
    }

    /**
     * حذف صنف (DELETE request)
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // التحقق من وجود منتجات مرتبطة
        if ($category->products()->count() > 0) {
            return redirect()->route('category.index')->with(['error' => 'لا يمكن حذف الصنف لوجود منتجات مرتبطة به!']);
        }

        // التحقق من وجود أصناف فرعية
        if ($category->children()->count() > 0) {
            return redirect()->route('category.index')->with(['error' => 'لا يمكن حذف الصنف لوجود أصناف فرعية مرتبطة به!']);
        }

        // حذف وحدات الصنف
        $category->categoryUnits()->delete();

        // حذف الصورة
        if ($category->attachments && file_exists(public_path($category->attachments))) {
            unlink(public_path($category->attachments));
        }

        // تسجيل النشاط
        try {
            OperationLog::record('logged', 'product_log', $category->id, 'تم حذف الصنف **'.$category->name.'**');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'destroy');
            // تجاهل خطأ التسجيل
        }

        $category->delete();

        return redirect()->route('category.index')->with(['success' => 'تم حذف الصنف بنجاح!']);
    }

    /**
     * رفع صورة
     */
    private function uploadImage($folder, $image)
    {
        $dest = public_path($folder);
        if (! is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $fileName = time().rand(1, 99).'.'.$image->getClientOriginalExtension();
        $image->move($dest, $fileName);

        return $folder.'/'.$fileName;
    }
}
