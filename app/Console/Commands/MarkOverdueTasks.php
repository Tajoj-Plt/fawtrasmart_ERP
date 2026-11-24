<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Console\Command;

/**
 * يضع المهامّ التي فات استحقاقها في حالة «متأخرة».
 *
 * الحالة `overdue` عمودٌ في لوحة المهام وخانةٌ في كل تقرير، ولم يكن في
 * النظام ما يضعها: لا تصير المهمّة «متأخرة» إلا إن سحبها أحدٌ بيده إلى
 * العمود. فالتقارير تعدّ ما سُحب، لا ما تأخّر.
 *
 * يُسجَّل في [\App\Services\Cron\CronRegistry] ويُشغَّل من `cron_jobs`.
 */
class MarkOverdueTasks extends Command
{
    protected $signature = 'tasks:mark-overdue {--dry-run : عرض ما سيتغيّر دون حفظ}';

    protected $description = 'وضع المهامّ التي فات تاريخ استحقاقها ولم تكتمل في حالة «متأخرة»';

    public function handle(): int
    {
        $query = Task::query()
            ->whereIn('status', [TaskVocabulary::STATUS_NOT_STARTED, TaskVocabulary::STATUS_IN_PROGRESS])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());

        if ($this->option('dry-run')) {
            $this->info("مهامّ فات استحقاقها: {$query->count()} (لم يُحفظ شيء)");

            return self::SUCCESS;
        }

        $marked = 0;

        // بالنموذج لا بتحديثٍ جماعي: حفظ النموذج يمرّ بما يتبع الحالة
        // (تاريخ الإنجاز)، و`chunkById` لأن الصفوف تخرج من الشرط كلّما حُفظت
        $query->chunkById(200, function ($tasks) use (&$marked) {
            foreach ($tasks as $task) {
                $task->update(['status' => TaskVocabulary::STATUS_OVERDUE]);
                $marked++;
            }
        });

        $this->info("وُضعت {$marked} مهمّة في حالة «متأخرة»");

        return self::SUCCESS;
    }
}
