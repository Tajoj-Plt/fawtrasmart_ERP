<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemCurrency extends Model
{
    protected $table = 'system_currencies';

    protected $fillable = [
        'code',
        'numeric_code',
        'name',
        'arabic_name',
        'english_name',
        'symbol',
        'native_symbol',
        'exchange_rate',
        'rate_source',
        'country',
        'flag',
        'decimal_places',
        'rounding_method',
        'thousands_separator',
        'decimal_separator',
        'negative_format',
        'positive_format',
        'sort_order',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'exchange_rate' => 'float',
        'decimal_places' => 'integer',
        'sort_order' => 'integer',
    ];
}
