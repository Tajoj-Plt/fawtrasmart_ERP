<?php

namespace App\Http\Controllers\TrackTime;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * شاشاتُ ساعات العمل. الصلاحيةُ على المسار (`time_entries.*` في
 * `routes/track_time.php`)، و«على أيّ موظفين» نطاقُ الرؤية: منتقي الموظف
 * لا يعرض إلا من يراهم المستخدم — ومن نطاقُه `data_scope_own` يرى نفسَه وحده.
 */
class TrackTimeController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function index()
    {
        $employees = $this->scopedEmployeeOptions([]);

        return view('trackTime.index', compact('employees'));
    }

    public function show()
    {
        $employees = $this->scopedEmployeeOptions([]);

        return view('trackTime.view', compact('employees'));
    }

    public function create_invoice_time()
    {
        $employees = $this->scopedEmployeeOptions([]);

        return view('trackTime.create_invoice_time', compact('employees'));
    }
}
