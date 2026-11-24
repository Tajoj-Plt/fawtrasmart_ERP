<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * عضوية موظف في مسير رواتب — جدول الربط لا أكثر.
 *
 * ليست سجّل صرف: لا مبلغ ولا حالة دفع. التغطية تُقرأ منها عبر
 * `Payroll::coversEmployee()` عندما تكون القائمة غير فارغة.
 */
class PayrollEmployee extends Pivot
{
    protected $table = 'payroll_employee';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['payroll_id', 'employee_id'];
}
