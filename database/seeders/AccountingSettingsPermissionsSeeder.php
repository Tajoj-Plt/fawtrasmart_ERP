<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات شاشتَي «الفترات المحاسبية» و«الحسابات المحاسبية».
 *
 * لم تُضمَّ إلى `settings_edit_general_settings` عمداً — تلك يملكها كل من
 * يغيّر شعار المنشأة، وهاتان تمسّان دفتر الأستاذ: الأولى تقرّر أي تاريخ
 * يُقبل فيه قيد، والثانية تقرّر — متى رُبطت بالنظام — إلى أي حساب يذهب
 * كل قيد. من يعدّل ألوان الواجهة يجب ألا يستطيع فتح سنة أُقفلت ولا تحويل
 * كل المبيعات إلى حساب آخر.
 *
 * التدرّج ثلاثي:
 *   view   — الاطّلاع، للمحاسبين.
 *   manage — الإنشاء والتعديل، لرئيس الحسابات.
 *   close / reopen / accounting_settings.edit — للمالك وحده.
 */
class AccountingSettingsPermissionsSeeder extends Seeder
{
    /** صلاحيات القراءة والترتيب — للمالك والمدير. */
    protected const SHARED = [
        'settings.accounting_periods.view',
        'settings.accounting_periods.manage',
        'settings.accounting_periods.close',
        // الاطّلاع على ربط العمليات بالحسابات — للمحاسبين
        'settings.accounting_settings.view',
    ];

    /** الصلاحيات التي لا تُمنح إلا للمالك. */
    protected const OWNER_ONLY = [
        // إعادة فتح فترة مقفلة تعيد فتح باب أُغلق عمداً على شهر مُراجَع
        'settings.accounting_periods.reopen',
        // تجاوز القفل عند الإدخال
        'settings.accounting_periods.override',
        // تغيير حساب عملية يغيّر — متى رُبطت الطبقة — وجهة كل قيد لاحق لها
        'settings.accounting_settings.edit',
    ];

    public function run(): void
    {
        foreach ([...self::SHARED, ...self::OWNER_ONLY] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->grant('owner', [...self::SHARED, ...self::OWNER_ONLY]);
        $this->grant('manager', self::SHARED);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('صلاحيات الإعدادات المحاسبية: '
            .(count(self::SHARED) + count(self::OWNER_ONLY)).' صلاحية.');
    }

    /**
     * منح صلاحيات لدور دون سحب ما مُنح سابقاً.
     *
     * @param  array<int, string>  $permissions
     */
    protected function grant(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->command?->warn("الدور «{$roleName}» غير موجود — تُخطّى صلاحياته.");

            return;
        }

        $role->givePermissionTo($permissions);
    }
}
