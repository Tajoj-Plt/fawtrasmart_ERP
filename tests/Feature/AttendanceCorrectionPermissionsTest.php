<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Constants\permissions\PermissionCatalog;
use Tests\TestCase;

/**
 * صلاحيتان منفصلتان لطلب/اعتماد تصحيح الحضور — TODO 10b.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * تدفّق «طلب → اعتماد» يحتاج صلاحيتين مختلفتين لا واحدة، تماماً كما في
 * طلب السلفة واعتمادها —
 * وإلا استوى من يطلب التصحيح بمن يعتمده، ولم يتحقق الفصل الذي بُنيت
 * الميزة كلُّها من أجله (`AttendanceDaysController::update` الحالي
 * كان يستخدم صلاحيةً واحدة لكلا الفعلين).
 */
class AttendanceCorrectionPermissionsTest extends TestCase
{
    public function test_the_two_permissions_exist_in_the_catalog(): void
    {
        $names = collect(HrPermissions::groups())
            ->flatMap(fn ($group) => array_keys($group['permissions']))
            ->all();

        $this->assertContains('attendance_corrections.create', $names);
        $this->assertContains('attendance_corrections.approve', $names);
    }

    /** الصلاحيتان مختلفتان — لا الاسم نفسه مكرَّراً بالخطأ. */
    public function test_the_two_permissions_are_distinct(): void
    {
        $this->assertNotSame(
            'attendance_corrections.create',
            'attendance_corrections.approve'
        );
    }

    /** ولا تصادم مع صلاحيةٍ بالاسم نفسه في قسمٍ آخر — الكتالوج كلُّه يمرّ من هنا. */
    public function test_the_catalog_has_no_duplicate_permission_names(): void
    {
        $this->assertSame([], PermissionCatalog::duplicates());
    }

    public function test_both_permissions_are_classified_under_the_attendance_group(): void
    {
        $groups = HrPermissions::groups();

        $this->assertArrayHasKey('attendance_corrections.create', $groups['attendance_corrections']['permissions']);
        $this->assertArrayHasKey('attendance_corrections.approve', $groups['attendance_corrections']['permissions']);
    }
}
