<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
        'is_default' => 'boolean',
    ];
    
    /**
     * الحقول المخفية
     */
    protected $hidden = [];
    
    /**
     * الحقول الإضافية
     */
    protected $appends = [];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
    
    /**
     * الحصول على كود الهاتف المنسق
     */
    public function getFormattedPhoneCodeAttribute(): string
    {
        $code = $this->phone_code;
        if (!$code) return '';
        return str_starts_with($code, '+') ? $code : '+' . $code;
    }
}
