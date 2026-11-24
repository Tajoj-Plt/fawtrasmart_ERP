<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDays;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Support\OperationLog;
use App\Support\PayrollSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * وجهُ الطلب: تقديمُ طلب عملٍ إضافيّ وسحبُه — لا اعتماده.
 *
 * الفصلُ عن `OvertimeApprovalController` مقصود: من يطلب ليس من يبتّ،
 * وصلاحيتان مستقلّتان (`overtime.create` / `overtime.approve`).
 *
 * الصلاحيات (مجموعة `overtime.*`):
 *   القائمة ← overtime.index      التفاصيل ← overtime.show
 *   تقديم الطلب وسحبه ← overtime.create
 *
 * **على من** يحدّده نطاقُ الرؤية وحده: صاحبُ `data_scope_own` يرى طلباتِه
 * ويطلب لنفسه فقط (قائمةُ الموظفين فيها هو وحده)، والأوسعُ نطاقاً من يشملهم.
 */
class OvertimeRequestController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:overtime.index', ['only' => ['index']]);
        $this->middleware('permission.role:overtime.show', ['only' => ['show']]);
        $this->middleware('permission.role:overtime.create', ['only' => ['create', 'store', 'cancel']]);
    }

    public function index(Request $request)
    {
        $query = OvertimeRequest::with(['employee', 'requestedBy', 'approvedBy'])
            ->orderByDesc('overtime_date')
            ->orderByDesc('id');

        $this->scopeToEmployees($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('hr::salaries.overtime_requests.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'requiresApproval' => (bool) (PayrollSettings::row()->overtime_requires_approval ?? false),
            'filters' => $request->only(['status']),
        ]);
    }

    public function create()
    {
        return view('hr::salaries.overtime_requests.create', [
            'employees' => $this->accessibleEmployees(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'overtime_date' => 'required|date',
            'requested_minutes' => 'required|integer|min:15|max:1440',
            'reason' => 'required|string|max:1000',
        ], [], [
            'overtime_date' => 'تاريخ العمل الإضافي',
            'requested_minutes' => 'عدد الدقائق',
        ]);

        $this->authorizeEmployeeScope((int) $validated['employee_id']);

        $date = Carbon::parse($validated['overtime_date'])->startOfDay();

        /*
            طلبٌ نافذٌ واحدٌ لكل يوم. الفهرسُ في الهجرة غيرُ فريد عمداً —
            فالطلبُ المرفوضُ أو الملغى لا يمنع تقديمَ غيره — والحراسةُ هنا
            على الحالتين اللتين تُحدثان أثراً: المنتظرة والمعتمَدة.
        */
        $existing = OvertimeRequest::where('employee_id', $validated['employee_id'])
            ->whereDate('overtime_date', $date->toDateString())
            ->whereIn('status', [OvertimeRequest::STATUS_PENDING, OvertimeRequest::STATUS_APPROVED])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'overtime_date' => 'يوجد طلبٌ '.$existing->statusLabel().' لهذا الموظف في هذا اليوم.',
            ]);
        }

        try {
            OvertimeRequest::create($validated + [
                'overtime_date' => $date->toDateString(),
                'status' => OvertimeRequest::STATUS_PENDING,
                'requested_by' => $request->user()?->id,
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.OvertimeRequests', 'store');

            return back()->withInput()->with('error', 'تعذّر حفظ الطلب.');
        }

        return redirect()->route('hr.overtime-requests.index')
            ->with('success', 'قُدِّم الطلب وينتظر الاعتماد.');
    }

    public function show($id)
    {
        $overtimeRequest = OvertimeRequest::with(['employee', 'requestedBy', 'approvedBy'])->findOrFail($id);

        $this->authorizeEmployeeScope($overtimeRequest->employee_id);

        return view('hr::salaries.overtime_requests.show', [
            'overtimeRequest' => $overtimeRequest,
            // الدقائقُ المسجَّلة فعلاً على اليوم — ليقارن المعتمِدُ بالمطلوب
            'recordedMinutes' => $this->recordedMinutes($overtimeRequest),
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $overtimeRequest = OvertimeRequest::findOrFail($id);

        $this->authorizeEmployeeScope($overtimeRequest->employee_id);

        if (! $overtimeRequest->canBeDecided()) {
            return back()->with('error', 'لا يُسحب طلبٌ سبق البتّ فيه.');
        }

        $overtimeRequest->update(['status' => OvertimeRequest::STATUS_CANCELLED]);

        return back()->with('success', 'سُحب الطلب.');
    }

    private function recordedMinutes(OvertimeRequest $overtimeRequest): int
    {
        return (int) AttendanceDays::where('employee_id', $overtimeRequest->employee_id)
            ->whereDate('attendance_date', $overtimeRequest->overtime_date->toDateString())
            ->value('overtime_minutes');
    }

    private function accessibleEmployees()
    {
        $query = Employee::query()->orderBy('first_name');

        // النطاقُ وحده: صاحبُ `data_scope_own` لا يرى في القائمة إلا نفسَه
        $this->scopeToEmployees($query, 'id');

        return $query->get(['id', 'first_name', 'middle_name', 'nickname']);
    }
}
