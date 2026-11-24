<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTargetSettlement extends Model
{
    protected $fillable = [
        'sales_target_id',
        'employee_id',
        'commission_amount',
        'from_date',
        'to_date',
        'notes',
        'settled_by',
        'is_settled',
        'salary_slip_id',
        'settled_date',
        'settled_amount',
        // حقول الصرف المباشر للعمولات الأسبوعية
        'paid_directly',
        'direct_journal_entry_id',
        'direct_paid_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'settled_date' => 'date',
        'direct_paid_at' => 'datetime',
        'commission_amount' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'is_settled' => 'boolean',
        'paid_directly' => 'boolean',
    ];

    /**
     * العلاقة مع الهدف
     */
    public function target()
    {
        return $this->belongsTo(SalesTarget::class, 'sales_target_id');
    }

    /**
     * العلاقة مع الهدف (alias)
     */
    public function salesTarget()
    {
        return $this->belongsTo(SalesTarget::class, 'sales_target_id');
    }

    /**
     * الموظف صاحب العمولة.
     *
     * كان العمود `employee_id` يُكتب في كل تسوية ولا علاقةَ تقرؤه، فكلّ
     * شاشةٍ أرادت اسم الموظف اضطرّت إلى `User::find()` داخل حلقة — أو
     * عرضت المعرّف رقماً عارياً. شاشة تفاصيل الهدف تحتاجه بالاسم.
     *
     * المرجع `employees` لا `users`. الهدفُ يُسند إلى مستخدم
     * (`achievement->employee_id` هو `users.id`)، لكنّ الكاتبَين —
     * `TargetSettlementService` و`CalculateWeeklyCommissions` — يكتبان
     * `$user->employee_id`، والقيدُ الأجنبي في القاعدة على `employees.id`.
     * كانت العلاقةُ على `User` فتقرأ رقمَ موظفٍ معرّفاً لمستخدم: اسمُ شخصٍ
     * آخر في جدول التسويات.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * العلاقة مع المستخدم الذي قام بالتسوية
     */
    public function settler()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    /**
     * تنسيق الفترة
     */
    public function getFormattedPeriodAttribute()
    {
        return $this->from_date->format('Y-m-d') . ' إلى ' . $this->to_date->format('Y-m-d');
    }
}
