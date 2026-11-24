<?php

namespace Modules\Sales\Http\Requests;

use App\Models\SalesInvoiceSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقّق من إعدادات الفواتير وعروض الأسعار.
 *
 * قائمتا الخيارات وأسماء المفاتيح تُقرآن من `SalesInvoiceSetting` لا تُكتَبان
 * هنا: الشاشة والقاعدة والتحقّق تشترك في مصدر واحد، فإضافة إعداد تبقى في
 * موضعين (النموذج والترحيل) لا خمسة.
 */
class SalesInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    /**
     * مربّع اختيار غير مؤشَّر لا يُرسَل أصلاً. بلا هذا التثبيت كان تعطيل
     * إعداد يعني «لم يُذكَر» فيبقى مفعَّلاً كما كان — وهو أسوأ من ألّا
     * يُحفَظ شيء، لأنه يُظهِر رسالة نجاح على تغيير لم يقع.
     */
    protected function prepareForValidation(): void
    {
        $flags = [];

        foreach (SalesInvoiceSetting::FLAGS as $flag) {
            $flags[$flag] = $this->boolean($flag);
        }

        $this->merge($flags);
    }

    public function rules(): array
    {
        /* السقف حقلٌ واحدٌ بوحدة: `max_discount_value` رقمُه،
           و`max_discount_type` وحدتُه — وتفريقُهما على عمودَي القاعدة في
           `settings()`. والسقف الأعلى مائةٌ للنسبة وحدها، فالمبلغ لا حدّ
           لأعلاه: فاتورةٌ بمليون قد يُسمَح فيها بخصم ألف. */
        $isAmount = $this->input('max_discount_type') === 'amount';

        return array_merge(
            [
                'max_discount_type' => ['required', Rule::in(SalesInvoiceSetting::MAX_DISCOUNT_TYPES)],

                /* مصدر مستودع البيع — قائمةٌ لا مفتاح تبديل، فقيمتُها
                   تُفحَص على `WAREHOUSE_SOURCES` لا على `boolean`. */
                'warehouse_source' => ['required', Rule::in(SalesInvoiceSetting::WAREHOUSE_SOURCES)],

                /* مطلوبٌ دائماً لا عند تفعيل المفتاح وحده: الحقل يبقى
                   معروضاً في الشاشة فيُرسَل في الحالين، والقيمة المحفوظة
                   تنتظر متى فُعِّل المفتاح لاحقاً. */
                'max_discount_value' => $isAmount
                    ? ['required', 'numeric', 'min:0']
                    : ['required', 'numeric', 'min:0', 'max:100'],
            ],
            array_fill_keys(SalesInvoiceSetting::FLAGS, ['boolean'])
        );
    }

    /** انظر التعليق نفسه في `ShippingOptionRequest::messages()`. */
    public function messages(): array
    {
        return [
            'required' => __('sales_settings.errors.required'),
            'in' => __('sales_settings.errors.in'),
            'boolean' => __('sales_settings.errors.boolean'),
            'numeric' => __('sales_settings.errors.numeric'),
            'min' => __('sales_settings.errors.min_num'),
            'max' => __('sales_settings.errors.max_num'),
        ];
    }

    public function attributes(): array
    {
        $names = [];

        $keys = array_merge(
            ['max_discount_type', 'max_discount_value', 'warehouse_source'],
            SalesInvoiceSetting::FLAGS
        );

        foreach ($keys as $key) {
            $names[$key] = __('sales_settings.invoice.fields.'.$key);
        }

        return $names;
    }

    /**
     * القيم النظيفة الجاهزة للكتابة — بلا `_token` ولا `_method`.
     *
     * وهنا يُفرَّق حقلُ السقف الواحد على عموديه: الرقم يُكتب في عمود
     * وحدتِه، والعمود الآخر يبقى كما هو — فمن بدّل الوحدة ثمّ عاد وجد
     * رقمه الأوّل. و`maxDiscountFor()`/`maxDiscountAmount()` لا تقرآن
     * إلّا عمود الوحدة المختارة، فلا يسري المهجور.
     */
    public function settings(): array
    {
        $values = $this->only(SalesInvoiceSetting::FLAGS);

        $type = in_array($this->input('max_discount_type'), SalesInvoiceSetting::MAX_DISCOUNT_TYPES, true)
            ? $this->input('max_discount_type')
            : 'percentage';

        $values['max_discount_type'] = $type;

        /* القائمة تُرسَل دائماً، والفحص هنا لِما قد يصل من نموذجٍ قديم
           مخزَّنٍ في المتصفّح: قيمةٌ مجهولة تُردّ إلى الافتراضي بدل أن
           تُكتب في العمود فتُقرأ بعدها `employee` على كل حال. */
        $values['warehouse_source'] = in_array($this->input('warehouse_source'), SalesInvoiceSetting::WAREHOUSE_SOURCES, true)
            ? $this->input('warehouse_source')
            : 'employee';

        $values[$type === 'amount' ? 'max_discount_amount' : 'max_discount_percent']
            = (float) $this->input('max_discount_value', 0);

        return $values;
    }
}
