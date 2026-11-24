<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * لا صلاحياتِ «هو» في قسم الحضور: الصلاحيةُ تسمّي الشاشةَ والعمل، وعلى أيّ
 * موظفين تسري يحدّده نطاقُ الرؤية (`data_scope_*`).
 *
 *   attendance_registration.self   → attendance_registration.mobile  (التسجيل من الجوال)
 *   attendance_registration.others → attendance_registration.kiosk   (شاشة جهاز الفرع)
 *
 * إعادةُ تسميةٍ في مكانها: `role_has_permissions` يربط بالمعرّف لا بالاسم،
 * فيبقى كلُّ منحٍ قائماً بلا خطوة توريث.
 */
return new class extends Migration
{
    private const RENAMES = [
        'attendance_registration.self' => 'attendance_registration.mobile',
        'attendance_registration.others' => 'attendance_registration.kiosk',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);

        // التسمياتُ العربية وتقسيمُ شاشة الأدوار للاسمين الجديدين (انظر ترحيل 190000)
        try {
            \Illuminate\Support\Facades\Artisan::call('permissions:sync-catalog');
        } catch (\Throwable $e) {
            // يكمله زرُّ «مزامنة كتالوج الصلاحيات» في شاشة تحديث النظام
        }
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    private function rename(array $map): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach ($map as $from => $to) {
            // الاسمُ الجديد موجودٌ أصلاً (أنشأته مزامنةُ الكتالوج): تُنقل روابطُ القديم إليه ثم يُحذف
            $fromId = DB::table('permissions')->where('name', $from)->where('guard_name', 'web')->value('id');
            $toId = DB::table('permissions')->where('name', $to)->where('guard_name', 'web')->value('id');

            if (! $fromId) {
                continue;
            }

            if (! $toId) {
                DB::table('permissions')->where('id', $fromId)->update(['name' => $to, 'updated_at' => now()]);
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $fromId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $toId, 'role_id' => $roleId]);
            }
            DB::table('role_has_permissions')->where('permission_id', $fromId)->delete();
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->where('permission_id', $fromId)->delete();
            }
            DB::table('permissions')->where('id', $fromId)->delete();
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الكاشُ يُبنى من جديد عند أوّل طلب
        }
    }
};
