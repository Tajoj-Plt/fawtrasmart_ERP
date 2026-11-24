<?php

namespace Modules\RentalManagement\Models;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class MaintenanceRequest extends RealEstateModel
{
    use SoftDeletes;

    protected $table = 're_maintenance_requests';

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function reporter()
    {
        return $this->belongsTo(Client::class, 'reported_by_client_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Supplier::class, 'contractor_supplier_id');
    }

    public function costs()
    {
        return $this->hasMany(MaintenanceCost::class, 'request_id');
    }

    public function getStatusMetaAttribute(): array
    {
        return $this->vocab(V::MAINTENANCE_STATUSES, 'status');
    }

    public function getPriorityMetaAttribute(): array
    {
        return $this->vocab(V::PRIORITIES, 'priority');
    }

    public function getCategoryLabelAttribute(): string
    {
        return V::label(V::MAINTENANCE_CATEGORIES, $this->category);
    }

    /** نطاق الصيانة: وحدةٌ بعينها أم المبنى ومرافقه المشتركة. */
    public function getScopeAttribute(): string
    {
        return $this->unit_id ? 'unit' : 'building';
    }

    /** @return array{label:string, tone:string, icon:string} */
    public function getScopeMetaAttribute(): array
    {
        return $this->unit_id
            ? ['label' => 'صيانة وحدة', 'tone' => 'info', 'icon' => 'grid']
            : ['label' => 'صيانة مبنى', 'tone' => 'warning', 'icon' => 'home'];
    }

    public function getLocationLabelAttribute(): string
    {
        return $this->unit ? $this->unit->full_label : ($this->building->name ?? '—');
    }

    public function scopeOpenOnes($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /** تجاوز موعد الزيارة ولم يُنجَز. */
    public function isLate(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled'], true)
            && $this->scheduled_at && $this->scheduled_at->isPast();
    }
}
