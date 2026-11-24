<?php

use App\Http\Controllers\CustomerAttendance\CustomerAttendanceController;
// كان الاستيراد من `App\Http\Controllers\Reports` — وهو مجلّدٌ
// لا وجود له في المشروع. فالمسار يُنتج 500 عند أوّل طلب، و`route:list`
// يسقط كلّه عند تحليله. الفئة موجودة في وحدة التقارير.
use Modules\Reports\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;
// كان الاستيراد من `App\Http\Controllers\Reports` — وهو مجلّدٌ
// لا وجود له في المشروع. فالمسار يُنتج 500 عند أوّل طلب، و`route:list`
// يسقط كلّه عند تحليله. الفئة موجودة في وحدة التقارير.
// استيرادٌ لفئةٍ غير موجودة ولا يُستعمل في هذا الملفّ — أُزيل.
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

require __DIR__ . '/auth.php';

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch']
    ],
    function () {
        Route::prefix('Customer_Attendance')->group(function () {
            Route::get('/', [CustomerAttendanceController::class, 'index'])->name('customer_attendance.index');
            Route::post('/store', [CustomerAttendanceController::class, 'store'])->name('customer_attendance.store');
            Route::put('/update/{id}', [CustomerAttendanceController::class, 'update'])->name('customer_attendance.update');
            Route::delete('/delete/{id}', [CustomerAttendanceController::class, 'destroy'])->name('customer_attendance.destroy');
        });
    }
);
