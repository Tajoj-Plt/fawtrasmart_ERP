<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إنشاء وتعديل قسيمة الراتب (اتفاق الراتب المستمر).
 *
 * **الأساسيُّ ليس إجبارياً بذاته**: موظفٌ بلا عقدٍ يُحسب راتبُه من بنود
 * الاستحقاق. والشرطُ الحقيقيّ — مبلغٌ موجبٌ في القسيمة، أساسياً كان أو
 * بنداً — يُفحص في المتحكّم بعد حساب البنود (`computeSlip()`)، لأنّ مبلغ
 * البند قد يأتي من صيغةٍ لا من حقلٍ في الطلب.
 *
 * والفشلُ هنا يعيد النموذجَ بقيمه (`withInput`) كما يفعل FormRequest دائماً،
 * وصفوفُ البنود الديناميكية تُبنى من `old()` في الشاشة.
 */
class SalarySlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'employee_id' => $creating ? 'required|exists:employees,id' : 'sometimes|exists:employees,id',
            'payroll_id' => $creating ? 'required|exists:payrolls,id' : 'sometimes|exists:payrolls,id',
            'basic_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'frequency' => 'nullable|in:monthly,weekly',
            'scheduled_payment_day' => 'nullable|integer|min:1|max:28',
            'treasury_id' => 'nullable|exists:accounts,id',
            'currency' => 'nullable|string|max:5',
            'notes' => 'nullable|string|max:2000',

            'addition_type' => 'nullable|array',
            'addition_type.*' => 'nullable|integer|exists:salary_items,id',
            'addition_amount' => 'nullable|array',
            'addition_amount.*' => 'nullable|numeric|min:0',
            'addition_calculation_formula' => 'nullable|array',
            'addition_calculation_formula.*' => 'nullable|string|max:255',

            'deduction_type' => 'nullable|array',
            'deduction_type.*' => 'nullable|integer|exists:salary_items,id',
            'deduction_amount' => 'nullable|array',
            'deduction_amount.*' => 'nullable|numeric|min:0',
            'deduction_calculation_formula' => 'nullable|array',
            'deduction_calculation_formula.*' => 'nullable|string|max:255',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'payroll_id.required' => 'يرجى اختيار مسير الرواتب',
            'payroll_id.exists' => 'مسير الرواتب المحدد غير موجود',
            'basic_amount.numeric' => 'الراتب الأساسي يجب أن يكون رقماً',
            'basic_amount.min' => 'الراتب الأساسي لا يكون سالباً',
            'start_date.required' => 'تاريخ بداية القسيمة مطلوب',
            'end_date.after_or_equal' => 'تاريخ نهاية القسيمة يجب أن يكون بعد تاريخ البداية أو مساوياً له',
            'scheduled_payment_day.max' => 'يوم الصرف يجب أن يكون بين 1-28 لضمان وجوده في كل شهر',
            'treasury_id.exists' => 'الخزينة المختارة غير موجودة',
            'addition_type.*.exists' => 'بند الاستحقاق المختار غير موجود',
            'deduction_type.*.exists' => 'بند الخصم المختار غير موجود',
            'addition_amount.*.numeric' => 'مبلغ بند الاستحقاق يجب أن يكون رقماً',
            'addition_amount.*.min' => 'مبلغ بند الاستحقاق لا يكون سالباً',
            'deduction_amount.*.numeric' => 'مبلغ بند الخصم يجب أن يكون رقماً',
            'deduction_amount.*.min' => 'مبلغ بند الخصم لا يكون سالباً',
        ];
    }
}
