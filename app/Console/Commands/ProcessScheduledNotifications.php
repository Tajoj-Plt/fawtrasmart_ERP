<?php

namespace App\Console\Commands;

use App\Models\notifications;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * معالجة الإشعارات المجدولة التي حان موعدها.
 *
 * كان المجدول يفعل هذا بـ `file_get_contents(url('/api/notifications/…'))`
 * أي بفتح اتصال HTTP من الخادم إلى نفسه كل دقيقة — يفشل خلف مصادقة أو
 * جدار ناري أو شهادة ذاتية، ويتعلّق حتى انتهاء المهلة، ولا يترك أثراً
 * مفهوماً. الاستدعاء المباشر هنا يلغي ذلك كلّه.
 */
class ProcessScheduledNotifications extends Command
{
    protected $signature = 'notifications:process-scheduled';

    protected $description = 'إرسال الإشعارات المجدولة التي حان وقتها';

    public function handle(): int
    {
        $due = notifications::where('is_scheduled', true)
            ->where('is_sent', false)
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($due as $notification) {
            try {
                $notification->update(['is_sent' => true]);

                // القنوات الإضافية لم تُطلَق عند الإنشاء لأن الإشعار كان
                // مجدولاً — هذه هي لحظة إرسالها الفعلية. الاستدعاء مباشر
                // لأننا في أمر طرفي أصلاً، فلا استجابة ننتظر انتهاءها
                NotificationDispatcher::deliver($notification);

                $sent++;
            } catch (\Throwable $e) {
                // إشعار واحد متعثّر يجب ألا يوقف الباقي
                Log::error('تعذّرت معالجة إشعار مجدول', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("تمت معالجة {$sent} إشعاراً مجدولاً.");
        Log::info('معالجة الإشعارات المجدولة', ['processed' => $sent]);

        return self::SUCCESS;
    }
}
