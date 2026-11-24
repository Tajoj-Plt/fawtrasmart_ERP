<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * طلبُ تصحيح حضورٍ — طلب → اعتماد → أثر، لا تعديلٌ مباشر على
 * `attendance_days`. يُطبَّق الأثر فقط عند الاعتماد (انظر
 * `AttendanceCorrectionApprovalController::approve`).
 *
 * نمطُها مأخوذٌ من `SalaryAdvanceRequest`: جدولٌ واحد يحمل الحالة
 * (`status`) بدل جدول اعتمادٍ منفصل، مع لقطةٍ من القيم الحالية وقت
 * التقديم (`current_*`) لعرض «قبل/بعد» على المعتمِد.
 */
class AttendanceCorrectionRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'requested_status',
        'requested_start_shift',
        'requested_end_shift',
        'requested_check_in_time',
        'requested_check_out_time',
        'requested_absence_type',
        'requested_absence_balance',
        'requested_notes',
        'current_status',
        'current_start_shift',
        'current_end_shift',
        'current_check_in_time',
        'current_check_out_time',
        'current_absence_type',
        'current_absence_balance',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'admin_notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * تسمياتُ حالة الطلب — مصدرٌ واحدٌ لثلاث شاشات.
     *
     * كانت كلُّ شاشةٍ تكتب سلسلتَها `@if ($x === 'pending') معلَّق @elseif …`،
     * فتفترق التسميةُ بأولِ تعديل.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'معلَّق',
            self::STATUS_APPROVED => 'معتمَد',
            self::STATUS_REJECTED => 'مرفوض',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    /**
     * الحالةُ المطلوبةُ ليوم الحضور — نفسُ مفاتيح `AttendanceDays::STATUS_*`.
     *
     * كانت تُطبَع خاماً في الجدول: «present» و«absent» و«late» في شاشةٍ
     * عربيّةٍ بأكملها.
     *
     * @return array<string, string>
     */
    public static function requestedStatusLabels(): array
    {
        return [
            AttendanceDays::STATUS_PRESENT => 'حاضر',
            AttendanceDays::STATUS_ABSENT => 'غائب',
            AttendanceDays::STATUS_LATE => 'متأخر',
        ];
    }

    public function requestedStatusLabel(): string
    {
        return self::requestedStatusLabels()[$this->requested_status] ?? (string) $this->requested_status;
    }

    /**
     * أنواعُ الغياب — `1`/`2` كما تكتبها شاشتا الطلب والإنشاء.
     *
     * كانت شاشةُ العرض تطبع الرقمَ نفسَه في عمودَي «الحالي/المطلوب»،
     * فيقرأ المعتمِدُ «2» ويُقرّر عليها.
     *
     * @return array<int, string>
     */
    public static function absenceTypeLabels(): array
    {
        return [
            1 => 'إجازة اعتيادية',
            2 => 'إجازة عرضية',
        ];
    }

    public static function absenceTypeLabel($type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return self::absenceTypeLabels()[(int) $type] ?? (string) $type;
    }

    public function currentStatusLabel(): ?string
    {
        if ($this->current_status === null) {
            return null;
        }

        return self::requestedStatusLabels()[$this->current_status] ?? (string) $this->current_status;
    }

    /** أينتظر اعتماداً؟ — الحارس الوحيد ضد الاعتماد المزدوج، فيُقفَل الصفّ (lockForUpdate) قبل قراءتها عند الاعتماد الفعلي. */
    public function canBeDecided(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}
