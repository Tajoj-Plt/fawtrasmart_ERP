<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\Reward;
use App\Models\Ecosystem\PointsTier;
use App\Models\Product;
use App\Models\Branch;
use App\Enums\UserType;
use App\Enums\RewardType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RewardController extends Controller
{
    /**
     * عرض قائمة المكافآت
     */
    public function index(Request $request)
    {
        $query = Reward::with(['minTier', 'branch', 'product', 'creator']);

        // فلترة حسب نوع المستخدم
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // فلترة حسب نوع المكافأة
        if ($request->filled('reward_type')) {
            $query->where('reward_type', $request->reward_type);
        }

        // فلترة حسب الحالة
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // فلترة حسب الفرع
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // الترتيب
        $query->orderBy('is_featured', 'desc')
              ->orderBy('created_at', 'desc');

        $rewards = $query->paginate(15)->withQueryString();

        // الإحصائيات
        $stats = [
            'total' => Reward::count(),
            'active' => Reward::where('is_active', true)->count(),
            'featured' => Reward::where('is_featured', true)->count(),
            'total_redeemed' => Reward::sum('quantity_redeemed'),
        ];

        // البيانات المساعدة للفلاتر
        $userTypes = UserType::values();
        $rewardTypes = RewardType::values();
        $branches = Branch::orderBy('name')->get();

        return view('ecosystem::points.rewards.index', compact(
            'rewards',
            'stats',
            'userTypes',
            'rewardTypes',
            'branches'
        ));
    }

    /**
     * عرض نموذج إضافة مكافأة جديدة
     */
    public function create()
    {
        $userTypes = UserType::values();
        $rewardTypes = RewardType::cases();
        $tiers = PointsTier::active()->ordered()->get();
        $branches = Branch::orderBy('name')->get();
        $products = Product::orderBy('name')->get();
        $leaveTypes = $this->leaveTypes();

        return view('ecosystem::points.rewards.create', compact(
            'userTypes',
            'rewardTypes',
            'tiers',
            'branches',
            'products',
            'leaveTypes'
        ));
    }

    /**
     * حفظ مكافأة جديدة
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'points_required' => 'required|integer|min:1',
            'reward_type' => 'required|string|in:' . implode(',', RewardType::values()),
            'reward_value' => 'nullable|numeric|min:0',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'validity_days' => 'nullable|integer|min:1',
            'product_id' => 'nullable|exists:products,id',
            'quantity_available' => 'nullable|integer|min:0',
            'max_per_user' => 'nullable|integer|min:1',
            'min_tier_id' => 'nullable|exists:points_tiers,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'terms_conditions' => 'nullable|array',
            'terms_conditions.*' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $this->assertHrRewardRules($request);
        if (($validated['reward_type'] ?? null) !== RewardType::LEAVE_DAY->value) {
            $validated['leave_type_id'] = null;
        }

        // معالجة الصورة
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('rewards', 'public');
        }

        // تنظيف الشروط والأحكام
        if (isset($validated['terms_conditions'])) {
            $validated['terms_conditions'] = array_filter($validated['terms_conditions']);
        }

        $validated['created_by'] = Auth::id();
        $validated['quantity_redeemed'] = 0;

        Reward::create($validated);

        return redirect()
            ->route('ecosystem.points.rewards.index')
            ->with('success', 'تم إضافة المكافأة بنجاح');
    }

    /**
     * عرض تفاصيل مكافأة
     */
    public function show($id)
    {
        $reward = Reward::with(['minTier', 'branch', 'product', 'creator', 'redemptions.employee', 'redemptions.client'])
            ->findOrFail($id);

        // إحصائيات الاستبدال
        $redemptionStats = [
            'total' => $reward->redemptions->count(),
            'completed' => $reward->redemptions->where('status', 'completed')->count(),
            'pending' => $reward->redemptions->where('status', 'pending')->count(),
            'cancelled' => $reward->redemptions->where('status', 'cancelled')->count(),
        ];

        // آخر عمليات الاستبدال
        $recentRedemptions = $reward->redemptions()
            ->with(['employee', 'client'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('ecosystem::points.rewards.show', compact(
            'reward',
            'redemptionStats',
            'recentRedemptions'
        ));
    }

    /**
     * عرض نموذج تعديل مكافأة
     */
    public function edit($id)
    {
        $reward = Reward::findOrFail($id);
        $userTypes = UserType::values();
        $rewardTypes = RewardType::cases();
        $tiers = PointsTier::active()->ordered()->get();
        $branches = Branch::orderBy('name')->get();
        $products = Product::orderBy('name')->get();
        $leaveTypes = $this->leaveTypes();

        return view('ecosystem::points.rewards.edit', compact(
            'reward',
            'userTypes',
            'rewardTypes',
            'tiers',
            'branches',
            'products',
            'leaveTypes'
        ));
    }

    /**
     * تحديث مكافأة
     */
    public function update(Request $request, $id)
    {
        $reward = Reward::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'points_required' => 'required|integer|min:1',
            'reward_type' => 'required|string|in:' . implode(',', RewardType::values()),
            'reward_value' => 'nullable|numeric|min:0',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'validity_days' => 'nullable|integer|min:1',
            'product_id' => 'nullable|exists:products,id',
            'quantity_available' => 'nullable|integer|min:0',
            'max_per_user' => 'nullable|integer|min:1',
            'min_tier_id' => 'nullable|exists:points_tiers,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'terms_conditions' => 'nullable|array',
            'terms_conditions.*' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $this->assertHrRewardRules($request);
        if (($validated['reward_type'] ?? null) !== RewardType::LEAVE_DAY->value) {
            $validated['leave_type_id'] = null;
        }

        // معالجة الصورة
        if ($request->hasFile('image')) {
            // حذف الصورة القديمة
            if ($reward->image) {
                Storage::disk('public')->delete($reward->image);
            }
            $validated['image'] = $request->file('image')->store('rewards', 'public');
        }

        // تنظيف الشروط والأحكام
        if (isset($validated['terms_conditions'])) {
            $validated['terms_conditions'] = array_filter($validated['terms_conditions']);
        }

        $reward->update($validated);

        return redirect()
            ->route('ecosystem.points.rewards.index')
            ->with('success', 'تم تحديث المكافأة بنجاح');
    }

    /**
     * أنواعُ الإجازات لحقل «نوع الإجازة» في مكافأة «إجازة».
     */
    private function leaveTypes()
    {
        return \Illuminate\Support\Facades\Schema::hasTable('leave_types')
            ? \App\Models\LeaveType::query()->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    /**
     * شروطُ المكافأة التي تُنفَّذ عبر الموارد البشرية — تُرفض عند الحفظ لا عند الاعتماد.
     *
     *   • «مكافأة نقدية»: قيمةٌ موجبة — تصير استحقاقاً على راتب الموظف (أو صرفاً من الخزينة للعميل).
     *   • «إجازة»: للموظفين وحدهم، بعدد أيامٍ صحيح (رصيدُ الإجازات بالأيام الصحيحة)،
     *     ومع نوع الإجازة الذي تُضاف إليه الأيام.
     */
    private function assertHrRewardRules(Request $request): void
    {
        $type = $request->input('reward_type');
        $value = (float) $request->input('reward_value', 0);
        $errors = [];

        if ($type === RewardType::CASH->value && $value <= 0) {
            $errors['reward_value'] = 'قيمة المكافأة النقدية مطلوبة (مبلغ أكبر من صفر).';
        }

        if ($type === RewardType::LEAVE_DAY->value) {
            if ($request->input('user_type') !== 'employee') {
                $errors['user_type'] = 'مكافأة «إجازة» للموظفين وحدهم.';
            }
            if ($value < 1 || abs($value - round($value)) > 0.001) {
                $errors['reward_value'] = 'قيمة مكافأة الإجازة = عدد الأيام، ويجب أن يكون عدداً صحيحاً (1 فأكثر) — رصيد الإجازات يُحفظ بأيامٍ صحيحة.';
            }
            if (! $request->filled('leave_type_id')) {
                $errors['leave_type_id'] = 'اختر نوع الإجازة الذي تُضاف إليه أيام المكافأة.';
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    /**
     * حذف مكافأة
     */
    public function destroy($id)
    {
        $reward = Reward::findOrFail($id);

        // حذف الصورة
        if ($reward->image) {
            Storage::disk('public')->delete($reward->image);
        }

        $reward->delete();

        return redirect()
            ->route('ecosystem.points.rewards.index')
            ->with('success', 'تم حذف المكافأة بنجاح');
    }

    /**
     * تبديل حالة المكافأة
     */
    public function toggleStatus($id)
    {
        $reward = Reward::findOrFail($id);
        $reward->update(['is_active' => !$reward->is_active]);

        return response()->json([
            'success' => true,
            'message' => $reward->is_active ? 'تم تفعيل المكافأة' : 'تم تعطيل المكافأة',
            'is_active' => $reward->is_active,
        ]);
    }

    /**
     * تبديل حالة التمييز
     */
    public function toggleFeatured($id)
    {
        $reward = Reward::findOrFail($id);
        $reward->update(['is_featured' => !$reward->is_featured]);

        return response()->json([
            'success' => true,
            'message' => $reward->is_featured ? 'تم تمييز المكافأة' : 'تم إلغاء تمييز المكافأة',
            'is_featured' => $reward->is_featured,
        ]);
    }

    /**
     * حذف مجموعة من المكافآت
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:rewards,id',
        ]);

        $rewards = Reward::whereIn('id', $request->ids)->get();

        foreach ($rewards as $reward) {
            if ($reward->image) {
                Storage::disk('public')->delete($reward->image);
            }
            $reward->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المكافآت المحددة بنجاح',
        ]);
    }

    /**
     * تصدير المكافآت
     */
    public function export(Request $request)
    {
        // يمكن إضافة منطق التصدير لاحقاً
        return redirect()->back()->with('info', 'جاري تطوير ميزة التصدير');
    }
}
