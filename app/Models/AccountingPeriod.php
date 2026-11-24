<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * فترة محاسبية — أصغر وحدة زمنية يُسمح أو يُمنع القيد فيها.
 *
 * @property int $id
 * @property string $name
 * @property string $status
 */
class AccountingPeriod extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_LOCKED = 'locked';

    /** أنواع التوليد ونصيب كل نوع من أشهر السنة. */
    public const TYPES = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannual' => 6,
        'annual' => 12,
    ];

    protected $fillable = [
        'fiscal_year_id',
        'name',
        'sequence',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /** الفترة المقفلة نهائياً لا تُعاد فتحها — هذا معنى القفل النهائي. */
    public function canReopen(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function contains(Carbon $date): bool
    {
        return $date->betweenIncluded($this->start_date, $this->end_date);
    }

    /** الفترة التي يقع فيها تاريخ ما. */
    public static function forDate(Carbon $date): ?self
    {
        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * عدد القيود اليومية الواقعة داخل الفترة.
     *
     * استعلام مباشر لا علاقة: جدول القيود يخصّ وحدة أخرى، وربطه بعلاقة
     * Eloquent هنا يجعل شاشة الإعدادات تنكسر لو غاب الجدول في تركيب ما.
     */
    public function journalEntryCount(): int
    {
        try {
            return DB::table('journal_entries')
                ->whereDate('date', '>=', $this->start_date)
                ->whereDate('date', '<=', $this->end_date)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** نصّ الحالة الملوَّن — يوحّد الشارة بين الشاشات. */
    public function badgeTone(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'on',
            self::STATUS_CLOSED => 'warn',
            default => 'dang',
        };
    }
}
