<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تحقّقُ خطّة الإنتاج — رأسُها وبنودُها.
 *
 * ما لا يُقبل من الطلب: `code` و`status` و`branch_id`.
 *
 *  · الرقمُ يُصدَر من إعدادات الترقيم داخل معاملةٍ بقفل
 *    (`issueDocumentNumber`) — نفسُ الدرس المكتوب في
 *    `ManufacturingOrdersController::store`: حقلٌ قابلٌ للتحرير يكسر
 *    التسلسل، و`unique` في الطلب ليس قفلاً.
 *  · الحالةُ تتحرّك بآلة [\App\Enums\ProductionPlanStatus] عبر مساراتها
 *    الخاصّة (`approve` و`cancel`)، لا بحقلٍ مُرسَل.
 *  · الفرعُ يُختم من الفرع النشط — انظر `ScopesToBranch::documentBranchId`.
 */
class ProductionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],

            /*
                المسؤولُ إلزاميّ هنا وإن كان العمودُ `nullable` في الجدول:
                فراغُه حالةٌ واحدة — حُذف سجلُّ الموظّف بعد الإنشاء
                (`nullOnDelete`). أمّا الإنشاءُ والتعديل فيلزمهما مسؤول،
                لأنّ `manufactur_orders.employee_id` إلزاميّ.
            */
            'employee_id' => ['required', 'exists:employees,id'],

            'client_id' => ['nullable', 'exists:clients,id'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'storehouse_id' => ['nullable', 'exists:store_houses,id'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // ── البنود ──
            'product_id' => ['required', 'array', 'min:1'],
            'product_id.*' => ['required', 'exists:products,id'],

            'production_material_id' => ['required', 'array', 'min:1'],
            'production_material_id.*' => ['required', 'exists:production_materials,id'],

            'production_path_id' => ['required', 'array', 'min:1'],
            'production_path_id.*' => ['required', 'exists:production_paths,id'],

            /*
                `gt:0` لا `min:1`: الكميّاتُ في هذه الوحدة كسريّة
                (`DECIMAL(18,4)`)، فـ`min:1` يمنع خطّةَ نصفِ طنّ بلا سبب.
                والممنوعُ فعلاً هو الصفرُ والسالب.
            */
            'planned_quantity' => ['required', 'array', 'min:1'],
            'planned_quantity.*' => ['required', 'numeric', 'gt:0'],

            'item_notes' => ['nullable', 'array'],
            'item_notes.*' => ['nullable', 'string', 'max:500'],

            /*
                معرّفاتُ البنود القائمة عند التعديل — تُميّز المعدَّل عن
                الجديد. وتُحرَس بأنّها من هذه الخطّة وحدها كي لا يُسحَب
                بندُ خطّةٍ أخرى إليها بمعرّفٍ مُرسَل.
            */
            'item_id' => ['nullable', 'array'],
            'item_id.*' => [
                'nullable',
                Rule::exists('production_plan_items', 'id')
                    ->where('production_plan_id', $this->route('id')),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الخطّة',
            'from_date' => 'تاريخ البداية',
            'to_date' => 'تاريخ النهاية',
            'employee_id' => 'المسؤول',
            'client_id' => 'العميل',
            'account_id' => 'الحساب',
            'storehouse_id' => 'مستودع المواد',
            'product_id' => 'المنتج',
            'product_id.*' => 'المنتج',
            'production_material_id.*' => 'قائمة المواد',
            'production_path_id.*' => 'مسار الإنتاج',
            'planned_quantity.*' => 'الكميّة المخطّطة',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'أضف بنداً واحداً على الأقل إلى الخطّة.',
            'product_id.min' => 'أضف بنداً واحداً على الأقل إلى الخطّة.',
            'planned_quantity.*.gt' => 'الكميّة المخطّطة يجب أن تكون أكبر من صفر.',
            'to_date.after_or_equal' => 'تاريخ النهاية لا يسبق تاريخ البداية.',
        ];
    }
}
