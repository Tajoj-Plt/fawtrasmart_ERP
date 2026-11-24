<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\PayrollSettings;
use Illuminate\Http\Request;
use Modules\HR\Support\AdvanceApprovers;

/**
 * إعدادات السلف — من يُختار «موظفاً معتمداً»، وهل يُصرَف من بوب الإشعار.
 *
 * لم يكن في إعدادات الموارد البشرية ما يحدّد معتمِدي السلف، فكانت شاشةُ
 * الطلب تعرض مستخدمي النظام كلَّهم. والإعدادُ هنا أقسامٌ يُختار من موظفيها
 * (مع شرط صلاحية الاعتماد دائماً) — انظر `AdvanceApprovers`.
 *
 * و`advance_popup_disburse`: بوبُ الإشعار يعتمد ويصرف في خطوةٍ واحدة من
 * خزينةٍ يختارها المعتمِد. ومنشأةٌ تفصل القرارَ عن المال تُطفئه فيعود البوبُ
 * إلى الاعتماد وحده — وصلاحيةُ `loans.disburse` شرطٌ فوقه في الحالين.
 */
class AdvanceSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:payroll_settings.manage');
    }

    public function edit()
    {
        $departments = Department::orderBy('name')->get(['id', 'name', 'status']);
        $selected = AdvanceApprovers::departmentIds();
        $approvers = AdvanceApprovers::for();

        // `1` افتراضاً كما في الهجرة — صفٌّ قديمٌ قبلها يقرأ الافتراضَ نفسَه
        $popupDisburse = (bool) PayrollSettings::value('advance_popup_disburse', 1);

        return view('hr::salaries.sitting.advances', compact('departments', 'selected', 'approvers', 'popupDisburse'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'approver_department_ids' => 'nullable|array',
            'approver_department_ids.*' => 'integer|exists:departments,id',
            'popup_disburse' => 'nullable|boolean',
        ], [
            'approver_department_ids.*.exists' => 'أحد الأقسام المختارة غير موجود',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['approver_department_ids'] ?? [])));

        PayrollSettings::put([
            AdvanceApprovers::SETTING => $ids === [] ? null : json_encode($ids),
            // الخانةُ المطفأة تصل `0` من الحقل المخفيّ الذي يسبقها في النموذج
            'advance_popup_disburse' => $request->boolean('popup_disburse'),
        ]);

        return redirect()->route('SalarySittings.advances')->with('success', 'حُفظت إعدادات السلف.');
    }
}
