<?php

namespace App\Services\Installments;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Installment;
use App\Models\InstallmentDetail;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentsProcess;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * تمويل التقسيط في دفتر الأستاذ — المرابحة والرسوم الإدارية وضريبتهما.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان
 * ══════════════════════════════════════════════════════════════════════
 *
 * الاتفاقيةُ تُقسّط «الفاتورة + الربح + الرسوم + الضريبة»، ودفترُ الأستاذ
 * لا يعرف إلا الفاتورة. فسقفُ التحصيل إجماليُّ الفاتورة: «سداد الكل»
 * يقف عنده، ويبقى من آخر قسطٍ مقدارُ التمويل لا يُقبَل عليه سند، ولا
 * يدخل الإيرادَ ربحٌ ولا رسم، ولا تصل الضريبةُ إلى حسابها.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما صار — قيدان، كلاهما بالفرق
 * ══════════════════════════════════════════════════════════════════════
 *
 * **١. التمويل** (`sync`) — عند حفظ الاتفاقية وتعديلها وإلغاء قسطٍ منها
 * وحذفها:
 *
 *     من ح/ العميل                         ربح + رسوم + ضريبة
 *         إلى ح/ أرباح مرابحة مؤجلة             الربح
 *         إلى ح/ رسوم إدارية على التقسيط        الرسوم
 *         إلى ح/ ضريبة التقسيط                  الضريبة
 *
 * **تحقّقُ الربح مركّبٌ مع سند القبض، والتمويلُ مستقلّ.** الربحُ يتحقّق
 * بالتحصيل نفسِه، فتُضاف أسطرُه إلى قيد السند (`recognize(..., $into)`).
 * أمّا التمويلُ فقيدٌ وحدَه ولو قُبضت دفعةٌ مقدَّمة معه: دمجُهما يُظهر
 * حسابَ العميل دائناً بالمقدَّمة ومديناً بالتمويل في قيدٍ واحد، وهما
 * حدثان مختلفان (المراجعة المحاسبية، 2026-09-16). و`sync(..., $into)`
 * باقٍ لمن يحتاجه، ولا يُنادى به اليوم.
 *
 * المستهدَف مجموعُ مكوّنات الأقساط **غير الملغاة**، ويُرحَّل الفرقُ بينه
 * وبين ما رُحِّل (`financing_*_posted`). فالحفظُ يرحّل الكلّ، وإلغاءُ قسطٍ
 * يعكس نصيبَه، والحذفُ يعكس الباقي — وتكرارُ التشغيل لا يُضاعف شيئاً.
 *
 * **٢. تحقّق الربح** (`recognize`) — مع كلّ تحصيل:
 *
 *     من ح/ أرباح مرابحة مؤجلة     xx
 *         إلى ح/ أرباح المرابحة          xx
 *
 * المستهدَف نصيبُ المحصَّل من ربح كلّ قسط (ربحُ القسط × مسدَّدُه ÷ مبلغُه)،
 * فإلغاءُ سندٍ يعكس ما تحقّق منه.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  وسقفُ التحصيل
 * ══════════════════════════════════════════════════════════════════════
 *
 * `invoiceClaim()` = إجمالي الفاتورة + التمويل المرحَّل + الغرامات
 * المحمَّلة. هو ما على العميل من هذه الفاتورة في دفتر الأستاذ، وعليه
 * تُبنى حالةُ السداد ومتبقّي الفاتورة.
 */
class InstallmentFinancing
{
    public const DEFERRED_PROFIT_KEY = 'installment_deferred_profit';

    public const PROFIT_REVENUE_KEY = 'installment_profit_revenue';

    public const ADMIN_FEES_KEY = 'installment_admin_fees';

    /* ضريبةُ التقسيط في حسابها (2146) لا في «ضريبة المبيعات» (2144) —
       فيُفصَل ما استُحقّ على التمويل عمّا استُحقّ على البضاعة. */
    public const VAT_KEY = 'installment_vat_payable';

    private const EPSILON = 0.009;

    // ══════════════════ ١. التمويل ══════════════════

    /**
     * ما يجب أن يكون مرحَّلاً الآن — من الأقساط غير الملغاة.
     *
     * @return array{profit: float, fees: float, tax: float}
     */
    public static function target(Installment $agreement): array
    {
        if ($agreement->status === Installment::STATUS_CANCELLED || ! $agreement->exists) {
            return ['profit' => 0.0, 'fees' => 0.0, 'tax' => 0.0];
        }

        $rows = $agreement->details()
            ->where('status', '!=', InstallmentDetail::STATUS_CANCELLED)
            ->get(['profit_amount', 'fees_amount', 'tax_amount']);

        return [
            'profit' => round((float) $rows->sum('profit_amount'), 2),
            'fees' => round((float) $rows->sum('fees_amount'), 2),
            'tax' => round((float) $rows->sum('tax_amount'), 2),
        ];
    }

    /**
     * يرحّل فرقَ التمويل بين المستهدَف والمرحَّل.
     *
     * @param  array{profit: float, fees: float, tax: float}|null  $target  يُمرَّر صفراً عند الحذف
     * @param  JournalEntry|null  $into  قيدُ السند الذي تُضاف إليه الأسطر — قيدٌ مركّب
     * @return float صافي ما رُحِّل على العميل الآن (سالبٌ عند العكس)
     *
     * @throws RuntimeException إن لم يُربط حسابٌ لازم
     */
    public static function sync(Installment $agreement, ?array $target = null, ?JournalEntry $into = null): float
    {
        $target ??= self::target($agreement);

        $delta = [
            'profit' => round($target['profit'] - (float) $agreement->financing_profit_posted, 2),
            'fees' => round($target['fees'] - (float) $agreement->financing_fees_posted, 2),
            'tax' => round($target['tax'] - (float) $agreement->financing_tax_posted, 2),
        ];

        $total = round(array_sum($delta), 2);

        if (max(array_map('abs', $delta)) <= self::EPSILON) {
            return 0.0;
        }

        // الربح المؤجَّل لا يُعكَس تحت ما تحقّق منه
        if ((float) $agreement->profit_recognized > $target['profit'] + self::EPSILON) {
            throw new RuntimeException(__('sales_installments.messages.has_payments'));
        }

        $accounts = [
            'profit' => abs($delta['profit']) > self::EPSILON ? self::account(self::DEFERRED_PROFIT_KEY, 'deferred_profit_account') : null,
            'fees' => abs($delta['fees']) > self::EPSILON ? self::account(self::ADMIN_FEES_KEY, 'admin_fees_account') : null,
            'tax' => abs($delta['tax']) > self::EPSILON ? self::account(self::VAT_KEY, 'vat_account') : null,
        ];

        $client = self::clientAccount($agreement);

        return DB::transaction(function () use ($agreement, $delta, $total, $accounts, $client, $target, $into) {
            $journal = $into
                ? self::extend($into, __('sales_installments.financing.compound_financing', ['code' => self::code($agreement)]))
                : self::journal(
                    $agreement,
                    ReferenceType::INSTALLMENT_FINANCING,
                    sprintf('%s-FIN', self::code($agreement)),
                    __($total >= 0 ? 'sales_installments.financing.journal' : 'sales_installments.financing.journal_reversal', [
                        'code' => self::code($agreement),
                    ])
                );

            // ذمّة العميل: تزيد بالتحميل وتنقص بالعكس
            if (abs($total) > self::EPSILON) {
                self::line($journal, $client, $total, __('sales_installments.financing.client_line', ['code' => self::code($agreement)]));
            }

            foreach (['profit', 'fees', 'tax'] as $part) {
                if ($accounts[$part]) {
                    // الطرف الدائن: موجبُ الفرق دائن، وسالبُه مدين
                    self::line($journal, $accounts[$part], -$delta[$part], __('sales_installments.financing.'.$part.'_line', [
                        'code' => self::code($agreement),
                    ]));
                }
            }

            $agreement->forceFill([
                'financing_profit_posted' => $target['profit'],
                'financing_fees_posted' => $target['fees'],
                'financing_tax_posted' => $target['tax'],
            ])->save();

            if ($agreement->invoice) {
                self::refreshInvoice($agreement->invoice);
            }

            return $total;
        });
    }

    // ══════════════════ ٢. تحقّق الربح ══════════════════

    /** نصيبُ المحصَّل من ربح الأقساط — مقصوصاً إلى ما رُحِّل مؤجَّلاً. */
    public static function earnedProfit(Installment $agreement): float
    {
        $earned = 0.0;

        foreach ($agreement->details()->get(['amount', 'paid_amount', 'profit_amount']) as $row) {
            $amount = (float) $row->amount;

            if ($amount <= 0 || (float) $row->paid_amount <= 0) {
                continue;
            }

            $ratio = min(1, (float) $row->paid_amount / $amount);

            // القسطُ المسدَّد كاملاً يأخذ ربحَه بالضبط — فلا كسرٌ يبقى مؤجَّلاً
            $earned += $ratio >= 1 - 1e-9 ? (float) $row->profit_amount : (float) $row->profit_amount * $ratio;
        }

        return round(min($earned, (float) $agreement->financing_profit_posted), 2);
    }

    /**
     * ينقل الفرقَ بين المحقَّق المستهدَف وما نُقل من قبل.
     *
     * @param  JournalEntry|null  $into  قيدُ سند القبض الذي حُصِّل به — تُضاف إليه الأسطر
     * @return float ما نُقل الآن (سالبٌ عند إلغاء سند)
     */
    public static function recognize(Installment $agreement, ?JournalEntry $into = null): float
    {
        $agreement->refresh();

        $delta = round(self::earnedProfit($agreement) - (float) $agreement->profit_recognized, 2);

        if (abs($delta) <= self::EPSILON) {
            return 0.0;
        }

        $deferred = self::account(self::DEFERRED_PROFIT_KEY, 'deferred_profit_account');
        $revenue = self::account(self::PROFIT_REVENUE_KEY, 'profit_revenue_account');

        return DB::transaction(function () use ($agreement, $delta, $deferred, $revenue, $into) {
            $journal = $into
                ? self::extend($into, __('sales_installments.financing.compound_recognition', ['code' => self::code($agreement)]))
                : self::journal(
                    $agreement,
                    ReferenceType::INSTALLMENT_PROFIT,
                    sprintf('%s-PR', self::code($agreement)),
                    __($delta >= 0 ? 'sales_installments.financing.recognition' : 'sales_installments.financing.recognition_reversal', [
                        'code' => self::code($agreement),
                    ])
                );

            self::line($journal, $deferred, $delta, __('sales_installments.financing.deferred_line'));
            self::line($journal, $revenue, -$delta, __('sales_installments.financing.revenue_line', ['code' => self::code($agreement)]));

            $agreement->forceFill([
                'profit_recognized' => round((float) $agreement->profit_recognized + $delta, 2),
            ])->save();

            return $delta;
        });
    }

    // ══════════════════ الفاتورة ══════════════════

    /**
     * ما على العميل من هذه الفاتورة: إجماليُّها والتمويلُ المرحَّل
     * والغراماتُ المحمَّلة — سقفُ التحصيل وأساسُ متبقّيها.
     */
    public static function invoiceClaim(?Invoice $invoice): float
    {
        if (! $invoice) {
            return 0.0;
        }

        $total = (float) ($invoice->grand_total ?? 0);

        try {
            $financing = (float) Installment::where('invoice_id', $invoice->id)
                ->selectRaw('COALESCE(SUM(financing_profit_posted + financing_fees_posted + financing_tax_posted), 0) AS posted')
                ->value('posted');

            // الغرامةُ **المحمَّلة** لا غيرُ المحصَّلة: سنداتُ الغرامة
            // المحصَّلة داخلةٌ في مجموع المقبوض الذي يُطرح من هذا السقف
            $fees = (float) InstallmentDetail::query()
                ->whereIn(
                    'installments_id',
                    Installment::where('invoice_id', $invoice->id)
                        ->where('status', '!=', Installment::STATUS_CANCELLED)
                        ->select('id')
                )
                ->selectRaw('COALESCE(SUM(late_fee_amount), 0) AS charged')
                ->value('charged');
        } catch (\Throwable $e) {
            // الأعمدة غير مرحَّلة بعد: السقفُ إجماليّ الفاتورة كما كان
            $financing = 0.0;
            $fees = 0.0;
        }

        return round($total + max(0, $financing) + max(0, $fees), 2);
    }

    /** المقبوض على الفاتورة — السندات غير الملغاة. */
    public static function invoiceCollected(Invoice $invoice): float
    {
        return round((float) PaymentsProcess::where('invoice_id', $invoice->id)
            ->where('type', 'client payments')
            ->where('payment_status', '!=', 5)
            ->sum('amount'), 2);
    }

    /** متبقّي الفاتورة وحالةُ سدادها من السقف لا من إجماليها وحده. */
    public static function refreshInvoice(Invoice $invoice): void
    {
        $claim = self::invoiceClaim($invoice);
        $collected = self::invoiceCollected($invoice);

        $invoice->advance_payment = $collected;
        $invoice->due_value = max(0, round($claim - $collected, 2));
        $invoice->is_paid = $collected >= $claim - self::EPSILON && $claim > 0;
        $invoice->payment_status = match (true) {
            $invoice->is_paid => 1,
            $collected > 0 => 2,
            default => 3,
        };

        $invoice->save();
    }

    // ══════════════════ أدوات ══════════════════

    private static function journal(Installment $agreement, ReferenceType $type, string $reference, string $description): JournalEntry
    {
        return JournalEntry::create([
            'reference_number' => $reference,
            'date' => now(),
            'description' => $description,
            'status' => 1,
            'currency' => $agreement->currency_code ?: CurrencySettings::code(),
            'client_id' => $agreement->client_id,
            'invoice_id' => $agreement->invoice_id,
            'branch_id' => $agreement->branch_id,
            'created_by_employee' => auth()->id(),
            'reference_type' => $type,
            'linking_type' => LinkingType::SINGLE,
        ]);
    }

    /**
     * قيدُ السند يصير مركّباً: نوعُه ومرجعُه يبقيان (تحصيلُ عميل يُعدّ في
     * تقارير التحصيل)، ويُلحَق بوصفه ما أُضيف إليه.
     */
    private static function extend(JournalEntry $journal, string $suffix): JournalEntry
    {
        if (! str_contains((string) $journal->description, $suffix)) {
            $journal->update(['description' => trim($journal->description.' + '.$suffix)]);
        }

        return $journal;
    }

    /** حساباتُ أسطر التقسيط التي قد تُضاف إلى قيد سند — لا يمسّها تعديلُ السند. */
    public static function financingAccountIds(): array
    {
        return array_values(array_filter(array_map(
            fn (string $key) => AccountingSettings::id($key),
            [self::DEFERRED_PROFIT_KEY, self::PROFIT_REVENUE_KEY, self::ADMIN_FEES_KEY, self::VAT_KEY]
        )));
    }

    /** موجبُ المبلغ مدين وسالبُه دائن — والأرصدةُ يُعيد حسابَها الحدثُ لا اليد. */
    private static function line(JournalEntry $journal, Account $account, float $signed, string $description): void
    {
        $amount = round(abs($signed), 2);

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $account->id,
            'description' => $description,
            'debit' => $signed > 0 ? $amount : 0,
            'credit' => $signed > 0 ? 0 : $amount,
            'is_debit' => $signed > 0,
        ]);
    }

    private static function account(string $key, string $label): Account
    {
        $account = AccountingSettings::account($key);

        if (! $account) {
            throw new RuntimeException(__('settings.accounting_settings.account_not_mapped', [
                'account' => __('sales_installments.financing.'.$label),
            ]));
        }

        return $account;
    }

    private static function clientAccount(Installment $agreement): Account
    {
        $account = $agreement->client_id
            ? Account::ledger()->where('client_id', $agreement->client_id)->first()
            : null;

        if (! $account) {
            throw new RuntimeException(__('sales_installments.messages.no_client_account'));
        }

        return $account;
    }

    private static function code(Installment $agreement): string
    {
        return $agreement->code ?: ('INS'.$agreement->id);
    }
}
