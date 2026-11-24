<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نموذج الصنف - Category
 * 
 * @property int $id
 * @property int|null $product_group_id مجموعة المنتجات
 * @property string $name اسم الصنف
 * @property string|null $code كود الصنف
 * @property string|null $attachments المرفقات
 * @property int|null $main_category الصنف الرئيسي
 * @property string|null $discretion الوصف
 * @property bool $is_active حالة التفعيل
 */
class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'product_group_id',
        'name',
        'code',
        'attachments',
        'main_category',
        'discretion',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ===============================
    // العلاقات - Relationships
    // ===============================

    /**
     * العلاقة مع المنتجات
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * العلاقة مع الصنف الرئيسي
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'main_category');
    }

    /**
     * العلاقة مع الأصناف الفرعية
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'main_category');
    }

    /**
     * العلاقة مع مجموعة المنتجات
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    /**
     * العلاقة مع الوحدات (علاقة many-to-many عبر الجدول الوسيط)
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'category_unit')
                    ->withPivot(['conversion_factor', 'is_base_unit', 'is_active'])
                    ->withTimestamps();
    }

    /**
     * ربط الصنف بالوحدات (الجدول الوسيط)
     */
    public function categoryUnits(): HasMany
    {
        return $this->hasMany(CategoryUnit::class, 'category_id');
    }

    // ===============================
    // الدوال المساعدة - Helper Methods
    // ===============================

    /**
     * الحصول على الوحدة الأساسية
     */
    public function baseUnit()
    {
        return $this->categoryUnits()->where('is_base_unit', true)->with('unit')->first();
    }

    /**
     * الحصول على وحدات الصنف النشطة
     */
    public function activeUnits()
    {
        return $this->categoryUnits()->where('is_active', true)->with('unit')->get();
    }

    /**
     * الحصول على أسماء الوحدات المتاحة
     */
    public function getUnitNamesAttribute(): array
    {
        return $this->units->pluck('name')->toArray();
    }

    // ===============================
    // النطاقات - Scopes
    // ===============================

    /**
     * الأصناف النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * الأصناف الرئيسية (بدون أب)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('main_category');
    }

    /**
     * الأصناف التابعة لمجموعة معينة
     */
    public function scopeInGroup($query, $groupId)
    {
        return $query->where('product_group_id', $groupId);
    }
}
