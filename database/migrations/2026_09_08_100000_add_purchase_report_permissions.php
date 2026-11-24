<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ربطُ صلاحيات تقارير المشتريات الجديدة بمن كان يملك البوابة العامة.
 *
 * ══════════ لماذا مُهاجرٌ لا `permissions:sync` وحدَه ══════════
 *
 * `permissions:sync` يُنشئ الصلاحيةَ ولا يربطها بدورٍ واحد. ولمّا صارت
 * تقاريرُ المشتريات الثمانية تطلب صلاحيةً لكلٍّ منها بدل `purchases.reports.view`
 * وحدَها، كان الاكتفاءُ به سيُخفي بطاقاتِ اللوحة عن كلّ دورٍ في النظام
 * ويردّ مساراتِها بـ403 — بما فيهم المالك.
 *
 * فكلُّ دورٍ يملك البوابةَ العامة يُمنح الثمانيةَ: يبقى يرى ما كان يراه
 * بالضبط. والتفصيلُ بعدها بيد مدير النظام من شاشة الأدوار — صار في وسعه
 * منحُ أمينِ المستودع «المشتريات حسب البند» دون أرصدة الموردين وأعمار
 * ديونهم، وهو ما كان مستحيلاً حين كان الاسمُ واحداً للثمانية.
 *
 * نظيرُ `2026_09_07_100000_add_client_report_permissions_and_default_data_scopes`
 * في وحدة العملاء، وبالمنطق نفسِه.
 */
return new class extends Migration
{
    /**
     * مطابقةٌ حرفيةٌ لـ`PurchasesPermissions::groups()['reports']`.
     *
     * ولو اختلفتا لأنشأ `permissions:sync` صفّاً ثانياً بالاسم الصحيح
     * وتُرك صفُّ المُهاجر يتيماً مربوطاً بالأدوار وهو لا يحرس شيئاً.
     */
    private const REPORT_PERMISSIONS = [
        'purchases.reports.by_employee' => 'المشتريات حسب الموظف',
        'purchases.reports.by_supplier' => 'المشتريات حسب المورد',
        'purchases.reports.by_product' => 'المشتريات حسب المنتج',
        'purchases.reports.suppliers_directory' => 'دليل الموردين',
        'purchases.reports.debt_aging' => 'أعمار ديون الموردين',
        'purchases.reports.supplier_balances' => 'أرصدة الموردين',
        'purchases.reports.payments' => 'مدفوعات المشتريات',
        'purchases.reports.receiving' => 'المشتريات حسب البند (المستلم)',
    ];

    /** البوابةُ العامة التي كانت تحرس الثمانية مجتمعةً. */
    private const GATE_PERMISSION = 'purchases.reports.view';

    public function up(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $this->createPermissions();
        $this->linkToGateHolders();
    }

    /**
     * التراجعُ يفكّ الربطَ ويحذف الصلاحياتِ الجديدة.
     *
     * البوابةُ العامة لا تُمَسّ: هي سابقةٌ لهذا المُهاجر، وحذفُها يُغلق
     * فهرسَ التقارير في وجه الجميع.
     */
    public function down(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::REPORT_PERMISSIONS))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    private function tablesExist(): bool
    {
        return DB::getSchemaBuilder()->hasTable('permissions')
            && DB::getSchemaBuilder()->hasTable('roles')
            && DB::getSchemaBuilder()->hasTable('role_has_permissions');
    }

    /**
     * إنشاءُ الصلاحيات بالبيانات الوصفية التي تعرضها بها شاشةُ الأدوار.
     *
     * `module`/`group_key` وأخواتُها ليست زينة: الشاشةُ تُجمّع الصلاحيات
     * بها، وصفٌّ بلا وحدةٍ يسقط منها فلا يُمنح أبداً.
     */
    private function createPermissions(): void
    {
        $existing = DB::table('permissions')
            ->whereIn('name', array_keys(self::REPORT_PERMISSIONS))
            ->pluck('name')
            ->all();

        $now = now();
        $sort = 0;
        $rows = [];

        foreach (self::REPORT_PERMISSIONS as $name => $label) {
            $sort++;

            if (in_array($name, $existing, true)) {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'guard_name' => 'web',
                'module' => 'purchases',
                'module_label' => 'المشتريات',
                'group_key' => 'reports',
                'group_label' => 'تقارير المشتريات',
                'label' => $label,
                'sort_order' => 310000 + $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('permissions')->insert($rows);
        }
    }

    /** منحُ الثمانيةِ لكلّ دورٍ يملك البوابة — بقاءُ الحال على ما كان. */
    private function linkToGateHolders(): void
    {
        $gateId = DB::table('permissions')->where('name', self::GATE_PERMISSION)->value('id');

        if (! $gateId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $gateId)
            ->distinct()
            ->pluck('role_id');

        $newIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::REPORT_PERMISSIONS))
            ->pluck('id');

        if ($roleIds->isEmpty() || $newIds->isEmpty()) {
            return;
        }

        /* `role_has_permissions` بلا `id` ولا طوابعِ وقت — مفتاحُه مركَّب،
           وإدراجُ صفٍّ مكرَّرٍ فيه يرمي انتهاكَ مفتاحٍ أوّليٍّ يُسقط المُهاجر
           كلَّه. فالموجودُ يُستبعد قبل الإدراج لا بعده. */
        $existing = DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $newIds)
            ->get()
            ->map(fn ($row) => $row->role_id.':'.$row->permission_id)
            ->flip();

        $rows = [];

        foreach ($roleIds as $roleId) {
            foreach ($newIds as $permissionId) {
                if ($existing->has($roleId.':'.$permissionId)) {
                    continue;
                }

                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('role_has_permissions')->insert($chunk);
        }
    }
};
