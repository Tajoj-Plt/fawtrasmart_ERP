<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سدادُ التأمينات المحجوزة — المخرجُ الذي لم يكن.
 *
 * صرفُ الراتب يحجز حصةَ الموظف ويُقيّدها دائنةً في ذمّتها، ويُقيّد حصةَ
 * المنشأة مصروفاً والتزاماً. فيتراكم الحسابان شهراً بعد شهر ولا سبيلَ في
 * وحدة الرواتب إلى إخراجهما نقداً: إقفالُهما كان يحتاج سندَ صرفٍ يدويّاً في
 * وحدة الحسابات، بلا رابطٍ يعود إلى الرواتب ولا سجلٍّ لما سُدِّد ومتى.
 *
 * وهو نظيرُ ما عولج في مكافأة نهاية الخدمة: التسويةُ إلى ذمّةٍ خطوة،
 * وإخراجُ النقد خطوةٌ بعدها.
 *
 * والحصّتان في صفٍّ واحد لا صفَّين: تُسدَّدان إلى الجهة نفسها بحوالةٍ
 * واحدة، وفصلُهما يُخرج سندَي صرفٍ لدفعةٍ واحدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_insurance_remittances')) {
            return;
        }

        Schema::create('social_insurance_remittances', function (Blueprint $table) {
            $table->id();

            /** الشهرُ المسدَّد عنه — نصّاً `YYYY-MM`، وقد تُسدَّد أشهرٌ معاً فيُترك فارغاً. */
            $table->string('period_month', 7)->nullable()->index();

            $table->decimal('employee_amount', 15, 2)->default(0);
            $table->decimal('employer_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->unsignedBigInteger('treasury_id')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->string('reference', 191)->nullable()->comment('رقم الحوالة أو إيصال الجهة');
            $table->text('notes')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_insurance_remittances');
    }
};
