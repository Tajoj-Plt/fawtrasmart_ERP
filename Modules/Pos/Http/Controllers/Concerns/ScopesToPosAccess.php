<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashierDevice;
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\PosSession;
use App\Models\StoreHouse;
use App\Models\TreasuryEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * نطاق الرؤية في نقاط البيع: الصلاحيةُ تحسم، ثم الفرعُ، ثم التسلسلُ الإداري.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 *     $isManager = $user->hasRoleName('manager');
 *
 * خمسةُ مواضع في متحكّم الجلسات تحسم بها **رؤيةَ بيانات كل الموظفين**
 * مقارنةً نصّيةً باسم دور. وفيها ثلاثةُ عيوب:
 *
 * • *هشاشة*: تسميةُ الدور بالعربية — «مدير» — أو إنشاءُ دورٍ «مشرف فرع»
 *   يكسر التحكّم بصمت. لا خطأ يظهر، فقط مشرفٌ يرى جلساته وحده ولا يفهم لِمَ.
 *
 * • *ازدواج*: النظام يملك لهذا صلاحيّتين دقيقتين —
 *   `points_sale_view_all_sessions` و`points_sale_view_own_sessions` —
 *   معرَّفتين في الكتالوج ومربوطتين بالأدوار، **ولا تُقرآن في اشتقاق
 *   النطاق إطلاقاً**. فالمسؤول يمنح «عرض جميع الجلسات» ولا يحدث شيء.
 *
 * • *تجاهلُ الفرع*: مديرُ فرعٍ يرى جلسات الفروع كلّها لأن الاسم واحد.
 *
 * ─────────────────────── القاعدة هنا ───────────────────────
 *
 * ثلاثُ مراتب، تُقرأ بالترتيب:
 *
 *   ١. `data_scope_all` ⇒ **بلا قيد** — تجاوزٌ صريح لكل شيء (المدير العام).
 *   ٢. غير ذلك ⇒ ما أنشأه هو ومن يديرهم إدارياً. **لا ثالثَ لهما.**
 *
 * ─────────────────────── ولماذا سقطت المرتبةُ الوسطى ───────────────────────
 *
 * كانت `points_sale_view_all_sessions` تفتح «جلسات الفرع كاملةً». والفرعُ
 * ليس وحدةَ مسؤولية: كاشيرٌ وزميلُه في فرعٍ واحدٍ لا يرأس أحدُهما الآخر،
 * وعُهدةُ كلٍّ منهما تخصّه. فمرتبةٌ تُعرّف الرؤيةَ بالمكان لا بالمسؤولية
 * تُسرّب بالضرورة.
 *
 * وكانت خانةً في شاشة الأدوار — فمُنحت لدور «موظف» بتعميمٍ واحد، وصار كلُّ
 * كاشيرٍ يرى عهدةَ زملائه. وخانةٌ تُلغي التحكّمَ كلَّه بنقرةٍ واحدة ليست
 * ضبطاً بل بابٌ خلفيّ.
 *
 * فحُذفت المرتبةُ من الشيفرة لا من البيانات وحدها: خانةٌ معطَّلةٌ باقيةٌ في
 * الشاشة هي الفخُّ نفسُه في انتظار من ينقرها.
 *
 * ─────────────────────── الفراغُ ليس «بلا قيد» ───────────────────────
 *
 * `null` ≠ `[]`، والخلطُ بينهما يقلب الحراسة. `when()` تختبر **الصدق** لا
 * الوجود، والمصفوفةُ الفارغة كاذبة في PHP — فكاشيرٌ **لا جهاز له** لا
 * يُطبَّق عليه الشرطُ أصلاً فيرى جلسات الجميع: عكسُ المقصود بالضبط، وأخطرُ
 * حالاته أنّ من لا يملك شيئاً يرى كلّ شيء.
 *
 *   • `null` ⇒ بلا قيد.
 *   • `[]`   ⇒ لا شيء له، فلا يُعرض شيء (تُترجم إلى `[0]`).
 */
trait ScopesToPosAccess
{
    protected function scopeUser(): User
    {
        return auth()->user();
    }

    /** تجاوزٌ صريح: يرى كل بيانات نقاط البيع بلا قيدٍ إطلاقاً. */
    protected function hasFullPosScope(): bool
    {
        return (bool) $this->scopeUser()?->hasRolePermission('data_scope_all');
    }

    /**
     * أنا + كل من يتبعني إدارياً. `null` = بلا قيد.
     */
    protected function accessibleEmployeeIds(): ?array
    {
        $user = $this->scopeUser();

        if ($this->hasFullPosScope()) {
            return null;
        }

        if (! $user->employee_id) {
            return null;
        }

        $employee = $user->employee;

        if (! $employee) {
            return [$user->employee_id];
        }

        return array_merge([$employee->id], $employee->getDescendantIds());
    }

    /** حسابات المستخدمين المقابلة لتلك المجموعة. `null` = بلا قيد. */
    protected function accessibleUserIds(): ?array
    {
        $employeeIds = $this->accessibleEmployeeIds();

        if ($employeeIds === null) {
            return null;
        }

        return User::whereIn('employee_id', $employeeIds)->pluck('id')->all();
    }

    /* ═══════════════ الفرع ═══════════════ */

    /**
     * فرعُ المستخدم الفعلي — من سجله الوظيفي، وإلا فرعُ حسابه.
     *
     * الترتيبُ مقصود: الموظف قد يُنقل بين الفروع في سجله الوظيفي بينما يبقى
     * `users.branch_id` على ما سُجّل يوم الإنشاء.
     */
    protected function currentEffectiveBranchId(): ?int
    {
        $user = $this->scopeUser();

        $branchId = $user->employee?->branch_id ?: $user->branch_id;

        return $branchId ? (int) $branchId : null;
    }

    /**
     * هل يجوز اختيارُ الفرع يدوياً؟
     *
     * الموظف مقفلٌ على فرع سجله الوظيفي دائماً؛ من ليس موظفاً يختار بحرية؛
     * ومن يملك `data_scope_all` يختار بحرية حتى لو كان موظفاً.
     */
    protected function canSelectBranch(): bool
    {
        $user = $this->scopeUser();

        if (! $user->isEmployee() || $this->hasFullPosScope()) {
            return true;
        }

        /*
            ومن يبلغ أكثرَ من فرعٍ يختار بينها.

            كان الشرطُ يمنع **كلَّ** موظّفٍ بلا نطاقٍ شامل. فمديرٌ يشرف على
            موظّفٍ في فرعٍ آخر يُقفَل على فرعه هو: لا يظهر له مُنتقي الفرع،
            فلا يبلغ فرعَ مرؤوسه ولا يُسند إليه جهازاً — رغم أنه مديرُه.

            وفرعٌ واحد يبقى مقفولاً: قائمةٌ بخيارٍ وحيد توحي بخيارٍ ليس له.
        */
        return count($this->reachableBranchIds()) > 1;
    }

    /**
     * يحسم `branch_id` عند الكتابة.
     *
     * يُقفَل على فرع الموظف بصرف النظر عمّا أرسله الطلب — فحقلٌ مخفيّ في
     * النموذج لا يصلح حارساً، والطلبُ يُصاغ بيدٍ أخرى بسهولة.
     */
    protected function resolveWriteBranchId(?int $requestedBranchId): ?int
    {
        if (! $this->canSelectBranch()) {
            return $this->currentEffectiveBranchId();
        }

        return $requestedBranchId;
    }

    /**
     * الفرعُ المختار في الشريط العلوي وتوابعه — `null` = «كل الفروع».
     *
     * `users.branch_id` هو ما يكتبه `BranchesController::switchBranch`، لا
     * سجلُّ الموظف: الأوّل اختيارٌ لحظيّ والثاني موطنُ الموظف الثابت.
     *
     * والتوابعُ معه: من اختار «منطقة» أراد ما تحتها، وإلّا خرج بشاشةٍ فارغة
     * كلّما اختار عقدةً تنظيمية لا جهاز فيها.
     */
    protected function activeBranchIds(): ?array
    {
        $branchId = $this->scopeUser()->branch_id ?? null;

        if (! $branchId) {
            return null;
        }

        $branch = Branch::find((int) $branchId);

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
     * الفروع التي يجوز لهذا المستخدم رؤيتها الآن. `null` = بلا قيد.
     *
     * **سياقُ الشريط العلوي يسبق مرتبةَ الصلاحية.**
     *
     * كانت الدالّة تقرأ الصلاحيةَ وحدها: من يجوز له اختيارُ الفرع (غيرُ
     * الموظف، أو مالكُ `data_scope_all`) يعود بـ`null` — بلا قيدٍ إطلاقاً.
     * فتبديلُ الفرع في الشريط لا يغيّر حرفاً في شاشات نقاط البيع: يقف
     * المستخدم على فرعٍ ويرى أجهزةَ الفروع كلّها وجلساتِها ودرجَها النقدي.
     * والموظفُ أسوأ: نطاقُه كان يُشتقّ من `employees.branch_id` — موطنِه
     * الثابت — فيبدّل الفرع إلى فرعٍ مسموحٍ له فلا يرى بياناته.
     *
     * والاختيارُ مُصدَّقٌ عند التبديل لا هنا: `switchBranch` يرفض فرعاً خارج
     * `employee_branch`، ويرفض المعطّل. فما وصل إلى `users.branch_id` مأذونٌ
     * فيه سلفاً.
     *
     * ويبقى قيدُ الصلاحية عاملاً حين لا فرعَ مختار («كل الفروع»).
     */
    protected function accessibleBranchIds(): ?array
    {
        /*
            `null` = بلا قيدٍ إطلاقاً — لغير الموظّف ولمن يملك النطاقَ الشامل
            وحدهما.

            وكان الشرطُ `canSelectBranch()` — وهو خطأٌ صار ظاهراً حين وسّعتُ
            تلك الدالّة لتشمل من يبلغ أكثرَ من فرع: فمديرٌ يبلغ فرعين صار
            «بلا قيد» فجأةً وظهرت له **الفروعُ كلُّها** بما ليس في مداه.

            فـ«أيجوز له الاختيار؟» سؤال، و«ممّا يختار؟» سؤالٌ آخر — وخلطُهما
            هو ما أوقع العطب.
        */
        $user = $this->scopeUser();

        if (! $user->isEmployee() || $this->hasFullPosScope()) {
            return null;
        }

        /*
            ═══════════ فرعُ العمل + فروعُ المرؤوسين ═══════════

            **ما كان**: `activeBranchIds()` وحدها — الفرعُ المُبدَّل إليه وما
            تحته. فمديرٌ يشرف على موظّفٍ في فرعٍ آخر **لا يبلغ فرعَه أبداً**:
            لا يظهر في مُنتقي الفرع، ولا تظهر أجهزتُه ولا ورديّاتُه، ولا
            يستطيع إسنادَ جهازٍ إليه — رغم أنه مديرُه.

            وهو ما شكا منه المستخدم: مرؤوسُه في الفرع ١٣ وهو يعمل في ٥،
            فيختفي من قائمة الموظفين لأن فلترَ الفرع يُخفيه ولا سبيل إلى
            تبديل الفرع أصلاً.

            **وما صار**: اتّحادُ المدَيين — فرعُ العمل وما تحته، **وفروعُ من
            يتبعونه إدارياً**. وهي القاعدةُ نفسُها في
            [App\Support\BranchScope::resolve()] للشريط العلوي، فلا يفترق
            ما يراه في القائمة عمّا يبلغه بالتبديل.
        */
        return $this->reachableBranchIds();
    }

    /**
     * المدى الفعليّ للفروع — بلا استشارة `canSelectBranch()`.
     *
     * الفصلُ ضروريّ لا تنظيميّ: `canSelectBranch()` صارت تسأل «أيبلغ أكثرَ
     * من فرع؟»، و`accessibleBranchIds()` تسأل «أهو غيرُ مقيَّد؟» — فلو نادى
     * كلٌّ منهما الآخرَ لوقع تكرارٌ لا نهائي. فالحسابُ هنا مرّةً واحدة،
     * والقراران يقرآن منه.
     *
     * @return array<int, int>
     */
    private function reachableBranchIds(): array
    {
        $ids = collect($this->activeBranchIds() ?? []);

        $employeeIds = $this->accessibleEmployeeIds();

        if ($employeeIds !== null) {
            $ids = $ids->merge(
                Employee::whereIn('id', $employeeIds)
                    ->whereNotNull('branch_id')
                    ->distinct()
                    ->pluck('branch_id')
            );
        }

        if ($ids->isEmpty() && ($branchId = $this->currentEffectiveBranchId())) {
            $ids->push($branchId);
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** تقييدُ استعلامٍ بعمود فرع. */
    protected function scopeToBranch(Builder $query, string $column = 'branch_id'): Builder
    {
        $ids = $this->accessibleBranchIds();

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    /* ═══════════════ الأجهزة ═══════════════ */

    /**
     * أجهزةُ الكاشير المتاحة لهذا المستخدم. `null` = بلا قيد.
     *
     * الجهازُ مربوطٌ بمستخدمٍ وبفرع، والرؤيةُ تتبع الاثنين: من يملك «الكل»
     * يرى أجهزة فرعه، وغيرُه يرى أجهزته المسنَدة إليه وحدها.
     */
    protected function accessibleDeviceIds(): ?array
    {
        if ($this->hasFullPosScope()) {
            return null;
        }

        /*
            ═══════════ الجهازُ يتبع مسؤولَه ═══════════

            **ما كان** طرفان كلاهما خطأ:

              • مع صلاحية «عرض جميع الجلسات» (سقطت): **كلُّ أجهزة الفرع**
                بلا نظرٍ إلى مسؤولها — فمشرفٌ يبلغ جهازَ زميلٍ ليس تحته.

              • وبدونها: `user_id = me` حرفياً — فالمديرُ **لا يبلغ جهازَ
                مرؤوسه** ولا يفتح عليه جلسةً، رغم أنه مديرُه.

            **وما صار**: أجهزتي وأجهزةُ من يتبعونني — وهي القاعدةُ نفسُها في
            `scopeToAssignedUser()` للورديات. والجهازُ بلا مسؤولٍ يبقى ظاهراً:
            لم يُسنَد بعد فليس عهدةَ أحدٍ يُحجب عنه.

            والفرعُ يبقى قيداً فوق ذلك: جهازُ فرعٍ لا أبلغه لا يظهر ولو كان
            مسؤولُه تحتي.
        */
        $query = CashierDevice::query();

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds ?: [0]);
        }

        $userIds = $this->accessibleUserIds();

        if ($userIds !== null) {
            $query->where(function ($inner) use ($userIds) {
                $inner->whereIn('user_id', $userIds ?: [0])
                    ->orWhereNull('user_id');
            });
        }

        return $query->pluck('id')->all();
    }

    /**
     * الأجهزةُ المُسنَدة إليّ أنا وحدي — لنموذج فتح الجلسة.
     *
     * تختلف عن `accessibleDevices()` عمداً. تلك تجيب «ما الأجهزة التي
     * تعنيني؟» فتشمل أجهزةَ مرؤوسيّ — وهو الصحيح في فلتر القائمة: المديرُ
     * يصفّي جلسات فرعه بجهاز مرؤوسه.
     *
     * وهذه تجيب «على أيّها أفتح جلسة؟» — والجوابُ جهازي وحده، لأنّ
     * `PosSessionService::assertDeviceBelongsToOpener()` ترفض ما عداه.
     * وعرضُ خيارٍ يُرفض عند الحفظ أسوأُ من إخفائه.
     */
    protected function ownDevices()
    {
        return CashierDevice::query()
            ->with('store:id,name')
            ->where('user_id', $this->scopeUser()->id)
            ->orderBy('device_name');
    }

    /** استعلامُ الأجهزة المتاحة — لقوائم الاختيار في النماذج والفلاتر. */
    protected function accessibleDevices()
    {
        $query = CashierDevice::query()->with('store:id,name')->orderBy('device_name');

        $ids = $this->accessibleDeviceIds();

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('id', $ids ?: [0]);
    }

    /* ═══════════════ الجلسات ═══════════════ */

    /**
     * تقييدُ استعلام جلساتٍ بنطاق الرؤية.
     *
     * يحلّ محلّ `if (! $isManager) { $query->where('user_id', $user->id); }`
     * المكرَّر في خمسة مواضع.
     */
    protected function scopeToVisibleSessions(Builder $query): Builder
    {
        if ($this->hasFullPosScope()) {
            return $query;
        }

        // جلساتُه هو ومن يديرهم إدارياً — ولا صلاحيةَ توسّع هذا
        return $this->scopeToVisibleUsers($query);
    }

    /**
     * المستخدمون الذين تُرى بياناتُهم: أنا ومن يتبعونني. `null` = بلا قيد.
     *
     * موضعٌ واحدٌ تقرأ منه الجلساتُ وحركاتُ الدرج ولوحةُ المؤشرات
     * والتقارير. وكان كلٌّ منها يكتب القاعدةَ بنفسه فاختلفت خمسَ مرّات:
     * موضعٌ يقيّد بالفرع، وموضعٌ بالمُنشئ، وموضعٌ يستثني حاملَ صلاحيةٍ،
     * وموضعان لا يقيّدان أصلاً.
     *
     * ═══════════ ومن ليس موظفاً ═══════════
     *
     * `null` — بلا قيد. لأنّ القاعدةَ **شجرةُ إدارة**، وحسابٌ خارجَ الشجرة
     * لا موضعَ له فيها: لا رئيسَ له ولا مرؤوس.
     *
     * وتقييدُه بنفسه يعميه تماماً — وهو ما وقع فعلاً حين جُرّب: حسابُ
     * `admin` (بلا `employee_id`) صار يرى صفرَ جلسات. وحجبُ النظام عن
     * إدارته ليس تشديداً بل عطل.
     *
     * وهي القاعدةُ نفسُها في `accessibleBranchIds()` أعلاه — `! isEmployee()`
     * ⇒ `null`. فمن أراد تقييدَ حسابٍ إداريّ يربطه بسجلّ موظف، فيدخل
     * الشجرةَ ويُقيَّد بها.
     *
     * @return array<int, int>|null
     */
    protected function visibleUserIds(): ?array
    {
        $ids = $this->accessibleUserIds();

        // فارغةٌ ≠ `null`: موظّفٌ بلا مرؤوسين يرى نفسَه، لا الجميع
        return $ids === null ? null : ($ids ?: [$this->scopeUser()->id]);
    }

    /**
     * تقييدُ استعلامٍ بعمود مالك — الجلسةُ بـ`user_id`، والحركةُ
     * بـ`created_by`.
     */
    /*
        بلا تلميحِ نوع: التقاريرُ تبني على `DB::table(...)` فتُمرّر
        `Query\Builder`، والشاشاتُ تبني على النموذج فتُمرّر
        `Eloquent\Builder`. والصنفان لا يشتركان في أبٍ ولا واجهة، و`whereIn`
        موجودةٌ في كليهما.

        وقد كسر التلميحُ التقاريرَ الخمسةَ كلَّها بـ500 عند أول تشغيل.
    */
    protected function scopeToVisibleUsers($query, string $column = 'user_id')
    {
        $ids = $this->visibleUserIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * تقييدُ استعلامٍ على **الفواتير** بنفس قاعدة الجلسات.
     *
     * الفاتورةُ لا تحمل `user_id` — تحمل `session_id`. فالقاعدةُ تُطبَّق على
     * الجلسة ثم تُسقَط على الفاتورة باستعلامٍ فرعيّ.
     *
     * وبدونه كانت أرقامُ لوحة المؤشرات تُقيَّد **بالفرع** وحده: كاشيرٌ يقرأ
     * «مبيعات اليوم ٤٨٬٠٠٠» وهي مبيعاتُ الفرع كلِّه لا مبيعاتُه. والرقمُ
     * المجموعُ يُسرّب ما يُسرّبه الصفُّ المفرد، وأخفى.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    protected function scopeToVisibleSessionsOf($query, string $column = 'session_id')
    {
        $ids = $this->visibleUserIds();

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn(
            $column,
            PosSession::whereIn('user_id', $ids)->select('id')
        );
    }

    /**
     * حمايةُ جلسةٍ واحدة بعد جلبها — يمنع تجاوز النطاق بتغيير المعرّف.
     *
     * الفحصُ بعد الجلب لا قبله: `findOrFail($id)` وحدها تُسلّم أي جلسةٍ
     * لأيّ مستخدمٍ يعرف رقمها.
     */
    protected function authorizeSessionAccess($session): void
    {
        if ($this->hasFullPosScope()) {
            return;
        }

        $userId = $this->scopeUser()->id;

        if ((int) $session->user_id === (int) $userId) {
            return;
        }

        $ids = $this->visibleUserIds();

        if ($ids === null || in_array((int) $session->user_id, $ids, true)) {
            return;
        }

        abort(403, 'لا تملك صلاحية الوصول إلى هذه الجلسة.');
    }

    /**
     * صلاحيةُ إغلاق جلسةٍ بعينها — الخاصّةُ غير جلسة الغير.
     *
     * @throws HttpException
     */
    protected function authorizeSessionClose($session): void
    {
        $isOwn = (int) $session->user_id === (int) $this->scopeUser()->id;

        $permission = $isOwn ? 'points_sale_close_sessions_own' : 'points_sale_close_sessions_all';

        if (! $this->hasFullPosScope() && ! hasPermission($permission)) {
            abort(403, $isOwn
                ? 'غير مصرح لك بإغلاق جلساتك الخاصة'
                : 'غير مصرح لك بإغلاق جلسات الآخرين');
        }
    }

    /**
     * صلاحيةُ تأكيد الإغلاق — والكاشير لا يؤكّد إقفال عهدته بنفسه.
     *
     * هذا أصلُ الضبط الداخلي في نقاط البيع: من يُغلق ليس من يؤكّد. ولذلك
     * لا يكفي أن يملك `..._own` ليؤكّد جلسته — يُشترط `..._all` أو تجاوزٌ
     * صريح.
     */
    protected function authorizeSessionConfirm($session): void
    {
        if ($this->hasFullPosScope()) {
            return;
        }

        $isOwn = (int) $session->user_id === (int) $this->scopeUser()->id;

        if ($isOwn) {
            if (! hasPermission('points_sale_confirm_close_sessions_own')) {
                abort(403, 'تأكيد إقفال العهدة لا يكون من صاحبها — يلزم مشرف.');
            }

            return;
        }

        if (! hasPermission('points_sale_confirm_close_sessions_all')) {
            abort(403, 'غير مصرح لك بتأكيد إغلاق جلسات الآخرين');
        }
    }

    /** يرمي خطأ تحقّقٍ إن كان الجهاز خارج نطاق المستخدم — لسياقات الكتابة. */
    protected function assertDeviceAccess(?int $deviceId, string $field = 'device_id'): void
    {
        if ($deviceId === null) {
            return;
        }

        $ids = $this->accessibleDeviceIds();

        if ($ids !== null && ! in_array($deviceId, $ids, true)) {
            throw ValidationException::withMessages([
                $field => ['لا تملك صلاحية استخدام هذا الجهاز.'],
            ]);
        }
    }

    /**
     * يرفض إسنادَ موظّفٍ من فرعٍ غير الفرع المختار.
     *
     * ═══════════════ العلّة ═══════════════
     *
     * الفرعُ والموظّفُ حقلان مستقلّان في النموذج، ولا شيء يربط بينهما في
     * التحقّق: `branch_id` موجودٌ في `branches` و`user_id` موجودٌ في `users`،
     * وكلاهما صحيحٌ منفرداً. فيُختار فرعُ «الرياض» ويُسنَد إليه موظّفُ
     * «جدة» ويُحفظ بلا اعتراض.
     *
     * وأثرُه لا يظهر إلّا لاحقاً: تقاريرُ الفرع تعدّ جهازاً مسؤولُه ليس منه،
     * وصلاحياتُ النطاق تُحسب على فرعٍ ولا تصل صاحبَها.
     *
     * والتصفيةُ في الشاشة دلالةٌ تمنع الخطأ قبل وقوعه؛ وهذا هو المنع.
     *
     * ═══════════════ ومتى يُتجاوز ═══════════════
     *
     * موظّفٌ **بلا فرع** يُقبل لأي فرع: سجلُّه ناقصٌ لا مخالف، ومنعُه هنا
     * يُعطّل إسنادَه إطلاقاً بلا أن يقول لماذا. والنقصُ يُصلَح في شاشة
     * الموظفين لا هنا.
     */
    protected function assertEmployeeBelongsToBranch(?int $userId, ?int $branchId, string $field = 'user_id'): void
    {
        if ($userId === null || $branchId === null) {
            return;
        }

        $employeeBranchId = User::whereKey($userId)
            ->with('employee:id,branch_id')
            ->first()?->employee?->branch_id;

        if ($employeeBranchId === null || (int) $employeeBranchId === (int) $branchId) {
            return;
        }

        throw ValidationException::withMessages([
            $field => ['الموظّف المختار ليس من الفرع المحدَّد.'],
        ]);
    }

    /**
     * يرفض ربطَ مستودعٍ خارج نطاق المستخدم.
     *
     * القائمةُ المقيَّدة في الشاشة تمنع الاختيارَ الخاطئ، ولا تمنع طلباً
     * مصنوعاً يرسل `store_id` بيده. والحارسُ هنا هو المنع؛ وذاك دلالة.
     */
    protected function assertStorehouseAccess(?int $storehouseId, string $field = 'store_id'): void
    {
        if ($storehouseId === null) {
            return;
        }

        $ids = $this->accessibleStorehouseIds();

        if ($ids !== null && ! in_array($storehouseId, $ids, true)) {
            throw ValidationException::withMessages([
                $field => ['لا تملك صلاحية الربط بهذا المستودع.'],
            ]);
        }
    }

    /**
     * المستودعاتُ التي يبلغها هذا المستخدم — عبر `default_warehouses`.
     *
     * ═══════════════ ما كان ═══════════════
     *
     * `StoreHouse::orderBy('name')->get()` — **كلُّ مستودعات المنشأة** في
     * قائمتَي «الأجهزة» و«فلتر المستودع». فكاشيرٌ لا يملك إلّا مستودعَه يرى
     * مستودعاتِ الفروع كلِّها، ويستطيع ربطَ جهازه بأيٍّ منها — فيُخصم بيعُه
     * من مخزنٍ ليس له، ويظهر العجزُ في جردِ قسمٍ آخر.
     *
     * والقائمةُ ليست عرضاً فحسب: `store_id` حقلٌ إلزاميّ في نموذج الجهاز،
     * وما يُعرض فيها هو ما يُقبل حفظُه.
     *
     * ═══════════════ القاعدة ═══════════════
     *
     *   • ليس موظفاً، أو يملك `data_scope_all` ⇒ `null` (بلا قيد).
     *   • غيرُ ذلك ⇒ مستودعاتُه ومستودعاتُ من يتبعونه إدارياً.
     *
     * والربطُ من `default_warehouses` — الجدولُ نفسُه الذي تقرؤه وحدةُ
     * المخزون، فلا تفترق الوحدتان في «أيُّ مستودعٍ لمن».
     *
     * @return array<int, int>|null
     */
    protected function accessibleStorehouseIds(): ?array
    {
        $employeeIds = $this->accessibleEmployeeIds();

        if ($employeeIds === null) {
            return null;
        }

        return DefaultWarehouses::whereIn('employee_id', $employeeIds)
            ->pluck('storehouse_id')
            ->unique()
            ->values()
            ->all();
    }

    /** استعلامُ المستودعات المتاحة — `[0]` عند الفراغ فالنتيجةُ خاليةٌ عن قصد. */
    /**
     * @param  array<int, string>  $columns  و`branch_id` افتراضاً: الشاشةُ
     *                                       تُصفّي المستودعات بالفرع، وبلا
     *                                       العمود يخرج الوسمُ فارغاً فلا
     *                                       تُصفّى قائمةٌ أبداً
     */
    protected function accessibleStorehouses(array $columns = ['id', 'name', 'branch_id'])
    {
        $ids = $this->accessibleStorehouseIds();

        $query = StoreHouse::query()->select($columns)->orderBy('name');

        return $ids === null ? $query : $query->whereIn('id', $ids ?: [0]);
    }

    /**
     * الخزائنُ التي يبلغها هذا المستخدم — عبر `treasury_employees`.
     *
     * القاعدةُ نفسُها والعلّةُ نفسُها: خزينةُ الموظف عهدتُه، وعرضُ خزائن
     * غيره يتيح ترحيلَ نقدٍ إلى عهدةٍ ليست له. والجدولُ مرآةُ
     * `default_warehouses` حرفياً (`employee_id` ⇒ `treasury_id`).
     *
     * @return array<int, int>|null
     */
    protected function accessibleTreasuryIds(): ?array
    {
        $employeeIds = $this->accessibleEmployeeIds();

        if ($employeeIds === null) {
            return null;
        }

        return TreasuryEmployee::whereIn('employee_id', $employeeIds)
            ->pluck('treasury_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * يرفض ترحيلاً إلى خزينةٍ خارج نطاق المستخدم.
     *
     * يُستدعى للترحيل والإيداع وحدهما — طرفاهما خزينة. أمّا الصرفُ النثريّ
     * فطرفُه حسابُ مصروفٍ لا خزينة، ولا معنى لقياسه بجدول
     * `treasury_employees`.
     *
     * ومن لا خزينةَ مربوطةً به إطلاقاً (`[]`) يُمنع من تحديد طرفٍ صراحةً —
     * فيسقط إلى الخزينة الافتراضية التي يحلّها `treasuryAccountFor()`.
     */
    protected function assertTreasuryAccess(?int $accountId, string $field = 'counterpart_account_id'): void
    {
        if ($accountId === null) {
            return;
        }

        $ids = $this->accessibleTreasuryIds();

        if ($ids !== null && ! in_array($accountId, $ids, true)) {
            throw ValidationException::withMessages([
                $field => ['لا تملك صلاحية الترحيل إلى هذه الخزينة.'],
            ]);
        }
    }

    /** استعلامُ الخزائن المتاحة. */
    protected function accessibleTreasuries(array $columns = ['id', 'name', 'code'])
    {
        $ids = $this->accessibleTreasuryIds();

        $query = Account::query()->select($columns)->orderBy('code');

        return $ids === null ? $query : $query->whereIn('id', $ids ?: [0]);
    }

    /**
     * تقييدُ استعلامٍ بالموظّف **المسنَد إليه** السجلّ.
     *
     * ═══════════════ ما يُقيَّد وما لا يُقيَّد ═══════════════
     *
     * هذا يُطبَّق على ما يحمل ارتباطاً حقيقياً بموظّف وحده:
     *
     *   • `shifts_pos.user_id`      — الموظّفُ المسنَد إليه الوردية
     *   • `cashier_devices.user_id` — الكاشيرُ المسؤول عن الجهاز
     *
     * ولا يُطبَّق على ما لا يرتبط بموظّف: **الطابعاتُ** تتبع جهازاً وفرعاً،
     * و`created_by` فيها ختمُ تدقيقٍ لا إسناد — فتقييدُها به يُخفي طابعةَ
     * الفرع عمّن يعمل عليه لمجرّد أن غيرَه أنشأها. والمنتجاتُ والتصنيفاتُ
     * وطرقُ الدفع كذلك: بيانٌ مرجعيٌّ للمنشأة لا عهدةٌ لأحد.
     *
     * ═══════════════ وما يقع بلا إسناد ═══════════════
     *
     * سجلٌّ بلا موظّفٍ مسنَد (`user_id IS NULL`) **يبقى ظاهراً**: وردية
     * عامّة أو جهازٌ لم يُسنَد بعد ليسا عهدةَ أحدٍ يُحجبان عنه، وإخفاؤهما
     * يجعلهما غيرَ قابلين للإسناد أبداً.
     */
    protected function scopeToAssignedUser(Builder $query, string $column = 'user_id'): Builder
    {
        $ids = $this->accessibleUserIds();

        if ($ids === null) {
            return $query;
        }

        /*
            غيرُ المُسنَد يبقى ظاهراً: جهازٌ لم يُسنَد بعدُ ليس عهدةَ أحدٍ
            يُحجب عنه، وإخفاؤه يمنع إسنادَه — فخٌّ مغلق.

            والورديّاتُ لا تمرّ من هنا أصلاً: قائمتُها مفتوحةٌ للجميع، لأنها
            تعريفُ وقتٍ لا عهدة. انظر `ShiftController::index()`.
        */
        return $query->where(function ($inner) use ($column, $ids) {
            $inner->whereIn($column, $ids ?: [0])
                ->orWhereNull($column);
        });
    }

    /**
     * يُسقط المفاتيحَ التي قيمتُها `null` وعمودُها لا يقبله.
     *
     * ═══════════════ العلّة ═══════════════
     *
     * `ConvertEmptyStringsToNull` — وسيطٌ عامٌّ في النواة — يحوّل كلَّ حقلٍ
     * تُرك فارغاً في النموذج إلى `null`. والعمودُ `NOT NULL DEFAULT x` يقبل
     * **غيابَ** المفتاح فيأخذ افتراضَه، ويرفض `null` صريحاً فينهار الإدراج.
     *
     * ووقع ذلك فعلاً: `shifts_pos.grace_minutes` هو `NOT NULL DEFAULT 15`،
     * فمن ترك «مهلة التأخّر» فارغةً أسقط حفظَ الوردية كلَّها برسالةِ قاعدةِ
     * بيانات لا يفهمها.
     *
     * والصنفُ أوسعُ من حالته: `pos_printers` وحده فيه ثمانيةُ أعمدةٍ بهذه
     * الصفة، و`pos_general_settings` تسعة. فالسدُّ هنا لا في كل حقلٍ على حدة.
     *
     * ═══════════════ ولماذا الإسقاط لا التصفير ═══════════════
     *
     * لأن الافتراضَ في القاعدة هو القرارُ الموثَّق: `paper_width` افتراضُه
     * ٨٠ لا صفر، و`is_active` افتراضُه ١ لا صفر. وتصفيرُها جميعاً يُنتج
     * طابعةً بعرضِ ورقٍ صفر وورديةً معطَّلة.
     *
     * وما له معنًى خاصٌّ عند الفراغ — كـ«مهلة التأخّر» — يُحسم عند نداء
     * الدالّة لا هنا.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dropNullsForNotNullColumns(string $table, array $data): array
    {
        static $cache = [];

        if (! array_key_exists($table, $cache)) {
            $cache[$table] = collect(DB::select("SHOW COLUMNS FROM `{$table}`"))
                ->filter(fn ($c) => $c->Null === 'NO' && $c->Default !== null)
                ->pluck('Field')
                ->all();
        }

        foreach ($cache[$table] as $column) {
            if (array_key_exists($column, $data) && $data[$column] === null) {
                unset($data[$column]);
            }
        }

        return $data;
    }

    /** تقييد استعلامٍ بعمود منشئ السجل وفق هذا النطاق. */
    protected function scopeToCreator(Builder $query, string $column = 'created_by'): Builder
    {
        $ids = $this->accessibleUserIds();

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }
}
