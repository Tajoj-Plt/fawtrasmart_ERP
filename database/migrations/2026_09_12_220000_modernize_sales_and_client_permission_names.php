<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * ما تبقّى من النظام القديم في المبيعات والعملاء والمشتريات.
 *
 * ═════════════════════ إعادةُ تسميةٍ لا حذف ═════════════════════
 *
 * الفرقُ عن [الترحيل 2026_09_12_210000] جوهريّ: تلك كانت أسماءً **ميتة** لا
 * يقرؤها أحد، فحُذفت. وهذه أسماءٌ **حيّة** تحرس مساراتٍ وقوائم، وعيبُها
 * الشكلُ لا المعنى — مسطَّحةٌ بنمط `job_roles` بين أخواتٍ منقوطة، أو شاذّةٌ
 * عن كل نمط. فتُعاد تسميتُها في مكانها.
 *
 * وإعادةُ التسمية **لا تمسّ أدواراً**: `role_has_permissions` يربط
 * بالمعرّف لا بالاسم، فتغييرُ `permissions.name` يُبقي كلَّ منحٍ قائماً.
 * ولذلك لا حاجة إلى خطوة منحٍ هنا كما كانت هناك.
 *
 *   sales_targets_add    ← إدارةُ الأهداف كلَّها (إضافةً وتعديلاً وحذفاً)
 *                          كما تشهد مساراتُها الستّة، فصارت `manage` لا
 *                          `create`: الاسمُ يقول ما يفعله.
 *   sales_view_reports   ← بوابةُ تقارير المبيعات، انضمّت إلى مجموعة
 *                          `reports` مع التقارير الأربعة عشر.
 *   Approve_Client_Status ← وحدَها بحرفٍ كبير وترتيبٍ معكوس في النظام كلِّه.
 *
 * ومجموعتا «(النظام القديم)» في كتالوج المبيعات زالتا معها، فلم يبقَ في
 * المبيعات ولا المشتريات اسمٌ مسطَّح واحد.
 *
 * ═════════════════════ وثلاثةٌ تُحذف ═════════════════════
 *
 * `clients_view_all_reports` و`clients_view_own_reports`: صلاحيتا **نطاق**
 * لا عملية — «كل العملاء» مقابل «عملائه هو» — وهو بعينه انفجارُ الأسماء
 * الذي وُجد `data_scope_*` لإنهائه. مواضعُها الخمسة نُقلت أولاً: بوابةُ
 * اللوحة إلى `clients_dashboard_view`، وشاشةُ الأهداف إلى
 * `clients.reports.targets`، وبوابةُ التقارير إلى
 * `client_reports_view|clients.reports.view` — وكلُّها كانت في نفس
 * البدائل أصلاً، فلا يفقد حاملُها شيئاً.
 *
 * `purchases.order_requests.convert`: منقوطةُ الاسم لكنها ميتةُ المعنى —
 * الفعلُ معطَّلٌ منذ أن صار الطريق يمرّ على «استلام المشتريات»، والدالّةُ
 * لا تفعل إلا إظهارَ رسالة. المسارُ باقٍ على `purchases.order_requests.view`
 * فلا يُكسر رابطٌ قديم.
 *
 * ومع ذلك يُمنح `clients.reports.view` لكلّ دورٍ كان يحمل إحدى صلاحيتَي
 * النطاق المحذوفتين: الاحتياطُ أرخص من مديرٍ يفقد بوابةَ التقارير.
 *
 * `down()` لا تعكس: أسماءُ الصلاحيات تُقرأ من الشيفرة، والشيفرةُ رجعت
 * بالنسخة لا بالترحيل.
 */
return new class extends Migration
{
    /** الاسم القديم ← الجديد. الصفُّ نفسُه يُعاد تسميتُه، فالأدوار محفوظة. */
    private const RENAMES = [
        'sales_targets_add' => 'sales.targets.manage',
        'sales_targets_settle' => 'sales.targets.settle',
        'sales_view_reports' => 'sales.reports.view',
        'Approve_Client_Status' => 'clients_approve_status',
    ];

    /** ما يُحذف، ومَن يرثه من الأدوار (أو `null` إن لم يكن له وارث). */
    private const RETIRED = [
        'clients_view_all_reports' => 'clients.reports.view',
        'clients_view_own_reports' => 'clients.reports.view',
        'purchases.order_requests.convert' => null,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $this->rename();
        $this->retire();

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        // لا رجعة — انظر ديباجة الملف
    }

    /**
     * إعادةُ التسمية في مكانها.
     *
     * تُتخطّى إن كان الاسمُ الجديد موجوداً أصلاً: صفّان بنفس الاسم يكسران
     * فهرسَ `permissions.name` الفريد، والوجودُ المسبق يعني أن الترحيل جرى.
     */
    private function rename(): void
    {
        foreach (self::RENAMES as $from => $to) {
            $exists = DB::table('permissions')->where('name', $to)->exists();

            if ($exists) {
                continue;
            }

            DB::table('permissions')
                ->where('name', $from)
                ->update(['name' => $to, 'updated_at' => now()]);
        }
    }

    /** الحذف — بعد توريث الأدوار حيث للاسم وارث. */
    private function retire(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys(self::RETIRED))->pluck('id', 'name');

        if ($ids->isEmpty()) {
            return;
        }

        $rows = [];

        foreach (self::RETIRED as $name => $successor) {
            $id = $ids[$name] ?? null;

            if (! $id || ! $successor) {
                continue;
            }

            $successorId = DB::table('permissions')->where('name', $successor)->value('id');

            if (! $successorId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $id)->pluck('role_id') as $roleId) {
                $rows[$successorId.'-'.$roleId] = ['permission_id' => $successorId, 'role_id' => $roleId];
            }
        }

        if ($rows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore(array_values($rows));
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids->values())->delete();

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->whereIn('permission_id', $ids->values())->delete();
        }

        DB::table('permissions')->whereIn('id', $ids->values())->delete();
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
