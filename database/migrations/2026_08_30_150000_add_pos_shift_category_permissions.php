<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيتا «تصنيفات الورديات» — إلى الأدوار التي تملك نظيرَهما على الورديات.
 *
 * ─────────────────── لماذا صلاحيةٌ مستقلّة ───────────────────
 *
 * شاشةُ التصنيفات وُلدت تتبع `pos.shifts.*`. وهو خلطُ مستويين: الوردياتُ
 * تُنشأ كلَّ أسبوع ويُعدَّل وقتُها كلَّ موسم، والتصنيفاتُ تُضبط مرّةً ثم
 * تُترك سنين. فمشرفُ الفرع يحتاج الأولى ولا يلزم أن يملك الثانية.
 *
 * ─────────────────── والخطرُ في ذلك ───────────────────
 *
 * `permissions:sync` يُنشئ الصلاحية في `permissions` و**لا يربطها بدور**،
 * و`hasPermission()` تقرأ دورَ المستخدم (`role_id` ← `role_has_permissions`)
 * لا Spatie المباشرة. فالنتيجةُ المعروفة في هذا المستودع — انظر
 * `2026_08_27_130000_grant_sales_order_and_debit_note_permissions` — أنّ
 * الشاشةَ تُقفَل على من كان يفتحها بالأمس: الرابطُ مخفيّ والصفحةُ 403.
 *
 * ─────────────────── المنح بالنظير لا بالتعميم ───────────────────
 *
 *     pos.shift_categories.view   ← كلّ دورٍ يملك pos.shifts.view
 *     pos.shift_categories.manage ← كلّ دورٍ يملك pos.shifts.manage
 *
 * فالدورُ الذي لا يملك إلّا العرضَ على الورديات لا يأخذ إلّا العرض. ولا
 * دورَ يكسب بهذا الترحيل قدرةً لم تكن له: التصنيفاتُ كانت تُدار بـ
 * `pos.shifts.*` نفسِها إلى الآن، فهذا نقلُ ما كان لا توسيعُه.
 *
 * وما يُمنَح هنا يبقى قابلاً للسحب من شاشة الأدوار كأيّ صلاحية.
 *
 * `down()` تسحب الارتباطات وحدها ولا تحذف الصلاحيتين: مصدرُهما كتالوج
 * `PosPermissions`، والتراجعُ عن ترحيلٍ ليس إلغاءً للميزة.
 */
return new class extends Migration
{
    /** الصلاحية الجديدة ⇐ نظيرُها الذي يُشتقّ منه الدور. */
    private const MAPPING = [
        'pos.shift_categories.view' => 'pos.shifts.view',
        'pos.shift_categories.manage' => 'pos.shifts.manage',
    ];

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::MAPPING as $name => $counterpart) {
            $permissionId = $this->ensurePermission($name);

            $counterpartId = DB::table('permissions')
                ->where('name', $counterpart)
                ->value('id');

            if (! $counterpartId) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $counterpartId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::MAPPING))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        }

        $this->forgetPermissionCache();
    }

    /**
     * الصلاحيةُ موجودةً — يُنشئها الترحيلُ ولا ينتظر `permissions:sync`.
     *
     * الخوادمُ المشتركة كثيرٌ منها بلا سطر أوامر، وميزةٌ تنتظر أمراً
     * يدوياً ميزةٌ لا تصل.
     */
    private function ensurePermission(string $name): int
    {
        $id = DB::table('permissions')
            ->where('name', $name)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => self::GUARD,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
