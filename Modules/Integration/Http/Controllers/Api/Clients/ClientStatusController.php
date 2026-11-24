<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientStageRequest;
use App\Models\ClientStatusRequest;
use App\Models\notifications;
use App\Models\Statuses;
use App\Models\Visit;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Services\Clients\ClientVisibility;

/**
 * «حالة العميل» (نشط/موقوف) و«وضعه» (مرحلة التسجيل) — نفس
 * `updateStatusClient()` و`updateStageClient()` في الموقع:
 *
 *   • من يملك الاعتماد (`clients_approve_status` / `clients_approve_stage`) يغيّر مباشرةً
 *     ويُسجَّل طلبٌ معتمَدٌ للأثر.
 *   • ومن لا يملكه يرفع طلباً إلى مُعتمِدٍ يختاره (`manager_id`) ويصله إشعار.
 *     الحالة تحتاج اعتماداً فقط إن اشترطته الإعدادات، والوضع يحتاجه دائماً.
 */
class ClientStatusController extends Controller
{
    use RespondsJson;

    private const LOG_MODULE = 'Clients';

    public function __construct(private readonly ClientVisibility $visibility) {}

    /** ما يحتاجه نموذجا التغيير: الخيارات، وهل يلزم اعتماد، ومن يعتمد. */
    public function options(Request $request, Client $client): JsonResponse
    {
        $this->guardAccess($request, $client);

        $statusNeedsApproval = ! hasPermission('clients_approve_status') && ClientSettingsHelper::clientStatusChangeRequiresApproval();
        $stageNeedsApproval = ! hasPermission('clients_approve_stage');

        return $this->ok([
            'current_status_id' => $client->status_id ? (int) $client->status_id : null,
            'current_stage_id' => $client->registration_stage_id ? (int) $client->registration_stage_id : null,
            'statuses' => Statuses::assignable()->ordered()->get(['id', 'name', 'color']),
            'stages' => Statuses::section('registration_stage')->ordered()->get(['id', 'name', 'color']),
            'status_needs_approval' => $statusNeedsApproval,
            'stage_needs_approval' => $stageNeedsApproval,
            'status_approvers' => $statusNeedsApproval ? ClientStatusRequest::approvers() : [],
            'stage_approvers' => $stageNeedsApproval ? ClientStageRequest::approvers() : [],
        ]);
    }

    public function updateStatus(Request $request, Client $client): JsonResponse
    {
        $this->guardAccess($request, $client);

        $data = $request->validate([
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')->whereNull('section')->whereNotNull('kpi_role')],
            'notes' => 'nullable|string|max:1000',
            'manager_id' => 'nullable|integer',
            'visit_id' => 'nullable|integer',
        ], ['status_id.exists' => 'الحالة المختارة غير متاحة — اختر من قائمة حالات العميل.']);

        $user = $request->user();
        $oldId = $client->status_id && Statuses::whereKey($client->status_id)->exists() ? (int) $client->status_id : null;
        $newId = (int) $data['status_id'];
        $notes = $this->notesWithVisitTag($client, $data);
        $needsApproval = ! hasPermission('clients_approve_status') && ClientSettingsHelper::clientStatusChangeRequiresApproval();

        if ($needsApproval && ! ClientStatusRequest::isApprover((int) ($data['manager_id'] ?? 0))) {
            return $this->fail('اختر مسؤولاً يملك صلاحية اعتماد حالات العملاء (manager_id).', 422, [
                'errors' => ['manager_id' => ['اختر مسؤولاً يملك صلاحية اعتماد حالات العملاء.']],
            ]);
        }

        if (! $needsApproval && $oldId === $newId) {
            return $this->fail('العميل على هذه الحالة أصلاً.', 422);
        }

        $oldName = Statuses::whereKey($oldId)->value('name') ?? 'غير معروف';
        $newName = Statuses::whereKey($newId)->value('name') ?? 'غير معروف';

        DB::transaction(function () use ($client, $user, $oldId, $newId, $notes, $needsApproval, $data, $oldName, $newName) {
            if ($needsApproval) {
                ClientStatusRequest::create([
                    'client_id' => $client->id, 'requested_by' => $user->id, 'old_status_id' => $oldId,
                    'new_status_id' => $newId, 'status' => 'pending', 'notes' => $notes,
                ]);

                OperationLog::record('status_change_requested', self::LOG_MODULE, $client,
                    'طلب تغيير حالة العميل من «'.$oldName.'» إلى «'.$newName.'» بانتظار الاعتماد — من تطبيق الجوال'.($notes ? ' — السبب: '.$notes : ''),
                    ['old' => ['status_id' => $oldId], 'new' => ['status_id' => $newId]]);

                notifications::create([
                    'user_id' => $user->id, 'receiver_id' => (int) $data['manager_id'], 'client_id' => $client->id,
                    'title' => 'طلب اعتماد حالة عميل',
                    'description' => 'طلب '.$user->name.' تغيير حالة العميل '.$client->trade_name.' إلى «'.$newName.'»'.($notes ? ' — السبب: '.$notes : ''),
                    'type' => 'client_status_request', 'read' => false, 'popup_shown' => false, 'is_sent' => true,
                ]);

                return;
            }

            $client->status_id = $newId;
            $client->save();

            ClientStatusRequest::create([
                'client_id' => $client->id, 'requested_by' => $user->id, 'old_status_id' => $oldId,
                'new_status_id' => $newId, 'status' => 'approved', 'approved_by' => $user->id, 'notes' => $notes,
            ]);

            OperationLog::record('status_changed', self::LOG_MODULE, $client,
                'تغيير حالة العميل من «'.$oldName.'» إلى «'.$newName.'» — من تطبيق الجوال'.($notes ? ' — السبب: '.$notes : ''),
                ['old' => ['status_id' => $oldId], 'new' => ['status_id' => $newId]]);
        });

        return $this->ok(
            ['client_id' => $client->id, 'status_id' => $needsApproval ? $oldId : $newId, 'pending_approval' => $needsApproval],
            $needsApproval ? 'رُفع طلب تغيير الحالة للمسؤول وأُشعِر به.' : 'تم تغيير حالة العميل.'
        );
    }

    public function updateStage(Request $request, Client $client): JsonResponse
    {
        $this->guardAccess($request, $client);

        $data = $request->validate([
            'stage_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('section', 'registration_stage'))],
            'notes' => 'nullable|string|max:1000',
            'manager_id' => 'nullable|integer',
            'visit_id' => 'nullable|integer',
        ], ['stage_id.exists' => 'الوضع المختار غير موجود في قائمة أوضاع العملاء.']);

        $user = $request->user();
        $oldId = $client->registration_stage_id && Statuses::whereKey($client->registration_stage_id)->exists() ? (int) $client->registration_stage_id : null;
        $newId = isset($data['stage_id']) ? (int) $data['stage_id'] : null;
        $notes = $this->notesWithVisitTag($client, $data);
        $needsApproval = ! hasPermission('clients_approve_stage');

        if ($needsApproval && ! ClientStageRequest::isApprover((int) ($data['manager_id'] ?? 0))) {
            return $this->fail('اختر مسؤولاً يملك صلاحية اعتماد وضع العملاء (manager_id).', 422, [
                'errors' => ['manager_id' => ['اختر مسؤولاً يملك صلاحية اعتماد وضع العملاء.']],
            ]);
        }

        if (! $needsApproval && $oldId === $newId) {
            return $this->fail('العميل على هذا الوضع أصلاً.', 422);
        }

        $oldName = $oldId ? (Statuses::whereKey($oldId)->value('name') ?? 'بلا وضع') : 'بلا وضع';
        $newName = $newId ? (Statuses::whereKey($newId)->value('name') ?? 'بلا وضع') : 'بلا وضع';

        DB::transaction(function () use ($client, $user, $oldId, $newId, $notes, $needsApproval, $data, $oldName, $newName) {
            if ($needsApproval) {
                ClientStageRequest::create([
                    'client_id' => $client->id, 'requested_by' => $user->id, 'old_stage_id' => $oldId,
                    'new_stage_id' => $newId, 'status' => 'pending', 'notes' => $notes,
                ]);

                OperationLog::record('stage_change_requested', self::LOG_MODULE, $client,
                    'طلب تغيير وضع العميل من «'.$oldName.'» إلى «'.$newName.'» بانتظار الاعتماد — من تطبيق الجوال'.($notes ? ' — السبب: '.$notes : ''),
                    ['old' => ['registration_stage_id' => $oldId], 'new' => ['registration_stage_id' => $newId]]);

                notifications::create([
                    'user_id' => $user->id, 'receiver_id' => (int) $data['manager_id'], 'client_id' => $client->id,
                    'title' => 'طلب اعتماد وضع عميل',
                    'description' => 'طلب '.$user->name.' تغيير وضع العميل '.$client->trade_name.' إلى «'.$newName.'»'.($notes ? ' — السبب: '.$notes : ''),
                    'type' => 'client_stage_request', 'read' => false, 'popup_shown' => false, 'is_sent' => true,
                ]);

                return;
            }

            $client->registration_stage_id = $newId;
            $client->save();

            ClientStageRequest::create([
                'client_id' => $client->id, 'requested_by' => $user->id, 'old_stage_id' => $oldId,
                'new_stage_id' => $newId, 'status' => 'approved', 'approved_by' => $user->id, 'notes' => $notes,
            ]);

            OperationLog::record('stage_changed', self::LOG_MODULE, $client,
                'تغيير وضع العميل من «'.$oldName.'» إلى «'.$newName.'» — من تطبيق الجوال'.($notes ? ' — السبب: '.$notes : ''),
                ['old' => ['registration_stage_id' => $oldId], 'new' => ['registration_stage_id' => $newId]]);
        });

        return $this->ok(
            ['client_id' => $client->id, 'stage_id' => $needsApproval ? $oldId : $newId, 'pending_approval' => $needsApproval],
            $needsApproval ? 'رُفع طلب تغيير الوضع للمسؤول وأُشعِر به.' : 'تم تغيير وضع العميل.'
        );
    }

    private function guardAccess(Request $request, Client $client): void
    {
        abort_unless($this->visibility->canAccess($request->user(), $client->id), 403, 'لا يمكنك تغيير عميلٍ غير مسموح لك بالوصول إليه.');
    }

    /** الطلب القادم من زيارة يحمل رقمها في السبب — زيارة هذا العميل وحده. */
    private function notesWithVisitTag(Client $client, array $data): ?string
    {
        $tag = '';

        if (! empty($data['visit_id']) && ($visit = Visit::find($data['visit_id'])) && (int) $visit->client_id === (int) $client->id) {
            $tag = '[من زيارة '.($visit->visit_number ?: '#'.$visit->id).' بتاريخ '.optional($visit->visit_date)->format('Y-m-d').'] ';
        }

        return trim($tag.trim((string) ($data['notes'] ?? ''))) ?: null;
    }
}
