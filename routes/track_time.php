<?php

use App\Http\Controllers\Task\TaskController;
use Illuminate\Support\Facades\Route;
// كانت هذه الاستيرادات من `App\Http\Controllers\Hr` — ولا مجلّد بهذا
// الاسم. الفئات الثلاث في وحدة الموارد البشرية.
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Http\Controllers\ShiftManagementController;
// كان الاستيراد من `App\Http\Controllers\Reports` — وهو مجلّدٌ
// لا وجود له في المشروع. فالمسار يُنتج 500 عند أوّل طلب، و`route:list`
// يسقط كلّه عند تحليله. الفئة موجودة في وحدة التقارير.
use App\Http\Controllers\TrackTime\ActivitiesTrackTimeController;
use App\Http\Controllers\TrackTime\AverageHoursTrackTimeController;
use App\Http\Controllers\TrackTime\ProjectTrackTimeController;
use App\Http\Controllers\TrackTime\SittingTrackTimeController;
use App\Http\Controllers\TrackTime\TrackTimeController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

require __DIR__ . '/auth.php';


Route::group(

    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath','check.branch']
    ], function(){

        /*
            تتبّعُ الوقت — كلُّ شاشةٍ بصلاحيتها (`time_entries.*` / `time_projects.*` /
            `time_activities.*`)، و«على أيّ موظفين» يحدّده نطاقُ الرؤية في منتقيات
            الموظفين. لا صلاحيةَ «ساعات الموظفين الآخرين»: من يرى ساعاتِه وحده
            يحمل `time_entries.index` ونطاقُه `data_scope_own`.

            كان `TrackTime.index` و`TrackTime.create_invoice_time` يشيران إلى
            `Modules\Reports\...\TimeTrackingController` — متحكّمِ التقارير الذي
            لا يعرف `create_invoice_time` ولا يعرض شاشةَ الساعات، فالمساران
            يسقطان بخطأ. والشاشاتُ ومتحكّماتُها قائمةٌ في `App\Http\Controllers\TrackTime`.
        */
        Route::prefix('TimeTracking')->middleware(['auth'])->group(function () {

            # ساعات العمل
            Route::prefix('TimeTracking')->group(function () {
                Route::get('/index', [TrackTimeController::class, 'index'])->name('TrackTime.index')
                    ->middleware('permission.role:time_entries.index');
                Route::get('/show', [TrackTimeController::class, 'show'])->name('TrackTime.show')
                    ->middleware('permission.role:time_entries.index');
                Route::get('/create_invoice_time', [TrackTimeController::class, 'create_invoice_time'])->name('TrackTime.create_invoice_time')
                    ->middleware('permission.role:time_entries.create');
            });

            # الإعدادات: المشاريع والأنشطة ومعدّل الساعات
            Route::prefix('settings')->group(function () {
                Route::get('/', [SittingTrackTimeController::class, 'index'])->name('SittingTrackTime.index')
                    ->middleware('permission.role:time_projects.create|time_projects.edit|time_projects.destroy|time_activities.create|time_activities.edit|time_activities.destroy|time_entries.edit');
                Route::get('/general', [SittingTrackTimeController::class, 'create'])->name('SittingTrackTime.create')
                    ->middleware('permission.role:time_projects.edit|time_activities.edit');

                Route::get('/projects', [ProjectTrackTimeController::class, 'index'])->name('ProjectTrackTime.index')
                    ->middleware('permission.role:time_projects.create|time_projects.edit|time_projects.destroy');
                Route::get('/projects/create', [ProjectTrackTimeController::class, 'create'])->name('ProjectTrackTime.create')
                    ->middleware('permission.role:time_projects.create');

                Route::get('/activities', [ActivitiesTrackTimeController::class, 'index'])->name('Activities.index')
                    ->middleware('permission.role:time_activities.create|time_activities.edit|time_activities.destroy');
                Route::get('/activities/create', [ActivitiesTrackTimeController::class, 'create'])->name('Activities.create')
                    ->middleware('permission.role:time_activities.create');

                Route::get('/average-hours', [AverageHoursTrackTimeController::class, 'index'])->name('AverageHours.index')
                    ->middleware('permission.role:time_entries.edit');
                Route::get('/average-hours/create', [AverageHoursTrackTimeController::class, 'create'])->name('AverageHours.create')
                    ->middleware('permission.role:time_entries.edit');
                Route::get('/average-hours/edit/{id}', [AverageHoursTrackTimeController::class, 'edit'])->name('AverageHours.edit')
                    ->middleware('permission.role:time_entries.edit');
            });
        });

});
