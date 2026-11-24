<?php

namespace App\Helpers;

use Carbon\Carbon;

class WeekHelper
{
    /**
     * حساب رقم الأسبوع والسنة بشكل صحيح مع التعامل مع الأسابيع التي تمتد عبر السنوات
     * الأسبوع يبدأ من السبت وينتهي الجمعة
     *
     * @param  Carbon|null  $date
     * @return array ['week' => int, 'year' => int]
     */
    public static function getWeekAndYear($date = null)
    {
        $date = $date ?? now();

        // حساب الأسبوع بناءً على السنة الحالية
        $startOfYear = $date->copy()->startOfYear();
        $startOfYearDay = ($startOfYear->dayOfWeek + 1) % 7; // تحويل إلى السبت = 0
        $daysSinceStart = $startOfYear->diffInDays($date);
        $week = (int) ceil(($daysSinceStart + $startOfYearDay + 1) / 7);

        $year = $date->year;

        // إذا كان الأسبوع أكبر من 52، قد يكون جزءاً من السنة السابقة
        // أو قد تكون هناك حاجة للتحقق من الأسبوع 53
        if ($week > 52) {
            // التحقق: إذا كنا في أيام قليلة من نهاية السنة
            // وتاريخ اليوم أقل من بداية السنة الحالية + 7 أيام
            // فقد يكون هذا جزءاً من الأسبوع 53 من السنة السابقة
            if ($date->month === 1 && $date->day <= 2) {
                // تحقق مما إذا كان التاريخ يقع ضمن أسبوع 53 من السنة السابقة
                $lastDayOfPrevYear = $date->copy()->subYear()->endOfYear();
                $startOfPrevYear = $date->copy()->subYear()->startOfYear();
                $startOfPrevYearDay = ($startOfPrevYear->dayOfWeek + 1) % 7;
                $daysSincePrevYearStart = $startOfPrevYear->diffInDays($lastDayOfPrevYear) + 1;
                $prevYearLastWeek = (int) ceil(($daysSincePrevYearStart + $startOfPrevYearDay + 1) / 7);

                // إذا كان الأسبوع الأخير من السنة السابقة أكبر من 52
                // وكان التاريخ الحالي في أول أيام السنة الحالية
                // فهو جزء من أسبوع 53 من السنة السابقة
                if ($prevYearLastWeek >= 53) {
                    // تحقق من أن التاريخ الحالي يقع في نفس الأسبوع 53
                    $week53Start = Carbon::createFromDate($date->year - 1, 12, 27);
                    if ($date->diffInDays($week53Start) <= 6) {
                        return [
                            'week' => 53,
                            'year' => $date->year - 1,
                        ];
                    }
                }
            }
        }

        return [
            'week' => $week,
            'year' => $year,
        ];
    }

    /**
     * الحصول على أيام الأسبوع بصيغة إنجليزية
     */
    public static function getEnglishDays()
    {
        return ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    }

    /**
     * الحصول على أيام الأسبوع بصيغة عربية
     */
    public static function getArabicDays()
    {
        return ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
    }

    /**
     * الحصول على يوم الأسبوع الحالي بصيغة إنجليزية
     */
    public static function getCurrentDayNameEn($date = null)
    {
        $date = $date ?? now();
        $currentDayOfWeek = $date->dayOfWeek;
        $adjustedDayOfWeek = ($currentDayOfWeek + 1) % 7;
        $englishDays = self::getEnglishDays();

        return $englishDays[$adjustedDayOfWeek];
    }

    /**
     * الحصول على يوم الأسبوع الحالي بصيغة عربية
     */
    public static function getCurrentDayNameAr($date = null)
    {
        $date = $date ?? now();
        $currentDayOfWeek = $date->dayOfWeek;
        $adjustedDayOfWeek = ($currentDayOfWeek + 1) % 7;
        $arabicDays = self::getArabicDays();

        return $arabicDays[$adjustedDayOfWeek];
    }
}
