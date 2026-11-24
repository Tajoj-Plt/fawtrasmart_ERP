<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * وضعُ «إختيار القواعد» بلا قاعدةٍ واحدة يعني كلَّ موظفي المنشأة — وهو
 * ما لا يقصده أحدٌ حين يفتح شاشةً ليقيّد بها. فتُشترط قاعدةٌ واحدة على
 * الأقل، وإلا فوضعُ «إختيار الموظفين» هو المقصود.
 *
 * تُستثنى قائمةُ الاستبعاد: هي تضييقٌ بعد الجلب لا قاعدةُ جلب.
 */
trait RequiresAtLeastOneRule
{
    /**
     * أسماءُ حقول القواعد تختلف بين الشاشات، فتُذكر كلُّها.
     *
     * @return array<int, string>
     */
    protected function ruleFieldNames(): array
    {
        return ['branch_id', 'department_id', 'job_title_id', 'shifts_id', 'shift_rule_id'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('use_rules') !== 'rules') {
                return;
            }

            foreach ($this->ruleFieldNames() as $field) {
                if (filled($this->input($field))) {
                    return;
                }
            }

            $validator->errors()->add(
                'use_rules',
                'يجب اختيار قاعدة واحدة على الأقل (فرع أو قسم أو مسمى وظيفي أو وردية)، وإلا فاستعمل «إختيار الموظفين».',
            );
        });
    }
}
