<?php

namespace Modules\Purchases\Support;

use App\Enums\PurchaseDocumentType;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

/**
 * الكمّيةُ المتبقّية للارتجاع من فاتورة مشتريات — الفرقُ بين ما فُوتِر
 * وما رُدّ فعلاً (مرتجعاً **أو** إشعاراً دائناً)، صنفاً بصنف.
 *
 * كانت شاشة «الإشعار الدائن» تستبعد الفاتورةَ كلَّها بمجرّد وجود مرتجعٍ
 * واحدٍ لها (`whereDoesntHave('relatedDocuments', type=Return)`) — قاعدةٌ
 * صحيحةٌ في وضع «مرتجعٌ واحد لكل فاتورة» (الافتراضي)، لكنها خاطئةٌ حين
 * يُفعَّل إعداد «السماح بأكثر من مرتجع»: فاتورةٌ رُدَّ نصفُ كمّيتها تبقى
 * قابلةً للنصف الآخر، وكانت تختفي من القائمة كاملةً.
 *
 * فالمعيارُ هنا الكمّيةُ لا الوجود: صنفٌ استُنفدت كمّيتُه (مرتجعاً أو
 * إشعاراً) يُستبعَد وحده، لا الفاتورةُ كلُّها.
 *
 * @see \Modules\Purchases\Http\Controllers\ReturnsInvoiceController::existingReturnFor() القاعدة الأخرى («مرتجعٌ واحد») — مستقلّةٌ عن هذه ولا تُستبدَل بها
 */
class ReturnableInvoiceLines
{
    /** فرقٌ في الكمية دون هذا يُعدّ صفراً — الكمية تمرّ بالنصّ ذهاباً وإياباً */
    private const TOLERANCE = 0.0001;

    /**
     * الكمّيةُ المتبقّية لكلّ صنفٍ في الفاتورة — بعد خصم ما رُدّ منه.
     *
     * `quantity_in_base_unit` تُقاس بها لا `quantity` الخام: الفاتورةُ قد
     * تُسجَّل بوحدةٍ ومرتجعُها بأخرى (كرتونة مقابل حبّة)، فالمقارنةُ على
     * الوحدة الأساس وحدها تصحّ.
     *
     * @return array<int, float> product_id => الكمّية المتبقّية
     */
    public static function remainingByProduct(int $invoiceId): array
    {
        $sumByProduct = function (iterable $documentIds) {
            if (empty($documentIds)) {
                return collect();
            }

            return DB::table('invoice_items')
                ->whereIn('purchase_invoice_id', $documentIds)
                ->whereNotNull('product_id')
                ->selectRaw('product_id, SUM(COALESCE(quantity_in_base_unit, quantity)) as qty')
                ->groupBy('product_id')
                ->pluck('qty', 'product_id');
        };

        $invoiced = $sumByProduct([$invoiceId]);

        $returnDocumentIds = PurchaseInvoice::where('reference_id', $invoiceId)
            ->whereIn('type', [PurchaseDocumentType::RETURN->value, PurchaseDocumentType::DEBIT_NOTE->value])
            ->pluck('id')
            ->all();

        $returned = $sumByProduct($returnDocumentIds);

        return $invoiced->map(fn ($qty, $productId) => max(0.0, (float) $qty - (float) ($returned[$productId] ?? 0)))
            ->all();
    }

    /** هل بقي في الفاتورة صنفٌ واحدٌ على الأقلّ له كمّيةٌ قابلة للارتجاع؟ */
    public static function hasRemainingQuantity(int $invoiceId): bool
    {
        foreach (self::remainingByProduct($invoiceId) as $qty) {
            if ($qty > self::TOLERANCE) {
                return true;
            }
        }

        return false;
    }

    /**
     * فواتير موردٍ بعينه، بنوع «فاتورة» فقط، وبقيت فيها كمّيةٌ قابلة
     * للارتجاع — لبناء قائمة الاختيار في شاشتَي المرتجع والإشعار الدائن.
     *
     * الفحصُ على مستوى القاعدة أوّلاً (فاتورةٌ بلا أيّ مستند ارتجاعٍ عليها
     * مؤهَّلةٌ يقيناً بلا حساب) ثمّ فلترةُ الباقي بالكمّية الفعلية — فلا
     * يُحسَب فرقُ الكمّية إلا لما يحتاجه فعلاً.
     */
    public static function forSupplier(int $supplierId): \Illuminate\Support\Collection
    {
        return PurchaseInvoice::where('supplier_id', $supplierId)
            ->where('type', PurchaseDocumentType::INVOICE->value)
            ->withCount(['relatedDocuments as returns_count' => function ($q) {
                $q->whereIn('type', [PurchaseDocumentType::RETURN->value, PurchaseDocumentType::DEBIT_NOTE->value]);
            }])
            ->orderByDesc('id')
            ->get(['id', 'code', 'grand_total', 'date', 'supplier_id'])
            ->filter(fn (PurchaseInvoice $invoice) => $invoice->returns_count === 0 || self::hasRemainingQuantity($invoice->id))
            ->values();
    }
}
