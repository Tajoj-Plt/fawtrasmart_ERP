<?php

use App\Constants\planFeatures\PurchasesPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Purchases\Http\Controllers\PurchasesController;

/*
 * مسارات واجهة المشتريات.
 *
 * **حارسُ الباقة لازمٌ هنا كلزومه في `web.php`**: مجموعة `api` لا تمرّ
 * بمجموعة `web` ولا بحُرّاسها، وقفلٌ يحرس الشاشة ويترك الواجهة مفتوحة
 * يُلتفّ عليه بطلبٍ واحد. وكانت هذه المسارات الخمسة بلا حارسٍ إطلاقاً،
 * كما كانت الوحدة كلُّها.
 *
 * و`plan.limit` على الإنشاء وحده، مسجَّلاً **قبل** `apiResource`: أوّل
 * مسارٍ مطابق هو الذي يعمل، فلو سُجّل بعدها لالتقطت هي `POST` أوّلاً.
 * ولا يُوضع على `update` ولا `destroy` — من بلغ سقفه يحتاج الحذف ليخرج
 * منه.
 *
 * ⚠ `PurchasesController` **غير موجود** في هذه الوحدة (لا ملفَّ له في
 * `Http/Controllers/`) — كنظيره في المبيعات سواءً. فهذه المسارات ترمي
 * عند أوّل نداء منذ ما قبل هذا التغيير، والحراسة مركَّبةٌ لتكون صحيحةً
 * يوم يُكتب المتحكّم لا لتُصلح غيابه.
 *
 * @see \Modules\Sales\routes\api النظير في المبيعات
 */
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('purchases', [PurchasesController::class, 'store'])
        ->name('purchases.store')
        ->middleware([
            'plan:'.PurchasesPlanFeatures::INVOICES,
            'plan.limit:'.PurchasesPlanFeatures::LIMIT_INVOICES,
        ]);

    Route::apiResource('purchases', PurchasesController::class)
        ->except('store')
        ->names('purchases')
        ->middleware('plan:'.PurchasesPlanFeatures::INVOICES);
});
