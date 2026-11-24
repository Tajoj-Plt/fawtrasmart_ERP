<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriesClientRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\Employee;
use App\Models\Statuses;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Client\Services\CustomerLedgerService;

/**
 * تصنيفات العملاء، ولوحةُ مؤشّرات العملاء.
 *
 * **الصلاحيات**: لم تكن على المتحكّم صلاحيةٌ واحدة رغم أنّ
 * `settings_customer_categories` معرَّفةٌ في الكتالوج منذ البداية —
 * فأيُّ مستخدمٍ يصل الشاشة كان يحذف تصنيفاً تُبنى عليه تقاريرُ المنشأة.
 *
 * **السجلّات**: `OperationLog` في الإنشاء والتعديل والحذف. كان الفشلُ
 * وحده يُسجَّل، والحذفُ يجري بلا التقاطِ خطأ ولا أثرٍ في سجلّ النشاط.
 */
class CatagroiyClientController extends Controller
{
    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientCategories';

    public function __construct(private readonly \App\Services\ClientDashboardService $dashboardService)
    {
        $this->middleware('auth');

        $this->middleware('permission:settings_customer_categories|clients_edit_client_settings', ['only' => [
            'index', 'create', 'store', 'edit', 'update', 'destroy',
        ]]);

        $this->middleware('permission:clients_dashboard_view|clients_view_all_clients', ['only' => ['dashboard', 'kpis', 'segment']]);
    }

    public function index()
    {
        $categories = CategoriesClient::withCount('clients')->get();

        return view('client::setting.category.index', compact('categories'));
    }

    public function create()
    {
        return view('client::setting.category.create');
    }

    public function store(CategoriesClientRequest $request)
    {
        try {
            // إنشاء التصنيف الجديد
            $category = CategoriesClient::create([
                'name' => $request->name,
                'description' => $request->description,
                'active' => $request->active ?? true,
            ]);

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $category,
                'إضافة تصنيف عملاء «'.$category->name.'»',
                ['new' => $category->only(['name', 'description', 'active'])]
            );

            return redirect()->route('categoriesClient.index')->with('success', 'تم إنشاء تصنيف العميل بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء إنشاء تصنيف العميل: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * تحديث تصنيف عميل موجود
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $category = CategoriesClient::find($id);

        if (! $category) {
            return redirect()->route('categoriesClient.index')->with('error', 'التصنيف غير موجود');
        }

        return view('client::setting.category.edit', compact('category'));
    }

    public function update(CategoriesClientRequest $request, $id)
    {
        // البحث عن التصنيف
        $category = CategoriesClient::find($id);

        if (! $category) {
            return redirect()->route('categoriesClient.index')->with('error', 'التصنيف غير موجود');
        }

        $before = $category->only(['name', 'description', 'active']);

        try {
            // تحديث بيانات التصنيف
            $category->update([
                'name' => $request->name,
                'description' => $request->description,
                'active' => $request->active ?? $category->active,
            ]);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $category,
                'تعديل تصنيف العملاء «'.$category->name.'»',
                ['old' => $before, 'new' => $category->only(['name', 'description', 'active'])]
            );

            return redirect()->route('categoriesClient.index')->with('success', 'تم تحديث تصنيف العميل بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'update', (int) $id);

            return redirect()
                ->back()
                ->with('error', 'حدث خطأ أثناء تحديث تصنيف العميل: '.$e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        // `find()` تعيد `null` لمعرّفٍ لا وجود له، و`->delete()` عليها
        // «Call to a member function on null» — صفحةُ 500 بدل رسالة
        $category = CategoriesClient::find($id);

        if (! $category) {
            return redirect()->route('categoriesClient.index')->with('error', 'التصنيف غير موجود');
        }

        try {
            // السجلّ قبل الحذف: بعده لا يبقى ما يُوصَف
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $category,
                'حذف تصنيف العملاء «'.$category->name.'»',
                ['old' => $category->only(['name', 'description', 'active'])]
            );

            $category->delete();
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', (int) $id);

            return redirect()->route('categoriesClient.index')
                ->with('error', 'تعذّر حذف التصنيف: '.$e->getMessage());
        }

        return redirect()->route('categoriesClient.index')->with('success', 'تم حذف تصنيف العميل بنجاح');
    }

    /**
     * ملخّص KPI خفيف للوحة التحكّم (AJAX) — نفس نطاق الفروع المستخدم في
     * `dashboard()` بالضبط (نفس الدالتين المساعدتين)، لا حسابٌ موازٍ.
     * كان المسار `clients.kpis` مسجَّلاً بلا دالّةٍ مقابلة له هنا فيُعطي
     * 500 دائماً؛ صار مكتملاً ومحروساً بنفس صلاحية اللوحة.
     */
    public function kpis(Request $request)
    {
        $user = auth()->user();
        $activeStatusIds = Statuses::activeClientStatusIds();

        $userBranchModel = $user->branch_id
            ? Branch::withoutGlobalScopes()->find((int) $user->branch_id)
            : null;
        $isMainBranch = (bool) ($userBranchModel && $userBranchModel->is_main);
        $scopeBranchIds = $this->resolveDashboardBranchScopeIds($request, $user, $isMainBranch);

        $baseQuery = Client::query();
        $this->applyDashboardBranchScope($baseQuery, $scopeBranchIds);

        $totalClients = (clone $baseQuery)->count();
        $activeClients = (clone $baseQuery)->whereIn('status_id', $activeStatusIds)->count();
        $newClientsThisMonth = (clone $baseQuery)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $newClientsLastMonth = (clone $baseQuery)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $growthRate = 0;
        if ($newClientsLastMonth > 0) {
            $growthRate = round((($newClientsThisMonth - $newClientsLastMonth) / $newClientsLastMonth) * 100, 1);
        } elseif ($newClientsThisMonth > 0) {
            $growthRate = 100;
        }

        return response()->json([
            'success' => true,
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
            'new_clients_this_month' => $newClientsThisMonth,
            'growth_rate' => $growthRate,
        ]);
    }

    public function dashboard(Request $request)
    {
        $scope = $this->dashboardScope($request);
        $isMainBranch = $scope['isMainBranch'];
        $branch = $scope['branch'];
        $baseQuery = $scope['query'];
        $ctx = $scope['ctx'];
        $user = auth()->user();

        $activeStatusIds = Statuses::activeClientStatusIds();
        $stoppedStatusIds = Statuses::stoppedClientStatusIds();

        /* نطاقُ العملاء بعد كل فلاتر الشريط — يُحسب مرّةً واحدة، وتُبنى منه
           كلُّ بطاقةٍ ومخطَّط، ويُعيد بناءه `segment()` حرفياً للقوائم. */
        $scopedClientIds = (clone $baseQuery)->pluck('id')->map(fn ($id) => (int) $id)->all();

        /* نطاقُ العملاء «النشطين» وحدَهم — تُقصَر عليه ثلاث بطاقات بطلب المستخدم:
           «نوع النشاط» و«دورة حياة العميل» و«أهداف العملاء». الموقوف وأي حالةٍ
           أخرى لا يظهر فيها ولا في قوائمها التفصيلية (segment() يطبّق نفس القيد). */
        $activeClientIds = (clone $baseQuery)->whereIn('status_id', $activeStatusIds)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        // 🔸 المؤشرات العامة — الحالةُ الحالية لا سجلُّ تغييرها
        $totalClients = count($scopedClientIds);
        $activeClients = (clone $baseQuery)->whereIn('status_id', $activeStatusIds)->count();
        $stoppedClients = (clone $baseQuery)->whereIn('status_id', $stoppedStatusIds)->count();
        $clientsWithoutStatus = (clone $baseQuery)->whereNull('status_id')->count();

        /* العملاء الجدد: في الفترة المختارة، أو في الشهر الجاري مقارنةً بالماضي.
           (كانت الفترةُ تُطبَّق على `clients.created_at` للقاعدة كلِّها، فاختيارُ
           «شهر» يجعل «إجمالي العملاء» عددَ من أُضيف خلال الشهر لا عددَ العملاء.) */
        $newClients = (clone $baseQuery)->whereBetween('created_at', [$ctx['new_from'], $ctx['new_to']])->count();
        $growthRate = null;
        if (! $ctx['has_period']) {
            $lastMonth = now()->subMonthNoOverflow();
            $newClientsLastMonth = (clone $baseQuery)
                ->whereBetween('created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()])
                ->count();
            $growthRate = $newClientsLastMonth > 0
                ? round((($newClients - $newClientsLastMonth) / $newClientsLastMonth) * 100, 1)
                : ($newClients > 0 ? 100 : 0);
        }

        /*
         * «مقارنة الأداء الشهري» — استعلامٌ واحدٌ مُجمَّعٌ بـ`GROUP BY YEAR, MONTH`
         * يغطّي العامين معاً بدل ٢٤ استعلام COUNT.
         */
        $currentYear = now()->year;
        $previousYear = now()->subYear()->year;
        $monthlyRaw = (clone $baseQuery)
            ->where('created_at', '>=', now()->subYears(2)->startOfMonth())
            ->selectRaw('YEAR(created_at) as y, MONTH(created_at) as m, COUNT(*) as cnt')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($row) => $row->y.'-'.$row->m);

        $monthCount = fn (int $year, int $month) => (int) ($monthlyRaw->get($year.'-'.$month)->cnt ?? 0);

        $currentYearData = [];
        $previousYearData = [];
        for ($month = 1; $month <= 12; $month++) {
            $currentYearData[] = $monthCount($currentYear, $month);
            $previousYearData[] = $monthCount($previousYear, $month);
        }

        $yearlyComparison = [
            'currentYear' => $currentYear,
            'previousYear' => $previousYear,
            'currentYearData' => $currentYearData,
            'previousYearData' => $previousYearData,
            'monthNames' => collect(range(1, 12))->map(function ($month) {
                return Carbon::create(null, $month, 1)->locale('ar')->translatedFormat('F');
            })->toArray(),
        ];

        // 🔸 قائمة الحالات في شريط الفلترة — القابلة للاستعمال فقط
        $statuses = Statuses::assignable()->get();

        /* 🔸 توزيع الحالة — كلُّ حالةٍ يحملها عميلٌ فعلاً، لا القابلةُ للإسناد
           وحدها: عميلٌ على حالةٍ قديمة كان يسقط من المخطَّط فلا يساوي مجموعُه
           إجماليَّ العملاء. */
        $statusCounts = (clone $baseQuery)
            ->select('status_id', DB::raw('count(*) as total'))
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $clientsByStatus = Statuses::whereIn('id', $statusCounts->keys()->filter()->all())->get()
            ->map(fn ($status) => [
                'key' => (string) $status->id,
                'status' => $status->name,
                'count' => (int) $statusCounts->get($status->id, 0),
                'color' => $status->color ?: '#6c757d',
            ])
            ->sortByDesc('count')
            ->values();

        if ($clientsWithoutStatus > 0) {
            $clientsByStatus->push(['key' => 'none', 'status' => 'بدون حالة', 'count' => $clientsWithoutStatus, 'color' => '#999999']);
        }

        // 🔸 وضع العميل — `clients.registration_stage_id`، على العملاء النشطين
        // وحدَهم بطلب المستخدم، بنفس نطاق البطاقات الثلاث أعلاه
        $registrationStageCounts = (clone $baseQuery)
            ->whereIn('status_id', $activeStatusIds)
            ->select('registration_stage_id', DB::raw('count(*) as total'))
            ->groupBy('registration_stage_id')
            ->pluck('total', 'registration_stage_id');

        $clientsByRegistrationStage = Statuses::whereIn('id', $registrationStageCounts->keys()->filter()->all())->get()
            ->map(fn ($stage) => [
                'key' => (string) $stage->id,
                'stage' => $stage->name,
                'count' => (int) $registrationStageCounts->get($stage->id, 0),
                'color' => $stage->color ?: '#ff9f43',
            ])
            ->sortByDesc('count')
            ->values();

        $clientsWithoutRegistrationStage = (clone $baseQuery)->whereIn('status_id', $activeStatusIds)->whereNull('registration_stage_id')->count();
        if ($clientsWithoutRegistrationStage > 0) {
            $clientsByRegistrationStage->push(['key' => 'none', 'stage' => 'بلا وضع', 'count' => $clientsWithoutRegistrationStage, 'color' => '#999999']);
        }

        // 🔸 نوع النشاط (التصنيف) — على العملاء «النشطين» وحدَهم بطلب المستخدم،
        // ومعه «بلا تصنيف» كي يساوي المجموعُ إجماليَّ النشطين لا كلَّ العملاء
        $categoryCounts = (clone $baseQuery)
            ->whereIn('status_id', $activeStatusIds)
            ->select('category_id', DB::raw('count(*) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $clientsByCategory = CategoriesClient::whereIn('id', $categoryCounts->keys()->filter()->all())->get()
            ->map(fn ($category) => [
                'key' => (string) $category->id,
                'category' => $category->name,
                'count' => (int) $categoryCounts->get($category->id, 0),
            ])
            ->sortByDesc('count')
            ->values();

        $withoutCategory = (int) ($categoryCounts->get(null) ?? $categoryCounts->get('') ?? 0);
        if ($withoutCategory > 0) {
            $clientsByCategory->push(['key' => 'none', 'category' => 'بلا تصنيف', 'count' => $withoutCategory]);
        }

        // 🔸 آخر الملاحظات — على عملاء النطاق نفسه
        $recentNotes = ClientRelation::with(['client', 'employee'])
            ->whereIn('client_id', (clone $baseQuery)->select('clients.id')->toBase())
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'client_id' => $note->client_id,
                    'client_name' => $note->client?->display_name ?? 'غير محدد',
                    'employee_name' => $note->employee->name ?? 'غير محدد',
                    'process' => $note->process,
                    // نصُّ الملاحظة نفسُه — `notes` للصفوف القديمة التي كُتبت فيه
                    'description' => preg_replace('/\s+/u', ' ', trim((string) ($note->description ?: $note->notes))) ?? '',
                    'date' => $note->created_at->format('Y-m-d'),
                ];
            });

        // 🔸 حركة المدى من دفتر الأستاذ — مبيعاتٍ ومرتجعاتٍ وتحصيلات
        $ledgerMovement = $this->dashboardLedgerMovement($scopedClientIds, $ctx['from'], $ctx['to']);

        // 🔸 العملاء حسب الفرع (تراكمي: كل فرع فرعي + كل أبنائه في الشجرة)
        $selectedBranch = $branch['selected'] ? Branch::withoutGlobalScopes()->find($branch['selected']) : null;
        $clientsByBranch = $this->buildClientsByBranchRollup($baseQuery, $isMainBranch, $user->branch_id, $selectedBranch);

        /* 🔸 العملاء حسب الموظف — مقصورةٌ على «أقسام الموظفين المسموح تعيينهم
           للعملاء» في إعدادات وحدات العملاء، والفراغُ هناك يعني كلَّ الأقسام. */
        $employeesQuery = ClientSettingsHelper::scopeEmployeesToClientDepartments(Employee::query());
        $scopeBranchIds = $scope['scopeBranchIds'];
        if ($scopeBranchIds !== null && count($scopeBranchIds) > 0) {
            $scopeIds = array_values(array_unique(array_map('intval', $scopeBranchIds)));
            $employeesQuery->where(function (Builder $eq) use ($scopeIds) {
                $eq->whereIn('branch_id', $scopeIds)
                    ->orWhereHas('clients', function ($cq) use ($scopeIds) {
                        $this->applyDashboardClientMatchesBranchNodes($cq, $scopeIds);
                    });
            });
        }

        // الموظفون وعملاؤهم — بالإسناد وبخطّ السير وبالزيارات، لا بالإسناد وحده
        $employeeActivity = $this->dashboardService->employeeActivity($scopedClientIds, $ctx['from'], $ctx['to']);

        $employees = (clone $employeesQuery)->orderBy('id')->get();
        $categories = CategoriesClient::all();

        // 🔸 مؤشّرات الخدمة — كلُّها خرائطُ لكل عميل، فالرقمُ = طولُ قائمته.
        // «دورة حياة العميل» على النشطين وحدَهم بطلب المستخدم — نفس نطاق
        // «نوع النشاط» و«أهداف العملاء» أدناه.
        $lifecycleSegments = $this->dashboardService->lifecycleSegments($activeClientIds);
        // بطاقة «أهداف العملاء» تتبع صلاحية شاشاتها — مفتاح القسم أُزيل (2026-09-15)
        $customerTargetsEnabled = hasPermission('clients.reports.targets') || hasPermission('clients_edit_client_settings');
        $customerTargets = $this->dashboardService->customerTargetInfo($activeClientIds);
        unset($customerTargets['clients']);
        $debtAging = $this->dashboardService->debtAgingBuckets($scopedClientIds);
        // الأرصدةُ الدائنة وحدَها — لا تدخل المديونية ولا تُطرح منها
        $creditPositions = $this->dashboardService->creditPositions($scopedClientIds);
        $creditClients = count($creditPositions);
        $creditTotal = round(array_sum($creditPositions), 2);

        $overdueMap = $this->dashboardService->overdueMap($scopedClientIds, $ctx['overdue_days']);
        $overdueClients = count($overdueMap);
        $overdueDebt = round(array_sum(array_column($overdueMap, 'overdue')), 2);

        $topOverdueIds = array_slice(array_keys($overdueMap), 0, 10);
        $topOverdueNames = Client::query()->whereIn('id', $topOverdueIds)->get()->keyBy('id');
        $topOverdueDebtors = collect($topOverdueIds)->map(fn ($id) => [
            'client_id' => $id,
            'client_name' => $topOverdueNames->get($id)?->display_name ?? ('#'.$id),
            'overdue' => $overdueMap[$id]['overdue'],
            'oldest_days' => $overdueMap[$id]['oldest_days'],
            'balance' => $overdueMap[$id]['balance'],
        ]);

        $visitStates = $this->dashboardService->visitStates($scopedClientIds, $ctx['from'], $ctx['to']);
        $visitOutcomes = $this->dashboardService->visitOutcomeCounts($scopedClientIds, $ctx['from'], $ctx['to']);
        $monthVisitResults = $this->dashboardService->thisMonthVisitResults($scopedClientIds);
        $dashboardNotifications = $this->dashboardService->notificationsCenter($scopedClientIds);
        $competitorsSummary = $this->dashboardService->competitorsSummary($scopedClientIds, Carbon::now()->subMonth());
        $clientsNeedingVisit = count($this->dashboardService->needsVisitMap($scopedClientIds, $ctx['visit_gap']));

        $branchOptions = $branch['options'];
        $branchLineage = $branch['lineage'];
        $selectedBranchId = $branch['selected'];
        $includeChildren = $branch['includeChildren'];
        $descendantCount = $branch['descendants'];
        $hasChildBranches = $branch['hasChildren'];

        $hasPeriod = $ctx['has_period'];
        $rangeLabel = $ctx['range_label'];
        $visitGap = $ctx['visit_gap'];
        $overdueDays = $ctx['overdue_days'];
        $defaultVisitGap = ClientSettingsHelper::dashboardNeedsVisitDays();
        $defaultOverdueDays = ClientSettingsHelper::dashboardOverdueDays();

        return view('client::dashboard.client.index', compact(
            'totalClients',
            'activeClients',
            'stoppedClients',
            'clientsWithoutStatus',
            'newClients',
            'growthRate',
            'clientsByStatus',
            'clientsByRegistrationStage',
            'clientsByCategory',
            'yearlyComparison',
            'recentNotes',
            'ledgerMovement',
            'clientsByBranch',
            'employeeActivity',
            'statuses',
            'employees',
            'categories',
            'isMainBranch',
            'branchOptions',
            'branchLineage',
            'selectedBranchId',
            'includeChildren',
            'descendantCount',
            'hasChildBranches',
            'lifecycleSegments',
            'customerTargets',
            'customerTargetsEnabled',
            'debtAging',
            'creditClients',
            'creditTotal',
            'overdueClients',
            'overdueDebt',
            'topOverdueDebtors',
            'visitStates',
            'visitOutcomes',
            'monthVisitResults',
            'dashboardNotifications',
            'competitorsSummary',
            'clientsNeedingVisit',
            'hasPeriod',
            'rangeLabel',
            'visitGap',
            'overdueDays',
            'defaultVisitGap',
            'defaultOverdueDays'
        ));
    }

    /**
     * عملاءُ شريحةٍ من اللوحة (JSON) — ما يفتحه النقرُ على بطاقةٍ أو مخطَّط
     * أو صفٍّ من جدول.
     *
     * يستقبل فلاترَ اللوحة نفسَها، ويبني النطاقَ بـ`dashboardScope()` نفسِها،
     * فالقائمةُ لا تخرج عمّا تعرضه اللوحة. الشرائحُ التحليلية (دورة الحياة،
     * التقادم، الزيارات…) تُحسب في الخدمة من الخريطة التي عُدّ منها الرقم؛
     * والتصنيفيّة البسيطة (حالة/وضع/تصنيف/تابع لـ/موظف) تُضيَّق هنا ثم تُعرض
     * كقائمةٍ عامة.
     */
    public function segment(Request $request)
    {
        $segment = (string) $request->input('segment', '');
        abort_unless(preg_match('/^[a-z_]+(\.[a-z0-9_]+)?$/', $segment) === 1, 422);

        $scope = $this->dashboardScope($request);
        $query = $scope['query'];
        $ctx = $scope['ctx'];
        [$group, $key] = array_pad(explode('.', $segment, 2), 2, null);
        $id = (int) $key;

        switch ($group) {
            case 'status':
                $key === 'none' ? $query->whereNull('status_id') : $query->where('status_id', $id);
                $ctx['title'] = $key === 'none' ? 'عملاء بدون حالة' : 'الحالة: '.(Statuses::find($id)->name ?? '—');
                $segment = 'list';
                break;
            case 'stage':
                $key === 'none' ? $query->whereNull('registration_stage_id') : $query->where('registration_stage_id', $id);
                $ctx['title'] = $key === 'none' ? 'عملاء بلا وضع' : 'وضع العميل: '.(Statuses::find($id)->name ?? '—');
                $segment = 'list';
                break;
            case 'category':
                $key === 'none' ? $query->whereNull('category_id') : $query->where('category_id', $id);
                $ctx['title'] = $key === 'none' ? 'عملاء بلا تصنيف' : 'نوع النشاط: '.(CategoriesClient::find($id)->name ?? '—');
                $segment = 'list';
                break;
            case 'node':
            case 'direct':
            case 'tree':
                $node = Branch::withoutGlobalScopes()->find($id);
                abort_unless($node, 404);
                if ($group === 'node') {
                    $query->whereRaw($this->dashboardNodeExpr().' = ?', [$id]);
                } else {
                    $this->applyDashboardClientMatchesBranchNodes($query, $group === 'direct' ? [$id] : $this->dashboardBranchSubtreeIds($id, true));
                }
                $ctx['title'] = 'تابع لـ: '.$node->name.' — '.$node->displayTypeLabel();
                $ctx['hint'] = $group === 'tree' ? 'شاملاً كل ما يتبعه' : 'التابعون له مباشرةً دون ما تحته';
                $segment = 'list';
                break;
            case 'scope':
                $ctx['title'] = 'كل عملاء النطاق';
                $segment = 'list';
                break;
            case 'employee':
                $query->whereIn('clients.id', DB::table('client_employee')->where('employee_id', $id)->select('client_id'));
                $ctx['title'] = 'عملاء الموظف: '.(Employee::find($id)?->full_name ?? '—');
                $segment = 'list';
                break;
        }

        /* «نوع النشاط» و«دورة حياة العميل» و«أهداف العملاء» و«وضع العميل» مقصورةٌ
           على العملاء النشطين وحدَهم في البطاقة نفسها (dashboard())، فقائمةُ
           التفصيل عند الضغط على شريحةٍ منها تطابق ذلك — لا تُظهر موقوفاً لم
           تعدّه البطاقة. */
        if (in_array($group, ['category', 'lifecycle', 'target', 'stage'], true)) {
            $query->whereIn('status_id', Statuses::activeClientStatusIds());
        }

        // خلايا «نشط / موقوف / أخرى» في جداول «تابع لـ» — تضييقٌ فوق أيّ شريحة
        $statusKind = (string) $request->input('status_kind', '');
        if (in_array($statusKind, ['active', 'stopped', 'other'], true)) {
            $activeIds = Statuses::activeClientStatusIds();
            $stoppedIds = Statuses::stoppedClientStatusIds();

            if ($statusKind === 'active') {
                $query->whereIn('status_id', $activeIds ?: [0]);
            } elseif ($statusKind === 'stopped') {
                $query->whereIn('status_id', $stoppedIds ?: [0]);
            } else {
                $query->where(fn ($q) => $q->whereNull('status_id')->orWhereNotIn('status_id', array_merge($activeIds, $stoppedIds) ?: [0]));
            }

            $ctx['title'] = ($ctx['title'] ?? 'العملاء').' — '.['active' => 'نشط', 'stopped' => 'موقوف', 'other' => 'بلا حالة أو حالة أخرى'][$statusKind];
        }

        $clientIds = (clone $query)->pluck('id')->map(fn ($clientId) => (int) $clientId)->all();
        $result = $this->dashboardService->drillDown($segment, $clientIds, $ctx);

        abort_if($result === null, 404);

        return response()->json($result + ['count' => count($result['rows'])]);
    }

    /**
     * نطاقُ اللوحة: الفرعُ وشجرتُه، وفلاترُ الشريط، والمدى، والحدود.
     *
     * مشتركٌ بين `dashboard()` و`segment()` كي لا تختلف قائمةٌ عن رقمها.
     *
     * **الفترةُ لا تضيّق قاعدةَ العملاء**: هي مدى الحركة (مبيعات، تحصيل،
     * زيارات) ومدى «العملاء الجدد». كانت تُطبَّق على `clients.created_at`
     * فتجعل كلَّ مؤشّرٍ في اللوحة عن «من أُضيف في الفترة» بلا أن يُقال ذلك.
     *
     * **الموظف** يُطابَق من `client_employee` — الجدولُ الذي تعدّ منه بطاقةُ
     * «أعلى الموظفين» — لا من `clients.employee_id` الذي يحمل معرّفاً من جدولٍ آخر.
     *
     * @return array{isMainBranch: bool, branch: array, scopeBranchIds: ?array, query: Builder, ctx: array}
     */
    private function dashboardScope(Request $request): array
    {
        $user = auth()->user();

        $userBranchModel = $user->branch_id
            ? Branch::withoutGlobalScopes()->find((int) $user->branch_id)
            : null;
        /** فرع رئيسي (مكتب/شركة): بدون اختيار في الفلتر = كل العملاء */
        $isMainBranch = (bool) ($userBranchModel && $userBranchModel->is_main);

        $branch = $this->resolveDashboardBranchTree($request, $isMainBranch, $user->branch_id ? (int) $user->branch_id : null);

        $query = Client::query();
        $this->applyDashboardBranchScope($query, $branch['ids']);

        if ($request->filled('region')) {
            $query->whereHas('neighborhood', fn ($q) => $q->where('region_id', $request->region));
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('employee')) {
            $query->whereIn('clients.id', DB::table('client_employee')->where('employee_id', (int) $request->employee)->select('client_id'));
        }
        if ($request->filled('status')) {
            $query->where('status_id', $request->status);
        }

        $period = (string) $request->input('period', 'all');
        $from = null;
        $to = null;

        if ($period === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            $from = Carbon::parse($request->input('start_date'))->startOfDay();
            $to = Carbon::parse($request->input('end_date'))->endOfDay();
        } elseif ($period !== 'all' && is_numeric($period)) {
            $from = now()->subDays((int) $period)->startOfDay();
            $to = now()->endOfDay();
        }

        $hasPeriod = $from !== null;
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        $visitGap = $request->filled('visit_gap')
            ? max(1, min(3650, (int) $request->input('visit_gap')))
            : ClientSettingsHelper::dashboardNeedsVisitDays();
        $overdueDays = $request->filled('overdue_days')
            ? max(0, min(3650, (int) $request->input('overdue_days')))
            : ClientSettingsHelper::dashboardOverdueDays();

        return [
            'isMainBranch' => $isMainBranch,
            'branch' => $branch,
            'scopeBranchIds' => $branch['ids'],
            'query' => $query,
            'ctx' => [
                'has_period' => $hasPeriod,
                'from' => $from,
                'to' => $to,
                'new_from' => $from,
                'new_to' => $to,
                'range_label' => $from->format('Y-m-d').' ← '.$to->format('Y-m-d'),
                'visit_gap' => $visitGap,
                'overdue_days' => $overdueDays,
            ],
        ];
    }

    /** العقدةُ التي يتبعها العميل: تبعيّتُه الصريحة، وإلّا فرعُه */
    private function dashboardNodeExpr(): string
    {
        return Schema::hasColumn('clients', 'tree_node_id')
            ? 'COALESCE(clients.tree_node_id, clients.branch_id)'
            : 'clients.branch_id';
    }

    /**
     * حركةُ المدى المالية للعملاء المعروضين — من دفتر الأستاذ لا من المستندات.
     *
     * الفاتورةُ غير المرحّلة ليست مبيعاً بعد، والتحصيلُ قد يُرحَّل سندَ قبضٍ
     * `RV` لا `REC`؛ فالثلاثةُ من `CustomerLedgerService` — المصدرُ الذي تقرأ
     * منه تقاريرُ العملاء الستّة، و«المرتجعات» فيه `SR` و`CN` معاً.
     *
     * على **عملاء النطاق** بكل فلاتره: كانت تُحصر بالفرع وحده، فاختيارُ
     * تصنيفٍ أو موظفٍ يغيّر كلَّ بطاقةٍ في اللوحة إلّا هذه الثلاث.
     *
     * @param  array<int, int>  $clientIds
     * @return array{sales: float, returns: float, collections: float, from: Carbon, to: Carbon, label: string}
     */
    private function dashboardLedgerMovement(array $clientIds, Carbon $from, Carbon $to): array
    {
        $movement = [
            'sales' => 0.0,
            'returns' => 0.0,
            'collections' => 0.0,
            'from' => $from,
            'to' => $to,
            'label' => $from->format('Y-m-d').' ← '.$to->format('Y-m-d'),
        ];

        // حساباتُ الذمم وحدَها — المحفظةُ التزامٌ لا ذمّة
        $accountIds = [];
        foreach (array_chunk($clientIds, 1000) as $chunk) {
            array_push($accountIds, ...Account::ledger()->whereIn('client_id', $chunk)->pluck('id')->all());
        }

        if ($accountIds === []) {
            return $movement;
        }

        $totals = app(CustomerLedgerService::class)
            ->movementTotals($accountIds, $from->toDateString(), $to->toDateString());

        foreach ($totals as $row) {
            $movement['sales'] += (float) $row['invoices'];
            $movement['returns'] += (float) $row['returns'];
            $movement['collections'] += (float) $row['receipts'];
        }

        return $movement;
    }

    /**
     * الفرع المعروض وموضعه من الشجرة — تنقّلٌ مستوىً بمستوى، طبق الأصل عن
     * Sales\DashboardController::resolveDashboardBranch، كي تتصرّف فلترة
     * الفرع في لوحة العملاء تماماً كما تتصرّف في لوحة المبيعات: لا اختيارٌ
     * يعرض الجذور (أو أبناء فرع المستخدم غير الرئيسي)، واختيار عقدةٍ يعرض
     * أبناءها المباشرين ومساراً للعودة صعوداً.
     *
     * @return array{ids: array<int,int>|null, selected: int|null, isMain: bool, options: Collection, lineage: Collection, includeChildren: bool, descendants: int, hasChildren: bool}
     */
    private function resolveDashboardBranchTree(Request $request, bool $isMainBranch, ?int $userBranchId): array
    {
        $includeChildren = $request->boolean('include_children', true);

        $requested = $request->input('branch_id');
        $selectedId = in_array($requested, [null, '', '0', 'all'], true) ? null : (int) $requested;
        $selected = $selectedId ? Branch::withoutGlobalScopes()->find($selectedId) : null;

        // مستخدم فرعٍ غير رئيسي محبوسٌ في شجرته: يتنقّل داخلها ولا يخرج
        if (! $isMainBranch) {
            $home = $userBranchId ? Branch::withoutGlobalScopes()->find($userBranchId) : null;

            if (! $home) {
                return $this->dashboardBranchState(null, null, collect(), $includeChildren, false);
            }

            // اختيارٌ خارج شجرته يُرَدّ إلى فرعه
            if (! $selected || ! $selected->isUnderBranch((int) $home->id)) {
                $selected = $home;
            }

            return $this->dashboardBranchState($selected, $home, $this->dashboardBranchChildrenOf($selected->id), $includeChildren, false);
        }

        return $this->dashboardBranchState($selected, null, $this->dashboardBranchChildrenOf($selected?->id), $includeChildren, true);
    }

    /**
     * أبناءُ عقدةٍ مباشرةً — أو الجذور حين لا عقدة.
     *
     * كانت تستدعي `accountingOnly()` فتُسقط كلَّ منطقةٍ ومجموعةٍ ومنفذ: لا
     * يستطيع أحدٌ أن ينزل في اللوحة إلّا داخل الفروع، ومنشأةٌ عملاؤها موزّعون
     * على المناطق والمنافذ لا تجد في القائمة ما تنزل إليه أصلاً.
     *
     * والعميلُ يتبع أيَّ عقدة، فالتنقّلُ يجب أن يبلغ أيَّ عقدة. و`node_type`
     * تُجلَب كي تكتب الشاشةُ نوعَ كل خيار بجانب اسمه.
     *
     * @return Collection<int, Branch>
     */
    private function dashboardBranchChildrenOf(?int $parentId)
    {
        return Branch::withoutGlobalScopes()
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'node_type', 'is_territory']);
    }

    /**
     * حالة الفرع كما تقرأها الشاشة.
     *
     * `ids: null` تعني بلا حصر (كل العملاء) — يطابق ما تتوقّعه
     * applyDashboardBranchScope() أدناه، لا الاصطلاح `[]` المستخدم في
     * Sales\DashboardController.
     *
     * @param  Branch|null  $home  جذرُ صلاحية المستخدم — يقف عنده المسار
     * @return array<string, mixed>
     */
    private function dashboardBranchState(?Branch $selected, ?Branch $home, $options, bool $includeChildren, bool $isMain): array
    {
        $ids = $selected
            ? $this->dashboardBranchSubtreeIds((int) $selected->id, $includeChildren)
            : null;

        $lineage = collect();

        if ($selected) {
            $lineage = $selected->lineageFromRoot()
                ->when($home, fn ($path) => $path->filter(fn ($node) => $node->isUnderBranch((int) $home->id))->values());
        }

        return [
            'ids' => $ids,
            'selected' => $selected?->id ? (int) $selected->id : null,
            'isMain' => $isMain,
            'options' => $options,
            'lineage' => $lineage,
            'includeChildren' => $includeChildren,
            'descendants' => $selected ? max(0, count($ids ?? []) - 1) : 0,
            'hasChildren' => $options->isNotEmpty(),
        ];
    }

    /**
     * الفرع وحده، أو هو وكل أبنائه مهما عمقوا.
     *
     * @return array<int, int>
     */
    private function dashboardBranchSubtreeIds(int $branchId, bool $includeChildren): array
    {
        if (! $includeChildren) {
            return [$branchId];
        }

        $branch = Branch::withoutGlobalScopes()->find($branchId);

        if (! $branch) {
            return [$branchId];
        }

        return $branch->descendantBranchIds()->push($branchId)
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function applyDashboardBranchScope(Builder $query, ?array $scopeIds): void
    {
        if ($scopeIds === null) {
            return;
        }
        if (count($scopeIds) === 0) {
            $query->whereRaw('0 = 1');

            return;
        }
        $this->applyDashboardClientMatchesBranchNodes($query, $scopeIds);
    }

    private function applyDashboardBranchScopeOnClientsTable(QueryBuilder $query, ?array $scopeIds): void
    {
        if ($scopeIds === null) {
            return;
        }
        if (count($scopeIds) === 0) {
            $query->whereRaw('0 = 1');

            return;
        }
        // نفس قاعدة `applyDashboardClientMatchesBranchNodes()` على استعلامٍ خام
        $ids = array_values(array_unique(array_map('intval', $scopeIds)));
        $hasTreeNode = Schema::hasColumn('clients', 'tree_node_id');
        $hasGroupId = Schema::hasColumn('clients', 'group_id');

        $query->where(function (QueryBuilder $w) use ($ids, $hasTreeNode, $hasGroupId) {
            if ($hasTreeNode) {
                $w->whereIn('clients.tree_node_id', $ids)
                    ->orWhere(function (QueryBuilder $legacy) use ($ids, $hasGroupId) {
                        $legacy->whereNull('clients.tree_node_id')
                            ->where(function (QueryBuilder $any) use ($ids, $hasGroupId) {
                                $any->whereIn('clients.branch_id', $ids)
                                    ->orWhereIn('neighborhoods.region_id', $ids);
                                if ($hasGroupId) {
                                    $any->orWhereIn('clients.group_id', $ids);
                                }
                            });
                    });

                return;
            }

            $w->whereIn('clients.branch_id', $ids)
                ->orWhereIn('neighborhoods.region_id', $ids);
            if ($hasGroupId) {
                $w->orWhereIn('clients.group_id', $ids);
            }
        });
    }

    /**
     * فرع المحاسبة للعميل: ضمن الشجرة (الفرع + الأبناء). null = بدون حصر.
     *
     * @return array<int>|null
     */
    private function resolveDashboardBranchScopeIds(Request $request, $user, bool $isMainBranch): ?array
    {
        $userBranchId = $user->branch_id ? (int) $user->branch_id : null;
        $userBranch = $userBranchId ? Branch::withoutGlobalScopes()->find($userBranchId) : null;

        if ($request->filled('branch')) {
            $selected = Branch::withoutGlobalScopes()->find((int) $request->branch);
            if (! $selected) {
                return [];
            }
            $selectedScope = $selected->descendantBranchIds()
                ->push((int) $selected->id)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($isMainBranch || ! $userBranchId) {
                return $selectedScope;
            }

            if (! $userBranch) {
                return [];
            }

            $userScopeIds = $userBranch->descendantBranchIds()
                ->push((int) $userBranch->id)
                ->map(fn ($id) => (int) $id)
                ->unique();

            return collect($selectedScope)->filter(fn (int $id) => $userScopeIds->contains($id))->values()->all();
        }

        if (! $isMainBranch && $userBranch) {
            return $userBranch->descendantBranchIds()
                ->push((int) $userBranch->id)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return null;
    }

    private function buildClientsByBranchRollup(
        Builder $baseQuery,
        bool $isMainBranch,
        ?int $userBranchId,
        ?Branch $selectedBranch,
    ): Collection {
        $focus = $selectedBranch;
        if (! $focus && ! $isMainBranch && $userBranchId) {
            $focus = Branch::withoutGlobalScopes()->find((int) $userBranchId);
        }

        // الصفوف: العقدةُ المختارة مباشرةً ثم أبناؤها بما تحتهم — أو الجذورُ بلا اختيار
        $nodes = [];
        if ($focus) {
            $nodes[] = [
                'label' => $focus->name.' — '.$focus->displayTypeLabel().' (مباشرةً)',
                'segment' => 'direct.'.$focus->id,
                'ids' => [(int) $focus->id],
            ];
        }

        $children = Branch::withoutGlobalScopes()
            ->where('parent_id', $focus ? (int) $focus->id : null)
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            $nodes[] = [
                'label' => $child->lineagePathLabel(' ← ').' — '.$child->displayTypeLabel(),
                'segment' => 'tree.'.$child->id,
                'ids' => $child->descendantBranchIds()->push((int) $child->id)->map(fn ($id) => (int) $id)->unique()->values()->all(),
            ];
        }

        $activeIds = Statuses::activeClientStatusIds();
        $stoppedIds = Statuses::stoppedClientStatusIds();
        $rows = collect();

        foreach ($nodes as $node) {
            $query = clone $baseQuery;
            $this->applyDashboardClientMatchesBranchNodes($query, $node['ids']);
            $counts = $this->statusKindCounts($query, $activeIds, $stoppedIds);

            if ($counts['total'] > 0) {
                $rows->push(['branch' => $node['label'], 'segment' => $node['segment'], 'count' => $counts['total']] + $counts);
            }
        }

        return $rows->sortByDesc('count')->values();
    }

    /**
     * نشط / موقوف / أخرى / الإجمالي لاستعلام عملاء — استعلامٌ واحد.
     *
     * «أخرى» = بلا حالة أو بحالةٍ غير النشط والموقوف، فالثلاثة تجمع الإجمالي.
     *
     * @param  array<int>  $activeIds
     * @param  array<int>  $stoppedIds
     * @return array{total: int, active: int, stopped: int, other: int}
     */
    private function statusKindCounts(Builder $query, array $activeIds, array $stoppedIds): array
    {
        $list = fn (array $ids) => implode(',', array_map('intval', $ids)) ?: '0';

        $row = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status_id IN ('.$list($activeIds).') THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN status_id IN ('.$list($stoppedIds).') THEN 1 ELSE 0 END) as stopped')
            ->first();

        $total = (int) ($row->total ?? 0);
        $active = (int) ($row->active ?? 0);
        $stopped = (int) ($row->stopped ?? 0);

        return ['total' => $total, 'active' => $active, 'stopped' => $stopped, 'other' => max(0, $total - $active - $stopped)];
    }

    /**
     * عميلٌ «ضمن» عُقدٍ معيّنة — بتبعيّته أوّلاً.
     *
     * كانت المطابقة بـ`branch_id` وحده، وهو **الفرع المحاسبيّ** المشتقّ لا
     * موضعُ العميل: تابعُ المنطقة أو المنفذ يحمل معرّفَ الفرع الذي فوقهما.
     * فتصفيةُ اللوحة بمنطقةٍ لا تجد أحداً، وبطاقةُ التوزيع تكدّس عملاءَ
     * المناطق والمنافذ على الفرع — تحسبهم كأنّهم فرعٌ واحد.
     *
     * والأعمدةُ القديمة (`neighborhoods.region_id`، `group_id`) تُقرأ لمن لا
     * تبعيّةَ صريحة له وحدَه، وإلّا ظهر عميلٌ نُقل إلى عقدةٍ أخرى في موضعَين.
     * نفس قاعدة `Client::scopeInTreeNode()`.
     *
     * @param  array<int>  $nodeIds
     */
    private function applyDashboardClientMatchesBranchNodes(Builder $query, array $nodeIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $nodeIds)));
        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $hasTreeNode = Schema::hasColumn('clients', 'tree_node_id');
        $hasGroupId = Schema::hasColumn('clients', 'group_id');

        $query->where(function (Builder $w) use ($ids, $hasTreeNode, $hasGroupId) {
            if ($hasTreeNode) {
                $w->whereIn('tree_node_id', $ids)
                    ->orWhere(function (Builder $legacy) use ($ids, $hasGroupId) {
                        $legacy->whereNull('tree_node_id')
                            ->where(function (Builder $any) use ($ids, $hasGroupId) {
                                $any->whereIn('branch_id', $ids)
                                    ->orWhereHas('neighborhood', function ($nq) use ($ids) {
                                        $nq->whereIn('region_id', $ids);
                                    });
                                if ($hasGroupId) {
                                    $any->orWhereIn('group_id', $ids);
                                }
                            });
                    });

                return;
            }

            $w->whereIn('branch_id', $ids)
                ->orWhereHas('neighborhood', function ($nq) use ($ids) {
                    $nq->whereIn('region_id', $ids);
                });
            if ($hasGroupId) {
                $w->orWhereIn('group_id', $ids);
            }
        });
    }
}
