<?php

namespace App\Models\Ecosystem;

use App\Enums\BatchStatus;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsBatch extends Model
{
    protected $fillable = [
        'transaction_id',
        'user_type',
        'employee_id',
        'client_id',
        'original_points',
        'remaining_points',
        'used_points',
        'expired_points',
        'earned_at',
        'expires_at',
        'expired_at',
        'status',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'status' => BatchStatus::class,
        'original_points' => 'integer',
        'remaining_points' => 'integer',
        'used_points' => 'integer',
        'expired_points' => 'integer',
        'earned_at' => 'datetime',
        'expires_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('user_type', UserType::EMPLOYEE->value)
            ->where('employee_id', $employeeId);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('user_type', UserType::CLIENT->value)
            ->where('client_id', $clientId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', BatchStatus::ACTIVE->value)
            ->where('remaining_points', '>', 0);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', BatchStatus::ACTIVE->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days));
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', BatchStatus::EXPIRED->value)
                ->orWhere(function ($q2) {
                    $q2->where('status', BatchStatus::ACTIVE->value)
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now());
                });
        });
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isActive(): bool
    {
        return $this->status === BatchStatus::ACTIVE && $this->remaining_points > 0;
    }

    public function isExpired(): bool
    {
        return $this->status === BatchStatus::EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function isDepleted(): bool
    {
        return $this->status === BatchStatus::DEPLETED || $this->remaining_points <= 0;
    }

    public function usePoints(int $points): bool
    {
        if ($points > $this->remaining_points) {
            return false;
        }

        $this->remaining_points -= $points;
        $this->used_points += $points;

        if ($this->remaining_points <= 0) {
            $this->status = BatchStatus::DEPLETED;
        }

        $this->save();
        return true;
    }

    public function markAsExpired(): void
    {
        if ($this->remaining_points > 0) {
            $this->expired_points += $this->remaining_points;
            $this->remaining_points = 0;
        }

        $this->status = BatchStatus::EXPIRED;
        $this->save();
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function getUser()
    {
        return $this->user_type === UserType::EMPLOYEE
            ? $this->employee
            : $this->client;
    }
}
