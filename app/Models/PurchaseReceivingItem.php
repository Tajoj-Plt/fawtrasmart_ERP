<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سطر استلام — كميةٌ استُلمت من بندٍ واحد في أمر الشراء
 * (`purchase_order_item_id` يشير إلى صفٍّ في `invoice_items`).
 *
 * لا سعر ولا وحدة هنا: تُقرآن من بند الأمر حيّتين وقت التحويل إلى فاتورة.
 * `invoiced_quantity` هو مجموع ما فُوتر من هذا السطر حتى الآن عبر أيّ عدد
 * من الفواتير الجزئية — يزيد عند كل تحويل ولا يُعاد أبداً بلا إلغاء الفاتورة.
 */
class PurchaseReceivingItem extends Model
{
    protected $table = 'purchase_receiving_items';

    protected $fillable = [
        'purchase_receiving_id', 'purchase_order_item_id', 'product_id',
        'received_quantity', 'invoiced_quantity', 'notes',
    ];

    protected $casts = [
        'received_quantity' => 'decimal:4',
        'invoiced_quantity' => 'decimal:4',
    ];

    public function receiving()
    {
        return $this->belongsTo(PurchaseReceiving::class, 'purchase_receiving_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'purchase_order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** الكمية المستلمة التي لم تُفوتَر بعد من هذا السطر */
    public function remainingToInvoice(): float
    {
        return round((float) $this->received_quantity - (float) $this->invoiced_quantity, 4);
    }
}
