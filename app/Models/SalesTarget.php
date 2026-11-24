<?php

namespace App\Models;

use App\Models\Concerns\HasTargetPeriod;
use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    use HasTargetPeriod;

    protected $fillable = [
        'name',
        'target_type',
        'target_value',
        'base_amount',
        'commission_percentage',
        'status',
        'commission_period',
        'auto_calculate_weekly',
        'description',
        'created_by',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'auto_calculate_weekly' => 'boolean',
    ];

    /**
     * العلاقة مع المستخدم الذي أنشأ الهدف
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة مع التسويات
     */
    public function settlements()
    {
        return $this->hasMany(SalesTargetSettlement::class);
    }

    /**
     * العلاقة مع إنجازات الموظفين
     */
    public function achievements()
    {
        return $this->hasMany(SalesTargetAchievement::class);
    }

    /**
     * العلاقة مع مستويات العمولات
     */
    public function commissionTiers()
    {
        return $this->hasMany(SalesTargetCommissionTier::class)->orderBy('tier_order');
    }

    /**
     * حساب القيمة الفعلية للهدف (إذا كان نسبة)
     */
    public function getActualTargetValueAttribute()
    {
        if ($this->target_type === 'percentage' && $this->base_amount) {
            return ($this->base_amount * $this->target_value) / 100;
        }
        return $this->target_value;
    }

    /**
     * حساب العمولة المستحقة بناءً على نسبة الإنجاز والمستويات المتدرجة
     * العمولة تُحسب من الهدف الأساسي وليس من المبلغ المحقق
     */
    public function calculateCommission($achievedAmount, $targetAmount)
    {
        if ($targetAmount <= 0) {
            return 0;
        }

        $achievementPercentage = ($achievedAmount / $targetAmount) * 100;

        // إذا كان هناك مستويات متدرجة، استخدمها
        if ($this->commissionTiers && $this->commissionTiers->count() > 0) {
            $applicableTier = null;
            
            // البحث عن أعلى مستوى ينطبق على نسبة الإنجاز
            foreach ($this->commissionTiers->sortByDesc('achievement_percentage') as $tier) {
                if ($achievementPercentage >= $tier->achievement_percentage) {
                    $applicableTier = $tier;
                    break;
                }
            }

            if ($applicableTier) {
                // العمولة تحسب من المبلغ المحقق فعلياً وليس من الهدف الأساسي
                return $achievedAmount * ($applicableTier->commission_percentage / 100);
            }

            return 0; // لم يصل لأي مستوى
        }

        // إذا لم يكن هناك مستويات، استخدم العمولة الافتراضية (من المبلغ المحقق)
        return $achievedAmount * ($this->commission_percentage / 100);
    }

    /**
     * التحقق من وجود تسوية لفترة معينة
     */
    public function hasSettlementForPeriod($fromDate, $toDate)
    {
        return $this->settlements()
            ->where(function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('from_date', [$fromDate, $toDate])
                  ->orWhereBetween('to_date', [$fromDate, $toDate])
                  ->orWhere(function ($q2) use ($fromDate, $toDate) {
                      $q2->where('from_date', '<=', $fromDate)
                         ->where('to_date', '>=', $toDate);
                  });
            })
            ->exists();
    }

    /**
     * الموظفون (`employees.id`) الذين سُوّيت عمولتُهم عن فترةٍ تتداخل مع هذه.
     *
     * الفحصُ لكلّ موظفٍ لا للهدف كلّه: تسويةٌ تخطّت موظفاً (بلا خزينة مثلاً)
     * لا تُغلق الفترة على من لم يُصرف له. نفس شرط التداخل في
     * `TargetSettlementService`.
     *
     * @return array<int, int>
     */
    public function settledEmployeeIdsForPeriod($fromDate, $toDate): array
    {
        return $this->settlements()
            ->where('from_date', '<=', $toDate)
            ->where('to_date', '>=', $fromDate)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Scope للأهداف الشهرية
     */
    public function scopeMonthly($query)
    {
        return $query->where('commission_period', 'monthly');
    }

    /**
     * Scope للأهداف الأسبوعية
     */
    public function scopeWeekly($query)
    {
        return $query->where('commission_period', 'weekly');
    }

    /**
     * هل الهدف أسبوعي؟
     */
    public function isWeekly()
    {
        return $this->commission_period === 'weekly';
    }

    /**
     * هل الهدف شهري؟
     */
    public function isMonthly()
    {
        return $this->commission_period === 'monthly';
    }
}
