<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * تعيين الفرع تلقائياً وتقييد الاستعلامات حسب نطاق البيانات — المرجع الذي
 * يشير إليه `App\Constants\permissions\DataScopePermissions::groups()['scopes']['controller']`.
 *
 * محوران منفصلان مقصودان:
 *   - {@see resolveBranchId()} / {@see canSelectBranch()}: أي فرعٍ يُنسب إليه
 *     مستندٌ جديد، ومن يُسمح له باختيار غيره.
 *   - {@see applyDataScope()}: أي صفوفٍ من جدولٍ موجود يرى المستخدم عند
 *     العرض، حسب صلاحيات `data_scope_*` (بياناته، فريقه، فرعه، منطقته، الكل).
 *
 * الاستعمال في كنترولر:
 *
 *     $branchId = Scopes::resolveBranchId(auth()->user());
 *     $query = Scopes::applyDataScope($query, auth()->user());
 */
class Scopes
{
    /**
     * العقدة التي يُنسب إليها مستندٌ جديد ينشئه هذا المستخدم — «تابع لـ».
     *
     * **العقدة النشطة من الهيدر أوّلاً** (`users.branch_id`)، ثمّ تعيينُ
     * الموظّف في الموارد البشرية (`employees.branch_id`) لمن لا عقدةَ نشطةَ
     * له.
     *
     * **كان العكس، وكان يُعطّل مبدّلَ الفروع بالكامل.** الترتيبُ السابق
     * يقرأ `employees.branch_id` أوّلاً ويهمل `users.branch_id`، وهما
     * عمودان مختلفان: المبدّلُ في الهيدر يكتب الثاني، والموارد البشرية
     * تكتب الأوّل. فمن بدّل فرعه من الهيدر ثمّ أنشأ مستنداً خُتم المستندُ
     * بفرع تعيينه لا بالفرع الذي يقف عليه — والمبدّلُ لا أثر له إطلاقاً.
     *
     * وأسوأُ منه: `employees.branch_id` فارغٌ لأربعةٍ من المستخدمين هنا
     * (منهم مالكُ النظام)، فكانت مستنداتُهم تُختم بـ`null`. و`forBranch`
     * تُظهر ما لا عقدةَ له تحت **كلّ** عقدة — فبدت شاشاتُ المشتريات كأنّها
     * لا تُصفّي شيئاً: كلُّ فواتير الشراء الخمس والستّين كانت بلا عقدة.
     *
     * وهذا هو ترتيبُ شاشة العملاء نفسُه
     * ({@see \App\Services\ClientAccessService::resolveUserBranchTreeScope()}):
     * `users.branch_id` هو مصدرُ الحقيقة، والعميلُ يُنسب إلى العقدة التي
     * يقف عليها منشئُه.
     */
    public static function resolveBranchId(User $user): ?int
    {
        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        return $user->employee?->branch_id ? (int) $user->employee->branch_id : null;
    }

    /**
     * هل يُسمح لهذا المستخدم باختيار فرعٍ غير الفرع المُحلَّل تلقائياً؟
     *
     * يُبنى على صلاحيات نطاق الرؤية الموجودة أصلاً بدل استحداث صلاحية جديدة:
     * من يملك نطاقاً أوسع من «فريقه/نفسه» (فرع، منطقة، أو الكل) منطقيٌّ أن
     * يُسند مستندات لفروع غير فرعه.
     */
    public static function canSelectBranch(User $user): bool
    {
        return hasPermission(['data_scope_branch', 'data_scope_region', 'data_scope_all']);
    }

    /**
     * العقدةُ التي يُختم بها المستندُ عند الحفظ — ما اختاره المستخدم إن جاز
     * له، وإلّا ما كان عليه المستندُ ثمّ عقدتُه هو.
     *
     * الموضعُ الواحد لقاعدةٍ كانت مكرَّرةً ستَّ عشرةَ مرّة في متحكّمات
     * المشتريات بصيغٍ ثلاث — وكلُّها كانت تقبل المعرّفَ المرسَل كما هو متى
     * ملك المستخدمُ صلاحيةَ الاختيار: صاحبُ منطقةٍ يرسل `branch_id` لفرعٍ في
     * منطقةٍ أخرى فيُختم به المستند. فيُتحقَّق هنا أن المختارَ داخل نطاقه
     * ({@see DataScope::selectableBranchIds()})، وإلّا سقط إلى الاحتياط.
     *
     * @param  mixed  $requested  ما جاء في الطلب (`branch_id`) — فارغٌ إن لم يُرسَل
     * @param  int|null  $fallback  عقدةُ المستند المحفوظة في التعديل — تبقى ولا
     *                              تُعاد وسمُها بعقدة المحرِّر
     */
    public static function chosenBranchId(User $user, $requested, ?int $fallback = null): ?int
    {
        $requested = $requested ? (int) $requested : null;

        if ($requested && static::canSelectBranch($user)) {
            $allowed = DataScope::selectableBranchIds();

            if ($allowed === null || in_array($requested, $allowed, true)) {
                return $requested;
            }
        }

        return $fallback ?: static::resolveBranchId($user);
    }

    /**
     * تقييد استعلام بما يحقّ لهذا المستخدم رؤيته حسب صلاحية نطاق البيانات
     * التي يملكها (واحدة فقط منطقياً — الأوسع تُغني عمّا دونها).
     *
     * @param  string  $creatorColumn  عمود منشئ السجل (عادة created_by)
     * @param  string|null  $branchColumn  عمود الفرع المجمَّد على السجل؛ null إن كان
     *                                     الجدول لا يحمل فرعاً فيُتجاوز مستوى «فرعه/منطقته»
     */
    public static function applyDataScope(
        Builder $query,
        User $user,
        string $creatorColumn = 'created_by',
        ?string $branchColumn = 'branch_id',
    ): Builder {
        if (hasPermission('data_scope_all')) {
            return $query;
        }

        if ($branchColumn && hasPermission('data_scope_region')) {
            $branchId = static::resolveBranchId($user);
            if ($branchId) {
                $branchIds = Branch::findCached($branchId)?->descendantBranchIds()->push($branchId) ?? collect([$branchId]);

                return $query->whereIn($branchColumn, $branchIds);
            }
        }

        if ($branchColumn && hasPermission('data_scope_branch')) {
            $branchId = static::resolveBranchId($user);
            if ($branchId) {
                return $query->where($branchColumn, $branchId);
            }
            // لا فرع لهذا المستخدم — يُعامَل كموظف عادي (نطاق «هو فقط» أدناه)
        }

        if (hasPermission('data_scope_department') && $user->employee?->department_id) {
            $userIds = User::whereHas('employee', function ($q) use ($user) {
                $q->where('department_id', $user->employee->department_id);
            })->pluck('id');

            return $query->whereIn($creatorColumn, $userIds);
        }

        if (hasPermission('data_scope_team') && $user->employee) {
            $employeeIds = collect($user->employee->getDescendantIds())->push($user->employee->id);

            $userIds = User::whereIn('employee_id', $employeeIds)->pluck('id');

            return $query->whereIn($creatorColumn, $userIds);
        }

        return $query->where($creatorColumn, $user->id);
    }
}
