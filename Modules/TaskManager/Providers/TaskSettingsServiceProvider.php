<?php

namespace Modules\TaskManager\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\TaskManager\Http\Controllers\Settings\GlobalSettingsController;

class TaskSettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the global settings controller as a singleton
        $this->app->singleton('taskmanager.settings', function ($app) {
            return new GlobalSettingsController();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish the config file
        $this->publishes([
            __DIR__.'/../config/taskmanager-settings.php' => config_path('taskmanager-settings.php'),
        ], 'taskmanager-settings-config');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'taskmanager');

        // لا تُحمَّل المسارات من هنا: `RouteServiceProvider` في هذه
        // الوحدة يحمّل الملفّ نفسه داخل مجموعة `web`. وتحميله مرّةً
        // ثانية بـ`loadRoutesFrom` — وهي بلا أي مجموعة وسائط — يُسجّل
        // المسارات نفسها بلا جلسة ولا كوكيز، والتسجيل الأخير يطغى على
        // الأول لأن `RouteCollection` مفهرسة بالطريقة والمسار. فكانت
        // النتيجة أن `auth` لا يرى مستخدماً مسجَّلاً أبداً على شاشات
        // إدارة المهام فيعيد إلى صفحة الدخول مهما تكرّر الدخول.
    }
}