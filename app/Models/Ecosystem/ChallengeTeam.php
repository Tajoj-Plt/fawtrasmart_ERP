<?php

namespace App\Models\Ecosystem;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChallengeTeam extends Model
{
    protected $fillable = [
        'challenge_id',
        'name',
        'user_type',
        'leader_employee_id',
        'total_progress',
        'is_completed',
        'completed_at',
        'rank',
    ];

    protected $casts = [
        'total_progress' => 'integer',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'rank' => 'integer',
    ];

    /**
     * هل الفريق للموظفين
     */
    public function isEmployeeTeam(): bool
    {
        return $this->user_type === 'employee';
    }

    /**
     * هل الفريق للعملاء
     */
    public function isClientTeam(): bool
    {
        return $this->user_type === 'client';
    }

    /**
     * عرض نوع الفريق بالعربية
     */
    public function getUserTypeLabelAttribute(): string
    {
        return $this->user_type === 'employee' ? 'فريق موظفين' : 'فريق عملاء';
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_employee_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class, 'team_id');
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function getMembersCount(): int
    {
        return $this->members()->count();
    }

    public function getAverageProgress(): float
    {
        $count = $this->getMembersCount();
        if ($count === 0) {
            return 0;
        }

        return $this->total_progress / $count;
    }

    public function updateTotalProgress(): void
    {
        $this->total_progress = $this->members()->sum('current_progress');
        $this->save();
    }
}
