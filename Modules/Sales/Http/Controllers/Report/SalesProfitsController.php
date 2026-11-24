<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\User;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesProfitsController extends Controller
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
        $this->middleware('permission:sales.reports.profits');
    }

    use SalesReportTrait;

    /**
     * عرض تقرير أرباح المبيعات حسب العميل
     * يعتمد على قيدين: قيد المبيعات وقيد تكلفة المبيعات
     */
    public function index(Request $request)
    {
        // =================== معالجة الفترة الزمنية ===================
        /*
         * مصدرٌ واحد للمدى (SalesReportTrait): كان زرّ «أسبوع» هنا ثمانيةَ
         * أيامٍ وفي تقارير المبيعات سبعة، فيعطي الزرُّ الواحد مدىً مختلفاً
         * باختلاف التقرير — وتتبدّل الفترةُ على المستخدم وهو ينتقل بينها.
         */
        [$fromDate, $toDate] = $this->parseDateRangeRequest($request);

        // =================== تحديد الفرع الفعّال ===================
        $user = User::find(auth()->user()->id);
        $userBranchId = $user->branch_id;
        $currentBranch = Branch::find($userBranchId);
        $isMainBranch = $currentBranch && $currentBranch->is_main == 1;

        $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
        $branchIds = $effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null;

        // =================== جلب القوائم المنسدلة المفلترة ===================
        $dropdowns = $this->getFilteredDropdowns($branchIds);
        extract($dropdowns); // $employees, $clients, $regionGroups, $branches, $cascadeBranches, $treeNodes
        $users = $employees;
        $categories = CategoriesClient::all();

        $selectedBranchId = $isMainBranch ? ($request->input('branch_id') ?: null) : $userBranchId;
        $selectedRegionGroupId = $request->input('region_group_id');

        // =================== معالجة نوع العرض (ملخص/تفاصيل) ===================
        $viewType = $request->input('view_type', 'summary');
        $isSummary = $viewType === 'summary';

        ['reportData' => $reportData, 'totals' => $totals] = $this->buildReport($request, $fromDate, $toDate, $effectiveBranchId);

        return view('sales::sals_report.salesRport.Sales_Profits', compact(
            'reportData',
            'employees',
            'clients',
            'categories',
            'branches',
            'users',
            'totals',
            'fromDate',
            'toDate',
            'selectedBranchId',
            'isMainBranch',
            'isSummary',
            'viewType',
            'regionGroups',
            'cascadeBranches',
            'treeNodes',
            'effectiveBranchId',
            'selectedRegionGroupId',
            'statuses',
            'registrationStages'
        ));
    }

    /**
     * AJAX لجلب بيانات التقرير — الحسابُ نفسُه الذي تعرضه الشاشة.
     *
     * كان نسخةً ثانيةً كاملة: مدىً بأيامٍ مختلفة، وفرعٌ يُقرأ من
     * `cascade_node` بلا قصٍّ على نطاق الرؤية — فيقرأ موظّفُ فرعٍ أرباحَ
     * فرعٍ آخر بتبديل رقمٍ في الطلب.
     */
    public function ajax(Request $request)
    {
        try {
            [$fromDate, $toDate] = $this->parseDateRangeRequest($request);

            $user = auth()->user();
            $isMainBranch = (bool) optional(Branch::find($user->branch_id))->is_main;
            $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $user->branch_id);

            ['reportData' => $reportData, 'totals' => $totals] = $this->buildReport($request, $fromDate, $toDate, $effectiveBranchId);

            return response()->json([
                'success' => true,
                'data' => $reportData,
                'totals' => $totals,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesProfits', 'ajax');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * أرباحُ الفترة لكلّ عميل — من القيود المرحّلة، باستعلاماتٍ مجمَّعة.
     *
     *     مبيعات        = دائن − مدين  حساب «المبيعات»
     *     مردودات       = مدين − دائن  حساب «مردودات المبيعات» (المرتجع والإشعار الدائن)
     *     تكلفة المبيعات = مدين − دائن  حساب «تكلفة المبيعات» (عكسُ المرتجع يطرح نفسَه)
     *     الربح         = المبيعات − المردودات − التكلفة
     *
     * ══════════ ما تغيّر ولماذا ══════════
     *
     * • **الفرع = عقدةُ العميل وحدَها** (`Client::scopeInTreeNode`). كان
     *   الشرط `clients.branch_id OR حيّ OR je.branch_id`: يُغفل
     *   `tree_node_id` فيسقط عميلٌ منسوبٌ صراحةً إلى العقدة، وختمُ القيد
     *   يُدخل عملاءَ عقدٍ أخرى ثم يجمع لهم حركتهم كاملة.
     * • **استعلاماتٌ مجمَّعة لا حلقةٌ على كلّ عملاء الفرع:** كان خمسةُ
     *   استعلاماتٍ لكلّ عميلٍ في النطاق ولو بلا حركة — ١١٦٩ استعلاماً في
     *   ٧ ثوانٍ لفرعٍ واحد لمدة ستة أسابيع.
     * • **الحسابات من «إعدادات الحسابات المحاسبية»** لا من ثوابت الشيفرة.
     * • **الكمّية بالوحدة الأساسية** (`quantity_in_base_unit`) ومن فواتيرَ
     *   مرحّلةٍ فقط — كانت تجمع «كرتون» و«حبة» رقماً واحداً، وتعدّ فواتيرَ
     *   لم يُرحَّل لها قيد.
     *
     * @return array{reportData: \Illuminate\Support\Collection, totals: array<string, float|int>}
     */
    private function buildReport(Request $request, Carbon $fromDate, Carbon $toDate, ?int $nodeId): array
    {
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();
        $employeeId = $request->input('employee_id');

        $accounts = array_filter([
            'sales' => AccountingSettings::id('sales'),
            'returns' => AccountingSettings::id('sales_returns'),
            'cost' => AccountingSettings::id('cost_of_sales'),
        ]);

        // =================== العملاء المشمولون ===================
        $clientsQuery = Client::query();

        if ($nodeId) {
            $clientsQuery->inTreeNode($nodeId, ! $request->boolean('exact_node'));
        }
        if ($request->filled('client_id')) {
            $clientsQuery->whereKey($request->client_id);
        }
        if ($request->filled('category_id')) {
            $clientsQuery->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $request->status === 'no_status'
                ? $clientsQuery->whereNull('status_id')
                : $clientsQuery->where('status_id', $request->status);
        }
        if ($request->filled('registration_stage')) {
            $request->registration_stage === 'no_stage'
                ? $clientsQuery->whereNull('registration_stage_id')
                : $clientsQuery->where('registration_stage_id', $request->registration_stage);
        }

        $clientIds = (clone $clientsQuery)->select('clients.id');

        // =================== المبالغ من القيود ===================
        $amounts = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->where('je.status', 1)
            ->whereBetween('je.date', [$from, $to])
            ->whereIn('jed.account_id', array_values($accounts) ?: [0])
            ->whereIn('je.client_id', $clientIds)
            ->when($employeeId, fn ($q) => $q->where('je.created_by_employee', $employeeId));

        $this->excludeClosedRanges($amounts, 'je.date');
        $this->scopeJournalToVisibleUsers($amounts, 'je.created_by_employee');

        $amountRows = $amounts
            ->groupBy('je.client_id', 'jed.account_id')
            ->select([
                'je.client_id',
                'jed.account_id',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
                DB::raw('COUNT(DISTINCT je.id) as entries'),
            ])
            ->get();

        // =================== الكمّيات من بنود الفواتير المرحّلة ===================
        $quantities = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->whereIn('i.type', ['normal', 'returned'])
            ->whereBetween('i.invoice_date', [$from, $to])
            ->whereIn('i.client_id', $clientIds)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('journal_entries as qje')
                    ->whereColumn('qje.invoice_id', 'i.id')
                    ->where('qje.status', 1);
            })
            ->when($employeeId, fn ($q) => $q->where('i.created_by', $employeeId));

        $this->excludeClosedRanges($quantities, 'i.invoice_date');

        $quantityRows = $quantities
            ->groupBy('i.client_id', 'i.type')
            ->select([
                'i.client_id',
                'i.type',
                DB::raw('SUM(COALESCE(ii.quantity_in_base_unit, ii.quantity)) as qty'),
            ])
            ->get();

        // =================== التجميع لكلّ عميل ===================
        $perClient = [];
        $blank = fn () => [
            'sales_qty' => 0.0, 'sales_amount' => 0.0,
            'returns_qty' => 0.0, 'returns_amount' => 0.0,
            'cost_of_sales' => 0.0, 'sales_count' => 0,
        ];

        foreach ($amountRows as $row) {
            $id = (int) $row->client_id;
            $perClient[$id] ??= $blank();
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            match ((int) $row->account_id) {
                (int) ($accounts['sales'] ?? -1) => [
                    $perClient[$id]['sales_amount'] += $credit - $debit,
                    $perClient[$id]['sales_count'] += (int) $row->entries,
                ],
                (int) ($accounts['returns'] ?? -1) => $perClient[$id]['returns_amount'] += $debit - $credit,
                (int) ($accounts['cost'] ?? -1) => $perClient[$id]['cost_of_sales'] += $debit - $credit,
                default => null,
            };
        }

        /* كمّيةُ الإشعارات الدائنة المرحّلة تُضاف إلى المرتجع — مبلغُها في
           حساب المردودات مع المرتجعات، فكانت الكمّيةُ وحدَها تُسقطها. */
        $creditNoteQuantities = DB::table('invoice_items as ii')
            ->join('credit_notifications as cn', 'cn.id', '=', 'ii.credit_note_id')
            ->whereBetween('cn.credit_date', [$from, $to])
            ->whereIn('cn.client_id', $clientIds)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('journal_entries as cje')
                    ->where('cje.reference_type', 'CN')
                    ->whereColumn('cje.reference_number', 'cn.id')
                    ->where('cje.status', 1);
            })
            ->when($employeeId, fn ($q) => $q->where('cn.created_by', $employeeId));

        $this->excludeClosedRanges($creditNoteQuantities, 'cn.credit_date');

        foreach ($creditNoteQuantities->groupBy('cn.client_id')->select(['cn.client_id', DB::raw('SUM(COALESCE(ii.quantity_in_base_unit, ii.quantity)) as qty')])->get() as $row) {
            $id = (int) $row->client_id;
            $perClient[$id] ??= $blank();
            $perClient[$id]['returns_qty'] += (float) $row->qty;
        }

        foreach ($quantityRows as $row) {
            $id = (int) $row->client_id;
            $perClient[$id] ??= $blank();
            $key = $row->type === 'returned' ? 'returns_qty' : 'sales_qty';
            $perClient[$id][$key] += (float) $row->qty;
        }

        $clients = Client::with(['status_client', 'registrationStage'])
            ->whereIn('id', array_keys($perClient))
            ->get()
            ->keyBy('id');

        $reportData = collect();
        $totals = [
            'sales_qty' => 0, 'sales_amount' => 0,
            'returns_qty' => 0, 'returns_amount' => 0,
            'net_sales_qty' => 0, 'net_sales_amount' => 0,
            'cost_of_sales' => 0, 'profit_amount' => 0,
            'sales_count' => 0,
        ];

        foreach ($clients as $id => $client) {
            $data = $perClient[$id];

            // عميلٌ حركتُه كمّياتٌ بلا قيدٍ في الحسابات الثلاثة لا يُعرض — كما كان
            if ($data['sales_count'] == 0 && $data['returns_amount'] == 0 && $data['cost_of_sales'] == 0) {
                continue;
            }

            $data['net_sales_qty'] = $data['sales_qty'] - $data['returns_qty'];
            $data['net_sales_amount'] = $data['sales_amount'] - $data['returns_amount'];
            $data['profit_amount'] = $data['net_sales_amount'] - $data['cost_of_sales'];
            $data['profit_percentage'] = $data['net_sales_amount'] > 0 ? round(($data['profit_amount'] / $data['net_sales_amount']) * 100, 2) : 0;
            $data['profit_to_sales_ratio'] = $data['profit_percentage'];
            $data['profit_to_cost_ratio'] = $data['cost_of_sales'] > 0 ? round(($data['profit_amount'] / $data['cost_of_sales']) * 100, 2) : 0;

            $reportData->put($id, array_merge([
                'name' => $client->trade_name ?: $client->display_name,
                'status' => $client->status_client,
                'registration_stage' => $client->registrationStage,
            ], $data));

            foreach ($totals as $key => $val) {
                $totals[$key] += $data[$key];
            }
        }

        $reportData = $reportData->sortByDesc('profit_amount');

        $totals['profit_percentage'] = $totals['net_sales_amount'] > 0
            ? round(($totals['profit_amount'] / $totals['net_sales_amount']) * 100, 2)
            : 0;
        $totals['profit_to_sales_ratio'] = $totals['profit_percentage'];
        $totals['profit_to_cost_ratio'] = $totals['cost_of_sales'] > 0
            ? round(($totals['profit_amount'] / $totals['cost_of_sales']) * 100, 2)
            : 0;

        return compact('reportData', 'totals');
    }
}
