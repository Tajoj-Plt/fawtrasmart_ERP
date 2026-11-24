<?php

namespace Modules\Manufacturing\Support;

use App\Models\Branch;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;

/**
 * عُقدُ مُنتقي «تابع لـ» في وحدة التصنيع — مصدرٌ واحد للمتحكّمات والقوالب.
 *
 * نطاقُ رؤية المستخدم أوّلاً، ثمّ عقدةُ الشريط العلويّ وما تحتها. والفرعُ
 * الرئيسيّ (أو لا فرع) يعني المنشأةَ كلَّها فتُعرض الشجرةُ كاملة — قاعدةُ
 * {@see HeaderBranchScope} نفسُها في المبيعات والمشتريات.
 */
final class BranchTree
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $nodes = null;

    /** @var array<int, string>|null */
    private static ?array $names = null;

    /**
     * @return array<int, array{id:int, name:string, parent_id:?int, node_type:string, type_label:string}>
     */
    public static function nodes(): array
    {
        if (self::$nodes !== null) {
            return self::$nodes;
        }

        try {
            $nodes = DataScope::filterNodes(AffiliationTree::nodes());
            $header = HeaderBranchScope::nodeId();

            if ($header) {
                $ids = AffiliationTree::descendantIds($header);
                $nodes = array_values(array_filter(
                    $nodes,
                    fn ($n) => in_array((int) ($n['id'] ?? 0), $ids, true)
                ));
            }

            return self::$nodes = $nodes;
        } catch (\Throwable $e) {
            return self::$nodes = [];
        }
    }

    public static function contains(int $branchId): bool
    {
        foreach (self::nodes() as $node) {
            if ((int) $node['id'] === $branchId) {
                return true;
            }
        }

        return false;
    }

    /** اسمُ العقدة لعمود «تابع لـ» — «—» لما لم يُنسب لجهة. */
    public static function name($branchId): string
    {
        if (! $branchId) {
            return '—';
        }

        self::$names ??= Branch::withoutGlobalScopes()->pluck('name', 'id')->all();

        return (string) (self::$names[(int) $branchId] ?? '—');
    }
}
