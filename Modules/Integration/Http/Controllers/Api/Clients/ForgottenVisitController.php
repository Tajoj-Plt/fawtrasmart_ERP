<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Http\Controllers\Controller;
use App\Models\EmployeeClientVisit;
use App\Models\notifications;
use App\Models\Visit;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Services\TeamScope;

/**
 * الزيارات المنسيّة — بدأها المندوب ولم يُنهِها (مضى على بدئها أكثر من
 * أربع ساعات، أو فُتحت في يومٍ سابق ولم تبدأ). نفس `VisitController` في الموقع:
 *
 *   • العرض: زياراته (`_own`)، أو زيارات من يراهم (`_all`، بنطاقه: هو وفريقه).
 *   • الإنهاء عن بُعد: زيارته بـ`clients_close_forgotten_visits_own`، وزيارة
 *     غيره بـ`clients_force_close_forgotten_visits` — ويُشعَر صاحبها.
 *   • الإلغاء بسبب: `clients_cancel_forgotten_visits` — زيارته هو، أو زيارة
 *     غيره لمدير (admin/manager).
 */
class ForgottenVisitController extends Controller
{
    use RespondsJson;

    public const FORGOTTEN_AFTER_HOURS = 4;

    private const LOG_MODULE = 'ClientVisits';

    public function index(Request $request): JsonResponse
    {
        $request->validate(['scope' => 'nullable|in:own,all']);
        $user = $request->user();

        $scope = $request->input('scope', 'own') === 'all' && hasPermission('clients_view_forgotten_visits_all') ? 'all' : 'own';
        $team = $scope === 'all' ? TeamScope::userIds($user) : [(int) $user->id];

        $visits = $this->forgottenQuery()
            ->when($team !== null, fn ($q) => $q->whereIn('employee_id', $team))
            ->with(['employee:id,name', 'client:id,trade_name,code'])
            ->orderBy('started_at')
            ->limit(200)
            ->get();

        return $this->ok([
            'scope' => $scope,
            'count' => $visits->count(),
            'visits' => $visits->map(fn (Visit $v) => [
                'id' => (int) $v->id,
                'visit_number' => $v->visit_number,
                'visit_date' => $v->visit_date?->toDateString(),
                'started_at' => $v->started_at?->toDateTimeString(),
                'stage' => $v->stage,
                'employee' => $v->employee ? ['id' => (int) $v->employee->id, 'name' => $v->employee->name] : null,
                'client' => $v->client ? ['id' => (int) $v->client->id, 'trade_name' => $v->client->trade_name, 'code' => $v->client->code] : null,
                'is_own' => (int) $v->employee_id === (int) $user->id,
            ])->values(),
        ]);
    }

    public function close(Request $request, int $visit): JsonResponse
    {
        $user = $request->user();
        $row = $this->forgottenQuery()->with(['employee:id,name', 'client:id,trade_name'])->findOrFail($visit);
        $own = (int) $row->employee_id === (int) $user->id;

        if ($own && ! hasPermission('clients_close_forgotten_visits_own')) {
            return $this->fail('غير مصرّح لك بإنهاء زياراتك المنسيّة.', 403);
        }

        if (! $own && (! hasPermission('clients_force_close_forgotten_visits') || ! TeamScope::covers($user, (int) $row->employee_id))) {
            return $this->fail('غير مصرّح لك بإنهاء زيارات هذا الموظف.', 403);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:1000']);
        $before = $row->only(['stage', 'departure_time', 'outcome', 'outcome_notes']);

        $row->update([
            'stage' => Visit::STAGE_COMPLETED,
            'departure_time' => now(),
            'outcome' => 'successful',
            'outcome_notes' => $data['notes'] ?? 'تم إنهاء الزيارة عن بُعد — الموظف لم يعد متاحاً',
        ]);

        // مدّةٌ معقولة وحدها تُكتب — زيارةٌ تُركت أيّاماً ليست زيارةً دامت أيّاماً
        $minutes = (int) round($row->fresh()->elapsed_seconds / 60);
        EmployeeClientVisit::markCompletedFor((int) $row->employee_id, (int) $row->client_id, $row->visit_date,
            $minutes <= self::FORGOTTEN_AFTER_HOURS * 60 ? $minutes : null);

        OperationLog::record('closed_forgotten', self::LOG_MODULE, $row,
            'إنهاء زيارة منسيّة: '.($row->client->trade_name ?? '#'.$row->client_id).' (الموظف: '.($row->employee->name ?? '').') — من تطبيق الجوال',
            ['action' => $own ? 'closed_by_self' : 'closed_by_manager', 'closer_id' => $user->id, 'old' => $before]);

        if (! $own) {
            notifications::create([
                'user_id' => $user->id,
                'receiver_id' => $row->employee_id,
                'client_id' => $row->client_id,
                'type' => 'forgotten_visit_closed',
                'title' => 'تم إنهاء زيارة منسيّة',
                'description' => 'أنهى '.$user->name.' الزيارة المنسيّة عند العميل '.($row->client->trade_name ?? '').' (بتاريخ '.$row->visit_date?->format('Y-m-d').')',
                'read' => false,
            ]);
        }

        return $this->ok(['visit_id' => (int) $row->id], 'أُنهيت الزيارة المنسيّة.');
    }

    public function cancel(Request $request, int $visit): JsonResponse
    {
        $user = $request->user();
        $row = $this->forgottenQuery()->with('client:id,trade_name')->findOrFail($visit);
        $own = (int) $row->employee_id === (int) $user->id;

        if (! $own && (! $user->hasRole(['admin', 'manager']) || ! TeamScope::covers($user, (int) $row->employee_id))) {
            return $this->fail('لا يمكنك إلغاء زيارة موظفٍ آخر.', 403);
        }

        $data = $request->validate(['cancel_reason' => 'required|string|max:500'], ['cancel_reason.required' => 'اكتب سبب الإلغاء.']);
        $before = $row->only(['stage', 'cancel_reason']);

        $row->update([
            'stage' => Visit::STAGE_CANCELLED,
            'cancel_reason' => $data['cancel_reason'],
            'departure_time' => now(),
        ]);

        OperationLog::record('cancelled_forgotten', self::LOG_MODULE, $row,
            'إلغاء زيارة منسيّة: '.($row->client->trade_name ?? '#'.$row->client_id).' — من تطبيق الجوال',
            ['reason' => $data['cancel_reason'], 'cancelled_by' => $user->name, 'old' => $before]);

        return $this->ok(['visit_id' => (int) $row->id], 'أُلغيت الزيارة المنسيّة.');
    }

    /** نفس تعريف «المنسيّة» في لوحة زيارات الموقع حرفاً بحرف. */
    private function forgottenQuery()
    {
        $threshold = now()->subHours(self::FORGOTTEN_AFTER_HOURS);

        return Visit::openWorkspaceVisit()->where(fn ($q) => $q
            ->where(fn ($s) => $s->whereNotNull('started_at')->where('started_at', '<', $threshold))
            ->orWhere(fn ($n) => $n->whereNull('started_at')->whereDate('visit_date', '<', now()->toDateString())));
    }
}
