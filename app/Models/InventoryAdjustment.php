<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    /*
        إذنُ المخزن وتسويةُ الجرد عمليتان محاسبيتان: كلٌّ منهما تُولّد قيداً
        يمسّ حساب المخزون. فتُختَم بفترتها لحظةَ الإنشاء كما تُختَم السندات.
    */
    use BelongsToAccountingPeriod;

    /** التاريخُ الحاكم لفترة هذا المستند. */
    public function accountingDateColumn(): string
    {
        return 'inventory_time';
    }

    use HasDocumentCurrency;

    protected $fillable = [
        'stock_id',
        'inventory_time',
        'quantity_in_system',
        'quantity_in_stock',
        'quantity_difference',
        'status',
        'calculation_type',
        'reference_number',
        'created_by',
        'adjusted_by',
        'adjusted_at',
        'note',
        'currency',

        'accounting_period_id',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'adjustment_id');
    }

    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'stock_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adjuster()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
