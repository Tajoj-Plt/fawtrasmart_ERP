<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الإشعارات: صفّ لكل نوع إشعار يعرفه النظام.
 *
 * لم يكن في المشروع أي وسيلة لإيقاف نوع إشعار أو توجيهه؛ كان كل إشعار
 * يُكتب مباشرة في جدول `notifications` من 73 موضعاً في الكود. هذا الجدول
 * هو البوابة التي تُقرأ قبل الكتابة، فيصبح التحكّم من الإعدادات لا من الكود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_settings')) {
            Schema::create('notification_settings', function (Blueprint $table) {
                $table->id();

                // مطابق لعمود `notifications.type` — هو مفتاح الربط بين الاثنين
                $table->string('key', 100)->unique();
                $table->string('label');
                $table->string('description', 500)->nullable();
                $table->string('category', 50)->default('system')->index();

                $table->boolean('is_enabled')->default(true)->index();

                // القنوات المفعّلة: ['database','mail','sms']
                $table->json('channels')->nullable();
                // من يستلم: ['receiver','admins','managers','client']
                $table->json('recipients')->nullable();

                // نصّ اختياري للبريد/الرسالة؛ فارغ = يُستعمل عنوان الإشعار نفسه
                $table->string('sms_template_code', 60)->nullable();

                $table->unsignedSmallInteger('sort_order')->default(0);

                // أنواع اكتُشفت من البيانات ولم تُعرَّف في الكتالوج — تُعلَّم كي
                // يعرف المسؤول أنها لم تُراجَع بعد
                $table->boolean('is_discovered')->default(false);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
