<?php

namespace Modules\Account\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\ChequeStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ChequeBook;
use App\Models\Client;
use App\Models\PayableCheque;
use App\Models\ReceivedCheque;
use App\Support\AccountingSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class ChecksController extends Controller
{
    use ScopesToEmployeeHierarchy;

    /**
     * صلاحيات تقارير الشيكات — الكتالوج الجديد account.reports.cheques.
     * لم يكن لهذا المتحكّم أي حماية صلاحياتٍ إطلاقاً من قبل.
     */
    public function __construct()
    {
        $this->middleware('permission.role:account.reports.cheques');
    }

    /**
     * صفحة الفهرس الرئيسية للشيكات - تعرض إحصائيات شاملة
     */
    public function index()
    {
        // إحصائيات الشيكات المستلمة
        $receivedStats = $this->getReceivedChequeStats();

        // إحصائيات الشيكات المدفوعة
        $payableStats = $this->getPayableChequeStats();

        // الشيكات المستحقة اليوم — من أصدره/استلمه هو أو من يديرهم فقط
        // (ScopesToEmployeeHierarchy)، نفس نطاق شاشتَي الشيكات أنفسهما.
        $dueTodayReceived = $this->scopeToCreator(ReceivedCheque::query())
            ->whereDate('due_date', Carbon::today())
            ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])
            ->with(['recipientAccount', 'client'])
            ->limit(10)
            ->get();

        $dueTodayPayable = $this->scopeToCreator(PayableCheque::query())
            ->whereDate('due_date', Carbon::today())
            ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])
            ->with(['bank', 'recipientAccount'])
            ->limit(10)
            ->get();

        // الشيكات المتأخرة
        $overdueReceived = $this->scopeToCreator(ReceivedCheque::query())
            ->whereDate('due_date', '<', Carbon::today())
            ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])
            ->with(['recipientAccount', 'client'])
            ->limit(10)
            ->get();

        $overduePayable = $this->scopeToCreator(PayableCheque::query())
            ->whereDate('due_date', '<', Carbon::today())
            ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])
            ->with(['bank', 'recipientAccount'])
            ->limit(10)
            ->get();

        // بيانات الرسم البياني الشهري
        $monthlyChartData = $this->getMonthlyChartData();

        return view('account::report.checks.index', compact(
            'receivedStats',
            'payableStats',
            'dueTodayReceived',
            'dueTodayPayable',
            'overdueReceived',
            'overduePayable',
            'monthlyChartData'
        ));
    }

    /**
     * إحصائيات الشيكات المستلمة
     */
    private function getReceivedChequeStats()
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // قاعدةٌ مقيَّدة بمن استلم الشيك هو أو من يديرهم (ScopesToEmployeeHierarchy)
        // — كل استعلامٍ أدناه يُستنسَخ منها، لا من `ReceivedCheque::` مباشرة.
        $base = fn () => $this->scopeToCreator(ReceivedCheque::query());

        $allCheques = $base()->get();
        $monthCheques = $base()->whereBetween('issue_date', [$startOfMonth, $endOfMonth])->get();

        return [
            'total_count' => $allCheques->count(),
            'total_amount' => $allCheques->sum('amount'),
            'month_count' => $monthCheques->count(),
            'month_amount' => $monthCheques->sum('amount'),
            'pending_count' => $base()->where('status', ChequeStatus::PENDING)->count(),
            'pending_amount' => $base()->where('status', ChequeStatus::PENDING)->sum('amount'),
            'under_collection_count' => $base()->where('status', ChequeStatus::UNDER_COLLECTION)->count(),
            'under_collection_amount' => $base()->where('status', ChequeStatus::UNDER_COLLECTION)->sum('amount'),
            'collected_count' => $base()->where('status', ChequeStatus::COLLECTED)->count(),
            'collected_amount' => $base()->where('status', ChequeStatus::COLLECTED)->sum('amount'),
            'rejected_count' => $base()->where('status', ChequeStatus::REJECTED)->count(),
            'rejected_amount' => $base()->where('status', ChequeStatus::REJECTED)->sum('amount'),
            'endorsed_count' => $base()->where('status', ChequeStatus::ENDORSED)->count(),
            'endorsed_amount' => $base()->where('status', ChequeStatus::ENDORSED)->sum('amount'),
            'overdue_count' => $base()->whereDate('due_date', '<', $today)
                ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])->count(),
            'overdue_amount' => $base()->whereDate('due_date', '<', $today)
                ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])->sum('amount'),
            'due_today_count' => $base()->whereDate('due_date', $today)
                ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])->count(),
            'due_today_amount' => $base()->whereDate('due_date', $today)
                ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])->sum('amount'),
            'upcoming_week_count' => $base()->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addWeek()])
                ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])->count(),
            'upcoming_week_amount' => $base()->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addWeek()])
                ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED, ChequeStatus::ENDORSED])->sum('amount'),
        ];
    }

    /**
     * إحصائيات الشيكات المدفوعة
     */
    private function getPayableChequeStats()
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // قاعدةٌ مقيَّدة بمن أصدر الشيك هو أو من يديرهم (ScopesToEmployeeHierarchy)
        // — كل استعلامٍ أدناه يُستنسَخ منها، لا من `PayableCheque::` مباشرة.
        $base = fn () => $this->scopeToCreator(PayableCheque::query());

        $allCheques = $base()->get();
        $monthCheques = $base()->whereBetween('issue_date', [$startOfMonth, $endOfMonth])->get();

        return [
            'total_count' => $allCheques->count(),
            'total_amount' => $allCheques->sum('amount'),
            'month_count' => $monthCheques->count(),
            'month_amount' => $monthCheques->sum('amount'),
            'issued_count' => $base()->where('status', ChequeStatus::ISSUED)->count(),
            'issued_amount' => $base()->where('status', ChequeStatus::ISSUED)->sum('amount'),
            'cashed_count' => $base()->where('status', ChequeStatus::CASHED)->count(),
            'cashed_amount' => $base()->where('status', ChequeStatus::CASHED)->sum('amount'),
            'rejected_count' => $base()->where('status', ChequeStatus::REJECTED)->count(),
            'rejected_amount' => $base()->where('status', ChequeStatus::REJECTED)->sum('amount'),
            'cancelled_count' => $base()->where('status', ChequeStatus::CANCELLED)->count(),
            'cancelled_amount' => $base()->where('status', ChequeStatus::CANCELLED)->sum('amount'),
            'overdue_count' => $base()->whereDate('due_date', '<', $today)
                ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])->count(),
            'overdue_amount' => $base()->whereDate('due_date', '<', $today)
                ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])->sum('amount'),
            'due_today_count' => $base()->whereDate('due_date', $today)
                ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])->count(),
            'due_today_amount' => $base()->whereDate('due_date', $today)
                ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])->sum('amount'),
            'upcoming_week_count' => $base()->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addWeek()])
                ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])->count(),
            'upcoming_week_amount' => $base()->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addWeek()])
                ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED])->sum('amount'),
        ];
    }

    /**
     * بيانات الرسم البياني الشهري
     */
    private function getMonthlyChartData()
    {
        $months = [];
        $receivedData = [];
        $payableData = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $months[] = $month->translatedFormat('F Y');

            $receivedData[] = $this->scopeToCreator(ReceivedCheque::query())
                ->whereYear('issue_date', $month->year)
                ->whereMonth('issue_date', $month->month)
                ->sum('amount');

            $payableData[] = $this->scopeToCreator(PayableCheque::query())
                ->whereYear('issue_date', $month->year)
                ->whereMonth('issue_date', $month->month)
                ->sum('amount');
        }

        return [
            'labels' => $months,
            'received' => $receivedData,
            'payable' => $payableData,
        ];
    }

    /**
     * تقرير الشيكات المدفوعة (الصادرة)
     */
    public function deliveredChecks(Request $request)
    {
        // جلب البيانات المطلوبة للفلاتر - البنوك من جدول الحسابات
        $banks = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))->orderBy('name')->get();
        $cheque_books = ChequeBook::with('bank')->orderBy('id')->get();
        $recipient_accounts = Account::orderBy('name')->get();
        // جلب الموردين من جدول الحسابات (الحسابات التي لديها supplier_id)
        $suppliers = Account::whereNotNull('supplier_id')->with('supplier')->orderBy('name')->get();

        // بناء الاستعلام الأساسي — من أصدر الشيك هو أو من يديرهم فقط
        // (ScopesToEmployeeHierarchy)
        $query = $this->scopeToCreator(
            PayableCheque::with(['bank', 'chequeBook', 'recipientAccount', 'supplier', 'journalEntry'])
        );

        // تطبيق الفلاتر
        $this->applyFiltersPayable($query, $request);

        // جلب البيانات
        $cheques = $query->orderBy('due_date', 'asc')->get();

        // حساب الإجماليات الموسعة
        $totals = $this->calculateTotalsPayable($cheques);

        // إحصائيات حسب الحالة
        $statusStats = $this->getStatusStatsPayable($cheques);

        // إحصائيات حسب البنك
        $bankStats = $this->getBankStats($cheques);

        // إعداد بيانات الرسوم البيانية
        $chartData = $this->prepareChartDataPayable($cheques);

        // بيانات التجميع إذا تم اختيارها
        $groupedData = $this->getGroupedDataPayable($cheques, $request->group_by);

        // تحديد نوع العرض
        $viewType = $request->view_type ?? 'detailed';

        return view('account::report.checks.delivered-checks', compact(
            'banks',
            'cheque_books',
            'recipient_accounts',
            'suppliers',
            'cheques',
            'totals',
            'statusStats',
            'bankStats',
            'chartData',
            'groupedData',
            'viewType'
        ));
    }

    /**
     * تطبيق الفلاتر على الاستعلام للشيكات المدفوعة
     */
    private function applyFiltersPayable($query, Request $request)
    {
        // فلتر الحساب المستلم
        if ($request->filled('recipient_account')) {
            $query->where('recipient_account_id', $request->recipient_account);
        }

        // فلتر البنك
        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }

        // فلتر دفتر الشيكات
        if ($request->filled('cheque_book_id')) {
            $query->where('cheque_book_id', $request->cheque_book_id);
        }

        // فلتر المورد (باستخدام حساب المورد)
        if ($request->filled('supplier_id')) {
            $query->where('recipient_account_id', $request->supplier_id);
        }

        // فلتر الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلتر رقم الشيك
        if ($request->filled('cheque_number')) {
            $query->where('cheque_number', 'like', '%'.$request->cheque_number.'%');
        }

        // فلتر المبلغ
        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->amount_from);
        }
        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->amount_to);
        }

        // فلتر اسم المستفيد
        if ($request->filled('payee_name')) {
            $query->where('payee_name', 'like', '%'.$request->payee_name.'%');
        }

        // فلاتر التاريخ
        $this->applyDateFiltersPayable($query, $request);

        // فلتر حالة الاستحقاق
        if ($request->filled('due_status')) {
            $today = Carbon::today();
            switch ($request->due_status) {
                case 'overdue':
                    $query->whereDate('due_date', '<', $today)
                        ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED]);
                    break;
                case 'due_today':
                    $query->whereDate('due_date', $today)
                        ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED]);
                    break;
                case 'upcoming':
                    $query->whereDate('due_date', '>', $today)
                        ->whereNotIn('status', [ChequeStatus::CASHED, ChequeStatus::CANCELLED]);
                    break;
            }
        }
    }

    /**
     * تطبيق فلاتر التاريخ للشيكات المدفوعة
     */
    private function applyDateFiltersPayable($query, Request $request)
    {
        // فلتر تاريخ الإصدار
        if ($request->filled('issue_date_from')) {
            $query->whereDate('issue_date', '>=', $request->issue_date_from);
        }
        if ($request->filled('issue_date_to')) {
            $query->whereDate('issue_date', '<=', $request->issue_date_to);
        }

        // فلتر تاريخ الاستحقاق
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }

        // فلتر تاريخ الصرف
        if ($request->filled('cashed_date_from')) {
            $query->whereDate('cashed_at', '>=', $request->cashed_date_from);
        }
        if ($request->filled('cashed_date_to')) {
            $query->whereDate('cashed_at', '<=', $request->cashed_date_to);
        }
    }

    /**
     * حساب الإجماليات للشيكات المدفوعة
     */
    private function calculateTotalsPayable($cheques)
    {
        $totalAmount = $cheques->sum('amount');
        $totalCount = $cheques->count();

        $today = Carbon::today();

        return [
            'total_amount' => $totalAmount,
            'total_count' => $totalCount,
            'average_amount' => $totalCount > 0 ? $totalAmount / $totalCount : 0,
            'max_amount' => $cheques->max('amount') ?? 0,
            'min_amount' => $cheques->min('amount') ?? 0,
            'due_today_count' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->eq($today))->count(),
            'due_today_amount' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->eq($today))->sum('amount'),
            'overdue_count' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->lt($today) && ! in_array($c->status?->value ?? $c->status, ['cashed', 'cancelled']))->count(),
            'overdue_amount' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->lt($today) && ! in_array($c->status?->value ?? $c->status, ['cashed', 'cancelled']))->sum('amount'),
            'upcoming_count' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->gt($today) && ! in_array($c->status?->value ?? $c->status, ['cashed', 'cancelled']))->count(),
            'upcoming_amount' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->gt($today) && ! in_array($c->status?->value ?? $c->status, ['cashed', 'cancelled']))->sum('amount'),
        ];
    }

    /**
     * إحصائيات حسب الحالة للشيكات المدفوعة
     */
    private function getStatusStatsPayable($cheques)
    {
        return [
            'issued' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'issued')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'issued')->sum('amount'),
            ],
            'cashed' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'cashed')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'cashed')->sum('amount'),
            ],
            'rejected' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'rejected')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'rejected')->sum('amount'),
            ],
            'cancelled' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'cancelled')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'cancelled')->sum('amount'),
            ],
        ];
    }

    /**
     * إحصائيات حسب البنك
     */
    private function getBankStats($cheques)
    {
        return $cheques->groupBy('bank_id')->map(function ($group) {
            return [
                'name' => $group->first()->bank->name ?? 'غير محدد',
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        })->values();
    }

    /**
     * إعداد بيانات الرسوم البيانية للشيكات المدفوعة
     */
    private function prepareChartDataPayable($cheques)
    {
        $today = Carbon::today();

        // بيانات حالة الاستحقاق
        $dueStatusData = [
            'overdue' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->lt($today) && ! in_array($c->status?->value ?? $c->status, ['cashed', 'cancelled']))->count(),
            'due_today' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->eq($today))->count(),
            'upcoming' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->gt($today) && ! in_array($c->status?->value ?? $c->status, ['cashed', 'cancelled']))->count(),
        ];

        // بيانات الحالة
        $statusData = [
            'issued' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'issued')->count(),
            'cashed' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'cashed')->count(),
            'rejected' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'rejected')->count(),
            'cancelled' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'cancelled')->count(),
        ];

        // بيانات حسب البنك
        $bankData = $cheques->groupBy(fn ($c) => $c->bank->name ?? 'غير محدد')->map(fn ($group) => $group->sum('amount'));

        // بيانات حسب الشهر
        $monthlyData = $cheques->groupBy(fn ($c) => Carbon::parse($c->issue_date)->format('Y-m'))
            ->map(fn ($group) => ['count' => $group->count(), 'amount' => $group->sum('amount')]);

        return [
            'dueStatus' => [
                'labels' => ['متأخر', 'يستحق اليوم', 'قادم'],
                'values' => array_values($dueStatusData),
                'colors' => ['#ef4444', '#f59e0b', '#10b981'],
            ],
            'status' => [
                'labels' => ['صادر', 'مصروف', 'مرفوض', 'ملغي'],
                'values' => array_values($statusData),
                'colors' => ['#3b82f6', '#10b981', '#ef4444', '#6b7280'],
            ],
            'banks' => [
                'labels' => $bankData->keys()->toArray(),
                'values' => $bankData->values()->toArray(),
            ],
            'monthly' => [
                'labels' => $monthlyData->keys()->toArray(),
                'counts' => $monthlyData->pluck('count')->toArray(),
                'amounts' => $monthlyData->pluck('amount')->toArray(),
            ],
        ];
    }

    /**
     * تجميع البيانات حسب المعيار المحدد للشيكات المدفوعة
     */
    private function getGroupedDataPayable($cheques, $groupBy)
    {
        if (! $groupBy) {
            return null;
        }

        switch ($groupBy) {
            case 'bank':
                return $cheques->groupBy('bank_id')->map(function ($group) {
                    return [
                        'name' => $group->first()->bank->name ?? 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'cheque_book':
                return $cheques->groupBy('cheque_book_id')->map(function ($group) {
                    return [
                        'name' => $group->first()->chequeBook->cheque_book_number ?? 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'recipient':
                return $cheques->groupBy('recipient_account_id')->map(function ($group) {
                    return [
                        'name' => $group->first()->recipientAccount->name ?? 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'status':
                return $cheques->groupBy(fn ($c) => $c->status?->value ?? $c->status)->map(function ($group, $status) {
                    return [
                        'name' => $this->getPayableStatusLabel($status),
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'month':
                return $cheques->groupBy(fn ($c) => Carbon::parse($c->issue_date)->format('Y-m'))->map(function ($group, $month) {
                    return [
                        'name' => Carbon::parse($month.'-01')->translatedFormat('F Y'),
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            default:
                return null;
        }
    }

    /**
     * تحويل رمز الحالة إلى نص عربي للشيكات المدفوعة
     */
    private function getPayableStatusLabel($status)
    {
        $statusMap = [
            'issued' => 'صادر',
            'cashed' => 'مصروف',
            'rejected' => 'مرفوض',
            'cancelled' => 'ملغي',
        ];

        if ($status instanceof ChequeStatus) {
            return $status->getName();
        }

        return $statusMap[$status] ?? 'غير محدد';
    }

    /**
     * عرض تقرير الشيكات المستلمة
     */
    public function received(Request $request)
    {
        // جلب البيانات المطلوبة للفلاتر
        $recipient_accounts = Account::orderBy('name')->get();
        $collection_accounts = Account::orderBy('name')->get();
        $bank_accounts = Account::where('name', 'like', '%بنك%')->orderBy('name')->get();
        $clients = Client::get();

        // بناء الاستعلام الأساسي — من استلم الشيك هو أو من يديرهم فقط
        // (ScopesToEmployeeHierarchy)
        $query = $this->scopeToCreator(
            ReceivedCheque::with(['recipientAccount', 'collectionAccount', 'bankAccount', 'client', 'journalEntry'])
        );

        // تطبيق الفلاتر
        $this->applyFiltersReceived($query, $request);

        // جلب البيانات
        $cheques = $query->orderBy('due_date', 'asc')->get();

        // حساب الإجماليات الموسعة
        $totals = $this->calculateTotalsReceived($cheques);

        // إحصائيات حسب الحالة
        $statusStats = $this->getStatusStatsReceived($cheques);

        // إحصائيات حسب العميل
        $clientStats = $this->getClientStats($cheques);

        // إعداد بيانات الرسوم البيانية
        $chartData = $this->prepareChartDataReceived($cheques);

        // بيانات التجميع إذا تم اختيارها
        $groupedData = $this->getGroupedDataReceived($cheques, $request->group_by);

        // تحديد نوع العرض
        $viewType = $request->view_type ?? 'detailed';

        return view('account::report.checks.received-checks', compact(
            'recipient_accounts',
            'collection_accounts',
            'bank_accounts',
            'clients',
            'cheques',
            'totals',
            'statusStats',
            'clientStats',
            'chartData',
            'groupedData',
            'viewType'
        ));
    }

    /**
     * تطبيق الفلاتر على الاستعلام للشيكات المستلمة
     */
    private function applyFiltersReceived($query, Request $request)
    {
        // فلتر الحساب المستلم
        if ($request->filled('recipient_account')) {
            $query->where('recipient_account_id', $request->recipient_account);
        }

        // فلتر حساب التحصيل
        if ($request->filled('collection_account')) {
            $query->where('collection_account_id', $request->collection_account);
        }

        // فلتر حساب البنك
        if ($request->filled('bank_account')) {
            $query->where('bank_account_id', $request->bank_account);
        }

        // فلتر العميل
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // فلتر الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فلتر رقم الشيك
        if ($request->filled('cheque_number')) {
            $query->where('cheque_number', 'like', '%'.$request->cheque_number.'%');
        }

        // فلتر المبلغ
        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->amount_from);
        }
        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->amount_to);
        }

        // فلتر اسم المدفوع له
        if ($request->filled('payee_name')) {
            $query->where('payee_name', 'like', '%'.$request->payee_name.'%');
        }

        // فلتر الاسم
        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        // فلتر نوع التظهير
        if ($request->filled('endorsement')) {
            $query->where('endorsement', $request->endorsement);
        }

        // فلتر البنك الساحب
        if ($request->filled('drawer_bank')) {
            $query->where('drawer_bank', 'like', '%'.$request->drawer_bank.'%');
        }

        // فلاتر التاريخ
        $this->applyDateFiltersReceived($query, $request);

        // فلتر حالة الاستحقاق
        if ($request->filled('due_status')) {
            $today = Carbon::today();
            switch ($request->due_status) {
                case 'overdue':
                    $query->whereDate('due_date', '<', $today)
                        ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED]);
                    break;
                case 'due_today':
                    $query->whereDate('due_date', $today)
                        ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED]);
                    break;
                case 'upcoming':
                    $query->whereDate('due_date', '>', $today)
                        ->whereNotIn('status', [ChequeStatus::COLLECTED, ChequeStatus::REJECTED]);
                    break;
            }
        }
    }

    /**
     * تطبيق فلاتر التاريخ للشيكات المستلمة
     */
    private function applyDateFiltersReceived($query, Request $request)
    {
        // فلتر تاريخ الإصدار
        if ($request->filled('issue_date_from')) {
            $query->whereDate('issue_date', '>=', $request->issue_date_from);
        }
        if ($request->filled('issue_date_to')) {
            $query->whereDate('issue_date', '<=', $request->issue_date_to);
        }

        // فلتر تاريخ الاستحقاق
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }

        // فلتر تاريخ التحصيل
        if ($request->filled('collected_date_from')) {
            $query->whereDate('collected_at', '>=', $request->collected_date_from);
        }
        if ($request->filled('collected_date_to')) {
            $query->whereDate('collected_at', '<=', $request->collected_date_to);
        }
    }

    /**
     * حساب الإجماليات للشيكات المستلمة
     */
    private function calculateTotalsReceived($cheques)
    {
        $totalAmount = $cheques->sum('amount');
        $totalCount = $cheques->count();
        $today = Carbon::today();

        return [
            'total_amount' => $totalAmount,
            'total_count' => $totalCount,
            'average_amount' => $totalCount > 0 ? $totalAmount / $totalCount : 0,
            'max_amount' => $cheques->max('amount') ?? 0,
            'min_amount' => $cheques->min('amount') ?? 0,
            'due_today_count' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->eq($today))->count(),
            'due_today_amount' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->eq($today))->sum('amount'),
            'overdue_count' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->lt($today) && ! in_array($c->status?->value ?? $c->status, ['collected', 'rejected', 'endorsed']))->count(),
            'overdue_amount' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->lt($today) && ! in_array($c->status?->value ?? $c->status, ['collected', 'rejected', 'endorsed']))->sum('amount'),
            'upcoming_count' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->gt($today) && ! in_array($c->status?->value ?? $c->status, ['collected', 'rejected', 'endorsed']))->count(),
            'upcoming_amount' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->gt($today) && ! in_array($c->status?->value ?? $c->status, ['collected', 'rejected', 'endorsed']))->sum('amount'),
        ];
    }

    /**
     * إحصائيات حسب الحالة للشيكات المستلمة
     */
    private function getStatusStatsReceived($cheques)
    {
        return [
            'pending' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'pending')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'pending')->sum('amount'),
            ],
            'under_collection' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'under_collection')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'under_collection')->sum('amount'),
            ],
            'collected' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'collected')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'collected')->sum('amount'),
            ],
            'rejected' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'rejected')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'rejected')->sum('amount'),
            ],
            'endorsed' => [
                'count' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'endorsed')->count(),
                'amount' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'endorsed')->sum('amount'),
            ],
        ];
    }

    /**
     * إحصائيات حسب العميل
     */
    private function getClientStats($cheques)
    {
        return $cheques->groupBy('client_id')->map(function ($group) {
            return [
                'name' => $group->first()->client->name ?? ($group->first()->payee_name ?? 'غير محدد'),
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        })->sortByDesc('amount')->take(10)->values();
    }

    /**
     * إعداد بيانات الرسوم البيانية للشيكات المستلمة
     */
    private function prepareChartDataReceived($cheques)
    {
        $today = Carbon::today();

        // بيانات حالة الاستحقاق
        $dueStatusData = [
            'overdue' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->lt($today) && ! in_array($c->status?->value ?? $c->status, ['collected', 'rejected']))->count(),
            'due_today' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->eq($today))->count(),
            'upcoming' => $cheques->filter(fn ($c) => $c->due_date && Carbon::parse($c->due_date)->gt($today) && ! in_array($c->status?->value ?? $c->status, ['collected', 'rejected']))->count(),
        ];

        // بيانات الحالة
        $statusData = [
            'pending' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'pending')->count(),
            'under_collection' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'under_collection')->count(),
            'collected' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'collected')->count(),
            'rejected' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'rejected')->count(),
            'endorsed' => $cheques->filter(fn ($c) => ($c->status?->value ?? $c->status) === 'endorsed')->count(),
        ];

        // بيانات حسب العميل (أعلى 5)
        $clientData = $cheques->groupBy('client_id')
            ->map(fn ($group) => [
                'name' => $group->first()->client->name ?? ($group->first()->payee_name ?? 'غير محدد'),
                'amount' => $group->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->take(5);

        // بيانات حسب الشهر
        $monthlyData = $cheques->groupBy(fn ($c) => Carbon::parse($c->issue_date)->format('Y-m'))
            ->map(fn ($group) => ['count' => $group->count(), 'amount' => $group->sum('amount')]);

        return [
            'dueStatus' => [
                'labels' => ['متأخر', 'يستحق اليوم', 'قادم'],
                'values' => array_values($dueStatusData),
                'colors' => ['#ef4444', '#f59e0b', '#10b981'],
            ],
            'status' => [
                'labels' => ['قيد الانتظار', 'برسم التحصيل', 'محصّل', 'مرفوض', 'مظهّر'],
                'values' => array_values($statusData),
                'colors' => ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6'],
            ],
            'clients' => [
                'labels' => $clientData->pluck('name')->toArray(),
                'values' => $clientData->pluck('amount')->toArray(),
            ],
            'monthly' => [
                'labels' => $monthlyData->keys()->toArray(),
                'counts' => $monthlyData->pluck('count')->toArray(),
                'amounts' => $monthlyData->pluck('amount')->toArray(),
            ],
        ];
    }

    /**
     * تجميع البيانات حسب المعيار المحدد للشيكات المستلمة
     */
    private function getGroupedDataReceived($cheques, $groupBy)
    {
        if (! $groupBy) {
            return null;
        }

        switch ($groupBy) {
            case 'recipient_account':
                return $cheques->groupBy('recipient_account_id')->map(function ($group) {
                    return [
                        'name' => $group->first()->recipientAccount->name ?? 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'collection_account':
                return $cheques->groupBy('collection_account_id')->map(function ($group) {
                    return [
                        'name' => $group->first()->collectionAccount->name ?? 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'client':
                return $cheques->groupBy('client_id')->map(function ($group) {
                    return [
                        'name' => $group->first()->client->name ?? ($group->first()->payee_name ?? 'غير محدد'),
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'status':
                return $cheques->groupBy(fn ($c) => $c->status?->value ?? $c->status)->map(function ($group, $status) {
                    return [
                        'name' => $this->getReceivedStatusLabel($status),
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'endorsement':
                return $cheques->groupBy('endorsement')->map(function ($group, $endorsement) {
                    return [
                        'name' => $this->getEndorsementLabel($endorsement),
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'month':
                return $cheques->groupBy(fn ($c) => Carbon::parse($c->issue_date)->format('Y-m'))->map(function ($group, $month) {
                    return [
                        'name' => Carbon::parse($month.'-01')->translatedFormat('F Y'),
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            case 'drawer_bank':
                return $cheques->groupBy('drawer_bank')->map(function ($group, $bank) {
                    return [
                        'name' => $bank ?: 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'items' => $group,
                    ];
                });
            default:
                return null;
        }
    }

    /**
     * تحويل رمز الحالة إلى نص عربي للشيكات المستلمة
     */
    private function getReceivedStatusLabel($status)
    {
        $statusMap = [
            'pending' => 'قيد الانتظار',
            'under_collection' => 'برسم التحصيل',
            'collected' => 'محصّل',
            'rejected' => 'مرفوض',
            'endorsed' => 'مظهّر',
        ];

        if ($status instanceof ChequeStatus) {
            return $status->getName();
        }

        return $statusMap[$status] ?? 'غير محدد';
    }

    /**
     * تحويل رمز التظهير إلى نص عربي
     */
    private function getEndorsementLabel($endorsement)
    {
        $endorsementMap = [
            'cash' => 'نقدي',
            'bank_deposit' => 'إيداع بنكي',
            'transfer' => 'تحويل',
            'other' => 'أخرى',
        ];

        return $endorsementMap[$endorsement] ?? 'غير محدد';
    }
}
