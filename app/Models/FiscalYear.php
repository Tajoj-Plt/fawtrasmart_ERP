<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * سنة مالية — وعاء الفترات المحاسبية.
 *
 * @property int $id
 * @property string $name
 * @property string $status
 * @property bool $is_current
 */
class FiscalYear extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('sequence');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** السنة تحتوي التاريخ إن وقع بين طرفيها شاملَين. */
    public function contains(Carbon $date): bool
    {
        return $date->betweenIncluded($this->start_date, $this->end_date);
    }

    /** عدد أيام السنة — يكشف السنوات القصيرة (سنة تأسيس) أو المتداخلة. */
    public function lengthInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * السنة الجارية — المعلَّمة يدوياً، وإلا التي يقع فيها اليوم.
     *
     * الرجوع إلى تاريخ اليوم مقصود: منشأة لم تعلّم سنتها الجارية يجب ألا
     * تفقد الشاشة، والتعليم اليدوي يبقى قادراً على تجاوز التقويم حين تُدار
     * سنة سابقة أثناء إقفالها.
     */
    public static function current(): ?self
    {
        return static::where('is_current', true)->first()
            ?: static::whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today())
                ->first();
    }

    /**
     * السنة التي يقع فيها تاريخ ما — أو `null` إن لم تُعرَّف سنة تغطّيه.
     */
    public static function forDate(Carbon $date): ?self
    {
        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }
}
