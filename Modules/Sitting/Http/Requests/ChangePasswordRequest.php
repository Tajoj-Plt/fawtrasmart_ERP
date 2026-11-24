<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => __('settings.validation.current_password_required'),
            'password.required' => __('settings.validation.password_required'),
            'password.min' => __('settings.validation.password_min'),
            'password.confirmed' => __('settings.validation.password_confirmed'),
        ];
    }
}
