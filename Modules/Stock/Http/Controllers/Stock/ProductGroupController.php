<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\OperationLog;

class ProductGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:stock.product_groups.view', ['only' => ['index', 'getData']]);
        $this->middleware('permission.role:stock.product_groups.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.product_groups.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.product_groups.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:stock.product_groups.toggle_status', ['only' => ['toggleStatus']]);
    }

    /**
     * عرض قائمة المجموعات
     */
    public function index()
    {
        try {
            // جلب المجموعات الرئيسية مع الأبناء
            $groups = ProductGroup::with(['children', 'parent'])
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get();

            return view('stock::product_groups.index', compact('groups'));
        } catch (\Exception $e) {
            Log::error('Error loading product groups: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء تحميل المجموعات');
        }
    }

    /**
     * عرض صفحة إنشاء مجموعة جديدة
     */
    public function create()
    {
        try {
            // جلب جميع المجموعات لاختيار الأب
            $parentGroups = ProductGroup::active()
                ->orderBy('name')
                ->get();

            return view('stock::product_groups.create', compact('parentGroups'));
        } catch (\Exception $e) {
            Log::error('Error loading create group page: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء تحميل الصفحة');
        }
    }

    /**
     * حفظ مجموعة جديدة
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:product_groups,id',
        ], [
            'name.required' => 'اسم المجموعة مطلوب',
            'parent_id.exists' => 'المجموعة الأب غير موجودة',
        ]);

        try {
            DB::beginTransaction();

            // حساب المستوى بناءً على الأب
            $level = 1;
            if ($request->parent_id) {
                $parent = ProductGroup::find($request->parent_id);
                $level = $parent->level + 1;
            }

            $group = ProductGroup::create([
                'name' => $request->name,
                'code' => $request->code,
                'parent_id' => $request->parent_id,
                'level' => $level,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            DB::commit();

            return redirect()->route('product_groups.index')
                ->with('success', 'تم إنشاء المجموعة بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'store');
            DB::rollBack();
            Log::error('Error creating product group: ' . $e->getMessage());
            return back()->withInput()->with('error', 'حدث خطأ أثناء حفظ المجموعة');
        }
    }

    /**
     * عرض صفحة تعديل مجموعة
     */
    public function edit($id)
    {
        try {
            $group = ProductGroup::with(['parent', 'children'])->findOrFail($id);
            
            // جلب المجموعات المحتملة للأب (استثناء المجموعة الحالية وأبنائها)
            $parentGroups = ProductGroup::active()
                ->where('id', '!=', $id)
                ->orderBy('name')
                ->get()
                ->filter(function($parentGroup) use ($group) {
                    // استثناء أبناء المجموعة الحالية
                    $descendants = $this->getAllDescendants($group);
                    return !in_array($parentGroup->id, $descendants);
                });

            return view('stock::product_groups.edit', compact('group', 'parentGroups'));
        } catch (\Exception $e) {
            Log::error('Error loading edit group page: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء تحميل الصفحة');
        }
    }

    /**
     * تحديث مجموعة
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:product_groups,id',
        ], [
            'name.required' => 'اسم المجموعة مطلوب',
            'parent_id.exists' => 'المجموعة الأب غير موجودة',
        ]);

        try {
            DB::beginTransaction();

            $group = ProductGroup::findOrFail($id);

            // التحقق من عدم جعل المجموعة ابنة لنفسها أو لأحد أبنائها
            if ($request->parent_id) {
                $descendants = $this->getAllDescendants($group);
                if (in_array($request->parent_id, $descendants) || $request->parent_id == $id) {
                    return back()->withInput()->with('error', 'لا يمكن جعل المجموعة ابنة لنفسها أو لأحد أبنائها');
                }
            }

            // حساب المستوى الجديد
            $level = 1;
            if ($request->parent_id) {
                $parent = ProductGroup::find($request->parent_id);
                $level = $parent->level + 1;
            }

            $group->update([
                'name' => $request->name,
                'code' => $request->code,
                'parent_id' => $request->parent_id,
                'level' => $level,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            // تحديث مستويات الأبناء
            $this->updateChildrenLevels($group);

            DB::commit();

            return redirect()->route('product_groups.index')
                ->with('success', 'تم تحديث المجموعة بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'update');
            DB::rollBack();
            Log::error('Error updating product group: ' . $e->getMessage());
            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث المجموعة');
        }
    }

    /**
     * حذف مجموعة
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $group = ProductGroup::findOrFail($id);

            // التحقق من عدم وجود تصنيفات مرتبطة
            if ($group->classifications()->count() > 0) {
                return back()->with('error', 'لا يمكن حذف المجموعة لوجود تصنيفات مرتبطة بها');
            }

            // التحقق من عدم وجود مجموعات فرعية
            if ($group->children()->count() > 0) {
                return back()->with('error', 'لا يمكن حذف المجموعة لوجود مجموعات فرعية تابعة لها');
            }

            $group->delete();

            DB::commit();

            return redirect()->route('product_groups.index')
                ->with('success', 'تم حذف المجموعة بنجاح');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'product_log', 'destroy');
            DB::rollBack();
            Log::error('Error deleting product group: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء حذف المجموعة');
        }
    }

    /**
     * تبديل حالة المجموعة
     */
    public function toggleStatus($id)
    {
        try {
            $group = ProductGroup::findOrFail($id);
            $group->update(['is_active' => !$group->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير حالة المجموعة بنجاح',
                'is_active' => $group->is_active
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling group status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير الحالة'
            ], 500);
        }
    }

    /**
     * الحصول على جميع الأبناء (recursive)
     */
    private function getAllDescendants($group)
    {
        $descendants = [];
        foreach ($group->children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, $this->getAllDescendants($child));
        }
        return $descendants;
    }

    /**
     * تحديث مستويات الأبناء (recursive)
     */
    private function updateChildrenLevels($group)
    {
        foreach ($group->children as $child) {
            $child->update(['level' => $group->level + 1]);
            $this->updateChildrenLevels($child);
        }
    }

    /**
     * الحصول على البيانات عبر AJAX
     */
    public function getData(Request $request)
    {
        try {
            $groups = ProductGroup::with(['parent', 'children'])
                ->when($request->search, function($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%')
                          ->orWhere('code', 'like', '%' . $request->search . '%');
                })
                ->orderBy('level')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $groups
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting groups data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات'
            ], 500);
        }
    }
}
