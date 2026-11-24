<?php

use App\Constants\planFeatures\PurchasesPlanFeatures;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Purchases\Http\Controllers\CityNoticesController;
use Modules\Purchases\Http\Controllers\InvoiceSettingsController;
use Modules\Purchases\Http\Controllers\InvoicesPurchaseController;
use Modules\Purchases\Http\Controllers\PurchaseOrdersController;
use Modules\Purchases\Http\Controllers\PurchasePaymentController;
use Modules\Purchases\Http\Controllers\PurchaseReceivingController;
use Modules\Purchases\Http\Controllers\PurchaseRequestsController;
use Modules\Purchases\Http\Controllers\PurchasesDashboardController;
use Modules\Purchases\Http\Controllers\PurchaseSettingsController;
use Modules\Purchases\Http\Controllers\QuotationsController;
use Modules\Purchases\Http\Controllers\Reports\AdditionalPurchaseReportController;
use Modules\Purchases\Http\Controllers\Reports\EmployeePurchasesReportController;
use Modules\Purchases\Http\Controllers\Reports\PaymentReportController;
use Modules\Purchases\Http\Controllers\Reports\ProductReportController;
use Modules\Purchases\Http\Controllers\Reports\PurchasesReportController;
use Modules\Purchases\Http\Controllers\Reports\ReceivingReportController;
use Modules\Purchases\Http\Controllers\Reports\SupplierReportController;
use Modules\Purchases\Http\Controllers\ReturnsInvoiceController;
use Modules\Purchases\Http\Controllers\SupplierManagementController;
use Modules\Purchases\Http\Controllers\SupplierWalletController;
use Modules\Purchases\Http\Controllers\ViewPurchasePriceController;
use Modules\Purchases\Http\Middleware\EnsurePurchaseSettingEnabled;

Route::middleware(['auth'])->group(function () {

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
                    الداخلية، ونظيرُ ما تفعله `Modules/Stock` على بادئة
                    `stock`.

                    الشرط «واحدةٌ على الأقل» لا «كلّها»: باقةٌ تشمل
                    المورّدين وحدهم يجب أن تفتح شاشتهم، وحارسُ كل مجموعةٍ
                    داخلَها يتكفّل بالباقي. وباقةٌ لا تشمل شيئاً من
                    المشتريات تُردّ من هنا فلا يصل الطلب إلى متحكّم.

                    وكانت الوحدة كلُّها بلا حارس باقةٍ واحد: `permission:`
                    على كل مسار — وهي تسأل «من أنت» لا «ماذا اشترت
                    المنشأة».
                */
                'plan:'.implode('|', PurchasesPlanFeatures::all()),
            ],
        ],
        function () {

            Route::get('/purchases-dashboard', [PurchasesDashboardController::class, 'index'])->name('purchases.dashboard');

            // «ماذا وراء هذا الرقم؟» — قائمةُ الرقم المنقور (JSON)، كما في لوحة المبيعات
            Route::get('/purchases-dashboard/segment', [PurchasesDashboardController::class, 'segment'])
                ->name('purchases.dashboard.segment');

            // طلبات الشراء
            Route::prefix('PurchaseRequests')
                ->middleware('plan:'.PurchasesPlanFeatures::ORDER_REQUESTS)
                ->group(function () {
                    Route::get('/index', [PurchaseRequestsController::class, 'index'])->name('PurchaseRequests.index')
                        ->middleware('permission:purchases.orders.view');
                    Route::get('/create', [PurchaseRequestsController::class, 'create'])->name('PurchaseRequests.create')
                        ->middleware('permission:purchases.orders.create');
                    Route::post('/store', [PurchaseRequestsController::class, 'store'])->name('PurchaseRequests.store')
                        ->middleware('permission:purchases.orders.create');
                    Route::get('edit/{id}', [PurchaseRequestsController::class, 'edit'])->name('PurchaseRequests.edit')
                        ->middleware('permission:purchases.orders.edit');
                    Route::put('update/{id}', [PurchaseRequestsController::class, 'update'])->name('PurchaseRequests.update')
                        ->middleware('permission:purchases.orders.edit');
                    Route::get('/show/{id}', [PurchaseRequestsController::class, 'show'])->name('PurchaseRequests.show')
                        ->middleware('permission:purchases.orders.view');
                    Route::get('/{id}/pdf/{template?}', [PurchaseRequestsController::class, 'generatePdf'])->name('PurchaseRequests.pdf')
                        ->middleware('permission:purchases.orders.export');
                    Route::get('/{id}/print/{template?}', [PurchaseRequestsController::class, 'printView'])->name('PurchaseRequests.print')
                        ->middleware('permission:purchases.orders.export');
                    Route::post('/{id}/approve', [PurchaseRequestsController::class, 'approve'])->name('PurchaseRequests.approve')
                        ->middleware('permission:purchases.orders.approve_reject');
                    Route::post('/{id}/reject', [PurchaseRequestsController::class, 'reject'])->name('PurchaseRequests.reject')
                        ->middleware('permission:purchases.orders.approve_reject');
                    // إلغاء الاعتماد أُزيل عمداً: الاعتماد صار نهائياً لا رجعة فيه
                    Route::post('/{id}/undo', [PurchaseRequestsController::class, 'undoRejection'])->name('PurchaseRequests.undoRejection')
                        ->middleware('permission:purchases.orders.approve_reject');
                    Route::delete('/destroy/{id}', [PurchaseRequestsController::class, 'destroy'])->name('PurchaseRequests.destroy')
                        ->middleware('permission:purchases.orders.delete');
                    Route::post('/bulk-delete', [PurchaseRequestsController::class, 'bulkDelete'])->name('PurchaseRequests.bulkDelete')
                        ->middleware('permission:purchases.orders.delete');
                });

            // طلبات عروض الأسعار
            Route::prefix('Quotations')
                ->middleware('plan:'.PurchasesPlanFeatures::QUOTATIONS)
                ->group(function () {
                    Route::get('/index', [QuotationsController::class, 'index'])->name('Quotations.index')
                        ->middleware('permission:purchases.quotations.view');
                    Route::get('/create', [QuotationsController::class, 'create'])->name('Quotations.create')
                        ->middleware('permission:purchases.quotations.create');
                    Route::post('/store', [QuotationsController::class, 'store'])->name('Quotations.store')
                        ->middleware('permission:purchases.quotations.create')
                        ->middleware('plan.limit:'.PurchasesPlanFeatures::LIMIT_QUOTATIONS);
                    Route::get('/edit/{id}', [QuotationsController::class, 'edit'])->name('Quotations.edit')
                        ->middleware('permission:purchases.quotations.edit');
                    Route::put('/update/{id}', [QuotationsController::class, 'update'])->name('Quotations.update')
                        ->middleware('permission:purchases.quotations.edit');
                    Route::post('Quotations/{id}/approve', [QuotationsController::class, 'approve'])->name('Quotations.approve')
                        ->middleware('permission:purchases.quotations.approve_reject');
                    Route::post('Quotations/{id}/reject', [QuotationsController::class, 'reject'])->name('Quotations.reject')
                        ->middleware('permission:purchases.quotations.approve_reject');
                    Route::delete('/destroy/{id}', [QuotationsController::class, 'destroy'])->name('Quotations.destroy')
                        ->middleware('permission:purchases.quotations.delete');
                    Route::post('/bulk-delete', [QuotationsController::class, 'bulkDelete'])->name('Quotations.bulkDelete')
                        ->middleware('permission:purchases.quotations.delete');
                    Route::get('/show/{id}', [QuotationsController::class, 'show'])->name('Quotations.show')
                        ->middleware('permission:purchases.quotations.view');
                    Route::get('/duplicate/{id}', [QuotationsController::class, 'duplicate'])->name('Quotations.duplicate')
                        ->middleware('permission:purchases.quotations.duplicate');
                    Route::get('Quotations/{id}/pdf/{template?}', [QuotationsController::class, 'generatePdf'])->name('Quotations.pdf')
                        ->middleware('permission:purchases.quotations.export');
                    Route::get('Quotations/{id}/print/{template?}', [QuotationsController::class, 'printView'])->name('Quotations.print')
                        ->middleware('permission:purchases.quotations.export');
                    Route::get('/export', [QuotationsController::class, 'exportCsv'])->name('Quotations.export')
                        ->middleware('permission:purchases.quotations.export');
                });

            // عروض أسعار المشتريات
            Route::prefix('ViewPurchasePrice')
                ->middleware('plan:'.PurchasesPlanFeatures::PRICE_QUOTATIONS)
                ->group(function () {
                    Route::get('/index', [ViewPurchasePriceController::class, 'index'])->name('ViewPurchasePrice.index')
                        ->middleware('permission:purchases.price_quotations.view');
                    Route::get('/create', [ViewPurchasePriceController::class, 'create'])->name('ViewPurchasePrice.create')
                        ->middleware('permission:purchases.price_quotations.create');
                    Route::post('/store', [ViewPurchasePriceController::class, 'store'])->name('ViewPurchasePrice.store')
                        ->middleware('permission:purchases.price_quotations.create');
                    Route::get('/edit/{id}', [ViewPurchasePriceController::class, 'edit'])->name('ViewPurchasePrice.edit')
                        ->middleware('permission:purchases.price_quotations.edit');
                    Route::put('/update/{id}', [ViewPurchasePriceController::class, 'update'])->name('ViewPurchasePrice.update')
                        ->middleware('permission:purchases.price_quotations.edit');
                    Route::delete('/destroy/{id}', [ViewPurchasePriceController::class, 'destroy'])->name('ViewPurchasePrice.destroy')
                        ->middleware('permission:purchases.price_quotations.delete');
                    Route::post('/bulk-delete', [ViewPurchasePriceController::class, 'bulkDelete'])->name('ViewPurchasePrice.bulkDelete')
                        ->middleware('permission:purchases.price_quotations.delete');
                    Route::get('/show/{id}', [ViewPurchasePriceController::class, 'show'])->name('ViewPurchasePrice.show')
                        ->middleware('permission:purchases.price_quotations.view');
                    Route::get('/duplicate/{id}', [ViewPurchasePriceController::class, 'duplicate'])->name('ViewPurchasePrice.duplicate')
                        ->middleware('permission:purchases.price_quotations.duplicate');
                    Route::get('ViewPurchasePrice/{id}/pdf/{template?}', [ViewPurchasePriceController::class, 'exportPDF'])->name('ViewPurchasePrice.pdf')
                        ->middleware('permission:purchases.price_quotations.export');
                    Route::get('ViewPurchasePrice/{id}/print/{template?}', [ViewPurchasePriceController::class, 'printView'])->name('ViewPurchasePrice.print')
                        ->middleware('permission:purchases.price_quotations.export');
                    Route::post('view-purchase-price/{id}/approve', [ViewPurchasePriceController::class, 'approve'])->name('ViewPurchasePrice.approve')
                        ->middleware('permission:purchases.price_quotations.approve_reject');
                    Route::post('view-purchase-price/{id}/reject', [ViewPurchasePriceController::class, 'reject'])->name('ViewPurchasePrice.reject')
                        ->middleware('permission:purchases.price_quotations.approve_reject');
                    // إلغاء الاعتماد أُزيل عمداً: الاعتماد صار نهائياً لا رجعة فيه
                    Route::post('view-purchase-price/{id}/undo', [ViewPurchasePriceController::class, 'undoRejection'])->name('ViewPurchasePrice.undoRejection')
                        ->middleware('permission:purchases.price_quotations.approve_reject');
                    Route::post('view-purchase-price/{id}/add-note', [ViewPurchasePriceController::class, 'addNote'])->name('ViewPurchasePrice.addNote')
                        ->middleware('permission:purchases.price_quotations.edit');
                    Route::get('view-purchase-price/{id}/notes', [ViewPurchasePriceController::class, 'getNotes'])->name('ViewPurchasePrice.getNotes')
                        ->middleware('permission:purchases.price_quotations.view');
                    Route::delete('view-purchase-price/note/{noteId}', [ViewPurchasePriceController::class, 'destroyNote'])->name('ViewPurchasePrice.deleteNote')
                        ->middleware('permission:purchases.price_quotations.delete');
                    Route::post('ViewPurchasePrice/{id}/convert-to-po', [ViewPurchasePriceController::class, 'convertToPurchaseOrder'])->name('ViewPurchasePrice.convertToPurchaseOrder')
                        ->middleware('permission:purchases.price_quotations.convert');
                    Route::put('ViewPurchasePrice/{id}/status', [ViewPurchasePriceController::class, 'updateStatus'])->name('ViewPurchasePrice.updateStatus')
                        ->middleware('permission:purchases.price_quotations.approve_reject');
                });

            // أوامر الشراء — مُنقولة إلى كتالوج purchases.order_requests.*
            // (نفس نقل Orders/Quotations/Invoices سابقاً). الأسماء القديمة
            // (purchase_cycle_orders_manage/approve_reject) لم تعد تُستعمَل
            Route::prefix('PurchaseOrders')
                ->middleware('plan:'.PurchasesPlanFeatures::ORDERS)
                ->group(callback: function () {
                    Route::get('/index', [PurchaseOrdersController::class, 'index'])->name('PurchaseOrders.index')
                        ->middleware('permission:purchases.order_requests.view');
                    Route::get('/create', [PurchaseOrdersController::class, 'create'])->name('PurchaseOrders.create')
                        ->middleware('permission:purchases.order_requests.create');
                    Route::post('/store', [PurchaseOrdersController::class, 'store'])->name('PurchaseOrders.store')
                        ->middleware('permission:purchases.order_requests.create')
                        ->middleware('plan.limit:'.PurchasesPlanFeatures::LIMIT_ORDERS);
                    Route::get('/edit/{id}', [PurchaseOrdersController::class, 'edit'])->name('PurchaseOrders.edit')
                        ->middleware('permission:purchases.order_requests.edit');
                    Route::put('/update/{id}', [PurchaseOrdersController::class, 'update'])->name('PurchaseOrders.update')
                        ->middleware('permission:purchases.order_requests.edit');
                    Route::delete('/destroy/{id}', [PurchaseOrdersController::class, 'destroy'])->name('PurchaseOrders.destroy')
                        ->middleware('permission:purchases.order_requests.delete');
                    Route::put('/{id}/update-status', [PurchaseOrdersController::class, 'updateStatus'])->name('PurchaseOrders.updateStatus')
                        ->middleware('permission:purchases.order_requests.edit');
                    Route::get('/show/{id}', [PurchaseOrdersController::class, 'show'])->name('PurchaseOrders.show')
                        ->middleware('permission:purchases.order_requests.view');
                    Route::get('/{id}/pdf/{template?}', [PurchaseOrdersController::class, 'generatePdf'])->name('PurchaseOrders.pdf')
                        ->middleware('permission:purchases.order_requests.export');
                    Route::get('/{id}/print/{template?}', [PurchaseOrdersController::class, 'printView'])->name('PurchaseOrders.print')
                        ->middleware('permission:purchases.order_requests.export');
                    Route::post('/{id}/approve', [PurchaseOrdersController::class, 'approve'])->name('PurchaseOrders.approve')
                        ->middleware('permission:purchases.order_requests.approve_reject');
                    Route::post('/{id}/reject', [PurchaseOrdersController::class, 'reject'])->name('PurchaseOrders.reject')
                        ->middleware('permission:purchases.order_requests.approve_reject');
                    Route::post('/{id}/cancel', [PurchaseOrdersController::class, 'cancelApproval'])->name('PurchaseOrders.cancelApproval')
                        ->middleware('permission:purchases.order_requests.approve_reject');
                    Route::post('/{id}/undo', [PurchaseOrdersController::class, 'undoRejection'])->name('PurchaseOrders.undoRejection')
                        ->middleware('permission:purchases.order_requests.approve_reject');
                    Route::post('/{id}/convert', [PurchaseOrdersController::class, 'convertToInvoice'])->name('PurchaseOrders.convertToInvoice')
                        ->middleware('permission:purchases.order_requests.view');
                    Route::post('/{id}/notes', [PurchaseOrdersController::class, 'addNote'])->name('PurchaseOrders.addNote')
                        ->middleware('permission:purchases.order_requests.edit');
                    Route::get('/{id}/notes', [PurchaseOrdersController::class, 'getNotes'])->name('PurchaseOrders.getNotes')
                        ->middleware('permission:purchases.order_requests.view');
                    Route::delete('/notes/{noteId}', [PurchaseOrdersController::class, 'deleteNote'])->name('PurchaseOrders.deleteNote')
                        ->middleware('permission:purchases.order_requests.edit');
                    Route::post('/{id}/close', [PurchaseOrdersController::class, 'close'])->name('PurchaseOrders.close')
                        ->middleware('permission:purchases.order_requests.edit');
                    Route::post('/{id}/cancel-order', [PurchaseOrdersController::class, 'cancelOrder'])->name('PurchaseOrders.cancelOrder')
                        ->middleware('permission:purchases.order_requests.edit');
                });

            // استلام المشتريات — المستند الجديد بين أمر الشراء وفاتورة
            // الشراء؛ لا فاتورة إلا من استلامٍ معتمَد (انظر توثيق
            // PurchaseReceivingController). نفس بوّابة خطة أوامر الشراء —
            // لا ميزة اشتراكٍ مستقلّة لها كي لا يُكرَّر خطأ خريطتَي
            // allows()/limit() المنفصلتين على ميزةٍ جديدة.
            Route::prefix('PurchaseReceiving')
                ->middleware('plan:'.PurchasesPlanFeatures::ORDERS)
                ->group(function () {
                    Route::get('/index', [PurchaseReceivingController::class, 'index'])->name('PurchaseReceiving.index')
                        ->middleware('permission:purchases.receiving.view');
                    Route::get('/create', [PurchaseReceivingController::class, 'create'])->name('PurchaseReceiving.create')
                        ->middleware('permission:purchases.receiving.create');
                    Route::post('/store', [PurchaseReceivingController::class, 'store'])->name('PurchaseReceiving.store')
                        ->middleware('permission:purchases.receiving.create');
                    Route::get('/show/{id}', [PurchaseReceivingController::class, 'show'])->name('PurchaseReceiving.show')
                        ->middleware('permission:purchases.receiving.view');
                    Route::get('/edit/{id}', [PurchaseReceivingController::class, 'edit'])->name('PurchaseReceiving.edit')
                        ->middleware('permission:purchases.receiving.edit');
                    Route::put('/update/{id}', [PurchaseReceivingController::class, 'update'])->name('PurchaseReceiving.update')
                        ->middleware('permission:purchases.receiving.edit');
                    Route::delete('/destroy/{id}', [PurchaseReceivingController::class, 'destroy'])->name('PurchaseReceiving.destroy')
                        ->middleware('permission:purchases.receiving.delete');
                    Route::post('/{id}/approve', [PurchaseReceivingController::class, 'approve'])->name('PurchaseReceiving.approve')
                        ->middleware('permission:purchases.receiving.approve');
                    Route::post('/{id}/convert', [PurchaseReceivingController::class, 'convertToInvoice'])->name('PurchaseReceiving.convertToInvoice')
                        ->middleware('permission:purchases.receiving.convert');
                });

            // فواتير الشراء — مُنقولة إلى كتالوج purchases.invoices.* (نفس
            // نقل Orders/Quotations سابقاً). الأسماء القديمة (inv_manage_
            // purchase_invoice_*) لم تعد تُستعمَل هنا؛ الأدوار التي كانت
            // تملكها مُنحت الأسماء الجديدة صراحةً في قاعدة البيانات
            Route::prefix('invoicePurchases')
                ->middleware('plan:'.PurchasesPlanFeatures::INVOICES)
                ->group(function () {
                    Route::get('/index', [InvoicesPurchaseController::class, 'index'])->name('invoicePurchases.index')
                        ->middleware('permission:purchases.invoices.view');
                    Route::get('/create', [InvoicesPurchaseController::class, 'create'])->name('invoicePurchases.create')
                        ->middleware('permission:purchases.invoices.create');
                    Route::post('/store', [InvoicesPurchaseController::class, 'store'])->name('invoicePurchases.store')
                        ->middleware('permission:purchases.invoices.create')
                        ->middleware('plan.limit:'.PurchasesPlanFeatures::LIMIT_INVOICES);
                    Route::get('/edit/{id}', [InvoicesPurchaseController::class, 'edit'])->name('invoicePurchases.edit')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::put('/update/{id}', [InvoicesPurchaseController::class, 'update'])->name('invoicePurchases.update')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::delete('/destroy/{id}', [InvoicesPurchaseController::class, 'destroy'])->name('invoicePurchases.destroy')
                        ->middleware('permission:purchases.invoices.delete');
                    Route::get('/show/{id}', [InvoicesPurchaseController::class, 'show'])->name('invoicePurchases.show')
                        ->middleware('permission:purchases.invoices.view');
                    Route::post('/{id}/mark-as-pending', [InvoicesPurchaseController::class, 'markAsPending'])->name('invoicePurchases.markAsPending')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::post('/{id}/mark-as-approved', [InvoicesPurchaseController::class, 'markAsApproved'])->name('invoicePurchases.markAsApproved')
                        ->middleware('permission:purchases.invoices.approve');
                    Route::post('/{id}/mark-as-paid', [InvoicesPurchaseController::class, 'markAsPaid'])->name('invoicePurchases.markAsPaid')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::post('/{id}/mark-as-cancelled', [InvoicesPurchaseController::class, 'markAsCancelled'])->name('invoicePurchases.markAsCancelled')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::post('/{id}/update-payment-status', [InvoicesPurchaseController::class, 'updatePaymentStatus'])->name('invoicePurchases.updatePaymentStatus')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::post('/{id}/add-note', [InvoicesPurchaseController::class, 'addNote'])->name('invoicePurchases.addNote')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::get('/{id}/get-notes', [InvoicesPurchaseController::class, 'getNotes'])->name('invoicePurchases.getNotes')
                        ->middleware('permission:purchases.invoices.view');
                    Route::delete('/note/{noteId}', [InvoicesPurchaseController::class, 'deleteNote'])->name('invoicePurchases.deleteNote')
                        ->middleware('permission:purchases.invoices.edit');
                    // الاسمُ كان `copy` مجرَّداً بلا بادئة الوحدة — اسمٌ عامّ
                    // في فضاءٍ مشترك يصطدم بأوّل `copy` تُسجّله وحدةٌ أخرى
                    Route::get('/{id}/copy', [InvoicesPurchaseController::class, 'copy'])->name('invoicePurchases.copy')
                        ->middleware('permission:purchases.invoices.duplicate');
                    Route::get('/{id}/assign-cost-center', [InvoicesPurchaseController::class, 'assignCostCenter'])->name('invoicePurchases.assignCostCenter')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::post('/{id}/assign-cost-center', [InvoicesPurchaseController::class, 'updateCostCenter'])->name('invoicePurchases.updateCostCenter')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::get('/{id}/export-pdf', [InvoicesPurchaseController::class, 'exportPDF'])->name('exportPDF')
                        ->middleware('permission:purchases.invoices.export');
                    Route::get('/{id}/export-excel', [InvoicesPurchaseController::class, 'exportExcel'])->name('exportExcel')
                        ->middleware('permission:purchases.invoices.export');
                    Route::get('/{id}/print', [InvoicesPurchaseController::class, 'print'])->name('print')
                        ->middleware('permission:purchases.invoices.export');
                    Route::get('/{id}/notes-pdf', [InvoicesPurchaseController::class, 'generateNotesPdf'])->name('invoicePurchases.notesPdf')
                        ->middleware('permission:purchases.invoices.export');
                    Route::get('/{id}/print-pdf', [InvoicesPurchaseController::class, 'printPdf'])->name('invoicePurchases.printPdf')
                        ->middleware('permission:purchases.invoices.export');
                    Route::get('/{id}/compact-pdf', [InvoicesPurchaseController::class, 'compactPdf'])->name('invoicePurchases.compactPdf')
                        ->middleware('permission:purchases.invoices.export');
                    // كان المساران يكرّران البادئة: `/invoicePurchases/invoicePurchases/…`
                    Route::get('/{id}/pdf/{template?}', [InvoicesPurchaseController::class, 'generatePdf'])->name('invoicePurchases.pdf')
                        ->middleware('permission:purchases.invoices.export');
                    // معاينة بقالب مضبوط في «قوالب المستندات»
                    Route::get('/{id}/print-view/{template?}', [InvoicesPurchaseController::class, 'printView'])->name('invoicePurchases.printView')
                        ->middleware('permission:purchases.invoices.export');
                    Route::post('/{id}/convert-to-credit-memo', [InvoicesPurchaseController::class, 'convertToCreditMemo'])->name('invoicePurchases.convertToCreditMemo')
                        ->middleware('permission:purchases.invoices.edit');
                    Route::post('/{id}/receive', [InvoicesPurchaseController::class, 'markAsApproved'])->name('invoicePurchases.receive')
                        ->middleware('permission:purchases.invoices.approve');
                });

            // مرتجعات المشتريات
            Route::prefix('ReturnsInvoice')
                ->middleware('plan:'.PurchasesPlanFeatures::RETURNS)
                ->group(function () {
                    Route::get('/index', [ReturnsInvoiceController::class, 'index'])->name('ReturnsInvoice.index')
                        ->middleware('permission:purchases.returns.view');
                    Route::get('/create', [ReturnsInvoiceController::class, 'create'])->name('ReturnsInvoice.create')
                        ->middleware('permission:purchases.returns.create');
                    Route::get('/create-from-invoice/{invoiceId}', [ReturnsInvoiceController::class, 'createFromInvoice'])->name('ReturnsInvoice.createFromInvoice')
                        ->middleware('permission:purchases.returns.create');
                    Route::get('/get-invoice-details/{invoiceId}', [ReturnsInvoiceController::class, 'getInvoiceDetails'])->name('ReturnsInvoice.getInvoiceDetails')
                        ->middleware('permission:purchases.returns.view');
                    Route::post('/store', [ReturnsInvoiceController::class, 'store'])->name('ReturnsInvoice.store')
                        ->middleware('permission:purchases.returns.create');
                    Route::get('/edit/{id}', [ReturnsInvoiceController::class, 'edit'])->name('ReturnsInvoice.edit')
                        ->middleware('permission:purchases.returns.edit');
                    Route::put('/update/{id}', [ReturnsInvoiceController::class, 'update'])->name('ReturnsInvoice.update')
                        ->middleware('permission:purchases.returns.edit');
                    Route::delete('/destroy/{id}', [ReturnsInvoiceController::class, 'destroy'])->name('ReturnsInvoice.destroy')
                        ->middleware('permission:purchases.returns.delete');
                    Route::get('/show/{id}', [ReturnsInvoiceController::class, 'show'])->name('ReturnsInvoice.show')
                        ->middleware('permission:purchases.returns.view');
                    Route::get('/{id}/pdf/{template?}', [ReturnsInvoiceController::class, 'pdf'])->name('ReturnsInvoice.pdf')
                        ->middleware('permission:purchases.returns.export');
                    Route::get('/{id}/print/{template?}', [ReturnsInvoiceController::class, 'printView'])->name('ReturnsInvoice.print')
                        ->middleware('permission:purchases.returns.export');
                    // روتات الملاحظات
                    Route::post('/{id}/add-note', [ReturnsInvoiceController::class, 'addNote'])->name('ReturnsInvoice.addNote')
                        ->middleware('permission:purchases.returns.edit');
                    Route::get('/{id}/get-notes', [ReturnsInvoiceController::class, 'getNotes'])->name('ReturnsInvoice.getNotes')
                        ->middleware('permission:purchases.returns.view');
                    Route::delete('/note/{noteId}', [ReturnsInvoiceController::class, 'deleteNote'])->name('ReturnsInvoice.deleteNote')
                        ->middleware('permission:purchases.returns.delete');
                    // روتات النسخ والنسخ المضغوطة
                    Route::get('/{id}/copy', [ReturnsInvoiceController::class, 'copy'])->name('ReturnsInvoice.copy')
                        ->middleware('permission:purchases.returns.duplicate');
                    Route::post('/{id}/receive', [ReturnsInvoiceController::class, 'markAsApproved'])->name('ReturnsInvoice.receive')
                        ->middleware('permission:purchases.returns.approve');
                });

            // الأشعارات المدينة
            // الإشعار المدين — بابٌ يحكمه خيار «تفعيل الإشعار المدين»
            // في خيارات المشتريات. المنع هنا لا في القالب: إخفاء الرابط
            // من القائمة لا يمنع من حفظ الرابط عن فتحه
            Route::middleware(EnsurePurchaseSettingEnabled::class.':enable_debit_notice')
                ->prefix('CityNotices')
                ->middleware('plan:'.PurchasesPlanFeatures::DEBIT_NOTICES)
                ->group(function () {
                    Route::get('/index', [CityNoticesController::class, 'index'])->name('CityNotices.index')
                        ->middleware('permission:purchases.debit_notices.view');
                    Route::get('/create', [CityNoticesController::class, 'create'])->name('CityNotices.create')
                        ->middleware('permission:purchases.debit_notices.create');
                    Route::post('/store', [CityNoticesController::class, 'store'])->name('CityNotices.store')
                        ->middleware('permission:purchases.debit_notices.create');
                    Route::get('/edit/{id}', [CityNoticesController::class, 'edit'])->name('CityNotices.edit')
                        ->middleware('permission:purchases.debit_notices.edit');
                    Route::put('/update/{id}', [CityNoticesController::class, 'update'])->name('CityNotices.update')
                        ->middleware('permission:purchases.debit_notices.edit');
                    Route::delete('/destroy/{id}', [CityNoticesController::class, 'destroy'])->name('CityNotices.destroy')
                        ->middleware('permission:purchases.debit_notices.delete');
                    Route::get('/show/{id}', [CityNoticesController::class, 'show'])->name('CityNotices.show')
                        ->middleware('permission:purchases.debit_notices.view');
                    Route::get('/create-from-invoice/{invoiceId}', [CityNoticesController::class, 'createFromInvoice'])->name('CreditNotes.createFromInvoice')
                        ->middleware('permission:purchases.debit_notices.create');
                    Route::get('/get-invoice-details/{invoiceId}', [CityNoticesController::class, 'getInvoiceDetails'])->name('CityNotices.getInvoiceDetails')
                        ->middleware('permission:purchases.debit_notices.view');
                    // فواتيرُ موردٍ بعينه القابلة للارتجاع — يستدعيها اختيارُ
                    // المورد في شاشة الإنشاء عبر Ajax، بدل سرد فواتير المنشأة
                    // كلِّها في قائمةٍ واحدة ثابتة
                    Route::get('/invoices-for-supplier/{supplierId}', [CityNoticesController::class, 'invoicesForSupplier'])->name('CityNotices.invoicesForSupplier')
                        ->middleware('permission:purchases.debit_notices.create');
                    Route::post('/{id}/receive', [CityNoticesController::class, 'markAsApproved'])->name('CityNotices.receive')
                        ->middleware('permission:purchases.debit_notices.approve');
                    Route::get('/{id}/pdf/{template?}', [CityNoticesController::class, 'pdf'])->name('CityNotices.pdf')
                        ->middleware('permission:purchases.debit_notices.export');
                    Route::get('/{id}/print/{template?}', [CityNoticesController::class, 'printView'])->name('CityNotices.print')
                        ->middleware('permission:purchases.debit_notices.export');
                });

            /* محافظ الموردين — نظير «محافظ العملاء» في وحدة العملاء.
               المحفظة أصلٌ لا التزام: مالٌ لنا لدى المورد. */
            /* أربع شاشات كبقيّة مستندات النظام: قائمة، وعرض، وإضافة، وتعديل.
               وحلّت `store`/`update` محلّ `deposit` و`withdraw` و`pay-invoice`
               الثلاثة — انظر النظير في `Modules/Client/routes/web.php`. */
            Route::prefix('supplier-wallets')
                ->group(function () {
                    Route::get('/', [SupplierWalletController::class, 'index'])
                        ->name('purchases.supplier-wallet.index');
                    Route::get('/{supplier}/movements/create', [SupplierWalletController::class, 'create'])
                        ->name('purchases.supplier-wallet.create');
                    Route::post('/{supplier}/movements', [SupplierWalletController::class, 'store'])
                        ->name('purchases.supplier-wallet.store');
                    Route::get('/{supplier}/movements/{transaction}/edit', [SupplierWalletController::class, 'edit'])
                        ->name('purchases.supplier-wallet.edit');
                    Route::put('/{supplier}/movements/{transaction}', [SupplierWalletController::class, 'update'])
                        ->name('purchases.supplier-wallet.update');
                    Route::get('/{supplier}', [SupplierWalletController::class, 'show'])
                        ->name('purchases.supplier-wallet.show');
                });

            // أدارة الموردين
            Route::prefix('SupplierManagement')
                ->middleware('plan:'.PurchasesPlanFeatures::SUPPLIERS)
                ->group(function () {
                    Route::get('/index', [SupplierManagementController::class, 'index'])->name('SupplierManagement.index')
                        ->middleware('permission:purchases.suppliers.view');
                    Route::get('/create', [SupplierManagementController::class, 'create'])->name('SupplierManagement.create')
                        ->middleware('permission:purchases.suppliers.create');
                    Route::post('/store', [SupplierManagementController::class, 'store'])->name('SupplierManagement.store')
                        ->middleware('permission:purchases.suppliers.create')
                        ->middleware('plan.limit:'.PurchasesPlanFeatures::LIMIT_SUPPLIERS);
                    Route::get('/edit/{id}', [SupplierManagementController::class, 'edit'])->name('SupplierManagement.edit')
                        ->middleware('permission:purchases.suppliers.edit');
                    Route::put('/update/{id}', [SupplierManagementController::class, 'update'])->name('SupplierManagement.update')
                        ->middleware('permission:purchases.suppliers.edit');
                    Route::delete('/destroy/{id}', [SupplierManagementController::class, 'destroy'])->name('SupplierManagement.destroy')
                        ->middleware('permission:purchases.suppliers.delete');
                    Route::get('/show/{id}', [SupplierManagementController::class, 'show'])->name('SupplierManagement.show')
                        ->middleware('permission:purchases.suppliers.view');
                    Route::post('/suppliers/{id}/update-opening-balance', [SupplierManagementController::class, 'updateSupplierOpeningBalance'])->name('SupplierManagement.updateOpeningBalance')
                        ->middleware('permission:purchases.suppliers.manage_opening_balance');
                    Route::get('/suppliers/{id}/statement', [SupplierManagementController::class, 'supplierStatement'])->name('SupplierManagement.statement')
                        ->middleware('permission:purchases.suppliers.view_statement');
                    // مطبوعات كشف الحساب — بقوالب «قوالب المستندات» لا بقالبٍ
                    // مكتوبٍ داخل الشاشة
                    Route::get('/suppliers/{id}/statement/print/{template?}', [SupplierManagementController::class, 'statementPrint'])->name('SupplierManagement.statementPrint')
                        ->middleware('permission:purchases.suppliers.view_statement');
                    Route::get('/suppliers/{id}/statement/pdf/{template?}', [SupplierManagementController::class, 'statementPdf'])->name('SupplierManagement.statementPdf')
                        ->middleware('permission:purchases.suppliers.view_statement');
                    Route::get('/supplier-statement/journal-entry/{journalEntryId}/details', [SupplierManagementController::class, 'showJournalEntryDetails'])->name('SupplierManagement.journalEntryDetails')
                        ->middleware('permission:purchases.suppliers.view_statement');
                    Route::post('/{id}/update-status', [SupplierManagementController::class, 'updateStatus'])->name('SupplierManagement.updateStatus')
                        ->middleware('permission:purchases.suppliers.edit');
                    Route::post('/{id}/assign-employee', [SupplierManagementController::class, 'assignEmployee'])->name('SupplierManagement.assignEmployee')
                        ->middleware('permission:purchases.suppliers.assign_employee');
                    Route::get('/stats/overview', [SupplierManagementController::class, 'getStats'])->name('stats')
                        ->middleware('permission:purchases.suppliers.view');
                });

            // أعدادات فواتير الشراء
            //
            // اللوحة وشاشة الخيارات انتقلتا إلى وحدة الإعدادات مع سائر
            // الإعدادات (`PurchaseSettings.*`). المساران القديمان يبقيان
            // عاملَين فلا ينكسر رابطٌ محفوظ ولا صفحةٌ مفتوحة.
            Route::prefix('invoice_settings')
                ->middleware('plan:'.PurchasesPlanFeatures::INVOICES)
                ->group(function () {
                    Route::get('/index', fn () => redirect()->route('purchases.settings.index'))
                        ->name('purchases.invoice_settings.index');
                    Route::get('/create', [InvoiceSettingsController::class, 'create'])->name('purchases.invoice_settings.create');

                    // مسارات تصاميم الفواتير
                    Route::get('/bill-designs', [InvoiceSettingsController::class, 'bill_designs'])->name('purchases.invoice_settings.bill_designs');
                    Route::get('/templates/create', [InvoiceSettingsController::class, 'create'])->name('purchases.invoice_settings.templates.create'); // Add this if missing
                    Route::post('/templates', [InvoiceSettingsController::class, 'store'])->name('purchases.invoice_settings.templates.store');
                    Route::get('/templates/{template}/edit', [InvoiceSettingsController::class, 'edit'])->name('purchases.invoice_settings.templates.edit');
                    Route::put('/templates/{template}', [InvoiceSettingsController::class, 'update_template'])->name('purchases.invoice_settings.templates.update');
                    Route::delete('/templates/{template}', [InvoiceSettingsController::class, 'destroy'])->name('purchases.invoice_settings.templates.destroy');
                    Route::get('/templates/{template}/reset', [InvoiceSettingsController::class, 'reset'])->name('purchases.invoice_settings.templates.reset');
                    Route::post('/template/preview', [InvoiceSettingsController::class, 'preview'])->name('purchases.invoice_settings.template.preview');
                });

            // أعدادات الموردين
            //
            // الشاشة لم تُبنَ قطّ: قالباها صفر بايت، و`create` مسارٌ بلا
            // دالّة — يُخرج خطأ 500 لمن يفتحه. المسارات تُبقى بأسمائها
            // وتُوجَّه إلى لوحة إعدادات المشتريات حتى تُبنى.
            Route::prefix('Supplier_Settings')
                ->middleware('plan:'.PurchasesPlanFeatures::SUPPLIERS)
                ->group(function () {
                    Route::get('/index', fn () => redirect()->route('purchases.settings.index'))
                        ->name('purchases.supplier_settings.index');
                    Route::get('/create', fn () => redirect()->route('purchases.settings.index'))
                        ->name('purchases.supplier_settings.create');
                });

            // مدفوعات الموردين
            Route::prefix('PaymentSupplier')
                ->middleware('plan:'.PurchasesPlanFeatures::PAYMENTS)
                ->group(function () {
                    Route::get('/indexPurchase', [PurchasePaymentController::class, 'index'])->name('PaymentSupplier.indexPurchase')
                        ->middleware('permission:purchases.payments.view');
                    Route::get('/createPurchase/{id}', [PurchasePaymentController::class, 'create'])->name('PaymentSupplier.createPurchase')
                        ->middleware('permission:purchases.payments.create');
                    Route::post('/storePurchase', [PurchasePaymentController::class, 'store'])->name('PaymentSupplier.storePurchase')
                        ->middleware('permission:purchases.payments.create');
                    Route::get('/showSupplierPayment/{id}', [PurchasePaymentController::class, 'show'])->name('PaymentSupplier.showSupplierPayment')
                        ->middleware('permission:purchases.payments.view');
                    Route::get('/editSupplierPayment/{id}', [PurchasePaymentController::class, 'edit'])->name('PaymentSupplier.editSupplierPayment')
                        ->middleware('permission:purchases.payments.edit');
                    Route::put('/updateSupplierPayment/{id}', [PurchasePaymentController::class, 'update'])->name('PaymentSupplier.updateSupplierPayment')
                        ->middleware('permission:purchases.payments.edit');
                    Route::delete('/destroySupplierPayment/{id}', [PurchasePaymentController::class, 'destroy'])->name('PaymentSupplier.destroySupplierPayment')
                        ->middleware('permission:purchases.payments.delete');
                    Route::post('/cancelSupplierPayment/{id}', [PurchasePaymentController::class, 'cancel'])->name('PaymentSupplier.cancelSupplierPayment')
                        ->middleware('permission:purchases.payments.cancel');
                    Route::get('/get-invoice-details/{invoice_id}', [PurchasePaymentController::class, 'getInvoiceDetails'])->name('PaymentSupplier.getInvoiceDetails')
                        ->middleware('permission:purchases.payments.view');
                    Route::get('/rereceipt/{id}', [PurchasePaymentController::class, 'rereceipt'])->name('PaymentSupplier.rereceipt')
                        ->middleware('permission:purchases.payments.export');
                    // مطبوعات سند الصرف — بقوالب «قوالب المستندات» لا بقالبٍ
                    // مكتوبٍ في الشيفرة
                    Route::get('/{id}/print/{template?}', [PurchasePaymentController::class, 'printView'])->name('PaymentSupplier.print')
                        ->middleware('permission:purchases.payments.export');
                    Route::get('/{id}/pdf/{template?}', [PurchasePaymentController::class, 'pdf'])->name('PaymentSupplier.pdf')
                        ->middleware('permission:purchases.payments.export');

                    // الدفع المجمع للموردين
                    Route::get('/bulk-payment', [PurchasePaymentController::class, 'bulkPaymentCreate'])->name('PaymentSupplier.bulk-payment')
                        ->middleware('permission:purchases.payments.bulk_create');
                    Route::post('/bulk-payment/store', [PurchasePaymentController::class, 'bulkPaymentStore'])->name('PaymentSupplier.bulk-payment.store')
                        ->middleware('permission:purchases.payments.bulk_create');
                    Route::get('/supplier-unpaid-invoices', [PurchasePaymentController::class, 'getSupplierUnpaidInvoices'])->name('PaymentSupplier.supplier-unpaid-invoices')
                        ->middleware('permission:purchases.payments.view');
                });

            // تقارير المشتريات - الصفحة الرئيسية
            Route::get('/reports', [PurchasesReportController::class, 'index'])->name('ReportsPurchases.index');

            // تقارير المشتريات حسب الموظفين
            Route::prefix('reports/employees')
                ->middleware('plan:'.PurchasesPlanFeatures::REPORTS)
                ->group(function () {
                    Route::get('/', [EmployeePurchasesReportController::class, 'index'])->name('ReportsPurchases.purchaseByEmployee');
                    Route::get('/ajax-data', [EmployeePurchasesReportController::class, 'ajaxData'])->name('ReportsPurchases.purchaseByEmployeeAjax');
                    Route::get('/export', [EmployeePurchasesReportController::class, 'exportExcel'])->name('ReportsPurchases.purchaseByEmployeeExport');
                });

            // تقارير الموردين
            Route::prefix('reports/suppliers')
                ->middleware('plan:'.PurchasesPlanFeatures::REPORTS)
                ->group(function () {
                    Route::get('/directory', [SupplierReportController::class, 'directory'])->name('ReportsPurchases.SuppliersDirectory');
                    Route::get('/directory-ajax-data', [SupplierReportController::class, 'directoryAjaxData'])->name('ReportsPurchases.SuppliersDirectoryAjax');
                    Route::get('/by-supplier', [SupplierReportController::class, 'bySupplier'])->name('ReportsPurchases.bySupplier');
                    Route::get('/supplier-purchases/ajax', [SupplierReportController::class, 'getReportData'])
                        ->name('ReportsPurchases.SupplierPurchasesAjax');
                    Route::get('/supplier-purchases/export', [SupplierReportController::class, 'exportExcel'])
                        ->name('SupplierPurchasesExport');
                    Route::get('/purchase-supplier', [SupplierReportController::class, 'index'])->name('ReportsPurchases.purchaseSupplier');
                    Route::get('/balance-suppliers', [SupplierReportController::class, 'supplierBalances'])->name('ReportsPurchases.balanceSuppliers');
                    Route::get('/balance-suppliers/ajax', [SupplierReportController::class, 'supplierBalancesAjax'])->name('ReportsPurchases.balanceSuppliersAjax');
                    Route::get('/unified-supplier-report', [SupplierReportController::class, 'unifiedSupplierReport'])->name('ReportsPurchases.unifiedSupplierReport');
                    // تقرير محافظ الموردين — أرصدةٌ وسجلُّ حركات (الصلاحية في المتحكّم)
                    Route::get('/wallets', [\Modules\Purchases\Http\Controllers\Reports\SupplierWalletReportController::class, 'index'])->name('ReportsPurchases.supplierWallets');
                });

            // تقارير المنتجات
            Route::prefix('reports/products')
                ->middleware('plan:'.PurchasesPlanFeatures::REPORTS)
                ->group(function () {
                    Route::get('/', [ProductReportController::class, 'index'])->name('ReportsPurchases.byProduct');
                    Route::get('/ajax-data', [ProductReportController::class, 'ajaxData'])->name('ReportsPurchases.byProductReportAjax');
                    Route::get('/export', [ProductReportController::class, 'exportExcel'])->name('ReportsPurchases.byProductExport');

                    // المشتريات حسب البند — المشترى مقابل المستلم
                    Route::get('/receiving', [ReceivingReportController::class, 'index'])->name('ReportsPurchases.receiving');
                    Route::get('/receiving-ajax', [ReceivingReportController::class, 'ajaxData'])->name('ReportsPurchases.receivingAjax');
                    Route::get('/receiving-export', [ReceivingReportController::class, 'exportExcel'])->name('ReportsPurchases.receivingExport');
                    Route::get('/purchases', [AdditionalPurchaseReportController::class, 'productPurchases'])->name('ReportsPurchases.prodectPurchases');
                });

            // تقارير المدفوعات
            Route::prefix('reports/payments')
                ->middleware('plan:'.PurchasesPlanFeatures::REPORTS)
                ->group(function () {
                    Route::get('/supplier-payments', [PaymentReportController::class, 'supplierPayments'])->name('ReportsPurchases.supplierPayments');
                    Route::get('/supplier-payments-data', [PaymentReportController::class, 'supplierPaymentsData'])->name('ReportsPurchases.supplierPaymentsData');
                    Route::get('/employee-supplier-payments', [PaymentReportController::class, 'employeeSupplierPayments'])->name('ReportsPurchases.employeeSupplierPayments');
                    Route::get('/employee-supplier-payments-data', [PaymentReportController::class, 'employeeSupplierPaymentsData'])->name('ReportsPurchases.employeeSupplierPaymentsData');
                    Route::get('/payment-purchases', [PaymentReportController::class, 'paymentPurchases'])->name('ReportsPurchases.paymentPurchases');
                    // مدفوعات المشتريات موزَّعةً على الزمن — يومياً وشهرياً
                    Route::get('/purchase-payments', [PaymentReportController::class, 'purchasePayments'])->name('ReportsPurchases.purchasePayments');
                    Route::get('/purchase-payments-data', [PaymentReportController::class, 'purchasePaymentsData'])->name('ReportsPurchases.purchasePaymentsData');
                });

            // تقارير أخرى
            Route::prefix('reports/additional')
                ->middleware('plan:'.PurchasesPlanFeatures::REPORTS)
                ->group(function () {
                    Route::get('/debt-aging', [SupplierReportController::class, 'debtAging'])->name('ReportsPurchases.supplierDebtAging');
                    Route::get('/debt-aging-ajax', [SupplierReportController::class, 'debtAgingAjax'])->name('ReportsPurchases.supplierDebtAgingAjax');
                    // أعمار ديون فواتير الموردين — مستنداً بمستند، لا مجموعاً
                    // على المورّد كالتقرير أعلاه
                    Route::get('/invoice-debt-aging', [SupplierReportController::class, 'invoiceDebtAging'])->name('ReportsPurchases.invoiceDebtAging');
                    Route::get('/invoice-debt-aging-ajax', [SupplierReportController::class, 'invoiceDebtAgingAjax'])->name('ReportsPurchases.invoiceDebtAgingAjax');
                    // بحثُ الموردين لمُنتقي Select2 في التقرير — نظيرُ `ClientReport.searchClients`
                    Route::get('/invoice-debt-aging/search-suppliers', [SupplierReportController::class, 'searchSuppliers'])->name('ReportsPurchases.searchSuppliers')
                        ->middleware('permission:purchases.reports.debt_aging');
                });

            // ================== إعدادات المشتريات ==================
            //
            // خيارات الأقسام كلها — الفاتورة والطلبات والأوامر وعروض الأسعار
            // والمرتجعات والإشعارات والموردين — في شاشة واحدة داخل الوحدة.
            /* لوحة إعدادات المشتريات: أيّ ميزةٍ من الوحدة تكفي لفتحها —
               فهي فهرسُ شاشاتها لا شاشةَ ميزةٍ بعينها. والسور الخارجي
               يكفلها أصلاً، والتصريح هنا ليقرأها من يقرأ المجموعة وحدها */
            Route::prefix('purchase-settings')->group(function () {
                Route::get('/', [PurchaseSettingsController::class, 'index'])->name('purchases.settings.index');
                Route::put('/', [PurchaseSettingsController::class, 'update'])->name('purchases.settings.update');
                Route::post('/reset', [PurchaseSettingsController::class, 'reset'])->name('purchases.settings.reset');
            });

            // المساران القديمان يبقيان عاملَين فلا ينكسر رابط محفوظ. وكانت
            // الشاشة تطلب «رقم الفاتورة التالي» إلزامياً ثم لا تحفظه، والرقم
            // يأتي أصلاً من `DocumentNumberService`
            Route::get('/purchase-invoices/settings', fn () => redirect()->route('purchases.settings.index'))
                ->name('purchase_invoices.settings');
            Route::put('/purchase-invoices/settings', fn () => redirect()->route('purchases.settings.index'))
                ->name('purchase_invoices.settings.update');

        }
    );

    Route::fallback(function () {
        // نفس علّة Stock::routes — view() وحدها تُرجع 200 لصفحة عنوانها 404
        return response()->view('errors.404', [], 404);
    });
});
