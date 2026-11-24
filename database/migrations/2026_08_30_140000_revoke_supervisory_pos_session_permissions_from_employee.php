<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * سحبُ صلاحيات الجلسات الرقابية من دور «موظف».
 *
 * ═════════════════════ العَرَض ═════════════════════
 *
 * كاشيرٌ يفتح `/POS/session` فيرى جلسةَ زميلٍ ليس تابعاً له ولا يديره.
 *
 * ═════════════════════ والسبب ═════════════════════
 *
 * ليس في الشيفرة. `ScopesToPosAccess::scopeToVisibleSessions()` صحيحة:
 *
 *     data_scope_all                ⇒ كلُّ شيء
 *     points_sale_view_all_sessions ⇒ جلساتُ الفرع كاملةً
 *     وإلّا                          ⇒ جلساتُه هو ومن يديرهم إدارياً
 *
 * والعطلُ أنّ الدورَ الافتراضيَّ «employee» يحمل
 * `points_sale_view_all_sessions` — فلا يبلغ الفرعَ الثالث قطّ، ويقع كلُّ
 * موظّفٍ في الفرع الثاني: جلساتُ الفرع كاملةً.
 *
 * و`RolesSeeder` لا يمنح `employee` شيئاً بالجملة (`full_access => false`).
 * فالمنحُ جاء من خارجه — تعميمٌ يدويٌّ من شاشة الأدوار على الأرجح.
 *
 * ═════════════════════ وأخطرُ من الرؤية ═════════════════════
 *
 * الدورُ نفسُه يحمل `points_sale_confirm_close_sessions_all`. وهذه تنقض
 * ضبطاً داخلياً بناه النظام صراحةً — `authorizeSessionConfirm()`:
 *
 *     «الكاشير لا يؤكّد إقفال عهدته بنفسه. من يُغلق ليس من يؤكّد.»
 *
 * الدالّةُ ترفض `..._own` وتشترط `..._all` تحديداً لهذا الغرض. ومنحُ
 * `..._all` لكلِّ موظّفٍ يجعل كلَّ كاشيرٍ مؤكِّدَ إقفالِ نفسِه — وفرقُ
 * الصندوق يُعتمد بلا عينٍ ثانية.
 *
 * ═════════════════════ ما يُسحب وما يبقى ═════════════════════
 *
 * تُسحب الأربعُ الرقابية وحدها. وتبقى نظائرُها `..._own` كما هي، فالكاشيرُ
 * يفتح جلسته ويغلقها ويراها — ولا يتغيّر عملُه اليوميّ في شيء.
 *
 * ═════════════════════ ودورُ «employee» وحده ═════════════════════
 *
 * لا تُمسّ `owner` و`manager` و`admin` — الرقابةُ عملُها. ولا أيُّ دورٍ
 * أنشأه العميل بنفسه: دورٌ اسمُه «مشرف وردية» صنعه صاحبُ النظام ومنحه
 * هذه الصلاحيات قصدَها قصداً، وسحبُها منه تخريبُ ضبطٍ مقصود.
 *
 * `down()` تُعيد ما سُحب بالضبط — ولا تلمس ما لم يكن ممنوحاً أصلاً.
 */
return new class extends Migration
{
    /**
     * الصلاحياتُ الرقابية على الجلسات.
     *
     * كلُّها بلاحقة `_all`: تعني «على جلسات الآخرين» لا «على جلساتي». وهي
     * أربعٌ لأنّ دورةَ الجلسة أربعُ مراحل — فتحٌ وعرضٌ وإغلاقٌ وتأكيد — ولكلٍّ
     * منها نظيرٌ خاصٌّ يبقى للموظّف.
     */
    private const SUPERVISORY = [
        'points_sale_view_all_sessions',
        'points_sale_open_sessions_all',
        'points_sale_close_sessions_all',
        'points_sale_confirm_close_sessions_all',
    ];

    private const ROLE = 'employee';

    public function up(): void
    {
        if (! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('name', self::ROLE)->value('id');

        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::SUPERVISORY)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('name', self::ROLE)->value('id');

        if (! $roleId) {
            return;
        }

        $rows = DB::table('permissions')
            ->whereIn('name', self::SUPERVISORY)
            ->pluck('id')
            ->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])
            ->all();

        foreach ($rows as $row) {
            $exists = DB::table('role_has_permissions')
                ->where($row)
                ->exists();

            if (! $exists) {
                DB::table('role_has_permissions')->insert($row);
            }
        }

        $this->forgetPermissionCache();
    }

    /**
     * كاشُ Spatie يُبقي الصلاحيةَ سارية بعد حذف صفّها.
     *
     * `hasRolePermission()` تستعلم القاعدةَ مباشرةً فلا يعنيها الكاش، لكنّ
     * بقيّةَ النظام (`@can`، وسائطُ Spatie) تقرأ منه — فيبقى الزرُّ ظاهراً
     * والصلاحيةُ مسحوبة.
     */
    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
