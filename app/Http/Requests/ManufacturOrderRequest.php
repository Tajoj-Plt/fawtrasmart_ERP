<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Manufacturing\Support\OrderFormSettings;

class ManufacturOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        /*
            وجوبُ الحقول الاختيارية من «الإعدادات العامة للتصنيع».

            كان الخادمُ يُلزِم بالموظف وقائمة المواد والمسار والمرحلة دائماً.
            صار كلٌّ منها يتبع إعدادَه: ما أُخفي من الشاشة لا يُطلب هنا.
        */
        $stage = OrderFormSettings::presence('stage');

        $rules = [
            'name' => 'required|string|max:255',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'account_id' => OrderFormSettings::presence('account_id').'|exists:accounts,id',
            'employee_id' => OrderFormSettings::presence('employee_id').'|exists:employees,id',
            'client_id' => OrderFormSettings::presence('client_id').'|exists:clients,id',
            'product_id' => 'required|exists:products,id',
            // كسورُ الكمية مقبولة (٠٫٥ كجم، ٢٫٢٥ لتر) — كان `min:1` يردّها
            'quantity' => 'required|numeric|min:0.0001',
            // «تابع لـ» — يُفحص نطاقُه في المتحكّم
            'branch_id' => 'nullable|integer',
            // مستودع المواد — اختياري؛ اختيارُه يحجز المواد فيه عند الحفظ
            'storehouse_id' => OrderFormSettings::presence('storehouse_id').'|exists:store_houses,id',
            'production_material_id' => OrderFormSettings::presence('production_material_id').'|exists:production_materials,id',
            'production_path_id' => OrderFormSettings::presence('production_path_id').'|exists:production_paths,id',
            /*
                حقول الإجماليات لم تعد تُقبل من الطلب.

                `last_total_cost` و`raw_total` و`expenses_total` و`manu_total`
                و`end_life_total` تُحسب كلّها في الخادم عبر `BuildsOrderItems`
                من الكميات والأسعار. فقواعدها هنا لا تحرس شيئاً، وقاعدة
                `raw_total => required` كانت تُسقط الحفظ بعد أن رُفعت الحقول
                من الإرسال.

                والمحروس فعلاً هو المدخلات التي يُبنى منها الحساب: الكمية
                والسعر ووقت التشغيل — وقواعدها باقية أدناه.
            */
            'created_by' => 'nullable|exists:users,id',
            'updated_by' => 'nullable|exists:users,id',

            // قواعد للحقول الديناميكية - المواد الخام
            'raw_product_id' => 'required|array|min:1',
            'raw_product_id.*' => 'required|exists:products,id',
            'raw_production_stage_id' => 'nullable|array',
            'raw_production_stage_id.*' => $stage.'|exists:production_stages,id',
            'raw_quantity' => 'required|array|min:1',
            'raw_quantity.*' => 'required|numeric|min:0.0001',
            'raw_unit_price' => 'nullable|array',
            'raw_unit_price.*' => 'nullable|numeric|min:0',
            'raw_unit_name' => 'nullable|array',
            'raw_unit_name.*' => 'nullable|string',

            // قواعد للمصروفات (اختيارية)
            'expenses_account_id' => 'nullable|array',
            'expenses_account_id.*' => 'nullable|exists:accounts,id',
            'expenses_cost_type' => 'nullable|array',
            'expenses_production_stage_id' => 'nullable|array',
            'expenses_production_stage_id.*' => 'nullable|exists:production_stages,id',
            'expenses_price' => 'nullable|array',
            'expenses_price.*' => 'nullable|numeric|min:0',
            'expenses_description' => 'nullable|array',

            // المصروفات غير المباشرة — يُختار المستند والمبلغُ يحسبه الخادم
            'indirect_cost_id' => 'nullable|array',
            'indirect_cost_id.*' => 'nullable|exists:indirect_costs,id',
            'indirect_stage_id' => 'nullable|array',
            'indirect_stage_id.*' => 'nullable|exists:production_stages,id',

            // قواعد للتصنيع (اختيارية)
            'workstation_id' => 'nullable|array',
            'workstation_id.*' => 'nullable|exists:work_stations,id',
            'operating_time' => 'nullable|array',
            'operating_time.*' => 'nullable|numeric|min:0',
            'manu_production_stage_id' => 'nullable|array',
            'manu_production_stage_id.*' => 'nullable|exists:production_stages,id',
            'manu_cost_type' => 'nullable|array',
            'manu_total_cost' => 'nullable|array',
            'manu_total_cost.*' => 'nullable|numeric|min:0',
            'manu_description' => 'nullable|array',

            // قواعد للمواد الهالكة (اختيارية)
            'end_life_product_id' => 'nullable|array',
            'end_life_product_id.*' => 'nullable|exists:products,id',
            'end_life_unit_price' => 'nullable|array',
            'end_life_unit_price.*' => 'nullable|numeric|min:0',
            'end_life_production_stage_id' => 'nullable|array',
            'end_life_production_stage_id.*' => 'nullable|exists:production_stages,id',
            'end_life_quantity' => 'nullable|array',
            'end_life_quantity.*' => 'nullable|numeric|min:0',
        ];

        /*
            الكود لم يعد يُرسَل من الشاشة.

            كان حقلاً قابلاً للتحرير، وكانت هذه القاعدة تفرضه `required`
            و`unique`. وصار يُصدَر في الخادم عند الحفظ عبر
            `issueDocumentNumber()` — من إعدادات الترقيم المتسلسل، داخل معاملة
            بقفل تتخطّى المحجوز — ويُحفظ كما هو عند التعديل عبر
            `keepDocumentNumber()`.

            فحذف القاعدة لازم: إبقاؤها `required` يُسقط كل حفظ بحقلٍ لا تُرسله
            الشاشة أصلاً. والتفرّد يضمنه مُصدِر الأرقام لا التحقّق.
        */

        return $rules;
    }

    /**
     * أسماءُ الحقول بالعربية — لأيّ رسالةٍ لا نصَّ خاصّاً لها أدناه.
     */
    public function attributes()
    {
        return [
            'name' => 'اسم الأمر',
            'from_date' => 'من تاريخ',
            'to_date' => 'إلى تاريخ',
            'product_id' => 'المنتج المصنَّع',
            'quantity' => 'الكمية',
            'employee_id' => 'الموظف المسؤول',
            'client_id' => 'العميل',
            'account_id' => 'الحساب',
            'storehouse_id' => 'مستودع المواد',
            'production_material_id' => 'قائمة المواد',
            'production_path_id' => 'مسار الإنتاج',
            'raw_product_id.*' => 'المادة الخام',
            'raw_production_stage_id.*' => 'مرحلة المادة الخام',
            'raw_quantity.*' => 'كمية المادة الخام',
            'raw_unit_price.*' => 'سعر المادة الخام',
            'expenses_account_id.*' => 'حساب المصروف',
            'expenses_production_stage_id.*' => 'مرحلة المصروف',
            'expenses_price.*' => 'مبلغ المصروف',
            'workstation_id.*' => 'محطة العمل',
            'operating_time.*' => 'وقت التشغيل',
            'manu_production_stage_id.*' => 'مرحلة العملية',
            'manu_total_cost.*' => 'تكلفة الساعة',
            'indirect_cost_id.*' => 'التكلفة غير المباشرة',
            'indirect_stage_id.*' => 'مرحلة المصروف غير المباشر',
            'end_life_product_id.*' => 'المادة الهالكة',
            'end_life_production_stage_id.*' => 'مرحلة المادة الهالكة',
            'end_life_unit_price.*' => 'سعر المادة الهالكة',
            'end_life_quantity.*' => 'كمية المادة الهالكة',
        ];
    }

    /**
     * كلُّ رسالةٍ تقول **أين** الخطأ: القسمُ ثمّ رقمُ الصفّ (`:position`).
     *
     * كانت رسائلُ الصفوف بلا موضع («يجب تحديد المادة الخام») فلا يُعرف أيُّ
     * صفٍّ من عشرة، وكان أكثرُ الحقول بلا رسالةٍ أصلاً فتخرج باسم الحقل
     * الإنجليزي (`expenses_account_id.2`).
     */
    public function messages()
    {
        return [
            'name.required' => 'بيانات الأمر: اسم الأمر مطلوب.',
            'from_date.required' => 'بيانات الأمر: «من تاريخ» مطلوب.',
            'to_date.required' => 'بيانات الأمر: «إلى تاريخ» مطلوب.',
            'to_date.after_or_equal' => 'بيانات الأمر: «إلى تاريخ» يجب أن يكون بعد «من تاريخ» أو يساويه.',
            'account_id.exists' => 'بيانات الأمر: الحساب المحدد غير موجود.',
            'employee_id.required' => 'بيانات الأمر: الموظف المسؤول مطلوب.',
            'employee_id.exists' => 'بيانات الأمر: الموظف المحدد غير موجود.',
            'client_id.required' => 'بيانات الأمر: العميل مطلوب.',
            'account_id.required' => 'بيانات الأمر: الحساب مطلوب.',
            'storehouse_id.required' => 'بيانات الأمر: مستودع المواد مطلوب.',
            'client_id.exists' => 'بيانات الأمر: العميل المحدد غير موجود.',
            'product_id.required' => 'بيانات الأمر: المنتج المصنَّع مطلوب.',
            'product_id.exists' => 'بيانات الأمر: المنتج المحدد غير موجود.',
            'quantity.required' => 'بيانات الأمر: الكمية مطلوبة.',
            'quantity.numeric' => 'بيانات الأمر: الكمية يجب أن تكون رقماً.',
            'quantity.min' => 'بيانات الأمر: الكمية يجب أن تكون أكبر من صفر.',
            'storehouse_id.exists' => 'بيانات الأمر: مستودع المواد المحدد غير موجود.',
            'production_material_id.required' => 'بيانات الأمر: قائمة المواد مطلوبة.',
            'production_material_id.exists' => 'بيانات الأمر: قائمة المواد المحددة غير موجودة.',
            'production_path_id.required' => 'بيانات الأمر: مسار الإنتاج مطلوب.',
            'production_path_id.exists' => 'بيانات الأمر: مسار الإنتاج المحدد غير موجود.',

            // المواد الخام
            'raw_product_id.required' => 'المواد الخام: أضف مادة خام واحدة على الأقل.',
            'raw_product_id.min' => 'المواد الخام: أضف مادة خام واحدة على الأقل.',
            'raw_product_id.*.required' => 'المواد الخام — الصف :position: اختر المنتج.',
            'raw_product_id.*.exists' => 'المواد الخام — الصف :position: المنتج المختار غير موجود.',
            'raw_production_stage_id.required' => 'المواد الخام: اختر المرحلة لكل مادة.',
            'raw_production_stage_id.*.required' => 'المواد الخام — الصف :position: اختر المرحلة (أضف مراحل لمسار الإنتاج إن كانت القائمة فارغة).',
            'raw_production_stage_id.*.exists' => 'المواد الخام — الصف :position: المرحلة المختارة غير موجودة.',
            'raw_quantity.required' => 'المواد الخام: أدخل الكمية لكل مادة.',
            'raw_quantity.*.required' => 'المواد الخام — الصف :position: الكمية مطلوبة.',
            'raw_quantity.*.numeric' => 'المواد الخام — الصف :position: الكمية يجب أن تكون رقماً.',
            'raw_quantity.*.min' => 'المواد الخام — الصف :position: الكمية يجب أن تكون أكبر من صفر.',
            'raw_unit_price.required' => 'المواد الخام: أدخل سعر الوحدة لكل مادة (يُقبل صفر).',
            'raw_unit_price.*.numeric' => 'المواد الخام — الصف :position: سعر الوحدة يجب أن يكون رقماً.',
            'raw_unit_price.*.min' => 'المواد الخام — الصف :position: سعر الوحدة لا يكون سالباً.',

            // المصروفات
            'expenses_account_id.*.exists' => 'المصروفات — الصف :position: الحساب المختار غير موجود.',
            'expenses_production_stage_id.*.exists' => 'المصروفات — الصف :position: المرحلة المختارة غير موجودة.',
            'expenses_price.*.numeric' => 'المصروفات — الصف :position: المبلغ يجب أن يكون رقماً.',
            'expenses_price.*.min' => 'المصروفات — الصف :position: المبلغ لا يكون سالباً.',

            // عمليات التصنيع
            'workstation_id.*.exists' => 'عمليات التصنيع — الصف :position: محطة العمل المختارة غير موجودة.',
            'operating_time.*.numeric' => 'عمليات التصنيع — الصف :position: وقت التشغيل يجب أن يكون رقماً.',
            'operating_time.*.min' => 'عمليات التصنيع — الصف :position: وقت التشغيل لا يكون سالباً.',
            'manu_production_stage_id.*.exists' => 'عمليات التصنيع — الصف :position: المرحلة المختارة غير موجودة.',
            'manu_total_cost.*.numeric' => 'عمليات التصنيع — الصف :position: تكلفة الساعة يجب أن تكون رقماً.',
            'manu_total_cost.*.min' => 'عمليات التصنيع — الصف :position: تكلفة الساعة لا تكون سالبة.',

            // المصروفات غير المباشرة
            'indirect_cost_id.*.exists' => 'المصروفات غير المباشرة — الصف :position: التكلفة المختارة غير موجودة.',
            'indirect_stage_id.*.exists' => 'المصروفات غير المباشرة — الصف :position: المرحلة المختارة غير موجودة.',

            // المواد الهالكة
            'end_life_product_id.*.exists' => 'المواد الهالكة — الصف :position: المنتج المختار غير موجود.',
            'end_life_production_stage_id.*.exists' => 'المواد الهالكة — الصف :position: المرحلة المختارة غير موجودة.',
            'end_life_unit_price.*.numeric' => 'المواد الهالكة — الصف :position: السعر يجب أن يكون رقماً.',
            'end_life_unit_price.*.min' => 'المواد الهالكة — الصف :position: السعر لا يكون سالباً.',
            'end_life_quantity.*.numeric' => 'المواد الهالكة — الصف :position: الكمية يجب أن تكون رقماً.',
            'end_life_quantity.*.min' => 'المواد الهالكة — الصف :position: الكمية لا تكون سالبة.',
        ];
    }
}
