<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نافذةُ انصرافٍ عامّة — لمنشأةٍ بلا جدول ورديات.
 *
 * الطبقةُ الثانية تقرأ النافذة من `shift_days.logout_end_time`. وذلك
 * يفترض أن للموظف ورديةً مُسنَدة — وهو فرضٌ لا يصدق على كل منشأة: حين
 * لا توجد ورديات أصلاً يشتقّ `AttendanceProcessorService` موعدَ الانصراف
 * المتوقَّع من `work_hours_per_day` وحدها، فلا صفَّ ورديةٍ تُقرأ منه
 * نافذة — وتبقى الطبقةُ نائمةً تماماً بلا أن يُنبّه أحد.
 *
 * فهذه نافذةٌ بالدقائق **بعد موعد الانصراف المتوقَّع**، أيّاً كان مصدرُه.
 * والأسبقيّةُ للأخصّ: نافذةُ صفّ الوردية إن وُجدت، وإلّا هذه، وإلّا لا قيد.
 *
 * وتُترك فارغةً افتراضياً: التقييدُ قرارُ سياسةٍ لا أثرُ ترحيلِ هجرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_settings', 'checkout_window_minutes')) {
                $table->unsignedSmallInteger('checkout_window_minutes')->nullable()
                    ->after('early_leave_threshold_minutes')
                    ->comment('دقائق مسموحة بعد نهاية الوردية للانصراف — ما بعدها يُقصّ');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'checkout_window_minutes')) {
                $table->dropColumn('checkout_window_minutes');
            }
        });
    }
};
