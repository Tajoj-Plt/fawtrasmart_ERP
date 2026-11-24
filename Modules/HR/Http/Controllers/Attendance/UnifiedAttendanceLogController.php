<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Attendance\DailyAttendanceBoard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\BranchTree;

/**
 * سجلُّ اليوم الموحَّد: كلُّ حركات اليوم لكل الموظفين — المقرُّ والميدان
 * في جدولٍ واحد.
 *
 * ولِمَ شاشةٌ مستقلّة و`attendanceDays.index` قائمة؟ لأنهما شيئان
 * مختلفان. تلك قائمةُ إدارةٍ (تعديل/حذف) تُحمّل **كلَّ** صفوف الجدول
 * بـ`->get()` بلا ترقيمٍ ولا مرشِّح تاريخ — تكبر بعدد الموظفين في
 * الأيام، وتُصبح غيرَ قابلةٍ للفتح بعد أشهر. وهذه قراءةٌ تشغيليةٌ
 * ليومٍ بعينه، وفيها ما ليس هناك: الوردية، والوسيلة، والموقع، ودقائق
 * التأخير والانصراف المبكر.
 *
 * والفارقُ الجوهري: هذه تعرض **مَن لم يُسجِّل** أيضاً. جدولٌ يعرض
 * الحاضرين وحدهم لا يُجيب السؤالَ الذي يُفتح لأجله كلَّ صباح — «من
 * ينقصنا؟» — ويتركه للعدّ اليدوي.
 */
class UnifiedAttendanceLogController extends Controller
{
    use ScopesToAccessibleEmployees;

    /*
        قراءةٌ لأيام الحضور — صلاحيةُ عرضها وحدها، والنطاقُ يحدّد مَن فيها.
    */
    public function __construct()
    {
        $this->middleware('permission.role:attendance_days.index');
    }

    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        $board = app(DailyAttendanceBoard::class);
        $rows = $board->build($this->employeesInScope($request), $date);

        if ($request->filled('state')) {
            $rows = $rows->where('state', $request->state)->values();
        }

        return view('hr::attendance.unified_log.index', [
            'date' => $date,
            'rows' => $rows,
            'summary' => $board->summarise($rows),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['date', 'department_id', 'branch_id', 'channel', 'state', 'q']),
        ]);
    }

    // ════════════════════════════════════════════════════

    private function employeesInScope(Request $request): Collection
    {
        $query = Employee::query()
            ->with(['department:id,name', 'branch:id,name', 'attendanceConfig'])
            ->orderBy('first_name');

        $this->scopeToEmployees($query, 'id');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        BranchTree::apply($query, $request->input('branch_id'));

        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $query->where(fn ($q) => $q
                ->where('first_name', 'like', $term)
                ->orWhere('middle_name', 'like', $term)
                ->orWhere('nickname', 'like', $term));
        }

        return $query->get();
    }
}
