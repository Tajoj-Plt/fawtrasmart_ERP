<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePhotoType extends Model
{
    protected $table = 'attendance_photo_types';

    protected $fillable = [
        'code', 'name', 'icon', 'description',
        'phase', 'is_active', 'is_required', 'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    public const PHASE_CHECKIN  = 'checkin';
    public const PHASE_CHECKOUT = 'checkout';
    public const PHASE_BOTH     = 'both';

    public function photos(): HasMany
    {
        return $this->hasMany(AttendancePhoto::class, 'photo_type_id');
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeForPhase($q, string $phase)
    {
        return $q->where(function ($qq) use ($phase) {
            $qq->where('phase', $phase)->orWhere('phase', self::PHASE_BOTH);
        });
    }
}
