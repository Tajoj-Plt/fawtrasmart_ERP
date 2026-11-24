<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وسمُ سند الصرف المعكوس، ليخرج من الإحصاء ويبقى في السجلّ.
 *
 * `PayrollReversalService::annotateExpense()` لا يفعل بسند صرف الراتب
 * المعكوس إلا أن يُصدّر وصفَه بـ`[معكوس]` ويُفرّغ
 * `payroll_runs.expense_id`. أمّا صفُّ `expenses` فيبقى بكامل مبلغه —
 * وذلك مقصودٌ في نفسه: المستندُ لا يُمحى.
 *
 * لكنّ تقاريرَ المصروفات (`GeneralAccountsController::expensesByCategory`
 * و`expensesByPeriod`) وبطاقاتَ الإحصاء في شاشة المصروفات تستعلم
 * `Expense::query()` بلا أيِّ استثناءٍ للمعكوس. فالعكسُ ثمّ إعادةُ الصرف —
 * وهو المسارُ المقصود لتصحيح خطأ — يُخلِّف صفَّي مصروفٍ لراتبٍ واحد:
 * فيضاعف التقريرُ بينما الدفترُ صحيح، ويختلف تقريران عن الشهر نفسه.
 *
 * والوسمُ عمودُ تاريخٍ لا رايةَ `boolean`: «متى عُكس» سؤالٌ يُسأل، و`null`
 * تكفي للدلالة على غير المعكوس.
 *
 * ولا ملءَ لما مضى: لا سبيلَ موثوقاً لمعرفة أيُّ سندٍ قديمٍ عُكس — الرابطُ
 * `expense_id` مُفرَّغٌ عند العكس، والوسمُ النصّيُّ `[معكوس]` في الوصف
 * يقبل الكتابةَ يدوياً فلا يُبنى عليه تعديلُ بيانات. ومن أراد تصحيح
 * التاريخ يفعله بعلمه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        if (! Schema::hasColumn('expenses', 'reversed_at')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('expenses', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable()->index();
                }
            });
        }

        if (! Schema::hasColumn('expenses', 'reversal_reason')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('expenses', 'reversal_reason')) {
                    $table->string('reversal_reason', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        foreach (['reversed_at', 'reversal_reason'] as $column) {
            if (Schema::hasColumn('expenses', $column)) {
                Schema::table('expenses', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
