<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Log as ModelsLog;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRequest;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Services\PayrollDisbursementService;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use App\Support\PayrollSettings;
use App\Traits\CalculationFormulaTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HR\Http\Controllers\Concerns\GeneratesHrDocumentNumbers;
use Modules\HR\Http\Controllers\Concerns\PrintsPayrollDocuments;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Requests\SalarySlipRequest;
use Modules\HR\Support\BranchTree;

/**
 * إدارة قسائم الرواتب (Salary Slips) — اتفاقيات الراتب المستمرة.
 *
 * مسؤوليات هذا الكنترولر:
 *   - إنشاء/تعديل/اعتماد/إيقاف القسيمة
 *   - عرض الموظفين النشطين + سلفهم + خصومات معتمدة
 *   - ربط القسيمة بالعقد الفعّال
 *   - طباعتها بقوالب «قوالب المستندات»
 *
 * ❌ لا يصرف الرواتب — الصرف في PayrollRunController.
 * ❌ لا يتعامل مع العمولات إطلاقاً.
 * ❌ لا ينشئ قيوداً محاسبية ولا سندات صرف.
 *
 * **حسابُ القسيمة** — مصدرٌ واحدٌ هو `computeSlip()`:
 *
 *   الإجمالي = الأساسي + مجموع بنود الاستحقاق
 *   الخصومات = مجموع بنود الخصم
 *   الصافي   = الإجمالي − الخصومات
 *
 * والأساسيُّ ليس شرطاً بذاته: موظفٌ بلا عقدٍ يُحسب راتبُه من بنود
 * الاستحقاق وحدها. الشرطُ أن يكون للقسيمة **مبلغٌ موجب** — أساسيٌّ أو بندٌ.
 */
class SalarySlipController extends Controller
{
    use CalculationFormulaTrait;
    use GeneratesHrDocumentNumbers;
    use PrintsPayrollDocuments;
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        /*
            كلُّ فعلٍ بصلاحيته، وعلى من يسري يحدّده نطاقُ الرؤية
            (`visibleEmployeeIds()`): الموظفُ الذي يرى قسيمتَه وحدها يحمل
            `payslips.index/show/print` + `data_scope_own`.
        */
        $this->middleware('permission.role:payslips.index', ['only' => ['index']]);
        $this->middleware('permission.role:payslips.show', ['only' => ['show']]);
        $this->middleware('permission.role:payslips.create', ['only' => ['create', 'store']]);
        // بياناتُ راتب الموظف وموظفو المسير تُطلب بـ AJAX من نموذجَي الإنشاء والتعديل.
        $this->middleware('permission.role:payslips.create|payslips.edit', ['only' => [
            'getEmployeePayrollData', 'payrollEmployees',
        ]]);
        $this->middleware('permission.role:payslips.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:payslips.approve', ['only' => ['approve', 'stop']]);
        $this->middleware('permission.role:payslips.destroy', ['only' => ['destroy']]);
        // الطباعةُ بصلاحيتها — والنطاقُ يقصر صاحبَ `data_scope_own` على سجلّاته
        $this->middleware('permission.role:payslips.print', ['only' => ['print']]);
    }

    // ============================================================
    // نطاقُ الرؤية
    // ============================================================

    /**
     * الموظفون الذين يرى المستخدمُ قسائمهم.
     *
     * نطاقُ الرؤية وحده (`data_scope_*`) — لا صلاحيةَ «قسيمتي أنا»:
     * صاحبُ `data_scope_own` يرى قسائمَه هو، والأوسعُ نطاقاً من يشملهم.
     * القوائمُ تُقيَّد فوق ذلك بفرع الشريط العلويّ.
     *
     * @return array<int, int>|null
     */
    private function visibleEmployeeIds(bool $forList = false): ?array
    {
        return $forList ? $this->listEmployeeIds() : $this->accessibleEmployeeIds();
    }

    /**
     * المسيراتُ التي تضمّ موظفاً واحداً على الأقلّ من نطاق المستخدم — لقوائم
     * «المسير» في الفلتر والنموذج، فلا تُعرض أسماءُ مسيراتٍ لا يرى أحداً فيها.
     */
    private function visiblePayrolls(bool $forList = false)
    {
        $query = Payroll::query();
        $ids = $this->visibleEmployeeIds($forList);

        if ($ids !== null) {
            $ids = $ids ?: [0];
            $query->where(function ($q) use ($ids) {
                $q->whereHas('employees', fn ($e) => $e->whereIn('employees.id', $ids))
                    ->orWhereHas('salarySlips', fn ($s) => $s->whereIn('employee_id', $ids));
            });
        }

        return $query;
    }

    private function limitToVisible($query, string $column, bool $forList = false)
    {
        $ids = $this->visibleEmployeeIds($forList);

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    private function authorizeSlip(SalarySlip $slip): void
    {
        $ids = $this->visibleEmployeeIds();

        if ($ids !== null && ! in_array((int) $slip->employee_id, $ids, true)) {
            abort(403, 'هذه القسيمة خارج نطاق الموظفين المصرّح لك بالاطّلاع عليهم.');
        }
    }

    /** الخزائنُ القابلة للصرف منها — الحارسُ نفسُه الذي يسأله الصرف. */
    private function treasuries()
    {
        return TreasuryBalanceGuard::withdrawableAccounts();
    }

    /** بنودُ الراتب المعرَّفة (لا المنسوخة إلى قسيمةٍ أو عقد). */
    private function catalogItems(int $type)
    {
        return SalaryItem::query()->catalog()
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    // ============================================================
    // الشاشات
    // ============================================================

    public function index(Request $request)
    {
        $query = SalarySlip::with(['employee.department', 'employee.job_title', 'payroll:id,name', 'contract:id', 'payrollRuns:id,salary_slip_id,status'])
            ->orderByDesc('id');

        $this->limitToVisible($query, 'salary_slips.employee_id', true);

        $base = clone $query;

        if ($request->filled('status') && array_key_exists($request->status, SalarySlip::statuses())) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->employee_id);
        }
        if ($request->filled('payroll_id')) {
            $query->where('payroll_id', (int) $request->payroll_id);
        }
        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->whereHas('employee', function ($qe) use ($term) {
                    $qe->where('first_name', 'like', "%$term%")
                        ->orWhere('middle_name', 'like', "%$term%")
                        ->orWhere('nickname', 'like', "%$term%")
                        ->orWhere('employee_code', 'like', "%$term%");
                })->orWhere('code', 'like', "%$term%")->orWhere('id', $term);
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;
        $slips = $query->paginate($perPage)->withQueryString();

        // الإحصاءاتُ مقيَّدةٌ بنطاق المستخدم كالقائمة — لا الجدولُ كلُّه
        $visible = $this->visibleEmployeeIds(true);
        $runs = fn () => $visible === null ? PayrollRun::query() : PayrollRun::whereIn('employee_id', $visible ?: [0]);

        $statistics = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->whereIn('status', [SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE])->count(),
            'pending_runs' => $runs()->pending()->count(),
            'this_month_disbursed' => (float) $runs()->disbursed()
                ->whereMonth('disbursed_at', now()->month)
                ->whereYear('disbursed_at', now()->year)
                ->sum('net_salary'),
        ];

        $employees = $this->limitToVisible(
            Employee::query()->orderBy('first_name'),
            'employees.id',
            true
        )->get(['id', 'first_name', 'middle_name', 'nickname']);

        $payrolls = $this->visiblePayrolls(true)->orderByDesc('id')->get(['id', 'name']);
        $statuses = SalarySlip::statuses();

        return view('hr::salaries.salary_slip.index', compact('slips', 'statistics', 'employees', 'payrolls', 'statuses'));
    }

    public function create()
    {
        // مسيرات الرواتب النشطة فقط — لاختيارها يدوياً
        $payrolls = $this->visiblePayrolls()->where('status', Payroll::STATUS_ACTIVE)->orderBy('name')->get();

        // موظفو المسير المختار سابقاً (رجوعٌ بعد خطأ تحقّق) — تُملأ القائمةُ
        // منهم وحدهم، ولا تُعرض قائمةُ موظفي المنشأة كلِّها قبل اختيار مسير.
        // والمسيرُ والموظفُ قد يأتيان في العنوان من شاشة المسير («إنشاء قسيمة» عند موظف).
        $payrollId = (int) old('payroll_id', request('payroll_id'));
        $oldPayroll = $payrollId ? $payrolls->firstWhere('id', $payrollId) : null;
        $employees = $oldPayroll ? $this->payrollEmployeeRows($oldPayroll) : collect();

        return view('hr::salaries.salary_slip.create', [
            'payrolls' => $payrolls,
            'employees' => $employees,
            'additionItems' => $this->catalogItems(SalaryItem::TYPE_ADDITION),
            'deductionItems' => $this->catalogItems(SalaryItem::TYPE_DEDUCTION),
            'treasuries' => $this->treasuries(),
            'nextSlipCode' => $this->previewDocumentNumber('salary-slip'),
        ]);
    }

    /**
     * موظفو المسير الذين تجوز لهم قسيمة — لقائمة «الموظف» في النموذج.
     *
     * كانت القائمةُ كلَّ موظفي المنشأة النشطين، والتحقّقُ من أنّ الموظف في
     * المسير يقع بعد الضغط على «حفظ». صارت تُملأ من المسير المختار وحده.
     */
    public function payrollEmployees($payrollId)
    {
        $payroll = Payroll::findOrFail($payrollId);

        // مسيرٌ لا يضمّ أحداً من نطاق المستخدم لا يُكشف اسمُه ولا فترتُه بتغيير المعرّف
        if (! $this->visiblePayrolls()->whereKey($payroll->id)->exists()) {
            abort(403, 'هذا المسير خارج نطاق الموظفين المصرّح لك بهم.');
        }

        return response()->json([
            'payroll' => [
                'id' => $payroll->id,
                'name' => $payroll->name,
                'start_date' => optional($payroll->start_date)->toDateString(),
                'end_date' => optional($payroll->end_date)->toDateString(),
                'active' => $payroll->isActive(),
            ],
            'employees' => $this->payrollEmployeeRows($payroll)->values(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function payrollEmployeeRows(Payroll $payroll)
    {
        $query = Employee::query()
            ->with(['department:id,name', 'job_title:id,name'])
            ->active()
            ->whereIn('employees.id', $payroll->memberEmployeeIds() ?: [0])
            ->orderBy('first_name');

        $ids = $this->accessibleEmployeeIds();
        if ($ids !== null) {
            $query->whereIn('employees.id', $ids ?: [0]);
        }

        $withSlip = SalarySlip::where('payroll_id', $payroll->id)
            ->whereIn('status', [SalarySlip::STATUS_DRAFT, SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE])
            ->pluck('employee_id')->map(fn ($v) => (int) $v)->all();

        return $query->get()->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => trim($e->first_name.' '.$e->middle_name.' '.$e->nickname),
            'code' => $e->employee_code,
            'department' => optional($e->department)->name,
            'job_title' => optional($e->job_title)->name,
            'has_slip' => in_array((int) $e->id, $withSlip, true),
        ]);
    }

    /**
     * تحضير بيانات الموظف لشاشة إنشاء/تعديل قسيمة:
     *   - العقد الفعّال (الراتب الأساسي + البدلات)
     *   - السلف غير المسواة (مع رصيد حساب السلف)
     *   - الخصميات المعتمدة وغير المسواة
     */
    public function getEmployeePayrollData($employeeId)
    {
        try {
            $employee = Employee::with(['advanceAccount', 'salaryPayableAccount'])
                ->findOrFail($employeeId);

            $this->authorizeEmployeeScope($employee->id);

            if (! $employee->isActive()) {
                return response()->json(['error' => 'الموظف غير نشط'], 422);
            }

            $contract = $this->effectiveContract($employee, now());

            $basicSalary = 0;
            $contractAdditions = [];
            $contractDeductions = [];
            if ($contract) {
                $basicSalary = (float) ($contract->basic_salary ?? $contract->amount ?? 0);
                $contractAdditions = SalaryItem::where('contracts_id', $contract->id)
                    ->where('type', SalaryItem::TYPE_ADDITION)
                    ->get(['id', 'name', 'amount', 'calculation_formula'])
                    ->toArray();
                $contractDeductions = SalaryItem::where('contracts_id', $contract->id)
                    ->where('type', SalaryItem::TYPE_DEDUCTION)
                    ->get(['id', 'name', 'amount', 'calculation_formula'])
                    ->toArray();
            }

            $advances = $this->buildUnsettledAdvancesForEmployee($employee);

            $deductions = $employee->unsettledDeductions()
                ->orderBy('date')
                ->get(['id', 'deduction_type', 'amount', 'settled_amount', 'reason', 'date'])
                ->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'type' => $d->type_label ?? $d->deduction_type,
                        'amount' => (float) $d->amount,
                        'settled' => (float) $d->settled_amount,
                        'remaining' => (float) $d->remaining_amount,
                        'reason' => $d->reason,
                        'date' => $d->date->toDateString(),
                    ];
                });

            $advanceSummary = $employee->getAdvanceSummary();

            return response()->json([
                'success' => true,
                'contract' => $contract ? [
                    'id' => $contract->id,
                    'basic_salary' => $basicSalary,
                    // تاريخٌ لا طابعٌ زمنيّ ISO — كان يظهر «2026-08-23T21:00:00.000000Z»
                    'start_date' => $contract->start_date ? \Carbon\Carbon::parse($contract->start_date)->toDateString() : null,
                    'end_date' => $contract->end_date ? \Carbon\Carbon::parse($contract->end_date)->toDateString() : null,
                    'description' => $contract->description,
                ] : null,
                'basic_salary' => $basicSalary,
                'contract_additions' => $contractAdditions,
                'contract_deductions' => $contractDeductions,
                'advances' => $advances,
                'deductions' => $deductions,
                'advance_summary' => $advanceSummary,
                'has_payable_account' => ! empty($employee->salary_payable_account_id),
                'has_advance_account' => ! empty($employee->employee_advance_account_id),
                'payable_account_name' => optional($employee->salaryPayableAccount)->name,
                'advance_account_name' => optional($employee->advanceAccount)->name,
                'effective_payment_day' => $employee->scheduled_payment_day
                    ?? (int) PayrollSettings::value('default_payment_day')
                    ?: 1,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('getEmployeePayrollData failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** العقد الساري في تاريخٍ — الأحدث بدءاً. */
    private function effectiveContract(Employee $employee, $date): ?Contract
    {
        $date = Carbon::parse($date);

        return Contract::where('employee_id', $employee->id)
            ->where(function ($q) use ($date) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $date->toDateString());
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date->toDateString());
            })
            ->orderByDesc('start_date')
            ->first();
    }

    public function store(SalarySlipRequest $request)
    {
        $employee = Employee::findOrFail($request->employee_id);

        if (! $this->canAccessEmployee($employee->id)) {
            return back()->withInput()->withErrors(['employee_id' => 'هذا الموظف خارج نطاق الموظفين المصرّح لك بهم.']);
        }
        if (! $employee->isActive()) {
            return back()->withInput()->withErrors(['employee_id' => 'لا يمكن إنشاء قسيمة لموظف غير نشط.']);
        }
        if (empty($employee->salary_payable_account_id)) {
            return back()->withInput()->withErrors(['employee_id' => 'الموظف "'.$employee->first_name.'" لا يملك حساب مستحقات راتب. '.
                'افتح بطاقة الموظف ثم اضغط حفظ لإنشاء/ربط الحساب.']);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : null;

        // ⛔ التحقق من المسير المُختار: نشط، ساري في تاريخ البداية، ويشمل الموظف
        $payroll = Payroll::find($request->payroll_id);
        if (! $payroll || ! $payroll->isActive()) {
            return back()->withInput()->withErrors(['payroll_id' => 'المسير المُختار غير نشط أو غير معتمد. اختر مسيراً نشطاً.']);
        }
        if (! $payroll->isRunningAt($startDate)) {
            return back()->withInput()->withErrors(['start_date' => 'مسير الرواتب "'.$payroll->name.'" غير فعّال في تاريخ '.$startDate->toDateString().' (خارج فترة المسير).']);
        }
        if (! $payroll->coversEmployee($employee->id)) {
            return back()->withInput()->withErrors(['employee_id' => 'المسير "'.$payroll->name.'" لا يشمل الموظف "'.trim($employee->first_name.' '.$employee->middle_name).'". '.
                'اختر موظفاً من قائمة موظفي المسير، أو عدّل المسير ليشمله.']);
        }

        $overlapping = SalarySlip::where('employee_id', $employee->id)
            ->whereIn('status', [SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE])
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate->toDateString());
            })
            ->when($endDate, fn ($q) => $q->where('start_date', '<=', $endDate->toDateString()))
            ->exists();
        if ($overlapping) {
            return back()->withInput()->withErrors(['start_date' => 'يوجد قسيمة نشطة أخرى لهذا الموظف تتداخل مع الفترة المحددة.']);
        }

        $contract = $this->effectiveContract($employee, $startDate);

        $computed = $this->computeSlip($request);
        if ($computed['gross'] <= 0) {
            return back()->withInput()->withErrors(['basic_amount' => $contract
                ? 'أدخل الراتب الأساسي أو بند استحقاقٍ واحداً على الأقل بمبلغ.'
                : 'الموظف بلا عقدٍ ساري: أدخل الراتب الأساسي، أو أضف بند استحقاقٍ واحداً على الأقل بمبلغ — يُحسب الراتب منه.']);
        }

        DB::beginTransaction();
        try {
            $slipCode = null;
            try {
                $slipCode = $this->issueDocumentNumber('salary-slip');
            } catch (\Throwable $e) {
                OperationLog::failure($e, 'HR.SalarySlip', 'code');
            }

            $slip = SalarySlip::create([
                'code' => $slipCode,
                'employee_id' => $employee->id,
                'contract_id' => $contract?->id,
                'payroll_id' => $payroll->id,
                'basic_amount' => $computed['basic'],
                // الافتراضُ عملةُ النظام من شاشة «العملات» لا الريالُ نصّاً
                'currency' => $request->currency ?: CurrencySettings::code(),
                'treasury_id' => $request->treasury_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate ? $endDate->toDateString() : ($contract?->end_date),
                'frequency' => $request->frequency ?? 'monthly',
                'scheduled_payment_day' => $request->scheduled_payment_day,
                'status' => SalarySlip::STATUS_DRAFT,
                // مَن حدّد الأجر — عليه يقوم فصلُ المهام عند الاعتماد
                'created_by' => Auth::id(),
                'notes' => $request->notes,
                // الحقول القديمة للتوافق:
                'slip_date' => $startDate->toDateString(),
                'from_date' => $startDate->toDateString(),
                'to_date' => $endDate?->toDateString() ?? $startDate->copy()->endOfMonth()->toDateString(),
                'total_salary' => $computed['gross'],
                'total_deductions' => $computed['deductions'],
                'net_salary' => $computed['net'],
            ]);

            $this->persistItems($slip, $computed['rows']);

            ModelsLog::create([
                'type' => 'salary_slip_log',
                'type_id' => $slip->id,
                'type_log' => 'log',
                'description' => 'تم إنشاء قسيمة راتب جديدة',
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('salarySlip.show', $slip->id)
                ->with('success', 'تم إنشاء القسيمة كمسودة. اعتمدها لتفعيل توليد رواتب شهرية.');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.SalarySlip', 'store');

            return back()->withInput()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $slip = SalarySlip::with([
            'employee.advanceAccount', 'employee.salaryPayableAccount', 'employee.department', 'employee.job_title',
            'contract', 'treasury', 'approver', 'stopper', 'payroll', 'creator',
            'salaryItems',
            'payrollRuns' => fn ($q) => $q->orderByDesc('period_to'),
        ])->findOrFail($id);

        // حراسةُ السجلّ المفرد: القائمةُ مقيَّدة، لكنّ `show/{id}`
        // تُطلب بمعرّفٍ مباشر — فمن غيّر الرقمَ في العنوان بلغ سجلَّ غيره.
        $this->authorizeSlip($slip);

        // ✅ تصفية إضافية: تأكد أن الـ run.employee_id = slip.employee_id
        // (يحمي من بيانات قديمة معلقة قبل تطبيق الإصلاح)
        $slip->setRelation('payrollRuns',
            $slip->payrollRuns->where('employee_id', $slip->employee_id)->values()
        );

        $employee = $slip->employee;
        $advanceSummary = $employee ? $employee->getAdvanceSummary() : null;
        $unsettledDeductions = $employee ? $employee->unsettledDeductions()->get() : collect();
        $unsettledAdvances = $employee ? collect($this->buildUnsettledAdvancesForEmployee($employee)) : collect();

        // الزر اليدوي للصرف: يظهر فقط للـ run المعلق الذي حان موعده
        $dueRun = $slip->payrollRuns()
            ->where('employee_id', $slip->employee_id)
            ->where('status', PayrollRun::STATUS_PENDING)
            ->whereDate('scheduled_date', '<=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        // 🔄 احتياط: lazy generation — يحترم إعداد "auto_generate_payroll_runs"
        $autoGenerateEnabled = (bool) (PayrollSettings::value('auto_generate_payroll_runs') ?? true);
        if (! $dueRun && $autoGenerateEnabled && in_array($slip->status, [SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE])) {
            $effectiveDay = $slip->getEffectivePaymentDay();
            $hasCurrentMonthRun = $slip->payrollRuns()
                ->whereYear('period_from', now()->year)
                ->whereMonth('period_from', now()->month)
                ->exists();
            if (now()->day >= $effectiveDay && ! $hasCurrentMonthRun && $slip->canGenerateRuns()) {
                try {
                    $generated = app(PayrollDisbursementService::class)->generateNextRun($slip);
                    if ($generated && $generated->scheduled_date->lte(now())) {
                        $dueRun = $generated;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Lazy run generation failed', ['slip' => $slip->id, 'err' => $e->getMessage()]);
                }
            }
        }

        $logs = ModelsLog::where('type', 'salary_slip_log')
            ->where('type_id', $id)
            ->where('created_at', '>=', $slip->created_at)
            ->with('user')
            ->orderByDesc('id')
            ->get();

        // قائمةُ «خزينة الصرف» في نافذة الصرف — الحارسُ نفسُه الذي يسأله
        // `PayrollRunController::disburse` عند الحفظ، فلا يُعرض ما يُرفض
        $treasuries = $this->treasuries();

        $printTemplates = $this->payrollTemplates('salary_slip');

        // «القيود المحاسبية» لكلّ صرفات القسيمة، والخصمياتُ المُقيَّدة بانتظار الاقتطاع
        $trailService = app(\App\Services\PayrollJournalTrail::class);
        $journalTrail = $trailService->forSlip($slip);
        $pendingDeductions = $trailService->pendingPostedDeductions($employee);

        // لماذا تُحتسب التأمينات أو لا — للصرفة القادمة إن وُجدت
        $nextRun = $slip->payrollRuns->firstWhere('status', PayrollRun::STATUS_PENDING);
        $gosiBreakdown = app(\App\Services\SocialInsuranceService::class)->breakdown($slip, $nextRun, $employee);
        $gosiExplanation = $gosiBreakdown['explanation'] ?? null;
        $gosiEmployeeShare = (float) ($gosiBreakdown['employee_amount'] ?? 0);

        return view('hr::salaries.salary_slip.show', compact(
            'slip', 'employee', 'advanceSummary', 'unsettledDeductions',
            'unsettledAdvances', 'dueRun', 'logs', 'treasuries', 'printTemplates',
            'journalTrail', 'pendingDeductions', 'gosiExplanation', 'gosiEmployeeShare'
        ));
    }

    public function edit($id)
    {
        $slip = SalarySlip::with(['salaryItems', 'employee', 'payroll'])->findOrFail($id);
        $this->authorizeSlip($slip);

        if (! $slip->canBeEdited()) {
            return redirect()->route('salarySlip.show', $id)
                ->with('error', 'لا يمكن تعديل قسيمة في حالة: '.$slip->status_label);
        }

        return view('hr::salaries.salary_slip.edit', [
            'slip' => $slip,
            'employees' => BranchTree::headerEmployees(Employee::query(), [$slip->employee_id])
                ->whereKey($slip->employee_id)->get(),
            'additionItems' => $this->catalogItems(SalaryItem::TYPE_ADDITION),
            'deductionItems' => $this->catalogItems(SalaryItem::TYPE_DEDUCTION),
            'treasuries' => $this->treasuries(),
        ]);
    }

    public function update(SalarySlipRequest $request, $id)
    {
        $slip = SalarySlip::findOrFail($id);
        $this->authorizeSlip($slip);

        if (! $slip->canBeEdited()) {
            return back()->withInput()->with('error', 'لا يمكن تعديل قسيمة بحالة: '.$slip->status_label);
        }

        $computed = $this->computeSlip($request);
        if ($computed['gross'] <= 0) {
            return back()->withInput()->withErrors(['basic_amount' => 'أدخل الراتب الأساسي أو بند استحقاقٍ واحداً على الأقل بمبلغ.']);
        }

        DB::beginTransaction();
        try {
            // الموظفُ والمسيرُ لا يتغيّران بالتعديل: القسيمةُ اتفاقٌ لموظفٍ بعينه
            // تحت مسيرٍ بعينه، وصرفاتُها السابقة مقيَّدةٌ عليهما.
            $slip->update([
                'basic_amount' => $computed['basic'],
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'frequency' => $request->frequency ?? $slip->frequency,
                'scheduled_payment_day' => $request->scheduled_payment_day,
                'treasury_id' => $request->treasury_id,
                'currency' => $request->currency ?: $slip->currency,
                'notes' => $request->notes,
                'total_salary' => $computed['gross'],
                'total_deductions' => $computed['deductions'],
                'net_salary' => $computed['net'],
            ]);

            $this->persistItems($slip, $computed['rows']);

            ModelsLog::create([
                'type' => 'salary_slip_log',
                'type_id' => $slip->id,
                'type_log' => 'log',
                'description' => 'تم تعديل القسيمة',
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('salarySlip.show', $id)->with('success', 'تم حفظ التعديلات');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.SalarySlip', 'update');

            return back()->withInput()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * اعتماد القسيمة — تنتقل من draft إلى approved.
     * لا يتم أي صرف أو قيد محاسبي هنا.
     */
    public function approve($id)
    {
        $slip = SalarySlip::findOrFail($id);
        $this->authorizeSlip($slip);

        if (! $slip->canBeApproved()) {
            return back()->with('error', 'لا يمكن اعتماد قسيمة بحالة: '.$slip->status_label);
        }

        /*
            فصلُ المهام: لا يعتمد الأجرَ مَن حدّده.

            وهو مفتاحٌ لا قاعدةٌ ثابتة، وافتراضُه **الإطفاء** — بخلاف اشتراط
            اعتماد الصرف. والفرقُ أنّ منشأةً بمسؤولٍ واحد لا تستطيع أن تُرضيه
            بحال: من ينشئ القسيمة هو من يعتمدها ولا ثالثَ لهما.
        */
        if ((bool) (PayrollSettings::value('require_slip_maker_checker') ?? false)
            && $slip->wasApprovedByItsCreator(Auth::id())) {
            return back()->with('error',
                'لا يعتمد القسيمة مَن أنشأها. اطلب اعتمادها من مستخدمٍ آخر، '.
                'أو أوقف «فصل المهام على القسائم» من إعدادات الرواتب.'
            );
        }

        $slip->update([
            'status' => SalarySlip::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        ModelsLog::create([
            'type' => 'salary_slip_log',
            'type_id' => $slip->id,
            'type_log' => 'log',
            'description' => 'تم اعتماد القسيمة. ستبدأ بتوليد الرواتب الشهرية تلقائياً.',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'تم اعتماد القسيمة. ستُولِّد رواتب شهرية تلقائياً عبر الكرون.');
    }

    /**
     * إيقاف القسيمة (لا توليد runs جديدة بعد ذلك).
     */
    public function stop(Request $request, $id)
    {
        $slip = SalarySlip::findOrFail($id);
        $this->authorizeSlip($slip);

        if (! $slip->canBeStopped()) {
            return back()->with('error', 'لا يمكن إيقاف قسيمة بحالة: '.$slip->status_label);
        }

        $reason = $request->input('stop_reason');
        $slip->update([
            'status' => SalarySlip::STATUS_STOPPED,
            'stopped_at' => now(),
            'stopped_by' => Auth::id(),
            'stop_reason' => $reason,
        ]);

        ModelsLog::create([
            'type' => 'salary_slip_log',
            'type_id' => $slip->id,
            'type_log' => 'log',
            'description' => 'تم إيقاف القسيمة'.($reason ? '. السبب: '.$reason : ''),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'تم إيقاف القسيمة');
    }

    /**
     * ⚠️ دالة اختبار مؤقتة — مُجمَّدة (لا route يصل إليها حالياً).
     */
    public function testDisburse($id)
    {
        // 🔒 مُجمَّد: ارجع 404 لمنع أي استدعاء حتى لو ربط شخص الـ route يدوياً
        abort(404, 'Test endpoint is frozen.');
    }

    public function destroy($id)
    {
        $slip = SalarySlip::findOrFail($id);
        $this->authorizeSlip($slip);

        if (! $slip->canBeDeleted()) {
            return back()->with('error', 'لا يمكن حذف قسيمة بحالة: '.$slip->status_label.'. أوقفها بدلاً من الحذف.');
        }

        // منع الحذف إن وُجد سجل صرف فعلي (disbursed)
        if ($slip->payrollRuns()->where('status', PayrollRun::STATUS_DISBURSED)->exists()) {
            return back()->with('error',
                'لا يمكن حذف القسيمة: لها سجلات صرف فعلية. أوقف القسيمة بدلاً من حذفها.'
            );
        }

        DB::beginTransaction();
        try {
            $runIds = $slip->payrollRuns()->pluck('id');
            if ($runIds->isNotEmpty()) {
                PayrollRunItem::whereIn('payroll_run_id', $runIds)->delete();
                $slip->payrollRuns()->delete();
            }
            ModelsLog::where('type', 'salary_slip_log')->where('type_id', $slip->id)->delete();
            $slip->salaryItems()->delete();
            $slip->delete();

            DB::commit();

            return redirect()->route('salarySlip.index')->with('success', 'تم حذف القسيمة وكل سجلاتها بنجاح');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.SalarySlip', 'destroy');

            return back()->with('error', 'فشل الحذف: '.$e->getMessage());
        }
    }

    /**
     * طباعةُ القسيمة بقالب «كشف رواتب» من «قوالب المستندات» — الافتراضيّ
     * لنوعه، أو المطلوب بـ`?template=`.
     */
    public function print(Request $request, $id)
    {
        $slip = SalarySlip::findOrFail($id);
        $this->authorizeSlip($slip);

        $html = $this->renderPayrollTemplate('salary_slip', $slip->id, $request->input('template'));

        if ($html === null) {
            return redirect()->route('salarySlip.show', $slip->id)
                ->with('error', 'لا يوجد قالبُ طباعةٍ فعّالٌ لقسيمة الراتب. فعّل قالباً من الإعدادات ← قوالب المستندات.');
        }

        return $this->printResponse($html, 'قسيمة راتب '.($slip->code ?: '#'.$slip->id), route('salarySlip.show', $slip->id));
    }

    // ============================================================
    // الحساب
    // ============================================================

    /**
     * حسابُ القسيمة من الطلب — مصدرٌ واحدٌ للتحقّق والحفظ.
     *
     * @return array{basic: float, additions: float, deductions: float, gross: float, net: float, rows: array<int, array<string, mixed>>}
     */
    private function computeSlip(Request $request): array
    {
        $basic = round(max(0, (float) $request->input('basic_amount', 0)), 2);
        $vars = ['basic' => $basic, 'basic_amount' => $basic, 'basic_salary' => $basic];

        $rows = [];
        $additions = 0.0;
        $deductions = 0.0;

        foreach (['addition' => SalaryItem::TYPE_ADDITION, 'deduction' => SalaryItem::TYPE_DEDUCTION] as $prefix => $type) {
            foreach ($this->collectUniqueRequestItems($request, $prefix) as $itemId => $row) {
                // البندُ المعرَّف وحده — لا نسخةٌ داخل عقدٍ أو قسيمةٍ أخرى
                $orig = SalaryItem::query()->catalog()->where('type', $type)->find($itemId);
                if (! $orig) {
                    continue;
                }

                // 🚫 العمولات لا تدخل القسيمة — نظامٌ مستقلّ
                if ($type === SalaryItem::TYPE_ADDITION && preg_match('/عمولة|عمولات|commission/iu', $orig->name)) {
                    continue;
                }

                $amount = round(max(0, $this->computeItemAmount($row['formula'], $row['manual'], $orig, $vars)), 2);

                $rows[] = [
                    'orig' => $orig,
                    'type' => $type,
                    'formula' => $row['formula'],
                    'amount' => $amount,
                ];

                if ($type === SalaryItem::TYPE_ADDITION) {
                    $additions += $amount;
                } else {
                    $deductions += $amount;
                }
            }
        }

        $gross = round($basic + $additions, 2);

        return [
            'basic' => $basic,
            'additions' => round($additions, 2),
            'deductions' => round($deductions, 2),
            'gross' => $gross,
            'net' => round($gross - $deductions, 2),
            'rows' => $rows,
        ];
    }

    /** يستبدل بنودَ القسيمة بما حسبه `computeSlip()`. */
    private function persistItems(SalarySlip $slip, array $rows): void
    {
        // سطرُ مكافأةٍ من «كتالوج المكافآت» ليس من بنود النموذج — يبقى حتى يُصرف
        $slip->salaryItems()
            ->where(fn ($q) => $q->whereNull('origin')->orWhere('origin', '!=', SalaryItem::ORIGIN_REWARD_REDEMPTION))
            ->delete();

        foreach ($rows as $row) {
            $orig = $row['orig'];

            SalaryItem::create([
                'name' => $orig->name,
                'type' => $row['type'],
                'status' => $orig->status,
                'description' => $orig->description,
                'calculation_formula' => $row['formula'] ?: $orig->calculation_formula,
                'amount' => $row['amount'],
                'salary_slips_id' => $slip->id,
                'account_id' => $orig->account_id,
            ]);
        }
    }

    /**
     * يجمع البنود من الـ request مع إزالة التكرار: لكل بندٍ آخرُ قيمة.
     *
     * والمبلغُ الفارغ «لا مبلغ» لا صفر: كان `isset('')` صادقاً فيُقرأ صفراً،
     * فيسقط إلى المبلغ الافتراضيّ للبند وإن كتب المستخدمُ صيغة.
     *
     * @return array<int, array{formula:?string, manual:?float}>
     */
    private function collectUniqueRequestItems(Request $request, string $prefix): array
    {
        $types = (array) $request->input($prefix.'_type', []);
        $formulas = (array) $request->input($prefix.'_calculation_formula', []);
        $manuals = (array) $request->input($prefix.'_amount', []);

        $byType = [];
        foreach ($types as $key => $type) {
            if (! is_numeric($type) || (int) $type <= 0) {
                continue;
            }

            $manual = $manuals[$key] ?? null;
            $formula = trim((string) ($formulas[$key] ?? ''));

            $byType[(int) $type] = [
                'formula' => $formula !== '' ? $formula : null,
                'manual' => is_numeric($manual) ? (float) $manual : null,
            ];
        }

        return $byType;
    }

    /**
     * مبلغُ البند: ما كتبه المستخدم أوّلاً — فهو ما رآه في الملخّص وحفظه —
     * ثمّ الصيغةُ على الأساسيّ، ثمّ مبلغُ البند المعرَّف.
     */
    private function computeItemAmount(?string $formula, ?float $manual, SalaryItem $orig, array $vars): float
    {
        if ($manual !== null) {
            return (float) $manual;
        }
        if (! empty($formula)) {
            return (float) $this->evaluateCalculationFormula($formula, $vars);
        }

        return (float) ($orig->amount ?? 0);
    }

    /**
     * بناء قائمة سلف الموظف غير المسواة (للعرض في شاشة الإنشاء/التعديل).
     */
    private function buildUnsettledAdvancesForEmployee(Employee $employee): array
    {
        $rows = [];

        // 🔒 الموظف يجب أن يملك حساب سلف خاص
        $empAdvAccountId = $employee->employee_advance_account_id;
        if (! $empAdvAccountId) {
            return [];
        }

        // هل السلفة مرتبطة بحساب السلف الخاص بالموظف؟ (قيد Dr على حسابه لها)
        $isLinkedToEmployeeAccount = function (int $advanceRequestId) use ($empAdvAccountId): bool {
            return DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'jed.journal_entry_id', '=', 'je.id')
                ->where('jed.account_id', $empAdvAccountId)
                ->where('jed.debit', '>', 0)
                ->where(function ($q) use ($advanceRequestId) {
                    $q->where('je.reference_number', 'ADV-REQ-'.$advanceRequestId)
                        ->orWhere('je.reference_number', 'ADV-ISSUE-'.$advanceRequestId)
                        ->orWhere(function ($q2) use ($advanceRequestId) {
                            $q2->where('je.reference_id', $advanceRequestId)
                                ->where('je.reference_number', 'NOT LIKE', 'SAL-%');
                        });
                })
                ->exists();
        };

        // النظام A: سلف بأقساط متعددة
        $advA = SalaryAdvance::where('employee_id', $employee->id)
            ->whereRaw('paid_installments < total_installments')
            ->with(['payments' => function ($q) {
                $q->where('status', 'unpaid')->orderBy('due_date');
            }])
            ->get();
        foreach ($advA as $a) {
            if (! $isLinkedToEmployeeAccount($a->id)) {
                continue;
            }
            foreach ($a->payments as $inst) {
                $rows[] = [
                    'system' => 'A',
                    'source_table' => 'installment_payments',
                    'source_id' => $inst->id,
                    'advance_id' => $a->id,
                    'amount' => (float) $inst->amount,
                    'name' => "قسط #{$inst->installment_number} من سلفة #{$a->id}",
                    'due_date' => optional($inst->due_date)->toDateString(),
                ];
            }
        }

        // النظام B: السلف الحديثة
        $advB = SalaryAdvanceRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('is_settled', false)
            ->get();
        foreach ($advB as $r) {
            if (! $isLinkedToEmployeeAccount($r->id)) {
                continue;
            }
            $rows[] = [
                'system' => 'B',
                'source_table' => 'salary_advance_requests',
                'source_id' => $r->id,
                'advance_id' => $r->id,
                // المتبقّي لا الأصل: ما سُدِّد نقداً أو اقتُطع سابقاً لا يُعرض مرّةً ثانية —
                // ومقصوراً على القسط للمجدولة، كما يخطّط له مسيرُ الرواتب.
                'amount' => $r->installment_amount
                    ? min((float) $r->installment_amount, $r->remainingAmount())
                    : $r->remainingAmount(),
                'name' => 'سلفة #'.$r->id.(($r->advance_type ?? 'normal') === 'scheduled' ? ' (مجدولة)' : ' (عادية)'),
                'reason' => $r->reason,
            ];
        }

        return $rows;
    }
}
