<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إعدادات الحضور الفردية لكل موظف.
 * تعلو على AttendanceSetting (الإعدادات العامة).
 * أي حقل NULL = استخدم الإعداد العام.
 */
class EmployeeAttendanceConfig extends Model
{
    protected $table = 'employee_attendance_configs';

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
        'employee_id',
        'expected_check_in', 'expected_check_out',
        'late_grace_minutes', 'absent_after_minutes',
        'auto_deduction_enabled',
        'late_deduction_amount', 'absence_deduction_amount',
        'work_hours_per_day', 'checkout_method',
        'work_mode', 'break_allowed_minutes',
        'photos_required',
        'weekly_work_days', 'weekly_off_days',
        'uses_route_tracking', 'notes',
    ];

    protected $casts = [
        'auto_deduction_enabled' => 'boolean',
        'photos_required' => 'boolean',
        'uses_route_tracking' => 'boolean',
        'weekly_work_days' => 'array',
        'weekly_off_days' => 'array',
        'late_deduction_amount' => 'decimal:2',
        'absence_deduction_amount' => 'decimal:2',
        'work_hours_per_day' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * يحصل على قيمة الإعداد للموظف بالأولوية:
     *   1) قيمة EmployeeAttendanceConfig الخاصة
     *   2) قيمة AttendanceSetting العامة
     *   3) القيمة الافتراضية المُمرَّرة
     */
    public function getResolved(string $key, $default = null)
    {
        if (! is_null($this->{$key})) {
            return $this->{$key};
        }
        $global = AttendanceSetting::first();

        return $global?->{$key} ?? $default;
    }
}
