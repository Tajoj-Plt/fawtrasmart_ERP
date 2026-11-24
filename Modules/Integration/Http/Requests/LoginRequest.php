<?php

namespace Modules\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            // اسم الجهاز يُميّز رمز الوصول في قائمة أجهزة المستخدم
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
