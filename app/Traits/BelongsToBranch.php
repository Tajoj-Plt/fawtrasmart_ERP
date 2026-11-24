<?php

namespace App\Traits;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مستندٌ يخصّ فرعاً.
 *
 * الفرع يُجمَّد مع المستند وقت إنشائه (`branch_id`) لا يُستنتَج من فرع
 * مُنشئه الحاليّ: الموظّف يبدّل فرعه من الهيدر، والمستند لا يبدّل فرعه معه.
 *
 * الاستعمال في الشاشات:
 *
 *     $query->forBranch(current_branch_id());   // الفرع النشط من الهيدر
 *     $query->forBranch($request->branch);      // فلتر الفرع في الشاشة
 */
trait BelongsToBranch
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * مستندات عقدةٍ في الشجرة — **هي وكلُّ ما تحتها** — ومعها المستندات
     * القديمة التي سبقت عمود الفرع.
     *
     * `null` (المدير على «كل الفروع») يعني بلا تصفية.
     *
     * **كانت مطابقةً تامّة، وهو ما كسر التصفية من الهيدر.** مبدّلُ الفروع
     * لا يعرض إلّا عُقد الجذر (`parent_id` فارغ)، وأكثرُ تلك الجذور مناطقُ
     * لا فروع: «منطقة الرياض» وحدها تحتها ثلاثٌ وأربعون عقدة. والمستندُ
     * يُختم بالعقدة التي أصدرته — منفذاً كانت أو مجموعة — لا بالمنطقة
     * فوقها. فمن اختار المنطقة طابق `branch_id = 2` وحده فلم يُرجَع له شيء،
     * بينما مستنداتُ منطقته كلُّها موجودة تحت أبنائها.
     *
     * وهي قاعدةُ شاشة العملاء نفسُها
     * ({@see \App\Models\Client::scopeInTreeNode()}): «فرع الرياض» تعني
     * «الرياض وما تحتها»، وإلّا أخفت المطابقةُ التامّة عميلَ المنفذ عمّن
     * يفلتر بالفرع الذي فوقه.
     */
    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        $branchId = (int) $branchId;

        if ($branchId <= 0) {
            return $query;
        }

        $ids = static::branchSubtreeIds($branchId);
        $column = $this->qualifyColumn('branch_id');

        return $query->where(function (Builder $scope) use ($column, $ids) {
            $scope->whereIn($column, $ids)->orWhereNull($column);
        });
    }

    /**
     * العقدة وكلُّ أحفادها، بأيّ عمق — محفوظةً لأن هذا النطاق يُنادى على
     * كلّ استعلامِ شاشة.
     *
     * @return array<int, int>
     */
    protected static function branchSubtreeIds(int $branchId): array
    {
        static $memo = [];

        if (isset($memo[$branchId])) {
            return $memo[$branchId];
        }

        $branch = Branch::findCached($branchId);

        if (! $branch) {
            return $memo[$branchId] = [$branchId];
        }

        return $memo[$branchId] = $branch->descendantBranchIds()
            ->push($branchId)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
