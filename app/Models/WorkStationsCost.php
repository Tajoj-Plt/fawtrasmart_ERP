<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasDocumentCurrency;

class WorkStationsCost extends Model
{
    use HasDocumentCurrency;

    protected $table = 'work_stations_costs';

    protected $fillable = [
        'work_station_id',
        'cost_expenses',
        'account_expenses',
        'currency',
    ];

    public function workStations()
    {
        return $this->belongsTo(WorkStations::class, 'work_station_id');
    }

    public function accountExpenses()
    {
        return $this->belongsTo(Account::class, 'account_expenses');
    }


}
