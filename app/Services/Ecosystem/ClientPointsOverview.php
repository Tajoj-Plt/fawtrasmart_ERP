<?php

namespace App\Services\Ecosystem;

use App\Enums\BatchStatus;
use App\Enums\ReferralStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Ecosystem\Achievement;
use App\Models\Ecosystem\PointsBatch;
use App\Models\Ecosystem\PointsTier;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\Referral;
use App\Models\Ecosystem\ReferralSetting;
use App\Models\Ecosystem\Reward;
use App\Models\Ecosystem\RewardRedemption;
use App\Models\Ecosystem\UserAchievement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * صورةُ نقاط العميل ومكافآته — رصيدُه ومستواه وإنجازاتُه وإحالاتُه.
 *
 * ══════════ لماذا خدمةٌ لا متحكّم؟ ══════════
 *
 * كان هذا المنطقُ في `ClientPortalController::dashboard()` وحدَه — بوابةُ
 * نقاطٍ منفصلةٌ بعنوانٍ آخر ودخولٍ برقم الهاتف. وحين وُحِّدت البوابتان
 * (طلب المستخدم 2026-09-19: «أبغى بوابة واحدة») صار للنقاط قسمٌ في بوابة
 * العميل، فانتقل الحسابُ إلى هنا ليقرأه القسمُ الجديد — ولو بقي في المتحكّم
 * لنُسخ نسخةً ثانيةً تفترق عنه بعد أوّل تعديل.
 *
 * والحسابُ كما كان حرفاً بحرف: الرصيدُ = المكتسبُ المكتمل − المصروف،
 * والمستوى من `PointsTier` بالرصيد، لا صيغةَ جديدةً تُخترع هنا.
 */
class ClientPointsOverview
{
    /**
     * @return array<string, mixed> ما تحتاجه شاشة «نقاطي ومكافآتي»
     */
    public static function for(Client $client): array
    {
        /* كودُ الإحالة يُولَّد عند أوّل عرض — لكن بشرط وجود عموده.
           `clients.referral_code` **غير موجودٍ على قواعدَ قائمة** (انحرافُ
           المخطّط القديم)، وكانت البوابة القديمة تناديه بلا فحصٍ فتسقط
           الشاشةُ كلُّها بـ«Unknown column». فالإحالاتُ تُطفأ بهدوء ويبقى
           باقي القسم يعمل. */
        $referralReady = static::referralColumnsExist();

        if ($referralReady) {
            $client->generateReferralCode();
        }

        $allTiers = PointsTier::active()->forUserType('client')->ordered()->get();

        $totalEarned = (int) PointsTransaction::where('user_type', UserType::CLIENT)
            ->where('client_id', $client->id)
            ->where('points', '>', 0)
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('points');

        /* المصروفُ بلا شرط الحالة عمداً — كما كان: السحبُ يُخصم فورَ وقوعه،
           وانتظارُ اكتماله يُظهر للعميل رصيداً أكبر ممّا يملك. */
        $totalSpent = (int) abs(PointsTransaction::where('user_type', UserType::CLIENT)
            ->where('client_id', $client->id)
            ->where('points', '<', 0)
            ->sum('points'));

        $currentBalance = max(0, $totalEarned - $totalSpent);

        $currentTier = static::tierFromPoints($currentBalance, $allTiers);
        $nextTier = $currentTier ? $currentTier->getNextTier() : $allTiers->first();

        $pointsToNextTier = $currentTier
            ? $currentTier->getPointsToNextTier($currentBalance)
            : ($allTiers->first() ? $allTiers->first()->min_points - $currentBalance : null);

        $progressToNextTier = 0;

        if ($currentTier && $nextTier) {
            $span = max(1, $nextTier->min_points - $currentTier->min_points);
            $progressToNextTier = min(100, max(0, round((($currentBalance - $currentTier->min_points) / $span) * 100)));
        }

        $userAchievements = UserAchievement::where('user_type', UserType::CLIENT)
            ->where('client_id', $client->id)
            ->with(['achievement'])
            ->orderByDesc('achieved_at')
            ->get();

        $recentTransactions = PointsTransaction::where('user_type', UserType::CLIENT)
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        /* النقاطُ التي تنتهي خلال شهر — أهمُّ رقمٍ للعميل بعد رصيده:
           نقطةٌ تنتهي بلا تنبيهٍ شكوى مؤجَّلة. */
        $expiringBatches = PointsBatch::where('user_type', UserType::CLIENT)
            ->where('client_id', $client->id)
            ->where('status', BatchStatus::ACTIVE->value)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->orderBy('expires_at')
            ->get();

        $availableRewards = Reward::active()
            ->forUserType(UserType::CLIENT)
            ->available()
            ->orderBy('points_required')
            ->limit(6)
            ->get();

        $redemptions = RewardRedemption::where('client_id', $client->id)
            ->with(['reward'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $referrals = $referralReady
            ? Referral::where('referrer_id', $client->id)->with(['referred'])->orderByDesc('created_at')->get()
            : collect();

        return [
            'client' => $client,
            'allTiers' => $allTiers,
            'currentBalance' => $currentBalance,
            'totalEarned' => $totalEarned,
            'totalSpent' => $totalSpent,
            'currentTier' => $currentTier,
            'nextTier' => $nextTier,
            'pointsToNextTier' => $pointsToNextTier,
            'progressToNextTier' => $progressToNextTier,
            'userAchievements' => $userAchievements,
            'allAvailableAchievements' => Achievement::active()->forUserType(UserType::CLIENT)->visible()->get(),
            'recentTransactions' => $recentTransactions,
            'expiringBatches' => $expiringBatches,
            'expiringPointsTotal' => (int) $expiringBatches->sum('remaining_points'),
            'availableRewards' => $availableRewards,
            'redemptions' => $redemptions,
            'referrals' => $referrals,
            'referralStats' => [
                'total' => $referrals->count(),
                'confirmed' => $referrals->where('status', ReferralStatus::CONFIRMED)->count(),
                'pending' => $referrals->where('status', ReferralStatus::PENDING)->count(),
                'total_points_earned' => $referrals->where('status', ReferralStatus::CONFIRMED)->sum('referrer_points'),
            ],
            'referralReady' => $referralReady,
            'referralSettings' => $referralReady ? ReferralSetting::getActiveSettings($client->branch_id) : null,
            'monthlyPoints' => static::monthlySeries($client),
        ];
    }

    /**
     * أعمودا الإحالة موجودان في `clients`؟
     *
     * يُفحص مرّةً لكل طلب: `Schema::hasColumn` استعلامُ وصفٍ لا يُكرَّر لكل عميل.
     */
    public static function referralColumnsExist(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasColumn('clients', 'referral_code')
            && Schema::hasColumn('clients', 'referred_by');
    }

    /** المستوى الذي يقع فيه هذا الرصيد — أعلى مستوًى بلغ حدَّه الأدنى. */
    public static function tierFromPoints(int $points, $tiers): ?PointsTier
    {
        if ($tiers->isEmpty()) {
            return null;
        }

        foreach ($tiers->sortByDesc('min_points') as $tier) {
            if ($points >= $tier->min_points) {
                return $tier;
            }
        }

        return $tiers->sortBy('min_points')->first();
    }

    /**
     * ستّةُ أشهر: المكتسب والمصروف في كلٍّ منها.
     *
     * الشهرُ من عمودَي `points_month`/`points_year` لا من `created_at`:
     * النقاطُ قد تُسجَّل في شهرٍ وتُنسب إلى آخر (تسويةٌ متأخّرة)، والعميلُ
     * يقرؤها بنسبتها لا بتاريخ كتابتها.
     *
     * @return array<int, array{label:string, earned:int, spent:int}>
     */
    protected static function monthlySeries(Client $client): array
    {
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);

            $base = fn () => PointsTransaction::where('user_type', UserType::CLIENT)
                ->where('client_id', $client->id)
                ->where('points_month', $date->month)
                ->where('points_year', $date->year);

            $series[] = [
                'label' => $date->locale('ar')->translatedFormat('M'),
                'earned' => (int) $base()->where('points', '>', 0)
                    ->where('status', TransactionStatus::COMPLETED)->sum('points'),
                'spent' => (int) abs($base()->where('points', '<', 0)->sum('points')),
            ];
        }

        return $series;
    }
}
