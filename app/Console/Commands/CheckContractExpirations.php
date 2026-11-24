<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SendsExpiryReminders;
use App\Models\Contract;
use App\Models\notifications;
use App\Models\User;
use App\Support\PayrollSettings;
use Illuminate\Console\Command;

/**
 * تنبيهُ اقتراب انتهاء عقود الموظفين.
 *
 * ─────────────────── أربعُ عللٍ أُصلحت ───────────────────
 *
 * ١. **حقلان لا وجود لهما**: كانت الرسالةُ تُبنى بـ`$employee->last_name`
 *    و`$employee->employee_number`. و`employees` لا يحمل واحداً منهما —
 *    اللقبُ فيه `nickname`، ولا رقمَ للموظف كان أصلاً. فتخرج الرسالة
 *    «عقد الموظف سالم  (رقم: )» باسمٍ ناقصٍ ورقمٍ فارغ. صارت
 *    `full_name` و`employee_code`.
 *
 * ٢. **كسرٌ زائف في عدد الأيام**: `now()->diffInDays($end)` تُرجع
 *    `30.000000000069445` — فرقَ الساعات محسوباً — فتُطبع الرسالةُ
 *    «(بعد 30.000000000069 يوم)». صارت أياماً صحيحة من `startOfDay`.
 *
 * ٣. **مطابقةُ اليوم الواحد**: `whereDate('end_date', $targetDate)` تعني
 *    أنّ فوات `schedule:run` يوماً واحداً **يُسقط التنبيه عن ذلك العقد
 *    نهائياً** — اليومُ الموافق مضى ولن يعود. صار الشرطُ مدًى.
 *
 * ٤. **والمدى يقتضي كبحاً**: فبلا `last_alert_sent_at` يُنبَّه على العقد
 *    نفسِه كلَّ يومٍ ثلاثين مرّة. التذكيرُ أسبوعيّ — انظر
 *    [SendsExpiryReminders].
 *
 * ولم يكن يُنبَّه على عقدٍ **انتهى فعلاً**: تُتخطّاه المطابقةُ لأن يومَه
 * مضى. وهو أَولى بالتنبيه ممّا سينتهي.
 */
class CheckContractExpirations extends Command
{
    use SendsExpiryReminders;

    protected $signature = 'contracts:check-expirations
                            {--days= : عدد أيام الإنذار — يعلو على الإعدادات}
                            {--dry-run : عرضُ ما سيُرسَل بلا إرسال}';

    protected $description = 'فحص العقود القريبة من الانتهاء وإرسال إشعارات';

    private const DEFAULT_NOTICE_DAYS = 30;

    public function handle(): int
    {
        $days = $this->resolveNoticeDays();
        $dryRun = (bool) $this->option('dry-run');

        $this->info("البحث عن عقود تنتهي خلال {$days} يوماً أو انتهت فعلاً…");

        $contracts = Contract::with('employee')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->startOfDay()->addDays($days))
            ->orderBy('end_date')
            ->get()
            ->filter(fn (Contract $contract) => $this->isReminderDue($contract));

        if ($contracts->isEmpty()) {
            $this->info('لا عقود تستحقّ التنبيه اليوم.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($contracts as $contract) {
            $employee = $contract->employee;

            // عقدٌ بلا صاحبٍ لا يُنبَّه عليه أحد
            if (! $employee) {
                continue;
            }

            $remaining = $this->wholeDaysUntil($contract->end_date);
            $line = $employee->full_name.' — '.$this->describeRemaining($remaining);

            if ($dryRun) {
                $this->line('  ▸ '.$line);
                $sent++;

                continue;
            }

            $this->notify($contract, $remaining);
            $sent++;
            $this->line('  ✓ '.$line);
        }

        $this->info($dryRun
            ? "وضع المعاينة — {$sent} تنبيهاً كانت سترسل."
            : "تم إرسال {$sent} تنبيهاً.");

        return self::SUCCESS;
    }

    /** مدّةُ الإنذار: الخيار، ثمّ الإعدادات، ثمّ الافتراضي. */
    private function resolveNoticeDays(): int
    {
        $option = $this->option('days');

        if (is_numeric($option) && (int) $option > 0) {
            return (int) $option;
        }

        try {
            $configured = PayrollSettings::value('contract_end_notice_period');
        } catch (\Throwable) {
            // إعداداتٌ غير مهيّأة يجب ألّا تُعطّل التنبيه
            $configured = null;
        }

        return $configured && (int) $configured > 0
            ? (int) $configured
            : self::DEFAULT_NOTICE_DAYS;
    }

    private function notify(Contract $contract, ?int $remaining): void
    {
        $employee = $contract->employee;
        $endDate = optional($contract->end_date)->format('Y-m-d')
            ?: (string) $contract->end_date;
        $state = $this->describeRemaining($remaining);
        $code = $employee->employee_code ? ' (رقم '.$employee->employee_code.')' : '';

        $employeeUser = User::where('employee_id', $employee->id)->first();

        if ($employeeUser) {
            notifications::create([
                'user_id' => null,
                'receiver_id' => $employeeUser->id,
                'title' => 'تنبيه: اقتراب انتهاء عقد العمل',
                'description' => 'عقدك ينتهي في '.$endDate.' ('.$state.'). يرجى مراجعة قسم الموارد البشرية.',
                'type' => 'contract_expiration',
                'read' => 0,
            ]);
        }

        notifications::create([
            'user_id' => null,
            'receiver_id' => null,
            'title' => 'تنبيه: انتهاء عقد موظف قريباً',
            'description' => 'عقد الموظف '.$employee->full_name.$code
                .' ينتهي في '.$endDate.' ('.$state.').',
            'type' => 'contract_expiration_admin',
            'read' => 0,
        ]);

        $this->stampReminder($contract);
    }
}
