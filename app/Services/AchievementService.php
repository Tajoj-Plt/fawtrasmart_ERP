<?php

namespace App\Services;

use App\Enums\ConditionPeriod;
use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\Ecosystem\Achievement;
use App\Models\Ecosystem\AchievementSummary;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\UserAchievement;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    /**
     * فحص ومنح الإنجازات للمستخدم بعد كل معاملة نقاط مكتملة
     * يتم استدعاء هذا الميثود من PointsTransactionController بعد حفظ المعاملة
     */
    public function checkAchievements(int $userId, UserType $userType): array
    {
        $awardedAchievements = [];

        // جلب الإنجازات النشطة لنوع المستخدم
        $achievements = Achievement::where('is_active', true)
            ->where('user_type', $userType->value)
            ->get();

        foreach ($achievements as $achievement) {
            try {
                // تجاهل إذا كان الإنجاز لمرة واحدة وتم تحقيقه مسبقاً
                if ($achievement->is_one_time && $achievement->hasBeenAchievedBy($userId, $userType)) {
                    continue;
                }

                // حساب القيمة الفعلية للمستخدم بناءً على نوع الشرط والفترة الزمنية
                $currentValue = $this->calculateConditionValue(
                    $achievement->condition_type,
                    $userId,
                    $userType,
                    $achievement->condition_period
                );

                // فحص هل تحقق الشرط
                if ($achievement->checkCondition($currentValue)) {
                    // منح الإنجاز
                    $userAchievement = $this->awardAchievement($achievement, $userId, $userType, $currentValue);

                    if ($userAchievement) {
                        $awardedAchievements[] = [
                            'achievement' => $achievement,
                            'user_achievement' => $userAchievement,
                            'value' => $currentValue,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Achievement check failed', [
                    'achievement_id' => $achievement->id,
                    'user_id' => $userId,
                    'user_type' => $userType->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $awardedAchievements;
    }

    /**
     * حساب القيمة الفعلية للشرط بناءً على نوعه والفترة الزمنية
     * 
     * التفريق بين الموظف والعميل:
     * - الموظف: employee_id + action_types خاصة بالموظفين
     * - العميل: client_id + action_types خاصة بالعملاء
     */
    public function calculateConditionValue(
        string $conditionType,
        int $userId,
        UserType $userType,
        ConditionPeriod $period
    ): int {
        $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';

        // بناء الاستعلام الأساسي مع فلترة الفترة الزمنية
        $baseQuery = PointsTransaction::where('user_type', $userType)
            ->where($idColumn, $userId)
            ->where('status', TransactionStatus::COMPLETED);

        // تطبيق فلتر الفترة الزمنية
        $baseQuery = $this->applyPeriodFilter($baseQuery, $period);

        return match ($conditionType) {
            // ─── شروط مشتركة (موظف + عميل) ───
            'invoices_count' => (clone $baseQuery)
                ->whereIn('action_type', $userType === UserType::CLIENT 
                    ? ['invoice_created', 'first_purchase'] 
                    : ['invoice_created'])
                ->count(),

            'total_spent' => (clone $baseQuery)
                ->whereIn('action_type', $userType === UserType::CLIENT 
                    ? ['invoice_created', 'invoice_paid', 'order_completed', 'payment_received', 'first_purchase']
                    : ['invoice_created', 'cash_sale'])
                ->where('points', '>', 0)
                ->sum('points'),

            'total_collected' => (clone $baseQuery)
                ->whereIn('action_type', ['payment_collected', 'cash_sale', 'invoice_created', 'overdue_collection', 'receipt_created'])
                ->where('points', '>', 0)
                ->sum('points'),

            // ─── شروط خاصة بالعملاء ───
            'referrals_count' => $this->countReferrals($userId, $userType, $period),

            'reviews_count' => (clone $baseQuery)
                ->where('action_type', 'review_submitted')
                ->count(),

            'orders_count' => (clone $baseQuery)
                ->where('action_type', 'order_completed')
                ->count(),

            // ─── شروط خاصة بالموظفين ───
            'new_clients_count' => (clone $baseQuery)
                ->where('action_type', 'new_client_acquired')
                ->count(),

            'days_on_time' => $this->calculateDaysOnTime($userId, $period),

            'collaborations_count' => (clone $baseQuery)
                ->where('action_type', 'collaboration')
                ->count(),

            'tasks_completed' => (clone $baseQuery)
                ->where('action_type', 'task_completed')
                ->count(),

            'sales_count' => (clone $baseQuery)
                ->whereIn('action_type', ['invoice_created', 'cash_sale'])
                ->count(),

            'visits_count' => (clone $baseQuery)
                ->where('action_type', 'visit_completed')
                ->count(),

            // ─── شروط جديدة ───
            'overdue_collections_count' => (clone $baseQuery)
                ->where('action_type', 'overdue_collection')
                ->count(),

            'early_completions_count' => (clone $baseQuery)
                ->where('action_type', 'early_completion')
                ->count(),

            'trainings_completed' => (clone $baseQuery)
                ->where('action_type', 'training_completed')
                ->count(),

            'customer_satisfaction_count' => (clone $baseQuery)
                ->where('action_type', 'customer_satisfaction')
                ->count(),

            // ─── افتراضي: عد المعاملات من هذا النوع ───
            default => (clone $baseQuery)
                ->where('action_type', $conditionType)
                ->count(),
        };
    }

    /**
     * منح الإنجاز للمستخدم وتحديث الملخص
     */
    private function awardAchievement(
        Achievement $achievement,
        int $userId,
        UserType $userType,
        int $achievedValue
    ): ?UserAchievement {
        $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';

        // للإنجازات المتكررة: تحقق من عدم وجود إنجاز في نفس الفترة
        if (!$achievement->is_one_time) {
            $existsInPeriod = $this->hasAchievementInCurrentPeriod(
                $achievement,
                $userId,
                $userType
            );

            if ($existsInPeriod) {
                return null;
            }
        }

        try {
            // إنشاء سجل الإنجاز
            $userAchievement = UserAchievement::create([
                'achievement_id' => $achievement->id,
                'user_type' => $userType->value,
                'employee_id' => $userType === UserType::EMPLOYEE ? $userId : null,
                'client_id' => $userType === UserType::CLIENT ? $userId : null,
                'achieved_at' => now(),
                'achievement_value' => $achievedValue,
                'points_awarded' => $achievement->bonus_points,
            ]);

            // تحديث جدول الملخصات
            AchievementSummary::updateForAchievement(
                $achievement->id,
                $achievement->bonus_points
            );

            Log::info('Achievement awarded', [
                'achievement_id' => $achievement->id,
                'achievement_name' => $achievement->name,
                'user_type' => $userType->value,
                'user_id' => $userId,
                'points_awarded' => $achievement->bonus_points,
                'achieved_value' => $achievedValue,
            ]);

            return $userAchievement;
        } catch (\Exception $e) {
            Log::error('Failed to award achievement', [
                'achievement_id' => $achievement->id,
                'user_id' => $userId,
                'user_type' => $userType->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * تطبيق فلتر الفترة الزمنية على الاستعلام
     */
    private function applyPeriodFilter($query, ConditionPeriod $period)
    {
        return match ($period) {
            ConditionPeriod::DAILY => $query->whereDate('created_at', today()),
            ConditionPeriod::WEEKLY => $query->where('created_at', '>=', now()->startOfWeek()),
            ConditionPeriod::MONTHLY => $query->where('created_at', '>=', now()->startOfMonth()),
            ConditionPeriod::YEARLY => $query->where('created_at', '>=', now()->startOfYear()),
            ConditionPeriod::ALL_TIME => $query, // بدون فلتر
        };
    }

    /**
     * عد إحالات العميل
     */
    private function countReferrals(int $userId, UserType $userType, ConditionPeriod $period): int
    {
        // الإحالات خاصة بالعملاء فقط
        if ($userType !== UserType::CLIENT) {
            return 0;
        }

        $query = \App\Models\Ecosystem\Referral::where('referrer_id', $userId)
            ->where('status', 'confirmed');

        return match ($period) {
            ConditionPeriod::DAILY => $query->whereDate('confirmed_at', today())->count(),
            ConditionPeriod::WEEKLY => $query->where('confirmed_at', '>=', now()->startOfWeek())->count(),
            ConditionPeriod::MONTHLY => $query->where('confirmed_at', '>=', now()->startOfMonth())->count(),
            ConditionPeriod::YEARLY => $query->where('confirmed_at', '>=', now()->startOfYear())->count(),
            ConditionPeriod::ALL_TIME => $query->count(),
        };
    }

    /**
     * حساب عدد أيام الحضور في الموعد (للموظفين)
     */
    private function calculateDaysOnTime(int $employeeId, ConditionPeriod $period): int
    {
        $onTimeQuery = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employeeId)
            ->where('action_type', 'attendance_on_time')
            ->where('status', TransactionStatus::COMPLETED);

        $onTimeQuery = $this->applyPeriodFilter($onTimeQuery, $period);

        $lateQuery = PointsTransaction::where('user_type', UserType::EMPLOYEE)
            ->where('employee_id', $employeeId)
            ->where('action_type', 'late_arrival')
            ->where('status', TransactionStatus::COMPLETED);

        $lateQuery = $this->applyPeriodFilter($lateQuery, $period);

        return max(0, $onTimeQuery->count() - $lateQuery->count());
    }

    /**
     * التحقق من وجود إنجاز في الفترة الحالية (للإنجازات المتكررة)
     */
    private function hasAchievementInCurrentPeriod(
        Achievement $achievement,
        int $userId,
        UserType $userType
    ): bool {
        $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';

        $query = UserAchievement::where('achievement_id', $achievement->id)
            ->where('user_type', $userType->value)
            ->where($idColumn, $userId);

        return match ($achievement->condition_period) {
            ConditionPeriod::DAILY => $query->whereDate('achieved_at', today())->exists(),
            ConditionPeriod::WEEKLY => $query->where('achieved_at', '>=', now()->startOfWeek())->exists(),
            ConditionPeriod::MONTHLY => $query->where('achieved_at', '>=', now()->startOfMonth())->exists(),
            ConditionPeriod::YEARLY => $query->where('achieved_at', '>=', now()->startOfYear())->exists(),
            ConditionPeriod::ALL_TIME => false, // لا يتكرر في all_time
        };
    }
}
