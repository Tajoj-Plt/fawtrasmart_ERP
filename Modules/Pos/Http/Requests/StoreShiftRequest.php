<?php

namespace Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * قواعدُ الوردية.
 *
 * ═════════ ما أُضيف ═════════
 *
 * `start_time` و`end_time` و`days_of_week`. وبدونها كان `shifts_pos`
 * **شجرةَ تسمياتٍ لا وردية**: اسمٌ وأبٌ ومرفق، بلا أيّ مدًى زمني. فلا
 * يمكن السؤال «أفَتَح الكاشيرُ جلسته خارج ورديته؟» ولا بناءُ تقرير التزام.
 */
class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('pos.shifts.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'category_id' => 'nullable|exists:pos_shift_categories,id',
            'parent_id' => 'nullable|exists:shifts_pos,id',
            'branch_id' => 'required|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',

            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:0,6',
            'grace_minutes' => 'nullable|integer|min:0|max:240',
            'is_active' => 'nullable|boolean',

            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * وقتُ بدايةٍ بلا نهاية — أو العكس — لا يصنع مدًى.
     *
     * ولا يُشترط أن تكون النهايةُ بعد البداية: ورديةٌ تعبر منتصف الليل
     * (٢٢:٠٠ ← ٠٦:٠٠) صحيحةٌ ومألوفة، ويتولّى حسابَها منطقُ الجلسة.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if (($start && ! $end) || ($end && ! $start)) {
                $v->errors()->add('end_time', 'يجب تحديد وقتَي البداية والنهاية معاً، أو تركهما فارغين.');
            }

            if ($this->filled('parent_id') && (int) $this->input('parent_id') === (int) $this->route('id')) {
                $v->errors()->add('parent_id', 'لا يمكن أن تكون الوردية أباً لنفسها.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الوردية مطلوب',
            'name.min' => 'اسم الوردية يجب أن يكون على الأقل 3 أحرف',
            'category_id.exists' => 'التصنيف المختار غير موجود',
            'parent_id.exists' => 'الوردية الأب المختارة غير موجودة',
            'branch_id.required' => 'الفرع مطلوب',
            'branch_id.exists' => 'الفرع المختار غير موجود',
            'user_id.exists' => 'الموظف المختار غير موجود',
            'start_time.date_format' => 'وقت البداية يجب أن يكون بصيغة HH:MM',
            'end_time.date_format' => 'وقت النهاية يجب أن يكون بصيغة HH:MM',
            'days_of_week.*.between' => 'أيام الأسبوع يجب أن تكون بين 0 (الأحد) و6 (السبت)',
            'grace_minutes.max' => 'مهلة التأخّر يجب ألا تتجاوز 240 دقيقة',
            'attachment.mimes' => 'صيغة الملف يجب أن تكون pdf أو doc أو docx أو jpg أو jpeg أو png',
            'attachment.max' => 'حجم الملف يجب ألا يتجاوز 10 ميغابايت',
            'description.max' => 'الوصف يجب ألا يتجاوز 1000 حرف',
        ];
    }
}
