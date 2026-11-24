<?php

namespace Modules\Client\Http\Controllers\Concerns;

use App\Models\SystemCurrency;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * عملة مستندات وحدة العملاء — افتراضٌ عند الإنشاء، وتجميدٌ بعد الحفظ.
 *
 * **القاعدة**
 *
 *   الإنشاء  ← العملة الافتراضية من شاشة «العملات».
 *   الحفظ    ← تُخزَّن مع المستند في عموده `currency`.
 *   ما بعده  ← تُقرأ من المستند وحده. تبديل الافتراضية في الإعدادات لا
 *              يمسّ قيداً قائماً، لا في العرض ولا في الترحيل ولا في الطباعة.
 *
 * **ما كان**
 *
 * `DoubtfulDebtController` يكتب `'currency' => 'SAR'` نصّاً في اثني عشر
 * موضعاً: في كل رأس قيدٍ وكل طرفٍ من أطرافه. فمن ضبط عملة النظام على غير
 * الريال رأى رصيد عميله بعملته في الشاشة، وقيدَ دينه بـSAR في دفتر
 * اليومية.
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\UsesDocumentCurrency النسخة الأمّ
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
}
