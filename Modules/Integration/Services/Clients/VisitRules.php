<?php

namespace Modules\Integration\Services\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Models\ClientModuleSetting;
use App\Models\ClientRelation;
use App\Models\Invoice;
use App\Models\notifications;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCompetitorObservation;
use App\Support\GeoDistance;
use App\Support\OperationLog;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * قواعد «مساحة عمل الزيارة» — منسوخةٌ من `VisitWorkspaceController` في
 * الموقع: من يفتح الزيارة، الإعفاء من الموقع، نطاق القرب من العميل، وسلسلة
 * الخطوات الإلزاميّة التي تحرس المستندات والإنهاء.
 */
class VisitRules
{
    public const LOG_MODULE = 'ClientVisitWorkspace';

    /** صاحب الزيارة، أو متتبّع الزيارات (`clients_visits_track`)، أو دور admin. */
    public static function authorizeOwner(Visit $visit, User $user): void
    {
        $allowed = (int) $visit->employee_id === (int) $user->id
            || hasPermission('clients_visits_track')
            || $user->hasRole('admin');

        if (! $allowed) {
            throw new AccessDeniedHttpException('لا يمكنك الوصول إلى زيارة لا تخصّك.');
        }
    }

    /** لا إجراء قبل «بدء الزيارة» — رسالة المنع أو null. */
    public static function notStartedMessage(Visit $visit): ?string
    {
        return $visit->started_at ? null : 'لم تبدأ الزيارة بعد — اضغط «بدء الزيارة» من موقع العميل قبل تنفيذ أي إجراء.';
    }

    /** زيارةٌ أُغلقت (أُنهيت أو أُلغيت) لا تُكتب فيها خطوات. */
    public static function closedMessage(Visit $visit): ?string
    {
        return $visit->departure_time ? 'هذه الزيارة مغلقة (أُنهيت أو أُلغيت) — افتح زيارةً جديدة للعميل.' : null;
    }

    public static function canVisitFromAnyLocation(): bool
    {
        return hasPermission('clients_visit_any_location');
    }

    public static function isClientExempt(?Client $client): bool
    {
        return $client !== null
            && ClientModuleSetting::current()->exempt_location_enabled
            && $client->isExemptFromVisitLocation();
    }

    /** `required` لمن يُلزَم بالموقع، و`nullable` لمن يُعفى — بصلاحيته أو بإعفاء العميل. */
    public static function locationRule(Visit $visit): string
    {
        return self::canVisitFromAnyLocation() || self::isClientExempt($visit->client) ? 'nullable' : 'required';
    }

    /** @return array{0: float, 1: float}|null */
    public static function clientCoordinates(Visit $visit): ?array
    {
        if ($visit->client_latitude && $visit->client_longitude) {
            return [(float) $visit->client_latitude, (float) $visit->client_longitude];
        }

        $location = $visit->client?->locations;

        return ($location && $location->latitude && $location->longitude)
            ? [(float) $location->latitude, (float) $location->longitude]
            : null;
    }

    /** خارج نطاق العميل؟ — رسالة المنع، أو null إن كان ضمنه أو معفى. */
    public static function outOfRangeMessage(Visit $visit, $lat, $lng, string $action): ?string
    {
        if (self::canVisitFromAnyLocation() || self::isClientExempt($visit->client)) {
            return null;
        }

        $coordinates = self::clientCoordinates($visit);
        if ($coordinates === null) {
            return 'لا يوجد موقعٌ مسجَّل لهذا العميل، فلا يمكن التحقّق من وجودك عنده. حدّث بيانات العميل بموقعه على الخريطة، أو ألغِ الزيارة.';
        }

        $distance = GeoDistance::metersBetween((float) $lat, (float) $lng, $coordinates[0], $coordinates[1]) / 1000;
        $max = ClientSettingsHelper::visitProximityRadiusKmFor($visit->employee);

        return $distance > $max
            ? sprintf('يجب أن تكون ضمن نطاق %.1f كم من موقع العميل %s. المسافة الحالية: %.2f كم.', $max, $action, $distance)
            : null;
    }

    public static function competitorCaptureEnabled(): bool
    {
        return ClientSettingsHelper::visitWorkspaceSectionVisible('show_competitor_capture');
    }

    /**
     * حالة الخطوات — مصدرٌ واحدٌ يقرؤه التطبيق ويحرس به الإنهاء:
     * مسؤول العميل ← دوامه ← ملاحظة الزيارة ← رصد المنافسين.
     */
    public static function steps(Visit $visit, int $userId): array
    {
        $clientId = (int) $visit->client_id;
        $contacts = ClientEmployeeNote::where('client_id', $clientId)->get(['shift_start', 'shift_end']);
        $since = $visit->started_at ?: now()->startOfDay();

        $hasContact = $contacts->isNotEmpty();
        $hasShift = $contacts->contains(fn ($c) => ! empty($c->shift_start) && ! empty($c->shift_end));
        $hasNote = ClientRelation::where('client_id', $clientId)->where('employee_id', $userId)->where('created_at', '>=', $since)->exists();
        $hasSale = self::hasSaleOrCollectionSince($clientId, $userId, $since);

        $competitorStep = self::competitorCaptureEnabled();
        $hasCompetitors = ! $competitorStep || VisitCompetitorObservation::where('visit_id', $visit->id)->exists();

        $basics = $hasContact && $hasShift && $hasNote;

        return [
            'has_contact' => $hasContact,
            'has_shift' => $hasContact && $hasShift,
            'has_note' => $hasNote,
            'competitor_step' => $competitorStep,
            'has_competitors' => $hasCompetitors,
            'docs_unlocked' => $basics && $hasCompetitors,
            'has_sale' => $hasSale,
            'can_complete' => $basics && $hasCompetitors,
            'can_return' => $basics,
            'outcome' => $hasSale ? 'success' : 'failed',
        ];
    }

    /** الخطوة الناقصة التالية برسالةٍ مفهومة — لرفض الإنهاء. */
    public static function missingStepMessage(array $steps, bool $returnLater): string
    {
        return match (true) {
            ! $steps['has_contact'] => 'أضِف مسؤول العميل أولاً.',
            ! $steps['has_shift'] => 'سجّل دوام المسؤول أولاً.',
            ! $steps['has_note'] => 'سجّل ملاحظة الزيارة أولاً.',
            default => $returnLater ? 'أكمل خطوات الزيارة أولاً.' : 'سجّل رصد المنافسين أولاً — أو اختر «سأعود لهذا العميل اليوم».',
        };
    }

    public static function hasSaleOrCollectionSince(int $clientId, int $userId, Carbon $since): bool
    {
        if (Invoice::where('client_id', $clientId)->where('created_by', $userId)->where('created_at', '>=', $since)->exists()) {
            return true;
        }

        $accountId = Account::ledger()->where('client_id', $clientId)->value('id');

        return (bool) $accountId && Receipt::where('account_id', $accountId)->where('created_by', $userId)->where('created_at', '>=', $since)->exists();
    }

    /** إشعار الوصول/الانصراف — نفس `VisitController::sendVisitNotifications()`. */
    public static function notifyArrivalOrDeparture(Visit $visit, string $type): void
    {
        try {
            $arrival = $type === 'arrival';
            $employee = $visit->employee->name ?? 'غير معروف';
            $client = $visit->client->trade_name ?? 'غير معروف';
            $time = Carbon::parse($arrival ? $visit->arrival_time : $visit->departure_time)->format('H:i');

            notifications::create([
                'user_id' => $visit->employee_id,
                'type' => 'visit',
                'title' => $arrival ? 'وصول موظف إلى عميل' : 'انصراف موظف من عميل',
                'description' => $arrival
                    ? "الموظف $employee وصل إلى العميل $client — $time"
                    : "الموظف $employee انصرف من العميل $client — $time",
                'client_id' => $visit->client_id,
                'read' => false,
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'visit_notification', $visit);
        }
    }
}
