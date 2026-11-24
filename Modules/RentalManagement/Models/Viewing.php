<?php

namespace Modules\RentalManagement\Models;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class Viewing extends Model
{
    protected $table = 're_viewings';

    protected $guarded = ['id'];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function getPersonAttribute(): string
    {
        return $this->client->display_name ?? ($this->visitor_name ?: '—');
    }

    public function getStatusMetaAttribute(): array
    {
        return ['label' => V::label(V::VIEWING_STATUSES, $this->status), 'tone' => V::tone(V::VIEWING_STATUSES, $this->status)];
    }
}
