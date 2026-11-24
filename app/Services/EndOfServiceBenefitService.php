<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EndOfServiceBenefit;
use App\Models\SalaryComponentType;
use App\Models\SalarySlip;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\Schema;

/**
 * مزامنة مسودة مكافأة نهاية الخدمة من بيانات إنهاء الموظف (الجولة 1.2).
 */
class EndOfServiceBenefitService
{
    public function __construct(private EndOfServiceCalculator $calculator) {}

    public function syncDraftFor(Employee $employee, bool $respectEnabled = true): ?EndOfServiceBenefit
    {
        if (! $this->tablesReady() || ! $employee->hasEndedService()) {
            return null;
        }

        if ($respectEnabled && ! $this->settings()['enabled']) {
            return null;
        }

        $existing = EndOfServiceBenefit::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->first();

        if ($existing && ! $existing->isDraft()) {
            return $existing;
        }

        $payload = $this->payloadFor($employee);

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return EndOfServiceBenefit::create($payload);
    }

    /** @return array<string, mixed> */
    public function payloadFor(Employee $employee): array
    {
        $settings = $this->settings();
        $end = $employee->last_working_day ?? $employee->termination_date;
        $years = $this->calculator->yearsOfService($employee->hire_date, $end);
        $wage = $this->monthlyWage($employee, $settings['include_default_allowances']);
        $resigned = $employee->employmentStatus() === EmploymentStatus::RESIGNED;
        $calc = $this->calculator->breakdown(
            $years,
            $wage,
            $resigned,
            $settings['rules'],
            $settings['bands']
        );

        return [
            'employee_id' => $employee->id,
            'status' => EndOfServiceBenefit::STATUS_DRAFT,
            'employment_status' => (int) $employee->status,
            'hire_date' => $employee->hire_date,
            'last_working_day' => $end,
            'years_of_service' => $calc['years'],
            'monthly_wage' => $wage,
            'full_amount' => $calc['full_amount'],
            'fraction' => $calc['fraction'],
            'payable_amount' => $calc['payable_amount'],
            'component_type_id' => $settings['component_type_id'],
            'notes' => $employee->termination_reason,
        ];
    }

    public function monthlyWage(Employee $employee, bool $includeDefaultAllowances): float
    {
        $slip = null;
        if (Schema::hasTable('salary_slips')) {
            $query = $employee->salarySlips()->orderByDesc('id');
            $slip = (clone $query)->where('status', SalarySlip::STATUS_ACTIVE)->first()
                ?? $query->first();
        }

        $wage = (float) ($slip->basic_amount ?? 0);

        // علمُ «يدخل أجر نهاية الخدمة» على البند نفسِه منذ تقاعد كتالوج البدلات
        // — انظر `SalaryItem::scopeInEosWage()`.
        if ($includeDefaultAllowances && $slip && Schema::hasTable('salary_items')) {
            $wage += (float) $slip->additionItems()
                ->inEosWage()
                ->sum('amount');
        }

        if ($wage <= 0 && Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'amount')) {
            $wage = (float) ($employee->contracts()->orderByDesc('id')->value('amount') ?? 0);
        }

        return round($wage, 2);
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $row = PayrollSettings::row();

        $get = function (string $column, mixed $default = null) use ($row) {
            if (! $row || ! Schema::hasColumn('payroll_settings', $column)) {
                return $default;
            }
            $value = $row->{$column} ?? $default;

            return $value === '' ? $default : $value;
        };

        $bands = $get('eos_resignation_bands');
        if (is_string($bands)) {
            $bands = json_decode($bands, true);
        }
        if (! is_array($bands) || $bands === []) {
            $bands = EndOfServiceCalculator::defaultResignationBands();
        }

        $typeId = $get('eos_component_type_id');
        if (! $typeId && Schema::hasTable('salary_component_types')) {
            $typeId = SalaryComponentType::query()
                ->where('code', SalaryComponentType::CODE_END_OF_SERVICE)
                ->value('id');
        }

        return [
            'enabled' => (bool) $get('eos_enabled', true),
            'include_default_allowances' => (bool) $get('eos_include_default_allowances', true),
            // من «إعدادات الحسابات المحاسبية» وحدها (`eos_expense`/`eos_provision`) —
            // عمودا `payroll_settings.eos_*_account_id` متقاعدان لا يُقرآن. ولا
            // تخمينَ برمز حساب: غيرُ المربوط يُرفض عند الاعتماد برسالةٍ تسمّيه.
            'expense_account_id' => \App\Support\PayrollAccounts::id(\App\Support\PayrollAccounts::EOS_EXPENSE),
            'provision_account_id' => \App\Support\PayrollAccounts::id(\App\Support\PayrollAccounts::EOS_PROVISION),
            'component_type_id' => $typeId ? (int) $typeId : null,
            'rules' => [
                'first_bracket_years' => (float) $get('eos_first_bracket_years', 5),
                'first_months_per_year' => (float) $get('eos_first_months_per_year', 0.5),
                'later_months_per_year' => (float) $get('eos_later_months_per_year', 1),
            ],
            'bands' => $bands,
        ];
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('end_of_service_benefits');
    }
}
