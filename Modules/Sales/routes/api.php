<?php

use App\Constants\planFeatures\SalesPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesController;

/*
 * مسارات واجهة المبيعات.
 *
 * **حارسُ الباقة لازمٌ هنا كلزومه في `web.php`**: مجموعة `api` لا تمرّ
 * بمجموعة `web` ولا بحُرّاسها، وقفلٌ يحرس الشاشة ويترك الواجهة مفتوحة
 * يُلتفّ عليه بطلبٍ واحد.
 *
 * و`plan.limit` على الإنشاء وحده، مسجَّلاً **قبل** `apiResource`: أوّل
 * مسارٍ مطابق هو الذي يعمل، فلو سُجّل بعدها لالتقطت هي `POST` أوّلاً.
 * ولا يُوضع على `update` ولا `destroy` — من بلغ سقفه يحتاج الحذف ليخرج منه.
 *
 * ⚠ `SalesController` **غير موجود** في هذه الوحدة (لا ملفَّ له في
 * `Http/Controllers/`). فهذه المسارات ترمي عند أوّل نداء منذ ما قبل هذا
 * التغيير — الحراسة مركَّبةٌ لتكون صحيحةً يوم يُكتب المتحكّم، لا لتُصلح
 * غيابه.
 */
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('sales', [SalesController::class, 'store'])
        ->name('sales.store')
        ->middleware(['plan:'.SalesPlanFeatures::INVOICES, 'plan.limit:'.SalesPlanFeatures::LIMIT_INVOICES]);

    Route::apiResource('sales', SalesController::class)
        ->except('store')
        ->names('sales')
        ->middleware('plan:'.SalesPlanFeatures::INVOICES);
});
