<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAttachment extends Model
{
    protected $table = 'employee_attachments';

    protected $fillable = [
        'employee_id',
        'label',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
