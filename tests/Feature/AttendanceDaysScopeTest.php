<?php

namespace Tests\Feature;

use Modules\HR\Http\Controllers\Attendance\AttendanceDaysController;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Tests\TestCase;

/**
 * نطاقُ الرؤية على شاشة أيام الحضور.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `index()` كانت تجلب أيامَ حضور كلِّ موظفي المنشأة بلا قيد — بينما
 * `filter()` في المتحكّم نفسِه تقيّد بالنطاق منذ البداية. فمن يملك
 * `attendance_days.index` مع نطاقٍ ضيّق (`data_scope_branch` مثلاً)
 * كان يرى الشاشةَ الأولى مفتوحةً على الجميع، والفلترةَ محدودةً بفرعه.
 * **الاختبار مقيَّدٌ بنيوياً** كنظيره في [HrReportScopeTest] لأن التحقّق
 * السلوكي الكامل يحتاج هجراتِ MySQL الخاصة (`enum`, foreign keys) التي
 * لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class AttendanceDaysScopeTest extends TestCase
{
    private function sourceOf(string $class): string
    {
        return (string) file_get_contents((new \ReflectionClass($class))->getFileName());
    }

    public function test_the_controller_applies_the_employee_scope(): void
    {
        $this->assertContains(
            ScopesToAccessibleEmployees::class,
            array_values(class_uses_recursive(AttendanceDaysController::class)),
            'AttendanceDaysController لا يطبّق نطاق الرؤية'
        );
    }

    /** index() و show() كلاهما يستدعي المرجع فعلاً، لا الاكتفاء باستعماله. */
    public function test_both_the_listing_and_the_single_record_call_the_scope(): void
    {
        $source = $this->sourceOf(AttendanceDaysController::class);

        $calls = preg_match_all(
            '/\$this->(scopeToEmployees|authorizeEmployeeScope)\(/',
            $source
        );

        $this->assertGreaterThanOrEqual(
            2,
            $calls,
            "المتحكّم يستعمل المرجع ولا يناديه إلا {$calls} مرّة"
        );
    }

    /** والسجلّ المفرد يُطلب بمعرّفٍ مباشر؛ تقييد القائمة وحدَه لا يحميه. */
    public function test_the_single_record_view_authorises_the_employee_scope(): void
    {
        $this->assertStringContainsString(
            '$this->authorizeEmployeeScope($attendance_day->employee_id);',
            $this->sourceOf(AttendanceDaysController::class)
        );
    }
}
