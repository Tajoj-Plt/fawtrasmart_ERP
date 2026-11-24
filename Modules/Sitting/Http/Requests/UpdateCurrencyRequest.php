<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id') ?? $this->input('id');

        return [
            'code' => 'required|string|max:10|unique:system_currencies,code,'.$id,
            'numeric_code' => 'nullable|string|max:10',
            'name' => 'required|string|max:100',
            'arabic_name' => 'nullable|string|max:100',
            'english_name' => 'nullable|string|max:100',
            'symbol' => 'required|string|max:20',
            'native_symbol' => 'nullable|string|max:20',
            'exchange_rate' => 'required|numeric|min:0',
            'rate_source' => 'nullable|in:manual,api',
            'country' => 'nullable|string|max:100',
            'flag' => 'nullable|string|max:20',
            'decimal_places' => 'nullable|integer|min:0',
            'rounding_method' => 'nullable|in:round,ceil,floor',
            'thousands_separator' => 'nullable|string|max:5',
            'decimal_separator' => 'nullable|string|max:5',
            'negative_format' => 'nullable|string|max:50',
            'positive_format' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
