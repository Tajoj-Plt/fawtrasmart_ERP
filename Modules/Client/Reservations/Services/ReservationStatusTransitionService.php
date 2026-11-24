<?php

namespace Modules\Client\Reservations\Services;

use App\Support\OperationLog;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Events\ReservationCancelled;
use Modules\Client\Reservations\Events\ReservationCheckedIn;
use Modules\Client\Reservations\Events\ReservationCompleted;
use Modules\Client\Reservations\Events\ReservationConfirmed;
use Modules\Client\Reservations\Events\ReservationNoShow;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Models\ReservationStatusHistory;

/**
 * الانتقالُ بين حالات الحجز — البابُ الوحيد لتغيير `status`.
 *
 * ═══════════ لماذا بابٌ واحد؟ ═══════════
 *
 * المتحكّمُ القديم كان يكتب `$reservation->update(['status' => $request->status])`
 * مباشرةً: أيُّ حالةٍ إلى أيّ حالة، بلا قاعدة. فيُعاد حجزٌ مكتملٌ إلى
 * «قيد الانتظار» بعد أن صدرت فاتورتُه، ويُلغى حجزٌ ملغىً فيُخصَم رسمُ
 * إلغاءٍ مرّتين.
 *
 * كلُّ تغييرِ حالةٍ يمرّ من هنا، ويُجري خمسةَ أشياءَ معاً **في معاملةٍ
 * واحدة** — فإمّا أن تقع كلُّها أو لا يقع شيء:
 *
 *   1. قفلُ صفّ الحجز وقراءةُ حالته **من القاعدة** لا من الذاكرة.
 *   2. التحقّق من جواز الانتقال ومن قواعد الإعدادات (المُهَل والحدود).
 *   3. ختمُ لحظة الانتقال في عمودها (`confirmed_at`، `cancelled_at`…).
 *   4. كتابةُ صفٍّ في خطّ حياة الحجز.
 *   5. تسجيلٌ في السجلّات، ثمّ إطلاقُ الحدث **بعد الالتزام**.
 *
 * ═══════════ لماذا القفل؟ ═══════════
 *
 * نقرتان على «إلغاء الحجز» تصلان معاً. كلتاهما قرأت الحجزَ «مؤكَّداً»
 * قبل أن تكتب الأولى، فتنجحان — ويُقتطع رسمُ الإلغاء مرّتين ويُكتب
 * قيدان. فالحالةُ تُقرأ بعد القفل، والطلبُ الثاني يجدها «ملغاة» فيُردّ.
 *
 * ═══════════ ولماذا الحدثُ بعد الالتزام لا بعد الحفظ؟ ═══════════
 *
 * المستمعون يكتبون إشعاراتٍ للعميل — وهي آثارٌ لا تُلغى بـrollback. وقد
 * يقع الانتقالُ داخل معاملةٍ أوسع (إلغاءٌ معه ردٌّ ماليّ، إعادةُ جدولةٍ
 * معها فحصُ توفّر)، فيفشل ما بعده ويتراجع كلُّ شيء. `DB::afterCommit`
 * تُطلق الحدثَ حين تلتزم المعاملةُ الخارجية وحدَها، ولا تُطلقه إن تراجعت.
 */
class ReservationStatusTransitionService
{
    /**
     * ينقل الحجزَ إلى حالةٍ جديدة، أو يرمي `ReservationException`.
     *
     * @param  array<string, mixed>  $extra  أعمدةٌ تُحدَّث مع الانتقال
     */
    public function transition(
        Reservation $reservation,
        ReservationStatus $target,
        ?string $note = null,
        array $extra = []
    ): Reservation {
        $current = DB::transaction(function () use ($reservation, $target, $note, $extra) {
            // القفلُ قبل الفحص — انظر ترويسة الصنف
            $reservation->lockForWrite();

            $current = $reservation->status;

            if ($current === $target) {
                throw ReservationException::because(
                    __('reservations.errors.already_in_status', ['status' => $target->label()])
                );
            }

            if (! $current->canTransitionTo($target)) {
                throw ReservationException::because(__('reservations.errors.transition_not_allowed', [
                    'from' => $current->label(),
                    'to' => $target->label(),
                ]));
            }

            $this->assertSettingsAllow($reservation, $target);

            $reservation->fill($extra);
            $reservation->status = $target;

            foreach ($this->stampColumn($target) as $column) {
                $reservation->{$column} = now();
            }

            $reservation->updated_by = auth()->id();
            $reservation->save();

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'note' => $note,
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);

            // موعدُ الحجز يتبع حالتَه — داخل المعاملة فيتراجع معها
            app(ReservationAppointmentSync::class)->sync($reservation);

            return $current;
        });

        OperationLog::record(
            'status_changed',
            'Reservations',
            $reservation,
            "تغيير حالة الحجز {$reservation->code} من «{$current->label()}» إلى «{$target->label()}»"
            .($note ? " — {$note}" : ''),
            ['old' => ['status' => $current->value], 'new' => ['status' => $target->value]]
        );

        DB::afterCommit(fn () => $this->dispatchFor($reservation, $target));

        return $reservation;
    }

    /**
     * الحالاتُ التي يجوز للمستخدم الحاليّ نقلُ الحجز إليها.
     *
     * تجمع محورَي القيد معاً: ما تسمح به الحالةُ، وما يملك المستخدمُ
     * صلاحيتَه. فلا يُعرَض زرٌّ يرفضه الحفظ — وهو أكثرُ ما يُربك المستخدم.
     *
     * @return array<int, ReservationStatus>
     */
    public function availableTransitions(Reservation $reservation): array
    {
        return array_values(array_filter(
            $reservation->status->allowedTransitions(),
            function (ReservationStatus $target) use ($reservation) {
                $permission = $this->permissionFor($target);

                if ($permission && ! hasPermission($permission)) {
                    return false;
                }

                // الحجزُ المفوتَر (بفاتورةٍ قائمة) لا يُلغى من هنا — الفاتورةُ تُعالَج في المبيعات
                if ($target === ReservationStatus::CANCELLED && $reservation->isInvoiced()) {
                    return false;
                }

                try {
                    $this->assertSettingsAllow($reservation, $target);
                } catch (ReservationException $e) {
                    return false;
                }

                return true;
            }
        ));
    }

    /** صلاحيةُ الانتقال إلى حالةٍ — أو `null` إن لم تُفرَد لها صلاحية. */
    public function permissionFor(ReservationStatus $target): ?string
    {
        return match ($target) {
            ReservationStatus::CONFIRMED => 'reservations.confirm',
            ReservationStatus::CHECKED_IN => 'reservations.checkin',
            ReservationStatus::IN_PROGRESS => 'reservations.start',
            ReservationStatus::COMPLETED => 'reservations.complete',
            ReservationStatus::CANCELLED, ReservationStatus::REJECTED => 'reservations.cancel',
            ReservationStatus::RESCHEDULED => 'reservations.reschedule',
            ReservationStatus::NO_SHOW => 'reservations.checkin',
            default => null,
        };
    }

    // ════════════════════ قواعد الإعدادات ════════════════════

    /**
     * القواعدُ التي لا تُستنتَج من جدول الانتقالات — بل من شاشة الإعدادات.
     */
    public function assertSettingsAllow(Reservation $reservation, ReservationStatus $target): void
    {
        $settings = ReservationSetting::current();

        if ($target === ReservationStatus::CANCELLED) {
            if (! $settings->allow_cancellation) {
                throw ReservationException::because(__('reservations.errors.cancellation_disabled'));
            }

            if (! $settings->allow_cancel_after_confirm && $reservation->status === ReservationStatus::CONFIRMED) {
                throw ReservationException::because(__('reservations.errors.cancel_after_confirm_disabled'));
            }

            $this->assertWithinDeadline(
                $reservation,
                (int) $settings->cancellation_deadline_hours,
                __('reservations.actions.cancel')
            );
        }

        if ($target === ReservationStatus::RESCHEDULED) {
            if (! $settings->allow_rescheduling) {
                throw ReservationException::because(__('reservations.errors.rescheduling_disabled'));
            }

            $max = (int) $settings->max_reschedule_attempts;

            if ($max > 0 && (int) $reservation->reschedule_count >= $max) {
                throw ReservationException::because(__('reservations.errors.reschedule_limit', ['max' => $max]));
            }

            $this->assertWithinDeadline(
                $reservation,
                (int) $settings->reschedule_deadline_hours,
                __('reservations.actions.reschedule')
            );
        }

        /*
            «لم يحضر» قبل أن يحلّ الموعدُ أصلاً لا معنى لها — والعميلُ الذي
            لم يحن دورُه بعدُ ليس متخلّفاً عن الحضور. وهي حالةٌ نهائية،
            فوسمُه بها مبكّراً يُغلق حجزَه بلا رجعة.
        */
        if ($target === ReservationStatus::NO_SHOW && $reservation->starts_at?->isFuture()) {
            throw ReservationException::because(__('reservations.errors.no_show_before_start'));
        }

        /*
            الإنهاءُ في يوم الموعد وحدَه (طلب المستخدم 2026-09-29): حجزُ الغد
            لا يُنهى اليوم، وحجزُ الأمس لا يُنهى بأثرٍ رجعي. والفحصُ هنا فيختفي
            الزرُّ من `availableTransitions()` ويرفضه الحفظُ معاً.
        */
        if ($target === ReservationStatus::COMPLETED
            && ! $reservation->reservation_date?->isSameDay(now())) {
            throw ReservationException::because(__('reservations.errors.complete_not_today', [
                'date' => $reservation->reservation_date?->format('Y-m-d') ?? '—',
            ]));
        }
    }

    /**
     * المهلةُ قبل الموعد: تُقاس من الآن إلى بداية الحجز.
     *
     * **ولا تسري على موعدٍ فات.** المهلةُ تحمي المنشأةَ من إلغاءٍ متأخّر،
     * ولا معنى لأن تمنع تصحيحَ سجلٍّ عن موعدٍ مضى — وإلّا بقي حجزٌ قديم
     * عالقاً في «مؤكَّد» إلى الأبد.
     */
    protected function assertWithinDeadline(Reservation $reservation, int $hours, string $action): void
    {
        if ($hours <= 0) {
            return;
        }

        $startsAt = $reservation->starts_at;

        if (! $startsAt || $startsAt->isPast()) {
            return;
        }

        if (now()->diffInMinutes($startsAt, false) < $hours * 60) {
            throw ReservationException::because(__('reservations.errors.deadline', [
                'action' => $action,
                'hours' => $hours,
            ]));
        }
    }

    // ════════════════════ الختم والأحداث ════════════════════

    /**
     * أعمدةُ الوقت التي تُختَم عند بلوغ حالةٍ.
     *
     * @return array<int, string>
     */
    protected function stampColumn(ReservationStatus $target): array
    {
        return match ($target) {
            ReservationStatus::CONFIRMED => ['confirmed_at'],
            ReservationStatus::CHECKED_IN => ['checked_in_at'],
            ReservationStatus::IN_PROGRESS => ['started_at'],
            ReservationStatus::COMPLETED => ['completed_at'],
            ReservationStatus::CANCELLED, ReservationStatus::REJECTED => ['cancelled_at'],
            ReservationStatus::NO_SHOW => ['no_show_at'],
            default => [],
        };
    }

    protected function dispatchFor(Reservation $reservation, ReservationStatus $target): void
    {
        match ($target) {
            ReservationStatus::CONFIRMED => event(new ReservationConfirmed($reservation)),
            ReservationStatus::CHECKED_IN => event(new ReservationCheckedIn($reservation)),
            ReservationStatus::COMPLETED => event(new ReservationCompleted($reservation)),
            ReservationStatus::CANCELLED, ReservationStatus::REJECTED => event(new ReservationCancelled($reservation)),
            ReservationStatus::NO_SHOW => event(new ReservationNoShow($reservation)),
            default => null,
        };
    }
}
