<?php

namespace Modules\RentalManagement\Services;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\OperationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\MaintenanceRequest;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\OwnerEntry;
use Modules\RentalManagement\Models\Payment;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateMoney;

/**
 * دفتر المالك وحسابه الجاري — وفق الدورة المحاسبية لإدارة العقارات:
 *
 *   • ما يُحصَّل من المستأجر نيابةً عن المالك ليس إيراداً للمكتب: يدخل حسابَ
 *     المالك الجاري (له)، ومعه ضريبة الإيجار المحصَّلة لحسابه بسطرٍ مستقل.
 *   • عمولة إدارة الأملاك وضريبتها تُخصمان من حساب المالك.
 *   • الصيانة على المالك تُخصم من حسابه، والتحويل إليه يُنقص رصيده.
 *
 * قاعدةٌ لا تُكسر: كل حركةٍ في الدفتر يقابلها سطرٌ في قيد يومية على الحساب
 * الجاري للمالك — فرصيد الدفتر = رصيد حسابه في الدليل دائماً.
 */
class OwnerLedgerService
{
    public const LOG = 're_owner_log';

    public const COMMISSION_ACCOUNT_NAME = 'إيرادات إدارة الأملاك';

    /* ─────────────────────────── الحسابات ─────────────────────────── */

    /** حساب المالك الجاري: فرعٌ في الخصوم تحت «مستحقات ملاك العقار». */
    public function ensureAccount(Owner $owner): Account
    {
        if ($owner->account_id && ($account = Account::find($owner->account_id))) {
            return $account;
        }

        $properties = app(PropertyAccountService::class);
        $parent = RealEstateAccounts::ownerPayables();

        $account = Account::create([
            'name' => 'جاري المالك — '.$owner->name,
            'code' => $properties->nextCode($parent),
            'parent_id' => $parent->id,
            'type' => 'sub',
            'account_type' => 'liabilities',
            'balance_type' => 'credit',
            'balance' => 0,
            'is_active' => 1,
            'branch_id' => $owner->branch_id,
        ]);

        $owner->forceFill(['account_id' => $account->id])->saveQuietly();

        return $account;
    }

    public function syncAccountName(Owner $owner): void
    {
        if ($owner->account_id && $owner->wasChanged('name')) {
            Account::whereKey($owner->account_id)->update(['name' => 'جاري المالك — '.$owner->name]);
        }
    }

    /** حساب «إيرادات إدارة الأملاك»: من الإعدادات، وإلا يُنشأ تحت جذر الإيرادات. */
    public function commissionAccount(): Account
    {
        return RealEstateAccounts::ensure('management_commission_account_id');
    }

    /* ─────────────────────────── الأرصدة ─────────────────────────── */

    public function balance(Owner $owner): float
    {
        $row = OwnerEntry::withoutGlobalScopes()->where('owner_id', $owner->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance_total")
            ->toBase()->first();

        return round((float) ($row->balance_total ?? 0), 2);
    }

    /** إيجارٌ مستحق على المستأجرين لم يُحصَّل بعد — استحقاق المالك المحاسبي قبل أن يصير متاحاً للتحويل. */
    public function accruedNotCollected(Owner $owner): float
    {
        $due = PaymentSchedule::query()->where('kind', 'rent')->whereNotIn('status', ['cancelled', 'paid'])
            ->whereIn('contract_id', Contract::ofOwner($owner->id)->whereIn('status', Contract::COLLECTIBLE_STATUSES)->select('re_contracts.id'))
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid), 0) as due_total')->toBase()->first();

        return round((float) ($due->due_total ?? 0), 2);
    }

    /* ─────────────────────────── العمولة ─────────────────────────── */

    /**
     * عمولة الإدارة على مبلغٍ يُوزَّع على قسط إيجار.
     *
     * الأساس صافي الإيجار بلا ضريبة:
     *  - «من المحصَّل»: نسبةٌ من المبلغ المسدَّد نفسه، أو حصّته من المبلغ الثابت
     *    بنسبة ما سُدِّد من القسط.
     *  - «من المستحق»: عمولة القسط كاملةً تُخصم على الدفعات بالتتابع حتى تُستوفى
     *    (دون أن تتجاوز صافي المسدَّد في الدفعة).
     */
    public function commissionFor(Owner $owner, PaymentSchedule $schedule, float $allocated, float $taxRate = 0): float
    {
        if ($allocated <= 0 || $schedule->kind !== 'rent' || (float) $owner->agreement_value <= 0) {
            return 0.0;
        }

        $divisor = 1 + max(0, $taxRate) / 100;
        $net = $allocated / $divisor;
        $dueNet = (float) $schedule->amount_due / $divisor;
        $value = (float) $owner->agreement_value;

        if ($owner->commission_basis === 'due') {
            $target = $owner->isPercentage() ? $dueNet * $value / 100 : $value;
            $charged = (float) DB::table('re_payment_allocations as a')
                ->join('re_payments as p', 'p.id', '=', 'a.payment_id')
                ->where('a.schedule_id', $schedule->id)->where('p.posting_status', '!=', 'cancelled')->sum('a.commission_amount');

            return round(max(0, min($net, $target - $charged)), 2);
        }

        $commission = $owner->isPercentage()
            ? $net * $value / 100
            : ($dueNet > 0 ? $value * min(1, $net / $dueNet) : 0);

        return round(max(0, min($net, $commission)), 2);
    }

    /* ─────────────────────────── دفتر الدفعات ─────────────────────────── */

    /**
     * بعد ترحيل الدفعة: حركات المالك تطابق سطور قيدها على حسابه الجاري —
     * إيجار (له)، ضريبة محصَّلة لحسابه (له)، عمولة وضريبتها (عليه)، وصيانة
     * استُردّت له من المستأجر (له).
     *
     * @param  array{net_rent:float, tax:float, commission:float, commission_tax:float, maintenance:float}  $parts
     */
    public function recordPayment(Payment $payment, Owner $owner, array $parts): void
    {
        $payment->loadMissing('contract.unit');
        $contract = $payment->contract;
        $base = [
            'entry_date' => $payment->payment_date,
            'building_id' => $contract->building_id ?? $contract->unit?->building_id,
            'unit_id' => $contract->unit_id,
            'contract_id' => $contract->id,
            'payment_id' => $payment->id,
            'journal_entry_id' => $payment->journal_entry_id,
            'currency' => $payment->currency,
        ];

        $rows = [
            ['rent', 'credit', $parts['net_rent'], 'إيجار محصَّل — عقد '.$contract->code.' — دفعة '.$payment->code],
            ['tax', 'credit', $parts['tax'], 'ضريبة قيمة مضافة محصَّلة لحسابه — عقد '.$contract->code.' — دفعة '.$payment->code],
            ['commission', 'debit', $parts['commission'], 'عمولة إدارة الأملاك ('.$owner->agreement_label.') — دفعة '.$payment->code],
            ['commission_tax', 'debit', $parts['commission_tax'], 'ضريبة القيمة المضافة على عمولة الإدارة — دفعة '.$payment->code],
            ['recovery', 'credit', $parts['maintenance'], 'صيانة محصَّلة من المستأجر — عقد '.$contract->code.' — دفعة '.$payment->code],
        ];

        foreach ($rows as [$type, $direction, $amount, $description]) {
            if ((float) $amount > 0) {
                $this->addEntry($owner, $base + ['type' => $type, 'direction' => $direction, 'amount' => $amount, 'description' => $description]);
            }
        }
    }

    /** إلغاء الدفعة: حركاتها في دفتر المالك تُعكس بحركاتٍ مقابلة (لا حذف). */
    public function reversePayment(Payment $payment): void
    {
        $entries = OwnerEntry::withoutGlobalScopes()->where('payment_id', $payment->id)
            ->where('type', '!=', 'reversal')->whereDoesntHave('reversal')->get();

        foreach ($entries as $entry) {
            $this->mirror($entry, 'إلغاء دفعة '.$payment->code, now()->toDateString());
        }
    }

    /* ─────────────────────────── التحويل للمالك ─────────────────────────── */

    /**
     * تحويل مستحقات المالك: مدين حسابه الجاري، دائن الخزينة/البنك. يحمل رقم
     * مستندٍ من تسلسل النظام هو مرجع قيده.
     *
     * @param  array{amount:float|string, date:string, treasury_account_id:int|string, reference_no?:?string, notes?:?string}  $data
     */
    public function transfer(Owner $owner, array $data): OwnerEntry
    {
        $amount = round((float) $data['amount'], 2);
        $available = $this->balance($owner);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'أدخل مبلغاً صحيحاً.']);
        }

        if ($amount > $available) {
            throw ValidationException::withMessages(['amount' => 'المبلغ يتجاوز رصيد المالك المتاح للتحويل ('.number_format($available, 2).').']);
        }

        $treasuryId = RealEstateAccounts::assertTreasury($data['treasury_account_id'] ?? null, 'withdraw');
        RealEstateAccounts::assertPeriodOpen($data['date'], 'date');

        return DB::transaction(function () use ($owner, $data, $amount, $treasuryId) {
            $account = $this->ensureAccount($owner);
            $number = RealEstateAccounts::nextNumber('re-owner-transfer', 'OWT');
            $bankRef = filled($data['reference_no'] ?? null) ? ' — مرجع '.$data['reference_no'] : '';
            $currency = RealEstateMoney::code();

            $journal = $this->postJournal([
                'reference_number' => $number,
                'date' => $data['date'],
                'description' => 'تحويل مستحقات المالك '.$owner->name.' رقم '.$number.$bankRef,
                'reference_type' => ReferenceType::REAL_ESTATE_OWNER_TRANSFER,
                'reference_id' => $owner->id,
                'currency' => $currency,
            ], [
                [$account->id, $amount, 0, 'تحويل للمالك '.$owner->name.' — '.$number],
                [$treasuryId, 0, $amount, 'صرف مستحقات مالك '.$owner->code.' — '.$number],
            ]);

            $row = $this->addEntry($owner, [
                'entry_date' => $data['date'], 'type' => 'transfer', 'direction' => 'debit', 'amount' => $amount,
                'journal_entry_id' => $journal->id, 'reference_no' => $number, 'currency' => $currency,
                'description' => 'تحويل للمالك رقم '.$number.$bankRef.(filled($data['notes'] ?? null) ? ' — '.$data['notes'] : ''),
            ]);

            OperationLog::record('created', self::LOG, $owner, sprintf('حُوِّل للمالك **%s** مبلغ **%s** بالمستند **%s**',
                $owner->name, RealEstateMoney::format($amount, $currency), $number));

            return $row;
        });
    }

    /** إلغاء تحويل: قيدٌ عكسي وحركةٌ مقابلة في الدفتر — الأصل يبقى مقروءاً. */
    public function cancelTransfer(Owner $owner, OwnerEntry $entry, string $reason): OwnerEntry
    {
        if ((int) $entry->owner_id !== (int) $owner->id || $entry->type !== 'transfer') {
            throw ValidationException::withMessages(['transfer' => 'الحركة المختارة ليست تحويلاً لهذا المالك.']);
        }

        if (OwnerEntry::withoutGlobalScopes()->where('reverses_entry_id', $entry->id)->exists()) {
            throw ValidationException::withMessages(['transfer' => 'هذا التحويل مُلغى سابقاً.']);
        }

        RealEstateAccounts::assertPeriodOpen($entry->entry_date, 'transfer');
        RealEstateAccounts::assertPeriodOpen(now(), 'transfer');

        return DB::transaction(function () use ($owner, $entry, $reason) {
            $date = now()->toDateString();
            $reversalId = null;

            if ($entry->journal_entry_id && ($original = JournalEntry::with('details')->find($entry->journal_entry_id))) {
                $reversalId = $this->reverseJournal($original, $date, ReferenceType::REAL_ESTATE_OWNER_TRANSFER, $owner->id, 'إلغاء تحويل المالك — '.$reason)->id;
            }

            $mirror = $this->mirror($entry, 'إلغاء التحويل: '.$reason, $date, $reversalId);

            OperationLog::record('cancelled', self::LOG, $owner, sprintf('أُلغي تحويل المالك **%s** %s بمبلغ **%s** — %s',
                $owner->name, $entry->reference_no ?: '#'.$entry->id, RealEstateMoney::format($entry->amount, $entry->currency), $reason));

            return $mirror;
        });
    }

    /* ─────────────────────────── الصيانة ─────────────────────────── */

    public function ownerOfRequest(MaintenanceRequest $request): ?Owner
    {
        $request->loadMissing('unit.building.owner', 'building.owner', 'contract.owner');

        return $request->contract?->owner
            ?? $request->unit?->building?->owner
            ?? $request->building?->owner;
    }

    /* ─────────────────────────── مساعدات ─────────────────────────── */

    /** حركةٌ في دفتر المالك، مختومةٌ بعملتها ومنشئها. */
    public function addEntry(Owner $owner, array $attributes): OwnerEntry
    {
        $attributes['currency'] = ($attributes['currency'] ?? null) ?: RealEstateMoney::code();

        return OwnerEntry::create($attributes + ['owner_id' => $owner->id, 'created_by' => auth()->id()]);
    }

    /**
     * حركةٌ مقابلة تُلغي أثر حركةٍ سابقة وتشير إليها. `reversalJournalId`:
     * قيدها العكسي إن كان للحركة قيدٌ خاص بها (التحويل، الصيانة)؛ وحركات
     * الدفعة يعكسها قيد إلغاء الدفعة نفسه.
     */
    public function mirror(OwnerEntry $entry, string $why, string $date, ?int $reversalJournalId = null): OwnerEntry
    {
        return OwnerEntry::create([
            'owner_id' => $entry->owner_id, 'entry_date' => $date, 'type' => 'reversal',
            'direction' => $entry->direction === 'credit' ? 'debit' : 'credit', 'amount' => $entry->amount,
            'building_id' => $entry->building_id, 'unit_id' => $entry->unit_id, 'contract_id' => $entry->contract_id,
            'payment_id' => $entry->payment_id, 'maintenance_request_id' => $entry->maintenance_request_id,
            'maintenance_cost_id' => $entry->maintenance_cost_id, 'journal_entry_id' => $reversalJournalId,
            'reverses_entry_id' => $entry->id, 'reference_no' => $entry->reference_no, 'currency' => $entry->currency,
            'description' => mb_substr('عكس: '.$entry->description.' ('.$why.')', 0, 255), 'created_by' => auth()->id(),
        ]);
    }

    /**
     * قيد يومية معتمد من عدّة سطور، يُرفض إن لم يتوازن.
     *
     * @param  array{reference_number?:?string, date:mixed, description:string, reference_type:ReferenceType, reference_id:?int, currency?:?string, client_id?:?int}  $header
     * @param  array<int, array{0:int, 1:float, 2:float, 3:string}>  $lines  [account_id, debit, credit, description]
     */
    public function postJournal(array $header, array $lines): JournalEntry
    {
        $lines = array_values(array_filter($lines, fn ($l) => round((float) $l[1], 2) > 0 || round((float) $l[2], 2) > 0));
        $debit = round(array_sum(array_column($lines, 1)), 2);
        $credit = round(array_sum(array_column($lines, 2)), 2);

        if ($lines === [] || abs($debit - $credit) > 0.004) {
            throw ValidationException::withMessages([
                'accounting' => sprintf('تعذّر الترحيل: القيد غير متوازن (مدين %s، دائن %s).', number_format($debit, 2), number_format($credit, 2)),
            ]);
        }

        $entry = JournalEntry::create([
            'status' => 1, 'linking_type' => LinkingType::SINGLE,
            'created_by_employee' => auth()->id(), 'approved_by_employee' => auth()->id(),
        ] + $header);

        foreach ($lines as [$accountId, $lineDebit, $lineCredit, $description]) {
            JournalEntryDetail::create([
                'journal_entry_id' => $entry->id, 'account_id' => $accountId, 'description' => $description,
                'debit' => round((float) $lineDebit, 2), 'credit' => round((float) $lineCredit, 2), 'is_debit' => (float) $lineDebit > 0,
            ]);
        }

        RealEstateAccounts::refreshBalances(array_column($lines, 0));

        return $entry;
    }

    /** قيدٌ عكسي يقلب كل سطور الأصل ويعلّمه معكوساً. */
    public function reverseJournal(JournalEntry $original, string $date, ReferenceType $type, ?int $referenceId, ?string $description = null): JournalEntry
    {
        $original->loadMissing('details');

        $reversal = JournalEntry::create([
            'reference_number' => $original->reference_number.'-REV', 'date' => $date,
            'description' => $description ?: 'عكس: '.$original->description,
            'status' => 1, 'currency' => $original->currency, 'client_id' => $original->client_id, 'reference_type' => $type,
            'linking_type' => LinkingType::SINGLE, 'reference_id' => $referenceId,
            'created_by_employee' => auth()->id(), 'approved_by_employee' => auth()->id(),
        ]);

        foreach ($original->details as $detail) {
            JournalEntryDetail::create([
                'journal_entry_id' => $reversal->id, 'account_id' => $detail->account_id, 'description' => 'عكس: '.$detail->description,
                'debit' => $detail->credit, 'credit' => $detail->debit, 'is_debit' => (float) $detail->credit > 0,
            ]);
        }

        $original->update(['is_reversed' => 1]);
        RealEstateAccounts::refreshBalances($original->details->pluck('account_id')->all());

        return $reversal;
    }
}
