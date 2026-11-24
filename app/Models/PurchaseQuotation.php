<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PurchaseQuotation extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'code',
        'order_date',
        'due_date',
        'order_id',
        'status',
        'notes',
        'attachments',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date'
    ];

    /**
     * بنود طلب عرض الأسعار.
     *
     * كانت العلاقة تشير إلى `ProductDetails` بينما المتحكّم يكتب البنود في
     * `InvoiceItem` (انظر `QuotationsController::store`) — فالقسم يكتب في
     * جدول ويقرأ من آخر: البنود لا تظهر في شاشة العرض ولا في المطبوعات،
     * و`items()->delete()` عند الحذف لا يمسّ شيئاً فتبقى بنود يتيمة.
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'purchase_quotation_id');
    }

    // العلاقة مع طلب الشراء
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    // العلاقة مع الموردين
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'purchase_quotation_supplier', 'purchase_quotation_id', 'supplier_id')
            ->withTimestamps();
    }
    // العلاقة مع المستخدم الذي أنشأ الطلب
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // العلاقة مع المستخدم الذي عدل الطلب
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scope للبحث حسب الكود
    public function scopeByCode($query, $code)
    {
        return $query->where('code', 'like', "%{$code}%");
    }

    // Scope للبحث حسب التاريخ
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        if ($startDate && $endDate) {
            return $query->whereBetween('order_date', [$startDate, $endDate]);
        }
        return $query;
    }


    // العلاقة مع جدول العلاقة مع الموردين
    public function supplierRelations()
    {
        return $this->hasMany(PurchaseQuotationSupplier::class, 'purchase_quotation_id');
    }
    public   function  orderPurchases()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
public function generatedQuotations()
{
    return $this->hasMany(\App\Models\PurchaseQuotationView::class, 'quotation_id');
}

}
