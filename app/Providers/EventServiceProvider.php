<?php

namespace App\Providers;

use App\Listeners\RecordSessionActivity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // سجلّ الجلسات: الأحداث لا المتحكّمات.
        //
        // الدخول يقع من `AuthController` و`AuthenticatedSessionController`
        // و`LoginController` ومن استعادة كلمة المرور ومن «تذكّرني»؛ ربط
        // التسجيل بهذه الأحداث يغطّي المسارات كلّها دفعةً واحدة.
        Login::class => [
            [RecordSessionActivity::class, 'onLogin'],
        ],
        Logout::class => [
            [RecordSessionActivity::class, 'onLogout'],
        ],
        OtherDeviceLogout::class => [
            [RecordSessionActivity::class, 'onOtherDeviceLogout'],
        ],
        Failed::class => [
            [RecordSessionActivity::class, 'onFailed'],
        ],
        Lockout::class => [
            [RecordSessionActivity::class, 'onLockout'],
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
