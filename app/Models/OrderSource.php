<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSource extends Model
{
    protected $fillable = ['name', 'active', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** المفعَّلة وحدها — المطفأ لا يُعرَض في مستندٍ جديد */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** بترتيب الشاشة: `sort_order` ثمّ الاسم */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
