<?php

// كان الاستيراد من `App\Http\Controllers\Reports` — وهو مجلّدٌ
// لا وجود له في المشروع. فالمسار يُنتج 500 عند أوّل طلب، و`route:list`
// يسقط كلّه عند تحليله. الفئة موجودة في وحدة التقارير.
use Modules\Reports\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;
use Modules\Client\Http\Controllers\Customers\CustomerReportController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

require __DIR__ . '/auth.php';

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath','check.branch']
    ],
    function () {
        Route::prefix('reports/customers')->middleware('auth')->group(function () {
            Route::get('/', [CustomerReportController::class, 'index'])->name('reports.customers.index');
            Route::get('/aging-invoices', [CustomerReportController::class, 'agingInvoices'])->name('reports.customers.agingInvoices');
            Route::get('/aging-ledger', [CustomerReportController::class, 'agingLedger'])->name('reports.customers.agingLedger');
            Route::get('/customer-directory', [CustomerReportController::class, 'customerDirectory'])->name('reports.customers.customerDirectory');
            Route::get('/customer-balances', [CustomerReportController::class, 'customerBalances'])->name('reports.customers.customerBalances');
            Route::get('/customer-sales', [CustomerReportController::class, 'customerSales'])->name('reports.customers.customerSales');
            Route::get('/customer-payments', [CustomerReportController::class, 'customerPayments'])->name('reports.customers.customerPayments');
            Route::get('/customer-statements', [CustomerReportController::class, 'customerStatements'])->name('reports.customers.customerStatements');
            Route::get('/customer-appointments', [CustomerReportController::class, 'customerAppointments'])->name('reports.customers.customerAppointments');
            Route::get('/customer-installments', [CustomerReportController::class, 'customerInstallments'])->name('reports.customers.customerInstallments');
            Route::get('/customer-account-statement', [CustomerReportController::class, 'customerAccountStatement'])->name('client.report.customer_account_statement');
            Route::get('/customer-account-statement-ajax', [CustomerReportController::class, 'customerAccountStatementAjax'])->name('customer-report.statement-ajax');
            Route::get('/statement/{id}', [CustomerReportController::class, 'getCustomerStatement'])->name('customer-report.statement');
        });
    }
);
