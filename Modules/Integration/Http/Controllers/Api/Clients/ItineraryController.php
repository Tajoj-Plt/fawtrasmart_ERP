<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Enums\VisitStatus;
use App\Helpers\ClientSettingsHelper;
use App\Helpers\WeekHelper;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientModuleSetting;
use App\Models\EmployeeClientVisit;
use App\Models\User;
use App\Services\Itinerary\DailyItineraryService;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Services\TeamScope;

/**
 * «خط سيري اليومي» — نفس `DailyItineraryController` في الموقع وعبر نفس
 * الخدمة ({@see DailyItineraryService}): سقف اليوم، قفل الخطّ بعد بدء
 * الزيارات، وتقييد الإضافة على العملاء المُسندين كلّها من «إعدادات وحدات
 * العملاء ← خطوط السير» وتسري هنا كما تسري هناك.
 *
 * `employee_id` هنا رقم **المستخدم** (users.id) — كما في جدول خطوط السير.
 */
class ItineraryController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly DailyItineraryService $daily) {}

    /** يوم المندوب بزياراته وإحصاءاته، ومعه ما يحقّ للمستخدم فعلُه عليه. */
    public function day(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'nullable|integer|min:1', 'date' => 'nullable|date']);

        if ($block = $this->sectionBlock()) {
            return $block;
        }

        $employeeId = $request->integer('employee_id') ?: (int) $request->user()->id;
        $this->authorizeTarget($employeeId, false);

        $date = $this->date($request);
        $isSelf = $employeeId === (int) $request->user()->id;
        $payload = $this->daily->day($employeeId, $date);

        return $this->ok($payload + [
            'employee' => ['id' => $employeeId, 'name' => User::whereKey($employeeId)->value('name')],
            'is_self' => $isSelf,
            'can' => [
                'add' => ! $payload['is_past'] && $this->mayAdd($employeeId),
                'postpone' => ! $payload['is_past'] && hasPermission('clients_itinerary_edit'),
                'start' => $isSelf && $payload['is_today'] && hasPermission(['clients_visits_track', 'clients_itinerary_add', 'clients_visit_workspace_access', 'clients_must_visit_before_view']),
            ],
        ]);
    }

    /** المندوبون الذين يفتح المستخدم خطوطهم — لمن يضيف لغيره أو يرى خطوط السير. */
    public function employees(Request $request): JsonResponse
    {
        if ($block = $this->sectionBlock()) {
            return $block;
        }

        $user = $request->user();
        $employees = collect([$user->only(['id', 'name'])]);

        if ($this->canViewOthers()) {
            // مندوبو الموقع (نطاقه + أقسام موظفي العملاء + فرعه) ومعهم فريقه في التسلسل الإداري
            $scope = TeamScope::userIds($user);
            $team = $scope === null ? collect() : User::whereIn('id', $scope)->whereNotNull('employee_id')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => $u->only(['id', 'name']));
            $employees = $this->daily->employeeOptions()->merge($team)->unique('id');
        }

        return $this->ok($employees->values());
    }

    /** عملاء مُنتقي «إضافة عميل لخطّ السير» — `q` للبحث و`node_id` لعقدة «تابع لـ». */
    public function clients(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'nullable|integer|min:1', 'date' => 'nullable|date', 'node_id' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100']);

        if ($block = $this->sectionBlock()) {
            return $block;
        }

        $employeeId = $request->integer('employee_id') ?: (int) $request->user()->id;
        $this->authorizeTarget($employeeId, true);

        return $this->ok($this->daily->pickerClients(
            $employeeId,
            $this->date($request),
            $request->integer('node_id') ?: null,
            trim((string) $request->input('q'))
        ) + ['nodes' => $this->daily->nodeOptions()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'nullable|integer|exists:users,id',
            'date' => 'nullable|date|after_or_equal:today',
            'client_ids' => 'required|array|min:1|max:'.DailyItineraryService::PICKER_LIMIT,
            'client_ids.*' => 'integer|distinct',
        ], [
            'date.after_or_equal' => 'لا يُضاف عميلٌ إلى خطّ يومٍ مضى.',
            'client_ids.required' => 'اختر عميلاً واحداً على الأقل.',
        ]);

        if ($block = $this->sectionBlock()) {
            return $block;
        }

        $employeeId = (int) ($data['employee_id'] ?? $request->user()->id);
        $this->authorizeTarget($employeeId, true);

        if (! $this->daily->isTargetAllowed($employeeId)) {
            return $this->fail('المندوب ليس من «أقسام الموظفين المسموح تعيينهم للعملاء» أو من فرعك الحالي.', 422);
        }

        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : today();
        $targetName = (string) User::whereKey($employeeId)->value('name');

        try {
            $result = $this->daily->addClients($employeeId, $date, $data['client_ids'], $targetName);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'ClientItineraries', 'integration.daily_add_clients');

            return $this->fail('تعذّرت الإضافة — حاول مرّةً أخرى.', 500);
        }

        if ($result['locked']) {
            return $this->fail('بدأ المندوب زيارات هذا اليوم وخطُّه مقفلٌ عن التعديل (إعدادات خطوط السير).', 422);
        }

        $skipped = array_filter([
            $result['already'] ? "{$result['already']} في المسار أصلاً" : null,
            $result['out_of_scope'] ? "{$result['out_of_scope']} خارج ما يتبعك" : null,
            $result['unassigned'] ? "{$result['unassigned']} غير مُسنَد للمندوب" : null,
            $result['full'] ? "{$result['full']} تجاوز سقف اليوم" : null,
        ]);

        $message = $result['added'] > 0
            ? 'أُضيف '.$result['added'].' عميل إلى خطّ سير '.($employeeId === (int) $request->user()->id ? 'يومك' : "«{$targetName}»").'.'
            : 'لم يُضف أيّ عميل.';

        if ($skipped !== []) {
            $message .= ' تُخطّي: '.implode('، ', $skipped).'.';
        }

        return $result['added'] > 0
            ? $this->ok($result, $message)
            : $this->fail($message, 422, ['data' => $result]);
    }

    /** تأجيل زيارةٍ لم تبدأ إلى آخر خطّ الغد. */
    public function postpone(Request $request, int $row): JsonResponse
    {
        if ($block = $this->sectionBlock()) {
            return $block;
        }

        $visit = EmployeeClientVisit::findOrFail($row);
        $this->authorizeTarget((int) $visit->employee_id, false);

        if ($error = $this->daily->postpone($visit)) {
            return $this->fail($error, 422);
        }

        return $this->ok(['id' => $visit->id, 'visit_date' => $visit->fresh()->visit_date?->toDateString()], 'أُجِّلت الزيارة إلى آخر خطّ الغد.');
    }

    /**
     * «إرجاع لخطّ السير» — عميلٌ أنهى المندوب زيارته اليوم فأُخفي ويريد العودة
     * إليه. نفس `VisitWorkspaceController::returnToItinerary()`.
     */
    public function returnClient(Request $request, Client $client): JsonResponse
    {
        if (! ClientSettingsHelper::allowReturnFinishedClientSameDay()) {
            return $this->fail('إرجاع العميل لخطّ السير غير مفعَّل في الإعدادات.', 403);
        }

        $userId = (int) $request->user()->id;

        if (! EmployeeClientVisit::finishedTodayClientIdsFor($userId)->contains((int) $client->id)) {
            return $this->fail('هذا العميل ليس ممّن أنهيت زيارتهم اليوم.', 422);
        }

        self::createReturnRow($userId, (int) $client->id, null);

        OperationLog::record('visit_client_returned_to_itinerary', 'ClientVisitWorkspace', $client,
            'إرجاع العميل «'.$client->trade_name.'» لخطّ السير اليوم بعد إنهاء زيارته — من تطبيق الجوال');

        return $this->ok(['client_id' => $client->id], 'أُرجع «'.$client->trade_name.'» لخطّ سيرك اليوم — يمكنك زيارته من جديد.');
    }

    /** صفّ خطّ سيرٍ مفتوحٌ اليوم — يُظهر العميل للمندوب من جديد. */
    public static function createReturnRow(int $userId, int $clientId, ?string $plannedTime): void
    {
        $now = now();
        $week = WeekHelper::getWeekAndYear($now);

        EmployeeClientVisit::create([
            'employee_id' => $userId,
            'client_id' => $clientId,
            'day_of_week' => strtolower(WeekHelper::getCurrentDayNameEn($now)),
            'year' => $week['year'],
            'week_number' => $week['week'],
            'visit_date' => $now->copy(),
            'planned_time' => $plannedTime,
            'status' => 'unactive',
            'visit_status' => VisitStatus::PLANNED,
        ]);
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** قسم «خطوط السير» مطفأٌ من إعدادات وحدات العملاء ⇒ منعٌ صريح. */
    private function sectionBlock(): ?JsonResponse
    {
        try {
            $enabled = (bool) ClientModuleSetting::current()->itinerary_enabled;
        } catch (\Throwable) {
            $enabled = true;
        }

        return $enabled ? null : $this->fail('قسم «خطوط السير» معطَّل من إعدادات وحدات العملاء.', 403, ['code' => 'SECTION_DISABLED']);
    }

    private function canViewOthers(): bool
    {
        return hasPermission('clients_daily_itinerary_add_for_others') || hasPermission('clients_itinerary_view');
    }

    private function mayAdd(int $employeeId): bool
    {
        return $employeeId === (int) auth()->id()
            ? hasPermission('clients_daily_itinerary_add_client')
            : hasPermission('clients_daily_itinerary_add_for_others') && TeamScope::covers(auth()->user(), $employeeId);
    }

    private function authorizeTarget(int $employeeId, bool $adding): void
    {
        if ($adding) {
            abort_unless($this->mayAdd($employeeId), 403, 'لا تملك إضافة عملاء إلى خطّ سير هذا المندوب.');

            return;
        }

        abort_unless(
            $employeeId === (int) auth()->id() || ($this->canViewOthers() && TeamScope::covers(auth()->user(), $employeeId)),
            403,
            'لا يمكنك الوصول إلى خطّ سير هذا المندوب.'
        );
    }

    private function date(Request $request): Carbon
    {
        return $request->filled('date') ? Carbon::parse((string) $request->input('date'))->startOfDay() : today();
    }
}
