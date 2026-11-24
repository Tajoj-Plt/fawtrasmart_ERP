<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تفعيل/تعطيل الأقسام الفرعية لموديول العملاء — صفٌّ واحد (singleton).
 *
 * Usage:
 *   ClientModuleSetting::current()->visits_enabled
 */
class ClientModuleSetting extends Model
{
    protected $fillable = [
        'visits_enabled',
        'itinerary_enabled',
        'location_enabled',
        'notes_enabled',
        'appointments_enabled',
        'crm_enabled',
        'doubtful_debts_enabled',
        'invoice_transfer_enabled',
        'customer_targets_enabled',
        'exempt_location_enabled',
        'exempt_exchange_enabled',
        'updated_by',
    ];

    protected $casts = [
        'visits_enabled' => 'boolean',
        'itinerary_enabled' => 'boolean',
        'location_enabled' => 'boolean',
        'notes_enabled' => 'boolean',
        'appointments_enabled' => 'boolean',
        'crm_enabled' => 'boolean',
        'doubtful_debts_enabled' => 'boolean',
        'invoice_transfer_enabled' => 'boolean',
        'customer_targets_enabled' => 'boolean',
        'exempt_location_enabled' => 'boolean',
        'exempt_exchange_enabled' => 'boolean',
    ];

    /** @var self|null memoized لعمر الطلب — يُستدعى مراراً من الشريط الجانبي وحده. */
    private static ?self $cached = null;

    /**
     * الصفّ الوحيد — يُنشأ بكل الأقسام مفعّلة إن لم يوجد بعد.
     */
    public static function current(): self
    {
        return static::$cached ??= static::query()->firstOrCreate([], []);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
