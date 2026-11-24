<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * قاعدة أجر إضافي: مضاعف يوم العمل مقابل العطلة.
 *
 * الدقائق تُحسب في الحضور (`attendance_days.overtime_minutes`) ولا تُحوَّل
 * إلى استحقاق إلا عبر هذه القاعدة عند صرف المسير.
 */
class OvertimeRule extends Model
{
    public const APPLIES_WEEKDAY = 'weekday';

    public const APPLIES_HOLIDAY = 'holiday';

    public const STATUS_ACTIVE = 1;

    public const STATUS_INACTIVE = 2;

    protected $fillable = [
        'code',
        'name',
        'applies_on',
        'multiplier',
        'hourly_rate',
        'max_minutes_per_day',
        'is_system',
        'status',
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'max_minutes_per_day' => 'integer',
        'is_system' => 'boolean',
        'status' => 'integer',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function seededRows(): array
    {
        return [
            [
                'code' => self::APPLIES_WEEKDAY,
                'name' => 'يوم عمل',
                'applies_on' => self::APPLIES_WEEKDAY,
                'multiplier' => 1.50,
                'hourly_rate' => null,
                'max_minutes_per_day' => null,
                'is_system' => true,
                'status' => self::STATUS_ACTIVE,
            ],
            [
                'code' => self::APPLIES_HOLIDAY,
                'name' => 'عطلة / راحة أسبوعية',
                'applies_on' => self::APPLIES_HOLIDAY,
                'multiplier' => 2.00,
                'hourly_rate' => null,
                'max_minutes_per_day' => null,
                'is_system' => true,
                'status' => self::STATUS_ACTIVE,
            ],
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isHolidayRule(): bool
    {
        return $this->applies_on === self::APPLIES_HOLIDAY;
    }
}
