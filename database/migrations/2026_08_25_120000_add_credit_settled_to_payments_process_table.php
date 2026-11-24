<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ما سُوِّي من الدفعة على رصيد المورد — لا نقداً من الخزينة.
 *
 * الدفعة إلى مورد يحتفظ بمالنا (سُلفة سُدِّدت قبل البضاعة) تنقسم قسمين:
 * قسمٌ تغطّيه تلك السُلفة فلا يخرج به ريال، وقسمٌ نقديّ. والقسمان يُحفظان
 * في `amount` مجموعَين لأنّ الفاتورة تُسدَّد بهما معاً، فلولا هذا العمود
 * لما عرف الإلغاءُ والتعديلُ كم يُعيدان إلى الخزينة — فيُعيدان المبلغ
 * كلّه ويخلقان نقداً من عدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments_process', function (Blueprint $table) {
            if (! Schema::hasColumn('payments_process', 'credit_settled')) {
                $table->decimal('credit_settled', 15, 2)->default(0)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments_process', function (Blueprint $table) {
            $table->dropColumn('credit_settled');
        });
    }
};
