<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `daily_visits_report` (الأمر `reports:daily-visits`) كان يمرّ على كل
 * الموظفين يومياً بصرف النظر عمّن أقفل يومه، ويرسل عبر Telegram محتوىً
 * غير الإقفال اليومي — وكلاهما أُلغي: الإرسال صار حصراً عند إقفال كل
 * موظفٍ يومه (`DailyClosingController` ← `TelegramNotificationService`)،
 * ولا مسارٍ آخر يُرسل عبر Telegram بعد اليوم. حذفُ الصفّ هنا لا تعطيلُه:
 * الأمر والصنف الذي يستدعيه حُذفا من الكود، فبقاء صفٍّ يشير إليهما مضلِّل.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cron_jobs')->where('key', 'daily_visits_report')->delete();
    }

    public function down(): void
    {
        // لا عودة تلقائية — الأمر المرجعي نفسه حُذف من الكود.
    }
};
