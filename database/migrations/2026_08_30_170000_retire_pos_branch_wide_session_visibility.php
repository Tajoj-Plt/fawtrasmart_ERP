<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * إلغاءُ «نطاق رؤية الجلسات» — من كل دور، لا من دور «موظف» وحده.
 *
 * ═════════════════════ لماذا الإلغاء لا التضييق ═════════════════════
 *
 * `points_sale_view_all_sessions` كانت مرتبةً وسطى بين «كلُّ شيء» و«ما
 * أنشأتُه»: تفتح **جلسات الفرع كاملةً**.
 *
 * والفرعُ ليس وحدةَ مسؤولية. كاشيرٌ وزميلُه في فرعٍ واحدٍ لا يرأس أحدُهما
 * الآخر، وعُهدةُ كلٍّ منهما تخصّه وحده. فمرتبةٌ تُعرّف الرؤيةَ بالمكان لا
 * بالمسؤولية تُسرّب بالضرورة، لا بالخطأ.
 *
 * وقد وقع ذلك: مُنحت الخانةُ لدور «موظف» بتعميمٍ واحد في شاشة الأدوار،
 * فصار كلُّ كاشيرٍ يرى عهدةَ زملائه كلِّهم. وسحبُها من ذلك الدور
 * ([الترحيل 2026_08_30_140000]) عالج الحالةَ ولم يعالج السبب: الخانةُ
 * باقيةٌ في الشاشة، ونقرةٌ واحدةٌ تُعيد كلَّ شيء.
 *
 * فأُلغيت المرتبةُ من الشيفرة (`ScopesToPosAccess::scopeToVisibleSessions`)،
 * ومن الكتالوج (`PosPermissions`) فلا تظهر خانتُها، ومن المسارات. وهذا
 * الترحيلُ يُتمّ ذلك في البيانات.
 *
 * ═════════════════════ والقاعدةُ الباقية ═════════════════════
 *
 *   `data_scope_all` ⇒ بلا قيد (المدير العام)
 *   وإلّا            ⇒ ما أنشأه هو ومن يديرهم إدارياً
 *
 * وهي نفسُها الآن في الجلسات، وحركات الدرج، وعدّاد «بانتظار التأكيد»،
 * ولوحة المؤشرات، وتقارير نقاط البيع الأربعة.
 *
 * ═════════════════════ ما لا يُمسّ ═════════════════════
 *
 * صلاحياتُ **الفعل** تبقى كما هي: من يُغلق، ومن يؤكّد، ومن يعتمد حركةَ
 * درج. الملغى هو صلاحيةُ **الرؤية** وحدها — والفرقُ جوهريّ: المُعتمِدُ
 * يبلغ ما يعتمده عبر شجرة الإدارة، لا عبر خانةٍ تفتح له الفرع.
 *
 * ═════════════════════ عن الصفّ في `permissions` ═════════════════════
 *
 * تُحذف **الارتباطاتُ** ولا يُحذف الصفّ: اسمُه مذكورٌ في
 * `JobRole::$job_roles` (النظام القديم) و`permissions:sync` يُعيد إنشاءه.
 * وحذفُه هنا ثم عودتُه هناك عبثٌ متكرّر. والصفُّ بلا ارتباطٍ وبلا مدخلٍ في
 * الكتالوج لا يظهر في شاشة الأدوار ولا يقرأه سطرٌ واحد.
 *
 * `down()` لا تُعيد المنح. لا تستطيع: الترحيلُ لا يعرف أيَّ الأدوار كانت
 * تحملها قبله، والشيفرةُ لم تعد تقرأ الصلاحيةَ أصلاً فإعادةُ المنح لا
 * تُعيد سلوكاً.
 */
return new class extends Migration
{
    private const PERMISSION = 'points_sale_view_all_sessions';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        if (! $id) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $id)->delete();

        // وارتباطُها المباشر بمستخدم إن وُجد — نفسُ الصلاحية من بابٍ آخر
        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        // لا رجعة — انظر ديباجة الملف
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
