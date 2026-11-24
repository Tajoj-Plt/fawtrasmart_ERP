<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامة مائية لما رُفع من الاستهلاك إلى لوحة فوترة.
 *
 * المزامنة تسحب الحدود وترفع الاستهلاك في الدورة نفسها. والرفع تراكميّ
 * (`usage/report` يزيد العدّاد ولا يضبطه)، فلا بدّ من تذكّر ما رُفع سابقاً
 * لكل ميزة في كل فترة — وإلا رُفعت فواتير الشهر كاملةً في كل دورة، فبلغ
 * العميل حدّه في ساعة.
 *
 * الشكل: `{"sales_invoices": {"period": "2026-08", "reported": 120, "at": …}}`
 * ومفتاح الفترة داخل القيمة هو ما يجعل بداية شهرٍ جديد تُصفّر العدّ بلا
 * مهمّة تنظيف: فترةٌ مختلفة تعني علامةً مائية جديدة تبدأ من صفر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fawtra_subscription', function (Blueprint $table) {
            if (! Schema::hasColumn('fawtra_subscription', 'usage_report_state')) {
                $table->json('usage_report_state')->nullable()->after('snapshot')
                    ->comment('ما رُفع من الاستهلاك لكل ميزة وفترة');
            }

            if (! Schema::hasColumn('fawtra_subscription', 'last_usage_report_at')) {
                $table->timestamp('last_usage_report_at')->nullable()->after('last_synced_at')
                    ->comment('آخر رفع ناجح للاستهلاك');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fawtra_subscription', function (Blueprint $table) {
            $table->dropColumn(['usage_report_state', 'last_usage_report_at']);
        });
    }
};
