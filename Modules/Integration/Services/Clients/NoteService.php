<?php

namespace Modules\Integration\Services\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Models\ClientModuleSetting;
use App\Models\ClientRelation;
use App\Models\Location;
use App\Models\notifications;
use App\Models\User;
use App\Services\PointsService;
use App\Support\GeoDistance;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ملاحظة المتابعة على العميل (`client_relations`) — نفس
 * `ClientNoteController::addnotes()` في الموقع بكامل منطقه: التحقّق من قرب
 * الموظف من العميل (ما لم يُعفَ)، عدد العهدة والصور من الإعدادات، الإشعار،
 * ربط موقع الموظف بالملاحظة، النقاط، وتقييم اكتمال خطّ السير.
 *
 * الدوام يُقرأ من مسؤول العميل المختار، أو يُرسَل `shift` صريحاً.
 */
class NoteService
{
    public const LOG_MODULE = 'Clients';

    public const SITE_TYPES = ['independent_booth', 'grocery', 'supplies', 'markets', 'station'];

    /**
     * @param  array{shift_start?: string, shift_end?: string, competitor_documents?: int}  $overrides
     */
    public static function create(Request $request, Client $client, User $user, array $overrides = []): ClientRelation
    {
        $bypass = hasPermission(['client_follow_up_add_notes_from_any_location', 'clients_visit_any_location'])
            || (ClientModuleSetting::current()->exempt_location_enabled && $client->isExemptFromVisitLocation());
        $location = $bypass ? 'nullable' : 'required';
        $requireDeposit = ClientSettingsHelper::visitWorkspaceSectionVisible('note_deposit_count');
        $requirePhoto = ClientSettingsHelper::visitWorkspaceSectionVisible('note_photo');
        $minPhotos = max(1, ClientSettingsHelper::noteMinPhotos());

        $data = $request->validate([
            'client_employee_id' => ['required', 'integer', Rule::exists('client_employee_notes', 'id')->where('client_id', $client->id)],
            'description' => 'required|string|max:5000',
            'process' => 'nullable|string|max:255',
            'deposit_count' => ($requireDeposit ? 'required' : 'nullable').'|integer|min:0|max:100000',
            'site_type' => ['nullable', Rule::in(self::SITE_TYPES)],
            'shift' => 'nullable|string',
            'attachments' => $requirePhoto ? 'required|array|min:'.$minPhotos.'|max:10' : 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png|max:'.ClientSettingsHelper::maxUploadSizeKb(),
            'current_latitude' => [$location, 'numeric', 'between:-90,90'],
            'current_longitude' => [$location, 'numeric', 'between:-180,180'],
        ], [
            'client_employee_id.required' => 'اختر مسؤول العميل الذي قابلته.',
            'client_employee_id.exists' => 'المسؤول المختار لا يتبع هذا العميل.',
            'description.required' => 'اكتب نصّ الملاحظة.',
            'deposit_count.required' => 'عدد العهدة إلزامي (0 إن لا عهدة).',
            'attachments.required' => 'أرفق '.$minPhotos.' صورة على الأقل.',
            'attachments.min' => 'أرفق '.$minPhotos.' صورة على الأقل.',
            'attachments.*.mimes' => 'المرفقات صورٌ فقط (jpg, jpeg, png).',
            'current_latitude.required' => 'يجب تحديد موقعك الحالي لإضافة الملاحظة — فعّل GPS.',
            'current_longitude.required' => 'يجب تحديد موقعك الحالي لإضافة الملاحظة — فعّل GPS.',
        ]);

        $contact = ClientEmployeeNote::find($data['client_employee_id']);
        [$shiftStart, $shiftEnd] = self::resolveShift($data['shift'] ?? null, $contact, $overrides);
        $lat = $data['current_latitude'] ?? null;
        $lng = $data['current_longitude'] ?? null;

        // قرب الموظف من العميل — للموظف غير المعفى، كما في الموقع
        if ($user->isEmployee() && ! $bypass) {
            $clientPoint = Location::where('client_id', $client->id)->clientPoint()->latest()->first();

            if (! $clientPoint) {
                throw ValidationException::withMessages(['current_latitude' => 'لم يُسجَّل موقع هذا العميل — تواصل مع الإدارة لتحديد موقعه.']);
            }

            $distance = GeoDistance::metersBetween((float) $lat, (float) $lng, (float) $clientPoint->latitude, (float) $clientPoint->longitude) / 1000;
            $max = ClientSettingsHelper::visitProximityRadiusKmFor($user);

            if ($distance > $max) {
                throw ValidationException::withMessages([
                    'current_latitude' => 'يجب أن تكون ضمن نطاق '.$max.' كم من العميل — المسافة الحالية: '.round($distance, 2).' كم.',
                ]);
            }
        }

        $deposit = $data['deposit_count'] ?? null;
        $competitorDocuments = (int) ($overrides['competitor_documents'] ?? 0);
        $shiftLabel = ClientEmployeeNote::isFullTimeRange($shiftStart, $shiftEnd)
            ? ClientEmployeeNote::FULL_TIME_LABEL
            : ClientEmployeeNote::formatShiftTime($shiftStart).' إلى '.ClientEmployeeNote::formatShiftTime($shiftEnd);
        $text = $data['description'].' | دوام المسؤول: '.$shiftLabel.' | عدد العهدة: '.($deposit ?? 0);

        $files = [];
        foreach ((array) $request->file('attachments', []) as $file) {
            if ($file && $file->isValid()) {
                $files[] = MediaStore::storePhoto($file, MediaStore::NOTES);
            }
        }

        try {
            return DB::transaction(function () use ($client, $user, $data, $contact, $shiftStart, $shiftEnd, $lat, $lng, $deposit, $competitorDocuments, $text, $files, $bypass) {
                $note = ClientRelation::create([
                    'employee_id' => $user->id,
                    'client_id' => $client->id,
                    'status' => 'pending',
                    'process' => $data['process'] ?? null,
                    'description' => $text,
                    'client_employee_id' => $contact->id,
                    'deposit_count' => $deposit,
                    'site_type' => $data['site_type'] ?? 'markets',
                    'competitor_documents' => $competitorDocuments,
                    'shift_start' => $shiftStart,
                    'shift_end' => $shiftEnd,
                    'attachments' => $files ? json_encode($files) : null,
                    'additional_data' => [
                        'deposit_count' => $deposit,
                        'competitor_documents' => $competitorDocuments,
                        'shift_start' => $shiftStart,
                        'shift_end' => $shiftEnd,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'source' => 'mobile',
                    ],
                ]);

                notifications::create([
                    'user_id' => $user->id,
                    'receiver_id' => $user->id,
                    'type' => 'client_note',
                    'title' => 'تم إضافة ملاحظة للعميل '.$client->trade_name,
                    'description' => $text,
                    'client_id' => $client->id,
                    'read' => false,
                ]);

                $client->last_note_at = now();
                $client->save();

                // موقع الموظف لحظة الملاحظة يُربط بها — نفس ما يكتبه الموقع
                if ($user->isEmployee() && $lat !== null && $lng !== null) {
                    Location::create([
                        'employee_id' => $user->id,
                        'client_id' => $client->id,
                        'client_relation_id' => $note->id,
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ]);
                }

                OperationLog::record('note_added', self::LOG_MODULE, $client,
                    'إضافة ملاحظة للعميل من تطبيق الجوال: '.mb_substr($data['description'], 0, 200).' — عدد العهدة: '.($deposit ?? 0).($bypass ? ' (معفى من الموقع)' : ''));

                try {
                    app(PointsService::class)->processAction('note_created', [
                        'employee_id' => $user->employee_id ?? $user->id,
                        'client_id' => $client->id,
                        'description' => 'تسجيل ملاحظة متابعة من الموظف',
                        'reference_type' => get_class($note),
                        'reference_id' => $note->id,
                    ]);
                    app(PointsService::class)->evaluateItineraryCompletion($user->id, now()->toDateString());
                } catch (\Throwable $e) {
                    OperationLog::failure($e, self::LOG_MODULE, 'points_on_note');
                }

                return $note;
            });
        } catch (\Throwable $e) {
            foreach ($files as $file) {
                MediaStore::delete(MediaStore::NOTES, $file);
            }

            throw $e;
        }
    }

    /** @return array{0: string, 1: string} */
    private static function resolveShift(?string $shift, ClientEmployeeNote $contact, array $overrides): array
    {
        if (isset($overrides['shift_start'], $overrides['shift_end'])) {
            return [$overrides['shift_start'], $overrides['shift_end']];
        }

        if ($shift !== null && $shift !== '') {
            $range = ClientEmployeeNote::parseShiftRange($shift);
            if ($range === null) {
                throw ValidationException::withMessages(['shift' => 'فترة الدوام غير صالحة — أرسل full أو من|إلى بصيغة HH:00|HH:00.']);
            }

            return $range;
        }

        if ($contact->hasShift()) {
            return [$contact->shift_start, $contact->shift_end];
        }

        throw ValidationException::withMessages(['shift' => 'حدّد دوام المسؤول «'.$contact->employee_name.'» مع الملاحظة.']);
    }

    public static function present(ClientRelation $note, ?int $viewerId = null, bool $canEditAll = false, bool $canEditOwn = false): array
    {
        $files = json_decode((string) $note->attachments, true) ?: [];
        $own = $viewerId !== null && (int) $note->employee_id === $viewerId;

        return [
            'id' => (int) $note->id,
            'client_id' => (int) $note->client_id,
            'description' => $note->description,
            'process' => $note->process,
            'status' => $note->status,
            'deposit_count' => $note->deposit_count !== null ? (int) $note->deposit_count : null,
            'competitor_documents' => (int) $note->competitor_documents,
            'site_type' => $note->site_type,
            'shift_start' => $note->shift_start,
            'shift_end' => $note->shift_end,
            'contact' => $note->relationLoaded('clientEmployee') && $note->clientEmployee
                ? ['id' => (int) $note->clientEmployee->id, 'name' => $note->clientEmployee->employee_name]
                : null,
            'employee' => $note->relationLoaded('employee') && $note->employee
                ? ['id' => (int) $note->employee->id, 'name' => $note->employee->name]
                : null,
            'attachments' => array_values(array_map(fn ($f, $i) => [
                'index' => $i,
                'url' => MediaStore::url(MediaStore::NOTES, $f),
            ], $files, array_keys($files))),
            'competitors' => $note->relationLoaded('competitorObservations')
                ? $note->competitorObservations->map(fn ($o) => [
                    'competitor_id' => $o->competitor_id ? (int) $o->competitor_id : null,
                    'name' => $o->competitor_name,
                    'asset_count' => (int) $o->asset_count,
                    'photo_url' => MediaStore::url(MediaStore::COMPETITORS, $o->photo_path),
                ])->values()
                : [],
            'can_edit' => $canEditAll || ($canEditOwn && $own),
            'created_at' => $note->created_at?->toDateTimeString(),
        ];
    }
}
