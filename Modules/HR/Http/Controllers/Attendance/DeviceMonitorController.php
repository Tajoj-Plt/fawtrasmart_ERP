<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * سجلُّ جلسات الحضور: حالةُ الأجهزة والجلسات الفعّالة.
 *
 * سؤالُ هذه الشاشة ليس «من حضر؟» بل «هل النظامُ يعمل؟» — وهو سؤالٌ لا
 * تُجيبه أيُّ شاشةٍ أخرى. جهازُ فرعٍ انطفأ الساعة العاشرة صباحاً يُنتج
 * يوماً يبدو فيه نصفُ الفرع غائباً، ولا يُكتشف ذلك إلا عند صرف الرواتب —
 * حين لا يبقى ما يُثبت أن الموظفين حضروا.
 *
 * والدليلُ على الحياة نبضتان: `attendance_kiosks.last_seen_at` التي تُحدَّث
 * كلّما فُتحت شاشةُ الجهاز أو وقع ختمٌ عليه، والأختامُ نفسُها. فجهازٌ نبضَ
 * صباحاً ثم صمت ثلاث ساعاتٍ في وسط الدوام واقعةٌ تستحق أن تُرى.
 *
 * و«محاولاتُ التسجيل من أجهزة غير معرّفة» تُقرأ من سجل الأختام: ختمُ
 * قناةِ كشكٍ بلا `kiosk_id` جاء من متصفّحٍ غير مقترن، وبطاقةٌ مجهولة
 * تتكرّر على جهازٍ بعينه قد تكون بطاقةً لم تُفعَّل — أو محاولةَ عبث.
 */
class DeviceMonitorController extends Controller
{
    use ScopesToAccessibleEmployees;

    /**
     * يُعدّ الجهازُ صامتاً بعد هذا الحدّ من آخر إشارةِ حياة.
     *
     * والقياسُ من «الآن» لا من ساعات الدوام: لا تُنمذَج ساعاتُ الفرع على
     * الجهاز، فجهازُ فرعٍ مغلقٍ ليلاً يُقرأ صامتاً وهو بخير. وقراءتُه
     * على يومٍ ماضٍ تسأل «هل عمل فيه» لا «هل يعمل الآن» — انظر
     * `kioskState()`.
     */
    private const SILENCE_MINUTES = 45;

    /** سقفُ ما يُقرأ من أختام اليوم الواحد. */
    private const MAX_PUNCHES = 5000;

    public function __construct()
    {
        // شاشةُ الأجهزة: صحّتُها لكل من يرى الأجهزة، وأسماءُ الموظفين فيها من نطاقه وحده
        $this->middleware('permission.role:attendance_devices.index');
    }

    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        /*
            أختامُ اليوم كلُّها — بلا ترقيم: الشاشةُ تُجمِّع لا تسرد،
            وكلُّ ختمٍ يدخل في عدّ جهازٍ أو جلسةِ موظف. والحدُّ مع ذلك
            قائمٌ لئلّا يُسقط يومٌ شاذٌّ الصفحةَ بذاكرتها.
        */
        $punches = AttendancePunch::with('employee:id,first_name,middle_name,nickname')
            ->whereDate('attendance_date', $date->toDateString())
            ->orderByDesc('punched_at')
            ->limit(self::MAX_PUNCHES)
            ->get();

        return view('hr::attendance.device_monitor.index', [
            'date' => $date,
            'kiosks' => $this->kioskStates($punches, $date),
            'fieldSessions' => $this->fieldSessions($this->inScope($punches)),
            'unidentified' => $this->unidentified($this->inScope($punches, true)),
            'totals' => [
                'accepted' => $punches->where('result', AttendancePunch::RESULT_ACCEPTED)->count(),
                'rejected' => $punches->where('result', AttendancePunch::RESULT_REJECTED)->count(),
            ],
        ]);
    }

    // ════════════════════════════════════════════════════

    /**
     * أختامُ موظفي نطاق المستخدم فقط — عدّادُ الأجهزة يبقى كاملاً (صحّةُ جهاز
     * لا بياناتُ موظف)، أمّا ما يُسمّي موظفاً فمن النطاق.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     */
    private function inScope(Collection $punches, bool $keepAnonymous = false): Collection
    {
        $ids = $this->listEmployeeIds();

        if ($ids === null) {
            return $punches;
        }

        return $punches->filter(fn (AttendancePunch $p) => $p->employee_id === null
            ? $keepAnonymous
            : in_array((int) $p->employee_id, $ids, true))->values();
    }

    /**
     * حالةُ كل جهازٍ مُسجَّل.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     * @return Collection<int, array<string, mixed>>
     */
    private function kioskStates(Collection $punches, Carbon $date): Collection
    {
        $byKiosk = $punches->whereNotNull('kiosk_id')->groupBy('kiosk_id');

        return AttendanceKiosk::with(['branch:id,name', 'geofence:id,name'])
            ->orderBy('name')
            ->get()
            ->map(function (AttendanceKiosk $kiosk) use ($byKiosk, $date) {
                $own = $byKiosk->get($kiosk->id, collect());
                $lastPunch = $own->first();

                /*
                    آخرُ إشارةِ حياة: النبضةُ أو آخرُ ختم، أيُّهما أحدث.
                    النبضةُ وحدها لا تكفي — جهازٌ تُركت شاشتُه مفتوحةً ينبض
                    بلا أن يُسجِّل أحد؛ والأختامُ وحدها لا تكفي — فرعٌ هادئٌ
                    ساعةً ليس جهازاً معطوباً.
                */
                $lastSignal = collect([$kiosk->last_seen_at, $lastPunch?->punched_at])
                    ->filter()
                    ->max();

                return [
                    'kiosk' => $kiosk,
                    'last_signal' => $lastSignal,
                    'silent_minutes' => $lastSignal ? (int) abs(Carbon::now()->diffInMinutes($lastSignal)) : null,
                    'state' => $this->kioskState($kiosk, $lastSignal, $date),
                    'accepted' => $own->where('result', AttendancePunch::RESULT_ACCEPTED)->count(),
                    'rejected' => $own->where('result', AttendancePunch::RESULT_REJECTED)->count(),
                    'last_punch' => $lastPunch,
                ];
            });
    }

    private function kioskState(AttendanceKiosk $kiosk, $lastSignal, Carbon $date): string
    {
        if (! $kiosk->is_active) {
            return 'disabled';
        }

        // يومٌ ماضٍ: السؤالُ هل عمل فيه، لا هل يعمل الآن
        if (! $date->isToday()) {
            return $lastSignal && Carbon::parse($lastSignal)->isSameDay($date) ? 'worked' : 'idle';
        }

        if (! $lastSignal || ! Carbon::parse($lastSignal)->isToday()) {
            return 'idle';
        }

        return abs(Carbon::now()->diffInMinutes($lastSignal)) > self::SILENCE_MINUTES ? 'silent' : 'live';
    }

    /**
     * جلساتُ الجوال الميداني: من سجّل اليوم ومن أين.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     * @return Collection<int, array<string, mixed>>
     */
    private function fieldSessions(Collection $punches): Collection
    {
        return $punches
            ->where('channel', AttendancePunch::CHANNEL_FIELD)
            ->groupBy('employee_id')
            ->map(fn (Collection $own) => [
                'employee' => $own->first()->employee,
                'last' => $own->first(),
                'accepted' => $own->where('result', AttendancePunch::RESULT_ACCEPTED)->count(),
                'rejected' => $own->where('result', AttendancePunch::RESULT_REJECTED)->count(),
            ])
            ->values();
    }

    /**
     * ما لا يُنسَب إلى جهازٍ معروف.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     * @return Collection<int, AttendancePunch>
     */
    private function unidentified(Collection $punches): Collection
    {
        return $punches->filter(function (AttendancePunch $punch) {
            // ختمُ كشكٍ بلا جهازٍ مقترن: متصفّحٌ فُتح على العنوان مباشرةً
            $unpairedKiosk = $punch->channel === AttendancePunch::CHANNEL_KIOSK && $punch->kiosk_id === null;

            // بطاقةٌ لا يعرفها النظام — قد تكون غيرَ مفعَّلةٍ أو عبثاً
            $unknownCard = $punch->rejection_code === 'unknown_barcode';

            return $unpairedKiosk || $unknownCard;
        })->values();
    }
}
