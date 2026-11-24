<?php

namespace Modules\Client\Http\Controllers;

use App\Models\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Client\Http\Controllers\Customers\CustomerAccountStatementController;
use Modules\Client\Http\Controllers\Customers\CustomerAppointmentsController;
use Modules\Client\Http\Controllers\Customers\CustomerBalancesController;
use Modules\Client\Http\Controllers\Customers\CustomerDebtAgingController;
use Modules\Client\Http\Controllers\Customers\CustomerDebtsController;
use Modules\Client\Http\Controllers\Customers\CustomerGuideController;
use Modules\Client\Http\Controllers\Customers\CustomerInstallmentsController;
use Modules\Client\Http\Controllers\Customers\CustomerReportController;
use Modules\Sitting\Http\Controllers\ActivityLogController;

// =============================================
// Routes الرئيسية للعملاء تحت الترجمة + ميدلوير
// =============================================
Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [
            'localeSessionRedirect',
            'localizationRedirect',
            'localeViewPath',
            'check.branch',
            'auth',
            'notification',
        ],
    ],
    function () {

        // =============================================
        // لوحة تحكم العملاء ومؤشرات الأداء
        // =============================================
        Route::get('/client-dashboard', [CatagroiyClientController::class, 'dashboard'])->name('clients.dashboard')
            ->middleware('permission:clients_dashboard_view|clients_view_all_clients');
        // قائمةُ عملاء أيّ رقمٍ في اللوحة — تفتحها البطاقات والمخطَّطات
        Route::get('/client-dashboard/segment', [CatagroiyClientController::class, 'segment'])->name('clients.dashboard.segment')
            ->middleware('permission:clients_dashboard_view|clients_view_all_clients');
        Route::get('/client-kpis', [CatagroiyClientController::class, 'kpis'])->name('clients.kpis')
            ->middleware('permission:clients_dashboard_view|clients_view_all_clients');

        // =============================================
        // إعدادات العميل
        // =============================================
        Route::prefix('client-settings')->group(function () {
            Route::get('/personal', [ClientSettingController::class, 'personal'])->name('clients.personal');
            Route::get('/edit/profile', [ClientSettingController::class, 'profile'])->name('clients.profile');

            Route::get('/invoice/client', [ClientSettingController::class, 'invoice_client'])->name('clients.invoice_client');
            Route::get('/appointments/client', [ClientSettingController::class, 'appointments_client'])->name('clients.appointments_client');
            Route::get('/questions/client', [ClientSettingController::class, 'questions_client'])->name('clients.questions_client');
            // كشف حساب العميل في بوابته — يحرسه مفتاح `view_account_statement`
            // في «صلاحيات بوابة العميل» عبر ClientAccessMiddleware
            Route::get('/statement/client', [ClientSettingController::class, 'statement'])->name('clients.portal.statement');

            /* ══════════ ما يفعله العميل بنفسه من بوابته ══════════

               لا `permission:` هنا: شاشاتُ البوابة يحرسها مفتاحُها في
               «صلاحيات بوابة العميل» (`ClientPortalPermissions`) عبر
               `ClientAccessMiddleware` وعبر `guardPortal` في المتحكّم —
               وصلاحياتُ الموظفين لا يملك العميلُ منها شيئاً أصلاً. */
            Route::prefix('reservations')->group(function () {
                Route::get('/', [Portal\PortalReservationController::class, 'index'])->name('clients.portal.reservations.index');
                Route::get('/create', [Portal\PortalReservationController::class, 'create'])->name('clients.portal.reservations.create');
                Route::get('/slots', [Portal\PortalReservationController::class, 'slots'])->name('clients.portal.reservations.slots');
                Route::post('/', [Portal\PortalReservationController::class, 'store'])->name('clients.portal.reservations.store');
            });

            Route::post('/payment-notices', [Portal\PortalPaymentNoticeController::class, 'store'])->name('clients.portal.payments.store');
            Route::post('/payment-notices/{notice}/cancel', [Portal\PortalPaymentNoticeController::class, 'cancel'])->name('clients.portal.payments.cancel');

            // مرتجعاتُ العميل ومحفظتُه — عرضٌ فقط، يحرسهما مفتاحاهما في «صلاحيات بوابة العميل»
            Route::get('/returns/client', [Portal\PortalReturnController::class, 'index'])->name('clients.portal.returns');
            Route::get('/wallet/client', [Portal\PortalWalletController::class, 'index'])->name('clients.portal.wallet');

            /* طلبُ زيارةٍ ميدانية — يراجعه موظّفٌ من `clients.visit_requests.index`.
               السحبُ `POST` لا `DELETE`: الصفُّ يبقى بحالة `cancelled` ليُرى
               في سجلّ العميل، ونماذجُ البوابة بلا جافاسكربت تُزوِّر الفعل. */
            Route::prefix('visit-requests')->group(function () {
                Route::get('/', [Portal\PortalVisitRequestController::class, 'index'])->name('clients.portal.visit_requests.index');
                Route::post('/', [Portal\PortalVisitRequestController::class, 'store'])->name('clients.portal.visit_requests.store');
                Route::post('/{visitRequest}/cancel', [Portal\PortalVisitRequestController::class, 'cancel'])->name('clients.portal.visit_requests.cancel');
            });

            /* النقاطُ والمكافآت — كانت بوابةً ثانيةً على `/client-portal`
               بدخولٍ برقم الهاتف؛ وُحِّدت هنا (طلب المستخدم 2026-09-19)
               وعناوينُ القديمة تحوّل إلى هذه. */
            Route::get('/points', [Portal\PortalPointsController::class, 'index'])->name('clients.portal.points');
            Route::post('/points/refer', [Portal\PortalPointsController::class, 'refer'])->name('clients.portal.points.refer');

            Route::get('/clients/get-for-select', [ClientController::class, 'getClientsForSelect'])
                ->name('clients.get_for_select')
                ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
            // Route::get('/employee-targets', [ClientSettingController::class, 'employee_targets'])->name('employee_targets.index');
            /* الاسم كان `clients.setting` — يتصادم مع شاشة «إعدادات العملاء»
               الفعلية (`ClientSettingController::setting`) التي تستخدمها
               القائمة الجانبية فعلاً؛ تلك تفوز دائماً (آخر تسجيل)، وهذا
               الرابط لم يكن يُستدعى بالاسم قط. صار اسماً خاصّاً بلا أثر. */
            Route::get('/sittingsIndex', [ClientSettingController::class, 'index'])->name('clients.settingsIndex');
            //    Route::post('/employee-targets', [ClientSettingController::class, 'storeOrUpdate'])->name('employee_targets.store');

            // Route::get('/general-target', [ClientSettingController::class, 'showGeneralTarget'])->name('target.show');
            // Route::post('/general-target', [ClientSettingController::class, 'updateGeneralTarget'])->name('target.update');

            /* «هدف العميل» (رقمٌ عامٌّ واحدٌ مشترك، Target id=2) أُزيل —
               كان بلا صلاحيةٍ ولا اشتراكٍ يحرسه، وتأكَّد أنه غير مُستخدَمٍ
               فعلياً. لا علاقة له بـ«الأهداف» أدناه (CustomerTarget) —
               نظامٌ مختلفٌ كلياً محميٌّ بصلاحيةٍ واشتراكٍ من الأصل. */
            Route::get('/statistics-sitting', [ClientSettingController::class, 'statistics_sitting'])->name('client.target.statistics_sitting');

            Route::put('/Client/store', [ClientSettingController::class, 'Client_store'])->name('clients.Client_store');
        });

        // =============================================
        // إعدادات وحدات العملاء (تفعيل/تعطيل الأقسام الفرعية)
        // =============================================
        Route::get('/client-settings/module-settings', [ClientModuleSettingsController::class, 'index'])->name('clients.module_settings')
            ->middleware('permission:clients_module_settings_view');
        Route::post('/client-settings/module-settings', [ClientModuleSettingsController::class, 'update'])->name('clients.module_settings.update')
            ->middleware('permission:clients_module_settings_update');
        Route::post('/client-settings/module-settings/employee-proximity-overrides', [ClientModuleSettingsController::class, 'storeEmployeeProximityOverride'])
            ->name('clients.module_settings.employee_proximity_overrides.store')
            ->middleware('permission:clients_module_settings_update');
        Route::delete('/client-settings/module-settings/employee-proximity-overrides/{user}', [ClientModuleSettingsController::class, 'destroyEmployeeProximityOverride'])
            ->name('clients.module_settings.employee_proximity_overrides.destroy')
            ->middleware('permission:clients_module_settings_update');

        // =============================================
        // موقع العميل
        // =============================================
        Route::prefix('client-location')->group(function () {
            Route::post('/', [LocationController::class, 'store'])->name('clients.location.store')
                ->middleware('permission:clients_visits_track|clients_itinerary_add');
        });

        // =============================================
        // إعدادات أهداف العملاء
        // =============================================
        Route::prefix('customer-targets')->name('client.customer_targets.')->group(function () {
            Route::get('/', [\Modules\Client\Http\Controllers\CustomerTargetController::class, 'index'])->name('index')
                ->middleware('permission:clients.reports.targets|clients_edit_client_settings');
            Route::get('/create', [\Modules\Client\Http\Controllers\CustomerTargetController::class, 'create'])->name('create')
                ->middleware('permission:clients_edit_client_settings');
            Route::post('/', [\Modules\Client\Http\Controllers\CustomerTargetController::class, 'store'])->name('store')
                ->middleware('permission:clients_edit_client_settings');
            Route::get('/{id}/edit', [\Modules\Client\Http\Controllers\CustomerTargetController::class, 'edit'])->name('edit')
                ->middleware('permission:clients_edit_client_settings');
            Route::put('/{id}', [\Modules\Client\Http\Controllers\CustomerTargetController::class, 'update'])->name('update')
                ->middleware('permission:clients_edit_client_settings');
            Route::delete('/{id}', [\Modules\Client\Http\Controllers\CustomerTargetController::class, 'destroy'])->name('destroy')
                ->middleware('permission:clients_edit_client_settings');
            Route::post('/evaluate-all', [\Modules\Client\Http\Controllers\CustomerTargetController::class, 'evaluateAll'])->name('evaluateAll')
                ->middleware('permission:clients_edit_client_settings');
        });

        // =============================================
        // كانت أربعة مسارات تشير إلى ClientApiController — صنفٌ غير موجود في
        // الشجرة إطلاقاً (لا Modules/Client ولا أي مكان آخر)، فكل طلبٍ لها
        // كان يفشل بخطأ 500 دائماً. لا رابط ولا JS في كل الوحدة يستدعيها
        // (بحث شامل)، فحُذفت بدل إبقائها معطوبة.
        // =============================================

        Route::prefix('categoriesClient')->group(function () {
            Route::get('/client/department', [CatagroiyClientController::class, 'dashboard'])
                ->name('client.department.dashboard')
                ->middleware('permission:clients_dashboard_view|clients_view_all_clients');
            Route::get('/categories', [CatagroiyClientController::class, 'index'])->name('categoriesClient.index')
                ->middleware('permission:settings_customer_categories|clients_edit_client_settings');
            Route::get('/categories/create', [CatagroiyClientController::class, 'create'])->name('categoriesClient.create')
                ->middleware('permission:settings_customer_categories|clients_edit_client_settings');
            Route::post('/categories/store', [CatagroiyClientController::class, 'store'])->name('categoriesClient.store')
                ->middleware('permission:settings_customer_categories|clients_edit_client_settings');
            Route::get('/categories/edit/{id}', [CatagroiyClientController::class, 'edit'])->name('categoriesClient.edit')
                ->middleware('permission:settings_customer_categories|clients_edit_client_settings');
            Route::put('/categories/update/{id}', [CatagroiyClientController::class, 'update'])->name('categoriesClient.update')
                ->middleware('permission:settings_customer_categories|clients_edit_client_settings');
            Route::delete('/categories/delete/{id}', [CatagroiyClientController::class, 'destroy'])->name('categoriesClient.destroy')
                ->middleware('permission:settings_customer_categories|clients_edit_client_settings');
        });

        // =============================================
        // الجدول الزمني (Itinerary)
        // =============================================
        Route::prefix('itinerary')->group(function () {
            Route::get('/', [ItineraryController::class, 'create'])->name('itinerary.create')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::post('/', [ItineraryController::class, 'store'])->name('itinerary.store')
                ->middleware('permission:clients_itinerary_add');
            Route::get('/edit/{id}', [ItineraryController::class, 'edit'])->name('itinerary.edit')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::put('/update/{id}', [ItineraryController::class, 'update'])->name('itinerary.update')
                ->middleware('permission:clients_itinerary_edit');
            Route::get('/week-itinerary/{employeeId}', [ItineraryController::class, 'getWeekItinerary'])->name('itinerary.week-itinerary')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            // فروع الموظف/عملاء الفرع — كانتا في routes/api.php، فيفشل جلبهما بـ"Unauthenticated."
            // كلما لم يطابق Referer المتصفح قائمة نطاقات Sanctum الموثوقة (EnsureFrontendRequestsAreStateful).
            // هنا داخل مجموعة web/auth العادية — نفس ضمان كل مسار آخر في هذه الصفحة يعمل بلا مشاكل.
            Route::get('/groups-for-employee/{employee}', [ItineraryController::class, 'getGroupsForEmployee'])->name('itinerary.groups-for-employee')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::get('/clients-for-group/{group}', [ItineraryController::class, 'getClientsForGroup'])->name('itinerary.clients-for-group')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::post('/visits/reorder', [ItineraryController::class, 'reorderVisits'])->name('itinerary.visits.reorder')
                ->middleware('permission:clients_itinerary_edit');
            // عملاءُ مُنتقي شاشة التخطيط — بعقدة «تابع لـ» وبالإسناد وبالبحث
            Route::get('/route-clients', [ItineraryController::class, 'routeClients'])->name('itinerary.route-clients')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::get('/list', [ItineraryController::class, 'listAll'])->name('itinerary.list')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::get('/dashboard', [ItineraryController::class, 'dashboard'])->name('itinerary.dashboard')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            // لوحةُ اليوم في شاشة عرض خطوط السير — زياراتُ مندوبٍ في يومٍ من أسبوع
            Route::get('/day-visits', [ItineraryController::class, 'dayVisits'])->name('itinerary.day-visits')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::get('/today-notes/{employeeId?}', [ItineraryController::class, 'getTodayNotes'])->name('itinerary.today-notes')
                ->middleware('permission:clients_itinerary_today_notes_view');
            Route::delete('/visits/{visit}', [ItineraryController::class, 'destroyVisit'])->name('itinerary.visits.destroy')
                ->middleware('permission:clients_itinerary_edit');
            Route::post('/visits/bulk-delete', [ItineraryController::class, 'bulkDestroyVisits'])->name('itinerary.visits.bulk-destroy')
                ->middleware('permission:clients_itinerary_edit');
            Route::post('/visits/{visit}/start', [ItineraryController::class, 'startVisit'])->name('itinerary.visits.start')
                ->middleware('permission:clients_itinerary_edit');
            Route::post('/visits/{visit}/complete', [ItineraryController::class, 'completeVisit'])->name('itinerary.visits.complete')
                ->middleware('permission:clients_itinerary_edit');
            Route::post('/visits/{visit}/cancel', [ItineraryController::class, 'cancelVisit'])->name('itinerary.visits.cancel')
                ->middleware('permission:clients_itinerary_edit');
            Route::post('/visits/{visit}/miss', [ItineraryController::class, 'missVisit'])->name('itinerary.visits.miss')
                ->middleware('permission:clients_itinerary_edit');
            Route::post('/add-client', [ItineraryController::class, 'addClientToItinerary'])->name('itinerary.add-client')
                ->middleware('permission:clients_itinerary_add');
            Route::post('/quick-add', [ItineraryController::class, 'quickAddToItinerary'])->name('itinerary.quick-add')
                ->middleware('permission:clients_itinerary_add');
            Route::post('/bulk-add-clients', [ItineraryController::class, 'bulkAddClientsToItinerary'])->name('itinerary.bulk-add-clients')
                ->middleware('permission:clients_itinerary_add');
            Route::get('/employees-list', [ItineraryController::class, 'getEmployeesListForModal'])->name('employees.list')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::post('/move-incomplete', [ItineraryController::class, 'moveIncompleteVisits'])->name('itinerary.move-incomplete')
                ->middleware('permission:clients_itinerary_edit');
            Route::get('/incomplete-visits', [ItineraryController::class, 'getIncompleteVisitsForDay'])->name('itinerary.incomplete-visits')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::get('/client-status/{clientId}', [ItineraryController::class, 'getClientStatus'])->name('itinerary.client-status')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::get('/monthly-notes', [ItineraryController::class, 'monthlyNotesReport'])->name('itinerary.monthly-notes')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::get('/map-data', [ItineraryController::class, 'getMapData'])->name('itinerary.map-data')
                ->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary');
            Route::post('/update-location', [ItineraryController::class, 'updateLocation'])->name('itinerary.update-location')
                ->middleware('permission:clients_itinerary_edit');

            // «خط سيري اليومي» — يومُ المندوب والإضافةُ عليه منه أو من دورٍ آخر
            Route::get('/my-day', [DailyItineraryController::class, 'index'])->name('itinerary.daily')
                ->middleware('permission:clients_daily_itinerary_view|clients_daily_itinerary_add_for_others');
            Route::get('/my-day/data', [DailyItineraryController::class, 'day'])->name('itinerary.daily.data')
                ->middleware('permission:clients_daily_itinerary_view|clients_daily_itinerary_add_for_others');
            Route::get('/my-day/clients', [DailyItineraryController::class, 'clients'])->name('itinerary.daily.clients')
                ->middleware('permission:clients_daily_itinerary_add_client|clients_daily_itinerary_add_for_others');
            Route::post('/my-day/clients', [DailyItineraryController::class, 'store'])->name('itinerary.daily.store')
                ->middleware('permission:clients_daily_itinerary_add_client|clients_daily_itinerary_add_for_others');
            Route::post('/my-day/visits/{visit}/postpone', [DailyItineraryController::class, 'postpone'])->name('itinerary.daily.postpone')
                ->middleware('permission:clients_itinerary_edit');
        });

        // =============================================
        // العملاء (Clients Management + Loyalty + CRM)
        // =============================================
        Route::prefix('clients')->group(function () {

            /* مراجعةُ الموظّف لإشعارات سداد بوابة العميل.

               الصلاحيةُ صلاحيةُ سند القبض نفسُها لا صلاحيةٌ جديدة (انظر
               ترويسة المتحكّم): من يحصّل من العميل هو من يراجع إشعاره،
               وصلاحيةٌ جديدةٌ لا يربطها `permissions:sync` بدورٍ تبقى
               الشاشةُ خلفها مخفيّةً عن الجميع. */
            Route::prefix('payment-notices')->group(function () {
                Route::get('/', [ClientPaymentNoticeController::class, 'index'])
                    ->name('clients.payment_notices.index')
                    ->middleware('permission:sales.payments.view');
                Route::post('/{notice}/approve', [ClientPaymentNoticeController::class, 'approve'])
                    ->name('clients.payment_notices.approve')
                    ->middleware('permission:sales.payments.create');
                Route::post('/{notice}/reject', [ClientPaymentNoticeController::class, 'reject'])
                    ->name('clients.payment_notices.reject')
                    ->middleware('permission:sales.payments.create');
            });

            /* مراجعةُ طلبات الزيارة الميدانية من بوابة العميل.

               صلاحياتُ خطّ السير نفسُها لا صلاحيةٌ جديدة (انظر ترويسة
               المتحكّم): العرضُ بزوج `itinerary.daily` ذاتِه، والقرارُ
               بصلاحيتَي «إضافة عميل إلى خطّ سير» — فالاعتمادُ التزامٌ
               بزيارة. وصلاحيةٌ جديدةٌ لا يربطها `permissions:sync` بدورٍ
               تبقى الشاشةُ خلفها مخفيّةً عن الجميع. */
            Route::prefix('visit-requests')->group(function () {
                Route::get('/', [ClientVisitRequestController::class, 'index'])
                    ->name('clients.visit_requests.index')
                    ->middleware('permission:clients_itinerary_view|clients_daily_itinerary_add_for_others');
                Route::post('/{visitRequest}/approve', [ClientVisitRequestController::class, 'approve'])
                    ->name('clients.visit_requests.approve')
                    ->middleware('permission:clients_daily_itinerary_add_for_others|clients_daily_itinerary_add_client');
                Route::post('/{visitRequest}/reject', [ClientVisitRequestController::class, 'reject'])
                    ->name('clients.visit_requests.reject')
                    ->middleware('permission:clients_daily_itinerary_add_for_others|clients_daily_itinerary_add_client');
            });

            // AJAX endpoint لجلب تفاصيل العميل
            Route::get('/get-client-details/{clientId}', [CRMController::class, 'getClientDetails'])->name('clients.get-details')
                ->middleware('permission:clients_crm_view');

            // AJAX endpoint لجلب عملاء المجموعة
            Route::get('/get-group-clients', [CRMController::class, 'getGroupClients'])->name('clients.get-group-clients')
                ->middleware('permission:clients_crm_view');

            // AJAX endpoint لجلب الموظفين المربوطين بالعميل
            Route::get('/get-employees/{clientId}', [CRMController::class, 'getEmployeesForClient'])->name('clients.get-employees')
                ->middleware('permission:clients_crm_view');

            // AJAX endpoint لجلب الموظفين المربوطين بمجموعة معينة
            Route::get('/get-employees-by-group', [CRMController::class, 'getEmployeesForGroup'])->name('clients.get-employees-by-group')
                ->middleware('permission:clients_crm_view');

            // =============================================
            // تسليم واستلام الفواتير بين الموظفين
            // =============================================
            Route::prefix('invoice-transfers')->group(function () {
                Route::get('/', [InvoiceTransferController::class, 'index'])->name('invoice-transfers.index')
                    ->middleware('permission:clients_invoice_transfer_view');
                Route::post('/', [InvoiceTransferController::class, 'store'])->name('invoice-transfers.store')
                    ->middleware('permission:clients_invoice_transfer_send');
                Route::post('/balance', [InvoiceTransferController::class, 'storeBalance'])->name('invoice-transfers.balance')
                    ->middleware('permission:clients_invoice_transfer_send');
                Route::get('/sendable-clients', [InvoiceTransferController::class, 'sendableClients'])->name('invoice-transfers.sendable-clients')
                    ->middleware('permission:clients_invoice_transfer_view');
                Route::post('/receive-all', [InvoiceTransferController::class, 'receiveAll'])->name('invoice-transfers.receive-all')
                    ->middleware('permission:clients_invoice_transfer_respond');
                Route::post('/batch/{batch}/respond', [InvoiceTransferController::class, 'respondBatch'])->name('invoice-transfers.batch.respond')
                    ->middleware('permission:clients_invoice_transfer_respond');
                Route::post('/{id}/accept', [InvoiceTransferController::class, 'accept'])->name('invoice-transfers.accept')
                    ->middleware('permission:clients_invoice_transfer_respond');
                Route::post('/{id}/reject', [InvoiceTransferController::class, 'reject'])->name('invoice-transfers.reject')
                    ->middleware('permission:clients_invoice_transfer_respond');
                Route::delete('/{id}/cancel', [InvoiceTransferController::class, 'cancel'])->name('invoice-transfers.cancel')
                    ->middleware('permission:clients_invoice_transfer_cancel');
                Route::get('/available-invoices', [InvoiceTransferController::class, 'getAvailableInvoices'])->name('invoice-transfers.available-invoices')
                    ->middleware('permission:clients_invoice_transfer_view');
                Route::get('/employees', [InvoiceTransferController::class, 'getEmployees'])->name('invoice-transfers.employees')
                    ->middleware('permission:clients_invoice_transfer_view');
                Route::get('/stats', [InvoiceTransferController::class, 'getStats'])->name('invoice-transfers.stats')
                    ->middleware('permission:clients_invoice_transfer_view');
                Route::get('/client/{clientId}', [InvoiceTransferController::class, 'getClientTransfers'])->name('invoice-transfers.client')
                    ->middleware('permission:clients_invoice_transfer_view');
                Route::get('/history/{invoiceId}', [InvoiceTransferController::class, 'getInvoiceHistory'])->name('invoice-transfers.history')
                    ->middleware('permission:clients_invoice_transfer_view');
            });

            // Clients Management
            Route::prefix('clients_management')->group(function () {
                Route::get('/index', [ClientController::class, 'index'])->name('clients.index')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                /* كانت تستدعي `getMapDataWithBranch` — دالّةٌ لا وجود لها إطلاقاً
                   في المتحكّم (500 دائم)، بلا استخدامٍ بالاسم في أي Blade/JS.
                   `getMapData()` الموجودة فعلاً تعمل بنفس الغرض بالضبط (نطاق
                   الفرع والصلاحيات مطبَّقان داخلها). */
                Route::get('/map', [ClientController::class, 'getMapData'])->name('clients.getMapDataWithBranch')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/search', [ClientController::class, 'search'])->name('clients.search')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/clients/data', [ClientController::class, 'getClientsData'])->name('clients.data')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/clients/ajax/map-data', [ClientController::class, 'getMapData'])->name('clients.getMapData')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::post('/{client}/update-opening-balance', [ClientController::class, 'updateOpeningBalance'])->name('clients.updateOpeningBalance')
                    ->middleware('permission:clients_manage_opening_balance');

                Route::post('/{client}/hide-from-map', [ClientController::class, 'hideFromMap'])->name('clients.hideFromMap')
                    ->middleware('permission:clients_hide_from_map');
                Route::post('/{client}/show-in-map', [ClientController::class, 'showInMap'])->name('clients.showInMap')
                    ->middleware('permission:clients_hide_from_map');
                Route::get('/hidden-clients', [ClientController::class, 'getHiddenClients'])->name('clients.getHiddenClients')
                    ->middleware('permission:clients_hide_from_map');
                Route::get('/clients/export/all', [ClientController::class, 'exportAllClients'])
                    ->name('clients.export.all')
                    ->middleware('permission:clients_export');
                Route::get('clients/{id}/paginate', [ClientController::class, 'paginate'])->name('clients.paginate')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');

                Route::get('/{client}/details', [ClientController::class, 'getClientDetails'])->name('clients.details')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/{client}/invoices', [ClientController::class, 'getClientInvoices'])->name('clients.invoices')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/{client}/notes', [ClientController::class, 'getClientNotes'])->name('clients.getClientNotesForClient')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/{id}/last-note', [ClientNoteController::class, 'getLastNote'])->name('clients.last_note')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/{id}/client-employees', [ClientEmployeeController::class, 'getClientEmployees'])->name('clients.client_employees')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::put('/{id}/client-employee/{employeeId}', [ClientEmployeeController::class, 'updateClientEmployee'])->name('clients.updateClientEmployee')
                    ->middleware('permission:clients_assign_employees');
                Route::get('/notes/pdf/{id}', [ClientController::class, 'generateNotesPdf'])->name('clients.notes.pdf')
                    ->middleware('permission:clients_export');
                Route::get('/clients/select', [ClientController::class, 'getClientsForSelect'])->name('clients.getForSelect')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/clients/select-all', [ClientController::class, 'getClientsForSelect'])->name('clients.selectAll')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/clients/ajax', [ClientController::class, 'ajaxIndex'])->name('clients.ajax')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/clients/credit-limit/{clientId}', [ClientController::class, 'getClientCreditLimit'])->name('clients.getCreditLimit')
                    ->middleware('permission:clients_manage_credit_limit');
                Route::post('/clients/update-credit-limit', [ClientController::class, 'updateCreditLimit'])->name('clients.update_credit_limit')
                    ->middleware('permission:clients_manage_credit_limit');
                Route::post('/clients/update-item-quantity-limit', [ClientController::class, 'updateItemQuantityLimit'])->name('clients.update_item_quantity_limit')
                    ->middleware('permission:clients_manage_quantity_limit');
                Route::post('/clients/update-all-item-quantity-limits', [ClientController::class, 'updateAllClientsItemQuantityLimit'])->name('clients.update_all_item_quantity_limits')
                    ->middleware('permission:clients_manage_quantity_limit');
                Route::post('/clients/update-all-credit-limits', [ClientController::class, 'updateAllClientsCreditLimit'])->name('clients.update_all_credit_limits')
                    ->middleware('permission:clients_manage_credit_limit');

                // ملاحظات الموظفين
                Route::post('/{id}/employee-notes', [ClientNoteController::class, 'storeEmployeeNote'])->name('clients.storeEmployeeNote')
                    ->middleware('permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note');
                Route::get('/{id}/employee-notes', [ClientNoteController::class, 'getEmployeeNotes'])->name('clients.getEmployeeNotes')
                    ->middleware('permission:client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own');

                /* كتالوج المنافسين — يملأ قائمة الاختيار في «رصد المنافسين»
                   داخل ملاحظة الزيارة. صلاحياتُه على المتحكّم نفسه
                   (`CompetitorController::__construct`) بنمط الوحدة: بارِزةٌ
                   لكل مسار، وتجميعٌ بـ`only[]` في المُنشئ. */
                Route::get('/competitors', [CompetitorController::class, 'index'])->name('clients.competitors.index');
                Route::post('/competitors', [CompetitorController::class, 'store'])->name('clients.competitors.store');
                Route::put('/competitors/{competitor}', [CompetitorController::class, 'update'])->name('clients.competitors.update');
                Route::post('/competitors/{competitor}/toggle', [CompetitorController::class, 'toggle'])->name('clients.competitors.toggle');
                Route::delete('/competitors/{competitor}', [CompetitorController::class, 'destroy'])->name('clients.competitors.destroy');

                Route::get('clients/export-pdf', [ClientController::class, 'generateClientsPdf'])->name('clients.export-pdf')
                    ->middleware('permission:clients_export');
                Route::get('/{id}/invoices', [ClientController::class, 'clientInvoices'])->name('clients.invoicesById')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                /* كان اسمها `clients.notes` يتعارض مع مسارٍ آخر بنفس الاسم
                   تحت بادئة `/CRM` أدناه (متحكّمٌ مختلف، دالّةٌ لا وجود لها
                   أصلاً) — ذاك المسار حُذف الآن، فبقي هذا الاسمُ الخاصُّ
                   `clients.notesById` بلا تغييرٍ في أي سلوك، لأن لا استدعاء
                   بالاسم لأيٍّ منهما أصلاً (بحثٌ شامل). */
                Route::get('/{id}/notes', [ClientController::class, 'clientNotes'])->name('clients.notesById')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::post('/{id}/notes', [ClientController::class, 'storeNote'])->name('clients.notes.store');
                Route::delete('/{id}/notes/{noteId}', [ClientController::class, 'deleteNote'])->name('clients.notes.delete');

                /* `/testcient` كانت تستدعي `ClientController::testcient` — دالّةٌ
                   لا وجود لها إطلاقاً في المتحكّم (500 دائم)، بلا صلاحية، وبلا
                   استخدامٍ في أي Blade أو JS. حُذفت بدل حراسة رابطٍ معطوب. */
                Route::get('/notes/clients', [ClientController::class, 'notes'])->name('clients.notes.list')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::post('/clients/{id}/update-status', [ClientController::class, 'updateStatus'])
                    ->middleware('permission:clients_change_status');

                Route::get('/send_info/{id}', [ClientController::class, 'send_email'])->name('clients.send_info')
                    ->middleware('permission:clients_send_email');

                // إعدادات العميل
                Route::get('/setting', [ClientSettingController::class, 'setting'])->name('clients.setting')
                    ->middleware('permission:clients_edit_client_settings|settings_customer_categories|settings_customer_followup_status|settings_customer_permissions');

                // شاشة «عام» دُمجت في «إعدادات وحدات العملاء» — يبقى اسمُ
                // المسار تحويلاً دائماً فلا تنكسر روابطُ محفوظة ولا قائمةٌ
                // جانبية لم تُحدَّث بعد. حقولُها في تبويب «العملاء» هناك.
                // بالاسم لا بالمسار: المسارات هنا مُلحقةٌ ببادئة اللغة، فمسارٌ
                // مكتوبٌ نصّاً يخرج من `/ar/` ويضيع
                Route::get('/general/settings', fn () => redirect()->route('clients.module_settings', ['tab' => 'clients']))
                    ->name('clients.general');
                Route::get('/status/clients', [ClientSettingController::class, 'status'])->name('clients.status')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');
                Route::post('/status/store', [ClientSettingController::class, 'storeStatus'])->name('clients.status.store')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');
                Route::put('/status/update/{id}', [ClientSettingController::class, 'updateStatus'])->name('clients.status.update')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');

                // مراحل التسجيل — قائمةٌ قابلة للتعديل مثل حالات العملاء، لكن
                // مفتوحة بلا سقف على عددها. انظر خطوة 3 من
                // docs/CRM_Customer_Status_Gap_Analysis_Plan.md
                Route::get('/registration-stages', [ClientSettingController::class, 'registrationStages'])->name('clients.registrationStages')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');
                Route::post('/registration-stages/store', [ClientSettingController::class, 'storeRegistrationStage'])->name('clients.registrationStages.store')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');
                Route::put('/registration-stages/update/{id}', [ClientSettingController::class, 'updateRegistrationStage'])->name('clients.registrationStages.update')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');
                Route::delete('/registration-stages/delete/{id}', [ClientSettingController::class, 'deleteRegistrationStage'])->name('clients.registrationStages.delete')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');

                Route::post('/update-client-status', [ClientController::class, 'updateStatusClient'])->name('clients.updateStatusClient')
                    ->middleware('permission:clients_change_status');
                Route::post('/bulk-update-status', [ClientController::class, 'bulkUpdateStatus'])->name('clients.bulkUpdateStatus')
                    ->middleware('permission:clients_change_status');
                Route::get('/bulk-status-page', [ClientController::class, 'bulkStatusPage'])->name('clients.bulkStatusPage')
                    ->middleware('permission:clients_change_status');
                Route::post('/fill-stage-from-status', [ClientController::class, 'fillStageFromStatus'])->name('clients.fillStageFromStatus')
                    ->middleware('permission:clients_change_status');
                /* الزرّ "تصفية وجلب" في صفحة التحديث الجماعي يستدعيها منذ
                   إنشاء الصفحة، ولم يكن لها Route مسجَّل قطّ — 404 صامت. */
                Route::get('/filtered-clients', [ClientController::class, 'getFilteredClients'])->name('clients.getFilteredClients')
                    ->middleware('permission:clients_change_status');

                // اعتماد عميلٍ من مرحلة التسجيل (متابعة/تحت المراجعة) — نفس
                // صلاحية اعتماد طلبات تغيير الحالة، فالفعل نفسه اعتماد
                Route::post('/{id}/approve-registration', [ClientController::class, 'approveRegistrationStage'])->name('clients.approveRegistrationStage')
                    ->middleware('permission:clients_approve_status');

                /* اعتماد/رفض طلب تغيير الحالة — بمعرّف الطلب نفسه (تبويب سجل
                   النشاط بملف العميل) وبمعرّف العميل (استدعاء الإشعار المباشر
                   من notification-system.js). الأسماء والمسار الحرفي هنا
                   مطابقان تماماً لما يستدعيه الـ Blade والـ JS أصلاً — كانا
                   بلا route مسجَّل فيرمي RouteNotFoundException عند فتح
                   التبويب، ويفشل الإشعار بصمت من الجافاسكربت. */
                Route::post('/status-requests/{id}/approve', [ClientController::class, 'approveStatusRequest'])->name('clients.status-requests.approve')
                    ->middleware('permission:clients_approve_status');
                Route::post('/status-requests/{id}/reject', [ClientController::class, 'rejectStatusRequest'])->name('clients.status-requests.reject')
                    ->middleware('permission:clients_approve_status');
                Route::post('/status-requests/client/{client_id}/approve', [ClientController::class, 'approveStatusRequestByClient'])->name('clients.status-requests.approveByClient')
                    ->middleware('permission:clients_approve_status');
                Route::post('/status-requests/client/{client_id}/reject', [ClientController::class, 'rejectStatusRequestByClient'])->name('clients.status-requests.rejectByClient')
                    ->middleware('permission:clients_approve_status');
                Route::get('/status-requests', [ClientController::class, 'statusRequestsIndex'])->name('clients.status-requests.index')
                    ->middleware('permission:clients_approve_status');

                Route::post('/update-client-stage', [ClientController::class, 'updateStageClient'])->name('clients.updateStageClient')
                    ->middleware('permission:clients_change_status');
                // اعتماد/رفض طلب تغيير الوضع — نفس نمط طلبات الحالة أعلاه
                // بمسارين (بمعرّف الطلب، وبمعرّف العميل لاستدعاء الإشعار المباشر)
                Route::post('/stage-requests/{id}/approve', [ClientController::class, 'approveStageRequest'])->name('clients.stage-requests.approve')
                    ->middleware('permission:clients_approve_stage');
                Route::post('/stage-requests/{id}/reject', [ClientController::class, 'rejectStageRequest'])->name('clients.stage-requests.reject')
                    ->middleware('permission:clients_approve_stage');
                Route::post('/stage-requests/client/{client_id}/approve', [ClientController::class, 'approveStageRequestByClient'])->name('clients.stage-requests.approveByClient')
                    ->middleware('permission:clients_approve_stage');
                Route::post('/stage-requests/client/{client_id}/reject', [ClientController::class, 'rejectStageRequestByClient'])->name('clients.stage-requests.rejectByClient')
                    ->middleware('permission:clients_approve_stage');

                Route::get('/search-by-code', [ClientController::class, 'searchByCode'])->name('clients.searchByCode')
                    ->middleware('permission:clients_edit_data');
                Route::get('/clients-select-search', [ClientController::class, 'searchClientsForSelect'])->name('clients.searchClientsForSelect')
                    ->middleware('permission:clients_edit_data');
                Route::delete('/status/delete/{id}', [ClientSettingController::class, 'deleteStatus'])->name('clients.status.delete')
                    ->middleware('permission:settings_customer_followup_status|clients_edit_client_settings');

                // صلاحيات العميل
                Route::get('/permission/settings', [ClientSettingController::class, 'permission'])->name('clients.permission')
                    ->middleware('permission:settings_customer_permissions|clients_edit_client_settings');
                Route::post('/permission/settings', [ClientSettingController::class, 'permission_store'])->name('clients.store_permission')
                    ->middleware('permission:settings_customer_permissions|clients_edit_client_settings');

                /* معاينة بوابة العميل بحسابه.
                   `start` بـPOST لا GET: تغييرُ صاحب الجلسة فعلٌ لا قراءة.
                   و`stop` بلا صلاحيةٍ عمداً — صاحبُ الجلسة حينها هو العميل
                   لا الموظّف، فحارسُ الصلاحية عليه يحبس من فتح المعاينة.
                   والمسار مستثنىً في `ClientAccessMiddleware` بهذا الاسم. */
                Route::post('/{client}/portal-preview', [ClientPortalPreviewController::class, 'start'])
                    ->name('clients.portal.start')
                    ->middleware('permission:clients_portal_preview');
                Route::post('/portal-preview/stop', [ClientPortalPreviewController::class, 'stop'])
                    ->name('clients.portal.stop');

                // CRUD العملاء
                Route::get('/create', [ClientController::class, 'create'])->name('clients.create')
                    ->middleware('permission:clients_add_client');
                Route::post('/clients/import', [ClientController::class, 'import'])->name('clients.import')
                    ->middleware('permission:clients_import');
                Route::post('/import/excel', [ClientController::class, 'importExcel'])->name('clients.import.excel')
                    ->middleware('permission:clients_import');
                Route::get('/import/template', [ClientController::class, 'getImportTemplate'])->name('clients.import.template')
                    ->middleware('permission:clients_export');
                Route::get('/import', [ClientController::class, 'showImportPage'])->name('clients.import.page')
                    ->middleware('permission:clients_import');
                Route::post('/addnotes', [ClientNoteController::class, 'addnotes'])->name('clients.addnotes')
                    ->middleware('permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note');
                Route::post('/quick-visit-note', [ClientNoteController::class, 'quickVisitNote'])->name('clients.quick_visit_note')
                    ->middleware('permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note');

                // تعديل وحذف الملاحظات
                Route::get('/notes/{noteId}/edit', [ClientNoteController::class, 'editNote'])->name('clients.notes.edit')
                    ->middleware('permission:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own');
                Route::put('/notes/{noteId}', [ClientNoteController::class, 'updateNote'])->name('clients.notes.update')
                    ->middleware('permission:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own');
                Route::delete('/notes/{noteId}', [ClientNoteController::class, 'destroyNote'])->name('clients.notes.destroy')
                    ->middleware('permission:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own');
                Route::delete('/notes/{noteId}/attachment', [ClientNoteController::class, 'deleteNoteAttachment'])->name('clients.notes.deleteAttachment')
                    ->middleware('permission:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own');
                Route::get('/notes/{noteId}/print', [ClientNoteController::class, 'printNote'])->name('clients.notes.print')
                    ->middleware('permission:client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own');

                // دليل موظفي العملاء
                Route::get('/employees-directory', [ClientEmployeeController::class, 'clientEmployeesDirectory'])->name('clients.employees_directory')
                    ->middleware('permission:clients_assign_employees');

                Route::post('/store', [ClientController::class, 'store'])->name('clients.store')
                    ->middleware('permission:clients_add_client');
                Route::get('/clients/{client_id}/notes', [ClientController::class, 'getClientNotes'])
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/edit/{id}', [ClientController::class, 'edit_question'])->name('clients.edit')
                    ->middleware('permission:clients_edit_data');
                Route::get('/show/client/{id}', [ClientController::class, 'show'])->name('clients.show')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                /* `register-visit` بابٌ لا غرفة: `registerVisit()` تجد زيارة
                   اليوم أو تُنشئها ثمّ تُحوّل فوراً إلى `clients.visitWorkspace`،
                   ولا تعرض شيئاً بنفسها.

                   فصلاحياتُه يجب أن تكون **بسعة راوت مساحة الزيارة نفسه**.
                   كان أضيق منه بـ`clients_visit_workspace_access`: من يملكها
                   وحدها يفتح مساحة الزيارة مباشرةً، ويُردّ بـ403 عند الباب
                   الذي يوصله إليها — «لا يوجد صلاحيات» على رابط
                   `/register-visit/{id}` رغم أنّ الغرفة مفتوحةٌ له. */
                Route::get('/register-visit/{id}', [ClientController::class, 'registerVisit'])->name('clients.registerVisit')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                Route::post('/register-visit/{id}', [ClientController::class, 'storeVisit'])->name('clients.storeVisit')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');

                // مساحة عمل «إتمام الزيارة» — تُفتح بعد تسجيل الوصول أعلاه.
                // كانت العشرة مساراتٍ بلا صلاحيةٍ إطلاقاً (الميدلوير العام فقط) —
                // نفس صلاحيتَي تسجيل الزيارة نفسها في `registerVisit`/`storeVisit` أعلاه.
                // `clients_must_visit_before_view` أُضيفت لكلّ راوتٍ هنا لاحقاً: من
                // يحمل هذه الصلاحية وحدها (بلا `clients_visits_track`/`clients_itinerary_add`)
                // يُعاد توجيهه إلى هذا المسار عمداً (`ClientController::show()` و
                // `client_cards.blade.php`) — فحرمانُه من دخوله فعلياً كان يحبسه
                // بين توجيهٍ إجباريّ إلى مسارٍ يُرفض بصلاحيةٍ لا يملكها.
                /* المدخل الظاهر للمندوب: عميلٌ فقط. يجد زيارة اليوم أو
                   يُنشئها ثمّ يُحوّل إلى الرابط القانوني أدناه — فلا يمرّ
                   بـ`register-visit` ولا يراه أحد. */
                Route::get('/{client}/visit-workspace', [VisitWorkspaceController::class, 'open'])->name('clients.openVisitWorkspace')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                /* «إرجاع لخط السير» — عميلٌ أُخفي بعد إنهاء زيارته يعود للمندوب في
                   اليوم نفسه. بصلاحية دخول الزيارة نفسها: من يُرجع عميلاً هو من
                   سيزوره. والإعداد وملكيّة الزيارة يُفحصان في المتحكّم. */
                Route::post('/{client}/return-to-itinerary', [VisitWorkspaceController::class, 'returnToItinerary'])->name('clients.returnToItinerary')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                Route::get('/{client}/visits/{visit}/workspace', [VisitWorkspaceController::class, 'show'])->name('clients.visitWorkspace')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                Route::post('/visits/{visit}/start', [VisitWorkspaceController::class, 'start'])->name('clients.visits.workspaceStart')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                Route::post('/visits/{visit}/toggle-pause', [VisitWorkspaceController::class, 'togglePause'])->name('clients.visits.togglePause')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                /* هذا المسار ينادي `ClientNoteController::addnotes()` نفسها التي
                   يحرسها راوت `clients.addnotes` بصلاحية الملاحظات المخصَّصة —
                   لكن النداء هنا مباشرٌ في PHP فيتجاوز ميدلوير ذاك الراوت
                   بالكامل. فمن يملك صلاحية تتبّع الزيارات فقط (بلا أي صلاحية
                   إضافة ملاحظات) كان يضيف ملاحظاتٍ من هنا رغم ذلك. */
                Route::post('/visits/{visit}/notes', [VisitWorkspaceController::class, 'storeNote'])->name('clients.visits.storeNote')
                    ->middleware([
                        'permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view',
                        'permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note',
                    ]);
                /* خطوات الزيارة الإلزاميّة الثلاث الأولى: مسؤول العميل، ثم
                   دوامه، ثم الملاحظة (`storeNote` أعلاه). `openDocument`
                   يفتح مستندات الخطوة الرابعة بختم رابط العودة إلى الزيارة.
                   راجع ترويسة `VisitWorkspaceController`.

                   حُذفت من هنا مسارات الأصول/الجرد والمهام ونتيجة الزيارة
                   اليدوية — لم تعد لها شاشة: العهد والأصول والمواعيد خرجت
                   من مساحة عمل الزيارة، والنتيجة تُستنتج من البيع/التحصيل
                   لا تُختار. */
                /* خطوة «رصد المنافسين» بعد الملاحظة — بصلاحية الملاحظات نفسها:
                   الرصد كان قسماً داخل مودال الملاحظة وصار خطوتها التالية. */
                Route::post('/visits/{visit}/competitor-observations', [VisitWorkspaceController::class, 'storeCompetitorStep'])->name('clients.visits.storeCompetitorStep')
                    ->middleware([
                        'permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view',
                        'permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note',
                    ]);
                Route::post('/visits/{visit}/contact', [VisitWorkspaceController::class, 'storeContact'])->name('clients.visits.storeContact')
                    ->middleware([
                        'permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view',
                        'permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note',
                    ]);
                Route::post('/visits/{visit}/contact-shift', [VisitWorkspaceController::class, 'storeContactShift'])->name('clients.visits.storeContactShift')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                /* منافسٌ جديد يضيفه المندوب وهو داخل الزيارة.

                   بصلاحية **الملاحظات** لا بصلاحية كتالوج المنافسين: من يكتب
                   ملاحظة الزيارة يجب أن يستطيع تسمية منافسٍ رآه فيها. ربطُها
                   بـ`clients_competitors_add` (صلاحية شاشة الإدارة) كان يعني
                   عملياً أنّ أحداً لا يملكها — فالمندوب يرى منافساً جديداً
                   ولا سبيل له إلا ترك الرصد ناقصاً. شاشةُ الكتالوج تبقى
                   محروسةً بصلاحياتها الأربع كما هي. */
                Route::post('/visits/{visit}/competitors', [VisitWorkspaceController::class, 'storeQuickCompetitor'])->name('clients.visits.storeQuickCompetitor')
                    ->middleware([
                        'permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view',
                        'permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note',
                    ]);
                Route::get('/visits/{visit}/document/{type}', [VisitWorkspaceController::class, 'openDocument'])->name('clients.visits.openDocument')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                /* إشعارات الإدارة عن العميل — «تم الاطّلاع» والردّ.

                   لماذا هنا لا في `notifications.client.popup.markRead`
                   و`notifications.client.reply`: ذانك المساران خلف
                   `permission:sales.notifications.view` (صلاحية شاشة إشعارات
                   المبيعات الإدارية) و`plan:notifications`، ولا يملك دور
                   «مندوب مبيعات» الأولى — وهو المستقبِل الوحيد لهذه الإشعارات
                   عملياً. فكان كلُّ نداءٍ منه يُردّ 403 صامتاً.

                   والحارس هنا صاحبُ الإشعار نفسُه: `ownedClientNotification()`
                   تقيّده بـ`receiver_id` وبعميل هذه الزيارة معاً. والردّ فوقه
                   بصلاحية الردّ العامّة — يملكها المندوب. */
                Route::post('/visits/{visit}/notifications/{notification}/read', [VisitWorkspaceController::class, 'markNotificationRead'])->name('clients.visits.notifications.read')
                    ->middleware('permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view');
                Route::post('/visits/{visit}/notifications/{notification}/reply', [VisitWorkspaceController::class, 'replyToNotification'])->name('clients.visits.notifications.reply')
                    ->middleware([
                        'permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view',
                        'permission:notifications_reply_to_notifications',
                    ]);
                /* إغلاق الزيارة — إنهاءً أو إلغاءً — بصلاحيةٍ مستقلّة فوق
                   صلاحية دخول مساحة العمل: من يُسجّل الزيارة ليس بالضرورة من
                   يُقرّر إغلاقها. والموقع يُتحقَّق منه في المتحكّم للاثنين. */
                Route::post('/visits/{visit}/complete', [VisitWorkspaceController::class, 'complete'])->name('clients.visits.completeWorkspace')
                    ->middleware([
                        'permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view',
                        'permission:clients_visit_complete',
                    ]);
                Route::post('/visits/{visit}/cancel', [VisitWorkspaceController::class, 'cancel'])->name('clients.visits.cancelWorkspace')
                    ->middleware([
                        'permission:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view',
                        'permission:clients_visit_cancel',
                    ]);

                /* تبريرات الزيارات — موحَّدةٌ داخل Modules/Client (راجع
                   `app/Models/VisitJustification.php` و
                   `VisitJustificationController`/`IncompleteVisitsController`).
                   `auth` مطبَّقٌ أصلاً على كل هذه المجموعة (السطر أعلاه). */

                // الجزء الذاتي-الخدمة (IncompleteVisitsController) — الموظف يقدّم/يعدّل تبريره هو.
                // بلا صلاحيةٍ عمداً على مسار الإفلات الإجباري (`incomplete-visits-justification`):
                // هذا هو المخرج الوحيد من حجب CheckIncompleteVisits؛ حراسته
                // بصلاحيةٍ قد تُحبس بها موظفٌ لا يملكها إلى الأبد.
                Route::get('/incomplete-visits-justification', [IncompleteVisitsController::class, 'showJustificationForm'])
                    ->name('incomplete.visits.justification')
                    ->middleware(\App\Http\Middleware\CheckJustificationExemption::class);
                Route::post('/incomplete-visits-justification', [IncompleteVisitsController::class, 'submitJustification'])
                    ->name('incomplete.visits.submit')
                    ->middleware(\App\Http\Middleware\CheckJustificationExemption::class);

                // «تبريراتي» — سجلّ الموظف الشخصي (تبريراته الفردية + فترات الإعفاء المُمنوحة له).
                Route::get('/my-visit-justifications', [IncompleteVisitsController::class, 'showMyJustifications'])
                    ->name('employee.visit-justifications.index')
                    ->middleware(['permission:clients_visit_justifications_view_own|clients_visit_justifications_view_all', \App\Http\Middleware\CheckJustificationExemption::class]);
                Route::get('/my-visit-justifications/{id}', [IncompleteVisitsController::class, 'showJustificationDetails'])
                    ->name('employee.visit-justifications.show')
                    ->middleware(['permission:clients_visit_justifications_view_own|clients_visit_justifications_view_all', \App\Http\Middleware\CheckJustificationExemption::class]);
                Route::get('/my-visit-justifications/{id}/edit', [IncompleteVisitsController::class, 'editJustification'])
                    ->name('employee.visit-justifications.edit')
                    ->middleware(['permission:clients_visit_justifications_view_own|clients_visit_justifications_view_all', \App\Http\Middleware\CheckJustificationExemption::class]);
                Route::put('/my-visit-justifications/{id}', [IncompleteVisitsController::class, 'updateJustification'])
                    ->name('employee.visit-justifications.update')
                    ->middleware(['permission:clients_visit_justifications_view_own|clients_visit_justifications_view_all', \App\Http\Middleware\CheckJustificationExemption::class]);

                // الجزء الإداري (VisitJustificationController) — إدارة فترات التبرير/الإعفاء
                // ومراجعة تبريرات الزيارات الفردية. صلاحياتٌ حبيبيةٌ مستقلة
                // بدل `employee_visit_justifications_view` الشاملة القديمة —
                // راجع مجموعة `visit_justifications` في ClientsPermissions.
                Route::get('/admin/visit-justifications', [VisitJustificationController::class, 'index'])
                    ->name('admin.visit-justifications.index')
                    ->middleware('permission:clients_visit_justifications_view_all');
                Route::get('/admin/visit-justifications/create', [VisitJustificationController::class, 'create'])
                    ->name('admin.visit-justifications.create')
                    ->middleware('permission:clients_visit_justifications_create');
                Route::post('/admin/visit-justifications', [VisitJustificationController::class, 'store'])
                    ->name('admin.visit-justifications.store')
                    ->middleware('permission:clients_visit_justifications_create');

                // تبريرٌ جماعي بالفترة — موظفٌ واحد وفترةٌ واحدة، وفترة تبريرٍ
                // تُنشأ تلقائياً لكل عميلٍ له زياراتٌ غير مكتملة ضمنها. قبل
                // مسار `{id}` أدناه عمداً: `bulk`/`bulk-search` كلمتان حرفيّتان
                // لا معرّفات، وتصطدمان بذاك المسار العام لو جاء أولاً.
                Route::get('/admin/visit-justifications/bulk', [VisitJustificationController::class, 'bulkPage'])
                    ->name('admin.visit-justifications.bulk')
                    ->middleware('permission:clients_visit_justifications_create');
                Route::get('/admin/visit-justifications/bulk-search', [VisitJustificationController::class, 'bulkSearch'])
                    ->name('admin.visit-justifications.bulk-search')
                    ->middleware('permission:clients_visit_justifications_create');
                Route::post('/admin/visit-justifications/bulk-store', [VisitJustificationController::class, 'bulkStore'])
                    ->name('admin.visit-justifications.bulk-store')
                    ->middleware('permission:clients_visit_justifications_create');

                Route::post('/admin/visit-justifications/approve-all', [VisitJustificationController::class, 'approveAll'])
                    ->name('admin.visit-justifications.approve-all')
                    ->middleware('permission:clients_visit_justifications_approve');
                Route::post('/admin/visit-justifications/approve-by-employee', [VisitJustificationController::class, 'approveByEmployee'])
                    ->name('admin.visit-justifications.approve-by-employee')
                    ->middleware('permission:clients_visit_justifications_approve');
                Route::get('/admin/visit-justifications/{id}', [VisitJustificationController::class, 'show'])
                    ->name('admin.visit-justifications.show')
                    ->middleware('permission:clients_visit_justifications_view_all');
                Route::get('/admin/visit-justifications/{id}/edit', [VisitJustificationController::class, 'edit'])
                    ->name('admin.visit-justifications.edit')
                    ->middleware('permission:clients_visit_justifications_edit');
                Route::put('/admin/visit-justifications/{id}', [VisitJustificationController::class, 'update'])
                    ->name('admin.visit-justifications.update')
                    ->middleware('permission:clients_visit_justifications_edit');
                Route::delete('/admin/visit-justifications/{id}', [VisitJustificationController::class, 'destroy'])
                    ->name('admin.visit-justifications.destroy')
                    ->middleware('permission:clients_visit_justifications_delete');
                Route::post('/admin/visit-justifications/{id}/approve-period', [VisitJustificationController::class, 'approvePeriod'])
                    ->name('admin.visit-justifications.approve-period')
                    ->middleware('permission:clients_visit_justifications_approve');
                Route::post('/admin/visit-justifications/{id}/reject-period', [VisitJustificationController::class, 'rejectPeriod'])
                    ->name('admin.visit-justifications.reject-period')
                    ->middleware('permission:clients_visit_justifications_approve');

                // مراجعة تبريرات الزيارات الفردية (قديمة، بلا تغيير في الآلية).
                Route::post('/admin/visit-justifications/{id}/approve', [VisitJustificationController::class, 'approve'])
                    ->name('admin.visit-justifications.approve')
                    ->middleware('permission:clients_visit_justifications_approve');
                Route::post('/admin/visit-justifications/{id}/reject', [VisitJustificationController::class, 'reject'])
                    ->name('admin.visit-justifications.reject')
                    ->middleware('permission:clients_visit_justifications_approve');

                /* «ملاحظات الموظفين» — كانت مسجَّلةً مرّتين بالحرف (نفس الرابط
                   والمتحكّم والصلاحية) في هذا الملف؛ الأولى أعلى بقيت، وهذه
                   النسخة المكرَّرة حُذفت. */

                // ═══════════════════════════════════════════════════════════
                // الزيارات المنسية — إدارة الزيارات التي لم يُنهِها الموظف
                // ═══════════════════════════════════════════════════════════

                /* لوحة تحكم خط السير — عرض الزيارات الجارية والمنسية بشكل
                   مرئي وسهل الوصول، مع إمكانية إنهاء الزيارات من الصفحة. */
                Route::get('/itinerary-dashboard', [VisitController::class, 'itineraryDashboard'])
                    ->name('visits.itinerary-dashboard')
                    ->middleware('permission:clients_view_forgotten_visits_own|clients_view_forgotten_visits_all|clients_view_itinerary_dashboard');

                /* الموظف يرى زياراته المنسية ويُنهيها من البعيد إذا نسي
                   عند العميل — بلا هذه الصلاحية يُحجَب من كل العمل. */
                Route::get('/forgotten-visits', [VisitController::class, 'listForgottenVisits'])
                    ->name('forgotten-visits.index')
                    ->middleware('permission:clients_view_forgotten_visits_own|clients_view_forgotten_visits_all');

                /* إنهاء زيارة منسية: الموظف ينهي زيارته، والمدير ينهي زيارة
                   أيّ موظف. التحقق من الصلاحية يتم داخل المتحكّم. */
                Route::post('/forgotten-visits/{visit}/close', [VisitController::class, 'closeForgottenVisit'])
                    ->name('forgotten-visits.close')
                    ->middleware('permission:clients_close_forgotten_visits_own|clients_force_close_forgotten_visits');

                /* إلغاء زيارة منسية: عندما تكون خطأً تماماً أو لا يجب
                   الاستمرار فيها. */
                Route::post('/forgotten-visits/{visit}/cancel', [VisitController::class, 'cancelForgottenVisit'])
                    ->name('forgotten-visits.cancel')
                    ->middleware('permission:clients_cancel_forgotten_visits');

                Route::get('/statement/{id}', [ClientController::class, 'statement'])->name('clients.statement')
                    ->middleware('permission:clients_view_statement');
                // مطبوعات كشف الحساب — بقوالب «قوالب المستندات» لا بنسخِ
                // محتوى الصفحة إلى نافذةٍ بأنماطٍ مكتوبةٍ في JavaScript
                Route::get('/statement/{id}/print/{template?}', [ClientController::class, 'statementPrint'])->name('clients.statementPrint')
                    ->middleware('permission:clients_view_statement');
                Route::get('/statement/{id}/pdf/{template?}', [ClientController::class, 'statementPdf'])->name('clients.statementPdf')
                    ->middleware('permission:clients_view_statement');
                /* استبعاد العميل من التحقّق بالموقع ومن قيد الاستبدال.
                   الزرّان موجودان في `client::partials.actions-buttons` منذ
                   مدّة، والعمودان في `clients` والصلاحيتان في الكتالوج
                   و`Client::requiresVisitLocation()` تقرأ الأوّل — ولم يكن
                   للمسارين وجود، فكلّ من يملك الصلاحية يفتح شاشة العميل
                   على `RouteNotFoundException`. */
                Route::post('/{id}/toggle-exempt-location', [ClientController::class, 'toggleExemptLocation'])
                    ->name('clients.toggleExemptLocation')
                    ->middleware(['permission:clients_exempt_visit_location', 'permission:clients_manage_exemptions']);
                Route::post('/{id}/toggle-exempt-exchange', [ClientController::class, 'toggleExemptExchange'])
                    ->name('clients.toggleExemptExchange')
                    ->middleware(['permission:clients_exempt_exchange_transaction', 'permission:clients_manage_exemptions']);

                Route::put('/update/{id}', [ClientController::class, 'update'])->name('clients.update')
                    ->middleware('permission:clients_edit_data');
                Route::delete('/{id}', [ClientController::class, 'destroy'])->name('clients.destroy')
                    ->middleware('permission:clients_delete');
                Route::post('/delete-multiple', [ClientController::class, 'deleteMultiple'])->name('clients.deleteMultiple')
                    ->middleware('permission:clients_delete');
                Route::get('/contacts', [ClientController::class, 'contacts'])->name('clients.contacts')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/first', [ClientController::class, 'getFirstClient'])->name('clients.first')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/next', [ClientController::class, 'getNextClient'])->name('clients.next')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/previous', [ClientController::class, 'getPreviousClient'])->name('clients.previous')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::post('/clients/{client}/force-show', [ClientController::class, 'forceShow'])->name('clients.force-show')
                    ->middleware('permission:clients_hide_from_map');
                Route::post('/clients/{client}/assign-employees', [ClientEmployeeController::class, 'assignEmployees'])->name('clients.assign-employees')
                    ->middleware('permission:clients_assign_employees');
                Route::post('/{client}/remove-employee', [ClientEmployeeController::class, 'removeEmployee'])->name('clients.remove-employee')
                    ->middleware('permission:clients_assign_employees');
                Route::get('/clients/{client}/assigned-employees', [ClientEmployeeController::class, 'getAssignedEmployees'])->name('clients.get-assigned-employees')
                    ->middleware('permission:clients_assign_employees');
                Route::get('/clients_management/clients/all', [ClientController::class, 'getAllClients'])->name('clients.all')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/show-contant/{id}', [ClientController::class, 'show_contant'])->name('clients.show_contant')
                    ->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');
                Route::get('/export', [ClientController::class, 'export'])->name('clients.export')
                    ->middleware('permission:clients_export');
                Route::post('/export-all', [ClientController::class, 'exportAllClients'])->name('clients.exportAll')
                    ->middleware('permission:clients_export');
                Route::post('/{client}/write-off-debt', [ClientController::class, 'writeOffBadDebt'])->name('clients.write-off-debt')
                    ->middleware('permission:clients_manage_debts');
                Route::get('/{client}/add-doubtful-debt-quick', [ClientController::class, 'addDoubtfulDebtQuick'])->name('clients.add-doubtful-debt-quick')
                    ->middleware('permission:clients_manage_debts');

                // بحث Ajax
                Route::get('/clients/search', function (Request $request) {
                    $query = trim((string) $request->query('query'));

                    if ($query === '') {
                        return response()->json([]);
                    }

                    $clients = Client::with('latestStatus')
                        ->where('trade_name', 'LIKE', "%{$query}%")
                        ->orWhere('first_name', 'LIKE', "%{$query}%")
                        ->orWhere('last_name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', 'LIKE', "%{$query}%")
                        ->orWhere('mobile', 'LIKE', "%{$query}%")
                        ->orWhere('city', 'LIKE', "%{$query}%")
                        ->orWhere('region', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%")
                        ->limit(10)
                        ->get();

                    return response()->json($clients);
                })->middleware('permission:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary');

                /* بوابةُ الدخول العامة لا أكثر — والتقريرُ نفسُه محروسٌ
                   بصلاحيته في بانية متحكّمه (`clients.reports.*`)، كما في
                   المبيعات والمشتريات. أُضيفت `clients.reports.view` إلى
                   البوابة كي يدخل من مُنح تقريراً واحداً بلا حاجةٍ إلى
                   إحدى صلاحيات النظام القديم؛ وبقيّةُ ما في المجموعة
                   (سجلّ النشاط، CRM، الديون المشكوك فيها) محروسٌ بصلاحياته
                   هو على مساره أو في بانيته، فلا يوسّع هذا شيئاً منها. */
                Route::prefix('ClientReport')->middleware('permission:client_reports_view|clients.reports.view')->group(function () {
                    Route::get('/', [CustomerReportController::class, 'index'])->name('ClientReport.index');

                    // =============================================
                    // تقرير أعمار ديون الفواتير - CustomerDebtsController
                    // =============================================
                    /* `debtReconstructionInv` كان اسماً واحداً على مسارين بمتحكّمٍ
                       ودالّةٍ واحدة — الاسم بلا رابطٍ إليه (`/debtReconstructionInv`
                       القديم) صار `ClientReport.debtReconstructionInvLegacy`
                       بلا تغييرٍ في السلوك: من يستخدم `route('ClientReport.debtReconstructionInv')`
                       اليوم يصل `/debt-reconstruction` كما كان تماماً. */
                    Route::get('/debtReconstructionInv', [CustomerDebtsController::class, 'debtReconstructionInv'])->name('ClientReport.debtReconstructionInvLegacy');
                    Route::get('/clients/search', [CustomerDebtsController::class, 'searchClients'])->name('ClientReport.searchClients');
                    Route::get('/debt-reconstruction/ajax', [CustomerDebtsController::class, 'invoiceDebtAgingAjax'])->name('ClientReport.debtReconstructionInvAjax');
                    Route::get('/debt-reconstruction', [CustomerDebtsController::class, 'debtReconstructionInv'])->name('ClientReport.debtReconstructionInv');
                    Route::get('/invoice-debt-aging-ajax', [CustomerDebtsController::class, 'invoiceDebtAgingAjax'])->name('ClientReport.invoiceDebtAgingAjax');
                    /* اسمٌ كان مكرَّراً مع `CustomerBalancesController::getBranchesRegionData`
                       أدناه — الفائز بالاسم دائماً هناك (آخر تسجيل)، فهذا لم
                       يكن يُستدعى بالاسم قطّ. صار اسماً خاصّاً بلا تغييرٍ في
                       أيّ سلوكٍ فعليّ. */
                    Route::get('/get-branches-region-data', [CustomerDebtsController::class, 'getBranchesRegionData'])->name('ClientReport.getBranchesRegionDataDebts');

                    // =============================================
                    // تقرير أعمار ديون الأستاذ العام - CustomerDebtAgingController
                    // =============================================
                    Route::get('/debt-aging-general-ledger', [CustomerDebtAgingController::class, 'debtAgingGeneralLedger'])->name('ClientReport.debtAgingGeneralLedger');
                    Route::get('/debt-aging-general-ledger/ajax', [CustomerDebtAgingController::class, 'debtAgingGeneralLedgerAjax'])->name('ClientReport.debtAgingGeneralLedgerAjax');

                    // =============================================
                    // تقرير دليل العملاء - CustomerGuideController
                    // =============================================
                    Route::get('/customerGuide', [CustomerGuideController::class, 'customerGuide'])->name('ClientReport.customerGuide');
                    Route::get('/customer-guide-ajax', [CustomerGuideController::class, 'customerGuideAjax'])->name('ClientReport.customerGuideAjax');
                    Route::get('/customer-guide-map-ajax', [CustomerGuideController::class, 'customerGuideMapAjax'])->name('ClientReport.customerGuideMapAjax');
                    Route::get('/get-neighborhoods', [CustomerGuideController::class, 'getNeighborhoods'])->name('ClientReport.getNeighborhoods');
                    Route::get('/get-region-groups', [CustomerGuideController::class, 'getRegionGroups'])->name('ClientReport.getRegionGroups');
                    Route::get('/get-cities', [CustomerGuideController::class, 'getCities'])->name('ClientReport.getCities');

                    // =============================================
                    // تقرير أرصدة العملاء - CustomerBalancesController
                    // =============================================
                    Route::get('/customerBalances', [CustomerBalancesController::class, 'customerBalances'])->name('ClientReport.customerBalance');
                    Route::get('/customer-balances-ajax', [CustomerBalancesController::class, 'customerBalancesAjax'])->name('ClientReport.customerBalancesAjax');
                    Route::get('/getBranchesRegionData', [CustomerBalancesController::class, 'getBranchesRegionData'])->name('ClientReport.getBranchesRegionData');

                    // تقرير محافظ العملاء — أرصدةٌ وسجلُّ حركات (الصلاحية في المتحكّم)
                    Route::get('/customerWallets', [\Modules\Client\Http\Controllers\Customers\CustomerWalletReportController::class, 'index'])->name('ClientReport.customerWallets');

                    // تقرير المنافسين، والعملاء المسندون للموظفين (الصلاحية في المتحكّم)
                    Route::get('/competitors', [\Modules\Client\Http\Controllers\Customers\CustomerCompetitorReportController::class, 'index'])->name('ClientReport.competitors');
                    Route::get('/assignments', [\Modules\Client\Http\Controllers\Customers\CustomerAssignmentReportController::class, 'index'])->name('ClientReport.assignments');

                    // =============================================
                    // تقرير مواعيد العملاء — صفحة مستقلة بأقسام مجمَّعة
                    // على تصميم أعمار الديون (منفصل عن تقرير الحجوزات)
                    // =============================================
                    Route::get('/customerAppointments', [CustomerAppointmentsController::class, 'customerAppointments'])->name('ClientReport.customerAppointments');
                    Route::get('/customerAppointments/export/{report}', [CustomerAppointmentsController::class, 'export'])->name('ClientReport.customerAppointmentsExport');

                    // =============================================
                    // تقرير كشف حساب العملاء - CustomerAccountStatementController
                    // =============================================
                    Route::get('/customerAccountStatement', [CustomerAccountStatementController::class, 'customerAccountStatement'])->name('ClientReport.customerAccountStatement');
                    Route::get('/customer-account-statement-ajax', [CustomerAccountStatementController::class, 'customerAccountStatementAjax'])->name('ClientReport.customerAccountStatementAjax');

                    // =============================================
                    // تقرير أقساط العملاء - CustomerInstallmentsController
                    // =============================================
                    Route::get('/reports/customers/installments', [CustomerInstallmentsController::class, 'customerInstallments'])->name('ClientReport.customerInstallments');
                    Route::get('/reports/customers/installments/ajax', [CustomerInstallmentsController::class, 'customerInstallmentsAjax'])->name('ClientReport.customerInstallmentsAjax');

                    // =============================================
                    // الدوال المشتركة - CustomerReportController
                    // =============================================
                    Route::get('/get-employee-groups', [CustomerReportController::class, 'getEmployeeGroups'])->name('ClientReport.getEmployeeGroups');
                    Route::get('/get-group-clients', [CustomerReportController::class, 'getGroupClients'])->name('ClientReport.getGroupClients');
                    Route::post('/invoice-debt-aging/export', [CustomerReportController::class, 'exportInvoiceDebtAging'])->name('ClientReport.exportInvoiceDebtAging');
                    Route::get('/invoices/month/{month}', [CustomerReportController::class, 'getInvoicesByMonth']);
                    Route::get('/statement/{id}', [CustomerReportController::class, 'getCustomerStatement'])->name('customer-report.statement');
                    Route::get('/customerPayments', [CustomerReportController::class, 'customerPayments'])->name('ClientReport.customerPayments');
                    Route::get('/customer-payments-ajax', [CustomerReportController::class, 'customerPaymentsAjax'])->name('ClientReport.customerPaymentsAjax');

                    // =============================================
                    // تقرير أهداف العملاء
                    // =============================================
                    Route::get('/customer-targets', [\Modules\Client\Http\Controllers\Customers\CustomerTargetReportController::class, 'index'])->name('ClientReport.customerTargets');
                });

                // CRM
                Route::prefix('CRM')->middleware('permission:clients_crm_view')->group(function () {
                    /* كانتا `clients.invoices`/`clients.notes` — نفس اسمي مسارين
                       مسجَّلين أعلاه (L323, L325) بمتحكّمٍ مختلف؛ لارافيل يحسم
                       التعارض بآخر تسجيل فيفوز هذان بلا قصد. لا استدعاء بأيّ
                       من الاسمين القديمين في أي Blade/JS (بحثٌ شامل)، فالتسمية
                       هنا خاصّةٌ بلا أثرٍ على أي سلوك. */
                    Route::get('/clients/{clientId}/invoices', [CRMController::class, 'getClientDetails'])->name('crm.invoices');
                    /* `CRMController::getClientNotes` لم توجد قط — مسارٌ ميت
                       (500 دائم لو نودي) وغير مستدعًى من أي مكان؛ حُذف بدل
                       حراسة رابطٍ معطوب. تبويب الملاحظات في شاشة العلاقات
                       يُغذَّى فعلاً من `clients.get-details` (L253). */
                    Route::get('/mang_client', [CRMController::class, 'mang_client'])->name('clients.mang_client');
                    Route::get('/group-clients', [CRMController::class, 'getGroupClients'])->name('crm.get-group-clients');
                    Route::get('/search-client-node', [CRMController::class, 'searchClientNode'])->name('crm.search-client-node');
                    Route::post('/render-note', [CRMController::class, 'renderNote'])->name('clients.render-note');
                });

                /* الاسمُ كان `ActivityLog.index` — نفسُ اسم مسار وحدة الإعدادات
                   العامة (`Modules/Sitting/routes/web.php`، `/activity`)، فآخِرُ
                   تسجيلٍ يفوز بالاسم ويُخفي هذا تماماً عن `route()`؛ ولم يكن
                   مربوطاً بأي رابطٍ في واجهة العملاء أصلاً (بحثٌ شامل لم يجد
                   شيئاً)، فبقي مساراً ميتاً. صار اسماً خاصاً بوحدة العملاء
                   ومصفّى إليها فعلاً عبر `module`، لا نسخةً مموّهة من الشاشة
                   العامة. شاشة الإعدادات العامة (`/activity`) لم تُمسّ. */
                Route::get('/log/report', [ActivityLogController::class, 'index'])
                    ->name('clients.activity_log')
                    ->defaults('module', 'Client')
                    ->middleware('permission:clients_view_all_activity_logs|clients_view_own_activity_log');
                // الديون المشكوك فيها
                Route::prefix('doubtful-debts')->middleware(['client.feature:doubtful_debts', 'permission:clients_manage_debts'])->group(function () {
                    Route::get('/', [DoubtfulDebtController::class, 'index'])->name('doubtful-debts.index');
                    Route::get('/{id}', [DoubtfulDebtController::class, 'show'])->name('doubtful-debts.show');
                    Route::post('/{id}/update-collection', [DoubtfulDebtController::class, 'updateCollection'])->name('doubtful-debts.update-collection');
                    Route::post('/{id}/reverse-collection', [DoubtfulDebtController::class, 'reverseCollection'])->name('doubtful-debts.reverse-collection');
                    Route::post('/{id}/reverse-without-collection', [DoubtfulDebtController::class, 'reverseWithoutCollection'])->name('doubtful-debts.reverse-without-collection');
                    Route::post('/{id}/write-off', [DoubtfulDebtController::class, 'writeOff'])->name('doubtful-debts.write-off');
                });

                /* محافظ العملاء — البند ١١ من تقرير الإصلاح.
                   انتُقلت من وحدة المبيعات: المحفظة صفةٌ للعميل لا
                   للفاتورة، ورصيدُه عندنا يُقرأ من ملفّه.
                   والمتحكّم بلا `use` لأن الملفّ يعلن نفس مساحة الأسماء. */
                /* أربع شاشات كبقيّة مستندات النظام: قائمة، وعرض، وإضافة،
                   وتعديل. وحلّت `store`/`update` محلّ `deposit` و`withdraw`
                   و`pay-invoice` الثلاثة — نموذجٌ واحد يقود الأنواع كلَّها،
                   وصلاحيةُ النوع تُفحَص بالنوع المرسَل داخل المتحكّم.

                   و`movements/{transaction}` تحت العميل لا مستقلّةً: الحركة
                   لا تُقرأ ولا تُعدَّل خارج محفظتها، والمسار يفرض ذلك بدل
                   أن يُترك للمتحكّم وحده. */
                Route::prefix('client-wallets')->group(function () {
                    Route::get('/', [ClientWalletController::class, 'index'])->name('clients.wallet.index')
                        ->middleware('permission:clients.wallet.view');
                    Route::get('/{client}/movements/create', [ClientWalletController::class, 'create'])->name('clients.wallet.create')
                        ->middleware('permission:clients.wallet.deposit|clients.wallet.withdraw|clients.wallet.pay_invoice');
                    Route::post('/{client}/movements', [ClientWalletController::class, 'store'])->name('clients.wallet.store')
                        ->middleware('permission:clients.wallet.deposit|clients.wallet.withdraw|clients.wallet.pay_invoice');
                    Route::get('/{client}/movements/{transaction}/edit', [ClientWalletController::class, 'edit'])->name('clients.wallet.edit')
                        ->middleware('permission:clients.wallet.deposit|clients.wallet.withdraw|clients.wallet.pay_invoice');
                    Route::put('/{client}/movements/{transaction}', [ClientWalletController::class, 'update'])->name('clients.wallet.update')
                        ->middleware('permission:clients.wallet.deposit|clients.wallet.withdraw|clients.wallet.pay_invoice');
                    Route::get('/{client}', [ClientWalletController::class, 'show'])->name('clients.wallet.show')
                        ->middleware('permission:clients.wallet.view');
                });

                // التعيين الجماعي للعملاء إلى الموظفين
                Route::prefix('bulk-assignment')->middleware('permission:clients_assign_employees|client_follow_up_assign_clients_to_employees')->group(function () {
                    Route::get('/', [ClientAssignmentController::class, 'index'])->name('clients.bulk-assignment.index');
                    /* التعيينات الحالية — تقريرُ «العملاء المسندون للموظفين» نفسُه، من
                       مسارٍ تحرسه صلاحيةُ التعيين: من يملكها بلا صلاحية التقارير يصل
                       إلى عملاء الموظف من شاشة التعيين. */
                    Route::get('/current', [\Modules\Client\Http\Controllers\Customers\CustomerAssignmentReportController::class, 'index'])->name('clients.bulk-assignment.current');
                    Route::post('/transfer', [ClientAssignmentController::class, 'transferAssignment'])->name('clients.transfer-assignment');
                    Route::post('/bulk-assign', [ClientAssignmentController::class, 'bulkAssign'])->name('clients.bulk-assign');
                    Route::post('/assign-branch-to-employee', [ClientAssignmentController::class, 'assignBranchToEmployee'])->name('clients.assign-branch-to-employee');
                    Route::get('/group-info/{groupId}', [ClientAssignmentController::class, 'getGroupInfo'])->name('clients.group-info');
                    Route::post('/remove-employee-from-group', [ClientAssignmentController::class, 'removeEmployeeFromGroup'])->name('clients.remove-employee-from-group');
                    Route::post('/remove-single-assignment', [ClientAssignmentController::class, 'removeSingleAssignment'])->name('clients.remove-single-assignment');
                    // إجراءاتُ تقرير «العملاء المسندون للموظفين»: إضافة/نقل/إزالة على المحدَّدين أو كلّ نتائج الفلتر
                    Route::post('/apply-to-clients', [ClientAssignmentController::class, 'applyToClients'])->name('clients.assignments.apply');
                });

            });
        });
    }
);
