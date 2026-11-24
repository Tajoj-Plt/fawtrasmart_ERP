<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «قوالب الرواتب» أُلغيت (2026-09-24) — تُحذف صلاحياتُها الخمس.
 *
 * بنودُ الراتب تُضاف مباشرةً في العقد، ومساراتُ `SalaryTemplates.*` صارت
 * تحويلاً إلى «بنود الرواتب» محروساً بـ`salary_items.index`. فلم يعد في
 * الشيفرة من يسأل `salary_templates.*`، ومجموعتُها أُزيلت من
 * `App\Constants\permissions\HrPermissions` — وكانت ستبقى في شاشة الأدوار
 * صلاحياتٍ يتيمةً بلا أثر.
 *
 * الحذفُ بالأسماء صراحةً مع روابطها في `role_has_permissions` و
 * `model_has_permissions`. **لا يُمسّ** جدولُ `salary_template` ولا صفوفُه،
 * ولا `salary_items.salary_template_id`، ولا `contracts.salary_temp_id`.
 *
 * `down()` لا تعيد شيئاً: الشاشاتُ التي كانت تفحصها لم تعد موجودة.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const RETIRED = [
        'salary_templates.index',
        'salary_templates.show',
        'salary_templates.create',
        'salary_templates.edit',
        'salary_templates.destroy',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function () {
            $ids = DB::table('permissions')
                ->whereIn('name', self::RETIRED)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return;
            }

            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            }
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('id', $ids)->delete();
        });

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الذاكرةُ المؤقتة تنتهي وحدها — لا يُفشَل الترحيلُ لأجلها
        }
    }

    public function down(): void
    {
        // لا استرجاع: «قوالب الرواتب» أُلغيت ولا شاشةَ تفحص هذه الصلاحيات.
    }
};
