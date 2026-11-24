<?php

use App\Constants\planFeatures\StockPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Stock\Http\Controllers\StockController;
use Modules\Stock\Http\Controllers\Stock\BrandApiController;

/*
|--------------------------------------------------------------------------
| واجهة المخزون البرمجية
|--------------------------------------------------------------------------
|
| **حدود الباقة تُفرض هنا كما تُفرض على الشاشات.** مسارات `api` لا تمرّ
| بمجموعة `web` ولا بحُرّاسها، فقفلٌ يحرس الشاشة ويترك الواجهة مفتوحة
| يُلتفّ عليه بطلبٍ واحد من أي عميل — ويعمل النظام كاملاً من خلفه.
|
| و`apiResource` تسجّل `store` و`update` و`destroy` مع `index`/`show` في
| سطرٍ واحد، فحارسُ العرض وحده لا يكفي: `plan.limit` على المجموعة كلّها
| كان سيمنع القراءة أيضاً، ولهذا يُفرَد `store` بحدّه ويُترك الباقي لحارس
| الميزة وحده.
|
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('stocks', StockController::class)
        ->names('stock')
        ->middleware('plan:'.implode('|', StockPlanFeatures::stockOnly()));

    // سقف العلامات التجارية يُفحص على الإنشاء وحده — القراءة والتعديل
    // والحذف لا تزيد العدد، ومنعُها ببلوغ السقف يقفل باب الخروج منه.
    //
    // ويُسجَّل **قبل** `apiResource` ويُستثنى منها: أوّل مسارٍ مطابق هو
    // الذي يعمل في Laravel، فلو جاء بعدها لَما وصله طلبٌ قطّ — حارسٌ
    // مكتوبٌ لا يُنفَّذ
    Route::post('brands', [BrandApiController::class, 'store'])
        ->name('brands.store')
        ->middleware(['plan:'.StockPlanFeatures::BRANDS, 'plan.limit:'.StockPlanFeatures::LIMIT_BRANDS]);

    Route::apiResource('brands', BrandApiController::class)
        ->names('brands')
        ->except('store')
        ->middleware('plan:'.StockPlanFeatures::BRANDS);
});

/*
    بنود قائمة المواد — **نُقلت إلى `routes/api.php` الجذر**.

    كانت هنا نسخةٌ ثانية من المسار نفسِه بينما الجذر يحمل نسخةً ثالثة
    إغلاقاً. والجذر يُحمَّل أوّلاً، وأوّلُ مسارٍ مطابق هو الذي يعمل — فما
    كان يُكتب هنا من حرّاس لا يصله طلبٌ قطّ.

    النسخةُ الحيّة الوحيدة الآن — بالمصادقة والصلاحية وحدّ الباقة —
    في `routes/api.php` باسم `api.production-materials.items`.
*/
