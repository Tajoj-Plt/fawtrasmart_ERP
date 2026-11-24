<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ربطُ صلاحيات تقارير العملاء الجديدة، وإظهارُ نطاقِ الرؤية للأدوار التي بلا نطاق.
 *
 * ══════════ لماذا مُهاجرٌ لا `permissions:sync` وحدَه ══════════
 *
 * `permissions:sync` يُنشئ الصلاحيةَ في `permissions` **ولا يربطها بدورٍ
 * واحد**. فلو اكتُفي به لصار كلُّ تقارير العملاء التسعة محجوباً عن كلّ
 * دورٍ في النظام لحظةَ النشر — بما فيهم المالك — لأنّ متحكّماتها صارت
 * تطلب `clients.reports.*` بدل التركيبة الثلاثية القديمة.
 *
 * فالربطُ هنا: كلُّ دورٍ كان يملك إحدى صلاحيات التقارير القديمة يُمنح
 * الصلاحياتِ الجديدةَ كلَّها — أي يبقى يرى ما كان يراه بالضبط، لا أقلَّ
 * ولا أكثر. والتفصيلُ بعد ذلك بيد مدير النظام من شاشة الأدوار: صار في
 * وسعه منحُ المندوب «مواعيد العملاء» دون «أرصدة العملاء»، وهو ما كان
 * مستحيلاً حين كان الاسمُ واحداً للتسعة.
 *
 * ══════════ ونطاقُ الرؤية ══════════
 *
 * دوران هنا («مندوب مبيعات» و«مشرف») لا يملكان أيَّ صلاحية `data_scope_*`
 * إطلاقاً. وقد صارت التقاريرُ تقرأ هذا المحور فعلاً عبر
 * {@see \App\Support\DataScope}، وحكمُها فيمن لا نطاقَ له هو الأضيق
 * (`own`) — صلاحيةٌ ناقصةٌ تُغلق لا تفتح.
 *
 * فيُسنَد النطاقُ صراحةً بدل أن يبقى ضمنياً: ما يراه المستخدم يصير
 * مقروءاً في شاشة الأدوار وقابلاً للتعديل منها، بدل أن يكون سلوكاً
 * مدفوناً في الشيفرة لا يفسّره شيءٌ في الواجهة.
 *
 * و«مشرف» يُمنح `data_scope_team` لا `own`: هو الوصفُ الحرفيُّ للصلاحية
 * في {@see \App\Constants\permissions\DataScopePermissions} («بيانات فريقه
 * — المشرف»)، وقصرُه على نفسه كان سيُسقط عنه مرؤوسيه وهم عملُه كلُّه.
 *
 * ولا يُمَسّ دورٌ يملك نطاقاً أصلاً — لا توسيعاً ولا تضييقاً.
 */
return new class extends Migration
{
    /**
     * الصلاحياتُ الجديدة: المفتاحُ الاسمُ، والقيمةُ التسمية العربية.
     *
     * مطابقةٌ حرفياً لـ`ClientsPermissions::groups()['reports']` — ولو
     * اختلفتا لأنشأ `permissions:sync` صفّاً ثانياً بالاسم الصحيح وتُرك
     * صفُّ المُهاجر يتيماً مربوطاً بالأدوار وهو لا يحرس شيئاً.
     */
    private const REPORT_PERMISSIONS = [
        'clients.reports.view' => 'الدخول إلى تقارير العملاء (فهرس التقارير)',
        'clients.reports.balances' => 'تقرير أرصدة العملاء',
        'clients.reports.debts' => 'تقرير مديونية العملاء',
        'clients.reports.debt_aging' => 'تقرير أعمار الديون',
        'clients.reports.statement' => 'كشف حساب العميل',
        'clients.reports.guide' => 'دليل العملاء والخريطة',
        'clients.reports.appointments' => 'تقرير مواعيد العملاء',
        'clients.reports.installments' => 'تقرير أقساط العملاء',
        'clients.reports.targets' => 'تقرير أهداف العملاء',
        'clients.reports.export' => 'تصدير تقارير العملاء',
    ];

    /** الأسماءُ القديمة التي كانت تحرس التقارير التسعة مجتمعةً. */
    private const LEGACY_REPORT_PERMISSIONS = [
        'clients_view_all_reports',
        'clients_view_own_reports',
        'client_reports_view',
    ];

    public function up(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $this->createReportPermissions();
        $this->linkReportPermissionsToLegacyRoles();
        $this->assignDefaultDataScopes();
    }

    /**
     * التراجعُ يفكّ الربطَ ويحذف الصلاحياتِ الجديدة، ولا يمسّ `data_scope_*`.
     *
     * إسنادُ النطاق لا يُتراجَع عنه: نزعُه يُعيد الدورَ إلى «بلا نطاق»،
     * وهي حالةٌ صارت تعني `own` ضمنياً — فالتراجعُ لا يُرجع شيئاً وإنّما
     * يُخفي ما صار ظاهراً. وسحبُ صلاحيةٍ ممنوحةٍ عن قصدٍ بعد المُهاجر
     * أسوأُ: التراجعُ عن مُهاجرٍ لا يجوز أن يغيّر قرارَ مدير نظام.
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
     * `module`/`group_key` وأخواتُها ليست زينة: شاشةُ الأدوار تُجمّع
     * الصلاحياتِ بها، وصفٌّ بلا وحدةٍ يسقط من الشاشة فلا يُمنح أبداً.
     */
    private function createReportPermissions(): void
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
                'module' => 'clients',
                'module_label' => 'العملاء',
                'group_key' => 'reports',
                'group_label' => 'تقارير العملاء',
                'label' => $label,
                'sort_order' => 300000 + $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('permissions')->insert($rows);
        }
    }

    /**
     * منحُ الجديدةِ لكلّ دورٍ يملك إحدى القديمة — بقاءُ الحال على ما كان.
     */
    private function linkReportPermissionsToLegacyRoles(): void
    {
        $legacyIds = DB::table('permissions')
            ->whereIn('name', self::LEGACY_REPORT_PERMISSIONS)
            ->pluck('id');

        if ($legacyIds->isEmpty()) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->whereIn('permission_id', $legacyIds)
            ->distinct()
            ->pluck('role_id');

        $newIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::REPORT_PERMISSIONS))
            ->pluck('id');

        $this->attach($roleIds, $newIds);
    }

    /**
     * إظهارُ النطاق الضمني للأدوار التي لا نطاقَ لها إطلاقاً.
     */
    private function assignDefaultDataScopes(): void
    {
        $scopeIds = DB::table('permissions')
            ->where('name', 'like', 'data_scope_%')
            ->pluck('id', 'name');

        if ($scopeIds->isEmpty()) {
            return;
        }

        $rolesWithScope = DB::table('role_has_permissions')
            ->whereIn('permission_id', $scopeIds->values())
            ->distinct()
            ->pluck('role_id')
            ->all();

        $scopeless = DB::table('roles')
            ->whereNotIn('id', $rolesWithScope ?: [0])
            ->get(['id', 'name']);

        foreach ($scopeless as $role) {
            /* «المشرف» هو الوصفُ الحرفيُّ لـ`data_scope_team` في الكتالوج،
               ومطابقةُ الاسم مقصورةٌ على من لا نطاقَ له أصلاً — فلا يمكن
               أن تُوسّع دوراً ضُبط نطاقُه عن قصد. وما عداه إلى الأضيق،
               وهو ما كان النظامُ سيفعله ضمنياً على أيّ حال. */
            $isSupervisor = str_contains($role->name, 'مشرف')
                || str_contains(mb_strtolower($role->name), 'supervisor');

            $target = $isSupervisor ? 'data_scope_team' : 'data_scope_own';

            if (! isset($scopeIds[$target])) {
                continue;
            }

            $this->attach(collect([$role->id]), collect([$scopeIds[$target]]));
        }
    }

    /**
     * ربطُ أدوارٍ بصلاحيات، متخطّياً ما هو مربوطٌ أصلاً.
     *
     * `role_has_permissions` بلا `id` ولا طوابعِ وقت — مفتاحُه مركَّب،
     * وإدراجُ صفٍّ مكرَّرٍ فيه يرمي انتهاكَ مفتاحٍ أوّليٍّ يُسقط المُهاجر
     * كلَّه. فالموجودُ يُستبعد قبل الإدراج لا بعده.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $roleIds
     * @param  \Illuminate\Support\Collection<int, int>  $permissionIds
     */
    private function attach($roleIds, $permissionIds): void
    {
        if ($roleIds->isEmpty() || $permissionIds->isEmpty()) {
            return;
        }

        $existing = DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->get()
            ->map(fn ($row) => $row->role_id.':'.$row->permission_id)
            ->flip();

        $rows = [];

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
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
