<?php

namespace Modules\Client\Reservations\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Schema;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Models\Reservation;

/**
 * موعدُ الحجز — الحجزُ المؤكَّد يظهر في شاشة «المواعيد».
 *
 * ═══════════ صفٌّ واحدٌ لكلّ حجز ═══════════
 *
 * الموعدُ مربوطٌ بحجزه بـ`appointments.reservation_id` (فريد)، ويُكتب من حالة
 * الحجز لا من الزرّ الذي ضُغط — فكلُّ طريقٍ يغيّر الحالة (الشاشة، إعادة
 * الجدولة، الإنشاء مؤكَّداً مباشرةً، الأمر الخلفي) ينتهي إلى النتيجة نفسِها:
 *
 *   مؤكَّد / حضر / جارٍ        ← موعدٌ «قيد الانتظار» (يُنشأ إن لم يوجد)
 *   مكتمل                     ← «مكتمل»
 *   انتظار / معاد جدولته      ← يُنقل الموعدُ القائم ويصير «معاد جدولته»
 *   ملغى / مرفوض / لم يحضر    ← يُغلق الموعدُ القائم «ملغي»
 *   مسودّة                    ← لا شيء
 *
 * «قيد الانتظار» بلا تأكيدٍ لا يُنشئ موعداً: الموعدُ التزامٌ أمام العميل،
 * والحجزُ غيرُ المؤكَّد لم يصر التزاماً بعد.
 *
 * ═══════════ ويُنادى داخل معاملة الحجز ═══════════
 *
 * فإن تراجع الانتقالُ تراجع الموعدُ معه، ولا يبقى موعدٌ لحجزٍ لم يتأكّد.
 * وعلى استضافةٍ لم تُشغَّل فيها الترحيلةُ بعد (`ready()` كاذبة) يتنحّى بصمت —
 * فلا يتوقّف تأكيدُ الحجوزات بسبب عمودٍ ناقص.
 */
class ReservationAppointmentSync
{
    /** نوعُ الإجراء الذي تُعرف به مواعيدُ الحجوزات في شاشة المواعيد وفلترها. */
    public const ACTION_TYPE = 'حجز';

    protected static ?bool $ready = null;

    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('appointments')
            && Schema::hasColumn('appointments', 'reservation_id');
    }

    /** موعدُ الحجز القائم — أو `null`. */
    public function for(Reservation $reservation): ?Appointment
    {
        if (! self::ready() || ! $reservation->exists) {
            return null;
        }

        return Appointment::where('reservation_id', $reservation->id)->first();
    }

    /** يكتب موعدَ الحجز على حالته الحاليّة — انظر ترويسة الصنف. */
    public function sync(Reservation $reservation): ?Appointment
    {
        $appointment = $this->for($reservation);

        if (! self::ready() || ! $reservation->exists) {
            return null;
        }

        $status = $this->statusFor($reservation->status, $appointment !== null);

        // `appointments.client_id` إلزاميّ — والحجزُ بلا عميلٍ لا موعدَ له
        if ($status === null || ! $reservation->client_id || ! $reservation->reservation_date || ! $reservation->start_time) {
            return $appointment;
        }

        $appointment ??= new Appointment;

        $appointment->forceFill([
            'reservation_id' => $reservation->id,
            'client_id' => $reservation->client_id,
            'employee_id' => $reservation->employee_id,
            'appointment_date' => $reservation->reservation_date->toDateString(),
            'time' => $this->pad((string) $reservation->start_time),
            'duration' => (string) (int) $reservation->duration,
            'notes' => $this->notes($reservation),
            'action_type' => self::ACTION_TYPE,
            'status' => $status,
            'created_by' => $appointment->created_by ?? (auth()->id() ?? $reservation->created_by),
        ])->save();

        return $appointment;
    }

    /** حُذف الحجز — يُغلق موعدُه «ملغي» ولا يُحذف، إلّا ما اكتمل فيبقى شاهداً. */
    public function retire(Reservation $reservation): void
    {
        $appointment = $this->for($reservation);

        if ($appointment && (int) $appointment->status !== Appointment::STATUS_COMPLETED) {
            $appointment->forceFill(['status' => Appointment::STATUS_IGNORED])->save();
        }
    }

    protected function statusFor(ReservationStatus $status, bool $exists): ?int
    {
        return match ($status) {
            ReservationStatus::CONFIRMED,
            ReservationStatus::CHECKED_IN,
            ReservationStatus::IN_PROGRESS => Appointment::STATUS_PENDING,
            ReservationStatus::COMPLETED => Appointment::STATUS_COMPLETED,
            ReservationStatus::PENDING,
            ReservationStatus::RESCHEDULED => $exists ? Appointment::STATUS_RESCHEDULED : null,
            ReservationStatus::CANCELLED,
            ReservationStatus::REJECTED,
            ReservationStatus::NO_SHOW => $exists ? Appointment::STATUS_IGNORED : null,
            ReservationStatus::DRAFT => null,
        };
    }

    protected function notes(Reservation $reservation): string
    {
        $line = __('reservations.links.appointment_note', [
            'code' => $reservation->code,
            'service' => $reservation->service_name ?: '—',
        ]);

        return $reservation->notes ? $line."\n".$reservation->notes : $line;
    }

    protected function pad(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
