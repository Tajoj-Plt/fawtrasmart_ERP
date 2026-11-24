<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDays extends Model
{
    use HasFactory;

    // اسم الجدول
    protected $table = 'attendance_days';

    // الأعمدة المسموح بملئها
    protected $fillable = [
        'employee_id',
        'attendance_sheets_id',
        'attendance_date',
        'status',
        'start_shift',
        'end_shift',
        'check_in_time',
        'check_out_time',
        'login_time',
        'logout_time',
        'absence_type',
        'absence_balance',
        'check_in_method',
        'check_out_method',
        'scanned_via_barcode',
        'ip_address',
        'user_agent',
        'notes',
        // V2 fields
        'deduction_id', 'late_minutes', 'early_leave_minutes', 'overtime_minutes',
        'expected_check_in', 'expected_check_out',
        'is_holiday', 'is_weekly_off', 'is_on_leave', 'leave_request_id',
        'processed_at', 'auto_generated',
        'manual_override',
        'checkin_latitude', 'checkin_longitude',
        'checkout_latitude', 'checkout_longitude',
        // الوسيلةُ والمكان كما سجّلهما النظام لحظةَ الختم
        'check_in_channel', 'check_out_channel', 'check_out_capped',
        'check_in_kiosk_id', 'check_out_kiosk_id',
        'check_in_geofence_id', 'check_out_geofence_id',
        'check_in_distance_meters', 'check_out_distance_meters',
        'check_in_punch_id', 'check_out_punch_id',
        // الاستراحة: المفتوحةُ الآن ومجموعُ المغلقة
        'break_started_at', 'break_minutes',
    ];

    protected $casts = [
        'attendance_date'    => 'date',
        'is_holiday'         => 'boolean',
        'is_weekly_off'      => 'boolean',
        'is_on_leave'        => 'boolean',
        'auto_generated'     => 'boolean',
        'manual_override'    => 'boolean',
        'processed_at'       => 'datetime',
        'break_started_at'   => 'datetime',
        'break_minutes'      => 'integer',
        'late_minutes'       => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes'   => 'integer',
        'checkin_latitude'   => 'decimal:7',
        'checkin_longitude'  => 'decimal:7',
        'checkout_latitude'  => 'decimal:7',
        'checkout_longitude' => 'decimal:7',
        'check_out_capped'   => 'boolean',
        'check_in_distance_meters'  => 'integer',
        'check_out_distance_meters' => 'integer',
    ];

    // ============= الثوابت =============
    public const STATUS_PRESENT  = 'present';
    public const STATUS_ABSENT   = 'absent';
    public const STATUS_LATE     = 'late';
    public const STATUS_HALF_DAY = 'half_day';
    public const STATUS_LEAVE    = 'leave';
    public const STATUS_HOLIDAY  = 'holiday';

    // ============= العلاقات =============
    public function employee()
    {
        // withTrashed: سجلُّ الحضور تاريخيٌّ، فحذفُ الموظف لاحقاً (حذفاً
        // ناعماً) لا ينبغي أن يفقد اليومَ اسمَ صاحبه.
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function deduction()
    {
        return $this->belongsTo(Deduction::class, 'deduction_id');
    }

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function photos()
    {
        return $this->hasMany(AttendancePhoto::class, 'attendance_day_id');
    }

    // نطاقُ المرفق الذي وقع فيه كلُّ ختم — يُقرأ في سجل اليوم الموحَّد
    public function checkInGeofence()
    {
        return $this->belongsTo(AttendanceGeofence::class, 'check_in_geofence_id');
    }

    public function checkOutGeofence()
    {
        return $this->belongsTo(AttendanceGeofence::class, 'check_out_geofence_id');
    }

    public function punches()
    {
        return $this->hasMany(AttendancePunch::class, 'attendance_day_id');
    }

    public function checkinPhotos()
    {
        return $this->hasMany(AttendancePhoto::class, 'attendance_day_id')->where('phase', 'checkin');
    }

    public function checkoutPhotos()
    {
        return $this->hasMany(AttendancePhoto::class, 'attendance_day_id')->where('phase', 'checkout');
    }
     public function scopeToday($query)
    {
        return $query->whereDate('attendance_date', Carbon::today());
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    // Accessors

    /**
     * دقائق العمل الفعلية بين الحضور والانصراف.
     *
     * كانت تُحسب بـ`Carbon::parse($time)` بلا تاريخ — أي على تاريخ اللحظة
     * الحالية ضمناً، لا `attendance_date` — وبلا قيمةٍ مطلقة، فتُرجع Carbon 3
     * فرقاً بإشارة لا فرقاً مطلقاً كما في Carbon 2. فكل وردية عادية (كـ
     * 08:00 → 16:00) كانت تُرجع دقائق **سالبة**، ووردية تعبر منتصف الليل
     * (كـ 22:00 → 06:00) كانت تُرجع مدةً مقلوبة (16 ساعة بدل 8) لأن الانصراف
     * يُقرأ في نفس يوم الحضور بدل اليوم التالي.
     */
    public function getWorkingHoursAttribute()
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            return 0;
        }

        $date = ($this->attendance_date?->toDateString()) ?? now()->toDateString();
        $checkIn = Carbon::parse($date . ' ' . $this->check_in_time);
        $checkOut = Carbon::parse($date . ' ' . $this->check_out_time);

        /*
            الانصرافُ قبل الحضور يُنقل إلى الغد في الوردية الليلية وحدها
            (نهايتُها المتوقَّعة قبل بدايتها على الساعة). في غيرها هو ختمٌ
            قُصّ أو أُدخل خطأً — كان يُحسب له قرابة يوم كامل (1188 دقيقة
            لمن حضر 20:11 وقُصّ انصرافُه إلى 16:00)، فلا ساعاتِ عمل له.
        */
        if ($checkOut->lt($checkIn)) {
            $overnight = $this->expected_check_in && $this->expected_check_out
                && $this->expected_check_out < $this->expected_check_in;

            if (! $overnight) {
                return 0;
            }

            $checkOut->addDay();
        }

        return (int) abs($checkOut->diffInMinutes($checkIn));
    }

    public function getIsCheckedInAttribute()
    {
        return !is_null($this->check_in_time) && is_null($this->check_out_time);
    }

    public function getIsCompleteAttribute()
    {
        return !is_null($this->check_in_time) && !is_null($this->check_out_time);
    }

    // Helper Methods
    public static function getTodayAttendance($employeeId)
    {
        return self::where('employee_id', $employeeId)
                   ->whereDate('attendance_date', Carbon::today())
                   ->first();
    }

    public static function checkIfAlreadyCheckedIn($employeeId)
    {
        $attendance = self::getTodayAttendance($employeeId);
        return $attendance && $attendance->check_in_time && !$attendance->check_out_time;
    }

    public static function checkIfAlreadyCheckedOut($employeeId)
    {
        $attendance = self::getTodayAttendance($employeeId);
        return $attendance && $attendance->check_in_time && $attendance->check_out_time;
    }
}
