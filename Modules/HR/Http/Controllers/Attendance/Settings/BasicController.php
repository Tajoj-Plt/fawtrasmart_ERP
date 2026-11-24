<?php

namespace Modules\HR\Http\Controllers\Attendance\Settings;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use Illuminate\Http\Request;

class BasicController extends Controller
{
    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        $this->middleware('permission.role:attendance_settings.manage', ['only' => ['index', 'update']]);
    }

    public function index()
    {
        $employees = Employee::select('id', 'first_name', 'middle_name')->get();

        $attendanceSettings = AttendanceSetting::firstOrNew();

        return view('hr::attendance.settings.basic.index', compact('employees', 'attendanceSettings'));
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'start_month' => 'required|integer|min:1|max:12',
            'start_day' => 'required|integer|min:1|max:31',
            'employee_id' => 'nullable|array',
            'employee_id.*' => 'integer|exists:employees,id',
        ]);

        $attendanceSettings = AttendanceSetting::updateOrCreate(
            [],
            [
                'start_month' => $validatedData['start_month'],
                'start_day' => $validatedData['start_day'],
                'allow_second_shift' => $request->boolean('allow_second_shift'),
                'allow_backdated_requests' => $request->boolean('allow_backdated_requests'),
                'direct_manager_approval' => $request->boolean('direct_manager_approval'),
                'department_manager_approval' => $request->boolean('department_manager_approval'),
                'employees_approval' => $request->boolean('employees_approval'),
            ]
        );

        /*
            قائمةٌ فُرِّغت تُحفظ فارغة: القائمةُ المتعددة الفارغة لا تُرسل شيئاً،
            فكان الشرطُ `has()` يترك الموظفين القدامى معتمِدين إلى الأبد.
        */
        $attendanceSettings->employees()->sync($validatedData['employee_id'] ?? []);

        return redirect()->back()->with(['success' => 'تم حفظ الإعدادات بنجاح .']);
    }
}
