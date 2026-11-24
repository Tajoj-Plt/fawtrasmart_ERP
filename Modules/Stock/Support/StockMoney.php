<?php

namespace Modules\Stock\Support;

use App\Models\SystemCurrency;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Model;

/**
 * عرض المبالغ في شاشات المخزون — خلف توجيه `@money`.
 *
 * **لماذا صنفٌ لا نداءٌ مباشر في التوجيه؟**
 *
 * `@money` تقبل شكلين: مبلغاً وحده، ومبلغاً ومستنداً معه. التمييز بينهما
 * منطقٌ لا يصحّ أن يُكتب داخل نصّ PHP مُولَّد في التوجيه، فوُضع هنا حيث
 * يُقرأ ويُختبَر.
 *
 * **قاعدة العرض**
 *
 *   بلا مستند   ← العملة الافتراضية للنظام، بكل تنسيقاتها المضبوطة.
 *   مع مستند    ← عملة المستند المحفوظة معه.
 *
 * الفرق ليس تجميلياً: إذنٌ حُرّر بالدولار يجب أن يُقرأ بالدولار بعد سنة،
 * ولو صارت عملة المنشأة الافتراضية شيئاً آخر.
 */
class StockMoney
{
    /**
     * مبلغ منسَّق للعرض.
     *
     * @param  mixed  $amount  المبلغ
     * @param  Model|string|null  $document  المستند صاحب العملة، أو رمز عملة
     */
    public static function render($amount, Model|string|null $document = null): string
    {
        if ($document === null) {
            return e(CurrencySettings::format($amount));
        }

        // النموذج الذي يستعمل `HasDocumentCurrency` يعرف كيف يُنسّق بعملته
        if ($document instanceof Model && method_exists($document, 'formatMoney')) {
            return e($document->formatMoney($amount));
        }

        $code = $document instanceof Model
            ? (string) $document->getAttribute('currency')
            : (string) $document;

        if ($code === '' || $code === CurrencySettings::code()) {
            return e(CurrencySettings::format($amount));
        }

        return e(CurrencySettings::number($amount).' '.self::symbolFor($code));
    }

    /** رمز عملة بعينها — أو رمزها الحرفي إن لم تكن مسجَّلة */
    protected static function symbolFor(string $code): string
    {
        try {
            $currency = SystemCurrency::where('code', $code)->first();
        } catch (\Throwable $e) {
            return $code;
        }

        if (! $currency) {
            return $code;
        }

        return $currency->native_symbol ?: ($currency->symbol ?: $code);
    }
}
