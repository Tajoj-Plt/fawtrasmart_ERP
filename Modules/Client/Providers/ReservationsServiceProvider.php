<?php

namespace Modules\Client\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * قسمُ الحجوزات والمواعيد داخل وحدة العملاء.
 *
 * كان وحدةً مستقلّة (`Modules/Reservations`) ونُقل بكامله إلى العملاء
 * (2026-09-16، طلب المستخدم: «يكون مع العملاء»). ما بقي له من مزوّدٍ خاصّ
 * هو ما لا يخصّ العملاء عموماً: أوامرُه وأحداثُه. أمّا الواجهات فتحت
 * `client::reservations.*`، والترحيلات في `Modules/Client/database/migrations`
 * يحمّلها `ClientServiceProvider`، والمسارات في `routes/reservations.php`
 * يحمّلها `RouteServiceProvider` الوحدة.
 *
 * **ما لم يتغيّر عمداً:** أسماءُ المسارات (`Reservations.*`، `appointments.*`)
 * والعنوانُ `Reservations/…` — فمفتاحُ `booking_management` في «شاشة التطبيقات»
 * ما زال يطفئ القسمَ وحده دون العملاء — والصلاحياتُ والجداول.
 */
class ReservationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(ReservationsEventServiceProvider::class);
    }

    public function boot(): void
    {
        $this->commands([
            \Modules\Client\Reservations\Console\SendReservationReminders::class,
            \Modules\Client\Reservations\Console\ReservationSelfCheck::class,
            \Modules\Client\Reservations\Console\SyncReservationAppointments::class,
            \Modules\Client\Reservations\Console\GenerateRecurringAppointments::class,
        ]);
    }
}
