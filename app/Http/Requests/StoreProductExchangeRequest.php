<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductExchangeRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'customer_id' => ['required', 'exists:clients,id'],
            'storehouse_id' => ['required', 'exists:store_houses,id'],
            'quantity_returned' => ['required', 'integer', 'min:1', 'max:999999'],
            'quantity_provided' => ['required', 'integer', 'min:1', 'max:999999'],
            'reason' => ['required', 'in:expired,defective,missing,wrong_item,damaged,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'المنتج مطلوب',
            'product_id.exists' => 'المنتج المحدد غير موجود',
            'customer_id.required' => 'العميل مطلوب',
            'customer_id.exists' => 'العميل المحدد غير موجود',
            'storehouse_id.required' => 'المستودع مطلوب',
            'storehouse_id.exists' => 'المستودع المحدد غير موجود',
            'quantity_returned.required' => 'الكمية المرجعة مطلوبة',
            'quantity_returned.integer' => 'الكمية المرجعة يجب أن تكون رقم صحيح',
            'quantity_returned.min' => 'الكمية المرجعة يجب أن تكون أكثر من صفر',
            'quantity_provided.required' => 'الكمية المعطاة مطلوبة',
            'quantity_provided.integer' => 'الكمية المعطاة يجب أن تكون رقم صحيح',
            'quantity_provided.min' => 'الكمية المعطاة يجب أن تكون أكثر من صفر',
            'reason.required' => 'السبب مطلوب',
            'reason.in' => 'السبب المحدد غير صحيح',
            'notes.string' => 'الملاحظات يجب أن تكون نص',
            'notes.max' => 'الملاحظات لا يجب أن تتجاوز 1000 حرف',
        ];
    }
}
