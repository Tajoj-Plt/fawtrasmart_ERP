<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\SalaryAdvanceRequest;
use App\Models\SalesTargetSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\Concerns\PrintsPayrollDocuments;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * طباعةُ سندَي الموارد البشرية بقوالب «قوالب المستندات»:
 *
 *   • «سند سلفة» (`salary_advance`) — صفٌّ في `salary_advance_requests`.
 *     لا يطبع السلفَ القديمة (`salary_advances`): معرّفاتُ الجدولين
 *     متداخلة، فمعرّفُ سلفةٍ قديمة هنا يطبع طلباً آخر.
 *   • «سند صرف عمولة» (`commission_payment`) — تسويةٌ في `sales_target_settlements`.
 *
 * الصلاحيةُ على المسار وهنا: سندُ السلفة `loans.show`، وسندُ العمولة
 * `commissions.print` — وعلى من يحدّده نطاقُ الرؤية وحده.
 * والنطاقُ على كلّ سند: رقمٌ في العنوان لا يطبع سندَ موظفٍ خارج النطاق. وبلا قالبٍ فعّال ⇒ رجوعٌ
 * برسالةٍ تدلّ على شاشة القوالب — لا مطبوعةٌ مدمجة.
 */
class HrVoucherPrintController extends Controller
{
    use PrintsPayrollDocuments;
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:loans.show', ['only' => ['advance']]);
        $this->middleware('permission.role:commissions.print', ['only' => ['commission']]);
    }

    public function advance(Request $request, $id)
    {
        $advance = SalaryAdvanceRequest::findOrFail($id);

        // `loans.show` داخل النطاق — صاحبُ `data_scope_own` يطبع سندَه هو وحده
        $this->authorizeEmployeeScope($advance->employee_id);

        $back = hasPermission('loans.index') && Route::has('hr.advance-approvals.show')
            ? route('hr.advance-approvals.show', $advance->id)
            : (Route::has('hr.advance-requests.show') ? route('hr.advance-requests.show', $advance->id) : url()->previous());

        $html = $this->renderPayrollTemplate('salary_advance', $advance->id, $request->input('template'));

        if ($html === null) {
            return redirect()->to($back)
                ->with('error', 'لا يوجد قالبُ طباعةٍ فعّالٌ لسند السلفة. فعّل قالباً من الإعدادات ← قوالب المستندات.');
        }

        return $this->printResponse($html, 'سند سلفة #'.$advance->id, $back);
    }

    public function commission(Request $request, $id)
    {
        $commission = SalesTargetSettlement::findOrFail($id);
        $this->authorizeEmployeeScope($commission->employee_id);

        $back = Route::has('hr.commissions-report.show')
            ? route('hr.commissions-report.show', $commission->id)
            : url()->previous();

        $html = $this->renderPayrollTemplate('commission_payment', $commission->id, $request->input('template'));

        if ($html === null) {
            return redirect()->to($back)
                ->with('error', 'لا يوجد قالبُ طباعةٍ فعّالٌ لسند صرف العمولة. فعّل قالباً من الإعدادات ← قوالب المستندات.');
        }

        return $this->printResponse($html, 'سند صرف عمولة #'.$commission->id, $back);
    }
}
