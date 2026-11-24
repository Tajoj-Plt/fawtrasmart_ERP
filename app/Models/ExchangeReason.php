<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeReason extends Model
{
    use HasFactory;

    protected $table = 'exchange_reasons';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * الحصول على الأسباب النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * ترتيب حسب sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * الحصول على قائمة الأسباب للـ dropdown
     */
    public static function getForDropdown()
    {
        return self::active()->ordered()->pluck('name', 'id')->toArray();
    }
}
