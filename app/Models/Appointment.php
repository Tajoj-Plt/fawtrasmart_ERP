<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    // تعريف ثوابت الحالة
    const STATUS_PENDING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_IGNORED = 3;
    const STATUS_RESCHEDULED = 4;

    // تحديد الحقول القابلة للتعبئة - تأكد من أن هذه الحقول موجودة في جدولك
    protected $fillable = [
        'client_id',
        'employee_id',
        'title',
        'description',
        'appointment_date', // تأكد من أن هذا هو اسم الحقل في جدولك
        'status',
        'created_by',
        'time',
        'duration',
        'notes',
        'action_type',
        'share_with_client',
        'is_recurring',
        'recurrence_type',
        'recurrence_date',
        'recurrence_parent_id',
    ];

    /**
     * أنواعُ التكرار كما يخزّنها `recurrence_type` — والمدّةُ بين موعدين.
     * `[وحدة, عدد]`: الشهريُّ بلا تجاوز (موعدُ ٣١ يناير يليه ٢٨ فبراير لا ٣ مارس).
     */
    public const RECURRENCE_STEPS = [
        1 => ['week', 1],
        2 => ['week', 2],
        3 => ['month', 1],
        4 => ['month', 2],
        5 => ['year', 1],
        6 => ['year', 2],
    ];

    public const RECURRENCE_LABELS = [
        1 => 'أسبوعي',
        2 => 'كل أسبوعين',
        3 => 'شهري',
        4 => 'كل شهرين',
        5 => 'سنوي',
        6 => 'كل سنتين',
    ];

    // تحديد الحقول التي يتم تحويلها إلى أنواع معينة
    protected $casts = [
        'appointment_date' => 'datetime', // تأكد من أن هذا هو اسم الحقل في جدولك
        'status' => 'integer',
        'share_with_client' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    /** موظفو الموعد — واحدٌ أو أكثر. `employee_id` يحمل أوّلهم. */
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'appointment_employees', 'appointment_id', 'employee_id')
            ->withTimestamps();
    }

    /** أصلُ سلسلة الموعد المتكرّر، للمواعيد التي ولّدتها المهمّة المجدولة */
    public function recurrenceParent()
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    /**
     * معرّفاتُ موظفي الموعد — من جدول الربط، وإلّا `employee_id`.
     *
     * الاحتياطُ لأنّ المواعيد التي يكتبها الحجز (`ReservationAppointmentSync`)
     * تضع `employee_id` وحده.
     *
     * @return array<int, int>
     */
    public function assignedEmployeeIds(): array
    {
        $ids = $this->relationLoaded('employees')
            ? $this->employees->pluck('id')
            : $this->employees()->pluck('employees.id');

        $ids = $ids->map(fn ($id) => (int) $id)->all();

        if ($ids === [] && $this->employee_id) {
            $ids = [(int) $this->employee_id];
        }

        return array_values(array_unique($ids));
    }

    /** أسماءُ موظفي الموعد مفصولةً بفاصلة — أو `null` */
    public function employeeNames(): ?string
    {
        $employees = $this->relationLoaded('employees') ? $this->employees : $this->employees()->get();

        if ($employees->isEmpty() && $this->employee_id) {
            $employees = collect([$this->employee])->filter();
        }

        $names = $employees->map(fn ($employee) => $employee->full_name)->filter();

        return $names->isEmpty() ? null : $names->implode('، ');
    }

    /** مواعيدُ موظفٍ بعينه — في جدول الربط أو في `employee_id` */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where(function ($q) use ($employeeId) {
            $q->where('appointments.employee_id', $employeeId)
                ->orWhereHas('employees', fn ($e) => $e->where('employees.id', $employeeId));
        });
    }

    /** التاريخُ التالي في سلسلة التكرار بعد `$from` */
    public static function nextRecurrenceDate(\Carbon\CarbonInterface $from, ?int $type): ?\Carbon\Carbon
    {
        [$unit, $count] = self::RECURRENCE_STEPS[(int) $type] ?? [null, null];

        if ($unit === null) {
            return null;
        }

        $date = \Carbon\Carbon::parse($from)->startOfDay();

        return match ($unit) {
            'week' => $date->addWeeks($count),
            'month' => $date->addMonthsNoOverflow($count),
            'year' => $date->addYearsNoOverflow($count),
        };
    }

    // العلاقة مع المستخدم الذي أنشأ الموعد
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // العلاقة مع العميل
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    // العلاقة مع الموظف
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // الحصول على النص العربي للحالة
    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case self::STATUS_PENDING:
                return 'تم جدولته';
            case self::STATUS_COMPLETED:
                return 'تم';
            case self::STATUS_IGNORED:
                return 'صرف النظر عنه';
            case self::STATUS_RESCHEDULED:
                return 'تم جدولته مجدداً';
            default:
                return 'غير معروف';
        }
    }

    // الحصول على لون الحالة
    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case self::STATUS_PENDING:
                return 'bg-warning text-dark';
            case self::STATUS_COMPLETED:
                return 'bg-success text-white';
            case self::STATUS_IGNORED:
                return 'bg-danger text-white';
            case self::STATUS_RESCHEDULED:
                return 'bg-info text-white';
            default:
                return 'bg-secondary text-white';
        }
    }

    // نطاق للمواعيد المنتظرة
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // نطاق للمواعيد المكتملة
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // نطاق لمواعيد اليوم
    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }
}