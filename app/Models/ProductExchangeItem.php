<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductExchangeItem extends Model
{
    use HasFactory;

    protected $table = 'product_exchange_items';

    protected $fillable = [
        'product_exchange_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
        'reason_id',
        'reason_notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // العلاقات
    public function productExchange()
    {
        return $this->belongsTo(ProductExchange::class, 'product_exchange_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function exchangeReason()
    {
        return $this->belongsTo(ExchangeReason::class, 'reason_id');
    }

    // الحصول على نص السبب - متوافق مع النظام القديم والجديد
    public function getReasonTextAttribute()
    {
        // النظام الجديد: reason_id موجود
        if (!empty($this->reason_id)) {
            $reasonModel = $this->exchangeReason;
            if ($reasonModel) {
                return $reasonModel->name;
            }
        }
        
        // النظام القديم: reason كـ string/enum
        $oldReason = $this->getOriginal('reason') ?? ($this->attributes['reason'] ?? null);
        if ($oldReason && is_string($oldReason)) {
            $oldReasons = [
                'damaged' => 'تالفة',
                'missing_perfume' => 'العطر ناقص',
                'defective' => 'معيبة',
                'other' => 'أخرى',
            ];
            return $oldReasons[$oldReason] ?? $oldReason;
        }
        
        return 'غير محدد';
    }
}
