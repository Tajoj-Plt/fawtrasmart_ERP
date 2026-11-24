<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلُّ التذكيرات المُرسَلة — هو ما يجعل أمر التذكير **لا يكرّر نفسه**.
 *
 * ═══════════ لماذا جدول؟ ═══════════
 *
 * أمرُ الكرون يعمل كلَّ يوم (وقد يُشغَّل يدوياً مرّاتٍ بينهما)، ومهلُ
 * التذكير ثلاثٌ أو أربع لكل حجز. فبلا سجلٍّ لما أُرسل:
 *
 *   • تشغيلان في اليوم ⇒ العميلُ يتلقّى الرسالةَ مرّتين.
 *   • ومهلتان متقاربتان ⇒ يتلقّاها مرّتين في ساعة.
 *
 * وعمودٌ واحد في `reservations` (`reminded_at`) لا يكفي: المهلُ متعدّدة،
 * ويجب أن يُعرَف **أيُّها** أُرسل — تذكيرُ «قبل يوم» لا يمنع تذكيرَ «قبل
 * ساعتين».
 *
 * والمفتاحُ الفريد `(reservation_id, offset_minutes)` هو الضمانة البنيوية:
 * حتى لو تسابق تشغيلان، الثاني يصطدم بالفهرس ولا يكتب صفّاً ثانياً — فلا
 * يُرسل. وهو أقوى من فحصٍ في الشيفرة قد يقع بين القراءة والكتابة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_reminders')) {
            return;
        }

        Schema::create('reservation_reminders', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('reservation_id');

            /** المهلة التي أُرسل عندها — بالدقائق قبل الموعد */
            $table->unsignedInteger('offset_minutes');

            $table->timestamp('sent_at')->nullable();

            /** فشلُ الإرسال يُسجَّل ولا يُخفى: صفٌّ بلا `sent_at` وبسببٍ مكتوب */
            $table->string('channel', 30)->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique(['reservation_id', 'offset_minutes'], 'reservation_reminders_unique');
            $table->index('sent_at', 'reservation_reminders_sent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_reminders');
    }
};
