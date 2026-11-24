<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorehouseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'status' => 'required|in:active,inactive,stopped',
            // nullable لا required: مستودعاتٌ قديمة كثيرة بلا فرعٍ بعد من قبل
            // هذه المهاجرة، ويجب أن يبقى تعديل بياناتها الأخرى ممكناً. التحقّق
            // الفعلي من القيمة المُرسَلة (صلاحية اختيارها) يتم في المتحكّم عبر resolveWriteBranchId
            'branch_id' => 'nullable|integer|exists:branches,id',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'هذا الحقل مطلوب',
            'status.required' => 'حقل الحالة مطلوب',
            'status.in' => 'قيمة الحالة غير صحيحة',
            'branch_id.exists' => 'الفرع المحدَّد غير موجود',
        ];
    }
}