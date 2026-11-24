<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyClosingStockDetail extends Model
{
    protected $table = 'daily_closing_stock_details';

    protected $fillable = [
        'daily_closing_id',
        'product_id',
        'product_name',
        'reported_quantity',
        'system_quantity',
    ];

    public function dailyClosing()
    {
        return $this->belongsTo(DailyClosing::class, 'daily_closing_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
