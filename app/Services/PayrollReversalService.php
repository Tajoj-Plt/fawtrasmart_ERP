<?php

namespace App\Services;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Deduction;
use App\Models\DeductionHistory;
use App\Models\Expense;
use App\Models\InstallmentPayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRequest;
use App\Support\AccountingPeriodGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * عكس صرف راتب: قيد عكسي للقيود الأصلية بلا حذفها، وإرجاع التسويات،
 * وإعادة الـ run إلى pending ليُصرف من جديد بعد تصحيح الخطأ.
 */
class PayrollReversalService
{
    public function reverse(PayrollRun $run, string $reason, ?int $reversedBy = null): PayrollRun
    {
        if (! $run->canBeReversed()) {
            throw new \RuntimeException('لا يُعكس إلا راتب مصروف.');
        }

        $block = AccountingPeriodGuard::blockReason(now());
        if ($block) {
            throw new \RuntimeException($block);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('سبب العكس مطلوب.');
        }

        $reversedBy = $reversedBy ?: Auth::id();

        return DB::transaction(function () use ($run, $reason, $reversedBy) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! $run->canBeReversed()) {
                throw new \RuntimeException('لا يُعكس إلا راتب مصروف.');
            }

            // يُقرأ قبل العكس: هل حمل قيدُ استحقاق هذه الصرفة الخصمياتِ بنفسه؟
            $legacyDeductions = $this->accrualCarriedDeductions($run);

            $reversalId = $this->reverseJournals($run, $reason, $reversedBy);
            $this->unsettleItems($run, $reversedBy, $legacyDeductions);
            $this->annotateExpense($run, $reason);
            // مكافآتُ الكتالوج المصروفة في هذه الصرفة تعود معلَّقةً على القسيمة
            app(Rewards\EmployeeRewardFulfillment::class)->unpay($run);

            $run->items()->where('item_type', '!=', PayrollRunItem::TYPE_RETRO)->delete();

            $run->update([
                'status' => PayrollRun::STATUS_PENDING,
                'disbursed_at' => null,
                'disbursed_by' => null,
                'expense_id' => null,
                'journal_entry_id' => null,
                'accrual_journal_entry_id' => null,
                'reversed_at' => now(),
                'reversed_by' => $reversedBy,
                'reversal_reason' => $reason,
                'reversal_journal_entry_id' => $reversalId,
            ]);

            return $run->fresh();
        });
    }

    private function reverseJournals(PayrollRun $run, string $reason, ?int $reversedBy): ?int
    {
        if (! Schema::hasTable('journal_entries') || ! Schema::hasTable('journal_entry_details')) {
            return null;
        }

        $ids = array_values(array_filter([
            $run->journal_entry_id ? (int) $run->journal_entry_id : null,
            $run->accrual_journal_entry_id ? (int) $run->accrual_journal_entry_id : null,
        ]));

        $entries = JournalEntry::withoutGlobalScopes()
            ->where(function ($q) use ($ids, $run) {
                if ($ids !== []) {
                    $q->whereIn('id', $ids);
                }
                $q->orWhereIn('reference_number', [
                    'SAL-PAY-'.$run->id,
                    'SAL-ACC-'.$run->id,
                    'SAL-GOSI-'.$run->id,
                ]);
            })
            ->get()
            ->unique('id');

        /*
            `reversal_journal_entry_id` كان يحمل **آخر** ما كُتب من قيودٍ
            عكسية — وهو قيدُ التأمينات (`SAL-GOSI`) إن وُجد، لأنه آخرُ
            الثلاثة ترتيباً. فمن فتح الصرفةَ ليرى «قيد العكس» يُقاد إلى
            عكسِ التأمينات لا إلى عكسِ الراتب.

            فالمحفوظُ الآن عكسُ **قيد الصرف** (`SAL-PAY`) — نظيرُ
            `journal_entry_id` الذي كانت تحمله الصرفةُ قبل العكس — وما
            سواه يبقى موجوداً بمرجعه `REVERSE_SAL-*-{id}`.
        */
        $lastId = null;
        $paymentReversalId = null;

        foreach ($entries as $entry) {
            $ref = (string) $entry->reference_number;
            if (str_starts_with($ref, 'REVERSE_') || $entry->is_reversed) {
                continue;
            }
            $reverse = JournalEntry::create([
                'reference_number' => 'REVERSE_'.$entry->reference_number,
                'reference_id' => $run->id,
                'date' => now()->toDateString(),
                'description' => 'قيد عكسي — '.$reason.' — '.$entry->description,
                'status' => 1,
                'currency' => $entry->currency ?: null,
                'branch_id' => $entry->branch_id ?: null,
                'employee_id' => $entry->employee_id ?: $run->employee_id,
                'salary_id' => $entry->salary_id,
                'created_by_employee' => $reversedBy,
                'reference_type' => $entry->reference_type ?: ReferenceType::PAYROLL,
                'linking_type' => LinkingType::SINGLE,
            ]);

            foreach (JournalEntryDetail::query()->where('journal_entry_id', $entry->id)->get() as $detail) {
                $debit = (float) $detail->credit;
                $credit = (float) $detail->debit;
                JournalEntryDetail::create([
                    'journal_entry_id' => $reverse->id,
                    'account_id' => $detail->account_id,
                    'description' => 'عكس - '.$detail->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'is_debit' => $debit > 0.009,
                ]);
            }

            $entry->is_reversed = true;
            $entry->save();
            $lastId = $reverse->id;

            if ($ref === 'SAL-PAY-'.$run->id) {
                $paymentReversalId = $reverse->id;
            }
        }

        return $paymentReversalId ?: $lastId;
    }

    /**
     * هل قيّد قيدُ استحقاق هذه الصرفة الخصمياتِ دائنةً على حسابٍ غير ذمّة الموظف؟
     *
     * هكذا كانت الصرفاتُ قبل قيد الخصمية عند اعتمادها. فعكسُ صرفةٍ كهذه يعكس
     * معه تغطيةَ خصمياتها المحاسبية، فيُنقَص `posted_amount` لتُقيَّد من جديد
     * عند الصرف التالي. أمّا الصرفةُ الحديثة فقيدُ الخصمية مستقلٌّ عنها
     * (`DED-{id}`) ويبقى قائماً — والخصميةُ ما زالت مستحقّة.
     */
    private function accrualCarriedDeductions(PayrollRun $run): bool
    {
        if (! $run->accrual_journal_entry_id || ! Schema::hasColumn('deductions', 'posted_amount')) {
            return false;
        }

        $payableId = (int) (optional($run->employee)->salary_payable_account_id ?? 0);

        /*
            القيدُ الحديث يُقيّد الذمّةَ دائنةً **بالإجمالي**. والقديمُ بنوعَيه
            أقلُّ منه بمقدار الخصميات: إمّا أنّها ذهبت دائنةً إلى
            `payroll_deductions` (منذ 2026-09-22)، وإمّا أنّ المصروفَ كلَّه قُيِّد
            صافياً (قبل ذلك). فالمعيارُ الواحدُ للنوعين: دائنُ الذمّة دون الإجمالي.
        */
        $payableCredit = (float) JournalEntryDetail::query()
            ->where('journal_entry_id', $run->accrual_journal_entry_id)
            ->where('account_id', $payableId)
            ->sum('credit');

        $gross = (float) $run->basic_amount + (float) $run->total_additions;

        return $payableCredit + 0.01 < $gross;
    }

    private function unsettleItems(PayrollRun $run, ?int $userId, bool $legacyDeductions = false): void
    {
        if (! Schema::hasTable('payroll_run_items')) {
            return;
        }

        foreach ($run->items as $item) {
            if ($item->item_type === PayrollRunItem::TYPE_DEDUCTION
                && $item->source_table === 'deductions'
                && $item->source_id) {
                $this->unsettleDeduction((int) $item->source_id, (float) $item->amount, $run, $userId, $legacyDeductions);
            }

            if ($item->item_type === PayrollRunItem::TYPE_ADVANCE && $item->source_id) {
                $this->unsettleAdvance($item);
            }
        }
    }

    private function unsettleDeduction(int $id, float $amount, PayrollRun $run, ?int $userId, bool $legacy = false): void
    {
        if (! Schema::hasTable('deductions')) {
            return;
        }

        $deduction = Deduction::query()->find($id);
        if (! $deduction) {
            return;
        }

        $newSettled = max(0, (float) $deduction->settled_amount - $amount);
        $status = $newSettled < 0.01
            ? Deduction::STATUS_APPROVED
            : Deduction::STATUS_PARTIALLY_SETTLED;

        $attributes = [
            'settled_amount' => $newSettled,
            'status' => $status,
            'settled_at' => $newSettled < 0.01 ? null : $deduction->settled_at,
        ];

        if ($legacy) {
            $attributes['posted_amount'] = max(0, round((float) $deduction->posted_amount - $amount, 2));
        }

        // بلا أحداث: عودةُ الحالة إلى «معتمدة» هنا ليست اعتماداً جديداً يُقيَّد
        $deduction->forceFill($attributes)->saveQuietly();

        if (Schema::hasTable('deduction_histories')) {
            DeductionHistory::create([
                'deduction_id' => $deduction->id,
                'action' => DeductionHistory::ACTION_UPDATED,
                'user_id' => $userId,
                'description' => 'عكس تسوية من صرف راتب #'.$run->id,
                'amount' => $amount,
            ]);
        }
    }

    private function unsettleAdvance(PayrollRunItem $item): void
    {
        $amount = (float) $item->amount;
        if ($item->source_table === 'installment_payments' && Schema::hasTable('installment_payments')) {
            $inst = InstallmentPayment::query()->find($item->source_id);
            if ($inst && $inst->status === 'paid') {
                $inst->update([
                    'status' => 'unpaid',
                    'payment_date' => null,
                ]);
                if (Schema::hasTable('salary_advances') && $inst->salary_advance_id) {
                    $parent = SalaryAdvance::query()->find($inst->salary_advance_id);
                    if ($parent) {
                        $paid = max(0, (int) $parent->paid_installments - 1);
                        $parent->update(['paid_installments' => $paid]);
                    }
                }
            }

            return;
        }

        if ($item->source_table === 'salary_advance_requests' && Schema::hasTable('salary_advance_requests')) {
            $req = SalaryAdvanceRequest::query()->find($item->source_id);
            if (! $req) {
                return;
            }
            $newSettled = max(0, (float) ($req->settled_amount ?? 0) - $amount);
            $req->update([
                'settled_amount' => $newSettled,
                'is_settled' => $newSettled >= (float) $req->amount - 0.001,
                'settled_date' => $newSettled < 0.01 ? null : $req->settled_date,
            ]);
        }
    }

    /**
     * وسمُ سند الصرف بأنّه معكوس — لا حذفُه.
     *
     * المستندُ يبقى: الدفترُ صحَّ بالقيد العكسي، والسندُ أثرٌ لواقعةٍ حدثت.
     * لكنّ الوسمَ كان نصّاً في الوصف وحده (`[معكوس]`)، ولا تقرأ التقاريرُ
     * وصفاً. فكان المعكوسُ يُحصى مصروفاً في
     * `GeneralAccountsController::expensesByCategory` وفي بطاقات شاشة
     * المصروفات — والعكسُ ثمّ إعادةُ الصرف يُخلِّف صفَّين لراتبٍ واحد.
     *
     * فصار الوسمُ عموداً (`reversed_at`) يستثنيه `scopeNotReversed()`،
     * ويبقى النصُّ في الوصف لمن يقرأ القائمة.
     */
    private function annotateExpense(PayrollRun $run, string $reason): void
    {
        if (! $run->expense_id || ! Schema::hasTable('expenses')) {
            return;
        }

        $expense = Expense::query()->find($run->expense_id);
        if (! $expense) {
            return;
        }

        $attributes = [];

        $prefix = '[معكوس] ';
        if (! str_starts_with((string) $expense->description, $prefix)) {
            $attributes['description'] = $prefix.$expense->description;
        }

        if (Schema::hasColumn('expenses', 'reversed_at') && $expense->reversed_at === null) {
            $attributes['reversed_at'] = now();
            $attributes['reversal_reason'] = mb_substr($reason, 0, 500);
        }

        if ($attributes !== []) {
            $expense->update($attributes);
        }
    }
}
