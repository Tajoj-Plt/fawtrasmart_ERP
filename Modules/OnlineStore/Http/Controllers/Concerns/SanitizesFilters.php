<?php

namespace Modules\OnlineStore\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * فلاترُ الرابط نصوصٌ لا مصفوفات.
 *
 * `?search[]=x` أو `?end_date[]=1` في شريط العنوان يصل الصفحةَ مصفوفةً،
 * والقوالب تطبعه بـ`{{ request('search') }}` فتسقط بـ500
 * (`htmlspecialchars(): array given`) — والجزءُ المشترك لفلتر الفترة
 * المحاسبية يطبع حقلَي التاريخ بالطريقة نفسها. تُحذف هنا قبل أن تصل أحداً.
 */
trait SanitizesFilters
{
    /**
     * يحذف من الاستعلام كلَّ مفتاحٍ من المذكورة ليست قيمتُه نصّاً.
     *
     * @param  array<int, string>  $keys
     */
    protected function dropNonScalarFilters(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            $value = $request->query($key);

            if ($value !== null && ! is_scalar($value)) {
                $request->query->remove($key);
                $request->request->remove($key);
            }
        }
    }
}
