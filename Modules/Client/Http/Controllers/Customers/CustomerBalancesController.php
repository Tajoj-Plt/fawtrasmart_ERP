<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Region_groub;
use App\Models\Statuses;
use App\Models\User;
use App\Support\DataScope;
use App\Support\DebtAge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Client\Services\CustomerLedgerService;

/**
 * أرصدةُ العملاء ومديونيّتُهم — شاشةٌ واحدةٌ تجيب السؤالين.
 *
 * ══════════ ما تغيّر ══════════
 *
 * كان الرصيدُ يُقرأ من العمود `accounts.balance` كما هو. وذلك العمودُ
 * رصيدُ **اليوم**: لا يعرف تاريخَ قطعٍ ولا فترةً محاسبية، فحقلا «من/إلى»
 * فوق التقرير لم يكونا يغيّران رقماً واحداً في العمود الأخير. بل كان
 * فلترُ التاريخ يُطبَّق على `accounts.created_at` — أي على تاريخ **فتح
 * الحساب** — فاختيارُ السنة المالية ٢٠٢٦ كان يُسقط من التقرير كلَّ عميلٍ
 * فُتح حسابُه في ٢٠٢٥، وهم أكثرُ العملاء.
 *
 * صار الرصيدُ يُحسب من الأستاذ حتى تاريخ القطع عبر
 * {@see CustomerLedgerService}، ومعه الرصيدُ الافتتاحي وإجمالي المدين
 * والدائن — فالسطرُ الواحد يقرأ:
 *
 *     افتتاحي + (فواتير) − (تحصيلات + مرتجعات) = ختامي
 *
 * وهو بعينه ما يعرضه كشفُ الحساب لنفس العميل ونفس المدى.
 */
class CustomerBalancesController extends Controller
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
        $this->middleware('permission:clients.reports.balances');
    }

    /**
     * عرض صفحة تقرير أرصدة العملاء
     */
    public function customerBalances(Request $request)
    {
        $employees = User::whereIn('role', ['employee', 'manager'])->get();
        /* قائمتان لسؤالين مختلفين، وكانت واحدةً تخدمهما معاً:

           • «مسؤول المبيعات» يطابق `clients.employee_id` — معرّفُ **موظّف**،
             والقائمةُ من الأقسام المسموحة في إعدادات وحدات العملاء.
           • «تمت الإضافة بواسطة» يطابق `clients.user_id` — معرّفُ **مستخدم**.

           ودمجُهما في متغيّرٍ واحد كان يملأ الحقلين بفضاءِ معرّفاتٍ واحد،
           فأحدُهما يفلتر بمعرّفٍ لا يعني في جدوله ما يعنيه في الآخر. */
        $salesEmployees = $this->salesEmployeeOptions();
        $salesManagers = User::whereIn('role', ['employee', 'manager'])->get();
        $statuses = Statuses::assignable()->get(); // حالات العميل القابلة للاختيار فقط — نشط/موقوف، بلا مراحل التسجيل ولا حالات قديمة
        $categories = CategoriesClient::all();
        $customerTargets = $this->customerTargetOptions();
        $registrationStages = $this->registrationStageOptions();
        $neighborhoods = \App\Models\Neighborhood::select('name', 'region_id')
            ->groupBy('name', 'region_id')
            ->orderBy('name')
            ->get();
        /* قائمةُ العملاء لم تعد تُطبع كاملةً في الصفحة (٣٨٥٢ خياراً) —
           يبقى منها المختارُ وحدَه، والبحثُ عبر Select2/AJAX. */
        $selectedClient = $request->filled('customer')
            ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->input('customer'))
            : null;

        // تحديد نوع العرض (ملخص أو تفاصيل)
        $isSummary = $request->input('view_type', 'summary') === 'summary';

        /* ══════════ الفترة الزمنية ══════════

           الافتراضُ كان **آخر ثلاثين يوماً** — رقمٌ تقويميٌّ لا صلةَ له
           بالسنة المالية ولا بالفترة المحاسبية. صار الافتراضُ الفترةَ
           المحاسبية المختارة، وإلّا السنةَ المالية الجارية — وهو المدى
           الذي يُعلنه شريطُ الحالة فوق التقرير. */
        $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');

        $report = $this->reportRows($request, $periodContext);

        return view('client::customers.CustomerReport.customer_blances', [
            'employees' => $employees,
            'selectedClient' => $selectedClient,
            // شجرةُ «تابع لـ» — المُنتقي التنظيمي الوحيد في الشاشة
            'treeNodes' => $this->affiliationTreeNodes(),
            'salesEmployees' => $salesEmployees,
            'salesManagers' => $salesManagers,
            'statuses' => $statuses,
            'registrationStages' => $registrationStages,
            'categories' => $categories,
            'customerTargets' => $customerTargets,
            'neighborhoods' => $neighborhoods,
            'isSummary' => $isSummary,
            'reportData' => $report['data'],
            'totals' => $report['totals'],
            'fromDate' => Carbon::parse($periodContext['start'])->startOfDay(),
            'toDate' => Carbon::parse($periodContext['end'])->endOfDay(),
            'periodContext' => $periodContext,
        ]);
    }

    /**
     * إرجاع بيانات تقرير أرصدة العملاء عبر AJAX
     */
    public function customerBalancesAjax(Request $request)
    {
        try {
            $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
            $report = $this->reportRows($request, $periodContext);

            $top = array_slice($report['data'], 0, 10);

            return response()->json([
                'success' => true,
                'data' => $report['data'],
                'totals' => $report['totals'],
                'chart_data' => [
                    'labels' => array_column($top, 'client_name'),
                    'balances' => array_column($top, 'balance'),
                ],
                // النطاقُ المطبَّق فعلاً لا ما كتبه المستخدم
                'from_date' => $periodContext['start'],
                'to_date' => $periodContext['end'],
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في تقرير أرصدة العملاء: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحميل البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * صفوفُ التقرير — مصدرٌ واحدٌ للشاشة و للـAJAX معاً.
     *
     * كانت هناك نسختان من هذه الدالّة (`…ForView` و`…Ajax`) تختلفان في
     * تفاصيلَ صامتة: إحداهما تفلتر بالتاريخ والأخرى لا، وإحداهما تحسب
     * الأيام من آخر قيدٍ مدينٍ والأخرى من آخر قيدٍ أيّاً كان. فالشاشةُ
     * وتصديرُها كانا يعطيان رقمين مختلفين للفلاتر نفسها.
     *
     * @param  array<string, mixed>  $period
     * @return array{data: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    private function reportRows(Request $request, array $period): array
    {
        $from = $period['start'];
        // حدُّ البيانات لا يتجاوز اليوم — كاللوحة ({@see CustomerLedgerService::dataCutoff()})
        $to = CustomerLedgerService::dataCutoff($period['end']);
        /* `$to` يقصّ الحركاتَ؛ و`$cut` يقيس «الأيام» وحدَها، فلا يتجاوز
           اليومَ ولو انتهى المدى في المستقبل — والمدى الافتراضي ينتهي
           بنهاية السنة المالية. {@see CustomerLedgerService::agingDate()} */
        $cut = CustomerLedgerService::agingDate($to);

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
                'clientRelations' => function ($q) {
                    $q->select('id', 'client_id', 'deposit_count', 'created_at')
                        ->whereNotNull('deposit_count')
                        ->orderByDesc('created_at')
                        ->limit(1);
                },
            ])
            ->whereHas('ledgerAccount');

        $this->applyClientFilters($clientsQuery, $request);

        $clients = $clientsQuery->get();

        $accountIds = $clients->pluck('ledgerAccount.id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $ledger = app(CustomerLedgerService::class);
        /* ما قبل بداية المدى، مفصَّلاً بنوع المرجع — باستعلامٍ واحد.

           كان عمودٌ واحدٌ اسمُه «الرصيد الافتتاحي» يحمل كلَّ ما قبل المدى،
           فظهر لعميلٍ لم يُنزَّل له رصيدٌ افتتاحيٌّ قطّ رقمٌ هو في الحقيقة
           فاتورةٌ سابقة (765 عميلاً في «آخر 30 يوماً»). صار عمودين:
             • «رصيد افتتاحي» = قيودُ الرصيد الافتتاحي (`OPB`) وحدَها،
             • «حركات سابقة» = الفواتيرُ والسنداتُ وغيرُها قبل المدى.
           ومجموعُهما هو الرصيدُ السابقُ نفسُه، فالمعادلةُ لا تتغيّر. */
        $before = $ledger->movementTotals($accountIds, '1000-01-01', Carbon::parse($from)->subDay()->format('Y-m-d'));
        $closing = $ledger->positionAt($accountIds, $to);
        $movements = $ledger->movementTotals($accountIds, $from, $to);

        /* «عمر الدين»: عمرُ أقدم مبلغٍ مفتوح (FIFO من الأستاذ، بعد مدّة
           الائتمان) — لا «الأيام» المعروضة هنا، وهي منذ آخر فاتورة. فعميلٌ
           اشترى أمس وعليه دينٌ من ستّة أشهر كان «يوماً واحداً». البنودُ
           المفتوحةُ نفسُها التي يعمّر بها تقريرُ أعمار الديون. */
        $debtAge = DebtAge::fromRequest($request);
        /* البنودُ المفتوحة تُبنى دائماً لا مع «عمر الدين» وحده: منها يُعرف
           «لم يُسدَّد من الافتتاحي» — ما بقي مفتوحاً من قيود الرصيد
           الافتتاحي بعد أن أطفأت التحصيلاتُ أقدمَ الدين أوّلاً. */
        $creditPeriods = [];
        foreach ($clients as $client) {
            if ($client->ledgerAccount) {
                $creditPeriods[(int) $client->ledgerAccount->id] = $client->credit_period;
            }
        }
        $openItems = $ledger->openItems($accountIds, $to, $creditPeriods);

        // أرصدةُ المحافظ — أموالُ العميل لدينا، تُعرض ولا تُخلط بالدين
        $walletBalances = Account::wallets()
            ->whereIn('client_id', $clients->pluck('id'))
            ->pluck('balance', 'client_id');

        $rows = [];

        foreach ($clients as $client) {
            $accountId = (int) ($client->ledgerAccount->id ?? 0);

            if (! $accountId) {
                continue;
            }

            $prior = $before[$accountId] ?? null;
            $position = $closing[$accountId] ?? ['balance' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'last_movement' => null, 'last_charge' => null];
            $range = $movements[$accountId] ?? [
                'debit' => 0.0, 'credit' => 0.0, 'invoices' => 0.0,
                'receipts' => 0.0, 'returns' => 0.0, 'opening' => 0.0, 'other' => 0.0,
                'opening_debit' => 0.0, 'opening_credit' => 0.0,
            ];

            /* قيودُ الرصيد الافتتاحي تُقرأ «رصيداً افتتاحياً» **أينما وقع
               تاريخُها** حتى تاريخ القطع — قبل المدى أو داخله.

               كانت قيودُ الترحيل مؤرَّخةً 2025/12/04، فمدىً يبدأ 2025/12/01
               يُدخلها في «إجمالي المدين» حركةً عاديةً ويُظهر «رصيد افتتاحي
               = 0». فتُستبعد من مدين المدى ودائنه وتُضاف إلى عمودها، والمعادلةُ
               (افتتاحي + سابقة + مدين − دائن = ختامي) لا تتغيّر. */
            $priorNet = $prior ? (float) $prior['debit'] - (float) $prior['credit'] : 0.0;
            $priorOpening = $prior ? (float) $prior['opening'] : 0.0;
            $openingEntries = round($priorOpening + (float) $range['opening_debit'] - (float) $range['opening_credit'], 2);
            $priorMovements = round($priorNet - $priorOpening, 2);
            $rangeDebit = round((float) $range['debit'] - (float) $range['opening_debit'], 2);
            $rangeCredit = round((float) $range['credit'] - (float) $range['opening_credit'], 2);

            $balance = (float) $position['balance'];

            /* «نوع الرصيد»: مدين (عليه لنا) / دائن (له علينا) / صفري — كي
               تُقرأ الأرصدةُ الدائنة وحدَها لا مخلوطةً بالمديونية. */
            $side = (string) $request->input('balance_side', '');
            if (($side === 'debit' && $balance <= 0.005)
                || ($side === 'credit' && $balance >= -0.005)
                || ($side === 'zero' && abs($balance) >= 0.005)) {
                continue;
            }

            // الأرصدةُ الصفرية تُخفى ما لم تُطلب — والصفريّةُ تعني ختاميّاً
            // صفراً بلا حركةٍ في المدى، لا مجرّدَ ختاميٍّ صفر
            if (! $request->boolean('show_zero')
                && abs($balance) < 0.005
                && abs($range['debit']) < 0.005
                && abs($range['credit']) < 0.005) {
                continue;
            }

            $oldestDebtDays = null;
            $olderAmount = null;
            if ($debtAge !== null) {
                $items = $openItems[$accountId]['items'] ?? [];
                $oldestDebtDays = $items ? (int) max(array_column($items, 'days')) : 0;
                $olderAmount = round(array_sum(array_map(
                    fn ($item) => DebtAge::exceeds((int) $item['days'], $debtAge) ? (float) $item['amount'] : 0.0,
                    $items
                )), 2);

                // لا مبلغَ مفتوحاً أقدمُ من الحدّ ← ليس من نتيجة الفلتر
                if ($olderAmount <= 0.005) {
                    continue;
                }
            }

            $chargeDate = $position['last_charge'] ?? null;
            $daysLate = $chargeDate ? (int) max(0, Carbon::parse($chargeDate)->startOfDay()->diffInDays($cut, false)) : 0;

            $lastDeposit = $client->clientRelations->first()->deposit_count ?? 0;
            $affiliation = $client->resolveTreeNode();

            $rows[] = [
                'client_id' => $client->id,
                'client_name' => $client->display_name,
                'client_code' => $client->code,
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
                'stage_name' => $client->registrationStage->name ?? '—',
                'stage_color' => $client->registrationStage->color ?? '#6c757d',
                // «تابع لـ» — الجهةُ الفعلية في الشجرة ونوعُها، لا الفرعُ المشتقّ
                'affiliation_name' => $affiliation?->name ?? ($client->branch->name ?? 'غير محدد'),
                'affiliation_type' => $affiliation?->displayTypeLabel() ?? '',
                // هدفُ العميل — الاسمُ لا المعرّف
                'target_name' => $client->customerTarget->name ?? 'بلا هدف',
                'target_color' => $client->customerTarget->color ?? '#6c757d',

                // ── المعادلةُ المحاسبية للمدى ──────────────────────
                'opening_balance' => round($openingEntries + $priorMovements, 2), // الافتتاحيُّ + السابقة
                'opening_entries' => $openingEntries,   // قيودُ الرصيد الافتتاحي كلُّها حتى القطع
                'opening_remaining' => CustomerLedgerService::openingRemaining($openItems[$accountId] ?? null), // ما لم يُسدَّد منها (FIFO)
                'prior_movements' => $priorMovements,   // فواتيرُ وسنداتٌ قبل المدى (بلا الافتتاحي)
                'total_debit' => $rangeDebit,           // مدينُ المدى بلا قيود الافتتاحي
                'total_credit' => $rangeCredit,         // دائنُ المدى بلا قيود الافتتاحي
                'balance' => round($balance, 2),
                'balance_type' => $balance >= 0 ? 'مدين' : 'دائن',

                // ── تفصيلُ المديونية ──────────────────────────────
                'invoices_total' => round((float) $range['invoices'], 2),
                'paid_total' => round((float) $range['receipts'], 2),
                'returns_total' => round((float) $range['returns'], 2),
                'opening_movement' => round((float) $range['opening'], 2),
                'other_total' => round((float) $range['other'], 2),
                'last_movement' => $position['last_movement'],
                'last_charge' => $chargeDate,

                // ── معلوماتٌ جانبية ───────────────────────────────
                'credit_limit' => (float) ($client->credit_limit ?? 0),
                'available_credit' => (float) ($client->credit_limit ?? 0) - $balance,
                'wallet_balance' => (float) ($walletBalances[$client->id] ?? 0),
                'days_late' => $daysLate,
                // مع فلتر «عمر الدين» وحده: عمرُ أقدم مبلغٍ مفتوح، وما تجاوز الحدَّ منه
                'oldest_debt_days' => $oldestDebtDays,
                'older_than_amount' => $olderAmount,
                'last_deposit' => (int) $lastDeposit,

                // الاسمانِ القديمان — العرضُ ما زال يقرأهما
                'total_sales' => round(max($balance, 0), 2),
                'total_payments' => round(max(-$balance, 0), 2),
            ];
        }

        // مع «عمر الدين» يُرتَّب بعمر أقدم دين — هو ما سُئل عنه
        $debtAge !== null
            ? usort($rows, fn ($a, $b) => $b['oldest_debt_days'] <=> $a['oldest_debt_days'])
            : usort($rows, fn ($a, $b) => $b['days_late'] <=> $a['days_late']);

        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);

        return [
            'data' => $rows,
            'totals' => [
                'total_clients' => count($rows),
                'debt_age' => $debtAge,
                'older_than_amount' => $debtAge !== null ? $sum('older_than_amount') : null,
                'opening_balance' => $sum('opening_balance'),
                'opening_entries' => $sum('opening_entries'),
                'opening_remaining' => $sum('opening_remaining'),
                'opening_clients' => count(array_filter($rows, fn ($row) => abs($row['opening_entries']) > 0.005)),
                'opening_open_clients' => count(array_filter($rows, fn ($row) => $row['opening_remaining'] > 0.005)),
                'prior_movements' => $sum('prior_movements'),
                /* المديونيةُ والأرصدةُ الدائنة **منفصلتان**: الأولى أرصدةُ المدينين
                   (= «إجمالي المديونية» في لوحتَي العملاء والمبيعات)، والثانيةُ
                   مبالغُ لصالح العملاء موجبةً. و`total_balance` صافيهما. */
                'debt_total' => round(array_sum(array_filter(array_column($rows, 'balance'), fn ($b) => $b > 0.005)), 2),
                'debt_clients' => count(array_filter(array_column($rows, 'balance'), fn ($b) => $b > 0.005)),
                'credit_total' => round(abs(array_sum(array_filter(array_column($rows, 'balance'), fn ($b) => $b < -0.005))), 2),
                'credit_clients' => count(array_filter(array_column($rows, 'balance'), fn ($b) => $b < -0.005)),
                'total_debit' => $sum('total_debit'),
                'total_credit' => $sum('total_credit'),
                'total_balance' => $sum('balance'),
                'invoices_total' => $sum('invoices_total'),
                'paid_total' => $sum('paid_total'),
                'returns_total' => $sum('returns_total'),
                // الاسمانِ القديمان — بطاقاتُ المؤشرات تقرأهما
                'total_sales' => $sum('total_sales'),
                'total_payments' => $sum('total_payments'),
            ],
        ];
    }

    /**
     * جلب بيانات الفروع والمجموعات الإقليمية
     */
    public function getBranchesRegionData(Request $request)
    {
        try {
            $branches = DataScope::branchOptions(Branch::orderBy('name'), ['id', 'name', 'is_main']);

            $regions = Region_groub::select('id', 'name', 'territory_office_id')
                ->orderBy('name')
                ->get()
                ->map(fn ($region) => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'branch_id' => (int) $region->territory_office_id,
                ]);

            return response()->json([
                'success' => true,
                'branches' => $branches,
                'regions' => $regions,
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في جلب بيانات الفروع والمجموعات: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحميل البيانات',
            ], 500);
        }
    }
}
