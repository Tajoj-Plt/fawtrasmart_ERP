<?php

namespace App\Support;

/**
 * أدوات ألوان: اشتقاق الدرجات وحساب التباين.
 *
 * تُستعمل لتوليد درجات التحويم والضغط من اللون الأساسي بدل مطالبة المستخدم
 * بضبط كل درجة يدوياً، وللتحقق من أن النص يُقرأ فوق خلفيته.
 */
class ColorTools
{
    /**
     * يحوّل #RRGGBB إلى [r, g, b] — يرجع null لأي صيغة غير صالحة.
     */
    public static function toRgb(?string $hex): ?array
    {
        if (! is_string($hex) || ! preg_match('/^#?([0-9a-fA-F]{6})$/', trim($hex), $match)) {
            return null;
        }

        return array_map('hexdec', str_split($match[1], 2));
    }

    public static function toHex(array $rgb): string
    {
        return '#' . implode('', array_map(
            fn ($channel) => str_pad(dechex((int) max(0, min(255, round($channel)))), 2, '0', STR_PAD_LEFT),
            $rgb
        ));
    }

    /**
     * تفتيح اللون بنسبة 0–1 باتجاه الأبيض.
     */
    public static function lighten(?string $hex, float $amount): ?string
    {
        $rgb = self::toRgb($hex);

        return $rgb ? self::toHex(array_map(fn ($c) => $c + (255 - $c) * $amount, $rgb)) : null;
    }

    /**
     * تعتيم اللون بنسبة 0–1 باتجاه الأسود.
     */
    public static function darken(?string $hex, float $amount): ?string
    {
        $rgb = self::toRgb($hex);

        return $rgb ? self::toHex(array_map(fn ($c) => $c * (1 - $amount), $rgb)) : null;
    }

    /**
     * `rgba()` من لون سداسي — للظلال والخلفيات الشفافة.
     */
    public static function rgba(?string $hex, float $alpha): ?string
    {
        $rgb = self::toRgb($hex);

        return $rgb ? 'rgba(' . implode(',', $rgb) . ',' . $alpha . ')' : null;
    }

    /**
     * السطوع النسبي حسب WCAG 2.1.
     */
    public static function luminance(?string $hex): ?float
    {
        $rgb = self::toRgb($hex);

        if (! $rgb) {
            return null;
        }

        $channels = array_map(function ($channel) {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * نسبة التباين بين لونين — من 1 (متطابقان) إلى 21 (أسود فوق أبيض).
     *
     * معيار WCAG AA للنص العادي هو 4.5 فأعلى.
     */
    public static function contrast(?string $foreground, ?string $background): ?float
    {
        $first = self::luminance($foreground);
        $second = self::luminance($background);

        if ($first === null || $second === null) {
            return null;
        }

        return round((max($first, $second) + 0.05) / (min($first, $second) + 0.05), 2);
    }

    /**
     * أبيض أو أسود — أيّهما يُقرأ فوق الخلفية المعطاة.
     */
    public static function readableOn(?string $background): string
    {
        $luminance = self::luminance($background);

        return ($luminance !== null && $luminance > 0.45) ? '#1a1c24' : '#ffffff';
    }

    public static function isDark(?string $hex): bool
    {
        $luminance = self::luminance($hex);

        return $luminance !== null && $luminance < 0.45;
    }
}
