<?php

namespace App\Support;

/**
 * حساب المسافة بين نقطتي إحداثيات (صيغة Haversine).
 *
 * كانت هذه الصيغة مكرَّرة بثلاث نسخ منفصلة: `ItineraryController`،
 * `LocationController`، وموديل `Visit` (الأخيرة ميتة بلا نداءات) — هذه
 * هي النسخة الوحيدة الآن.
 */
class GeoDistance
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    public static function metersBetween(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
