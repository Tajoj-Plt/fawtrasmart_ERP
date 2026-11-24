<?php

namespace App\Services\Rewards;

use App\Enums\RedemptionStatus;
use App\Enums\RewardType;
use App\Models\Account;
use App\Models\Ecosystem\RewardRedemption;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * تنفيذُ مكافآت الموظفين من «كتالوج المكافآت» — عبر الرواتب وأرصدة الإجازات.
 *
 * ══════════════════ مكافأةٌ لا بدل ══════════════════
 *
 *   • **البدل** (بدل سكن، مواصلات…) بندُ راتبٍ تعاقديٌّ متكرّر: يُعرَّف في
 *     «بنود الرواتب»، ويُنسخ إلى العقد والقسيمة، ويُصرف في **كلّ** صرفة.
 *   • **المكافأة** حافزٌ لمرّةٍ واحدة: يكسب الموظفُ نقاطاً (إنجازات، أهداف،
 *     تحدّيات)، ويستبدلها بمكافأةٍ من الكتالوج، فتُعتمد، ثمّ تُصرف **مرّةً واحدة**.
 *
 * ══════════════════ مسارٌ واحدٌ للنقد ══════════════════
 *
 * كانت المكافأةُ النقدية للموظف تُصرف عند الاعتماد من الخزينة مباشرةً
 * (مدين «مكافآت نقدية مستبدلة» / دائن الخزينة): لا تظهر في قسيمة الراتب ولا
 * في الصرفة ولا في ملف الموظف، ولا يمنع شيءٌ أن يُعطى المبلغُ نفسُه مرّةً
 * ثانيةً بندَ «مكافأة» يدويّاً على القسيمة. وكان الاعتمادُ التلقائي (حين لا
 * تُشترط الموافقة) يضع الحالةَ «موافق عليه» بلا أيّ قيدٍ أصلاً.
 *
 * فصار للموظف مسارٌ واحد — **الراتب**:
 *
 *   1. الاعتماد ← سطرُ استحقاقٍ واحد على قسيمة الموظف
 *      (`origin = reward_redemption`، `reward_redemption_id` فريد) على حساب
 *      «مكافآت نقدية مستبدلة» المضبوط — لا احتياطَ مكتوباً في الشيفرة.
 *   2. الصرف ← `PayrollCalculator` يزامن السطر، و`PayrollAccountant` يُحمّله
 *      على حساب المكافآت لا على مصروف الرواتب، ثمّ `markPaid()` يربط الطلبَ
 *      بالصرفة ويفصل السطرَ عن القسيمة فلا يتكرّر في الصرفة التالية.
 *   3. عكسُ الصرفة ← `unpay()` يعيد الطلبَ معلَّقاً ويعيد سطرَه إلى القسيمة.
 *
 * والعميلُ يبقى على مسار الخزينة — لا راتبَ له.
 */
class EmployeeRewardFulfillment
{
    /** مفتاحُ «الحسابات المحاسبية» — يُربط من «إعدادات الحسابات المحاسبية» وحدها (شاشة «حسابات الرواتب» تعرضه للقراءة). */
    public const ACCOUNT_KEY = 'rewards_cash_expense';

    // =====================================================
    // التصنيف
    // =====================================================

    /** مكافأةٌ نقدية لموظف — تُصرف عبر الراتب لا الخزينة. */
    public function isPayrollCash(RewardRedemption $redemption): bool
    {
        return $this->userTypeOf($redemption) === 'employee'
            && $this->rewardTypeOf($redemption) === RewardType::CASH->value;
    }

    /** مكافأةُ «إجازة» لموظف — تُضاف أيامُها إلى رصيد إجازاته. */
    public function isLeaveDay(RewardRedemption $redemption): bool
    {
        return $this->userTypeOf($redemption) === 'employee'
            && $this->rewardTypeOf($redemption) === RewardType::LEAVE_DAY->value;
    }

    public function handles(RewardRedemption $redemption): bool
    {
        return $this->isPayrollCash($redemption) || $this->isLeaveDay($redemption);
    }

    /**
     * طلبٌ نقديٌّ قديم صُرف من الخزينة عند اعتماده (قبل هذا المسار) — له قيدٌ
     * في `metadata.journal_entry_id`. لا يُضاف إلى الراتب أبداً: دُفع فعلاً.
     */
    public function paidFromTreasuryLegacy(RewardRedemption $redemption): bool
    {
        return ! empty(($redemption->metadata ?? [])['journal_entry_id']);
    }

    // =====================================================
    // الاعتماد
    // =====================================================

    /** حسابُ مصروف المكافآت المضبوط — أو null (لا احتياطَ مكتوباً). */
    public function expenseAccountId(): ?int
    {
        $id = AccountingSettings::id(self::ACCOUNT_KEY);

        return ($id && Account::query()->whereKey($id)->exists()) ? (int) $id : null;
    }

    /**
     * سببُ رفض الاعتماد — يُسأل قبل أيّ كتابة، فالرفضُ لا يترك أثراً.
     */
    public function approvalBlockReason(RewardRedemption $redemption): ?string
    {
        if ($this->isPayrollCash($redemption)) {
            if (! $redemption->employee_id) {
                return 'طلب المكافأة النقدية بلا موظف — لا يمكن إضافته إلى الراتب.';
            }
            if ((float) $redemption->reward_value <= 0.009) {
                return 'قيمة المكافأة النقدية صفر — حدّد قيمة المكافأة في الكتالوج أولاً.';
            }
            if (! $this->schemaReady()) {
                return 'جداول ربط المكافآت بالرواتب غير مُرحَّلة — شغّل ترحيل قاعدة البيانات.';
            }
            if (! $this->expenseAccountId()) {
                return 'لا يمكن اعتماد مكافأة نقدية لموظف: '.
                    \App\Support\PayrollAccounts::missingMessage(\App\Support\PayrollAccounts::REWARDS_EXPENSE, 'إعادة الاعتماد');
            }

            return null;
        }

        if ($this->isLeaveDay($redemption)) {
            if (! $redemption->employee_id) {
                return 'طلب مكافأة الإجازة بلا موظف.';
            }
            $reward = $redemption->reward;
            if (! $reward || ! $reward->leave_type_id || ! LeaveType::query()->whereKey($reward->leave_type_id)->exists()) {
                return 'مكافأة «'.($reward->name ?? '').'» لا تحدّد نوع الإجازة الذي تُضاف إليه الأيام. '.
                    'عدّل المكافأة في الكتالوج واختر «نوع الإجازة»، ثم أعد الاعتماد.';
            }
            $days = (float) $redemption->reward_value;
            if ($days < 1 || abs($days - round($days)) > 0.001) {
                return 'رصيد الإجازات يُحفظ بأيامٍ صحيحة، وقيمة هذه المكافأة '.rtrim(rtrim(number_format($days, 2), '0'), '.').
                    ' يوم. عدّل قيمة المكافأة إلى عدد أيامٍ صحيح (1 فأكثر).';
            }

            return null;
        }

        return null;
    }

    /**
     * أثرُ الاعتماد — داخل معاملة المتحكّم، بعد وضع الحالة «موافق عليه».
     */
    public function applyApproval(RewardRedemption $redemption): void
    {
        if ($reason = $this->approvalBlockReason($redemption)) {
            throw new \RuntimeException($reason);
        }

        if ($this->isPayrollCash($redemption)) {
            $item = $this->queueForPayroll($redemption);
            $slipId = $item->salary_slips_id;

            OperationLog::record(
                'approve',
                'reward_redemption',
                $redemption,
                'اعتماد مكافأة نقدية '.$redemption->redemption_number.' بقيمة '.number_format((float) $redemption->reward_value, 2).
                ($slipId ? ' — أُضيفت استحقاقاً على قسيمة الراتب #'.$slipId : ' — تُضاف إلى أول صرفة راتبٍ للموظف')
            );

            return;
        }

        if ($this->isLeaveDay($redemption)) {
            $this->creditLeave($redemption);
        }
    }

    // =====================================================
    // المكافأة النقدية ← الراتب
    // =====================================================

    /** قسيمةُ الراتب السارية للموظف — التي ستُولَّد منها صرفتُه التالية. */
    public function currentSlip(int $employeeId): ?SalarySlip
    {
        return SalarySlip::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [SalarySlip::STATUS_ACTIVE, SalarySlip::STATUS_APPROVED])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    /**
     * يكتب (أو يحدّث) سطرَ الاستحقاق الوحيد لهذا الطلب — على القسيمة المعطاة
     * أو قسيمة الموظف السارية. المفتاحُ `reward_redemption_id` فريدٌ في القاعدة.
     */
    public function queueForPayroll(RewardRedemption $redemption, ?SalarySlip $slip = null): SalaryItem
    {
        if ($redemption->payroll_run_id) {
            throw new \RuntimeException('المكافأة '.$redemption->redemption_number.' صُرفت في راتبٍ سابق — لا تُضاف مرّةً ثانية.');
        }

        $slip ??= $this->currentSlip((int) $redemption->employee_id);
        $rewardName = $redemption->reward->name ?? 'مكافأة';

        $payload = [
            'name' => 'مكافأة: '.$rewardName.' — '.$redemption->redemption_number,
            'type' => SalaryItem::TYPE_ADDITION,
            'status' => SalaryItem::STATUS_ACTIVE,
            'salary_item_value' => SalaryItem::VALUE_TYPE_AMOUNT,
            'amount' => round((float) $redemption->reward_value, 2),
            'salary_slips_id' => $slip?->id,
            'account_id' => $this->expenseAccountId(),
            'origin' => SalaryItem::ORIGIN_REWARD_REDEMPTION,
            'reward_redemption_id' => $redemption->id,
            // مبلغٌ لمرّةٍ واحدة: لا يدخل أجرَ التأمينات ولا أجرَ نهاية الخدمة
            'is_insurable' => false,
            'in_eos_wage' => false,
            'description' => 'مكافأة من كتالوج المكافآت (استبدال نقاط) — تُصرف مرّةً واحدة مع الراتب التالي',
        ];

        $item = SalaryItem::query()->where('reward_redemption_id', $redemption->id)->first();
        if ($item) {
            $item->update($payload);
        } else {
            $item = SalaryItem::create($payload);
        }

        $redemption->update([
            'metadata' => array_merge($redemption->metadata ?? [], [
                'fulfillment' => 'payroll',
                'payroll_status' => 'pending',
                'salary_item_id' => $item->id,
                'salary_slip_id' => $slip?->id,
            ]),
        ]);

        return $item;
    }

    /**
     * يُستدعى من `PayrollCalculator` قبل قراءة بنود القسيمة: كلُّ مكافأةٍ نقدية
     * معتمدة لم تُصرف تُلحق بقسيمة هذه الصرفة، وما أُلغي يُزال منها.
     */
    public function syncForRun(PayrollRun $run, SalarySlip $slip, Employee $employee): void
    {
        if (! $this->schemaReady()) {
            return;
        }

        // القفلُ يسلسل صرفتين متزامنتين للموظف نفسِه: الثانيةُ تجد الطلبَ مصروفاً
        $pending = RewardRedemption::query()
            ->where('employee_id', $employee->id)
            ->where('user_type', 'employee')
            ->whereIn('status', [RedemptionStatus::APPROVED->value, RedemptionStatus::PROCESSING->value])
            ->whereNull('payroll_run_id')
            ->whereHas('reward', fn ($q) => $q->where('reward_type', RewardType::CASH->value))
            ->lockForUpdate()
            ->get()
            ->reject(fn (RewardRedemption $r) => $this->paidFromTreasuryLegacy($r));

        foreach ($pending as $redemption) {
            $this->queueForPayroll($redemption, $slip);
        }

        SalaryItem::query()
            ->where('salary_slips_id', $slip->id)
            ->where('origin', SalaryItem::ORIGIN_REWARD_REDEMPTION)
            ->when($pending->isNotEmpty(), fn ($q) => $q->whereNotIn('reward_redemption_id', $pending->pluck('id')->all()))
            ->delete();
    }

    /**
     * أسطرُ المكافآت بين استحقاقات الصرفة — لقيد الاستحقاق.
     *
     * @return array<int, array{account_id: int|null, amount: float, name: string}>
     */
    public function accrualLines(Collection $additions): array
    {
        return $additions
            ->filter(fn ($item) => ($item->origin ?? null) === SalaryItem::ORIGIN_REWARD_REDEMPTION)
            ->map(fn ($item) => [
                'account_id' => $item->account_id ? (int) $item->account_id : null,
                'amount' => round((float) $item->amount, 2),
                'name' => (string) $item->name,
            ])
            ->filter(fn ($line) => $line['amount'] > 0.009)
            ->values()
            ->all();
    }

    /**
     * بعد الصرف: الطلبُ «تم التسليم» ومربوطٌ بالصرفة، والسطرُ يُفصل عن القسيمة
     * (ويبقى صفُّه مرجعاً لسطر الصرفة) فلا يتكرّر في الصرفة التالية.
     */
    public function markPaid(PayrollRun $run, SalarySlip $slip): void
    {
        if (! $this->schemaReady()) {
            return;
        }

        $items = SalaryItem::query()
            ->where('salary_slips_id', $slip->id)
            ->where('origin', SalaryItem::ORIGIN_REWARD_REDEMPTION)
            ->whereNotNull('reward_redemption_id')
            ->get();

        foreach ($items as $item) {
            $redemption = RewardRedemption::query()->lockForUpdate()->find($item->reward_redemption_id);
            if (! $redemption) {
                continue;
            }
            if ($redemption->payroll_run_id && (int) $redemption->payroll_run_id !== (int) $run->id) {
                throw new \RuntimeException('المكافأة '.$redemption->redemption_number.' صُرفت في راتبٍ سابق — لا تُصرف مرّةً ثانية.');
            }

            $redemption->update([
                'payroll_run_id' => $run->id,
                'status' => RedemptionStatus::DELIVERED->value,
                'delivered_at' => now(),
                'metadata' => array_merge($redemption->metadata ?? [], [
                    'payroll_status' => 'paid',
                    'payroll_run_id' => $run->id,
                    'payroll_run_code' => $run->code,
                    'salary_slip_id' => $slip->id,
                ]),
            ]);

            $item->update(['salary_slips_id' => null]);

            OperationLog::record(
                'deliver',
                'reward_redemption',
                $redemption,
                'صُرفت المكافأة '.$redemption->redemption_number.' مع الراتب — صرفة '.($run->code ?? '#'.$run->id)
            );
        }
    }

    /** عكسُ صرفة: المكافآتُ التي دُفعت فيها تعود معلَّقةً على قسيمتها. */
    public function unpay(PayrollRun $run): void
    {
        if (! $this->schemaReady()) {
            return;
        }

        $slip = $run->slip;

        foreach (RewardRedemption::query()->where('payroll_run_id', $run->id)->lockForUpdate()->get() as $redemption) {
            $redemption->update([
                'payroll_run_id' => null,
                'status' => RedemptionStatus::APPROVED->value,
                'delivered_at' => null,
                'metadata' => array_merge($redemption->metadata ?? [], [
                    'payroll_status' => 'pending',
                    'payroll_run_id' => null,
                    'payroll_reversed_run_id' => $run->id,
                ]),
            ]);

            $this->queueForPayroll($redemption, $slip);
        }
    }

    /** إلغاءُ طلبٍ لم يُصرف: يُزال سطرُه من القسيمة. */
    public function withdraw(RewardRedemption $redemption): void
    {
        if (! $this->schemaReady()) {
            return;
        }

        if ($redemption->payroll_run_id) {
            throw new \RuntimeException('المكافأة صُرفت مع الراتب — اعكس صرفة الراتب أولاً.');
        }

        SalaryItem::query()->where('reward_redemption_id', $redemption->id)->delete();
    }

    // =====================================================
    // مكافأة «إجازة» ← رصيد الإجازات
    // =====================================================

    /**
     * يضيف الأيام إلى «الرصيد الإضافي» لنوع الإجازة في السنة الجارية، ويُسجَّل
     * ذلك في ملاحظات الرصيد وسجلّ النشاط. الإضافةُ هي التسليم: فالطلبُ «تم التسليم».
     */
    public function creditLeave(RewardRedemption $redemption): EmployeeLeaveBalance
    {
        $meta = $redemption->metadata ?? [];
        if (! empty($meta['leave_balance_id'])) {
            throw new \RuntimeException('أُضيفت أيام هذه المكافأة إلى رصيد الإجازات سابقاً.');
        }

        $reward = $redemption->reward;
        $days = (int) round((float) $redemption->reward_value);
        $year = (int) now()->year;

        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $redemption->employee_id)
            ->where('leave_type_id', $reward->leave_type_id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = EmployeeLeaveBalance::create([
                'employee_id' => $redemption->employee_id,
                'leave_type_id' => $reward->leave_type_id,
                'year' => $year,
                'initial_balance' => 0,
                'used_balance' => 0,
                'remaining_balance' => 0,
                'carried_forward' => 0,
                'additional_balance' => 0,
            ]);
        }

        $before = (int) $balance->additional_balance;
        $balance->additional_balance = $before + $days;
        $balance->remaining_balance = $balance->getActualRemainingBalance();
        $balance->notes = ($balance->notes ? $balance->notes."\n" : '').
            now()->format('Y-m-d H:i').': إضافة '.$days.' يوم — مكافأة «'.$reward->name.'» '.$redemption->redemption_number;
        $balance->save();

        $redemption->update([
            'status' => RedemptionStatus::DELIVERED->value,
            'delivered_at' => now(),
            'metadata' => array_merge($meta, [
                'fulfillment' => 'leave_balance',
                'leave_balance_id' => $balance->id,
                'leave_type_id' => (int) $reward->leave_type_id,
                'leave_days' => $days,
                'leave_year' => $year,
            ]),
        ]);

        OperationLog::record(
            'update',
            'employee_leave_balance',
            $balance,
            'إضافة '.$days.' يوم إلى رصيد الإجازات (سنة '.$year.') — مكافأة '.$redemption->redemption_number,
            ['old' => ['additional_balance' => $before], 'new' => ['additional_balance' => $balance->additional_balance]]
        );

        return $balance;
    }

    // =====================================================

    private function userTypeOf(RewardRedemption $redemption): ?string
    {
        $type = $redemption->user_type;

        return $type instanceof \BackedEnum ? $type->value : ($type ? (string) $type : null);
    }

    private function rewardTypeOf(RewardRedemption $redemption): ?string
    {
        $type = $redemption->reward?->reward_type;

        return $type instanceof \BackedEnum ? $type->value : ($type ? (string) $type : null);
    }

    private function schemaReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasColumn('salary_items', 'reward_redemption_id')
            && Schema::hasColumn('reward_redemptions', 'payroll_run_id');
    }
}
