<?php

use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\Api\Clients\ClientController;
use Modules\Integration\Http\Controllers\Api\Clients\ClientStatusController;
use Modules\Integration\Http\Controllers\Api\Clients\ContactController;
use Modules\Integration\Http\Controllers\Api\Clients\ForgottenVisitController;
use Modules\Integration\Http\Controllers\Api\Clients\ItineraryController;
use Modules\Integration\Http\Controllers\Api\Clients\JustificationController;
use Modules\Integration\Http\Controllers\Api\Clients\NoteController;
use Modules\Integration\Http\Controllers\Api\Clients\SettingsController;
use Modules\Integration\Http\Controllers\Api\Clients\VisitController;

/*
|--------------------------------------------------------------------------
| العملاء — /api/integration/v1/clients | itinerary | visits | notes | visit-justifications
|--------------------------------------------------------------------------
|
| يُحمَّل من routes/api.php داخل مجموعة (auth:sanctum + EnsureEmployeeUser).
| الصلاحيات هنا هي صلاحيات مسارات وحدة العملاء في الموقع حرفياً
| (Modules/Client/routes/web.php)، ومفتاح «العملاء» في شاشة التطبيقات
| (`module:customers`). النطاق (أيّ عميل، أيّ زيارة) داخل المتحكّمات.
|
*/

$view = 'permission.role:clients_view_all_clients|clients_view_own_clients|clients_view_by_employee_groups|clients_view_by_daily_itinerary';
$workspace = 'permission.role:clients_visits_track|clients_itinerary_add|clients_visit_workspace_access|clients_must_visit_before_view';
$notesView = 'permission.role:client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own';
$notesAdd = 'permission.role:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own|clients_add_employee_note';
$notesEdit = 'permission.role:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own';

Route::middleware('module:customers')->group(function () use ($view, $workspace, $notesView, $notesAdd, $notesEdit) {

    // ── العملاء ─────────────────────────────────────────────────────
    Route::prefix('clients')->name('clients.')->group(function () use ($view, $workspace, $notesView, $notesAdd) {
        Route::get('settings', SettingsController::class)->name('settings');
        Route::get('/', [ClientController::class, 'index'])->middleware($view)->name('index');
        Route::get('map', [ClientController::class, 'map'])->middleware($view)->name('map');
        Route::get('options', [ClientController::class, 'options'])->middleware('permission.role:clients_add_client|clients_edit_data')->name('options');
        Route::post('/', [ClientController::class, 'store'])->middleware('permission.role:clients_add_client')->name('store');
        Route::get('{client}', [ClientController::class, 'show'])->whereNumber('client')->middleware($view)->name('show');
        Route::post('{client}', [ClientController::class, 'update'])->whereNumber('client')->middleware('permission.role:clients_edit_data')->name('update');

        // الحالة والوضع — طلبٌ للمعتمِد أو تغييرٌ مباشر
        Route::get('{client}/status-options', [ClientStatusController::class, 'options'])->whereNumber('client')->middleware('permission.role:clients_change_status')->name('status-options');
        Route::post('{client}/status', [ClientStatusController::class, 'updateStatus'])->whereNumber('client')->middleware('permission.role:clients_change_status')->name('status');
        Route::post('{client}/stage', [ClientStatusController::class, 'updateStage'])->whereNumber('client')->middleware('permission.role:clients_change_status')->name('stage');

        // مسؤولو العميل (موظفو المحلّ)
        Route::get('{client}/contacts', [ContactController::class, 'index'])->whereNumber('client')->middleware($notesView)->name('contacts.index');
        Route::post('{client}/contacts', [ContactController::class, 'store'])->whereNumber('client')->middleware($notesAdd)->name('contacts.store');

        // ملاحظات المتابعة
        Route::get('{client}/notes', [NoteController::class, 'index'])->whereNumber('client')->middleware($notesView)->name('notes.index');
        Route::post('{client}/notes', [NoteController::class, 'store'])->whereNumber('client')->middleware($notesAdd)->name('notes.store');

        // الزيارة: فتحها، وإرجاع عميلٍ أُنهيت زيارته لخطّ اليوم
        Route::post('{client}/visits/open', [VisitController::class, 'open'])->whereNumber('client')->middleware($workspace)->name('visits.open');
        Route::post('{client}/return-to-itinerary', [ItineraryController::class, 'returnClient'])->whereNumber('client')->middleware($workspace)->name('return-to-itinerary');
    });

    Route::prefix('notes/{note}')->name('notes.')->whereNumber('note')->middleware($notesEdit)->group(function () {
        Route::post('/', [NoteController::class, 'update'])->name('update');
        Route::delete('/', [NoteController::class, 'destroy'])->name('destroy');
        Route::delete('attachments/{index}', [NoteController::class, 'destroyAttachment'])->whereNumber('index')->name('attachments.destroy');
    });

    // ── خطّ السير اليومي ────────────────────────────────────────────
    Route::prefix('itinerary')->name('itinerary.')->controller(ItineraryController::class)->group(function () {
        Route::get('day', 'day')->middleware('permission.role:clients_daily_itinerary_view|clients_daily_itinerary_add_for_others')->name('day');
        Route::get('employees', 'employees')->middleware('permission.role:clients_daily_itinerary_view|clients_daily_itinerary_add_for_others')->name('employees');
        Route::get('clients', 'clients')->middleware('permission.role:clients_daily_itinerary_add_client|clients_daily_itinerary_add_for_others')->name('clients');
        Route::post('clients', 'store')->middleware('permission.role:clients_daily_itinerary_add_client|clients_daily_itinerary_add_for_others')->name('store');
        Route::post('{row}/postpone', 'postpone')->whereNumber('row')->middleware('permission.role:clients_itinerary_edit')->name('postpone');
    });

    // ── مساحة عمل الزيارة ───────────────────────────────────────────
    Route::prefix('visits')->name('visits.')->group(function () use ($workspace, $notesAdd) {
        // الزيارات المنسيّة — قبل {visit} كي لا يلتقطها
        Route::get('forgotten', [ForgottenVisitController::class, 'index'])->middleware('permission.role:clients_view_forgotten_visits_own|clients_view_forgotten_visits_all')->name('forgotten.index');
        Route::post('forgotten/{visit}/close', [ForgottenVisitController::class, 'close'])->whereNumber('visit')->middleware('permission.role:clients_close_forgotten_visits_own|clients_force_close_forgotten_visits')->name('forgotten.close');
        Route::post('forgotten/{visit}/cancel', [ForgottenVisitController::class, 'cancel'])->whereNumber('visit')->middleware('permission.role:clients_cancel_forgotten_visits')->name('forgotten.cancel');

        Route::prefix('{visit}')->whereNumber('visit')->middleware($workspace)->controller(VisitController::class)->group(function () use ($notesAdd) {
            Route::get('/', 'show')->name('show');
            Route::post('start', 'start')->name('start');
            Route::post('toggle-pause', 'togglePause')->name('toggle-pause');
            Route::post('contact', 'storeContact')->middleware($notesAdd)->name('contact');
            Route::post('contact-shift', 'storeContactShift')->name('contact-shift');
            Route::post('notes', 'storeNote')->middleware($notesAdd)->name('notes');
            Route::post('competitors', 'storeCompetitors')->middleware($notesAdd)->name('competitors');
            Route::post('competitors/quick', 'storeQuickCompetitor')->middleware($notesAdd)->name('competitors.quick');
            Route::post('notifications/{notification}/read', 'readNotification')->whereNumber('notification')->name('notifications.read');
            Route::post('notifications/{notification}/reply', 'replyNotification')->whereNumber('notification')
                ->middleware('permission.role:notifications_reply_to_notifications')->name('notifications.reply');
            Route::post('complete', 'complete')->middleware('permission.role:clients_visit_complete')->name('complete');
            Route::post('cancel', 'cancel')->middleware('permission.role:clients_visit_cancel')->name('cancel');
        });
    });

    // ── تبريرات الزيارات غير المكتملة (ذاتية) ─────────────────────────
    Route::prefix('visit-justifications')->name('visit-justifications.')->controller(JustificationController::class)->group(function () {
        Route::get('pending', 'pending')->name('pending');
        Route::post('/', 'submit')->name('submit');
        Route::get('/', 'index')->middleware('permission.role:clients_visit_justifications_view_own|clients_visit_justifications_view_all')->name('index');
        Route::get('{visit}', 'show')->whereNumber('visit')->middleware('permission.role:clients_visit_justifications_view_own|clients_visit_justifications_view_all')->name('show');
        Route::put('{visit}', 'update')->whereNumber('visit')->middleware('permission.role:clients_visit_justifications_view_own|clients_visit_justifications_view_all')->name('update');
    });
});
