<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('settings_edit_general_settings');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'date_format' => ['nullable', 'string', 'in:Y-m-d,d/m/Y,m/d/Y,d-m-Y,d.m.Y'],
            'time_format' => ['nullable', 'string', 'in:12,24'],
            'negative_currency_formats' => ['nullable', 'string', 'max:50'],
            'time_formula' => ['nullable', 'string', 'max:50'],
            'printing_method' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('settings.validation.account_name_required'),
            'name.max' => __('settings.validation.account_name_max'),
            'phone.max' => __('settings.validation.phone_max'),
            'language.max' => __('settings.validation.language_max'),
            'currency.max' => __('settings.validation.currency_max'),
            'timezone.max' => __('settings.validation.timezone_max'),
            'date_format.in' => __('settings.validation.date_format_in'),
            'time_format.in' => __('settings.validation.time_format_in'),
        ];
    }
}
