<?php

namespace Modules\RentalManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContractAmendment extends Model
{
    protected $table = 're_contract_amendments';

    protected $guarded = ['id'];

    protected $casts = ['effective_date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
