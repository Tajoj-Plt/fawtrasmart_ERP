<?php

use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\Tracking\TrackingController;

/*
|--------------------------------------------------------------------------
| تتبّع الموقع — /api/integration/v1/tracking
|--------------------------------------------------------------------------
|
| نفس صلاحيات الموقع: إرسال الموقع `clients_itinerary_edit`
| (`itinerary.update-location`)، وعرض المسار والفريق
| `clients_itinerary_view|clients_view_by_daily_itinerary` (`itinerary.map-data`).
| ومفتاح «العملاء» في شاشة التطبيقات، ومفتاح «تتبّع خط السير» داخل المتحكّم.
|
*/

Route::prefix('tracking')->name('tracking.')->controller(TrackingController::class)
    ->middleware('module:customers')->group(function () {
        Route::post('location', 'store')->middleware(['permission.role:clients_itinerary_edit', 'throttle:30,1'])->name('location');
        Route::get('path', 'path')->middleware('permission.role:clients_itinerary_view|clients_view_by_daily_itinerary')->name('path');
        Route::get('team', 'team')->middleware('permission.role:clients_itinerary_view|clients_view_by_daily_itinerary')->name('team');
    });
