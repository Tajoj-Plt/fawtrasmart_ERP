<?php

namespace App\Services;

use App\Mail\SalarySlipMail;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\User;
use App\Support\AccountingPeriodGuard;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * تنسيق صرف payroll_run: قفل الصف، الأهلية، ثم الحساب فالمحاسبة فالتسوية.
 *
 * الأرقام في `PayrollCalculator`، السند والقيود في `PayrollAccountant`،
 * السلف والخصميات في `PayrollSettlement`. الكرون والمتحكّم يناديان هنا.
 *
 * **لا تستخدم العمولات إطلاقاً** — العمولات نظام مستقل (WeeklyCommission).
 */
class PayrollDisbursementService
{
    public function __construct(
        private PayrollCalculator $calculator,
        private PayrollAccountant $accountant,
        private PayrollSettlement $settlement,
    ) {}

    /**
     * توليد payroll_run جديد لقسيمة (إن لم يكن موجوداً بالفعل لنفس الفترة).
     *
     * @return PayrollRun|null null إذا لا يمكن التوليد
     */
    public function generateNextRun(SalarySlip $slip): ?PayrollRun
    {
        if ($slip->payroll_id) {
            $payroll = $slip->payroll;
            if (! $payroll || ! $payroll->isActive() || ! $payroll->isRunning()) {
                return null;
            }
        }

        $period = $slip->generateNextPeriod();
        if (! $period) {
            return null;
        }

        $exists = PayrollRun::where('salary_slip_id', $slip->id)
            ->where('employee_id', $slip->employee_id)
            ->where('period_from', $period['period_from']->toDateString())
            ->where('period_to', $period['period_to']->toDateString())
            ->exists();
        if ($exists) {
            return null;
        }

        return PayrollRun::create([
            'code' => PayrollRun::issueDocumentCode(),
            'salary_slip_id' => $slip->id,
            'employee_id' => $slip->employee_id,
            'contract_id' => $slip->contract_id,
            'period_from' => $period['period_from']->toDateString(),
            'period_to' => $period['period_to']->toDateString(),
            'scheduled_date' => $period['scheduled_date']->toDateString(),
            'status' => PayrollRun::STATUS_PENDING,
            'trigger_source' => PayrollRun::TRIGGER_CRON,
            'basic_amount' => $slip->basic_amount ?? 0,
        ]);
    }

    /**
     * تنفيذ صرف فعلي لـ payroll_run.
     *
     * الاعتمادُ المطلوب واحد: اعتمادُ مسير الرواتب الذي تنتمي إليه القسيمة
     * (`PayrollRun::disbursementApprovalBlockReason()`) — لا «مسير صرفات».
     *
     * @param  bool  $early  «صرفٌ مبكر» مؤكَّدٌ صراحةً قبل يوم الصرف. الكرونُ
     *                       والصرفُ الجماعيّ لا يمرّران `true` أبداً.
     *
     * @throws \RuntimeException
     */
    public function disburse(PayrollRun $run, string $trigger = PayrollRun::TRIGGER_MANUAL, bool $early = false): PayrollRun
    {
        $early = $early && $trigger === PayrollRun::TRIGGER_MANUAL;

        if (! $run->canBeDisbursed($early)) {
            throw new \RuntimeException(
                'لا يمكن صرف هذه القسيمة الآن. '.($run->disbursementBlockReason($early) ?? $run->status_label)
            );
        }

        $postingDate = now();

        if ($block = AccountingPeriodGuard::blockReason($postingDate)) {
            throw new \RuntimeException($block);
        }

        return DB::transaction(function () use ($run, $trigger, $postingDate) {
            /*
                طلبان متزامنان (يدوي + كرون، أو نقر مزدوج) يمرّان معاً على
                canBeDisbursed قبل المعاملة فينشئان سندين وقيدين لنفس الصرفة.
                القفل هنا يسلسلهما: الثاني ينتظر ثم يقرأ الحالة بعد صرف الأوّل.
                ويُعاد فحص الحالة لا أهلية الموظف — تلك تُلغي الصرفة لاحقاً.
            */
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! $run->isPending() || $run->isHeld()) {
                throw new \RuntimeException(
                    'لا يمكن صرف هذه القسيمة الآن. '.($run->disbursementBlockReason() ?? $run->status_label)
                );
            }

            // اعتمادُ مسير الرواتب يُعاد تحت القفل — لا «مسير صرفات»: الدفعةُ القديمة لا تُسأل
            if ($approvalBlock = $run->disbursementApprovalBlockReason()) {
                throw new \RuntimeException('لا يمكن صرف هذه القسيمة الآن. '.$approvalBlock);
            }

            $slip = $run->slip;
            $employee = $run->employee;

            $this->assertEligible($run, $slip, $employee);

            $calc = $this->calculator->calculate($run, $slip, $employee);
            $posted = $this->accountant->post($run, $slip, $employee, $calc, $postingDate);

            $this->calculator->writeEarningItems($run, $slip, $calc);
            // المكافآتُ المصروفة الآن تُربط بالصرفة وتُفصل عن القسيمة — فلا تتكرّر
            app(Rewards\EmployeeRewardFulfillment::class)->markPaid($run, $slip);
            $this->settlement->applyDeductions($calc, $run, $slip, $posted['settled_by']);
            $this->settlement->applyAdvances($calc, $run);

            $run->update([
                'status' => PayrollRun::STATUS_DISBURSED,
                'trigger_source' => $trigger,
                'disbursed_at' => now(),
                'disbursed_by' => $posted['settled_by'],
                // لا سندَ لصرفةٍ صافيها صفر — انظر PayrollAccountant::post()
                'expense_id' => $posted['expense']?->id,
                'journal_entry_id' => $posted['journal']->id,
                'accrual_journal_entry_id' => $posted['accrual']->id,
                'treasury_id' => $posted['treasury']->id,
                'basic_amount' => $calc->basic,
                'total_additions' => $calc->totalAdditions + $calc->totalRetro,
                'total_deductions' => $calc->totalDeductions,
                'total_advances_deducted' => $calc->totalAdvances,
                'net_salary' => $calc->netSalary,
            ]);

            if ($slip->status === SalarySlip::STATUS_APPROVED) {
                $slip->update(['status' => SalarySlip::STATUS_ACTIVE]);
            }

            $this->notifyEmployee($run, $employee, $calc->netSalary, $trigger);

            /*
                «إرسال قسيمة الراتب بالبريد» (`send_salary_slips` في الإعدادات
                العامة) — كان يُحفظ ولا يقرؤه شيء. يُرسل بعد إتمام المعاملة
                وحدها: صرفٌ تراجع لا يصل عنه بريد.
            */
            $runId = $run->id;
            DB::afterCommit(fn () => $this->emailPayslip($runId));

            $slip = $slip->fresh();
            if ($slip && ($reason = $slip->shouldExpire())) {
                $slip->update(['status' => SalarySlip::STATUS_EXPIRED, 'stop_reason' => $reason]);
            }

            return $run->fresh();
        });
    }

    /**
     * عكس صرف مصروف — قيد عكسي بلا حذف الأصلي.
     */
    public function reverse(PayrollRun $run, string $reason, ?int $reversedBy = null): PayrollRun
    {
        return app(PayrollReversalService::class)->reverse($run, $reason, $reversedBy);
    }

    private function assertEligible(PayrollRun $run, ?SalarySlip $slip, ?Employee $employee): void
    {
        if ($slip && $run->employee_id != $slip->employee_id) {
            throw new \RuntimeException(
                "خلل في عزل البيانات: سجل الصرف #{$run->id} (employee=#{$run->employee_id}) ".
                "لا يخص موظف القسيمة #{$slip->id} (employee=#{$slip->employee_id}). ".
                'هذا الـ run يتيم — يجب حذفه.'
            );
        }

        if (! $employee || ! $employee->isActive()) {
            $run->update([
                'status' => PayrollRun::STATUS_CANCELLED,
                'cancellation_reason' => 'الموظف غير نشط',
            ]);
            if ($slip && ! $slip->hasUndisbursedRuns()) {
                $slip->update(['status' => SalarySlip::STATUS_EXPIRED, 'stop_reason' => 'الموظف غير نشط']);
            }
            throw new \RuntimeException('الموظف غير نشط');
        }

        if ($slip->contract_id) {
            $contract = $slip->contract;
            if (! $contract) {
                $run->update([
                    'status' => PayrollRun::STATUS_CANCELLED,
                    'cancellation_reason' => 'العقد المرتبط بالقسيمة محذوف',
                ]);
                if (! $slip->hasUndisbursedRuns()) {
                    $slip->update(['status' => SalarySlip::STATUS_EXPIRED, 'stop_reason' => 'العقد محذوف']);
                }
                throw new \RuntimeException('العقد المرتبط بالقسيمة محذوف');
            }
            if ($contract->end_date && now()->gt($contract->end_date)) {
                $run->update([
                    'status' => PayrollRun::STATUS_CANCELLED,
                    'cancellation_reason' => 'انتهى العقد قبل تاريخ الصرف',
                ]);
                if (! $slip->hasUndisbursedRuns()) {
                    $slip->update(['status' => SalarySlip::STATUS_EXPIRED, 'stop_reason' => 'انتهى العقد']);
                }
                throw new \RuntimeException('انتهى العقد المرتبط بالموظف — لا يمكن الصرف');
            }
            if ($contract->end_date && $run->period_from > $contract->end_date) {
                $run->update([
                    'status' => PayrollRun::STATUS_CANCELLED,
                    'cancellation_reason' => 'فترة الـ run تبدأ بعد انتهاء العقد',
                ]);
                if (! $slip->hasUndisbursedRuns()) {
                    $slip->update(['status' => SalarySlip::STATUS_EXPIRED, 'stop_reason' => 'فترة خارج العقد']);
                }
                throw new \RuntimeException('فترة هذا الصرف خارج فترة العقد');
            }
        }

        if ($slip->end_date && $run->period_from > $slip->end_date) {
            $run->update([
                'status' => PayrollRun::STATUS_CANCELLED,
                'cancellation_reason' => 'فترة الـ run بعد نهاية القسيمة',
            ]);
            if (! $slip->hasUndisbursedRuns()) {
                $slip->update(['status' => SalarySlip::STATUS_EXPIRED, 'stop_reason' => 'انتهت فترة القسيمة']);
            }
            throw new \RuntimeException('فترة الصرف خارج فترة القسيمة');
        }

        if ($slip->payroll_id) {
            $payroll = $slip->payroll;
            if (! $payroll) {
                $run->update([
                    'status' => PayrollRun::STATUS_CANCELLED,
                    'cancellation_reason' => 'مسير الرواتب المرتبط محذوف',
                ]);
                throw new \RuntimeException('مسير الرواتب المرتبط بالقسيمة محذوف');
            }
            if (! $payroll->isActive()) {
                $run->update([
                    'status' => PayrollRun::STATUS_CANCELLED,
                    'cancellation_reason' => 'مسير الرواتب موقوف',
                ]);
                throw new \RuntimeException('مسير الرواتب "'.$payroll->name.'" موقوف — لا يمكن الصرف');
            }
            if (! $payroll->isRunningAt(now())) {
                $run->update([
                    'status' => PayrollRun::STATUS_CANCELLED,
                    'cancellation_reason' => 'مسير الرواتب خارج فترة التشغيل',
                ]);
                throw new \RuntimeException('مسير الرواتب "'.$payroll->name.'" خارج فترة تشغيله الآن');
            }
        }
    }

    /**
     * قسيمةُ الراتب إلى بريد الموظف بعد صرفها — متى فُعّل `send_salary_slips`.
     *
     * لا يُسقط الصرفَ أبداً: بريدٌ غير مضبوط أو موظفٌ بلا بريد يُسجَّل تحذيراً
     * ويمضي. البريدُ من `employees.email`، والإعدادُ من الإعدادات العامة للرواتب.
     *
     * @return bool أرُسلت الرسالة؟
     */
    public function emailPayslip(int $runId): bool
    {
        if (! (bool) PayrollSettings::value('send_salary_slips', false)) {
            return false;
        }

        try {
            $run = PayrollRun::query()->with('employee')->find($runId);
            $employee = $run?->employee;
            $email = trim((string) ($employee->email ?? ''));

            if (! $run || ! $run->isDisbursed() || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            Mail::to($email)->send(new SalarySlipMail([
                'name' => trim(($employee->first_name ?? '').' '.($employee->middle_name ?? '')) ?: ($employee->full_name ?? ''),
                'from' => optional($run->period_from)->toDateString(),
                'to' => optional($run->period_to)->toDateString(),
                'create' => optional($run->disbursed_at)->toDateString() ?: now()->toDateString(),
                'total_salary' => (float) $run->basic_amount + (float) $run->total_additions,
                'total_deductions' => (float) $run->total_deductions + (float) $run->total_advances_deducted,
                'net_salary' => (float) $run->net_salary,
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::warning('PayrollDisbursementService::emailPayslip failed', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function notifyEmployee(PayrollRun $run, Employee $employee, float $netSalary, string $trigger): void
    {
        try {
            $employeeUser = User::where('employee_id', $employee->id)->first();
            if (! $employeeUser) {
                return;
            }

            $automatedSuffix = $trigger === PayrollRun::TRIGGER_CRON ? ' (تلقائي)' : '';
            $employeeName = trim(($employee->first_name ?? '').' '.($employee->middle_name ?? ''));

            DB::table('notifications')->insert([
                'user_id' => Auth::id() ?: $employeeUser->id,
                'receiver_id' => $employeeUser->id,
                'title' => '💰 تم صرف راتبك',
                'description' => sprintf(
                    'تم صرف راتبك (%s) للفترة %s إلى %s — الصافي: %s.%s',
                    $employeeName,
                    $run->period_from->toDateString(),
                    $run->period_to->toDateString(),
                    // عملةُ النظام من شاشة «العملات» — لا «ر.س» نصّاً
                    \App\Support\CurrencySettings::format($netSalary),
                    $automatedSuffix
                ),
                'type' => 'salary_disbursement',
                'read' => 0,
                'is_sent' => 1,
                'is_scheduled' => 0,
                'popup_shown' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PayrollDisbursementService::notifyEmployee failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
