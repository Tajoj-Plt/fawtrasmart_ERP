<?php

namespace App\Http\Controllers\TrackTime;

use App\Http\Controllers\Controller;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/** معدّلُ ساعات الموظفين — منتقي الموظف من نطاق الرؤية. */
class AverageHoursTrackTimeController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function index()
    {
        return view('trackTime.sitting.average_hours.index');
    }

    public function create()
    {
        $employees = $this->scopedEmployeeOptions([]);

        return view('trackTime.sitting.average_hours.create', compact('employees'));
    }

    public function edit($id = null)
    {
        $employees = $this->scopedEmployeeOptions([]);

        return view('trackTime.sitting.average_hours.edit', compact('employees'));
    }
}
