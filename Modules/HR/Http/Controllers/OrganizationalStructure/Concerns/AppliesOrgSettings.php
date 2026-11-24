<?php

namespace Modules\HR\Http\Controllers\OrganizationalStructure\Concerns;

use App\Support\HrSettings;
use Illuminate\Validation\Rule;

/**
 * قواعدُ التحقّق التي تحكمها «إعدادات الموظفين والهيكل التنظيمي».
 *
 * كانت إلزاميّةُ حقول الهيكل مثبَّتةً في كلّ متحكّمٍ على حِدة: الاسمُ
 * والحالةُ `required` دائماً، والوصفُ `nullable` دائماً، ومديرُ القسم
 * واختصارُه كذلك — ولا فهرسَ فريداً على أيّ اسمٍ في قاعدة البيانات، فـ«محاسب»
 * يُحفَظ مرّتين في القسم نفسه بلا اعتراض.
 *
 * فصارت القاعدةُ تُقرأ من الإعداد، والمنطقُ هنا مرّةً واحدةً لأربع شاشات.
 *
 * `status` و`name` خارجَ ذلك: إلزاميّتُهما بنيويّة — العمودان `NOT NULL`،
 * وسجلٌّ بلا اسمٍ لا يُعرَف في أيّ منتقٍ.
 *
 * @see HrSettings::definitions() مجموعاتُ `org_*`
 */
trait AppliesOrgSettings
{
    /** `required` أو `nullable` حسب مفتاح الإعداد. */
    protected function orgRequired(string $group, string $key): string
    {
        return HrSettings::flag($group, $key) ? 'required' : 'nullable';
    }

    /**
     * قاعدةُ فرادةِ الاسم — أو مصفوفةٌ فارغةٌ إن كانت الفرادةُ مطفأة.
     *
     * تُدمج في قواعد الحقل بـ`array_merge`، فالمطفأةُ لا تضيف شيئاً ولا
     * تحتاج شرطاً عند نداءها.
     *
     * @param  array<string, mixed>  $scope  أعمدةٌ يُقاس التكرارُ داخلها (القسم مثلاً)
     * @return array<int, mixed>
     */
    protected function orgUniqueName(string $table, string $group, string $key, ?int $ignoreId = null, array $scope = []): array
    {
        if (! HrSettings::flag($group, $key)) {
            return [];
        }

        $rule = Rule::unique($table, 'name');

        foreach ($scope as $column => $value) {
            $rule->where($column, $value);
        }

        return [$ignoreId ? $rule->ignore($ignoreId) : $rule];
    }

    /**
     * الحالةُ المختارةُ سلفاً في نموذج الإضافة: `0` نشط و`1` غير نشط —
     * وهي دلالةُ العمود في هذه الجداول الأربعة (معكوسةً عن المتوقَّع).
     */
    protected function orgDefaultStatus(): int
    {
        return HrSettings::flag('org_defaults', 'new_node_active', true) ? 0 : 1;
    }
}
