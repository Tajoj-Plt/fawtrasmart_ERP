<?php

namespace Database\Seeders;

use App\Constants\permissions\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionTableSeeder extends Seeder
{
    /**
     * أسماء تطلبها الشيفرة ولا توجد في الكتالوجات الحالية.
     */
    private const CODE_ONLY = [
        'client_follow_up_add_notes_attachments_appointments_assigned',
        'client_follow_up_edit_delete_notes_attachments_appointments_assigned',
    ];

    public function run(): void
    {
        // النظام الجديد فقط
        $catalogued = PermissionCatalog::all();

        $names = array_values(array_unique(array_merge(
            $catalogued,
            self::CODE_ONLY
        )));

        // حذف الصلاحيات اليتيمة فقط: غير معروفة لأي كتالوج حالي (وليست
        // من CODE_ONLY). لا نحذف بناءً على شكل الاسم (وجود نقطة أو لا) —
        // الحضورُ والإجازات في HR ما زالت بصيغة underscore وهي معتمدة في
        // الكتالوج (والرواتبُ صارت `payroll.*` / `payslips.*` / `loans.*` …
        // منذ 2026-09-24)، فحذفها بهذا المعيار كان يمسح صامتاً كل ربط
        // role_has_permissions (cascadeOnDelete) لأي دور مخصَّص يحملها.
        // والأسماءُ المتقاعدة (`salaries_payroll_*` وأخواتها) خارج الكتالوج
        // فتُحذف هنا على التثبيت الجديد — ولا دورَ يُزرع عليها أصلاً.
        Permission::query()
            ->where('guard_name', RolesSeeder::GUARD)
            ->whereNotIn('name', $names)
            ->delete();

        $created = $this->ensure($names);

        Artisan::call('permissions:sync-catalog');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = [];

        foreach (PermissionCatalog::modules() as $module) {
            $count = 0;

            foreach ($module['groups'] as $group) {
                $count += count($group['permissions']);
            }

            $modules[] = $module['label'].' '.$count;
        }

        $this->command?->info(sprintf(
            'الصلاحيات: %d مصنفة في %d قسم، %d أُنشئت الآن.',
            count($catalogued),
            count($modules),
            $created
        ));

        $this->command?->line('الأقسام: '.implode(' ، ', $modules));
    }

    /**
     * إنشاء الصلاحيات غير الموجودة فقط.
     *
     * @param array<int, string> $names
     * @return int
     */
    protected function ensure(array $names): int
    {
        $existing = Permission::query()
            ->where('guard_name', RolesSeeder::GUARD)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($names, $existing));

        if ($missing === []) {
            return 0;
        }

        $now = now();

        foreach (array_chunk($missing, 500) as $chunk) {
            Permission::insertOrIgnore(array_map(
                fn (string $name) => [
                    'name' => $name,
                    'guard_name' => RolesSeeder::GUARD,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $chunk
            ));
        }

        return count($missing);
    }
}