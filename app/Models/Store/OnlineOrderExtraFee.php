<?php

namespace App\Models\Store;

use App\Enums\ExtraFeeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل الرسوم الإضافية للطلبات
 * Online Order Extra Fee Model
 *
 * يدير الرسوم الإضافية المخصصة للطلبات الإلكترونية
 * يحتوي على معلومات الرسوم ونوعها وقابليتها للاسترداد
 */
class OnlineOrderExtraFee extends Model
{
    use HasFactory;

    protected $table = 'online_order_extra_fees';

    protected $fillable = [
        'order_id',
        'name',
        'description',
        'amount',
        'type',
        'is_taxable',
        'is_refundable',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'type' => ExtraFeeType::class,
        'is_taxable' => 'boolean',
        'is_refundable' => 'boolean',
    ];

    /**
     * العلاقة مع الطلب
     */
    public function order()
    {
        return $this->belongsTo(OnlineOrder::class, 'order_id');
    }

    /**
     * رسوم طلب محدد
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * فلترة حسب النوع
     */
    public function scopeByType($query, ExtraFeeType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * الرسوم القابلة للضريبة
     */
    public function scopeTaxable($query)
    {
        return $query->where('is_taxable', true);
    }

    /**
     * الرسوم القابلة للاسترداد
     */
    public function scopeRefundable($query)
    {
        return $query->where('is_refundable', true);
    }

    /**
     * الرسوم الثابتة
     */
    public function scopeFixed($query)
    {
        return $query->where('type', ExtraFeeType::FIXED);
    }

    /**
     * الرسوم بالنسبة المئوية
     */
    public function scopePercentage($query)
    {
        return $query->where('type', ExtraFeeType::PERCENTAGE);
    }

    /**
     * إنشاء رسوم إضافية لطلب
     */
    public static function createForOrder(
        $orderId,
        $name,
        $amount,
        $type = ExtraFeeType::FIXED,
        $description = null,
        $nameEn = null,
        $isTaxable = false,
        $isRefundable = true
    ): self {
        return self::create([
            'order_id' => $orderId,
            'name' => $name,
            'name_en' => $nameEn,
            'description' => $description,
            'amount' => $amount,
            'type' => $type,
            'is_taxable' => $isTaxable,
            'is_refundable' => $isRefundable,
        ]);
    }

    /**
     * حساب المبلغ النهائي للرسوم (مع الضريبة إن وجدت)
     */
    public function calculateFinalAmount($taxRate = 0): float
    {
        $baseAmount = $this->amount;

        if ($this->is_taxable && $taxRate > 0) {
            $tax = $baseAmount * ($taxRate / 100);

            return $baseAmount + $tax;
        }

        return $baseAmount;
    }

    /**
     * حساب مبلغ الضريبة على الرسوم
     */
    public function calculateTax($taxRate = 0): float
    {
        if (! $this->is_taxable || $taxRate <= 0) {
            return 0;
        }

        return $this->amount * ($taxRate / 100);
    }

    /**
     * الحصول على الاسم المناسب حسب اللغة
     */
    public function getNameForLocale($locale = 'ar'): string
    {
        if ($locale === 'en' && $this->name_en) {
            return $this->name_en;
        }

        return $this->name;
    }

    /**
     * التحقق من نوع الرسوم
     */
    public function isFixed(): bool
    {
        return $this->type === ExtraFeeType::FIXED;
    }

    public function isPercentage(): bool
    {
        return $this->type === ExtraFeeType::PERCENTAGE;
    }

    /**
     * التحقق من إمكانية فرض الضريبة
     */
    public function isTaxable(): bool
    {
        return $this->is_taxable;
    }

    /**
     * التحقق من إمكانية الاسترداد
     */
    public function isRefundable(): bool
    {
        return $this->is_refundable;
    }

    /**
     * الحصول على إجمالي الرسوم الإضافية لطلب
     */
    public static function getTotalForOrder($orderId): float
    {
        return self::forOrder($orderId)->sum('amount');
    }

    /**
     * الحصول على إجمالي الرسوم القابلة للاسترداد لطلب
     */
    public static function getRefundableAmountForOrder($orderId): float
    {
        return self::forOrder($orderId)
            ->refundable()
            ->sum('amount');
    }

    /**
     * الحصول على إجمالي الضرائب على الرسوم الإضافية
     */
    public static function getTotalTaxForOrder($orderId, $taxRate = 15): float
    {
        $fees = self::forOrder($orderId)->taxable()->get();
        $totalTax = 0;

        foreach ($fees as $fee) {
            $totalTax += $fee->calculateTax($taxRate);
        }

        return $totalTax;
    }

    /**
     * تطبيق رسوم إضافية متعددة على طلب
     */
    public static function applyMultipleFees($orderId, array $fees): void
    {
        foreach ($fees as $fee) {
            self::createForOrder(
                $orderId,
                $fee['name'],
                $fee['amount'],
                $fee['type'] ?? ExtraFeeType::FIXED,
                $fee['description'] ?? null,
                $fee['name_en'] ?? null,
                $fee['is_taxable'] ?? false,
                $fee['is_refundable'] ?? true
            );
        }
    }

    /**
     * مسح جميع الرسوم الإضافية لطلب
     */
    public static function clearForOrder($orderId): int
    {
        return self::forOrder($orderId)->delete();
    }

    /**
     * الحصول على وصف تفصيلي للرسوم
     */
    public function getDetailedDescriptionAttribute(): string
    {
        $description = $this->name;

        if ($this->type === ExtraFeeType::PERCENTAGE) {
            $description .= " ({$this->amount}%)";
        } else {
            $description .= " ({$this->amount} ريال)";
        }

        if ($this->is_taxable) {
            $description .= ' - خاضع للضريبة';
        }

        if (! $this->is_refundable) {
            $description .= ' - غير قابل للاسترداد';
        }

        return $description;
    }
}
