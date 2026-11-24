<?php

namespace App\Models\Ecosystem;

use App\Enums\ActionType;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeTransaction extends Model
{
    protected $fillable = [
        'challenge_id',
        'challenge_participant_id',
        'points_transaction_id',
        'user_type',
        'employee_id',
        'client_id',
        'action_type',
        'progress_value',
        'progress_before',
        'progress_after',
        'points',
        'amount',
        'description',
        'metadata',
    ];

    protected $casts = [
        'user_type' => UserType::class,
        'progress_value' => 'integer',
        'progress_before' => 'integer',
        'progress_after' => 'integer',
        'points' => 'integer',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(ChallengeParticipant::class, 'challenge_participant_id');
    }

    public function pointsTransaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class, 'points_transaction_id');
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
    // Helper Methods
    // =====================================================

    /**
     * الحصول على اسم المستخدم
     */
    public function getUserName(): string
    {
        if ($this->user_type === UserType::EMPLOYEE) {
            return $this->employee?->full_name ?? $this->employee?->name ?? 'موظف محذوف';
        }
        return $this->client?->name ?? 'عميل محذوف';
    }

    /**
     * الحصول على تسمية نوع الإجراء
     */
    public function getActionLabel(): string
    {
        $actionType = ActionType::tryFrom($this->action_type);
        return $actionType?->label() ?? $this->action_type;
    }
}
