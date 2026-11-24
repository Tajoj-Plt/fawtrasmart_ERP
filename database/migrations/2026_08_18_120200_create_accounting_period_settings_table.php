<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ضوابط الفترات المحاسبية — صفّ واحد يمثّل سياسة النظام كلها.
 *
 * `enforce_lock` هو المفتاح الرئيسي: ما دام مطفأً تبقى الفترات سجلّاً
 * تنظيمياً يُعرض ولا يمنع شيئاً. تشغيله يجعلها قيداً فعلياً على الإدخال.
 * الفصل مقصود — المنشأة تُرتّب فتراتها أولاً ثم تفرضها حين تطمئنّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_period_settings')) {
            Schema::create('accounting_period_settings', function (Blueprint $table) {
                $table->bigIncrements('id');

                // البوّابة العامّة: مطفأة ابتداءً كي لا يتوقّف إدخال قائم فجأة
                $table->boolean('enforce_lock')->default(false);

                // تاريخ قفل مطلق: كل ما قبله ممنوع مهما كانت حالة فترته.
                // يفيد حين تُدقَّق سنوات سابقة بلا فترات مُدخَلة في النظام
                $table->date('lock_before_date')->nullable();

                // تاريخ خارج أي فترة معرّفة: يُمنع أم يُسمح؟ المنع أسلم لكنه
                // يكسر الإدخال في منشأة لم تُعرّف فتراتها كلها بعد
                $table->boolean('block_undefined_dates')->default(false);

                // منع القيد بتاريخ مستقبلي — شائع في السياسات المحاسبية
                $table->boolean('block_future_dates')->default(false);

                // نوع الفترات المولَّدة افتراضياً عند إنشاء سنة مالية
                $table->enum('default_period_type', ['monthly', 'quarterly', 'semiannual', 'annual'])
                    ->default('monthly');

                // من يملك هذه الصلاحية يتجاوز القفل — يُسجَّل تجاوزه في السجلّ
                $table->string('override_permission', 120)->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_settings');
    }
};
