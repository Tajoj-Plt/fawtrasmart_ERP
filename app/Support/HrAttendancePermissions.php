<?php

namespace App\Support;

use App\Constants\permissions\HrPermissions;

/**
 * صلاحيات الحضور والإجازات: اسمٌ واحدٌ لكل عمليةٍ على كل شاشة (`<screen>.<action>`).
 *
 * المحور الأول (هنا): ماذا يفعل المستخدم على الشاشة (عرض / إضافة / تعديل…).
 * المحور الثاني (`data_scope_*`): على أي موظفين تسري العملية — ويُطبَّق في
 * المتحكّمات عبر `ScopesToAccessibleEmployees`. لا صلاحياتِ «هو» ولا «الكل»:
 * من يرى سجلاته وحده يحمل `attendance_records.index` + `data_scope_own`.
 *
 * لا بدائل قديمة بعد اليوم: كل بوابةٍ تعيد اسماً واحداً من الكتالوج
 * ({@see HrPermissions}). المفاتيحُ المختصرة القديمة (`records.view`,
 * `leaves.view` …) تبقى مرادفاتٍ للاسم الجديد كي لا تنكسر شاشةٌ تستدعيها،
 * ويُفضَّل في الكود الجديد كتابةُ الاسم مباشرة: `permission.role:attendance_days.edit`.
 */
class HrAttendancePermissions
{
    /**
     * المرادفات المختصرة ← الاسم الجديد الوحيد.
     *
     * @return array<string, list<string>>
     */
    public static function gates(): array
    {
        return [
            // ── سجلات الحضور ─────────────────────────────────────────
            'records.view' => ['attendance_records.index'],
            'records.create' => ['attendance_records.create'],
            'records.pull_device' => ['attendance_records.pull'],

            // ── دفاتر الحضور ─────────────────────────────────────────
            'books.view' => ['attendance_books.index'],
            'books.show' => ['attendance_books.show'],
            'books.create' => ['attendance_books.create'],
            'books.edit' => ['attendance_books.edit'],
            'books.delete' => ['attendance_books.destroy'],

            // ── أيام الحضور ──────────────────────────────────────────
            'days.view' => ['attendance_days.index'],
            'days.show' => ['attendance_days.show'],
            'days.edit' => ['attendance_days.edit'],
            'days.calculate' => ['attendance_days.calculate'],

            // ── تصحيح الحضور ─────────────────────────────────────────
            'corrections.view' => ['attendance_corrections.index'],
            'corrections.request' => ['attendance_corrections.create'],
            'corrections.approve' => ['attendance_corrections.approve'],

            // ── التسجيل الأونلاين / الكشك / الميدان ───────────────────
            'kiosk.screen' => ['attendance_registration.kiosk'],
            'kiosk.manual' => ['attendance_records.create'],
            'kiosk.mobile' => ['attendance_registration.mobile'],
            'kiosk.team' => ['attendance_team.index'],

            // ── بطاقات الحضور، والأماكن والأجهزة ─────────────────────
            'cards.view' => ['attendance_cards.index'],
            'cards.manage' => ['attendance_cards.manage'],
            'cards.print' => ['attendance_cards.print'],
            'places.view' => ['attendance_places.index'],
            'places.edit' => ['attendance_places.edit'],

            // ── إعدادات الحضور ───────────────────────────────────────
            'settings.manage' => ['attendance_settings.manage'],

            // ── طلبات الإجازات ───────────────────────────────────────
            'leaves.view' => ['leave_requests.index'],
            'leaves.show' => ['leave_requests.show'],
            'leaves.create' => ['leave_requests.create'],
            'leaves.edit' => ['leave_requests.edit'],
            'leaves.delete' => ['leave_requests.destroy'],
            'leaves.approve' => ['leave_requests.approve'],

            // ── أذونات المغادرة ──────────────────────────────────────
            'leave_permissions.view' => ['leave_permissions.index'],
            'leave_permissions.show' => ['leave_permissions.show'],
            'leave_permissions.create' => ['leave_permissions.create'],
            'leave_permissions.edit' => ['leave_permissions.edit'],
            'leave_permissions.delete' => ['leave_permissions.destroy'],
        ];
    }

    /**
     * سلسلة الحارس: `permission.role:…`.
     *
     * تقبل المرادفَ المختصر أو اسمَ الصلاحية نفسَه من الكتالوج.
     */
    public static function gate(string $action): string
    {
        $names = self::gates()[$action] ?? null;

        if ($names === null && self::isCatalogName($action)) {
            $names = [$action];
        }

        if ($names === null || $names === []) {
            throw new \InvalidArgumentException("Unknown attendance permission gate [{$action}]");
        }

        return implode('|', $names);
    }

    /** دمج عدة بوابات في حارس واحد (OR). */
    public static function any(string ...$actions): string
    {
        $names = [];

        foreach ($actions as $action) {
            array_push($names, ...explode('|', self::gate($action)));
        }

        return implode('|', array_values(array_unique($names)));
    }

    /**
     * الاسم الوحيد لكل مرادف.
     *
     * @return array<string, string>
     */
    public static function primaryKeys(): array
    {
        $out = [];

        foreach (self::gates() as $action => $names) {
            $out[$action] = $names[0];
        }

        return $out;
    }

    /**
     * أُلغيت خريطة الترحيل: الأسماء القديمة حُذفت بترحيل
     * `2026_09_24_220000_hr_permissions_by_screen_and_scope`، ولا بديلَ قديماً يُمنح بعده.
     *
     * @return array<string, list<string>>
     */
    public static function legacyMigrationMap(): array
    {
        return [];
    }

    /** @deprecated الأسماء القديمة حُذفت — تعيد مصفوفةً فارغة. */
    public static function legacyToPrimary(): array
    {
        return self::legacyMigrationMap();
    }

    private static function isCatalogName(string $name): bool
    {
        static $names = null;

        if ($names === null) {
            $names = [];
            foreach (HrPermissions::groups() as $group) {
                foreach (array_keys($group['permissions'] ?? []) as $permission) {
                    $names[$permission] = true;
                }
            }
        }

        return isset($names[$name]);
    }
}
