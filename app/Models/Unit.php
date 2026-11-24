<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * نموذج الوحدة - Unit
 * 
 * الوحدات منفصلة ومشتركة بين جميع الأصناف
 * مثل: حبة، كرتون، كيلو، درزن، إلخ
 * 
 * @property int $id
 * @property string $name اسم الوحدة
 * @property string|null $symbol رمز الوحدة
 * @property string|null $description وصف الوحدة
 * @property bool $is_active حالة التفعيل
 */
class Unit extends Model
{
    use HasFactory;

    protected $table = 'units';

    protected $fillable = [
        'name',
        'symbol',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ===============================
    // العلاقات - Relationships
    // ===============================

    /**
     * الأصناف المرتبطة بهذه الوحدة (علاقة many-to-many)
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_unit')
                    ->withPivot(['conversion_factor', 'is_base_unit', 'is_active'])
                    ->withTimestamps();
    }

    /**
     * ربط الوحدة بالأصناف (الجدول الوسيط)
     */
    public function categoryUnits()
    {
        return $this->hasMany(CategoryUnit::class, 'unit_id');
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

    // ===============================
    // الدوال المساعدة - Helper Methods
    // ===============================

    /**
     * الحصول على الاسم مع الرمز
     */
    public function getNameWithSymbolAttribute(): string
    {
        if ($this->symbol) {
            return "{$this->name} ({$this->symbol})";
        }
        return $this->name;
    }

    /**
     * هل هذه الوحدة مستخدمة في أي صنف؟
     */
    public function isUsed(): bool
    {
        return $this->categoryUnits()->exists();
    }

    /**
     * عدد الأصناف التي تستخدم هذه الوحدة
     */
    public function getCategoriesCountAttribute(): int
    {
        return $this->categoryUnits()->count();
    }
}
