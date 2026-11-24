<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/*
|--------------------------------------------------------------------------
| مسارات المتجر القديمة
|--------------------------------------------------------------------------
|
| كانت هذه المسارات تشير إلى متحكّمَين حُذفا (`ContentManagementController`
| و`SettingsStoreController`)، فكلّ رابطٍ إليها — ومنها «إدارة المحتوى» في
| القائمة الرئيسية — يعطي خطأ 500. وكان أحدها يحمل الاسم `store_settings.index`
| نفسَه الذي تحمله شاشة الإعدادات الحقيقية في وحدة المتجر.
|
| الشاشات الحقيقية كلُّها في `Modules/OnlineStore/routes/web.php`. تبقى
| العناوين القديمة هنا تحويلاً إليها كي لا ينكسر رابطٌ محفوظ، وبأسماءٍ
| لا تزاحم أسماء الوحدة.
|
*/

require __DIR__.'/auth.php';

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch'],
    ], function () {
        Route::prefix('online_store')->middleware(['auth'])->group(function () {
            $to = fn (string $name, string $fallback = 'dashboard') => fn () => redirect()->route(Route::has($name) ? $name : $fallback);

            // إدارة المحتوى ← صفحات المتجر وبانراته
            Route::prefix('content-management')->group(function () use ($to) {
                Route::get('/index', $to('store_pages.index'))->name('content_management.index');
                Route::get('/media', $to('store_banners.index'))->name('content_management.media');
                Route::get('/edit/media', $to('store_banners.index'))->name('content_management.media_edit');
                Route::get('/page/management', $to('store_pages.index'))->name('content_management.page_management');
                Route::get('/page/management/create', $to('store_pages.create', 'store_pages.index'))->name('content_management.page_management_create');
            });

            // إعدادات المتجر القديمة ← شاشة الإعدادات في الوحدة
            Route::prefix('store_settings')->group(function () use ($to) {
                Route::get('/index', $to('store_settings.index'))->name('store_settings.legacy.index');
                Route::get('/template', $to('store_settings.index'))->name('store_settings.template');
                Route::get('/preview', $to('store_settings.index'))->name('store_settings.template.preview');
            });
        });
    });
