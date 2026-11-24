<?php

namespace Modules\Integration\Http\Controllers\Api\Tracking;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\EmployeeTracking;
use App\Models\Location;
use App\Models\User;
use App\Support\GeoDistance;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Services\TeamScope;

/**
 * تتبّع موقع المندوب — نفس ما يفعله الموقع (`ItineraryController::updateLocation()`
 * و`getMapData()`): الموقع الحالي صفٌّ واحد في `locations`، ومسار اليوم نقاطٌ
 * في `employee_tracking` لا تُحفظ إلا بتحرّكٍ ٢ متر أو مرور دقيقة.
 *
 * زيادة التطبيق: `points` دفعةً واحدة (حتى ١٠٠ نقطة بتوقيتها) لما جُمع
 * بلا اتصال. والعرض (مساري، فريقي) لمن يرى خطوط السير، بنطاقه: هو وفريقه.
 * كلّه معطَّلٌ متى أُطفئ «تتبّع خط السير» في إعدادات العملاء.
 */
class TrackingController extends Controller
{
    use RespondsJson;

    private const MIN_METERS = 2;

    private const MIN_SECONDS = 60;

    private const LIVE_MINUTES = 15;

    public function store(Request $request): JsonResponse
    {
        if ($blocked = $this->disabled()) {
            return $blocked;
        }

        $batch = $request->has('points');
        $data = $request->validate($batch ? [
            'points' => 'required|array|min:1|max:100',
            'points.*.latitude' => 'required|numeric|between:-90,90',
            'points.*.longitude' => 'required|numeric|between:-180,180',
            'points.*.recorded_at' => 'nullable|date',
        ] : [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'latitude.required' => 'الإحداثيات مطلوبة.',
            'longitude.required' => 'الإحداثيات مطلوبة.',
        ]);

        $userId = (int) $request->user()->id;
        $now = now();
        $points = $batch
            ? collect($data['points'])->map(fn ($p) => [
                'lat' => (float) $p['latitude'],
                'lng' => (float) $p['longitude'],
                'at' => isset($p['recorded_at']) ? Carbon::parse($p['recorded_at']) : $now->copy(),
            ])
            : collect([['lat' => (float) $data['latitude'], 'lng' => (float) $data['longitude'], 'at' => $now->copy()]]);

        // نقاطٌ من المستقبل أو أقدم من يوم تُسقط — توقيتٌ مصنوع لا يرسم مساراً
        [$valid, $rejected] = $points->partition(fn ($p) => $p['at']->lte($now->copy()->addMinutes(2)) && $p['at']->gte($now->copy()->subDay()));
        $valid = $valid->sortBy(fn ($p) => $p['at']->timestamp)->values();

        if ($valid->isEmpty()) {
            return $this->fail('لم تُقبل أيّ نقطة — توقيت النقاط خارج آخر ٢٤ ساعة.', 422);
        }

        $saved = 0;

        try {
            DB::transaction(function () use ($valid, $userId, &$saved) {
                $last = EmployeeTracking::where('employee_id', $userId)->latest('created_at')->latest('id')->first();

                foreach ($valid as $point) {
                    if ($last && Carbon::parse($last->tracking_date)->toDateString() === $point['at']->toDateString()) {
                        $meters = GeoDistance::metersBetween((float) $last->latitude, (float) $last->longitude, $point['lat'], $point['lng']);
                        $seconds = abs($point['at']->diffInSeconds($last->created_at, false));

                        if ($meters < self::MIN_METERS && $seconds < self::MIN_SECONDS) {
                            continue;
                        }
                    }

                    $row = new EmployeeTracking([
                        'employee_id' => $userId,
                        'latitude' => $point['lat'],
                        'longitude' => $point['lng'],
                        'tracking_date' => $point['at']->toDateString(),
                    ]);
                    $row->created_at = $point['at'];
                    $row->updated_at = $point['at'];
                    $row->save();

                    $last = $row;
                    $saved++;
                }

                $latest = $valid->last();
                $location = Location::firstOrNew(['employee_id' => $userId, 'client_id' => null, 'client_relation_id' => null]);
                $location->latitude = $latest['lat'];
                $location->longitude = $latest['lng'];
                $location->updated_at = now();
                $location->save();
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'ClientItineraries', 'integration.update_location');

            return $this->fail('تعذّر حفظ الموقع — أعد الإرسال لاحقاً.', 500);
        }

        return $this->ok([
            'received' => $points->count(),
            'saved' => $saved,
            'skipped' => $valid->count() - $saved,
            'rejected' => $rejected->count(),
        ]);
    }

    /** مسار يوم موظف (أنا افتراضاً) وموقعه الأخير. */
    public function path(Request $request): JsonResponse
    {
        if ($blocked = $this->disabled()) {
            return $blocked;
        }

        $request->validate(['employee_id' => 'nullable|integer|min:1', 'date' => 'nullable|date']);

        $user = $request->user();
        $employeeId = $request->integer('employee_id') ?: (int) $user->id;

        if (! TeamScope::covers($user, $employeeId)) {
            return $this->fail('لا يمكنك تتبّع موقع موظفٍ خارج فريقك.', 403);
        }

        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : now()->toDateString();

        $points = EmployeeTracking::where('employee_id', $employeeId)->where('tracking_date', $date)
            ->orderBy('created_at')->orderBy('id')->get(['latitude', 'longitude', 'created_at']);

        $distance = 0.0;
        $previous = null;
        foreach ($points as $point) {
            if ($previous) {
                $distance += GeoDistance::metersBetween((float) $previous->latitude, (float) $previous->longitude, (float) $point->latitude, (float) $point->longitude);
            }
            $previous = $point;
        }

        return $this->ok([
            'employee' => ['id' => $employeeId, 'name' => User::whereKey($employeeId)->value('name')],
            'date' => $date,
            'current' => $this->currentLocation($employeeId),
            'distance_km' => round($distance / 1000, 2),
            'points_count' => $points->count(),
            'points' => $points->map(fn ($p) => [
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'at' => $p->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }

    /** آخر موقع لكل من في فريقي خلال ٢٤ ساعة — و«مباشر» إن حُدِّث خلال ١٥ دقيقة. */
    public function team(Request $request): JsonResponse
    {
        if ($blocked = $this->disabled()) {
            return $blocked;
        }

        $user = $request->user();
        $scope = TeamScope::userIds($user);

        $rows = Location::whereNotNull('employee_id')->whereNull('client_id')
            ->whereNotNull('latitude')->whereNotNull('longitude')->where('latitude', '!=', 0)
            ->where('updated_at', '>=', now()->subDay())
            ->when($scope !== null, fn ($q) => $q->whereIn('employee_id', $scope))
            ->orderByDesc('updated_at')
            ->get(['employee_id', 'latitude', 'longitude', 'updated_at'])
            ->unique('employee_id');

        $names = User::whereIn('id', $rows->pluck('employee_id'))->pluck('name', 'id');

        return $this->ok([
            'count' => $rows->count(),
            'employees' => $rows->map(fn ($l) => [
                'employee' => ['id' => (int) $l->employee_id, 'name' => $names[$l->employee_id] ?? 'موظف'],
                'latitude' => (float) $l->latitude,
                'longitude' => (float) $l->longitude,
                'is_live' => $l->updated_at?->gt(now()->subMinutes(self::LIVE_MINUTES)) ?? false,
                'updated_at' => $l->updated_at?->toDateTimeString(),
                'is_me' => (int) $l->employee_id === (int) $user->id,
            ])->values(),
        ]);
    }

    private function currentLocation(int $userId): ?array
    {
        $row = Location::where('employee_id', $userId)->whereNull('client_id')
            ->whereNotNull('latitude')->whereNotNull('longitude')->where('latitude', '!=', 0)
            ->orderByDesc('updated_at')->first();

        return $row ? [
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'is_live' => $row->updated_at?->gt(now()->subMinutes(self::LIVE_MINUTES)) ?? false,
            'updated_at' => $row->updated_at?->toDateTimeString(),
        ] : null;
    }

    private function disabled(): ?JsonResponse
    {
        return ClientSettingsHelper::isRouteTrackingEnabled()
            ? null
            : $this->fail('تتبّع خط السير معطَّل من إعدادات العملاء.', 403, ['code' => 'TRACKING_DISABLED']);
    }
}
