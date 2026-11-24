<?php

namespace Modules\Purchases\Support;

use App\Models\InvoiceItem;
use App\Models\PurchaseInvoice;
use App\Services\Tax\DocumentTaxCalculator;

/**
 * تكلفةُ البضاعة الداخلة بـ«استلام مشتريات» — صافيةً بعد الخصمين.
 *
 * ══════════════════ لماذا؟ ══════════════════
 *
 * القاعدةُ في هذا النظام مكتوبةٌ صراحةً في
 * `App\Services\Purchases\PurchaseDocumentTotals::compute()` وفي
 * `InvoicesPurchaseController::createReceiptAccountingEntry()`:
 *
 *   > القيمةُ من سطور الإذن لا من «كمية × سعر» البند: تلك تحمل الضريبةَ
 *   > المضافة وتتجاهل الخصمَ العامّ، فيدخل المستودعَ حسابياً أكثرُ ممّا
 *   > دخله فعلاً — والفرقُ يبقى معلَّقاً في حساب المشتريات بلا ما يفسّره.
 *
 * ومسارُ الاستلام كان يفعل بالضبط ما تحذّر منه تلك القاعدة: بندُ الإذن
 * و`PurchaseReceivingAccounting::receiptValue()` كلاهما
 * `الكمية المستلمة × سعر بند الأمر` — بلا خصمِ البند ولا الخصمِ العامّ.
 *
 * فأمرٌ مجموعُه ٣٤٩٥ وخصمُه ٣٠ يُنتج قيد استلامٍ بـ٣٤٩٥: المستودعُ أعلى
 * بثلاثين ممّا دُفع فيه، وحسابُ المشتريات لا يتقاصّ إلى صفر. وأثقلُ من
 * ذلك أنّ `warehouse_permits_products.total` هو وعاءُ التكلفة الذي يقرؤه
 * `Product::averageCost()` ومنه تُقيَّد تكلفةُ المبيعات — فالخصمُ الضائع
 * يتضخّم به المخزون ثمّ تتضخّم به تكلفةُ كلّ بيعةٍ لاحقة.
 *
 * ══════════════════ الحساب ══════════════════
 *
 * لكلّ سطرٍ مستلَم، وبنسبة ما استُلم من كمّية بند الأمر:
 *
 *   الإجمالي   = الكمية المستلمة × سعر بند الأمر
 *   − خصمُ البند بحصّة ما استُلم منه
 *   − حصّته من الخصم العامّ، موزَّعاً بنسبة صافي البنود (نفس
 *     `DocumentTaxCalculator::allocateDocumentDiscount`)
 *   − الضريبةُ المتضمَّنة في السعر إن كان نوعُها في «إعدادات الضريبة»
 *     `included` — فهي مستردّةٌ ومقيَّدةٌ في «ضريبة المشتريات»، ولا
 *     تُرسمَل في المخزون
 *
 * والتوزيعُ بالنسبة يعني أنّ استلاماتِ الأمر الواحد تقتسم خصمَه ولا
 * يأخذه كلٌّ منها كاملاً — كما تفعل
 * `PurchaseReceivingController::convertToInvoice()` مع الشحن وخصمِ المستند.
 */
class PurchaseReceivingCost
{
    /**
     * صافي تكلفة كلّ سطرٍ مستلَم، بترتيب المصفوفة الممرَّرة.
     *
     * @param  array<int|string, array{orderItem: InvoiceItem, quantity: float}>  $lines
     * @return array<int|string, float>
     */
    public static function netLineCosts(?PurchaseInvoice $order, array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        // وعاءُ توزيع الخصم العامّ: صافي بنود **الأمر كلِّه** بعد خصومها
        // الخاصّة — لا بنود هذا الاستلام وحده، وإلا أخذ كلُّ استلامٍ
        // الخصمَ كاملاً
        $orderNet = 0.0;

        foreach ($order?->items ?? [] as $orderItem) {
            $orderNet += self::itemNet($orderItem);
        }

        $documentDiscount = round((float) ($order?->discount_amount ?? 0), 2);

        $costs = [];

        foreach ($lines as $key => $line) {
            $orderItem = $line['orderItem'];
            $quantity = (float) $line['quantity'];
            $orderQuantity = (float) ($orderItem->quantity ?: 0);

            $gross = round($quantity * (float) $orderItem->unit_price, 2);

            // حصّةُ ما استُلم من خصم البند — البندُ قد يُستلم على دفعات
            $ratio = $orderQuantity > 0 ? min(1.0, $quantity / $orderQuantity) : 1.0;
            $lineDiscount = round((float) ($orderItem->discount ?? 0) * $ratio, 2);

            $net = max(0.0, round($gross - $lineDiscount, 2));

            // حصّتُه من الخصم العامّ بنسبة صافيه إلى صافي الأمر
            $share = ($documentDiscount > 0 && $orderNet > 0)
                ? round($documentDiscount * ($net / $orderNet), 2)
                : 0.0;

            $base = max(0.0, round($net - $share, 2));

            /* الضريبةُ المتضمَّنة تُستخرَج من الوعاء: نفس حسّاب المستندات،
               فلا يختلف ما يدخل المخزون عمّا يدخل قيدَ الفاتورة. ومعرّفا
               الضريبة هما المرجع حين يوجدان — والنسبةُ المحفوظة على البند
               بديلاً عنهما. */
            $line = DocumentTaxCalculator::line(
                1.0,
                $base,
                0.0,
                [$orderItem->tax_1_id, $orderItem->tax_2_id],
                [(float) $orderItem->tax_1, (float) $orderItem->tax_2],
            );

            $costs[$key] = round($line['net'], 2);
        }

        return $costs;
    }

    /** صافي بندِ أمرٍ كاملاً بعد خصمه الخاصّ — وعاءُ توزيع الخصم العامّ */
    private static function itemNet(InvoiceItem $orderItem): float
    {
        $gross = (float) $orderItem->quantity * (float) $orderItem->unit_price;

        return max(0.0, round($gross - (float) ($orderItem->discount ?? 0), 2));
    }
}
