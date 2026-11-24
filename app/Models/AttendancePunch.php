<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * ختمٌ خام — يُكتب مرّةً ولا يُعدَّل.
 *
 * القيدُ مفروضٌ في الموديل لا في الاتفاق: `updating` و`deleting` يرميان.
 * سجلٌّ تدقيقيٌّ يجوز تعديلُه ليس سجلاً تدقيقياً، ودورةُ التصحيح
 * (`AttendanceCorrectionRequest`) هي المسار الوحيد لتغيير الأثر — تُبقي
 * الختمَ الأصلي كما وقع وتُضيف فوقه قراراً معتمَداً.
 */
class AttendancePunch extends Model
{
    use HasFactory;

    protected $table = 'attendance_punches';

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    // بدءُ الاستراحة ونهايتُها — تُسجَّل مع الحضور والانصراف في السجل نفسه
    public const TYPE_BREAK_START = 'rest_in';

    public const TYPE_BREAK_END = 'rest_out';

    public const CHANNEL_KIOSK = 'kiosk';

    public const CHANNEL_FIELD = 'field';

    public const CHANNEL_MANUAL = 'manual';

    public const METHOD_BARCODE = 'barcode';

    public const METHOD_QR = 'qr';

    public const METHOD_GEO = 'geo';

    public const METHOD_MANUAL = 'manual';

    /** الموظفُ مسح «رمز المكتب» بجواله (`OfficeCode`) */
    public const METHOD_OFFICE = 'office_qr';

    public const RESULT_ACCEPTED = 'accepted';

    public const RESULT_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id', 'attendance_day_id',
        'punch_type', 'punched_at', 'applied_time', 'adjustment_code', 'entry_note', 'attendance_date',
        'channel', 'method', 'kiosk_id', 'geofence_id',
        'latitude', 'longitude', 'accuracy_meters', 'distance_meters',
        'ip_address', 'wifi_ssid', 'user_agent', 'raw_barcode',
        'expected_check_in', 'expected_check_out',
        'late_minutes', 'early_leave_minutes',
        'result', 'stage', 'rejection_code', 'rejection_reason',
        'created_by',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'attendance_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy_meters' => 'integer',
        'distance_meters' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('سجلُّ الأختام غير قابل للتعديل — استخدم طلب تصحيح حضور.');
        });

        static::deleting(function () {
            throw new RuntimeException('سجلُّ الأختام غير قابل للحذف.');
        });
    }

    public function employee(): BelongsTo
    {
        // withTrashed: الختمُ واقعةٌ تاريخية، وحذفُ الموظف لاحقاً لا يُفقدها صاحبَها.
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDays::class, 'attendance_day_id');
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(AttendanceKiosk::class, 'kiosk_id');
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(AttendanceGeofence::class, 'geofence_id');
    }

    public function scopeAccepted($query)
    {
        return $query->where('result', self::RESULT_ACCEPTED);
    }

    public function scopeRejected($query)
    {
        return $query->where('result', self::RESULT_REJECTED);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('attendance_date', $date);
    }

    /**
     * عنوانٌ قصير لسبب الردّ — للجداول. النصُّ الكامل (`rejection_reason`)
     * طويلٌ ويحمل أرقاماً (الساعة، المسافة)، فيُعرض تلميحاً لا سطوراً.
     */
    public const REJECTION_TITLES = [
        'employee_inactive' => 'الموظف متوقّف عن العمل',
        'barcode_disabled' => 'البطاقة ملغاة',
        'barcode_mismatch' => 'بطاقة لا تطابق صاحبها',
        'barcode_not_supplied' => 'لم يُمسح باركود',
        'unknown_barcode' => 'بطاقة غير معروفة',
        'before_hire_date' => 'لم يبدأ التعيين بعد',
        'after_last_working_day' => 'انتهت الخدمة',
        'channel_not_allowed' => 'طريقة تسجيل غير مسموحة',
        'outside_geofence' => 'خارج مكان التسجيل',
        'location_required' => 'الموقع مطلوب',
        'location_inaccurate' => 'دقّة الموقع غير كافية',
        'network_not_allowed' => 'شبكة غير مسموحة',
        'determinant_rejected' => 'رُفض حسب محدِّد الحضور',
        'already_checked_in' => 'الحضور مسجَّل مسبقاً',
        'already_checked_out' => 'الانصراف مسجَّل مسبقاً',
        'no_check_in' => 'انصراف بلا حضور',
        'too_soon_after_check_in' => 'انصراف بعد الحضور مباشرة',
        'photos_missing' => 'الصور المطلوبة ناقصة',
        'kiosk_required' => 'الجهاز غير مربوط',
        'kiosk_not_allowed' => 'جهاز غير مسموح لهذا الموظف',
        'on_approved_leave' => 'الموظف في إجازة',
        'weekly_off' => 'عطلة أسبوعية',
        'public_holiday' => 'عطلة رسمية',
        'outside_login_window' => 'خارج وقت تسجيل الحضور',
        'outside_logout_window' => 'قبل وقت الانصراف',
        'route_missing' => 'لا خط سير لهذا اليوم',
        'route_incomplete' => 'خط السير غير مكتمل',
        'visit_open' => 'زيارة مفتوحة',
        'day_complete' => 'اليوم مكتمل',
        'day_corrected' => 'اليوم مصحَّح باعتماد',
        'break_open' => 'في استراحة',
        'no_break' => 'لا استراحة مفتوحة',
    ];

    public function rejectionTitle(): string
    {
        return self::REJECTION_TITLES[$this->rejection_code]
            ?? \Illuminate\Support\Str::limit((string) ($this->rejection_reason ?: 'مردود'), 40);
    }

    public function channelLabel(): string
    {
        return [
            self::CHANNEL_KIOSK => 'جهاز الفرع',
            self::CHANNEL_FIELD => 'الجوال الميداني',
            self::CHANNEL_MANUAL => 'إدخال يدوي',
        ][$this->channel] ?? $this->channel;
    }

    public function methodLabel(): string
    {
        return [
            self::METHOD_BARCODE => 'باركود البطاقة',
            self::METHOD_QR => 'رمز QR',
            self::METHOD_OFFICE => 'رمز المكتب بالجوال',
            self::METHOD_GEO => 'الموقع الجغرافي',
            self::METHOD_MANUAL => 'يدوي',
        ][$this->method] ?? $this->method;
    }

    /**
     * نصُّ الموقع للعرض في سجلات الحضور: النطاق + الإحداثيات + المسافة.
     */
    public function locationDisplay(): string
    {
        $parts = [];

        if ($this->geofence?->name) {
            $parts[] = $this->geofence->name;
        }

        if ($this->latitude !== null && $this->longitude !== null) {
            $parts[] = number_format((float) $this->latitude, 6).', '.number_format((float) $this->longitude, 6);
        }

        if ($this->distance_meters !== null) {
            $parts[] = number_format($this->distance_meters).' م';
        }

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        return $this->ip_address ? 'شبكة '.$this->ip_address : '—';
    }

    public function mapsUrl(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return 'https://www.google.com/maps?q='.(float) $this->latitude.','.(float) $this->longitude;
    }
}
