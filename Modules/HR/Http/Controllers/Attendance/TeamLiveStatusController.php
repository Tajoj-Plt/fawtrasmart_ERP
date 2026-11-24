<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Attendance\DailyAttendanceBoard;
use App\Support\DurationLabel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * لوحةُ متابعة الفريق: أين فريقي الآن؟
 *
 * تفترق عن «سجل اليوم الموحَّد» في شيئين، وكلاهما مقصود:
 *
 *   ١) **مَن فيها يحدّده نطاقُ الرؤية وحده** (`data_scope_*`): المديرُ بنطاق
 *      «فريقه» يرى من يرأسهم، ومديرُ الفرع يرى فرعَه، والموارد البشرية
 *      المنشأة. وصاحبُ «بياناته هو» لا يرى أحداً — اللوحةُ للفريق لا للفرد.
 *      وخيارُ «المستويات الأدنى» حين يُطفأ يقصرها على المرؤوسين المباشرين.
 *
 *   ٢) **حيّة.** تتحدّث من نفسها كل دقيقة وتُحدِّث ساعتَها كل ثانية.
 *
 * وحسابُ الحالات في `DailyAttendanceBoard` لا هنا: «غائب» و«لم يُسجِّل
 * بعد» يجب ألّا تختلفا بين شاشةٍ وأخرى.
 */
class TeamLiveStatusController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:attendance_team.index');
    }

    public function index(Request $request)
    {
        $manager = $this->currentManager();
        $team = $this->teamOf($manager, $request);

        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        $board = app(DailyAttendanceBoard::class);
        $rows = $board->build($team, $date);

        return view('hr::attendance.team_board.index', [
            'manager' => $manager,
            'date' => $date,
            'rows' => $rows,
            'groups' => $this->groupByManager($rows, $board, $manager),
            'summary' => $board->summarise($rows),
            'includeIndirect' => $request->boolean('all_levels', true),
        ]);
    }

    /**
     * تحديثٌ حيٌّ بلا إعادة تحميل — تُناديه اللوحة كل دقيقة.
     */
    public function feed(Request $request)
    {
        $manager = $this->currentManager();
        $date = Carbon::today();

        $board = app(DailyAttendanceBoard::class);
        $rows = $board->build($this->teamOf($manager, $request), $date);

        return response()->json([
            'success' => true,
            'server_time' => Carbon::now()->format('H:i:s'),
            'summary' => $board->summarise($rows),
            'members' => $rows->map(fn (array $row) => [
                'id' => $row['employee']->id,
                'name' => $row['employee']->full_name,
                'title' => $row['employee']->job_title->name ?? null,
                'state' => $row['state'],
                'check_in' => $row['check_in'] ? substr($row['check_in'], 0, 5) : null,
                'check_out' => $row['check_out'] ? substr($row['check_out'], 0, 5) : null,
                'expected_in' => $row['expected_in'] ? substr($row['expected_in'], 0, 5) : null,
                'late_minutes' => $row['late_minutes'],
                // الخلايا تُحدَّث نصّاً كما تُعرض، فالتنسيقُ هنا لا في المتصفّح — صيغةٌ واحدةٌ للمدّة في الشاشتين
                'late_label' => $row['late_minutes'] ? DurationLabel::minutes($row['late_minutes']) : null,
                'working_label' => $row['working_minutes']
                    ? sprintf('%d:%02d', intdiv($row['working_minutes'], 60), $row['working_minutes'] % 60)
                    : null,
                'early_label' => $row['early_leave_minutes'] ? DurationLabel::minutes($row['early_leave_minutes']) : null,
                'over_label' => $row['overtime_minutes'] ? DurationLabel::minutes($row['overtime_minutes']) : null,
                'break_label' => $row['break_minutes'] ? DurationLabel::minutes($row['break_minutes']) : null,
                'early_leave_minutes' => $row['early_leave_minutes'],
                'overtime_minutes' => $row['overtime_minutes'],
                'on_break' => (bool) $row['on_break'],
                'channel' => $row['channel'],
                'location' => $row['location'],
                // رقمُ المدير يُرسَل لتُعاد عدّةُ كل مجموعةٍ في المتصفّح بلا إعادة تحميل
                'manager_id' => (int) ($row['employee']->direct_manager_id ?: 0),
                'is_late' => (bool) $row['is_late'],
            ])->values(),
        ]);
    }

    // ════════════════════════════════════════════════════

    /** ملفُّ موظف المستخدم إن وُجد — المستخدمُ غيرُ الموظف يرى بنطاقه أيضاً. */
    private function groupByManager(Collection $rows, DailyAttendanceBoard $board, ?Employee $manager): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $buckets[(int) ($row['employee']->direct_manager_id ?: 0)][] = $row;
        }

        if (! $buckets) {
            return [];
        }

        // أسماءُ المديرين باستعلامٍ واحد — والمديرُ قد لا يكون هو نفسُه داخلَ النطاق المعروض
        $managers = Employee::query()
            ->with('job_title:id,name')
            ->whereIn('id', array_filter(array_keys($buckets)))
            ->get()
            ->keyBy('id');

        $groups = [];

        foreach ($buckets as $id => $members) {
            $head = $id ? $managers->get($id) : null;

            $groups[] = [
                'manager_id' => $id,
                'manager' => $head,
                'name' => $head?->full_name ?: 'بلا مدير مباشر',
                'title' => $head?->job_title->name,
                'orphan' => $id === 0,
                'is_mine' => $manager && $id === (int) $manager->id,
                'rows' => $members,
                'summary' => $board->summarise(collect($members)),
            ];
        }

        usort($groups, function (array $a, array $b) {
            if ($a['is_mine'] !== $b['is_mine']) {
                return $a['is_mine'] ? -1 : 1;
            }

            if ($a['orphan'] !== $b['orphan']) {
                return $a['orphan'] ? 1 : -1;
            }

            return count($b['rows']) <=> count($a['rows'])
                ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        return $groups;
    }

    private function currentManager(): ?Employee
    {
        $employeeId = Auth::user()?->employee_id;

        return $employeeId ? Employee::with('job_title')->find($employeeId) : null;
    }

    /**
     * الموظفون ضمن النطاق (وفرع الشريط) — عدا المستخدم نفسه.
     *
     * @return Collection<int, Employee>
     */
    private function teamOf(?Employee $manager, Request $request): Collection
    {
        $query = Employee::query()
            ->with([
                'department:id,name', 'branch:id,name', 'job_title:id,name', 'attendanceConfig',
                'direct_manager:id,first_name,middle_name,nickname',
            ])
            ->orderBy('first_name');

        $this->scopeToEmployees($query, 'id');

        if ($manager) {
            $query->where('id', '!=', $manager->id);

            if (! $request->boolean('all_levels', true)) {
                $query->whereIn('id', $manager->children()->pluck('id')->all() ?: [0]);
            }
        }

        return $query->get();
    }
}
