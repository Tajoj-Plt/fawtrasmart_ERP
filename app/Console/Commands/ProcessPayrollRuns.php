<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Log as ModelsLog;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Services\PayrollDisbursementService;
use App\Support\CurrencySettings;
use App\Support\PayrollSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * كرون يومي لمعالجة قسائم الرواتب:
 *
 *   1) لكل slip بحالة approved/active:
 *      - تحقق من نشاط الموظف والعقد
 *      - أنشئ payroll_run جديد للفترة التالية (إن لم يكن موجوداً)
 *
 *   2) لكل payroll_run بحالة pending وحان موعده:
 *      - نفّذ الصرف عبر PayrollDisbursementService
 *
 * يجب جدولته يومياً في bootstrap/app.php أو Kernel.
 */
class ProcessPayrollRuns extends Command
{
    protected $signature = 'payroll:process
                            {--dry-run : عرض ما سيتم تنفيذه دون حفظ}
                            {--slip= : قسيمة معينة فقط}';

    protected $description = 'توليد ومعالجة عمليات صرف الرواتب التلقائية';

    public function handle(PayrollDisbursementService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $slipId = $this->option('slip');

        $this->info('🚀 بدء معالجة الرواتب - ' . now()->format('Y-m-d H:i:s'));

        // ===== قراءة الإعدادات =====
        $settings = PayrollSettings::row();
        $autoGenerate = $settings ? (bool) ($settings->auto_generate_payroll_runs ?? true) : true;
        $autoDisburse = $settings ? (bool) ($settings->auto_disburse_payroll_runs ?? true) : true;

        if (!$autoGenerate && !$autoDisburse) {
            $this->warn('⏸️  التوليد التلقائي والصرف التلقائي معطّلان من الإعدادات — لا شيء للتنفيذ.');
            Log::info('ProcessPayrollRuns: skipped (both toggles disabled)');
            return self::SUCCESS;
        }

        // قسائم وُسِمت منتهية وبقي فيها صرف معلَّق — أعدها نشطة قبل التوليد/الصرف
        if (! $dryRun) {
            SalarySlip::query()
                ->where('status', SalarySlip::STATUS_EXPIRED)
                ->whereHas('payrollRuns', fn ($q) => $q->where('status', PayrollRun::STATUS_PENDING))
                ->each(fn (SalarySlip $slip) => $slip->reviveWhileUndisbursed());
        }

        // ===== Phase 1: توليد runs جديدة =====
        if (!$autoGenerate) {
            $this->warn('⏸️  Phase 1 (التوليد التلقائي) معطّل من الإعدادات — تخطّي.');
        }
        $slips = !$autoGenerate ? collect() : SalarySlip::query()
            ->whereIn('status', [SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE])
            ->when($slipId, fn($q) => $q->where('id', $slipId))
            ->with('employee')
            ->get();

        $generated = 0;
        $expired   = 0;

        foreach ($slips as $slip) {
            // قسيمة وُسِمت منتهية وبقي فيها صرف معلَّق — أعدها نشطة
            if (! $dryRun) {
                $slip->reviveWhileUndisbursed();
            }

            $expireReason = $slip->shouldExpire();
            if ($expireReason) {
                if (! $dryRun) {
                    $slip->update(['status' => SalarySlip::STATUS_EXPIRED, 'stop_reason' => $expireReason]);
                }
                $this->warn("  ⛔ slip #{$slip->id}: {$expireReason} → expired");
                $expired++;
                continue;
            }

            // انتهت الفترة لكن بقي صرف معلَّق: لا توليد جديد، ولا إنهاء
            if ($slip->end_date && now()->gt($slip->end_date)) {
                $this->line("  ⏳ slip #{$slip->id}: انتهت الفترة وبقي صرف معلَّق — تبقى نشطة حتى اكتمال الصرف");
                continue;
            }

            // توليد run جديد (إن لزم)
            $period = $slip->generateNextPeriod();
            if (!$period) continue;

            $exists = PayrollRun::where('salary_slip_id', $slip->id)
                ->where('period_from', $period['period_from']->toDateString())
                ->where('period_to', $period['period_to']->toDateString())
                ->exists();
            if ($exists) continue;

            // التحقق من أن موعد الصرف قريب (خلال 14 يوماً أو أقل)
            $daysUntilDue = now()->diffInDays($period['scheduled_date'], false);
            if ($daysUntilDue > 14) continue;

            if ($dryRun) {
                $this->line("  🔍 [dry-run] sera créé run for slip #{$slip->id} period " .
                    $period['period_from']->toDateString() . ' → ' . $period['period_to']->toDateString() .
                    ' (scheduled: ' . $period['scheduled_date']->toDateString() . ')');
                $generated++;
                continue;
            }

            try {
                $run = $service->generateNextRun($slip);
                if ($run) {
                    $this->info("  ✓ slip #{$slip->id}: تم توليد run #{$run->id}");
                    $generated++;
                }
            } catch (\Throwable $e) {
                $this->error("  ❌ slip #{$slip->id}: " . $e->getMessage());
                Log::error('ProcessPayrollRuns generate failed', ['slip_id' => $slip->id, 'error' => $e->getMessage()]);
            }
        }

        // ===== Phase 2: صرف runs المستحقة =====
        $this->info('');
        if (!$autoDisburse) {
            $this->warn('⏸️  Phase 2 (الصرف التلقائي) معطّل من الإعدادات — تخطّي.');
            $this->info("✅ انتهى: تم توليد {$generated} run، {$expired} قسيمة منتهية (الصرف التلقائي معطّل)");
            return self::SUCCESS;
        }
        $this->info('🔁 صرف القسائم المستحقة...');

        $dueRuns = PayrollRun::due()
            ->when($slipId, fn($q) => $q->where('salary_slip_id', $slipId))
            ->with('employee')
            ->get();

        $disbursed = 0;
        $netTotal = 0.0;
        $awaitingApproval = 0;

        foreach ($dueRuns as $run) {
            if (!$run->employee || !$run->employee->isActive()) {
                $this->warn("  ⛔ run #{$run->id}: الموظف غير نشط — لن يُصرف");
                continue;
            }

            /*
                مسيرُ الرواتب لم يُعتمد: تُتخطّى وتبقى معلَّقةً حتى يُعتمد.
                والفحصُ هنا لا في `catch`: الانتظارُ حالةٌ متوقَّعةٌ لا عطل،
                فلا يجوز أن يملأ سجلَّ الأخطاء كلَّ يومٍ حتى يُعتمد المسير.
            */
            if ($approvalBlock = $run->disbursementApprovalBlockReason()) {
                $this->warn("  ⏳ run #{$run->id}: {$approvalBlock}");
                $awaitingApproval++;
                continue;
            }

            if ($dryRun) {
                $this->line("  🔍 [dry-run] sera صرف run #{$run->id} للموظف {$run->employee->first_name}");
                $disbursed++;
                continue;
            }

            try {
                $run = $service->disburse($run, PayrollRun::TRIGGER_CRON);
                $this->recordActivity($run);
                $this->info("  ✓ run #{$run->id}: صُرف بصافي " . number_format($run->net_salary, 2) . ' ر.س');
                $disbursed++;
                $netTotal += (float) $run->net_salary;
            } catch (\Throwable $e) {
                $this->error("  ❌ run #{$run->id}: " . $e->getMessage());
                Log::error('ProcessPayrollRuns disburse failed', ['run_id' => $run->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info('');
        $this->info("✅ انتهى: تم توليد {$generated} run، صرف {$disbursed} (إجمالي: " . number_format($netTotal, 2) . " ر.س)، {$expired} قسيمة منتهية");

        if ($awaitingApproval > 0) {
            $this->warn("⏳ {$awaitingApproval} صرفة مستحقة تنتظر اعتماد مسير الرواتب — اعتمِد مسيراتها من «مسيرات الرواتب» لتُصرف.");
            Log::info('ProcessPayrollRuns: runs awaiting disbursement approval', ['count' => $awaitingApproval]);
        }

        if ($dryRun) {
            $this->warn('⚠️  وضع dry-run — لم يتم حفظ أي تغييرات');
        }

        return self::SUCCESS;
    }

    /**
     * أثرُ الصرف التلقائي في «سجل النشاطات».
     *
     * الصرفُ اليدوي في `PayrollRunController::disburse` يكتب صفَّ
     * `payroll_run_log`، وكان هذا المسارُ — وهو الذي يصرف رواتبَ المنشأة
     * كلَّها في يوم الصرف — لا يكتب شيئاً. فالأثرُ الوحيد
     * `trigger_source = 'cron'` على الصرفة نفسها، و`Log::error` في ملفّ
     * اللوق عند الفشل وحده. أمّا `ActivityLogController` فيقرأ
     * `type_log = 'log'` من جدول `logs`، فلم تكن الصرفاتُ التلقائية تظهر
     * في الشاشة التي يُراجع فيها المحاسبُ من صرف وماذا صُرف.
     *
     * و`created_by` من `disbursed_by` الذي كتبته الخدمة
     * (`Auth::id() ?: User::min('id')`): لا جلسةَ في الكرون، فيُنسَب الأثرُ
     * إلى ما نُسب إليه الصرفُ نفسه لا إلى فراغ.
     *
     * والإخفاق لا يُسقط الصرف: الراتب صُرف وقُيّد وأُثبت في `payroll_runs`،
     * فلا يجوز أن يُرمى ذلك كلُّه لتعذّر كتابة سطرِ سجلّ.
     */
    private function recordActivity(PayrollRun $run): void
    {
        try {
            ModelsLog::create([
                'type' => 'payroll_run_log',
                'type_id' => $run->id,
                'type_log' => 'log',
                'description' => sprintf(
                    'تم صرف راتب تلقائياً (الصرف المجدول) للفترة %s → %s بصافي %s',
                    $run->period_from->toDateString(),
                    $run->period_to->toDateString(),
                    CurrencySettings::format($run->net_salary)
                ),
                'created_by' => $run->disbursed_by,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProcessPayrollRuns: activity log write failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
