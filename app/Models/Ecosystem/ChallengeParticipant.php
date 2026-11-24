<?php

namespace App\Models\Ecosystem;

use App\Enums\UserType;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChallengeParticipant extends Model
{
    protected $fillable = [
        'challenge_id',
        'user_type',
        'employee_id',
        'client_id',
        'team_id',
        'target_value_snapshot',
        'current_progress',
        'is_completed',
        'completed_at',
        'rank',
        'points_awarded',
        'points_awarded_at',
        'enrolled_at',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'target_value_snapshot' => 'integer',
        'current_progress' => 'integer',
        'progress_percentage' => 'decimal:2',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'rank' => 'integer',
        'points_awarded' => 'integer',
        'points_awarded_at' => 'datetime',
        'enrolled_at' => 'datetime',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ChallengeTeam::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class);
    }

    public function challengeTransactions(): HasMany
    {
        return $this->hasMany(ChallengeTransaction::class, 'challenge_participant_id');
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

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeInProgress($query)
    {
        return $query->where('is_completed', false);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function updateProgress(int $newProgress): void
    {
        // لا يمكن أن يكون التقدم سالباً
        $this->current_progress = max(0, $newProgress);

        $targetValue = $this->target_value_snapshot ?: ($this->challenge?->target_value ?? 1);

        if ($this->current_progress >= $targetValue && !$this->is_completed) {
            $this->markAsCompleted();
        } elseif ($this->current_progress < $targetValue && $this->is_completed) {
            // إذا انخفض التقدم عن الهدف بسبب نقاط سالبة، يُلغى الإكمال
            $this->is_completed = false;
            $this->completed_at = null;
        }

        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->is_completed = true;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * الحصول على نسبة التقدم (محسوبة من DB كحقل STORED)
     */
    public function getProgressPercentage(): float
    {
        if ($this->target_value_snapshot <= 0) return 0;
        return round(($this->current_progress / $this->target_value_snapshot) * 100, 1);
    }

    public function getUser()
    {
        return $this->user_type === UserType::EMPLOYEE
            ? $this->employee
            : $this->client;
    }

    public function getUserName(): string
    {
        $user = $this->getUser();
        return $user?->name ?? 'Unknown';
    }
}
