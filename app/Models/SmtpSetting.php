<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'use_smtp',
        'sender_name',
        'email',
        'password',
        'smtp_username',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'ssl_required',
        'is_active',
    ];

    protected $casts = [
        'use_smtp' => 'boolean',
        'ssl_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the user that owns the SMTP settings.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the SMTP settings for a specific user.
     */
    public static function getUserSettings($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Encrypt password before saving.
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = encrypt($value);
        }
    }

    /**
     * Decrypt password when accessing.
     */
    public function getPasswordAttribute($value)
    {
        if ($value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
}
