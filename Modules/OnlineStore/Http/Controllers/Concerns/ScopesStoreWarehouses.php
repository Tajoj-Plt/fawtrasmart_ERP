<?php

namespace Modules\OnlineStore\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\OnlineStore\Support\StoreAssignmentScope;

/**
 * أيُّ مخازن المتجر يراها هذا المستخدم الآن — مصدرٌ واحد للقائمة وللفلاتر
 * ولمنسدلات الأذون ولحراسة الروابط المباشرة.
 *
 * ══════════ القاعدة (قرار صاحب النظام، الجولة الثانية) ══════════
 *
 *   «التي مسندة له وإلى الموظفين التابعين له، وتُفلتر حسب الفرع؛
 *    إذا كان هو ضمن الفرع الرئيسي تظهر الكل».
 *
 * وهي طبقتان تتقاطعان — والتفصيلُ في {@see StoreAssignmentScope}:
 *
 *   ١. **الإسناد**: من يرى الكلَّ (غيرُ الموظف، أو `data_scope_all`، أو فرعُه
 *      الأساسيّ الرئيسي) يرى كلَّ مخازن المتجر؛ وغيرُه يرى المخازنَ المُسنَدة
 *      إليه أو إلى من يتبعه إدارياً (`default_warehouses`). مخزنٌ بلا مسندٍ
 *      لا يراه إلا من يرى الكل.
 *   ٢. **فرعُ الشريط**: من اختار فرعاً رأى مخازنَه وما تحته؛ والرئيسيُّ يعني
 *      «الكلّ» لمن يرى الكل. وهذه الطبقةُ تُضيّق ولا توسّع.
 *
 * وفوقهما صلاحيةُ «عرض المستودع» المضبوطةُ على المخزن نفسه
 * (`storehouseViewableIds()`).
 *
 * ══════════ ما كان قبلها ══════════
 *
 * كان الموظفُ العاديّ يرى كلَّ مخازن المتجر في فرعه (نطاقُ مكان) ولو لم
 * تُسنَد إليه، وكان الإذنُ يُقيَّد فوق ذلك بمنشئه. الآن الإذنُ يتبع مخزنَه:
 * من رأى المخزنَ رأى أذونَه — فمن عُهد إليه مخزنٌ يرى ما جرى فيه ولو كتبه غيرُه.
 *
 * والتحويلُ بين مخزنين يقرأ طبقةَ الإسناد وحدَها ({@see storeWarehouseScopeIds()}):
 * ولا يُحوَّل إلا بين مخزنين كلاهما في نطاقه — ولو قُصّت منسدلتا «من/إلى»
 * على فرع الشريط لاستحال التحويلُ بين فرعين أصلاً.
 *
 * مخزنُ متجرٍ قديمٌ بلا فرع يُعامَل كأنه في الفرع الرئيسي.
 *
 * تُستعمل مع `Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy`
 * في المتحكّم نفسه — منها `scopeUser()` و`storehouseViewableIds()`.
 */
trait ScopesStoreWarehouses
{
    /** @var array<int, int|null>|null معرّف المخزن ⇒ فرعُه الفعليّ */
    private ?array $storeWarehouseBranchMapCache = null;

    /** @var array<string, array<int, int>> */
    private array $storeWarehouseIdsCache = [];

    /**
     * كلُّ مخازن المتجر مع فرعها الفعليّ (الفارغ ⇒ الرئيسي).
     *
     * @return array<int, int|null>
     */
    private function storeWarehouseBranchMap(): array
    {
        if ($this->storeWarehouseBranchMapCache !== null) {
            return $this->storeWarehouseBranchMapCache;
        }

        $mainId = Branch::mainId();

        return $this->storeWarehouseBranchMapCache = StoreHouse::onlineStore()
            ->pluck('branch_id', 'id')
            ->map(fn ($branchId) => $branchId ? (int) $branchId : ($mainId ? (int) $mainId : null))
            ->all();
    }

    /**
     * فرعُ الشريط وما تحته — `null` = بلا قيد فرع.
     *
     * الرئيسيُّ «الكلّ» لمن يرى الكل ({@see StoreAssignmentScope::seesAll()})،
     * ولغيره فرعٌ كغيره: نطاقُ الأشخاص يُقاس على الرئيسيّ نفسه لا على المنشأة.
     *
     * @return array<int, int>|null
     */
    protected function storeHeaderBranchIds(): ?array
    {
        $node = StoreAssignmentScope::headerNode();
        $branch = $node ? Branch::find($node) : null;

        if (! $branch) {
            return null;
        }

        return $branch->descendantBranchIds()
            ->push($branch->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * مخازنُ المتجر داخل **نطاق المستخدم** — بلا نظرٍ إلى فرع الشريط.
     *
     * @return array<int, int>
     */
    protected function storeWarehouseScopeIds(): array
    {
        if (isset($this->storeWarehouseIdsCache['scope'])) {
            return $this->storeWarehouseIdsCache['scope'];
        }

        $all = array_keys($this->storeWarehouseBranchMap());

        $ids = StoreAssignmentScope::seesAll()
            ? $all
            : array_values(array_intersect(
                StoreAssignmentScope::warehouseIdsAssignedTo(StoreAssignmentScope::teamEmployeeIds()),
                $all
            ));

        // صلاحيةُ «عرض المستودع» على المخزن نفسه
        $viewable = $this->storehouseViewableIds();

        if ($viewable !== null) {
            $ids = array_intersect($ids, $viewable);
        }

        return $this->storeWarehouseIdsCache['scope'] = array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * مخازنُ المتجر المرئيّة **الآن**: نطاقُ المستخدم ∩ فرعُ الشريط.
     *
     * @return array<int, int>
     */
    protected function storeWarehouseIds(): array
    {
        if (isset($this->storeWarehouseIdsCache['active'])) {
            return $this->storeWarehouseIdsCache['active'];
        }

        $ids = $this->storeWarehouseScopeIds();
        $headerBranchIds = $this->storeHeaderBranchIds();

        if ($headerBranchIds !== null) {
            $map = $this->storeWarehouseBranchMap();

            $ids = array_values(array_filter(
                $ids,
                fn ($id) => ($map[$id] ?? null) !== null && in_array($map[$id], $headerBranchIds, true)
            ));
        }

        return $this->storeWarehouseIdsCache['active'] = $ids;
    }

    /**
     * استعلامُ مخازن المتجر المرئيّة — للقوائم والمنسدلات.
     *
     * @param  bool  $withHeaderBranch  `false` لمنسدلتَي التحويل: النطاقُ وحده
     */
    protected function storeWarehousesQuery(bool $withHeaderBranch = true): Builder
    {
        $ids = $withHeaderBranch ? $this->storeWarehouseIds() : $this->storeWarehouseScopeIds();

        // `[0]` لا يوافق معرّفاً حقيقياً: «لا شيء» تبقى لا شيء ولا تُقرأ «الكلّ»
        return StoreHouse::onlineStore()->whereIn('id', $ids ?: [0]);
    }

    /**
     * مخزنُ متجرٍ بمعرّفه — أو 404 إن لم يكن مخزنَ متجرٍ أو خرج عن النطاق.
     *
     * 404 لا 403: الرابطُ المباشر لا يُفشي وجودَ مخزنٍ في فرعٍ لا يراه صاحبُ الطلب.
     */
    protected function findStoreWarehouseOrFail($id, array $with = [], bool $withHeaderBranch = true): StoreHouse
    {
        $storehouse = is_numeric($id) ? $this->storeWarehousesQuery($withHeaderBranch)->with($with)->find((int) $id) : null;

        abort_if(! $storehouse, 404, 'المخزن غير موجود أو خارج نطاق صلاحياتك.');

        return $storehouse;
    }

    /** هل المخزنُ داخل نطاق المستخدم (بلا فرع الشريط)؟ */
    protected function storeWarehouseInScope($id): bool
    {
        return $id && in_array((int) $id, $this->storeWarehouseScopeIds(), true);
    }

    /**
     * خطأُ تحقّقٍ إن كان المخزنُ المُرسَل خارج النطاق أو ليس مخزنَ متجر —
     * المنسدلةُ وعدٌ لا حارس، والقيمةُ تُبدَّل في المتصفّح.
     */
    protected function assertStoreWarehouseInScope($id, string $field, string $label = 'المستودع'): void
    {
        if (! $this->storeWarehouseInScope($id)) {
            throw ValidationException::withMessages([
                $field => [$label.' المحدَّد ليس من مخازن المتجر المتاحة لك.'],
            ]);
        }
    }

    /**
     * حصرُ استعلام أذونٍ بمخازن المتجر المرئيّة — يمرّ الإذنُ إن كان أيٌّ من
     * أعمدته الثلاثة (المخزن، «من»، «إلى») مخزناً مرئياً، فإذنُ تحويلٍ بين
     * فرعين يظهر لمن يقف على أيٍّ منهما.
     *
     * @param  Builder  $query  استعلامُ `WarehousePermits`
     */
    protected function scopePermitsToStoreWarehouses($query, ?array $ids = null)
    {
        $ids = ($ids ?? $this->storeWarehouseIds()) ?: [0];

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('store_houses_id', $ids)
                ->orWhereIn('from_store_houses_id', $ids)
                ->orWhereIn('to_store_houses_id', $ids);
        });
    }

    /**
     * استعلامُ أذون المتجر المرئيّة لهذا المستخدم.
     *
     * الإذنُ يتبع **مخزنَه**: من رأى المخزنَ (مسنداً إليه أو إلى فريقه، في
     * فرع الشريط) رأى أذونَه ولو كتبها غيرُه. كان يُضاف قيدُ المنشئ
     * (`created_by`) لمحور الأشخاص، فلا يرى من عُهد إليه مخزنٌ ما كتبه
     * زميلُه عليه — ويخالف «يرى الكلَّ» لموظف الفرع الرئيسي.
     */
    protected function storePermitsQuery(): Builder
    {
        return $this->scopePermitsToStoreWarehouses(WarehousePermits::query());
    }

    /** إذنُ متجرٍ بمعرّفه — أو 404 إن خرج عن النطاق. */
    protected function findStorePermitOrFail($id, array $with = [], bool $lock = false): WarehousePermits
    {
        $query = $this->storePermitsQuery()->with($with);

        if ($lock) {
            $query->lockForUpdate();
        }

        $permit = is_numeric($id) ? $query->find((int) $id) : null;

        abort_if(! $permit, 404, 'الإذن المخزني غير موجود أو خارج نطاق صلاحياتك.');

        return $permit;
    }

    /**
     * فروعُ منسدلة «الفرع» — ما يحقّ للمستخدم اختيارُه وحدَه.
     *
     * @return Collection<int, Branch>
     */
    protected function storeBranchOptions(): Collection
    {
        $query = Branch::query()->orderBy('name');
        $allowed = StoreAssignmentScope::selectableBranchIds();

        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return $query->get(['id', 'name', 'parent_id']);
    }

    /**
     * الفرعُ المطلوب في فلترٍ وما تحته في الشجرة — أو `null` إن لم يُطلب أو
     * لم يكن ممّا يحقّ للمستخدم اختيارُه (فيُهمَل الفلتر ولا يوسَّع به شيء:
     * الصفوفُ مقيَّدةٌ بالنطاق قبله على أيّ حال).
     *
     * @return array<int, int>|null
     */
    protected function requestedStoreBranchIds($requested): ?array
    {
        if (! is_numeric($requested) || (int) $requested <= 0) {
            return null;
        }

        $branch = Branch::find((int) $requested);

        if (! $branch) {
            return [0];
        }

        $allowed = StoreAssignmentScope::selectableBranchIds();

        if ($allowed !== null && ! in_array((int) $branch->id, $allowed, true)) {
            return [0];
        }

        return $branch->descendantBranchIds()
            ->push($branch->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** معرّفاتُ مخازن المتجر المرئيّة الواقعة في هذه الفروع. */
    protected function storeWarehouseIdsInBranches(array $branchIds): array
    {
        $map = $this->storeWarehouseBranchMap();

        return array_values(array_filter(
            $this->storeWarehouseIds(),
            fn ($id) => in_array($map[$id] ?? null, $branchIds, true)
        ));
    }

    // ==================== المسند إليهم ====================

    /**
     * موظفو منسدلة «المسند إليهم» في شاشتَي إنشاء المخزن وتعديله.
     *
     * @param  array<int, int>  $keepIds  المسندون الحاليون — يبقون في الحقل
     * @return Collection<int, Employee>
     */
    protected function storeWarehouseAssignableEmployees(array $keepIds = []): Collection
    {
        return StoreAssignmentScope::assignableEmployees($keepIds);
    }

    /**
     * المسندون المحدَّدون مسبقاً في النموذج: ما أُعيد من الطلب السابق، وإلا
     * الحاليون، وعند الإنشاء — الموظفُ المقيَّد نفسُه (وإلا اختفى عنه ما أنشأه).
     *
     * @return array<int, int>
     */
    protected function storeWarehouseSelectedAssignees(?StoreHouse $storehouse = null): array
    {
        $old = old(StoreAssignmentScope::WAREHOUSE_FIELD);

        if (is_array($old)) {
            return array_values(array_map('intval', $old));
        }

        if ($storehouse) {
            return StoreAssignmentScope::warehouseAssigneeIds((int) $storehouse->id);
        }

        $self = StoreAssignmentScope::selfEmployeeId();

        return ! StoreAssignmentScope::seesAll() && $self ? [$self] : [];
    }

    /**
     * الفرعُ الذي يُختم به مخزنُ متجرٍ عند الحفظ.
     *
     * ما اختاره المستخدم إن كان ممّا يحقّ له، وإلّا خطأُ تحقّق. وإن لم يختر:
     * ما كان عليه المخزن، ثم فرعُ الشريط، ثم فرعُ الموظف، ثم الرئيسي — فلا
     * يُحفظ مخزنٌ بلا فرعٍ يختفي به من كلّ نطاق.
     */
    protected function resolveStoreWarehouseBranchId($requested, ?int $current = null): ?int
    {
        $allowed = StoreAssignmentScope::selectableBranchIds();

        if (is_numeric($requested) && (int) $requested > 0) {
            $requested = (int) $requested;

            if ($requested === $current) {
                return $current;
            }

            if (($allowed !== null && ! in_array($requested, $allowed, true)) || ! Branch::whereKey($requested)->exists()) {
                throw ValidationException::withMessages([
                    'branch_id' => ['الفرع المحدَّد خارج نطاق صلاحياتك.'],
                ]);
            }

            return $requested;
        }

        if ($current) {
            return $current;
        }

        $user = $this->scopeUser();

        // فرعُ الشريط أوّلاً (هو ما ينظر إليه المستخدم الآن)، ثم فرعُ سجلّه الوظيفي
        foreach ([$user->branch_id, $user->employee?->branch_id] as $candidate) {
            $candidate = $candidate ? (int) $candidate : null;

            if ($candidate && ($allowed === null || in_array($candidate, $allowed, true))) {
                return $candidate;
            }
        }

        if ($allowed) {
            return (int) $allowed[0];
        }

        return Branch::mainId()
            ?? Branch::whereNull('parent_id')->orderBy('id')->value('id')
            ?? Branch::orderBy('id')->value('id');
    }
}
