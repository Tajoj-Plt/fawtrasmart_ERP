<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmployeeClientVisit;
use App\Models\User;
use App\Services\Itinerary\DailyItineraryService;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «خط سيري اليومي» — المندوب يرى يومه ويضيف عليه، ودورٌ آخر يضيف إلى خطّ
 * مندوبٍ في نطاقه.
 *
 * ثلاث صلاحيات ({@see \App\Constants\permissions\ClientsPermissions} ← خطوط السير):
 *
 *   • `clients_daily_itinerary_view`           — الشاشة لخطّه هو.
 *   • `clients_daily_itinerary_add_client`     — يضيف عميلاً إلى خطّه هو.
 *   • `clients_daily_itinerary_add_for_others` — يفتح خطَّ مندوبٍ في نطاقه
 *     ويضيف إليه؛ والعملاء من عقد «تابع لـ» التي يملكها. كلُّ ما يضيفه يظهر
 *     عند المندوب بـ«أضافه: الاسم (الدور)».
 *
 * رؤيةُ خطّ غيره بلا إضافة تكفيها `clients_itinerary_view` القائمة. والتأجيلُ
 * يتبع `clients_itinerary_edit` كنقل الزيارات وتعليمها فائتة في شاشة التخطيط.
 */
class DailyItineraryController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;

    public function __construct(private DailyItineraryService $daily)
    {
        $this->middleware('auth');

        $this->middleware('permission:clients_daily_itinerary_view|clients_daily_itinerary_add_for_others', ['only' => [
            'index', 'day',
        ]]);

        $this->middleware('permission:clients_daily_itinerary_add_client|clients_daily_itinerary_add_for_others', ['only' => [
            'clients', 'store',
        ]]);

        $this->middleware('permission:clients_itinerary_edit', ['only' => ['postpone']]);
    }

    public function index(Request $request)
    {
        if ($block = $this->sectionBlock('itinerary')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $employees = $this->canViewOthers() ? $this->daily->employeeOptions() : collect([auth()->user()->only(['id', 'name'])]);
        $requested = $request->integer('employee_id') ?: (int) auth()->id();
        $employeeId = $employees->contains('id', $requested) && $this->daily->canManage($requested) ? $requested : (int) auth()->id();

        return view('client::Itinerary.daily', [
            'employees' => $employees,
            'employeeId' => $employeeId,
            'date' => $this->requestedDate($request)->toDateString(),
            'nodes' => $this->daily->nodeOptions(),
        ]);
    }

    /** يومُ مندوبٍ بزياراته وإحصاءاته، ومعه ما يحقّ للمستخدم فعلُه عليه */
    public function day(Request $request): JsonResponse
    {
        $employeeId = $this->requestedEmployee($request);
        $this->authorizeTarget($employeeId, false);

        $date = $this->requestedDate($request);
        $isSelf = $employeeId === (int) auth()->id();
        $payload = $this->daily->day($employeeId, $date);

        return response()->json($payload + [
            'employee' => User::whereKey($employeeId)->value('name'),
            'is_self' => $isSelf,
            'can' => [
                'add' => ! $payload['is_past'] && $this->mayAdd($employeeId),
                'postpone' => ! $payload['is_past'] && hasPermission('clients_itinerary_edit'),
                'skip' => hasPermission('clients_itinerary_edit'),
                // مساحةُ الزيارة يفتحها صاحبُ الخطّ نفسُه اليوم — بصلاحيات راوتها
                'start' => $isSelf && $payload['is_today'] && collect(['clients_visits_track', 'clients_itinerary_add', 'clients_visit_workspace_access', 'clients_must_visit_before_view'])->contains(fn ($p) => hasPermission($p)),
            ],
        ]);
    }

    /** عملاءُ مُنتقي «إضافة عميل للمسار» */
    public function clients(Request $request): JsonResponse
    {
        $employeeId = $this->requestedEmployee($request);
        $this->authorizeTarget($employeeId, true);

        return response()->json($this->daily->pickerClients(
            $employeeId,
            $this->requestedDate($request),
            $request->integer('node_id') ?: null,
            trim((string) $request->input('q'))
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'date' => 'required|date|after_or_equal:today',
            'client_ids' => 'required|array|min:1|max:'.DailyItineraryService::PICKER_LIMIT,
            'client_ids.*' => 'integer',
        ], [
            'date.after_or_equal' => 'لا يُضاف عميلٌ إلى خطّ يومٍ مضى.',
            'client_ids.required' => 'اختر عميلاً واحداً على الأقل.',
        ]);

        $employeeId = (int) $data['employee_id'];
        $this->authorizeTarget($employeeId, true);

        if (! $this->daily->isTargetAllowed($employeeId)) {
            return response()->json([
                'success' => false,
                'message' => 'المندوب ليس من «أقسام الموظفين المسموح تعيينهم للعملاء» أو من الفرع المختار في أعلى الصفحة.',
            ], 422);
        }

        $targetName = (string) User::whereKey($employeeId)->value('name');

        try {
            $result = $this->daily->addClients($employeeId, Carbon::parse($data['date']), $data['client_ids'], $targetName);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'ClientItineraries', 'daily_add_clients');

            return response()->json(['success' => false, 'message' => 'تعذّرت الإضافة: '.$e->getMessage()], 500);
        }

        if ($result['locked']) {
            return response()->json([
                'success' => false,
                'message' => 'بدأ المندوب زيارات هذا اليوم وخطُّه مقفلٌ عن التعديل (إعدادات خطوط السير).',
            ], 422);
        }

        $skipped = array_filter([
            $result['already'] ? "{$result['already']} في المسار أصلاً" : null,
            $result['out_of_scope'] ? "{$result['out_of_scope']} خارج ما يتبعك" : null,
            $result['unassigned'] ? "{$result['unassigned']} غير مُسنَد للمندوب" : null,
            $result['full'] ? "{$result['full']} تجاوز سقف اليوم" : null,
        ]);

        $message = $result['added'] > 0
            ? 'أُضيف '.$result['added'].' عميل إلى خط سير '.($employeeId === (int) auth()->id() ? 'يومك' : "«{$targetName}»").'.'
            : 'لم يُضف أيُّ عميل.';

        if ($skipped !== []) {
            $message .= ' تُخطّي: '.implode('، ', $skipped).'.';
        }

        return response()->json(['success' => $result['added'] > 0, 'message' => $message] + $result);
    }

    public function postpone($visitId): JsonResponse
    {
        $visit = EmployeeClientVisit::findOrFail($visitId);
        $this->authorizeTarget((int) $visit->employee_id, false);

        if ($error = $this->daily->postpone($visit)) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        return response()->json(['success' => true, 'message' => 'أُجِّلت الزيارة إلى آخر خطّ الغد.']);
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** رؤيةُ خطّ غيره: من يضيف لغيره، أو من يرى خطوط السير أصلاً */
    private function canViewOthers(): bool
    {
        return hasPermission('clients_daily_itinerary_add_for_others') || hasPermission('clients_itinerary_view');
    }

    private function mayAdd(int $employeeId): bool
    {
        return $employeeId === (int) auth()->id()
            ? hasPermission('clients_daily_itinerary_add_client')
            : hasPermission('clients_daily_itinerary_add_for_others') && $this->daily->canManage($employeeId);
    }

    private function authorizeTarget(int $employeeId, bool $adding): void
    {
        if ($adding) {
            abort_unless($this->mayAdd($employeeId), 403, 'لا تملك إضافة عملاء إلى خط سير هذا المندوب.');

            return;
        }

        abort_unless(
            $employeeId === (int) auth()->id() || ($this->canViewOthers() && $this->daily->canManage($employeeId)),
            403,
            'لا يمكنك الوصول إلى خط سير هذا المندوب.'
        );
    }

    private function requestedEmployee(Request $request): int
    {
        return $request->integer('employee_id') ?: (int) auth()->id();
    }

    private function requestedDate(Request $request): Carbon
    {
        try {
            return $request->filled('date') ? Carbon::parse((string) $request->input('date'))->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }
}
