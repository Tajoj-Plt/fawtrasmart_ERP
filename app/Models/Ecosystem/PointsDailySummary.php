<?php

namespace App\Models\Ecosystem;

use App\Enums\UserType;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsDailySummary extends Model
{
    protected $table = 'points_daily_summary';

    protected $fillable = [
        'date',
        'user_type',
        'branch_id',
        'total_earned',
        'total_spent',
        'total_expired',
        'total_adjusted',
        'transactions_count',
        'unique_users',
        'new_tier_upgrades',
        'redemptions_count',
        'redemptions_value',
        'achievements_unlocked',
        'challenges_completed',
        'referrals_confirmed',
    ];

    protected $casts = [
        'date' => 'date',
        'user_type' => UserType::class,
        'total_earned' => 'integer',
        'total_spent' => 'integer',
        'total_expired' => 'integer',
        'total_adjusted' => 'integer',
        'transactions_count' => 'integer',
        'unique_users' => 'integer',
        'new_tier_upgrades' => 'integer',
        'redemptions_count' => 'integer',
        'redemptions_value' => 'decimal:2',
        'achievements_unlocked' => 'integer',
        'challenges_completed' => 'integer',
        'referrals_confirmed' => 'integer',
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

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForBranch($query, ?int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForUserType($query, UserType $userType)
    {
        return $query->where('user_type', $userType->value);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function getNetPointsChange(): int
    {
        return $this->total_earned - $this->total_spent - $this->total_expired + $this->total_adjusted;
    }
}
