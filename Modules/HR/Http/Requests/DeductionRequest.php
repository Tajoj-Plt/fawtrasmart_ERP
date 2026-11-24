<?php

namespace Modules\HR\Http\Requests;

use App\Models\Deduction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء وتعديل خصمية موظف.
 */
class DeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'deduction_type' => ['required', Rule::in(array_keys(Deduction::types()))],
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'deduction_type.required' => 'يرجى تحديد نوع الخصمية',
            'deduction_type.in' => 'نوع الخصمية غير صالح',
            'amount.required' => 'يرجى إدخال مبلغ الخصمية',
            'amount.min' => 'مبلغ الخصمية يجب أن يكون أكبر من صفر',
            'date.required' => 'يرجى تحديد تاريخ الخصمية',
            'reason.required' => 'يرجى كتابة سبب الخصمية',
        ];
    }
}
