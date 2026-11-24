<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Enums\ProductType;
use App\Models\Account;
use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;

class WarehouseBalanceReportController extends ReportController
{
    /*
        الفترة المحاسبية: النطاقُ الافتراضي للتقرير هو السنةُ المالية الجارية
        لا مدًى مكتوبٌ في الشيفرة.
    */
    use RespectsAccountingPeriods;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.warehouse_balance');
    }

    public function index(Request $request)
    {
        // 1. إعداد الفلاتر — الأصناف المخزنية وحدها، لا رصيد للخدمات والرقميّات
        $products = Product::whereIn('type', ProductType::stockedValues())
            ->select('id', 'name', 'serial_number')->orderBy('name')->get();
        $categories = Category::select('id', 'name')->orderBy('name')->get();

        $scopedWarehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::select('id', 'name')
            ->when($scopedWarehouseIds !== null, fn ($q) => $q->whereIn('id', $scopedWarehouseIds ?: [0]))
            ->orderBy('name')->get();
        $accounts = Account::select('id', 'name')->orderBy('name')->get();

        // معالجة الفترة الزمنية
        $period = $request->input('period', '30');
        $dateRange = $this->getDateRange($period, $request);
        $startDate = $dateRange['from'];
        $endDate = $dateRange['to'];

        // جلب بيانات كشف الحساب
        $reportData = $this->getWarehouseStatementData($request, $startDate, $endDate);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'html' => view('stock::report.stock_report.partials.warehouse_statement_table', compact('reportData'))->render(),
                'totals' => $reportData['totals'],
                'pagination' => isset($reportData['data'][0]['operations']) ? (string) $reportData['data'][0]['operations']->links() : '',
            ]);
        }

        return view('stock::report.stock_report.warehouse_balance', compact(
            'products', 'categories', 'warehouses', 'accounts', 'reportData', 'startDate', 'endDate', 'period'
        ));
    }

    /**
     * معالجة نطاق التاريخ
     */
    private function getDateRange($period, Request $request)
    {
        $today = now();

        /*
            الفترة المحاسبية تسبق كل شيء.

            قائمة المدى هنا كانت أعداد أيام — ٧، ٣٠، ٩٠ — لا صلة لها
            بالفترة التي تعمل المنشأة عليها. ووسيط `accounting.period`
            يكون قد حوّل `period_id` إلى تاريخين، فيكفي احترامُهما مهما
            كان المختار في قائمة الأيام.
        */
        /*
            **الفترة المحاسبية تسبق كل شيء — والنطاقُ الافتراضي منها.**

            كان الرجوعُ عند غياب التاريخين إلى «آخر N يوماً» (٧ أو ٣٠ أو ٩٠) —
            مدًى لا صلة له بالفترة التي تعمل المنشأة عليها، فيُقرأ تقريرُ
            الحركة على نافذةٍ لا توافق أيَّ إقفال.

            و`stockReportScope` هي القاعدةُ نفسُها في المالية: مدًى كتبه
            المستخدم يتقدّم، وإلّا فالسنةُ المالية الجارية، وإلّا — إن لم يكن
            ثمّة تقويم — فلا حصر.
        */
        $scope = $this->stockReportScope($request, 'start_date', 'end_date');

        if ($scope['mode'] !== 'all' && $scope['start'] && $scope['end']) {
            return [
                'from' => Carbon::parse($scope['start'])->startOfDay(),
                'to' => Carbon::parse($scope['end'])->endOfDay(),
            ];
        }

        /*
            لا تقويمَ معرَّفاً: يُعرض التاريخُ كلُّه بدل أن يُحصَر بمعيارٍ لا
            وجود له. والحدُّ الأدنى تاريخُ أوّل حركةٍ عملياً.
        */
        if ($scope['mode'] === 'all') {
            return [
                'from' => Carbon::parse('1970-01-01')->startOfDay(),
                'to' => $today->copy()->endOfDay(),
            ];
        }
        if ($period === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            return [
                'from' => Carbon::parse($request->start_date)->startOfDay(),
                'to' => Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        $periodDays = is_numeric($period) ? (int) $period : 30;
        if ($periodDays == 1) {
            return [
                'from' => $today->copy()->startOfDay(),
                'to' => $today->copy()->endOfDay(),
            ];
        }

        return [
            'from' => $today->copy()->subDays($periodDays)->startOfDay(),
            'to' => $today->copy()->endOfDay(),
        ];
    }

    /**
     * جلب بيانات كشف حساب المستودع(ات) من القيود المحاسبية
     */
    private function getWarehouseStatementData(Request $request, $startDate, $endDate)
    {
        try {
            // تحديد المستودعات - إذا لم يتم اختيار مستودع معين، نجلب مستودعات نطاقي كلّها
            $requestedWarehouseId = $request->input('warehouse');
            if ($requestedWarehouseId) {
                $this->authorizeWarehouseScope((int) $requestedWarehouseId);
            }

            // جلب المستودعات مع حساباتها
            $scopedWarehouseIds = $this->allowedWarehouseIds();
            $warehousesQuery = StoreHouse::query()
                ->when($scopedWarehouseIds !== null, fn ($q) => $q->whereIn('id', $scopedWarehouseIds ?: [0]));

            if ($requestedWarehouseId) {
                $warehousesQuery->where('id', $requestedWarehouseId);
            }

            $warehouses = $warehousesQuery->orderBy('name')->get();

            if ($warehouses->isEmpty()) {
                return [
                    'data' => [],
                    'totals' => [
                        'total_warehouses' => 0,
                        'total_in' => 0,
                        'total_out' => 0,
                        'current_balance' => 0,
                    ],
                ];
            }

            $reportData = [];
            $grandTotals = [
                'total_warehouses' => 0,
                'total_in' => 0,
                'total_out' => 0,
                'current_balance' => 0,
            ];

            foreach ($warehouses as $warehouse) {
                // البحث عن الحساب المرتبط بالمستودع من خلال storehouse_id في جدول الحسابات
                $account = Account::where('storehouse_id', $warehouse->id)->first();

                if (! $account) {
                    continue; // تجاوز المستودعات التي لا تحتوي على حساب محاسبي مرتبط
                }

                // 1. حساب الرصيد الافتتاحي (قبل تاريخ البداية)
                $openingBalance = $this->calculateOpeningBalance($account->id, $startDate);

                // بناء الاستعلام للقيود المحاسبية
                $journalEntriesQuery = JournalEntryDetail::where('account_id', $account->id)
                    ->with(['journalEntry', 'journalEntry.createdByEmployee:id,name']);

                // تطبيق فلاتر التاريخ (للمعاملات خلال الفترة فقط)
                if ($startDate) {
                    $journalEntriesQuery->whereHas('journalEntry', function ($q) use ($startDate) {
                        $q->whereDate('date', '>=', $startDate);
                    });
                }

                if ($endDate) {
                    $journalEntriesQuery->whereHas('journalEntry', function ($q) use ($endDate) {
                        $q->whereDate('date', '<=', $endDate);
                    });
                }

                // تطبيق فلاتر المنتجات والتصنيفات
                if ($request->filled('product') || $request->filled('category')) {
                    $journalEntriesQuery->whereHas('journalEntry', function ($q) use ($request) {
                        $q->where(function ($sub) use ($request) {
                            $sub->whereHas('purchaseInvoice.items', function ($pi) use ($request) {
                                if ($request->filled('product')) {
                                    $pi->where('product_id', $request->product);
                                }
                                if ($request->filled('category')) {
                                    $pi->whereHas('product', fn ($p) => $p->where('category_id', $request->category));
                                }
                            })
                                ->orWhereHas('invoice.items', function ($si) use ($request) {
                                    if ($request->filled('product')) {
                                        $si->where('product_id', $request->product);
                                    }
                                    if ($request->filled('category')) {
                                        $si->whereHas('product', fn ($p) => $p->where('category_id', $request->category));
                                    }
                                })
                                ->orWhereHas('warehousePermits.items', function ($wp) use ($request) {
                                    if ($request->filled('product')) {
                                        $wp->where('product_id', $request->product);
                                    }
                                    if ($request->filled('category')) {
                                        $wp->whereHas('product', fn ($p) => $p->where('category_id', $request->category));
                                    }
                                });
                        });
                    });
                }

                // البحث النصي (وصف القيد أو رقم المرجع)
                if ($request->filled('search')) {
                    $search = $request->search;
                    $journalEntriesQuery->where(function ($q) use ($search) {
                        $q->where('description', 'like', "%{$search}%")
                            ->orWhereHas('journalEntry', function ($je) use ($search) {
                                $je->where('reference_number', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%");
                            });
                    });
                }

                // ترتيب حسب التاريخ من الأقدم إلى الأحدث
                $journalEntries = $journalEntriesQuery
                    ->orderBy('created_at', 'asc')
                    ->get();

                // حساب العمليات مع تمرير الرصيد الافتتاحي
                $operationsData = $this->buildStatementOperations($account, $journalEntries, $openingBalance);
                $operations = $operationsData['operations'];
                $finalBalance = $operationsData['final_balance'];

                // الترقيم
                $perPage = $request->input('per_page', 20);
                $currentPage = $request->query('page', 1);
                $operationsPaginator = new LengthAwarePaginator(
                    $operations->forPage($currentPage, $perPage)->values(),
                    $operations->count(),
                    $perPage,
                    $currentPage,
                    [
                        'path' => $request->url(),
                        'query' => $request->query(),
                    ]
                );

                // حساب إجماليات الفترة
                $totalDebit = $journalEntries->sum('debit');
                $totalCredit = $journalEntries->sum('credit');

                $companyName = config('app.name', 'الشركة');

                $reportData[] = [
                    'warehouse' => $warehouse,
                    'account' => $account,
                    'openingBalance' => $openingBalance,
                    'operations' => $operationsPaginator,
                    'finalBalance' => $finalBalance,
                    'totalDebit' => $totalDebit,
                    'totalCredit' => $totalCredit,
                    'companyName' => $companyName,
                ];

                // تحديث الإجماليات العامة
                $grandTotals['total_warehouses']++;
                $grandTotals['total_in'] += $totalDebit;
                $grandTotals['total_out'] += $totalCredit;
                $grandTotals['current_balance'] += $finalBalance;
            }

            return [
                'data' => $reportData,
                'totals' => $grandTotals,
            ];

        } catch (\Exception $e) {
            Log::error('WarehouseStatement Error: '.$e->getMessage());

            return [
                'data' => [],
                'totals' => [
                    'total_warehouses' => 0,
                    'total_in' => 0,
                    'total_out' => 0,
                    'current_balance' => 0,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * حساب الرصيد الافتتاحي قبل تاريخ معين
     */
    private function calculateOpeningBalance($accountId, $startDate)
    {
        if (! $startDate) {
            return 0;
        }

        $account = Account::find($accountId);
        if (! $account) {
            return 0;
        }

        $balanceType = $account->balance_type ?? 'debit';

        $totals = JournalEntryDetail::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($startDate) {
                $q->whereDate('date', '<', $startDate);
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return ($balanceType === 'debit') ? ($debit - $credit) : ($credit - $debit);
    }

    /**
     * بناء قائمة العمليات مع الأرصدة الجارية
     */
    private function buildStatementOperations($account, $journalEntries, $openingBalance = 0)
    {
        $operations = collect();
        $balanceType = $account->balance_type ?? 'debit'; // المستودعات عادة مدينون

        $running = (float) $openingBalance;
        $processed = [];

        // إضافة سطر الرصيد الافتتاحي كأول عملية (افتراضياً)
        $processed[] = [
            'id' => null,
            'date' => null,
            'reference_number' => null,
            'description' => 'رصيد أول المدة',
            'debit' => 0,
            'credit' => 0,
            'balance' => $running,
            'journal_entry_id' => null,
            'user' => '-',
            'is_opening' => true,
        ];

        foreach ($journalEntries as $entry) {
            $debit = (float) ($entry->debit ?? 0);
            $credit = (float) ($entry->credit ?? 0);

            if ($balanceType === 'debit') {
                $running += ($debit - $credit);
            } else {
                $running += ($credit - $debit);
            }

            $processed[] = [
                'id' => $entry->id,
                'date' => $entry->journalEntry->date,
                'reference_number' => $entry->journalEntry->reference_number,
                'description' => $entry->description ?? $entry->journalEntry->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
                'journal_entry_id' => $entry->journal_entry_id,
                'user' => $entry->journalEntry->createdByEmployee ? $entry->journalEntry->createdByEmployee->name : '-',
                'is_opening' => false,
            ];
        }

        // إرجاع العمليات بالترتيب من الأحدث إلى الأقدم (ليظهر الرصيد النهائي في الأعلى أو العكس حسب رغبة العميل)
        // في العادة كشف الحساب يظهر الرصيد المتراكم من الأقدم للحدث، ولكن العرض يكون من الأحدث للأقدم
        foreach (array_reverse($processed) as $item) {
            $operations->push($item);
        }

        return [
            'operations' => $operations,
            'final_balance' => $running,
        ];
    }

    /**
     * عرض تفاصيل قيد محاسبي
     */
    public function showJournalEntryDetails($journalEntryId)
    {
        try {
            $journalEntry = JournalEntry::with('details.account')
                ->findOrFail($journalEntryId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $journalEntry->id,
                    'date' => $journalEntry->date,
                    'reference_number' => $journalEntry->reference_number,
                    'description' => $journalEntry->description,
                    'status' => $journalEntry->status,
                    'details' => $journalEntry->details->map(function ($detail) {
                        return [
                            'account_id' => $detail->account_id,
                            'account_name' => $detail->account->name,
                            'account_code' => $detail->account->code,
                            'description' => $detail->description,
                            'debit' => $detail->debit,
                            'credit' => $detail->credit,
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'showJournalEntryDetails');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }
}
