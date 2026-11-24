<?php

namespace App\Console\Commands;

use App\Support\HrAttendancePermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * يمنح الأدوارَ التي تحمل صلاحيات الحضور القديمة المفاتيحَ الجديدة
 * المكافئة — دون نزع القديمة (التوافق يبقى عبر الحارس).
 *
 * شغِّل بعد `permissions:sync-catalog`.
 */
class MigrateAttendancePermissionScopes extends Command
{
    protected $signature = 'permissions:migrate-attendance-scopes
                            {--dry-run : عرض ما سيُمنَح دون تنفيذه}';

    protected $description = 'ترحيل منح صلاحيات الحضور القديمة إلى المفاتيح الجديدة (عملية + نطاق رؤية)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $map = HrAttendancePermissions::legacyToPrimary();
        $permissionIds = Permission::pluck('id', 'name');

        $granted = 0;
        $skippedMissing = 0;

        foreach ($map as $legacy => $primaries) {
            $legacyId = $permissionIds[$legacy] ?? null;
            if (! $legacyId) {
                $skippedMissing++;

                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $legacyId)
                ->pluck('role_id');

            foreach ($primaries as $primary) {
                $primaryId = $permissionIds[$primary] ?? null;
                if (! $primaryId) {
                    $this->warn("الصلاحية الجديدة «{$primary}» غير موجودة — شغِّل permissions:sync-catalog أولاً.");
                    $skippedMissing++;

                    continue;
                }

                foreach ($roleIds as $roleId) {
                    $exists = DB::table('role_has_permissions')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $primaryId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $this->line("دور #{$roleId}: {$legacy} → {$primary}");

                    if (! $dry) {
                        DB::table('role_has_permissions')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $primaryId,
                        ]);
                    }

                    $granted++;
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."مِنَح جديدة: {$granted} — تخطّي ناقص: {$skippedMissing}");

        if (! $dry) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return self::SUCCESS;
    }
}
