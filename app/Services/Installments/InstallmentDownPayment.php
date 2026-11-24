<?php

namespace App\Services\Installments;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\PaymentMethod;
use App\Models\PaymentsProcess;
use App\Models\TreasuryEmployee;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * الدفعة المقدَّمة — سندُ قبضٍ وقيدٌ على الفاتورة، لا رقمٌ في حقل.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان
 * ══════════════════════════════════════════════════════════════════════
 *
 * `down_payment` عمودٌ يُملأ من نموذج الاتفاقية ويُخصَم من المبلغ الممول،
 * **ولا يُنشئ شيئاً**: لا سندَ قبضٍ في `payments_process`، ولا قيداً في
 * دفتر الأستاذ، ولا أثراً على `invoices.advance_payment`.
 *
 * فالفاتورةُ بعشرة آلاف ومقدَّمتُها ألفان تبقى «غير مسدَّدة» بعشرة آلاف
 * في كلّ شاشةٍ وتقرير، والخزينةُ لا تعرف بالألفين، وذمّةُ العميل في دفتر
 * الأستاذ أكبرُ ممّا عليه فعلاً. ثمّ يُسجّل المستخدم أوّلَ قسطٍ فيذهب
 * سندُه إلى الدفعة المقدَّمة (`applyPayments()` تخصمها أوّلاً)، فيجد
 * القسطَ الأوّل «غير مسدَّد» بلا سببٍ ظاهر.
 *
 * وكان علاجُ ذلك زرّاً في الشاشة يفتح سندَ قبضٍ يدوياً — يُصيب من انتبه
 * له ويُخطئ من لم ينتبه.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما صار
 * ══════════════════════════════════════════════════════════════════════
 *
 * **الدفعة المقدَّمة دفعةٌ أولى حقيقية**: تُسجَّل سندَ قبضٍ على الفاتورة
 * لحظةَ حفظ الاتفاقية، ويُرحَّل قيدُها:
 *
 *     من ح/ الخزينة            xx
 *         إلى ح/ العميل             xx
 *
 * وهو **مسارُ المال نفسُه** الذي تكتبه `SalesPaymentController::store()`
 * — الجدولُ نفسُه والأعمدةُ نفسُها ونوعُ القيد نفسُه — فلا مساران
 * يفترقان ولا رصيدُ عميلٍ لا يُعرف أيُّهما صوابه. وما يميّزه أنّ
 * `installments_id` مملوءٌ فيه، وبه يُعرف السندُ المقدَّم من غيره فلا
 * يُسجَّل مرّتين.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  حدودٌ مقصودة
 * ══════════════════════════════════════════════════════════════════════
 *
 *   • **لا تُسجَّل مرّتين.** وجودُ سندٍ بـ`installments_id` للاتفاقية
 *     يُنهي العملية بلا خطأ: تعديلُ الاتفاقية لا يُضاعف مقدَّمتَها.
 *
 *   • **لا تتجاوز متبقّي الفاتورة.** فاتورةٌ سُدِّد أكثرُها قبل التقسيط
 *     لا يُقبَض عليها ثانيةً، والمبلغُ يُقصَّ إلى ما بقي.
 *
 *   • **تسقط العمليةُ كلُّها إن لم يوجد حسابٌ مضبوط** — الخزينة أو حساب
 *     العميل. سندٌ بلا قيدٍ أسوأ من اتفاقيةٍ لم تُحفَظ: الأولى تُصلَح
 *     بإعادة الإدخال، والثاني لا يُكتشَف إلا في ميزان المراجعة.
 */
class InstallmentDownPayment
{
    /** قسم الترقيم المتسلسل لسندات القبض — نفسُه في شاشة السندات. */
    private const NUMBER_SECTION = 'invoice-payment';

    /**
     * يسجّل الدفعة المقدَّمة سندَ قبضٍ وقيداً.
     *
     * @return PaymentsProcess|null السند المُنشأ، أو null إن لا مقدَّمة أو
     *                              كانت مسجَّلةً من قبل
     *
     * @throws RuntimeException إن لم يُربط حسابٌ لازم
     */
    public static function post(Installment $agreement, ?int $treasuryId = null): ?PaymentsProcess
    {
        $amount = round((float) $agreement->down_payment, 2);

        if ($amount <= 0.009) {
            return null;
        }

        $invoice = $agreement->invoice;

        if (! $invoice) {
            return null;
        }

        // مسجَّلةٌ من قبل: تعديلُ الاتفاقية لا يُضاعف مقدَّمتَها
        if (self::existingFor($agreement)) {
            return null;
        }

        $amount = self::capToInvoice($invoice, $amount);

        if ($amount <= 0.009) {
            return null;
        }

        $treasury = self::resolveTreasury($treasuryId);

        if (! $treasury) {
            throw new RuntimeException(__('settings.accounting_settings.account_not_mapped', [
                'account' => __('sales_installments.fields.treasury'),
            ]));
        }

        $clientAccount = self::clientAccount($agreement, $invoice);

        if (! $clientAccount) {
            throw new RuntimeException(__('sales_installments.messages.no_client_account'));
        }

        return DB::transaction(function () use ($agreement, $invoice, $amount, $treasury, $clientAccount) {
            $payment = PaymentsProcess::create([
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'installments_id' => $agreement->id,
                'treasury_id' => $treasury->id,
                'branch_id' => $invoice->branch_id ?: current_branch_id(),
                'employee_id' => auth()->id(),
                'type' => 'client payments',
                /* تاريخُ السند اليوم لا تاريخُ أوّل قسط: المال قُبض عند
                   التوقيع، وأوّلُ قسطٍ موعدٌ في المستقبل */
                'payment_date' => now(),
                'amount' => $amount,
                'payment_status' => 1,
                /* طريقةُ الاتفاقية، وإلّا الافتراضيةُ المفعَّلة في «طرق الدفع»:
                   الحقلُ اختياريٌّ في شاشة التقسيط، وسندُ قبضٍ بلا طريقةِ
                   دفعٍ يعرض «—» في الشاشة وفي مطبوعته. */
                'payment_method' => $agreement->payment_method ?: PaymentMethod::query()
                    ->where('status', 'active')
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->value('id'),
                'reference_number' => self::documentNumber(),
                'notes' => __('sales_installments.down_payment.voucher_note', ['code' => $agreement->code]),
            ]);

            /* الأرصدة لا تُكتب بيدٍ: `JournalEntryDetail::created` يُعيد
               حسابَ الحساب من قيوده ويُصعّده إلى آبائه. وكتابتُها هنا —
               كما يفعل `SalesPaymentController::store()` — سطرٌ لا أثر له:
               إعادةُ الحساب بعده تدهسه. والأسوأ منه كتابتُها **بعد** القيد
               فتدهس هي المحسوب وتترك الأبَ مخالفاً لابنه. */
            $journal = JournalEntry::create([
                'reference_number' => $payment->reference_number ?: $invoice->code,
                'date' => now(),
                'description' => __('sales_installments.down_payment.journal', [
                    'code' => $agreement->code,
                    'invoice' => $invoice->code,
                ]),
                'status' => 1,
                'currency' => $agreement->currency_code ?: CurrencySettings::code(),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'created_by_employee' => auth()->id(),
                // تحصيلُ عميلٍ كأيّ تحصيل: الفرقُ في سببه لا في طبيعته
                'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
                'linking_type' => LinkingType::SINGLE,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $treasury->id,
                'description' => __('sales_installments.down_payment.debit_line'),
                'debit' => $amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $clientAccount->id,
                'description' => __('sales_installments.down_payment.credit_line'),
                'debit' => 0,
                'credit' => $amount,
                'is_debit' => false,
            ]);

            self::refreshInvoice($invoice);

            ModelsLog::create([
                'type' => 'payments',
                'type_id' => $payment->id,
                'type_log' => 'log',
                'icon' => 'create',
                'description' => __('sales_installments.log.down_payment_posted', [
                    'amount' => CurrencySettings::format($amount),
                    'code' => $agreement->code,
                    'invoice' => $invoice->code,
                ]),
                'created_by' => auth()->id(),
            ]);

            return $payment;
        });
    }

    /** السندُ المقدَّم المسجَّل لهذه الاتفاقية إن وُجد. */
    public static function existingFor(Installment $agreement): ?PaymentsProcess
    {
        return PaymentsProcess::query()
            ->where('installments_id', $agreement->id)
            ->where('payment_status', '!=', 5)
            ->first();
    }

    /**
     * متبقّي الفاتورة سقفٌ للمقدَّمة.
     *
     * فاتورةٌ سُدِّد أكثرُها قبل أن تُقسَّط: قبضُ مقدَّمةٍ كاملةٍ عليها
     * يجعل المحصَّل أكبرَ من الدَّين، ويظهر العميلُ دائناً لنا بلا سبب.
     */
    private static function capToInvoice(Invoice $invoice, float $amount): float
    {
        $collected = (float) PaymentsProcess::where('invoice_id', $invoice->id)
            ->where('payment_status', '!=', 5)
            ->sum('amount');

        $remaining = max(0, round((float) $invoice->grand_total - $collected, 2));

        return min($amount, $remaining);
    }

    /**
     * الخزينة: ما اختاره المستخدم، ثمّ خزينةُ الموظّف، ثمّ المضبوطة في
     * «الحسابات المحاسبية» — نفسُ ترتيب `SalesPaymentController`.
     */
    private static function resolveTreasury(?int $treasuryId): ?Account
    {
        if ($treasuryId && $account = Account::find($treasuryId)) {
            return $account;
        }

        $user = auth()->user();

        if ($user && $user->employee_id) {
            $link = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

            if ($link && $link->treasury_id && $account = Account::find($link->treasury_id)) {
                return $account;
            }
        }

        return AccountingSettings::account('main_treasury');
    }

    /**
     * حسابُ ذمّة العميل في دفتر الأستاذ.
     *
     * `ledger()` تستبعد حسابَ المحفظة: المحفظةُ التزامٌ علينا لا ذمّةٌ له
     * — انظر `Account::scopeLedger`.
     */
    private static function clientAccount(Installment $agreement, Invoice $invoice): ?Account
    {
        $clientId = $agreement->client_id ?: $invoice->client_id;

        return $clientId
            ? Account::ledger()->where('client_id', $clientId)->first()
            : null;
    }

    /** تحديث مسدَّد الفاتورة بعد السند. */
    private static function refreshInvoice(Invoice $invoice): void
    {
        $collected = (float) PaymentsProcess::where('invoice_id', $invoice->id)
            ->where('payment_status', '!=', 5)
            ->sum('amount');

        $invoice->advance_payment = $collected;

        /* الحالة لا تُلمَس هنا: المتحكّم يضعها «مقسَّطة» بعد هذا مباشرةً،
           وفاتورةٌ لها اتفاقيةُ تقسيط لا تُوصَف بـ«مسدَّدة جزئياً». */
        $invoice->save();
    }

    /** رقم السند من «تسلسل أرقام المستندات». */
    private static function documentNumber(): ?string
    {
        try {
            return next_document_number(self::NUMBER_SECTION);
        } catch (\Throwable $e) {
            /* ترقيمٌ غير مهيّأ لا يمنع قبضَ مال: يبقى السندُ بلا رقمٍ
               متسلسل ويُعرَف بمعرّفه، كما كان قبل الترقيم. */
            return null;
        }
    }
}
