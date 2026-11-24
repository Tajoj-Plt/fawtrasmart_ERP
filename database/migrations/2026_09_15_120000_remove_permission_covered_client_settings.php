<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تنظيف «إعدادات وحدات العملاء» بطلب المستخدم (2026-09-15): كلُّ إعدادٍ
 * تكفي عنه صلاحية، وكلُّ إعدادٍ لا يقرؤه شيء، خرج من الشاشة ومن النظام.
 *
 * **صفوفٌ تُحذف هنا**
 *
 *   • `client_show_actions_settings` — ثلاثة عشر مفتاح «إظهار زرّ» في شاشة
 *     عرض العميل. كلُّ زرٍّ صار يحكمه فحصُ صلاحيةِ مساره في
 *     `actions-buttons.blade.php` (وأُصلحت ثلاثة فحوصٍ خاطئة وأُضيفت أربعة
 *     غائبة)، ومفتاحُ إخفاءٍ فوقها كان يُخفي الزرّ عن الشركة كلّها لا عن دور.
 *   • `employee_visit_permissions` — كان يُقرأ في `VisitController::update()`
 *     وحده، على مسارٍ لا تستدعيه شاشة. بقي سلوكُ افتراضه (منعُ الموظف من
 *     تعديل الحالة) ثابتاً في الكود.
 *   • `visit_justification_settings` — الإعفاء من التبريرات صلاحيةٌ
 *     (`staff_visit_justification_exempt`) يقرؤها الميدلوير نفسه.
 *   • `credit_limit` (من «الحقول الإضافية») — كان يقرّر وجود نافذة الحد
 *     الائتماني وحده، ومسارُها محروسٌ بـ`clients_manage_credit_limit`.
 *
 * يجب حذفُ صفّ المجموعة مع تعريفها: `ClientSettings::clientFields()` هو «كلُّ
 * صفٍّ ليس في التعريف»، فصفٌّ يتيم يعود للشاشة حقلاً إضافياً مزيّفاً.
 *
 * **أقسامٌ أُزيلت من الشاشة بلا صفوف**: أعمدةُ `client_module_settings`
 * (`itinerary_enabled`، `location_enabled`، `crm_enabled`،
 * `invoice_transfer_enabled`، `customer_targets_enabled`) تبقى مكانها —
 * العمود لا يُحذف — ولا يقرؤها شيءٌ بعد اليوم.
 *
 * `down()` لا يعيد شيئاً: الشيفرة التي كانت تقرأ هذه الصفوف لم تعد موجودة.
 */
return new class extends Migration
{
    private const DEAD_ROWS = [
        'client_show_actions_settings',
        'employee_visit_permissions',
        'visit_justification_settings',
        'credit_limit',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('general_client_settings')) {
            return;
        }

        DB::table('general_client_settings')
            ->whereIn('key', self::DEAD_ROWS)
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
