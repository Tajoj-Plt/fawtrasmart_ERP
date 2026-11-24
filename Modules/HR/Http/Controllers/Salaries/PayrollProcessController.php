<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Exports\PayrollRunExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JopTitle;
use App\Models\Log as ModelsLog;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Services\PayrollDisbursementService;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Http\Controllers\Concerns\PrintsPayrollDocuments;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\BranchTree;

/**
 * مسيرات الرواتب: مظلة زمنية (من يُشمَل ومتى)، لا صرف.
 *
 * **اعتمادُ المسير هو الاعتمادُ الوحيد للصرف.** اعتماده يفعّله لإنشاء قسائم،
 * ويولّد صرفات الفترة الجارية لقسائمه السارية (`generatePeriodRuns`)، فتصير
 * «مستحقة الصرف» في يوم صرف كلّ قسيمة بلا اعتمادٍ ثانٍ — كان يُشترط فوقه
 * «مسير صرفات» (`payroll_batches`) يُعتمد لكلّ دفعة، فأُلغي.
 * `approveAll` تعتمد القسائم المسودة وتولّد صرفاتها كذلك. `approveRuns`
 * (بالاسم القديم للمسار) صار «تحديث صرفات الفترة». و`disburseDue` يصرف
 * المستحقَّ كلَّه عبر `PayrollDisbursementService` — معاملةٌ لكلّ صرفة.
 *
 * **مَن في المسير** — مصدرٌ واحد: `Payroll::memberEmployeeIds()`. الإنشاءُ
 * يحفظ الموظفين في `payroll_employee` (بالقواعد كان أو بالاختيار)، والعرضُ
 * والتصديرُ والطباعةُ وقائمةُ موظفي القسيمة تقرأ منه — فلا يختلف عددٌ بين
 * شاشةٍ وملفّ.
 *
 * **قسائمُ المسير** — ما يحمل `payroll_id` هذا المسير، لا ما «تداخلت فترتُه»
 * مع فترته كما كان: تلك كانت تجمع قسائمَ مسيراتٍ أخرى وتُسقط قسائمَه هو إن
 * اختلفت تواريخُها، فيخالف ملفُّ Excel ما في الشاشة.
 */
class PayrollProcessController extends Controller
{
    use PrintsPayrollDocuments;
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:payroll.index', ['only' => ['index']]);
        $this->middleware('permission.role:payroll.show', ['only' => ['show']]);
        $this->middleware('permission.role:payroll.create', ['only' => ['create', 'store']]);
        // معاينةُ من ستضمّهم القواعد — تخدم نموذجَي الإنشاء والتعديل معاً.
        $this->middleware('permission.role:payroll.create|payroll.edit', ['only' => ['previewEmployees']]);
        $this->middleware('permission.role:payroll.edit', ['only' => ['edit', 'update']]);
        // اعتمادُ المسير — الاعتمادُ الوحيد قبل الصرف — وإيقافُه وتفعيلُه وتحديثُ صرفات فترته
        $this->middleware('permission.role:payroll.approve', ['only' => ['approve', 'approveRuns', 'deactivate', 'activate']]);
        // اعتمادُ قسائم المسير المسودة دفعةً واحدة فعلُ «قسيمة» لا «مسير»
        $this->middleware('permission.role:payslips.approve', ['only' => ['approveAll']]);
        // «صرف المستحق» جملةً هو صرفُ القسائم نفسُه
        $this->middleware('permission.role:payslips.disburse', ['only' => ['disburseDue']]);
        $this->middleware('permission.role:payroll.destroy', ['only' => ['destroy']]);
        $this->middleware('permission.role:payroll.export', ['only' => ['exportSlips']]);
        $this->middleware('permission.role:payroll.print', ['only' => ['print']]);
    }

    // ============================================================
    // نطاقُ الرؤية
    // ============================================================

    /**
     * الموظفون الذين يحقّ للمستخدم رؤيةُ بياناتهم داخل المسير.
     *
     * الصلاحيةُ (`payroll.*`) تحدّد *ماذا يفعل*، ونطاقُ الرؤية
     * (`data_scope_*` عبر `ScopesToAccessibleEmployees`) يحدّد *على أي
     * موظفين* — للعرض والاعتماد والتصدير والطباعة سواء. `null` = بلا قيد.
     * (لا صلاحيةَ «خاصّ»: صاحبُ `data_scope_own` يرى نفسَه وحده.)
     *
     * والسجلُّ المفرد (العرض والتصدير والطباعة) يُحرَس بالنطاق وحده لا
     * بفرع الشريط العلويّ — الشريطُ يرشّح القوائم ولا يُخفي نصفَ مسيرٍ مفتوح.
     *
     * @return array<int, int>|null
     */
    private function visibleEmployeeIds(bool $forList = false): ?array
    {
        return $forList ? $this->listEmployeeIds() : $this->accessibleEmployeeIds();
    }

    /**
     * المسيراتُ الظاهرة: ما فيه موظفٌ واحدٌ على الأقلّ من نطاق المستخدم
     * (عضواً أو صاحبَ قسيمة). والمسيرُ جدولٌ بلا `employee_id`، فكان
     * `scopeToEmployees()` عليه يُسقط الاستعلامَ بخطأ عمودٍ مجهول لكلّ
     * مستخدمٍ مقيَّد النطاق.
     */
    private function scopePayrolls(Builder $query, bool $forList = false): Builder
    {
        $ids = $this->visibleEmployeeIds($forList);

        if ($ids === null) {
            return $query;
        }

        $ids = $ids ?: [0];

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereHas('employees', fn ($e) => $e->whereIn('employees.id', $ids))
                ->orWhereHas('salarySlips', fn ($s) => $s->whereIn('employee_id', $ids));
        });
    }

    /** حراسةُ السجلّ المفرد: `show/{id}` تُطلب بمعرّفٍ مباشر. */
    private function authorizePayroll(Payroll $payroll): void
    {
        if ($this->visibleEmployeeIds() === null) {
            return;
        }

        if (! $this->scopePayrolls(Payroll::query()->whereKey($payroll->id))->exists()) {
            abort(403, 'هذا المسير خارج نطاق الموظفين المصرّح لك بالاطّلاع عليهم.');
        }
    }

    /** يقصر استعلاماً بعمود موظفٍ على الظاهرين. */
    private function limitToVisible($query, string $column, bool $forList = false)
    {
        $ids = $this->visibleEmployeeIds($forList);

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    /** يقصر استعلاماً بعمود موظفٍ على نطاق الصلاحية وحده (بلا فرع الشريط). */
    private function limitToAccessible($query, string $column)
    {
        $ids = $this->accessibleEmployeeIds();

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    /**
     * قسائمُ المسير كما تُعرض وتُصدَّر وتُطبع — استعلامٌ واحدٌ للثلاثة.
     */
    private function payrollSlips(Payroll $payroll): Collection
    {
        $query = SalarySlip::query()
            ->with(['employee.department', 'employee.job_title', 'employee.branch', 'salaryItems', 'contract'])
            ->where('payroll_id', $payroll->id);

        $this->limitToVisible($query, 'salary_slips.employee_id');

        return $query->orderBy('employee_id')->orderBy('id')->get();
    }

    /** موظفو المسير الظاهرون للمستخدم. */
    private function payrollMembers(Payroll $payroll): Collection
    {
        $query = Employee::query()
            ->with(['department:id,name', 'job_title:id,name', 'branch:id,name'])
            ->whereIn('employees.id', $payroll->memberEmployeeIds() ?: [0]);

        $this->limitToVisible($query, 'employees.id');

        return $query->orderBy('first_name')->get();
    }

    // ============================================================
    // الشاشات
    // ============================================================

    public function index(Request $request)
    {
        $query = Payroll::query()->with(['department:id,name', 'jobTitle:id,name', 'branch:id,name']);
        $this->scopePayrolls($query, true);

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.trim($request->name).'%');
        }

        if ($request->filled('status') && array_key_exists($request->status, Payroll::statuses())) {
            $query->where('status', $request->status);
        }

        if ($departments = Payroll::ruleValues($request->input('department_id'))) {
            $query->whereIn('department_id', $departments);
        }

        if ($titles = Payroll::ruleValues($request->input('jop_title_id'))) {
            $query->whereIn('jop_title_id', $titles);
        }

        if ($employeeIds = Payroll::ruleValues($request->input('employee_id'))) {
            $query->whereHas('employees', fn ($q) => $q->whereIn('employees.id', $employeeIds));
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }
        if ($request->filled('registration_date_from')) {
            $query->where('registration_date', '>=', $request->registration_date_from);
        }
        if ($request->filled('registration_date_to')) {
            $query->where('registration_date', '<=', $request->registration_date_to);
        }

        if ($request->filled('branch_id')) {
            BranchTree::apply($query, $request->branch_id);
        }

        // الإحصاءات من الاستعلام المقيَّد نفسِه قبل الترقيم — لا من الجدول كلّه
        $stats = (clone $query)->setEagerLoads([])
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        // العدّادات في الصفّ مقيَّدةٌ بنطاق المستخدم أيضاً
        $visible = $this->visibleEmployeeIds(true);
        $limitEmployees = fn ($q) => $visible === null ? $q : $q->whereIn('employees.id', $visible ?: [0]);
        $limitSlips = fn ($q) => $visible === null ? $q : $q->whereIn('employee_id', $visible ?: [0]);

        $query->withCount([
            'employees as members_count' => $limitEmployees,
            'salarySlips as slips_count' => $limitSlips,
            'salarySlips as running_slips_count' => fn ($q) => $limitSlips($q)
                ->whereIn('status', [SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE]),
        ])->withSum(['salarySlips as slips_net' => $limitSlips], 'net_salary');

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $payrolls = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $jobTitles = JopTitle::orderBy('name')->get(['id', 'name']);
        $employees = $this->limitToVisible(
            Employee::query()->select('id', 'first_name', 'middle_name', 'nickname')->orderBy('first_name'),
            'employees.id',
            true
        )->get();

        return view('hr::salaries.payroll_ process.index', compact(
            'payrolls', 'stats', 'departments', 'jobTitles', 'employees'
        ));
    }

    public function create()
    {
        return view('hr::salaries.payroll_ process.create', $this->formData());
    }

    public function edit($id)
    {
        $payroll = Payroll::with(['department', 'employees:id'])->findOrFail($id);
        $this->authorizePayroll($payroll);

        $selected = $payroll->employees->pluck('id')->map(fn ($v) => (int) $v)->all();

        $data = $this->formData($selected);
        $data['payroll'] = $payroll;
        $data['selectedEmployees'] = $selected;
        // موظفون لهم قسائمُ في المسير — لا يُفكّون عنه عند التعديل
        $data['lockedEmployees'] = SalarySlip::where('payroll_id', $payroll->id)
            ->pluck('employee_id')->map(fn ($v) => (int) $v)->unique()->values()->all();

        return view('hr::salaries.payroll_ process.edit', $data);
    }

    /**
     * بياناتُ نموذج الإنشاء والتعديل.
     *
     * @param  array<int, int>  $keepEmployeeIds  موظفو المسير المفتوح — يبقون في القائمة ولو خارج فرع الشريط
     */
    private function formData(array $keepEmployeeIds = []): array
    {
        $query = Employee::query()
            ->select('id', 'first_name', 'middle_name', 'nickname', 'department_id', 'job_title_id', 'branch_id', 'status')
            ->with(['department:id,name', 'job_title:id,name', 'branch:id,name'])
            ->active()
            ->orderBy('first_name');

        $this->limitToAccessible($query, 'employees.id');
        BranchTree::headerEmployees($query, $keepEmployeeIds);

        return [
            'employees' => $query->get(),
            'branches' => Branch::all(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'jop_titles' => JopTitle::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * معاينةُ من ستضمّهم القواعد — يطلبها النموذج عند كلّ تغييرٍ في الفرع
     * أو القسم أو المسمّى، فيرى المستخدمُ العددَ والأسماءَ قبل الحفظ بدل
     * أن يكتشف بعده أنّ المسير خرج بأربعة موظفين.
     */
    public function previewEmployees(Request $request)
    {
        $ids = $this->ruleMatchedIds(
            $request->input('department_id'),
            $request->input('jop_title_id'),
            $request->input('branch_id')
        );

        $employees = Employee::query()
            ->with(['department:id,name', 'job_title:id,name', 'branch:id,name'])
            ->whereIn('employees.id', $ids ?: [0])
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => trim($e->first_name.' '.$e->middle_name),
                'department' => optional($e->department)->name,
                'job_title' => optional($e->job_title)->name,
                'branch' => optional($e->branch)->name,
            ]);

        return response()->json([
            'count' => $employees->count(),
            'employees' => $employees->values(),
        ]);
    }

    public function show($id)
    {
        $payroll = Payroll::with(['department', 'branch', 'jobTitle', 'approver'])->findOrFail($id);
        $this->authorizePayroll($payroll);

        $members = $this->payrollMembers($payroll);
        $slips = $this->payrollSlips($payroll);

        $slipEmployeeIds = $slips->pluck('employee_id')->map(fn ($v) => (int) $v)->all();
        $membersWithoutSlip = $members->reject(fn ($e) => in_array((int) $e->id, $slipEmployeeIds, true))->values();

        $totals = [
            'basic' => (float) $slips->sum('basic_amount'),
            'gross' => (float) $slips->sum('total_salary'),
            'deductions' => (float) $slips->sum('total_deductions'),
            'net' => (float) $slips->sum('net_salary'),
        ];

        $slipCounts = $slips->groupBy('status')->map->count();
        $countDraft = (int) ($slipCounts[SalarySlip::STATUS_DRAFT] ?? 0);

        $logs = ModelsLog::where('type', 'payroll_log')
            ->where('type_id', $payroll->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        $printTemplates = $this->payrollTemplates('payroll_sheet');

        // صرفاتُ المسير: المعلّقةُ أوّلاً ثمّ الأحدث — ومنها «المستحق» الذي يُصرف جملةً
        $runsQuery = PayrollRun::query()
            ->with(['employee:id,first_name,middle_name,status', 'slip.payroll', 'contract'])
            ->whereHas('slip', fn ($q) => $q->where('payroll_id', $payroll->id));
        $this->limitToVisible($runsQuery, 'payroll_runs.employee_id');
        $runs = $runsQuery
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [PayrollRun::STATUS_PENDING])
            ->orderByDesc('period_from')
            ->orderBy('employee_id')
            ->limit(300)
            ->get();

        $dueRuns = $runs->filter(fn (PayrollRun $r) => $r->isPending() && $r->canBeDisbursed())->values();
        $treasuries = $dueRuns->isNotEmpty() && hasPermission('payslips.disburse')
            ? TreasuryBalanceGuard::withdrawableAccounts()
            : collect();

        return view('hr::salaries.payroll_ process.show', compact(
            'payroll', 'members', 'slips', 'membersWithoutSlip', 'totals', 'slipCounts', 'countDraft', 'logs', 'printTemplates',
            'runs', 'dueRuns', 'treasuries'
        ));
    }

    // ============================================================
    // الحفظ
    // ============================================================

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'registration_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'selection' => 'required|in:1,2',
            'receiving_cycle' => 'nullable|in:1,2,3,4,5',
            'department_id' => 'nullable',
            'jop_title_id' => 'nullable',
            'branch_id' => 'nullable',
            'employee_id' => 'required_if:selection,2|array',
            'employee_id.*' => 'integer|exists:employees,id',
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'اسم مسير الرواتب مطلوب.',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساوياً له.',
            'selection.required' => 'اختر طريقة اختيار الموظفين.',
            'employee_id.required_if' => 'اختر موظفاً واحداً على الأقل.',
            'employee_id.*.exists' => 'أحد الموظفين المختارين غير موجود.',
        ];
    }

    /**
     * معرّفاتُ من تنطبق عليهم القواعد، مقيَّدةً بنطاق صلاحية المستخدم.
     *
     * لا بفرع الشريط العلويّ: الفرعُ قاعدةٌ في النموذج نفسِه («تابع لـ»)،
     * والشريطُ يطابق `employees.branch_id` وحدَه — فيُسقط من فرعُه محفوظٌ
     * في حساب دخوله، وهو ما تتداركه `Payroll::ruleEmployeesQuery()`.
     *
     * @return array<int, int>
     */
    private function ruleMatchedIds($departmentId, $jopTitleId, $branchId): array
    {
        $query = Payroll::ruleEmployeesQuery($departmentId, $jopTitleId, $branchId);
        $this->limitToAccessible($query, 'employees.id');

        return $query->pluck('employees.id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * الموظفون المقصودون بالنموذج — بالقواعد (1) أو بالاختيار (2).
     *
     * قيمتا «طريقة اختيار الموظفين» كانتا معكوستين في الإضافة مرّة:
     * الفرعُ الأوّل يقرأ `employee_id` عند `1` — وهي حالةُ القواعد التي
     * تُعطَّل فيها قائمةُ الموظفين فلا تصل أصلاً — فلا يُربَط أحد.
     *
     * @return array<int, int>
     */
    private function resolveMembers(Request $request): array
    {
        if ((string) $request->input('selection') === '1') {
            return $this->ruleMatchedIds(
                $request->input('department_id'),
                $request->input('jop_title_id'),
                $request->input('branch_id')
            );
        }

        $requested = Payroll::ruleValues($request->input('employee_id')) ?? [];

        // الاختيارُ اليدويّ مقيَّدٌ بالنطاق والنشاط: القائمةُ في النموذج كذلك،
        // وطلبٌ مصنوعٌ يدوياً لا يضمّ من لا يراه المستخدم.
        $query = Employee::query()->active()->whereIn('employees.id', $requested ?: [0]);
        $this->limitToAccessible($query, 'employees.id');

        return $query->pluck('employees.id')->map(fn ($v) => (int) $v)->all();
    }

    /** قيمةُ القاعدة المحفوظة في المسير: رقمٌ واحد أو لا شيء («الكل»). */
    private function storedRule($value): ?int
    {
        $values = Payroll::ruleValues($value);

        return $values ? $values[0] : null;
    }

    /** خطأُ «لا أحد» على الحقل الذي يُصلحه — فتمرّره شاشةُ النموذج إليه. */
    private function emptySelectionError(Request $request)
    {
        $byRules = (string) $request->input('selection') === '1';

        return back()->withInput()->withErrors($byRules
            ? ['department_id' => 'لا يوجد موظفٌ نشطٌ ينطبق على هذه القواعد (الفرع/القسم/المسمّى) ضمن نطاقك. وسّع القواعد أو اختر موظفين بأعيانهم.']
            : ['employee_id' => 'اختر موظفاً نشطاً واحداً على الأقل ضمن نطاقك.']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $memberIds = $this->resolveMembers($request);
        if ($memberIds === []) {
            return $this->emptySelectionError($request);
        }

        $byRules = (string) $validated['selection'] === '1';

        try {
            DB::beginTransaction();

            $payroll = Payroll::create([
                'name' => $validated['name'],
                'registration_date' => $validated['registration_date'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'select_emp_role' => (int) $validated['selection'],
                'receiving_cycle' => $validated['receiving_cycle'] ?? null,
                'department_id' => $byRules ? $this->storedRule($request->input('department_id')) : null,
                'jop_title_id' => $byRules ? $this->storedRule($request->input('jop_title_id')) : null,
                'branch_id' => $byRules ? $this->storedRule($request->input('branch_id')) : null,
                'status' => Payroll::STATUS_DRAFT, // ابدأ كمسودة — يحتاج اعتماد لتفعيله
            ]);

            $payroll->employees()->sync($memberIds);

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'تم إضافة مسير رواتب وضُمّ إليه '.count($memberIds).' موظفاً',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('PayrollProcess.show', $payroll->id)
                ->with('success', 'تم إنشاء مسير الرواتب وضُمَّ إليه '.count($memberIds).' موظفاً. اعتمده لتفعيله وبدء إنشاء قسائم تحته.');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.PayrollProcess', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء إنشاء مسير الرواتب: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        $validated = $request->validate($this->rules(), $this->messages());

        $memberIds = $this->resolveMembers($request);
        if ($memberIds === []) {
            return $this->emptySelectionError($request);
        }

        /*
            موظفٌ له قسيمةٌ في المسير لا يُفكّ عنه: القسيمةُ تحمل `payroll_id`
            هذا المسير، وفكُّه يجعلها قسيمةً في مسيرٍ لا يشمل صاحبَها — تتوقّف
            صرفاتُها بصمت. وموظفٌ خارج نطاق المستخدم لا يُمسّ أيضاً: التعديلُ
            يرى بعضَ المسير لا كلَّه.
        */
        $locked = SalarySlip::where('payroll_id', $payroll->id)->pluck('employee_id')->map(fn ($v) => (int) $v)->all();
        $current = $payroll->employees()->pluck('employees.id')->map(fn ($v) => (int) $v)->all();
        $outOfScope = array_values(array_filter($current, fn ($eid) => ! $this->canAccessEmployee($eid)));
        $kept = array_values(array_intersect(
            array_diff(array_unique(array_merge($locked, $outOfScope)), $memberIds),
            $current
        ));
        $finalIds = array_values(array_unique(array_merge($memberIds, $kept)));

        $byRules = (string) $validated['selection'] === '1';

        try {
            DB::beginTransaction();

            $payroll->update([
                'name' => $validated['name'],
                'registration_date' => $validated['registration_date'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'select_emp_role' => (int) $validated['selection'],
                'receiving_cycle' => $validated['receiving_cycle'] ?? $payroll->receiving_cycle,
                'department_id' => $byRules ? $this->storedRule($request->input('department_id')) : null,
                'jop_title_id' => $byRules ? $this->storedRule($request->input('jop_title_id')) : null,
                'branch_id' => $byRules ? $this->storedRule($request->input('branch_id')) : null,
            ]);

            $payroll->employees()->sync($finalIds);

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'تم تعديل مسير رواتب — عدد الموظفين '.count($finalIds),
                'created_by' => auth()->id(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.PayrollProcess', 'update');

            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث مسير الرواتب: '.$e->getMessage());
        }

        $message = 'تم تحديث مسير الرواتب — يضمّ الآن '.count($finalIds).' موظفاً.';
        if ($kept !== []) {
            $message .= ' وأُبقي '.count($kept).' موظفاً لهم قسائم في المسير أو خارج نطاقك.';
        }

        return redirect()->route('PayrollProcess.show', $payroll->id)->with('success', $message);
    }

    // ============================================================
    // الحالة
    // ============================================================

    /**
     * «اعتماد المسير» — يجعل حالته active، وهو **الاعتمادُ الوحيد للصرف**.
     *
     * يفتح إنشاء القسائم تحته، ويولّد صرفات الفترة الجارية لقسائمه السارية
     * فتُصرف في يوم صرف كلّ قسيمة بلا اعتمادٍ آخر.
     */
    public function approve($id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        try {
            if ($payroll->isActive()) {
                return redirect()->back()->with('warning', 'مسير الرواتب نشط بالفعل.');
            }

            $payroll->update([
                'status' => Payroll::STATUS_ACTIVE,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            $generated = $this->generatePeriodRuns($payroll->fresh());

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'تم اعتماد مسير الرواتب — صار الصرفُ مصرَّحاً به في يوم صرف كلّ قسيمة'.
                    ($generated ? '، وأُنشئت '.$generated.' صرفة للفترة الجارية' : '').'.',
                'created_by' => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'تم اعتماد مسير الرواتب. '.$this->generatedMessage($generated));
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.PayrollProcess', 'approve');

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * إيقاف مسير الرواتب — يمنع توليد runs جديدة من القسائم المرتبطة به.
     */
    public function deactivate($id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        try {
            if ($payroll->isInactive()) {
                return redirect()->back()->with('warning', 'مسير الرواتب موقوف بالفعل.');
            }

            $payroll->update(['status' => Payroll::STATUS_INACTIVE]);

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'تم إيقاف مسير الرواتب. لن تُولَّد رواتب جديدة من القسائم المرتبطة به.',
                'created_by' => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'تم إيقاف مسير الرواتب.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.PayrollProcess', 'deactivate');

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * إعادة تنشيط مسير موقوف.
     */
    public function activate($id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        try {
            if ($payroll->isActive()) {
                return redirect()->back()->with('warning', 'مسير الرواتب نشط بالفعل.');
            }

            $payroll->update([
                'status' => Payroll::STATUS_ACTIVE,
                'approved_at' => $payroll->approved_at ?? now(),
                'approved_by' => $payroll->approved_by ?? auth()->id(),
            ]);

            $generated = $this->generatePeriodRuns($payroll->fresh());

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'تم إعادة تفعيل مسير الرواتب'.($generated ? ' وأُنشئت '.$generated.' صرفة للفترة الجارية' : '').'.',
                'created_by' => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'تم تفعيل مسير الرواتب. '.$this->generatedMessage($generated));
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.PayrollProcess', 'activate');

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * اعتماد جميع قسائم الرواتب المسودة المرتبطة بهذا المسير دفعة واحدة —
     * ضمن نطاق المستخدم وحده.
     */
    public function approveAll($id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        try {
            if (! $payroll->isActive()) {
                return redirect()->back()->with('error',
                    'لا يمكن اعتماد القسائم: مسير الرواتب نفسه غير نشط. اعتمد المسير أولاً.'
                );
            }

            $query = SalarySlip::where('payroll_id', $payroll->id)
                ->where('status', SalarySlip::STATUS_DRAFT);
            $this->limitToVisible($query, 'employee_id');
            $slips = $query->get();

            if ($slips->isEmpty()) {
                return redirect()->back()->with('warning', 'لا توجد قسائم بحالة "مسودة" للاعتماد ضمن هذا المسير.');
            }

            $approvedCount = 0;
            foreach ($slips as $slip) {
                if (! $slip->canBeApproved()) {
                    continue;
                }
                $slip->update([
                    'status' => SalarySlip::STATUS_APPROVED,
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);
                ModelsLog::create([
                    'type' => 'salary_slip_log',
                    'type_id' => $slip->id,
                    'type_log' => 'log',
                    'description' => 'تم الاعتماد ضمن مسير الرواتب #'.$payroll->id,
                    'created_by' => auth()->id(),
                ]);
                $approvedCount++;
            }

            $generated = $this->generatePeriodRuns($payroll);

            return redirect()->back()->with('success',
                "تم اعتماد {$approvedCount} قسيمة. ".$this->generatedMessage($generated)
            );
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.PayrollProcess', 'approveAll');

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * «تحديث صرفات الفترة» — يولّد ما نقص من صرفات الفترة الجارية لقسائم المسير.
     *
     * كان هذا المسارُ «اعتماد الصرفات»: يجمّع المعلّقة في مسير صرفاتٍ معتمد.
     * أُلغي ذلك الاعتمادُ الثاني، فبقي الاسمُ للمسار وصار عملُه التوليدَ وحده.
     */
    public function approveRuns($id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        if (! $payroll->isActive()) {
            return redirect()->back()->with('error', 'مسير الرواتب غير معتمد. اعتمد المسير أولاً.');
        }

        $generated = $this->generatePeriodRuns($payroll);

        if ($generated > 0) {
            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'أُنشئت '.$generated.' صرفة للفترة الجارية',
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->back()->with($generated ? 'success' : 'warning', $this->generatedMessage($generated));
    }

    /**
     * «صرف المستحق» — يصرف كلَّ صرفةٍ حلّ يومُ صرفها في هذا المسير.
     *
     * عبر `PayrollDisbursementService::disburse()` نفسِها بحرّاسها وقيودها
     * وحارس رصيد الخزينة — معاملةٌ مستقلّةٌ لكلّ صرفة، فإخفاقُ واحدةٍ (رصيدٌ لا
     * يكفي، عقدٌ منتهٍ) لا يُسقط ما صُرف قبلها. لا «صرف مبكر» هنا: ما قبل يوم
     * الصرف يُصرف فرداً من سجلّه بتأكيدٍ صريح.
     */
    public function disburseDue(Request $request, $id, PayrollDisbursementService $service)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        if (! $payroll->isActive()) {
            return redirect()->back()->with('error', 'مسير الرواتب «'.$payroll->name.'» غير معتمد — اعتمده أولاً ثمّ اصرف.');
        }

        $validated = $request->validate(['treasury_id' => 'nullable|integer']);

        $treasury = null;
        if (! empty($validated['treasury_id'])) {
            $treasury = TreasuryBalanceGuard::withdrawableAccounts()->firstWhere('id', (int) $validated['treasury_id']);
            if (! $treasury) {
                return redirect()->back()->with('error', 'الخزينة المختارة غير متاحة لك للصرف منها.');
            }
        }

        // ما نقص من صرفات الفترة يُولَّد أوّلاً — فلا تفوت قسيمةٌ اعتُمدت بعد المسير
        $this->generatePeriodRuns($payroll);

        $query = PayrollRun::query()
            ->due()
            ->whereHas('slip', fn ($q) => $q->where('payroll_id', $payroll->id))
            ->orderBy('scheduled_date')
            ->orderBy('id');
        $this->limitToAccessible($query, 'employee_id');
        $runs = $query->get();

        if ($runs->isEmpty()) {
            return redirect()->back()->with('warning', 'لا صرفاتٍ مستحقة الصرف اليوم في هذا المسير.');
        }

        $paid = 0;
        $netTotal = 0.0;
        $failures = [];

        foreach ($runs as $run) {
            $name = trim(($run->employee->first_name ?? '').' '.($run->employee->middle_name ?? '')) ?: '#'.$run->employee_id;

            try {
                if ($treasury) {
                    $run->treasury_id = $treasury->id;
                    $run->save();
                }

                $run = $service->disburse($run, PayrollRun::TRIGGER_MANUAL);

                ModelsLog::create([
                    'type' => 'payroll_run_log',
                    'type_id' => $run->id,
                    'type_log' => 'log',
                    'description' => sprintf(
                        'تم صرف راتب ضمن «صرف المستحق» لمسير %s للفترة %s → %s بصافي %s',
                        $payroll->name,
                        $run->period_from->toDateString(),
                        $run->period_to->toDateString(),
                        number_format((float) $run->net_salary, 2)
                    ),
                    'created_by' => auth()->id(),
                ]);

                $paid++;
                $netTotal += (float) $run->net_salary;
            } catch (\Throwable $e) {
                $failures[] = $name.' ('.$run->period_from->format('Y-m').'): '.$e->getMessage();
            }
        }

        ModelsLog::create([
            'type' => 'payroll_log',
            'type_id' => $payroll->id,
            'type_log' => 'log',
            'description' => sprintf('صرف المستحق: عددُ الرواتب المصروفة %d بصافي %s%s',
                $paid, number_format($netTotal, 2), $failures ? '، وتعذّر '.count($failures) : ''),
            'created_by' => auth()->id(),
        ]);

        $redirect = redirect()->back();
        if ($paid > 0) {
            $redirect->with('success', 'عددُ الرواتب المصروفة: '.$paid.' — بصافي '.\App\Support\CurrencySettings::format($netTotal).'.');
        }
        if ($failures) {
            $shown = array_slice($failures, 0, 10);
            $redirect->with('error', 'تعذّر صرف '.count($failures).' من الرواتب المستحقة: '.implode(' — ', $shown)
                .(count($failures) > 10 ? ' …' : ''));
        }

        return $redirect;
    }

    /**
     * يولّد صرفات قسائم المسير السارية حتى الفترة الجارية — بالتوليد نفسِه
     * الذي يستعمله الكرون (`PayrollDisbursementService::generateNextRun()`).
     *
     * يتقدّم فترةً فترة ما دامت بدايتُها لم تتجاوز اليوم، بحدٍّ أقصى 12 فترة
     * لكلّ قسيمة. والموجودُ لا يتكرّر: `generateNextRun` يرفض الفترة القائمة.
     */
    private function generatePeriodRuns(Payroll $payroll): int
    {
        if (! $payroll->isActive()) {
            return 0;
        }

        $service = app(PayrollDisbursementService::class);
        $today = now()->toDateString();
        $count = 0;

        $slips = SalarySlip::query()
            ->where('payroll_id', $payroll->id)
            ->whereIn('status', [SalarySlip::STATUS_APPROVED, SalarySlip::STATUS_ACTIVE])
            ->get();

        foreach ($slips as $slip) {
            for ($i = 0; $i < 12; $i++) {
                $period = $slip->generateNextPeriod();
                if (! $period || $period['period_from']->toDateString() > $today) {
                    break;
                }

                try {
                    $run = $service->generateNextRun($slip);
                } catch (\Throwable $e) {
                    OperationLog::failure($e, 'HR.PayrollProcess', 'generatePeriodRuns', $slip->id);
                    break;
                }

                if (! $run) {
                    break;
                }
                $count++;
            }
        }

        return $count;
    }

    private function generatedMessage(int $generated): string
    {
        return $generated > 0
            ? 'عددُ الصرفات المُنشأة للفترة الجارية: '.$generated.' — تصير «مستحقة الصرف» في يوم صرف كلّ قسيمة.'
            : 'لا صرفاتٍ جديدة للفترة الجارية: صرفاتُ القسائم السارية قائمةٌ بالفعل، أو لا قسائم معتمدة بعد.';
    }

    public function destroy($id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        try {
            // منع الحذف إن كان للمسير قسائم رواتب مرتبطة (بأي حالة)
            $linkedSlipsCount = SalarySlip::where('payroll_id', $payroll->id)->count();
            if ($linkedSlipsCount > 0) {
                return redirect()->back()->with('error',
                    "لا يمكن حذف هذا المسير: مرتبط بـ {$linkedSlipsCount} قسيمة راتب. ".
                    'أوقف القسائم أو احذفها أولاً، أو استخدم زر «إيقاف المسير» بدلاً من الحذف.'
                );
            }

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'تم حذف مسير رواتب',
                'created_by' => auth()->id(),
            ]);

            DB::transaction(function () use ($payroll) {
                $payroll->employees()->detach();
                $payroll->delete();
            });

            return redirect()->route('PayrollProcess.index')->with('success', 'تم حذف مسير الرواتب بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.PayrollProcess', 'destroy');

            return redirect()->back()->with('error', 'حدث خطأ أثناء حذف مسير الرواتب: '.$e->getMessage());
        }
    }

    // ============================================================
    // التصدير والطباعة
    // ============================================================

    /**
     * تصديرُ المسير إلى Excel: ورقةُ القسائم وورقةُ الموظفين — من الاستعلامين
     * نفسَيهما اللذين تعرضهما شاشةُ المسير، فيطابق الملفُّ الشاشةَ صفّاً بصفّ.
     */
    public function exportSlips($id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        try {
            $slips = $this->payrollSlips($payroll);
            $members = $this->payrollMembers($payroll);

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $payroll->id,
                'type_log' => 'log',
                'description' => 'تصدير المسير إلى Excel ('.$slips->count().' قسيمة، '.$members->count().' موظف)',
                'created_by' => auth()->id(),
            ]);

            return Excel::download(
                new PayrollRunExport($payroll, $slips, $members),
                'payroll_'.$payroll->id.'_'.date('Y-m-d').'.xlsx'
            );
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.PayrollProcess', 'exportSlips');

            return redirect()->back()->with('error', 'حدث خطأ أثناء التصدير: '.$e->getMessage());
        }
    }

    /**
     * طباعةُ كشف المسير بقالب «كشف مسير رواتب» من «قوالب المستندات».
     *
     * كانت الطباعةُ `window.print()` على شاشة العرض نفسِها بأنماطٍ مكتوبةٍ
     * فيها — لا تتبع القالب الذي صمّمه المستخدم ولا ترويسة المنشأة.
     */
    public function print(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);
        $this->authorizePayroll($payroll);

        $html = $this->renderPayrollTemplate('payroll_sheet', $payroll->id, $request->input('template'), $this->visibleEmployeeIds());

        if ($html === null) {
            return redirect()->route('PayrollProcess.show', $payroll->id)
                ->with('error', 'لا يوجد قالبُ طباعةٍ فعّالٌ لـ«كشف مسير رواتب». فعّل قالباً من الإعدادات ← قوالب المستندات.');
        }

        return $this->printResponse($html, 'مسير الرواتب — '.$payroll->name, route('PayrollProcess.show', $payroll->id));
    }
}
