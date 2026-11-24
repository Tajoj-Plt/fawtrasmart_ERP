<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;




use Modules\Reports\Http\Controllers\EmployeesController;
use Modules\Reports\Http\Controllers\RentalsController;



use Modules\Reports\Http\Controllers\UnitTrackReport;

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch'],
    ],
    function () {
        Route::prefix('Reports')
            ->middleware(['auth'])
            ->group(function () {

                // تقارير الموظفين — نفس صلاحية HRReportsController؛ كانت
                // بلا مسارات إطلاقاً، فأيّ رابطٍ في employees/index.blade.php
                // كان يُسقط الصفحة بـRouteNotFoundException فور تحميلها.
                Route::prefix('employees')
                    ->middleware(['permission.role:hr_reports.employees'])
                    ->group(function () {
                        Route::get('/index', [EmployeesController::class, 'index'])->name('reports.employees.index');
                        Route::get('/residences', [EmployeesController::class, 'Residences'])->name('reports.employees.Residences');
                        Route::get('/attendance', [EmployeesController::class, 'attendance'])->name('reports.employees.attendance');
                        Route::get('/attendance-multiple', [EmployeesController::class, 'attendancemultiple'])->name('reports.employees.attendance_multiple');
                        Route::get('/leaves', [EmployeesController::class, 'Leaves'])->name('reports.employees.Leaves');
                        Route::get('/shift', [EmployeesController::class, 'shift'])->name('reports.employees.shift');
                        Route::get('/attendance-by-employee', [EmployeesController::class, 'AttendancebyEmployee'])->name('reports.employees.Attendance_by_Employee');
                        Route::get('/attendance-by-day', [EmployeesController::class, 'AttendancebyDay'])->name('reports.employees.Attendance-by-Day');
                        Route::get('/attendance-by-week', [EmployeesController::class, 'AttendancebyWeek'])->name('reports.employees.attendance-by-week');
                        Route::get('/attendance-by-month', [EmployeesController::class, 'AttendancebyMonth'])->name('reports.employees.attendance-by-month');
                        Route::get('/attendance-by-year', [EmployeesController::class, 'AttendancebyYear'])->name('reports.employees.attendance-by-year');
                        Route::get('/attendance-by-department', [EmployeesController::class, 'AttendancebyDepartment'])->name('reports.employees.attendance-by-department');
                        Route::get('/salaries-by-branch', [EmployeesController::class, 'byBranch'])->name('reports.employees.Salaries_by_Branch');
                        Route::get('/advances', [EmployeesController::class, 'Advances'])->name('reports.employees.Advances');
                        Route::get('/contracts', [EmployeesController::class, 'Contracts'])->name('reports.employees.Contracts');
                    });
            });




    // تم نقل توجيهات GeneralAccountReports إلى موديول Account




        Route::prefix('reportsProduction')->group(function () {
            Route::get('/production-index', [RentalsController::class, 'production'])->name('ReportsProduction.index');
        });

        // Route::prefix('employee')->group(function () {
        //     Route::get('/employee-index', [EmployeeReportController::class, 'index'])->name('employeeReports.index');
        // });
    },
);





                Route::prefix('unit-track')->group(function () {
            Route::get('/index', [UnitTrackReport::class, 'index'])->name('unit-track.index');
  Route::get('/unit-track', [UnitTrackReport::class, 'unitsReport'])
         ->name('unit-track.unitsReport');
    // AJAX endpoint لجلب بيانات التقرير
    Route::get('/unit-track/ajax', [UnitTrackReport::class, 'unitsReportAjax'])
         ->name('unit-track.unitsReportAjax');

        });

