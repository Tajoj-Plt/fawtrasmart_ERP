<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Client\Http\Controllers\Reservations\AppointmentController;
use Modules\Client\Http\Controllers\Reservations\AppointmentNoteController;
use Modules\Client\Http\Controllers\Reservations\ReservationDashboardController;
use Modules\Client\Http\Controllers\Reservations\ReservationReportsController;
use Modules\Client\Http\Controllers\Reservations\ReservationsController;
use Modules\Client\Http\Controllers\Reservations\ReservationServicesController;
use Modules\Client\Http\Controllers\Reservations\ReservationSettingsController;
use Modules\Client\Http\Controllers\Reservations\ReservationWorkflowController;

/*
|--------------------------------------------------------------------------
| مسارات وحدة الحجوزات
|--------------------------------------------------------------------------
|
| البادئة `Reservations/` واحدة لكل مسارات الوحدة — وهي البادئة المسجَّلة
| في `ApplicationRegistry::groups()` ليعرف وسيط `EnsureModuleEnabled` أن
| هذا الطلب يخصّ الحجوزات، فيمنعه حين تُعطَّل الوحدة من «شاشة التطبيقات».
|
| **البادئة المكرّرة أُزيلت.** كانت `Route::prefix('Reservations')` مكتوبةً
| مرّتين متداخلتين، فالمسار الفعليّ `Reservations/Reservations/index` —
| مسارٌ لا يكتبه أحد عمداً، ويجعل بادئةَ الوحدة في سجلّ التطبيقات لا تطابق
| ما بعدها.
|
| والصلاحيات على المتحكّمات لا هنا: `$this->middleware('permission:…')` في
| البانيات، على اصطلاح `Modules/Purchases`. فيبقى ملفّ المسارات مقروءاً،
| ولا تُنسى صلاحيةٌ حين يُضاف فعلٌ إلى متحكّم قائم.
*/

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch'],
], function () {

    Route::prefix('Reservations')->middleware(['auth'])->group(function () {

        // ───────────── اللوحة والتقويم ─────────────
        Route::get('dashboard', [ReservationDashboardController::class, 'index'])->name('Reservations.dashboard');
        Route::get('calendar', [ReservationsController::class, 'calendar'])->name('Reservations.calendar');
        Route::get('calendar/feed', [ReservationsController::class, 'calendarFeed'])->name('Reservations.calendar.feed');

        // ───────────── نداءات AJAX ─────────────
        // قبل `{id}` عمداً: `slots` لو جاءت بعده لطابقته كمعرّف
        Route::get('ajax/slots', [ReservationsController::class, 'slots'])->name('Reservations.slots');
        Route::get('ajax/service-employees', [ReservationsController::class, 'serviceEmployees'])->name('Reservations.serviceEmployees');
        Route::get('ajax/service-products', [ReservationsController::class, 'serviceProducts'])->name('Reservations.serviceProducts');
        Route::get('ajax/quote', [ReservationsController::class, 'quote'])->name('Reservations.quote');
        Route::get('ajax/clients', [ReservationsController::class, 'clients'])->name('Reservations.clients');

        // شاشة «ابدأ من هنا» حُذفت بطلب المستخدم (2026-09-20) —
        // شريطُ الجاهزية `readiness-banner` باقٍ فوق الإنشاء واللوحة،
        // وروابطُه إلى الشاشة تختفي وحدها بحارس `Route::has`.

        // ───────────── الخدمات ─────────────
        Route::prefix('services')->group(function () {
            Route::get('/', [ReservationServicesController::class, 'index'])->name('Reservations.services.index');
            Route::get('create', [ReservationServicesController::class, 'create'])->name('Reservations.services.create');
            Route::post('/', [ReservationServicesController::class, 'store'])->name('Reservations.services.store');
            Route::get('{service}/edit', [ReservationServicesController::class, 'edit'])->name('Reservations.services.edit');
            Route::put('{service}', [ReservationServicesController::class, 'update'])->name('Reservations.services.update');
            Route::post('{service}/toggle', [ReservationServicesController::class, 'toggle'])->name('Reservations.services.toggle');
            Route::delete('{service}', [ReservationServicesController::class, 'destroy'])->name('Reservations.services.destroy');
        });

        /*
            «أوقات العمل والعطل» أُزيلت (2026-09-10): الدوامُ من الورديات
            والعطلُ من الموارد البشرية — `EmployeeShiftSchedule`.
        */

        // ───────────── الإعدادات ─────────────
        Route::get('settings', [ReservationSettingsController::class, 'index'])->name('Reservations.settings');
        Route::put('settings', [ReservationSettingsController::class, 'update'])->name('Reservations.settings.update');

        // ───────────── التقارير ─────────────
        Route::prefix('reports')->group(function () {
            Route::get('/', [ReservationReportsController::class, 'index'])->name('Reservations.reports.index');
            Route::get('{report}', [ReservationReportsController::class, 'show'])->name('Reservations.reports.show');
            Route::get('{report}/export', [ReservationReportsController::class, 'export'])->name('Reservations.reports.export');
        });


        // ───────────── الحجز نفسه ─────────────
        Route::get('/', [ReservationsController::class, 'index'])->name('Reservations.index');
        Route::get('index', [ReservationsController::class, 'index'])->name('Reservations.index.legacy');
        Route::get('export', [ReservationsController::class, 'export'])->name('Reservations.export');
        Route::get('create', [ReservationsController::class, 'create'])->name('Reservations.create');
        Route::post('store', [ReservationsController::class, 'store'])->name('Reservations.store');
        Route::get('client/{client}', [ReservationsController::class, 'clientHistory'])->name('Reservations.client');

        Route::prefix('{reservation}')->whereNumber('reservation')->group(function () {
            Route::get('/', [ReservationsController::class, 'show'])->name('Reservations.show');
            Route::get('edit', [ReservationsController::class, 'edit'])->name('Reservations.edit');
            Route::put('/', [ReservationsController::class, 'update'])->name('Reservations.update');
            Route::delete('/', [ReservationsController::class, 'destroy'])->name('Reservations.destroy');

            // ── سير العمل: مسارٌ لكلّ انتقال، وصلاحيةٌ لكلّ مسار ──
            Route::post('confirm', [ReservationWorkflowController::class, 'confirm'])->name('Reservations.confirm');
            Route::post('check-in', [ReservationWorkflowController::class, 'checkIn'])->name('Reservations.checkin');
            Route::post('start', [ReservationWorkflowController::class, 'start'])->name('Reservations.start');
            Route::post('complete', [ReservationWorkflowController::class, 'complete'])->name('Reservations.complete');
            Route::post('no-show', [ReservationWorkflowController::class, 'noShow'])->name('Reservations.noShow');
            Route::post('reject', [ReservationWorkflowController::class, 'reject'])->name('Reservations.reject');
            Route::post('cancel', [ReservationWorkflowController::class, 'cancel'])->name('Reservations.cancel');
            Route::post('reschedule', [ReservationWorkflowController::class, 'reschedule'])->name('Reservations.reschedule');

            /*
                ── الفاتورة والطباعة ──
                لا قبضَ ولا ردَّ في الحجز: «تحويل إلى فاتورة» يفتح شاشةَ فاتورة
                المبيعات معبّأةً، والمالُ كلُّه عليها.
            */
            Route::get('to-invoice', [ReservationsController::class, 'toInvoice'])->name('Reservations.toInvoice');
            Route::get('print', [ReservationsController::class, 'print'])->name('Reservations.print');
            Route::post('send-email', [ReservationsController::class, 'sendEmail'])->name('Reservations.sendEmail');
        });
    });

    /*
    |----------------------------------------------------------------------
    | مواعيد العملاء وملاحظاتهم — تحت `clients/` لا `Reservations/`
    |----------------------------------------------------------------------
    |
    | كانت تحت بادئة الحجوزات، فوسيطُ `EnsureModuleEnabled` ينسبها إلى
    | `booking_management`: إطفاءُ الحجوزات من «شاشة التطبيقات» كان يُغلق
    | «إضافة ملاحظة» و«ترتيب موعد» من صفحة العميل نفسها. وهي ميزاتُ عملاء
    | يحكمها قاطعا `client.feature:*` في «إعدادات وحدة العملاء».
    |
    | أسماءُ المسارات لم تتغيّر (`appointments.*`، `appointment.notes.*`،
    | `update-status`) — عشراتُ المراجع تناديها بأسمائها.
    |
    | حُذفت مساراتُ ملاحظات لا يصل إليها رابط وكانت معطوبة: القائمة (تعرض
    | قالباً بلا `$client`)، والعرض (قالبٌ غير موجود، ويطغى عليه التعديل على
    | العنوان نفسه)، والتعديل والحفظ (نموذجٌ لا يُرسل `visit_type` المطلوب
    | ويُحوِّل إلى مسارٍ غير موجود). تعديلُ ملاحظة العميل من
    | `client::notes.edit_client_note`.
    */
    Route::prefix('clients')->middleware(['auth'])->group(function () {

        Route::middleware('client.feature:appointments')->group(function () {
            // قبل `resource` عمداً: مساراتٌ ثابتة لا يبتلعها مُعرّف
            Route::get('appointments/calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
            Route::get('appointments/export', [AppointmentController::class, 'export'])->name('appointments.export');
            Route::get('appointments/show/{id}', [AppointmentController::class, 'show'])->name('appointments.show');
            // POST لا GET: تغييرُ حالةٍ بنقرة رابط يفعله أيُّ جلبٍ مسبق للصفحة وبلا CSRF
            Route::post('appointments/{id}/status/{status}', [AppointmentController::class, 'updateStatus'])->name('update-status');

            Route::resource('appointments', AppointmentController::class)->except(['show']);
        });

        Route::prefix('appointment-notes')->middleware('client.feature:notes')->group(function () {
            Route::get('create/{id}', [AppointmentNoteController::class, 'create'])->name('appointment.notes.create');
            Route::post('/', [AppointmentNoteController::class, 'store'])->name('appointment.notes.store');
            Route::get('{note}/download/{index}', [AppointmentNoteController::class, 'downloadAttachment'])->name('appointment.notes.download');
            Route::delete('{note}', [AppointmentNoteController::class, 'destroy'])->name('appointment.notes.destroy');
        });
    });
});
