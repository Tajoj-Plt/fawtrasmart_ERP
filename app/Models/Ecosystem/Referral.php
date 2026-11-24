<?php

namespace App\Models\Ecosystem;

use App\Enums\ReferralStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_id',
        'referral_code',
        'status',
        'referrer_points',
        'referred_points',
        'referrer_transaction_id',
        'referred_transaction_id',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'status' => ReferralStatus::class,
        'referrer_points' => 'integer',
        'referred_points' => 'integer',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_id');
    }

    public function referrerTransaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class, 'referrer_transaction_id');
    }

    public function referredTransaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class, 'referred_transaction_id');
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeForReferrer($query, int $referrerId)
    {
        return $query->where('referrer_id', $referrerId);
    }

    public function scopePending($query)
    {
        return $query->where('status', ReferralStatus::PENDING->value);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', ReferralStatus::CONFIRMED->value);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isPending(): bool
    {
        return $this->status === ReferralStatus::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === ReferralStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ReferralStatus::CANCELLED;
    }

    public function confirm(string $details = null): void
    {
        $this->update([
            'status' => ReferralStatus::CONFIRMED,
            'confirmed_at' => now(),
            'confirmation_details' => $details,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => ReferralStatus::CANCELLED,
        ]);
    }
}
