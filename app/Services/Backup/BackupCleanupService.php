<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\BackupSetting;
use App\Services\SystemAlertService;
use Illuminate\Support\Facades\Log;

/**
 * تطبيق سياسة الاحتفاظ بالنسخ الاحتياطية.
 *
 * ثلاث فئات لا تُحذف أبداً مهما بلغ عمرها أو عددها:
 * - النسخ الجارية (`pending`/`running`) — حذف ملف قيد الكتابة يُفسد النسخة.
 * - النسخ المحميّة يدوياً (`is_locked`).
 * - نسخ الأمان المأخوذة قبل استرجاع — هي طريق العودة الوحيد.
 *
 * وأياً كانت السياسة، تبقى أحدث نسخة صالحة دائماً: تطبيق حرفيّ لـ«احتفظ
 * بما عمره أقلّ من 30 يوماً» على نظام توقّف نسخه شهراً يمحو كل نسخه.
 */
class BackupCleanupService
{
    public function __construct(private BackupService $backups) {}

    /**
     * تنفيذ التنظيف.
     *
     * @return array{deleted:int,freed_bytes:int,kept:int,errors:array<int,string>}
     */
    public function run(?BackupSetting $settings = null): array
    {
        $settings ??= BackupSetting::current();

        $candidates = Backup::cleanable()->orderByDesc('created_at')->get();

        $result = ['deleted' => 0, 'freed_bytes' => 0, 'kept' => 0, 'errors' => []];

        if ($candidates->isEmpty()) {
            return $result;
        }

        // أحدث نسخة محميّة دائماً — تُزال من قائمة المرشَّحين قبل أي حساب
        $protected = $candidates->shift();
        $result['kept']++;

        $doomed = $settings->retention_strategy === 'days'
            ? $this->byAge($candidates, max(1, $settings->retention_days))
            : $this->byCount($candidates, max(1, $settings->retention_count));

        $result['kept'] += $candidates->count() - $doomed->count();

        foreach ($doomed as $backup) {
            try {
                $size = (int) $backup->size_bytes;
                $this->backups->delete($backup);

                $result['deleted']++;
                $result['freed_bytes'] += $size;
            } catch (\Throwable $e) {
                // نسخة متعذّرة الحذف لا توقف تنظيف البقية
                $message = 'تعذّر حذف النسخة #'.$backup->id.': '.$e->getMessage();
                $result['errors'][] = $message;

                Log::warning('فشل حذف نسخة احتياطية أثناء التنظيف', [
                    'backup_id' => $backup->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        unset($protected);

        if ($result['errors'] !== [] && $settings->notify_on_cleanup_failure) {
            SystemAlertService::send(
                SystemAlertService::BACKUP_CLEANUP_FAILED,
                'فشل تنظيف النسخ الاحتياطية القديمة',
                'تعذّر حذف '.count($result['errors']).' نسخة. أول خطأ: '.$result['errors'][0]
            );
        }

        return $result;
    }

    /**
     * ما تجاوز عمره المدّة المحدَّدة.
     *
     * @param  \Illuminate\Support\Collection<int, Backup>  $candidates
     * @return \Illuminate\Support\Collection<int, Backup>
     */
    protected function byAge($candidates, int $days)
    {
        $cutoff = now()->subDays($days);

        return $candidates->filter(fn (Backup $b) => $b->created_at !== null && $b->created_at->lt($cutoff))->values();
    }

    /**
     * ما زاد عن العدد المطلوب الاحتفاظ به.
     *
     * `$candidates` مرتّبة بالأحدث وقد أُزيلت منها النسخة المحميّة، فالعدد
     * المطلوب هنا أقلّ بواحد.
     *
     * @param  \Illuminate\Support\Collection<int, Backup>  $candidates
     * @return \Illuminate\Support\Collection<int, Backup>
     */
    protected function byCount($candidates, int $keep)
    {
        return $candidates->slice(max(0, $keep - 1))->values();
    }
}
