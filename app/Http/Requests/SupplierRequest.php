<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Supplier;
use App\Rules\SaudiPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Purchases\Services\PurchaseSettings;

/**
 * التحقّق من بطاقة المورد — قواعده الثابتة، وما تضيفه «إعدادات المشتريات».
 *
 * كل حقل هنا كان `nullable` بلا استثناء: يُحفَظ مورد بلا اسم ولا جوال ولا
 * رقم ضريبي، ويُحفَظ مورد ثانٍ بنفس الرقم الضريبي فتتشتّت مشترياته على
 * بطاقتين. وليس ذلك خطأً في نفسه — منشآت تُدخل مورّديها على عجل ثم تُكمل —
 * لكنّه كان قراراً مفروضاً لا مضبوطاً.
 *
 * الآن يقرّره قسم «الموردون» في إعدادات المشتريات: الافتراض إبقاء كل شيء
 * اختيارياً كما كان، ومن أراد الإلزام فعّله.
 */
class SupplierRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * الأرقام تُطبَّع قبل التحقّق — انظر {@see SaudiPhone}.
     */
    protected function prepareForValidation(): void
    {
        SaudiPhone::normalizeRequest($this, ['phone', 'mobile'], ['contacts' => ['phone', 'mobile']]);
    }

    public function rules()
    {
        $saudiPhone = $this->saudiPhone();

        return array_merge([
            'trade_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => ['nullable', 'string', 'max:20', $saudiPhone],
            'mobile' => ['nullable', 'string', 'max:20', $saudiPhone],
            'email' => 'nullable|email|max:255',
            'street1' => 'nullable|string|max:255',
            'street2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|size:2',
            'tax_number' => 'nullable|string|max:50',
            'commercial_registration' => 'nullable|string|max:50',

            'currency' => 'nullable|string|size:3',
            // الموظّف المسؤول عن المورّد — نظير `employee_id` في العملاء
            'employee_id' => 'nullable|integer|exists:employees,id',
            'notes' => 'nullable|string',
            // يُعتمَد فقط لمن يملك صلاحية اختيار الفرع — انظر Scopes::canSelectBranch()
            'branch_id' => 'nullable|integer|exists:branches,id',
            'attachments' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'contacts' => 'nullable|array',
            'contacts.*.first_name' => 'nullable|string|max:100',
            'contacts.*.last_name' => 'nullable|string|max:100',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => ['nullable', 'string', 'max:20', $saudiPhone],
            'contacts.*.mobile' => ['nullable', 'string', 'max:20', $saudiPhone],
        ], $this->settingRules());
    }

    /**
     * صيغةُ الرقم السعودي، وأرقامُ المورد المحفوظة قبلها تُقبل ما لم تتغيّر
     * — فلا يُحبس موردٌ قديمٌ عن كلّ تعديل برقمٍ لم يُمسّ.
     */
    private function saudiPhone(): SaudiPhone
    {
        $id = $this->route('id');

        if (! $id) {
            return new SaudiPhone;
        }

        return new SaudiPhone(
            collect(Supplier::whereKey($id)->first(['phone', 'mobile'])?->only(['phone', 'mobile']))
                ->merge(Contact::where('supplier_id', $id)->get(['phone', 'mobile'])
                    ->flatMap(fn ($contact) => [$contact->phone, $contact->mobile]))
                ->filter()
                ->all()
        );
    }

    /**
     * القواعد التي تضيفها إعدادات المشتريات.
     *
     * الإلزام يُضاف بـ`required` والمنع بـ`Rule::unique`. وقاعدة التفرّد
     * تستثني المورد الجاري تعديله بمعرّفه، وإلا رفض المورد نفسَه على نفسه
     * في أول حفظ بلا تغيير.
     *
     * @return array<string, mixed>
     */
    protected function settingRules(): array
    {
        // القاعدة الأساسية لكل حقل تقبله الإعدادات — تُبنى مصفوفةً لا نصّاً
        // لأن `Rule::unique` كائن لا يُدمَج في نصّ مفصول بالشرطات
        $rules = [
            // تُستبدل بها قاعدةُ `rules()` متى مسّتها الإعدادات، فالصيغةُ تُعاد هنا
            'mobile' => ['nullable', 'string', 'max:20', $this->saudiPhone()],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_registration' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ];

        $required = [
            'supplier_require_mobile' => 'mobile',
            'supplier_require_tax_number' => 'tax_number',
            'supplier_require_commercial_registration' => 'commercial_registration',
        ];

        $unique = [
            'supplier_unique_tax_number' => 'tax_number',
            'supplier_unique_mobile' => 'mobile',
            'supplier_unique_email' => 'email',
        ];

        $touched = [];

        foreach ($required as $setting => $field) {
            if (PurchaseSettings::enabled($setting)) {
                $rules[$field][0] = 'required';
                $touched[$field] = true;
            }
        }

        // قاعدة التفرّد تستثني المورد الجاري تعديله بمعرّفه، وإلا رفض المورد
        // نفسَه على نفسه في أول حفظ بلا تغيير
        $id = $this->route('id');

        foreach ($unique as $setting => $field) {
            if (! PurchaseSettings::enabled($setting)) {
                continue;
            }

            $rule = Rule::unique('suppliers', $field);

            if ($id) {
                $rule->ignore($id);
            }

            $rules[$field][] = $rule;
            $touched[$field] = true;
        }

        // ما لم تمسّه الإعدادات يبقى على قاعدته في `rules()` — لا يُعاد كتابته
        return array_intersect_key($rules, $touched);
    }

    /**
     * الاسم مطلوب: التجاري أو الشخصي — أيّهما.
     *
     * لا يُعبَّر عنه بـ`required` على حقل واحد لأن أيّاً منهما يكفي، ولا
     * بـ`required_without` لأن الاسم الشخصي حقلان يلزمان معاً.
     */
    public function withValidator($validator): void
    {
        if (! PurchaseSettings::enabled('supplier_require_name')) {
            return;
        }

        $validator->after(function ($validator) {
            $hasTradeName = filled($this->input('trade_name'));
            $hasPersonName = filled($this->input('first_name')) && filled($this->input('last_name'));

            if (! $hasTradeName && ! $hasPersonName) {
                $validator->errors()->add('trade_name', __('purchase_settings.validation.supplier_name_required'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.required' => __('purchase_settings.validation.supplier_mobile_required'),
            'tax_number.required' => __('purchase_settings.validation.supplier_tax_number_required'),
            'commercial_registration.required' => __('purchase_settings.validation.supplier_commercial_registration_required'),
            'tax_number.unique' => __('purchase_settings.validation.supplier_tax_number_unique'),
            'mobile.unique' => __('purchase_settings.validation.supplier_mobile_unique'),
            'email.unique' => __('purchase_settings.validation.supplier_email_unique'),
        ];
    }
}
