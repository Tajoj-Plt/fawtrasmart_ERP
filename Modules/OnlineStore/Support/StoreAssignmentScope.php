<?php

namespace Modules\OnlineStore\Support;

use App\Models\Branch;
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\StoreHouse;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * «المسند له» — قاعدةُ رؤية مخازن المتجر وموردِيه (قرار صاحب النظام).
 *
 * ══════════ القاعدة (حرفياً من المالك) ══════════
 *
 *   «التي مسندة له وإلى الموظفين التابعين له، وتُفلتر حسب الفرع؛
 *    إذا كان هو ضمن الفرع الرئيسي تظهر الكل».
 *
 * وتُترجَم هكذا — في موضعٍ واحد تقرؤه شاشاتُ المتجر كلُّها:
 *
 *   ١. **يرى الكلَّ** ({@see seesAll()}): من ليس موظفاً، أو يملك `data_scope_all`،
 *      أو فرعُه الأساسيّ (`employees.branch_id`) هو الرئيسي. وفرعُ الشريط
 *      يبقى يُضيّق عليه كما كان حين يختار فرعاً بعينه.
 *   ٢. **غيرُه** يرى ما أُسند إليه أو إلى من يتبعه إدارياً
 *      ({@see teamEmployeeIds()}: هو + `Employee::getDescendantIds()` — السلّمُ
 *      الإداريّ نفسُه الذي يبني عليه المخزونُ والمبيعات، لا مشيٌ جديد
 *      في الشجرة)، ثم يُضيَّق بفرع الشريط.
 *
 * ما لا مسندَ له (مخزنٌ أو موردٌ قديم) لا يراه إلا من يرى الكل — ويُسنَد
 * من شاشة التعديل.
 *
 * ══════════ أين يُخزَّن «المسند» ══════════
 *
 *   • المخازن — `default_warehouses` (مخزن ↔ موظف، متعدّد): الجدولُ نفسُه الذي
 *     يقرؤه المخزونُ ونقاطُ البيع. التخزينُ كلُّه في هذا الصنف
 *     ({@see warehouseIdsAssignedTo()}, {@see syncWarehouseAssignees()}) كي
 *     يكون تبديلُ الجدول لاحقاً تعديلاً في ملفٍّ واحد.
 *   • الموردون — `suppliers.employee_id` («الموظف المسؤول»): عمودٌ قائم
 *     بمفتاح أجنبيٍّ إلى `employees` وتستعمله وحدةُ المشتريات نفسُها
 *     (`purchases.suppliers.assign_employee`)، فلا جدولَ ولا عمودَ جديد، ولا
 *     تفترق الوحدتان في «لمن هذا المورد».
 *
 * الذاكرةُ على سمات الطلب الحالي: تُنسى بنهايته، فلا تنتقل بين مستخدمَين.
 */
final class StoreAssignmentScope
{
    /** اسمُ حقل الإسناد في نماذج المخازن (`employee_id` محجوزٌ لصلاحيات العرض القديمة) */
    public const WAREHOUSE_FIELD = 'assigned_employee_ids';

    /** اسمُ حقل الإسناد في نموذج المورد (كما في وحدة المشتريات) */
    public const SUPPLIER_FIELD = 'employee_id';

    // ==================== من يرى الكل؟ ====================

    /**
     * هل يرى هذا المستخدم كلَّ المخازن وكلَّ الموردين (مع بقاء فرع الشريط)؟
     */
    public static function seesAll(): bool
    {
        return self::memo('sees_all', function () {
            $user = auth()->user();

            if (! $user || ! $user->employee_id) {
                // غيرُ الموظف يدير النظام ولا يعمل فيه؛ ومن لا مستخدمَ له لا يرى شيئاً
                return (bool) $user;
            }

            if ($user->hasRolePermission('data_scope_all') || DataScope::isUnrestricted()) {
                return true;
            }

            // «ضمن الفرع الرئيسي»: فرعُه الأساسيّ في سجلّه الوظيفيّ لا ما يقف عليه في الشريط
            $home = (int) ($user->employee?->branch_id ?? 0);

            return $home > 0 && (bool) Branch::findCached($home)?->is_main;
        });
    }

    /** معرّفُ موظفِ المستخدم الحاليّ — صفرٌ لمن ليس موظفاً */
    public static function selfEmployeeId(): int
    {
        return (int) (auth()->user()?->employee_id ?? 0);
    }

    /**
     * هو ومن يتبعه إدارياً (مباشرةً أو عبر السلسلة).
     *
     * @return array<int, int>
     */
    public static function teamEmployeeIds(): array
    {
        return self::memo('team', function () {
            $selfId = self::selfEmployeeId();

            if (! $selfId) {
                return [];
            }

            $team = Employee::find($selfId)?->getDescendantIds() ?? [];

            return array_values(array_unique(array_map('intval', array_merge([$selfId], $team))));
        });
    }

    // ==================== الفرع ====================

    /**
     * عقدةُ فرع الشريط التي تُضيَّق عليها القوائم — `null` = بلا تضييق.
     *
     * الرئيسيُّ في الشريط يعني «المنشأة كلَّها» لمن يرى الكل (قرار المالك:
     * «إذا كان ضمن الفرع الرئيسي تظهر الكل»)، ولغيره يمرّ بالقاعدة المشتركة
     * {@see HeaderBranchScope::nodeId()}: الرئيسيُّ عنده فرعٌ كغيره.
     */
    public static function headerNode(): ?int
    {
        return self::memo('header_node', function () {
            $current = current_branch_id();

            if (! $current) {
                return null;
            }

            if (! self::seesAll()) {
                /*
                    مُسنَدٌ لا مكانٌ: الوصولُ هنا بالإسناد، ولا يُسقِط الشريطُ منه إلا ما هو
                    خارج الفرع المختار. فنأخذ الفرعَ كما اختاره حرفياً — لا نردّه إلى فرعه
                    الأساسيّ كما تفعل {@see HeaderBranchScope::nodeId()} لأصحاب نطاق الفرع:
                    مديرٌ يبدّل الشريط إلى فرعٍ أُسند إليه فيه مخزنٌ يجب أن يراه، لا أن يعود
                    إلى شاشته السابقة كأنّ التبديل لم يقع.
                */
                return (int) $current;
            }

            if (HeaderBranchScope::meansWholeOrganisation((int) $current)) {
                return null;
            }

            return HeaderBranchScope::nodeId();
        });
    }

    /**
     * الفروعُ التي يحقّ للمستخدم اختيارُها في نموذجٍ أو فلتر — `null` = كلُّها.
     *
     * من يرى الكل يختار أيَّ فرع؛ وغيرُه ما يحدّده نطاقُ بياناته.
     *
     * @return array<int, int>|null
     */
    public static function selectableBranchIds(): ?array
    {
        return self::seesAll() ? null : DataScope::selectableBranchIds();
    }

    // ==================== من يجوز الإسناد إليه ====================

    /**
     * معرّفاتُ من يحقّ للمستخدم الإسنادُ إليهم — `null` = أيُّ موظفٍ نشط.
     *
     * @return array<int, int>|null
     */
    public static function assignableEmployeeIds(): ?array
    {
        return self::seesAll() ? null : self::teamEmployeeIds();
    }

    /**
     * موظفو منسدلة الإسناد: النشطون ممّن يحقّ إسنادُهم + ما هو مُسنَدٌ فعلاً
     * (`$keepIds`) كي لا يختفي من الحقل ولا يُسقَط عند الحفظ. نفسُه أولاً.
     *
     * @param  array<int, int|string|null>  $keepIds
     * @return Collection<int, Employee>
     */
    public static function assignableEmployees(array $keepIds = []): Collection
    {
        $keepIds = array_values(array_filter(array_map('intval', $keepIds)));
        $allowed = self::assignableEmployeeIds();

        $employees = Employee::query()
            ->where(function ($q) use ($allowed, $keepIds) {
                $q->where(function ($active) use ($allowed) {
                    $active->active();

                    if ($allowed !== null) {
                        $active->whereIn('id', $allowed ?: [0]);
                    }
                });

                if ($keepIds) {
                    $q->orWhereIn('id', $keepIds);
                }
            })
            ->get(['id', 'first_name', 'middle_name', 'nickname', 'branch_id']);

        $selfId = self::selfEmployeeId();

        return $employees
            ->sortBy(fn (Employee $e) => mb_strtolower(trim((string) $e->full_name)))
            ->sortBy(fn (Employee $e) => (int) $e->id === $selfId ? 0 : 1)
            ->values();
    }

    /**
     * يحسم قائمةَ مسندي مخزنٍ من الطلب.
     *
     * المنسدلةُ وعدٌ لا حارس: القيمةُ تُبدَّل في المتصفّح، فكلُّ معرّفٍ يُفحص هنا
     * ويُرفض بخطأ تحقّقٍ لا يُقبل بصمت.
     *
     *   • من يرى الكل: القائمةُ المُرسَلة هي الحقيقة (يجوز تفريغُها).
     *   • غيرُه: ما لا يحقّ له إسنادُه من الموجود سابقاً (`$existing`) يبقى
     *     كما هو، وما أسنده يُضاف إليه؛ وإن لم يبقَ في القائمة هو ولا أحدٌ
     *     من فريقه أُسنِد إليه هو — وإلا اختفى عنه ما أنشأه بنفسه.
     *
     * @param  mixed  $requested
     * @param  array<int, int>  $existing  المسندون الحاليون (فارغ عند الإنشاء)
     * @return array<int, int>
     */
    public static function resolveWarehouseAssignees($requested, array $existing = []): array
    {
        $requested = array_values(array_unique(array_filter(array_map('intval', is_array($requested) ? $requested : []))));
        $existing = array_values(array_unique(array_map('intval', $existing)));
        $allowed = self::assignableEmployeeIds();

        if ($requested) {
            $known = Employee::whereIn('id', $requested)->pluck('id')->map(fn ($id) => (int) $id)->all();

            if (array_diff($requested, $known)) {
                throw ValidationException::withMessages([
                    self::WAREHOUSE_FIELD => ['أحد الموظفين المختارين غير موجود.'],
                ]);
            }

            // ما هو مُسنَدٌ فعلاً يمرّ ولو خرج عن نطاق المحرِّر (لم يلمسه)
            $outside = array_diff($requested, $allowed ?? $requested, $existing);

            if ($outside) {
                throw ValidationException::withMessages([
                    self::WAREHOUSE_FIELD => ['الموظف المحدَّد خارج من يحقّ لك الإسناد إليهم (أنت ومن يتبعك).'],
                ]);
            }
        }

        if ($allowed === null) {
            return $requested;
        }

        // ما يعلو نطاقَه يبقى مُسنَداً كما كان — لا يستطيع نزعَه
        $final = array_values(array_unique(array_merge($requested, array_diff($existing, $allowed))));

        if (! array_intersect($final, self::teamEmployeeIds()) && ($selfId = self::selfEmployeeId())) {
            $final[] = $selfId;
        }

        return $final;
    }

    /**
     * مسندُ المورد من الطلب (حقل `employee_id`) — واحد.
     *
     * @param  mixed  $requested  ما جاء في الطلب
     * @param  bool  $submitted  هل أُرسل الحقلُ أصلاً؟ (غيابُه في التعديل = لا تغيير)
     * @param  int|null  $current  المسندُ الحاليّ للمورد
     */
    public static function resolveSupplierAssignee($requested, bool $submitted, ?int $current = null): ?int
    {
        if (! $submitted) {
            return $current ?: (self::seesAll() ? null : (self::selfEmployeeId() ?: null));
        }

        $requested = is_numeric($requested) && (int) $requested > 0 ? (int) $requested : null;
        $allowed = self::assignableEmployeeIds();

        if ($requested) {
            if (! Employee::whereKey($requested)->exists()) {
                throw ValidationException::withMessages([self::SUPPLIER_FIELD => ['الموظف المحدَّد غير موجود.']]);
            }

            if ($allowed !== null && $requested !== $current && ! in_array($requested, $allowed, true)) {
                throw ValidationException::withMessages([
                    self::SUPPLIER_FIELD => ['الموظف المحدَّد خارج من يحقّ لك الإسناد إليهم (أنت ومن يتبعك).'],
                ]);
            }

            return $requested;
        }

        if ($allowed === null) {
            return null;
        }

        // موظفٌ مقيَّد لم يختر أحداً: يُسنَد إليه، لا يُترك المورد بلا مسند فيختفي عنه
        return self::selfEmployeeId() ?: null;
    }

    // ==================== المخازن ====================

    /**
     * مخازنُ مُسنَدةٌ إلى هؤلاء الموظفين.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, int>
     */
    public static function warehouseIdsAssignedTo(array $employeeIds): array
    {
        if (! $employeeIds) {
            return [];
        }

        return DefaultWarehouses::whereIn('employee_id', $employeeIds)
            ->pluck('storehouse_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * مسندو كلِّ مخزنٍ: [معرّف المخزن => [[id, name], …]] — لعرضهم في القائمة والتفاصيل.
     *
     * @param  iterable<int, int|string>  $storehouseIds
     * @return array<int, array<int, array{id:int, name:string}>>
     */
    public static function warehouseAssignees(iterable $storehouseIds): array
    {
        $ids = collect($storehouseIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if (! $ids) {
            return [];
        }

        $rows = DefaultWarehouses::whereIn('storehouse_id', $ids)->get(['storehouse_id', 'employee_id']);
        $employees = Employee::withTrashed()
            ->whereIn('id', $rows->pluck('employee_id')->unique())
            ->get(['id', 'first_name', 'middle_name', 'nickname'])
            ->keyBy('id');

        $out = [];

        foreach ($rows as $row) {
            $employee = $employees->get($row->employee_id);

            if ($employee) {
                $out[(int) $row->storehouse_id][] = ['id' => (int) $employee->id, 'name' => $employee->full_name];
            }
        }

        return $out;
    }

    /**
     * معرّفاتُ مسندي مخزنٍ واحد.
     *
     * @return array<int, int>
     */
    public static function warehouseAssigneeIds(int $storehouseId): array
    {
        return DefaultWarehouses::where('storehouse_id', $storehouseId)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * يطابق مسندي المخزن مع القائمة النهائية: يضيف الناقص ويحذف الزائد، وما
     * لم يتغيّر لا يُلمس (فلا يُعاد ترقيم صفوفٍ تقرؤها شاشاتٌ أخرى).
     *
     * @param  array<int, int>  $employeeIds
     */
    public static function syncWarehouseAssignees(StoreHouse $storehouse, array $employeeIds): void
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $current = self::warehouseAssigneeIds((int) $storehouse->id);

        $added = array_diff($employeeIds, $current);
        $removed = array_diff($current, $employeeIds);

        foreach ($added as $employeeId) {
            DefaultWarehouses::create(['storehouse_id' => $storehouse->id, 'employee_id' => $employeeId]);
        }

        if ($removed) {
            DefaultWarehouses::where('storehouse_id', $storehouse->id)->whereIn('employee_id', $removed)->delete();
        }

        if ($added || $removed) {
            $names = Employee::withTrashed()->whereIn('id', array_merge($added, $removed))->get()
                ->mapWithKeys(fn (Employee $e) => [(int) $e->id => $e->full_name]);

            OperationLog::record('updated', 'storehouse_log', $storehouse, sprintf(
                'تحديث المسند إليهم للمخزن **%s**%s%s',
                $storehouse->name,
                $added ? ' — أُسند إلى: '.collect($added)->map(fn ($id) => $names[$id] ?? '#'.$id)->implode('، ') : '',
                $removed ? ' — رُفع الإسناد عن: '.collect($removed)->map(fn ($id) => $names[$id] ?? '#'.$id)->implode('، ') : ''
            ));
        }
    }

    // ==================== الموردون ====================

    /**
     * يقصر استعلام موردين على من يراهم المستخدم بالإسناد: لمن يرى الكل لا قيد
     * (فرع الشريط يُطبَّق في الشاشة)، ولغيره `employee_id` ضمن هو وفريقه، والمورد
     * بلا مسند لا يظهر له.
     *
     * @param  Builder|Relation  $query
     */
    public static function constrainSuppliers($query, string $column = 'suppliers.employee_id')
    {
        if (self::seesAll()) {
            return $query;
        }

        // `[0]` لا يوافق معرّفاً حقيقياً: «لا شيء» تبقى لا شيء ولا تُقرأ «الكلّ»
        return $query->whereIn($column, self::teamEmployeeIds() ?: [0]);
    }

    // ==================== الذاكرة ====================

    /**
     * @template T
     *
     * @param  callable():T  $compute
     * @return T
     */
    private static function memo(string $key, callable $compute)
    {
        $request = request();
        $full = 'store_assignment.'.(auth()->id() ?? 0).'.'.$key;

        if (! $request->attributes->has($full)) {
            $request->attributes->set($full, $compute());
        }

        return $request->attributes->get($full);
    }
}
