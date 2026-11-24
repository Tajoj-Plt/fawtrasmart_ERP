<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalarySlip;

/**
 * أرقام الصرفة: أساسي + بدلات + رجعي − خصميات − سلف = صافٍ.
 *
 * لا سند ولا قيد ولا إشعار — تلك في `PayrollAccountant` بعد الحساب.
 */
class PayrollCalculator
{
    public function __construct(private PayrollSettlement $settlement) {}

    public function calculate(PayrollRun $run, SalarySlip $slip, Employee $employee): PayrollCalculation
    {
        $basic = (float) ($slip->basic_amount ?? 0);

        app(OvertimePayService::class)->syncForRun($run, $slip, $employee);
        // المكافآتُ النقدية المعتمدة من «كتالوج المكافآت» — استحقاقٌ لمرّةٍ واحدة، لا بدل
        app(Rewards\EmployeeRewardFulfillment::class)->syncForRun($run, $slip, $employee);
        $statutory = app(SocialInsuranceService::class)->syncForRun($run, $slip, $employee);

        $rawAdditions = $slip->additionItems()->get();
        $additions = $rawAdditions
            ->groupBy(function ($item) {
                return $item->account_id.'|'.$item->name;
            })
            ->map(function ($group) {
                $first = $group->first();
                if ($group->pluck('amount')->unique()->count() === 1) {
                    return $first;
                }
                $first->amount = $group->sum('amount');

                return $first;
            })
            ->values();

        $totalAdditions = (float) $additions->sum('amount');
        $totalRetro = (float) $run->items()
            ->where('item_type', PayrollRunItem::TYPE_RETRO)
            ->sum('amount');

        $totalEarnings = $basic + $totalAdditions + $totalRetro;

        $gosiEmployee = round(min((float) ($statutory['employee_amount'] ?? 0), $totalEarnings), 2);
        $gosiEmployer = round((float) ($statutory['employer_amount'] ?? 0), 2);
        $gosiItem = $statutory['item'] ?? null;
        $gosiEmployeeAccountId = $statutory['employee_payable_account_id'] ?? null;

        /*
            **بنودُ الخصم اليدوية على القسيمة.**

            شاشةُ القسيمة تحسب صافيها «الإجمالي − بنود الخصم»، والصرفُ كان
            يتجاهل تلك البنود: فقسيمةٌ أساسيُّها ٣٠٠٠ وعليها خصمٌ يدويٌّ ٢٠٠
            تعرض ٢٨٠٠ وتُصرف ٣٠٠٠. فصارت تُقتطع هنا — بعد حصة التأمينات
            وقبل خصميات جدول `deductions` — وتُقيَّد في قيد الصرف دائنةً على
            حساب البند (`PayrollAccountant`).

            والمستثنى: بندُ التأمينات المولَّد (`origin = social_insurance`) —
            له مساره أعلاه — وكلُّ بندٍ مولَّدٍ آخر. ولا تمسّ خصمياتِ جدول
            `deductions` (تلك تُقيَّد عند اعتمادها في `DeductionAccountingService`).
        */
        $slipDeductions = [];
        $totalSlipDeductions = 0.0;
        $room = max(0, $totalEarnings - $gosiEmployee);
        foreach ($slip->deductionItems()->whereNull('origin')->orderBy('id')->get() as $item) {
            $amount = round(min((float) $item->amount, $room), 2);
            if ($amount <= 0.009) {
                continue;
            }
            $slipDeductions[] = [
                'item' => $item,
                'amount' => $amount,
                'account_id' => $item->account_id ? (int) $item->account_id : null,
            ];
            $totalSlipDeductions += $amount;
            $room -= $amount;
        }
        $totalSlipDeductions = round($totalSlipDeductions, 2);

        $rawDeductions = $employee->unsettledDeductions()->orderBy('id')->get();
        $deductionPlan = $this->settlement->planDeductions($rawDeductions, $totalEarnings - $gosiEmployee - $totalSlipDeductions);
        $otherDeductions = $deductionPlan['total'];
        $totalDeductions = $otherDeductions + $gosiEmployee + $totalSlipDeductions;

        $remainingAfterDeductions = $totalEarnings - $totalDeductions;
        $rawAdvances = $this->settlement->unsettledAdvances($employee, $run->period_to);
        $advancePlan = $this->settlement->planAdvances($rawAdvances, $remainingAfterDeductions);
        $advancesToSettle = $advancePlan['items'];
        $totalAdvances = $advancePlan['total'];

        $netSalary = max(0, $totalEarnings - $totalDeductions - $totalAdvances);

        return new PayrollCalculation(
            basic: $basic,
            additions: $additions,
            totalAdditions: $totalAdditions,
            totalRetro: $totalRetro,
            totalEarnings: $totalEarnings,
            statutory: $statutory,
            gosiEmployee: $gosiEmployee,
            gosiEmployer: $gosiEmployer,
            gosiItem: $gosiItem,
            gosiEmployeeAccountId: $gosiEmployeeAccountId ? (int) $gosiEmployeeAccountId : null,
            deductionPlan: $deductionPlan,
            otherDeductions: (float) $otherDeductions,
            totalDeductions: $totalDeductions,
            advancePlan: $advancePlan,
            advancesToSettle: $advancesToSettle,
            totalAdvances: (float) $totalAdvances,
            netSalary: $netSalary,
            slipDeductions: $slipDeductions,
            totalSlipDeductions: $totalSlipDeductions,
        );
    }

    public function writeEarningItems(PayrollRun $run, SalarySlip $slip, PayrollCalculation $calc): void
    {
        // قسيمةُ موظفٍ بلا عقدٍ قد تقوم على بنود الاستحقاق وحدها — فلا سطرَ
        // «راتب أساسي» بصفرٍ في كشف الصرفة.
        if ($calc->basic > 0.009) {
            PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_ADDITION,
                'source_table' => null,
                'source_id' => null,
                'name' => 'الراتب الأساسي',
                'amount' => $calc->basic,
                'description' => "راتب أساسي من قسيمة #{$slip->id}",
            ]);
        }
        foreach ($calc->additions as $item) {
            PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_ADDITION,
                'source_table' => 'salary_items',
                'source_id' => $item->id,
                'name' => $item->name,
                'amount' => (float) $item->amount,
            ]);
        }

        if ($calc->gosiItem && $calc->gosiEmployee > 0.009) {
            PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_DEDUCTION,
                'source_table' => 'salary_items',
                'source_id' => $calc->gosiItem->id,
                'name' => $calc->gosiItem->name,
                'amount' => $calc->gosiEmployee,
                'description' => $calc->gosiItem->description,
            ]);
        }
        foreach ($calc->slipDeductions as $entry) {
            PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_DEDUCTION,
                'source_table' => 'salary_items',
                'source_id' => $entry['item']->id,
                'name' => $entry['item']->name,
                'amount' => $entry['amount'],
                'description' => 'بند خصم على القسيمة #'.$slip->id,
            ]);
        }

        if ($calc->gosiEmployer > 0.009) {
            PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_EMPLOYER,
                'source_table' => 'social_insurance_settings',
                'source_id' => null,
                'name' => 'تأمينات - حصة المنشأة',
                'amount' => $calc->gosiEmployer,
                // سببُ الخضوع ونسبتُه (مواطن/مقيم…) — انظر SocialInsuranceService::explain()
                'description' => mb_substr(($calc->statutory['explanation'] ?? null)
                    ? 'لا تُنقص صافي الراتب — '.$calc->statutory['explanation']
                    : 'مساهمة المنشأة — لا تُنقص صافي الراتب', 0, 250),
            ]);
        }
    }
}
