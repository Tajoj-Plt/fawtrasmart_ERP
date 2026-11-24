<?php

namespace App\Models\Ecosystem;

use App\Enums\ConditionOperator;
use App\Enums\ConditionPeriod;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'description',
        'user_type',
        'category',
        'icon',
        'image',
        'color',
        'condition_type',
        'condition_operator',
        'condition_value',
        'condition_period',
        'bonus_points',
        'is_hidden',
        'is_one_time',
        'is_active',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'condition_operator' => ConditionOperator::class,
        'condition_period' => ConditionPeriod::class,
        'condition_value' => 'integer',
        'bonus_points' => 'integer',
        'is_hidden' => 'boolean',
        'is_one_time' => 'boolean',
        'is_active' => 'boolean',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
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
        return $query->whereIn('user_type', [$userTypeValue, 'both']);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function checkCondition($value): bool
    {
        return match($this->condition_operator) {
            ConditionOperator::GREATER_THAN_OR_EQUAL => $value >= $this->condition_value,
            ConditionOperator::GREATER_THAN => $value > $this->condition_value,
            ConditionOperator::EQUAL => $value == $this->condition_value,
            ConditionOperator::LESS_THAN => $value < $this->condition_value,
            ConditionOperator::LESS_THAN_OR_EQUAL => $value <= $this->condition_value,
        };
    }

    public function hasBeenAchievedBy($userId, UserType $userType): bool
    {
        return $this->userAchievements()
            ->where('user_type', $userType->value)
            ->where($userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id', $userId)
            ->exists();
    }

    public function getDisplayName(?string $locale = null): string
    {
        if ($locale === 'en' && $this->name_en) {
            return $this->name_en;
        }
        return $this->name;
    }

    public function getProgressPercentage($currentValue): float
    {
        if ($this->condition_value == 0) {
            return 100;
        }
        
        return min(100, ($currentValue / $this->condition_value) * 100);
    }
}
