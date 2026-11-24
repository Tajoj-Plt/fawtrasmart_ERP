<?php

namespace Modules\Client\Http\Controllers;

use App\Enums\VisitStatus;
use App\Helpers\ClientSettingsHelper;
use App\Helpers\WeekHelper;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientRelation;
use App\Models\EmployeeClientVisit;
use App\Models\EmployeeTracking;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\User;
use App\Models\Visit;
use App\Services\Clients\ClientEmployeeCandidates;
use App\Services\Itinerary\ItineraryWeekService;
use App\Services\PointsService;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * خطوط سير المندوبين: بناؤها أسبوعياً، وترتيبها، وتتبّعها على الخريطة.
 *
 * **ما يأتي من الإعدادات** — «إعدادات وحدات العملاء» ← تبويب خطوط السير:
 *
 *   • *القسم*    ← `itinerary_enabled`: مطفأً تُمنع الشاشة والروابط معاً.
 *   • *السقف*    ← `max_clients_per_day`: أقصى عدد عملاء في خطّ سير الموظف
 *                  في اليوم الواحد. كان الخطّ يقبل بلا حدّ، فيُسنَد لمندوبٍ
 *                  أربعون عميلاً في يومٍ لا يسع عشرة.
 *   • *الإقفال*  ← `lock_after_start`: بعد بدء أوّل زيارة يُقفل الخطّ عن
 *                  التعديل، فلا يُغيَّر تحت يد المندوب وهو ينفّذه.
 *   • *الترتيب*  ← `order_strategy`: يدوي، أو الأقرب فالأقرب، أو حسب
 *                  الأولوية، أو الأقدم زيارةً أولاً.
 *   • *الإسناد*  ← `itinerary_restrict_to_assigned_clients` (كان مربوطاً).
 *
 * **الحسابات**: `CASH_TREASURIES_PARENT` و`BANK_ACCOUNTS_PARENT` كانتا
 * ثابتين في تقرير التحصيلات — صارتا من «الحسابات المحاسبية»
 * (`ResolvesAccountingAccounts`). وهذه قراءةُ تقرير لا قيد، فتحتمل الغياب
 * ولا تستعمل `require`.
 *
 * **الصلاحيات**: لم تكن على المتحكّم صلاحيةٌ واحدة رغم وجود ستّ صلاحيات
 * خطوط سيرٍ معرَّفة في الكتالوج. صارت عليها.
 */
class ItineraryController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;
    use Concerns\ResolvesAccountingAccounts;
    use Concerns\RespectsAccountingPeriods;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientItineraries';

    /**
     * الأيام السبعة بترتيبها (السبت أولًا) — الاسم العربي والأيقونة
     * والإزاحة عن بداية الأسبوع. مصدرٌ واحد بدل النسخ المنفصلة التي كانت
     * موزَّعة بين هذا الكنترولر و`create.blade.php`/`edit.blade.php`
     * (وبعضها كان متعارضاً: أيقونات مختلفة، "الاثنين" مقابل "الإثنين").
     */
    public const DAYS = [
        'saturday' => ['name' => 'السبت', 'icon' => 'fa-calendar-day', 'offset' => 0],
        'sunday' => ['name' => 'الأحد', 'icon' => 'fa-sun', 'offset' => 1],
        'monday' => ['name' => 'الاثنين', 'icon' => 'fa-briefcase', 'offset' => 2],
        'tuesday' => ['name' => 'الثلاثاء', 'icon' => 'fa-calendar-check', 'offset' => 3],
        'wednesday' => ['name' => 'الأربعاء', 'icon' => 'fa-calendar-alt', 'offset' => 4],
        'thursday' => ['name' => 'الخميس', 'icon' => 'fa-calendar-week', 'offset' => 5],
        'friday' => ['name' => 'الجمعة', 'icon' => 'fa-mosque', 'offset' => 6],
    ];

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:clients_itinerary_view|clients_view_by_daily_itinerary', ['only' => [
            'create', 'edit', 'getWeekItinerary', 'getItineraryForWeek', 'getGroupsForEmployee',
            'getClientsForGroup', 'listAll', 'dayVisits', 'getIncompleteVisitsForDay',
            'getEmployeesListForModal', 'getClientStatus', 'getTodayNotes', 'monthlyNotesReport',
            'getMapData', 'getTodayNotesMapData', 'dashboard',
            'routeClients',
        ]]);

        $this->middleware('permission:clients_itinerary_add', ['only' => [
            'store', 'addClientToItinerary', 'quickAddToItinerary', 'bulkAddClientsToItinerary',
        ]]);

        $this->middleware('permission:clients_itinerary_edit', ['only' => [
            'update', 'destroyVisit', 'bulkDestroyVisits', 'moveIncompleteVisits', 'updateLocation',
            'startVisit', 'completeVisit', 'cancelVisit', 'missVisit', 'reorderVisits',
        ]]);
    }

    /**
     * موظفٌ يفتح/يعدّل خط سير غير خط سيره — لا صلاحية `clients_itinerary_*`
     * وحدها تكفي: كانت تسمح بالوصول إلى خط سير أيّ زميلٍ آخر بتغيير رقم
     * الموظف في الرابط أو حمولة الطلب. الحدُّ نطاقُ المستخدم
     * ({@see canManageItineraryOf()}) — نفس قاعدة `create()`/`edit()`/`update()`
     * الأصلية، مُستخرجةً هنا لتُطبَّق على كل نقطةٍ تقبل `employee_id` بدل
     * ثلاثٍ فقط من بين أكثر من خمس عشرة نقطة كانت تقبله بلا تحقّق.
     */
    private function assertCanAccessEmployeeItinerary(int $employeeId, string $message = 'لا يمكنك الوصول إلى خط سير موظف آخر.'): void
    {
        if (! $this->canManageItineraryOf($employeeId)) {
            abort(403, $message);
        }
    }

    /**
     * هل يحقّ للمستخدم الحالي أن يعمل على خط سير هذا المستخدم؟
     *
     * **النطاق لا وجودُ سجلّ موظف.** كان الفحص `isEmployee()` — وهي صادقةٌ
     * لكلّ مستخدمٍ له `employee_id`، والمدير والمالك هنا لهما سجلّا موظفَين.
     * فعومل المديرُ كمندوب: «إضافة إلى خط السير» تُسقط كلَّ موظفٍ غيره
     * بصمت فتقول «تمت الإضافة لـ 0 موظفين»، و«نقل الزيارات» يُرفض بـ403.
     * صار الحكمُ لـ{@see DataScope}: `data_scope_all` بلا قيد، والفرعُ/الفريقُ
     * على أشخاصه، ومن لا نطاق له من الموظفين يُقاس على تسلسله الإداري.
     *
     * و{@see DataScope::documentUserIds()} لا `userIds()`: هذه شاشةُ عملٍ لا
     * تقرير، فتتبع نطاقَ المستندات — وهو نفسُه الذي صارت تقرؤه «إدارة
     * العملاء» في خطّ السير، فلا يفترق ما يُعرض عمّا يُدار.
     */
    private function canManageItineraryOf(int $userId): bool
    {
        if ($userId === (int) auth()->id()) {
            return true;
        }

        $allowed = DataScope::documentUserIds();

        return $allowed === null || in_array($userId, $allowed, true);
    }

    /**
     * يقصر قائمة موظفين مطلوبةً على من يحقّ للمستخدم العمل على خطوط سيرهم،
     * ويعدّ المُستبعَدين كي تذكرهم رسالةُ النتيجة بدل «تمت إضافة 0» صامتة.
     *
     * @param  array<int, mixed>  $employeeIds
     * @return array<int, mixed>
     */
    private function filterManageableEmployees(array $employeeIds, int &$outOfScopeCount): array
    {
        return array_values(array_filter($employeeIds, function ($id) use (&$outOfScopeCount) {
            if ($this->canManageItineraryOf((int) $id)) {
                return true;
            }
            $outOfScopeCount++;

            return false;
        }));
    }

    /**
     * يقصر موظفي الطلب على من يجوز اختيارُه لهذا العميل: من قسم موظفي العملاء
     * في الإعدادات، وتابعٌ لعقدة العميل (المنطقة/الفرع/المجموعة/المنفذ وما تحتها)
     * أو مُسنَدٌ له — `ClientEmployeeCandidates`، الشرطُ الذي تبني به نوافذُ إدارة
     * علاقات العملاء قوائمها. قائمةٌ مقيَّدة وحدها ليست حارساً.
     *
     * @param  array<int, mixed>  $employeeIds
     * @return array<int, mixed>
     */
    private function filterClientEligibleEmployees(array $employeeIds, int $clientId, int &$notEligibleCount): array
    {
        $candidates = app(ClientEmployeeCandidates::class);

        return array_values(array_filter($employeeIds, function ($id) use ($candidates, $clientId, &$notEligibleCount) {
            if ($candidates->isEligible((int) $id, $clientId)) {
                return true;
            }
            $notEligibleCount++;

            return false;
        }));
    }

    /**
     * أبلغَ خطُّ سير الموظف في هذا اليوم سقفَه؟
     *
     * السقف `null` يعني بلا حدّ لا صفراً — والفرق هو الفرق بين «بلا تقييد»
     * و«ممنوعٌ تماماً».
     */
    protected function dayIsFull(int $employeeId, int $year, int $weekNumber, string $dayOfWeek): bool
    {
        $max = \App\Helpers\ClientSettingsHelper::itineraryMaxClientsPerDay();

        if ($max === null) {
            return false;
        }

        return EmployeeClientVisit::where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('week_number', $weekNumber)
            ->where('day_of_week', $dayOfWeek)
            ->count() >= $max;
    }

    /**
     * أقُفل خطُّ سير هذا اليوم عن التعديل؟
     *
     * يُفعِّل إعداد `itinerary_lock_after_start` — بمجرّد بدء أوّل زيارة في
     * اليوم (`visit_status: started` أو `completed`) يُمنع تعديل بقية زيارات
     * ذلك اليوم، فلا يُغيَّر الخطّ تحت يد المندوب وهو ينفّذه.
     */
    protected function dayIsLocked(int $employeeId, int $year, int $weekNumber, string $dayOfWeek): bool
    {
        if (! \App\Helpers\ClientSettingsHelper::itineraryLockAfterStart()) {
            return false;
        }

        return EmployeeClientVisit::where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('week_number', $weekNumber)
            ->where('day_of_week', $dayOfWeek)
            ->whereIn('visit_status', [VisitStatus::STARTED, VisitStatus::COMPLETED])
            ->exists();
    }

    /** أقصى عدد عملاء يعرضه مُنتقي الشاشة في الطلب الواحد */
    private const ROUTE_CLIENTS_LIMIT = 500;

    /**
     * شاشةُ تخطيط خط السير الأسبوعي — مندوبٌ وسنةٌ وأسبوع، ثم توزيعُ عملاء
     * الفرع على أيام السبت إلى الجمعة. {@see ItineraryWeekService}
     */
    public function create()
    {
        if ($block = $this->sectionBlock('itinerary')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        [$employees, $lockedToSelf] = $this->itineraryEmployeeOptions();

        $selectedId = (int) request('employee_id', $lockedToSelf ? auth()->id() : 0);

        return view('client::Itinerary.create', $this->routeBuilderData([
            'mode' => 'create',
            'employees' => $employees,
            'employee' => $employees->firstWhere('id', $selectedId),
            'employeeLocked' => $lockedToSelf,
        ] + $this->requestedWeek()));
    }

    /**
     * تعديلُ خطّ مندوبٍ — `?year=&week=` كروابط القائمة القديمة، أو `?date=`
     * فيُفتح أسبوعُه على يومه.
     */
    public function edit($employeeId)
    {
        if ($block = $this->sectionBlock('itinerary')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $employee = User::findOrFail($employeeId);

        $this->assertCanAccessEmployeeItinerary((int) $employeeId);

        return view('client::Itinerary.edit', $this->routeBuilderData([
            'mode' => 'edit',
            'employees' => collect([$employee]),
            'employee' => $employee,
            'employeeLocked' => true,
        ] + $this->requestedWeek()));
    }

    public function store(Request $request)
    {
        return $this->saveWeekRequest($request, null);
    }

    public function update(Request $request, $employeeId)
    {
        return $this->saveWeekRequest($request, (int) $employeeId);
    }

    /** خطُّ أسبوعٍ محفوظ: عملاءُ كل يومٍ بترتيبهم وحالتهم، والأيامُ المقفلة */
    public function getWeekItinerary($employeeId)
    {
        $this->assertCanAccessEmployeeItinerary((int) $employeeId);

        $service = app(ItineraryWeekService::class);
        $today = $service->partsFor(today());

        return response()->json($service->weekPayload(
            (int) $employeeId,
            (int) request('year', $today['year']),
            (int) request('week', $today['week_number'])
        ));
    }

    /**
     * عملاءُ مُنتقي الشاشة.
     *
     * كالشاشة القديمة: عملاءُ الجهة المختارة كلُّهم (فرعاً أو منطقةً أو منفذاً،
     * بما تحتها). «المُسنَدون للمندوب» تقييدٌ اختياريّ — وإجباريٌّ حين يكون إعدادُ
     * «تقييد خط السير على العملاء المُسنَدين» مفعَّلاً. `summary=1` يُعيد
     * معرّفات المُسنَدين وحدها لبطاقات الإحصاء.
     */
    public function routeClients(Request $request)
    {
        $service = app(ItineraryWeekService::class);
        $allowed = AffiliationTree::withOrganisationalChildren(DataScope::selectableBranchIds());
        $nodeId = $request->integer('node_id') ?: null;
        $routeEmployee = $request->integer('employee_id') ?: null;

        if ($routeEmployee) {
            $this->assertCanAccessEmployeeItinerary($routeEmployee);
        }

        $header = $this->headerNodeIds();
        $assignedIds = $routeEmployee ? $this->withinHeaderBranch($service->assignedClientIds($routeEmployee), $header) : [];
        $response = ['clients' => [], 'total' => 0, 'limit' => self::ROUTE_CLIENTS_LIMIT, 'assigned_ids' => $assignedIds];

        if ($request->boolean('summary')) {
            return response()->json($response);
        }

        $restrict = ClientSettingsHelper::shouldRestrictItineraryToAssignedClients();

        if ($restrict && ! $routeEmployee) {
            return response()->json($response + ['needs_employee' => true]);
        }

        $onlyAssigned = $restrict || $request->input('assigned') === 'route';

        // لا قائمةَ قبل اختيار جهة — إلا «المُسنَدون للمندوب»
        if (! $nodeId && ! $onlyAssigned) {
            return response()->json($response + ['needs_node' => true]);
        }

        if ($nodeId && (($allowed !== null && ! in_array($nodeId, $allowed)) || ($header !== null && ! in_array($nodeId, $header)))) {
            return response()->json($response);
        }

        $query = Client::query();

        if ($nodeId) {
            $query->inTreeNode($nodeId);
        } else {
            // بلا جهةٍ مختارة: نطاقُ المستخدم، ثمّ فرعُ الهيدر
            foreach (array_filter([$allowed, $header], fn ($ids) => $ids !== null) as $ids) {
                AffiliationTree::constrainClients($query, $ids, Schema::hasColumn('clients', 'group_id'));
            }
        }

        if ($onlyAssigned) {
            $query->whereIn('id', $assignedIds ?: [0]);
        }

        $term = trim((string) $request->input('q'));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('trade_name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like));
        }

        $total = (clone $query)->count();
        $ids = $query->orderBy('trade_name')->limit(self::ROUTE_CLIENTS_LIMIT)->pluck('id')->all();
        $described = $service->describeClients($ids);

        return response()->json(array_merge($response, [
            'clients' => collect($ids)->map(fn ($id) => $described[(int) $id] ?? null)->filter()->values(),
            'total' => $total,
        ]));
    }

    private function saveWeekRequest(Request $request, ?int $routeEmployee)
    {
        $data = $request->validate([
            'employee_id' => ($routeEmployee ? 'nullable' : 'required').'|integer|exists:users,id',
            'year' => 'required|integer|min:2020|max:2100',
            'week_number' => 'required|integer|min:1|max:53',
            'visits' => 'required|array',
            'visits.*' => 'nullable|array',
            'visits.*.*.client_id' => 'required|integer|exists:clients,id',
        ], [
            'employee_id.required' => 'اختر المندوب.',
        ]);

        $employeeId = $routeEmployee ?? (int) $data['employee_id'];

        if ($routeEmployee && ! empty($data['employee_id']) && (int) $data['employee_id'] !== $routeEmployee) {
            return response()->json(['success' => false, 'message' => 'لا يمكن نقل خط السير إلى مندوبٍ آخر من شاشة التعديل.'], 422);
        }

        $this->assertCanAccessEmployeeItinerary(
            $employeeId,
            $routeEmployee ? 'لا يمكنك تعديل خط سير موظف آخر.' : 'لا يمكنك إنشاء خط سير لموظف آخر.'
        );

        if (! $this->isItineraryEmployeeAllowed($employeeId)) {
            return response()->json([
                'success' => false,
                'message' => 'الموظف ليس من «أقسام الموظفين المسموح تعيينهم للعملاء» في إعدادات وحدات العملاء.',
            ], 422);
        }

        if (! $this->isItinerarySelfOnly() && ! $this->isInHeaderBranch($employeeId)) {
            return response()->json([
                'success' => false,
                'message' => 'المندوب ليس من الفرع المختار في أعلى الصفحة — غيّر الفرع من الهيدر أو اختر مندوباً منه.',
            ], 422);
        }

        $service = app(ItineraryWeekService::class);
        $visits = collect($data['visits'])
            ->mapWithKeys(fn ($stops, $day) => [strtolower((string) $day) => $stops ?? []])
            ->only(ItineraryWeekService::DAYS)
            ->all();

        if (ClientSettingsHelper::shouldRestrictItineraryToAssignedClients()) {
            $outside = collect($visits)->flatten(1)->pluck('client_id')->map(fn ($id) => (int) $id)->unique()
                ->diff($service->assignedClientIds($employeeId));

            if ($outside->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'visits' => 'إعدادات خطوط السير تقصر الخط على عملاء المندوب المُسنَدين إليه — '.$outside->count().' عميل غير مُسنَد.',
                ]);
            }
        }

        try {
            $result = $service->saveWeek($employeeId, (int) $data['year'], (int) $data['week_number'], $visits);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, $routeEmployee ? 'update' : 'store');

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء حفظ خط السير: '.$e->getMessage()], 500);
        }

        OperationLog::record(
            $routeEmployee ? 'itinerary_updated' : 'itinerary_created',
            self::LOG_MODULE,
            null,
            "خط سير الموظف #{$employeeId} — الأسبوع {$data['week_number']}/{$data['year']} — {$result['saved']} زيارة"
        );

        $message = 'تم حفظ خط السير.';
        if ($result['locked'] !== []) {
            $message .= ' تعذّر تعديل الأيام المقفلة بعد بدء زياراتها: '
                .implode('، ', array_map([ItineraryWeekService::class, 'dayName'], $result['locked']));
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'locked_days' => $result['locked'],
            'redirect' => route('itinerary.list'),
        ]);
    }

    /**
     * فرعُ الهيدر وما تحته — `null` حين لا فرعَ مختاراً أو كان الفرعَ الرئيسي
     * (`is_main` يعني المنشأة كلَّها، {@see HeaderBranchScope}).
     *
     * @return array<int, int>|null
     */
    private function headerNodeIds(): ?array
    {
        $node = HeaderBranchScope::nodeId();

        return $node ? AffiliationTree::descendantIds($node) : null;
    }

    /**
     * موظفو عقدٍ من الشجرة: فرعُ ملفّ الموظف (`employees.branch_id`)، وإن خلا
     * فـ`users.branch_id`. الأوّلُ أولاً لأنّ الثاني يكتبه مُبدِّلُ الهيدر نفسُه،
     * فينتقل الموظفُ بين الفروع كلّما بدّل فرعه في الأعلى.
     */
    private function constrainUsersToNodes($query, array $nodeIds)
    {
        return $query->where(fn ($w) => $w
            ->whereIn('employee_id', \App\Models\Employee::whereIn('branch_id', $nodeIds)->select('id'))
            ->orWhere(fn ($u) => $u->whereIn('branch_id', $nodeIds)
                ->whereIn('employee_id', \App\Models\Employee::whereNull('branch_id')->select('id'))));
    }

    private function isInHeaderBranch(int $userId): bool
    {
        $nodeIds = $this->headerNodeIds();

        return $nodeIds === null || $this->constrainUsersToNodes(User::whereKey($userId), $nodeIds)->exists();
    }

    /**
     * @param  array<int, int>  $clientIds
     * @return array<int, int>
     */
    private function withinHeaderBranch(array $clientIds, ?array $nodeIds): array
    {
        if ($nodeIds === null || $clientIds === []) {
            return $clientIds;
        }

        $query = Client::query()->whereIn('id', $clientIds);
        AffiliationTree::constrainClients($query, $nodeIds, Schema::hasColumn('clients', 'group_id'));

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** نطاقُ المستخدم نفسُه وحده (مندوب) */
    private function isItinerarySelfOnly(): bool
    {
        $scope = DataScope::documentUserIds();

        return $scope !== null && count(array_diff($scope, [(int) auth()->id()])) === 0;
    }

    /**
     * «أقسام الموظفين المسموح تعيينهم للعملاء» (إعدادات وحدات العملاء) على
     * استعلام `users` — فارغةً تعني كل الأقسام.
     */
    private function constrainToClientDepartments($query)
    {
        if (ClientSettingsHelper::clientEmployeeDepartmentIds() === []) {
            return $query;
        }

        return $query->whereIn('employee_id', ClientSettingsHelper::scopeEmployeesToClientDepartments(\App\Models\Employee::query())->select('id'));
    }

    private function isItineraryEmployeeAllowed(int $userId): bool
    {
        if (ClientSettingsHelper::clientEmployeeDepartmentIds() === [] || ($userId === (int) auth()->id() && $this->isItinerarySelfOnly())) {
            return true;
        }

        return $this->constrainToClientDepartments(User::whereKey($userId))->exists();
    }

    /**
     * مندوبو المُنتقي: بنطاق المستخدم، ومن أقسام موظفي العملاء وحدها.
     *
     * `employee_id` لا العمود النصّي `role`: الأخير مرآةٌ لاسم الدور المخصَّص
     * («مدير مبيعات» مثلاً)، فموظفٌ تحت دورٍ غير "employee" كان يختفي.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: bool}
     */
    private function itineraryEmployeeOptions(): array
    {
        $user = auth()->user();
        $scopeUserIds = DataScope::documentUserIds();
        $lockedToSelf = $this->isItinerarySelfOnly();
        $columns = ['id', 'name', 'employee_id', 'branch_id'];

        if ($lockedToSelf) {
            $employees = User::where('id', $user->id)->get($columns);
        } else {
            $employees = $this->constrainToClientDepartments(User::whereNotNull('employee_id'))
                ->when($scopeUserIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('id', $scopeUserIds)->orWhere('id', $user->id)))
                ->when($this->headerNodeIds(), fn ($q, $nodeIds) => $this->constrainUsersToNodes($q, $nodeIds))
                ->orderBy('name')
                ->get($columns);
        }

        return [$employees, $lockedToSelf];
    }

    private function routeBuilderData(array $data): array
    {
        return $data + [
            'nodes' => $this->itineraryNodeOptions(),
            'days' => self::DAYS,
            'headerBranch' => ($headerNode = HeaderBranchScope::nodeId()) ? Branch::findCached($headerNode)?->name : null,
            'mapsKey' => (string) config('services.google_maps.key'),
            // «ترتيب الزيارات في خط السير» في إعدادات وحدات العملاء
            'orderStrategy' => ClientSettingsHelper::itineraryOrderStrategy(),
            'maxPerDay' => ClientSettingsHelper::itineraryMaxClientsPerDay(),
            'restrictToAssigned' => ClientSettingsHelper::shouldRestrictItineraryToAssignedClients(),
            // السحبُ في «يدوي» وحده — غيرُه يرتّبه النظام؛ وللمندوب نفسِه بإعداد «السماح للموظف بإعادة الترتيب»
            'canReorder' => ClientSettingsHelper::itineraryOrderStrategy() === 'manual'
                && (ClientSettingsHelper::itineraryAllowReorderByEmployee() || ! $this->isItinerarySelfOnly()),
        ];
    }

    /** @return array{currentYear:int, currentWeek:int, selectedDay:string} */
    private function requestedWeek(): array
    {
        $service = app(ItineraryWeekService::class);

        if ($date = request('date')) {
            try {
                $parts = $service->partsFor(Carbon::parse((string) $date));

                return ['currentYear' => $parts['year'], 'currentWeek' => $parts['week_number'], 'selectedDay' => $parts['day_of_week']];
            } catch (\Throwable) {
                // تاريخٌ غير صالح في الرابط — يُكمل بالأسبوع الحالي
            }
        }

        $today = $service->partsFor(today());
        $year = (int) request('year', $today['year']);
        $week = (int) request('week', $today['week_number']);
        $isCurrent = $year === $today['year'] && $week === $today['week_number'];

        return [
            'currentYear' => $year,
            'currentWeek' => $week,
            'selectedDay' => $isCurrent ? $today['day_of_week'] : 'saturday',
        ];
    }

    /**
     * ترتيب زيارات يومٍ واحد حسب `itinerary_order_strategy`.
     *
     * `priority` لا بيانات أولويةٍ تسندها اليوم (لا حقل على العميل ولا على
     * الزيارة) — تتصرّف كـ`manual` حتى تُضاف بيانات أولوية حقيقية.
     */
    private function sortVisitsByStrategy(Collection $visits, string $strategy): Collection
    {
        return match ($strategy) {
            'last_visit' => $visits->sortBy(function ($visit) {
                $last = $visit->client?->visits?->first();

                return $last?->visit_date ?? \Carbon\Carbon::minValue();
            })->values(),
            'nearest' => $this->sortVisitsByProximity($visits),
            default => $visits->sortBy(fn ($v) => $v->sequence ?? PHP_INT_MAX)->values(),
        };
    }

    /**
     * ترتيبٌ جشِعٌ (nearest-neighbor): من كل عميل إلى أقرب عميلٍ متبقٍّ
     * بإحداثياته. لا نقطة بداية ثابتة (لا إحداثيات لفرعٍ في هذا المشروع) —
     * البداية من أول عميل بترتيبه الأصلي.
     */
    private function sortVisitsByProximity(Collection $visits): Collection
    {
        $remaining = $visits->values()->all();

        if (count($remaining) <= 1) {
            return collect($remaining);
        }

        $ordered = [];
        $current = array_shift($remaining);
        $ordered[] = $current;

        while (! empty($remaining)) {
            $currentCoords = $this->visitClientCoords($current);

            if (! $currentCoords) {
                $current = array_shift($remaining);
                $ordered[] = $current;

                continue;
            }

            $nearestIndex = null;
            $nearestDistance = null;

            foreach ($remaining as $index => $candidate) {
                $coords = $this->visitClientCoords($candidate);
                if (! $coords) {
                    continue;
                }

                $distance = \App\Support\GeoDistance::metersBetween(
                    $currentCoords[0], $currentCoords[1], $coords[0], $coords[1]
                );

                if ($nearestDistance === null || $distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            if ($nearestIndex === null) {
                // لا أحد من المتبقّين له إحداثيات — يُبقى ترتيبهم كما هو
                $ordered = array_merge($ordered, array_values($remaining));
                break;
            }

            $current = $remaining[$nearestIndex];
            unset($remaining[$nearestIndex]);
            $remaining = array_values($remaining);
            $ordered[] = $current;
        }

        return collect($ordered);
    }

    /** إحداثيات عميل الزيارة — أو `null` إن لم تتوفر. */
    private function visitClientCoords($visit): ?array
    {
        $location = $visit->client?->locations;

        if (! $location || ! $location->latitude || ! $location->longitude) {
            return null;
        }

        return [(float) $location->latitude, (float) $location->longitude];
    }

    /**
     * حفظ ترتيبٍ يدويٍ لعملاء يومٍ واحد — من `edit.blade.php` عبر السحب.
     *
     * العملاء مُعرَّفون بمعرّفات العميل لا الزيارة (هكذا تعمل شاشتا البناء
     * أصلاً)، فالتحديث يطابق الصفوف بـ(employee_id, client_id, day, year,
     * week) لا بمعرّف الزيارة مباشرة.
     */
    public function reorderVisits(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'year' => 'required|integer',
            'week_number' => 'required|integer',
            'day' => 'required|string',
            'ordered_client_ids' => 'required|array',
            'ordered_client_ids.*' => 'integer',
        ]);

        $employeeId = (int) $request->input('employee_id');

        $this->assertCanAccessEmployeeItinerary($employeeId, 'لا يمكنك إعادة ترتيب خط سير موظف آخر.');

        if (! ClientSettingsHelper::itineraryAllowReorderByEmployee() && auth()->user()->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'إعادة الترتيب اليدوي غير مفعَّلة حالياً في إعدادات خطوط السير.',
            ], 403);
        }

        $day = strtolower($request->input('day'));
        $year = (int) $request->input('year');
        $weekNumber = (int) $request->input('week_number');

        try {
            DB::transaction(function () use ($employeeId, $year, $weekNumber, $day, $request) {
                foreach ($request->input('ordered_client_ids') as $index => $clientId) {
                    EmployeeClientVisit::where('employee_id', $employeeId)
                        ->where('client_id', $clientId)
                        ->where('day_of_week', $day)
                        ->where('year', $year)
                        ->where('week_number', $weekNumber)
                        ->update(['sequence' => $index]);
                }
            });

            OperationLog::record(
                'visits_reordered',
                self::LOG_MODULE,
                null,
                "إعادة ترتيب عملاء يوم {$this->translateDay($day)} للموظف #{$employeeId}"
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ الترتيب.',
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'reorder_visits');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الترتيب: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroyVisit($visitId)
    {
        $visit = EmployeeClientVisit::findOrFail($visitId);
        $this->assertCanAccessEmployeeItinerary((int) $visit->employee_id, 'لا يمكنك حذف زيارة من خط سير موظف آخر.');

        try {
            if ($this->dayIsLocked((int) $visit->employee_id, (int) $visit->year, (int) $visit->week_number, $visit->day_of_week)) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطّ سير هذا اليوم مقفلٌ عن التعديل بعد بدء الزيارة.',
                ], 422);
            }

            $visit->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الزيارة بنجاح',
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy_visit');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء الحذف: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * حذف زيارات متعددة دفعة واحدة (Bulk Delete)
     */
    public function bulkDestroyVisits(Request $request)
    {
        try {
            $request->validate([
                'visit_ids' => 'required|array|min:1',
                'visit_ids.*' => 'required|integer|exists:employee_client_visits,id',
            ]);

            $visitIds = $request->input('visit_ids');
            $deletedCount = 0;
            $lockedCount = 0;
            $deniedCount = 0;

            DB::beginTransaction();

            foreach ($visitIds as $visitId) {
                $visit = EmployeeClientVisit::find($visitId);
                if (! $visit) {
                    continue;
                }

                // موظفٌ يحمل صلاحية «تعديل خطوط السير» يستطيع طلب حذف زيارةٍ من
                // خط سير زميلٍ آخر عبر تمرير رقمها ضمن الدفعة — تُستبعد بصمت
                // هنا كما تُستبعد الزيارات المقفلة، لا تُرفض الدفعة كلّها.
                if (! $this->canManageItineraryOf((int) $visit->employee_id)) {
                    $deniedCount++;

                    continue;
                }

                if ($this->dayIsLocked((int) $visit->employee_id, (int) $visit->year, (int) $visit->week_number, $visit->day_of_week)) {
                    $lockedCount++;

                    continue;
                }

                $visit->delete();
                $deletedCount++;
            }

            DB::commit();

            OperationLog::record(
                'visits_bulk_deleted',
                self::LOG_MODULE,
                null,
                'حذف '.$deletedCount.' زيارة من خطوط السير',
                ['old' => ['visit_ids' => $visitIds, 'deleted' => $deletedCount]]
            );

            $message = "تم حذف {$deletedCount} زيارة بنجاح";
            if ($lockedCount > 0) {
                $message .= " ({$lockedCount} زيارة مقفلة تعذّر حذفها)";
            }
            if ($deniedCount > 0) {
                $message .= " ({$deniedCount} زيارة لا تملك صلاحية الوصول إليها)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'deleted_count' => $deletedCount,
                'locked_count' => $lockedCount,
                'denied_count' => $deniedCount,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صالحة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'bulk_destroy_visits');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف: '.$e->getMessage(),
            ], 500);
        }
    }

    // ═══════════════════════ دورة حياة الزيارة ═══════════════════════

    /** بدء الزيارة الآن. */
    public function startVisit($visitId)
    {
        $visit = EmployeeClientVisit::findOrFail($visitId);
        $this->assertCanAccessEmployeeItinerary((int) $visit->employee_id, 'لا يمكنك بدء زيارة من خط سير موظف آخر.');

        try {
            DB::transaction(function () use ($visit) {
                $visit->startVisit();
            });

            OperationLog::record('visit_started', self::LOG_MODULE, $visit, "بدء الزيارة #{$visit->id}");

            return response()->json([
                'success' => true,
                'message' => 'تم بدء الزيارة.',
                'visit_status' => $visit->visit_status->value,
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'start_visit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء بدء الزيارة: '.$e->getMessage(),
            ], 500);
        }
    }

    /** إنهاء الزيارة الآن. */
    public function completeVisit($visitId)
    {
        $visit = EmployeeClientVisit::findOrFail($visitId);
        $this->assertCanAccessEmployeeItinerary((int) $visit->employee_id, 'لا يمكنك إنهاء زيارة من خط سير موظف آخر.');

        try {
            DB::transaction(function () use ($visit) {
                $visit->completeVisit();
            });

            OperationLog::record('visit_completed', self::LOG_MODULE, $visit, "إنهاء الزيارة #{$visit->id}");

            return response()->json([
                'success' => true,
                'message' => 'تم إنهاء الزيارة.',
                'visit_status' => $visit->visit_status->value,
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'complete_visit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنهاء الزيارة: '.$e->getMessage(),
            ], 500);
        }
    }

    /** إلغاء الزيارة بسبب. */
    public function cancelVisit(Request $request, $visitId)
    {
        $visit = EmployeeClientVisit::findOrFail($visitId);
        $this->assertCanAccessEmployeeItinerary((int) $visit->employee_id, 'لا يمكنك إلغاء زيارة من خط سير موظف آخر.');

        try {
            $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $reason = $request->input('reason');

            DB::transaction(function () use ($visit, $reason) {
                $visit->cancelVisit($reason);
            });

            OperationLog::record('visit_cancelled', self::LOG_MODULE, $visit, "إلغاء الزيارة #{$visit->id}: {$reason}");

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الزيارة.',
                'visit_status' => $visit->visit_status->value,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صالحة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'cancel_visit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الزيارة: '.$e->getMessage(),
            ], 500);
        }
    }

    /** تعليم الزيارة فائتة يدويًا (تعطيلٌ احتياطي بجانب الأمر المجدول). */
    public function missVisit(Request $request, $visitId)
    {
        $visit = EmployeeClientVisit::findOrFail($visitId);
        $this->assertCanAccessEmployeeItinerary((int) $visit->employee_id, 'لا يمكنك تعليم زيارة من خط سير موظف آخر فائتة.');

        try {
            $reason = $request->input('reason');

            DB::transaction(function () use ($visit, $reason) {
                $visit->markMissed($reason);
            });

            OperationLog::record('visit_missed', self::LOG_MODULE, $visit, "تعليم الزيارة #{$visit->id} فائتة");

            return response()->json([
                'success' => true,
                'message' => 'تم تعليم الزيارة كفائتة.',
                'visit_status' => $visit->visit_status->value,
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'miss_visit');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الزيارة: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getItineraryForWeek(Request $request, $employeeId)
    {
        $this->assertCanAccessEmployeeItinerary((int) $employeeId);

        $request->validate([
            'year' => 'required|integer',
            'week' => 'required|integer',
        ]);

        $year = $request->query('year');
        $week = $request->query('week');

        $itinerary = EmployeeClientVisit::with([
            'client' => function ($query) {
                $query->with([
                    'visits' => function ($q) {
                        $q->latest()->limit(1);
                    },
                    'invoices' => function ($q) {
                        $q->latest()->limit(1);
                    },
                    'appointmentNotes' => function ($q) {
                        $q->latest()->limit(1);
                    },
                ]);
            },
        ])
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('week_number', $week)
            ->get();

        return response()->json($itinerary);
    }

    public function getGroupsForEmployee(User $employee)
    {
        $this->assertCanAccessEmployeeItinerary((int) $employee->id);

        return response()->json($this->itineraryNodeOptions());
    }

    /**
     * خياراتُ مُنتقي «تابع لـ» في خطّ السير — كلُّ عقدةٍ بمسارها ونوعها.
     *
     * كانت القائمةُ شجرةَ `users.branch_id` للموظف وحده: من فرعُه «الفرع
     * الرئيسي» (عقدةٌ لها ابنٌ واحد) يجد خيارين، ولا يجد المنطقةَ ولا
     * المنفذَ الذي يتبع له عملاؤه. صارت قائمةَ «تابع لـ» نفسَها
     * ({@see AffiliationTree::pathOptions()}) مقصورةً بقاعدة المنسدلات
     * ({@see DataScope::selectableBranchIds()}): بلا قيدٍ ⇒ كلُّ العقد،
     * نطاقٌ مكانيٌّ ⇒ فروعُه، مندوبٌ ⇒ شجرةُ فرعه.
     *
     * `parent_id` و`display_type` لقوائم المنطقة/الفرع/المجموعة/المنفذ
     * المتسلسلة في شاشة تخطيط المسار.
     *
     * @return Collection<int, object{id:int, name:string, tree_label:string, parent_id:?int, display_type:string}>
     */
    private function itineraryNodeOptions(): Collection
    {
        $allowed = AffiliationTree::withOrganisationalChildren(DataScope::selectableBranchIds());
        $header = $this->headerNodeIds();

        // المسارُ يُبنى من العقد كلِّها ثمّ يُقصّ — كي لا ينقطع اسمُ الأب
        $all = Branch::withoutGlobalScopes()->get(['id', 'name', 'parent_id', 'node_type', 'is_territory']);
        $parents = $all->pluck('parent_id', 'id');

        return AffiliationTree::pathOptions($all)
            ->filter(fn (array $option) => ($allowed === null || in_array($option['id'], $allowed))
                && ($header === null || in_array($option['id'], $header)))
            ->map(fn (array $option) => (object) [
                'id' => $option['id'],
                'name' => $option['name'],
                'tree_label' => $option['label'],
                'parent_id' => $parents[$option['id']] ? (int) $parents[$option['id']] : null,
                'display_type' => $option['display_type'],
            ])
            ->values();
    }

    /**
     * كل الفروع لقوائم فلترة القائمة/اللوحة (لا نطاق مستخدمٍ هنا،
     * بخلاف `itineraryNodeOptions()`) — بنفس معاملة `tree_label`
     * (الأب ← الابن ← الابن) كي لا يلتبس فرعان بنفس الاسم في قائمةٍ مسطَّحة.
     */
    private function branchesWithTreeLabels()
    {
        $branches = Branch::select('id', 'name', 'code', 'is_main', 'parent_id')->get();

        $branches->each(fn (Branch $branch) => $branch->setAttribute('tree_label', $branch->lineagePathLabel(' ← ')));

        return $branches->sortBy(fn (Branch $b) => (string) $b->getAttribute('tree_label'))->values();
    }

    public function getClientsForGroup($id)
    {
        $branch = Branch::withoutGlobalScopes()->find($id);
        $allowed = AffiliationTree::withOrganisationalChildren(DataScope::selectableBranchIds());

        // عقدةٌ لا يعرضها المُنتقي لهذا المستخدم لا تُفتح بتعديل الرابط
        if (! $branch || ($allowed !== null && ! in_array((int) $branch->id, $allowed))) {
            return response()->json([]);
        }

        $clients = Client::with([
            'visits' => function ($query) {
                $query->latest()->limit(1);
            },
            'invoices' => function ($query) {
                $query->latest()->limit(1);
            },
            'appointmentNotes' => function ($query) {
                $query->latest()->limit(1);
            },
            'account.receipts' => function ($query) {
                $query->latest()->limit(1);
            },
        ])
            /* «تابع لـ» (`tree_node_id`) أوّلاً ثمّ الأعمدةُ القديمة لمن لا تبعيّةَ
               له — كانت المطابقةُ على `branch_id` والحيّ وحدَهما، فعميلٌ نُسب
               إلى منطقةٍ أو منفذ لا يظهر تحتها. */
            ->inTreeNode((int) $branch->id)
            ->get(['id', 'trade_name', 'code', 'city', 'branch_id']);

        return response()->json($clients);
    }

    /**
     * الفروع الفعّالة لعرض بيانات خط السير — الفرع المطلوب صراحةً، أو
     * الفرع الرئيسي فكل الفروع، أو فرع المستخدم الحالي وفروعه الفرعية.
     *
     * مستخرجةٌ من `listAll()` لتُستعمَل أيضاً من `dashboard()` دون تكرار.
     *
     * @return array<int>
     */
    private function resolveEffectiveBranchIds(?int $branchId): array
    {
        $currentUserBranch = auth()->user()->branch_id;

        $isMainBranch = false;
        if ($currentUserBranch) {
            $userBranch = Branch::find($currentUserBranch);
            $isMainBranch = $userBranch && $userBranch->is_main;
        }

        $selectedBranchIsMain = false;
        if ($branchId) {
            $selectedBranch = Branch::find($branchId);
            $selectedBranchIsMain = $selectedBranch && $selectedBranch->is_main;
        }

        if ($selectedBranchIsMain) {
            $effectiveBranchId = null;
        } elseif ($isMainBranch || ! $currentUserBranch) {
            $effectiveBranchId = $branchId;
        } else {
            $effectiveBranchId = $branchId ?? $currentUserBranch;
        }

        if (! $effectiveBranchId) {
            return [];
        }

        $effectiveBranch = Branch::find($effectiveBranchId);
        if (! $effectiveBranch) {
            return [(int) $effectiveBranchId];
        }

        return $effectiveBranch->descendantBranchIds()->push((int) $effectiveBranchId)->unique()->values()->all();
    }

    /**
     * زيارةٌ مكتملة: دورةُ الحياة `completed`، والعمودُ القديم `status = active`
     * **فقط** لصفٍّ بلا `visit_status` (قديمٌ سبق دورة الحياة) — كـ
     * `DailyItineraryService::stateOf()`. كان `active` وحده يكفي، فصفُّ «سأعود»
     * (active + planned) وما أُجِّل منه يظهر «مكتملة» في يومٍ لم يُزَر فيه.
     */
    private const VISIT_COMPLETED_SQL = "(visit_status = 'completed' OR (visit_status IS NULL AND status = 'active'))";

    private static function visitIsCompleted(EmployeeClientVisit $visit): bool
    {
        return $visit->visit_status === VisitStatus::COMPLETED
            || ($visit->visit_status === null && $visit->status === 'active');
    }

    /**
     * عرضُ خطوط السير — أسابيعُ ومندوبون وأيام، بتصميم شاشة التخطيط نفسه.
     *
     * **مَن يرى مَن** ({@see itineraryListQuery()}): نطاقُ الدور
     * (`DataScope::documentUserIds()` — المندوبُ نفسُه، والمشرفُ فريقُه أو فرعُه،
     * وبلا قيدٍ الكلّ)، ثمّ فرعُ الهيدر، ثمّ «أقسام الموظفين المسموح تعيينهم
     * للعملاء». القاعدةُ نفسُها في البطاقات والأسابيع والجدول و{@see dayVisits()}.
     * كانت الشاشةُ تعرض خطوطَ الجميع لكل من يفتحها، وشرحُها يقول غير ذلك.
     *
     * **الأسابيع بحساب النظام** (السبت أوّلاً، `ItineraryWeekService::dateFor()`)
     * لا `setISODate()`: التقويمُ الدوليّ يبدأ أسبوعَه الأوّل من أوّل خميس، فكانت
     * تواريخُ أسابيع 2027 كلُّها متأخّرةً أسبوعاً عمّا تحفظه شاشةُ التخطيط.
     *
     * **الجهة** تُطابَق بـ«تابع لـ» (`Client::inTreeNode()`) لا بـ`clients.branch_id`.
     */
    public function listAll(Request $request)
    {
        if ($block = $this->sectionBlock('itinerary')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $service = app(ItineraryWeekService::class);
        $filters = $this->itineraryListFilters($request);
        $completed = self::VISIT_COMPLETED_SQL;
        $today = today()->toDateString();

        // البطاقات «الآن» — لا يقصرها مدى التاريخ، استعلامٌ واحد لا سبعة
        $statsRow = $this->itineraryListQuery($filters, false)
            ->selectRaw("
                SUM(visit_date = ?) AS today,
                SUM(visit_date > ? AND visit_status = 'planned') AS upcoming,
                SUM(visit_date < ? AND visit_status = 'planned') AS overdue,
                SUM({$completed}) AS completed,
                SUM(visit_status = 'started') AS in_progress,
                SUM(visit_status = 'cancelled') AS cancelled
            ", [$today, $today, $today])
            ->first();
        $stats = collect(['today', 'upcoming', 'overdue', 'completed', 'in_progress', 'cancelled'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($statsRow->{$key} ?? 0)])
            ->all();

        $weeks = $this->itineraryListQuery($filters)
            ->selectRaw("year, week_number, COUNT(DISTINCT employee_id) AS reps, COUNT(*) AS visits, SUM({$completed}) AS completed")
            ->groupBy('year', 'week_number')
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit($filters['date_from'] || $filters['date_to'] ? 53 : 12)
            ->get()
            ->map(fn ($row) => $this->itineraryWeekCard($service, (int) $row->year, (int) $row->week_number, (int) $row->visits, (int) $row->completed, (int) $row->reps));

        // الأسبوعُ الحاليّ حاضرٌ دوماً — ولو بلا زيارات — ما لم يُطلب مدى تاريخ
        $current = $service->partsFor(today());
        $currentKey = sprintf('%d-%02d', $current['year'], $current['week_number']);
        if (! $filters['date_from'] && ! $filters['date_to'] && ! $weeks->contains('key', $currentKey)) {
            $weeks->push($this->itineraryWeekCard($service, $current['year'], $current['week_number'], 0, 0, 0));
        }

        $selected = $this->resolveItineraryWeek($weeks, $filters, (string) $request->input('week'), $currentKey, $service);
        if ($selected && ! $weeks->contains('key', $selected['key'])) {
            $weeks->push($selected);
        }
        $weeks = $weeks->sortByDesc('key')->values();

        $rows = $selected
            ? $this->itineraryListQuery($filters)
                ->where('year', $selected['year'])
                ->where('week_number', $selected['week'])
                ->selectRaw("employee_id, LOWER(day_of_week) AS day, COUNT(DISTINCT client_id) AS visits, COUNT(DISTINCT CASE WHEN {$completed} THEN client_id END) AS completed")
                ->groupBy('employee_id', DB::raw('LOWER(day_of_week)'))
                ->get()
            : collect();

        return view('client::Itinerary.list', [
            'stats' => $stats,
            'weeks' => $weeks,
            'selectedWeek' => $selected,
            'currentWeek' => ['year' => $current['year'], 'week' => $current['week_number']],
            'reps' => $this->itineraryRepRows($rows),
            'filters' => $filters,
            'nodes' => $this->itineraryNodeOptions(),
            'employees' => $this->itineraryEmployeeOptions()[0],
            'days' => self::DAYS,
            'headerBranch' => ($headerNode = HeaderBranchScope::nodeId()) ? Branch::findCached($headerNode)?->name : null,
            'mapsKey' => (string) config('services.google_maps.key'),
            'canEdit' => hasPermission('clients_itinerary_edit'),
            'canAdd' => hasPermission('clients_itinerary_add'),
        ]);
    }

    /**
     * زياراتُ مندوبٍ في يومٍ من أسبوع — للوحة اليوم في شاشة العرض.
     *
     * بنفس نطاق الشاشة وتصفيتها، فلا تعرض اللوحةُ ما لا يعدّه الجدول. العميلُ
     * المكرَّر في اليوم صفٌّ واحد يحمل معرّفات زياراته كلّها (`visit_ids`)، كي
     * يحذفها الحذفُ جميعاً لا الأولى وحدها.
     */
    public function dayVisits(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'year' => 'required|integer|min:2020|max:2100',
            'week' => 'required|integer|min:1|max:53',
            'day' => 'required|in:'.implode(',', ItineraryWeekService::DAYS),
        ]);

        $employeeId = (int) $data['employee_id'];
        $this->assertCanAccessEmployeeItinerary($employeeId);

        $service = app(ItineraryWeekService::class);
        $visits = $this->itineraryListQuery($this->itineraryListFilters($request))
            ->where('employee_id', $employeeId)
            ->where('year', (int) $data['year'])
            ->where('week_number', (int) $data['week'])
            ->where('day_of_week', $data['day'])
            ->orderByRaw('sequence IS NULL')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get(['id', 'client_id', 'status', 'visit_status', 'is_moved', 'moved_from_day', 'added_by']);

        $described = $service->describeClients($visits->pluck('client_id')->all());
        $sources = app(\App\Services\Itinerary\DailyItineraryService::class)->sourceBadges($visits, $employeeId);

        $payload = $visits->groupBy('client_id')->map(function ($group, $clientId) use ($described, $sources) {
            $first = $group->first();
            $done = $group->contains(fn ($visit) => self::visitIsCompleted($visit));
            $client = $described[(int) $clientId] ?? [
                'id' => (int) $clientId, 'name' => 'عميل محذوف', 'code' => '', 'status' => null,
                'region' => null, 'branch' => null, 'unit' => null, 'lat' => null, 'lng' => null, 'last_visit' => null,
            ];

            return $client + [
                'visit_ids' => $group->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'visit_status' => $first->visit_status?->value,
                'completed' => $done,
                'moved_from' => $first->is_moved ? ItineraryWeekService::dayName(strtolower((string) $first->moved_from_day)) : null,
                'source' => $sources[(int) $clientId] ?? null,
                'tone' => \App\Services\Itinerary\DailyItineraryService::visitTone(isset($sources[(int) $clientId]), $done),
            ];
        })->values();

        return response()->json([
            'employee' => User::whereKey($employeeId)->value('name'),
            'date' => $service->dateFor((int) $data['year'], (int) $data['week'], $data['day']),
            'locked' => $service->isLocked($employeeId, (int) $data['year'], (int) $data['week'], $data['day']),
            'visits' => $payload,
        ]);
    }

    /**
     * @return array{node_id:?int, employee_id:?int, date_from:?Carbon, date_to:?Carbon}
     */
    private function itineraryListFilters(Request $request): array
    {
        $nodeId = $request->integer('node_id') ?: null;
        $allowed = AffiliationTree::withOrganisationalChildren(DataScope::selectableBranchIds());
        $header = $this->headerNodeIds();

        // جهةٌ لا يعرضها المُنتقي لهذا المستخدم تُهمَل بصمت — لا تفتح ما وراء نطاقه
        if ($nodeId && (($allowed !== null && ! in_array($nodeId, $allowed)) || ($header !== null && ! in_array($nodeId, $header)))) {
            $nodeId = null;
        }

        $date = function ($value) {
            try {
                return $value ? Carbon::parse((string) $value)->startOfDay() : null;
            } catch (\Throwable) {
                return null;
            }
        };

        return [
            'node_id' => $nodeId,
            'employee_id' => $request->integer('employee_id') ?: null,
            'date_from' => $date($request->input('date_from')),
            'date_to' => $date($request->input('date_to')),
        ];
    }

    /** زياراتُ خطوط السير التي يراها المستخدم الحالي، بتصفية الشاشة */
    private function itineraryListQuery(array $filters, bool $withDates = true)
    {
        $query = EmployeeClientVisit::query();
        $selfOnly = $this->isItinerarySelfOnly();

        if (($scope = DataScope::documentUserIds()) !== null) {
            $query->whereIn('employee_id', $scope ?: [0]);
        }

        if (! $selfOnly && ($header = $this->headerNodeIds())) {
            $query->whereIn('employee_id', $this->constrainUsersToNodes(User::query(), $header)->select('id'));
        }

        if (! $selfOnly && ClientSettingsHelper::clientEmployeeDepartmentIds() !== []) {
            $query->whereIn('employee_id', $this->constrainToClientDepartments(User::query())->select('id'));
        }

        if ($filters['employee_id']) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if ($filters['node_id']) {
            $query->whereIn('client_id', Client::withoutGlobalScopes()->inTreeNode($filters['node_id'])->select('id'));
        }

        if ($withDates && $filters['date_from']) {
            // `where` لا `whereDate`: العمودُ DATE، و`date()` حوله يُسقط الفهرس
            $query->where('visit_date', '>=', $filters['date_from']->toDateString());
        }

        if ($withDates && $filters['date_to']) {
            $query->where('visit_date', '<=', $filters['date_to']->toDateString());
        }

        return $query;
    }

    private function resolveItineraryWeek($weeks, array $filters, string $requested, string $currentKey, ItineraryWeekService $service): ?array
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $requested, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 53) {
            $key = sprintf('%d-%02d', (int) $m[1], (int) $m[2]);

            if ($found = $weeks->firstWhere('key', $key)) {
                return $found;
            }

            $row = $this->itineraryListQuery($filters)
                ->where('year', (int) $m[1])
                ->where('week_number', (int) $m[2])
                ->selectRaw('COUNT(DISTINCT employee_id) AS reps, COUNT(*) AS visits, SUM('.self::VISIT_COMPLETED_SQL.') AS completed')
                ->first();

            return $this->itineraryWeekCard($service, (int) $m[1], (int) $m[2], (int) ($row->visits ?? 0), (int) ($row->completed ?? 0), (int) ($row->reps ?? 0));
        }

        return $weeks->firstWhere('key', $currentKey) ?? $weeks->first();
    }

    private function itineraryWeekCard(ItineraryWeekService $service, int $year, int $week, int $visits, int $completed, int $reps): array
    {
        $start = Carbon::parse($service->dateFor($year, $week, 'saturday'));

        return [
            'key' => sprintf('%d-%02d', $year, $week),
            'year' => $year,
            'week' => $week,
            'label' => $start->format('j/n').' – '.$start->copy()->addDays(6)->format('j/n'),
            'dates' => collect(ItineraryWeekService::DAYS)->mapWithKeys(fn ($day, $offset) => [$day => $start->copy()->addDays($offset)->toDateString()])->all(),
            'visits' => $visits,
            'completed' => $completed,
            'reps' => $reps,
            'rate' => $visits ? (int) round($completed * 100 / $visits) : 0,
        ];
    }

    /**
     * صفوفُ جدول المندوبين: لكل مندوبٍ عددُ عملاء كل يوم ومكتملُها.
     *
     * فرعُ المندوب من ملفّ الموظف (`employees.branch_id`) ثمّ `users.branch_id` —
     * الثاني يكتبه مُبدِّلُ الهيدر فيتغيّر كلّما بدّل المندوبُ فرعه.
     */
    private function itineraryRepRows($rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::whereIn('id', $rows->pluck('employee_id')->unique())->get(['id', 'name', 'branch_id', 'employee_id'])->keyBy('id');
        $employeeBranches = \App\Models\Employee::whereIn('id', $users->pluck('employee_id')->filter())->pluck('branch_id', 'id');

        return $rows->groupBy('employee_id')->map(function ($days, $employeeId) use ($users, $employeeBranches) {
            $user = $users->get($employeeId);
            $perDay = array_fill_keys(ItineraryWeekService::DAYS, ['visits' => 0, 'completed' => 0]);

            foreach ($days as $day) {
                if (isset($perDay[$day->day])) {
                    $perDay[$day->day] = ['visits' => (int) $day->visits, 'completed' => (int) $day->completed];
                }
            }

            $visits = array_sum(array_column($perDay, 'visits'));
            $completed = array_sum(array_column($perDay, 'completed'));
            $branchId = ($user ? $employeeBranches->get($user->employee_id) : null) ?: $user?->branch_id;

            return [
                'id' => (int) $employeeId,
                'name' => $user?->name ?? ('#'.$employeeId),
                'branch' => $branchId ? Branch::findCached((int) $branchId)?->name : null,
                'days' => $perDay,
                'visits' => $visits,
                'completed' => $completed,
                'rate' => $visits ? (int) round($completed * 100 / $visits) : 0,
            ];
        })->sortBy('name')->values()->all();
    }

    /**
     * لوحة تحكّم خط السير — بتصميم شاشتَي التخطيط والعرض وقاعدتهما.
     *
     * **النطاق والتصفية** من {@see itineraryListQuery()} نفسها: الدور وفرع الهيدر
     * والأقسام و«تابع لـ». كانت اللوحة ترى خطوطَ الجميع وتطابق الفرع بـ`clients.branch_id`.
     *
     * **الفترة** افتراضاً الأسبوعُ الحاليّ بحساب النظام (السبت–الجمعة).
     *
     * **ما صُحّح في الأرقام**:
     *   • الدائرة كانت تخلط «مكتملة اليوم» بـ«متأخرة/فائتة/ملغاة الفترة». صارت
     *     حالاتُ الفترة ستّاً متنافية (مكتملة، جارية، قادمة، متأخرة، فائتة، ملغاة)
     *     مجموعُها إجمالي الزيارات.
     *   • «المكتملة» لم تكن تعدّ العمودَ القديم `status = active`.
     *   • «عملاء بلا زيارة» كانوا كلَّ عميلٍ له موظفٌ في المنشأة — صاروا عملاءَ
     *     المندوبين المعروضين وحدهم، داخل الجهة المختارة.
     */
    public function dashboard(Request $request)
    {
        if ($block = $this->sectionBlock('itinerary')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $service = app(ItineraryWeekService::class);
        $filters = $this->itineraryDashboardFilters($request, $service);
        $completed = self::VISIT_COMPLETED_SQL;
        $open = "visit_status = 'planned' AND NOT {$completed}";
        $today = today()->toDateString();
        $asInts = fn ($row, array $keys) => collect($keys)->mapWithKeys(fn ($key) => [$key => (int) ($row->{$key} ?? 0)])->all();

        $kpi = $asInts($this->itineraryListQuery($filters)
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(DISTINCT employee_id) AS reps,
                COUNT(DISTINCT client_id) AS clients,
                SUM({$completed}) AS completed,
                SUM(visit_status = 'started' AND NOT {$completed}) AS started,
                SUM(visit_status = 'missed' AND NOT {$completed}) AS missed,
                SUM(visit_status = 'cancelled' AND NOT {$completed}) AS cancelled,
                SUM({$open} AND visit_date < ?) AS overdue,
                SUM({$open} AND visit_date >= ?) AS upcoming
            ", [$today, $today])
            ->first(), ['total', 'reps', 'clients', 'completed', 'started', 'missed', 'cancelled', 'overdue', 'upcoming']);

        $base = $kpi['total'] - $kpi['cancelled'];
        $kpi['rate'] = $base > 0 ? round($kpi['completed'] * 100 / $base, 1) : 0;

        // زياراتُ اليوم «الآن» — لا يقصرها مدى الفترة
        $kpi += collect($asInts($this->itineraryListQuery($filters, false)
            ->where('visit_date', $today)
            ->selectRaw("COUNT(*) AS total, SUM({$completed}) AS completed, SUM(visit_status = 'started' AND NOT {$completed}) AS started")
            ->first(), ['total', 'completed', 'started']))
            ->mapWithKeys(fn ($value, $key) => ['today_'.$key => $value])
            ->all();

        $byDay = $this->itineraryListQuery($filters)
            ->selectRaw("visit_date AS day, COUNT(*) AS total, SUM({$completed}) AS completed")
            ->groupBy('visit_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $trend = [];
        for ($day = $filters['date_from']->copy(); $day->lte($filters['date_to']); $day->addDay()) {
            $key = $day->toDateString();
            $trend[] = [
                'label' => $day->format('j/n'),
                'total' => (int) ($byDay[$key]->total ?? 0),
                'completed' => (int) ($byDay[$key]->completed ?? 0),
            ];
        }

        $repRows = $this->itineraryListQuery($filters)
            ->selectRaw("employee_id, COUNT(*) AS total, SUM({$completed}) AS completed,
                SUM(visit_status = 'started' AND NOT {$completed}) AS started,
                SUM(visit_status = 'missed' AND NOT {$completed}) AS missed,
                SUM(visit_status = 'cancelled' AND NOT {$completed}) AS cancelled,
                SUM({$open} AND visit_date < ?) AS overdue,
                SUM({$open} AND visit_date >= ?) AS upcoming", [$today, $today])
            ->groupBy('employee_id')
            ->get();

        $todayVisits = $this->itineraryListQuery($filters, false)
            ->where('visit_date', $today)
            ->orderBy('employee_id')
            ->orderByRaw('sequence IS NULL')
            ->orderBy('sequence')
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'employee_id', 'client_id', 'status', 'visit_status', 'started_at', 'completed_at']);

        $topRows = $this->itineraryListQuery($filters)
            ->whereRaw($completed)
            ->selectRaw('client_id, COUNT(*) AS visits')
            ->groupBy('client_id')
            ->orderByDesc('visits')
            ->limit(10)
            ->get();

        // عملاءُ مُسنَدون لمندوبي اللوحة بلا أيّ زيارةٍ في الفترة
        [$repOptions] = $this->itineraryEmployeeOptions();
        $repUserIds = $repOptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($filters['employee_id']) {
            $repUserIds = in_array($filters['employee_id'], $repUserIds, true) ? [$filters['employee_id']] : [];
        }
        $employeeUsers = User::whereIn('id', $repUserIds ?: [0])->whereNotNull('employee_id')->pluck('id', 'employee_id');

        $assignments = ClientEmployee::query()
            ->whereIn('employee_id', $employeeUsers->keys()->all() ?: [0])
            ->whereNotIn('client_id', $this->itineraryListQuery($filters)->select('client_id'));

        if ($filters['node_id']) {
            $assignments->whereIn('client_id', Client::withoutGlobalScopes()->inTreeNode($filters['node_id'])->select('id'));
        } elseif (! $this->isItinerarySelfOnly() && ($header = $this->headerNodeIds())) {
            $inHeader = Client::withoutGlobalScopes()->select('id');
            AffiliationTree::constrainClients($inHeader, $header, Schema::hasColumn('clients', 'group_id'));
            $assignments->whereIn('client_id', $inHeader);
        }

        $unscheduledTotal = (clone $assignments)->distinct()->count('client_id');
        $unscheduledRows = $assignments->selectRaw('client_id, MIN(employee_id) AS employee_id')
            ->groupBy('client_id')
            ->orderBy('client_id')
            ->limit(15)
            ->get();

        $identities = $this->itineraryRepIdentities(array_merge(
            $repRows->pluck('employee_id')->all(),
            $todayVisits->pluck('employee_id')->all(),
            $employeeUsers->values()->all()
        ));
        $described = $service->describeClients(array_merge(
            $todayVisits->pluck('client_id')->all(),
            $topRows->pluck('client_id')->all(),
            $unscheduledRows->pluck('client_id')->all()
        ));

        $reps = $repRows->map(function ($row) use ($identities) {
            $id = (int) $row->employee_id;
            $base = max(0, (int) $row->total - (int) $row->cancelled);

            return [
                'id' => $id,
                'name' => $identities[$id]['name'] ?? ('#'.$id),
                'branch' => $identities[$id]['branch'] ?? null,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'started' => (int) $row->started,
                'upcoming' => (int) $row->upcoming,
                'overdue' => (int) $row->overdue,
                'missed' => (int) $row->missed,
                'cancelled' => (int) $row->cancelled,
                'rate' => $base ? round($row->completed * 100 / $base, 1) : 0,
            ];
        })->sortBy([['rate', 'desc'], ['total', 'desc']])->values()->all();

        $todayGroups = $todayVisits->groupBy('employee_id')->map(function ($visits, $employeeId) use ($identities, $described) {
            $items = $visits->unique('client_id')->map(function ($visit) use ($described) {
                $done = self::visitIsCompleted($visit);

                return [
                    'name' => $described[(int) $visit->client_id]['name'] ?? 'عميل محذوف',
                    'state' => $done ? 'completed' : ($visit->visit_status?->value ?? 'planned'),
                    'time' => ($done ? $visit->completed_at : $visit->started_at)?->format('H:i'),
                ];
            })->values();

            return [
                'id' => (int) $employeeId,
                'name' => $identities[(int) $employeeId]['name'] ?? ('#'.$employeeId),
                'total' => $items->count(),
                'completed' => $items->where('state', 'completed')->count(),
                'visits' => $items->all(),
            ];
        })->sortByDesc('total')->values()->all();

        $topClients = $topRows
            ->map(fn ($row) => isset($described[(int) $row->client_id]) ? $described[(int) $row->client_id] + ['visits' => (int) $row->visits] : null)
            ->filter()
            ->values()
            ->all();

        $unscheduled = $unscheduledRows
            ->map(function ($row) use ($described, $employeeUsers, $identities) {
                $client = $described[(int) $row->client_id] ?? null;
                $repId = (int) ($employeeUsers[(int) $row->employee_id] ?? 0);

                return $client ? $client + ['rep_id' => $repId, 'rep' => $identities[$repId]['name'] ?? null] : null;
            })
            ->filter()
            ->values()
            ->all();

        return view('client::Itinerary.dashboard', [
            'kpi' => $kpi,
            'trend' => $trend,
            'reps' => $reps,
            'todayGroups' => $todayGroups,
            'topClients' => $topClients,
            'unscheduled' => $unscheduled,
            'unscheduledTotal' => $unscheduledTotal,
            'filters' => $filters,
            'ranges' => $this->itineraryQuickRanges($service),
            'nodes' => $this->itineraryNodeOptions(),
            'employees' => $repOptions,
            'headerBranch' => ($headerNode = HeaderBranchScope::nodeId()) ? Branch::findCached($headerNode)?->name : null,
            'canAdd' => hasPermission('clients_itinerary_add'),
        ]);
    }

    /** تصفيةُ اللوحة: تصفيةُ شاشة العرض، وفترةٌ افتراضها الأسبوعُ الحاليّ وسقفها سنة */
    private function itineraryDashboardFilters(Request $request, ItineraryWeekService $service): array
    {
        $filters = $this->itineraryListFilters($request);

        if (! $filters['date_from'] && ! $filters['date_to']) {
            $current = $service->partsFor(today());
            $filters['date_from'] = Carbon::parse($service->dateFor($current['year'], $current['week_number'], 'saturday'));
        }

        $filters['date_from'] ??= $filters['date_to']->copy()->subDays(6);
        $filters['date_to'] ??= $filters['date_from']->copy()->addDays(6);

        if ($filters['date_to']->lt($filters['date_from'])) {
            [$filters['date_from'], $filters['date_to']] = [$filters['date_to'], $filters['date_from']];
        }

        if ($filters['date_from']->diffInDays($filters['date_to']) > 366) {
            $filters['date_to'] = $filters['date_from']->copy()->addDays(366);
        }

        return $filters;
    }

    /** @return array<int, array{label:string, from:string, to:string}> */
    private function itineraryQuickRanges(ItineraryWeekService $service): array
    {
        $current = $service->partsFor(today());
        $weekStart = Carbon::parse($service->dateFor($current['year'], $current['week_number'], 'saturday'));

        return collect([
            ['هذا الأسبوع', $weekStart, $weekStart->copy()->addDays(6)],
            ['الأسبوع الماضي', $weekStart->copy()->subWeek(), $weekStart->copy()->subDay()],
            ['هذا الشهر', today()->startOfMonth(), today()->endOfMonth()],
            ['آخر 30 يوماً', today()->subDays(29), today()],
        ])->map(fn ($range) => ['label' => $range[0], 'from' => $range[1]->toDateString(), 'to' => $range[2]->toDateString()])->all();
    }

    /**
     * اسمُ المندوب وفرعُه — فرعُ ملفّ الموظف أوّلاً، ثمّ `users.branch_id` الذي
     * يكتبه مُبدِّلُ الهيدر.
     *
     * @return array<int, array{name:string, branch:?string}>
     */
    private function itineraryRepIdentities(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if ($userIds === []) {
            return [];
        }

        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'branch_id', 'employee_id']);
        $employeeBranches = \App\Models\Employee::whereIn('id', $users->pluck('employee_id')->filter())->pluck('branch_id', 'id');

        return $users->mapWithKeys(function (User $user) use ($employeeBranches) {
            $branchId = $employeeBranches->get($user->employee_id) ?: $user->branch_id;

            return [(int) $user->id => [
                'name' => $user->name,
                'branch' => $branchId ? Branch::findCached((int) $branchId)?->name : null,
            ]];
        })->all();
    }

    /**
     * إضافة عميل إلى خط السير من صفحة إدارة العملاء (CRM)
     */
    public function addClientToItinerary(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|exists:clients,id',
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:users,id',
                'year' => 'required|integer|min:2020|max:2030',
                'week_number' => 'required|integer|min:1|max:53',
                'day_of_week' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            ]);

            $clientId = $request->input('client_id');
            $employeeIds = $request->input('employee_ids');
            $year = $request->input('year');
            $weekNumber = $request->input('week_number');
            $dayOfWeek = strtolower($request->input('day_of_week'));

            $addedCount = 0;
            $skippedCount = 0;
            $unassignedCount = 0;
            $outOfScopeCount = 0;

            // لا يُضاف عميلٌ إلا لخط سير من يقع في نطاق المستخدم — كانت
            // `employee_ids` تُقبل كما هي من الطلب، فأيّ موظفٍ يحمل
            // `clients_itinerary_add` يُدرج زياراتٍ في خط سير زميلٍ آخر.
            $employeeIds = $this->filterManageableEmployees($employeeIds, $outOfScopeCount);

            $notEligibleCount = 0;
            $employeeIds = $this->filterClientEligibleEmployees($employeeIds, (int) $clientId, $notEligibleCount);

            if (ClientSettingsHelper::shouldRestrictItineraryToAssignedClients()) {
                $employeeIds = array_values(array_filter($employeeIds, function ($employeeId) use ($clientId, &$unassignedCount) {
                    $assigned = $this->isClientAssignedToEmployee((int) $clientId, (int) $employeeId);
                    if (! $assigned) {
                        $unassignedCount++;
                    }

                    return $assigned;
                }));
            }

            $fullCount = 0;

            foreach ($employeeIds as $employeeId) {
                // التحقق من وجود الزيارة بالفعل
                $existingVisit = EmployeeClientVisit::where([
                    'employee_id' => $employeeId,
                    'client_id' => $clientId,
                    'year' => $year,
                    'week_number' => $weekNumber,
                    'day_of_week' => $dayOfWeek,
                ])->first();

                if ($existingVisit) {
                    $skippedCount++;

                    continue;
                }

                // سقفُ خطّ سير اليوم من الإعدادات — الخطّ الممتلئ لا يقبل
                // مزيداً، وإلّا أُسنِد لمندوبٍ ما لا يسع يومُه
                if ($this->dayIsFull((int) $employeeId, (int) $year, (int) $weekNumber, $dayOfWeek)) {
                    $fullCount++;

                    continue;
                }

                // ✅ حساب التاريخ الفعلي للزيارة
                $visitDate = $this->calculateVisitDate($year, $weekNumber, $dayOfWeek);

                // إضافة الزيارة الجديدة
                $visit = EmployeeClientVisit::create([
                    'employee_id' => (int) $employeeId,
                    'client_id' => (int) $clientId,
                    'day_of_week' => $dayOfWeek,
                    'year' => (int) $year,
                    'week_number' => (int) $weekNumber,
                    'visit_date' => $visitDate,
                    'status' => 'unactive',
                ]);

                $addedCount++;

                OperationLog::record(
                    'visit_added',
                    self::LOG_MODULE,
                    $visit,
                    "إضافة العميل #{$clientId} إلى خط سير الموظف #{$employeeId} — {$dayOfWeek} أسبوع {$weekNumber}/{$year}",
                    ['new' => [
                        'client_id' => (int) $clientId,
                        'employee_id' => (int) $employeeId,
                        'day_of_week' => $dayOfWeek,
                        'week_number' => (int) $weekNumber,
                        'year' => (int) $year,
                    ]]
                );
            }

            $message = $addedCount > 0
                ? "تمت إضافة العميل لـ {$addedCount} موظفين بنجاح"
                : 'لم تتم إضافة العميل لأي موظف';
            if ($skippedCount > 0) {
                $message .= " (تم تخطي {$skippedCount} زيارة موجودة مسبقاً)";
            }
            if ($fullCount > 0) {
                $message .= " (تم تخطي {$fullCount} موظف بلغ خط سيره سقف اليوم)";
            }
            if ($unassignedCount > 0) {
                $message .= " (تم تجاهل {$unassignedCount} موظف غير مُسنَد إليه هذا العميل)";
            }
            if ($outOfScopeCount > 0) {
                $message .= " (تم تجاهل {$outOfScopeCount} موظف خارج نطاق صلاحيتك)";
            }
            if ($notEligibleCount > 0) {
                $message .= " (تم تجاهل {$notEligibleCount} اختيار لموظف ليس من قسم موظفي العملاء، أو غير تابع لعقدة العميل ولا مُسنَد إليه)";
            }

            return response()->json([
                'success' => $addedCount > 0 || $skippedCount > 0,
                'message' => $message,
                'added_count' => $addedCount,
                'skipped_count' => $skippedCount,
                'unassigned_count' => $unassignedCount,
                'out_of_scope_count' => $outOfScopeCount,
                'not_eligible_count' => $notEligibleCount,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'add_client_to_itinerary');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة العميل: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إضافة سريعة لعميل إلى خط السير (الأسبوع الحالي أو القادم)
     */
    public function quickAddToItinerary(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|exists:clients,id',
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:users,id',
                'days' => 'required|array|min:1',
                'days.*' => 'in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
                'week_type' => 'required|in:current,next',
            ]);

            $clientId = $request->input('client_id');
            $employeeIds = $request->input('employee_ids');
            $days = $request->input('days');
            $weekType = $request->input('week_type');

            // حساب السنة ورقم الأسبوع (Saturday-start week)
            $now = Carbon::now();
            if ($weekType === 'next') {
                $now = $now->addWeek();
            }
            $weekInfo = WeekHelper::getWeekAndYear($now);
            $year = $weekInfo['year'];
            $weekNumber = $weekInfo['week'];

            $addedCount = 0;
            $skippedCount = 0;
            $unassignedCount = 0;
            $fullCount = 0;
            $outOfScopeCount = 0;

            $employeeIds = $this->filterManageableEmployees($employeeIds, $outOfScopeCount);

            $notEligibleCount = 0;
            $employeeIds = $this->filterClientEligibleEmployees($employeeIds, (int) $clientId, $notEligibleCount);

            if (ClientSettingsHelper::shouldRestrictItineraryToAssignedClients()) {
                $employeeIds = array_values(array_filter($employeeIds, function ($employeeId) use ($clientId, &$unassignedCount) {
                    $assigned = $this->isClientAssignedToEmployee((int) $clientId, (int) $employeeId);
                    if (! $assigned) {
                        $unassignedCount++;
                    }

                    return $assigned;
                }));
            }

            foreach ($employeeIds as $employeeId) {
                foreach ($days as $day) {
                    $dayOfWeek = strtolower($day);

                    // التحقق من وجود الزيارة بالفعل
                    $existingVisit = EmployeeClientVisit::where([
                        'employee_id' => $employeeId,
                        'client_id' => $clientId,
                        'year' => $year,
                        'week_number' => $weekNumber,
                        'day_of_week' => $dayOfWeek,
                    ])->first();

                    if ($existingVisit) {
                        $skippedCount++;

                        continue;
                    }

                    // سقفُ خطّ سير اليوم من الإعدادات
                    if ($this->dayIsFull((int) $employeeId, (int) $year, (int) $weekNumber, $dayOfWeek)) {
                        $fullCount++;

                        continue;
                    }

                    // حساب التاريخ الفعلي للزيارة
                    $visitDate = $this->calculateVisitDate($year, $weekNumber, $dayOfWeek);

                    // إضافة الزيارة الجديدة
                    EmployeeClientVisit::create([
                        'employee_id' => (int) $employeeId,
                        'client_id' => (int) $clientId,
                        'day_of_week' => $dayOfWeek,
                        'year' => (int) $year,
                        'week_number' => (int) $weekNumber,
                        'visit_date' => $visitDate,
                        'status' => 'unactive',
                    ]);

                    $addedCount++;
                }
            }

            $message = $addedCount > 0 ? "تمت إضافة {$addedCount} زيارة بنجاح" : 'لم تتم إضافة أي زيارة';
            if ($skippedCount > 0) {
                $message .= " (تم تخطي {$skippedCount} زيارة موجودة مسبقاً)";
            }
            if ($unassignedCount > 0) {
                $message .= " (تم تجاهل {$unassignedCount} موظف غير مُسنَد إليه هذا العميل)";
            }
            if ($fullCount > 0) {
                $message .= " (تم تخطي {$fullCount} يوم بلغ سقفه)";
            }
            if ($outOfScopeCount > 0) {
                $message .= " (تم تجاهل {$outOfScopeCount} موظف خارج نطاق صلاحيتك)";
            }
            if ($notEligibleCount > 0) {
                $message .= " (تم تجاهل {$notEligibleCount} اختيار لموظف ليس من قسم موظفي العملاء، أو غير تابع لعقدة العميل ولا مُسنَد إليه)";
            }

            if ($addedCount > 0) {
                OperationLog::record(
                    'visits_added',
                    self::LOG_MODULE,
                    null,
                    "إضافة سريعة: {$addedCount} زيارة للعميل #{$clientId} في أسبوع {$weekNumber}/{$year}",
                    ['new' => ['client_id' => (int) $clientId, 'employees' => $employeeIds, 'days' => $days]]
                );
            }

            return response()->json([
                'success' => $addedCount > 0 || $skippedCount > 0,
                'message' => $message,
                'added_count' => $addedCount,
                'skipped_count' => $skippedCount,
                'unassigned_count' => $unassignedCount,
                'full_count' => $fullCount,
                'out_of_scope_count' => $outOfScopeCount,
                'not_eligible_count' => $notEligibleCount,
            ]);

        } catch (ValidationException $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'quick_add_validation');

            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'quick_add_to_itinerary');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إضافة عدة عملاء إلى خط السير دفعة واحدة (Bulk Add)
     */
    public function bulkAddClientsToItinerary(Request $request)
    {
        try {
            $request->validate([
                'client_ids' => 'required|array|min:1',
                'client_ids.*' => 'exists:clients,id',
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:users,id',
                'weeks' => 'required|array|min:1',
                'weeks.*.year' => 'required|integer|min:2020|max:2030',
                'weeks.*.week_number' => 'required|integer|min:1|max:53',
                'days' => 'required|array|min:1',
                'days.*' => 'in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            ]);

            $clientIds = $request->input('client_ids');
            $employeeIds = $request->input('employee_ids');
            $weeks = $request->input('weeks');
            $days = $request->input('days');

            $addedCount = 0;
            $skippedCount = 0;
            $unassignedCount = 0;
            $fullCount = 0;
            $outOfScopeCount = 0;
            $restrictToAssigned = ClientSettingsHelper::shouldRestrictItineraryToAssignedClients();

            $employeeIds = $this->filterManageableEmployees($employeeIds, $outOfScopeCount);

            $notEligibleCount = 0;
            $candidates = app(ClientEmployeeCandidates::class);

            foreach ($employeeIds as $employeeId) {
                foreach ($clientIds as $clientId) {
                    if (! $candidates->isEligible((int) $employeeId, (int) $clientId)) {
                        $notEligibleCount++;

                        continue;
                    }

                    if ($restrictToAssigned && ! $this->isClientAssignedToEmployee((int) $clientId, (int) $employeeId)) {
                        $unassignedCount++;

                        continue;
                    }

                    foreach ($weeks as $weekData) {
                        $year = $weekData['year'];
                        $weekNumber = $weekData['week_number'];

                        foreach ($days as $day) {
                            $dayOfWeek = strtolower($day);

                            // التحقق من وجود الزيارة بالفعل
                            $existingVisit = EmployeeClientVisit::where([
                                'employee_id' => $employeeId,
                                'client_id' => $clientId,
                                'year' => $year,
                                'week_number' => $weekNumber,
                                'day_of_week' => $dayOfWeek,
                            ])->first();

                            if ($existingVisit) {
                                $skippedCount++;

                                continue;
                            }

                            // سقفُ خطّ سير اليوم من الإعدادات
                            if ($this->dayIsFull((int) $employeeId, (int) $year, (int) $weekNumber, $dayOfWeek)) {
                                $fullCount++;

                                continue;
                            }

                            // حساب التاريخ الفعلي للزيارة
                            $visitDate = $this->calculateVisitDate($year, $weekNumber, $dayOfWeek);

                            // إضافة الزيارة الجديدة
                            EmployeeClientVisit::create([
                                'employee_id' => (int) $employeeId,
                                'client_id' => (int) $clientId,
                                'day_of_week' => $dayOfWeek,
                                'year' => (int) $year,
                                'week_number' => (int) $weekNumber,
                                'visit_date' => $visitDate,
                                'status' => 'unactive',
                            ]);

                            $addedCount++;
                        }
                    }
                }
            }

            OperationLog::record(
                'visits_bulk_added',
                self::LOG_MODULE,
                null,
                "إضافة جماعية: {$addedCount} زيارة على ".count($employeeIds).' موظف و'.count($clientIds).' عميل',
                ['new' => [
                    'client_ids' => $clientIds,
                    'employee_ids' => $employeeIds,
                    'weeks' => $weeks,
                    'days' => $days,
                    'added' => $addedCount,
                    'skipped' => $skippedCount,
                ]]
            );

            $message = $addedCount > 0 ? "تمت إضافة {$addedCount} زيارة بنجاح" : 'لم تتم إضافة أي زيارة';
            if ($skippedCount > 0) {
                $message .= " (تم تخطي {$skippedCount} زيارة موجودة مسبقاً)";
            }
            if ($unassignedCount > 0) {
                $message .= " (تم تجاهل {$unassignedCount} إسناد موظف/عميل غير مُسنَد)";
            }
            if ($fullCount > 0) {
                $message .= " (تم تخطي {$fullCount} يوم بلغ سقفه)";
            }
            if ($outOfScopeCount > 0) {
                $message .= " (تم تجاهل {$outOfScopeCount} موظف خارج نطاق صلاحيتك)";
            }
            if ($notEligibleCount > 0) {
                $message .= " (تم تجاهل {$notEligibleCount} اختيار لموظف ليس من قسم موظفي العملاء، أو غير تابع لعقدة العميل ولا مُسنَد إليه)";
            }

            return response()->json([
                'success' => $addedCount > 0 || $skippedCount > 0,
                'message' => $message,
                'added_count' => $addedCount,
                'skipped_count' => $skippedCount,
                'unassigned_count' => $unassignedCount,
                'out_of_scope_count' => $outOfScopeCount,
                'not_eligible_count' => $notEligibleCount,
            ]);

        } catch (ValidationException $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'bulk_add_validation');

            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'bulk_add_to_itinerary');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة العملاء: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * نقل الزيارات غير المكتملة إلى يوم أو أسبوع آخر
     */
    /**
     * جلب الزيارات غير المكتملة لموظف في تاريخ محدد
     */
    public function getIncompleteVisitsForDay(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'source_date' => 'required|date',
        ]);

        $this->assertCanAccessEmployeeItinerary((int) $request->employee_id);

        $visits = EmployeeClientVisit::with(['client:id,trade_name,code'])
            ->where('employee_id', $request->employee_id)
            ->where('visit_date', $request->source_date)
            ->where('status', '!=', 'active')
            ->get(['id', 'client_id', 'status', 'visit_status', 'day_of_week']);

        return response()->json($visits);
    }

    public function moveIncompleteVisits(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'source_date' => 'required|date',
            'target_day' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'target_year' => 'nullable|integer|min:2020|max:2030',
            'target_week' => 'nullable|integer|min:1|max:53',
            'visit_ids' => 'nullable|array',
            'visit_ids.*' => 'integer|exists:employee_client_visits,id',
        ]);

        $employeeId = $request->input('employee_id');
        $this->assertCanAccessEmployeeItinerary((int) $employeeId, 'لا يمكنك نقل زيارات من خط سير موظف آخر.');

        try {
            $sourceDate = $request->input('source_date');
            $targetDay = strtolower($request->input('target_day'));
            $targetYear = $request->input('target_year');
            $targetWeek = $request->input('target_week');
            $visitIds = $request->input('visit_ids'); // null = move all

            // جلب الزيارات غير المكتملة في التاريخ المحدد — الملغاة عمداً
            // لا "تُنقل" كأنها غير مكتملة
            $query = EmployeeClientVisit::where('employee_id', $employeeId)
                ->where('visit_date', $sourceDate)
                ->where('status', '!=', 'active')
                ->where('visit_status', '!=', VisitStatus::CANCELLED);

            // إذا تم تحديد زيارات بعينها، نقتصر عليها فقط
            if (! empty($visitIds)) {
                $query->whereIn('id', $visitIds);
            }

            $visitsToMove = $query->get();

            if ($visitsToMove->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد زيارات غير مكتملة لنقلها في هذا اليوم.',
                ]);
            }

            $movedCount = 0;
            $skippedCount = 0;

            DB::beginTransaction();

            foreach ($visitsToMove as $visit) {
                // حفظ المعلومات الأصلية قبل النقل
                $originalDay = $visit->day_of_week;
                $originalWeek = $visit->week_number;
                $originalYear = $visit->year;

                // إذا لم يتم تحديد سنة أو أسبوع، استخدم السنة والأسبوع الحالية للزيارة
                $year = $targetYear ?? $visit->year;
                $weekNumber = $targetWeek ?? $visit->week_number;

                // حساب التاريخ الجديد بناءً على السنة ورقم الأسبوع لليوم المستهدف
                $newVisitDate = $this->calculateVisitDate($year, $weekNumber, $targetDay);

                // التحقق من عدم وجود نفس الزيارة في اليوم المستهدف
                $existingVisit = EmployeeClientVisit::where([
                    'employee_id' => $employeeId,
                    'client_id' => $visit->client_id,
                    'visit_date' => $newVisitDate,
                ])->first();

                if ($existingVisit) {
                    // إذا كانت موجودة بالفعل، نحذف الزيارة القديمة (لأنها كانت غير مكتملة ونحن "ننقلها")
                    $visit->delete();
                    $skippedCount++;

                    continue;
                }

                // تحديث الزيارة مع حفظ معلومات النقل — تعود «مخطَّطة» من جديد
                // في موعدها الجديد (أثر الترحيل يبقى عبر is_moved/moved_from_*)
                $visit->update([
                    'year' => $year,
                    'week_number' => $weekNumber,
                    'day_of_week' => $targetDay,
                    'visit_date' => $newVisitDate,
                    'visit_status' => VisitStatus::PLANNED,
                    'is_moved' => true,
                    'moved_from_day' => $originalDay,
                    'moved_from_week' => $originalWeek,
                    'moved_from_year' => $originalYear,
                    'moved_at' => now(),
                    'updated_at' => now(),
                ]);

                $movedCount++;
            }

            DB::commit();

            // Deduct points for incomplete itinerary (penalty for moving unfinished visits)
            if ($movedCount > 0) {
                try {
                    app(PointsService::class)->processAction('itinerary_incomplete', [
                        'employee_id' => $employeeId,
                        'description' => "خصم نقاط بسبب ترحيل {$movedCount} زيارة غير مكتملة من تاريخ {$sourceDate}",
                        'reference_type' => 'itinerary_move',
                        'reference_id' => null, // No single visit ID as multiple were moved
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('PointsService: Failed to deduct points for incomplete itinerary during move', [
                        'employee_id' => $employeeId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $daysMapping = [
                'saturday' => 'السبت',
                'sunday' => 'الأحد',
                'monday' => 'الاثنين',
                'tuesday' => 'الثلاثاء',
                'wednesday' => 'الأربعاء',
                'thursday' => 'الخميس',
                'friday' => 'الجمعة',
            ];
            $targetDayName = $daysMapping[$targetDay] ?? $targetDay;

            $message = "تم نقل {$movedCount} زيارة بنجاح";
            if ($targetYear && $targetWeek) {
                $message .= " إلى أسبوع {$targetWeek} - {$targetYear} (يوم {$targetDayName})";
            } else {
                $message .= ' إلى يوم '.$targetDayName;
            }

            if ($skippedCount > 0) {
                $message .= " (تم دمج {$skippedCount} زيارة موجودة مسبقاً)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'moved_count' => $movedCount,
                'skipped_count' => $skippedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'move_incomplete_visits');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء نقل الزيارات: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب قائمة الموظفين للـ Modal في صفحة CRM
     */
    public function getEmployeesListForModal()
    {
        try {
            $user = auth()->user();
            $employees = collect();

            // من يقع في نطاقه فقط — `null` بلا قيد (الإداريون)، ومندوبٌ بلا
            // نطاقٍ أوسع يرى نفسه وحده
            $scopeUserIds = DataScope::documentUserIds();

            $employees = User::whereNotNull('employee_id')
                ->when($scopeUserIds !== null, fn ($q) => $q->where(
                    fn ($inner) => $inner->whereIn('id', $scopeUserIds)->orWhere('id', $user->id)
                ))
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'employees' => $employees,
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'employees_list');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الموظفين',
            ], 500);
        }
    }

    /**
     * جلب حالة العميل في خط السير (الأسبوع الحالي والقادم)
     */
    public function getClientStatus($clientId, \App\Services\ClientAccessService $clientAccess)
    {
        if (! $clientAccess->canAccessClient(auth()->user(), (int) $clientId)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا العميل.');
        }

        try {
            $now = Carbon::now();
            $current = WeekHelper::getWeekAndYear($now);
            $currentYear = $current['year'];
            $currentWeek = $current['week'];

            $next = WeekHelper::getWeekAndYear($now->copy()->addWeek());
            $nextYear = $next['year'];
            $nextWeekNumber = $next['week'];

            // جلب زيارات الأسبوع الحالي
            $currentVisits = EmployeeClientVisit::where('client_id', $clientId)
                ->where('year', $currentYear)
                ->where('week_number', $currentWeek)
                ->get()
                ->map(function ($v) {
                    return [
                        'day_name' => $this->translateDay($v->day_of_week),
                        'employee_name' => $v->employee->name ?? 'غير معروف',
                    ];
                });

            // جلب زيارات الأسبوع القادم
            $nextVisits = EmployeeClientVisit::where('client_id', $clientId)
                ->where('year', $nextYear)
                ->where('week_number', $nextWeekNumber)
                ->get()
                ->map(function ($v) {
                    return [
                        'day_name' => $this->translateDay($v->day_of_week),
                        'employee_name' => $v->employee->name ?? 'غير معروف',
                    ];
                });

            return response()->json([
                'success' => true,
                'is_in_current_week' => $currentVisits->isNotEmpty(),
                'is_in_next_week' => $nextVisits->isNotEmpty(),
                'current_week' => [
                    'week' => $currentWeek,
                    'visits' => $currentVisits,
                ],
                'next_week' => [
                    'week' => $nextWeekNumber,
                    'visits' => $nextVisits,
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Client.Itinerary', 'getClientStatus');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب حالة العميل: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ترجمة اسم اليوم
     */
    private function translateDay($day)
    {
        return self::DAYS[strtolower($day)]['name'] ?? $day;
    }

    /**
     * حساب التاريخ الفعلي للزيارة بناءً على رقم الأسبوع واسم اليوم والسنة
     * الأسبوع يبدأ من السبت (Saturday)
     *
     * @param  int  $year  السنة
     * @param  int  $weekNumber  رقم الأسبوع (ISO week number)
     * @param  string  $dayName  اسم اليوم (Saturday, Sunday, Monday, etc.)
     * @return string تاريخ اليوم (Y-m-d)
     */
    /**
     * هل هذا العميل مُسنَدٌ لهذا الموظف؟ — تُستدعى فقط حين يكون إعداد
     * «تقييد الإضافة لخط السير على العملاء المُسنَدين» مفعّلاً.
     *
     * `client_employee.employee_id` مستخدَمٌ في هذا المشروع أحياناً كمعرّف
     * مستخدم (`users.id`) وأحياناً كمعرّف موظف (`employees.id`) — تناقضٌ
     * قائمٌ في البيانات سبق هذه الميزة. الفحص هنا يقبل أيّاً من التفسيرين
     * حتى لا يُحجَب إسنادٌ صحيح بسبب هذا التضارب القديم.
     */
    private function isClientAssignedToEmployee(int $clientId, int $employeeUserId): bool
    {
        $employeeRecordId = User::find($employeeUserId)?->employee_id;

        return ClientEmployee::where('client_id', $clientId)
            ->where(function ($q) use ($employeeUserId, $employeeRecordId) {
                $q->where('employee_id', $employeeUserId);
                if ($employeeRecordId) {
                    $q->orWhere('employee_id', $employeeRecordId);
                }
            })
            ->exists();
    }

    private function calculateVisitDate($year, $weekNumber, $dayName)
    {
        try {
            $dayName = strtolower($dayName);
            if (! isset(self::DAYS[$dayName])) {
                \Log::warning('Invalid day name', ['dayName' => $dayName]);

                return now()->toDateString();
            }

            $dayOffset = self::DAYS[$dayName]['offset'];

            // منطق متسق مع WeekHelper::getWeekAndYear (الأسبوع يبدأ السبت):
            // السبت الذي يبدأ الأسبوع 1 من السنة = أول يناير ناقص (يومه في نظام السبت=0)
            $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfDay();
            $startOfYearDay = ($startOfYear->dayOfWeek + 1) % 7; // 0=السبت، 6=الجمعة
            $week1Saturday = $startOfYear->copy()->subDays($startOfYearDay);

            // السبت الذي يبدأ الأسبوع المطلوب
            $weekSaturday = $week1Saturday->copy()->addWeeks($weekNumber - 1);

            // إضافة عدد الأيام للوصول إلى اليوم المطلوب داخل الأسبوع
            $visitDate = $weekSaturday->copy()->addDays($dayOffset);

            return $visitDate->toDateString();
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'calculate_visit_date');

            return now()->toDateString();
        }
    }

    /**
     * ملاحظات اليوم لخط السير — عملاءُ مندوبٍ في يومٍ بحالة زياراتهم وأرصدتهم
     * وآخر ملاحظاتهم، وخريطةُ يومه.
     *
     * **مَن يُختار** من {@see itineraryEmployeeOptions()} (الدور وفرع الهيدر
     * والأقسام). كان الفحصُ `isEmployee()` — صادقٌ للمدير الذي له سجلُّ موظف،
     * فيُجبَر على يومه هو ولا يقدر أن يختار مندوباً.
     *
     * **الرصيد** من حساب الذمّة (`Account::ledger()`) لا `$client->account` الذي
     * قد يلتقط المحفظة. **الملاحظات** بطاقاتُ {@see \App\Services\Clients\VisitNoteCards}
     * نفسُها التي في شاشة العميل — آخرُ ثلاثٍ لكل عميل.
     */
    public function getTodayNotes($employeeId = null)
    {
        if ($block = $this->sectionBlock('itinerary')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $service = app(ItineraryWeekService::class);
        $completed = self::VISIT_COMPLETED_SQL;
        [$employees, $lockedToSelf] = $this->itineraryEmployeeOptions();

        $requested = $lockedToSelf ? (int) auth()->id() : ((int) ($employeeId ?: request('employee_id')) ?: null);
        $employee = $requested
            ? $employees->firstWhere('id', $requested)
            : ($employees->count() === 1 ? $employees->first() : null);

        try {
            $date = request('date') ? Carbon::parse((string) request('date'))->startOfDay() : today();
        } catch (\Throwable) {
            $date = today();
        }

        $dateString = $date->toDateString();
        $parts = $service->partsFor($date);
        $visitStatus = in_array(request('visit_status'), ['planned', 'started', 'completed', 'missed', 'cancelled'], true)
            ? request('visit_status')
            : null;
        $currency = \App\Models\AccountSetting::where('user_id', auth()->id())->value('currency');

        $data = [
            'employees' => $employees,
            'employee' => $employee,
            'employeeLocked' => $lockedToSelf,
            'date' => $date,
            'dayKey' => $parts['day_of_week'],
            'weekNumber' => $parts['week_number'],
            'visitStatus' => $visitStatus,
            'weekDays' => [],
            'clients' => [],
            'stats' => array_fill_keys(['total', 'completed', 'started', 'missed', 'cancelled', 'remaining', 'notes_today'], 0) + ['balance' => 0.0],
            'repsToday' => [],
            'headerBranch' => ($headerNode = HeaderBranchScope::nodeId()) ? Branch::findCached($headerNode)?->name : null,
            'mapsKey' => (string) config('services.google_maps.key'),
            'trackingEnabled' => (bool) ClientSettingsHelper::isRouteTrackingEnabled(),
            'currencySymbol' => empty($currency) || $currency === 'SAR'
                ? '<img src="'.asset('assets/images/Saudi_Riyal.svg').'" alt="ريال سعودي" width="13">'
                : e($currency),
        ];

        $allFilters = ['node_id' => null, 'employee_id' => null, 'date_from' => null, 'date_to' => null];

        // بلا مندوبٍ مختار: بطاقةٌ لكل مندوبٍ له زياراتٌ في اليوم
        if (! $employee) {
            $rows = $this->itineraryListQuery($allFilters, false)
                ->where('visit_date', $dateString)
                ->selectRaw("employee_id, COUNT(DISTINCT client_id) AS total, COUNT(DISTINCT CASE WHEN {$completed} THEN client_id END) AS completed")
                ->groupBy('employee_id')
                ->get();
            $identities = $this->itineraryRepIdentities($rows->pluck('employee_id')->all());

            $data['repsToday'] = $rows
                ->filter(fn ($row) => $employees->contains('id', (int) $row->employee_id))
                ->map(fn ($row) => [
                    'id' => (int) $row->employee_id,
                    'name' => $identities[(int) $row->employee_id]['name'] ?? ('#'.$row->employee_id),
                    'branch' => $identities[(int) $row->employee_id]['branch'] ?? null,
                    'total' => (int) $row->total,
                    'completed' => (int) $row->completed,
                ])
                ->sortByDesc('total')
                ->values()
                ->all();

            return view('client::Itinerary.today-notes', $data);
        }

        $filters = ['employee_id' => (int) $employee->id] + $allFilters;
        $day = fn () => $this->itineraryListQuery($filters, false)->where('visit_date', $dateString);

        $weekCounts = $this->itineraryListQuery($filters, false)
            ->where('year', $parts['year'])
            ->where('week_number', $parts['week_number'])
            ->selectRaw("LOWER(day_of_week) AS day, COUNT(DISTINCT client_id) AS total, COUNT(DISTINCT CASE WHEN {$completed} THEN client_id END) AS completed")
            ->groupBy(DB::raw('LOWER(day_of_week)'))
            ->get()
            ->keyBy('day');

        $data['weekDays'] = collect(ItineraryWeekService::DAYS)->map(fn ($key) => [
            'key' => $key,
            'name' => self::DAYS[$key]['name'],
            'date' => $service->dateFor($parts['year'], $parts['week_number'], $key),
            'total' => (int) ($weekCounts[$key]->total ?? 0),
            'completed' => (int) ($weekCounts[$key]->completed ?? 0),
        ])->all();

        $dayRow = $day()->selectRaw("
                COUNT(DISTINCT client_id) AS total,
                COUNT(DISTINCT CASE WHEN {$completed} THEN client_id END) AS completed,
                COUNT(DISTINCT CASE WHEN visit_status = 'started' AND NOT {$completed} THEN client_id END) AS started,
                COUNT(DISTINCT CASE WHEN visit_status = 'missed' AND NOT {$completed} THEN client_id END) AS missed,
                COUNT(DISTINCT CASE WHEN visit_status = 'cancelled' AND NOT {$completed} THEN client_id END) AS cancelled
            ")->first();

        foreach (['total', 'completed', 'started', 'missed', 'cancelled'] as $key) {
            $data['stats'][$key] = (int) ($dayRow->{$key} ?? 0);
        }
        $data['stats']['remaining'] = max(0, $data['stats']['total'] - $data['stats']['completed'] - $data['stats']['missed'] - $data['stats']['cancelled']);

        $dayClientIds = $day()->distinct()->pluck('client_id')->map(fn ($id) => (int) $id)->all();
        $balances = Account::query()
            ->ledger()
            ->whereIn('client_id', $dayClientIds ?: [0])
            ->orderBy('id')
            ->get(['client_id', 'balance'])
            ->unique('client_id')
            ->mapWithKeys(fn ($account) => [(int) $account->client_id => (float) $account->balance]);

        $data['stats']['balance'] = round($balances->sum(), 2);
        $data['stats']['notes_today'] = ClientRelation::where('employee_id', $employee->id)->whereDate('created_at', $dateString)->count();

        $visits = $day()
            ->when($visitStatus === 'completed', fn ($q) => $q->whereRaw($completed))
            ->when($visitStatus && $visitStatus !== 'completed', fn ($q) => $q->where('visit_status', $visitStatus)->whereRaw("NOT {$completed}"))
            ->orderByRaw('sequence IS NULL')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get(['id', 'client_id', 'status', 'visit_status', 'started_at', 'completed_at', 'is_moved', 'moved_from_day', 'added_by']);

        $clientIds = $visits->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $described = $service->describeClients($clientIds);
        $sources = app(\App\Services\Itinerary\DailyItineraryService::class)->sourceBadges($visits, (int) $employee->id);
        $noteCounts = ClientRelation::whereIn('client_id', $clientIds ?: [0])
            ->selectRaw('client_id, COUNT(*) AS notes')
            ->groupBy('client_id')
            ->pluck('notes', 'client_id');
        // زيارةٌ وقعت فعلاً — لا مساحةُ عملٍ فُتحت بلا «بدء الزيارة» ولا تسجيلٌ تلقائي بالموقع
        $fieldVisits = Visit::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('visit_date', $dateString)
            ->where(fn ($q) => Visit::constrainActualVisit($q))
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data['clients'] = $visits->groupBy('client_id')->map(function ($group, $clientId) use ($described, $balances, $noteCounts, $fieldVisits, $sources) {
            $client = $described[(int) $clientId] ?? null;

            if (! $client) {
                return null;
            }

            $first = $group->first();
            $done = $group->contains(fn ($visit) => self::visitIsCompleted($visit));
            /* آخر ثلاث **زيارات** لا آخر ثلاث ملاحظات: الفاتورة والسند والمرتجع
               يكتب كلٌّ منها ملاحظة، فآخر ثلاث ملاحظات كانت زيارةً واحدة مكرّرة.
               تُجلب ثلاثون ملاحظة لتكفي ثلاث بطاقات مدمجة. */
            $recentNotes = ClientRelation::where('client_id', $clientId)->latest('created_at')->limit(30)->get();
            $cards = \App\Services\Clients\VisitNoteCards::build($recentNotes, (int) $clientId);
            $notes = \App\Services\Clients\VisitNoteCards::leadNotes($recentNotes, $cards)->take(3);

            return $client + [
                'state' => $done ? 'completed' : ($first->visit_status?->value ?? 'planned'),
                'started_at' => $first->started_at?->format('H:i'),
                'completed_at' => $group->pluck('completed_at')->filter()->first()?->format('H:i'),
                'moved_from' => $first->is_moved ? ItineraryWeekService::dayName(strtolower((string) $first->moved_from_day)) : null,
                'field_visit' => in_array((int) $clientId, $fieldVisits, true),
                'source' => $sources[(int) $clientId] ?? null,
                'tone' => \App\Services\Itinerary\DailyItineraryService::visitTone(isset($sources[(int) $clientId]), $done),
                'balance' => $balances[(int) $clientId] ?? null,
                'notes_total' => (int) ($noteCounts[$clientId] ?? 0),
                // الملاحظات التي تغطّيها البطاقات المعروضة — لعدّاد «س من ص»
                'notes_shown' => $notes->sum(fn ($note) => $cards[$note->id]['entries']),
                'notes' => $notes,
                'cards' => $cards,
            ];
        })->filter()->values()->all();

        return view('client::Itinerary.today-notes', $data);
    }

    /**
     * تقرير حركات وزيارات العملاء الشامل
     */
    public function monthlyNotesReport(Request $request)
    {
        try {
            // 0. الفلاتر الزمنية
            $period = $request->input('period', '30');
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $startDate = Carbon::parse($request->input('date_from'))->startOfDay();
                $endDate = Carbon::parse($request->input('date_to'))->endOfDay();
            } else {
                $endDate = now()->endOfDay();
                switch ($period) {
                    case '1': $startDate = now()->startOfDay();
                        break;
                    case '7': $startDate = now()->subDays(7)->startOfDay();
                        break;
                    case '30': $startDate = now()->subDays(30)->startOfDay();
                        break;
                    case '90': $startDate = now()->subDays(90)->startOfDay();
                        break;
                    case '180': $startDate = now()->subDays(180)->startOfDay();
                        break;
                    case '365': $startDate = now()->subDays(365)->startOfDay();
                        break;
                    default: $startDate = now()->subDays(30)->startOfDay();
                }
            }

            $employeeId = $request->input('employee_id');
            $clientId = $request->input('client_id');
            $status = $request->input('status');
            $code = $request->input('code');
            $cascadeNode = $request->input('cascade_node');
            $viewType = $request->input('view_type', 'details');
            $groupBy = $request->input('group_by', 'day_employee');

            // تحديد الفرع
            $branchIds = null;
            if ($cascadeNode && str_starts_with($cascadeNode, 'b_')) {
                $bId = (int) substr($cascadeNode, 2);
                $branch = Branch::find($bId);
                if ($branch) {
                    $branchIds = $branch->descendantBranchIds()->push($branch->id)->unique()->values()->all();
                }
            }

            $applyFilters = function ($query) use ($employeeId, $clientId, $status, $code, $branchIds) {
                if ($employeeId) {
                    $query->where('employee_id', $employeeId);
                }
                if ($clientId) {
                    $query->where('client_id', $clientId);
                }
                if ($code || $status || $branchIds) {
                    $query->whereHas('client', function ($q) use ($code, $status, $branchIds) {
                        if ($code) {
                            $q->where('code', 'LIKE', "%{$code}%");
                        }
                        if ($status) {
                            $q->where('status_id', $status);
                        }
                        if ($branchIds) {
                            $q->whereIn('branch_id', $branchIds)
                                ->orWhereExists(function ($sub) use ($branchIds) {
                                    $sub->select(DB::raw(1))->from('neighborhoods')
                                        ->whereColumn('neighborhoods.client_id', 'clients.id')
                                        ->whereIn('neighborhoods.region_id', $branchIds);
                                });
                        }
                    });
                }
            };

            $activities = collect();

            if ($request->ajax() || $request->has('period') || true) {
                // 1. الملاحظات والزيارات
                $notesQuery = ClientRelation::with(['employee', 'client.branch'])->whereBetween('created_at', [$startDate, $endDate]);
                $applyFilters($notesQuery);
                $notes = $notesQuery->get();
                foreach ($notes as $note) {
                    if (! $note->client) {
                        continue;
                    }
                    $activities->push([
                        'type' => 'note',
                        'type_name' => $note->process === 'add_note' ? 'ملاحظة' : ($note->process === 'visit_note' ? 'زيارة' : 'ملاحظة'),
                        'date' => Carbon::parse($note->created_at->format('Y-m-d')),
                        'client_id' => $note->client_id,
                        'client_name' => $note->client->trade_name ?? '',
                        'client_code' => $note->client->code ?? '',
                        'client_status' => $note->client->status_id,
                        'employee_id' => $note->employee_id,
                        'employee_name' => $note->employee->name ?? '',
                        'branch_id' => $note->client->branch_id,
                        'branch_name' => $note->client->branch->name ?? 'الفرع الرئيسي',
                        'amount' => 0,
                        'description' => $note->description ?? 'بدون تفاصيل',
                        'model' => $note,
                    ]);
                }

                // 2. الفواتير والمبيعات
                $invQuery = Invoice::with(['employee', 'client.branch'])->whereBetween('invoice_date', [$startDate, $endDate]);
                $applyFilters($invQuery);
                $invoices = $invQuery->get();
                foreach ($invoices as $inv) {
                    if (! $inv->client) {
                        continue;
                    }
                    $invDate = $inv->invoice_date instanceof Carbon ? $inv->invoice_date : Carbon::parse($inv->invoice_date);
                    $activities->push([
                        'type' => 'invoice',
                        'type_name' => 'فاتورة مبيعات',
                        'date' => Carbon::parse($invDate->format('Y-m-d')),
                        'client_id' => $inv->client_id,
                        'client_name' => $inv->client->trade_name ?? '',
                        'client_code' => $inv->client->code ?? '',
                        'client_status' => $inv->client->status_id,
                        'employee_id' => $inv->employee_id,
                        'employee_name' => $inv->employee->name ?? '',
                        'branch_id' => $inv->client->branch_id,
                        'branch_name' => $inv->client->branch->name ?? 'الفرع الرئيسي',
                        'amount' => $inv->total,
                        'description' => 'فاتورة مبيعات رقم: '.$inv->code,
                        'model' => $inv,
                    ]);
                }

                // 3. التحصيلات
                // من «الحسابات المحاسبية» لا من الثوابت — وهذه قراءةُ تقرير
                // فتحتمل غيابَ الربط: مفتاحٌ غير مربوط يُسقَط من القائمة
                // ويبقى الآخر، بدل أن يرمي `require` في وجه تقرير
                $treasuryParents = array_values(array_filter([
                    $this->accountingAccountId('cash_treasuries_parent'),
                    $this->accountingAccountId('bank_accounts_parent'),
                ]));

                $treasuryAccounts = $treasuryParents
                    ? Account::whereIn('parent_id', $treasuryParents)->pluck('id')->toArray()
                    : [];

                if (! empty($treasuryAccounts)) {
                    $collQuery = DB::table('journal_entry_details')
                        ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                        ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                        ->leftJoin('users', 'journal_entries.employee_id', '=', 'users.id')
                        ->leftJoin('branches', 'clients.branch_id', '=', 'branches.id')
                        ->whereIn('journal_entry_details.account_id', $treasuryAccounts)
                        ->where('journal_entry_details.debit', '>', 0)
                        ->whereBetween('journal_entries.date', [$startDate, $endDate])
                        ->where('journal_entries.status', 1)
                        ->select(
                            'journal_entries.id as je_id', 'journal_entries.date', 'journal_entries.client_id', 'journal_entries.employee_id',
                            'journal_entries.description',
                            'journal_entry_details.debit as amount',
                            'clients.trade_name as client_name', 'clients.code as client_code', 'clients.branch_id', 'clients.status_id as client_status',
                            'users.name as employee_name', 'branches.name as branch_name'
                        );

                    if ($employeeId) {
                        $collQuery->where('journal_entries.employee_id', $employeeId);
                    }
                    if ($clientId) {
                        $collQuery->where('journal_entries.client_id', $clientId);
                    }
                    if ($code) {
                        $collQuery->where('clients.code', 'LIKE', "%{$code}%");
                    }
                    if ($status) {
                        $collQuery->where('clients.status_id', $status);
                    }
                    if ($branchIds) {
                        $collQuery->where(function ($q) use ($branchIds) {
                            $q->whereIn('clients.branch_id', $branchIds)
                                ->orWhereExists(function ($sub) use ($branchIds) {
                                    $sub->select(DB::raw(1))->from('neighborhoods')
                                        ->whereColumn('neighborhoods.client_id', 'clients.id')
                                        ->whereIn('neighborhoods.region_id', $branchIds);
                                });
                        });
                    }

                    $collections = $collQuery->get();
                    foreach ($collections as $coll) {
                        $activities->push([
                            'type' => 'collection',
                            'type_name' => 'سند تحصيل',
                            'date' => Carbon::parse(Carbon::parse($coll->date)->format('Y-m-d')),
                            'client_id' => $coll->client_id,
                            'client_name' => $coll->client_name,
                            'client_code' => $coll->client_code,
                            'client_status' => $coll->client_status,
                            'employee_id' => $coll->employee_id,
                            'employee_name' => $coll->employee_name,
                            'branch_id' => $coll->branch_id,
                            'branch_name' => $coll->branch_name ?? 'الفرع الرئيسي',
                            'amount' => $coll->amount,
                            'description' => $coll->description ?? 'تحصيل دفعة مالية',
                            'model' => null,
                        ]);
                    }
                }
            }

            // الإحصائيات
            $totals = [
                'visits' => $activities->where('type', 'note')->count(),
                'invoices_count' => $activities->where('type', 'invoice')->count(),
                'invoices_amount' => $activities->where('type', 'invoice')->sum('amount'),
                'collections_count' => $activities->where('type', 'collection')->count(),
                'collections_amount' => $activities->where('type', 'collection')->sum('amount'),
                'total_actions' => $activities->count(),
            ];

            // التجميع
            $reportData = [];

            $getKey = function ($act) use ($groupBy) {
                switch ($groupBy) {
                    case 'day': return $act['date']->format('Y-m-d');
                    case 'day_employee': return $act['date']->format('Y-m-d').'_'.$act['employee_id'];
                    case 'month': return $act['date']->format('Y-m');
                    case 'employee': return $act['employee_id'];
                    case 'client': return $act['client_id'];
                    default: return $act['date']->format('Y-m-d');
                }
            };

            $getName = function ($act) use ($groupBy) {
                switch ($groupBy) {
                    case 'day': return $act['date']->format('Y-m-d');
                    case 'day_employee': return $act['date']->format('Y-m-d').' - '.($act['employee_name'] ?: 'غير محدد');
                    case 'month': return $act['date']->format('Y-m');
                    case 'employee': return $act['employee_name'] ?: 'غير محدد';
                    case 'client': return $act['client_name'] ?: 'غير محدد';
                    default: return $act['date']->format('Y-m-d');
                }
            };

            if ($viewType === 'matrix') {
                foreach ($activities as $act) {
                    $key = $getKey($act);
                    $month = (int) $act['date']->format('m');
                    if (! isset($reportData[$key])) {
                        $reportData[$key] = [
                            'name' => $getName($act),
                            'client_code' => $act['client_code'],
                            'client_status' => $act['client_status'],
                            'months' => array_fill(1, 12, 0),
                            'total' => 0,
                        ];
                    }
                    $reportData[$key]['months'][$month]++;
                    $reportData[$key]['total']++;
                }
                uasort($reportData, function ($a, $b) {
                    return $b['total'] <=> $a['total'];
                });
            } elseif ($viewType === 'summary') {
                foreach ($activities as $act) {
                    $key = $getKey($act);
                    if (! isset($reportData[$key])) {
                        $reportData[$key] = [
                            'name' => $getName($act),
                            'notes_count' => 0,
                            'invoices_count' => 0,
                            'invoices_amount' => 0,
                            'collections_count' => 0,
                            'collections_amount' => 0,
                        ];
                    }
                    if ($act['type'] == 'note') {
                        $reportData[$key]['notes_count']++;
                    }
                    if ($act['type'] == 'invoice') {
                        $reportData[$key]['invoices_count']++;
                        $reportData[$key]['invoices_amount'] += $act['amount'];
                    }
                    if ($act['type'] == 'collection') {
                        $reportData[$key]['collections_count']++;
                        $reportData[$key]['collections_amount'] += $act['amount'];
                    }
                }
                uasort($reportData, function ($a, $b) {
                    return ($b['notes_count'] + $b['invoices_count']) <=> ($a['notes_count'] + $a['invoices_count']);
                });
            } else {
                // عرض التفاصيل (الجدول الموحد للزيارات)
                // السطر الواحد يمثل: تاريخ + عميل + موظف
                foreach ($activities as $act) {
                    $visitKey = $act['date']->format('Y-m-d').'_'.$act['client_id'].'_'.$act['employee_id'];

                    if (! isset($reportData[$visitKey])) {
                        $statusId = $act['client_status'];
                        // اسمُ الحالة ولونها من جدولها لا من `1`/`2` مكتوبَين: المعرّفان
                        // قد يعنيان حالتين أخريين في منشأةٍ أخرى — نفس القاعدة تشمل اللون
                        // لا الاسم وحده
                        $statusRow = $statusId ? \App\Models\Statuses::find($statusId) : null;
                        $statusName = $statusRow->name ?? 'غير محدد';
                        $statusColor = $statusRow->color ?? '#6c757d';

                        $reportData[$visitKey] = [
                            'date' => $act['date']->format('Y-m-d'),
                            'client_id' => $act['client_id'],
                            'client_name' => $act['client_name'] ?: 'بدون عميل',
                            'client_code' => $act['client_code'] ?: '-',
                            'client_status_name' => $statusName,
                            'client_status_color' => $statusColor,
                            'branch_name' => $act['branch_name'],
                            'employee_name' => $act['employee_name'] ?: '-',
                            'notes' => [],
                            'invoices_amount' => 0,
                            'invoices_count' => 0,
                            'collections_amount' => 0,
                            'collections_count' => 0,
                            'details_text' => [], // للاحتفاظ بنصوص تفاصيل الفواتير والتحصيلات للـ tooltip
                        ];
                    }

                    if ($act['type'] == 'note') {
                        $reportData[$visitKey]['notes'][] = $act['description'];
                    } elseif ($act['type'] == 'invoice') {
                        $reportData[$visitKey]['invoices_amount'] += $act['amount'];
                        $reportData[$visitKey]['invoices_count']++;
                        $reportData[$visitKey]['details_text'][] = 'فاتورة مبيعات: '.number_format($act['amount'], 2);
                    } elseif ($act['type'] == 'collection') {
                        $reportData[$visitKey]['collections_amount'] += $act['amount'];
                        $reportData[$visitKey]['collections_count']++;
                        $reportData[$visitKey]['details_text'][] = 'سند تحصيل: '.number_format($act['amount'], 2);
                    }
                }

                // ترتيب الزيارات حسب التاريخ الأحدث
                usort($reportData, function ($a, $b) {
                    return strtotime($b['date']) <=> strtotime($a['date']);
                });
            }

            $clients = Client::select('id', 'trade_name', 'code')->get();
            $employees = User::whereNotNull('employee_id')->select('id', 'name')->get();
            $cascadeBranches = Branch::select('id', 'name', 'parent_id', 'is_territory')->orderBy('name')->get();

            $groupByOptions = [
                'day' => 'اليوم',
                'day_employee' => 'اليوم والموظف',
                'month' => 'الشهر',
                'employee' => 'الموظف',
                'client' => 'العميل',
            ];

            if ($request->ajax()) {
                $html = view('client::Itinerary.partials.monthly-notes-table', compact('reportData', 'viewType', 'groupBy', 'totals', 'groupByOptions'))->render();

                return response()->json(['success' => true, 'html' => $html]);
            }

            $statuses = \App\Models\Statuses::assignable()->ordered()->get(['id', 'name']);

            return view('client::Itinerary.monthly-notes', compact('reportData', 'totals', 'clients', 'cascadeBranches', 'employees', 'period', 'startDate', 'endDate', 'viewType', 'groupBy', 'groupByOptions', 'statuses'));

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'monthly_notes_report');
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * AJAX: جلب بيانات الخريطة والتتبع لخط السير اليومي
     */
    public function getMapData(Request $request)
    {
        if (! ClientSettingsHelper::isRouteTrackingEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'تتبّع خط السير معطّل من إعدادات العملاء.',
            ], 403);
        }

        $employeeId = $request->input('employee_id');

        if ($employeeId) {
            $this->assertCanAccessEmployeeItinerary((int) $employeeId, 'لا يمكنك تتبّع موقع موظف آخر.');
        }

        try {
            $date = $request->input('date') ?: now()->toDateString();
            $statusFilter = $request->input('status');
            $visitStatusFilter = $request->input('visit_status');
            $liveOnly = $request->input('live_only');

            // 1️⃣ تتبع مباشر أو آخر موقع للموظف (فقط سجلات GPS بدون client_id)
            $employeeLocation = null;
            $pathCoords = [];
            if ($employeeId) {
                $latestLocation = Location::where('employee_id', $employeeId)
                    ->whereNull('client_id')
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', 0)
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($latestLocation) {
                    $employee = User::find($employeeId);
                    $isLive = $latestLocation->updated_at->gt(now()->subMinutes(15));
                    $employeeLocation = [
                        'employee_name' => $employee->name ?? 'موظف',
                        'latitude' => (float) $latestLocation->latitude,
                        'longitude' => (float) $latestLocation->longitude,
                        'is_live' => $isLive,
                        'updated_at_formatted' => $latestLocation->updated_at->diffForHumans(),
                    ];
                }

                // جلب مسار حركة الموظف اليوم من جدول التتبع
                $trackingPoints = EmployeeTracking::where('employee_id', $employeeId)
                    ->where('tracking_date', $date)
                    ->orderBy('created_at', 'asc')
                    ->get(['latitude', 'longitude']);

                foreach ($trackingPoints as $point) {
                    $pathCoords[] = [
                        'lat' => (float) $point->latitude,
                        'lng' => (float) $point->longitude,
                    ];
                }
            }

            if ($liveOnly) {
                $employeesLocations = [];
                if (! $employeeId) {
                    $activeEmployeeLocations = Location::whereNotNull('employee_id')
                        ->when(DataScope::documentUserIds() !== null, fn ($q) => $q->whereIn('employee_id', DataScope::documentUserIds() ?: [0]))
                        ->whereNull('client_id')
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->where('latitude', '!=', 0)
                        ->where('updated_at', '>=', now()->subHours(24))
                        ->get()
                        ->groupBy('employee_id');

                    foreach ($activeEmployeeLocations as $empId => $locations) {
                        $latestLoc = $locations->sortByDesc('updated_at')->first();
                        if ($latestLoc) {
                            $emp = User::find($empId);
                            $employeesLocations[$empId] = [
                                'employee_name' => $emp->name ?? 'موظف',
                                'latitude' => (float) $latestLoc->latitude,
                                'longitude' => (float) $latestLoc->longitude,
                                'updated_at_formatted' => $latestLoc->updated_at->diffForHumans(),
                            ];
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'mode' => $employeeId ? 'single' : 'multi',
                    'employee_location' => $employeeLocation,
                    'employees_locations' => $employeesLocations,
                    'path_coords' => $pathCoords,
                ]);
            }

            // 2️⃣ جلب زيارات خط السير لليوم المحدد
            $visitsQuery = EmployeeClientVisit::with(['client.locations', 'client.branch'])
                ->where('visit_date', $date)
                // بلا مندوبٍ محدَّد: زياراتُ نطاق الدور وحده، لا المنشأة كلِّها
                ->when(DataScope::documentUserIds() !== null, fn ($q) => $q->whereIn('employee_id', DataScope::documentUserIds() ?: [0]));

            if ($employeeId) {
                $visitsQuery->where('employee_id', $employeeId);
            }

            $visits = $visitsQuery->get();

            // 3️⃣ تصفية وتجهيز الزيارات
            $formattedVisits = [];
            foreach ($visits as $visit) {
                $client = $visit->client;
                if (! $client) {

                    continue;
                }

                // جلب الإحداثيات: الأولوية لأعمدة العميل المباشرة (الثابتة للمحل)
                $lat = null;
                $lng = null;
                $address = 'غير محدد';

                // أولاً: محاولة جلب الإحداثيات من أعمدة العميل المباشرة
                if (isset($client->latitude) && $client->latitude && isset($client->longitude) && $client->longitude) {
                    $lat = (float) $client->latitude;
                    $lng = (float) $client->longitude;
                }

                // ثانياً: إذا لم تكن موجودة، نجلبها من جدول locations (حيث client_id مربوط)
                if ((! $lat || ! $lng) && $client->locations) {
                    $lat = (float) $client->locations->latitude;
                    $lng = (float) $client->locations->longitude;
                    $address = $client->locations->address ?? 'غير محدد';
                }

                if (! $lat || ! $lng) {

                    continue;
                }

                // التحقق من فلتر الحالة (مكتمل / غير مكتمل / معلق)
                // في الواجهة: visit.status === 'completed' يعادل 'active'، و 'inactive' يعادل 'unactive'
                $visitStatus = $visit->status === 'active' ? 'completed' : ($visit->status === 'unactive' ? 'inactive' : 'pending');

                if ($statusFilter && $statusFilter !== 'all') {
                    if ($statusFilter === 'active' && $visit->status !== 'active') {
                        continue;
                    }
                    if ($statusFilter === 'unactive' && $visit->status === 'active') {
                        continue;
                    }
                }

                // فلترة اختيارية بحالة دورة الحياة الجديدة — بجانب `status`
                // القديم أعلاه، لا بديلاً عنه
                if ($visitStatusFilter && $visitStatusFilter !== 'all' && $visit->visit_status->value !== $visitStatusFilter) {
                    continue;
                }

                $formattedVisits[] = [
                    'visit_id' => $visit->id,
                    'client_id' => $client->id,
                    'client_name' => $client->trade_name ?? 'غير معروف',
                    'code' => $client->code ?? '-',
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'status' => $visitStatus,
                    // دورة حياة الزيارة الكاملة (planned/started/completed/missed/cancelled) —
                    // إضافيّة بجانب `status` أعلاه (مشتقٌّ من الحقل القديم) حتى لا
                    // ينكسر أي استهلاكٍ آخر لها
                    'visit_status' => $visit->visit_status->value,
                    'visit_status_label' => $visit->visit_status->label(),
                    'visit_status_color' => $visit->visit_status->color(),
                    'branch_name' => $client->branch->name ?? 'الفرع الرئيسي',
                    'phone' => $client->phone ?? 'غير متوفر',
                    'address' => $address,
                ];
            }

            // 4️⃣ جلب مسار الحركة التاريخي للموظف لليوم المحدد (من جدول employee_tracking)
            $pathCoords = [];
            if ($employeeId) {
                $pathCoords = EmployeeTracking::where('employee_id', $employeeId)
                    ->where('tracking_date', $date)
                    ->orderBy('created_at', 'asc')
                    ->select('latitude', 'longitude', 'created_at')
                    ->get()
                    ->map(function ($loc) {
                        return [
                            'latitude' => (float) $loc->latitude,
                            'longitude' => (float) $loc->longitude,
                        ];
                    })->toArray();
            }

            // 5️⃣ وضع تتبع متعدد الموظفين (إذا لم يتم تحديد موظف معين)
            $employeesLocations = [];
            if (! $employeeId) {
                // جلب آخر موقع لكل موظف نشط اليوم
                $activeEmployeeIds = Location::whereDate('created_at', $date)
                    ->when(DataScope::documentUserIds() !== null, fn ($q) => $q->whereIn('employee_id', DataScope::documentUserIds() ?: [0]))
                    ->pluck('employee_id')
                    ->unique();

                foreach ($activeEmployeeIds as $empId) {
                    $latestLoc = Location::where('employee_id', $empId)
                        ->whereNull('client_id')
                        ->whereDate('updated_at', $date)
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->orderBy('updated_at', 'desc')
                        ->first();

                    if ($latestLoc) {
                        $emp = User::find($empId);
                        $employeesLocations[$empId] = [
                            'employee_name' => $emp->name ?? 'موظف',
                            'latitude' => (float) $latestLoc->latitude,
                            'longitude' => (float) $latestLoc->longitude,
                            'updated_at_formatted' => $latestLoc->created_at->format('H:i'),
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'mode' => $employeeId ? 'single' : 'multi',
                'visits' => $formattedVisits,
                'employee_location' => $employeeLocation,
                'path_coords' => $pathCoords,
                'employees_locations' => $employeesLocations,
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'map_data');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب بيانات الخريطة: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * اسم مستعار لمنع أي استثناء في حال تم استدعاء هذا الاسم في أي ملف كاش أو قالب
     */
    public function getTodayNotesMapData(Request $request)
    {
        return $this->getMapData($request);
    }

    /**
     * استقبال تحديث الموقع المباشر من GPS الهيدر (يعمل في الخلفية كل 15 ثانية)
     * يقوم بتحديث أو إنشاء سجل موقع الموظف في جدول locations
     */
    public function updateLocation(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            $employeeId = auth()->id();
            if (! $employeeId) {
                return response()->json(['success' => false, 'message' => 'غير مسجل دخول'], 401);
            }

            $lat = $request->latitude;
            $lng = $request->longitude;

            // تحديث أو إنشاء سجل الموقع الحالي للموظف مع تحديث الوقت إجبارياً
            $location = Location::firstOrNew(['employee_id' => $employeeId, 'client_id' => null]);
            $location->latitude = $lat;
            $location->longitude = $lng;
            $location->updated_at = now();
            $location->save();

            // حفظ نقطة مسار جديدة (فقط إذا تحرك مسافة معقولة عن آخر نقطة أو مر وقت)
            $lastPoint = EmployeeTracking::where('employee_id', $employeeId)
                ->where('tracking_date', now()->toDateString())
                ->orderBy('created_at', 'desc')
                ->first();

            $shouldSave = true;
            if ($lastPoint) {
                // حساب المسافة بالمتر بين آخر نقطة والنقطة الحالية
                $distance = $this->calculateDistanceInMeters(
                    (float) $lastPoint->latitude, (float) $lastPoint->longitude,
                    (float) $lat, (float) $lng
                );
                // بلا `true` صريحة تُعيد Carbon 3 فرقاً سالباً دائماً هنا
                // (created_at يسبق now() دائماً) — فيبقى `$timeDiff < 60`
                // صحيحاً أبداً بصرف النظر عن الوقت الحقيقي المنقضي، ويسقط
                // شرط «أو مرّت دقيقة كاملة» من المعادلة كلياً
                $timeDiff = now()->diffInSeconds($lastPoint->created_at, true);

                // لا نحفظ إذا المسافة أقل من 2 متر ولم يمر دقيقة كاملة
                if ($distance < 2 && $timeDiff < 60) {
                    $shouldSave = false;
                }
            }

            if ($shouldSave) {
                EmployeeTracking::create([
                    'employee_id' => $employeeId,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'tracking_date' => now()->toDateString(),
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update_location');

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * حساب المسافة بين نقطتين بالمتر (Haversine)
     */
    private function calculateDistanceInMeters($lat1, $lng1, $lat2, $lng2)
    {
        return \App\Support\GeoDistance::metersBetween((float) $lat1, (float) $lng1, (float) $lat2, (float) $lng2);
    }
}
