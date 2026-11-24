<?php

namespace Database\Seeders;

use App\Constants\permissions\TaskManagerPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * منح صلاحيات وحدة إدارة المهام للأدوار.
 *
 * **لماذا هذا السيدر لازم؟**
 *
 * لأن حراسة الوحدة أُضيفت وهي مفتوحةٌ للجميع. وكانت `/ar/task-dashboard`
 * وأخواتها تُفتح لأي مستخدمٍ مسجَّل بلا شرط، فلمّا صارت تطلب
 * `permission.role:tasks.board.dashboard` رُدّ **الجميع** — صاحبَ النظام
 * ومديرَه ومشرفَه — لأن الصلاحية لا وجود لها في `permissions` ولا يملكها
 * دور.
 *
 * ولا مهرب من ذلك بالتفافٍ في الفحص: لا `Gate::before` يستثني أحداً في
 * هذا المشروع، و[\App\Models\User::hasRolePermission()] تقرأ من
 * `users.role_id` ← `role_has_permissions` وحدها — «مستخدمٌ بلا دور لا
 * يملك شيئاً»، ومَن دورُه لا يحمل الاسم فكذلك.
 *
 * فمسارُ أي صلاحيةٍ جديدة ثلاث خطوات، لا اثنتان:
 *
 *   ١. **الكتالوج** — [App\Constants\permissions\TaskManagerPermissions] يسمّي ٤٤ صلاحية.
 *   ٢. **الإنشاء** — صفوفها في `permissions` (هنا، أو `permissions:sync-catalog`).
 *   ٣. **المنح**  — إسنادها إلى الأدوار. وهي الخطوة التي يُنساها الناس،
 *      فتظهر الشاشة في القوائم ويردّ المسار خلفها ٤٠٣.
 *
 * نظيرُ [StockPermissionsSeeder] و[SalesPermissionsSeeder] حرفاً بحرف —
 * وقد كُتب أوّلهما لعلاج هذا العطل نفسه في وحدة المخزون.
 *
 * **المنح إضافيٌّ لا مزامنة:** `givePermissionTo` لا `syncPermissions` —
 * إعادةُ تشغيل السيدر يجب ألّا تسحب ما ضبطه صاحب النظام من شاشة الأدوار.
 *
 * **الترتيب مُلزِم:** بعد [PermissionTableSeeder] و[RolesSeeder].
 *
 * @see TaskManagerPermissions كتالوج الأسماء
 * @see StockPermissionsSeeder النظير في المخزون
 */
class TaskManagerPermissionsSeeder extends Seeder
{
    /**
     * الأدوار التي تأخذ كل صلاحيات إدارة المهام.
     *
     * `employee` لا يُمنح شيئاً بالجملة — وهو نصُّ [RolesSeeder] نفسه:
     * «لا يحصل على صلاحيات بالجملة؛ صلاحياته تُحدد حسب الوظيفة». فمن
     * يشارك في المهام تُمنح له صلاحياتُه من شاشة الأدوار، كما في كل وحدةٍ
     * أخرى في النظام.
     *
     * وذلك مقصودٌ هنا بوجهٍ خاصّ: `tasks.reports.performance` تقرأ إنجاز
     * كل عضوٍ على حدة، و`tasks.comments.delete_any` تمحو كلام غيره —
     * ومنحُهما لكل موظّفٍ بالجملة أسوأ من غياب الحراسة الذي كان.
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
            '  صلاحيات إدارة المهام: %d إجمالاً، %d أُنشئت الآن.',
            count(TaskManagerPermissions::all()),
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
     * يُشغَّل وحده (`db:seed --class=TaskManagerPermissionsSeeder`) لفتح
     * وحدةٍ مقفلة على نظامٍ عامل، فلا يصحّ أن يعتمد على غيره ثمّ يمنح
     * صلاحياتٍ لا وجود لها.
     *
     * @return int عدد ما أُنشئ
     */
    protected function ensurePermissions(): int
    {
        $names = TaskManagerPermissions::all();

        $existing = Permission::where('guard_name', TaskManagerPermissions::GUARD)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $missing = array_diff($names, $existing);

        foreach ($missing as $name) {
            Permission::create([
                'name' => $name,
                'guard_name' => TaskManagerPermissions::GUARD,
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
        $operations = TaskManagerPermissions::all();

        foreach (self::FULL_ACCESS_ROLES as $roleName) {
            $role = $this->findRole($roleName);

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($operations);

            $this->command?->info("  «{$roleName}»: مُنح ".count($operations).' صلاحية مهام.');
        }
    }

    protected function findRole(string $name): ?Role
    {
        $role = Role::where('name', $name)
            ->where('guard_name', TaskManagerPermissions::GUARD)
            ->first();

        if (! $role) {
            $this->command?->warn("  الدور «{$name}» غير موجود — تُخطّي منحه. شغّل RolesSeeder أولاً.");
        }

        return $role;
    }
}
