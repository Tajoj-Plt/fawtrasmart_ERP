<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * «قوالب الرواتب» — أُلغيت (2026-09-24) بقرار المستخدم.
 *
 * كان القالبُ مجموعةَ بنودٍ جاهزةً تُنسخ إلى العقد عند اختياره. فصار للعقد
 * مصدران لبنوده، والطباعةُ لا علاقةَ لها به (تمرّ بـ«قوالب المستندات»).
 * بنودُ الراتب تُضاف الآن مباشرةً في العقد وحده.
 *
 * كلُّ مسارات `SalaryTemplates.*` باقيةٌ بأسمائها كي لا ينكسر رابطٌ محفوظ،
 * وكلُّها تُحوِّل إلى «بنود الرواتب» برسالة.
 *
 * **ما بقي كما هو ولم يُحذف**: جدولا `salary_template` وصفوفُه، وصفوفُ
 * `salary_items` ذاتُ `salary_template_id`، وعمودُ `contracts.salary_temp_id`
 * وقيمُه، والموديل `App\Models\SalaryTemplate`. لا يقرأ شيءٌ منها في المسير أو
 * القسيمة أو نهاية الخدمة — العقودُ القائمة تحمل نُسخَ بنودها الخاصّة
 * (`salary_items.contracts_id`). وصلاحياتُ `salary_templates.*` حُذفت بترحيل
 * `2026_09_24_230000_retire_salary_templates_permissions`.
 */
class SalaryTemplatesController extends Controller
{
    public function __construct()
    {
        // التحويلُ يُحرس بصلاحية وجهته: لا يفتح أحدٌ «بنود الرواتب» من بابٍ قديم
        $this->middleware('permission.role:salary_items.index');
    }

    public function retired(): RedirectResponse
    {
        return redirect()
            ->route('SalaryItems.index')
            ->with('success', 'قوالب الرواتب أُلغيت — بنود الراتب تُضاف مباشرة في العقد');
    }
}
