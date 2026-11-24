<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Http\Request;

/**
 * «تابع لـ» — الجهةُ التي يتبع لها العميل في هيكل المنشأة.
 *
 * ══════════ لماذا صنفٌ واحدٌ لا نسخةٌ في كلّ وحدة ══════════
 *
 * كان لهذا السؤالِ الواحدِ تعريفانِ متوازيان: واحدٌ في تقارير العملاء
 * (`FiltersCustomerReports`) وآخرُ في تقارير المبيعات (`SalesReportTrait`)،
 * ولكلٍّ منهما مُنتقيه في الشاشة. واختلفا فيما لا يجوز أن يختلفا فيه:
 *
 *   • **القيمة**: تقارير المبيعات ترسل `b_12` — بادئةٌ من حين ظُنّ أنّ
 *     الفروعَ والمجموعات جدولان، وهما جدولٌ واحد (`branches`).
 *   • **المطابقة**: تقارير المبيعات تطابق `clients.branch_id`، وهو الفرعُ
 *     **المحاسبيّ** المشتقُّ آلياً؛ فعميلُ منطقةٍ يُنسب إلى الفرع الذي
 *     فوقها. اختبارُ منطقة الرياض: ٦٤٩ عميلاً بـ`branch_id` و٦٥٧ بالتبعيّة
 *     الحقيقية — ثمانيةٌ يسقطون من التقرير ومن قائمة العملاء معاً.
 *   • **التسمية**: حقلٌ يُسمّى بنوع ما فوقه («تابع لفرع» وخياراتُه مجموعات).
 *
 * صار التعريفُ هنا وحدَه، والمُنتقي `reports.partials.affiliation-tree`
 * وحدَه، تشترك فيهما الوحدتان.
 */
final class AffiliationTree
{
    /**
     * عقدُ الهيكل كلُّها، كلٌّ بنوعه وتسميته.
     *
     * تُمرَّر إلى المُنتقي فيبني منها مستوياتِه، ويُسمّي كلَّ حقلٍ بنوع ما
     * يعرضه هو — لا بنوع ما فوقه.
     *
     * @return array<int, array{id:int, name:string, parent_id:?int, node_type:string, type_label:string}>
     */
    public static function nodes(): array
    {
        $all = Branch::withoutGlobalScopes()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'node_type', 'is_territory']);

        /* أبناءُ كلّ عقدةٍ في اليد — يحتاجهم `displayNodeType()` ليعرف
           الجذرَ الذي تحته فروعٌ فيسمّيه «منطقة». */
        $childrenOf = $all->groupBy('parent_id');

        return $all
            ->map(function (Branch $node) use ($childrenOf) {
                $type = $node->nodeTypeKey();

                /* التسميةُ من نوع **العرض** لا المخزَّن: المناطقُ والمنافذُ
                   القديمة مخزَّنةٌ `group`، فكانت الشجرةُ تقول «منطقة جيزان —
                   مجموعة». `node_type` يبقى المخزَّنَ كما هو — للمنطق لا للنص. */
                $shown = Branch::displayNodeType($node, $childrenOf->get($node->id, collect()));

                return [
                    'id' => (int) $node->id,
                    'name' => $node->name,
                    'parent_id' => $node->parent_id ? (int) $node->parent_id : null,
                    'node_type' => $type,
                    'type_label' => Branch::nodeTypeMeta($shown)['label'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * خياراتُ قائمة «تابع لـ» الواحدة — كلُّ خيارٍ بمساره من الأعلى ونوعِه.
     *
     * هي قائمةُ فلتر فهرس العملاء بعينها، وتستعملها شاشةُ التعيين الجماعي
     * كي لا تختلفا في الترتيب ولا في التسمية — كانت الثانيةُ قوائمَ متدرّجة
     * بلا نوعٍ لأيّ عقدة، تقول «المستوى ٣» ولا تقول أهو فرعٌ أم منفذ.
     *
     * @param  \Illuminate\Support\Collection<int, Branch>  $nodes  العقدُ المتاحة للمستخدم
     * @return \Illuminate\Support\Collection<int, array{id:int, label:string, node_type:string, depth:int, sort_key:string}>
     */
    public static function pathOptions($nodes)
    {
        $byId = $nodes->keyBy('id');

        /* أبناءُ كلّ عقدة من الجدول كلّه لا من القائمة المعروضة — يحتاجهم
           `displayNodeType()` ليعرف الجذرَ الذي تحته فروعٌ فيسمّيه «منطقة». */
        $childrenOf = Branch::withoutGlobalScopes()->get(['id', 'parent_id', 'node_type'])->groupBy('parent_id');

        return $nodes->map(function (Branch $node) use ($byId, $childrenOf) {
            $labels = collect([$node->name]);
            $parentId = $node->parent_id;
            $safety = 0;
            while ($parentId && $safety < 20) {
                $parent = $byId->get($parentId);
                if (! $parent) {
                    break;
                }
                $labels->prepend($parent->name);
                $parentId = $parent->parent_id;
                $safety++;
            }

            /* النوعُ **المعروض** لا المخزَّن: المناطقُ والمنافذ القديمة مخزَّنةٌ
               `group`، فكانت القائمة تقول «منطقة الرياض — مجموعة». */
            $shown = Branch::displayNodeType($node, $childrenOf->get($node->id, collect()));
            $typeLabel = Branch::nodeTypeMeta($shown)['label'];

            return [
                'id' => (int) $node->id,
                'name' => $node->name,
                'label' => $labels->implode(' ← ').' — '.$typeLabel,
                'node_type' => $node->nodeTypeKey(),
                'display_type' => $shown,
                'type_label' => $typeLabel,
                'depth' => $labels->count() - 1,
                'sort_key' => $labels->implode("\x01"),
            ];
        })->sortBy('sort_key')->values();
    }

    /**
     * معرّفُ العقدة المطلوبة في الطلب — من الاسم الجديد أو أيٍّ من القديمة.
     *
     * `tree_node` هو المعتمَد. وتبقى `cascade_node` (بصيغة `b_12`/`g_7`)
     * و`region_group` و`branch` مقروءةً كي لا ينكسر رابطٌ محفوظٌ ولا
     * مرجعيّةٌ مرسَلة، ولا شاشةٌ لم تُنقَل بعد إلى المُنتقي المشترك.
     *
     * و`$keys` لمن لا يريد القائمةَ كلَّها: تقاريرُ المبيعات تقرأ `branch_id`
     * بقاعدتها هي — يُهمَل عند مستخدمٍ خارج الفرع الرئيسي فلا يوسّع نطاقَه
     * برابطٍ مصنوعٍ بيد — فتستثنيه من هنا وتفصل فيه بنفسها.
     */
    public static function requestedNodeId(Request $request, ?array $keys = null): ?int
    {
        foreach ($keys ?? ['tree_node', 'cascade_node', 'region_group', 'region_group_id', 'branch', 'branch_id'] as $key) {
            $value = $request->input($key);

            if ($value === null || $value === '' || $value === '0' || $value === 'all') {
                continue;
            }

            $id = (int) preg_replace('/^[bg]_/', '', (string) $value);

            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    /** أطُلبت المطابقةُ التامّة — هذه الجهة وحدَها دون ما تحتها؟ */
    public static function exactRequested(Request $request): bool
    {
        return $request->boolean('exact_node');
    }

    /**
     * قصرُ استعلامِ عملاءَ على عقدةٍ وما تحتها — **الموضعُ الوحيد** لهذه
     * المطابقة في النظام، تستدعيه `Client::scopeInTreeNode()`.
     *
     * ══════════ لماذا الأعمدةُ القديمة للفارغين وحدَهم ══════════
     *
     * `clients.tree_node_id` هو التبعيّةُ التي اختارها المستخدم فعلاً.
     * و`branch_id` الفرعُ المحاسبيُّ المشتقُّ آلياً — يُقرأ **فقط** لمن لا
     * تبعيّةَ صريحةً له (صفّان من ٣٨٥٢ هنا)، ولو قُرئ للجميع لسرّب في
     * المطابقة التامّة عميلَ المنفذ إلى الفرع الذي فوقه ولم يُنسب إليه.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Client>  $query
     * @param  array<int, int>  $nodeIds  العقدةُ وما تحتها، أو العقدةُ وحدَها
     */
    public static function constrainClients($query, array $nodeIds, bool $withGroupColumn = false): void
    {
        $query->where(function ($w) use ($nodeIds, $withGroupColumn) {
            $w->whereIn('tree_node_id', $nodeIds)
                ->orWhere(function ($legacy) use ($nodeIds, $withGroupColumn) {
                    $legacy->whereNull('tree_node_id')
                        ->where(function ($any) use ($nodeIds, $withGroupColumn) {
                            $any->whereIn('branch_id', $nodeIds)
                                ->orWhereHas('neighborhood', function ($nq) use ($nodeIds) {
                                    $nq->whereIn('region_id', $nodeIds);
                                });

                            if ($withGroupColumn) {
                                $any->orWhereIn('group_id', $nodeIds);
                            }
                        });
                });
        });
    }

    /**
     * عقدُ المُنتقي مضافاً إليها ما يتبع كلَّ فرعٍ منها تنظيمياً — مجموعاتُه
     * ومنافذُه ومناطقُه بأيّ عمق، دون الفروع المحاسبية المتداخلة تحته.
     *
     * `DataScope::selectableBranchIds()` تعيد لنطاق «الفرع» فرعَه وحدَه
     * (`[$root]`)، فكان مديرُ الفرع يجد قائمةَ «المنفذ» في خطّ السير فارغةً،
     * ويُرفض المنفذُ إن وصل إليه برابط، مع أنّ منافذَ فرعه جزءٌ منه.
     *
     * @param  array<int, int>|null  $ids
     * @return array<int, int>|null
     */
    public static function withOrganisationalChildren(?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }

        $childrenOf = Branch::withoutGlobalScopes()->get(['id', 'parent_id', 'node_type', 'is_territory'])->groupBy('parent_id');
        $result = array_map('intval', $ids);
        $frontier = $result;

        while ($frontier) {
            $next = [];
            foreach ($frontier as $id) {
                foreach ($childrenOf->get($id, collect()) as $child) {
                    if (! $child->isAccountingBranch() && ! in_array((int) $child->id, $result, true)) {
                        $result[] = (int) $child->id;
                        $next[] = (int) $child->id;
                    }
                }
            }
            $frontier = $next;
        }

        return $result;
    }

    /**
     * العقدةُ وكلُّ ما تحتها — بعمقٍ غير محدود.
     *
     * @return array<int, int>
     */
    public static function descendantIds($nodeId): array
    {
        $node = Branch::withoutGlobalScopes()->find((int) $nodeId);

        if (! $node) {
            return [(int) $nodeId];
        }

        return $node->descendantBranchIds()
            ->push((int) $node->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
