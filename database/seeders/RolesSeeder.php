<?php

namespace Database\Seeders;

use App\Constants\permissions\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * أدوار النظام الأساسية — مفصولةً عن `UserSeeder`.
 *
 * الترتيب المُلزِم:
 * بعد `PermissionTableSeeder` وقبل أي Seeder يمنح صلاحيات لدورٍ بالاسم.
 *
 * هذا السيدر لا يحذف الصلاحيات الموجودة للدور.
 * يستخدم `givePermissionTo` للإضافة فقط، حتى لا يفقد صاحب النظام
 * أي صلاحيات تم ضبطها يدويًا من شاشة الأدوار الوظيفية.
 */
class RolesSeeder extends Seeder
{
    /**
     * أدوار النظام:
     *
     * الاسم ⇦ [الوصف، هل يملك كل الصلاحيات الأساسية؟]
     *
     * owner و manager و admin:
     * يحصلون على جميع الصلاحيات الموجودة في PermissionCatalog.
     *
     * employee:
     * لا يحصل على صلاحيات بالجملة؛ صلاحياته تُحدد حسب الوظيفة.
     */
    public const SYSTEM_ROLES = [
        'owner' => [
            'label' => 'صاحب النظام',
            'full_access' => true,
        ],

        'manager' => [
            'label' => 'مدير النظام',
            'full_access' => true,
        ],

        'admin' => [
            'label' => 'مشرف',
            'full_access' => true,
        ],

        'employee' => [
            'label' => 'موظف',
            'full_access' => false,
        ],
    ];

    public const GUARD = 'web';

    public function run(): void
    {
        /*
         * ضمان وجود الصلاحيات الجديدة قبل إنشاء/منح الأدوار.
         *
         * المصدر الوحيد للصلاحيات هنا هو PermissionCatalog.
         * لا يوجد أي اعتماد على JobRole::$job_roles.
         */
        $basePermissions = $this->ensureBasePermissions();

        /*
         * تفريغ كاش Spatie قبل منح الصلاحيات.
         */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::SYSTEM_ROLES as $name => $config) {
            /*
             * إنشاء الدور إذا لم يكن موجودًا.
             * إذا كان موجودًا فلن يتم حذفه أو إعادة إنشائه.
             */
            $role = Role::firstOrCreate([
                'name' => $name,
                'guard_name' => self::GUARD,
            ]);

            /*
             * الأدوار ذات full_access تحصل على جميع صلاحيات
             * PermissionCatalog.
             *
             * نستخدم givePermissionTo وليس syncPermissions حتى لا
             * نمسح الصلاحيات التي أضيفت يدويًا للدور.
             */
            if ($config['full_access']) {
                $role->givePermissionTo($basePermissions);
            }

            $this->command?->info(sprintf(
                '  الدور «%s» (%s): %d صلاحية',
                $name,
                $config['label'],
                $role->permissions()->count()
            ));
        }

        /*
         * تفريغ الكاش بعد انتهاء المنح.
         */
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * ضمان وجود الصلاحيات الأساسية قبل منحها للأدوار.
     *
     * مصدر الصلاحيات:
     *
     *     PermissionCatalog::all()
     *
     * لا يتم استخدام:
     *
     *     JobRole::$job_roles
     *
     * وبالتالي لن تعود الصلاحيات القديمة إلى قاعدة البيانات
     * عند تشغيل RolesSeeder.
     *
     * @return array<int, string> أسماء الصلاحيات الأساسية
     */
    protected function ensureBasePermissions(): array
    {
        /*
         * النظام الجديد فقط.
         *
         * لا نقرأ JobRole::$job_roles.
         * لا نستخدم PermissionCatalog::SUPERSEDED لأن الصلاحيات
         * القديمة أصلًا لم تعد تدخل في هذه القائمة.
         */
        $names = array_values(
            array_unique(
                PermissionCatalog::all()
            )
        );

        /*
         * معرفة الصلاحيات الموجودة بالفعل.
         */
        $existing = Permission::where('guard_name', self::GUARD)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        /*
         * تحديد الصلاحيات الناقصة فقط.
         */
        $missing = array_values(
            array_diff($names, $existing)
        );

        /*
         * إنشاء الصلاحيات الناقصة.
         */
        foreach ($missing as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => self::GUARD,
            ]);
        }

        if ($missing !== []) {
            $this->command?->info(
                '  أُنشئت '.count($missing).' صلاحية ناقصة.'
            );
        }

        return $names;
    }
}