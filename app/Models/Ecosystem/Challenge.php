<?php

namespace App\Models\Ecosystem;

use App\Enums\ChallengeType;
use App\Enums\CompetitionType;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'description',
        'user_type',
        'challenge_type',
        'competition_type',
        'target_action',
        'target_value',
        'target_unit',
        'reward_points',
        'reward_points_2nd',
        'reward_points_3rd',
        'max_winners',
        'min_tier_id',
        'image',
        'rules',
        'is_featured',
        'starts_at',
        'ends_at',
        'results_announced_at',
        'auto_enroll',
        'is_active',
        'branch_id',
        'department_id',
        'created_by',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'challenge_type' => ChallengeType::class,
        'competition_type' => CompetitionType::class,
        'target_action' => 'array',
        'target_value' => 'integer',
        'reward_points' => 'integer',
        'reward_points_2nd' => 'integer',
        'reward_points_3rd' => 'integer',
        'max_winners' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'results_announced_at' => 'datetime',
        'auto_enroll' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function minTier(): BelongsTo
    {
        return $this->belongsTo(PointsTier::class, 'min_tier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(ChallengeTeam::class);
    }

    public function challengeTransactions(): HasMany
    {
        return $this->hasMany(ChallengeTransaction::class);
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

    public function scopeOngoing($query)
    {
        return $query->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now());
    }

    public function scopeEnded($query)
    {
        return $query->where('ends_at', '<', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('ends_at', '<', now());
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isOngoing(): bool
    {
        $now = now();
        return $this->starts_at <= $now && $this->ends_at >= $now;
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at > now();
    }

    public function hasEnded(): bool
    {
        return $this->ends_at < now();
    }

    public function getDaysRemaining(): int
    {
        if ($this->hasEnded()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->ends_at, false));
    }

    public function getHoursRemaining(): int
    {
        if ($this->hasEnded()) {
            return 0;
        }

        return max(0, now()->diffInHours($this->ends_at, false));
    }

    public function getParticipantsCount(): int
    {
        return $this->participants()->count();
    }

    public function getCompletedCount(): int
    {
        return $this->participants()->where('is_completed', true)->count();
    }

    public function getLeaderboard(int $limit = 10)
    {
        return $this->participants()
            ->with(['employee', 'client'])
            ->orderBy('rank')
            ->orderByDesc('current_progress')
            ->limit($limit)
            ->get();
    }

    public function getDisplayName(?string $locale = null): string
    {
        if ($locale === 'en' && $this->name_en) {
            return $this->name_en;
        }
        return $this->name;
    }

    /**
     * Check if this challenge targets a specific action type
     */
    public function targetsAction(string $actionType): bool
    {
        $actions = $this->target_action;
        if (is_array($actions)) {
            return in_array($actionType, $actions);
        }
        return $actions === $actionType;
    }

    /**
     * Get target actions as display labels
     */
    public function getTargetActionLabels(): array
    {
        $actions = $this->target_action;
        if (!is_array($actions)) {
            $actions = [$actions];
        }

        return array_map(function ($action) {
            $enum = \App\Enums\ActionType::tryFrom($action);
            return $enum ? $enum->label() : $action;
        }, $actions);
    }
}
