<?php

namespace App\Models\Ecosystem;

use App\Enums\RewardType;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Product; // Add Product import
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'description',
        'user_type',
        'points_required',
        'reward_type',
        'reward_value',
        'leave_type_id',    // نوع الإجازة لمكافأة «إجازة»
        'max_discount_value',
        'min_order_amount',
        'validity_days',
        'product_id',       // Added
        'quantity_available',
        'quantity_redeemed',
        'max_per_user',     // Added
        'min_tier_id',
        'image',
        'terms_conditions',
        'is_active',
        'is_featured',      // Added
        'starts_at',
        'expires_at',       // Renamed from ends_at
        'branch_id',
        'created_by',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'reward_type' => RewardType::class,
        'points_required' => 'integer',
        'reward_value' => 'decimal:2',
        'max_discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'validity_days' => 'integer',
        'product_id' => 'integer',      // Added
        'quantity_available' => 'integer',
        'quantity_redeemed' => 'integer',
        'max_per_user' => 'integer',    // Added
        'starts_at' => 'datetime',      // User says timestamp
        'expires_at' => 'datetime',     // Renamed and timestamp
        'is_active' => 'boolean',
        'is_featured' => 'boolean',     // Added
        'terms_conditions' => 'array',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** نوعُ الإجازة الذي تُضاف إليه أيامُ مكافأة «إجازة» عند اعتمادها. */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LeaveType::class, 'leave_type_id');
    }

    public function minTier(): BelongsTo
    {
        return $this->belongsTo(PointsTier::class, 'min_tier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeForUserType($query, string|UserType $userType)
    {
        $userTypeValue = $userType instanceof UserType ? $userType->value : $userType;
        return $query->where('user_type', $userTypeValue);
    }

    public function scopeAvailable($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('quantity_available')
                ->orWhereRaw('quantity_available > quantity_redeemed');
        });
    }

    public function scopeAffordable($query, int $points)
    {
        return $query->where('points_required', '<=', $points);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function isAvailable(): bool
    {
        if ($this->quantity_available === null) {
            return true;
        }

        return $this->quantity_available > $this->quantity_redeemed;
    }

    public function getRemainingQuantity(): ?int
    {
        if ($this->quantity_available === null) {
            return null;
        }

        return max(0, $this->quantity_available - $this->quantity_redeemed);
    }

    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function canBeRedeemedBy($user, int $userPoints, ?int $userTierId = null): bool
    {
        if (!$this->isActive() || !$this->isAvailable()) {
            return false;
        }

        if ($this->points_required > $userPoints) {
            return false;
        }

        if ($this->min_tier_id && $userTierId) {
            $minTier = $this->minTier;
            $userTier = PointsTier::find($userTierId);

            if ($userTier && $minTier && $userTier->min_points < $minTier->min_points) {
                return false;
            }
        }

        return true;
    }

    /**
     * هل هذه المكافأة يجب أن تكون كميتها 1 فقط؟
     * الخصومات، القسائم، الشحن المجاني، والإجازات لا يمكن تكرارها
     */
    public function singleQuantityOnly(): bool
    {
        return in_array($this->reward_type->value, [
            'discount_percentage',
            'discount_amount',
            'voucher',
            'free_shipping',
            'leave_day',
        ]);
    }

    /**
     * هل هذه المكافأة من نوع الخصم/القسيمة التي تحتاج كود؟
     */
    public function needsVoucherCode(): bool
    {
        return in_array($this->reward_type->value, ['discount_percentage', 'discount_amount', 'voucher']);
    }

    /**
     * هل هذه المكافأة تحتاج مبلغ فاتورة لحساب الخصم؟
     */
    public function needsInvoiceAmount(): bool
    {
        return $this->reward_type->value === 'discount_percentage';
    }

    /**
     * حساب قيمة الخصم بناءً على مبلغ الفاتورة
     */
    public function calculateDiscount(float $invoiceAmount): float
    {
        if ($this->reward_type->value === 'discount_percentage') {
            $discount = $invoiceAmount * ($this->reward_value / 100);
            // تطبيق الحد الأقصى إذا كان محدداً
            if ($this->max_discount_value && $discount > $this->max_discount_value) {
                $discount = (float) $this->max_discount_value;
            }
            return round($discount, 2);
        }

        if ($this->reward_type->value === 'discount_amount') {
            return min((float) $this->reward_value, $invoiceAmount);
        }

        if ($this->reward_type->value === 'voucher') {
            return min((float) $this->reward_value, $invoiceAmount);
        }

        return 0;
    }

    /**
     * التحقق من أن مبلغ الفاتورة يحقق الحد الأدنى
     */
    public function meetsMinOrderAmount(float $invoiceAmount): bool
    {
        if (!$this->min_order_amount) {
            return true;
        }
        return $invoiceAmount >= $this->min_order_amount;
    }

    public function getDisplayName(?string $locale = null): string
    {
        if ($locale === 'en' && $this->name_en) {
            return $this->name_en;
        }
        return $this->name;
    }

    public function incrementRedeemed(): void
    {
        $this->increment('quantity_redeemed');
    }

    public function getUserTypeLabelAttribute(): string
    {
        if ($this->user_type === 'both') {
            return 'الجميع (موظفين وعملاء)';
        }

        return UserType::tryFrom((string) $this->user_type)?->label() ?? (string) $this->user_type;
    }
}
