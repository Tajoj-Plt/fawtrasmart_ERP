<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated استعمل `Modules\Client\Reservations\Models\Reservation`.
 *
 * نموذجُ توافقٍ خلفيّ لا أكثر. الجدولُ صار اسمه `reservations` بعد تصحيح
 * التسمية (كان `bookings` بينما `reservations` جدولُ الإعدادات) —
 * راجع `2026_09_08_000001_rename_reservation_tables`.
 *
 * أُبقي الصنفُ لأنّ ثلاثة مواضع خارج وحدة الحجوزات تشير إليه ولا علاقة
 * لها بها: `Client::bookings()`، ومتحكّمُ العملاء، وتبويبُ الحجوزات في
 * شاشة العميل. حذفُه يكسرها بلا مقابل.
 *
 * **لا يُبنى عليه جديد.** كلُّ ما تحتاجه وحدةُ الحجوزات في
 * `Modules\Client\Reservations\Models\Reservation`: الحالةُ تعدادٌ، والفرعُ
 * مصفّى، والفترةُ مختومة، والعملةُ مجمَّدة — ولا شيء من ذلك هنا.
 */
class Booking extends Model
{
    protected $table = 'reservations';

    protected $fillable = [
        'product_id', 'employee_id', 'reservation_date', 'client_id',
        'start_time', 'end_time', 'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * كان `belongsTo(User::class)` — وهو خطأٌ ورثه النموذج: `employee_id`
     * معرّفُ سجلّ موظف. صُحّح في `Reservation::employee()`، وتُرك هنا على
     * `Employee` كذلك حتى لا يفترق التفسيران عن العمود نفسه.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
