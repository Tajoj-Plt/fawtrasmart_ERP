<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProductsRequest extends FormRequest
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
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        /*
            التصنيف اختياريّ **لكل الأنواع** بقرار صاحب النظام (كان إلزامياً
            للمخزني ثم عُمِّم الاختيار) — والعمود nullable منذ ترحيل
            make_products_category_nullable. نوعُ المادة يبقى إلزامياً
            للمخزني/التجميعي وحدهما.
        */
        $isNonStocked = in_array($this->type, ['services', 'digital'], true);

        return [
            'name' => 'required',
            'material_type' => $isNonStocked ? 'nullable' : 'required|in:raw,semi_finished,finished',
            'category_id' => 'nullable|exists:categories,id',
        ];
    }

    public function messages()
    {
        return [
            'required' => 'هذا الحقل مطلوب',
            'material_type.required' => 'يجب اختيار نوع المادة (خام، نصف مصنع، أو منتج نهائي)',
            'material_type.required_unless' => 'يجب اختيار نوع المادة (خام، نصف مصنع، أو منتج نهائي)',
            'material_type.in' => 'القيمة المختارة لنوع المادة غير صالحة',
            'name.required' => 'يرجى إدخال اسم المنتج',
            'category_id.required' => 'يرجى اختيار القسم/التصنيف',
        ];
    }
}
