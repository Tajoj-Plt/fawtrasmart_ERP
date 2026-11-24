<?php

namespace App\Support;

use App\Models\AccountSetting;
use App\Models\SystemCurrency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * العملة الافتراضية كما ضُبطت في شاشة «العملات» — الرمز والكسور والفواصل.
 *
 * كانت المبالغ تُطبع في الشاشات بـ`number_format($v, 2)` وتُلحق بها «ر.س»
 * مكتوبةً في القالب، فلا يغيّر تبديلُ العملة في الإعدادات شيئاً على الشاشة.
 * هنا نقطة القراءة الموحّدة:
 *
 *     CurrencySettings::format($invoice->total)    // 1,092.50 ر.س
 *     CurrencySettings::format($v, false)          // 1,092.50 بلا رمز
 *     CurrencySettings::symbol()                   // ر.س
 *
 * الكاش يستعمل مفتاح `default_system_currency` نفسه الذي تُبطله
 * `CurrencyRepository::clearCache()`، فلا حاجة لإبطال إضافي عند الحفظ.
 */
class CurrencySettings
{
    protected const CACHE_KEY = 'default_system_currency';

    protected const CACHE_TTL = 3600;

    protected static ?SystemCurrency $resolved = null;

    protected static bool $attempted = false;

    /**
     * سجلّ العملة الافتراضية — أو null قبل زرع الجدول.
     *
     * الترتيب: `is_default` في جدول العملات، ثم العملة المختارة في إعدادات
     * حساب المستخدم الحالي، ثم لا شيء (فتعود التنسيقات إلى الافتراضي).
     */
    public static function current(): ?SystemCurrency
    {
        if (self::$attempted) {
            return self::$resolved;
        }

        self::$attempted = true;

        try {
            $currency = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL,
                fn () => SystemCurrency::where('is_default', true)->first()
            );

            if (! $currency && Auth::check()) {
                $code = AccountSetting::where('user_id', Auth::id())->value('currency');
                $currency = $code ? SystemCurrency::where('code', $code)->first() : null;
            }

            self::$resolved = $currency;
        } catch (\Throwable $e) {
            // قاعدة بيانات غير مهيّأة (أثناء الترحيلات مثلاً): تبقى الافتراضيات
            self::$resolved = null;
        }

        return self::$resolved;
    }

    /** يُنسى الحل المخزَّن — للاختبارات وبعد الحفظ داخل الطلب نفسه */
    public static function forget(): void
    {
        self::$resolved = null;
        self::$attempted = false;
        Cache::forget(self::CACHE_KEY);
    }

    public static function code(): string
    {
        return self::current()?->code ?: 'SAR';
    }

    /** الرمز المعروض — الأصلي إن وُجد، وإلا الرمز العام، وإلا رمز العملة */
    public static function symbol(): string
    {
        $currency = self::current();

        if (! $currency) {
            return 'SAR';
        }

        return $currency->native_symbol ?: ($currency->symbol ?: $currency->code);
    }

    /** اسم العملة بلغة الواجهة الحالية */
    public static function name(): string
    {
        $currency = self::current();

        if (! $currency) {
            return self::code();
        }

        $localized = app()->getLocale() === 'ar'
            ? $currency->arabic_name
            : $currency->english_name;

        return $localized ?: ($currency->name ?: $currency->code);
    }

    /** عدد المنازل العشرية المضبوط للعملة */
    public static function decimals(): int
    {
        $places = self::current()?->decimal_places;

        return $places === null ? 2 : max(0, (int) $places);
    }

    /**
     * فاصل الآلاف — من العملة، وإلا من إعدادات اللغة النشطة.
     *
     * الفواصل معرَّفة في الشاشتين معاً؛ العملة أخصّ فتتقدّم، واللغة تغطّي
     * الحالة التي لم تُضبط فيها العملة (مصفوفة العملات المزروعة تتركها فارغة).
     */
    public static function thousandsSeparator(): string
    {
        $separator = self::current()?->thousands_separator;

        if ($separator !== null && $separator !== '') {
            return $separator;
        }

        return LanguageSettings::current()?->thousands_separator ?: ',';
    }

    public static function decimalSeparator(): string
    {
        $separator = self::current()?->decimal_separator;

        if ($separator !== null && $separator !== '') {
            return $separator;
        }

        return LanguageSettings::current()?->decimal_separator ?: '.';
    }

    /**
     * كميةٌ معروضة — كسورُها بقدر ما فيها لا بقدرٍ ثابت.
     *
     * `invoice_items.quantity` صار `decimal(15,4)`، فمن يبيع بالكيلو أو
     * بالمتر يكتب `2.75`. وكانت شاشات العرض تطبعها بـ`number($q, 0)`
     * فتصير «٣» — رقمٌ لم يكتبه أحد ولا يوافق ما تحسبه الفاتورة نفسها.
     * و`number($q, 4)` يقلبها إلى «٣٫٠٠٠٠» في الغالبية العظمى من البنود
     * التي كمّياتها صحيحة.
     *
     * فالكسورُ بقدر ما فيها: صحيحةٌ بلا كسر، وكسريّةٌ بلا أصفارٍ لاحقة —
     * والفواصلُ من «العملات» كما في `number()`، فلا يختلف شكلُ الرقم
     * باختلاف عمودِه.
     *
     * @param  int  $max أقصى كسرٍ يُعرَض — أربعةٌ كما يخزّنه العمود
     */
    public static function quantity($amount, int $max = 4): string
    {
        $value = self::round((float) $amount, $max);

        // عدد الخانات الفعلية: تُنقَص الأصفارُ اللاحقة واحداً واحداً
        $decimals = $max;

        while ($decimals > 0 && self::round($value, $decimals - 1) === $value) {
            $decimals--;
        }

        return self::number($value, $decimals);
    }

    /**
     * رقم منسَّق بلا رمز العملة — للجداول التي يُذكر فيها الرمز مرة واحدة.
     */
    public static function number($amount, ?int $decimals = null): string
    {
        $decimals ??= self::decimals();
        $value = self::round((float) $amount, $decimals);

        return number_format(
            abs($value),
            $decimals,
            self::decimalSeparator(),
            self::thousandsSeparator()
        );
    }

    /**
     * مبلغ منسَّق كاملاً — الكسور والفواصل والرمز وشكل السالب.
     *
     * @param  bool  $withSymbol  إلحاق رمز العملة بالناتج
     */
    public static function format($amount, bool $withSymbol = true): string
    {
        $decimals = self::decimals();
        $value = self::round((float) $amount, $decimals);
        $formatted = self::number($value, $decimals);

        if ($withSymbol) {
            $formatted = self::attachSymbol($formatted);
        }

        return $value < 0 ? self::wrapNegative($formatted) : $formatted;
    }

    /**
     * تقريب حسب `rounding_method` المضبوط للعملة.
     *
     * `ceil`/`floor` يُطبَّقان على القيمة المطلقة ثم تُعاد الإشارة، وإلا
     * قرّب السالبُ في الاتجاه المعاكس لما يتوقّعه المحاسب.
     */
    protected static function round(float $value, int $decimals): float
    {
        $factor = 10 ** $decimals;
        $method = self::current()?->rounding_method ?: 'round';
        $sign = $value < 0 ? -1 : 1;
        $magnitude = abs($value) * $factor;

        return match ($method) {
            'ceil' => $sign * ceil($magnitude) / $factor,
            'floor' => $sign * floor($magnitude) / $factor,
            default => round($value, $decimals),
        };
    }

    /**
     * موضع الرمز — `suffix` يضعه بعد الرقم (الافتراضي في العربية)،
     * و`prefix` قبله. القيمة `standard` المزروعة تعني «اتبع اتجاه اللغة».
     */
    protected static function attachSymbol(string $formatted): string
    {
        $symbol = self::symbol();

        if ($symbol === '') {
            return $formatted;
        }

        $position = self::current()?->positive_format ?: 'standard';

        $prefix = $position === 'prefix'
            || ($position === 'standard' && ! LanguageSettings::isRtl());

        return $prefix ? $symbol.' '.$formatted : $formatted.' '.$symbol;
    }

    /**
     * شكل المبالغ السالبة — الأقواس أو إشارة الناقص.
     *
     * العملة أخصّ، فإن لم تُضبط رجعنا إلى إعداد الحساب
     * (`negative_currency_formats` في شاشة معلومات الحساب).
     */
    protected static function wrapNegative(string $formatted): string
    {
        $format = self::current()?->negative_format;

        if (! $format || $format === 'standard') {
            $format = self::accountNegativeFormat();
        }

        return $format === 'parentheses' ? '('.$formatted.')' : '-'.$formatted;
    }

    protected static function accountNegativeFormat(): string
    {
        if (! Auth::check()) {
            return 'standard';
        }

        try {
            return AccountSetting::where('user_id', Auth::id())
                ->value('negative_currency_formats') ?: 'standard';
        } catch (\Throwable $e) {
            return 'standard';
        }
    }
}
