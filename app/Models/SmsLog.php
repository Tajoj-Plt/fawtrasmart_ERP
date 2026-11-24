<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient',
        'message',
        'template_code',
        'segments',
        'cost',
        'status',
        'scheduled_at',
        'attempts',
        'gateway_response',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'segments' => 'integer',
        'attempts' => 'integer',
        'cost' => 'decimal:4',
    ];

    /**
     * حالات السجل ودلالتها.
     *
     * كانت الحالة نصّاً حرّاً بقيمتين (`sent` و`failed`)، ثم صارت إعدادات
     * الإرسال تنتج حالاتٍ أخرى — رسالة أُجّلت لساعات الهدوء ليست رسالة
     * فاشلة، ولا يصحّ أن تظهر في السجل كأنها كذلك.
     */
    public const STATUSES = [
        'sent' => 'أُرسلت',
        'failed' => 'فشلت',
        'deferred' => 'مؤجَّلة',
        'retry' => 'بانتظار إعادة المحاولة',
        'processing' => 'قيد المعالجة',
        'test' => 'وضع الاختبار',
        'blocked' => 'ممنوعة',
        'suppressed' => 'مكرّرة',
        'capped' => 'تجاوزت السقف',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    public function template()
    {
        return $this->belongsTo(SmsTemplate::class, 'template_code', 'code');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
