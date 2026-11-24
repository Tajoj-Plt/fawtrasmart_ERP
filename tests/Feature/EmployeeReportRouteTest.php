<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Http\Controllers\EmployeeReportController;
use Tests\TestCase;

/**
 * توصيلةُ «تقرير الموظفين».
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كان المسار `employeeReports.index` يشير إلى
 * `EmployeeController::report()` — دالّةٍ **جسدُها فارغ** (`{}`) — فبندُ
 * «تقرير الموظفين» في الشريط الجانبي يفتح صفحةً بيضاء. و
 * `EmployeeReportController` قائمٌ لهذا الغرض وحده، يعرض القالب ويحرسه
 * `hr_reports_view`، **ولا مسارَ يبلغه**. فالخللُ توصيلةٌ لا نقص.
 *
 * وفي إصلاحها فخٌّ: حارسُ الوجهة الجديدة `hr_reports_view`، وحارسُ بند
 * القائمة كان `employees_view_profile`. فمن يملك الثاني دون الأول يرى
 * البندَ ويُردّ بـ403 عند النقر — زرٌّ يَعِد بما يمنعه الخادم. ولهذا
 * يُقاس الحارسان معاً.
 */
class EmployeeReportRouteTest extends TestCase
{
    private function route(): \Illuminate\Routing\Route
    {
        $found = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'employeeReports.index');

        $this->assertNotNull($found, 'المسار employeeReports.index غير مسجَّل');

        return $found;
    }

    public function test_the_route_points_at_the_report_controller(): void
    {
        $this->assertSame(
            EmployeeReportController::class.'@index',
            $this->route()->getActionName()
        );
    }

    public function test_the_employee_controller_no_longer_carries_an_empty_report_action(): void
    {
        $this->assertFalse(
            method_exists(EmployeeController::class, 'report'),
            'بقيت `EmployeeController::report()` — وكانت دالّةً فارغة تُنتج صفحة بيضاء'
        );
    }

    public function test_the_route_is_guarded_by_the_reports_permission(): void
    {
        $guards = array_values(array_filter(
            $this->route()->gatherMiddleware(),
            fn ($m) => is_string($m) && str_starts_with($m, 'permission.role:')
        ));

        $this->assertContains('permission.role:hr_reports_view', $guards);
    }

    /**
     * وبندُ القائمة يُحرَس بالصلاحية نفسِها — وإلا وَعَد بما يمنعه الخادم.
     */
    public function test_the_sidebar_entry_is_guarded_by_the_same_permission(): void
    {
        $sidebar = base_path('resources/views/layouts/sidebar.blade.php');

        $this->assertFileExists($sidebar);

        $html = (string) file_get_contents($sidebar);

        $this->assertSame(
            1,
            preg_match(
                "/@if\s*\(\s*hasPermission\('([a-z_]+)'\)\s*\)\s*(?:\R\s*)*<li>\s*<a href=\"\{\{ route\('employeeReports\.index'\) \}\}\"/u",
                $html,
                $found
            ),
            'لم يُعثر على بند «تقرير الموظفين» مسبوقاً بحارسه في الشريط الجانبي'
        );

        $this->assertSame('hr_reports_view', $found[1]);
    }
}
