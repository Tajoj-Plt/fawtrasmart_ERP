<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «يُقفل تسجيل الحضور بعد» — ضابطٌ مستقلٌّ عن «اعتبار غياب بعد».
 *
 * كانت `absent_after_minutes` تعمل عملين في آنٍ واحد:
 *
 *   ١) **الحكمُ على اليوم**: من تأخّر أكثر منها يُحتسب يومُه غياباً
 *      (`AttendanceProcessorService::calculateStatus`).
 *   ٢) **إقفالُ التسجيل**: `AttendanceStampService::checkInWindow()` كانت
 *      تُنهي نافذةَ الحضور عند (بداية الوردية + هذه المهلة)، فيُردّ كلُّ
 *      ختمٍ بعدها بـ«انتهى وقت تسجيل الحضور لهذا اليوم».
 *
 * والعملُ الثاني أشدُّ من الأول بكثير: الأولُ يصف اليومَ، والثاني يمنع
 * الموظفَ من تسجيله أصلاً — فلا يبقى له إلّا طلبُ تصحيحٍ يعتمده مديرُه عن
 * تأخيرٍ عاديّ. وبالإعداد الافتراضي (بدايةُ الدوام 08:00 ومهلةُ الغياب
 * 120 دقيقة) يُقفل التسجيلُ الساعةَ **عشراً صباحاً** لكلّ موظفٍ لا وردية
 * مُسنَدةً له — و08:00 نفسُها ليست وردياً حقيقيةً بل افتراضَ المنشأة.
 *
 * والنموذجُ كان يحتمل المتأخّرَ أصلاً: `applyStampToDay()` تُسند حالةَ
 * اليوم من `determineCheckInStatus()`، وهي تُرجع «غياب» لمن تجاوز المهلة.
 * أي أنّ الختمَ المتأخّر كان **محسوباً** في الحكم ومُمنوعاً في الباب.
 *
 * فصار الإقفالُ مفتاحاً خاصّاً به، و`null` افتراضُه: **لا يُقفل**. فمن
 * تأخّر يسجّل حضوره، ويُحتسب يومُه بما تقوله مهلةُ الغياب — سجلٌّ بحقيقته
 * بدل يومٍ فارغٍ وورقةِ تصحيح. ومن أراد الإقفالَ ضبط المفتاح.
 *
 * ونافذةُ صفّ الوردية (`shift_days.login_end_time`) تبقى فوق هذا كلِّه:
 * قرارٌ صريحٌ على جدولٍ صريح.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_settings') || Schema::hasColumn('attendance_settings', 'checkin_close_after_minutes')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            // `null` = لا يُقفل التسجيل — وهو الافتراض
            $table->unsignedSmallInteger('checkin_close_after_minutes')->nullable()->after('checkin_early_minutes');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_settings') && Schema::hasColumn('attendance_settings', 'checkin_close_after_minutes')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->dropColumn('checkin_close_after_minutes');
            });
        }
    }
};
