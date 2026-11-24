<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('settings_edit_general_settings');
    }

    public function rules(): array
    {
        return [
            'dark_mode' => ['nullable', 'boolean'],
            'notification_email' => ['nullable', 'boolean'],
            'notification_sms' => ['nullable', 'boolean'],
            'notification_browser' => ['nullable', 'boolean'],
            'backup_auto' => ['nullable', 'boolean'],
            'backup_frequency' => ['nullable', 'string', 'in:daily,weekly,monthly'],
        ];
    }

    public function messages(): array
    {
        return [
            'dark_mode.boolean' => __('settings.validation.dark_mode_boolean'),
            'notification_email.boolean' => __('settings.validation.notify_email_boolean'),
            'notification_sms.boolean' => __('settings.validation.notify_sms_boolean'),
            'notification_browser.boolean' => __('settings.validation.notify_browser_boolean'),
            'backup_auto.boolean' => __('settings.validation.backup_auto_boolean'),
            'backup_frequency.in' => __('settings.validation.backup_frequency_in'),
        ];
    }
}
