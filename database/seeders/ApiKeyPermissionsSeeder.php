<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات مفاتيح الربط البرمجي.
 *
 * الصلاحيات الخمس كانت موجودة في قاعدة البيانات ولا يفرضها أحد: المتحكّم
 * بلا وسيط والمسارات بلا صلاحية. وبعد فرضها في المتحكّم ظهر خلل ثانٍ كان
 * مستوراً — أنها ممنوحة **للمدير وحده دون المالك**، فلو فُرضت كما هي لخرج
 * صاحب النظام من شاشته وبقي المدير.
 *
 * هذا السيدر يصحّح التوزيع: المالك يملكها كلّها، والمدير يملك العرض
 * والإنشاء والإبطال دون الحذف — إبطال المفتاح يوقفه ويُبقي أثره، أمّا
 * الحذف فيمحو سجلّ من أنشأه ومتى استُعمل آخر مرّة.
 */
class ApiKeyPermissionsSeeder extends Seeder
{
    /** ما يملكه المدير أيضاً. */
    protected const SHARED = [
        'api_keys.view',
        'api_keys.create',
        'api_keys.revoke',
    ];

    /** للمالك وحده. */
    protected const OWNER_ONLY = [
        'api_keys.edit',
        'api_keys.delete',
    ];

    public function run(): void
    {
        foreach ([...self::SHARED, ...self::OWNER_ONLY] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->grant('owner', [...self::SHARED, ...self::OWNER_ONLY]);
        $this->grant('manager', self::SHARED);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('صلاحيات مفاتيح الربط: '
            .(count(self::SHARED) + count(self::OWNER_ONLY)).' صلاحية، والمالك يملكها كلّها.');
    }

    /**
     * `givePermissionTo` لا `syncPermissions`: الأخير يمحو بقية صلاحيات
     * الدور، وتشغيل سيدر يجب ألّا يُفقد أحداً شيئاً.
     *
     * @param  array<int, string>  $permissions
     */
    protected function grant(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->command?->warn("الدور «{$roleName}» غير موجود — تُخطّي منح صلاحياته.");

            return;
        }

        $role->givePermissionTo($permissions);
    }
}
