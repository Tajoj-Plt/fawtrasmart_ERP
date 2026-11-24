<?php

namespace App\Models\Ecosystem;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsNotificationLog extends Model
{
    protected $table = 'points_notifications_log';

    /**
     * تعطيل updated_at لأن الجدول يحتوي فقط على created_at
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'template_id',
        'user_type',
        'employee_id',
        'client_id',
        'event_type',
        'channel',
        'title',
        'body',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'channel' => NotificationChannel::class,
        'status' => NotificationStatus::class,
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function template(): BelongsTo
    {
        return $this->belongsTo(PointsNotificationTemplate::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('user_type', UserType::EMPLOYEE->value)
            ->where('employee_id', $employeeId);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('user_type', UserType::CLIENT->value)
            ->where('client_id', $clientId);
    }

    public function scopePending($query)
    {
        return $query->where('status', NotificationStatus::PENDING->value);
    }

    public function scopeSent($query)
    {
        return $query->where('status', NotificationStatus::SENT->value);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', NotificationStatus::DELIVERED->value);
    }

    public function scopeRead($query)
    {
        return $query->where('status', NotificationStatus::READ->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', NotificationStatus::FAILED->value);
    }

    public function scopeUnread($query)
    {
        return $query->whereIn('status', [
            NotificationStatus::PENDING->value,
            NotificationStatus::SENT->value,
            NotificationStatus::DELIVERED->value,
        ]);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function markAsSent(): void
    {
        $this->update([
            'status' => NotificationStatus::SENT,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => NotificationStatus::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => NotificationStatus::READ,
            'read_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => NotificationStatus::FAILED,
            'failure_reason' => $error,
            'failed_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === NotificationStatus::PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::SENT;
    }

    public function isDelivered(): bool
    {
        return $this->status === NotificationStatus::DELIVERED;
    }

    public function isRead(): bool
    {
        return $this->status === NotificationStatus::READ;
    }

    public function isFailed(): bool
    {
        return $this->status === NotificationStatus::FAILED;
    }

    public function getUser()
    {
        return $this->user_type === UserType::EMPLOYEE
            ? $this->employee
            : $this->client;
    }
}
