<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientEmployee extends Model
{
    protected $table = 'client_employee';
    protected $fillable = ['client_id','employee_id', 'employee_number', 'is_primary', 'invoice_id', 'relationship_type'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
