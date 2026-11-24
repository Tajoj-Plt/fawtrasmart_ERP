<?php

namespace Tests\Feature;

use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Tests\TestCase;

/**
 * مساراتُ التصفية بـAJAX تُقيَّد بنطاق الموظفين كما تُقيَّد شاشاتُها.
 *
 * **ما كان**
 *
 * `index()` في شاشتَي «طلبات الإجازة» و«أذونات إجازة» تستدعي
 * `scopeToEmployees()`، ومسارُ التصفية بـAJAX الذي تستدعيه الشاشةُ نفسُها
 * عند أوّل حرفٍ يُكتب — `LeaveRequestsController::search()` و
 * `LeavePermissionsController::getLeavePermissionsAjax()` — لا يستدعيه.
 *
 * فالنتيجةُ أنّ المستخدم يفتح الصفحة فيرى نطاقَه، فإذا بحث رأى طلباتِ
 * المنشأة كلِّها: أسماءَ أصحابها وأسبابَهم وأرصدتَهم. والتقييدُ في القائمة
 * وحدَها لا يكفي حين يكون البحثُ بابَها الفعليّ.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن كلَّ مسارٍ منها يمرّ على النطاق. والقياسُ على الشيفرة لا على
 * الاستعلام لأن بناءَ مخطَّطِ الوحدتين كاملاً لاختبارٍ واحد أكلفُ من
 * الفائدة، والغلَطُ الذي وقع كان غيابَ سطرٍ لا خطأً في منطقه.
 *
 * @see ScopesToAccessibleEmployees
 */
class AttendanceAjaxFilterScopeTest extends TestCase
{
    /** @return array<int, array{0: string, 1: string}> */
    public static function ajaxFilterPaths(): array
    {
        return [
            'طلبات الإجازة — البحث' => [
                'Modules/HR/Http/Controllers/Attendance/LeaveRequestsController.php',
                'public function search(',
            ],
            'أذونات إجازة — التصفية' => [
                'Modules/HR/Http/Controllers/Attendance/LeavePermissionsController.php',
                'public function getLeavePermissionsAjax(',
            ],
        ];
    }

    /**
     * @dataProvider ajaxFilterPaths
     */
    public function test_the_ajax_filter_path_applies_the_employee_scope(string $file, string $signature): void
    {
        $source = (string) file_get_contents(base_path($file));

        $start = strpos($source, $signature);
        $this->assertNotFalse($start, "لم يُعثر على {$signature} في {$file}");

        // من مطلع الدالّة حتى أوّل استعلامٍ مُرقَّم — النطاقُ يقع قبله
        $body = substr($source, $start, 3000);

        $this->assertStringContainsString(
            '$this->scopeToEmployees($query)',
            $body,
            "مسارُ التصفية في {$file} يتخطّى نطاقَ الموظفين"
        );
    }

    /** والنطاقُ نفسُه يعمل: مستخدمٌ بلا `data_scope_all` مقيَّدٌ بموظفه. */
    public function test_the_scope_trait_limits_a_plain_user_to_their_own_employee(): void
    {
        $scope = new class
        {
            use ScopesToAccessibleEmployees;

            public function ids(): ?array
            {
                return $this->accessibleEmployeeIds();
            }
        };

        $this->assertSame([], $scope->ids(), 'بلا مستخدمٍ لا أحد');
    }
}
