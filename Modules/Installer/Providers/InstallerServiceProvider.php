<?php

namespace Modules\Installer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Installer\Services\InstallationState;

class InstallerServiceProvider extends ServiceProvider
{
    protected string $name = 'Installer';

    protected string $nameLower = 'installer';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerRoutes();
        $this->registerCommands();
        $this->forceFilesystemDriversBeforeInstall();
    }

    public function register(): void
    {
        //
    }

    /**
     * قبل التثبيت: الجلسة والكاش على الملفات لا على قاعدة البيانات.
     *
     * `.env` منسوخ من `.env.example` قد يحمل `SESSION_DRIVER=database`، وحينها
     * أول طلبٍ لمعالج التثبيت يحاول الكتابة على جدول `sessions` غير الموجود
     * فينهار — أي أن المعالج يعجز عن العمل بسبب المشكلة التي وُجد لحلّها.
     *
     * التجاوز مؤقت: بمجرد وجود علامة التثبيت تعود القيم إلى ما في `.env`.
     *
     * في `boot` لا `register`: الفحص قد يمسّ قاعدة البيانات (استكشاف تركيبٍ
     * قائم)، واستدعاء ذلك أثناء register يُجبر مدير القاعدة على الإقلاع قبل
     * أوانه. والجلسة تبدأ في الوسيط — بعد إقلاع كل المزوّدين — فالضبط هنا
     * يسبقها بمسافة كافية.
     */
    protected function forceFilesystemDriversBeforeInstall(): void
    {
        if (InstallationState::isInstalled()) {
            return;
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);
    }

    protected function registerRoutes(): void
    {
        Route::group([], module_path($this->name, 'routes/web.php'));
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(module_path($this->name, 'resources/views'), $this->nameLower);
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), $this->nameLower);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Installer\Console\InstallCommand::class,
                \Modules\Installer\Console\CheckRequirementsCommand::class,
                \Modules\Installer\Console\ResetInstallationCommand::class,
            ]);
        }
    }
}
