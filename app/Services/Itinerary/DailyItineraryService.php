<?php

namespace App\Services\Itinerary;

use App\Enums\VisitStatus;
use App\Helpers\ClientSettingsHelper;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClientDashboardService;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\GeoDistance;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «خط سيري اليومي» — يومٌ واحدٌ من خطّ سير مندوب: زياراته بترتيبها وحالتها
 * ووقتها التقديريّ، والإضافةُ عليه.
 *
 * يقرأ الصفوف نفسها التي يكتبها «تخطيط خط السير» الأسبوعي
 * ({@see ItineraryWeekService}) — اليومُ بـ`visit_date` كما تقرؤه بقية النظام
 * ({@see EmployeeClientVisit::clientIdsForDate()}).
 *
 * **من أضاف العميل** (`added_by`): يُختم عند الإضافة من هذه الشاشة وحدها. صفوف
 * الخطّة الأسبوعية بلا ختم — هي الخطّة نفسها لا إضافةٌ عليها.
 *
 * **النطاق**: من يُضاف لخطّه = {@see DataScope::documentUserIds()} مع أقسام موظفي
 * العملاء وفرع الهيدر — نفس قواعد شاشة التخطيط. والعملاء = عقد «تابع لـ» في
 * {@see DataScope::selectableBranchIds()} مقصوصةً بفرع الهيدر.
 */
class DailyItineraryService
{
    public const DEFAULT_START = '08:00';

    public const DEFAULT_DURATION = 30;

    /** سرعةُ تنقّلٍ تقديريّة داخل المدن (كم/س) — لوقت الطريق بين زيارتين */
    private const CITY_SPEED_KMH = 30;

    /** آخرُ بدايةٍ تُحسب — يومٌ مزدحم يتوقّف عندها بدل أن يلتفّ بعد منتصف الليل */
    private const LAST_START = 23 * 60 + 55;

    /** أقصى عملاءٍ يعرضهم مُنتقي الإضافة في الطلب الواحد */
    public const PICKER_LIMIT = 100;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** أدوار النظام الأربعة مخزَّنةٌ بأسمائها الإنجليزية — والمندوب يقرأ «أضافه: … · مدير» */
    private const SYSTEM_ROLE_LABELS = ['owner' => 'المالك', 'manager' => 'مدير', 'admin' => 'مسؤول النظام', 'employee' => 'موظف'];

    /**
     * لونُ العميل في يومه على شاشتَي «خطوط السير» و«ملاحظات اليوم»: مصدرُه
     * (الخطّة الأسبوعية أو «خط سيري اليومي») × هل تمّت زيارته. بالترتيب الذي
     * يُعرض به دليلُ الألوان.
     */
    public const VISIT_TONES = [
        'added' => ['label' => 'مُضاف — لم يُزَر', 'color' => '#d97706', 'hint' => 'أُضيف لخط السير من «خط سيري اليومي» ولم تتم زيارته بعد'],
        'added_visited' => ['label' => 'مُضاف وتمت زيارته', 'color' => '#2563eb', 'hint' => 'أُضيف لخط السير من «خط سيري اليومي» وزاره المندوب'],
        'visited' => ['label' => 'من الخطة وتمت زيارته', 'color' => '#16a34a', 'hint' => 'في خطة الأسبوع وزاره المندوب'],
        'not_visited' => ['label' => 'من الخطة ولم يُزَر', 'color' => '#dc2626', 'hint' => 'في خطة الأسبوع ولم تتم زيارته — مخطَّطة أو جارية أو فائتة أو ملغاة'],
    ];

    public static function visitTone(bool $added, bool $visited): string
    {
        return match (true) {
            $added && $visited => 'added_visited',
            $added => 'added',
            $visited => 'visited',
            default => 'not_visited',
        };
    }

    public function __construct(private ItineraryWeekService $week) {}

    // ═══════════════════════ الأشخاص ═══════════════════════

    /** هل يقع هذا المستخدم في نطاق من يعمل المستخدمُ الحالي على خطوطهم؟ */
    public function canManage(int $userId): bool
    {
        if ($userId === (int) auth()->id()) {
            return true;
        }

        $allowed = DataScope::documentUserIds();

        return $allowed === null || in_array($userId, $allowed, true);
    }

    /**
     * مندوبو المُنتقي: بنطاق المستخدم، ومن «أقسام الموظفين المسموح تعيينهم
     * للعملاء»، ومن فرع الهيدر — ومعهم المستخدم نفسه دائماً.
     */
    public function employeeOptions(): Collection
    {
        $user = auth()->user();
        $scope = DataScope::documentUserIds();
        $header = $this->headerNodeIds();

        $others = $this->constrainToClientDepartments(User::whereNotNull('employee_id'))
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))
            ->when($header !== null, fn ($q) => $this->constrainUsersToNodes($q, $header))
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return collect([$user->only(['id', 'name'])])
            ->merge($others->map(fn (User $option) => $option->only(['id', 'name'])))
            ->values();
    }

    /** يصحّ الإضافة لخطّ هذا المندوب: من أقسام موظفي العملاء ومن فرع الهيدر */
    public function isTargetAllowed(int $userId): bool
    {
        if ($userId === (int) auth()->id()) {
            return true;
        }

        $departmentsOk = ClientSettingsHelper::clientEmployeeDepartmentIds() === []
            || $this->constrainToClientDepartments(User::whereKey($userId))->exists();

        $header = $this->headerNodeIds();

        return $departmentsOk && ($header === null || $this->constrainUsersToNodes(User::whereKey($userId), $header)->exists());
    }

    /**
     * عقدُ «تابع لـ» التي يختار منها المستخدم: نطاقُه مقصوصاً بفرع الهيدر،
     * بمسارها الكامل كي لا يلتبس فرعان باسمٍ واحد.
     *
     * @return Collection<int, array{id:int, label:string, type:string}>
     */
    public function nodeOptions(): Collection
    {
        $allowed = AffiliationTree::withOrganisationalChildren(DataScope::selectableBranchIds());
        $header = $this->headerNodeIds();

        return AffiliationTree::pathOptions(Branch::withoutGlobalScopes()->get(['id', 'name', 'parent_id', 'node_type', 'is_territory']))
            ->filter(fn (array $option) => ($allowed === null || in_array($option['id'], $allowed))
                && ($header === null || in_array($option['id'], $header)))
            ->map(fn (array $option) => [
                'id' => (int) $option['id'],
                'label' => $option['label'],
                'type' => $option['display_type'],
            ])
            ->values();
    }

    // ═══════════════════════ اليوم ═══════════════════════

    /**
     * زياراتُ اليوم بترتيبها ووقتها التقديريّ وحالتها، والإحصاءات.
     *
     * الوقت: يبدأ اليوم 08:00، ولكل زيارةٍ مدّتُها المخطَّطة (30 د افتراضاً)
     * ووقتُ الطريق إليها بخطٍّ مستقيم على 30 كم/س. ما بدأ أو انتهى فعلاً يُقرأ
     * وقتُه الحقيقيّ، و`planned_time` (العودة المجدولة) لا تُقدَّم عليه زيارة.
     */
    public function day(int $userId, Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $isToday = $date->isToday();

        $rows = EmployeeClientVisit::where('employee_id', $userId)
            ->whereDate('visit_date', $date->toDateString())
            ->orderByRaw('sequence IS NULL')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        $clientIds = $rows->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $described = $this->week->describeClients($clientIds);
        $contacts = Client::withoutGlobalScopes()->whereIn('id', $clientIds ?: [0])->get(['id', 'phone', 'mobile', 'city', 'street1', 'avatar', 'attachments'])->keyBy('id');
        // «جارية» = زيارةٌ بدأها المندوب — لا مساحةُ عملٍ فُتحت بلا «بدء الزيارة»
        $openVisit = $isToday ? Visit::startedWorkspaceClientIdsFor($userId)->all() : [];
        $evidenced = $this->evidencedRowIds($clientIds, $date);
        $rows = $this->visitedFirst($rows, $openVisit, $evidenced);
        $adders = $this->describeAdders($rows->pluck('added_by')->filter()->unique()->all());
        $gallery = $this->clientGallery($clientIds);

        $stops = [];
        $cursor = $this->toMinutes(self::DEFAULT_START);
        $nowMinutes = now()->hour * 60 + now()->minute;
        $projected = ! $isToday;
        $previous = null;
        $totals = ['km' => 0.0, 'minutes' => 0];

        foreach ($rows as $row) {
            $client = $described[(int) $row->client_id] ?? null;

            if ($client === null) {
                continue;
            }

            $state = $this->stateOf($row, in_array((int) $row->client_id, $openVisit, true), isset($evidenced[(int) $row->id]));
            $located = $client['lat'] !== null && $client['lng'] !== null;
            $legKm = null;
            $legMinutes = 0;

            if ($located && $previous !== null) {
                $legKm = GeoDistance::metersBetween($previous['lat'], $previous['lng'], $client['lat'], $client['lng']) / 1000;
                $legMinutes = (int) (ceil($legKm / self::CITY_SPEED_KMH * 60 / 5) * 5);
            }

            $duration = (int) ($row->planned_duration_minutes ?: self::DEFAULT_DURATION);

            /* ما بقي من يومٍ جارٍ يُحسب من الآن لا من 08:00: مندوبٌ لم يبدأ بعد
               الظهر لا تصير زياراتُه كلُّها «متأخرة» بتقديرٍ صباحيٍّ لم يلتزمه أحد. */
            if (! $projected && $state === 'planned') {
                $cursor = max($cursor, $nowMinutes);
                $projected = true;
            }

            $start = $cursor + $legMinutes;

            if ($row->planned_time) {
                $start = max($start, $this->toMinutes((string) $row->planned_time));
            }
            if ($row->started_at) {
                $start = $row->started_at->hour * 60 + $row->started_at->minute;
            }

            $start = min($start, self::LAST_START);
            $end = $row->started_at && $row->completed_at
                ? $row->completed_at->hour * 60 + $row->completed_at->minute
                : min($start + $duration, self::LAST_START + 4);

            if ($state !== 'cancelled') {
                $cursor = max($cursor, $end);
                $totals['km'] += $legKm ?? 0;
                $totals['minutes'] += $legMinutes + max(0, $end - $start);
            }

            if ($located && $state !== 'cancelled') {
                $previous = $client;
            }

            $contact = $contacts->get((int) $row->client_id);
            $adder = $row->added_by ? ($adders[(int) $row->added_by] ?? null) : null;

            $stops[] = [
                'id' => (int) $row->id,
                'client_id' => (int) $row->client_id,
                'name' => trim(preg_replace('/\s+/u', ' ', (string) $client['name'])),
                'code' => $client['code'],
                'client_status' => $client['status'],
                'place' => implode(' - ', array_filter([$client['unit'] ?? null, $client['branch'], $client['region']])) ?: ($contact->city ?? ''),
                'address' => implode('، ', array_filter([$contact->city ?? null, $contact->street1 ?? null])),
                'phone' => $contact ? (string) ($contact->mobile ?: $contact->phone ?: '') : '',
                'lat' => $client['lat'],
                'lng' => $client['lng'],
                'state' => $state,
                'start' => $this->fromMinutes($start),
                'end' => $this->fromMinutes($end),
                'planned_minutes' => $row->planned_time ? $this->toMinutes((string) $row->planned_time) + $duration : null,
                'leg_km' => $legKm !== null ? round($legKm, 1) : null,
                'leg_minutes' => $legKm !== null ? $legMinutes : null,
                'added_by' => $adder ? $adder + ['self' => (int) $row->added_by === $userId] : null,
                'note' => $gallery[(int) $row->client_id]['note'] ?? null,
                'photos' => $gallery[(int) $row->client_id]['photos'] ?? [],
                // صورةُ العميل المخزَّنة في ملفه — تُعرَض في بطاقة «العميل التالي»:
                // صورةُ شاشة «إضافة عميل» أوّلاً، ثم صورةُ الحساب (البوابة/الجوال)
                'client_photo' => $contact ? ($this->createFormPhoto($contact->attachments) ?? $contact->avatar_url) : null,
            ];
        }

        $this->markNextAndLate($stops, $date);

        $counted = array_filter($stops, fn (array $stop) => $stop['state'] !== 'cancelled');
        $done = count(array_filter($counted, fn (array $stop) => $stop['state'] === 'done'));

        return [
            'date' => $date->toDateString(),
            'date_label' => $date->copy()->locale('ar')->translatedFormat('l، j F Y'),
            'is_today' => $isToday,
            'is_past' => $date->lt(today()),
            'stops' => array_map(fn (array $stop) => array_diff_key($stop, ['planned_minutes' => true]), $stops),
            'stats' => [
                'done' => $done,
                'total' => count($counted),
                'percent' => $counted === [] ? 0 : (int) round($done / count($counted) * 100),
                'km' => round($totals['km'], 1),
                'minutes' => $totals['minutes'],
            ],
        ];
    }

    // ═══════════════════════ الإضافة ═══════════════════════

    /**
     * عملاءُ مُنتقي «إضافة عميل للمسار»: عقدةُ «تابع لـ» المختارة (أو نطاقُ
     * المستخدم كلُّه)، والبحث، و«في المسار» لمن له زيارةٌ مفتوحة ذلك اليوم.
     */
    public function pickerClients(int $userId, Carbon $date, ?int $nodeId, string $term): array
    {
        $query = $this->scopedClients($userId, $nodeId);

        if ($query === null) {
            return ['clients' => [], 'total' => 0, 'limit' => self::PICKER_LIMIT];
        }

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('trade_name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like));
        }

        $total = (clone $query)->count();
        $ids = $query->orderBy('trade_name')->limit(self::PICKER_LIMIT)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $described = $this->week->describeClients($ids);
        $inRoute = $this->openClientIds($userId, $date);

        return [
            'clients' => collect($ids)
                ->map(fn (int $id) => isset($described[$id]) ? [
                    'id' => $id,
                    'name' => $described[$id]['name'],
                    'code' => $described[$id]['code'],
                    'place' => implode(' - ', array_filter([$described[$id]['unit'] ?? null, $described[$id]['branch'], $described[$id]['region']])),
                    'in_route' => in_array($id, $inRoute, true),
                ] : null)
                ->filter()
                ->values()
                ->all(),
            'total' => $total,
            'limit' => self::PICKER_LIMIT,
        ];
    }

    /**
     * يضيف عملاءً إلى آخر خطّ سير اليوم ويختم `added_by` بالمستخدم الحالي.
     *
     * يُتخطّى: من هو خارج نطاق «تابع لـ» للمستخدم، وغيرُ المُسنَد حين يقصر
     * الإعدادُ الخطَّ على المُسنَدين، ومن له زيارةٌ مفتوحةٌ ذلك اليوم، وما يتجاوز
     * سقف اليوم. واليومُ المقفل بعد بدء زياراته يُرفض لغير صاحبه فقط — القفل
     * يحمي المندوب من تغيير خطّه تحت يده، لا يمنعه هو من الإضافة عليه.
     *
     * @param  array<int, int>  $clientIds
     * @return array{added:int, already:int, out_of_scope:int, unassigned:int, full:int, locked:bool}
     */
    public function addClients(int $userId, Carbon $date, array $clientIds, string $targetName): array
    {
        $result = ['added' => 0, 'already' => 0, 'out_of_scope' => 0, 'unassigned' => 0, 'full' => 0, 'locked' => false];
        $requested = array_values(array_unique(array_filter(array_map('intval', $clientIds))));
        $parts = $this->week->partsFor($date);
        $isSelf = $userId === (int) auth()->id();

        if (! $isSelf && $this->week->isLocked($userId, $parts['year'], $parts['week_number'], $parts['day_of_week'])) {
            return ['locked' => true] + $result;
        }

        $eligible = ($query = $this->scopedClients($userId, null))
            ? $query->whereIn('id', $requested ?: [0])->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        $result['out_of_scope'] = count(array_diff($requested, $eligible));

        if (ClientSettingsHelper::shouldRestrictItineraryToAssignedClients()) {
            $assigned = $this->week->assignedClientIds($userId);
            $result['unassigned'] = count(array_diff($eligible, $assigned));
            $eligible = array_values(array_intersect($eligible, $assigned));
        }

        $open = $this->openClientIds($userId, $date);
        $result['already'] = count(array_intersect($eligible, $open));
        $eligible = array_values(array_diff($eligible, $open));

        $dayRows = EmployeeClientVisit::where('employee_id', $userId)->whereDate('visit_date', $date->toDateString());
        $max = ClientSettingsHelper::itineraryMaxClientsPerDay();

        if ($max !== null) {
            $room = max(0, $max - (clone $dayRows)->count());
            $result['full'] = max(0, count($eligible) - $room);
            $eligible = array_slice($eligible, 0, $room);
        }

        if ($eligible === []) {
            return $result;
        }

        DB::transaction(function () use ($eligible, $userId, $date, $parts, $targetName, &$result) {
            $sequence = $this->renumberDay($userId, $date);

            foreach ($eligible as $clientId) {
                $visit = EmployeeClientVisit::create([
                    'employee_id' => $userId,
                    'added_by' => (int) auth()->id(),
                    'client_id' => $clientId,
                    'visit_date' => $date->toDateString(),
                    'day_of_week' => $parts['day_of_week'],
                    'year' => $parts['year'],
                    'week_number' => $parts['week_number'],
                    'sequence' => ++$sequence,
                    'status' => 'unactive',
                    'visit_status' => VisitStatus::PLANNED,
                ]);

                $result['added']++;

                OperationLog::record(
                    'visit_added',
                    'ClientItineraries',
                    $visit,
                    "إضافة العميل #{$clientId} إلى خط سير «{$targetName}» ليوم {$date->toDateString()} من «خط سيري اليومي»",
                    ['new' => ['client_id' => $clientId, 'employee_id' => $userId, 'visit_date' => $date->toDateString(), 'added_by' => (int) auth()->id()]]
                );
            }
        });

        return $result;
    }

    /**
     * «تأجيل الزيارة» — تنتقل إلى آخر خطّ اليوم التالي بأعمدة النقل نفسها التي
     * يكتبها «نقل الزيارات غير المكتملة».
     *
     * @return string|null رسالةُ الرفض، أو `null` عند النجاح
     */
    public function postpone(EmployeeClientVisit $row): ?string
    {
        if ($this->stateOf($row, false) !== 'planned') {
            return 'لا تُؤجَّل إلا زيارةٌ لم تبدأ بعد.';
        }

        $from = Carbon::parse($row->visit_date)->startOfDay();

        if ($from->lt(today())) {
            return 'زيارةُ يومٍ مضى لا تُؤجَّل — تُبرَّر.';
        }

        $to = $from->copy()->addDay();
        $parts = $this->week->partsFor($to);
        $target = EmployeeClientVisit::where('employee_id', $row->employee_id)->whereDate('visit_date', $to->toDateString());
        $max = ClientSettingsHelper::itineraryMaxClientsPerDay();

        if ($max !== null && (clone $target)->count() >= $max) {
            return "خطُّ الغد بلغ سقفه ({$max} عميلاً) — لا مكان للزيارة فيه.";
        }

        if ((clone $target)->where('client_id', $row->client_id)->exists()) {
            return 'العميل في خطّ الغد أصلاً.';
        }

        $old = ['visit_date' => $from->toDateString(), 'day_of_week' => $row->day_of_week, 'week_number' => $row->week_number, 'year' => $row->year];

        $row->update([
            'is_moved' => true,
            'moved_from_day' => strtolower((string) $row->day_of_week),
            'moved_from_week' => $row->week_number,
            'moved_from_year' => $row->year,
            'moved_at' => now(),
            'visit_date' => $to->toDateString(),
            'day_of_week' => $parts['day_of_week'],
            'week_number' => $parts['week_number'],
            'year' => $parts['year'],
            'sequence' => $this->renumberDay((int) $row->employee_id, $to) + 1,
        ]);

        OperationLog::record('visit_postponed', 'ClientItineraries', $row, "تأجيل الزيارة #{$row->id} إلى {$to->toDateString()}", [
            'old' => $old,
            'new' => ['visit_date' => $to->toDateString(), 'day_of_week' => $parts['day_of_week'], 'week_number' => $parts['week_number'], 'year' => $parts['year']],
        ]);

        return null;
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /**
     * يرقّم صفوف اليوم 1..n بترتيب عرضها ويُعيد n — كي يقع ما يُضاف بعدها.
     *
     * صفوفٌ كثيرة بلا `sequence` (قديمة، أو من «سأعود»)، والعرضُ يضع الفارغ
     * آخراً — فكان صفٌّ جديدٌ برقم 1 يتقدّم الخطَّ كلَّه بدل أن يُلحق به.
     */
    private function renumberDay(int $userId, Carbon $date): int
    {
        $ids = EmployeeClientVisit::where('employee_id', $userId)
            ->whereDate('visit_date', $date->toDateString())
            ->orderByRaw('sequence IS NULL')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get(['id', 'sequence']);

        foreach ($ids->values() as $index => $row) {
            if ((int) $row->sequence !== $index + 1) {
                EmployeeClientVisit::whereKey($row->id)->update(['sequence' => $index + 1]);
            }
        }

        return $ids->count();
    }

    /**
     * عملاءُ نطاق «تابع لـ» — `null` حين تكون العقدة المطلوبة خارجه.
     */
    private function scopedClients(int $userId, ?int $nodeId)
    {
        $allowed = AffiliationTree::withOrganisationalChildren(DataScope::selectableBranchIds());
        $header = $this->headerNodeIds();

        if ($nodeId && (($allowed !== null && ! in_array($nodeId, $allowed)) || ($header !== null && ! in_array($nodeId, $header)))) {
            return null;
        }

        $query = Client::query();

        if ($nodeId) {
            $query->inTreeNode($nodeId);
        } else {
            foreach (array_filter([$allowed, $header], fn ($ids) => $ids !== null) as $ids) {
                AffiliationTree::constrainClients($query, $ids, Schema::hasColumn('clients', 'group_id'));
            }
        }

        return $query;
    }

    /** عملاءُ لهم في ذلك اليوم زيارةٌ لم تنتهِ ولم تُلغَ — «في المسار» */
    private function openClientIds(int $userId, Carbon $date): array
    {
        return EmployeeClientVisit::where('employee_id', $userId)
            ->whereDate('visit_date', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('visit_status')
                ->orWhereNotIn('visit_status', [VisitStatus::COMPLETED->value, VisitStatus::CANCELLED->value]))
            ->where(fn ($q) => $q->whereNotNull('visit_status')->orWhere('status', '!=', 'active'))
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * ترتيبُ العرض بحسب الزيارة الفعليّة: من زاره المندوبُ أوّلاً يظهر أوّلاً
     * (بوقت `started_at`، ثم `completed_at`)، ثم الجارية، ثم ما بقي بترتيب
     * الخطّة (`sequence`) — والملغاةُ آخراً. الترتيبُ عرضٌ فقط، لا يُكتب.
     */
    private function visitedFirst(Collection $rows, array $openVisit, array $evidenced = []): Collection
    {
        return $rows->values()
            ->map(function (EmployeeClientVisit $row, int $index) use ($openVisit, $evidenced) {
                $state = $this->stateOf($row, in_array((int) $row->client_id, $openVisit, true), isset($evidenced[(int) $row->id]));
                $at = $row->started_at ?? $row->completed_at;

                $group = match (true) {
                    $state === 'cancelled' => 3,
                    $state === 'active' => $at ? 0 : 1,
                    $state === 'done' => 0,
                    default => 2,
                };

                return [$row, [$group, $group === 0 ? ($at?->getTimestamp() ?? PHP_INT_MAX) : 0, $index]];
            })
            ->sort(fn (array $a, array $b) => $a[1] <=> $b[1])
            ->map(fn (array $pair) => $pair[0])
            ->values();
    }

    /**
     * صفوفٌ «تمّت» بقاعدة لوحة العملاء ({@see ClientDashboardService::planRows()}):
     * مكتملة، أو للعميل في يومها زيارةٌ يدوية أو ملاحظةُ متابعة أو فاتورة — فعدّادُ
     * «تمت الزيارات» هنا هو «مكتملة» هناك وفي «أداء المندوبين».
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, true>  معرّف صفّ خطّ السير مفتاحاً
     */
    private function evidencedRowIds(array $clientIds, Carbon $date): array
    {
        if ($clientIds === []) {
            return [];
        }

        $ids = [];

        foreach (app(ClientDashboardService::class)->planRows($clientIds, $date->copy(), $date->copy()) as $row) {
            if ($row['key'] === 'visited') {
                $ids[$row['id']] = true;
            }
        }

        return $ids;
    }

    /**
     * `done|active|planned|missed|cancelled` — والصفُّ القديم بلا `visit_status`
     * يُقرأ من العمود القديم (`active` = زيارةٌ تمّت).
     */
    private function stateOf(EmployeeClientVisit $row, bool $hasOpenVisit, bool $evidenced = false): string
    {
        $status = $row->visit_status;

        return match (true) {
            $status === VisitStatus::COMPLETED, $status === null && $row->status === 'active' => 'done',
            $status === VisitStatus::CANCELLED => 'cancelled',
            $status === VisitStatus::STARTED && $row->visit_date?->isToday(), $hasOpenVisit => 'active',
            // زار ولم يضغط «إنهاء الزيارة» — تمّت بقاعدة لوحة العملاء
            $evidenced => 'done',
            $status === VisitStatus::MISSED => 'missed',
            default => 'planned',
        };
    }

    /**
     * «الزيارة التالية» = أوّل زيارةٍ لم تبدأ ما لم تكن زيارةٌ جاريةٌ الآن؛
     * و«متأخرة» = مخطَّطةٌ من يومٍ مضى، أو لها وقتٌ صريح (`planned_time`)
     * انقضى — التقديرُ وحده لا يُؤخِّر زيارة، فهو يُحسب من الآن.
     */
    private function markNextAndLate(array &$stops, Carbon $date): void
    {
        $hasActive = collect($stops)->contains(fn (array $stop) => $stop['state'] === 'active');
        $nowMinutes = now()->hour * 60 + now()->minute;
        $nextMarked = false;

        foreach ($stops as &$stop) {
            if ($stop['state'] !== 'planned') {
                continue;
            }

            if (! $hasActive && ! $nextMarked && ! $date->lt(today())) {
                $stop['state'] = 'next';
                $nextMarked = true;

                continue;
            }

            if ($date->lt(today()) || ($date->isToday() && $stop['planned_minutes'] !== null && $stop['planned_minutes'] < $nowMinutes)) {
                $stop['state'] = 'late';
            }
        }
        unset($stop);
    }

    /** @return array<int, array{id:int, name:string, role:?string}> */
    public function describeAdders(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'role_id']);
        $roles = Role::whereIn('id', $users->pluck('role_id')->filter()->unique())->pluck('name', 'id');

        return $users->mapWithKeys(fn (User $user) => [(int) $user->id => [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => $user->role_id && isset($roles[$user->role_id]) ? (self::SYSTEM_ROLE_LABELS[$roles[$user->role_id]] ?? $roles[$user->role_id]) : null,
        ]])->all();
    }

    /**
     * شارةُ «من أضاف العميل» لكل عميلٍ في خطّ مندوب — لشاشات العرض والتعديل
     * وملاحظات اليوم. صفوفُ الخطّة الأسبوعية (`added_by` فارغ) بلا شارة؛ وإن
     * تكرّر العميلُ فآخرُ صفٍّ مختومٍ يغلب.
     *
     * @param  Collection<int, EmployeeClientVisit>  $rows  فيها id وclient_id وadded_by
     * @return array<int, array{tone:string, label:string}>  العميل ← الشارة
     */
    public function sourceBadges(Collection $rows, int $repId): array
    {
        $adders = $this->describeAdders($rows->pluck('added_by')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all());
        $viewer = (int) auth()->id();
        $badges = [];

        foreach ($rows->sortBy('id') as $row) {
            $by = (int) $row->added_by;

            if (! $by) {
                continue;
            }

            $adder = $adders[$by] ?? null;
            $badges[(int) $row->client_id] = match (true) {
                $by === $repId => ['tone' => 'self', 'label' => $viewer === $repId ? 'أضفتَه أنت' : 'أضافه المندوب'],
                $by === $viewer => ['tone' => 'other', 'label' => 'أضفتَه أنت لخط المندوب'],
                default => ['tone' => 'other', 'label' => 'أضافه: '.($adder['name'] ?? '—').(! empty($adder['role']) ? ' · '.$adder['role'] : '')],
            };
        }

        return $badges;
    }

    /**
     * صورةُ العميل المرفوعة من شاشة «إضافة عميل» (`clients.attachments`، اسمُ
     * ملفٍّ تحت public/assets/uploads). الحقلُ نفسُه قد يحمل ملفّاً غير صورة
     * في بياناتٍ قديمة، فلا يُعرَض إلا ما امتدادُه صورة وملفُّه موجود.
     */
    private function createFormPhoto(?string $filename): ?string
    {
        $filename = trim((string) $filename);

        if ($filename === '' || ! preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $filename)) {
            return null;
        }

        $filename = basename($filename);

        if (! is_file(public_path('assets/uploads/'.$filename))) {
            return null;
        }

        // أسماءُ الملفات تحمل مسافاتٍ وحروفاً عربية — تُرمَّز لتصلح رابطاً
        return asset('assets/uploads/'.rawurlencode($filename));
    }

    /**
     * آخرُ ملاحظةٍ لكل عميل وصورُ ملاحظاته الأخيرة — لبطاقة «العميل التالي».
     * استعلامٌ لكل عميل بحدٍّ صغير: خطُّ اليوم عشراتٌ لا آلاف، وعميلٌ بمئات
     * الملاحظات لا يُحمَّل كلُّه.
     *
     * @return array<int, array{note:?array, photos:array<int, string>}>
     */
    private function clientGallery(array $clientIds): array
    {
        $gallery = [];

        foreach ($clientIds as $clientId) {
            $notes = ClientRelation::where('client_id', $clientId)
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'description', 'attachments', 'created_at']);

            $latest = $notes->first(fn ($note) => trim((string) $note->description) !== '');
            $photos = [];

            foreach ($notes as $note) {
                foreach ((array) json_decode((string) $note->attachments, true) as $file) {
                    if (is_string($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true)) {
                        $photos[] = asset('assets/uploads/notes/'.$file);
                    }
                }
            }

            $gallery[$clientId] = [
                'note' => $latest ? ['text' => trim((string) $latest->description), 'date' => optional($latest->created_at)->toDateString()] : null,
                'photos' => array_slice(array_values(array_unique($photos)), 0, 8),
            ];
        }

        return $gallery;
    }

    /** @return array<int, int>|null */
    private function headerNodeIds(): ?array
    {
        $node = HeaderBranchScope::nodeId();

        return $node ? AffiliationTree::descendantIds($node) : null;
    }

    /**
     * موظفو عُقَد: فرعُ ملفّ الموظف، أو أيُّ فرعٍ مُسنَدٍ له في `employee_branch`،
     * وإن خلا ملفُّه فـ`users.branch_id`.
     *
     * كان يُقرأ الفرعُ الأساسيّ وحده، فالفرعُ المُسنَد لموظفَين أحدُهما أساسيُّه
     * فرعٌ آخر أظهر موظفاً واحداً. كلُّ صفوف `employee_branch` سواء (`is_main`
     * هو الافتراضيُّ عند الدخول فقط).
     */
    private function constrainUsersToNodes($query, array $nodeIds)
    {
        return $query->where(fn ($w) => $w
            ->whereIn('employee_id', Employee::whereIn('branch_id', $nodeIds)->select('id'))
            ->orWhereIn('employee_id', DB::table('employee_branch')->whereIn('branch_id', $nodeIds)->select('employee_id'))
            ->orWhere(fn ($u) => $u->whereIn('branch_id', $nodeIds)
                ->whereIn('employee_id', Employee::whereNull('branch_id')->select('id'))));
    }

    private function constrainToClientDepartments($query)
    {
        if (ClientSettingsHelper::clientEmployeeDepartmentIds() === []) {
            return $query;
        }

        return $query->whereIn('employee_id', ClientSettingsHelper::scopeEmployeesToClientDepartments(Employee::query())->select('id'));
    }

    private function toMinutes(string $time): int
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', $time, $m) ? (int) $m[1] * 60 + (int) $m[2] : 0;
    }

    private function fromMinutes(int $minutes): string
    {
        $minutes = max(0, min($minutes, 23 * 60 + 59));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
