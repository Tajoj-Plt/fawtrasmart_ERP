<?php

namespace Modules\Purchases\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * وحدات المنتجات لنموذج بنود المشتريات.
 *
 * شاشة فواتير الشراء وحدها كانت تعرض قائمة الوحدات، وبقيّة أقسام الدورة
 * (طلب الشراء، طلب عرض الأسعار، عرض السعر، أمر الشراء) تسأل عن الكمية
 * بلا وحدة — فلا يعرف من يقرأ المستند أهي حبّةٌ أم كرتون. هذا الصنف يجمع
 * البيانات مرّةً واحدة ليقرأها النموذج المشترك، بنفس ترتيب أولويات شاشة
 * الفواتير:
 *
 *   1. وحدات المنتج نفسه (`product_units`) إن عُرِّفت له
 *   2. وإلا وحدات صنفه (`category_unit`)
 *   3. وإلا فلا وحدات — فيبقى حقل الوحدة مخفيّاً كما كان
 *
 * `conversion_factor` هو معامل التحويل إلى الوحدة الأساسية: تُخزَّن الكمية
 * كما أدخلها المستخدم، ويُخزَّن معها ما يقابلها بالوحدة الأساسية
 * (`quantity_in_base_unit`) فلا يختلّ المخزون حين يُشترى بالكرتون ويُصرف
 * بالحبّة.
 */
class ProductUnits
{
    /**
     * خريطة: معرّف المنتج ← وحداته.
     *
     * تُحمَّل العلاقات هنا لا في المتحكّمات، فلا يتكرّر الاستعلام لكل بند
     * ولا يحتاج كل قسم إلى تذكّر ما يُحمَّل.
     *
     * @param  Collection<int, Product>|iterable  $products
     * @return array<int, array<int, array{id: ?int, category_unit_id: ?int, name: string, conversion_factor: float, is_default: bool, purchase_price: float}>>
     */
    public static function map($products): array
    {
        $ids = collect($products)->pluck('id')->filter()->all();

        if ($ids === []) {
            return [];
        }

        $loaded = Product::with([
            'productUnits.categoryUnit.unit',
            'category.categoryUnits.unit',
        ])->whereIn('id', $ids)->get();

        $map = [];

        foreach ($loaded as $product) {
            $units = self::forProduct($product);

            // المنتجات بلا وحدات لا تُثقل الخريطة المرسَلة إلى المتصفّح
            if ($units !== []) {
                $map[$product->id] = $units;
            }
        }

        return $map;
    }

    /**
     * سعر شراء **الوحدة المختارة** للبند — لا سعر الوحدة الأساسية.
     *
     * عرضُ السعر وطلبُ التسعير كانا يضربان الكمية في `products.purchase_price`
     * وهو سعر الحبّة: ثلاثون كرتوناً × ٠٫١٩ = ٥٫٧ بدل ثلاثين × ١٩١٫٣٠. فمن
     * اختار وحدةً غير الأساسية رأى إجمالياً أصغر بمقدار معامل التحويل.
     * الأسبقية: سعر الوحدة المسجَّل لها، وإلا سعر الأساسية × المعامل.
     *
     * @param  array<string, mixed>  $item  بند كما ورد من الطلب
     */
    public static function linePrice(array $item, Product $product): float
    {
        $factor = (float) ($item['conversion_factor'] ?? 1);
        $factor = $factor > 0 ? $factor : 1.0;
        $submitted = $item['product_unit_id'] ?? null;

        if (is_numeric($submitted)) {
            $unitPrice = (float) \App\Models\ProductUnit::whereKey((int) $submitted)
                ->where('product_id', $product->id)
                ->value('purchase_price');

            if ($unitPrice > 0) {
                return round($unitPrice, 4);
            }
        }

        return round((float) ($product->purchase_price ?? 0) * $factor, 4);
    }

    /**
     * حقول الوحدة الجاهزة للحفظ من بندٍ أرسلته الشاشة.
     *
     * قيمة الحقل في الشاشة إمّا معرّف صفٍّ في `product_units`، وإمّا
     * `cu:<id>` حين تكون الوحدة من وحدات الصنف بلا صفٍّ خاصّ بالمنتج —
     * وتلك لا معرّف لها يُخزَّن، فيبقى العمود فارغاً ويُحفظ اسم الوحدة
     * ومعاملها وحدهما.
     *
     * @param  array<string, mixed>  $item  بند كما ورد من الطلب
     * @return array{product_unit_id: ?int, unit_name: ?string, conversion_factor: float, quantity_in_base_unit: float}
     */
    public static function fieldsFor(array $item, float $quantity): array
    {
        $submitted = $item['product_unit_id'] ?? null;
        $factor = (float) ($item['conversion_factor'] ?? 1);

        // معامل صفر أو سالب يُفسد الكمية الأساسية — الوحدة الأساسية هي البديل
        if ($factor <= 0) {
            $factor = 1.0;
        }

        return [
            'product_unit_id' => is_numeric($submitted) ? (int) $submitted : null,
            'unit_name' => $item['unit_name'] ?? null,
            'conversion_factor' => $factor,
            'quantity_in_base_unit' => round($quantity * $factor, 4),
        ];
    }

    /**
     * وحدات منتج واحد — وحداته الخاصة، وإلا وحدات صنفه.
     *
     * @return array<int, array{id: ?int, category_unit_id: ?int, name: string, conversion_factor: float, is_default: bool, purchase_price: float}>
     */
    public static function forProduct(Product $product): array
    {
        $units = [];

        foreach ($product->productUnits ?? [] as $productUnit) {
            $categoryUnit = $productUnit->categoryUnit;

            if (! $categoryUnit || ! $categoryUnit->unit) {
                continue;
            }

            $units[] = [
                'id' => $productUnit->id,
                'category_unit_id' => $categoryUnit->id,
                'name' => $categoryUnit->unit->name,
                'conversion_factor' => (float) ($categoryUnit->conversion_factor ?: 1),
                'is_default' => (bool) $productUnit->is_default,
                // سعر شراء الوحدة إن سُجِّل لها، وإلا سعر شراء المنتج
                'purchase_price' => (float) ($productUnit->purchase_price ?: 0),
            ];
        }

        if ($units !== []) {
            return $units;
        }

        foreach ($product->category->categoryUnits ?? [] as $categoryUnit) {
            if (! $categoryUnit->unit) {
                continue;
            }

            $units[] = [
                // لا صفّ في `product_units` لهذه الوحدة، فلا معرّف يُخزَّن
                'id' => null,
                'category_unit_id' => $categoryUnit->id,
                'name' => $categoryUnit->unit->name,
                'conversion_factor' => (float) ($categoryUnit->conversion_factor ?: 1),
                'is_default' => (bool) ($categoryUnit->is_base_unit ?? false),
                'purchase_price' => 0.0,
            ];
        }

        return $units;
    }
}
