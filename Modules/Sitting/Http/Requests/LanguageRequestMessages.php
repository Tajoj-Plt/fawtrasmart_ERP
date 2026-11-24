<?php

namespace Modules\Sitting\Http\Requests;

/**
 * أسماء الحقول ورسائل التحقق المشتركة بين نموذجي إضافة وتعديل اللغة،
 * حتى لا تتفرّع النصوص بين الشاشتين.
 */
class LanguageRequestMessages
{
    public static function attributes(): array
    {
        return [
            'code' => __('languages.code'),
            'name' => __('languages.name'),
            'native_name' => __('languages.native_name'),
            'locale' => __('languages.locale'),
            'flag' => __('languages.flag'),
            'direction' => __('languages.direction'),
            'sort_order' => __('languages.sort_order'),
            'date_format' => __('languages.date_format'),
            'time_format' => __('languages.time_format'),
            'number_format' => __('languages.number_format'),
            'timezone' => __('languages.timezone'),
            'calendar_type' => __('languages.calendar_type'),
            'thousands_separator' => __('languages.thousands_separator'),
            'decimal_separator' => __('languages.decimal_separator'),
            'font_family' => __('languages.font_family'),
            'font_size' => __('languages.font_size'),
            'color' => __('languages.color'),
            'description' => __('languages.description'),
            'is_active' => __('languages.is_active'),
            'is_default' => __('languages.is_default'),
            'translation_file' => __('languages.translation_file'),
            'translation_files' => __('languages.translation_files'),
            'translation_files.*' => __('languages.translation_file'),
        ];
    }

    public static function messages(): array
    {
        return [
            'code.unique' => __('languages.validation.code_unique'),
            'code.alpha_dash' => __('languages.validation.code_format'),
            'name.unique' => __('languages.validation.name_unique'),
            'locale.unique' => __('languages.validation.locale_unique'),
            'locale.alpha_dash' => __('languages.validation.locale_format'),
            'direction.in' => __('languages.validation.direction_in'),
            'translation_file.extensions' => __('languages.validation.translation_file_type'),
            'translation_file.mimetypes' => __('languages.validation.translation_file_type'),
            'translation_files.*.extensions' => __('languages.validation.translation_file_type'),
            'translation_files.*.mimetypes' => __('languages.validation.translation_file_type'),
            'translation_files.max' => __('languages.validation.translation_files_max'),
        ];
    }
}
