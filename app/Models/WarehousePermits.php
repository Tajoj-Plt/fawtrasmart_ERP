<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehousePermits extends Model
{
    /*
        إذنُ المخزن وتسويةُ الجرد عمليتان محاسبيتان: كلٌّ منهما تُولّد قيداً
        يمسّ حساب المخزون. فتُختَم بفترتها لحظةَ الإنشاء كما تُختَم السندات.
    */
    use BelongsToAccountingPeriod;

    /** التاريخُ الحاكم لفترة هذا المستند. */
    public function accountingDateColumn(): string
    {
        return 'permission_date';
    }

    use HasDocumentCurrency, HasFactory;

    protected $table = 'warehouse_permits';

    /**
     * الإذن الجديد «معلَّق» حتى يُعتمَد.
     *
     * معظم مواضع الإنشاء في النظام لا تمرّر `status`، وهو عمودٌ إلزامي —
     * فكان الحفظ يسقط بخطأ SQL خام. القيمة هنا تحمي كل تلك المواضع،
     * ومَن يمرّر حالةً صريحة تبقى حالته.
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'permission_type',
        'permission_date',
        'reference_type',
        'reference_id',
        'permission_source_id',
        'client_id',
        'sub_account',
        'number',
        'store_houses_id',
        'from_store_houses_id',    // ✅ مع s
        'to_store_houses_id',      // ✅ مع s
        'grand_total',
        'currency',
        'details',                 // ✅ اسم العمود الصحيح في الجدول
        'attachments',
        'created_by',
        'status',
        'rejection_reason',
        'rejected_by',
        'rejected_at',

        'accounting_period_id',
    ];

    // العلاقات
    public function products()
    {
        return $this->belongsToMany(Product::class, 'warehouse_permits_products', 'warehouse_permits_id', 'product_id');
    }

    public function reference()
    {
        return $this->belongsTo(WarehousePermitsProducts::class, 'reference_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function warehousePermitsProducts()
    {
        return $this->hasMany(WarehousePermitsProducts::class, 'warehouse_permits_id');
    }

    public function items()
    {
        return $this->hasMany(WarehousePermitsProducts::class, 'warehouse_permits_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_houses_id');
    }

    public function fromStoreHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'from_store_houses_id');
    }

    public function toStoreHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'to_store_houses_id');
    }

    public function permissionSource()
    {
        return $this->belongsTo(PermissionSource::class, 'permission_source_id');
    }
}
