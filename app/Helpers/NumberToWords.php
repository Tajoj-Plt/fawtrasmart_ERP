<?php

namespace App\Helpers;

class NumberToWords
{
    private static $ones = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    ];

    private static $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
    ];

    public static function convert($number)
    {
        if ($number == 0) {
            return 'Zero';
        }

        $whole = floor($number);
        $fraction = round(($number - $whole) * 100);

        $words = self::convertWhole($whole);

        if ($fraction > 0) {
            $words .= ' and ' . $fraction . '/100';
        }

        return $words;
    }

    private static function convertWhole($number)
    {
        if ($number < 0) {
            return 'Negative ' . self::convertWhole(abs($number));
        }

        if ($number < 20) {
            return self::$ones[$number];
        }

        if ($number < 100) {
            return self::$tens[floor($number/10)] . ($number % 10 ? ' ' . self::$ones[$number % 10] : '');
        }

        if ($number < 1000) {
            return self::$ones[floor($number/100)] . ' Hundred' . ($number % 100 ? ' and ' . self::convertWhole($number % 100) : '');
        }

        if ($number < 1000000) {
            return self::convertWhole(floor($number/1000)) . ' Thousand' . ($number % 1000 ? ' ' . self::convertWhole($number % 1000) : '');
        }

        if ($number < 1000000000) {
            return self::convertWhole(floor($number/1000000)) . ' Million' . ($number % 1000000 ? ' ' . self::convertWhole($number % 1000000) : '');
        }

        return self::convertWhole(floor($number/1000000000)) . ' Billion' . ($number % 1000000000 ? ' ' . self::convertWhole($number % 1000000000) : '');
    }

    /**
     * التفقيط بالعربية: «فقط خمسة آلاف وتسعمائة ريال سعودي وخمسون هللة لا غير».
     *
     * لمطبوعات الموارد البشرية (قسيمة الراتب، مسير الرواتب، العقد، السندات).
     * العملةُ برمزها (SAR، EGP…)، وما لا يُعرف يُكتب رمزُه كما هو.
     */
    public static function arabic($amount, string $currency = 'SAR', bool $wrap = true): string
    {
        $amount = round(abs((float) $amount), 2);
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);

        [$major, $minor] = self::arabicCurrency($currency);

        $text = ($whole === 0 ? 'صفر' : self::arabicWhole($whole)).' '.$major;

        if ($fraction > 0) {
            $text .= ' و'.self::arabicWhole($fraction).' '.$minor;
        }

        return $wrap ? 'فقط '.$text.' لا غير' : $text;
    }

    /** @return array{0:string,1:string} */
    private static function arabicCurrency(string $currency): array
    {
        return match (strtoupper(trim($currency))) {
            'SAR', 'ر.س', '' => ['ريال سعودي', 'هللة'],
            'EGP' => ['جنيه مصري', 'قرش'],
            'AED' => ['درهم إماراتي', 'فلس'],
            'KWD' => ['دينار كويتي', 'فلس'],
            'BHD' => ['دينار بحريني', 'فلس'],
            'OMR' => ['ريال عماني', 'بيسة'],
            'QAR' => ['ريال قطري', 'درهم'],
            'JOD' => ['دينار أردني', 'قرش'],
            'YER' => ['ريال يمني', 'فلس'],
            'USD' => ['دولار أمريكي', 'سنت'],
            'EUR' => ['يورو', 'سنت'],
            default => [$currency, ''],
        };
    }

    private static function arabicWhole(int $number): string
    {
        $scales = [
            1000000000 => ['مليار', 'ملياران', 'مليارات'],
            1000000 => ['مليون', 'مليونان', 'ملايين'],
            1000 => ['ألف', 'ألفان', 'آلاف'],
        ];

        $parts = [];

        foreach ($scales as $size => [$one, $two, $few]) {
            $count = intdiv($number, $size);
            $number %= $size;

            if ($count === 0) {
                continue;
            }

            $parts[] = match (true) {
                $count === 1 => $one,
                $count === 2 => $two,
                $count <= 10 => self::arabicBelowThousand($count).' '.$few,
                default => self::arabicBelowThousand($count).' '.$one,
            };
        }

        if ($number > 0) {
            $parts[] = self::arabicBelowThousand($number);
        }

        return implode(' و', $parts);
    }

    private static function arabicBelowThousand(int $number): string
    {
        $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
            'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $tens = [2 => 'عشرون', 3 => 'ثلاثون', 4 => 'أربعون', 5 => 'خمسون', 6 => 'ستون', 7 => 'سبعون', 8 => 'ثمانون', 9 => 'تسعون'];
        $hundreds = [1 => 'مائة', 2 => 'مائتان', 3 => 'ثلاثمائة', 4 => 'أربعمائة', 5 => 'خمسمائة',
            6 => 'ستمائة', 7 => 'سبعمائة', 8 => 'ثمانمائة', 9 => 'تسعمائة'];

        $parts = [];

        if ($number >= 100) {
            $parts[] = $hundreds[intdiv($number, 100)];
            $number %= 100;
        }

        if ($number >= 20) {
            $unit = $number % 10;
            $parts[] = ($unit ? $ones[$unit].' و' : '').$tens[intdiv($number, 10)];
        } elseif ($number > 0) {
            $parts[] = $ones[$number];
        }

        return implode(' و', $parts);
    }
}
