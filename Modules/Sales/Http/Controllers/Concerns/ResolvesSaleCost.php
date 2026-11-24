<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\InvoiceItem;
use Modules\Pos\Http\Controllers\Concerns\ResolvesProductCost;

/**
 * تكلفةُ الوحدة في قيود المبيعات — **سعرُ التكلفة** لا سعرُ الشراء.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * قيدُ الفاتورة وقيدُ المرتجع وقيدُ الإشعار الدائن — ثلاثتُها — تنسخ
 * السلسلة نفسها: `product_details.unit_price` لآخر صفٍّ، ثم
 * `products.purchase_price`، ثم `AVG(unit_price)` غيرُ مرجَّح. أي
 * **سعرُ آخر شراء** في الغالب، لا متوسّطُ ما في المخزن. وشرحُ أعطال
 * كلِّ درجةٍ مكتوبٌ في `ResolvesProductCost` حيث أُصلحت نقاطُ البيع أوّلاً.
 *
 * ═════════════════════ وما صار ═════════════════════
 *
 * ١. البيع: `Product::averageCost()` — المتوسّطُ المرجَّح المعروض على بطاقة
 *    الصنف وفي تقارير المخزون — ويُجمَّد في `invoice_items.unit_cost_at_sale`.
 * ٢. المرتجعُ والإشعارُ الدائن: تكلفةُ **بند الفاتورة الأصلية** المجمَّدة؛
 *    فيعكس الردُّ ما قُيّد على البيع بالضبط، ولا يولّد ربحاً أو خسارةً
 *    من تغيّر المتوسّط بين البيع والردّ. فإن لم يكن للأصل تكلفةٌ مجمَّدة
 *    (فاتورةٌ أقدم من هذا التعديل، أو إشعارٌ بلا فاتورة) فالمتوسّطُ اليوم.
 */
trait ResolvesSaleCost
{
    use ResolvesProductCost;

    /**
     * تكلفةُ الوحدة لبندِ مرتجعٍ أو إشعارٍ دائن: المجمَّدةُ على بند الأصل
     * للمنتج نفسه، وإلّا متوسّطُ التكلفة الآن.
     */
    protected function returnUnitCost(?int $originalInvoiceId, $product): float
    {
        $productId = is_object($product) ? $product->id : $product;

        if ($originalInvoiceId && $productId) {
            $frozen = InvoiceItem::where('invoice_id', $originalInvoiceId)
                ->where('product_id', $productId)
                ->whereNotNull('unit_cost_at_sale')
                ->where('unit_cost_at_sale', '>', 0)
                ->value('unit_cost_at_sale');

            if ($frozen !== null) {
                return round((float) $frozen, 2);
            }
        }

        return $this->averageUnitCost($product) ?? 0.0;
    }

    /**
     * يكتب التكلفة المقيَّدة على البند إن لم تُكتب بعد — ليقرأها المرتجعُ
     * وتبويبُ الربح لاحقاً بدل متوسّطٍ تغيّر.
     */
    protected function freezeLineCost(InvoiceItem $item, float $unitCost): void
    {
        if ($unitCost > 0 && $item->unit_cost_at_sale === null) {
            InvoiceItem::whereKey($item->id)->update(['unit_cost_at_sale' => $unitCost]);
        }
    }
}
