<?php

namespace App\Models;

use App\Enums\EmailStatus;
use App\Enums\RecipientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';

    protected $primaryKey = 'log_id';

    protected $fillable = [
        'template_id',
        'recipient_type',
        'recipient_id',
        'recipient_email',
        'subject',
        'email_content',
        'status',
        'sent_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'recipient_type' => RecipientType::class,
        'status' => EmailStatus::class,
        'sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    // علاقة إلى القالب المرتبط بالسجل
    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id', 'template_id');
    }

    /**
     * علاقة المستلم (عميل أو مدير).
     *
     * تُعيد دائماً علاقة belongsTo صالحة — سابقاً كانت تُعيد null لأنواع
     * المستلمين غير Customer/Admin (Guest/Support/External)، ما يُعطب أي
     * ->load('recipient') / ->with('recipient') بخطأ addEagerConstraints() on null.
     * كما كانت تشير إلى صنف غير موجود (Modules\Client\Models\Client) ومفتاح
     * client_id خاطئ (مفتاح clients هو id).
     */
    public function recipient()
    {
        $related = $this->recipient_type === RecipientType::Admin
            ? User::class
            : Client::class;

        return $this->belongsTo($related, 'recipient_id');
    }

    // Scopes
    public function scopeByStatus($query, EmailStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSent($query)
    {
        return $query->where('status', EmailStatus::Sent);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', EmailStatus::Failed);
    }

    public function scopePending($query)
    {
        return $query->where('status', EmailStatus::Pending);
    }

    public function scopeByRecipientType($query, RecipientType $type)
    {
        return $query->where('recipient_type', $type);
    }

    // Accessors
    public function getStatusBadgeAttribute(): string
    {
        return $this->status?->badge() ?? 'secondary';
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? '';
    }

    public function getRecipientTypeLabelAttribute(): string
    {
        return $this->recipient_type?->label() ?? '';
    }

    public function getIsSentAttribute(): bool
    {
        return $this->status === EmailStatus::Sent;
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === EmailStatus::Failed;
    }
}
