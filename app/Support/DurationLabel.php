<?php

namespace App\Support;

use Modules\Sales\Support\DailyClosingLedger;

/**
 * مدّةٌ بالدقائق تُقرأ بالساعات والدقائق — «ساعة و10 دقائق» لا «70».
 *
 * كانت أعمدةُ المدّة في شاشات الحضور تطبع الدقائقَ عدداً خاماً: تأخيرُ
 * سبعين دقيقةً يظهر «70»، فيُترك للقارئ أن يقسمه على ستّين في رأسه كلَّ
 * مرّة. وشاشةُ تفصيل اليوم كانت تقول «ساعة و 10 دقيقة» بالتركيب الصحيح —
 * فالصيغةُ موجودةٌ في الوحدة نفسِها، مكتوبةً في القالب لا في موضعٍ يُشارَك.
 *
 * **والجمعُ العربيُّ لا يُترك لـ`trans_choice`**: صيغةُ لارافل تعرف المفردَ
 * والجمعَ وحدَهما، والعربيةُ تفرّق المفردَ والمثنّى وجمعَ القلّة (٣–١٠)
 * وجمعَ الكثرة (١١+). فتُكتب هنا صراحةً: «ساعة»، «ساعتان»، «3 ساعات»،
 * «11 ساعة».
 *
 * @see DailyClosingLedger::durationLabel() الصيغةُ
 *      المختصرة («2 س 28 د») في مطبوعة الإقفال اليومي — مكانٌ ضيّقٌ لا
 *      يحتمل العبارة الكاملة
 */
class DurationLabel
{
    /**
     * «ساعة و10 دقائق» — والصفرُ والفراغُ يعودان بـ`$empty`.
     *
     * @param  int|float|string|null  $minutes  دقائقُ المدّة
     * @param  string  $empty  ما يُعرَض للصفر أو الفراغ (شرطةُ الجدول افتراضاً)
     */
    public static function minutes(mixed $minutes, string $empty = '—'): string
    {
        if ($minutes === null || $minutes === '') {
            return $empty;
        }

        $total = (int) round((float) $minutes);

        if ($total === 0) {
            return $empty;
        }

        // المدّةُ السالبة تُقرأ «ناقص …» لا «-ساعة …»: الإشارةُ قبل كلمةٍ
        // لا تُقرأ عربياً. ولا يُنتظر سالبٌ في أعمدة الحضور، لكن الصيغةَ
        // تُستعمل في غيرها
        $sign = $total < 0 ? 'ناقص ' : '';
        $total = abs($total);

        $hours = intdiv($total, 60);
        $rest = $total % 60;

        if ($hours === 0) {
            return $sign.self::unit($rest, 'دقيقة', 'دقيقتان', 'دقائق');
        }

        $label = self::unit($hours, 'ساعة', 'ساعتان', 'ساعات');

        return $rest === 0
            ? $sign.$label
            : $sign.$label.' و'.self::unit($rest, 'دقيقة', 'دقيقتان', 'دقائق');
    }

    /**
     * الصيغةُ المختصرة «1:10» — لعمودٍ ضيّقٍ أو مقارنةٍ بصريّةٍ بين صفوف.
     */
    public static function clock(mixed $minutes, string $empty = '—'): string
    {
        if ($minutes === null || $minutes === '') {
            return $empty;
        }

        $total = (int) round((float) $minutes);

        if ($total === 0) {
            return $empty;
        }

        $sign = $total < 0 ? '-' : '';
        $total = abs($total);

        return $sign.sprintf('%d:%02d', intdiv($total, 60), $total % 60);
    }

    /**
     * العددُ بصيغته العربية: مفردٌ بلا رقم، ومثنّى بلا رقم، ثمّ الرقمُ مع
     * جمع القلّة (٣–١٠) أو مع المفرد في جمع الكثرة (١١+).
     */
    private static function unit(int $count, string $singular, string $dual, string $few): string
    {
        return match (true) {
            $count === 1 => $singular,
            $count === 2 => $dual,
            $count <= 10 => $count.' '.$few,
            default => $count.' '.$singular,
        };
    }
}
