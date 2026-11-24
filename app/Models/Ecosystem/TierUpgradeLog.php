<?php

namespace App\Models\Ecosystem;

use App\Enums\UpgradeType;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل ترقيات المستويات
 * 
 * upgrade_type:
 * - 'manual' = يدوي: عند إضافة/تعديل النقاط من الواجهة
 * - 'automatic' = تلقائي: عند حذف/إلغاء المعاملات أو العمليات التلقائية من النظام
 */
class TierUpgradeLog extends Model
{
    protected $table = 'tier_upgrades_log';

    public $timestamps = false;

    protected $fillable = [
        'user_type',
        'employee_id',
        'client_id',
        'old_tier_id',
        'new_tier_id',
        'points_at_upgrade',
        'upgrade_type', // 'automatic' = تلقائي من النظام، 'manual' = يدوي من الواجهة
        'upgraded_by',
        'created_at',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'upgrade_type' => UpgradeType::class,
        'points_at_upgrade' => 'integer',
        'created_at' => 'datetime',
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

    public function oldTier(): BelongsTo
    {
        return $this->belongsTo(PointsTier::class, 'old_tier_id');
    }

    public function newTier(): BelongsTo
    {
        return $this->belongsTo(PointsTier::class, 'new_tier_id');
    }

    public function upgradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'upgraded_by');
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

    public function scopeAutomatic($query)
    {
        return $query->where('upgrade_type', UpgradeType::AUTOMATIC->value);
    }

    public function scopeManual($query)
    {
        return $query->where('upgrade_type', UpgradeType::MANUAL->value);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isUpgrade(): bool
    {
        if (!$this->oldTier || !$this->newTier) {
            return true;
        }

        return $this->newTier->min_points > $this->oldTier->min_points;
    }

    public function isDowngrade(): bool
    {
        if (!$this->oldTier || !$this->newTier) {
            return false;
        }

        return $this->newTier->min_points < $this->oldTier->min_points;
    }

    public function getUser()
    {
        return $this->user_type === UserType::EMPLOYEE
            ? $this->employee
            : $this->client;
    }
}
