<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductExchange extends Model
{
    use HasFactory;
    use \App\Traits\BelongsToBranch;

    protected $table = 'product_exchanges';

    protected $fillable = [
        'code',
        'client_id',
        'branch_id',
        'employee_id',
        'storehouse_id',
        'return_storehouse_id',
        'created_by',
        'exchange_date',
        'notes',
        'image',
        'total_value',
        'status',
        'outgoing_permit_id',
        'incoming_permit_id'
    ];

    protected $casts = [
        'exchange_date' => 'date',
        'total_value' => 'decimal:2',
        'status' => 'integer',
    ];

    // العلاقات
    public function items()
    {
        return $this->hasMany(ProductExchangeItem::class, 'product_exchange_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function storehouse()
    {
        return $this->belongsTo(StoreHouse::class, 'storehouse_id');
    }

    public function returnStorehouse()
    {
        return $this->belongsTo(StoreHouse::class, 'return_storehouse_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // علاقة مع إذن الصرف (صادر)
    public function outgoingPermit()
    {
        return $this->belongsTo(WarehousePermits::class, 'outgoing_permit_id');
    }

    // علاقة مع إذن الإضافة (وارد)
    public function incomingPermit()
    {
        return $this->belongsTo(WarehousePermits::class, 'incoming_permit_id');
    }

    // Scopes للتقارير
    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeByStorehouse($query, $storehouseId)
    {
        return $query->where('storehouse_id', $storehouseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('exchange_date', [$startDate, $endDate]);
    }

    // أسباب التبديل المتاحة
    public static function getReasons()
    {
        return [
            'damaged' => 'تالفة',
            'missing_perfume' => 'العطر ناقص',
            'defective' => 'معيبة',
            'other' => 'أخرى'
        ];
    }

    // توليد كود تلقائي
    public static function generateCode()
    {
        $lastExchange = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastExchange ? intval($lastExchange->id) + 1 : 1;
        return 'EX-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
