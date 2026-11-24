<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeTracking extends Model
{
    protected $table = 'employee_tracking';

    protected $fillable = [
        'employee_id',
        'latitude',
        'longitude',
        'tracking_date',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'tracking_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
