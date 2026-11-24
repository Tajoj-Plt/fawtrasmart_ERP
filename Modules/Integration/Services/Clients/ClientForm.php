<?php

namespace Modules\Integration\Services\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Statuses;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * ما يحتاجه نموذج «إضافة/تعديل عميل» — منسوخٌ من `ClientController` في
 * الموقع بقواعده نفسها: شجرة «تابع لـ» بحدود فرع المستخدم ومجموعات مناطقه،
 * والموظفون من «أقسام الموظفين المسموح تعيينهم» فقط، والحقول الإلزامية من
 * «إعدادات وحدات العملاء».
 */
class ClientForm
{
    /**
     * عقد الشجرة التي يحقّ للمستخدم اختيارها — `branchesForClientTreeSelect()`.
     * التقاطعُ بين فرع الترويسة ومجموعات مناطق الموظف، والتقاطعُ الفارغ يعود
     * إلى مجموعاته وحدها كي لا تخلو القائمة.
     */
    public static function selectableNodes(User $user): Collection
    {
        $headerBranch = $user->branch ?? null;
        $headerScope = ($headerBranch && ! $headerBranch->is_main)
            ? collect([(int) $headerBranch->id])->merge($headerBranch->descendantBranchIds())->unique()->all()
            : null;

        $userScope = null;
        if ($user->isEmployee()) {
            $groups = $user->regionGroups;
            if ($groups->isNotEmpty()) {
                $ids = collect();
                foreach ($groups as $group) {
                    if (! $group) {
                        continue;
                    }
                    $ids->push((int) $group->id);
                    $ids = $ids->merge($group->descendantBranchIds());
                }
                $userScope = $ids->unique()->all();
            }
        }

        $scope = match (true) {
            $headerScope !== null && $userScope !== null => array_values(array_intersect($headerScope, $userScope)) ?: $userScope,
            $headerScope !== null => $headerScope,
            $userScope !== null => $userScope,
            default => null,
        };

        $query = Branch::withoutGlobalScopes();
        if ($scope !== null) {
            $query->whereIn('id', $scope ?: [0]);
        }

        return $query->orderBy('code')->orderBy('id')->get();
    }

    /** @return array<int, int> */
    public static function selectableNodeIds(User $user, ?Client $client = null): array
    {
        $ids = self::selectableNodes($user)->pluck('id')->map(fn ($id) => (int) $id)->all();

        // عميلٌ قائم يبقى على عقدته الحالية ولو خرجت من نطاق المعدِّل — نفس `update()`
        if ($client && ($current = $client->resolveTreeNode()?->id) && ! in_array((int) $current, $ids, true)) {
            $ids[] = (int) $current;
        }

        return $ids;
    }

    /** الشجرة مسطّحةً للتطبيق: كل عقدة بأبيها ونوعها، و`selectable` لما يُختار. */
    public static function treeNodes(User $user, ?int $keepId = null): array
    {
        $nodes = [];
        $put = function (Branch $node, bool $selectable) use (&$nodes) {
            $id = (int) $node->id;
            $type = $node->nodeTypeKey();
            $nodes[$id] = [
                'id' => $id,
                'name' => $node->name,
                'parent_id' => $node->parent_id ? (int) $node->parent_id : null,
                'selectable' => $selectable || ($nodes[$id]['selectable'] ?? false),
                'node_type' => $type,
                'type_label' => Branch::nodeTypeMeta($type)['label'],
            ];
        };

        foreach (self::selectableNodes($user) as $node) {
            $put($node, true);
        }

        if ($keepId && ! isset($nodes[$keepId]) && ($extra = Branch::withoutGlobalScopes()->find($keepId))) {
            foreach ($extra->lineageFromRoot() as $ancestor) {
                $put($ancestor, (int) $ancestor->id === $keepId);
            }
        }

        return array_values($nodes);
    }

    /** الموظفون المتاحون للإسناد — `employeesForClientAssignment()`. */
    public static function assignableEmployees(?Client $client = null): Collection
    {
        $employees = ClientSettingsHelper::scopeEmployeesToClientDepartments(Employee::query()->with('department'))->get();

        if ($client) {
            $missing = array_diff($client->employees->pluck('id')->all(), $employees->pluck('id')->all());
            if ($missing) {
                $employees = $employees->merge(Employee::with('department')->whereIn('id', $missing)->get())->unique('id')->values();
            }
        }

        return $employees;
    }

    /** قاعدة «الموظفون المسؤولون» — `employeeAssignmentRules()`. */
    public static function employeeRules(?Client $client = null): array
    {
        $allowedDepartments = ClientSettingsHelper::clientEmployeeDepartmentIds();
        $current = $client ? $client->employees->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        return [
            'employee_client_id' => 'nullable|array',
            'employee_client_id.*' => [
                'integer', 'distinct', 'exists:employees,id',
                function ($attribute, $value, $fail) use ($allowedDepartments, $current) {
                    if (! $allowedDepartments || in_array((int) $value, $current, true)) {
                        return;
                    }
                    if (! Employee::whereKey($value)->whereIn('department_id', $allowedDepartments)->exists()) {
                        $fail('أحد الموظفين المختارين خارج الأقسام المسموح تعيينها للعملاء في إعدادات وحدات العملاء.');
                    }
                },
            ],
        ];
    }

    public static function requiredFields(): array
    {
        return [
            'trade_name' => ClientSettingsHelper::clientRequireTradeName(),
            'phone' => ClientSettingsHelper::clientRequirePhone(),
            'mobile' => ClientSettingsHelper::clientRequireMobile(),
            'tax_number' => ClientSettingsHelper::clientRequireTaxNumber(),
            'email' => ClientSettingsHelper::clientRequireEmail(),
            'commercial_registration' => ClientSettingsHelper::clientRequireCommercialRegistration(),
            'category_id' => ClientSettingsHelper::clientRequireCategory(),
            'registration_stage_id' => ClientSettingsHelper::clientRequireRegistrationStage(),
            'location' => (bool) ClientSettingsHelper::isLocationRequired(),
            'group_id' => Schema::hasColumn('clients', 'group_id') && ClientSettingsHelper::isGroupRequired(),
            'attachments' => ClientSettingsHelper::isImageFieldEnabled(),
        ];
    }

    /** قوائم النموذج كلّها في ردٍّ واحد. */
    public static function options(User $user, ?Client $client = null): array
    {
        return [
            'branch_tree' => self::treeNodes($user, $client?->resolveTreeNode()?->id),
            'categories' => CategoriesClient::orderBy('name')->get(['id', 'name']),
            'statuses' => Statuses::assignable()->get(['id', 'name', 'color']),
            'registration_stages' => Statuses::where('section', 'registration_stage')->get(['id', 'name']),
            'can_assign_employees' => hasPermission('clients_assign_employees'),
            'employees' => hasPermission('clients_assign_employees')
                ? self::assignableEmployees($client)->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => trim($e->first_name.' '.$e->middle_name),
                    'department' => $e->department?->name,
                ])->values()
                : [],
            'client_type_scope' => ClientSettingsHelper::clientTypeScope(),
            'next_code' => $client ? null : peek_document_number('customer'),
            'required_fields' => self::requiredFields(),
            'max_upload_kb' => ClientSettingsHelper::maxUploadSizeKb(),
        ];
    }
}
