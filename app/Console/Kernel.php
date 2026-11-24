<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * جدولة التطبيق.
     *
     * كانت هنا عشر مهام مكتوبة يدوياً، فتغيير وقت أيٍّ منها أو إيقافها
     * يتطلّب تعديل هذا الملف ونشره. انتقلت كلّها إلى جدول `cron_jobs`
     * (انظر `CronJobsSeeder`)، ولم يبقَ هنا إلا `cron:run-due` الذي يقرأ
     * الجدول كل دقيقة وينفّذ ما استُحقّ.
     *
     * تسويات الأهداف كذلك في `cron_jobs` (مفتاح `targets_settlements`)
     * — انظر التعليق أسفل الدالّة.
     */
    protected function schedule(Schedule $schedule)
    {
        // ====================================================================
        // نقطة الدخول الوحيدة للمهام المُدارة من الإعدادات
        // ====================================================================
        $schedule->command('cron:run-due')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->name('cron-run-due')
            ->onFailure(function () {
                // كان هذا الملف يستدعي `App\Models\Log::error()` — وهو نموذج
                // Eloquent لا واجهة السجلّ، فلا وجود لدالّة `error` فيه
                // ويرمي الاستدعاء `BadMethodCallException`. كل معالجات
                // النجاح والفشل العشر هنا كانت معطّلة بهذا السبب
                Log::error('فشلت دورة المجدول: cron:run-due');
            });

        // `sms:flush` و`sessions:reconcile` كانتا هنا وحدَهما خارج جدول
        // `cron_jobs`، فلا يصلهما المجدولُ الداخلي (`SchedulerTick` يُنفّذ
        // `cron:run-due` لا `schedule:run`). صارتا صفّين في الجدول
        // (`sms_flush`، `sessions_reconcile`) — انظر `CronJobsSeeder`.
        //
        // تسوية أهداف المبيعات/التحصيلات ليست هنا: هي مهمة «تسوية أهداف
        // المبيعات والتحصيلات» في جدول `cron_jobs` (مفتاح `targets_settlements`)
        // تُفعَّل وتُوقف من شاشة «المهام المجدولة» كسائر المهام، والأمرُ
        // `targets:process-settlements` يفحص يومَ التسوية ووقتَها من
        // «إعدادات الأهداف» بنفسه. كانت تُجدوَل هنا ديناميكياً من
        // `target_settings` إلى جانب تلك المهمة، فصار للصرف مفتاحان يتخالفان:
        // مهمةٌ موقوفة في الشاشة وصرفٌ يقع من هنا. (2026-09-26)
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
