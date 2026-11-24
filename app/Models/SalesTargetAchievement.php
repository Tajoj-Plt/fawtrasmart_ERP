<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTargetAchievement extends Model
{
    protected $fillable = [
        'sales_target_id',
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

    /**
     * العلاقة مع الهدف
     */
    public function target()
    {
        return $this->belongsTo(SalesTarget::class, 'sales_target_id');
    }

    /**
     * العلاقة مع الموظف
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * العلاقة مع الخزينة
     */
    public function treasury()
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    /**
     * حساب نسبة الإنجاز
     */
    public function calculateAchievementPercentage()
    {
        if ($this->target_amount > 0) {
            return ($this->achieved_amount / $this->target_amount) * 100;
        }
        return 0;
    }

    /**
     * تحديث نسبة الإنجاز
     */
    public function updateAchievementPercentage()
    {
        $this->achievement_percentage = $this->calculateAchievementPercentage();
        $this->save();
    }

    /**
     * المبلغ المتبقي للوصول إلى الهدف
     */
    public function getRemainingAmountAttribute()
    {
        return max(0, $this->target_amount - $this->achieved_amount);
    }

    /**
     * هل تم تحقيق الهدف؟
     */
    public function getIsAchievedAttribute()
    {
        return $this->achieved_amount >= $this->target_amount;
    }
}
