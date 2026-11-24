<?php

namespace Modules\Ecosystem\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\PointsTier;
use App\Models\Ecosystem\TierUpgradeLog;
use App\Models\Ecosystem\Reward;
use App\Models\Ecosystem\RewardRedemption;
use App\Models\Ecosystem\Achievement;
use App\Models\Ecosystem\UserAchievement;
use App\Models\Ecosystem\Challenge;
use App\Models\Ecosystem\ChallengeParticipant;
use App\Models\Ecosystem\Referral;
use App\Models\Ecosystem\ReferralSetting;
use App\Models\Ecosystem\PointsBatch;
use App\Models\Ecosystem\PointsDailySummary;
use App\Models\Ecosystem\PointsSetting;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PointsReportController extends Controller
{
    // =====================================================
    // تقرير النقاط الشامل (Points Overview Report)
    // =====================================================
    public function points(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());
        $userType = $request->input('user_type');
        $branchId = $request->input('branch_id');
        $actionType = $request->input('action_type');
        $status = $request->input('status');
        $search = $request->input('search');
        $minPoints = $request->input('min_points');
        $maxPoints = $request->input('max_points');

        // Base query
        $query = PointsTransaction::with(['employee', 'client', 'branch', 'creator'])
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($userType) {
            $query->where('user_type', $userType);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($actionType) {
            $query->where('action_type', $actionType);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($minPoints !== null) {
            $query->where('points', '>=', $minPoints);
        }
        if ($maxPoints !== null) {
            $query->where('points', '<=', $maxPoints);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%$search%")
                  ->orWhere('action_type', 'like', "%$search%")
                  ->orWhereHas('employee', fn($e) => $e->where('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%"))
                  ->orWhereHas('client', fn($c) => $c->where('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%")->orWhere('trade_name', 'like', "%$search%"));
            });
        }

        // Aggregate stats
        $statsQuery = (clone $query);
        $stats = [
            'total_transactions' => (clone $statsQuery)->count(),
            'total_earned' => (clone $statsQuery)->where('points', '>', 0)->sum('points'),
            'total_spent' => abs((clone $statsQuery)->where('points', '<', 0)->sum('points')),
            'net_points' => (clone $statsQuery)->sum('points'),
            'avg_transaction' => round((clone $statsQuery)->avg('points') ?? 0, 1),
            'unique_employees' => (clone $statsQuery)->whereNotNull('employee_id')->distinct('employee_id')->count('employee_id'),
            'unique_clients' => (clone $statsQuery)->whereNotNull('client_id')->distinct('client_id')->count('client_id'),
        ];

        // Top earners
        $topEmployees = PointsTransaction::select('employee_id', DB::raw('SUM(points) as total_points'), DB::raw('COUNT(*) as transactions_count'))
            ->whereNotNull('employee_id')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('employee_id')
            ->orderByDesc('total_points')
            ->limit(10)
            ->with('employee')
            ->get();

        $topClients = PointsTransaction::select('client_id', DB::raw('SUM(points) as total_points'), DB::raw('COUNT(*) as transactions_count'))
            ->whereNotNull('client_id')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('client_id')
            ->orderByDesc('total_points')
            ->limit(10)
            ->with('client')
            ->get();

        // Points by action type
        $pointsByAction = PointsTransaction::select('action_type', DB::raw('SUM(points) as total_points'), DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($userType, fn($q) => $q->where('user_type', $userType))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('action_type')
            ->orderByDesc('total_points')
            ->get();

        // Daily trend
        $dailyTrend = PointsTransaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as earned'),
                DB::raw('SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as spent'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($userType, fn($q) => $q->where('user_type', $userType))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Action types for filter
        $actionTypes = PointsTransaction::select('action_type')->distinct()->pluck('action_type');

        $transactions = $query->latest()->paginate(25)->withQueryString();
        $branches = Branch::orderBy('name')->get();

        return view('ecosystem::points.reports.points', compact(
            'transactions', 'stats', 'topEmployees', 'topClients',
            'pointsByAction', 'dailyTrend', 'branches', 'actionTypes',
            'dateFrom', 'dateTo'
        ));
    }

    // =====================================================
    // تقرير المستويات (Tiers Report)
    // =====================================================
    public function tiers(Request $request)
    {
        $userType = $request->input('user_type');
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subMonths(3)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());

        $tiers = PointsTier::when($userType, fn($q) => $q->where('user_type', $userType))
          ->orderBy('min_points')
          ->get();

        // Tier distribution (check if tier_id column exists)
        $employeeTierDistribution = collect([]);
        $clientTierDistribution = collect([]);
        
        try {
            if (DB::getSchemaBuilder()->hasColumn('employees', 'tier_id')) {
                $employeeTierDistribution = Employee::select('tier_id', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('tier_id')
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->groupBy('tier_id')
                    ->pluck('count', 'tier_id');
            }
            
            if (DB::getSchemaBuilder()->hasColumn('clients', 'tier_id')) {
                $clientTierDistribution = Client::select('tier_id', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('tier_id')
                    ->groupBy('tier_id')
                    ->pluck('count', 'tier_id');
            }
        } catch (\Exception $e) {
            // Silently handle if columns don't exist
        }
        
        // Attach counts to tiers manually
        $tiers->each(function($tier) use ($employeeTierDistribution, $clientTierDistribution) {
            $tier->employees_count = $employeeTierDistribution[$tier->id] ?? 0;
            $tier->clients_count = $clientTierDistribution[$tier->id] ?? 0;
        });

        // Upgrade log stats
        $upgradeStats = TierUpgradeLog::whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($branchId, fn($q) => $q->whereHas('employee', fn($e) => $e->where('branch_id', $branchId)))
            ->select(
                DB::raw('COUNT(*) as total_upgrades'),
                DB::raw('SUM(CASE WHEN upgrade_type = "automatic" THEN 1 ELSE 0 END) as auto_upgrades'),
                DB::raw('SUM(CASE WHEN upgrade_type = "manual" THEN 1 ELSE 0 END) as manual_upgrades')
            )
            ->first();

        // Upgrades per tier
        $upgradesPerTier = TierUpgradeLog::select('new_tier_id', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('new_tier_id')
            ->pluck('count', 'new_tier_id');

        // Monthly upgrades trend
        $monthlyUpgrades = TierUpgradeLog::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Recent upgrades
        $recentUpgrades = TierUpgradeLog::with(['employee', 'client', 'oldTier', 'newTier'])
            ->latest()
            ->limit(20)
            ->get();

        $branches = Branch::orderBy('name')->get();

        return view('ecosystem::points.reports.tiers', compact(
            'tiers', 'employeeTierDistribution', 'clientTierDistribution',
            'upgradeStats', 'upgradesPerTier', 'monthlyUpgrades',
            'recentUpgrades', 'branches', 'dateFrom', 'dateTo'
        ));
    }

    // =====================================================
    // تقرير المكافآت (Rewards Report)
    // =====================================================
    public function rewards(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());
        $userType = $request->input('user_type');
        $branchId = $request->input('branch_id');
        $rewardType = $request->input('reward_type');
        $status = $request->input('status');

        // Redemption stats
        $redemptionQuery = RewardRedemption::whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($status, fn($q) => $q->where('status', $status));

        $stats = [
            'total_redemptions' => (clone $redemptionQuery)->count(),
            'total_points_used' => (clone $redemptionQuery)->sum('points_used'),
            'pending_count' => (clone $redemptionQuery)->where('status', 'pending')->count(),
            'approved_count' => (clone $redemptionQuery)->where('status', 'approved')->count(),
            'delivered_count' => (clone $redemptionQuery)->where('status', 'delivered')->count(),
            'cancelled_count' => (clone $redemptionQuery)->where('status', 'cancelled')->count(),
            'total_rewards_value' => (clone $redemptionQuery)->whereHas('reward')->with('reward')->get()->sum(fn($r) => $r->reward->reward_value ?? 0),
        ];

        // Top rewards
        $topRewards = RewardRedemption::select('reward_id', DB::raw('COUNT(*) as redemption_count'), DB::raw('SUM(points_used) as total_points'))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('reward_id')
            ->orderByDesc('redemption_count')
            ->limit(10)
            ->with('reward')
            ->get();

        // Redemptions by status
        $redemptionsByStatus = RewardRedemption::select('status', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Daily redemptions trend
        $dailyRedemptions = RewardRedemption::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(points_used) as points')
            )
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Reward availability
        $rewardAvailability = Reward::select('id', 'name', 'points_required', 'quantity_available', 'reward_type', 'is_active')
            ->withCount('redemptions')
            ->orderByDesc('redemptions_count')
            ->get();

        $recentRedemptions = RewardRedemption::with(['reward', 'employee', 'client'])
            ->latest()
            ->limit(20)
            ->get();

        $branches = Branch::orderBy('name')->get();
        $rewardTypes = Reward::select('reward_type')->distinct()->pluck('reward_type');

        return view('ecosystem::points.reports.rewards', compact(
            'stats', 'topRewards', 'redemptionsByStatus', 'dailyRedemptions',
            'rewardAvailability', 'recentRedemptions', 'branches', 'rewardTypes',
            'dateFrom', 'dateTo'
        ));
    }

    // =====================================================
    // تقرير الإنجازات (Achievements Report)
    // =====================================================
    public function achievements(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());
        $userType = $request->input('user_type');
        $category = $request->input('category');

        // Stats
        $achievementQuery = UserAchievement::whereBetween('achieved_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $stats = [
            'total_unlocked' => (clone $achievementQuery)->count(),
            'total_points_awarded' => (clone $achievementQuery)->sum('points_awarded'),
            'unique_employees' => (clone $achievementQuery)->whereNotNull('employee_id')->distinct('employee_id')->count('employee_id'),
            'unique_clients' => (clone $achievementQuery)->whereNotNull('client_id')->distinct('client_id')->count('client_id'),
            'total_achievements' => Achievement::count(),
            'active_achievements' => Achievement::where('is_active', true)->count(),
        ];

        // Top achievements
        $topAchievements = UserAchievement::select('achievement_id', DB::raw('COUNT(*) as unlock_count'), DB::raw('SUM(points_awarded) as total_points'))
            ->whereBetween('achieved_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('achievement_id')
            ->orderByDesc('unlock_count')
            ->limit(10)
            ->with('achievement')
            ->get();

        // Achievements by category
        $achievementsByCategory = Achievement::select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category');

        // Monthly unlocks trend
        $monthlyUnlocks = UserAchievement::select(
                DB::raw('DATE_FORMAT(achieved_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(points_awarded) as points')
            )
            ->whereBetween('achieved_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy(DB::raw('DATE_FORMAT(achieved_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Hardest achievements (least unlocked)
        $hardestAchievements = Achievement::withCount('userAchievements')
            ->where('is_active', true)
            ->orderBy('user_achievements_count')
            ->limit(10)
            ->get();

        // Recent unlocks
        $recentUnlocks = UserAchievement::with(['achievement', 'employee', 'client'])
            ->latest('achieved_at')
            ->limit(20)
            ->get();

        // User leaderboard
        $topEmployeeAchievers = UserAchievement::select('employee_id', DB::raw('COUNT(*) as achievements_count'), DB::raw('SUM(points_awarded) as total_points'))
            ->whereNotNull('employee_id')
            ->whereBetween('achieved_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('employee_id')
            ->orderByDesc('achievements_count')
            ->limit(10)
            ->with('employee')
            ->get();

        $categories = Achievement::select('category')->distinct()->pluck('category');

        return view('ecosystem::points.reports.achievements', compact(
            'stats', 'topAchievements', 'achievementsByCategory', 'monthlyUnlocks',
            'hardestAchievements', 'recentUnlocks', 'topEmployeeAchievers', 'categories',
            'dateFrom', 'dateTo'
        ));
    }

    // =====================================================
    // تقرير التحديات (Challenges Report)
    // =====================================================
    public function challenges(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());
        $userType = $request->input('user_type');
        $challengeType = $request->input('challenge_type');
        $status = $request->input('status');

        // Stats
        try {
            $hasStatusColumn = DB::getSchemaBuilder()->hasColumn('challenges', 'status');
        } catch (\Exception $e) {
            $hasStatusColumn = false;
        }
        
        $stats = [
            'total_challenges' => Challenge::count(),
            'active_challenges' => $hasStatusColumn ? Challenge::where('status', 'active')->count() : 0,
            'completed_challenges' => $hasStatusColumn ? Challenge::where('status', 'completed')->count() : 0,
            'total_participants' => ChallengeParticipant::count(),
            'total_points_awarded' => ChallengeParticipant::sum('points_awarded'),
            'avg_completion_rate' => round(ChallengeParticipant::where('is_completed', true)->count() / max(ChallengeParticipant::count(), 1) * 100, 1),
        ];

        // Challenges list with stats
        try {
            $hasUserTypeColumn = DB::getSchemaBuilder()->hasColumn('challenges', 'user_type');
            $hasChallengeTypeColumn = DB::getSchemaBuilder()->hasColumn('challenges', 'challenge_type');
        } catch (\Exception $e) {
            $hasUserTypeColumn = false;
            $hasChallengeTypeColumn = false;
        }
        
        $challengeQuery = Challenge::withCount('participants')
            ->withSum('participants', 'points_awarded')
            ->when($userType && $hasUserTypeColumn, fn($q) => $q->where('user_type', $userType))
            ->when($challengeType && $hasChallengeTypeColumn, fn($q) => $q->where('challenge_type', $challengeType))
            ->when($status && $hasStatusColumn, fn($q) => $q->where('status', $status));

        $challenges = $challengeQuery->latest()->paginate(15)->withQueryString();

        // Challenge type distribution
        try {
            $hasCompetitionTypeColumn = DB::getSchemaBuilder()->hasColumn('challenges', 'competition_type');
        } catch (\Exception $e) {
            $hasCompetitionTypeColumn = false;
        }
        
        $challengeTypeDistribution = $hasChallengeTypeColumn 
            ? Challenge::select('challenge_type', DB::raw('COUNT(*) as count'))
                ->groupBy('challenge_type')
                ->pluck('count', 'challenge_type')
            : collect([]);

        // Competition type distribution
        $competitionTypeDistribution = $hasCompetitionTypeColumn
            ? Challenge::select('competition_type', DB::raw('COUNT(*) as count'))
                ->groupBy('competition_type')
                ->pluck('count', 'competition_type')
            : collect([]);

        // Top participants
        $topParticipants = ChallengeParticipant::select(
                'employee_id', 'client_id',
                DB::raw('COUNT(*) as challenges_joined'),
                DB::raw('SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as challenges_completed'),
                DB::raw('SUM(points_awarded) as total_points')
            )
            ->groupBy('employee_id', 'client_id')
            ->orderByDesc('total_points')
            ->limit(15)
            ->with(['employee', 'client'])
            ->get();

        // Monthly challenges trend
        $monthlyChallenges = Challenge::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        $branches = Branch::orderBy('name')->get();

        return view('ecosystem::points.reports.challenges', compact(
            'stats', 'challenges', 'challengeTypeDistribution', 'competitionTypeDistribution',
            'topParticipants', 'monthlyChallenges', 'branches', 'dateFrom', 'dateTo'
        ));
    }

    // =====================================================
    // تقرير الإحالات (Referrals Report)
    // =====================================================
    public function referrals(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());
        $status = $request->input('status');

        $referralQuery = Referral::with(['referrer', 'referred'])
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($status, fn($q) => $q->where('status', $status));

        // Stats
        $stats = [
            'total_referrals' => (clone $referralQuery)->count(),
            'confirmed_referrals' => (clone $referralQuery)->where('status', 'confirmed')->count(),
            'pending_referrals' => (clone $referralQuery)->where('status', 'pending')->count(),
            'cancelled_referrals' => (clone $referralQuery)->where('status', 'cancelled')->count(),
            'total_referrer_points' => (clone $referralQuery)->where('status', 'confirmed')->sum('referrer_points'),
            'total_referred_points' => (clone $referralQuery)->where('status', 'confirmed')->sum('referred_points'),
            'conversion_rate' => round(
                (clone $referralQuery)->where('status', 'confirmed')->count() / max((clone $referralQuery)->count(), 1) * 100, 1
            ),
        ];

        // Top referrers
        $topReferrers = Referral::select('referrer_id', DB::raw('COUNT(*) as referral_count'), DB::raw('SUM(referrer_points) as total_points'))
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('referrer_id')
            ->orderByDesc('referral_count')
            ->limit(10)
            ->with('referrer')
            ->get();

        // Monthly referrals trend
        $monthlyReferrals = Referral::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed')
            )
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Referrals by status
        $referralsByStatus = Referral::select('status', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('status')
            ->pluck('count', 'status');

        // Recent referrals
        $referrals = $referralQuery->latest()->paginate(20)->withQueryString();

        // Active referral settings
        $activeSettings = ReferralSetting::where('is_active', true)->get();

        return view('ecosystem::points.reports.referrals', compact(
            'stats', 'topReferrers', 'monthlyReferrals', 'referralsByStatus',
            'referrals', 'activeSettings', 'dateFrom', 'dateTo'
        ));
    }

    // =====================================================
    // تقرير انتهاء الصلاحية (Expiry Report)
    // =====================================================
    public function expiry(Request $request)
    {
        $branchId = $request->input('branch_id');
        $userType = $request->input('user_type');
        $status = $request->input('status');
        $daysToExpiry = $request->input('days_to_expiry', 30);

        // Stats
        $activeBatches = PointsBatch::where('status', 'active');
        $stats = [
            'total_active_batches' => (clone $activeBatches)->count(),
            'total_remaining_points' => (clone $activeBatches)->sum('remaining_points'),
            'expiring_soon' => (clone $activeBatches)->where('expires_at', '<=', Carbon::now()->addDays($daysToExpiry))->where('expires_at', '>', Carbon::now())->count(),
            'expiring_soon_points' => (clone $activeBatches)->where('expires_at', '<=', Carbon::now()->addDays($daysToExpiry))->where('expires_at', '>', Carbon::now())->sum('remaining_points'),
            'expired_total' => PointsBatch::where('status', 'expired')->count(),
            'expired_points_total' => PointsBatch::where('status', 'expired')->sum('expired_points'),
            'depleted_total' => PointsBatch::where('status', 'depleted')->count(),
        ];

        // Batches expiring soon
        $expiringBatches = PointsBatch::with(['employee', 'client', 'transaction'])
            ->where('status', 'active')
            ->where('expires_at', '<=', Carbon::now()->addDays($daysToExpiry))
            ->where('expires_at', '>', Carbon::now())
            ->when($userType === 'employee', fn($q) => $q->whereNotNull('employee_id'))
            ->when($userType === 'client', fn($q) => $q->whereNotNull('client_id'))
            ->orderBy('expires_at')
            ->paginate(20)
            ->withQueryString();

        // Expiry by month
        $expiryByMonth = PointsBatch::select(
                DB::raw('DATE_FORMAT(expires_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(remaining_points) as remaining_points')
            )
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->groupBy(DB::raw('DATE_FORMAT(expires_at, "%Y-%m")'))
            ->orderBy('month')
            ->limit(12)
            ->get();

        // Recently expired
        $recentlyExpired = PointsBatch::with(['employee', 'client'])
            ->where('status', 'expired')
            ->latest('expires_at')
            ->limit(20)
            ->get();

        $branches = Branch::orderBy('name')->get();

        return view('ecosystem::points.reports.expiry', compact(
            'stats', 'expiringBatches', 'expiryByMonth', 'recentlyExpired',
            'branches', 'daysToExpiry'
        ));
    }

    // =====================================================
    // الملخص اليومي (Daily Summary)
    // =====================================================
    public function dailySummary(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());
        $userType = $request->input('user_type');
        $branchId = $request->input('branch_id');

        $query = PointsDailySummary::query()
            ->betweenDates($dateFrom, $dateTo)
            ->when($userType, fn($q) => $q->where('user_type', $userType))
            ->when($branchId, fn($q) => $q->forBranch($branchId));

        // Aggregated totals
        $totals = [
            'total_earned' => (clone $query)->sum('total_earned'),
            'total_spent' => (clone $query)->sum('total_spent'),
            'total_expired' => (clone $query)->sum('total_expired'),
            'total_adjusted' => (clone $query)->sum('total_adjusted'),
            'transactions_count' => (clone $query)->sum('transactions_count'),
            'unique_users' => (clone $query)->sum('unique_users'),
            'tier_upgrades' => (clone $query)->sum('new_tier_upgrades'),
            'redemptions_count' => (clone $query)->sum('redemptions_count'),
            'redemptions_value' => (clone $query)->sum('redemptions_value'),
            'achievements_unlocked' => (clone $query)->sum('achievements_unlocked'),
            'challenges_completed' => (clone $query)->sum('challenges_completed'),
            'referrals_confirmed' => (clone $query)->sum('referrals_confirmed'),
        ];
        $totals['net_change'] = $totals['total_earned'] - $totals['total_spent'] - $totals['total_expired'] + $totals['total_adjusted'];

        $summaries = $query->orderByDesc('date')->paginate(30)->withQueryString();

        // Daily chart data
        $chartData = PointsDailySummary::query()
            ->betweenDates($dateFrom, $dateTo)
            ->when($userType, fn($q) => $q->where('user_type', $userType))
            ->when($branchId, fn($q) => $q->forBranch($branchId))
            ->select(
                'date',
                DB::raw('SUM(total_earned) as earned'),
                DB::raw('SUM(total_spent) as spent'),
                DB::raw('SUM(total_expired) as expired'),
                DB::raw('SUM(transactions_count) as transactions')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $branches = Branch::orderBy('name')->get();

        return view('ecosystem::points.reports.daily-summary', compact(
            'summaries', 'totals', 'chartData', 'branches', 'dateFrom', 'dateTo'
        ));
    }

    // =====================================================
    // تصدير التقارير
    // =====================================================
    public function export(Request $request, $type)
    {
        // Return JSON download for different report types
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());

        $data = [];
        switch ($type) {
            case 'points':
                $data = PointsTransaction::with(['employee', 'client', 'branch'])
                    ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                    ->get()
                    ->map(fn($t) => [
                        'id' => $t->id,
                        'user' => $t->employee?->full_name ?? $t->client?->full_name ?? '-',
                        'user_type' => $t->user_type,
                        'action_type' => $t->action_type,
                        'points' => $t->points,
                        'balance_before' => $t->balance_before,
                        'balance_after' => $t->balance_after,
                        'status' => $t->status,
                        'branch' => $t->branch?->name,
                        'date' => $t->created_at->format('Y-m-d H:i'),
                    ]);
                break;

            case 'daily-summary':
                $data = PointsDailySummary::betweenDates($dateFrom, $dateTo)->orderByDesc('date')->get();
                break;

            default:
                return response()->json(['error' => 'Invalid report type'], 400);
        }

        $filename = "report_{$type}_" . date('Y-m-d_His') . '.json';
        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }
}
