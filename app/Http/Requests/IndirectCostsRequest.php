<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndirectCostsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ما يُقبل من الشاشة: الحسابُ والفترةُ ونوعُ التوزيع، و**أيُّ** القيود
     * والأوامر اختيرت — لا مبالغُها.
     *
     * كانت القواعد تقبل `restriction_total` و`manufacturing_price` و`total`
     * كما تصل، فيُكتب مبلغُ القيد باليد ولو زاد على القيد نفسِه. المبالغ
     * كلُّها الآن يحسبها الخادم من الدفتر
     * ({@see \Modules\Manufacturing\Services\IndirectCostAllocator})، وما يصل
     * منها في الطلب يُهمَل.
     */
    public function rules(): array
    {
        return [
            'account_id' => 'required|exists:accounts,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'based_on' => 'required|in:1,2',

            'restriction_id' => 'required|array|min:1',
            'restriction_id.*' => 'required|integer|distinct|exists:journal_entries,id',

            // اختيارُ الأوامر اختياريّ — بلا أوامر يُحسب النصيبُ على أوامر الفترة عند التحميل
            'manufacturing_order_id' => 'nullable|array',
            'manufacturing_order_id.*' => 'nullable|integer|distinct|exists:manufactur_orders,id',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'الحساب',
            'from_date' => 'التاريخ من',
            'to_date' => 'التاريخ إلى',
            'based_on' => 'نوع التوزيع',
            'restriction_id' => 'القيود',
            'restriction_id.*' => 'القيد المحاسبي',
            'manufacturing_order_id' => 'أوامر التصنيع',
            'manufacturing_order_id.*' => 'أمر التصنيع',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'اختر حساب المصروف.',
            'account_id.exists' => 'الحساب المحدد غير موجود.',
            'from_date.required' => 'حدّد «التاريخ من».',
            'from_date.date' => '«التاريخ من» ليس تاريخاً صحيحاً.',
            'to_date.required' => 'حدّد «التاريخ إلى».',
            'to_date.date' => '«التاريخ إلى» ليس تاريخاً صحيحاً.',
            'to_date.after_or_equal' => '«التاريخ إلى» يجب أن يكون بعد «التاريخ من» أو يساويه.',
            'based_on.required' => 'اختر نوع التوزيع.',
            'based_on.in' => 'نوع التوزيع غير صحيح.',

            'restriction_id.required' => 'القيود: اختر قيداً واحداً على الأقل من قيود الحساب في الفترة.',
            'restriction_id.min' => 'القيود: اختر قيداً واحداً على الأقل من قيود الحساب في الفترة.',
            'restriction_id.*.distinct' => 'القيود: القيد نفسه مختار أكثر من مرّة.',
            'restriction_id.*.exists' => 'القيود: أحد القيود المختارة غير موجود.',

            'manufacturing_order_id.*.distinct' => 'أوامر التصنيع: الأمر نفسه مختار أكثر من مرّة.',
            'manufacturing_order_id.*.exists' => 'أوامر التصنيع: أحد الأوامر المختارة غير موجود.',
        ];
    }
}
