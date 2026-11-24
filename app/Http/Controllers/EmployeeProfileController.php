<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\RedemptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\Employee;
use App\Models\Ecosystem\Achievement;
use App\Models\Ecosystem\Challenge;
use App\Models\Ecosystem\ChallengeParticipant;
use App\Models\Ecosystem\PointsBatch;
use App\Models\Ecosystem\PointsTier;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\Reward;
use App\Models\Ecosystem\RewardRedemption;
use App\Models\Ecosystem\TierUpgradeLog;
use App\Models\Ecosystem\UserAchievement;
use App\Models\Ecosystem\ChallengeTeam;
use App\Models\Ecosystem\PointsGeneralSetting;
use App\Services\PointsNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeProfileController extends Controller
{
    /**
     * عرض صفحة البروفايل الشخصي للموظف
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // التحقق من أن المستخدم موظف
        if (!$user->employee) {
            abort(403, 'هذه الصفحة متاحة للموظفين فقط');
        }

        $employee = $user->employee;

        // =====================================================
        // 1. كل المستويات (نحتاجها مبكراً لحساب المستوى)
        // =====================================================
        $allTiers = PointsTier::active()->forUserType('employee')->ordered()->get();

        // =====================================================
        // 2. إحصائيات النقاط (Points Statistics) - محسوبة من المعاملات
        // بنفس طريقة PointsUsersController::showEmployee()
        // يمكن الفلترة عبر الشهر لرؤية المستويات والرصيد لكل شهر
        // =====================================================
        $filterMonth = $request->filled('month') ? (int) $request->month : now()->month;
        $filterYear = $request->filled('year') ? (int) $request->year : now()->year;
        $isCurrentMonth = ($filterMonth === now()->month && $filterYear === now()->year);

        // نقاط الشهر الحالي - باستخدام points_month/points_year (مثل PointsUsersController)
        $monthEarned = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points_month', $filterMonth)
            ->where('points_year', $filterYear)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        $monthSpent = abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points_month', $filterMonth)
            ->where('points_year', $filterYear)
            ->where('points', '<', 0)
            ->sum('points'));

        // صافي نقاط الشهر (المكتسب - المستخدم) = الرصيد الفعلي
        $monthNet = max(0, $monthEarned - $monthSpent);

        // إجمالي النقاط من كل المعاملات (تراكمي)
        $totalPointsEarned = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        $totalPointsSpent = abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points', '<', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points'));

        // الرصيد الحالي = صافي نقاط الشهر
        $currentBalance = $monthNet;
        $pointsThisMonth = $monthNet;

        // =====================================================
        // 3. حساب المستوى الحالي ديناميكياً من النقاط
        // بنفس طريقة PointsUsersController::showEmployee()
        // =====================================================
        $currentTier = $this->calculateTierFromPoints($monthNet, $allTiers);
        $nextTier = $currentTier ? $currentTier->getNextTier() : $allTiers->first();
        $pointsToNextTier = $currentTier 
            ? $currentTier->getPointsToNextTier($monthNet) 
            : ($allTiers->first() ? $allTiers->first()->min_points - $monthNet : null);

        // حساب التقدم للمستوى التالي
        $progressToNextTier = 0;
        if ($currentTier && $nextTier) {
            $progress = (($monthNet - $currentTier->min_points) / max(1, ($nextTier->min_points - $currentTier->min_points))) * 100;
            $progressToNextTier = min(100, max(0, round($progress)));
        }

        // =====================================================
        // 4. سجل النقاط (Transactions with Pagination) - مفلتر حسب الشهر المحدد
        // =====================================================
        $transactions = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points_month', $filterMonth)
            ->where('points_year', $filterYear)
            ->with(['branch', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->appends(['month' => $filterMonth, 'year' => $filterYear]);
            
        $recentTransactions = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points_month', $filterMonth)
            ->where('points_year', $filterYear)
            ->with(['branch', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // =====================================================
        // 4. الإنجازات (Achievements)
        // =====================================================
        $achievements = UserAchievement::where('employee_id', $employee->id)
            ->with(['achievement', 'transaction'])
            ->orderBy('achieved_at', 'desc')
            ->get();

        $totalAchievements = $achievements->count();

        // الإنجازات المتاحة للحصول عليها
        $achievedIds = $achievements->pluck('achievement_id')->toArray();
        
        // جميع الإنجازات المتاحة (للشريط الأفقي)
        $allAvailableAchievements = Achievement::active()
            ->forUserType(UserType::EMPLOYEE)
            ->visible()
            ->get();
            
        $availableAchievements = $allAvailableAchievements;

        // =====================================================
        // 5. التحديات (Challenges)
        // =====================================================
        $activeChallenges = ChallengeParticipant::where('employee_id', $employee->id)
            ->with(['challenge'])
            ->whereHas('challenge', function($q) {
                $q->active()->ongoing();
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $completedChallenges = ChallengeParticipant::where('employee_id', $employee->id)
            ->with(['challenge'])
            ->whereHas('challenge', function($q) {
                $q->completed();
            })
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        // =====================================================
        // 6. سجل الترقيات (Tier Upgrades)
        // =====================================================
        $tierUpgrades = TierUpgradeLog::where('employee_id', $employee->id)
            ->with(['oldTier', 'newTier', 'upgradedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // =====================================================
        // 7. المكافآت المتاحة (Available Rewards)
        // =====================================================
        $availableRewards = Reward::active()
            ->forUserType(UserType::EMPLOYEE)
            ->available()
            ->where(function($q) use ($employee) {
                $q->whereNull('min_tier_id')
                  ->orWhere(function($q2) use ($employee) {
                      $q2->whereHas('minTier', function($q3) use ($employee) {
                          if ($employee->tier_id) {
                              $currentTier = PointsTier::find($employee->tier_id);
                              if ($currentTier) {
                                  $q3->where('min_points', '<=', $currentTier->min_points);
                              }
                          }
                      });
                  });
            })
            ->orderBy('points_required', 'asc')
            ->limit(10)
            ->get();

        // =====================================================
        // 8. طلبات المكافآت (Reward Redemptions)
        // =====================================================
        $redemptions = RewardRedemption::where('employee_id', $employee->id)
            ->with(['reward', 'approver', 'processor'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $pendingRedemptions = RewardRedemption::where('employee_id', $employee->id)
            ->where('status', RedemptionStatus::PENDING)
            ->count();

        // =====================================================
        // 9. النقاط القابلة للانتهاء (Expiring Points)
        // =====================================================
        $expiringPoints = PointsBatch::where('employee_id', $employee->id)
            ->where('status', BatchStatus::ACTIVE)
            ->expiringSoon(30)
            ->orderBy('expires_at', 'asc')
            ->limit(5)
            ->get();

        // =====================================================
        // 10. الفريق (Team) - إذا كان ضمن فريق
        // =====================================================
        $teams = ChallengeParticipant::where('employee_id', $employee->id)
            ->whereNotNull('team_id')
            ->with(['team', 'team.challenge', 'team.members.employee'])
            ->get()
            ->pluck('team')
            ->unique('id');

        // =====================================================
        // الرسوم البيانية - بيانات النقاط الشهرية
        // =====================================================
        $monthlyPoints = [];
        $monthlyEarned = [];
        $monthlySpent = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            
            $earned = PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('employee_id', $employee->id)
                ->where('points_month', $date->month)
                ->where('points_year', $date->year)
                ->where('status', TransactionStatus::COMPLETED)
                ->where('points', '>', 0)
                ->sum('points');
                
            $spent = abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('employee_id', $employee->id)
                ->where('points_month', $date->month)
                ->where('points_year', $date->year)
                ->where('status', TransactionStatus::COMPLETED)
                ->where('points', '<', 0)
                ->sum('points'));
            
            $monthlyPoints[] = [
                'month' => $date->format('M Y'),
                'points' => $earned - $spent
            ];
            
            $monthlyEarned[] = $earned;
            $monthlySpent[] = $spent;
        }

        // =====================================================
        // إحصائيات نوع المعاملات
        // =====================================================
        $transactionsByType = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('status', TransactionStatus::COMPLETED)
            ->select('action_type', DB::raw('SUM(ABS(points)) as total_points'), DB::raw('COUNT(*) as count'))
            ->groupBy('action_type')
            ->orderBy('total_points', 'desc')
            ->limit(10)
            ->get();

        // =====================================================
        // إحصائيات الإنجازات حسب الفئة
        // =====================================================
        $achievementsByCategory = UserAchievement::where('employee_id', $employee->id)
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->select('achievements.category', DB::raw('COUNT(*) as count'), DB::raw('SUM(user_achievements.points_awarded) as total_points'))
            ->groupBy('achievements.category')
            ->get();

        // =====================================================
        // أفضل 5 أسابيع في جمع النقاط
        // =====================================================
        $topWeeks = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('status', TransactionStatus::COMPLETED)
            ->where('points', '>', 0)
            ->where('created_at', '>=', Carbon::now()->subMonths(3))
            ->select(
                DB::raw('YEARWEEK(created_at, 1) as week'),
                DB::raw('SUM(points) as total_points'),
                DB::raw('MIN(created_at) as week_start')
            )
            ->groupBy('week')
            ->orderBy('total_points', 'desc')
            ->limit(5)
            ->get();

        // =====================================================
        // إحصائيات التحديات
        // =====================================================
        $challengeStats = [
            'total_participated' => ChallengeParticipant::where('employee_id', $employee->id)->count(),
            'completed' => ChallengeParticipant::where('employee_id', $employee->id)
                ->where('is_completed', true)->count(),
            'active' => $activeChallenges->count(),
            'total_points_from_challenges' => ChallengeParticipant::where('employee_id', $employee->id)
                ->sum('points_awarded')
        ];

        // =====================================================
        // إحصائيات المكافآت
        // =====================================================
        $rewardStats = [
            'total_redeemed' => RewardRedemption::where('employee_id', $employee->id)->count(),
            'pending' => $pendingRedemptions,
            'approved' => RewardRedemption::where('employee_id', $employee->id)
                ->where('status', RedemptionStatus::APPROVED)->count(),
            'completed' => RewardRedemption::where('employee_id', $employee->id)
                ->where('status', RedemptionStatus::DELIVERED)->count(),
            'total_points_spent' => RewardRedemption::where('employee_id', $employee->id)
                ->whereIn('status', [RedemptionStatus::APPROVED, RedemptionStatus::DELIVERED])
                ->sum('points_used')
        ];

        // =====================================================
        // بيانات شريط تقدم النقاط الشهرية - بناءً على صافي النقاط
        // =====================================================
        $monthlyPointsData = $this->calculateMonthlyTierProgress(
            $monthNet,
            $allTiers,
            'employee',
            $filterMonth,
            $filterYear
        );

        // إحصائيات الموظف (مثل PointsUsersController)
        $employeeStats = [
            'current_balance' => $currentBalance,
            'total_earned' => $totalPointsEarned,
            'total_spent' => $totalPointsSpent,
            'transactions_count' => PointsTransaction::where('user_type', UserType::EMPLOYEE)->where('employee_id', $employee->id)->count(),
            'this_month_earned' => $monthEarned,
            'this_month_spent' => $monthSpent,
        ];

        // =====================================================
        // بيانات انتهاء صلاحية النقاط (شاملة)
        // =====================================================
        $expiringBatches = PointsBatch::where('employee_id', $employee->id)
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
            'active_batches' => PointsBatch::where('employee_id', $employee->id)
                ->where('status', BatchStatus::ACTIVE->value)
                ->where('remaining_points', '>', 0)
                ->count(),
            'expiring_soon_count' => $expiringBatches->count(),
            'expiring_soon_points' => $expiringPointsTotal,
            'nearest_expiry' => $nearestExpiryDate,
            'total_expired' => PointsBatch::where('employee_id', $employee->id)
                ->where('status', BatchStatus::EXPIRED->value)
                ->sum('expired_points'),
        ];

        // =====================================================
        // أحدث الأنشطة (Timeline)
        // =====================================================
        $recentActivities = collect();
        
        // إضافة المعاملات الأخيرة
        $recentTransactions->each(function($transaction) use ($recentActivities) {
            $recentActivities->push([
                'type' => 'transaction',
                'data' => $transaction,
                'date' => $transaction->created_at
            ]);
        });
        
        // إضافة الإنجازات الأخيرة
        $achievements->take(5)->each(function($achievement) use ($recentActivities) {
            $recentActivities->push([
                'type' => 'achievement',
                'data' => $achievement,
                'date' => $achievement->achieved_at
            ]);
        });
        
        // إضافة طلبات المكافآت
        $redemptions->take(5)->each(function($redemption) use ($recentActivities) {
            $recentActivities->push([
                'type' => 'redemption',
                'data' => $redemption,
                'date' => $redemption->created_at
            ]);
        });
        
        // ترتيب حسب التاريخ
        $recentActivities = $recentActivities->sortByDesc('date')->take(15);

        // =====================================================
        // قائمة الأشهر المتاحة للفلترة
        // =====================================================
        $availableMonths = $this->getAvailableMonths($employee->id, UserType::EMPLOYEE);

        return view('employee.profile', compact(
            'employee',
            'currentTier',
            'nextTier',
            'progressToNextTier',
            'pointsToNextTier',
            'currentBalance',
            'monthEarned',
            'monthSpent',
            'monthNet',
            'totalPointsEarned',
            'totalPointsSpent',
            'pointsThisMonth',
            'employeeStats',
            'transactions',
            'recentTransactions',
            'achievements',
            'totalAchievements',
            'availableAchievements',
            'activeChallenges',
            'completedChallenges',
            'tierUpgrades',
            'availableRewards',
            'redemptions',
            'pendingRedemptions',
            'expiringPoints',
            'expiringBatches',
            'teams',
            'monthlyPoints',
            'monthlyEarned',
            'monthlySpent',
            'transactionsByType',
            'achievementsByCategory',
            'topWeeks',
            'challengeStats',
            'rewardStats',
            'allTiers',
            'monthlyPointsData',
            'batchStats',
            'recentActivities',
            'filterMonth',
            'filterYear',
            'availableMonths',
            'isCurrentMonth'
        ));
    }

    /**
     * إنشاء طلب استبدال مكافأة
     * مبني على نفس منطق RewardRedemptionController::store()
     */
    public function requestReward(Request $request)
    {
        $request->validate([
            'reward_id' => 'required|exists:rewards,id',
            'quantity' => 'required|integer|min:1',
            'delivery_method' => 'nullable|string',
            'delivery_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500'
        ]);

        $user = Auth::user();
        $employee = $user->employee;

        if (!$employee) {
            return back()->with('error', 'هذه الميزة متاحة للموظفين فقط');
        }

        $reward = Reward::findOrFail($request->reward_id);

        // التحقق من أن المكافأة متاحة للموظفين
        if (!in_array($reward->user_type->value, ['employee', 'both'])) {
            return back()->with('error', 'هذه المكافأة غير متاحة للموظفين.');
        }

        // =====================================================
        // تطبيق إعدادات النظام العامة
        // =====================================================
        $autoApprove = false;
        try {
            $generalSettings = PointsGeneralSetting::instance();

            if (!$generalSettings->enable_employee_points) {
                return back()->with('error', 'نقاط الموظفين معطلة حالياً من الإعدادات العامة.');
            }

            if (!$generalSettings->require_redemption_approval) {
                $autoApprove = true;
            }

            // فحص الحد الأقصى لطلبات الاستبدال اليومية
            if ($generalSettings->max_daily_redemptions > 0) {
                $todayRedemptions = RewardRedemption::where('user_type', 'employee')
                    ->where('employee_id', $employee->id)
                    ->whereDate('created_at', today())
                    ->whereNotIn('status', [RedemptionStatus::CANCELLED->value])
                    ->count();

                if ($todayRedemptions >= $generalSettings->max_daily_redemptions) {
                    return back()->with('error', 'تم الوصول للحد الأقصى لطلبات الاستبدال اليومية (' . $generalSettings->max_daily_redemptions . ' طلبات). يرجى المحاولة غداً.');
                }
            }

            // فحص فترة الانتظار بين الطلبات
            if ($generalSettings->redemption_cooldown_hours > 0) {
                $lastRedemption = RewardRedemption::where('user_type', 'employee')
                    ->where('employee_id', $employee->id)
                    ->whereNotIn('status', [RedemptionStatus::CANCELLED->value])
                    ->latest()
                    ->first();

                if ($lastRedemption && $lastRedemption->created_at->diffInHours(now()) < $generalSettings->redemption_cooldown_hours) {
                    $remainingHours = $generalSettings->redemption_cooldown_hours - $lastRedemption->created_at->diffInHours(now());
                    return back()->with('error', 'يجب الانتظار ' . $remainingHours . ' ساعة على الأقل بين كل طلب استبدال.');
                }
            }
        } catch (\Exception $e) {
            // تجاوز في حالة عدم وجود جدول الإعدادات
        }

        // التحقق من أن الكمية = 1 للأنواع التي لا تدعم التكرار
        if ($reward->singleQuantityOnly() && $request->quantity > 1) {
            return back()->with('error', 'هذا النوع من المكافآت لا يدعم كمية أكثر من 1.');
        }

        // التحقق من توفر الكمية
        if ($reward->quantity_available !== null) {
            $remaining = $reward->getRemainingQuantity();
            if ($remaining !== null && $remaining < $request->quantity) {
                return back()->with('error', 'الكمية المطلوبة غير متاحة. الكمية المتبقية: ' . $remaining);
            }
        }

        // التحقق من كفاية رصيد النقاط (صافي نقاط الشهر الحالي فقط)
        $pointsToDeduct = $reward->points_required * $request->quantity;
        
        $currentMonthEarned = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points_month', now()->month)
            ->where('points_year', now()->year)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        $currentMonthSpent = abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employee->id)
            ->where('points_month', now()->month)
            ->where('points_year', now()->year)
            ->where('points', '<', 0)
            ->sum('points'));

        $currentBalance = max(0, $currentMonthEarned - $currentMonthSpent);

        if ($currentBalance < $pointsToDeduct) {
            return back()->with('error', 'رصيد النقاط غير كافٍ. الرصيد الحالي: ' . number_format($currentBalance) . ' نقطة | المطلوب: ' . number_format($pointsToDeduct) . ' نقطة');
        }

        // التحقق من المستوى المطلوب
        if ($reward->min_tier_id && $employee->tier_id) {
            $employeeTier = PointsTier::find($employee->tier_id);
            $requiredTier = PointsTier::find($reward->min_tier_id);
            if ($employeeTier && $requiredTier && $employeeTier->min_points < $requiredTier->min_points) {
                return back()->with('error', 'عذراً، مستواك الحالي لا يسمح بطلب هذه المكافأة.');
            }
        }

        // التحقق من الحد الأقصى لكل مستخدم
        if ($reward->max_per_user) {
            $count = RewardRedemption::where('reward_id', $reward->id)
                ->where('user_type', 'employee')
                ->where('employee_id', $employee->id)
                ->whereNotIn('status', [RedemptionStatus::CANCELLED->value])
                ->sum('quantity');

            if (($count + $request->quantity) > $reward->max_per_user) {
                return back()->with('error', 'تم تجاوز الحد الأقصى للاستبدال. الحد: ' . $reward->max_per_user . ' | السابق: ' . $count);
            }
        }

        DB::beginTransaction();
        try {
            // حساب قيمة المكافأة
            $rewardValue = $reward->reward_value * $request->quantity;

            // إنشاء طلب الاستبدال
            $redemption = RewardRedemption::create([
                'reward_id' => $reward->id,
                'employee_id' => $employee->id,
                'user_type' => UserType::EMPLOYEE->value,
                'points_used' => $pointsToDeduct,
                'reward_value' => $rewardValue,
                'quantity' => $request->quantity,
                'status' => $autoApprove ? RedemptionStatus::APPROVED->value : RedemptionStatus::PENDING->value,
                'delivery_method' => $request->delivery_method,
                'delivery_address' => $request->delivery_address,
                'notes' => $request->notes,
                'branch_id' => $employee->branch_id,
                'requested_at' => now(),
                // توليد كود القسيمة للأنواع التي تحتاجه
                'voucher_code' => $reward->needsVoucherCode()
                    ? 'VCH-' . strtoupper(Str::random(8))
                    : null,
                'voucher_serial' => $reward->needsVoucherCode()
                    ? 'SRL-' . date('Ymd') . '-' . strtoupper(Str::random(6))
                    : null,
                'voucher_pin' => $reward->needsVoucherCode()
                    ? str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT)
                    : null,
                'voucher_expires_at' => $reward->needsVoucherCode() && $reward->validity_days
                    ? now()->addDays($reward->validity_days)->toDateString()
                    : null,
                'voucher_status' => 'active',
            ]);

            // إعادة التحقق من الرصيد داخل الـ DB transaction (صافي نقاط الشهر الحالي)
            $txMonthEarned = PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('employee_id', $employee->id)
                ->where('points_month', now()->month)
                ->where('points_year', now()->year)
                ->where('points', '>', 0)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points');

            $txMonthSpent = abs(PointsTransaction::where('user_type', UserType::EMPLOYEE)
                ->where('employee_id', $employee->id)
                ->where('points_month', now()->month)
                ->where('points_year', now()->year)
                ->where('points', '<', 0)
                ->sum('points'));

            $currentBalance = max(0, $txMonthEarned - $txMonthSpent);

            if ($currentBalance < $pointsToDeduct) {
                throw new \Exception('رصيد النقاط غير كافٍ. الرصيد: ' . number_format($currentBalance) . ' | المطلوب: ' . number_format($pointsToDeduct));
            }

            // إنشاء معاملة خصم النقاط
            $transaction = PointsTransaction::create([
                'user_type' => UserType::EMPLOYEE->value,
                'employee_id' => $employee->id,
                'action_type' => 'redeem',
                'points' => -$pointsToDeduct,
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance - $pointsToDeduct,
                'description' => 'استبدال مكافأة: ' . $reward->name . ' (x' . $request->quantity . ')',
                'reference_type' => 'reward_redemption',
                'reference_id' => $redemption->id,
                'branch_id' => $employee->branch_id,
                'created_by' => Auth::id(),
                'status' => 'completed',
                'points_month' => now()->month,
                'points_year' => now()->year,
            ]);

            // ربط المعاملة بطلب الاستبدال
            $redemption->update([
                'metadata' => array_merge($redemption->metadata ?? [], [
                    'transaction_id' => $transaction->id,
                ]),
            ]);

            // تحديث كمية الاستبدال في المكافأة
            $reward->increment('quantity_redeemed', $request->quantity);

            // خصم النقاط من الدفعات (FIFO)
            $this->deductFromBatches($employee->id, $pointsToDeduct, $redemption->id);

            DB::commit();

            // إرسال إشعار
            try {
                app(PointsNotificationService::class)->notifyRewardRedeemed(
                    'employee',
                    $employee->id,
                    null,
                    [
                        'user_name' => $employee->full_name ?? $employee->name,
                        'reward_name' => $reward->name,
                        'points_used' => $pointsToDeduct,
                        'redemption_number' => $redemption->redemption_number,
                    ]
                );
            } catch (\Throwable $e) {
                \Log::warning('فشل إرسال إشعار استبدال المكافأة: ' . $e->getMessage());
            }

            $statusMsg = $autoApprove
                ? 'تم إرسال طلبك وتمت الموافقة عليه تلقائياً! رقم الطلب: ' . $redemption->redemption_number
                : 'تم إرسال طلب المكافأة بنجاح! رقم الطلب: ' . $redemption->redemption_number . ' — في انتظار موافقة المدير.';

            return back()->with('success', $statusMsg);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('خطأ في إنشاء طلب المكافأة: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء إرسال الطلب: ' . $e->getMessage());
        }
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
                $date = Carbon::create($m->points_year, $m->points_month, 1);
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
     * خصم النقاط من الدفعات بنظام FIFO (الأقدم أولاً)
     */
    private function deductFromBatches(int $employeeId, int $pointsToDeduct, int $redemptionId): void
    {
        try {
            $batches = PointsBatch::where('employee_id', $employeeId)
                ->where('status', BatchStatus::ACTIVE->value)
                ->where('remaining_points', '>', 0)
                ->where('user_type', 'employee')
                ->orderBy('earned_at', 'asc')
                ->orderBy('expires_at', 'asc')
                ->get();

            $remaining = $pointsToDeduct;

            foreach ($batches as $batch) {
                if ($remaining <= 0) break;

                $deduct = min($remaining, $batch->remaining_points);
                $batch->usePoints($deduct);
                $remaining -= $deduct;
            }

            if ($remaining > 0) {
                \Log::warning('خصم دفعات: لم تكف الدفعات لتغطية الخصم', [
                    'employee_id' => $employeeId,
                    'redemption_id' => $redemptionId,
                    'unmatched_points' => $remaining,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('فشل خصم النقاط من الدفعات: ' . $e->getMessage(), [
                'redemption_id' => $redemptionId,
            ]);
        }
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
