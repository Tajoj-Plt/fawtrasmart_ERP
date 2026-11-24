<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Enums\ChallengeType;
use App\Enums\UserType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Ecosystem\Challenge;
use App\Models\Ecosystem\ChallengeParticipant;
use App\Models\Ecosystem\ChallengeTeam;
use App\Services\ChallengeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChallengeTeamController extends Controller
{
    /**
     * عرض قائمة الفرق
     */
    public function index(Request $request)
    {
        $query = ChallengeTeam::with(['challenge', 'leader']);

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // فلترة حسب التحدي
        if ($request->filled('challenge_id')) {
            $query->where('challenge_id', $request->challenge_id);
        }

        // فلترة حسب نوع الفريق
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // فلترة حسب حالة الإكمال
        if ($request->filled('is_completed')) {
            $query->where('is_completed', $request->is_completed === '1');
        }

        $teams = $query->withCount('members')->orderByDesc('created_at')->paginate(15)->withQueryString();
        $challenges = Challenge::where('challenge_type', ChallengeType::TEAM)->orderBy('name')->get();

        // إحصائيات
        $stats = [
            'total_teams' => ChallengeTeam::count(),
            'total_members' => ChallengeParticipant::whereNotNull('team_id')->count(),
            'team_challenges' => Challenge::where('challenge_type', ChallengeType::TEAM)->count(),
            'completed_teams' => ChallengeTeam::where('is_completed', true)->count(),
        ];

        return view('ecosystem::points.challenges.teams.index', compact('teams', 'challenges', 'stats'));
    }

    /**
     * نموذج إنشاء فريق جديد
     */
    public function create()
    {
        $challenges = Challenge::where('challenge_type', ChallengeType::TEAM)
            ->where('is_active', true)
            ->where('ends_at', '>=', now())
            ->orderBy('name')
            ->get();

        $employees = Employee::active()
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->full_name ?? trim($e->first_name . ' ' . ($e->middle_name ?? '') . ' ' . ($e->nickname ?? '')),
            ]);

        // الموظفين المأخوذين في فرق لكل تحدي (لفلترة القائد)
        $challengeIds = $challenges->pluck('id');
        $takenEmployees = ChallengeParticipant::whereIn('challenge_id', $challengeIds)
            ->whereNotNull('team_id')
            ->whereNotNull('employee_id')
            ->get()
            ->groupBy('challenge_id')
            ->map(fn($items) => $items->pluck('employee_id')->unique()->values()->toArray())
            ->toArray();

        // خريطة نوع المستخدم لكل تحدي (employee, client, both)
        $challengeUserTypes = $challenges->pluck('user_type', 'id')
            ->map(fn($v) => $v instanceof UserType ? $v->value : (string)$v)
            ->toArray();

        return view('ecosystem::points.challenges.teams.create', compact('challenges', 'employees', 'takenEmployees', 'challengeUserTypes'));
    }

    /**
     * حفظ فريق جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'challenge_id' => 'required|exists:challenges,id',
            'name' => 'required|string|max:255',
            'user_type' => 'required|in:employee,client',
            'leader_employee_id' => 'nullable|exists:employees,id',
            'members' => 'nullable|array',
            'members.*' => 'integer',
        ]);

        $challenge = Challenge::findOrFail($validated['challenge_id']);

        // التحقق من أن التحدي من نوع فرق
        if ($challenge->challenge_type !== ChallengeType::TEAM) {
            return redirect()->back()->with('error', 'هذا التحدي ليس تحدي فرق')->withInput();
        }

        // التحقق: نوع الفريق يتوافق مع نوع التحدي
        $challengeUserType = $challenge->user_type instanceof UserType ? $challenge->user_type->value : (string)$challenge->user_type;
        if ($challengeUserType !== 'both' && $challengeUserType !== $validated['user_type']) {
            $label = $challengeUserType === 'employee' ? 'موظفين' : 'عملاء';
            return redirect()->back()->with('error', "هذا التحدي مخصص لل{$label} فقط")->withInput();
        }

        // التحقق: إذا كان فريق عملاء لا يمكن تعيين قائد موظف
        if ($validated['user_type'] === 'client' && !empty($validated['leader_employee_id'])) {
            return redirect()->back()->with('error', 'لا يمكن تعيين قائد موظف لفريق عملاء')->withInput();
        }

        // التحقق: القائد ليس في فريق آخر بنفس التحدي
        if (!empty($validated['leader_employee_id'])) {
            $leaderInTeam = ChallengeParticipant::where('challenge_id', $validated['challenge_id'])
                ->where('employee_id', $validated['leader_employee_id'])
                ->whereNotNull('team_id')
                ->exists();

            if ($leaderInTeam) {
                return redirect()->back()->with('error', 'هذا الموظف موجود بالفعل في فريق آخر لنفس التحدي')->withInput();
            }
        }

        $team = ChallengeTeam::create([
            'challenge_id' => $validated['challenge_id'],
            'name' => $validated['name'],
            'user_type' => $validated['user_type'],
            'leader_employee_id' => ($validated['user_type'] === 'employee') ? ($validated['leader_employee_id'] ?? null) : null,
        ]);

        // إضافة الأعضاء
        $addedCount = 0;
        if (!empty($validated['members'])) {
            $service = app(ChallengeService::class);
            $userType = UserType::from($validated['user_type']);
            foreach ($validated['members'] as $userId) {
                $participant = $service->enrollParticipant(
                    $challenge,
                    $userId,
                    $userType,
                    $team->id
                );
                if ($participant) {
                    $addedCount++;
                }
            }
        }

        $message = 'تم إنشاء الفريق بنجاح';
        if ($addedCount > 0) {
            $message .= " وتم إضافة {$addedCount} عضو";
        }

        return redirect()
            ->route('ecosystem.points.challenges.teams.show', $team)
            ->with('success', $message);
    }

    /**
     * عرض تفاصيل فريق
     */
    public function show(ChallengeTeam $team)
    {
        $team->load(['challenge', 'leader', 'members.employee', 'members.client']);

        $teamStats = [
            'total_members' => $team->members->count(),
            'completed_members' => $team->members->where('is_completed', true)->count(),
            'total_progress' => $team->total_progress,
            'avg_progress' => $team->members->count() > 0
                ? round($team->members->avg('current_progress'), 1)
                : 0,
            'points_distributed' => $team->members->sum('points_awarded'),
        ];

        return view('ecosystem::points.challenges.teams.show', compact('team', 'teamStats'));
    }

    /**
     * نموذج تعديل فريق
     */
    public function edit(ChallengeTeam $team)
    {
        $team->load(['challenge', 'leader', 'members.employee', 'members.client']);

        $challenges = Challenge::where('challenge_type', ChallengeType::TEAM)
            ->orderBy('name')
            ->get();

        // جلب القادة المتاحين (فقط إذا كان فريق موظفين)
        $employees = collect();
        if ($team->isEmployeeTeam()) {
            $employeesInOtherTeams = ChallengeParticipant::where('challenge_id', $team->challenge_id)
                ->whereNotNull('team_id')
                ->where('team_id', '!=', $team->id)
                ->whereNotNull('employee_id')
                ->pluck('employee_id')
                ->toArray();

            $employees = Employee::active()
                ->whereNotIn('id', $employeesInOtherTeams)
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'name' => $e->full_name ?? trim($e->first_name . ' ' . ($e->middle_name ?? '') . ' ' . ($e->nickname ?? '')),
                ]);
        }

        return view('ecosystem::points.challenges.teams.edit', compact('team', 'challenges', 'employees'));
    }

    /**
     * تحديث فريق
     */
    public function update(Request $request, ChallengeTeam $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'leader_employee_id' => 'nullable|exists:employees,id',
        ]);

        $team->update($validated);

        return redirect()
            ->route('ecosystem.points.challenges.teams.show', $team)
            ->with('success', 'تم تحديث الفريق بنجاح');
    }

    /**
     * حذف فريق
     */
    public function destroy(ChallengeTeam $team)
    {
        // التحقق من عدم وجود مشاركين حصلوا على نقاط
        $hasPoints = ChallengeParticipant::where('team_id', $team->id)
            ->where('points_awarded', '>', 0)
            ->exists();

        if ($hasPoints) {
            return redirect()->back()->with('error', 'لا يمكن حذف فريق به مشاركين حصلوا على نقاط');
        }

        // حذف المشاركين (الأعضاء)
        ChallengeParticipant::where('team_id', $team->id)->delete();
        $team->delete();

        return redirect()
            ->route('ecosystem.points.challenges.teams.index')
            ->with('success', 'تم حذف الفريق بنجاح');
    }

    /**
     * إضافة عضو للفريق
     */
    public function addMember(Request $request, ChallengeTeam $team)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        // نوع المستخدم يُحدد من نوع الفريق
        $userTypeStr = $team->user_type;

        // التحقق من أن المستخدم ليس في فريق آخر بنفس التحدي
        $field = $userTypeStr === 'employee' ? 'employee_id' : 'client_id';
        $existing = ChallengeParticipant::where('challenge_id', $team->challenge_id)
            ->where($field, $validated['user_id'])
            ->whereNotNull('team_id')
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', 'هذا المستخدم موجود بالفعل في فريق آخر لنفس التحدي');
        }

        $userType = UserType::from($userTypeStr);
        $service = app(ChallengeService::class);

        $participant = $service->enrollParticipant(
            $team->challenge,
            $validated['user_id'],
            $userType,
            $team->id
        );

        if ($participant) {
            return redirect()->back()->with('success', 'تم إضافة العضو للفريق بنجاح');
        }

        return redirect()->back()->with('error', 'فشل في إضافة العضو. قد يكون مسجلاً مسبقاً');
    }

    /**
     * إزالة عضو من الفريق
     */
    public function removeMember(ChallengeTeam $team, ChallengeParticipant $participant)
    {
        if ($participant->team_id !== $team->id) {
            return redirect()->back()->with('error', 'هذا العضو ليس في الفريق');
        }

        if ($participant->points_awarded) {
            return redirect()->back()->with('error', 'لا يمكن إزالة عضو حصل على نقاط');
        }

        $participant->delete();

        return redirect()->back()->with('success', 'تم إزالة العضو من الفريق بنجاح');
    }

    /**
     * جلب المستخدمين المتاحين (غير مسجلين في فريق آخر) - AJAX
     */
    public function getAvailableUsers(Request $request)
    {
        $userType = $request->get('user_type', 'employee');
        $challengeId = $request->get('challenge_id');

        // IDs اللي موجودين في فرق بنفس التحدي
        $existingIds = [];
        if ($challengeId) {
            $field = $userType === 'employee' ? 'employee_id' : 'client_id';
            $existingIds = ChallengeParticipant::where('challenge_id', $challengeId)
                ->whereNotNull('team_id')
                ->whereNotNull($field)
                ->pluck($field)
                ->toArray();
        }

        if ($userType === 'employee') {
            $users = Employee::active()
                ->when(!empty($existingIds), fn($q) => $q->whereNotIn('id', $existingIds))
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'name' => $e->full_name ?? trim($e->first_name . ' ' . ($e->middle_name ?? '') . ' ' . ($e->nickname ?? '')),
                ]);
        } else {
            $users = Client::where('status', 1)
                ->when(!empty($existingIds), fn($q) => $q->whereNotIn('id', $existingIds))
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->full_name ?? trim($c->first_name . ' ' . ($c->middle_name ?? '')),
                ]);
        }

        return response()->json($users);
    }
}
