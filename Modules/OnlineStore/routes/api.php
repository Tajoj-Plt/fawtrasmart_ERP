<?php

use Illuminate\Support\Facades\Route;
use Modules\OnlineStore\Http\Controllers\Api\LocationController;
use Modules\OnlineStore\Http\Controllers\Api\StoreAddressController;
use Modules\OnlineStore\Http\Controllers\Api\StoreAuthController;
use Modules\OnlineStore\Http\Controllers\Api\StoreBannerController;
use Modules\OnlineStore\Http\Controllers\Api\StoreBrandController;
use Modules\OnlineStore\Http\Controllers\Api\StoreCartController;
use Modules\OnlineStore\Http\Controllers\Api\StoreCategoryController;
use Modules\OnlineStore\Http\Controllers\Api\StoreCouponController;
use Modules\OnlineStore\Http\Controllers\Api\StoreHomeController;
use Modules\OnlineStore\Http\Controllers\Api\StoreOrderController;
use Modules\OnlineStore\Http\Controllers\Api\StorePageController;
use Modules\OnlineStore\Http\Controllers\Api\StorePaymentCardsController;
use Modules\OnlineStore\Http\Controllers\Api\StoreProductController;
use Modules\OnlineStore\Http\Controllers\Api\StoreQuestionController;
use Modules\OnlineStore\Http\Controllers\Api\StoreRecentlyViewedController;
use Modules\OnlineStore\Http\Controllers\Api\StoreReviewController;
use Modules\OnlineStore\Http\Controllers\Api\StoreShippingController;
use Modules\OnlineStore\Http\Controllers\Api\StoreWishlistController;
use Modules\OnlineStore\Http\Middleware\NormalizeStoreInput;
use Modules\OnlineStore\Http\Middleware\ThrottleStoreAuth;
use Modules\Sales\Http\Controllers\SalesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('sales', SalesController::class)->names('sales');
});

/*
|--------------------------------------------------------------------------
| Store Public API Routes - توجيهات المتجر العامة
|--------------------------------------------------------------------------
| يستخدم guard 'client' خاص بعملاء المتجر الإلكتروني
| auth.client - يتطلب تسجيل دخول العميل
| auth.client.optional - اختياري (يعمل للزوار والعملاء المسجلين)
|--------------------------------------------------------------------------
*/
// معرّفاتُ المسارات أرقامٌ فقط (حتى 18 خانة): نصٌّ أو رقمٌ ضخم في المسار كان يصل إلى متحكّمٍ يتوقّع int فيسقط بـ500 — الآن 404 نظيف
Route::prefix('store')->middleware(NormalizeStoreInput::class)->where([
    'id' => '[0-9]{1,18}',
    'itemId' => '[0-9]{1,18}',
    'productId' => '[0-9]{1,18}',
    'categoryId' => '[0-9]{1,18}',
    'brandId' => '[0-9]{1,18}',
    'parentId' => '[0-9]{1,18}',
    'countryId' => '[0-9]{1,18}',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Home & Shop Routes - الصفحة الرئيسية والمتجر (عامة)
    |--------------------------------------------------------------------------
    */
    Route::get('/', [StoreHomeController::class, 'index']);                    // الصفحة الرئيسية
    Route::get('/products', [StoreHomeController::class, 'products']);         // المنتجات مع فلترة وبحث و pagination
    Route::get('/filters', [StoreHomeController::class, 'filters']);           // بيانات الفلاتر
    Route::get('/search', [StoreHomeController::class, 'search']);             // البحث السريع (autocomplete)
    Route::get('/settings', [StoreHomeController::class, 'settings']);         // إعدادات المتجر
    Route::get('/menu-categories', [StoreHomeController::class, 'menuCategories']); // أصناف القائمة
    Route::get('/banners', [StoreHomeController::class, 'banners']);           // البانرات/الإعلانات

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes - توجيهات المصادقة (guard: client)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        // التوجيهات العامة (بدون تسجيل دخول)
        // حدٌّ للمحاولات لكل عنوان: تخمين كلمات المرور/رموز التحقق وإغراق الحسابات والبريد
        Route::post('/register', [StoreAuthController::class, 'register'])->middleware(ThrottleStoreAuth::class.':10,1');          // تسجيل حساب جديد
        Route::post('/login', [StoreAuthController::class, 'login'])->middleware(ThrottleStoreAuth::class.':10,1');                // تسجيل الدخول
        Route::post('/forgot-password', [StoreAuthController::class, 'forgotPassword'])->middleware(ThrottleStoreAuth::class.':5,1'); // نسيت كلمة المرور
        Route::post('/reset-password', [StoreAuthController::class, 'resetPassword'])->middleware(ThrottleStoreAuth::class.':10,1');   // إعادة تعيين كلمة المرور

        // توجيهات تتطلب تسجيل دخول العميل
        Route::middleware('auth.client')->group(function () {
            // تسجيل الخروج
            Route::post('/logout', [StoreAuthController::class, 'logout']);             // تسجيل الخروج
            Route::post('/logout-all', [StoreAuthController::class, 'logoutAll']);      // تسجيل الخروج من جميع الأجهزة

            // الملف الشخصي
            Route::get('/profile', [StoreAuthController::class, 'profile']);            // عرض الملف الشخصي
            Route::put('/profile', [StoreAuthController::class, 'updateProfile']);      // تحديث الملف الشخصي
            Route::post('/change-password', [StoreAuthController::class, 'changePassword']); // تغيير كلمة المرور

            // التحقق (OTP)
            Route::post('/verify-otp', [StoreAuthController::class, 'verifyOtp'])->middleware(ThrottleStoreAuth::class.':10,1');      // التحقق من رمز OTP
            Route::post('/resend-otp', [StoreAuthController::class, 'resendOtp'])->middleware(ThrottleStoreAuth::class.':3,1');      // إعادة إرسال رمز OTP

            // إدارة الأجهزة
            Route::get('/devices', [StoreAuthController::class, 'devices']);             // قائمة الأجهزة
            Route::post('/register-device', [StoreAuthController::class, 'registerDevice']); // تسجيل جهاز
            Route::delete('/devices/{deviceId}', [StoreAuthController::class, 'removeDevice']); // إزالة جهاز

            // التحقق من التوكن وحذف الحساب
            Route::get('/check-token', [StoreAuthController::class, 'checkToken']);     // التحقق من صلاحية التوكن
            Route::delete('/delete-account', [StoreAuthController::class, 'deleteAccount']); // حذف الحساب
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Categories Routes - توجيهات الفئات (عامة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('categories')->group(function () {
        Route::get('/', [StoreCategoryController::class, 'index']);
        Route::get('/tree', [StoreCategoryController::class, 'tree']);
        Route::get('/featured', [StoreCategoryController::class, 'featured']);
        Route::get('/menu', [StoreCategoryController::class, 'menu']);
        Route::get('/search', [StoreCategoryController::class, 'search']);
        Route::get('/{identifier}', [StoreCategoryController::class, 'show']);
        Route::get('/{parentId}/children', [StoreCategoryController::class, 'children']);
        Route::get('/{id}/breadcrumb', [StoreCategoryController::class, 'breadcrumb']);
    });

    /*
    |--------------------------------------------------------------------------
    | Products Routes - توجيهات المنتجات (عامة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('products')->group(function () {
        Route::get('/', [StoreProductController::class, 'index']);
        Route::get('/featured', [StoreProductController::class, 'featured']);
        Route::get('/new-arrivals', [StoreProductController::class, 'newArrivals']);
        Route::get('/best-sellers', [StoreProductController::class, 'bestSellers']);
        Route::get('/on-sale', [StoreProductController::class, 'onSale']);
        Route::get('/autocomplete', [StoreProductController::class, 'autocomplete']);
        Route::get('/filters', [StoreProductController::class, 'filters']);
        Route::get('/by-category/{categoryId}', [StoreProductController::class, 'byCategory']);
        Route::get('/by-brand/{brandId}', [StoreProductController::class, 'byBrand']);
        Route::get('/related/{productId}', [StoreProductController::class, 'related']);
        Route::get('/{identifier}', [StoreProductController::class, 'show'])->where('identifier', '[0-9]{1,18}');
    });

    /*
    |--------------------------------------------------------------------------
    | Brands Routes - توجيهات العلامات التجارية (عامة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('brands')->group(function () {
        Route::get('/', [StoreBrandController::class, 'index']);
        Route::get('/featured', [StoreBrandController::class, 'featured']);
        Route::get('/search', [StoreBrandController::class, 'search']);
        Route::get('/{identifier}', [StoreBrandController::class, 'show'])->where('identifier', '[0-9]{1,18}');
    });

    /*
    |--------------------------------------------------------------------------
    | Cart Routes - توجيهات السلة (عامة مع دعم العميل المسجل)
    |--------------------------------------------------------------------------
    */
    Route::prefix('cart')->middleware('auth.client.optional')->group(function () {
        Route::get('/', [StoreCartController::class, 'index']);
        Route::post('/items', [StoreCartController::class, 'addItem']);
        Route::put('/items/{itemId}', [StoreCartController::class, 'updateItem']);
        Route::delete('/items/{itemId}', [StoreCartController::class, 'removeItem']);
        Route::delete('/clear', [StoreCartController::class, 'clear']);
        Route::post('/coupon', [StoreCartController::class, 'applyCoupon']);
        Route::delete('/coupon', [StoreCartController::class, 'removeCoupon']);
        Route::post('/validate', [StoreCartController::class, 'validateCart']);
    });

    // دمج السلة بعد تسجيل الدخول (يتطلب مصادقة)
    Route::post('/cart/merge', [StoreCartController::class, 'mergeCart'])->middleware('auth.client');

    /*
    |--------------------------------------------------------------------------
    | Wishlist Routes - توجيهات قائمة الأمنيات (تتطلب تسجيل دخول العميل)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth.client')->prefix('wishlist')->group(function () {
        Route::get('/', [StoreWishlistController::class, 'index']);
        Route::post('/', [StoreWishlistController::class, 'add']);
        Route::delete('/{productId}', [StoreWishlistController::class, 'remove']);
        Route::post('/clear', [StoreWishlistController::class, 'clear']);
        Route::post('/move-to-cart/{productId}', [StoreWishlistController::class, 'moveToCart']);
        Route::get('/check/{productId}', [StoreWishlistController::class, 'check']);
        Route::post('/check-multiple', [StoreWishlistController::class, 'checkMultiple']);
        Route::get('/product-ids', [StoreWishlistController::class, 'getProductIds']);
        Route::get('/count', [StoreWishlistController::class, 'count']);
    });

    /*
    |--------------------------------------------------------------------------
    | Orders Routes - توجيهات الطلبات (تتطلب تسجيل دخول العميل)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth.client')->prefix('orders')->group(function () {
        Route::get('/', [StoreOrderController::class, 'index']);
        Route::post('/', [StoreOrderController::class, 'store']);
        Route::get('/{id}', [StoreOrderController::class, 'show']);
        Route::post('/{id}/cancel', [StoreOrderController::class, 'cancel']);
        Route::post('/{id}/reorder', [StoreOrderController::class, 'reorder']);
    });

    // حساب تكلفة الشحن (لا يتطلب تسجيل دخول)
    Route::post('/orders/calculate-shipping', [StoreOrderController::class, 'calculateShipping']);

    // تتبع الطلب (متاح للجميع - بدون تسجيل دخول)
    Route::get('/orders/track/{orderNumber}', [StoreOrderController::class, 'track']);

    /*
    |--------------------------------------------------------------------------
    | Addresses Routes - توجيهات العناوين (تتطلب تسجيل دخول العميل)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth.client')->prefix('addresses')->group(function () {
        Route::get('/', [StoreAddressController::class, 'index']);
        Route::post('/', [StoreAddressController::class, 'store']);
        Route::get('/{id}', [StoreAddressController::class, 'show']);
        Route::put('/{id}', [StoreAddressController::class, 'update']);
        Route::delete('/{id}', [StoreAddressController::class, 'destroy']);
        Route::post('/{id}/set-default', [StoreAddressController::class, 'setDefault']);
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Cards Routes - توجيهات بطاقات الدفع (تتطلب تسجيل دخول العميل)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth.client')->prefix('payment-cards')->group(function () {
        Route::get('/', [StorePaymentCardsController::class, 'index']);
        Route::post('/', [StorePaymentCardsController::class, 'store']);
        Route::get('/{id}', [StorePaymentCardsController::class, 'show']);
        Route::put('/{id}', [StorePaymentCardsController::class, 'update']);
        Route::delete('/{id}', [StorePaymentCardsController::class, 'destroy']);
        Route::post('/{id}/set-default', [StorePaymentCardsController::class, 'setDefault']);
    });

    /*
    |--------------------------------------------------------------------------
    | Reviews Routes - توجيهات المراجعات
    |--------------------------------------------------------------------------
    */
    Route::prefix('reviews')->group(function () {
        // عامة
        Route::get('/product/{productId}', [StoreReviewController::class, 'index']);
        Route::get('/product/{productId}/stats', [StoreReviewController::class, 'stats']);

        // تتطلب تسجيل دخول العميل
        Route::middleware('auth.client')->group(function () {
            Route::post('/', [StoreReviewController::class, 'store']);
            Route::put('/{id}', [StoreReviewController::class, 'update']);
            Route::delete('/{id}', [StoreReviewController::class, 'destroy']);
            Route::post('/{id}/helpful', [StoreReviewController::class, 'helpful']);
            Route::delete('/{id}/helpful', [StoreReviewController::class, 'unhelpful']);
            Route::get('/my-reviews', [StoreReviewController::class, 'myReviews']);
            Route::get('/can-review/{productId}', [StoreReviewController::class, 'canReview']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Questions Routes - توجيهات الأسئلة
    |--------------------------------------------------------------------------
    */
    Route::prefix('questions')->group(function () {
        // عامة
        Route::get('/product/{productId}', [StoreQuestionController::class, 'index']);

        // تتطلب تسجيل دخول العميل
        Route::middleware('auth.client')->group(function () {
            Route::post('/', [StoreQuestionController::class, 'store']);
            Route::get('/my-questions', [StoreQuestionController::class, 'myQuestions']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Shipping Routes - توجيهات الشحن (عامة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('shipping')->group(function () {
        Route::get('/methods', [StoreShippingController::class, 'index']);
        Route::get('/methods/{id}', [StoreShippingController::class, 'show']);
        Route::get('/default', [StoreShippingController::class, 'getDefaultSettings']);
        Route::post('/calculate', [StoreShippingController::class, 'calculate']);
        Route::post('/check-availability', [StoreShippingController::class, 'checkAvailability']);
    });

    /*
    |--------------------------------------------------------------------------
    | Coupons Routes - توجيهات الكوبونات
    |--------------------------------------------------------------------------
    */
    Route::prefix('coupons')->group(function () {
        // عامة
        Route::post('/validate', [StoreCouponController::class, 'validateCoupon']);
        Route::get('/{code}', [StoreCouponController::class, 'show']);

        // تتطلب تسجيل دخول العميل
        Route::get('/', [StoreCouponController::class, 'available'])->middleware('auth.client');
    });

    /*
    |--------------------------------------------------------------------------
    | Banners Routes - توجيهات البانرات (عامة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('banners')->group(function () {
        Route::get('/', [StoreBannerController::class, 'index']);
        Route::get('/sliders', [StoreBannerController::class, 'homeSliders']);
        Route::get('/sidebar', [StoreBannerController::class, 'sidebar']);
        Route::get('/position/{position}', [StoreBannerController::class, 'byPosition']);
    });

    /*
    |--------------------------------------------------------------------------
    | Pages Routes - توجيهات الصفحات (عامة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('pages')->group(function () {
        Route::get('/', [StorePageController::class, 'index']);
        Route::get('/privacy-policy', [StorePageController::class, 'privacyPolicy']);
        Route::get('/terms-conditions', [StorePageController::class, 'termsConditions']);
        Route::get('/return-policy', [StorePageController::class, 'returnPolicy']);
        Route::get('/shipping-policy', [StorePageController::class, 'shippingPolicy']);
        Route::get('/about-us', [StorePageController::class, 'aboutUs']);
        Route::get('/contact-us', [StorePageController::class, 'contactUs']);
        Route::get('/faqs', [StorePageController::class, 'faqs']);
        Route::get('/{identifier}', [StorePageController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Recently Viewed Routes - توجيهات المشاهدات الأخيرة
    |--------------------------------------------------------------------------
    */
    Route::prefix('recently-viewed')->middleware('auth.client.optional')->group(function () {
        Route::get('/', [StoreRecentlyViewedController::class, 'index']);
        Route::post('/', [StoreRecentlyViewedController::class, 'store']);
        Route::delete('/{productId}', [StoreRecentlyViewedController::class, 'destroy']);
        Route::delete('/', [StoreRecentlyViewedController::class, 'clear']);
        Route::get('/most-viewed', [StoreRecentlyViewedController::class, 'mostViewed']);
    });

    // دمج المشاهدات بعد تسجيل الدخول (يتطلب مصادقة)
    Route::post('/recently-viewed/merge', [StoreRecentlyViewedController::class, 'merge'])->middleware('auth.client');

    /*
    |--------------------------------------------------------------------------
    | Locations Routes - توجيهات المواقع (الدول والمدن) - عامة
    |--------------------------------------------------------------------------
    */
    Route::prefix('locations')->group(function () {
        // عرض جميع الدول مع مدنها
        Route::get('/', [LocationController::class, 'index']);                              // جلب جميع الدول مع مدنها
        // الدول
        Route::get('/countries', [LocationController::class, 'getCountries']);              // جلب جميع الدول
        Route::get('/countries/{id}', [LocationController::class, 'getCountry']);           // جلب دولة محددة
        // المدن
        Route::get('/cities', [LocationController::class, 'getCities']);                    // جلب جميع المدن
        Route::get('/cities/{id}', [LocationController::class, 'getCity']);                 // جلب مدينة محددة
        Route::get('/countries/{countryId}/cities', [LocationController::class, 'getCitiesByCountry']); // جلب مدن دولة محددة
        // البحث
        Route::get('/search', [LocationController::class, 'search']);                       // البحث في الدول والمدن
    });

    // مسارٌ غير موجود تحت /api/store (معرّفٌ مشوَّه مثلاً): 404 بصيغة المتجر. مسارٌ عاديّ أخيرٌ لا `Route::fallback`
    // لأن وحدتَي Stock وPurchases تُسجِّلان fallback عامّاً خلف مصادقة فيردّ 401 «Unauthenticated» بدل 404
    Route::any('{any}', [StoreHomeController::class, 'notFound'])->where('any', '.*');

});
