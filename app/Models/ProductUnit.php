<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    use HasFactory;

    protected $table = 'product_units';

    protected $fillable = [
        'product_id',
        'category_unit_id',
        'barcode',
        'purchase_price',
        'sale_price',
        'min_sale_price',
        'wholesale_price',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'min_sale_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * العلاقة مع المنتج
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * العلاقة مع وحدة الصنف
     */
    public function categoryUnit(): BelongsTo
    {
        return $this->belongsTo(CategoryUnit::class, 'category_unit_id');
    }

    /**
     * Scope للوحدات النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope للوحدة الافتراضية
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * الحصول على اسم الوحدة من وحدة الصنف
     */
    public function getUnitNameAttribute(): string
    {
        return $this->categoryUnit->name ?? '';
    }

    /**
     * الحصول على معامل التحويل من وحدة الصنف
     */
    public function getConversionFactorAttribute(): float
    {
        return $this->categoryUnit->conversion_factor ?? 1;
    }

    /**
     * حساب هامش الربح
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->purchase_price && $this->purchase_price > 0) {
            return (($this->sale_price - $this->purchase_price) / $this->purchase_price) * 100;
        }
        return 0;
    }

    /**
     * حساب مبلغ الربح
     */
    public function getProfitAmountAttribute(): float
    {
        return ($this->sale_price ?? 0) - ($this->purchase_price ?? 0);
    }
}
