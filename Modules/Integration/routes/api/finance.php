<?php

use App\Constants\planFeatures\AccountPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\Finance\ClientAccountController;
use Modules\Integration\Http\Controllers\Api\Finance\ExpenseController;
use Modules\Integration\Http\Controllers\Api\Finance\ReceiptController;
use Modules\Integration\Http\Controllers\Api\Finance\SummaryController;
use Modules\Integration\Http\Controllers\Api\Finance\TreasuryController;

/*
|--------------------------------------------------------------------------
| الماليّة — /api/integration/v1/finance/...
|--------------------------------------------------------------------------
|
| يُحمَّل من routes/api.php داخل مجموعة (auth:sanctum + EnsureEmployeeUser).
| كل مسار يحمل صلاحيته مطابقةً حرفياً لِما يفرضه كنترولر الويب المقابل —
| IncomesController / ExpensesController / TreasuryController — ومعها ما
| تفرضه مسارات الويب (Modules/Account/routes/web.php): مفتاح التطبيق في
| «التطبيقات» (`module:finance`)، ميزة الباقة لكل قسم (`plan:`)، وحدّ
| الاستخدام عند الإنشاء (`plan.limit:`).
|
*/

Route::prefix('finance')->name('finance.')->middleware('module:finance')->group(function () {
    Route::get('summary', SummaryController::class)
        ->middleware([
            'plan:'.AccountPlanFeatures::INCOMES.'|'.AccountPlanFeatures::EXPENSES,
            'permission.role:account.incomes.view|account.expenses.view',
        ])->name('summary');

    Route::prefix('receipts')->name('receipts.')->controller(ReceiptController::class)
        ->middleware('plan:'.AccountPlanFeatures::INCOMES)->group(function () {
            Route::get('/', 'index')->middleware('permission.role:account.incomes.view')->name('index');
            Route::get('create-data', 'createData')->middleware('permission.role:account.incomes.create')->name('create-data');
            Route::get('{id}', 'show')->whereNumber('id')->middleware('permission.role:account.incomes.view')->name('show');
            Route::post('/', 'store')
                ->middleware(['permission.role:account.incomes.create', 'plan.limit:'.AccountPlanFeatures::LIMIT_INCOMES])->name('store');
            Route::put('{id}', 'update')->whereNumber('id')->middleware('permission.role:account.incomes.edit')->name('update');
            Route::post('{id}/cancel', 'cancel')->whereNumber('id')->middleware('permission.role:account.incomes.cancel')->name('cancel');
        });

    Route::prefix('expenses')->name('expenses.')->controller(ExpenseController::class)
        ->middleware('plan:'.AccountPlanFeatures::EXPENSES)->group(function () {
            Route::get('/', 'index')->middleware('permission.role:account.expenses.view')->name('index');
            Route::get('create-data', 'createData')->middleware('permission.role:account.expenses.create')->name('create-data');
            Route::get('{id}', 'show')->whereNumber('id')->middleware('permission.role:account.expenses.view')->name('show');
            Route::post('/', 'store')
                ->middleware(['permission.role:account.expenses.create', 'plan.limit:'.AccountPlanFeatures::LIMIT_EXPENSES])->name('store');
            Route::put('{id}', 'update')->whereNumber('id')->middleware('permission.role:account.expenses.edit')->name('update');
            Route::post('{id}/cancel', 'cancel')->whereNumber('id')->middleware('permission.role:account.expenses.cancel')->name('cancel');
            Route::delete('{id}', 'destroy')->whereNumber('id')->middleware('permission.role:account.expenses.delete')->name('delete');
        });

    Route::controller(TreasuryController::class)->middleware('plan:'.AccountPlanFeatures::TREASURY)->group(function () {
        Route::get('treasuries', 'index')->middleware('permission.role:account.treasury.view')->name('treasuries');
        Route::get('treasuries/{treasuryId}/transfers', 'transfers')
            ->whereNumber('treasuryId')->middleware('permission.role:account.treasury_transfers.view')->name('treasuries.transfers');
        Route::post('treasury-transfers', 'storeTransfer')
            ->middleware('permission.role:account.treasury_transfers.create')->name('treasury-transfers.store');
        Route::put('treasury-transfers/{id}', 'updateTransfer')
            ->whereNumber('id')->middleware('permission.role:account.treasury_transfers.edit')->name('treasury-transfers.update');
    });

    // العملاء وكشوف حساباتهم جزءٌ من سندات القبض (نفس نطاق حساباتها)
    Route::controller(ClientAccountController::class)->middleware('plan:'.AccountPlanFeatures::INCOMES)->group(function () {
        Route::get('clients', 'index')->middleware('permission.role:account.incomes.view')->name('clients');
        Route::get('clients/{clientId}/statement', 'statement')
            ->whereNumber('clientId')->middleware('permission.role:account.incomes.view')->name('clients.statement');
    });
});
