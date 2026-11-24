<?php

use App\Constants\planFeatures\AccountPlanFeatures as Plan;
use App\Constants\planFeatures\HrPlanFeatures as HrPlan;
use App\Constants\planFeatures\StockPlanFeatures as StockPlan;
use App\Constants\planFeatures\SalesPlanFeatures as SalesPlan;
use App\Http\Controllers\Mobile\MobileAccountingFormsController;
use App\Http\Controllers\Mobile\MobileClientFormsController;
use App\Http\Controllers\Mobile\MobileStockFormsController;
use App\Http\Controllers\Mobile\MobileSalesFormsController;
use App\Http\Middleware\MobileApiContext;
use Illuminate\Support\Facades\Route;
use Modules\Client\Http\Controllers\ClientController;
// متحكّمات الجوال في وحدة التكامل (Integration): الطباعة بقوالب الويب، وتسليم الفواتير والأرصدة
use Modules\Integration\Http\Controllers\Mobile\MobileAttendanceController;
use Modules\Integration\Http\Controllers\Mobile\MobileInvoiceTransfersController;
use Modules\Integration\Http\Controllers\Mobile\MobileSalesDashboardController;
use Modules\Integration\Http\Controllers\Mobile\MobileSalesPrintController;
use Modules\Client\Http\Controllers\CompetitorController;
use Modules\Client\Http\Controllers\InvoiceTransferController;
use Modules\Stock\Http\Controllers\Stock\ProductsController;
use Modules\Stock\Http\Controllers\Stock\StorehouseController;
use Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController;
use Modules\Sales\Http\Controllers\InvoicesController;
use Modules\Sales\Http\Controllers\QuoteController;
use Modules\Sales\Http\Controllers\SalesPaymentController;
use Modules\Account\Http\Controllers\AccountsChartController;
use Modules\Account\Http\Controllers\Cheques\CheckBooksController;
use Modules\Account\Http\Controllers\Cheques\PayableChequesController;
use Modules\Account\Http\Controllers\Cheques\ReceivedChequesController;
use Modules\Account\Http\Controllers\CostCentersController;
use Modules\Account\Http\Controllers\Finance\ExpensesController;
use Modules\Account\Http\Controllers\Finance\IncomesController;
use Modules\Account\Http\Controllers\Finance\TreasuryController;
use Modules\Account\Http\Controllers\JournalEntryController;

/*
|--------------------------------------------------------------------------
| واجهة تطبيق الجوال للحسابات — إنشاء السندات والقيود والسجلات
|--------------------------------------------------------------------------
|
| كل مسار هنا يستدعي **نفس متحكّم الويب** بنفس سلسلة الوسطاء (مفتاح التطبيق + الباقة + الصلاحية
| + حدّ الباقة)، فلا تنقسم القواعد المحاسبية (الترقيم والقيود والأرصدة والفترات) بين نسختين.
| الفرق الوحيد: المصادقة برمز Sanctum بدل الجلسة، ووسيط `MobileApiContext` الذي يُهيّئ الطلب
| ويترجم التحويلات إلى JSON.
|
| تصدر الرموز خدمةُ الجوال (MobileAppBackend) لكل مستخدم بعد تسجيل دخوله هناك.
*/

Route::middleware([MobileApiContext::class, 'auth:sanctum'])->prefix('accounting')->group(function () {
    // ─── قوائم الاختيار والمساعدات ───
    Route::get('/forms/{form}/options', [MobileAccountingFormsController::class, 'options']);
    Route::get('/forms/next-code/{kind}', [MobileAccountingFormsController::class, 'nextCode']);
    Route::get('/forms/checkbook/{id}', [MobileAccountingFormsController::class, 'checkbookInfo'])->whereNumber('id');

    // ─── المالية ───
    Route::post('/expenses', [ExpensesController::class, 'store'])
        ->middleware(['module:finance', 'plan:'.Plan::EXPENSES, 'permission.role:account.expenses.create', 'plan.limit:'.Plan::LIMIT_EXPENSES]);
    Route::post('/incomes', [IncomesController::class, 'store'])
        ->middleware(['module:finance', 'plan:'.Plan::INCOMES, 'permission.role:account.incomes.create', 'plan.limit:'.Plan::LIMIT_INCOMES]);
    // طباعة سند القبض الماليّ بقوالب الويب — كرابط «طباعة» في شاشة السند (`incomes.print`)
    Route::get('/print/income/templates', [MobileSalesPrintController::class, 'templates'])->defaults('kind', 'income')
        ->middleware(['module:finance', 'plan:'.Plan::INCOMES, 'permission.role:account.incomes.print']);
    Route::get('/print/income/{id}', [MobileSalesPrintController::class, 'income'])->whereNumber('id')
        ->middleware(['module:finance', 'plan:'.Plan::INCOMES, 'permission.role:account.incomes.print']);
    Route::post('/treasuries', [TreasuryController::class, 'store'])
        ->middleware(['module:finance', 'plan:'.Plan::TREASURY, 'permission.role:account.treasury.create', 'plan.limit:'.Plan::LIMIT_TREASURIES]);

    // ─── الحسابات العامة ───
    Route::post('/journal-entries', [JournalEntryController::class, 'store'])
        ->middleware(['module:general_accounts_journal_entries', 'plan:'.Plan::JOURNAL_ENTRIES, 'permission.role:account.journal.create', 'plan.limit:'.Plan::LIMIT_JOURNAL_ENTRIES]);
    Route::post('/chart-accounts', [AccountsChartController::class, 'store_account'])
        ->middleware(['module:general_accounts_journal_entries', 'plan:'.Plan::CHART_OF_ACCOUNTS, 'permission.role:account.chart.create']);
    Route::post('/cost-centers', [CostCentersController::class, 'store_account'])
        ->middleware(['module:general_accounts_journal_entries', 'plan:'.Plan::COST_CENTERS, 'permission.role:account.cost_centers.create', 'plan.limit:'.Plan::LIMIT_COST_CENTERS]);

    // ─── دورة الشيكات ───
    Route::post('/cheque-books', [CheckBooksController::class, 'store'])
        ->middleware(['module:cheque_cycle', 'plan:'.Plan::CHECK_BOOKS, 'permission.role:account.cheque_books.create', 'plan.limit:'.Plan::LIMIT_CHECK_BOOKS]);
    Route::post('/payable-cheques', [PayableChequesController::class, 'store'])
        ->middleware(['module:cheque_cycle', 'plan:'.Plan::PAYABLE_CHEQUES, 'permission.role:account.payable_cheques.create', 'plan.limit:'.Plan::LIMIT_PAYABLE_CHEQUES]);
    Route::post('/received-cheques', [ReceivedChequesController::class, 'store'])
        ->middleware(['module:cheque_cycle', 'plan:'.Plan::RECEIVED_CHEQUES, 'permission.role:account.received_cheques.create', 'plan.limit:'.Plan::LIMIT_RECEIVED_CHEQUES]);
});

/*
|--------------------------------------------------------------------------
| واجهة تطبيق الجوال للعملاء — إنشاء عميلٍ ومنافسٍ
|--------------------------------------------------------------------------
| لا حدود باقة على وحدة العملاء أصلاً (`ClientsPlanFeatures::hasPlanLimit()` ثابتة `false`).
*/
Route::middleware([MobileApiContext::class, 'auth:sanctum'])->prefix('client')->group(function () {
    Route::get('/forms/{form}/options', [MobileClientFormsController::class, 'options']);

    Route::post('/clients', [ClientController::class, 'store'])
        ->middleware(['module:customers', 'permission:clients_add_client']);
    Route::post('/competitors', [CompetitorController::class, 'store'])
        ->middleware(['module:customers', 'permission:clients_competitors_add']);

    // ─── تسليم واستلام الفواتير والأرصدة — شاشة الويب بياناتٍ، وإجراءاتُها دوالُّ متحكّمها نفسها ───
    Route::prefix('invoice-transfers')->middleware('module:customers')->group(function () {
        Route::get('/', [MobileInvoiceTransfersController::class, 'overview'])
            ->middleware('permission:clients_invoice_transfer_view');
        Route::get('/sendable-clients', [InvoiceTransferController::class, 'sendableClients'])
            ->middleware('permission:clients_invoice_transfer_view');
        // تبويب «تسليم واستلام الفواتير» في مساحة الزيارة لعميلٍ واحد
        Route::get('/client/{clientId}', [MobileInvoiceTransfersController::class, 'client'])->whereNumber('clientId')
            ->middleware('permission:clients_invoice_transfer_view');
        Route::post('/', [InvoiceTransferController::class, 'store'])
            ->middleware('permission:clients_invoice_transfer_send');
        Route::post('/balance', [InvoiceTransferController::class, 'storeBalance'])
            ->middleware('permission:clients_invoice_transfer_send');
        Route::post('/receive-all', [InvoiceTransferController::class, 'receiveAll'])
            ->middleware('permission:clients_invoice_transfer_respond');
        Route::post('/batch/{batch}/respond', [InvoiceTransferController::class, 'respondBatch'])
            ->middleware('permission:clients_invoice_transfer_respond');
        Route::post('/{id}/accept', [InvoiceTransferController::class, 'accept'])->whereNumber('id')
            ->middleware('permission:clients_invoice_transfer_respond');
        Route::post('/{id}/reject', [InvoiceTransferController::class, 'reject'])->whereNumber('id')
            ->middleware('permission:clients_invoice_transfer_respond');
        Route::post('/{id}/cancel', [MobileInvoiceTransfersController::class, 'cancel'])->whereNumber('id')
            ->middleware('permission:clients_invoice_transfer_cancel');
    });
});

/*
|--------------------------------------------------------------------------
| واجهة تطبيق الجوال للمخزون — إنشاء مستودعٍ ومنتجٍ وإذنٍ مخزنيّ
|--------------------------------------------------------------------------
| أذون المخزون: صلاحية الإنشاء تختلف بنوع الإذن وتُفحَص داخل `store()` نفسها
| (`authorizeCreatePermissionType()`) — لا صلاحيةً واحدةً هنا تناسب الأربعة كلَّها.
*/
Route::middleware([MobileApiContext::class, 'auth:sanctum'])->prefix('stock')->group(function () {
    Route::get('/forms/{form}/options', [MobileStockFormsController::class, 'options']);

    Route::post('/warehouses', [StorehouseController::class, 'store'])
        ->middleware(['module:inventory_management', 'plan:'.StockPlan::STOREHOUSES, 'permission.role:stock.warehouses.create', 'plan.limit:'.StockPlan::LIMIT_STOREHOUSES]);
    Route::post('/products', [ProductsController::class, 'store'])
        ->middleware(['module:inventory_management', 'plan:'.StockPlan::PRODUCTS, 'permission.role:stock.products.edit', 'plan.limit:'.StockPlan::LIMIT_PRODUCTS]);
    Route::post('/permits', [StorePermitsManagementController::class, 'store'])
        ->middleware(['module:inventory_management', 'plan:'.StockPlan::WAREHOUSE_PERMITS, 'plan.limit:'.StockPlan::LIMIT_WAREHOUSE_PERMITS]);
});

/*
|--------------------------------------------------------------------------
| واجهة تطبيق الجوال للمبيعات — إنشاء سند قبضٍ وفاتورةٍ وعرض سعر
|--------------------------------------------------------------------------
| فاتورة المبيعات هي الأثقل أثراً في كل الجسر: خصمٌ مخزونٍ وقيودٌ محاسبيةٌ تلقائية داخل
| `persistInvoice()` نفسها — لا شيء من ذلك يُعاد كتابته هنا، الجسر يستدعيها كما هي.
*/
Route::middleware([MobileApiContext::class, 'auth:sanctum'])->prefix('sales')->group(function () {
    Route::get('/forms/{form}/options', [MobileSalesFormsController::class, 'options']);

    Route::post('/payments', [SalesPaymentController::class, 'store'])
        ->middleware(['module:sales', 'plan:'.SalesPlan::PAYMENTS, 'permission:sales.payments.create', 'plan.limit:'.SalesPlan::LIMIT_PAYMENTS]);
    Route::post('/invoices', [InvoicesController::class, 'store'])
        ->middleware(['module:sales', 'plan:'.SalesPlan::INVOICES, 'permission:sales.invoices.create', 'plan.limit:'.SalesPlan::LIMIT_INVOICES]);
    Route::post('/quotes', [QuoteController::class, 'store'])
        ->middleware(['module:sales', 'plan:'.SalesPlan::QUOTES, 'permission:sales.quotes.create', 'plan.limit:'.SalesPlan::LIMIT_QUOTES]);

    // ─── الطباعة بقوالب الويب: الافتراضيّ من «قوالب المستندات»، أو `?builtin=1` لقالب النظام ───
    Route::get('/print/invoice/{id}', [MobileSalesPrintController::class, 'invoice'])->whereNumber('id')
        ->middleware(['module:sales', 'plan:'.SalesPlan::INVOICES, 'permission:sales.invoices.print']);
    Route::get('/print/return/{id}', [MobileSalesPrintController::class, 'salesReturn'])->whereNumber('id')
        ->middleware(['module:sales', 'plan:'.SalesPlan::RETURN_INVOICES, 'permission:sales.returns.print']);
    Route::get('/print/credit-note/{id}', [MobileSalesPrintController::class, 'creditNote'])->whereNumber('id')
        ->middleware(['module:sales', 'plan:'.SalesPlan::CREDIT_NOTES, 'permission:sales.credit_notes.print']);
    Route::get('/print/payment/{id}', [MobileSalesPrintController::class, 'payment'])->whereNumber('id')
        ->middleware(['module:sales', 'plan:'.SalesPlan::PAYMENTS, 'permission:sales.payments.print']);
    // الإقفال اليوميّ — ميزة الباقة وصلاحيته كمسار `daily-closing.print` في الويب
    Route::get('/print/closing/{id}', [MobileSalesPrintController::class, 'closing'])->whereNumber('id')
        ->middleware(['module:sales', 'plan:'.SalesPlan::DASHBOARD.'|'.SalesPlan::REPORTS, 'permission:sales.daily_closing.print']);

    // «أداء المندوبين» — شاشة الويب `dashboard.reps` نفسُها: الفرع والفترة والترتيب ومجموع الفريق
    Route::get('/reps-performance', [MobileSalesDashboardController::class, 'reps'])
        ->middleware(['module:sales', 'plan:'.SalesPlan::DASHBOARD, 'permission:sales.dashboard.view']);

    // قائمة قوالب النوع (قائمة «المطبوعات» في شاشة المستند بالويب) — بصلاحية طباعته نفسها
    foreach ([
        'invoice' => [SalesPlan::INVOICES, 'sales.invoices.print'],
        'return' => [SalesPlan::RETURN_INVOICES, 'sales.returns.print'],
        'credit-note' => [SalesPlan::CREDIT_NOTES, 'sales.credit_notes.print'],
        'payment' => [SalesPlan::PAYMENTS, 'sales.payments.print'],
    ] as $printKind => [$printPlan, $printPermission]) {
        Route::get("/print/{$printKind}/templates", [MobileSalesPrintController::class, 'templates'])
            ->defaults('kind', $printKind)
            ->middleware(['module:sales', 'plan:'.$printPlan, 'permission:'.$printPermission]);
    }
});

/*
|--------------------------------------------------------------------------
| الموارد البشرية — «سجّل حضوري»
|--------------------------------------------------------------------------
| شاشة الموظف لتسجيل حضوره وانصرافه واستراحته بموقعه — `FieldAttendanceController` نفسُه، بمفتاح
| التطبيق وميزة الباقة وصلاحية المسار في الويب (`hr.field-attendance.*`). الموظفُ صاحبُ الحساب دائماً.
*/
Route::middleware([MobileApiContext::class, 'auth:sanctum', 'module:employee_attendance', 'plan:'.HrPlan::ATTENDANCE, 'permission.role:attendance_registration.mobile'])
    ->prefix('hr/attendance')->group(function () {
        Route::get('/', [MobileAttendanceController::class, 'state']);
        Route::post('/check-in', [MobileAttendanceController::class, 'checkIn']);
        Route::post('/check-out', [MobileAttendanceController::class, 'checkOut']);
        Route::post('/break', [MobileAttendanceController::class, 'toggleBreak']);
        Route::post('/office-scan', [MobileAttendanceController::class, 'officeScan']);
    });
