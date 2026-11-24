<?php

namespace Database\Seeders;

use App\Constants\permissions\EcosystemPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ربطُ صلاحيات النظام البيئي للنقاط بالأدوار الإدارية.
 *
 * ═══════════ لماذا سيدرٌ بعد المزامنة؟ ═══════════
 *
 * `permissions:sync-catalog` **يُنشئ الصلاحية ولا يربطها بأيّ دور**. وهو
 * الصواب — ليس للأمر أن يقرّر من يملك ماذا. لكنّ أثرَه هنا خطير: بطاقةُ
 * الوحدة كانت **بلا حارس** فيراها الجميع، وصارت محروسةً بـ`ecosystem.view`.
 * فبلا هذا الربط تختفي البطاقةُ عن **كلّ** مستخدمي النظام بمن فيهم المدير،
 * ويبدو الأمرُ كأنّ الوحدة حُذفت.
 *
 * ═══════════ ولماذا هذه الأدوار وحدها؟ ═══════════
 *
 * `manager` و`owner` و`admin` أدوارُ الإدارة الكاملة (تملك اليوم أكثر من
 * تسعمئة صلاحية كلٌّ). أمّا «مندوب مبيعات» و«مشرف» و`employee` فتُترك
 * بلا صلاحيات النقاط: من يحتاجها منهم يُمنحها من شاشة الأدوار بقرارٍ
 * صريح — لا بسيدرٍ يفترض عنه.
 *
 * والسيدرُ **تراكميٌّ لا يمحو**: يمنح الناقصَ وحدَه، فإعادةُ تشغيله بعد
 * ضبطٍ يدوي لا تسحب صلاحيةً نُزعت عمداً.
 *
 * @see \Modules\Client\Database\Seeders\ReservationsPermissionsSeeder النظير
 */
class EcosystemPermissionsSeeder extends Seeder
{
    /** الأدوار التي تُمنح الوحدةَ كاملةً. */
    private const ADMIN_ROLES = ['manager', 'owner', 'admin'];

    public function run(): void
    {
        $names = EcosystemPermissions::all();

        foreach ($names as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => EcosystemPermissions::GUARD,
            ]);
        }

        $granted = 0;
        $touched = [];

        foreach (self::ADMIN_ROLES as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', EcosystemPermissions::GUARD)
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
            'صلاحيات النظام البيئي: '.count($names).' صلاحية، '
            .$granted.' ربطاً جديداً على الأدوار: '.(implode('، ', $touched) ?: 'لا شيء')
        );
    }
}
