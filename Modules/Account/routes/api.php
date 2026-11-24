<?php

use App\Constants\planFeatures\AccountPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Account\Http\Controllers\AccountController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    /*
        حدّ الباقة على الواجهة كما على الشاشة: مسارات `api` لا تمرّ بمجموعة
        `web` ولا بحُرّاسها، وقفلٌ يحرس الشاشة ويترك الواجهة مفتوحة يُلتفّ
        عليه بطلبٍ واحد.
    */
    Route::apiResource('accounts', AccountController::class)
        ->names('account')
        ->middleware('plan:'.AccountPlanFeatures::CHART_OF_ACCOUNTS);
});
