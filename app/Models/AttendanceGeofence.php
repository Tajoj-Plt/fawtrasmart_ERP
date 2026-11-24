<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نطاقُ مرفقٍ واحد: دائرةٌ جغرافية، وشبكةٌ مسموحة، ومَن يخدمهما.
 *
 * حسابُ المسافة هنا لا في المتحكّم: نفسُ الرقم يُستعمل في القبول، وفي
 * ما يُكتب على الختم (`distance_meters`)، وفي رسالة الرفض التي يقرؤها
 * الموظف — فمصدرُه واحد.
 */
class AttendanceGeofence extends Model
{
    use HasFactory;

    protected $table = 'attendance_geofences';

    protected $fillable = [
        'name', 'branch_id',
        'latitude', 'longitude', 'radius_meters', 'max_accuracy_meters',
        'allowed_ip_ranges', 'allowed_wifi_ssids',
        'require_geo', 'require_network',
        'is_active', 'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_meters' => 'integer',
        'max_accuracy_meters' => 'integer',
        'allowed_ip_ranges' => 'array',
        'allowed_wifi_ssids' => 'array',
        'require_geo' => 'boolean',
        'require_network' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function kiosks(): HasMany
    {
        return $this->hasMany(AttendanceKiosk::class, 'geofence_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** أَلِهذا النطاق مركزٌ يُقاس منه أصلاً؟ */
    public function hasCircle(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->radius_meters > 0;
    }

    /**
     * المسافةُ بالأمتار بين نقطةٍ ومركز النطاق — صيغة Haversine.
     *
     * تُرجِع `null` حين لا مركزَ للنطاق: «لا أعرف» ليست «صفر متر»، والخلطُ
     * بينهما يجعل نطاقاً غيرَ مضبوطٍ يقبل كلَّ نقطةٍ على الأرض.
     */
    public function distanceFrom(?float $latitude, ?float $longitude): ?int
    {
        if (! $this->hasCircle() || $latitude === null || $longitude === null) {
            return null;
        }

        $earthRadius = 6371000; // بالأمتار

        $lat1 = deg2rad((float) $this->latitude);
        $lat2 = deg2rad($latitude);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad($longitude - (float) $this->longitude);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function containsPoint(?float $latitude, ?float $longitude): bool
    {
        $distance = $this->distanceFrom($latitude, $longitude);

        return $distance !== null && $distance <= $this->radius_meters;
    }
}
