<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Branch extends Model
{
    use HasFactory;

    // In-memory static cache for request lifecycle optimization
    protected static $branchCache = [];

    protected static $ancestorsCache = [];

    protected static $descendantIdsCache = [];

    protected static $lineageFromRootCache = [];

    protected static $lineagePathLabelCache = [];

    protected static $deepestDescendantCache = [];

    /**
     * هل يحتوي $branchCache على كل الفروع؟ findCached() يملؤه جزئياً (سلف واحد هنا،
     * آخر هناك)، وdescendantBranchIds() كان يعتبر أي محتوى فيه مجموعة كاملة فيحسب
     * الأحفاد من عيّنة ناقصة ويعيد شجرة أصغر من الحقيقة.
     */
    protected static bool $branchCacheComplete = false;

    public static function findCached($id)
    {
        if (! $id) {
            return null;
        }
        if (isset(static::$branchCache[$id])) {
            return static::$branchCache[$id];
        }

        // الذاكرةُ كاملة ولم يوجد فيها: المعرّفُ يتيم (أبٌ حُذف)، فلا استعلام
        if (static::$branchCacheComplete) {
            return null;
        }

        $found = static::withoutGlobalScopes()->find($id);

        /* لا يُخزَّن `null`: `descendantBranchIds()` تمرّ على الذاكرة كلِّها
           وتقرأ `parent_id` من كل عنصر، فعنصرٌ فارغ يُسقط لوحة المبيعات */
        if ($found !== null) {
            static::$branchCache[$id] = $found;
        }

        return $found;
    }

    public static function preloadCache()
    {
        if (! static::$branchCacheComplete) {
            $branches = static::withoutGlobalScopes()->get();
            foreach ($branches as $branch) {
                static::$branchCache[$branch->id] = $branch;
            }
            static::$branchCacheComplete = true;
        }
    }

    /**
     * إفراغ كل ذاكرات الشجرة — يجب استدعاؤها بعد أي تغيير في parent_id داخل نفس
     * الطلب، وإلا استمرت العمليات التالية تقرأ شجرة ما قبل النقل.
     */
    public static function flushTreeCaches(): void
    {
        static::$branchCache = [];
        static::$ancestorsCache = [];
        static::$descendantIdsCache = [];
        static::$lineageFromRootCache = [];
        static::$lineagePathLabelCache = [];
        static::$deepestDescendantCache = [];
        static::$branchCacheComplete = false;
    }

    // تعيين الحقول القابلة للتحديث بشكل جماعي
    protected $fillable = [
        'name',
        'code',
        'phone',
        'mobile',
        'address1',
        'address2',
        'is_main',
        'city',
        'status',
        'region',
        'country',
        'work_hours',
        'description',
        'credit_limit',
        'location',
        'parent_id',
        'directions_id',
        'is_territory',
        'node_type',
        'territory_office_id',
    ];

    public function settings()
    {
        return $this->belongsToMany(BranchSetting::class, 'branch_setting_branch')
            ->withPivot('status');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'branch_id');
    }

    public function neighborhoods()
    {
        return $this->hasMany(Neighborhood::class, 'region_id');
    }

    /**
     * إحداثيات الفرع في جدول locations.
     *
     * الاسم ليس location() عمداً: جدول branches فيه عمود نصّي اسمه `location`،
     * وEloquent يفضّل السمة على العلاقة عند تطابق الاسم — فكانت $branch->location
     * تُرجع العمود (NULL دائماً) بدل السجل، فلا تظهر الإحداثيات المحفوظة في شاشة
     * التعديل ويُنشأ سجل موقع جديد مكرر عند كل حفظ.
     */
    public function geoLocation()
    {
        return $this->hasOne(Location::class, 'branch_id')->latestOfMany();
    }

    /**
     * عقد المناطق (المجموعات) المعلّقة مباشرة تحت هذا الفرع في الشجرة.
     */
    public function regionGroups()
    {
        return $this->hasMany(Region_groub::class, 'parent_id');
    }

    public function parentBranch()
    {
        return $this->belongsTo(Branch::class, 'parent_id');
    }

    public function childBranches()
    {
        return $this->hasMany(Branch::class, 'parent_id');
    }

    /**
     * فروع محاسبية فقط — استبعاد عُقد المناطق (is_territory) المعرّفة كـ Region_groub.
     */
    public function scopeAccountingOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_territory', false)->orWhereNull('is_territory');
        });
    }

    /**
     * كود هرمي متسلسل: جذر 1، 2… | تحت 1 → 11، 12…19، 1A، 1B… | تحت 11 → 111، 112…
     * (لاحقة رقم 1–9 ثم حروف A–Z وما بعدها للإخوة من 10 فما فوق لتجنب التعارض مع 111 = ابن 11).
     */
    public static function generateTreeCode(?int $parentId): string
    {
        if ($parentId === null) {
            $max = static::whereNull('parent_id')
                ->get()
                ->map(fn ($b) => static::parseTreeRootCode((string) $b->code))
                ->filter(fn ($n) => $n !== null)
                ->max();

            return (string) (($max ?? 0) + 1);
        }

        $parent = static::withoutGlobalScopes()->find($parentId);
        if (! $parent) {
            return '1';
        }

        $prefix = static::treeCodePrefixForParent($parent);
        $maxOrdinal = 0;
        foreach (static::withoutGlobalScopes()->where('parent_id', $parentId)->get() as $child) {
            $ordinal = static::suffixAfterPrefix((string) $child->code, $prefix);
            if ($ordinal !== null) {
                $maxOrdinal = max($maxOrdinal, $ordinal);
            }
        }

        $ordinal = $maxOrdinal + 1;
        do {
            $candidate = $prefix.static::ordinalToTreeSuffix($ordinal);
            $exists = static::withoutGlobalScopes()->where('code', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
            $ordinal++;
        } while ($ordinal < 10000);

        throw ValidationException::withMessages([
            'parent_id' => ['تعذّر توليد كود فرع فريد تحت هذا الأب. راجع أكواد الفروع الحالية.'],
        ]);
    }

    /** معاينة الكود التالي عند الإضافة. */
    public static function peekNextTreeCode(?int $parentId): ?string
    {
        try {
            return static::generateTreeCode($parentId);
        } catch (ValidationException $e) {
            return null;
        }
    }

    /**
     * @return int|null رقم الجذر إن كان الكود رقماً صالحاً للشجرة (تجاهل الترقيم القديم مثل 00001)
     */
    public static function parseTreeRootCode(string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        if (preg_match('/^[1-9]\d*$/', $code)) {
            return (int) $code;
        }

        return null;
    }

    /** بادئة كود الأب لربط الأبناء (كود الشجرة أو معرف السجل للقديم) */
    public static function treeCodePrefixForParent(Branch $parent): string
    {
        $c = trim((string) $parent->code);
        if (preg_match('/^[1-9][1-9A-Z]*$/', $c)) {
            return $c;
        }

        return (string) $parent->id;
    }

    /**
     * ترتيب الإخوة بعد بادئة الأب (مثال: code 111 و prefix 11 → 1، code 1A و prefix 1 → 10).
     */
    public static function suffixAfterPrefix(string $code, string $prefix): ?int
    {
        if ($prefix === '' || ! str_starts_with($code, $prefix)) {
            return null;
        }
        $suffix = substr($code, strlen($prefix));
        if ($suffix === '') {
            return null;
        }
        if (preg_match('/^[1-9]$/', $suffix)) {
            return (int) $suffix;
        }
        if (preg_match('/^[A-Z]+$/', $suffix)) {
            return static::letterSuffixToOrdinal($suffix);
        }

        return null;
    }

    /** تحويل لاحقة حرفية (A=10، B=11، …، Z=35، AA=36…) إلى ترتيب إخوة. */
    public static function letterSuffixToOrdinal(string $letters): int
    {
        $value = 0;
        foreach (str_split($letters) as $c) {
            $value = $value * 26 + (ord($c) - ord('A') + 1);
        }

        return 9 + $value;
    }

    /** تحويل ترتيب الإخوة إلى لاحقة (1–9 أرقام، من 10 فما فوق حروف). */
    public static function ordinalToTreeSuffix(int $ordinal): string
    {
        if ($ordinal >= 1 && $ordinal <= 9) {
            return (string) $ordinal;
        }

        $n = $ordinal - 9;
        $letters = '';
        while ($n > 0) {
            $n--;
            $letters = chr(ord('A') + ($n % 26)).$letters;
            $n = intdiv($n, 26);
        }

        return $letters;
    }

    /** إعادة توليد كود هذا الفرع ثم كل الأبناء عمقاً بعد تغيير الأب */
    public function regenerateSubtreeCodes(): void
    {
        $this->code = static::generateTreeCode($this->parent_id);
        $this->saveQuietly();
        foreach ($this->childBranches()->orderBy('id')->get() as $child) {
            $child->regenerateSubtreeCodes();
        }
    }

    // ==================== شجرة الفروع (parent_id) ====================

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /** الفرع الرئيسي (`is_main = true`) — القيمة الافتراضية حين لا يُحدَّد فرع صراحةً. */
    public static function main(): ?self
    {
        return static::where('is_main', true)->first();
    }

    /** معرّف الفرع الرئيسي — أو null إن لم يُعيَّن فرعٌ رئيسي. */
    public static function mainId(): ?int
    {
        return static::main()?->id;
    }

    /**
     * جذر الشجرة: أعلى سلف (حيث parent_id = null).
     */
    public function rootBranch(): Branch
    {
        $node = $this;
        while ($node->parent_id) {
            $parent = static::withoutGlobalScopes()->find($node->parent_id);
            if (! $parent) {
                break;
            }
            $node = $parent;
        }

        return $node;
    }

    /**
     * الأسلاف من الأب المباشر حتى الجذر (بدون العقدة الحالية).
     * الترتيب: أب → جد → … → جذر.
     *
     * @return Collection<int, Branch>
     */
    public function ancestors(): Collection
    {
        if (isset(static::$ancestorsCache[$this->id])) {
            return static::$ancestorsCache[$this->id];
        }

        $list = collect();
        $id = $this->parent_id;
        while ($id) {
            $parent = static::findCached($id);
            if (! $parent) {
                break;
            }
            $list->push($parent);
            $id = $parent->parent_id;
        }

        return static::$ancestorsCache[$this->id] = $list;
    }

    /**
     * المسار من الجذر إلى هذه العقدة (جذر → … → أب → هذه العقدة).
     * مفيد عند اختيار أي ابن: تعرف السلسلة كاملة حتى آخر أب.
     *
     * @return Collection<int, Branch>
     */
    public function lineageFromRoot(): Collection
    {
        if (isset(static::$lineageFromRootCache[$this->id])) {
            return static::$lineageFromRootCache[$this->id];
        }

        return static::$lineageFromRootCache[$this->id] = $this->ancestors()
            ->reverse()
            ->values()
            ->push($this)
            ->values();
    }

    /**
     * أسماء المسار من الجذر إلى العقدة الحالية، جاهزة للعرض.
     */
    public function lineagePathLabel(string $separator = ' ← '): string
    {
        $cacheKey = $this->id.'_'.$separator;
        if (isset(static::$lineagePathLabelCache[$cacheKey])) {
            return static::$lineagePathLabelCache[$cacheKey];
        }

        return static::$lineagePathLabelCache[$cacheKey] = $this->lineageFromRoot()
            ->pluck('name')
            ->implode($separator);
    }

    /**
     * معرفات كل العقد التابعة في الشجرة (كل الأعماق).
     *
     * @return Collection<int, int>
     */
    public function descendantBranchIds(): Collection
    {
        if (isset(static::$descendantIdsCache[$this->id])) {
            return static::$descendantIdsCache[$this->id];
        }

        if (static::$branchCacheComplete) {
            $ids = collect();
            $frontier = collect([$this->id]);
            while ($frontier->isNotEmpty()) {
                $next = collect(static::$branchCache)
                    ->filter(function ($b) use ($frontier) {
                        return $b !== null && in_array($b->parent_id, $frontier->toArray());
                    })
                    ->pluck('id');
                $ids = $ids->merge($next);
                $frontier = $next;
            }

            return static::$descendantIdsCache[$this->id] = $ids->unique()->values();
        }

        $ids = collect();
        $frontier = collect([$this->id]);
        while ($frontier->isNotEmpty()) {
            $next = static::withoutGlobalScopes()
                ->whereIn('parent_id', $frontier)
                ->pluck('id');
            $ids = $ids->merge($next);
            $frontier = $next;
        }

        return static::$descendantIdsCache[$this->id] = $ids->unique()->values();
    }

    /**
     * هل هذه العقدة ضمن شجرة فرع معيّن (هو أو أي من أسلافه = $ancestorId)؟
     */
    public function isUnderBranch(int $ancestorId): bool
    {
        if ((int) $this->id === $ancestorId) {
            return true;
        }
        foreach ($this->ancestors() as $a) {
            if ((int) $a->id === $ancestorId) {
                return true;
            }
        }

        return false;
    }

    /**
     * أعمق عقدة في الشجرة ضمن (هذا الفرع + كل أبنائه) — نفس العمق نفضّل المعرف الأكبر لاستقرار العرض.
     */
    public function deepestDescendantOrSelf(): self
    {
        if (isset(static::$deepestDescendantCache[$this->id])) {
            return static::$deepestDescendantCache[$this->id];
        }

        $candidates = $this->descendantBranchIds()->push($this->id)->unique()->values();
        $best = $this;
        $bestDepth = $this->lineageFromRoot()->count();

        foreach ($candidates as $cid) {
            $node = static::findCached($cid);
            if (! $node) {
                continue;
            }
            $depth = $node->lineageFromRoot()->count();
            if ($depth > $bestDepth || ($depth === $bestDepth && (int) $node->id > (int) $best->id)) {
                $bestDepth = $depth;
                $best = $node;
            }
        }

        return static::$deepestDescendantCache[$this->id] = $best;
    }

    /**
     * فرع المحاسبة المحفوظ على العميل: إن كانت العقدة منطقة نستخدم مكتبها، وإلا العقدة نفسها.
     */
    public function resolveAccountingBranchIdForClient(): int
    {
        if ($this->is_territory) {
            $officeId = $this->territory_office_id;

            return (int) ($officeId ?: $this->rootBranch()->id);
        }

        return (int) $this->id;
    }

    /**
     * عقدة المنطقة لربط الحي (region_id): العقدة إن كانت منطقة، وإلا أقرب سلف منطقة، ثم أول منطقة في الأبناء، ثم أول منطقة تحت المكتب.
     */
    public function resolveTerritoryBranchForNeighborhood(): self
    {
        if ($this->is_territory) {
            return $this;
        }

        foreach ($this->ancestors() as $ancestor) {
            if ($ancestor->is_territory) {
                return $ancestor;
            }
        }

        $queue = collect([$this->id]);
        $visited = [];
        while ($queue->isNotEmpty()) {
            $currentId = (int) $queue->shift();
            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            $children = static::withoutGlobalScopes()
                ->where('parent_id', $currentId)
                ->orderBy('id')
                ->get();

            foreach ($children as $child) {
                if ($child->is_territory) {
                    return $child;
                }
                $queue->push($child->id);
            }
        }

        $fallback = static::withoutGlobalScopes()
            ->where('is_territory', true)
            ->where('territory_office_id', $this->id)
            ->orderBy('id')
            ->first();

        if ($fallback) {
            return $fallback;
        }

        return $this;
    }

    /**
     * نوعُ العقدة الفعليّ — `node_type` أوّلاً، ثمّ استنتاجٌ من `is_territory`
     * للسجلات التي سبقت العمود. نفسُ الاستنتاج المستعمل في شجرة الفروع
     * (`Modules/Branches/resources/views/partials/_branch_tree_node.blade.php`)
     * كي لا يفترق تصنيفُ العقدة بين شاشةٍ وأخرى.
     */
    public function nodeTypeKey(): string
    {
        return $this->node_type ?: ($this->is_territory ? 'group' : 'branch');
    }

    /** هل هذه العقدة وحدةٌ محاسبية تُنسب إليها الفواتير والقيود؟ */
    public function isAccountingBranch(): bool
    {
        return $this->nodeTypeKey() === 'branch';
    }

    /**
     * تسميةُ نوع العقدة وأيقونتُه ولونُه — مصدرٌ واحد تقرأ منه كلُّ شاشة،
     * كي لا تُسمّى العقدةُ «منطقة» هنا و«مجموعة» هناك.
     *
     * @return array{label: string, icon: string, color: string}
     */
    public static function nodeTypeMeta(?string $key): array
    {
        return [
            'branch' => ['label' => __('branches.node_types.branch_short'), 'icon' => 'icon-home', 'color' => 'primary'],
            'region' => ['label' => __('branches.node_types.region'), 'icon' => 'icon-map', 'color' => 'success'],
            'group' => ['label' => __('branches.node_types.group_short'), 'icon' => 'icon-users', 'color' => 'info'],
            'outlet' => ['label' => __('branches.node_types.outlet_short'), 'icon' => 'icon-shopping-bag', 'color' => 'warning'],
        ][$key] ?? ['label' => __('branches.node_types.branch_short'), 'icon' => 'icon-home', 'color' => 'primary'];
    }

    /** تسميةُ نوع هذه العقدة — «فرع» / «منطقة» / «مجموعة» / «منفذ». */
    public function nodeTypeLabel(): string
    {
        return static::nodeTypeMeta($this->nodeTypeKey())['label'];
    }

    /**
     * تسميةُ نوع العقدة **للعرض** — بعد تصحيح `displayNodeType()`، فتُسمّى
     * «منطقة الرياض» المخزَّنةُ مجموعةً «منطقة». للنصوص وحدها لا للمنطق.
     */
    public function displayTypeLabel(): string
    {
        return static::nodeTypeMeta(static::displayNodeType($this))['label'];
    }

    /**
     * نوعُ العقدة **للعرض** — يصحّح ما خُزِّن «مجموعة» وهو منطقةٌ أو منفذ.
     *
     * عُقد المناطق والمنافذ القديمة مخزَّنةٌ `node_type = group` (ستُّ مناطق
     * وثلاثةٌ وثلاثون منفذاً في هذه المنشأة)، فتُعرض «منطقة جيزان — مجموعة»
     * و«منفذ صبيا — مجموعة» والاسمُ نفسُه يقول غير ذلك. فالعرضُ يقرأ:
     *
     *     اسمٌ يبدأ بـ«منطقة»، أو جذرٌ تحته فروع   ← منطقة
     *     اسمٌ يبدأ بـ«منفذ»                        ← منفذ
     *     ما تحت **مجموعةٍ** مباشرةً                 ← منفذ
     *
     * والقاعدةُ الثالثة للمنافذ التي عُدِّل اسمُها فسقطت منه كلمةُ «منفذ»:
     * «الحمراء» و«اشبيليا» تحت «مجموعة الرمال والمونسية…» وأخوهما ما زال
     * اسمُه «منفذ الرمال». الهيكلُ منطقةٌ ← فرعٌ ← مجموعةٌ ← منفذ، فما وقع
     * تحت مجموعةٍ هو منفذُها مهما سُمّي. والأبُ يُفحص بنوعه المخزَّن واسمِه
     * وبأن له أباً — فلا تُحسب المنطقةُ الجذرُ المخزَّنةُ «مجموعة» مجموعةً.
     *
     * ولا يُستنتج «فرع» من الاسم: الفرعُ وحدةٌ لها حساباتها، ومجموعةٌ سُمّيت
     * «فرع…» تحت فرعٍ حقيقي ليست كذلك. ولا يمسّ هذا `nodeTypeKey()` —
     * عليها يقوم `isAccountingBranch()` — فهو للعرض وحده.
     *
     * @param  iterable<int, self>|null  $children  أبناء العقدة إن كانوا في اليد
     */
    public static function displayNodeType(self $node, ?iterable $children = null): string
    {
        $stored = $node->nodeTypeKey();

        if ($stored !== 'group') {
            return $stored;
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $node->name));

        if (str_starts_with($name, 'منطقة')) {
            return 'region';
        }

        if (str_starts_with($name, 'منفذ')) {
            return 'outlet';
        }

        if ($node->parent_id === null && $children !== null) {
            foreach ($children as $child) {
                if (($child->node_type ?? null) === 'branch') {
                    return 'region';
                }
            }
        }

        if ($node->parent_id !== null && static::isPlainGroup(static::parentForDisplay((int) $node->parent_id))) {
            return 'outlet';
        }

        return 'group';
    }

    /**
     * أبُ العقدة لقاعدة العرض — من الذاكرة لا باستعلامٍ لكل صفّ: الشاشاتُ
     * تسأل عن نوع مئةٍ وسبعٍ وستّين عقدةً في الطلب الواحد، فتُحمَّل الشجرةُ
     * مرّةً ثمّ يُقرأ منها.
     */
    private static function parentForDisplay(int $parentId): ?self
    {
        static::preloadCache();

        return static::findCached($parentId);
    }

    /** أهي مجموعةٌ فعلاً — لا منطقةٌ ولا منفذٌ خُزِّنا «مجموعة»، ولا جذر؟ */
    private static function isPlainGroup(?self $node): bool
    {
        if ($node === null || $node->parent_id === null || $node->nodeTypeKey() !== 'group') {
            return false;
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $node->name));

        return ! str_starts_with($name, 'منطقة') && ! str_starts_with($name, 'منفذ');
    }

    /**
     * الفرعُ المحاسبيّ الذي تتبع له هذه العقدة — العقدةُ نفسها إن كانت فرعاً،
     * وإلّا أقربُ سلفٍ من نوع «فرع».
     *
     * وهذا نصُّ السياسة المكتوب في `branches.node_types.explain`: «فرع» وحدةٌ
     * محاسبية تُنسب إليها الفواتير، أمّا البقيّة فوحداتٌ تنظيمية للعملاء تتبع
     * محاسبياً لأقرب فرعٍ فوقها.
     *
     * تختلف عن `resolveAccountingBranchIdForClient()` التي تقرأ `is_territory`
     * وحدَه: المنفذُ (`node_type = outlet`) ليس منطقةً فتعيده تلك الدالّةُ
     * بنفسه — وهو ليس فرعاً محاسبياً. فالاعتمادُ هنا على `node_type`.
     */
    public function resolveAccountingBranchId(): ?int
    {
        if ($this->isAccountingBranch()) {
            return (int) $this->id;
        }

        foreach ($this->ancestors() as $ancestor) {
            if ($ancestor->isAccountingBranch()) {
                return (int) $ancestor->id;
            }
        }

        // لا فرعَ في السلسلة: المكتبُ المرتبط بالمنطقة، ثمّ الفرعُ الرئيسي،
        // ثمّ جذرُ الشجرة — حتى لا يبقى العميلُ بلا فرعٍ محاسبيّ إطلاقاً
        if ($this->territory_office_id) {
            return (int) $this->territory_office_id;
        }

        return static::mainId() ?? (int) $this->rootBranch()->id;
    }

    // العلاقة مع الحسابات
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    // العلاقة مع قيود اليومية (فرع رئيسي)
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    // العلاقة مع القيود الموزعة على هذا الفرع (many-to-many)
    public function distributedJournalEntries()
    {
        return $this->belongsToMany(JournalEntry::class, 'journal_entry_branches', 'branch_id', 'journal_entry_id')
            ->withPivot('allocation_amount', 'allocation_percentage')
            ->withTimestamps();
    }

    // ==================== علاقات نظام النقاط ====================

    /**
     * العلاقة مع إعدادات النقاط الخاصة بالفرع
     */
    public function pointsSettings()
    {
        return $this->hasMany(PointsSetting::class);
    }

    /**
     * العلاقة مع معاملات النقاط
     */
    public function pointsTransactions()
    {
        return $this->hasMany(PointsTransaction::class);
    }

    /**
     * الحصول على إعداد نقاط لعملية معينة
     */
    public function getPointsSettingFor(string $userType, string $actionType): ?PointsSetting
    {
        return $this->pointsSettings()
            ->where('user_type', $userType)
            ->where('action_type', $actionType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * الحصول على إحصائيات النقاط للفرع
     */
    public function getPointsStats(): array
    {
        return [
            'total_client_points' => $this->clients()->sum('points_balance'),
            'total_employee_points' => $this->employees()->sum('points_balance'),
            'transactions_count' => $this->pointsTransactions()->count(),
            'transactions_today' => $this->pointsTransactions()->whereDate('created_at', today())->count(),
        ];
    }

    /**
     * كلُّ العقد، كلُّ واحدةٍ بمسارها من الجذر — مادّةُ قوائم «تابع لـ».
     *
     * **قائمةٌ واحدة بمسارٍ كامل، لا قوائمُ متدرّجة.** الاسمُ وحده لا يقول
     * تحت أيّ عقدةٍ يقع، وأسماءٌ متشابهةٌ تحت آباءٍ مختلفين لا تُميَّز
     * بغيره — و«منفذ صبيا» في هذه المنشأة يتكرّر معناه في أكثر من منطقة.
     * فكلُّ خيارٍ يحمل `منطقة جيزان ← فرع بيش ← منفذ صبيا`، والبحثُ داخل
     * القائمة يختصر الطريق.
     *
     * وهو النمطُ الذي استقرّت عليه شاشةُ العملاء بعد أن كانت قوائمَ
     * متدرّجة، مستوًى لكلّ طبقة — انظر `client::partials.branch-tree-picker`.
     *
     * @return Collection<int, array{id: int, label: string, path: string, type_label: string, depth: int}>
     */
    public static function nodePathOptions(): Collection
    {
        static::preloadCache();

        return static::withoutGlobalScopes()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'node_type', 'is_territory'])
            ->map(function ($node) {
                $path = $node->lineagePathLabel();

                return [
                    'id' => (int) $node->id,
                    'path' => $path,
                    'label' => $path.' — '.$node->displayTypeLabel(),
                    'type_label' => $node->displayTypeLabel(),
                    'depth' => max(0, substr_count($path, '←')),
                ];
            })
            ->sortBy('path', SORT_NATURAL)
            ->values();
    }

    /**
     * مسارُ عقدةٍ جاهزٌ للعرض في صفّ جدول — أو `null` إن لم تُعرف.
     *
     * تُنادى مرّةً لكل صفّ، فتمرّ على `findCached` لا على استعلامٍ جديد.
     */
    public static function pathLabelFor($branchId): ?string
    {
        $node = static::findCached($branchId);

        return $node?->lineagePathLabel();
    }
}
