<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTargetCommissionTier extends Model
{
    protected $fillable = [
        'sales_target_id',
        'achievement_percentage',
        'commission_percentage',
        'tier_order',
    ];

    protected $casts = [
        'achievement_percentage' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'tier_order' => 'integer',
    ];

    /**
     * العلاقة مع الهدف
     */
    public function target()
    {
        return $this->belongsTo(SalesTarget::class, 'sales_target_id');
    }
}
