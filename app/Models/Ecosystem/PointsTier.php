<?php

namespace App\Models\Ecosystem;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointsTier extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'user_type',
        'min_points',
        'max_points',
        'color',
        'icon',
        'image',
        'points_multiplier',
        'discount_percentage',
        'priority_support',
        'free_shipping',
        'sort_order',
        'benefits',
        'is_active',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'min_points' => 'integer',
        'max_points' => 'integer',
        'points_multiplier' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'priority_support' => 'boolean',
        'free_shipping' => 'boolean',
        'sort_order' => 'integer',
        'benefits' => 'array',
        'is_active' => 'boolean',
    ];

    // =====================================================
    // Accessors & Mutators
    // =====================================================

    public function getBenefitsAttribute($value)
    {
        if (is_null($value)) {
            return [];
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            
            if (!is_array($decoded)) {
                return [];
            }
            
            // If it's an associative array (object), extract all values from all keys
            if ($this->isAssociativeArray($decoded)) {
                $allValues = [];
                foreach ($decoded as $items) {
                    if (is_array($items)) {
                        $allValues = array_merge($allValues, $items);
                    } elseif (is_string($items)) {
                        $allValues[] = $items;
                    }
                }
                return array_filter($allValues);
            }
            
            // If it's a simple array, return it
            return array_filter($decoded);
        }
        
        if (is_array($value)) {
            // If it's an associative array, extract all values
            if ($this->isAssociativeArray($value)) {
                $allValues = [];
                foreach ($value as $items) {
                    if (is_array($items)) {
                        $allValues = array_merge($allValues, $items);
                    } elseif (is_string($items)) {
                        $allValues[] = $items;
                    }
                }
                return array_filter($allValues);
            }
            return array_filter($value);
        }
        
        return [];
    }

    public function setBenefitsAttribute($value)
    {
        if (is_array($value)) {
            $filtered = array_filter($value);
            $this->attributes['benefits'] = json_encode(['features' => array_values($filtered)]);
        } elseif (is_null($value)) {
            $this->attributes['benefits'] = json_encode(['features' => []]);
        } else {
            $this->attributes['benefits'] = $value;
        }
    }

    private function isAssociativeArray($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function upgrades(): HasMany
    {
        return $this->hasMany(TierUpgradeLog::class, 'new_tier_id');
    }

    public function downgrades(): HasMany
    {
        return $this->hasMany(TierUpgradeLog::class, 'old_tier_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'min_tier_id');
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('min_points');
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isInRange(int $points): bool
    {
        $meetsMin = $points >= $this->min_points;
        $meetsMax = $this->max_points === null || $points <= $this->max_points;
        
        return $meetsMin && $meetsMax;
    }

    public function hasMultiplier(): bool
    {
        return $this->points_multiplier > 1;
    }

    public function hasDiscount(): bool
    {
        return $this->discount_percentage > 0;
    }

    public function getDisplayName(?string $locale = null): string
    {
        if ($locale === 'en' && $this->name_en) {
            return $this->name_en;
        }
        return $this->name;
    }

    public static function getTierForPoints(string $userType, int $points): ?self
    {
        // الحصول على أعلى مستوى يناسب النقاط الحالية
        // نرتب تنازلياً حسب min_points لنحصل على أعلى مستوى
        return static::active()
            ->forUserType($userType)
            ->where('min_points', '<=', $points)
            ->where(function ($query) use ($points) {
                $query->whereNull('max_points')
                      ->orWhere('max_points', '>=', $points);
            })
            ->orderByDesc('min_points') // الترتيب تنازلياً للحصول على أعلى مستوى
            ->first();
    }

    public function getNextTier(): ?self
    {
        return static::active()
            ->forUserType($this->user_type->value)
            ->where('min_points', '>', $this->min_points)
            ->ordered()
            ->first();
    }

    public function getPointsToNextTier(int $currentPoints): ?int
    {
        $nextTier = $this->getNextTier();
        if (!$nextTier) {
            return null;
        }
        
        return max(0, $nextTier->min_points - $currentPoints);
    }
}
