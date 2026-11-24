<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الورديةُ الافتراضية: مُدخَلةٌ لا مكتوبةٌ في الشيفرة.
 *
 * موظفٌ بلا وردية مُسنَدة — وهي حالُ منشأةٍ لم تُنشئ جدول ورديات بعد —
 * كان يُقاس عليه ثابتان مدفونان في `AttendanceProcessorService`:
 * `'08:00:00'` لبداية الدوام، و`['fri']` للعطلة الأسبوعية.
 *
 * وذلك يعني أن منشأةً تبدأ التاسعة تُحتسب على موظفيها ساعةُ تأخيرٍ كلَّ
 * يوم، وأخرى عطلتُها الجمعة والسبت يُحتسب سبتُها يومَ غياب — ولا سبيل
 * إلى تصحيح ذلك من أي شاشة، لأن القيمة ليست إعداداً أصلاً.
 *
 * ونهايةُ الدوام لا عمودَ لها: تُشتقّ من البداية + `work_hours_per_day`
 * وهي مُدخَلةٌ منذ V2.
 *
 * الفراغُ يُبقي السلوك الحالي حرفياً — الثابتان يبقيان احتياطاً أخيراً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_settings', 'default_shift_start')) {
                $table->time('default_shift_start')->nullable()->after('work_hours_per_day')
                    ->comment('بداية الدوام لمن لا وردية له — الفراغ يعني 08:00');
            }

            if (! Schema::hasColumn('attendance_settings', 'default_weekly_off_days')) {
                $table->json('default_weekly_off_days')->nullable()->after('default_shift_start')
                    ->comment("أيام العطلة الأسبوعية: ['fri','sat'] — الفراغ يعني الجمعة");
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            foreach (['default_shift_start', 'default_weekly_off_days'] as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
