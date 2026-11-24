<?php

namespace Modules\Client\Reservations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Client\Reservations\Enums\ReservationStatus;

/**
 * صفٌّ واحد في خطّ حياة الحجز — انتقالُ حالةٍ لا يُمحى.
 */
class ReservationStatusHistory extends Model
{
    protected $table = 'reservation_status_histories';

    protected $fillable = [
        'reservation_id', 'from_status', 'to_status', 'note',
        'user_id', 'ip', 'user_agent',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fromStatus(): ?ReservationStatus
    {
        return $this->from_status ? ReservationStatus::from_value($this->from_status) : null;
    }

    public function toStatus(): ReservationStatus
    {
        return ReservationStatus::from_value($this->to_status);
    }
}
