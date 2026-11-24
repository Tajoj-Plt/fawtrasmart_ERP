<?php

namespace Modules\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تعديل الملف الشخصي من التطبيق — حقول التواصل والعنوان فقط.
 *
 * حقول التوظيف (المسمّى، القسم، الفرع، الراتب، الحالة، الرقم الوظيفي)
 * ملكُ شاشات HR وحدها ولا تُقبل من الجوال.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // حساب المستخدم
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            // سجلّ الموظف — تواصل
            'mobile_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'personal_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:100'],

            // جهة الطوارئ
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['sometimes', 'nullable', 'string', 'max:100'],

            // العنوان الحالي
            'current_address_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'current_address_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
