<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
