<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * جهازُ المسح الثابت في الفرع.
 *
 * رمزُ الاقتران يُولَّد هنا لا في المتحكّم: الجهازُ لا يُنشأ إلا برمز،
 * ولا يُستنسخ رمزٌ مرتين.
 */
class AttendanceKiosk extends Model
{
    use HasFactory;

    protected $table = 'attendance_kiosks';

    protected $fillable = [
        'name', 'branch_id', 'geofence_id', 'pairing_code',
        'is_active', 'last_seen_at', 'notes', 'office_qr',
        // يُشترَط الموقع على هذا الجهاز — يُلغي إعفاءَ الجهاز المربوط بالمكان
        'require_location',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'require_location' => 'boolean',
        'last_seen_at' => 'datetime',
        'paired_at' => 'datetime',
    ];

    /** بصمةُ مفتاح الشاشة لا تخرج في JSON */
    protected $hidden = ['device_token_hash'];

    /** فكُّ اقتران الشاشة — يُقرَن الجهازُ بعدها بشاشةٍ جديدة */
    public function releaseDevice(): void
    {
        $this->forceFill(['device_token_hash' => null, 'paired_at' => null, 'paired_by' => null, 'paired_user_agent' => null])->save();
    }

    protected static function booted(): void
    {
        static::creating(function (self $kiosk) {
            if (! $kiosk->pairing_code) {
                $kiosk->pairing_code = self::generatePairingCode();
            }
        });
    }

    public static function generatePairingCode(): string
    {
        do {
            // بلا أحرفٍ متشابهة (O/0، I/1): الرمزُ يُقرأ من شاشةٍ ويُكتب بيد مشغّل.
            $code = strtoupper(Str::padLeft(
                substr(str_replace(['O', '0', 'I', '1', 'L'], '', Str::random(24)), 0, 8),
                8,
                'X'
            ));
        } while (self::where('pairing_code', $code)->exists());

        return $code;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(AttendanceGeofence::class, 'geofence_id');
    }

    /** الموظفون المربوطون بهذا الجهاز — من لا جهازَ له يُقبل على أيّ جهاز */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'attendance_kiosk_employee', 'kiosk_id', 'employee_id')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function touchHeartbeat(): void
    {
        // `timestamps = false` عمداً: نبضةُ الجهاز ليست تعديلاً على تعريفه،
        // وتحريكُ `updated_at` معها يُضيّع تاريخَ آخر تغييرٍ إداريّ حقيقي.
        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }
}
