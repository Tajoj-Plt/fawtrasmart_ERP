<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeavePermissions;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Log as ModelsLog;
use App\Services\Attendance\LeaveApprovalAuthorizer;
use App\Services\AttendanceProcessorService;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Modules\HR\Support\BranchTree;

class LeaveRequestsController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        /*
            كلُّ فعلٍ بصلاحيته `leave_requests.*`، ولا صلاحيةَ «لنفسه»:
            نطاقُ الرؤية (`data_scope_*`) وحده يحدّد طلباتِ أيِّ موظفين
            تظهر وتُقدَّم وتُعدَّل — فالموظف الذي يرى طلباته وحده يحمل
            `leave_requests.index` مع `data_scope_own`.
        */
        $this->middleware('permission.role:leave_requests.index', ['only' => ['index', 'search']]);
        $this->middleware('permission.role:leave_requests.show', ['only' => ['show']]);
        $this->middleware('permission.role:leave_requests.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:leave_requests.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:leave_requests.destroy', ['only' => ['destroy']]);
        $this->middleware('permission.role:leave_requests.approve', ['only' => [
            'approve', 'reject', 'cancelApproval', 'returnFromRejection',
            'updateStatus', 'processAction',
        ]]);
        // منتقي الموظفين ورصيدُ الموظف يخدمان نموذجَي الإضافة والتعديل وشاشةَ القائمة
        $this->middleware('permission.role:leave_requests.index|leave_requests.create|leave_requests.edit', ['only' => [
            'getEmployeeLeaveBalance', 'getEmployeesList', 'leaveEndDate',
        ]]);
    }

    public function index()
    {
        $query = LeaveRequest::with(['employee', 'approver', 'leaveType'])
            ->orderBy('created_at', 'desc');

        // نطاقُ الموظفين: الصلاحيةُ تفتح الشاشة، والنطاقُ يحدّد بياناتِ مَن فيها.
        // ويقع **قبل** التصفيح: بعده يصير المتغيّرُ صفحةً لا استعلاماً.
        $this->scopeToEmployees($query);

        $leaveRequests = $query->paginate(15);
        $leaveTypes = LeaveType::all();
        $departments = Department::all();
        $branches = Branch::all();

        return view('hr::attendance.leave_requests.index', compact('leaveRequests', 'leaveTypes', 'departments', 'branches'));
    }

    public function search(Request $request)
    {
        $query = LeaveRequest::with(['employee', 'approver', 'leaveType']);

        /*
            نطاقُ الموظفين هنا كما في `index()`.

            كان مسارُ البحث بـAJAX يُغفله وحدَه، والشاشةُ تستدعيه عند أوّل
            حرفٍ يُكتب أو أوّل مُرشِّحٍ يُغيَّر. فمن يفتح الصفحة يرى طلباتِ
            نطاقه، فإذا بحث رأى طلباتِ إجازاتِ المنشأة كلِّها — بأسماء
            أصحابها وأسبابها وأرصدتهم.
        */
        $this->scopeToEmployees($query);

        // فلترة بواسطة الموظف (الاسم أو الكود)
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('middle_name', 'LIKE', "%{$search}%")
                    ->orWhere('nickname', 'LIKE', "%{$search}%")
                    ->orWhere('employee_code', 'LIKE', "%{$search}%");
            });
        }

        // فلترة بواسطة تاريخ بداية الإجازة
        if ($request->filled('from_date')) {
            $query->where('start_date', '>=', $request->from_date);
        }

        // فلترة بواسطة تاريخ نهاية الإجازة
        if ($request->filled('to_date')) {
            $query->where('end_date', '<=', $request->to_date);
        }

        // فلترة بواسطة تاريخ الإنشاء
        if ($request->filled('created_date')) {
            $query->whereDate('created_at', $request->created_date);
        }

        // فلترة بواسطة نوع الإجازة
        if ($request->filled('leave_type')) {
            $query->whereHas('leaveType', function ($q) use ($request) {
                $q->where('id', $request->leave_type);
            });
        }

        // فلترة بواسطة القسم
        if ($request->filled('department')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department);
            });
        }

        // فلترة بواسطة الفرع
        if ($request->filled('branch')) {
            $query->whereHas('employee', function ($q) use ($request) {
                BranchTree::apply($q, $request->branch);
            });
        }

        // فلترة بواسطة الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // عدد السجلات المعروضة كما يختاره المستخدم من قائمة "عرض ... سجل"
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->paginate($perPage)->appends($request->all());

        // إرجاع HTML للجدول (الترقيم مضمَّن داخل تذييل الجدول)
        $tableHtml = view('hr::attendance.leave_requests.partials.table_rows', compact('leaveRequests'))->render();

        return response()->json([
            'table' => $tableHtml,
            'total' => $leaveRequests->total(),
        ]);
    }

    /**
     * الحصول على قائمة الموظفين للبحث
     */

    /**
     * الحصول على رصيد إجازات موظف معين
     */

    /**
     * عرض نموذج إنشاء طلب إجازة جديد
     */
    public function create()
    {
        $employees = $this->scopedEmployeeOptions([]);

        $leaveTypes = LeaveType::all();

        return view('hr::attendance.leave_requests.create', compact('employees', 'leaveTypes'));
    }

    /**
     * حفظ طلب إجازة جديد
     */
    public function store(Request $request)
    {
        $allowBackdated = AttendanceSetting::current()->allow_backdated_requests;
        $startDateRule = $allowBackdated
            ? 'required|date'
            : 'required|date|after_or_equal:today';

        // التحقق من صحة البيانات
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'request_type' => 'required|in:leave,emergency,sick',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => $startDateRule,
            'days' => 'required|integer|min:1',
            'description' => 'nullable|string|max:1000',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ], [
            'employee_id.required' => 'يجب اختيار موظف',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'request_type.required' => 'يجب اختيار نوع الطلب',
            'request_type.in' => 'نوع الطلب غير صحيح',
            'leave_type_id.required' => 'يجب اختيار نوع الإجازة',
            'leave_type_id.exists' => 'نوع الإجازة المحدد غير موجود',
            'start_date.required' => 'يجب تحديد تاريخ البدء',
            'start_date.date' => 'تاريخ البدء غير صحيح',
            'start_date.after_or_equal' => 'تاريخ البدء يجب أن يكون اليوم أو بعده (فعّل «السماح بتواريخ سابقة» من الإعدادات الأساسية)',
            'days.required' => 'يجب تحديد عدد الأيام',
            'days.integer' => 'عدد الأيام يجب أن يكون رقم صحيح',
            'days.min' => 'عدد الأيام يجب أن يكون أكبر من صفر',
            'description.max' => 'الوصف يجب ألا يزيد عن 1000 حرف',
            'attachments.*.file' => 'المرفق يجب أن يكون ملف',
            'attachments.*.mimes' => 'المرفقات المسموحة: PDF, DOC, DOCX, JPG, JPEG, PNG',
            'attachments.*.max' => 'حجم الملف يجب ألا يزيد عن 5 ميجابايت',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'يرجى التحقق من البيانات المدخلة');
        }

        $this->authorizeEmployeeScope($request->employee_id);

        try {
            $employee = Employee::findOrFail($request->employee_id);
            // أيام العمل فقط — العطل الأسبوعية والرسمية لا تُحتسب من الرصيد
            $startDate = Carbon::parse($request->start_date);
            $endDate = app(AttendanceProcessorService::class)
                ->endDateForWorkingDays($employee, $startDate, (int) $request->days);

            // التحقق من تداخل الإجازات
            $existingLeave = LeaveRequest::where('employee_id', $request->employee_id)
                ->where('status', '!=', 'rejected')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->exists();

            if ($existingLeave) {
                return back()
                    ->withInput()
                    ->with('error', 'يوجد طلب إجازة أخر متداخل مع هذه الفترة');
            }

            if ($violation = $this->leaveTypeViolation($employee ?? Employee::findOrFail($request->employee_id), (int) $request->leave_type_id, (int) $request->days, $startDate)) {
                return back()->withInput()->with('error', $violation);
            }

            // رفع المرفقات إذا وجدت
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                    $path = $file->storeAs('leave_requests', $filename, 'public');
                    $attachments[] = $path;
                }
            }

            // إنشاء طلب الإجازة
            $leaveRequest = LeaveRequest::create([
                'employee_id' => $request->employee_id,
                'request_type' => $request->request_type,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $request->days,
                'description' => $request->description,
                'status' => 'pending',
                'attachments' => ! empty($attachments) ? json_encode($attachments) : null,
            ]);

            ModelsLog::create([
                'type' => 'leave_requests',
                'type_id' => $leaveRequest->id,
                'type_log' => 'log',
                'description' => 'تم إنشاء طلب إجازة جديد للموظف: '.$leaveRequest->employee->full_name,
                'created_by' => auth()->id(),
            ]);

            return redirect()
                ->route('attendance.leave_requests.index')
                ->with('success', 'تم إنشاء طلب الإجازة بنجاح')
                ->with('sweet_alert', [
                    'type' => 'success',
                    'title' => 'نجح الحفظ!',
                    'message' => 'تم إنشاء طلب الإجازة بنجاح',
                ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.LeaveRequests', 'store');

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء حفظ طلب الإجازة: '.$e->getMessage())
                ->with('sweet_alert', [
                    'type' => 'error',
                    'title' => 'خطأ!',
                    'message' => 'حدث خطأ أثناء حفظ البيانات',
                ]);
        }
    }

    /**
     * عرض تفاصيل طلب إجازة محدد
     */
    public function show($id, Request $request)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $this->authorizeEmployeeScope($leaveRequest->employee_id);

        // جلب سجل الإجازات للموظف مع الفلترة
        $leaveRecordsQuery = LeavePermissions::where('employee_id', $leaveRequest->employee_id)
            ->with('employee')
            ->orderBy('start_date', 'desc');

        // فلترة حسب الشهر والسنة إذا تم تمريرها
        if ($request->filled('filter_month')) {
            $leaveRecordsQuery->whereMonth('start_date', $request->filter_month);
        }

        if ($request->filled('filter_year')) {
            $leaveRecordsQuery->whereYear('start_date', $request->filter_year);
        }

        $leaveRecords = $leaveRecordsQuery->get();

        // جلب سجل النشاطات مع التجميع حسب التاريخ
        $logs = ModelsLog::where('type', 'leave_requests')
            ->where('type_id', $id)
            ->with('user.branch')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('hr::attendance.leave_requests.show', compact('leaveRequest', 'leaveRecords', 'logs'));
    }

    /**
     * عرض نموذج تعديل طلب الإجازة
     */
    public function edit(LeaveRequest $leaveRequest)
    {
        $this->authorizeEmployeeScope($leaveRequest->employee_id);

        // التحقق من إمكانية التعديل
        if (! $leaveRequest->isPending()) {
            return redirect()
                ->route('attendance.leave_requests.index')
                ->with('error', 'لا يمكن تعديل طلب الإجازة بعد الموافقة عليه أو رفضه')
                ->with('sweet_alert', [
                    'type' => 'warning',
                    'title' => 'تحذير!',
                    'message' => 'لا يمكن تعديل هذا الطلب',
                ]);
        }

        $employees = $this->scopedEmployeeOptions([]);

        $leaveTypes = LeaveType::orderBy('name')
            ->get();

        return view('hr::attendance.leave_requests.edit', compact('leaveRequest', 'employees', 'leaveTypes'));
    }

    /**
     * تحديث طلب الإجازة
     */
    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        // التحقق من إمكانية التعديل
        if (! $leaveRequest->isPending()) {
            return redirect()
                ->route('attendance.leave_requests.index')
                ->with('error', 'لا يمكن تعديل طلب الإجازة بعد الموافقة عليه أو رفضه')
                ->with('sweet_alert', [
                    'type' => 'warning',
                    'title' => 'تحذير!',
                    'message' => 'لا يمكن تعديل هذا الطلب',
                ]);
        }

        // التحقق من صحة البيانات
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'request_type' => 'required|in:leave,emergency,sick',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => AttendanceSetting::current()->allow_backdated_requests ? 'required|date' : 'required|date|after_or_equal:today',
            'days' => 'required|integer|min:1',
            'description' => 'nullable|string|max:1000',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ], [
            'employee_id.required' => 'يجب اختيار موظف',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'request_type.required' => 'يجب اختيار نوع الطلب',
            'request_type.in' => 'نوع الطلب غير صحيح',
            'leave_type_id.required' => 'يجب اختيار نوع الإجازة',
            'leave_type_id.exists' => 'نوع الإجازة المحدد غير موجود',
            'start_date.required' => 'يجب تحديد تاريخ البدء',
            'start_date.date' => 'تاريخ البدء غير صحيح',
            'days.required' => 'يجب تحديد عدد الأيام',
            'days.integer' => 'عدد الأيام يجب أن يكون رقم صحيح',
            'days.min' => 'عدد الأيام يجب أن يكون أكبر من صفر',
            'description.max' => 'الوصف يجب ألا يزيد عن 1000 حرف',
            'attachments.*.file' => 'المرفق يجب أن يكون ملف',
            'attachments.*.mimes' => 'المرفقات المسموحة: PDF, DOC, DOCX, JPG, JPEG, PNG',
            'attachments.*.max' => 'حجم الملف يجب ألا يزيد عن 5 ميجابايت',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'يرجى التحقق من البيانات المدخلة');
        }

        $this->authorizeEmployeeScope($leaveRequest->employee_id);
        $this->authorizeEmployeeScope($request->employee_id);

        try {
            $employee = Employee::findOrFail($request->employee_id);
            $startDate = Carbon::parse($request->start_date);
            $endDate = app(AttendanceProcessorService::class)
                ->endDateForWorkingDays($employee, $startDate, (int) $request->days);

            // التحقق من تداخل الإجازات (باستثناء الطلب الحالي)
            $existingLeave = LeaveRequest::where('employee_id', $request->employee_id)
                ->where('id', '!=', $leaveRequest->id)
                ->where('status', '!=', 'rejected')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->exists();

            if ($existingLeave) {
                return back()
                    ->withInput()
                    ->with('error', 'يوجد طلب إجازة أخر متداخل مع هذه الفترة');
            }

            if ($violation = $this->leaveTypeViolation($employee ?? Employee::findOrFail($request->employee_id), (int) $request->leave_type_id, (int) $request->days, $startDate)) {
                return back()->withInput()->with('error', $violation);
            }

            // التعامل مع المرفقات الجديدة
            $attachments = $leaveRequest->attachments ? json_decode($leaveRequest->attachments, true) : [];

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                    $path = $file->storeAs('leave_requests', $filename, 'public');
                    $attachments[] = $path;
                }
            }

            // تحديث طلب الإجازة
            $leaveRequest->update([
                'employee_id' => $request->employee_id,
                'request_type' => $request->request_type,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $request->days,
                'description' => $request->description,
                'attachments' => ! empty($attachments) ? json_encode($attachments) : null,
            ]);
            ModelsLog::create([
                'type' => 'leave_requests',
                'type_id' => $leaveRequest->id,
                'type_log' => 'log',
                'description' => 'تم تحديث طلب إجازة جديد للموظف: '.$leaveRequest->employee->full_name,
                'created_by' => auth()->id(),
            ]);

            return redirect()
                ->route('attendance.leave_requests.index')
                ->with('success', 'تم تحديث طلب الإجازة بنجاح')
                ->with('sweet_alert', [
                    'type' => 'success',
                    'title' => 'نجح التحديث!',
                    'message' => 'تم تحديث طلب الإجازة بنجاح',
                ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.LeaveRequests', 'update');

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث طلب الإجازة: '.$e->getMessage())
                ->with('sweet_alert', [
                    'type' => 'error',
                    'title' => 'خطأ!',
                    'message' => 'حدث خطأ أثناء تحديث البيانات',
                ]);
        }
    }

    /**
     * حذف طلب الإجازة
     */
    public function destroy($id)
    {
        try {
            $leaveRequest = LeaveRequest::findOrFail($id);
            $this->authorizeEmployeeScope($leaveRequest->employee_id);

            // التحقق من إمكانية الحذف
            if ($leaveRequest->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف طلب إجازة مقبول',
                ], 422);
            }

            // حذف المرفقات
            if ($leaveRequest->attachments) {
                $attachments = json_decode($leaveRequest->attachments, true);
                foreach ($attachments as $attachment) {
                    Storage::disk('public')->delete($attachment);
                }
            }

            // تسجيل الحذف
            ModelsLog::create([
                'type' => 'leave_requests',
                'type_id' => $leaveRequest->id,
                'type_log' => 'delete',
                'description' => 'تم حذف طلب إجازة للموظف: '.$leaveRequest->employee->full_name,
                'created_by' => auth()->id(),
            ]);

            // حذف الطلب
            $leaveRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف طلب الإجازة بنجاح',
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في حذف طلب الإجازة: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الطلب',
            ], 500);
        }
    }

    /**
     * الحصول على قائمة الموظفين
     */
    public function getEmployeesList()
    {
        try {
            // لا last_name ولا is_active في جدول employees: الاسمُ الثالث nickname
            // والحالةُ في العمود status. كان هذا يرمي 1054 فيفشل مودال الأرصدة.
            // نطاقُ المستخدم وفرعُ الشريط معاً — لا يظهر في المنتقي من لا يراه
            $employees = $this->scopeToEmployees(Employee::query(), 'id')->select('id', 'first_name', 'middle_name', 'nickname', 'employee_code')
                ->whereIn('status', [1, '1', 'active']) // فقط الموظفين النشطين
                ->orderBy('first_name', 'asc')
                ->get()
                ->append('full_name'); // مُعرَّف في Employee::getFullNameAttribute()

            return response()->json($employees);
        } catch (\Exception $e) {
            Log::error('خطأ في تحميل قائمة الموظفين: '.$e->getMessage());

            return response()->json(['error' => 'حدث خطأ في تحميل الموظفين'], 500);
        }
    }

    /**
     * الحصول على رصيد إجازات موظف معين
     */
    public function getEmployeeLeaveBalance($employeeId)
    {
        // رصيدُ موظفٍ خارج النطاق لا يُكشف — قبل try كي لا تبتلعها catch
        $this->authorizeEmployeeScope($employeeId);

        try {
            $employee = Employee::findOrFail($employeeId);
            $currentYear = now()->year;

            // جلب أرصدة الإجازات للموظف من مودل EmployeeLeaveBalance
            $employeeBalances = EmployeeLeaveBalance::with('leaveType')
                ->where('employee_id', $employeeId)
                ->where('year', $currentYear)
                ->get();

            $totalBalance = 0;
            $totalUsed = 0;
            $leaveTypesData = [];

            /*
                كلُّ نوع إجازةٍ يظهر برصيده — ومن لا صفَّ له يظهر بالحدّ
                السنوي لنوعه. كان النوعُ بلا صفٍّ يُعاد «404 — يجب تحديد
                الأرصدة» فتعرض الشاشة «0 يوم»، بينما الاعتمادُ ينشئ الصفَّ
                بالحدّ السنوي ويخصم منه (`deductLeaveBalanceForRequest`).
                فالشاشةُ تقول صفراً والاعتمادُ يمرّ.
            */
            $byType = $employeeBalances->keyBy('leave_type_id');

            foreach (LeaveType::orderBy('name')->get() as $type) {
                $balance = $byType->get($type->id);

                $totalAvailable = $balance
                    ? (int) (($balance->initial_balance ?? 0) + ($balance->carried_forward ?? 0) + ($balance->additional_balance ?? 0))
                    : (int) ($type->max_days_per_year ?? 0);
                $usedAmount = $balance ? (int) ($balance->used_balance ?? 0) : 0;
                $remaining = max(0, $totalAvailable - $usedAmount);

                $totalBalance += $totalAvailable;
                $totalUsed += $usedAmount;

                $leaveTypesData[] = [
                    'name' => $type->name ?? 'غير محدد',
                    'total_balance' => $totalAvailable,
                    'used_balance' => $usedAmount,
                    'remaining_balance' => $remaining,
                ];
            }

            // التحقق من أن لدينا على الأقل بيانات واحدة
            if (empty($leaveTypesData)) {
                $leaveTypesData = [[
                    'name' => 'لا توجد أنواع إجازات متاحة',
                    'total_balance' => 0,
                    'used_balance' => 0,
                    'remaining_balance' => 0,
                ]];
            }

            // الحصول على الاسم الكامل للموظف
            $fullName = $employee->full_name;

            $response = [
                'success' => true,
                'employee' => [
                    'id' => (int) $employee->id,
                    'name' => $fullName ?: 'غير محدد',
                    'employee_code' => $employee->employee_code ?? (string) $employee->id,
                    'department' => optional($employee->department)->name ?? 'غير محدد',
                    'job_title' => optional($employee->jobTitle)->name ?? 'غير محدد',
                ],
                'total_balance' => (int) $totalBalance,
                'used_balance' => (int) $totalUsed,
                'remaining_balance' => (int) max(0, $totalBalance - $totalUsed),
                'leave_types' => $leaveTypesData,
            ];

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            Log::warning('الموظف غير موجود: '.$employeeId);

            return response()->json([
                'success' => false,
                'error' => 'الموظف المحدد غير موجود',
            ], 404);

        } catch (\Exception $e) {
            Log::error('خطأ في تحميل رصيد الإجازات للموظف '.$employeeId.': '.$e->getMessage());
            Log::error('Stack Trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'حدث خطأ في تحميل رصيد الإجازات',
                'details' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * رصيدُ الموظف من نوع إجازةٍ بعينه لسنةٍ معيّنة.
     *
     * المصدرُ الصحيح هو `employee_leave_balances` — نفس ما يقرؤه مودال الرصيد.
     * وإن لم يكن للموظف صفٌّ لهذه السنة، نرجع إلى الحدّ السنوي على نوع الإجازة.
     */
    /**
     * تاريخُ نهاية الإجازة كما سيحفظه الخادم — بأيام العمل.
     *
     * كانت الشاشةُ تحسبه في المتصفح بأيامٍ تقويمية (البداية + الأيام − 1)،
     * والخادمُ يحفظه بأيام العمل متخطّياً العطلَ الأسبوعية والرسمية. فإجازةُ
     * خمسة أيامٍ تبدأ الأربعاء تُعرض منتهيةً الأحد وتُحفظ منتهيةً الثلاثاء.
     */
    public function leaveEndDate(Request $request, $employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        $data = $request->validate([
            'start_date' => 'required|date',
            'days' => 'required|integer|min:1|max:365',
        ]);

        $employee = Employee::findOrFail($employeeId);
        $end = app(AttendanceProcessorService::class)
            ->endDateForWorkingDays($employee, Carbon::parse($data['start_date']), (int) $data['days']);

        return response()->json(['success' => true, 'end_date' => $end->toDateString()]);
    }

    /**
     * قواعدُ نوع الإجازة على الطلب — تُفحص عند الإنشاء والتعديل.
     *
     * كانت تُحفظ في شاشة «أنواع الإجازات» ولا تُقرأ في أيّ مكان: نوعٌ
     * حدُّه يومان متتاليان يقبل طلباً بخمسة، ونوعٌ يُستحقّ بعد تسعين يوماً
     * من التعيين يقبله موظفٌ عُيّن أمس.
     */
    private function leaveTypeViolation(Employee $employee, int $leaveTypeId, int $days, Carbon $start): ?string
    {
        $type = LeaveType::find($leaveTypeId);

        if (! $type) {
            return null;
        }

        if ((int) $type->max_consecutive_days > 0 && $days > (int) $type->max_consecutive_days) {
            return sprintf(
                'إجازة «%s» لا تزيد على %d يوم متتالٍ — المطلوب %d يوم. قسّمها على طلبين أو اختر نوعاً آخر.',
                $type->name, $type->max_consecutive_days, $days
            );
        }

        if ((int) $type->applicable_after > 0 && $employee->hire_date) {
            $eligibleFrom = Carbon::parse($employee->hire_date)->addDays((int) $type->applicable_after);

            if ($start->lt($eligibleFrom)) {
                return sprintf(
                    'إجازة «%s» تُستحق بعد %d يوماً من التعيين — يستحقها هذا الموظف من %s.',
                    $type->name, $type->applicable_after, $eligibleFrom->toDateString()
                );
            }
        }

        return null;
    }

    private function getEmployeeLeaveTypeBalance($employeeId, $leaveTypeId, ?int $year = null)
    {
        $year = $year ?? (int) now()->year;

        $balance = EmployeeLeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($balance) {
            return (float) $balance->getTotalAvailableBalance();
        }

        return (float) (LeaveType::find($leaveTypeId)?->max_days_per_year ?? 0);
    }

    /**
     * التحقق من توفر رصيد كافٍ قبل الموافقة — من عمود used_balance لا من مجموع الطلبات.
     */
    private function checkLeaveBalance($employeeId, $leaveTypeId, $requestedDays, ?int $year = null)
    {
        $year = $year ?? (int) now()->year;

        $balance = EmployeeLeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($balance) {
            $available = $balance->getActualRemainingBalance();
            $total = $balance->getTotalAvailableBalance();
            $used = (int) $balance->used_balance;

            return [
                'has_sufficient_balance' => $available >= $requestedDays,
                'available_balance' => $available,
                'total_balance' => $total,
                'used_balance' => $used,
                'requested_days' => $requestedDays,
            ];
        }

        // بلا صف رصيد: الحدّ السنوي للنوع ناقص مجموع الطلبات المعتمدة لتلك السنة
        $totalBalance = $this->getEmployeeLeaveTypeBalance($employeeId, $leaveTypeId, $year);
        $usedDays = (int) LeaveRequest::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days');

        $availableBalance = $totalBalance - $usedDays;

        return [
            'has_sufficient_balance' => $availableBalance >= $requestedDays,
            'available_balance' => $availableBalance,
            'total_balance' => $totalBalance,
            'used_balance' => $usedDays,
            'requested_days' => $requestedDays,
        ];
    }

    /** خصم أيام الطلب من صف الرصيد (داخل معاملة، مع قفل). */
    private function deductLeaveBalanceForRequest(LeaveRequest $leaveRequest): bool
    {
        $year = (int) Carbon::parse($leaveRequest->start_date)->year;

        $balance = EmployeeLeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            // أنشئ صفاً من الحد السنوي إن وُجد، وإلا ارفض الخصم الصريح
            $initial = (int) (LeaveType::find($leaveRequest->leave_type_id)?->max_days_per_year ?? 0);
            if ($initial <= 0) {
                return false;
            }

            $balance = EmployeeLeaveBalance::create([
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'year' => $year,
                'initial_balance' => $initial,
                'used_balance' => 0,
                'remaining_balance' => $initial,
                'carried_forward' => 0,
                'additional_balance' => 0,
            ]);
            $balance = EmployeeLeaveBalance::whereKey($balance->id)->lockForUpdate()->first();
        }

        return $balance->deductDays(
            (int) $leaveRequest->days,
            'اعتماد طلب إجازة #'.$leaveRequest->id
        );
    }

    /** إرجاع أيام الطلب إلى الرصيد عند إلغاء الاعتماد. */
    private function restoreLeaveBalanceForRequest(LeaveRequest $leaveRequest): void
    {
        $year = (int) Carbon::parse($leaveRequest->start_date)->year;

        $balance = EmployeeLeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            $balance->addDays(
                (int) $leaveRequest->days,
                'إلغاء اعتماد طلب إجازة #'.$leaveRequest->id
            );
        }
    }

    /**
     * التحقق من توفر رصيد كافي قبل الموافقة
     */
    /**
     * الموافقة على طلب الإجازة
     */
    /**
     * اعتماد الطلب أو رفضه من شاشة الطلبات.
     *
     * الشاشةُ تنادي هذا المسار منذ البداية (زرّا «موافقة» و«رفض» في جدول
     * الطلبات يرسلان status إليه)، لكن الدالّة لم تكن موجودة — فكان كل ضغطٍ
     * على أيٍّ منهما يرتدّ بـ«Method updateStatus does not exist» أي 500.
     *
     * لا نُفوِّض إلى reject() لأنها تشترط سبب رفضٍ لا ترسله هذه الشاشة؛
     * ونعيد استعمال فحص الرصيد نفسه الذي تعتمد عليه approve().
     */
    public function updateStatus(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:500',
        ], [
            'status.required' => 'يجب تحديد الحالة',
            'status.in' => 'الحالة غير معروفة',
        ]);

        if (! app(LeaveApprovalAuthorizer::class)->canDecide(Auth::user(), $leaveRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'لست مخوَّلاً بالبتّ في هذا الطلب حسب قنوات الموافقة في الإعدادات الأساسية',
            ], 403);
        }

        $this->authorizeEmployeeScope($leaveRequest->employee_id);

        try {
            DB::transaction(function () use ($leaveRequest, $validated) {
                $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'pending') {
                    abort(422, 'سبق البتّ في هذا الطلب — لا يمكن تغيير حالته مرّتين.');
                }

                if ($validated['status'] === 'approved') {
                    $year = (int) Carbon::parse($locked->start_date)->year;
                    $balanceCheck = $this->checkLeaveBalance(
                        $locked->employee_id,
                        $locked->leave_type_id,
                        $locked->days,
                        $year
                    );

                    if (! $balanceCheck['has_sufficient_balance']) {
                        abort(422, sprintf(
                            'الرصيد المتاح غير كافي. المطلوب: %d أيام، المتاح: %d أيام',
                            $balanceCheck['requested_days'],
                            $balanceCheck['available_balance']
                        ));
                    }

                    if (! $this->deductLeaveBalanceForRequest($locked)) {
                        abort(422, 'تعذّر خصم الرصيد — تحقق من رصيد الموظف لهذا النوع.');
                    }
                }

                $locked->update([
                    'status' => $validated['status'],
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejection_reason' => $validated['status'] === 'rejected'
                        ? ($validated['rejection_reason'] ?? null)
                        : null,
                ]);

                ModelsLog::create([
                    'type' => 'leave_requests',
                    'type_id' => $locked->id,
                    'type_log' => $validated['status'],
                    'description' => sprintf(
                        'تم %s طلب إجازة للموظف: %s - النوع: %s - المدة: %d أيام',
                        $validated['status'] === 'approved' ? 'قبول' : 'رفض',
                        $locked->employee->full_name ?? '—',
                        $locked->leaveType->name ?? 'غير محدد',
                        $locked->days
                    ),
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => $validated['status'] === 'approved'
                ? 'تم قبول طلب الإجازة وخصم الأيام من الرصيد'
                : 'تم رفض طلب الإجازة',
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {

        if (! app(LeaveApprovalAuthorizer::class)->canDecide(Auth::user(), $leaveRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'لست مخوَّلاً باعتماد هذا الطلب حسب قنوات الموافقة في الإعدادات الأساسية',
            ], 403);
        }

        $this->authorizeEmployeeScope($leaveRequest->employee_id);

        try {
            DB::transaction(function () use ($leaveRequest) {
                $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'pending') {
                    abort(422, 'لا يمكن الموافقة على هذا الطلب - الطلب ليس في حالة الانتظار');
                }

                $year = (int) Carbon::parse($locked->start_date)->year;
                $balanceCheck = $this->checkLeaveBalance(
                    $locked->employee_id,
                    $locked->leave_type_id,
                    $locked->days,
                    $year
                );

                if (! $balanceCheck['has_sufficient_balance']) {
                    abort(422, sprintf(
                        'الرصيد المتاح غير كافي. المطلوب: %d أيام، المتاح: %d أيام',
                        $balanceCheck['requested_days'],
                        $balanceCheck['available_balance']
                    ));
                }

                if (! $this->deductLeaveBalanceForRequest($locked)) {
                    abort(422, 'تعذّر خصم الرصيد — تحقق من رصيد الموظف لهذا النوع.');
                }

                $locked->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                ModelsLog::create([
                    'type' => 'leave_requests',
                    'type_id' => $locked->id,
                    'type_log' => 'approved',
                    'description' => sprintf(
                        'تم قبول طلب إجازة للموظف: %s - النوع: %s - المدة: %d أيام وخصم من الرصيد',
                        $locked->employee->first_name,
                        $locked->leaveType->name ?? 'غير محدد',
                        $locked->days
                    ),
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'تم قبول طلب الإجازة بنجاح وخصم الأيام من الرصيد',
        ]);
    }

    /**
     * رفض طلب الإجازة
     */
    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        if (! app(LeaveApprovalAuthorizer::class)->canDecide(Auth::user(), $leaveRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'لست مخوَّلاً برفض هذا الطلب حسب قنوات الموافقة في الإعدادات الأساسية',
            ], 403);
        }

        $this->authorizeEmployeeScope($leaveRequest->employee_id);

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ], [
            'rejection_reason.required' => 'يجب إدخال سبب الرفض',
            'rejection_reason.max' => 'سبب الرفض يجب ألا يزيد عن 500 حرف',
        ]);

        try {
            if (! $leaveRequest->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن رفض هذا الطلب',
                ], 422);
            }

            DB::transaction(function () use ($leaveRequest, $request) {
                $leaveRequest->update([
                    'status' => 'rejected',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejection_reason' => $request->rejection_reason,
                ]);

                ModelsLog::create([
                    'type' => 'leave_requests',
                    'type_id' => $leaveRequest->id,
                    'type_log' => 'rejected',
                    'description' => sprintf(
                        'تم رفض طلب إجازة للموظف: %s - السبب: %s',
                        $leaveRequest->employee->first_name,
                        $request->rejection_reason
                    ),
                    'created_by' => auth()->id(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'تم رفض طلب الإجازة',
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في رفض الطلب: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة الطلب',
            ], 500);
        }
    }

    /**
     * إلغاء الموافقة على طلب الإجازة
     */
    public function cancelApproval(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeEmployeeScope($leaveRequest->employee_id);

        if (! app(LeaveApprovalAuthorizer::class)->canDecide(Auth::user(), $leaveRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لك أن تبتّ في هذا الطلب — يبتّ فيه المسؤول عن اعتماد إجازات صاحبه.',
            ], 403);
        }

        try {
            if ($leaveRequest->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إلغاء الموافقة - الطلب ليس في حالة موافق عليه',
                ], 422);
            }

            DB::transaction(function () use ($leaveRequest, $request) {
                $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'approved') {
                    abort(422, 'لا يمكن إلغاء الموافقة - الطلب ليس في حالة موافق عليه');
                }

                $this->restoreLeaveBalanceForRequest($locked);

                $locked->update([
                    'status' => 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                    'approval_note' => null,
                    'cancellation_reason' => $request->input('note'),
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now(),
                ]);

                ModelsLog::create([
                    'type' => 'leave_requests',
                    'type_id' => $locked->id,
                    'type_log' => 'cancelled',
                    'description' => sprintf(
                        'تم إلغاء موافقة طلب إجازة للموظف: %s - تم إرجاع %d أيام للرصيد',
                        $locked->employee->first_name,
                        $locked->days
                    ),
                    'created_by' => auth()->id(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الموافقة بنجاح وإرجاع الأيام إلى رصيد الموظف',
            ]);

        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('خطأ في إلغاء الموافقة: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الموافقة',
            ], 500);
        }
    }

    /**
     * الرجوع من رفض طلب الإجازة
     */
    public function returnFromRejection(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeEmployeeScope($leaveRequest->employee_id);

        if (! app(LeaveApprovalAuthorizer::class)->canDecide(Auth::user(), $leaveRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لك أن تبتّ في هذا الطلب — يبتّ فيه المسؤول عن اعتماد إجازات صاحبه.',
            ], 403);
        }

        try {
            if ($leaveRequest->status !== 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن الرجوع من الرفض - الطلب ليس في حالة مرفوض',
                ], 422);
            }

            DB::transaction(function () use ($leaveRequest, $request) {
                $leaveRequest->update([
                    'status' => 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => null,
                    'return_reason' => $request->input('note'),
                    'returned_by' => auth()->id(),
                    'returned_at' => now(),
                ]);

                ModelsLog::create([
                    'type' => 'leave_requests',
                    'type_id' => $leaveRequest->id,
                    'type_log' => 'returned',
                    'description' => sprintf(
                        'تم الرجوع من رفض طلب إجازة للموظف: %s - أصبح الطلب في حالة انتظار',
                        $leaveRequest->employee->first_name
                    ),
                    'created_by' => auth()->id(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'تم الرجوع من الرفض بنجاح - أصبح الطلب في حالة انتظار',
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في الرجوع من الرفض: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الرجوع من الرفض',
            ], 500);
        }
    }

    /**
     * معالجة طلبات الإجراءات المختلفة
     */
    public function processAction(Request $request, LeaveRequest $leaveRequest)
    {
        $action = $request->input('action');

        switch ($action) {
            case 'approved':
                return $this->approve($request, $leaveRequest);
            case 'rejected':
                return $this->reject($request, $leaveRequest);
            case 'cancel_approval':
                return $this->cancelApproval($request, $leaveRequest);
            case 'return_from_rejection':
                return $this->returnFromRejection($request, $leaveRequest);
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'إجراء غير صحيح',
                ], 400);
        }
    }

    /**
     * عرض تفاصيل طلب الإجازة مع الرصيد
     */
}
