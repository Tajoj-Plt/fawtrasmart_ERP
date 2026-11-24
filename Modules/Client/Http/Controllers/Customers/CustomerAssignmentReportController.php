<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تقريرُ العملاء المسنَدين للموظفين — «إذا بحثتُ بموظفٍ يعطيني عملاءه، ولأيّ
 * جهةٍ يتبعون في شجرة الفروع» — وهو أيضاً **شاشةُ العمل** على التعيينات القائمة.
 *
 * الإسنادُ من جدول `client_employee` (الموظفون المسؤولون عن العميل) — الجدولُ
 * الذي يكتبه ملفُّ العميل وشاشةُ التعيين الجماعي، ومنه تعدّ لوحةُ العملاء. وهو
 * نفسُه ما يطابقه فلتر «مسؤول المبيعات» (`sales_manager`) في كلّ تقارير العملاء.
 *
 * ما تعرضه الشاشة:
 *   • **التوزيعُ على الشجرة**: جهةٌ لكلّ سطر بمسارها من الجذر، وعددُ عملاء
 *     الموظف فيها (نشط/موقوف) — الجوابُ عن «لأيّ تابعٍ عملاؤه».
 *   • **العملاء**: كلُّ عميلٍ بحالته ووضعه وهدفه وجهته، ومن المسؤولون عنه.
 *
 * ══════════ الإجراءات (2026-09-28) ══════════
 *
 * لمن يملك صلاحيةَ التعيين: تحديدُ عملاءَ من الجدول — أو «كلّ نتائج الفلتر» —
 * ثمّ إضافةُ موظفٍ أو النقلُ إليه أو إزالتُه، وحذفُ موظفٍ من عميلٍ بعينه من
 * شارته. التنفيذُ في {@see \Modules\Client\Http\Controllers\ClientAssignmentController::applyToClients()}،
 * وهو يسأل {@see self::resolveClientIds()} عن العملاء — فلا يُسنَد عميلٌ
 * خارج ما يعرضه هذا التقريرُ للمستخدم نفسه.
 *
 * بلا موظفٍ مختار تعرض المسنَدين جميعاً، و«غير مسنَدين» خيارٌ صريح.
 */
class CustomerAssignmentReportController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;

    public const PAGE_SIZES = [50, 100, 200];

    /** صلاحيةُ الإجراءات — نفسُ حارس شاشة التعيين الجماعي */
    public const ASSIGN_PERMISSIONS = ['clients_assign_employees', 'client_follow_up_assign_clients_to_employees'];

    public function __construct()
    {
        // صلاحيةُ التقرير، أو صلاحيةُ التعيين (مسار `clients.bulk-assignment.current`)
        $this->middleware('permission:clients.reports.assignments|clients.reports.view|'.implode('|', self::ASSIGN_PERMISSIONS));
    }

    public function index(Request $request)
    {
        Branch::preloadCache();

        $mode = (string) $request->input('assignment', '');
        $query = $this->clientsQuery($request);

        // ── التوزيعُ على الشجرة — من العملاء كلِّهم لا من صفحة الجدول ──
        $activeIds = array_flip(\App\Models\Statuses::activeClientStatusIds());
        $stoppedIds = array_flip(\App\Models\Statuses::stoppedClientStatusIds());
        $byNode = [];
        foreach ((clone $query)->get(['clients.id', 'tree_node_id', 'branch_id', 'status_id']) as $client) {
            $node = $client->resolveTreeNode();
            $key = $node?->id ?? 0;
            $byNode[$key] ??= [
                'id' => $key,
                'name' => $node?->name ?? 'بلا جهة',
                'path' => $node?->lineagePathLabel() ?? 'بلا جهة',
                'type' => $node?->displayTypeLabel() ?? '',
                'total' => 0, 'active' => 0, 'stopped' => 0,
            ];
            $byNode[$key]['total']++;
            if (isset($activeIds[(int) $client->status_id])) {
                $byNode[$key]['active']++;
            } elseif (isset($stoppedIds[(int) $client->status_id])) {
                $byNode[$key]['stopped']++;
            }
        }
        // بالمسار: الجهاتُ الأخواتُ متجاورة، والأبُ قبل أبنائه
        uasort($byNode, fn ($a, $b) => strcmp($a['path'], $b['path']));

        $query->with([
            'status_client:id,name,color',
            'registrationStage:id,name,color',
            'customerTarget:id,name,color',
            'employees:id,first_name,middle_name,nickname',
        ])->orderBy('clients.trade_name');

        // التصديرُ والطباعةُ — كلُّ العملاء المطابقين لا صفحةُ الجدول
        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $query->get()->values()->map(function (Client $client, $i) {
                $node = $client->resolveTreeNode();

                return [
                    $i + 1, (string) $client->code, $this->clientName($client),
                    $client->status_client->name ?? 'بدون حالة', $client->registrationStage->name ?? 'بدون وضع',
                    $client->customerTarget->name ?? 'بلا هدف', $node?->lineagePathLabel() ?? '—',
                    $client->employees->map(fn ($e) => $e->full_name)->filter()->implode('، ') ?: '—',
                ];
            })->all()]);
        }

        $perPage = in_array((int) $request->input('per_page'), self::PAGE_SIZES, true)
            ? (int) $request->input('per_page')
            : self::PAGE_SIZES[0];
        $clients = $query->paginate($perPage)->withQueryString();

        $selectedEmployee = $request->filled('sales_manager')
            ? Employee::find((int) $request->input('sales_manager'))
            : null;

        $canAssign = hasPermission(self::ASSIGN_PERMISSIONS);

        return view('client::customers.CustomerReport.customer_assignments', [
            'clients' => $clients,
            'perPage' => $perPage,
            'pageSizes' => self::PAGE_SIZES,
            'byNode' => array_values($byNode),
            'totals' => [
                'clients' => array_sum(array_column($byNode, 'total')),
                'active' => array_sum(array_column($byNode, 'active')),
                'stopped' => array_sum(array_column($byNode, 'stopped')),
                'nodes' => count($byNode),
            ],
            'assignmentMode' => $mode,
            'selectedEmployee' => $selectedEmployee,
            'treeNodes' => $this->affiliationTreeNodes(),
            'salesEmployees' => $this->salesEmployeeOptions(),
            'registrationStages' => $this->registrationStageOptions(),
            'customerTargets' => $this->customerTargetOptions(),
            'categories' => CategoriesClient::all(['id', 'name']),
            'selectedClient' => $request->filled('customer')
                ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->input('customer'))
                : null,

            // ── الإجراءات ──
            'canAssign' => $canAssign,
            // من يُسنَد إليهم: «أقسام الموظفين المسموح تعيينهم للعملاء» — قائمةُ شاشة التعيين نفسُها
            'assignableEmployees' => $canAssign
                ? ClientSettingsHelper::scopeEmployeesToClientDepartments(Employee::query())->orderBy('first_name')->get()
                : collect(),
            'allowMultiple' => ClientSettingsHelper::clientEmployeesAllowMultiple(),
            'maxEmployees' => ClientSettingsHelper::clientEmployeesMax(),
        ]);
    }

    /**
     * العملاءُ الذين يقع عليهم إجراءٌ — مقصورين على ما يعرضه التقريرُ للمستخدم.
     *
     *   • `$onlyIds` معطاة ⇒ هؤلاء العملاء، بنطاق الرؤية وفرع الشريط وحدَهما
     *     (حُدّدوا من الجدول، ففلاترُه طُبّقت عليهم يومَ عُرضوا).
     *   • وإلّا ⇒ كلُّ نتائج `$filters` — «تطبيق على كلّ نتائج الفلتر».
     *
     * @param  array<string, mixed>  $filters  معاملاتُ رابط التقرير
     * @param  array<int, int>|null  $onlyIds
     * @return array<int, int>
     */
    public function resolveClientIds(array $filters, ?array $onlyIds): array
    {
        if ($onlyIds !== null) {
            $query = Client::query()->whereIn('clients.id', $onlyIds ?: [0]);
            $this->applyClientFilters($query, new Request);
        } else {
            $query = $this->clientsQuery(new Request($filters));
        }

        return $query->pluck('clients.id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** عملاءُ التقرير بفلاتره — المسنَدون، أو غيرُ المسنَدين إن طُلبوا */
    private function clientsQuery(Request $request): Builder
    {
        $assignedIds = DB::table('client_employee')
            ->join('employees', 'employees.id', '=', 'client_employee.employee_id')
            ->whereNull('employees.deleted_at')
            ->select('client_employee.client_id');

        // فلاترُ تقارير العملاء كلُّها — ومنها «مسؤول المبيعات» (`sales_manager`) على `client_employee`
        $query = Client::query();
        $this->applyClientFilters($query, $request);

        (string) $request->input('assignment', '') === 'unassigned'
            ? $query->whereNotIn('clients.id', $assignedIds)
            : $query->whereIn('clients.id', $assignedIds);

        return $query;
    }

    private function clientName(Client $client): string
    {
        $name = trim((string) $client->trade_name) ?: trim($client->first_name.' '.$client->last_name);

        return $name !== '' ? $name : '#'.$client->id;
    }
}
