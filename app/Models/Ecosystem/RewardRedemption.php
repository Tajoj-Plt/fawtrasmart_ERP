<?php

namespace App\Models\Ecosystem;

use App\Enums\DeliveryMethod;
use App\Enums\RedemptionStatus;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    protected $fillable = [
        'redemption_number',
        'reward_id',
        'user_type',
        'employee_id',
        'client_id',
        'quantity',
        'points_used',
        'reward_value',
        'status',
        'applied_to_amount',
        'calculated_discount',
        'used_in_invoice_id',
        'voucher_code',
        'voucher_serial',
        'voucher_pin',
        'voucher_used',
        'voucher_used_at',
        'voucher_expires_at',
        'voucher_status',
        'delivery_method',
        'delivery_address',
        'notes',
        'approved_at',
        'approved_by',
        'processed_at',
        'processed_by',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'expires_at',
        'metadata',
        'branch_id',
        'store_house_id',
        'treasury_account_id',
        'payroll_run_id',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'status' => RedemptionStatus::class,
        'delivery_method' => DeliveryMethod::class,
        'points_used' => 'integer',
        'quantity' => 'integer',
        'reward_value' => 'decimal:2',
        'applied_to_amount' => 'decimal:2',
        'calculated_discount' => 'decimal:2',
        'used_in_invoice_id' => 'integer',
        'voucher_used' => 'boolean',
        'voucher_used_at' => 'datetime',
        'voucher_expires_at' => 'date',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /** صرفةُ الراتب التي دُفعت فيها المكافأةُ النقدية للموظف. */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PayrollRun::class, 'payroll_run_id');
    }

    /** سطرُ الاستحقاق المولَّد لها على قسيمة الراتب (إن وُجد). */
    public function salaryItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\SalaryItem::class, 'reward_redemption_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function scopePending($query)
    {
        return $query->where('status', RedemptionStatus::PENDING->value);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', RedemptionStatus::APPROVED->value);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', RedemptionStatus::DELIVERED->value);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isPending(): bool
    {
        return $this->status === RedemptionStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === RedemptionStatus::APPROVED;
    }

    public function isDelivered(): bool
    {
        return $this->status === RedemptionStatus::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === RedemptionStatus::CANCELLED;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function approve(User $approver): void
    {
        $this->update([
            'status' => RedemptionStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => RedemptionStatus::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => RedemptionStatus::CANCELLED,
        ]);
    }

    public function getUser()
    {
        return $this->user_type === UserType::EMPLOYEE
            ? $this->employee
            : $this->client;
    }

    /**
     * هل القسيمة نشطة وقابلة للاستخدام؟
     */
    public function isVoucherActive(): bool
    {
        if (!$this->voucher_code) {
            return false;
        }

        if ($this->voucher_used) {
            return false;
        }

        if ($this->voucher_status && $this->voucher_status !== 'active') {
            return false;
        }

        if ($this->voucher_expires_at && $this->voucher_expires_at->isPast()) {
            return false;
        }

        // يجب أن يكون الطلب موافق عليه أو مسلّم
        if (!in_array($this->status->value, ['approved', 'processing', 'delivered'])) {
            return false;
        }

        return true;
    }

    /**
     * استخدام القسيمة
     */
    public function useVoucher(float $invoiceAmount, ?int $invoiceId = null): float
    {
        $reward = $this->reward;
        $discount = $reward->calculateDiscount($invoiceAmount);

        $this->update([
            'voucher_used' => true,
            'voucher_used_at' => now(),
            'voucher_status' => 'used',
            'applied_to_amount' => $invoiceAmount,
            'calculated_discount' => $discount,
            'used_in_invoice_id' => $invoiceId,
        ]);

        return $discount;
    }

    /**
     * الحصول على تسمية حالة القسيمة
     */
    public function getVoucherStatusLabelAttribute(): string
    {
        return match ($this->voucher_status) {
            'active' => 'نشطة',
            'used' => 'مستخدمة',
            'expired' => 'منتهية',
            'cancelled' => 'ملغاة',
            default => 'غير معروف',
        };
    }

    /**
     * الحصول على لون حالة القسيمة
     */
    public function getVoucherStatusColorAttribute(): string
    {
        return match ($this->voucher_status) {
            'active' => 'success',
            'used' => 'info',
            'expired' => 'warning',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($redemption) {
            if (!$redemption->redemption_number) {
                $redemption->redemption_number = 'RDM-' . strtoupper(uniqid());
            }
        });
    }
}
