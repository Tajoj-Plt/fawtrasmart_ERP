<?php

namespace Database\Seeders;

use App\Constants\permissions\StockPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * منح صلاحيات وحدة المخزون والتصنيع للأدوار.
 *
 * **ما كان** — وحدة المخزون **مغلقةٌ على الجميع**، صاحبَ النظام ومديرَه
 * ومشرفَه: `/stock/dashboard` يردّ ٤٠٣ لكل مستخدم في النظام.
 *
 * السبب أنّ خطوة المنح لم تُكتَب أصلاً. مسارُ الصلاحيات الجديدة ثلاث
 * خطوات:
 *
 *   ١. **الكتالوج** — [App\Constants\permissions\StockPermissions] يسمّي ١٣٢ صلاحية.
 *   ٢. **الإنشاء** — [PermissionTableSeeder] يقرأ الكتالوج ويُنشئ صفوفها
 *      في `permissions`. وهذا كان يعمل.
 *   ٣. **المنح**  — سيدرٌ يمنحها للأدوار. وهذا **لم يكن موجوداً**.
 *
 * فالصفوف الـ١٣٢ في الجدول ولا يملكها أحد: صفرُ أدوارٍ وصفرُ مستخدمين.
 * ومسارات الوحدة تطلبها صراحةً (`permission:stock.reports.view` وأخواتها)
 * فتردّ الجميع.
 *
 * وممّا يخفي العطل أنّ **بطاقة «إدارة المخزون» في لوحة التحكّم تظهر**:
 * شرطُها أسماءُ النظام القديم (`products_view_all_products` و
 * `inv_manage_inventory_permission_view`) وهي ممنوحةٌ فعلاً، بينما
 * المسارُ خلفها يطلب الأسماء الجديدة. فالبطاقة تدعو إلى بابٍ مغلق.
 *
 * **المنح إضافيٌّ لا مزامنة:** `givePermissionTo` لا `syncPermissions` —
 * إعادةُ تشغيل السيدر يجب ألّا تسحب ما ضبطه صاحب النظام من شاشة الأدوار.
 *
 * نظيرُ [SalesPermissionsSeeder] حرفاً بحرف، وهو الذي يمنح صلاحيات
 * المبيعات الـ١١٨ — ولذلك تعمل شاشات المبيعات ولا تعمل شاشات المخزون.
 *
 * **الترتيب مُلزِم:** بعد [PermissionTableSeeder] و[RolesSeeder].
 *
 * @see \App\Constants\permissions\StockPermissions كتالوج الأسماء
 * @see \Database\Seeders\SalesPermissionsSeeder النظير في المبيعات
 */
class StockPermissionsSeeder extends Seeder
{
    /**
     * الأدوار التي تأخذ كل صلاحيات عمليات المخزون.
     *
     * `employee` لا يُمنح شيئاً بالجملة: صلاحياته تُبنى من شاشة «الأدوار
     * الوظيفية» لكل وظيفة على حدة — أمينُ المستودع ليس كمن يعتمد الأذون،
     * وفصلُ الاعتماد عن التحرير أصلُ الضبط الداخلي في هذه الوحدة.
     *
     * ونطاقُ البيانات (`data_scope_*`) لا يُمنح هنا: محوره مستقلٌّ عن
     * العملية، ويمنحه [SalesPermissionsSeeder] مرّةً واحدةً للأدوار
     * نفسها — فمنحُه هنا ثانيةً تكرارٌ بلا أثر.
     */
    protected const FULL_ACCESS_ROLES = ['owner', 'manager', 'admin'];

    public function run(): void
    {
        $created = $this->ensurePermissions();

        /* الكاش يُفرَّغ قبل المنح: الصلاحيات المُنشأة قبل قليل لا تراها
           طبقة Spatie المخزَّنة، فيفشل `givePermissionTo` بحجّة أنها غير
           موجودة. */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->grantToRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            '  صلاحيات المخزون: %d إجمالاً، %d أُنشئت الآن.',
            count(StockPermissions::all()),
            $created
        ));
    }

    /**
     * إنشاء ما ينقص من الصلاحيات.
     *
     * `guard_name` صريح في الاستعلام وفي الإنشاء: مفتاح `permissions`
     * الفريد `(name, guard_name)`، فالمطابقة على الاسم وحده تُنشئ صفّاً
     * مكرّراً حين يختلف الحارس.
     *
     * ويُكرَّر ما يفعله [PermissionTableSeeder] عمداً: هذا السيدر قد
     * يُشغَّل وحده (`db:seed --class=StockPermissionsSeeder`) لإصلاح
     * وحدةٍ مغلقة على نظامٍ عامل، فلا يصحّ أن يعتمد على غيره ثمّ يمنح
     * صلاحياتٍ لا وجود لها.
     *
     * @return int عدد ما أُنشئ
     */
    protected function ensurePermissions(): int
    {
        $names = StockPermissions::all();

        $existing = Permission::where('guard_name', StockPermissions::GUARD)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $missing = array_diff($names, $existing);

        foreach ($missing as $name) {
            Permission::create([
                'name' => $name,
                'guard_name' => StockPermissions::GUARD,
            ]);
        }

        return count($missing);
    }

    /**
     * منح الصلاحيات للأدوار.
     *
     * الدور غير الموجود يُتخطّى بتحذير لا باستثناء: قد يُشغَّل هذا السيدر
     * قبل [RolesSeeder] على تركيبٍ قديم، وسقوطُه حينها يمنع زرع
     * الصلاحيات نفسها — وهي نافعة حتى بلا منحٍ فوري.
     */
    protected function grantToRoles(): void
    {
        $operations = StockPermissions::all();

        foreach (self::FULL_ACCESS_ROLES as $roleName) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($operations);

            $this->command?->info("  «{$roleName}»: مُنح ".count($operations).' صلاحية مخزون.');
        }
    }

    protected function findRole(string $name): ?Role
    {
        $role = Role::where('name', $name)
            ->where('guard_name', StockPermissions::GUARD)
            ->first();

        if (! $role) {
            $this->command?->warn("  الدور «{$name}» غير موجود — تُخطّي منحه. شغّل RolesSeeder أولاً.");
        }

        return $role;
    }
}
