<?php

namespace App\Support;

use App\Models\SystemLanguage;
use Illuminate\Support\Facades\Cache;

/**
 * إعدادات اللغة الحالية — الاتجاه والتنسيقات والمنطقة الزمنية والخط.
 *
 * كل ما يُضبط في شاشة «إعدادات اللغات» يسري تلقائياً حين يبدّل المستخدم لغته،
 * فتتبع التنسيقات اللغةَ بدل أن تكون ثابتة في القوالب.
 *
 * الحل مخزَّن في متغيّر ساكن لكل طلب، فلا يتكرر الاستعلام مهما تكرر النداء.
 */
class LanguageSettings
{
    protected static ?SystemLanguage $current = null;

    protected static ?string $resolvedFor = null;

    /**
     * سجلّ اللغة المطابق للّغة النشطة حالياً — أو null قبل زرع الجدول.
     */
    public static function current(): ?SystemLanguage
    {
        $locale = app()->getLocale();

        if (self::$resolvedFor === $locale) {
            return self::$current;
        }

        self::$resolvedFor = $locale;
        self::$current = null;

        try {
            $languages = Cache::get('active_system_languages');

            if ($languages === null) {
                $languages = SystemLanguage::where('is_active', true)->orderBy('sort_order')->get();

                if ($languages->isNotEmpty()) {
                    Cache::put('active_system_languages', $languages, 3600);
                }
            }

            self::$current = $languages->firstWhere('code', $locale)
                ?? $languages->firstWhere('locale', $locale);
        } catch (\Throwable $e) {
            // قاعدة بيانات غير مهيّأة: تبقى القيم الافتراضية
        }

        return self::$current;
    }

    /** يُنسى الحل المخزَّن — يُستدعى بعد تغيير اللغة داخل نفس الطلب */
    public static function forget(): void
    {
        self::$current = null;
        self::$resolvedFor = null;
    }

    public static function direction(): string
    {
        return self::current()?->direction ?: (in_array(app()->getLocale(), ['ar', 'ur', 'fa', 'he'], true) ? 'rtl' : 'ltr');
    }

    public static function isRtl(): bool
    {
        return self::direction() === 'rtl';
    }

    public static function dateFormat(): string
    {
        return self::current()?->date_format ?: 'Y-m-d';
    }

    public static function timeFormat(): string
    {
        return self::current()?->time_format ?: 'H:i';
    }

    public static function dateTimeFormat(): string
    {
        return self::dateFormat().' '.self::timeFormat();
    }

    public static function timezone(): string
    {
        return self::current()?->timezone ?: config('app.timezone', 'UTC');
    }

    public static function calendarType(): string
    {
        return self::current()?->calendar_type ?: 'gregorian';
    }

    /** يطبّق المنطقة الزمنية على التطبيق كاملاً */
    public static function apply(): void
    {
        $timezone = self::timezone();

        if ($timezone && $timezone !== config('app.timezone')) {
            config(['app.timezone' => $timezone]);

            try {
                date_default_timezone_set($timezone);
            } catch (\Throwable $e) {
                // منطقة زمنية غير صالحة في السجل: تُتجاهل ويبقى إعداد التطبيق
            }
        }
    }

    /** تنسيق تاريخ حسب إعدادات اللغة الحالية */
    public static function formatDate($date, bool $withTime = false): string
    {
        if (blank($date)) {
            return '';
        }

        try {
            $carbon = $date instanceof \DateTimeInterface
                ? \Illuminate\Support\Carbon::instance($date)
                : \Illuminate\Support\Carbon::parse($date);

            return $carbon->timezone(self::timezone())
                ->format($withTime ? self::dateTimeFormat() : self::dateFormat());
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    /** تنسيق رقم بفاصلَي الآلاف والعشرية المضبوطين للّغة */
    public static function formatNumber($number, int $decimals = 2): string
    {
        $language = self::current();

        return number_format(
            (float) $number,
            $decimals,
            $language?->decimal_separator ?: '.',
            $language?->thousands_separator ?: ','
        );
    }

    /**
     * متغيّرات CSS للخط واللون — تُحقن في ترويسة الصفحة.
     * تُعاد فارغة إن لم تُضبط، فلا تُفرض على التصميم القائم.
     */
    public static function cssVariables(): string
    {
        $language = self::current();

        if (! $language) {
            return '';
        }

        $rules = [];

        if (filled($language->font_family)) {
            $rules[] = '--app-font-family: '.$language->font_family.';';
        }
        if (filled($language->font_size)) {
            $rules[] = '--app-font-size: '.$language->font_size.';';
        }
        if (filled($language->color)) {
            $rules[] = '--app-accent-color: '.$language->color.';';
        }

        return $rules ? ':root{'.implode('', $rules).'}' : '';
    }
}
