<?php

namespace Modules\HR\Support;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Employee;
use App\Models\InstallmentPayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRequest;
use App\Models\TreasuryEmployee;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\TreasuryAccessGuard;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\PostsHrJournalEntries;

/**
 * الأثرُ النقديّ للسلفة — الصرفُ والتحصيلُ والإلغاء — في موضعٍ واحد.
 *
 * ══════════════ ما كان ══════════════
 *
 *   • الاعتمادُ يصرف المالَ في الخطوة نفسها، ويقرأ كفايةَ الخزينة من العمود
 *     المخزَّن `accounts.balance` بلا قفل — فصرفتان متزامنتان تمرّان معاً
 *     وتنتهي الخزينةُ سالبة. والإصدارُ المباشر من الإدارة يفحص الرصيدَ ثمّ
 *     يعود من داخل معاملةٍ مفتوحة بلا `rollBack`.
 *   • «التسوية» تُغلق السلفةَ العاديّة بمبلغها كلِّه ولو اقتُطع جزءٌ منها
 *     من الراتب — فتُقيَّد على الموظف دائنيّةٌ أكبر من دَينه.
 *   • ولا إلغاءَ أصلاً: السلفةُ القديمة تُحذف بزرٍّ وقيدُ صرفها باقٍ في
 *     الدفتر، والموظفُ يُلغي طلبَه فيُكتب «مرفوضاً».
 *
 * ══════════════ ما صار ══════════════
 *
 *   **الصرف**  ← `disburse()`: مدين حساب سلف الموظف / دائن الخزينة، بعد
 *               `TreasuryBalanceGuard` (شرطُ السحب + الرصيدُ من الدفتر تحت
 *               قفل) وقبل إنشاء القيد. المرجع `ADV-REQ-{id}` — وهو ما تشترطه
 *               خطّةُ الرواتب لتقتطع السلفة، فالمعتمدةُ غيرُ المصروفة لا تُقتطع.
 *   **التحصيل** ← `repayCash()`: مدين الخزينة / دائن حساب السلف، بالمتبقّي
 *               فعلاً لا بأصل المبلغ. التسويةُ تُدخل السلفةَ في دورة السداد
 *               (قيد السداد ← مسدَّدة) ولا تُلغيها.
 *   **الإلغاء** ← `cancel()`: عمليةٌ مستقلّة. قبل الصرف تُغلق بلا قيد؛ بعده
 *               بقيدٍ عكسيّ إلى الخزينة نفسِها؛ وتُمنع متى بدأ السداد (نقداً
 *               أو قسطاً أو إدراجاً في مسيرِ رواتب قائم) — تلك تُكمَل تسويتُها.
 *
 * كلُّ عمليةٍ تُستدعى داخل معاملةٍ يفتحها المتحكّم، وتقفل صفَّ السلفة أوّلاً
 * فلا يصرفها طلبان متزامنان مرّتين.
 */
class SalaryAdvanceService
{
    use PostsHrJournalEntries;

    /* ══════════════════ الخزائن ══════════════════ */

    /**
     * الخزائن الصالحة للعملية — حساباتُ النقدية والبنوك من «إعدادات الحسابات
     * المحاسبية»، مصفّاةً بشرط السحب أو الإيداع المضبوط على كلّ خزينة.
     *
     * الصرفُ من `TreasuryBalanceGuard::withdrawableAccounts()`؛ والتحصيلُ
     * (إيداع) من حسابات النقدية والبنوك مصفّاةً بشرط الإيداع.
     *
     * @param  string  $action  `withdraw` للصرف، `deposit` للتحصيل
     * @return Collection<int, Account>
     */
    public function treasuryOptions(string $action = 'withdraw'): Collection
    {
        // قائمةُ الصرف من الحارس المشترك نفسِه الذي يفحص الحفظ — قائمةٌ واحدة لكلّ مخرجٍ نقديّ
        if ($action === 'withdraw') {
            return TreasuryBalanceGuard::withdrawableAccounts();
        }

        $parents = array_values(array_filter([
            AccountingSettings::id('cash_treasuries_parent'),
            AccountingSettings::id('bank_accounts_parent'),
        ]));

        if ($parents === []) {
            return collect();
        }

        return Account::whereIn('parent_id', $parents)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->filter(fn (Account $a) => TreasuryAccessGuard::blockReason($a->id, $action) === null)
            ->values();
    }

    /** خزينةُ الموظف المربوطة به، وإلّا «الخزينة الرئيسية» من الإعدادات. */
    public function defaultTreasuryId(?int $employeeId): ?int
    {
        if ($employeeId) {
            $linked = TreasuryEmployee::where('employee_id', $employeeId)->value('treasury_id');
            if ($linked && Account::whereKey($linked)->exists()) {
                return (int) $linked;
            }
        }

        return AccountingSettings::id('main_treasury');
    }

    /* ══════════════════ النظام الحديث: salary_advance_requests ══════════════════ */

    private function lockRequest(SalaryAdvanceRequest $advance): SalaryAdvanceRequest
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('عمليات السلفة تُستدعى داخل معاملة.');
        }

        return SalaryAdvanceRequest::with('employee.advanceAccount')->lockForUpdate()->findOrFail($advance->id);
    }

    private function employeeName(?Employee $employee): string
    {
        return $employee ? trim(($employee->first_name ?? '').' '.($employee->middle_name ?? '')) : '';
    }

    /**
     * صرفُ سلفةٍ معتمدة من الخزينة.
     *
     *     مدين  حساب سلف الموظف
     *         دائن  الخزينة
     *
     * @throws \RuntimeException إن لم تكن معتمدةً غيرَ مصروفة، أو لا حسابَ سلف،
     *                           أو لا يُسمح بالسحب، أو لا يكفي الرصيد
     */
    public function disburse(SalaryAdvanceRequest $advance, int $treasuryAccountId, $date = null, string $referencePrefix = 'ADV-REQ-'): JournalEntry
    {
        $advance = $this->lockRequest($advance);

        if ($advance->status !== 'approved') {
            throw new \RuntimeException('لا تُصرف إلّا سلفةٌ معتمدة.');
        }
        if ($advance->disbursed_at) {
            throw new \RuntimeException('هذه السلفة صُرفت من قبل — لا تُصرف مرّتين.');
        }
        if (! $advance->employee) {
            throw new \RuntimeException('سجل الموظف غير موجود لهذه السلفة.');
        }

        $advanceAccount = $advance->employee->getAdvanceAccountOrFail();
        $amount = round((float) $advance->amount, 2);

        // شرطُ السحب ثمّ كفايةُ الرصيد تحت قفل — قبل أيِّ قيد
        $treasury = TreasuryBalanceGuard::assertWithdrawable($treasuryAccountId, $amount, 'صرف السلفة #'.$advance->id);

        $name = $this->employeeName($advance->employee);
        $description = "صرف سلفة للموظف {$name} - رقم {$advance->id}";
        if ($advance->isScheduled() && $advance->installments_count) {
            $description .= ' (مجدولة على '.$advance->installments_count.' قسط - قسط '.CurrencySettings::format($advance->installment_amount).')';
        }

        $journal = $this->createHrJournalEntry([
            'reference_number' => $referencePrefix.$advance->id,
            'reference_id' => $advance->id,
            'date' => $date ?: now(),
            'description' => $description,
            'status' => 1,
            'employee_id' => $advance->employee_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::EXPENSES,
            'linking_type' => LinkingType::SINGLE,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $advanceAccount->id,
            'description' => "صرف سلفة - {$advanceAccount->name}",
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $treasury->id,
            'description' => "صرف سلفة من {$treasury->name} للموظف {$name}",
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        $advance->update([
            'treasury_id' => $treasury->id,
            'disbursed_at' => now(),
            'disbursed_by' => Auth::id(),
            'disbursement_journal_id' => $journal->id,
        ]);

        $this->log($advance->id, 'تم صرف السلفة بمبلغ '.CurrencySettings::format($amount).' من '.$treasury->name.' (قيد #'.$journal->id.')');
        $this->notifyEmployee($advance, 'تم صرف السلفة', 'تم صرف سلفتك رقم #'.$advance->id.' بمبلغ '.CurrencySettings::format($amount).' — وتدخل الآن دورة السداد.', 'advance_approved');

        return $journal;
    }

    /**
     * ما يستحقّ في تحصيلٍ نقديّ: للمجدولة أقساطُ ما بين أوّل قسطٍ غير مسدَّد
     * و`$month`، وللعاديّة المتبقّي كلُّه أو ما يُحدَّد منه.
     *
     * @return array{amount: float, installments: int, full: bool}
     */
    public function cashRepaymentPlan(SalaryAdvanceRequest $advance, ?string $month = null, ?float $amount = null): array
    {
        $remaining = $advance->remainingAmount();

        if ($advance->isScheduled() && (int) $advance->installments_count > 0) {
            $total = (int) $advance->installments_count;
            $paid = (int) $advance->paid_installments;

            if (! $month) {
                throw new \RuntimeException('حدّد شهرَ التسوية للسلفة المجدولة.');
            }

            $start = Carbon::parse(($advance->start_month ?: now()->format('Y-m')).'-01');
            $until = Carbon::parse($month.'-01');
            $dueUpTo = ($until->year * 12 + $until->month) - ($start->year * 12 + $start->month) + 1;
            $count = min($dueUpTo - $paid, $total - $paid);

            if ($count <= 0) {
                throw new \RuntimeException('لا أقساط مستحقّة حتى الشهر المحدّد.');
            }

            $full = ($paid + $count) >= $total;
            // القسطُ الأخير يحمل فرقَ التقريب: الإقفالُ بالمتبقّي لا بعدد × قسط
            $value = $full ? $remaining : min($remaining, round($count * (float) $advance->installment_amount, 2));

            return ['amount' => round($value, 2), 'installments' => $count, 'full' => $full];
        }

        $value = $amount !== null && $amount > 0 ? min($amount, $remaining) : $remaining;

        return ['amount' => round($value, 2), 'installments' => 0, 'full' => $value >= $remaining - 0.001];
    }

    /**
     * تحصيلُ سدادٍ نقديٍّ من الموظف — التسوية.
     *
     *     مدين  الخزينة
     *         دائن  حساب سلف الموظف
     *
     * لا تُلغي السلفة: تنقلها في دورة السداد (قيد السداد ← مسدَّدة بالكامل).
     */
    public function repayCash(SalaryAdvanceRequest $advance, int $treasuryAccountId, ?string $month = null, ?float $amount = null, ?string $notes = null): JournalEntry
    {
        $advance = $this->lockRequest($advance);

        if (! $advance->isDisbursed()) {
            throw new \RuntimeException($advance->status === 'approved'
                ? 'السلفة لم تُصرف بعد — لا يُحصَّل سدادُ ما لم يُصرف.'
                : 'لا تُسوّى إلّا سلفةٌ مصروفة.');
        }
        if ($advance->is_settled || $advance->remainingAmount() <= 0.001) {
            throw new \RuntimeException('هذه السلفة مسدَّدة بالكامل.');
        }

        if ($block = TreasuryAccessGuard::depositBlockReason($treasuryAccountId)) {
            throw new \RuntimeException($block);
        }

        $treasury = Account::find($treasuryAccountId);
        if (! $treasury) {
            throw new \RuntimeException('الخزينة المختارة غير موجودة.');
        }

        $advanceAccount = $advance->employee->getAdvanceAccountOrFail();
        $plan = $this->cashRepaymentPlan($advance, $month, $amount);

        if ($plan['amount'] <= 0) {
            throw new \RuntimeException('لا مبلغَ مستحقّاً للتحصيل.');
        }

        $name = $this->employeeName($advance->employee);
        $description = $plan['installments'] > 0
            ? 'تحصيل '.$plan['installments'].' قسط نقداً من السلفة المجدولة رقم '.$advance->id.' - الموظف '.$name
            : 'تحصيل سداد نقدي من السلفة رقم '.$advance->id.' - الموظف '.$name;
        if ($plan['full']) {
            $description .= ' (سداد نهائي)';
        }

        $journal = $this->createHrJournalEntry([
            'reference_number' => 'ADV-SETTLE-'.$advance->id.'-'.now()->format('YmdHis'),
            'reference_id' => $advance->id,
            'date' => now(),
            'description' => $description,
            'status' => 1,
            'employee_id' => $advance->employee_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::EXPENSES,
            'linking_type' => LinkingType::SINGLE,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $treasury->id,
            'description' => "تحصيل سداد سلفة نقداً من الموظف {$name}",
            'debit' => $plan['amount'],
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $advanceAccount->id,
            'description' => "سداد سلفة نقداً - {$advanceAccount->name}",
            'debit' => 0,
            'credit' => $plan['amount'],
            'is_debit' => false,
        ]);

        $paidInstallments = (int) $advance->paid_installments + $plan['installments'];
        $settledAmount = round((float) ($advance->settled_amount ?? 0) + $plan['amount'], 2);

        $advance->update([
            'paid_installments' => $paidInstallments,
            'remaining_installments' => $advance->isScheduled() ? max(0, (int) $advance->installments_count - $paidInstallments) : $advance->remaining_installments,
            'settled_amount' => $settledAmount,
            'is_settled' => $plan['full'],
            'settled_date' => $plan['full'] ? now()->toDateString() : $advance->settled_date,
            'settlement_type' => 'cash',
        ]);

        $this->log($advance->id, 'تحصيل سداد نقدي '.CurrencySettings::format($plan['amount']).
            ($plan['installments'] ? ' ('.$paidInstallments.'/'.$advance->installments_count.' قسط)' : '').
            ($plan['full'] ? ' - سداد نهائي' : '').
            ($notes ? ' — '.$notes : ''));

        $this->notifyEmployee($advance, 'تسوية السلفة', 'تم تحصيل '.CurrencySettings::format($plan['amount']).' نقداً من سلفتك رقم #'.$advance->id.
            ($plan['full'] ? ' - سُدِّدت بالكامل' : ' - المتبقّي '.CurrencySettings::format(max(0, (float) $advance->amount - $settledAmount))), 'advance_settled');

        return $journal;
    }

    /**
     * سببُ منع إلغاء السلفة، أو `null` إن جاز.
     */
    public function cancelBlockReason(SalaryAdvanceRequest $advance): ?string
    {
        if (in_array($advance->status, ['rejected', 'cancelled'], true)) {
            return 'السلفة مغلقة أصلاً ('.$advance->stageLabel().').';
        }

        if ($advance->isDisbursed() && $advance->hasRepaymentActivity()) {
            return 'بدأ سدادُ هذه السلفة (نقداً أو من الراتب أو هي مدرجة في مسير رواتب قائم) — لا تُلغى، بل تُكمَل تسويتُها.';
        }

        return null;
    }

    /**
     * إلغاءُ السلفة — عمليةٌ مستقلّة عن التسوية.
     *
     *   • معلّقة أو معتمدة لم تُصرف ← تُغلق «ملغاة» بلا قيد.
     *   • مصروفة لم يُسدَّد منها شيء ← قيدٌ عكسيّ إلى الخزينة التي صُرفت منها:
     *         مدين  الخزينة
     *             دائن  حساب سلف الموظف
     *   • بدأ سدادُها ← مرفوض.
     *
     * @return JournalEntry|null القيدُ العكسيّ إن كُتب
     */
    public function cancel(SalaryAdvanceRequest $advance, string $reason): ?JournalEntry
    {
        $advance = $this->lockRequest($advance);

        if ($block = $this->cancelBlockReason($advance)) {
            throw new \RuntimeException($block);
        }

        $journal = null;

        if ($advance->isDisbursed()) {
            $treasury = $advance->treasury_id ? Account::find($advance->treasury_id) : null;
            if (! $treasury) {
                throw new \RuntimeException('الخزينة التي صُرفت منها السلفة غير موجودة — لا يُعرف إلى أين يُردّ المبلغ.');
            }

            $advanceAccount = $advance->employee->getAdvanceAccountOrFail();
            $amount = round((float) $advance->amount, 2);
            $name = $this->employeeName($advance->employee);

            $journal = $this->createHrJournalEntry([
                'reference_number' => 'ADV-CANCEL-'.$advance->id,
                'reference_id' => $advance->id,
                'date' => now(),
                'description' => "إلغاء السلفة رقم {$advance->id} للموظف {$name} وردّ مبلغها إلى {$treasury->name} — {$reason}",
                'status' => 1,
                'employee_id' => $advance->employee_id,
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::EXPENSES,
                'linking_type' => LinkingType::SINGLE,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $treasury->id,
                'description' => "ردّ سلفة ملغاة من الموظف {$name}",
                'debit' => $amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $advanceAccount->id,
                'description' => "إلغاء سلفة - {$advanceAccount->name}",
                'debit' => 0,
                'credit' => $amount,
                'is_debit' => false,
            ]);
        }

        $advance->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $reason,
            'cancellation_journal_id' => $journal?->id,
        ]);

        $this->log($advance->id, 'أُلغيت السلفة: '.$reason.($journal ? ' — قيد عكسي #'.$journal->id : ''));

        return $journal;
    }

    /* ══════════════════ النظام القديم: salary_advances ══════════════════ */

    /**
     * قيدُ صرف السلفة القديمة وخزينتُه: القيدُ مرجعُه رقمُ السلفة عارياً،
     * فيُعرف بسطرَيه (مدين حساب السلف بالمبلغ، ودائن غيرِه بالمبلغ نفسِه).
     *
     * @return array{journal_id: int, treasury_id: int}|null
     */
    public function legacyDisbursement(SalaryAdvance $advance): ?array
    {
        $advanceAccountId = $advance->employee?->employee_advance_account_id;
        if (! $advanceAccountId) {
            return null;
        }

        $row = DB::table('journal_entries as je')
            ->join('journal_entry_details as dr', function ($j) use ($advanceAccountId) {
                $j->on('dr.journal_entry_id', '=', 'je.id')->where('dr.account_id', $advanceAccountId)->where('dr.debit', '>', 0);
            })
            ->join('journal_entry_details as cr', function ($j) use ($advanceAccountId) {
                $j->on('cr.journal_entry_id', '=', 'je.id')->where('cr.account_id', '!=', $advanceAccountId)->where('cr.credit', '>', 0);
            })
            ->where('je.reference_number', (string) $advance->id)
            ->where('je.employee_id', $advance->employee_id)
            ->whereRaw('ABS(dr.debit - ?) < 0.01', [(float) $advance->amount])
            ->select('je.id as journal_id', 'cr.account_id as treasury_id')
            ->orderByDesc('je.id')
            ->first();

        return $row ? ['journal_id' => (int) $row->journal_id, 'treasury_id' => (int) $row->treasury_id] : null;
    }

    public function legacyCancelBlockReason(SalaryAdvance $advance): ?string
    {
        if (($advance->status ?? 'active') === 'cancelled') {
            return 'السلفة ملغاة أصلاً.';
        }

        $installmentIds = InstallmentPayment::where('salary_advance_id', $advance->id)->pluck('id');

        if (InstallmentPayment::whereIn('id', $installmentIds)->where('status', 'paid')->exists()) {
            return 'سُدِّد من هذه السلفة قسطٌ أو أكثر — لا تُلغى، بل تُكمَل أقساطُها.';
        }

        $inRun = DB::table('payroll_run_items as i')
            ->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->where('i.source_table', 'installment_payments')
            ->whereIn('i.source_id', $installmentIds->all() ?: [0])
            ->whereNull('r.reversed_at')
            ->exists();

        if ($inRun) {
            return 'أحدُ أقساط السلفة مدرجٌ في مسير رواتب قائم — لا تُلغى قبل عكسه.';
        }

        return null;
    }

    /**
     * إلغاءُ سلفةٍ من النظام القديم — بدلُ «الحذف» الذي كان يمحو السجلَّ
     * ويُبقي قيدَ صرفه: قيدٌ عكسيٌّ إلى الخزينة، وأقساطٌ غيرُ مسدَّدة تُوسَم
     * «ملغاة» فلا تلتقطها خطّةُ الرواتب، والسجلُّ يبقى بحالة «ملغاة».
     */
    public function cancelLegacy(SalaryAdvance $advance, string $reason): ?JournalEntry
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('عمليات السلفة تُستدعى داخل معاملة.');
        }

        $advance = SalaryAdvance::with('employee')->lockForUpdate()->findOrFail($advance->id);

        if ($block = $this->legacyCancelBlockReason($advance)) {
            throw new \RuntimeException($block);
        }

        $journal = null;
        $source = $this->legacyDisbursement($advance);

        if ($source) {
            $treasury = Account::find($source['treasury_id']);
            if (! $treasury) {
                throw new \RuntimeException('الخزينة التي صُرفت منها السلفة غير موجودة.');
            }

            $advanceAccount = $advance->employee->getAdvanceAccountOrFail();
            $amount = round((float) $advance->amount, 2);
            $name = $this->employeeName($advance->employee);

            $journal = $this->createHrJournalEntry([
                'reference_number' => 'ADV-A-CANCEL-'.$advance->id,
                'reference_id' => $advance->id,
                'date' => now(),
                'description' => "إلغاء السلفة رقم {$advance->id} للموظف {$name} وردّ مبلغها إلى {$treasury->name} — {$reason}",
                'status' => 1,
                'employee_id' => $advance->employee_id,
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::EXPENSES,
                'linking_type' => LinkingType::SINGLE,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $treasury->id,
                'description' => "ردّ سلفة ملغاة من الموظف {$name}",
                'debit' => $amount,
                'credit' => 0,
                'is_debit' => true,
            ]);

            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $advanceAccount->id,
                'description' => "إلغاء سلفة - {$advanceAccount->name}",
                'debit' => 0,
                'credit' => $amount,
                'is_debit' => false,
            ]);
        }

        InstallmentPayment::where('salary_advance_id', $advance->id)->where('status', 'unpaid')->update(['status' => 'cancelled']);

        $advance->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $reason,
            'cancellation_journal_id' => $journal?->id,
        ]);

        ModelsLog::create([
            'type' => 'salary_advance_log',
            'type_id' => $advance->id,
            'type_log' => 'log',
            'description' => 'أُلغيت السلفة: '.$reason.($journal ? ' — قيد عكسي #'.$journal->id : ' — لم يُعثر على قيد صرف لعكسه'),
            'created_by' => Auth::id(),
        ]);

        return $journal;
    }

    /* ══════════════════ مساعدات ══════════════════ */

    private function log(int $advanceId, string $description): void
    {
        ModelsLog::create([
            'type' => 'salary_advance_request_log',
            'type_id' => $advanceId,
            'type_log' => 'log',
            'description' => $description,
            'created_by' => Auth::id(),
        ]);
    }

    private function notifyEmployee(SalaryAdvanceRequest $advance, string $title, string $description, string $type): void
    {
        $receiverId = DB::table('users')->where('employee_id', $advance->employee_id)->value('id');

        if (! $receiverId || (int) $receiverId === (int) Auth::id()) {
            return;
        }

        notifications::create([
            'user_id' => Auth::id(),
            'receiver_id' => $receiverId,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'read' => 0,
            'is_scheduled' => 0,
            'is_sent' => 1,
        ]);
    }
}
