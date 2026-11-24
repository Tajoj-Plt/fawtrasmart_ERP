<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\JournalEntryDetail;
use App\Models\User;
use App\Support\AccountingSettings;
use App\Support\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Support\SalesLedger;

class PaymentsByCustomerController extends Controller
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
        $this->middleware('permission:sales.reports.payments_by_customer');
    }

    use SalesReportTrait;

    /**
     * خيارات التجميع للملخص
     */
    protected $groupByOptions = [
        'day' => 'اليوم',
        'day_employee' => 'اليوم والموظف',
        'month' => 'الشهر',
        'region_month' => 'المجموعة + الشهر',
        'employee' => 'الموظف',
        'client' => 'العميل',
        'branch' => 'الفرع',
        'region' => 'المجموعة',
        'account_type' => 'نوع الحساب',
        'treasury' => 'الخزينة/البنك',
    ];

    /**
     * عرض صفحة التحصيلات بالتفصيل
     * يدعم الفترات الزمنية مثل تقارير المبيعات (يوم، أسبوع، شهر، سنة)
     */
    public function index(Request $request)
    {
        try {
            return view('sales::sals_report.paymentsReport.Payments_By_Customer', $this->buildReport($request, true));
        } catch (\Exception $e) {
            Log::error('Collections Report Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
            ]);

            return redirect()->back()->withErrors(['error' => 'حدث خطأ أثناء تحميل بيانات المدفوعات: '.$e->getMessage()]);
        }
    }

    /**
     * أرقامُ الشاشة نفسُها JSON — مسارُ `…Ajax` كان يشير إلى دالّةٍ غير
     * موجودة فيُرجع خطأ ٥٠٠. البانيةُ واحدة، فلا يختلف رقمٌ بين الاثنين.
     */
    public function ajax(Request $request): JsonResponse
    {
        try {
            $data = $this->buildReport($request, false);

            return response()->json([
                'success' => true,
                'period' => $data['period'],
                'start_date' => $data['startDate']->toDateString(),
                'end_date' => $data['endDate']->toDateString(),
                'view_type' => $data['viewType'],
                'group_by' => $data['groupBy'],
                'totals' => $data['totals'],
                'statistics' => $data['statistics'],
                'report_data' => $data['reportData'],
            ]);
        } catch (\Exception $e) {
            Log::error('Collections Report Ajax Error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء تحميل بيانات المدفوعات'], 500);
        }
    }

    /**
     * بانيةُ التقرير المشتركة بين الشاشة و`ajax()`.
     *
     * @param  bool  $withFilterLists  قوائمُ الفلترة للشاشة وحدَها
     * @return array<string, mixed>
     */
    private function buildReport(Request $request, bool $withFilterLists): array
    {
        // =================== معالجة الفترة الزمنية (مثل SalesProfitsController) ===================
        $period = $request->input('period', '30'); // الافتراضي شهر

        // مصدرٌ واحد للمدى (SalesReportTrait) تشترك فيه تقارير المبيعات
        // كلها، فلا يعطي زرُّ «أسبوع» مدىً هنا ومدىً آخر في تقرير المبيعات.
        [$startDate, $endDate] = $this->parseDateRangeRequest($request);
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        // الحصول على الفلاتر من الطلب
        $branchId = $request->input('branch_id');
        $employeeId = $request->input('employee_id');
        $clientId = $request->input('client_id');
        $neighborhoodId = $request->input('neighborhood_id');

        // خيارات العرض
        $viewType = $request->input('view_type', 'details');
        $groupBy = $request->input('group_by', 'client'); // الافتراضي هنا العميل
        $isSummary = $viewType === 'summary';

        // التحقق من صلاحيات المستخدم
        $user = auth()->user();
        $currentBranchId = $user->branch_id;
        $isMainBranch = false;

        if ($currentBranchId) {
            $branch = Branch::find($currentBranchId);
            $isMainBranch = $branch && $branch->is_main == 1;
        }

        /* العقدةُ الفعّالة من المصدر المشترك لتقارير المبيعات: يقرأ
           `tree_node` و`cascade_node` القديمة، ويقصر ما يُطلب على شجرة
           فرع الشريط العلويّ، ويعامل الفرعَ الرئيسيَّ «المنشأةَ كلَّها»،
           ثمّ يقصّ على نطاق الرؤية. كانت نسخةٌ محليّةٌ هنا لا تعرف إلّا
           `b_` وتفلتر بالرئيسيّ على شجرته هو. */
        $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $currentBranchId);

        // الحصول على بيانات التحصيلات (المدفوعات)
        $collectionsData = $this->getCollectionsDetail(
            $startDate,
            $endDate,
            $effectiveBranchId,
            $employeeId,
            $clientId,
            $neighborhoodId
        );

        // حساب الإحصائيات الإجمالية
        $statistics = $this->calculateStatistics($collectionsData, $startDate);

        /* العدّ = سنداتُ التحصيل الفعليّة وحدَها، في الإجمالي وفي كلّ
           مجموعة بالقاعدة نفسِها. كان الإجماليُّ يعدّ الأصولَ الملغاة
           (٩٤٠١) والمجموعاتُ تعدّ معها صفوفَ الصرف والإلغاء (٩٥٢٤).
           وصرفُ قيمة المرتجع نقداً يبقى صفّاً سالباً في المبلغ ويُعدّ
           منفصلاً في `refunds_count`. */
        $receipts = $collectionsData->where('is_return', false);
        $refunds = $collectionsData->where('is_return', true);
        $totals = [
            'count' => (int) $receipts->count(),
            // بلا `max(0)`: صافٍ سالبٌ رقمٌ صحيح، وإخفاؤه صفراً يكذب
            'amount' => (float) $collectionsData->sum('amount'),
            'gross_amount' => (float) $receipts->sum('amount'),
            'refunds_count' => (int) $refunds->count(),
            'refunds_amount' => (float) abs($refunds->sum('amount')),
            // الاسمان القديمان — باقيان لمن يقرأهما
            'return_count' => (int) $refunds->count(),
            'return_amount' => (float) abs($refunds->sum('amount')),
        ];

        $reportData = $isSummary
            ? $this->groupCollectionsData($collectionsData, $groupBy)
            : $this->groupCollectionsDataWithDetails($collectionsData, $groupBy);

        $data = compact(
            'collectionsData',
            'reportData',
            'totals',
            'effectiveBranchId',
            'employeeId',
            'clientId',
            'neighborhoodId',
            'isMainBranch',
            'statistics',
            'startDate',
            'endDate',
            'viewType',
            'groupBy',
            'isSummary',
            'period'
        );

        if (! $withFilterLists) {
            return $data;
        }

        // الحصول على قوائم الفلترة
        $data['branches'] = $this->getBranchesForFilter($isMainBranch, $currentBranchId);
        $data['employees'] = $this->getEmployeesForFilter($isMainBranch, $currentBranchId, $effectiveBranchId);
        $data['clients'] = $this->getClientsForFilter($effectiveBranchId);
        $data['regionGroups'] = $this->getRegionGroupsForFilter($effectiveBranchId);
        $data['groupByOptions'] = $this->groupByOptions;

        // شجرةُ الـ cascade — مقصورةً على فرع الشريط العلويّ وما تحته
        $data['cascadeBranches'] = collect($this->trimNodesToHeader(
            Branch::select('id', 'name', 'parent_id', 'is_territory')->orderBy('name')->get()
                ->map(fn ($b) => $b->only(['id', 'name', 'parent_id', 'is_territory']))
                ->all()
        ));

        return $data;
    }

    /**
     * تجميع بيانات التحصيلات حسب الخيار المحدد (للملخص)
     */
    private function groupCollectionsData($collectionsData, $groupBy)
    {
        $grouped = [];

        foreach ($collectionsData as $collection) {
            $key = $this->getGroupKey($collection, $groupBy);
            $name = $this->getGroupName($collection, $groupBy);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $name,
                    'count' => 0,
                    'refunds_count' => 0,
                    'amount' => 0.0,
                    'date_key' => $key,
                ];
            }

            // سندُ تحصيلٍ يُعدّ في «count»، وصرفُ قيمة مرتجعٍ في «refunds_count» — كالإجمالي
            $grouped[$key][empty($collection['is_return']) ? 'count' : 'refunds_count']++;
            $grouped[$key]['amount'] += (float) ($collection['amount'] ?? 0);
        }

        // ترتيب حسب التاريخ تنازلياً عند التجميع باليوم/الشهر، وإلا حسب المبلغ
        if (in_array($groupBy, ['day', 'day_employee', 'month', 'region_month'])) {
            uksort($grouped, function ($a, $b) {
                return strcmp($b, $a);
            });
        } else {
            uasort($grouped, function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });
        }

        return $grouped;
    }

    /**
     * تجميع بيانات التحصيلات مع التفاصيل (للعرض التفصيلي)
     */
    private function groupCollectionsDataWithDetails($collectionsData, $groupBy)
    {
        $grouped = [];

        foreach ($collectionsData as $collection) {
            $key = $this->getGroupKey($collection, $groupBy);
            $name = $this->getGroupName($collection, $groupBy);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $name,
                    'count' => 0,
                    'refunds_count' => 0,
                    'amount' => 0.0,
                    'items' => [],
                ];
            }

            // سندُ تحصيلٍ يُعدّ في «count»، وصرفُ قيمة مرتجعٍ في «refunds_count» — كالإجمالي
            $grouped[$key][empty($collection['is_return']) ? 'count' : 'refunds_count']++;
            $grouped[$key]['amount'] += (float) ($collection['amount'] ?? 0);
            $grouped[$key]['items'][] = $collection;
        }

        // ترتيب حسب التاريخ تنازلياً عند التجميع باليوم/الشهر، وإلا حسب المبلغ
        if (in_array($groupBy, ['day', 'day_employee', 'month', 'region_month'])) {
            uksort($grouped, function ($a, $b) {
                return strcmp($b, $a);
            });
        } else {
            uasort($grouped, function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });
        }

        return $grouped;
    }

    /**
     * الحصول على مفتاح التجميع
     */
    private function getGroupKey($collection, $groupBy)
    {
        switch ($groupBy) {
            case 'day':
                $date = $collection['date'];

                return $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
            case 'day_employee':
                $date = $collection['date'];
                $dateStr = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;

                return $dateStr.'_'.($collection['employee_id'] ?? 'unknown');
            case 'month':
                $date = $collection['date'];

                return $date instanceof \Carbon\Carbon
                    ? $date->format('Y-m')
                    : (\Carbon\Carbon::parse($date)->format('Y-m'));
            case 'region_month':
                $date = $collection['date'];
                $monthStr = $date instanceof \Carbon\Carbon
                    ? $date->format('Y-m')
                    : (\Carbon\Carbon::parse($date)->format('Y-m'));

                return ($collection['region_id'] ?? 'unknown').'_'.$monthStr;
            case 'employee':
                return $collection['employee_id'] ?? 'unknown';
            case 'client':
                return $collection['client_id'] ?? 'unknown';
            case 'branch':
                return $collection['branch_id'] ?? 'unknown';
            case 'region':
                return $collection['region_id'] ?? 'unknown';
            case 'account_type':
                return $collection['account_type'] ?? 'unknown';
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
                $date = $collection['date'];
                if ($date instanceof \Carbon\Carbon) {
                    return $date->format('Y-m-d').' ('.$this->getArabicDayName($date->dayOfWeek).')';
                }

                return $date;
            case 'day_employee':
                $date = $collection['date'];
                $dateStr = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
                $dayName = $date instanceof \Carbon\Carbon ? ' ('.$this->getArabicDayName($date->dayOfWeek).')' : '';
                $employeeName = $collection['employee_name'] ?? 'غير محدد';

                return $dateStr.$dayName.' - '.$employeeName;
            case 'month':
                $date = $collection['date'];
                $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);

                return $carbon->format('Y-m').' ('.$this->getArabicMonthName($carbon->month).')';
            case 'region_month':
                $date = $collection['date'];
                $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
                $regionName = $collection['region_name'] ?? 'غير محدد';

                return $regionName.' — '.$this->getArabicMonthName($carbon->month).' '.$carbon->format('Y');
            case 'employee':
                return $collection['employee_name'] ?? 'غير محدد';
            case 'client':
                return $collection['client_name'] ?? 'غير محدد';
            case 'branch':
                return $collection['branch_name'] ?? 'غير محدد';
            case 'region':
                return $collection['region_name'] ?? 'غير محدد';
            case 'account_type':
                return $collection['account_type'] ?? 'غير محدد';
            case 'treasury':
                return $collection['treasury_account'] ?? 'غير محدد';
            default:
                return 'غير محدد';
        }
    }

    /**
     * الحصول على اسم الشهر بالعربية
     */
    private function getArabicMonthName($month)
    {
        $months = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
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
    private function getCollectionsDetail($startDate, $endDate, $branchId, $employeeId = null, $clientId = null, $neighborhoodId = null)
    {
        /* حساباتُ الخزائن والبنوك من «الحسابات المحاسبية» وحدَها — لا ثابتَ
           برمجيّاً ولا احتياطاً: مفتاحٌ غير مربوط = لا حسابات = تقريرٌ فارغ،
           لا أرقامٌ من حسابٍ لم يختره المحاسب. */
        $treasuryAndBankAccounts = Account::whereIn('parent_id', $this->cashParentIds())->pluck('id')->toArray();

        if (empty($treasuryAndBankAccounts)) {
            return collect([]);
        }

        // حدّا المدى بالتاريخ وحدَه — العمودُ `date`، فالحدّان داخلان
        $fromDate = $startDate->toDateString();
        $toDate = $endDate->toDateString();

        /* سندُ القبض الملغى وقيدُ إلغائه (`PCAN`) يخرجان **معاً** — كدفتر
           العملاء (`SalesLedger::cancelledEntryIds()`). كان الأصلُ يبقى
           تحصيلاً باسم منشئه، والعكسيُّ يُطرح باسم من ألغى: فيظهر المديرُ
           بتحصيلٍ سالب (−١١٬٤٨١٫٤٢) ويُضخَّم المندوب بما أُلغي له. */
        $cancelledEntryIds = SalesLedger::cancelledEntryIds();

        // توسيع الفرع: يشمل الفرع نفسه + كل الـ territories التابعة له (المجموعات بعد الترحيل)
        $branchIds = $branchId ? $this->expandBranchToDescendants($branchId) : null;

        /* «تابع لـ» = عقدةُ العميل وحدَها (`SalesLedger::matchClientNodes`).
           كان `clients.branch_id OR je.branch_id OR حيّ`: يُغفل `tree_node_id`،
           وختمُ القيد يُدخل نقديةً بلا عميل (قيود يومية ومصروفات وأرصدة
           افتتاحية) وتحصيلاتِ عملاء عقدٍ أخرى — «منطقة الرياض» ١٥٣٬١٦٧٫٧٦
           مقابل ١٤٦٬٩٨٣ في الدفتر. */
        $constrainToClientNodes = function ($query) use ($branchIds) {
            $query->whereIn('journal_entries.client_id', function ($sub) use ($branchIds) {
                $sub->select('id')->from('clients');
                SalesLedger::matchClientNodes($sub, 'clients', $branchIds);
            });
        };

        // ========== 1. جلب التحصيلات (مدين > 0 في حسابات الخزينة/البنوك) ==========
        $collectionsQuery = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
            ->where('journal_entry_details.debit', '>', 0)
            ->whereBetween('journal_entries.date', [$fromDate, $toDate])
            ->where('journal_entries.status', 1)
            // استثناء التحويلات بين الخزائن/البنوك (نفس طريقة DashboardController)
            ->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
                $subQuery->select(DB::raw(1))
                    ->from('journal_entry_details as jed2')
                    ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                    ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                    ->where('jed2.credit', '>', 0);
            });

        if ($cancelledEntryIds !== []) {
            $collectionsQuery->whereNotIn('journal_entries.id', $cancelledEntryIds);
        }

        if ($branchIds) {
            $constrainToClientNodes($collectionsQuery);
        } else {
            $collectionsQuery->whereNotNull('journal_entries.client_id');
        }

        if ($employeeId) {
            $collectionsQuery->where('journal_entries.created_by_employee', $employeeId);
        }

        /* ونطاقُ الرؤية فوق فلتر الموظف: الفلترُ اختيارُ المستخدم،
           وهذا حدُّه — محورُ الأشخاص وحدَه، إذ قيدُ الفرع مطبَّقٌ
           أعلاه على القائمة المقصوصة. */
        if (DataScope::isPeopleAxis()) {
            $collectionsQuery->whereIn('journal_entries.created_by_employee', DataScope::userIds() ?? [0]);
        }
        if ($clientId) {
            $collectionsQuery->where('journal_entries.client_id', $clientId);
        }
        if ($neighborhoodId) {
            $collectionsQuery->join('neighborhoods', 'clients.id', '=', 'neighborhoods.client_id')
                ->where('neighborhoods.region_id', $neighborhoodId);
        }

        // الفتراتُ المقفلة — كبقيّة تقارير المبيعات، على تاريخ القيد
        $this->excludeClosedRanges($collectionsQuery, 'journal_entries.date');

        $collectionDetailIds = $collectionsQuery->pluck('journal_entry_details.id')->toArray();

        // ========== 2. جلب مردودات المبيعات المدفوعة من الخزينة (نفس طريقة DashboardController) ==========
        $returnsQuery = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
            ->where('journal_entry_details.credit', '>', 0)
            ->whereBetween('journal_entries.date', [$fromDate, $toDate])
            ->where('journal_entries.status', 1)
            /* يُطرح من التحصيل صرفُ قيمة المرتجع وحده. قيدُ إلغاء سند القبض
               (`PCAN`) لم يعد يُطرح هنا: الأصلُ الملغى نفسُه مستبعدٌ أعلاه،
               فطرحُ العكسيّ فوقه يطرح المبلغ مرّتين. */
            ->whereIn('journal_entries.reference_type', SalesLedger::RETURN_TYPES);

        if ($branchIds) {
            $constrainToClientNodes($returnsQuery);
        } else {
            $returnsQuery->whereNotNull('journal_entries.client_id');
        }
        if ($employeeId) {
            $returnsQuery->where('journal_entries.created_by_employee', $employeeId);
        }

        /* ونطاقُ الرؤية فوق فلتر الموظف: الفلترُ اختيارُ المستخدم،
           وهذا حدُّه — محورُ الأشخاص وحدَه، إذ قيدُ الفرع مطبَّقٌ
           أعلاه على القائمة المقصوصة. */
        if (DataScope::isPeopleAxis()) {
            $returnsQuery->whereIn('journal_entries.created_by_employee', DataScope::userIds() ?? [0]);
        }
        if ($clientId) {
            $returnsQuery->where('journal_entries.client_id', $clientId);
        }
        if ($neighborhoodId) {
            $returnsQuery->join('neighborhoods as n2', 'clients.id', '=', 'n2.client_id')
                ->where('n2.region_id', $neighborhoodId);
        }

        $this->excludeClosedRanges($returnsQuery, 'journal_entries.date');

        $returnDetailIds = $returnsQuery->pluck('journal_entry_details.id')->all();

        // ========== 3. دمج وجلب البيانات ==========
        $allDetailIds = array_merge($collectionDetailIds, $returnDetailIds);

        if (empty($allDetailIds)) {
            return collect([]);
        }

        $allDetails = JournalEntryDetail::whereIn('id', $allDetailIds)
            ->with(['journalEntry.client.Neighborhoodname.Region', 'journalEntry.client.branch', 'account'])
            ->get();

        // جلب بيانات الموظفين دفعة واحدة
        $userIds = $allDetails->pluck('journalEntry.created_by_employee')->unique()->filter()->toArray();
        $users = User::whereIn('id', $userIds)->with('branch')->get()->keyBy('id');

        $returnDetailIdsFlipped = array_flip($returnDetailIds);
        $collectionsDetail = [];

        foreach ($allDetails as $detail) {
            $journalEntry = $detail->journalEntry;
            $createdByUser = $users[$journalEntry->created_by_employee] ?? null;
            $isReturn = isset($returnDetailIdsFlipped[$detail->id]);

            $regionName = null;
            $regionId = null;
            if ($journalEntry->client && $journalEntry->client->Neighborhoodname && $journalEntry->client->Neighborhoodname->Region) {
                $regionName = $journalEntry->client->Neighborhoodname->Region->name;
                $regionId = $journalEntry->client->Neighborhoodname->Region->id;
            }

            // المردودات تظهر بمبلغ سالب (نفس منطق الداش بورد: totalCollected - totalReturned)
            $amount = $isReturn ? -1 * (float) $detail->credit : (float) $detail->debit;

            $collectionsDetail[] = [
                'id' => $journalEntry->id,
                'date' => $journalEntry->date,
                'reference_number' => $journalEntry->reference_number,
                'description' => $journalEntry->description,
                'employee_name' => $createdByUser->name ?? 'غير محدد',
                'employee_id' => $journalEntry->created_by_employee,
                'client_name' => $journalEntry->client?->trade_name ?? $journalEntry->client?->name ?? 'عام',
                'client_id' => $journalEntry->client_id,
                /* الفرعُ فرعُ العميل وحدَه — كفلتر «تابع لـ». كان المفتاحُ يسقط
                   إلى ختم القيد والاسمُ إلى فرع منشئه: مجموعةٌ مفتاحُها فرعٌ
                   واسمُها فرعٌ آخر. */
                'branch_name' => $journalEntry->client?->branch?->name ?? 'غير محدد',
                'branch_id' => $journalEntry->client?->branch_id,
                'treasury_account' => $detail->account?->name ?? 'غير محدد',
                'account_id' => $detail->account_id,
                'amount' => $amount,
                'account_type' => $this->getAccountType($detail->account),
                'note' => $detail->description,
                'region_name' => $regionName ?? 'غير محدد',
                'region_id' => $regionId,
                'is_return' => $isReturn,
                // السندُ الملغى وعكسُه مستبعدان من المصدر — المفتاحُ باقٍ للعرض
                'is_cancellation' => false,
            ];
        }

        usort($collectionsDetail, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return collect($collectionsDetail);
    }

    /**
     * أبَوا الخزائن والبنوك كما رُبطا في «الحسابات المحاسبية» — بلا احتياط.
     *
     * @return array<int, int>
     */
    private function cashParentIds(): array
    {
        return array_values(array_filter([
            AccountingSettings::id('cash_treasuries_parent'),
            AccountingSettings::id('bank_accounts_parent'),
        ]));
    }

    /**
     * تحديد نوع الحساب (خزينة أم بنك)
     */
    private function getAccountType($account)
    {
        if (! $account || ! $account->parent_id) {
            return 'غير محدد';
        }

        if ($account->parent_id == AccountingSettings::id('cash_treasuries_parent')) {
            return 'خزينة';
        } elseif ($account->parent_id == AccountingSettings::id('bank_accounts_parent')) {
            return 'بنك';
        }

        return 'غير محدد';
    }

    /**
     * حساب الإحصائيات الإجمالية
     */
    private function calculateStatistics($collectionsData, $startDate)
    {
        $totalCollections = (float) $collectionsData->sum('amount');
        $collectionCount = $collectionsData->where('is_return', false)->count();
        $returnCount = $collectionsData->where('is_return', true)->count();
        $openingBalance = 0;

        return [
            'opening_balance' => $openingBalance,
            'total_collections' => $totalCollections,
            'collection_count' => $collectionCount,
            'return_count' => $returnCount,
            'average_collection' => $collectionCount > 0 ? $totalCollections / $collectionCount : 0,
            'closing_balance' => $openingBalance + $totalCollections,
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

        // القائمةُ تتبع النطاق كما تتبعه الأرقام — وإلّا عَلِم من
        // المنسدلة وحدَها أسماءَ من لا يحقّ له الاطّلاع على بياناتهم.
        DataScope::constrainCreator($query, 'users.id');

        return $query->orderBy('users.name')->get();
    }

    /**
     * الحصول على قائمة العملاء
     */
    private function getClientsForFilter($branchId = null)
    {
        $query = Client::where('status', 'active');

        DataScope::constrainOwner($query, 'employee_id');

        if ($branchId) {
            $branchIds = $this->expandBranchToDescendants($branchId);

            // قائمةُ العملاء على محور الأرقام نفسِه: عقدةُ العميل (`tree_node_id` أولاً)
            $query->where(fn ($q) => SalesLedger::matchClientNodes($q, 'clients', $branchIds));
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

        return $branch->descendantBranchIds()
            ->push((int) $branch->id)
            ->unique()
            ->values()
            ->all();
    }
}
