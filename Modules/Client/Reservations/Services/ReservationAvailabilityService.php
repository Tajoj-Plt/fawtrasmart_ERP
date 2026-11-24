<?php

namespace Modules\Client\Reservations\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationService;
use Modules\Client\Reservations\Models\ReservationSetting;

/**
 * هل يجوز هذا الموعد؟ — الحارسُ الوحيد، ولا يُلتفّ عليه.
 *
 * ═══════════════ الأسئلة ═══════════════
 *
 *   1. المدى     — أهو داخل نافذة الحجز المسموحة (من متى إلى متى)؟
 *   2. العطلة    — أهو عطلةٌ رسمية في الموارد البشرية؟
 *   3. الوردية   — أهو داخل وردية الموظّف في ذلك اليوم؟
 *   4. الإجازة   — أللموظّف إجازةٌ معتمدة في هذا اليوم؟
 *   5. التعارض   — أله حجزٌ آخر يتداخل مع هذا؟
 *   6. الحدّ     — أبلغت الخدمةُ أو المنشأةُ حدَّها اليومي؟
 *   7. التكرار   — أللعميل حجزٌ في اليوم نفسه والإعدادُ يمنعه؟
 *
 * **الدوامُ من «الورديات» في الموارد البشرية** (`EmployeeShiftSchedule`) لا
 * من جدولٍ في الحجوزات: الموظّفُ يُحجز داخل ورديته وحدَها، ومن لا وردية
 * له لا يُحجز له. وشاشةُ «أوقات العمل» التي كانت هنا أُزيلت (2026-09-10)،
 * وصفوفُها في `reservation_working_hours` باقيةٌ لا يقرؤها شيء.
 *
 * ═══════════════ التداخل: لماذا `<` و`>` لا `<=` و`>=` ═══════════════
 *
 * حجزٌ ينتهي 11:00 وآخرُ يبدأ 11:00 لا يتداخلان — وبـ`<=` كان يُمنع الحجزُ
 * المتلاصق كلُّه. ووقتا التحضير والتنظيف يُضافان إلى طرفَي الفترة.
 *
 * ═══════════════ والتزامن ═══════════════
 *
 * يُنادى `assertSlotAvailable()` **داخل** `DB::transaction` في
 * `ReservationBookingService` بعد قفل صفوف اليوم — فطلبان متزامنان لا
 * يحجزان الموعدَ نفسَه.
 */
class ReservationAvailabilityService
{
    public function __construct(protected EmployeeShiftSchedule $shifts) {}

    /**
     * يرمي `ReservationException` إن كان الموعد غير جائز — وإلّا يمرّ صامتاً.
     *
     * `$branchId` للحدّ اليومي وحده: الحجزُ لا يُختار له فرع، ويُنسب إلى فرع موظّفه.
     *
     * @param  int|null  $ignoreReservationId  الحجزُ الجاري تعديلُه لا يتعارض مع نفسه
     */
    public function assertSlotAvailable(
        $date,
        string $startTime,
        string $endTime,
        ?int $employeeId,
        ?int $branchId,
        ?ReservationService $service = null,
        ?int $ignoreReservationId = null,
        ?int $clientId = null
    ): void {
        $settings = ReservationSetting::current();
        $day = Carbon::parse($date)->startOfDay();
        $startTime = $this->pad($startTime);
        $endTime = $this->pad($endTime);

        $this->assertWithinBookingWindow($day, $startTime, $settings);

        if ($endTime <= $startTime) {
            throw ReservationException::because(__('reservations.errors.end_before_start'));
        }

        $this->assertDurationWithinLimits($startTime, $endTime, $settings);

        if (! $employeeId) {
            throw ReservationException::because(__('reservations.errors.employee_required'));
        }

        $this->assertNotHoliday($day);
        $this->assertWithinShift($day, $startTime, $endTime, $employeeId);
        $this->assertEmployeeNotOnLeave($day, $employeeId);

        /*
            تعطيلُ كشف التعارض قرارٌ صريح لمن يقبل الحجز الزائد. وما قبله من
            فحوصٍ يبقى سارياً: «حجزٌ زائد» لا تعني «حجزٌ في يوم عطلة».
        */
        if (! $settings->allow_overbooking) {
            $this->assertNoConflict($day, $startTime, $endTime, $employeeId, $service, $ignoreReservationId);
        }

        $this->assertDailyLimits($day, $branchId, $service, $ignoreReservationId, $settings);
        $this->assertNoDuplicateForClient($day, $clientId, $ignoreReservationId, $settings);
    }

    /** صيغةٌ لا ترمي — للواجهات التي تسأل قبل أن تُظهر الزرّ. */
    public function reasonSlotUnavailable(...$args): ?string
    {
        try {
            $this->assertSlotAvailable(...$args);

            return null;
        } catch (ReservationException $e) {
            return $e->getMessage();
        }
    }

    // ════════════════════ 1. نافذة الحجز ════════════════════

    protected function assertWithinBookingWindow(Carbon $day, string $startTime, ReservationSetting $settings): void
    {
        $startsAt = Carbon::parse($day->toDateString().' '.$startTime);

        $minAdvance = (int) $settings->min_advance_minutes;
        if ($minAdvance > 0 && $startsAt->lt(now()->addMinutes($minAdvance))) {
            throw ReservationException::because(__('reservations.errors.min_advance', ['minutes' => $minAdvance]));
        }

        // صفرٌ تعني «بلا مهلةٍ مسبقة» لا «بأثرٍ رجعي» — واليومُ نفسُه
        // لا يُحجز فيه وقتٌ مضى: المقارنةُ بـ`now()` لا ببداية اليوم.
        if ($minAdvance === 0 && $startsAt->lt(now())) {
            throw ReservationException::because(
                $startsAt->isToday()
                    ? __('reservations.errors.past_time')
                    : __('reservations.errors.past_date')
            );
        }

        $maxDays = (int) $settings->max_advance_days;
        if ($maxDays > 0 && $day->gt(now()->startOfDay()->addDays($maxDays))) {
            throw ReservationException::because(__('reservations.errors.max_advance', ['days' => $maxDays]));
        }
    }

    protected function assertDurationWithinLimits(string $startTime, string $endTime, ReservationSetting $settings): void
    {
        $minutes = $this->minutesBetween($startTime, $endTime);

        if ($minutes < (int) $settings->min_duration) {
            throw ReservationException::because(__('reservations.errors.duration_min', ['minutes' => $settings->min_duration]));
        }

        if ($minutes > (int) $settings->max_duration) {
            throw ReservationException::because(__('reservations.errors.duration_max', ['minutes' => $settings->max_duration]));
        }
    }

    // ════════════════════ 2. العطلة الرسمية ════════════════════

    protected function assertNotHoliday(Carbon $day): void
    {
        if ($name = $this->shifts->holidayOn($day)) {
            throw ReservationException::because(__('reservations.errors.holiday', ['name' => $name]));
        }
    }

    // ════════════════════ 3. الوردية ════════════════════

    protected function assertWithinShift(Carbon $day, string $startTime, string $endTime, int $employeeId): void
    {
        $windows = $this->shifts->windowsFor($employeeId, $day);

        if ($windows === null) {
            throw ReservationException::because(__('reservations.errors.no_shift', [
                'name' => Employee::query()->whereKey($employeeId)->first()?->full_name ?? '#'.$employeeId,
            ]));
        }

        if ($windows->isEmpty()) {
            throw ReservationException::because(__('reservations.errors.closed_day'));
        }

        foreach ($windows as $window) {
            if ($startTime >= $window['start'] && $endTime <= $window['end']) {
                return;
            }
        }

        $label = $windows->map(fn ($w) => substr($w['start'], 0, 5).'—'.substr($w['end'], 0, 5))->implode('، ');

        throw ReservationException::because(__('reservations.errors.outside_hours', ['hours' => $label]));
    }

    /**
     * نافذةُ دوام الموظّف ليومٍ بعينه — من ورديته.
     *
     * @return Collection<int, array{start:string,end:string}>|null
     */
    public function workingWindows(Carbon $day, ?int $employeeId): ?Collection
    {
        return $employeeId ? $this->shifts->windowsFor($employeeId, $day) : null;
    }

    // ════════════════════ 4. الإجازات ════════════════════

    protected function assertEmployeeNotOnLeave(Carbon $day, int $employeeId): void
    {
        $onLeave = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $day->toDateString())
            ->whereDate('end_date', '>=', $day->toDateString())
            ->exists();

        if ($onLeave) {
            throw ReservationException::because(__('reservations.errors.on_leave'));
        }
    }

    // ════════════════════ 5. التعارض ════════════════════

    protected function assertNoConflict(
        Carbon $day,
        string $startTime,
        string $endTime,
        int $employeeId,
        ?ReservationService $service,
        ?int $ignoreReservationId
    ): void {
        /*
            الطرفان يُقارَنان بفترتَيهما **المحجوزتين** (مع التحضير والتنظيف)
            — `blocked_start`/`blocked_end` مخزَّنةٌ مع كلّ حجز، و`COALESCE`
            احتياطٌ لصفوفٍ سبقت العمودين.
        */
        $windowStart = $this->shift($startTime, -(int) ($service?->buffer_before ?? 0));
        $windowEnd = $this->shift($endTime, (int) ($service?->buffer_after ?? 0));

        $conflict = Reservation::query()
            ->occupying()
            ->whereDate('reservation_date', $day->toDateString())
            ->where('employee_id', $employeeId)
            ->when($ignoreReservationId, fn ($q) => $q->where('id', '!=', $ignoreReservationId))
            ->whereRaw('COALESCE(blocked_start, start_time) < ?', [$windowEnd])
            ->whereRaw('COALESCE(blocked_end, end_time) > ?', [$windowStart])
            ->first();

        if ($conflict) {
            throw ReservationException::because(
                __('reservations.errors.employee_busy', [
                    'from' => substr((string) $conflict->start_time, 0, 5),
                    'to' => substr((string) $conflict->end_time, 0, 5),
                ]),
                ['conflict_id' => $conflict->id, 'conflict_code' => $conflict->code]
            );
        }
    }

    // ════════════════════ 6. الحدود اليومية ════════════════════

    protected function assertDailyLimits(
        Carbon $day,
        ?int $branchId,
        ?ReservationService $service,
        ?int $ignoreReservationId,
        ReservationSetting $settings
    ): void {
        $base = Reservation::query()
            ->occupying()
            ->whereDate('reservation_date', $day->toDateString())
            ->when($ignoreReservationId, fn ($q) => $q->where('id', '!=', $ignoreReservationId));

        $max = (int) $settings->max_daily_reservations;

        if ($max > 0) {
            $count = (clone $base)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();

            if ($count >= $max) {
                throw ReservationException::because(__('reservations.errors.branch_daily_limit', ['max' => $max]));
            }
        }

        if ($service && $service->max_daily) {
            $count = (clone $base)
                ->when($service->product_id,
                    fn ($q) => $q->where('product_id', $service->product_id),
                    fn ($q) => $q->where('service_id', $service->id))
                ->count();

            if ($count >= (int) $service->max_daily) {
                throw ReservationException::because(__('reservations.errors.service_daily_limit', [
                    'service' => $service->displayName(),
                    'max' => $service->max_daily,
                ]));
            }
        }
    }

    // ════════════════════ 7. تكرار العميل ════════════════════

    protected function assertNoDuplicateForClient(
        Carbon $day,
        ?int $clientId,
        ?int $ignoreReservationId,
        ReservationSetting $settings
    ): void {
        if (! $clientId || $settings->allow_repeated_booking) {
            return;
        }

        $exists = Reservation::query()
            ->occupying()
            ->whereDate('reservation_date', $day->toDateString())
            ->where('client_id', $clientId)
            ->when($ignoreReservationId, fn ($q) => $q->where('id', '!=', $ignoreReservationId))
            ->exists();

        if ($exists) {
            throw ReservationException::because(__('reservations.errors.client_duplicate'));
        }
    }

    // ════════════════════ الأوقات المتاحة ════════════════════

    /**
     * الأوقاتُ الشاغرة لموظّفٍ في يوم — داخل ورديته، بخطوة `slot_interval`،
     * وكلُّ وقتٍ يمرّ على الحارس نفسِه فلا تعرض الشاشةُ وقتاً يرفضه الحفظ.
     *
     * @return array<int, array{start:string,end:string}>
     */
    public function availableSlots(
        $date,
        ?ReservationService $service,
        ?int $employeeId,
        ?int $branchId = null,
        ?int $ignoreReservationId = null
    ): array {
        if (! $employeeId) {
            return [];
        }

        $settings = ReservationSetting::current();
        $day = Carbon::parse($date)->startOfDay();

        $duration = (int) ($service?->duration ?: $settings->default_duration);
        $step = max(5, (int) $settings->slot_interval);

        $windows = $this->shifts->windowsFor($employeeId, $day) ?? collect();
        $slots = [];

        foreach ($windows as $window) {
            $cursor = $window['start'];

            while ($this->shift($cursor, $duration) <= $window['end']) {
                $end = $this->shift($cursor, $duration);

                $reason = $this->reasonSlotUnavailable(
                    $day, $cursor, $end, $employeeId, $branchId, $service, $ignoreReservationId, null
                );

                if ($reason === null) {
                    $slots[] = ['start' => substr($cursor, 0, 5), 'end' => substr($end, 0, 5)];
                }

                $next = $this->shift($cursor, $step);

                if ($next <= $cursor) {
                    break;
                }

                $cursor = $next;
            }
        }

        return $slots;
    }

    /**
     * الموظفون الذين يُحجز لهم: **من له وردية** في الموارد البشرية، ومن قسمِ
     * الإعدادات أو مسمّاها إن ضُبط أحدهما، والمؤهَّلون
     * للخدمة إن قُصرت على بعضهم (القائمةُ الفارغة تعني «الجميع»).
     *
     * لا فرعَ هنا: الموظّفُ قد يتبع أكثر من فرع، والحجزُ لا يُختار له فرع.
     *
     * @return Collection<int, Employee>
     */
    public function eligibleEmployees(?ReservationService $service, ?int $branchId = null): Collection
    {
        $query = $this->shifts->constrainToShifted(Employee::query()->orderBy('first_name'));

        // القسمُ أو المسمّى المضبوط في «إعدادات الحجوزات ← الموظفون»
        ReservationSetting::current()->constrainEmployees($query);

        if ($service && $service->exists) {
            $ids = $service->employees()->pluck('employees.id')->all();

            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
        }

        return $query->get();
    }

    // ════════════════════ أدوات الوقت ════════════════════

    /** `HH:MM` ← `HH:MM:SS`، فتصحّ المقارنةُ النصّية بين الصيغتين. */
    protected function pad($time): string
    {
        $time = substr((string) $time, 0, 8);

        return strlen($time) === 5 ? $time.':00' : $time;
    }

    /** إزاحةُ وقتٍ بالدقائق، مقصوصةً على حدود اليوم. */
    protected function shift(string $time, int $minutes): string
    {
        $seconds = $this->toSeconds($time) + ($minutes * 60);
        $seconds = max(0, min($seconds, 86399));

        return gmdate('H:i:s', $seconds);
    }

    protected function minutesBetween(string $from, string $to): int
    {
        return (int) round(($this->toSeconds($to) - $this->toSeconds($from)) / 60);
    }

    protected function toSeconds(string $time): int
    {
        [$h, $m, $s] = array_pad(array_map('intval', explode(':', $this->pad($time))), 3, 0);

        return ($h * 3600) + ($m * 60) + $s;
    }
}
