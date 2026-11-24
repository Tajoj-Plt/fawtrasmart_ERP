<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;

/**
 * الفروع (عقد الشجرة) التي يحقّ للمستخدم اختيارها — نفس منسدلة الفروع في
 * الموقع ({@see DataScope::branchOptions()}): من لا قيد عليه يرى الشجرة
 * كلّها، ومن له نطاق فرعٍ/منطقة يرى شجرته فقط. تُستعمل لفلتر `branch_id`
 * في قوائم العملاء والخريطة.
 *
 * `current_branch_id` هو فرع المستخدم الحالي (المختار في ترويسة الموقع) —
 * وهو ما تُعرض عليه البيانات حين لا يُرسَل فلتر.
 */
class BranchController extends Controller
{
    use RespondsJson;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->branch_id ? (int) $user->branch_id : null;

        $query = Branch::query()->orderByDesc('is_main')->orderBy('code')->orderBy('id');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.addcslashes((string) $request->input('search'), '%_\\').'%');
        }

        $branches = DataScope::branchOptions($query, ['id', 'parent_id', 'name', 'code', 'node_type', 'is_main', 'is_territory', 'status'])
            ->map(fn (Branch $b) => [
                'id' => (int) $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'parent_id' => $b->parent_id ? (int) $b->parent_id : null,
                'node_type' => $b->nodeTypeKey(),
                'type_label' => Branch::nodeTypeMeta($b->nodeTypeKey())['label'],
                'is_main' => (bool) $b->is_main,
                'is_current' => (int) $b->id === $current,
            ])->values();

        return $this->ok([
            'current_branch_id' => $current,
            'restricted' => DataScope::selectableBranchIds() !== null,
            'branches' => $branches,
        ]);
    }
}
