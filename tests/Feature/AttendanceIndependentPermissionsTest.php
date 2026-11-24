<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Constants\permissions\PermissionCatalog;
use App\Support\HrAttendancePermissions;
use Tests\TestCase;

/**
 * صلاحيات الحضور المستقلة + فصل العملية عن نطاق الرؤية.
 */
class AttendanceIndependentPermissionsTest extends TestCase
{
    public function test_records_and_books_are_separate_catalog_groups(): void
    {
        $groups = HrPermissions::groups();

        $this->assertArrayHasKey('attendance_records', $groups);
        $this->assertArrayHasKey('attendance_books', $groups);
        $this->assertArrayHasKey('attendance_records.index', $groups['attendance_records']['permissions']);
        $this->assertArrayHasKey('attendance_books.index', $groups['attendance_books']['permissions']);
        $this->assertArrayHasKey('attendance_books.edit', $groups['attendance_books']['permissions']);
        $this->assertArrayHasKey('attendance_days.edit', $groups['attendance_days']['permissions']);
    }

    public function test_attendance_groups_have_no_own_or_all_permissions(): void
    {
        foreach (HrPermissions::groups() as $key => $group) {
            if (($group['section'] ?? null) !== 'attendance') {
                continue;
            }

            foreach (array_keys($group['permissions']) as $name) {
                $this->assertStringStartsWith($key.'.', $name);
                $this->assertDoesNotMatchRegularExpression('/_own|view_all/', $name);
            }
        }
    }

    public function test_gates_have_no_legacy_aliases(): void
    {
        $this->assertSame('attendance_records.index', HrAttendancePermissions::gate('records.view'));
        $this->assertSame('leave_requests.index', HrAttendancePermissions::gate('leaves.view'));
    }

    public function test_department_scope_exists_in_catalog(): void
    {
        $this->assertContains('data_scope_department', \App\Constants\permissions\DataScopePermissions::all());
    }

    public function test_catalog_has_no_duplicates(): void
    {
        $this->assertSame([], PermissionCatalog::duplicates());
    }
}
