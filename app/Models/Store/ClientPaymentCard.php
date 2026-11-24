<?php

namespace App\Models\Store;

use App\Models\Client;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * نموذج بطاقات الدفع للعملاء
 * يحفظ بيانات البطاقات المحفوظة للعملاء لاستخدامها في الدفع
 */
class ClientPaymentCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_payment_cards';

    protected $fillable = [
        'client_id',
        'payment_method_id',
        'card_type',
        'card_holder_name',
        'card_last_four',
        'card_bin',
        'expiry_month',
        'expiry_year',
        'token',
        'gateway_card_id',
        'gateway_name',
        'is_default',
        'is_active',
        'nickname',
        'billing_address',
        'billing_city',
        'billing_country',
        'billing_postal_code',
        'metadata',
        'last_used_at',
    ];

    protected $casts = [
        'expiry_month' => 'integer',
        'expiry_year' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
        'gateway_card_id',
    ];

    /**
     * الحصول على أنواع البطاقات المتاحة
     */
    public static function cardTypes(): array
    {
        return [
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'mada' => 'مدى',
            'amex' => 'American Express',
            'other' => 'أخرى',
        ];
    }

    /**
     * العلاقة مع العميل
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع طريقة الدفع
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * الحصول على رقم البطاقة المخفي
     */
    public function getMaskedNumberAttribute(): string
    {
        return '**** **** **** '.$this->card_last_four;
    }

    /**
     * الحصول على تاريخ انتهاء الصلاحية
     */
    public function getExpiryDateAttribute(): string
    {
        return sprintf('%02d/%d', $this->expiry_month, $this->expiry_year);
    }

    /**
     * التحقق من صلاحية البطاقة
     */
    public function isExpired(): bool
    {
        $now = now();
        $expiryDate = Carbon::createFromDate($this->expiry_year, $this->expiry_month, 1)->endOfMonth();

        return $now->greaterThan($expiryDate);
    }

    /**
     * التحقق من أن البطاقة ستنتهي قريباً (خلال 30 يوم)
     */
    public function isExpiringSoon(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        $now = now();
        $expiryDate = Carbon::createFromDate($this->expiry_year, $this->expiry_month, 1)->endOfMonth();

        return $now->diffInDays($expiryDate) <= 30;
    }

    /**
     * الحصول على أيقونة نوع البطاقة
     */
    public function getCardIconAttribute(): string
    {
        return match ($this->card_type) {
            'visa' => 'cc-visa',
            'mastercard' => 'cc-mastercard',
            'mada' => 'credit-card',
            'amex' => 'cc-amex',
            default => 'credit-card',
        };
    }

    /**
     * الحصول على اسم نوع البطاقة
     */
    public function getCardTypeNameAttribute(): string
    {
        return self::cardTypes()[$this->card_type] ?? 'غير معروف';
    }

    /**
     * تحديث آخر استخدام
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * تعيين البطاقة كافتراضية
     */
    public function setAsDefault(): void
    {
        // إلغاء الافتراضي من البطاقات الأخرى
        static::where('client_id', $this->client_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Scope للبطاقات النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope للبطاقات غير منتهية الصلاحية
     */
    public function scopeValid($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->where('expiry_year', '>', $now->year)
                ->orWhere(function ($q2) use ($now) {
                    $q2->where('expiry_year', $now->year)
                        ->where('expiry_month', '>=', $now->month);
                });
        });
    }

    /**
     * Scope للبطاقة الافتراضية
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
