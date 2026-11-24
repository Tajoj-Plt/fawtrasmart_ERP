<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SalesTarget;
use App\Models\SalesTargetSettlement;
use App\Models\SalesTargetAchievement;
use App\Models\TargetSetting;
use App\Services\TargetCalculationService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CalculateWeeklyCommissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:calculate-weekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'حساب العمولات الأسبوعية تلقائياً كل خميس';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('بدء حساب العمولات الأسبوعية...');

        // حساب الأسبوع الماضي الكامل: من السبت الماضي إلى الجمعة الماضية
        $today = Carbon::today();
        
        // نجد الجمعة الماضية أولاً
        if ($today->isFriday()) {
            // إذا اليوم جمعة، نأخذ الجمعة قبل أسبوع
            $lastFriday = $today->copy()->subWeek();
        } else {
            // أي يوم آخر: نجد آخر جمعة مرت
            $lastFriday = $today->copy()->previous(Carbon::FRIDAY);
        }
        
        // السبت = اليوم التالي بعد الجمعة - 6 أيام = السبت في بداية نفس الأسبوع
        $fromDate = $lastFriday->copy()->subDays(6)->startOfDay(); // السبت
        $toDate = $lastFriday->copy()->endOfDay(); // الجمعة

        $this->info("الفترة: من {$fromDate->format('Y-m-d')} إلى {$toDate->format('Y-m-d')}");

        /* «تفعيل أهداف المبيعات» في إعدادات الأهداف — التسويةُ اليدويّة
           والمجدولة تقفان عنده، وهذا الحسابُ كان يمضي. */
        $setting = TargetSetting::for(TargetSetting::TYPE_SALES);
        if (! $setting->enabled) {
            $this->warn('أهداف المبيعات موقوفة في إعدادات الأهداف — لا حساب');

            return 0;
        }
        $method = $setting->calculation_method ?: 'net_sales';
        $calculator = app(TargetCalculationService::class);

        // جلب الأهداف الأسبوعية النشطة التي لها حساب تلقائي
        $weeklyTargets = SalesTarget::where('commission_period', 'weekly')
            ->where('status', 'active')
            ->where('auto_calculate_weekly', true) // فقط الأهداف التي حساب تلقائي
            ->with(['achievements.employee', 'commissionTiers'])
            ->get();

        if ($weeklyTargets->isEmpty()) {
            $this->warn('لا توجد أهداف أسبوعية نشطة');
            return 0;
        }

        $this->info("عدد الأهداف النشطة: {$weeklyTargets->count()}");

        $processedCount = 0;
        $totalCommissions = 0;

        foreach ($weeklyTargets as $target) {
            $this->info("\nمعالجة الهدف: {$target->name}");

            foreach ($target->achievements as $achievement) {
                $employee = $achievement->employee;
                
                if (!$employee) {
                    $this->warn("  - موظف غير موجود (ID: {$achievement->employee_id})");
                    continue;
                }

                if (! $employee->employee_id) {
                    $this->warn("  - المستخدم {$employee->name} ليس مرتبطاً بسجل موظف");
                    continue;
                }

                /* ⛔ حارسُ الازدواج: تسويةٌ **متداخلة** مع الأسبوع لا مطابقةٌ له
                   فحسب. كان الفحصُ على التاريخين حرفياً، فتسويةُ شهرٍ كامل من
                   شاشة الأهداف (1–30) لا تمنع حسابَ أسبوعٍ داخله — فتُحسب عمولةُ
                   الأيام نفسِها مرّتين وتُصرف مرّتين. وهو الفحصُ نفسُه الذي
                   يجريه `TargetSettlementService`. */
                $existingSettlement = SalesTargetSettlement::where('sales_target_id', $target->id)
                    ->where('employee_id', $employee->employee_id)
                    ->whereDate('from_date', '<=', $toDate)
                    ->whereDate('to_date', '>=', $fromDate)
                    ->exists();

                if ($existingSettlement) {
                    $this->warn("  - عمولة {$employee->name} محسوبة عن فترةٍ تتداخل مع هذا الأسبوع");
                    continue;
                }

                /* الإنجازُ بطريقة الاحتساب المضبوطة في «إعدادات الأهداف» — كما
                   يحسبه تقريرُ الهدف وتسويتُه. كان هنا تحصيلاتُ خزينة الموظف،
                   فيُعطى الهدفُ الواحد رقمَي إنجازٍ وعمولتين مختلفتين. */
                $achievedAmount = (float) $calculator->getEmployeeNetSales(
                    $employee->id,
                    $fromDate->toDateString(),
                    $toDate->toDateString(),
                    $method
                );

                // حساب العمولة — أعلى شريحةٍ بلغها الإنجاز، من المحقق
                $commissionAmount = round((float) $target->calculateCommission($achievedAmount, $achievement->target_amount), 2);

                if ($commissionAmount > 0) {
                    // إنشاء سجل التسوية
                    $settlement = SalesTargetSettlement::create([
                        'sales_target_id' => $target->id,
                        'employee_id' => $employee->employee_id, // employee_id من جدول employees
                        'commission_amount' => $commissionAmount,
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'notes' => 'حساب تلقائي للعمولة الأسبوعية',
                        'settled_by' => 1, // النظام (System User ID)
                        'is_settled' => false, // لم يتم الصرف بعد
                        'paid_directly' => false,
                    ]);

                    // إرسال إشعار للموظف
                    $this->sendNotification($employee, $target, $commissionAmount, $settlement);

                    $processedCount++;
                    $totalCommissions += $commissionAmount;

                    $this->info("  ✓ {$employee->name}: {$commissionAmount} ر.س (المحقق: {$achievedAmount})");
                } else {
                    $this->comment("  - {$employee->name}: لم يحقق الحد الأدنى (المحقق: {$achievedAmount})");
                }
            }
        }

        $this->info("\n" . str_repeat('=', 50));
        $this->info("تم حساب {$processedCount} عمولة");
        $this->info("إجمالي العمولات: " . number_format($totalCommissions, 2) . " ر.س");
        $this->info(str_repeat('=', 50));

        return 0;
    }

    /**
     * حساب التحصيلات للموظف في فترة محددة
     */
    private function getEmployeeCollections($userId, $treasuryId, $fromDate, $toDate)
    {
        if (!$treasuryId) {
            return 0;
        }

        // حساب التحصيلات من جدول journal_entry_details (المدين في الخزينة)
        $collections = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_details.account_id', $treasuryId)
            ->where('journal_entry_details.debit', '>', 0)
            ->whereBetween('journal_entries.date', [$fromDate, $toDate])
            ->where('journal_entries.created_by_employee', $userId)
            ->sum('journal_entry_details.debit');

        return $collections ?? 0;
    }

    /**
     * إرسال إشعار للموظف
     */
    private function sendNotification($employee, $target, $amount, $settlement)
    {
        try {
            // إنشاء إشعار في جدول notifications (بنية النظام الحالي)
            DB::table('notifications')->insert([
                'title' => 'عمولة أسبوعية جديدة',
                'description' => "تم حساب عمولتك الأسبوعية عن الهدف: {$target->name}\nالمبلغ: " . number_format($amount, 2) . " ر.س",
                'read' => 0,
                'user_id' => $employee->id,
                'receiver_id' => $employee->id,
                'type' => 'weekly_commission',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->comment("    → تم إرسال إشعار للموظف");
        } catch (\Exception $e) {
            $this->error("    ✗ فشل إرسال الإشعار: {$e->getMessage()}");
        }
    }
}
