<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\SystemCurrency;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * عملة مستندات المبيعات — افتراضٌ عند الإنشاء، وتجميدٌ بعد الحفظ.
 *
 * **القاعدة**
 *
 *   الإنشاء  ← العملة الافتراضية من شاشة «العملات».
 *   الحفظ    ← تُخزَّن مع المستند في عموده `currency`.
 *   ما بعده  ← تُقرأ من المستند وحده. تبديل الافتراضية في الإعدادات لا
 *              يمسّ فاتورةً قائمة، لا في العرض ولا في القيد ولا في الطباعة.
 *
 * **ما كان**
 *
 * ستّة مواضع في `InvoicesController` تكتب `'currency' => 'SAR'` نصّاً في
 * القيد المحاسبي، وقالب الجدول يقرأ `$account_setting->currency` — إعداد
 * حسابٍ شخصيّ لا إعداد منشأة — ثم يستبدل بالرمز صورةَ ريالٍ ثابتة. فمن
 * ضبط عملة النظام على غير الريال رأى فاتورته بالريال في الشاشة وبـSAR في
 * القيد.
 *
 * @see \Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency النسخة الأمّ
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
     * تطبيق فلتر العملة على استعلامٍ ما.
     *
     * ══════════════════════════════════════════════════════════════════
     *  لماذا لا `where('currency', $code)` مباشرةً
     * ══════════════════════════════════════════════════════════════════
     *
     * عمود `currency` أُضيف إلى جداول المستندات بعد أن امتلأت: الصفوفُ
     * السابقةُ له تحمل `''` أو `null` لا رمزَ عملة. والفلتر يبدأ على
     * العملة الافتراضية، فمطابقةٌ حرفيّةٌ تُقصي كلَّ ما سبق العمود.
     *
     * شاشةُ الإشعارات الدائنة كانت المثال: ٤٦٢ إشعاراً من ٤٦٣ بعمودٍ
     * فارغ، فتفتح الشاشةُ على إشعارٍ واحد ويظنّ المستخدمُ أنّها لا تجلب
     * شيئاً — لا خطأَ ولا رسالة.
     *
     * فالقاعدة: **الفارغ عملةُ النظام**. من اختار العملة الافتراضية رأى
     * معها ما لا عملةَ له؛ ومن اختار غيرها رأى ما خُتم بها وحده.
     *
     * @param  string|null  $currency  رمز العملة، أو `null` لكل العملات
     * @param  string  $column  اسم العمود — مؤهَّلاً باسم الجدول عند الضمّ
     */
    protected function applyCurrencyFilter($query, ?string $currency, string $column = 'currency'): void
    {
        if ($currency === null) {
            return;
        }

        if ($currency !== $this->defaultCurrency()) {
            $query->where($column, $currency);

            return;
        }

        $query->where(function ($q) use ($column, $currency) {
            $q->where($column, $currency)
                ->orWhereNull($column)
                ->orWhere($column, '');
        });
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
