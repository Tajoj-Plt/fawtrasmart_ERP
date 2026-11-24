<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTarget extends Model
{
    protected $fillable = [
        'name',
        'color',
        'description',
        'sort_order',
        'is_active',
        'min_sales',
        'min_collections',
        'min_collection_ratio',
        'max_avg_collection_days',
        'max_late_ratio',
        'max_debt',
        'min_invoices_count',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_sales' => 'decimal:2',
        'min_collections' => 'decimal:2',
        'min_collection_ratio' => 'decimal:2',
        'max_avg_collection_days' => 'integer',
        'max_late_ratio' => 'decimal:2',
        'max_debt' => 'decimal:2',
        'min_invoices_count' => 'integer',
        'priority' => 'integer',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class, 'customer_target_id');
    }
}
