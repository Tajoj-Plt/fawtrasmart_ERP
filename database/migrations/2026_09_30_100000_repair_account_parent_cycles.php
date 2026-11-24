<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
    إصلاحُ حساباتٍ صار أحدُها أباً لنفسه (أو لأحد آبائه).

    **ما كان**

    تعديلُ الحساب في شجرة الحسابات كان يقبل الحسابَ نفسه أباً له
    (`exists` وحدها). فصار «سلف عبدالمنعم» (3946) و«سلف محمد فالح العتيبي»
    (3948) أبوَين لنفسيهما. وكلُّ تقريرٍ يصعد الشجرة ليحسب مستوى الحساب
    يدور حولهما بلا نهاية حتى تنفد ذاكرة PHP — فتسقط تقارير الحسابات كلُّها
    على الاستضافة بـ«503 Service Unavailable».

    شاشةُ التعديل تمنع ذلك الآن، والنموذجُ يمنعه من أيّ مسار
    (`Account::booted()`). وهذه الهجرةُ تُصلح ما وقع قبلهما.

    **الأبُ الجديد**

    الكودُ يحمل موضعَ الحساب في الشجرة: كودُه يبدأ بكود أبيه. فيُختار أطولُ
    كودٍ موجودٍ يبدأ به كودُ الحساب ويكون **حسابَ تجميع** (له أبناءٌ غيره) —
    لا حساباً طرفياً تُرحَّل عليه قيود. وإن لم يوجد فأطولُ بادئةٍ موجودة،
    وإلا صار الحسابُ جذراً. ولا يُختار أبداً أحدُ أحفاد الحساب، فلا تنشأ حلقةٌ
    جديدة.
*/
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('accounts')->get(['id', 'parent_id', 'code']);

        $parents = [];
        $codes = [];
        $children = [];
        foreach ($rows as $row) {
            $parents[$row->id] = $row->parent_id ? (int) $row->parent_id : null;
            $codes[$row->id] = (string) $row->code;
            if ($row->parent_id) {
                $children[(int) $row->parent_id][] = (int) $row->id;
            }
        }

        $idByCode = [];
        foreach ($codes as $id => $code) {
            if ($code !== '' && ! isset($idByCode[$code])) {
                $idByCode[$code] = $id;
            }
        }

        foreach ($this->cycleBreakPoints($parents) as $accountId) {
            $descendants = $this->descendantsOf($accountId, $children);
            $newParent = $this->parentFromCode($accountId, $codes[$accountId] ?? '', $idByCode, $children, $descendants);

            DB::table('accounts')->where('id', $accountId)->update(['parent_id' => $newParent]);

            Log::warning('repair_account_parent_cycles: account re-parented', [
                'account_id' => $accountId,
                'old_parent_id' => $parents[$accountId],
                'new_parent_id' => $newParent,
            ]);

            $parents[$accountId] = $newParent;
        }
    }

    public function down(): void
    {
        // لا رجوعَ إلى حلقة — الحالةُ السابقة معطوبة
    }

    /**
     * حسابٌ واحد من كلّ حلقة — أصغرُ معرّفٍ فيها. الحلقةُ تنكسر بقطع صلةٍ واحدة.
     *
     * @param  array<int, int|null>  $parents
     * @return int[]
     */
    private function cycleBreakPoints(array $parents): array
    {
        $state = []; // 1 = في المسار الحالي، 2 = انتهى فحصه
        $breaks = [];

        foreach (array_keys($parents) as $start) {
            if (isset($state[$start])) {
                continue;
            }

            $path = [];
            $node = $start;
            // يصعد حتى جذرٍ أو أبٍ غير موجود أو حسابٍ فُحص من قبل أو حلقة
            while ($node !== null && array_key_exists($node, $parents)) {
                $seen = $state[$node] ?? 0;
                if ($seen === 2) {
                    break;
                }
                if ($seen === 1) {
                    $breaks[] = min(array_slice($path, array_search($node, $path, true)));
                    break;
                }
                $state[$node] = 1;
                $path[] = $node;
                $node = $parents[$node];
            }

            foreach ($path as $visited) {
                $state[$visited] = 2;
            }
        }

        return array_values(array_unique($breaks));
    }

    /**
     * @param  array<int, int[]>  $children
     * @return array<int, true>
     */
    private function descendantsOf(int $accountId, array $children): array
    {
        $seen = [$accountId => true];
        $stack = [$accountId];
        while ($stack) {
            foreach ($children[array_pop($stack)] ?? [] as $child) {
                if (! isset($seen[$child])) {
                    $seen[$child] = true;
                    $stack[] = $child;
                }
            }
        }

        return $seen;
    }

    /**
     * @param  array<string, int>  $idByCode
     * @param  array<int, int[]>  $children
     * @param  array<int, true>  $descendants  الحسابُ نفسه وأحفاده — لا يصلحون أباً
     */
    private function parentFromCode(int $accountId, string $code, array $idByCode, array $children, array $descendants): ?int
    {
        $fallback = null;

        for ($length = strlen($code) - 1; $length > 0; $length--) {
            $candidate = $idByCode[substr($code, 0, $length)] ?? null;
            if ($candidate === null || isset($descendants[$candidate])) {
                continue;
            }

            $otherChildren = array_diff($children[$candidate] ?? [], [$accountId]);
            if ($otherChildren) {
                return $candidate;
            }

            $fallback ??= $candidate;
        }

        return $fallback;
    }
};
