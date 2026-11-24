<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\Backup\BackupCleanupService;
use Illuminate\Console\Command;

/**
 * تطبيق سياسة الاحتفاظ بالنسخ الاحتياطية على حدة.
 *
 * منفصل عن `backup:scheduled-run` لأن التنظيف قد يُراد جدولته وحده — مثلاً
 * حين تُؤخذ النسخ يدوياً بينما يُراد تنظيفها آلياً.
 *
 * ليس `backup:clean` الخاص بالحزمة: ذاك يمسح المجلَّد اعتماداً على أعمار
 * الملفات ولا يعرف شيئاً عن `backups`، فيخلّف صفوفاً تشير إلى ملفات محذوفة.
 */
class CleanupBackups extends Command
{
    protected $signature = 'backup:cleanup {--dry-run : عرض ما سيُحذف دون حذفه}';

    protected $description = 'حذف النسخ الاحتياطية التي تجاوزت سياسة الاحتفاظ المضبوطة';

    public function handle(BackupCleanupService $cleanup): int
    {
        if ($this->option('dry-run')) {
            $candidates = Backup::cleanable()->orderByDesc('created_at')->get();

            $this->info('المرشَّحات للتنظيف (الأحدث محميّة دائماً): '.$candidates->count());

            foreach ($candidates as $index => $backup) {
                $this->line(sprintf(
                    '%s#%d %s — %s — %s%s',
                    $index === 0 ? '[محميّة] ' : '',
                    $backup->id,
                    $backup->file_name,
                    $backup->humanSize(),
                    $backup->created_at?->format('Y-m-d H:i'),
                    ''
                ));
            }

            return self::SUCCESS;
        }

        $result = $cleanup->run();

        $this->info(
            'حُذفت '.$result['deleted'].' نسخة، '
            .'أُفرغ '.Backup::formatBytes($result['freed_bytes']).'، '
            .'أُبقيت '.$result['kept'].'.'
        );

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
