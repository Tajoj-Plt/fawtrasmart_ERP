<?php

namespace Modules\Stock\Http\Controllers\Concerns;

use App\Models\SystemCurrency;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * عملة مستندات المخزون — افتراضٌ عند الإنشاء، وتجميدٌ بعد الحفظ.
 *
 * **القاعدة**
 *
 *   الإنشاء  ← العملة الافتراضية من شاشة «العملات».
 *   الحفظ    ← تُخزَّن مع المستند في عموده `currency`.
 *   ما بعده  ← تُقرأ من المستند وحده. تبديل الافتراضية في الإعدادات لا يمسّ
 *              مستنداً قائماً، لا في العرض ولا في القيد ولا في التقرير.
 *
 * كانت الشيفرة تكتب `'currency' => 'SAR'` في ثلاثة عشر موضعاً، والمستندات
 * بلا عمود عملة أصلاً — فلا العملةُ الافتراضية تُحترم، ولا القديمُ يُصان.
 *
 * **لماذا `documentCurrency()` تقبل `null`؟**
 *
 * مستندات ما قبل الترحيل قد تحمل `null` نظرياً؛ تُقرأ عندئذ بالافتراضية
 * الحالية بدل أن تُطبع فارغة. الترحيل يملأ القائم، وهذا احتياط للحافّة.
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
     * سمات الإنشاء الخاصّة بالعملة — تُدمج في `create()`.
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
