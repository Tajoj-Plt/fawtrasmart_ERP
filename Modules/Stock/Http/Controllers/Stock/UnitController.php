<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Support\OperationLog;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:stock.units.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:stock.units.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.units.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.units.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:stock.units.toggle_status', ['only' => ['toggleStatus']]);
    }

    /**
     * عرض قائمة الوحدات
     */
    public function index(Request $request)
    {
        $query = Unit::query();

        // البحث بالاسم
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('symbol', 'like', '%' . $request->search . '%');
            });
        }

        // الفلترة حسب الحالة
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        $units = $query->withCount('categoryUnits')
                      ->orderBy('name')
                      ->paginate(20);

        return view('stock::units.index', compact('units'));
    }

    /**
     * صفحة إنشاء وحدة جديدة
     */
    public function create()
    {
        return view('stock::units.create');
    }

    /**
     * عرض تفاصيل وحدة
     */
    public function show($id)
    {
        $unit = Unit::with('categoryUnits.category')->withCount('categoryUnits')->findOrFail($id);
        
        return view('stock::units.show', compact('unit'));
    }

    /**
     * صفحة تعديل وحدة
     */
    public function edit($id)
    {
        $unit = Unit::findOrFail($id);
        
        return view('stock::units.edit', compact('unit'));
    }

    /**
     * حفظ وحدة جديدة
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:units,name',
            'symbol' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'اسم الوحدة مطلوب',
            'name.unique' => 'هذا الاسم مستخدم مسبقاً',
            'symbol.max' => 'الرمز يجب ألا يتجاوز 50 حرف',
        ]);

        $unit = Unit::create([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        // تسجيل النشاط
        try {
            OperationLog::record('logged', 'product_log', $unit->id, 'تم إضافة وحدة جديدة **' . $unit->name . '**');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'store');
            // تجاهل خطأ التسجيل
        }

        return redirect()->route('units.index')->with(['success' => 'تم إضافة الوحدة بنجاح!']);
    }

    /**
     * تحديث وحدة
     */
    public function update(Request $request, $id)
    {
        $unit = Unit::findOrFail($id);
        $oldName = $unit->name;

        $request->validate([
            'name' => 'required|string|max:255|unique:units,name,' . $id,
            'symbol' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'اسم الوحدة مطلوب',
            'name.unique' => 'هذا الاسم مستخدم مسبقاً',
            'symbol.max' => 'الرمز يجب ألا يتجاوز 50 حرف',
        ]);

        $unit->update([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        // تسجيل النشاط
        try {
            OperationLog::record(
                'logged',
                'product_log',
                $unit->id,
                'تم تعديل الوحدة من **' . $oldName . '** إلى **' . $unit->name . '**',
            );
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'update');
            // تجاهل خطأ التسجيل
        }

        return redirect()->route('units.index')->with(['success' => 'تم تحديث الوحدة بنجاح!']);
    }

    /**
     * حذف وحدة
     */
    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);

        // التحقق من وجود أصناف مرتبطة
        if ($unit->categoryUnits()->count() > 0) {
            return redirect()->route('units.index')->with(['error' => 'لا يمكن حذف الوحدة لوجود أصناف مرتبطة بها!']);
        }

        $unitName = $unit->name;

        // تسجيل النشاط
        try {
            OperationLog::record('logged', 'product_log', $unit->id, 'تم حذف الوحدة **' . $unitName . '**');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'destroy');
            // تجاهل خطأ التسجيل
        }

        $unit->delete();

        return redirect()->route('units.index')->with(['success' => 'تم حذف الوحدة بنجاح!']);
    }

    /**
     * تبديل حالة التفعيل
     */
    public function toggleStatus($id)
    {
        $unit = Unit::findOrFail($id);
        $unit->is_active = !$unit->is_active;
        $unit->save();

        // تسجيل النشاط
        try {
            OperationLog::record(
                'logged',
                'product_log',
                $unit->id,
                ($unit->is_active ? 'تم تفعيل' : 'تم تعطيل') . ' الوحدة **' . $unit->name . '**',
            );
        } catch (\Exception $e) {
            // تجاهل خطأ التسجيل
        }

        return response()->json([
            'success' => true,
            'is_active' => $unit->is_active,
            'message' => $unit->is_active ? 'تم تفعيل الوحدة' : 'تم تعطيل الوحدة'
        ]);
    }
}
