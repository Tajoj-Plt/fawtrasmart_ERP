<?php

namespace App\Models\Ecosystem;

use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * نموذج معاملات النقاط
 * 
 * ملاحظة: الإضافة والتعديل من الواجهة تعتبر عمليات يدوية (manual)
 * العمليات التلقائية (automatic) تحدث من النظام عند الحذف أو الإلغاء
 */
class PointsTransaction extends Model
{
    protected $fillable = [
        'user_type',
        'employee_id',
        'client_id',
        'action_type',
        'points',
        'balance_before',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'branch_id',
        'created_by',
        'status',
        'expires_at',
        'points_month',
        'points_year',
        'metadata',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'status' => TransactionStatus::class,
        'points' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'expires_at' => 'datetime',
        'points_month' => 'integer',
        'points_year' => 'integer',
        'metadata' => 'array',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
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

    public function scopeCompleted($query)
    {
        return $query->where('status', TransactionStatus::COMPLETED->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', TransactionStatus::PENDING->value);
    }

    public function scopeEarned($query)
    {
        return $query->where('points', '>', 0);
    }

    public function scopeSpent($query)
    {
        return $query->where('points', '<', 0);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expires_at')
                     ->where('expires_at', '<=', now()->addDays($days))
                     ->where('status', TransactionStatus::COMPLETED->value);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope للبحث عن معاملات شهر محدد
     */
    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('points_month', $month)->where('points_year', $year);
    }

    /**
     * Scope للبحث عن معاملات الشهر الحالي
     */
    public function scopeCurrentMonth($query)
    {
        return $query->where('points_month', now()->month)->where('points_year', now()->year);
    }

    /**
     * Scope للحصول على نقاط موظف في شهر محدد
     */
    public function scopeEmployeeMonthlyPoints($query, int $employeeId, int $month = null, int $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        return $query->where('user_type', UserType::EMPLOYEE->value)
                     ->where('employee_id', $employeeId)
                     ->where('points_month', $month)
                     ->where('points_year', $year)
                     ->where('status', TransactionStatus::COMPLETED->value);
    }

    /**
     * Scope للحصول على نقاط عميل في شهر محدد
     */
    public function scopeClientMonthlyPoints($query, int $clientId, int $month = null, int $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        return $query->where('user_type', UserType::CLIENT->value)
                     ->where('client_id', $clientId)
                     ->where('points_month', $month)
                     ->where('points_year', $year)
                     ->where('status', TransactionStatus::COMPLETED->value);
    }

    // =====================================================
    // Static Methods for Monthly Points
    // =====================================================

    /**
     * الحصول على إجمالي نقاط موظف للشهر الحالي
     */
    public static function getEmployeeMonthlyEarned(int $employeeId, int $month = null, int $year = null): int
    {
        return static::employeeMonthlyPoints($employeeId, $month, $year)
                     ->where('points', '>', 0)
                     ->sum('points');
    }

    /**
     * الحصول على إجمالي نقاط عميل للشهر الحالي
     */
    public static function getClientMonthlyEarned(int $clientId, int $month = null, int $year = null): int
    {
        return static::clientMonthlyPoints($clientId, $month, $year)
                     ->where('points', '>', 0)
                     ->sum('points');
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isEarned(): bool
    {
        return $this->points > 0;
    }

    public function isSpent(): bool
    {
        return $this->points < 0;
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::COMPLETED;
    }

    public function isExpired(): bool
    {
        return $this->status === TransactionStatus::EXPIRED 
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function getAbsolutePoints(): int
    {
        return abs($this->points);
    }

    public function getUser()
    {
        return $this->user_type === UserType::EMPLOYEE 
            ? $this->employee 
            : $this->client;
    }
}
