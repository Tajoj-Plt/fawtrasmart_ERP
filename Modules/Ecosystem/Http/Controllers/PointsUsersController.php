<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\PointsTier;
use App\Models\Ecosystem\TierUpgradeLog;
use App\Models\Ecosystem\UserAchievement;
use App\Models\Ecosystem\Achievement;
use App\Models\Ecosystem\PointsBatch;
use App\Enums\UserType;
use App\Enums\BatchStatus;
use App\Enums\TransactionStatus;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PointsUsersController extends Controller
{
    /**
     * عرض قائمة الموظفين الذين لديهم نقاط
     */
    public function employees(Request $request)
    {
        // تحديد الشهر والسنة للفلترة (الافتراضي: الشهر الحالي)
        $filterMonth = $request->filled('month') ? (int) $request->month : now()->month;
        $filterYear = $request->filled('year') ? (int) $request->year : now()->year;

        // الحصول على معرفات الموظفين الذين لديهم معاملات نقاط في الشهر المحدد (دائماً مفلتر)
        $employeeIdsWithPoints = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('points_month', $filterMonth)
            ->where('points_year', $filterYear)
            ->distinct()
            ->pluck('employee_id')
            ->filter()
            ->toArray();

        $query = Employee::with(['branch', 'department'])
            ->whereIn('id', $employeeIdsWithPoints);

        // فلترة حسب الفرع
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // فلترة حسب القسم
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // الترتيب (افتراضياً حسب الاسم)
        $sortBy = $request->get('sort_by', 'first_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $employees = $query->paginate(20)->withQueryString();

        // حساب نقاط كل موظف للشهر المحدد
        $employees->getCollection()->transform(function ($employee) use ($filterMonth, $filterYear) {
            $monthQuery = PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('employee_id', $employee->id)
                ->where('points_month', $filterMonth)
                ->where('points_year', $filterYear);

            $employee->month_earned = (clone $monthQuery)
                ->where('points', '>', 0)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points');
            $employee->month_spent = abs((clone $monthQuery)
                ->where('points', '<', 0)
                ->sum('points'));
            $employee->month_balance = $employee->month_earned - $employee->month_spent;
            $employee->month_transactions_count = (clone $monthQuery)->count();
            return $employee;
        });

        // حساب الإحصائيات للشهر المحدد
        $stats = [
            'total_employees' => count($employeeIdsWithPoints),
            'total_points' => PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('points_month', $filterMonth)
                ->where('points_year', $filterYear)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points'),
            'total_earned' => PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('points_month', $filterMonth)
                ->where('points_year', $filterYear)
                ->where('points', '>', 0)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points'),
            'total_spent' => abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('points_month', $filterMonth)
                ->where('points_year', $filterYear)
                ->where('points', '<', 0)
                ->sum('points')),
        ];

        // المستويات للفلترة
        $tiers = PointsTier::active()->forUserType('employee')->ordered()->get();

        // قائمة الأشهر المتاحة
        $availableMonths = $this->getAvailableMonthsForList(UserType::EMPLOYEE);

        return view('ecosystem::points.users.employees', compact('employees', 'stats', 'tiers', 'filterMonth', 'filterYear', 'availableMonths'));
    }

    /**
     * عرض تفاصيل موظف محدد ومعاملات النقاط الخاصة به
     */
    public function showEmployee(Request $request, $id)
    {
        $employee = Employee::with(['branch', 'department'])->findOrFail($id);

        // تحديد الشهر والسنة للفلترة (الافتراضي: الشهر الحالي)
        $filterMonth = $request->filled('month') ? (int) $request->month : now()->month;
        $filterYear = $request->filled('year') ? (int) $request->year : now()->year;

        // معاملات النقاط - استخدام Enum بشكل صحيح
        $transactionsQuery = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->with(['branch', 'creator']);

        // فلترة حسب الشهر والسنة
        $transactionsQuery->where('points_month', $filterMonth)
                         ->where('points_year', $filterYear);

        // فلترة حسب نوع العملية
        if ($request->filled('action_type')) {
            $transactionsQuery->where('action_type', 'like', '%' . $request->action_type . '%');
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $transactionsQuery->where('status', $request->status);
        }

        // فلترة حسب نوع النقاط (مكتسبة/مستخدمة)
        if ($request->filled('points_type')) {
            if ($request->points_type === 'earned') {
                $transactionsQuery->where('points', '>', 0);
            } elseif ($request->points_type === 'spent') {
                $transactionsQuery->where('points', '<', 0);
            }
        }

        $transactions = $transactionsQuery->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // سجل ترقيات المستوى - استخدام Enum بشكل صحيح
        $tierUpgrades = TierUpgradeLog::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->with(['oldTier', 'newTier', 'upgradedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        // المستويات للعرض
        $allTiers = PointsTier::active()->forUserType('employee')->ordered()->get();

        // حساب النقاط للشهر المحدد بالفلتر (النقاط المكتسبة فقط لتحديد المستوى)
        $monthEarned = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->where('points_month', $filterMonth)
            ->where('points_year', $filterYear)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        $monthSpent = abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->where('points_month', $filterMonth)
            ->where('points_year', $filterYear)
            ->where('points', '<', 0)
            ->sum('points'));

        // صافي نقاط الشهر (المكتسب - المستخدم)
        $monthNet = max(0, $monthEarned - $monthSpent);

        // حساب المستوى الحالي بناءً على صافي نقاط الشهر (بعد خصم المستخدم)
        $currentTier = $this->calculateTierFromPoints($monthNet, $allTiers);
        $nextTier = $currentTier ? $currentTier->getNextTier() : $allTiers->first();
        $pointsToNextTier = $currentTier ? $currentTier->getPointsToNextTier($monthNet) : ($allTiers->first() ? $allTiers->first()->min_points - $monthNet : null);

        // حساب إجمالي النقاط المكتسبة والمنفقة من جميع المعاملات
        $totalEarned = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        $totalSpent = abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->where('points', '<', 0)
            ->sum('points'));

        // إحصائيات الموظف - محسوبة من المعاملات
        $employeeStats = [
            'current_balance' => $monthNet, // صافي رصيد الشهر (مكتسب - مستخدم)
            'total_earned' => $totalEarned,
            'total_spent' => $totalSpent,
            'transactions_count' => PointsTransaction::where('user_type', UserType::EMPLOYEE)->where('employee_id', $id)->count(),
            'this_month_earned' => $monthEarned,
            'this_month_spent' => $monthSpent,
        ];

        // بيانات شريط تقدم النقاط الشهرية - بناءً على صافي النقاط (مكتسب - مستخدم)
        $monthlyPointsData = $this->calculateMonthlyTierProgress(
            $monthNet,
            $allTiers,
            'employee',
            $filterMonth,
            $filterYear
        );

        $statuses = TransactionStatus::values();

        // قائمة الأشهر للفلترة
        $months = $this->getAvailableMonths($id, UserType::EMPLOYEE);

        // إنجازات الموظف
        $userAchievements = UserAchievement::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->with('achievement')
            ->orderBy('achieved_at', 'desc')
            ->get();

        // الإنجازات المتاحة لهذا النوع
        $availableAchievements = Achievement::active()->forUserType('employee')->visible()->get();

        // =====================================================
        // بيانات انتهاء صلاحية النقاط
        // =====================================================
        $expiringBatches = PointsBatch::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $id)
            ->where('status', BatchStatus::ACTIVE->value)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'asc')
            ->get();

        $expiringPointsTotal = $expiringBatches->sum('remaining_points');
        $nearestExpiryDate = $expiringBatches->first()?->expires_at;

        $batchStats = [
            'active_batches' => PointsBatch::where('user_type', UserType::EMPLOYEE)
                ->where('employee_id', $id)
                ->where('status', BatchStatus::ACTIVE->value)
                ->where('remaining_points', '>', 0)
                ->count(),
            'expiring_soon_count' => $expiringBatches->count(),
            'expiring_soon_points' => $expiringPointsTotal,
            'nearest_expiry' => $nearestExpiryDate,
            'total_expired' => PointsBatch::where('user_type', UserType::EMPLOYEE)
                ->where('employee_id', $id)
                ->where('status', BatchStatus::EXPIRED->value)
                ->sum('expired_points'),
        ];

        return view('ecosystem::points.users.show-employee', compact(
            'employee',
            'transactions',
            'tierUpgrades',
            'allTiers',
            'currentTier',
            'nextTier',
            'pointsToNextTier',
            'employeeStats',
            'statuses',
            'monthlyPointsData',
            'filterMonth',
            'filterYear',
            'months',
            'userAchievements',
            'availableAchievements',
            'expiringBatches',
            'batchStats'
        ));
    }

    /**
     * عرض قائمة العملاء الذين لديهم نقاط
     */
    public function clients(Request $request)
    {
        // الحصول على معرفات العملاء الذين لديهم معاملات نقاط (تراكمي - بدون فلترة شهرية)
        $clientIdsWithPoints = PointsTransaction::where('user_type', UserType::CLIENT)
            ->distinct()
            ->pluck('client_id')
            ->filter()
            ->toArray();

        $query = Client::with(['branch'])
            ->whereIn('id', $clientIdsWithPoints);

        // فلترة حسب الفرع
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // الترتيب (افتراضياً حسب الاسم)
        $sortBy = $request->get('sort_by', 'first_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $clients = $query->paginate(20)->withQueryString();

        // حساب نقاط كل عميل (تراكمي)
        $clients->getCollection()->transform(function ($client) {
            $baseQuery = PointsTransaction::where('user_type', UserType::CLIENT)
                ->where('client_id', $client->id);

            $client->total_earned = (clone $baseQuery)
                ->where('points', '>', 0)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points');
            $client->total_spent = abs((clone $baseQuery)
                ->where('points', '<', 0)
                ->sum('points'));
            $client->total_balance = $client->total_earned - $client->total_spent;
            $client->total_transactions_count = (clone $baseQuery)->count();
            return $client;
        });

        // حساب الإحصائيات (تراكمي)
        $stats = [
            'total_clients' => count($clientIdsWithPoints),
            'total_points' => PointsTransaction::where('user_type', UserType::CLIENT)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points'),
            'total_earned' => PointsTransaction::where('user_type', UserType::CLIENT)
                ->where('points', '>', 0)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points'),
            'total_spent' => abs(PointsTransaction::where('user_type', UserType::CLIENT)
                ->where('points', '<', 0)
                ->sum('points')),
        ];

        // المستويات للفلترة
        $tiers = PointsTier::active()->forUserType('client')->ordered()->get();

        return view('ecosystem::points.users.clients', compact('clients', 'stats', 'tiers'));
    }

    /**
     * عرض تفاصيل عميل محدد ومعاملات النقاط الخاصة به
     */
    public function showClient(Request $request, $id)
    {
        $client = Client::with(['branch'])->findOrFail($id);

        // معاملات النقاط - تراكمي (بدون فلترة شهرية)
        $transactionsQuery = PointsTransaction::where('user_type', UserType::CLIENT)
            ->where('client_id', $id)
            ->with(['branch', 'creator']);

        // فلترة حسب نوع العملية
        if ($request->filled('action_type')) {
            $transactionsQuery->where('action_type', 'like', '%' . $request->action_type . '%');
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $transactionsQuery->where('status', $request->status);
        }

        // فلترة حسب نوع النقاط (مكتسبة/مستخدمة)
        if ($request->filled('points_type')) {
            if ($request->points_type === 'earned') {
                $transactionsQuery->where('points', '>', 0);
            } elseif ($request->points_type === 'spent') {
                $transactionsQuery->where('points', '<', 0);
            }
        }

        $transactions = $transactionsQuery->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // سجل ترقيات المستوى - استخدام Enum بشكل صحيح
        $upgradeLogs = TierUpgradeLog::where('user_type', UserType::CLIENT)
            ->where('client_id', $id)
            ->with(['oldTier', 'newTier', 'upgradedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        // المستويات للعرض
        $allTiers = PointsTier::active()->forUserType('client')->ordered()->get();

        // حساب إجمالي النقاط المكتسبة والمنفقة من جميع المعاملات (تراكمي)
        $totalEarned = PointsTransaction::where('user_type', UserType::CLIENT)
            ->where('client_id', $id)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        $totalSpent = abs(PointsTransaction::where('user_type', UserType::CLIENT)
            ->where('client_id', $id)
            ->where('points', '<', 0)
            ->sum('points'));

        // صافي النقاط التراكمي (المكتسب - المستخدم)
        $totalNet = max(0, $totalEarned - $totalSpent);

        // حساب المستوى الحالي بناءً على صافي النقاط التراكمي
        $currentTier = $this->calculateTierFromPoints($totalNet, $allTiers);
        $nextTier = $currentTier ? $currentTier->getNextTier() : $allTiers->first();
        $pointsToNextTier = $currentTier ? $currentTier->getPointsToNextTier($totalNet) : ($allTiers->first() ? $allTiers->first()->min_points - $totalNet : null);

        // النقاط التي ستنتهي قريباً
        $expiringSoon = PointsTransaction::where('user_type', UserType::CLIENT)
            ->where('client_id', $id)
            ->where('status', TransactionStatus::COMPLETED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->sum('points');

        // إحصائيات العميل - تراكمي
        $stats = [
            'current_balance' => $totalNet, // صافي الرصيد التراكمي (مكتسب - مستخدم)
            'total_earned' => $totalEarned,
            'total_spent' => $totalSpent,
            'total_transactions' => PointsTransaction::where('user_type', UserType::CLIENT)->where('client_id', $id)->count(),
            'expiring_soon' => $expiringSoon,
        ];

        // بيانات شريط تقدم النقاط التراكمية
        $monthlyPointsData = $this->calculateMonthlyTierProgress(
            $totalNet,
            $allTiers,
            'client',
            now()->month,
            now()->year
        );
        // تغيير التسمية إلى تراكمي
        $monthlyPointsData['month_name'] = 'الإجمالي التراكمي';

        $statuses = TransactionStatus::values();

        // إنجازات العميل
        $userAchievements = UserAchievement::where('user_type', UserType::CLIENT)
            ->where('client_id', $id)
            ->with('achievement')
            ->orderBy('achieved_at', 'desc')
            ->get();

        // الإنجازات المتاحة لهذا النوع
        $availableAchievements = Achievement::active()->forUserType('client')->visible()->get();

        // =====================================================
        // بيانات انتهاء صلاحية النقاط
        // =====================================================
        $expiringBatches = PointsBatch::where('user_type', UserType::CLIENT)
            ->where('client_id', $id)
            ->where('status', BatchStatus::ACTIVE->value)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'asc')
            ->get();

        $expiringPointsTotal = $expiringBatches->sum('remaining_points');
        $nearestExpiryDate = $expiringBatches->first()?->expires_at;

        $batchStats = [
            'active_batches' => PointsBatch::where('user_type', UserType::CLIENT)
                ->where('client_id', $id)
                ->where('status', BatchStatus::ACTIVE->value)
                ->where('remaining_points', '>', 0)
                ->count(),
            'expiring_soon_count' => $expiringBatches->count(),
            'expiring_soon_points' => $expiringPointsTotal,
            'nearest_expiry' => $nearestExpiryDate,
            'total_expired' => PointsBatch::where('user_type', UserType::CLIENT)
                ->where('client_id', $id)
                ->where('status', BatchStatus::EXPIRED->value)
                ->sum('expired_points'),
        ];

        return view('ecosystem::points.users.show-client', compact(
            'client',
            'transactions',
            'upgradeLogs',
            'allTiers',
            'currentTier',
            'nextTier',
            'pointsToNextTier',
            'stats',
            'statuses',
            'monthlyPointsData',
            'userAchievements',
            'availableAchievements',
            'expiringBatches',
            'batchStats'
        ));
    }

    /**
     * حساب بيانات شريط تقدم النقاط الشهرية
     */
    private function calculateMonthlyTierProgress(int $monthlyPoints, $allTiers, string $userType, int $month = null, int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        $monthName = \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y');
        
        // التأكد من وجود مستويات
        if ($allTiers->isEmpty()) {
            return [
                'month' => $month,
                'year' => $year,
                'month_name' => $monthName,
                'monthly_points' => $monthlyPoints,
                'current_tier' => null,
                'next_tier' => null,
                'points_to_next_tier' => 0,
                'max_tier' => null,
                'points_to_max_tier' => 0,
                'overall_progress' => 0,
                'tier_positions' => [],
            ];
        }
        
        // ترتيب المستويات حسب min_points تصاعدياً
        $sortedTiers = $allTiers->sortBy('min_points')->values();
        
        // الحصول على أعلى رتبة (آخر مستوى)
        $maxTier = $sortedTiers->last();
        $minTier = $sortedTiers->first();
        
        // حساب نطاق النقاط لتوزيع المستويات
        $minPoints = $minTier->min_points; // عادة 0
        $maxPoints = $maxTier->min_points; // نقاط بداية أعلى مستوى
        
        // إضافة هامش بعد أعلى مستوى للعرض
        $displayMaxPoints = $maxPoints > 0 ? $maxPoints * 1.15 : 1000;
        
        // تحديد المستوى الحالي حسب نقاط الشهر
        $currentMonthlyTier = null;
        $nextMonthlyTier = null;
        $pointsToNextMonthlyTier = 0;
        
        foreach ($sortedTiers as $index => $tier) {
            $tierMax = $tier->max_points ?? PHP_INT_MAX;
            
            if ($monthlyPoints >= $tier->min_points && $monthlyPoints <= $tierMax) {
                $currentMonthlyTier = $tier;
                $nextMonthlyTier = $sortedTiers->get($index + 1);
                break;
            }
        }
        
        // إذا لم يصل لأي مستوى، فهو قبل المستوى الأول
        if (!$currentMonthlyTier && $sortedTiers->count() > 0) {
            $nextMonthlyTier = $minTier;
        }
        
        // حساب النقاط المتبقية للمستوى التالي والأخير
        if ($nextMonthlyTier) {
            $pointsToNextMonthlyTier = max(0, $nextMonthlyTier->min_points - $monthlyPoints);
        }
        
        $pointsToMaxTier = max(0, $maxPoints - $monthlyPoints);
        
        // حساب نسبة التقدم العامة نحو أعلى رتبة
        $overallProgress = 0;
        if ($displayMaxPoints > 0) {
            $overallProgress = min(100, ($monthlyPoints / $displayMaxPoints) * 100);
        }
        
        // حساب موضع كل مستوى على الشريط - توزيع متساوي
        $tierPositions = [];
        $tierCount = $sortedTiers->count();
        
        foreach ($sortedTiers as $index => $tier) {
            // توزيع المستويات بشكل متساوي على الشريط
            // المستوى الأول عند 5%، الأخير عند 90%
            $position = 5 + (($index) / max(1, $tierCount - 1)) * 85;
            
            // التأكد من عدم تجاوز الحدود
            $position = min(95, max(5, $position));
            
            $tierPositions[] = [
                'id' => $tier->id,
                'name' => $tier->name,
                'color' => $tier->color,
                'min_points' => $tier->min_points,
                'position' => round($position, 1),
                'is_achieved' => $monthlyPoints >= $tier->min_points,
                'is_current' => $currentMonthlyTier && $currentMonthlyTier->id === $tier->id,
            ];
        }
        
        return [
            'month' => $month,
            'year' => $year,
            'month_name' => $monthName,
            'monthly_points' => $monthlyPoints,
            'current_tier' => $currentMonthlyTier,
            'next_tier' => $nextMonthlyTier,
            'points_to_next_tier' => max(0, $pointsToNextMonthlyTier),
            'max_tier' => $maxTier,
            'points_to_max_tier' => $pointsToMaxTier,
            'overall_progress' => round($overallProgress, 1),
            'tier_positions' => $tierPositions,
        ];
    }

    /**
     * الحصول على قائمة الأشهر المتاحة للفلترة (لقائمة المستخدمين)
     */
    private function getAvailableMonthsForList(UserType $userType): array
    {
        $months = PointsTransaction::where('user_type', $userType)
            ->selectRaw('DISTINCT points_month, points_year')
            ->orderByDesc('points_year')
            ->orderByDesc('points_month')
            ->get();

        $result = [];
        foreach ($months as $m) {
            if ($m->points_month && $m->points_year) {
                $date = \Carbon\Carbon::create($m->points_year, $m->points_month, 1);
                $result[] = [
                    'month' => $m->points_month,
                    'year' => $m->points_year,
                    'label' => $date->translatedFormat('F Y'),
                ];
            }
        }

        // إضافة الشهر الحالي إذا لم يكن موجوداً
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $hasCurrentMonth = collect($result)->contains(function ($item) use ($currentMonth, $currentYear) {
            return $item['month'] === $currentMonth && $item['year'] === $currentYear;
        });

        if (!$hasCurrentMonth) {
            array_unshift($result, [
                'month' => $currentMonth,
                'year' => $currentYear,
                'label' => now()->translatedFormat('F Y'),
            ]);
        }

        return $result;
    }

    /**
     * الحصول على قائمة الأشهر المتاحة للفلترة
     */
    private function getAvailableMonths(int $userId, UserType $userType): array
    {
        $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';
        
        $months = PointsTransaction::where('user_type', $userType)
            ->where($idColumn, $userId)
            ->selectRaw('DISTINCT points_month, points_year')
            ->orderByDesc('points_year')
            ->orderByDesc('points_month')
            ->get();

        $result = [];
        foreach ($months as $m) {
            if ($m->points_month && $m->points_year) {
                $date = \Carbon\Carbon::create($m->points_year, $m->points_month, 1);
                $result[] = [
                    'month' => $m->points_month,
                    'year' => $m->points_year,
                    'label' => $date->translatedFormat('F Y'),
                ];
            }
        }

        // إضافة الشهر الحالي إذا لم يكن موجوداً
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $hasCurrentMonth = collect($result)->contains(function ($item) use ($currentMonth, $currentYear) {
            return $item['month'] === $currentMonth && $item['year'] === $currentYear;
        });

        if (!$hasCurrentMonth) {
            array_unshift($result, [
                'month' => $currentMonth,
                'year' => $currentYear,
                'label' => now()->translatedFormat('F Y'),
            ]);
        }

        return $result;
    }

    /**
     * حساب المستوى بناءً على النقاط
     * 
     * @param int $points عدد النقاط
     * @param \Illuminate\Support\Collection $tiers مجموعة المستويات المتاحة
     * @return \App\Models\Ecosystem\PointsTier|null المستوى المطابق أو null
     */
    private function calculateTierFromPoints(int $points, $tiers): ?PointsTier
    {
        if ($tiers->isEmpty()) {
            return null;
        }

        // ترتيب المستويات تنازلياً حسب الحد الأدنى للنقاط
        $sortedTiers = $tiers->sortByDesc('min_points');

        // البحث عن أول مستوى يحقق به المستخدم الحد الأدنى
        foreach ($sortedTiers as $tier) {
            if ($points >= $tier->min_points) {
                return $tier;
            }
        }

        // إذا لم يتم العثور على مستوى، إرجاع المستوى الأول (الأدنى)
        return $tiers->sortBy('min_points')->first();
    }
}
