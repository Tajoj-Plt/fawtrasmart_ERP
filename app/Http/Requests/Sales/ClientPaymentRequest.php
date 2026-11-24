<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class ClientPaymentRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            // يجب توفير واحد على الأقل: purchases_id للمشتريات أو invoice_id للمبيعات
            'purchases_id' => 'nullable|exists:purchase_invoices,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'employee_id' => 'nullable|exists:users,id',
            // الجدول `installments` لا `installment`: الاسم المفرد لا وجود
            // له، فأيّ قيمةٍ غير فارغة كانت ترمي خطأ SQL بدل أن تُفحَص
            'installments_id' => 'nullable|exists:installments,id',
            'treasury_id' => 'nullable|exists:accounts,id',

            'payment_date' => 'nullable|date',
            'type' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'payment_type' => 'nullable|exists:payment_methods,id',
            'status_payment' => 'nullable|in:1,2,3,4,5',

            /* رقم السند من «تسلسل أرقام المستندات» — لم تكن له قاعدة،
               وما لا قاعدة له تُسقطه `validated()` بصمت فلا يصل الحفظ. */
            'reference_number' => 'nullable|string|max:255',
            'payment_data' => 'nullable|string',
            'notes' => 'nullable|string',
            // القواعد والامتدادات من مصدرٍ واحد مع بقيّة مرفقات المستندات
            'attachments' => 'nullable|'.\App\Support\DocumentAttachments::rule(),
        ];

        // إذا كانت العملية تحديث، نجعل القواعد أكثر مرونة
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = array_map(function ($rule) {
                return 'sometimes|' . $rule;
            }, $rules);
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // التحقق من وجود واحد على الأقل من purchases_id أو invoice_id
            if (!$this->filled('purchases_id') && !$this->filled('invoice_id')) {
                $validator->errors()->add('invoice_id', 'يجب تحديد فاتورة (مشتريات أو مبيعات)');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [


            'payment_date.required' => 'تاريخ الدفع مطلوب',
            'payment_date.date' => 'تاريخ الدفع يجب أن يكون تاريخاً صحيحاً',
            'amount.required' => 'المبلغ مطلوب',

            'amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'payment_type.required' => 'نوع الدفع مطلوب',
            'payment_type.in' => 'نوع الدفع غير صحيح',
            'status_payment.required' => 'حالة الدفع مطلوبة',
            'status_payment.in' => 'حالة الدفع غير صحيحة',
            ...\App\Support\DocumentAttachments::messages(),
        ];
    }
}
