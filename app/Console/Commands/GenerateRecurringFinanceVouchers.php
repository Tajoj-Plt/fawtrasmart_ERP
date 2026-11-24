<?php

namespace App\Console\Commands;

use App\Services\Finance\RecurringVoucherGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon as SupportCarbon;

/**
 * إصدار سندات القبض والصرف المكرَّرة المستحقّة.
 *
 * شاشتا السندين تَعِدان بأنّ «السند المكرَّر يُعاد إنشاؤه تلقائياً على
 * فترات»، ولم يكن شيءٌ يُنشئه. المنطق كلُّه في
 * `App\Services\Finance\RecurringVoucherGenerator` — ومنه لماذا تمرّ النسخة
 * بـ`store()` المتحكّم نفسها.
 *
 * يُشغَّل من «المهام المجدولة» في الإعدادات، لا من `Console\Kernel`:
 * الأمر مسجَّلٌ في `App\Services\Cron\CronRegistry::commands()` وصفُّه
 * `finance_recurring_vouchers` في `cron_jobs`.
 *
 *     php artisan finance:generate-recurring-vouchers
 *     php artisan finance:generate-recurring-vouchers --dry-run
 *     php artisan finance:generate-recurring-vouchers --date=2026-09-01
 */
class GenerateRecurringFinanceVouchers extends Command
{
    protected $signature = 'finance:generate-recurring-vouchers
                            {--dry-run : يحسب المستحقّ ويعرضه بلا كتابة}
                            {--date= : اليوم المرجعي بصيغة Y-m-d، افتراضه اليوم}';

    protected $description = 'إصدار النسخ المستحقّة من سندات القبض والصرف المكرَّرة';

    public function handle(RecurringVoucherGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $on = $this->option('date')
                ? SupportCarbon::instance(Carbon::createFromFormat('Y-m-d', $this->option('date')))->startOfDay()
                : SupportCarbon::today();
        } catch (\Throwable $e) {
            $this->error('تاريخ غير صالح: '.$this->option('date'));

            return self::FAILURE;
        }

        $result = $generator->run($on, $dryRun);

        $this->line(($dryRun ? '[معاينة] ' : '')."اليوم المرجعي: {$on->toDateString()}");
        $this->line("سندات مكرَّرة مفحوصة: {$result['scanned']}");
        $this->line('نسخ '.($dryRun ? 'مستحقّة' : 'صادرة').": {$result['created']}");
        $this->line("متخطّاة: {$result['skipped']}   أخفقت: {$result['failed']}");

        foreach ($result['vouchers'] as $line) {
            $this->line('  • '.$line);
        }

        foreach ($result['notes'] as $note) {
            $this->warn('  ! '.$note);
        }

        // الإخفاق يُرفَع إلى `cron_jobs` كي تُعلَّم المهمّة فاشلةً ولا تمرّ بصمت
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
