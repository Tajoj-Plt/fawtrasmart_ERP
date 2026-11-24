<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'adjustment_id',
        'product_id',
        'quantity_in_system',
        'quantity_in_stock',
        'quantity_difference',
        'image',
        'note',
        'unit_price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function adjustment()
    {
        return $this->belongsTo(InventoryAdjustment::class, 'adjustment_id');
    }
}