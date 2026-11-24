<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * فترة تبرير/إعفاء زيارات — موظفٌ وعميلٌ ونطاق تاريخٍ كامل، لا صفّ زيارةٍ
 * واحد. هذا هو مصدر الحقيقة لِـ«هل هذا التاريخ مغطًّى؟» بصرف النظر عن
 * صلاحية الموظف الحالية أو تفعيل/تعطيل نظام التبريرات لاحقاً — راجع
 * `App\Models\EmployeeClientVisit::needingJustificationQuery()` و
 * `App\Http\Middleware\CheckIncompleteVisits`، وهما المستهلكان الوحيدان
 * لـ{@see coversDate()}.
 *
 * لا تخلط هذا بـ`EmployeeClientVisit.justification` (نصّ تبريرٍ حرّ على
 * زيارة يومٍ واحد، يُقدَّمه الموظف نفسه عبر شاشة «تبريراتي» حين يُحجَب) —
 * الفترات هنا عملٌ إداري (مدير/مشرف يملك صلاحية `clients_visit_justifications_create`)
 * يمنح إعفاءً مسبقاً أو رجعياً لفترةٍ كاملة.
 */
class VisitJustification extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 0;

    public const STATUS_APPROVED = 1;

    public const STATUS_REJECTED = 2;

    protected $fillable = [
        'employee_id',
        'client_id',
        'date_from',
        'date_to',
        'reason',
        'notes',
        'status',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'approved_at' => 'datetime',
        'status' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /** فتراتٌ تتقاطع مع النطاق [from, to] لنفس الموظف والعميل — لفحص التكرار/التداخل عند الإضافة والتعديل. */
    public function scopeOverlapping(Builder $query, int $employeeId, int $clientId, string $from, string $to, ?int $excludeId = null): Builder
    {
        $query->where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->where('date_from', '<=', $to)
            ->where('date_to', '>=', $from);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * هل هذا التاريخ مغطًّى بفترة تبريرٍ **معتمدة** لهذا الموظف مع هذا
     * العميل؟ **المصدر الوحيد** لهذا السؤال — لا تكرّر هذا الاستعلام؛
     * استدعِها من أي موضعٍ يحتاج الإجابة.
     */
    public static function coversDate(int $employeeId, int $clientId, Carbon|string $date): bool
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return static::query()
            ->approved()
            ->where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->where('date_from', '<=', $date)
            ->where('date_to', '>=', $date)
            ->exists();
    }

    /**
     * فترةٌ معتمدةٌ موجودة تغطّي النطاق [from, to] بالكامل بالفعل — أو
     * `null`. تُستعمَل لمنع تكرار تبريرٍ لفترةٍ سبقت تغطيتها كاملةً
     * (تداخلٌ جزئي يبقى مسموحاً، راجع طلب المستخدم).
     */
    public static function fullyCoveringPeriod(int $employeeId, int $clientId, string $from, string $to, ?int $excludeId = null): ?self
    {
        $query = static::query()
            ->approved()
            ->where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->where('date_from', '<=', $from)
            ->where('date_to', '>=', $to);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }
}
