<?php

namespace Modules\RentalManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class ContractRenewal extends Model
{
    protected $table = 're_contract_renewals';

    protected $guarded = ['id'];

    protected $casts = [
        'new_start_date' => 'date',
        'new_end_date' => 'date',
    ];

    public function source()
    {
        return $this->belongsTo(Contract::class, 'source_contract_id');
    }

    public function renewedContract()
    {
        return $this->belongsTo(Contract::class, 'renewed_contract_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusMetaAttribute(): array
    {
        return ['label' => V::label(V::RENEWAL_STATUSES, $this->renewal_status), 'tone' => V::tone(V::RENEWAL_STATUSES, $this->renewal_status)];
    }
}
