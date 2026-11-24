<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * الصرف اليدوي لعملية راتب معلّقة.
 */
class PayrollRunDisburseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'treasury_id' => 'nullable|exists:accounts,id',
            // «صرفٌ مبكر» مؤكَّد قبل يوم الصرف، والعودةُ إلى الشاشة التي صُرف منها
            'early' => 'nullable|boolean',
            'back' => 'nullable|boolean',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'treasury_id.exists' => 'الخزينة المختارة غير موجودة',
        ];
    }
}
