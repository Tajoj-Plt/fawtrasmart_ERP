<?php

namespace Modules\Client\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Client\Reservations\Events\ReservationCancelled;
use Modules\Client\Reservations\Events\ReservationCompleted;
use Modules\Client\Reservations\Events\ReservationConfirmed;
use Modules\Client\Reservations\Events\ReservationCreated;
use Modules\Client\Reservations\Events\ReservationNoShow;
use Modules\Client\Reservations\Events\ReservationRescheduled;
use Modules\Client\Reservations\Listeners\SendReservationNotification;

class ReservationsEventServiceProvider extends ServiceProvider
{
    /**
     * جدولُ توجيه أحداث الحجوزات.
     *
     * **مكتوبٌ صراحةً لا مكتشَفاً.** الاكتشافُ التلقائي يمسح مجلّد
     * المستمعين ويربط بحسب توقيع `handle()`. ومستمعُنا يوقّع على
     * `ReservationEvent` الأب — فيلتقط الأحداثَ العشرةَ كلَّها بما فيها
     * أحداثُ الدفع والفوترة التي لا إشعارَ لها، ثمّ يُهملها بصمت.
     *
     * والجدولُ الصريح يجعل «أيُّ حدثٍ يُشعِر العميل؟» سؤالاً يُجاب من
     * موضعٍ واحد يُقرأ في سطر.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ReservationCreated::class => [SendReservationNotification::class],
        ReservationConfirmed::class => [SendReservationNotification::class],
        ReservationCancelled::class => [SendReservationNotification::class],
        ReservationRescheduled::class => [SendReservationNotification::class],
        ReservationCompleted::class => [SendReservationNotification::class],
        ReservationNoShow::class => [SendReservationNotification::class],
    ];

    /**
     * الاكتشافُ مُطفأ — الجدولُ أعلاه هو المرجع الوحيد.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
