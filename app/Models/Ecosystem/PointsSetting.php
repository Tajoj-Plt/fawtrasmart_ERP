<?php

namespace App\Models\Ecosystem;

use App\Enums\CalculationType;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsSetting extends Model
{
    protected $fillable = [
        'user_type',
        'action_type',
        'action_name',
        'description',
        'points',
        'calculation_type',
        'calculation_value',
        'min_amount',
        'max_amount',
        'max_points',
        'tiers_config',
        'is_active',
        'disabled_reason',
        'disabled_at',
        'branch_id',
        'created_by',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'calculation_type' => CalculationType::class,
        'points' => 'integer',
        'calculation_value' => 'decimal:4',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'max_points' => 'integer',
        'tiers_config' => 'array',
        'is_active' => 'boolean',
        'disabled_at' => 'datetime',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function scopeForAction($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function scopeForBranch($query, ?int $branchId = null)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->whereNull('branch_id')
              ->orWhere('branch_id', $branchId);
        });
    }

    public function scopeDisabled($query)
    {
        return $query->where('is_active', false);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    /**
     * تعطيل الإعداد مع ذكر السبب
     */
    public function disable(?string $reason = null): self
    {
        $this->update([
            'is_active' => false,
            'disabled_reason' => $reason,
            'disabled_at' => now(),
        ]);

        return $this;
    }

    /**
     * تفعيل الإعداد
     */
    public function enable(): self
    {
        $this->update([
            'is_active' => true,
            'disabled_reason' => null,
            'disabled_at' => null,
        ]);

        return $this;
    }

    /**
     * هل الإعداد معطّل؟
     */
    public function isDisabled(): bool
    {
        return !$this->is_active;
    }

    public function isPositive(): bool
    {
        return $this->points > 0;
    }

    public function isNegative(): bool
    {
        return $this->points < 0;
    }

    public function getAbsolutePoints(): int
    {
        return abs($this->points);
    }

    /**
     * حساب النقاط بناءً على نوع الحساب وقيمة المعاملة
     * 
     * @param float|null $transactionAmount قيمة المعاملة (للفواتير، سندات القبض، إلخ)
     * @return int عدد النقاط المحسوبة
     * 
     * أنواع الحساب:
     * - fixed: نقاط ثابتة (تُرجع قيمة points)
     * - percentage: نسبة من قيمة المعاملة (1% = 1 نقطة لكل 100 ريال)
     * - per_amount: نقطة لكل X ريال (مثل: 1 نقطة لكل 10 ريال)
     * - tiered: مستويات متدرجة حسب قيمة المعاملة
     */
    public function calculatePoints(?float $transactionAmount = null): int
    {
        // التحقق من الحد الأدنى للمعاملة
        if ($this->min_amount !== null && $transactionAmount !== null && $transactionAmount < $this->min_amount) {
            return 0;
        }

        // التحقق من الحد الأقصى للمعاملة (تقليص القيمة)
        if ($this->max_amount !== null && $transactionAmount !== null && $transactionAmount > $this->max_amount) {
            $transactionAmount = $this->max_amount;
        }

        $calculatedPoints = 0;

        $calcType = $this->calculation_type instanceof CalculationType 
            ? $this->calculation_type 
            : CalculationType::tryFrom($this->calculation_type) ?? CalculationType::FIXED;

        switch ($calcType) {
            case CalculationType::FIXED:
                // نقاط ثابتة - لا تعتمد على قيمة المعاملة
                $calculatedPoints = $this->points;
                break;

            case CalculationType::PERCENTAGE:
                // نسبة من قيمة المعاملة
                // calculation_value = 1 يعني 1%
                if ($transactionAmount !== null && $this->calculation_value > 0) {
                    $calculatedPoints = (int) floor($transactionAmount * ($this->calculation_value / 100));
                } else {
                    $calculatedPoints = $this->points; // fallback للنقاط الثابتة
                }
                break;

            case CalculationType::PER_AMOUNT:
                // نقطة لكل X ريال
                // calculation_value = 10 يعني نقطة واحدة لكل 10 ريال
                if ($transactionAmount !== null && $this->calculation_value > 0) {
                    $calculatedPoints = (int) floor($transactionAmount / $this->calculation_value);
                } else {
                    $calculatedPoints = $this->points; // fallback للنقاط الثابتة
                }
                break;

            case CalculationType::TIERED:
                // مستويات متدرجة
                $calculatedPoints = $this->calculateTieredPoints($transactionAmount);
                break;

            default:
                $calculatedPoints = $this->points;
                break;
        }

        // تطبيق سقف النقاط إذا كان محدداً
        if ($this->max_points !== null && $calculatedPoints > $this->max_points) {
            $calculatedPoints = $this->max_points;
        }

        // الحفاظ على إشارة النقاط (سالب أو موجب)
        if ($this->points < 0 && $calculatedPoints > 0) {
            $calculatedPoints = -$calculatedPoints;
        }

        return $calculatedPoints;
    }

    /**
     * حساب النقاط المتدرجة بناءً على tiers_config
     * 
     * مثال tiers_config:
     * [
     *   {"min": 0, "max": 100, "points": 5},
     *   {"min": 100, "max": 500, "points": 15},
     *   {"min": 500, "max": 1000, "points": 30},
     *   {"min": 1000, "max": null, "points": 50}
     * ]
     */
    private function calculateTieredPoints(?float $transactionAmount): int
    {
        if ($transactionAmount === null || empty($this->tiers_config)) {
            return $this->points; // fallback
        }

        foreach ($this->tiers_config as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_INT_MAX;
            
            if ($transactionAmount >= $min && $transactionAmount < $max) {
                return (int) ($tier['points'] ?? 0);
            }
        }

        // إذا لم يُطابق أي مستوى، أرجع النقاط الافتراضية
        return $this->points;
    }

    /**
     * الحصول على وصف نوع الحساب
     */
    public function getCalculationTypeLabel(): string
    {
        if ($this->calculation_type instanceof CalculationType) {
            return $this->calculation_type->label();
        }
        
        $type = CalculationType::tryFrom($this->calculation_type);
        return $type ? $type->label() : 'ثابت';
    }

    /**
     * الحصول على وصف طريقة الحساب
     */
    public function getCalculationDescription(): string
    {
        $calcType = $this->calculation_type instanceof CalculationType 
            ? $this->calculation_type 
            : CalculationType::tryFrom($this->calculation_type) ?? CalculationType::FIXED;

        return match($calcType) {
            CalculationType::FIXED => $this->points . ' نقطة لكل عملية',
            CalculationType::PERCENTAGE => $this->calculation_value . '% من قيمة المعاملة',
            CalculationType::PER_AMOUNT => 'نقطة لكل ' . number_format($this->calculation_value, 2) . ' ريال',
            CalculationType::TIERED => 'نقاط متدرجة حسب قيمة المعاملة',
        };
    }

    /**
     * الحصول على النقاط لعملية معينة
     * 
     * @param string $userType نوع المستخدم
     * @param string $actionType نوع العملية
     * @param float|null $transactionAmount قيمة المعاملة (اختياري)
     * @param int|null $branchId معرف الفرع (اختياري)
     * @return int|null عدد النقاط أو null إذا لم يُوجد إعداد
     */
    public static function getPointsForAction(
        string $userType, 
        string $actionType, 
        ?float $transactionAmount = null, 
        ?int $branchId = null
    ): ?int {
        $setting = static::active()
            ->forUserType($userType)
            ->forAction($actionType)
            ->forBranch($branchId)
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if (!$setting) {
            return null;
        }

        return $setting->calculatePoints($transactionAmount);
    }

    /**
     * الحصول على إعداد النقاط لعملية معينة
     */
    public static function getSettingForAction(
        string $userType, 
        string $actionType, 
        ?int $branchId = null
    ): ?self {
        return static::active()
            ->forUserType($userType)
            ->forAction($actionType)
            ->forBranch($branchId)
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();
    }

    /**
     * الحصول على إعداد النقاط (فعّال أو معطّل)
     * مفيد للتحقق من وجود إعداد معطّل وإرجاع سبب التعطيل
     */
    public static function getSettingForActionIncludingDisabled(
        string $userType, 
        string $actionType, 
        ?int $branchId = null
    ): ?self {
        return static::forUserType($userType)
            ->forAction($actionType)
            ->forBranch($branchId)
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();
    }
}
