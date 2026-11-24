<?php

use App\Support\SchemaRetire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط «الإشعارات المدينة» من المبيعات — بطلب المستخدم.
 *
 * المستند كان يزيد ذمّة العميل بفارق سعرٍ أو رسمٍ بعد إصدار الفاتورة،
 * وأُسقط بشاشاته الأربع ومتحكّمه ونموذجيه ومساراته وصلاحياته ومفتاحه في
 * «إعدادات الفواتير». وهنا يُسقَط أثرُه في القاعدة:
 *
 *   • الجدولان — `sales_debit_notes` و`sales_debit_note_items`.
 *   • العمود `sales_invoice_settings.enable_debit_notification` — مفتاحُه.
 *   • صفوف `permissions` الأربعة، وارتباطاتُها بالأدوار معها.
 *
 * والصلاحيات تُحذف لا تُترك: `permissions:sync` لا يمسّ ما ليس في
 * `SalesPermissions`، فتبقى أربعُ صلاحياتٍ معلّقةً في شاشة الأدوار تشير
 * إلى شاشةٍ لا وجود لها.
 *
 * `down()` لا يُعيد المستند: شيفرتُه ذهبت مع الشاشات، وجدولٌ فارغٌ يعود
 * بلا ما يكتب فيه ليس رجوعاً. يُعيد العمودَ وحدَه — وهو ما تقرؤه
 * `SalesInvoiceSetting`.
 */
return new class extends Migration
{
    /** أسماء الصلاحيات كما كانت في `SalesPermissions::groups()` */
    private const PERMISSIONS = [
        'sales.debit_notes.view',
        'sales.debit_notes.create',
        'sales.debit_notes.print',
        'sales.debit_notes.cancel',
    ];

    public function up(): void
    {
        /* البنود أوّلاً: مفتاحُها الأجنبيّ على الرأس.

           وتقاعدٌ لا حذف — انظر [\App\Support\SchemaRetire]. الميزةُ أُلغيت
           بطلب المستخدم، لكن إشعاراً مدينًا صدر بالفعل مستندٌ محاسبيّ: لا
           يُمحى لأن شاشته أُغلقت. الفارغُ يُسقَط وما فيه صفوفٌ يُحفظ باسم
           `retired_*`. */
        SchemaRetire::tables(['sales_debit_note_items', 'sales_debit_notes']);

        /* `sales_invoice_settings.enable_debit_notification` يبقى عموداً
           مهجوراً. كان يُحذف هنا — والحذفُ لا يربح شيئاً: قيمةٌ منطقيةٌ
           واحدة في جدول إعدادات، ولا كودَ يقرؤها بعد إلغاء الميزة. */

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

            if ($ids->isNotEmpty()) {
                foreach (['role_has_permissions', 'model_has_permissions'] as $pivot) {
                    if (Schema::hasTable($pivot)) {
                        DB::table($pivot)->whereIn('permission_id', $ids)->delete();
                    }
                }

                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales_invoice_settings', 'enable_debit_notification')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->boolean('enable_debit_notification')->default(false);
            });
        }
    }
};
