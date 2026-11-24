<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * «كتالوج البدلات والاستقطاعات» — متقاعد (2026-09-24).
 *
 * كان يعرّف أنواعاً معيارية (بدل سكن، مواصلات…) إلى جانب «بنود الرواتب» التي
 * تعرّف الشيءَ نفسَه، ليضيف علمَين: الخضوعَ للتأمينات ودخولَ أجر نهاية الخدمة.
 * فكانت للبند الواحد شاشتان، والتصنيفُ لا يبلغ القسيمةَ أصلاً (النسخُ في العقد
 * والقسيمة لا تحمل `component_type_id`).
 *
 * العلَمان صارا على البند نفسِه (`salary_items.is_insurable` و`in_eos_wage`،
 * ترحيل `2026_09_24_100000_move_salary_catalog_flags_onto_salary_items`)،
 * وكلُّ مسارات الكتالوج تُحوِّل إلى «بنود الرواتب» بأسمائها القديمة.
 *
 * **ما بقي كما هو ولم يُحذف**: جدولُ `salary_component_types` وصفوفُه،
 * وعمودُ `salary_items.component_type_id` وقيمُه، ومراجعُ الأنواع المزروعة في
 * إعدادات الأجر الإضافي ونهاية الخدمة والتأمينات (تسمّي البنودَ المولَّدة)،
 * وتجميعُ تقرير البدلات بالاسم. وقوالبُ `component_types/*` باقيةٌ في مكانها
 * بلا مسارٍ يعرضها.
 */
class SalaryComponentTypeController extends Controller
{
    public function __construct()
    {
        // التحويلُ نفسُه يُحرس بصلاحية وجهته: لا يفتح أحدٌ «بنود الرواتب» من بابٍ قديم
        $this->middleware('permission.role:salary_items.index');
    }

    public function retired(): RedirectResponse
    {
        return redirect()
            ->route('SalaryItems.index')
            ->with('success', 'كتالوج البدلات والاستقطاعات دُمج في «بنود الرواتب»: الخضوعُ للتأمينات وأجرُ نهاية الخدمة يُضبطان الآن من البند نفسِه.');
    }
}
