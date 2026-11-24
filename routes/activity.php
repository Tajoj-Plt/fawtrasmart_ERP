<?php

use App\Http\Controllers\Dashboard\AboFalehController;
use Illuminate\Support\Facades\Route;
// كان الاستيراد من `App\Http\Controllers\Reports` — وهو مجلّدٌ
// لا وجود له في المشروع. فالمسار يُنتج 500 عند أوّل طلب، و`route:list`
// يسقط كلّه عند تحليله. الفئة موجودة في وحدة التقارير.
use Modules\Reports\Http\Controllers\ActivityController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

require __DIR__ . '/auth.php';

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath','check.branch']
    ],
    function () {
        Route::prefix('reports.Activity')->middleware(['auth', 'role:manager'])->group(function () {
            // صفحة الفهرس
            Route::get('/index', [ActivityController::class, 'index'])->name('reports.Activity.index');



        });

        Route::get('/index', [AboFalehController::class, 'index'])->name('ABO_FALEH.index');
        Route::get('/trackReprt', [AboFalehController::class, 'reportTrac'])->name('ABO_FALEH.reportTrac');
        Route::get('/storehouse-report', [AboFalehController::class, 'storehouseReport'])->name('ABO_FALEH.storehouseReport');
    }
);
