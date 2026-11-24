<?php

namespace Modules\Client\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Clients\ClientPortalNotifier;
use App\Models\Client;
use App\Models\notifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Client\Http\Controllers\Concerns\GuardsClientPortal;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationService;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Services\ReservationAvailabilityService;
use Modules\Client\Reservations\Services\ReservationBookingService;
use Modules\Client\Reservations\Services\ServiceProductCatalog;
use Throwable;

/**
 * حجزُ العميل موعدَه بنفسه من البوابة.
 *
 * ═══════════ لا محرّكَ حجزٍ ثانٍ ═══════════
 *
 * الشاشةُ واجهةٌ فقط: الأوقاتُ المتاحة من `ReservationAvailabilityService`
 * والحفظُ من `ReservationBookingService::create` — نفسُ ما تستعمله شاشةُ
 * الموظّف. فالدوامُ والعطلُ والتداخلُ والحدُّ اليومي ومهلةُ الحجز المسبق
 * تُطبَّق على العميل كما تُطبَّق على الموظّف، بلا قاعدةٍ تُنسى هنا.
 *
 * ═══════════ «بانتظار التأكيد» دائماً ═══════════
 *
 * (طلب المستخدم 2026-09-18.) الحالةُ تُفرض هنا ولا تُقرأ من الطلب ولا من
 * `default_status` في الإعدادات: حجزٌ يؤكّده صاحبُه بنفسه يحجز وقتَ موظّفٍ
 * بلا موافقة أحد. والموظّفُ يؤكّده من شاشة الحجوزات كأيّ حجز.
 *
 * ═══════════ ولا مال ═══════════
 *
 * لا عربونَ ولا دفعَ عند الحجز — المالُ كلُّه على فاتورة المبيعات بعد
 * تأدية الخدمة. وسدادُ الفواتير من البوابة بابُه
 * `PortalPaymentNoticeController`.
 */
class PortalReservationController extends Controller
{
    use GuardsClientPortal;

    /** مفتاحُ «صلاحيات بوابة العميل» الذي يفتح هذه الشاشات */
    private const PERMISSION = 'book_reservations';

    public function __construct(
        private readonly ReservationBookingService $booking,
        private readonly ReservationAvailabilityService $availability,
    ) {}

    /** حجوزاتُ العميل — القادمةُ أوّلاً ثمّ ما مضى. */
    public function index()
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $reservations = Reservation::query()
            ->where('client_id', $clientId)
            ->with(['product', 'employee'])
            ->orderByDesc('reservation_date')
            ->orderByDesc('start_time')
            ->paginate(15);

        return view('client::portal.reservations', [
            'portalClient' => Client::find($clientId),
            'reservations' => $reservations,
        ]);
    }

    /** نموذجُ «احجز موعداً»: الخدمةُ ثمّ اليومُ ثمّ الوقتُ المتاح. */
    public function create()
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $settings = ReservationSetting::current();

        return view('client::portal.reservation_create', [
            'portalClient' => Client::find($clientId),
            'services' => ServiceProductCatalog::options(),
            // حدودُ التقويم من الإعدادات نفسِها التي يفرضها الحارس عند الحفظ،
            // فلا يختار العميلُ يوماً يُردّ فيه
            'minDate' => today()->toDateString(),
            'maxDate' => today()->addDays(max(1, (int) $settings->max_advance_days))->toDateString(),
        ]);
    }

    /**
     * الأوقاتُ المتاحة ليومٍ وخدمة — لكلّ وقتٍ موظّفُه.
     *
     * العميلُ لا يختار موظّفاً: الاختيارُ وقتٌ، والموظّفُ يُشتقّ منه (أوّلُ
     * مؤهَّلٍ فارغٍ في ذلك الوقت). ومنتقي الموظفين في شاشة البوابة يفضح
     * جدولَ موظّفينا للعملاء بلا حاجة.
     */
    public function slots(Request $request): JsonResponse
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return response()->json(['success' => false, 'message' => 'غير متاح'], 403);
        }

        $request->validate([
            'product_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        try {
            $product = ServiceProductCatalog::resolve($request->input('product_id'));
        } catch (ReservationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $service = ReservationService::profileFor($product);
        $date = Carbon::parse($request->input('date'))->startOfDay();

        /* تجميعُ أوقات كلّ الموظفين المؤهَّلين في قائمةٍ واحدة: الوقتُ
           الواحد يُعرض مرّةً ولو أتاحه ثلاثةٌ — العميلُ يحجز وقتاً لا
           موظّفاً، وأوّلُ من أتاحه يأخذه. */
        $slots = [];

        foreach ($this->availability->eligibleEmployees($service) as $employee) {
            $branchId = $this->booking->branchForEmployee($employee->id);

            foreach ($this->availability->availableSlots($date, $service, $employee->id, $branchId) as $slot) {
                $key = $slot['start'];

                if (! isset($slots[$key])) {
                    $slots[$key] = [
                        'start' => $slot['start'],
                        'end' => $slot['end'],
                        'employee_id' => $employee->id,
                    ];
                }
            }
        }

        ksort($slots);

        return response()->json([
            'success' => true,
            'duration' => (int) $service->duration,
            'slots' => array_values($slots),
        ]);
    }

    /** حفظُ الحجز — «بانتظار التأكيد» دائماً. */
    public function store(Request $request)
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'reservation_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            // يأتي من نداء الأوقات المتاحة — ويُعاد التحقّق منه في الحفظ
            'employee_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'product_id' => 'الخدمة',
            'reservation_date' => 'التاريخ',
            'start_time' => 'الوقت',
        ]);

        try {
            $reservation = $this->booking->create([
                'product_id' => $data['product_id'],
                'client_id' => $clientId,
                'employee_id' => $data['employee_id'],
                'reservation_date' => $data['reservation_date'],
                'start_time' => $data['start_time'],
                'notes' => $data['notes'] ?? null,
                // انظر ترويسة الصنف: الحالةُ تُفرض ولا تُقرأ من الطلب
                'status' => ReservationStatus::PENDING->value,
                'source' => 'portal',
            ]);
        } catch (ReservationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('تعذّر حفظ حجز البوابة', ['client' => $clientId, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'تعذّر تسجيل الحجز. حاول مرّةً أخرى أو تواصل معنا.');
        }

        $this->notifyStaff($reservation);

        return redirect()
            ->route('clients.portal.reservations.index')
            ->with('success', 'تم استلام طلب الحجز '.$reservation->code.'. سنؤكّده لك قريباً.');
    }

    /**
     * إشعارُ **موظّف العميل المسند** بطلب الحجز (طلب المستخدم 2026-09-19)؛
     * وإن لم يكن له حسابُ دخول بقيت الرؤيةُ بصلاحيات قائمة الإشعارات.
     */
    private function notifyStaff(Reservation $reservation): void
    {
        ClientPortalNotifier::notify(
            $reservation->client ?? $reservation->client_id,
            'reservation_portal_request',
            'طلب حجز جديد من بوابة العميل',
            'طلب العميل '.($reservation->client?->trade_name ?? '#'.$reservation->client_id)
                .' حجزاً ('.$reservation->code.') بتاريخ '
                .Carbon::parse($reservation->reservation_date)->format('Y-m-d')
                .' الساعة '.substr((string) $reservation->start_time, 0, 5).' — بانتظار التأكيد.',
        );
    }
}
