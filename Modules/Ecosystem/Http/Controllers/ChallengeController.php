<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Enums\ChallengeType;
use App\Enums\CompetitionType;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Ecosystem\Challenge;
use App\Models\Ecosystem\ChallengeParticipant;
use App\Models\Ecosystem\ChallengeTeam;
use App\Models\Ecosystem\PointsTier;
use App\Services\ChallengeService;
use App\Services\PointsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Ecosystem\PointsGeneralSetting;

class ChallengeController extends Controller
{
    /**
     * عرض قائمة التحديات
     */
    public function index(Request $request)
    {
        $query = Challenge::with(['branch', 'creator', 'minTier'])
            ->withCount(['participants', 'participants as completed_count' => function ($q) {
                $q->where('is_completed', true);
            }]);

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فلتر نوع المستخدم
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // فلتر نوع التحدي
        if ($request->filled('challenge_type')) {
            $query->where('challenge_type', $request->challenge_type);
        }

        // فلتر الحالة
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active()->ongoing();
                    break;
                case 'upcoming':
                    $query->active()->upcoming();
                    break;
                case 'ended':
                    $query->ended();
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
            }
        }

        // فلتر الفرع
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $challenges = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        // إحصائيات
        $stats = app(ChallengeService::class)->getStats();
        $branches = Branch::where('status', 'active')->get();
        $challengeTypes = ChallengeType::cases();
        $userTypes = UserType::values();

        return view('ecosystem::points.challenges.index', compact(
            'challenges', 'stats', 'branches', 'challengeTypes', 'userTypes'
        ));
    }

    /**
     * نموذج إنشاء تحدي جديد
     */
    public function create()
    {
        $branches = Branch::where('status', 'active')->get();
        $challengeTypes = ChallengeType::cases();
        $competitionTypes = CompetitionType::cases();
        $userTypes = UserType::values();
        $tiers = PointsTier::orderBy('min_points')->get();

        // قائمة الإجراءات المتاحة مجمّعة
        $actionTypes = [
            'employee' => \App\Enums\ActionType::groupedForUserType('employee'),
            'client' => \App\Enums\ActionType::groupedForUserType('client'),
        ];

        return view('ecosystem::points.challenges.create', compact(
            'branches', 'challengeTypes', 'competitionTypes', 'userTypes', 'tiers', 'actionTypes'
        ));
    }

    /**
     * حفظ تحدي جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'user_type' => 'required|string|in:employee,client,both',
            'challenge_type' => 'required|string|in:' . implode(',', ChallengeType::values()),
            'competition_type' => 'required|string|in:' . implode(',', CompetitionType::values()),
            'target_action' => 'required|array|min:1',
            'target_action.*' => 'required|string|max:255',
            'target_value' => 'required|integer|min:1',
            'target_unit' => 'nullable|string|max:50',
            'reward_points' => 'required|integer|min:1',
            'reward_points_2nd' => 'nullable|integer|min:0',
            'reward_points_3rd' => 'nullable|integer|min:0',
            'max_winners' => 'nullable|integer|min:1',
            'min_tier_id' => 'nullable|exists:points_tiers,id',
            'rules' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'auto_enroll' => 'boolean',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['is_featured'] = $request->has('is_featured');
        $validated['auto_enroll'] = $request->has('auto_enroll');
        $validated['created_by'] = Auth::id();

        // =====================================================
        // تطبيق إعدادات النظام العامة على التحديات
        // =====================================================
        try {
            $generalSettings = PointsGeneralSetting::instance();

            // فحص الحد الأقصى للتحديات النشطة
            if ($generalSettings->max_active_challenges > 0 && $validated['is_active']) {
                $activeChallengesCount = Challenge::where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                    })
                    ->count();

                if ($activeChallengesCount >= $generalSettings->max_active_challenges) {
                    return redirect()->back()->withInput()
                        ->with('error', 'تم الوصول للحد الأقصى من التحديات النشطة (' . $generalSettings->max_active_challenges . '). قم بإنهاء تحدي حالي أو زيادة الحد من الإعدادات العامة.');
                }
            }

            // تطبيق إعداد التسجيل التلقائي من الإعدادات العامة
            if ($generalSettings->auto_enroll_challenges && !$request->has('auto_enroll')) {
                $validated['auto_enroll'] = true;
            }

            // فحص السماح بالفرق
            if (!$generalSettings->allow_challenge_teams && $validated['challenge_type'] === 'team') {
                return redirect()->back()->withInput()
                    ->with('error', 'التحديات الجماعية (الفرق) غير مسموح بها حالياً. يمكنك تفعيلها من الإعدادات العامة.');
            }
        } catch (\Exception $e) {
            // تجاوز في حالة عدم وجود جدول الإعدادات
        }

        // رفع الصورة
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('challenges', 'public');
        }

        $challenge = Challenge::create($validated);

        // تسجيل تلقائي إذا مفعل
        $enrolledCount = 0;
        if ($challenge->auto_enroll) {
            $enrolledCount = app(ChallengeService::class)->autoEnrollAll($challenge);
        }

        $message = 'تم إنشاء التحدي بنجاح';
        if ($enrolledCount > 0) {
            $message .= ' وتم تسجيل ' . $enrolledCount . ' مشارك تلقائياً';
        }

        return redirect()
            ->route('ecosystem.points.challenges.show', $challenge)
            ->with('success', $message);
    }

    /**
     * عرض تفاصيل تحدي
     */
    public function show(Challenge $challenge)
    {
        $challenge->load(['branch', 'creator', 'minTier']);

        // المشاركون مرتبون حسب التقدم
        $participants = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->with(['employee', 'client'])
            ->orderByDesc('current_progress')
            ->paginate(20);

        // الفرق (إذا كان تحدي جماعي)
        $teams = null;
        if ($challenge->challenge_type === ChallengeType::TEAM || 
            (is_string($challenge->challenge_type) && $challenge->challenge_type === 'team')) {
            $teams = ChallengeTeam::where('challenge_id', $challenge->id)
                ->with(['leader'])
                ->withCount('members')
                ->orderByDesc('total_progress')
                ->get();
        }

        // معاملات التحدي (سجل المعاملات المرتبطة)
        $challengeTransactions = \App\Models\Ecosystem\ChallengeTransaction::where('challenge_id', $challenge->id)
            ->with(['participant', 'employee', 'client', 'pointsTransaction'])
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'transactions_page');

        // إحصائيات التحدي
        $challengeStats = [
            'total_participants' => ChallengeParticipant::where('challenge_id', $challenge->id)->count(),
            'completed' => ChallengeParticipant::where('challenge_id', $challenge->id)->where('is_completed', true)->count(),
            'in_progress' => ChallengeParticipant::where('challenge_id', $challenge->id)->where('is_completed', false)->count(),
            'points_distributed' => ChallengeParticipant::where('challenge_id', $challenge->id)->sum('points_awarded'),
            'avg_progress' => ChallengeParticipant::where('challenge_id', $challenge->id)->avg('current_progress') ?? 0,
            'total_transactions' => \App\Models\Ecosystem\ChallengeTransaction::where('challenge_id', $challenge->id)->count(),
        ];

        return view('ecosystem::points.challenges.show', compact(
            'challenge', 'participants', 'teams', 'challengeStats', 'challengeTransactions'
        ));
    }

    /**
     * نموذج تعديل تحدي
     */
    public function edit(Challenge $challenge)
    {
        $branches = Branch::where('status', 'active')->get();
        $challengeTypes = ChallengeType::cases();
        $competitionTypes = CompetitionType::cases();
        $userTypes = UserType::values();
        $tiers = PointsTier::orderBy('min_points')->get();
        $actionTypes = [
            'employee' => \App\Enums\ActionType::groupedForUserType('employee'),
            'client' => \App\Enums\ActionType::groupedForUserType('client'),
        ];

        return view('ecosystem::points.challenges.edit', compact(
            'challenge', 'branches', 'challengeTypes', 'competitionTypes', 'userTypes', 'tiers', 'actionTypes'
        ));
    }

    /**
     * تحديث تحدي
     */
    public function update(Request $request, Challenge $challenge)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'user_type' => 'required|string|in:employee,client,both',
            'challenge_type' => 'required|string|in:' . implode(',', ChallengeType::values()),
            'competition_type' => 'required|string|in:' . implode(',', CompetitionType::values()),
            'target_action' => 'required|array|min:1',
            'target_action.*' => 'required|string|max:255',
            'target_value' => 'required|integer|min:1',
            'target_unit' => 'nullable|string|max:50',
            'reward_points' => 'required|integer|min:1',
            'reward_points_2nd' => 'nullable|integer|min:0',
            'reward_points_3rd' => 'nullable|integer|min:0',
            'max_winners' => 'nullable|integer|min:1',
            'min_tier_id' => 'nullable|exists:points_tiers,id',
            'rules' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'auto_enroll' => 'boolean',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['is_featured'] = $request->has('is_featured');
        $validated['auto_enroll'] = $request->has('auto_enroll');

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('challenges', 'public');
        }

        $challenge->update($validated);

        return redirect()
            ->route('ecosystem.points.challenges.show', $challenge)
            ->with('success', 'تم تحديث التحدي بنجاح');
    }

    /**
     * حذف تحدي
     */
    public function destroy(Challenge $challenge)
    {
        $challenge->delete();
        return redirect()
            ->route('ecosystem.points.challenges.index')
            ->with('success', 'تم حذف التحدي بنجاح');
    }

    /**
     * تفعيل/تعطيل التحدي
     */
    public function toggleStatus(Challenge $challenge)
    {
        $challenge->update(['is_active' => !$challenge->is_active]);
        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة التحدي بنجاح',
            'is_active' => $challenge->is_active,
        ]);
    }

    /**
     * تسجيل مشارك
     */
    public function enroll(Request $request, Challenge $challenge)
    {
        // لا يمكن إضافة مشاركين مباشرة لتحدي فرق
        if ($challenge->challenge_type === ChallengeType::TEAM) {
            return redirect()->back()->with('error', 'لا يمكن إضافة مشاركين مباشرة في تحدي فرق. استخدم إدارة الفرق');
        }

        $validated = $request->validate([
            'user_type' => 'required|in:employee,client',
            'user_id' => 'required|integer',
        ]);

        // التحقق: نوع المستخدم يتوافق مع نوع التحدي
        $challengeUserType = $challenge->user_type instanceof UserType ? $challenge->user_type->value : (string)$challenge->user_type;
        if ($challengeUserType !== 'both' && $challengeUserType !== $validated['user_type']) {
            $label = $challengeUserType === 'employee' ? 'موظفين' : 'عملاء';
            return redirect()->back()->with('error', "هذا التحدي مخصص لل{$label} فقط");
        }

        // التحقق: إذا كان التحدي مرتبط بفرع، المستخدم يجب أن يكون من نفس الفرع
        if ($challenge->branch_id) {
            if ($validated['user_type'] === 'employee') {
                $inBranch = Employee::where('id', $validated['user_id'])
                    ->where('branch_id', $challenge->branch_id)
                    ->exists();
            } else {
                $inBranch = Client::where('id', $validated['user_id'])
                    ->where('branch_id', $challenge->branch_id)
                    ->exists();
            }
            if (!$inBranch) {
                return redirect()->back()->with('error', 'هذا المستخدم ليس من نفس فرع التحدي');
            }
        }

        $userType = UserType::from($validated['user_type']);
        $service = app(ChallengeService::class);
        $participant = $service->enrollParticipant($challenge, $validated['user_id'], $userType);

        if ($participant) {
            return redirect()
                ->route('ecosystem.points.challenges.show', $challenge)
                ->with('success', 'تم تسجيل المشارك بنجاح');
        }

        return redirect()
            ->route('ecosystem.points.challenges.show', $challenge)
            ->with('error', 'فشل في تسجيل المشارك. قد يكون مسجلاً مسبقاً أو التحدي غير متاح');
    }

    /**
     * إلغاء مشاركة
     */
    public function removeParticipant(Challenge $challenge, ChallengeParticipant $participant)
    {
        // لا يمكن إزالة مشاركين في تحدي فرق (يتم من إدارة الفرق)
        if ($challenge->challenge_type === ChallengeType::TEAM) {
            return redirect()->back()->with('error', 'لا يمكن إزالة مشاركين من تحدي فرق. استخدم صفحة إدارة الفرق');
        }

        if ($participant->points_awarded) {
            return redirect()
                ->route('ecosystem.points.challenges.show', $challenge)
                ->with('error', 'لا يمكن إزالة مشارك حصل على نقاط');
        }

        $participant->delete();
        return redirect()
            ->route('ecosystem.points.challenges.show', $challenge)
            ->with('success', 'تم إزالة المشارك بنجاح');
    }

    /**
     * إنهاء التحدي وتوزيع الجوائز
     */
    public function finalize(Challenge $challenge)
    {
        $service = app(ChallengeService::class);
        $results = $service->finalizeChallenge($challenge);

        $isTeamChallenge = $challenge->challenge_type === ChallengeType::TEAM
            || (is_string($challenge->challenge_type) && $challenge->challenge_type === 'team');

        $message = 'تم إنهاء التحدي بنجاح. ';

        if ($isTeamChallenge) {
            // ===== تحدي فرق =====
            $rankedTeams = ChallengeTeam::where('challenge_id', $challenge->id)->count();
            $message .= 'تم ترتيب ' . $rankedTeams . ' فريق. ';

            // جلب الفرق الفائزة (التي حصل أعضاؤها على نقاط)
            $winningTeams = ChallengeTeam::where('challenge_id', $challenge->id)
                ->whereHas('members', fn($q) => $q->where('points_awarded', '>', 0))
                ->with(['members' => fn($q) => $q->where('points_awarded', '>', 0)->with(['employee', 'client'])])
                ->orderBy('rank')
                ->get();

            if ($winningTeams->count() > 0) {
                $totalPoints = ChallengeParticipant::where('challenge_id', $challenge->id)
                    ->where('points_awarded', '>', 0)->sum('points_awarded');
                $message .= 'إجمالي النقاط الموزعة: ' . number_format($totalPoints) . ' نقطة. ';

                $teamDetails = [];
                foreach ($winningTeams as $team) {
                    $teamPoints = $team->members->sum('points_awarded');
                    $memberNames = $team->members->map(function ($m) {
                        $name = $m->employee?->full_name ?? $m->employee?->first_name
                            ?? $m->client?->full_name ?? $m->client?->first_name ?? 'غير معروف';
                        return $name . ' (' . number_format($m->points_awarded) . ')';
                    })->implode('، ');
                    $teamDetails[] = 'المركز ' . $team->rank . ' - ' . $team->name
                        . ' (' . number_format($teamPoints) . ' نقطة): ' . $memberNames;
                }
                $message .= 'الفرق الفائزة: ' . implode(' | ', $teamDetails);
            } else {
                $message .= 'لم يتم منح أي نقاط (لا يوجد فرق مستحقة).';
            }
        } else {
            // ===== تحدي أفراد =====
            $message .= 'تم ترتيب ' . $results['ranked'] . ' مشارك. ';

            $winners = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('points_awarded', '>', 0)
                ->with(['employee', 'client'])
                ->orderBy('rank')
                ->get();

            if ($winners->count() > 0) {
                $totalPoints = $winners->sum('points_awarded');
                $message .= 'تم منح ' . $winners->count() . ' مشارك إجمالي ' . number_format($totalPoints) . ' نقطة. ';

                $winnerNames = [];
                foreach ($winners as $winner) {
                    $name = $winner->employee?->full_name ?? $winner->employee?->first_name
                        ?? $winner->client?->full_name ?? $winner->client?->first_name ?? 'غير معروف';
                    $winnerNames[] = 'المركز ' . $winner->rank . ': ' . $name . ' (' . number_format($winner->points_awarded) . ' نقطة)';
                }
                $message .= 'الفائزون: ' . implode(' | ', $winnerNames);
            } else {
                $message .= 'لم يتم منح أي نقاط (لا يوجد مشاركون مستحقون).';
            }
        }

        // إرسال إشعارات للفائزين
        try {
            $notificationService = new PointsNotificationService();
            $allWinners = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('points_awarded', '>', 0)
                ->with(['employee', 'client'])
                ->get();

            foreach ($allWinners as $winner) {
                $winnerUserType = $winner->employee_id ? 'employee' : 'client';
                $winnerName = $winnerUserType === 'employee'
                    ? ($winner->employee?->full_name ?? 'موظف')
                    : ($winner->client?->full_name ?? $winner->client?->trade_name ?? 'عميل');

                $notificationService->notifyChallengeCompleted(
                    $winnerUserType,
                    $winner->employee_id,
                    $winner->client_id,
                    [
                        'user_name' => $winnerName,
                        'challenge_name' => $challenge->name,
                        'rank' => $winner->rank,
                        'points_awarded' => $winner->points_awarded,
                    ]
                );
            }
        } catch (\Exception $e) {
            \Log::warning('فشل إرسال إشعارات إنهاء التحدي: ' . $e->getMessage());
        }

        return redirect()
            ->route('ecosystem.points.challenges.show', $challenge)
            ->with('success', $message);
    }

    /**
     * صفحة إضافة مشاركين دفعة واحدة
     */
    public function bulkEnrollForm(Challenge $challenge)
    {
        // لا يمكن لتحدي فرق
        if ($challenge->challenge_type === ChallengeType::TEAM) {
            return redirect()->route('ecosystem.points.challenges.show', $challenge)
                ->with('error', 'لا يمكن إضافة مشاركين مباشرة في تحدي فرق. استخدم إدارة الفرق');
        }

        $challenge->load('branch');

        $challengeUserType = $challenge->user_type instanceof UserType
            ? $challenge->user_type->value
            : (string)$challenge->user_type;

        // المشاركون المسجلون مسبقاً
        $existingEmployeeIds = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->whereNotNull('employee_id')->pluck('employee_id')->toArray();
        $existingClientIds = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->whereNotNull('client_id')->pluck('client_id')->toArray();

        $employees = collect();
        $clients = collect();

        // جلب الموظفين المتاحين
        if (in_array($challengeUserType, ['employee', 'both'])) {
            $employees = Employee::active()
                ->when($challenge->branch_id, fn($q) => $q->where('branch_id', $challenge->branch_id))
                ->when(!empty($existingEmployeeIds), fn($q) => $q->whereNotIn('id', $existingEmployeeIds))
                ->with(['branch', 'department', 'job_title'])
                ->orderBy('first_name')
                ->get();
        }

        // جلب العملاء المتاحين
        if (in_array($challengeUserType, ['client', 'both'])) {
            $query = Client::query()
                ->when($challenge->branch_id, fn($q) => $q->where('branch_id', $challenge->branch_id))
                ->when(!empty($existingClientIds), fn($q) => $q->whereNotIn('id', $existingClientIds))
                ->with('branch');
            try {
                $query->whereHas('status_client', fn($q) => $q->where('state', 1));
            } catch (\Exception $e) {}
            $clients = $query->orderBy('first_name')->get();
        }

        $currentParticipantsCount = ChallengeParticipant::where('challenge_id', $challenge->id)->count();

        return view('ecosystem::points.challenges.bulk-enroll', compact(
            'challenge', 'employees', 'clients', 'currentParticipantsCount'
        ));
    }

    /**
     * تسجيل مشاركين دفعة واحدة
     */
    public function bulkEnroll(Request $request, Challenge $challenge)
    {
        // لا يمكن لتحدي فرق
        if ($challenge->challenge_type === ChallengeType::TEAM) {
            return redirect()->back()->with('error', 'لا يمكن إضافة مشاركين مباشرة في تحدي فرق');
        }

        $validated = $request->validate([
            'users' => 'required|array|min:1',
            'users.*' => 'required|string',
        ]);

        $challengeUserType = $challenge->user_type instanceof UserType
            ? $challenge->user_type->value
            : (string)$challenge->user_type;

        $service = app(ChallengeService::class);
        $successCount = 0;
        $failCount = 0;

        foreach ($validated['users'] as $userEntry) {
            // الصيغة: employee_123 أو client_456
            $parts = explode('_', $userEntry, 2);
            if (count($parts) !== 2) {
                $failCount++;
                continue;
            }

            [$type, $userId] = $parts;

            // التحقق من نوع المستخدم
            if (!in_array($type, ['employee', 'client'])) {
                $failCount++;
                continue;
            }
            if ($challengeUserType !== 'both' && $challengeUserType !== $type) {
                $failCount++;
                continue;
            }

            // التحقق من الفرع
            if ($challenge->branch_id) {
                if ($type === 'employee') {
                    $inBranch = Employee::where('id', $userId)->where('branch_id', $challenge->branch_id)->exists();
                } else {
                    $inBranch = Client::where('id', $userId)->where('branch_id', $challenge->branch_id)->exists();
                }
                if (!$inBranch) {
                    $failCount++;
                    continue;
                }
            }

            try {
                $userType = UserType::from($type);
                $participant = $service->enrollParticipant($challenge, (int)$userId, $userType);
                if ($participant) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $failCount++;
            }
        }

        $message = "تم تسجيل {$successCount} مشارك بنجاح";
        if ($failCount > 0) {
            $message .= " ({$failCount} لم يتم تسجيلهم - قد يكونون مسجلين مسبقاً)";
        }

        return redirect()
            ->route('ecosystem.points.challenges.show', $challenge)
            ->with('success', $message);
    }

    /**
     * لوحة المتصدرين
     */
    public function leaderboard(Challenge $challenge)
    {
        $challenge->load(['branch']);

        $isTeamChallenge = $challenge->challenge_type === ChallengeType::TEAM ||
            (is_string($challenge->challenge_type) && $challenge->challenge_type === 'team');

        $teams = null;
        if ($isTeamChallenge) {
            $teams = ChallengeTeam::where('challenge_id', $challenge->id)
                ->with(['leader', 'members.employee', 'members.client'])
                ->withCount('members')
                ->orderByDesc('total_progress')
                ->paginate(50);
        }

        $participants = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->with(['employee', 'client'])
            ->orderByDesc('current_progress')
            ->paginate(50);

        return view('ecosystem::points.challenges.leaderboard', compact('challenge', 'participants', 'teams', 'isTeamChallenge'));
    }

    /**
     * جلب الموظفين أو العملاء (AJAX) - مفلتر حسب الفرع
     */
    public function getUsers(Request $request)
    {
        $userType = $request->get('user_type', 'employee');
        $challengeId = $request->get('challenge_id');
        $branchId = null;

        // جلب فرع التحدي للفلترة
        if ($challengeId) {
            $branchId = Challenge::where('id', $challengeId)->value('branch_id');
        }

        // استبعاد المشاركين المسجلين مسبقاً
        $existingIds = [];
        if ($challengeId) {
            $field = $userType === 'employee' ? 'employee_id' : 'client_id';
            $existingIds = ChallengeParticipant::where('challenge_id', $challengeId)
                ->whereNotNull($field)
                ->pluck($field)
                ->toArray();
        }

        if ($userType === 'employee') {
            $users = Employee::active()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->when(!empty($existingIds), fn($q) => $q->whereNotIn('id', $existingIds))
                ->get()
                ->map(fn($e) => ['id' => $e->id, 'name' => $e->full_name]);
        } else {
            $query = Client::query()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->when(!empty($existingIds), fn($q) => $q->whereNotIn('id', $existingIds));

            // محاولة فلترة العملاء النشطين
            try {
                $query->whereHas('status_client', fn($q) => $q->where('state', 1));
            } catch (\Exception $e) {
                // إذا لم توجد العلاقة
            }

            $users = $query->get()
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->full_name]);
        }

        return response()->json($users);
    }

    /**
     * حذف متعدد
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'لم يتم تحديد أي تحديات']);
        }

        Challenge::whereIn('id', $ids)->delete();
        return response()->json([
            'success' => true,
            'message' => 'تم حذف ' . count($ids) . ' تحدي بنجاح',
        ]);
    }

    // ==========================================
    // لوحة المتصدرين (عامة)
    // ==========================================

    /**
     * لوحة متصدرين عامة لجميع التحديات
     */
    public function leaderboardAll(Request $request)
    {
        $query = Challenge::where('is_active', true);

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $now = now();
            if ($request->status === 'ongoing') {
                $query->where('starts_at', '<=', $now)->where('ends_at', '>=', $now);
            } elseif ($request->status === 'upcoming') {
                $query->where('starts_at', '>', $now);
            } elseif ($request->status === 'ended') {
                $query->where('ends_at', '<', $now);
            }
        }

        $challenges = $query->orderByDesc('created_at')->get();

        // إحصائيات عامة
        $stats = [
            'total_challenges' => Challenge::count(),
            'active_challenges' => Challenge::where('is_active', true)->count(),
            'total_participants' => ChallengeParticipant::count(),
            'total_points_distributed' => ChallengeParticipant::sum('points_awarded'),
        ];

        return view('ecosystem::points.challenges.leaderboard.index', compact('challenges', 'stats'));
    }
}

