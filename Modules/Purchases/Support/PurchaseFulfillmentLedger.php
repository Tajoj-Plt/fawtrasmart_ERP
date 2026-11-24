<?php

namespace Modules\Purchases\Support;

use App\Models\InvoiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReceivingItem;
use Illuminate\Support\Collection;

/**
 * دفتر تتبّع أمر الشراء عبر دورة الاستلام والفوترة — كم طُلب، كم استُلم،
 * كم فُوتر، وكم بقي من كلٍّ. هو المصدر الوحيد لهذه الأرقام؛ لا تُحسب في
 * كنترولر ولا في Blade بمعزلٍ عنه.
 *
 * يخالف `ReceivingLedger` (تقرير المشتريات مقابل المستلم من الفواتير):
 * ذاك يستنتج الاستلام من الأذون المخزنية بمقارنةٍ حسابية لأن لا عمود
 * «كمية مستلمة» كان موجوداً. هذا الصنف يقرأ من عمودين حقيقيين
 * (`purchase_receiving_items.received_quantity`/`invoiced_quantity`)
 * أضافتهما هجرة الاستلام — فهو المصدر لمحور أمر الشراء ← استلام ← فاتورة
 * تحديداً، ولا يُغني عن `ReceivingLedger` في سؤاله هو.
 */
class PurchaseFulfillmentLedger
{
    /** سماحية التقريب عند مقارنة كمّيات عشرية */
    private const EPS = 0.0009;

    /**
     * تفصيل كل بند من بنود أمر الشراء: المطلوب، المستلم، المتبقي للاستلام،
     * المفوتر، والمتبقي للفوترة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forOrder(PurchaseInvoice $order): Collection
    {
        $items = InvoiceItem::where('purchase_invoice_id', $order->id)->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $sums = PurchaseReceivingItem::query()
            ->join('purchase_receivings', 'purchase_receivings.id', '=', 'purchase_receiving_items.purchase_receiving_id')
            ->whereIn('purchase_receiving_items.purchase_order_item_id', $items->pluck('id'))
            ->where('purchase_receivings.status', '!=', 'cancelled')
            ->selectRaw('purchase_receiving_items.purchase_order_item_id as purchase_order_item_id,
                SUM(CASE WHEN purchase_receivings.status = "approved" THEN purchase_receiving_items.received_quantity ELSE 0 END) as received,
                SUM(purchase_receiving_items.invoiced_quantity) as invoiced')
            ->groupBy('purchase_receiving_items.purchase_order_item_id')
            ->get()
            ->keyBy('purchase_order_item_id');

        return $items->map(function (InvoiceItem $item) use ($sums) {
            $ordered = (float) $item->quantity;
            $row = $sums->get($item->id);
            $received = round((float) ($row->received ?? 0), 4);
            $invoiced = round((float) ($row->invoiced ?? 0), 4);

            return [
                'item' => $item,
                'ordered' => $ordered,
                'received' => $received,
                'remaining_to_receive' => max(0, round($ordered - $received, 4)),
                'invoiced' => $invoiced,
                'remaining_to_invoice' => max(0, round($received - $invoiced, 4)),
            ];
        });
    }

    /** إجماليات أمر الشراء — مجموع بنوده أعلاه */
    public static function orderTotals(PurchaseInvoice $order): array
    {
        $lines = self::forOrder($order);

        return [
            'ordered' => round($lines->sum('ordered'), 4),
            'received' => round($lines->sum('received'), 4),
            'remaining_to_receive' => round($lines->sum('remaining_to_receive'), 4),
            'invoiced' => round($lines->sum('invoiced'), 4),
            'remaining_to_invoice' => round($lines->sum('remaining_to_invoice'), 4),
        ];
    }

    /**
     * الكمية القابلة للاستلام من بند أمر شراء الآن — المطلوب ناقص كل ما
     * التزمت به استلاماتٌ لم تُلغَ (معلَّقة أو معتمدة)، فلا يتجاوزها
     * استلامٌ جديد ولو لم تُعتمد الاستلامات السابقة بعد.
     */
    public static function remainingToReceive(InvoiceItem $orderItem): float
    {
        $committed = PurchaseReceivingItem::query()
            ->where('purchase_order_item_id', $orderItem->id)
            ->whereHas('receiving', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->sum('received_quantity');

        return max(0, round((float) $orderItem->quantity - (float) $committed, 4));
    }

    /** الكمية المستلمة من هذا السطر التي لم تُفوتَر بعد */
    public static function remainingToInvoice(PurchaseReceivingItem $receivingItem): float
    {
        return $receivingItem->remainingToInvoice();
    }

    /** حالة الاستلام محسوبةً من الكمّيتين — نفس مفردات `receiving_status` القائم */
    public static function deriveReceivingStatus(float $ordered, float $received): string
    {
        if ($received <= self::EPS) {
            return 'not_received';
        }

        if ($received + self::EPS >= $ordered) {
            return 'received';
        }

        return 'partially_received';
    }

    /**
     * يُعيد حساب `receiving_status` لأمر الشراء ويحفظه — يُستدعى بعد كل
     * اعتماد أو إلغاء استلام حتى لا يبقى العمود متأخراً عن الواقع.
     */
    public static function syncOrderReceivingStatus(PurchaseInvoice $order): void
    {
        $totals = self::orderTotals($order);

        $order->update([
            'receiving_status' => self::deriveReceivingStatus($totals['ordered'], $totals['received']),
        ]);
    }
}
