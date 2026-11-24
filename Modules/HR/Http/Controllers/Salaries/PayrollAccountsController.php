<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Deduction;
use App\Models\TargetSetting;
use App\Support\PayrollAccounts;

/**
 * «حسابات الرواتب» — نظرةٌ للقراءة على كلّ حسابٍ تقيّد عليه الرواتب.
 *
 * كانت الشاشة تحمل منتقيَ حسابٍ لكلّ بند وتكتب في `payroll_settings`
 * و`accounting_settings` معاً. صار المصدرُ الواحدُ «إعدادات الحسابات
 * المحاسبية» (مجموعة «الرواتب والموظفين» — انظر `PayrollAccounts`)،
 * وهذه الشاشة تعرض الربطَ مجمَّعاً بأقسامه مع رابطٍ إلى هناك: يعرفها
 * المستخدمون، فبقيت — لكن بلا حقلٍ يُحفظ.
 *
 * وحسابُ العمولة يبقى في «إعدادات أهداف المبيعات» (`target_settings`) —
 * يُعرض هنا للقراءة مع رابطه.
 *
 * الصلاحية القائمة `payroll_settings.manage`.
 */
class PayrollAccountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:payroll_settings.manage');
    }

    public function edit()
    {
        /*
            حساباتُ أنواع الخصميات تُعرض **تحت** «خصميات الموظفين» — كما
            تُفتح في الشجرة (`DeductionTypeAccounts`) — باسم النوع وحده.
        */
        $deductionTypes = [];
        foreach (Deduction::types() as $type => $label) {
            $deductionTypes[PayrollAccounts::deductionTypeKey($type)] = $label;
        }

        $sections = [];
        $linked = 0;
        $total = 0;
        foreach (PayrollAccounts::sections() as $id => $section) {
            $rows = PayrollAccounts::rows(array_keys($section['keys']));
            foreach ($rows as $key => $row) {
                $rows[$key]['child'] = isset($deductionTypes[$key]);
                $rows[$key]['short'] = $deductionTypes[$key] ?? $row['label'];
            }

            $sectionLinked = collect($rows)->where('state', 'mapped')->count();
            $sections[$id] = $section + [
                'rows' => $rows,
                'linked' => $sectionLinked,
                'unlinked' => count($rows) - $sectionLinked,
            ];
            $linked += $sectionLinked;
            $total += count($rows);
        }

        $commissionSetting = class_exists(TargetSetting::class)
            ? TargetSetting::for(TargetSetting::TYPE_SALES)
            : null;
        $commissionAccount = $commissionSetting?->commission_account_id
            ? Account::find($commissionSetting->commission_account_id)
            : null;

        $total++;
        if ($commissionAccount) {
            $linked++;
        }

        return view('hr::salaries.sitting.accounts', [
            'sections' => $sections,
            'commissionAccount' => $commissionAccount,
            'summary' => ['linked' => $linked, 'unlinked' => $total - $linked, 'total' => $total],
        ]);
    }

    /**
     * لم يعد في الشاشة ما يُحفظ — الربطُ من «إعدادات الحسابات المحاسبية».
     * يبقى المسارُ كي لا ينكسر نموذجٌ قديمٌ مفتوح في متصفّح، ولا يكتب شيئاً.
     */
    public function update()
    {
        return redirect()->route('SalarySittings.accounts')
            ->with('warning', 'حسابات الرواتب تُربط من '.PayrollAccounts::SCREEN.' — لم يُحفظ شيءٌ من هذه الشاشة.');
    }
}
