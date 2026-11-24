<?php

namespace Modules\Client\Database\Seeders;

use App\Constants\permissions\ReservationsPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ربطُ صلاحيات الحجوزات بالأدوار الإدارية.
 *
 * ═══════════ لماذا سيدرٌ مستقلّ بعد المزامنة؟ ═══════════
 *
 * `permissions:sync-catalog` **يُنشئ الصلاحية ولا يربطها بأيّ دور**. وهو
 * الصواب — ليس للأمر أن يقرّر من يملك ماذا. لكنّ أثرَه العمليّ أنّ وحدةً
 * جديدة تبقى **غير مرئيةٍ لأحد** بعد المزامنة: القائمةُ الجانبية تُخفي
 * روابطَها لأنّ أحداً لا يملك `reservations.view`، فيُظنّ أن التركيب فشل.
 *
 * فالربطُ هنا، صريحاً ومحدوداً بالأدوار الإدارية وحدها.
 *
 * ═══════════ ولماذا لا يُمنح الجميع؟ ═══════════
 *
 * `manager` و`owner` و`admin` تملك اليوم أكثرَ من تسعمئة صلاحية كلٌّ —
 * وهي أدوارُ الإدارة الكاملة. أمّا «مندوب مبيعات» و«مشرف» و`employee`
 * فتُترك بلا صلاحياتِ حجوزات: مَن يحتاجها منهم يُمنحها من شاشة الأدوار
 * بقرارٍ صريح — لا بسيدرٍ يفترض عنه.
 *
 * والسيدرُ **تراكميٌّ لا يمحو**: يستعمل `givePermissionTo` على الناقص
 * وحدَه، فإعادةُ تشغيله بعد ضبطٍ يدوي لا تسحب صلاحيةً ولا تمنح ما نُزع
 * عمداً... إلّا ما نقص أصلاً. وهو ما يجعله آمناً في التحديثات.
 */
class ReservationsPermissionsSeeder extends Seeder
{
    /** الأدوار التي تُمنح الوحدةَ كاملةً. */
    private const ADMIN_ROLES = ['manager', 'owner', 'admin'];

    public function run(): void
    {
        $names = ReservationsPermissions::all();

        // الصلاحيات نفسُها — تُنشأ إن لم تكن المزامنة قد شُغّلت بعد
        foreach ($names as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => ReservationsPermissions::GUARD,
            ]);
        }

        $granted = 0;
        $touched = [];

        foreach (self::ADMIN_ROLES as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', ReservationsPermissions::GUARD)
                ->first();

            if (! $role) {
                continue;
            }

            $missing = array_diff($names, $role->permissions()->pluck('name')->all());

            if ($missing !== []) {
                $role->givePermissionTo($missing);
                $granted += count($missing);
            }

            $touched[] = $roleName.' (+'.count($missing).')';
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info(
            'صلاحيات الحجوزات: '.count($names).' صلاحية، '
            .$granted.' ربطاً جديداً على الأدوار: '.(implode('، ', $touched) ?: 'لا شيء')
        );
    }
}
