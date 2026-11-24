<?php

namespace Modules\Account\Http\Controllers;

use App\Constants\permissions\AccountPermissions;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ChequeBook;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\PayableCheque; // Still imported in case other methods rely on it
use App\Models\Receipt;
use App\Models\ReceivedCheque;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use Modules\Account\Http\Controllers\Concerns\BuildsAccountingDashboard;

class AccountController extends Controller
{
    use AccountingOperations;
    use BuildsAccountingDashboard;

    /**
     * بوابة الدخول: أي صلاحية واحدة من صلاحيات الحسابات والمالية
     * (`account.*`) تكفي لرؤية الداشبورد — ليست صلاحيةً بعينها.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()?->hasAnyPermissionWithPrefix(AccountPermissions::PREFIX)) {
                abort(403, 'لا تملك أي صلاحية على وحدة الحسابات والمالية.');
            }

            return $next($request);
        });
    }

    /**
     * لوحة الحسابات.
     *
     * أُعيد بناء أرقامها كلّها على القيود بدل أرصدة الجذور — تفصيل الأسباب
     * في `BuildsAccountingDashboard`.
     */
    public function index()
    {
        try {
        
            $dashboardCreatorIds = $this->dashboardCreatorIds();
            $fullFinancialView = $dashboardCreatorIds === null;

            // نطاق مستندات اللوحة الموحَّد — بدل scopeToCreator العام الذي
            // لا يستثني غير الموظف
            $scopeDashboardCreator = function ($query, string $column = 'created_by') use ($dashboardCreatorIds) {
                if ($dashboardCreatorIds !== null) {
                    $query->whereIn($column, $dashboardCreatorIds ?: [0]);
                }

                return $query;
            };

            // ═════════ الحسابات والقيود ═════════
            $totalAccounts = $fullFinancialView ? Account::count() : 0;
            $activeAccounts = $fullFinancialView ? Account::where('is_active', 1)->count() : 0;

            // نطاق القيود: قيوده هو أو قيود من يديرهم — وغيرُ الموظف بلا قيد
            $entriesScope = fn () => $scopeDashboardCreator(JournalEntry::query(), 'created_by_employee');
            $totalEntries = $entriesScope()->count();
            $pendingEntries = $entriesScope()->where('status', 0)->count();
            $approvedEntries = $entriesScope()->where('status', 1)->count();

            // ═════════ الأداء المالي ═════════
            /*
                من القيود لا من أرصدة الجذور: أرصدة المصروفات مخزَّنة بالسالب،
                فكان `الإيرادات − المصروفات` يقلب الإنفاق ربحاً. والإيرادات
                كانت تُقرأ من الجذر وحده فتخرج صفراً وإن تحرّكت فروعُها.
            */
            $revenueSides = $this->movementSides(Account::REVENUES);
            $expenseSides = $this->movementSides(Account::EXPENSES);

            $totalReceipts = $revenueSides['credit'] - $revenueSides['debit'];
            $totalExpenses = $expenseSides['debit'] - $expenseSides['credit'];
            $netIncome = $totalReceipts - $totalExpenses;

            /*
                الأرقامُ أعلاه على الفترة المحاسبية بعلاقتها. وهذه نافذةٌ
                تقويمية إلى جانبها للمقارنة — فمعرفةُ ما جرى في الفترة لا
                تُغني عن رؤية الاتّجاه في الشهر الجاري.
            */
            $accountingPeriod = $this->dashboardPeriod();
            $monthStart = now()->copy()->startOfMonth();
            $monthReceipts = $this->movementOf(Account::REVENUES, 'credit', $monthStart, now());
            $monthExpenses = $this->movementOf(Account::EXPENSES, 'debit', $monthStart, now());
            $monthNet = $monthReceipts - $monthExpenses;

            // ═════════ الخزائن ═════════
            $treasuryAccounts = $this->treasuryAccounts();
            $totalTreasuryBalance = (float) $treasuryAccounts->sum('balance');

            $treasuryStats = (object) [
                'total' => $treasuryAccounts->count(),
                'active' => $treasuryAccounts->where('is_active', 1)->count(),
                'cash' => $treasuryAccounts->where('is_cash', true)->count(),
                'banks' => $treasuryAccounts->where('is_cash', false)->count(),
                'cash_balance' => (float) $treasuryAccounts->where('is_cash', true)->sum('balance'),
                'bank_balance' => (float) $treasuryAccounts->where('is_cash', false)->sum('balance'),
            ];

            // ═════════ سلامة الدفاتر (أرقام منشأة — لغير المقيَّد وحده) ═════════
            if ($fullFinancialView) {
                $equation = $this->balanceEquation();
                $unbalanced = $this->unbalancedEntries();
                $periodStatus = $this->periodStatus();
                $ledger = $this->receivablesPayables();
            } else {
                $equation = ['balanced' => true, 'difference' => 0.0, 'assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0, 'revenues' => 0.0, 'expenses' => 0.0, 'left' => 0.0, 'right' => 0.0];
                $unbalanced = ['count' => 0, 'rows' => collect()];
                $periodStatus = ['enforced' => false, 'lock_before' => null, 'orphans' => ['total' => 0, 'tables' => []]];
                $ledger = ['receivable' => null, 'payable' => null, 'receivable_account' => null, 'payable_account' => null];
            }

            // ═════════ الشيكات ═════════
            /*
                `SUM(CASE …)` على جدولٍ فارغ يعيد `null` لا صفراً، وكانت
                القيم تُطبع كما هي — فتظهر خانة «مستحقة» فارغةً بدل «0».

                وبنطاق المستخدم: شيكاتُه هو ومن يديرهم (`created_by`) —
                كانت الأربع بطاقاتٍ تَعُدّ شيكات المنشأة كلِّها لكل من فتح
                اللوحة، بينما القيود والسندات بجانبها منطاقة.
            */
            $chequeAgg = function ($model) use ($scopeDashboardCreator) {
                $scoped = fn () => $scopeDashboardCreator($model::query());

                return (object) [
                    'total' => (int) $scoped()->count(),
                    'due_soon' => (int) $scoped()->whereDate('due_date', '>=', now()->toDateString())
                        ->whereDate('due_date', '<=', now()->copy()->addDays(30)->toDateString())->count(),
                    'overdue' => (int) $scoped()->whereDate('due_date', '<', now()->toDateString())->count(),
                    'amount' => (float) ($scoped()->sum('amount') ?? 0),
                ];
            };

            $payableChequeStats = $chequeAgg(PayableCheque::class);
            $receivedChequeStats = $chequeAgg(ReceivedCheque::class);

            // دفاتر الشيكات بنيوية — لغير المقيَّد وحده
            $chequeBookStats = $fullFinancialView ? (object) [
                'total' => (int) ChequeBook::count(),
                'active' => (int) ChequeBook::where('status', 'active')->count(),
                'inactive' => (int) ChequeBook::where('status', 'inactive')->count(),
                'finished' => (int) ChequeBook::where('status', 'finished')->count(),
            ] : (object) ['total' => 0, 'active' => 0, 'inactive' => 0, 'finished' => 0];

            $totalPayableCheques = $payableChequeStats->total;
            $totalReceivedCheques = $receivedChequeStats->total;
            $totalChequeBooks = $chequeBookStats->total;
            $totalPayableChequeAmount = $payableChequeStats->amount;
            $totalReceivedChequeAmount = $receivedChequeStats->amount;

            // ═════════ مراكز التكلفة والأصول (أرقام منشأة) ═════════
            $totalCostCenters = $fullFinancialView ? CostCenter::count() : 0;
            $activeCostCenters = $fullFinancialView ? CostCenter::where('is_active', 1)->count() : 0;

            // ═════════ التوزيع والاتجاه ═════════
            $accountTypes = $fullFinancialView ? $this->accountTypeDistribution() : collect();
            $trend = $this->monthlyTrend(12);
            $months = $trend['months'];
            $monthlyReceipts = $trend['receipts'];
            $monthlyExpenses = $trend['expenses'];
            $monthlyNet = $trend['net'];

            $topReceipts = $this->topAccountsByMovement(Account::REVENUES, 'credit');
            $topExpenses = $this->topAccountsByMovement(Account::EXPENSES, 'debit');

            // ═════════ لوحات إضافية ═════════
            // حركة الخزائن تبقى: خزائنه هو أصلاً (accessibleTreasuryIds)
            $flowAll = $this->treasuryFlow();
            $flowMonth = $this->treasuryFlow($monthStart, now());

            if ($fullFinancialView) {
                $vat = $this->vatPosition();
                $assets = $this->fixedAssets();
                $costCenters = $this->costCenterLoad();
                $largestAccounts = $this->largestAccounts();
            } else {
                $vat = ['net' => null, 'collected' => null, 'paid' => null, 'collected_account' => null, 'paid_account' => null];
                $assets = ['count' => 0, 'value' => 0.0, 'sold' => 0];
                $costCenters = collect();
                $largestAccounts = collect();
            }

            // ═════════ أحدث المستندات ═════════
            $recentEntries = $entriesScope()
                ->withSum('details as total_debit', 'debit')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(6)
                ->get();

            /*
                من جداول السندات لا من القيود: كانت تُبنى من `JournalEntry`
                وتجمع `debit` على حسابات المصروفات — فتخرج بمبلغ **صفر** كلّما
                رُحِّل السند على الجانب الآخر، وهو ما كان يقع فعلاً.
            */
            $recentReceipts = $this->recentVouchers(Receipt::class);
            $recentExpenses = $this->recentVouchers(Expense::class);

            return view('account::index', compact('fullFinancialView', 'accountingPeriod',
                'totalAccounts', 'activeAccounts',
                'totalEntries', 'pendingEntries', 'approvedEntries',
                'totalReceipts', 'totalExpenses', 'netIncome',
                'revenueSides', 'expenseSides',
                'monthReceipts', 'monthExpenses', 'monthNet',
                'totalTreasuryBalance', 'treasuryStats', 'treasuryAccounts',
                'equation', 'unbalanced', 'periodStatus', 'ledger',
                'vat', 'flowAll', 'flowMonth', 'assets', 'costCenters', 'largestAccounts',
                'accountTypes', 'recentEntries', 'topExpenses', 'topReceipts',
                'monthlyReceipts', 'monthlyExpenses', 'monthlyNet', 'months',
                'totalPayableCheques', 'totalReceivedCheques', 'totalChequeBooks',
                'totalPayableChequeAmount', 'totalReceivedChequeAmount',
                'totalCostCenters', 'activeCostCenters',
                'recentReceipts', 'recentExpenses',
                'chequeBookStats', 'payableChequeStats', 'receivedChequeStats'
            ));
        } catch (\Exception $e) {
            OperationLog::failure($e, 'Account.Account', 'index');

            return redirect()->back()->with('error', 'حدث خطأ أثناء تحميل البيانات: '.$e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('account::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('account::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('account::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    /**
     * لوحة الحسابات.
     *
     * كانت الشاشة بطاقةً واحدة فيها «أهلاً <اسم المستخدم>» وتاريخُ اليوم
     * ورابطٌ إلى الموقع — لا رقم ولا مؤشّر: لوحةُ تحكّمٍ لا تُحكِم شيئاً.
     * تُغذَّى الآن بما يلزم المحاسب أول ما يفتح النظام.
     */
    public function dashboardaccount()
    {
        $range = $this->fiscalYearBounds();

        // نطاق القيود والسندات: قيوده/سنداته هو أو مَن يديرهم — نفس نطاق
        // index() بالضبط (لا داعٍ لتكراره: هذه الشاشة بلا مسارٍ فعلياً
        // اليوم، لكن أُبقيت متّسقةً مع الشاشة الحيّة تحسّباً لربطها لاحقاً).
        $entries = $this->scopeToCreator(JournalEntry::query(), 'created_by_employee')
            ->whereBetween('date', [$range['start'], $range['end']]);

        // الخزائن: `treasuryAccounts()` مُقيَّدةٌ أصلاً بنفس نطاق
        // ScopesToEmployeeHierarchy — خزائنه هو أو خزائن مَن يديرهم.
        $treasuries = $this->treasuryAccounts();

        return view('account::dashboard.account.index', [
            'periodLabel' => $range['start']->toDateString().' — '.$range['end']->toDateString(),

            'entriesCount' => (clone $entries)->count(),
            'unpostedCount' => (clone $entries)->where('is_posted', 0)->count(),

            'cashBalance' => (float) $treasuries->sum('balance'),

            'expensesTotal' => $this->scopeToCreator(Expense::whereBetween('date', [$range['start'], $range['end']]))->sum('amount'),
            'receiptsTotal' => $this->scopeToCreator(Receipt::whereBetween('date', [$range['start'], $range['end']]))->sum('amount'),

            'treasuries' => $treasuries->sortByDesc('balance')->take(6)->values(),

            'latestEntries' => $this->scopeToCreator(JournalEntry::with('details'), 'created_by_employee')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
