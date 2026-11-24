<?php

namespace App\Services;

use App\Models\AttendanceDays;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRule;
use App\Models\PayrollRun;
use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Support\PayrollSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * تحويل `attendance_days.overtime_minutes` إلى بند استحقاق على القسيمة.
 *
 * الحضور يحسب الدقائق ولا يمسّ الراتب. الصرف يلتقط البند عبر
 * `SalarySlip::additionItems()` بعد مزامنة هذه الخدمة.
 */
class OvertimePayService
{
    /**
     * يكتب (أو يحذف) بند الأجر الإضافي المولَّد على القسيمة لفترة الـ run.
     */
    public function syncForRun(PayrollRun $run, SalarySlip $slip, Employee $employee): ?SalaryItem
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $breakdown = $this->breakdown(
            $employee,
            $run->period_from,
            $run->period_to,
            (float) ($slip->basic_amount ?? $run->basic_amount ?? 0)
        );

        return $this->persist($slip, $breakdown);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     weekday_minutes: float,
     *     holiday_minutes: float,
     *     amount: float,
     *     weekday_amount: float,
     *     holiday_amount: float,
     *     hourly_rate: float,
     *     name: string,
     *     component_type_id: int|null
     * }
     */
    public function breakdown(Employee $employee, $periodFrom, $periodTo, float $basicAmount): array
    {
        $empty = [
            'enabled' => false,
            'weekday_minutes' => 0.0,
            'holiday_minutes' => 0.0,
            'amount' => 0.0,
            'weekday_amount' => 0.0,
            'holiday_amount' => 0.0,
            'hourly_rate' => 0.0,
            'name' => 'أجر إضافي',
            'component_type_id' => null,
        ];

        if (! $this->tablesReady()) {
            return $empty;
        }

        $settings = $this->settings();
        $empty['component_type_id'] = $settings['component_type_id'];
        $empty['name'] = $this->itemName($settings['component_type_id']);

        if (! $settings['enabled']) {
            return $empty;
        }

        $rules = OvertimeRule::query()->active()->get()->keyBy('applies_on');
        $weekdayRule = $rules->get(OvertimeRule::APPLIES_WEEKDAY);
        $holidayRule = $rules->get(OvertimeRule::APPLIES_HOLIDAY);

        if (! $weekdayRule && ! $holidayRule) {
            return $empty;
        }

        $days = $this->overtimeDays($employee->id, $periodFrom, $periodTo);

        /*
            الاعتمادُ المسبق: الدقائقُ محسوبةٌ ومسجَّلةٌ على أي حال — تلك
            واقعة. لكنّ تحوّلها إلى أجرٍ مشروطٌ بقرارٍ بشريّ سابق حين
            يُفعَّل المفتاح. فتُجلَب السقوفُ المعتمَدة لأيام الفترة دفعةً
            واحدة، لا استعلامٌ لكل يوم.
        */
        $approvedCaps = $settings['requires_approval']
            ? $this->approvedCaps($employee->id, $periodFrom, $periodTo)
            : null;

        $weekdayMinutes = 0.0;
        $holidayMinutes = 0.0;

        foreach ($days as $day) {
            $minutes = max(0, (int) $day->overtime_minutes);
            if ($minutes === 0) {
                continue;
            }

            if ($approvedCaps !== null) {
                $dateKey = $this->dateKey($day->attendance_date);
                $cap = $approvedCaps[$dateKey] ?? 0;

                /*
                    السقفُ لا المِنحة: من اعتُمدت له أربعُ ساعاتٍ وعمل
                    ساعتين يُصرف له ساعتان. والاعتمادُ يحدّ ولا يُنشئ.
                */
                $minutes = min($minutes, $cap);

                if ($minutes === 0) {
                    continue;
                }
            }

            $isHoliday = (bool) ($day->is_holiday ?? false) || (bool) ($day->is_weekly_off ?? false);
            $rule = $isHoliday ? ($holidayRule ?? $weekdayRule) : ($weekdayRule ?? $holidayRule);
            if (! $rule) {
                continue;
            }

            if ($rule->max_minutes_per_day) {
                $minutes = min($minutes, (int) $rule->max_minutes_per_day);
            }

            if ($isHoliday) {
                $holidayMinutes += $minutes;
            } else {
                $weekdayMinutes += $minutes;
            }
        }

        $cap = $settings['max_minutes_per_period'];
        $totalMinutes = $weekdayMinutes + $holidayMinutes;
        if ($cap && $totalMinutes > $cap && $totalMinutes > 0) {
            $scale = $cap / $totalMinutes;
            $weekdayMinutes *= $scale;
            $holidayMinutes *= $scale;
        }

        $hourly = $this->hourlyRate($weekdayRule, $holidayRule, $settings, $basicAmount);
        $weekdayMultiplier = (float) ($weekdayRule->multiplier ?? 1.5);
        $holidayMultiplier = (float) ($holidayRule->multiplier ?? 2.0);

        $weekdayAmount = round(($weekdayMinutes / 60) * $hourly * $weekdayMultiplier, 2);
        $holidayAmount = round(($holidayMinutes / 60) * $hourly * $holidayMultiplier, 2);

        return [
            'enabled' => true,
            'weekday_minutes' => $weekdayMinutes,
            'holiday_minutes' => $holidayMinutes,
            'amount' => round($weekdayAmount + $holidayAmount, 2),
            'weekday_amount' => $weekdayAmount,
            'holiday_amount' => $holidayAmount,
            'hourly_rate' => $hourly,
            'name' => $empty['name'],
            'component_type_id' => $settings['component_type_id'],
        ];
    }

    /** @param  array<string, mixed>  $breakdown */
    private function persist(SalarySlip $slip, array $breakdown): ?SalaryItem
    {
        $existing = SalaryItem::query()
            ->where('salary_slips_id', $slip->id)
            ->where('origin', SalaryItem::ORIGIN_OVERTIME)
            ->first();

        if (! $breakdown['enabled'] || $breakdown['amount'] < 0.01) {
            $existing?->delete();

            return null;
        }

        $payload = [
            'name' => $breakdown['name'],
            'type' => SalaryItem::TYPE_ADDITION,
            'status' => SalaryItem::STATUS_ACTIVE,
            'salary_item_value' => SalaryItem::VALUE_TYPE_AMOUNT,
            'amount' => $breakdown['amount'],
            'salary_slips_id' => $slip->id,
            'component_type_id' => $breakdown['component_type_id'],
            'origin' => SalaryItem::ORIGIN_OVERTIME,
            'description' => sprintf(
                'إضافي %.0f د عمل + %.0f د عطلة',
                $breakdown['weekday_minutes'],
                $breakdown['holiday_minutes']
            ),
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return SalaryItem::create($payload);
    }

    /**
     * سقوفُ الدقائق المعتمَدة في الفترة، مفهرسةً بتاريخ اليوم.
     *
     * طلبٌ واحدٌ معتمَدٌ لكل يومٍ هو القاعدة، وتعدُّدُها — إن وقع بسباقٍ
     * على الإنشاء — يُجمَع لا يُؤخَذ آخرُه: الموظفُ اعتُمد له المجموع.
     *
     * @return array<string, int>
     */
    private function approvedCaps(int $employeeId, $from, $to): array
    {
        if (! Schema::hasTable('overtime_requests')) {
            // الجدولُ غير مُرحَّل: لا سقوف، فلا يُصرف شيء — وهو الأحوط
            // حين يُفعَّل المفتاح على قاعدةٍ ناقصة.
            return [];
        }

        $query = OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->approved();

        if ($from) {
            $query->whereDate('overtime_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('overtime_date', '<=', $to);
        }

        $caps = [];

        foreach ($query->get() as $request) {
            $key = $this->dateKey($request->overtime_date);
            $caps[$key] = ($caps[$key] ?? 0) + $request->effectiveMinutes();
        }

        return $caps;
    }

    /** مفتاحُ يومٍ موحَّد — العمودُ قد يعود نصاً أو كائنَ Carbon. */
    private function dateKey($date): string
    {
        return $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : substr((string) $date, 0, 10);
    }

    private function overtimeDays(int $employeeId, $from, $to): Collection
    {
        $query = AttendanceDays::query()
            ->where('employee_id', $employeeId)
            ->where('overtime_minutes', '>', 0);

        if ($from) {
            $query->whereDate('attendance_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('attendance_date', '<=', $to);
        }

        return $query->get();
    }

    private function hourlyRate(?OvertimeRule $weekday, ?OvertimeRule $holiday, array $settings, float $basic): float
    {
        foreach ([$weekday?->hourly_rate, $holiday?->hourly_rate, $settings['hourly_rate']] as $rate) {
            if ($rate !== null && (float) $rate > 0) {
                return round((float) $rate, 2);
            }
        }

        $hours = $settings['monthly_hours'] > 0 ? $settings['monthly_hours'] : 240;

        return $hours > 0 ? round($basic / $hours, 2) : 0.0;
    }

    /** @return array{enabled: bool, monthly_hours: float, hourly_rate: float|null, max_minutes_per_period: int|null, component_type_id: int|null} */
    private function settings(): array
    {
        $row = PayrollSettings::row();

        $get = function (string $column, mixed $default = null) use ($row) {
            if (! $row || ! Schema::hasColumn('payroll_settings', $column)) {
                return $default;
            }

            $value = $row->{$column} ?? $default;

            return $value === '' ? $default : $value;
        };

        $typeId = $get('overtime_component_type_id');
        if (! $typeId && Schema::hasTable('salary_component_types')) {
            $typeId = SalaryComponentType::query()
                ->where('code', SalaryComponentType::CODE_OVERTIME)
                ->value('id');
        }

        $hourly = $get('overtime_hourly_rate');

        return [
            'enabled' => (bool) $get('overtime_enabled', false),
            'requires_approval' => (bool) $get('overtime_requires_approval', false),
            'monthly_hours' => (float) $get('overtime_monthly_hours', 240),
            'hourly_rate' => $hourly !== null && (float) $hourly > 0 ? (float) $hourly : null,
            'max_minutes_per_period' => $get('overtime_max_minutes_per_period')
                ? (int) $get('overtime_max_minutes_per_period')
                : null,
            'component_type_id' => $typeId ? (int) $typeId : null,
        ];
    }

    private function itemName(?int $componentTypeId): string
    {
        if ($componentTypeId) {
            $name = SalaryComponentType::query()->whereKey($componentTypeId)->value('name');
            if ($name) {
                return $name;
            }
        }

        return 'أجر إضافي';
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('overtime_rules')
            && Schema::hasTable('attendance_days')
            && Schema::hasTable('salary_items')
            && Schema::hasColumn('salary_items', 'origin');
    }
}
