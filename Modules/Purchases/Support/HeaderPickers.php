<?php

namespace Modules\Purchases\Support;

use App\Models\Employee;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;
use Illuminate\Database\Eloquent\Builder;
use Modules\Purchases\Services\PurchaseSettings;

/**
 * قوائمُ الاختيار في المشتريات تتبع فرعَ الشريط العلويّ.
 *
 * من وقف على «منطقة الرياض» لا يرى في أيّ قائمةٍ منسدلة — مورِّدين أو
 * موظفين أو مستودعات — إلّا ما يتبع الرياض. كانت كلُّ شاشةٍ تجلب الجدولَ
 * كاملاً (`Supplier::all()`)، فتظهر في فاتورةٍ صادرةٍ من الرياض مورِّدو جدّة.
 *
 * القاعدةُ واحدةٌ هنا لا في كلّ شاشة:
 *
 *   • عقدةُ الشريط **وما يتبعها** — كقاعدة `BelongsToBranch::scopeForBranch()`.
 *   • **الفرعُ الرئيسيّ** أو لا شيء يعنيان المنشأة كلَّها ({@see HeaderBranchScope}).
 *   • الصفُّ **بلا فرع** يبقى ظاهراً في كلّ جهة — لم يُنسب لجهةٍ بعد.
 *   • ما **يحمله المستندُ المفتوح** يُستبقى صراحةً (`$keepIds`)، فلا يُفرَّغ
 *     حقلٌ عند تعديل مستندٍ من جهةٍ أخرى.
 */
final class HeaderPickers
{
    /**
     * العقدُ المشمولة: عقدةُ الشريط وما يتبعها — أو `null` حين لا ترشيح.
     *
     * @return array<int, int>|null
     */
    public static function nodeIds(): ?array
    {
        $node = HeaderBranchScope::nodeId();

        return $node ? AffiliationTree::descendantIds($node) : null;
    }

    /**
     * تقييدُ استعلامٍ على عمود فرعه بعقد الشريط.
     *
     * @param  array<int, int|string|null>  $keepIds  صفوفٌ تبقى ولو كانت خارج الجهة
     */
    public static function constrain(Builder $query, array $keepIds = [], string $column = 'branch_id'): Builder
    {
        $ids = self::nodeIds();

        if ($ids === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $keepIds = array_values(array_filter(array_map('intval', $keepIds)));

        return $query->where(function ($w) use ($ids, $keepIds, $column, $table) {
            $w->whereIn($table.'.'.$column, $ids)
                ->orWhereNull($table.'.'.$column);

            if ($keepIds !== []) {
                $w->orWhereIn($table.'.id', $keepIds);
            }
        });
    }

    /**
     * عقدُ مُنتقي «تابع لـ» — ما يحقّ للمستخدم رؤيتُه، مقصوراً على فرع الشريط.
     *
     * البانِي الواحدُ للشجرة في التقارير والنماذج وفلاتر الفهارس معاً:
     * نطاقُ الرؤية أوّلاً ({@see DataScope::filterNodes()})، ثمّ عقدةُ الشريط
     * وما تحتها. والعقدةُ التي أبوها خارج القائمة يجعلها المُنتقي أعلى
     * مستوىً، فتبدأ شجرةُ صاحبِ الفرع من فرعه لا من جذرٍ لا يراه.
     *
     * @return array<int, array{id:int, name:string, parent_id:?int, node_type:string, type_label:string}>
     */
    public static function treeNodes(): array
    {
        $nodes = DataScope::filterNodes(AffiliationTree::nodes());
        $ids = self::nodeIds();

        if ($ids === null) {
            return $nodes;
        }

        return array_values(array_filter($nodes, fn ($n) => in_array((int) ($n['id'] ?? 0), $ids, true)));
    }

    public static function suppliers(?Builder $query = null, array $keepIds = []): Builder
    {
        $query ??= Supplier::query();

        /* على محور الأشخاص (`own`/`team`) يصمت فرعُ الشريط — القاعدةُ نفسُها
           التي تعمل بها شاشةُ الموردين ومحافظُهم: صفوفُ المستخدم مقيَّدةٌ
           بشخصه وفريقه أصلاً، ومورّدٌ نُسب إليه مختومٌ على الفرع الرئيسيّ
           كان يختفي من قوائم الإنشاء والتعديل لأنّ فرعَه ليس فرعَ الشريط. */
        if (DataScope::documentUserIds() !== null && DataScope::isPeopleAxis()) {
            return $query;
        }

        return self::constrain($query, $keepIds);
    }

    public static function employees(?Builder $query = null, array $keepIds = []): Builder
    {
        $query ??= Employee::query();

        /* الأقسامُ المختارة في الإعدادات هي **مصدرُ** قوائم الموظفين لا
           مصفاةٌ فوق فرع الشريط: مديرٌ واقفٌ على «منطقة القصيم» كان يرى من
           قسم المشتريات موظّفَ القصيم وحدَه، والإعدادُ وُضع ليجلب موظّفي
           القسم لكل القوائم أينما كان الشريط. فمع أقسامٍ محدَّدة يُترك قيدُ
           الفرع ويبقى نطاقُ الرؤية (الأمنُ لا يُتنازل عنه). */
        if (PurchaseSettings::employeeDepartments() === null) {
            $query = self::constrain($query, $keepIds);
        }

        return self::constrainToDepartments(
            self::constrainToVisibleEmployees($query, $keepIds),
            $keepIds
        );
    }

    /**
     * محورُ الأشخاص على قوائم الموظفين — نطاقُ **المستندات** لا التقارير.
     *
     * كانت القائمةُ على فرع الشريط والأقسام وحدهما، فمن نطاقُه «بياناته
     * هو» أو «فريقه» يرى في مُنتقي الموظّف أسماءَ من لا يرى لهم سجلّاً في
     * الجدول، ويختارهم فيردّه الخادم. القاعدةُ نفسُها التي تشرحها
     * {@see DataScope::employeeOptions()}: `documentEmployeeIds()` كي يجد
     * المشرفُ أسماءَ فريقه الذين يرى فواتيرهم. وما يحمله المستندُ المفتوح
     * يُستبقى (`$keepIds`).
     *
     * @param  array<int, int|string|null>  $keepIds
     */
    public static function constrainToVisibleEmployees(Builder $query, array $keepIds = []): Builder
    {
        $allowed = DataScope::documentEmployeeIds();

        if ($allowed === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $keepIds = array_values(array_filter(array_map('intval', $keepIds)));

        return $query->where(function ($w) use ($allowed, $keepIds, $table) {
            $w->whereIn($table.'.id', $allowed ?: [0]);

            if ($keepIds !== []) {
                $w->orWhereIn($table.'.id', $keepIds);
            }
        });
    }

    /**
     * حصرُ قائمة موظفين بأقسام «موظّفي المشتريات» من الإعدادات.
     *
     * تسري على كلّ قائمة موظفين في الوحدة من هذا الموضع الواحد، فمن ضبط
     * الأقسام في الإعدادات لا يرى في أيّ سيلكت إلّا موظّفيها. وما يحمله
     * المستندُ المفتوح يُستبقى (`$keepIds`) كقاعدة الفرع، فلا يُفرَّغ حقلٌ
     * عند تعديل مستندٍ لموظّفٍ نُقل قسمُه لاحقاً.
     *
     * @param  array<int, int|string|null>  $keepIds
     */
    public static function constrainToDepartments(Builder $query, array $keepIds = []): Builder
    {
        $departments = PurchaseSettings::employeeDepartments();

        if ($departments === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $keepIds = array_values(array_filter(array_map('intval', $keepIds)));

        return $query->where(function ($w) use ($departments, $keepIds, $table) {
            $w->whereIn($table.'.department_id', $departments);

            if ($keepIds !== []) {
                $w->orWhereIn($table.'.id', $keepIds);
            }
        });
    }

    /**
     * النظيرُ على قوائم **المستخدمين** (منشئو المستندات): يُقاس المستخدمُ
     * بقسم موظّفه عبر `users.employee_id`. مستخدمٌ بلا سجلّ موظفٍ يبقى —
     * الحصرُ للموظفين، ولا يُخفي مديراً لم يُربَط ببطاقة.
     */
    public static function constrainUsersToDepartments(Builder $query): Builder
    {
        $departments = PurchaseSettings::employeeDepartments();

        if ($departments === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where(function ($w) use ($departments, $table) {
            $w->whereNull($table.'.employee_id')
                ->orWhereIn($table.'.employee_id', Employee::whereIn('department_id', $departments)->select('id'));
        });
    }

    public static function storeHouses(?Builder $query = null, array $keepIds = []): Builder
    {
        return self::constrain($query ?? StoreHouse::query(), $keepIds);
    }
}
