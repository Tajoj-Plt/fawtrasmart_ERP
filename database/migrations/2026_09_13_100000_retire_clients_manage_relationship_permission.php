<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * تقاعدُ `clients_manage_relationship` — «إدارة علاقات العملاء وارتباطاتهم».
 *
 * كان لشاشة إدارة علاقات العملاء اسمان: المسارُ و`CRMController` يقرآن
 * `clients_crm_view`، ورابطُ القائمة الجانبية وحده يقرأ هذا الاسم (وبـ`@can`).
 * فمن فعّل إحداهما للدور لم يرَ شيئاً: الأولى لا تُظهر الرابط، والثانية
 * تُظهره ثم يرفض المسار. ولا يحرس هذا الاسمُ شيئاً غير ذلك الرابط.
 *
 * الترتيبُ نفسُه في `2026_09_12_210000_retire_legacy_job_role_permissions`:
 * حُوّل الرابطُ إلى `clients_crm_view` أولاً، ثم يُمنح لكل دورٍ يحمل القديم،
 * ثم يُحذف القديم — فلا يفقد أحدٌ وصولاً كان يملكه.
 *
 * `down()` لا تُعيد شيئاً: لا شيء في الشيفرة يقرأ الاسمَ بعد الآن.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $legacyId = DB::table('permissions')->where('name', 'clients_manage_relationship')->value('id');
        $successorId = DB::table('permissions')->where('name', 'clients_crm_view')->value('id');

        // بلا مقابلٍ لا حذف — وإلّا سُحب الوصولُ ممّن يملكه
        if (! $legacyId || ! $successorId) {
            return;
        }

        $roleRows = DB::table('role_has_permissions')
            ->where('permission_id', $legacyId)
            ->pluck('role_id')
            ->map(fn ($roleId) => ['permission_id' => $successorId, 'role_id' => $roleId])
            ->all();

        foreach (array_chunk($roleRows, 200) as $chunk) {
            DB::table('role_has_permissions')->insertOrIgnore($chunk);
        }

        if (Schema::hasTable('model_has_permissions')) {
            $modelRows = DB::table('model_has_permissions')
                ->where('permission_id', $legacyId)
                ->get(['model_type', 'model_id'])
                ->map(fn ($row) => ['permission_id' => $successorId, 'model_type' => $row->model_type, 'model_id' => $row->model_id])
                ->all();

            foreach (array_chunk($modelRows, 200) as $chunk) {
                DB::table('model_has_permissions')->insertOrIgnore($chunk);
            }

            DB::table('model_has_permissions')->where('permission_id', $legacyId)->delete();
        }

        DB::table('role_has_permissions')->where('permission_id', $legacyId)->delete();
        DB::table('permissions')->where('id', $legacyId)->delete();

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // لا رجعة — انظر ديباجة الملف
    }
};
