<?php

namespace App\Services\Itinerary;

use App\Enums\VisitStatus;
use App\Helpers\ClientSettingsHelper;
use App\Helpers\WeekHelper;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\EmployeeClientVisit;
use App\Models\Location;
use App\Models\User;
use App\Models\Visit;
use App\Support\GeoDistance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خطُّ السير الأسبوعي — مندوبٌ وسنةٌ وأسبوع، وعملاءُ كلِّ يومٍ من السبت إلى الجمعة.
 *
 * نفسُ آلية الشاشة القديمة: يُحفظ الأسبوعُ كلُّه يوماً يوماً في
 * `employee_client_visits` بمفتاح (الموظف، السنة، الأسبوع، اليوم)، ومعه ترتيبُ
 * الزيارة (`sequence`).
 *
 * **الترتيب من «ترتيب الزيارات في خط السير»** في إعدادات وحدات العملاء: «يدوي»
 * يحفظ ما رتّبه المستخدم بالسحب، و«الأقرب فالأقرب» و«الأقدم زيارةً أولاً» يرتّبهما
 * النظام عند العرض وعند الحفظ ({@see orderStops()}).
 *
 * **لا يُكتب `planned_time`**: ذاك ساعةُ العودة التي يسجّلها «سأعود» في مساحة
 * الزيارة، فلا تمحوها إعادةُ حفظ الأسبوع.
 *
 * **الحفظ يُطابق اليومَ بما في الشاشة**: ما أُضيف يُكتب، وما حُذف يُرفع — عدا
 * زيارةٍ بدأت أو انتهت أو فاتت أو أُلغيت، فسجلُّ ما جرى لا يُمحى بتعديل خطّة.
 */
class ItineraryWeekService
{
    public const DAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    private const DAY_NAMES = [
        'saturday' => 'السبت',
        'sunday' => 'الأحد',
        'monday' => 'الاثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
    ];

    /** @var array<int, array{name:string, parent_id:?int, type:string}>|null */
    private ?array $nodeIndex = null;

    /**
     * @param  array<string, array>  $visitsByDay  اليوم ← عملاؤه بترتيبهم
     * @return array{locked: array<int, string>, saved: int}
     */
    public function saveWeek(int $employeeId, int $year, int $week, array $visitsByDay): array
    {
        $days = [];
        foreach (self::DAYS as $day) {
            if (array_key_exists($day, $visitsByDay)) {
                $days[$day] = $this->normalizeClientIds((array) ($visitsByDay[$day] ?? []));
            }
        }

        $max = ClientSettingsHelper::itineraryMaxClientsPerDay();
        if ($max !== null) {
            $over = array_keys(array_filter($days, fn (array $ids) => count($ids) > $max));

            if ($over !== []) {
                throw ValidationException::withMessages([
                    'visits' => "الحدّ الأعلى لعملاء اليوم الواحد {$max} عميلاً (إعدادات خطوط السير) — تجاوزته: "
                        .implode('، ', array_map([self::class, 'dayName'], $over)),
                ]);
            }
        }

        // الترتيبُ الآليّ يُعاد على الخادم — لا يُعتمد على ما رتّبته الصفحة
        if (ClientSettingsHelper::itineraryOrderStrategy() !== 'manual') {
            $described = $this->describeClients(array_merge([], ...array_values($days)));
            foreach ($days as $day => $ids) {
                $days[$day] = array_column($this->orderStops(array_values(array_filter(array_map(fn ($id) => $described[$id] ?? null, $ids)))), 'id');
            }
        }

        return DB::transaction(function () use ($employeeId, $year, $week, $days) {
            $locked = [];
            $saved = 0;

            foreach ($days as $day => $ids) {
                if ($this->isLocked($employeeId, $year, $week, $day)) {
                    $locked[] = $day;

                    continue;
                }

                $this->syncDay($employeeId, $year, $week, $day, $ids);
                $saved += count($ids);
            }

            return ['locked' => $locked, 'saved' => $saved];
        });
    }

    /**
     * خطُّ أسبوعٍ كما هو محفوظ — للتحميل وللنسخ من أسبوعٍ سابق.
     *
     * @return array{days: array<string, array>, locked_days: array<int, string>}
     */
    public function weekPayload(int $employeeId, int $year, int $week): array
    {
        $rows = EmployeeClientVisit::where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('week_number', $week)
            ->orderByRaw('sequence IS NULL')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get(['id', 'client_id', 'day_of_week', 'visit_status', 'added_by']);

        $described = $this->describeClients($rows->pluck('client_id')->all());
        $days = array_fill_keys(self::DAYS, []);
        $locked = [];
        // حلٌّ عند الطلب لا حقنٌ: DailyItineraryService نفسُها تحقن هذه الخدمة
        $daily = app(DailyItineraryService::class);

        foreach ($rows->groupBy(fn ($visit) => strtolower((string) $visit->day_of_week)) as $day => $visits) {
            if (! array_key_exists($day, $days)) {
                continue;
            }

            $sources = $daily->sourceBadges($visits, $employeeId);

            foreach ($visits->unique('client_id') as $visit) {
                $client = $described[(int) $visit->client_id] ?? null;

                if ($client !== null) {
                    $days[$day][] = $client + [
                        'client_id' => (int) $visit->client_id,
                        'visit_status' => $visit->visit_status?->value,
                        'source' => $sources[(int) $visit->client_id] ?? null,
                    ];
                }
            }

            $days[$day] = $this->orderStops($days[$day]);

            if (ClientSettingsHelper::itineraryLockAfterStart()
                && $visits->contains(fn ($visit) => in_array($visit->visit_status, [VisitStatus::STARTED, VisitStatus::COMPLETED], true))) {
                $locked[] = $day;
            }
        }

        return ['days' => $days, 'locked_days' => $locked];
    }

    /**
     * ترتيبُ عملاء يومٍ حسب «ترتيب الزيارات في خط السير».
     *
     *   • `manual`     — كما هم.
     *   • `nearest`    — من أوّل عميلٍ إلى أقرب عميلٍ متبقٍّ بإحداثياته؛ من لا
     *                    إحداثياتَ له يُلحق آخراً بترتيبه.
     *   • `last_visit` — الأقدمُ زيارةً أولاً، ومن لم يُزَر قطّ قبل الجميع.
     *
     * نفسُ القواعد في `route-builder-scripts` ليرتّب اليومُ فور الإضافة.
     *
     * @param  array<int, array>  $stops  بطاقاتٌ من {@see describeClients()}
     */
    public function orderStops(array $stops): array
    {
        return match (ClientSettingsHelper::itineraryOrderStrategy()) {
            'nearest' => $this->nearestFirst($stops),
            'last_visit' => collect($stops)->sortBy(fn (array $stop) => $stop['last_visit'] ?? '')->values()->all(),
            default => array_values($stops),
        };
    }

    /** يومٌ بدأت إحدى زياراته وإعدادُ «الإقفال بعد البدء» مفعَّل */
    public function isLocked(int $employeeId, int $year, int $week, string $day): bool
    {
        if (! ClientSettingsHelper::itineraryLockAfterStart()) {
            return false;
        }

        return $this->dayQuery($employeeId, $year, $week, $day)
            ->whereIn('visit_status', [VisitStatus::STARTED->value, VisitStatus::COMPLETED->value])
            ->exists();
    }

    /** مفتاحُ تاريخٍ في الجدول — بنفس حساب `WeekHelper` (الأسبوع يبدأ السبت) */
    public function partsFor(Carbon $date): array
    {
        $day = $date->copy()->startOfDay();
        $week = WeekHelper::getWeekAndYear($day);

        return [
            'year' => (int) $week['year'],
            'week_number' => (int) $week['week'],
            'day_of_week' => strtolower(WeekHelper::getCurrentDayNameEn($day)),
        ];
    }

    /** تاريخُ يومٍ من أسبوع — نظيرُ `ItineraryController::calculateVisitDate()` */
    public function dateFor(int $year, int $week, string $day): string
    {
        $jan1 = Carbon::create($year, 1, 1)->startOfDay();
        $offset = ($jan1->dayOfWeek + 1) % 7;

        return $jan1->copy()
            ->subDays($offset)
            ->addWeeks($week - 1)
            ->addDays((int) array_search($day, self::DAYS, true))
            ->toDateString();
    }

    public static function dayName(string $day): string
    {
        return self::DAY_NAMES[$day] ?? $day;
    }

    /**
     * عملاءُ المندوب المُسنَدون إليه — `client_employee.employee_id` معرّفُ
     * صفٍّ في `employees` لا في `users`، فيُترجَم عبر `users.employee_id`.
     *
     * @return array<int, int>
     */
    public function assignedClientIds(int $userId): array
    {
        $employeeId = User::whereKey($userId)->value('employee_id');

        if (! $employeeId) {
            return [];
        }

        return ClientEmployee::where('employee_id', $employeeId)
            ->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * بطاقاتُ العملاء للشاشة: الاسم والكود والحالة والمنطقة والفرع والإحداثيات
     * وتاريخُ آخر زيارة (لترتيب «الأقدم زيارةً أولاً»).
     *
     * المنطقةُ والفرع من «تابع لـ» صعوداً في الشجرة بالنوع **المعروض** —
     * «منطقة الرياض» مخزَّنةٌ مجموعةً في هذه القاعدة.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array{id:int, name:string, code:string, status:?array, region:?string, branch:?string, unit:?string, lat:?float, lng:?float, last_visit:?string}>
     */
    public function describeClients(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $clients = Client::withoutGlobalScopes()
            ->with('status_client:id,name,color')
            ->whereIn('id', $ids)
            ->get(['id', 'trade_name', 'first_name', 'last_name', 'code', 'tree_node_id', 'branch_id', 'status_id']);

        $coords = Location::whereIn('client_id', $ids)
            ->clientPoint()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('id')
            ->get(['client_id', 'latitude', 'longitude'])
            ->unique('client_id')
            ->keyBy('client_id');

        $lastVisits = Visit::withoutGlobalScopes()
            ->whereIn('client_id', $ids)
            ->groupBy('client_id')
            ->selectRaw('client_id, MAX(visit_date) AS last_visit')
            ->pluck('last_visit', 'client_id');

        $described = [];

        foreach ($clients as $client) {
            $place = $this->placeOf((int) ($client->tree_node_id ?: $client->branch_id));
            $location = $coords->get($client->id);
            $status = $client->status_client;
            $lastVisit = $lastVisits->get($client->id);

            $described[(int) $client->id] = [
                'id' => (int) $client->id,
                'name' => $client->display_name,
                'code' => (string) ($client->code ?? ''),
                'status' => $status ? ['name' => $status->name, 'color' => $status->color] : null,
                'region' => $place['region'],
                'branch' => $place['branch'],
                'unit' => $place['unit'],
                'lat' => $location ? (float) $location->latitude : null,
                'lng' => $location ? (float) $location->longitude : null,
                'last_visit' => $lastVisit ? Carbon::parse($lastVisit)->toDateString() : null,
            ];
        }

        return $described;
    }

    /** @return array<int, int> معرّفاتٌ فريدة بترتيبها — تقبل `{client_id}` أو رقماً مجرّداً */
    public function normalizeClientIds(array $stops): array
    {
        $ids = [];

        foreach ($stops as $stop) {
            $clientId = (int) (is_array($stop) ? ($stop['client_id'] ?? 0) : $stop);

            if ($clientId > 0 && ! in_array($clientId, $ids, true)) {
                $ids[] = $clientId;
            }
        }

        return $ids;
    }

    private function nearestFirst(array $stops): array
    {
        $stops = array_values($stops);
        $located = array_values(array_filter($stops, fn (array $stop) => $stop['lat'] !== null && $stop['lng'] !== null));
        $unlocated = array_values(array_filter($stops, fn (array $stop) => $stop['lat'] === null || $stop['lng'] === null));

        if (count($located) <= 1) {
            return array_merge($located, $unlocated);
        }

        $ordered = [array_shift($located)];

        while ($located !== []) {
            $current = end($ordered);
            $nearest = null;
            $nearestDistance = null;

            foreach ($located as $index => $candidate) {
                $distance = GeoDistance::metersBetween($current['lat'], $current['lng'], $candidate['lat'], $candidate['lng']);

                if ($nearestDistance === null || $distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearest = $index;
                }
            }

            $ordered[] = $located[$nearest];
            unset($located[$nearest]);
            $located = array_values($located);
        }

        return array_merge($ordered, $unlocated);
    }

    /** @param  array<int, int>  $clientIds */
    private function syncDay(int $employeeId, int $year, int $week, string $day, array $clientIds): void
    {
        $existing = $this->dayQuery($employeeId, $year, $week, $day)->get();
        $byClient = $existing->groupBy(fn ($visit) => (int) $visit->client_id);
        $date = $this->dateFor($year, $week, $day);

        foreach (array_values($clientIds) as $index => $clientId) {
            $attributes = ['sequence' => $index + 1, 'visit_date' => $date];

            if ($byClient->has($clientId)) {
                EmployeeClientVisit::whereIn('id', $byClient->get($clientId)->pluck('id'))->update($attributes);

                continue;
            }

            EmployeeClientVisit::create($attributes + [
                'employee_id' => $employeeId,
                'client_id' => $clientId,
                'day_of_week' => $day,
                'year' => $year,
                'week_number' => $week,
                'status' => 'unactive',
            ]);
        }

        $removable = $existing->filter(fn (EmployeeClientVisit $visit) => ! in_array((int) $visit->client_id, $clientIds, true)
            && ($visit->visit_status === null || $visit->visit_status === VisitStatus::PLANNED));

        if ($removable->isNotEmpty()) {
            EmployeeClientVisit::whereIn('id', $removable->pluck('id'))->delete();
        }
    }

    private function dayQuery(int $employeeId, int $year, int $week, string $day): Builder
    {
        return EmployeeClientVisit::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('week_number', $week)
            ->where('day_of_week', $day);
    }

    /**
     * منطقةُ العميل وفرعُه، و`unit` = المنفذُ أو المجموعةُ التي يتبع لها تحت
     * فرعه — أقربُها إليه. كان المكانُ منطقةً وفرعاً فقط، فعميلُ «منفذ خميس
     * مشيط» يظهر «منطقة أبها · فرع الخميس» ولا يُعرف منفذُه.
     *
     * @return array{region:?string, branch:?string, unit:?string}
     */
    private function placeOf(int $nodeId): array
    {
        $index = $this->nodeIndex();
        $region = null;
        $branch = null;
        $unit = null;
        $cursor = $nodeId;
        $guard = 0;

        while ($cursor && isset($index[$cursor]) && $guard++ < 20) {
            $node = $index[$cursor];
            if ($branch === null && $region === null && in_array($node['type'], ['outlet', 'group'], true)) {
                $unit ??= $node['name'];
            }
            $region ??= $node['type'] === 'region' ? $node['name'] : null;
            $branch ??= $node['type'] === 'branch' ? $node['name'] : null;
            $cursor = $node['parent_id'];
        }

        return ['region' => $region, 'branch' => $branch, 'unit' => $unit];
    }

    private function nodeIndex(): array
    {
        if ($this->nodeIndex !== null) {
            return $this->nodeIndex;
        }

        $all = Branch::withoutGlobalScopes()->get(['id', 'name', 'parent_id', 'node_type', 'is_territory']);
        $children = $all->groupBy('parent_id');

        return $this->nodeIndex = $all->mapWithKeys(fn (Branch $node) => [
            (int) $node->id => [
                'name' => $node->name,
                'parent_id' => $node->parent_id ? (int) $node->parent_id : null,
                'type' => Branch::displayNodeType($node, $children->get($node->id, collect())),
            ],
        ])->all();
    }
}
