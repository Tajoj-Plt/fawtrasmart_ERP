<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(auth()->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('settings.validation.email_required'),
            'email.email' => __('settings.validation.email_invalid'),
            'email.max' => __('settings.validation.email_max'),
            'email.unique' => __('settings.validation.email_unique'),
        ];
    }
}
