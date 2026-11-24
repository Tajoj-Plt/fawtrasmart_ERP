<?php

namespace App\Services;

use App\Models\Deduction;
use App\Models\SalaryItem;
use Illuminate\Support\Collection;

/**
 * نتيجة حساب صرفة — أرقام وخطط تسوية بلا سند ولا قيد ولا إشعار.
 */
class PayrollCalculation
{
    /**
     * @param  Collection<int, SalaryItem>  $additions
     * @param  array{items: array<int, array{deduction: Deduction, apply: float}>, total: float}  $deductionPlan
     * @param  array{items: array<int, array>, total: float}  $advancePlan
     */
    public function __construct(
        public float $basic,
        public Collection $additions,
        public float $totalAdditions,
        public float $totalRetro,
        public float $totalEarnings,
        public array $statutory,
        public float $gosiEmployee,
        public float $gosiEmployer,
        public mixed $gosiItem,
        public ?int $gosiEmployeeAccountId,
        public array $deductionPlan,
        public float $otherDeductions,
        public float $totalDeductions,
        public array $advancePlan,
        public array $advancesToSettle,
        public float $totalAdvances,
        public float $netSalary,
        /**
         * بنودُ الخصم اليدوية على القسيمة (نوع «خصم» بلا `origin`) — جزءٌ من
         * صافي القسيمة كما تعرضه شاشتُها، تُقتطع عند الصرف وتُقيَّد دائنةً على
         * حساب البند في قيد الصرف. غيرُ خصميات جدول `deductions` وغيرُ التأمينات.
         *
         * @var array<int, array{item: SalaryItem, amount: float, account_id: int|null}>
         */
        public array $slipDeductions = [],
        public float $totalSlipDeductions = 0.0,
    ) {}
}
