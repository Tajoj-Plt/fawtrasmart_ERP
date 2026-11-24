<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;

/**
 * «مسير الصرفات» — **أُلغي**. روابطُه القديمة تُحوَّل إلى «سجلات الصرف».
 *
 * **ما كان**: صرفاتُ `payroll_runs` لا تُصرف إلا بعد تجميعها في
 * `payroll_batches` واعتماد الدفعة — اعتمادٌ ثانٍ فوق اعتماد مسير الرواتب
 * للموظفين أنفسهم كلَّ شهر.
 *
 * **ما صار**: اعتمادُ **مسير الرواتب** هو الاعتمادُ الوحيد
 * (`PayrollRun::disbursementApprovalBlockReason()`)، والصرفُ من شاشة المسير
 * («صرف المستحق») أو من سجل الصرف. الجدولُ وعمودُ `payroll_batch_id` باقيان
 * بياناتٍ تاريخية لا تُحذف ولا تُسأل عند الصرف.
 *
 * المساراتُ باقيةٌ بأسمائها كي لا تنكسر إشارةٌ محفوظة أو رابطٌ قديم.
 */
class PayrollBatchController extends Controller
{
    private const NOTICE = 'أُلغي «مسير الصرفات»: اعتمادُ مسير الرواتب يكفي للصرف، وتُصرف الرواتب المستحقّة من شاشة المسير أو من سجلات الصرف.';

    public function __construct()
    {
        $this->middleware('permission.role:disbursements.index|payroll.approve');
    }

    public function index()
    {
        return $this->retired();
    }

    public function create()
    {
        return $this->retired();
    }

    public function store()
    {
        return $this->retired();
    }

    public function show($id)
    {
        return $this->retired();
    }

    public function approve($id)
    {
        return $this->retired();
    }

    private function retired()
    {
        return redirect()->route('payroll-runs.index')->with('success', self::NOTICE);
    }
}
