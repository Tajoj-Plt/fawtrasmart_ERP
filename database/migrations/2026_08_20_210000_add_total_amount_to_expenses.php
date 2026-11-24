<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `expenses.total_amount` — العمود الذي يكتبه المتحكّم ولا وجود له.
 *
 * **العطل**
 *
 * `ExpensesController::store()` و`update()` يُسنِدان
 * `$expense->total_amount = $expense->amount + $tax1 + $tax2`، وشاشتا
 * الإنشاء والتعديل تعرضان حقل «المبلغ الإجمالي بعد الضريبة» وتقرآن
 * `$expense->total_amount` — بينما الجدول لا يحمل هذا العمود إطلاقاً.
 *
 * النتيجة أنّ **كل** محاولة حفظ سند صرف تُردّ من المحرّك:
 *
 *     SQLSTATE[42S22]: Column not found: 1054
 *     Unknown column 'total_amount' in 'field list'
 *
 * لا سندَ صرفٍ واحد يمكن حفظه. وهو ما يفسّر خلوّ الجدول تماماً.
 *
 * **العلاج**
 *
 * العمود يُضاف كما تتوقّعه الشيفرة والشاشات: المبلغ شاملاً الضريبتين،
 * وافتراضُه صفر فلا يُسقط سنداً لا ضريبة عليه. والصفوف القائمة — إن
 * وُجدت في تنصيبٍ آخر — تُملأ من مبلغها وضرائبها المحفوظة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses') || Schema::hasColumn('expenses', 'total_amount')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('amount');
            }
        });

        DB::table('expenses')->update([
            'total_amount' => DB::raw('COALESCE(amount, 0) + COALESCE(tax1_amount, 0) + COALESCE(tax2_amount, 0)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'total_amount')) {
            Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('total_amount'));
        }
    }
};
