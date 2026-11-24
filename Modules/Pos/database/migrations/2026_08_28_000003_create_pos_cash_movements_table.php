<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حركاتُ الدرج النقدي — إيداعٌ وسحبٌ وترحيلٌ وعدّ.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 * لا شيء. الجلسةُ تُفتح برصيدٍ افتتاحي وتُقفل برصيدٍ فعلي، وبينهما **لا
 * سبيل لإخراج ريالٍ أو إدخاله**. وهذا لا يوافق تشغيلاً حقيقياً:
 *
 *   • الكاشير يحتاج فكّةً في منتصف الوردية.
 *   • المشرفُ يسحب النقد الزائد إلى الخزينة حين يمتلئ الدرج (Cash Drop) —
 *     وهو إجراءٌ أمنيّ قبل أن يكون محاسبياً.
 *   • مصروفٌ نثري صغير يُدفع من الدرج.
 *
 * وبلا هذا الجدول كان كلُّ ذلك يحدث **خارج النظام**، ثم يظهر أثرُه فرقاً
 * في الإقفال لا تفسير له. فالفرقُ الذي يُفترض أن يقيس الخطأ صار يقيس
 * الحركاتِ المشروعةَ غيرَ المسجَّلة.
 *
 * ─────────────────────── ولكلّ حركةٍ قيدُها ───────────────────────
 *
 * `journal_entry_id` ليس اختيارياً في التصميم: نقلُ مالٍ من درج الكاشير
 * إلى الخزينة الرئيسية حركةٌ بين حسابين، وتركُها بلا قيدٍ يجعل رصيد
 * الخزينة في الدفاتر يخالف عدّها الفعلي.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_cash_movements')) {
            return;
        }

        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();

            $table->string('reference_number')->nullable()->unique();

            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            /*
                `pay_in`  ← إدخالُ نقدٍ إلى الدرج (فكّة).
                `pay_out` ← إخراجُ نقدٍ منه (مصروف نثري).
                `drop`    ← ترحيلٌ إلى الخزينة الرئيسية.
                `count`   ← عدُّ الدرج بلا حركة مالٍ — للرقابة أثناء الوردية.
            */
            $table->string('type');

            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->nullable();

            /** الحسابُ المقابل: الخزينةُ في الترحيل، والمصروفُ في السحب. */
            $table->unsignedBigInteger('counterpart_account_id')->nullable();

            $table->text('reason')->nullable();

            /*
                الاعتماد.

                سحبُ النقد فعلٌ رقابيّ لا تشغيلي: يُنشئه الكاشير ويعتمده
                المشرف. و`pending` هي الحالةُ الابتدائية لما يحتاج اعتماداً
                بحسب الإعدادات.
            */
            $table->string('status')->default('approved');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->datetime('approved_at')->nullable();

            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('accounting_period_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->datetime('movement_time');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['session_id', 'type'], 'pos_cash_session_type_idx');
            $table->index(['branch_id', 'movement_time'], 'pos_cash_branch_time_idx');
            $table->index('accounting_period_id', 'pos_cash_period_idx');
            $table->index('status', 'pos_cash_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
    }
};
