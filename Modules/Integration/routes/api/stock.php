<?php

use App\Constants\planFeatures\StockPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\Stock\PermitController;
use Modules\Integration\Http\Controllers\Api\Stock\PriceListController;
use Modules\Integration\Http\Controllers\Api\Stock\StatementController;
use Modules\Integration\Http\Controllers\Api\Stock\WarehouseController;

/*
|--------------------------------------------------------------------------
| المخزون — /api/integration/v1/stock/...
|--------------------------------------------------------------------------
|
| يُحمَّل من routes/api.php داخل مجموعة (auth:sanctum + EnsureEmployeeUser).
| لكل مسار صلاحيته وميزة الباقة (`plan:`) وحدّ الاستخدام (`plan.limit:`)
| نفسها في مسارات المخزون على الويب (Modules/Stock/routes/web.php)، ومفتاح
| التطبيق في «التطبيقات» (`module:inventory_management`).
|
*/

Route::prefix('stock')->name('stock.')->middleware('module:inventory_management')->group(function () {

    // ── المستودعات (عرض فقط) ────────────────────────────────────────
    Route::prefix('warehouses')->name('warehouses.')->controller(WarehouseController::class)
        ->middleware('plan:'.StockPlanFeatures::STOREHOUSES)->group(function () {
            Route::get('/', 'index')->middleware('permission.role:stock.warehouses.view')->name('index');
            Route::get('{id}', 'show')->whereNumber('id')
                ->middleware(['permission.role:stock.warehouses.view', 'permission.role:stock.warehouses.show'])->name('show');
            Route::get('{id}/movements', 'movements')->whereNumber('id')
                ->middleware('permission.role:stock.reports.summary_movement')->name('movements');
        });

    // ── الأذون المخزنية ─────────────────────────────────────────────
    Route::prefix('permits')->name('permits.')->controller(PermitController::class)
        ->middleware('plan:'.StockPlanFeatures::WAREHOUSE_PERMITS)->group(function () {
            $anyCreate = 'permission.role:stock.permits.create_addition|stock.permits.create_disbursement|stock.permits.create_transfer|stock.permits.create_opening';

            Route::get('/', 'index')->middleware('permission.role:stock.permits.view')->name('index');
            // صلاحية النوع بعينه تُفحص داخل createData() وstore() وفق type الفعلي
            Route::get('create-data', 'createData')->middleware($anyCreate)->name('create-data');
            Route::get('product-stock/{warehouseId}/{productId}', 'productStock')
                ->whereNumber(['warehouseId', 'productId'])->middleware('permission.role:stock.permits.view')->name('product-stock');
            Route::get('{id}', 'show')->whereNumber('id')->middleware('permission.role:stock.permits.show')->name('show');
            Route::post('/', 'store')->middleware([$anyCreate, 'plan.limit:'.StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS])->name('store');
            Route::put('{id}', 'update')->whereNumber('id')->middleware('permission.role:stock.permits.edit')->name('update');
            Route::delete('{id}', 'destroy')->whereNumber('id')->middleware('permission.role:stock.permits.delete')->name('delete');
            Route::post('{id}/approve', 'approve')->whereNumber('id')->middleware('permission.role:stock.permits.approve')->name('approve');
            Route::post('{id}/reject', 'reject')->whereNumber('id')->middleware('permission.role:stock.permits.reject')->name('reject');
            Route::post('{id}/cancel-approval', 'cancelApproval')->whereNumber('id')
                ->middleware('permission.role:stock.permits.cancel_approval')->name('cancel-approval');
        });

    // ── قوائم الأسعار ───────────────────────────────────────────────
    Route::prefix('price-lists')->name('price-lists.')->controller(PriceListController::class)
        ->middleware('plan:'.StockPlanFeatures::PRICE_LISTS)->group(function () {
            Route::get('/', 'index')->middleware('permission.role:stock.price_lists.view')->name('index');
            Route::get('{id}', 'show')->whereNumber('id')->middleware('permission.role:stock.price_lists.show')->name('show');
            Route::post('/', 'store')
                ->middleware(['permission.role:stock.price_lists.create', 'plan.limit:'.StockPlanFeatures::LIMIT_PRICE_LISTS])->name('store');
            Route::put('{id}', 'update')->whereNumber('id')->middleware('permission.role:stock.price_lists.edit')->name('update');
            Route::delete('{id}', 'destroy')->whereNumber('id')->middleware('permission.role:stock.price_lists.delete')->name('delete');
            Route::post('{id}/products', 'addProduct')->whereNumber('id')
                ->middleware('permission.role:stock.price_lists.manage_products')->name('products.store');
            Route::delete('products/{itemId}', 'removeProduct')->whereNumber('itemId')
                ->middleware('permission.role:stock.price_lists.manage_products')->name('products.delete');
        });

    // ── الكشف المخزني ───────────────────────────────────────────────
    Route::prefix('statement')->name('statement.')->controller(StatementController::class)
        ->middleware(['plan:'.StockPlanFeatures::STOREHOUSES, 'permission.role:stock.reports.employee_statement'])->group(function () {
            Route::get('warehouses', 'warehouses')->name('warehouses');
            Route::get('/', 'show')->name('show');
        });
});
