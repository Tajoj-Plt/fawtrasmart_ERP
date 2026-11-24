<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Models\ClientRelation;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Services\Clients\ClientVisibility;
use Modules\Integration\Services\Clients\MediaStore;
use Modules\Integration\Services\Clients\NoteService;

/**
 * ملاحظات المتابعة على العميل — نفس `ClientNoteController` في الموقع:
 *
 *   • العرض بصلاحيات «عرض الملاحظات» (الكلّ/المسندة/الخاصة) — من لا يملك
 *     إلا «الخاصة» يرى ملاحظاته وحدها.
 *   • الإضافة بصلاحيات «إضافة الملاحظات»، وقرب الموظف من العميل شرطٌ ما لم يُعفَ.
 *   • التعديل والحذف: «الكلّ» على أيّ ملاحظة، و«الخاصة» على ملاحظاته هو.
 */
class NoteController extends Controller
{
    use RespondsJson;

    private const EDIT_ALL = 'client_follow_up_edit_delete_notes_attachments_appointments_all';

    private const EDIT_OWN = 'client_follow_up_edit_delete_notes_attachments_appointments_own';

    public function __construct(private readonly ClientVisibility $visibility) {}

    public function index(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->visibility->canAccess($user, $client->id), 403, 'لا تملك صلاحية الوصول إلى هذا العميل.');

        $ownOnly = ! hasPermission([
            'client_follow_up_view_notes_attachments_appointments_all',
            'client_follow_up_view_notes_attachments_appointments_assigned',
        ]);

        $page = ClientRelation::where('client_id', $client->id)
            ->when($ownOnly, fn ($q) => $q->where('employee_id', $user->id))
            ->with(['employee:id,name', 'clientEmployee:id,employee_name', 'competitorObservations'])
            ->latest()
            ->paginate($this->perPage(15));

        $editAll = hasPermission(self::EDIT_ALL);
        $editOwn = hasPermission(self::EDIT_OWN);

        return $this->paginated($page, $page->getCollection()
            ->map(fn ($n) => NoteService::present($n, (int) $user->id, $editAll, $editOwn))->values()->all());
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->visibility->canAccess($user, $client->id), 403, 'لا تملك صلاحية الوصول إلى هذا العميل.');

        $note = NoteService::create($request, $client, $user);

        return $this->ok(
            NoteService::present($note->load(['employee:id,name', 'clientEmployee:id,employee_name']), (int) $user->id, hasPermission(self::EDIT_ALL), hasPermission(self::EDIT_OWN)),
            'تمت إضافة الملاحظة.',
            201
        );
    }

    public function update(Request $request, ClientRelation $note): JsonResponse
    {
        $this->authorizeEdit($request, $note, 'تعديل');

        $data = $request->validate([
            'client_employee_id' => ['required', 'integer', Rule::exists('client_employee_notes', 'id')->where('client_id', $note->client_id)],
            'description' => 'required|string|max:5000',
            'deposit_count' => 'required|integer|min:0|max:100000',
            'site_type' => ['required', Rule::in(NoteService::SITE_TYPES)],
            'competitor_documents' => 'required|integer|min:0',
            'shift' => 'required|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png|max:'.ClientSettingsHelper::maxUploadSizeKb(),
        ], [
            'client_employee_id.exists' => 'المسؤول المختار لا يتبع عميل هذه الملاحظة.',
            'site_type.in' => 'نوع الموقع غير صالح.',
        ]);

        $range = ClientEmployeeNote::parseShiftRange($data['shift']);
        if ($range === null) {
            return $this->fail('فترة الدوام غير صالحة — أرسل full أو من|إلى بصيغة HH:00|HH:00.', 422, ['errors' => ['shift' => ['فترة الدوام غير صالحة.']]]);
        }

        [$start, $end] = $range;
        $label = ClientEmployeeNote::isFullTimeRange($start, $end)
            ? ClientEmployeeNote::FULL_TIME_LABEL
            : ClientEmployeeNote::formatShiftTime($start).' إلى '.ClientEmployeeNote::formatShiftTime($end);

        $files = json_decode((string) $note->attachments, true) ?: [];
        foreach ((array) $request->file('attachments', []) as $file) {
            if ($file && $file->isValid()) {
                $files[] = MediaStore::storePhoto($file, MediaStore::NOTES);
            }
        }

        DB::transaction(function () use ($note, $data, $start, $end, $label, $files) {
            $note->update([
                'description' => $data['description'].' | دوام المسؤول: '.$label.' | عدد العهدة: '.$data['deposit_count'],
                'client_employee_id' => $data['client_employee_id'],
                'deposit_count' => $data['deposit_count'],
                'site_type' => $data['site_type'],
                'competitor_documents' => $data['competitor_documents'],
                'shift_start' => $start,
                'shift_end' => $end,
                'attachments' => $files ? json_encode(array_values($files)) : null,
                'additional_data' => array_merge((array) $note->additional_data, [
                    'deposit_count' => $data['deposit_count'],
                    'site_type' => $data['site_type'],
                    'competitor_documents' => $data['competitor_documents'],
                    'shift_start' => $start,
                    'shift_end' => $end,
                ]),
            ]);

            OperationLog::record('note_updated', NoteService::LOG_MODULE, (int) $note->client_id,
                'تعديل ملاحظة للعميل «'.($note->client->trade_name ?? $note->client_id).'» — من تطبيق الجوال');
        });

        return $this->ok(
            NoteService::present($note->fresh(['employee:id,name', 'clientEmployee:id,employee_name']), (int) $request->user()->id, hasPermission(self::EDIT_ALL), hasPermission(self::EDIT_OWN)),
            'تم تعديل الملاحظة.'
        );
    }

    public function destroy(Request $request, ClientRelation $note): JsonResponse
    {
        $this->authorizeEdit($request, $note, 'حذف');

        $files = json_decode((string) $note->attachments, true) ?: [];
        $clientId = (int) $note->client_id;
        $clientName = $note->client->trade_name ?? '#'.$clientId;

        DB::transaction(function () use ($note, $clientId, $clientName) {
            $note->delete();
            OperationLog::record('note_deleted', NoteService::LOG_MODULE, $clientId, 'حذف ملاحظة للعميل «'.$clientName.'» — من تطبيق الجوال');
        });

        foreach ($files as $file) {
            MediaStore::delete(MediaStore::NOTES, $file);
        }

        return $this->ok(null, 'تم حذف الملاحظة.');
    }

    public function destroyAttachment(Request $request, ClientRelation $note, int $index): JsonResponse
    {
        $this->authorizeEdit($request, $note, 'تعديل');

        $files = json_decode((string) $note->attachments, true) ?: [];
        if (! array_key_exists($index, $files)) {
            return $this->fail('المرفق غير موجود.', 404);
        }

        $removed = $files[$index];
        array_splice($files, $index, 1);
        $note->attachments = $files ? json_encode(array_values($files)) : null;
        $note->save();
        MediaStore::delete(MediaStore::NOTES, $removed);

        OperationLog::record('note_attachment_deleted', NoteService::LOG_MODULE, $note, 'حذف مرفق من ملاحظة العميل #'.$note->client_id.' — من تطبيق الجوال');

        return $this->ok(['remaining' => count($files)], 'تم حذف المرفق.');
    }

    /** «الكلّ» على أيّ ملاحظة، و«الخاصة» على ملاحظاته — ومحصوراً بعملاء نطاقه. */
    private function authorizeEdit(Request $request, ClientRelation $note, string $action): void
    {
        $user = $request->user();
        $allowed = hasPermission(self::EDIT_ALL) || (hasPermission(self::EDIT_OWN) && (int) $note->employee_id === (int) $user->id);

        abort_unless($allowed, 403, 'ليس لديك صلاحية '.$action.' هذه الملاحظة.');
        abort_unless($this->visibility->canAccess($user, (int) $note->client_id), 403, 'لا تملك صلاحية الوصول إلى عميل هذه الملاحظة.');
    }
}
