<?php

use Illuminate\Support\Facades\Route;
// كان الاستيراد من `App\Http\Controllers\Reports` — وهو مجلّدٌ
// لا وجود له في المشروع. فالمسار يُنتج 500 عند أوّل طلب، و`route:list`
// يسقط كلّه عند تحليله. الفئة موجودة في وحدة التقارير.
use Modules\Reports\Http\Controllers\SMSController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

require __DIR__ . '/auth.php';

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath','check.branch']
    ],
    function () {
        Route::prefix('reports_SMS')->group(function () {
            // صفحة الفهرس
            Route::get('/index', [SMSController::class, 'index'])->name('reports.sms.index');

            // صفحة الشيكات المسلمة
            Route::get('/Campaigns', [SMSController::class, 'Campaigns'])->name('reports.sms.Campaigns');

            // صفحة الشيكات المستلمة
            Route::get('/log', [SMSController::class, 'log'])->name('reports.sms.log');
        });
    }
);
