<?php

namespace Modules\HR\Http\Controllers\Concerns;

use App\Models\Employee;
use Illuminate\Http\Request;
use Modules\HR\Support\BranchTree;

/**
 * شاشاتُ «إختيار المعايير» لها وضعان: `employees` فيُؤخذ من اختير صراحةً،
 * و`rules` فيُجلب الموظفون من الفرع/القسم/المسمى/الوردية ثم يُستبعد منهم من
 * اختير في حقل «الموظفين المستبعدين».
 *
 * كان الحقلان يتشاركان الاسم `employee_id[]` فينقلب الاستبعادُ تضميناً: من
 * تختاره «مستبعداً» هو وحده من يُعالَج، والقواعدُ لا تُستعمل في جلب أحد.
 * صار حقلُ الاستبعاد `excluded_employee_id[]` وحده، والقواعدُ تجلب.
 */
trait ResolvesEmployeesByRules
{
    /**
     * قائمةُ الموظفين المقصودين بالعملية وفق الوضع المختار.
     *
     * @return array<int, int>
     */
    protected function resolveEmployeeIdsFromRequest(Request $request): array
    {
        if ($request->input('use_rules') !== 'rules') {
            return $this->normalizeEmployeeIds($request->input('employee_id'));
        }

        $matching = $this->employeeIdsMatchingRules([
            'branch_id' => $request->input('branch_id'),
            'department_id' => $request->input('department_id'),
            'job_title_id' => $request->input('job_title_id'),
            // اسمُ حقل الوردية يختلف بين الشاشات: دفاتر الحضور `shifts_id`
            // والورديات المتخصصة `shift_rule_id`.
            'shift_id' => $request->input('shifts_id', $request->input('shift_rule_id')),
        ]);

        return array_values(array_diff(
            $matching,
            $this->normalizeEmployeeIds($request->input('excluded_employee_id')),
        ));
    }

    /**
     * الموظفون المطابقون للقواعد وحدها — قبل الاستبعاد.
     *
     * @param  array<string, mixed>  $rules
     * @return array<int, int>
     */
    protected function employeeIdsMatchingRules(array $rules): array
    {
        $query = Employee::query();

        // الفرعُ يشمل ما تحته من مناطق ومجموعات ومنافذ
        BranchTree::apply($query, $rules['branch_id'] ?? null);

        /* «الكل» تصل '' أو 'all' أو 0 أو مصفوفة — و`filled('all')` صادقة، فكانت
           تمرّ إلى `where('department_id', 'all')` فلا يُطابَق أحد. القيمُ الرقميّةُ
           الموجبة وحدها شرط. */
        foreach (['department_id', 'job_title_id', 'shift_id'] as $column) {
            $values = array_values(array_filter(
                array_map(fn ($v) => is_numeric($v) ? (int) $v : 0, (array) ($rules[$column] ?? null)),
                fn ($v) => $v > 0
            ));

            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * ما استُبعد فعلاً في سجلٍ محفوظ: مطابقو قواعده ناقصَ المرتبطين به —
     * إذ لا تُحفظ قائمةُ الاستبعاد، فتُستنتج عند فتح شاشة التعديل.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<int, mixed>  $attachedIds
     * @return array<int, int>
     */
    protected function excludedEmployeeIdsFromRules(array $rules, array $attachedIds): array
    {
        return array_values(array_diff(
            $this->employeeIdsMatchingRules($rules),
            $this->normalizeEmployeeIds($attachedIds),
        ));
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeEmployeeIds(mixed $value): array
    {
        return array_values(array_unique(array_map(
            'intval',
            array_filter((array) $value, static fn ($id) => is_numeric($id)),
        )));
    }
}
