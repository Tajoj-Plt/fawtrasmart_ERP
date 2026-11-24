<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * حالةُ يومٍ لمجموعةِ موظفين — تقرؤها كلُّ شاشةٍ تعرض «من أين نحن اليوم».
 *
 * سجلُّ اليوم الموحَّد ولوحةُ متابعة الفريق يطرحان السؤال نفسَه على
 * مجموعتين مختلفتين. وكتابةُ الجواب مرّتين تعني أن تُصبح «غائب» في
 * شاشةٍ «لم يُسجِّل بعد» في الأخرى بعد أول تعديل — وهما حالتان لا
 * تحتملان الخلاف: إحداهما حكمٌ والأخرى انتظار.
 *
 * وما يجعلها طبقةً لا دالّةً في متحكّم: الورديات والإعدادات والعطل
 * تُحمَّل **مرّةً واحدة** لكل الصفحة. `AttendanceProcessorService::describeDay`
 * تستعلم عن الوردية والإعدادات في كل نداء — صحيحةٌ لموظفٍ واحد،
 * وكارثةٌ لمئتين في صفحة.
 */
class DailyAttendanceBoard
{
    public const STATE_COMPLETED = 'completed';

    public const STATE_PRESENT = 'present';

    public const STATE_PENDING = 'pending';

    public const STATE_ABSENT = 'absent';

    public const STATE_LEAVE = 'leave';

    public const STATE_HOLIDAY = 'holiday';

    public const STATE_WEEKLY_OFF = 'weekly_off';

    /** في استراحةٍ الآن — حضر ولم ينصرف، وبدأ استراحةً لم يُنهها */
    public const STATE_ON_BREAK = 'on_break';

    /** انصرف بعد دوامٍ أقلّ من نصف المطلوب */
    public const STATE_HALF_DAY = 'half_day';

    /** انصرف بعد دقائق — دوامٌ أقلّ من الحدّ فاحتُسب غياباً */
    public const STATE_SHORT = 'short';

    /** @return array<string, string> */
    public static function stateLabels(): array
    {
        return [
            self::STATE_COMPLETED => 'مكتمل',
            self::STATE_HALF_DAY => 'نصف يوم',
            self::STATE_SHORT => 'دوام ناقص — غياب',
            self::STATE_PRESENT => 'حاضر الآن',
            self::STATE_ON_BREAK => 'في استراحة',
            self::STATE_PENDING => 'لم يسجّل بعد',
            self::STATE_ABSENT => 'غائب',
            self::STATE_LEAVE => 'إجازة',
            self::STATE_HOLIDAY => 'عطلة رسمية',
            self::STATE_WEEKLY_OFF => 'عطلة أسبوعية',
        ];
    }

    /**
     * صفٌّ لكل موظف — بما فيهم من لا سجلَّ له.
     *
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, array<string, mixed>>
     */
    public function build(Collection $employees, Carbon $date): Collection
    {
        if ($employees->isEmpty()) {
            return collect();
        }

        $ids = $employees->pluck('id');

        /*
            `deduction` معها: أثرُ اليوم على الراتب يُقرأ من الشاشة نفسِها.
            «متأخر ٤٠ دقيقة» و«متأخر ٤٠ دقيقة وخُصم عليها» حالتان مختلفتان
            عند من يراجع قبل الإغلاق، وكانتا تُعرضان سواءً.
        */
        $days = AttendanceDays::with([
            'checkInGeofence:id,name', 'checkOutGeofence:id,name',
            'deduction:id,amount,deduction_type,status',
        ])
            ->whereIn('employee_id', $ids)
            ->whereDate('attendance_date', $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        $leaves = LeaveRequest::whereIn('employee_id', $ids)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        /*
            حركاتُ اليوم المقبولة لكلّ موظف — حضور، استراحة، انصراف — بترتيبها.
            كانت الشاشةُ تعرض الحضورَ والانصرافَ وحدهما، فلا يُعرف متى خرج
            للاستراحة ومتى عاد، ولا من أين سجّل كلَّ حركة.
        */
        $timeline = AttendancePunch::whereIn('employee_id', $ids)
            ->whereDate('attendance_date', $date->toDateString())
            ->where('result', AttendancePunch::RESULT_ACCEPTED)
            ->orderBy('punched_at')
            ->get(['employee_id', 'punch_type', 'punched_at', 'applied_time', 'channel'])
            ->groupBy('employee_id');

        $shifts = Shift::with('days')->get()->keyBy('id');
        $settings = AttendanceSetting::current();
        $weeklyOff = $this->isWeeklyOff($date, $settings);

        return $employees->map(function (Employee $employee) use (
            $days, $leaves, $shifts, $settings, $date, $weeklyOff, $timeline
        ) {
            $day = $days->get($employee->id);
            [$expectedIn, $expectedOut] = $this->expectedWindow($employee, $day, $shifts, $settings, $date);
            $isHoliday = Holiday::isHoliday($date, $employee);

            return [
                'employee' => $employee,
                'day' => $day,
                'expected_in' => $expectedIn,
                'expected_out' => $expectedOut,
                'check_in' => $day?->check_in_time,
                'check_out' => $day?->check_out_time,
                'channel' => $day ? $this->channelLabel($day) : null,
                'location' => $day ? $this->locationLabel($day) : null,
                'map_url' => ($day && $day->checkin_latitude !== null && $day->checkin_longitude !== null)
                    ? 'https://www.google.com/maps?q='.((float) $day->checkin_latitude).','.((float) $day->checkin_longitude)
                    : null,
                // صافي العمل: من الحضور إلى الانصراف ناقصاً الاستراحات
                'working_minutes' => $this->netMinutes($day, $date),
                'still_working' => (bool) ($day?->check_in_time && ! $day?->check_out_time && $date->isToday()),
                'break_minutes' => (int) ($day?->break_minutes ?? 0),
                'on_break' => (bool) $day?->break_started_at,
                'flexible' => ($employee->attendanceConfig?->work_mode ?? 'shift') === 'flexible',
                // ساعاتُ الدوام المرن الفعلية: ساعاتُ الموظف، وإلّا ساعاتُ الإعدادات العامة
                'flexible_hours' => (float) ($employee->attendanceConfig?->work_hours_per_day ?: ($settings->work_hours_per_day ?: 8)),
                'status' => $day?->status,
                'timeline' => $timeline->get($employee->id, collect())->map(fn (AttendancePunch $p) => [
                    'type' => $p->punch_type,
                    'time' => substr((string) ($p->applied_time ?: $p->punched_at->format('H:i:s')), 0, 5),
                ])->values()->all(),
                'late_minutes' => (int) ($day?->late_minutes ?? 0),
                'early_leave_minutes' => (int) ($day?->early_leave_minutes ?? 0),
                'overtime_minutes' => (int) ($day?->overtime_minutes ?? 0),
                'capped' => (bool) ($day?->check_out_capped ?? false),
                'deduction' => $day?->deduction,
                /*
                    «متأخر» تعني تجاوزَ السماحية لا مجرّدَ دقيقةٍ بعد الموعد.
                    `late_minutes` تُسجَّل خاماً دائماً — فمن حضر 08:05
                    ووردِيتُه 08:00 بسماحٍ 15 دقيقةً ليس متأخراً، وعدُّه
                    كذلك يُغرق لوحةَ المدير بأسماءٍ لا شأن له بها.
                */
                'is_late' => $day?->status === AttendanceDays::STATUS_LATE,
                'state' => $this->state(
                    $day, $leaves->get($employee->id), $date, $expectedIn, $isHoliday, $weeklyOff
                ),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function summarise(Collection $rows): array
    {
        $counts = array_fill_keys(array_keys(self::stateLabels()), 0);

        foreach ($rows as $row) {
            $counts[$row['state']] = ($counts[$row['state']] ?? 0) + 1;
        }

        $counts['late'] = $rows->where('is_late', true)->count();
        $counts['total'] = $rows->count();

        return $counts;
    }

    // ════════════════════════════════════════════════════

    /**
     * الوردية المتوقَّعة لهذا اليوم.
     *
     * لقطةُ صفّ اليوم أَولى حين وُجدت: هي ما قِيس عليه الختمُ فعلاً، وقد
     * تُعدَّل الورديةُ بعدها فيصير الحسابُ القديم غيرَ مفهومٍ بمعطياته.
     * وإلّا فالترتيبُ نفسُه في `AttendanceProcessorService`: إعدادُ الموظف
     * ← صفُّ الوردية ← افتراضُ المنشأة.
     *
     * @param  Collection<int, Shift>  $shifts
     * @return array{0: ?string, 1: ?string}
     */
    private function expectedWindow(
        Employee $employee,
        ?AttendanceDays $day,
        Collection $shifts,
        AttendanceSetting $settings,
        Carbon $date,
    ): array {
        if ($day?->expected_check_in) {
            return [$day->expected_check_in, $day->expected_check_out];
        }

        // نفسُ مسار المسح والمعالج: وردية مخصَّصة ← دائمة ← إعداد موظف ← افتراضي
        $described = app(\App\Services\AttendanceProcessorService::class)->describeDay($employee, $date);

        return [
            $described['expected_check_in']?->format('H:i:s'),
            $described['expected_check_out']?->format('H:i:s'),
        ];
    }

    private function channelLabel(AttendanceDays $day): ?string
    {
        /*
            صفُّ يومٍ قد يُنشَأ بلا ختمٍ إطلاقاً — يُولّده الكرون للغياب،
            وأعمدةُ الوسيلة فيه تحمل قيمتَها الافتراضية `manual`. فعرضُها
            يقول «سُجِّل يدوياً» عن يومٍ لم يُسجَّل فيه شيء.
        */
        if (! $day->check_in_time && ! $day->check_out_time) {
            return null;
        }

        $channel = $day->check_in_channel ?? $day->check_out_channel;
        $method = $day->check_in_method ?? $day->check_out_method;

        $channels = ['kiosk' => 'جهاز الفرع', 'field' => 'الجوال الميداني', 'manual' => 'إدخال يدوي'];
        $methods = ['barcode' => 'بطاقة', 'qr' => 'QR', 'geo' => 'موقع', 'manual' => 'يدوي'];

        // قناةٌ غيرُ مسجَّلة تعني صفّاً سابقاً لمسار الختم — يُقال ذلك ولا يُخمَّن
        return ($channels[$channel] ?? 'غير مسجَّلة').' · '.($methods[$method] ?? '—');
    }

    private function locationLabel(AttendanceDays $day): ?string
    {
        $parts = [];

        if ($day->checkInGeofence) {
            $parts[] = $day->checkInGeofence->name
                .($day->check_in_distance_meters !== null ? ' — '.number_format($day->check_in_distance_meters).' م' : '');
        }

        // الإحداثياتُ الخام لا تُقرأ — تُعرض رابطاً للخريطة (`map_url`) بدل رقمين طويلين
        if ($parts === [] && $day->checkin_latitude !== null && $day->checkin_longitude !== null) {
            $parts[] = 'موقع الجوال';
        }

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        // لا إحداثيات: الشبكةُ هي كلُّ ما يُعرَف عن المكان — ويُقال كذلك
        return $day->ip_address ? 'شبكة '.$day->ip_address : null;
    }

    /**
     * حالةُ اليوم — وفيها ما لا يظهر في `attendance_days.status`: التمييزُ
     * بين «غائب» و«لم يُسجِّل بعد». الأولى حكمٌ، والثانيةُ انتظار — وخلطُهما
     * صباحاً يُنذر بغيابٍ لم يقع.
     */
    private function state(
        ?AttendanceDays $day,
        ?LeaveRequest $leave,
        Carbon $date,
        ?string $expectedIn,
        bool $isHoliday,
        bool $weeklyOff,
    ): string {
        /*
            «مكتمل» كان يُكتب لكلّ من له انصراف — ولو انصرف بعد دقائق فاحتُسب
            يومُه غياباً أو نصفَ يوم. الحالةُ الآن من حكم المعالج على اليوم.
        */
        if ($day?->check_out_time) {
            return match ($day->status) {
                AttendanceDays::STATUS_ABSENT => self::STATE_SHORT,
                AttendanceDays::STATUS_HALF_DAY => self::STATE_HALF_DAY,
                default => self::STATE_COMPLETED,
            };
        }

        if ($day?->check_in_time) {
            return $day->break_started_at ? self::STATE_ON_BREAK : self::STATE_PRESENT;
        }

        if ($leave) {
            return self::STATE_LEAVE;
        }

        if ($isHoliday) {
            return self::STATE_HOLIDAY;
        }

        if ($weeklyOff) {
            return self::STATE_WEEKLY_OFF;
        }

        // اليومُ الجاري وما زال الوقتُ قبل بداية الوردية: انتظارٌ لا غياب
        if ($date->isToday() && $expectedIn
            && Carbon::now()->lt(Carbon::parse($date->toDateString().' '.$expectedIn))) {
            return self::STATE_PENDING;
        }

        return $date->isFuture() ? self::STATE_PENDING : self::STATE_ABSENT;
    }

    /**
     * صافي العمل: من الحضور إلى الانصراف ناقصاً الاستراحات. ومن لم ينصرف
     * اليوم بعد: حتى الآن — كان يظهر صفراً طوال دوامه.
     */
    private function netMinutes(?AttendanceDays $day, Carbon $date): int
    {
        if (! $day || ! $day->check_in_time) {
            return 0;
        }

        if ($day->check_out_time || ! $date->isToday()) {
            return max(0, (int) $day->working_hours - (int) $day->break_minutes);
        }

        $gross = (int) Carbon::parse($date->toDateString().' '.$day->check_in_time)->diffInMinutes(Carbon::now(), false);
        $breaks = (int) $day->break_minutes + ($day->break_started_at ? (int) $day->break_started_at->diffInMinutes(Carbon::now(), false) : 0);

        return max(0, $gross - $breaks);
    }

    private function isWeeklyOff(Carbon $date, AttendanceSetting $settings): bool
    {
        $offDays = $settings->default_weekly_off_days ?? ['fri'];

        return in_array(substr(strtolower($date->format('D')), 0, 3), array_map('strtolower', $offDays), true);
    }
}
