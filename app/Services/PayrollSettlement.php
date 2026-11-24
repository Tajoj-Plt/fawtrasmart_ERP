<?php

namespace App\Services;

use App\Models\Deduction;
use App\Models\DeductionHistory;
use App\Models\Employee;
use App\Models\InstallmentPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRequest;
use App\Models\SalarySlip;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * تسوية السلف والخصميات عند الصرف — التخطيط ثم التطبيق.
 *
 * الحساب يستدعي التخطيط فقط؛ التطبيق يكتب في جداول السلف والخصميات
 * بعد نجاح السند والقيود.
 */
class PayrollSettlement
{
    /**
     * السلف غير المسواة المرشحة للخصم من هذا الـ run.
     *
     * @return array<int, array{advance_id:int, source_table:string, source_id:int, amount:float, name:string, description:?string, system:string}>
     */
    public function unsettledAdvances(Employee $employee, Carbon $periodTo): array
    {
        $rows = [];

        $empAdvAccountId = $employee->employee_advance_account_id;
        if (! $empAdvAccountId) {
            return [];
        }

        $isLinkedToEmployeeAccount = function (int $advanceRequestId) use ($empAdvAccountId): bool {
            return DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'jed.journal_entry_id', '=', 'je.id')
                ->where('jed.account_id', $empAdvAccountId)
                ->where('jed.debit', '>', 0)
                ->where(function ($q) use ($advanceRequestId) {
                    $q->where('je.reference_number', 'ADV-REQ-'.$advanceRequestId)
                        ->orWhere('je.reference_number', 'ADV-ISSUE-'.$advanceRequestId)
                        ->orWhere(function ($q2) use ($advanceRequestId) {
                            $q2->where('je.reference_id', $advanceRequestId)
                                ->where('je.reference_number', 'NOT LIKE', 'SAL-%');
                        });
                })
                ->exists();
        };

        $advancesA = SalaryAdvance::where('employee_id', $employee->id)
            ->where(function ($q) {
                $q->where('pay_from_salary', 1)->orWhereNull('pay_from_salary');
            })
            ->whereRaw('paid_installments < total_installments')
            ->with(['payments' => function ($q) use ($periodTo) {
                $q->where('status', 'unpaid')
                    ->whereDate('due_date', '<=', $periodTo->toDateString())
                    ->orderBy('due_date');
            }])
            ->get();

        foreach ($advancesA as $adv) {
            if (! $isLinkedToEmployeeAccount($adv->id)) {
                Log::info('تخطي سلفة (نظام A) غير مرتبطة بحساب الموظف', [
                    'advance_id' => $adv->id, 'employee_id' => $employee->id,
                ]);

                continue;
            }
            foreach ($adv->payments as $inst) {
                $rows[] = [
                    'system' => 'A',
                    'advance_id' => $adv->id,
                    'source_table' => 'installment_payments',
                    'source_id' => $inst->id,
                    'amount' => (float) $inst->amount,
                    // القسطُ ذرّة: `installment_payments` لا عمودَ مبلغٍ مسدَّدٍ فيه،
                    // حالتُه `paid` أو `unpaid` — فلا يُقتطع منه جزء
                    'unit' => (float) $inst->amount,
                    'name' => 'قسط سلفة #'.$adv->id.' (قسط '.$inst->installment_number.')',
                    'description' => 'استحقاق '.$inst->due_date,
                ];
            }
        }

        $advancesB = SalaryAdvanceRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('is_settled', false)
            ->get();

        foreach ($advancesB as $advB) {
            if (! $isLinkedToEmployeeAccount($advB->id)) {
                Log::info('تخطي سلفة (نظام B) غير مرتبطة بحساب الموظف', [
                    'advance_id' => $advB->id, 'employee_id' => $employee->id,
                ]);

                continue;
            }

            if (($advB->advance_type ?? 'normal') === 'scheduled') {
                $startMonth = Carbon::parse(($advB->start_month ?? now()->format('Y-m')).'-01');
                $paidCount = (int) ($advB->paid_installments ?? 0);
                $totalCount = (int) ($advB->installments_count ?? 0);
                if ($totalCount === 0 || $paidCount >= $totalCount) {
                    continue;
                }

                $nextDueMonth = $startMonth->copy()->addMonths($paidCount);
                if ($nextDueMonth->gt($periodTo)) {
                    continue;
                }

                $monthsDiff = ($periodTo->year * 12 + $periodTo->month) - ($nextDueMonth->year * 12 + $nextDueMonth->month);
                $dueCount = min($monthsDiff + 1, $totalCount - $paidCount);
                if ($dueCount <= 0) {
                    continue;
                }

                $installmentAmount = (float) ($advB->installment_amount ?? ((float) $advB->amount / max(1, $totalCount)));
                $totalDue = $installmentAmount * $dueCount;

                $rows[] = [
                    'system' => 'B',
                    'advance_id' => $advB->id,
                    'source_table' => 'salary_advance_requests',
                    'source_id' => $advB->id,
                    'amount' => $totalDue,
                    'name' => 'قسط سلفة مجدولة #'.$advB->id." ({$dueCount} قسط)",
                    'description' => "أقساط مستحقة حتى {$periodTo->toDateString()}",
                    // `paid_installments` يعدّ أقساطاً كاملة، فالوحدةُ قسطٌ لا مبلغٌ حرّ
                    'unit' => $installmentAmount,
                    '__extra' => [
                        'due_count' => $dueCount,
                        'amount_per_installment' => $installmentAmount,
                    ],
                ];
            } else {
                $rows[] = [
                    'system' => 'B',
                    'advance_id' => $advB->id,
                    'source_table' => 'salary_advance_requests',
                    'source_id' => $advB->id,
                    // المتبقّي لا أصلُ المبلغ: ما سُدِّد نقداً أو اقتُطع جزئياً من
                    // راتبٍ سابق (`settled_amount`) لا يُقتطع مرّةً ثانية
                    'amount' => max(0.0, round((float) $advB->amount - (float) ($advB->settled_amount ?? 0), 2)),
                    // `settled_amount` عمودُ مبلغ، فالسدادُ الجزئيُّ يُسجَّل فيه بدقّة
                    'unit' => null,
                    'name' => 'تسوية سلفة عادية #'.$advB->id,
                    'description' => $advB->reason ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Deduction>  $deductions
     * @return array{items: array<int, array{deduction: Deduction, apply: float}>, total: float}
     */
    public function planDeductions($deductions, float $maxBudget): array
    {
        $items = [];
        $total = 0.0;
        $remaining = $maxBudget;

        foreach ($deductions as $d) {
            $needed = (float) $d->remaining_amount;
            if ($needed <= 0 || $remaining <= 0) {
                continue;
            }

            $apply = min($needed, $remaining);
            $items[] = ['deduction' => $d, 'apply' => $apply];
            $total += $apply;
            $remaining -= $apply;
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param  array<int, array>  $advances
     * @return array{items: array<int, array>, total: float}
     */
    /**
     * ما يُخصَم من السلف في هذه الصرفة، بحدود المتاح من الاستحقاق.
     *
     * **ما كان**
     *
     * كلُّ سلفةٍ لا يكفيها المتبقي تُقتطع جزئياً بمقدار المتبقي، وتُعلَّم
     * `__partial`. فينزل صافي الموظف بالجزء، ويدخل الجزءُ في
     * `totalAdvances` فيُدان به حسابُ السلف في قيد الصرف — أي يُثبت الدفترُ
     * أنّ المدينيةَ نزلت. ثمّ يعود `applyAdvanceSettlement()` **بلا فعل**
     * للأقساط الجزئية، فيبقى القسطُ `unpaid`: فيُطالَب الموظفُ به كاملاً في
     * الشهر التالي — يدفع جزءَه مرّتين، ويُدان حسابُ السلف بأكثر مما عليه.
     *
     * **ما صار**
     *
     * لكلِّ سلفةٍ أصغرُ وحدةٍ يمكن تسجيلُها (`unit`):
     *
     *   • قسطُ نظام A: الوحدةُ القسطُ كلُّه — `installment_payments` لا
     *     عمودَ مبلغٍ مسدَّدٍ فيه، فإمّا `paid` أو لا شيء.
     *   • السلفةُ المجدولة: الوحدةُ قسطٌ كامل — `paid_installments` يعدّ
     *     أقساطاً، فيُنقص عددُ الأقساط المخصومة إلى ما يتّسع له المتبقي.
     *   • السلفةُ العادية: `unit === null` — `settled_amount` عمودُ مبلغٍ
     *     يقبل الكسر، فالتجزئةُ هنا تُسجَّل بدقّةٍ وتبقى كما كانت.
     *
     * فلا يُخصَم من الموظف قرشٌ لا يقابله سطرٌ يُثبته.
     *
     * @param  array<int, array>  $advances
     * @return array{items: array<int, array>, total: float}
     */
    public function planAdvances(array $advances, float $maxBudget): array
    {
        $items = [];
        $total = 0.0;
        $remaining = $maxBudget;

        foreach ($advances as $adv) {
            $amount = (float) $adv['amount'];
            if ($amount <= 0 || $remaining <= 0) {
                continue;
            }

            if ($amount <= $remaining) {
                $items[] = $adv;
                $total += $amount;
                $remaining -= $amount;

                continue;
            }

            // مبلغٌ حرٌّ يقبل الكسر — يُقتطع المتبقي ويُسجَّل بدقّته
            if (($adv['unit'] ?? null) === null) {
                $partialAdv = $adv;
                $partialAdv['amount'] = $remaining;
                $partialAdv['name'] = ($adv['name'] ?? 'سلفة').' (جزئي)';
                $partialAdv['__partial'] = true;
                $items[] = $partialAdv;
                $total += $remaining;

                break;
            }

            $unit = (float) $adv['unit'];

            /*
                الاقتطاعُ بوحداتٍ كاملة. والسماحُ الصغير في القسمة لأنّ
                المتبقي حصيلةُ طرحِ عشريّاتٍ متتابعة: قسطٌ بـ333.33 ومتبقٍّ
                999.99 يجب أن يخرج ثلاثةَ أقساطٍ لا اثنَين.
            */
            $units = $unit > 0 ? (int) floor(($remaining + 0.005) / $unit) : 0;

            if ($units <= 0) {
                break;   // لا يتّسع المتبقي لوحدةٍ واحدة — فلا يُخصَم شيء
            }

            $partialAdv = $adv;
            $partialAdv['amount'] = round($units * $unit, 2);

            if (isset($partialAdv['__extra']['due_count'])) {
                $partialAdv['__extra']['due_count'] = $units;
                $partialAdv['name'] = 'قسط سلفة مجدولة #'.$adv['advance_id']." ({$units} قسط)";
            }

            $items[] = $partialAdv;
            $total += $partialAdv['amount'];

            break;
        }

        return ['items' => $items, 'total' => $total];
    }

    public function applyDeductions(PayrollCalculation $calc, PayrollRun $run, SalarySlip $slip, int $settledBy): void
    {
        foreach ($calc->deductionPlan['items'] as $entry) {
            /** @var Deduction $d */
            $d = $entry['deduction'];
            $applyAmount = (float) $entry['apply'];
            if ($applyAmount <= 0) {
                continue;
            }

            $newSettled = (float) $d->settled_amount + $applyAmount;
            $isFullSettlement = $newSettled >= (float) $d->amount - 0.001;

            $d->update([
                'settled_amount' => $newSettled,
                'status' => $isFullSettlement
                    ? Deduction::STATUS_SETTLED
                    : Deduction::STATUS_PARTIALLY_SETTLED,
                'settled_at' => $isFullSettlement ? now() : $d->settled_at,
            ]);

            DeductionHistory::create([
                'deduction_id' => $d->id,
                'action' => DeductionHistory::ACTION_SETTLED,
                'user_id' => $settledBy,
                'description' => ($isFullSettlement ? 'تسوية كاملة' : 'تسوية جزئية').
                    " من قسيمة الراتب #{$slip->id} (run #{$run->id})",
                'amount' => $applyAmount,
                'notes' => 'الفترة: '.$run->period_from->toDateString().' → '.$run->period_to->toDateString().
                    ($isFullSettlement ? '' : ' — متبقي: '.number_format((float) $d->amount - $newSettled, 2)),
            ]);

            PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_DEDUCTION,
                'source_table' => 'deductions',
                'source_id' => $d->id,
                'name' => ($d->type_label ?? 'خصمية').' - '.Str::limit($d->reason, 40).
                    ($isFullSettlement ? '' : ' (جزئي)'),
                'amount' => $applyAmount,
            ]);
        }
    }

    public function applyAdvances(PayrollCalculation $calc, PayrollRun $run): void
    {
        foreach ($calc->advancesToSettle as $adv) {
            $this->applyAdvanceSettlement($adv);
            PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_ADVANCE,
                'source_table' => $adv['source_table'],
                'source_id' => $adv['source_id'],
                'name' => $adv['name'],
                'amount' => (float) $adv['amount'],
                'description' => $adv['description'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $adv
     */
    private function applyAdvanceSettlement(array $adv): void
    {
        $isPartial = ! empty($adv['__partial']);

        if ($adv['source_table'] === 'installment_payments') {
            /*
                لا يُخصَم قسطٌ جزئياً: لا موضعَ في الجدول يُسجَّل فيه ذلك.
                و`planAdvances()` لا يُنتج جزئياً لهذا النوع أصلاً (وحدتُه
                القسطُ كلُّه) — فالوصولُ إلى هنا خللٌ في الخطّة لا حالةٌ
                متوقَّعة. وكان الردُّ عليه `return` صامتاً: يُخصَم من الموظف
                ولا يُسجَّل السداد. فيُرفع الآن ليُبطل الصرفةَ كلَّها في
                معاملتها بدل أن يمضي الخصمُ بلا أثر.
            */
            if ($isPartial) {
                throw new \RuntimeException(
                    'خللٌ في خطّة السلف: حُوِّل قسطٌ (#'.$adv['source_id'].') إلى سدادٍ جزئيّ ولا يقبل جدولُه ذلك.'
                );
            }

            $inst = InstallmentPayment::find($adv['source_id']);
            if ($inst && $inst->status === 'unpaid') {
                $inst->update([
                    'status' => 'paid',
                    'payment_date' => now()->toDateString(),
                ]);
                $parent = SalaryAdvance::find($inst->salary_advance_id);
                if ($parent) {
                    $paidCount = $parent->payments()->where('status', 'paid')->count();
                    $parent->update(['paid_installments' => $paidCount]);
                }
            }

            return;
        }

        if ($adv['source_table'] === 'salary_advance_requests') {
            $req = SalaryAdvanceRequest::find($adv['source_id']);
            if (! $req) {
                return;
            }

            if (($req->advance_type ?? 'normal') === 'scheduled') {
                $extra = $adv['__extra'] ?? [];
                $dueCount = (int) ($extra['due_count'] ?? 1);

                /*
                    عددُ الأقساط يأتي من الخطّة بعد تقليصه إلى ما اتّسع له
                    المتبقي، فيُضاف كما هو. وكان الجزئيُّ يزيد
                    `settled_amount` ولا يزيد `paid_installments`، فلا يتقدّم
                    شهرُ الاستحقاق التالي ويُخصَم القسطُ نفسُه مرّةً أخرى.
                */
                if ($isPartial) {
                    throw new \RuntimeException(
                        'خللٌ في خطّة السلف: سلفةٌ مجدولة (#'.$adv['source_id'].') حُوِّلت إلى سدادٍ جزئيٍّ دون عدد أقساط.'
                    );
                }

                $newPaid = (int) ($req->paid_installments ?? 0) + $dueCount;
                $isFull = $newPaid >= (int) $req->installments_count;
                $req->update([
                    'paid_installments' => $newPaid,
                    'remaining_installments' => max(0, (int) $req->installments_count - $newPaid),
                    'is_settled' => $isFull,
                    'settled_date' => $isFull ? now()->toDateString() : $req->settled_date,
                    'settled_amount' => ($req->settled_amount ?? 0) + (float) $adv['amount'],
                    'settlement_type' => 'salary',
                ]);
            } else {
                $newSettled = (float) ($req->settled_amount ?? 0) + (float) $adv['amount'];
                $isFull = $newSettled >= (float) $req->amount - 0.001;
                $req->update([
                    'is_settled' => $isFull,
                    'settled_date' => $isFull ? now()->toDateString() : $req->settled_date,
                    'settled_amount' => $newSettled,
                    'settlement_type' => 'salary',
                ]);
            }
        }
    }
}
