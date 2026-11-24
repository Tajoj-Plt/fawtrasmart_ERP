<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Enums\ConditionOperator;
use App\Enums\ConditionPeriod;
use App\Enums\UserType;
use App\Models\Ecosystem\Achievement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class AchievementController extends Controller
{
    public function index(Request $request)
    {
        $query = Achievement::query();

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فلترة حسب نوع المستخدم
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // فلترة حسب الفئة
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        // فلترة حسب النوع (مرة واحدة أو متكرر)
        if ($request->filled('is_one_time')) {
            $query->where('is_one_time', $request->is_one_time === 'yes');
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $achievements = $query->paginate(15)->withQueryString();

        // الإحصائيات
        $stats = [
            'total' => Achievement::count(),
            'active' => Achievement::where('is_active', true)->count(),
            'client_achievements' => Achievement::where('user_type', UserType::CLIENT->value)->count(),
            'employee_achievements' => Achievement::where('user_type', UserType::EMPLOYEE->value)->count(),
            'one_time' => Achievement::where('is_one_time', true)->count(),
            'repeatable' => Achievement::where('is_one_time', false)->count(),
        ];

        return view('ecosystem::points.achievements.index', compact('achievements', 'stats'));
    }

    /**
     * إضافة الإنجازات معطلة - تُدار من السيدر فقط
     */
    public function create()
    {
        abort(403, 'إضافة الإنجازات معطلة. الإنجازات تُدار تلقائياً من النظام.');
    }

    /**
     * حفظ إنجاز جديد معطل - تُدار من السيدر فقط
     */
    public function store(Request $request)
    {
        abort(403, 'إضافة الإنجازات معطلة. الإنجازات تُدار تلقائياً من النظام.');
    }

    public function show(Achievement $achievement)
    {
        $achievement->load(['userAchievements']);

        // إحصائيات الإنجاز
        $stats = [
            'total_earned' => $achievement->userAchievements()->count(),
            'clients_earned' => $achievement->userAchievements()
                ->where('user_type', UserType::CLIENT->value)
                ->count(),
            'employees_earned' => $achievement->userAchievements()
                ->where('user_type', UserType::EMPLOYEE->value)
                ->count(),
            'total_points_awarded' => $achievement->userAchievements()->count() * $achievement->bonus_points,
        ];

        return view('ecosystem::points.achievements.show', compact('achievement', 'stats'));
    }

    public function edit(Achievement $achievement)
    {
        $userTypes = UserType::cases();
        $conditionOperators = ConditionOperator::cases();
        $conditionPeriods = ConditionPeriod::cases();

        $conditionTypes = [
            'invoices_count' => 'عدد الفواتير',
            'total_collected' => 'إجمالي المحصّل',
            'total_spent' => 'إجمالي المصروف',
            'points_earned' => 'النقاط المكتسبة',
            'referrals_count' => 'عدد الإحالات',
            'reviews_count' => 'عدد التقييمات',
            'orders_count' => 'عدد الطلبات',
            'days_on_time' => 'أيام الحضور في الوقت',
            'collaborations_count' => 'عدد التعاونات',
            'new_clients_count' => 'عدد العملاء الجدد',
            'tasks_completed' => 'المهام المنجزة',
            'sales_count' => 'عدد المبيعات',
            'visits_count' => 'عدد الزيارات',
            'overdue_collections_count' => 'تحصيل ديون متأخرة',
            'early_completions_count' => 'إنجاز قبل الموعد',
            'trainings_completed' => 'التدريبات المكتملة',
            'customer_satisfaction_count' => 'رضا العملاء',
        ];

        $categories = [
            'loyalty' => 'الولاء',
            'sales' => 'المبيعات',
            'collection' => 'التحصيل',
            'attendance' => 'الحضور',
            'teamwork' => 'العمل الجماعي',
            'acquisition' => 'اكتساب العملاء',
            'referral' => 'الإحالة',
            'engagement' => 'التفاعل',
            'orders' => 'الطلبات',
            'spending' => 'الإنفاق',
            'visits' => 'الزيارات',
            'tasks' => 'المهام',
            'milestone' => 'معالم بارزة',
            'development' => 'التطوير',
            'customer_service' => 'خدمة العملاء',
            'special' => 'خاص',
        ];

        return view('ecosystem::points.achievements.edit', compact(
            'achievement',
            'userTypes',
            'conditionOperators',
            'conditionPeriods',
            'conditionTypes',
            'categories'
        ));
    }

    public function update(Request $request, Achievement $achievement)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'user_type' => 'required|in:' . implode(',', array_column(UserType::cases(), 'value')),
            'category' => 'required|string|max:100',
            'icon' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:2048',
            'color' => 'nullable|string|max:7',
            'condition_type' => 'required|string|max:100',
            'condition_operator' => 'required|in:' . implode(',', array_column(ConditionOperator::cases(), 'value')),
            'condition_value' => 'required|integer|min:0',
            'condition_period' => 'required|in:' . implode(',', array_column(ConditionPeriod::cases(), 'value')),
            'bonus_points' => 'required|integer|min:0',
            'is_hidden' => 'boolean',
            'is_one_time' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // معالجة الصورة
        if ($request->hasFile('image')) {
            // حذف الصورة القديمة
            if ($achievement->image) {
                Storage::disk('public')->delete($achievement->image);
            }
            $validated['image'] = $request->file('image')->store('achievements', 'public');
        }

        $achievement->update($validated);

        return redirect()
            ->route('ecosystem.points.achievements.show', $achievement)
            ->with('success', 'تم تحديث الإنجاز بنجاح');
    }

    public function destroy(Achievement $achievement)
    {
        // حذف الصورة إن وجدت
        if ($achievement->image) {
            Storage::disk('public')->delete($achievement->image);
        }

        $achievement->delete();

        return redirect()
            ->route('ecosystem.points.achievements.index')
            ->with('success', 'تم حذف الإنجاز بنجاح');
    }

    public function toggleStatus(Achievement $achievement)
    {
        $achievement->update([
            'is_active' => !$achievement->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => $achievement->is_active ? 'تم تفعيل الإنجاز' : 'تم تعطيل الإنجاز',
            'is_active' => $achievement->is_active,
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:achievements,id',
        ]);

        $achievements = Achievement::whereIn('id', $request->ids)->get();

        foreach ($achievements as $achievement) {
            if ($achievement->image) {
                Storage::disk('public')->delete($achievement->image);
            }
            $achievement->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف ' . count($request->ids) . ' إنجاز بنجاح',
        ]);
    }

    public function export()
    {
        // TODO: تصدير البيانات إلى Excel
        return back()->with('info', 'جاري العمل على ميزة التصدير');
    }
}
