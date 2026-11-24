<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryUnit;
use App\Models\Unit;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Support\OperationLog;

class CategoryUnitController extends Controller
{
    /**
     * وحدات الصنف جزءٌ من شاشة الأصناف — نفس صلاحيات [CategoryController].
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.categories.view', ['only' => ['index']]);
        $this->middleware('permission.role:stock.categories.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.categories.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.categories.delete', ['only' => ['delete']]);
    }

    /**
     * عرض قائمة وحدات الصنف
     */
    public function index($category_id)
    {
        $category = Category::with(['categoryUnits.unit', 'productGroup'])->findOrFail($category_id);
        $units = $category->categoryUnits()->with('unit')->orderBy('conversion_factor')->get();
        
        return view('stock::category_units.index', compact('category', 'units'));
    }

    /**
     * صفحة إضافة وحدة جديدة للصنف
     */
    public function create($category_id)
    {
        $category = Category::findOrFail($category_id);
        
        // الوحدات المتاحة (التي لم تُضاف لهذا الصنف بعد)
        $usedUnitIds = $category->categoryUnits()->pluck('unit_id')->toArray();
        $availableUnits = Unit::active()
                              ->whereNotIn('id', $usedUnitIds)
                              ->orderBy('name')
                              ->get();
        
        return view('stock::category_units.create', compact('category', 'availableUnits'));
    }

    /**
     * صفحة تعديل وحدة
     */
    public function edit($category_id, $id)
    {
        $category = Category::findOrFail($category_id);
        $categoryUnit = CategoryUnit::where('category_id', $category_id)
                                   ->with('unit')
                                   ->findOrFail($id);
        
        return view('stock::category_units.edit', compact('category', 'categoryUnit'));
    }

    /**
     * حفظ وحدة جديدة للصنف
     */
    public function store(Request $request, $category_id)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'is_base_unit' => 'nullable|boolean',
        ], [
            'unit_id.required' => 'يجب اختيار الوحدة',
            'unit_id.exists' => 'الوحدة المختارة غير موجودة',
            'conversion_factor.required' => 'معامل التحويل مطلوب',
            'conversion_factor.numeric' => 'معامل التحويل يجب أن يكون رقمًا',
            'conversion_factor.min' => 'معامل التحويل يجب أن يكون أكبر من صفر',
        ]);

        $category = Category::findOrFail($category_id);
        $unit = Unit::findOrFail($request->unit_id);

        // التحقق من عدم وجود هذه الوحدة مسبقًا
        if (CategoryUnit::where('category_id', $category_id)->where('unit_id', $request->unit_id)->exists()) {
            return redirect()->back()->with(['error' => 'هذه الوحدة مضافة للصنف مسبقًا!']);
        }

        // إذا كانت وحدة أساسية، إلغاء تحديد الوحدات الأخرى كأساسية
        if ($request->has('is_base_unit') && $request->is_base_unit) {
            CategoryUnit::where('category_id', $category_id)
                       ->update(['is_base_unit' => false]);
        }

        $categoryUnit = CategoryUnit::create([
            'category_id' => $category_id,
            'unit_id' => $request->unit_id,
            'conversion_factor' => $request->conversion_factor,
            'is_base_unit' => $request->has('is_base_unit'),
            'is_active' => true,
        ]);

        // تسجيل النشاط
        OperationLog::record(
            'logged',
            'product_log',
            $categoryUnit->id,
            'تم إضافة وحدة **' . $unit->name . '** للصنف **' . $category->name . '**',
        );

        return redirect()->route('category_units.index', $category_id)
                        ->with(['success' => 'تم إضافة الوحدة بنجاح!']);
    }

    /**
     * تحديث وحدة
     */
    public function update(Request $request, $category_id, $id)
    {
        $request->validate([
            'conversion_factor' => 'required|numeric|min:0.0001',
            'is_base_unit' => 'nullable|boolean',
        ], [
            'conversion_factor.required' => 'معامل التحويل مطلوب',
            'conversion_factor.numeric' => 'معامل التحويل يجب أن يكون رقمًا',
            'conversion_factor.min' => 'معامل التحويل يجب أن يكون أكبر من صفر',
        ]);

        $category = Category::findOrFail($category_id);
        $categoryUnit = CategoryUnit::where('category_id', $category_id)->findOrFail($id);
        $unitName = $categoryUnit->unit->name ?? '';

        // إذا كانت وحدة أساسية، إلغاء تحديد الوحدات الأخرى كأساسية
        if ($request->has('is_base_unit') && $request->is_base_unit) {
            CategoryUnit::where('category_id', $category_id)
                       ->where('id', '!=', $id)
                       ->update(['is_base_unit' => false]);
        }

        $categoryUnit->update([
            'conversion_factor' => $request->conversion_factor,
            'is_base_unit' => $request->has('is_base_unit'),
        ]);

        // تسجيل النشاط
        OperationLog::record(
            'logged',
            'product_log',
            $categoryUnit->id,
            'تم تعديل وحدة **' . $unitName . '** للصنف **' . $category->name . '**',
        );

        return redirect()->route('category_units.index', $category_id)
                        ->with(['success' => 'تم تحديث الوحدة بنجاح!']);
    }

    /**
     * حذف وحدة من الصنف
     */
    public function delete($category_id, $id)
    {
        $category = Category::findOrFail($category_id);
        $categoryUnit = CategoryUnit::where('category_id', $category_id)->with('unit')->findOrFail($id);

        // التحقق من وجود وحدات منتج مرتبطة
        if ($categoryUnit->productUnits()->count() > 0) {
            return redirect()->route('category_units.index', $category_id)
                           ->with(['error' => 'لا يمكن حذف الوحدة لوجود منتجات مرتبطة بها!']);
        }

        $unitName = $categoryUnit->unit->name ?? '';

        // تسجيل النشاط
        OperationLog::record(
            'logged',
            'product_log',
            $categoryUnit->id,
            'تم حذف وحدة **' . $unitName . '** من الصنف **' . $category->name . '**',
        );

        $categoryUnit->delete();

        return redirect()->route('category_units.index', $category_id)
                        ->with(['success' => 'تم حذف الوحدة بنجاح!']);
    }
}
