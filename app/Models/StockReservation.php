<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * حجزُ كميةٍ من منتجٍ في مستودعٍ لصالح مستند.
 *
 *     المتاح  =  الرصيد الفعلي  −  المحجوز النشط
 *
 * الحسابُ نفسُه في [\Modules\Manufacturing\Services\ReservationService].
 */
class StockReservation extends Model
{
    /** حجزٌ قائم — يُخصم من المتاح. */
    public const STATUS_ACTIVE = 'active';

    /** صُرفت مادتُه فعلاً، فصار حركةَ مخزونٍ لا وعداً. */
    public const STATUS_CONSUMED = 'consumed';

    /** فُكّ بلا صرف — إلغاءٌ أو حذف. */
    public const STATUS_RELEASED = 'released';

    protected $table = 'stock_reservations';

    protected $fillable = [
        'product_id', 'store_house_id', 'quantity',
        'reference_type', 'reference_id',
        'status', 'released_at', 'consumed_at', 'note', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    /** الحجوزُ التي ما تزال تُخصم من المتاح. */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
