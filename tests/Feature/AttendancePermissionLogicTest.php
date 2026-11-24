<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Support\HrAttendancePermissions;
use Tests\TestCase;

/**
 * صلاحيات الحضور: اسمٌ واحدٌ لكل فعلٍ على كل شاشة، بلا بدائل قديمة وبلا «هو».
 *
 * نطاقُ الرؤية (`data_scope_*`) وحده يحدّد على من تسري الصلاحية — فلا بوابةَ
 * تحمل `view_all` أو `view_own`، ولا بوابةَ جزءٍ تفتح جزءاً آخر.
 */
class AttendancePermissionLogicTest extends TestCase
{
    public function test_every_gate_resolves_to_exactly_one_catalog_name(): void
    {
        $catalog = collect(HrPermissions::groups())
            ->flatMap(fn ($group) => array_keys($group['permissions']))
            ->all();

        foreach (HrAttendancePermissions::gates() as $action => $names) {
            $this->assertCount(1, $names, "{$action} must map to a single permission");
            $this->assertContains($names[0], $catalog, "{$action} → {$names[0]} missing from catalog");
        }
    }

    public function test_no_gate_carries_own_or_all_or_legacy_names(): void
    {
        foreach (HrAttendancePermissions::gates() as $action => $names) {
            foreach ($names as $name) {
                $this->assertDoesNotMatchRegularExpression('/_own|view_all|^staff_|^employee_staff|^hr_/', $name, $action);
            }
        }
    }

    public function test_days_edit_does_not_grant_books_edit(): void
    {
        $this->assertSame('attendance_books.edit', HrAttendancePermissions::gate('books.edit'));
        $this->assertSame('attendance_days.edit', HrAttendancePermissions::gate('days.edit'));
    }

    public function test_gate_accepts_catalog_names_directly(): void
    {
        $this->assertSame('attendance_team.index', HrAttendancePermissions::gate('attendance_team.index'));
        $this->assertSame('leave_requests.index', HrAttendancePermissions::gate('leaves.view'));
    }

    public function test_unknown_gate_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HrAttendancePermissions::gate('records.view_all');
    }

    public function test_legacy_migration_map_is_retired(): void
    {
        $this->assertSame([], HrAttendancePermissions::legacyMigrationMap());
    }

    public function test_punches_controller_guards_records_index_only(): void
    {
        $source = file_get_contents(base_path(
            'Modules/HR/Http/Controllers/Attendance/AttendancePunchesController.php'
        ));

        $this->assertStringContainsString("permission.role:attendance_records.index'", $source);
        $this->assertStringNotContainsString('attendance_registration', $source);
    }

    public function test_calculation_form_posts_to_run_calculation_not_sheets(): void
    {
        $source = file_get_contents(base_path(
            'Modules/HR/resources/views/attendance/attendance_days/Calculation.blade.php'
        ));

        $this->assertStringContainsString('attendanceDays.runCalculation', $source);
        $this->assertStringNotContainsString("route('attendance_sheets.store')", $source);
        $this->assertStringContainsString("@permission('attendance_days.calculate')", $source);
    }
}
