<?php

namespace Modules\HR\Http\Controllers\Attendance\Settings;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\Log as ModelsLog;
use Illuminate\Http\Request;

class LeaveTypesController extends Controller
{
    public function __construct()
    {
        // لكلّ فعلٍ صلاحيتُه `leave_types.*` — لا صلاحيةَ «إدارة» واحدة تفتح الشاشة كلّها
        $this->middleware('permission.role:leave_types.index', ['only' => ['index']]);
        $this->middleware('permission.role:leave_types.show', ['only' => ['show']]);
        $this->middleware('permission.role:leave_types.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:leave_types.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:leave_types.destroy', ['only' => ['delete']]);
    }

    public function index()
    {
        $perPage = request('per_page', 10);

        $query = LeaveType::query()->orderBy('id', 'DESC');

        // البحث
        if (request()->has('keywords') && ! empty(request('keywords'))) {
            $keywords = request('keywords');
            $query->where(function ($q) use ($keywords) {
                $q->where('name', 'like', '%'.$keywords.'%')
                    ->orWhere('id', 'like', '%'.$keywords.'%');
            });
        }

        $leave_types = $query->paginate($perPage);

        return view('hr::attendance.settings.leave_types.index', compact('leave_types'));
    }

    public function create()
    {
        return view('hr::attendance.settings.leave_types.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $leaveType = new LeaveType;

        if ($request->has('requires_approval')) {
            $leaveType->requires_approval = 1;
        }

        if ($request->has('replace_weekends')) {
            $leaveType->replace_weekends = 1;
        }

        $leaveType->name = $request->name;
        $leaveType->color = $request->color;
        $leaveType->max_days_per_year = $request->max_days_per_year;
        $leaveType->max_consecutive_days = $request->max_consecutive_days;
        $leaveType->description = $request->description;
        $leaveType->applicable_after = $request->applicable_after;

        $leaveType->save();

        ModelsLog::create([
            'type' => 'leave_Type',
            'type_id' => $leaveType->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم اضافة نوع الاجازة',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);

        return redirect()->route('leave_types.index')->with(['success' => 'تمت إضافة نوع الاجازة بنجاح']);
    }

    public function edit($id)
    {
        $leaveType = LeaveType::findOrFail($id);

        return view('hr::attendance.settings.leave_types.edit', compact('leaveType'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $leaveType = LeaveType::findOrFail($id);

        if ($request->has('requires_approval')) {
            $leaveType->requires_approval = 1;
        }

        if ($request->has('replace_weekends')) {
            $leaveType->replace_weekends = 1;
        }

        $leaveType->name = $request->name;
        $leaveType->color = $request->color;
        $leaveType->max_days_per_year = $request->max_days_per_year;
        $leaveType->max_consecutive_days = $request->max_consecutive_days;
        $leaveType->description = $request->description;
        $leaveType->applicable_after = $request->applicable_after;

        $leaveType->update();

        ModelsLog::create([
            'type' => 'leave_Type',
            'type_id' => $leaveType->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم تحديث نوع الاجازة',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);

        return redirect()->route('leave_types.show', $id)->with(['success' => 'تم تعديل نوع الاجازة بنجاح']);
    }

    public function show($id)
    {
        $leaveType = LeaveType::findOrFail($id);
        $logs = ModelsLog::where('type', 'leave_Type')
            ->where('type_id', $id)
            ->with(['user.branch'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('hr::attendance.settings.leave_types.show', compact('leaveType', 'logs'));
    }

    public function delete($id)
    {
        $leaveType = LeaveType::findOrFail($id);

        /*
            نوعٌ عليه طلباتٌ أو أرصدة لا يُحذف: لا مفتاحَ أجنبياً يمنعه،
            فكانت طلباتُه تبقى تشير إلى نوعٍ لا وجود له، وتسقط شاشةُ الطلبات.
        */
        $requests = \App\Models\LeaveRequest::where('leave_type_id', $leaveType->id)->count();
        $balances = \App\Models\EmployeeLeaveBalance::where('leave_type_id', $leaveType->id)->count();

        if ($requests > 0 || $balances > 0) {
            return redirect()->route('leave_types.index')->with(['error' => sprintf(
                'لا يمكن حذف «%s» — عليه %d طلب إجازة و%d رصيد موظف. عطّله بدل حذفه، أو انقل طلباته إلى نوعٍ آخر.',
                $leaveType->name, $requests, $balances
            )]);
        }

        $leaveType->delete();

        return redirect()->route('leave_types.index')->with(['success' => 'تم حذف نوع الاجازة بنجاح']);
    }
}
