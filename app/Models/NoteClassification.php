<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'frequency',
        'interval_days',
        'day_of_week',
        'is_automated',
    ];

    public function clientRelations()
    {
        return $this->hasMany(ClientRelation::class, 'classification_id');
    }
}
