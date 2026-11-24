<?php

use App\Constants\planFeatures\SalesPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\Notifications\NotificationController;

/*
|--------------------------------------------------------------------------
| الإشعارات الداخلية — /api/integration/v1/notifications
|--------------------------------------------------------------------------
|
| نفس بوّابات شاشة الإشعارات في الموقع (Modules/Sales/routes/web.php):
| ميزة الباقة `plan:notifications`، والعرض/القراءة بـ`sales.notifications.view`،
| والردّ بـ`sales.notifications.reply` — ومعها صلاحيتا الكتالوج الداخليّتان اللتان
| يفحصهما متحكّم الموقع (عرض القائمة، الردّ)، ومالك `data_scope_all` يتجاوزهما.
| لا إشعارات دفع (Push).
|
*/

Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)
    ->middleware('plan:'.SalesPlanFeatures::NOTIFICATIONS)->group(function () {
        Route::get('/', 'index')->middleware(['permission.role:sales.notifications.view', 'permission.role:notifications_view_all_notifications|data_scope_all'])->name('index');
        Route::get('unread-count', 'unreadCount')->middleware('permission.role:sales.notifications.view')->name('unread-count');
        Route::post('read-all', 'markAllRead')->middleware('permission.role:sales.notifications.view')->name('read-all');
        Route::get('{notification}', 'show')->whereNumber('notification')->middleware('permission.role:sales.notifications.view')->name('show');
        Route::post('{notification}/read', 'markRead')->whereNumber('notification')->middleware('permission.role:sales.notifications.view')->name('read');
        Route::post('{notification}/reply', 'reply')->whereNumber('notification')->middleware(['permission.role:sales.notifications.reply', 'permission.role:notifications_reply_to_notifications|data_scope_all'])->name('reply');
    });
