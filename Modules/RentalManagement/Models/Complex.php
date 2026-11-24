<?php

namespace Modules\RentalManagement\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Complex extends RealEstateModel
{
    use SoftDeletes;

    protected $table = 're_complexes';

    public function buildings()
    {
        return $this->hasMany(Building::class, 'complex_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() !== 'ar' && filled($this->name_en) ? $this->name_en : $this->name_ar;
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id')->withTrashed();
    }
}
