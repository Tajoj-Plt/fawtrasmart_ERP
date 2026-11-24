<?php

namespace Tests\Feature;

use Modules\HR\Http\Controllers\CommissionsReportController;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Controllers\DashboardController;
use Modules\HR\Http\Controllers\EmployeePerformanceController;
use Modules\HR\Http\Controllers\EmployeeReportController;
use Modules\HR\Http\Controllers\HRReportsController;
use Modules\HR\Http\Controllers\PayrollReportController;
use Tests\TestCase;

/**
 * نطاقُ الرؤية على التقارير واللوحات.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `ScopesToAccessibleEmployees` كان مطبَّقاً على ثلاثةَ عشرَ متحكّماً من
 * ستّةٍ وأربعين — وليس على **التقارير ولا اللوحة ولا العمولات ولا
 * الأداء**. فمديرُ قسمٍ يملك `hr_reports_view` كان يرى:
 *
 *   • تقريرَ الرواتب لموظفي المنشأة كلِّهم،
 *   • وتقريرَ الحضور كذلك،
 *   • وأداءَ الجميع في اللوحة،
 *   • وعمولاتِهم —
 *
 * بينما تمنعه شاشةُ القسائم نفسُها من رؤية قسيمةٍ واحدة خارج نطاقه.
 * **فالبابُ الذي يُغلق في شاشةٍ كان يُفتح في أخرى.**
 *
 * ويُقاس هنا بنيوياً لا سلوكياً: تشغيلُ تقريرٍ كامل يحتاج عشرةَ جداولَ
 * وبيانات، والمقيسُ **أنّ كلَّ استعلامٍ يمسّ الموظفين يمرّ على النطاق** —
 * وهو ما يسهل أن يُنسى في تقريرٍ جديد.
 */
class HrReportScopeTest extends TestCase
{
    /** @return array<int, string> */
    private function traitsOf(string $class): array
    {
        return array_values(class_uses_recursive($class));
    }

    private function sourceOf(string $class): string
    {
        return (string) file_get_contents((new \ReflectionClass($class))->getFileName());
    }

    /**
     * أربعةُ متحكّماتٍ كانت مكشوفة — صارت تطبّق النطاق.
     */
    public function test_the_reporting_controllers_apply_the_employee_scope(): void
    {
        $controllers = [
            HRReportsController::class,
            DashboardController::class,
            CommissionsReportController::class,
            EmployeePerformanceController::class,
            EmployeeReportController::class,
            PayrollReportController::class,
        ];

        foreach ($controllers as $controller) {
            $this->assertContains(
                ScopesToAccessibleEmployees::class,
                $this->traitsOf($controller),
                class_basename($controller).' لا يطبّق نطاق الرؤية'
            );
        }
    }

    /** ولا يكفي استعمالُ المرجع: يجب أن يُنادى فعلاً في كل واحدٍ منها. */
    public function test_each_controller_actually_calls_the_scope(): void
    {
        $expectations = [
            HRReportsController::class => 4,          // ثلاثُ قوائم فلترة + ثلاثةُ تقارير
            DashboardController::class => 1,
            CommissionsReportController::class => 2,  // القائمة + السجلّ المفرد
            EmployeePerformanceController::class => 3,
            EmployeeReportController::class => 3,     // الاستعلام + العقود/الوثائق + قائمة الفلتر
            PayrollReportController::class => 4,      // runs + items + deductions + advances + قائمة الفلتر
        ];

        foreach ($expectations as $controller => $minimum) {
            $source = $this->sourceOf($controller);

            $calls = preg_match_all(
                '/\$this->(scopeToEmployees|scopeAnyQueryToEmployees|scopedEmployeeOptions|authorizeEmployeeScope)\(/',
                $source
            );

            $this->assertGreaterThanOrEqual(
                $minimum,
                $calls,
                class_basename($controller)." يستعمل المرجع ولا يناديه إلا {$calls} مرّة"
            );
        }
    }

    /**
     * والقوائمُ المنسدلة في التقارير من النطاق نفسِه.
     *
     * قائمةٌ تعرض أسماءَ من لا يراهم المستخدم تسرّب أسماءَ الموظفين، ثم
     * تُصفّي بها تقريراً لا يُظهر شيئاً.
     */
    public function test_report_filters_no_longer_list_every_employee(): void
    {
        $source = $this->sourceOf(HRReportsController::class);

        $this->assertFalse(
            str_contains($source, "Employee::with('department')->get()"),
            'قائمةُ الفلتر ما زالت تجلب كلَّ موظفي المنشأة'
        );

        $this->assertStringContainsString('scopedEmployeeOptions()', $source);
    }

    /**
     * وحمايةُ السجلّ المفرد في العمولات: `show/{id}` تُطلب بمعرّفٍ مباشر،
     * فتقييدُ القائمة وحدَها يُبلغ من غيّر الرقمَ عمولةَ غيره.
     */
    public function test_the_commission_detail_authorises_the_single_record(): void
    {
        $this->assertStringContainsString(
            '$this->authorizeEmployeeScope($commission->employee_id);',
            $this->sourceOf(CommissionsReportController::class)
        );
    }

    /**
     * والمرجعُ يقبل بانيَ الاستعلام الخام — وهو سببُ بقاء التقارير مكشوفة.
     *
     * `scopeToEmployees()` تشترط `Eloquent\Builder`، وتقاريرُ الوحدة تُبنى
     * بـ`DB::table(...)->join('employees', …)` فلا تقبلها.
     */
    public function test_the_trait_supports_raw_query_builders(): void
    {
        $this->assertTrue(
            method_exists(
                ScopesToAccessibleEmployees::class,
                'scopeAnyQueryToEmployees'
            )
        );
    }

    /**
     * والعمودُ يُمرَّر مؤهَّلاً باسم الجدول أو باسمه المستعار: الاستعلامُ
     * الواحد في تقرير المدفوعات يضمّ ثلاثَ وصلاتٍ إلى `employees`، فعمودٌ
     * غيرُ مؤهَّل يُسقط الاستعلام بـ«ambiguous column».
     */
    public function test_the_scoped_columns_are_table_qualified(): void
    {
        $source = $this->sourceOf(HRReportsController::class);

        preg_match_all(
            "/scopeAnyQueryToEmployees\(\\\$q, '([^']+)'\)/",
            $source,
            $matches
        );

        $this->assertNotEmpty($matches[1], 'لم يُعثر على استدعاءات النطاق');

        foreach ($matches[1] as $column) {
            $this->assertStringContainsString('.', $column, "العمود «{$column}» غير مؤهَّل باسم جدوله");
        }
    }
}
