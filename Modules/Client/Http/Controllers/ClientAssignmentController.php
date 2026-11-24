<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Employee;
use App\Models\notifications;
use App\Services\ClientAccessService;
use App\Services\ClientAssignmentService;
use App\Support\AffiliationTree;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * إسناد العملاء إلى الموظفين — فردياً وبالمجموعة.
 *
 * **ما يأتي من الإعدادات** — «إعدادات وحدات العملاء» ← تبويب الموظفين:
 *
 *   • *التعدّد*  ← `allow_multiple`: مطفأً يُرفض إسنادُ موظفٍ ثانٍ لعميلٍ
 *                  له موظف. كان العميل يقبل أيّ عددٍ بلا حدّ ولا قاعدة.
 *   • *السقف*    ← `max_employees_per_client`: فارغاً بلا حدّ.
 *   • *الإشعار*  ← `notify_employee_on_assignment_change`.
 *
 * **الصلاحيات**: لم تكن على المتحكّم صلاحيةٌ واحدة رغم أن الإسناد يقرّر
 * من يرى العميل ومن يزوره ومن تُحتسب مبيعاتُه له. صارت على
 * `clients_assign_employees`.
 *
 * **السجلّات**: `OperationLog` في كل إسنادٍ وإزالة — كان الفشل وحده
 * يُسجَّل، فلا أثر لمن أعاد توزيع مئة عميل بين الموظفين.
 */
class ClientAssignmentController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientAssignments';

    protected $assignmentService;

    public function __construct(ClientAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;

        $this->middleware('auth');
        $this->middleware('permission:clients_assign_employees|client_follow_up_assign_clients_to_employees');
    }

    /** إشعار موظف بتغيّر تعييناته — مشروط بإعداد «إشعار الموظفين عند تغيير التعيين». */
    private function notifyEmployeeOfAssignmentChange(?int $employeeId, string $title, string $description): void
    {
        if (! $employeeId || ! ClientSettingsHelper::shouldNotifyEmployeeOnAssignmentChange()) {
            return;
        }

        $receiverId = Employee::find($employeeId)?->user?->id;
        if (! $receiverId) {
            return;
        }

        notifications::create([
            'user_id' => auth()->id(),
            'receiver_id' => $receiverId,
            'title' => $title,
            'description' => $description,
            'type' => 'client_assignment_change',
            'read' => false,
            'popup_shown' => false,
            'is_sent' => true,
        ]);
    }

    /**
     * عرض صفحة التعيين الجماعي
     *
     * كان للشاشة مُنتقيان لا يعرفان «تابع لـ»: قائمةُ «المناطق» (`Region_groub`)
     * تُسند عبر حيّ العميل، وقوائمُ متدرّجة للفروع تُسند عبر `clients.branch_id`
     * — الفرعِ **المحاسبيّ** المشتقّ لا الجهةِ التي نُسب إليها العميل. فمنفذٌ
     * تحت منطقة لا يظهر خياراً بنوعه، وعملاؤه يُسندون مع الفرع فوقه أو لا
     * يُسندون أبداً. صار مُنتقٍ واحد هو قائمةُ فلتر الفهرس بعينها
     * (`AffiliationTree::pathOptions`)، والعملاءُ عبر `Client::scopeInTreeNode`.
     */
    public function index()
    {
        Branch::preloadCache();

        $nodeOptions = $this->nodeOptions();
        $employees = $this->assignableEmployees();
        $allowMultiple = ClientSettingsHelper::clientEmployeesAllowMultiple();
        $maxEmployees = ClientSettingsHelper::clientEmployeesMax();

        /* «التعيينات الحالية» صارت ملخّصاً: موظفٌ لكلّ سطر بعدد عملائه وجهاته،
           وعملاؤه أنفسُهم يُفتحون في تقرير «العملاء المسندون للموظفين» حيث
           يُنقلون ويُزالون. كانت شجرةً ترسم كلَّ عميلٍ مسنَد في الصفحة (١٨٠٠+
           صفّاً بأزرارها) — ثقيلةً ولا يُبحث فيها. */
        $scoped = Client::query();
        app(ClientAccessService::class)->applyClientBranchTreeScope($scoped, $this->userScopeIds());

        $summary = DB::table('client_employee as ce')
            ->join('employees as e', 'e.id', '=', 'ce.employee_id')
            ->join('clients as c', 'c.id', '=', 'ce.client_id')
            ->whereNull('e.deleted_at')
            ->whereIn('ce.client_id', (clone $scoped)->select('clients.id'))
            ->groupBy('ce.employee_id')
            ->selectRaw('ce.employee_id, COUNT(DISTINCT ce.client_id) as clients, COUNT(DISTINCT COALESCE(c.tree_node_id, c.branch_id)) as nodes')
            ->get()
            ->keyBy('employee_id');

        $employeeSummary = Employee::whereIn('id', $summary->keys())->get()
            ->map(fn (Employee $employee) => [
                'id' => (int) $employee->id,
                'name' => $employee->full_name,
                'clients' => (int) $summary[$employee->id]->clients,
                'nodes' => (int) $summary[$employee->id]->nodes,
            ])
            ->sortByDesc('clients')
            ->values();

        $totalClients = (clone $scoped)->count();
        $assignedClients = DB::table('client_employee as ce')
            ->join('employees as e', 'e.id', '=', 'ce.employee_id')
            ->whereNull('e.deleted_at')
            ->whereIn('ce.client_id', (clone $scoped)->select('clients.id'))
            ->distinct()
            ->count('ce.client_id');

        $stats = [
            'assigned' => $assignedClients,
            'unassigned' => max(0, $totalClients - $assignedClients),
            'employees' => $employeeSummary->count(),
        ];

        return view('client::bulk-assignment.index', compact(
            'nodeOptions', 'employees', 'employeeSummary', 'stats', 'allowMultiple', 'maxEmployees'
        ));
    }

    // ═══════════════════ «تابع لـ» ونطاق المستخدم ═══════════════════

    /**
     * نطاقُ فرع المستخدم في الشجرة — `null` بلا حصر (فرعٌ رئيسي أو بلا فرع).
     *
     * @return array<int, int>|null
     */
    private function userScopeIds(): ?array
    {
        return app(ClientAccessService::class)->resolveUserBranchTreeScope(auth()->user());
    }

    /**
     * خياراتُ «تابع لـ» — نفسُ قائمة فلتر فهرس العملاء: كلُّ العقد عدا
     * الجذر الرئيسي، محصورةً بفرع الترويسة إن كان المستخدم في فرعٍ فرعيّ.
     */
    private function nodeOptions(): Collection
    {
        $scope = $this->userScopeIds();

        $nodes = Branch::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('is_main', 0)->orWhereNull('is_main'))
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope ?: [0]))
            ->orderBy('name')
            ->get();

        return AffiliationTree::pathOptions($nodes);
    }

    /** الموظفون المسموح إسنادُهم — «أقسام الموظفين المسموح تعيينهم للعملاء». */
    private function assignableEmployees(): Collection
    {
        return ClientSettingsHelper::scopeEmployeesToClientDepartments(Employee::query())
            ->orderBy('first_name')
            ->get();
    }

    /** قاعدةُ تحقّق «تابع لـ»: عقدةٌ من القائمة المعروضة لا أيُّ صفٍّ في الجدول. */
    private function nodeRule(): array
    {
        return ['required', 'integer', Rule::in($this->nodeOptions()->pluck('id')->all())];
    }

    /**
     * عملاءُ «تابع لـ» — العقدةُ وما تحتها افتراضاً، أو العقدةُ وحدها.
     *
     * @return array<int, int>
     */
    private function clientIdsUnderNode(int $nodeId, bool $includeDescendants): array
    {
        return Client::query()
            ->inTreeNode($nodeId, $includeDescendants)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private static function wantsDescendants(Request $request): bool
    {
        return ! in_array((string) $request->input('include_descendants', '1'), ['0', 'false', 'off'], true);
    }

    private function nodeName(int $nodeId): string
    {
        return (string) (Branch::findCached($nodeId)?->name ?? '#'.$nodeId);
    }

    private function respond(Request $request, array $result)
    {
        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 400);
        }

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /** إشعارُ من نُزع عنهم عملاؤهم بـ«نقل» — كانوا يفقدون العملاء بصمت. */
    private function notifyReleased(array $employeeIds, string $what): void
    {
        foreach ($employeeIds as $id) {
            $this->notifyEmployeeOfAssignmentChange((int) $id, 'نقل عملاء', 'نُقل عنك '.$what.' إلى موظفٍ آخر');
        }
    }

    // ═══════════════════ الإسناد ═══════════════════

    /**
     * إسنادُ عملاء «تابع لـ» إلى موظف — إضافةً أو نقلاً.
     *
     * يبقى اسمُ المسار كما هو (`assign-branch-to-employee`) ويقبل `branch_id`
     * القديم مع `tree_node`، كي لا ينكسر نموذجٌ محفوظ.
     */
    public function assignBranchToEmployee(Request $request)
    {
        if (! $request->filled('tree_node') && $request->filled('branch_id')) {
            $request->merge(['tree_node' => $request->input('branch_id')]);
        }

        $validated = $request->validate([
            'tree_node' => $this->nodeRule(),
            'employee_id' => 'required|exists:employees,id',
            'mode' => ['nullable', Rule::in([ClientAssignmentService::MODE_ADD, ClientAssignmentService::MODE_REPLACE])],
        ], [
            'tree_node.required' => 'اختر الجهة التي يتبع لها العملاء',
            'tree_node.in' => 'الجهة المختارة خارج نطاق فرعك',
            'employee_id.required' => 'يجب اختيار الموظف',
        ]);

        $nodeId = (int) $validated['tree_node'];
        $employeeId = (int) $validated['employee_id'];
        $mode = $validated['mode'] ?? ClientAssignmentService::MODE_ADD;
        $withChildren = self::wantsDescendants($request);
        $nodeName = $this->nodeName($nodeId);

        $result = $this->assignmentService->assignToEmployee(
            $this->clientIdsUnderNode($nodeId, $withChildren),
            $employeeId,
            $mode
        );

        if ($result['success'] && ($result['assigned_count'] + $result['replaced_count']) > 0) {
            $scopeText = 'عملاء «'.$nodeName.'»'.($withChildren ? ' وما يتبع له' : '');

            $this->notifyEmployeeOfAssignmentChange($employeeId, 'تعيين عملاء', 'تم تعيينك مسؤولاً عن '.$scopeText);
            $this->notifyReleased($result['released_employee_ids'], $scopeText);

            OperationLog::record(
                $mode === ClientAssignmentService::MODE_REPLACE ? 'node_transferred' : 'node_assigned',
                self::LOG_MODULE,
                null,
                ($mode === ClientAssignmentService::MODE_REPLACE ? 'نقل ' : 'إسناد ').$scopeText
                    .' إلى الموظف #'.$employeeId.' — '.$result['message'],
                ['new' => [
                    'tree_node' => $nodeId,
                    'include_descendants' => $withChildren,
                    'employee_id' => $employeeId,
                    'mode' => $mode,
                    'assigned' => $result['assigned_count'],
                    'replaced' => $result['replaced_count'],
                    'skipped' => $result['skipped_count'],
                ], 'old' => ['employee_ids' => $result['released_employee_ids']]]
            );
        }

        return $this->respond($request, $result);
    }

    /**
     * نقلُ إسنادٍ قائم من موظفٍ إلى آخر — عميلاً واحداً أو مجموعته كلَّها.
     *
     * كان نموذجُ التعديل يرسل **اسمَ** المجموعة في `group_id`، والتحقّقُ
     * يشترط معرّفاً في `branches` — فيُرفض كلُّ تعديلٍ بـ«المجموعة غير صالحة»
     * قبل أن يصل إلى أيّ منطق. و«المجموعة كاملة» كانت `attach` للجديد على
     * عملاء حيّ المنطقة كلّهم ولا تنزع القديم: لا نقل، بل إضافةٌ على كل عميلٍ
     * في المنطقة ولو لم يكن للموظف القديم.
     *
     * «المجموعة» هنا ما تعرضه الشاشة تحت الموظف: عملاؤه الذين يتبعون لتلك
     * الجهة بعينها.
     */
    public function transferAssignment(Request $request)
    {
        $validated = $request->validate([
            'old_employee_id' => 'required|integer',
            'employee_id' => 'required|exists:employees,id|different:old_employee_id',
            'edit_type' => ['required', Rule::in(['single', 'group'])],
            'client_id' => 'required_if:edit_type,single|nullable|exists:clients,id',
            'tree_node' => 'required_if:edit_type,group|nullable|integer',
        ], [
            'employee_id.required' => 'يجب اختيار الموظف الجديد',
            'employee_id.different' => 'اختر موظفاً غير الموظف الحالي',
        ]);

        $from = (int) $validated['old_employee_id'];
        $to = (int) $validated['employee_id'];

        if ($validated['edit_type'] === 'single') {
            $clientIds = [(int) $validated['client_id']];
            $what = 'العميل '.(Client::find($clientIds[0])?->trade_name ?? '');
        } else {
            $nodeId = (int) $validated['tree_node'];
            $onFrom = Employee::withTrashed()->find($from)?->clients()->pluck('clients.id')->all() ?? [];

            $clientIds = $nodeId
                ? Client::query()->inTreeNode($nodeId, false)->whereIn('id', $onFrom ?: [0])->pluck('id')->all()
                : Client::query()->whereIn('id', $onFrom ?: [0])->whereNull('tree_node_id')->whereNull('branch_id')->pluck('id')->all();

            $what = 'عملاء «'.($nodeId ? $this->nodeName($nodeId) : 'غير محدد').'»';
        }

        $result = $this->assignmentService->transferBetweenEmployees($clientIds, $from, $to);

        if ($result['success']) {
            $this->notifyReleased([$from], $what);
            $this->notifyEmployeeOfAssignmentChange($to, 'تعيين عملاء', 'تم تعيينك مسؤولاً عن '.$what);

            OperationLog::record(
                'reassigned',
                self::LOG_MODULE,
                $validated['edit_type'] === 'single' ? Client::find($clientIds[0]) : null,
                'نقل '.$what.' من الموظف #'.$from.' إلى الموظف #'.$to.' — '.$result['moved_count'].' عميل',
                ['old' => ['employee_id' => $from], 'new' => ['employee_id' => $to, 'client_ids' => $clientIds]]
            );
        }

        return $this->respond($request, $result);
    }

    /**
     * إسنادٌ متعدّد: عدّةُ جهاتٍ لعدّة موظفين في عمليةٍ واحدة.
     *
     * الصفوفُ تُنفَّذ من الأعلى إلى الأعمق: من أسند المنطقةَ لموظفٍ ومنفذاً
     * تحتها لآخر في وضع «نقل» يريد المنفذ للثاني — ولو نُفّذ بترتيب الإدخال
     * لمحت المنطقةُ ما سبقها.
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.tree_node' => $this->nodeRule(),
            'assignments.*.employee_id' => 'required|exists:employees,id',
            'mode' => ['nullable', Rule::in([ClientAssignmentService::MODE_ADD, ClientAssignmentService::MODE_REPLACE])],
        ], [
            'assignments.required' => 'يجب تحديد التعيينات',
            'assignments.*.tree_node.required' => 'اختر «تابع لـ» في كل صف',
            'assignments.*.tree_node.in' => 'إحدى الجهات المختارة خارج نطاق فرعك',
            'assignments.*.employee_id.required' => 'يجب اختيار الموظف لكل صف',
        ]);

        $mode = $validated['mode'] ?? ClientAssignmentService::MODE_ADD;
        $withChildren = self::wantsDescendants($request);
        $rows = collect($validated['assignments'])->map(fn ($r) => [
            'tree_node' => (int) $r['tree_node'],
            'employee_id' => (int) $r['employee_id'],
        ]);

        if ($mode === ClientAssignmentService::MODE_REPLACE && $rows->pluck('tree_node')->duplicates()->isNotEmpty()) {
            return $this->respond($request, [
                'success' => false,
                'message' => 'تكرّرت الجهة نفسُها في أكثر من صف — في «نقل» يحلّ الصفُّ الأخير محلّ الأول. احذف التكرار أو اختر «إضافة».',
            ]);
        }

        $depth = $this->nodeOptions()->pluck('depth', 'id');
        $rows = $rows->sortBy(fn ($r) => $depth->get($r['tree_node'], 0))->values();

        $totals = ['assigned_count' => 0, 'replaced_count' => 0, 'already_count' => 0, 'skipped_count' => 0];
        $lines = [];

        foreach ($rows as $row) {
            $r = $this->assignmentService->assignToEmployee(
                $this->clientIdsUnderNode($row['tree_node'], $withChildren),
                $row['employee_id'],
                $mode
            );

            foreach ($totals as $k => $_) {
                $totals[$k] += $r[$k] ?? 0;
            }
            $lines[] = '«'.$this->nodeName($row['tree_node']).'»: '.$r['message'];

            if (($r['assigned_count'] ?? 0) + ($r['replaced_count'] ?? 0) > 0) {
                $what = 'عملاء «'.$this->nodeName($row['tree_node']).'»';
                $this->notifyEmployeeOfAssignmentChange($row['employee_id'], 'تعيين عملاء', 'تم تعيينك مسؤولاً عن '.$what);
                $this->notifyReleased($r['released_employee_ids'] ?? [], $what);
            }
        }

        $changed = $totals['assigned_count'] + $totals['replaced_count'] > 0;

        if ($changed) {
            OperationLog::record(
                'bulk_assigned',
                self::LOG_MODULE,
                null,
                'إسناد متعدد ('.($mode === ClientAssignmentService::MODE_REPLACE ? 'نقل' : 'إضافة').'): '
                    .$rows->count().' صف على '.$rows->pluck('employee_id')->unique()->count().' موظف — '
                    .$totals['assigned_count'].' عميل',
                ['new' => ['assignments' => $rows->all(), 'mode' => $mode, 'include_descendants' => $withChildren, 'totals' => $totals]]
            );
        }

        return $this->respond($request, $totals + [
            'success' => $changed || $totals['already_count'] > 0,
            'message' => implode(' | ', $lines),
        ]);
    }

    /**
     * إجراءٌ على عملاءَ محدَّدين — من تقرير «العملاء المسندون للموظفين».
     *
     * العملاءُ إمّا محدَّدون من الجدول (`client_ids[]`) أو «كلُّ نتائج الفلتر»
     * (`apply_to=all` مع `filters[...]` = معاملاتُ رابط التقرير). وفي الحالين
     * يُسأل التقريرُ نفسُه عنهم ({@see \Modules\Client\Http\Controllers\Customers\CustomerAssignmentReportController::resolveClientIds()})
     * فلا يقع إجراءٌ على عميلٍ لا يعرضه التقريرُ لهذا المستخدم.
     *
     *   • `add`      إضافةُ الموظف بجانب من على العميل (بقواعد الإعدادات).
     *   • `replace`  نقلٌ إليه: يصير وحده على هؤلاء العملاء.
     *   • `transfer` من موظفٍ بعينه إلى آخر — يبقى بقيّةُ الموظفين.
     *   • `remove`   إزالةُ الموظف عن هؤلاء العملاء.
     */
    public function applyToClients(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['add', 'replace', 'transfer', 'remove'])],
            'employee_id' => 'required|exists:employees,id',
            'from_employee_id' => 'required_if:action,transfer|nullable|integer|different:employee_id',
            'apply_to' => ['nullable', Rule::in(['selected', 'all'])],
            'client_ids' => 'required_unless:apply_to,all|nullable|array',
            'client_ids.*' => 'integer',
            'filters' => 'nullable|array',
        ], [
            'employee_id.required' => 'اختر الموظف',
            'client_ids.required_unless' => 'حدّد عميلاً واحداً على الأقل من الجدول',
            'from_employee_id.required_if' => 'اختر الموظف الذي يُنقل عنه',
            'from_employee_id.different' => 'اختر موظفاً غير الموظف الحالي',
        ]);

        $all = ($validated['apply_to'] ?? 'selected') === 'all';
        $clientIds = app(\Modules\Client\Http\Controllers\Customers\CustomerAssignmentReportController::class)
            ->resolveClientIds($all ? ($validated['filters'] ?? []) : [], $all ? null : array_map('intval', $validated['client_ids'] ?? []));

        if ($clientIds === []) {
            return $this->respond($request, ['success' => false, 'message' => 'لا عملاء مطابقون ضمن نطاقك']);
        }

        $employeeId = (int) $validated['employee_id'];
        $action = $validated['action'];
        $what = count($clientIds).' عميل';

        if ($action === 'remove') {
            $removed = 0;
            foreach (array_chunk($clientIds, 1000) as $chunk) {
                $removed += DB::table('client_employee')->whereIn('client_id', $chunk)->where('employee_id', $employeeId)->delete();
            }
            $result = [
                'success' => $removed > 0,
                'message' => $removed > 0
                    ? 'أُزيل '.trim((string) Employee::find($employeeId)?->full_name).' عن '.$removed.' عميل'
                    : 'الموظف المختار ليس مسنداً لأيٍّ من العملاء المحدَّدين',
            ];

            if ($removed > 0) {
                $this->notifyEmployeeOfAssignmentChange($employeeId, 'إزالة تعيين عملاء', 'تم إزالة تعيينك عن '.$removed.' عميل');
            }
        } elseif ($action === 'transfer') {
            $from = (int) $validated['from_employee_id'];
            $result = $this->assignmentService->transferBetweenEmployees($clientIds, $from, $employeeId);

            if ($result['success']) {
                $this->notifyReleased([$from], $result['moved_count'].' عميل');
                $this->notifyEmployeeOfAssignmentChange($employeeId, 'تعيين عملاء', 'تم تعيينك مسؤولاً عن '.$result['moved_count'].' عميل');
            }
        } else {
            $result = $this->assignmentService->assignToEmployee($clientIds, $employeeId, $action);

            if ($result['success'] && ($result['assigned_count'] + $result['replaced_count']) > 0) {
                $this->notifyEmployeeOfAssignmentChange($employeeId, 'تعيين عملاء', 'تم تعيينك مسؤولاً عن '.$what);
                $this->notifyReleased($result['released_employee_ids'], $what);
            }
        }

        if ($result['success']) {
            OperationLog::record(
                'clients_'.$action,
                self::LOG_MODULE,
                count($clientIds) === 1 ? Client::find($clientIds[0]) : null,
                ['add' => 'إضافة موظف إلى', 'replace' => 'نقل إلى موظف:', 'transfer' => 'نقل بين موظفَين:', 'remove' => 'إزالة موظف عن'][$action]
                    .' '.$what.' ('.($all ? 'كل نتائج الفلتر' : 'محدَّدون من التقرير').') — '.$result['message'],
                ['new' => [
                    'action' => $action,
                    'employee_id' => $employeeId,
                    'from_employee_id' => $validated['from_employee_id'] ?? null,
                    'client_ids' => array_slice($clientIds, 0, 500),
                    'clients_count' => count($clientIds),
                    'filters' => $all ? ($validated['filters'] ?? []) : null,
                ]]
            );
        }

        return $this->respond($request, $result);
    }

    /**
     * معاينةُ «تابع لـ» قبل التعيين: كم عميلاً، وكم منهم بلا موظف، وكم على
     * الموظف المختار أصلاً — كي يعرف المستخدم ما سيحدث قبل أن يضغط.
     */
    public function getGroupInfo(Request $request, $groupId)
    {
        $nodeId = (int) $groupId;
        $options = $this->nodeOptions()->keyBy('id');

        if (! $options->has($nodeId)) {
            return response()->json(['error' => 'الجهة غير موجودة أو خارج نطاق فرعك'], 404);
        }

        $clientIds = $this->clientIdsUnderNode($nodeId, self::wantsDescendants($request));

        $assigned = collect();
        foreach (array_chunk($clientIds, 1000) as $chunk) {
            // صفوفٌ لموظفين غير موجودين لا تجعل العميلَ «مسنداً» — لا يراها أحد
            $assigned = $assigned->merge(
                DB::table('client_employee')
                    ->join('employees', 'employees.id', '=', 'client_employee.employee_id')
                    ->whereNull('employees.deleted_at')
                    ->whereIn('client_employee.client_id', $chunk)
                    ->get(['client_employee.client_id', 'client_employee.employee_id'])
            );
        }

        $withAny = $assigned->pluck('client_id')->unique()->count();
        $employeeId = (int) $request->input('employee_id');

        /* العملاءُ أنفسُهم لا عددُهم وحده — كلٌّ بما يتبع له ونوعِه ومن عليه
           الآن، فيرى المستخدم قبل الضغط أيَّ عملاءٍ سيُسندون بالضبط. */
        Branch::preloadCache();
        $limit = 500;
        $employeeNames = Employee::whereIn('id', $assigned->pluck('employee_id')->unique())->get()
            ->mapWithKeys(fn ($e) => [(int) $e->id => $e->full_name]);
        $byClient = $assigned->groupBy('client_id');

        $clients = Client::query()
            ->whereIn('id', array_slice($clientIds, 0, $limit) ?: [0])
            ->orderBy('trade_name')
            ->get(['id', 'code', 'trade_name', 'tree_node_id', 'branch_id'])
            ->map(function (Client $client) use ($options, $employeeNames, $byClient) {
                $node = $client->resolveTreeNode();
                $option = $node ? $options->get((int) $node->id) : null;

                return [
                    'id' => (int) $client->id,
                    'code' => $client->code,
                    'name' => $client->trade_name,
                    'node' => $option['name'] ?? $node?->name ?? 'غير محدد',
                    'node_type' => $option['type_label'] ?? $node?->displayTypeLabel() ?? '',
                    'employees' => $byClient->get($client->id, collect())
                        ->pluck('employee_id')->unique()
                        ->map(fn ($id) => $employeeNames->get((int) $id))
                        ->filter()->values()->all(),
                ];
            })
            ->values();

        return response()->json([
            'node' => $options->get($nodeId),
            'clients_count' => count($clientIds),
            'unassigned_count' => count($clientIds) - $withAny,
            'with_employee_count' => $employeeId
                ? $assigned->where('employee_id', $employeeId)->pluck('client_id')->unique()->count()
                : null,
            'clients' => $clients,
            'clients_truncated' => count($clientIds) > $limit,
        ]);
    }

    /**
     * إزالة تعيين موظف من عميل معين
     */
    public function removeSingleAssignment(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'employee_id' => 'required|exists:employees,id',
        ]);

        try {
            $client = Client::find($validated['client_id']);
            $employee = Employee::find($validated['employee_id']);

            $client->employees()->detach($validated['employee_id']);

            $this->notifyEmployeeOfAssignmentChange((int) $validated['employee_id'], 'إزالة تعيين عميل', 'تم إزالة تعيينك عن العميل '.$client->trade_name);

            OperationLog::record(
                'unassigned',
                self::LOG_MODULE,
                $client,
                'إزالة إسناد الموظف #'.$validated['employee_id'].' عن العميل '.$client->trade_name,
                ['old' => ['employee_id' => (int) $validated['employee_id']]]
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "تم حذف تعيين {$employee->first_name} من {$client->trade_name}",
                ]);
            }

            return redirect()->back()->with('success', 'تم حذف التعيين بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'remove_single_assignment');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ: '.$e->getMessage(),
                ], 400);
            }

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * إزالة تعيين موظف من مجموعة عملاء
     */
    public function removeEmployeeFromGroup(Request $request)
    {
        if (! $request->filled('tree_node') && $request->filled('group_id')) {
            $request->merge(['tree_node' => $request->input('group_id')]);
        }

        $validated = $request->validate([
            'tree_node' => $this->nodeRule(),
            'employee_id' => 'required|exists:employees,id',
        ]);

        try {
            $nodeId = (int) $validated['tree_node'];
            $clientIds = $this->clientIdsUnderNode($nodeId, self::wantsDescendants($request));

            $detached_count = 0;
            foreach (array_chunk($clientIds, 1000) as $chunk) {
                $detached_count += DB::table('client_employee')
                    ->whereIn('client_id', $chunk)
                    ->where('employee_id', $validated['employee_id'])
                    ->delete();
            }

            if ($detached_count > 0) {
                $nodeName = $this->nodeName($nodeId);
                $this->notifyEmployeeOfAssignmentChange((int) $validated['employee_id'], 'إزالة تعيين مجموعة', 'تم إزالة تعيينك عن عملاء «'.$nodeName.'» ('.$detached_count.' عميل)');

                OperationLog::record(
                    'group_unassigned',
                    self::LOG_MODULE,
                    null,
                    'إزالة إسناد الموظف #'.$validated['employee_id'].' عن عملاء «'.$nodeName
                        .'» — '.$detached_count.' عميل',
                    ['old' => ['tree_node' => $nodeId, 'employee_id' => (int) $validated['employee_id']]]
                );
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "تم إزالة تعيين الموظف من {$detached_count} عميل",
                    'detached_count' => $detached_count,
                ]);
            }

            return redirect()->back()->with('success', "تم إزالة تعيين الموظف من {$detached_count} عميل");
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'remove_employee_from_group');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ: '.$e->getMessage(),
                ], 400);
            }

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }
}
