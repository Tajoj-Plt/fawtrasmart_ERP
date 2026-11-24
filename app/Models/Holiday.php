<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = [
        'holiday_list_id', 'holiday_date', 'named',
        'recurrence_type', 'recurrence_day_of_week',
        'recurrence_day_of_month', 'recurrence_month',
        'is_active', 'is_paid',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active'    => 'boolean',
        'is_paid'      => 'boolean',
    ];

    // ============ ثوابت ============
    public const RECURRENCE_ONCE    = 'once';
    public const RECURRENCE_WEEKLY  = 'weekly';
    public const RECURRENCE_MONTHLY = 'monthly';
    public const RECURRENCE_YEARLY  = 'yearly';

    public static function recurrenceTypes(): array
    {
        return [
            self::RECURRENCE_ONCE    => 'مرة واحدة (تاريخ محدد)',
            self::RECURRENCE_WEEKLY  => 'أسبوعي (يوم من كل أسبوع)',
            self::RECURRENCE_MONTHLY => 'شهري (يوم من كل شهر)',
            self::RECURRENCE_YEARLY  => 'سنوي (تاريخ من كل سنة)',
        ];
    }

    public static function daysOfWeek(): array
    {
        return [
            0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء',
            3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
        ];
    }

    public static function months(): array
    {
        return [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
    }

    // ============ العلاقات ============
    public function holidayList()
    {
        return $this->belongsTo(HolidayList::class);
    }

    // ============ Scopes ============
    public function scopeActive($q) { return $q->where('is_active', true); }

    // ============ Logic ============
    /**
     * هل التاريخ المُمرَّر يقع ضمن هذه العطلة؟
     */
    public function appliesToDate(Carbon $date): bool
    {
        if (!$this->is_active) return false;

        switch ($this->recurrence_type) {
            case self::RECURRENCE_ONCE:
                return $this->holiday_date
                    && $this->holiday_date->isSameDay($date);

            case self::RECURRENCE_WEEKLY:
                return $this->recurrence_day_of_week !== null
                    && (int) $date->dayOfWeek === (int) $this->recurrence_day_of_week;

            case self::RECURRENCE_MONTHLY:
                return $this->recurrence_day_of_month !== null
                    && (int) $date->day === (int) $this->recurrence_day_of_month;

            case self::RECURRENCE_YEARLY:
                return $this->recurrence_month !== null
                    && $this->recurrence_day_of_month !== null
                    && (int) $date->month === (int) $this->recurrence_month
                    && (int) $date->day === (int) $this->recurrence_day_of_month;
        }

        return false;
    }

    /**
     * هل التاريخ يصادف أي عطلة نشطة؟
     *
     * بلا موظف: أيُّ عطلة في التاريخ (سلوك الشاشات العامّة).
     * مع موظف: تُحترم قوائم الإسناد في «موظفو العطلة» — قائمة بلا إسناد
     * تسري على الجميع، وقائمة بإسناد تسري على المُسنَدين فقط.
     */
    public static function isHoliday(Carbon $date, ?Employee $employee = null): bool
    {
        return self::findForDate($date, $employee) !== null;
    }

    /**
     * الحصول على العطلة المنطبقة (للحصول على اسمها).
     */
    public static function findForDate(Carbon $date, ?Employee $employee = null): ?self
    {
        $candidates = self::active()->get()->filter(fn (self $h) => $h->appliesToDate($date));

        foreach ($candidates as $holiday) {
            if ($employee === null || $holiday->appliesToEmployee($employee)) {
                return $holiday;
            }
        }

        return null;
    }

    /**
     * أتسري هذه العطلة على هذا الموظف؟
     *
     * بلا قائمة، أو قائمة بلا صفوف إسناد → الجميع.
     * بوجود إسناد (موظفين أو قواعد فرع/قسم/مسمى) → المُطابِق فقط.
     */
    public function appliesToEmployee(Employee $employee): bool
    {
        if (! $this->holiday_list_id) {
            return true;
        }

        $customizations = HolyDayListCustomize::query()
            ->where('holiday_list_id', $this->holiday_list_id)
            ->with('employees:id')
            ->get();

        if ($customizations->isEmpty()) {
            return true;
        }

        foreach ($customizations as $customization) {
            if ((int) $customization->use_rules === 2) {
                if ($customization->employees->contains('id', $employee->id)) {
                    return true;
                }

                continue;
            }

            if ($this->matchesHolidayRule($employee, $customization)) {
                return true;
            }
        }

        return false;
    }

    private function matchesHolidayRule(Employee $employee, HolyDayListCustomize $customization): bool
    {
        $matched = false;

        foreach ([
            'branch_id' => $employee->branch_id,
            'department_id' => $employee->department_id,
            'job_title_id' => $employee->job_title_id ?? null,
        ] as $column => $employeeValue) {
            if (! $customization->{$column}) {
                continue;
            }

            if ((int) $customization->{$column} !== (int) $employeeValue) {
                return false;
            }

            $matched = true;
        }

        return $matched;
    }

    public function getRecurrenceLabelAttribute(): string
    {
        switch ($this->recurrence_type) {
            case self::RECURRENCE_ONCE:
                return $this->holiday_date ? $this->holiday_date->format('Y-m-d') : '—';
            case self::RECURRENCE_WEEKLY:
                return 'كل ' . (self::daysOfWeek()[$this->recurrence_day_of_week] ?? '?');
            case self::RECURRENCE_MONTHLY:
                return 'يوم ' . $this->recurrence_day_of_month . ' من كل شهر';
            case self::RECURRENCE_YEARLY:
                return (self::months()[$this->recurrence_month] ?? '?') . ' ' . $this->recurrence_day_of_month;
            default:
                return '—';
        }
    }
}
