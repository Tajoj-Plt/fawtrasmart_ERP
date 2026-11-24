<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequestRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Log as ModelsLog;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Controllers\Concerns\SubmitsAttendanceCorrectionRequests;

/**
 * الوجهُ الطالب لتصحيح الحضور — يُقدِّم الطلب فقط، لا يكتب على
 * `attendance_days` أبداً. الاعتماد الفعلي في
 * `AttendanceCorrectionApprovalController`، بصلاحيةٍ منفصلة، على نمط
 * `AdvanceRequestController`/`AdvanceApprovalController` لطلبات السلف.
 */
class AttendanceCorrectionRequestController extends Controller
{
    use ScopesToAccessibleEmployees;
    use SubmitsAttendanceCorrectionRequests;

    public function __construct()
    {
        $this->middleware('permission.role:attendance_corrections.index', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:attendance_corrections.create', ['only' => ['create', 'store', 'cancel']]);
    }

    /**
     * الملخّصُ يُحسَب **قبل** الترقيم، لا بعده.
     *
     * `paginate()` تُعدّل الباني نفسَه: تضع عليه `limit/offset` الصفحةِ
     * المطلوبة. وكانت النُّسخُ الأربعُ (`clone $query`) تُؤخذ بعدها، فتحمل
     * `offset` معها — و`count(*)` صفٌّ واحدٌ يتخطّاه أيُّ إزاحةٍ أكبر من
     * صفر. فالشاراتُ الأربع تُظهر الأعدادَ صحيحةً في الصفحة الأولى
     * و**أصفاراً** في كل صفحةٍ بعدها.
     */
    public function index(Request $request)
    {
        $query = AttendanceCorrectionRequest::with(['employee', 'requestedBy', 'approvedBy'])
            ->orderByDesc('id');

        $this->scopeToEmployees($query);

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->pending()->count(),
            'approved' => (clone $query)->approved()->count(),
            'rejected' => (clone $query)->rejected()->count(),
        ];

        // التصفيةُ بالحالة بعد الملخّص: الشاراتُ تعدّ الكلَّ وتُصفّي عند الضغط.
        $status = $request->input('status');

        if (array_key_exists($status, AttendanceCorrectionRequest::statusLabels())) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('hr::attendance.correction_requests.index', compact('requests', 'stats', 'status'));
    }

    public function create()
    {
        $employees = $this->scopedEmployeeOptions();

        return view('hr::attendance.correction_requests.create', compact('employees'));
    }

    public function store(AttendanceCorrectionRequestRequest $request)
    {
        $correction = $this->submitCorrectionRequest($request);

        return redirect()
            ->route('hr.attendance-corrections.show', $correction->id)
            ->with('success', 'تم تقديم طلب التصحيح بنجاح — بانتظار الاعتماد.');
    }

    public function show($id)
    {
        $correction = AttendanceCorrectionRequest::with(['employee', 'requestedBy', 'approvedBy'])->findOrFail($id);

        $this->authorizeEmployeeScope($correction->employee_id);

        return view('hr::attendance.correction_requests.show', compact('correction'));
    }

    /** إلغاءٌ ذاتيّ من مقدِّم الطلب نفسه، طالما لم يُبتّ فيه بعد. */
    public function cancel($id)
    {
        $correction = AttendanceCorrectionRequest::pending()->findOrFail($id);

        if ((int) $correction->requested_by !== (int) auth()->id()) {
            abort(403, 'لا يمكنك إلغاء طلبٍ لم تُقدِّمه أنت.');
        }

        $correction->update([
            'status' => AttendanceCorrectionRequest::STATUS_REJECTED,
            'rejection_reason' => 'أُلغي من قِبل مقدِّم الطلب.',
        ]);

        ModelsLog::create([
            'type' => 'attendance_correction_request_log',
            'type_id' => $correction->id,
            'type_log' => 'log',
            'description' => 'تم إلغاء طلب تصحيح الحضور من مقدِّمه',
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('hr.attendance-corrections.index')
            ->with('success', 'تم إلغاء طلب التصحيح.');
    }
}
