<?php

namespace App\Models\Ecosystem;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchievementSummary extends Model
{
    protected $table = 'achievements_summary';

    protected $fillable = [
        'achievement_id',
        'total_achievers',
        'total_points_awarded',
        'last_achieved_at',
    ];

    protected $casts = [
        'total_achievers' => 'integer',
        'total_points_awarded' => 'integer',
        'last_achieved_at' => 'datetime',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    // =====================================================
    // Scopes
    // =====================================================

    // user_type scope removed as column doesn't exist

    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderByDesc('total_achievers')->limit($limit);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function incrementAchieved(int $pointsAwarded): void
    {
        $this->total_achievers++;
        $this->total_points_awarded += $pointsAwarded;
        $this->last_achieved_at = now();
        $this->save();
    }

    public function getAveragePointsPerAchievement(): float
    {
        if ($this->total_achievers === 0) {
            return 0;
        }

        return $this->total_points_awarded / $this->total_achievers;
    }

    public static function updateForAchievement(
        int $achievementId,
        int $pointsAwarded
    ): void {
        $summary = static::firstOrCreate([
            'achievement_id' => $achievementId,
        ], [
            'total_achievers' => 0,
            'total_points_awarded' => 0,
        ]);

        $summary->incrementAchieved($pointsAwarded);
    }
}
