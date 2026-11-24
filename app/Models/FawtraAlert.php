<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تنبيه وارد من لوحة فوترة: قرب انتهاء، استنفاد ميزة، دفعة معلّقة…
 *
 * يُخزَّن محلياً لسببين: أن يظهر في الشاشات ولو انقطعت الشبكة، وأن يُعرف ما
 * أُقرّ به وما لم يُقرّ — والإقرار حالةٌ محلية وبعيدة معاً.
 */
class FawtraAlert extends Model
{
    protected $table = 'fawtra_alerts';

    protected $fillable = [
        'remote_id', 'fingerprint', 'source', 'type', 'severity', 'title', 'body', 'payload',
        'remote_created_at', 'acknowledged_at', 'dismissed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'remote_created_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    /**
     * أنواع التنبيهات ووصفها العربي — من الوثيقة.
     *
     * التفرّع على `type` لا على `title`: النصّ قد يتحسّن عند فوترة والرمز ثابت.
     */
    public const TYPES = [
        'subscription.expiring' => 'قرب انتهاء الاشتراك',
        'subscription.expired' => 'انتهى الاشتراك',
        'subscription.suspended' => 'أُوقف الاشتراك',
        'subscription.payment_due' => 'دفعة بانتظار السداد',
        'usage.threshold' => 'بلوغ ٨٠٪ من حدّ ميزة',
        'usage.exhausted' => 'استُنفد حدّ ميزة',
    ];

    public function scopeOpen($query)
    {
        return $query->whereNull('dismissed_at');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    /** التنبيهات التي رصدها النظام محلياً — لا تُقرّ عند فوترة. */
    public function scopeLocal($query)
    {
        return $query->where('source', 'local');
    }

    public function scopeFromDashboard($query)
    {
        return $query->where('source', 'remote');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** لون الشارة في الواجهة — مشتقّ من الخطورة لا من النوع. */
    public function severityClass(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };
    }
}
