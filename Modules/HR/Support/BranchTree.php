<?php

namespace Modules\HR\Support;

use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;

/**
 * «تابع لـ» في شاشات الموارد البشرية — قاعدةٌ واحدةٌ لكلّ حقل فرعٍ في القسم.
 *
 * كانت كلُّ شاشةٍ تعرض قائمةً مسطّحةً (`Branch::all()`) وتطابق
 * `where('branch_id', $id)` مطابقةً تامّة، فاختيارُ «منطقة الرياض» لا يعيد
 * موظّفي فروعها ومنافذها — والموظّفون كلُّهم معلَّقون على الأبناء.
 *
 *   • المُنتقي: شجرةٌ متدرّجة (`reports.partials.affiliation-tree`) على ما
 *     يحقّ للمستخدم رؤيتُه ({@see DataScope::filterNodes()}) وفرعِ الشريط.
 *   • الترشيح: العقدةُ المختارة **وكلُّ ما تحتها** ({@see ids()}).
 */
final class BranchTree
{
    /**
     * عقدُ المُنتقي.
     *
     * @param  array<int, int|string|null>  $keepIds  عقدٌ محفوظةٌ في السجلّ المفتوح تبقى
     *                                              ولو كانت خارج فرع الشريط — فلا
     *                                              يُفرَّغ الحقلُ عند تعديل سجلٍّ من جهةٍ أخرى.
     */
    public static function treeNodes(array $keepIds = []): array
    {
        $nodes = DataScope::filterNodes(AffiliationTree::nodes());
        $header = HeaderBranchScope::nodeId();

        if (! $header) {
            return $nodes;
        }

        $allowed = AffiliationTree::descendantIds($header);

        foreach (array_filter(array_map('intval', $keepIds)) as $keep) {
            $allowed[] = $keep;
        }

        return array_values(array_filter($nodes, fn ($n) => in_array((int) ($n['id'] ?? 0), $allowed, true)));
    }

    /**
     * العقدةُ المختارة وما تحتها — أو `null` حين لا اختيار («الكل»/فارغ).
     *
     * @return array<int, int>|null
     */
    public static function ids($branchId): ?array
    {
        if ($branchId === null || $branchId === '' || $branchId === 'all' || ! is_numeric($branchId)) {
            return null;
        }

        return AffiliationTree::descendantIds((int) $branchId);
    }

    /**
     * قائمةُ موظفين منسدلة مقصورةٌ على فرع الشريط العلويّ وما تحته — ولا
     * شيء على الفرع الرئيسيّ أو «كلّ الفروع».
     *
     * @param  array<int, int|string|null>  $keepIds  موظفون مرتبطون بالسجلّ المفتوح يبقون ظاهرين
     */
    public static function headerEmployees($query, array $keepIds = [])
    {
        $node = HeaderBranchScope::nodeId();

        if (! $node) {
            return $query;
        }

        $ids = AffiliationTree::descendantIds($node);
        $keepIds = array_values(array_filter(array_map('intval', $keepIds)));

        return $query->where(function ($w) use ($ids, $keepIds) {
            $w->whereIn('employees.branch_id', $ids);

            if ($keepIds !== []) {
                $w->orWhereIn('employees.id', $keepIds);
            }
        });
    }

    /**
     * `where($column, $id)` شاملاً ما تحت العقدة. لا شيء حين لا اختيار.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     */
    public static function apply($query, $branchId, string $column = 'branch_id')
    {
        $ids = self::ids($branchId);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }
}
