<?php

namespace App\Models;

use App\Enums\PaymentMethod as PaymentMethodEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_methods';

    protected $fillable = [
        'name',
        'name_en',
        'code',
        'type',
        'description',
        'icon',
        'logo',
        // كان غائباً: مفتاحُ التفعيل في الشاشة يكتب `status` و`is_active` معاً،
        // فيسقط الأوّل صامتاً ويبقى «مفعّل» — ونصفُ النظام يقرأ هذا والنصفُ ذاك
        'status',
        'is_active',
        'is_online',
        'is_default',
        'requires_card',
        'requires_bank_details',
        'min_amount',
        'max_amount',
        'fee_type',
        'fee_amount',
        'instructions',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_iban',
        'sort_order',
        'public_key',
        'secret_key',
        'webhook_url',
        'webhook_secret',
        'gateway_mode',
        'config_file_path',
        'config_file_generated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'is_default' => 'boolean',
        'requires_card' => 'boolean',
        'requires_bank_details' => 'boolean',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'sort_order' => 'integer',
        // معمَّى بـ`APP_KEY`: العمود يعيش في قاعدة تُنسَخ احتياطياً، ومفتاح
        // صريح فيه يعني أرشيفاً يحمل مفاتيح بوابة الدفع كما هي
        'secret_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'config_file_generated_at' => 'datetime',
    ];

    /**
     * المفاتيح لا تُسلسَل مع النموذج — رد JSON واحد يسرّبها كلّها.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'secret_key',
        'webhook_secret',
    ];

    public const GATEWAY_MODES = ['test', 'live'];

    /**
     * هل رُبطت الطريقة ببوابة فعلاً؟ مفتاح عام وحده لا يكفي للتشغيل.
     */
    public function hasGatewayCredentials(): bool
    {
        return filled($this->public_key) && filled($this->secret_key);
    }

    /**
     * قيمة مقنَّعة للعرض — آخر أربعة محارف تكفي للتعرّف على المفتاح
     * دون كشفه. إظهار المفتاح كاملاً في شاشة إعدادات يعني تسريبه لكل
     * من يمرّ خلف الكتف أو يلتقط صورة للشاشة.
     */
    public function maskedSecret(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return str_repeat('•', 8) . (strlen($value) > 4 ? substr($value, -4) : '');
    }

    /**
     * الحصول على Enum المرتبط بهذه الطريقة
     */
    public function getTypeEnumAttribute(): ?PaymentMethodEnum
    {
        return PaymentMethodEnum::tryFrom($this->type);
    }

    /**
     * الحصول على التسمية العربية من Enum
     */
    public function getTypeLabelAttribute(): string
    {
        $enum = $this->type_enum;
        return $enum ? $enum->label() : $this->name;
    }

    /**
     * الحصول على التسمية الإنجليزية من Enum
     */
    public function getTypeLabelEnAttribute(): string
    {
        $enum = $this->type_enum;
        return $enum ? $enum->labelEn() : ($this->name_en ?? $this->name);
    }

    /**
     * الحصول على الأيقونة من Enum
     */
    public function getTypeIconAttribute(): string
    {
        $enum = $this->type_enum;
        return $enum ? $enum->icon() : 'credit-card';
    }

    /**
     * التحقق من أن الطريقة تتطلب دفع فوري
     */
    public function requiresImmediatePayment(): bool
    {
        $enum = $this->type_enum;
        return $enum ? $enum->requiresImmediatePayment() : false;
    }

    /**
     * التحقق إذا كانت طريقة الدفع نقدية
     * يعتمد على code أو id لأن حقل type قد يكون غير محدث
     */
    public function isCash(): bool
    {
        // الكاش: id=1 أو code='cash'
        return $this->id == 1 || $this->code == 'cash';
    }

    /**
     * التحقق إذا كانت طريقة الدفع إلكترونية (غير نقدية)
     * البطاقات والتحويلات والمحافظ الإلكترونية
     */
    public function isElectronic(): bool
    {
        // فيزا/مدى: code يحتوي على visa أو card أو mada
        // أو type = card
        $electronicCodes = ['visa', 'card', 'mada', 'mastercard'];
        foreach ($electronicCodes as $code) {
            if (stripos($this->code, $code) !== false) {
                return true;
            }
        }
        return $this->type == 'card';
    }

    /**
     * التحقق إذا كانت طريقة الدفع تحويل بنكي
     */
    public function isBankTransfer(): bool
    {
        return $this->code == 'thoyl-bnky' || stripos($this->code, 'bank') !== false || stripos($this->code, 'transfer') !== false;
    }

    /**
     * التحقق إذا كانت طريقة الدفع شيك
     */
    public function isCheck(): bool
    {
        return $this->code == 'shyk' || stripos($this->code, 'check') !== false || stripos($this->code, 'cheque') !== false;
    }

    /**
     * الحصول على طريقة الدفع الافتراضية
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('is_active', true)->orderBy('sort_order')->first();
    }

    /**
     * حساب رسوم المعاملة
     */
    public function calculateFee(float $amount): float
    {
        if (!$this->fee_amount || $this->fee_amount <= 0) {
            return 0;
        }

        if ($this->fee_type === 'percentage') {
            return $amount * ($this->fee_amount / 100);
        }

        return $this->fee_amount;
    }

    /**
     * التحقق من صلاحية المبلغ
     */
    public function isAmountValid(float $amount): bool
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }

    /**
     * Scope للطرق النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * الطرقُ التي يُسجَّل بها سندٌ بيدِ موظّف — لا بوّابةَ دفعٍ إلكترونية.
     *
     * `type = normal` و`status = active`: هذان الشرطان كانا مكتوبين في
     * `SalesPaymentController::manualPaymentMethods()` وحدَه. ولمّا صارت
     * بوابةُ العميل تعرض طرقَ السداد أيضاً (2026-09-19) احتاجت القائمةَ
     * نفسَها: طريقةٌ تُعرض للعميل ولا يقبلها سندُ القبض تعني إشعاراً
     * يختار الموظّفُ له طريقةً أخرى عند الاعتماد.
     *
     * وانتبه: `status` غير `is_active` — الأخيرةُ تشمل بوّابات الدفع
     * الإلكتروني (تمارا وغيرها) التي لا يُسجَّل بها سندٌ يدوي.
     */
    public function scopeManual($query)
    {
        return $query->where('type', 'normal')->where('status', 'active');
    }

    /**
     * Scope للطرق المتاحة للمتجر الإلكتروني
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope للترتيب
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * العلاقة مع الطلبات
     */
    public function orders()
    {
        return $this->hasMany(\App\Models\Store\OnlineOrder::class, 'payment_method_id');
    }

    /**
     * العلاقة مع بطاقات العملاء
     */
    public function clientCards()
    {
        return $this->hasMany(\App\Models\Store\ClientPaymentCard::class, 'payment_method_id');
    }
}

