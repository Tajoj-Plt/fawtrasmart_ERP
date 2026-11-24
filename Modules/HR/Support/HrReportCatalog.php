<?php

namespace Modules\HR\Support;

use Illuminate\Support\Facades\Route;

/**
 * فهرسُ تقارير الموارد البشرية — مصدرٌ واحدٌ للوحة التقارير ولتبويبات كل تقرير.
 *
 * كانت اللوحةُ ثلاثين بلاطة، وكلُّ عرضٍ من عروض الرواتب صفحةٌ مستقلة بلاطتُها
 * (حسب الشهر، حسب القسم، لكل موظف…). صارت ثمانيةَ تقارير: لكلٍّ بلاطةٌ واحدة
 * في اللوحة، وعروضُه تبويباتٌ أعلى صفحته (`hr::reports.partials.rpt-tabs`).
 * المساراتُ القديمة كلُّها باقية — التبويبُ رابطٌ إليها.
 */
final class HrReportCatalog
{
    /**
     * @return array<int, array{key: string, title: string, hint: string, icon: string, tabs: array<int, array{route: string, title: string, permission: string, extra_permission?: string}>}>
     */
    public static function groups(): array
    {
        $att = 'hr_reports.attendance';
        $pay = 'hr_reports.payroll';
        $emp = 'hr_reports.employees';

        return [
            [
                'key' => 'attendance', 'icon' => 'user-clock',
                'title' => 'الحضور والإجازات',
                'hint' => 'الحضور والغياب والتأخير وصافي العمل لكل موظف، وسجلات التسجيل، والإجازات والأرصدة',
                'tabs' => [
                    ['route' => 'hr.reports.attendance.summary', 'title' => 'ملخص الحضور', 'permission' => $att],
                    ['route' => 'hr.reports.attendance.punches', 'title' => 'سجلات الحضور', 'permission' => $att],
                    ['route' => 'hr.reports.leaves', 'title' => 'الإجازات والأذونات', 'permission' => $att],
                ],
            ],
            [
                'key' => 'payroll', 'icon' => 'hand-holding-usd',
                'title' => 'الرواتب المصروفة',
                'hint' => 'ما صُرف من رواتب: لكل دفعة، حسب الشهر، حسب القسم، لكل موظف، والمصروف وغير المصروف',
                'tabs' => [
                    ['route' => 'hr.reports.payments', 'title' => 'صرف الرواتب', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.summary', 'title' => 'كل الرواتب', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.by_period', 'title' => 'حسب الشهر', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.by_department', 'title' => 'حسب القسم', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.net', 'title' => 'لكل موظف', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.status', 'title' => 'المصروف وغير المصروف', 'permission' => $pay],
                ],
            ],
            [
                'key' => 'slips', 'icon' => 'file-signature',
                'title' => 'القسائم والمسيرات',
                'hint' => 'قسيمة راتب كل موظف، ومسيرات الرواتب وموظفوها، وطباعة إيصالات الرواتب',
                'tabs' => [
                    ['route' => 'hr.reports.payroll.slips_register', 'title' => 'قسائم الرواتب', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.sheets', 'title' => 'مسيرات الرواتب', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.payslips', 'title' => 'طباعة الإيصالات', 'permission' => $pay],
                ],
            ],
            [
                'key' => 'items', 'icon' => 'list-ul',
                'title' => 'بنود الرواتب والبدلات',
                'hint' => 'كل بند إضافة واستقطاع في الرواتب المصروفة، والبدلات حسب نوعها',
                'tabs' => [
                    ['route' => 'hr.reports.payroll.components', 'title' => 'بنود الرواتب', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.allowances', 'title' => 'البدلات', 'permission' => $pay],
                ],
            ],
            [
                'key' => 'advances', 'icon' => 'coins',
                'title' => 'السلف والخصميات والعمولات',
                'hint' => 'السلف وما سُدّد منها والمتبقي، والخصميات بأنواعها وحالتها، وعمولات الأهداف',
                'tabs' => [
                    ['route' => 'hr.reports.payroll.advances', 'title' => 'السلف', 'permission' => $pay],
                    ['route' => 'hr.reports.payroll.deductions', 'title' => 'الخصميات', 'permission' => $pay],
                    ['route' => 'hr.commissions-report.index', 'title' => 'العمولات', 'permission' => 'commissions.index'],
                ],
            ],
            [
                'key' => 'accounting', 'icon' => 'balance-scale',
                'title' => 'الرواتب في الحسابات',
                'hint' => 'كشف حساب الموظف من القيود، ومطابقة الرواتب المصروفة بقيودها',
                'tabs' => [
                    ['route' => 'hr.reports.payroll.statement', 'title' => 'كشف حساب الموظف', 'permission' => $pay, 'extra_permission' => 'account.statement.view'],
                    ['route' => 'hr.reports.payroll.reconciliation', 'title' => 'مطابقة الرواتب والقيود', 'permission' => $pay],
                ],
            ],
            [
                'key' => 'employees', 'icon' => 'users',
                'title' => 'الموظفون',
                'hint' => 'دليل الموظفين، وتوزيعهم حسب القسم والمسمى والحالة، والإحصائيات، والهيكل التنظيمي',
                'tabs' => [
                    ['route' => 'hr.reports.employees', 'title' => 'دليل الموظفين', 'permission' => $emp],
                    ['route' => 'hr.reports.employees.by_department', 'title' => 'حسب القسم', 'permission' => $emp],
                    ['route' => 'hr.reports.employees.by_job_title', 'title' => 'حسب المسمى', 'permission' => $emp],
                    ['route' => 'hr.reports.employees.by_status', 'title' => 'حسب الحالة', 'permission' => $emp],
                    ['route' => 'hr.reports.employees.statistics', 'title' => 'الإحصائيات', 'permission' => $emp],
                    ['route' => 'hr.reports.employees.organization', 'title' => 'الهيكل التنظيمي', 'permission' => 'org_chart.index'],
                ],
            ],
            [
                'key' => 'lifecycle', 'icon' => 'file-contract',
                'title' => 'العقود والوثائق والتعيينات',
                'hint' => 'العقود والوثائق المنتهية وما ينتهي قريباً، ومن التحق ومن غادر في الفترة',
                'tabs' => [
                    ['route' => 'hr.reports.employees.contracts', 'title' => 'العقود', 'permission' => $emp],
                    ['route' => 'hr.reports.employees.documents', 'title' => 'الوثائق', 'permission' => $emp],
                    ['route' => 'hr.reports.employees.turnover', 'title' => 'التعيينات وانتهاء الخدمة', 'permission' => $emp],
                ],
            ],
        ];
    }

    /** تبويباتُ المجموعة التي يملك المستخدمُ صلاحيتها ومسارُها موجود. */
    public static function visibleTabs(array $group): array
    {
        return array_values(array_filter($group['tabs'], fn (array $tab) => Route::has($tab['route'])
            && hasPermission($tab['permission'])
            && (empty($tab['extra_permission']) || hasPermission($tab['extra_permission']))));
    }

    /** المجموعة التي ينتمي إليها مسارٌ — لتبويبات صفحة التقرير. */
    public static function groupFor(?string $routeName): ?array
    {
        foreach (self::groups() as $group) {
            foreach ($group['tabs'] as $tab) {
                if ($tab['route'] === $routeName) {
                    return $group;
                }
            }
        }

        return null;
    }
}
