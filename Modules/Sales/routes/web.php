<?php

use App\Constants\planFeatures\SalesPlanFeatures;
use App\Models\Client;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Sales\Http\Controllers\CollectionsTargetController;
use Modules\Sales\Http\Controllers\CreditNotificationController;
use Modules\Sales\Http\Controllers\DailyClosingController;
use Modules\Sales\Http\Controllers\DashboardController;
use Modules\Sales\Http\Controllers\ElectronicInvoiceController;
use Modules\Sales\Http\Controllers\ExchangeReasonController;
use Modules\Sales\Http\Controllers\GiftOfferController;
use Modules\Sales\Http\Controllers\Installments\InstallmentsController;
use Modules\Sales\Http\Controllers\InvoicesController;
use Modules\Sales\Http\Controllers\NotificationsController;
use Modules\Sales\Http\Controllers\OffersController;
use Modules\Sales\Http\Controllers\OrderSourceController;
use Modules\Sales\Http\Controllers\PeriodicInvoicesController;
use Modules\Sales\Http\Controllers\ProductExchangeController;
use Modules\Sales\Http\Controllers\QuoteController;
use Modules\Sales\Http\Controllers\Report\CollectionsReportController;
use Modules\Sales\Http\Controllers\Report\CommissionsReportController as SalesCommissionsReportController;
use Modules\Sales\Http\Controllers\Report\ExchangesReportController;
use Modules\Sales\Http\Controllers\Report\PaymentsByCustomerController;
use Modules\Sales\Http\Controllers\Report\PaymentsByEmployeeController;
use Modules\Sales\Http\Controllers\Report\SalesByCustomerController;
use Modules\Sales\Http\Controllers\Report\SalesByEmployeeController;
use Modules\Sales\Http\Controllers\Report\SalesByItemController;
use Modules\Sales\Http\Controllers\Report\SalesByProductController;
use Modules\Sales\Http\Controllers\Report\SalesByProductGroupController;
use Modules\Sales\Http\Controllers\Report\SalesProfitsController;
use Modules\Sales\Http\Controllers\Report\UnifiedCustomerReportController;
use Modules\Sales\Http\Controllers\ReturnInvoiceController;
use Modules\Sales\Http\Controllers\RevolvingInvoicesController;
use Modules\Sales\Http\Controllers\SalesDocumentDesignsController;
use Modules\Sales\Http\Controllers\SalesOrderController;
use Modules\Sales\Http\Controllers\SalesPaymentController;
use Modules\Sales\Http\Controllers\SalesReportsController;
use Modules\Sales\Http\Controllers\SalesTargetController;
use Modules\Sales\Http\Controllers\ShippingOptionsController;
use Modules\Sales\Http\Controllers\SittingInvoiceController;
use Modules\Sales\Http\Controllers\TargetSettingsController;
use Modules\Sales\Http\Controllers\WeeklyCommissionController;

// ⭐ API للكرون جوب - معالجة الإشعارات المجدولة (بدون auth - خارج الـ middleware)
Route::get('process-scheduled-notifications', [NotificationsController::class, 'processScheduledNotificationsApi'])
    ->name('notifications.process-scheduled-api')
    // بلا `auth` عن قصد (ينادى من مهمّةٍ مجدولة)، لكنّ الباقة تُفحَص:
    // منشأةٌ لا تشمل باقتُها الإشعارات لا تُعالَج لها إشعاراتٌ مجدولة
    ->middleware('plan:'.SalesPlanFeatures::NOTIFICATIONS);

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [
            'localeSessionRedirect',
            'localizationRedirect',
            'localeViewPath',
            'check.branch',
            'auth',
            // «الفترة المحاسبية» المختارة تصير تاريخَي التقرير قبل أن
            // يقرأهما المتحكّم — كما في المخزون والحسابات
            'accounting.period',
            /*
                حدّ الباقة على الوحدة كلّها — سورٌ خارجي قبل الأسوار
                الداخلية، ونظيرُ ما تفعله `Modules/Stock` على بادئة `stock`.

                الشرط «واحدةٌ على الأقل» لا «كلّها»: باقةٌ تشمل التقارير
                وحدها يجب أن تفتح تقاريرها، وحارسُ كل مجموعةٍ داخلَها
                يتكفّل بالباقي.

                وموضعُه هنا لا على كل مسار: الوحدة فيها ثلاثمئة مسار،
                والتسعةُ التي بقيت بلا `plan:` — مسارات AJAX وشاشاتٌ لا
                مفتاحَ لها في الكتالوج — تصير محروسةً بهذا السور على
                الأقلّ: من لا يملك من المبيعات شيئاً لا يبلغها.
            */
            'plan:'.implode('|', SalesPlanFeatures::all()),
        ],
    ],

    function () {
        // Existing sales routes...

        Route::get('/dashboard/sales/department', [DashboardController::class, 'index'])
            ->name('sales.department.dashboard')
            ->middleware(['permission:sales.dashboard.view', 'plan:'.SalesPlanFeatures::DASHBOARD]);

        // «ماذا وراء هذا الرقم؟» — قائمةُ كل رقمٍ في اللوحة، بنمط لوحة العملاء
        Route::get('/dashboard/sales/department/segment', [DashboardController::class, 'segment'])
            ->name('sales.dashboard.segment')
            ->middleware(['permission:sales.dashboard.view', 'plan:'.SalesPlanFeatures::DASHBOARD]);

        // GiftOfferController routes
        Route::prefix('gift-offers')
            ->middleware('plan:'.SalesPlanFeatures::GIFT_OFFERS)
            ->group(function () {
                Route::get('/', [GiftOfferController::class, 'index'])->name('gift-offers.index')
                    ->middleware('permission:sales.gift_offers.view');
                Route::get('/create', [GiftOfferController::class, 'create'])->name('gift-offers.create')
                    ->middleware('permission:sales.gift_offers.create');
                Route::post('/store', [GiftOfferController::class, 'store'])->name('gift-offers.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_GIFT_OFFERS)
                    ->middleware('permission:sales.gift_offers.create');
                Route::get('/edit/{giftOffer}', [GiftOfferController::class, 'edit'])->name('gift-offers.edit')
                    ->middleware('permission:sales.gift_offers.edit');
                Route::put('/update/{id}', [GiftOfferController::class, 'update'])->name('gift-offers.update')
                    ->middleware('permission:sales.gift_offers.edit');
                Route::get('/show/{id}', [GiftOfferController::class, 'show'])->name('gift-offers.show')
                    ->middleware('permission:sales.gift_offers.view');
                Route::delete('/destroy/{giftOffer}', [GiftOfferController::class, 'destroy'])->name('gift-offers.destroy')
                    ->middleware('permission:sales.gift_offers.delete');
                // يقلب التفعيل — POST لا GET: رابطٌ أو صورةٌ في صفحةٍ أخرى كانا يكفيان لتبديله
                Route::post('/status/{id}', [GiftOfferController::class, 'status'])->name('gift-offers.status')
                    ->middleware('permission:sales.gift_offers.edit');
            });

        Route::prefix('sales')
            ->middleware('plan:'.SalesPlanFeatures::DASHBOARD)
            ->group(function () {
                Route::get('/kpis', [DashboardController::class, 'index'])->name('dashboard.kpis')
                    ->middleware('permission:sales.dashboard.view');
                // «أداء المندوبين» — جدولُ اللوحة كاملاً في شاشةٍ مستقلّة، بالأكثر تحصيلاً
                Route::get('/reps-performance', [DashboardController::class, 'reps'])->name('dashboard.reps')
                    ->middleware('permission:sales.dashboard.view');
                Route::get('/financial-analysis', [DashboardController::class, 'financialAnalysis'])->name('dashboard.financial_analysis')
                    ->middleware('permission:sales.dashboard.financial_analysis');
            });

        // ══════════ الإقفال اليومي ══════════
        // نُقل من `Modules\HR` — أسماء المسارات كما كانت (`daily-closing.*`)
        // فلا يتغيّر رابطٌ واحد في القوائم أو القوالب، والبادئة وحدها صارت
        // `sales` لتخضع الشاشةُ لمفتاح تطبيق المبيعات في «شاشة التطبيقات».
        Route::prefix('sales/daily-closing')
            ->middleware('plan:'.SalesPlanFeatures::DASHBOARD.'|'.SalesPlanFeatures::REPORTS)
            ->group(function () {
                Route::get('/', [DailyClosingController::class, 'index'])->name('daily-closing.index')
                    ->middleware('permission:sales.daily_closing.view');
                Route::post('/store', [DailyClosingController::class, 'store'])->name('daily-closing.store')
                    ->middleware('permission:sales.daily_closing.create');
                // جردُ المستودع يقرؤه نموذج الإقفال اليدوي وحده
                Route::get('/stock-products', [DailyClosingController::class, 'getStockProducts'])->name('daily-closing.stock-products')
                    ->middleware('permission:sales.daily_closing.create');
                Route::get('/system-data', [DailyClosingController::class, 'getSystemData'])->name('daily-closing.system-data')
                    ->middleware('permission:sales.daily_closing.auto_close|sales.daily_closing.review');
                // زرُّ «إقفال تلقائي» الواحد يفتح نافذة المراجعة ثم يؤكّد — أيٌّ من الصلاحيتين يكفي للاثنين
                Route::post('/auto-store', [DailyClosingController::class, 'autoStore'])->name('daily-closing.auto-store')
                    ->middleware('permission:sales.daily_closing.auto_close|sales.daily_closing.review');
                // الجماعيّان يمسّان كلّ الموظفين لا الموظفَ وحده — صلاحيةٌ إدارية منفصلة
                Route::post('/bulk-auto-store', [DailyClosingController::class, 'bulkAutoStore'])->name('daily-closing.bulk-auto-store')
                    ->middleware('permission:sales.daily_closing.bulk');
                Route::get('/bulk-print-report', [DailyClosingController::class, 'bulkPrintReport'])->name('daily-closing.bulk-print-report')
                    ->middleware('permission:sales.daily_closing.bulk_report');
                Route::get('/print-report', [DailyClosingController::class, 'printReport'])->name('daily-closing.print-report')
                    ->middleware('permission:sales.daily_closing.print_report');
                Route::get('/{id}/print', [DailyClosingController::class, 'print'])->name('daily-closing.print')
                    ->middleware('permission:sales.daily_closing.print');
                Route::delete('/{id}', [DailyClosingController::class, 'destroy'])->name('daily-closing.destroy')
                    ->middleware('permission:sales.daily_closing.delete');
            });

        Route::prefix('salesReports')
            ->middleware(['auth', 'plan:'.implode('|', SalesPlanFeatures::reports())])
            ->group(function () {
                // تقارير الفواتير
                Route::get('/index', [SalesReportsController::class, 'index'])->name('salesReports.index');

                // تقرير المبيعات حسب الموظف (من Controller جديد)
                Route::get('/byEmployee', [SalesByEmployeeController::class, 'index'])->name('salesReports.byEmployee')
                    ->middleware('permission:sales.reports.by_employee');
                Route::get('/byEmployeeAjax', [SalesByEmployeeController::class, 'ajax'])->name('salesReports.byEmployeeAjax')
                    ->middleware('permission:sales.reports.by_employee');
                Route::get('/byEmployee/clients-search', [SalesByEmployeeController::class, 'searchClients'])->name('salesReports.byEmployee.searchClients')
                    ->middleware('permission:sales.reports.by_employee');

                // تقرير المبيعات حسب العميل (من Controller جديد)
                Route::get('/byCustomer', [SalesByCustomerController::class, 'index'])->name('salesReports.byCustomer')
                    ->middleware('permission:sales.reports.by_customer');
                Route::get('/byCustomerAjax', [SalesByCustomerController::class, 'ajax'])->name('salesReports.byCustomerAjax')
                    ->middleware('permission:sales.reports.by_customer');
                Route::get('/byCustomer/clients-search', [SalesByCustomerController::class, 'searchClients'])->name('salesReports.byCustomer.searchClients')
                    ->middleware('permission:sales.reports.by_customer');

                // تقرير المبيعات حسب المنتج (من Controller جديد)
                Route::get('/byProductNew', [SalesByProductController::class, 'index'])->name('salesReports.byProductNew')
                    ->middleware('permission:sales.reports.by_product');
                Route::get('/byProductNewAjax', [SalesByProductController::class, 'ajax'])->name('salesReports.byProductNewAjax')
                    ->middleware('permission:sales.reports.by_product');
                Route::get('/byProductNew/clients-search', [SalesByProductController::class, 'searchClients'])->name('salesReports.byProductNew.searchClients')
                    ->middleware('permission:sales.reports.by_product');

                // تقرير المبيعات حسب البند (من Controller جديد)
                Route::get('/byItemNew', [SalesByItemController::class, 'index'])->name('salesReports.byItemNew')
                    ->middleware('permission:sales.reports.by_item');
                Route::get('/byItemNewAjax', [SalesByItemController::class, 'ajax'])->name('salesReports.byItemNewAjax')
                    ->middleware('permission:sales.reports.by_item');
                /* مغذّي مُنتقي العميل في الشاشة — محروسٌ بصلاحية التقرير نفسِها.
                   ومسارُ تقارير العملاء لا يصلح بديلاً: حارسُه صلاحياتُ تلك
                   التقارير، فيعود 403 صامتاً داخل القائمة المنسدلة. */
                Route::get('/byItemNew/clients-search', [SalesByItemController::class, 'searchClients'])->name('salesReports.byItemNew.searchClients')
                    ->middleware('permission:sales.reports.by_item');

                // تقرير المبيعات حسب مجموعة المنتج والتصنيف والوحدات (جديد)
                Route::get('/byProductGroup', [SalesByProductGroupController::class, 'index'])->name('salesReports.byProductGroup')
                    ->middleware('permission:sales.reports.by_product_group');
                Route::get('/byProductGroupAjax', [SalesByProductGroupController::class, 'ajax'])->name('salesReports.byProductGroupAjax')
                    ->middleware('permission:sales.reports.by_product_group');

                // تقرير المدفوعات حسب العميل (جديد)
                Route::get('/payments/byCustomer', [PaymentsByCustomerController::class, 'index'])->name('paymentsReports.byCustomer')
                    ->middleware('permission:sales.reports.payments_by_customer');
                Route::get('/payments/byCustomerAjax', [PaymentsByCustomerController::class, 'ajax'])->name('paymentsReports.byCustomerAjax')
                    ->middleware('permission:sales.reports.payments_by_customer');

                // تقرير المدفوعات حسب الموظف (جديد)
                Route::get('/payments/byEmployee', [PaymentsByEmployeeController::class, 'index'])->name('paymentsReports.byEmployee')
                    ->middleware('permission:sales.reports.payments_by_employee');
                Route::get('/payments/byEmployeeAjax', [PaymentsByEmployeeController::class, 'ajax'])->name('paymentsReports.byEmployeeAjax')
                    ->middleware('permission:sales.reports.payments_by_employee');

                // الـ Routes القديمة (للتوافق)
                Route::get('/sales-reports/by-employee-ajax', [SalesReportsController::class, 'byEmployeeAjax'])->name('salesReports.byEmployeeAjaxOld')
                    ->middleware('permission:sales.reports.by_employee');
                Route::get('/byInvoice', [SalesReportsController::class, 'byInvoice'])->name('salesReports.byInvoice');
                Route::get('/sales/by-employee/export', [SalesReportsController::class, 'exportByEmployeeToExcel'])->name('salesReports.byEmployee.export');
                Route::get('/exportByCustomerToExcel', [SalesReportsController::class, 'exportByCustomerToExcel'])->name('salesReports.exportByCustomerToExcel');
                Route::get('/sales/by-employee/export', [SalesReportsController::class, 'clientPaymentReport'])->name('salesReports.clientPaymentReport');
                Route::get('/sales-reports/export-by-product', [SalesReportsController::class, 'exportByProductToExcel'])->name('salesReports.exportByProduct');
                Route::get('/sales-reports/ProfitReportTime', [SalesReportsController::class, 'ProfitReportTime'])->name('salesReports.ProfitReportTime');
                Route::get('/payments-receipts/employee-report', [SalesReportsController::class, 'employeePaymentsReceiptsReport'])->name('salesReports.employeePaymentsReceiptsReport')
                    ->middleware('permission:sales.reports.payments_by_employee');

                Route::get('/payments-receipts/employee-report-ajax', [SalesReportsController::class, 'employeePaymentsReceiptsReportAjax'])->name('salesReports.employeePaymentsReceiptsReportAjax')
                    ->middleware('permission:sales.reports.payments_by_employee');
                Route::get('/payments-receipts/payment-method-report', [SalesReportsController::class, 'paymentMethodReport'])->name('salesReports.paymentMethodReport')
                    ->middleware('permission:sales.reports.payment_methods');

                Route::get('/payment-method-report', [SalesReportsController::class, 'paymentMethodReportAjax'])->name('salesReports.paymentMethodReportAjax')
                    ->middleware('permission:sales.reports.payment_methods');

                Route::get('/sales-reports/by-item', [SalesReportsController::class, 'byItemReport'])->name('salesReports.byItem')
                    ->middleware('permission:sales.reports.by_item');
                Route::get('/sales-reports/by-item-ajax', [SalesReportsController::class, 'byItemReportAjax'])->name('salesReports.byItemReportAjax')
                    ->middleware('permission:sales.reports.by_item');
                Route::get('/papatyment', [SalesReportsController::class, 'patyment'])->name('salesReports.patyment');

                // تقرير أرباح المبيعات حسب العميل (الجديد)
                Route::get('/reports/profits', [SalesProfitsController::class, 'index'])->name('salesReports.profits')
                    ->middleware('permission:sales.reports.profits');
                Route::get('/reports/profits-ajax', [SalesProfitsController::class, 'ajax'])->name('salesReports.profitsAjax')
                    ->middleware('permission:sales.reports.profits');

                // تقرير التحصيلات (الجديد)
                Route::get('/reports/collections', [CollectionsReportController::class, 'index'])->name('salesReports.collections')
                    ->middleware('permission:sales.reports.collections');
                Route::get('/reports/collections/clients-search', [CollectionsReportController::class, 'searchClients'])->name('salesReports.collections.searchClients')
                    ->middleware('permission:sales.reports.collections');
                // نافذة «ملاحظات الفاتورة»: بطاقات ملاحظات العميل بمنافسيها وصورها، وإضافة ملاحظة
                Route::get('/reports/collections/notes', [CollectionsReportController::class, 'collectionNotes'])->name('salesReports.collections.notes')
                    ->middleware('permission:sales.reports.collections');
                Route::post('/reports/collections/notes', [CollectionsReportController::class, 'storeCollectionNote'])->name('salesReports.collections.storeNote')
                    ->middleware('permission:sales.reports.collections');

                // تقرير التبديلات — بنود التبديل بأسبابها ومستودعاتها، وملخّصٌ بأيّ تجميع
                Route::get('/reports/exchanges', [ExchangesReportController::class, 'index'])->name('salesReports.exchanges')
                    ->middleware(['permission:sales.reports.exchanges', 'plan:'.SalesPlanFeatures::PRODUCT_EXCHANGES]);
                Route::get('/reports/exchanges/clients-search', [ExchangesReportController::class, 'searchClients'])->name('salesReports.exchanges.searchClients')
                    ->middleware('permission:sales.reports.exchanges');

                // تقرير العمولات — المصروف من تسويات أهداف المبيعات والتحصيلات، والمستحقّ غير المصروف
                Route::get('/reports/commissions', [SalesCommissionsReportController::class, 'index'])->name('salesReports.commissions')
                    ->middleware('permission:sales.reports.commissions');

                // تفاصيل نشاط العميل في شهر معين (AJAX)
                Route::get('/reports/client-month-details', [CollectionsReportController::class, 'getClientMonthDetails'])->name('salesReports.clientMonthDetails')
                    ->middleware('permission:sales.reports.collections');

                // تفاصيل العميل للنوافذ المنبثقة (AJAX)
                Route::get('/reports/client-modal-details', [CollectionsReportController::class, 'getClientDetails'])->name('salesReports.clientModalDetails')
                    ->middleware('permission:sales.reports.collections');

                // تقرير العملاء الموحد (يجمع: فواتير + تحصيلات + رصيد + آخر تحصيل + أيام + عهدة)
                Route::get('/reports/unified-customer', [UnifiedCustomerReportController::class, 'index'])->name('salesReports.unified')
                    ->middleware('permission:sales.reports.unified_customer');

                Route::get('/sales-reports/profit-timeline', [SalesReportsController::class, 'profitTimeline'])->name('salesReports.profitTimeline');
                Route::get('/reports/customerProfits', [SalesReportsController::class, 'customerProfits'])->name('salesReports.customerProfits');
                Route::get('/employee-comprehensive-profits', [SalesReportsController::class, 'employeeComprehensiveProfitsReport'])->name('salesReports.employeeProfits')
                    ->middleware('permission:sales.reports.profits');

                // AJAX لتحميل بيانات تقرير الأرباح الشامل
                Route::get('/employee-comprehensive-profits-ajax', [SalesReportsController::class, 'employeeComprehensiveProfitsReportAjax'])->name('salesReports.employeeComprehensiveProfitsReportAjax')
                    ->middleware('permission:sales.reports.profits');
                Route::get('/salaryRep', [SalesReportsController::class, 'salaryRep'])->name('salesReports.salaryRep');
                Route::get('/byProduct', [SalesReportsController::class, 'byProduct'])->name('salesReports.byProduct')
                    ->middleware('permission:sales.reports.by_product');

                Route::get('/sales/by-product-ajax', [SalesReportsController::class, 'byProductReportAjax'])->name('salesReports.byProductReportAjax')
                    ->middleware('permission:sales.reports.by_product');

                // تقارير المبيعات حسب الفترة الزمنية
                Route::get('/salesByPeriod', [SalesReportsController::class, 'salesByPeriod'])->name('salesReports.salesByPeriod')
                    ->middleware('permission:sales.reports.periods');

                // تقارير المدفوعات حسب الفترة الزمنية
                Route::get('/paymentsByPeriod', [SalesReportsController::class, 'paymentsByPeriod'])->name('salesReports.paymentsByPeriod')
                    ->middleware('permission:sales.reports.periods');
            });
        // OffersController routes
        Route::prefix('offers')
            ->middleware('plan:'.SalesPlanFeatures::OFFERS)
            ->group(function () {
                // كان الاسم سطراً فارغاً ومسافات، فلم يكن لشاشة العروض اسم يُنادى به
                Route::get('/', [OffersController::class, 'index'])->name('offers.index')
                    ->middleware('permission:sales.offers.view');
                Route::get('/create', [OffersController::class, 'create'])->name('offers.create')
                    ->middleware('permission:sales.offers.create');
                Route::post('/store', [OffersController::class, 'store'])->name('offers.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_OFFERS)
                    ->middleware('permission:sales.offers.create');
                Route::get('/edit/{id}', [OffersController::class, 'edit'])->name('offers.edit')
                    ->middleware('permission:sales.offers.edit');
                Route::put('/update/{id}', [OffersController::class, 'update'])->name('offers.update')
                    ->middleware('permission:sales.offers.edit');
                Route::get('/show/{id}', [OffersController::class, 'show'])->name('offers.show')
                    ->middleware('permission:sales.offers.view');
                Route::delete('/destroy/{id}', [OffersController::class, 'destroy'])->name('offers.destroy')
                    ->middleware('permission:sales.offers.delete');
            });

        // PaymentProcessController routes
        Route::prefix('payments-client')
            ->middleware('plan:'.SalesPlanFeatures::PAYMENTS)
            ->group(function () {
                Route::get('/', [SalesPaymentController::class, 'index'])->name('paymentsClient.index')
                    ->middleware('permission:sales.payments.view');
                Route::get('/create/{id}/{type?}', [SalesPaymentController::class, 'create'])->name('paymentsClient.create')
                    ->middleware('permission:sales.payments.create');
                Route::post('/store', [SalesPaymentController::class, 'store'])->name('paymentsClient.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_PAYMENTS)
                    ->middleware('permission:sales.payments.create');
                Route::get('/show/{id}', [SalesPaymentController::class, 'show'])->name('paymentsClient.show')
                    ->middleware('permission:sales.payments.view');
                Route::post('/cancel/{id}', [SalesPaymentController::class, 'cancel'])->name('paymentsClient.cancel')
                    ->middleware('permission:sales.payments.cancel');
                Route::get('/edit/{id}', [SalesPaymentController::class, 'edit'])->name('paymentsClient.edit')
                    ->middleware('permission:sales.payments.edit');
                Route::get('/rereceipt/{id}', [SalesPaymentController::class, 'rereceipt'])->name('paymentsClient.rereceipt')
                    ->middleware('permission:sales.payments.print');
                Route::get('/receipt/pdf/{id}', [SalesPaymentController::class, 'pdfReceipt'])->name('paymentsClient.pdf')
                    ->middleware('permission:sales.payments.print');
                Route::post('/send-email/{id}', [SalesPaymentController::class, 'sendReceiptEmail'])->name('paymentsClient.send-email')
                    ->middleware('permission:sales.payments.print');
                Route::delete('/destroy/{id}', [SalesPaymentController::class, 'destroy'])->name('paymentsClient.destroy')
                    ->middleware('permission:sales.payments.delete');
                Route::put('/update/{id}', [SalesPaymentController::class, 'update'])->name('paymentsClient.update')
                    ->middleware('permission:sales.payments.edit');
                Route::get('invoice-details/{invoice_id}', [SalesPaymentController::class, 'getInvoiceDetails'])->name('paymentsClient.invoice-details')
                    ->middleware('permission:sales.payments.view');

                // الدفع المجمع للفواتير
                Route::get('/bulk-payment', [SalesPaymentController::class, 'bulkPaymentCreate'])->name('paymentsClient.bulk-payment')
                    ->middleware('permission:sales.payments.bulk');
                Route::post('/bulk-payment/store', [SalesPaymentController::class, 'bulkPaymentStore'])->name('paymentsClient.bulk-payment.store')
                    ->middleware('permission:sales.payments.bulk');
                Route::get('/client-unpaid-invoices', [SalesPaymentController::class, 'getClientUnpaidInvoices'])->name('paymentsClient.client-unpaid-invoices')
                    ->middleware('permission:sales.payments.view');
            });

        // PeriodicInvoicesController routes
        Route::prefix('periodic-invoices')
            ->middleware('plan:'.SalesPlanFeatures::PERIODIC_INVOICES)
            ->group(function () {
                Route::get('/', [PeriodicInvoicesController::class, 'index'])->name('periodic_invoices.index')
                    ->middleware('permission:sales.periodic_invoices.view');
                Route::get('/create', [PeriodicInvoicesController::class, 'create'])->name('periodic_invoices.create')
                    ->middleware('permission:sales.periodic_invoices.create');
                Route::post('/store', [PeriodicInvoicesController::class, 'store'])->name('periodic_invoices.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_PERIODIC_INVOICES)
                    ->middleware('permission:sales.periodic_invoices.create');
                // كان `'PeriodicInvoiceController@bulkDelete'` نصاً بلا مجالٍ ولا
                // جمع: صنفٌ لا وجود له، والحذفُ الجماعي معطَّلٌ رغم وجود دالّته
                // وصلاحيتها.
                Route::post('periodic_invoices/bulk-delete', [PeriodicInvoicesController::class, 'bulkDelete'])->name('periodic_invoices.bulk_delete')
                    ->middleware('permission:sales.periodic_invoices.bulk_delete');
                Route::get('/show/{id}', [PeriodicInvoicesController::class, 'show'])->name('periodic_invoices.show')
                    ->middleware('permission:sales.periodic_invoices.view');
                Route::get('/edit/{id}', [PeriodicInvoicesController::class, 'edit'])->name('periodic_invoices.edit')
                    ->middleware('permission:sales.periodic_invoices.edit');
                Route::put('/update/{id}', [PeriodicInvoicesController::class, 'update'])->name('periodic_invoices.update')
                    ->middleware('permission:sales.periodic_invoices.edit');
                Route::delete('/destroy/{id}', [PeriodicInvoicesController::class, 'destroy'])->name('periodic_invoices.destroy')
                    ->middleware('permission:sales.periodic_invoices.delete');

                /* إدارةُ الاشتراك بعد إنشائه — إيقافه واستئنافه، وإصدارُ ما
                   استحقّ منه فوراً بدل انتظار المهمّة المجدولة. كانت الشاشة
                   تعرض حالة «موقوف/مفعَّل» ولا سبيل إلى تغييرها، ولا سبيل
                   إلى الإصدار إلّا سطرُ أمرٍ على الخادم. */
                Route::post('/toggle/{id}', [PeriodicInvoicesController::class, 'toggle'])->name('periodic_invoices.toggle')
                    ->middleware('permission:sales.periodic_invoices.edit');
                Route::post('/generate/{id}', [PeriodicInvoicesController::class, 'generateNow'])->name('periodic_invoices.generate')
                    ->middleware('permission:sales.periodic_invoices.edit');
            });

        // QuoteController routes
        Route::prefix('quotes')
            ->middleware('plan:'.SalesPlanFeatures::QUOTES)
            ->group(function () {
                Route::get('/', [QuoteController::class, 'index'])->name('questions.index')
                    ->middleware('permission:sales.quotes.view');
                Route::post('/send/{id}', [QuoteController::class, 'sendQuoteLink'])->name('questions.email')
                    ->middleware('permission:sales.quotes.send_link');
                Route::get('/create', [QuoteController::class, 'create'])->name('questions.create')
                    ->middleware('permission:sales.quotes.create');
                Route::get('/show/{id}', [QuoteController::class, 'show'])->name('questions.show')
                    ->middleware('permission:sales.quotes.view');
                Route::get('/edit/{id}', [QuoteController::class, 'edit'])->name('questions.edit')
                    ->middleware('permission:sales.quotes.edit');
                Route::post('/store', [QuoteController::class, 'store'])->name('questions.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_QUOTES)
                    ->middleware('permission:sales.quotes.create');
                /* فحصُ كوبونٍ قبل الحفظ — نظيرُ `invoices.check_coupon`
                   بصلاحية هذه الشاشة. والشاشة يجب أن تُري ما سيحفظه
                   الخادم، وإلّا خالف الإجمالي المعروض المحفوظَ. */
                Route::post('/check-coupon', [QuoteController::class, 'checkCoupon'])
                    ->name('questions.check_coupon')
                    ->middleware('permission:sales.quotes.create');
                Route::put('/update/{id}', [QuoteController::class, 'update'])->name('questions.update')
                    ->middleware('permission:sales.quotes.edit');
                Route::post('/convert-to-invoice/{id}', [QuoteController::class, 'convertToInvoice'])->name('questions.convert-to-invoice')
                    ->middleware('permission:sales.quotes.convert');
                Route::delete('/delete/{id}', [QuoteController::class, 'destroy'])->name('questions.destroy')
                    ->middleware('permission:sales.quotes.delete');

                /* الطباعة: `print()` كانت موجودةً في المتحكّم بلا مسارٍ يصل
                   إليها، بينما `sendQuoteLink()` تبني رابط البريد بـ
                   `route('questions.print')` — فترمي `RouteNotFoundException`
                   عند كل إرسال. المعرّف الثاني اختياريّ: قالب الطباعة
                   المصمَّم في «قوالب المستندات». */
                Route::get('/print/{id}/{template?}', [QuoteController::class, 'print'])->name('questions.print')
                    ->middleware('permission:sales.quotes.print');
                Route::get('/{id}/pdf', [QuoteController::class, 'downloadPdf'])->name('questions.pdf')
                    ->middleware('permission:sales.quotes.print');

                /* حُذفت ثلاثة مسارات:
                     questions.logs            → `logsaction()` تعيد `view('test')`
                     questions.template.edit   → `editTemplate()` لا وجود لها
                     questions.template.update → `updateTemplate()` لا وجود لها
                   الأخيران خطأٌ فادحٌ عند أوّل نداء، والأوّل قالب تجربة خلف
                   فحص صلاحية. */
            });

        /* أوامر البيع — `SalesOrderController` يرث `QuoteController`
           بالكامل ولا يبدّل إلّا نوعَ المستند وقسمَ ترقيمه وصلاحياته،
           فالمسارات نظيرةُ مسارات عروض الأسعار عمداً: الأفعال نفسُها
           والشاشات نفسُها. انظر تعليق الوارث.

           وحدُّ الاشتراك: أمر البيع مستندٌ يسبق الفاتورة كعرض السعر،
           فيُحسَب على حدّ عروض الأسعار نفسِه لا على حدٍّ ثالثٍ لا وجود
           له في خطط `SalesPlanFeatures`. */
        Route::prefix('sales-orders')
            ->middleware('plan:'.SalesPlanFeatures::QUOTES)
            ->group(function () {
                Route::get('/', [SalesOrderController::class, 'index'])->name('sales_orders.index')
                    ->middleware('permission:sales.sales_orders.view');
                Route::post('/send/{id}', [SalesOrderController::class, 'sendQuoteLink'])->name('sales_orders.email')
                    ->middleware('permission:sales.sales_orders.send_link');
                Route::get('/create', [SalesOrderController::class, 'create'])->name('sales_orders.create')
                    ->middleware('permission:sales.sales_orders.create');
                Route::get('/show/{id}', [SalesOrderController::class, 'show'])->name('sales_orders.show')
                    ->middleware('permission:sales.sales_orders.view');
                Route::get('/edit/{id}', [SalesOrderController::class, 'edit'])->name('sales_orders.edit')
                    ->middleware('permission:sales.sales_orders.edit');
                Route::post('/check-coupon', [SalesOrderController::class, 'checkCoupon'])
                    ->name('sales_orders.check_coupon')
                    ->middleware('permission:sales.sales_orders.create');
                Route::post('/store', [SalesOrderController::class, 'store'])->name('sales_orders.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_QUOTES)
                    ->middleware('permission:sales.sales_orders.create');
                Route::put('/update/{id}', [SalesOrderController::class, 'update'])->name('sales_orders.update')
                    ->middleware('permission:sales.sales_orders.edit');
                Route::post('/convert-to-invoice/{id}', [SalesOrderController::class, 'convertToInvoice'])
                    ->name('sales_orders.convert-to-invoice')
                    ->middleware('permission:sales.sales_orders.convert');
                Route::delete('/delete/{id}', [SalesOrderController::class, 'destroy'])->name('sales_orders.destroy')
                    ->middleware('permission:sales.sales_orders.delete');
                Route::get('/print/{id}/{template?}', [SalesOrderController::class, 'print'])->name('sales_orders.print')
                    ->middleware('permission:sales.sales_orders.print');
                Route::get('/{id}/pdf', [SalesOrderController::class, 'downloadPdf'])->name('sales_orders.pdf')
                    ->middleware('permission:sales.sales_orders.print');
            });

        // InvoicesController routes
        Route::prefix('invoices')
            ->middleware(['auth', 'plan:'.SalesPlanFeatures::INVOICES])
            ->group(function () {
                Route::get('/index', [InvoicesController::class, 'index'])->name('invoices.index')
                    ->middleware('permission:sales.invoices.view');
                Route::get('/create', [InvoicesController::class, 'create'])->name('invoices.create')
                    ->middleware('permission:sales.invoices.create');
                Route::post('/verify/code', [InvoicesController::class, 'verify_code'])->name('invoice.verify_code')
                    ->middleware('permission:sales.invoices.create');
                /* بياناتُ عرض سعرٍ (أو أمرِ بيع) لتعبئة شاشة الفاتورة به.
                   الحمولةُ تُجلَب عند الاختيار لا مع الشاشة: منشأةٌ لها
                   ألوفُ العروض تُحمِّل بنودَها كلَّها في كلّ فتحةِ شاشة. */
                Route::get('/quote-source/{quote}', [InvoicesController::class, 'quoteSource'])
                    ->name('invoices.quote_source')
                    ->middleware('permission:sales.invoices.create');
                // فحصُ كوبونٍ قبل الحفظ — ليرى البائع الخصم في الشاشة
                Route::post('/check-coupon', [InvoicesController::class, 'checkCoupon'])
                    ->name('invoices.check_coupon')
                    ->middleware('permission:sales.invoices.create');
                Route::get('/get-client/{id}', function ($id) {
                    $client = Client::find($id);

                    return response()->json($client);
                })->middleware('permission:sales.invoices.view');
                Route::post('/send/invoice/{id}', [InvoicesController::class, 'send_invoice'])->name('invoices.send')
                    ->middleware('permission:sales.invoices.send');
                Route::post('/send/verification', [InvoicesController::class, 'sendVerificationCode'])
                    ->middleware('permission:sales.invoices.create');
                Route::post('/verify-code', [InvoicesController::class, 'verifyCode'])
                    ->middleware('permission:sales.invoices.create');
                Route::get('/invoices/{id}/label', [InvoicesController::class, 'label'])->name('invoices.label')
                    ->middleware('permission:sales.invoices.labels');
                Route::get('/invoices/{id}/picklist', [InvoicesController::class, 'picklist'])->name('invoices.picklist')
                    ->middleware('permission:sales.invoices.print');
                Route::get('/invoices/{id}/shipping_label', [InvoicesController::class, 'shipping_label'])->name('invoices.shipping_label')
                    ->middleware('permission:sales.invoices.labels');
                Route::get('/show/{id}', [InvoicesController::class, 'show'])->name('invoices.show')
                    ->middleware('permission:sales.invoices.view');
                /* حُذف `invoices.import`: المسار يشير إلى `import()` غير
                   المعرَّفة في المتحكّم، ولا يستدعيه قالبٌ ولا زرّ. */
                Route::get('/edit/{id}', [InvoicesController::class, 'edit'])->name('invoices.edit')
                    ->middleware('permission:sales.invoices.edit');
                Route::post('/store', [InvoicesController::class, 'store'])->name('invoices.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_INVOICES)
                    ->middleware('permission:sales.invoices.create');
                Route::delete('/delete/{id}', [InvoicesController::class, 'destroy'])->name('invoices.destroy')
                    ->middleware('permission:sales.invoices.delete');
                Route::get('/{id}/generatePdf/{template?}', [InvoicesController::class, 'generatePdf'])->name('invoices.generatePdf')
                    ->middleware('permission:sales.invoices.export_pdf');

                Route::get('/{id}/print/{template?}', [InvoicesController::class, 'print'])->name('invoices.print')
                    ->middleware('permission:sales.invoices.print');
                Route::get('/{id}/print-tax/{template?}', [InvoicesController::class, 'printTax'])->name('invoices.print_tax')
                    ->middleware('permission:sales.invoices.print_tax');
                Route::get('/{id}/markAsPaidSilently', [InvoicesController::class, 'markAsPaidSilently'])->name('invoices.markAsPaidSilently')
                    ->middleware('permission:sales.invoices.mark_paid');

                Route::get('/get-price', [InvoicesController::class, 'getPrice'])->name('get-price')
                    ->middleware('permission:sales.invoices.create');
                Route::post('/invoices/{invoice}/signatures', [InvoicesController::class, 'storeSignatures'])->name('invoices.signatures.store')
                    ->middleware('permission:sales.invoices.edit');
                Route::post('/{id}/add-note-sales', [InvoicesController::class, 'addNote'])->name('invoices.addNote')
                    ->middleware('permission:sales.invoices.notes');
                Route::get('/{id}/get-notes-sales', [InvoicesController::class, 'getNotes'])->name('invoices.getNotes')
                    ->middleware('permission:sales.invoices.notes');
                Route::delete('/note-sales/{noteId}', [InvoicesController::class, 'deleteNote'])->name('invoices.deleteNote')
                    ->middleware('permission:sales.invoices.notes');
            });

        // Notification routes
        Route::prefix('notifications')
            ->middleware(['auth', 'plan:'.SalesPlanFeatures::NOTIFICATIONS])
            ->group(function () {

                // ⭐ AJAX endpoint للفلترة - يجب أن يكون قبل الـ route الرئيسي
                Route::get('/ajax', [NotificationsController::class, 'notificationsAjax'])->name('notifications.ajax')
                    ->middleware('permission:sales.notifications.view');

                // الصفحة الرئيسية
                Route::get('/', [NotificationsController::class, 'notifications'])->name('notifications.index')
                    ->middleware('permission:sales.notifications.view');

                // إنشاء إشعار
                Route::get('/create', [NotificationsController::class, 'create'])->name('notifications.create')
                    ->middleware('permission:sales.notifications.send');
                Route::post('/send', [NotificationsController::class, 'sendNotification'])->name('notifications.send')
                    ->middleware('permission:sales.notifications.send');

                // غير المقروءة
                Route::get('/unread', [NotificationsController::class, 'getUnreadNotifications'])->name('notifications.unread')
                    ->middleware('permission:sales.notifications.view');
                Route::get('/unread/count', [NotificationsController::class, 'getUnreadCount'])->name('notifications.unread.count')
                    ->middleware('permission:sales.notifications.view');

                // المرسلة
                Route::get('/sent', [NotificationsController::class, 'sentNotifications'])->name('notifications.sent')
                    ->middleware('permission:sales.notifications.view');

                // قراءة الإشعارات
                Route::post('/mark', [NotificationsController::class, 'markAsRead'])->name('notifications.markAsRead')
                    ->middleware('permission:sales.notifications.view');
                Route::post('/mark-all-read', [NotificationsController::class, 'markAllAsRead'])->name('notifications.markAllAsRead')
                    ->middleware('permission:sales.notifications.view');

                // ⭐ إشعارات Popup للعميل
                Route::get('/client-popup/{clientId}', [NotificationsController::class, 'getClientPopupNotifications'])->name('notifications.client.popup')
                    ->middleware('permission:sales.notifications.view');
                Route::post('/client-popup/mark-read', [NotificationsController::class, 'markClientPopupAsRead'])->name('notifications.client.popup.markRead')
                    ->middleware('permission:sales.notifications.view');

                // ⭐ إشعارات Popup للموظف
                Route::get('/employee-popup', [NotificationsController::class, 'getEmployeePopupNotifications'])->name('notifications.employee.popup')
                    ->middleware('permission:sales.notifications.view');
                Route::post('/employee-popup/mark-shown', [NotificationsController::class, 'markEmployeePopupAsShown'])->name('notifications.employee.popup.markShown')
                    ->middleware('permission:sales.notifications.view');

                // ⭐⭐⭐ الرد - لازم يكون قبل {id} ⭐⭐⭐
                Route::post('reply/{id}', [NotificationsController::class, 'replyToNotification'])->name('notifications.reply')
                    ->middleware('permission:sales.notifications.reply');
                Route::post('reply-form/{id}', [NotificationsController::class, 'replyToNotificationForm'])->name('notifications.reply.form')
                    ->middleware('permission:sales.notifications.reply');
                Route::post('client-reply/{id}', [NotificationsController::class, 'replyClientToNotification'])->name('notifications.client.reply')
                    ->middleware('permission:sales.notifications.reply');

                // الحذف
                Route::delete('{id}', [NotificationsController::class, 'destroy'])->name('notifications.destroy')
                    ->middleware('permission:sales.notifications.send');

                Route::get('/get-clients-by-employees', [NotificationsController::class, 'getClientsByEmployees'])->name('notifications.get-clients-by-employees')
                    ->middleware('permission:sales.notifications.view');
                // عرض محدد - آخر شي
                Route::get('{id}', [NotificationsController::class, 'show'])->name('notifications.show')
                    ->middleware('permission:sales.notifications.view');
            });

        // ⭐ API للكرون جوب - معالجة الإشعارات المجدولة (بدون auth)

        // ReturnInvoiceController routes
        Route::prefix('return-invoices')
            ->middleware('plan:'.SalesPlanFeatures::RETURN_INVOICES)
            ->group(function () {
                Route::get('/', [ReturnInvoiceController::class, 'index'])->name('ReturnIInvoices.index')
                    ->middleware('permission:sales.returns.view');
                Route::get('/create/{id}', [ReturnInvoiceController::class, 'create'])->name('ReturnIInvoices.create')
                    ->middleware('permission:sales.returns.create');
                Route::get('/show/{id}', [ReturnInvoiceController::class, 'show'])->name('ReturnIInvoices.show')
                    ->middleware('permission:sales.returns.view');
                Route::get('/{id}/print/{template?}', [ReturnInvoiceController::class, 'print'])->name('ReturnIInvoices.print')
                    ->middleware('permission:sales.returns.print');
                Route::post('/send-email/{id}', [ReturnInvoiceController::class, 'sendReturnInvoiceEmail'])->name('ReturnIInvoices.send-email')
                    ->middleware('permission:sales.returns.send');
                Route::get('/edit/{id}', [ReturnInvoiceController::class, 'edit_brand'])->name('ReturnIInvoices.edit')
                    ->middleware('permission:sales.returns.edit');
                Route::post('/store', [ReturnInvoiceController::class, 'store'])->name('ReturnIInvoices.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_RETURN_INVOICES)
                    ->middleware('permission:sales.returns.create');
                Route::put('/update/{id}', [ReturnInvoiceController::class, 'update'])->name('ReturnIInvoices.update')
                    ->middleware('permission:sales.returns.edit');
                Route::delete('/destroy/{id}', [ReturnInvoiceController::class, 'destroy'])->name('ReturnIInvoices.destroy')
                    ->middleware('permission:sales.returns.delete');
            });

        // ProductExchangeController routes - تبديل المنتجات
        Route::prefix('product-exchanges')
            ->middleware('plan:'.SalesPlanFeatures::PRODUCT_EXCHANGES)
            ->group(function () {
                Route::get('/dashboard', [ProductExchangeController::class, 'dashboard'])->name('product-exchanges.dashboard')
                    ->middleware('permission:sales.exchanges.dashboard');
                Route::get('/', [ProductExchangeController::class, 'index'])->name('product-exchanges.index')
                    ->middleware('permission:sales.exchanges.view');
                Route::get('/create', [ProductExchangeController::class, 'create'])->name('product-exchanges.create')
                    ->middleware('permission:sales.exchanges.create');
                Route::post('/store', [ProductExchangeController::class, 'store'])->name('product-exchanges.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_PRODUCT_EXCHANGES)
                    ->middleware('permission:sales.exchanges.create');
                Route::get('/show/{id}', [ProductExchangeController::class, 'show'])->name('product-exchanges.show')
                    ->middleware('permission:sales.exchanges.view');

                // التعديل — لم يكن للتبديل مسارُ تعديلٍ أصلاً، فكان تصحيحُ
                // صنفٍ أو كميةٍ يقتضي حذف العملية وإعادتها برقمٍ جديد.
                // المساران ثابتان قبل `{id}` كما في بقيّة مجموعات المبيعات
                Route::get('/edit/{id}', [ProductExchangeController::class, 'edit'])->name('product-exchanges.edit')
                    ->middleware('permission:sales.exchanges.edit');
                Route::put('/update/{id}', [ProductExchangeController::class, 'update'])->name('product-exchanges.update')
                    ->middleware('permission:sales.exchanges.edit');

                Route::get('/{id}/details', [ProductExchangeController::class, 'getDetails'])->name('product-exchanges.details')
                    ->middleware('permission:sales.exchanges.view');
                Route::get('/{id}/print', [ProductExchangeController::class, 'print'])->name('product-exchanges.print')
                    ->middleware('permission:sales.exchanges.print');
                Route::delete('/destroy/{id}', [ProductExchangeController::class, 'destroy'])->name('product-exchanges.destroy')
                    ->middleware('permission:sales.exchanges.delete');
                Route::get('/get-product-price', [ProductExchangeController::class, 'getProductPrice'])->name('product-exchanges.get-price')
                    ->middleware('permission:sales.exchanges.view');

                // إدارة الحدود اليومية للموظفين
                Route::get('/manage-limits', [ProductExchangeController::class, 'getEmployeeLimits'])->name('product-exchanges.get-limits')
                    ->middleware('permission:sales.exchanges.manage_limits');
                Route::post('/update-limits', [ProductExchangeController::class, 'updateLimits'])->name('product-exchanges.update-limits')
                    ->middleware('permission:sales.exchanges.manage_limits');

                // إدارة الحدود اليومية للعملاء
                Route::get('/get-client-limits', [ProductExchangeController::class, 'getClientLimits'])->name('product-exchanges.get-client-limits')
                    ->middleware('permission:sales.exchanges.manage_limits');
                Route::post('/update-client-limits', [ProductExchangeController::class, 'updateClientLimits'])->name('product-exchanges.update-client-limits')
                    ->middleware('permission:sales.exchanges.manage_limits');
            });

        // ExchangeReasonController routes - أسباب التبديل
        Route::prefix('exchange-reasons')
            ->middleware(['permission:sales.exchange_reasons.manage', 'plan:'.SalesPlanFeatures::EXCHANGE_REASONS])
            ->group(function () {
                Route::get('/', [ExchangeReasonController::class, 'index'])->name('exchange-reasons.index');
                Route::get('/create', [ExchangeReasonController::class, 'create'])->name('exchange-reasons.create');
                Route::post('/store', [ExchangeReasonController::class, 'store'])->name('exchange-reasons.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_EXCHANGE_REASONS);
                Route::get('/edit/{id}', [ExchangeReasonController::class, 'edit'])->name('exchange-reasons.edit');
                Route::put('/update/{id}', [ExchangeReasonController::class, 'update'])->name('exchange-reasons.update');
                Route::delete('/destroy/{id}', [ExchangeReasonController::class, 'destroy'])->name('exchange-reasons.destroy');
                Route::post('/toggle/{id}', [ExchangeReasonController::class, 'toggleActive'])->name('exchange-reasons.toggle');
            });

        // RevolvingInvoicesController routes — الكتالوج يعرّف .view و.create فقط لهذه الشاشة بعد
        Route::prefix('revolving-invoices')
            ->middleware('plan:'.SalesPlanFeatures::REVOLVING_INVOICES)
            ->group(function () {
                Route::get('/', [RevolvingInvoicesController::class, 'index'])->name('revolving-invoices.index')
                    ->middleware('permission:sales.revolving_invoices.view');
                Route::get('/create', [RevolvingInvoicesController::class, 'create'])->name('revolving-invoices.create')
                    ->middleware('permission:sales.revolving_invoices.create');
                Route::post('/store', [RevolvingInvoicesController::class, 'store'])->name('revolving-invoices.store')
                    ->middleware('permission:sales.revolving_invoices.create');
                Route::get('/show/{id}', [RevolvingInvoicesController::class, 'show'])->name('revolving-invoices.show')
                    ->middleware('permission:sales.revolving_invoices.view');
                Route::get('/edit/{id}', [RevolvingInvoicesController::class, 'edit'])->name('revolving-invoices.edit')
                    ->middleware('permission:sales.revolving_invoices.create');
                Route::put('/update/{id}', [RevolvingInvoicesController::class, 'update'])->name('revolving-invoices.update')
                    ->middleware('permission:sales.revolving_invoices.create');
                Route::delete('/destroy/{id}', [RevolvingInvoicesController::class, 'destroy'])->name('revolving-invoices.destroy')
                    ->middleware('permission:sales.revolving_invoices.create');
            });

        // ShippingOptionsController routes
        Route::prefix('shipping-options')
            ->middleware('plan:'.SalesPlanFeatures::SHIPPING_OPTIONS)
            ->group(function () {
                Route::get('/', [ShippingOptionsController::class, 'index'])->name('shippingOptions.index')
                    ->middleware('permission:sales.shipping_options.view');
                Route::get('/create', [ShippingOptionsController::class, 'create'])->name('shippingOptions.create')
                    ->middleware('permission:sales.shipping_options.manage');
                Route::post('/store', [ShippingOptionsController::class, 'store'])->name('shippingOptions.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_SHIPPING_OPTIONS)
                    ->middleware('permission:sales.shipping_options.manage');
                Route::get('/edit/{id}', [ShippingOptionsController::class, 'edit'])->name('shippingOptions.edit')
                    ->middleware('permission:sales.shipping_options.manage');
                Route::get('/show/{id}', [ShippingOptionsController::class, 'show'])->name('shippingOptions.show')
                    ->middleware('permission:sales.shipping_options.view');
                Route::put('/update/{id}', [ShippingOptionsController::class, 'update'])->name('shippingOptions.update')
                    ->middleware('permission:sales.shipping_options.manage');
                // يقلب الحالة — POST لا GET: رابطٌ أو صورةٌ في صفحةٍ أخرى كانا يكفيان لتبديلها
                Route::post('/updateStatus/{id}', [ShippingOptionsController::class, 'updateStatus'])->name('shippingOptions.updateStatus')
                    ->middleware('permission:sales.shipping_options.manage');
                Route::delete('/destroy/{id}', [ShippingOptionsController::class, 'destroy'])->name('shippingOptions.destroy')
                    ->middleware('permission:sales.shipping_options.manage');
            });

        // SittingInvoiceController routes
        //
        // كانت هذه المجموعة تحمل أربعة مسارات إلى دوالّ غير موجودة في
        // المتحكّم (`create` و`store` مرّتين) وستّة أسماء مكرّرة لنفس الوجهة
        // (`SittingInvoice.update` نسخة من `templates.update` وهكذا)، إضافةً
        // إلى `templates.store` و`template.preview` اللذين تسجّلهما وحدات
        // أخرى أيضاً — والاسم المكرّر يجعل `route()` يعطي رابط آخر مسجَّل لا
        // رابط المقصود. أُبقي من كلّ وجهة اسم واحد.
        Route::prefix('sitting')
            ->middleware('plan:'.implode('|', SalesPlanFeatures::all()))
            ->group(function () {
                Route::get('/settingsInvoice', [SittingInvoiceController::class, 'index'])->name('sittingInvoice.index')
                    ->middleware('permission:sales.settings.view');
                // الفاتورة الإلكترونية — كانت ثلاث دوالّ في `SittingInvoiceController`
                // لا تفعل إلا `return view(...)`، بلا جدول ولا حفظ ولا تحقّق
                Route::get('/settings/electronic-invoice', [ElectronicInvoiceController::class, 'index'])->name('settings.electronic_invoice')
                    ->middleware('permission:sales.settings.view');
                Route::get('/settings/electronic-edit', [ElectronicInvoiceController::class, 'edit'])->name('electronic_invoice_edit')
                    ->middleware('permission:sales.settings.view');
                Route::get('/settings/electronic-company', [ElectronicInvoiceController::class, 'company'])->name('electronic_invoice.company')
                    ->middleware('permission:sales.settings.view');
                // الاسم القديم يبقى: القائمة الجانبية والقوالب تشير إليه
                Route::get('/settings/electronic-send', [ElectronicInvoiceController::class, 'company'])->name('electronic_invoice_send')
                    ->middleware('permission:sales.settings.view');
                Route::put('/settings/electronic-update', [ElectronicInvoiceController::class, 'update'])->name('electronic_invoice.update')
                    ->middleware('permission:sales.settings.update');
                Route::post('/settings/electronic-activate', [ElectronicInvoiceController::class, 'activate'])->name('electronic_invoice.activate')
                    ->middleware('permission:sales.settings.update');
                Route::post('/settings/electronic-deactivate', [ElectronicInvoiceController::class, 'deactivate'])->name('electronic_invoice.deactivate')
                    ->middleware('permission:sales.settings.update');

                // الربط مع هيئة الزكاة والضريبة والجمارك: التسجيل، سجلّ الإرسال،
                // والإرسال من صفحة المستند
                Route::post('/settings/electronic-onboard', [ElectronicInvoiceController::class, 'onboard'])->name('electronic_invoice.onboard')
                    ->middleware('permission:sales.settings.update');
                Route::post('/settings/electronic-phase', [ElectronicInvoiceController::class, 'phase'])->name('electronic_invoice.phase')
                    ->middleware('permission:sales.settings.update');
                Route::get('/electronic-invoice/{document}/pdf', [ElectronicInvoiceController::class, 'downloadPdf'])->name('electronic_invoice.documents.pdf')
                    ->whereNumber('document')->middleware('permission:sales.invoices.export_pdf');
                Route::get('/settings/electronic-documents', [ElectronicInvoiceController::class, 'documents'])->name('electronic_invoice.documents')
                    ->middleware('permission:sales.settings.view');
                Route::get('/settings/electronic-documents/{document}/xml', [ElectronicInvoiceController::class, 'downloadXml'])->name('electronic_invoice.documents.xml')
                    ->whereNumber('document')->middleware('permission:sales.settings.view');
                Route::post('/settings/electronic-documents/{document}/retry', [ElectronicInvoiceController::class, 'retry'])->name('electronic_invoice.documents.retry')
                    ->whereNumber('document')->middleware('permission:sales.settings.update');
                Route::post('/electronic-invoice/send/{type}/{id}', [ElectronicInvoiceController::class, 'send'])->name('electronic_invoice.send')
                    ->whereIn('type', ['invoice', 'credit_note'])->whereNumber('id')
                    ->middleware('permission:sales.invoices.send');

                // إعدادات الفواتير: العرض ثم الحفظ
                Route::get('/invoice', [SittingInvoiceController::class, 'invoice'])->name('SittingInvoice.invoice')
                    ->middleware('permission:sales.settings.view');
                Route::put('/update-sitting', [SittingInvoiceController::class, 'update_invoices'])->name('settings.update_invoices')
                    ->middleware('permission:sales.settings.update');

                // تصاميم مستندات المبيعات — نافذة على `DocumentTemplate`.
                // كان هذا المسار يعرض نظام `Template` القديم، وقالبه في الوحدة
                // ملفٌّ بقسم محتوى فارغ.
                Route::get('/bill-designs', [SalesDocumentDesignsController::class, 'index'])->name('SittingInvoice.bill-designs')
                    ->middleware('permission:sales.settings.invoice_designs');

                /* نظام القوالب القديم (`Template`) أُزيل بمساراته الثمانية:
                   legacy-designs وtemplate/preview و{template} تعديلاً وحفظاً
                   واستعادةً وحذفاً، وtest_print وtest-print/{id}.

                   معاينته وطباعته كانتا تمرّران محتوى القالب إلى
                   `Blade::render()` بعد تحويل `@php` و`{!! !!}` إلى PHP خام —
                   أي أن من يملك تعديل الإعدادات ينفّذ شيفرةً على الخادم. ولا
                   مسارَ طباعةٍ حقيقيّاً كان يقرأ منه: مستندات المبيعات كلّها
                   تُطبع من `DocumentTemplate` عبر «تصاميم مستندات المبيعات». */

                // Order Sources routes
                Route::get('/order-sources', [OrderSourceController::class, 'index'])->name('order_sources.index')
                    ->middleware('permission:sales.order_sources.view');
                Route::post('/order-sources', [OrderSourceController::class, 'storeOrUpdate'])->name('order_sources.storeOrUpdate')
                    ->middleware('permission:sales.order_sources.manage');
                Route::delete('/order-sources/{id}', [OrderSourceController::class, 'destroy'])->name('order_sources.destroy')
                    ->middleware('permission:sales.order_sources.manage');
                Route::post('/order-sources/sort', [OrderSourceController::class, 'sort'])->name('order_sources.sort')
                    ->middleware('permission:sales.order_sources.manage');
            });

        // CreditNotificationController routes
        Route::prefix('credit-notes')
            ->middleware('plan:'.SalesPlanFeatures::CREDIT_NOTES)
            ->group(function () {
                Route::get('/', [CreditNotificationController::class, 'index'])->name('CreditNotes.index')
                    ->middleware('permission:sales.credit_notes.view');
                Route::get('/create', [CreditNotificationController::class, 'create'])->name('CreditNotes.create')
                    ->middleware('permission:sales.credit_notes.create');
                // معاينةُ ما سيُسدّده الإشعار من ديون العميل — `ClientDebtSettlement`
                Route::get('/settlement-preview', [CreditNotificationController::class, 'settlementPreview'])->name('CreditNotes.settlementPreview')
                    ->middleware('permission:sales.credit_notes.create');
                // فواتيرُ العميل المختار المتاحة مرجعاً — تتبع اختيار العميل في الشاشة
                Route::get('/client-invoices', [CreditNotificationController::class, 'clientInvoices'])->name('CreditNotes.clientInvoices')
                    ->middleware('permission:sales.credit_notes.create');
                Route::post('/cancel/{id}', [CreditNotificationController::class, 'cancel'])->name('CreditNotes.cancel')
                    ->middleware('permission:sales.credit_notes.cancel');
                Route::get('/show/{id}', [CreditNotificationController::class, 'show'])->name('CreditNotes.show')
                    ->middleware('permission:sales.credit_notes.view');
                Route::post('/send-email/{id}', [CreditNotificationController::class, 'sendCreditNotification'])->name('CreditNotes.send')
                    ->middleware('permission:sales.credit_notes.send');
                Route::get('/edit/{id}', [CreditNotificationController::class, 'edit'])->name('CreditNotes.edit')
                    ->middleware('permission:sales.credit_notes.edit');
                Route::post('/store', [CreditNotificationController::class, 'store'])->name('CreditNotes.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_CREDIT_NOTES)
                    ->middleware('permission:sales.credit_notes.create');
                Route::put('/update/{id}', [CreditNotificationController::class, 'update'])->name('CreditNotes.update')
                    ->middleware('permission:sales.credit_notes.edit');
                Route::delete('/destroy/{id}', [CreditNotificationController::class, 'destroy'])->name('CreditNotes.destroy')
                    ->middleware('permission:sales.credit_notes.delete');
                Route::get('/print/{id}', [CreditNotificationController::class, 'print'])->name('CreditNotes.print')
                    ->middleware('permission:sales.credit_notes.print');
            });
        /*
         * اتفاقيات التقسيط — مُنقولة إلى كتالوج sales.installments.* (نفس
         * نقل باقي شاشات المبيعات). الأسماء القديمة (installments_view_all
         * إلخ) لم تعد تُستعمَل هنا؛ الأدوار التي كانت تملكها مُنحت الأسماء
         * الجديدة صراحةً في قاعدة البيانات عند هذا النقل.
         *
         * **المسارات المحذوفة**: `edit_amount` و`show_amount` كانتا تعرضان
         * قوالب `installments_detites/` — شاشتان تعدّلان «قسطاً» وتُرسلان
         * إلى `installments.update` (متحكّم الاتفاقية) بحقلين بلا قيمٍ
         * مبدئية، وإحداهما تعرض `sales::Installments…` بحرفٍ كبير فتعطي
         * 404 على أيّ خادم Linux. حلّ محلّهما جدولُ الأقساط وإلغاء القسط.
         */
        Route::prefix('Installments')
            ->middleware(['auth', 'plan:'.SalesPlanFeatures::INSTALLMENTS])
            ->group(function () {
                Route::get('/index', [InstallmentsController::class, 'index'])->name('installments.index')
                    ->middleware('permission:sales.installments.view');
                Route::get('/create', [InstallmentsController::class, 'create'])->name('installments.create')
                    ->middleware('permission:sales.installments.create');
                Route::post('/store', [InstallmentsController::class, 'store'])->name('installments.store')
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_INSTALLMENT_PLANS)
                    ->middleware('permission:sales.installments.create');

                // جدول الأقساط عبر الاتفاقيات كلّها — شاشة المتابعة والتحصيل.
                // الاسم القديم يبقى: روابطُ محفوظةٌ وقوائمُ تشير إليه
                Route::get('/schedule', [InstallmentsController::class, 'schedule'])
                    ->name('installments.agreement_installments')
                    ->middleware('permission:sales.installments.view');

                Route::get('/show/{id}', [InstallmentsController::class, 'show'])->name('installments.show')
                    ->middleware('permission:sales.installments.view');
                Route::get('/edit/{id}', [InstallmentsController::class, 'edit'])->name('installments.edit')
                    ->middleware('permission:sales.installments.agreement');
                Route::put('/update/{id}', [InstallmentsController::class, 'update'])->name('installments.update')
                    ->middleware('permission:sales.installments.agreement');
                Route::delete('/destroy/{id}', [InstallmentsController::class, 'destroy'])->name('installments.destroy')
                    ->middleware('permission:sales.installments.agreement');

                // ── الطباعة: تناديها الشاشة ولم تكن مسجَّلة ───────────
                Route::get('/print/{id}', [InstallmentsController::class, 'print'])->name('installments.print')
                    ->middleware('permission:sales.installments.view');
                Route::get('/pdf/{id}', [InstallmentsController::class, 'pdf'])->name('installments.pdf')
                    ->middleware('permission:sales.installments.view');

                // ── التحصيل ──────────────────────────────────────────
                // `POST` لا `GET`: كلاهما يغيّر حالة
                Route::post('/sync/{id}', [InstallmentsController::class, 'sync'])->name('installments.sync')
                    ->middleware('permission:sales.installments.edit_amount');
                Route::post('/detail/{id}/cancel', [InstallmentsController::class, 'cancelDetail'])
                    ->name('installments.detail.cancel')
                    ->middleware('permission:sales.installments.edit_amount');
            });

        /*
            مسارات الأهداف (Targets).

            **`permission:` لا `can:`** — كانت المسارات المشتركة بين الإدارة
            والتسوية مكتوبةً `can:sales.targets.manage,sales.targets.settle`
            بنيّة «أيّ الصلاحيتين». و`can:` تقرأ ما بعد الفاصلة **نموذجاً
            يُمرَّر إلى البوابة لا صلاحيةً ثانية**، فيلتقطه خطّاف Spatie
            اسمَ حارسٍ (`registerPermissions()`) ويسقط الفحص كلُّه. والنتيجة
            أنّ من يملك «تسوية الأهداف» وحدها لا يبلغ القائمة ولا التقرير
            ولا صفحة الهدف — أي لا يستطيع التسوية أصلاً.

            الأنبوب في وسيط Spatie (`permission:a|b`) هو «أو» الحقيقية:
            الوسيط يفصله ويمرّ على الصلاحيتين بـ`canAny`.
        */
        Route::prefix('targets')
            ->middleware('plan:'.SalesPlanFeatures::TARGET_SETTINGS)
            ->group(function () {
                // عرض القائمة
                Route::get('/', [SalesTargetController::class, 'index'])->name('sales-targets.index')->middleware('permission:sales.targets.manage|sales.targets.settle');

                // إعدادات الأهداف (مبيعات / تحصيلات / مرنة للتوسع) — لم تكن محروسة إطلاقاً خلافاً لبقيّة مسارات هذه المجموعة
                Route::get('/settings/{type?}', [TargetSettingsController::class, 'index'])
                    ->name('sales-targets.settings')
                    ->middleware('permission:sales.target_settings.view');
                Route::post('/settings/{type}', [TargetSettingsController::class, 'update'])
                    ->name('sales-targets.settings.update')
                    ->middleware('permission:sales.target_settings.update');

                // إضافة وتعديل الأهداف - المسارات الثابتة أولاً قبل {id}
                Route::get('/create', [SalesTargetController::class, 'create'])->name('sales-targets.create')->middleware('permission:sales.targets.manage');
                Route::post('/store', [SalesTargetController::class, 'store'])->name('sales-targets.store')->middleware('permission:sales.targets.manage');

                // المسارات الديناميكية بعد المسارات الثابتة
                Route::get('/{id}', [SalesTargetController::class, 'show'])->name('sales-targets.show')->middleware('permission:sales.targets.manage|sales.targets.settle');
                Route::get('/{id}/edit', [SalesTargetController::class, 'edit'])->name('sales-targets.edit')->middleware('permission:sales.targets.manage');
                Route::put('/{id}', [SalesTargetController::class, 'update'])->name('sales-targets.update')->middleware('permission:sales.targets.manage');
                Route::delete('/{id}', [SalesTargetController::class, 'destroy'])->name('sales-targets.destroy')->middleware('permission:sales.targets.manage');
                Route::get('/{id}/report', [SalesTargetController::class, 'report'])->name('sales-targets.report')->middleware('permission:sales.targets.manage|sales.targets.settle');
                Route::match(['get', 'post'], '/{id}/generate-report', [SalesTargetController::class, 'generateReport'])->name('sales-targets.generate-report')->middleware('permission:sales.targets.manage|sales.targets.settle');

                // التسوية - يحتاج صلاحية التسوية
                Route::post('/{id}/settle', [SalesTargetController::class, 'settle'])->name('sales-targets.settle')->middleware('permission:sales.targets.settle');
            });

        // ============== مسارات أهداف التحصيلات (Collections Targets) ==============
        // تشارك صلاحيات أهداف المبيعات: sales.targets.manage, sales.targets.settle
        // (`sales.collections_targets.*` في الكتالوج تخصّ شاشات الموظف في
        //  `EmployeeTargetController` لا هذه الشاشة الإدارية)
        Route::prefix('collections-targets')
            ->middleware('plan:'.SalesPlanFeatures::COLLECTIONS_TARGETS)
            ->group(function () {
                // عرض القائمة
                Route::get('/', [CollectionsTargetController::class, 'index'])
                    ->name('collections-targets.index')
                    ->middleware('permission:sales.targets.manage|sales.targets.settle');

                // المسارات الثابتة أولاً قبل {id}
                Route::get('/create', [CollectionsTargetController::class, 'create'])
                    ->name('collections-targets.create')
                    ->middleware('permission:sales.targets.manage');
                Route::post('/store', [CollectionsTargetController::class, 'store'])
                    ->middleware('plan.limit:'.SalesPlanFeatures::LIMIT_COLLECTIONS_TARGETS)
                    ->name('collections-targets.store')
                    ->middleware('permission:sales.targets.manage');

                // المسارات الديناميكية بعد المسارات الثابتة
                Route::get('/{id}', [CollectionsTargetController::class, 'show'])
                    ->name('collections-targets.show')
                    ->middleware('permission:sales.targets.manage|sales.targets.settle');
                Route::get('/{id}/edit', [CollectionsTargetController::class, 'edit'])
                    ->name('collections-targets.edit')
                    ->middleware('permission:sales.targets.manage');
                Route::put('/{id}', [CollectionsTargetController::class, 'update'])
                    ->name('collections-targets.update')
                    ->middleware('permission:sales.targets.manage');
                Route::delete('/{id}', [CollectionsTargetController::class, 'destroy'])
                    ->name('collections-targets.destroy')
                    ->middleware('permission:sales.targets.manage');
                Route::get('/{id}/report', [CollectionsTargetController::class, 'report'])
                    ->name('collections-targets.report')
                    ->middleware('permission:sales.targets.manage|sales.targets.settle');
                Route::match(['get', 'post'], '/{id}/generate-report', [CollectionsTargetController::class, 'generateReport'])
                    ->name('collections-targets.generate-report')
                    ->middleware('permission:sales.targets.manage|sales.targets.settle');

                // التسوية
                Route::post('/{id}/settle', [CollectionsTargetController::class, 'settle'])
                    ->name('collections-targets.settle')
                    ->middleware('permission:sales.targets.settle');
            });

        /*
            العمولات الأسبوعية — الموظف يصرف مباشرة من خزينته.

            **بلا حارس باقة عن قصد**: لا مفتاحَ لها في كتالوج فوترة، لا
            صلاحيةً ولا حدّاً. واختراعُ مفتاحٍ لا تعرفه اللوحة يُعامَل
            «مسموحاً» دائماً — فيبدو حارساً وليس بشيء، وهو أسوأ من غيابه
            لأنه يُوهم من يقرأ الشيفرة أن الشاشة محروسة.
        */
        Route::prefix('weekly-commissions')->group(function () {
            Route::get('/', [WeeklyCommissionController::class, 'index'])->name('sales.weekly-commissions.index')
                ->middleware('permission:sales.commissions.view');
            Route::get('/history', [WeeklyCommissionController::class, 'history'])->name('sales.weekly-commissions.history')
                ->middleware('permission:sales.commissions.view');
            Route::post('/payout-all', [WeeklyCommissionController::class, 'payoutAll'])->name('sales.weekly-commissions.payout-all')
                ->middleware('permission:sales.commissions.payout');
            Route::post('/{id}/payout', [WeeklyCommissionController::class, 'payout'])->name('sales.weekly-commissions.payout')
                ->middleware('permission:sales.commissions.payout');
        });
    },
);
