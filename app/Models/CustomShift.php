<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomShift extends Model
{
    use HasFactory;
    protected $table = 'custom_shifts';
    protected $guarded = [];

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'custom_shifts_employees', 'custom_shifts_id', 'employee_id');
    }
    public function branch()
    {
        return $this->belongsTo(branch::class, 'branch_id');
    }
     public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
     }
       public function jobTitle()
    {
        return $this->belongsTo(JopTitle::class, 'job_title_id ');
     }


}
