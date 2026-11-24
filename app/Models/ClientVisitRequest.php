<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * طلبُ زيارةٍ ميدانية أرسله العميل من بوابته.
 *
 * انظر ترحيلَ `client_visit_requests`: الطلبُ رغبةٌ لا موعد — لا يظهر في
 * خطّ سير مندوبٍ ولا يحجز وقتَه حتى يعتمده موظّفٌ ويضيف العميلَ إلى الخطّ
 * من شاشة «خط سيري اليومي». نظيرُ `ClientPaymentNotice` تماماً.
 */
class ClientVisitRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** سحبه العميلُ نفسُه قبل مراجعته */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * الفتراتُ التي يختار منها العميل.
     *
     * ليست أوقاتَ دوامٍ ولا بورديّاتٍ في قاعدة البيانات عمداً: تلك تحكم
     * الحجزَ بالوقت (`ReservationAvailabilityService`)، وهذه تفضيلٌ فضفاضٌ
     * يقرؤه بشرٌ حين يرتّب خطَّ يومه. وساعةٌ محدَّدةٌ يختارها العميلُ تَعِد
     * بما لا يضمنه خطُّ سيرٍ ميداني.
     *
     * @var array<string, string>
     */
    public const TIME_WINDOWS = [
        'morning' => 'صباحاً',
        'afternoon' => 'بعد الظهر',
        'any' => 'أيّ وقت',
    ];

    protected $fillable = [
        'client_id', 'user_id', 'preferred_date', 'time_window', 'reason',
        'note', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'visit_id',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function timeWindowLabel(): string
    {
        return self::TIME_WINDOWS[$this->time_window] ?? $this->time_window;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'معتمد',
            self::STATUS_REJECTED => 'مرفوض',
            self::STATUS_CANCELLED => 'ملغى',
            default => 'بانتظار المراجعة',
        };
    }

    /** لونُ الشارة في شاشات الموظّف — بمفردات bootstrap كبقيّتها */
    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'warning',
        };
    }

    /**
     * لونُ الشارة في البوابة — مفرداتُها `cp-badge--*` لا `badge bg-*`.
     *
     * قالبُ البوابة نظامُ تصميمٍ مستقلّ، وصنفٌ غريبٌ عنه يصمت بلا خطأ —
     * فالترجمةُ هنا لا في القالب، كيلا تُنسخ في كل شاشةٍ تعرض الحالة.
     */
    public function portalTone(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'muted',
            default => 'warning',
        };
    }
}
