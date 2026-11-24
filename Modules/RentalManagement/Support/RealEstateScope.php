<?php

namespace Modules\RentalManagement\Support;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * نطاق بيانات العقارات — مصدرٌ واحد تقرؤه المتحكّمات والخدمات والتقارير.
 *
 * لا قاعدةَ جديدة هنا: السلَّم سلَّمُ {@see DataScope} والفرعُ النشط فرعُ
 * {@see HeaderBranchScope}. الجديد أنّهما يُطبَّقان معاً على سجلّ العقارات
 * بأعمدته هو:
 *
 *   ١. **بلا قيد** (`data_scope_all`، من ليس موظفاً، مدير الفرع الرئيسي بنطاق
 *      فرع/منطقة): لا قيد أشخاصٍ ولا مكان.
 *   ٢. **محور الأشخاص** (خاصّ/فريق/قسم، أو موظفٌ بلا نطاق): ما أنشأه هو أو
 *      من يديرهم، **وما أُسند إليه أو إليهم** (مدير المبنى، مسوّق العرض،
 *      مسؤول العلاقة/المهمة، فنّي الصيانة)، وما يقع في مبنى يديره أحدهم.
 *   ٣. **محور المكان** (فرع/منطقة): سجلّات فروعه، **ومعها** ما يخصّه هو ومن
 *      يديرهم بالقاعدة السابقة ولو وقع في فرعٍ آخر — فالمدير يرى بيانات
 *      مرؤوسيه وفروعهم.
 *   ٤. ثم **الفرع النشط في الشريط** يضيّق الجميع (سياقٌ لا صلاحية): الفرع
 *      الرئيسي لا يرفع القيد إلّا لمن هو بلا قيدٍ أصلاً.
 *
 * السجلّات الفرعية التي لا فرع لها ولا منشئ (الاستحقاقات، التوزيعات، الطوابق،
 * المعاينات، التكاليف، البنود، التجديدات) لا تُقيَّد بنفسها: تُبلَغ من أبٍ
 * مقيَّد (`whereIn('contract_id', scoped(Contract)->select('id'))`).
 */
class RealEstateScope
{
    /** أعمدة «مُسنَد إليه» لكل جدول — تُعامَل كالمنشئ في محور الأشخاص. */
    private const ASSIGNED = [
        're_buildings' => ['manager_user_id'],
        're_complexes' => ['manager_user_id'],
        're_listings' => ['assigned_agent_id'],
        're_customer_relationships' => ['assigned_user_id'],
        're_tasks' => ['assigned_user_id'],
        're_maintenance_requests' => ['assigned_to_user_id'],
    ];

    /** جداول تتبع مبنى: من يدير المبنى يرى ما فيه. */
    private const VIA_BUILDING = [
        're_units' => 'building_id',
        're_contracts' => 'building_id',
        're_listings' => 'building_id',
        're_maintenance_requests' => 'building_id',
    ];

    /** جداول تتبع عقداً في مبنى يديره. */
    private const VIA_CONTRACT = [
        're_payments' => 'contract_id',
    ];

    /** @var array<int, array<int, int>|null> ذاكرة الطلب — مفتاحها المستخدم */
    private static array $peopleMemo = [];

    public static function flush(): void
    {
        self::$peopleMemo = [];
    }

    /**
     * يقيّد استعلاماً على جدولٍ عقاريّ يحمل `branch_id` و`created_by`.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     * @param  string|null  $table  اسم الجدول (أو مستعاره) — يُستنتج من النموذج إن غاب
     */
    public static function apply($query, ?string $table = null)
    {
        $base = match (true) {
            $query instanceof EloquentBuilder => $query->getModel()->getTable(),
            $query instanceof Relation => $query->getRelated()->getTable(),
            default => (string) $table,
        };
        $prefix = ($table ?: $base) ? ($table ?: $base).'.' : '';

        if (! DataScope::isUnrestricted()) {
            $people = self::peopleIds() ?? [0];
            $branches = DataScope::branchIds();

            $query->where(function ($scope) use ($prefix, $base, $people, $branches) {
                if ($branches !== null) {
                    $scope->whereIn($prefix.'branch_id', $branches);
                }

                $scope->orWhere(fn ($mine) => self::constrainPeople($mine, $prefix, $base, $people));
            });
        }

        if ($ids = self::headerBranchIds()) {
            $query->where(fn ($place) => $place->whereIn($prefix.'branch_id', $ids)->orWhereNull($prefix.'branch_id'));
        }

        return $query;
    }

    /**
     * المستخدمون الذين تُحسب سجلّاتهم «له»: هو ومن يديرهم — أو `null` بلا قيد.
     *
     * محور الأشخاص يتبع {@see DataScope::documentUserIds()} حرفياً (الفريق
     * محسوبٌ على التسلسل الإداري، و«خاصّ» المصرَّح بها تبقى خاصّة). ومحور
     * المكان يُضاف إليه التسلسل الإداري: المدير يرى مرؤوسيه ولو في فرعٍ آخر.
     *
     * @return array<int, int>|null
     */
    public static function peopleIds(): ?array
    {
        if (DataScope::isUnrestricted()) {
            return null;
        }

        $user = DataScope::user();
        $key = (int) ($user?->id ?? 0);

        if (array_key_exists($key, self::$peopleMemo)) {
            return self::$peopleMemo[$key];
        }

        if (! $user) {
            return self::$peopleMemo[$key] = [0];
        }

        if (DataScope::isPeopleAxis()) {
            return self::$peopleMemo[$key] = DataScope::documentUserIds() ?? [(int) $user->id];
        }

        $ids = [(int) $user->id];

        if ($user->isEmployee() && $user->employee) {
            $managed = $user->employee->getDescendantIds();

            if ($managed !== []) {
                $ids = array_merge($ids, User::whereIn('employee_id', $managed)->pluck('id')->map(fn ($id) => (int) $id)->all());
            }
        }

        return self::$peopleMemo[$key] = array_values(array_unique($ids));
    }

    /**
     * المستخدمون الذين يجوز إسناد سجلٍّ إليهم — أو `null` بلا قيد. محور المكان
     * يضمّ مستخدمي فروعه إلى مرؤوسيه.
     *
     * @return array<int, int>|null
     */
    public static function assignableUserIds(): ?array
    {
        $people = self::peopleIds();

        if ($people === null) {
            return null;
        }

        return array_values(array_unique(array_merge($people, DataScope::isPeopleAxis() ? [] : (DataScope::userIds() ?? []))));
    }

    /**
     * عقدة الشريط وما تحتها — أو `null` حين لا تضييق (لا فرع مختاراً، أو
     * الرئيسيّ لمن هو بلا قيد).
     *
     * @return array<int, int>|null
     */
    public static function headerBranchIds(): ?array
    {
        $node = HeaderBranchScope::nodeId();

        if (! $node) {
            return null;
        }

        $descendants = Branch::findCached((int) $node)?->descendantBranchIds();

        return $descendants
            ? $descendants->push((int) $node)->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [(int) $node];
    }

    /** هل يقع هذا المستخدم داخل من يجوز الإسناد إليهم؟ */
    public static function canAssignTo($userId): bool
    {
        $allowed = self::assignableUserIds();

        return ! $userId || $allowed === null || in_array((int) $userId, $allowed, true);
    }

    /**
     * عملاء الملفّ المركزي الذين يراهم المستخدم من داخل العقارات.
     *
     * بلا قيد: كلّ العملاء. وغيره: من له علاقة/عقد/عرض/مهمة داخل نطاقه،
     * ومن أُسند إليه أو إلى من يديرهم في ملفّ العملاء (`clients.employee_id`
     * و`client_employee`)، ومحور المكان يضمّ عملاء فروعه.
     */
    public static function clients(?EloquentBuilder $query = null): EloquentBuilder
    {
        $query ??= Client::query();

        if (DataScope::isUnrestricted()) {
            return $query;
        }

        $employees = DataScope::documentEmployeeIds() ?? [];
        $branches = DataScope::branchIds();

        return $query->where(function ($scope) use ($employees, $branches) {
            $scope->whereIn('clients.id', self::apply(DB::table('re_customer_relationships'), 're_customer_relationships')->select('client_id'))
                ->orWhereIn('clients.id', self::apply(DB::table('re_contracts'), 're_contracts')->whereNull('re_contracts.deleted_at')->select('tenant_client_id'))
                ->orWhereIn('clients.id', self::apply(DB::table('re_listings'), 're_listings')->whereNotNull('client_id')->select('client_id'))
                ->orWhereIn('clients.id', self::apply(DB::table('re_tasks'), 're_tasks')->whereNotNull('client_id')->select('client_id'))
                // المعاينة لا فرع لها: تُبلَغ من عرضها المقيَّد
                ->orWhereIn('clients.id', DB::table('re_viewings')->whereNotNull('client_id')
                    ->whereIn('listing_id', self::apply(DB::table('re_listings'), 're_listings')->whereNull('re_listings.deleted_at')->select('id'))->select('client_id'));

            if ($employees !== []) {
                $scope->orWhereIn('clients.employee_id', $employees)
                    ->orWhereIn('clients.id', DB::table('client_employee')->whereIn('employee_id', $employees)->select('client_id'));
            }

            if ($branches !== null) {
                $scope->orWhereIn('clients.branch_id', $branches)->orWhereIn('clients.tree_node_id', $branches);
            }
        });
    }

    /** قيد الأشخاص على جدولٍ واحد: المنشئ، والمُسنَد إليه، وما في مبنى يديره. */
    private static function constrainPeople($query, string $prefix, string $table, array $people): void
    {
        $query->whereIn($prefix.'created_by', $people);

        foreach (self::ASSIGNED[$table] ?? [] as $column) {
            $query->orWhereIn($prefix.$column, $people);
        }

        $managed = fn () => DB::table('re_buildings')->whereIn('manager_user_id', $people)->whereNull('deleted_at')->select('id');

        if (isset(self::VIA_BUILDING[$table])) {
            $query->orWhereIn($prefix.self::VIA_BUILDING[$table], $managed());
        }

        if (isset(self::VIA_CONTRACT[$table])) {
            $query->orWhereIn($prefix.self::VIA_CONTRACT[$table], DB::table('re_contracts')->whereIn('building_id', $managed())->select('id'));
        }
    }
}
