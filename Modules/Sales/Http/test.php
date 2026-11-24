<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Account;
use App\Models\User;
use App\Models\Branch;
use App\Models\Region_groub;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentsProcess;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(auth()->user()->id);
        $branch_id = $user->branch_id;
        $selected_branch_id = $request->get('branch_id', $branch_id);

        // الفرع الحالي لتحديد هل هو رئيسي
        $currentBranch = Branch::find($branch_id);
        $isMainBranch = $currentBranch && $currentBranch->is_main == 1;

        // =================== الإحصائيات الأساسية ===================

        // 1) إجمالي المبيعات (من القيود)
        $totalSales = $this->getTotalSales($selected_branch_id, $isMainBranch);

        // 2) إجمالي المدفوعات (من القيود - المرتبطة بفواتير/عميل)
        $totalPayments = $this->getTotalPayments($selected_branch_id, $isMainBranch);

        // 3) إجمالي سندات القبض (من القيود)
        $totalReceipts = $this->getTotalReceiveVouchers($selected_branch_id, $isMainBranch);

        // 4) إجمالي التحصيلات (المدفوعات + سندات القبض)
        $totalCollections = $totalPayments + $totalReceipts;

        // 5) إجمالي مردود المبيعات الموحّد (مرتجع + "إشعار دائن")
        $totalReturns = $this->getTotalSalesReturns($selected_branch_id, $isMainBranch);

        // 6) إشعارات دائنة كـ "بند مستقل" لم تعد مستخدمة، نضعها صفر مؤقتاً للحفاظ على التوافق مع الواجهة الحالية
        $totalCredits = 0;

        // =================== الإحصائيات الشهرية ===================

        // المبيعات الشهرية
        $monthlySalesData = $this->getMonthlySales($selected_branch_id, $isMainBranch);

        // المدفوعات الشهرية
        $monthlyPaymentsData = $this->getMonthlyPayments($selected_branch_id, $isMainBranch);

        // سندات القبض الشهرية
        $monthlyReceiptsData = $this->getMonthlyReceipts($selected_branch_id, $isMainBranch);

        // التحصيلات الشهرية = مدفوعات + سندات قبض
        $monthlyCollectionsData = array_map(function($payment, $receipt) {
            return $payment + $receipt;
        }, $monthlyPaymentsData, $monthlyReceiptsData);

        // المرتجعات الشهرية الموحّدة (مرتجع + "إشعار دائن")
        $monthlyReturnsData = $this->getMonthlyReturns($selected_branch_id, $isMainBranch);

        // "الإشعارات الدائنة الشهرية" أصبحت غير مستخدمة — نمرر صفراً مؤقتاً للحفاظ على التوافق مع الواجهة الحالية
        $monthlyCreditNotesData = array_fill(0, 12, 0);

        // =================== إحصائيات إضافية ===================

        // عدد الفواتير (من القيود التي تحتوي على "فاتورة مبيعات")
        $invoiceCount = $this->getInvoiceCount($selected_branch_id, $isMainBranch);

        // متوسط المبيعات
        $averageSales = $invoiceCount > 0 ? $totalSales / $invoiceCount : 0;

        // الأهداف الشهرية (110% من المبيعات)
        $monthlyTargetData = array_map(function ($value) {
            return round($value * 1.10, 2);
        }, $monthlySalesData);

        // عدد الفواتير الشهري
        $monthlyInvoiceCountData = $this->getMonthlyInvoiceCounts($selected_branch_id, $isMainBranch);

        // متوسط المبيعات الشهرية
        $monthlyAvgSalesData = [];
        foreach ($monthlySalesData as $idx => $total) {
            $count = $monthlyInvoiceCountData[$idx] ?? 0;
            $monthlyAvgSalesData[] = $count > 0 ? round($total / $count, 2) : 0;
        }

        // =================== حساب النسب ===================

        // نسبة مردود المبيعات (الموحّد)
        $returnRate = $totalSales > 0 ? round(($totalReturns / $totalSales) * 100, 2) : 0;

        // لم نعد نعرض نسبة إشعار دائن كبند منفصل
        $creditRate = 0;

        // نمو المبيعات (مقارنة الشهر الحالي بالسابق)
        $currentMonth = Carbon::now()->month;
        $prevMonth = $currentMonth > 1 ? $currentMonth - 1 : 12;
        $currentMonthSales = $monthlySalesData[$currentMonth - 1] ?? 0;
        $prevMonthSales = $monthlySalesData[$prevMonth - 1] ?? 0;

        if ($prevMonthSales > 0) {
            $salesGrowthPercent = (($currentMonthSales - $prevMonthSales) / $prevMonthSales) * 100;
        } elseif ($averageSales > 0) {
            $salesGrowthPercent = (($currentMonthSales - $averageSales) / $averageSales) * 100;
        } else {
            $salesGrowthPercent = 0;
        }
        $salesGrowthPercent = round($salesGrowthPercent, 2);

        // =================== بيانات إضافية للرسوم ===================

        $topProductLabels = [];
        $topProductData = [];

        $quoteLabels = [];
        $quoteData = [];

        // العملاء
        $customersQuery = Client::when(!$isMainBranch, function($query) use ($selected_branch_id) {
            $query->where('branch_id', $selected_branch_id);
        });
        $newCustomers = $customersQuery->whereYear('created_at', Carbon::now()->year)->count();
        $totalCustomers = $customersQuery->count();
        $regularCustomers = max($totalCustomers - $newCustomers, 0);
        $occasionalCustomers = 0;
        $lostCustomers = 0;

        // =================== إحصائيات الفروع ===================

        $branches = [];
        if ($isMainBranch) {
            $branches = Branch::all();
        }

        $branchStatistics = $this->getBranchStatistics($selected_branch_id, $isMainBranch);
        $branchSalesLabels = $branchStatistics['labels'];
        $branchSalesData   = $branchStatistics['sales'];
        $branchReturnsData = $branchStatistics['returns'];
        $branchPaymentsData= $branchStatistics['payments'];

        // =================== بيانات الموظفين ===================

        $employeePaymentsReceipts = $this->getPaymentsReceiptsByEmployee($selected_branch_id, $isMainBranch);

        // =================== إحصائيات المجموعات ===================

        $regionGroups = Region_groub::when(!$isMainBranch, function ($query) use ($selected_branch_id) {
            $query->where(function ($q) use ($selected_branch_id) {
                $q->where('territory_office_id', $selected_branch_id)
                    ->orWhereHas('neighborhoods.client', function ($clientQuery) use ($selected_branch_id) {
                        $clientQuery->where('branch_id', $selected_branch_id);
                    });
            });
        })->get(['id', 'name']);

        $groupChartData = collect([]);

        return view('sales::kpis.index', compact(
            'invoiceCount',
            'totalSales',
            'totalPayments',
            'totalReceipts',
            'totalCollections',
            'totalReturns',
            'totalCredits',              // مؤقتاً صفر
            'averageSales',
            'monthlySalesData',
            'monthlyCollectionsData',
            'monthlyReturnsData',        // الموحّد (مرتجع + إشعار دائن)
            'monthlyPaymentsData',
            'monthlyReceiptsData',
            'monthlyTargetData',
            'monthlyInvoiceCountData',
            'monthlyAvgSalesData',
            'topProductLabels',
            'topProductData',
            'quoteLabels',
            'quoteData',
            'newCustomers',
            'regularCustomers',
            'occasionalCustomers',
            'lostCustomers',
            'salesGrowthPercent',
            'returnRate',
            'creditRate',                // مؤقتاً صفر
            'branches',
            'selected_branch_id',
            'branch_id',
            'branchSalesLabels',
            'branchSalesData',
            'branchReturnsData',
            'branchPaymentsData',
            'groupChartData',
            'regionGroups',
            'isMainBranch',
            'monthlyCreditNotesData',     // مصفوفة أصفار مؤقتاً
            'employeePaymentsReceipts'    // بيانات المدفوعات وسندات القبض حسب الموظف
        ));
    }

    // =================== أدوات مساعدة ===================

    /**
     * يطبق تصفية الفرع على مستوى journalEntry (client أو invoice.client) للأفرع غير الرئيسية
     */
    private function constrainByBranchOnJE($q, $branchId, $isMainBranch)
    {
        if ($isMainBranch) {
            return;
        }

        $q->where(function($branchQuery) use ($branchId) {
            $branchQuery
                ->whereHas('client', function($clientQuery) use ($branchId) {
                    $clientQuery->where('branch_id', $branchId);
                })
                ->orWhereHas('invoice.client', function($invoiceClientQuery) use ($branchId) {
                    $invoiceClientQuery->where('branch_id', $branchId);
                });
        });
    }

    // =================== دوال الجلب من القيود ===================

    /**
     * إجمالي المبيعات من القيود (فاتورة مبيعات) - تُجمع من المدين
     */
    private function getTotalSales($branchId, $isMainBranch)
    {
        $query = JournalEntryDetail::whereHas('journalEntry', function($q) use ($branchId, $isMainBranch) {
            $q->where('status', 1);
            $this->constrainByBranchOnJE($q, $branchId, $isMainBranch);
        })
        ->where('description', 'LIKE', '%فاتورة مبيعات%');

        return $query->sum('debit');
    }

    /**
     * إجمالي المدفوعات - من جدول PaymentsProcess
     */
    private function getTotalPayments($branchId, $isMainBranch)
    {
        // احصل على الفواتير المستثناة
        $returnedInvoiceIds = Invoice::whereNotNull('reference_number')
            ->pluck('reference_number')->toArray();

        $excludedInvoiceIds = array_unique(array_merge(
            $returnedInvoiceIds,
            Invoice::where('type', 'returned')->pluck('id')->toArray()
        ));

        // استعلام المدفوعات
        $query = PaymentsProcess::where('created_at', '>=', Carbon::now()->subYear())
            ->whereHas('invoice', function ($q) use ($excludedInvoiceIds) {
            $q->where('type', 'normal')
                ->whereNotIn('id', $excludedInvoiceIds);
        });

        // تطبيق فلتر الفرع
        if (!$isMainBranch) {
            $query->whereHas('invoice.client', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        return $query->sum('amount');
    }

    /**
     * إجمالي سندات القبض - من جدول Receipt
     */
    private function getTotalReceiveVouchers($branchId, $isMainBranch)
    {
        $query = Receipt::where('created_at', '>=', Carbon::now()->subYear());

        // تطبيق فلتر الفرع
        if (!$isMainBranch) {
            $query->whereHas('account.client', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        return $query->sum('amount');
    }

    /**
     * إجمالي مردود المبيعات (مرتجع + "إشعار دائن بحسب أوصافك") - تُجمع من الدائن
     */
    private function getTotalSalesReturns($branchId, $isMainBranch)
    {
        $returnLike = [
            '%مرتجع مبيعات%',
            '%مردود المبيعات%',
            '%مردود مبيعات%',
        ];

        // الأوصاف التي اعتبرتها سابقاً "إشعار دائن"
        $creditNoteLike = [
            '%دفعة عميل%',
            '%دفعة من العميل%',
            // إن وجدت لديك أوصاف صريحة لاشعار دائن اضفها هنا:
            // '%اشعار دائن%',
            // '%إشعار دائن%',
        ];

        $query = JournalEntryDetail::whereHas('journalEntry', function($q) use ($branchId, $isMainBranch) {
            $q->where('status', 1);
            $this->constrainByBranchOnJE($q, $branchId, $isMainBranch);
        })
        ->where(function($q) use ($returnLike, $creditNoteLike) {
            $q->where(function($qq) use ($returnLike) {
                foreach ($returnLike as $like) {
                    $qq->orWhere('description', 'LIKE', $like);
                }
            })
            ->orWhere(function($qq) use ($creditNoteLike) {
                foreach ($creditNoteLike as $like) {
                    $qq->orWhere('description', 'LIKE', $like);
                }
            });
        });

        return $query->sum('credit');
    }

    /**
     * المبيعات الشهرية (فاتورة مبيعات) - من المدين
     */
    private function getMonthlySales($branchId, $isMainBranch)
    {
        $query = JournalEntryDetail::select(
            DB::raw('MONTH(journal_entries.date) as month'),
            DB::raw('SUM(journal_entry_details.debit) as total')
        )
        ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
        ->where('journal_entries.status', 1)
        ->whereYear('journal_entries.date', Carbon::now()->year)
        ->where('journal_entry_details.description', 'LIKE', '%فاتورة مبيعات%');

        if (!$isMainBranch) {
            $query->where(function($branchQuery) use ($branchId) {
                $branchQuery
                    ->whereHas('journalEntry.client', function($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    })
                    ->orWhereHas('journalEntry.invoice.client', function($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    });
            });
        }

        $results = $query->groupBy('month')->get();

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $entry = $results->firstWhere('month', $m);
            $monthlyData[] = $entry ? (float) $entry->total : 0;
        }

        return $monthlyData;
    }

    /**
     * المدفوعات الشهرية - من جدول PaymentsProcess
     */
    private function getMonthlyPayments($branchId, $isMainBranch)
    {
        // احصل على الفواتير المستثناة
        $returnedInvoiceIds = Invoice::whereNotNull('reference_number')
            ->pluck('reference_number')->toArray();

        $excludedInvoiceIds = array_unique(array_merge(
            $returnedInvoiceIds,
            Invoice::where('type', 'returned')->pluck('id')->toArray()
        ));

        $query = PaymentsProcess::select(
            DB::raw('MONTH(payments_process.created_at) as month'),
            DB::raw('SUM(payments_process.amount) as total')
        )
        ->whereYear('payments_process.created_at', Carbon::now()->year)
        ->whereHas('invoice', function ($q) use ($excludedInvoiceIds) {
            $q->where('type', 'normal')
                ->whereNotIn('id', $excludedInvoiceIds);
        });

        if (!$isMainBranch) {
            $query->whereHas('invoice.client', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $results = $query->groupBy('month')->get();

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $entry = $results->firstWhere('month', $m);
            $monthlyData[] = $entry ? (float) $entry->total : 0;
        }

        return $monthlyData;
    }

    /**
     * سندات القبض الشهرية - من جدول Receipt
     */
    private function getMonthlyReceipts($branchId, $isMainBranch)
    {
        $query = Receipt::select(
            DB::raw('MONTH(receipts.created_at) as month'),
            DB::raw('SUM(receipts.amount) as total')
        )
        ->whereYear('receipts.created_at', Carbon::now()->year);

        if (!$isMainBranch) {
            $query->whereHas('account.client', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $results = $query->groupBy('month')->get();

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $entry = $results->firstWhere('month', $m);
            $monthlyData[] = $entry ? (float) $entry->total : 0;
        }

        return $monthlyData;
    }

    /**
     * مردود المبيعات الشهري (مرتجع + "إشعار دائن بحسب أوصافك") - من الدائن
     */
    private function getMonthlyReturns($branchId, $isMainBranch)
    {
        $returnLike = [
            '%مرتجع مبيعات%',
            '%مردود المبيعات%',
            '%مردود مبيعات%',
        ];

        $creditNoteLike = [
            '%دفعة عميل%',
            '%دفعة من العميل%',
            // '%اشعار دائن%',
            // '%إشعار دائن%',
        ];

        $query = JournalEntryDetail::select(
            DB::raw('MONTH(journal_entries.date) as month'),
            DB::raw('SUM(journal_entry_details.credit) as total')
        )
        ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
        ->where('journal_entries.status', 1)
        ->whereYear('journal_entries.date', Carbon::now()->year)
        ->where(function($q) use ($returnLike, $creditNoteLike) {
            $q->where(function($qq) use ($returnLike) {
                foreach ($returnLike as $like) {
                    $qq->orWhere('journal_entry_details.description', 'LIKE', $like);
                }
            })
            ->orWhere(function($qq) use ($creditNoteLike) {
                foreach ($creditNoteLike as $like) {
                    $qq->orWhere('journal_entry_details.description', 'LIKE', $like);
                }
            });
        });

        if (!$isMainBranch) {
            $query->where(function($branchQuery) use ($branchId) {
                $branchQuery
                    ->whereHas('journalEntry.client', function($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    })
                    ->orWhereHas('journalEntry.invoice.client', function($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    });
            });
        }

        $results = $query->groupBy('month')->get();

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $entry = $results->firstWhere('month', $m);
            $monthlyData[] = $entry ? (float) $entry->total : 0;
        }

        return $monthlyData;
    }

    /**
     * عدد الفواتير (وصف يحتوي "فاتورة مبيعات")
     */
    private function getInvoiceCount($branchId, $isMainBranch)
    {
        $query = JournalEntryDetail::whereHas('journalEntry', function($q) use ($branchId, $isMainBranch) {
            $q->where('status', 1);
            $this->constrainByBranchOnJE($q, $branchId, $isMainBranch);
        })
        ->where('description', 'LIKE', '%فاتورة مبيعات%')
        ->distinct('journal_entry_id');

        return $query->count();
    }

    /**
     * عدد الفواتير الشهري
     */
    private function getMonthlyInvoiceCounts($branchId, $isMainBranch)
    {
        $query = JournalEntryDetail::select(
            DB::raw('MONTH(journal_entries.date) as month'),
            DB::raw('COUNT(DISTINCT journal_entry_details.journal_entry_id) as total')
        )
        ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
        ->where('journal_entries.status', 1)
        ->whereYear('journal_entries.date', Carbon::now()->year)
        ->where('journal_entry_details.description', 'LIKE', '%فاتورة مبيعات%');

        if (!$isMainBranch) {
            $query->where(function($branchQuery) use ($branchId) {
                $branchQuery
                    ->whereHas('journalEntry.client', function($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    })
                    ->orWhereHas('journalEntry.invoice.client', function($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    });
            });
        }

        $results = $query->groupBy('month')->get();

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $entry = $results->firstWhere('month', $m);
            $monthlyData[] = $entry ? (int) $entry->total : 0;
        }

        return $monthlyData;
    }

    /**
     * إحصائيات الفروع
     */
    private function getBranchStatistics($branchId, $isMainBranch)
    {
        if (!$isMainBranch) {
            $branch = Branch::find($branchId);
            return [
                'labels'   => [$branch->name ?? 'الفرع الحالي'],
                'sales'    => [$this->getTotalSales($branchId, false)],
                'returns'  => [$this->getTotalSalesReturns($branchId, false)],
                'payments' => [$this->getTotalPayments($branchId, false)]
            ];
        }

        $branches = Branch::all();
        $labels = [];
        $salesData = [];
        $returnsData = [];
        $paymentsData = [];

        foreach ($branches as $branch) {
            $labels[]      = $branch->name;
            $salesData[]   = $this->getTotalSales($branch->id, false);
            $returnsData[] = $this->getTotalSalesReturns($branch->id, false);
            $paymentsData[] = $this->getTotalPayments($branch->id, false);
        }

        return [
            'labels'   => $labels,
            'sales'    => $salesData,
            'returns'  => $returnsData,
            'payments' => $paymentsData
        ];
    }

    /**
     * جلب المدفوعات وسندات القبض مجمعة حسب الموظف
     */
    public function getPaymentsReceiptsByEmployee($branchId, $isMainBranch)
    {
        // احصل على الفواتير المستثناة
        $returnedInvoiceIds = Invoice::whereNotNull('reference_number')
            ->pluck('reference_number')->toArray();

        $excludedInvoiceIds = array_unique(array_merge(
            $returnedInvoiceIds,
            Invoice::where('type', 'returned')->pluck('id')->toArray()
        ));

        // جلب جميع الموظفين
        $employees = User::whereIn('role', ['employee', 'manager'])
            ->when(!$isMainBranch, function($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->get();

        $chartLabels = [];
        $chartPayments = [];
        $chartReceipts = [];

        foreach ($employees as $employee) {
            // جلب المدفوعات
            $paymentsQuery = PaymentsProcess::where('created_at', '>=', Carbon::now()->subYear())
                ->whereHas('invoice', function ($q) use ($excludedInvoiceIds) {
                    $q->where('type', 'normal')
                        ->whereNotIn('id', $excludedInvoiceIds);
                });

            // تطبيق فلتر الموظف
            $paymentsQuery->where(function($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                  ->orWhereHas('invoice', function($qq) use ($employee) {
                      $qq->where('created_by', $employee->id);
                  });
            });

            // تطبيق فلتر الفرع إن لزم
            if (!$isMainBranch) {
                $paymentsQuery->whereHas('invoice.client', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            }

            $paymentsTotal = $paymentsQuery->sum('amount');

            // جلب سندات القبض
            $receiptsQuery = Receipt::where('created_by', $employee->id)
                ->where('created_at', '>=', Carbon::now()->subYear());

            if (!$isMainBranch) {
                $receiptsQuery->whereHas('account.client', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            }

            $receiptsTotal = $receiptsQuery->sum('amount');

            // أضف البيانات فقط إذا كان هناك عمليات
            if ($paymentsTotal > 0 || $receiptsTotal > 0) {
                $chartLabels[] = $employee->name;
                $chartPayments[] = $paymentsTotal;
                $chartReceipts[] = $receiptsTotal;
            }
        }

        return [
            'labels' => $chartLabels,
            'payments' => $chartPayments,
            'receipts' => $chartReceipts
        ];
    }
}