<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * استلام المشتريات — مستندٌ بين أمر الشراء وفاتورة الشراء، يسجّل ما دخل
 * المستودع فعلاً من أمرٍ معتمَد. انظر توثيق الجدول في هجرة الإنشاء
 * وتوثيق `Modules\Purchases\Support\PurchaseFulfillmentLedger` لحساب
 * الكميات المتبقية للاستلام وللفوترة.
 */
class PurchaseReceiving extends Model
{
    protected $table = 'purchase_receivings';

    protected $fillable = [
        'code', 'purchase_order_id', 'supplier_id', 'store_house_id', 'receiving_date',
        'status', 'warehouse_permit_id', 'branch_id', 'notes',
        'created_by', 'updated_by', 'approved_by', 'approved_at', 'cancelled_by', 'cancelled_at',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceivingItem::class, 'purchase_receiving_id');
    }

    public function warehousePermit()
    {
        return $this->belongsTo(WarehousePermits::class, 'warehouse_permit_id');
    }

    public function invoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'purchase_receiving_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /** لا تعديل ولا حذف إلا لاستلامٍ لم يُعتمد بعد */
    public function isEditable(): bool
    {
        return $this->status === 'pending';
    }
}
