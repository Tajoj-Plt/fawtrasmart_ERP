<?php

namespace App\Jobs;

use App\Models\notifications;
use App\Services\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * إرسال قنوات الإشعار الإضافية (بريد/رسائل) بعد انتهاء الطلب.
 *
 * الإرسال المباشر داخل الطلب يكلّف ثوانٍ: خادم بريد بطيء أو متوقّف يعلّق
 * كل عملية يُنشأ فيها إشعار — قياساً، خادم غير موجود أخّر الطلب 10.5 ثانية.
 * `dispatchAfterResponse` يجعل ذلك يحدث بعد وصول الصفحة للمستخدم، وهو
 * يعمل مهما كانت إعدادات الطابور (المشروع على `sync` حالياً).
 */
class DeliverNotificationChannels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        try {
            $notification = notifications::find($this->notificationId);

            if (! $notification) {
                return;
            }

            NotificationDispatcher::deliver($notification);
        } catch (\Throwable $e) {
            // آخر خط دفاع: لا شيء هنا يجوز أن يصعد إلى العملية الأصلية
            Log::warning('تعذّر تسليم قنوات الإشعار', [
                'notification_id' => $this->notificationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
