<?php

namespace App\Console\Commands;

use App\Services\Zatca\ZatcaInvoiceService;
use Illuminate\Console\Command;

/**
 * يرسل إلى هيئة الزكاة والضريبة والجمارك ما تعذّر بلوغه إياها.
 *
 * الفاتورة المبسّطة لها مهلةٌ نظامية للإبلاغ، والاتصال قد ينقطع لحظة
 * إنشائها. فهذه الدورة ما يضمن وصولها: تعيد إرسال الملفّ الموقَّع نفسه،
 * وترسل ما لم يُرسَل حين يكون الإرسال التلقائي مفعَّلاً.
 *
 * لا تفعل شيئاً والخدمة معطَّلة أو غير مسجَّلة — فتشغيلها آمنٌ دائماً.
 *
 * مسجَّلة في `CronRegistry` وصفّها في `cron_jobs` (`zatca_submit_pending`).
 */
class ZatcaSubmitPending extends Command
{
    protected $signature = 'zatca:submit-pending
        {--limit=50 : أقصى عدد مستندات في الدورة}
        {--dry-run : عرض ما سيُرسَل دون إرسال}';

    protected $description = 'إرسال الفواتير الإلكترونية المعلَّقة إلى هيئة الزكاة والضريبة والجمارك';

    public function handle(ZatcaInvoiceService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $stats = $service->processPending($limit, $dryRun);

        $this->info(sprintf(
            '%sمعاد إرساله: %d — جديد: %d — مقبول: %d — متعثّر: %d',
            $dryRun ? '[معاينة] ' : '',
            $stats['retried'],
            $stats['sent'],
            $stats['accepted'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
