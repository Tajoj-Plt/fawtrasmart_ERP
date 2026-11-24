<?php

namespace Modules\RentalManagement\Models;

use Illuminate\Database\Eloquent\Model;

class Floor extends Model
{
    protected $table = 're_floors';

    protected $guarded = ['id'];

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function units()
    {
        return $this->hasMany(Unit::class, 'floor_id');
    }

    public function getLabelAttribute(): string
    {
        return $this->name ?: ($this->number == 0 ? 'الأرضي' : 'الطابق '.$this->number);
    }
}
