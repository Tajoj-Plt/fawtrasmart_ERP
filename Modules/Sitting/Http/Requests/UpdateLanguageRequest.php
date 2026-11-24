<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return canLanguages('edit');
    }

    /**
     * معرّف اللغة الجاري تعديلها — يأتي من مقطع المسار أو من الحقل المخفي.
     */
    protected function languageId()
    {
        return $this->route('id') ?? $this->route('language') ?? $this->input('id');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->languageId();

        return [
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('system_languages', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('system_languages', 'name')->ignore($id)],
            'native_name' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:20', 'alpha_dash', Rule::unique('system_languages', 'locale')->ignore($id)],
            'flag' => ['nullable', 'string', 'max:50'],
            'direction' => ['required', 'in:ltr,rtl'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'date_format' => ['nullable', 'string', 'max:50'],
            'time_format' => ['nullable', 'string', 'max:50'],
            'number_format' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'calendar_type' => ['nullable', 'string', 'max:50'],
            'thousands_separator' => ['nullable', 'string', 'max:5'],
            'decimal_separator' => ['nullable', 'string', 'max:5'],
            'font_family' => ['nullable', 'string', 'max:100'],
            'font_size' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            // JSON أو PHP — والملف لا يُنفَّذ بل يُفكَّك بمحلّل يقبل مصفوفة حرفية فقط
            'translation_file' => ['nullable', 'file', 'max:2048', 'extensions:json,php'],
            'translation_files' => ['nullable', 'array', 'max:20'],
            'translation_files.*' => ['file', 'max:2048', 'extensions:json,php'],
            // جدول المفاتيح يصل حمولةً واحدة تفادياً لحد max_input_vars
            'translations_payload' => ['nullable', 'string', 'max:4000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? strtolower(trim($this->input('code'))) : $this->input('code'),
            'locale' => $this->filled('locale') ? strtolower(trim($this->input('locale'))) : null,
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function attributes()
    {
        return LanguageRequestMessages::attributes();
    }

    public function messages()
    {
        return LanguageRequestMessages::messages();
    }
}
