<?php

namespace App\Console\Commands;

use App\Services\Invoices\PeriodicInvoiceGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * إصدار فواتير الاشتراكات الدورية المستحقّة.
 *
 * يُشغَّل من «المهام المجدولة» في الإعدادات، لا من `Console\Kernel`:
 * جدولة هذا النظام تعيش في جدول `cron_jobs` ويقودها `cron:run-due`.
 * الأمر مسجَّلٌ في `App\Services\Cron\CronRegistry::commands()` — وهي
 * قائمةٌ بيضاء، فما ليس فيها يُرفض قبل أن يصل إلى `Artisan::call`.
 *
 *     php artisan sales:generate-periodic-invoices
 *     php artisan sales:generate-periodic-invoices --dry-run
 *     php artisan sales:generate-periodic-invoices --date=2026-09-01
 */
class GeneratePeriodicInvoices extends Command
{
    protected $signature = 'sales:generate-periodic-invoices
                            {--dry-run : يحسب المستحقّ ويعرضه بلا كتابة}
                            {--date= : اليوم المرجعي بصيغة Y-m-d، افتراضه اليوم}';

    protected $description = 'إصدار فواتير المبيعات المستحقّة من الاشتراكات الدورية';

    public function handle(PeriodicInvoiceGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $on = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        } catch (\Throwable $e) {
            $this->error('تاريخ غير صالح: '.$this->option('date'));

            return self::FAILURE;
        }

        $result = $generator->run($on, $dryRun);

        $this->line(($dryRun ? '[معاينة] ' : '')."اليوم المرجعي: {$on->toDateString()}");
        $this->line("اشتراكات مفحوصة: {$result['scanned']}");
        $this->line('فواتير '.($dryRun ? 'مستحقّة' : 'صادرة').": {$result['created']}");
        $this->line("متخطّاة: {$result['skipped']}   أخفقت: {$result['failed']}");

        foreach ($result['invoices'] as $line) {
            $this->line('  • '.$line);
        }

        foreach ($result['notes'] as $note) {
            $this->warn('  ! '.$note);
        }

        // الإخفاق يُرفَع إلى `cron_jobs` كي تُعلَّم المهمّة فاشلةً ولا
        // تمرّ بصمت في شاشة «المهام المجدولة»
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
