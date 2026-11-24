<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\AuthController;
use Modules\Integration\Http\Controllers\Api\BranchController;
use Modules\Integration\Http\Controllers\Api\CreditNoteController;
use Modules\Integration\Http\Controllers\Api\InvoiceController;
use Modules\Integration\Http\Controllers\Api\PermissionController;
use Modules\Integration\Http\Controllers\Api\ProductExchangeController;
use Modules\Integration\Http\Controllers\Api\ProfileController;
use Modules\Integration\Http\Controllers\Api\SalesReturnController;
use Modules\Integration\Http\Middleware\EnsureEmployeeUser;
use Modules\Integration\Http\Middleware\ForceJsonResponse;
use Modules\Integration\Http\Middleware\SetApiLocale;

/*
|--------------------------------------------------------------------------
| Integration API — واجهة تطبيق الجوال (Flutter)
|--------------------------------------------------------------------------
|
| كل المسارات هنا تحت  /api/integration/v1  وتخدم المناديب/الموظفين حصراً:
| مصادقة (دخول/خروج)، استعادة كلمة المرور بالرمز، توثيق البريد، والملف
| الشخصي عرضاً وتعديلاً. المستند الكامل ومجموعة Postman في docs/ للوحدة.
|
*/

Route::prefix('integration/v1')->name('integration.')->middleware([ForceJsonResponse::class, SetApiLocale::class])->group(function () {

    // ── مسارات عامة (بلا مصادقة) ──────────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:integration-login')->name('login');

        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:integration-otp-send')->name('password.forgot');

        Route::post('password/verify-otp', [AuthController::class, 'verifyResetOtp'])
            ->middleware('throttle:integration-otp-check')->name('password.verify-otp');

        Route::post('password/reset', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:integration-otp-check')->name('password.reset');
    });

    // ── مسارات محمية (رمز Sanctum + حساب موظف) ───────────────────────
    Route::middleware(['auth:sanctum', EnsureEmployeeUser::class])->group(function () {

        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');

            Route::post('email/send-otp', [AuthController::class, 'sendEmailVerificationOtp'])
                ->middleware('throttle:integration-otp-send')->name('email.send-otp');

            Route::post('email/verify', [AuthController::class, 'verifyEmail'])
                ->middleware('throttle:integration-otp-check')->name('email.verify');
        });

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::post('avatar', [ProfileController::class, 'updateAvatar'])->name('avatar');
            Route::post('change-password', [ProfileController::class, 'changePassword'])->name('change-password');
        });

        // ── صلاحيات المستخدم ونطاق بياناته (لإظهار الشاشات والأزرار) ──
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions');

        // ── الفروع التي يحقّ له اختيارها (لفلترة العملاء وغيرها) ──
        Route::get('branches', [BranchController::class, 'index'])->name('branches');

        // ── الأقسام — كلٌّ في ملفّه (routes/api/) ───────────────────────
        require __DIR__.'/api/finance.php';
        require __DIR__.'/api/stock.php';
        require __DIR__.'/api/clients.php';
        require __DIR__.'/api/notifications.php';
        require __DIR__.'/api/tracking.php';

        /*
            وثائق المبيعات الأربع — الصلاحية على مستوى المسار (`permission.role:...`)
            بنفس كتالوج `sales.*` الذي تفرضه مُنشئات كنترولرات الويب المقابلة،
            لا فحصٌ مكرَّر داخل الكنترولر (نفس مبدأ routes/api/finance.php).
            النطاق (من يرى أيّ مستند) يبقى داخل الكنترولر — سؤالٌ عن بيانات
            الصفّ لا عن الإجراء نفسه.
        */

        // ── فواتير المبيعات ──────────────────────────────────────────────
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::post('/', [InvoiceController::class, 'store'])
                ->middleware('permission.role:sales.invoices.create')->name('store');
            Route::get('/', [InvoiceController::class, 'index'])
                ->middleware('permission.role:sales.invoices.view')->name('index');
            Route::get('{id}', [InvoiceController::class, 'show'])
                ->middleware('permission.role:sales.invoices.view')->name('show');
            Route::get('{id}/print', [InvoiceController::class, 'print'])
                ->middleware('permission.role:sales.invoices.print')->name('print');
        });

        // ── مرتجعات المبيعات ─────────────────────────────────────────────
        Route::prefix('returns')->name('returns.')->group(function () {
            Route::post('/', [SalesReturnController::class, 'store'])
                ->middleware('permission.role:sales.returns.create')->name('store');
            Route::get('/', [SalesReturnController::class, 'index'])
                ->middleware('permission.role:sales.returns.view')->name('index');
            Route::get('{id}', [SalesReturnController::class, 'show'])
                ->middleware('permission.role:sales.returns.view')->name('show');
            Route::get('{id}/print', [SalesReturnController::class, 'print'])
                ->middleware('permission.role:sales.returns.print')->name('print');
        });

        // ── الإشعارات الدائنة ────────────────────────────────────────────
        Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
            Route::get('settlement-preview', [CreditNoteController::class, 'settlementPreview'])
                ->middleware('permission.role:sales.credit_notes.create')->name('settlement-preview');
            Route::post('/', [CreditNoteController::class, 'store'])
                ->middleware('permission.role:sales.credit_notes.create')->name('store');
            Route::get('/', [CreditNoteController::class, 'index'])
                ->middleware('permission.role:sales.credit_notes.view')->name('index');
            Route::get('{id}', [CreditNoteController::class, 'show'])
                ->middleware('permission.role:sales.credit_notes.view')->name('show');
            Route::get('{id}/print', [CreditNoteController::class, 'print'])
                ->middleware('permission.role:sales.credit_notes.print')->name('print');
        });

        // ── تبديل البضاعة ────────────────────────────────────────────────
        Route::prefix('exchanges')->name('exchanges.')->group(function () {
            Route::post('/', [ProductExchangeController::class, 'store'])
                ->middleware('permission.role:sales.exchanges.create')->name('store');
            Route::get('/', [ProductExchangeController::class, 'index'])
                ->middleware('permission.role:sales.exchanges.view')->name('index');
            Route::get('{id}', [ProductExchangeController::class, 'show'])
                ->middleware('permission.role:sales.exchanges.view')->name('show');
            Route::get('{id}/print', [ProductExchangeController::class, 'print'])
                ->middleware('permission.role:sales.exchanges.print')->name('print');
        });
    });

    /*
        عنوانٌ غير معروف تحت واجهة الجوال — JSON لا صفحة HTML.

        وحدتا Stock وPurchases تسجّلان `Route::fallback` في مساراتهما على
        الويب، فكان أي عنوانٍ خاطئ هنا يصل إليهما ويعود صفحةَ HTML لا
        يفهمها التطبيق. ويبقى هذا آخرَ مسارٍ في الملف فلا يحجب غيره.
    */
    Route::any('{any}', function (Request $request) {
        // العنوان موجودٌ بطريقةٍ أخرى (GET على مسار POST مثلاً) ⇒ 405 لا 404
        $allowed = collect(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])
            ->reject(fn ($m) => $m === $request->method())
            ->filter(function ($m) use ($request) {
                try {
                    $route = Route::getRoutes()->match(Request::create($request->path(), $m));

                    return $route->getName() !== 'api.integration.not-found';
                } catch (Throwable) {
                    return false;
                }
            })
            ->values();

        if ($allowed->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'طريقة الطلب غير مسموحة لهذا العنوان — المسموح: '.$allowed->implode('، ').'.',
                'code' => 'METHOD_NOT_ALLOWED',
            ], 405, ['Allow' => $allowed->implode(', ')]);
        }

        return response()->json([
            'success' => false,
            'message' => 'العنوان المطلوب غير موجود في واجهة التطبيق.',
        ], 404);
    })->where('any', '.*')->name('not-found');
});
