<?php

use Illuminate\Support\Facades\Route;
// كان الاستيراد من `App\Http\Controllers\Reports` — وهو مجلّدٌ
// لا وجود له في المشروع. فالمسار يُنتج 500 عند أوّل طلب، و`route:list`
// يسقط كلّه عند تحليله. الفئة موجودة في وحدة التقارير.
use Modules\Account\Http\Controllers\ChecksController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

require __DIR__ . '/auth.php';

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath','check.branch']
    ],
    function () {
        Route::prefix('reports_checks')->middleware(['auth'])->group(function () {
            // صفحة الفهرس
            Route::get('/index', [ChecksController::class, 'index'])->name('reports_Checks.index');

            // صفحة الشيكات المسلمة
            Route::get('/delivered', [ChecksController::class, 'delivered'])->name('reports.Checks.delivered-checks');

            // صفحة الشيكات المستلمة
            Route::get('/received', [ChecksController::class, 'received'])->name('reports.Checks.received-checks');
        });
    }
);
