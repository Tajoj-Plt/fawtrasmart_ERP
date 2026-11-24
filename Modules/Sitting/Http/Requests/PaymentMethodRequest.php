<?php

namespace Modules\Sitting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * التحقّق من طريقة دفع.
 *
 * كان `store` يكتب الطلب حرفياً بلا سطر تحقّق: اسم فارغ كان يصل إلى
 * القاعدة فيرفضه قيد `NOT NULL` بصفحة 500، وحالةٌ بأي نصّ تُحفَظ كما هي.
 */
class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    protected function prepareForValidation(): void
    {
        // مربّعات الاختيار غير المؤشَّرة لا تُرسَل — تُثبَّت هنا فلا تسقط
        // في قيود `NOT NULL` على القاعدة
        $this->merge([
            'is_online' => $this->boolean('is_online'),
            'status' => $this->input('status', 'active'),
        ]);
    }

    public function rules(): array
    {
        // على التحديث يُستثنى الصفّ نفسه من قيد التفرّد، وإلا رفض الحفظ
        // بحجّة أن الرمز مستعمل — وهو مستعمل من الطريقة نفسها
        $id = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:payment_methods,code'.($id ? ','.$id : '')],
            'type' => ['required', 'in:normal,electronic'],
            'status' => ['required', 'in:active,inactive'],
            'is_online' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            // حدّان اختياريان، والأعلى لا يكون دون الأدنى
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_amount'],
            'fee_type' => ['nullable', 'in:fixed,percentage'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],

            // بيانات اعتماد البوابة — اختيارية، فالطرق النقدية لا بوابة لها
            'public_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:2000'],
            // رابط الويب هوك يستقبل نداء البوابة، فلا يقبل إلا http(s)
            'webhook_url' => ['nullable', 'url', 'starts_with:http://,https://', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:2000'],
            'gateway_mode' => ['nullable', 'in:test,live'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('settings.payment.name'),
            'name_en' => __('settings.payment.name_en'),
            'code' => __('settings.common.code'),
            'type' => __('settings.common.type'),
            'status' => __('settings.common.status'),
            'min_amount' => __('settings.payment.min_amount'),
            'max_amount' => __('settings.payment.max_amount'),
            'fee_amount' => __('settings.payment.fee_amount'),
            'public_key' => __('settings.payment.public_key'),
            'secret_key' => __('settings.payment.secret_key'),
            'webhook_url' => __('settings.payment.webhook_url'),
            'webhook_secret' => __('settings.payment.webhook_secret'),
            'gateway_mode' => __('settings.payment.gateway_mode'),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('settings.payment.errors.name_required'),
            'type.in' => __('settings.payment.errors.type_in'),
            'status.in' => __('settings.payment.errors.status_in'),
            'code.unique' => __('settings.payment.errors.code_unique'),
            'max_amount.gte' => __('settings.payment.errors.max_gte'),
            'fee_type.in' => __('settings.payment.errors.fee_type_in'),
            'webhook_url.url' => __('settings.payment.errors.webhook_url'),
            'webhook_url.starts_with' => __('settings.payment.errors.webhook_url'),
            'gateway_mode.in' => __('settings.payment.errors.gateway_mode_in'),
        ];
    }
}
