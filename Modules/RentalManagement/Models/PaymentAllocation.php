<?php

namespace Modules\RentalManagement\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $table = 're_payment_allocations';

    protected $guarded = ['id'];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'schedule_id');
    }
}
