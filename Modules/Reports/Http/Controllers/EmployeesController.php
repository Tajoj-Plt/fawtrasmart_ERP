<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDays;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

class EmployeesController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        // نفسُ صلاحية تقارير الموظفين في HRReportsController (`hr_reports.employees`)
        $this->middleware('permission.role:hr_reports.employees');
    }

    public function index()
    {
        return view('reports::employees.index');
    }
    public function Residences()
    {
        return view('reports::employees.Residences');
    }

    /**
     * تقرير حضور تفصيلي لموظفٍ واحد — بحثٌ بالاسم/المعرّف/البريد.
     *
     * كان القالبُ مصفوفةً شهرية ثابتة (31 عموداً ليومٍ، بيانات "OFF"
     * مكتوبة يدوياً) بلا أي استعلامٍ خلفه. بُسِّط إلى جدولٍ يومي —
     * التاريخ والحالة وأوقات الحضور/الانصراف ودقائق التأخير/الإضافي —
     * من `attendance_days` مباشرة، بنفس منطق `HRReportsController
     * ::attendanceByDepartment` (TODO 11) لا عتباتٍ ثابتة.
     */
    public function attendance(Request $request)
    {
        $fromDate = $request->filled('date_from') ? Carbon::parse($request->date_from) : now()->startOfMonth();
        $toDate = $request->filled('date_to') ? Carbon::parse($request->date_to) : now();

        $employee = null;
        $attendanceData = collect();
        $totals = [
            'total_present' => 0,
            'total_absent' => 0,
            'total_late' => 0,
            'total_overtime_minutes' => 0,
        ];

        if ($request->filled('employee')) {
            $search = trim((string) $request->input('employee'));

            $employeeQuery = Employee::query()->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });

            // نطاقُ الموظفين: الصلاحيةُ تفتح الشاشة، والنطاقُ يحدّد مَن يظهر فيها.
            $this->scopeToEmployees($employeeQuery, 'id');

            $employee = $employeeQuery->first();

            if ($employee) {
                $attendanceData = AttendanceDays::where('employee_id', $employee->id)
                    ->whereBetween('attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')])
                    ->orderByDesc('attendance_date')
                    ->get();

                $totals['total_present'] = $attendanceData->where('status', 'present')->count();
                $totals['total_absent'] = $attendanceData->where('status', 'absent')->count();
                $totals['total_late'] = $attendanceData->where('status', 'late')->count();
                $totals['total_overtime_minutes'] = (int) $attendanceData->sum('overtime_minutes');
            }
        }

        return view('reports::employees.attendance', compact('employee', 'attendanceData', 'totals', 'fromDate', 'toDate'));
    }

    public function attendancemultiple()
    {
        return view('reports::employees.attendance_multiple');
    }
    public function Leaves()
    {
        return view('reports::employees.Leaves');
    }
    public function shift()
    {
        return view('reports::employees.shift');
    }
    public function AttendancebyEmployee()
    {
        return view('reports::employees.Attendance_by_Employee');
    }
    public function AttendancebyDay()
    {
        return view('reports::employees.Attendance-by-Day');
    }
    public function AttendancebyWeek()
    {
        return view('reports::employees.attendance-by-week');
    }
    public function AttendancebyMonth()
    {
        return view('reports::employees.attendance-by-month');
    }
    public function AttendancebyYear()
    {
        return view('reports::employees.attendance-by-year');

    }
    public function AttendancebyDepartment()
    {
        return view('reports::employees.attendance-by-department');
    }
    public function byBranch()
    {
        return view('reports::employees.Salaries_by_Branch');
    }
    public function Advances()
    {
        return view('reports::employees.Advances');
    }
    public function Contracts()
    {
        return view('reports::employees.Contracts');
    }

}
