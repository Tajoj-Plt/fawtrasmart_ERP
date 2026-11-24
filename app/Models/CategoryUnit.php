<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نموذج الجدول الوسيط - CategoryUnit
 * 
 * يربط بين الأصناف والوحدات (علاقة many-to-many)
 * يحتوي على معامل التحويل الخاص بكل صنف
 * 
 * @property int $id
 * @property int $category_id الصنف
 * @property int $unit_id الوحدة
 * @property float $conversion_factor معامل التحويل
 * @property bool $is_base_unit هل هي الوحدة الأساسية؟
 * @property bool $is_active حالة التفعيل
 */
class CategoryUnit extends Model
{
    use HasFactory;

    protected $table = 'category_unit';

    protected $fillable = [
        'category_id',
        'unit_id',
        'conversion_factor',
        'is_base_unit',
        'is_active',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'is_base_unit' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ===============================
    // العلاقات - Relationships
    // ===============================

    /**
     * العلاقة مع الصنف
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * العلاقة مع الوحدة
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * العلاقة مع وحدات المنتجات
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'category_unit_id');
    }

    // ===============================
    // النطاقات - Scopes
    // ===============================

    /**
     * الوحدات النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * الوحدة الأساسية فقط
     */
    public function scopeBaseUnit($query)
    {
        return $query->where('is_base_unit', true);
    }

    // ===============================
    // الدوال المساعدة - Helper Methods
    // ===============================

    /**
     * الحصول على اسم الوحدة
     */
    public function getNameAttribute(): string
    {
        return $this->unit?->name ?? '';
    }

    /**
     * الحصول على رمز الوحدة
     */
    public function getSymbolAttribute(): ?string
    {
        return $this->unit?->symbol;
    }

    /**
     * تحويل الكمية من هذه الوحدة إلى الوحدة الأساسية
     */
    public function convertToBase($quantity): float
    {
        return $quantity * $this->conversion_factor;
    }

    /**
     * تحويل الكمية من الوحدة الأساسية إلى هذه الوحدة
     */
    public function convertFromBase($quantity): float
    {
        if ($this->conversion_factor == 0) {
            return 0;
        }
        return $quantity / $this->conversion_factor;
    }

    /**
     * هل هذه الوحدة مستخدمة في منتجات؟
     */
    public function isUsedInProducts(): bool
    {
        return $this->productUnits()->exists();
    }
}
