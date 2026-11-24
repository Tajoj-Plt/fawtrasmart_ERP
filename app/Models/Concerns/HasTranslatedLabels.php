<?php

namespace App\Models\Concerns;

/**
 * ترجمة تسميات الثوابت.
 *
 * التسميات في النماذج معرَّفة داخل `const`، والثابت لا يقبل نداء `__()`
 * لأن قيمته تُحسَب وقت الترجمة لا وقت التشغيل. فبقيت قيمه العربية في
 * مكانها بوصفها احتياطاً، وتمرّ عبر هذه الدالة لتُستبدَل بالمترجَم إن وُجد.
 *
 * الاحتياط مقصود: مفتاح ترجمة ناقص يعرض النصّ العربي الأصلي، لا اسم
 * المفتاح الخام في وجه المستخدم.
 */
trait HasTranslatedLabels
{
    protected static function tr(string $key, string $fallback): string
    {
        $full = 'settings.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }
}
