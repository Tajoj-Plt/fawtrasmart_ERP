<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات المهام المجدولة والنسخ الاحتياطي وتحديث النظام.
 *
 * لم تُضَف إلى `settings_edit_general_settings` عمداً: تلك الصلاحية يملكها
 * كل من يعدّل شعار المنشأة، وهذه تشغّل أوامر على الخادم وتنزّل قاعدة
 * البيانات كاملة وتكتب فوقها. جمعُهما في صلاحية واحدة يعني أن من يغيّر
 * لون الواجهة يستطيع محو قاعدة البيانات.
 *
 * أخطر ثلاث صلاحيات — التنزيل والاسترجاع والحذف — تُمنح لدور `owner` وحده
 * ابتداءً. الباقي يُمنح للمدراء أيضاً.
 */
class SystemMaintenancePermissionsSeeder extends Seeder
{
    /** صلاحيات القراءة والتشغيل — تُمنح للمالك والمدير. */
    protected const SHARED = [
        'settings.cron.view',
        'settings.cron.create',
        'settings.cron.update',
        'settings.cron.run',
        'settings.cron.logs',
        'settings.backup.view',
        'settings.backup.create',
        // شاشة «تحديث النظام». ليست في `OWNER_ONLY` عمداً: من يرفع ملفّات
        // التحديث إلى الخادم هو من يشغّل ترحيلاتها، وهو في هذا التركيب
        // `manager` لا `owner`. وحصرُها في دورٍ لا مستخدمَ له يجعل الشاشة
        // موجودةً لا تُفتَح — فيعود التحديث إلى سطر الأوامر، وهو الغياب
        // الذي أُنشئت الشاشة لسدّه.
        //
        // وهي دون «الاسترجاع» خطراً: الترحيل هنا إلى الأمام فقط بلا تراجع
        // ولا مسح، ومهامّ البيانات محصورة في `SystemUpdateRegistry` وكلّها
        // تُضيف أو تحدّث صفوفاً ثابتة. لنقلها إلى المالك وحده: انقل الأسطر
        // الثلاثة إلى `OWNER_ONLY` أدناه وأعد تشغيل هذا السيدر — الصلاحية
        // الممنوحة لا تُسحَب تلقائياً، فاسحبها من شاشة الأدوار.
        'settings.system_update.view',
        'settings.system_update.migrate',
        'settings.system_update.seed',
        // شاشة «مسح الذاكرة المؤقتة». مشتركةٌ لا مقصورةٌ على المالك: المسح
        // لا يُتلف بياناً — الكاش نسخةٌ ثانية يعيد النظام بناءها وحدَه —
        // وحصرُه في دورٍ واحد يعيد أشيعَ إصلاحٍ بعد التحديث إلى سطر الأوامر.
        'settings.cache.view',
        'settings.cache.clear',
    ];

    /**
     * وضع الصيانة — للمالك والمدير والمشرف.
     *
     * ليس في `SHARED` لأن `SHARED` تُمنح لـ`owner` و`manager` وحدهما، و`admin`
     * يحتاجها هنا: من يقفل النظام لاسترجاع نسخةٍ هو نفسُه من يستعيدها، وشاشة
     * الاسترجاع مفتوحةٌ لـ`admin`. وقفلٌ لا يملك المسؤولُ عن العملية إطفاءه
     * قفلٌ على العملية نفسها.
     *
     * وهي مزروعةٌ كذلك في ترحيل `create_maintenance_mode_settings_table` —
     * عمداً: صلاحيةٌ تُنشَأ ولا تُربط بدور تبقى مخفيّةً عن الجميع حتى يُشغَّل
     * هذا السيدر، ووضعُ الصيانة أسوأ موضعٍ لذلك. والزرعان `firstOrCreate` كلاهما
     * فلا يتضاعف شيء.
     */
    protected const MAINTENANCE_MODE = [
        'settings.maintenance_mode.view',
        'settings.maintenance_mode.manage',
    ];

    /** الصلاحيات الحسّاسة — للمالك وحده. */
    protected const OWNER_ONLY = [
        'settings.cron.delete',
        'settings.backup.download',
        // التصدير استخراج بيانات خام (أرصدة العملاء، رواتب الموظفين) في
        // ملفّ يخرج من النظام — طبقة الخطر نفسها التي للتنزيل، لا طبقة
        // التقارير
        'settings.backup.export',
        'settings.backup.delete',
        'settings.backup.restore',
        'settings.backup.settings',
    ];

    public function run(): void
    {
        foreach ([...self::SHARED, ...self::OWNER_ONLY, ...self::MAINTENANCE_MODE] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->grant('owner', [...self::SHARED, ...self::OWNER_ONLY, ...self::MAINTENANCE_MODE]);
        $this->grant('manager', [...self::SHARED, ...self::MAINTENANCE_MODE]);
        $this->grant('admin', self::MAINTENANCE_MODE);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('صلاحيات المهام والنسخ الاحتياطي: '
            .(count(self::SHARED) + count(self::OWNER_ONLY) + count(self::MAINTENANCE_MODE)).' صلاحية.');
    }

    /**
     * منح صلاحيات لدور — دون سحب ما مُنح سابقاً.
     *
     * `givePermissionTo` لا `syncPermissions`: الأخير يمحو كل صلاحيات
     * الدور الأخرى، وتشغيل هذا السيدر يجب ألا يُفقد أحداً شيئاً.
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
