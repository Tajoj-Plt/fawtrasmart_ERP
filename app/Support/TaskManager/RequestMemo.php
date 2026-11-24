<?php

namespace App\Support\TaskManager;

/**
 * حفظُ نتيجةٍ لمدّة الطلب الجاري.
 *
 * على الطلب لا في خاصّيةٍ ساكنة: الساكنة تعيش بين الاختبارات وفي العامل
 * الطويل العمر، فتُجيب بإعدادٍ أو عضويةٍ تغيّرت منذ طلباتٍ مضت.
 */
class RequestMemo
{
    private const KEY = 'taskmanager.memo';

    public static function remember(string $key, callable $resolve): mixed
    {
        $bag = request()->attributes;
        $memo = $bag->get(self::KEY, []);

        if (! array_key_exists($key, $memo)) {
            $memo[$key] = $resolve();
            $bag->set(self::KEY, $memo);
        }

        return $memo[$key];
    }

    public static function forget(?string $prefix = null): void
    {
        $bag = request()->attributes;

        if ($prefix === null) {
            $bag->remove(self::KEY);

            return;
        }

        $bag->set(self::KEY, array_filter(
            $bag->get(self::KEY, []),
            fn ($key) => ! str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY,
        ));
    }
}
