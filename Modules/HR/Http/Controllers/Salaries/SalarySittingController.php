<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Support\PayrollSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SalarySittingController extends Controller
{
    public function __construct()
    {
        /*
            شاشةُ البلاطات تُفتح لمن يملك أيَّ إعدادٍ تعرضه بلاطةٌ فيها —
            وكلُّ بلاطةٍ تُظهرها صلاحيتُها وحدها.
        */
        $this->middleware('permission.role:payroll_settings.manage|contracts.notifications|salary_items.index|overtime.settings|insurance.settings|insurance.index|bank_files.index|eos.settings', ['only' => ['index']]);

        $this->middleware('permission.role:payroll_settings.manage', ['only' => [
            'general', 'updateGeneral', 'commissions', 'updateCommissions',
        ]]);

        /*
            إشعاراتُ العقود لها صلاحيتُها في الكتالوج (`contracts.notifications`):
            من مُنحها يضبط مدّة الإنذار دون أن تلزمه إعداداتُ الرواتب كلُّها.
        */
        $this->middleware('permission.role:contracts.notifications|payroll_settings.manage', ['only' => [
            'notifications', 'updateNotifications',
        ]]);
    }

    public function index()
    {
        return view('hr::salaries.sitting.index');
    }

    /**
     * Display the general payroll settings form.
     *
     * This view allows administrators to toggle emailing approved salary slips and payroll automation
     * (print templates live in Document Templates). Values are pulled from the payroll_settings table if present or
     * defaulted when no record exists.
     */
    public function general()
    {
        return view('hr::salaries.sitting.general', [
            'payrollSettings' => PayrollSettings::toArray(),
        ]);
    }

    /**
     * Persist the submitted general settings to the database.
     *
     * @return RedirectResponse
     */
    public function updateGeneral(Request $request)
    {
        // `default_template` لم يعد يُكتب: قالبُ الطباعة مصدرُه «قوالب المستندات»
        // وحدها (العمود يبقى في الجدول بلا قارئ).
        $validated = $request->validate([
            // يقرؤه `SalarySlip::getEffectivePaymentDay()` — كان مقروءاً بلا شاشةٍ تضبطه
            'default_payment_day' => 'required|integer|min:1|max:28',
        ], [], [
            'default_payment_day' => 'يوم الصرف الافتراضي',
        ]);

        // مفاتيحُ هذه الشاشة وحدها — لا يمسّ الحفظُ مفاتيحَ الشاشات الأخرى في الصفّ نفسه
        PayrollSettings::put([
            'default_payment_day' => (int) $validated['default_payment_day'],
            // يقرؤه `PayrollDisbursementService::emailPayslip()` بعد نجاح كلّ صرف
            'send_salary_slips' => $request->boolean('send_salary_slips'),
            // أتمتة الرواتب — قابلة للتعطيل من نفس النموذج
            'auto_generate_payroll_runs' => $request->boolean('auto_generate_payroll_runs'),
            'auto_disburse_payroll_runs' => $request->boolean('auto_disburse_payroll_runs'),
            // ضابطُ التصريح بخروج النقد — إطفاؤه قرارٌ صريحٌ لا افتراض
            'require_disbursement_approval' => $request->boolean('require_disbursement_approval'),
            'require_slip_maker_checker' => $request->boolean('require_slip_maker_checker'),
        ]);

        return redirect()->back()->with('success', 'تم تحديث الاعدادات العامة بنجاح');
    }

    /**
     * Display the contract notifications settings form.
     *
     * Allows admins to specify the number of days prior to contract end to send a notification.
     */
    public function notifications()
    {
        return view('hr::salaries.sitting.notifications', [
            'payrollSettings' => PayrollSettings::toArray(),
        ]);
    }

    /**
     * Persist contract notification settings.
     *
     * @return RedirectResponse
     */
    public function updateNotifications(Request $request)
    {
        $request->validate([
            // الصفرُ كان مقبولاً ويعني عند القارئ «30» — فالحدُّ الأدنى يوم، والفارغ = 30
            'contract_end_notice_period' => 'nullable|integer|min:1|max:365',
            'document_expiry_notice_period' => 'nullable|integer|min:1|max:365',
        ], [], [
            'contract_end_notice_period' => 'إنذار انتهاء العقد',
            'document_expiry_notice_period' => 'إنذار انتهاء الوثائق',
        ]);
        PayrollSettings::put([
            'contract_end_notice_period' => $request->input('contract_end_notice_period'),
            // مدّة إنذار وثائق الموظفين — يقرؤها `hr:document-expiry-alerts`
            'document_expiry_notice_period' => $request->input('document_expiry_notice_period'),
        ]);

        return redirect()->back()->with('success', 'تم تحديث إشعارات العقد بنجاح');
    }

    /**
     * Display the commission payout settings form.
     *
     * Allows admins to enable or disable automatically paying approved sales periods.
     */
    public function commissions()
    {
        /*
            العمولاتُ تُحسب وتُصرف من أهداف المبيعات وحدها
            (`TargetSettlementService`)، فالشاشةُ تعرض إعداداتِها هناك قراءةً
            — لا نظامَ عمولاتٍ ثانياً في الموارد البشرية. ومفتاحُ
            `auto_pay_approved_sales_periods` الذي كانت تحفظه لا يقرؤه شيء.
        */
        $setting = \App\Models\TargetSetting::for(\App\Models\TargetSetting::TYPE_SALES);

        return view('hr::salaries.sitting.commissions', [
            'payrollSettings' => PayrollSettings::toArray(),
            'targetSetting' => $setting,
            'commissionAccount' => $setting->commission_account_id ? \App\Models\Account::find($setting->commission_account_id) : null,
            'commissionTreasury' => $setting->default_commission_treasury_id ? \App\Models\Account::find($setting->default_commission_treasury_id) : null,
        ]);
    }

    /**
     * Persist commission settings.
     *
     * @return RedirectResponse
     */
    public function updateCommissions(Request $request)
    {
        /*
            الشاشةُ للقراءة: إعداداتُ العمولات تُحرَّر في «إعدادات الأهداف».
            وكان هذا الفعلُ يكتب `auto_pay_approved_sales_periods` الذي لا
            يقرؤه شيء — فلا يُكتب بعد الآن (العمودُ يبقى في الجدول).
        */
        return redirect()->route('SalarySittings.commissions')
            ->with('error', 'إعداداتُ العمولات تُحرَّر من «إعدادات أهداف المبيعات» — هذه الشاشة للعرض.');
    }
}
