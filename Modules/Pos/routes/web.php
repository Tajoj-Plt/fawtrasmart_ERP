<?php

use App\Constants\planFeatures\PosPlanFeatures;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Pos\Http\Controllers\CashDrawerController;
use Modules\Pos\Http\Controllers\DevicesController;
use Modules\Pos\Http\Controllers\PrintersController;
use Modules\Pos\Http\Controllers\ReportsController;
use Modules\Pos\Http\Controllers\SalesStartController;
use Modules\Pos\Http\Controllers\SessionsController;
use Modules\Pos\Http\Controllers\SettingsController;
use Modules\Pos\Http\Controllers\ShiftCategoriesController;
use Modules\Pos\Http\Controllers\ShiftController;

/*
|--------------------------------------------------------------------------
| مسارات وحدة نقاط البيع
|--------------------------------------------------------------------------
|
| ═════════════════════ ما كان ═════════════════════
|
| • **استيرادان لفئتين خاطئتين**:
|
|       use Modules\Sales\Http\Controllers\InvoicesController;  // غير مستخدم
|       use Modules\Sales\Http\Controllers\PosController;       // الملف غير موجود أصلاً
|
|   والثاني كان **يحجب** `Modules\Pos\Http\Controllers\PosController` داخل
|   هذا الملف — فأيُّ استخدامٍ لـ`PosController::class` هنا يشير إلى فئةٍ
|   لا وجود لها.
|
| • **خمسةُ مسارات تشير إلى دوالّ غير موجودة**:
|   `getCategorySalesReport`، `exportCategorySalesReport`،
|   `testCategoryReport`، `printCategorySalesReport` — وقالبُ
|   `sessions.close` المفقود. أي أن زرَّ «إغلاق الجلسة» كان يؤدي إلى خطأ.
|
| • **مجموعةُ «Fast AJAX»** خارج `check.branch` وخارج التوطين، بـ`auth`
|   وحدها — تقرأ كتالوجَ المنتجات وأسعارَه وأرصدةَ العملاء.
|
| • **مسارُ `shabaka-payment`**: يُعلّم أيَّ فاتورةٍ كمدفوعة بمبلغٍ من الطلب،
|   بلا بوابةِ دفعٍ حقيقية وبلا فحصِ ملكية. حُذف بالكامل.
|
| • **`/search` معرَّفٌ مرّتين**، إحداهما خارج `auth` كلياً.
|
| • **بلا أيّ حارس صلاحية على مسار واحد**، وبلا حدّ باقة.
|
| ═════════════════════ ما صار ═════════════════════
|
| حارسان متداخلان — كما في وحدة المخزون:
|
|   ١. سورٌ خارجي على المجموعة: `plan:` بشرط «واحدةٌ على الأقل»، فباقةٌ لا
|      تشمل شيئاً من نقاط البيع تُردّ قبل أن يصل الطلب إلى متحكّم.
|   ٢. حارسُ صلاحيةٍ على كل مسار — `permission.role:`.
|
| وموضعُ الأوّل على المجموعة لا على كل مسار: نسيانُ مسارٍ واحد يفتحه كلَّه،
| والمنسيُّ عادةً مسارُ AJAX لا شاشة، فلا يُلاحَظ غيابُ حارسِه أبداً.
|
*/

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [
            'localeSessionRedirect',
            'localizationRedirect',
            'localeViewPath',
            'check.branch',
            'auth',
            // الفترةُ المحاسبية المختارة تصير حدَّي التقرير قبل أن يقرأهما
            // المتحكّم — كما في وحدتَي الحسابات والمخزون
            'accounting.period',
        ],
    ],
    function () {
        Route::prefix('POS')
            ->middleware('plan:'.PosPlanFeatures::anyOf())
            ->group(function () {

                /*
                |------------------------------------------------------------
                | شاشة البيع
                |------------------------------------------------------------
                |
                | `check.pos.session` — ميدل وير **كان مسجَّلاً في
                | `bootstrap/app.php:149` ولا يُستعمل في أيّ مسار**، بينما
                | نفسُ الفحص مكرَّرٌ اثنتي عشرة مرّةً يدوياً داخل المتحكّم.
                |
                */
                /*
                    `pos.custody` هنا لا على الوحدة كلِّها: البيعُ وحدَه يمسّ
                    عهدةَ الكاشير — مستودعاً يُخصم منه وخزينةً يدخلها النقد.
                    والتقاريرُ والإعداداتُ لا تمسّ شيئاً، فمشرفٌ بلا مستودعٍ
                    يظلّ يراجعها.
                */
                Route::middleware(['permission.role:pos.sell', 'plan:'.PosPlanFeatures::TERMINAL, 'pos.custody'])
                    ->group(function () {
                        Route::get('/sales-start', [SalesStartController::class, 'index'])->name('sales_start.index');
                        Route::get('/Sales_Start/index', [SalesStartController::class, 'index'])->name('POS.sales_start.index');

                        Route::middleware('check.pos.session')->group(function () {
                            Route::post('/sales-start/store', [SalesStartController::class, 'store'])->name('sales_start.store');
                            Route::post('/Sales_Start/store', [SalesStartController::class, 'store'])->name('POS.sales_start.store');
                        });
                    });

                /*
                |------------------------------------------------------------
                | الكتالوج والبحث
                |------------------------------------------------------------
                |
                | كانت هذه المسارات في مجموعةٍ منفصلة بـ`auth` وحدها،
                | ومستثناةً من فحص الصلاحيات في المتحكّم عبر `$fastEndpoints`
                | «للسرعة». وهي تقرأ الأسعار وأرصدةَ العملاء.
                |
                */
                Route::middleware(['permission.role:pos.sell', 'plan:'.PosPlanFeatures::TERMINAL])
                    ->group(function () {
                        Route::get('/products-by-category', [SalesStartController::class, 'getProductsByCategory'])->name('pos.products_by_category');
                        Route::get('/product-by-barcode', [SalesStartController::class, 'getProductByBarcode'])->name('pos.product_by_barcode');
                        Route::get('/quick-search-products', [SalesStartController::class, 'quickSearchProducts'])->name('pos.quick_search_products');
                        Route::get('/search', [SalesStartController::class, 'search'])->name('pos.search');
                        Route::get('/search-clients', [SalesStartController::class, 'searchClients'])->name('pos.search_clients');
                        Route::get('/daily-stats', [SalesStartController::class, 'getDailyStats'])->name('pos.daily_stats');
                        Route::get('/session-stats', [SalesStartController::class, 'getSessionStats'])->name('pos.session_stats');
                        Route::get('/check-session', [SalesStartController::class, 'checkSession'])->name('pos.check_session');
                    });

                /*
                |------------------------------------------------------------
                | الفواتير والطباعة
                |------------------------------------------------------------
                */
                Route::get('/invoices/{id}/details', [SalesStartController::class, 'getInvoiceDetails'])
                    ->name('pos.invoices.details')
                    ->middleware('permission.role:pos.sell');

                Route::get('/Sales_Start/invoices/{id}/print', [SalesStartController::class, 'print'])
                    ->name('POS.invoices.print')
                    ->middleware('permission.role:pos.orders.reprint');

                Route::get('/return_invoices/{id}/print', [SalesStartController::class, 'printReturnInvoice'])
                    ->name('pos.returns.print')
                    ->middleware('permission.role:pos.orders.reprint');

                /*
                |------------------------------------------------------------
                | إلغاء الفاتورة
                |------------------------------------------------------------
                |
                | `pos.orders.void` كانت صلاحيةً في الكتالوج **بلا دالّةٍ ولا
                | مسار**، فالوسيلةُ الوحيدة لتصحيح فاتورةٍ خاطئة كانت
                | المرتجع — وهو يُظهر بيعاً ثم مرتجعاً لعمليةٍ لم تقع.
                |
                */
                Route::post('/invoices/{id}/void', [SalesStartController::class, 'voidInvoice'])
                    ->name('pos.orders.void')
                    ->middleware(['permission.role:pos.orders.void', 'plan:'.PosPlanFeatures::VOID])
                    ->whereNumber('id');

                /*
                |------------------------------------------------------------
                | السلال المعلَّقة
                |------------------------------------------------------------
                |
                | كانت ثلاثَ نقاطٍ للقراءة والحذف فوق بياناتٍ **لا تُخلق
                | أبداً**: لا سطرَ في المستودع كلِّه يُنشئ فاتورةً بحالة
                | `held`، ولا نقطةَ نهايةٍ للتعليق أصلاً. وصارت تُحفظ في
                | `pos_held_orders` — ومعها نقطةُ التعليق التي لم تكن.
                |
                */
                Route::middleware(['permission.role:pos.orders.hold', 'plan:'.PosPlanFeatures::HOLD_ORDERS, 'check.pos.session'])
                    ->prefix('held-invoices')
                    ->group(function () {
                        Route::get('/', [SalesStartController::class, 'getHeldInvoices'])->name('pos.held_orders.index');
                        Route::post('/', [SalesStartController::class, 'holdOrder'])->name('pos.held_orders.store');
                        Route::get('/{id}/resume', [SalesStartController::class, 'resumeHeldInvoice'])
                            ->name('pos.held_orders.resume')->whereNumber('id');
                        Route::delete('/{id}', [SalesStartController::class, 'deleteHeldInvoice'])
                            ->name('pos.held_orders.delete')->whereNumber('id');
                    });

                /*
                |------------------------------------------------------------
                | المرتجعات
                |------------------------------------------------------------
                */
                Route::middleware(['plan:'.PosPlanFeatures::RETURNS, 'check.pos.session'])->group(function () {
                    Route::get('/available-invoices-for-return', [SalesStartController::class, 'getAvailableInvoicesForReturn'])
                        ->name('pos.returns.available')
                        ->middleware('permission.role:pos.returns.create');

                    Route::get('/invoice-details-for-return/{id}', [SalesStartController::class, 'getInvoiceDetailsForReturn'])
                        ->name('pos.returns.details')
                        ->middleware('permission.role:pos.returns.create');

                    Route::post('/process-return', [SalesStartController::class, 'processReturn'])
                        ->name('pos.returns.process')
                        ->middleware('permission.role:pos.returns.create');
                });

                /*
                |------------------------------------------------------------
                | الجلسات
                |------------------------------------------------------------
                */
                Route::prefix('session')
                    ->middleware('plan:'.PosPlanFeatures::SESSIONS)
                    ->group(function () {
                        Route::get('/', [SessionsController::class, 'index'])
                            ->name('pos.sessions.index')
                            ->middleware('permission.role:points_sale_view_own_sessions');

                        Route::get('/create', [SessionsController::class, 'create'])
                            ->name('pos.sessions.create')
                            ->middleware([
                                'permission.role:points_sale_open_sessions_own|points_sale_open_sessions_all',
                                'plan.limit:'.PosPlanFeatures::LIMIT_CONCURRENT_SESSIONS,
                                // سقفٌ متزامن **وعدّادٌ شهري** معاً: الأول يمنع
                                // فتح أكثر ممّا اشتُري في وقتٍ واحد، والثاني
                                // يمنع تجاوز حصّة الشهر مهما تعاقبت
                                'plan.limit:'.PosPlanFeatures::LIMIT_SESSIONS,
                            ]);

                        Route::post('/', [SessionsController::class, 'store'])
                            ->name('pos.session.store')
                            ->middleware([
                                'permission.role:points_sale_open_sessions_own|points_sale_open_sessions_all',
                                'plan.limit:'.PosPlanFeatures::LIMIT_CONCURRENT_SESSIONS,
                                // سقفٌ متزامن **وعدّادٌ شهري** معاً: الأول يمنع
                                // فتح أكثر ممّا اشتُري في وقتٍ واحد، والثاني
                                // يمنع تجاوز حصّة الشهر مهما تعاقبت
                                'plan.limit:'.PosPlanFeatures::LIMIT_SESSIONS,
                            ]);

                        Route::get('/{id}', [SessionsController::class, 'show'])
                            ->name('pos.sessions.show')
                            ->middleware('permission.role:points_sale_view_own_sessions')
                            ->whereNumber('id');

                        Route::get('/{id}/details', [SessionsController::class, 'details'])
                            ->name('pos.sessions.details')
                            ->middleware('permission.role:points_sale_view_own_sessions')
                            ->whereNumber('id');

                        Route::get('/{id}/transactions', [SessionsController::class, 'getTransactions'])
                            ->name('pos.sessions.transactions')
                            ->middleware('permission.role:points_sale_view_own_sessions')
                            ->whereNumber('id');

                        Route::get('/{id}/refresh-stats', [SessionsController::class, 'refreshStats'])
                            ->name('pos.sessions.refresh_stats')
                            ->middleware('permission.role:points_sale_view_own_sessions')
                            ->whereNumber('id');

                        // التعليقُ والاستئناف — كانت الدالّتان موجودتين **بلا مسار**
                        Route::post('/{id}/suspend', [SessionsController::class, 'suspend'])
                            ->name('pos.sessions.suspend')
                            ->middleware('permission.role:pos.sessions.suspend')
                            ->whereNumber('id');

                        Route::post('/{id}/resume', [SessionsController::class, 'resume'])
                            ->name('pos.sessions.resume')
                            ->middleware('permission.role:pos.sessions.suspend')
                            ->whereNumber('id');

                        Route::get('/{id}/close', [SessionsController::class, 'closeForm'])
                            ->name('pos.sessions.close-form')
                            ->middleware('permission.role:points_sale_close_sessions_own|points_sale_close_sessions_all')
                            ->whereNumber('id');

                        Route::post('/{id}/close', [SessionsController::class, 'close'])
                            ->name('pos.sessions.close')
                            ->middleware('permission.role:points_sale_close_sessions_own|points_sale_close_sessions_all')
                            ->whereNumber('id');

                        /*
                            تأكيدُ الإقفال — الصلاحيتان كانتا في الكتالوج بلا
                            مسارٍ ولا دالّة، فالكاشير يعتمد عهدته بنفسه.
                        */
                        Route::post('/{id}/confirm', [SessionsController::class, 'confirm'])
                            ->name('pos.sessions.confirm')
                            ->middleware([
                                'permission.role:points_sale_confirm_close_sessions_own|points_sale_confirm_close_sessions_all',
                                'plan:'.PosPlanFeatures::SESSION_CONFIRM,
                            ])
                            ->whereNumber('id');

                        /*
                            الإحصائياتُ صفحةٌ يُنتقل إليها لا مودل.

                            كانت مودلاً في شاشة العرض: لا رابطَ لها يُرسَل،
                            ولا يُطبع منها شيء، ولا تُفتح إلّا بعد تحميل
                            الشاشة كلِّها بجدول معاملاتها. وهي مقروءةٌ بحتة
                            فحارسُها حارسُ العرض.
                        */
                        Route::get('/{id}/stats', [SessionsController::class, 'stats'])
                            ->name('pos.sessions.stats')
                            ->middleware('permission.role:points_sale_view_own_sessions')
                            ->whereNumber('id');

                        Route::get('/{id}/summary', [SessionsController::class, 'summary'])
                            ->name('pos.sessions.summary')
                            ->middleware('permission.role:points_sale_view_own_sessions')
                            ->whereNumber('id');

                        Route::get('/{id}/print', [SessionsController::class, 'print'])
                            ->name('pos.sessions.print')
                            ->middleware('permission.role:points_sale_view_own_sessions')
                            ->whereNumber('id');
                    });

                /*
                |------------------------------------------------------------
                | الدرج النقدي — وحدةٌ كاملةٌ لم تكن موجودة
                |------------------------------------------------------------
                */
                Route::prefix('cash-drawer')
                    ->middleware('plan:'.PosPlanFeatures::CASH_DRAWER)
                    ->group(function () {
                        Route::get('/', [CashDrawerController::class, 'index'])
                            ->name('pos.cash_drawer.index')
                            ->middleware('permission.role:pos.cash_drawer.view');

                        Route::post('/', [CashDrawerController::class, 'store'])
                            ->name('pos.cash_drawer.store')
                            ->middleware('permission.role:pos.cash_drawer.pay_in|pos.cash_drawer.pay_out|pos.cash_drawer.drop');

                        Route::post('/{id}/approve', [CashDrawerController::class, 'approve'])
                            ->name('pos.cash_drawer.approve')
                            ->middleware('permission.role:pos.cash_drawer.approve')
                            ->whereNumber('id');

                        Route::post('/{id}/reject', [CashDrawerController::class, 'reject'])
                            ->name('pos.cash_drawer.reject')
                            ->middleware('permission.role:pos.cash_drawer.approve')
                            ->whereNumber('id');

                        Route::get('/session/{sessionId}/summary', [CashDrawerController::class, 'sessionSummary'])
                            ->name('pos.cash_drawer.session_summary')
                            ->middleware('permission.role:pos.cash_drawer.view')
                            ->whereNumber('sessionId');
                    });

                /*
                |------------------------------------------------------------
                | التقارير
                |------------------------------------------------------------
                |
                | مسارٌ واحدٌ بمعامل، بدل ستّة مساراتٍ لستّ دوالّ متطابقة
                | البنية. وحدُّ الباقة على الربحية منفصلٌ عن التقارير العادية.
                |
                */
                Route::prefix('reports')
                    ->middleware('plan:'.PosPlanFeatures::REPORTS)
                    ->group(function () {
                        Route::get('/', [ReportsController::class, 'index'])
                            ->name('pos_reports.index')
                            ->middleware('permission.role:pos_view_reports');

                        // تقريرا X وZ — قبل المسار ذي المعامل كي لا يبتلعهما
                        Route::get('/x-report', [ReportsController::class, 'xReport'])
                            ->name('pos.reports.x')
                            ->middleware('permission.role:pos_view_reports');

                        Route::get('/z-report/{sessionId}', [ReportsController::class, 'zReport'])
                            ->name('pos.reports.z')
                            ->middleware('permission.role:pos_view_reports')
                            ->whereNumber('sessionId');

                        /*
                            حدُّ الباقة هنا `REPORTS` العام — وتقريرا الربحية
                            والفروقات يفحصان مفتاحيهما الخاصّين داخل
                            `AbstractPosReport::authorize()`، إذ لا يُعرف
                            التقريرُ المطلوب إلّا من المعامل.
                        */
                        Route::get('/{report}', [ReportsController::class, 'show'])
                            ->name('pos.reports.show')
                            ->middleware('permission.role:pos_view_reports|pos.reports.profit|pos.reports.variance')
                            ->whereAlpha('report');

                        Route::get('/{report}/export', [ReportsController::class, 'export'])
                            ->name('pos.reports.export')
                            ->middleware('permission.role:pos.reports.export')
                            ->whereAlpha('report');
                    });

                /*
                |------------------------------------------------------------
                | الإعدادات والإدارة
                |------------------------------------------------------------
                */
                Route::prefix('Settings')
                    ->middleware(['permission.role:pos_manage_settings', 'plan:'.PosPlanFeatures::SETTINGS])
                    ->group(function () {
                        Route::get('/index', [SettingsController::class, 'index'])->name('pos.settings.index');
                        Route::get('/general', [SettingsController::class, 'general'])->name('pos.settings.general');
                        Route::post('/store', [SettingsController::class, 'store'])->name('pos.settings.store');
                        Route::get('/categories-by-type', [SettingsController::class, 'getCategoriesByType'])->name('pos.settings.categories.by-type');
                        Route::get('/active-payment-methods', [SettingsController::class, 'getActivePaymentMethods'])->name('pos.active-payment-methods');
                        Route::post('/save-active-payment-methods', [SettingsController::class, 'saveActivePaymentMethods'])->name('pos.save-active-payment-methods');
                    });

                Route::prefix('Devices')
                    ->middleware('plan:'.PosPlanFeatures::DEVICES)
                    ->group(function () {
                        Route::get('/index', [DevicesController::class, 'index'])
                            ->name('pos.settings.devices.index')
                            ->middleware('permission.role:pos.devices.view|pos.devices.manage');

                        Route::get('/Create', [DevicesController::class, 'create'])
                            ->name('pos.settings.devices.create')
                            ->middleware(['permission.role:pos.devices.manage', 'plan.limit:'.PosPlanFeatures::LIMIT_DEVICES]);

                        Route::post('/Create', [DevicesController::class, 'store'])
                            ->name('pos.settings.devices.store')
                            ->middleware(['permission.role:pos.devices.manage', 'plan.limit:'.PosPlanFeatures::LIMIT_DEVICES]);

                        Route::get('/show/{id}', [DevicesController::class, 'show'])
                            ->name('pos.settings.devices.show')
                            ->middleware('permission.role:pos.devices.view|pos.devices.manage')
                            ->whereNumber('id');

                        Route::get('/edit/{id}', [DevicesController::class, 'edit'])
                            ->name('pos.settings.devices.edit')
                            ->middleware('permission.role:pos.devices.manage')
                            ->whereNumber('id');

                        // كان `POST` لعمليةِ تحديث
                        Route::put('/update/{id}', [DevicesController::class, 'update'])
                            ->name('pos.settings.devices.update')
                            ->middleware('permission.role:pos.devices.manage')
                            ->whereNumber('id');

                        Route::patch('/toggle-status/{id}', [DevicesController::class, 'toggleStatus'])
                            ->name('pos.settings.devices.toggle-status')
                            ->middleware('permission.role:pos.devices.manage')
                            ->whereNumber('id');

                        Route::delete('/delete/{id}', [DevicesController::class, 'destroy'])
                            ->name('pos.settings.devices.destroy')
                            ->middleware('permission.role:pos.devices.manage')
                            ->whereNumber('id');
                    });

                /*
                |------------------------------------------------------------
                | الطابعات
                |------------------------------------------------------------
                |
                | `invoice_template` كان الضبطَ الوحيد: `thermal` أو
                | `electronic` لكل المنشأة. ولا عرضَ ورق ولا نسخ ولا طابعةَ
                | مطبخ ولا ربطَ طابعةٍ بجهاز.
                |
                */
                Route::prefix('Printers')
                    ->middleware('plan:'.PosPlanFeatures::PRINTERS)
                    ->group(function () {
                        Route::get('/index', [PrintersController::class, 'index'])
                            ->name('pos.settings.printers.index')
                            ->middleware('permission.role:pos.printers.manage|pos.devices.view');

                        Route::get('/create', [PrintersController::class, 'create'])
                            ->name('pos.settings.printers.create')
                            ->middleware(['permission.role:pos.printers.manage', 'plan.limit:'.PosPlanFeatures::LIMIT_PRINTERS]);

                        Route::post('/create', [PrintersController::class, 'store'])
                            ->name('pos.settings.printers.store')
                            ->middleware(['permission.role:pos.printers.manage', 'plan.limit:'.PosPlanFeatures::LIMIT_PRINTERS]);

                        Route::get('/edit/{id}', [PrintersController::class, 'edit'])
                            ->name('pos.settings.printers.edit')
                            ->middleware('permission.role:pos.printers.manage')
                            ->whereNumber('id');

                        Route::put('/update/{id}', [PrintersController::class, 'update'])
                            ->name('pos.settings.printers.update')
                            ->middleware('permission.role:pos.printers.manage')
                            ->whereNumber('id');

                        Route::delete('/delete/{id}', [PrintersController::class, 'destroy'])
                            ->name('pos.settings.printers.destroy')
                            ->middleware('permission.role:pos.printers.manage')
                            ->whereNumber('id');
                    });

                /*
                    تصنيفاتُ الورديات.

                    تحت `pos.shifts.*` لا تحت صلاحيةٍ ثالثة: التصنيفُ جزءٌ من
                    إدارة الورديات، ولو أُفرد بصلاحيةٍ لاحتاجت مزامنةً ثم
                    نُسيت فأُقفل البابُ على من يملك الورديات نفسَها.
                */
                Route::prefix('ShiftCategory')
                    ->middleware('plan:'.PosPlanFeatures::DEVICES)
                    ->group(function () {
                        Route::get('/index', [ShiftCategoriesController::class, 'index'])
                            ->name('pos.settings.shift_categories.index')
                            ->middleware('permission.role:pos.shift_categories.view|pos.shift_categories.manage');

                        Route::get('/create', [ShiftCategoriesController::class, 'create'])
                            ->name('pos.settings.shift_categories.create')
                            ->middleware('permission.role:pos.shift_categories.manage');

                        Route::post('/create', [ShiftCategoriesController::class, 'store'])
                            ->name('pos.settings.shift_categories.store')
                            ->middleware('permission.role:pos.shift_categories.manage');

                        Route::get('/edit/{id}', [ShiftCategoriesController::class, 'edit'])
                            ->name('pos.settings.shift_categories.edit')
                            ->middleware('permission.role:pos.shift_categories.manage')
                            ->whereNumber('id');

                        Route::put('/update/{id}', [ShiftCategoriesController::class, 'update'])
                            ->name('pos.settings.shift_categories.update')
                            ->middleware('permission.role:pos.shift_categories.manage')
                            ->whereNumber('id');

                        Route::delete('/delete/{id}', [ShiftCategoriesController::class, 'destroy'])
                            ->name('pos.settings.shift_categories.destroy')
                            ->middleware('permission.role:pos.shift_categories.manage')
                            ->whereNumber('id');
                    });

                Route::prefix('Shift')
                    ->middleware('plan:'.PosPlanFeatures::DEVICES)
                    ->group(function () {
                        Route::get('/index', [ShiftController::class, 'index'])
                            ->name('pos.settings.shift.index')
                            ->middleware('permission.role:pos.shifts.view|pos.shifts.manage');

                        Route::get('/create', [ShiftController::class, 'create'])
                            ->name('pos.settings.shift.create')
                            ->middleware(['permission.role:pos.shifts.manage', 'plan.limit:'.PosPlanFeatures::LIMIT_SHIFTS]);

                        Route::post('/create', [ShiftController::class, 'store'])
                            ->name('pos.settings.shift.store')
                            ->middleware(['permission.role:pos.shifts.manage', 'plan.limit:'.PosPlanFeatures::LIMIT_SHIFTS]);

                        Route::get('/show/{id}', [ShiftController::class, 'show'])
                            ->name('pos.settings.shift.show')
                            ->middleware('permission.role:pos.shifts.view|pos.shifts.manage')
                            ->whereNumber('id');

                        Route::get('/edit/{id}', [ShiftController::class, 'edit'])
                            ->name('pos.settings.shift.edit')
                            ->middleware('permission.role:pos.shifts.manage')
                            ->whereNumber('id');

                        Route::put('/update/{id}', [ShiftController::class, 'update'])
                            ->name('pos.settings.shift.update')
                            ->middleware('permission.role:pos.shifts.manage')
                            ->whereNumber('id');

                        Route::delete('/delete/{id}', [ShiftController::class, 'destroy'])
                            ->name('pos.settings.shift.destroy')
                            ->middleware('permission.role:pos.shifts.manage')
                            ->whereNumber('id');
                    });
            });
    }
);
