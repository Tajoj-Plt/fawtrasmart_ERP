<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JopTitle;
use App\Models\LeavePermissions;
use App\Models\Log as ModelsLog;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\BranchTree;

class LeavePermissionsController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        // لكلّ فعلٍ صلاحيتُه `leave_permissions.*`؛ ونطاقُ الرؤية يحدّد موظفي القائمة والسجلّ
        $this->middleware('permission.role:leave_permissions.index', ['only' => ['index', 'getLeavePermissionsAjax']]);
        $this->middleware('permission.role:leave_permissions.show', ['only' => ['show']]);
        $this->middleware('permission.role:leave_permissions.create', ['only' => ['create', 'store', 'uploadImage']]);
        $this->middleware('permission.role:leave_permissions.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:leave_permissions.destroy', ['only' => ['delete']]);
    }

    public function index()
    {
        $perPage = request('per_page', 10);

        $query = LeavePermissions::with('employee')->orderBy('id', 'DESC');

        // نطاقُ الموظفين — قبل التصفيح: بعده يصير المتغيّرُ صفحةً لا استعلاماً.
        $this->scopeToEmployees($query);

        $leavePermissions = $query->paginate($perPage);

        $departments = Department::all();
        $branches = Branch::all();
        $job_titles = JopTitle::all();

        return view('hr::attendance.leave-permissions.index',
            compact('leavePermissions', 'departments', 'branches', 'job_titles'));
    }

    // أضف هذه الدالة للتعامل مع طلبات AJAX
    public function getLeavePermissionsAjax(Request $request)
    {
        try {
            $query = LeavePermissions::with(['employee', 'employee.department', 'employee.branch', 'employee.job_title'])
                ->orderBy('id', 'DESC');

            /*
                نطاقُ الموظفين هنا كما في `index()`.

                كان مسارُ التصفية بـAJAX يُغفله وحدَه — والشاشةُ تُصفّي عند
                أوّل ضغطةٍ على أيّ حقل. فمن يفتح الصفحة يرى موظفي نطاقه،
                فإذا كتب حرفاً في البحث رأى أذونَ المنشأة كلِّها.
            */
            $this->scopeToEmployees($query);

            // تطبيق الفلاتر
            if ($request->keywords) {
                $query->whereHas('employee', function ($q) use ($request) {
                    // `full_name` مُعرَّفٌ في الموديل كـ accessor (getFullNameAttribute)
                    // لا كعمودٍ حقيقي، فيسقط أيُّ استعلامٍ يطلبه بـ«العمود غير موجود».
                    // البحثُ الفعليّ بالاسم يكون عبر `first_name` — تحقّقتُ منه حيّاً.
                    $q->where('first_name', 'like', '%'.$request->keywords.'%')
                        ->orWhere('employee_code', 'like', '%'.$request->keywords.'%');
                });
            }

            if ($request->from_date) {
                $query->where('start_date', '>=', $request->from_date);
            }

            if ($request->to_date) {
                $query->where('end_date', '<=', $request->to_date);
            }

            // نوعُ الإجازة (اعتيادية/عرضية) — `type`
            if ($request->filled('status')) {
                $query->where('type', (int) $request->status);
            }

            /*
                نوعُ الإذن (وصولٌ متأخر / انصرافٌ مبكر) — `leave_type`.

                وهو المُرشِّحُ الذي يُفتَح لأجله هذا السجلّ: الإذنُ يرفع
                عقوبةَ أحدِ الطرفين لا كليهما، فمراجعةُ «من له إذنُ تأخيرٍ
                هذا الشهر» سؤالٌ مختلفٌ عن «من له إذنُ انصرافٍ مبكر». ولم
                يكن في الشاشة سبيلٌ إليه.
            */
            if ($request->filled('leave_type')) {
                $query->where('leave_type', (int) $request->leave_type);
            }

            /*
                لا مُرشِّحَ لـ`approval_status`: لا عمودَ بهذا الاسم في
                `leave_permissions` ولا مسارَ اعتمادٍ للأذون أصلاً — تُنشأ
                معتمَدةً. وكان الشرطُ يُسقط الاستعلامَ بـ«لا عمودَ بهذا
                الاسم»، فتردّ الشاشةُ 500 على كلِّ اختيارٍ من قائمةٍ
                معروضةٍ بثلاثة خيارات.
            */

            if ($request->department) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department);
                });
            }

            if ($request->branch) {
                $query->whereHas('employee', function ($q) use ($request) {
                    BranchTree::apply($q, $request->branch);
                });
            }

            if ($request->job_title) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('job_title_id', $request->job_title);
                });
            }

            // عددُ السجلّات من مُنتقي الجدول — ضمن القيم المعروضة فيه فقط
            $perPage = (int) $request->input('per_page', 10);
            $leavePermissions = $query->paginate(in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10);

            $html = view('hr::attendance.leave-permissions.partials.table_rows', compact('leavePermissions'))->render();

            return response()->json([
                'success' => true,
                'html' => $html,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.LeavePermissions', 'getLeavePermissionsAjax');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {
        $employees = $this->scopedEmployeeOptions([]);

        return view('hr::attendance.leave-permissions.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $allowBackdated = AttendanceSetting::current()->allow_backdated_requests;
        $startDateRule = $allowBackdated
            ? 'required|date'
            : 'required|date|after_or_equal:today';

        // التحقق من صحة البيانات
        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => $startDateRule,
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|in:1,2', // قيم رقمية لنوع الإجازة
            'leave_type' => 'required|in:1,2', // قيم رقمية لنوع الإذن
            'submission_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'attachments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        $this->authorizeEmployeeScope($validatedData['employee_id']);

        try {
            // التحقق من عدم وجود تداخل في التواريخ للموظف نفسه
            $existingLeave = LeavePermissions::where('employee_id', $validatedData['employee_id'])
                ->where(function ($query) use ($validatedData) {
                    $query
                        ->whereBetween('start_date', [$validatedData['start_date'], $validatedData['end_date']])
                        ->orWhereBetween('end_date', [$validatedData['start_date'], $validatedData['end_date']])
                        ->orWhere(function ($q) use ($validatedData) {
                            $q->where('start_date', '<=', $validatedData['start_date'])->where('end_date', '>=', $validatedData['end_date']);
                        });
                })
                ->exists();

            if ($existingLeave) {
                return back()
                    ->withInput()
                    ->withErrors(['date_conflict' => 'يوجد تداخل في التواريخ مع إذن إجازة آخر لنفس الموظف']);
            }

            // إنشاء إذن الإجازة
            $leavePermission = new LeavePermissions;
            $leavePermission->employee_id = $validatedData['employee_id'];
            $leavePermission->start_date = $validatedData['start_date'];
            $leavePermission->end_date = $validatedData['end_date'];
            $leavePermission->type = $validatedData['type'];
            $leavePermission->leave_type = $validatedData['leave_type'];
            $leavePermission->submission_date = $validatedData['submission_date'] ?? now();
            $leavePermission->notes = $validatedData['notes'];

            // رفع المرفقات
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                $fileName = time().'_'.$file->getClientOriginalName();
                $filePath = $file->storeAs('leave_permissions', $fileName, 'public');
                $leavePermission->attachments = $filePath;
            }

            $leavePermission->save();

            // إنشاء السجل
            ModelsLog::create([
                'type' => 'leave_permissions',
                'type_id' => $leavePermission->id,
                'type_log' => 'create',
                'description' => 'تم إنشاء إذن إجازة جديد للموظف: '.$leavePermission->employee->full_name,
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('leave_permissions.index')->with('success', 'تم إنشاء إذن الإجازة بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.LeavePermissions', 'store');

            return back()
                ->withInput()
                ->withErrors(['error' => 'حدث خطأ أثناء حفظ البيانات: '.$e->getMessage()]);
        }
    }

    /**
     * Upload image helper method
     */
    public function edit($id)
    {
        $leavePermission = LeavePermissions::findOrFail($id);
        $this->authorizeEmployeeScope($leavePermission->employee_id);
        $employees = $this->scopedEmployeeOptions([]);

        return view('hr::attendance.leave-permissions.edit', compact('leavePermission', 'employees'));
    }

    public function update(Request $request, $id)
    {
        // كان التوقيعُ `LeavePermissions $leavePermission` والمسارُ `{id}` — فالربطُ
        // الضمنيّ (بالاسم) لا يقع ويصل نموذجٌ فارغ، فيُنشأ إذنٌ جديد بدل تعديل القائم.
        $leavePermission = LeavePermissions::findOrFail($id);

        $allowBackdated = AttendanceSetting::current()->allow_backdated_requests;
        $startDateRule = $allowBackdated
            ? 'required|date'
            : 'required|date|after_or_equal:today';

        // التحقق من صحة البيانات
        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => $startDateRule,
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|in:1,2', // قيم رقمية
            'leave_type' => 'required|in:1,2',  // قيم نصية
            'submission_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'attachments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        // صاحبُ الإذن الحاليّ والموظفُ الجديد كلاهما داخل النطاق
        $this->authorizeEmployeeScope($leavePermission->employee_id);
        $this->authorizeEmployeeScope($validatedData['employee_id']);

        try {
            // التحقق من عدم وجود تداخل في التواريخ للموظف نفسه (استثناء السجل الحالي)
            $existingLeave = LeavePermissions::where('employee_id', $validatedData['employee_id'])
                ->where('id', '!=', $leavePermission->id)
                ->where(function ($query) use ($validatedData) {
                    $query
                        ->whereBetween('start_date', [$validatedData['start_date'], $validatedData['end_date']])
                        ->orWhereBetween('end_date', [$validatedData['start_date'], $validatedData['end_date']])
                        ->orWhere(function ($q) use ($validatedData) {
                            $q->where('start_date', '<=', $validatedData['start_date'])->where('end_date', '>=', $validatedData['end_date']);
                        });
                })
                ->exists();

            if ($existingLeave) {
                return back()
                    ->withInput()
                    ->withErrors(['date_conflict' => 'يوجد تداخل في التواريخ مع إذن إجازة آخر لنفس الموظف']);
            }

            // تحديث البيانات
            $leavePermission->employee_id = $validatedData['employee_id'];
            $leavePermission->start_date = $validatedData['start_date'];
            $leavePermission->end_date = $validatedData['end_date'];
            $leavePermission->type = $validatedData['type']; // قيمة رقمية
            $leavePermission->leave_type = $validatedData['leave_type']; // قيمة نصية
            $leavePermission->submission_date = $validatedData['submission_date'] ?? now();
            $leavePermission->notes = $validatedData['notes'];

            // تحديث المرفقات إذا تم رفع ملف جديد
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                $fileName = time().'_'.$file->getClientOriginalName();
                $filePath = $file->storeAs('leave_permissions', $fileName, 'public');
                $leavePermission->attachments = $filePath;
            }

            $leavePermission->save();

            // إنشاء السجل
            ModelsLog::create([
                'type' => 'leave_permissions',
                'type_id' => $leavePermission->id,
                'type_log' => 'update',
                'description' => 'تم تحديث إذن إجازة للموظف: '.$leavePermission->employee->full_name,
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('leave_permissions.index')->with('success', 'تم تحديث إذن الإجازة بنجاح');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.LeavePermissions', 'update');

            return back()
                ->withInput()
                ->withErrors(['error' => 'حدث خطأ أثناء تحديث البيانات: '.$e->getMessage()]);
        }
    }

    public function show($id)
    {
        $leavePermission = LeavePermissions::findOrFail($id);
        $this->authorizeEmployeeScope($leavePermission->employee_id);
        $logs = ModelsLog::where('type', 'leave_permissions')
            ->where('type_id', $leavePermission->id)
            ->whereHas('LeavePermissions') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('hr::attendance.leave-permissions.show', compact('leavePermission', 'logs'));
    }

    public function delete($id)
    {
        $leavePermission = LeavePermissions::findOrFail($id);
        $this->authorizeEmployeeScope($leavePermission->employee_id);

        ModelsLog::create([
            'type' => 'atendes_log',
            'type_id' => $leavePermission->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم حذف اذن  اجازة',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);
        if ($leavePermission->attachments != null) {
            unlink('assets/uploads/leave_permissions/'.$leavePermission->attachments);
        }
        $leavePermission->delete();

        return redirect()
            ->route('leave_permissions.index')
            ->with(['success' => 'تم حذف اذن الاجازة بنجاح']);
    }

    // Helpper
    public function uploadImage($folder, $image)
    {
        $fileExtension = $image->getClientOriginalExtension();
        $fileName = time().rand(1, 99).'.'.$fileExtension;
        $image->move($folder, $fileName);

        return $fileName;
    } // end of uploadImage
}
