<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ الجلسات: صفٌّ دائم لكل دخول وخروج.
 *
 * كانت شاشة «جلسات المستخدمين» تقرأ جدول `sessions` الذي تديره Laravel،
 * وهو جدول **حالة لا تاريخ**: صفّ الجلسة يُحذف لحظة الخروج. فمن دخل
 * وخرج لا يترك أثراً، ومن أُغلق متصفّحه يبقى صفّه معلّقاً حتى تنظيف
 * الجدول. لا يمكن أن يُجاب من هناك سؤالُ «متى دخل فلان أمس ومتى خرج».
 *
 * هذا الجدول مستقلّ عن `sessions` تماماً: يُكتب من أحداث المصادقة، ولا
 * يُحذف منه شيء عند الخروج — يُغلق الصفّ بتاريخ خروجه ومدّته فحسب.
 *
 * ويسجّل المحاولات الفاشلة أيضاً: من حاول الدخول بكلمة خاطئة عشرين مرّة
 * ليلاً معلومةٌ أمنية لا تقلّ أهمية عن الدخول الناجح.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('session_logs')) {
            Schema::create('session_logs', function (Blueprint $table) {
                $table->id();

                // يبقى فارغاً في المحاولة الفاشلة بعنوان بريد غير مسجَّل
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                // البريد كما كُتب في المحاولة — المفتاح الوحيد لتتبّع محاولة فاشلة
                $table->string('attempted_email')->nullable();

                // مُعرّف جلسة Laravel: به تُطابَق الجلسة المفتوحة عند الخروج،
                // وبه يُعرف أنها انتهت حين يختفي صفّها من `sessions`
                $table->string('session_id')->nullable()->index();

                $table->string('status', 20)->default('active')->index()
                    ->comment('active | ended | expired | failed | locked');
                $table->string('logout_reason', 20)->nullable()
                    ->comment('manual | expired | forced | other_device');

                $table->timestamp('login_at')->nullable()->index();
                $table->timestamp('logout_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();

                // تُحسب عند الإغلاق: حسابها في الاستعلام يمنع الترتيب والتصفية
                $table->unsignedInteger('duration_seconds')->nullable();

                $table->string('ip_address', 45)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->string('browser', 60)->nullable();
                $table->string('os', 60)->nullable();
                $table->string('device_type', 20)->nullable()->comment('desktop | mobile | tablet | bot');

                $table->string('guard', 30)->nullable();
                $table->boolean('remembered')->default(false);

                $table->timestamps();

                // شاشة السجل تُصفّي بالمستخدم وبالحالة مرتّبةً بوقت الدخول
                $table->index(['user_id', 'login_at']);
                $table->index(['status', 'login_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('session_logs');
    }
};
