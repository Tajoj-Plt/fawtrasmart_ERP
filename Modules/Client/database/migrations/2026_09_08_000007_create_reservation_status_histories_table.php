<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خطُّ حياة الحجز — كلُّ انتقالِ حالةٍ صفٌّ لا يُمحى.
 *
 * **ولمَ لا يكفي `OperationLog`؟** يكفي للسؤال «من غيّر ماذا ومتى» — وهو
 * يجيبه لكل مستندات النظام. لكنّه سجلٌّ عامٌّ مشتركٌ بين ثلاثين شاشة،
 * يُنقَّى دورياً بـ`activity-logs:prune`، ولا يُستعلَم منه بكفاءةٍ سؤالُ
 * التقارير: «كم حجزاً انتقل من مؤكَّد إلى لم يحضر هذا الشهر؟».
 *
 * فالسجلّان معاً ولكلٍّ عملُه: `OperationLog` للتدقيق العامّ، وهذا الجدول
 * لقياس سير العمل — ومنه يُبنى تقريرُ عدم الحضور وتقريرُ الإلغاء.
 *
 * ويُكتب فيه `ip` و`user_agent` كذلك: الإلغاءُ نزاعٌ محتمل مع العميل،
 * ومَن ألغى ومن أيّ جهازٍ سؤالٌ يُطرَح فعلاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_status_histories')) {
            return;
        }

        Schema::create('reservation_status_histories', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('reservation_id');

            /* فارغاً في أوّل صفّ — الحجز لم يكن له قبلُ حالة */
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);

            $table->text('note')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['reservation_id', 'created_at'], 'reservation_status_histories_reservation_index');
            $table->index(['to_status', 'created_at'], 'reservation_status_histories_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_status_histories');
    }
};
