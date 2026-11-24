<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Enums\UserType;
use App\Models\Ecosystem\Achievement;
use App\Models\Ecosystem\UserAchievement;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserAchievementController extends Controller
{
    /**
     * عرض قائمة إنجازات المستخدمين
     */
    public function index(Request $request)
    {
        $query = UserAchievement::with(['achievement', 'employee', 'client', 'transaction']);

        // فلترة حسب نوع المستخدم
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // فلترة حسب الإنجاز
        if ($request->filled('achievement_id')) {
            $query->where('achievement_id', $request->achievement_id);
        }

        // فلترة حسب الفرع (للموظفين)
        if ($request->filled('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('employee', function ($eq) use ($request) {
                    $eq->where('branch_id', $request->branch_id);
                })->orWhereHas('client', function ($cq) use ($request) {
                    $cq->where('branch_id', $request->branch_id);
                });
            });
        }

        // فلترة حسب التاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('achieved_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('achieved_at', '<=', $request->date_to);
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('achievement', function ($aq) use ($search) {
                    $aq->where('name', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%");
                })
                ->orWhereHas('employee', function ($eq) use ($search) {
                    $eq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->orWhereHas('client', function ($cq) use ($search) {
                    $cq->where('trade_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            });
        }

        // الترتيب
        $query->orderBy('achieved_at', 'desc');

        $userAchievements = $query->paginate(15)->withQueryString();

        // إحصائيات
        $stats = [
            'total' => UserAchievement::count(),
            'clients' => UserAchievement::where('user_type', UserType::CLIENT)->count(),
            'employees' => UserAchievement::where('user_type', UserType::EMPLOYEE)->count(),
            'total_points' => UserAchievement::sum('points_awarded'),
            'this_month' => UserAchievement::whereMonth('achieved_at', now()->month)
                ->whereYear('achieved_at', now()->year)
                ->count(),
        ];

        // البيانات للفلاتر
        $achievements = Achievement::where('is_active', true)->get();
        $branches = Branch::all();

        return view('ecosystem::points.user-achievements.index', compact(
            'userAchievements',
            'stats',
            'achievements',
            'branches'
        ));
    }

    /**
     * عرض تفاصيل إنجاز مستخدم معين
     */
    public function show($id)
    {
        $userAchievement = UserAchievement::with([
            'achievement',
            'employee.branch',
            'client.branch',
            'transaction'
        ])->findOrFail($id);

        return view('ecosystem::points.user-achievements.show', compact('userAchievement'));
    }

    /**
     * عرض إنجازات موظف معين
     */
    public function employeeAchievements(Request $request, $employeeId)
    {
        $query = UserAchievement::with(['achievement', 'transaction'])
            ->where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employeeId);

        // فلترة حسب الإنجاز
        if ($request->filled('achievement_id')) {
            $query->where('achievement_id', $request->achievement_id);
        }

        // فلترة حسب التاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('achieved_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('achieved_at', '<=', $request->date_to);
        }

        $query->orderBy('achieved_at', 'desc');
        $userAchievements = $query->paginate(15)->withQueryString();

        // إحصائيات
        $stats = [
            'total' => UserAchievement::forEmployee($employeeId)->count(),
            'total_points' => UserAchievement::forEmployee($employeeId)->sum('points_awarded'),
            'this_month' => UserAchievement::forEmployee($employeeId)
                ->whereMonth('achieved_at', now()->month)
                ->whereYear('achieved_at', now()->year)
                ->count(),
        ];

        $achievements = Achievement::where('user_type', UserType::EMPLOYEE)
            ->where('is_active', true)
            ->get();

        return view('ecosystem::points.user-achievements.employee', compact(
            'userAchievements',
            'stats',
            'achievements',
            'employeeId'
        ));
    }

    /**
     * عرض إنجازات عميل معين
     */
    public function clientAchievements(Request $request, $clientId)
    {
        $query = UserAchievement::with(['achievement', 'transaction'])
            ->where('user_type', UserType::CLIENT)
            ->where('client_id', $clientId);

        // فلترة حسب الإنجاز
        if ($request->filled('achievement_id')) {
            $query->where('achievement_id', $request->achievement_id);
        }

        // فلترة حسب التاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('achieved_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('achieved_at', '<=', $request->date_to);
        }

        $query->orderBy('achieved_at', 'desc');
        $userAchievements = $query->paginate(15)->withQueryString();

        // إحصائيات
        $stats = [
            'total' => UserAchievement::forClient($clientId)->count(),
            'total_points' => UserAchievement::forClient($clientId)->sum('points_awarded'),
            'this_month' => UserAchievement::forClient($clientId)
                ->whereMonth('achieved_at', now()->month)
                ->whereYear('achieved_at', now()->year)
                ->count(),
        ];

        $achievements = Achievement::where('user_type', UserType::CLIENT)
            ->where('is_active', true)
            ->get();

        return view('ecosystem::points.user-achievements.client', compact(
            'userAchievements',
            'stats',
            'achievements',
            'clientId'
        ));
    }

    /**
     * تصدير إنجازات المستخدمين إلى Excel
     */
    public function export(Request $request)
    {
        // يمكن إضافة وظيفة التصدير لاحقاً باستخدام Laravel Excel
        return back()->with('info', 'ستتوفر وظيفة التصدير قريباً');
    }
}
