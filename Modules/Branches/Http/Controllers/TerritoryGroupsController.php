<?php

namespace Modules\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\GroupMergeLog;
use App\Models\Log as ModelsLog;
use App\Models\Neighborhood;
use App\Models\Region_groub;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TerritoryGroupsController extends Controller
{
    public function __construct()
    {
        /*
            حراسةٌ على المتحكّم بجانب حراسة المسار — لا بدلاً منها.

            وأخطرُ ما في هذا الملفّ ثلاث دوالّ تنقل **آلاف العملاء** بين
            كياناتٍ محاسبية في نداءٍ واحد: `merge_groups` و`bulk_merge`
            و`transfer_clients`. ولكلٍّ صلاحيتُها المستقلّة، ولا تُمنح جملةً
            مع «عرض المجموعات».

            و`revert_merge` مفردةٌ كذلك: التراجع عن دمجٍ يُعيد رسم الشجرة
            ويُرجع العملاء — فعلٌ لا يقلّ خطراً عن الدمج نفسه.
        */
        $this->middleware('permission.role:branches.groups.view', ['only' => ['merge_logs', 'getClientsByNode', 'merge_groups_page', 'merge_select_page', 'bulk_merge_page']]);
        $this->middleware('permission.role:branches.groups.merge', ['only' => ['merge_groups', 'merge_groups_bulk', 'bulk_merge']]);
        $this->middleware('permission.role:branches.groups.revert', ['only' => ['revert_merge']]);
        $this->middleware('permission.role:branches.groups.transfer_clients', ['only' => ['transfer_clients']]);
        $this->middleware('permission.role:branches.groups.delete', ['only' => ['destroy']]);
    }

    public function destroy($id)
    {
        $group = Region_groub::findOrFail($id);

        // حذف عقدة لها أبناء كان يترك الأبناء بـ parent_id يشير إلى سجل محذوف،
        // فتختفي فروع كاملة من الشجرة بلا أثر. نمنع الحذف ونطلب نقلهم أولاً.
        $childrenCount = Branch::withoutGlobalScopes()->where('parent_id', $group->id)->count();
        if ($childrenCount > 0) {
            return redirect()->route('branches.index')->with(
                'error',
                __('branches.messages.group_has_children', ['name' => $group->name, 'count' => $childrenCount])
            );
        }

        // نفس المنطق للأحياء/العملاء: region_id سيصبح يتيماً بعد الحذف.
        $neighborhoodsCount = Neighborhood::where('region_id', $group->id)->count();
        if ($neighborhoodsCount > 0) {
            return redirect()->route('branches.index')->with(
                'error',
                __('branches.messages.group_has_clients', ['name' => $group->name, 'count' => $neighborhoodsCount])
            );
        }

        $name = $group->name;
        $group->delete();

        ModelsLog::create([
            'type' => 'branch',
            'type_id' => $id,
            'type_log' => 'log',
            'description' => __('branches.log.group_deleted', ['name' => $name]),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('branches.index')->with('success', __('branches.messages.group_deleted'));
    }

    // دالة لضم مجموعة على مجموعة أخرى
    public function merge_groups(Request $request, $id)
    {
        /* لا قيدَ على النوع: العميلُ يتبع فرعاً أو منطقةً أو مجموعةً أو منفذاً،
           فنقلُه يجب أن يقع بين أيّ عقدتين. وكان `Region_groub` بنطاقه العام
           (`is_territory = true`) يمنع أن يكون المصدرُ أو الهدفُ فرعاً أو
           منفذاً — فيُردّ المستخدم بـ404 بلا سبب مفهوم. */
        $sourceGroup = Branch::withoutGlobalScopes()->findOrFail($id);

        $request->validate([
            // 'different:id' كانت قاعدة ميتة — الحقل `id` ليس جزءاً من الطلب أصلاً،
            // فكان بإمكان المستخدم النقل إلى العقدة نفسها. نقارن بالمعرّف الفعلي.
            'target_group_id' => [
                'required',
                'integer',
                Rule::notIn([(int) $id]),
                Rule::exists('branches', 'id'),
            ],
        ], [
            'target_group_id.not_in' => __('branches.messages.merge_into_self'),
        ], ['target_group_id' => __('branches.messages.target_group')]);

        $targetGroup = Branch::withoutGlobalScopes()->findOrFail($request->target_group_id);

        /* العمليةُ نقلُ عملاء أوّلاً وآخراً؛ وحذفُ العقدة المصدر بعد إفراغها
           خيارٌ صريحٌ يطلبه المستخدم، لا أثرٌ جانبيّ يفرضه اسمُ الزر. كان
           الحذفُ يقع دائماً، فمن أراد نقلَ عملاءَ من فرعٍ إلى آخر يفقد الفرع. */
        $deleteSource = $request->boolean('delete_source');

        // ضم مجموعة داخل شجرتها الخاصة يقطع الشجرة (الهدف يُحذف أبوه).
        if ($sourceGroup->descendantBranchIds()->contains((int) $targetGroup->id)) {
            return back()->with('error', __('branches.messages.target_inside_source'));
        }

        $childrenCount = Branch::withoutGlobalScopes()->where('parent_id', $sourceGroup->id)->count();
        if ($childrenCount > 0) {
            return back()->with(
                'error',
                __('branches.messages.source_has_children', ['name' => $sourceGroup->name, 'count' => $childrenCount])
            );
        }

        try {
            DB::transaction(function () use ($sourceGroup, $targetGroup, $deleteSource) {
                $moved = Neighborhood::where('region_id', $sourceGroup->id)->get();

                Neighborhood::where('region_id', $sourceGroup->id)
                    ->update(['region_id' => $targetGroup->id]);

                $this->syncClientsAffiliation($moved->pluck('id')->all(), $targetGroup);

                // بدون هذا السجل لا يمكن التراجع عن الضم من صفحة «سجل الضم».
                GroupMergeLog::create([
                    'source_group_id' => $sourceGroup->id,
                    'target_group_id' => $targetGroup->id,
                    'source_group_name' => $sourceGroup->name,
                    'target_group_name' => $targetGroup->name,
                    'moved_count' => $moved->count(),
                    'clients_backup' => $moved->toArray(),
                ]);

                if ($deleteSource) {
                    $sourceGroup->delete();
                }
            });
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.TerritoryGroups', 'merge_groups');

            return back()->with('error', __('branches.messages.merge_failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('branches.index')
            ->with('success', __('branches.messages.merge_done'));
    }

    public function merge_groups_page($id)
    {
        $sourceGroup = Branch::withoutGlobalScopes()->findOrFail($id);
        // كلُّ العقد صالحةٌ هدفاً — عدا العقدة نفسِها وما تحتها (نقلٌ إلى الداخل)
        $excluded = $sourceGroup->descendantBranchIds()->push($sourceGroup->id)->map(fn ($i) => (int) $i)->all();
        $targetGroups = Branch::withoutGlobalScopes()->whereNotIn('id', $excluded)->orderBy('name')->get();

        return view('branches::territory_groups.merge_groups', compact('sourceGroup', 'targetGroups'));
    }

    public function merge_select_page()
    {
        /* الشجرةُ كاملةً بلا تصفيةٍ بالنوع: المصدرُ والهدفُ أيُّ عقدة.
           و`$groups` و`$cascadeGroups` — قائمتا المجموعات وحدَها — حُذفتا:
           الأولى لم تكن تُقرأ في القالب أصلاً (`groupsData` متغيّرٌ ميت)،
           والثانية تكرارٌ لصفوفٍ هي أصلاً ضمن `branches`. */
        $cascadeBranches = $this->cascadeTree();

        $nodeTypeLabels = $this->nodeTypeLabels();

        return view('branches::territory_groups.merge_select', compact('cascadeBranches', 'nodeTypeLabels'));
    }

    public function merge_groups_bulk(Request $request)
    {
        $request->validate([
            'source_group_id' => 'required|string',
            'target_group_id' => 'required|string',
        ], [], [
            'source_group_id' => __('branches.messages.group_source'),
            'target_group_id' => __('branches.messages.target_group'),
        ]);

        $sourceGroupId = $this->nodeKeyToId($request->source_group_id);
        $targetGroupId = $this->nodeKeyToId($request->target_group_id);

        if (! $sourceGroupId || ! $targetGroupId) {
            return redirect()->back()->with('error', __('branches.messages.read_source_target_failed'));
        }

        if ($sourceGroupId === $targetGroupId) {
            return redirect()->back()->with('error', __('branches.messages.same_source_target'));
        }

        $sourceGroup = Branch::withoutGlobalScopes()->findOrFail($sourceGroupId);
        $targetGroup = Branch::withoutGlobalScopes()->findOrFail($targetGroupId);

        // Collect all descendant IDs of the source node recursively
        $allSourceIds = $this->collectBranchAndDescendantIds($sourceGroup->id);

        // الهدف داخل الشجرة المصدر يعني أننا سننقل العملاء إليه ثم نحذفه ضمن الشجرة نفسها.
        if (in_array($targetGroup->id, $allSourceIds, true)) {
            return redirect()->back()->with('error', __('branches.messages.target_inside_source_tree'));
        }

        $clientsBackup = [];

        try {
            $deleteSource = $request->boolean('delete_source');

            DB::transaction(function () use ($allSourceIds, $sourceGroup, $targetGroup, $deleteSource, &$clientsBackup) {
                $clientsBackup = Neighborhood::whereIn('region_id', $allSourceIds)->get()->toArray();

                Neighborhood::whereIn('region_id', $allSourceIds)
                    ->update(['region_id' => $targetGroup->id]);

                $this->syncClientsAffiliation(
                    collect($clientsBackup)->pluck('id')->filter()->all(),
                    $targetGroup
                );

                GroupMergeLog::create([
                    'source_group_id' => $sourceGroup->id,
                    'target_group_id' => $targetGroup->id,
                    'source_group_name' => $sourceGroup->name,
                    'target_group_name' => $targetGroup->name,
                    'moved_count' => count($clientsBackup),
                    'clients_backup' => $clientsBackup,
                ]);

                /* حذفُ عقد المصدر بعد إفراغها — خيارٌ صريح لا أثرٌ لازم.
                   ولا يُحذف إلّا ما ليس فرعاً: الفرعُ وحدةٌ لها حساباتُها
                   وفواتيرُها، وحذفُه ضمن «نقل عملاء» خسارةٌ لا يتوقّعها أحد.
                   وما بقي داخلَه يُعلَّق على الهدف كي لا يبقى بلا أب. */
                if ($deleteSource) {
                    $removableIds = Branch::withoutGlobalScopes()
                        ->whereIn('id', $allSourceIds)
                        ->where('is_territory', true)
                        ->pluck('id')
                        ->all();

                    Branch::withoutGlobalScopes()
                        ->whereIn('parent_id', $removableIds)
                        ->whereNotIn('id', $removableIds)
                        ->update(['parent_id' => $targetGroup->id]);

                    Branch::withoutGlobalScopes()->whereIn('id', $removableIds)->delete();
                }
            });
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.TerritoryGroups', 'merge_groups_bulk');

            return redirect()->back()->with('error', __('branches.messages.merge_failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('groups.merge_select_page')
            ->with('success', __('branches.messages.merge_done_detail', ['source' => $sourceGroup->name, 'target' => $targetGroup->name, 'count' => count($clientsBackup)]));
    }

    /**
     * شاشة ضم مجموعات متعددة.
     *
     * كانت تقرأ من `region_groubs` القديم بينما `bulk_merge()` تتحقّق من
     * `branches` — فكانت الشاشة تعرض صفوفاً من جدول والفعل يستهلك معرّفات
     * جدول آخر، ولم تنجُ إلا لأن المعرّفات تصادف تطابقها. الآن المصدر واحد:
     * شجرة الفروع نفسها التي تستعملها شاشة «ضم ونقل عملاء».
     */
    public function bulk_merge_page()
    {
        $cascadeBranches = $this->cascadeTree();

        // أعداد العملاء لكل عقدة — تُعرض في تسميات الخيارات
        // بموضع العميل لا بفرعه المحاسبيّ — وإلّا ظهرت كلُّ مجموعةٍ بصفر
        // وتكدّس عملاؤها على الفرع الذي فوقها (نفس علّة `linkCountsByBranch()`)
        $clientCounts = [];
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'branch_id')) {
            $nodeExpr = Schema::hasColumn('clients', 'tree_node_id')
                ? 'COALESCE(tree_node_id, branch_id)'
                : 'branch_id';

            $clientCounts = DB::table('clients')
                ->selectRaw($nodeExpr.' as node_id, COUNT(*) as c')
                ->whereRaw($nodeExpr.' is not null')
                ->groupBy(DB::raw($nodeExpr))
                ->pluck('c', 'node_id')
                ->map(fn ($c) => (int) $c)
                ->all();
        }

        $nodeTypeLabels = $this->nodeTypeLabels();

        return view('branches::territory_groups.bulk_merge', compact('cascadeBranches', 'clientCounts', 'nodeTypeLabels'));
    }

    public function bulk_merge(Request $request)
    {
        $request->validate([
            // بلا شرط `is_territory`: النقلُ يقع بين أيّ عقدتين في الهيكل
            'source_groups' => 'required|array|min:1',
            'source_groups.*' => [Rule::exists('branches', 'id')],
            'target_group_id' => ['required', Rule::exists('branches', 'id')],
        ], [], [
            'source_groups' => __('branches.messages.group_sources'),
            'target_group_id' => __('branches.messages.target_group'),
        ]);

        $sourceGroupIds = collect($request->source_groups)->map(fn ($v) => (int) $v)->unique()->values();
        $targetGroupId = (int) $request->target_group_id;

        if ($sourceGroupIds->contains($targetGroupId)) {
            return redirect()->back()->with('error', __('branches.messages.target_in_sources'));
        }

        $targetGroup = Branch::withoutGlobalScopes()->findOrFail($targetGroupId);

        // الهدف تحت إحدى المجموعات المصدر = حذف أبيه أثناء العملية.
        foreach ($sourceGroupIds as $sid) {
            if (in_array($targetGroupId, $this->collectBranchAndDescendantIds($sid), true)) {
                return redirect()->back()->with('error', __('branches.messages.target_inside_one_source'));
            }
        }

        $totalMovedCount = 0;

        try {
            DB::transaction(function () use ($sourceGroupIds, $targetGroup, &$totalMovedCount) {
                foreach ($sourceGroupIds as $sourceGroupId) {
                    $sourceGroup = Branch::withoutGlobalScopes()->findOrFail($sourceGroupId);

                    $clientsBackup = Neighborhood::where('region_id', $sourceGroup->id)->get()->toArray();
                    $movedCount = count($clientsBackup);

                    if ($movedCount > 0) {
                        Neighborhood::where('region_id', $sourceGroup->id)
                            ->update(['region_id' => $targetGroup->id]);

                        $this->syncClientsAffiliation(
                            collect($clientsBackup)->pluck('id')->filter()->all(),
                            $targetGroup
                        );

                        GroupMergeLog::create([
                            'source_group_id' => $sourceGroup->id,
                            'target_group_id' => $targetGroup->id,
                            'source_group_name' => $sourceGroup->name,
                            'target_group_name' => $targetGroup->name,
                            'moved_count' => $movedCount,
                            'clients_backup' => $clientsBackup,
                        ]);

                        $totalMovedCount += $movedCount;
                    }

                    // كان الحذف يتم دون النظر إلى الأبناء، فتختفي فروع كاملة من الشجرة.
                    // نعلّق الأبناء على الهدف قبل حذف المجموعة المصدر.
                    Branch::withoutGlobalScopes()
                        ->where('parent_id', $sourceGroup->id)
                        ->update(['parent_id' => $targetGroup->id]);

                    $sourceGroup->delete();
                }
            });
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.TerritoryGroups', 'bulk_merge');

            return redirect()->back()->with('error', __('branches.messages.bulk_merge_failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('groups.merge_logs')
            ->with('success', __('branches.messages.bulk_merge_done', ['count' => $sourceGroupIds->count(), 'target' => $targetGroup->name, 'clients' => $totalMovedCount]));
    }

    public function merge_logs()
    {
        $logs = GroupMergeLog::orderBy('created_at', 'desc')->paginate(15);

        return view('branches::territory_groups.merge_logs', compact('logs'));
    }

    public function revert_merge(GroupMergeLog $log)
    {
        if ($log->is_reversed) {
            return redirect()->route('groups.merge_logs')
                ->with('error', __('branches.messages.already_reverted'));
        }

        // كان يُستخدم Region_groub هنا، ونطاقه العام يفرض is_territory = true —
        // فكل ضمٍّ هدفه فرع محاسبي كان يعطي 404 بدل التراجع.
        $targetGroup = Branch::withoutGlobalScopes()->find($log->target_group_id);
        if (! $targetGroup) {
            return redirect()->route('groups.merge_logs')
                ->with('error', __('branches.messages.target_gone'));
        }

        try {
            DB::transaction(function () use ($log, $targetGroup) {
                $sourceGroup = Region_groub::create([
                    'name' => $log->source_group_name,
                    'parent_id' => $targetGroup->parent_id,
                    'territory_office_id' => $targetGroup->is_territory
                        ? $targetGroup->territory_office_id
                        : $targetGroup->id,
                    'directions_id' => $targetGroup->directions_id,
                ]);

                $this->restoreFromBackup($log->clients_backup, $targetGroup, $sourceGroup);

                $log->update([
                    'is_reversed' => true,
                    'reversed_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.TerritoryGroups', 'revert_merge');

            return redirect()->route('groups.merge_logs')
                ->with('error', __('branches.messages.revert_failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('groups.merge_logs')
            ->with('success', __('branches.messages.revert_done', ['name' => $log->source_group_name]));
    }

    // =============================================
    // نقل عملاء محددين من مجموعة إلى أخرى
    // =============================================

    /**
     * نقلُ تبعيّة العملاء الذين تحرّكت أحياؤهم — للضمّ وللتراجع عنه.
     *
     * أدواتُ الضم كانت تحرّك `neighborhoods.region_id` وحدَه وتبقي تبعيّةَ
     * العميل حيث كانت. وكان ذلك يمرّ سابقاً لأن الشاشةَ تشتقّ موضعَ العميل من
     * حيّه؛ أمّا الآن فالتبعيّةُ عمودٌ صريح، فحركةُ الحيّ وحدَها بلا أثر.
     *
     * @param  array<int, int>  $neighborhoodIds  الأحياء التي تحرّكت
     * @return int عددُ العملاء الذين تغيّرت تبعيّتهم
     */
    private function syncClientsAffiliation(array $neighborhoodIds, Branch $target): int
    {
        if ($neighborhoodIds === [] || ! Schema::hasTable('clients')) {
            return 0;
        }

        $clientIds = Neighborhood::whereIn('id', $neighborhoodIds)
            ->whereNotNull('client_id')
            ->pluck('client_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $this->moveClientsToNode($clientIds, $target);
    }

    /**
     * نقلُ عملاءَ محدَّدين من عقدةٍ إلى أخرى — أيّاً كان نوعُ العقدتين.
     *
     * كان النقلُ يعمل على معرّفات **الأحياء** (`neighborhoods.id`) لا العملاء،
     * وكان يحرّك `region_id` وحدَه. فعميلٌ بلا حيٍّ لا يُنقل، وتبعيّتُه لا
     * تتغيّر أصلاً بعد أن صارت عموداً صريحاً في `clients`.
     *
     * صار يعمل على العملاء أنفسِهم: تُكتب تبعيّتُهم الجديدة، ويُشتقّ فرعُهم
     * المحاسبيّ من العقدة الهدف، ويتبعهم حيُّهم إن وُجد.
     */
    public function transfer_clients(Request $request)
    {
        $request->validate([
            'source_group_id' => ['required'],
            'target_group_id' => ['required'],
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
        ], [], [
            'source_group_id' => __('branches.messages.group_source'),
            'target_group_id' => __('branches.messages.target_group'),
            'client_ids' => __('branches.messages.selected_clients'),
        ]);

        $targetGroupId = $this->nodeKeyToId($request->target_group_id);
        if (! $targetGroupId) {
            return redirect()->back()->with('error', __('branches.messages.read_target_failed'));
        }

        $targetGroup = Branch::withoutGlobalScopes()->findOrFail($targetGroupId);

        $clients = Client::whereIn('id', $request->client_ids)->get();
        if ($clients->isEmpty()) {
            return redirect()->back()->with('error', __('branches.messages.selected_clients_gone'));
        }

        // نسخةٌ احتياطية بموضع كل عميل قبل النقل — بها وحدها يصحّ التراجع
        $backup = $clients->map(fn (Client $c) => [
            'client_id' => (int) $c->id,
            'tree_node_id' => $c->tree_node_id ? (int) $c->tree_node_id : null,
            'branch_id' => $c->branch_id ? (int) $c->branch_id : null,
        ])->all();

        $movedCount = 0;

        try {
            DB::transaction(function () use ($clients, $targetGroup, &$movedCount) {
                $movedCount = $this->moveClientsToNode($clients->pluck('id')->all(), $targetGroup);
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Branches.TerritoryGroups', 'transfer_clients');

            return redirect()->back()->with('error', __('branches.messages.transfer_failed', ['error' => $e->getMessage()]));
        }

        $sourceName = __('branches.messages.tree_node');
        $sourceId = $this->nodeKeyToId($request->source_group_id);
        if ($sourceId) {
            $sourceNode = Branch::withoutGlobalScopes()->find($sourceId);
            if ($sourceNode) {
                $sourceName = $sourceNode->name;
            } else {
                $sourceId = null;
            }
        }

        GroupMergeLog::create([
            'source_group_id' => $sourceId,
            'target_group_id' => $targetGroup->id,
            'source_group_name' => $sourceName,
            'target_group_name' => $targetGroup->name,
            'moved_count' => $movedCount,
            'clients_backup' => $backup,
        ]);

        return redirect()->route('groups.merge_select_page')
            ->with('success', __('branches.messages.clients_moved', ['count' => $movedCount, 'target' => $targetGroup->name]));
    }

    /**
     * إعادةُ العملاء إلى مواضعهم من النسخة الاحتياطية للعملية.
     *
     * للنسخة شكلان: القديمُ صفوفُ أحياء (`id` و`region_id`) كتبتْها أدواتُ
     * الضمّ، والجديدُ مواضعُ عملاء (`client_id` و`tree_node_id`) تكتبها شاشةُ
     * نقل العملاء. وسجلّاتٌ قديمةٌ باقيةٌ في القاعدة، فيجب أن يُقرأ الشكلان
     * معاً وإلّا صار زرُّ التراجع في السجلّ لا يفعل شيئاً.
     *
     * ولا يُعاد إلّا من بقي على العقدة الهدف: من نُقل يدوياً بعدها إلى موضعٍ
     * ثالث يبقى حيث وضعه صاحبُه.
     */
    private function restoreFromBackup(mixed $backup, Branch $target, Branch $source): int
    {
        $rows = collect($backup ?: []);
        if ($rows->isEmpty()) {
            return 0;
        }

        // الشكل الجديد: مواضعُ عملاء
        $clientIds = $rows->pluck('client_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($clientIds !== []) {
            $stillOnTarget = Client::whereIn('id', $clientIds)
                ->where(function ($w) use ($target) {
                    $w->where('tree_node_id', $target->id)
                        ->orWhere(function ($legacy) use ($target) {
                            $legacy->whereNull('tree_node_id')->where('branch_id', $target->id);
                        });
                })
                ->pluck('id')
                ->all();

            return $this->moveClientsToNode($stillOnTarget, $source);
        }

        // الشكل القديم: صفوفُ أحياء
        $neighborhoodIds = $rows->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($neighborhoodIds === []) {
            return 0;
        }

        $stillOnTarget = Neighborhood::whereIn('id', $neighborhoodIds)
            ->where('region_id', $target->id)
            ->pluck('id')
            ->all();

        if ($stillOnTarget === []) {
            return 0;
        }

        Neighborhood::whereIn('id', $stillOnTarget)->update(['region_id' => $source->id]);

        return $this->syncClientsAffiliation($stillOnTarget, $source);
    }

    /**
     * كتابةُ تبعيّةٍ جديدة لمجموعةٍ من العملاء — الموضعُ الوحيد الذي ينقل عميلاً.
     *
     * @param  array<int, int>  $clientIds
     * @return int عددُ من نُقل فعلاً
     */
    private function moveClientsToNode(array $clientIds, Branch $target): int
    {
        if ($clientIds === []) {
            return 0;
        }

        $payload = ['branch_id' => $target->resolveAccountingBranchId() ?? $target->id];
        if (Schema::hasColumn('clients', 'tree_node_id')) {
            $payload['tree_node_id'] = $target->id;
        }

        $moved = DB::table('clients')->whereIn('id', $clientIds)->update($payload);

        // الحيُّ يتبع صاحبَه: تركُه على العقدة القديمة يترك الشاشات القديمة
        // التي تقرأ `neighborhoods.region_id` تخالف تبعيّة العميل
        Neighborhood::whereIn('client_id', $clientIds)->update(['region_id' => $target->id]);

        return (int) $moved;
    }

    /**
     * عملاءُ عقدةٍ وما تحتها — للائحة الاختيار في شاشة نقل العملاء.
     *
     * كانت القراءة عبر جدول `neighborhoods` وحدَه، فعميلٌ نُسب إلى فرعٍ أو
     * منطقةٍ من شاشة العميل بلا أن يُسجَّل له حيٌّ **لا يظهر في الشاشة أصلاً**
     * فلا سبيل إلى نقله. صارت القراءة من تبعيّة العميل نفسِها
     * (`clients.tree_node_id`)، والحيُّ بيانٌ إضافيّ لا شرطٌ للظهور.
     *
     * ولا قيدَ على نوع العقدة: فرعاً كانت أو منطقةً أو مجموعةً أو منفذاً.
     */
    public function getClientsByNode($nodeKey)
    {
        $id = $this->nodeKeyToId($nodeKey);
        if (! $id) {
            return response()->json([]);
        }

        $nodeIds = $this->collectBranchAndDescendantIds($id);

        $query = Client::query()
            ->select('id', 'code', 'trade_name', 'first_name', 'last_name', 'tree_node_id', 'branch_id');

        if (Schema::hasColumn('clients', 'tree_node_id')) {
            $query->where(function ($w) use ($nodeIds) {
                $w->whereIn('tree_node_id', $nodeIds)
                    ->orWhere(function ($legacy) use ($nodeIds) {
                        $legacy->whereNull('tree_node_id')->whereIn('branch_id', $nodeIds);
                    });
            });
        } else {
            $query->whereIn('branch_id', $nodeIds);
        }

        Branch::preloadCache();

        $clients = $query->orderBy('trade_name')->get()->map(function (Client $client) {
            $node = $client->resolveTreeNode();

            return [
                'id' => (int) $client->id,
                'code' => $client->code,
                'trade_name' => $client->display_name,
                // موضعُه الحاليّ: من ينقل من عقدةٍ أب يحتاج أن يعرف أيَّ ابنٍ
                // يقف عليه كلُّ عميل قبل أن يحرّكه
                'node_name' => $node?->name,
                'node_type_label' => $node?->displayTypeLabel(),
            ];
        })->values();

        return response()->json($clients);
    }

    /**
     * شجرةُ الاختيار لشاشتَي النقل والضم — وكلُّ عقدةٍ معها `display_type`:
     * نوعُ **العرض** الذي يصحّح ما خُزِّن «مجموعة» وهو منطقةٌ أو منفذ
     * (انظر `Branch::displayNodeType()`). `node_type` المخزَّن يبقى كما هو.
     *
     * @return Collection<int, Branch>
     */
    private function cascadeTree()
    {
        $nodes = Branch::withoutGlobalScopes()
            ->select('id', 'name', 'parent_id', 'is_territory', 'node_type')
            ->orderBy('name')
            ->get();

        $children = $nodes->groupBy(fn (Branch $b) => (int) ($b->parent_id ?? 0));

        return $nodes->each(fn (Branch $b) => $b->setAttribute(
            'display_type',
            Branch::displayNodeType($b, $children->get((int) $b->id, collect()))
        ));
    }

    /**
     * تسميةُ كل نوع عقدة — تُمرَّر إلى الشاشة كي تكتب «منطقة» منطقةً و«منفذ»
     * منفذاً. كانت الشاشةُ تلصق «مجموعة» على كلِّ ما ليس فرعاً (`is_territory`)
     * فيقرأ المستخدم «منطقة الرياض — مجموعة» ويظنّ الاختيار مقصوراً عليها.
     *
     * @return array<string, string>
     */
    private function nodeTypeLabels(): array
    {
        $labels = [];
        foreach (['branch', 'region', 'group', 'outlet'] as $type) {
            $labels[$type] = Branch::nodeTypeMeta($type)['label'];
        }

        return $labels;
    }

    private function nodeKeyToId(mixed $nodeKey): ?int
    {
        $key = trim((string) $nodeKey);
        if ($key === '') {
            return null;
        }
        if (str_starts_with($key, 'b_') || str_starts_with($key, 'g_')) {
            $key = substr($key, 2);
        }

        return ctype_digit($key) ? (int) $key : null;
    }

    private function collectBranchAndDescendantIds($branchId)
    {
        $ids = [$branchId];
        $queue = [$branchId];
        while (! empty($queue)) {
            $children = Branch::withoutGlobalScopes()->whereIn('parent_id', $queue)->pluck('id')->all();
            if (empty($children)) {
                break;
            }
            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
