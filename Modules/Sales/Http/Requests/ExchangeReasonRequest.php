<?php

namespace Modules\Sales\Http\Requests;

use App\Models\ExchangeReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقّق من سبب تبديل.
 *
 * كان مكتوباً داخل `store` و`update` بنسختين، ورسائله عربية مكتوبة في
 * الشفرة لا في ملفّ الترجمة.
 */
class ExchangeReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    /**
     * الترتيب اختياري: الفراغ يعني «في الآخر» لا صفراً، وإلا تصدّر كلُّ
     * سبب جديد القائمةَ. ومربّع «مفعَّل» غير المؤشَّر لا يُرسَل فيُثبَّت هنا.
     */
    protected function prepareForValidation(): void
    {
        $order = $this->input('sort_order');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'sort_order' => ($order === null || $order === '')
                ? (int) ExchangeReason::max('sort_order') + 1
                : $order,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('exchange_reasons', 'name')->ignore($this->route('id')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('settings.common.name'),
            'description' => __('settings.common.description'),
            'sort_order' => __('sales_settings.exchange_reasons.sort_order'),
            'is_active' => __('settings.common.status'),
        ];
    }

    /** انظر التعليق نفسه في `ShippingOptionRequest::messages()`. */
    public function messages(): array
    {
        return [
            'required' => __('sales_settings.errors.required'),
            'integer' => __('sales_settings.errors.integer'),
            'min' => __('sales_settings.errors.min_num'),
            'max' => __('sales_settings.errors.max_len'),
            'boolean' => __('sales_settings.errors.boolean'),
            'name.unique' => __('sales_settings.exchange_reasons.name_unique'),
        ];
    }
}
