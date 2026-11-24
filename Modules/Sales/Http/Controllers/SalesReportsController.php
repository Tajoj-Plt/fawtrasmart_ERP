<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Category;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\PaymentsProcess;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StoreHouse;
use App\Models\Treasury;
use App\Models\User;
use App\Support\AccountingSettings;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Controllers\Report\SalesReportTrait;
use Modules\Sales\Support\SalesLedger;

class SalesReportsController extends Controller
{
    use SalesReportTrait;

    public function __construct()
    {
        $this->middleware('permission:sales.reports.by_employee', ['only' => ['byEmployee', 'byEmployeeAjax']]);
        $this->middleware('permission:sales.reports.by_product', ['only' => ['byProduct', 'byProductReportAjax']]);
        $this->middleware('permission:sales.reports.by_item', ['only' => ['byItemReport', 'byItemReportAjax']]);
        $this->middleware('permission:sales.reports.payments_by_employee', ['only' => ['employeePaymentsReceiptsReport', 'employeePaymentsReceiptsReportAjax']]);
        $this->middleware('permission:sales.reports.payment_methods', ['only' => ['paymentMethodReport', 'paymentMethodReportAjax']]);
        $this->middleware('permission:sales.reports.profits', ['only' => ['employeeComprehensiveProfitsReport', 'employeeComprehensiveProfitsReportAjax']]);
        $this->middleware('permission:sales.reports.periods', ['only' => ['salesByPeriod', 'paymentsByPeriod']]);

        /*
            لوحةُ التقارير نفسُها — كانت الدالّة الوحيدة الباقية بلا حراسة.
            تُرِكت سابقاً بحجّة «لا صلاحيةَ لها في الكتالوج»، وذلك لم يعد
            صحيحاً: `sales.reports.view` معرَّفةٌ في مجموعة `reports`
            وهي الصلاحية نفسُها التي يفحصها رابطُ القائمة الجانبية — فكان
            الرابط محروساً والعنوانُ مفتوحاً لمن يكتبه.
        */
        $this->middleware('permission:sales.reports.view', ['only' => ['index']]);
    }

    // تقارير المبيعات
    public function index()
    {
        return view('sales::sals_report.index');
    }

    public function byEmployee(Request $request)
    {
        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $branches = Branch::all();
        $categories = CategoriesClient::all();
        $clients = Client::with('branch')->get();
        $users = $employees;

        $fromDate = $request->input('date_from') ? Carbon::parse($request->input('date_from')) : now()->subMonth();
        $toDate = $request->input('date_to') ? Carbon::parse($request->input('date_to')) : now();

        $isSummary = $request->input('view_type', 'summary') !== 'details';

        // جلب البيانات من القيود المحاسبية
        $journalEntries = JournalEntry::query()
            ->whereIn('reference_type', ['SI', 'SR', 'DN', 'CN']) // أنواع المبيعات
            ->whereBetween('date', [$fromDate, $toDate])
            ->with(['details.account', 'client', 'createdByEmployee', 'branch', 'payments']);

        // تطبيق الفلاتر
        if ($request->filled('employee_id')) {
            $journalEntries->where('created_by_employee', $request->employee_id);
        }
        if ($request->filled('client_id')) {
            $journalEntries->where('client_id', $request->client_id);
        }
        if ($request->filled('branch_id')) {
            $journalEntries->where('branch_id', $request->branch_id);
        }
        if ($request->filled('category_id')) {
            $journalEntries->whereHas('client', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        $entries = $journalEntries->get();

        // معالجة البيانات
        $reportData = collect();
        $totals = [
            'count' => 0,
            'amount' => 0,
            'paid' => 0,
            'unpaid' => 0,
        ];

        if ($entries->isNotEmpty()) {
            if ($isSummary) {
                // الملخص
                $reportData = $entries->groupBy('created_by_employee')->map(function ($group) {
                    return [
                        'name' => $group->first()->createdByEmployee->name ?? 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum(function ($entry) {
                            return $entry->details->sum('debit');
                        }),
                        'paid' => $group->sum(function ($entry) {
                            return $entry->payments->sum('amount') ?? 0;
                        }),
                        'unpaid' => max($group->sum(function ($entry) {
                            return $entry->details->sum('debit');
                        }) - $group->sum(function ($entry) {
                            return $entry->payments->sum('amount') ?? 0;
                        }), 0),
                    ];
                });
            } else {
                // التفاصيل
                $reportData = $entries->groupBy('created_by_employee')->map(function ($group) {
                    return [
                        'name' => $group->first()->createdByEmployee->name ?? 'غير محدد',
                        'count' => $group->count(),
                        'amount' => $group->sum(function ($entry) {
                            return $entry->details->sum('debit');
                        }),
                        'paid' => $group->sum(function ($entry) {
                            return $entry->payments->sum('amount') ?? 0;
                        }),
                        'unpaid' => max($group->sum(function ($entry) {
                            return $entry->details->sum('debit');
                        }) - $group->sum(function ($entry) {
                            return $entry->payments->sum('amount') ?? 0;
                        }), 0),
                        'items' => $group->map(function ($entry) {
                            $debit = $entry->details->sum('debit');
                            $paid = $entry->payments->sum('amount') ?? 0;

                            return [
                                'id' => $entry->id,
                                'reference_number' => $entry->reference_number,
                                'date' => Carbon::parse($entry->date)->format('d/m/Y'),
                                'employee_name' => $entry->createdByEmployee->name ?? '-',
                                'client_name' => $entry->client->trade_name ?? '-',
                                'reference_type' => $entry->reference_type,
                                'branch_name' => $entry->branch->name ?? '-',
                                'debit' => $debit,
                                'credit' => $entry->details->sum('credit'),
                                'paid' => $paid,
                                'unpaid' => max($debit - $paid, 0),
                            ];
                        })->toArray(),
                    ];
                });
            }

            // حساب الإجماليات
            $totals = [
                'count' => $entries->count(),
                'amount' => $entries->sum(function ($entry) {
                    return $entry->details->sum('debit');
                }),
                'paid' => $entries->sum(function ($entry) {
                    return $entry->payments->sum('amount') ?? 0;
                }),
                'unpaid' => max($entries->sum(function ($entry) {
                    return $entry->details->sum('debit');
                }) - $entries->sum(function ($entry) {
                    return $entry->payments->sum('amount') ?? 0;
                }), 0),
            ];
        }

        return view('sales::sals_report.salesRport.Sales_By_Employee', compact(
            'reportData',
            'employees',
            'clients',
            'categories',
            'branches',
            'users',
            'totals',
            'fromDate',
            'toDate',
            'isSummary'
        ));
    }

    /**
     * دالة AJAX لجلب بيانات التقرير
     */
    public function byEmployeeAjax(Request $request)
    {
        try {
            // 1. التحقق من صحة البيانات المدخلة
            $validatedData = $request->validate([
                'category' => 'nullable|exists:categories_clients,id',
                'client' => 'nullable|exists:clients,id',
                'branch' => 'nullable|exists:branches,id',
                'status' => 'nullable|in:0,1,2,3,5',
                'invoice_type' => 'nullable|in:normal,returned',
                'order_origin' => 'nullable|exists:employees,id',
                'added_by' => 'nullable|exists:users,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'report_type' => 'nullable|in:daily,weekly,monthly,yearly,sales_manager,employee,returns',
                'date_type' => 'nullable|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom',
            ]);

            // 2. تحديد نطاق التاريخ بناءً على نوع التاريخ المحدد
            $fromDate = now()->subMonth();
            $toDate = now();

            if ($request->filled('date_type') && $request->date_type !== 'custom') {
                $dateRange = $this->getDateRange($request->date_type);
                $fromDate = $dateRange['from'];
                $toDate = $dateRange['to'];
            } elseif ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDate = Carbon::parse($request->from_date);
                $toDate = Carbon::parse($request->to_date);
            } elseif ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date);
            } elseif ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date);
            }

            // 3. بناء استعلام الفواتير مع العلاقات
            $invoices = Invoice::with(['client.branch', 'createdByUser', 'employee'])
                ->orderBy('invoice_date', 'desc')
                ->orderBy('created_at', 'desc');

            $invoices->whereNotIn('id', function ($query) {
                $query
                    ->select('reference_number')
                    ->from('invoices')
                    ->whereIn('type', ['normal', 'returned'])
                    ->whereNotNull('reference_number');
            });

            // 4. تطبيق الفلاتر
            // فلتر فئة العميل
            if ($request->filled('category')) {
                $invoices->whereHas('client', function ($query) use ($request) {
                    $query->where('category_id', $request->category);
                });
            }

            // فلتر العميل
            if ($request->filled('client')) {
                $invoices->where('client_id', $request->client);
            }

            // فلتر الفرع
            if ($request->filled('branch')) {
                $invoices->whereHas('client', function ($query) use ($request) {
                    $query->where('branch_id', $request->branch);
                });
            }

            // فلتر حالة الدفع
            if ($request->filled('status')) {
                $invoices->where('payment_status', $request->status);
            }

            // فلتر نوع الفاتورة
            if ($request->filled('invoice_type')) {
                if ($request->invoice_type === 'normal') {
                    $invoices->whereNotIn('type', ['return', 'returned']);
                } else {
                    $invoices->whereIn('type', ['return', 'returned']);
                }
            }

            // فلتر الموظف
            if ($request->filled('order_origin')) {
                $invoices->where(function ($query) use ($request) {
                    $query->where('employee_id', $request->order_origin)->orWhere('created_by', $request->order_origin);
                });
            }

            // فلتر المضيف بواسطة
            if ($request->filled('added_by')) {
                $invoices->where('created_by', $request->added_by);
            }

            // فلتر نطاق التاريخ
            $invoices->whereBetween('invoice_date', [$fromDate, $toDate]);

            // فلتر نوع التقرير
            if ($request->filled('report_type')) {
                switch ($request->report_type) {
                    case 'yearly':
                        $invoices->whereYear('invoice_date', $toDate->year);
                        break;
                    case 'monthly':
                        $invoices->whereMonth('invoice_date', $toDate->month)->whereYear('invoice_date', $toDate->year);
                        break;
                    case 'weekly':
                        $invoices->whereBetween('invoice_date', [$toDate->copy()->startOfWeek(), $toDate->copy()->endOfWeek()]);
                        break;
                    case 'daily':
                        $invoices->whereDate('invoice_date', $toDate->toDateString());
                        break;
                    case 'sales_manager':
                        $invoices->whereHas('createdByUser', function ($query) {
                            $query->whereHas('roles', function ($q) {
                                $q->where('name', 'sales_manager');
                            });
                        });
                        break;
                    case 'employee':
                        $invoices->whereHas('createdByUser', function ($query) {
                            $query->whereHas('roles', function ($q) {
                                $q->where('name', 'employee');
                            });
                        });
                        break;
                    case 'returns':
                        $invoices->whereIn('type', ['return', 'returned']);
                        break;
                }
            }

            // 5. الحصول على النتائج
            $invoices = $invoices->get();

            // 6. معالجة البيانات وتجميعها
            $groupedInvoices = collect();
            $totals = [
                'paid_amount' => 0,
                'unpaid_amount' => 0,
                'returned_amount' => 0,
                'total_amount' => 0,
                'total_sales' => 0,
                'total_returns' => 0,
            ];

            if ($invoices->isNotEmpty()) {
                // تجميع الفواتير حسب الموظف
                $groupedInvoices = $invoices->groupBy(function ($invoice) {
                    return $invoice->employee_id ?? $invoice->created_by;
                });

                // حساب الإجماليات العامة
                foreach ($invoices as $invoice) {
                    $isReturn = in_array($invoice->type, ['return', 'returned']);

                    if ($isReturn) {
                        $totals['returned_amount'] += $invoice->grand_total;
                        $totals['total_returns'] += $invoice->grand_total;
                        $totals['total_amount'] -= $invoice->grand_total;
                    } else {
                        $totals['total_sales'] += $invoice->grand_total;
                        $totals['total_amount'] += $invoice->grand_total;

                        if ($invoice->payment_status == 1) {
                            $totals['paid_amount'] += $invoice->grand_total;
                        } else {
                            $totals['paid_amount'] += $invoice->paid_amount;
                            $totals['unpaid_amount'] += $invoice->due_value;
                        }
                    }
                }

                // تحويل المجموعات إلى array للإرسال عبر JSON
                $groupedInvoicesArray = [];
                foreach ($groupedInvoices as $employeeId => $employeeInvoices) {
                    $groupedInvoicesArray[$employeeId] = $employeeInvoices
                        ->map(function ($invoice) {
                            return [
                                'id' => $invoice->id,
                                'code' => $invoice->code,
                                'invoice_date' => $invoice->invoice_date,
                                'type' => $invoice->type,
                                'grand_total' => $invoice->grand_total,
                                'paid_amount' => $invoice->paid_amount,
                                'due_value' => $invoice->due_value,
                                'payment_status' => $invoice->payment_status,
                                'client' => $invoice->client
                                    ? [
                                        'id' => $invoice->client->id,
                                        'trade_name' => $invoice->client->trade_name,
                                    ]
                                    : null,
                                'created_by_user' => $invoice->createdByUser
                                    ? [
                                        'id' => $invoice->createdByUser->id,
                                        'name' => $invoice->createdByUser->name,
                                    ]
                                    : null,
                                'employee' => $invoice->employee
                                    ? [
                                        'id' => $invoice->employee->id,
                                        'name' => $invoice->employee->name,
                                    ]
                                    : null,
                            ];
                        })
                        ->toArray();
                }
            } else {
                $groupedInvoicesArray = [];
            }

            // 7. إرجاع البيانات كـ JSON
            return response()->json([
                'success' => true,
                'grouped_invoices' => $groupedInvoicesArray,
                'totals' => $totals,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
                'invoice_count' => $invoices->count(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesReports', 'byEmployeeAjax');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    private function getDateRange($dateType)
    {
        $today = now();

        switch ($dateType) {
            case 'today':
                return [
                    'from' => $today->copy()->startOfDay(),
                    'to' => $today->copy()->endOfDay(),
                ];

            case 'yesterday':
                $yesterday = $today->copy()->subDay();

                return [
                    'from' => $yesterday->copy()->startOfDay(),
                    'to' => $yesterday->copy()->endOfDay(),
                ];

            case 'this_week':
                return [
                    'from' => $today->copy()->startOfWeek(),
                    'to' => $today->copy()->endOfWeek(),
                ];

            case 'last_week':
                return [
                    'from' => $today->copy()->subWeek()->startOfWeek(),
                    'to' => $today->copy()->subWeek()->endOfWeek(),
                ];

            case 'this_month':
                return [
                    'from' => $today->copy()->startOfMonth(),
                    'to' => $today->copy()->endOfMonth(),
                ];

            case 'last_month':
                return [
                    'from' => $today->copy()->subMonth()->startOfMonth(),
                    'to' => $today->copy()->subMonth()->endOfMonth(),
                ];

            case 'this_year':
                return [
                    'from' => $today->copy()->startOfYear(),
                    'to' => $today->copy()->endOfYear(),
                ];

            case 'last_year':
                return [
                    'from' => $today->copy()->subYear()->startOfYear(),
                    'to' => $today->copy()->subYear()->endOfYear(),
                ];

            default:
                return [
                    'from' => $today->copy()->subMonth(),
                    'to' => $today,
                ];
        }
    }

    public function byProduct(Request $request)
    {
        // 1. الحصول على البيانات الأساسية للقوائم المنسدلة
        $products = Product::all();
        $categories = Category::all();
        $branches = Branch::all();
        $client_categories = CategoriesClient::all();
        $clients = Client::with('branch')->get();
        $users = User::where('role', 'employee')->get();
        $storehouses = StoreHouse::all();

        // 2. تحديد التواريخ الافتراضية
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : now()->subMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : now();

        // 3. إعداد البيانات الافتراضية الفارغة
        $totals = [
            'total_quantity' => 0,
            'total_amount' => 0,
            'total_discount' => 0,
            'total_sales' => 0,
            'total_returns' => 0,
            'total_invoices' => 0,
        ];

        $groupedProducts = collect();
        $productTotals = collect();
        $chartData = ['labels' => [], 'quantities' => [], 'amounts' => []];

        // 4. إرجاع العرض
        return view('sales::sals_report.salesRport.by_Product', compact('groupedProducts', 'products', 'categories', 'clients', 'client_categories', 'branches', 'storehouses', 'totals', 'chartData', 'fromDate', 'toDate', 'productTotals', 'users'));
    }

    /**
     * دالة AJAX لجلب بيانات تقرير المنتجات
     */
    public function byProductReportAjax(Request $request)
    {
        try {
            // 1. التحقق من صحة البيانات المدخلة
            $validatedData = $request->validate([
                'product' => 'nullable|exists:products,id',
                'category' => 'nullable|exists:categories,id',
                'client' => 'nullable|exists:clients,id',
                'client_category' => 'nullable|exists:categories_clients,id',
                'branch' => 'nullable|exists:branches,id',
                'storehouse' => 'nullable|exists:store_houses,id',
                'status' => 'nullable|in:0,1,2,3,5',
                'invoice_type' => 'nullable|in:normal,returned',
                'added_by' => 'nullable|exists:users,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'report_type' => 'nullable|in:daily,weekly,monthly,yearly,sales_manager,employee,returns',
                'date_type' => 'nullable|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom',
            ]);

            // 2. تحديد نطاق التاريخ بناءً على نوع التاريخ المحدد
            $fromDate = now()->subMonth();
            $toDate = now();

            if ($request->filled('date_type') && $request->date_type !== 'custom') {
                $dateRange = $this->getDateRange($request->date_type);
                $fromDate = $dateRange['from'];
                $toDate = $dateRange['to'];
            } elseif ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDate = Carbon::parse($request->from_date);
                $toDate = Carbon::parse($request->to_date);
            } elseif ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date);
            } elseif ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date);
            }

            // 3. بناء استعلام عناصر الفواتير مع العلاقات
            $invoiceItems = InvoiceItem::with([
                'product.category',
                'invoice' => function ($q) {
                    $q->with(['client.branch', 'createdByUser']);
                },
                'storeHouse',
            ])
                ->whereHas('invoice', function ($query) use ($fromDate, $toDate) {
                    $query
                        ->whereNotIn('id', function ($subQuery) {
                            $subQuery
                                ->select('reference_number')
                                ->from('invoices')
                                ->whereIn('type', ['normal', 'returned'])
                                ->whereNotNull('reference_number');
                        })
                        ->whereBetween('invoice_date', [$fromDate, $toDate]);
                })
                ->orderBy('created_at', 'desc');

            // 4. تطبيق الفلاتر
            // فلتر المنتج
            if ($request->filled('product')) {
                $invoiceItems->where('product_id', $request->product);
            }

            // فلتر فئة المنتج
            if ($request->filled('category')) {
                $invoiceItems->whereHas('product', function ($query) use ($request) {
                    $query->where('category_id', $request->category);
                });
            }

            // فلتر فئة العميل
            if ($request->filled('client_category')) {
                $invoiceItems->whereHas('invoice.client', function ($query) use ($request) {
                    $query->where('category_id', $request->client_category);
                });
            }

            // فلتر العميل
            if ($request->filled('client')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    $query->where('client_id', $request->client);
                });
            }

            // فلتر الفرع
            if ($request->filled('branch')) {
                $invoiceItems->whereHas('invoice.client', function ($query) use ($request) {
                    $query->where('branch_id', $request->branch);
                });
            }

            // فلتر المخزن
            if ($request->filled('storehouse')) {
                $invoiceItems->where('store_house_id', $request->storehouse);
            }

            // فلتر حالة الدفع
            if ($request->filled('status')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    $query->where('payment_status', $request->status);
                });
            }

            // فلتر نوع الفاتورة
            if ($request->filled('invoice_type')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    if ($request->invoice_type === 'normal') {
                        $query->whereNotIn('type', ['return', 'returned']);
                    } else {
                        $query->whereIn('type', ['return', 'returned']);
                    }
                });
            }

            // فلتر المضيف بواسطة
            if ($request->filled('added_by')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    $query->where('created_by', $request->added_by);
                });
            }

            // فلتر نوع التقرير
            if ($request->filled('report_type')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request, $toDate) {
                    switch ($request->report_type) {
                        case 'yearly':
                            $query->whereYear('invoice_date', $toDate->year);
                            break;
                        case 'monthly':
                            $query->whereMonth('invoice_date', $toDate->month)->whereYear('invoice_date', $toDate->year);
                            break;
                        case 'weekly':
                            $query->whereBetween('invoice_date', [$toDate->copy()->startOfWeek(), $toDate->copy()->endOfWeek()]);
                            break;
                        case 'daily':
                            $query->whereDate('invoice_date', $toDate->toDateString());
                            break;
                        case 'sales_manager':
                            $query->whereHas('createdByUser', function ($q) {
                                $q->whereHas('roles', function ($role) {
                                    $role->where('name', 'sales_manager');
                                });
                            });
                            break;
                        case 'employee':
                            $query->whereHas('createdByUser', function ($q) {
                                $q->whereHas('roles', function ($role) {
                                    $role->where('name', 'employee');
                                });
                            });
                            break;
                        case 'returns':
                            $query->whereIn('type', ['return', 'returned']);
                            break;
                    }
                });
            }

            // 5. الحصول على النتائج
            $invoiceItems = $invoiceItems->get();

            // 6. معالجة البيانات وتجميعها
            $groupedProducts = collect();
            $totals = [
                'total_quantity' => 0,
                'total_amount' => 0,
                'total_discount' => 0,
                'total_sales' => 0,
                'total_returns' => 0,
                'total_invoices' => 0,
            ];

            if ($invoiceItems->isNotEmpty()) {
                // تجميع العناصر حسب المنتج
                $groupedProducts = $invoiceItems->groupBy('product_id');

                // حساب الإجماليات العامة
                foreach ($invoiceItems as $item) {
                    $isReturn = in_array($item->invoice->type, ['return', 'returned']);
                    $itemTotal = $item->quantity * $item->unit_price;

                    $totals['total_quantity'] += $item->quantity;
                    $totals['total_discount'] += $item->discount_amount ?? 0;

                    if ($isReturn) {
                        $totals['total_returns'] += $itemTotal;
                        $totals['total_amount'] -= $itemTotal;
                    } else {
                        $totals['total_sales'] += $itemTotal;
                        $totals['total_amount'] += $itemTotal;
                    }
                }

                $totals['total_invoices'] = $invoiceItems->groupBy('invoice_id')->count();

                // تحويل المجموعات إلى array للإرسال عبر JSON
                $groupedProductsArray = [];
                foreach ($groupedProducts as $productId => $productItems) {
                    $groupedProductsArray[$productId] = $productItems
                        ->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'discount_amount' => $item->discount_amount,
                                'total_amount' => $item->quantity * $item->unit_price,
                                'product' => [
                                    'id' => $item->product->id,
                                    'name' => $item->product->name,
                                    'code' => $item->product->code,
                                    'category' => $item->product->category
                                        ? [
                                            'id' => $item->product->category->id,
                                            'name' => $item->product->category->name,
                                        ]
                                        : null,
                                ],
                                'invoice' => [
                                    'id' => $item->invoice->id,
                                    'code' => $item->invoice->code,
                                    'invoice_date' => $item->invoice->invoice_date,
                                    'type' => $item->invoice->type,
                                    'payment_status' => $item->invoice->payment_status,
                                    'client' => $item->invoice->client
                                        ? [
                                            'id' => $item->invoice->client->id,
                                            'trade_name' => $item->invoice->client->trade_name,
                                        ]
                                        : null,
                                    'created_by_user' => $item->invoice->createdByUser
                                        ? [
                                            'id' => $item->invoice->createdByUser->id,
                                            'name' => $item->invoice->createdByUser->name,
                                        ]
                                        : null,
                                ],
                                'store_house' => $item->storeHouse
                                    ? [
                                        'id' => $item->storeHouse->id,
                                        'name' => $item->storeHouse->name,
                                    ]
                                    : null,
                            ];
                        })
                        ->toArray();
                }
            } else {
                $groupedProductsArray = [];
            }

            // 7. إرجاع البيانات كـ JSON
            return response()->json([
                'success' => true,
                'grouped_products' => $groupedProductsArray,
                'totals' => $totals,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
                'items_count' => $invoiceItems->count(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesReports', 'byProductReportAjax');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    // Helper method to prepare chart data
    protected function prepareChartData1($payments)
    {
        // Group payments by client
        $groupedPayments = $payments->groupBy('clients_id');

        $chartLabels = [];
        $chartValues = [];

        foreach ($groupedPayments as $clientId => $clientPayments) {
            $client = Client::find($clientId);
            $chartLabels[] = $client ? $client->trade_name : "عميل $clientId";
            $chartValues[] = $clientPayments->sum('amount');
        }

        return [
            'labels' => $chartLabels,
            'values' => $chartValues,
        ];
    }

    public function employeePaymentsReceiptsReport(Request $request)
    {
        // 1. الحصول على البيانات الأساسية للقوائم المنسدلة
        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $clients = Client::all();
        $branches = Branch::orderBy('name')->get();
        $accounts = Account::all();
        $treasuries = Treasury::all();
        $paymentMethods = [['id' => 1, 'name' => 'نقدي'], ['id' => 2, 'name' => 'شيك'], ['id' => 3, 'name' => 'تحويل بنكي'], ['id' => 4, 'name' => 'بطاقة ائتمان']];

        // 2. تحديد التواريخ الافتراضية
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : now()->subMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : now();

        // 3. إعداد البيانات الافتراضية الفارغة
        $totals = [
            'total_payments' => 0,
            'total_receipts' => 0,
            'total_amount' => 0,
            'payments_count' => 0,
            'receipts_count' => 0,
            'total_count' => 0,
        ];

        $groupedData = collect();
        $chartData = ['labels' => [], 'payments' => [], 'receipts' => []];

        // 4. إرجاع العرض
        return view('sales::sals_report.payments.employee_report', compact('groupedData', 'employees', 'clients', 'branches', 'accounts', 'treasuries', 'paymentMethods', 'totals', 'chartData', 'fromDate', 'toDate'));
    }

    public function employeePaymentsReceiptsReportAjax(Request $request)
    {
        try {
            // 1. التحقق من صحة البيانات المدخلة
            $validatedData = $request->validate([
                'employee' => 'nullable|exists:users,id',
                'client' => 'nullable|exists:clients,id',
                'branch' => 'nullable|exists:branches,id',
                'account' => 'nullable|exists:accounts,id',
                'treasury' => 'nullable|exists:treasuries,id',
                'payment_method' => 'nullable|in:1,2,3,4',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'date_type' => 'nullable|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom',
                'report_type' => 'nullable|in:payments,receipts,both',
            ]);

            // 2. تحديد نطاق التاريخ بناءً على نوع التاريخ المحدد
            $fromDate = now()->subMonth()->startOfDay();
            $toDate = now()->endOfDay();

            if ($request->filled('date_type') && $request->date_type !== 'custom') {
                $dateRange = $this->getDateRange($request->date_type);
                $fromDate = $dateRange['from'];
                $toDate = $dateRange['to'];
            } elseif ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
                $toDate = Carbon::parse($request->to_date)->endOfDay();
            } elseif ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
            } elseif ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date)->endOfDay();
            }

            // 3. استبعاد الفواتير المرجعة
            $returnedInvoiceIds = Invoice::whereNotNull('reference_number')
                ->pluck('reference_number')->toArray();

            $excludedInvoiceIds = array_unique(array_merge(
                $returnedInvoiceIds,
                Invoice::where('type', 'returned')->pluck('id')->toArray()
            ));

            // 4. جلب بيانات الموظفين
            $employeeQuery = User::whereIn('role', ['employee', 'manager']);

            if ($request->filled('employee')) {
                $employeeQuery->where('id', $request->employee);
            }

            $employees = $employeeQuery->get();

            // 5. معالجة البيانات للموظفين
            $groupedData = collect();

            foreach ($employees as $user) {
                $userId = $user->id;

                // جلب المدفوعات
                $paymentsQuery = PaymentsProcess::with('invoice')
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->whereHas('invoice', function ($q) use ($excludedInvoiceIds) {
                        $q->where('type', 'normal')
                            ->whereNotIn('id', $excludedInvoiceIds);
                    });

                // تطبيق الفلاتر على المدفوعات
                if ($request->filled('client')) {
                    $paymentsQuery->whereHas('invoice', function ($q) use ($request) {
                        $q->where('client_id', $request->client);
                    });
                }

                if ($request->filled('branch')) {
                    $paymentsQuery->whereHas('invoice.client', function ($q) use ($request) {
                        $q->where('branch_id', $request->branch);
                    });
                }

                if ($request->filled('payment_method')) {
                    $paymentsQuery->where('payment_method', $request->payment_method);
                }

                $payments = $paymentsQuery->get();

                // حساب مجموع المدفوعات للموظف - تصفية حسب الموظف الفعلي
                $employeePayments = $payments->filter(function ($payment) use ($userId) {
                    if ($payment->employee_id) {
                        return $payment->employee_id == $userId;
                    }

                    return optional($payment->invoice)->created_by == $userId;
                });

                $paymentsTotal = $employeePayments->sum('amount');

                // جلب سندات القبض
                $receiptsQuery = Receipt::where('created_by', $userId)
                    ->whereBetween('created_at', [$fromDate, $toDate]);

                // تطبيق الفلاتر على سندات القبض
                if ($request->filled('client')) {
                    $receiptsQuery->whereHas('account', function ($q) use ($request) {
                        $q->where('client_id', $request->client);
                    });
                }

                if ($request->filled('branch')) {
                    $receiptsQuery->whereHas('account.client', function ($q) use ($request) {
                        $q->where('branch_id', $request->branch);
                    });
                }

                if ($request->filled('account')) {
                    $receiptsQuery->where('account_id', $request->account);
                }

                if ($request->filled('treasury')) {
                    $receiptsQuery->where('treasury_id', $request->treasury);
                }

                $receiptsTotal = $receiptsQuery->sum('amount');
                $receipts = $receiptsQuery->get();

                $totalAmount = $paymentsTotal + $receiptsTotal;

                // يجب إضافة الموظف حتى لو لم تكن لديه أي عمليات إذا تم تحديده بشكل محدد
                // أو إذا كان لديه عمليات
                if ($totalAmount > 0 || $request->filled('employee')) {
                    $groupedData->put($userId, [
                        'employee' => [
                            'id' => $user->id,
                            'name' => $user->name,
                        ],
                        'payments' => $employeePayments->values(),
                        'receipts' => $receipts,
                        'total_payments' => $paymentsTotal,
                        'total_receipts' => $receiptsTotal,
                        'total_amount' => $totalAmount,
                    ]);
                }
            }

            // 6. جلب البيانات حسب نوع التقرير
            if ($request->filled('report_type') && $request->report_type === 'payments') {
                $groupedData = $groupedData->map(function ($data) {
                    $data['receipts'] = collect();
                    $data['total_receipts'] = 0;
                    $data['total_amount'] = $data['total_payments'];

                    return $data;
                });
            } elseif ($request->filled('report_type') && $request->report_type === 'receipts') {
                $groupedData = $groupedData->map(function ($data) {
                    $data['payments'] = collect();
                    $data['total_payments'] = 0;
                    $data['total_amount'] = $data['total_receipts'];

                    return $data;
                });
            }

            $payments = collect();
            $receipts = collect();

            foreach ($groupedData as $data) {
                $payments = $payments->concat($data['payments']);
                $receipts = $receipts->concat($data['receipts']);
            }

            // 7. حساب الإجماليات العامة
            $totals = [
                'total_payments' => $groupedData->sum('total_payments'),
                'total_receipts' => $groupedData->sum('total_receipts'),
                'total_amount' => $groupedData->sum('total_amount'),
                'payments_count' => $groupedData->sum(function ($data) {
                    return $data['payments']->count();
                }),
                'receipts_count' => $groupedData->sum(function ($data) {
                    return $data['receipts']->count();
                }),
                'total_count' => 0,
            ];

            $totals['total_count'] = $totals['payments_count'] + $totals['receipts_count'];

            // 8. إعداد بيانات الرسم البياني
            // تصفية البيانات لعرض فقط الموظفين الذين عندهم عمليات
            $chartDataFiltered = $groupedData->filter(function ($data) {
                return $data['total_amount'] > 0;
            });

            $chartData = [
                'labels' => $chartDataFiltered->map(function ($data) {
                    return $data['employee']['name'] ?? 'غير محدد';
                })->values()->toArray(),
                'payments' => $chartDataFiltered->map(function ($data) {
                    return $data['total_payments'];
                })->values()->toArray(),
                'receipts' => $chartDataFiltered->map(function ($data) {
                    return $data['total_receipts'];
                })->values()->toArray(),
            ];

            // 9. تحويل البيانات للإرسال عبر JSON
            $groupedDataArray = [];
            foreach ($groupedData as $employeeId => $data) {
                $groupedDataArray[$employeeId] = [
                    'employee' => $data['employee'],
                    'payments' => $data['payments']
                        ->map(function ($payment) {
                            return [
                                'id' => $payment->id,
                                'amount' => $payment->amount,
                                'payment_date' => $payment->created_at->format('Y-m-d'),
                                'payment_method' => $payment->payment_method ?? 'غير محدد',
                                'reference_number' => $payment->reference_number,
                                'notes' => $payment->notes,
                                'invoice' => $payment->invoice
                                    ? [
                                        'id' => $payment->invoice->id,
                                        'code' => $payment->invoice->code,
                                        'client' => $payment->invoice->client
                                            ? [
                                                'id' => $payment->invoice->client->id,
                                                'trade_name' => $payment->invoice->client->trade_name,
                                            ]
                                            : null,
                                    ]
                                    : null,
                            ];
                        })
                        ->toArray(),
                    'receipts' => $data['receipts']
                        ->map(function ($receipt) {
                            return [
                                'id' => $receipt->id,
                                'code' => $receipt->code,
                                'amount' => $receipt->amount,
                                'date' => $receipt->created_at->format('Y-m-d'),
                                'description' => $receipt->description,
                                'client' => $receipt->account && $receipt->account->client
                                    ? [
                                        'id' => $receipt->account->client->id,
                                        'trade_name' => $receipt->account->client->trade_name,
                                    ]
                                    : null,
                                'account' => $receipt->account
                                    ? [
                                        'id' => $receipt->account->id,
                                        'name' => $receipt->account->name,
                                    ]
                                    : null,
                            ];
                        })
                        ->toArray(),
                    'total_payments' => $data['total_payments'],
                    'total_receipts' => $data['total_receipts'],
                    'total_amount' => $data['total_amount'],
                ];
            }

            // 10. إرجاع البيانات كـ JSON
            return response()->json([
                'success' => true,
                'grouped_data' => $groupedDataArray,
                'totals' => $totals,
                'chart_data' => $chartData,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesReports', 'employeePaymentsReceiptsReportAjax');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function paymentMethodReport(Request $request)
    {
        // 1. الحصول على البيانات الأساسية للقوائم المنسدلة
        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $clients = Client::all();
        $branches = Branch::orderBy('name')->get();
        $accounts = Account::all();
        $treasuries = Treasury::all();
        $paymentMethods = [['id' => 1, 'name' => 'نقدي'], ['id' => 2, 'name' => 'شيك'], ['id' => 3, 'name' => 'تحويل بنكي'], ['id' => 4, 'name' => 'بطاقة ائتمان']];

        // 2. تحديد التواريخ الافتراضية
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : now()->subMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : now();

        // 3. إعداد البيانات الافتراضية الفارغة
        $totals = [
            'total_payments' => 0,
            'total_receipts' => 0,
            'total_amount' => 0,
            'payments_count' => 0,
            'receipts_count' => 0,
            'total_count' => 0,
        ];

        $groupedData = collect();
        $chartData = ['labels' => [], 'payments' => [], 'receipts' => []];

        // 4. إرجاع العرض - تأكد من مسار الـ view الصحيح
        return view('sales::sals_report.payments.payment_method_report', compact('groupedData', 'employees', 'clients', 'branches', 'accounts', 'treasuries', 'paymentMethods', 'totals', 'chartData', 'fromDate', 'toDate'));
    }

    public function paymentMethodReportAjax(Request $request)
    {
        try {
            // 1. التحقق من صحة البيانات المدخلة
            $validatedData = $request->validate([
                'employee' => 'nullable|exists:users,id',
                'client' => 'nullable|exists:clients,id',
                'branch' => 'nullable|exists:branches,id',
                'account' => 'nullable|exists:accounts,id',
                'treasury' => 'nullable|exists:treasuries,id',
                'payment_method' => 'nullable|in:1,2,3,4',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'date_type' => 'nullable|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom',
                'report_type' => 'nullable|in:payments,receipts,both',
            ]);

            // 2. تحديد نطاق التاريخ بناءً على نوع التاريخ المحدد
            $fromDate = now()->subMonth();
            $toDate = now();

            if ($request->filled('date_type') && $request->date_type !== 'custom') {
                $dateRange = $this->getDateRange($request->date_type);
                $fromDate = $dateRange['from'];
                $toDate = $dateRange['to'];
            } elseif ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDate = Carbon::parse($request->from_date);
                $toDate = Carbon::parse($request->to_date);
            } elseif ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date);
            } elseif ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date);
            }

            // 3. جلب بيانات المدفوعات
            $paymentsQuery = PaymentsProcess::with(['invoice.client', 'invoice.createdByUser'])
                ->where('type', 'client payments')
                ->whereBetween('payment_date', [$fromDate, $toDate])
                ->whereHas('invoice', function ($q) {
                    $q->whereIn('type', ['normal']);
                });

            // 4. جلب بيانات سندات القبض
            $receiptsQuery = Receipt::with(['account.client', 'user', 'treasury'])
                ->whereBetween('date', [$fromDate, $toDate])
                ->whereHas('account', function ($q) {
                    $q->whereNotNull('client_id');
                });
            // 5. تطبيق الفلاتر على المدفوعات
            if ($request->filled('employee')) {
                $paymentsQuery->whereHas('invoice', function ($q) use ($request) {
                    $q->where('created_by', $request->employee);
                });
            }

            if ($request->filled('client')) {
                $paymentsQuery->where('client_id', $request->client);
            }

            if ($request->filled('branch')) {
                $paymentsQuery->whereHas('invoice.client', function ($q) use ($request) {
                    $q->where('branch_id', $request->branch);
                });
            }

            if ($request->filled('payment_method')) {
                $paymentsQuery->where('payment_method', $request->payment_method);
            }

            // 6. تطبيق الفلاتر على سندات القبض
            if ($request->filled('employee')) {
                $receiptsQuery->where('created_by', $request->employee);
            }

            if ($request->filled('client')) {
                $receiptsQuery->where('client_id', $request->client);
            }

            if ($request->filled('account')) {
                $receiptsQuery->where('account_id', $request->account);
            }

            if ($request->filled('treasury')) {
                $receiptsQuery->where('treasury_id', $request->treasury);
            }

            // 7. جلب البيانات حسب نوع التقرير
            $payments = collect();
            $receipts = collect();

            if (! $request->filled('report_type') || $request->report_type === 'both' || $request->report_type === 'payments') {
                $payments = $paymentsQuery->orderBy('payment_date', 'desc')->get();
            }

            if (! $request->filled('report_type') || $request->report_type === 'both' || $request->report_type === 'receipts') {
                $receipts = $receiptsQuery->orderBy('date', 'desc')->get();
            }

            // 8. معالجة البيانات وتجميعها حسب طريقة الدفع
            $groupedData = collect();
            $paymentMethods = [
                1 => 'نقدي',
                2 => 'شيك',
                3 => 'تحويل بنكي',
                4 => 'بطاقة ائتمان',
            ];

            // تجميع المدفوعات حسب طريقة الدفع
            if ($payments->isNotEmpty()) {
                $groupedPayments = $payments->groupBy('payment_method');

                foreach ($groupedPayments as $methodId => $methodPayments) {
                    if (isset($paymentMethods[$methodId])) {
                        if (! $groupedData->has($methodId)) {
                            $groupedData->put($methodId, [
                                'payment_method' => [
                                    'id' => $methodId,
                                    'name' => $paymentMethods[$methodId],
                                ],
                                'payments' => collect(),
                                'receipts' => collect(),
                                'total_payments' => 0,
                                'total_receipts' => 0,
                                'total_amount' => 0,
                            ]);
                        }
                        $currentData = $groupedData->get($methodId);
                        $currentData['payments'] = $methodPayments;
                        $currentData['total_payments'] = $methodPayments->sum('amount');
                        $groupedData->put($methodId, $currentData);
                    }
                }
            }

            // تجميع سندات القبض حسب نوع الحساب/الخزينة (نعتبرها كطريقة دفع)
            if ($receipts->isNotEmpty()) {
                foreach ($receipts as $receipt) {
                    // نعتبر سندات القبض كطريقة دفع "نقدي" افتراضياً
                    $methodId = 1; // نقدي

                    if (! $groupedData->has($methodId)) {
                        $groupedData->put($methodId, [
                            'payment_method' => [
                                'id' => $methodId,
                                'name' => $paymentMethods[$methodId],
                            ],
                            'payments' => collect(),
                            'receipts' => collect(),
                            'total_payments' => 0,
                            'total_receipts' => 0,
                            'total_amount' => 0,
                        ]);
                    }

                    $currentData = $groupedData->get($methodId);
                    $currentData['receipts']->push($receipt);
                    $groupedData->put($methodId, $currentData);
                }

                // إعادة حساب مجاميع سندات القبض
                foreach ($groupedData as $methodId => $data) {
                    if ($data['receipts']->isNotEmpty()) {
                        $data['total_receipts'] = $data['receipts']->sum('amount');
                        $groupedData->put($methodId, $data);
                    }
                }
            }

            // حساب المجاميع لطرق الدفع
            $groupedDataArray = $groupedData->toArray();
            foreach ($groupedDataArray as $methodId => &$data) {
                $data['total_amount'] = $data['total_payments'] + $data['total_receipts'];
            }
            $groupedData = collect($groupedDataArray);

            // 9. حساب الإجماليات العامة
            $totals = [
                'total_payments' => $payments->sum('amount'),
                'total_receipts' => $receipts->sum('amount'),
                'total_amount' => $payments->sum('amount') + $receipts->sum('amount'),
                'payments_count' => $payments->count(),
                'receipts_count' => $receipts->count(),
                'total_count' => $payments->count() + $receipts->count(),
            ];

            // 10. إعداد بيانات الرسم البياني
            $chartData = [
                'labels' => $groupedData
                    ->map(function ($data) {
                        return $data['payment_method']['name'] ?? 'غير محدد';
                    })
                    ->values()
                    ->toArray(),
                'payments' => $groupedData
                    ->map(function ($data) {
                        return $data['total_payments'];
                    })
                    ->values()
                    ->toArray(),
                'receipts' => $groupedData
                    ->map(function ($data) {
                        return $data['total_receipts'];
                    })
                    ->values()
                    ->toArray(),
            ];

            // 11. تحويل البيانات للإرسال عبر JSON
            $groupedDataArray = [];
            foreach ($groupedData as $methodId => $data) {
                $groupedDataArray[$methodId] = [
                    'payment_method' => [
                        'id' => $data['payment_method']['id'],
                        'name' => $data['payment_method']['name'],
                    ],
                    'payments' => $data['payments']
                        ->map(function ($payment) {
                            return [
                                'id' => $payment->id,
                                'amount' => $payment->amount,
                                'payment_date' => $payment->payment_date,
                                'payment_method' => $payment->payment_method,
                                'reference_number' => $payment->reference_number,
                                'notes' => $payment->notes,
                                'invoice' => $payment->invoice
                                    ? [
                                        'id' => $payment->invoice->id,
                                        'code' => $payment->invoice->code,
                                        'client' => $payment->invoice->client
                                            ? [
                                                'id' => $payment->invoice->client->id,
                                                'trade_name' => $payment->invoice->client->trade_name,
                                            ]
                                            : null,
                                    ]
                                    : null,
                                'employee' => $payment->invoice && $payment->invoice->createdByUser
                                        ? [
                                            'id' => $payment->invoice->createdByUser->id,
                                            'name' => $payment->invoice->createdByUser->name,
                                        ]
                                        : null,
                            ];
                        })
                        ->toArray(),
                    'receipts' => $data['receipts']
                        ->map(function ($receipt) {
                            return [
                                'id' => $receipt->id,
                                'code' => $receipt->code,
                                'amount' => $receipt->amount,
                                'date' => $receipt->date,
                                'description' => $receipt->description,
                                'client' => $receipt->account && $receipt->account->client
                                        ? [
                                            'id' => $receipt->account->client->id,
                                            'trade_name' => $receipt->account->client->trade_name,
                                        ]
                                        : null,
                                'account' => $receipt->account
                                    ? [
                                        'id' => $receipt->account->id,
                                        'name' => $receipt->account->name,
                                    ]
                                    : null,
                                'treasury' => $receipt->treasury
                                    ? [
                                        'id' => $receipt->treasury->id,
                                        'name' => $receipt->treasury->name,
                                    ]
                                    : null,
                                'employee' => $receipt->user
                                    ? [
                                        'id' => $receipt->user->id,
                                        'name' => $receipt->user->name,
                                    ]
                                    : null,
                            ];
                        })
                        ->toArray(),
                    'total_payments' => $data['total_payments'],
                    'total_receipts' => $data['total_receipts'],
                    'total_amount' => $data['total_amount'],
                ];
            }

            // 12. إرجاع البيانات كـ JSON
            return response()->json([
                'success' => true,
                'grouped_data' => $groupedDataArray,
                'totals' => $totals,
                'chart_data' => $chartData,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesReports', 'paymentMethodReportAjax');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * دالة مساعدة لتحديد نطاقات التاريخ بناءً على النوع المحدد
     */

    // دالة مساعدة لتجميع المدفوعات حسب الفترة
    protected function groupPaymentsByPeriod($payments, $period)
    {
        $grouped = [];

        foreach ($payments as $payment) {
            $periodKey = $this->getPeriodKey($payment->payment_date, $period);

            if (! isset($grouped[$periodKey])) {
                $grouped[$periodKey] = [
                    'period_name' => $this->getPeriodName($payment->payment_date, $period),
                    'items' => [],
                    'total_amount' => 0,
                    'count' => 0, // إضافة عداد للمدفوعات
                ];
            }

            $grouped[$periodKey]['items'][] = $payment;
            $grouped[$periodKey]['total_amount'] += $payment->amount;
            $grouped[$periodKey]['count']++; // زيادة العداد
        }

        return [
            'grouped_data' => $grouped,
            'grand_total' => array_sum(array_column($grouped, 'total_amount')),
        ];
    }

    // دالة مساعدة للحصول على مفتاح الفترة
    protected function getPeriodKey($date, $period)
    {
        switch ($period) {
            case 'daily':
                return $date->format('Y-m-d');
            case 'weekly':
                return $date->format('Y-W');
            case 'monthly':
                return $date->format('Y-m');
            case 'yearly':
                return $date->format('Y');
            default:
                return $date->format('Y-m-d');
        }
    }

    // دالة مساعدة للحصول على اسم الفترة
    protected function getPeriodName($date, $period)
    {
        switch ($period) {
            case 'daily':
                return $date->locale('ar')->isoFormat('dddd، LL');
            case 'weekly':
                return 'الأسبوع '.$date->weekOfYear.' ('.$date->startOfWeek()->format('Y-m-d').' إلى '.$date->endOfWeek()->format('Y-m-d').')';
            case 'monthly':
                return $date->locale('ar')->isoFormat('MMMM YYYY');
            case 'yearly':
                return $date->format('Y');
            default:
                return $date->format('Y-m-d');
        }
    }

    /**
     * تقرير أرباح الموظفين
     */
    public function employeeComprehensiveProfitsReport(Request $request)
    {
        // 1. الحصول على البيانات الأساسية للقوائم المنسدلة
        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $clients = Client::orderBy('trade_name')->get();
        $branches = Branch::orderBy('name')->get();
        $products = Product::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        /* الماركة من `brands` عبر `products.brand_id` — لا عمودَ `brand` في
           جدول المنتجات، فكانت الشاشةُ تسقط بـ«Unknown column 'brand'» قبل أن
           تُعرض. معرّفٌ ← اسم، والمُنتقي يرسل المعرّف. */
        $brands = \Illuminate\Support\Facades\DB::table('brands')
            ->whereIn('id', Product::whereNotNull('brand_id')->distinct()->select('brand_id'))
            ->orderBy('name')
            ->pluck('name', 'id');

        // 2. تحديد التواريخ الافتراضية
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : now()->subMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : now();

        // 3. إعداد البيانات الافتراضية الفارغة
        $totals = [
            'total_revenue' => 0,
            'total_cost' => 0,
            'total_profit' => 0,
            'total_profit_margin' => 0,
            'total_transactions' => 0,
            'total_quantity_sold' => 0,
        ];

        $groupedData = collect();
        $chartData = ['labels' => [], 'profits' => [], 'revenues' => [], 'costs' => []];

        // 4. إرجاع العرض
        return view('sales::sals_report.proudect_proifd.employee_profits', compact('groupedData', 'employees', 'clients', 'branches', 'products', 'categories', 'brands', 'totals', 'chartData', 'fromDate', 'toDate'));
    }

    public function employeeComprehensiveProfitsReportAjax(Request $request)
    {
        try {
            // 1. التحقق من صحة البيانات المدخلة
            $validatedData = $request->validate([
                'employee' => 'nullable|exists:users,id',
                'client' => 'nullable|exists:clients,id',
                'branch' => 'nullable|exists:branches,id',
                'product' => 'nullable|exists:products,id',
                'category' => 'nullable|exists:categories,id',
                'brand' => 'nullable|string',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'date_type' => 'nullable|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom',
                'report_type' => 'nullable|in:employee_profits,client_profits,product_profits,comprehensive',
            ]);

            // 2. تحديد نطاق التاريخ بناءً على نوع التاريخ المحدد
            $fromDate = now()->subMonth();
            $toDate = now();

            if ($request->filled('date_type') && $request->date_type !== 'custom') {
                $dateRange = $this->getDateRange($request->date_type);
                $fromDate = $dateRange['from'];
                $toDate = $dateRange['to'];
            } elseif ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDate = Carbon::parse($request->from_date);
                $toDate = Carbon::parse($request->to_date);
            } elseif ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date);
            } elseif ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date);
            }

            // 3. جلب بيانات المبيعات مع التفاصيل
            $salesQuery = InvoiceItem::select(['invoice_items.*', 'invoices.invoice_date', 'invoices.created_by as employee_id', 'invoices.client_id', 'products.name as product_name', 'products.category_id', 'brands.name as brand', 'products.purchase_price', 'users.name as employee_name', 'clients.trade_name as client_name', 'categories.name as category_name'])
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->join('products', 'invoice_items.product_id', '=', 'products.id')
                ->join('users', 'invoices.created_by', '=', 'users.id')
                ->leftJoin('clients', 'invoices.client_id', '=', 'clients.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                // الماركة من جدولها — لا عمودَ `brand` في المنتجات
                ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
                ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
                ->whereIn('invoices.type', ['normal', 'pos']);

            // 4. تطبيق الفلاتر
            if ($request->filled('employee')) {
                $salesQuery->where('invoices.created_by', $request->employee);
            }

            if ($request->filled('client')) {
                $salesQuery->where('invoices.client_id', $request->client);
            }

            if ($request->filled('branch')) {
                $salesQuery->where('invoices.branch_id', $request->branch);
            }

            if ($request->filled('product')) {
                $salesQuery->where('invoice_items.product_id', $request->product);
            }

            if ($request->filled('category')) {
                $salesQuery->where('products.category_id', $request->category);
            }

            if ($request->filled('brand')) {
                // المُنتقي يرسل معرّف الماركة — انظر `employeeComprehensiveProfitsReport()`
                $salesQuery->where('products.brand_id', $request->brand);
            }

            // 5. جلب البيانات وحساب الأرباح
            $salesData = $salesQuery->orderBy('invoices.invoice_date', 'desc')->get();

            /* تكلفةُ البند **بسعر التكلفة** لا بسعر الشراء.

               كانت `الكمية × products.purchase_price`: سعرُ الشراء على بطاقة
               الصنف اليوم، مضروباً في الكمية بوحدة البيع — فكرتونٌ بألف حبّةٍ
               يُكلَّف ثمنَ حبّة، وتغييرُ سعر الشراء غداً يبدّل ربحَ فاتورةِ أمس.

               الآن ما يُقيَّد به القيدُ نفسُه: التكلفةُ المجمَّدة على البند
               لحظةَ البيع (`unit_cost_at_sale`)، وللبنود الأقدم منها المتوسّطُ
               المرجَّح (`Product::averageCost()`) — كلاهما للوحدة الأساسية، فيُضربان
               في الكمية بالوحدة الأساسية. المتوسّطُ يُحسب مرّةً لكل منتج. */
            $averageCosts = Product::whereIn('id', $salesData->pluck('product_id')->filter()->unique())
                ->get()
                ->mapWithKeys(fn (Product $product) => [$product->id => (float) $product->averageCost()]);

            $salesData->each(function ($item) use ($averageCosts) {
                $unitCost = (float) $item->unit_cost_at_sale > 0
                    ? (float) $item->unit_cost_at_sale
                    : (float) ($averageCosts[$item->product_id] ?? 0);

                $item->line_cost = round((float) $item->quantity_in_base_unit * $unitCost, 2);
            });

            // 6. معالجة البيانات وتجميعها حسب نوع التقرير
            $reportType = $request->input('report_type', 'comprehensive');
            $groupedData = $this->processDataByReportType($salesData, $reportType);

            // 7. حساب الإجماليات العامة
            $totals = $this->calculateTotals($salesData);

            // 8. إعداد بيانات الرسم البياني
            $chartData = $this->prepareChartDataProfits($groupedData, $reportType);

            // 9. تحويل البيانات للإرسال عبر JSON
            $groupedDataArray = $this->formatDataForJson($groupedData, $reportType);

            // 10. إرجاع البيانات كـ JSON
            return response()->json([
                'success' => true,
                'grouped_data' => $groupedDataArray,
                'totals' => $totals,
                'chart_data' => $chartData,
                'report_type' => $reportType,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesReports', 'employeeComprehensiveProfitsReportAjax');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    private function processDataByReportType($salesData, $reportType)
    {
        switch ($reportType) {
            case 'employee_profits':
                return $this->groupByEmployee($salesData);
            case 'client_profits':
                return $this->groupByClient($salesData);
            case 'product_profits':
                return $this->groupByProduct($salesData);
            case 'comprehensive':
            default:
                return $this->groupComprehensive($salesData);
        }
    }

    private function groupByEmployee($salesData)
    {
        return $salesData->groupBy('employee_id')->map(function ($items, $employeeId) {
            $employee = $items->first();

            $totalRevenue = $items->sum('total');
            $totalCost = $items->sum(function ($item) {
                return (float) ($item->line_cost ?? 0);
            });
            $totalProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

            return [
                'id' => $employeeId,
                'name' => $employee->employee_name,
                'type' => 'employee',
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin' => $profitMargin,
                'total_quantity' => $items->sum('quantity'),
                'transactions_count' => $items->count(),
                'items' => $items->toArray(),
            ];
        });
    }

    private function groupByClient($salesData)
    {
        return $salesData->groupBy('client_id')->map(function ($items, $clientId) {
            $client = $items->first();

            $totalRevenue = $items->sum('total');
            $totalCost = $items->sum(function ($item) {
                return (float) ($item->line_cost ?? 0);
            });
            $totalProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

            return [
                'id' => $clientId,
                'name' => $client->client_name ?? 'عميل غير محدد',
                'type' => 'client',
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin' => $profitMargin,
                'total_quantity' => $items->sum('quantity'),
                'transactions_count' => $items->count(),
                'items' => $items->toArray(),
            ];
        });
    }

    private function groupByProduct($salesData)
    {
        return $salesData->groupBy('product_id')->map(function ($items, $productId) {
            $product = $items->first();

            $totalRevenue = $items->sum('total');
            $totalCost = $items->sum(function ($item) {
                return (float) ($item->line_cost ?? 0);
            });
            $totalProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

            return [
                'id' => $productId,
                'name' => $product->product_name,
                'type' => 'product',
                'category' => $product->category_name ?? 'غير محدد',
                'brand' => $product->brand ?? 'غير محدد',
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin' => $profitMargin,
                'total_quantity' => $items->sum('quantity'),
                'transactions_count' => $items->count(),
                'items' => $items->toArray(),
            ];
        });
    }

    private function groupComprehensive($salesData)
    {
        $employees = $this->groupByEmployee($salesData);
        $clients = $this->groupByClient($salesData);
        $products = $this->groupByProduct($salesData);

        return [
            'employees' => $employees,
            'clients' => $clients,
            'products' => $products,
        ];
    }

    private function calculateTotals($salesData)
    {
        $totalRevenue = $salesData->sum('total');
        $totalCost = $salesData->sum(function ($item) {
            return (float) ($item->line_cost ?? 0);
        });
        $totalProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'total_profit_margin' => $profitMargin,
            'total_transactions' => $salesData->count(),
            'total_quantity_sold' => $salesData->sum('quantity'),
        ];
    }

    private function formatDataForJson($groupedData, $reportType)
    {
        if ($reportType === 'comprehensive') {
            return [
                'employees' => $groupedData['employees']->values()->toArray(),
                'clients' => $groupedData['clients']->values()->toArray(),
                'products' => $groupedData['products']->values()->toArray(),
            ];
        }

        return $groupedData->values()->toArray();
    }

    private function prepareChartDataProfits($groupedData, $reportType)
    {
        if ($reportType === 'comprehensive') {
            // للتقرير الشامل، نأخذ بيانات الموظفين
            $data = $groupedData['employees'] ?? collect();
        } else {
            $data = $groupedData;
        }

        $labels = $data->pluck('name')->take(10)->toArray();
        $profits = $data->pluck('total_profit')->take(10)->toArray();
        $revenues = $data->pluck('total_revenue')->take(10)->toArray();
        $costs = $data->pluck('total_cost')->take(10)->toArray();

        return [
            'labels' => $labels,
            'profits' => $profits,
            'revenues' => $revenues,
            'costs' => $costs,
        ];
    }

    public function byItemReport(Request $request)
    {
        // 1. الحصول على البيانات الأساسية للقوائم المنسدلة
        $clients = Client::all();
        $employees = Employee::all();
        $products = Product::all();
        $branches = Branch::all();
        $categories = Category::all();
        $storehouses = StoreHouse::all();
        $users = User::where('role', 'employee')->get();

        // 2. تحديد التواريخ الافتراضية
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : now()->subMonth();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : now();

        // 3. إعداد البيانات الافتراضية الفارغة
        $totals = [
            'total_quantity' => 0,
            'total_amount' => 0,
            'total_discount' => 0,
            'total_sales' => 0,
            'total_returns' => 0,
            'total_invoices' => 0,
        ];

        $groupedItems = collect();
        $itemTotals = collect();
        $chartData = ['labels' => [], 'quantities' => [], 'amounts' => []];

        // 4. إرجاع العرض
        return view('sales::sals_report.salesRport.itemReport', compact('groupedItems', 'clients', 'employees', 'products', 'branches', 'categories', 'storehouses', 'totals', 'chartData', 'fromDate', 'toDate', 'itemTotals', 'users'));
    }

    /**
     * دالة AJAX لجلب بيانات تقرير البنود
     */
    public function byItemReportAjax(Request $request)
    {
        try {
            // 1. التحقق من صحة البيانات المدخلة
            $validatedData = $request->validate([
                'item' => 'nullable|exists:products,id',
                'category' => 'nullable|exists:categories,id',
                'client' => 'nullable|exists:clients,id',
                'branch' => 'nullable|exists:branches,id',
                'storehouse' => 'nullable|exists:store_houses,id',
                'employee' => 'nullable|exists:employees,id',
                'status' => 'nullable|in:فاتورة,مسودة',
                'invoice_type' => 'nullable|in:1,2,3',
                'added_by' => 'nullable|exists:users,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'period' => 'nullable|in:daily,weekly,monthly,yearly',
                'date_type' => 'nullable|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom',
            ]);

            // 2. تحديد نطاق التاريخ بناءً على نوع التاريخ المحدد
            $fromDate = now()->subMonth();
            $toDate = now();

            if ($request->filled('date_type') && $request->date_type !== 'custom') {
                $dateRange = $this->getDateRange($request->date_type);
                $fromDate = $dateRange['from'];
                $toDate = $dateRange['to'];
            } elseif ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDate = Carbon::parse($request->from_date);
                $toDate = Carbon::parse($request->to_date);
            } elseif ($request->filled('from_date')) {
                $fromDate = Carbon::parse($request->from_date);
            } elseif ($request->filled('to_date')) {
                $toDate = Carbon::parse($request->to_date);
            }

            // 3. بناء استعلام عناصر الفواتير مع العلاقات
            $invoiceItems = InvoiceItem::with([
                'product.category',
                'invoice' => function ($q) {
                    $q->with(['client.branch', 'createdByUser', 'employee']);
                },
                'storeHouse',
            ])
                ->whereHas('invoice', function ($query) use ($fromDate, $toDate) {
                    $query
                        ->whereNotIn('id', function ($subQuery) {
                            $subQuery
                                ->select('reference_number')
                                ->from('invoices')
                                ->whereIn('type', ['normal', 'returned'])
                                ->whereNotNull('reference_number');
                        })
                        ->whereBetween('invoice_date', [$fromDate, $toDate]);
                })
                ->orderBy('created_at', 'desc');

            // 4. تطبيق الفلاتر
            // فلتر البند/المنتج
            if ($request->filled('item')) {
                $invoiceItems->where('product_id', $request->item);
            }

            // فلتر فئة المنتج
            if ($request->filled('category')) {
                $invoiceItems->whereHas('product', function ($query) use ($request) {
                    $query->where('category_id', $request->category);
                });
            }

            // فلتر العميل
            if ($request->filled('client')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    $query->where('client_id', $request->client);
                });
            }

            // فلتر الفرع
            if ($request->filled('branch')) {
                $invoiceItems->whereHas('invoice.client', function ($query) use ($request) {
                    $query->where('branch_id', $request->branch);
                });
            }

            // فلتر المخزن
            if ($request->filled('storehouse')) {
                $invoiceItems->where('store_house_id', $request->storehouse);
            }

            // فلتر الموظف
            if ($request->filled('employee')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    $query->where('employee_id', $request->employee);
                });
            }

            // فلتر حالة الفاتورة
            if ($request->filled('status')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    if ($request->status === 'فاتورة') {
                        $query->where('payment_status', '!=', 'draft');
                    } elseif ($request->status === 'مسودة') {
                        $query->where('payment_status', 'draft');
                    }
                });
            }

            // فلتر نوع الفاتورة
            if ($request->filled('invoice_type')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    switch ($request->invoice_type) {
                        case '1':
                            $query->whereIn('type', ['return', 'returned']);
                            break;
                        case '2':
                            $query->where('type', 'debit_note');
                            break;
                        case '3':
                            $query->where('type', 'credit_note');
                            break;
                    }
                });
            }

            // فلتر المضيف بواسطة
            if ($request->filled('added_by')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    $query->where('created_by', $request->added_by);
                });
            }

            // فلتر الفترة
            if ($request->filled('period')) {
                $invoiceItems->whereHas('invoice', function ($query) use ($request) {
                    $now = Carbon::now();
                    switch ($request->period) {
                        case 'daily':
                            $query->whereDate('invoice_date', $now->toDateString());
                            break;
                        case 'weekly':
                            $query->whereBetween('invoice_date', [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()]);
                            break;
                        case 'monthly':
                            $query->whereMonth('invoice_date', $now->month);
                            break;
                        case 'yearly':
                            $query->whereYear('invoice_date', $now->year);
                            break;
                    }
                });
            }

            // 5. الحصول على النتائج
            $invoiceItems = $invoiceItems->get();

            // 6. معالجة البيانات وتجميعها
            $groupedItems = collect();
            $totals = [
                'total_quantity' => 0,
                'total_amount' => 0,
                'total_discount' => 0,
                'total_sales' => 0,
                'total_returns' => 0,
                'total_invoices' => 0,
            ];

            if ($invoiceItems->isNotEmpty()) {
                // تجميع العناصر حسب البند
                $groupedItems = $invoiceItems->groupBy('product.name');

                // حساب الإجماليات العامة
                foreach ($invoiceItems as $item) {
                    $isReturn = in_array($item->invoice->type, ['return', 'returned']);
                    $itemTotal = $item->quantity * $item->unit_price;

                    $totals['total_quantity'] += $item->quantity;
                    $totals['total_discount'] += $item->discount_amount ?? 0;

                    if ($isReturn) {
                        $totals['total_returns'] += $itemTotal;
                        $totals['total_amount'] -= $itemTotal;
                    } else {
                        $totals['total_sales'] += $itemTotal;
                        $totals['total_amount'] += $itemTotal;
                    }
                }

                $totals['total_invoices'] = $invoiceItems->groupBy('invoice_id')->count();

                // تحويل المجموعات إلى array للإرسال عبر JSON
                $groupedItemsArray = [];
                foreach ($groupedItems as $itemName => $itemsData) {
                    $groupedItemsArray[$itemName] = $itemsData
                        ->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'discount_amount' => $item->discount_amount,
                                'total_amount' => $item->quantity * $item->unit_price,
                                'product' => [
                                    'id' => $item->product->id,
                                    'name' => $item->product->name,
                                    'code' => $item->product->code,
                                    'category' => $item->product->category
                                        ? [
                                            'id' => $item->product->category->id,
                                            'name' => $item->product->category->name,
                                        ]
                                        : null,
                                ],
                                'invoice' => [
                                    'id' => $item->invoice->id,
                                    'code' => $item->invoice->code,
                                    'invoice_date' => $item->invoice->invoice_date,
                                    'type' => $item->invoice->type,
                                    'payment_status' => $item->invoice->payment_status,
                                    'client' => $item->invoice->client
                                        ? [
                                            'id' => $item->invoice->client->id,
                                            'trade_name' => $item->invoice->client->trade_name,
                                        ]
                                        : null,
                                    'employee' => $item->invoice->employee
                                        ? [
                                            'id' => $item->invoice->employee->id,
                                            'full_name' => $item->invoice->employee->full_name,
                                        ]
                                        : null,
                                    'created_by_user' => $item->invoice->createdByUser
                                        ? [
                                            'id' => $item->invoice->createdByUser->id,
                                            'name' => $item->invoice->createdByUser->name,
                                        ]
                                        : null,
                                ],
                                'store_house' => $item->storeHouse
                                    ? [
                                        'id' => $item->storeHouse->id,
                                        'name' => $item->storeHouse->name,
                                    ]
                                    : null,
                            ];
                        })
                        ->toArray();
                }
            } else {
                $groupedItemsArray = [];
            }

            // 7. إرجاع البيانات كـ JSON
            return response()->json([
                'success' => true,
                'grouped_items' => $groupedItemsArray,
                'totals' => $totals,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
                'items_count' => $invoiceItems->count(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesReports', 'byItemReportAjax');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * تقرير المبيعات حسب الفترة الزمنية
     *
     * ══════════ الأساس: ذمّةُ العميل، شاملةً الضريبة والشحن ══════════
     *
     * كان المبلغ = كلَّ مدينٍ في القيد: طرفَ العميل، وقيدَ تكلفة المبيعات
     * التوأم (نوعُه SI أيضاً)، وحسابَ المرتجعات — والمرتجعُ والإشعارُ
     * الدائن يُضافان إلى المبيعات لا يُطرحان. فأغسطس ٢٠٢٦ كان ١٩٣٬٩٨١٫٩٣
     * وصافيه الصحيح ١٢٧٬٧٩٣٫٧٦، والعددُ ١٣٢٢ «عملية» نصفُها قيودُ تكلفة.
     *
     * الآن الأرقامُ من طرف حساب العميل وحدَه — قاعدةُ {@see SalesLedger}
     * نفسُها التي تقرأ بها لوحةُ المبيعات:
     *
     *     إجمالي المبيعات = مدين − دائن قيود SI على حساب العميل
     *     المرتجعات       = دائن قيود SR على حساب العميل
     *     الإشعارات الدائنة = دائن قيود CN على حساب العميل
     *     الصافي          = الإجمالي − المرتجعات − الإشعارات
     *
     * فقيدُ التكلفة التوأم لا طرفَ له على العميل فيسقط بالبناء، ومحفظةُ
     * العميل مستبعدة، والقيدُ المرحَّل وحدَه (`status = 1`).
     *
     * «المحصَّل» = دفعاتُ العميل المربوطة بفواتير الفترة (`payments_process`
     * بنوع «client payments»، عدا الفاشلة `payment_status = 5`) المؤرَّخةُ
     * حتى نهاية مدى التقرير — قاعدةُ «المبيعات حسب العميل» حرفياً. كان
     * يربط `reference_number` الدفعة بمعرّف القيد، وهو ربطٌ لا معنى له.
     *
     * والفرعُ محورُه عقدةُ العميل ({@see SalesLedger::matchClientNodes()})
     * لا ختمُ القيد، ونطاقُ الرؤية من {@see SalesReportTrait}.
     */
    public function salesByPeriod(Request $request)
    {
        $categories = CategoriesClient::all();

        // تحديد نوع الفترة (daily, weekly, monthly, yearly)
        $periodType = in_array($request->input('period_type'), ['daily', 'weekly', 'monthly', 'yearly'], true)
            ? $request->input('period_type')
            : 'monthly';

        // تحديد الفترة الزمنية بناءً على نوع الفترة
        $now = Carbon::now();
        switch ($periodType) {
            case 'daily':
                $defaultFrom = $now->copy()->subDays(30);
                $defaultTo = $now->copy();
                break;
            case 'weekly':
                $defaultFrom = $now->copy()->subWeeks(12);
                $defaultTo = $now->copy();
                break;
            case 'yearly':
                $defaultTo = $now->copy();
                $defaultFrom = $now->copy()->subYears(5)->startOfYear();
                break;
            default:
                $defaultFrom = $now->copy()->startOfYear();
                $defaultTo = $now->copy();
        }

        $fromDate = $request->filled('date_from') ? Carbon::parse($request->date_from) : $defaultFrom;
        $toDate = $request->filled('date_to') ? Carbon::parse($request->date_to) : $defaultTo;

        $isSummary = $request->input('view_type', 'summary') !== 'details';

        // =================== النطاق: عقدة «تابع لـ» مقصوصةً على الرؤية ===================
        $user = auth()->user();
        $userBranchId = $user->branch_id;
        $isMainBranch = (bool) optional(Branch::findCached($userBranchId))->is_main;

        /* نفسُ قاعدة «المبيعات حسب العميل» حرفياً — فتتطابق الشاشتان على
           المدى والفلاتر نفسِها: العقدةُ المقصوصةُ وشجرتُها، أو وحدَها. */
        $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
        $branchIds = $effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null;
        $nodeIds = $effectiveBranchId
            ? ($request->boolean('exact_node') ? [(int) $effectiveBranchId] : $branchIds)
            : null;

        $dropdowns = $this->getFilteredDropdowns($branchIds, withClientList: false);
        $employees = $dropdowns['employees'];
        $branches = $dropdowns['branches'];
        $treeNodes = $dropdowns['treeNodes'];
        $clients = $this->salesByPeriodClientOptions($branchIds);

        // =================== المستندات: صفٌّ لكلّ مستندٍ على حساب عميله ===================
        $documents = $this->salesByPeriodDocuments($request, $fromDate, $toDate, $nodeIds);
        $paidByInvoice = $this->salesByPeriodPaid($documents, $toDate);

        // دالة لتحديد مفتاح الفترة
        $getPeriodKey = function ($date) use ($periodType) {
            $carbonDate = Carbon::parse($date);
            switch ($periodType) {
                case 'daily':
                    return $carbonDate->format('Y-m-d');
                case 'weekly':
                    return $carbonDate->startOfWeek()->format('Y-m-d');
                case 'monthly':
                    return $carbonDate->format('Y-m');
                case 'yearly':
                    return $carbonDate->format('Y');
                default:
                    return $carbonDate->format('Y-m');
            }
        };

        // دالة لتحديد اسم الفترة
        $getPeriodName = function ($date) use ($periodType) {
            $carbonDate = Carbon::parse($date);
            switch ($periodType) {
                case 'daily':
                    $arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

                    return ($arabicDays[$carbonDate->dayOfWeek] ?? '').' '.$carbonDate->format('d/m/Y');
                case 'weekly':
                    $weekStart = $carbonDate->copy()->startOfWeek();
                    $weekEnd = $carbonDate->copy()->endOfWeek();

                    return 'الأسبوع '.$carbonDate->weekOfYear.' ('.$weekStart->format('d/m').' - '.$weekEnd->format('d/m/Y').')';
                case 'monthly':
                    $arabicMonths = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

                    return ($arabicMonths[$carbonDate->month] ?? '').' '.$carbonDate->format('Y');
                case 'yearly':
                    return 'سنة '.$carbonDate->format('Y');
                default:
                    return $carbonDate->format('Y-m');
            }
        };

        $emptyFigures = [
            'count' => 0,
            'gross' => 0.0,
            'returns' => 0.0,
            'returns_count' => 0,
            'credit_notes' => 0.0,
            'credit_notes_count' => 0,
            'net' => 0.0,
            'paid' => 0.0,
            'unpaid' => 0.0,
            'overpaid' => 0.0,
        ];

        /* الملخّصُ والتفاصيلُ من الصفوف نفسِها — فيستحيل أن يختلفا. */
        $reportData = [];
        $keyByDate = [];

        foreach ($documents as $doc) {
            $periodKey = $keyByDate[$doc->date] ??= $getPeriodKey($doc->date);

            if (! isset($reportData[$periodKey])) {
                $reportData[$periodKey] = ['name' => $getPeriodName($doc->date), 'date_key' => $periodKey]
                    + $emptyFigures
                    + ['items' => []];
            }

            $row = &$reportData[$periodKey];
            $paid = 0.0;

            switch ($doc->reference_type) {
                case 'SI':
                    $amount = (float) $doc->debit - (float) $doc->credit;
                    $paid = (float) ($paidByInvoice[(int) $doc->invoice_id] ?? 0);
                    $row['gross'] += $amount;
                    $row['paid'] += $paid;
                    $row['count']++;
                    break;
                case 'SR':
                    $amount = (float) $doc->credit;
                    $row['returns'] += $amount;
                    $row['returns_count']++;
                    break;
                default: // CN
                    $amount = (float) $doc->credit;
                    $row['credit_notes'] += $amount;
                    $row['credit_notes_count']++;
            }

            if (! $isSummary) {
                /* المبلغُ بإشارته: المرتجعُ والإشعارُ سالبان، فمجموعُ الصفوف
                   = صافي الفترة. و«الرصيد» = المبلغ − المحصَّل بلا تقريبٍ إلى
                   الصفر، فمجموعُه = الصافي − المحصَّل: الموجبُ متبقٍّ، والسالبُ
                   مدفوعٌ زيادة — كما في إجمالي الفترة تماماً. */
                $signed = $doc->reference_type === 'SI' ? $amount : -$amount;
                $row['items'][] = [
                    'entry_id' => (int) $doc->entry_id,
                    'date' => Carbon::parse($doc->date)->format('d/m/Y'),
                    'reference' => $doc->reference_number,
                    'client_id' => $doc->client_name !== null ? (int) $doc->client_id : null,
                    'client_name' => (string) $doc->client_name,
                    'employee' => $doc->employee_name ?: '-',
                    'type_code' => $doc->reference_type,
                    'node' => $doc->node_name ?: '-',
                    'amount' => $signed,
                    'paid' => $paid,
                    'balance' => $signed - $paid,
                ];
            }

            unset($row);
        }

        $totals = $emptyFigures;

        foreach ($reportData as &$period) {
            $period['net'] = $period['gross'] - $period['returns'] - $period['credit_notes'];
            $period['unpaid'] = max($period['net'] - $period['paid'], 0);
            $period['overpaid'] = max($period['paid'] - $period['net'], 0);

            foreach (array_keys($emptyFigures) as $field) {
                $totals[$field] += $period[$field];
            }
        }
        unset($period);

        krsort($reportData);

        // بيانات الرسم البياني
        $chartData = [
            'labels' => [],
            'net' => [],
            'paid' => [],
            'unpaid' => [],
        ];

        $sortedForChart = $reportData;
        ksort($sortedForChart);

        foreach ($sortedForChart as $period) {
            $chartData['labels'][] = $period['name'];
            $chartData['net'][] = round($period['net'], 2);
            $chartData['paid'][] = round($period['paid'], 2);
            $chartData['unpaid'][] = round($period['unpaid'], 2);
        }

        $periodTypes = [
            'daily' => 'يومي',
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            'yearly' => 'سنوي',
        ];

        return view('sales::sals_report.salesRport.sales_by_period', compact(
            'reportData',
            'totals',
            'chartData',
            'branches',
            'employees',
            'clients',
            'categories',
            'treeNodes',
            'effectiveBranchId',
            'fromDate',
            'toDate',
            'isSummary',
            'periodType',
            'periodTypes'
        ));
    }

    /**
     * مستنداتُ المبيعات في المدى — صفٌّ لكلّ قيدٍ على حساب عميله.
     *
     * SI بطرفه المدين، وSR وCN بطرفهما الدائن وحدَه: مدينُ المرتجع صرفُ
     * قيمته للعميل نقداً، تسويةٌ لا مرتجع ({@see SalesLedger::periodTotals()}).
     * والقيدُ الذي لا قيمةَ له في طرفه (قيدُ تكلفةٍ، أو صرفُ مرتجعٍ وحدَه)
     * لا يُعدّ مستنداً.
     *
     * @param  array<int, int>|null  $branchIds
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function salesByPeriodDocuments(Request $request, Carbon $fromDate, Carbon $toDate, ?array $branchIds)
    {
        $quote = fn (array $types) => implode(',', array_map(fn ($t) => DB::getPdo()->quote($t), $types));

        /* الإشعاراتُ القديمة بلا `reference_type` (ثمانيةٌ في ديسمبر ٢٠٢٥) تُعرف
           بطرفها المدين على «مردودات المبيعات» كما رُبط في الإعدادات المحاسبية —
           القاعدةُ نفسُها في `SalesByCustomerController::ledgerDocuments()`. */
        $salesReturnsAccount = AccountingSettings::id('sales_returns');
        $legacyCreditNote = $salesReturnsAccount
            ? "((je.reference_type IS NULL OR je.reference_type = '') AND EXISTS (SELECT 1 FROM journal_entry_details sr_leg WHERE sr_leg.journal_entry_id = je.id AND sr_leg.account_id = ".(int) $salesReturnsAccount.' AND sr_leg.debit > 0))'
            : '(1 = 0)';

        $docTypeSql = 'CASE'
            .' WHEN je.reference_type IN ('.$quote(SalesLedger::SALE_TYPES).") THEN 'SI'"
            .' WHEN je.reference_type IN ('.$quote(SalesLedger::RETURN_TYPES).") THEN 'SR'"
            .' WHEN je.reference_type IN ('.$quote(SalesLedger::CREDIT_NOTE_TYPES).") OR $legacyCreditNote THEN 'CN'"
            .' END';

        $query = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->leftJoin('clients as c', 'c.id', '=', 'a.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by_employee')
            ->leftJoin('branches as nb', 'nb.id', '=', DB::raw(SalesLedger::clientNodeExpr('c')))
            ->whereNotNull('a.client_id')
            ->where('je.status', 1)
            ->whereBetween('je.date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->where(fn ($q) => $q->whereIn('je.reference_type', array_merge(SalesLedger::SALE_TYPES, SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES))
                ->orWhereNull('je.reference_type')
                ->orWhere('je.reference_type', ''))
            ->whereRaw("($docTypeSql) IS NOT NULL");

        SalesLedger::excludeWalletAccounts($query, 'a');
        $this->excludeClosedRanges($query, 'je.date');
        $this->scopeJournalToVisibleUsers($query, 'je.created_by_employee');

        if ($branchIds) {
            $query->whereIn('a.client_id', function ($sub) use ($branchIds) {
                $sub->select('id')->from('clients');
                SalesLedger::matchClientNodes($sub, 'clients', $branchIds);
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('je.created_by_employee', (int) $request->employee_id);
        }
        if ($request->filled('client_id')) {
            $query->where('a.client_id', (int) $request->client_id);
        }
        if ($request->filled('category_id')) {
            $query->where('c.category_id', (int) $request->category_id);
        }

        return $query
            ->groupBy('je.id', 'a.client_id', 'je.reference_type', 'je.reference_number', 'je.date', 'je.invoice_id', 'c.trade_name', 'c.first_name', 'u.name', 'nb.name')
            ->select([
                'je.id as entry_id',
                // مجمَّعٌ لا خام: التعبيرُ يقرأ `je.id` داخل EXISTS (ONLY_FULL_GROUP_BY)
                DB::raw("MAX($docTypeSql) as reference_type"),
                'je.reference_number',
                'je.date',
                'je.invoice_id',
                'a.client_id',
                DB::raw("COALESCE(NULLIF(c.trade_name, ''), c.first_name) as client_name"),
                'u.name as employee_name',
                'nb.name as node_name',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
                /* المبلغُ على أساس «المبيعات حسب العميل»: طرفُ حساب المبيعات
                   للفاتورة وطرفُ مردوداتها للمرتجع والإشعار — قبل الضريبة. طرفُ
                   العميل أعلاه يحدّد المستندَ وعميلَه فقط. */
                DB::raw('(SELECT COALESCE(SUM(sl.credit - sl.debit), 0) FROM journal_entry_details sl WHERE sl.journal_entry_id = je.id AND sl.account_id = '.(int) AccountingSettings::id('sales').') as sales_leg'),
                DB::raw('(SELECT COALESCE(SUM(rl.debit - rl.credit), 0) FROM journal_entry_details rl WHERE rl.journal_entry_id = je.id AND rl.account_id = '.(int) $salesReturnsAccount.') as returns_leg'),
            ])
            ->orderByDesc('je.date')
            ->orderByDesc('je.id')
            ->get()
            // الفاتورةُ طرفُها المدين، والمرتجعُ والإشعارُ طرفُهما الدائن — وما خلا منه ليس مستنداً
            ->filter(fn ($doc) => $doc->reference_type === 'SI' ? (float) $doc->debit > 0 : (float) $doc->credit > 0)
            ->each(function ($doc) {
                if ($doc->reference_type === 'SI') {
                    $doc->debit = (float) $doc->sales_leg;
                    $doc->credit = 0.0;
                } else {
                    $doc->credit = (float) $doc->returns_leg;
                }
            })
            ->values();
    }

    /**
     * ما سُدِّد من كلّ فاتورة حتى نهاية مدى التقرير — قاعدةُ «المبيعات حسب
     * العميل»: دفعاتُ العميل المربوطة بالفاتورة، بلا الفاشلة (الحالة ٥).
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $documents
     * @return array<int, float>
     */
    private function salesByPeriodPaid($documents, Carbon $asOf): array
    {
        $invoiceIds = $documents->where('reference_type', 'SI')->pluck('invoice_id')->filter()->unique()->values();

        $paid = [];

        foreach ($invoiceIds->chunk(2000) as $chunk) {
            DB::table('payments_process')
                ->whereIn('invoice_id', $chunk->all())
                ->where('type', 'client payments')
                ->where(fn ($q) => $q->whereNull('payment_status')->orWhere('payment_status', '<>', 5))
                ->where(fn ($q) => $q->whereNull('payment_date')->orWhereDate('payment_date', '<=', $asOf->toDateString()))
                ->groupBy('invoice_id')
                ->select(['invoice_id', DB::raw('SUM(amount) as paid')])
                ->get()
                ->each(function ($row) use (&$paid) {
                    $paid[(int) $row->invoice_id] = (float) $row->paid;
                });
        }

        return $paid;
    }

    /**
     * قائمةُ العملاء للفلتر — خفيفةٌ (بلا علاقات)، مقصورةٌ على العقدة ونطاق الرؤية.
     *
     * @param  array<int, int>|null  $branchIds
     */
    private function salesByPeriodClientOptions(?array $branchIds)
    {
        $query = Client::query()->select(['id', 'trade_name', 'first_name', 'code']);

        DataScope::constrainOwner($query, 'employee_id');

        if ($branchIds) {
            AffiliationTree::constrainClients($query, $branchIds);
        }

        return $query->orderBy('trade_name')->get();
    }

    /**
     * تقرير المدفوعات حسب الفترة الزمنية
     */
    public function paymentsByPeriod(Request $request)
    {
        // جلب البيانات للفلاتر
        $branches = Branch::all();
        $employees = User::where('role', 'employee')->orderBy('name')->get();
        $clients = Client::orderBy('trade_name')->get();

        /* قائمةُ الخزائن بمعرّف **الحساب** لا بمعرّف صفّ `treasuries`.
           `payments_process.treasury_id` عمودٌ يحمل `accounts.id`، وكان
           الفلتر يرسل `treasuries.id` (أرقامٌ صغيرة) فيقارنه بمعرّفات
           حساباتٍ بالآلاف: اختيارُ أيّ خزينةٍ يُفرغ التقرير — أو يُصادف
           رقماً فيعرض سنداتِ خزينةٍ أخرى. وصفٌّ بلا `account_id` لا يصلح
           خياراً أصلاً: لا حركةَ تُقيَّد عليه. */
        $treasuries = Treasury::whereNotNull('account_id')->orderBy('name')->get();

        // تحديد نوع الفترة
        $periodType = $request->filled('period_type') ? $request->period_type : 'monthly';

        // تحديد الفترة الزمنية
        $now = Carbon::now();
        switch ($periodType) {
            case 'daily':
                $defaultFrom = $now->copy()->subDays(30);
                $defaultTo = $now->copy();
                break;
            case 'weekly':
                $defaultFrom = $now->copy()->subWeeks(12);
                $defaultTo = $now->copy();
                break;
            case 'monthly':
                $defaultFrom = $now->copy()->startOfYear();
                $defaultTo = $now->copy();
                break;
            case 'yearly':
                $defaultTo = $now->copy();
                $defaultFrom = $now->copy()->subYears(5)->startOfYear();
                break;
            default:
                $defaultFrom = $now->copy()->startOfYear();
                $defaultTo = $now->copy();
        }

        $fromDate = $request->filled('date_from') ? Carbon::parse($request->date_from) : $defaultFrom;
        $toDate = $request->filled('date_to') ? Carbon::parse($request->date_to) : $defaultTo;

        $isSummary = $request->input('view_type', 'summary') !== 'details';

        // جلب بيانات المدفوعات
        $query = PaymentsProcess::query()
            ->where('type', 'client payments')
            ->whereBetween('payment_date', [$fromDate->format('Y-m-d 00:00:00'), $toDate->format('Y-m-d 23:59:59')])
            ->with(['client', 'employee', 'treasuryAccount', 'invoice', 'branch']);

        // تطبيق الفلاتر
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('treasury_id')) {
            $query->where('treasury_id', $request->treasury_id);
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        // تجهيز بيانات التقرير حسب الفترة
        $reportData = [];
        $totals = [
            'amount' => 0,
            'count' => 0,
        ];

        $getPeriodKey = function ($date) use ($periodType) {
            $carbonDate = Carbon::parse($date);
            switch ($periodType) {
                case 'daily':
                    return $carbonDate->format('Y-m-d');
                case 'weekly':
                    return $carbonDate->startOfWeek()->format('Y-m-d');
                case 'monthly':
                    return $carbonDate->format('Y-m');
                case 'yearly':
                    return $carbonDate->format('Y');
                default:
                    return $carbonDate->format('Y-m');
            }
        };

        $getPeriodName = function ($date) use ($periodType) {
            $carbonDate = Carbon::parse($date);
            switch ($periodType) {
                case 'daily':
                    $arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

                    return ($arabicDays[$carbonDate->dayOfWeek] ?? '').' '.$carbonDate->format('d/m/Y');
                case 'weekly':
                    $weekStart = $carbonDate->copy()->startOfWeek();
                    $weekEnd = $carbonDate->copy()->endOfWeek();

                    return 'الأسبوع '.$carbonDate->weekOfYear.' ('.$weekStart->format('d/m').' - '.$weekEnd->format('d/m/Y').')';
                case 'monthly':
                    $arabicMonths = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

                    return ($arabicMonths[$carbonDate->month] ?? '').' '.$carbonDate->format('Y');
                case 'yearly':
                    return 'سنة '.$carbonDate->format('Y');
                default:
                    return $carbonDate->format('Y-m');
            }
        };

        foreach ($payments as $payment) {
            $periodKey = $getPeriodKey($payment->payment_date);
            $periodName = $getPeriodName($payment->payment_date);

            $amount = $payment->amount ?? 0;

            if (! isset($reportData[$periodKey])) {
                $reportData[$periodKey] = [
                    'name' => $periodName,
                    'date_key' => $periodKey,
                    'amount' => 0,
                    'count' => 0,
                    'items' => collect(),
                ];
            }

            $reportData[$periodKey]['amount'] += $amount;
            $reportData[$periodKey]['count']++;
            $reportData[$periodKey]['items']->push($payment);

            $totals['amount'] += $amount;
            $totals['count']++;
        }

        krsort($reportData);

        // بيانات الرسم البياني
        $chartData = [
            'labels' => [],
            'amounts' => [],
        ];

        $sortedForChart = $reportData;
        ksort($sortedForChart);

        foreach ($sortedForChart as $period) {
            $chartData['labels'][] = $period['name'];
            $chartData['amounts'][] = round($period['amount'], 2);
        }

        $periodTypes = [
            'daily' => 'يومي',
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            'yearly' => 'سنوي',
        ];

        return view('sales::sals_report.payments.payments_by_period', compact(
            'reportData',
            'totals',
            'chartData',
            'branches',
            'employees',
            'clients',
            'treasuries',
            'fromDate',
            'toDate',
            'isSummary',
            'periodType',
            'periodTypes'
        ));
    }
}
