<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Department;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\FunctionalLevels;
use App\Models\JopTitle;
use App\Models\Log as ModelsLog;
use App\Models\SalaryItem;
use App\Models\User;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Requests\ContractRequest;
use Modules\HR\Services\ContractService;
use Modules\HR\Support\BranchTree;

/**
 * عقود الموظفين.
 *
 * ── الصلاحيات (مجموعة `contracts.*`) والنطاق ──
 *
 *   • القائمة `contracts.index`، والعرض `contracts.show`، والطباعة
 *     `contracts.print` — كلُّها داخل نطاق الموظفين (`ScopesToAccessibleEmployees`
 *     — صلاحيةُ النطاق وفرعُ الشريط العلويّ).
 *   • لا صلاحيةَ «عقدي أنا»: الموظفُ الذي يرى عقدَه وحده يحمل
 *     `contracts.index/show/print` + `data_scope_own`، فيقصره النطاقُ عليه.
 *   • الإنشاء `contracts.create`، والتعديل `contracts.edit`، والحذف
 *     `contracts.destroy`، والاعتماد `contracts.approve` — كلٌّ بصلاحيته،
 *     وكلُّها على موظفٍ داخل النطاق.
 *   • **الاعتماد**: العقدُ الجديد «مسودّة» حتى يُعتمد («اعتماد العقد»).
 *     وتعديلُ عقدٍ معتمد لا يكون إلا بـ`contracts.edit` ويُسجَّل في سجلّ
 *     النشاط «تعديل عقد معتمد» — ويبقى معتمداً. ومسيرُ الرواتب ونهايةُ
 *     الخدمة يقرآن العقدَ كما كانا، معتمداً كان أو مسودّة.
 *
 * الحارسُ على السجلّ المفرد لا على القائمة وحدها: `show/{id}` يُطلب
 * بمعرّفٍ مباشر، فمن غيّر الرقمَ في العنوان بلغ عقدَ غيره.
 */
class ContractsController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct(private ContractService $contracts)
    {
        $this->middleware('permission.role:contracts.index', ['only' => ['index']]);
        $this->middleware('permission.role:contracts.show', ['only' => ['show']]);
        $this->middleware('permission.role:contracts.print', ['only' => ['printTemplate', 'printContract', 'printContract1']]);
        $this->middleware('permission.role:contracts.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:contracts.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:contracts.destroy', ['only' => ['destroy']]);
        $this->middleware('permission.role:contracts.approve', ['only' => ['approve']]);
        // بياناتُ الموظف تُطلب من نموذجَي الإنشاء والتعديل
        $this->middleware('permission.role:contracts.create|contracts.edit', ['only' => ['employeeData']]);
    }

    // ───────────────────────── الحراسة ─────────────────────────

    /**
     * هل يملك المستخدمُ هذا الفعلَ على هذا العقد؟
     *
     * كلُّ فعلٍ بصلاحيته وداخل نطاق الموظفين — والاعتمادُ للمسودّة وحدها.
     */
    protected function canOnContract(string $action, Contract $contract): bool
    {
        $inScope = fn () => $this->canAccessEmployee($contract->employee_id);

        return match ($action) {
            'view' => hasPermission('contracts.show') && $inScope(),
            'print' => hasPermission('contracts.print') && $inScope(),
            'edit' => hasPermission('contracts.edit') && $inScope(),
            'destroy' => hasPermission('contracts.destroy') && $inScope(),
            'approve' => ! $contract->isApproved() && hasPermission('contracts.approve') && $inScope(),
            default => false,
        };
    }

    /** أفعالُ العقد للشاشة — تُخفى الأزرارُ التي لا يملكها المستخدم */
    protected function contractActions(Contract $contract): array
    {
        $out = [];
        foreach (['view', 'print', 'edit', 'destroy', 'approve'] as $action) {
            $out[$action] = $this->canOnContract($action, $contract);
        }

        return $out;
    }

    private function authorizeOn(string $action, Contract $contract): void
    {
        abort_unless($this->canOnContract($action, $contract), 403, match ($action) {
            'view', 'print' => 'هذا العقد خارج نطاق ما يحقّ لك الاطّلاع عليه.',
            'approve' => 'لا يمكنك اعتماد هذا العقد.',
            default => 'ليست لك صلاحية على هذا العقد.',
        });
    }

    // ───────────────────────── القائمة ─────────────────────────

    public function index(Request $request)
    {
        $query = Contract::with(['employee', 'employee.department', 'jobTitle', 'salaryItems', 'creator']);

        /*
            النطاقُ على الموظف صاحبِ العقد، لا على كاتبه (`created_by`) —
            فصاحبُ `data_scope_own` يرى عقدَه هو وحده.
        */
        $this->scopeToEmployees($query);

        if ($request->filled('employee_search')) {
            $query->where('employee_id', $request->employee_search);
        }
        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.$request->code.'%');
        }
        if ($request->filled('end_date_from')) {
            $query->whereDate('end_date', '>=', $request->end_date_from);
        }
        if ($request->filled('end_date_to')) {
            $query->whereDate('end_date', '<=', $request->end_date_to);
        }
        if ($request->filled('job_title_id')) {
            $query->where('job_title_id', $request->job_title_id);
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->department_id));
        }
        if ($request->filled('contract_component')) {
            $query->where('description', 'LIKE', '%'.$request->contract_component.'%');
        }
        if ($request->filled('start_date_from')) {
            $query->whereDate('start_date', '>=', $request->start_date_from);
        }
        if ($request->filled('start_date_to')) {
            $query->whereDate('start_date', '<=', $request->start_date_to);
        }
        // بندُ الراتب: نُسخُ البند في العقد تحمل اسمَه لا معرّفَه
        if ($request->filled('salary_item_id')) {
            $name = SalaryItem::whereKey($request->salary_item_id)->value('name');
            $query->whereHas('salaryItems', fn ($q) => $q->where('name', $name));
        }
        if ($request->filled('join_date_from')) {
            $query->whereDate('join_date', '>=', $request->join_date_from);
        }
        if ($request->filled('join_date_to')) {
            $query->whereDate('join_date', '<=', $request->join_date_to);
        }
        if ($request->filled('probation_date_from')) {
            $query->whereDate('probation_end_date', '>=', $request->probation_date_from);
        }
        if ($request->filled('probation_date_to')) {
            $query->whereDate('probation_end_date', '<=', $request->probation_date_to);
        }
        if ($request->filled('employee_status')) {
            $query->whereHas('employee', fn ($q) => $q->where('status', $request->employee_status));
        }
        // حالةُ العقد: ساري / منتهٍ / ينتهي خلال 30 يوماً
        if ($request->filled('contract_status')) {
            $today = now()->startOfDay();
            match ($request->contract_status) {
                'active' => $query->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today)),
                'expired' => $query->whereDate('end_date', '<', $today),
                'expiring' => $query->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()]),
                default => null,
            };
        }
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }
        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn ($q) => BranchTree::apply($q, $request->branch_id));
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if (in_array($request->input('approval_status'), [Contract::APPROVAL_DRAFT, Contract::APPROVAL_APPROVED], true)) {
            $query->where('approval_status', $request->input('approval_status'));
        }

        // منشئو العقود التي يراها المستخدم — لا كلُّ منشئي العقود في المنشأة
        $creatorIds = (clone $query)->reorder()->whereNotNull('created_by')->distinct()->pluck('created_by');

        $contracts = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        // فلترُ الموظف من النطاق نفسِه — صاحبُ `data_scope_own` لا يرى فيه إلا نفسَه
        $employees = $this->scopedEmployeeOptions([]);

        return view('hr::salaries.contracts.index', [
            'contracts' => $contracts,
            'employees' => $employees,
            'jobTitles' => JopTitle::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'salaryItems' => SalaryItem::query()->catalog()->orderBy('name')->get(['id', 'name']),
            'users' => User::whereIn('id', $creatorIds)->get(['id', 'name']),
            'contractActions' => fn (Contract $c) => $this->contractActions($c),
        ]);
    }

    // ───────────────────────── الإنشاء ─────────────────────────

    public function create(Request $request)
    {
        /*
            رقمُ العقد من شاشة «ترقيم المستندات» — `peek` لا تستهلك الرقم:
            هذه معاينةٌ في نموذج الإنشاء، والاستهلاكُ عند الحفظ في `store`.
        */
        try {
            $nextNumber = peek_document_number('contracts');
        } catch (\Throwable $e) {
            $nextNumber = null;
        }

        return view('hr::salaries.contracts.create', $this->formData() + [
            'contract' => new Contract([
                'type_contract' => Contract::TYPE_PERIOD,
                'duration' => 1,
                'duration_unit' => Contract::DURATION_YEAR,
                'receiving_cycle' => Contract::CYCLE_MONTHLY,
                'employee_id' => $request->integer('employee_id') ?: null,
            ]),
            'employees' => $this->scopedEmployeeOptions([]),
            'nextNumber' => $nextNumber,
            'contractAdditions' => collect(),
            'contractDeductions' => collect(),
        ]);
    }

    public function store(ContractRequest $request)
    {
        if (! $this->canAccessEmployee($request->employee_id)) {
            return back()->withInput()->withErrors(['employee_id' => 'هذا الموظف خارج نطاق الموظفين المصرّح لك بهم.']);
        }

        try {
            DB::beginTransaction();

            $contract = new Contract($this->contractAttributes($request));
            // الاستهلاك الفعلي للرقم من تسلسل «العقود» المضبوط في الإعدادات.
            $contract->code = next_document_number('contracts');
            $contract->created_by = auth()->id();
            // العقدُ الجديد مسودّةٌ حتى يُعتمد (`contracts.approve`)
            $contract->approval_status = Contract::APPROVAL_DRAFT;
            $contract->save();

            $basic = (float) ($request->input('amount') ?? 0);
            $this->contracts->syncItems($contract, $this->contracts->buildItemRows($request->all(), $basic));

            $this->storeAttachment($contract, $request);

            OperationLog::record('created', 'salary_contracts_log', $contract, 'تم انشاء عقد رقم **'.$contract->code.'** للموظف **'.($contract->employee->full_name ?? '').'**');

            DB::commit();

            return redirect()->route('Contracts.show', $contract->id)->with('success', 'تم إضافة العقد بنجاح');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.Contracts', 'store');

            return back()->withInput()->with('error', 'حدث خطأ أثناء إضافة العقد: '.$e->getMessage());
        }
    }

    // ───────────────────────── العرض ─────────────────────────

    public function show($id)
    {
        $contract = Contract::with(['employee.department', 'employee.branch', 'jobTitle', 'jobLevel', 'salaryItems', 'parentContract', 'creator', 'approver'])->findOrFail($id);

        $this->authorizeOn('view', $contract);

        $logs = ModelsLog::where('type', 'salary_contracts_log')
            ->where('type_id', $id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        return view('hr::salaries.contracts.show', [
            'contract' => $contract,
            'logs' => $logs,
            'additionItems' => $contract->salaryItems->where('type', SalaryItem::TYPE_ADDITION)->values(),
            'deductionItems' => $contract->salaryItems->where('type', SalaryItem::TYPE_DEDUCTION)->values(),
            'totals' => $contract->salaryTotals(),
            'can' => $this->contractActions($contract),
            'printTemplates' => PrintSettings::templatesFor('contract'),
        ]);
    }

    // ───────────────────────── التعديل ─────────────────────────

    public function edit($id)
    {
        $contract = Contract::with(['employee', 'salaryItems'])->findOrFail($id);

        $this->authorizeOn('edit', $contract);

        $employees = $this->scopedEmployeeOptions([]);
        if ($contract->employee && ! $employees->contains('id', $contract->employee_id)) {
            $employees->push($contract->employee);
        }

        return view('hr::salaries.contracts.edit', $this->formData($contract) + [
            'contract' => $contract,
            'employees' => $employees,
            'contractAdditions' => $contract->salaryItems->where('type', SalaryItem::TYPE_ADDITION)->values(),
            'contractDeductions' => $contract->salaryItems->where('type', SalaryItem::TYPE_DEDUCTION)->values(),
        ]);
    }

    /*
        كان التعديلُ بلا تحقّقٍ ولا يكتب إلا ما امتلأ من الحقول — فلا يُفرَّغ
        وصفٌ ولا يُمحى تاريخُ انتهاء — ولا يستبدل البنودَ إلا إن وصل صفٌّ
        واحدٌ على الأقل. صار يمرّ بقاعدة الإنشاء نفسِها ويكتب النموذجَ كاملاً.
    */
    public function update(ContractRequest $request, $id)
    {
        $contract = Contract::findOrFail($id);
        $this->authorizeOn('edit', $contract);

        if (! $this->canAccessEmployee($request->employee_id)) {
            return back()->withInput()->withErrors(['employee_id' => 'هذا الموظف خارج نطاق الموظفين المصرّح لك بهم.']);
        }

        try {
            DB::beginTransaction();

            $contract->fill($this->contractAttributes($request));
            $changes = OperationLog::diff($contract, ['employee_id', 'job_title_id', 'job_level_id', 'start_date', 'end_date', 'duration', 'duration_unit', 'amount', 'receiving_cycle']);
            $contract->save();

            $basic = (float) ($request->input('amount') ?? 0);
            $this->contracts->syncItems($contract, $this->contracts->buildItemRows($request->all(), $basic, $contract->id));

            $this->storeAttachment($contract, $request);

            // تعديلُ عقدٍ معتمد يُسجَّل بصفته — ويبقى العقدُ معتمداً
            OperationLog::record(
                'updated',
                'salary_contracts_log',
                $contract,
                ($contract->isApproved() ? 'تم تعديل عقد **معتمد** رقم **' : 'تم تعديل عقد رقم **').$contract->code.'**',
                $changes
            );

            DB::commit();

            return redirect()->route('Contracts.show', $contract->id)->with('success', 'تم تحديث العقد بنجاح');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.Contracts', 'update', (int) $id);

            return back()->withInput()->with('error', 'حدث خطأ أثناء تحديث العقد: '.$e->getMessage());
        }
    }

    // ───────────────────────── الحذف ─────────────────────────

    /*
        كان يستدعي `salaryItems()->detach()` على علاقة `hasMany` (فيرمي
        دائماً) ويكتب في سجلّ النشاط أعمدةً لا وجود لها — فالحذفُ لم ينجح
        مرّةً قطّ.
    */
    public function destroy($id)
    {
        $contract = Contract::with('employee')->findOrFail($id);
        $this->authorizeOn('destroy', $contract);

        try {
            DB::beginTransaction();

            $employeeName = $contract->employee->full_name ?? 'غير محدد';
            $code = $contract->code ?? 'غير محدد';
            $attachment = $contract->attachments;

            SalaryItem::where('contracts_id', $contract->id)->delete();
            Contract::where('parent_contract_id', $contract->id)->update(['parent_contract_id' => null]);
            $contract->delete();

            OperationLog::record('deleted', 'salary_contracts_log', (int) $id, 'تم حذف عقد الموظف **'.$employeeName.'** — رقم العقد **'.$code.'**');

            DB::commit();

            if ($attachment && is_string($attachment)) {
                $path = public_path('assets/uploads/contracts/'.basename($attachment));
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            return redirect()->route('Contracts.index')->with('success', 'تم حذف العقد بنجاح');
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.Contracts', 'destroy', (int) $id);

            return back()->with('error', 'حدث خطأ أثناء حذف العقد: '.$e->getMessage());
        }
    }

    // ───────────────────────── الاعتماد ─────────────────────────

    /**
     * «اعتماد العقد» — ينقل المسودّةَ إلى «معتمد» ويختمها بالمعتمِد وتاريخه.
     * لا يمسّ الراتبَ ولا البنود: المسيرُ يقرأ العقدَ كما كان.
     */
    public function approve($id)
    {
        $contract = Contract::with('employee')->findOrFail($id);

        if ($contract->isApproved()) {
            return redirect()->route('Contracts.show', $contract->id)->with('error', 'العقد معتمدٌ سابقاً.');
        }

        $this->authorizeOn('approve', $contract);

        try {
            DB::transaction(function () use ($contract) {
                $locked = Contract::lockForUpdate()->findOrFail($contract->id);

                if ($locked->isApproved()) {
                    throw new \RuntimeException('العقد معتمدٌ سابقاً.');
                }

                $locked->forceFill([
                    'approval_status' => Contract::APPROVAL_APPROVED,
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ])->save();

                OperationLog::record('approved', 'salary_contracts_log', $locked, 'تم اعتماد عقد رقم **'.$locked->code.'** للموظف **'.($contract->employee->full_name ?? '').'**');
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Contracts', 'approve', (int) $id);

            return redirect()->route('Contracts.show', $contract->id)->with('error', 'تعذّر اعتماد العقد: '.$e->getMessage());
        }

        return redirect()->route('Contracts.show', $contract->id)->with('success', 'اعتُمد العقد.');
    }

    // ───────────────────────── بيانات الموظف ─────────────────────────

    /**
     * ما يُملأ به العقد عند اختيار الموظف — المسمّى والمستوى والفرع والقسم
     * وتاريخ التعيين وعقودُه السابقة. `?except=` يستثني العقدَ الجاري تعديلُه
     * من قائمة «العقد الأساسي».
     */
    public function employeeData(Request $request, $employee)
    {
        $model = Employee::find($employee);

        if (! $model || ! $this->canAccessEmployee($model->id)) {
            return response()->json(['success' => false, 'message' => 'الموظف غير موجود أو خارج نطاقك.'], 404);
        }

        return response()->json([
            'success' => true,
            'employee' => $this->contracts->employeeProfile($model, $request->integer('except') ?: null),
        ]);
    }

    // ───────────────────────── الطباعة ─────────────────────────

    /**
     * طباعةُ العقد بقالبٍ من «قوالب المستندات» (نوع `contract`) — المطلوبِ
     * بمعرّفه أو الافتراضيِّ للنوع. لا قالبَ مدمجاً: ما يُصمَّم في الإعدادات
     * هو ما يُطبع، ومن لم يُعدّ قالباً يُدَلّ على شاشة القوالب.
     */
    public function printTemplate(Request $request, $id, $template = null)
    {
        $contract = Contract::findOrFail($id);
        $this->authorizeOn('print', $contract);

        $template = $template ?? $request->query('template');

        $model = is_numeric($template)
            ? DocumentTemplate::where('id', (int) $template)->where('document_type', 'contract')->first()
            : PrintSettings::defaultTemplateFor('contract');

        if (! $model) {
            return redirect()->route('Contracts.show', $contract->id)
                ->with('error', 'لا يوجد قالب طباعة فعّال لنوع «عقد عمل». أنشئ قالباً أو فعّله من الإعدادات ← قوالب المستندات.');
        }

        try {
            $data = app(DocumentTemplateDataService::class)->getDocumentData('contract', $contract->id);
            $html = app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Contracts', 'print', $contract);

            return redirect()->route('Contracts.show', $contract->id)
                ->with('error', 'تعذّر تصيير قالب الطباعة: '.$e->getMessage());
        }

        return response(DocumentPrintPage::wrap(
            $html,
            'عقد عمل '.($contract->code ?: $contract->id),
            route('Contracts.show', $contract->id),
            $request->boolean('auto_print')
        ));
    }

    /** المسارُ القديم «طباعة - عرض» — يُحوَّل إلى طباعة القالب كي لا تنكسر الروابط */
    public function printContract1($id)
    {
        return redirect()->route('Contracts.printTemplate', ['id' => $id]);
    }

    /** المسارُ القديم «طباعة - PDF» — صفحةُ القالب نفسُها بفتح نافذة الطباعة (حفظ PDF منها) */
    public function printContract($id)
    {
        return redirect()->route('Contracts.printTemplate', ['id' => $id, 'auto_print' => 1]);
    }

    // ───────────────────────── مساعدات ─────────────────────────

    /** قوائمُ النموذج المشتركة بين الإنشاء والتعديل */
    private function formData(?Contract $contract = null): array
    {
        return [
            'jopTitle' => JopTitle::orderBy('name')->get(['id', 'name']),
            'functionalLevels' => FunctionalLevels::orderBy('name')->get(['id', 'name']),
            'additionItems' => ContractService::catalogItems(SalaryItem::TYPE_ADDITION),
            'deductionItems' => ContractService::catalogItems(SalaryItem::TYPE_DEDUCTION),
            'currencyCode' => ContractService::currency(),
            'parentContracts' => $contract
                ? Contract::where('employee_id', $contract->employee_id)->where('id', '!=', $contract->id)->orderByDesc('start_date')->get(['id', 'code', 'start_date', 'end_date'])
                : collect(),
        ];
    }

    /** حقولُ العقد من طلبٍ تحقّقت منه `ContractRequest` */
    private function contractAttributes(ContractRequest $request): array
    {
        $isPeriod = (int) $request->input('type_contract') === Contract::TYPE_PERIOD;

        return [
            'employee_id' => $request->employee_id,
            'job_title_id' => $request->job_title_id,
            'job_level_id' => $request->job_level_id,
            // `salary_temp_id` لا يُكتب: «قوالب الرواتب» أُلغيت (2026-09-24) — العمودُ باقٍ للعقود القديمة
            'description' => $request->description,
            'parent_contract_id' => $request->parent_contract_id ?: null,
            'start_date' => $request->start_date,
            'type_contract' => (int) $request->type_contract,
            'duration' => $isPeriod ? (int) $request->duration : null,
            'duration_unit' => $isPeriod ? (int) $request->duration_unit : null,
            // في نوع «مدة» حسبته `ContractRequest::prepareForValidation()` من البداية والمدّة
            'end_date' => $request->end_date ?: null,
            'amount' => $request->filled('amount') ? (float) $request->amount : null,
            'join_date' => $request->join_date,
            'probation_end_date' => $request->probation_end_date,
            'contract_date' => $request->contract_date,
            'receiving_cycle' => $request->receiving_cycle ?: Contract::CYCLE_MONTHLY,
            // العملةُ من الإعدادات وحدها — ما يصل من النموذج يُهمَل
            'currency' => ContractService::currency(),
        ];
    }

    private function storeAttachment(Contract $contract, Request $request): void
    {
        if (! $request->hasFile('attachments') || ! $request->file('attachments')->isValid()) {
            return;
        }

        $file = $request->file('attachments');
        $old = $contract->attachments;

        $filename = time().'_'.preg_replace('/[^\w.\-]+/u', '_', $file->getClientOriginalName());
        $file->move(public_path('assets/uploads/contracts'), $filename);

        $contract->attachments = $filename;
        $contract->save();

        if ($old && is_string($old) && $old !== $filename) {
            $path = public_path('assets/uploads/contracts/'.basename($old));
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
