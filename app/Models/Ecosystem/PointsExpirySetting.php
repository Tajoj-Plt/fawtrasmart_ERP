<?php

namespace App\Models\Ecosystem;

use App\Enums\ExpiryType;
use App\Enums\UserType;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsExpirySetting extends Model
{
    protected $fillable = [
        'user_type',
        'expiry_type',
        'expiry_days',
        'expiry_month',
        'expiry_day',
        'warning_days',
        'reminder_days',
        'grace_period_days',
        'exclude_tiers',
        'is_active',
        'branch_id',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'expiry_type' => ExpiryType::class,
        'expiry_days' => 'integer',
        'expiry_month' => 'integer',
        'expiry_day' => 'integer',
        'warning_days' => 'integer',
        'grace_period_days' => 'integer',
        'reminder_days' => 'array',
        'exclude_tiers' => 'array',
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

    public function scopeForUserType($query, string|UserType $userType)
    {
        $userTypeValue = $userType instanceof UserType ? $userType->value : $userType;
        return $query->where('user_type', $userTypeValue);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isPointsExpiryEnabled(): bool
    {
        return $this->expiry_type !== ExpiryType::NEVER && $this->is_active;
    }

    public function isTierExcluded(?int $tierId): bool
    {
        if (!$this->exclude_tiers || !$tierId) {
            return false;
        }

        return in_array($tierId, $this->exclude_tiers);
    }

    public function getReminderDays(): array
    {
        return $this->reminder_days ?? [];
    }

    public function shouldSendReminder(int $daysUntilExpiry): bool
    {
        return in_array($daysUntilExpiry, $this->getReminderDays());
    }

    public static function getSettingsForUser(UserType $userType, ?int $branchId = null): ?self
    {
        return static::active()
            ->forUserType($userType->value)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();
    }
}
