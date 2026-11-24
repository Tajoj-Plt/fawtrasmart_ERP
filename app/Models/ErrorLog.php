<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'error_type',
        'error_message',
        'stack_trace',
        'url',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
