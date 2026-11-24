<?php

namespace Modules\RentalManagement\Services;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\MaintenanceCost;
use Modules\RentalManagement\Models\MaintenanceRequest;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\OwnerEntry;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateMoney;

/**
 * تكاليف الصيانة وقيودها.
 *
 * كل تكلفةٍ لها طرفٌ مقابل إلزامي — خزينة/بنك دُفعت منه، أو ذمة المقاول
 * المورّد إن كانت آجلة — فلا حركة في دفتر المالك بلا قيد:
 *
 *   على المالك (مبنى مُدار) : مدين جاري المالك        / دائن الخزينة أو المورّد
 *   على المنشأة             : مدين مصروفات الصيانة    / دائن الخزينة أو المورّد
 *   على المستأجر            : القيد نفسه بحسب ملكية المبنى (من دفع عنه)،
 *                             ويُنشأ عليه قسط «صيانة» في جدول عقده يُحصَّل
 *                             كأيّ قسط؛ وعند تحصيله يُردّ لمن تحمّل التكلفة.
 *
 * و«على المالك» في مبنى مملوك للمنشأة أو بلا مالك = على المنشأة.
 */
class MaintenanceCostService
{
    /**
     * @param  array{description:string, amount:float|string, cost_date?:?string, paid_from?:?string, treasury_account_id?:int|string|null}  $data
     */
    public function add(MaintenanceRequest $request, array $data): MaintenanceCost
    {
        if (in_array($request->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'الطلب مغلق — لا تُسجَّل عليه تكاليف.']);
        }

        $amount = round((float) $data['amount'], 2);
        $date = Carbon::parse($data['cost_date'] ?? now())->startOfDay();

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'أدخل مبلغاً صحيحاً.']);
        }

        if ($date->gt(now()->endOfDay())) {
            throw ValidationException::withMessages(['cost_date' => 'تاريخ التكلفة لا يكون في المستقبل.']);
        }

        RealEstateAccounts::assertPeriodOpen($date, 'cost_date');

        $chargedTo = $request->charge_to;
        $contract = $chargedTo === 'tenant' ? $this->tenantContract($request) : null;
        $owner = $this->managedOwner($request);
        [$counterAccount, $treasuryId, $supplierId] = $this->counterparty($request, $data);

        return DB::transaction(function () use ($request, $data, $amount, $date, $chargedTo, $contract, $owner, $counterAccount, $treasuryId, $supplierId) {
            $ledger = app(OwnerLedgerService::class);
            $currency = RealEstateMoney::code();
            $day = $date->toDateString();

            $cost = $request->costs()->create([
                'description' => $data['description'], 'amount' => $amount, 'cost_date' => $day,
                'currency' => $currency, 'charged_to' => $chargedTo,
                'treasury_account_id' => $treasuryId, 'supplier_id' => $supplierId, 'created_by' => auth()->id(),
            ]);

            $this->book($request, $cost, $contract, $owner, $counterAccount, $supplierId);
            $request->update(['actual_cost' => $request->costs()->sum('amount')]);

            return $cost;
        });
    }

    /**
     * تقييد تكلفةٍ قديمة سُجّلت قبل إلزام القيد (بلا قيد يومية): يُختار لها
     * الطرف المقابل الآن فتُقيَّد بتاريخها، وتُربط حركة المالك الموجودة
     * بالقيد بدل تكرارها.
     */
    public function post(MaintenanceCost $cost, array $data): MaintenanceCost
    {
        if ($cost->journal_entry_id) {
            throw ValidationException::withMessages(['cost' => 'هذه التكلفة مقيَّدة بالفعل.']);
        }

        $request = $cost->request()->firstOrFail();
        $date = Carbon::parse($cost->cost_date ?? now())->startOfDay();

        if ($date->gt(now()->endOfDay())) {
            $date = now()->startOfDay();
        }

        RealEstateAccounts::assertPeriodOpen($date, 'cost');

        $chargedTo = $cost->charged_to ?: $request->charge_to;
        $contract = $chargedTo === 'tenant' && ! $cost->schedule_id ? $this->tenantContract($request) : null;
        $owner = $this->managedOwner($request);
        [$counterAccount, $treasuryId, $supplierId] = $this->counterparty($request, $data);

        return DB::transaction(function () use ($request, $cost, $date, $chargedTo, $contract, $owner, $counterAccount, $treasuryId, $supplierId) {
            $cost->update([
                'cost_date' => $date->toDateString(), 'charged_to' => $chargedTo,
                'currency' => $cost->currency ?: RealEstateMoney::code(),
                'treasury_account_id' => $treasuryId, 'supplier_id' => $supplierId,
            ]);

            $this->book($request, $cost, $contract, $owner, $counterAccount, $supplierId);

            return $cost;
        });
    }

    /** القيد وحركة دفتر المالك وقسط المستأجر لتكلفةٍ محفوظة — داخل معاملة المستدعي. */
    protected function book(MaintenanceRequest $request, MaintenanceCost $cost, ?Contract $contract, ?Owner $owner, int $counterAccount, ?int $supplierId): void
    {
        $ledger = app(OwnerLedgerService::class);
        $amount = round((float) $cost->amount, 2);
        $currency = $cost->currency ?: RealEstateMoney::code();
        $day = Carbon::parse($cost->cost_date)->toDateString();
        $chargedTo = $cost->charged_to;

        // من يتحمّل التكلفة الآن: المالك في المبنى المُدار (إلا إن كانت «على المنشأة»)، وإلا المنشأة
        $bearer = $owner && $chargedTo !== 'company' ? $owner : null;
        $debitAccount = $bearer ? $ledger->ensureAccount($bearer)->id : RealEstateAccounts::id('maintenance_expense_account_id');
        $who = match (true) {
            $chargedTo === 'tenant' => 'صيانة على المستأجر',
            (bool) $bearer => 'صيانة على المالك '.$bearer->name,
            default => 'صيانة على المنشأة',
        };

        $journal = $ledger->postJournal([
            'date' => $day, 'description' => $who.' — '.$request->code.' — '.$cost->description,
            'reference_number' => $request->code.'-'.$cost->id,
            'reference_type' => ReferenceType::REAL_ESTATE_OWNER_CHARGE, 'reference_id' => $cost->id, 'currency' => $currency,
        ], [
            [$debitAccount, $amount, 0, $who.' — '.$cost->description],
            [$counterAccount, 0, $amount, ($supplierId ? 'مستحق للمقاول عن صيانة ' : 'دفع تكلفة صيانة ').$request->code],
        ]);

        $update = ['journal_entry_id' => $journal->id];

        if ($bearer) {
            // حركةٌ قديمة للتكلفة نفسها بلا قيد تُربط بالقيد ولا تُكرَّر
            $existing = OwnerEntry::withoutGlobalScopes()->where('owner_id', $bearer->id)->where('maintenance_cost_id', $cost->id)
                ->where('type', 'maintenance')->where('direction', 'debit')->whereNull('journal_entry_id')->whereDoesntHave('reversal')->first();

            if ($existing) {
                $existing->update(['journal_entry_id' => $journal->id]);
            } else {
                $ledger->addEntry($bearer, [
                    'entry_date' => $day, 'type' => 'maintenance', 'direction' => 'debit', 'amount' => $amount,
                    'building_id' => $request->building_id ?: $request->unit?->building_id, 'unit_id' => $request->unit_id,
                    'contract_id' => $request->contract_id, 'maintenance_request_id' => $request->id, 'maintenance_cost_id' => $cost->id,
                    'journal_entry_id' => $journal->id, 'currency' => $currency,
                    'description' => mb_substr($who.' — '.$request->code.' — '.$cost->description, 0, 255),
                ]);
            }
        }

        if ($contract) {
            $schedule = PaymentSchedule::create([
                'contract_id' => $contract->id, 'installment_no' => 0, 'kind' => 'maintenance', 'due_date' => $day,
                'amount_due' => $amount, 'amount_paid' => 0, 'status' => 'due',
                'description' => mb_substr('صيانة '.$request->code.' — '.$cost->description, 0, 255), 'maintenance_cost_id' => $cost->id,
            ]);
            $schedule->refreshStatus();
            $update['schedule_id'] = $schedule->id;
        }

        $cost->update($update);
    }

    /** حذف تكلفة: قيدٌ عكسي وحركةٌ مقابلة في دفتر المالك، ويُسحب قسط المستأجر إن لم يُحصَّل. */
    public function remove(MaintenanceCost $cost): void
    {
        $request = $cost->request()->first();
        $schedule = $cost->schedule_id ? PaymentSchedule::find($cost->schedule_id) : null;

        // توزيعات الدفعات الملغاة تبقى سجلاً ولا تمنع الحذف: المعتبر ما هو محصَّل فعلاً
        if ($schedule && (float) $schedule->amount_paid > 0) {
            throw ValidationException::withMessages(['cost' => 'حُصِّل من المستأجر قسط هذه الصيانة — ألغِ دفعته أولاً ثم احذف التكلفة.']);
        }

        $original = $cost->journal_entry_id ? JournalEntry::with('details')->find($cost->journal_entry_id) : null;

        if ($original) {
            RealEstateAccounts::assertPeriodOpen($original->date, 'cost');
            RealEstateAccounts::assertPeriodOpen(now(), 'cost');
        }

        DB::transaction(function () use ($cost, $request, $schedule, $original) {
            $ledger = app(OwnerLedgerService::class);
            $date = now()->toDateString();
            $reversalId = $original && ! $original->is_reversed
                ? $ledger->reverseJournal($original, $date, ReferenceType::REAL_ESTATE_OWNER_CHARGE, $cost->id)->id
                : null;

            OwnerEntry::withoutGlobalScopes()->where('maintenance_cost_id', $cost->id)->where('type', 'maintenance')
                ->whereDoesntHave('reversal')->get()
                ->each(fn (OwnerEntry $entry) => $ledger->mirror($entry, 'حذف تكلفة صيانة', $date, $entry->journal_entry_id ? $reversalId : null));

            if ($schedule) {
                // قسطٌ عليه توزيعات دفعةٍ ملغاة يُلغى ولا يُحذف — يبقى أثر الدفعة
                $schedule->allocations()->exists() ? $schedule->update(['status' => 'cancelled']) : $schedule->delete();
            }

            $cost->delete();
            $request?->update(['actual_cost' => $request->costs()->sum('amount')]);
        });
    }

    /** المالك الذي تُحمَّل عليه الصيانة — فقط إن كان مبناه مُداراً لحسابه. */
    public function managedOwner(MaintenanceRequest $request): ?Owner
    {
        $owner = app(OwnerLedgerService::class)->ownerOfRequest($request);
        $building = $request->unit?->building ?? $request->building;

        return $owner && RealEstateAccounts::isManaged($building) ? $owner : null;
    }

    /** حساب المقاول المورّد في الدليل (ذمته)، أو null إن لم يكن على الطلب مقاولٌ له حساب. */
    public function supplierAccount(MaintenanceRequest $request): ?Account
    {
        return $request->contractor_supplier_id
            ? Account::where('supplier_id', $request->contractor_supplier_id)->orderBy('id')->first()
            : null;
    }

    /**
     * الطرف المقابل: ذمة المورّد أو خزينة/بنك.
     *
     * @return array{0:int, 1:?int, 2:?int} [account_id, treasury_account_id, supplier_id]
     */
    protected function counterparty(MaintenanceRequest $request, array $data): array
    {
        if (($data['paid_from'] ?? null) === 'supplier') {
            $account = $this->supplierAccount($request);

            if (! $account) {
                throw ValidationException::withMessages([
                    'paid_from' => 'لا مقاول على الطلب له حسابٌ في الدليل — اختر المقاول في الطلب أو ادفع من خزينة.',
                ]);
            }

            return [(int) $account->id, null, (int) $request->contractor_supplier_id];
        }

        if (empty($data['treasury_account_id'])) {
            throw ValidationException::withMessages([
                'treasury_account_id' => 'حدّد من أين دُفعت التكلفة: خزينة/بنك، أو ذمة المقاول — لا تُسجَّل تكلفةٌ بلا قيد.',
            ]);
        }

        $treasuryId = RealEstateAccounts::assertTreasury($data['treasury_account_id'], 'withdraw');

        return [$treasuryId, $treasuryId, null];
    }

    protected function tenantContract(MaintenanceRequest $request): Contract
    {
        $contract = $request->contract_id ? Contract::find($request->contract_id) : null;

        if (! $contract || in_array($contract->status, ['draft', 'pending_approval', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'charge_to' => 'الصيانة على المستأجر تحتاج عقداً معتمداً مرتبطاً بالطلب ليُضاف القسط إلى جدوله.',
            ]);
        }

        return $contract;
    }
}
