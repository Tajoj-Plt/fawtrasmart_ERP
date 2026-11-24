<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Http\Controllers\Controller;
use App\Models\EmployeeClientVisit;
use App\Models\notifications;
use App\Models\User;
use App\Models\VisitJustification;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;

/**
 * تبريرات الزيارات غير المكتملة — الجزء الذاتي من `IncompleteVisitsController`
 * في الموقع: زيارات الأمس وما قبله التي لم تُنجَز تحتاج تبريراً نصّياً
 * يراجعه المدير. من يملك `staff_visit_justification_exempt` معفى.
 *
 * في الموقع يُحجب الموظف عن الشاشات حتى يبرّر؛ واجهة الجوال لا تُحجب (كما
 * يستثنيها الموقع نفسه) — والتطبيق يقرأ `pending_count` ليطلب التبرير.
 */
class JustificationController extends Controller
{
    use RespondsJson;

    private const LOG_MODULE = 'VisitJustification';

    /** الزيارات التي تنتظر تبريراً مني، وعدد ما قُدّم وينتظر الاعتماد. */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        if (hasPermission('staff_visit_justification_exempt')) {
            return $this->ok(['exempt' => true, 'pending_count' => 0, 'awaiting_approval' => 0, 'visits' => []]);
        }

        $visits = EmployeeClientVisit::needingJustificationQuery($user->id)
            ->with('client:id,trade_name,code')
            ->orderByDesc('visit_date')->orderByDesc('id')
            ->limit(200)->get();

        return $this->ok([
            'exempt' => false,
            'pending_count' => $visits->count(),
            'awaiting_approval' => EmployeeClientVisit::pendingApprovalQuery($user->id)->count(),
            'visits' => $visits->map(fn ($v) => [
                'id' => (int) $v->id,
                'visit_date' => $v->visit_date?->toDateString(),
                'day_of_week' => $v->day_of_week,
                'client' => $v->client ? ['id' => (int) $v->client->id, 'trade_name' => $v->client->trade_name, 'code' => $v->client->code] : null,
            ])->values(),
        ]);
    }

    /** تبريرٌ لكل زيارة: `justifications[{visit_id}] = "النص"`. */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (hasPermission('staff_visit_justification_exempt')) {
            return $this->fail('أنت معفى من تقديم تبريرات الزيارات.', 422);
        }

        $data = $request->validate([
            'justifications' => 'required|array|min:1|max:200',
            'justifications.*' => 'required|string|min:5|max:1000',
        ], [
            'justifications.required' => 'أرسل تبريراً لزيارة واحدة على الأقل.',
            'justifications.*.required' => 'التبرير مطلوب لكل زيارة.',
            'justifications.*.min' => 'التبرير 5 أحرف على الأقل.',
            'justifications.*.max' => 'التبرير لا يتجاوز 1000 حرف.',
        ]);

        // لا يُبرَّر إلا ما ينتظر تبريراً مني فعلاً — لا زيارة غيري ولا زيارةٌ أُنجزت
        $eligible = EmployeeClientVisit::needingJustificationQuery($user->id)
            ->whereIn('id', array_map('intval', array_keys($data['justifications'])))
            ->with('client:id,trade_name')->get()->keyBy('id');

        $done = [];
        $rejected = [];

        DB::transaction(function () use ($data, $eligible, &$done, &$rejected) {
            foreach ($data['justifications'] as $visitId => $text) {
                $visit = $eligible->get((int) $visitId);

                if (! $visit) {
                    $rejected[] = (int) $visitId;

                    continue;
                }

                $visit->update(['justification' => trim($text), 'justification_date' => now()]);
                $done[] = (int) $visitId;

                OperationLog::record('visit_justification_submitted', self::LOG_MODULE, $visit,
                    'تقديم تبرير لزيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'» غير المكتملة — من تطبيق الجوال',
                    ['old' => ['justification' => null], 'new' => ['justification' => trim($text)]]);
            }
        });

        if ($done === []) {
            return $this->fail('لم يُقبل أيّ تبرير — الزيارات المرسلة ليست ممّا ينتظر تبريرك.', 422, ['data' => ['rejected' => $rejected]]);
        }

        $this->notifyManagers($user, count($done));

        return $this->ok(['submitted' => $done, 'rejected' => $rejected],
            'قُدّم '.count($done).' تبرير — ستراجعه الإدارة.'.($rejected ? ' تُخطّي '.count($rejected).' لا ينتظر تبريراً.' : ''));
    }

    /** تبريراتي المقدَّمة بحالتها، وفترات الإعفاء الممنوحة لي. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $page = EmployeeClientVisit::with(['client:id,trade_name,code', 'approvedBy:id,name'])
            ->where('employee_id', $user->id)
            ->whereNotNull('justification')->where('justification', '!=', '')
            ->orderByDesc('justification_date')
            ->paginate($this->perPage(15));

        $periods = VisitJustification::with('client:id,trade_name')->forEmployee($user->id)
            ->orderByDesc('date_from')->limit(50)->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'client' => $p->client?->trade_name,
                'date_from' => $p->date_from ? Carbon::parse($p->date_from)->toDateString() : null,
                'date_to' => $p->date_to ? Carbon::parse($p->date_to)->toDateString() : null,
                'status' => $p->status,
                'reason' => $p->reason ?? null,
            ])->values();

        return $this->paginated($page, $page->getCollection()->map(fn ($v) => $this->present($v))->values()->all(), ['periods' => $periods]);
    }

    public function show(Request $request, int $visit): JsonResponse
    {
        $row = EmployeeClientVisit::with(['client:id,trade_name,code', 'approvedBy:id,name'])
            ->where('employee_id', $request->user()->id)->findOrFail($visit);

        return $this->ok($this->present($row));
    }

    /** تعديل تبريرٍ لم يُعتمد بعد — المعتمَد لا يُعدَّل. */
    public function update(Request $request, int $visit): JsonResponse
    {
        if (hasPermission('staff_visit_justification_exempt')) {
            return $this->fail('أنت معفى من تقديم تبريرات الزيارات.', 422);
        }

        $data = $request->validate(['justification' => 'required|string|min:5|max:1000'], [
            'justification.required' => 'التبرير مطلوب.',
            'justification.min' => 'التبرير 5 أحرف على الأقل.',
        ]);

        $row = EmployeeClientVisit::where('employee_id', $request->user()->id)->findOrFail($visit);

        if (empty($row->justification)) {
            return $this->fail('لا تبرير مقدَّماً لهذه الزيارة بعد — قدّمه أوّلاً.', 422);
        }

        if ((int) $row->justification_approved === 1) {
            return $this->fail('اعتُمد هذا التبرير — لا يُعدَّل بعد الاعتماد.', 422);
        }

        $before = $row->justification;
        // تبريرٌ مرفوض يُعاد تقديمه فيعود «بانتظار الاعتماد»
        $row->update([
            'justification' => trim($data['justification']),
            'justification_date' => now(),
            'justification_approved' => (int) $row->justification_approved === 2 ? null : $row->justification_approved,
        ]);

        OperationLog::record('visit_justification_updated', self::LOG_MODULE, $row,
            'تعديل تبرير زيارة العميل #'.$row->client_id.' — من تطبيق الجوال',
            ['old' => ['justification' => $before], 'new' => ['justification' => $row->justification]]);

        return $this->ok($this->present($row->fresh(['client:id,trade_name,code', 'approvedBy:id,name'])), 'تم تحديث التبرير.');
    }

    private function present(EmployeeClientVisit $v): array
    {
        $approved = $v->justification_approved;

        return [
            'id' => (int) $v->id,
            'visit_date' => $v->visit_date?->toDateString(),
            'client' => $v->client ? ['id' => (int) $v->client->id, 'trade_name' => $v->client->trade_name, 'code' => $v->client->code] : null,
            'justification' => $v->justification,
            'justification_date' => $v->justification_date?->toDateTimeString(),
            'status' => match (true) {
                (int) $approved === 1 => 'approved',
                $approved !== null && (int) $approved === 2 => 'rejected',
                default => 'pending',
            },
            'approved_by' => $v->approvedBy?->name,
        ];
    }

    /** إشعار المدراء بتبريراتٍ جديدة — نفس مستقبِلي الموقع (admin/manager). */
    private function notifyManagers(User $user, int $count): void
    {
        try {
            User::whereIn('role', ['admin', 'manager'])->pluck('id')->each(fn ($managerId) => notifications::create([
                'user_id' => $user->id,
                'receiver_id' => $managerId,
                'type' => 'visit',
                'title' => 'تبرير زيارة جديد',
                'description' => 'قدّم الموظف '.$user->name.' '.$count.' تبرير(ات) لزيارات غير مكتملة.',
                'read' => false,
            ]));
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'notify_managers');
        }
    }
}
