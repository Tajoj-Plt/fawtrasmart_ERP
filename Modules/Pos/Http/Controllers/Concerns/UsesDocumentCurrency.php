<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Models\SystemCurrency;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * عملة مستندات نقاط البيع — افتراضٌ عند الإنشاء، وتجميدٌ بعد الحفظ.
 *
 * ─────────────────────── القاعدة ───────────────────────
 *
 *   الإنشاء  ← العملة الافتراضية من شاشة «العملات».
 *   الحفظ    ← تُخزَّن مع المستند في عموده `currency`.
 *   ما بعده  ← تُقرأ من المستند وحده. تبديلُ الافتراضية في الإعدادات لا يمسّ
 *              مستنداً قائماً، لا في العرض ولا في القيد ولا في التقرير ولا
 *              في الإيصال المطبوع.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 *     'currency' => 'SAR',
 *
 * أربعةَ عشرَ موضعاً بهذه الصيغة في متحكّم واحد — في الفاتورة وفي المرتجع
 * وفي كلّ قيدٍ محاسبي يُنشأ. ومعها رسائلُ خطأٍ تقول «ر.س» صراحةً، ورمزُ
 * الاستجابة QR يكتب المبلغ بلا عملةٍ أصلاً.
 *
 * فمنشأةٌ تبيع بعملةٍ أخرى كانت تُصدر إيصالاتٍ ترياليةً وقيوداً ريالية
 * ومجاميعَ ريالية — بينما شاشة العملات تقول غير ذلك ولا أثر لها.
 *
 * ─────────────────────── ولماذا يُجمَّد بعد الحفظ؟ ───────────────────────
 *
 * الفاتورةُ مستندٌ مُصدَر: طُبع إيصالُها وسُلّم للعميل، وقُيّد مبلغُها في
 * دفتر الأستاذ. فتغييرُ عملتها لاحقاً — بتبديل الافتراضية في الإعدادات —
 * يجعل المطبوعَ يخالف المحفوظ، والقيدَ يخالف الفاتورة التي وُلد منها.
 */
trait UsesDocumentCurrency
{
    /**
     * العملة الافتراضية للنظام — القيمة الابتدائية لأي مستند جديد،
     * والقيمة الافتراضية في الفلاتر.
     */
    protected function defaultCurrency(): string
    {
        return CurrencySettings::code();
    }

    /**
     * عملة مستند محفوظ — المخزَّنة معه وحدها.
     *
     * تقبل `null` احتياطاً للحافّة: مستنداتُ ما قبل الترحيل قد تحمل عموداً
     * فارغاً، فتُقرأ عندئذ بالافتراضية الحالية بدل أن تُطبع بلا عملة.
     *
     * @param  Model|string|null  $document  المستند، أو رمز عملة جاهز
     */
    protected function documentCurrency(Model|string|null $document): string
    {
        if (is_string($document)) {
            return $document !== '' ? $document : $this->defaultCurrency();
        }

        $stored = $document?->getAttribute('currency');

        return is_string($stored) && $stored !== '' ? $stored : $this->defaultCurrency();
    }

    /**
     * رمز العملة كما يُطبع على الإيصال — لا نصٌّ مكتوبٌ في القالب.
     *
     * كانت الشيفرة تُلحق «ر.س» بيدها في رسائل الخطأ وفي الإيصال. والرمزُ
     * صفةٌ للعملة تُقرأ منها.
     */
    protected function currencySymbol(Model|string|null $document = null): string
    {
        $code = $this->documentCurrency($document);

        try {
            $currency = SystemCurrency::where('code', $code)->first(['symbol', 'native_symbol', 'code']);

            return $currency?->native_symbol ?: ($currency?->symbol ?: $code);
        } catch (\Throwable $e) {
            return $code;
        }
    }

    /**
     * عملة الفلتر — ما اختاره المستخدم، وإلا الافتراضية.
     *
     * السلسلة الفارغة تعني «كل العملات» فتُرَدّ `null`: الفلتر الفارغ يجب
     * ألّا يُقصي شيئاً.
     */
    protected function filterCurrency(Request $request, string $key = 'currency'): ?string
    {
        if (! $request->has($key)) {
            return $this->defaultCurrency();
        }

        $value = trim((string) $request->input($key));

        return $value === '' ? null : $value;
    }

    /**
     * العملات المفعَّلة — لقوائم الاختيار في النماذج والفلاتر.
     *
     * @return Collection<int, SystemCurrency>
     */
    protected function activeCurrencies()
    {
        try {
            return SystemCurrency::where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'arabic_name', 'english_name', 'symbol', 'native_symbol', 'is_default']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * سمات الإنشاء الخاصّة بالعملة — تُدمج في بيانات أي مستند جديد.
     *
     * @return array{currency: string}
     */
    protected function newDocumentCurrencyAttributes(): array
    {
        return ['currency' => $this->defaultCurrency()];
    }

    /**
     * ينزع `currency` من بيانات التعديل — عملة المستند لا تُعدَّل بعد حفظه.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withoutCurrency(array $attributes): array
    {
        unset($attributes['currency']);

        return $attributes;
    }
}
