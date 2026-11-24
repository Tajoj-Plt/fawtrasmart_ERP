<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Visit;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Throwable;

/**
 * تسجيل زيارة من موقع الموظف — مقارنةً بموقع العميل المسجَّل.
 *
 * **ما كان مكسوراً**
 *
 * الدالّة تنادي `$this->calculateDistance(...)` و
 * `$this->sendNotificationToManager(...)` — ولا وجود لأيٍّ منهما في هذا
 * الصنف ولا في أبيه. فكلُّ طلبٍ يبلغ فرعَ «العميل له موقع مسجَّل» يسقط
 * بـ«Call to undefined method»، أي أنّ المسار لم ينجح قطّ.
 *
 * ونطاقُ القرب كان `100` متراً مكتوباً في الشيفرة، بينما في «إعدادات وحدات
 * العملاء» ← الزيارات حقلٌ اسمه «أقصى مسافة بين الموظف والعميل» قيمتُه
 * الافتراضية 0.3 كم — يُضبَط ولا يُقرأ هنا.
 *
 * **ما صار**: المسافة تُحسب بصيغة هافرساين، والنطاق من الإعدادات، والزيارة
 * تُسجَّل في `OperationLog`.
 *
 * @see \App\Helpers\ClientSettingsHelper::visitProximityRadiusKm()
 */
class LocationController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientVisits';

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:clients_visits_track|clients_itinerary_add');
    }

    public function store(Request $request)
    {
        if ($block = $this->sectionBlock('visits')) {
            return response()->json(['message' => $block], 422);
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'client_id' => 'required|exists:clients,id',
            'visit_date' => 'required|date',
            'status' => 'required|in:present,absent',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        // أحدث موقع مسجَّل للعميل مع هذا الموظف
        $clientLocation = Location::where('client_id', $request->client_id)
            ->where('employee_id', $request->employee_id)
            ->latest()
            ->first();

        if (! $clientLocation) {
            return response()->json(['message' => 'العميل أو الموظف ليس لديه موقع مسجَّل'], 400);
        }

        $distanceKm = $this->distanceKm(
            (float) $clientLocation->latitude,
            (float) $clientLocation->longitude,
            (float) $request->latitude,
            (float) $request->longitude
        );

        // النطاق من الإعدادات لا من `100` مكتوبةٍ في الشيفرة
        $radiusKm = ClientSettingsHelper::visitProximityRadiusKm();

        // الإعفاء من التحقّق من الموقع صلاحيةٌ قائمة في الكتالوج، ولم يكن
        // لها أثرٌ هنا
        if ($distanceKm > $radiusKm && ! hasPermission('clients_exempt_visit_location')) {
            return response()->json([
                'message' => 'أنت لست قريباً من العميل — المسافة '
                    .number_format($distanceKm * 1000, 0).' متر، والمسموح '
                    .number_format($radiusKm * 1000, 0).' متر.',
                'distance_km' => round($distanceKm, 3),
                'allowed_km' => $radiusKm,
            ], 400);
        }

        try {
            $visit = Visit::create([
                'employee_id' => $request->employee_id,
                'client_id' => $request->client_id,
                'visit_date' => $request->visit_date,
                'status' => $request->status,
                'employee_latitude' => $request->latitude,
                'employee_longitude' => $request->longitude,
                'client_latitude' => $clientLocation->latitude,
                'client_longitude' => $clientLocation->longitude,
                'distance' => round($distanceKm, 2),
                'recording_method' => 'location',
            ]);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store_by_location');

            return response()->json(['message' => 'تعذّر تسجيل الزيارة: '.$e->getMessage()], 500);
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $visit,
            'تسجيل زيارة بالموقع للعميل #'.$request->client_id
                .' على بعد '.number_format($distanceKm * 1000, 0).' متر',
            ['new' => [
                'client_id' => (int) $request->client_id,
                'employee_id' => (int) $request->employee_id,
                'distance_km' => round($distanceKm, 3),
            ]]
        );

        return response()->json($visit, 201);
    }

    /**
     * المسافة بين نقطتين بالكيلومترات — صيغة هافرساين.
     *
     * كانت `calculateDistance()` تُنادى ولا وجود لها، فالفرعُ الذي يبلغها
     * لم ينجح قطّ. والوحدة كيلومتر لا متر لأنّ الإعداد بالكيلومتر، فالتحويل
     * في موضعٍ واحد لا اثنين.
     */
    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return \App\Support\GeoDistance::metersBetween($lat1, $lon1, $lat2, $lon2) / 1000;
    }
}
