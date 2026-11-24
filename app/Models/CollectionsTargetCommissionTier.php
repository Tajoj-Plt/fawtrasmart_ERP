<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionsTargetCommissionTier extends Model
{
    protected $table = 'collections_target_commission_tiers';

    protected $fillable = [
        'collections_target_id',
        'achievement_percentage',
        'commission_percentage',
        'tier_order',
    ];

    protected $casts = [
        'achievement_percentage' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'tier_order' => 'integer',
    ];

    public function target()
    {
        return $this->belongsTo(CollectionsTarget::class, 'collections_target_id');
    }
}
