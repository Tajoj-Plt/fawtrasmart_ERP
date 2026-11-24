<?php

namespace App\Console\Commands;

use App\Models\CronJobExecution;
use Illuminate\Console\Command;

/**
 * تنظيف سجلّ تنفيذ المهام.
 *
 * مهمة تعمل كل دقيقة تكتب أكثر من نصف مليون صفّ في السنة. بلا تنظيف
 * دوري يصبح الجدول أثقل من كل ما عداه في قاعدة البيانات.
 */
class PruneCronExecutions extends Command
{
    protected $signature = 'cron:prune-executions {--days=30 : عمر السجلّات التي يُحتفظ بها}';

    protected $description = 'حذف سجلّات تنفيذ المهام الأقدم من المدّة المحدَّدة';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        // الحذف على دفعات: `delete` واحد على مئات الآلاف من الصفوف يقفل
        // الجدول طويلاً ويعطّل الكتابة عليه أثناء ذلك
        $total = 0;

        do {
            $deleted = CronJobExecution::where('created_at', '<', $cutoff)
                ->limit(2000)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        $this->info("حُذف {$total} سجلّ تنفيذ أقدم من {$days} يوماً.");

        return self::SUCCESS;
    }
}
