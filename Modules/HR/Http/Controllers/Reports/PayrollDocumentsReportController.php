<?php

namespace Modules\HR\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\HR\Http\Controllers\Concerns\BuildsHrReports;
use Modules\HR\Http\Controllers\Concerns\ExportsHrReports;

/**
 * تقاريرُ مستندات الرواتب — بتصميم تقارير العملاء.
 *
 *   ١) «قسائم الرواتب» (`slipsRegister`): القسيمةُ اتفاقُ أجرٍ مستمرّ
 *      (`salary_slips`) — أساسيُّها وبدلاتُها وخصمُها الثابت وصافيها المتوقَّع
 *      وآخرُ صرفٍ تمّ عليها. وعرضٌ ثانٍ يجمعها لكلّ قسم (كان تقريرَ
 *      «قسائم الراتب حسب القسم» المستقلّ، فصار عرضاً هنا).
 *   ٢) «مسيرات الرواتب» (`sheets`): المسيرُ مظلّةٌ زمنية (`payrolls`) —
 *      من يشمل، وكم قسيمةً فيه، وما صُرف من قسائمه في المدى، وما ينتظر.
 *   ٣) «صرف الرواتب» (`payments`): ما خرج نقداً فعلاً — `payroll_runs`
 *      المصروفة، لكلّ دفعة أو لكلّ موظف أو لكلّ خزينة.
 *
 * الصافي المتوقَّع يُحسب كما يحسبه الصرف (`PayrollCalculator`): الأساسي +
 * بنودُ الاستحقاق اليدوية على القسيمة − بنودُ الخصم اليدوية عليها. والبنودُ
 * المولَّدة (الإضافي والتأمينات والمكافآت، `origin`) تُحسب يومَ الصرف فلا
 * تدخله، ولا خصمياتُ جدول `deductions` ولا السلف.
 */
class PayrollDocumentsReportController extends Controller
{
    use BuildsHrReports;
    use ExportsHrReports;

    /** القسيمةُ السارية: معتمدةٌ أو نشطة — منها تُولَّد الصرفات */
    private const LIVE_SLIP = [SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE];

    /** دورةُ القبض في المسير — كما في شاشة المسيرات */
    private const CYCLES = [1 => 'شهري', 2 => 'أسبوعي', 3 => 'سنوي', 4 => 'ربع سنوي', 5 => 'كل أسبوعين'];

    private const FREQUENCIES = [SalarySlip::FREQUENCY_MONTHLY => 'شهري', SalarySlip::FREQUENCY_WEEKLY => 'أسبوعي'];

    public function __construct()
    {
        $this->middleware('permission.role:hr_reports.payroll');

        // تنزيلُ الملفّ (xlsx/pdf) صلاحيةٌ فوق صلاحية التقرير — كما في `HRReportsController`
        $this->middleware(function ($request, $next) {
            if ($request->filled('export') && $request->input('export') !== 'rows' && ! hasPermission('hr_reports.export')) {
                abort(403, 'ليس لديك صلاحية تصدير تقارير الموارد البشرية.');
            }

            return $next($request);
        });
    }

    /* ═══════════════════════ ١) قسائم الرواتب ═══════════════════════ */

    public function slipsRegister(Request $request)
    {
        $view = $request->input('view') === 'by_department' ? 'by_department' : 'slips';
        $ids = $this->hrReportEmployees($request, false)->pluck('id');

        $slips = SalarySlip::query()
            ->whereIn('employee_id', $ids)
            ->with([
                'employee', // كاملاً: `scheduled_payment_day` قد لا يكون عموداً بعد
                'employee.department:id,name',
                'payroll:id,name,status',
                'salaryItems:id,salary_slips_id,name,type,amount,account_id,origin',
            ])
            ->when($request->filled('slip_status'), fn ($q) => $q->where('status', $request->input('slip_status')))
            ->when($request->input('payroll_id') === 'none', fn ($q) => $q->whereNull('payroll_id'))
            ->when(is_numeric($request->input('payroll_id')), fn ($q) => $q->where('payroll_id', (int) $request->input('payroll_id')))
            ->get();

        $lastRuns = $this->lastDisbursedRuns($slips->pluck('id'));

        $rows = $slips->map(function (SalarySlip $slip) use ($lastRuns) {
            $amounts = $this->slipAmounts($slip);
            $last = $lastRuns->get($slip->id);

            return [
                'slip_id' => $slip->id,
                'employee_id' => $slip->employee_id,
                'employee' => $slip->employee?->full_name ?: '#'.$slip->employee_id,
                'department_id' => $slip->employee?->department_id,
                'department' => $slip->employee?->department->name ?? 'بلا قسم',
                'code' => $slip->code ?: '#'.$slip->id,
                'sheet' => $slip->payroll->name ?? 'بلا مسير',
                'basic' => $amounts['basic'],
                'allowances' => $amounts['allowances'],
                'deductions' => $amounts['deductions'],
                'net' => $amounts['net'],
                'monthly_net' => $this->monthly($amounts['net'], $slip->frequency),
                'monthly_basic' => $this->monthly($amounts['basic'], $slip->frequency),
                'monthly_allowances' => $this->monthly($amounts['allowances'], $slip->frequency),
                'monthly_deductions' => $this->monthly($amounts['deductions'], $slip->frequency),
                'frequency' => self::FREQUENCIES[$slip->frequency] ?? ($slip->frequency ?: 'شهري'),
                'pay_day' => $slip->getEffectivePaymentDay(),
                'status' => $slip->status,
                'status_label' => $slip->status_label,
                'live' => in_array($slip->status, self::LIVE_SLIP, true),
                'start' => $slip->start_date?->toDateString() ?? '—',
                'end' => $slip->end_date?->toDateString() ?? 'مفتوحة',
                'last_run' => $last ? $last->period_label : '—',
                'last_run_date' => $last ? $this->runDate($last)->toDateString() : null,
                'last_run_net' => $last ? (float) $last->net_salary : null,
            ];
        })->sortBy([['live', 'desc'], ['employee', 'asc']])->values();

        $live = $rows->where('live', true);

        // ── لكلّ قسم ──
        $byDepartment = $rows->groupBy('department')->map(function (Collection $group, $name) {
            $liveRows = $group->where('live', true);

            return [
                'department_id' => $group->first()['department_id'],
                'department' => $name,
                'employees' => $group->pluck('employee_id')->unique()->count(),
                'slips' => $group->count(),
                'live' => $liveRows->count(),
                'basic' => (float) $liveRows->sum('monthly_basic'),
                'allowances' => (float) $liveRows->sum('monthly_allowances'),
                'deductions' => (float) $liveRows->sum('monthly_deductions'),
                'net' => (float) $liveRows->sum('monthly_net'),
            ];
        })->sortByDesc('net')->values();

        $slipHeads = ['#', 'الموظف', 'القسم', 'رقم القسيمة', 'المسير', 'الأساسي', 'البدلات', 'الخصم الثابت', 'الصافي المتوقع', 'الدورة', 'يوم الصرف', 'الحالة', 'البداية', 'النهاية', 'آخر صرف'];
        $slipLine = fn ($r, $i) => [
            $i + 1, $r['employee'], $r['department'], $r['code'], $r['sheet'], number_format($r['basic'], 2),
            number_format($r['allowances'], 2), number_format($r['deductions'], 2), number_format($r['net'], 2),
            $r['frequency'], $r['pay_day'], $r['status_label'], $r['start'], $r['end'],
            $r['last_run_date'] ? $r['last_run'].' ('.$r['last_run_date'].')' : '—',
        ];
        $deptLine = fn ($r, $i) => [
            $i + 1, $r['department'], $r['employees'], $r['slips'], $r['live'], number_format($r['basic'], 2),
            number_format($r['allowances'], 2), number_format($r['deductions'], 2), number_format($r['net'], 2),
        ];

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $view === 'slips'
                ? $rows->map($slipLine)->all()
                : $byDepartment->map($deptLine)->all(),
            ]);
        }

        // تصديرُ الملفّ القديم (كان على «قسائم الراتب حسب القسم») — يبقى يعمل
        if ($file = $this->exportIfRequested($request, 'قسائم الرواتب', $slipHeads, fn () => $rows->map($slipLine)->all(), 'salary_slips_register')) {
            return $file;
        }

        return view('hr::reports.payroll_docs.slips', [
            'reportView' => $view,
            'rows' => $view === 'slips' ? $this->hrPaginate($rows, $request) : null,
            'byDepartment' => $view === 'by_department' ? $this->hrPaginate($byDepartment, $request) : null,
            'totals' => [
                'slips' => $rows->count(),
                'live' => $live->count(),
                'employees' => $live->pluck('employee_id')->unique()->count(),
                'basic' => (float) $live->sum('monthly_basic'),
                'allowances' => (float) $live->sum('monthly_allowances'),
                'deductions' => (float) $live->sum('monthly_deductions'),
                'net' => (float) $live->sum('monthly_net'),
                'no_sheet' => $live->where('sheet', 'بلا مسير')->count(),
            ],
            // الرسمان من كلّ القسائم السارية لا من الصفحة: أعلى الصوافي، وتوزيعُها على الأقسام
            'hrCharts' => [
                'bar' => [
                    'title' => 'أعلى صافي متوقع شهرياً', 'icon' => 'money-check-alt', 'color' => '#10b981', 'money' => true, 'unit' => 'ر.س',
                    'items' => $live->groupBy('employee_id')
                        ->map(fn ($g) => ['name' => $g->first()['employee'], 'value' => $g->sum('monthly_net')])->values()->all(),
                ],
                'donut' => [
                    'title' => 'القسائم السارية حسب القسم', 'unit' => 'قسيمة',
                    'groups' => $this->chartGroups($live->groupBy('department')
                        ->map(fn ($g, $name) => ['label' => $name, 'count' => $g->count(), 'sum' => (float) $g->sum('monthly_net')])),
                ],
            ],
            'slipStatuses' => SalarySlip::statuses(),
            'sheetOptions' => Payroll::orderByDesc('start_date')->get(['id', 'name']),
            'slipHeads' => $slipHeads,
            'periodContext' => [],
        ] + $this->hrReportOptions());
    }

    /* ═══════════════════════ ٢) مسيرات الرواتب ═══════════════════════ */

    public function sheets(Request $request)
    {
        $view = $request->input('view') === 'details' ? 'details' : 'summary';
        $period = $this->hrReportPeriod($request);
        $ids = $this->hrReportEmployees($request, false)->pluck('id')->map(fn ($id) => (int) $id);
        $idSet = $ids->flip();

        // مقيَّدٌ بنطاقٍ أو فلترِ موظفين؟ — فالمسيرُ الذي لا يشمل أحداً منهم لا يُعرض
        $restricted = $this->accessibleEmployeeIds() !== null
            || $request->filled('department_id') || $request->filled('job_title_id')
            || $request->filled('branch_id') || $request->filled('employee_id')
            || in_array($request->input('employee_status'), ['active', 'inactive'], true);

        $payrolls = Payroll::query()
            ->with(['approver:id,name'])
            ->when($request->filled('sheet_status'), fn ($q) => $request->input('sheet_status') === Payroll::STATUS_ACTIVE
                ? $q->where(fn ($w) => $w->where('status', Payroll::STATUS_ACTIVE)->orWhereNull('status'))
                : $q->where('status', $request->input('sheet_status')))
            ->when($request->filled('payroll_id'), fn ($q) => $q->where('id', (int) $request->input('payroll_id')))
            ->orderByDesc('start_date')->orderByDesc('id')
            ->get();

        $slips = SalarySlip::query()
            ->whereIn('payroll_id', $payrolls->pluck('id'))
            ->whereIn('employee_id', $ids)
            ->with([
                'employee:id,first_name,middle_name,nickname,department_id',
                'employee.department:id,name',
                'salaryItems:id,salary_slips_id,name,type,amount,account_id,origin',
            ])
            ->get();
        $slipsByPayroll = $slips->groupBy('payroll_id');

        $runs = PayrollRun::query()
            ->whereIn('salary_slip_id', $slips->pluck('id'))
            ->get(['id', 'salary_slip_id', 'employee_id', 'status', 'held_at', 'disbursed_at', 'scheduled_date', 'period_from', 'period_to', 'net_salary']);
        $runsBySlip = $runs->groupBy('salary_slip_id');

        $from = $period['from'];
        $to = $period['to'];
        $inPeriod = fn (PayrollRun $r) => $r->isDisbursed() && $this->runDate($r)->between($from, $to);

        $summary = collect();
        $details = collect();
        $employeesCovered = collect();

        foreach ($payrolls as $payroll) {
            $sheetSlips = $slipsByPayroll->get($payroll->id, collect());
            foreach ($sheetSlips as $slip) {
                $slip->setRelation('payroll', $payroll);
            }
            $members = collect($payroll->memberEmployeeIds())->filter(fn ($id) => $idSet->has($id));
            $employeeIds = $members->merge($sheetSlips->pluck('employee_id')->map(fn ($id) => (int) $id))->unique()->values();

            if ($restricted && $employeeIds->isEmpty()) {
                continue;
            }

            $liveSlips = $sheetSlips->filter(fn ($s) => in_array($s->status, self::LIVE_SLIP, true));
            $sheetRuns = $sheetSlips->flatMap(fn ($s) => $runsBySlip->get($s->id, collect()));
            $paid = $sheetRuns->filter($inPeriod);
            $pending = $sheetRuns->where('status', PayrollRun::STATUS_PENDING);
            $employeesCovered = $employeesCovered->merge($employeeIds);

            $summary->push([
                'payroll_id' => $payroll->id,
                'name' => $payroll->name,
                'cycle' => self::CYCLES[(int) $payroll->receiving_cycle] ?? '—',
                'period' => ($payroll->start_date?->toDateString() ?? '—').' → '.($payroll->end_date?->toDateString() ?? 'مفتوح'),
                'employees' => $employeeIds->count(),
                'slips' => $sheetSlips->count(),
                'live_slips' => $liveSlips->count(),
                'basic' => (float) $liveSlips->sum(fn ($s) => $this->monthly((float) $s->basic_amount, $s->frequency)),
                'net' => (float) $liveSlips->sum(fn ($s) => $this->monthly($this->slipAmounts($s)['net'], $s->frequency)),
                'paid' => (float) $paid->sum('net_salary'),
                'paid_count' => $paid->count(),
                'pending' => $pending->count(),
                'held' => $pending->whereNotNull('held_at')->count(),
                'approved' => $payroll->isActive(),
                'status' => $payroll->status ?? Payroll::STATUS_ACTIVE,
                'status_label' => $payroll->status_label,
                'approved_at' => $payroll->approved_at?->format('Y-m-d'),
                'approved_by' => $payroll->approver->name ?? null,
            ]);

            if ($view !== 'details') {
                continue;
            }

            // ── التفاصيل: موظفٌ لكلّ صف في المسير — ومن يشمله المسير بلا قسيمةٍ يظهر كذلك ──
            $slipEmployees = $sheetSlips->pluck('employee_id')->map(fn ($id) => (int) $id)->flip();
            foreach ($sheetSlips as $slip) {
                $amounts = $this->slipAmounts($slip);
                $slipRuns = $runsBySlip->get($slip->id, collect());
                $last = $slipRuns->sortByDesc(fn ($r) => $r->period_to?->toDateString())->first();
                if ($last) {
                    $last->setRelation('slip', $slip);
                }

                $details->push([
                    'sheet' => $payroll->name,
                    'employee_id' => $slip->employee_id,
                    'employee' => $slip->employee?->full_name ?: '#'.$slip->employee_id,
                    'department' => $slip->employee?->department->name ?? '—',
                    'slip_id' => $slip->id,
                    'code' => $slip->code ?: '#'.$slip->id,
                    'slip_status' => $slip->status,
                    'slip_status_label' => $slip->status_label,
                    'basic' => $amounts['basic'],
                    'allowances' => $amounts['allowances'],
                    'deductions' => $amounts['deductions'],
                    'net' => $amounts['net'],
                    'last_run' => $last ? $last->stage_label : 'لم تُولَّد صرفة',
                    'last_run_stage' => $last ? $last->stage() : null,
                    'last_run_period' => $last ? $last->period_label : null,
                    'paid' => (float) $slipRuns->filter($inPeriod)->sum('net_salary'),
                ]);
            }

            $missing = $members->reject(fn ($id) => $slipEmployees->has($id));
            if ($missing->isNotEmpty()) {
                $names = Employee::with('department:id,name')->whereIn('id', $missing)->get(['id', 'first_name', 'middle_name', 'nickname', 'department_id']);
                foreach ($names as $emp) {
                    $details->push([
                        'sheet' => $payroll->name, 'employee_id' => $emp->id, 'employee' => $emp->full_name,
                        'department' => $emp->department->name ?? '—', 'slip_id' => null, 'code' => '—',
                        'slip_status' => null, 'slip_status_label' => 'لا قسيمة', 'basic' => 0.0, 'allowances' => 0.0,
                        'deductions' => 0.0, 'net' => 0.0, 'last_run' => '—', 'last_run_stage' => null,
                        'last_run_period' => null, 'paid' => 0.0,
                    ]);
                }
            }
        }

        $details = $details->sortBy([['sheet', 'asc'], ['employee', 'asc']])->values();

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $view === 'summary'
                ? $summary->map(fn ($r, $i) => [
                    $i + 1, $r['name'], $r['cycle'], $r['period'], $r['employees'], $r['slips'], number_format($r['basic'], 2),
                    number_format($r['net'], 2), number_format($r['paid'], 2), $r['paid_count'], $r['pending'],
                    $r['status_label'].($r['approved_at'] ? ' — '.$r['approved_at'] : ''),
                ])->all()
                : $details->map(fn ($r, $i) => [
                    $i + 1, $r['sheet'], $r['employee'], $r['department'], $r['code'], $r['slip_status_label'],
                    number_format($r['basic'], 2), number_format($r['allowances'], 2), number_format($r['deductions'], 2),
                    number_format($r['net'], 2), $r['last_run'].($r['last_run_period'] ? ' ('.$r['last_run_period'].')' : ''),
                    $r['paid'] > 0 ? number_format($r['paid'], 2) : '—',
                ])->all(),
            ]);
        }

        return view('hr::reports.payroll_docs.sheets', [
            'reportView' => $view,
            'summary' => $view === 'summary' ? $this->hrPaginate($summary, $request) : null,
            'details' => $view === 'details' ? $this->hrPaginate($details, $request) : null,
            'totals' => [
                'sheets' => $summary->count(),
                'approved' => $summary->where('approved', true)->count(),
                'employees' => $employeesCovered->unique()->count(),
                'paid' => (float) $summary->sum('paid'),
                'paid_count' => $summary->sum('paid_count'),
                'pending' => $summary->sum('pending'),
                'held' => $summary->sum('held'),
            ],
            // الرسمان من كلّ المسيرات (الملخّص يُبنى كاملاً في العرضين): ما صُرف لكلّ مسير، وحالُ صرفاته
            'hrCharts' => [
                'bar' => [
                    'title' => 'المصروف في الفترة لكل مسير', 'icon' => 'chart-bar', 'color' => '#3b82f6', 'money' => true, 'unit' => 'ر.س',
                    'items' => $summary->map(fn ($r) => ['name' => $r['name'], 'value' => $r['paid']])->all(),
                ],
                'donut' => [
                    'title' => 'صرفات المسيرات حسب الحالة', 'unit' => 'صرفة',
                    'groups' => [
                        ['label' => 'مصروفة في الفترة', 'count' => $summary->sum('paid_count'), 'sum' => (float) $summary->sum('paid'), 'color' => '#10b981'],
                        ['label' => 'معلّقة', 'count' => $summary->sum('pending') - $summary->sum('held'), 'color' => '#f59e0b'],
                        ['label' => 'موقوفة', 'count' => $summary->sum('held'), 'color' => '#ef4444'],
                    ],
                ],
            ],
            'sheetStatuses' => Payroll::statuses(),
            'sheetOptions' => Payroll::orderByDesc('start_date')->get(['id', 'name']),
            'fromDate' => $period['from'],
            'toDate' => $period['to'],
            'periodLabel' => $period['label'],
            'periodContext' => $period['context'],
        ] + $this->hrReportOptions());
    }

    /* ═══════════════════════ ٣) صرف الرواتب ═══════════════════════ */

    /**
     * ما صُرف فعلاً — `payroll_runs` بحالة «مدفوعة» فقط. تاريخُها = يومُ الصرف،
     * أو يومُ الصرف المجدول ثمّ نهايةُ الفترة للسجلات القديمة بلا `disbursed_at`.
     * يُستدعى من `HRReportsController::paymentsReport` (المسار `hr.reports.payments`).
     */
    public function payments(Request $request)
    {
        $view = in_array($request->input('view'), ['employees', 'treasuries'], true) ? $request->input('view') : 'runs';
        $period = $this->hrReportPeriod($request);
        $ids = $this->hrReportEmployees($request, false)->pluck('id');

        $runs = PayrollRun::query()
            ->whereIn('employee_id', $ids)
            ->where('status', PayrollRun::STATUS_DISBURSED)
            ->whereRaw('DATE(COALESCE(disbursed_at, scheduled_date, period_to)) BETWEEN ? AND ?', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->when($request->filled('treasury_id'), fn ($q) => $q->where('treasury_id', (int) $request->input('treasury_id')))
            ->with([
                'employee:id,first_name,middle_name,nickname,department_id',
                'employee.department:id,name',
                'treasury:id,name',
                'journalEntry:id,reference_number',
            ])
            ->get();

        $lines = $runs->map(fn (PayrollRun $run) => [
            'run_id' => $run->id,
            'code' => $run->code ?: 'PRN-'.$run->id,
            'date' => $this->runDate($run)->toDateString(),
            'employee_id' => $run->employee_id,
            'employee' => $run->employee?->full_name ?: '#'.$run->employee_id,
            'department' => $run->employee?->department->name ?? '—',
            'period' => $run->period_label,
            'basic' => (float) $run->basic_amount,
            'additions' => (float) $run->total_additions,
            'deductions' => (float) $run->total_deductions,
            'advances' => (float) $run->total_advances_deducted,
            'net' => (float) $run->net_salary,
            'treasury_id' => $run->treasury_id,
            'treasury' => $run->treasury->name ?? '—',
            'journal' => $run->journalEntry->reference_number ?? ($run->journal_entry_id ? '#'.$run->journal_entry_id : '—'),
        ])->sortBy([['date', 'desc'], ['employee', 'asc']])->values();

        $sumUp = fn (Collection $g) => [
            'runs' => $g->count(),
            'employees' => $g->pluck('employee_id')->unique()->count(),
            'basic' => (float) $g->sum('basic'),
            'additions' => (float) $g->sum('additions'),
            'deductions' => (float) $g->sum('deductions'),
            'advances' => (float) $g->sum('advances'),
            'net' => (float) $g->sum('net'),
            'first' => $g->min('date'),
            'last' => $g->max('date'),
        ];

        $byEmployee = $lines->groupBy('employee_id')->map(fn ($g) => [
            'employee_id' => $g->first()['employee_id'],
            'employee' => $g->first()['employee'],
            'department' => $g->first()['department'],
        ] + $sumUp($g))->sortBy('employee')->values();

        $byTreasury = $lines->groupBy(fn ($l) => (string) $l['treasury_id'])->map(fn ($g) => [
            'treasury_id' => $g->first()['treasury_id'],
            'treasury' => $g->first()['treasury'],
        ] + $sumUp($g))->sortByDesc('net')->values();

        // المعلّقةُ حتى نهاية المدى — لم تُصرف بعد، ومنها الموقوفةُ بقرار
        $pending = PayrollRun::query()
            ->whereIn('employee_id', $ids)
            ->where('status', PayrollRun::STATUS_PENDING)
            ->whereDate('scheduled_date', '<=', $period['to']->toDateString())
            ->get(['id', 'held_at', 'net_salary', 'basic_amount']);

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => match ($view) {
                'employees' => $byEmployee->map(fn ($r, $i) => [
                    $i + 1, $r['employee'], $r['department'], $r['runs'], number_format($r['basic'], 2), number_format($r['additions'], 2),
                    number_format($r['deductions'], 2), number_format($r['advances'], 2), number_format($r['net'], 2), $r['last'],
                ])->all(),
                'treasuries' => $byTreasury->map(fn ($r, $i) => [
                    $i + 1, $r['treasury'], $r['runs'], $r['employees'], number_format($r['net'], 2), $r['first'], $r['last'],
                ])->all(),
                default => $lines->map(fn ($r, $i) => [
                    $i + 1, $r['date'], $r['code'], $r['employee'], $r['department'], $r['period'], number_format($r['basic'], 2),
                    number_format($r['additions'], 2), number_format($r['deductions'], 2), number_format($r['advances'], 2),
                    number_format($r['net'], 2), $r['treasury'], $r['journal'],
                ])->all(),
            }]);
        }

        // تصديرُ الملفّ القديم (xlsx/pdf) — العناوينُ نفسُها التي كان يُخرجها التقرير
        if ($file = $this->exportIfRequested(
            $request,
            'مدفوعات رواتب الموظفين',
            ['الموظف', 'القسم', 'تاريخ الصرف', 'المرجع', 'الفترة', 'الخزينة', 'الأساسي', 'الخصميات', 'السلف', 'الصافي'],
            fn () => $lines->map(fn ($r) => [
                $r['employee'], $r['department'], $r['date'], $r['code'], $r['period'], $r['treasury'],
                $r['basic'], $r['deductions'], $r['advances'], $r['net'],
            ])->all(),
            'payroll_disbursements'
        )) {
            return $file;
        }

        return view('hr::reports.payroll_docs.payments', [
            'reportView' => $view,
            'lines' => $view === 'runs' ? $this->hrPaginate($lines, $request) : null,
            'byEmployee' => $view === 'employees' ? $this->hrPaginate($byEmployee, $request) : null,
            'byTreasury' => $view === 'treasuries' ? $this->hrPaginate($byTreasury, $request) : null,
            'totals' => $sumUp($lines) + [
                'pending' => $pending->count(),
                'pending_net' => (float) $pending->sum(fn ($r) => (float) ($r->net_salary ?: $r->basic_amount)),
                'held' => $pending->whereNotNull('held_at')->count(),
            ],
            // الرسمان من كلّ الصرفات لا من الصفحة: من قبض أكثر، ومن أيّ خزينة خرج المال
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأعلى صافياً مصروفاً', 'icon' => 'hand-holding-usd', 'color' => '#10b981', 'money' => true, 'unit' => 'ر.س',
                    'items' => $byEmployee->map(fn ($r) => ['name' => $r['employee'], 'value' => $r['net']])->all(),
                ],
                'donut' => [
                    'title' => 'المصروف حسب الخزينة', 'unit' => 'دفعة',
                    'groups' => $this->chartGroups($byTreasury->map(fn ($r) => ['label' => $r['treasury'], 'count' => $r['runs'], 'sum' => $r['net']])),
                ],
            ],
            'treasuryOptions' => Account::whereIn('id', PayrollRun::whereNotNull('treasury_id')->distinct()->pluck('treasury_id'))->orderBy('name')->get(['id', 'name']),
            'fromDate' => $period['from'],
            'toDate' => $period['to'],
            'periodLabel' => $period['label'],
            'periodContext' => $period['context'],
        ] + $this->hrReportOptions());
    }

    /* ═══════════════════════ أدوات ═══════════════════════ */

    /**
     * أرقامُ القسيمة كما يحسبها الصرف (`PayrollCalculator::calculate`):
     * بنودُ الاستحقاق اليدوية تُجمع لكلّ (حساب|اسم)، والمكرَّرُ بالمبلغ نفسِه
     * يُعدّ مرّةً واحدة؛ وبنودُ الخصم اليدوية لا تتجاوز ما بقي من الاستحقاق.
     *
     * @return array{basic: float, allowances: float, deductions: float, net: float}
     */
    private function slipAmounts(SalarySlip $slip): array
    {
        $basic = (float) $slip->basic_amount;
        $items = $slip->salaryItems->whereNull('origin');

        $allowances = (float) $items->where('type', SalaryItem::TYPE_ADDITION)
            ->groupBy(fn ($i) => $i->account_id.'|'.$i->name)
            ->sum(fn ($g) => $g->pluck('amount')->unique()->count() === 1 ? (float) $g->first()->amount : (float) $g->sum('amount'));

        $room = $basic + $allowances;
        $deductions = 0.0;
        foreach ($items->where('type', SalaryItem::TYPE_DEDUCTION)->sortBy('id') as $item) {
            $amount = round(min((float) $item->amount, $room), 2);
            if ($amount > 0) {
                $deductions += $amount;
                $room -= $amount;
            }
        }

        return [
            'basic' => $basic,
            'allowances' => round($allowances, 2),
            'deductions' => round($deductions, 2),
            'net' => round(max(0, $basic + $allowances - $deductions), 2),
        ];
    }

    /**
     * مجموعاتُ حلقة التوزيع: الأكبرُ مبلغاً أولاً بلونٍ لكلٍّ منها، وما بعد الثامنة «أخرى».
     *
     * @param  Collection<int|string, array{label: string, count: int, sum: float}>  $groups
     */
    private function chartGroups(Collection $groups): array
    {
        $palette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#f97316', '#64748b'];
        $sorted = $groups->sortByDesc('sum')->values();
        $out = $sorted->take(8)->map(fn ($g, $i) => $g + ['color' => $palette[$i]]);
        if ($sorted->count() > 8) {
            $rest = $sorted->slice(8);
            $out->push(['label' => 'أخرى', 'count' => $rest->sum('count'), 'sum' => (float) $rest->sum('sum'), 'color' => '#94a3b8']);
        }

        return $out->all();
    }

    /** ما يعادل المبلغَ شهرياً — الأسبوعيُّ × ٥٢ ÷ ١٢ */
    private function monthly(float $amount, ?string $frequency): float
    {
        return $frequency === SalarySlip::FREQUENCY_WEEKLY ? round($amount * 52 / 12, 2) : $amount;
    }

    /** تاريخُ الصرفة: يومُ صرفها، وإلا المجدول، وإلا نهايةُ فترتها */
    private function runDate(PayrollRun $run): Carbon
    {
        return Carbon::parse($run->disbursed_at ?? $run->scheduled_date ?? $run->period_to);
    }

    /** آخرُ صرفةٍ مدفوعة لكلّ قسيمة */
    private function lastDisbursedRuns(Collection $slipIds): Collection
    {
        return PayrollRun::query()
            ->whereIn('salary_slip_id', $slipIds)
            ->where('status', PayrollRun::STATUS_DISBURSED)
            ->get(['id', 'salary_slip_id', 'period_from', 'period_to', 'disbursed_at', 'scheduled_date', 'net_salary'])
            ->groupBy('salary_slip_id')
            ->map(fn ($g) => $g->sortByDesc(fn ($r) => $this->runDate($r)->timestamp)->first());
    }
}
