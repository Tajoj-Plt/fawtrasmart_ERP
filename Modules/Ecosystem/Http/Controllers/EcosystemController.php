<?php

namespace Modules\Ecosystem\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\PointsTier;
use App\Models\Ecosystem\TierUpgradeLog;
use App\Models\Ecosystem\RewardRedemption;
use App\Models\Ecosystem\UserAchievement;
use App\Models\Ecosystem\Challenge;
use App\Models\Ecosystem\ChallengeParticipant;
use App\Models\Ecosystem\Referral;
use App\Models\Employee;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

class EcosystemController extends Controller
{
  
    /**
     * Display ecosystem dashboard
     */
    public function index(Request $request)
    {
        // تواريخ للتصفية
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // =====================================================
        // الإحصائيات الرئيسية
        // =====================================================
        
        try {
            // إجمالي النقاط الممنوحة (النقاط الموجبة المكتملة)
            $totalPointsAwarded = PointsTransaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('points', '>', 0)
                ->where('status', 'completed')
                ->sum('points');
        } catch (\Exception $e) {
            $totalPointsAwarded = 0;
        }

        try {
            // إجمالي النقاط المستبدلة (النقاط السالبة)
            $totalPointsRedeemed = abs(PointsTransaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('points', '<', 0)
                ->sum('points'));
        } catch (\Exception $e) {
            $totalPointsRedeemed = 0;
        }

        try {
            // المستخدمون النشطون (لديهم معاملات في الفترة)
            $activeEmployees = PointsTransaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->whereNotNull('employee_id')
                ->distinct('employee_id')
                ->count('employee_id');
            
            $activeClients = PointsTransaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->whereNotNull('client_id')
                ->distinct('client_id')
                ->count('client_id');
            
            $activeUsers = $activeEmployees + $activeClients;
        } catch (\Exception $e) {
            $activeUsers = 0;
        }

        try {
            // المكافآت المطلوبة
            $totalRewardsClaimed = RewardRedemption::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();
        } catch (\Exception $e) {
            $totalRewardsClaimed = 0;
        }

        try {
            // المكافآت قيد المراجعة
            $pendingRedemptions = RewardRedemption::where('status', 'pending')->count();
        } catch (\Exception $e) {
            $pendingRedemptions = 0;
        }

        try {
            // الإنجازات المحققة
            $achievementsUnlocked = UserAchievement::whereBetween('achieved_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();
        } catch (\Exception $e) {
            $achievementsUnlocked = 0;
        }

        try {
            // التحديات النشطة
            $activeChallengesCount = Challenge::where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->count();
        } catch (\Exception $e) {
            $activeChallengesCount = 0;
        }

        try {
            // الإحالات
            $totalReferrals = Referral::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            $confirmedReferrals = Referral::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', 'confirmed')
                ->count();

            $pendingReferrals = Referral::where('status', 'pending')->count();
        } catch (\Exception $e) {
            $totalReferrals = 0;
            $confirmedReferrals = 0;
            $pendingReferrals = 0;
        }

        $stats = [
            'total_points_awarded' => $totalPointsAwarded,
            'total_points_redeemed' => $totalPointsRedeemed,
            'active_users' => $activeUsers,
            'total_rewards_claimed' => $totalRewardsClaimed,
            'achievements_unlocked' => $achievementsUnlocked,
            'active_challenges' => $activeChallengesCount,
            'pending_redemptions' => $pendingRedemptions,
            'total_referrals' => $totalReferrals,
            'confirmed_referrals' => $confirmedReferrals,
            'pending_referrals' => $pendingReferrals,
        ];

        // =====================================================
        // بيانات الرسم البياني (آخر 7 أيام)
        // =====================================================
        $labels = [];
        $pointsEarnedData = [];
        $pointsRedeemedData = [];
        
        try {
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $labels[] = $date->format('d/m');
                
                // النقاط الممنوحة
                $earned = PointsTransaction::whereDate('created_at', $date)
                    ->where('points', '>', 0)
                    ->where('status', 'completed')
                    ->sum('points');
                $pointsEarnedData[] = $earned;
                
                // النقاط المستبدلة
                $redeemed = abs(PointsTransaction::whereDate('created_at', $date)
                    ->where('points', '<', 0)
                    ->sum('points'));
                $pointsRedeemedData[] = $redeemed;
            }
        } catch (\Exception $e) {
            \Log::warning('Error loading chart data: ' . $e->getMessage());
            // بيانات افتراضية في حالة الخطأ
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $labels[] = $date->format('d/m');
                $pointsEarnedData[] = 0;
                $pointsRedeemedData[] = 0;
            }
        }

        // =====================================================
        // توزيع المستويات
        // =====================================================
        $tierDistribution = [];
        
        try {
            $tiers = PointsTier::orderBy('min_points')->get();
            
            foreach ($tiers as $tier) {
                $tierName = $tier->name;
                
                // عدد المستخدمين في هذا المستوى (آخر ترقية لكل مستخدم)
                $count = TierUpgradeLog::where('new_tier_id', $tier->id)
                    ->whereIn('id', function($query) {
                        $query->selectRaw('MAX(id)')
                            ->from('tier_upgrade_logs')
                            ->groupBy(DB::raw('COALESCE(employee_id, 0)'), DB::raw('COALESCE(client_id, 0)'));
                    })
                    ->count();
                
                if ($count > 0) {
                    $tierDistribution[$tierName] = $count;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error loading tier distribution: ' . $e->getMessage());
        }
        
        // إذا لم توجد بيانات، استخدم بيانات افتراضية
        if (empty($tierDistribution)) {
            $tierDistribution = ['لا توجد بيانات' => 1];
        }

        // =====================================================
        // أفضل المستخدمين (Top 5)
        // =====================================================
        
        $topUsers = [];
        
        try {
            // أفضل الموظفين
            $topEmployeesData = PointsTransaction::select(
                    'employee_id',
                    DB::raw('SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_points')
                )
                ->whereNotNull('employee_id')
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', 'completed')
                ->groupBy('employee_id')
                ->orderByDesc('total_points')
                ->limit(3)
                ->with('employee')
                ->get();

            // أفضل العملاء
            $topClientsData = PointsTransaction::select(
                    'client_id',
                    DB::raw('SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_points')
                )
                ->whereNotNull('client_id')
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', 'completed')
                ->groupBy('client_id')
                ->orderByDesc('total_points')
                ->limit(2)
                ->with('client')
                ->get();

            // دمج الموظفين والعملاء
            foreach ($topEmployeesData as $emp) {
                if ($emp->employee) {
                    // جلب آخر مستوى
                    $lastTier = TierUpgradeLog::where('employee_id', $emp->employee_id)
                        ->orderBy('created_at', 'desc')
                        ->with('newTier')
                        ->first();
                    
                    $tierName = 'بدون مستوى';
                    if ($lastTier && $lastTier->newTier) {
                        $tierName = $lastTier->newTier->name;
                    } elseif ($lastTier && $lastTier->new_tier_id) {
                        // محاولة جلب المستوى يدوياً إذا لم تُحمّل العلاقة
                        $tier = PointsTier::find($lastTier->new_tier_id);
                        $tierName = $tier ? $tier->name : 'بدون مستوى';
                    }
                    
                    // عدد الإنجازات
                    $achievementsCount = UserAchievement::where('employee_id', $emp->employee_id)->count();
                    
                    $topUsers[] = [
                        'name' => $emp->employee->full_name ?? 'موظف',
                        'points' => $emp->total_points,
                        'tier' => $tierName,
                        'achievements' => $achievementsCount,
                    ];
                }
            }
            
            foreach ($topClientsData as $cli) {
                if ($cli->client) {
                    // جلب آخر مستوى
                    $lastTier = TierUpgradeLog::where('client_id', $cli->client_id)
                        ->orderBy('created_at', 'desc')
                        ->with('newTier')
                        ->first();
                    
                    $tierName = 'بدون مستوى';
                    if ($lastTier && $lastTier->newTier) {
                        $tierName = $lastTier->newTier->name;
                    } elseif ($lastTier && $lastTier->new_tier_id) {
                        // محاولة جلب المستوى يدوياً إذا لم تُحمّل العلاقة
                        $tier = PointsTier::find($lastTier->new_tier_id);
                        $tierName = $tier ? $tier->name : 'بدون مستوى';
                    }
                    
                    // عدد الإنجازات
                    $achievementsCount = UserAchievement::where('client_id', $cli->client_id)->count();
                    
                    $topUsers[] = [
                        'name' => $cli->client->full_name ?? $cli->client->trade_name ?? 'عميل',
                        'points' => $cli->total_points,
                        'tier' => $tierName,
                        'achievements' => $achievementsCount,
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error loading top users: ' . $e->getMessage());
        }

        // إذا لم توجد بيانات، استخدم بيانات افتراضية
        if (empty($topUsers)) {
            $topUsers = [
                ['name' => 'لا توجد بيانات', 'points' => 0, 'tier' => '-', 'achievements' => 0],
            ];
        }

        // =====================================================
        // آخر المعاملات (أحدث 10)
        // =====================================================
        $recentTransactions = [];
        
        try {
            $transactions = PointsTransaction::with(['employee', 'client'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            foreach ($transactions as $trans) {
                $userName = 'غير معروف';
                if ($trans->employee) {
                    $userName = $trans->employee->full_name ?? 'موظف';
                } elseif ($trans->client) {
                    $userName = $trans->client->full_name ?? $trans->client->trade_name ?? 'عميل';
                }

                $recentTransactions[] = [
                    'user' => $userName,
                    'action' => $trans->action_type ?? 'معاملة',
                    'points' => ($trans->points > 0 ? '+' : '') . number_format($trans->points),
                    'time' => $trans->created_at->diffForHumans(),
                ];
            }
        } catch (\Exception $e) {
            \Log::warning('Error loading recent transactions: ' . $e->getMessage());
        }

        if (empty($recentTransactions)) {
            $recentTransactions = [
                ['user' => 'لا توجد معاملات', 'action' => '-', 'points' => '0', 'time' => '-'],
            ];
        }

        // =====================================================
        // التحديات النشطة
        // =====================================================
        $activeChallenges = [];
        
        try {
            $challenges = Challenge::where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->withCount('participants')
                ->limit(6)
                ->get();

            foreach ($challenges as $challenge) {
                // حساب متوسط التقدم
                $avgProgress = ChallengeParticipant::where('challenge_id', $challenge->id)
                    ->avg('progress_percentage') ?? 0;
                
                // حساب الأيام المتبقية
                $daysLeft = now()->diffInDays($challenge->ends_at, false);
                $endsText = $daysLeft > 0 
                    ? "ينتهي خلال {$daysLeft} يوم" 
                    : ($daysLeft == 0 ? 'ينتهي اليوم' : 'منتهي');

                $activeChallenges[] = [
                    'name' => $challenge->name,
                    'participants' => $challenge->participants_count,
                    'progress' => round($avgProgress),
                    'ends' => $endsText,
                ];
            }
        } catch (\Exception $e) {
            \Log::warning('Error loading active challenges: ' . $e->getMessage());
        }

        if (empty($activeChallenges)) {
            $activeChallenges = [
                ['name' => 'لا توجد تحديات نشطة', 'participants' => 0, 'progress' => 0, 'ends' => '-'],
            ];
        }

        // =====================================================
        // إحصائيات إضافية
        // =====================================================
        
        try {
            // عدد الإنجازات النادرة (محققة من قبل أقل من 10% من المستخدمين)
            $totalUsersWithAchievements = UserAchievement::distinct('employee_id', 'client_id')->count();
            $rareAchievements = UserAchievement::select('achievement_id', DB::raw('COUNT(*) as unlock_count'))
                ->groupBy('achievement_id')
                ->having('unlock_count', '<=', max($totalUsersWithAchievements * 0.1, 1))
                ->count();
        } catch (\Exception $e) {
            $rareAchievements = 0;
        }

        try {
            // إجمالي المشاركين في التحديات
            $totalChallengeParticipants = ChallengeParticipant::whereHas('challenge', function($q) {
                $q->where('status', 'active');
            })->count();
        } catch (\Exception $e) {
            $totalChallengeParticipants = 0;
        }

        try {
            // التحديات المنتهية
            $completedChallenges = Challenge::where('status', 'completed')->count();
        } catch (\Exception $e) {
            $completedChallenges = 0;
        }

        try {
            // معدل الإنجاز (نسبة الإنجازات المحققة للإنجازات المتاحة)
            $totalAchievements = \App\Models\Ecosystem\Achievement::where('is_active', true)->count();
            $achievementRate = $totalAchievements > 0 && $achievementsUnlocked > 0
                ? round(($achievementsUnlocked / $totalAchievements) * 100, 1) 
                : 0;
        } catch (\Exception $e) {
            $achievementRate = 0;
        }

        $additionalStats = [
            'rare_achievements' => $rareAchievements,
            'total_challenge_participants' => $totalChallengeParticipants,
            'completed_challenges' => $completedChallenges,
            'achievement_rate' => $achievementRate,
        ];

        // =====================================================
        // إحصائيات تفصيلية إضافية
        // =====================================================
        
        // توزيع النقاط حسب نوع الإجراء (Top 10)
        try {
            $pointsByAction = PointsTransaction::select(
                    'action_type',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_points')
                )
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', 'completed')
                ->groupBy('action_type')
                ->orderByDesc('total_points')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            $pointsByAction = collect([]);
        }

        // نمو المستخدمين - آخر 30 يوم
        try {
            $userGrowth = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $empCount = PointsTransaction::whereDate('created_at', $date)
                    ->whereNotNull('employee_id')
                    ->distinct('employee_id')
                    ->count('employee_id');
                
                $cliCount = PointsTransaction::whereDate('created_at', $date)
                    ->whereNotNull('client_id')
                    ->distinct('client_id')
                    ->count('client_id');
                
                $userGrowth[] = [
                    'date' => $date->format('d/m'),
                    'employees' => $empCount,
                    'clients' => $cliCount,
                ];
            }
        } catch (\Exception $e) {
            $userGrowth = [];
        }

        // آخر الإنجازات المحققة
        try {
            $recentAchievements = UserAchievement::with(['achievement', 'employee', 'client'])
                ->orderBy('achieved_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($ua) {
                    $userName = 'غير معروف';
                    if ($ua->employee) {
                        $userName = $ua->employee->full_name ?? 'موظف';
                    } elseif ($ua->client) {
                        $userName = $ua->client->full_name ?? $ua->client->trade_name ?? 'عميل';
                    }
                    
                    return [
                        'user' => $userName,
                        'achievement' => $ua->achievement ? $ua->achievement->name : 'إنجاز',
                        'points' => $ua->points_awarded ?? 0,
                        'time' => $ua->achieved_at ? $ua->achieved_at->diffForHumans() : '-',
                    ];
                });
        } catch (\Exception $e) {
            $recentAchievements = collect([]);
        }

        if ($recentAchievements->isEmpty()) {
            $recentAchievements = collect([
                ['user' => 'لا توجد إنجازات', 'achievement' => '-', 'points' => 0, 'time' => '-'],
            ]);
        }

        // إحصائيات الاستبدالات التفصيلية
        try {
            $redemptionStats = [
                'total' => RewardRedemption::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count(),
                'pending' => RewardRedemption::where('status', 'pending')->count(),
                'approved' => RewardRedemption::where('status', 'approved')->count(),
                'delivered' => RewardRedemption::where('status', 'delivered')->count(),
                'cancelled' => RewardRedemption::where('status', 'cancelled')->count(),
                'total_points_used' => RewardRedemption::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->sum('points_used'),
            ];
        } catch (\Exception $e) {
            $redemptionStats = [
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'delivered' => 0,
                'cancelled' => 0,
                'total_points_used' => 0,
            ];
        }

        // أفضل المكافآت المطلوبة
        try {
            $topRewards = RewardRedemption::select(
                    'reward_id',
                    DB::raw('COUNT(*) as redemption_count'),
                    DB::raw('SUM(points_used) as total_points')
                )
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('reward_id')
                ->orderByDesc('redemption_count')
                ->limit(5)
                ->with('reward')
                ->get()
                ->map(function($item) {
                    return [
                        'name' => $item->reward ? $item->reward->name : 'مكافأة محذوفة',
                        'count' => $item->redemption_count,
                        'points' => $item->total_points,
                    ];
                });
        } catch (\Exception $e) {
            $topRewards = collect([]);
        }

        if ($topRewards->isEmpty()) {
            $topRewards = collect([
                ['name' => 'لا توجد بيانات', 'count' => 0, 'points' => 0],
            ]);
        }

        // النشاط حسب الفروع
        try {
            $branchActivity = PointsTransaction::select(
                    'branch_id',
                    DB::raw('COUNT(*) as transaction_count'),
                    DB::raw('SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_points')
                )
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->whereNotNull('branch_id')
                ->where('status', 'completed')
                ->groupBy('branch_id')
                ->orderByDesc('total_points')
                ->limit(10)
                ->with('branch')
                ->get()
                ->map(function($item) {
                    return [
                        'branch' => $item->branch ? $item->branch->name : 'فرع محذوف',
                        'transactions' => $item->transaction_count,
                        'points' => $item->total_points,
                    ];
                });
        } catch (\Exception $e) {
            $branchActivity = collect([]);
        }

        if ($branchActivity->isEmpty()) {
            $branchActivity = collect([
                ['branch' => 'لا توجد بيانات', 'transactions' => 0, 'points' => 0],
            ]);
        }

        // معدلات الإكمال والنشاط
        try {
            $activityRates = [
                'completion_rate' => $stats['total_points_awarded'] > 0 
                    ? round((PointsTransaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                        ->where('status', 'completed')->count() / 
                        PointsTransaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count()) * 100, 1)
                    : 0,
                'redemption_rate' => $stats['total_points_awarded'] > 0
                    ? round(($stats['total_points_redeemed'] / $stats['total_points_awarded']) * 100, 1)
                    : 0,
                'avg_points_per_user' => $stats['active_users'] > 0
                    ? round($stats['total_points_awarded'] / $stats['active_users'], 1)
                    : 0,
                'avg_transactions_per_user' => $stats['active_users'] > 0
                    ? round(PointsTransaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                        ->count() / $stats['active_users'], 1)
                    : 0,
            ];
        } catch (\Exception $e) {
            $activityRates = [
                'completion_rate' => 0,
                'redemption_rate' => 0,
                'avg_points_per_user' => 0,
                'avg_transactions_per_user' => 0,
            ];
        }

        return view('ecosystem::dashboard.index', compact(
            'stats',
            'labels',
            'pointsEarnedData',
            'pointsRedeemedData',
            'tierDistribution',
            'topUsers',
            'recentTransactions',
            'activeChallenges',
            'additionalStats',
            'pointsByAction',
            'userGrowth',
            'recentAchievements',
            'redemptionStats',
            'topRewards',
            'branchActivity',
            'activityRates',
            'startDate',
            'endDate'
        ));
    }
}
