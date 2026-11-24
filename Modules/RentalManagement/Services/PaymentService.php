<?php

namespace Modules\RentalManagement\Services;

use App\Enums\ChequeStatus;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentMethod;
use App\Models\ReceivedCheque;
use App\Services\ChequeService;
use App\Support\OperationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\DepositSettlement;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\Payment;
use Modules\RentalManagement\Models\PaymentAllocation;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateMoney;
use Modules\RentalManagement\Support\RealEstateSettings;

/**
 * تسجيل الدفعة وتوزيعها وترحيلها وإلغاؤها.
 *
 * الدفعة المرحّلة لا تُحذف ولا تُعدَّل: تُلغى بقيدٍ عكسي يبقى معه الأصل
 * مقروءاً في الدفاتر.
 *
 * قاعدة الترحيل:
 *   • مبنى **مُدار** لمالكٍ خارجي: صافي الإيجار وضريبته (سطراً مستقلاً) في
 *     الحساب الجاري للمالك، وعمولة المكتب إيرادٌ له وضريبتها في حساب ضريبة
 *     المنشأة.
 *   • مبنى **مملوك** للمنشأة أو بلا مالك: الإيجار إيراد، وضريبته في حساب
 *     ضريبة المنشأة، ولا عمولة ولا دفتر مالك.
 */
class PaymentService
{
    public const LOG = 're_payment_log';

    /** آخر دفعةٍ سُجّلت وُزّعت يدوياً متخطّيةً أقساطاً أقدم ما زالت مفتوحة — تنبيهٌ لا منع. */
    public bool $skippedOlder = false;

    /** نحن من يحذف الشيك (إلغاء الدفعة) — فلا يعيد مراقب الشيك إلغاءها. */
    public static bool $releasingCheque = false;

    /**
     * @param  array<int|string, float|string>  $allocations  schedule_id => amount.
     *                                                        فارغة = توزيعٌ آلي على الأقدم استحقاقاً.
     */
    public function record(Contract $contract, array $data, array $allocations = []): Payment
    {
        $amount = round((float) $data['amount'], 2);
        $manual = array_filter(array_map(fn ($v) => (float) $v, $allocations), fn ($v) => $v > 0) !== [];
        $allocations = $this->resolveAllocations($contract, $amount, $allocations);
        $allocated = round(array_sum($allocations), 2);

        if ($allocated > $amount) {
            throw ValidationException::withMessages(['amount' => 'إجمالي الموزَّع على الاستحقاقات يتجاوز مبلغ الدفعة.']);
        }

        $unallocated = round($amount - $allocated, 2);

        if ($unallocated > 0 && ! RealEstateSettings::enabled('allow_advance_payment')) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ يتجاوز رصيد الاستحقاقات المفتوحة بـ '.number_format($unallocated, 2)
                    .'. فعّل «الدفعة المقدّمة» في الإعدادات أو صحّح المبلغ.',
            ]);
        }

        $date = Carbon::parse($data['payment_date'])->startOfDay();

        if ($contract->start_date && $date->lt($contract->start_date->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'payment_date' => 'تاريخ الدفعة يسبق بداية العقد ('.$contract->start_date->format('Y-m-d').').',
            ]);
        }

        [$method, $kind] = $this->resolveMethod($data);
        $isCheque = $kind === 'cheque';

        // الشيك لا يدخل خزينةً عند استلامه: يُسجَّل في «الشيكات المستلمة» ويُحصَّل من هناك
        if ($isCheque) {
            $data['treasury_account_id'] = null;
            $this->assertChequeData($data);
        } else {
            // الطرف المدين خزينةٌ أو بنك حصراً — لا أيّ حسابٍ في الدليل
            RealEstateAccounts::assertTreasury($data['treasury_account_id'] ?? null, 'deposit');
        }

        RealEstateAccounts::assertPeriodOpen($data['payment_date'], 'payment_date');

        if (Payment::kindNeedsReference($kind) && blank($data['reference_no'] ?? null)) {
            throw ValidationException::withMessages(['reference_no' => 'اكتب رقم التحويل أو الشيك.']);
        }

        $this->skippedOlder = $manual && $this->skipsOlder($contract, $allocations);

        return DB::transaction(function () use ($contract, $data, $amount, $allocations, $unallocated, $method, $kind, $isCheque) {
            $payment = Payment::create([
                'code' => next_document_number('re-payment'),
                'contract_id' => $contract->id,
                'client_id' => $contract->tenant_client_id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'unallocated_amount' => $unallocated,
                'payment_method' => $kind,
                'payment_method_id' => $method?->id,
                'treasury_account_id' => $data['treasury_account_id'],
                'reference_no' => $data['reference_no'] ?? null,
                'cheque_due_date' => $isCheque ? ($data['cheque_due_date'] ?? null) : null,
                'cheque_bank' => $isCheque ? ($data['cheque_bank'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                // العملة لقطة: عملة العقد إن حُدّدت، وإلا عملة النظام يوم الدفع
                'currency' => $contract->currency ?: RealEstateMoney::code(),
                'posting_status' => 'draft',
            ]);

            // عمولة إدارة الأملاك تُخصم من نصيب المالك عند كل توزيع على قسط إيجار — للمبنى المُدار فقط
            $owner = RealEstateAccounts::managedOwner($contract);
            $ledger = app(OwnerLedgerService::class);
            $commissionTotal = 0.0;

            foreach ($allocations as $scheduleId => $value) {
                $schedule = PaymentSchedule::lockForUpdate()->findOrFail($scheduleId);
                $commission = $owner ? $ledger->commissionFor($owner, $schedule, (float) $value, (float) $contract->tax_rate) : 0.0;
                $commissionTotal += $commission;

                PaymentAllocation::create([
                    'payment_id' => $payment->id, 'schedule_id' => $schedule->id, 'amount' => $value, 'commission_amount' => $commission,
                ]);

                $schedule->amount_paid = round((float) $schedule->amount_paid + $value, 2);
                $schedule->refreshStatus();
            }

            $payment->commission_amount = round($commissionTotal, 2);
            $parts = $this->breakdown($payment, RealEstateSettings::commissionTaxRate());

            $payment->update([
                'commission_amount' => $parts['commission'],
                'tax_amount' => $parts['tax'],
                'owner_tax_amount' => $parts['owner'] ? $parts['tax'] : 0,
                'commission_tax_amount' => $parts['commission_tax'],
            ]);

            if ($isCheque && RealEstateSettings::enabled('post_payments')) {
                $this->receiveCheque($payment, $contract);
            } elseif (RealEstateSettings::enabled('post_payments')) {
                $this->post($payment, $parts);

                if ($parts['owner']) {
                    $ledger->recordPayment($payment->refresh(), $parts['owner'], $parts);
                }
            } else {
                // بلا قيد: لا تُعدّ «مرحّلة» ولا تمسّ دفتر المالك، فيبقى الدفتر مطابقاً لحسابه في الدليل
                $payment->update(['posting_status' => 'unposted']);
            }

            OperationLog::record('created', self::LOG, $payment, sprintf(
                'سُجّلت الدفعة **%s** بمبلغ **%s** على العقد **%s**',
                $payment->code, RealEstateMoney::format($amount, $payment->currency), $contract->code
            ));

            return $payment;
        });
    }

    /**
     * طريقة الدفع من طرق النظام ونوعُها المشتقّ. الطلب القديم الذي يحمل النوع
     * وحده (`payment_method`) يبقى مقبولاً.
     *
     * @return array{0: ?PaymentMethod, 1: string}
     */
    protected function resolveMethod(array $data): array
    {
        if (! empty($data['payment_method_id'])) {
            $method = PaymentMethod::where('is_active', true)->find($data['payment_method_id']);

            if (! $method) {
                throw ValidationException::withMessages(['payment_method_id' => 'طريقة الدفع غير موجودة أو غير مفعّلة في النظام.']);
            }

            return [$method, Payment::methodKind($method)];
        }

        $kind = $data['payment_method'] ?? 'cash';

        return [null, isset(Payment::METHOD_KINDS[$kind]) ? $kind : 'cash'];
    }

    /** هل تُرك قسطٌ مفتوح أقدم استحقاقاً من أحدث قسطٍ وُزّع عليه؟ */
    protected function skipsOlder(Contract $contract, array $allocations): bool
    {
        $open = $contract->schedules()->open()->get();
        $latest = $open->whereIn('id', array_keys($allocations))->max('due_date');

        if (! $latest) {
            return false;
        }

        return $open->contains(fn (PaymentSchedule $s) => $s->due_date->lt($latest)
            && round((float) ($allocations[$s->id] ?? 0), 2) < $s->balance);
    }

    /**
     * التحقّق من التوزيع اليدوي، أو بناء توزيعٍ آلي: الأقدم استحقاقاً أولاً.
     *
     * @return array<int, float>
     */
    protected function resolveAllocations(Contract $contract, float $amount, array $requested): array
    {
        $open = $contract->schedules()->open()->get()->keyBy('id');
        $requested = array_filter(array_map(fn ($v) => round((float) $v, 2), $requested), fn ($v) => $v > 0);

        if ($requested !== []) {
            foreach ($requested as $scheduleId => $value) {
                $schedule = $open->get((int) $scheduleId);

                if (! $schedule) {
                    throw ValidationException::withMessages(['allocations' => 'استحقاقٌ مختار لا يخصّ هذا العقد أو أُغلق.']);
                }

                if ($value > $schedule->balance) {
                    throw ValidationException::withMessages([
                        'allocations' => sprintf('الموزَّع على القسط %d يتجاوز رصيده المتبقّي (%s).',
                            $schedule->installment_no, number_format($schedule->balance, 2)),
                    ]);
                }
            }

            return $requested;
        }

        $result = [];
        $left = $amount;

        foreach ($open as $schedule) {
            if ($left <= 0) {
                break;
            }

            $take = min($left, $schedule->balance);
            $result[$schedule->id] = round($take, 2);
            $left = round($left - $take, 2);
        }

        return $result;
    }

    /**
     * تفصيل الدفعة إلى مكوّناتها المحاسبية.
     *
     * @param  float|null  $commissionTaxRate  نسبة ضريبة العمولة عند التسجيل؛ null = المحفوظ على الدفعة.
     * @return array{rent:float, net_rent:float, tax:float, deposit:float, maintenance:float, advance:float, commission:float, commission_tax:float, owner:?Owner}
     */
    public function breakdown(Payment $payment, ?float $commissionTaxRate = null): array
    {
        $payment->loadMissing('allocations.schedule', 'contract');
        $contract = $payment->contract;

        $rent = round((float) $payment->allocations->where('schedule.kind', 'rent')->sum('amount'), 2);
        $deposit = round((float) $payment->allocations->where('schedule.kind', 'deposit')->sum('amount'), 2);
        $maintenance = round((float) $payment->allocations->where('schedule.kind', 'maintenance')->sum('amount'), 2);

        // مبالغ الأقساط شاملةٌ الضريبة: تُستخرج منها بنسبة العقد
        $rate = (float) $contract->tax_rate / 100;
        $tax = $rate > 0 ? round($rent - $rent / (1 + $rate), 2) : 0.0;
        $net = round($rent - $tax, 2);

        $owner = RealEstateAccounts::managedOwner($contract);
        $commission = $owner ? round(min((float) $payment->commission_amount, $net), 2) : 0.0;

        $commissionTax = 0.0;

        if ($owner && $commission > 0) {
            $commissionTax = $commissionTaxRate === null
                ? (float) $payment->commission_tax_amount
                : round($commission * $commissionTaxRate / 100, 2);
            // العمولة وضريبتها تُخصمان من نصيب المالك ولا تتجاوزانه
            $commissionTax = round(max(0, min($commissionTax, $net - $commission)), 2);
        }

        return [
            'rent' => $rent, 'net_rent' => $net, 'tax' => $tax, 'deposit' => $deposit, 'maintenance' => $maintenance,
            'advance' => round((float) $payment->unallocated_amount, 2),
            'commission' => $commission, 'commission_tax' => $commissionTax, 'owner' => $owner,
        ];
    }

    /**
     * قيد الدفعة: الخزينة مدينة بالإجمالي، والدائن بحسب ما سُدّد وملكية
     * المبنى (انظر رأس الصنف).
     */
    public function post(Payment $payment, ?array $parts = null, ?string $date = null): void
    {
        $payment->loadMissing('allocations.schedule', 'contract', 'client.account');
        $contract = $payment->contract;
        $parts ??= $this->breakdown($payment);

        // دفعة الشيك تُرحَّل عند تحصيله: مدينها الحساب الوسيط الذي قُيِّد له الشيك (البنك قُيِّد في قيد التحصيل بالحسابات)
        $treasuryId = $payment->received_cheque_id
            ? RealEstateAccounts::id('cheque_clearing_account_id')
            : RealEstateAccounts::assertTreasury($payment->treasury_account_id, 'deposit');
        $owner = $parts['owner'];
        $lines = [];

        if ($owner) {
            $ownerAccount = app(OwnerLedgerService::class)->ensureAccount($owner)->id;
            $ownerNet = round($parts['net_rent'] - $parts['commission'] - $parts['commission_tax'], 2);

            if ($ownerNet > 0) {
                $lines[] = [$ownerAccount, $ownerNet, 'إيجار محصَّل للمالك '.$owner->name.' — '.$contract->code];
            }

            if ($parts['tax'] > 0) {
                // مورِّد خدمة التأجير هو المالك: ضريبتها تُحصَّل لحسابه وهو من يورّدها
                $lines[] = [$ownerAccount, $parts['tax'], 'ضريبة قيمة مضافة محصَّلة لحساب المالك '.$owner->name.' — '.$contract->code];
            }

            if ($parts['commission'] > 0) {
                $lines[] = [RealEstateAccounts::id('management_commission_account_id'), $parts['commission'], 'عمولة إدارة الأملاك ('.$owner->agreement_label.') — '.$contract->code];
            }

            if ($parts['commission_tax'] > 0) {
                $lines[] = [RealEstateAccounts::vatPayableId(), $parts['commission_tax'], 'ضريبة القيمة المضافة على عمولة إدارة الأملاك — '.$contract->code];
            }

            if ($parts['maintenance'] > 0) {
                $lines[] = [$ownerAccount, $parts['maintenance'], 'صيانة محصَّلة من المستأجر لحساب المالك '.$owner->name.' — '.$contract->code];
            }
        } else {
            if ($parts['net_rent'] > 0) {
                $lines[] = [RealEstateAccounts::id('rent_revenue_account_id'), $parts['net_rent'], 'إيراد إيجار — '.$contract->code];
            }

            if ($parts['tax'] > 0) {
                $lines[] = [RealEstateAccounts::vatPayableId(), $parts['tax'], 'ضريبة القيمة المضافة على الإيجار — '.$contract->code];
            }

            if ($parts['maintenance'] > 0) {
                $lines[] = [RealEstateAccounts::id('maintenance_expense_account_id'), $parts['maintenance'], 'استرداد تكلفة صيانة من المستأجر — '.$contract->code];
            }
        }

        if ($parts['deposit'] > 0) {
            $lines[] = [RealEstateAccounts::id('deposit_liability_account_id'), $parts['deposit'], 'تأمين مستأجر — '.$contract->code];
        }

        if ($parts['advance'] > 0) {
            $lines[] = [$this->existingAccount($payment->client->account->id ?? null, 'حساب العميل في الدليل المحاسبي'), $parts['advance'], 'دفعة مقدّمة — '.$contract->code];
        }

        $credit = round(array_sum(array_column($lines, 1)), 2);

        if (abs($credit - round((float) $payment->amount, 2)) > 0.004) {
            throw ValidationException::withMessages([
                'accounting' => sprintf('تعذّر الترحيل: القيد غير متوازن (مدين %s، دائن %s).', number_format((float) $payment->amount, 2), number_format($credit, 2)),
            ]);
        }

        $entry = JournalEntry::create([
            'reference_number' => $payment->code,
            'date' => $date ?? $payment->payment_date,
            'description' => ($payment->received_cheque_id ? 'تحصيل شيك دفعة إيجار رقم ' : 'تحصيل دفعة إيجار رقم ').$payment->code.' — عقد '.$contract->code,
            'status' => 1,
            'currency' => $payment->currency ?: null,
            'client_id' => $payment->client_id,
            'reference_type' => ReferenceType::REAL_ESTATE_RENT,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $payment->id,
            'created_by_employee' => auth()->id(),
            'approved_by_employee' => auth()->id(),
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $treasuryId,
            'description' => ($payment->received_cheque_id ? 'إقفال شيك محصَّل عن دفعة إيجار ' : 'استلام دفعة إيجار ').$payment->code,
            'debit' => $payment->amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        foreach ($lines as [$accountId, $value, $description]) {
            JournalEntryDetail::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'description' => $description,
                'debit' => 0,
                'credit' => round($value, 2),
                'is_debit' => false,
            ]);
        }

        $payment->update(['posting_status' => 'posted', 'journal_entry_id' => $entry->id]);

        RealEstateAccounts::refreshBalances(array_merge([$treasuryId], array_column($lines, 0)));
    }

    /* ─────────────────────────── الشيكات ─────────────────────────── */

    /** رقم الشيك رقميّ وغير مكرَّر في «الشيكات المستلمة»، واستحقاقه لا يسبق تاريخه. */
    protected function assertChequeData(array $data): void
    {
        $number = trim((string) ($data['reference_no'] ?? ''));

        if ($number === '' || ! ctype_digit($number)) {
            throw ValidationException::withMessages(['reference_no' => 'اكتب رقم الشيك (أرقام فقط).']);
        }

        if (ReceivedCheque::withoutGlobalScopes()->where('cheque_number', $number)->exists()) {
            throw ValidationException::withMessages(['reference_no' => 'رقم الشيك مسجَّل من قبل في «الشيكات المستلمة».']);
        }

        if (! empty($data['cheque_due_date']) && Carbon::parse($data['cheque_due_date'])->lt(Carbon::parse($data['payment_date'])->startOfDay())) {
            throw ValidationException::withMessages(['cheque_due_date' => 'تاريخ استحقاق الشيك لا يسبق تاريخ استلامه.']);
        }
    }

    /**
     * يسجّل شيك الدفعة في «الشيكات المستلمة» (الحسابات): قيده هناك
     * مدين شيكات تحت التحصيل / دائن الحساب الوسيط. الدفعة تبقى «شيك تحت
     * التحصيل» — أقساطها موزَّعة، ولا قيد لها ولا حركة للمالك حتى يُحصَّل.
     */
    protected function receiveCheque(Payment $payment, Contract $contract): void
    {
        $cheque = app(ChequeService::class)->receiveCheck([
            'amount' => $payment->amount,
            'cheque_number' => trim((string) $payment->reference_no),
            'issue_date' => Carbon::parse($payment->payment_date)->toDateString(),
            'due_date' => $payment->cheque_due_date ? Carbon::parse($payment->cheque_due_date)->toDateString() : null,
            'recipient_account_id' => RealEstateAccounts::id('cheque_clearing_account_id'),
            // بلا `client_id` عمداً: التحصيل هناك يسدّد فواتير المبيعات للعميل، وهذه دفعة عقار لا فاتورة
            'payee_name' => $contract->tenant?->display_name ?? $contract->tenant?->trade_name,
            'name' => 'دفعة عقار '.$payment->code,
            'description' => 'دفعة عقار '.$payment->code.' — عقد '.$contract->code,
            'drawer_bank' => $payment->cheque_bank,
        ]);

        $payment->update(['posting_status' => 'cheque_pending', 'received_cheque_id' => $cheque->id]);
    }

    /** حُصِّل الشيك (أو ظُهِّر) من شاشة الشيكات: تُرحَّل الدفعة ويُقيَّد نصيب المالك. */
    public function chequeCollected(Payment $payment): void
    {
        if ($payment->posting_status !== 'cheque_pending') {
            return;
        }

        RealEstateAccounts::assertPeriodOpen(now(), 'payment');

        DB::transaction(function () use ($payment) {
            $parts = $this->breakdown($payment);
            $this->post($payment, $parts, now()->toDateString());

            if ($parts['owner']) {
                app(OwnerLedgerService::class)->recordPayment($payment->refresh(), $parts['owner'], $parts);
            }
        });

        OperationLog::record('posted', self::LOG, $payment, sprintf('حُصِّل شيك الدفعة **%s** فرُحِّلت', $payment->code));
    }

    /** ارتُجع الشيك أو حُذف من شاشة الشيكات: تُلغى الدفعة وتعود أقساطها مفتوحة — قيد الارتجاع هناك أقفل الوسيط. */
    public function chequeBounced(Payment $payment, string $reason): void
    {
        if ($payment->posting_status !== 'cheque_pending') {
            return;
        }

        $this->cancel($payment, $reason, true);
    }

    /** إلغاء دفعة شيك من العقارات: يُسحب الشيك من «الشيكات المستلمة» ما دام لم يُودَع. */
    protected function releaseCheque(Payment $payment): void
    {
        $cheque = $payment->received_cheque_id ? ReceivedCheque::withoutGlobalScopes()->find($payment->received_cheque_id) : null;

        if (! $cheque) {
            return;
        }

        $status = $cheque->status instanceof ChequeStatus ? $cheque->status : ChequeStatus::tryFrom((string) $cheque->status);

        if ($status !== ChequeStatus::PENDING) {
            throw ValidationException::withMessages(['payment' => sprintf(
                'شيك هذه الدفعة (%s) حالته «%s» في الحسابات — يُعالَج من شاشة «الشيكات المستلمة» (ارتجاعه يلغي الدفعة تلقائياً).',
                $cheque->cheque_number, $status?->getName() ?? (string) $cheque->status)]);
        }

        RealEstateAccounts::assertPeriodOpen($cheque->issue_date, 'payment');

        self::$releasingCheque = true;

        try {
            app(ChequeService::class)->createReceivedChequeReversalEntry($cheque, 'إلغاء دفعة العقار '.$payment->code);
            $cheque->delete();
        } finally {
            self::$releasingCheque = false;
        }
    }

    /** إلغاء دفعة: فكّ التوزيع وقيدٌ عكسي — لا حذف. */
    public function cancel(Payment $payment, ?string $reason, bool $fromCheque = false): void
    {
        if ($payment->posting_status === 'cancelled') {
            return;
        }

        if ($payment->received_cheque_id && ! $fromCheque) {
            if ($payment->posting_status === 'posted') {
                throw ValidationException::withMessages(['payment' => 'هذه الدفعة حُصِّل شيكها ودخل البنك — لا تُلغى من هنا. ردّ المبلغ للمستأجر يُسجَّل بسند صرف من الحسابات.']);
            }

            DB::transaction(function () use ($payment, $reason) {
                $this->releaseCheque($payment);
                $this->cancel($payment, $reason, true);
            });

            return;
        }

        if ($payment->journal_entry_id) {
            // القيد العكسي يُؤرَّخ اليوم؛ والأصل في فترته — كلاهما يجب أن يكون مفتوحاً
            RealEstateAccounts::assertPeriodOpen($payment->payment_date, 'payment');
            RealEstateAccounts::assertPeriodOpen(now(), 'payment');
        }

        $payment->loadMissing('allocations.schedule');

        if ($payment->allocations->contains(fn ($a) => $a->schedule?->kind === 'deposit')
            && DepositSettlement::withoutGlobalScopes()->where('contract_id', $payment->contract_id)->exists()) {
            throw ValidationException::withMessages(['payment' => 'سُوّي تأمين هذا العقد — لا تُلغى دفعةٌ قُبض بها التأمين بعد تسويته.']);
        }

        DB::transaction(function () use ($payment, $reason) {
            foreach ($payment->allocations()->with('schedule')->get() as $allocation) {
                $schedule = $allocation->schedule;
                $schedule->amount_paid = max(0, round((float) $schedule->amount_paid - (float) $allocation->amount, 2));
                $schedule->refreshStatus();
            }

            $reversalId = null;

            if ($payment->journal_entry_id && ($original = JournalEntry::with('details')->find($payment->journal_entry_id))) {
                $reversal = JournalEntry::create([
                    'reference_number' => $payment->code.'-REV',
                    'date' => now()->toDateString(),
                    'description' => 'إلغاء دفعة الإيجار رقم '.$payment->code,
                    'status' => 1,
                    'currency' => $original->currency,
                    'client_id' => $payment->client_id,
                    'reference_type' => ReferenceType::PAYMENT_CANCELLATION,
                    'linking_type' => LinkingType::SINGLE,
                    'reference_id' => $payment->id,
                    'created_by_employee' => auth()->id(),
                    'approved_by_employee' => auth()->id(),
                ]);

                foreach ($original->details as $detail) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $reversal->id,
                        'account_id' => $detail->account_id,
                        'description' => 'عكس: '.$detail->description,
                        'debit' => $detail->credit,
                        'credit' => $detail->debit,
                        'is_debit' => (float) $detail->credit > 0,
                    ]);
                }

                $original->update(['is_reversed' => 1]);
                $reversalId = $reversal->id;
                RealEstateAccounts::refreshBalances($original->details->pluck('account_id')->all());
            }

            app(OwnerLedgerService::class)->reversePayment($payment);

            $payment->update([
                'posting_status' => 'cancelled',
                'reversal_journal_entry_id' => $reversalId,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
        });

        OperationLog::record('cancelled', self::LOG, $payment,
            sprintf('أُلغيت الدفعة **%s**%s', $payment->code, $reason ? ' — '.$reason : ''));
    }

    protected function existingAccount($id, string $label): int
    {
        if (! $id || ! Account::whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                'accounting' => 'تعذّر الترحيل: لم يُحدَّد «'.$label.'».',
            ]);
        }

        return (int) $id;
    }
}
