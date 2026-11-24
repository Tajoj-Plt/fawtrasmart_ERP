<?php

namespace App\Models\Ecosystem;

use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class UserAchievement extends Model
{
    // الجدول لا يحتوي على updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'achievement_id',
        'user_type',
        'employee_id',
        'client_id',
        'achieved_at',
        'achievement_value',
        'points_awarded',
        'notified',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'achieved_at' => 'datetime',
        'achievement_value' => 'integer',
        'points_awarded' => 'integer',
        'notified' => 'boolean',
    ];

    // =====================================================
    // Boot - منح نقاط المكافأة تلقائياً
    // =====================================================

    protected static function boot()
    {
        parent::boot();

        /**
         * عند إنشاء سجل إنجاز جديد:
         * → إنشاء معاملة نقاط مكافأة تلقائية
         * → ربطها بالإنجاز عبر reference (morphTo) في PointsTransaction
         * 
         * لماذا morphTo وليس transaction_id؟
         * لأن الإنجاز يعتمد على عدة معاملات (مثلاً 50 فاتورة)
         * فلا يمكن ربطه بمعاملة واحدة. المعاملة الجديدة هي فقط مكافأة الإنجاز.
         */
        static::created(function (UserAchievement $userAchievement) {
            if ($userAchievement->points_awarded > 0) {
                $userAchievement->grantBonusPoints();
            }
        });
    }

    // =====================================================
    // Bonus Points Logic
    // =====================================================

    /**
     * إنشاء معاملة نقاط المكافأة تلقائياً عند تحقيق الإنجاز
     * 
     * يتم الربط عبر reference_type و reference_id في جدول points_transactions
     * حيث reference_type = UserAchievement و reference_id = user_achievement.id
     * 
     * action_type = 'achievement_bonus' لا يؤثر على أي شرط إنجاز آخر
     * (لن يسبب حلقة لا نهائية)
     */
    public function grantBonusPoints(): ?PointsTransaction
    {
        try {
            $userTypeEnum = $this->user_type instanceof UserType
                ? $this->user_type
                : UserType::from($this->user_type);

            $idColumn = $userTypeEnum === UserType::EMPLOYEE ? 'employee_id' : 'client_id';
            $userId = $userTypeEnum === UserType::EMPLOYEE ? $this->employee_id : $this->client_id;

            $month = now()->month;
            $year = now()->year;

            // حساب الرصيد الحالي
            $balanceBefore = PointsTransaction::where('user_type', $userTypeEnum)
                ->where($idColumn, $userId)
                ->where('points_month', $month)
                ->where('points_year', $year)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points');

            // جلب اسم الإنجاز
            $achievement = $this->relationLoaded('achievement')
                ? $this->achievement
                : Achievement::find($this->achievement_id);

            $achievementName = $achievement->name ?? 'إنجاز';

            // إنشاء معاملة نقاط المكافأة مع ربطها بالإنجاز عبر reference
            $transaction = PointsTransaction::create([
                'user_type' => $userTypeEnum->value,
                'employee_id' => $this->employee_id,
                'client_id' => $this->client_id,
                'action_type' => 'achievement_bonus',
                'points' => $this->points_awarded,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore + $this->points_awarded,
                'description' => '🏆 مكافأة إنجاز: ' . $achievementName,
                'reference_type' => self::class,
                'reference_id' => $this->id,
                'status' => TransactionStatus::COMPLETED->value,
                'points_month' => $month,
                'points_year' => $year,
                'created_by' => auth()->id(),
            ]);

            Log::info('Achievement bonus points granted', [
                'user_achievement_id' => $this->id,
                'achievement_name' => $achievementName,
                'points' => $this->points_awarded,
                'transaction_id' => $transaction->id,
                'user_type' => $userTypeEnum->value,
                'user_id' => $userId,
            ]);

            return $transaction;
        } catch (\Exception $e) {
            Log::error('Failed to grant achievement bonus points', [
                'user_achievement_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * جلب معاملة نقاط المكافأة المرتبطة بهذا الإنجاز
     */
    public function bonusTransaction()
    {
        return PointsTransaction::where('reference_type', self::class)
            ->where('reference_id', $this->id)
            ->first();
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * معاملة نقاط المكافأة (مربوطة عبر reference في PointsTransaction)
     */
    public function transaction()
    {
        return $this->morphOne(PointsTransaction::class, 'reference');
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

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('achieved_at', '>=', now()->subDays($days));
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function getUser()
    {
        return $this->user_type === UserType::EMPLOYEE 
            ? $this->employee 
            : $this->client;
    }
}
