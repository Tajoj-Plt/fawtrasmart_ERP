<?php

namespace Modules\Client\Reservations\Services;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * دوامُ الموظّف من «الورديات» في الموارد البشرية — لا جدولَ دوامٍ في الحجوزات.
 *
 * ═══════════ لماذا ═══════════
 *
 * كانت للحجوزات شاشةُ «أوقات عمل» خاصّة، والمنشأةُ تضبط دوامَ موظفيها في
 * الموارد البشرية بالورديات. فالدوامُ يُكتب مرّتين ويفترق. قال المستخدم:
 * «عندنا ورديات في النظام في قسم الـHR — ما يحتاج أوقات عمل هنا».
 *
 * ═══════════ من أين يُقرأ ═══════════
 *
 *   1. ورديةٌ مخصّصة (`custom_shifts`) تغطّي التاريخ والموظّفُ فيها
 *      (`custom_shifts_employees`) — تتقدّم لأنها استثناءٌ مقصود لفترة.
 *   2. وإلّا ورديتُه الأساسية `employees.shift_id`.
 *   3. يومُ الوردية من `shift_days` بالاسم الإنجليزي (`sunday`…): `working_day`
 *      يقول هل يعمل، و`start_time`/`end_time` نافذتُه.
 *
 * والعطلُ من «العطلات» في الموارد البشرية (`Holiday::findForDate`)، والإجازاتُ
 * المعتمدة يقرؤها حارسُ التوفّر من `leave_requests` كما كان.
 *
 * ═══════════ ثلاثُ إجابات لا اثنتان ═══════════
 *
 *   `null`          ← لا ورديةَ للموظّف أصلاً (يُرفض الحجز برسالةٍ تقول ذلك).
 *   مجموعةٌ فارغة   ← له ورديةٌ وهذا يومُ راحته.
 *   نافذةٌ          ← يعمل من … إلى ….
 */
class EmployeeShiftSchedule
{
    /** @var array<string, Collection|null> */
    protected array $windows = [];

    protected ?bool $customShifts = null;

    /** الوردية السارية على الموظّف في يومٍ بعينه. */
    public function shiftIdFor(int $employeeId, Carbon $day): ?int
    {
        if ($this->hasCustomShifts()) {
            $custom = DB::table('custom_shifts as cs')
                ->join('custom_shifts_employees as ce', 'ce.custom_shifts_id', '=', 'cs.id')
                ->where('ce.employee_id', $employeeId)
                ->whereDate('cs.from_date', '<=', $day->toDateString())
                ->whereDate('cs.to_date', '>=', $day->toDateString())
                ->whereNotNull('cs.shift_id')
                ->orderByDesc('cs.id')
                ->value('cs.shift_id');

            if ($custom) {
                return (int) $custom;
            }
        }

        // `DB` لا النموذج: نطاقاتُ الموظفين العامّة لا تُخفي ورديةَ من يُحجز له
        $shiftId = DB::table('employees')->where('id', $employeeId)->value('shift_id');

        return $shiftId ? (int) $shiftId : null;
    }

    /**
     * نافذةُ دوام الموظّف في يوم — انظر ترويسة الصنف للإجابات الثلاث.
     *
     * @return Collection<int, array{start: string, end: string}>|null
     */
    public function windowsFor(int $employeeId, Carbon $day): ?Collection
    {
        $key = $employeeId.'|'.$day->toDateString();

        if (array_key_exists($key, $this->windows)) {
            return $this->windows[$key];
        }

        $shiftId = $this->shiftIdFor($employeeId, $day);

        if (! $shiftId) {
            return $this->windows[$key] = null;
        }

        $row = DB::table('shift_days')
            ->where('shift_id', $shiftId)
            ->where('day', strtolower($day->englishDayOfWeek))
            ->first();

        if (! $row || ! $row->working_day || ! $row->start_time || ! $row->end_time) {
            return $this->windows[$key] = collect();
        }

        $start = $this->pad($row->start_time);
        $end = $this->pad($row->end_time);

        // ورديةٌ تعبر منتصف الليل تُقصّ عند نهاية اليوم — الحجزُ في يومٍ واحد
        if ($end <= $start) {
            $end = '23:59:59';
        }

        return $this->windows[$key] = collect([['start' => $start, 'end' => $end]]);
    }

    /**
     * ورديةُ الموظّف في يومٍ كما تُعرض في نموذج الحجز والتقويم.
     *
     * @return array{has_shift: bool, name: ?string, working: bool, start: ?string, end: ?string, holiday: ?string}
     */
    public function describe(int $employeeId, Carbon $day): array
    {
        $shiftId = $this->shiftIdFor($employeeId, $day);
        $window = $this->windowsFor($employeeId, $day)?->first();

        return [
            'has_shift' => $shiftId !== null,
            'name' => $shiftId ? DB::table('shifts')->where('id', $shiftId)->value('name') : null,
            'working' => $window !== null,
            'start' => $window ? substr($window['start'], 0, 5) : null,
            'end' => $window ? substr($window['end'], 0, 5) : null,
            'holiday' => $this->holidayOn($day),
        ];
    }

    /** اسمُ العطلة الرسمية في هذا اليوم من الموارد البشرية، أو `null`. */
    public function holidayOn(Carbon $day): ?string
    {
        if (! Schema::hasTable('holidays')) {
            return null;
        }

        $holiday = Holiday::findForDate($day);

        if (! $holiday) {
            return null;
        }

        return (string) ($holiday->named ?: ($holiday->holidayList?->name ?: __('reservations.errors.hr_holiday')));
    }

    /**
     * يقصر استعلامَ الموظفين على من له وردية — أساسيةٌ، أو مخصّصةٌ لم تنتهِ.
     */
    public function constrainToShifted(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNotNull('shift_id');

            if ($this->hasCustomShifts()) {
                $q->orWhereIn('id', DB::table('custom_shifts_employees as ce')
                    ->join('custom_shifts as cs', 'cs.id', '=', 'ce.custom_shifts_id')
                    ->whereDate('cs.to_date', '>=', now()->toDateString())
                    ->select('ce.employee_id'));
            }
        });
    }

    /** عددُ الموظفين الذين لهم وردية — لفحص الجاهزية. */
    public function shiftedEmployeesCount(?array $visibleIds = null): int
    {
        return $this->constrainToShifted(\App\Models\Employee::query())
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('id', $visibleIds))
            ->count();
    }

    protected function hasCustomShifts(): bool
    {
        return $this->customShifts ??= Schema::hasTable('custom_shifts') && Schema::hasTable('custom_shifts_employees');
    }

    protected function pad($time): string
    {
        $time = substr((string) $time, 0, 8);

        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
