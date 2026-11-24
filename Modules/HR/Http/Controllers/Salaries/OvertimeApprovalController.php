<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDays;
use App\Models\OvertimeRequest;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * الوجهُ المعتمِد لطلبات العمل الإضافي.
 *
 * `approve()` هو الموضعُ الوحيد الذي يُنشئ سقفاً قابلاً للصرف. وقفلُ
 * الصفّ قبل فحص `canBeDecided()` هو ما يمنع اعتمادين متزامنين من أن
 * يُنشئا سقفين للدقائق نفسِها — وهو نفسُ الحارس في
 * `AttendanceCorrectionApprovalController`.
 */
class OvertimeApprovalController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        // البتُّ كلُّه — القائمةُ المنتظرة والمراجعةُ والاعتمادُ والرفض — بصلاحية واحدة
        $this->middleware('permission.role:overtime.approve');
    }

    public function index()
    {
        $query = OvertimeRequest::with(['employee', 'requestedBy'])
            ->pending()
            ->orderBy('overtime_date');

        $this->scopeToEmployees($query);

        return view('hr::salaries.overtime_approvals.index', [
            'requests' => $query->paginate(25),
        ]);
    }

    public function show($id)
    {
        $overtimeRequest = OvertimeRequest::with(['employee', 'requestedBy', 'approvedBy'])->findOrFail($id);

        $this->authorizeEmployeeScope($overtimeRequest->employee_id);

        return view('hr::salaries.overtime_approvals.show', [
            'overtimeRequest' => $overtimeRequest,
            'recordedMinutes' => (int) AttendanceDays::where('employee_id', $overtimeRequest->employee_id)
                ->whereDate('attendance_date', $overtimeRequest->overtime_date->toDateString())
                ->value('overtime_minutes'),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approved_minutes' => 'required|integer|min:0|max:1440',
            'admin_notes' => 'nullable|string|max:1000',
        ], [], ['approved_minutes' => 'الدقائق المعتمَدة']);

        // خارج القفل عمداً: فحصُ النطاق ثابتٌ ولا يحتاج قفلَ صفّ، ووقوعُه
        // هنا يمنع 403 من أن يُبتلَع في catch أدناه.
        $this->authorizeEmployeeScope(OvertimeRequest::findOrFail($id)->employee_id);

        try {
            DB::beginTransaction();

            $overtimeRequest = OvertimeRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if (! $overtimeRequest->canBeDecided()) {
                DB::rollBack();

                return back()->with('error', 'هذا الطلب سبق البتّ فيه — لا يُعتمد مرتين.');
            }

            $overtimeRequest->update([
                'status' => OvertimeRequest::STATUS_APPROVED,
                'approved_minutes' => $validated['approved_minutes'],
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.OvertimeApprovals', 'approve');

            return back()->with('error', 'تعذّر اعتماد الطلب.');
        }

        return redirect()->route('hr.overtime-approvals.index')
            ->with('success', 'اعتُمد الطلب — سقفُ '.$validated['approved_minutes'].' دقيقة.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
        ], [], ['rejection_reason' => 'سبب الرفض']);

        $this->authorizeEmployeeScope(OvertimeRequest::findOrFail($id)->employee_id);

        try {
            DB::beginTransaction();

            $overtimeRequest = OvertimeRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if (! $overtimeRequest->canBeDecided()) {
                DB::rollBack();

                return back()->with('error', 'هذا الطلب سبق البتّ فيه.');
            }

            $overtimeRequest->update([
                'status' => OvertimeRequest::STATUS_REJECTED,
                'rejection_reason' => $validated['rejection_reason'],
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'HR.OvertimeApprovals', 'reject');

            return back()->with('error', 'تعذّر رفض الطلب.');
        }

        return redirect()->route('hr.overtime-approvals.index')->with('success', 'رُفض الطلب.');
    }
}
