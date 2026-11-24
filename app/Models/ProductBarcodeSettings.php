<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBarcodeSettings extends Model
{
    protected $table = 'product_barcode_settings';

    protected $fillable = [
        'barcode_type',
        'include_weight',
        'barcode_format',
        'weight_unit_division',
        'currency_division',
        'barcode_width',
        'barcode_height',
        'display_value',
        'font_size',
        'margin',
    ];

    protected $casts = [
        'include_weight' => 'boolean',
        'display_value' => 'boolean',
        'barcode_width' => 'integer',
        'barcode_height' => 'integer',
        'font_size' => 'integer',
        'margin' => 'integer',
    ];

    /**
     * الحصول على الإعدادات الحالية
     */
    public static function getCurrent()
    {
        return self::first() ?? self::create([
            'barcode_type' => 'CODE128',
            'include_weight' => false,
            'barcode_width' => 2,
            'barcode_height' => 50,
            'display_value' => true,
            'font_size' => 12,
            'margin' => 10,
        ]);
    }
}
