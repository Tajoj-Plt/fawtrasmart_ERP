<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToBranch;

    protected $table = 'purchase_orders';

    /**
     * الحقول القابلة للتعبئة
     * يتضمن branch_id لربط الأمر بالفرع — يُعيّن تلقائياً من فرع
     * المستخدم/الموظف عند الإنشاء
     */
    protected $fillable = [
        'title', 'code', 'order_date', 'due_date', 'notes', 'attachments',
        'status', 'created_by', 'updated_by', 'branch_id'
    ];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date',
    ];

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * العلاقات
     * ═══════════════════════════════════════════════════════════════════════
     */

    /**
     * المستخدم الذي أنشأ الطلب
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستخدم الذي عدّل الطلب آخر مرة
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * الموظف الذي أنشأ الطلب (عبر المستخدم منشئه) — دالة مساعدة عادية لا
     * علاقة Eloquent (لا شيء في المشروع يحمّلها بـ with()), فتتفادى الانهيار
     * حين يكون created_by فارغاً أو المستخدم المُنشئ محذوفاً.
     */
    public function employee(): ?Employee
    {
        return $this->creator?->employee;
    }

    /**
     * عناصر الطلب (البنود) — المنتجات والكميات والأسعار
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'purchase_order_id');
    }

    /**
     * تفاصيل المنتجات المرتبطة (قد تكون علاقة قديمة)
     */
    public function productDetails()
    {
        return $this->hasMany(ProductDetails::class, 'purchase_order_id');
    }
}