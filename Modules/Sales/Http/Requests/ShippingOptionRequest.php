<?php

namespace Modules\Sales\Http\Requests;

use App\Models\ShippingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقّق من خيار شحن.
 *
 * كان التحقّق مكتوباً داخل `store` و`update` بنسختين متباعدتين، وكلتاهما
 * تخالف الشاشة والقاعدة:
 *
 *  - `tax` كان `numeric|min:0` وهو في الحقيقة رمز معاملة (1 أو 2)، فكان
 *    أي رقم يُقبل ويُخزَّن رمزاً لا وجود له.
 *  - `default_account_id` كان `required` بينما العمود يقبل الفراغ والحقل
 *    في الشاشة بلا نجمة إلزام — فكان الحفظ يفشل بلا سبب ظاهر للمستخدم.
 *  - `description` كان في الشاشة بنجمة إلزام وفي المتحكّم `nullable`.
 */
class ShippingOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    /**
     * ترتيب العرض اختياري في الشاشة: الفراغ يعني «في الآخر» لا صفراً،
     * وإلا تصدّر كلُّ خيار جديد القائمةَ.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('display_order') === null || $this->input('display_order') === '') {
            $this->merge([
                'display_order' => (int) ShippingOption::max('display_order') + 1,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', Rule::in([ShippingOption::TAX_VAT, ShippingOption::TAX_ZERO])],
            'status' => ['required', Rule::in([ShippingOption::STATUS_ACTIVE, ShippingOption::STATUS_INACTIVE])],
            'display_order' => ['required', 'integer', 'min:0'],
            'default_account_id' => ['nullable', 'exists:accounts,id'],
        ];
    }

    /**
     * ملفّ `validation.php` العربي في المشروع نسخةٌ إنجليزية لم تُترجَم، فكانت
     * الرسالة تخرج مركّبةً: قالب إنجليزي واسم حقل عربي في جملة واحدة.
     */
    public function messages(): array
    {
        return [
            'required' => __('sales_settings.errors.required'),
            'numeric' => __('sales_settings.errors.numeric'),
            'integer' => __('sales_settings.errors.integer'),
            'min' => __('sales_settings.errors.min_num'),
            'max' => __('sales_settings.errors.max_len'),
            'in' => __('sales_settings.errors.in'),
            'exists' => __('sales_settings.errors.exists'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('settings.common.name'),
            'description' => __('settings.common.description'),
            'cost' => __('sales_settings.shipping.cost'),
            'tax' => __('sales_settings.shipping.tax_type'),
            'status' => __('settings.common.status'),
            'display_order' => __('sales_settings.shipping.display_order'),
            'default_account_id' => __('sales_settings.shipping.default_account'),
        ];
    }
}
