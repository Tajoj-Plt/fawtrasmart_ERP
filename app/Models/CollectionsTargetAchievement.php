<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionsTargetAchievement extends Model
{
    protected $table = 'collections_target_achievements';

    protected $fillable = [
        'collections_target_id',
        'employee_id',
        'treasury_id',
        'achieved_amount',
        'target_amount',
        'achievement_percentage',
        'payments_count',
        'last_payment_at',
    ];

    protected $casts = [
        'achieved_amount' => 'decimal:2',
        'target_amount' => 'decimal:2',
        'achievement_percentage' => 'decimal:2',
        'last_payment_at' => 'datetime',
    ];

    public function target()
    {
        return $this->belongsTo(CollectionsTarget::class, 'collections_target_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function treasury()
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    public function calculateAchievementPercentage()
    {
        if ($this->target_amount > 0) {
            return ($this->achieved_amount / $this->target_amount) * 100;
        }
        return 0;
    }

    public function updateAchievementPercentage()
    {
        $this->achievement_percentage = $this->calculateAchievementPercentage();
        $this->save();
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->target_amount - $this->achieved_amount);
    }

    public function getIsAchievedAttribute()
    {
        return $this->achieved_amount >= $this->target_amount;
    }
}
