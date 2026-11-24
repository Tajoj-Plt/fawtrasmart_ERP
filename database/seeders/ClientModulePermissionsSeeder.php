<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات تسليم الفواتير وعلاقات العملاء — وربطُها بالأدوار القائمة.
 *
 * **لماذا الربط جزءٌ من الإصلاح لا خطوةٌ لاحقة؟**
 *
 * `InvoiceTransferController` و`CRMController` لم يكن على أيٍّ منهما
 * صلاحيةٌ واحدة: كلُّ من يصل شاشةَ العملاء كان يقدر يحوّل أيّ فاتورة لأيّ
 * موظف ويحذف أيّ إجراء متابعة. ووضعُ صلاحيةٍ على مسارٍ كان مفتوحاً يقلب
 * الوضع إلى نقيضه — لا أحد يقدر، لأنّ `permissions:sync-catalog` تُنشئ
 * الصفَّ ولا تربطه بدور. فتختفي شاشةٌ كانت تعمل، ويبدو الإصلاحُ عطلاً.
 *
 * فالقاعدة هنا: **من كان يقدر يبقى قادراً.** الصلاحيات الجديدة تُمنح لكلّ
 * دورٍ يملك أصلاً صلاحيةَ رؤية العملاء — وهي البوّابة التي كان يُدخَل منها
 * إلى الشاشتين. أمّا الحذفُ والإلغاء فيُقصَران على من يملك صلاحيةَ تعديلٍ
 * قائمة، لا على كل من يرى.
 *
 * **مرّةً واحدة**: `givePermissionTo` لا تسحب ما مُنح، وإعادةُ التشغيل بلا
 * أثر. لكنّ إعادةَ التشغيل بعد أن ينزع مسؤولٌ صلاحيةً بيده **تُعيدها** —
 * فهذا سيدر ترحيلٍ لا سيدر ضبطٍ دوري.
 *
 * @see \App\Constants\permissions\ClientsPermissions تعريف الصلاحيات
 */
class ClientModulePermissionsSeeder extends Seeder
{
    /** ما يُمنح لكل دورٍ يرى العملاء — نظير ما كان مفتوحاً بلا صلاحية. */
    protected const VIEWER_GRANTS = [
        'clients_invoice_transfer_view',
        'clients_invoice_transfer_send',
        'clients_invoice_transfer_respond',
        'clients_crm_view',
        'clients_crm_send_notification',
    ];

    /**
     * ما يُقصَر على من يملك صلاحية تعديلٍ قائمة.
     *
     * `clients_import` و`clients_send_email` هنا لا في `VIEWER_GRANTS`:
     * الاستيراد يكتب مئات الصفوف دفعةً واحدة، والبريد يخرج من المنشأة إلى
     * العميل — كلاهما فعلٌ لا اطّلاع، وإن كان كلاهما مفتوحاً بلا صلاحية قبلُ.
     */
    protected const EDITOR_GRANTS = [
        'clients_invoice_transfer_cancel',
        'clients_import',
        'clients_send_email',
    ];

    /**
     * ما لا يُمنح إلّا للمالك — ويُسنَد لغيره فرداً فرداً من شاشة الأدوار.
     *
     * `clients_writeoff_branch_debt` يشطب ذمم فرعٍ كامل بضغطة، و
     * `clients_manage_exemptions` يعطّل ضوابط رقابية على العملاء. كلاهما كان
     * مفتوحاً بلا صلاحية، ومنحُهما تلقائياً لكل دورٍ يرى العملاء يُبقي
     * الثغرة باسمٍ جديد بدل أن يسدّها.
     *
     * **والمالكُ استثناء** لسببٍ عمليّ لا مبدئي: لولاه لأغلقت هذه الخطوةُ
     * الشاشتين على كل مستخدمٍ في النظام — بما فيهم من يُفترض أن يمنح
     * الصلاحية — فيبدو سدُّ الثغرة عطلاً.
     *
     * @var array<int, string>
     */
    protected const OWNER_ONLY = [
        'clients_writeoff_branch_debt',
        'clients_manage_exemptions',
    ];

    /** الصلاحيات التي تدلّ على أن الدور يصل شاشة العملاء أصلاً. */
    protected const VIEWER_MARKERS = [
        'clients_view_all_clients',
        'clients_view_own_clients',
        'clients_view_by_employee_groups',
        'clients_view_by_daily_itinerary',
    ];

    /** الصلاحيات التي تدلّ على أن الدور يعدّل بيانات العملاء. */
    protected const EDITOR_MARKERS = [
        'clients_edit_data',
        'clients_edit_delete_all_clients',
    ];

    public function run(): void
    {
        foreach ([...self::VIEWER_GRANTS, ...self::EDITOR_GRANTS, ...self::OWNER_ONLY] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $viewers = 0;
        $editors = 0;

        foreach (Role::with('permissions')->get() as $role) {
            $held = $role->permissions->pluck('name')->all();

            if (array_intersect($held, self::VIEWER_MARKERS)) {
                $role->givePermissionTo(self::VIEWER_GRANTS);
                $viewers++;
            }

            if (array_intersect($held, self::EDITOR_MARKERS)) {
                $role->givePermissionTo(self::EDITOR_GRANTS);
                $editors++;
            }
        }

        // المالك وحده يأخذ الصلاحيتين الخطيرتين — غيرُه يُسنَد يدوياً
        $owner = Role::where('name', 'owner')->where('guard_name', 'web')->first();

        if ($owner) {
            $owner->givePermissionTo(self::OWNER_ONLY);
        } else {
            $this->command?->warn('الدور «owner» غير موجود — لم تُمنح صلاحيتا شطب ديون الفرع وإدارة الإعفاءات لأحد.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(
            'صلاحيات وحدة العملاء: مُنحت لـ'.$viewers.' دور عارض و'.$editors.' دور محرِّر'
            .($owner ? '، والخطيرتان للمالك.' : '.')
        );
    }
}
