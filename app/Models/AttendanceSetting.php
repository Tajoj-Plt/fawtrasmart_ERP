<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $table = 'attendance_settings';

    /*
        `location_check_enabled` و`ip_check_enabled` عمودان في الجدول لا
        يقرؤهما أحد — أُسقطا من `$fillable` و`$casts` (2026-10-04) فلا
        يُسنَدان بالجملة ولا يبدوان مفتاحاً عاملاً.

        وفحصُ الموقع الفعليّ ليس مفتاحاً عامّاً أصلاً: هو على «المكان»
        (`attendance_geofences.require_geo` ونصفُ القطر والدقّة)، وعلى
        الجهاز (`attendance_kiosks.require_location`)، وفي محدِّدات الحضور
        (`AttendanceEntryGuard`). والعمودان يبقيان في الجدول — لا يُحذف
        عمودٌ لأنّه لم يُستعمل، فحذفُه يُسقط بيانات من رقّى من نسخةٍ أقدم.
    */
    protected $fillable = [
        // قديمة
        'start_month', 'start_day',
        'allow_second_shift', 'allow_backdated_requests',
        'direct_manager_approval', 'department_manager_approval', 'employees_approval',
        // V2 — مدة السماح والتأخير
        'late_grace_minutes', 'absent_after_minutes',
        // V2 — الخصميات التلقائية
        'auto_deduction_enabled', 'late_deduction_amount', 'absence_deduction_amount',
        'early_leave_deduction_amount',
        'deduction_calculation_type',
        // V2 — ساعات العمل والانصراف
        'work_hours_per_day', 'checkout_method',
        // V2 — الصور
        'photos_required', 'photos_count_checkin', 'photos_count_checkout',
        // V2 — التحقق
        'early_leave_threshold_minutes',
        // نافذةُ الانصراف العامّة — لمنشأةٍ بلا جدول ورديات
        'checkout_window_minutes',
        'checkin_early_minutes',
        // `null` = لا يُقفل تسجيل الحضور — ضابطٌ مستقلٌّ عن `absent_after_minutes`
        'checkin_close_after_minutes',
        // قيودُ «بدء زيارة العميل» للموظف الميداني — `FieldWorkGuard`
        'visit_requires_check_in', 'visit_blocked_after_check_out', 'visit_blocked_during_break',
        'absent_below_minutes', 'half_day_below_percent',
        'deduction_approval',
        'break_allowed_minutes',
        // الورديةُ الافتراضية لمن لا وردية له — مُدخَلةٌ لا ثابتة
        'default_shift_start', 'default_weekly_off_days',
    ];

    protected $casts = [
        'allow_second_shift' => 'boolean',
        'allow_backdated_requests' => 'boolean',
        'direct_manager_approval' => 'boolean',
        'department_manager_approval' => 'boolean',
        'employees_approval' => 'boolean',
        'auto_deduction_enabled' => 'boolean',
        'photos_required' => 'boolean',
        'visit_requires_check_in' => 'boolean',
        'visit_blocked_after_check_out' => 'boolean',
        'visit_blocked_during_break' => 'boolean',
        'late_deduction_amount' => 'decimal:2',
        'absence_deduction_amount' => 'decimal:2',
        'early_leave_deduction_amount' => 'decimal:2',
        'work_hours_per_day' => 'decimal:2',
        'default_weekly_off_days' => 'array',
    ];

    // ============== ثوابت ==============
    public const CHECKOUT_HOURS = 'hours';

    public const CHECKOUT_ROUTE = 'route';

    public const CHECKOUT_BOTH = 'both';

    public const DEDUCTION_FIXED = 'fixed';

    public const DEDUCTION_DAILY_SALARY = 'daily_salary';

    public const DEDUCTION_PER_MINUTE = 'per_minute';

    // ============== العلاقات ==============
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'attendance_settings_employees', 'attendance_settings_id', 'employee_id');
    }

    // ============== Helpers ==============
    /**
     * يحصل على الإعداد الأول (Singleton-like).
     * لو لا يوجد إعداد، يُنشئ واحداً افتراضياً.
     */
    public static function current(): self
    {
        $setting = self::first();
        if (! $setting) {
            $setting = self::create([
                'start_month' => 1,
                'start_day' => 1,
                'late_grace_minutes' => 15,
                'absent_after_minutes' => 120,
                'auto_deduction_enabled' => false,
                'late_deduction_amount' => 0,
                'absence_deduction_amount' => 0,
                'early_leave_deduction_amount' => 0,
                'deduction_calculation_type' => self::DEDUCTION_FIXED,
                'work_hours_per_day' => 8.00,
                'checkout_method' => self::CHECKOUT_HOURS,
                'photos_required' => false,
                'photos_count_checkin' => 0,
                'photos_count_checkout' => 0,
                'early_leave_threshold_minutes' => 15,
            ]);
        }

        return $setting;
    }
}
