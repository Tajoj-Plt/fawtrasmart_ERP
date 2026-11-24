<?php

use Illuminate\Support\Facades\Route;
use Modules\Client\Http\Controllers\Customers\CustomerReportController;
use Modules\Client\Http\Controllers\Customers\CustomerBalancesController;
use Modules\Client\Http\Controllers\Customers\CustomerDebtsController;
use Modules\Client\Http\Controllers\Customers\CustomerDebtAgingController;
use Modules\Client\Http\Controllers\Customers\CustomerGuideController;
use Modules\Client\Http\Controllers\Customers\CustomerAppointmentsController;
use Modules\Client\Http\Controllers\Customers\CustomerInstallmentsController;
use Modules\Client\Http\Controllers\Customers\CustomerAccountStatementController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

require __DIR__ . '/auth.php';

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath','check.branch']
    ],
    function () {
        Route::prefix('reports/customers')->middleware('auth')->group(function () {
            // الصفحة الرئيسية للتقارير
            Route::get('/', [CustomerReportController::class, 'index'])->name('reports.customers.index');

            // تقرير الفروع والمناطق
            Route::get('/branches-regions', [CustomerDebtsController::class, 'getBranchesRegionData'])->name('reports.customers.branchesRegions');

            // تقارير الديون والفواتير
            Route::get('/debt-reconstruction', [CustomerDebtsController::class, 'debtReconstructionInv'])->name('reports.customers.debtReconstruction');
            Route::post('/invoice-debt-aging', [CustomerDebtsController::class, 'invoiceDebtAgingAjax'])->name('reports.customers.invoiceDebtAging');
            Route::get('/search-clients', [CustomerDebtsController::class, 'searchClients'])->name('reports.customers.searchClients');

            // تقرير أعمار الديون
            Route::get('/debt-aging-general', [CustomerDebtAgingController::class, 'debtAgingGeneralLedger'])->name('reports.customers.debtAgingGeneral');
            Route::get('/debt-aging-general-ajax', [CustomerDebtAgingController::class, 'debtAgingGeneralLedgerAjax'])->name('reports.customers.debtAgingGeneralAjax');

            // تقرير دليل العملاء
            Route::get('/customer-guide', [CustomerGuideController::class, 'customerGuide'])->name('reports.customers.customerGuide');
            Route::post('/customer-guide-ajax', [CustomerGuideController::class, 'customerGuideAjax'])->name('reports.customers.customerGuideAjax');
            Route::post('/customer-guide-map-ajax', [CustomerGuideController::class, 'customerGuideMapAjax'])->name('reports.customers.customerGuideMapAjax');
            Route::get('/neighborhoods', [CustomerGuideController::class, 'getNeighborhoods'])->name('reports.customers.neighborhoods');
            Route::get('/region-groups', [CustomerGuideController::class, 'getRegionGroups'])->name('reports.customers.regionGroups');
            Route::get('/cities', [CustomerGuideController::class, 'getCities'])->name('reports.customers.cities');

            // تقرير أرصدة العملاء
            Route::get('/customer-balances', [CustomerBalancesController::class, 'customerBalances'])->name('reports.customers.customerBalances');
            Route::post('/customer-balances-ajax', [CustomerBalancesController::class, 'customerBalancesAjax'])->name('reports.customers.customerBalancesAjax');

            // تقرير المواعيد
            Route::get('/customer-appointments', [CustomerAppointmentsController::class, 'customerAppointments'])->name('reports.customers.customerAppointments');
            Route::post('/customer-appointments-ajax', [CustomerAppointmentsController::class, 'customerAppointmentsAjax'])->name('reports.customers.customerAppointmentsAjax');

            // تقرير الأقساط
            Route::get('/customer-installments', [CustomerInstallmentsController::class, 'customerInstallments'])->name('reports.customers.customerInstallments');
            Route::post('/customer-installments-ajax', [CustomerInstallmentsController::class, 'customerInstallmentsAjax'])->name('reports.customers.customerInstallmentsAjax');

            // تقرير كشف الحساب
            Route::get('/customer-account-statement', [CustomerAccountStatementController::class, 'customerAccountStatement'])->name('client.report.customer_account_statement');
            Route::post('/customer-account-statement-ajax', [CustomerAccountStatementController::class, 'customerAccountStatementAjax'])->name('customer-report.statement-ajax');
        });
    }
);
