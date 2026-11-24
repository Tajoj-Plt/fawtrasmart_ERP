<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientCompanyAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'asset_code',
        'asset_name',
        'category',
        'serial_number',
        'placed_at',
        'is_active',
    ];

    protected $casts = [
        'placed_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function inspections()
    {
        return $this->hasMany(VisitAssetInspection::class);
    }
}
