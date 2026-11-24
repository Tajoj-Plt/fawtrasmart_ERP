<?php

namespace Modules\RentalManagement\Support;

use App\Models\SystemCurrency;
use App\Support\CurrencySettings;
use Throwable;

/**
 * عرض مبلغٍ مع عملته **المسجَّلة** على الحركة.
 *
 * العملة لقطةٌ تُحفظ وقت إنشاء الدفعة أو الحركة، فلا تتبدّل حين تتبدّل عملة
 * النظام: دفعةٌ قُبضت بالريال تبقى معروضةً بالريال. وما لا عملة عليه (سجلٌّ
 * قديم أو إجماليٌّ عبر حركات) يُعرض بعملة النظام الحالية.
 */
class RealEstateMoney
{
    /** @var array<string, ?string> */
    private static array $symbols = [];

    public static function format($amount, ?string $currency = null): string
    {
        $currency = $currency ? strtoupper(trim($currency)) : null;

        if (! $currency || $currency === strtoupper(self::code())) {
            return CurrencySettings::format($amount);
        }

        $value = (float) $amount;
        $text = number_format(abs($value), 2).' '.(self::symbol($currency) ?: $currency);

        return $value < 0 ? '-'.$text : $text;
    }

    /** الرقم وحده بلا رمز — لخانات الجداول التي يحمل رأسُها العملة. */
    public static function number($amount): string
    {
        return number_format((float) $amount, 2);
    }

    /** رمز عملة النظام الحالية — ما يُختَم على الحركة الجديدة. */
    public static function code(): string
    {
        try {
            return CurrencySettings::code();
        } catch (Throwable $e) {
            return 'SAR';
        }
    }

    /** رمز العرض لعملةٍ مسجَّلة (ر.س) أو رمزها الدولي إن لم تُعرَّف. */
    public static function symbol(?string $currency = null): string
    {
        $currency = strtoupper(trim((string) ($currency ?: self::code())));

        if (! array_key_exists($currency, self::$symbols)) {
            try {
                self::$symbols[$currency] = SystemCurrency::where('code', $currency)->value('symbol');
            } catch (Throwable $e) {
                self::$symbols[$currency] = null;
            }
        }

        return self::$symbols[$currency] ?: $currency;
    }
}
