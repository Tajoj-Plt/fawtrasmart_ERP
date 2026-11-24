<?php

namespace Modules\HR\Http\Requests;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\HR\Services\ContractService;

/**
 * إنشاء عقد موظف وتعديله — القاعدةُ نفسُها للاثنين.
 *
 * كان التعديلُ بلا تحقّقٍ إطلاقاً (`Request` عادي يُحدِّث ما امتلأ فقط)،
 * والإنشاءُ يشترط `amount` — حقلَ الأساسي المنفصل — فيُرفض عقدٌ بنودُه
 * المستحقة كلُّها بمبالغ لأن ذلك الحقل وحده فارغ. الشرطُ الآن على
 * البنود الفعلية: أساسيٌّ أو بندٌ مستحقٌّ واحدٌ على الأقل بمبلغ.
 *
 * وعند الإخفاق يُعاد المستخدم إلى النموذج بكلّ ما أدخله (`withInput`
 * تلقائياً في FormRequest) — فالشاشةُ تقرأ كل حقلٍ وكل صفّ بندٍ من `old()`.
 */
class ContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        // «مدة» الافتراضية عند غياب الاختيار — كان اسمُ الحقل في شاشة
        // الإنشاء `contract_type` وفي المتحكّم `type_contract`، فلا يُحفظ أبداً.
        $type = $this->input('type_contract', $this->input('contract_type'));
        $merge['type_contract'] = in_array((string) $type, ['1', '2'], true) ? (int) $type : Contract::TYPE_PERIOD;

        // الأساسي قد يُكتب بفواصل آلاف — يُطبَّع قبل `numeric`
        if ($this->filled('amount')) {
            $number = ContractService::number($this->input('amount'));
            $merge['amount'] = $number ?? $this->input('amount');
        }

        foreach (['addition_amount', 'deduction_amount'] as $key) {
            if (is_array($this->input($key))) {
                $merge[$key] = array_map(
                    fn ($v) => ($v === null || trim((string) $v) === '') ? null : (ContractService::number($v) ?? $v),
                    $this->input($key)
                );
            }
        }

        // تاريخُ الانتهاء في نوع «مدة» يُحسب هنا لا يُقبل من النموذج — فلا
        // يُحفظ عقدٌ مدّتُه سنة وتاريخُ انتهائه غيرُ ما تقوله المدّة.
        if ($merge['type_contract'] === Contract::TYPE_PERIOD) {
            $end = app(ContractService::class)->computeEndDate(
                $this->input('start_date') ?: $this->input('join_date'),
                $this->input('duration'),
                $this->input('duration_unit')
            );
            $merge['end_date'] = $end?->toDateString();
        }

        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'job_title_id' => 'required|exists:jop_titles,id',
            'job_level_id' => 'required|exists:functional_levels,id',
            // `salary_temp_id` لم يعد يُقبل: «قوالب الرواتب» أُلغيت (2026-09-24)
            'parent_contract_id' => 'nullable|exists:contracts,id',
            'description' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'type_contract' => 'required|in:1,2',
            'duration' => 'required_if:type_contract,1|nullable|integer|min:1|max:1200',
            'duration_unit' => 'required_if:type_contract,1|nullable|in:1,2,3',
            // «تاريخ انتهاء» فارغاً = عقدٌ غير محدد المدة (كالعقود القائمة بلا انتهاء)
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'join_date' => 'required|date',
            'probation_end_date' => 'required|date|after_or_equal:join_date',
            'contract_date' => 'required|date',
            'receiving_cycle' => 'nullable|in:1,2,3,4,5',
            // الأساسيُّ اختياري إن وُجدت بنودٌ مستحقة — انظر `withValidator`
            'amount' => 'nullable|numeric|min:0',
            'addition_type' => 'nullable|array',
            'addition_amount' => 'nullable|array',
            'addition_amount.*' => 'nullable|numeric|min:0',
            'addition_calculation_formula' => 'nullable|array',
            'deduction_type' => 'nullable|array',
            'deduction_amount' => 'nullable|array',
            'deduction_amount.*' => 'nullable|numeric|min:0',
            'deduction_calculation_formula' => 'nullable|array',
            'attachments' => 'nullable|file|max:10240',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $service = app(ContractService::class);
            $basic = (float) ($this->input('amount') ?? 0);
            $contractId = is_numeric($this->route('id')) ? (int) $this->route('id') : null;
            $rows = $service->buildItemRows($this->all(), $basic, $contractId);
            $totals = ContractService::totals($basic, $rows['additions'], $rows['deductions']);

            // مبلغُ العقد = الأساسي + المستحقات: يكفي أحدُهما.
            if ($totals['gross'] <= 0) {
                $validator->errors()->add(
                    'amount',
                    'مبلغ العقد صفر: أدخل الراتب الأساسي، أو أضف بنداً مستحقاً واحداً على الأقل بمبلغ.'
                );

                return;
            }

            if ($totals['net'] < 0) {
                $validator->errors()->add(
                    'deduction_amount',
                    'مجموع المستقطعات ('.number_format($totals['deductions'], 2).') يتجاوز إجمالي الراتب ('.number_format($totals['gross'], 2).').'
                );
            }

            // بندٌ مختارٌ مكرَّر في الجهة نفسها يُحسب مرّتين في القسيمة
            foreach (['addition_type' => 'المستحقات', 'deduction_type' => 'المستقطعات'] as $key => $label) {
                $ids = array_filter((array) $this->input($key, []), 'is_numeric');
                if (count($ids) !== count(array_unique($ids))) {
                    $validator->errors()->add($key, 'البند نفسه مكرَّر في '.$label.' — احذف الصفّ المكرَّر.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'job_title_id.required' => 'المسمّى الوظيفي مطلوب',
            'job_title_id.exists' => 'المسمّى الوظيفي المحدد غير موجود',
            'job_level_id.required' => 'المستوى الوظيفي مطلوب',
            'job_level_id.exists' => 'المستوى الوظيفي المحدد غير موجود',
            'parent_contract_id.exists' => 'العقد الأساسي المحدد غير موجود',
            'start_date.required' => 'تاريخ بداية العقد مطلوب',
            'type_contract.required' => 'اختر نوع مدة العقد: مدة أو تاريخ انتهاء',
            'duration.required_if' => 'أدخل مدة العقد',
            'duration.min' => 'مدة العقد يجب أن تكون 1 على الأقل',
            'duration_unit.required_if' => 'اختر وحدة المدة',
            'end_date.after_or_equal' => 'تاريخ نهاية العقد يجب أن يكون بعد تاريخ البداية أو مساوياً له',
            'join_date.required' => 'تاريخ الالتحاق مطلوب',
            'probation_end_date.required' => 'تاريخ نهاية التجربة مطلوب',
            'probation_end_date.after_or_equal' => 'نهاية فترة التجربة لا تسبق تاريخ الالتحاق',
            'contract_date.required' => 'تاريخ توقيع العقد مطلوب',
            'amount.numeric' => 'الراتب الأساسي يجب أن يكون رقماً',
            'amount.min' => 'الراتب الأساسي لا يكون سالباً',
            'addition_amount.*.numeric' => 'مبلغ البند المستحق يجب أن يكون رقماً',
            'deduction_amount.*.numeric' => 'مبلغ البند المستقطع يجب أن يكون رقماً',
            'attachments.max' => 'حجم المرفق يتجاوز 10 ميغابايت',
        ];
    }
}
