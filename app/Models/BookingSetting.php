<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated استعمل `Modules\Client\Reservations\Models\ReservationSetting::current()`.
 *
 * نموذجُ توافقٍ خلفيّ. الجدولُ صار اسمه `reservation_settings` بعد تصحيح
 * التسمية، وعمودُ `duration` صار `default_duration` — راجع
 * `2026_09_08_000008_extend_reservation_settings_table`.
 *
 * لا يُبنى عليه جديد: الإعداداتُ كلُّها (نحو خمسين مفتاحاً) في
 * `ReservationSetting` مع الـcasts وقراءاتها المشتقّة.
 */
class BookingSetting extends Model
{
    protected $table = 'reservation_settings';

    protected $fillable = ['default_duration', 'payment_online'];
}
