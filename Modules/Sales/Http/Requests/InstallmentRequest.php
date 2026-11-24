<?php

namespace Modules\Sales\Http\Requests;

use App\Models\Installment;
use App\Models\Invoice;
use App\Models\TaxSitting;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Sales\Support\InstallmentSchedule;

/**
 * التحقّق من اتفاقية التقسيط.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان
 * ══════════════════════════════════════════════════════════════════════
 *
 * في `store()`:
 *
 *     'amount' => 'required|numeric|min:0.01',
 *     'payment_rate' => 'required|integer|in:1,2,3,4,5',
 *
 * و`create.blade.php` فيه **حقلان اسمُهما `amount`**: الأول إجمالي
 * الفاتورة (`readonly`) والثاني قيمة القسط. في HTTP يفوز الأخير، فالقاعدة
 * تتحقّق من قيمة القسط ويُحفَظ إجمالي الفاتورة… لا شيء. والحقل
 * `installment_number` مُتحقَّقٌ منه `required` وهو `readonly` يملؤه
 * جافاسكربت — فمن عطّل السكربت أو أبطأ تحميله أرسل فراغاً.
 *
 * وفي `update()` لا شيء مطلوب إطلاقاً:
 *
 *     'amount' => 'nullable|numeric',
 *     'payment_rate' => 'nullable|integer',
 *
 * `nullable|integer` بلا `in:` تقبل `payment_rate = 97`، فتصير الدوريّة
 * قيمةً لا يعرفها `calculateNextDueDate()` وتسقط على `default` الشهري
 * بصمت. ولا تحقّق من الفترة المحاسبية، ولا من العملة، ولا من أنّ الدفعة
 * المقدَّمة أقلّ من الإجمالي.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما صار
 * ══════════════════════════════════════════════════════════════════════
 *
 * اسمٌ واحدٌ لكلّ معنى: `down_payment` و`installment_number` و
 * `first_due_date`. وقيمة القسط **لا تُرسَل أصلاً** — تُحسب في
 * `InstallmentSchedule` من الممول والعدد، فلا يختلف ما يراه المستخدم عمّا
 * يُحفَظ.
 */
class InstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // المنع الفعلي في وسائط المتحكّم؛ هذا الطلب لا يُبنى إلا بعدها
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],

            'code' => [
                'nullable', 'string', 'max:60',
                Rule::unique('installments', 'code')->ignore($id),
            ],

            // ── المال ─────────────────────────────────────────────
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],

            // `lt:total_amount` لا `max:` : دفعةٌ مقدَّمة تساوي الإجمالي
            // تعني اتفاقيةً بمبلغٍ ممولٍ صفر — أي جدولاً بلا أقساط
            'down_payment' => ['nullable', 'numeric', 'min:0', 'lt:total_amount'],

            'profit_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admin_fees' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],

            /* الضريبة **تُختار من «إعدادات الضريبة» ولا تُكتب**.
               `tax_rate` لم يعد حقلاً في الشاشة: النسبةُ تُقرأ من الصفّ
               المختار في `taxRate()` أدناه. وكتابةُ نسبةٍ بيدٍ تعني أن
               اتفاقيتين في المنشأة نفسِها تحملان ضريبتين مختلفتين بلا
               سببٍ إلا اختلافَ من أدخلهما. */
            'tax_id' => ['nullable', 'integer', 'exists:tax_sittings,id'],

            // ── الجدولة ───────────────────────────────────────────
            'installment_number' => [
                'required', 'integer', 'min:1', 'max:'.InstallmentSchedule::MAX_INSTALLMENTS,
            ],

            // `in:` بمفاتيح `RATES` نفسها لا بقائمةٍ مكتوبة: إضافة دوريّة
            // هناك تسري هنا بلا تعديل
            'payment_rate' => [
                'required', 'integer', Rule::in(array_keys(InstallmentSchedule::RATES)),
            ],

            'first_due_date' => ['required', 'date'],

            // ── الغرامة ───────────────────────────────────────────
            'late_fee_type' => ['nullable', Rule::in(['percent', 'fixed'])],
            'late_fee_value' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'late_fee_grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            // مرّةً واحدة بعد السماح، أو عن كلّ يوم تأخير
            'late_fee_basis' => ['nullable', Rule::in(InstallmentSchedule::FEE_BASES)],

            // سقفُ الغرامة على القسط الواحد — فارغاً يعني بلا سقف
            'late_fee_max' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],

            // ── إعدادات النظام ────────────────────────────────────
            'currency' => ['nullable', 'string', 'max:10', Rule::in($this->allowedCurrencies())],

            'payment_method' => [
                'nullable', 'integer',
                Rule::exists('payment_methods', 'id')->where('status', 'active'),
            ],

            /* خزينةُ الدفعة المقدَّمة — حسابٌ من شجرة الحسابات لا صفٌّ في
               `treasuries`: عمودُ `payments_process.treasury_id` يشير إلى
               `accounts`، وهو ما يتحقّق منه `ClientPaymentRequest` كذلك.
               فارغةً تُستعمل خزينةُ الموظّف ثمّ المضبوطة في «الحسابات
               المحاسبية». */
            'treasury_id' => ['nullable', 'integer', 'exists:accounts,id'],

            'status' => ['nullable', Rule::in(Installment::STATUSES)],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /* ── فاتورةٌ لا دَينَ فيها ──────────────────────────────
             *
             * **عند الإنشاء وحده**: `$this->route('id')` موجودٌ في التعديل
             * وغائبٌ في الإنشاء. ولو فُحصت الاتفاقيةُ القائمة كذلك لامتنع
             * تعديلُها بمجرّد اكتمال تحصيلها — وهو الحال الطبيعي لا خطأً:
             * الاتفاقيةُ تُسدَّد فتُقفَل فاتورتُها، ويبقى تعديلُ ملاحظةٍ
             * أو حالةٍ فيها مشروعاً.
             *
             * انظر `Invoice::installmentBlockReason()` للسبب كاملاً.
             */
            if (! $this->route('id') && $invoiceId = $this->input('invoice_id')) {
                $invoice = Invoice::find($invoiceId);

                if ($invoice && $reason = $invoice->installmentBlockReason()) {
                    $validator->errors()->add('invoice_id', $reason);
                }
            }

            // ── الفترة المحاسبية ──────────────────────────────────
            // الاتفاقية تُنشئ ذمّةً مجدولة على العميل، وتاريخُ أوّل قسط
            // هو تاريخ المستند. إنشاؤها داخل فترةٍ أُقفلت وأُصدرت قوائمها
            // يفتح باباً على سنةٍ مغلقة
            if ($date = $this->input('first_due_date')) {
                if ($reason = AccountingPeriodGuard::blockReason($date)) {
                    $validator->errors()->add('first_due_date', $reason);
                }
            }

            // ── الغرامة: نوعٌ بلا قيمة أو قيمةٌ بلا نوع ───────────
            $type = $this->input('late_fee_type');
            $value = (float) $this->input('late_fee_value');

            if ($type && $value <= 0) {
                $validator->errors()->add(
                    'late_fee_value',
                    __('sales_installments.validation.late_fee_value_required')
                );
            }

            if (! $type && $value > 0) {
                $validator->errors()->add(
                    'late_fee_type',
                    __('sales_installments.validation.late_fee_type_required')
                );
            }

            /* سقفٌ أصغرُ من وحدة الغرامة اليومية بلا معنى: القسطُ يبلغ
               سقفَه في أوّل يومٍ من التأخّر، فالاحتسابُ اليوميّ عندئذٍ
               احتسابٌ لمرّةٍ واحدة مكتوبٌ بطريقةٍ أطول. */
            $max = (float) $this->input('late_fee_max');

            if ($max > 0 && $value > 0 && $type === 'fixed' && $max < $value) {
                $validator->errors()->add(
                    'late_fee_max',
                    __('sales_installments.validation.late_fee_max_too_small')
                );
            }

            // ── الجدول قابلٌ للبناء؟ ──────────────────────────────
            // الفحص بمحاولة البناء لا بإعادة كتابة شروطه: `InstallmentSchedule`
            // هو من يعرف متى يستحيل الجدول، وتكرارُ شروطه هنا نسخةٌ ثانية
            // تتباعد عنه
            try {
                $this->schedule();
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('installment_number', $e->getMessage());
            }
        });
    }

    /**
     * الجدول المحسوب من المدخلات — مصدرٌ واحد للمتحكّم وللمعاينة.
     */
    public function schedule(): InstallmentSchedule
    {
        return new InstallmentSchedule(
            (float) $this->input('total_amount'),
            (float) $this->input('down_payment', 0),
            (float) $this->input('profit_rate', 0),
            (float) $this->input('admin_fees', 0),
            $this->taxRate(),
            (int) $this->input('installment_number'),
            (int) $this->input('payment_rate'),
            (string) $this->input('first_due_date')
        );
    }

    /**
     * نسبة الضريبة — **من «إعدادات الضريبة» لا من الطلب**.
     *
     * الصفُّ المختار (`tax_id`) هو المصدر، وتُقبَل نسبتُه بالصيغتين:
     * كسراً (`0.15`) ومئويةً (`15`) — فجدولُ الضرائب يحتمل الاثنتين،
     * و`TaxCalculator::fraction()` يعاملهما كذلك. وهذا الصنف يعمل
     * بالمئوية، فالكسرُ يُحوَّل.
     *
     * وطريقةُ الضريبة (`included`/`excluded`) **لا تُقرأ هنا**: وعاءُ
     * الضريبة في التقسيط هو الربحُ والرسومُ الإدارية — مبالغُ تُحسب عارية
     * ولا تحمل ضريبةً أصلاً، فلا معنى لاستخراجها منها.
     */
    public function taxRate(): float
    {
        $id = $this->input('tax_id');

        if (! $id) {
            return 0.0;
        }

        $rate = (float) (TaxSitting::whereKey($id)->value('tax') ?? 0);

        // 0.15 تعني 15٪ — الصيغة الكسرية كما يقبلها `TaxCalculator`
        return $rate > 0 && $rate < 1 ? round($rate * 100, 4) : $rate;
    }

    public function attributes(): array
    {
        return [
            'invoice_id' => __('sales_installments.fields.invoice'),
            'client_id' => __('sales_installments.fields.client'),
            'code' => __('sales_installments.fields.code'),
            'total_amount' => __('sales_installments.fields.total_amount'),
            'down_payment' => __('sales_installments.fields.down_payment'),
            'profit_rate' => __('sales_installments.fields.profit_rate'),
            'admin_fees' => __('sales_installments.fields.admin_fees'),
            'tax_id' => __('sales_installments.fields.tax'),
            'installment_number' => __('sales_installments.fields.installment_number'),
            'payment_rate' => __('sales_installments.fields.payment_rate'),
            'first_due_date' => __('sales_installments.fields.first_due_date'),
            'late_fee_type' => __('sales_installments.fields.late_fee_type'),
            'late_fee_value' => __('sales_installments.fields.late_fee_value'),
            'late_fee_grace_days' => __('sales_installments.fields.late_fee_grace_days'),
            'late_fee_basis' => __('sales_installments.fields.late_fee_basis'),
            'late_fee_max' => __('sales_installments.fields.late_fee_max'),
            'currency' => __('sales_installments.fields.currency'),
            'payment_method' => __('sales_installments.fields.payment_method'),
            'treasury_id' => __('sales_installments.fields.treasury'),
            'status' => __('sales_installments.fields.status'),
            'note' => __('sales_installments.fields.note'),
        ];
    }

    /**
     * رموز العملات المقبولة — المفعَّلة، ثمّ عملة النظام، ثمّ المحفوظة.
     *
     * @return array<int, string>
     */
    private function allowedCurrencies(): array
    {
        $codes = [];

        try {
            $codes = \App\Models\SystemCurrency::where('is_active', true)
                ->pluck('code')->filter()->all();
        } catch (\Throwable $e) {
            $codes = [];
        }

        try {
            $codes[] = CurrencySettings::code();
        } catch (\Throwable $e) {
            // إعدادات العملة غير مهيّأة
        }

        // عملةٌ عُطّلت بعد حفظ الاتفاقية لا يجوز أن تمنع تعديل ملاحظةٍ فيها
        if ($id = $this->route('id')) {
            $stored = Installment::whereKey($id)->value('currency');

            if (is_string($stored) && $stored !== '') {
                $codes[] = $stored;
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }
}
