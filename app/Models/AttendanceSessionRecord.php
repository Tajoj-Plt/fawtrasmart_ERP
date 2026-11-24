<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a single attendance session within a day for an employee.
 *
 * Each session belongs to an AttendanceRecord and stores the times the
 * employee checked in and out for that specific session.  When `status` is
 * false the session is considered "open" and the employee has not yet
 * checked out; when `status` is true the session is "closed".
 */
class AttendanceSessionRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendance_session_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_record_id',
        'session_number',
        'source',
        'status',
        'login_time',
        'logout_time',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status'      => 'boolean',
        'login_time'  => 'datetime',
        'logout_time' => 'datetime',
    ];

    /**
     * Get the attendance record that owns this session.
     */
    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    /**
     * Human‑readable status accessor.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status ? 'مغلق' : 'نشط';
    }
}