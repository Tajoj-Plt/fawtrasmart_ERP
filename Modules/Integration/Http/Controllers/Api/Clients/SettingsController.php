<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\ClientEmployeeNote;
use App\Models\ClientModuleSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Services\Clients\ClientForm;
use Modules\Integration\Services\Clients\NoteService;

/**
 * ما يحتاجه التطبيق من «إعدادات وحدات العملاء» ليبني شاشاته بقواعد الخادم
 * نفسها — الأقسام المفعّلة، الحقول الإلزامية، قواعد الزيارة، وخيارات الدوام.
 * القيم هنا للعرض؛ الفرض الفعليّ في كل مسار.
 */
class SettingsController extends Controller
{
    use RespondsJson;

    public function __invoke(Request $request): JsonResponse
    {
        $module = ClientModuleSetting::current();

        return $this->ok([
            'sections' => [
                'visits' => (bool) $module->visits_enabled,
                'itinerary' => (bool) $module->itinerary_enabled,
                'notes' => (bool) $module->notes_enabled,
                'route_tracking' => (bool) ClientSettingsHelper::isRouteTrackingEnabled(),
            ],
            'required_fields' => ClientForm::requiredFields(),
            'client_type_scope' => ClientSettingsHelper::clientTypeScope(),
            'visit' => [
                'proximity_meters' => (int) round(ClientSettingsHelper::visitProximityRadiusKmFor($request->user()) * 1000),
                'competitor_capture' => ClientSettingsHelper::visitWorkspaceSectionVisible('show_competitor_capture'),
                'signature_required' => ClientSettingsHelper::visitWorkspaceSectionVisible('show_signature_section'),
                'note_deposit_count' => ClientSettingsHelper::visitWorkspaceSectionVisible('note_deposit_count'),
                'note_photo' => ClientSettingsHelper::visitWorkspaceSectionVisible('note_photo'),
                'note_min_photos' => ClientSettingsHelper::noteMinPhotos(),
                'hide_finished_clients' => ClientSettingsHelper::hideFinishedVisitClients(),
                'allow_return_same_day' => ClientSettingsHelper::allowReturnFinishedClientSameDay(),
            ],
            'status_change_requires_approval' => ClientSettingsHelper::clientStatusChangeRequiresApproval(),
            'itinerary_max_clients_per_day' => ClientSettingsHelper::itineraryMaxClientsPerDay(),
            'max_upload_kb' => ClientSettingsHelper::maxUploadSizeKb(),
            'image_quality' => ClientSettingsHelper::uploadImageQuality(),
            'image_max_dimension' => ClientSettingsHelper::uploadImageMaxDimension(),
            'site_types' => NoteService::SITE_TYPES,
            'shift' => [
                'full_time_value' => ClientEmployeeNote::FULL_TIME_VALUE,
                'full_time_label' => ClientEmployeeNote::FULL_TIME_LABEL,
                'hours' => collect(ClientEmployeeNote::shiftHourOptions())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'format' => 'HH:00|HH:00',
            ],
        ]);
    }
}
