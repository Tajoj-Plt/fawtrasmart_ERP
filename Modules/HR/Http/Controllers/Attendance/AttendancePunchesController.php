<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * سجلُّ الأختام كما تراه الموارد البشرية — قراءةٌ فقط، بلا زرِّ تعديلٍ
 * واحد. هذا هو بيت القصيد: ما يُقرأ هنا هو ما وقع، لا ما استقرّ عليه
 * الحسابُ بعد تصحيح.
 *
 * والمردودُ يُعرض مع المقبول. تسعُ محاولاتٍ مردودةٍ لموظفٍ من على بُعد
 * ٦٠٠ متر ليست خطأً في النظام — هي إمّا نطاقٌ مضبوطٌ على العنوان الخطأ،
 * أو موظفٌ يُسجّل من مكانٍ آخر. وكلاهما لا يُرى إلا هنا.
 */
class AttendancePunchesController extends Controller
{
    use ScopesToAccessibleEmployees;

    /*
        سجلات الحضور = عرض الأختام فقط.
        لا تُفتح بكشك ولا بتعديل أيام — ذلك كان يخلط الشاشات.
    */
    public function __construct()
    {
        $this->middleware('permission.role:attendance_records.index');
    }

    public function index(Request $request)
    {
        $query = AttendancePunch::with(['employee:id,first_name,middle_name,nickname', 'kiosk:id,name', 'geofence:id,name'])
            ->orderByDesc('punched_at');

        $this->scopeToEmployees($query);

        if ($request->filled('date_from')) {
            $query->whereDate('attendance_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('attendance_date', '<=', $request->date_to);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        foreach (['result', 'channel', 'punch_type', 'stage', 'adjustment_code'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('kiosk_id')) {
            $query->where('kiosk_id', $request->kiosk_id);
        }

        return view('hr::attendance.punches.index', [
            'punches' => $query->paginate(50)->withQueryString(),
            'kiosks' => AttendanceKiosk::orderBy('name')->get(['id', 'name']),
            // قائمةُ التصفية من النطاق نفسِه: لا تُعرض أسماءُ من لا تُعرض أختامُهم.
            'employees' => $this->scopedEmployeeOptions([]),
            'filters' => $request->only([
                'date_from', 'date_to', 'employee_id', 'result',
                'channel', 'punch_type', 'stage', 'kiosk_id', 'adjustment_code',
            ]),
        ]);
    }
}
