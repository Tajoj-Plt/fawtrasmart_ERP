<?php

namespace Modules\HR\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDays;
use App\Models\Deduction;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\BuildsHrReports;

/**
 * تقاريرُ الحضور والانصراف — بتصميم تقارير العملاء.
 *
 * الملخّصُ الشامل: موظفٌ لكلّ صف — أيامُ الحضور والغياب والإجازات والعطل،
 * والتأخيرُ والانصرافُ المبكر والإضافيُّ والاستراحة، وصافي ساعات العمل،
 * وخصمُ الحضور. والتفاصيل: يومٌ لكلّ صف بالأرقام نفسها.
 *
 * المصدرُ `attendance_days` كما احتسبه المعالج (`AttendanceProcessorService`)،
 * والخصمُ من `deductions` بأنواع الحضور (غياب/تأخير/انصراف مبكر/نصف يوم)
 * غيرَ الملغاة والمرفوضة.
 */
class AttendanceReportController extends Controller
{
    use BuildsHrReports;

    private const ATTENDANCE_DEDUCTIONS = [
        Deduction::TYPE_ABSENCE, Deduction::TYPE_LATE, Deduction::TYPE_EARLY_LEAVE, Deduction::TYPE_HALF_DAY,
    ];

    public function __construct()
    {
        $this->middleware('permission.role:hr_reports.attendance');
    }

    public function summary(Request $request)
    {
        $period = $this->hrReportPeriod($request, true);
        $employees = $this->hrReportEmployees($request)
            ->with(['department:id,name', 'job_title:id,name', 'branch:id,name'])
            ->orderBy('first_name')
            ->get();
        $ids = $employees->pluck('id');

        $days = AttendanceDays::whereIn('employee_id', $ids)
            ->whereBetween('attendance_date', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->orderBy('attendance_date')
            ->get();

        $deductions = Deduction::whereIn('employee_id', $ids)
            ->whereBetween('date', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->whereIn('deduction_type', self::ATTENDANCE_DEDUCTIONS)
            ->whereNotIn('status', [Deduction::STATUS_CANCELLED, Deduction::STATUS_REJECTED])
            ->get(['employee_id', 'date', 'amount', 'status']);

        $view = $request->input('view') === 'details' ? 'details' : 'summary';

        $statusLabels = [
            AttendanceDays::STATUS_PRESENT => 'حاضر', AttendanceDays::STATUS_LATE => 'متأخر',
            AttendanceDays::STATUS_ABSENT => 'غائب', AttendanceDays::STATUS_HALF_DAY => 'نصف يوم',
            AttendanceDays::STATUS_LEAVE => 'إجازة', AttendanceDays::STATUS_HOLIDAY => 'عطلة',
        ];

        // ── الملخّص: موظفٌ لكلّ صف ──
        $byEmployee = $days->groupBy('employee_id');
        $dedByEmployee = $deductions->groupBy('employee_id');
        $summary = $employees->map(function ($emp) use ($byEmployee, $dedByEmployee) {
            $d = $byEmployee->get($emp->id, collect());
            $worked = $d->whereIn('status', [AttendanceDays::STATUS_PRESENT, AttendanceDays::STATUS_LATE, AttendanceDays::STATUS_HALF_DAY]);
            $net = (int) $worked->sum(fn ($day) => max(0, (int) $day->working_hours - (int) $day->break_minutes));
            $ded = $dedByEmployee->get($emp->id, collect());

            return [
                'employee_id' => $emp->id,
                'employee' => $emp->full_name,
                'department' => $emp->department->name ?? '—',
                'job_title' => $emp->job_title->name ?? '—',
                'worked' => $worked->count(),
                'late_days' => $d->where('late_minutes', '>', 0)->where('status', '!=', AttendanceDays::STATUS_ABSENT)->count(),
                'absent' => $d->where('status', AttendanceDays::STATUS_ABSENT)->count(),
                'half' => $d->where('status', AttendanceDays::STATUS_HALF_DAY)->count(),
                'leave' => $d->where('status', AttendanceDays::STATUS_LEAVE)->count(),
                'off' => $d->where('status', AttendanceDays::STATUS_HOLIDAY)->count(),
                'late_minutes' => (int) $d->where('status', '!=', AttendanceDays::STATUS_ABSENT)->sum('late_minutes'),
                'early_minutes' => (int) $d->sum('early_leave_minutes'),
                'overtime_minutes' => (int) $d->sum('overtime_minutes'),
                'break_minutes' => (int) $d->sum('break_minutes'),
                'missing_checkout' => $d->filter(fn ($day) => $day->check_in_time && ! $day->check_out_time)->count(),
                'net_minutes' => $net,
                'deduction' => (float) $ded->sum('amount'),
                'deduction_pending' => (float) $ded->where('status', Deduction::STATUS_PENDING)->sum('amount'),
            ];
        })->values();

        // ── التفاصيل: يومٌ لكلّ صف ──
        $empById = $employees->keyBy('id');
        $dedByDay = $deductions->groupBy(fn ($x) => $x->employee_id.'|'.$x->date->toDateString());
        $details = $days->map(function ($day) use ($empById, $dedByDay, $statusLabels) {
            $emp = $empById->get($day->employee_id);

            return [
                'date' => $day->attendance_date->toDateString(),
                'day_name' => $day->attendance_date->locale('ar')->dayName,
                'employee_id' => $day->employee_id,
                'day_id' => $day->id,
                'employee' => $emp?->full_name ?? '#'.$day->employee_id,
                'department' => $emp?->department->name ?? '—',
                'status' => $day->status,
                'status_label' => $statusLabels[$day->status] ?? $day->status,
                'expected' => $day->expected_check_in ? substr($day->expected_check_in, 0, 5).' – '.substr((string) $day->expected_check_out, 0, 5) : '—',
                'in' => $day->check_in_time ? substr($day->check_in_time, 0, 5) : '—',
                'out' => $day->check_out_time ? substr($day->check_out_time, 0, 5) : ($day->check_in_time ? 'لم ينصرف' : '—'),
                'late' => (int) $day->late_minutes,
                'early' => (int) $day->early_leave_minutes,
                'overtime' => (int) $day->overtime_minutes,
                'break' => (int) $day->break_minutes,
                'net' => max(0, (int) $day->working_hours - (int) $day->break_minutes),
                'deduction' => (float) $dedByDay->get($day->employee_id.'|'.$day->attendance_date->toDateString(), collect())->sum('amount'),
            ];
        })->sortBy([['date', 'desc'], ['employee', 'asc']])->values();

        if ($request->filled('day_status')) {
            $details = $details->where('status', $request->input('day_status'))->values();
        }

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $view === 'summary'
                ? $summary->map(fn ($r, $i) => [
                    $i + 1, $r['employee'], $r['department'], $r['worked'], $r['absent'], $r['half'], $r['leave'], $r['off'],
                    $r['late_days'], $this->hm($r['late_minutes']), $this->hm($r['early_minutes']), $this->hm($r['overtime_minutes']),
                    $this->hm($r['break_minutes']), $this->hm($r['net_minutes']), $r['missing_checkout'], number_format($r['deduction'], 2),
                ])->all()
                : $details->map(fn ($r, $i) => [
                    $i + 1, $r['date'], $r['day_name'], $r['employee'], $r['department'], $r['status_label'], $r['expected'],
                    $r['in'], $r['out'], $r['late'] ?: '—', $r['early'] ?: '—', $r['overtime'] ?: '—', $r['break'] ?: '—',
                    $this->hm($r['net']), $r['deduction'] > 0 ? number_format($r['deduction'], 2) : '—',
                ])->all(),
            ]);
        }

        return view('hr::reports.attendance.summary', [
            'reportView' => $view,
            'summary' => $view === 'summary' ? $this->hrPaginate($summary, $request) : null,
            'details' => $view === 'details' ? $this->hrPaginate($details, $request) : null,
            'totals' => [
                'employees' => $employees->count(),
                'worked' => $summary->sum('worked'),
                'absent' => $summary->sum('absent'),
                'late_days' => $summary->sum('late_days'),
                'late_minutes' => $summary->sum('late_minutes'),
                'early_minutes' => $summary->sum('early_minutes'),
                'overtime_minutes' => $summary->sum('overtime_minutes'),
                'net_minutes' => $summary->sum('net_minutes'),
                'deduction' => $summary->sum('deduction'),
                'deduction_pending' => $summary->sum('deduction_pending'),
            ],
            'statusLabels' => $statusLabels,
            // الرسمان — كتقرير «أرصدة العملاء»
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأكثر تأخيراً (بالدقائق)', 'icon' => 'clock', 'color' => '#f59e0b', 'unit' => 'دقيقة',
                    'items' => $summary->map(fn ($r) => ['name' => $r['employee'], 'value' => $r['late_minutes']])->all(),
                ],
                'donut' => [
                    'title' => 'توزيع أيام الفترة حسب الحالة', 'unit' => 'يوم',
                    'groups' => collect([
                        AttendanceDays::STATUS_PRESENT => '#10b981', AttendanceDays::STATUS_LATE => '#f59e0b',
                        AttendanceDays::STATUS_HALF_DAY => '#f97316', AttendanceDays::STATUS_ABSENT => '#ef4444',
                        AttendanceDays::STATUS_LEAVE => '#3b82f6', AttendanceDays::STATUS_HOLIDAY => '#64748b',
                    ])->map(fn ($color, $status) => [
                        'label' => $statusLabels[$status], 'color' => $color,
                        'count' => $days->where('status', $status)->count(),
                    ])->values()->all(),
                ],
            ],
            'fromDate' => $period['from'],
            'toDate' => $period['to'],
            'periodLabel' => $period['label'],
            'periodContext' => $period['context'],
        ] + $this->hrReportOptions());
    }
}
