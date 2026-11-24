<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('settings_edit_general_settings');
    }

    public function rules(): array
    {
        return [
            'trade_name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_registration' => ['nullable', 'string', 'max:50'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'business_type' => ['nullable', 'in:products,services,both'],
            'currency' => ['nullable', 'string', 'max:10'],
            'attachments' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_name.required' => __('settings.validation.trade_name_required'),
            'trade_name.max' => __('settings.validation.trade_name_max'),
            'business_email.email' => __('settings.validation.business_email'),
            'website.url' => __('settings.validation.website_url'),
            'business_type.in' => __('settings.validation.business_type_in'),
            'tax_number.max' => __('settings.validation.tax_number_max'),
            'commercial_registration.max' => __('settings.validation.commercial_registration_max'),
            'attachments.image' => __('settings.validation.attachment_image'),
            'attachments.mimes' => __('settings.validation.attachment_mimes'),
            'attachments.max' => __('settings.validation.attachment_max'),
        ];
    }
}
