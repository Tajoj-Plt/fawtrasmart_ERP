<?php

namespace App\Services;

use App\Enums\ChallengeType;
use App\Enums\CompetitionType;
use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\Ecosystem\Challenge;
use App\Models\Ecosystem\ChallengeParticipant;
use App\Models\Ecosystem\ChallengeTeam;
use App\Models\Ecosystem\ChallengeTransaction;
use App\Models\Ecosystem\PointsTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChallengeService
{
    /**
     * تسجيل مشارك في تحدي
     */
    public function enrollParticipant(Challenge $challenge, int $userId, UserType $userType, ?int $teamId = null): ?ChallengeParticipant
    {
        // التحقق من أن التحدي نشط وقابل للتسجيل
        if (!$challenge->is_active || $challenge->hasEnded()) {
            return null;
        }

        // التحقق من أن نوع المستخدم متوافق مع التحدي
        $challengeUserType = $challenge->user_type instanceof UserType ? $challenge->user_type->value : $challenge->user_type;
        if ($challengeUserType !== 'both' && $challengeUserType !== $userType->value) {
            return null;
        }

        $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';

        // التحقق من عدم التسجيل المسبق
        $exists = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('user_type', $userType)
            ->where($idColumn, $userId)
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            // التقدم يبدأ من 0 - يعتمد فقط على challenge_transactions المسجلة
            $participant = ChallengeParticipant::create([
                'challenge_id' => $challenge->id,
                'user_type' => $userType->value,
                'employee_id' => $userType === UserType::EMPLOYEE ? $userId : null,
                'client_id' => $userType === UserType::CLIENT ? $userId : null,
                'team_id' => $teamId,
                'target_value_snapshot' => $challenge->target_value,
                'current_progress' => 0,
                'is_completed' => false,
                'completed_at' => null,
                'enrolled_at' => now(),
            ]);

            // تحديث تقدم الفريق إذا كان تحدي جماعي
            if ($teamId) {
                $this->updateTeamProgress($teamId);
            }

            Log::info('Participant enrolled in challenge', [
                'challenge_id' => $challenge->id,
                'user_type' => $userType->value,
                'user_id' => $userId,
                'current_progress' => 0,
            ]);

            return $participant;

        } catch (\Exception $e) {
            Log::error('Failed to enroll participant', [
                'challenge_id' => $challenge->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * تسجيل تلقائي لجميع المستخدمين المؤهلين
     */
    public function autoEnrollAll(Challenge $challenge): int
    {
        if (!$challenge->auto_enroll || !$challenge->is_active) {
            return 0;
        }

        $enrolled = 0;
        $challengeUserType = $challenge->user_type instanceof UserType ? $challenge->user_type->value : $challenge->user_type;

        // تسجيل الموظفين
        if (in_array($challengeUserType, ['employee', 'both'])) {
            $employees = \App\Models\Employee::active()->get();
            foreach ($employees as $employee) {
                $result = $this->enrollParticipant($challenge, $employee->id, UserType::EMPLOYEE);
                if ($result) $enrolled++;
            }
        }

        // تسجيل العملاء
        if (in_array($challengeUserType, ['client', 'both'])) {
            $clients = \App\Models\Client::whereHas('status_client', fn($q) => $q->where('state', 1))->get();
            foreach ($clients as $client) {
                $result = $this->enrollParticipant($challenge, $client->id, UserType::CLIENT);
                if ($result) $enrolled++;
            }
        }

        Log::info('Auto-enrolled participants', [
            'challenge_id' => $challenge->id,
            'enrolled_count' => $enrolled,
        ]);

        return $enrolled;
    }

    /**
     * تحديث تقدم المشاركين عند إنشاء معاملة نقاط مكتملة
     * يُستدعى من PointsTransactionController
     * 
     * @param string $actionType نوع الإجراء
     * @param int $userId معرف المستخدم
     * @param UserType $userType نوع المستخدم
     * @param PointsTransaction|null $transaction معاملة النقاط الأصلية (لتسجيلها في challenge_transactions)
     */
    public function updateProgressForTransaction(string $actionType, int $userId, UserType $userType, ?PointsTransaction $transaction = null): array
    {
        $updatedParticipants = [];

        // البحث عن التحديات النشطة التي تستهدف هذا الإجراء
        $challenges = Challenge::active()
            ->ongoing()
            ->whereJsonContains('target_action', $actionType)
            ->get();

        if ($challenges->isEmpty()) {
            return [];
        }

        $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';

        foreach ($challenges as $challenge) {
            // التحقق من توافق نوع المستخدم
            $challengeUserType = $challenge->user_type instanceof UserType ? $challenge->user_type->value : $challenge->user_type;
            if ($challengeUserType !== 'both' && $challengeUserType !== $userType->value) {
                continue;
            }

            // البحث عن المشارك
            $participant = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('user_type', $userType)
                ->where($idColumn, $userId)
                ->first();

            // إذا التحدي auto_enroll ولم يكن المشارك مسجلاً، سجّله تلقائياً
            if (!$participant && $challenge->auto_enroll) {
                $participant = $this->enrollParticipant($challenge, $userId, $userType);
            }

            if (!$participant) {
                continue;
            }

            // لا يُحدَّث التقدم بدون معاملة - التحديات تعتمد كلياً على challenge_transactions
            if (!$transaction) {
                continue;
            }

            // حفظ التقدم السابق
            $progressBefore = $participant->current_progress;

            // حساب قيمة التقدم لهذه المعاملة
            $progressValue = $this->calculateProgressValue($challenge, $transaction);

            // حساب التقدم الجديد بالتراكم
            $newProgress = $progressBefore + $progressValue;

            // تسجيل المعاملة في جدول challenge_transactions أولاً
            $this->recordChallengeTransaction(
                $challenge,
                $participant,
                $transaction,
                $progressValue,
                $progressBefore,
                $newProgress
            );

            // ثم تحديث تقدم المشارك
            $wasCompleted = $participant->is_completed;
            $participant->updateProgress($newProgress);

            // تحديث تقدم الفريق
            if ($participant->team_id) {
                $this->updateTeamProgress($participant->team_id);
            }

            $updatedParticipants[] = [
                'challenge' => $challenge,
                'participant' => $participant->fresh(),
                'newly_completed' => !$wasCompleted && $participant->fresh()->is_completed,
            ];
        }

        return $updatedParticipants;
    }

    /**
     * حساب قيمة التقدم لمعاملة واحدة
     */
    private function calculateProgressValue(Challenge $challenge, ?PointsTransaction $transaction): int
    {
        if (!$transaction) {
            return 1;
        }

        $targetUnit = $challenge->target_unit;
        
        // إذا كان القياس بالمبلغ، نستخدم النقاط (قد تكون سالبة لإنقاص التقدم)
        if (in_array($targetUnit, ['ريال', 'مبلغ', 'amount', 'sum'])) {
            return (int) $transaction->points;
        }

        // الافتراضي: عدد (+1 للنقاط الموجبة، -1 للنقاط السالبة)
        return $transaction->points >= 0 ? 1 : -1;
    }

    /**
     * تسجيل معاملة تحدي في جدول challenge_transactions
     */
    private function recordChallengeTransaction(
        Challenge $challenge,
        ChallengeParticipant $participant,
        PointsTransaction $transaction,
        int $progressValue,
        int $progressBefore,
        int $progressAfter
    ): ChallengeTransaction {
        return ChallengeTransaction::create([
            'challenge_id' => $challenge->id,
            'challenge_participant_id' => $participant->id,
            'points_transaction_id' => $transaction->id,
            'user_type' => $transaction->user_type instanceof UserType ? $transaction->user_type->value : $transaction->user_type,
            'employee_id' => $transaction->employee_id,
            'client_id' => $transaction->client_id,
            'action_type' => $transaction->action_type,
            'progress_value' => $progressValue,
            'progress_before' => $progressBefore,
            'progress_after' => $progressAfter,
            'points' => $transaction->points,
            'amount' => $transaction->metadata['amount'] ?? $transaction->metadata['transaction_amount'] ?? null,
            'description' => $transaction->description,
            'metadata' => [
                'reference_type' => $transaction->reference_type,
                'reference_id' => $transaction->reference_id,
                'branch_id' => $transaction->branch_id,
                'original_metadata' => $transaction->metadata,
            ],
        ]);
    }

    /**
     * حساب التقدم الحالي للمشارك بناءً على جدول challenge_transactions
     * يُستخدم لإعادة الحساب والتحقق فقط - التقدم العادي يتم بالتراكم
     */
    public function calculateCurrentProgress(Challenge $challenge, int $userId, UserType $userType): int
    {
        $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';

        return (int) ChallengeTransaction::where('challenge_id', $challenge->id)
            ->where('user_type', $userType)
            ->where($idColumn, $userId)
            ->sum('progress_value');
    }

    /**
     * حساب الترتيب لجميع المشاركين في تحدي
     * الترتيب: الأكثر تقدماً أولاً، ثم الأول إكمالاً (لفك التعادل)
     */
    public function calculateRankings(Challenge $challenge): void
    {
        $participants = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->orderByDesc('current_progress')
            ->orderBy('completed_at') // الأول إكمالاً يتقدم عند التساوي
            ->orderBy('id') // fallback ثابت
            ->get();

        $rank = 1;
        foreach ($participants as $participant) {
            $participant->rank = $rank;
            $participant->saveQuietly();
            $rank++;
        }
    }

    /**
     * منح النقاط للمشارك الذي أكمل التحدي
     */
    public function awardPoints(ChallengeParticipant $participant): ?PointsTransaction
    {
        // التحقق من عدم صرف النقاط مسبقاً
        if ($participant->points_awarded) {
            return null;
        }

        $challenge = $participant->challenge;
        if (!$challenge) return null;

        // تحديد النقاط بناءً على الترتيب ونوع المنافسة
        $points = $this->getAwardPoints($challenge, $participant);

        if ($points <= 0) return null;

        try {
            $userType = $participant->user_type instanceof UserType ? $participant->user_type : UserType::from($participant->user_type);
            $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';
            $userId = $userType === UserType::EMPLOYEE ? $participant->employee_id : $participant->client_id;

            // حساب الأرصدة
            $month = now()->month;
            $year = now()->year;
            $balanceBefore = PointsTransaction::where('user_type', $userType)
                ->where($idColumn, $userId)
                ->where('points_month', $month)
                ->where('points_year', $year)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points');

            // إنشاء معاملة نقاط
            $transaction = PointsTransaction::create([
                'user_type' => $userType->value,
                'employee_id' => $userType === UserType::EMPLOYEE ? $userId : null,
                'client_id' => $userType === UserType::CLIENT ? $userId : null,
                'action_type' => 'challenge_reward',
                'points' => $points,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore + $points,
                'description' => 'مكافأة تحدي: ' . $challenge->name . ' (المركز ' . $participant->rank . ')',
                'status' => TransactionStatus::COMPLETED->value,
                'branch_id' => $challenge->branch_id,
                'points_month' => $month,
                'points_year' => $year,
                'created_by' => Auth::id(),
                'reference_type' => Challenge::class,
                'reference_id' => $challenge->id,
            ]);

            // تحديث المشارك
            $participant->update([
                'points_awarded' => $points,
                'points_awarded_at' => now(),
            ]);

            Log::info('Challenge points awarded', [
                'challenge_id' => $challenge->id,
                'participant_id' => $participant->id,
                'points' => $points,
                'rank' => $participant->rank,
            ]);

            return $transaction;

        } catch (\Exception $e) {
            Log::error('Failed to award challenge points', [
                'participant_id' => $participant->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * تحديد النقاط الممنوحة بناءً على نوع المنافسة والترتيب
     */
    private function getAwardPoints(Challenge $challenge, ChallengeParticipant $participant): int
    {
        $competitionType = $challenge->competition_type instanceof CompetitionType 
            ? $challenge->competition_type 
            : CompetitionType::from($challenge->competition_type);

        switch ($competitionType) {
            case CompetitionType::TARGET:
                // تحقيق هدف: فائز واحد فقط (المركز الأول)
                // الترتيب يُحسب مسبقاً بـ calculateRankings:
                // الأكثر تقدماً → ثم الأول إكمالاً عند التساوي
                if ($participant->is_completed && (int)$participant->rank === 1) {
                    return $challenge->reward_points;
                }
                return 0;

            case CompetitionType::RANKING:
                // نقاط حسب الترتيب
                return match ($participant->rank) {
                    1 => $challenge->reward_points,
                    2 => $challenge->reward_points_2nd ?? (int) ($challenge->reward_points * 0.6),
                    3 => $challenge->reward_points_3rd ?? (int) ($challenge->reward_points * 0.3),
                    default => 0,
                };

            case CompetitionType::FIRST_N:
                // أول N فائزين يحصلون على نفس النقاط بالتساوي
                // الكل يحصل على reward_points بدون تفرقة بين المراكز
                $maxWinners = $challenge->max_winners ?? 1;
                if ($participant->rank && (int)$participant->rank <= $maxWinners && $participant->is_completed) {
                    return $challenge->reward_points;
                }
                return 0;

            default:
                return 0;
        }
    }

    /**
     * إنهاء تحدي وتوزيع الجوائز (موزّع: فردي أو فرق)
     */
    public function finalizeChallenge(Challenge $challenge): array
    {
        $challengeType = $challenge->challenge_type instanceof ChallengeType
            ? $challenge->challenge_type
            : ChallengeType::from($challenge->challenge_type);

        if ($challengeType === ChallengeType::TEAM) {
            return $this->finalizeTeamChallenge($challenge);
        }

        return $this->finalizeIndividualChallenge($challenge);
    }

    /**
     * إنهاء تحدي فردي وتوزيع الجوائز
     */
    private function finalizeIndividualChallenge(Challenge $challenge): array
    {
        $results = [
            'ranked' => 0,
            'awarded' => 0,
            'total_points' => 0,
            'awarded_details' => [],
        ];

        // حساب الترتيب النهائي
        $this->calculateRankings($challenge);
        $results['ranked'] = $challenge->participants()->count();

        // توزيع الجوائز على المستحقين
        $participants = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->with(['employee', 'client'])
            ->orderBy('rank')
            ->get();

        foreach ($participants as $participant) {
            $transaction = $this->awardPoints($participant);
            if ($transaction) {
                $results['awarded']++;
                $results['total_points'] += $transaction->points;

                $name = 'غير معروف';
                if ($participant->employee) {
                    $name = $participant->employee->full_name ?? $participant->employee->first_name;
                } elseif ($participant->client) {
                    $name = $participant->client->full_name ?? $participant->client->first_name;
                }
                $results['awarded_details'][] = [
                    'name' => $name,
                    'rank' => $participant->rank,
                    'points' => $transaction->points,
                    'progress' => $participant->current_progress,
                ];
            }
        }

        // تحديث تاريخ إعلان النتائج
        $challenge->update(['results_announced_at' => now()]);

        Log::info('Individual challenge finalized', [
            'challenge_id' => $challenge->id,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * إنهاء تحدي فرق وتوزيع الجوائز على الفرق الفائزة
     * النقاط تُقسم بالتساوي على جميع أعضاء الفريق الفائز
     */
    private function finalizeTeamChallenge(Challenge $challenge): array
    {
        $results = [
            'ranked' => 0,
            'awarded' => 0,
            'total_points' => 0,
            'awarded_details' => [],
            'teams' => [],
        ];

        // 1. تحديث تقدم جميع الفرق
        $teams = ChallengeTeam::where('challenge_id', $challenge->id)->get();
        foreach ($teams as $team) {
            $team->updateTotalProgress();
        }

        // 2. حساب ترتيب الأفراد (للعرض فقط)
        $this->calculateRankings($challenge);
        $results['ranked'] = $challenge->participants()->count();

        // 3. ترتيب الفرق حسب total_progress تنازلياً، ثم الأول إكمالاً لفك التعادل
        $rankedTeams = ChallengeTeam::where('challenge_id', $challenge->id)
            ->orderByDesc('total_progress')
            ->orderBy('completed_at') // الأول إكمالاً يتقدم عند التساوي
            ->orderBy('id') // fallback ثابت
            ->get();

        $teamRank = 1;
        foreach ($rankedTeams as $team) {
            $team->rank = $teamRank;
            $team->save();
            $teamRank++;
        }

        // 4. تحديد الفرق الفائزة حسب قواعد المنافسة
        $winningTeams = $this->getWinningTeams($challenge, $rankedTeams);

        // 5. توزيع النقاط على أعضاء الفرق الفائزة بالتساوي
        foreach ($winningTeams as $teamData) {
            $team = $teamData['team'];
            $teamPoints = $teamData['points']; // إجمالي نقاط المكافأة لهذا الفريق

            if ($teamPoints <= 0) continue;

            // جلب جميع أعضاء الفريق
            $members = ChallengeParticipant::where('team_id', $team->id)
                ->with(['employee', 'client'])
                ->get();

            if ($members->isEmpty()) continue;

            $memberCount = $members->count();
            $pointsPerMember = (int) floor($teamPoints / $memberCount);
            $remainder = $teamPoints - ($pointsPerMember * $memberCount);

            $teamDetail = [
                'team_name' => $team->name,
                'team_rank' => $team->rank,
                'team_points' => $teamPoints,
                'members' => [],
            ];

            foreach ($members as $index => $member) {
                // تخطي إذا سبق منح النقاط
                if ($member->points_awarded) continue;

                $memberPoints = $pointsPerMember;
                // العضو الأول يحصل على الباقي (لضمان عدم تجاوز إجمالي المكافأة)
                if ($index === 0) {
                    $memberPoints += $remainder;
                }

                if ($memberPoints <= 0) continue;

                $transaction = $this->awardPointsToTeamMember($member, $challenge, $memberPoints, $team->rank);

                if ($transaction) {
                    $results['awarded']++;
                    $results['total_points'] += $memberPoints;

                    $name = $member->employee?->full_name ?? $member->employee?->first_name
                        ?? $member->client?->full_name ?? $member->client?->first_name
                        ?? 'غير معروف';

                    $teamDetail['members'][] = [
                        'name' => $name,
                        'points' => $memberPoints,
                    ];
                }
            }

            if (!empty($teamDetail['members'])) {
                $results['teams'][] = $teamDetail;
            }
        }

        // تحديث تاريخ إعلان النتائج
        $challenge->update(['results_announced_at' => now()]);

        Log::info('Team challenge finalized', [
            'challenge_id' => $challenge->id,
            'winning_teams' => count($winningTeams),
            'total_points' => $results['total_points'],
            'awarded_members' => $results['awarded'],
        ]);

        return $results;
    }

    /**
     * تحديد الفرق الفائزة بناءً على نوع المنافسة
     * @return array [['team' => ChallengeTeam, 'points' => int], ...]
     */
    private function getWinningTeams(Challenge $challenge, $rankedTeams): array
    {
        $competitionType = $challenge->competition_type instanceof CompetitionType
            ? $challenge->competition_type
            : CompetitionType::from($challenge->competition_type);

        $winningTeams = [];

        switch ($competitionType) {
            case CompetitionType::TARGET:
                // تحقيق هدف: فريق فائز واحد فقط (المركز الأول)
                // الترتيب: الأكثر تقدماً → ثم الأول إكمالاً عند التساوي
                // الفرق مرتبة مسبقاً بـ total_progress desc, completed_at asc
                foreach ($rankedTeams as $team) {
                    if ($team->total_progress >= $challenge->target_value) {
                        $winningTeams[] = ['team' => $team, 'points' => $challenge->reward_points];
                        break; // فائز واحد فقط
                    }
                }
                break;

            case CompetitionType::RANKING:
                // ترتيب: مراكز مختلفة بنقاط مختلفة (1st, 2nd, 3rd)
                // max_winners يحدد عدد المراكز المُكافأة (افتراضي 3)
                // لا يُكافأ فريق بدون أي تقدم
                $maxPositions = $challenge->max_winners ?: 3;
                foreach ($rankedTeams as $team) {
                    if ($team->rank > $maxPositions) break;
                    if ($team->total_progress <= 0) continue;

                    $points = match ($team->rank) {
                        1 => $challenge->reward_points,
                        2 => $challenge->reward_points_2nd ?? (int)($challenge->reward_points * 0.6),
                        3 => $challenge->reward_points_3rd ?? (int)($challenge->reward_points * 0.3),
                        default => 0,
                    };

                    if ($points > 0) {
                        $winningTeams[] = ['team' => $team, 'points' => $points];
                    }
                }
                break;

            case CompetitionType::FIRST_N:
                // أول N فرق تحقق الهدف (N = max_winners)
                // كل فريق فائز يحصل على نفس reward_points بدون تفرقة
                // النقاط تُوزع بالتساوي على جميع أعضاء الفريق
                $maxWinners = $challenge->max_winners ?? 1;
                $winnersCount = 0;
                foreach ($rankedTeams as $team) {
                    if ($winnersCount >= $maxWinners) break;
                    if ($team->total_progress >= $challenge->target_value) {
                        $winningTeams[] = ['team' => $team, 'points' => $challenge->reward_points];
                        $winnersCount++;
                    }
                }
                break;
        }

        return $winningTeams;
    }

    /**
     * منح نقاط لعضو في فريق فائز
     */
    private function awardPointsToTeamMember(ChallengeParticipant $member, Challenge $challenge, int $points, int $teamRank): ?PointsTransaction
    {
        try {
            $userType = $member->user_type instanceof UserType ? $member->user_type : UserType::from($member->user_type);
            $idColumn = $userType === UserType::EMPLOYEE ? 'employee_id' : 'client_id';
            $userId = $userType === UserType::EMPLOYEE ? $member->employee_id : $member->client_id;

            $month = now()->month;
            $year = now()->year;
            $balanceBefore = PointsTransaction::where('user_type', $userType)
                ->where($idColumn, $userId)
                ->where('points_month', $month)
                ->where('points_year', $year)
                ->where('status', TransactionStatus::COMPLETED)
                ->sum('points');

            $transaction = PointsTransaction::create([
                'user_type' => $userType->value,
                'employee_id' => $userType === UserType::EMPLOYEE ? $userId : null,
                'client_id' => $userType === UserType::CLIENT ? $userId : null,
                'action_type' => 'challenge_reward',
                'points' => $points,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore + $points,
                'description' => 'مكافأة تحدي فريق: ' . $challenge->name . ' (فريق المركز ' . $teamRank . ')',
                'status' => TransactionStatus::COMPLETED->value,
                'branch_id' => $challenge->branch_id,
                'points_month' => $month,
                'points_year' => $year,
                'created_by' => Auth::id(),
                'reference_type' => Challenge::class,
                'reference_id' => $challenge->id,
            ]);

            $member->update([
                'points_awarded' => $points,
                'points_awarded_at' => now(),
                'rank' => $teamRank,
            ]);

            Log::info('Team member points awarded', [
                'challenge_id' => $challenge->id,
                'member_id' => $member->id,
                'team_rank' => $teamRank,
                'points' => $points,
            ]);

            return $transaction;

        } catch (\Exception $e) {
            Log::error('Failed to award team member points', [
                'member_id' => $member->id,
                'points' => $points,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * تحديث تقدم الفريق
     */
    private function updateTeamProgress(int $teamId): void
    {
        $team = ChallengeTeam::find($teamId);
        if ($team) {
            $team->updateTotalProgress();
            
            // التحقق من اكتمال الفريق بطريقتين:
            // 1. كل الأعضاء أكملوا
            // 2. أو الفريق وصل للهدف (total_progress >= target_value)
            $challenge = $team->challenge;
            $targetValue = $challenge?->target_value ?? 0;
            
            $totalMembers = $team->members()->count();
            $completedMembers = $team->members()->where('is_completed', true)->count();
            
            $isCompleted = ($totalMembers > 0 && $completedMembers === $totalMembers)
                || ($targetValue > 0 && $team->total_progress >= $targetValue);
            
            if ($isCompleted && !$team->is_completed) {
                $team->is_completed = true;
                $team->completed_at = now();
                $team->save();
            } elseif (!$isCompleted && $team->is_completed) {
                // إذا تراجع التقدم (بسبب عكس معاملات) يُلغى الاكتمال
                $team->is_completed = false;
                $team->completed_at = null;
                $team->save();
            }
        }
    }

    /**
     * عكس تقدم التحديات عند إلغاء معاملة نقاط
     * يُنشئ معاملات تحدي عكسية ويُحدّث تقدم المشاركين
     */
    public function reverseProgressForTransaction(PointsTransaction $transaction): array
    {
        $reversedParticipants = [];

        // البحث عن جميع معاملات التحدي المرتبطة بهذه المعاملة (باستثناء العكسية السابقة)
        $challengeTransactions = ChallengeTransaction::where('points_transaction_id', $transaction->id)
            ->where('progress_value', '>', 0)
            ->get();

        foreach ($challengeTransactions as $ct) {
            $participant = ChallengeParticipant::find($ct->challenge_participant_id);
            if (!$participant) continue;

            $progressBefore = $participant->current_progress;
            $reverseValue = -$ct->progress_value;
            $newProgress = max(0, $progressBefore + $reverseValue);

            // تسجيل معاملة تحدي عكسية
            ChallengeTransaction::create([
                'challenge_id' => $ct->challenge_id,
                'challenge_participant_id' => $ct->challenge_participant_id,
                'points_transaction_id' => $transaction->id,
                'user_type' => $ct->user_type,
                'employee_id' => $ct->employee_id,
                'client_id' => $ct->client_id,
                'action_type' => $ct->action_type,
                'progress_value' => $reverseValue,
                'progress_before' => $progressBefore,
                'progress_after' => $newProgress,
                'points' => -$ct->points,
                'amount' => $ct->amount ? -$ct->amount : null,
                'description' => 'عكس: ' . ($ct->description ?? 'إلغاء معاملة'),
                'metadata' => [
                    'reversal_of' => $ct->id,
                    'reason' => 'transaction_cancelled',
                ],
            ]);

            // تحديث تقدم المشارك
            $participant->updateProgress($newProgress);

            // تحديث تقدم الفريق
            if ($participant->team_id) {
                $this->updateTeamProgress($participant->team_id);
            }

            $reversedParticipants[] = $participant;
        }

        return $reversedParticipants;
    }

    /**
     * الحصول على إحصائيات التحديات
     */
    public function getStats(): array
    {
        return [
            'active_count' => Challenge::active()->ongoing()->count(),
            'upcoming_count' => Challenge::active()->upcoming()->count(),
            'ended_count' => Challenge::ended()->count(),
            'total_participants' => ChallengeParticipant::count(),
            'completed_participants' => ChallengeParticipant::where('is_completed', true)->count(),
            'total_points_awarded' => ChallengeParticipant::sum('points_awarded'),
        ];
    }
}
