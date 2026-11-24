<?php

namespace Modules\HR\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\HR\Http\Controllers\Concerns\BuildsHrReports;

/**
 * سجلاتُ الحضور — كلُّ ختمٍ كما وقع، مقبولاً كان أو مرفوضاً.
 *
 * المصدرُ `attendance_punches`: سجلٌّ خامٌ لا يُعدَّل (التصحيحُ بطلبٍ منفصل)،
 * فهذا التقريرُ يُجيب «ماذا حدث عند الجهاز أو على الجوال؟» — لا «كم يوماً
 * حضر؟» (ذاك ملخّص الحضور الشامل من `attendance_days`).
 *
 * ثلاثةُ عروض: كلُّ التسجيلات (ختمٌ لكلّ صف)، وأسبابُ الرفض (سببٌ لكلّ صف)،
 * ولكلّ موظف. والمدى على وقت الختم الفعليّ `punched_at`.
 *
 * الأختامُ بلا موظف (بطاقةٌ غير معروفة) لا يراها إلا من نطاقُه «الكلّ»،
 * وحين لا يُحصَر التقريرُ بقسمٍ أو مسمى أو فرعٍ أو موظف — فهي لا تتبع أحداً.
 */
class AttendancePunchReportController extends Controller
{
    use BuildsHrReports;

    public const TYPE_LABELS = [
        AttendancePunch::TYPE_IN => 'حضور',
        AttendancePunch::TYPE_OUT => 'انصراف',
        AttendancePunch::TYPE_BREAK_START => 'بدء استراحة',
        AttendancePunch::TYPE_BREAK_END => 'نهاية استراحة',
    ];

    public const CHANNEL_LABELS = [
        AttendancePunch::CHANNEL_KIOSK => 'جهاز الفرع',
        AttendancePunch::CHANNEL_FIELD => 'الجوال الميداني',
        AttendancePunch::CHANNEL_MANUAL => 'إدخال يدوي',
    ];

    /** عناوينُ أسباب الرفض — نفسُ عناوين شاشة جهاز الحضور (`BarcodeAttendanceController`). */
    private const REJECTION_TITLES = [
        'employee_inactive' => 'الموظف متوقّف عن العمل',
        'barcode_disabled' => 'البطاقة ملغاة',
        'barcode_mismatch' => 'بطاقة لا تطابق صاحبها',
        'barcode_not_supplied' => 'لم يُمسح باركود',
        'unknown_barcode' => 'بطاقة غير معروفة',
        'before_hire_date' => 'لم يبدأ التعيين بعد',
        'after_last_working_day' => 'انتهت الخدمة',
        'channel_not_allowed' => 'قناة تسجيل غير مسموحة',
        'outside_geofence' => 'خارج نطاق الموقع',
        'location_required' => 'الموقع مطلوب',
        'location_inaccurate' => 'دقّة الموقع غير كافية',
        'network_not_allowed' => 'شبكة غير مسموحة',
        'determinant_rejected' => 'رُفض حسب محدِّد الحضور',
        'already_checked_in' => 'الحضور مسجَّل مسبقاً',
        'already_checked_out' => 'الانصراف مسجَّل مسبقاً',
        'no_check_in' => 'لا انصراف بلا حضور',
        'too_soon_after_check_in' => 'انصراف بعد الحضور مباشرة',
        'photos_missing' => 'الصور المطلوبة ناقصة',
        'kiosk_required' => 'الجهاز غير مقترن',
        'on_approved_leave' => 'الموظف في إجازة',
        'weekly_off' => 'اليوم عطلة أسبوعية',
        'public_holiday' => 'اليوم عطلة رسمية',
        'outside_login_window' => 'خارج نافذة تسجيل الحضور',
        'outside_logout_window' => 'خارج نافذة تسجيل الانصراف',
        'route_missing' => 'لا خط سير لهذا اليوم',
        'route_incomplete' => 'خط السير غير مكتمل',
        'visit_open' => 'زيارة مفتوحة لم تُغلق',
        'day_complete' => 'اليوم مكتمل بالفعل',
        'day_corrected' => 'اليوم مصحَّح باعتماد',
    ];

    public function __construct()
    {
        $this->middleware('permission.role:hr_reports.attendance');
    }

    public function index(Request $request)
    {
        $period = $this->hrReportPeriod($request, true);

        // سجلٌّ تاريخيّ: الافتراضُ كلُّ الموظفين لا «على رأس العمل» وحدهم —
        // فرفضُ ختم موظفٍ انتهت خدمتُه من صميم ما يُبحث عنه هنا
        $employees = $this->hrReportEmployees($request, false)
            ->with(['department:id,name', 'job_title:id,name'])
            ->orderBy('first_name')
            ->get();
        $empById = $employees->keyBy('id');

        // الأختامُ التي لا صاحبَ لها: لمن نطاقُه الكلّ، وبلا حصرٍ على موظفين
        $withOrphans = $this->accessibleEmployeeIds() === null
            && ! collect(['department_id', 'job_title_id', 'branch_id', 'employee_id'])->contains(fn ($k) => $request->filled($k))
            && (string) $request->input('employee_status', '') === '';

        $query = AttendancePunch::query()
            ->with(['kiosk:id,name', 'geofence:id,name'])
            ->whereBetween('punched_at', [$period['from'], $period['to']])
            ->where(function ($q) use ($employees, $withOrphans) {
                $q->whereIn('employee_id', $employees->pluck('id'));
                if ($withOrphans) {
                    $q->orWhereNull('employee_id');
                }
            });

        // `rejection_code` يأتي من رابط «أسباب الرفض» إلى تسجيلات السبب نفسه
        foreach (['punch_type', 'result', 'channel', 'rejection_code'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('kiosk_id')) {
            $query->where('kiosk_id', (int) $request->input('kiosk_id'));
        }

        $punches = $query->orderByDesc('punched_at')->orderByDesc('id')->get();

        $views = ['all', 'reasons', 'employees'];
        $view = in_array($request->input('view'), $views, true) ? $request->input('view') : 'all';

        // ── كلُّ التسجيلات: ختمٌ لكلّ صف، الأحدثُ أولاً ──
        $rows = $punches->map(function (AttendancePunch $p) use ($empById) {
            $emp = $p->employee_id ? $empById->get($p->employee_id) : null;
            $applied = $p->applied_time ? substr((string) $p->applied_time, 0, 5) : null;
            $actual = $p->punched_at?->format('H:i');

            return [
                'id' => $p->id,
                'date' => $p->punched_at?->format('Y-m-d'),
                'time' => $actual ?? '—',
                'employee_id' => $p->employee_id,
                'employee' => $emp?->full_name ?? ($p->employee_id ? '#'.$p->employee_id : 'غير معروف'),
                'department' => $emp?->department->name ?? '—',
                'type' => $p->punch_type,
                'type_label' => self::TYPE_LABELS[$p->punch_type] ?? $p->punch_type,
                // الوقتُ المعتمد يُعرض فقط حين يخالف الفعليّ (قصٌّ إلى نهاية الوردية مثلاً)
                'applied' => $applied && $applied !== $actual ? $applied : null,
                'adjusted' => $p->adjustment_code === 'capped_to_shift_end' ? 'قُصّ إلى نهاية الوردية' : ($p->adjustment_code ?: null),
                'channel' => $p->channel,
                'channel_label' => $p->channelLabel(),
                'method_label' => $p->methodLabel(),
                'source' => $p->kiosk?->name ?? '—',
                'location' => $p->locationDisplay(),
                'maps_url' => $p->mapsUrl(),
                'late' => (int) $p->late_minutes,
                'early' => (int) $p->early_leave_minutes,
                'result' => $p->result,
                'reason_title' => $p->result === AttendancePunch::RESULT_REJECTED ? $this->rejectionTitle($p->rejection_code, $p->rejection_reason) : null,
                'reason' => $p->result === AttendancePunch::RESULT_REJECTED ? ($p->rejection_reason ?: '—') : null,
            ];
        })->values();

        // ── أسبابُ الرفض: سببٌ لكلّ صف — الرمزُ يجمع (النصُّ يحمل أرقاماً متغيّرة) ──
        $reasons = $punches->where('result', AttendancePunch::RESULT_REJECTED)
            ->groupBy(fn ($p) => $p->rejection_code ?: ('msg:'.$p->rejection_reason))
            ->map(function (Collection $group) {
                $first = $group->first(); // الأحدث، فالترتيبُ تنازليّ

                return [
                    'code' => $first->rejection_code,
                    'title' => $this->rejectionTitle($first->rejection_code, $first->rejection_reason),
                    'sample' => $first->rejection_reason ?: '—',
                    'count' => $group->count(),
                    'employees' => $group->pluck('employee_id')->filter()->unique()->count(),
                    'unknown' => $group->whereNull('employee_id')->count(),
                    'last' => $first->punched_at?->format('Y-m-d H:i'),
                ];
            })->sortByDesc('count')->values();

        // ── لكلّ موظف ──
        $perEmployee = $punches->whereNotNull('employee_id')->groupBy('employee_id')
            ->map(function (Collection $group, $employeeId) use ($empById) {
                $emp = $empById->get($employeeId);
                $accepted = $group->where('result', AttendancePunch::RESULT_ACCEPTED);
                $last = $group->first();

                return [
                    'employee_id' => (int) $employeeId,
                    'employee' => $emp?->full_name ?? '#'.$employeeId,
                    'department' => $emp?->department->name ?? '—',
                    'job_title' => $emp?->job_title->name ?? '—',
                    'total' => $group->count(),
                    'in' => $accepted->where('punch_type', AttendancePunch::TYPE_IN)->count(),
                    'out' => $accepted->where('punch_type', AttendancePunch::TYPE_OUT)->count(),
                    'breaks' => $accepted->where('punch_type', AttendancePunch::TYPE_BREAK_START)->count(),
                    'rejected' => $group->where('result', AttendancePunch::RESULT_REJECTED)->count(),
                    'late' => (int) $accepted->sum('late_minutes'),
                    'early' => (int) $accepted->sum('early_leave_minutes'),
                    'last' => $last->punched_at?->format('Y-m-d H:i'),
                    'last_type' => self::TYPE_LABELS[$last->punch_type] ?? $last->punch_type,
                    'last_result' => $last->result,
                ];
            })->sortBy('employee')->values();

        if ($request->input('export') === 'rows') {
            $out = match ($view) {
                'reasons' => $reasons->map(fn ($r, $i) => [
                    $i + 1, $r['title'], $r['sample'], $r['count'], $r['employees'], $r['unknown'] ?: '—', $r['last'],
                ]),
                'employees' => $perEmployee->map(fn ($r, $i) => [
                    $i + 1, $r['employee'], $r['department'], $r['total'], $r['in'], $r['out'], $r['breaks'],
                    $r['rejected'] ?: '—', $this->hm($r['late']), $this->hm($r['early']), $r['last'].' ('.$r['last_type'].')',
                ]),
                default => $rows->map(fn ($r, $i) => [
                    $i + 1, $r['date'].' '.$r['time'], $r['employee'], $r['department'], $r['type_label'],
                    $r['applied'] ? $r['applied'].($r['adjusted'] ? ' — '.$r['adjusted'] : '') : '—',
                    $r['channel_label'].' · '.$r['method_label'], $r['source'], $r['location'],
                    $r['late'] ?: '—', $r['early'] ?: '—',
                    $r['result'] === AttendancePunch::RESULT_ACCEPTED ? 'مقبول' : 'مرفوض',
                    $r['reason'] ? $r['reason_title'].': '.$r['reason'] : '—',
                ]),
            };

            return response()->json(['rows' => $out->values()->all()]);
        }

        $total = $punches->count();
        $rejected = $punches->where('result', AttendancePunch::RESULT_REJECTED)->count();

        return view('hr::reports.attendance.punches', [
            'reportView' => $view,
            'rows' => $view === 'all' ? $this->hrPaginate($rows, $request) : null,
            'reasons' => $view === 'reasons' ? $this->hrPaginate($reasons, $request) : null,
            'perEmployee' => $view === 'employees' ? $this->hrPaginate($perEmployee, $request) : null,
            'totals' => [
                'total' => $total,
                'accepted' => $total - $rejected,
                'rejected' => $rejected,
                'rejected_pct' => $total > 0 ? round($rejected * 100 / $total, 1) : 0,
                'employees' => $punches->pluck('employee_id')->filter()->unique()->count(),
                'field' => $punches->where('channel', AttendancePunch::CHANNEL_FIELD)->count(),
                'kiosk' => $punches->where('channel', AttendancePunch::CHANNEL_KIOSK)->count(),
                'manual' => $punches->where('channel', AttendancePunch::CHANNEL_MANUAL)->count(),
            ],
            // الرسمان من كلّ الأختام لا من الصفحة: من رُفضت أختامُه أكثر، ولماذا رُفضت
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأكثر رفضاً للتسجيلات', 'icon' => 'user-times', 'color' => '#ef4444', 'unit' => 'تسجيل',
                    'items' => $perEmployee->map(fn ($r) => ['name' => $r['employee'], 'value' => $r['rejected']])->all(),
                ],
                'donut' => [
                    'title' => 'التسجيلات المرفوضة حسب السبب', 'unit' => 'تسجيل',
                    'groups' => $reasons->take(8)->values()->map(fn ($r, $i) => [
                        'label' => $r['title'], 'count' => $r['count'],
                        'color' => ['#ef4444', '#f59e0b', '#8b5cf6', '#3b82f6', '#f97316', '#06b6d4', '#10b981', '#64748b'][$i],
                    ])->when($reasons->count() > 8, fn ($g) => $g->push([
                        'label' => 'أسباب أخرى', 'count' => $reasons->slice(8)->sum('count'), 'color' => '#94a3b8',
                    ]))->all(),
                ],
            ],
            'typeLabels' => self::TYPE_LABELS,
            'channelLabels' => self::CHANNEL_LABELS,
            'kiosks' => AttendanceKiosk::orderBy('name')->get(['id', 'name']),
            'fromDate' => $period['from'],
            'toDate' => $period['to'],
            'periodLabel' => $period['label'],
            'periodContext' => $period['context'],
        ] + $this->hrReportOptions());
    }

    private function rejectionTitle(?string $code, ?string $message): string
    {
        if ($code && isset(self::REJECTION_TITLES[$code])) {
            return self::REJECTION_TITLES[$code];
        }

        $message = trim((string) $message);

        return $message !== '' ? (mb_strlen($message) > 90 ? mb_substr($message, 0, 87).'…' : $message) : 'سبب غير مذكور';
    }
}
