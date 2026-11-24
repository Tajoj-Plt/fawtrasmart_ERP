<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessData extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'business_email',
        'first_name',
        'last_name',
        'phone',
        'mobile',
        'street_address1',
        'street_address2',
        'city',
        'region',
        'postal_code',
        'country',
        // أعمدة جديدة
        'logo',
        'stamp',
        'signature',
        'cover_image',
        'tax_number',
        'commercial_registration',
        'website',
        'business_type',
    ];

    // ==================== العلاقات ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Accessors ====================

    public function getLogoUrlAttribute()
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function getStampUrlAttribute()
    {
        return $this->stamp ? asset('storage/' . $this->stamp) : null;
    }

    public function getSignatureUrlAttribute()
    {
        return $this->signature ? asset('storage/' . $this->signature) : null;
    }

    public function getCoverUrlAttribute()
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : null;
    }

    // ==================== Scopes ====================

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==================== Helpers ====================

    public static function getOrCreateForUser($userId)
    {
        return self::firstOrCreate(['user_id' => $userId]);
    }
}
