<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarehousePermitsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
               'permission_type' => 'required|exists:permission_sources,id',
            'permission_date'   => 'required|date',

            /*
                رقم الإذن حقلٌ تلقائي: يُصدره المتحكّم من إعدادات «الترقيم
                المتسلسل» تحت قفل (`issueDocumentNumber`)، ولا يُقرأ من هنا.
                فلا `required` — الحقل في الشاشة للعرض فقط وقد لا يصل أصلاً،
                ولا `unique` — الوارد مُهمَل فلا معنى لفحص تفرّده. ويبقى قيدُ
                الشكل وحده لئلا يُخزَّن ما لا يُخزَّن لو وصل من طريقٍ آخر.
            */
            'number'            => 'nullable|string|max:255',

            /*
                المستودع إلزامي فعلاً: كل الأنواع تتحرّك على مستودع. التحويل
                يستعمل «من/إلى»، وما عداه يستعمل `store_houses_id`.
                كان الحقل `nullable` فيمرّ إذنٌ بلا مستودع ثم يسقط لاحقاً.
            */
            'store_houses_id'   => 'required_unless:permission_type,3|nullable|exists:store_houses,id',
            'from_store_houses_id' => 'required_if:permission_type,3|nullable|exists:store_houses,id',
            'to_store_houses_id'   => 'required_if:permission_type,3|nullable|different:from_store_houses_id|exists:store_houses,id',

            'details'           => 'nullable|string',
            // إذنٌ بإجمالي صفر لا معنى له: لا بضاعة تحرّكت ولا قيمة تُرحَّل
            'grand_total'       => 'required|numeric|min:0.01',
            'attachments'       => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',

            /*
                البنود إلزامية.

                كانت القواعد تصف البنود بـ`quantity.*` وأخواتها فقط — وهي لا
                تعمل إن غاب المصفوف كلّه. فيمرّ إذنٌ بلا صنفٍ واحد وبإجمالي
                صفر، ويُحفظ (ويُعتمد إن كان رصيداً افتتاحياً) وهو فارغ:
                مستندٌ لا يقابله شيء في المخزون ولا في الدفاتر.
            */
            'product_id'        => 'required|array|min:1',
            'quantity'          => 'required|array|min:1',
            'unit_price'        => 'required|array|min:1',
            'total'             => 'required|array|min:1',

            // الكمية كانت `integer` بينما النموذج يقبل كسوراً (step=0.01)
            'quantity.*'        => 'required|numeric|min:0.01',
            'unit_price.*'      => 'required|numeric|min:0',
            'total.*'           => 'required|numeric|min:0',
            'product_id.*'      => 'required|exists:products,id',
        ];
    }

    /**
     * تخصيص رسائل الخطأ.
     */
    public function messages()
    {
        return [
            'permission_type.required'   => 'نوع الإذن مطلوب.',
            'permission_date.required'   => 'التاريخ مطلوب.',

            'store_houses_id.required_unless' => 'يجب اختيار المستودع.',
            'store_houses_id.exists'     => 'المستودع المحدد غير صالح.',
            'from_store_houses_id.required_if' => 'يجب اختيار المستودع المصدر.',
            'to_store_houses_id.required_if'   => 'يجب اختيار المستودع الهدف.',
            'to_store_houses_id.different'     => 'لا يُحوَّل المخزون من مستودعٍ إلى نفسه.',
            'quantity.*.numeric'         => 'الكمية يجب أن تكون رقماً.',
            'quantity.*.min'             => 'الكمية يجب أن تكون أكبر من صفر.',
            'attachments.mimes'          => 'صيغة المرفق غير مدعومة: PDF أو صورة.',
            'attachments.max'            => 'حجم المرفق يتجاوز 5 ميغابايت.',
            'grand_total.required'       => 'يجب إدخال المجموع الإجمالي.',
            'grand_total.min'            => 'إجمالي الإذن يجب أن يكون أكبر من صفر.',
            'product_id.required'        => 'أضف صنفاً واحداً على الأقل إلى الإذن.',
            'product_id.array'           => 'أضف صنفاً واحداً على الأقل إلى الإذن.',
            'product_id.min'             => 'أضف صنفاً واحداً على الأقل إلى الإذن.',
            'quantity.required'          => 'أضف صنفاً واحداً على الأقل بكمّيته.',
            'unit_price.required'        => 'أضف صنفاً واحداً على الأقل بسعره.',
            'total.required'             => 'أضف صنفاً واحداً على الأقل بإجماليه.',
            'quantity.*.required'        => 'الكمية مطلوبة لكل منتج.',
            'unit_price.*.required'      => 'سعر الوحدة مطلوب لكل منتج.',
            'total.*.required'           => 'الإجمالي مطلوب لكل منتج.',
            'product_id.*.exists'        => 'المنتج المحدد غير صالح.',
        ];
    }
}
