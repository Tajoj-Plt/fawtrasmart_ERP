<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Models\ClientRelation;
use App\Models\ClientStageRequest;
use App\Models\ClientStatusRequest;
use App\Models\Competitor;
use App\Models\CreditNotification;
use App\Models\EmployeeClientVisit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\notifications;
use App\Models\PaymentsProcess;
use App\Models\ProductExchange;
use App\Models\Receipt;
use App\Models\Visit;
use App\Models\VisitCompetitorObservation;
use App\Services\PointsService;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Http\Resources\Clients\ClientResource;
use Modules\Integration\Services\Clients\ClientVisibility;
use Modules\Integration\Services\Clients\ContactService;
use Modules\Integration\Services\Clients\MediaStore;
use Modules\Integration\Services\Clients\NoteService;
use Modules\Integration\Services\Clients\VisitRules;

/**
 * مساحة عمل الزيارة — نسخةٌ من `VisitWorkspaceController` في الموقع بسلسلة
 * خطواتها الإلزاميّة كاملةً:
 *
 *   فتح ← بدء (من موقع العميل) ← مسؤول العميل ← دوامه ← ملاحظة الزيارة
 *   ← رصد المنافسين ← (المستندات) ← إنهاء، أو إلغاء بسبب.
 *
 * كل خطوةٍ تُكتب بعد «البدء» فقط، ولا تُكتب في زيارةٍ مغلقة. والإنهاء لا
 * يقبل نتيجةً يدوية: فاتورةٌ أو تحصيلٌ بعد البدء ⇐ ناجحة، وإلا ⇐ سلبيّة.
 */
class VisitController extends Controller
{
    use RespondsJson;

    private const DOCS_LIMIT = 20;

    public function __construct(private readonly ClientVisibility $visibility) {}

    /** يجد زيارة اليوم المفتوحة لهذا العميل أو يُنشئها — ويعيدها كاملة. */
    public function open(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->visibility->canAccess($user, $client->id), 403, 'لا يمكنك فتح زيارة لعميلٍ غير مسموح لك بالوصول إليه.');

        $visit = Visit::where('employee_id', $user->id)->where('client_id', $client->id)
            ->whereDate('visit_date', now()->toDateString())->whereNull('departure_time')->first();

        $created = false;
        if (! $visit) {
            $location = $client->locations;
            $visit = Visit::create([
                'employee_id' => $user->id,
                'client_id' => $client->id,
                'visit_date' => now(),
                'visit_number' => next_document_number('visit'),
                'client_latitude' => $location?->latitude,
                'client_longitude' => $location?->longitude,
                'recording_method' => 'manual',
                'is_approved' => false,
                'stage' => Visit::STAGE_ARRIVED,
            ]);
            $created = true;

            OperationLog::record('visit_registered', VisitRules::LOG_MODULE, $visit, 'فتح زيارة للعميل «'.$client->trade_name.'» — من تطبيق الجوال');
        } elseif (! $visit->isWorkspaceManaged()) {
            // زيارةٌ سجّلها التتبّع الآلي تُتبنّى فلا يغلقها الانصراف التلقائي
            $visit->update(['recording_method' => 'manual']);
        }

        return $this->ok($this->payload($visit->fresh(), $user), $created ? 'فُتحت زيارة جديدة — اضغط «بدء الزيارة» من موقع العميل.' : null, $created ? 201 : 200);
    }

    public function show(Request $request, Visit $visit): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        return $this->ok($this->payload($visit, $request->user()));
    }

    public function start(Request $request, Visit $visit): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        if ($closed = VisitRules::closedMessage($visit)) {
            return $this->fail($closed, 422);
        }

        if ($visit->started_at) {
            return $this->ok($this->timer($visit), 'الزيارة بدأت من قبل.');
        }

        $data = $request->validate($this->locationRules($visit), $this->locationMessages('لبدء الزيارة'));

        if ($blocked = VisitRules::outOfRangeMessage($visit, $data['current_latitude'] ?? 0, $data['current_longitude'] ?? 0, 'لبدء الزيارة')) {
            return $this->fail($blocked, 422, ['code' => 'OUT_OF_RANGE']);
        }

        // الموظفُ الميداني يبدأ الزيارة داخل دوامه فقط — نفسُ قيد شاشة الويب
        if ($blocked = app(\App\Services\Attendance\FieldWorkGuard::class)->visitStartBlock((int) $visit->employee_id)) {
            return $this->fail($blocked, 422, ['code' => 'OUTSIDE_DUTY']);
        }

        $visit->update([
            'started_at' => now(),
            'arrival_time' => now(),
            'stage' => Visit::STAGE_IN_PROGRESS,
            'employee_latitude' => $data['current_latitude'] ?? null,
            'employee_longitude' => $data['current_longitude'] ?? null,
        ]);

        EmployeeClientVisit::markStartedFor((int) $visit->employee_id, (int) $visit->client_id, $visit->visit_date);
        OperationLog::record('visit_started', VisitRules::LOG_MODULE, $visit, 'بدء زيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'» — من تطبيق الجوال');
        VisitRules::notifyArrivalOrDeparture($visit, 'arrival');

        return $this->ok($this->timer($visit->fresh()), 'بدأت الزيارة.');
    }

    /** إيقاف/استئناف المؤقّت — يستدعيه التطبيق حين يخرج المندوب من نطاق العميل ويعود. */
    public function togglePause(Request $request, Visit $visit): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        if ($blocked = $this->writeBlock($visit)) {
            return $blocked;
        }

        if ($visit->isPaused()) {
            $visit->update([
                'total_paused_seconds' => $visit->total_paused_seconds + $visit->paused_at->diffInSeconds(now(), true),
                'paused_at' => null,
                'stage' => Visit::STAGE_IN_PROGRESS,
            ]);
        } else {
            $visit->update(['paused_at' => now(), 'stage' => Visit::STAGE_PAUSED]);
        }

        OperationLog::record($visit->isPaused() ? 'visit_paused' : 'visit_resumed', VisitRules::LOG_MODULE, $visit,
            ($visit->isPaused() ? 'إيقاف مؤقّت لزيارة العميل #' : 'استئناف زيارة العميل #').$visit->client_id.' — من تطبيق الجوال');

        return $this->ok($this->timer($visit->fresh()), $visit->isPaused() ? 'أُوقف مؤقّت الزيارة.' : 'استُؤنفت الزيارة.');
    }

    /** الخطوة الأولى — إضافة مسؤول العميل (بدوامه). */
    public function storeContact(Request $request, Visit $visit): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        if ($blocked = $this->writeBlock($visit)) {
            return $blocked;
        }

        $contact = ContactService::create($request, (int) $visit->client_id, $request->user(), VisitRules::LOG_MODULE);

        return $this->ok(ContactService::present($contact) + ['steps' => VisitRules::steps($visit, (int) $request->user()->id)], 'تمت إضافة مسؤول العميل.', 201);
    }

    /** الخطوة الثانية — دوام مسؤولٍ مسجَّل؛ تغييره بعد أوّل تسجيل يحتاج صلاحية. */
    public function storeContactShift(Request $request, Visit $visit): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        if ($blocked = $this->writeBlock($visit)) {
            return $blocked;
        }

        $data = $request->validate([
            'client_employee_id' => ['required', 'integer', Rule::exists('client_employee_notes', 'id')->where('client_id', $visit->client_id)],
            'shift' => ContactService::shiftRule(),
        ], [
            'client_employee_id.required' => 'اختر المسؤول الذي تسجّل دوامه.',
            'client_employee_id.exists' => 'هذا المسؤول لا يتبع هذا العميل.',
        ]);

        $contact = ClientEmployeeNote::findOrFail($data['client_employee_id']);

        if ($contact->hasShift() && ! hasPermission('clients_change_contact_shift')) {
            return $this->fail('دوام هذا المسؤول مسجَّل من قبل، وتغييره يحتاج صلاحية «تغيير دوام مسؤول العميل بعد تسجيله».', 403);
        }

        [$start, $end] = ClientEmployeeNote::parseShiftRange($data['shift']);
        $wasSet = $contact->hasShift();
        $contact->update(['shift_start' => $start, 'shift_end' => $end]);

        OperationLog::record($wasSet ? 'visit_contact_shift_changed' : 'visit_contact_shift_set', VisitRules::LOG_MODULE, $visit,
            ($wasSet ? 'تغيير دوام مسؤول العميل «' : 'تسجيل دوام مسؤول العميل «').$contact->employee_name.'» ('.$start.' — '.$end.') — من تطبيق الجوال');

        return $this->ok(ContactService::present($contact->fresh()) + ['steps' => VisitRules::steps($visit, (int) $request->user()->id)],
            $wasSet ? 'تم تغيير دوام المسؤول.' : 'تم تسجيل دوام المسؤول — أكمل الزيارة.');
    }

    /**
     * الخطوة الثالثة — ملاحظة الزيارة، مع دوام المسؤول (يُسجَّل أوّل مرّة، أو
     * يُعدَّل بصلاحية). الدوام يُكتب على المسؤول بعد نجاح الملاحظة لا قبله.
     */
    public function storeNote(Request $request, Visit $visit): JsonResponse
    {
        $user = $request->user();
        VisitRules::authorizeOwner($visit, $user);

        if ($blocked = $this->writeBlock($visit)) {
            return $blocked;
        }

        $request->validate([
            'client_employee_id' => ['required', 'integer', Rule::exists('client_employee_notes', 'id')->where('client_id', $visit->client_id)],
        ], [
            'client_employee_id.required' => 'اختر مسؤول العميل الذي قابلته.',
            'client_employee_id.exists' => 'المسؤول المختار لا يتبع هذا العميل.',
        ]);

        $contact = ClientEmployeeNote::findOrFail($request->integer('client_employee_id'));
        $submitted = trim((string) $request->input('shift', ''));
        $newShift = null;

        if ($submitted !== '' && $submitted !== $contact->shift_range_value) {
            $newShift = ClientEmployeeNote::parseShiftRange($submitted);

            if ($newShift === null) {
                return $this->fail('فترة الدوام غير صالحة — أرسل full أو من|إلى بصيغة HH:00|HH:00.', 422, ['errors' => ['shift' => ['فترة الدوام غير صالحة.']]]);
            }

            if ($contact->hasShift() && ! hasPermission('clients_change_contact_shift')) {
                return $this->fail('دوام «'.$contact->employee_name.'» مسجَّل من قبل، وتغييره يحتاج صلاحية «تغيير دوام مسؤول العميل بعد تسجيله».', 403);
            }
        } elseif (! $contact->hasShift()) {
            return $this->fail('اختر دوام المسؤول «'.$contact->employee_name.'» — يُسجَّل مع هذه الملاحظة.', 422, ['errors' => ['shift' => ['دوام المسؤول مطلوب.']]]);
        }

        [$start, $end] = $newShift ?? [$contact->shift_start, $contact->shift_end];

        $note = NoteService::create($request, $visit->client, $user, [
            'shift_start' => $start,
            'shift_end' => $end,
            'competitor_documents' => 0,
        ]);

        if ($newShift) {
            $wasSet = $contact->hasShift();
            $contact->update(['shift_start' => $start, 'shift_end' => $end]);
            OperationLog::record($wasSet ? 'visit_contact_shift_changed' : 'visit_contact_shift_set', VisitRules::LOG_MODULE, $visit,
                ($wasSet ? 'تغيير دوام مسؤول العميل «' : 'تسجيل دوام مسؤول العميل «').$contact->employee_name.'» ('.$start.' — '.$end.') مع ملاحظة الزيارة — من تطبيق الجوال');
        }

        return $this->ok([
            'note_id' => (int) $note->id,
            'steps' => VisitRules::steps($visit, (int) $user->id),
        ], 'سُجّلت ملاحظة الزيارة.', 201);
    }

    /**
     * الخطوة الرابعة — رصد المنافسين: منافسٌ + عدد قطع عهدته + صورته لكل صف،
     * أو «لا يوجد منافس» بصورة إثبات.
     */
    public function storeCompetitors(Request $request, Visit $visit): JsonResponse
    {
        $user = $request->user();
        VisitRules::authorizeOwner($visit, $user);

        if ($blocked = $this->writeBlock($visit)) {
            return $blocked;
        }

        if (! VisitRules::competitorCaptureEnabled()) {
            return $this->fail('رصد المنافسين غير مفعَّل في إعدادات الزيارات — تجاوز هذه الخطوة.', 422, ['code' => 'STEP_DISABLED']);
        }

        if (! VisitRules::steps($visit, (int) $user->id)['has_note']) {
            return $this->fail('سجّل ملاحظة الزيارة أولاً — رصد المنافسين يأتي بعدها.', 422, ['code' => 'NOTE_REQUIRED']);
        }

        $maxKb = ClientSettingsHelper::maxUploadSizeKb();
        $none = $request->boolean('no_competitors');

        $request->validate($none ? [
            'no_competitors_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ] : [
            'competitors' => ['required', 'array', 'min:1', 'max:30'],
            'competitors.*.competitor_id' => ['required', 'integer', 'distinct', Rule::exists('competitors', 'id')->where('is_active', true)],
            'competitors.*.asset_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'competitors.*.photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ], [
            'no_competitors_photo.required' => 'صورة إثبات البحث إلزامية عند اختيار «لا يوجد منافس».',
            'competitors.required' => 'اختر منافساً واحداً على الأقل وعدد قطع عهدته — أو أرسل no_competitors=1 مع صورة.',
            'competitors.*.competitor_id.required' => 'اختر المنافس في كل صفّ.',
            'competitors.*.competitor_id.exists' => 'أحد المنافسين المختارين غير موجودٍ أو معطَّل.',
            'competitors.*.competitor_id.distinct' => 'لا تختر المنافس نفسه مرّتين.',
            'competitors.*.asset_count.required' => 'اكتب عدد قطع عهدة كل منافس (0 إن لا عهدة له).',
            'competitors.*.photo.required' => 'صورة المنافس إلزامية لكل صف.',
        ]);

        $noteId = ClientRelation::where('client_id', $visit->client_id)->where('employee_id', $user->id)
            ->where('created_at', '>=', $visit->started_at)->latest('id')->value('id');

        $scope = fn () => VisitCompetitorObservation::where('visit_id', $visit->id)
            ->when($noteId, fn ($q) => $q->where('client_relation_id', $noteId), fn ($q) => $q->whereNull('client_relation_id')->where('employee_id', $user->id));

        $saved = 0;
        DB::transaction(function () use ($request, $visit, $user, $none, $noteId, $scope, &$saved) {
            if ($none) {
                if ($scope()->exists()) {
                    return;
                }

                VisitCompetitorObservation::create([
                    'visit_id' => $visit->id, 'client_relation_id' => $noteId, 'client_id' => $visit->client_id,
                    'employee_id' => $user->id, 'competitor_id' => null, 'competitor_name' => 'لا يوجد منافس', 'asset_count' => 0,
                    'photo_path' => MediaStore::storePhoto($request->file('no_competitors_photo'), MediaStore::COMPETITORS),
                ]);
                $saved = 1;

                return;
            }

            $rows = (array) $request->input('competitors', []);
            $names = Competitor::whereIn('id', array_column($rows, 'competitor_id'))->pluck('name', 'id');
            $already = $scope()->whereNotNull('competitor_id')->pluck('competitor_id')->map(fn ($id) => (int) $id)->all();

            foreach ($rows as $index => $row) {
                $competitorId = (int) ($row['competitor_id'] ?? 0);
                if (! $competitorId || in_array($competitorId, $already, true)) {
                    continue;
                }

                VisitCompetitorObservation::create([
                    'visit_id' => $visit->id, 'client_relation_id' => $noteId, 'client_id' => $visit->client_id,
                    'employee_id' => $user->id, 'competitor_id' => $competitorId,
                    'competitor_name' => $names[$competitorId] ?? '',
                    'asset_count' => (int) ($row['asset_count'] ?? 0),
                    'photo_path' => MediaStore::storePhoto($request->file("competitors.$index.photo"), MediaStore::COMPETITORS),
                ]);
                $saved++;
            }
        });

        if ($noteId) {
            ClientRelation::whereKey($noteId)->update([
                'competitor_documents' => VisitCompetitorObservation::where('client_relation_id', $noteId)->whereNotNull('competitor_id')->count(),
            ]);
        }

        OperationLog::record('visit_competitors_observed', VisitRules::LOG_MODULE, $visit,
            $none ? 'رصد المنافسين للعميل #'.$visit->client_id.': لا يوجد منافس — من تطبيق الجوال'
                : 'رصد '.$saved.' منافساً عند العميل #'.$visit->client_id.' — من تطبيق الجوال');

        return $this->ok(['saved' => $saved, 'steps' => VisitRules::steps($visit, (int) $user->id)],
            $saved ? 'تم رصد المنافسين — المستندات مفتوحة الآن.' : 'لا جديد — المنافسون مرصودون في هذه الزيارة من قبل.');
    }

    /** منافسٌ جديد يضيفه المندوب من داخل الزيارة — بصلاحية الملاحظات لا كتالوج المنافسين. */
    public function storeQuickCompetitor(Request $request, Visit $visit): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        if ($blocked = $this->writeBlock($visit)) {
            return $blocked;
        }

        $name = trim($request->validate(['name' => 'required|string|max:255'], ['name.required' => 'اكتب اسم المنافس.'])['name']);
        $existing = Competitor::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing) {
            return $existing->is_active
                ? $this->ok(['id' => (int) $existing->id, 'name' => $existing->name, 'existed' => true], 'المنافس «'.$existing->name.'» موجودٌ في القائمة — اخترناه لك.')
                : $this->fail('المنافس «'.$existing->name.'» معطَّل في القائمة — راجع مدير النظام لتفعيله.', 422);
        }

        $competitor = Competitor::create(['name' => $name, 'is_active' => true, 'notes' => 'أُضيف من زيارة العميل #'.$visit->client_id.' (تطبيق الجوال)']);
        OperationLog::record('competitor_added_from_visit', VisitRules::LOG_MODULE, $visit, 'إضافة منافس «'.$competitor->name.'» من مساحة عمل الزيارة — من تطبيق الجوال');

        return $this->ok(['id' => (int) $competitor->id, 'name' => $competitor->name, 'existed' => false], 'أُضيف «'.$competitor->name.'» إلى قائمة المنافسين.', 201);
    }

    /** «تم الاطّلاع» على إشعار الإدارة عن هذا العميل — صاحبه وحده. */
    public function readNotification(Request $request, Visit $visit, int $notification): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        $row = $this->ownedNotification($visit, $notification, (int) $request->user()->id);
        $row->update(['read' => 1]);

        OperationLog::record('client_notification_read', VisitRules::LOG_MODULE, $visit, 'اطّلاع على إشعار العميل «'.$row->title.'» — من تطبيق الجوال');

        return $this->ok(['id' => (int) $row->id, 'read' => true], 'عُلِّم الإشعار مقروءاً.');
    }

    public function replyNotification(Request $request, Visit $visit, int $notification): JsonResponse
    {
        $data = $request->validate(['reply_message' => 'required|string|max:500'], ['reply_message.required' => 'اكتب نصّ الردّ.']);

        $user = $request->user();
        VisitRules::authorizeOwner($visit, $user);
        $original = $this->ownedNotification($visit, $notification, (int) $user->id);

        DB::transaction(function () use ($original, $data, $user) {
            notifications::create(array_merge([
                'user_id' => $user->id,
                'receiver_id' => $original->user_id,
                'title' => 'رد عميل: '.$original->title,
                'description' => $data['reply_message'],
                'type' => 'reply',
                'client_id' => $original->client_id,
                'read' => 0,
                'is_sent' => true,
            ], notifications::supportsParentLink() ? ['parent_id' => $original->id] : []));

            $original->update(['read' => 1]);
        });

        OperationLog::record('client_notification_replied', VisitRules::LOG_MODULE, $visit, 'ردٌّ على إشعار العميل «'.$original->title.'» — من تطبيق الجوال');

        return $this->ok(['id' => (int) $original->id], 'أُرسل ردّك.');
    }

    /**
     * إنهاء الزيارة — بعد اكتمال خطواتها ومن موقع العميل. «سأعود» (`return_later`)
     * يكفيه المسؤول ودوامه والملاحظة، ويُعيد العميل لخطّ سير اليوم بموعد.
     */
    public function complete(Request $request, Visit $visit): JsonResponse
    {
        $user = $request->user();
        VisitRules::authorizeOwner($visit, $user);

        if ($blocked = $this->writeBlock($visit)) {
            return $blocked;
        }

        $signature = ClientSettingsHelper::visitWorkspaceSectionVisible('show_signature_section') ? 'required' : 'nullable';

        $data = $request->validate([
            'client_signature' => [$signature, 'string', 'max:3000000'],
            'employee_signature' => [$signature, 'string', 'max:3000000'],
            'outcome_notes' => 'nullable|string|max:2000',
            'return_later' => 'nullable|boolean',
            'return_time' => 'required_if:return_later,1,true|nullable|date_format:H:i',
        ] + $this->locationRules($visit), [
            'return_time.required_if' => 'حدّد وقت العودة المتوقَّع لهذا العميل (HH:MM).',
            'return_time.date_format' => 'اكتب وقت العودة بصيغة 24 ساعة مثل 18:30.',
            'client_signature.required' => 'توقيع العميل إلزامي (إعدادات الزيارات).',
            'employee_signature.required' => 'توقيعك إلزامي (إعدادات الزيارات).',
        ] + $this->locationMessages('لإنهاء الزيارة'));

        $returnLater = $request->boolean('return_later');
        $steps = VisitRules::steps($visit, (int) $user->id);

        if (! ($returnLater ? $steps['can_return'] : $steps['can_complete'])) {
            return $this->fail('لا يمكن إنهاء الزيارة قبل إتمام خطواتها: '.VisitRules::missingStepMessage($steps, $returnLater), 422, [
                'code' => 'STEPS_INCOMPLETE', 'data' => ['steps' => $steps],
            ]);
        }

        if ($blocked = VisitRules::outOfRangeMessage($visit, $data['current_latitude'] ?? 0, $data['current_longitude'] ?? 0, 'لإنهاء الزيارة')) {
            return $this->fail($blocked, 422, ['code' => 'OUT_OF_RANGE']);
        }

        $clientSig = MediaStore::storeSignature($data['client_signature'] ?? null, 'client');
        $employeeSig = MediaStore::storeSignature($data['employee_signature'] ?? null, 'employee');

        if ($signature === 'required' && (! $clientSig || ! $employeeSig)) {
            return $this->fail('التوقيع يجب أن يكون صورةً (data:image/png;base64,...).', 422);
        }

        $visit->update([
            'departure_time' => now(),
            'stage' => Visit::STAGE_COMPLETED,
            'outcome' => $steps['outcome'],
            'outcome_notes' => $data['outcome_notes'] ?? $visit->outcome_notes,
            'client_signature_path' => $clientSig,
            'employee_signature_path' => $employeeSig,
        ]);

        EmployeeClientVisit::markCompletedFor((int) $visit->employee_id, (int) $visit->client_id, $visit->visit_date, (int) round($visit->fresh()->elapsed_seconds / 60));

        try {
            app(PointsService::class)->evaluateItineraryCompletion((int) $visit->employee_id, now()->toDateString());
        } catch (\Throwable $e) {
            OperationLog::failure($e, VisitRules::LOG_MODULE, 'points_on_complete', $visit);
        }

        if ($returnLater) {
            try {
                ItineraryController::createReturnRow((int) $visit->employee_id, (int) $visit->client_id, $data['return_time'] ?? null);
                OperationLog::record('visit_return_scheduled', VisitRules::LOG_MODULE, $visit,
                    'جدولة عودة إلى العميل #'.$visit->client_id.' الساعة '.($data['return_time'] ?? '').' — من تطبيق الجوال');
            } catch (\Throwable $e) {
                OperationLog::failure($e, VisitRules::LOG_MODULE, 'schedule_return_visit', $visit);
            }
        }

        OperationLog::record('visit_completed', VisitRules::LOG_MODULE, $visit,
            'إنهاء زيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'» — '
            .($steps['outcome'] === 'success' ? 'ناجحة (فاتورة أو تحصيل)' : 'سلبيّة (بلا فاتورة ولا تحصيل)').' — من تطبيق الجوال');
        VisitRules::notifyArrivalOrDeparture($visit->fresh(), 'departure');

        $message = $steps['outcome'] === 'success'
            ? 'انتهت الزيارة — زيارة ناجحة (سُجّلت فاتورة أو تحصيل).'
            : 'انتهت الزيارة — زيارة سلبيّة (بلا فاتورة ولا تحصيل).';

        if ($returnLater) {
            $message .= ' وسُجّلت لك عودةٌ إليه اليوم.';
        } elseif (ClientSettingsHelper::hideFinishedVisitClients() && ! hasPermission('clients_view_all_clients')) {
            $message .= ' أُخفي العميل من قائمتك بقيّة اليوم.';
        }

        return $this->ok([
            'visit_id' => (int) $visit->id,
            'outcome' => $steps['outcome'],
            'duration_minutes' => (int) round($visit->fresh()->elapsed_seconds / 60),
            'return_later' => $returnLater,
        ], $message);
    }

    /** إلغاء الزيارة — بسببٍ مكتوب ومن موقع العميل. متاحٌ قبل البدء أيضاً. */
    public function cancel(Request $request, Visit $visit): JsonResponse
    {
        VisitRules::authorizeOwner($visit, $request->user());

        if ($closed = VisitRules::closedMessage($visit)) {
            return $this->fail($closed, 422);
        }

        $data = $request->validate(['cancel_reason' => 'required|string|max:255'] + $this->locationRules($visit), [
            'cancel_reason.required' => 'اكتب سبب إلغاء الزيارة — هو كلّ ما يبقى منها في التقرير.',
        ] + $this->locationMessages('لإلغاء الزيارة'));

        if ($blocked = VisitRules::outOfRangeMessage($visit, $data['current_latitude'] ?? 0, $data['current_longitude'] ?? 0, 'لإلغاء الزيارة')) {
            return $this->fail($blocked, 422, ['code' => 'OUT_OF_RANGE']);
        }

        $visit->update([
            'departure_time' => now(),
            'stage' => Visit::STAGE_CANCELLED,
            'cancel_reason' => $data['cancel_reason'],
            'employee_latitude' => $data['current_latitude'] ?? null,
            'employee_longitude' => $data['current_longitude'] ?? null,
        ]);

        OperationLog::record('visit_cancelled', VisitRules::LOG_MODULE, $visit, 'إلغاء زيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'» — من تطبيق الجوال');

        return $this->ok(['visit_id' => (int) $visit->id], 'أُلغيت الزيارة.');
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /** ما يُمنع قبل الكتابة: زيارةٌ مغلقة، أو لم تبدأ بعد. */
    private function writeBlock(Visit $visit): ?JsonResponse
    {
        if ($closed = VisitRules::closedMessage($visit)) {
            return $this->fail($closed, 422, ['code' => 'VISIT_CLOSED']);
        }

        if ($notStarted = VisitRules::notStartedMessage($visit)) {
            return $this->fail($notStarted, 422, ['code' => 'VISIT_NOT_STARTED']);
        }

        return null;
    }

    private function locationRules(Visit $visit): array
    {
        $rule = VisitRules::locationRule($visit);

        return [
            'current_latitude' => [$rule, 'numeric', 'between:-90,90'],
            'current_longitude' => [$rule, 'numeric', 'between:-180,180'],
        ];
    }

    private function locationMessages(string $action): array
    {
        return [
            'current_latitude.required' => 'يجب تحديد موقعك الحالي '.$action.' — فعّل GPS.',
            'current_longitude.required' => 'يجب تحديد موقعك الحالي '.$action.' — فعّل GPS.',
        ];
    }

    private function timer(Visit $visit): array
    {
        return [
            'visit_id' => (int) $visit->id,
            'stage' => $visit->stage,
            'started_at' => $visit->started_at?->toDateTimeString(),
            'paused' => $visit->isPaused(),
            'elapsed_seconds' => (int) $visit->elapsed_seconds,
        ];
    }

    private function ownedNotification(Visit $visit, int $id, int $userId): notifications
    {
        return notifications::where('id', $id)->where('client_id', $visit->client_id)
            ->where('type', 'client_popup')->where('receiver_id', $userId)->firstOrFail();
    }

    /** الزيارة كاملةً كما تعرضها شاشة «إتمام الزيارة». */
    private function payload(Visit $visit, $user): array
    {
        $visit->loadMissing(['client.status_client:id,name,color', 'client.locations', 'client.neighborhood', 'client.branch:id,name', 'employee:id,name']);
        $client = $visit->client;
        $client?->setAttribute('due_balance', (float) Account::ledger()->where('client_id', $visit->client_id)->sum('balance'));
        $steps = VisitRules::steps($visit, (int) $user->id);
        $capture = VisitRules::competitorCaptureEnabled();
        $canChangeStatus = hasPermission('clients_change_status');
        $statusNeedsApproval = $canChangeStatus && ! hasPermission('clients_approve_status') && ClientSettingsHelper::clientStatusChangeRequiresApproval();
        $stageNeedsApproval = $canChangeStatus && ! hasPermission('clients_approve_stage');
        $coordinates = VisitRules::clientCoordinates($visit);

        return $this->timer($visit) + [
            'visit_number' => $visit->visit_number,
            'visit_date' => $visit->visit_date?->toDateString(),
            'departure_time' => $visit->departure_time?->toDateTimeString(),
            'outcome' => $visit->outcome,
            'cancel_reason' => $visit->cancel_reason,
            'employee' => $visit->employee ? ['id' => (int) $visit->employee->id, 'name' => $visit->employee->name] : null,
            'client' => $client ? ClientResource::summary($client) : null,
            'client_location' => $coordinates ? ['latitude' => $coordinates[0], 'longitude' => $coordinates[1]] : null,
            'steps' => $steps,
            'rules' => [
                'proximity_meters' => (int) round(ClientSettingsHelper::visitProximityRadiusKmFor($visit->employee) * 1000),
                'any_location' => VisitRules::canVisitFromAnyLocation() || VisitRules::isClientExempt($client),
                'competitor_capture' => $capture,
                'signature_required' => ClientSettingsHelper::visitWorkspaceSectionVisible('show_signature_section'),
                'note_deposit_count' => ClientSettingsHelper::visitWorkspaceSectionVisible('note_deposit_count'),
                'note_photo' => ClientSettingsHelper::visitWorkspaceSectionVisible('note_photo'),
                'note_min_photos' => ClientSettingsHelper::noteMinPhotos(),
                'max_upload_kb' => ClientSettingsHelper::maxUploadSizeKb(),
            ],
            'can' => [
                'complete' => hasPermission('clients_visit_complete'),
                'cancel' => hasPermission('clients_visit_cancel'),
                'change_shift' => hasPermission('clients_change_contact_shift'),
                'reply_notifications' => hasPermission('notifications_reply_to_notifications'),
                'change_status' => $canChangeStatus,
                'status_needs_approval' => $statusNeedsApproval,
                'stage_needs_approval' => $stageNeedsApproval,
            ],
            'status_approvers' => $statusNeedsApproval ? ClientStatusRequest::approvers() : [],
            'stage_approvers' => $stageNeedsApproval ? ClientStageRequest::approvers() : [],
            'contacts' => ContactService::listFor((int) $visit->client_id),
            'notes' => ClientRelation::where('client_id', $visit->client_id)->with(['employee:id,name', 'clientEmployee:id,employee_name'])
                ->latest()->limit(20)->get()->map(fn ($n) => NoteService::present($n))->values(),
            'competitors_observed' => VisitCompetitorObservation::where('visit_id', $visit->id)->latest()->get()->map(fn ($o) => [
                'competitor_id' => $o->competitor_id ? (int) $o->competitor_id : null,
                'name' => $o->competitor_name,
                'asset_count' => (int) $o->asset_count,
                'photo_url' => MediaStore::url(MediaStore::COMPETITORS, $o->photo_path),
            ])->values(),
            'competitor_options' => $capture ? Competitor::active()->orderBy('name')->get(['id', 'name']) : [],
            'client_notifications' => $this->clientNotifications((int) $visit->client_id, (int) $user->id),
            'visit_documents' => $visit->started_at ? $this->documents($visit, true) : [],
            'client_documents' => $this->documents($visit, false)->groupBy('group'),
        ];
    }

    /** إشعارات الإدارة عن هذا العميل الموجَّهة للمستخدم — غير المقروء أوّلاً. */
    private function clientNotifications(int $clientId, int $userId): Collection
    {
        $query = notifications::with('user:id,name')->where('client_id', $clientId)->where('type', 'client_popup')->where('receiver_id', $userId)
            ->where(fn ($q) => $q->where('is_sent', 1)->orWhere(fn ($s) => $s->where('is_scheduled', 1)->where('scheduled_at', '<=', now())));

        if (notifications::supportsParentLink()) {
            $query->with(['replies' => fn ($r) => $r->select('id', 'parent_id', 'description', 'created_at')->oldest('id')]);
        }

        return $query->orderBy('read')->latest('id')->limit(20)->get()->map(fn ($n) => [
            'id' => (int) $n->id,
            'title' => $n->title,
            'description' => $n->description,
            'read' => (bool) $n->read,
            'from' => $n->user?->name,
            'created_at' => $n->created_at?->toDateTimeString(),
            'replies' => $n->relationLoaded('replies') ? $n->replies->map(fn ($r) => ['description' => $r->description, 'created_at' => $r->created_at?->toDateTimeString()])->values() : [],
        ])->values();
    }

    /**
     * مستندات الزيارة (نافذتها ومنشئوها) أو آخر مستندات العميل — لتسليمها له
     * وهو حاضر. `type` + `id` يكفيان التطبيق لفتحها من واجهتها (فواتير، مرتجعات…).
     */
    private function documents(Visit $visit, bool $visitOnly): Collection
    {
        $clientId = (int) $visit->client_id;
        $creators = array_values(array_unique([(int) $visit->employee_id, (int) auth()->id()]));
        $window = function ($query, string $creator = 'created_by') use ($visit, $visitOnly, $creators) {
            if ($visitOnly) {
                $query->whereIn($creator, $creators)->where('created_at', '>=', $visit->started_at)
                    ->when($visit->departure_time, fn ($q) => $q->where('created_at', '<=', $visit->departure_time));
            }

            return $query->latest('created_at')->limit(self::DOCS_LIMIT);
        };

        $docs = collect();
        $push = fn (string $group, string $type, $id, $number, $amount, $at, array $extra = []) => $docs->push([
            'group' => $group, 'type' => $type, 'id' => (int) $id, 'number' => $number,
            'amount' => $amount !== null ? round((float) $amount, 2) : null, 'at' => $at?->toDateTimeString(),
        ] + $extra);

        $window(Invoice::where('client_id', $clientId)->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'returned')))
            ->get(['id', 'code', 'grand_total', 'payment_status', 'created_at'])
            ->each(fn ($i) => $push('invoices', 'invoice', $i->id, $i->code, $i->grand_total, $i->created_at, ['payment_status' => (int) $i->payment_status]));

        $window(Invoice::where('client_id', $clientId)->where('type', 'returned'))
            ->get(['id', 'code', 'grand_total', 'created_at'])
            ->each(fn ($r) => $push('returns', 'sales_return', $r->id, $r->code, $r->grand_total, $r->created_at));

        $accountId = Account::ledger()->where('client_id', $clientId)->value('id');

        $window(Receipt::where(fn ($q) => $q->where('client_id', $clientId)->when($accountId, fn ($o) => $o->orWhere('account_id', $accountId))))
            ->get(['id', 'code', 'amount', 'created_at'])
            ->each(fn ($r) => $push('receipts', 'receipt', $r->id, $r->code, $r->amount, $r->created_at));

        // فواتير العميل مرّةً واحدة بدل `OR EXISTS` لكل صفٍّ من الدفعات (مسحٌ كامل)
        $invoiceIds = Invoice::where('client_id', $clientId)->pluck('id');

        $window(PaymentsProcess::whereIn('type', ['client payments', 'sale'])
            ->where(fn ($q) => $q->where('client_id', $clientId)->when($invoiceIds->isNotEmpty(), fn ($o) => $o->orWhereIn('invoice_id', $invoiceIds))), 'employee_id')
            ->get(['id', 'reference_number', 'amount', 'created_at'])
            ->each(fn ($p) => $push('receipts', 'client_payment', $p->id, $p->reference_number ?: str_pad((string) $p->id, 6, '0', STR_PAD_LEFT), $p->amount, $p->created_at));

        if ($accountId) {
            $window(Expense::where('account_id', $accountId))
                ->get(['id', 'code', 'amount', 'created_at'])
                ->each(fn ($e) => $push('expenses', 'expense', $e->id, $e->code, $e->amount, $e->created_at));
        }

        $window(CreditNotification::where('client_id', $clientId))
            ->get(['id', 'credit_number', 'grand_total', 'created_at'])
            ->each(fn ($c) => $push('credit_notes', 'credit_note', $c->id, $c->credit_number, $c->grand_total, $c->created_at));

        $window(ProductExchange::where('client_id', $clientId))
            ->get(['id', 'code', 'created_at'])
            ->each(fn ($x) => $push('exchanges', 'exchange', $x->id, $x->code, null, $x->created_at));

        return $docs->sortByDesc('at')->values();
    }
}
