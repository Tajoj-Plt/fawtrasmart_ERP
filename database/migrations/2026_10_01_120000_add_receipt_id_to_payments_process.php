<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
    دفعاتُ سند القبض تُربط بسندها بمعرّفٍ لا بنصّ.

    كان توزيعُ السند على الفواتير (`IncomesController::applyPaymentToInvoices`)
    يكتب معرّفَ السند في `reference_number`، والإلغاءُ يحذف كلَّ صفٍّ يساوي
    مرجعُه ذلك الرقم. والحقلُ نفسُه يكتبه المستخدمُ بيده في دفعات المبيعات،
    فدفعةٌ مرجعُها «2344» لعميلٍ آخر تُحذف مع إلغاء السند رقم 2344. وفي هذه
    القاعدة أربعُ دفعاتٍ من ديسمبر 2025 تطابق أرقامُها سنداتٍ لعملاءٍ آخرين
    أُنشئت بعدها بأشهر.

    فصار للدفعة عمودُ `receipt_id`، وبه وحده يُلغى التوزيع. ويُعبَّأ للقديم
    بالقرائن التي يكتبها التوزيعُ نفسُه — ولا يكتبها غيرُه مجتمعةً: مرجعٌ رقميّ
    هو معرّفُ السند، ونوعُ دفعة عميلٍ أو رصيدٍ سابق، بلا خزينة، لعميلِ حساب
    السند، ولم تُنشأ قبل السند (التعديلُ يعيد التوزيع لاحقاً فلا حدَّ أعلى).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments_process') || Schema::hasColumn('payments_process', 'receipt_id')) {
            return;
        }

        Schema::table('payments_process', function (Blueprint $table) {
            $table->unsignedBigInteger('receipt_id')->nullable()->after('reference_number');
            $table->foreign('receipt_id', 'payments_process_receipt_id_foreign')
                ->references('id')->on('receipts')
                ->nullOnDelete();
        });

        // تعبئةُ الصفوف القديمة بصيغة MySQL (`UPDATE … JOIN`, `REGEXP`) —
        // قاعدة SQLite للاختبار والتطوير تبدأ فارغة فلا شيء يُعبَّأ فيها
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            UPDATE payments_process p
            JOIN receipts r ON r.id = CAST(p.reference_number AS UNSIGNED)
            JOIN accounts a ON a.id = r.account_id
            SET p.receipt_id = r.id
            WHERE p.reference_number REGEXP '^[0-9]+$'
              AND p.type IN ('client payments', 'previous_balance_payment')
              AND p.treasury_id IS NULL
              AND a.client_id = p.client_id
              AND p.created_at >= r.created_at - INTERVAL 300 SECOND
        ");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments_process', 'receipt_id')) {
            return;
        }

        Schema::table('payments_process', function (Blueprint $table) {
            $table->dropForeign('payments_process_receipt_id_foreign');
            $table->dropColumn('receipt_id');
        });
    }
};
