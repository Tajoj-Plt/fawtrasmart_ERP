<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ما يلزم تنبيهَ انتهاء وثائق الموظفين.
 *
 * عمودان لا أكثر:
 *
 *   • `payroll_settings.document_expiry_notice_period` — كم يوماً قبل
 *     الانتهاء يبدأ التنبيه. بجوار `contract_end_notice_period` القائم
 *     وفي شاشته نفسِها، فالمنشأة تضبط مُهَلها في موضعٍ واحد.
 *
 *   • `employee_documents.last_alert_sent_at` — **بلا هذا العمود لا
 *     يكون التنبيه إلا مزعجاً**: المهمّة يومية، فوثيقةٌ في نافذة الخطر
 *     ستُنبَّه ثلاثين مرّة في ثلاثين يوماً. وأوّلُ ما يُفعل بتنبيهٍ يتكرّر
 *     كلَّ يوم أن يُتجاهَل — فتضيع معه التنبيهاتُ التي تستحقّ.
 *
 * والبديلُ عن هذا العمود مقارنةُ نصوص الإشعارات القديمة أو مطابقةُ
 * «اليوم الموافق تماماً» — والثانيةُ تُسقط التنبيه كلَّه إن فات
 * `schedule:run` يوماً واحداً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_settings') && ! Schema::hasColumn('payroll_settings', 'document_expiry_notice_period')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_settings', 'document_expiry_notice_period')) {
                    $table->integer('document_expiry_notice_period')->nullable()->after('contract_end_notice_period');
                }
            });
        }

        if (Schema::hasTable('employee_documents') && ! Schema::hasColumn('employee_documents', 'last_alert_sent_at')) {
            Schema::table('employee_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_documents', 'last_alert_sent_at')) {
                    $table->timestamp('last_alert_sent_at')->nullable()->after('notes');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_settings', 'document_expiry_notice_period')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                $table->dropColumn('document_expiry_notice_period');
            });
        }

        if (Schema::hasColumn('employee_documents', 'last_alert_sent_at')) {
            Schema::table('employee_documents', function (Blueprint $table) {
                $table->dropColumn('last_alert_sent_at');
            });
        }
    }
};
