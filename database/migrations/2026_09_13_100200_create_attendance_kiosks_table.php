<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جهازُ الفرع الثابت (تابلت/حاسوب بكاميرا) الذي يمسح بطاقاتِ الموظفين
 * طوال الوردية برعاية مشغّل الفرع.
 *
 * وجودُه كصفٍّ في الجدول — لا كمجرّد متصفّحٍ مفتوح — هو ما يجعل «الوسيلة
 * والمكان» تُسجَّلان تلقائياً: كلُّ مسحةٍ تحمل `kiosk_id`، ومن الجهاز
 * يُشتقّ الفرعُ والنطاق. ولا علاقة له بـ`machines` (أجهزة البصمة
 * الشبكية ZKTeco التي تُسحب منها السجلات دفعةً واحدة).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_kiosks')) {
            return;
        }

        Schema::create('attendance_kiosks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('geofence_id')->nullable()->index()
                ->comment('نطاقُ المرفق الذي يقف فيه هذا الجهاز');

            /*
                رمزُ الاقتران: يُدخَل مرّةً واحدة على الجهاز فيُحفظ محلياً،
                فلا يُطالَب المشغّلُ باختيار الجهاز في كل نوبة — ولا يُترك
                الاختيارُ لصدفةِ آخر ما اختاره أحدُهم.
            */
            $table->string('pairing_code', 16)->unique();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_kiosks');
    }
};
