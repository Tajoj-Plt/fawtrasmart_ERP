<?php

namespace Modules\Sales\Support;

use Illuminate\Support\Collection;

/**
 * وحدات المنتجات لشاشات مستندات المبيعات.
 *
 * ─────────────────── لماذا صنفٌ مستقلّ ───────────────────
 *
 * شاشةُ الفاتورة وحدها كانت تعرض قائمة الوحدات، وبنيتُها ستّون سطراً
 * مكتوبةً داخل `InvoicesController::create()`. فالإشعارُ الدائن وعرضُ السعر
 * يسألان عن الكمية بلا وحدة — فلا يعرف من يقرأ المستند أهي حبّةٌ أم كرتون،
 * ولا يُحسَب المخزون بما يقابلها في الوحدة الأساسية.
 *
 * ونسخُ تلك الستّين سطراً إلى كلّ شاشةٍ يعني أربع نسخٍ تتباعد مع أوّل
 * تصحيح — وهو ما وقع فعلاً بين الفاتورة والفاتورة الدورية.
 *
 * ولا يُستدعى نظيرُه في وحدة المشتريات (`Modules\Purchases\Support\ProductUnits`):
 * وحدةٌ معطَّلة في `modules_statuses.json` تُسقط أصنافَها، فشاشةُ مبيعاتٍ
 * تعتمد على المشتريات تنكسر بإطفائها.
 *
 * ─────────────────── ترتيب الأولويات ───────────────────
 *
 *   ١. وحدات المنتج نفسِه (`product_units`) إن عُرِّفت له
 *   ٢. وإلّا وحدات صنفه (`category_unit`)
 *   ٣. وإلّا فلا وحدات — فيبقى حقلُ الوحدة **مخفيّاً** لا فارغاً
 *
 * والثالثةُ هي المقصودة بطلب «إذا المنتج عنده وحدة تظهره»: القائمةُ لا
 * تظهر لمنتجٍ بلا وحدات، فلا يُسأل المستخدم عن اختيارٍ لا خيارَ فيه.
 *
 * ─────────────────── معامل التحويل ───────────────────
 *
 * `conversion_factor` ما يقابل الوحدةَ الواحدة بالوحدة الأساسية. تُخزَّن
 * الكميةُ كما أدخلها المستخدم، ويُخزَّن معها `quantity_in_base_unit` —
 * فلا يختلّ المخزون حين يُباع بالكرتون ويُصرَف بالحبّة.
 */
class ProductUnits
{
    /**
     * المنتجاتُ بوحداتها وأسعارها — كما تنتظرها سكربتات شاشات المستندات.
     *
     * الشكلُ مطابقٌ لِما كان يبنيه `InvoicesController::create()` حرفاً
     * بحرف، فالسكربتُ القائم في شاشة الفاتورة يعمل عليه بلا تعديل.
     *
     * @param  Collection|iterable  $products  منتجاتٌ محمَّلةٌ علاقاتُها
     * @return array<int, array<string, mixed>>
     */
    public static function map($products): array
    {
        return collect($products)->map(function ($product) {
            $units = self::forProduct($product);

            $available = 0;

            if ($product->productDetails && $product->productDetails->count() > 0) {
                $available = (float) $product->productDetails->sum('quantity');
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku ?? null,
                'barcode' => $product->barcode ?? null,
                'sale_price' => (float) ($product->sale_price ?? 0),
                'purchase_price' => (float) ($product->purchase_price ?? 0),
                'available_quantity' => $available,

                // `has_units` علَمٌ صريح لا `count($units)` في السكربت:
                // الشرطُ يُقرأ في ثلاثة مواضع، وكتابتُه ثلاثاً تتباعد
                'has_units' => $units !== [],
                'units' => $units,
            ];
        })->values()->all();
    }

    /**
     * وحداتُ منتجٍ واحد بترتيب الأولويات المشروح أعلاه.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forProduct($product): array
    {
        $units = [];

        // ١) وحدات المنتج نفسِه
        if ($product->productUnits && $product->productUnits->count() > 0) {
            foreach ($product->productUnits as $productUnit) {
                if (! $productUnit->categoryUnit || ! $productUnit->categoryUnit->unit) {
                    continue;
                }

                $units[] = [
                    'id' => $productUnit->id,
                    'category_unit_id' => $productUnit->category_unit_id ?? null,
                    'name' => $productUnit->categoryUnit->unit->name,
                    'conversion_factor' => (float) $productUnit->categoryUnit->conversion_factor,
                    'is_base_unit' => (bool) ($productUnit->categoryUnit->is_base_unit ?? false),
                    'is_default' => (bool) $productUnit->is_default,
                    'sale_price' => (float) $productUnit->sale_price,
                    'purchase_price' => (float) $productUnit->purchase_price,
                    'barcode' => $productUnit->barcode,
                ];
            }

            return $units;
        }

        // ٢) وحدات الصنف — لا معرّف لها في `product_units`، فيُرسَل `null`
        //    ويُميَّز الخيار في الشاشة بـ`category_unit_id`
        if ($product->category && $product->category->categoryUnits) {
            foreach ($product->category->categoryUnits as $categoryUnit) {
                if (! $categoryUnit->unit) {
                    continue;
                }

                $units[] = [
                    'id' => null,
                    'category_unit_id' => $categoryUnit->id,
                    'name' => $categoryUnit->unit->name,
                    'conversion_factor' => (float) $categoryUnit->conversion_factor,
                    'is_base_unit' => (bool) ($categoryUnit->is_base_unit ?? false),
                    // الوحدة الأساسية هي الافتراضية حين لا يُعرَّف للمنتج
                    // تفضيلٌ خاصّ به
                    'is_default' => (bool) ($categoryUnit->is_base_unit ?? false),
                    'sale_price' => (float) ($product->sale_price ?? 0),
                    'purchase_price' => (float) ($product->purchase_price ?? 0),
                    'barcode' => null,
                ];
            }
        }

        // ٣) لا وحدات — تُردّ فارغةً ليبقى الحقل مخفيّاً
        return $units;
    }

    /**
     * حقولُ الوحدة الجاهزة للحفظ من بندٍ أرسلته الشاشة.
     *
     * قيمةُ الحقل في الشاشة إمّا معرّفُ صفٍّ في `product_units`، وإمّا
     * `category_<id>` حين تكون الوحدة من وحدات الصنف بلا صفٍّ خاصّ
     * بالمنتج — وتلك لا معرّف لها يُخزَّن، فيبقى العمود فارغاً ويُحفظ اسمُ
     * الوحدة ومعاملُها وحدهما.
     *
     * **ولمَ تُبحَث `ProductUnit` أوّلاً في حالة `category_`؟** لأنّ المنتج
     * قد يكون له صفٌّ حقيقيّ على وحدة الصنف نفسِها؛ فاستعمالُ معرّفه يُبقي
     * الرابط قائماً بدل حفظ اسمٍ ومعاملٍ مفصولين عن مصدرهما.
     *
     * @param  array<string, mixed>  $item  بندٌ كما أرسلته الشاشة
     * @return array{product_unit_id: ?int, conversion_factor: float, unit_name: string}
     */
    public static function resolve(array $item): array
    {
        $productUnitId = null;
        $factor = 1.0;
        $name = '';

        $raw = $item['product_unit_id'] ?? null;

        if (! empty($raw)) {
            if (is_string($raw) && str_starts_with($raw, 'category_')) {
                $categoryUnitId = (int) substr($raw, strlen('category_'));

                $matched = \App\Models\ProductUnit::with('categoryUnit.unit')
                    ->where('product_id', $item['product_id'] ?? null)
                    ->where('category_unit_id', $categoryUnitId)
                    ->first();

                if ($matched) {
                    $productUnitId = $matched->id;
                    $factor = (float) ($matched->categoryUnit->conversion_factor ?? 1) ?: 1.0;
                    $name = $matched->categoryUnit->unit->name ?? '';
                } else {
                    $categoryUnit = \App\Models\CategoryUnit::with('unit')->find($categoryUnitId);
                    $name = $categoryUnit?->unit?->name ?? '';
                    $factor = (float) ($categoryUnit?->conversion_factor ?? 1) ?: 1.0;
                }
            } else {
                $productUnitId = (int) $raw;
                $productUnit = \App\Models\ProductUnit::with('categoryUnit.unit')->find($productUnitId);

                if ($productUnit && $productUnit->categoryUnit) {
                    $factor = (float) $productUnit->categoryUnit->conversion_factor ?: 1.0;
                    $name = $productUnit->categoryUnit->unit->name ?? '';
                }
            }
        }

        /* معاملٌ مرسَلٌ من الشاشة لا يُقبل: كان «يُحترَم كما هو» حين لا وحدة، فبندٌ
           بمعامل ٠٫٠٠١ يخصم من المخزون وتكلفة المبيعات جزءاً من الألف ممّا بِيع.
           المعامل يُقرأ من الوحدة وحدها، وبلا وحدةٍ فهو ١. */

        // اسمُ الوحدة قد يصل من الشاشة حين لا يُعثَر على مصدره في القاعدة
        if ($name === '' && ! empty($item['unit_name'])) {
            $name = (string) $item['unit_name'];
        }

        return [
            'product_unit_id' => $productUnitId,
            'conversion_factor' => $factor,
            'unit_name' => $name,
        ];
    }

    /** العلاقاتُ اللازمة لبناء الخريطة بلا استعلامٍ لكلّ منتج. */
    public static function relations(): array
    {
        return [
            'productUnits.categoryUnit.unit',
            'category.categoryUnits.unit',
            'productDetails',
        ];
    }
}
