<?php

namespace App\Services\Tax;

use App\Models\TaxSitting;
use Illuminate\Support\Collection;

/**
 * حساب ضريبة بند مستند — مصدر واحد للمبيعات والمشتريات معاً.
 *
 * **المسألة**: لكل ضريبة في «إعدادات الضريبة» نوعٌ (`tax_sittings.type`):
 *
 *   • `excluded` — السعر بلا ضريبة: 2000 بنسبة 15% ⇒ ضريبة 300، إجمالي 2300.
 *   • `included` — السعر شاملها: 2000 بنسبة 15% ⇒ ضريبة 260.87، إجمالي 2000.
 *
 * أي أن الضريبة المتضمَّنة **تُستخرَج** من المبلغ ولا تُضاف فوقه، فالإجمالي
 * يبقى ما أدخله المستخدم. المعادلة المعتمدة هي القياسية:
 *
 *     الضريبة = المبلغ − المبلغ ÷ (1 + النسبة ÷ 100)
 *
 * وهي نفسها المستعملة أصلاً في «أوامر الشراء» و«عروض أسعار المشتريات».
 *
 * **لماذا انتقل من `App\Services\Purchases`؟** كانت المشتريات وحدها تقرأ
 * `tax_sittings.type`، بينما فواتير البيع ومرتجعاته وإشعاراته الدائنة
 * تحسب الضريبة `المبلغ × النسبة ÷ 100` دائماً — أي تُضيفها فوق السعر ولو
 * كان الإعداد «متضمَّنة». والنتيجة أنّ شاشة البيع تعرض رقماً
 * (`invoice-calculator.js` يعرف النوع) والقاعدة تحفظ رقماً آخر، والقيد
 * يُبنى على المحفوظ. فصار الحسّاب مشتركاً بينهما.
 *
 * `App\Services\Purchases\PurchaseTaxCalculator` باقٍ صنفاً وارثاً، فلا
 * يُمسّ نداءٌ قائم في وحدة المشتريات.
 *
 * **الاستعمال**:
 *
 *     $line = DocumentTaxCalculator::line($quantity, $unitPrice, $discount, [$taxId1, $taxId2]);
 *     $line['tax'];    // مبلغ الضريبة
 *     $line['total'];  // إجمالي البند
 */
class DocumentTaxCalculator
{
    /** الضرائب محمَّلة مرة واحدة لكل طلب — تُنادى لكل بند */
    protected static ?Collection $cache = null;

    /**
     * كل الضرائب المعرَّفة، مفهرسةً بالمعرّف.
     *
     * @return Collection<int, TaxSitting>
     */
    public static function all(): Collection
    {
        if (self::$cache === null) {
            try {
                self::$cache = TaxSitting::all()->keyBy('id');
            } catch (\Throwable $e) {
                // جدول غير مهيّأ في هذا التثبيت: بلا ضرائب
                self::$cache = collect();
            }
        }

        return self::$cache;
    }

    /** يُنسى المحفوظ — للاختبارات وبعد تعديل الإعدادات */
    public static function forget(): void
    {
        self::$cache = null;
    }

    public static function find($taxId): ?TaxSitting
    {
        if (empty($taxId)) {
            return null;
        }

        return self::all()->get((int) $taxId);
    }

    /**
     * أول ضريبة نسبتها مطابقة — لبنودٍ حُفظت قبل وجود عمود المعرّف.
     *
     * الشاشات القديمة كانت ترسل النسبة وحدها، فلا سبيل لمعرفة أيّ ضريبة
     * كانت إلا بمطابقتها. تُستعمل لإعادة الاختيار في شاشة التعديل فقط،
     * فلا تمسّ أرقام مستند محفوظ.
     */
    public static function matchByRate(float $rate): ?int
    {
        if ($rate <= 0) {
            return null;
        }

        $match = self::all()->first(fn ($tax) => abs((float) $tax->tax - $rate) < 0.001);

        return $match->id ?? null;
    }

    /** النسبة المئوية المضبوطة للضريبة */
    public static function rate($taxId): float
    {
        return (float) (self::find($taxId)->tax ?? 0);
    }

    /** هل الضريبة متضمَّنة في السعر؟ الافتراضي `excluded` كما في العمود */
    public static function isIncluded($taxId): bool
    {
        return (self::find($taxId)->type ?? 'excluded') === 'included';
    }

    /**
     * مبلغ الضريبة على أساس مبلغ.
     *
     * @param  float  $base  المبلغ بعد الخصم
     * @param  float  $rate  النسبة المئوية
     * @param  bool  $included  هل النسبة متضمَّنة في `$base`؟
     */
    public static function amount(float $base, float $rate, bool $included): float
    {
        if ($base <= 0 || $rate <= 0) {
            return 0.0;
        }

        return round(
            $included ? $base - ($base / (1 + $rate / 100)) : $base * $rate / 100,
            2
        );
    }

    /** مبلغ الضريبة من معرّف ضريبة مضبوطة — النسبة والنوع منها معاً */
    public static function amountFor(float $base, $taxId, ?float $legacyRate = null): float
    {
        $tax = self::find($taxId);

        if (! $tax) {
            // بلا معرّف: النسبة المحفوظة تُعامَل معاملة الضريبة المضافة فوق
            // السعر، وهو سلوك البنود القديمة يوم حُفظت
            return self::amount($base, (float) ($legacyRate ?? 0), false);
        }

        return self::amount($base, (float) $tax->tax, $tax->type === 'included');
    }

    /**
     * ══════════════════════════════════════════════════════════════════
     * الضريبة الشاملة — ضريبةٌ واحدةٌ على مستوى المستند تُفرَض على كلّ بنوده
     * ══════════════════════════════════════════════════════════════════
     *
     * حين تُعلَّم ضريبةٌ «شاملةً للمستندات» في «إعدادات الضريبة»، تظهر خانةٌ
     * واحدةٌ أعلى المستند بدل اختيار ضريبةٍ لكلّ سطر، وما يُختار فيها يُفرَض
     * على كلّ بند. فهنا تُعاد كتابةُ ضريبة كلّ بندٍ إلى المختار قبل أيّ حساب
     * أو حفظ — والمصدرُ الموثوق هو الخادمُ لا النموذج: طلبٌ مصنوعٌ بيدٍ قد
     * يحمل ضريبةً أخرى، فتُطرَح ويُثبَّت المختار.
     *
     * `$taxId` فارغاً (`null`) ⇒ **بلا ضريبة**: تُمسَح ضريبةُ كلّ بند
     * (اختار المستخدمُ «بدون ضريبة» في الخانة). وتُصفَّر الضريبة الثانية
     * دائماً — الشاملة واحدةٌ بحكم تعريفها.
     *
     * تُنادى فقط والميزةُ مفعَّلة ({@see applyComprehensive})، فلا تمسّ
     * البنودَ حين لا ضريبةَ معلَّمةً في الإعدادات.
     *
     * @param  array<int|string, array<string, mixed>>  $items  بنود الطلب كما وردت
     * @return array<int|string, array<string, mixed>> البنود بعد فرض/مسح الضريبة
     */
    public static function forceLineTaxes(array $items, ?int $taxId): array
    {
        $rate = $taxId !== null ? self::rate($taxId) : 0.0;

        foreach ($items as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $item['tax_1_id'] = $taxId;
            $item['tax_1'] = $rate;
            $item['tax_2_id'] = null;
            $item['tax_2'] = 0;

            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * يُطبّق الضريبة الشاملة على بنود الطلب متى كانت الميزةُ مفعَّلة.
     *
     * الميزةُ مفعَّلةٌ حين تُعلَّم ضريبةٌ «شاملةً» في «إعدادات الضريبة»
     * ({@see \App\Models\TaxSitting::comprehensiveDefault()}). عندها تُقرأ
     * الخانةُ العلوية `document_tax_id`:
     *   • قيمةٌ لضريبةٍ موجودة ⇒ تُفرَض على كلّ بند.
     *   • فارغةٌ («بدون ضريبة») ⇒ تُمسَح ضريبةُ كلّ بند.
     *   • غائبةٌ (نموذجٌ قديمٌ في المتصفّح) ⇒ يُفرَض الافتراضُ المعلَّم.
     *
     * بلا ضريبةٍ معلَّمة تعود دون مساس، فتبقى ضريبةُ كلّ سطر كما كانت.
     */
    public static function applyComprehensive(\Illuminate\Http\Request $request, string $field = 'document_tax_id'): void
    {
        $request->merge([
            'items' => self::comprehensiveItems($request->input('items', []), $request, $field),
        ]);
    }

    /**
     * يُعيد البنودَ بعد تطبيق الضريبة الشاملة عليها متى كانت مفعَّلة —
     * أو كما هي حين لا ضريبةَ معلَّمة.
     *
     * نسخةٌ لا تمسّ الطلب، لمن يقرأ بنودَه من مصفوفةٍ مصادَقٍ عليها
     * (`$validated`) لا من `$request->items` مباشرةً — كعرض السعر.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array<int|string, array<string, mixed>>
     */
    public static function comprehensiveItems(array $items, \Illuminate\Http\Request $request, string $field = 'document_tax_id'): array
    {
        $default = TaxSitting::comprehensiveDefault();

        if (! $default) {
            return $items; // الميزة مطفأة — تبقى ضريبةُ كلّ سطر
        }

        if ($request->has($field)) {
            $raw = $request->input($field);
            $chosenId = ($raw !== '' && $raw !== null && self::find($raw)) ? (int) $raw : null;
        } else {
            $chosenId = (int) $default->id;
        }

        return self::forceLineTaxes($items, $chosenId);
    }

    /**
     * حساب بند كامل.
     *
     * @param  array<int, mixed>  $taxIds  معرّفات الضرائب المطبَّقة (ضريبة أو اثنتان)
     * @param  array<int, float|null>  $rates  نِسَب البنود القديمة — تُستعمل فقط
     *                                         حين لا يوجد معرّف ضريبة للبند
     * @return array{gross: float, discount: float, base: float, net: float, tax: float, total: float, taxes: array<int, array{id: int|null, rate: float, included: bool, amount: float}>}
     */
    public static function line(float $quantity, float $unitPrice, float $discount, array $taxIds = [], array $rates = []): array
    {
        $gross = round($quantity * $unitPrice, 2);
        $discount = max(0.0, $discount);
        $base = max(0.0, round($gross - $discount, 2));

        $tax = 0.0;
        $includedTax = 0.0;
        $details = [];

        foreach ($taxIds as $index => $taxId) {
            $setting = self::find($taxId);
            // الإعداد هو المرجع حين يوجد: النسبة المرسلة من الشاشة قابلة
            // للعبث، ولا تُعتمد إلا حين لا يكون للبند معرّف ضريبة أصلاً
            $rate = $setting ? (float) $setting->tax : (float) ($rates[$index] ?? 0);
            $included = $setting ? $setting->type === 'included' : false;

            if ($rate <= 0) {
                continue;
            }

            $amount = self::amount($base, $rate, $included);

            $tax += $amount;
            $includedTax += $included ? $amount : 0.0;

            $details[] = [
                'id' => $setting->id ?? null,
                'rate' => $rate,
                'included' => $included,
                'amount' => $amount,
            ];
        }

        $tax = round($tax, 2);
        $includedTax = round($includedTax, 2);

        return [
            // الإجمالي قبل الخصم كما أُدخل
            'gross' => $gross,
            'discount' => round($discount, 2),
            // الوعاء الذي تُحسب عليه الضريبة
            'base' => $base,
            // الصافي بلا ضريبة — المتضمَّنة مخصومة منه
            'net' => round($base - $includedTax, 2),
            'tax' => $tax,
            // المتضمَّنة داخل المبلغ فلا تُضاف، والمضافة فوقه فتُضاف
            'total' => round($base + ($tax - $includedTax), 2),
            'taxes' => $details,
        ];
    }

    /**
     * ضريبة الشحن — بالنسبة المضبوطة، **مضافةً** فوق تكلفة الشحن.
     *
     * كانت المتحكّمات الستّة تكتبها `$shipping_cost * 0.15` حرفياً. فمنشأةٌ
     * نسبتها غير ١٥٪ — أو غيّرتها في «إعدادات الضريبة» — تبقى مستنداتها
     * تحمّل الشحن ١٥٪ أبداً، ويخرج القيد بضريبةٍ لا تطابق الإعداد. وشاشة
     * الإنشاء فيها حقلُ «ضريبة الشحن» (`shipping_tax_id`) لا يقرؤه الخادم
     * إطلاقاً: يختار المستخدم ضريبةً فتُهمَل.
     *
     * **مضافةٌ دائماً وإن كان نوع الضريبة «متضمَّنة».** تكلفة الشحن يكتبها
     * المستخدم بلا ضريبة — هكذا تحسبها شاشة الإنشاء
     * (`invoice-calculator.js`)، وهكذا يفهمها من يكتب «١٠٠ شحن». ولو
     * استُخرجت الضريبة من داخلها لانخفض إيراد الشحن المقيَّد بلا أن يطلب
     * ذلك أحد، ولاختلف المحفوظ عن المعروض.
     *
     * @param  array<int, mixed>  $taxIds  معرّفات مرشَّحة: ضريبة الشحن
     *                                     المختارة أوّلاً، ثمّ ضرائب البنود
     * @param  float|null  $legacyRate  نسبةٌ مرسلةٌ من شاشةٍ قديمة بلا معرّف
     */
    /**
     * المعرّفات المرشَّحة لضريبة الشحن — و«بلا ضريبة» اختيارٌ لا سكوت.
     *
     * كان كل مُتحكّم يمرّر `array_merge([$request->shipping_tax_id], $itemTaxIds)`،
     * فالفراغ يسقط من `find()` ويُلتقط أوّلُ ضريبةٍ على البنود. ومُنتقي
     * الشاشة خيارُه الأوّل مكتوبٌ فيه **«بلا ضريبة»** — فمن اختاره وجد
     * شحنَه مضروباً بالضريبة نفسِها: عرضٌ بمجموع ١٠٬٠٠٠ وشحنٍ ١٠٠ «بلا
     * ضريبة» يخرج بضريبة ١٬٥١٥ لا ١٬٥٠٠.
     *
     * فالتفريق هنا بين حالتين لا واحدة:
     *
     *   • الشاشة فيها المُنتقي وتُرك فارغاً ⇒ **لا ضريبة على الشحن**.
     *     هذا وعدُ النصّ المكتوب في الخيار، ونقضُه خطأ في المال لا في العرض.
     *   • الشاشة بلا مُنتقٍ أصلاً ⇒ الشحن يتبع أوّل ضريبةٍ على البنود،
     *     وهو السلوك القديم ولا شاشةَ تَعِد بغيره.
     *
     * @param  bool  $hasSelector  أفي الطلب مفتاحُ `shipping_tax_id` أصلاً؟
     * @param  mixed  $chosenId  ما اختاره المستخدم — فارغاً كان أو معرّفاً
     * @param  array<int, mixed>  $documentTaxIds  ضرائب البنود، للحالة الثانية
     * @return array<int, mixed>
     */
    public static function shippingTaxIds(bool $hasSelector, $chosenId, array $documentTaxIds): array
    {
        if ($hasSelector) {
            return $chosenId ? [$chosenId] : [];
        }

        return $documentTaxIds;
    }

    public static function shippingTax(float $shippingCost, array $taxIds, ?float $legacyRate = null): float
    {
        if ($shippingCost <= 0) {
            return 0.0;
        }

        foreach ($taxIds as $taxId) {
            if ($setting = self::find($taxId)) {
                return self::amount($shippingCost, (float) $setting->tax, false);
            }
        }

        return self::amount($shippingCost, (float) ($legacyRate ?? 0), false);
    }

    /**
     * ══════════════════════════════════════════════════════════════════
     * حصّةُ كلّ بندٍ من الخصم العامّ — لتُطرح من وعاء ضريبته
     * ══════════════════════════════════════════════════════════════════
     *
     * ترتيبُ الحساب المعتمَد في مستندات المبيعات كلِّها:
     *
     *     إجمالي الأصناف
     *       − خصومات الأصناف        ⇒ صافي الأصناف
     *       − الخصم العامّ           ⇒ صافي التوريد
     *       + الإضافات الخاضعة (الشحن)
     *                                ⇒ الوعاء الضريبي
     *       × النسبة                 ⇒ الضريبة
     *                                ⇒ الإجمالي النهائي
     *
     * ── لماذا توزيعٌ لا طرحٌ من المجموع ──
     *
     * الضريبةُ تُحسب لكلّ بندٍ بنسبته هو: قد يحمل المستندُ الواحد نسبتين،
     * وقد يكون فيه بندٌ معفى، وقد تكون ضريبةُ بندٍ «متضمَّنة» وأخرى
     * «مضافة». فطرحُ الخصم من المجموع لا يقول أيَّ وعاءٍ ينقص. يُوزَّع هنا
     * على البنود بنسبة صافي كلٍّ منها، ثمّ تُحسب ضريبةُ كلّ بندٍ على ما
     * بقي له: البندُ المعفى يأخذ حصّته ولا يُنتج ضريبة، وذو النسبة الأعلى
     * يُخفَّض وعاؤه بحصّته هو.
     *
     * ── وما الذي كان ──
     *
     * خصمُ البند يُنقص الوعاء والخصمُ العامّ لا يُنقصه، فالخصمُ الواحد
     * يُعطي فاتورتين مختلفتين حسب **أين كُتب**: بنودٌ ٤٧٨ وخصمٌ ٣٠ وشحنٌ
     * ١٠٠ بضريبة ١٥٪ ⇒ خصماً على البند إجماليُّها ٦٣٠٫٢٠، وخصماً على
     * المستند ٦٣٤٫٧٠. فرقُ ٤٫٥٠ ضريبةً على ثلاثين ريالاً تنازل عنها البائع.
     *
     * ولا يُوزَّع على الشحن: هو **إضافةٌ خاضعة** تأتي بعد الخصم في السلسلة،
     * وله ضريبتُه المختارة. ومن أراد إعفاءه فله كوبون «الشحن المجانيّ».
     *
     * @param  array<int|string, float>  $lineNets  صافي كلّ بند بعد خصمه الخاصّ
     * @param  float  $documentDiscount  الخصمُ العامّ وخصمُ الكوبون مجموعَين
     * @return array<int|string, float> مفتاحُ البند ⇒ حصّتُه من الخصم
     */
    public static function allocateDocumentDiscount(array $lineNets, float $documentDiscount): array
    {
        $shares = array_map(fn () => 0.0, $lineNets);

        if ($documentDiscount <= 0 || $lineNets === []) {
            return $shares;
        }

        $base = array_sum(array_map(fn ($net) => max(0.0, (float) $net), $lineNets));

        if ($base <= 0) {
            return $shares;
        }

        // لا يتجاوز الخصمُ ما يُخصَم منه: أكبرُ منه يقلب الوعاء سالباً
        $documentDiscount = min($documentDiscount, $base);

        $remaining = $documentDiscount;
        $keys = array_keys($lineNets);
        $last = end($keys);

        foreach ($lineNets as $key => $net) {
            $net = max(0.0, (float) $net);

            /* الكسرُ المتبقّي على آخر بندٍ لا يضيع: القسمةُ تُقرَّب لكلّ
               بند، فمجموعُ الحصص قد ينقص عن الخصم بهلّلةٍ أو هلّلتين. */
            $share = ($key === $last)
                ? $remaining
                : round($documentDiscount * $net / $base, 2);

            $shares[$key] = max(0.0, min($share, $net));
            $remaining = round($remaining - $shares[$key], 2);
        }

        return $shares;
    }

    /**
     * إجماليات مجموعة بنود محسوبة بـ`line()`.
     *
     * `subtotal` هو المجموع **قبل الضريبة وقبل الخصم**، أي أن الضريبة
     * المتضمَّنة مستخرَجة منه — وإلا لما استقام السطر المعروض للمستخدم:
     *
     *     الإجمالي = المجموع − الخصم + الضريبة
     *
     * @param  array<int, array>  $lines
     * @return array{subtotal: float, discount: float, tax: float, grand_total: float}
     */
    public static function totals(array $lines): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($lines as $line) {
            // الصافي + الخصم = الإجمالي قبل الخصم بلا ضريبة متضمَّنة
            $subtotal += ($line['net'] ?? 0) + ($line['discount'] ?? 0);
            $discount += $line['discount'] ?? 0;
            $tax += $line['tax'] ?? 0;
            $total += $line['total'] ?? 0;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'grand_total' => round($total, 2),
        ];
    }
}
