<?php

namespace Modules\RentalManagement\Services;

use App\Enums\ReferenceType;
use App\Support\OperationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\DepositSettlement;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateMoney;

/**
 * تسوية تأمين المستأجر عند إنهاء العقد.
 *
 * التأمين أمانةٌ في «تأمينات المستأجرين». كان الإنهاء يحسب المخصوم
 * والمردود رقماً فقط، فيبقى الالتزام قائماً في الدفاتر بلا نهاية. التسوية
 * تُقفله بقيد:
 *
 *     مدين  تأمينات المستأجرين            (المحتجَز)
 *     دائن  الخزينة / البنك               (المردود للمستأجر)
 *     دائن  جاري المالك أو إيرادات خصم التأمينات   (المخصوم)
 */
class DepositService
{
    public const LOG = PaymentService::LOG;

    /**
     * التأمين المحتجَز فعلاً في الدفاتر عن العقد: ما قُبض منه بدفعاتٍ مرحّلة
     * ناقصاً ما سُوّي سابقاً.
     */
    public function held(Contract $contract): float
    {
        $collected = (float) DB::table('re_payment_allocations as a')
            ->join('re_payments as p', 'p.id', '=', 'a.payment_id')
            ->join('re_payment_schedules as s', 's.id', '=', 'a.schedule_id')
            ->where('s.contract_id', $contract->id)->where('s.kind', 'deposit')
            ->where('p.posting_status', 'posted')->sum('a.amount');

        $settled = (float) DepositSettlement::withoutGlobalScopes()->where('contract_id', $contract->id)->sum('held_amount');

        return round(max(0, $collected - $settled), 2);
    }

    /**
     * يسوّي التأمين المحتجَز كله: يخصم `$deducted` ويردّ الباقي من الخزينة.
     * يعيد null إن لم يكن على العقد تأمينٌ محتجَز (فلا قيد ولا خطأ).
     *
     * @param  string  $deductTo  `owner` (جاري المالك — للمبنى المُدار) أو `office` (إيراد للمنشأة).
     *                            المبنى المملوك للمنشأة يُقيَّد خصمُه إيراداً دائماً.
     */
    public function settle(Contract $contract, float $deducted, ?int $treasuryAccountId, ?string $reason, string $deductTo = 'owner', ?string $date = null): ?DepositSettlement
    {
        $held = $this->held($contract);

        if ($held <= 0) {
            return null;
        }

        $deducted = round(max(0, $deducted), 2);

        if ($deducted > $held) {
            throw ValidationException::withMessages([
                'deducted_amount' => 'المخصوم ('.number_format($deducted, 2).') يتجاوز التأمين المحتجَز ('.number_format($held, 2).').',
            ]);
        }

        if ($deducted > 0 && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'اكتب سبب الخصم من التأمين.']);
        }

        $refunded = round($held - $deducted, 2);
        $date = $date ?: now()->toDateString();

        if ($refunded > 0) {
            // ما يُردّ للمستأجر يخرج من خزينةٍ أو بنك مسموحٍ لي بالسحب منه
            RealEstateAccounts::assertTreasury($treasuryAccountId, 'withdraw');
        } else {
            $treasuryAccountId = null;
        }

        RealEstateAccounts::assertPeriodOpen($date, 'settled_at');

        $owner = $deductTo === 'owner' ? RealEstateAccounts::managedOwner($contract) : null;
        $deductTo = $owner ? 'owner' : 'office';

        return DB::transaction(function () use ($contract, $held, $deducted, $refunded, $treasuryAccountId, $reason, $deductTo, $owner, $date) {
            $ledger = app(OwnerLedgerService::class);
            $code = RealEstateAccounts::nextNumber('re-deposit-settlement', 'DPS');
            $currency = $contract->currency ?: RealEstateMoney::code();

            $settlement = DepositSettlement::create([
                'code' => $code, 'contract_id' => $contract->id, 'settled_at' => $date,
                'held_amount' => $held, 'deducted_amount' => $deducted, 'refunded_amount' => $refunded,
                'deduct_to' => $deductTo, 'treasury_account_id' => $treasuryAccountId,
                'reason' => $reason, 'currency' => $currency, 'created_by' => auth()->id(),
                'branch_id' => $contract->branch_id,
            ]);

            $deductAccount = $deducted > 0
                ? ($owner ? $ledger->ensureAccount($owner)->id : RealEstateAccounts::id('deposit_forfeit_account_id'))
                : null;

            $lines = [[RealEstateAccounts::id('deposit_liability_account_id'), $held, 0, 'تسوية تأمين مستأجر — '.$contract->code]];

            if ($refunded > 0) {
                $lines[] = [$treasuryAccountId, 0, $refunded, 'ردّ تأمين للمستأجر — '.$contract->code];
            }

            if ($deducted > 0) {
                $lines[] = [$deductAccount, 0, $deducted, ($owner ? 'خصم من التأمين لصالح المالك '.$owner->name : 'خصم من التأمين لصالح المنشأة').' — '.$contract->code];
            }

            $journal = $ledger->postJournal([
                'reference_number' => $code, 'date' => $date,
                'description' => 'تسوية تأمين العقد '.$contract->code.' رقم '.$code.(filled($reason) ? ' — '.$reason : ''),
                'reference_type' => ReferenceType::REAL_ESTATE_RENT, 'reference_id' => $settlement->id,
                'currency' => $currency, 'client_id' => $contract->tenant_client_id,
            ], $lines);

            $settlement->update(['journal_entry_id' => $journal->id]);

            if ($owner && $deducted > 0) {
                $ledger->addEntry($owner, [
                    'entry_date' => $date, 'type' => 'deposit', 'direction' => 'credit', 'amount' => $deducted,
                    'building_id' => $contract->building_id ?? $contract->unit?->building_id, 'unit_id' => $contract->unit_id,
                    'contract_id' => $contract->id, 'journal_entry_id' => $journal->id, 'reference_no' => $code,
                    'deposit_settlement_id' => $settlement->id, 'currency' => $currency,
                    'description' => mb_substr('خصم من تأمين المستأجر — عقد '.$contract->code.' — '.$reason, 0, 255),
                ]);
            }

            // أرقام العقد تتبع ما قُيِّد فعلاً
            DB::table('re_contracts')->where('id', $contract->id)->update([
                'deposit_deducted' => DepositSettlement::withoutGlobalScopes()->where('contract_id', $contract->id)->sum('deducted_amount'),
                'deposit_refunded' => DepositSettlement::withoutGlobalScopes()->where('contract_id', $contract->id)->sum('refunded_amount'),
            ]);

            OperationLog::record('created', 're_contract_log', $contract, sprintf(
                'سُوّي تأمين العقد **%s** بالمستند **%s**: محتجَز %s، مخصوم %s، مردود %s',
                $contract->code, $code, RealEstateMoney::format($held, $currency),
                RealEstateMoney::format($deducted, $currency), RealEstateMoney::format($refunded, $currency)));

            return $settlement;
        });
    }
}
