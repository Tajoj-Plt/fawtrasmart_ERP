<?php

namespace Modules\Sitting\Support;

/**
 * عددُ السجلّات في صفحة القائمة — ما يختاره المستخدم من ذيل الجدول.
 *
 * ذيلُ الجدول (`sitting::components.table-foot`) يرسل `per_page` في العنوان،
 * وقوائمُ هذه الوحدة كانت تكتب العددَ ثابتاً في المتحكّم (15، 20، 25، 30)
 * فلا أثرَ للاختيار. هنا يُقرأ الطلبُ ويُقصَر على قائمةٍ مغلقة — رقمٌ حرٌّ
 * في العنوان (`per_page=100000`) بابُ إنهاكٍ للخادم لا خيارُ عرض.
 */
final class PageSize
{
    /** الخيارات المعروضة في المُنتقي. */
    public const SIZES = [10, 25, 50, 100];

    /** @param  int  $default  عددُ الشاشة حين لا يختار المستخدم شيئاً */
    public static function get(int $default = 25): int
    {
        $requested = (int) request()->query('per_page', 0);

        return in_array($requested, self::SIZES, true) ? $requested : $default;
    }
}
