<?php

namespace Modules\Branches\Services;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingBranch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * محرّك إعدادات الفروع: القراءة، الحفظ، والتوريث.
 *
 * قاعدة الحسم لقيمة أي إعداد في فرع ما — بهذا الترتيب:
 *   1) قيمة مضبوطة للفرع نفسه            → المصدر «own»
 *   2) أقرب أبٍ له قيمة مضبوطة (لو كان الإعداد قابلاً للتوريث) → «inherited»
 *   3) القيمة الافتراضية من الكتالوج      → «default»
 *
 * التوريث هو ما يجعل الشجرة عمليّة: تضبط الإعداد على «الإدارة الإقليمية»
 * فترثه كل مناطقها ومنافذها، وأي فرع يحتاج استثناءً يضبطه لنفسه فيقطع
 * التوريث عنده هو وحده.
 */
class BranchSettingsService
{
    /** ذاكرة الطلب: [branch_id => [setting_key => ['status'=>, 'value'=>]]] */
    protected static array $ownValuesCache = [];

    /** ذاكرة الطلب لتعريفات الإعدادات مفهرسة بالمفتاح. */
    protected static ?Collection $definitionsCache = null;

    // ============================================================
    //  القراءة
    // ============================================================

    /** كل تعريفات الإعدادات النشِطة، مفهرسة بالمفتاح. */
    public static function definitions(): Collection
    {
        if (static::$definitionsCache === null) {
            static::$definitionsCache = BranchSetting::query()
                ->active()
                ->ordered()
                ->get()
                ->keyBy('key');
        }

        return static::$definitionsCache;
    }

    /**
     * قيمة إعداد لفرع، بعد تطبيق التوريث والافتراضي.
     *
     * @return mixed
     */
    public static function value(?int $branchId, string $key, $fallback = null)
    {
        $resolved = static::resolve($branchId, $key);

        if ($resolved === null) {
            return $fallback;
        }

        $value = $resolved['value'];

        // الافتراضي الفارغ لا يُغني عن `$fallback` الذي مرّره المستدعي.
        if ($fallback !== null && ($value === null || $value === '')) {
            return $fallback;
        }

        return $value;
    }

    /** هل الإعداد البولياني مفعّل لهذا الفرع؟ */
    public static function enabled(?int $branchId, string $key): bool
    {
        return (bool) static::value($branchId, $key, false);
    }

    /**
     * الحسم الكامل لإعداد واحد: القيمة ومن أين جاءت.
     *
     * @return array{value: mixed, source: string, source_branch: ?Branch, definition: BranchSetting}|null
     */
    public static function resolve(?int $branchId, string $key): ?array
    {
        $definition = static::definitions()->get($key);

        if (! $definition) {
            return null;
        }

        if ($branchId) {
            $own = static::ownValues((int) $branchId)[$key] ?? null;

            if ($own !== null) {
                return [
                    'value' => static::readRow($definition, $own),
                    'source' => 'own',
                    'source_branch' => null,
                    'definition' => $definition,
                ];
            }

            if ($definition->is_inheritable) {
                $branch = Branch::findCached($branchId);

                foreach ($branch?->ancestors() ?? [] as $ancestor) {
                    $inherited = static::ownValues((int) $ancestor->id)[$key] ?? null;

                    if ($inherited !== null) {
                        return [
                            'value' => static::readRow($definition, $inherited),
                            'source' => 'inherited',
                            'source_branch' => $ancestor,
                            'definition' => $definition,
                        ];
                    }
                }
            }
        }

        return [
            'value' => $definition->defaultValue(),
            'source' => 'default',
            'source_branch' => null,
            'definition' => $definition,
        ];
    }

    /**
     * كل الإعدادات المحسومة لفرع، مجمّعة حسب التصنيف — ما تعرضه الشاشة.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function grouped(?int $branchId): array
    {
        $groups = [];

        foreach (static::definitions() as $definition) {
            $resolved = static::resolve($branchId, $definition->key);

            $groups[$definition->group ?: 'general'][] = [
                'definition' => $definition,
                'key' => $definition->key,
                'name' => $definition->name,
                'description' => $definition->description,
                'type' => $definition->type,
                'value' => $resolved['value'],
                'source' => $resolved['source'],
                'source_branch' => $resolved['source_branch'],
                'default' => $definition->defaultValue(),
            ];
        }

        return $groups;
    }

    /** عدد الإعدادات المضبوطة للفرع نفسه (غير الموروثة وغير الافتراضية). */
    public static function ownCount(int $branchId): int
    {
        return count(static::ownValues($branchId));
    }

    // ============================================================
    //  الكتابة
    // ============================================================

    /**
     * حفظ إعدادات فرع من مدخلات النموذج.
     *
     * يُحفظ فقط ما اختلف عن القيمة المحسومة حالياً، وتُحذف صفوف الإعدادات
     * التي أعادها المستخدم إلى «موروث/افتراضي» — فلا يمتلئ الجدول بصفوف
     * تكرّر ما ترثه الشجرة أصلاً.
     *
     * @param  array<string, mixed>  $input   قيم الحقول كما وصلت من النموذج
     * @param  array<int, string>    $present المفاتيح التي عرضتها الشاشة فعلاً
     * @return array{changed: array<int, string>, cleared: array<int, string>}
     */
    public static function saveForBranch(Branch $branch, array $input, array $present, ?int $userId = null): array
    {
        $changed = [];
        $cleared = [];

        DB::transaction(function () use ($branch, $input, $present, $userId, &$changed, &$cleared) {
            foreach (static::definitions() as $definition) {
                if (! in_array($definition->key, $present, true)) {
                    continue; // إعداد لم تعرضه الشاشة — لا يُلمس
                }

                // «اتبع الأب» — إزالة الضبط الخاص وإعادة الفرع إلى التوريث.
                $inheritFlag = $input['__inherit__' . $definition->key] ?? null;

                if ($inheritFlag) {
                    $deleted = BranchSettingBranch::where('branch_id', $branch->id)
                        ->where('branch_setting_id', $definition->id)
                        ->delete();

                    if ($deleted) {
                        $cleared[] = $definition->key;
                    }

                    continue;
                }

                $before = static::resolve($branch->id, $definition->key)['value'] ?? null;
                $payload = static::rowPayloadFor($definition, $input);

                BranchSettingBranch::updateOrCreate(
                    ['branch_id' => $branch->id, 'branch_setting_id' => $definition->id],
                    $payload + ['updated_by' => $userId],
                );

                if ((string) $before !== (string) static::readRow($definition, (object) $payload)) {
                    $changed[] = $definition->name;
                }
            }
        });

        static::forget($branch->id);

        return ['changed' => $changed, 'cleared' => $cleared];
    }

    /**
     * نسخ إعدادات فرع إلى فروع أخرى.
     *
     * @param  array<int, int>  $targetIds
     * @param  array<int, string>|null  $onlyGroups  تصنيفات محدّدة، أو null لكلّها
     */
    public static function copyTo(Branch $source, array $targetIds, ?array $onlyGroups = null, ?int $userId = null): int
    {
        $sourceRows = static::ownValues((int) $source->id);

        if ($sourceRows === []) {
            return 0;
        }

        $targetIds = array_values(array_diff(array_map('intval', $targetIds), [(int) $source->id]));

        if ($targetIds === []) {
            return 0;
        }

        $copied = 0;

        DB::transaction(function () use ($sourceRows, $targetIds, $onlyGroups, $userId, &$copied) {
            foreach (static::definitions() as $definition) {
                if ($onlyGroups && ! in_array($definition->group, $onlyGroups, true)) {
                    continue;
                }

                $row = $sourceRows[$definition->key] ?? null;

                if ($row === null) {
                    continue;
                }

                foreach ($targetIds as $targetId) {
                    BranchSettingBranch::updateOrCreate(
                        ['branch_id' => $targetId, 'branch_setting_id' => $definition->id],
                        ['status' => (int) $row->status, 'value' => $row->value, 'updated_by' => $userId],
                    );
                    $copied++;
                }
            }
        });

        foreach ($targetIds as $targetId) {
            static::forget($targetId);
        }

        return $copied;
    }

    /**
     * فرض إعدادات فرع على كل الفروع التابعة له.
     *
     * ينفَّذ بحذف الضبط الخاص من التابعين لهذه المفاتيح، لا بنسخ القيم إليهم:
     * الفرع الذي لا ضبط خاص له يرث من أقرب أبٍ مضبوط — وبعد حذف كل الضبط
     * الخاص في السلسلة يصير هذا الفرع هو أقرب مضبوط، فيتبعه الجميع.
     *
     * الفرق عن النسخ ليس شكلياً: النسخ يجمّد القيمة في كل تابع، فتغييرُ الأب
     * لاحقاً لا يصلهم ويظلّون على القيمة القديمة. الحذف يبقيهم مرتبطين بالأب،
     * فأي تعديل مستقبلي يسري عليهم تلقائياً. وهو يترك الجدول أنظف كذلك.
     *
     * @param  array<int, string>  $keys  مفاتيح الإعدادات المراد فرضها
     * @return array{branches: int, rows: int}
     */
    public static function forceOnDescendants(Branch $branch, array $keys): array
    {
        $descendantIds = $branch->descendantBranchIds()->map(fn ($id) => (int) $id)->all();

        if ($descendantIds === [] || $keys === []) {
            return ['branches' => 0, 'rows' => 0];
        }

        $settingIds = static::definitions()
            ->filter(fn ($d) => in_array($d->key, $keys, true))
            ->pluck('id')
            ->all();

        if ($settingIds === []) {
            return ['branches' => 0, 'rows' => 0];
        }

        $affected = BranchSettingBranch::whereIn('branch_id', $descendantIds)
            ->whereIn('branch_setting_id', $settingIds)
            ->distinct()
            ->pluck('branch_id')
            ->count();

        $rows = BranchSettingBranch::whereIn('branch_id', $descendantIds)
            ->whereIn('branch_setting_id', $settingIds)
            ->delete();

        foreach ($descendantIds as $id) {
            static::forget($id);
        }

        return ['branches' => $affected, 'rows' => $rows];
    }

    /**
     * إعادة فرع إلى الوضع الافتراضي: حذف ضبطه الخاص ليعود إلى التوريث.
     *
     * @param  array<int, string>|null  $onlyGroups
     */
    public static function reset(Branch $branch, ?array $onlyGroups = null): int
    {
        $query = BranchSettingBranch::where('branch_id', $branch->id);

        if ($onlyGroups) {
            $ids = static::definitions()
                ->filter(fn ($d) => in_array($d->group, $onlyGroups, true))
                ->pluck('id')
                ->all();

            if ($ids === []) {
                return 0;
            }

            $query->whereIn('branch_setting_id', $ids);
        }

        $deleted = $query->delete();

        static::forget($branch->id);

        return $deleted;
    }

    /** إفراغ ذاكرة الطلب لفرع (أو للجميع). */
    public static function forget(?int $branchId = null): void
    {
        if ($branchId === null) {
            static::$ownValuesCache = [];
            static::$definitionsCache = null;

            return;
        }

        unset(static::$ownValuesCache[$branchId]);
    }

    // ============================================================
    //  داخلي
    // ============================================================

    /**
     * صفوف الضبط الخاصة بفرع، مفهرسة بمفتاح الإعداد.
     *
     * @return array<string, object>
     */
    protected static function ownValues(int $branchId): array
    {
        if (isset(static::$ownValuesCache[$branchId])) {
            return static::$ownValuesCache[$branchId];
        }

        $rows = DB::table('branch_setting_branch as bsb')
            ->join('branch_settings as bs', 'bs.id', '=', 'bsb.branch_setting_id')
            ->where('bsb.branch_id', $branchId)
            ->select('bs.key', 'bsb.status', 'bsb.value')
            ->get()
            ->keyBy('key')
            ->all();

        return static::$ownValuesCache[$branchId] = $rows;
    }

    /** قراءة قيمة صف الجدول الوسيط بنوع الإعداد الصحيح. */
    protected static function readRow(BranchSetting $definition, object $row)
    {
        return $definition->isBoolean()
            ? (bool) ($row->status ?? false)
            : $definition->castValue($row->value ?? null);
    }

    /**
     * تحويل مدخلات النموذج إلى أعمدة الجدول الوسيط.
     *
     * @param  array<string, mixed>  $input
     * @return array{status: int, value: ?string}
     */
    protected static function rowPayloadFor(BranchSetting $definition, array $input): array
    {
        if ($definition->isBoolean()) {
            // مربّع اختيار غير مؤشَّر لا يُرسل أصلاً — غيابه يعني «مغلق».
            return ['status' => ! empty($input[$definition->key]) ? 1 : 0, 'value' => null];
        }

        $raw = $input[$definition->key] ?? null;

        if (is_string($raw)) {
            $raw = trim($raw);
        }

        return ['status' => 1, 'value' => ($raw === null || $raw === '') ? null : (string) $raw];
    }
}
