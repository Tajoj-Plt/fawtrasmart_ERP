<?php

namespace Modules\Sales\Http\Requests;

use App\Models\ElectronicInvoiceSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقّق من إعدادات الفاتورة الإلكترونية.
 *
 * الشاشتان تحفظان في الصفّ نفسه ولكلٍّ حقولها، فالتحقّق يُبنى على `section`
 * بدل طلبين متطابقين تسعة أعشارهما.
 */
class ElectronicInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    /** أي قسم يُحفَظ: `behavior` سلوك الإرسال، `company` بيانات المنشأة. */
    private function section(): string
    {
        return $this->input('section') === 'company' ? 'company' : 'behavior';
    }

    protected function prepareForValidation(): void
    {
        if ($this->section() === 'behavior') {
            // مربّع غير مؤشَّر لا يُرسَل — بلا التثبيت يعني التعطيلُ
            // «لم يُذكَر» فيبقى الإعداد مفعَّلاً ورسالة النجاح تكذب
            $this->merge([
                'auto_send_after_create' => $this->boolean('auto_send_after_create'),
                'send_on_payment' => $this->boolean('send_on_payment'),
                'attach_qr_to_print' => $this->boolean('attach_qr_to_print'),
            ]);
        }

    }

    public function rules(): array
    {
        if ($this->section() === 'company') {
            // بيانات المنشأة (الاسم التجاري، الرقم الضريبي، السجل، العنوان،
            // الدولة) لا تُدخَل هنا — مصدرها شاشة «إعدادات الحساب». هنا ما
            // يخصّ الفوترة الإلكترونية وحدها.
            return [
                'section' => ['required', 'in:company'],
                'common_name' => ['nullable', 'string', 'max:255'],
                'tax_group_name' => ['required', 'string', 'max:255'],
                // مطلوبٌ في شهادة الهيئة، فلا تسجيل بدونه
                'business_category' => ['required', 'string', 'max:255'],
                // العنوان الوطني: رقم المبنى أربع خانات
                'building_number' => ['required', 'digits:4'],
                'district' => ['required', 'string', 'max:255'],
                // خانتان: ضريبية ثم مبسّطة
                'invoice_types' => ['required', Rule::in(['1100', '1000', '0100'])],
                // تصنيف البنود بلا ضريبة: إمّا الثلاثة معاً وإمّا لا شيء
                'zero_tax_category' => ['nullable', Rule::in(ElectronicInvoiceSetting::ZERO_TAX_CATEGORIES)],
                'zero_tax_reason_code' => ['nullable', 'required_with:zero_tax_category', 'string', 'max:40'],
                'zero_tax_reason' => ['nullable', 'required_with:zero_tax_category', 'string', 'max:255'],
            ];
        }

        return [
            'section' => ['required', 'in:behavior'],
            'environment' => ['required', Rule::in(ElectronicInvoiceSetting::ENVIRONMENTS)],
            'auto_send_after_create' => ['boolean'],
            'send_on_payment' => ['boolean'],
            'attach_qr_to_print' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        $names = [];

        foreach ([
            'common_name', 'tax_group_name', 'business_category',
            'building_number', 'district', 'invoice_types',
            'zero_tax_category', 'zero_tax_reason_code', 'zero_tax_reason',
            'api_base_url', 'client_id', 'client_secret', 'environment',
            'auto_send_after_create', 'send_on_payment', 'attach_qr_to_print',
        ] as $field) {
            $names[$field] = __('sales_settings.electronic.fields.'.$field);
        }

        return $names;
    }

    /** انظر التعليق نفسه في `ShippingOptionRequest::messages()`. */
    public function messages(): array
    {
        return [
            'required' => __('sales_settings.errors.required'),
            'in' => __('sales_settings.errors.in'),
            'boolean' => __('sales_settings.errors.boolean'),
            'max' => __('sales_settings.errors.max_len'),
            'digits' => __('zatca.errors.seller_building_number'),
            'required_with' => __('sales_settings.errors.required'),
            'api_base_url.url' => __('sales_settings.electronic.errors.url'),
            'api_base_url.starts_with' => __('sales_settings.electronic.errors.url'),
        ];
    }

    /** القيم الجاهزة للكتابة — بلا `section` ولا `_token`. */
    public function settings(): array
    {
        $data = $this->validated();
        unset($data['section']);

        // حقلا الاعتماد معمَّيان: الفراغ يعني «لا تغيّر» لا «امحُ»، وإلا
        // فقدَ من فتح الشاشة لتعديل العنوان مفتاحَه السرّي بلا أن يدري
        foreach (['client_id', 'client_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        return $data;
    }
}
