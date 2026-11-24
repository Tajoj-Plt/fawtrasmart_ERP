<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresAtLeastOneRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * شاشةُ «موظفو سياسة الإجازات» كانت بلا تحقّقٍ أصلاً: `use_rules` تُقرأ
 * كما جاءت، ومعرّفاتُ الموظفين تُمرَّر إلى `sync()` دون أن يُسأل عن
 * وجودها. الصلاحيةُ محروسةٌ في المتحكّم، والمدخلاتُ تُحرس هنا.
 */
class LeavePolicyEmployeesRequest extends FormRequest
{
    use RequiresAtLeastOneRule;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'use_rules' => 'required|in:rules,employees',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title_id' => 'nullable|exists:jop_titles,id',
            'employee_id' => 'required_if:use_rules,employees|array',
            'employee_id.*' => 'integer|exists:employees,id',
            'excluded_employee_id' => 'nullable|array',
            'excluded_employee_id.*' => 'integer|exists:employees,id',
        ];
    }

    /**
     * الشاشةُ عربية، فرسائلُها عربية.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'use_rules.required' => 'يجب اختيار أحد المعايير (القواعد أو الموظفين).',
            'use_rules.in' => 'القيمة المحددة في "المعايير" غير صحيحة.',
            'branch_id.exists' => 'الفرع المختار غير موجود.',
            'department_id.exists' => 'القسم المختار غير موجود.',
            'job_title_id.exists' => 'المسمى الوظيفي المختار غير موجود.',
            'employee_id.required_if' => 'يرجى اختيار موظف واحد على الأقل.',
            'employee_id.*.exists' => 'الموظف المختار غير موجود.',
            'excluded_employee_id.*.exists' => 'الموظف المستبعَد المختار غير موجود.',
        ];
    }

    /**
     * سياسةُ الإجازات لا وردية لها، فلا تُشترط قاعدةُ وردية.
     *
     * @return array<int, string>
     */
    protected function ruleFieldNames(): array
    {
        return ['branch_id', 'department_id', 'job_title_id'];
    }
}
