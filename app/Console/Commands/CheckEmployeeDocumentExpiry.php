<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SendsExpiryReminders;
use App\Models\EmployeeDocument;
use App\Models\notifications;
use App\Models\User;
use App\Support\PayrollSettings;
use Illuminate\Console\Command;

/**
 * تنبيهُ انتهاء وثائق الموظفين.
 *
 * **مهمّةٌ كانت معلنةً ولا وجود لها**: مسجَّلةٌ في `CronRegistry` ومبذورةٌ
 * في `cron_jobs`، لكنّ أمرَها لم يُكتب — فبقي صفُّها معطَّلاً موسوماً
 * «(غير مطبَّقة بعد)»، وهو ما تفعله `CronJobsSeeder` بكلّ معالِجٍ لا
 * يجتاز `CronRegistry::isAvailable()`. ولم يكن يُكتب لسببٍ وجيه: لا
 * بيانات له — `employee_attachments` بلا نوعٍ ولا تاريخِ انتهاء.
 *
 * فلمّا وُجد `employee_documents` صار للمهمّة ما تقرؤه. وبكتابة هذا الأمر
 * تعود `isAvailable()` صدقاً، **فيُفعّل السيدرُ الصفَّ تلقائياً وينزع
 * الوسم** — بلا تدخّلٍ يدوي.
 *
 * ─────────────────── لماذا لا يُنبَّه كلَّ يوم؟ ───────────────────
 *
 * المهمّة يومية، ووثيقةٌ في نافذة الثلاثين يوماً ستُنبَّه ثلاثين مرّة.
 * وأوّلُ ما يُفعل بتنبيهٍ يتكرّر كلَّ يوم أن يُتجاهَل — فتضيع معه
 * التنبيهاتُ التي تستحقّ. فالتذكيرُ أسبوعيّ عبر `last_alert_sent_at`.
 *
 * ─────────────────── ولماذا مدًى لا يومٌ بعينه؟ ───────────────────
 *
 * `CheckContractExpirations` تطابق «اليوم الموافق تماماً»
 * (`whereDate('end_date', $targetDate)`) — فإن فات `schedule:run` يوماً
 * واحداً سقط التنبيهُ كلُّه ولا يُرسَل أبداً. وهنا الشرطُ مدًى: كلُّ ما
 * دخل النافذة يُنبَّه عليه، فاتت المهمّةُ يوماً أو أياماً.
 */
class CheckEmployeeDocumentExpiry extends Command
{
    use SendsExpiryReminders;

    protected $signature = 'hr:document-expiry-alerts
                            {--days= : عدد أيام الإنذار — يعلو على الإعدادات}
                            {--dry-run : عرضُ ما سيُرسَل بلا إرسال}';

    protected $description = 'ينبّه على الإقامات والرخص والشهادات قبل انتهائها';

    /** المدّة الافتراضية حين لا تُضبط في الإعدادات. */
    private const DEFAULT_NOTICE_DAYS = 30;

    public function handle(): int
    {
        $days = $this->resolveNoticeDays();
        $dryRun = (bool) $this->option('dry-run');

        $this->info("البحث عن وثائق تنتهي خلال {$days} يوماً أو انتهت فعلاً…");

        /*
            `expiringWithin` تشمل ما انتهى كذلك — وهو مقصود: وثيقةٌ فاتت
            أَولى بالتنبيه ممّا سينتهي، ولا يجوز أن تسقط من القائمة لأنّ
            تاريخَها مضى.
        */
        $documents = EmployeeDocument::with('employee')
            ->expiringWithin($days)
            ->orderBy('expiry_date')
            ->get()
            ->filter(fn (EmployeeDocument $document) => $this->isReminderDue($document));

        if ($documents->isEmpty()) {
            $this->info('لا وثائق تستحقّ التنبيه اليوم.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($documents as $document) {
            $employee = $document->employee;

            // وثيقةٌ بلا صاحبٍ لا يُنبَّه عليها أحد
            if (! $employee) {
                continue;
            }

            $remaining = $document->daysUntilExpiry();
            $line = sprintf(
                '%s — %s (%s)',
                $employee->full_name,
                $document->displayTitle(),
                $this->describeRemaining($remaining)
            );

            if ($dryRun) {
                $this->line('  ▸ '.$line);
                $sent++;

                continue;
            }

            $this->notify($document, $line);
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
            $configured = PayrollSettings::value('document_expiry_notice_period');
        } catch (\Throwable) {
            // إعداداتٌ غير مهيّأة يجب ألّا تُعطّل التنبيه
            $configured = null;
        }

        return $configured && (int) $configured > 0
            ? (int) $configured
            : self::DEFAULT_NOTICE_DAYS;
    }

    private function notify(EmployeeDocument $document, string $summary): void
    {
        $employee = $document->employee;
        $expiry = optional($document->expiry_date)->format('Y-m-d');
        $code = $employee->employee_code ? ' (رقم '.$employee->employee_code.')' : '';

        // إشعارُ الموظف نفسِه — إن كان له حساب
        $employeeUser = User::where('employee_id', $employee->id)->first();

        if ($employeeUser) {
            notifications::create([
                'user_id' => null,
                'receiver_id' => $employeeUser->id,
                'title' => 'تنبيه: اقتراب انتهاء '.$document->displayTitle(),
                'description' => $document->displayTitle().' الخاصة بك تنتهي في '.$expiry.'. راجع قسم الموارد البشرية لتجديدها.',
                'type' => 'employee_document_expiry',
                'read' => 0,
            ]);
        }

        // وإشعارُ المسؤولين — `receiver_id` فارغاً كما في نظيرتها للعقود
        notifications::create([
            'user_id' => null,
            'receiver_id' => null,
            'title' => 'تنبيه: انتهاء وثيقة موظف',
            'description' => $document->displayTitle().' للموظف '.$employee->full_name.$code
                .' تنتهي في '.$expiry.' — '.$summary,
            'type' => 'employee_document_expiry_admin',
            'read' => 0,
        ]);

        $this->stampReminder($document);
    }
}
