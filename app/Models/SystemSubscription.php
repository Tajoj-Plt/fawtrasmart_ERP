<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مدّة اشتراك واحدة للمنشأة في النظام.
 *
 * لا يُخلط بـ`Subscription` القائم — ذاك اشتراك عميل في باقة تبيعها
 * المنشأة، وهذا اشتراك المنشأة نفسها في النظام.
 */
class SystemSubscription extends Model
{
    protected $table = 'system_subscriptions';

    protected $fillable = [
        'plan_name', 'plan_code', 'status', 'started_at', 'expires_at',
        'price', 'currency', 'billing_cycle',
        'max_users', 'max_branches', 'max_clients',
        'notes', 'created_by',
    ];

    protected $casts = [
        'started_at' => 'date',
        'expires_at' => 'date',
        'price' => 'decimal:2',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'max_clients' => 'integer',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE => ['label' => 'نشط', 'color' => 'on'],
        self::STATUS_TRIAL => ['label' => 'تجريبي', 'color' => 'info'],
        self::STATUS_EXPIRED => ['label' => 'منتهٍ', 'color' => 'dang'],
        self::STATUS_SUSPENDED => ['label' => 'موقوف', 'color' => 'warn'],
        self::STATUS_CANCELLED => ['label' => 'ملغى', 'color' => 'off'],
    ];

    public const CYCLES = [
        'monthly' => 'شهري',
        'quarterly' => 'ربع سنوي',
        'semi_annual' => 'نصف سنوي',
        'yearly' => 'سنوي',
        'lifetime' => 'دائم',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(SystemSubscriptionInvoice::class, 'subscription_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIAL])
            ->orderByDesc('started_at');
    }

    /**
     * الحالات بأسمائها المترجَمة.
     *
     * الثابتان أعلاه لا يقبلان نداء `__()`، فقيمهما العربية مفاتيح احتياطية
     * تُستعمل إن نقصت الترجمة، وهاتان الدالتان هما ما يُعرَض للمستخدم.
     */
    public static function statuses(): array
    {
        $out = [];

        foreach (self::STATUSES as $status => $meta) {
            $out[$status] = [
                'label' => self::label('subscription.statuses.'.$status, $meta['label']),
                'color' => $meta['color'],
            ];
        }

        return $out;
    }

    public static function cycles(): array
    {
        $out = [];

        foreach (self::CYCLES as $cycle => $fallback) {
            $out[$cycle] = self::label('subscription.cycles.'.$cycle, $fallback);
        }

        return $out;
    }

    private static function label(string $key, string $fallback): string
    {
        $full = 'settings.'.$key;
        $translated = __($full);

        return $translated === $full ? $fallback : $translated;
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'off';
    }

    public function cycleLabel(): string
    {
        return self::cycles()[$this->billing_cycle] ?? $this->billing_cycle;
    }

    /** اشتراك بلا تاريخ انتهاء = دائم، ولا معنى لعدّ أيامه. */
    public function isPerpetual(): bool
    {
        return $this->expires_at === null;
    }

    public function isExpired(): bool
    {
        return ! $this->isPerpetual() && $this->expires_at->endOfDay()->isPast();
    }

    /**
     * الأيام المتبقّية — سالبة إن انتهى، وnull إن كان دائماً.
     *
     * يُحسَب من نهاية يوم الانتهاء لا من بدايته: اشتراك ينتهي اليوم ما
     * زال صالحاً اليوم.
     */
    public function daysRemaining(): ?int
    {
        if ($this->isPerpetual()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expires_at->endOfDay(), false);
    }

    /** إجمالي أيام المدّة — لشريط التقدّم. */
    public function totalDays(): ?int
    {
        if ($this->isPerpetual() || ! $this->started_at) {
            return null;
        }

        return max(1, (int) $this->started_at->startOfDay()->diffInDays($this->expires_at->endOfDay()));
    }

    /** نسبة ما مضى من المدّة (0–100). */
    public function elapsedPercent(): ?int
    {
        $total = $this->totalDays();

        if ($total === null) {
            return null;
        }

        $remaining = max(0, (int) $this->daysRemaining());

        return (int) round((($total - $remaining) / $total) * 100);
    }

    /**
     * درجة الإلحاح — تحدّد لون البطاقة ونبرة الرسالة.
     *
     * `ok` مطمئنّ، `warn` يستدعي التجديد، `danger` يستدعيه الآن.
     */
    public function urgency(): string
    {
        if ($this->isPerpetual()) {
            return 'ok';
        }

        $days = $this->daysRemaining();

        return match (true) {
            $days < 0 => 'danger',
            $days <= 7 => 'danger',
            $days <= 30 => 'warn',
            default => 'ok',
        };
    }
}
