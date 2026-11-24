<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاحُ «الصرف من بوب الإشعار» — عمودٌ في `payroll_settings`.
 *
 * بوبُ إشعار طلب السلفة صار يعتمد **ويصرف** في خطوةٍ واحدة من خزينةٍ
 * يختارها المعتمِد. ومنشأةٌ تفصل القرارَ عن المال قصداً (المعتمِدُ يعتمد،
 * وأمينُ الخزينة يصرف من شاشة الطلب) تُطفئ هذا المفتاح فيعود البوبُ إلى
 * الاعتماد وحده — وصلاحيةُ `loans.disburse` تبقى شرطاً فوقه.
 *
 * عمودٌ لا مفتاحٌ في حقيبة: `PayrollSettings::put()` تُسقط بـ
 * `array_intersect_key` كلَّ مفتاحٍ لا عمودَ له — بصمتٍ تام. فمفتاحٌ بلا
 * عمودٍ هنا يُحفَظ في الشاشة ولا يُخزَّن.
 *
 * `1` افتراضاً: هو ما طُلب أن يكون سلوكَ النظام.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_settings') || Schema::hasColumn('payroll_settings', 'advance_popup_disburse')) {
            return;
        }

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->boolean('advance_popup_disburse')->default(1)->after('advance_approver_department_ids');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_settings') && Schema::hasColumn('payroll_settings', 'advance_popup_disburse')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                $table->dropColumn('advance_popup_disburse');
            });
        }
    }
};
