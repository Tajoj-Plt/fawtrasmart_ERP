<?php

namespace Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * قواعدُ تصنيف الوردية — لِلإنشاء والتعديل معاً.
 *
 * صنفٌ واحدٌ لا اثنان: `StoreShiftRequest` و`UpdateShiftRequest` نسختان
 * متطابقتان حرفياً من نفس القواعد ونفس الرسائل، ولا فرقَ بينهما إلّا
 * الاسم. والتفرّدُ هنا هو الفرقُ الحقيقيُّ الوحيد بين الحالتين، ويُعبَّر
 * عنه بـ`ignore()` لا بصنفٍ ثانٍ.
 */
class ShiftCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('pos.shifts.manage');
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            /*
                الاسمُ متفرّدٌ **داخل الفرع** لا في المنشأة كلّها.

                «صباحي» في الرياض و«صباحي» في جدة تصنيفان مشروعان — وفرضُ
                التفرّد عالمياً يمنع الفرعَ الثاني من تسمية تصنيفه باسمه
                الطبيعي. والمحذوفُ ناعماً لا يَحجز اسمَه.
            */
            'name' => [
                'required', 'string', 'min:2', 'max:190',
                Rule::unique('pos_shift_categories', 'name')
                    ->where(fn ($q) => $q
                        ->whereNull('deleted_at')
                        ->where('branch_id', $this->input('branch_id')))
                    ->ignore($id),
            ],

            'description' => 'nullable|string|max:1000',

            // لونٌ سداسيٌّ عشريّ — ‎#RGB أو ‎#RRGGBB
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/',

            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
            'branch_id' => 'required|exists:branches,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التصنيف مطلوب',
            'name.min' => 'اسم التصنيف يجب أن يكون حرفين على الأقل',
            'name.unique' => 'يوجد تصنيفٌ بهذا الاسم في هذا الفرع',
            'color.regex' => 'اللون يجب أن يكون بصيغة #RRGGBB',
            'sort_order.integer' => 'ترتيب العرض يجب أن يكون رقماً',
            'sort_order.max' => 'ترتيب العرض يجب ألا يتجاوز 9999',
            'branch_id.required' => 'الفرع مطلوب',
            'branch_id.exists' => 'الفرع المختار غير موجود',
            'description.max' => 'الوصف يجب ألا يتجاوز 1000 حرف',
        ];
    }
}
