<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Http\Controllers\Reservations\Concerns\ScopesReservations;
use Modules\Client\Http\Requests\Reservations\CancelReservationRequest;
use Modules\Client\Http\Requests\Reservations\RescheduleReservationRequest;
use Modules\Client\Reservations\Services\ReservationBookingService;
use Modules\Client\Reservations\Services\ReservationStatusTransitionService;
use Throwable;

/**
 * سير عمل الحجز — كلُّ انتقالٍ فعلٌ صريح بصلاحيته.
 *
 *   • صلاحيةٌ لكلّ فعل (`confirm` غير `cancel` غير `complete`).
 *   • مُدخلاتٌ تخصّ الفعلَ وحدَه (سببُ الإلغاء إلزاميّ).
 *
 * **ولا مالَ هنا**: لا فوترةَ تلقائية ولا رسمَ إلغاءٍ ولا ردّ — الفاتورةُ
 * تُصدَر من شاشة المبيعات بزرّ «تحويل إلى فاتورة»، والتحصيلُ والمرتجعُ
 * من المبيعات كأيّ فاتورة.
 *
 * والانتقالُ نفسُه في `ReservationStatusTransitionService`: يقفل الصفَّ ويفحص
 * الجوازَ والمُهَل ويختم الوقت ويكتب خطَّ الحياة ويطلق الحدث.
 */
class ReservationWorkflowController extends Controller
{
    use ScopesReservations;

    public function __construct(
        protected ReservationStatusTransitionService $transitions,
        protected ReservationBookingService $booking,
    ) {
        $this->middleware('permission:reservations.confirm', ['only' => ['confirm']]);
        $this->middleware('permission:reservations.checkin', ['only' => ['checkIn', 'noShow']]);
        $this->middleware('permission:reservations.start', ['only' => ['start']]);
        $this->middleware('permission:reservations.complete', ['only' => ['complete']]);
        $this->middleware('permission:reservations.cancel', ['only' => ['cancel', 'reject']]);
        $this->middleware('permission:reservations.reschedule', ['only' => ['reschedule']]);
    }

    public function confirm(Request $request, $id)
    {
        return $this->move($id, ReservationStatus::CONFIRMED, $request->input('note'), 'confirmed');
    }

    public function checkIn(Request $request, $id)
    {
        return $this->move($id, ReservationStatus::CHECKED_IN, $request->input('note'), 'checked_in');
    }

    public function start(Request $request, $id)
    {
        return $this->move($id, ReservationStatus::IN_PROGRESS, $request->input('note'), 'started');
    }

    public function complete(Request $request, $id)
    {
        return $this->move($id, ReservationStatus::COMPLETED, $request->input('note'), 'completed');
    }

    public function noShow(Request $request, $id)
    {
        return $this->move($id, ReservationStatus::NO_SHOW, $request->input('note'), 'no_show');
    }

    public function reject(Request $request, $id)
    {
        return $this->move($id, ReservationStatus::REJECTED, $request->input('note'), 'rejected');
    }

    /**
     * الإلغاء — حالةٌ وسبب. والمفوتَرُ لا يُلغى من هنا: فاتورتُه في المبيعات،
     * وعكسُها مرتجعُ مبيعات.
     */
    public function cancel(CancelReservationRequest $request, $id)
    {
        $reservation = $this->findScoped($id);
        $reason = (string) $request->input('reason');

        if ($reservation->invoice()->exists()) {
            return back()->with('error', __('reservations.errors.cancel_invoiced'));
        }

        try {
            $this->transitions->transition(
                $reservation,
                ReservationStatus::CANCELLED,
                $reason,
                ['cancellation_reason' => $reason, 'cancelled_by' => auth()->id()]
            );
        } catch (ReservationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->failMessage($e, 'cancel', $reservation));
        }

        return back()->with('success', __('reservations.messages.cancelled'));
    }

    public function reschedule(RescheduleReservationRequest $request, $id)
    {
        $reservation = $this->findScoped($id);

        try {
            $this->booking->reschedule(
                $reservation,
                $request->input('reservation_date'),
                $request->input('start_time'),
                $request->input('end_time'),
                $request->input('note'),
                $request->integer('employee_id') ?: null
            );
        } catch (ReservationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->failMessage($e, 'reschedule', $reservation));
        }

        return back()->with('success', __('reservations.messages.rescheduled'));
    }

    protected function move($id, ReservationStatus $target, ?string $note, string $messageKey)
    {
        $reservation = $this->findScoped($id);

        try {
            $this->transitions->transition($reservation, $target, $note);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->with('error', $this->failMessage($e, 'transition_'.$target->value, $reservation));
        }

        return back()->with('success', __('reservations.messages.'.$messageKey));
    }
}
