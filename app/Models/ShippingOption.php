<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * خيار شحن يُعرض في الفاتورة.
 *
 * تنبيه على العمود `tax`: هو **رمز معاملة ضريبية** لا مبلغ ولا نسبة —
 * الترحيل يوثّقه صراحةً (`1: القيمة المضافة، 2: القيمة الصفرية`). وكان
 * النموذج يحوّله `decimal:2` والشاشة تعرضه `number_format(...)`، فيقرأ
 * المستخدم «1.00» في عمود عنوانه «الضريبة» ويظنّه مبلغاً.
 */
class ShippingOption extends Model
{
    protected $table = 'shipping_options';

    /** الحالة: القالب يستعمل 1/2 لا 1/0، و`updateStatus` يقلب بينهما. */
    public const STATUS_ACTIVE = 1;

    public const STATUS_INACTIVE = 2;

    /** رموز المعاملة الضريبية — التسميات في `sales_settings.shipping.tax_types`. */
    public const TAX_VAT = 1;

    public const TAX_ZERO = 2;

    protected $fillable = ['name', 'description', 'cost', 'tax', 'status', 'display_order', 'default_account_id'];

    protected $casts = [
        'cost' => 'decimal:2',
        'tax' => 'integer',
        'status' => 'integer',
        'display_order' => 'integer',
    ];

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** تسمية المعاملة الضريبية بلغة الواجهة. */
    public function taxLabel(): string
    {
        return __('sales_settings.shipping.tax_types.'.($this->tax ?: self::TAX_VAT));
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
