<?php

use App\Constants\planFeatures\AccountPlanFeatures;
use App\Constants\planFeatures\OnlineStorePlanFeatures;
use App\Constants\planFeatures\PurchasesPlanFeatures;
use App\Constants\planFeatures\StockPlanFeatures;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\OnlineStore\Http\Controllers\BrandController;
use Modules\OnlineStore\Http\Controllers\CityController;
use Modules\OnlineStore\Http\Controllers\CountryController;
use Modules\OnlineStore\Http\Controllers\EmailLogController;
use Modules\OnlineStore\Http\Controllers\EmailTemplateController;
use Modules\OnlineStore\Http\Controllers\OnlineOrderController;
use Modules\OnlineStore\Http\Controllers\OnlineStoreDashboardController;
use Modules\OnlineStore\Http\Controllers\PaymentMethodController;
use Modules\OnlineStore\Http\Controllers\ProductQuestionController;
use Modules\OnlineStore\Http\Controllers\ProductReviewController;
use Modules\OnlineStore\Http\Controllers\ReportsController;
use Modules\OnlineStore\Http\Controllers\ShippingMethodController;
use Modules\OnlineStore\Http\Controllers\StoreBannerController;
use Modules\OnlineStore\Http\Controllers\StoreCategoryController;
use Modules\OnlineStore\Http\Controllers\StoreClientController;
use Modules\OnlineStore\Http\Controllers\StoreCouponController;
use Modules\OnlineStore\Http\Controllers\StorePageController;
use Modules\OnlineStore\Http\Controllers\StorePermitsController;
use Modules\OnlineStore\Http\Controllers\StoreProductController;
use Modules\OnlineStore\Http\Controllers\StorePurchaseInvoiceController;
use Modules\OnlineStore\Http\Controllers\StoreSettingsController;
use Modules\OnlineStore\Http\Controllers\StoreStorehouseController;
use Modules\OnlineStore\Http\Controllers\StoreSupplierController;
use Modules\OnlineStore\Http\Controllers\StoreTreasuryController;

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch', 'auth'],
    ],

    function () {

        // Online Store Routes
        Route::prefix('online_store')->group(function () {
            // ==========================================
            // لوحة تحكم المتجر الإلكتروني (Dashboard)
            // ==========================================
            Route::get('/dashboard', [OnlineStoreDashboardController::class, 'index'])->name('onlinestore.department.dashboard')
                ->middleware(['permission.role:online_store.dashboard.view', 'plan:'.OnlineStorePlanFeatures::DASHBOARD]);

            // ==========================================
            // تقارير المتجر (Reports)
            // ==========================================
            Route::prefix('reports')->middleware('plan:'.OnlineStorePlanFeatures::REPORTS)->group(function () {
                Route::get('/', [ReportsController::class, 'index'])->name('onlinestore.reports.index')
                    ->middleware('permission.role:online_store.reports.view');
                Route::get('/sales', [ReportsController::class, 'sales'])->name('onlinestore.reports.sales')
                    ->middleware('permission.role:online_store.reports.sales');
                Route::get('/orders', [ReportsController::class, 'orders'])->name('onlinestore.reports.orders')
                    ->middleware('permission.role:online_store.reports.orders');
                Route::get('/products', [ReportsController::class, 'products'])->name('onlinestore.reports.products')
                    ->middleware('permission.role:online_store.reports.products');
                Route::get('/clients', [ReportsController::class, 'clients'])->name('onlinestore.reports.clients')
                    ->middleware('permission.role:online_store.reports.clients');
                Route::get('/coupons', [ReportsController::class, 'coupons'])->name('onlinestore.reports.coupons')
                    ->middleware('permission.role:online_store.reports.coupons');
                Route::get('/shipping', [ReportsController::class, 'shipping'])->name('onlinestore.reports.shipping')
                    ->middleware('permission.role:online_store.reports.shipping');
                Route::get('/reviews', [ReportsController::class, 'reviews'])->name('onlinestore.reports.reviews')
                    ->middleware('permission.role:online_store.reports.reviews');
            });

            // ==========================================
            // عملاء المتجر الإلكتروني (Store Clients)
            // ==========================================
            Route::prefix('clients')->middleware('plan:'.OnlineStorePlanFeatures::CLIENTS)->group(function () {
                Route::get('/', [StoreClientController::class, 'index'])->name('store_clients.index')
                    ->middleware('permission.role:online_store.clients.view');
                Route::get('/create', [StoreClientController::class, 'create'])->name('store_clients.create')
                    ->middleware('permission.role:online_store.clients.create');
                Route::post('/', [StoreClientController::class, 'store'])->name('store_clients.store')
                    ->middleware('permission.role:online_store.clients.create');
                Route::get('/export', [StoreClientController::class, 'export'])->name('store_clients.export')
                    ->middleware('permission.role:online_store.clients.export');
                Route::get('/{storeClient}', [StoreClientController::class, 'show'])->name('store_clients.show')
                    ->middleware('permission.role:online_store.clients.view');
                Route::get('/{storeClient}/edit', [StoreClientController::class, 'edit'])->name('store_clients.edit')
                    ->middleware('permission.role:online_store.clients.edit');
                Route::put('/{storeClient}', [StoreClientController::class, 'update'])->name('store_clients.update')
                    ->middleware('permission.role:online_store.clients.edit');
                Route::delete('/{storeClient}', [StoreClientController::class, 'destroy'])->name('store_clients.destroy')
                    ->middleware('permission.role:online_store.clients.delete');
                Route::post('/{storeClient}/toggle-status', [StoreClientController::class, 'toggleStatus'])->name('store_clients.toggle-status')
                    ->middleware('permission.role:online_store.clients.edit');

                // عناوين العميل (Client Addresses)
                Route::post('/{storeClient}/addresses', [StoreClientController::class, 'storeAddress'])->name('store_clients.addresses.store')
                    ->middleware('permission.role:online_store.clients.edit');
                Route::put('/{storeClient}/addresses/{address}', [StoreClientController::class, 'updateAddress'])->name('store_clients.addresses.update')
                    ->middleware('permission.role:online_store.clients.edit');
                Route::delete('/{storeClient}/addresses/{address}', [StoreClientController::class, 'destroyAddress'])->name('store_clients.addresses.destroy')
                    ->middleware('permission.role:online_store.clients.edit');
                Route::post('/{storeClient}/addresses/{address}/set-default', [StoreClientController::class, 'setDefaultAddress'])->name('store_clients.addresses.set-default')
                    ->middleware('permission.role:online_store.clients.edit');
            });

            // ==========================================
            // الموردين (Suppliers)
            // ==========================================
            // صلاحية الباقة والصلاحية النظامية هنا من [PurchasesPlanFeatures]/`purchases.suppliers.*`
            // لا كتالوجٍ خاصٍّ بالمتجر — الموردون مورد مشتريات واحد لا يتكرّر
            Route::prefix('suppliers')->middleware('plan:'.PurchasesPlanFeatures::SUPPLIERS)->group(function () {
                Route::get('/', [StoreSupplierController::class, 'index'])->name('store_suppliers.index')
                    ->middleware('permission.role:purchases.suppliers.view');
                Route::get('/create', [StoreSupplierController::class, 'create'])->name('store_suppliers.create')
                    ->middleware(['permission.role:purchases.suppliers.create', 'plan.limit:'.PurchasesPlanFeatures::LIMIT_SUPPLIERS]);
                Route::post('/', [StoreSupplierController::class, 'store'])->name('store_suppliers.store')
                    ->middleware(['permission.role:purchases.suppliers.create', 'plan.limit:'.PurchasesPlanFeatures::LIMIT_SUPPLIERS]);
                Route::get('/stats', [StoreSupplierController::class, 'getStats'])->name('store_suppliers.stats')
                    ->middleware('permission.role:purchases.suppliers.view');
                Route::get('/{supplier}', [StoreSupplierController::class, 'show'])->name('store_suppliers.show')
                    ->middleware('permission.role:purchases.suppliers.view');
                Route::get('/{supplier}/edit', [StoreSupplierController::class, 'edit'])->name('store_suppliers.edit')
                    ->middleware('permission.role:purchases.suppliers.edit');
                Route::put('/{supplier}', [StoreSupplierController::class, 'update'])->name('store_suppliers.update')
                    ->middleware('permission.role:purchases.suppliers.edit');
                Route::delete('/{supplier}', [StoreSupplierController::class, 'destroy'])->name('store_suppliers.destroy')
                    ->middleware('permission.role:purchases.suppliers.delete');
                Route::post('/{supplier}/update-status', [StoreSupplierController::class, 'updateStatus'])->name('store_suppliers.update-status')
                    ->middleware('permission.role:purchases.suppliers.edit');
                Route::post('/{supplier}/update-opening-balance', [StoreSupplierController::class, 'updateSupplierOpeningBalance'])->name('store_suppliers.update-opening-balance')
                    ->middleware('permission.role:purchases.suppliers.edit');
            });

            // ==========================================
            // المخازن (Storehouses)
            // ==========================================
            // صلاحية الباقة والصلاحية النظامية من [StockPlanFeatures]/`stock.warehouses.*`
            // لا كتالوجٍ خاصٍّ بالمتجر — نفس مستودعات المخزون بعلامة «تابع للمتجر»
            Route::prefix('storehouses')->middleware('plan:'.StockPlanFeatures::STOREHOUSES)->group(function () {
                Route::get('/', [StoreStorehouseController::class, 'index'])->name('store_storehouses.index')
                    ->middleware('permission.role:stock.warehouses.view');
                Route::get('/create', [StoreStorehouseController::class, 'create'])->name('store_storehouses.create')
                    ->middleware(['permission.role:stock.warehouses.create', 'plan.limit:'.StockPlanFeatures::LIMIT_STOREHOUSES]);
                Route::post('/', [StoreStorehouseController::class, 'store'])->name('store_storehouses.store')
                    ->middleware(['permission.role:stock.warehouses.create', 'plan.limit:'.StockPlanFeatures::LIMIT_STOREHOUSES]);
                Route::get('/stats', [StoreStorehouseController::class, 'getStats'])->name('store_storehouses.stats')
                    ->middleware('permission.role:stock.warehouses.view');
                Route::get('/{storehouse}', [StoreStorehouseController::class, 'show'])->name('store_storehouses.show')
                    ->middleware('permission.role:stock.warehouses.show');
                Route::get('/{storehouse}/edit', [StoreStorehouseController::class, 'edit'])->name('store_storehouses.edit')
                    ->middleware('permission.role:stock.warehouses.edit');
                Route::put('/{storehouse}', [StoreStorehouseController::class, 'update'])->name('store_storehouses.update')
                    ->middleware('permission.role:stock.warehouses.edit');
                Route::delete('/{storehouse}', [StoreStorehouseController::class, 'destroy'])->name('store_storehouses.destroy')
                    ->middleware('permission.role:stock.warehouses.delete');
                Route::post('/{storehouse}/update-status', [StoreStorehouseController::class, 'updateStatus'])->name('store_storehouses.update-status')
                    ->middleware('permission.role:stock.warehouses.toggle_status');

                // تقارير المخزون
                Route::get('/{storehouse}/summary-inventory-operations', [StoreStorehouseController::class, 'summaryInventoryOperations'])->name('store_storehouses.summary-inventory-operations')
                    ->middleware('permission.role:stock.warehouses.show');
                Route::get('/{storehouse}/search-summary-inventory-operations', [StoreStorehouseController::class, 'searchSummaryInventoryOperations'])->name('store_storehouses.search-summary-inventory-operations')
                    ->middleware('permission.role:stock.warehouses.show');
                Route::get('/{storehouse}/export-summary-pdf', [StoreStorehouseController::class, 'exportSummaryPdf'])->name('store_storehouses.export-summary-pdf')
                    ->middleware('permission.role:stock.warehouses.show');

                Route::get('/{storehouse}/inventory-value', [StoreStorehouseController::class, 'inventoryValue'])->name('store_storehouses.inventory-value')
                    ->middleware('permission.role:stock.warehouses.show');
                Route::get('/{storehouse}/search-inventory-value', [StoreStorehouseController::class, 'searchInventoryValue'])->name('store_storehouses.search-inventory-value')
                    ->middleware('permission.role:stock.warehouses.show');
                Route::get('/{storehouse}/export-inventory-value-pdf', [StoreStorehouseController::class, 'exportInventoryValuePdf'])->name('store_storehouses.export-inventory-value-pdf')
                    ->middleware('permission.role:stock.warehouses.show');

                Route::get('/{storehouse}/inventory-sheet', [StoreStorehouseController::class, 'inventorySheet'])->name('store_storehouses.inventory-sheet')
                    ->middleware('permission.role:stock.warehouses.show');
            });

            // ==========================================
            // فواتير الشراء (Purchase Invoices)
            // ==========================================
            // صلاحية الباقة والصلاحية النظامية من [PurchasesPlanFeatures]/`purchases.invoices.*`
            Route::prefix('purchase-invoices')->middleware('plan:'.PurchasesPlanFeatures::INVOICES)->group(function () {
                Route::get('/', [StorePurchaseInvoiceController::class, 'index'])->name('store_purchase_invoices.index')
                    ->middleware('permission.role:purchases.invoices.view');
                Route::get('/create', [StorePurchaseInvoiceController::class, 'create'])->name('store_purchase_invoices.create')
                    ->middleware(['permission.role:purchases.invoices.create', 'plan.limit:'.PurchasesPlanFeatures::LIMIT_INVOICES]);
                Route::post('/', [StorePurchaseInvoiceController::class, 'store'])->name('store_purchase_invoices.store')
                    ->middleware(['permission.role:purchases.invoices.create', 'plan.limit:'.PurchasesPlanFeatures::LIMIT_INVOICES]);
                Route::get('/{purchaseInvoice}', [StorePurchaseInvoiceController::class, 'show'])->name('store_purchase_invoices.show')
                    ->middleware('permission.role:purchases.invoices.view');
                Route::get('/{purchaseInvoice}/edit', [StorePurchaseInvoiceController::class, 'edit'])->name('store_purchase_invoices.edit')
                    ->middleware('permission.role:purchases.invoices.edit');
                Route::put('/{purchaseInvoice}', [StorePurchaseInvoiceController::class, 'update'])->name('store_purchase_invoices.update')
                    ->middleware('permission.role:purchases.invoices.edit');
                Route::delete('/{purchaseInvoice}', [StorePurchaseInvoiceController::class, 'destroy'])->name('store_purchase_invoices.destroy')
                    ->middleware('permission.role:purchases.invoices.delete');

                // سداد فاتورة آجلة/جزئية من صفحتها — سند دفع للمورد وقيده، من خزائن المتجر
                Route::post('/{purchaseInvoice}/pay', [StorePurchaseInvoiceController::class, 'pay'])->name('store_purchase_invoices.pay')
                    ->middleware('permission.role:purchases.payments.create');

                // طباعة وتصدير — قالبٌ مضبوط في «قوالب المستندات» (رقم) وإلا القالب المدمج (thermal/compact/default)
                Route::get('/{purchaseInvoice}/print/{template?}', [StorePurchaseInvoiceController::class, 'print'])->name('store_purchase_invoices.print')
                    ->middleware('permission.role:purchases.invoices.view');
                Route::get('/{purchaseInvoice}/thermal', [StorePurchaseInvoiceController::class, 'thermal'])->name('store_purchase_invoices.thermal')
                    ->middleware('permission.role:purchases.invoices.view');
                Route::get('/{purchaseInvoice}/compact', [StorePurchaseInvoiceController::class, 'compact'])->name('store_purchase_invoices.compact')
                    ->middleware('permission.role:purchases.invoices.view');
                Route::get('/{purchaseInvoice}/pdf/{template?}', [StorePurchaseInvoiceController::class, 'exportPDF'])->name('store_purchase_invoices.pdf')
                    ->middleware('permission.role:purchases.invoices.view');
                Route::get('/{purchaseInvoice}/notes-pdf', [StorePurchaseInvoiceController::class, 'notesPdf'])->name('store_purchase_invoices.notes_pdf')
                    ->middleware('permission.role:purchases.invoices.view');
            });

            // ==========================================
            // الأذون المخزنية (Warehouse Permits)
            // ==========================================
            // صلاحية الباقة من [StockPlanFeatures]/`stock.permits.*` — نفس أذونات
            // المخزون، مقصورة هنا على مخازن المتجر (`is_store_storehouse`)
            Route::prefix('permits')->middleware('plan:'.StockPlanFeatures::WAREHOUSE_PERMITS)->group(function () {
                Route::get('/', [StorePermitsController::class, 'index'])->name('online_store.permits.index')
                    ->middleware('permission.role:stock.permits.view');
                Route::get('/create', [StorePermitsController::class, 'create'])->name('online_store.permits.create')
                    ->middleware(['permission.role:stock.permits.create_addition', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::post('/', [StorePermitsController::class, 'store'])->name('online_store.permits.store')
                    ->middleware(['permission.role:stock.permits.create_addition|stock.permits.create_disbursement|stock.permits.create_transfer|stock.permits.create_opening', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::get('/export', [StorePermitsController::class, 'export'])->name('online_store.permits.export')
                    ->middleware('permission.role:stock.permits.export');
                // أصناف مستودعٍ بعينه — تغذّي قائمة بنود إذن التحويل (تُعرَّف قبل
                // مسار `/{id}` كي لا يبتلعها كمعرِّف إذن)
                Route::get('/products-by-store/{storeId}', [StorePermitsController::class, 'productsByStore'])
                    ->name('online_store.permits.products_by_store')
                    ->middleware('permission.role:stock.permits.view');
                // رصيد صنفٍ في مخزن متجر — يغذّي «المتاح في المستودع» في نموذج الإذن
                // (كان النموذج يسأل مسار المخزون العام `/get-product-stock` المحروس بنطاقٍ آخر)
                Route::get('/product-stock/{storeId}/{productId}', [StorePermitsController::class, 'getProductStock'])
                    ->name('online_store.permits.product_stock')
                    ->middleware('permission.role:stock.permits.view');
                Route::get('/{id}', [StorePermitsController::class, 'show'])->name('online_store.permits.show')
                    ->middleware('permission.role:stock.permits.view');
                Route::get('/{id}/edit', [StorePermitsController::class, 'edit'])->name('online_store.permits.edit')
                    ->middleware('permission.role:stock.permits.edit');
                Route::post('/{id}/update', [StorePermitsController::class, 'update'])->name('online_store.permits.update')
                    ->middleware('permission.role:stock.permits.edit');
                Route::delete('/{id}', [StorePermitsController::class, 'delete'])->name('online_store.permits.delete')
                    ->middleware('permission.role:stock.permits.delete');
                Route::patch('/{id}/approve', [StorePermitsController::class, 'approve'])->name('online_store.permits.approve')
                    ->middleware('permission.role:stock.permits.approve');
                Route::patch('/{id}/reject', [StorePermitsController::class, 'reject'])->name('online_store.permits.reject')
                    ->middleware('permission.role:stock.permits.reject');
                // إلغاء اعتماد إذنٍ يدويّ معتمَد: يعيد الكميات ويعكس القيد — بصلاحيته الخاصة
                Route::post('/{id}/cancel-approval', [StorePermitsController::class, 'cancelApproval'])->name('online_store.permits.cancel_approval')
                    ->middleware('permission.role:stock.permits.cancel_approval');
                Route::get('/{id}/print/{template?}', [StorePermitsController::class, 'printView'])->name('online_store.permits.printView')
                    ->middleware('permission.role:stock.permits.print');
                Route::get('/{id}/pdf/{template?}', [StorePermitsController::class, 'pdf'])->name('online_store.permits.pdf')
                    ->middleware('permission.role:stock.permits.print');

                /*
                    الصرف/التحويل/الرصيد الافتتاحي: شاشات إنشاء وتعديل منفصلة
                    (نوعٌ مختلف لكل شاشة)، لكن حفظاً وتحديثاً واحدَين يفرّقان
                    بين الأنواع عبر `permission_type` — نفس بنية
                    Stock\StorePermitsManagementController (`store`/`update`
                    وحدهما، لا نسخة لكل نوع). كانت هذه المسارات تُسمّي أفعالاً
                    (`manual_disbursement_store`, `manual_disbursement_update`, …)
                    لا وجود لها في المتحكّم فيسقط أيّ حفظٍ أو تعديلٍ لهذه
                    الأنواع الثلاثة بخطأ "Method does not exist" — الأسماء
                    باقية للتوافق مع أي رابطٍ قديم، لكنها تُوجَّه الآن للفعل
                    الموحَّد الفعلي.
                */
                // الصرف اليدوي
                Route::get('/manual-disbursement/create', [StorePermitsController::class, 'manual_disbursement'])->name('online_store.permits.manual_disbursement')
                    ->middleware(['permission.role:stock.permits.create_disbursement', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::post('/manual-disbursement', [StorePermitsController::class, 'store'])->name('online_store.permits.manual_disbursement_store')
                    ->middleware(['permission.role:stock.permits.create_disbursement', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::get('/manual-disbursement/{id}/edit', [StorePermitsController::class, 'edit'])->name('online_store.permits.manual_disbursement_edit')
                    ->middleware('permission.role:stock.permits.edit');
                Route::post('/manual-disbursement/{id}/update', [StorePermitsController::class, 'update'])->name('online_store.permits.manual_disbursement_update')
                    ->middleware('permission.role:stock.permits.edit');

                // التحويل اليدوي
                Route::get('/manual-conversion/create', [StorePermitsController::class, 'manual_conversion'])->name('online_store.permits.manual_conversion')
                    ->middleware(['permission.role:stock.permits.create_transfer', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::post('/manual-conversion', [StorePermitsController::class, 'store'])->name('online_store.permits.manual_conversion_store')
                    ->middleware(['permission.role:stock.permits.create_transfer', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::get('/manual-conversion/{id}/edit', [StorePermitsController::class, 'edit'])->name('online_store.permits.manual_conversion_edit')
                    ->middleware('permission.role:stock.permits.edit');
                Route::post('/manual-conversion/{id}/update', [StorePermitsController::class, 'update'])->name('online_store.permits.manual_conversion_update')
                    ->middleware('permission.role:stock.permits.edit');

                // الأرصدة الافتتاحية
                Route::get('/opening-balance/create', [StorePermitsController::class, 'opening_balance'])->name('online_store.permits.opening_balance')
                    ->middleware(['permission.role:stock.permits.create_opening', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::post('/opening-balance', [StorePermitsController::class, 'store'])->name('online_store.permits.opening_balance_store')
                    ->middleware(['permission.role:stock.permits.create_opening', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                Route::get('/opening-balance/{id}/edit', [StorePermitsController::class, 'edit'])->name('online_store.permits.opening_balance_edit')
                    ->middleware('permission.role:stock.permits.edit');
                Route::post('/opening-balance/{id}/update', [StorePermitsController::class, 'update'])->name('online_store.permits.opening_balance_update')
                    ->middleware('permission.role:stock.permits.edit');
            });

            // ==========================================
            // الخزائن والحسابات البنكية (Treasury & Bank Accounts)
            // ==========================================
            // صلاحية الباقة من [AccountPlanFeatures]/`account.treasury*.*` — نفس
            // خزائن الحسابات، مقصورة هنا على خزائن المتجر (`is_store_treasury`)
            Route::prefix('treasury')->middleware('plan:'.AccountPlanFeatures::TREASURY)->group(function () {
                Route::get('/', [StoreTreasuryController::class, 'index'])->name('store_treasury.index')
                    ->middleware('permission.role:account.treasury.view');
                Route::get('/create', [StoreTreasuryController::class, 'create'])->name('store_treasury.create')
                    ->middleware(['permission.role:account.treasury.create', 'plan.limit:'.AccountPlanFeatures::LIMIT_TREASURIES]);
                Route::post('/', [StoreTreasuryController::class, 'store'])->name('store_treasury.store')
                    ->middleware(['permission.role:account.treasury.create', 'plan.limit:'.AccountPlanFeatures::LIMIT_TREASURIES]);
                Route::get('/create-bank', [StoreTreasuryController::class, 'createBank'])->name('store_treasury.create_bank')
                    ->middleware(['permission.role:account.treasury.bank_create', 'plan.limit:'.AccountPlanFeatures::LIMIT_TREASURIES]);
                Route::post('/store-bank', [StoreTreasuryController::class, 'storeBank'])->name('store_treasury.store_bank')
                    ->middleware(['permission.role:account.treasury.bank_create', 'plan.limit:'.AccountPlanFeatures::LIMIT_TREASURIES]);
                Route::get('/transfer-create', [StoreTreasuryController::class, 'transferCreate'])->name('store_treasury.transfer_create')
                    ->middleware('permission.role:account.treasury_transfers.create');
                Route::post('/transfer-store', [StoreTreasuryController::class, 'transferStore'])->name('store_treasury.transfer_store')
                    ->middleware('permission.role:account.treasury_transfers.create');
                Route::get('/{id}', [StoreTreasuryController::class, 'show'])->name('store_treasury.show')
                    ->middleware('permission.role:account.treasury.view');
                Route::get('/{id}/edit', [StoreTreasuryController::class, 'edit'])->name('store_treasury.edit')
                    ->middleware('permission.role:account.treasury.edit');
                Route::post('/{id}', [StoreTreasuryController::class, 'update'])->name('store_treasury.update')
                    ->middleware('permission.role:account.treasury.edit');
                Route::get('/{id}/edit-bank', [StoreTreasuryController::class, 'editBank'])->name('store_treasury.edit_bank')
                    ->middleware('permission.role:account.treasury.edit');
                Route::post('/{id}/bank', [StoreTreasuryController::class, 'updateBank'])->name('store_treasury.update_bank')
                    ->middleware('permission.role:account.treasury.edit');
                Route::delete('/{id}', [StoreTreasuryController::class, 'destroy'])->name('store_treasury.destroy')
                    ->middleware('permission.role:account.treasury.delete');
                Route::get('/{id}/update-status', [StoreTreasuryController::class, 'updateStatus'])->name('store_treasury.update_status')
                    ->middleware('permission.role:account.treasury.toggle_status');
                Route::get('/{id}/transfers-data', [StoreTreasuryController::class, 'getTransfersData'])->name('store_treasury.transfers_data')
                    ->middleware('permission.role:account.treasury_transfers.view');
            });

            // ==========================================
            // الطلبات الإلكترونية (Online Orders)
            // ==========================================
            Route::prefix('orders')->middleware('plan:'.OnlineStorePlanFeatures::ORDERS)->group(function () {
                Route::get('/', [OnlineOrderController::class, 'index'])->name('online_orders.index')
                    ->middleware('permission.role:online_store.orders.view');
                Route::get('/statistics', [OnlineOrderController::class, 'statistics'])->name('online_orders.statistics')
                    ->middleware('permission.role:online_store.orders.statistics');
                Route::get('/export', [OnlineOrderController::class, 'export'])->name('online_orders.export')
                    ->middleware('permission.role:online_store.orders.export');
                Route::get('/{order}', [OnlineOrderController::class, 'show'])->name('online_orders.show')
                    ->middleware('permission.role:online_store.orders.show');
                Route::post('/{order}/update-status', [OnlineOrderController::class, 'updateStatus'])->name('online_orders.update-status')
                    ->middleware('permission.role:online_store.orders.update_status');
                Route::post('/{order}/update-shipping', [OnlineOrderController::class, 'updateShipping'])->name('online_orders.update-shipping')
                    ->middleware('permission.role:online_store.orders.update_shipping');
                Route::post('/{order}/add-note', [OnlineOrderController::class, 'addNote'])->name('online_orders.add-note')
                    ->middleware('permission.role:online_store.orders.add_note');
                Route::post('/{order}/cancel', [OnlineOrderController::class, 'cancel'])->name('online_orders.cancel')
                    ->middleware('permission.role:online_store.orders.cancel');
                Route::post('/{order}/refund', [OnlineOrderController::class, 'refund'])->name('online_orders.refund')
                    ->middleware('permission.role:online_store.orders.refund');
                // تسجيل تحصيل طلبٍ لم يُدفع (الدفع عند الاستلام / التحويل البنكي)
                Route::post('/{order}/collect-payment', [OnlineOrderController::class, 'collectPayment'])->name('online_orders.collect-payment')
                    ->middleware('permission.role:online_store.orders.update_status');
                Route::get('/{order}/print/{template?}', [OnlineOrderController::class, 'print'])->name('online_orders.print')
                    ->middleware('permission.role:online_store.orders.print');
                Route::get('/{order}/print-shipping-label', [OnlineOrderController::class, 'printShippingLabel'])->name('online_orders.print-shipping-label')
                    ->middleware('permission.role:online_store.orders.print');
                Route::get('/{order}/print-invoice', [OnlineOrderController::class, 'printInvoice'])->name('online_orders.print-invoice')
                    ->middleware('permission.role:online_store.orders.print');
            });

            // ==========================================
            // العلامات التجارية (Brands)
            // ==========================================
            // حدّ الإنشاء من [StockPlanFeatures::LIMIT_BRANDS] لا حدٍّ خاصٍّ بالمتجر —
            // `brands` جدولٌ واحد يتقاسمه المخزون والمتجر (انظر تعليق OnlineStorePlanFeatures)
            Route::prefix('brands')->middleware('plan:'.OnlineStorePlanFeatures::CATALOG)->group(function () {
                Route::get('/', [BrandController::class, 'index'])->name('brands.index')
                    ->middleware('permission.role:online_store.brands.view');
                Route::get('/create', [BrandController::class, 'create'])->name('brands.create')
                    ->middleware(['permission.role:online_store.brands.create', 'plan.limit:'.StockPlanFeatures::LIMIT_BRANDS]);
                Route::post('/', [BrandController::class, 'store'])->name('brands.store')
                    ->middleware(['permission.role:online_store.brands.create', 'plan.limit:'.StockPlanFeatures::LIMIT_BRANDS]);
                Route::get('/{brand}', [BrandController::class, 'show'])->name('brands.show')
                    ->middleware('permission.role:online_store.brands.view');
                Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit')
                    ->middleware('permission.role:online_store.brands.edit');
                Route::put('/{brand}', [BrandController::class, 'update'])->name('brands.update')
                    ->middleware('permission.role:online_store.brands.edit');
                Route::delete('/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy')
                    ->middleware('permission.role:online_store.brands.delete');
                Route::post('/{brand}/toggle-status', [BrandController::class, 'toggleStatus'])->name('brands.toggle-status')
                    ->middleware('permission.role:online_store.brands.toggle_status');
                Route::post('/{brand}/toggle-featured', [BrandController::class, 'toggleFeatured'])->name('brands.toggle-featured')
                    ->middleware('permission.role:online_store.brands.toggle_status');
            });

            // ==========================================
            // منتجات المتجر الإلكتروني (Store Products)
            // ==========================================
            // حدّ الإنشاء من [StockPlanFeatures::LIMIT_PRODUCTS] لا حدٍّ خاصٍّ بالمتجر —
            // نفس جدول منتجات المخزون
            Route::prefix('products')->middleware('plan:'.OnlineStorePlanFeatures::PRODUCTS)->group(function () {
                Route::get('/', [StoreProductController::class, 'index'])->name('store_products.index')
                    ->middleware('permission.role:online_store.products.view');
                Route::get('/create', [StoreProductController::class, 'create'])->name('store_products.create')
                    ->middleware(['permission.role:online_store.products.create', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                Route::post('/', [StoreProductController::class, 'store'])->name('store_products.store')
                    ->middleware(['permission.role:online_store.products.create', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                Route::get('/export', [StoreProductController::class, 'export'])->name('store_products.export')
                    ->middleware('permission.role:online_store.products.export');

                Route::get('/{product}', [StoreProductController::class, 'show'])->name('store_products.show')
                    ->middleware('permission.role:online_store.products.show');
                Route::get('/{product}/edit', [StoreProductController::class, 'edit'])->name('store_products.edit')
                    ->middleware('permission.role:online_store.products.edit');
                Route::put('/{product}', [StoreProductController::class, 'update'])->name('store_products.update')
                    ->middleware('permission.role:online_store.products.edit');
                Route::delete('/{product}', [StoreProductController::class, 'destroy'])->name('store_products.destroy')
                    ->middleware('permission.role:online_store.products.delete');
                Route::post('/{product}/toggle-status', [StoreProductController::class, 'toggleStatus'])->name('store_products.toggle-status')
                    ->middleware('permission.role:online_store.products.toggle_status');
                Route::post('/{product}/toggle-online', [StoreProductController::class, 'toggleOnline'])->name('store_products.toggle-online')
                    ->middleware('permission.role:online_store.products.toggle_status');
                Route::post('/{product}/toggle-featured', [StoreProductController::class, 'toggleFeatured'])->name('store_products.toggle-featured')
                    ->middleware('permission.role:online_store.products.toggle_status');
                Route::post('/{product}/remove-image', [StoreProductController::class, 'removeImage'])->name('store_products.remove-image')
                    ->middleware('permission.role:online_store.products.edit');

                // حركة المخزون والجدول الزمني وسجل النشاطات
                Route::get('/{product}/stock-movements', [StoreProductController::class, 'getStockMovements'])->name('store_products.stock-movements')
                    ->middleware('permission.role:online_store.products.show');
                Route::get('/{product}/timeline', [StoreProductController::class, 'getTimeline'])->name('store_products.timeline')
                    ->middleware('permission.role:online_store.products.show');
                Route::get('/{product}/activity-logs', [StoreProductController::class, 'getActivityLogs'])->name('store_products.activity-logs')
                    ->middleware('permission.role:online_store.products.show');

                // عمليات المخزون اليدوية
                Route::get('/{product}/manual-stock-adjust', [StoreProductController::class, 'manualStockAdjust'])->name('store_products.manual_stock_adjust')
                    ->middleware('permission.role:online_store.products.manual_stock_adjust');
                Route::post('/{product}/manual-stock-adjust', [StoreProductController::class, 'addManualStockAdjust'])->name('store_products.add_manual_stock_adjust')
                    ->middleware('permission.role:online_store.products.manual_stock_adjust');
                // رصيد الصنف في مستودعٍ بعينه — AJAX لشاشة العملية اليدوية
                Route::get('/{product}/stock-in-store/{storeId}', [StoreProductController::class, 'productStockInStore'])->name('store_products.stock_in_store')
                    ->middleware('permission.role:online_store.products.manual_stock_adjust');

                // طباعة الباركود
                Route::get('/{product}/print-barcode', [StoreProductController::class, 'printBarcode'])->name('store_products.print_barcode')
                    ->middleware('permission.role:online_store.products.show');
            });

            // ==========================================
            // أصناف المنتجات (Store Categories)
            // ==========================================
            // حدّ الإنشاء من [StockPlanFeatures::LIMIT_CATEGORIES] لا حدٍّ خاصٍّ بالمتجر —
            // `StoreCategoryController` يدير `App\Models\Category` نفسه المشترك مع المخزون
            Route::prefix('categories')->middleware('plan:'.OnlineStorePlanFeatures::CATALOG)->group(function () {
                Route::get('/', [StoreCategoryController::class, 'index'])->name('store_categories.index')
                    ->middleware('permission.role:online_store.categories.view');
                Route::get('/create', [StoreCategoryController::class, 'create'])->name('store_categories.create')
                    ->middleware(['permission.role:online_store.categories.create', 'plan.limit:'.StockPlanFeatures::LIMIT_CATEGORIES]);
                Route::post('/', [StoreCategoryController::class, 'store'])->name('store_categories.store')
                    ->middleware(['permission.role:online_store.categories.create', 'plan.limit:'.StockPlanFeatures::LIMIT_CATEGORIES]);
                Route::get('/{category}', [StoreCategoryController::class, 'show'])->name('store_categories.show')
                    ->middleware('permission.role:online_store.categories.view');
                Route::get('/{category}/edit', [StoreCategoryController::class, 'edit'])->name('store_categories.edit')
                    ->middleware('permission.role:online_store.categories.edit');
                Route::put('/{category}', [StoreCategoryController::class, 'update'])->name('store_categories.update')
                    ->middleware('permission.role:online_store.categories.edit');
                Route::delete('/{category}', [StoreCategoryController::class, 'destroy'])->name('store_categories.destroy')
                    ->middleware('permission.role:online_store.categories.delete');
                Route::post('/{category}/toggle-status', [StoreCategoryController::class, 'toggleStatus'])->name('store_categories.toggle-status')
                    ->middleware('permission.role:online_store.categories.edit');
            });

            // ==========================================
            // كوبونات الخصم (Store Coupons)
            // ==========================================
            Route::prefix('coupons')->middleware('plan:'.OnlineStorePlanFeatures::COUPONS)->group(function () {
                Route::get('/', [StoreCouponController::class, 'index'])->name('store_coupons.index')
                    ->middleware('permission.role:online_store.coupons.view');
                Route::get('/create', [StoreCouponController::class, 'create'])->name('store_coupons.create')
                    ->middleware(['permission.role:online_store.coupons.create', 'plan.limit:'.OnlineStorePlanFeatures::LIMIT_COUPONS]);
                Route::post('/', [StoreCouponController::class, 'store'])->name('store_coupons.store')
                    ->middleware(['permission.role:online_store.coupons.create', 'plan.limit:'.OnlineStorePlanFeatures::LIMIT_COUPONS]);
                Route::get('/generate-code', [StoreCouponController::class, 'generateCode'])->name('store_coupons.generate-code')
                    ->middleware('permission.role:online_store.coupons.create');
                Route::get('/{storeCoupon}', [StoreCouponController::class, 'show'])->name('store_coupons.show')
                    ->middleware('permission.role:online_store.coupons.view');
                Route::get('/{storeCoupon}/edit', [StoreCouponController::class, 'edit'])->name('store_coupons.edit')
                    ->middleware('permission.role:online_store.coupons.edit');
                Route::put('/{storeCoupon}', [StoreCouponController::class, 'update'])->name('store_coupons.update')
                    ->middleware('permission.role:online_store.coupons.edit');
                Route::delete('/{storeCoupon}', [StoreCouponController::class, 'destroy'])->name('store_coupons.destroy')
                    ->middleware('permission.role:online_store.coupons.delete');
                Route::post('/{storeCoupon}/toggle-status', [StoreCouponController::class, 'toggleStatus'])->name('store_coupons.toggle-status')
                    ->middleware('permission.role:online_store.coupons.toggle_status');
                Route::get('/{storeCoupon}/usages', [StoreCouponController::class, 'usages'])->name('store_coupons.usages')
                    ->middleware('permission.role:online_store.coupons.view');
                Route::post('/validate', [StoreCouponController::class, 'validateCoupon'])->name('store_coupons.validate')
                    ->middleware('permission.role:online_store.coupons.view');
            });

            // ==========================================
            // طرق الشحن (Shipping Methods)
            // ==========================================
            Route::prefix('shipping-methods')->middleware('plan:'.OnlineStorePlanFeatures::SHIPPING)->group(function () {
                Route::get('/', [ShippingMethodController::class, 'index'])->name('shipping_methods.index')
                    ->middleware('permission.role:online_store.shipping_methods.view');
                Route::get('/create', [ShippingMethodController::class, 'create'])->name('shipping_methods.create')
                    ->middleware(['permission.role:online_store.shipping_methods.create', 'plan.limit:'.OnlineStorePlanFeatures::LIMIT_SHIPPING]);
                Route::post('/', [ShippingMethodController::class, 'store'])->name('shipping_methods.store')
                    ->middleware(['permission.role:online_store.shipping_methods.create', 'plan.limit:'.OnlineStorePlanFeatures::LIMIT_SHIPPING]);
                Route::get('/{shippingMethod}', [ShippingMethodController::class, 'show'])->name('shipping_methods.show')
                    ->middleware('permission.role:online_store.shipping_methods.view');
                Route::get('/{shippingMethod}/edit', [ShippingMethodController::class, 'edit'])->name('shipping_methods.edit')
                    ->middleware('permission.role:online_store.shipping_methods.edit');
                Route::put('/{shippingMethod}', [ShippingMethodController::class, 'update'])->name('shipping_methods.update')
                    ->middleware('permission.role:online_store.shipping_methods.edit');
                Route::delete('/{shippingMethod}', [ShippingMethodController::class, 'destroy'])->name('shipping_methods.destroy')
                    ->middleware('permission.role:online_store.shipping_methods.delete');
                Route::post('/{shippingMethod}/toggle-status', [ShippingMethodController::class, 'toggleStatus'])->name('shipping_methods.toggle-status')
                    ->middleware('permission.role:online_store.shipping_methods.toggle_status');
            });

            // ==========================================
            // طرق الدفع (Payment Methods) — عرض فقط؛ الإدارة موحّدة في
            // شاشة الإعدادات (Sitting\PaymentMethodsController) لتفادي
            // ازدواجية الكتابة على نفس الجدول بحقول متضاربة
            // ==========================================
            // بلا `plan:` — عرضٌ لطرق دفعٍ مُدارةٍ من إعدادات النظام العامة، لا
            // ميزةً خاصّة بباقة المتجر
            Route::prefix('payment-methods')->group(function () {
                Route::get('/', [PaymentMethodController::class, 'index'])->name('payment_methods.index')
                    ->middleware('permission.role:online_store.payment_methods.view');
                Route::get('/{paymentMethod}', [PaymentMethodController::class, 'show'])->name('payment_methods.show')
                    ->middleware('permission.role:online_store.payment_methods.view');
            });

            // ==========================================
            // البانرات والإعلانات (Store Banners)
            // ==========================================
            Route::prefix('banners')->middleware('plan:'.OnlineStorePlanFeatures::CONTENT)->group(function () {
                Route::get('/', [StoreBannerController::class, 'index'])->name('store_banners.index')
                    ->middleware('permission.role:online_store.banners.view');
                Route::get('/create', [StoreBannerController::class, 'create'])->name('store_banners.create')
                    ->middleware(['permission.role:online_store.banners.create', 'plan.limit:'.OnlineStorePlanFeatures::LIMIT_CONTENT]);
                Route::post('/', [StoreBannerController::class, 'store'])->name('store_banners.store')
                    ->middleware(['permission.role:online_store.banners.create', 'plan.limit:'.OnlineStorePlanFeatures::LIMIT_CONTENT]);
                Route::post('/update-order', [StoreBannerController::class, 'updateOrder'])->name('store_banners.update-order')
                    ->middleware('permission.role:online_store.banners.edit');
                Route::get('/{storeBanner}', [StoreBannerController::class, 'show'])->name('store_banners.show')
                    ->middleware('permission.role:online_store.banners.view');
                Route::get('/{storeBanner}/edit', [StoreBannerController::class, 'edit'])->name('store_banners.edit')
                    ->middleware('permission.role:online_store.banners.edit');
                Route::put('/{storeBanner}', [StoreBannerController::class, 'update'])->name('store_banners.update')
                    ->middleware('permission.role:online_store.banners.edit');
                Route::delete('/{storeBanner}', [StoreBannerController::class, 'destroy'])->name('store_banners.destroy')
                    ->middleware('permission.role:online_store.banners.delete');
                Route::post('/{storeBanner}/toggle-status', [StoreBannerController::class, 'toggleStatus'])->name('store_banners.toggle-status')
                    ->middleware('permission.role:online_store.banners.toggle_status');
                Route::post('/{storeBanner}/duplicate', [StoreBannerController::class, 'duplicate'])->name('store_banners.duplicate')
                    ->middleware(['permission.role:online_store.banners.create', 'plan.limit:'.OnlineStorePlanFeatures::LIMIT_CONTENT]);
                Route::delete('/{storeBanner}/mobile-image', [StoreBannerController::class, 'removeMobileImage'])->name('store_banners.remove-mobile-image')
                    ->middleware('permission.role:online_store.banners.edit');
            });

            // ==========================================
            // تقييمات المنتجات (Product Reviews)
            // ==========================================
            Route::prefix('reviews')->middleware('plan:'.OnlineStorePlanFeatures::FEEDBACK)->group(function () {
                Route::get('/', [ProductReviewController::class, 'index'])->name('product_reviews.index')
                    ->middleware('permission.role:online_store.reviews.view');
                Route::get('/{productReview}', [ProductReviewController::class, 'show'])->name('product_reviews.show')
                    ->middleware('permission.role:online_store.reviews.view');
                Route::delete('/{productReview}', [ProductReviewController::class, 'destroy'])->name('product_reviews.destroy')
                    ->middleware('permission.role:online_store.reviews.delete');
                Route::post('/{productReview}/approve', [ProductReviewController::class, 'approve'])->name('product_reviews.approve')
                    ->middleware('permission.role:online_store.reviews.moderate');
                Route::post('/{productReview}/reject', [ProductReviewController::class, 'reject'])->name('product_reviews.reject')
                    ->middleware('permission.role:online_store.reviews.moderate');
                Route::post('/{productReview}/reply', [ProductReviewController::class, 'reply'])->name('product_reviews.reply')
                    ->middleware('permission.role:online_store.reviews.reply');
                Route::delete('/{productReview}/reply', [ProductReviewController::class, 'removeReply'])->name('product_reviews.remove-reply')
                    ->middleware('permission.role:online_store.reviews.reply');
                Route::post('/{productReview}/toggle-featured', [ProductReviewController::class, 'toggleFeatured'])->name('product_reviews.toggle-featured')
                    ->middleware('permission.role:online_store.reviews.toggle_featured');
                Route::post('/bulk-approve', [ProductReviewController::class, 'bulkApprove'])->name('product_reviews.bulk-approve')
                    ->middleware('permission.role:online_store.reviews.moderate');
                Route::post('/bulk-reject', [ProductReviewController::class, 'bulkReject'])->name('product_reviews.bulk-reject')
                    ->middleware('permission.role:online_store.reviews.moderate');
                Route::post('/bulk-delete', [ProductReviewController::class, 'bulkDelete'])->name('product_reviews.bulk-delete')
                    ->middleware('permission.role:online_store.reviews.delete');
                Route::get('/product/{product}', [ProductReviewController::class, 'productReviews'])->name('product_reviews.product')
                    ->middleware('permission.role:online_store.reviews.view');
            });

            // ==========================================
            // أسئلة المنتجات (Product Questions)
            // ==========================================
            Route::prefix('questions')->middleware('plan:'.OnlineStorePlanFeatures::FEEDBACK)->group(function () {
                Route::get('/', [ProductQuestionController::class, 'index'])->name('product_questions.index')
                    ->middleware('permission.role:online_store.questions.view');
                // إضافة سؤال نيابةً عن عميل/زائر — قبل `/{productQuestion}` كي لا يُبتلع `create` كمعرِّف
                Route::get('/create', [ProductQuestionController::class, 'create'])->name('product_questions.create')
                    ->middleware('permission.role:online_store.questions.create');
                Route::post('/', [ProductQuestionController::class, 'store'])->name('product_questions.store')
                    ->middleware('permission.role:online_store.questions.create');
                Route::get('/{productQuestion}/edit', [ProductQuestionController::class, 'edit'])->name('product_questions.edit')
                    ->middleware('permission.role:online_store.questions.edit');
                Route::put('/{productQuestion}', [ProductQuestionController::class, 'update'])->name('product_questions.update')
                    ->middleware('permission.role:online_store.questions.edit');
                Route::get('/{productQuestion}', [ProductQuestionController::class, 'show'])->name('product_questions.show')
                    ->middleware('permission.role:online_store.questions.view');
                Route::delete('/{productQuestion}', [ProductQuestionController::class, 'destroy'])->name('product_questions.destroy')
                    ->middleware('permission.role:online_store.questions.delete');
                Route::post('/{productQuestion}/answer', [ProductQuestionController::class, 'answer'])->name('product_questions.answer')
                    ->middleware('permission.role:online_store.questions.answer');
                Route::put('/{productQuestion}/answer', [ProductQuestionController::class, 'updateAnswer'])->name('product_questions.update-answer')
                    ->middleware('permission.role:online_store.questions.answer');
                Route::post('/{productQuestion}/quick-answer', [ProductQuestionController::class, 'quickAnswer'])->name('product_questions.quick-answer')
                    ->middleware('permission.role:online_store.questions.answer');
                Route::post('/{productQuestion}/reject', [ProductQuestionController::class, 'reject'])->name('product_questions.reject')
                    ->middleware('permission.role:online_store.questions.moderate');
                Route::post('/{productQuestion}/reopen', [ProductQuestionController::class, 'reopen'])->name('product_questions.reopen')
                    ->middleware('permission.role:online_store.questions.moderate');
                Route::post('/{productQuestion}/toggle-public', [ProductQuestionController::class, 'togglePublic'])->name('product_questions.toggle-public')
                    ->middleware('permission.role:online_store.questions.toggle_public');
                Route::post('/bulk-answer', [ProductQuestionController::class, 'bulkAnswer'])->name('product_questions.bulk-answer')
                    ->middleware('permission.role:online_store.questions.answer');
                Route::post('/bulk-reject', [ProductQuestionController::class, 'bulkReject'])->name('product_questions.bulk-reject')
                    ->middleware('permission.role:online_store.questions.moderate');
                Route::post('/bulk-delete', [ProductQuestionController::class, 'bulkDelete'])->name('product_questions.bulk-delete')
                    ->middleware('permission.role:online_store.questions.delete');
                Route::get('/product/{product}', [ProductQuestionController::class, 'productQuestions'])->name('product_questions.product')
                    ->middleware('permission.role:online_store.questions.view');
            });

            // ==========================================
            // إعدادات المتجر (Store Settings)
            // ==========================================
            Route::prefix('settings')->middleware('plan:'.OnlineStorePlanFeatures::SETTINGS)->group(function () {
                Route::get('/', [StoreSettingsController::class, 'index'])->name('store_settings.index')
                    ->middleware('permission.role:online_store.settings.view');
                Route::get('/group/{group}', [StoreSettingsController::class, 'group'])->name('store_settings.group')
                    ->middleware('permission.role:online_store.settings.view');
                Route::post('/update', [StoreSettingsController::class, 'update'])->name('store_settings.update')
                    ->middleware('permission.role:online_store.settings.manage');
                Route::post('/update-single', [StoreSettingsController::class, 'updateSingle'])->name('store_settings.update-single')
                    ->middleware('permission.role:online_store.settings.manage');
                Route::post('/delete-file', [StoreSettingsController::class, 'deleteFile'])->name('store_settings.delete-file')
                    ->middleware('permission.role:online_store.settings.manage');
                Route::post('/reset', [StoreSettingsController::class, 'resetToDefault'])->name('store_settings.reset')
                    ->middleware('permission.role:online_store.settings.reset');
                Route::get('/export', [StoreSettingsController::class, 'export'])->name('store_settings.export')
                    ->middleware('permission.role:online_store.settings.import_export');
                Route::post('/import', [StoreSettingsController::class, 'import'])->name('store_settings.import')
                    ->middleware('permission.role:online_store.settings.import_export');
                Route::post('/test-email', [StoreSettingsController::class, 'testEmail'])->name('store_settings.test-email')
                    ->middleware('permission.role:online_store.settings.manage');
                Route::post('/clear-cache', [StoreSettingsController::class, 'clearCache'])->name('store_settings.clear-cache')
                    ->middleware('permission.role:online_store.settings.clear_cache');
                Route::get('/accounts-search', [StoreSettingsController::class, 'searchAccounts'])->name('store_settings.accounts-search')
                    ->middleware('permission.role:online_store.settings.view');
            });

            // ==========================================
            // صفحات المتجر (Store Pages)
            // ==========================================
            Route::prefix('pages')->middleware('plan:'.OnlineStorePlanFeatures::CONTENT)->group(function () {
                Route::get('/', [StorePageController::class, 'index'])->name('store_pages.index')
                    ->middleware('permission.role:online_store.pages.view');
                Route::get('/create', [StorePageController::class, 'create'])->name('store_pages.create')
                    ->middleware('permission.role:online_store.pages.create');
                Route::post('/', [StorePageController::class, 'store'])->name('store_pages.store')
                    ->middleware('permission.role:online_store.pages.create');
                Route::get('/type/{type}', [StorePageController::class, 'byType'])->name('store_pages.by-type')
                    ->middleware('permission.role:online_store.pages.view');
                Route::post('/update-order', [StorePageController::class, 'updateOrder'])->name('store_pages.update-order')
                    ->middleware('permission.role:online_store.pages.edit');
                Route::get('/{storePage}', [StorePageController::class, 'show'])->name('store_pages.show')
                    ->middleware('permission.role:online_store.pages.view');
                Route::get('/{storePage}/edit', [StorePageController::class, 'edit'])->name('store_pages.edit')
                    ->middleware('permission.role:online_store.pages.edit');
                Route::get('/{storePage}/preview', [StorePageController::class, 'preview'])->name('store_pages.preview')
                    ->middleware('permission.role:online_store.pages.view');
                Route::put('/{storePage}', [StorePageController::class, 'update'])->name('store_pages.update')
                    ->middleware('permission.role:online_store.pages.edit');
                Route::delete('/{storePage}', [StorePageController::class, 'destroy'])->name('store_pages.destroy')
                    ->middleware('permission.role:online_store.pages.delete');
                Route::post('/{storePage}/toggle-status', [StorePageController::class, 'toggleStatus'])->name('store_pages.toggle-status')
                    ->middleware('permission.role:online_store.pages.toggle_status');
                Route::post('/{storePage}/duplicate', [StorePageController::class, 'duplicate'])->name('store_pages.duplicate')
                    ->middleware('permission.role:online_store.pages.create');
                Route::post('/bulk-delete', [StorePageController::class, 'bulkDelete'])->name('store_pages.bulk-delete')
                    ->middleware('permission.role:online_store.pages.delete');
                Route::post('/bulk-activate', [StorePageController::class, 'bulkActivate'])->name('store_pages.bulk-activate')
                    ->middleware('permission.role:online_store.pages.toggle_status');
                Route::post('/bulk-deactivate', [StorePageController::class, 'bulkDeactivate'])->name('store_pages.bulk-deactivate')
                    ->middleware('permission.role:online_store.pages.toggle_status');
            });

            // ==========================================
            // قوالب البريد الإلكتروني (Email Templates)
            // ==========================================
            Route::prefix('email-templates')->middleware('plan:'.OnlineStorePlanFeatures::EMAIL)->group(function () {
                Route::get('/', [EmailTemplateController::class, 'index'])->name('email_templates.index')
                    ->middleware('permission.role:online_store.email_templates.view');
                Route::get('/create', [EmailTemplateController::class, 'create'])->name('email_templates.create')
                    ->middleware('permission.role:online_store.email_templates.create');
                Route::post('/', [EmailTemplateController::class, 'store'])->name('email_templates.store')
                    ->middleware('permission.role:online_store.email_templates.create');
                Route::get('/export', [EmailTemplateController::class, 'export'])->name('email_templates.export')
                    ->middleware('permission.role:online_store.email_templates.export');
                Route::get('/{emailTemplate}', [EmailTemplateController::class, 'show'])->name('email_templates.show')
                    ->middleware('permission.role:online_store.email_templates.view');
                Route::get('/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('email_templates.edit')
                    ->middleware('permission.role:online_store.email_templates.edit');
                Route::put('/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email_templates.update')
                    ->middleware('permission.role:online_store.email_templates.edit');
                Route::delete('/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('email_templates.destroy')
                    ->middleware('permission.role:online_store.email_templates.delete');
                Route::post('/{emailTemplate}/toggle-status', [EmailTemplateController::class, 'toggleStatus'])->name('email_templates.toggle-status')
                    ->middleware('permission.role:online_store.email_templates.edit');
                Route::get('/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->name('email_templates.preview')
                    ->middleware('permission.role:online_store.email_templates.view');
                Route::post('/{emailTemplate}/duplicate', [EmailTemplateController::class, 'duplicate'])->name('email_templates.duplicate')
                    ->middleware('permission.role:online_store.email_templates.create');
                Route::post('/{emailTemplate}/send-test', [EmailTemplateController::class, 'sendTest'])->name('email_templates.send-test')
                    ->middleware('permission.role:online_store.email_templates.send_test');
            });

            // ==========================================
            // سجلات البريد الإلكتروني (Email Logs)
            // ==========================================
            Route::prefix('email-logs')->middleware('plan:'.OnlineStorePlanFeatures::EMAIL)->group(function () {
                Route::get('/', [EmailLogController::class, 'index'])->name('email_logs.index')
                    ->middleware('permission.role:online_store.email_logs.view');
                Route::get('/statistics', [EmailLogController::class, 'statistics'])->name('email_logs.statistics')
                    ->middleware('permission.role:online_store.email_logs.view');
                Route::get('/export', [EmailLogController::class, 'export'])->name('email_logs.export')
                    ->middleware('permission.role:online_store.email_logs.export');
                Route::get('/{emailLog}', [EmailLogController::class, 'show'])->name('email_logs.show')
                    ->middleware('permission.role:online_store.email_logs.view');
                Route::post('/{emailLog}/resend', [EmailLogController::class, 'resend'])->name('email_logs.resend')
                    ->middleware('permission.role:online_store.email_logs.resend');
                Route::delete('/{emailLog}', [EmailLogController::class, 'destroy'])->name('email_logs.destroy')
                    ->middleware('permission.role:online_store.email_logs.delete');
                Route::post('/bulk-delete', [EmailLogController::class, 'bulkDelete'])->name('email_logs.bulk-delete')
                    ->middleware('permission.role:online_store.email_logs.delete');
                Route::post('/cleanup', [EmailLogController::class, 'cleanup'])->name('email_logs.cleanup')
                    ->middleware('permission.role:online_store.email_logs.cleanup');
            });

            // ==========================================
            // الدول (Countries)
            // ==========================================
            Route::prefix('countries')->middleware('plan:'.OnlineStorePlanFeatures::SETTINGS)->group(function () {
                Route::get('/', [CountryController::class, 'index'])->name('countries.index')
                    ->middleware('permission.role:online_store.geo.view');
                Route::get('/create', [CountryController::class, 'create'])->name('countries.create')
                    ->middleware('permission.role:online_store.geo.create');
                Route::post('/', [CountryController::class, 'store'])->name('countries.store')
                    ->middleware('permission.role:online_store.geo.create');
                Route::get('/{country}', [CountryController::class, 'show'])->name('countries.show')
                    ->middleware('permission.role:online_store.geo.view');
                Route::get('/{country}/edit', [CountryController::class, 'edit'])->name('countries.edit')
                    ->middleware('permission.role:online_store.geo.edit');
                Route::put('/{country}', [CountryController::class, 'update'])->name('countries.update')
                    ->middleware('permission.role:online_store.geo.edit');
                Route::delete('/{country}', [CountryController::class, 'destroy'])->name('countries.destroy')
                    ->middleware('permission.role:online_store.geo.delete');
                Route::post('/{country}/toggle-status', [CountryController::class, 'toggleStatus'])->name('countries.toggle-status')
                    ->middleware('permission.role:online_store.geo.toggle_status');
                Route::post('/{country}/toggle-default', [CountryController::class, 'toggleDefault'])->name('countries.toggle-default')
                    ->middleware('permission.role:online_store.geo.toggle_status');
                Route::get('/{country}/cities', [CountryController::class, 'getCities'])->name('countries.get-cities')
                    ->middleware('permission.role:online_store.geo.view');
                Route::post('/bulk-activate', [CountryController::class, 'bulkActivate'])->name('countries.bulk-activate')
                    ->middleware('permission.role:online_store.geo.toggle_status');
                Route::post('/bulk-deactivate', [CountryController::class, 'bulkDeactivate'])->name('countries.bulk-deactivate')
                    ->middleware('permission.role:online_store.geo.toggle_status');
            });

            // ==========================================
            // المدن (Cities)
            // ==========================================
            Route::prefix('cities')->middleware('plan:'.OnlineStorePlanFeatures::SETTINGS)->group(function () {
                Route::get('/', [CityController::class, 'index'])->name('cities.index')
                    ->middleware('permission.role:online_store.geo.view');
                Route::get('/create', [CityController::class, 'create'])->name('cities.create')
                    ->middleware('permission.role:online_store.geo.create');
                Route::post('/', [CityController::class, 'store'])->name('cities.store')
                    ->middleware('permission.role:online_store.geo.create');
                Route::get('/{city}', [CityController::class, 'show'])->name('cities.show')
                    ->middleware('permission.role:online_store.geo.view');
                Route::get('/{city}/edit', [CityController::class, 'edit'])->name('cities.edit')
                    ->middleware('permission.role:online_store.geo.edit');
                Route::put('/{city}', [CityController::class, 'update'])->name('cities.update')
                    ->middleware('permission.role:online_store.geo.edit');
                Route::delete('/{city}', [CityController::class, 'destroy'])->name('cities.destroy')
                    ->middleware('permission.role:online_store.geo.delete');
                Route::post('/{city}/toggle-status', [CityController::class, 'toggleStatus'])->name('cities.toggle-status')
                    ->middleware('permission.role:online_store.geo.toggle_status');
                Route::post('/bulk-activate', [CityController::class, 'bulkActivate'])->name('cities.bulk-activate')
                    ->middleware('permission.role:online_store.geo.toggle_status');
                Route::post('/bulk-deactivate', [CityController::class, 'bulkDeactivate'])->name('cities.bulk-deactivate')
                    ->middleware('permission.role:online_store.geo.toggle_status');
            });
        });
    },
);
