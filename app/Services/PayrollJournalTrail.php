<?php

namespace App\Services;

use App\Models\Deduction;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalarySlip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «القيود المحاسبية» لصرفة راتب أو قسيمة — كلُّ قيدٍ يخصّها في موضعٍ واحد.
 *
 * كانت الصرفةُ تعرف قيدَيها بعمودين (`journal_entry_id`، `accrual_journal_entry_id`)
 * يُصفَّران عند العكس، والقسيمةُ لا تعرض قيداً أصلاً. فمن صرف ثمّ عكس لا يجد
 * على الشاشة ما يدلّ على أنّ شيئاً قُيِّد، ويجد في دفتر اليومية قيدَ خصميةٍ
 * (`DED-*`) لا يربطه بقسيمةٍ شيء.
 *
 * فيُجمع هنا — قراءةً لا كتابة — بالمراجع الثابتة التي يكتبها المُرحِّلون:
 *
 *   SAL-ACC-{run}    استحقاق         (`PayrollAccountant`)
 *   SAL-PAY-{run}    صرف             — `reference_id` فيه معرّفُ سند الصرف لا الصرفة، عمداً
 *   SAL-GOSI-{run}   تأمينات (حصة المنشأة)
 *   REVERSE_*        عكس             (`PayrollReversalService`)
 *   DED-{id}[-n]     خصمية مُقيَّدة عند اعتمادها، اقتُطعت في هذه الصرفة
 *   DED-CAN/WVR-{id} إلغاء/إعفاء خصمية
 *
 * والخصميةُ تُعرف بأنها اقتُطعت في الصرفة من بنودها (`payroll_run_items`) أو —
 * بعد العكس، حين تُحذف البنود — من سجلّها (`deduction_histories`).
 */
class PayrollJournalTrail
{
    public const TYPE_ACCRUAL = 'accrual';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_GOSI = 'gosi';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_REWARD = 'reward';

    public const TYPE_REVERSAL = 'reversal';

    public const LABELS = [
        self::TYPE_ACCRUAL => 'استحقاق',
        self::TYPE_PAYMENT => 'صرف',
        self::TYPE_GOSI => 'تأمينات',
        self::TYPE_DEDUCTION => 'خصمية',
        self::TYPE_REWARD => 'مكافأة',
        self::TYPE_REVERSAL => 'عكس',
    ];

    public const TONES = [
        self::TYPE_ACCRUAL => 'neutral',
        self::TYPE_PAYMENT => 'on',
        self::TYPE_GOSI => 'neutral',
        self::TYPE_DEDUCTION => 'warn',
        self::TYPE_REWARD => 'on',
        self::TYPE_REVERSAL => 'off',
    ];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forRun(PayrollRun $run): Collection
    {
        return $this->build([$run]);
    }

    /**
     * كلُّ قيود صرفات القسيمة، وما حمل `salary_id` القسيمةِ من غيرها.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forSlip(SalarySlip $slip): Collection
    {
        $runs = $slip->relationLoaded('payrollRuns') ? $slip->payrollRuns : $slip->payrollRuns()->get();

        return $this->build($runs->all(), (int) $slip->id);
    }

    /**
     * الخصمياتُ المُقيَّدة على ذمّة الموظف ولم تُقتطع بعد — قيدُها قائمٌ
     * والاقتطاعُ ينتظر الراتبَ القادم. هي ما يُفسِّر قيدَ `DED-*` الباقي بعد
     * عكس صرفة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pendingPostedDeductions(?Employee $employee): Collection
    {
        if (! $employee || ! Schema::hasTable('deductions') || ! Schema::hasColumn('deductions', 'posted_amount')) {
            return collect();
        }

        return Deduction::query()
            ->where('employee_id', $employee->id)
            ->unsettled()
            ->where('posted_amount', '>', 0.009)
            ->orderBy('date')
            ->get()
            ->map(function (Deduction $d) {
                $journal = $d->journal_entry_id ? JournalEntry::withoutGlobalScopes()->find($d->journal_entry_id) : null;

                return [
                    'deduction' => $d,
                    'remaining' => round((float) $d->amount - (float) $d->settled_amount, 2),
                    'journal_id' => $journal?->id,
                    'journal_ref' => $journal?->reference_number,
                    'journal_url' => $journal ? $this->url($journal->id) : null,
                ];
            })
            ->filter(fn ($row) => $row['remaining'] > 0.009)
            ->values();
    }

    /**
     * @param  array<int, PayrollRun>  $runs
     * @return Collection<int, array<string, mixed>>
     */
    private function build(array $runs, ?int $slipId = null): Collection
    {
        if (! Schema::hasTable('journal_entries')) {
            return collect();
        }

        $refs = [];
        $ids = [];
        $runIds = [];
        foreach ($runs as $run) {
            $runIds[] = (int) $run->id;
            foreach (['SAL-ACC-', 'SAL-PAY-', 'SAL-GOSI-'] as $prefix) {
                $refs[] = $prefix.$run->id;
                $refs[] = 'REVERSE_'.$prefix.$run->id;
            }
            foreach (['journal_entry_id', 'accrual_journal_entry_id', 'reversal_journal_entry_id'] as $col) {
                if ($run->{$col}) {
                    $ids[] = (int) $run->{$col};
                }
            }
        }

        $deductionIds = $this->deductionIdsFor($runIds);
        $rewardJournalIds = $this->rewardJournalIdsFor($runIds);
        $ids = array_merge($ids, $rewardJournalIds);

        $query = JournalEntry::withoutGlobalScopes()->where(function ($q) use ($refs, $ids, $deductionIds, $slipId) {
            $q->whereIn('reference_number', $refs ?: ['__none__']);
            if ($ids) {
                $q->orWhereIn('id', $ids);
            }
            foreach ($deductionIds as $dedId) {
                $q->orWhere('reference_number', 'DED-'.$dedId)
                    ->orWhere('reference_number', 'like', 'DED-'.$dedId.'-%')
                    ->orWhere('reference_number', 'DED-CAN-'.$dedId)
                    ->orWhere('reference_number', 'DED-WVR-'.$dedId);
            }
            if ($slipId) {
                $q->orWhere('salary_id', $slipId);
            }
        });

        $entries = $query->orderBy('date')->orderBy('id')->get()->unique('id');
        if ($entries->isEmpty()) {
            return collect();
        }

        $totals = DB::table('journal_entry_details')
            ->whereIn('journal_entry_id', $entries->pluck('id'))
            ->groupBy('journal_entry_id')
            ->selectRaw('journal_entry_id, SUM(debit) as dr, SUM(credit) as cr')
            ->get()
            ->keyBy('journal_entry_id');

        return $entries->map(function (JournalEntry $entry) use ($totals, $rewardJournalIds) {
            $type = $this->typeOf((string) $entry->reference_number, in_array((int) $entry->id, $rewardJournalIds, true));
            $t = $totals->get($entry->id);

            return [
                'id' => (int) $entry->id,
                'reference' => (string) $entry->reference_number,
                'date' => $entry->date ? \Carbon\Carbon::parse($entry->date)->toDateString() : null,
                'description' => (string) $entry->description,
                'type' => $type,
                'label' => self::LABELS[$type] ?? 'قيد',
                'tone' => self::TONES[$type] ?? 'neutral',
                'amount' => round((float) ($t->dr ?? 0), 2),
                'balanced' => $t ? abs((float) $t->dr - (float) $t->cr) < 0.01 : true,
                'is_reversed' => (bool) $entry->is_reversed,
                'url' => $this->url((int) $entry->id),
            ];
        })->values();
    }

    private function typeOf(string $ref, bool $isReward): string
    {
        return match (true) {
            str_starts_with($ref, 'REVERSE_') => self::TYPE_REVERSAL,
            str_starts_with($ref, 'DED-CAN-'), str_starts_with($ref, 'DED-WVR-') => self::TYPE_REVERSAL,
            str_starts_with($ref, 'SAL-ACC-') => self::TYPE_ACCRUAL,
            str_starts_with($ref, 'SAL-PAY-') => self::TYPE_PAYMENT,
            str_starts_with($ref, 'SAL-GOSI-') => self::TYPE_GOSI,
            str_starts_with($ref, 'DED-') => self::TYPE_DEDUCTION,
            $isReward => self::TYPE_REWARD,
            default => self::TYPE_ACCRUAL,
        };
    }

    /**
     * الخصمياتُ التي اقتُطعت في هذه الصرفات — من البنود القائمة، ثمّ من السجلّ
     * (بعد العكس تُحذف البنود ويبقى السجلّ: «… (run #45)» و«عكس تسوية من صرف راتب #45»).
     *
     * @param  array<int, int>  $runIds
     * @return array<int, int>
     */
    private function deductionIdsFor(array $runIds): array
    {
        if ($runIds === []) {
            return [];
        }

        $ids = Schema::hasTable('payroll_run_items')
            ? PayrollRunItem::query()
                ->whereIn('payroll_run_id', $runIds)
                ->where('item_type', PayrollRunItem::TYPE_DEDUCTION)
                ->where('source_table', 'deductions')
                ->pluck('source_id')->all()
            : [];

        if (Schema::hasTable('deduction_histories')) {
            $history = DB::table('deduction_histories')->where(function ($q) use ($runIds) {
                foreach ($runIds as $id) {
                    $q->orWhere('description', 'like', '%(run #'.$id.')')
                        ->orWhere('description', 'عكس تسوية من صرف راتب #'.$id);
                }
            })->pluck('deduction_id')->all();
            $ids = array_merge($ids, $history);
        }

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    /**
     * قيودُ مكافآت الكتالوج المربوطة بالصرفة (إن قُيِّد لها قيدٌ مستقلّ).
     * المكافأةُ النقدية المصروفة مع الراتب تُقيَّد داخل قيد الاستحقاق نفسِه.
     *
     * @param  array<int, int>  $runIds
     * @return array<int, int>
     */
    private function rewardJournalIdsFor(array $runIds): array
    {
        if ($runIds === [] || ! Schema::hasTable('reward_redemptions') || ! Schema::hasColumn('reward_redemptions', 'payroll_run_id')) {
            return [];
        }

        return DB::table('reward_redemptions')
            ->whereIn('payroll_run_id', $runIds)
            ->pluck('metadata')
            ->map(fn ($m) => (int) ((is_string($m) ? json_decode($m, true) : (array) $m)['journal_entry_id'] ?? 0))
            ->filter()
            ->values()
            ->all();
    }

    private function url(int $journalId): ?string
    {
        if (! \Illuminate\Support\Facades\Route::has('journal.show') || ! hasPermission('account.journal.view')) {
            return null;
        }

        return route('journal.show', $journalId);
    }
}
