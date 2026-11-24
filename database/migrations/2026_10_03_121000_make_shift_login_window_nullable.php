<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نافذةُ تسجيل الدخول على صفّ الوردية تصير اختيارية.
 *
 * `shift_days.login_start_time` و`login_end_time` عمودان `NOT NULL`، وحقلاهما
 * في نموذج الورديات `required` بقيمتَين افتراضيّتَين مكتوبتَين في القالب:
 * **07:00** و**11:00**. فمن أنشأ ورديّةً — ولو لم ينظر إلى هذين الحقلين —
 * كتب سياسةً: «لا يُقبل تسجيل حضورٍ بعد الحادية عشرة». ولم يكن في الشاشة
 * سبيلٌ إلى إلغائها، لأنّ الحقل مطلوبٌ والعمود لا يقبل الفراغ.
 *
 * وهي تعلو على كلّ شيء في `AttendanceStampService::checkInWindow()` — فمفتاحُ
 * «إقفال تسجيل الحضور بعد» الذي أُضيف في
 * `2026_10_03_120000_add_checkin_close_after_minutes_to_attendance_settings`
 * لا أثرَ له على موظفٍ له ورديّة، ما بقي الصفُّ يحمل 11:00.
 *
 * فصار العمودان يقبلان الفراغ: فارغٌ = لا نافذةَ على مستوى الوردية، فيحكم
 * الإعدادُ العام (وهو «لا يُقفل» افتراضاً). ومن أراد نافذةً على ورديةٍ
 * بعينها كتبها — قراراً صريحاً لا افتراضَ نموذج.
 *
 * **ولا تُمسّ القيمُ المحفوظة**: صفٌّ يحمل 11:00 يبقى كما هو، فلعلّها
 * سياسةٌ مقصودة في منشأةٍ ما. وتفريغُها من شاشة الورديات صار مُتاحاً.
 *
 * `start_time`/`end_time` و`logout_*` تبقى كما هي — معناها غيرُ هذا،
 * وتغييرُها خارج هذه المسألة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_days')) {
            return;
        }

        Schema::table('shift_days', function (Blueprint $table) {
            $table->time('login_start_time')->nullable()->change();
            $table->time('login_end_time')->nullable()->change();
        });
    }

    /**
     * التراجعُ يُعيد `NOT NULL`، وقد يسقط إن كان في الجدول صفٌّ فارغُ
     * النافذة — وهو متوقَّع: لا يصحّ أن يُلفّق التراجعُ ساعةً لمن تركها فارغة.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shift_days')) {
            return;
        }

        Schema::table('shift_days', function (Blueprint $table) {
            $table->time('login_start_time')->nullable(false)->change();
            $table->time('login_end_time')->nullable(false)->change();
        });
    }
};
