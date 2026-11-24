<?php

namespace Modules\Integration\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IntegrationOtp extends Model
{
    protected $table = 'integration_otps';

    /** أغراض الرموز المدعومة */
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    protected $fillable = [
        'user_id',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
