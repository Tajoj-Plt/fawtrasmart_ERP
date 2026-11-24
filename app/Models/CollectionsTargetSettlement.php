<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionsTargetSettlement extends Model
{
    protected $table = 'collections_target_settlements';

    protected $fillable = [
        'collections_target_id',
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

    public function target()
    {
        return $this->belongsTo(CollectionsTarget::class, 'collections_target_id');
    }

    public function collectionsTarget()
    {
        return $this->belongsTo(CollectionsTarget::class, 'collections_target_id');
    }

    /**
     * الموظف صاحب العمولة.
     *
     * كان العمود `employee_id` يُكتب في كل تسوية ولا علاقةَ تقرؤه، فكلّ
     * شاشةٍ أرادت اسم الموظف اضطرّت إلى `User::find()` داخل حلقة — أو
     * عرضت المعرّف رقماً عارياً. شاشة تفاصيل الهدف تحتاجه بالاسم.
     *
     * المرجع `employees` لا `users`. الهدفُ يُسند إلى مستخدم
     * (`achievement->employee_id` هو `users.id`)، لكنّ `TargetSettlementService`
     * يكتب `$user->employee_id` — رقمَ الموظف. كانت العلاقةُ على `User`
     * فتقرأ رقمَ موظفٍ معرّفاً لمستخدم: اسمُ شخصٍ آخر في جدول التسويات.
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

    public function getFormattedPeriodAttribute()
    {
        return $this->from_date->format('Y-m-d') . ' إلى ' . $this->to_date->format('Y-m-d');
    }
}
