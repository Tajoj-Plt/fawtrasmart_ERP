<?php

use App\Constants\planFeatures\StockPlanFeatures;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Account\Http\Controllers\AccountsChartController;
use Modules\Account\Http\Controllers\AssetsController;
use Modules\Stock\Http\Controllers\Stock\BrandController;
use Modules\Stock\Http\Controllers\Stock\CategoryController;
use Modules\Stock\Http\Controllers\Stock\InventoryManagementController;
use Modules\Stock\Http\Controllers\Stock\InventoryReportController;
use Modules\Stock\Http\Controllers\Stock\InventorySettingsController;
use Modules\Stock\Http\Controllers\Stock\PriceListController;
use Modules\Stock\Http\Controllers\Stock\ProductsController;
use Modules\Stock\Http\Controllers\Stock\ProductsSettingsController;
use Modules\Stock\Http\Controllers\Stock\BarcodeSettingsController;
use Modules\Stock\Http\Controllers\Stock\StorehouseController;
use Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController;
use Modules\Stock\Http\Controllers\Stock\TemplateUnitController;
use Modules\Stock\Http\Controllers\Stock\ProductGroupController;
use Modules\Stock\Http\Controllers\StockController;
use Modules\Stock\Http\Controllers\EmployeeStockMovementController;
// Add the new report controllers
use Modules\Stock\Http\Controllers\Reports\InventorySheetReportController;
use Modules\Stock\Http\Controllers\Reports\SummaryInventoryReportController;
use Modules\Stock\Http\Controllers\Reports\DetailedMovementReportController;
use Modules\Stock\Http\Controllers\Reports\WarehouseBalanceReportController;
use Modules\Stock\Http\Controllers\Reports\ValueInventoryReportController;
use Modules\Stock\Http\Controllers\Reports\InventoryBalanceReportController;
use Modules\Stock\Http\Controllers\Reports\TrialBalanceReportController;
use Modules\Stock\Http\Controllers\Reports\InventoryMovementDetailReportController;
use Modules\Stock\Http\Controllers\Reports\ComprehensiveInventoryReportController;

Route::middleware(['auth'])->group(function () {
    // AJAX route بدون بادئة اللغة
    //
    // **مسار AJAX يُحرَس كما تُحرَس الشاشة**: هذا العنوان يقرأ رصيد صنفٍ في
    // مستودع — وهو نفس ما تعرضه شاشة الأذون. فتركُه بلا حدّ باقة يجعل من
    // مُنعت عنه الشاشةُ يقرأ البيانات نفسها بطلبٍ مباشر. وهو أوّل ما يُنسى
    // لأنه ليس شاشةً ولا بند قائمة
    //
    // والصلاحية قائمةٌ بديلة لا صلاحيةً واحدة: مستدعوه هم شاشاتُ إنشاء الإذن
    // وتعديله، ولكلٍّ منها صلاحيتها. وكان الشرط `stock.permits.view` وحدها —
    // فمن مُنح «إنشاء إذن صرف» بلا «عرض الأذون» تُفتح له الشاشةُ ويُردّ طلبُ
    // الرصيد بـ403، فيرى صفراً في خانة «الرصيد قبل» بلا خبر: يصرف على رصيدٍ
    // يظنّه معدوماً، أو يمتنع عن صرفٍ متاح.
    Route::get('/get-product-stock/{storeId}/{productId}', [StorePermitsManagementController::class, 'getProductStock'])
                            ->middleware([
                                'permission.role:stock.permits.view'
                                    .'|stock.permits.create_addition'
                                    .'|stock.permits.create_disbursement'
                                    .'|stock.permits.create_transfer'
                                    .'|stock.permits.create_opening'
                                    .'|stock.permits.edit',
                                'plan:'.StockPlanFeatures::WAREHOUSE_PERMITS,
                            ]);

    Route::group(
        [
            'prefix' => LaravelLocalization::setLocale(),
            'middleware' => [
                'localeSessionRedirect',
                'localizationRedirect',
                'localeViewPath',
                'check.branch',
                'auth',
                // «الفترة المحاسبية» المختارة تصير تاريخَي التقرير قبل
                // أن يقرأهما المتحكّم — كما في وحدة الحسابات
                'accounting.period',
            ],
        ],
        function () {
            /*
                حدّ الباقة على الوحدة كلّها — سورٌ خارجي قبل الأسوار الداخلية.

                الشرط «واحدةٌ على الأقل» لا «كلّها»: باقةٌ تشمل التقارير وحدها
                يجب أن تفتح تقاريرها، وحارسُ كل شاشةٍ داخلَها يتكفّل بالباقي.
                وباقةٌ لا تشمل شيئاً من المخزون تُردّ من هنا فلا يصل الطلب إلى
                متحكّمٍ أصلاً.

                وموضعُه على المجموعة لا على كل مسار: الوحدة فيها ثلاثمئة مسار،
                ونسيانُ واحدٍ منها يفتحه كلّه — والمنسيّ عادةً مسار AJAX لا
                شاشة، فلا يُلاحَظ غيابُ حارسِه أبداً.
            */
            Route::prefix('stock')
                ->middleware(['auth', 'plan:'.implode('|', StockPlanFeatures::stockOnly())])
                ->group(function () {
                    // ===== Stock Dashboard =====
                    // بوابة الدخول داخل StockController::__construct() — أي صلاحية stock.* واحدة تكفي، لا stock.reports.view بعينها
                    Route::get('/dashboard', [StockController::class, 'index'])->name('stock.dashboard');
                    Route::get('/advanced-analytics', [StockController::class, 'advancedAnalytics'])->name('stock.advanced_analytics')
                            ->middleware(['permission.role:stock.reports.view', 'plan:'.StockPlanFeatures::REPORTS]);
                    
                    // ===== Session Keep-Alive Route =====
                    Route::get('/heartbeat', function() {
                        return response()->json([
                            'status' => 'alive',
                            'user' => auth()->user()->first_name ?? 'Unknown',
                            'time' => now()->format('Y-m-d H:i:s')
                        ]);
                    })->name('stock.heartbeat');
                    
                    // ===== Employee Stock Movement =====
                    Route::get('/employee-stock-movements', [EmployeeStockMovementController::class, 'index'])->name('stock.employee_movements')
                            ->middleware(['permission.role:stock.employee_stock.view', 'plan:'.StockPlanFeatures::REPORTS]);

                    // ===== Products Management =====
                    Route::prefix('products')->middleware('plan:'.StockPlanFeatures::PRODUCTS)->group(function () {
                        Route::get('/index', [ProductsController::class, 'index'])
                            ->name('products.index')
                            ->middleware('permission.role:stock.products.view');
                        Route::get('/traking/products', [ProductsController::class, 'traking'])
                            ->name('products.traking')
                            ->middleware('permission.role:stock.products.movements');
                        Route::get('/create', [ProductsController::class, 'create'])
                            ->name('products.create')
                            ->middleware(['permission.role:stock.products.edit', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                        Route::post('/products/export', [ProductsController::class, 'export'])
                            ->name('products.export')
                            ->middleware('permission.role:stock.products.export');
                        Route::get('/products/export', [ProductsController::class, 'export'])
                            ->name('products.export.get')
                            ->middleware('permission.role:stock.products.export');
                        Route::post('/products/import', [ProductsController::class, 'import'])
                            ->name('products.import')
                            ->middleware('permission.role:stock.products.import');
                        Route::get('/activity-logs/{id}', [ProductsController::class, 'getActivityLogs'])
                            ->name('products.activity_logs')
                            ->middleware('permission.role:stock.products.timeline');
                        Route::get('/timeline/{id}', [ProductsController::class, 'getTimeline'])
                            ->name('products.timeline')
                            ->middleware('permission.role:stock.products.timeline');
                        Route::get('/stock-movements/{id}', [ProductsController::class, 'getStockMovements'])
                            ->name('products.stock_movements')
                            ->middleware('permission.role:stock.products.movements');
                        Route::get('/{id}/activity-logs', [ProductsController::class, 'getActivityLogs'])
                            ->name('products.show.activity_logs')
                            ->middleware('permission.role:stock.products.timeline');
                        Route::get('/{id}/timeline', [ProductsController::class, 'getTimeline'])
                            ->name('products.show.timeline')
                            ->middleware('permission.role:stock.products.timeline');
                        Route::get('/{id}/stock-movements', [ProductsController::class, 'getStockMovements'])
                            ->name('products.show.stock_movements')
                            ->middleware('permission.role:stock.products.movements');
                        Route::get('/print-barcode/{id}', [ProductsController::class, 'printBarcode'])
                            ->name('products.print_barcode')
                            ->middleware('permission.role:stock.barcodes.print');
                        Route::get('/get-sub-units', [ProductsController::class, 'getSubUnits'])
                            ->name('products.getSubUnits')
                            ->middleware('permission.role:stock.products.view');
                        Route::get('/get-category-units', [ProductsController::class, 'getCategoryUnits'])
                            ->name('products.getCategoryUnits')
                            ->middleware('permission.role:stock.products.view');
                        Route::get('/compiled', [ProductsController::class, 'compiled'])
                            ->name('products.compiled')
                            ->middleware(['permission.role:stock.products.compiled', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                        Route::post('/compiled', [ProductsController::class, 'compiled_store'])
                            ->name('products.compiled_store')
                            ->middleware(['permission.role:stock.products.compiled', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                        Route::get('/create/services', [ProductsController::class, 'create_services'])
                            ->name('products.create_services')
                            ->middleware(['permission.role:stock.products.services', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                        Route::post('/store/services', [ProductsController::class, 'store_services'])
                            ->name('products.store_services')
                            ->middleware(['permission.role:stock.products.services', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                        Route::get('/show/{id}', [ProductsController::class, 'show'])
                            ->name('products.show')
                            ->middleware('permission.role:stock.products.show');
                        Route::get('/edit/{id}', [ProductsController::class, 'edit'])
                            ->name('products.edit')
                            ->middleware('permission.role:stock.products.edit');
                        Route::get('/manual_stock_adjust/{id}', [ProductsController::class, 'manual_stock_adjust'])
                            ->name('products.manual_stock_adjust')
                            ->middleware('permission.role:stock.products.manual_adjust');
                        Route::post('/store', [ProductsController::class, 'store'])
                            ->name('products.store')
                            ->middleware(['permission.role:stock.products.edit', 'plan.limit:'.StockPlanFeatures::LIMIT_PRODUCTS]);
                        Route::get('/{id}/barcode/print', [ProductsController::class, 'printBarcode'])
                            ->name('products.print_barcode')
                            ->middleware('permission.role:stock.barcodes.print');
                        Route::get('/{id}/barcode/download', [ProductsController::class, 'downloadBarcode'])
                            ->name('products.download_barcode')
                            ->middleware('permission.role:stock.barcodes.download');
                        Route::get('/{id}/barcode/pdf', [ProductsController::class, 'printBarcodePdf'])
                            ->name('products.print_barcode_pdf')
                            ->middleware('permission.role:stock.barcodes.print');
                        Route::get('/{id}/barcode', [ProductsController::class, 'getBarcode'])
                            ->name('products.get_barcode')
                            ->middleware('permission.role:stock.barcodes.print');
                        Route::post('/barcodes/print-multiple', [ProductsController::class, 'printMultipleBarcodes'])
                            ->name('products.print_multiple_barcodes')
                            ->middleware('permission.role:stock.barcodes.print');
                        Route::post('/add/manual_stock_adjust/{id}', [ProductsController::class, 'add_manual_stock_adjust'])
                            ->name('products.add_manual_stock_adjust')
                            ->middleware('permission.role:stock.products.manual_adjust');
                        Route::put('/update/{id}', [ProductsController::class, 'update'])
                            ->name('products.update')
                            ->middleware('permission.role:stock.products.edit');
                        Route::delete('/delete/{id}', [ProductsController::class, 'delete'])
                            ->name('products.delete')
                            ->middleware('permission.role:stock.products.delete');
                        Route::get('/search', [ProductsController::class, 'search'])
                            ->name('products.search')
                            ->middleware('permission.role:stock.products.view');
                        Route::post('/import', [ProductsController::class, 'import'])
                            ->name('products.import')
                            ->middleware('permission.role:stock.products.import');
                        Route::post('/{id}/update-status', [ProductsController::class, 'updateStatus'])
                            ->name('products.updateStatus')
                            ->middleware('permission.role:stock.products.toggle_status');
                        Route::get('/getcategories', [ProductsController::class, 'categories'])
                            ->name('get-categories')
                            ->middleware('permission.role:stock.products.view');
                    });

                    // ===== Category Management =====
                    Route::prefix('category')->middleware('plan:'.StockPlanFeatures::CATEGORIES)->group(function () {
                        Route::get('/', [CategoryController::class, 'index'])
                            ->name('category.index')
                            ->middleware('permission.role:stock.categories.view');
                        Route::get('/create', [CategoryController::class, 'create'])
                            ->name('category.create')
                            ->middleware(['permission.role:stock.categories.create', 'plan.limit:'.StockPlanFeatures::LIMIT_CATEGORIES]);
                        Route::get('/show/{id}', [CategoryController::class, 'show'])
                            ->name('category.show')
                            ->middleware('permission.role:stock.categories.view');
                        Route::get('/edit/{id}', [CategoryController::class, 'edit'])
                            ->name('category.edit')
                            ->middleware('permission.role:stock.categories.edit');
                        Route::post('/store', [CategoryController::class, 'store'])
                            ->name('category.store')
                            ->middleware(['permission.role:stock.categories.create', 'plan.limit:'.StockPlanFeatures::LIMIT_CATEGORIES]);
                        Route::put('/update/{id}', [CategoryController::class, 'update'])
                            ->name('category.update')
                            ->middleware('permission.role:stock.categories.edit');
                        Route::delete('/destroy/{id}', [CategoryController::class, 'destroy'])
                            ->name('category.destroy')
                            ->middleware('permission.role:stock.categories.delete');
                        Route::get('/delete/{id}', [CategoryController::class, 'delete'])
                            ->name('category.delete')
                            ->middleware('permission.role:stock.categories.delete');
                        Route::post('/toggle-status/{id}', [CategoryController::class, 'toggleStatus'])
                            ->name('category.toggle-status')
                            ->middleware('permission.role:stock.categories.toggle_status');
                    });

                    // ===== Units Management =====
                    Route::prefix('units')->middleware('plan:'.StockPlanFeatures::PRODUCTS)->group(function () {
                        Route::get('/', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'index'])
                            ->name('units.index')
                            ->middleware('permission.role:stock.units.view');
                        Route::get('/create', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'create'])
                            ->name('units.create')
                            ->middleware('permission.role:stock.units.create');
                        Route::post('/store', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'store'])
                            ->name('units.store')
                            ->middleware('permission.role:stock.units.create');
                        Route::get('/show/{id}', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'show'])
                            ->name('units.show')
                            ->middleware('permission.role:stock.units.view');
                        Route::get('/edit/{id}', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'edit'])
                            ->name('units.edit')
                            ->middleware('permission.role:stock.units.edit');
                        Route::put('/update/{id}', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'update'])
                            ->name('units.update')
                            ->middleware('permission.role:stock.units.edit');
                        Route::delete('/destroy/{id}', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'destroy'])
                            ->name('units.destroy')
                            ->middleware('permission.role:stock.units.delete');
                        Route::post('/{id}/toggle-status', [\Modules\Stock\Http\Controllers\Stock\UnitController::class, 'toggleStatus'])
                            ->name('units.toggle-status')
                            ->middleware('permission.role:stock.units.toggle_status');
                    });

                    // ===== Category Units Management =====
                    Route::prefix('category-units')->middleware('plan:'.StockPlanFeatures::CATEGORIES)->group(function () {
                        Route::get('/{category_id}', [\Modules\Stock\Http\Controllers\Stock\CategoryUnitController::class, 'index'])
                            ->name('category_units.index')
                            ->middleware('permission.role:stock.categories.view');
                        Route::get('/{category_id}/create', [\Modules\Stock\Http\Controllers\Stock\CategoryUnitController::class, 'create'])
                            ->name('category_units.create')
                            ->middleware('permission.role:stock.categories.create');
                        Route::post('/{category_id}/store', [\Modules\Stock\Http\Controllers\Stock\CategoryUnitController::class, 'store'])
                            ->name('category_units.store')
                            ->middleware('permission.role:stock.categories.create');
                        Route::get('/{category_id}/edit/{id}', [\Modules\Stock\Http\Controllers\Stock\CategoryUnitController::class, 'edit'])
                            ->name('category_units.edit')
                            ->middleware('permission.role:stock.categories.edit');
                        Route::put('/{category_id}/update/{id}', [\Modules\Stock\Http\Controllers\Stock\CategoryUnitController::class, 'update'])
                            ->name('category_units.update')
                            ->middleware('permission.role:stock.categories.edit');
                        Route::get('/{category_id}/delete/{id}', [\Modules\Stock\Http\Controllers\Stock\CategoryUnitController::class, 'delete'])
                            ->name('category_units.delete')
                            ->middleware('permission.role:stock.categories.delete');
                    });

                    // ===== Brand Management =====
                    // الأسماء مسبوقة بـ`stock.` لأن `brands.*` مُعرَّف أيضاً في
                    // وحدة المتجر، ومساراتها تُحمَّل بعد هذه فتحجبها تماماً —
                    // فكانت شاشات براندات المخزون غير قابلة للوصول إطلاقاً
                    Route::prefix('brands')->middleware('plan:'.StockPlanFeatures::BRANDS)->group(function () {
                        Route::get('/index', [BrandController::class, 'index'])
                            ->name('stock.brands.index')
                            ->middleware('permission.role:stock.brands.view');
                        Route::get('/create', [BrandController::class, 'create'])
                            ->name('stock.brands.create')
                            ->middleware(['permission.role:stock.brands.create', 'plan.limit:'.StockPlanFeatures::LIMIT_BRANDS]);
                        Route::post('/store', [BrandController::class, 'store'])
                            ->name('stock.brands.store')
                            ->middleware(['permission.role:stock.brands.create', 'plan.limit:'.StockPlanFeatures::LIMIT_BRANDS]);
                        Route::get('/show/{brand}', [BrandController::class, 'show'])
                            ->name('stock.brands.show')
                            ->middleware('permission.role:stock.brands.view');
                        Route::get('/edit/{brand}', [BrandController::class, 'edit'])
                            ->name('stock.brands.edit')
                            ->middleware('permission.role:stock.brands.edit');
                        Route::put('/update/{brand}', [BrandController::class, 'update'])
                            ->name('stock.brands.update')
                            ->middleware('permission.role:stock.brands.edit');
                        Route::delete('/destroy/{brand}', [BrandController::class, 'destroy'])
                            ->name('stock.brands.destroy')
                            ->middleware('permission.role:stock.brands.delete');
                        Route::post('/toggle-status/{brand}', [BrandController::class, 'toggleStatus'])
                            ->name('stock.brands.toggleStatus')
                            ->middleware('permission.role:stock.brands.toggle_status');
                        Route::post('/toggle-featured/{brand}', [BrandController::class, 'toggleFeatured'])
                            ->name('stock.brands.toggleFeatured')
                            ->middleware('permission.role:stock.brands.toggle_status');
                        Route::get('/search', [BrandController::class, 'search'])
                            ->name('stock.brands.search')
                            ->middleware('permission.role:stock.brands.view');
                    });

                    // ===== Storehouse Management =====
                    Route::prefix('storehouse')->middleware('plan:'.StockPlanFeatures::STOREHOUSES)->group(function () {
                        Route::get('/index', [StorehouseController::class, 'index'])
                            ->name('storehouse.index')
                            ->middleware('permission.role:stock.warehouses.view');
                        Route::get('/create', [StorehouseController::class, 'create'])
                            ->name('storehouse.create')
                            ->middleware(['permission.role:stock.warehouses.create', 'plan.limit:'.StockPlanFeatures::LIMIT_STOREHOUSES]);
                        Route::get('/show/{id}', [StorehouseController::class, 'show'])
                            ->name('storehouse.show')
                            ->middleware('permission.role:stock.warehouses.show');
                        Route::get('/edit/{id}', [StorehouseController::class, 'edit'])
                            ->name('storehouse.edit')
                            ->middleware('permission.role:stock.warehouses.edit');
                        Route::post('/store', [StorehouseController::class, 'store'])
                            ->name('storehouse.store')
                            ->middleware(['permission.role:stock.warehouses.create', 'plan.limit:'.StockPlanFeatures::LIMIT_STOREHOUSES]);
                        Route::post('/update/{id}', [StorehouseController::class, 'update'])
                            ->name('storehouse.update')
                            ->middleware('permission.role:stock.warehouses.edit');
                        Route::get('/delete/{id}', [StorehouseController::class, 'delete'])
                            ->name('storehouse.delete')
                            ->middleware('permission.role:stock.warehouses.delete');
                        Route::get('/summary/inventory_operations/{id}', [StorehouseController::class, 'summary_inventory_operations'])
                            ->name('storehouse.summary_inventory_operations')
                            ->middleware('permission.role:stock.reports.summary_movement');
                        Route::get('/summary/inventory_operations/{id}/search', [StorehouseController::class, 'searchSummaryInventoryOperations'])
                            ->name('storehouse.summary_inventory_operations.search')
                            ->middleware('permission.role:stock.reports.summary_movement');
                        Route::get('/summary/inventory_operations/{id}/export/{format}', [StorehouseController::class, 'exportSummaryInventoryOperations'])
                            ->name('storehouse.summary_inventory_operations.export')
                            ->middleware('permission.role:stock.reports.export');
                        Route::get('/inventory_value/{id}', [StorehouseController::class, 'inventory_value'])
                            ->name('storehouse.inventory_value')
                            ->middleware('permission.role:stock.reports.value');
                        Route::get('/inventory_value/{id}/search', [StorehouseController::class, 'searchInventoryValue'])
                            ->name('storehouse.inventory_value.search')
                            ->middleware('permission.role:stock.reports.value');
                        Route::get('/inventory_value/{id}/export/{format}', [StorehouseController::class, 'exportInventoryValue'])
                            ->name('storehouse.inventory_value.export')
                            ->middleware('permission.role:stock.reports.export');
                        Route::get('/inventory_sheet/{id}', [StorehouseController::class, 'inventory_sheet'])
                            ->name('storehouse.inventory_sheet')
                            ->middleware('permission.role:stock.reports.inventory_sheet');
                        Route::post('/inventory_value/{id}/generate-bulk-opening-balance', [StorehouseController::class, 'generateBulkOpeningBalance'])
                            ->name('storehouse.inventory_value.generate_bulk_opening_balance')
                            ->middleware('permission.role:stock.warehouses.opening_balance');
                        // **يُنشئ إذناً مخزنياً لكل مستودع في نداءٍ واحد** — لا واحداً.
                        // فكمّيتُه لا تُعرف قبل التنفيذ، ولهذا لا `plan.limit`
                        // عليه هنا: الحارس الفعلي هو PlanLimitObserver على
                        // النموذج، ويرتدّ بالمعاملة كلّها عند بلوغ الحدّ
                        Route::post('/generate-all-opening-balances', [StorehouseController::class, 'generateAllWarehousesOpeningBalance'])
                            ->name('storehouse.generate_all_opening_balances')
                            ->middleware('permission.role:stock.warehouses.opening_balance');
                        // Add the toggle status route
                        Route::post('/toggle-status/{id}', [StorehouseController::class, 'toggleStatus'])
                            ->name('storehouse.toggle_status')
                            ->middleware('permission.role:stock.warehouses.toggle_status');
                        
                        // كشف حساب المستودع (Warehouse Statement)
                        Route::get('/statement', [\Modules\Stock\Http\Controllers\Reports\WarehouseStatementController::class, 'index'])
                            ->name('storehouse.statement')
                            ->middleware(['permission.role:stock.reports.view', 'plan:'.StockPlanFeatures::REPORTS]);
                        Route::get('/statement/data', [\Modules\Stock\Http\Controllers\Reports\WarehouseStatementController::class, 'getData'])
                            ->name('storehouse.statement.data')
                            ->middleware(['permission.role:stock.reports.view', 'plan:'.StockPlanFeatures::REPORTS]);
                        
                        // كشف حساب المستودع الخاص بالموظف (تصميم مخصص للمندوب)
                        Route::get('/employee-statement', [\Modules\Stock\Http\Controllers\Reports\WarehouseStatementController::class, 'employeeStatement'])
                            ->name('storehouse.employee_statement')
                            ->middleware(['permission.role:stock.reports.view', 'plan:'.StockPlanFeatures::REPORTS]);
                        
                        // تقرير المخزون التالف والسليم
                        Route::get('/damaged-stock', [\Modules\Stock\Http\Controllers\Reports\DamagedStockReportController::class, 'index'])
                            ->name('storehouse.damaged_stock')
                            ->middleware(['permission.role:stock.reports.view', 'plan:'.StockPlanFeatures::REPORTS]);
                        Route::get('/damaged-stock/data', [\Modules\Stock\Http\Controllers\Reports\DamagedStockReportController::class, 'getData'])
                            ->name('storehouse.damaged_stock.data')
                            ->middleware(['permission.role:stock.reports.view', 'plan:'.StockPlanFeatures::REPORTS]);
                    });

                    // ===== Price Lists Management =====
                    Route::prefix('price_list')->middleware('plan:'.StockPlanFeatures::PRICE_LISTS)->group(function () {
                        Route::get('/index', [PriceListController::class, 'index'])
                            ->name('price_list.index')
                            ->middleware('permission.role:stock.price_lists.view');
                        Route::get('/create', [PriceListController::class, 'create'])
                            ->name('price_list.create')
                            ->middleware(['permission.role:stock.price_lists.create', 'plan.limit:'.StockPlanFeatures::LIMIT_PRICE_LISTS]);
                        Route::get('/show/{id}', [PriceListController::class, 'show'])
                            ->name('price_list.show')
                            ->middleware('permission.role:stock.price_lists.show');
                        Route::get('/edit/{id}', [PriceListController::class, 'edit'])
                            ->name('price_list.edit')
                            ->middleware('permission.role:stock.price_lists.edit');
                        Route::post('/store', [PriceListController::class, 'store'])
                            ->name('price_list.store')
                            ->middleware(['permission.role:stock.price_lists.create', 'plan.limit:'.StockPlanFeatures::LIMIT_PRICE_LISTS]);
                        // PUT لا POST: نموذج التعديل يحمل `@method('PUT')`، فالطلب
                        // يصل مزوَّراً إلى PUT ولا يجد مساراً — «405 Method Not
                        // Allowed» في وجه المستخدم بدل الحفظ. وبقيّة شاشات الوحدة
                        // (المنتجات، الأصناف، الوحدات، البراندات، المجموعات) كلّها
                        // PUT، فهذه وحدها كانت شاذّة.
                        Route::put('/update/{id}', [PriceListController::class, 'update'])
                            ->name('price_list.update')
                            ->middleware('permission.role:stock.price_lists.edit');
                        Route::get('/delete/{id}', [PriceListController::class, 'delete'])
                            ->name('price_list.delete')
                            ->middleware('permission.role:stock.price_lists.delete');
                        Route::get('/delete_product/{id}', [PriceListController::class, 'delete_product'])
                            ->name('price_list.delete_product')
                            ->middleware('permission.role:stock.price_lists.manage_products');
                        Route::post('/add_product/{id}', [PriceListController::class, 'add_product'])
                            ->name('price_list.add_product')
                            ->middleware('permission.role:stock.price_lists.manage_products');
                        Route::get('/search', [PriceListController::class, 'search'])
                            ->name('price_list.search')
                            ->middleware('permission.role:stock.price_lists.view');
                        Route::get('/export', [PriceListController::class, 'export'])
                            ->name('price_list.export')
                            ->middleware('permission.role:stock.price_lists.export');
                        Route::post('/import', [PriceListController::class, 'import'])
                            ->name('price_list.import')
                            ->middleware('permission.role:stock.price_lists.import');
                    });

                    // ===== Inventory Settings =====
                    Route::prefix('inventory_settings')->middleware('plan:'.StockPlanFeatures::STOREHOUSES)->group(function () {
                        Route::get('/index', [InventorySettingsController::class, 'index'])
                            ->name('inventory_settings.index')
                            ->middleware('permission.role:stock.settings.view');
                        Route::get('/general', [InventorySettingsController::class, 'general'])
                            ->name('inventory_settings.general')
                            ->middleware('permission.role:stock.settings.general');
                        Route::post('/store', [InventorySettingsController::class, 'store'])
                            ->name('inventory_settings.store')
                            ->middleware('permission.role:stock.settings.general');
                        Route::get('/employee_default_warehouse', [InventorySettingsController::class, 'employee_default_warehouse'])
                            ->name('inventory_settings.employee_default_warehouse')
                            ->middleware('permission.role:stock.settings.employee_warehouse');
                        Route::get('/employee_default_warehouse_create', [InventorySettingsController::class, 'employee_default_warehouse_create'])
                            ->name('inventory_settings.employee_default_warehouse_create')
                            ->middleware('permission.role:stock.settings.employee_warehouse');
                        Route::post('/employee_default_warehouse_store', [InventorySettingsController::class, 'employee_default_warehouse_store'])
                            ->name('inventory_settings.employee_default_warehouse_store')
                            ->middleware('permission.role:stock.settings.employee_warehouse');
                        Route::get('/employee_default_warehouse_delete/{id}', [InventorySettingsController::class, 'employee_default_warehouse_delete'])
                            ->name('inventory_settings.employee_default_warehouse_delete')
                            ->middleware('permission.role:stock.settings.employee_warehouse');
                        Route::get('/employee_default_warehouse_show/{id}', [InventorySettingsController::class, 'employee_default_warehouse_show'])
                            ->name('inventory_settings.employee_default_warehouse_show')
                            ->middleware('permission.role:stock.settings.employee_warehouse');
                        Route::get('/employee_default_warehouse_edit/{id}', [InventorySettingsController::class, 'employee_default_warehouse_edit'])
                            ->name('inventory_settings.employee_default_warehouse_edit')
                            ->middleware('permission.role:stock.settings.employee_warehouse');
                        Route::post('/employee_default_warehouse_update/{id}', [InventorySettingsController::class, 'employee_default_warehouse_update'])
                            ->name('inventory_settings.employee_default_warehouse_update')
                            ->middleware('permission.role:stock.settings.employee_warehouse');
                    });

                    // ===== Product Settings =====
                    Route::prefix('product_settings')->middleware('plan:'.StockPlanFeatures::PRODUCTS)->group(function () {
                        Route::get('/index', [ProductsSettingsController::class, 'index'])
                            ->name('product_settings.index')
                            ->middleware('permission.role:stock.settings.products');
                        Route::get('/category', [ProductsSettingsController::class, 'category'])
                            ->name('product_settings.category')
                            ->middleware('permission.role:stock.settings.products');
                        Route::get('/default-taxes', [ProductsSettingsController::class, 'default_taxes'])
                            ->name('product_settings.default_taxes')
                            ->middleware('permission.role:stock.settings.products');
                        Route::get('/barcode-settings', [ProductsSettingsController::class, 'barcode_settings'])
                            ->name('product_settings.barcode_settings')
                            ->middleware('permission.role:stock.settings.products');
                    });

                    // ===== Barcode Settings =====
                    Route::prefix('barcode_settings')->middleware('plan:'.StockPlanFeatures::PRODUCTS)->group(function () {
                        Route::get('/index', [BarcodeSettingsController::class, 'index'])
                            ->name('barcode_settings.index')
                            ->middleware('permission.role:stock.barcodes.settings');
                        Route::post('/store', [BarcodeSettingsController::class, 'store'])
                            ->name('barcode_settings.store')
                            ->middleware('permission.role:stock.barcodes.settings');
                        Route::get('/get-settings', [BarcodeSettingsController::class, 'getSettings'])
                            ->name('barcode_settings.get_settings')
                            ->middleware('permission.role:stock.barcodes.settings');
                    });

                    // ===== Product Groups Management =====
                    Route::prefix('product_groups')->middleware('plan:'.StockPlanFeatures::CATEGORIES)->group(function () {
                        Route::get('/index', [ProductGroupController::class, 'index'])
                            ->name('product_groups.index')
                            ->middleware('permission.role:stock.product_groups.view');
                        Route::get('/create', [ProductGroupController::class, 'create'])
                            ->name('product_groups.create')
                            ->middleware('permission.role:stock.product_groups.create');
                        Route::post('/store', [ProductGroupController::class, 'store'])
                            ->name('product_groups.store')
                            ->middleware('permission.role:stock.product_groups.create');
                        Route::get('/edit/{id}', [ProductGroupController::class, 'edit'])
                            ->name('product_groups.edit')
                            ->middleware('permission.role:stock.product_groups.edit');
                        Route::put('/update/{id}', [ProductGroupController::class, 'update'])
                            ->name('product_groups.update')
                            ->middleware('permission.role:stock.product_groups.edit');
                        Route::delete('/delete/{id}', [ProductGroupController::class, 'destroy'])
                            ->name('product_groups.delete')
                            ->middleware('permission.role:stock.product_groups.delete');
                        Route::post('/toggle-status/{id}', [ProductGroupController::class, 'toggleStatus'])
                            ->name('product_groups.toggleStatus')
                            ->middleware('permission.role:stock.product_groups.toggle_status');
                        Route::get('/data', [ProductGroupController::class, 'getData'])
                            ->name('product_groups.getData')
                            ->middleware('permission.role:stock.product_groups.view');
                    });

                    // ===== Inventory Management =====
                    Route::prefix('inventory_management')->middleware('plan:'.StockPlanFeatures::INVENTORY_ADJUSTMENT)->group(function () {
                        Route::get('/index', [InventoryManagementController::class, 'index'])
                            ->name('inventory_management.index')
                            ->middleware('permission.role:stock.adjustments.view');
                        Route::get('/create', [InventoryManagementController::class, 'create'])
                            ->name('inventory_management.create')
                            ->middleware(['permission.role:stock.adjustments.create', 'plan.limit:'.StockPlanFeatures::LIMIT_ADJUSTMENTS]);
                        Route::get('/inventory/do/{id}', [InventoryManagementController::class, 'doStock'])
                            ->name('inventory.do_stock')
                            ->middleware('permission.role:stock.adjustments.count');
                        Route::post('/inventory/store', [InventoryManagementController::class, 'store'])
                            ->name('inventory.store')
                            ->middleware(['permission.role:stock.adjustments.create', 'plan.limit:'.StockPlanFeatures::LIMIT_ADJUSTMENTS]);
                        Route::post('/inventory/save/{id}', [InventoryManagementController::class, 'saveFinal'])
                            ->name('inventory.save_final')
                            ->middleware('permission.role:stock.adjustments.count');
                        Route::get('/inventory/show/{id}', [InventoryManagementController::class, 'show'])
                            ->name('inventory.show')
                            ->middleware('permission.role:stock.adjustments.show');
                        Route::get('/inventory/adjustment/{id}', [InventoryManagementController::class, 'adjustment'])
                            ->name('inventory.adjustment')
                            ->middleware('permission.role:stock.adjustments.approve');
                        Route::get('/inventory/cancel/adjustment/{id}', [InventoryManagementController::class, 'Canceladjustment'])
                            ->name('inventory.Canceladjustment')
                            ->middleware('permission.role:stock.adjustments.cancel');
                        Route::get('/inventory/edit/{id}', [InventoryManagementController::class, 'edit'])
                            ->name('inventory.edit')
                            ->middleware('permission.role:stock.adjustments.edit');
                        Route::put('/inventory/edit/{id}', [InventoryManagementController::class, 'update'])
                            ->name('inventory.update')
                            ->middleware('permission.role:stock.adjustments.edit');
                        Route::post('/inventory/export/{id}', [InventoryManagementController::class, 'export'])
                            ->name('inventory.export')
                            ->middleware('permission.role:stock.adjustments.export');
                        Route::post('/inventory/{id}/status', [InventoryManagementController::class, 'updateStatus'])
                            ->name('inventory.update_status')
                            ->middleware('permission.role:stock.adjustments.approve');
                        Route::get('/inventory/delete/{id}', [InventoryManagementController::class, 'delete'])
                            ->name('inventory.delete')
                            ->middleware('permission.role:stock.adjustments.delete');
                    });

                    // ===== Store Permits Management =====
                    Route::prefix('store_permits_management')->middleware('plan:'.StockPlanFeatures::WAREHOUSE_PERMITS)->group(function () {
                        Route::get('/index', [StorePermitsManagementController::class, 'index'])
                            ->name('store_permits_management.index')
                            ->middleware('permission.role:stock.permits.view');
                        Route::get('/show/{id}', [StorePermitsManagementController::class, 'show'])
                            ->name('store_permits_management.show')
                            ->middleware('permission.role:stock.permits.show');
                        Route::get('/create', [StorePermitsManagementController::class, 'create'])
                            ->name('store_permits_management.create')
                            ->middleware(['permission.role:stock.permits.create_addition', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                        Route::get('/manual_disbursement', [StorePermitsManagementController::class, 'manual_disbursement'])
                            ->name('store_permits_management.manual_disbursement')
                            ->middleware(['permission.role:stock.permits.create_disbursement', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                        Route::get('/manual_conversion', [StorePermitsManagementController::class, 'manual_conversion'])
                            ->name('store_permits_management.manual_conversion')
                            ->middleware(['permission.role:stock.permits.create_transfer', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                        Route::get('/opening_balance', [StorePermitsManagementController::class, 'opening_balance'])
                            ->name('store_permits_management.opening_balance')
                            ->middleware(['permission.role:stock.permits.create_opening', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                        Route::post('/store', [StorePermitsManagementController::class, 'store'])
                            ->name('store_permits_management.store')
                            ->middleware(['permission.role:stock.permits.create_addition', 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS]);
                        Route::get('/edit/{id}', [StorePermitsManagementController::class, 'edit'])
                            ->name('store_permits_management.edit')
                            ->middleware('permission.role:stock.permits.edit');
                        Route::post('/update/{id}', [StorePermitsManagementController::class, 'update'])
                            ->name('store_permits_management.update')
                            ->middleware('permission.role:stock.permits.edit');
                        Route::delete('/delete/{id}', [StorePermitsManagementController::class, 'delete'])
                            ->name('store_permits_management.delete')
                            ->middleware('permission.role:stock.permits.delete');
                        Route::post('/{id}/approve', [StorePermitsManagementController::class, 'approve'])
                            ->name('store_permits_management.approve')
                            ->middleware('permission.role:stock.permits.approve');
                        Route::post('/{id}/cancel-approval', [StorePermitsManagementController::class, 'cancelApproval'])
                            ->name('store_permits_management.cancel_approval')
                            ->middleware('permission.role:stock.permits.cancel_approval');
                        Route::post('/{id}/reject', [StorePermitsManagementController::class, 'reject'])
                            ->name('store_permits_management.reject')
                            ->middleware('permission.role:stock.permits.reject');
                        Route::get('/go', [StorePermitsManagementController::class, 'go'])
                            ->name('store_permits_management.go')
                            ->middleware('permission.role:stock.permits.view');
                        Route::get('/pending', [StorePermitsManagementController::class, 'pendingPermits'])
                            ->name('store_permits_management.pending')
                            ->middleware('permission.role:stock.permits.view');
                        Route::get('/approved', [StorePermitsManagementController::class, 'approvedPermits'])
                            ->name('store_permits_management.approved')
                            ->middleware('permission.role:stock.permits.view');
                        Route::get('/rejected', [StorePermitsManagementController::class, 'rejectedPermits'])
                            ->name('store_permits_management.rejected')
                            ->middleware('permission.role:stock.permits.view');
                        Route::get('/export', [StorePermitsManagementController::class, 'exportPermits'])
                            ->name('store_permits_management.export')
                            ->middleware('permission.role:stock.permits.export');
                        Route::post('/import', [StorePermitsManagementController::class, 'importPermits'])
                            ->name('store_permits_management.import')
                            ->middleware('permission.role:stock.permits.import');
                        Route::get('/print/{id}/{type?}', [StorePermitsManagementController::class, 'print'])
                            ->name('store_permits_management.print')
                            ->middleware('permission.role:stock.permits.print');
                        Route::get('/test-export', [StorePermitsManagementController::class, 'testExport'])
                            ->name('store_permits_management.test_export')
                            ->middleware('permission.role:stock.permits.export');
                        Route::get('/stats', [StorePermitsManagementController::class, 'getStats'])
                            ->name('store_permits_management.stats')
                            ->middleware('permission.role:stock.permits.view');
                        Route::get('/search', [StorePermitsManagementController::class, 'search'])
                            ->name('store_permits_management.search')
                            ->middleware('permission.role:stock.permits.view');
                    });

    Route::prefix('StorHouseReport')->middleware('plan:'.StockPlanFeatures::REPORTS)->group(function () {
        Route::get('/index', [InventorySheetReportController::class, 'indexReport'])->name('StorHouseReport.index')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/inventorySheet', [InventorySheetReportController::class, 'index'])->name('StorHouseReport.inventorySheet')
                            ->middleware('permission.role:stock.reports.view');

        // جلب بيانات ورقة الجرد عبر AJAX
        Route::get('/inventory-sheet-ajax', [InventorySheetReportController::class, 'inventorySheetAjax'])->name('StorHouseReport.inventorySheetAjax')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/summaryInventory', [SummaryInventoryReportController::class, 'index'])->name('StorHouseReport.summaryInventory')
                            ->middleware('permission.role:stock.reports.view');

        Route::get('/detailedMovementInventory', [DetailedMovementReportController::class, 'index'])->name('StorHouseReport.detailedMovementInventory')
                            ->middleware('permission.role:stock.reports.view');

        Route::get('/search/products', [InventoryMovementDetailReportController::class, 'searchProducts'])->name('StorHouseReport.searchProducts')
                            ->middleware('permission.role:stock.reports.view');

        Route::get('/valueInventory', [ValueInventoryReportController::class, 'index'])->name('StorHouseReport.valueInventory')
                            ->middleware('permission.role:stock.reports.view');

        Route::get('/inventoryBlance', [InventoryBalanceReportController::class, 'index'])->name('StorHouseReport.inventoryBlance')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/inventory-balance-ajax', [InventoryBalanceReportController::class, 'inventoryBlanceAjax'])->name('StorHouseReport.inventoryBlanceAjax')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/trialBalance', [TrialBalanceReportController::class, 'index'])->name('StorHouseReport.trialBalance')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/trial-balance-ajax', [TrialBalanceReportController::class, 'trialBalanceAjax'])->name('StorHouseReport.trialBalanceAjax')
                            ->middleware('permission.role:stock.reports.view');

        Route::get('/Inventory_mov_det_product', [InventoryMovementDetailReportController::class, 'index'])->name('StorHouseReport.Inventory_mov_det_product')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/inventory-movement-detail-ajax', [InventoryMovementDetailReportController::class, 'inventoryMovementDetailAjax'])->name('StorHouseReport.Inventory_mov_det_product_ajax')
                            ->middleware('permission.role:stock.reports.view');
        
        // تقرير رصيد المستودع
        Route::get('/warehouse-balance', [WarehouseBalanceReportController::class, 'index'])->name('StorHouseReport.warehouseBalance')
                            ->middleware('permission.role:stock.reports.view');
        // تفاصيل القيد المحاسبي
        Route::get('/warehouse-balance/journal-entry/{journalEntryId}/details', [WarehouseBalanceReportController::class, 'showJournalEntryDetails'])->name('StorHouseReport.warehouseBalance.journalEntryDetails')
                            ->middleware('permission.role:stock.reports.view');
    });

    // تقرير الحركة التفصيلية البسيط
    Route::prefix('StockReport')->middleware('plan:'.StockPlanFeatures::REPORTS)->group(function () {
        Route::get('/simple-detailed-movement', [\Modules\Stock\Http\Controllers\Reports\SimpleDetailedMovementController::class, 'index'])->name('StockReport.simpleDetailedMovement')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/get-detailed-movement', [\Modules\Stock\Http\Controllers\Reports\SimpleDetailedMovementController::class, 'getData'])->name('StockReport.getDetailedMovement')
                            ->middleware('permission.role:stock.reports.view');
        Route::get('/export-detailed-movement', [\Modules\Stock\Http\Controllers\Reports\SimpleDetailedMovementController::class, 'exportExcel'])->name('StockReport.exportDetailedMovement')
                            ->middleware('permission.role:stock.reports.view');
    });

                    // ===== Comprehensive Inventory Report =====
                    Route::prefix('comprehensive-inventory')->middleware('plan:'.StockPlanFeatures::REPORTS)->group(function () {
                        Route::get('/report', [ComprehensiveInventoryReportController::class, 'index'])->name('comprehensiveInventoryReport.index')
                            ->middleware('permission.role:stock.reports.view');
                        Route::get('/data', [ComprehensiveInventoryReportController::class, 'getComprehensiveData'])->name('comprehensiveInventoryReport.getData')
                            ->middleware('permission.role:stock.reports.view');
                        Route::get('/product/{id}/details', [ComprehensiveInventoryReportController::class, 'getProductDetails'])->name('comprehensiveInventoryReport.getProductDetails')
                            ->middleware('permission.role:stock.reports.view');
                        Route::get('/export/{format}', [ComprehensiveInventoryReportController::class, 'exportReport'])->name('comprehensiveInventoryReport.export')
                            ->middleware('permission.role:stock.reports.view');

                        // طرق جلب البيانات من InventoryAdjustment و InventoryItem
                        Route::get('/adjustments', [ComprehensiveInventoryReportController::class, 'getInventoryAdjustmentData'])->name('comprehensiveInventoryReport.getAdjustments')
                            ->middleware('permission.role:stock.reports.view');
                        Route::get('/items', [ComprehensiveInventoryReportController::class, 'getInventoryItems'])->name('comprehensiveInventoryReport.getItems')
                            ->middleware('permission.role:stock.reports.view');
                    });




                });
        },
    );

    Route::fallback(function () {
        // كانت `view()` وحدها تُرجع 200 لصفحةٍ عنوانها «404» — أي طلبٍ لا
        // يطابق مساراً حقيقياً (كخللٍ عابر في تسجيل مسارات اللغة عند إقلاع
        // الخادم) يظهر متصفّحاً كصفحةٍ ناجحة فارغة، بلا استثناءٍ يُسجَّل
        // في أي سجلّ — فيختفي العطل الحقيقي.
        return response()->view('errors.404', [], 404);
    });
});
