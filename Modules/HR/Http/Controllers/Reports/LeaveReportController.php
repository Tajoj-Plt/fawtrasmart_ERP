<?php

namespace Modules\HR\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeavePermissions;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\BuildsHrReports;

/**
 * تقريرُ الإجازات والأذونات — بتصميم تقارير العملاء.
 *
 * ثلاثةُ مصادر:
 *   • `leave_requests`  طلباتُ الإجازة (معلّقة/موافق عليها/مرفوضة).
 *   • `leave_permissions`  أذوناتُ الوصول المتأخر والانصراف المبكر — بلا حالة:
 *      الإذنُ المسجَّل نافذٌ، ويقرؤه حسابُ اليوم (`AttendanceStampService`).
 *   • `employee_leave_balances`  الرصيدُ السنويّ لكلّ نوع إجازة، والمتبقّي
 *      بـ`getActualRemainingBalance()` (الأساس + المرحَّل + الإضافيّ − المستخدَم).
 *
 * المدى هنا **لا يُقصّ عند اليوم** كبقية تقارير الحضور: الإجازةُ تُطلب وتُعتمد
 * قبل وقوعها، ومديرٌ يسأل «من في إجازة هذا الشهر؟» يريد بقيّةَ الشهر أيضاً.
 * فالافتراضُ الشهرُ الجاري كاملاً، والطلبُ يدخل المدى إن تقاطع معه.
 */
class LeaveReportController extends Controller
{
    use BuildsHrReports;

    public const STATUS_LABELS = [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليها',
        'rejected' => 'مرفوضة',
        'cancelled' => 'ملغاة',
    ];

    public function __construct()
    {
        $this->middleware('permission.role:hr_reports.attendance');
    }

    public function index(Request $request)
    {
        $period = $this->leavePeriod($request);
        $from = $period['from']->copy()->startOfDay();
        $to = $period['to']->copy()->startOfDay();

        // سجلٌّ تاريخيّ: الافتراضُ كلُّ الموظفين — إجازةُ من انتهت خدمتُه تبقى واقعة
        $employees = $this->hrReportEmployees($request, false)
            ->with(['department:id,name', 'job_title:id,name'])
            ->orderBy('first_name')
            ->get();
        $empById = $employees->keyBy('id');
        $ids = $employees->pluck('id');

        // ── طلباتُ الإجازة المتقاطعة مع المدى ──
        $leaveQuery = LeaveRequest::with('leaveType:id,name')
            ->whereIn('employee_id', $ids)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$from->toDateString()]);
        if ($request->filled('leave_type_id')) {
            $leaveQuery->where('leave_type_id', (int) $request->input('leave_type_id'));
        }
        if ($request->filled('status')) {
            $leaveQuery->where('status', $request->input('status'));
        }
        $leaves = $leaveQuery->orderByDesc('start_date')->orderByDesc('id')->get();

        // ── الأذونات المتقاطعة مع المدى ──
        $permQuery = LeavePermissions::whereIn('employee_id', $ids)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$from->toDateString()]);
        if ($request->filled('permission_kind')) {
            $permQuery->where('leave_type', (int) $request->input('permission_kind'));
        }
        $permissions = $permQuery->orderByDesc('start_date')->orderByDesc('id')->get();

        // ── الأرصدة: سنةُ نهاية المدى ──
        $balanceYear = $to->year;
        $balances = EmployeeLeaveBalance::with('leaveType:id,name')
            ->whereIn('employee_id', $ids)
            ->where('year', $balanceYear)
            ->when($request->filled('leave_type_id'), fn ($q) => $q->where('leave_type_id', (int) $request->input('leave_type_id')))
            ->get();

        $views = ['requests', 'employees', 'permissions'];
        $view = in_array($request->input('view'), $views, true) ? $request->input('view') : 'requests';

        $requestRows = $leaves->map(function (LeaveRequest $l) use ($empById, $from, $to) {
            $emp = $empById->get($l->employee_id);

            return [
                'id' => $l->id,
                'employee_id' => $l->employee_id,
                'employee' => $emp?->full_name ?? '#'.$l->employee_id,
                'department' => $emp?->department->name ?? '—',
                'leave_type' => $l->leaveType->name ?? ($l->legacy_leave_type ?: $l->request_type_text ?: '—'),
                'request_type' => $l->request_type_text ?: '—',
                'start' => $l->start_date?->toDateString() ?? '—',
                'end' => ($l->end_date ?? $l->start_date)?->toDateString() ?? '—',
                'days' => (int) $l->days,
                'in_period' => $this->daysInPeriod($l, $from, $to),
                'status' => $l->status,
                'status_label' => self::STATUS_LABELS[$l->status] ?? $l->status,
                'requested_at' => $l->created_at?->format('Y-m-d'),
            ];
        })->values();

        $permissionRows = $permissions->map(function (LeavePermissions $p) use ($empById) {
            $emp = $empById->get($p->employee_id);

            return [
                'id' => $p->id,
                'employee_id' => $p->employee_id,
                'employee' => $emp?->full_name ?? '#'.$p->employee_id,
                'department' => $emp?->department->name ?? '—',
                'kind' => (int) $p->leave_type,
                'kind_label' => $p->leave_type_text ?: '—',
                'type_label' => $p->type_text ?: '—',
                'start' => $p->start_date?->toDateString() ?? '—',
                'end' => ($p->end_date ?? $p->start_date)?->toDateString() ?? '—',
                'submitted' => $p->submission_date?->toDateString() ?? '—',
                'notes' => trim(strip_tags((string) $p->notes)) ?: '—',
            ];
        })->values();

        // ── لكلّ موظف: من له طلبٌ أو إذنٌ في المدى أو رصيدٌ في السنة ──
        $leavesByEmp = $requestRows->groupBy('employee_id');
        $permsByEmp = $permissionRows->groupBy('employee_id');
        $balByEmp = $balances->groupBy('employee_id');
        $perEmployee = $employees
            ->filter(fn ($e) => $leavesByEmp->has($e->id) || $permsByEmp->has($e->id) || $balByEmp->has($e->id))
            ->map(function ($emp) use ($leavesByEmp, $permsByEmp, $balByEmp) {
                $l = $leavesByEmp->get($emp->id, collect());
                $approved = $l->where('status', 'approved');
                $p = $permsByEmp->get($emp->id, collect());

                return [
                    'employee_id' => $emp->id,
                    'employee' => $emp->full_name,
                    'department' => $emp->department->name ?? '—',
                    'job_title' => $emp->job_title->name ?? '—',
                    'approved_days' => (int) $approved->sum('in_period'),
                    'approved_by_type' => $approved->groupBy('leave_type')->map(fn ($g, $t) => $t.': '.$g->sum('in_period'))->values()->implode(' · '),
                    'pending' => $l->where('status', 'pending')->count(),
                    'rejected' => $l->where('status', 'rejected')->count(),
                    'late_permissions' => $p->where('kind', LeavePermissions::LATE_ARRIVAL)->count(),
                    'early_permissions' => $p->where('kind', LeavePermissions::EARLY_LEAVE)->count(),
                    'balances' => $balByEmp->get($emp->id, collect())->map(fn (EmployeeLeaveBalance $b) => [
                        'type' => $b->leaveType->name ?? '#'.$b->leave_type_id,
                        'remaining' => $b->getActualRemainingBalance(),
                        'total' => $b->getTotalAvailableBalance(),
                    ])->values()->all(),
                ];
            })->values();

        if ($request->input('export') === 'rows') {
            $balanceText = fn (array $bal) => collect($bal)->map(fn ($b) => $b['type'].': '.$b['remaining'].' من '.$b['total'])->implode(' · ') ?: '—';
            $out = match ($view) {
                'employees' => $perEmployee->map(fn ($r, $i) => [
                    $i + 1, $r['employee'], $r['department'], $r['approved_days'] ?: '—', $r['approved_by_type'] ?: '—',
                    $r['pending'] ?: '—', $r['rejected'] ?: '—', $r['late_permissions'] ?: '—', $r['early_permissions'] ?: '—',
                    $balanceText($r['balances']),
                ]),
                'permissions' => $permissionRows->map(fn ($r, $i) => [
                    $i + 1, $r['employee'], $r['department'], $r['kind_label'], $r['type_label'], $r['start'], $r['end'], $r['submitted'], $r['notes'],
                ]),
                default => $requestRows->map(fn ($r, $i) => [
                    $i + 1, $r['employee'], $r['department'], $r['leave_type'], $r['start'], $r['end'], $r['days'],
                    $r['in_period'], $r['status_label'], $r['requested_at'],
                ]),
            };

            return response()->json(['rows' => $out->values()->all()]);
        }

        // في إجازةٍ اليوم: موافقٌ عليها ويقع اليومُ بين بدايتها ونهايتها — مستقلٌّ عن المدى
        $today = Carbon::today()->toDateString();
        $onLeaveToday = LeaveRequest::whereIn('employee_id', $ids)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$today])
            ->distinct()->count('employee_id');

        $approvedRows = $requestRows->where('status', 'approved');
        $pendingRows = $requestRows->where('status', 'pending');

        return view('hr::reports.attendance.leaves', [
            'reportView' => $view,
            'requestRows' => $view === 'requests' ? $this->hrPaginate($requestRows, $request) : null,
            'perEmployee' => $view === 'employees' ? $this->hrPaginate($perEmployee, $request) : null,
            'permissionRows' => $view === 'permissions' ? $this->hrPaginate($permissionRows, $request) : null,
            'totals' => [
                'approved_days' => (int) $approvedRows->sum('in_period'),
                'approved_count' => $approvedRows->count(),
                'pending' => $pendingRows->count(),
                'pending_days' => (int) $pendingRows->sum('days'),
                'permissions' => $permissionRows->count(),
                'late_permissions' => $permissionRows->where('kind', LeavePermissions::LATE_ARRIVAL)->count(),
                'early_permissions' => $permissionRows->where('kind', LeavePermissions::EARLY_LEAVE)->count(),
                'on_leave_today' => $onLeaveToday,
            ],
            // الرسمان من كلّ الطلبات المعتمدة لا من الصفحة: من أخذ أكثر، ومن أيّ نوع
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأكثر أيام إجازة معتمدة', 'icon' => 'plane-departure', 'color' => '#3b82f6', 'unit' => 'يوم',
                    'items' => $approvedRows->groupBy('employee_id')
                        ->map(fn ($g) => ['name' => $g->first()['employee'], 'value' => $g->sum('in_period')])->values()->all(),
                ],
                'donut' => [
                    'title' => 'أيام الإجازة المعتمدة حسب النوع', 'unit' => 'يوم',
                    'groups' => $approvedRows->groupBy('leave_type')
                        ->map(fn ($g, $type) => ['label' => $type, 'count' => (int) $g->sum('in_period')])
                        ->sortByDesc('count')->values()
                        ->map(fn ($g, $i) => $g + ['color' => ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#f97316', '#64748b'][$i % 8]])
                        ->all(),
                ],
            ],
            'balanceYear' => $balanceYear,
            'statusLabels' => self::STATUS_LABELS,
            'permissionKinds' => LeavePermissions::LEAVE_TYPES,
            'leaveTypes' => LeaveType::orderBy('name')->get(['id', 'name']),
            'fromDate' => $period['from'],
            'toDate' => $period['to'],
            'periodLabel' => $period['label'],
            'periodContext' => $period['context'],
        ] + $this->hrReportOptions());
    }

    /**
     * مدى التقرير: كـ`hrReportPeriod()` لكن الافتراضُ الشهرُ كاملاً، ولا قصَّ عند اليوم
     * — الإجازاتُ المعتمدة القادمة جزءٌ من السؤال.
     *
     * @return array{from: Carbon, to: Carbon, context: array<string, mixed>, label: string}
     */
    private function leavePeriod(Request $request): array
    {
        if (! $request->hasAny(['from_date', 'to_date', 'accounting_period_id', 'fiscal_year_id'])) {
            $request->merge([
                'from_date' => Carbon::today()->startOfMonth()->toDateString(),
                'to_date' => Carbon::today()->endOfMonth()->toDateString(),
            ]);
        }

        $context = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $from = Carbon::parse($context['start'])->startOfDay();
        $to = Carbon::parse($context['end'])->endOfDay();

        return [
            'from' => $from,
            'to' => $to,
            'context' => $context,
            'label' => 'من '.$from->format('d/m/Y').' إلى '.$to->format('d/m/Y'),
        ];
    }

    /**
     * أيامُ الطلب الواقعةُ داخل المدى: كلُّ أيامه إن وقع كلُّه فيه، وإلا أيامُ
     * التقاطع التقويمية بحدٍّ أعلى هو `days` المسجَّل (قد لا يحتسب العطل).
     */
    private function daysInPeriod(LeaveRequest $l, Carbon $from, Carbon $to): int
    {
        $days = (int) $l->days;
        if (! $l->start_date) {
            return $days;
        }

        $start = $l->start_date->copy()->startOfDay();
        $end = ($l->end_date ?? $l->start_date)->copy()->startOfDay();
        if ($start->gte($from) && $end->lte($to)) {
            return $days;
        }

        $overlap = (int) $start->max($from)->diffInDays($end->min($to)) + 1;

        return max(0, min($days, $overlap));
    }
}
