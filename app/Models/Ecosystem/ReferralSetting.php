<?php

namespace App\Models\Ecosystem;

use App\Enums\ConfirmationType;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralSetting extends Model
{
    protected $fillable = [
        'referrer_points',
        'referred_points',
        'confirmation_type',
        'confirmation_value',
        'max_referrals_per_month',
        'max_referrals_total',
        'bonus_milestones',
        'is_active',
        'branch_id',
    ];

    protected $casts = [
        'referrer_points' => 'integer',
        'referred_points' => 'integer',
        'confirmation_type' => ConfirmationType::class,
        'confirmation_value' => 'decimal:2',
        'max_referrals_per_month' => 'integer',
        'max_referrals_total' => 'integer',
        'bonus_milestones' => 'array',
        'is_active' => 'boolean',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function getBonusPointsForMilestone(int $referralCount): int
    {
        if (!$this->bonus_milestones) {
            return 0;
        }

        $bonus = 0;
        foreach ($this->bonus_milestones as $milestone) {
            if (isset($milestone['count']) && isset($milestone['points'])) {
                if ($referralCount >= $milestone['count']) {
                    $bonus = max($bonus, $milestone['points']);
                }
            }
        }

        return $bonus;
    }

    public static function getActiveSettings(?int $branchId = null): ?self
    {
        return static::active()
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();
    }
}
