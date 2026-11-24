<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب سلفة يقدّمه الموظف.
 */
class AdvanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:500',
            'requested_date' => 'required|date|after_or_equal:today',
            'approver_user_id' => 'required|exists:users,id',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'يرجى إدخال مبلغ السلفة',
            'amount.numeric' => 'مبلغ السلفة يجب أن يكون رقماً',
            'amount.min' => 'مبلغ السلفة يجب أن يكون أكبر من صفر',
            'reason.required' => 'يرجى إدخال سبب طلب السلفة',
            'reason.max' => 'سبب الطلب يجب ألا يتجاوز 500 حرف',
            'requested_date.required' => 'يرجى تحديد تاريخ الطلب',
            'requested_date.after_or_equal' => 'تاريخ الطلب يجب أن يكون اليوم أو بعده',
            'approver_user_id.required' => 'يرجى اختيار الموظف المعتمد',
            'approver_user_id.exists' => 'الموظف المعتمد المحدد غير موجود',
        ];
    }
}
