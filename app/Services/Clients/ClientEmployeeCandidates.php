<?php

namespace App\Services\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Neighborhood;
use App\Models\User;
use App\Support\AffiliationTree;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * من يجوز اختيارُه موظفاً لعميل — نوافذُ إدارة علاقات العملاء تبني قوائمها
 * منه، و`ItineraryController` يفرضه عند الحفظ.
 *
 * شرطان معاً:
 * 1. **القسم**: من «أقسام الموظفين المسموح تعيينهم للعملاء» في إعدادات وحدات
 *    العملاء (`ClientSettingsHelper::clientEmployeeDepartmentIds`، فراغُها = كل الأقسام).
 * 2. **الصلة بالعميل**: مُسنَدٌ له صراحةً في `client_employee`، أو تابعٌ لعقدته —
 *    المنطقة أو الفرع أو المجموعة أو المنفذ الذي يتبعه العميل وما تحتها.
 *
 * عقدةُ العميل `tree_node_id` (التبعيّة التي يختارها المستخدم)، ثمّ `branch_id`
 * وحيُّ `neighborhoods` لعملاءٍ سابقين للعمود — نفس ترتيب `AffiliationTree`.
 * وتبعيّةُ الموظف من ملفّه (`employees.branch_id`)، و`users.branch_id` لمن لا
 * فرع في ملفّه فقط، فالأخير يكتبه مبدّلُ الفرع في الرأس. وعضويةُ المناطق
 * (`employee_group`) تبعيّةٌ أيضاً.
 *
 * كلُّ المعرّفات المُعادة معرّفاتُ `users` — بها يُكتب خطُّ السير، بينما
 * `client_employee.employee_id` معرّفُ صفٍّ في `employees`.
 */
class ClientEmployeeCandidates
{
    /** @var array<int, array<int, true>> معرّفاتُ المستخدمين المؤهَّلين لكل عميل */
    private array $eligibleByClient = [];

    /**
     * المؤهَّلون لعميلٍ أو أكثر، مفصولين: المُسنَدون أوّلاً ثمّ التابعون للعقدة.
     *
     * @param  array<int, int|string>  $clientIds
     * @return array{assigned: Collection<int, User>, node: Collection<int, User>}
     */
    public function forClients(array $clientIds): array
    {
        $clientIds = array_values(array_unique(array_filter(array_map('intval', $clientIds))));

        if ($clientIds === []) {
            return ['assigned' => collect(), 'node' => collect()];
        }

        $assignedEmployeeIds = DB::table('client_employee')
            ->whereIn('client_id', $clientIds)
            ->pluck('employee_id')
            ->unique()
            ->all();

        $assigned = $assignedEmployeeIds
            ? $this->eligibleUsers()->whereIn('employee_id', $assignedEmployeeIds)->orderBy('name')->get(['id', 'name'])
            : collect();

        $nodeIds = $this->nodeIdsFor($clientIds);

        $node = $nodeIds
            ? $this->constrainToNodes($this->eligibleUsers(), $nodeIds)
                ->whereNotIn('id', $assigned->pluck('id')->all() ?: [0])
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return ['assigned' => $assigned, 'node' => $node];
    }

    /**
     * المؤهَّلون لعقدةٍ بلا عميلٍ بعينه: من القسم، وتابعون لها أو لما تحتها.
     *
     * @return Collection<int, User>
     */
    public function forNode(int $nodeId): Collection
    {
        return $this->constrainToNodes($this->eligibleUsers(), AffiliationTree::descendantIds($nodeId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** أيجوز اختيارُ هذا المستخدم لهذا العميل؟ — يُحسب لكل عميلٍ مرّةً في الطلب. */
    public function isEligible(int $userId, int $clientId): bool
    {
        if (! isset($this->eligibleByClient[$clientId])) {
            $sets = $this->forClients([$clientId]);

            $this->eligibleByClient[$clientId] = $sets['assigned']->pluck('id')
                ->merge($sets['node']->pluck('id'))
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        }

        return isset($this->eligibleByClient[$clientId][$userId]);
    }

    /** مستخدمون لهم ملفُّ موظف، ومن أقسام موظفي العملاء إن حُدِّدت. */
    private function eligibleUsers()
    {
        $query = User::query()->whereNotNull('employee_id');

        if (ClientSettingsHelper::clientEmployeeDepartmentIds() !== []) {
            $query->whereIn(
                'employee_id',
                ClientSettingsHelper::scopeEmployeesToClientDepartments(Employee::query())->select('id')
            );
        }

        return $query;
    }

    /**
     * @param  array<int, int>  $nodeIds
     */
    private function constrainToNodes($query, array $nodeIds)
    {
        return $query->where(fn ($w) => $w
            ->whereIn('employee_id', Employee::whereIn('branch_id', $nodeIds)->select('id'))
            ->orWhere(fn ($u) => $u
                ->whereIn('branch_id', $nodeIds)
                ->whereIn('employee_id', Employee::whereNull('branch_id')->select('id')))
            ->orWhereHas('regionGroups', fn ($g) => $g->whereIn('branches.id', $nodeIds)));
    }

    /**
     * عُقدُ العملاء وما تحت كلٍّ منها.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, int>
     */
    private function nodeIdsFor(array $clientIds): array
    {
        $clients = Client::withoutGlobalScopes()
            ->whereIn('id', $clientIds)
            ->get(['id', 'tree_node_id', 'branch_id']);

        $roots = [];
        $legacyIds = [];

        foreach ($clients as $client) {
            if ($client->tree_node_id) {
                $roots[] = (int) $client->tree_node_id;

                continue;
            }

            $legacyIds[] = (int) $client->id;
            if ($client->branch_id) {
                $roots[] = (int) $client->branch_id;
            }
        }

        if ($legacyIds !== []) {
            $roots = array_merge($roots, Neighborhood::whereIn('client_id', $legacyIds)
                ->whereNotNull('region_id')
                ->pluck('region_id')
                ->map(fn ($id) => (int) $id)
                ->all());
        }

        $nodeIds = [];
        foreach (array_unique(array_filter($roots)) as $root) {
            $nodeIds = array_merge($nodeIds, AffiliationTree::descendantIds($root));
        }

        return array_values(array_unique($nodeIds));
    }
}
