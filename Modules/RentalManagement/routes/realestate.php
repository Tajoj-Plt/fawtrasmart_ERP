<?php

use App\Constants\planFeatures\RealEstatePlanFeatures as Plan;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\RentalManagement\Http\Controllers\RealEstate\BuildingsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\ContractsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\DashboardController;
use Modules\RentalManagement\Http\Controllers\RealEstate\ListingsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\MaintenanceController;
use Modules\RentalManagement\Http\Controllers\RealEstate\OwnersController;
use Modules\RentalManagement\Http\Controllers\RealEstate\PaymentsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\RelationshipsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\ReportsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\SettingsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\TasksController;
use Modules\RentalManagement\Http\Controllers\RealEstate\TenantsController;
use Modules\RentalManagement\Http\Controllers\RealEstate\UnitsController;

/*
    إدارة العقارات — ثلاثة أسوار كما في المخزون:

      1. المجموعة كلّها: `module:rental_management` (تعطيل التطبيق من شاشة
         التطبيقات يقفلها فعلاً) + `plan:` بأيّ ميزةٍ من ميزات العقارات.
      2. كلّ قسم: `plan:` بميزته.
      3. كلّ إجراء: `permission.role:` — داخل مُنشئ المتحكّم، فلا يُنسى على
         مسار AJAX كما يُنسى حين يُكتب على كل راوت.

    مسارات AJAX (`units/{id}/info`, `payments/contract/{id}/schedules`) داخل
    المجموعة نفسها عمداً: ترث الأسوار الثلاثة.
*/
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch', 'auth', 'accounting.period'],
], function () {
    Route::prefix('realestate')->name('realestate.')
        ->middleware(['module:rental_management', 'plan:'.implode('|', Plan::all())])
        ->group(function () {
            Route::get('/', fn () => redirect()->route('realestate.dashboard'));
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // ═══════ المباني والمجمعات + الوحدات ═══════
            Route::middleware('plan:'.Plan::PROPERTIES)->group(function () {
                Route::prefix('buildings')->name('buildings.')->controller(BuildingsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::get('/map', 'map')->name('map');
                    Route::post('/', 'store')->name('store');
                    Route::get('/complexes', 'complexes')->name('complexes.index');
                    Route::post('/complexes', 'storeComplex')->name('complexes.store');
                    Route::put('/complexes/{complexId}', 'updateComplex')->name('complexes.update')->whereNumber('complexId');
                    Route::delete('/complexes/{complexId}', 'destroyComplex')->name('complexes.destroy')->whereNumber('complexId');
                    Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                    Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                    Route::post('/{id}/floors', 'storeFloor')->name('floors.store')->whereNumber('id');
                    Route::delete('/{id}/floors/{floorId}', 'destroyFloor')->name('floors.destroy')->whereNumber(['id', 'floorId']);
                });

                // الملّاك: كيانٌ مستقلّ عن العملاء، وله اتفاقية إدارة أملاك وحسابٌ جارٍ وتحويل مستحقات
                Route::prefix('owners')->name('owners.')->controller(OwnersController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                    Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                    Route::post('/{id}/transfer', 'transfer')->name('transfer')->whereNumber('id');
                    Route::post('/{id}/transfers/{entryId}/cancel', 'cancelTransfer')->name('transfers.cancel')->whereNumber(['id', 'entryId']);
                });

                Route::prefix('units')->name('units.')->controller(UnitsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::post('/bulk', 'bulkStore')->name('bulk');
                    Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                    Route::get('/{id}/info', 'info')->name('info')->whereNumber('id')
                        ->middleware('permission.role:realestate.units.view|realestate.units.show|realestate.contracts.create|realestate.contracts.edit|realestate.listings.create|realestate.listings.edit');
                    Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::post('/{id}/status', 'changeStatus')->name('status')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                });
            });

            // ═══════ العروض العقارية + علاقات العملاء ═══════
            Route::middleware('plan:'.Plan::LISTINGS)->group(function () {
                Route::prefix('listings')->name('listings.')->controller(ListingsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/requests', 'requests')->name('requests'); // طلبات العملاء (listing_kind = request)؛ الإنشاء: create?kind=request
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                    Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::post('/{id}/status', 'status')->name('status')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                    Route::post('/{id}/viewings', 'storeViewing')->name('viewings.store')->whereNumber('id');
                    Route::put('/{id}/viewings/{viewingId}', 'updateViewing')->name('viewings.update')->whereNumber(['id', 'viewingId']);
                });

                Route::prefix('relationships')->name('relationships.')->controller(RelationshipsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/client/{clientId}', 'client')->name('client')->whereNumber('clientId');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                });

                // المستأجرون: عرضٌ مخصّص فوق العلاقات، وملفّ المستأجر هو `relationships.client`
                Route::prefix('tenants')->name('tenants.')->controller(TenantsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::get('/clients/search', 'clientSearch')->name('clients'); // بحث العملاء لحقول الاختيار (داخل النطاق)
                    Route::post('/', 'store')->name('store');
                    Route::get('/{clientId}', 'show')->name('show')->whereNumber('clientId');
                    Route::get('/{clientId}/edit', 'edit')->name('edit')->whereNumber('clientId');
                    Route::put('/{clientId}', 'update')->name('update')->whereNumber('clientId');
                });
            });

            // ═══════ العقود والتجديدات ═══════
            Route::prefix('contracts')->name('contracts.')->middleware('plan:'.Plan::CONTRACTS)
                ->controller(ContractsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                    Route::get('/{id}/print', 'print')->name('print')->whereNumber('id');
                    Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                    Route::post('/{id}/submit', 'submit')->name('submit')->whereNumber('id');
                    Route::post('/{id}/approve', 'approve')->name('approve')->whereNumber('id');
                    Route::post('/{id}/reject', 'reject')->name('reject')->whereNumber('id');
                    Route::post('/{id}/cancel', 'cancel')->name('cancel')->whereNumber('id');
                    Route::post('/{id}/terminate', 'terminate')->name('terminate')->whereNumber('id');
                    Route::post('/{id}/amend', 'amend')->name('amend')->whereNumber('id');
                    Route::post('/{id}/schedule/regenerate', 'regenerateSchedule')->name('schedule.regenerate')->whereNumber('id');
                    Route::get('/{id}/renew', 'renewForm')->name('renew')->whereNumber('id');
                    Route::post('/{id}/renew', 'storeRenewal')->name('renew.store')->whereNumber('id');
                    Route::post('/{id}/renewals/{renewalId}/approve', 'approveRenewal')->name('renewals.approve')->whereNumber(['id', 'renewalId']);
                    Route::post('/{id}/renewals/{renewalId}/reject', 'rejectRenewal')->name('renewals.reject')->whereNumber(['id', 'renewalId']);
                });

            // ═══════ الدفعات والاستحقاقات ═══════
            Route::prefix('payments')->name('payments.')->middleware('plan:'.Plan::PAYMENTS)
                ->controller(PaymentsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/schedules', 'schedules')->name('schedules');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/contract/{contractId}/schedules', 'contractSchedules')->name('contract_schedules')->whereNumber('contractId');
                    Route::post('/deposits/{contractId}/settle', 'settleDeposit')->name('deposits.settle')->whereNumber('contractId');
                    Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                    Route::get('/{id}/receipt', 'receipt')->name('receipt')->whereNumber('id');
                    Route::post('/{id}/cancel', 'cancel')->name('cancel')->whereNumber('id');
                });

            // ═══════ الصيانة ═══════
            Route::prefix('maintenance')->name('maintenance.')->middleware('plan:'.Plan::MAINTENANCE)
                ->controller(MaintenanceController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{id}', 'show')->name('show')->whereNumber('id');
                    Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::post('/{id}/status', 'status')->name('status')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                    Route::post('/{id}/costs', 'storeCost')->name('costs.store')->whereNumber('id');
                    Route::delete('/{id}/costs/{costId}', 'destroyCost')->name('costs.destroy')->whereNumber(['id', 'costId']);
                    Route::post('/{id}/costs/{costId}/post', 'postCost')->name('costs.post')->whereNumber(['id', 'costId']);
                });

            // ═══════ المهام والمتابعات ═══════
            Route::prefix('tasks')->name('tasks.')->middleware('plan:'.Plan::TASKS)
                ->controller(TasksController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/clients/search', 'clientSearch')->name('clients'); // بحث العملاء (داخل النطاق)
                    Route::get('/related/search', 'relatedSearch')->name('related'); // بحث «مرتبطة بـ» حسب النوع
                    Route::post('/', 'store')->name('store');
                    Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                    Route::post('/{id}/status', 'status')->name('status')->whereNumber('id');
                    Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');
                });

            // ═══════ التقارير ═══════
            Route::prefix('reports')->name('reports.')->middleware('plan:'.Plan::REPORTS)
                ->controller(ReportsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::delete('/saved/{id}', 'destroySaved')->name('saved.destroy')->whereNumber('id');
                    Route::get('/{key}', 'show')->name('show')->where('key', '[a-z_]+');
                    Route::get('/{key}/export', 'export')->name('export')->where('key', '[a-z_]+');
                    Route::post('/{key}/save', 'save')->name('save')->where('key', '[a-z_]+');
                });

            // ═══════ الإعدادات والسجلّ ═══════
            Route::prefix('settings')->name('settings.')->controller(SettingsController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/', 'update')->name('update');
                Route::post('/accounts/repair', 'repairAccounts')->name('accounts.repair');
                Route::post('/amenities', 'storeAmenity')->name('amenities.store');
                Route::delete('/amenities/{id}', 'destroyAmenity')->name('amenities.destroy')->whereNumber('id');
                Route::post('/terms', 'storeTerm')->name('terms.store');
                Route::put('/terms/{id}', 'updateTerm')->name('terms.update')->whereNumber('id');
                Route::delete('/terms/{id}', 'destroyTerm')->name('terms.destroy')->whereNumber('id');
                Route::post('/roles/install', 'installRoles')->name('roles.install');
                Route::get('/logs', 'logs')->name('logs');
            });
        });
});
