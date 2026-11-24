<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * فلترُ «عمر الدين» المشترك بين تقارير الأرصدة وأعمار الديون — العملاء والموردين.
 *
 * `?debt_age=120` = «ما تجاوز عمرُه ١٢٠ يوماً»: في تقارير الأطراف (الأرصدة،
 * وأعمار ديون الأستاذ) يبقى من له مبلغٌ مفتوحٌ أقدمُ من ذلك، وفي تقارير
 * الفواتير تبقى الفواتيرُ التي تجاوزته. «أكثر من» حرفياً (> لا ≥)، فتطابق
 * «أكثر من ١٢٠» عمودَ «+120» في جداول الأعمار.
 *
 * اسمُ المعامل جديدٌ عن قصد: `period` و`period_filter` و`aging_filter` كلُّها
 * مأخوذةٌ في هذه الشاشات بمعانٍ أخرى (فترة التاريخ، وشريحةٌ واحدة بعينها).
 */
final class DebtAge
{
    public const PARAM = 'debt_age';

    /** الخياراتُ المعروضة — والطلبُ يقبل أيَّ عددٍ أيامٍ صحيح */
    public const OPTIONS = [30, 60, 90, 120, 180, 365];

    /** الحدّ المطلوب بالأيام — أو `null` حين لا فلتر. */
    public static function fromRequest(Request $request): ?int
    {
        $days = (int) $request->input(self::PARAM, 0);

        return $days > 0 ? min($days, 3650) : null;
    }

    /** أيتجاوز هذا العمرُ الحدَّ؟ */
    public static function exceeds(int|float|null $days, int $threshold): bool
    {
        return $days !== null && $days > $threshold;
    }

    public static function label(int $days): string
    {
        return 'أكثر من '.$days.' يوماً';
    }
}
