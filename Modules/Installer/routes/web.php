<?php

use Illuminate\Support\Facades\Route;
use Modules\Installer\Http\Controllers\InstallerController;
use Modules\Installer\Http\Middleware\RedirectIfInstalled;

/*
|--------------------------------------------------------------------------
| مسارات معالج التثبيت
|--------------------------------------------------------------------------
|
| هذه المسارات **لا** تستعمل مجموعة `web` كاملة، وذلك مقصود: تلك المجموعة
| تضمّ CheckIncompleteVisits وEnsureModuleEnabled وكلاهما يستعلم قاعدة
| البيانات — أي أن الصفحة التي وُجدت لتُنشئ قاعدة البيانات كانت ستنهار
| لغيابها. فالمكدَّس هنا مقصور على ما يلزم النموذج: كوكي وجلسة وأخطاء وCSRF.
|
| التسجيل يتم في RouteServiceProvider الخاص بالوحدة.
*/

Route::middleware([
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    RedirectIfInstalled::class,
])
    ->prefix('install')
    ->name('installer.')
    ->group(function () {
        Route::get('/', [InstallerController::class, 'welcome'])->name('welcome');

        Route::get('requirements', [InstallerController::class, 'requirements'])->name('requirements');

        // ---- تفعيل الاشتراك: قبل قاعدة البيانات ----
        Route::get('activation', [InstallerController::class, 'activation'])->name('activation');
        Route::post('activation', [InstallerController::class, 'storeActivation'])->name('activation.store');

        // الفحص الحيّ يفتح اتصالاً بخادمٍ يختاره الطالب — محدود المعدّل
        // كنظيره في قاعدة البيانات، وللسبب نفسه
        Route::post('activation/verify', [InstallerController::class, 'verifyActivation'])
            ->middleware('throttle:20,1')->name('activation.verify');

        Route::get('database', [InstallerController::class, 'database'])->name('database');

        // تحديد المعدّل على المسارين اللذين يفتحان اتصالاً بخادمٍ يختاره
        // الطالب: بلا حدٍّ يصلح المعالجُ ماسحَ منافذ للشبكة الداخلية — يُجرَّب
        // كل عنوان ومنفذ، ورسالةُ الخطأ تفرّق بين «رُفض الاتصال» و«لا استجابة»
        // فتكشف ما يعمل خلف الجدار. والمعالج مكشوف بلا مصادقة قبل التثبيت
        Route::middleware('throttle:20,1')->group(function () {
            Route::post('database', [InstallerController::class, 'storeDatabase'])->name('database.store');
            Route::post('database/test', [InstallerController::class, 'testDatabase'])->name('database.test');
        });

        Route::get('settings', [InstallerController::class, 'settings'])->name('settings');
        Route::post('settings', [InstallerController::class, 'storeSettings'])->name('settings.store');

        Route::get('admin', [InstallerController::class, 'admin'])->name('admin');
        Route::post('admin', [InstallerController::class, 'storeAdmin'])->name('admin.store');

        Route::get('review', [InstallerController::class, 'review'])->name('review');

        // التنفيذ على نبضات: `run` يعرض الشاشة، و`run/start` يبني الخطة،
        // و`run/step` ينفّذ مهمةً واحدة في كل نداء. بلا هذا التقسيم كان
        // التثبيت طلباً واحداً يتجاوز كل المهل وينقطع في منتصفه
        Route::match(['get', 'post'], 'run', [InstallerController::class, 'run'])->name('run');
        Route::post('run/start', [InstallerController::class, 'runStart'])->name('run.start');
        Route::post('run/step', [InstallerController::class, 'runStep'])->name('run.step');

        Route::get('finished', [InstallerController::class, 'finished'])->name('finished');
    });
