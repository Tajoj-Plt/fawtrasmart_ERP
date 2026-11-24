<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Support\HeaderBranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Manufacturing\Support\BranchTree;

/**
 * فروعُ التصنيع — الوجه التطبيقي للعمود الذي أضافته الهجرة.
 *
 * ── ما كان ───────────────────────────────────────────────────────────
 *
 * هجرةُ `2026_09_02_000005` أضافت `branch_id` إلى أوامر التصنيع وقوائم
 * المواد، وقالت في رأسها حرفياً: «**بنيةٌ فقط — لا عزل**… التطبيقُ الفعلي
 * موضعُه المرحلة ١٠». وهي هذه.
 *
 * فكان مبدّلُ الفروع في الشريط العلوي بلا أثرٍ إطلاقاً على الوحدة: يختار
 * المستخدم فرعاً فتبقى كلُّ شاشاتها كما هي — أوامرُ مصنعٍ آخر بين أوامره،
 * ومحطّاتُ عملٍ لا وجود لها عنده، وقوائمُ موادَّ لخطّ إنتاجٍ لا يشغّله.
 * ولا شيء في الشاشة يميّز الغريبَ من المحلّي، فيُبنى على الأرقام قرارٌ
 * تشغيليّ يخصّ فرعاً غير الذي يقف عليه صاحبُه.
 *
 * ── القاعدة ─────────────────────────────────────────────────────────
 *
 *   الإنشاء ← يُختم المستند بالفرع النشط لحظتَئذ (`current_branch_id()`).
 *   العرض   ← `forBranch()`: عقدةُ الفرع **وكلُّ ما تحتها**، ومعها
 *             الصفوفُ الفارغة (`branch_id = null` تعني «كل الفروع»).
 *   التعديل ← الفرع لا يتغيّر. المستند يخصّ الفرع الذي أصدره، ونقلُه إلى
 *             فرعٍ آخر ينقل معه قيودَه وأذونَه المخزنية — وهي لا تُنقل.
 *
 * و«كل الفروع» (`branch_id` فارغ على المستخدم) تعني بلا تصفية: من لا فرعَ
 * له يرى الجميع، وهو سلوك [\App\Traits\BelongsToBranch::scopeForBranch]
 * نفسُه في العملاء والمخزون والمتجر.
 *
 * ── لماذا سمةٌ لا سطرٌ في كل شاشة ────────────────────────────────────
 *
 * الوحدةُ تسع شاشاتٍ وأربعةُ جداولَ مُفرَّعة، والتصفيةُ المنسيّة في شاشةٍ
 * واحدة تكفي لتسريب بيانات الفروع كلِّها — وأوّلُ ما يُنسى مسارُ AJAX لا
 * شاشة، فلا يلاحظ أحدٌ غياب حارسه. والحسمُ في موضعٍ واحد هنا.
 *
 * وهي **سياقٌ لا صلاحية**: تسري على الجميع بلا استثناء. من اختار فرعاً
 * فقد طلب بياناته هو، ومن أراد الكلَّ اختار «كل الفروع».
 */
trait ScopesToBranch
{
    /** ذاكرةُ الطلب الواحد — تُنادى على كل استعلامِ شاشة. */
    private int|null|false $activeBranchIdCache = false;

    /**
     * الفرعُ النشط في الشريط العلوي — `null` تعني «كل الفروع».
     *
     * يُقرأ عبر [HeaderBranchScope::nodeId()] لا `current_branch_id()`
     * مباشرةً: **الفرعُ الرئيسيّ يعني المنشأةَ كلَّها** — قاعدةُ المبيعات
     * والمشتريات والمخزون نفسُها. كانت القراءةُ المباشرة تحصر مَن يقف على
     * الرئيسيّ في شجرته هو (عقدتان)، فلا يرى ما خُتم على بقيّة الفروع.
     */
    protected function activeBranchId(): ?int
    {
        if ($this->activeBranchIdCache !== false) {
            return $this->activeBranchIdCache;
        }

        return $this->activeBranchIdCache = HeaderBranchScope::nodeId();
    }

    /**
     * تقييدُ استعلامِ Eloquent بالفرع النشط.
     *
     * يشترط أن يستعمل الموديل [\App\Traits\BelongsToBranch] — وهو حال
     * `ManufacturOrders` و`ProductionMaterials` و`WorkStations`
     * و`IndirectCost` و`ProductionPath`.
     */
    protected function scopeToActiveBranch(Builder $query): Builder
    {
        $branchId = $this->activeBranchId();

        return $branchId ? $query->forBranch($branchId) : $query;
    }

    /**
     * العقدةُ النشطة وكلُّ ما تحتها — `null` تعني «كل الفروع».
     *
     * @return array<int, int>|null
     */
    protected function activeBranchIds(): ?array
    {
        $branchId = $this->activeBranchId();

        return $branchId ? $this->activeBranchSubtreeIds($branchId) : null;
    }

    /**
     * تقييدُ استعلامِ موديلٍ يحمل `branch_id` **بلا** سمة
     * [\App\Traits\BelongsToBranch].
     *
     * `StoreHouse` منها: عمودُ الفرع فيه منذ نشأته وعلاقةُ `branch()`
     * معرَّفة، لكن لا نطاقَ `forBranch` عليه. فبدل إضافة السمة إلى موديلٍ
     * تشترك فيه خمسُ وحدات — وهو تغييرٌ يمسّ ما لا يخصّ التصنيع — يُطبَّق
     * الشرطُ نفسُه من هنا: العقدةُ وما تحتها، ومعها الصفوف الفارغة.
     */
    protected function scopeBranchColumn(Builder $query, string $column = 'branch_id'): Builder
    {
        $ids = $this->activeBranchIds();

        if ($ids === null) {
            return $query;
        }

        $qualified = $query->getModel()->qualifyColumn($column);

        return $query->where(function (Builder $scope) use ($qualified, $ids) {
            $scope->whereIn($qualified, $ids)->orWhereNull($qualified);
        });
    }

    /**
     * العقدةُ المطلوبة وكلُّ ما تحتها — لفلترِ فرعٍ يختاره المستخدم داخل
     * نطاقه (يجب فحصُه بـ`isWithinActiveBranch()` أوّلاً).
     *
     * @return array<int, int>
     */
    protected function branchSubtreeFor(int $branchId): array
    {
        return $this->activeBranchSubtreeIds($branchId);
    }

    /**
     * تقييدُ استعلامٍ خام (`DB::table`) بالفرع النشط.
     *
     * التقاريرُ واللوحةُ تبنيان استعلاماتِهما بـ`DB::table` لأجل
     * `groupBy`/`selectRaw`، فلا نطاقَ Eloquent يسري عليها. والقاعدةُ
     * هنا هي هي: العقدةُ وما تحتها، ومعها الصفوف الفارغة.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $column  اسمُ العمود مؤهَّلاً بجدوله (`o.branch_id`)
     */
    protected function scopeRawToActiveBranch($query, string $column)
    {
        $branchId = $this->activeBranchId();

        if (! $branchId) {
            return $query;
        }

        $ids = $this->activeBranchSubtreeIds($branchId);

        return $query->where(function ($scope) use ($column, $ids) {
            $scope->whereIn($column, $ids)->orWhereNull($column);
        });
    }

    /**
     * الفرعُ الذي يُختم به مستندٌ جديد.
     *
     * ما اختاره المستخدم في حقل «تابع لـ» إن كان داخل نطاقه، وإلّا فرعُ
     * الشريط العلويّ كما هو — والرئيسيُّ يُختم رئيسيّاً لا فارغاً.
     * والقيمةُ الواصلة تُفحَص: معرّفٌ خارج النطاق يُهمَل بصمت ويُختم
     * بفرع الشريط.
     */
    protected function documentBranchId($requested = null): ?int
    {
        $requested = (int) ($requested ?? request('branch_id'));

        if ($requested > 0 && $this->isSelectableBranch($requested)) {
            return $requested;
        }

        $header = current_branch_id();

        return $header ? (int) $header : null;
    }

    /**
     * سماتُ الإنشاء الخاصّة بالفرع — تُدمج في مصفوفة `create()`.
     *
     * @return array{branch_id: int|null}
     */
    protected function newDocumentBranchAttributes(): array
    {
        return ['branch_id' => $this->documentBranchId()];
    }

    /**
     * «تابع لـ» عند تعديل بيانٍ مرجعيّ (محطّة، مسار، قائمة مواد).
     *
     * هذه لا قيودَ لها ولا أذونَ مخزنية، فنقلُها بين الجهات لا ينقل أثراً
     * محاسبياً. المختارُ إن كان في النطاق، وإلّا المحفوظُ كما هو.
     */
    protected function editedBranchId($saved): ?int
    {
        $requested = (int) request('branch_id');

        if ($requested > 0 && $this->isSelectableBranch($requested)) {
            return $requested;
        }

        return $saved ? (int) $saved : null;
    }

    /** أتقع هذه العقدةُ ضمن ما يعرضه مُنتقي «تابع لـ» للمستخدم؟ */
    protected function isSelectableBranch(int $branchId): bool
    {
        return BranchTree::contains($branchId);
    }

    /**
     * عُقدُ مُنتقي «تابع لـ» — انظر [BranchTree::nodes()].
     *
     * @return array<int, array{id:int, name:string, parent_id:?int, node_type:string, type_label:string}>
     */
    protected function branchTreeNodes(): array
    {
        return BranchTree::nodes();
    }

    /**
     * ينزع `branch_id` من بيانات التعديل — فرعُ المستند لا يُعدَّل.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withoutBranch(array $attributes): array
    {
        unset($attributes['branch_id']);

        return $attributes;
    }

    /**
     * أيقع هذا المستند في نطاق الفرع النشط؟
     *
     * `null` على المستند تعني «كل الفروع» فتمرّ دائماً — وهي حالُ كلّ ما
     * أُنشئ قبل هذه الهجرة.
     */
    protected function isWithinActiveBranch($branchId): bool
    {
        $active = $this->activeBranchId();

        if (! $active || $branchId === null || $branchId === '') {
            return true;
        }

        return in_array((int) $branchId, $this->activeBranchSubtreeIds($active), true);
    }

    /**
     * يوقف الطلب بـ403 إن كان المستند خارج الفرع النشط — لسياقات القراءة.
     *
     * التصفيةُ في القائمة وحدها ليست عزلاً: `show/{id}` و`edit/{id}` تقبل
     * المعرّفَ من العنوان مباشرةً، فمن رأى رقماً في تقريرٍ أو خمّنه فتح
     * مستندَ فرعٍ آخر كاملاً — ببنوده وتكاليفه — والقائمةُ التي «تحميه»
     * لم تُستدعَ أصلاً.
     */
    protected function authorizeBranchScope($branchId): void
    {
        if (! $this->isWithinActiveBranch($branchId)) {
            abort(403, 'هذا المستند يخصّ فرعاً آخر — بدّل الفرع من الشريط العلوي لعرضه.');
        }
    }

    /**
     * يرمي خطأ تحقّقٍ بدل 403 — لسياقات الكتابة (POST/PUT).
     *
     * المرجعُ الذي يُختار من قائمةٍ مصفّاةٍ بالفرع يجب أن يُفحص عند الحفظ
     * أيضاً: القائمةُ في الصفحة، والمعرّفُ يصل في الطلب.
     */
    protected function assertBranchScope($branchId, string $field = 'branch_id'): void
    {
        if (! $this->isWithinActiveBranch($branchId)) {
            throw ValidationException::withMessages([
                $field => ['هذا العنصر يخصّ فرعاً آخر.'],
            ]);
        }
    }

    /**
     * فروعُ قوائم الاختيار والتصفية — النشطة وما تحتها، أو الكلّ.
     *
     * `status = 0` هو «مفعَّل» في هذا الجدول (نفسُ شرط شاشات المخزون).
     *
     * @return Collection<int, Branch>
     */
    protected function branchFilterOptions(): Collection
    {
        try {
            $query = Branch::where('status', 0)->select('id', 'name')->orderBy('name');

            $branchId = $this->activeBranchId();

            if ($branchId) {
                $query->whereIn('id', $this->activeBranchSubtreeIds($branchId));
            }

            return $query->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * فلترُ الفرع الذي اختاره المستخدم داخل نطاقه — يضيق ولا يوسّع.
     *
     * القيمةُ الواصلة تُفحَص قبل تطبيقها: بدونها يكتب المستخدم معرّفَ فرعٍ
     * خارج نطاقه في العنوان فيقرأ بياناته — وهو التفافٌ على المبدّل نفسِه.
     */
    protected function applyBranchFilter(Builder $query, Request $request, string $key = 'branch_id'): Builder
    {
        $query = $this->scopeToActiveBranch($query);

        if (! $request->filled($key)) {
            return $query;
        }

        $requested = (int) $request->input($key);

        if ($requested <= 0 || ! $this->isWithinActiveBranch($requested)) {
            return $query;
        }

        return $query->forBranch($requested);
    }

    /**
     * العقدةُ وكلُّ أحفادها — محفوظةً لأنها تُنادى على كل استعلام.
     *
     * والتوابعُ معها عمداً: مبدّلُ الفروع لا يعرض إلّا عُقد الجذر، وأكثرُها
     * مناطقُ لا فروع. فمن اختار «منطقة الرياض» ثمّ طوبق `branch_id = 2`
     * وحده لم يُرجَع له شيء، بينما مستنداتُ منطقته كلُّها تحت أبنائها.
     *
     * @return array<int, int>
     */
    private function activeBranchSubtreeIds(int $branchId): array
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
