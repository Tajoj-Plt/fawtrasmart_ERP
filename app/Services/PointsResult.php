<?php

namespace App\Services;

use App\Models\Ecosystem\PointsBatch;
use App\Models\Ecosystem\PointsTransaction;

/**
 * كائن النتيجة الموحّد لجميع عمليات PointsService
 * يحمل كل تفاصيل العملية: المعاملة، الدفعة، الترقية، الإنجازات، التحديات، التحذيرات
 */
class PointsResult
{
    public bool $success;
    public string $message;
    public ?PointsTransaction $transaction;
    public ?PointsBatch $batch;
    public ?array $tierChange;
    public array $achievements;
    public array $challengeUpdates;
    public array $notifications;
    public array $warnings;
    public ?int $pointsAwarded;
    public ?array $dailySummaryUpdate;

    private function __construct(
        bool $success,
        string $message,
        ?PointsTransaction $transaction = null,
        ?PointsBatch $batch = null,
        ?array $tierChange = null,
        array $achievements = [],
        array $challengeUpdates = [],
        array $notifications = [],
        array $warnings = [],
        ?int $pointsAwarded = null,
        ?array $dailySummaryUpdate = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->transaction = $transaction;
        $this->batch = $batch;
        $this->tierChange = $tierChange;
        $this->achievements = $achievements;
        $this->challengeUpdates = $challengeUpdates;
        $this->notifications = $notifications;
        $this->warnings = $warnings;
        $this->pointsAwarded = $pointsAwarded;
        $this->dailySummaryUpdate = $dailySummaryUpdate;
    }

    // =====================================================
    // Static Constructors
    // =====================================================

    /**
     * إنشاء نتيجة ناجحة
     */
    public static function success(
        string $message,
        PointsTransaction $transaction,
        ?int $pointsAwarded = null
    ): self {
        return new self(
            success: true,
            message: $message,
            transaction: $transaction,
            pointsAwarded: $pointsAwarded ?? $transaction->points
        );
    }

    /**
     * إنشاء نتيجة فاشلة
     */
    public static function fail(string $message): self
    {
        return new self(
            success: false,
            message: $message
        );
    }

    /**
     * إنشاء نتيجة تخطي (الإعداد معطّل أو غير متاح)
     */
    public static function skipped(string $message, ?string $reason = null): self
    {
        $instance = new self(
            success: false,
            message: $message
        );
        if ($reason) {
            $instance->addWarning($reason);
        }
        return $instance;
    }

    // =====================================================
    // Fluent Setters
    // =====================================================

    public function withBatch(?PointsBatch $batch): self
    {
        $this->batch = $batch;
        return $this;
    }

    public function withTierChange(?array $tierChange): self
    {
        $this->tierChange = $tierChange;
        return $this;
    }

    public function withAchievements(array $achievements): self
    {
        $this->achievements = $achievements;
        return $this;
    }

    public function withChallengeUpdates(array $updates): self
    {
        $this->challengeUpdates = $updates;
        return $this;
    }

    public function withNotifications(array $notifications): self
    {
        $this->notifications = $notifications;
        return $this;
    }

    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;
        return $this;
    }

    public function withDailySummary(?array $summary): self
    {
        $this->dailySummaryUpdate = $summary;
        return $this;
    }

    // =====================================================
    // Query Helpers
    // =====================================================

    public function hasTierChange(): bool
    {
        return !empty($this->tierChange);
    }

    public function hasAchievements(): bool
    {
        return !empty($this->achievements);
    }

    public function hasChallengeUpdates(): bool
    {
        return !empty($this->challengeUpdates);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * أسماء الإنجازات المحققة كنص مفصول بفاصلة
     */
    public function getAchievementNames(): string
    {
        return collect($this->achievements)
            ->pluck('achievement.name')
            ->implode('، ');
    }

    /**
     * اسم المستوى الجديد بعد الترقية
     */
    public function getNewTierName(): ?string
    {
        return $this->tierChange['new_tier_name'] ?? null;
    }

    /**
     * اسم المستوى القديم قبل الترقية
     */
    public function getOldTierName(): ?string
    {
        return $this->tierChange['old_tier_name'] ?? null;
    }

    // =====================================================
    // Serialization
    // =====================================================

    /**
     * تحويل النتيجة إلى مصفوفة
     */
    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'message' => $this->message,
            'points_awarded' => $this->pointsAwarded,
        ];

        if ($this->transaction) {
            $data['transaction'] = [
                'id' => $this->transaction->id,
                'points' => $this->transaction->points,
                'action_type' => $this->transaction->action_type,
                'balance_before' => $this->transaction->balance_before,
                'balance_after' => $this->transaction->balance_after,
                'status' => $this->transaction->status,
            ];
        }

        if ($this->batch) {
            $data['batch'] = [
                'id' => $this->batch->id,
                'original_points' => $this->batch->original_points,
                'expires_at' => $this->batch->expires_at?->toDateTimeString(),
            ];
        }

        if ($this->hasTierChange()) {
            $data['tier_change'] = $this->tierChange;
        }

        if ($this->hasAchievements()) {
            $data['achievements'] = collect($this->achievements)->map(fn($a) => [
                'name' => $a['achievement']->name ?? $a['achievement']['name'] ?? '',
                'bonus_points' => $a['achievement']->bonus_points ?? $a['achievement']['bonus_points'] ?? 0,
            ])->toArray();
        }

        if ($this->hasChallengeUpdates()) {
            $data['challenge_updates'] = $this->challengeUpdates;
        }

        if ($this->hasWarnings()) {
            $data['warnings'] = $this->warnings;
        }

        return $data;
    }

    /**
     * تحويل إلى JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
