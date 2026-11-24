<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceDays;
use App\Models\AttendanceGeofence;
use App\Models\AttendanceKiosk;
use App\Models\AttendanceSetting;
use App\Models\CronJob;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolyDayListCustomize;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use Illuminate\Support\Facades\Log;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * «لوحة الحضور» — مدخلُ قسم الحضور والانصراف.
 *
 * كان القسمُ خمسةَ عشر رابطاً في القائمة الجانبية وثمانيَ بطاقاتٍ في
 * الإعدادات، بلا ترتيبٍ يقول للمستخدم من أين يبدأ. فيُفتح «أيام الحضور»
 * على جدولٍ فارغ، أو يُمسح باركودٌ لموظفٍ بلا وردية، ولا شيء يشرح السبب.
 *
 * هذه الشاشةُ تجيب سؤالين:
 *   ١) هل النظامُ جاهزٌ ليسجّل الموظفون؟ — خطواتُ التجهيز بالترتيب،
 *      لكلٍّ منها حالتُها الحيّة وزرُّ شاشتها.
 *   ٢) ماذا يجري اليوم؟ — أرقامُ اليوم وما ينتظر الاعتماد.
 *
 * كلُّ عدٍّ في `rescue()`: جدولٌ ناقصٌ في نسخةٍ قديمة يُسقط خطوةً
 * لا الشاشةَ كلَّها.
 */
class AttendanceHubController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        /*
            من يملك أيَّ شاشةٍ من شاشات الحضور يرى لوحتَه — البوّابةُ نفسُها
            التي تُظهر مجموعة «الحضور» في القائمة الجانبية.
        */
        $this->middleware(function ($request, $next) {
            abort_unless(canAny([
                'attendance_records.index', 'attendance_days.index', 'attendance_corrections.index',
                'attendance_corrections.approve', 'attendance_books.index', 'leave_permissions.index',
                'leave_requests.index', 'leave_balances.index', 'shifts.index', 'attendance_devices.index',
                'attendance_settings.manage', 'attendance_registration.kiosk',
                'attendance_registration.mobile', 'attendance_team.index',
                'attendance_places.index', 'attendance_cards.index', 'attendance_rules.index', 'holidays.index',
                // بنودُ «الإعدادات» في القائمة — من يرى رابطَ اللوحة يفتحها
                'leave_types.index', 'leave_policies.index',
            ]), 403, 'ليست لديك صلاحية الوصول إلى هذه الصفحة.');

            return $next($request);
        });
    }

    public function index()
    {
        $count = fn (callable $query, $default = 0) => rescue($query, $default, function ($e) {
            Log::warning('لوحة الحضور: تعذّر عدّ خطوة', ['error' => $e->getMessage()]);
        });

        $settings = rescue(fn () => AttendanceSetting::current(), null, false);

        $activeEmployees = $count(fn () => Employee::active()->count());
        $withoutShift = $count(fn () => Employee::active()->whereNull('shift_id')->count());
        $holidays = $count(fn () => Holiday::where('is_active', true)->count());
        $holidayRestricted = $count(fn () => HolyDayListCustomize::whereNotNull('holiday_list_id')->count());
        $shifts = $count(fn () => Shift::count());
        $geofences = $count(fn () => AttendanceGeofence::where('is_active', true)->count());
        $kiosks = $count(fn () => AttendanceKiosk::where('is_active', true)->count());
        $kiosksSeen = $count(fn () => AttendanceKiosk::where('is_active', true)->whereNotNull('last_seen_at')->count());
        $branchChannel = $count(fn () => Employee::active()->where(fn ($q) => $q->whereNull('attendance_channel')->orWhere('attendance_channel', 'branch'))->count());
        $fieldChannel = $count(fn () => Employee::active()->where('attendance_channel', 'field')->count());
        $branchWithoutCard = $count(fn () => Employee::active()
            ->where(fn ($q) => $q->whereNull('attendance_channel')->orWhere('attendance_channel', 'branch'))
            ->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode_enabled', false))
            ->count());
        $leaveTypes = $count(fn () => LeaveType::count());
        /*
            رأسُ السلسلة لا مديرَ فوقه — صاحبُ المنشأة ومن يُدير غيرَه بلا
            مديرٍ أعلى. كانت الخطوةُ لا تكتمل أبداً لأنها تعدّهم «بلا مدير».
        */
        $withoutManager = $count(fn () => Employee::active()
            ->whereNull('direct_manager_id')
            ->whereNotIn('id', Employee::query()->whereNotNull('direct_manager_id')->select('direct_manager_id'))
            ->count());
        $nightlyJob = rescue(fn () => CronJob::where('handler', 'attendance:process')->first(), null, false);

        /*
            بدايةُ الدوام الفارغة تعني ٠٨:٠٠ (كما يقول نموذج الإعدادات)، والعطلةُ
            الأسبوعية الفارغة قرارٌ صريح بالعمل سبعة أيام — فلا يُعدّ أيٌّ منهما
            «لم يُضبط». كانت الخطوة تبقى ✗ بعد الحفظ لمن ترك البداية فارغة.
            المعيار: أن يكون المسؤولُ قد حفظ الإعدادات مرّةً على الأقل.
        */
        $basicsDone = $settings
            && (! empty($settings->default_shift_start)
                || ($settings->updated_at && $settings->created_at && $settings->updated_at->gt($settings->created_at)));

        /*
            الخطوات بالترتيب الذي يعتمد فيه كلُّ شيءٍ على ما قبله: لا وردية
            بلا ساعات دوام، ولا بطاقة بلا طريقة تسجيل، ولا تسجيل بلا مكان.
        */
        $steps = [
            [
                'title' => 'اضبط وقت الدوام والعطلة الأسبوعية',
                'why' => 'بداية الدوام وعدد ساعاته ومدة السماح بالتأخير — يُقاس عليها كل موظف ليس له وردية.',
                'done' => $basicsDone,
                'status' => $basicsDone
                    ? 'الدوام يبدأ '.(substr((string) $settings->default_shift_start, 0, 5) ?: '08:00').' — السماح '.$settings->late_grace_minutes.' دقيقة'
                    : 'لم يُضبط بعد',
                'route' => 'attendance.v2.settings.index',
                'button' => 'إعدادات الدوام',
                'perm' => 'attendance_settings.manage',
            ],
            [
                'title' => 'أضف العطل الرسمية',
                'why' => 'يوم العطلة لا يُحسب غياباً ولا يُخصم منه.',
                'done' => $holidays > 0,
                'status' => $holidays > 0
                    ? $holidays.' يوم عطلة'.($holidayRestricted > 0 ? ' — تنبيه: القائمة مخصّصة لموظفين محدّدين فقط' : '')
                    : 'لا توجد عطل',
                'warn' => $holidayRestricted > 0,
                'route' => 'holiday_lists.index',
                'button' => 'قوائم العطل',
                'perm' => 'holidays.index',
            ],
            [
                'title' => 'أنشئ الورديات واربط الموظفين بها',
                'why' => 'الوردية تحدد وقت حضور وانصراف كل موظف وأيام عمله.',
                'done' => $shifts > 0 && $withoutShift === 0,
                'status' => $shifts === 0
                    ? 'لا توجد ورديات'
                    : $shifts.' وردية — '.($withoutShift > 0 ? $withoutShift.' موظف بلا وردية (يُقاس على وقت الدوام العام)' : 'كل الموظفين مربوطون'),
                'warn' => $shifts > 0 && $withoutShift > 0,
                'route' => 'shift_management.index',
                'button' => 'الورديات',
                'perm' => 'shifts.index',
            ],
            [
                'title' => 'حدّد أماكن التسجيل على الخريطة',
                'why' => 'مكان الفرع ونصف قطره — لا يُقبل تسجيل من خارجه.',
                'done' => $geofences > 0,
                'status' => $geofences > 0 ? $geofences.' مكان' : 'لم يُحدَّد مكان — التسجيل مقبول من أي مكان',
                'route' => 'attendance.settings.geofences.index',
                'button' => 'أماكن التسجيل',
                'perm' => 'attendance_places.index',
            ],
            [
                'title' => 'جهّز جهاز تسجيل الحضور في الفرع',
                'why' => 'شاشة ثابتة في الفرع يمسح عليها الموظفون بطاقاتهم. تُربط مرة واحدة برمز.',
                'done' => $kiosksSeen > 0 || $fieldChannel === $activeEmployees,
                'status' => $kiosks === 0
                    ? ($fieldChannel === $activeEmployees ? 'لا يلزم — كل الموظفين يسجّلون من الجوال' : 'لا يوجد جهاز')
                    : $kiosks.' جهاز — '.($kiosksSeen > 0 ? $kiosksSeen.' مربوط ويعمل' : 'أُضيف ولم يُربط بعد: افتح شاشة التسجيل على الجهاز وأدخل رمز الربط'),
                // جهازٌ أُضيف ولم يُربط خطوةٌ ناقصةٌ لا متروكة — تنبيهٌ لا ✗
                'warn' => $kiosks > 0 && $kiosksSeen === 0 && $fieldChannel !== $activeEmployees,
                'route' => 'attendance.settings.kiosks.index',
                'button' => 'أجهزة التسجيل',
                'perm' => 'attendance_places.index',
            ],
            [
                'title' => 'حدّد طريقة تسجيل كل موظف وأصدر البطاقات',
                'why' => 'موظف الفرع يسجل بالبطاقة على جهاز الفرع، والموظف الميداني يسجل من جواله.',
                'done' => $branchWithoutCard === 0 && $activeEmployees > 0,
                'status' => $branchChannel.' على جهاز الفرع — '.$fieldChannel.' من الجوال'
                    .($branchWithoutCard > 0 ? ' — '.$branchWithoutCard.' بلا بطاقة' : ''),
                'warn' => $branchWithoutCard > 0,
                'route' => 'barcode.cards',
                'button' => 'بطاقات الحضور',
                'perm' => 'attendance_cards.index',
            ],
            [
                'title' => 'أضف أنواع الإجازات وحدّد المدير المباشر',
                'why' => 'نوع الإجازة يحدد رصيدها السنوي، والمدير المباشر هو من يعتمد طلبات فريقه.',
                'done' => $leaveTypes > 0 && $withoutManager === 0,
                'status' => ($leaveTypes > 0 ? $leaveTypes.' نوع إجازة' : 'لا توجد أنواع إجازات')
                    .($withoutManager > 0 ? ' — '.$withoutManager.' موظف بلا مدير مباشر' : ''),
                'warn' => $leaveTypes > 0 && $withoutManager > 0,
                'route' => 'leave_types.index',
                'button' => 'أنواع الإجازات',
                'perm' => 'leave_types.index',
            ],
            [
                'title' => 'شغّل الحساب الليلي للحضور',
                'why' => 'يغلق كل ليلة يوم من لم يسجّل، ويحسب التأخير والغياب والخصم.',
                'done' => (bool) ($nightlyJob && $nightlyJob->isActive()),
                'status' => ! $nightlyJob ? 'المهمة غير موجودة' : ($nightlyJob->isActive() ? 'تعمل' : 'متوقفة'),
                'route' => $nightlyJob ? 'CronJobs.show' : 'CronJobs.index',
                'routeParam' => $nightlyJob?->id,
                'button' => 'المهام المجدولة',
                'perm' => null,
            ],
        ];

        $readyCount = count(array_filter($steps, fn ($s) => $s['done'] && empty($s['warn'])));

        // ── اليوم ──
        $today = now()->toDateString();
        $dayQuery = fn () => $this->scopeToEmployees(AttendanceDays::query()->whereDate('attendance_date', $today));
        $todayStats = [
            'present' => $count(fn () => $dayQuery()->where('status', AttendanceDays::STATUS_PRESENT)->count()),
            'late' => $count(fn () => $dayQuery()->where('status', AttendanceDays::STATUS_LATE)->count()),
            'absent' => $count(fn () => $dayQuery()->where('status', AttendanceDays::STATUS_ABSENT)->count()),
            'leave' => $count(fn () => $dayQuery()->where('status', AttendanceDays::STATUS_LEAVE)->count()),
        ];

        $pending = [
            'leaves' => $count(fn () => $this->scopeToEmployees(LeaveRequest::query()->where('status', 'pending'))->count()),
            'corrections' => $count(fn () => $this->scopeToEmployees(
                AttendanceCorrectionRequest::query()->where('status', AttendanceCorrectionRequest::STATUS_PENDING)
            )->count()),
        ];

        return view('hr::attendance.hub.index', compact('steps', 'readyCount', 'todayStats', 'pending'));
    }
}
