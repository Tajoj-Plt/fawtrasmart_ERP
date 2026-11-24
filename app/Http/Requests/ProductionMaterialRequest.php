<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            // بيانات أساسية
            'name' => 'required|string|max:255',
            // الكود لم يعد يُرسَل من الشاشة — يُصدَر في الخادم من إعدادات
            // الترقيم عند الحفظ، ويُحفظ كما هو عند التعديل.
            'product_id' => 'required|exists:products,id',
            'account_id' => 'required|exists:accounts,id',
            /*
                كان `exists:accounts,id` — نسخُ سطرٍ من الذي فوقه. فمسارٌ
                معرّفُه لا يقابل حساباً يُرفض، ومعرّفٌ يقابل حساباً ولا
                يقابل مساراً يمرّ.
            */
            'production_path_id' => 'nullable|exists:production_paths,id',
            // كميةُ القائمة مقامُ كل نسبةٍ في أمر التصنيع — الصفر يُسقط القسمة
            'quantity' => 'required|numeric|gt:0',
            'status' => 'nullable|integer|in:0,1',
            'default' => 'nullable|integer|in:0,1',

            /*
                بنودُ الجداول الأربعة.

                كانت قواعدُها كلُّها معلَّقةً بـ`//`: فيمرّ منتجٌ غير موجود
                وكميةٌ نصّية ومحطّةٌ محذوفة حتى يسقط الحفظ في قاعدة البيانات
                برسالةٍ عامّة. والإجمالياتُ (`*_total` و`last_total_cost`) لا
                قواعدَ لها عمداً — الخادم يحسبها ولا يقرأ ما أُرسل منها.
            */

            // المواد الخام
            'raw_product_id' => 'nullable|array',
            // المنتجُ المصنَّع مكانُه رأسُ القائمة وحده، لا بين موادّه
            'raw_product_id.*' => 'required|integer|exists:products,id|different:product_id',
            'raw_unit_id' => 'nullable|array',
            // معرّفُ وحدة المنتج، أو `category_<id>` لوحدة التصنيف، أو فارغ
            'raw_unit_id.*' => ['nullable', 'regex:/^(\d+|category_\d+)$/'],
            'raw_conversion_factor' => 'nullable|array',
            'raw_conversion_factor.*' => 'nullable|numeric|gt:0',
            'raw_unit_price' => 'nullable|array',
            'raw_unit_price.*' => 'nullable|numeric|min:0',
            'raw_quantity' => 'nullable|array',
            'raw_quantity.*' => 'required|numeric|gt:0',
            'raw_production_stage_id' => 'nullable|array',
            'raw_production_stage_id.*' => 'nullable|exists:production_stages,id',

            // المصروفات
            'expenses_account_id' => 'nullable|array',
            'expenses_account_id.*' => 'required|integer|exists:accounts,id',
            'expenses_cost_type' => 'nullable|array',
            'expenses_cost_type.*' => 'nullable|integer|in:1,2,3',
            'expenses_price' => 'nullable|array',
            'expenses_price.*' => 'required|numeric|min:0',
            'expenses_description' => 'nullable|array',
            'expenses_description.*' => 'nullable|string|max:1000',
            'expenses_production_stage_id' => 'nullable|array',
            'expenses_production_stage_id.*' => 'nullable|exists:production_stages,id',

            // عمليات التصنيع
            'workstation_id' => 'nullable|array',
            'workstation_id.*' => 'required|integer|exists:work_stations,id',
            'manu_cost_type' => 'nullable|array',
            'manu_cost_type.*' => 'nullable|integer|in:1,2,3',
            'operating_time' => 'nullable|array',
            'operating_time.*' => 'required|numeric|gt:0',
            'manu_total_cost' => 'nullable|array',
            'manu_total_cost.*' => 'nullable|numeric|min:0',
            'manu_description' => 'nullable|array',
            'manu_description.*' => 'nullable|string|max:1000',
            'manu_production_stage_id' => 'nullable|array',
            'manu_production_stage_id.*' => 'nullable|exists:production_stages,id',

            // المواد الهالكة
            'end_life_product_id' => 'nullable|array',
            'end_life_product_id.*' => 'required|integer|exists:products,id',
            'end_life_unit_price' => 'nullable|array',
            'end_life_unit_price.*' => 'nullable|numeric|min:0',
            'end_life_quantity' => 'nullable|array',
            'end_life_quantity.*' => 'required|numeric|gt:0',
            'end_life_production_stage_id' => 'nullable|array',
            'end_life_production_stage_id.*' => 'nullable|exists:production_stages,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * رسائلُ البنود تذكر القسمَ ورقمَ الصفّ: `:position` يضعه Laravel
     * برقم الصفّ (من ١) في قواعد المصفوفات. «حقل الكمية مطلوب» بلا موضعٍ
     * لا تدلّ على شيء في شاشةٍ بأربعة جداول.
     */
    public function messages()
    {
        $raw = 'المواد الخام — الصف :position: ';
        $expenses = 'المصروفات — الصف :position: ';
        $operations = 'عمليات التصنيع — الصف :position: ';
        $waste = 'المواد الهالكة — الصف :position: ';

        return [
            'name.required' => 'حقل الاسم مطلوب.',
            'name.max' => 'الاسم أطول من 255 حرفاً.',
            'product_id.required' => 'حقل المنتج مطلوب.',
            'product_id.exists' => 'المنتج المحدد غير موجود.',
            'account_id.required' => 'حقل الحساب مطلوب.',
            'account_id.exists' => 'الحساب المحدد غير موجود.',
            'production_path_id.exists' => 'مسار الإنتاج المحدد غير موجود.',
            'quantity.required' => 'كمية الإنتاج مطلوبة.',
            'quantity.numeric' => 'كمية الإنتاج يجب أن تكون رقماً.',
            'quantity.gt' => 'كمية الإنتاج يجب أن تكون أكبر من صفر.',

            'raw_product_id.*.required' => $raw.'اختر المنتج.',
            'raw_product_id.*.integer' => $raw.'المنتج المحدد غير صالح.',
            'raw_product_id.*.exists' => $raw.'المنتج المحدد غير موجود.',
            'raw_product_id.*.different' => $raw.'المنتج المصنَّع نفسه لا يكون مادةً خاماً في قائمته.',
            'raw_unit_id.*.regex' => $raw.'الوحدة المحددة غير صالحة.',
            'raw_conversion_factor.*.numeric' => $raw.'معامل تحويل الوحدة غير صالح.',
            'raw_conversion_factor.*.gt' => $raw.'معامل تحويل الوحدة غير صالح.',
            'raw_unit_price.*.numeric' => $raw.'سعر الوحدة يجب أن يكون رقماً.',
            'raw_unit_price.*.min' => $raw.'سعر الوحدة لا يكون سالباً.',
            'raw_quantity.*.required' => $raw.'الكمية مطلوبة.',
            'raw_quantity.*.numeric' => $raw.'الكمية يجب أن تكون رقماً.',
            'raw_quantity.*.gt' => $raw.'الكمية يجب أن تكون أكبر من صفر.',
            'raw_production_stage_id.*.exists' => $raw.'المرحلة المحددة غير موجودة.',

            'expenses_account_id.*.required' => $expenses.'اختر الحساب.',
            'expenses_account_id.*.integer' => $expenses.'الحساب المحدد غير صالح.',
            'expenses_account_id.*.exists' => $expenses.'الحساب المحدد غير موجود.',
            'expenses_cost_type.*.integer' => $expenses.'نوع التكلفة غير صالح.',
            'expenses_cost_type.*.in' => $expenses.'نوع التكلفة غير صالح.',
            'expenses_price.*.required' => $expenses.'المبلغ مطلوب.',
            'expenses_price.*.numeric' => $expenses.'المبلغ يجب أن يكون رقماً.',
            'expenses_price.*.min' => $expenses.'المبلغ لا يكون سالباً.',
            'expenses_description.*.max' => $expenses.'الوصف أطول من 1000 حرف.',
            'expenses_production_stage_id.*.exists' => $expenses.'المرحلة المحددة غير موجودة.',

            'workstation_id.*.required' => $operations.'اختر محطة العمل.',
            'workstation_id.*.integer' => $operations.'محطة العمل المحددة غير صالحة.',
            'workstation_id.*.exists' => $operations.'محطة العمل المحددة غير موجودة.',
            'manu_cost_type.*.integer' => $operations.'نوع التكلفة غير صالح.',
            'manu_cost_type.*.in' => $operations.'نوع التكلفة غير صالح.',
            'operating_time.*.required' => $operations.'وقت التشغيل مطلوب.',
            'operating_time.*.numeric' => $operations.'وقت التشغيل يجب أن يكون رقماً.',
            'operating_time.*.gt' => $operations.'وقت التشغيل يجب أن يكون أكبر من صفر.',
            'manu_total_cost.*.numeric' => $operations.'تكلفة الساعة يجب أن تكون رقماً.',
            'manu_total_cost.*.min' => $operations.'تكلفة الساعة لا تكون سالبة.',
            'manu_description.*.max' => $operations.'الوصف أطول من 1000 حرف.',
            'manu_production_stage_id.*.exists' => $operations.'المرحلة المحددة غير موجودة.',

            'end_life_product_id.*.required' => $waste.'اختر المنتج.',
            'end_life_product_id.*.integer' => $waste.'المنتج المحدد غير صالح.',
            'end_life_product_id.*.exists' => $waste.'المنتج المحدد غير موجود.',
            'end_life_unit_price.*.numeric' => $waste.'السعر يجب أن يكون رقماً.',
            'end_life_unit_price.*.min' => $waste.'السعر لا يكون سالباً.',
            'end_life_quantity.*.required' => $waste.'الكمية مطلوبة.',
            'end_life_quantity.*.numeric' => $waste.'الكمية يجب أن تكون رقماً.',
            'end_life_quantity.*.gt' => $waste.'الكمية يجب أن تكون أكبر من صفر.',
            'end_life_production_stage_id.*.exists' => $waste.'المرحلة المحددة غير موجودة.',
        ];
    }
}
