<?php

return [
    /*
     * Laravel Framework Service Providers...
     */
    Illuminate\Auth\AuthServiceProvider::class,
    Illuminate\Broadcasting\BroadcastServiceProvider::class,
    Illuminate\Bus\BusServiceProvider::class,
    Illuminate\Cache\CacheServiceProvider::class,
    Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
    Illuminate\Cookie\CookieServiceProvider::class,
    Illuminate\Database\DatabaseServiceProvider::class,
    Illuminate\Encryption\EncryptionServiceProvider::class,
    Illuminate\Filesystem\FilesystemServiceProvider::class,
    Illuminate\Foundation\Providers\FoundationServiceProvider::class,
    Illuminate\Hashing\HashServiceProvider::class,
    Illuminate\Mail\MailServiceProvider::class,
    Illuminate\Notifications\NotificationServiceProvider::class,
    Illuminate\Pagination\PaginationServiceProvider::class,
    Illuminate\Pipeline\PipelineServiceProvider::class,
    Illuminate\Queue\QueueServiceProvider::class,
    Illuminate\Redis\RedisServiceProvider::class,
    Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
    Illuminate\Session\SessionServiceProvider::class,
    Illuminate\Translation\TranslationServiceProvider::class,
    Illuminate\Validation\ValidationServiceProvider::class,
    Illuminate\View\ViewServiceProvider::class,

    /*
     * Package Service Providers...
     */
    Intervention\Image\ImageManagerServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,

    /*
     * Application Service Providers...
     */
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Providers\ViewServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
    App\Providers\TranslationServiceProvider::class,
    App\Providers\ValidationServiceProvider::class,
    App\Providers\MailServiceProvider::class,
    App\Providers\EventLogServiceProvider::class,

    /*
     * Module Service Providers...
     */
    Modules\Client\Providers\ClientServiceProvider::class,
    Modules\Sales\Providers\SalesServiceProvider::class,
    Modules\HR\Providers\HRServiceProvider::class,
    Modules\Stock\Providers\StockServiceProvider::class,
    Modules\Account\Providers\AccountServiceProvider::class,
    Modules\Purchases\Providers\PurchasesServiceProvider::class,
    Modules\RentalManagement\Providers\RentalManagementServiceProvider::class,
    Modules\Sitting\Providers\SittingServiceProvider::class,
    Modules\Branches\Providers\BranchesServiceProvider::class,
    Modules\Reports\Providers\ReportsServiceProvider::class,

    Modules\UserGuide\Providers\UserGuideServiceProvider::class,
    Modules\Subscriptions\Providers\SubscriptionsServiceProvider::class,
    Modules\Pos\Providers\PosServiceProvider::class,
    Modules\TaskManager\Providers\TaskManagerServiceProvider::class,
    Modules\OnlineStore\Providers\OnlineStoreServiceProvider::class,
];
