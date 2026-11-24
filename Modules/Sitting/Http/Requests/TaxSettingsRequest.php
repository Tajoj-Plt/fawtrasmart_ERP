<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * التحقّق من جدول الضرائب.
 *
 * كان `updateAll` يقرأ أربع مصفوفات خاماً من الطلب ويكتبها كما هي. جُرِّب
 * فعلياً فمرّت: نسبة `-50`، ونسبة `999`، واسم فارغ حُفظ `NULL`. وما مُنع
 * (`"abc"` في حقل النسبة) مَنَعه نوعُ العمود `double` لا الكود — فظهرت
 * للمسؤول صفحة 500 خام بدل رسالة.
 *
 * ومصفوفات متفاوتة الطول كانت تُنتج `Undefined array key`، لأن الحلقة
 * تدور على `id` وتقرأ البقية بالفهرس نفسه.
 */
class TaxSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    protected function prepareForValidation(): void
    {
        // صفّ فارغ تماماً يُسقَط قبل التحقّق: المستخدم يضيف سطراً ثم
        // يعدل عنه، ولا معنى لأن يُرفض الحفظ كلّه بسببه
        $rows = [];

        foreach ((array) $this->input('id', []) as $index => $id) {
            $name = trim((string) $this->input("name.{$index}", ''));
            $tax = $this->input("tax.{$index}");
            $type = $this->input("type.{$index}");

            if ($id === null && $name === '' && ($tax === null || $tax === '')) {
                continue;
            }

            $rows[] = [
                'id' => $id === null || $id === '' ? null : $id,
                'name' => $name,
                'tax' => $tax,
                'type' => $type,
            ];
        }

        $this->merge(['rows' => $rows]);
    }

    public function rules(): array
    {
        return [
            'rows' => ['present', 'array', 'max:50'],
            'rows.*.id' => ['nullable', 'integer', 'exists:tax_sittings,id'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            // العمود `double`، لكن ضريبةً سالبة أو فوق المئة لا معنى لها
            'rows.*.tax' => ['required', 'numeric', 'min:0', 'max:100'],
            'rows.*.type' => ['required', 'in:included,excluded'],
            /* الضريبة الشاملة الافتراضية للمستندات — معرّفٌ واحدٌ أو فارغ
               (تعطيل). يشير إلى ضريبةٍ محفوظةٍ سلفاً؛ صفٌّ جديدٌ يُعلَّم بعد
               حفظه. */
            'comprehensive_tax_id' => ['nullable', 'integer', 'exists:tax_sittings,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rows.*.name' => __('settings.tax.tax_name'),
            'rows.*.tax' => __('settings.tax.rate'),
            'rows.*.type' => __('settings.common.type'),
        ];
    }

    public function messages(): array
    {
        return [
            'rows.*.name.required' => __('settings.tax.errors.name_required'),
            'rows.*.tax.required' => __('settings.tax.errors.rate_required'),
            'rows.*.tax.numeric' => __('settings.tax.errors.rate_numeric'),
            'rows.*.tax.min' => __('settings.tax.errors.rate_min'),
            'rows.*.tax.max' => __('settings.tax.errors.rate_max'),
            'rows.*.type.in' => __('settings.tax.errors.type_in'),
            'rows.*.id.exists' => __('settings.tax.errors.id_exists'),
        ];
    }
}
