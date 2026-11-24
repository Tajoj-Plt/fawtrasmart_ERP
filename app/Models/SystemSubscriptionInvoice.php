<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** فاتورة اشتراك المنشأة في النظام. */
class SystemSubscriptionInvoice extends Model
{
    protected $table = 'system_subscription_invoices';

    protected $fillable = [
        'subscription_id', 'invoice_number', 'amount', 'currency', 'status',
        'issued_at', 'due_at', 'paid_at', 'payment_method', 'reference', 'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
        'paid_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public const STATUSES = [
        'paid' => ['label' => 'مدفوعة', 'color' => 'on'],
        'unpaid' => ['label' => 'غير مدفوعة', 'color' => 'warn'],
        'overdue' => ['label' => 'متأخّرة', 'color' => 'dang'],
        'refunded' => ['label' => 'مستردَّة', 'color' => 'off'],
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SystemSubscription::class, 'subscription_id');
    }

    /** الحالات مترجَمة — قيم الثابت أعلاه مفاتيح احتياطية لا غير. */
    public static function statuses(): array
    {
        $out = [];

        foreach (self::STATUSES as $status => $meta) {
            $key = 'settings.subscription.invoice_statuses.'.$status;
            $label = __($key);

            $out[$status] = [
                'label' => $label === $key ? $meta['label'] : $label,
                'color' => $meta['color'],
            ];
        }

        return $out;
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'off';
    }

    /** الحالة الفعلية بعد احتساب التأخّر — نصّاً ولوناً. */
    public function effectiveStatusLabel(): string
    {
        $status = $this->effectiveStatus();

        return self::statuses()[$status]['label'] ?? $status;
    }

    public function effectiveStatusColor(): string
    {
        return self::STATUSES[$this->effectiveStatus()]['color'] ?? 'off';
    }

    /**
     * فاتورة غير مدفوعة تجاوز تاريخ استحقاقها = متأخّرة.
     *
     * تُحسَب ولا تُخزَّن: الحالة المخزَّنة تتقادم بمرور اليوم، فتبقى
     * «غير مدفوعة» بينما هي متأخّرة منذ شهر.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === 'unpaid' && $this->due_at && $this->due_at->endOfDay()->isPast()) {
            return 'overdue';
        }

        return $this->status;
    }
}
