<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وقتُ فتح تسجيل الحضور قبل بداية الوردية.
 *
 * كان الحضورُ يُقبل في أيّ ساعة ما لم تُضبط «نافذة الدخول» على صفّ الوردية
 * (ونادراً ما تُضبط): موظفٌ ورديتُه ٠٨:٠٠ يسجّل حضوراً العاشرة ليلاً بعد
 * أن احتُسب يومُه غياباً، فيُقلب اليومُ بلا طلب تصحيح. صار للحضور نافذةٌ
 * افتراضية: من (البداية − هذه الدقائق) حتى (البداية + مهلة الغياب).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_settings', 'checkin_early_minutes')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('checkin_early_minutes')->default(60)->after('absent_after_minutes')
                    ->comment('كم دقيقة قبل بداية الوردية يُفتح تسجيل الحضور');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_settings', 'checkin_early_minutes')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->dropColumn('checkin_early_minutes');
            });
        }
    }
};
