<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AttendancePhoto extends Model
{
    protected $table = 'attendance_photos';

    protected $fillable = [
        'attendance_day_id', 'photo_type_id', 'employee_id',
        'phase', 'file_path', 'original_name', 'file_size', 'mime_type',
        'latitude', 'longitude', 'ip_address', 'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDays::class, 'attendance_day_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AttendancePhotoType::class, 'photo_type_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }
}
