<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\PointsSetting;
use App\Services\PointsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * أمر معالجة أفضل موظف شهرياً
 * 
 * يتم تشغيله في آخر يوم من كل شهر لمنح نقاط المكافأة
 * للموظف الذي حصل على أكبر عدد من النقاط في الشهر الحالي
 */
class ProcessMonthlyTopPerformer extends Command
{
    /**
     * اسم الأمر والتوقيع
     */
    protected $signature = 'points:monthly-top-performer 
                            {--month= : الشهر المراد معالجته (1-12)، الافتراضي الشهر الحالي}
                            {--year= : السنة المراد معالجتها، الافتراضي السنة الحالية}
                            {--dry-run : معاينة بدون تنفيذ فعلي}';

    /**
     * وصف الأمر
     */
    protected $description = 'منح نقاط أفضل موظف شهرياً للموظف صاحب أعلى نقاط مكتسبة';

    /**
     * تنفيذ الأمر
     */
    public function handle(PointsService $pointsService): int
    {
        // تحديد الشهر والسنة (الشهر الحالي لأن الأمر يُنفذ في آخر يوم من الشهر)
        $now = Carbon::now();
        $targetMonth = $this->option('month') ?: $now->month;
        $targetYear = $this->option('year') ?: $now->year;
        $isDryRun = $this->option('dry-run');

        $this->info("🏆 معالجة أفضل موظف لشهر {$targetMonth}/{$targetYear}...");

        // التحقق من وجود إعداد نقاط للإجراء
        $setting = PointsSetting::where('action_type', 'monthly_top_performer')
            ->where('user_type', UserType::EMPLOYEE->value)
            ->where('is_active', true)
            ->first();

        if (!$setting) {
            $this->error('❌ لا يوجد إعداد نقاط نشط لـ monthly_top_performer');
            Log::warning('ProcessMonthlyTopPerformer: لا يوجد إعداد نقاط نشط');
            return self::FAILURE;
        }

        $this->info("📊 إعداد النقاط: {$setting->points} نقطة");

        // التحقق من عدم منح المكافأة مسبقاً لهذا الشهر
        $existingReward = PointsTransaction::where('action_type', 'monthly_top_performer')
            ->where('user_type', UserType::EMPLOYEE->value)
            ->where('points_month', $targetMonth)
            ->where('points_year', $targetYear)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->exists();

        if ($existingReward) {
            $this->warn("⚠️ تم منح مكافأة أفضل موظف لهذا الشهر مسبقاً");
            return self::SUCCESS;
        }

        // حساب إجمالي النقاط المكتسبة لكل موظف في الشهر المحدد
        $topPerformer = PointsTransaction::where('user_type', UserType::EMPLOYEE->value)
            ->whereNotNull('employee_id')
            ->where('points_month', $targetMonth)
            ->where('points_year', $targetYear)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->where('points', '>', 0) // فقط النقاط المكتسبة (الموجبة)
            ->whereNotIn('action_type', ['monthly_top_performer', 'achievement_bonus', 'challenge_reward']) // استثناء المكافآت
            ->selectRaw('employee_id, SUM(points) as total_points')
            ->groupBy('employee_id')
            ->orderByDesc('total_points')
            ->first();

        if (!$topPerformer) {
            $this->warn("⚠️ لا يوجد موظفين لديهم نقاط في هذا الشهر");
            return self::SUCCESS;
        }

        $employeeId = $topPerformer->employee_id;
        $totalPoints = $topPerformer->total_points;

        // جلب بيانات الموظف
        $employee = \App\Models\Employee::find($employeeId);
        if (!$employee) {
            $this->error("❌ الموظف غير موجود: {$employeeId}");
            return self::FAILURE;
        }

        $employeeName = $employee->full_name ?? $employee->first_name . ' ' . $employee->last_name;

        $this->info("🥇 أفضل موظف: {$employeeName}");
        $this->info("   📈 إجمالي النقاط المكتسبة: {$totalPoints} نقطة");
        $this->info("   🎁 نقاط المكافأة: {$setting->points} نقطة");

        if ($isDryRun) {
            $this->warn("⏸️ وضع المعاينة - لم يتم تنفيذ أي تغييرات");
            return self::SUCCESS;
        }

        // منح نقاط المكافأة
        try {
            $result = $pointsService->awardEmployee(
                $employeeId,
                'monthly_top_performer',
                [
                    'description' => "أفضل موظف لشهر {$targetMonth}/{$targetYear} - إجمالي النقاط: {$totalPoints}",
                    'metadata' => [
                        'target_month' => $targetMonth,
                        'target_year' => $targetYear,
                        'total_earned_points' => $totalPoints,
                        'awarded_automatically' => true,
                    ],
                ]
            );

            if ($result->success) {
                $this->info("✅ تم منح {$result->pointsAwarded} نقطة للموظف {$employeeName}");
                
                Log::info('ProcessMonthlyTopPerformer: تم منح المكافأة بنجاح', [
                    'employee_id' => $employeeId,
                    'employee_name' => $employeeName,
                    'month' => $targetMonth,
                    'year' => $targetYear,
                    'total_earned' => $totalPoints,
                    'points_awarded' => $result->pointsAwarded,
                ]);

                return self::SUCCESS;
            } else {
                $this->error("❌ فشل في منح النقاط: {$result->message}");
                Log::error('ProcessMonthlyTopPerformer: فشل في منح النقاط', [
                    'employee_id' => $employeeId,
                    'error' => $result->message,
                ]);
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ خطأ: {$e->getMessage()}");
            Log::error('ProcessMonthlyTopPerformer: استثناء', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
