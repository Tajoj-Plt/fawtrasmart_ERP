<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * أيُّ الفروع يبلغها هذا المستخدم — مصدرٌ واحد للعرض وللتبديل معاً.
 *
 * ═══════════════ ما كان ═══════════════
 *
 * **الصنفُ لم يكن موجوداً أصلاً.** `BranchesController::switchBranch()` يستورده
 * وينادي منه ثلاث دوالّ (`canSwitchTo`، `canSwitchToAll`، `forget`) — فكلُّ
 * تبديلِ فرعٍ ينتهي بـ«Class not found»، أي خطأ 500 لا رسالةَ منعٍ مفهومة.
 *
 * وفي الشريط العلويّ عطبان مستقلّان:
 *
 *   • القائمةُ كلُّها محجوبةٌ خلف `@if (! auth()->user()->isEmployee())` —
 *     فمديرُ الفرع الذي يدير موظفين لا يرى مبدّلَ الفروع إطلاقاً، وهو أوّلُ
 *     من يحتاجه.
 *
 *   • ومن يمرّ ذلك الشرط يُعرض له `Branch::whereNull('parent_id')->get()` —
 *     **كلُّ فروع الجذر بلا أيّ نطاق**. فحسابُ نظامٍ بلا صلاحيةِ نطاقٍ واحدة
 *     يرى الفروعَ جميعاً ويبدّل إليها.
 *
 * ═══════════════ القاعدة ═══════════════
 *
 * أربعُ مراتبَ متدرّجة، الأولى المطابقةُ تُرجَع:
 *
 *   ١. `data_scope_all`         ⇒ كلُّ الفروع، ومعها خيار «جميع الفروع».
 *   ٢. `data_scope_region`      ⇒ فروعُه المُسنَدة وما تحتها في شجرة الفروع.
 *   ٣. `data_scope_branch`      ⇒ فروعُه المُسنَدة وحدها.
 *   ٤. **يدير موظفين**          ⇒ فروعُه وفروعُ من يتبعونه إدارياً.
 *   ٥. غيرُ ذلك                 ⇒ فروعُه المُسنَدة وحدها.
 *
 * و«فروعُه» في المراتب ٢ـ٥ = كلُّ صفوف `employee_branch` له، لا صاحبُ `is_main`
 * وحده: من أُسند إلى فرعين يبلغهما ويبدّل بينهما، و`is_main` لا يعدو أن يكون
 * الفرعَ الافتراضي عند الدخول.
 *
 * والمرتبةُ الرابعة هي الجديدة: التسلسلُ الإداري (`Employee::direct_manager_id`)
 * يمنح رؤيةَ فروع التابعين بلا حاجةٍ إلى صلاحيةِ نطاقٍ صريحة — لأن من يدير
 * موظفاً في فرعٍ آخر يحتاج أن يبلغ فرعَه بحكم عمله.
 *
 * ═══════════════ ولماذا صنفٌ واحد ═══════════════
 *
 * القائمةُ المعروضة وحارسُ التبديل يجب أن يجيبا الإجابةَ نفسها. ولو حُسبت
 * القائمةُ في القالب وحُرِس التبديلُ في المتحكّم لافترقا بأوّل تعديلٍ يُنسى
 * أحدُهما — فيظهر فرعٌ يُردّ عنه، أو يُخفى فرعٌ يُقبل الوصولُ إليه بالعنوان.
 */
class BranchScope
{
    /**
     * محفوظُ الطلب — مفهرسٌ بالمستخدم.
     *
     * الشريطُ يسأل مرّةً لعرض القائمة، والحارسُ يسأل مرّةً عند التبديل،
     * وكلُّ سؤالٍ يمسّ `employees` و`branches`. والحفظُ للطلب لا أكثر: الصلاحياتُ
     * تتغيّر، وقيمةٌ معمَّرةٌ تُبقي مستخدماً على نطاقٍ سُحب منه.
     *
     * @var array<int, Collection<int, Branch>>
     */
    private static array $memo = [];

    /**
     * الفروعُ التي يجوز لهذا المستخدم بلوغُها — مرتّبةً بالاسم.
     *
     * @return Collection<int, Branch>
     */
    public static function switchable(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user) {
            return collect();
        }

        if (isset(self::$memo[$user->id])) {
            return self::$memo[$user->id];
        }

        return self::$memo[$user->id] = self::resolve($user);
    }

    /** معرّفاتُ تلك الفروع وحدها. */
    public static function switchableIds(?User $user = null): Collection
    {
        return self::switchable($user)->pluck('id');
    }

    /** أيبلغ هذا المستخدم هذا الفرع؟ */
    public static function canSwitchTo(int $branchId, ?User $user = null): bool
    {
        return self::switchableIds($user)->contains($branchId);
    }

    /**
     * أيجوز له اختيار «جميع الفروع» (`branch_id = null`)؟
     *
     * لمالك `data_scope_all` وحده: القيمةُ الفارغة تعني «بلا قيدِ فرع» في كل
     * استعلامٍ في النظام، فهي أوسعُ من أيّ فرعٍ بعينه.
     */
    public static function canSwitchToAll(?User $user = null): bool
    {
        return hasPermission('data_scope_all');
    }

    /**
     * أيُعرض له مبدّلُ الفروع أصلاً؟
     *
     * فرعٌ واحد لا يُبدَّل إليه — وقائمةٌ بخيارٍ وحيدٍ توحي بخيارٍ ليس له.
     */
    public static function shouldShowSwitcher(?User $user = null): bool
    {
        return self::canSwitchToAll($user) || self::switchable($user)->count() > 1;
    }

    /** يُنسى المحفوظ — بعد تبديل الفرع أو تغيير الصلاحيات. */
    public static function forget(): void
    {
        self::$memo = [];
    }

    /**
     * القاعدةُ نفسها — انظر توثيق الصنف.
     *
     * @return Collection<int, Branch>
     */
    private static function resolve(User $user): Collection
    {
        $active = fn () => Branch::query()->where('status', '!=', 1)->orderBy('name');

        /*
            ١. بلا قيد — لمن يملك `data_scope_all`، **ولمن ليس موظفاً**.

            الثانيةُ كانت مفقودة، وهي عطبٌ أوقف العمل: حسابُ نظامٍ أو إدارة
            (`employee_id` فارغ) لا تنطبق عليه قواعدُ التسلسل الإداري أصلاً —
            لا مديرَ له ولا مرؤوسين ولا سجلَّ موظفٍ يحمل فرعاً. فكان يسقط إلى
            آخر الشروط ويحصل على **قائمةٍ خالية**، فتختفي الفروعُ كلُّها من
            الشريط ويُردّ عن التبديل بـ«لا تملك صلاحية الدخول لهذا الفرع».

            وهي القاعدةُ نفسُها المعمولُ بها في المشروع كلّه: `Scopes` و
            `ScopesToEmployeeHierarchy` و`ScopesToPosAccess` كلُّها تُرجع
            «بلا قيد» لمن ليس موظفاً، وأمرُه يُضبط بالصلاحيات وحدها.
        */
        if (hasPermission('data_scope_all') || ! $user->employee_id) {
            return $active()->get();
        }

        /*
            فروعُ الموظف كلُّها — لا فرعٌ واحد.

            شاشةُ الموظف تُسند أكثرَ من فرعٍ (`employee_branch`)، وكان هذا الصنف
            لا يقرأ الجدول إطلاقاً: يحلّ فرعاً واحداً من `users.branch_id` ويبني
            عليه كلَّ شيء. فمن أُسند إلى فرعين يُحفظ له الفرعان في الجدول، ثم
            لا يرى إلّا أوّلهما: لا يظهر الثاني في مبدّل الشريط، ويُردّ عنه لو
            كتب رقمه في الرابط — فالاختيارُ الثاني مُسجّلٌ معطّل.

            و`is_main` لا يُسأل عنه هنا: هو الفرعُ الافتراضي عند الدخول
            (والمكتوب في `employees.branch_id`)، لا حدُّ ما يبلغه الموظف.
        */
        $ownBranchId = Scopes::resolveBranchId($user);

        $baseIds = self::assignedBranchIds($user);
        if ($ownBranchId) {
            $baseIds->push($ownBranchId);
        }
        $baseIds = $baseIds->unique()->values();

        // ٢. فروعُه وما تحتها في الشجرة
        if ($baseIds->isNotEmpty() && hasPermission('data_scope_region')) {
            $ids = $baseIds->flatMap(
                fn (int $bid) => (Branch::findCached($bid)?->descendantBranchIds() ?? collect())->concat([$bid])
            )->unique()->values();

            return $active()->whereIn('id', $ids)->get();
        }

        // ٣. فروعُه وحدها
        if ($baseIds->isNotEmpty() && hasPermission('data_scope_branch')) {
            return $active()->whereIn('id', $baseIds)->get();
        }

        /*
            ٤. من يدير موظفين: فروعُه وفروعُ التابعين له.

            `getDescendantIds()` تمشي السلسلة كاملةً لا المستوى الأوّل فقط —
            فمن يدير مشرفاً يدير فريقَه أيضاً. والفروعُ تُقرأ من سجلّات
            الموظفين لا من حسابات المستخدمين: الموظفُ قد لا يكون له حسابُ
            دخولٍ أصلاً، وفرعُه مثبَّتٌ في سجلّه.
        */
        if ($user->employee) {
            $employeeIds = collect($user->employee->getDescendantIds());

            if ($employeeIds->isNotEmpty()) {
                $branchIds = \App\Models\Employee::whereIn('id', $employeeIds)
                    ->whereNotNull('branch_id')
                    ->distinct()
                    ->pluck('branch_id');

                $branchIds = $branchIds->concat($baseIds)->unique()->values();

                if ($branchIds->isNotEmpty()) {
                    return $active()->whereIn('id', $branchIds)->get();
                }
            }
        }

        // ٥. فروعُه وحدها — أو لا شيء إن لم يكن له فرع
        return $baseIds->isNotEmpty()
            ? $active()->whereIn('id', $baseIds)->get()
            : collect();
    }

    /**
     * الفروعُ المُسنَدة للموظف في `employee_branch` — كلُّها سواءٌ.
     *
     * الجدولُ يضمّ ما اختير في شاشة الموظف (فروعاً ومواقع) وأسلافَها
     * المُضافة تلقائياً — وكلُّها عقدٌ في شجرة `branches`.
     *
     * @return Collection<int, int>
     */
    private static function assignedBranchIds(User $user): Collection
    {
        if (! $user->employee_id) {
            return collect();
        }

        return DB::table('employee_branch')
            ->where('employee_id', $user->employee_id)
            ->pluck('branch_id')
            ->map(fn ($bid) => (int) $bid)
            ->filter()
            ->unique()
            ->values();
    }
}
