<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Modules\Sales\Support\SalesDashboardMetrics;
use Modules\Sales\Support\SalesLedger;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\CustomerTarget;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\JournalEntryDetail;
use App\Models\Statuses;
use App\Models\User;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectionsReportController extends Controller
{
    /**
     * الحراسة — نفس نمط بقيّة متحكّمات المبيعات: صلاحيةٌ مُنقَّطة واحدة
     * لهذا التقرير، مجموعةً عليها دوالُّه.
     *
     * هذا المتحكّم — كأخواته العشرة في `Report/` — لم تكن له بانيةٌ أصلاً
     * ولا وسيطٌ على مساره: أيُّ مستخدمٍ مسجَّلٍ يفتح التقرير ويقرأ أرقامَ
     * المبيعات والأرباح والمدفوعات. والصلاحيةُ كانت معرَّفةً في الكتالوج
     * ومُسنَدةً للأدوار منذ زمن — الحارسُ وحده هو الذي كان ناقصاً.
     *
     * ومسارُ `ajax` يُحرَس كما تُحرَس الشاشة: هو مصدرُ الأرقام نفسِه،
     * وحراسةُ الصفحة وترْكُ مغذّيها مفتوحاً لا تحرس شيئاً.
     */
    public function __construct()
    {
        $this->middleware('permission:sales.reports.collections');
    }

    // الدوال المشتركة لتقارير المبيعات — منها حسابُ مدى الفترة.
    // `expandBranchToDescendants` هنا نسخةٌ أوسع فتبقى هي المستعمَلة.
    use SalesReportTrait;

    /**
     * خيارات التجميع للملخص
     */
    protected $groupByOptions = [
        'day' => 'اليوم',
        'day_employee' => 'اليوم والموظف',
        'month' => 'الشهر',
        'employee' => 'الموظف',
        'client' => 'العميل',
        'branch_tree' => 'هيكلية الفروع (الآباء والأبناء)',
        'branch' => 'الفرع',
        'region' => 'المنطقة',
        'group' => 'المجموعة',
        'outlet' => 'المنفذ',
        'account_type' => 'نوع الحساب',
        'method' => 'طريقة التحصيل',
        'treasury' => 'الخزينة/البنك',
    ];

    /**
     * مفاتيحُ التجميع القديمة — روابطُ محفوظة ما زالت تحملها. «الفرع الرئيسي»
     * كان أقربَ فرعٍ فوق العميل، و«المنطقة» كانت تقبل الفرعَ الفرعيّ منطقةً،
     * و«المنفذ» يقبل الفرعَ الفرعيّ منفذاً؛ صارت كلُّها تُقرأ بنوع العقدة الصحيح.
     */
    private const GROUP_BY_ALIASES = [
        'main_branch' => 'branch',
        'territory' => 'region',
        'group_node' => 'group',
    ];

    /** ما يُكتب لمن لا يقع تحت عقدةٍ من النوع المطلوب — بدل «غير محدد» مبهمة */
    private const NODE_TYPE_MISSING = [
        'branch' => 'بدون فرع',
        'region' => 'بدون منطقة',
        'group' => 'بدون مجموعة',
        'outlet' => 'بدون منفذ',
    ];

    /** طريقةُ التحصيل = نوعُ الحساب المدين فعلاً: خزينةٌ نقداً أو بنكٌ تحويلاً */
    private const METHOD_LABELS = [
        'cash' => 'نقدي',
        'bank' => 'تحويل بنكي',
    ];

    /**
     * عرض صفحة التحصيلات بالتفصيل
     * يدعم الفترات الزمنية مثل تقارير المبيعات (يوم، أسبوع، شهر، سنة)
     */
    public function index(Request $request)
    {
        try {
            /* كان هنا تحديثٌ لجدول العملاء عند كلّ فتحٍ للتقرير: كلُّ عميلٍ
               «ذهبي/ممتاز» بلا مستوىً سابق يُكتب له «جيد» سابقاً — فتخترع
               شاشةُ «تغيّر المستوى» ترقيةً لم تحدث (١١٧ عميلاً هنا). التقريرُ
               يقرأ ولا يكتب؛ حُذف. */

            // =================== معالجة الفترة الزمنية (مثل SalesProfitsController) ===================
            $period = $request->input('period', '30'); // الافتراضي شهر

            if ($request->input('year_filter')) {
                $year = $request->input('year_filter');
                $startDate = Carbon::createFromDate($year, 1, 1)->startOfDay();
                $endDate = Carbon::createFromDate($year, 12, 31)->endOfDay();
                $period = 'year_filter';
            } else {
                // مصدرٌ واحد للمدى (SalesReportTrait) تشترك فيه تقارير المبيعات
                // كلها، فلا يختلف زرُّ «أسبوع» من تقريرٍ إلى آخر.
                [$startDate, $endDate] = $this->parseDateRangeRequest($request);
                $startDate = $startDate->startOfDay();
                $endDate = $endDate->endOfDay();
            }

            // الحصول على الفلاتر من الطلب
            $branchId = $request->input('branch_id');
            $employeeId = $request->input('employee_id');
            $clientId = $request->input('client_id');
            $neighborhoodId = $request->input('neighborhood_id');
            $targetId = $request->input('target_id');
            $categoryId = $request->input('category_id');

            // خيارات العرض
            $viewType = $request->input('view_type', 'details');
            $groupBy = $this->normalizeGroupBy($request->input('group_by'));
            $paymentMethod = $request->input('payment_method', 'all');
            $isSummary = $viewType === 'summary';
            $isMatrix = $viewType === 'matrix';
            $isTargetChanges = $viewType === 'target_changes';

            // التحقق من صلاحيات المستخدم
            $user = auth()->user();
            $currentBranchId = $user->branch_id;
            $isMainBranch = false;

            if ($currentBranchId) {
                $branch = Branch::find($currentBranchId);
                $isMainBranch = $branch && $branch->is_main == 1;
            }

            // تحديد الفرع الفعال للفلترة — نفس منطق «مبيعات حسب الموظف»:
            // شجرة «تابع لـ» المشتركة (AffiliationTree::requestedNodeId) بدل
            // الـ cascade القديم الخاص بهذه الشاشة وحدها.
            $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $currentBranchId);

            $yearFilter = $request->input('year_filter', '');
            /* الافتراضي «الكل» لا «ما عدا الموقوفين»: لوحةُ المبيعات تعدّ تحصيلَ
               العميل الموقوف مع البقيّة (938,570 مقابل 849,113 في 2026 حين كان
               الموقوفون مستبعَدين افتراضاً)، والتقريرُ يجب أن يفتح على رقمها؛
               ومن أراد استبعادَهم اختار ذلك من القائمة. */
            $statusId = $request->input('status_id', 'all');
            $registrationStageId = $request->input('registration_stage');
            $showZero = $request->boolean('show_zero', false);
            $targetChangeFilter = $request->input('target_change_filter', 'all');

            // الحصول على بيانات التحصيلات
            $collectionsData = $this->getCollectionsDetail(
                $startDate,
                $endDate,
                $effectiveBranchId,
                $employeeId,
                $clientId,
                $neighborhoodId,
                $statusId,
                $showZero,
                $targetId,
                $categoryId,
                $registrationStageId,
                $paymentMethod
            );

            // فلترة حسب تغير مستوى العميل
            if ($targetChangeFilter && $targetChangeFilter !== 'all') {
                $collectionsData = $collectionsData->filter(function ($item) use ($targetChangeFilter) {
                    if ($targetChangeFilter === 'upgrade') {
                        return ($item['target_change'] ?? '') === 'upgrade';
                    }
                    if ($targetChangeFilter === 'downgrade') {
                        return ($item['target_change'] ?? '') === 'downgrade';
                    }
                    if ($targetChangeFilter === 'changed') {
                        return in_array($item['target_change'] ?? '', ['upgrade', 'downgrade']);
                    }

                    return true;
                });
            }

            /* الفرع / المنطقة / المجموعة / المنفذ — كلٌّ بنوعه الصحيح: الصفُّ
               يطابق إن كانت أقربُ عقدةٍ من ذلك النوع فوق تبعيّة عميله هي المختارة
               (`node_{type}_id`، المحسوبة بـ`Branch::displayNodeType`). فاختيارُ
               منطقةٍ يجلب عملاءَ كلِّ فروعها ومجموعاتها، واختيارُ منفذٍ عملاءَه وحدهم. */
            $nodeFilters = [];
            foreach (array_keys(self::NODE_TYPE_MISSING) as $nodeType) {
                $picked = (int) $request->input('node_'.$nodeType);
                if ($picked > 0) {
                    $nodeFilters[$nodeType] = $picked;
                }
            }
            if ($nodeFilters) {
                $collectionsData = $collectionsData->filter(function ($item) use ($nodeFilters) {
                    foreach ($nodeFilters as $nodeType => $picked) {
                        if ((int) ($item['node_'.$nodeType.'_id'] ?? 0) !== $picked) {
                            return false;
                        }
                    }

                    return true;
                })->values();
            }

            // حساب الإحصائيات الإجمالية
            $statistics = $this->calculateStatistics($collectionsData, $startDate);

            // تجميع البيانات حسب الخيار المحدد
            $reportData = [];
            $totals = [
                'count' => (int) $collectionsData->where('is_return', false)->where('is_cancellation', false)->count(),
                'amount' => max(0, (float) $collectionsData->sum('amount')),
                'return_count' => (int) $collectionsData->where('is_return', true)->count(),
                'return_amount' => (float) abs($collectionsData->where('is_return', true)->sum('amount')),
            ];

            if ($isSummary) {
                $reportData = $this->groupCollectionsData($collectionsData, $groupBy);
            } elseif ($isMatrix) {
                $reportData = $this->groupCollectionsDataForMatrix($collectionsData, $groupBy, $startDate, $endDate, $employeeId);
            } elseif ($isTargetChanges) {
                $reportData = $this->groupCollectionsDataForTargetChanges($collectionsData);
            } else {
                $reportData = $this->groupCollectionsDataWithDetails($collectionsData, $groupBy);
            }

            /* المصفوفةُ الشهرية مرقَّمةٌ في الخادم أيضاً: صفُّ العميل فيها اثنا عشر
               شهراً بأزرارها، فصفحةُ ثلاثين يوماً (٤٠٨ عملاء) بلغت ١٥ ميغابايت
               وصفحةُ السنة ٥٢. الإجمالياتُ الشهرية تُحسب من `$reportData` كاملةً. */
            /* البحثُ السريع (`q`) يضيّق صفوفَ الجدول وحدَها ويجري في الخادم على
               الصفوف كلِّها لا صفحةِ العرض — البطاقاتُ والمخطّطاتُ والإجماليات
               تبقى على المجموعة كلِّها. */
            $quickSearch = trim((string) $request->input('q', ''));
            $listRows = $this->quickSearchRows($reportData, $quickSearch);

            $matrixRows = null;
            $matrixPager = null;
            if ($isMatrix) {
                [$matrixRows, $matrixPager] = $this->paginateDetailRows($request, $listRows, 25, true);
            }

            // بطاقاتُ الرأس والمخطّطان — من الصفوف نفسِها التي يعرضها الجدول
            $dashboard = $this->buildDashboard($collectionsData, $groupBy);
            $dashboard['debt'] = $this->outstandingDebt($request, $effectiveBranchId, $endDate, $collectionsData, $nodeFilters);
            $detailRows = $this->sortDetailRows($collectionsData, $groupBy);
            $detailRowsAll = $detailRows;
            $detailRows = $this->quickSearchRows($detailRows, $quickSearch)->values();

            /* صفوفُ التصدير والطباعة تُطلب عند النقر (`?export=rows`) لا تُضمَّن
               في الصفحة: كانت تُكتب JSON لكلّ صفٍّ بجانب صفّه، فصفحةُ سنةٍ كاملة
               تجاوزت خمسين ميغابايت. القوائمُ والمخطّطات لا تلزم هذا الطلب. */
            if ($request->input('export') === 'rows') {
                return response()->json([
                    'rows' => $isMatrix ? $this->matrixExportRows($reportData) : $this->exportRowsFor($detailRowsAll),
                ]);
            }

            /* الجدولُ مرقَّمٌ في الخادم: الصفحةُ تحمل صفوفَها وحدَها، والبطاقاتُ
               والمخطّطاتُ والإجماليات تبقى على المجموعة كلِّها. */
            [$detailRowsPage, $detailPager] = $this->paginateDetailRows($request, $detailRows);
            $paymentAccounts = $this->paymentAccountsForFilter();

            // الحصول على قوائم الفلترة
            $branches = $this->getBranchesForFilter($isMainBranch, $currentBranchId);
            $employees = $this->getEmployeesForFilter($isMainBranch, $currentBranchId, $effectiveBranchId);
            // مُنتقي العميل بالبحث (reports.partials.client-select) — لا قائمةَ كاملة في الصفحة
            $clients = collect();
            $regionGroups = $this->getRegionGroupsForFilter($effectiveBranchId);
            $groupByOptions = $this->groupByOptions;
            $statuses = Statuses::whereNull('section')->orderBy('id')->get();
            $registrationStages = Statuses::section('registration_stage')->ordered()->get();
            $customerTargets = CustomerTarget::where('is_active', true)->orderBy('priority', 'desc')->get();
            $categoriesClient = CategoriesClient::all();

            // عقدُ شجرة «تابع لـ» المشتركة — نفس مصدر «مبيعات حسب الموظف» وتقارير العملاء
            $treeNodes = $this->affiliationTreeNodes();
            $nodeTypeOptions = $this->nodeTypeOptions($treeNodes);

            // استخراج العملاء المميزين لإضافتهم في الـ Modals الخاصة بالملاحظات
            // تم نقل جلب البيانات إلى دالة AJAX (getClientDetails) لتخفيف الحمل على الصفحة
            $uniqueClients = [];

            return view('sales::sals_report.salesRport.Collections_Report', compact(
                'collectionsData',
                'reportData',
                'totals',
                'branches',
                'employees',
                'clients',
                'regionGroups',
                'treeNodes',
                'statuses',
                'registrationStages',
                'registrationStageId',
                'customerTargets',
                'categoriesClient',
                'effectiveBranchId',
                'employeeId',
                'clientId',
                'statusId',
                'targetId',
                'categoryId',
                'branchId',
                'neighborhoodId',
                'showZero',
                'isMainBranch',
                'statistics',
                'startDate',
                'endDate',
                'viewType',
                'groupBy',
                'isSummary',
                'isMatrix',
                'groupByOptions',
                'period',
                'uniqueClients',
                'yearFilter',
                'targetChangeFilter',
                'dashboard',
                'quickSearch',
                'listRows',
                'detailRows',
                'detailRowsPage',
                'detailPager',
                'matrixRows',
                'matrixPager',
                'paymentMethod',
                'paymentAccounts',
                'nodeTypeOptions',
                'nodeFilters'
            ));

        } catch (\Exception $e) {
            Log::error('Collections Report Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
            ]);

            // تهيئة متغيرات افتراضية فارغة لضمان فتح الصفحة الإلزامي عند حدوث أي خطأ
            $collectionsData = collect();
            $reportData = collect();
            $totals = ['count' => 0, 'amount' => 0, 'return_count' => 0, 'return_amount' => 0];

            $branches = Branch::withoutGlobalScopes()->get();
            $employees = Employee::all();
            $clients = Client::all();
            $regionGroups = [];
            $treeNodes = $this->affiliationTreeNodes();
            $nodeTypeOptions = $this->nodeTypeOptions($treeNodes);
            $statuses = Statuses::whereNull('section')->orderBy('id')->get();
            $registrationStages = Statuses::section('registration_stage')->ordered()->get();
            $customerTargets = CustomerTarget::where('is_active', true)->orderBy('priority', 'desc')->get();
            $categoriesClient = CategoriesClient::all();
            $uniqueClients = [];

            $effectiveBranchId = $effectiveBranchId ?? null;
            $employeeId = $employeeId ?? null;
            $clientId = $clientId ?? null;
            $statusId = $statusId ?? 'all';
            $registrationStageId = $registrationStageId ?? null;
            $targetId = $targetId ?? null;
            $categoryId = $categoryId ?? null;
            $branchId = $branchId ?? null;
            $neighborhoodId = $neighborhoodId ?? null;
            $showZero = $showZero ?? false;
            $isMainBranch = $isMainBranch ?? false;
            $statistics = [
                'total_collections' => 0,
                'total_returns' => 0,
                'net_collections' => 0,
                'count' => 0,
                'average' => 0,
            ];
            $startDate = $startDate ?? now()->subDays(30)->startOfDay();
            $endDate = $endDate ?? now()->endOfDay();
            $viewType = $viewType ?? 'details';
            $groupBy = $groupBy ?? 'day_employee';
            $isSummary = $viewType === 'summary';
            $isMatrix = $viewType === 'matrix';
            $groupByOptions = $this->groupByOptions;
            $period = $period ?? '30';
            $yearFilter = $yearFilter ?? '';
            $targetChangeFilter = $targetChangeFilter ?? 'all';
            $nodeFilters = $nodeFilters ?? [];
            $paymentMethod = $paymentMethod ?? 'all';
            $dashboard = $this->buildDashboard(collect(), $groupBy);
            $detailRows = collect();
            $detailRowsPage = collect();
            $detailPager = null;
            $matrixRows = null;
            $matrixPager = null;
            $paymentAccounts = $this->paymentAccountsForFilter();

            // تمرير رسالة الخطأ للعرض داخل الصفحة بدلاً من إعادة التوجيه للخلف
            session()->flash('error', 'حدث خطأ أثناء تحميل بعض بيانات التقرير: '.$e->getMessage());

            return view('sales::sals_report.salesRport.Collections_Report', compact(
                'collectionsData',
                'reportData',
                'totals',
                'branches',
                'employees',
                'clients',
                'regionGroups',
                'treeNodes',
                'statuses',
                'registrationStages',
                'registrationStageId',
                'customerTargets',
                'categoriesClient',
                'effectiveBranchId',
                'employeeId',
                'clientId',
                'statusId',
                'targetId',
                'categoryId',
                'branchId',
                'neighborhoodId',
                'showZero',
                'isMainBranch',
                'statistics',
                'startDate',
                'endDate',
                'viewType',
                'groupBy',
                'isSummary',
                'isMatrix',
                'groupByOptions',
                'period',
                'uniqueClients',
                'yearFilter',
                'targetChangeFilter',
                'dashboard',
                'detailRows',
                'detailRowsPage',
                'detailPager',
                'matrixRows',
                'matrixPager',
                'paymentMethod',
                'paymentAccounts',
                'nodeTypeOptions',
                'nodeFilters'
            ));
        }
    }

    /**
     * تجميع بيانات التحصيلات حسب الخيار المحدد (للملخص)
     */
    private function groupCollectionsData($collectionsData, $groupBy)
    {
        if ($groupBy === 'branch_tree') {
            return $this->buildBranchTreeGroup($collectionsData);
        }

        $grouped = [];

        foreach ($collectionsData as $collection) {
            $key = $this->getGroupKey($collection, $groupBy);
            $name = $this->getGroupName($collection, $groupBy);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $name,
                    'count' => 0,
                    'amount' => 0.0,
                    'date_key' => $key,
                ];
            }

            $grouped[$key]['count']++;
            $grouped[$key]['amount'] += (float) ($collection['amount'] ?? 0);
        }

        // ترتيب حسب التاريخ تنازلياً عند التجميع باليوم/الشهر، وإلا حسب المبلغ
        if (in_array($groupBy, ['day', 'day_employee', 'month', 'region_month'])) {
            uksort($grouped, function ($a, $b) {
                return strcmp($b, $a);
            });
        } elseif ($groupBy === 'node_type') {
            // ترتيب node_type حسب أولوية منطقية
            $order = ['branch' => 0, 'sub_branch' => 1, 'territory' => 2, 'group' => 3, 'outlet' => 4, 'region' => 5, 'zone' => 6, 'district' => 7];
            uksort($grouped, function ($a, $b) use ($order) {
                $ao = $order[$a] ?? 99;
                $bo = $order[$b] ?? 99;

                return $ao <=> $bo;
            });
        } else {
            uasort($grouped, function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });
        }

        return $grouped;
    }

    /**
     * تجميع بيانات التحصيلات كجدول شهري (Matrix)
     */
    private function groupCollectionsDataForMatrix($collectionsData, $groupBy, $startDate = null, $endDate = null, $employeeId = null)
    {
        if ($groupBy === 'branch_tree') {
            return $this->buildBranchTreeGroupForMatrix($collectionsData);
        }

        $grouped = [];
        $clientIds = [];

        foreach ($collectionsData as $collection) {
            $key = $this->getGroupKey($collection, $groupBy);
            $name = $this->getGroupName($collection, $groupBy);

            // استخراج الشهر من التاريخ
            $date = $collection['date'];
            $month = $date instanceof Carbon ? (int) $date->month : (int) Carbon::parse($date)->month;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $name,
                    'months' => array_fill(1, 12, 0.0), // 12 شهراً
                    'visits' => array_fill(1, 12, null), // visits indicators
                    'total' => 0.0,
                ];

                if ($groupBy === 'client') {
                    $cId = $collection['client_id'] ?? null;
                    if ($cId) {
                        $clientIds[] = $cId;
                    }

                    $grouped[$key]['client_id'] = $cId;
                    $grouped[$key]['client_code'] = $collection['client_code'] ?? '-';
                    $grouped[$key]['client_status'] = $collection['client_status'] ?? 'غير حدد';
                    $grouped[$key]['client_status_color'] = $collection['client_status_color'] ?? '#6c757d';
                    $grouped[$key]['client_category'] = $collection['client_category'] ?? 'غير مقيّم';
                    $grouped[$key]['client_category_color'] = $collection['client_category_color'] ?? '#6c757d';
                    $grouped[$key]['target_change'] = $collection['target_change'] ?? 'none';
                    $grouped[$key]['client_previous_category'] = $collection['client_previous_category'] ?? 'غير مقيّم';
                    $grouped[$key]['client_previous_category_color'] = $collection['client_previous_category_color'] ?? '#6c757d';
                    $grouped[$key]['client_real_category'] = $collection['client_real_category'] ?? 'غير محدد';
                    $grouped[$key]['last_deposit'] = 0;
                    $grouped[$key]['last_deposit_date'] = null;
                }
            }

            $amount = (float) ($collection['amount'] ?? 0);
            $grouped[$key]['months'][$month] += $amount;
            $grouped[$key]['total'] += $amount;
        }

        if ($groupBy === 'client' && ! empty($clientIds)) {
            $clientIds = array_unique($clientIds);

            /* «آخر عهدة» من ملاحظات الزيارة التي كتبها المندوب (`type = client`) وحدها.
               الفاتورةُ وسندُ القبض والإشعارُ يكتبون ملاحظةً تلقائيةً (`type = invoice`)
               تنسخ الرصيدَ الجاري، فكان آخرُ «عهدة» هو آخرَ مستندٍ لا آخرَ جردٍ فعليّ —
               وتاريخُها تاريخَ المستند. والصفرُ عهدةٌ مسجَّلة (أُرجعت) فلا يُستبعد. */
            $latestDepositIds = \DB::table('client_relations')
                ->select(\DB::raw('MAX(id) as id'))
                ->whereIn('client_id', $clientIds)
                ->where('type', 'client')
                ->whereNotNull('deposit_count')
                ->groupBy('client_id')
                ->pluck('id');

            $lastDeposits = \DB::table('client_relations')
                ->whereIn('id', $latestDepositIds)
                ->get()
                ->keyBy('client_id');

            $latestInvoiceIds = \DB::table('journal_entries as je')
                ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
                ->whereIn('je.client_id', $clientIds)
                ->where('je.status', 1)
                ->where('jed.account_id', SystemAccountIds::SALES)
                ->where('jed.credit', '>', 0)
                ->whereNotIn('je.reference_type', [
                    ReferenceType::SALES_RETURN->value,
                    ReferenceType::CREDIT_NOTE->value,
                ])
                ->select(\DB::raw('MAX(je.id) as id'))
                ->groupBy('je.client_id')
                ->pluck('id');

            $lastInvoices = \DB::table('journal_entries as je')
                ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
                ->whereIn('je.id', $latestInvoiceIds)
                ->where('jed.account_id', SystemAccountIds::SALES)
                ->where('jed.credit', '>', 0)
                ->select('je.client_id', 'je.id', 'je.date', 'jed.credit as amount')
                ->get()
                ->keyBy('client_id');

            /* زياراتُ الشهر بقاعدة لوحة العملاء (`ClientDashboardService::planRows()`):
               كان يُقرأ العمودُ القديم `status` وحده، فكلُّ ما ليس `active` — القادمةُ
               والجاريةُ والملغاةُ والمبرَّرةُ وما زاره المندوبُ ولم يُغلقه — يُعدّ «فائتة». */
            $clientVisits = $this->monthlyVisitCounts(collect($clientIds)->all(), $startDate, $endDate, $employeeId ? (int) $employeeId : null);

            foreach ($grouped as $key => $data) {
                $cId = $data['client_id'] ?? null;
                if ($cId) {
                    if (isset($lastDeposits[$cId])) {
                        $grouped[$key]['last_deposit'] = (int) $lastDeposits[$cId]->deposit_count;
                        $grouped[$key]['has_deposit'] = true;
                        $grouped[$key]['last_deposit_note'] = $lastDeposits[$cId]->description;
                        $grouped[$key]['last_deposit_date'] = Carbon::parse($lastDeposits[$cId]->date ?: $lastDeposits[$cId]->created_at)->format('Y-m-d');
                    }
                    if (isset($lastInvoices[$cId])) {
                        $grouped[$key]['last_invoice_amount'] = $lastInvoices[$cId]->amount;
                        $grouped[$key]['last_invoice_date'] = Carbon::parse($lastInvoices[$cId]->date)->format('Y-m-d');
                        $grouped[$key]['last_invoice_id'] = $lastInvoices[$cId]->id;
                    }
                    if (isset($clientVisits[$cId])) {
                        $grouped[$key]['visits'] = $clientVisits[$cId];
                    }
                }
            }
        }

        // الترتيب تنازلياً حسب الإجمالي
        uasort($grouped, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return $grouped;
    }

    /**
     * تجميع بيانات التحصيلات مع التفاصيل (للعرض التفصيلي)
     */
    private function groupCollectionsDataWithDetails($collectionsData, $groupBy)
    {
        if ($groupBy === 'branch_tree') {
            return $this->buildBranchTreeGroupWithDetails($collectionsData);
        }

        $grouped = [];

        foreach ($collectionsData as $collection) {
            $key = $this->getGroupKey($collection, $groupBy);
            $name = $this->getGroupName($collection, $groupBy);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $name,
                    'count' => 0,
                    'amount' => 0.0,
                    'items' => [],
                ];
            }

            $grouped[$key]['count']++;
            $grouped[$key]['amount'] += (float) ($collection['amount'] ?? 0);
            $grouped[$key]['items'][] = $collection;
        }

        // ترتيب حسب التاريخ تنازلياً عند التجميع باليوم/الشهر، وإلا حسب المبلغ
        if (in_array($groupBy, ['day', 'day_employee', 'month', 'region_month'])) {
            uksort($grouped, function ($a, $b) {
                return strcmp($b, $a);
            });
        } elseif ($groupBy === 'node_type') {
            // ترتيب node_type حسب أولوية منطقية
            $order = ['branch' => 0, 'sub_branch' => 1, 'territory' => 2, 'group' => 3, 'outlet' => 4, 'region' => 5, 'zone' => 6, 'district' => 7];
            uksort($grouped, function ($a, $b) use ($order) {
                $ao = $order[$a] ?? 99;
                $bo = $order[$b] ?? 99;

                return $ao <=> $bo;
            });
        } else {
            uasort($grouped, function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });
        }

        return $grouped;
    }

    /**
     * بناء شجرة الفروع للملخص
     */
    private function buildBranchTreeGroup($collectionsData)
    {
        $allBranches = Branch::withoutGlobalScopes()->get()->keyBy('id');
        $nodes = [];
        $childrenMap = [];
        $roots = [];

        foreach ($allBranches as $branch) {
            $nodes[$branch->id] = [
                'id' => $branch->id,
                'parent_id' => $branch->parent_id,
                'name' => $branch->name,
                'node_type' => $branch->node_type,
                'direct_amount' => 0,
                'direct_count' => 0,
                'total_amount' => 0,
                'total_count' => 0,
            ];
            if ($branch->parent_id && isset($allBranches[$branch->parent_id])) {
                $childrenMap[$branch->parent_id][] = $branch->id;
            } else {
                $roots[] = $branch->id;
            }
        }

        // توزيع التحصيلات
        foreach ($collectionsData as $collection) {
            $bId = $collection['branch_id'];
            if (isset($nodes[$bId])) {
                $nodes[$bId]['direct_amount'] += (float) ($collection['amount'] ?? 0);
                $nodes[$bId]['direct_count']++;
            }
        }

        // حساب الإجماليات بشكل تراجعي (Post-order traversal)
        $calculateTotals = function ($nodeId) use (&$nodes, &$childrenMap, &$calculateTotals) {
            $amount = $nodes[$nodeId]['direct_amount'];
            $count = $nodes[$nodeId]['direct_count'];

            if (isset($childrenMap[$nodeId])) {
                foreach ($childrenMap[$nodeId] as $childId) {
                    $childTotals = $calculateTotals($childId);
                    $amount += $childTotals['amount'];
                    $count += $childTotals['count'];
                }
            }

            $nodes[$nodeId]['total_amount'] = $amount;
            $nodes[$nodeId]['total_count'] = $count;

            return ['amount' => $amount, 'count' => $count];
        };

        foreach ($roots as $rootId) {
            $calculateTotals($rootId);
        }

        // تفريغ الشجرة لطباعتها
        $grouped = [];
        $flattenTree = function ($nodeId, $level) use (&$nodes, &$childrenMap, &$grouped, &$flattenTree) {
            $node = $nodes[$nodeId];
            if ($node['total_count'] > 0) {
                $typeLabel = $this->getNodeTypeName($node['node_type'] ?? null);
                $grouped['b_'.$nodeId] = [
                    'name' => str_repeat('—', $level).' '.$node['name'],
                    'count' => $node['total_count'],
                    'amount' => $node['total_amount'],
                    'level' => $level,
                    'node_type' => $typeLabel,
                ];
                if (isset($childrenMap[$nodeId])) {
                    foreach ($childrenMap[$nodeId] as $childId) {
                        $flattenTree($childId, $level + 1);
                    }
                }
            }
        };

        foreach ($roots as $rootId) {
            $flattenTree($rootId, 0);
        }

        return $grouped;
    }

    /**
     * بناء شجرة الفروع للتفاصيل
     */
    private function buildBranchTreeGroupWithDetails($collectionsData)
    {
        $allBranches = Branch::withoutGlobalScopes()->get()->keyBy('id');
        $nodes = [];
        $childrenMap = [];
        $roots = [];

        foreach ($allBranches as $branch) {
            $nodes[$branch->id] = [
                'id' => $branch->id,
                'parent_id' => $branch->parent_id,
                'name' => $branch->name,
                'node_type' => $branch->node_type,
                'direct_amount' => 0,
                'direct_count' => 0,
                'total_amount' => 0,
                'total_count' => 0,
                'items' => [],
            ];
            if ($branch->parent_id && isset($allBranches[$branch->parent_id])) {
                $childrenMap[$branch->parent_id][] = $branch->id;
            } else {
                $roots[] = $branch->id;
            }
        }

        foreach ($collectionsData as $collection) {
            $bId = $collection['branch_id'];
            if (isset($nodes[$bId])) {
                $nodes[$bId]['direct_amount'] += (float) ($collection['amount'] ?? 0);
                $nodes[$bId]['direct_count']++;
                $nodes[$bId]['items'][] = $collection;
            }
        }

        $calculateTotals = function ($nodeId) use (&$nodes, &$childrenMap, &$calculateTotals) {
            $amount = $nodes[$nodeId]['direct_amount'];
            $count = $nodes[$nodeId]['direct_count'];

            if (isset($childrenMap[$nodeId])) {
                foreach ($childrenMap[$nodeId] as $childId) {
                    $childTotals = $calculateTotals($childId);
                    $amount += $childTotals['amount'];
                    $count += $childTotals['count'];
                }
            }

            $nodes[$nodeId]['total_amount'] = $amount;
            $nodes[$nodeId]['total_count'] = $count;

            return ['amount' => $amount, 'count' => $count];
        };

        foreach ($roots as $rootId) {
            $calculateTotals($rootId);
        }

        $grouped = [];
        $flattenTree = function ($nodeId, $level) use (&$nodes, &$childrenMap, &$grouped, &$flattenTree) {
            $node = $nodes[$nodeId];
            if ($node['total_count'] > 0) {
                $typeLabel = $this->getNodeTypeName($node['node_type'] ?? null);
                // للملخص والتفاصيل (التفاصيل تعرض العمليات المباشرة فقط لهذا الفرع لعدم تكرار العمليات)
                $grouped['b_'.$nodeId] = [
                    'name' => str_repeat('—', $level).' '.$node['name'].' ('.$typeLabel.')',
                    'count' => $node['direct_count'],
                    'amount' => $node['direct_amount'],
                    'items' => $node['items'],
                ];
                if (isset($childrenMap[$nodeId])) {
                    foreach ($childrenMap[$nodeId] as $childId) {
                        $flattenTree($childId, $level + 1);
                    }
                }
            }
        };

        foreach ($roots as $rootId) {
            $flattenTree($rootId, 0);
        }

        return $grouped;
    }

    /**
     * بناء شجرة الفروع كجدول شهري (Matrix)
     */
    private function buildBranchTreeGroupForMatrix($collectionsData)
    {
        $allBranches = Branch::withoutGlobalScopes()->get()->keyBy('id');
        $nodes = [];
        $childrenMap = [];
        $roots = [];

        foreach ($allBranches as $branch) {
            $nodes[$branch->id] = [
                'id' => $branch->id,
                'parent_id' => $branch->parent_id,
                'name' => $branch->name,
                'node_type' => $branch->node_type,
                'months' => array_fill(1, 12, 0.0), // 12 شهراً
                'direct_months' => array_fill(1, 12, 0.0),
                'total' => 0.0,
            ];
            if ($branch->parent_id && isset($allBranches[$branch->parent_id])) {
                $childrenMap[$branch->parent_id][] = $branch->id;
            } else {
                $roots[] = $branch->id;
            }
        }

        // توزيع التحصيلات
        foreach ($collectionsData as $collection) {
            $bId = $collection['branch_id'];
            if (isset($nodes[$bId])) {
                $date = $collection['date'];
                $month = $date instanceof Carbon ? (int) $date->month : (int) Carbon::parse($date)->month;
                $amount = (float) ($collection['amount'] ?? 0);
                $nodes[$bId]['direct_months'][$month] += $amount;
            }
        }

        // حساب الإجماليات بشكل تراجعي (Post-order traversal)
        $calculateTotals = function ($nodeId) use (&$nodes, &$childrenMap, &$calculateTotals) {
            $months = $nodes[$nodeId]['direct_months'];

            if (isset($childrenMap[$nodeId])) {
                foreach ($childrenMap[$nodeId] as $childId) {
                    $childTotals = $calculateTotals($childId);
                    for ($m = 1; $m <= 12; $m++) {
                        $months[$m] += $childTotals[$m];
                    }
                }
            }

            $nodes[$nodeId]['months'] = $months;
            $nodes[$nodeId]['total'] = array_sum($months);

            return $months;
        };

        foreach ($roots as $rootId) {
            $calculateTotals($rootId);
        }

        $grouped = [];
        $flattenTree = function ($nodeId, $level) use (&$nodes, &$childrenMap, &$grouped, &$flattenTree) {
            $node = $nodes[$nodeId];

            $hasActivity = false;
            foreach ($node['months'] as $mAmount) {
                if (abs($mAmount) > 0.001) {
                    $hasActivity = true;
                    break;
                }
            }

            if ($hasActivity) {
                $grouped['b_'.$nodeId] = [
                    'name' => str_repeat('—', $level).' '.$node['name'],
                    'months' => $node['months'],
                    'total' => $node['total'],
                    'level' => $level,
                ];
                if (isset($childrenMap[$nodeId])) {
                    foreach ($childrenMap[$nodeId] as $childId) {
                        $flattenTree($childId, $level + 1);
                    }
                }
            }
        };

        foreach ($roots as $rootId) {
            $flattenTree($rootId, 0);
        }

        return $grouped;
    }

    /**
     * مفتاح التجميع.
     *
     * الفرع والمنطقة والمجموعة والمنفذ: أقربُ عقدةٍ **من ذلك النوع** فوق
     * تبعيّة العميل (أو هي نفسها) — تُحسب مرّةً في {@see getCollectionsDetail()}.
     * من لا يقع تحت عقدةٍ من النوع يُجمَع في «بدون منطقة» وأخواتها؛ كان
     * يُجمَع تحت مفتاح `unknown` ويُسمّى باسم فرعِ أوّلِ صفٍّ وقع فيه، فيظهر
     * عملاءُ فروعٍ شتّى تحت اسم فرعٍ واحد.
     */
    private function getGroupKey($collection, $groupBy)
    {
        switch ($groupBy) {
            case 'day':
                $date = $collection['date'];

                return $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');
            case 'day_employee':
                $date = $collection['date'];
                $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');

                return $dateStr.'_'.($collection['employee_id'] ?? 'unknown');
            case 'month':
                $date = $collection['date'];

                return $date instanceof Carbon
                    ? $date->format('Y-m')
                    : (Carbon::parse($date)->format('Y-m'));
            case 'employee':
                return $collection['employee_id'] ?? 'unknown';
            case 'client':
                return $collection['client_id'] ?? 'unknown';
            case 'branch':
            case 'region':
            case 'group':
            case 'outlet':
                return $collection['node_'.$groupBy.'_id'] ?? 'none';
            case 'account_type':
                return $collection['account_type'] ?? 'unknown';
            case 'method':
                return $collection['method_key'] ?? 'unknown';
            case 'treasury':
                return $collection['account_id'] ?? 'unknown';
            default:
                return 'unknown';
        }
    }

    /**
     * الحصول على اسم المجموعة للعرض
     */
    private function getGroupName($collection, $groupBy)
    {
        switch ($groupBy) {
            case 'day':
                $carbon = $collection['date'] instanceof Carbon ? $collection['date'] : Carbon::parse($collection['date']);

                return $carbon->format('Y-m-d').' ('.$this->getArabicDayName($carbon->dayOfWeek).')';
            case 'day_employee':
                $carbon = $collection['date'] instanceof Carbon ? $collection['date'] : Carbon::parse($collection['date']);

                return $carbon->format('Y-m-d').' ('.$this->getArabicDayName($carbon->dayOfWeek).') - '.($collection['employee_name'] ?? 'غير محدد');
            case 'month':
                $date = $collection['date'];
                $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

                return $carbon->format('Y-m').' ('.$this->getArabicMonthName($carbon->month).')';
            case 'employee':
                return $collection['employee_name'] ?? 'غير محدد (لم يحدد موظف)';
            case 'client':
                return $collection['client_name'] ?? 'غير محدد (معاملة عامة بدون عميل)';
            case 'branch':
            case 'region':
            case 'group':
            case 'outlet':
                return $collection['node_'.$groupBy.'_name'] ?? self::NODE_TYPE_MISSING[$groupBy];
            case 'account_type':
                return $collection['account_type'] ?? 'غير محدد (لم يحدد نوع حساب)';
            case 'method':
                return $collection['method_label'] ?? 'غير محدد';
            case 'treasury':
                return $collection['treasury_account'] ?? 'غير محدد (لم يحدد خزانة أو بنك)';
            default:
                return 'غير محدد (معيار تجميع غير معروف)';
        }
    }

    /**
     * ترجمة قيمة node_type إلى اسم عربي مع أيقونة
     */
    private function getNodeTypeName(?string $nodeType): string
    {
        $map = [
            'branch' => '🏢 فرع رئيسي',
            'sub_branch' => '🏬 فرع فرعي',
            'territory' => '📍 منطقة',
            'group' => '👥 مجموعة',
            'outlet' => '🏪 منفذ',
            'region' => '🗺️ إقليم',
            'zone' => '🔷 نطاق',
            'district' => '📌 حي/دائرة',
        ];

        if (! $nodeType) {
            return '📂 غير مصنّف';
        }

        return $map[$nodeType] ?? ('📂 '.$nodeType);
    }

    /**
     * الحصول على اسم الشهر بالعربية
     */
    private function getArabicMonthName($month)
    {
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        return $months[(int) $month] ?? '';
    }

    /**
     * الحصول على اسم اليوم بالعربية
     */
    private function getArabicDayName($dayOfWeek)
    {
        $days = [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        return $days[$dayOfWeek] ?? '';
    }

    /**
     * جلب تفاصيل التحصيلات (نفس منطق DashboardController - فلترة حسب فرع العميل + طرح مردودات المبيعات)
     */
    private function getCollectionsDetail($startDate, $endDate, $branchId, $employeeId = null, $clientId = null, $neighborhoodId = null, $statusId = null, $showZero = false, $targetId = null, $categoryId = null, $registrationStageId = null, $paymentMethod = 'all')
    {
        /*
            ══════════ الجلبُ على أساس لوحة المبيعات ══════════

            كان التقرير يلتقط التحصيلَ من **طرف الخزينة/البنك** (مدينُ حسابٍ تحت
            الخزائن أو البنوك) والعميلَ من رأس القيد. واللوحةُ (`SalesLedger`)
            تقرؤه من **طرف العميل**: دائنُ حساب العميل في سند القبض، ومدينُه في
            المرتجع والإشعار (ردُّ نقد)، والعميلُ من الحساب نفسِه. فاختلفا حيث لا
            يوجد `journal_entries.client_id` أو حيث سُدِّد الحسابُ من غير الخزينة.

            الآن الصفُّ هو طرفُ العميل، والخزينةُ تُقرأ من الطرف المقابل في القيد
            نفسِه للعرض فقط (الحساب، طريقة التحصيل). و«طريقة التحصيل» تُضيّق على
            وجود طرفٍ من خزائن النوع المطلوب في القيد.
        */
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->map(fn ($id) => (int) $id)->all();

        $collectedAccounts = $paymentMethod !== 'all'
            ? $this->accountsForPaymentMethod($paymentMethod, $treasuryAndBankAccounts)
            : $treasuryAndBankAccounts;
        if ($paymentMethod !== 'all' && empty($collectedAccounts)) {
            return collect([]);
        }

        // توسيع الفرع: يشمل الفرع نفسه + كل الـ territories التابعة له (المجموعات بعد الترحيل)
        $branchIds = $branchId ? $this->expandBranchToDescendants($branchId) : null;

        $collectionTypes = SalesLedger::COLLECTION_TYPES;
        $refundTypes = array_merge(SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES);
        $cancellationTypes = SalesLedger::CANCELLATION_TYPES;
        $cancelledSql = SalesLedger::cancellationSql('journal_entries');

        $legsQuery = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_details.account_id')
            ->leftJoin('clients', 'clients.id', '=', 'accounts.client_id')
            ->whereNotNull('accounts.client_id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->where(function ($q) use ($collectionTypes, $refundTypes, $cancellationTypes, $cancelledSql) {
                /* سندُ قبض: دائنُ العميل. السندُ الملغى يبقى صفّاً وعكسُه (PCAN)
                   صفٌّ سالب، فصافيهما صفرٌ — وهو رقمُ اللوحة نفسُه التي تُخرج
                   الاثنين معاً من «التحصيلات» وتعرض الملغى في بطاقةٍ مستقلّة. */
                $q->where(function ($w) use ($collectionTypes) {
                    $w->whereIn('journal_entries.reference_type', $collectionTypes)
                        ->where('journal_entry_details.credit', '>', 0);
                })
                // ردُّ نقدٍ مع مرتجعٍ أو إشعار: مدينُ العميل
                ->orWhere(function ($w) use ($refundTypes) {
                    $w->whereIn('journal_entries.reference_type', $refundTypes)
                        ->where('journal_entry_details.debit', '>', 0);
                })
                // إلغاءُ سند قبض: عكسُ التحصيل
                ->orWhere(function ($w) use ($cancellationTypes) {
                    $w->whereIn('journal_entries.reference_type', $cancellationTypes)
                        ->where('journal_entry_details.debit', '>', 0);
                });
            });

        SalesLedger::excludeWalletAccounts($legsQuery, 'accounts');

        if ($paymentMethod !== 'all') {
            $legsQuery->whereExists(function ($sub) use ($collectedAccounts) {
                $sub->select(DB::raw(1))
                    ->from('journal_entry_details as tl')
                    ->whereColumn('tl.journal_entry_id', 'journal_entries.id')
                    ->whereIn('tl.account_id', $collectedAccounts);
            });
        }

        /* «تابع لـ» = عقدةُ **العميل** وحدَها — نفسُ قاعدة دفتر العملاء
           (`SalesLedger::matchClientNodes`: `tree_node_id` ثمّ الأعمدة القديمة). */
        if ($branchIds) {
            $legsQuery->whereIn('accounts.client_id', function ($sub) use ($branchIds) {
                $sub->select('id')->from('clients');
                SalesLedger::matchClientNodes($sub, 'clients', $branchIds);
            });
        }

        if ($employeeId) {
            $legsQuery->where('journal_entries.created_by_employee', $employeeId);
        }
        if ($clientId) {
            $legsQuery->where('accounts.client_id', $clientId);
        }
        if ($neighborhoodId) {
            $legsQuery->join('neighborhoods', 'clients.id', '=', 'neighborhoods.client_id')
                ->where('neighborhoods.region_id', $neighborhoodId);
        }
        if ($targetId) {
            $legsQuery->where('clients.customer_target_id', $targetId);
        }
        if ($categoryId) {
            $legsQuery->where('clients.category_id', $categoryId);
        }

        if ($statusId && ! in_array($statusId, ['all', 'except_suspended'])) {
            $legsQuery->where('clients.status_id', $statusId);
        } elseif ($statusId === 'except_suspended') {
            $stoppedIds = Statuses::stoppedClientStatusIds();
            $legsQuery->where(function ($q) use ($stoppedIds) {
                $q->whereNotIn('clients.status_id', $stoppedIds)
                    ->orWhereNull('clients.status_id');
            });
        }

        // وضعُ العميل («مرحلة التسجيل») — منفصلٌ عن فلتر الحالة أعلاه.
        if ($registrationStageId) {
            $registrationStageId === 'no_stage'
                ? $legsQuery->whereNull('clients.registration_stage_id')
                : $legsQuery->where('clients.registration_stage_id', $registrationStageId);
        }

        // استبعادٌ صامتٌ للفترات المحاسبية المقفلة — كبقيّة تقارير المبيعات ولوحتها
        $this->excludeClosedRanges($legsQuery, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($legsQuery);

        $legTypes = $legsQuery->pluck('journal_entries.reference_type', 'journal_entry_details.id');

        $collectionDetailIds = $legTypes->filter(fn ($type) => in_array((string) $type, $collectionTypes, true))->keys()->all();
        $returnDetailIds = $legTypes->filter(fn ($type) => in_array((string) $type, $refundTypes, true))->keys()->all();
        $cancellationDetailIds = $legTypes->filter(fn ($type) => in_array((string) $type, $cancellationTypes, true))->keys()->all();
        $returnDetailIdsFlipped = array_flip($returnDetailIds);
        $cancellationDetailIdsFlipped = array_flip($cancellationDetailIds);

        // ========== 3. دمج وجلب البيانات ==========
        $allDetailIds = array_merge($collectionDetailIds, $returnDetailIds, $cancellationDetailIds);

        if (empty($allDetailIds)) {
            return collect([]);
        }

        $allDetails = JournalEntryDetail::whereIn('id', $allDetailIds)
            ->with([
                'journalEntry.client.Neighborhoodname.Region',
                'journalEntry.client.branch',
                'account',
                'journalEntry.details.account',
            ])
            ->get();

        // جلب بيانات الموظفين دفعة واحدة
        $userIds = $allDetails->pluck('journalEntry.created_by_employee')->unique()->filter()->toArray();
        $users = User::whereIn('id', $userIds)->with('branch')->get()->keyBy('id');

        // تحضير جميع الفروع مسبقاً لاستخراج الأصول (Ancestors)
        $allBranches = Branch::withoutGlobalScopes()->get()->keyBy('id');
        $allCategories = CategoriesClient::pluck('name', 'id')->toArray();
        $allCustomerTargets = CustomerTarget::pluck('name', 'id')->toArray();
        $allCustomerTargetColors = CustomerTarget::pluck('color', 'id')->toArray();
        $allCustomerTargetPriorities = CustomerTarget::pluck('priority', 'id')->toArray();
        /* المستويات مرتّبةً من الأعلى — مرّةً واحدة. كانت تُجلب داخل حلقة الصفوف
           لكلّ عميلٍ تغيّر مستواه: 1178 استعلاماً متطابقاً في تقرير سنة. */
        $targetIdsOrdered = CustomerTarget::orderBy('priority', 'desc')->pluck('id')->toArray();
        /* نوعُ كلّ عقدةٍ كما يُعرض — `Branch::displayNodeType()` لا `node_type`
           الخام: المناطق والمنافذ القديمة مخزَّنةٌ «مجموعة»، فمنطقةُ الرياض لا
           تُعرف منطقةً إلا بقراءة العرض. والفرعُ وحده يُقرأ بالمخزَّن
           (`nodeTypeKey`) لأنه وحدةٌ محاسبية لا تُستنتج من الاسم. */
        $childrenByParent = [];
        foreach ($allBranches as $node) {
            if ($node->parent_id) {
                $childrenByParent[(int) $node->parent_id][] = $node;
            }
        }
        $displayTypes = [];
        foreach ($allBranches as $node) {
            $displayTypes[(int) $node->id] = $node->nodeTypeKey() === 'branch'
                ? 'branch'
                : Branch::displayNodeType($node, $childrenByParent[(int) $node->id] ?? []);
        }

        /* أقربُ عقدةٍ من النوع المطلوب: العقدةُ نفسها ثمّ أسلافها صعوداً.
           كان هنا بحثٌ **نزولاً** في الأبناء حين لا يوجد سلف — فعميلٌ على فرعٍ
           يُنسب لأوّل منطقةٍ أو مجموعةٍ تحت ذلك الفرع ولا علاقة له بها، وكان
           الفرعُ الفرعيّ يُقبل «منفذاً» والمنطقةُ تُقبل من `is_territory`. */
        $nearestCache = [];
        $nearestOfType = function ($nodeId, string $type) use ($allBranches, $displayTypes, &$nearestCache) {
            if (! $nodeId) {
                return null;
            }
            $cacheKey = $nodeId.'|'.$type;
            if (array_key_exists($cacheKey, $nearestCache)) {
                return $nearestCache[$cacheKey];
            }

            $found = null;
            $current = $allBranches[(int) $nodeId] ?? null;
            $guard = 0;
            while ($current && $guard++ < 50) {
                if (($displayTypes[(int) $current->id] ?? null) === $type) {
                    $found = $current;
                    break;
                }
                $current = $current->parent_id ? ($allBranches[(int) $current->parent_id] ?? null) : null;
            }

            return $nearestCache[$cacheKey] = $found;
        };

        // تجهيز بيانات العملاء والحسابات والفواتير والحالات لتجنب استعلامات N+1
        $allStatuses = Statuses::pluck('name', 'id')->toArray();
        $allStatusColors = Statuses::pluck('color', 'id')->toArray();

        $clientIdsToFetch = [];
        $invoiceIdsToFetch = [];
        $accountIdsToFetch = [];

        foreach ($allDetails as $detail) {
            $je = $detail->journalEntry;
            if ($je) {
                if ($je->client_id) {
                    $clientIdsToFetch[] = $je->client_id;
                }
                if ($je->invoice_id) {
                    $invoiceIdsToFetch[] = $je->invoice_id;
                }
                if ($je->details) {
                    foreach ($je->details as $d) {
                        if ($d->account_id) {
                            $accountIdsToFetch[] = $d->account_id;
                        }
                    }
                }
            }
        }

        $accountIdsToFetch = array_unique(array_filter($accountIdsToFetch));
        $accountsData = DB::table('accounts')->whereIn('id', $accountIdsToFetch)->pluck('client_id', 'id')->toArray();

        foreach ($accountsData as $cId) {
            if ($cId) {
                $clientIdsToFetch[] = $cId;
            }
        }

        $invoiceIdsToFetch = array_unique(array_filter($invoiceIdsToFetch));
        $invoicesData = DB::table('invoices')->whereIn('id', $invoiceIdsToFetch)->pluck('client_id', 'id')->toArray();

        foreach ($invoicesData as $cId) {
            if ($cId) {
                $clientIdsToFetch[] = $cId;
            }
        }

        $clientIdsToFetch = array_unique(array_filter($clientIdsToFetch));
        $clientsData = DB::table('clients')->whereIn('id', $clientIdsToFetch)->get()->keyBy('id');
        // 1. جلب الفروع الجغرافية من خلال علاقة الحي بالعميل (العميل في جدول الأحياء)
        $clientNeighborhoods1 = DB::table('neighborhoods')
            ->whereIn('client_id', $clientIdsToFetch)
            ->pluck('region_id', 'client_id')
            ->toArray();

        // 2. جلب الفروع الجغرافية من خلال علاقة العميل بالحي (الحي في جدول العملاء - إن وجد الحقل)
        $clientNeighborhoods2 = [];
        if (\Schema::hasColumn('clients', 'neighborhood_id')) {
            $clientNeighborhoods2 = DB::table('clients')
                ->join('neighborhoods', 'clients.neighborhood_id', '=', 'neighborhoods.id')
                ->whereIn('clients.id', $clientIdsToFetch)
                ->pluck('neighborhoods.region_id', 'clients.id')
                ->toArray();
        }

        // دمج العلاقتين معاً لضمان تغطية أي من الطريقتين بقاعدة البيانات
        $clientNeighborhoods = $clientNeighborhoods1 + $clientNeighborhoods2;

        // جلب موظفي العملاء دفعة واحدة لتفادي استعلامات N+1
        $clientEmployeeIds = $clientsData->pluck('employee_id')->filter()->unique()->toArray();
        $clientEmployees = DB::table('employees')->whereIn('id', $clientEmployeeIds)->get()->keyBy('id');

        /* ========== الفواتير التي سدّدها كلُّ سند ==========
           سندٌ على فاتورةٍ واحدة يحمل `invoice_id`؛ وسندٌ وُزِّع على عدّة
           فواتير (`linking_type = multiple`) يحمل معرّفاتها في
           `batch_reference` مفصولةً بفاصلة («4464, 5439»). وما لا يحمل هذا
           ولا ذاك سدادٌ من الرصيد السابق بلا فاتورة. */
        $entryInvoiceIds = [];
        foreach ($allDetails as $detail) {
            $je = $detail->journalEntry;
            if (! $je) {
                continue;
            }
            $ids = [];
            if ($je->invoice_id) {
                $ids[] = (int) $je->invoice_id;
            } elseif (! empty($je->batch_reference)) {
                foreach (preg_split('/\s*,\s*/', trim((string) $je->batch_reference)) as $part) {
                    if (ctype_digit($part)) {
                        $ids[] = (int) $part;
                    }
                }
            }
            $entryInvoiceIds[$je->id] = array_values(array_unique($ids));
        }
        $linkedInvoices = $this->loadLinkedInvoices(array_merge([], ...array_values($entryInvoiceIds)));

        foreach ($allDetails as $detail) {
            $journalEntry = $detail->journalEntry;
            $createdByUser = $users[$journalEntry->created_by_employee] ?? null;
            $isReturn = isset($returnDetailIdsFlipped[$detail->id]);
            // إلغاءُ سند قبض: تحصيلٌ سالب — لا مرتجع
            $isCancellation = isset($cancellationDetailIdsFlipped[$detail->id]);

            $regionName = null;
            $regionId = null;
            if ($journalEntry->client && $journalEntry->client->Neighborhoodname && $journalEntry->client->Neighborhoodname->Region) {
                $regionName = $journalEntry->client->Neighborhoodname->Region->name;
                $regionId = $journalEntry->client->Neighborhoodname->Region->id;
            }

            /* الصفُّ طرفُ العميل: دائنُه تحصيلٌ موجب، ومدينُه في المرتجع والإشعار
               والإلغاء تحصيلٌ سالب — نفسُ معادلة `SalesLedger::periodTotals`. */
            $amount = ($isReturn || $isCancellation)
                ? -1 * ((float) $detail->debit - (float) $detail->credit)
                : ((float) $detail->credit - (float) $detail->debit);

            // طرفُ الخزينة/البنك في القيد نفسِه — للعرض (الحساب وطريقة التحصيل) لا للمبلغ
            $treasuryLeg = $journalEntry->details
                ? $journalEntry->details->first(fn ($d) => in_array((int) $d->account_id, $treasuryAndBankAccounts, true))
                : null;
            $treasuryAccount = $treasuryLeg?->account;

            $clientBranch = null;
            $nodeType = null;
            $mainBranch = null;
            $territory = null;
            $groupNode = null;
            $outlet = null;

            // استخراج العميل بطريقة مباشرة من المتغيرات المجهزة
            $clientData = null;

            // 1. نبحث أولاً في أطراف القيد عن حساب مرتبط بعميل
            if ($journalEntry->details) {
                foreach ($journalEntry->details as $d) {
                    if ($d->account_id) {
                        $accClientId = $accountsData[$d->account_id] ?? null;
                        if ($accClientId) {
                            $c = $clientsData[$accClientId] ?? null;
                            if ($c) {
                                $clientData = $c;
                                break;
                            }
                        }
                    }
                }
            }

            // 2. إذا لم نجد، نستخدم العميل المسجل في رأس القيد
            if (! $clientData && $journalEntry->client_id) {
                $clientData = $clientsData[$journalEntry->client_id] ?? null;
            }

            // 3. محاولة أخيرة عبر الفاتورة المرتبطة إن وجدت
            if (! $clientData && $journalEntry->invoice_id) {
                $invClientId = $invoicesData[$journalEntry->invoice_id] ?? null;
                if ($invClientId) {
                    $clientData = $clientsData[$invClientId] ?? null;
                }
            }

            // تجهيز المتغيرات النهائية مع إيضاح المصدر للقيم غير المحددة
            $clientCode = '-';
            $statusName = 'غير محدد (معاملة عامة بدون عميل)';
            $statusColor = 'secondary';
            $clientName = 'عام / بدون عميل';
            $clientCategory = 'غير مقيم (معاملة عامة بدون عميل)';
            $clientCategoryColor = '#6c757d';
            $clientRealCategory = 'غير محدد (معاملة عامة بدون عميل)';
            $targetChange = 'none';
            $clientPreviousCategory = 'غير مقيّم (معاملة عامة بدون عميل)';
            $finalClientId = $journalEntry->client_id;
            $clientBranchId = null;

            $clientUnspecified = true;
            $statusUnspecified = true;
            $categoryUnspecified = true;
            $realCategoryUnspecified = true;

            if ($clientData) {
                $clientUnspecified = false;
                $clientName = $clientData->trade_name ?? $clientData->name ?? 'عام';
                $clientCode = $clientData->code ?: '-';
                $finalClientId = $clientData->id;
                /* التبعيّةُ الصريحة (`tree_node_id`) أوّلاً — نفسُ عقدة فلتر
                   «تابع لـ». كانت تُغفَل فيُجمَّع ٤٩ عميلاً تحت عقدةٍ غير عقدتهم. */
                $clientBranchId = ! empty($clientData->tree_node_id) ? (int) $clientData->tree_node_id : null;
                $clientBranchId ??= $clientNeighborhoods[$clientData->id] ?? null;
                if (! $clientBranchId && isset($clientData->group_id) && $clientData->group_id) {
                    $clientBranchId = $clientData->group_id;
                }
                if (! $clientBranchId) {
                    $clientBranchId = $clientData->branch_id;
                }

                $clientBranch = $clientBranchId ? ($allBranches[$clientBranchId] ?? null) : null;
                $nodeType = $clientBranch ? ($displayTypes[(int) $clientBranch->id] ?? $clientBranch->nodeTypeKey()) : null;

                $clientCategory = 'غير مقيّم (العميل ليس له تارجت)';
                $clientCategoryColor = '#6c757d';
                $targetChange = 'none';
                $clientPreviousCategory = 'غير مقيّم';
                $clientPreviousCategoryColor = '#6c757d';
                if ($clientData->customer_target_id) {
                    $categoryUnspecified = false;
                    $clientCategory = $allCustomerTargets[$clientData->customer_target_id] ?? 'غير مقيّم';
                    $clientCategoryColor = $allCustomerTargetColors[$clientData->customer_target_id] ?? '#6c757d';

                    if (! empty($clientData->previous_target_id) && $clientData->previous_target_id != $clientData->customer_target_id) {
                        // المستويات مرتبة من الأعلى إلى الأقل — مجلوبةٌ قبل الحلقة
                        $oldIndex = array_search($clientData->previous_target_id, $targetIdsOrdered);
                        $newIndex = array_search($clientData->customer_target_id, $targetIdsOrdered);

                        if ($oldIndex !== false && $newIndex !== false) {
                            if ($newIndex < $oldIndex) {
                                // الفئة الجديدة أعلى في الترتيب (تقدم)
                                $targetChange = 'upgrade';
                            } elseif ($newIndex > $oldIndex) {
                                // الفئة الجديدة أقل في الترتيب (تراجع)
                                $targetChange = 'downgrade';
                            }
                        }
                    }
                }
                if ($clientData && ! empty($clientData->previous_target_id)) {
                    $clientPreviousCategory = $allCustomerTargets[$clientData->previous_target_id] ?? 'غير مقيّم';
                    $clientPreviousCategoryColor = $allCustomerTargetColors[$clientData->previous_target_id] ?? '#6c757d';
                }

                if ($clientData->status_id) {
                    $statusUnspecified = false;
                    $statusName = $allStatuses[$clientData->status_id] ?? 'غير محدد';
                    $statusColor = $allStatusColors[$clientData->status_id] ?? 'secondary';
                } else {
                    $statusName = 'غير محدد (العميل بدون حالة)';
                }

                if ($clientData->category_id) {
                    $realCategoryUnspecified = false;
                    $clientRealCategory = $allCategories[$clientData->category_id] ?? 'غير محدد';
                } else {
                    $clientRealCategory = 'غير محدد (العميل بدون تصنيف)';
                }
            }

            $clientBranch = $clientBranchId ? ($allBranches[$clientBranchId] ?? null) : null;

            // العقد الهرمية من تبعيّة العميل الفعلية — أقربُ عقدةٍ من كلّ نوع
            $mainBranch = $nearestOfType($clientBranch?->id, 'branch');
            $territory = $nearestOfType($clientBranch?->id, 'region');
            $groupNode = $nearestOfType($clientBranch?->id, 'group');
            $outlet = $nearestOfType($clientBranch?->id, 'outlet');

            // الفواتير المسدَّدة بهذا السند — ما وُجد منها فعلاً
            $rowInvoices = collect($entryInvoiceIds[$journalEntry->id] ?? [])
                ->map(fn ($id) => $linkedInvoices[$id] ?? null)
                ->filter()
                ->values();
            $methodKey = (int) ($treasuryAccount?->parent_id) === SystemAccountIds::BANK_ACCOUNTS_PARENT ? 'bank' : 'cash';

            // تفصيل مصدر الموظف مع إيضاح المصدر
            $employeeName = null;
            $employeeUnspecified = true;
            if ($createdByUser) {
                $employeeName = $createdByUser->name;
                $employeeUnspecified = false;
            } elseif ($clientData && ! empty($clientData->employee_id)) {
                $emp = $clientEmployees[$clientData->employee_id] ?? null;
                if ($emp) {
                    $employeeName = trim(($emp->first_name ?? '').' '.($emp->middle_name ?? '').' '.($emp->last_name ?? ''));
                    $employeeUnspecified = false;
                }
            }
            if (empty($employeeName)) {
                $employeeName = 'غير محدد (لا يوجد منشئ قيد ولا مندوب للعميل)';
            }

            $branchNameName = $clientBranch?->name ?? ($createdByUser?->branch?->name ?? null);
            $branchUnspecified = true;
            if ($branchNameName) {
                $branchUnspecified = false;
            } else {
                $branchNameName = 'غير محدد (لا يوجد فرع للعميل ولا لمنشئ القيد)';
            }

            $treasuryAccountName = $treasuryAccount?->name;
            $treasuryUnspecified = true;
            if ($treasuryAccountName) {
                $treasuryUnspecified = false;
            } else {
                $treasuryAccountName = 'غير محدد (الحساب المالي محذوف أو غير معرف)';
            }

            $finalRegionName = $regionName;
            $regionUnspecified = true;
            if ($finalRegionName) {
                $regionUnspecified = false;
            } else {
                $finalRegionName = 'غير محدد (العميل بدون حي أو منطقة)';
            }

            $collectionsDetail[] = [
                'id' => $journalEntry->id,
                'date' => $journalEntry->date,
                'reference_number' => $journalEntry->reference_number,
                'description' => $journalEntry->description,
                'employee_name' => $employeeName,
                'employee_id' => $journalEntry->created_by_employee,
                'employee_unspecified' => $employeeUnspecified,
                'client_name' => $clientName,
                'client_id' => $finalClientId,
                'client_unspecified' => $clientUnspecified,
                'status_unspecified' => $statusUnspecified,
                'category_unspecified' => $categoryUnspecified,
                'real_category_unspecified' => $realCategoryUnspecified,
                'branch_unspecified' => $branchUnspecified,
                'treasury_unspecified' => $treasuryUnspecified,
                'region_unspecified' => $regionUnspecified,
                'client_code' => $clientCode,
                'client_status' => $statusName,
                'client_status_color' => $statusColor,
                'client_category' => $clientCategory,
                'client_category_color' => $clientCategoryColor,
                'client_real_category' => $clientRealCategory,
                'target_change' => $targetChange,
                'client_previous_category' => $clientPreviousCategory ?? 'غير مقيّم',
                'client_previous_category_color' => $clientPreviousCategoryColor ?? '#6c757d',
                'branch_name' => $branchNameName,
                'branch_id' => $clientBranchId ?? $journalEntry->branch_id,

                'main_branch_name' => $mainBranch?->name,
                'main_branch_id' => $mainBranch?->id,
                'territory_name' => $territory?->name,
                'territory_id' => $territory?->id,
                'group_node_name' => $groupNode?->name,
                'group_node_id' => $groupNode?->id,
                'outlet_name' => $outlet?->name,
                'outlet_id' => $outlet?->id,

                'node_branch_id' => $mainBranch?->id,
                'node_branch_name' => $mainBranch?->name,
                'node_region_id' => $territory?->id,
                'node_region_name' => $territory?->name,
                'node_group_id' => $groupNode?->id,
                'node_group_name' => $groupNode?->name,
                'node_outlet_id' => $outlet?->id,
                'node_outlet_name' => $outlet?->name,

                'invoices' => $rowInvoices->all(),
                'invoice_ids' => $rowInvoices->pluck('id')->all(),
                'invoice_codes' => $rowInvoices->pluck('code')->all(),
                'invoice_total' => (float) $rowInvoices->sum('grand_total'),
                'invoice_remaining' => (float) $rowInvoices->sum('remaining'),
                'method_key' => $methodKey,
                'method_label' => self::METHOD_LABELS[$methodKey],
                'entry_reference' => $journalEntry->reference_number,

                'treasury_account' => $treasuryAccountName,
                'account_id' => $treasuryLeg?->account_id,
                'amount' => $amount,
                'account_type' => $this->getAccountType($treasuryAccount),
                'note' => $detail->description ?: ($treasuryLeg?->description ?: $journalEntry->description),
                'region_name' => $finalRegionName,
                'region_id' => $regionId,
                'node_type' => $nodeType,
                'is_return' => $isReturn,
                'is_cancellation' => $isCancellation,
            ];
        }

        usort($collectionsDetail, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return collect($collectionsDetail);
    }

    /**
     * بحثُ العملاء لمُنتقي الشاشة — المسارُ معرَّفٌ منذ زمن والدالّةُ لم
     * تكن، فكان كلُّ بحثٍ في حقل العميل يعود بخطأ.
     */
    public function searchClients(Request $request)
    {
        return $this->searchReportClients($request);
    }

    /**
     * أيقع العميلُ في نطاق هذا المستخدم؟ — عقدةُ الشاشة وما تحتها، ونطاقُ
     * الرؤية فوقها.
     *
     * نافذتا تفاصيل العميل كانتا تقبلان أيَّ `client_id`: من يملك صلاحية
     * التقرير يقرأ ملاحظاتِ أيِّ عميلٍ في المنشأة وصورَه وزياراتِه ورصيدَه
     * بتبديل رقمٍ في الطلب، بينما الشاشةُ نفسُها مقصورةٌ على فرعه.
     */
    private function clientVisibleForReport(Request $request, $clientId): bool
    {
        $user = auth()->user();
        $isMainBranch = (bool) optional(Branch::findCached($user->branch_id))->is_main;
        $node = $this->getEffectiveBranchId($request, $isMainBranch, $user->branch_id);

        $query = Client::query()->whereKey((int) $clientId);

        if ($node) {
            $query->inTreeNode($node, ! $request->boolean('exact_node'));
        }

        \App\Support\DataScope::constrainOwner($query, 'employee_id');

        return $query->exists();
    }

    /**
     * خياراتُ فلاتر الفرع/المنطقة/المجموعة/المنفذ من عقد «تابع لـ» (نطاقُ
     * المستخدم نفسُه). النوعُ نوعُ العرض (`AffiliationTree::nodes()` يسمّيه
     * بـ`displayNodeType`)، والفرعُ بالمخزَّن — نفسُ ما يُحسب به `node_*_id`.
     *
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    private function nodeTypeOptions(array $treeNodes): array
    {
        $labelToType = [];
        foreach (array_keys(self::NODE_TYPE_MISSING) as $type) {
            $labelToType[Branch::nodeTypeMeta($type)['label']] = $type;
        }

        $options = array_fill_keys(array_keys(self::NODE_TYPE_MISSING), []);
        foreach ($treeNodes as $node) {
            $type = ($node['node_type'] ?? null) === 'branch'
                ? 'branch'
                : ($labelToType[$node['type_label'] ?? ''] ?? null);
            if ($type) {
                $options[$type][] = ['id' => (int) $node['id'], 'name' => $node['name']];
            }
        }

        return $options;
    }

    /** مفتاحُ التجميع بعد ترجمة القديم، وإلّا «العميل» */
    private function normalizeGroupBy($groupBy): string
    {
        $groupBy = self::GROUP_BY_ALIASES[$groupBy] ?? $groupBy;

        return array_key_exists((string) $groupBy, $this->groupByOptions) ? (string) $groupBy : 'day_employee';
    }

    /**
     * الحساباتُ المدينة التي تُقرأ بحسب «طريقة التحصيل»:
     * `all` كلُّها، `cash` الخزائن، `bank` البنوك، `account:<id>` حسابٌ بعينه.
     * قيمةٌ لا تُعرف تعود بالكلّ لا بالفراغ — رابطٌ قديم لا يُخفي التقرير.
     */
    private function accountsForPaymentMethod($paymentMethod, array $allAccounts): array
    {
        $paymentMethod = (string) $paymentMethod;

        if ($paymentMethod === 'cash' || $paymentMethod === 'bank') {
            $parent = $paymentMethod === 'bank' ? SystemAccountIds::BANK_ACCOUNTS_PARENT : SystemAccountIds::CASH_TREASURIES_PARENT;

            return Account::whereIn('id', $allAccounts)->where('parent_id', $parent)->pluck('id')->all();
        }

        if (str_starts_with($paymentMethod, 'account:')) {
            $id = (int) substr($paymentMethod, 8);

            return in_array($id, array_map('intval', $allAccounts), true) ? [$id] : [];
        }

        return $allAccounts;
    }

    /** خيارات «طريقة التحصيل»: الخزائن والبنوك بأسمائها */
    private function paymentAccountsForFilter(): array
    {
        $accounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->orderBy('name')->get(['id', 'name', 'parent_id']);

        return [
            'cash' => $accounts->where('parent_id', SystemAccountIds::CASH_TREASURIES_PARENT)->values(),
            'bank' => $accounts->where('parent_id', SystemAccountIds::BANK_ACCOUNTS_PARENT)->values(),
        ];
    }

    /**
     * الفواتير المسدَّدة بالسندات — إجماليّها والمتبقّي عليها **الآن**.
     *
     * المتبقّي بمعادلة `Invoice::calculateRemainingAmount()` نفسها: الإجمالي
     * ناقصَ دفعات العميل (`payments_process` من نوع `client payments`) ناقصَ
     * ما أطفأه المرتجع. `due_value` المخزَّن يطابقها في ٩٧٪ من الفواتير
     * وحسب، فلا يُقرأ. والفاتورةُ الملغاة لا متبقّيَ عليها.
     *
     * @return array<int, array{id: int, code: string, grand_total: float, paid: float, remaining: float}>
     */
    private function loadLinkedInvoices(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_unique(array_filter(array_map('intval', $invoiceIds))));
        if (empty($invoiceIds)) {
            return [];
        }

        $result = [];
        foreach (array_chunk($invoiceIds, 2000) as $chunk) {
            $paid = DB::table('payments_process')
                ->where('type', 'client payments')
                ->whereIn('invoice_id', $chunk)
                ->groupBy('invoice_id')
                ->selectRaw('invoice_id, SUM(amount) as paid')
                ->pluck('paid', 'invoice_id');

            $invoices = DB::table('invoices')
                ->whereIn('id', $chunk)
                ->get(['id', 'code', 'grand_total', 'returned_payment', 'voided_at']);

            foreach ($invoices as $invoice) {
                $total = (float) $invoice->grand_total;
                $paidAmount = (float) ($paid[$invoice->id] ?? 0);
                $remaining = $invoice->voided_at
                    ? 0.0
                    : max(0.0, round($total - $paidAmount - (float) $invoice->returned_payment, 2));

                $result[(int) $invoice->id] = [
                    'id' => (int) $invoice->id,
                    'code' => $invoice->code ?: '#'.$invoice->id,
                    'grand_total' => $total,
                    'paid' => $paidAmount,
                    'remaining' => $remaining,
                ];
            }
        }

        return $result;
    }

    /**
     * المديونيةُ القائمة في نهاية المدى — رصيدُ العملاء في الأستاذ، كبطاقة «الذمم» في لوحة المبيعات.
     *
     * كانت البطاقةُ «المتبقي للتحصيل» = المتبقّي على الفواتير التي سدّدتها
     * سنداتُ المدى وحدَها، محسوباً من `payments_process`: فعميلٌ لم يدفع شيئاً
     * في المدى لا يدخلها، والتحصيلُ «على الحساب» لا يُنقص فاتورة — ١٢٩ ألفاً
     * في الشاشة والمديونيةُ في اللوحة ٢٧٧ ألفاً، فقُرئ الرقمُ خطأً.
     *
     * بلا فلترٍ يخصّ العميل: أرصدةُ عملاء العقدة كلُّهم — رقمُ اللوحة نفسُه.
     * ومع عميلٍ محدّد: رصيدُه. ومع فلترٍ آخر (موظف، حالة، هدف، تصنيف، حيّ،
     * نوع عقدة): أرصدةُ العملاء الظاهرين في التقرير.
     *
     * @return array{amount: float, clients: int, as_of: string, basis: string}
     */
    private function outstandingDebt(Request $request, $effectiveBranchId, $endDate, $rows, array $nodeFilters): array
    {
        $asOf = \Modules\Client\Services\CustomerLedgerService::dataCutoff(\Carbon\Carbon::parse($endDate)->toDateString());

        $status = (string) $request->input('status_id', 'all');
        $narrowed = $request->filled('employee_id') || $request->filled('neighborhood_id') || $request->filled('target_id')
            || $request->filled('category_id') || $request->filled('registration_stage')
            || ($status !== '' && $status !== 'all') || $nodeFilters !== []
            || ($request->input('target_change_filter', 'all') !== 'all');

        if (! $request->filled('client_id') && ! $narrowed) {
            $metrics = SalesDashboardMetrics::forBranch($effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null);
            $period = new \App\Support\ReportPeriod('custom', \Carbon\Carbon::parse($asOf)->startOfDay(), \Carbon\Carbon::parse($asOf)->endOfDay(), '');
            $receivables = $metrics->receivables($period);

            return ['amount' => (float) $receivables['receivable'], 'clients' => (int) $receivables['clients'], 'as_of' => $asOf, 'basis' => 'scope'];
        }

        $clientIds = $request->filled('client_id')
            ? [(int) $request->input('client_id')]
            : collect($rows)->filter(fn ($r) => empty($r['client_unspecified']) && ! empty($r['client_id']))->pluck('client_id')->unique()->map(fn ($id) => (int) $id)->values()->all();

        $accountToClient = [];
        foreach (array_chunk($clientIds, 1000) as $chunk) {
            foreach (Account::ledger()->whereIn('client_id', $chunk)->get(['id', 'client_id']) as $account) {
                $accountToClient[(int) $account->id] = (int) $account->client_id;
            }
        }

        $byClient = [];
        foreach (app(\Modules\Client\Services\CustomerLedgerService::class)->balancesAt(array_keys($accountToClient), $asOf) as $accountId => $balance) {
            $clientId = $accountToClient[$accountId] ?? 0;
            $byClient[$clientId] = ($byClient[$clientId] ?? 0.0) + (float) $balance;
        }
        $debtors = array_filter($byClient, fn ($b) => $b > 0.009);

        return ['amount' => round(array_sum($debtors), 2), 'clients' => count($debtors), 'as_of' => $asOf, 'basis' => $request->filled('client_id') ? 'client' : 'rows'];
    }

    /**
     * بطاقاتُ الرأس والمخطّطان — من صفوف الجدول نفسِها، فلا يختلف رقمُ
     * البطاقة عن مجموع الجدول.
     *
     *  • إجمالي التحصيلات = صافي الصفوف: التحصيلُ ناقصَ المرتجع المصروف
     *    نقداً وإلغاءِ السندات — رقمُ «الإجمالي العام» في الجدول نفسه.
     *  • المتبقّي = مجموع المتبقّي على الفواتير المميَّزة التي سدّدتها السندات،
     *    كلُّ فاتورةٍ مرّةً مهما تعدّدت دفعاتها.
     */
    private function buildDashboard($rows, string $groupBy): array
    {
        $rows = collect($rows);

        $invoices = [];
        foreach ($rows as $row) {
            foreach ($row['invoices'] ?? [] as $invoice) {
                $invoices[$invoice['id']] = $invoice;
            }
        }

        $clientIds = $rows->filter(fn ($r) => empty($r['client_unspecified']) && ! empty($r['client_id']))
            ->pluck('client_id')->unique();

        $net = (float) $rows->sum('amount');
        $returns = abs((float) $rows->where('is_return', true)->sum('amount'));
        $cancellations = abs((float) $rows->where('is_cancellation', true)->sum('amount'));

        // ——— حسب طريقة التحصيل: كلُّ خزينةٍ أو بنكٍ عمود، ولونُه بنوعه ———
        $byAccount = [];
        foreach ($rows as $row) {
            $key = $row['account_id'] ?? 0;
            $byAccount[$key] ??= [
                'name' => $row['treasury_account'] ?? 'غير محدد',
                'method' => $row['method_key'] ?? 'cash',
                'amount' => 0.0,
            ];
            $byAccount[$key]['amount'] += (float) $row['amount'];
        }
        $byAccount = collect($byAccount)->filter(fn ($a) => abs($a['amount']) >= 0.005)->sortByDesc('amount')->values();
        $methodBars = $byAccount->take(7)->values()->all();
        if ($byAccount->count() > 7) {
            /* «حسابات أخرى» تحمل حساباتِها: الرقمُ المجموعُ وحده لا يُعرف مصدرُه
               (سأل المستخدم «من وين الحسابات الأخرى»)، فالصفُّ يُفتح على تفصيله. */
            $methodBars[] = [
                'name' => 'حسابات أخرى ('.($byAccount->count() - 7).')',
                'method' => 'other',
                'amount' => (float) $byAccount->slice(7)->sum('amount'),
                'items' => $byAccount->slice(7)->values()->map(fn ($a) => ['name' => $a['name'], 'method' => $a['method'], 'amount' => round((float) $a['amount'], 2)])->all(),
            ];
        }

        /* ——— حسب الموظّف: من حرّر السند — لا الخزينةُ التي دخلها المال ———
           اللوحةُ كانت تعرض الخزائن، وطلب المستخدم تحصيلَ الموظف مكانها
           (2026-09-28). أعلى سبعة ثمّ «موظفون آخرون» يُفتح على أسمائهم. */
        $byEmployee = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['employee_name'] ?? ''));
            $key = ! empty($row['employee_unspecified']) || empty($row['employee_id']) ? 'none' : 'e'.$row['employee_id'];
            $byEmployee[$key] ??= ['name' => $name !== '' ? $name : 'غير محدد', 'method' => 'cash', 'amount' => 0.0, 'count' => 0];
            $byEmployee[$key]['amount'] += (float) $row['amount'];
            $byEmployee[$key]['count'] += empty($row['is_return']) && empty($row['is_cancellation']) ? 1 : 0;
        }
        $byEmployee = collect($byEmployee)->filter(fn ($e) => abs($e['amount']) >= 0.005)->sortByDesc('amount')->values()
            ->map(fn ($e) => ['name' => $e['name'], 'method' => 'cash', 'amount' => round($e['amount'], 2), 'count' => $e['count']]);
        $employeeBars = $byEmployee->take(7)->values()->all();
        if ($byEmployee->count() > 7) {
            $employeeBars[] = [
                'name' => 'موظفون آخرون ('.($byEmployee->count() - 7).')',
                'method' => 'other',
                'amount' => round((float) $byEmployee->slice(7)->sum('amount'), 2),
                'count' => (int) $byEmployee->slice(7)->sum('count'),
                'items' => $byEmployee->slice(7)->values()->all(),
            ];
        }

        $methodTotals = [];
        foreach (self::METHOD_LABELS as $key => $label) {
            $methodTotals[$key] = ['label' => $label, 'amount' => (float) $rows->where('method_key', $key)->sum('amount')];
        }

        // ——— حسب معيار التجميع: أعلى خمسة ثمّ «أخرى» ———
        $chartGroupBy = $groupBy === 'branch_tree' ? 'branch' : $groupBy;
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) $this->getGroupKey($row, $chartGroupBy);
            $groups[$key] ??= [
                'key' => $key,
                'name' => $this->getGroupName($row, $chartGroupBy),
                'client_id' => $chartGroupBy === 'client' && empty($row['client_unspecified']) ? ($row['client_id'] ?? null) : null,
                'amount' => 0.0,
            ];
            $groups[$key]['amount'] += (float) $row['amount'];
        }
        $positive = collect($groups)->filter(fn ($g) => $g['amount'] > 0.005)->sortByDesc('amount')->values();
        $positiveTotal = (float) $positive->sum('amount');
        $slices = $positive->take(5)->values()->all();
        if ($positive->count() > 5) {
            $slices[] = [
                'key' => '__others',
                'name' => $this->othersLabel($chartGroupBy).' ('.($positive->count() - 5).')',
                'client_id' => null,
                'amount' => (float) $positive->slice(5)->sum('amount'),
            ];
        }
        foreach ($slices as &$slice) {
            $slice['percent'] = $positiveTotal > 0 ? round($slice['amount'] / $positiveTotal * 100, 1) : 0;
        }
        unset($slice);

        // ——— التحصيلُ اليومي: صافي كلِّ يومٍ من الصفوف نفسِها ———
        $trend = [];
        foreach ($rows as $row) {
            $day = substr((string) ($row['date'] ?? ''), 0, 10);
            if ($day !== '') {
                $trend[$day] = ($trend[$day] ?? 0) + (float) $row['amount'];
            }
        }
        ksort($trend);

        // ——— الأعلى تحصيلاً: عميل / موظف / منطقة ———
        $top = [];
        foreach (['client' => ['client_name', 'client_id'], 'employee' => ['employee_name', null], 'region' => ['node_region_name', null]] as $topKey => [$nameKey, $idKey]) {
            $sums = [];
            foreach ($rows as $row) {
                $name = trim((string) ($row[$nameKey] ?? ''));
                $name = $name !== '' ? $name : 'غير محدد';
                $clientId = $idKey && empty($row['client_unspecified']) ? ($row[$idKey] ?? null) : null;
                // العميلُ بمعرّفه لا باسمه: عميلان باسمٍ واحد (فروعُ سلسلة) لا يُجمعان
                $sumKey = $clientId ? 'c'.$clientId : 'n'.$name;
                $sums[$sumKey] ??= ['name' => $name, 'client_id' => $clientId, 'amount' => 0.0];
                $sums[$sumKey]['amount'] += (float) $row['amount'];
            }
            $top[$topKey] = collect($sums)->filter(fn ($g) => $g['amount'] > 0.005)->sortByDesc('amount')->take(7)
                ->map(fn ($g) => ['name' => $g['name'], 'client_id' => $g['client_id'], 'amount' => round($g['amount'], 2)])->values()->all();
        }

        return [
            'trend' => array_map(fn ($amount) => round($amount, 2), $trend),
            'top' => $top,
            'net' => max(0.0, $net),
            'returns' => $returns,
            'cancellations' => $cancellations,
            'remaining' => (float) collect($invoices)->sum('remaining'),
            'invoice_count' => count($invoices),
            'client_count' => $clientIds->count(),
            'collection_count' => $rows->where('is_return', false)->where('is_cancellation', false)->count(),
            'unlinked_count' => $rows->filter(fn ($r) => empty($r['invoice_ids']) && empty($r['is_return']) && empty($r['is_cancellation']))->count(),
            'method_bars' => $methodBars,
            'employee_bars' => $employeeBars,
            'employee_count' => $byEmployee->count(),
            'method_totals' => $methodTotals,
            'group_label' => $this->groupByOptions[$chartGroupBy] ?? 'العميل',
            'group_slices' => $slices,
            'group_count' => count($groups),
        ];
    }

    /**
     * البحثُ السريع في صفوف الجدول: الاسمُ والكودُ ورقمُ السند والفاتورة
     * والموظفُ والحسابُ والفرعُ والملاحظة — بأيّ عرضٍ من الأربعة.
     */
    private function quickSearchRows($rows, string $term)
    {
        $rows = collect($rows);
        $term = mb_strtolower(trim($term));

        if ($term === '') {
            return $rows;
        }

        $fields = ['name', 'client_name', 'client_code', 'reference_number', 'entry_reference', 'employee_name', 'treasury_account', 'branch_name', 'note'];

        return $rows->filter(function ($row) use ($term, $fields) {
            $haystack = [];
            foreach ($fields as $field) {
                if (isset($row[$field]) && is_scalar($row[$field])) {
                    $haystack[] = (string) $row[$field];
                }
            }
            foreach ((array) ($row['invoice_codes'] ?? []) as $code) {
                $haystack[] = (string) $code;
            }

            return str_contains(mb_strtolower(implode(' | ', $haystack)), $term);
        });
    }

    private function othersLabel(string $groupBy): string
    {
        return [
            'client' => 'عملاء آخرون',
            'branch' => 'فروع أخرى',
            'region' => 'مناطق أخرى',
            'group' => 'مجموعات أخرى',
            'outlet' => 'منافذ أخرى',
            'employee' => 'موظفون آخرون',
            'treasury' => 'حسابات أخرى',
            'day' => 'أيام أخرى',
            'month' => 'أشهر أخرى',
        ][$groupBy] ?? 'أخرى';
    }

    /** أحجامُ صفحة جدول التفاصيل — `0` = الكلّ، بطلبٍ صريحٍ من المستخدم. */
    private const DETAIL_PAGE_SIZES = [25, 50, 100, 200, 0];

    private const DETAIL_PAGE_DEFAULT = 100;

    /**
     * صفحةٌ من صفوف التفاصيل مع وصف الترقيم.
     *
     * الترقيمُ في الخادم لا في المتصفّح: كانت الصفوفُ كلُّها تُرسَل ويُخفي
     * السكربتُ ما ليس في الصفحة بصنف، فتقريرُ سنةٍ (٧٧٤٥ سنداً) أرسل ٥١
     * ميغابايت من HTML. الإجمالياتُ والبطاقاتُ تبقى على المجموعة كلِّها.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array{per_page: int, page: int, pages: int, total: int, from: int, to: int}}
     */
    private function paginateDetailRows(Request $request, $rows, int $default = self::DETAIL_PAGE_DEFAULT, bool $preserveKeys = false): array
    {
        $perPage = (int) $request->input('per_page', $default);
        if (! in_array($perPage, self::DETAIL_PAGE_SIZES, true)) {
            $perPage = $default;
        }

        // المصفوفةُ الشهرية مفاتيحُها معرّفاتُ المجموعات، فتُحفَظ؛ التفاصيلُ قائمةٌ مرقّمة
        $rows = $preserveKeys ? collect($rows) : collect($rows)->values();
        $total = $rows->count();

        if ($perPage === 0) {
            return [$rows, ['per_page' => 0, 'page' => 1, 'pages' => 1, 'total' => $total, 'from' => $total ? 1 : 0, 'to' => $total]];
        }

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $request->input('page', 1)), $pages);
        $offset = ($page - 1) * $perPage;
        $slice = $rows->slice($offset, $perPage);

        return [
            $preserveKeys ? $slice : $slice->values(),
            [
                'per_page' => $perPage,
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'from' => $total ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * صفوفُ تصدير المصفوفة الشهرية — اسمٌ واثنا عشر شهراً وإجمالي، من الصفوف كلِّها.
     *
     * @return array<int, array{name: string, months: array<int, float>, total: float}>
     */
    private function matrixExportRows(array $reportData): array
    {
        $rows = [];

        foreach ($reportData as $groupKey => $data) {
            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $months[$m] = (float) ($data['months'][$m] ?? 0);
            }

            $rows[] = [
                'name' => (string) ($data['name'] ?? $groupKey),
                'months' => $months,
                'total' => (float) ($data['total'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * صفوفُ التصدير والطباعة — بالشكل الذي يقرؤه سكربتُ الشاشة (Excel والطباعة).
     *
     * كانت تُبنى في القالب داخل حلقة الصفوف وتُكتب JSON في الصفحة؛ صارت
     * تُطلب عند النقر وتُبنى هنا من الصفوف كلِّها لا من الصفحة المعروضة.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportRowsFor($rows): array
    {
        return $rows->values()->map(function ($row) {
            $codes = $row['invoice_codes'] ?? [];
            $hasInvoices = ! empty($codes);
            $isOut = ! empty($row['is_return']) || ! empty($row['is_cancellation']);
            $amount = (float) $row['amount'];
            $total = (float) ($row['invoice_total'] ?? 0);
            $remaining = (float) ($row['invoice_remaining'] ?? 0);

            if (! empty($row['is_return'])) {
                $kind = 'مرتجع مبيعات';
            } elseif (! empty($row['is_cancellation'])) {
                $kind = 'إلغاء سند قبض';
            } elseif (! $hasInvoices) {
                $kind = 'سداد من الرصيد السابق';
            } elseif ($amount + 0.009 < $total) {
                $kind = 'دفعة جزئية';
            } else {
                $kind = 'سداد كامل';
            }

            return [
                'group' => $row['group_name'],
                'group_key' => $row['group_key'],
                'group_amount' => (float) $row['group_amount'],
                'date' => Carbon::parse($row['date'])->format('d/m/Y'),
                'invoice' => $hasInvoices ? implode('، ', $codes) : '-',
                'employee' => $row['employee_name'] ?? '',
                'client' => $row['client_name'] ?? '',
                'category' => $row['client_category'] ?? '',
                'real_category' => $row['client_real_category'] ?? '',
                'status' => $row['client_status'] ?? '',
                'account_type' => $row['account_type'] ?? '',
                'account' => $row['treasury_account'] ?? '',
                'branch' => $row['branch_name'] ?? '',
                'note' => $row['note'] ?? '',
                'amount' => $amount,
                'remaining' => $hasInvoices && ! $isOut ? $remaining : null,
                'total' => $hasInvoices ? $total : null,
                'kind' => $kind,
            ];
        })->all();
    }

    /**
     * صفوفُ جدول التفاصيل مرتّبةً بالمجموعة: الأكبرُ مبلغاً أوّلاً (والأحدثُ
     * أوّلاً لليوم والشهر)، ثمّ الأحدثُ تاريخاً داخلها. كلُّ صفٍّ يحمل مفتاحَ
     * مجموعته واسمَها ومجموعَها ليرسم الجدولُ رأسَ المجموعة.
     */
    private function sortDetailRows($rows, string $groupBy)
    {
        $groupBy = $groupBy === 'branch_tree' ? 'branch' : $groupBy;
        $totals = [];
        $prepared = collect($rows)->map(function ($row) use ($groupBy, &$totals) {
            $row['group_key'] = (string) $this->getGroupKey($row, $groupBy);
            $row['group_name'] = $this->getGroupName($row, $groupBy);
            $totals[$row['group_key']] = ($totals[$row['group_key']] ?? 0) + (float) $row['amount'];

            return $row;
        });

        $byDate = in_array($groupBy, ['day', 'day_employee', 'month'], true);

        return $prepared->sort(function ($a, $b) use ($totals, $byDate) {
            if ($a['group_key'] !== $b['group_key']) {
                return $byDate
                    ? strcmp($b['group_key'], $a['group_key'])
                    : (($totals[$b['group_key']] <=> $totals[$a['group_key']]) ?: strcmp($a['group_key'], $b['group_key']));
            }

            return (strtotime((string) $b['date']) <=> strtotime((string) $a['date'])) ?: ($b['id'] <=> $a['id']);
        })->values()->map(function ($row) use ($totals) {
            $row['group_amount'] = $totals[$row['group_key']];

            return $row;
        });
    }

    /**
     * نافذة «ملاحظات الفاتورة» — بطاقاتُ ملاحظات العميل بمنافسيها وصورها
     * وعهدتها، من `VisitNoteCards` نفسِها التي تبني تبويب «ملاحظات الزيارات»
     * في شاشة العميل وإدارة علاقات العملاء؛ فلا تختلف البطاقةُ بين شاشةٍ وأخرى.
     *
     * البطاقةُ التي بين مستنداتها فاتورةُ السند تُعلَّم `related`.
     */
    public function collectionNotes(Request $request)
    {
        $clientId = (int) $request->input('client_id');
        if (! $clientId) {
            return response()->json(['error' => 'لم يُحدَّد العميل'], 400);
        }

        if (! $this->clientVisibleForReport($request, $clientId)) {
            return response()->json(['error' => 'هذا العميل خارج نطاقك'], 403);
        }

        return response()->json($this->notesPayload($clientId, (array) $request->input('invoice_codes', []), $request));
    }

    /** إضافةُ ملاحظةٍ نصّية على العميل من نافذة التقرير */
    public function storeCollectionNote(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'description' => 'required|string|max:2000',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
        ], [
            'description.required' => 'اكتب نصّ الملاحظة أوّلاً',
        ]);

        if (! $this->canAddNotes()) {
            return response()->json(['error' => 'لا تملك صلاحية إضافة ملاحظات العملاء'], 403);
        }

        if (! $this->clientVisibleForReport($request, $validated['client_id'])) {
            return response()->json(['error' => 'هذا العميل خارج نطاقك'], 403);
        }

        $now = now();
        $note = ClientRelation::create([
            'client_id' => (int) $validated['client_id'],
            'employee_id' => auth()->id(),
            'description' => trim($validated['description']),
            'process' => 'collection_note',
            'type' => 'client',
            'date' => $now->toDateString(),
            'time' => $now->format('H:i:s'),
            'invoice_id' => $validated['invoice_id'] ?? null,
        ]);

        OperationLog::record(
            'collection_note_added',
            'Clients',
            (int) $validated['client_id'],
            'إضافة ملاحظة تحصيل #'.$note->id.' للعميل #'.$validated['client_id'].' من تقرير التحصيلات'
        );

        return response()->json($this->notesPayload((int) $validated['client_id'], (array) $request->input('invoice_codes', [])) + [
            'message' => 'تمت إضافة الملاحظة',
        ]);
    }

    private function canAddNotes(): bool
    {
        return (bool) canAny([
            'client_follow_up_add_notes_attachments_appointments_all',
            'client_follow_up_add_notes_attachments_appointments_assigned',
            'client_follow_up_add_notes_attachments_appointments_own',
            'clients_add_employee_note',
        ]);
    }

    /**
     * حمولةُ نافذة الملاحظات الموحّدة — ثلاثةُ مداخل بتصميمٍ واحد:
     *  • `invoice` زرّ «عرض» في صفّ السند: الملاحظاتُ كلُّها وتُعلَّم بطاقةُ الفاتورة.
     *  • `client`  «ملاحظات العميل»: الملاحظاتُ كلُّها، والحركاتُ في فترة التقرير.
     *  • `month`   زرّ الشهر في العرض الشهري: الملاحظاتُ والحركاتُ في ذلك الشهر وحده.
     *
     * البطاقاتُ من `VisitNoteCards` (المنافسون بصورهم، صور الملاحظة، مرفقاتها،
     * العهدة، المستندات)؛ والحركاتُ (فواتير، مرتجعات، تحصيلات، زيارات بتبرير)
     * كانت تُعرض في نافذتَي «كشف العميل» و«تفاصيل الشهر» فبقيت.
     */
    private function notesPayload(int $clientId, array $invoiceCodes, ?Request $request = null): array
    {
        $request ??= request();
        $client = Client::with(['employee', 'branch', 'status_client'])->find($clientId);

        [$from, $to, $monthLabel, $monthStart, $monthEnd] = $this->notesRange($request);
        $mode = $request->input('mode', $invoiceCodes ? 'invoice' : 'client');

        /* الملاحظاتُ بجلب شاشة العميل نفسِه (`ClientController::show()`): الاستعلامُ
           نفسُه و`VisitNoteCards::build()` نفسُها، فالبطاقةُ هنا هي بطاقةُ تبويب
           «ملاحظات الزيارات» هناك. نافذةُ الشهر تُعلِّم ما وقع في **الشهر كاملاً**
           (`in_range`) وتعرضه افتراضاً، وزرُّ «كل الملاحظات» فيها يعرض البقيّة. */
        $notes = ClientRelation::where('client_id', $clientId)
            ->with(['employee', 'clientEmployee', 'competitorObservations.competitor'])
            ->orderBy('created_at', 'desc')
            ->get();

        $user = auth()->user();
        $canEditAll = $user?->can('client_follow_up_edit_delete_notes_attachments_appointments_all');
        $canEditOwn = $user?->can('client_follow_up_edit_delete_notes_attachments_appointments_own');
        $fromTs = ($mode === 'month' && $monthStart) ? $monthStart->getTimestamp() : null;
        $toTs = ($mode === 'month' && $monthEnd) ? $monthEnd->getTimestamp() : null;

        $codes = array_values(array_filter(array_map(fn ($c) => trim((string) $c), $invoiceCodes)));
        $cards = collect(\App\Services\Clients\VisitNoteCards::build($notes, $clientId))
            ->sortBy('order')
            ->map(function ($card) use ($codes, $notes, $canEditAll, $canEditOwn, $user, $fromTs, $toTs) {
                $card['related'] = $codes !== [] && collect($card['documents'])
                    ->contains(fn ($doc) => in_array((string) $doc['number'], $codes, true));
                $card['in_range'] = $fromTs !== null && ($card['timestamp'] ?? 0) >= $fromTs && ($card['timestamp'] ?? 0) <= $toTs;

                // أزرارُ بطاقة شاشة العميل نفسُها: طباعة، وتعديلٌ وحذفٌ بالصلاحية —
                // على الملاحظة القائدة وحدَها
                $card['print_url'] = route('clients.notes.print', ['noteId' => $card['id']]);
                $card['can_edit'] = (bool) ($canEditAll || ($canEditOwn && (int) $card['employee_id'] === (int) $user?->id));
                $card['edit_url'] = $card['can_edit'] ? route('clients.notes.edit', $card['id']) : null;
                $card['destroy_url'] = $card['can_edit'] ? route('clients.notes.destroy', $card['id']) : null;

                // صورةُ موظّف العميل المرفوعة مع الملاحظة (`uploads/client_notes`) — كانت
                // تُعرض في «كشف العميل» ولا تقرؤها `VisitNoteCards`
                foreach ($notes->whereIn('id', $card['note_ids'] ?? []) as $note) {
                    $img = $note->clientEmployee?->image;
                    if ($img) {
                        $url = asset(str_contains($img, 'uploads/client_notes/') ? $img : 'uploads/client_notes/'.$img);
                        if (! in_array($url, $card['images'], true)) {
                            $card['images'][] = $url;
                        }
                    }
                }

                return $card;
            })
            ->values()
            ->all();

        $activity = ($from && $to) ? $this->clientActivity($clientId, $from, $to) : ['items' => [], 'summary' => null];

        $lastVisit = EmployeeClientVisit::where('client_id', $clientId)
            ->where('visit_date', '<=', now())
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->first(['id', 'visit_date', 'status']);
        $lastVisitState = $lastVisit ? $this->visitStateOf($lastVisit, $this->clientVisitStates((int) $clientId)) : null;

        return [
            'mode' => $mode,
            'client' => [
                'id' => $clientId,
                'name' => $client?->trade_name ?? '',
                'code' => $client?->code ?? '',
                'url' => route('clients.show', $clientId),
                'branch' => ($client?->tree_node_id ? Branch::withoutGlobalScopes()->find($client->tree_node_id)?->name : null) ?? $client?->branch?->name ?? 'غير محدد',
                'employee' => $client?->employee ? trim($client->employee->first_name.' '.$client->employee->middle_name) : 'غير محدد',
                'status' => $client?->status_client?->name ?? 'غير محدد',
                'status_color' => $client?->status_client?->color ?? '#6c757d',
                'balance' => $client ? (float) $client->balance : 0.0,
                'last_visit' => $lastVisit ? Carbon::parse($lastVisit->visit_date)->format('Y/m/d') : null,
                'last_visit_done' => $lastVisitState ? $lastVisitState['key'] === 'visited' : null,
                'last_visit_label' => $lastVisitState['label'] ?? null,
                'last_visit_tone' => $lastVisitState['tone'] ?? null,
            ],
            'range' => [
                'from' => $from?->format('Y/m/d'),
                'to' => $to?->format('Y/m/d'),
                'month' => $monthLabel,
            ],
            'summary' => $activity['summary'],
            'activities' => $activity['items'],
            'cards' => $cards,
            'total_notes' => $notes->count(),
            'month_notes' => $mode === 'month' ? count(array_filter($cards, fn ($card) => ! empty($card['in_range']))) : null,
            'can_add' => $this->canAddNotes(),
            'csrf' => csrf_token(),
        ];
    }

    /**
     * مدى النافذة: `month` (رقم الشهر) مقصوصاً على فترة التقرير — وهو مدى
     * الحركات والملخّص، فيطابق «المحصَّل» خليّةَ الشهر — ومعه الشهرُ كاملاً
     * لتعليم الملاحظات. بلا شهرٍ: فترةُ التقرير نفسها.
     *
     * **سنةُ الشهر** تُختار من فترة التقرير لا من سنة بدايتها: فترةُ «سنة» تمتدّ
     * على سنتين (2025-09-28 ← 2026-09-28)، فكان «أغسطس» يُقرأ أغسطس 2025 —
     * خارج الفترة — فتفتح النافذةُ بلا ملاحظةٍ ولا حركة. تؤخذ أحدثُ سنةٍ يقع
     * شهرُها داخل الفترة.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: ?string, 3: ?Carbon, 4: ?Carbon}
     */
    private function notesRange(Request $request): array
    {
        try {
            $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : null;
            $to = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : null;
        } catch (\Throwable) {
            $from = $to = null;
        }

        $month = (int) $request->input('month');
        if ($month >= 1 && $month <= 12) {
            $lastYear = $to ? $to->year : now()->year;
            $firstYear = $from ? $from->year : $lastYear;
            $year = $lastYear;
            for ($candidate = $lastYear; $candidate >= $firstYear; $candidate--) {
                $start = Carbon::create($candidate, $month, 1)->startOfDay();
                if ((! $to || $start->lte($to)) && (! $from || $start->copy()->endOfMonth()->gte($from))) {
                    $year = $candidate;
                    break;
                }
            }

            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $from = $from && $from->gt($monthStart) ? $from : $monthStart->copy();
            $to = $to && $to->lt($monthEnd) ? $to : $monthEnd->copy();

            return [$from, $to, $this->getArabicMonthName($month).' '.$year, $monthStart, $monthEnd];
        }

        return [$from, $to, null, null, null];
    }

    /**
     * حركاتُ العميل في مدىً: فواتير البيع ومرتجعاتها، التحصيلات (مدين خزينةٍ
     * أو بنكٍ بلا تحويلٍ بين الخزائن — نفسُ شرط التقرير)، والزياراتُ التي لها
     * تبرير. والملخّص: إجمالي الفواتير، المحصَّل، والمتبقّي عليها الآن.
     */
    private function clientActivity(int $clientId, Carbon $from, Carbon $to): array
    {
        $items = [];

        $invoices = DB::table('invoices')
            ->where('client_id', $clientId)
            ->whereNull('voided_at')
            ->whereBetween('invoice_date', [$from, $to])
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get(['id', 'code', 'type', 'grand_total', 'invoice_date', 'created_at']);

        $salesIds = [];
        $salesTotal = 0.0;
        $returnsTotal = 0.0;
        foreach ($invoices as $invoice) {
            $isReturn = $invoice->type === 'returned';
            $at = Carbon::parse($invoice->created_at ?: $invoice->invoice_date);
            if ($isReturn) {
                $returnsTotal += (float) $invoice->grand_total;
            } else {
                $salesTotal += (float) $invoice->grand_total;
                $salesIds[] = (int) $invoice->id;
            }
            $items[] = [
                'kind' => $isReturn ? 'return' : 'invoice',
                'label' => $isReturn ? 'مرتجع مبيعات' : 'فاتورة مبيعات',
                'number' => $invoice->code ?: '#'.$invoice->id,
                'amount' => (float) $invoice->grand_total,
                'date' => Carbon::parse($invoice->invoice_date)->format('Y/m/d'),
                'time' => $at->format('h:i').' '.($at->format('A') === 'AM' ? 'ص' : 'م'),
                'sort' => Carbon::parse($invoice->invoice_date)->format('Y-m-d').' '.$at->format('H:i:s'),
                'url' => route($isReturn ? 'ReturnIInvoices.show' : 'invoices.show', $invoice->id),
            ];
        }

        /* التحصيلاتُ من صفوف التقرير نفسِها (`getCollectionsDetail`: طرفُ العميل،
           والمردودُ والإلغاءُ صفوفٌ سالبة) — فـ«المحصَّل» في النافذة هو رقمُ
           خليّة الشهر في الجدول. كانت تُقرأ من طرف الخزينة وبرأس القيد
           (`journal_entries.client_id`) فتختلف عن الجدول حيث لا يُختم العميلُ
           على القيد، ولا تطرح سنداً أُلغي. */
        $collectedTotal = 0.0;
        foreach ($this->getCollectionsDetail($from, $to, null, null, $clientId, null, 'all') as $row) {
            $amount = (float) $row['amount'];
            $collectedTotal += $amount;
            $at = Carbon::parse($row['date']);
            [$kind, $label] = match (true) {
                ! empty($row['is_return']) => ['return', 'ردّ نقدٍ للعميل'],
                ! empty($row['is_cancellation']) => ['return', $amount < 0 ? 'إلغاء سند قبض' : 'سند قبض ملغى'],
                default => ['collection', 'تحصيل'],
            };
            $items[] = [
                'kind' => $kind,
                'label' => $label,
                'number' => ($row['reference_number'] ?? null) ?: '#'.$row['id'],
                'amount' => $amount,
                'account' => $row['treasury_account'] ?? null,
                'date' => $at->format('Y/m/d'),
                'time' => $at->format('H:i:s') === '00:00:00' ? null : $at->format('h:i').' '.($at->format('A') === 'AM' ? 'ص' : 'م'),
                'sort' => $at->format('Y-m-d H:i:s'),
                'url' => null,
            ];
        }

        $visits = EmployeeClientVisit::where('client_id', $clientId)
            ->whereBetween('visit_date', [$from, $to])
            ->whereNotNull('justification')
            ->where('justification', '!=', '')
            ->orderByDesc('visit_date')
            ->limit(100)
            ->get(['id', 'visit_date', 'status', 'justification', 'justification_approved']);

        $visitStates = $this->clientVisitStates($clientId);

        foreach ($visits as $visit) {
            $date = Carbon::parse($visit->visit_date);
            $planState = $this->visitStateOf($visit, $visitStates);
            $state = match (true) {
                in_array($planState['key'], ['visited', 'in_progress', 'upcoming', 'cancelled'], true) => $planState['label'],
                (int) $visit->justification_approved === 1 => 'تبرير مقبول',
                (int) $visit->justification_approved === 2 => 'تبرير مرفوض',
                default => 'بانتظار الموافقة',
            };
            $items[] = [
                'kind' => 'visit',
                'label' => 'زيارة — '.$state,
                'number' => null,
                'amount' => null,
                'note' => $visit->justification,
                'date' => $date->format('Y/m/d'),
                'time' => null,
                'sort' => $date->format('Y-m-d').' 00:00:00',
                'url' => null,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['sort'], $a['sort']));

        $remaining = (float) collect($this->loadLinkedInvoices($salesIds))->sum('remaining');

        return [
            'items' => array_map(fn ($i) => collect($i)->except('sort')->put('ts', strtotime($i['sort']))->all(), $items),
            'summary' => [
                'sales' => round($salesTotal, 2),
                'returns' => round($returnsTotal, 2),
                'collected' => round($collectedTotal, 2),
                'remaining' => round($remaining, 2),
            ],
        ];
    }

    /**
     * تحديد نوع الحساب (خزينة أم بنك)
     */
    private function getAccountType($account)
    {
        if (! $account || ! $account->parent_id) {
            return 'غير محدد';
        }

        if ($account->parent_id == SystemAccountIds::CASH_TREASURIES_PARENT) {
            return 'خزينة';
        } elseif ($account->parent_id == SystemAccountIds::BANK_ACCOUNTS_PARENT) {
            return 'بنك';
        }

        return 'غير محدد';
    }

    /**
     * حساب الإحصائيات الإجمالية
     */
    private function calculateStatistics($collectionsData, $startDate)
    {
        // حساب التحصيلات بدون المردودات ليتطابق مع رقم الداش بورد الإجمالي
        $totalCollections = (float) $collectionsData->where('is_return', false)->sum('amount');
        // حساب المردودات
        $totalReturns = abs((float) $collectionsData->where('is_return', true)->sum('amount'));
        // صافي التحصيلات (تحصيلات - مردودات)
        $netCollections = max(0, $totalCollections - $totalReturns);

        // الإلغاءاتُ مطروحةٌ من المبلغ أعلاه، ولا تُعدّ سنداتِ تحصيل
        $collectionCount = $collectionsData->where('is_return', false)->where('is_cancellation', false)->count();
        $returnCount = $collectionsData->where('is_return', true)->count();
        $openingBalance = 0;

        return [
            'opening_balance' => $openingBalance,
            'total_collections' => $totalCollections, // هذا سيظهر كإجمالي قبل الخصم
            'total_returns' => $totalReturns,
            'net_collections' => $netCollections,
            'collection_count' => $collectionCount,
            'return_count' => $returnCount,
            'average_collection' => $collectionCount > 0 ? $totalCollections / $collectionCount : 0,
            'closing_balance' => $openingBalance + $netCollections, // الرصيد النهائي يعتمد على الصافي
        ];
    }

    /**
     * الحصول على الفروع للفلترة
     */
    private function getBranchesForFilter($isMainBranch, $currentBranchId)
    {
        if ($isMainBranch) {
            return \App\Support\DataScope::branchOptions(Branch::where('status', 'active'));
        } else {
            return Branch::where('id', $currentBranchId)->where('status', 'active')->get();
        }
    }

    /**
     * الحصول على قائمة الموظفين للفلترة
     */
    private function getEmployeesForFilter($isMainBranch, $currentBranchId, $effectiveBranchId)
    {
        $query = User::whereNotNull('employee_id')
            ->whereHas('employee', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('status', 'active')
                        ->orWhere('status', '1')
                        ->orWhere('status', 1);
                });
            });

        if (! $isMainBranch) {
            $query->where('branch_id', $currentBranchId);
        } elseif ($effectiveBranchId) {
            $query->where('branch_id', $effectiveBranchId);
        }

        return $query->orderBy('users.name')->get();
    }

    /**
     * الحصول على قائمة العملاء
     */
    private function getClientsForFilter($branchId = null)
    {
        $query = Client::where('status', 'active');

        if ($branchId) {
            $branchIds = $this->expandBranchToDescendants($branchId);

            $query->where(function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds)
                    ->orWhereExists(function ($sub) use ($branchIds) {
                        $sub->select(DB::raw(1))
                            ->from('neighborhoods')
                            ->whereColumn('neighborhoods.client_id', 'clients.id')
                            ->whereIn('neighborhoods.region_id', $branchIds);
                    });
            });
        }

        return $query->orderBy('trade_name')->get();
    }

    /**
     * الحصول على قائمة المجموعات: فروع المنطقة (territories) التابعة للفرع المحدد.
     * بعد ترحيل region_groubs، أصبحت المجموعات تخزَّن في branches بـ is_territory=1.
     * يُحمَّل اسم الفرع الأب مع كل مجموعة لعرض "الفرع → المجموعة" في الـ select.
     */
    private function getRegionGroupsForFilter($branchId = null)
    {
        $query = Branch::where('is_territory', 1)->with('parentBranch:id,name');

        if ($branchId) {
            $branchIds = $this->expandBranchToDescendants($branchId);
            $query->where(function ($q) use ($branchIds) {
                $q->whereIn('id', $branchIds)
                    ->orWhereIn('parent_id', $branchIds);
            });
        }

        return $query->orderBy('parent_id')->orderBy('name')->get();
    }

    /**
     * يُرجع معرف الفرع + معرفات كل الفروع التابعة (territories) في الشجرة.
     * يُستخدم لتوسيع فلتر الفرع ليشمل المجموعات التي رُحِّلت من region_groubs.
     */
    private function expandBranchToDescendants($branchId): array
    {
        $branch = Branch::withoutGlobalScopes()->find((int) $branchId);
        if (! $branch) {
            return [(int) $branchId];
        }

        if (method_exists($branch, 'descendantBranchIds')) {
            return $branch->descendantBranchIds()
                ->push((int) $branch->id)
                ->unique()
                ->values()
                ->all();
        }

        // البحث الهبوطي عن المعرفات التابعة عن طريق قاعدة البيانات مباشرة
        $ids = [(int) $branchId];
        $frontier = [(int) $branchId];
        while (! empty($frontier)) {
            $next = DB::table('branches')
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            if (empty($next)) {
                break;
            }
            $ids = array_merge($ids, $next);
            $frontier = $next;
        }

        return array_values(array_unique($ids));
    }

    /**
     * جلب تفاصيل نشاط العميل في شهر معين (فواتير، تحصيلات، ملاحظات، زيارات)
     */
    public function getClientMonthDetails(Request $request)
    {
        $clientId = $request->input('client_id');
        $month = $request->input('month');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (! $clientId || ! $month) {
            return response('<div class="alert alert-danger text-center">بيانات غير مكتملة</div>', 400);
        }

        if (! $this->clientVisibleForReport($request, $clientId)) {
            return response('<div class="alert alert-danger text-center">هذا العميل خارج نطاقك</div>', 403);
        }

        // 1. الفواتير
        $invoicesQuery = DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
            ->where('je.client_id', $clientId)
            ->where('je.status', 1)
            ->whereMonth('je.date', $month);

        if ($dateFrom && $dateTo) {
            $invoicesQuery->whereBetween('je.date', [$dateFrom, $dateTo]);
        }

        $invoices = $invoicesQuery->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('jed.account_id', SystemAccountIds::SALES)
                    ->where('jed.credit', '>', 0)
                    ->whereNotIn('je.reference_type', [
                        ReferenceType::SALES_RETURN->value,
                        ReferenceType::CREDIT_NOTE->value,
                    ]);
            })->orWhere(function ($q2) {
                $q2->whereIn('jed.account_id', [SystemAccountIds::SALES, SystemAccountIds::SALES_RETURNS])
                    ->where('jed.debit', '>', 0)
                    ->whereIn('je.reference_type', [
                        ReferenceType::SALES_RETURN->value,
                        ReferenceType::CREDIT_NOTE->value,
                    ]);
            });
        })
            ->select('je.id', 'je.date', 'je.description', 'je.reference_type', 'jed.credit', 'jed.debit')
            ->get()->map(function ($inv) {
                $isReturn = in_array($inv->reference_type, [
                    ReferenceType::SALES_RETURN->value,
                    ReferenceType::CREDIT_NOTE->value,
                ]);
                $amount = $isReturn ? $inv->debit : $inv->credit;

                if ($isReturn) {
                    $amount = -$amount;
                }

                return [
                    'type' => 'invoice',
                    'icon' => $isReturn ? 'fa-undo' : 'fa-file-invoice-dollar',
                    'color' => $isReturn ? 'danger' : 'primary',
                    'date' => $inv->date,
                    'amount' => $amount,
                    'note' => $inv->description,
                    'reference' => ($isReturn ? 'مرتجع مبيعات #' : 'فاتورة مبيعات #').$inv->id,
                ];
            });

        // 2. التحصيلات
        $treasuryAccountIds = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        $collections = collect([]);
        if (! empty($treasuryAccountIds)) {
            $collectionsQuery = DB::table('journal_entries as je')
                ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
                ->where('je.client_id', $clientId)
                ->where('je.status', 1)
                ->whereMonth('je.date', $month)
                ->whereIn('jed.account_id', $treasuryAccountIds);

            if ($dateFrom && $dateTo) {
                $collectionsQuery->whereBetween('je.date', [$dateFrom, $dateTo]);
            }

            $collections = $collectionsQuery->where('jed.debit', '>', 0)
                // استثناء التحويلات
                ->whereNotExists(function ($sub) use ($treasuryAccountIds) {
                    $sub->select(DB::raw(1))
                        ->from('journal_entry_details as jed2')
                        ->whereColumn('jed2.journal_entry_id', 'jed.journal_entry_id')
                        ->whereIn('jed2.account_id', $treasuryAccountIds)
                        ->where('jed2.credit', '>', 0);
                })
                ->select('je.id', 'je.date', 'je.description', 'jed.debit as amount')
                ->get()->map(function ($col) {
                    return [
                        'type' => 'collection',
                        'icon' => 'fa-hand-holding-usd',
                        'color' => 'success',
                        'date' => $col->date,
                        'amount' => $col->amount,
                        'note' => $col->description,
                        'reference' => 'سند قبض / تحصيل #'.$col->id,
                    ];
                });
        }

        // 3. الملاحظات والعهد (ClientRelations)
        $notesQuery = ClientRelation::where('client_id', $clientId)
            ->whereMonth('created_at', $month);

        if ($dateFrom && $dateTo) {
            $notesQuery->whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ]);
        }

        $notes = $notesQuery->with('clientEmployee')->get()->map(function ($note) {
            $hasDeposit = ! empty($note->deposit_count);
            // توحيد مسارات الصور
            $images = [];
            if (! empty($note->image)) {
                $images[] = $note->image;
            }
            if ($note->clientEmployee && ! empty($note->clientEmployee->image)) {
                $img = $note->clientEmployee->image;
                $images[] = str_contains($img, 'uploads/client_notes/') ? $img : 'uploads/client_notes/'.$img;
            }
            if (! empty($note->attachments)) {
                $rawAtt = $note->attachments;
                $atts = [];
                if (is_array($rawAtt)) {
                    $atts = $rawAtt;
                } elseif (is_string($rawAtt)) {
                    $decoded = json_decode($rawAtt, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $atts = $decoded;
                    } else {
                        $atts = explode(',', $rawAtt);
                    }
                }
                foreach ($atts as $att) {
                    if (is_string($att) && trim($att) !== '') {
                        $images[] = trim($att);
                    }
                }
            }

            return [
                'type' => 'note',
                'icon' => $hasDeposit ? 'fa-money-bill-wave' : 'fa-comment-alt',
                'color' => $hasDeposit ? 'warning' : 'info',
                'date' => $note->created_at->format('Y-m-d H:i:s'),
                'amount' => $note->deposit_count,
                'note' => $note->description,
                'images' => array_filter($images),
                'reference' => $hasDeposit ? 'ملاحظة مع عهدة' : 'ملاحظة عميل',
            ];
        });

        // 4. الزيارات (EmployeeClientVisit)
        $visitsQuery = EmployeeClientVisit::where('client_id', $clientId)
            ->whereMonth('visit_date', $month);

        if ($dateFrom && $dateTo) {
            $visitsQuery->whereBetween('visit_date', [$dateFrom, $dateTo]);
        }

        $visitStates = $this->clientVisitStates((int) $clientId);

        $visits = $visitsQuery->get()->map(function ($visit) use ($visitStates) {
            $planState = $this->visitStateOf($visit, $visitStates);
            $isActive = $planState['key'] === 'visited';
            // النصّان الثابتان يقرؤهما مرشِّحُ الخطّ الزمني أدناه — فلا يتغيّران
            $isMissed = in_array($planState['key'], ['not_visited', 'justified'], true);

            return [
                'type' => 'visit',
                'icon' => $isActive ? 'fa-map-marker-alt' : 'fa-calendar-times',
                'color' => $planState['tone'],
                'date' => $visit->visit_date->format('Y-m-d H:i:s'),
                'amount' => null,
                'note' => $visit->justification ?? ($isActive ? 'تمت الزيارة بنجاح' : ($isMissed ? 'لم تتم الزيارة (معلقة/غير نشطة)' : $planState['label'])),
                'reference' => 'زيارة مندوب',
            ];
        });

        $timeline = collect([])
            ->concat($invoices)
            ->concat($collections)
            ->concat($notes)
            ->concat($visits)
            ->sortByDesc('date')
            ->values();

        // Filter visits: only keep visit if it has a justification OR there is another activity (invoice/collection) on the same day
        $timeline = $timeline->filter(function ($item) use ($timeline) {
            if ($item['type'] === 'visit') {
                $hasJustification = ! empty($item['note']) && $item['note'] !== 'تمت الزيارة بنجاح' && $item['note'] !== 'لم تتم الزيارة (معلقة/غير نشطة)';
                if ($hasJustification) {
                    return true;
                }

                // Check for other activities on same day
                $visitDate = Carbon::parse($item['date'])->format('Y-m-d');
                $hasActivities = $timeline->contains(function ($otherItem) use ($visitDate) {
                    if ($otherItem['type'] === 'visit' || $otherItem['type'] === 'note') {
                        return false;
                    }
                    $otherDate = Carbon::parse($otherItem['date'])->format('Y-m-d');

                    return $otherDate === $visitDate;
                });

                return $hasActivities;
            }

            return true;
        })->values();

        $year = $dateFrom ? explode('-', $dateFrom)[0] : now()->year;

        // Calculate Target for this specific month
        $targetsList = CustomerTarget::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        // Sales in this month
        $mSales = (float) DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
            ->where('jed.account_id', SystemAccountIds::SALES)
            ->where('jed.credit', '>', 0)
            ->whereYear('je.date', $year)
            ->whereMonth('je.date', $month)
            ->where('je.status', 1)
            ->where('je.client_id', $clientId)
            ->whereNotIn('je.reference_type', [
                ReferenceType::SALES_RETURN->value,
                ReferenceType::CREDIT_NOTE->value,
            ])
            ->sum(DB::raw('jed.credit - jed.debit'));

        // Returns in this month
        $mInvoices = DB::table('invoices')
            ->where('client_id', $clientId)
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->get();

        $mSales = (float) $mInvoices->sum('grand_total');
        $mCollections = (float) $mInvoices->sum('advance_payment');

        $totalDays = 0;
        $paidCount = 0;
        foreach ($mInvoices as $inv) {
            if ($inv->is_paid) {
                $lastPayDate = DB::table('payments_process')
                    ->where('invoice_id', $inv->id)
                    ->where('type', 'client payments')
                    ->max('payment_date');
                if ($lastPayDate) {
                    $diff = Carbon::parse($inv->invoice_date)->diffInDays(Carbon::parse($lastPayDate));
                    $totalDays += $diff;
                    $paidCount++;
                }
            }
        }
        $avgDays = $paidCount > 0 ? (int) round($totalDays / $paidCount) : 0;

        $metrics = [
            'total_sales' => $mSales,
            'total_collections' => $mCollections,
            'avg_collection_days' => $avgDays,
        ];

        $monthTarget = null;
        foreach ($targetsList as $target) {
            $ok = true;
            if ($target->min_sales > 0 && $metrics['total_sales'] < $target->min_sales) {
                $ok = false;
            }
            if ($ok && $target->min_collections > 0 && $metrics['total_collections'] < $target->min_collections) {
                $ok = false;
            }
            if ($ok && $target->max_avg_collection_days > 0 && $metrics['avg_collection_days'] > $target->max_avg_collection_days) {
                $ok = false;
            }

            if ($ok) {
                $monthTarget = [
                    'name' => $target->name,
                    'color' => $target->color ?: '#6c757d',
                ];
                break;
            }
        }
        if (! $monthTarget) {
            $monthTarget = [
                'name' => 'غير مقيّم',
                'color' => '#6c757d',
            ];
        }

        return view('sales::sals_report.salesRport.partials.month_details_timeline', compact('timeline', 'month', 'year', 'monthTarget'));
    }

    /**
     * عددُ الزيارات المكتملة والفائتة لكل عميلٍ في كل شهر — من صفوف
     * `ClientDashboardService::planRows()`، فرقمُ الخانة هو رقمُ لوحة العملاء
     * و«أداء المندوبين». اليومُ الواحد يُعدّ مرّة (المكتملةُ تغلب)، والقادمةُ
     * والجاريةُ والملغاةُ والمنقولةُ (`is_moved`) لا تُعدّ فائتة.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, array<int, array{active: int, unactive: int}>>
     */
    private function monthlyVisitCounts(array $clientIds, $startDate, $endDate, ?int $employeeId): array
    {
        $clientIds = array_values(array_unique(array_map('intval', array_filter($clientIds))));

        if ($clientIds === []) {
            return [];
        }

        if ($startDate && $endDate) {
            $from = Carbon::parse($startDate);
            $to = Carbon::parse($endDate);
        } else {
            $bounds = DB::table('employee_client_visits')->whereIn('client_id', $clientIds)
                ->selectRaw('MIN(visit_date) as first_date, MAX(visit_date) as last_date')->first();
            if (! $bounds || ! $bounds->first_date) {
                return [];
            }
            $from = Carbon::parse($bounds->first_date);
            $to = Carbon::parse($bounds->last_date);
        }

        $moved = DB::table('employee_client_visits')
            ->where('is_moved', 1)
            ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('id')->flip();

        $days = [];
        foreach (app(\App\Services\ClientDashboardService::class)->planRows($clientIds, $from, $to) as $row) {
            if (($employeeId && (int) $row['employee_id'] !== $employeeId) || isset($moved[$row['id']])) {
                continue;
            }

            $state = match ($row['key']) {
                'visited' => 'active',
                'not_visited', 'justified' => 'unactive',
                default => null,
            };

            if ($state !== null && ($days[$row['client_id']][$row['date']] ?? null) !== 'active') {
                $days[$row['client_id']][$row['date']] = $state;
            }
        }

        $counts = [];
        foreach ($days as $clientId => $dates) {
            $counts[$clientId] = array_fill(1, 12, ['active' => 0, 'unactive' => 0]);
            foreach ($dates as $date => $state) {
                $counts[$clientId][(int) substr($date, 5, 2)][$state]++;
            }
        }

        return $counts;
    }

    /** @return array<int, array<string, mixed>> حالاتُ صفوف خطّ سير العميل بقاعدة لوحة العملاء */
    private function clientVisitStates(int $clientId): array
    {
        return app(\App\Services\ClientDashboardService::class)->clientVisitStates($clientId);
    }

    /**
     * حالةُ صفّ خطّ سيرٍ: `key` و`label` و`tone` (لونُ Bootstrap) — وما لا
     * حالةَ له في الخريطة يُقرأ من العمود القديم.
     *
     * @return array{key: string, label: string, tone: string}
     */
    private function visitStateOf($visit, array $states): array
    {
        $state = $states[(int) $visit->id] ?? null;

        if (! $state) {
            return $visit->status === 'active'
                ? ['key' => 'visited', 'label' => 'مكتملة', 'tone' => 'success']
                : ['key' => 'not_visited', 'label' => 'فائتة', 'tone' => 'danger'];
        }

        return [
            'key' => $state['key'],
            'label' => $state['label'],
            'tone' => ['visited' => 'success', 'in_progress' => 'primary', 'justified' => 'info', 'upcoming' => 'secondary', 'cancelled' => 'secondary'][$state['key']] ?? 'danger',
        ];
    }

    private function getUnifiedTimelineData(array $clientIds)
    {
        if (empty($clientIds)) {
            return ['visits_timeline' => [], 'unified_history' => []];
        }

        $visits = EmployeeClientVisit::whereIn('client_id', $clientIds)
            ->orderBy('visit_date', 'desc')
            ->get(['id', 'client_id', 'visit_date', 'status', 'justification', 'justification_approved', 'day_of_week', 'week_number', 'year', 'created_at']);

        $invoices = DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
            ->whereIn('je.client_id', $clientIds)
            ->where('je.status', 1)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('jed.account_id', SystemAccountIds::SALES)
                        ->where('jed.credit', '>', 0)
                        ->whereNotIn('je.reference_type', [
                            ReferenceType::SALES_RETURN->value,
                            ReferenceType::CREDIT_NOTE->value,
                        ]);
                })->orWhere(function ($q2) {
                    $q2->whereIn('jed.account_id', [SystemAccountIds::SALES, SystemAccountIds::SALES_RETURNS])
                        ->where('jed.debit', '>', 0)
                        ->whereIn('je.reference_type', [
                            ReferenceType::SALES_RETURN->value,
                            ReferenceType::CREDIT_NOTE->value,
                        ]);
                });
            })
            ->select(
                'je.client_id',
                'je.date',
                DB::raw('DATE(je.date) as visit_day'),
                DB::raw('(CASE WHEN jed.credit > 0 AND je.reference_type NOT IN ("'.ReferenceType::SALES_RETURN->value.'","'.ReferenceType::CREDIT_NOTE->value.'") THEN (jed.credit - jed.debit) ELSE 0 END) as sales_amount'),
                DB::raw('(CASE WHEN jed.debit > 0 AND je.reference_type IN ("'.ReferenceType::SALES_RETURN->value.'","'.ReferenceType::CREDIT_NOTE->value.'") THEN (jed.debit - jed.credit) ELSE 0 END) as return_amount')
            )
            ->get();

        $treasuryAccountIds = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        $collections = ! empty($treasuryAccountIds)
            ? DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'jed.journal_entry_id', '=', 'je.id')
                ->whereIn('je.client_id', $clientIds)
                ->whereIn('jed.account_id', $treasuryAccountIds)
                ->where('jed.debit', '>', 0)
                ->where('je.status', 1)
                ->whereNotExists(function ($sub) use ($treasuryAccountIds) {
                    $sub->select(DB::raw(1))
                        ->from('journal_entry_details as jed2')
                        ->whereColumn('jed2.journal_entry_id', 'jed.journal_entry_id')
                        ->whereIn('jed2.account_id', $treasuryAccountIds)
                        ->where('jed2.credit', '>', 0);
                })
                ->select('je.client_id', 'je.date', DB::raw('DATE(je.date) as visit_day'), DB::raw('(jed.debit) as total_collected'))
                ->get()
            : collect();

        $visitsTimeline = [];
        $visitStates = [];
        $invByClientDay = [];
        $unifiedHistory = [];

        foreach ($invoices as $inv) {
            $cid = $inv->client_id;
            $day = $inv->visit_day;
            if (! isset($invByClientDay[$cid][$day])) {
                $invByClientDay[$cid][$day] = ['sales' => 0, 'return' => 0];
            }
            $invByClientDay[$cid][$day]['sales'] += (float) $inv->sales_amount;
            $invByClientDay[$cid][$day]['return'] += (float) $inv->return_amount;

            if ((float) $inv->sales_amount > 0) {
                $unifiedHistory[$cid][] = ['date' => $inv->date, 'amount' => (float) $inv->sales_amount, 'type' => 'مبيعات'];
            }
            if ((float) $inv->return_amount > 0) {
                $unifiedHistory[$cid][] = ['date' => $inv->date, 'amount' => (float) $inv->return_amount, 'type' => 'مرتجع'];
            }
        }

        $colByClientDay = [];
        foreach ($collections as $col) {
            $cid = $col->client_id;
            $day = $col->visit_day;
            if (! isset($colByClientDay[$cid][$day])) {
                $colByClientDay[$cid][$day] = 0;
            }
            $colByClientDay[$cid][$day] += (float) $col->total_collected;

            if ((float) $col->total_collected > 0) {
                $unifiedHistory[$cid][] = ['date' => $col->date, 'amount' => (float) $col->total_collected, 'type' => 'تحصيل'];
            }
        }

        foreach ($visits as $visit) {
            $cid = $visit->client_id;
            $date = Carbon::parse($visit->visit_date)->format('Y-m-d');

            $planState = $this->visitStateOf($visit, $visitStates[(int) $cid] ??= $this->clientVisitStates((int) $cid));

            if (in_array($planState['key'], ['visited', 'in_progress', 'upcoming', 'cancelled'], true)) {
                $sLabel = $planState['label'];
                $sClass = $planState['tone'];
            } elseif (! empty($visit->justification) && $visit->justification_approved == 1) {
                $sLabel = 'تبرير مقبول';
                $sClass = 'info';
            } elseif (! empty($visit->justification) && $visit->justification_approved == 2) {
                $sLabel = 'تبرير مرفوض';
                $sClass = 'danger';
            } elseif (! empty($visit->justification)) {
                $sLabel = 'بانتظار الموافقة';
                $sClass = 'warning';
            } else {
                $sLabel = 'غير مكتملة';
                $sClass = 'danger';
            }

            $activities = [];
            if (isset($invByClientDay[$cid][$date])) {
                if ($invByClientDay[$cid][$date]['sales'] > 0) {
                    $activities[] = ['type' => 'فاتورة', 'amount' => $invByClientDay[$cid][$date]['sales'], 'class' => 'success', 'icon' => 'fa-file-text-o'];
                }
                if ($invByClientDay[$cid][$date]['return'] > 0) {
                    $activities[] = ['type' => 'مرتجع', 'amount' => $invByClientDay[$cid][$date]['return'], 'class' => 'danger', 'icon' => 'fa-undo'];
                }
            }
            if (isset($colByClientDay[$cid][$date]) && $colByClientDay[$cid][$date] > 0) {
                $activities[] = ['type' => 'تحصيل', 'amount' => $colByClientDay[$cid][$date], 'class' => 'info', 'icon' => 'fa-money'];
            }

            // وابغى ما يجلب اي زيارة الا اذا لها ملاحضة او تبرير او حركة مالية سواء سند او فاتورة
            if (empty($visit->justification) && empty($activities)) {
                continue;
            }

            $visitsTimeline[$cid][] = [
                'visit_date' => $date,
                'visit_time' => $visit->created_at ? Carbon::parse($visit->created_at)->format('H:i') : null,
                'status_label' => $sLabel,
                'status_class' => $sClass,
                'justification' => $visit->justification,
                'activities' => $activities,
            ];
        }

        return ['visits_timeline' => $visitsTimeline, 'unified_history' => $unifiedHistory];
    }

    /**
     * جلب تفاصيل العميل عبر الـ AJAX للنوافذ المنبثقة
     */
    public function getClientDetails(Request $request)
    {
        $clientId = $request->input('client_id');
        if (! $clientId) {
            return response()->json(['error' => 'Client ID is missing'], 400);
        }

        if (! $this->clientVisibleForReport($request, $clientId)) {
            return response()->json(['error' => 'هذا العميل خارج نطاقك'], 403);
        }

        try {
            $client = Client::with(['employee', 'branch', 'status_client'])->find($clientId);
            $clientBasicInfo = [
                'client_code' => $client ? $client->client_code : '',
                'branch_name' => ($client && $client->branch) ? $client->branch->name : 'غير محدد',
                'employee_name' => ($client && $client->employee) ? ($client->employee->first_name.' '.$client->employee->middle_name) : 'غير محدد',
                'client_status_name' => ($client && $client->status_client) ? $client->status_client->name : 'غير محدد',
                'client_status_color' => ($client && $client->status_client) ? $client->status_client->color : '#6c757d',
                'client_balance' => $client ? number_format((float) $client->balance, 2) : '0.00',
            ];

            // 1. جلب الملاحظات والصور
            $clientRelations = ClientRelation::with(['employee.employee', 'clientEmployee'])
                ->where('client_id', $clientId)
                ->orderBy('created_at', 'desc')
                ->take(20)
                ->get();

            $notesList = $clientRelations->map(function ($note) {
                $empName = $note->employee ? $note->employee->name : 'غير معروف';
                if ($note->employee && $note->employee->employee) {
                    $empName = $note->employee->employee->first_name.' '.$note->employee->employee->middle_name;
                }

                $images = [];
                if (! empty($note->image)) {
                    $images[] = $note->image;
                }
                if ($note->clientEmployee && ! empty($note->clientEmployee->image)) {
                    $img = $note->clientEmployee->image;
                    $images[] = str_contains($img, 'uploads/client_notes/') ? $img : 'uploads/client_notes/'.$img;
                }
                if (! empty($note->attachments)) {
                    $rawAtt = $note->attachments;
                    $atts = is_string($rawAtt) ? (json_decode($rawAtt, true) ?? explode(',', $rawAtt)) : (is_array($rawAtt) ? $rawAtt : []);
                    foreach ($atts as $att) {
                        if (is_string($att) && trim($att) !== '') {
                            $images[] = trim($att);
                        }
                    }
                }

                return [
                    'date' => Carbon::parse($note->date ?? $note->created_at)->format('Y-m-d H:i'),
                    'deposit' => $note->deposit_count,
                    'description' => $note->description,
                    'competitor_docs' => $note->competitor_documents,
                    'images' => array_filter($images),
                    'employee_name' => $empName,
                ];
            })->toArray();

            // 2. جلب التايم لاين والزيارات
            $timelineData = $this->getUnifiedTimelineData([$clientId]);
            $clientVisits = $timelineData['visits_timeline'][$clientId] ?? [];
            $unifiedHistory = $timelineData['unified_history'][$clientId] ?? [];
            usort($unifiedHistory, fn ($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

            $visitInfo = null;
            if (! empty($clientVisits)) {
                $lastVisit = $clientVisits[0];
                $visitDate = Carbon::parse($lastVisit['visit_date']);
                $now = Carbon::now();
                $diffDays = $visitDate->diffInDays($now);

                $timeSinceVisit = null;
                $timeSinceClass = 'secondary';
                if ($diffDays == 0) {
                    $timeSinceVisit = 'اليوم';
                    $timeSinceClass = 'success';
                } elseif ($diffDays == 1) {
                    $timeSinceVisit = 'منذ يوم';
                    $timeSinceClass = 'success';
                } elseif ($diffDays <= 7) {
                    $timeSinceVisit = 'منذ '.$diffDays.' أيام';
                    $timeSinceClass = 'info';
                } elseif ($diffDays <= 30) {
                    $weeks = ceil($diffDays / 7);
                    $timeSinceVisit = 'منذ '.$weeks.($weeks == 1 ? ' أسبوع' : ' أسابيع');
                    $timeSinceClass = 'warning';
                } else {
                    $months = ceil($diffDays / 30);
                    $timeSinceVisit = 'منذ '.$months.($months == 1 ? ' شهر' : ' أشهر');
                    $timeSinceClass = 'danger';
                }

                $visitInfo = [
                    'last_visit_date' => $lastVisit['visit_date'],
                    'last_visit_time' => $lastVisit['visit_time'] ?? null,
                    'total_visits' => count($clientVisits),
                    'status_label' => $lastVisit['status_label'],
                    'status_class' => $lastVisit['status_class'],
                    'time_since_visit' => $timeSinceVisit,
                    'time_since_class' => $timeSinceClass,
                    'justification' => $lastVisit['justification'],
                ];
            }

            return response()->json([
                'basic_info' => $clientBasicInfo,
                'notes_list' => $notesList,
                'visits_timeline' => $clientVisits,
                'unified_history' => $unifiedHistory,
                'visit_info' => $visitInfo,
            ]);

        } catch (\Exception $e) {
            Log::error('AJAX Client Details Error: '.$e->getMessage());

            return response()->json(['error' => 'حدث خطأ داخلي'], 500);
        }
    }

    /**
     * تجميع البيانات لتقرير تغير مستويات العملاء
     */
    private function groupCollectionsDataForTargetChanges($collectionsData)
    {
        $grouped = [];
        foreach ($collectionsData as $collection) {
            $clientId = $collection['client_id'] ?? null;
            if (! $clientId) {
                continue;
            }

            if (! isset($grouped[$clientId])) {
                $grouped[$clientId] = [
                    'client_id' => $clientId,
                    'client_code' => $collection['client_code'] ?? '-',
                    'client_name' => $collection['client_name'] ?? 'عام',
                    'branch_name' => $collection['branch_name'] ?? '-',
                    'client_previous_category' => $collection['client_previous_category'] ?? 'غير مقيّم',
                    'client_previous_category_color' => $collection['client_previous_category_color'] ?? '#6c757d',
                    'client_category' => $collection['client_category'] ?? 'غير مقيّم',
                    'client_category_color' => $collection['client_category_color'] ?? '#6c757d',
                    'target_change' => $collection['target_change'] ?? 'none',
                    'total_collections' => 0.0,
                ];
            }
            $grouped[$clientId]['total_collections'] += (float) ($collection['amount'] ?? 0);
        }

        return array_values($grouped);
    }
}
