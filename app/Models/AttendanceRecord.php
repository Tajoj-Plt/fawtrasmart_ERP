<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a single attendance session for an employee.
 *
 * Each record stores the start and end times of a single attendance
 * session along with some metadata about the session.  When `status` is
 * `0` the record is considered “open” and the employee has not yet
 * checked out; when `status` is `1` the record is “closed”.
 */
class AttendanceRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendance_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'session_number',
        'source',
        'latitude',
        'longitude',
        'accuracy_meters',
        'scanned_barcode',
        'entry_note',
        'status',
        'login_time',
        'logout_time',
    ];

    /**
     * Cast attributes to appropriate native types.
     *
     * @var array<string, string>
     */
    /** المصدران الوحيدان: مسحٌ بباركودٍ وموقع، أو استثناءٌ يدويٌّ مُبرَّر. */
    public const SOURCE_BARCODE = 'barcode';

    public const SOURCE_MANUAL = 'manual';

    /** @return array<string, string> */
    public static function sources(): array
    {
        return [
            self::SOURCE_BARCODE => 'باركود + موقع جغرافي',
            self::SOURCE_MANUAL => 'يدوي (استثنائي)',
        ];
    }

    public function sourceLabel(): string
    {
        return self::sources()[$this->source] ?? (string) $this->source;
    }

    protected $casts = [
        'status' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy_meters' => 'integer',
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
    ];

    /**
     * Get the employee associated with this record.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Human‑readable status accessor.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status ? 'مغلق' : 'نشط';
    }

    /**
     * Get the sessions associated with this attendance record.
     *
     * Each attendance record may have multiple sessions (check‑in/out pairs)
     * within the same day.  This relationship is useful when displaying
     * detailed breakdowns of an employee's day.
     */
    public function sessions()
    {
        return $this->hasMany(AttendanceSessionRecord::class, 'attendance_record_id');
    }
}
