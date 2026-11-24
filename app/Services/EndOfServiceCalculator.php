<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * احتساب مكافأة نهاية الخدمة — قواعد المدة والسبب بلا أثر جانبي.
 *
 * الافتراض سعودي شائع: نصف شهر عن كل سنة من الخمس الأولى، وشهر بعدها.
 * الاستقالة تُخفَّض حسب الشرائح؛ إنهاء المنشأة يُدفع كاملاً.
 */
class EndOfServiceCalculator
{
    /** @return list<array{max_years: float|null, fraction: float}> */
    public static function defaultResignationBands(): array
    {
        return [
            ['max_years' => 2.0, 'fraction' => 0.0],
            ['max_years' => 5.0, 'fraction' => 1 / 3],
            ['max_years' => 10.0, 'fraction' => 2 / 3],
            ['max_years' => null, 'fraction' => 1.0],
        ];
    }

    public function yearsOfService($hireDate, $endDate): float
    {
        if (! $hireDate || ! $endDate) {
            return 0.0;
        }

        $start = Carbon::parse($hireDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        if ($end->lt($start)) {
            return 0.0;
        }

        return round($start->diffInDays($end) / 365, 4);
    }

    /**
     * @param  array{first_bracket_years: float, first_months_per_year: float, later_months_per_year: float}  $rules
     */
    public function fullAmount(float $years, float $monthlyWage, array $rules): float
    {
        if ($years <= 0 || $monthlyWage <= 0) {
            return 0.0;
        }

        $firstYears = min($years, (float) $rules['first_bracket_years']);
        $laterYears = max(0.0, $years - (float) $rules['first_bracket_years']);
        $months = ($firstYears * (float) $rules['first_months_per_year'])
            + ($laterYears * (float) $rules['later_months_per_year']);

        return round($months * $monthlyWage, 2);
    }

    /**
     * @param  list<array{max_years: float|null, fraction: float}>  $bands
     */
    public function payableFraction(float $years, bool $resigned, array $bands): float
    {
        if (! $resigned) {
            return 1.0;
        }

        foreach ($bands as $band) {
            $max = $band['max_years'];
            if ($max === null || $years < (float) $max) {
                return (float) $band['fraction'];
            }
        }

        return 1.0;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{years: float, full_amount: float, fraction: float, payable_amount: float}
     */
    public function breakdown(float $years, float $monthlyWage, bool $resigned, array $rules, array $bands): array
    {
        $full = $this->fullAmount($years, $monthlyWage, $rules);
        $fraction = $this->payableFraction($years, $resigned, $bands);

        return [
            'years' => $years,
            'full_amount' => $full,
            'fraction' => $fraction,
            'payable_amount' => round($full * $fraction, 2),
        ];
    }
}
