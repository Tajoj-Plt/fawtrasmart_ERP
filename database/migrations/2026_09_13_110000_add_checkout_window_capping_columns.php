<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نافذةُ الانصراف: أعمدةُ ما قُصّ ولماذا.
 *
 * حين يقع ختمُ الانصراف بعد نافذة الوردية المسموحة، يُقبل الختمُ ليُغلق
 * اليومَ ولكن يُكتب في سجل اليوم وقتُ نهاية الوردية لا وقتُ الختم — وإلّا
 * صار نسيانُ الانصراف مكافأةً: موظفٌ غادر الرابعة ومرَّ على الجهاز الحادية
 * عشرة ليلاً يُحتسب له سبعُ ساعاتٍ إضافية لم يعملها.
 *
 * والقصُّ لا يجوز أن يكون صامتاً. فالختمُ يحمل الوقتين معاً: `punched_at`
 * هو ما قرأته الساعةُ فعلاً — لا يُمَسّ — و`applied_time` هو ما كُتب في
 * سجل اليوم، و`adjustment_code` يسمّي السبب. من يقرأ السجلَّ بعد شهرٍ يرى
 * الواقعةَ والقرارَ معاً، لا القرارَ وحده.
 *
 * و`check_out_capped` على صفّ اليوم ليست تكراراً: تقاريرُ الموارد البشرية
 * تُصفّي على صفوف اليوم لا على الأختام، و«أرِني أيامَ الشهر التي قُصَّ
 * انصرافُها» سؤالٌ يُسأل كثيراً — وصلةٌ عبر `check_out_punch_id` تجعله
 * وصلاً في كل تقرير.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_punches', 'applied_time')) {
                $table->time('applied_time')->nullable()->after('punched_at')
                    ->comment('الوقت المكتوب في سجل اليوم — يخالف punched_at عند القصّ');
            }

            if (! Schema::hasColumn('attendance_punches', 'adjustment_code')) {
                $table->string('adjustment_code', 32)->nullable()->after('applied_time')
                    ->comment('سببُ مخالفة الوقتين: capped_to_shift_end');
            }
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_days', 'check_out_capped')) {
                $table->boolean('check_out_capped')->default(false)->after('check_out_channel')
                    ->comment('قُصَّ الانصراف إلى نهاية الوردية لوقوعه خارج النافذة');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            foreach (['applied_time', 'adjustment_code'] as $column) {
                if (Schema::hasColumn('attendance_punches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_days', 'check_out_capped')) {
                $table->dropColumn('check_out_capped');
            }
        });
    }
};
