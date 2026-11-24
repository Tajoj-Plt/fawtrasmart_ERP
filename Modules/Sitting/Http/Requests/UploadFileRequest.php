<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('settings_edit_general_settings');
    }

    public function rules(): array
    {
        $type = $this->input('upload_type', 'logo');

        $maxSize = match ($type) {
            'stamp', 'signature' => 1024, // 1MB
            default => 2048, // 2MB
        };

        return [
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxSize],
            'upload_type' => ['required', 'string', 'in:logo,stamp,signature,avatar,cover'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('settings.validation.file_required'),
            'file.image' => __('settings.validation.file_image'),
            'file.mimes' => __('settings.validation.file_mimes'),
            'file.max' => __('settings.validation.file_max'),
            'upload_type.required' => __('settings.validation.upload_type_required'),
            'upload_type.in' => __('settings.validation.upload_type_in'),
        ];
    }
}
