<?php

namespace App\Models\Concerns;

use App\Models\SystemCurrency;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * عملة المستند: تُملأ مرّة عند الإنشاء، ثمّ تُجمَّد.
 *
 * **لماذا في النموذج لا في المتحكّم؟**
 *
 * مستندات المخزون تُنشأ من تسعة عشر موضعاً موزّعة على ثمانية متحكّمات —
 * إذنٌ يولّده أمرُ تصنيع، وآخرُ تولّده شاشةُ الجرد، وثالثٌ تولّده فاتورةُ
 * مبيعات من وحدة أخرى. ضبط العملة في كل موضع يعني ثغرةً في كل موضع
 * يُنسى، وثغرةً في كل موضع يُضاف لاحقاً.
 *
 * هنا الضمانة بنيوية: أيّاً كان الطريق الذي أنشأ المستند — متحكّم أو
 * سيدر أو مهمّة في الطابور أو وحدة أخرى — يخرج بعملة معلومة، ولا يملك
 * أحدٌ تغييرها بعد الحفظ.
 *
 * **قاعدتان**
 *
 *   `creating` ← إن لم تُحدَّد عملة، تُؤخذ الافتراضية من شاشة «العملات».
 *   `updating` ← أيّ محاولة لتغييرها تُرَدّ إلى قيمتها المحفوظة.
 *
 * الردّ صامتٌ عمداً لا استثناء: تعديلُ حقلٍ آخر في مستند قديم يجب ألّا
 * يفشل لأن نموذجاً قديماً أرسل معه عملةً مختلفة. لكنّه يُكتب في سجلّ
 * لارافيل، فلا تمرّ المحاولة بلا أثر.
 *
 * الجدول المضيف يجب أن يحمل عموداً باسم `currency`.
 */
trait HasDocumentCurrency
{
    public static function bootHasDocumentCurrency(): void
    {
        static::creating(function (Model $model) {
            if (blank($model->getAttribute('currency'))) {
                $model->setAttribute('currency', static::resolveDefaultCurrency());
            }
        });

        static::updating(function (Model $model) {
            if (! $model->isDirty('currency')) {
                return;
            }

            $original = $model->getOriginal('currency');

            // مستند أقدم من عمود العملة: يُسمح بملء الفراغ مرّة واحدة،
            // فتجميدُ `null` يترك المستند بلا عملة إلى الأبد
            if (blank($original)) {
                return;
            }

            Log::warning('محاولة تغيير عملة مستند محفوظ — رُدّت', [
                'model' => static::class,
                'id' => $model->getKey(),
                'from' => $original,
                'to' => $model->getAttribute('currency'),
            ]);

            $model->setAttribute('currency', $original);
        });
    }

    /**
     * العملة الافتراضية — أو `SAR` إن لم يُهيَّأ جدول العملات بعد.
     *
     * السيدرات والترحيلات تُنشئ مستندات على قاعدة لم تُزرع فيها العملات،
     * فلا يجوز أن تُسقط القراءةُ الإنشاءَ.
     */
    protected static function resolveDefaultCurrency(): string
    {
        try {
            return CurrencySettings::code();
        } catch (\Throwable $e) {
            return 'SAR';
        }
    }

    /**
     * عملة هذا المستند — المحفوظة معه، وإلا الافتراضية الحالية.
     */
    public function documentCurrency(): string
    {
        $stored = $this->getAttribute('currency');

        return is_string($stored) && $stored !== '' ? $stored : static::resolveDefaultCurrency();
    }

    /**
     * مبلغ منسَّق بعملة هذا المستند لا بالعملة الافتراضية.
     *
     * `CurrencySettings::format()` تُنسّق دائماً بالافتراضية، وهي الصواب في
     * الشاشات العامّة. أمّا سطرُ مستندٍ بعملة أخرى فيجب أن يُطبع برمزها هو.
     */
    public function formatMoney($amount, bool $withSymbol = true): string
    {
        $number = CurrencySettings::number($amount);

        if (! $withSymbol) {
            return $number;
        }

        $code = $this->documentCurrency();

        // العملة الافتراضية تمرّ بالمنسّق الكامل: رمزها الأصلي وموضعه
        // وشكل السالب كما ضُبطت كلّها في شاشة «العملات»
        if ($code === static::resolveDefaultCurrency()) {
            return CurrencySettings::format($amount);
        }

        return $number.' '.static::symbolFor($code);
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
