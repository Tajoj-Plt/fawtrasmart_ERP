<?php

namespace Modules\Client\Reservations\Listeners;

use App\Models\notifications;
use Illuminate\Support\Facades\Log;
use Modules\Client\Reservations\Events\ReservationCancelled;
use Modules\Client\Reservations\Events\ReservationCompleted;
use Modules\Client\Reservations\Events\ReservationConfirmed;
use Modules\Client\Reservations\Events\ReservationCreated;
use Modules\Client\Reservations\Events\ReservationEvent;
use Modules\Client\Reservations\Events\ReservationNoShow;
use Modules\Client\Reservations\Events\ReservationRescheduled;
use Modules\Client\Reservations\Models\ReservationSetting;
use Throwable;

/**
 * إشعارُ العميل عند أحداث الحجز.
 *
 * ═══════════ لا نظامَ إشعاراتٍ ثانٍ ═══════════
 *
 * يُكتب صفٌّ في `notifications` ولا شيء غير ذلك. والقنواتُ الإضافية
 * (البريد والرسائل النصّية) يقرّرها `NotificationDispatcher` من «إعدادات
 * الإشعارات» بحسب نوع الإشعار — فالمسؤولُ يفعّل قناةً لنوعٍ بعينه ولا
 * تُفرَض عليه من هنا.
 *
 * ═══════════ مفتاحان لا مفتاح ═══════════
 *
 *   • إعداداتُ الوحدة (`notify_confirmed`…) تقرّر: هل يُنشأ الإشعار؟
 *   • إعداداتُ الإشعارات تقرّر: بأيّ قناةٍ يُسلَّم؟
 *
 * وهما مستقلّان عمداً: منشأةٌ تريد سجلّاً داخلياً بلا رسائل، وأخرى تريد
 * رسائلَ للتأكيد وحده.
 *
 * ═══════════ ولا يرمي شيئاً ═══════════
 *
 * فشلُ الإشعار لا يجوز أن يُسقط العمليةَ التي نجحت: الحجزُ تأكّد فعلاً،
 * وتعطّلُ بوابة الرسائل لا يُلغي تأكيدَه. يُبتلَع ويُكتب في سجلّ لارافيل.
 */
class SendReservationNotification
{
    /**
     * خريطةُ الحدث ← (مفتاح الإعداد، نوع الإشعار، العنوان، صائغُ النصّ).
     *
     * الخريطةُ هنا لا في `if`ات متتالية: إضافةُ حدثٍ سطرٌ واحد، ونسيانُ
     * ربطه بمفتاحِ إعدادٍ صار مستحيلاً — الخريطةُ تُلزم بالأربعة معاً.
     *
     * @return array<class-string, array{0:string,1:string,2:string,3:callable}>
     */
    protected function map(): array
    {
        return [
            ReservationCreated::class => [
                'notify_created', 'reservation_created', 'تم إنشاء حجزك',
                fn ($r) => "تم تسجيل حجزك {$r->code}{$this->service($r)} بتاريخ {$this->when($r)}.",
            ],
            ReservationConfirmed::class => [
                'notify_confirmed', 'reservation_confirmed', 'تم تأكيد حجزك',
                fn ($r) => "تم تأكيد حجزك {$r->code}{$this->service($r)} بتاريخ {$this->when($r)}.",
            ],
            ReservationCancelled::class => [
                'notify_cancelled', 'reservation_cancelled', 'تم إلغاء حجزك',
                fn ($r) => "أُلغي حجزك {$r->code} المقرّر بتاريخ {$this->when($r)}."
                    .($r->cancellation_reason ? " السبب: {$r->cancellation_reason}." : ''),
            ],
            ReservationRescheduled::class => [
                'notify_rescheduled', 'reservation_rescheduled', 'تم تغيير موعد حجزك',
                fn ($r) => "نُقل حجزك {$r->code} إلى {$this->when($r)}.",
            ],
            ReservationCompleted::class => [
                'notify_completed', 'reservation_completed', 'اكتملت خدمتك',
                fn ($r) => "اكتمل حجزك {$r->code}{$this->service($r)}. شكراً لك.",
            ],
            ReservationNoShow::class => [
                'notify_no_show', 'reservation_no_show', 'لم يُسجَّل حضورك',
                fn ($r) => "لم يُسجَّل حضورك لحجز {$r->code} بتاريخ {$this->when($r)}.",
            ],
        ];
    }

    public function handle(ReservationEvent $event): void
    {
        try {
            $definition = $this->map()[get_class($event)] ?? null;

            if (! $definition) {
                return;
            }

            [$settingKey, $type, $title, $body] = $definition;

            if (! ReservationSetting::current()->{$settingKey}) {
                return;
            }

            $reservation = $event->reservation;

            // إشعارٌ بلا عميل لا مُستقبِل له
            if (! $reservation->client_id) {
                return;
            }

            notifications::create([
                'user_id' => null,
                'receiver_id' => null,
                'client_id' => $reservation->client_id,
                'title' => $title,
                'description' => $body($reservation),
                'type' => $type,
                'read' => 0,
            ]);
        } catch (Throwable $e) {
            // انظر ترويسة الصنف: لا يُسقط العمليةَ التي نجحت
            Log::warning('تعذّر إنشاء إشعار الحجز', [
                'event' => get_class($event),
                'reservation' => $event->reservation->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function service($reservation): string
    {
        return ($reservation->product_id || $reservation->service_id) ? ' — '.$reservation->service_name : '';
    }

    protected function when($reservation): string
    {
        return $reservation->reservation_date->format('Y-m-d')
            .' الساعة '.substr((string) $reservation->start_time, 0, 5);
    }
}
