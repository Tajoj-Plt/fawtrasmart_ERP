<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Statuses;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Client\Services\CustomerLedgerService;

/**
 * أعمارُ ديون العملاء.
 *
 * ══════════ ما كان معطوباً ══════════
 *
 * **١. التقريرُ كان يُخفي أكثرَ عملائه.** كان يُطبَّق على `accounts.created_at`
 * شرطُ المدى: `whereDate('created_at','>=', بدايةِ النطاق)`. والنطاقُ
 * الافتراضي هو السنةُ المالية الجارية (٢٠٢٦)، وأكثرُ حسابات العملاء
 * فُتحت في ٢٠٢٥ — فكانت تسقط كلُّها من تقريرٍ وُضع أصلاً لإظهار الديون
 * القديمة. وتاريخُ **فتح الحساب** لا صلةَ له بعمر الدين أصلاً.
 *
 * **٢. الرصيدُ كلُّه في شريحةٍ واحدة.** كان يُحسب عمرٌ واحدٌ للعميل من آخر
 * قيدٍ مدينٍ في حسابه، ثمّ يُلقى الرصيدُ بأكمله في الشريحة الموافقة. فمن
 * عليه دينُ سنةٍ واشترى أمسِ ينتقل دينُه القديم كلُّه إلى شريحة «١-٣٠»،
 * وهو عكسُ ما يُقرأ التقريرُ من أجله.
 *
 * **٣. `getAgingDistribution()` كانت تُستدعى بثلاث وسائطَ وتقبل اثنتين** —
 * الوسيطُ الثالث (`aging_filter`) يُلقى بلا أثر.
 *
 * ══════════ ما صار ══════════
 *
 * التوزيعُ بأسلوب FIFO على البنود المفتوحة
 * ({@see CustomerLedgerService::openItems()}): تُطفأ أقدمُ المستحقّات
 * بالتحصيلات أوّلاً، ويُوزَّع الباقي كلُّ بندٍ بعمر **استحقاقه** هو —
 * وتاريخُ الاستحقاق = تاريخُ الحركة + `clients.credit_period`.
 *
 * ولأنّ مجموعَ البنود المفتوحة = مدينُ الحساب − دائنُه، فمجموعُ الشرائح
 * يساوي رصيدَ العميل في تقرير الأرصدة وفي كشف الحساب — بلا استثناء.
 */
class CustomerDebtAgingController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;
    use \Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods;

    public function __construct()
    {
        /* صلاحيةُ هذا التقرير وحدَه.

           كانت التركيبةُ الثلاثية `clients_view_all_reports|clients_view_own_reports|client_reports_view`
           مكرَّرةً حرفياً في التسعة، فمن فُتح له تقريرٌ انفتحت له
           التسعةُ جميعاً. واثنتان منها صلاحيتا **نطاق** لا عملية،
           وقد صار النطاقُ محوراً مستقلاً يقرؤه `App\Support\DataScope`. */
        $this->middleware('permission:clients.reports.debt_aging');
    }

    /**
     * عرض صفحة تقرير أعمار الديون
     */
    public function debtAgingGeneralLedger(Request $request)
    {
        if ($request->ajax()) {
            return $this->debtAgingGeneralLedgerAjax($request);
        }

        /* قائمتان لسؤالين مختلفين، وكانت واحدةً تخدمهما معاً:

           • «مسؤول المبيعات» يطابق `clients.employee_id` — معرّفُ **موظّف**،
             والقائمةُ من الأقسام المسموحة في إعدادات وحدات العملاء.
           • «تمت الإضافة بواسطة» يطابق `clients.user_id` — معرّفُ **مستخدم**.

           ودمجُهما في متغيّرٍ واحد كان يملأ الحقلين بفضاءِ معرّفاتٍ واحد،
           فأحدُهما يفلتر بمعرّفٍ لا يعني في جدوله ما يعنيه في الآخر. */
        $salesEmployees = $this->salesEmployeeOptions();
        $salesManagers = User::whereIn('role', ['employee', 'manager'])->get();
        $categories = CategoriesClient::select('id', 'name')->get();
        $statuses = Statuses::assignable()->get();
        $customerTargets = $this->customerTargetOptions();
        $registrationStages = $this->registrationStageOptions();
        $neighborhoods = \App\Models\Neighborhood::select('name', 'region_id')
            ->groupBy('name', 'region_id')
            ->orderBy('name')
            ->get();

        /* لم تعد قائمةُ العملاء تُطبع كاملةً (٣٨٥٢ خياراً) — المختارُ وحده
           يبقى، والبحثُ عبر Select2/AJAX. */
        $selectedClient = $request->filled('customer')
            ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->input('customer'))
            : null;

        $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $report = $this->reportRows($request, $periodContext);

        return view('client::customers.CustomerReport.debt_aging_general_enhanced', [
            // شجرةُ «تابع لـ» — المُنتقي التنظيمي الوحيد في الشاشة
            'treeNodes' => $this->affiliationTreeNodes(),
            'selectedClient' => $selectedClient,
            'salesEmployees' => $salesEmployees,
            'salesManagers' => $salesManagers,
            'categories' => $categories,
            'statuses' => $statuses,
            'registrationStages' => $registrationStages,
            'customerTargets' => $customerTargets,
            'neighborhoods' => $neighborhoods,
            'reportData' => $report['data'],
            'creditors' => $report['creditors'],
            'totals' => $report['totals'],
            'fromDate' => Carbon::parse($periodContext['start']),
            'toDate' => Carbon::parse($periodContext['end']),
            'chartData' => $report['chart_data'],
            'periodContext' => $periodContext,
        ]);
    }

    /**
     * إرجاع بيانات التقرير عبر AJAX
     */
    public function debtAgingGeneralLedgerAjax(Request $request)
    {
        try {
            $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
            $report = $this->reportRows($request, $periodContext);

            return response()->json([
                'success' => true,
                'data' => $report['data'],
                'creditors' => $report['creditors'],
                'totals' => $report['totals'],
                'chart_data' => $report['chart_data'],
                // النطاقُ المطبَّق فعلاً لا ما كتبه المستخدم
                'from_date' => $periodContext['start'],
                'to_date' => $periodContext['end'],
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في تقرير أعمار الديون: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحميل البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * صفوفُ التقرير.
     *
     * ══════════ لماذا تاريخُ القطع وحده يهمّ ══════════
     *
     * أعمارُ الديون سؤالٌ عن **لحظة**: «ما الذي عليه اليومَ، وكم عمرُ كلّ
     * جزءٍ منه؟». فيُقرأ الأستاذُ كلُّه حتى طرف المدى الأعلى (تاريخ القطع)،
     * وتُطفأ البنودُ بالتحصيلات FIFO على الأستاذ كاملاً.
     *
     * **بدايةُ المدى لا تقصّ شيئاً** (2026-09-16). كان مدىً «مختاراً» يُبقي
     * البنودَ المؤرَّخةَ داخله فقط، والنموذجُ يرسل التاريخين مع كلّ بحث —
     * فالسنةُ نفسُها كانت تعطي عند أوّل فتحٍ 1313 عميلاً تطابق أرصدةَ العملاء،
     * وبعد «عرض التقرير» 1218 عميلاً: 66 مديناً (11,786.57) يختفون، و17 صفّاً
     * شرائحُه لا تساوي «إجمالي المديونية». الأعمارُ صورةُ الدين كلِّه حتى تاريخ
     * القطع، فتطابق «أرصدة العملاء» على أيّ مدى. (قصرُ المدى باقٍ في أعمار
     * **الفواتير** حيث السؤالُ «فواتيرُ هذه الفترة».)
     *
     * وفلترُ الشريحة (`aging_filter`) وحدَه يقصّ العرض: يُبقي بنودَ تلك الشريحة،
     * والتسويةُ تُخفى حينها (`reconcile_available`).
     *
     * @param  array<string, mixed>  $period
     * @return array{data: array<int, array<string, mixed>>, totals: array<string, mixed>, chart_data: array<string, mixed>}
     */
    private function reportRows(Request $request, array $period): array
    {
        // حدُّ البيانات لا يتجاوز اليوم — كاللوحة ({@see CustomerLedgerService::dataCutoff()})
        $cutoff = CustomerLedgerService::dataCutoff($period['end']);
        $restrict = false; // بدايةُ المدى لا تقصّ الأعمار — انظر أعلاه
        /* «المتأخّر» بتعريف لوحة العملاء: ما تجاوز استحقاقُه N يوماً من
           إعداداتها — فرقمُ «المديونية المتأخرة» هنا = رقمُها هناك. كان كلَّ
           ما تجاوز استحقاقَه ولو بيوم، ومدّةُ الائتمان صفرٌ عند الجميع فصار
           الدينُ كلُّه «متأخّراً» (277,261 هنا مقابل 118,189 في اللوحة). */
        $overdueDays = \App\Helpers\ClientSettingsHelper::dashboardOverdueDays();
        $bucketColumn = [
            'today' => 'today',
            '1-30' => 'days1to30',
            '31-60' => 'days31to60',
            '61-90' => 'days61to90',
            '91-120' => 'days91to120',
            '120+' => 'daysOver120',
        ][(string) $request->input('aging_filter')] ?? null;

        $clientsQuery = Client::query()
            ->with([
                // حسابُ الذمّة لا المحفظة — كلوحتَي العملاء والمبيعات ({@see Client::ledgerAccount()})
                'ledgerAccount:id,client_id,balance,code',
                'branch:id,name',
                'categoriesClient:id,name',
                'employee:id,first_name,middle_name',
                'neighborhood:id,client_id,name,region_id',
                'neighborhood.Region:id,name',
                'status_client:id,name,color',
                'registrationStage:id,name,color',
                'customerTarget:id,name,color',
            ])
            ->whereHas('ledgerAccount');

        $this->applyClientFilters($clientsQuery, $request);

        $clients = $clientsQuery->get();

        $accountIds = [];
        $creditPeriods = [];

        foreach ($clients as $client) {
            $accountId = (int) ($client->ledgerAccount->id ?? 0);

            if ($accountId) {
                $accountIds[] = $accountId;
                $creditPeriods[$accountId] = $client->credit_period;
            }
        }

        $ledger = app(CustomerLedgerService::class);
        $openItems = $ledger->openItems($accountIds, $cutoff, $creditPeriods);
        /* الرصيدُ الافتتاحي **كما في القيد**: مجموعُ قيود `OPB` على الحساب حتى
           تاريخ القطع — لا `accounts.opening_balance` (صفرٌ على كلّ الحسابات
           تقريباً؛ الأرصدةُ المرحَّلة نزلت قيوداً). والمتبقّي منه من البنود
           المفتوحة نفسِها ({@see CustomerLedgerService::openingRemaining()}). */
        $openingTotals = $ledger->movementTotals($accountIds, '1000-01-01', $cutoff);

        $walletBalances = Account::wallets()
            ->whereIn('client_id', $clients->pluck('id'))
            ->pluck('balance', 'client_id');

        $rows = [];

        foreach ($clients as $client) {
            $accountId = (int) ($client->ledgerAccount->id ?? 0);

            if (! $accountId) {
                continue;
            }

            $affiliation = $client->resolveTreeNode();
            $ledgerRow = $openItems[$accountId] ?? ['items' => [], 'balance' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
            $balance = (float) $ledgerRow['balance'];

            if (! $request->boolean('show_zero') && abs($balance) < 0.005) {
                continue;
            }

            $buckets = CustomerLedgerService::emptyBuckets();
            $oldestDays = 0;

            $keptItems = 0;
            $overdueAmount = 0.0;

            foreach ($ledgerRow['items'] as $item) {
                if ($restrict && ($item['date'] ?? '') < $period['start']) {
                    continue;
                }

                $bucket = CustomerLedgerService::bucketFor((int) $item['days']);

                if ($bucketColumn && $bucket !== $bucketColumn) {
                    continue;
                }

                $buckets[$bucket] += (float) $item['amount'];
                $oldestDays = max($oldestDays, (int) $item['days']);
                $keptItems++;

                if ((int) $item['days'] > $overdueDays) {
                    $overdueAmount += (float) $item['amount'];
                }
            }

            // قُصّ العرضُ ولم يبقَ للعميل بندٌ فيه ← ليس من نتيجة هذا الفلتر
            if (($restrict || $bucketColumn) && $keptItems === 0) {
                continue;
            }

            /* رصيدٌ دائن (العميلُ دفع أكثرَ ممّا عليه) لا عمرَ له: البنودُ
               المفتوحةُ صفرٌ، فتبقى الشرائحُ صفراً ويُعرض الرصيدُ سالباً في
               عموده. ومجموعُ الشرائح حينئذٍ ≠ الرصيد عمداً — لأنّ الدائنَ
               ليس ديناً يُعمَّر. */
            $rows[] = [
                'client_id' => $client->id,
                'client_name' => $client->display_name,
                'client_code' => $client->code,
                'account_code' => $client->ledgerAccount->code ?? '—',
                'branch_name' => $client->branch->name ?? 'غير محدد',
                'branch_id' => $client->branch_id,
                'employee_name' => $client->employee
                    ? trim($client->employee->first_name.' '.$client->employee->middle_name)
                    : 'غير محدد',
                'employee_id' => $client->employee_id,
                'category_name' => $client->categoriesClient->name ?? 'غير محدد',
                'group_name' => $client->neighborhood->Region->name ?? 'غير محدد',
                'neighborhood_name' => $client->neighborhood->name ?? 'غير محدد',
                'client_status_name' => $client->status_client->name ?? 'غير محدد',
                'client_status_color' => $client->status_client->color ?? 'secondary',
                // «الوضع» = مرحلة التسجيل، حقلٌ مستقلٌّ عن الحالة
                // بلا وضعٍ تبقى القيمةُ فارغة: «الوضع» صار تحت «الحالة» في
                // العمود نفسِه، وشرطةٌ تحت كلّ حالةٍ ضجيجٌ لا معلومة
                'stage_name' => $client->registrationStage->name ?? null,
                'stage_color' => $client->registrationStage->color ?? '#6c757d',
                // «تابع لـ» — الجهةُ الفعلية في الشجرة ونوعُها
                'affiliation_name' => $affiliation?->name ?? ($client->branch->name ?? 'غير محدد'),
                'affiliation_type' => $affiliation?->displayTypeLabel() ?? '',
                'target_name' => $client->customerTarget->name ?? 'بلا هدف',
                'target_color' => $client->customerTarget->color ?? '#6c757d',

                // الرصيد الافتتاحي كما في القيد، وما لم يُسدَّد منه بعدُ (FIFO)
                'opening_entries' => round((float) ($openingTotals[$accountId]['opening'] ?? 0), 2),
                'opening_remaining' => CustomerLedgerService::openingRemaining($ledgerRow),

                'total_debit' => round((float) $ledgerRow['debit'], 2),
                'total_credit' => round((float) $ledgerRow['credit'], 2),
                'balance' => round($balance, 2),
                'balance_type' => $balance >= 0 ? 'مدين' : 'دائن',
                'open_items_count' => ($restrict || $bucketColumn) ? $keptItems : count($ledgerRow['items']),

                'today' => round($buckets['today'], 2),
                'days1to30' => round($buckets['days1to30'], 2),
                'days31to60' => round($buckets['days31to60'], 2),
                'days61to90' => round($buckets['days61to90'], 2),
                'days91to120' => round($buckets['days91to120'], 2),
                'daysOver120' => round($buckets['daysOver120'], 2),
                // «الأيام» صارت عمرَ **أقدم** بندٍ مفتوح لا عمرَ آخر فاتورة
                'days_late' => $oldestDays,
                // ما تجاوز استحقاقُه `overdue_days` — تعريفُ لوحة العملاء
                'overdue_amount' => round($overdueAmount, 2),

                'credit_limit' => (float) ($client->credit_limit ?? 0),
                'available_credit' => (float) ($client->credit_limit ?? 0) - $balance,
                'wallet_balance' => (float) ($walletBalances[$client->id] ?? 0),
                'total_sales' => round(max($balance, 0), 2),
                'total_payments' => round(max(-$balance, 0), 2),
            ];
        }

        /* فلترُ كروت الإحصائيات — يُبقي من له مبلغٌ في الشريحة المضغوطة. */
        if ($request->filled('aging_filter')) {
            $column = [
                'today' => 'today',
                '1-30' => 'days1to30',
                '31-60' => 'days31to60',
                '61-90' => 'days61to90',
                '91-120' => 'days91to120',
                '120+' => 'daysOver120',
            ][$request->input('aging_filter')] ?? null;

            if ($column) {
                $rows = array_values(array_filter($rows, fn ($row) => abs($row[$column]) > 0.005));
            }
        }

        /* «عمر الدين»: من له مبلغٌ مفتوحٌ أقدمُ من الحدّ — و`days_late` هنا عمرُ
           أقدم بندٍ مفتوح فعلاً (FIFO). الدائنون لا دينَ لهم يُعمَّر فيخرجون. */
        $debtAge = \App\Support\DebtAge::fromRequest($request);
        if ($debtAge !== null) {
            $rows = array_values(array_filter($rows, fn ($row) => \App\Support\DebtAge::exceeds($row['days_late'], $debtAge)));
        }

        /* الأرصدةُ الدائنة **خارج جدول الأعمار** (2026-09-16، بطلب المستخدم).
           عميلٌ دفع أكثرَ ممّا عليه لا دينَ له يُعمَّر: كان يظهر في الجدول
           بشرائحَ صفرٍ و«إجمالي مديونية» سالب، ويُنقص إجماليَّ الجدول. صار
           قائمةً مستقلّة (`creditors`) بمبلغٍ موجبٍ لصالح العميل. */
        $creditors = array_values(array_filter($rows, fn ($row) => $row['balance'] < -0.005));
        $rows = array_values(array_filter($rows, fn ($row) => $row['balance'] >= -0.005));
        usort($creditors, fn ($a, $b) => $a['balance'] <=> $b['balance']);
        $creditTotal = round(abs(array_sum(array_column($creditors, 'balance'))), 2);

        usort($rows, fn ($a, $b) => $b['days_late'] <=> $a['days_late']);

        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);

        $totals = [
            'total_clients' => count($rows),
            // الافتتاحيُّ على صفوف الجدول (المدينون) — الدائنون لا دينَ لهم يُسدَّد
            'opening_entries' => $sum('opening_entries'),
            'opening_remaining' => $sum('opening_remaining'),
            'opening_clients' => count(array_filter($rows, fn ($row) => abs($row['opening_entries']) > 0.005)),
            'opening_open_clients' => count(array_filter($rows, fn ($row) => $row['opening_remaining'] > 0.005)),
            'total_debit' => $sum('total_debit'),
            'total_credit' => $sum('total_credit'),
            // الصافي (المديونية − الدائنة) — لشريط التسوية مع «أرصدة العملاء»
            'total_balance' => round($sum('balance') - $creditTotal, 2),
            // المديونيةُ وحدَها — أرصدةُ صفوف الجدول
            'debt_balance' => $sum('balance'),
            'credit_clients' => count($creditors),
            'today' => $sum('today'),
            'days1to30' => $sum('days1to30'),
            'days31to60' => $sum('days31to60'),
            'days61to90' => $sum('days61to90'),
            'days91to120' => $sum('days91to120'),
            'daysOver120' => $sum('daysOver120'),
            'total_sales' => $sum('total_sales'),
            'total_payments' => $sum('total_payments'),
            'overdue_amount' => $sum('overdue_amount'),
            'overdue_clients' => count(array_filter($rows, fn ($row) => $row['overdue_amount'] > 0.005)),
            'overdue_days' => $overdueDays,
        ];

        /* مجموعُ الشرائح، ومعه ما يفسّر فرقَه عن إجمالي الأرصدة.
           الفرقُ كلُّه أرصدةٌ **دائنة**: عملاءُ دفعوا أكثرَ ممّا عليهم، فلا
           بندَ مفتوحٌ لهم يُعمَّر بينما رصيدُهم سالبٌ يُنقص الإجمالي. فتُعلَن
           القيمةُ صراحةً بدل أن يبحث القارئُ عن سببِ فرقٍ لا يُرى:

               مجموعُ الشرائح − الأرصدةُ الدائنة = إجمالي الأرصدة */
        $totals['aging_total'] = round(
            $totals['today'] + $totals['days1to30'] + $totals['days31to60']
            + $totals['days61to90'] + $totals['days91to120'] + $totals['daysOver120'],
            2
        );

        $totals['credit_balances'] = $creditTotal;
        $totals['reconciles'] = abs($totals['aging_total'] - $totals['credit_balances'] - $totals['total_balance']) < 0.05;
        // الشرائحُ مقصوصةٌ بمدىً أو بشريحة ← لا تُقارَن بالأرصدة الكاملة
        $totals['reconcile_available'] = ! $restrict && ! $bucketColumn && $debtAge === null;
        $totals['debt_age'] = $debtAge;

        $top = array_slice($rows, 0, 10);

        return [
            'data' => $rows,
            'creditors' => $creditors,
            'totals' => $totals,
            'chart_data' => [
                'labels' => array_column($top, 'client_name'),
                'balances' => array_column($top, 'balance'),
                'aging_labels' => ['غير مستحق', '1-30 يوم', '31-60 يوم', '61-90 يوم', '91-120 يوم', '+120 يوم'],
                'aging_values' => [
                    $totals['today'],
                    $totals['days1to30'],
                    $totals['days31to60'],
                    $totals['days61to90'],
                    $totals['days91to120'],
                    $totals['daysOver120'],
                ],
            ],
        ];
    }
}
