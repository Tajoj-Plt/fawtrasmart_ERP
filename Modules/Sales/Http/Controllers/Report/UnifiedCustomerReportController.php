<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\EmployeeClientVisit;
use App\Models\Statuses;
use App\Models\User;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * تقرير موحد للعملاء — يجمع في صفحة واحدة:
 *   • إجمالي الفواتير (صافي المبيعات)
 *   • إجمالي التحصيلات
 *   • الرصيد الحالي
 *   • آخر تحصيل (تاريخ)
 *   • أيام التأخير
 *   • آخر عهدة (deposit)
 */
class UnifiedCustomerReportController extends Controller
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
        $this->middleware('permission:sales.reports.unified_customer');
    }

    use SalesReportTrait;

    public function index(Request $request)
    {
        try {
            // =================== الفترة الزمنية ===================
            $period = $request->input('period', '30');
            [$fromDate, $toDate] = $this->parseDateRangeRequest($request, $period);
            $startDate = $fromDate;
            $endDate = $toDate;

            // =================== صلاحيات المستخدم ===================
            $user = User::find(auth()->user()->id);
            $userBranchId = $user->branch_id;
            $currentBranch = Branch::find($userBranchId);
            $isMainBranch = ! $currentBranch || (bool) $currentBranch->is_main;

            $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
            $branchIds = $effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null;

            // =================== القوائم المنسدلة ===================
            $dropdowns = $this->getFilteredDropdowns($branchIds);
            extract($dropdowns); // $employees, $clients, $regionGroups, $branches, $cascadeBranches
            $categories = CategoriesClient::all();
            $statuses = Statuses::all();

            // =================== فلاتر الطلب ===================
            $filterClientId = $request->input('client_id');
            $filterEmployeeId = $request->input('employee_id');
            $filterCategoryId = $request->input('category_id');
            $filterStatusId = $request->input('status_id', 'except_suspended');
            $showZero = $request->boolean('show_zero', false);

            // تعريف المتغيرات بأسماء مباشرة لدعم قوالب العرض (Blade)
            $clientId = $filterClientId;
            $employeeId = $filterEmployeeId;
            $categoryId = $filterCategoryId;
            $statusId = $filterStatusId;
            $branchId = $effectiveBranchId;

            // متغيرات الفلاتر المشتركة لتجنب أخطاء Undefined variable
            $groupByOptions = [
                'client' => ['id' => 'client', 'name' => 'العميل'],
                'employee' => ['id' => 'employee', 'name' => 'الموظف'],
                'branch' => ['id' => 'branch', 'name' => 'الفرع'],
            ];
            $groupBy = $request->input('group_by', 'client');
            $viewType = $request->input('view_type', 'details');
            $isSummary = $viewType === 'summary';
            $neighborhoodId = $request->input('neighborhood_id');

            // =================== جلب البيانات الموحدة ===================
            $reportData = $this->buildUnifiedReport(
                $fromDate,
                $toDate,
                $branchIds,
                $filterClientId,
                $filterEmployeeId,
                $filterCategoryId,
                $filterStatusId,
                $showZero
            );

            // =================== إجماليات ===================
            $totals = [
                'total_clients' => count($reportData),
                'total_sales' => array_sum(array_column($reportData, 'total_sales')),
                'total_collections' => array_sum(array_column($reportData, 'total_collections')),
                'total_balance' => array_sum(array_column($reportData, 'balance')),
            ];

            return view('sales::sals_report.salesRport.Unified_Customer_Report', compact(
                'reportData',
                'totals',
                'employees',
                'clients',
                'branches',
                'categories',
                'statuses',
                'cascadeBranches',
                'regionGroups',
                'isMainBranch',
                'effectiveBranchId',
                'fromDate',
                'toDate',
                'startDate',
                'endDate',
                'period',
                'showZero',
                'filterClientId',
                'filterEmployeeId',
                'filterCategoryId',
                'filterStatusId',
                'clientId',
                'employeeId',
                'categoryId',
                'statusId',
                'branchId',
                'groupByOptions',
                'groupBy',
                'viewType',
                'isSummary',
                'neighborhoodId'
            ));
        } catch (\Exception $e) {
            Log::error('Unified Customer Report Error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors(['error' => 'حدث خطأ أثناء تحميل البيانات: '.$e->getMessage()]);
        }
    }

    // =========================================================
    // القلب: بناء التقرير الموحد
    // =========================================================
    private function buildUnifiedReport(
        Carbon $fromDate,

        Carbon $toDate,
        ?array $branchIds,
        $filterClientId,
        $filterEmployeeId,
        $filterCategoryId,
        $filterStatusId,
        bool $showZero
    ): array {

        // -- 1. جلب حسابات الخزينة والبنوك --
        $treasuryAccountIds = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        // -- 2. الاستعلام الرئيسي: حسابات العملاء مع علاقاتهم --
        $query = Account::with([
            'client' => function ($q) {
                $q->select('id', 'code', 'trade_name', 'branch_id', 'category_id', 'status_id', 'employee_id');
            },
            'client.branch:id,name',
            'client.categoriesClient:id,name',
            'client.employee:id,first_name,middle_name',
            'client.status_client:id,name,color',
            // العهدة وملاحظات العميل
            'client.clientRelations' => function ($q) {
                $q->select('id', 'client_id', 'deposit_count', 'description', 'competitor_documents', 'created_at', 'date', 'attachments', 'client_employee_id', 'employee_id')
                    ->orderBy('created_at', 'desc')
                    ->limit(20);
            },
            'client.clientRelations.employee.employee',
            'client.clientRelations.clientEmployee',
            // آخر ملاحظة (آخر سجل في client_relations مع وصف)
            'client.latestRelation',
            // تفاصيل القيود لحساب أيام التأخير
            'journalEntries' => function ($q) {
                $q->select('journal_entry_details.id', 'journal_entry_details.account_id',
                    'journal_entry_details.debit', 'journal_entry_details.credit',
                    'journal_entry_details.created_at', 'journal_entry_details.journal_entry_id')
                    ->where(function ($qq) {
                        $qq->where('journal_entry_details.debit', '>', 0)
                            ->orWhere('journal_entry_details.credit', '>', 0);
                    })
                    ->orderBy('journal_entry_details.created_at', 'asc');
            },
            'journalEntries.journalEntry:id,reference_type,created_at',
        ])
            ->select('id', 'client_id', 'balance', 'created_at', 'updated_at')
            ->whereNotNull('client_id');

        // فلتر الرصيد الصفري
        if (! $showZero) {
            $query->where('balance', '<>', 0);
        }

        // فلتر الفرع / الشجرة
        if ($branchIds) {
            $query->where(function ($q) use ($branchIds) {
                $q->whereHas('client', fn ($s) => $s->whereIn('branch_id', $branchIds))
                    ->orWhereHas('client', function ($s) use ($branchIds) {
                        $s->whereExists(fn ($ex) => $ex->select(DB::raw(1))
                            ->from('neighborhoods')
                            ->whereColumn('neighborhoods.client_id', 'clients.id')
                            ->whereIn('neighborhoods.region_id', $branchIds)
                        );
                    })
                    ->orWhereIn('accounts.branch_id', $branchIds);
            });
        }

        // فلاتر أخرى
        if ($filterClientId) {
            $query->where('client_id', $filterClientId);
        }
        if ($filterEmployeeId) {
            $query->whereHas('client', fn ($q) => $q->where('employee_id', $filterEmployeeId));
        }

        /* نطاقُ الرؤية — على مندوب العميل، لا على منشئ الحساب.
           التقريرُ صفُّه عميلٌ لا مستند، فالسؤالُ «مَن العميلُ الذي أراه؟»
           لا «مَن كتب هذا الصفّ؟». ومحورُ الأشخاص وحدَه: قيدُ الفرع
           مطبَّقٌ أعلاه على `$branchIds` الآتية من عقدةٍ مقصوصة. */
        $query->when(
            DataScope::isPeopleAxis(),
            fn ($q) => $q->whereHas(
                'client',
                fn ($s) => $s->whereIn('employee_id', DataScope::employeeIds() ?? [0])
            )
        );
        if ($filterCategoryId) {
            $query->whereHas('client', fn ($q) => $q->where('category_id', $filterCategoryId));
        }
        if ($filterStatusId && ! in_array($filterStatusId, ['all', 'except_suspended'])) {
            $query->whereHas('client', fn ($q) => $q->where('status_id', $filterStatusId));
        } elseif ($filterStatusId === 'except_suspended') {
            $suspendedStatus = \App\Models\Statuses::where('name', 'like', '%موقوف%')->first();
            if ($suspendedStatus) {
                $query->whereHas('client', fn ($q) => $q->where(function ($qq) use ($suspendedStatus) {
                    $qq->where('status_id', '!=', $suspendedStatus->id)
                        ->orWhereNull('status_id');
                }));
            }
        }

        $accounts = $query->latest('updated_at')->get();

        // -- 3. جلب إجماليات المبيعات لكل العملاء بـ استعلام واحد --
        $salesPerClient = $this->getSalesPerClient($fromDate, $toDate, $branchIds, $filterClientId, $filterEmployeeId);

        // -- 4. جلب إجماليات التحصيلات وآخر تحصيل لكل العملاء بـ استعلام واحد --
        $collectionsPerClient = $this->getCollectionsPerClient(
            $fromDate, $toDate, $branchIds, $treasuryAccountIds, $filterClientId, $filterEmployeeId
        );

        // -- 4b. جلب تاريخ آخر فاتورة وبيانات الزيارات لكل العملاء بـ استعلام واحد --
        $clientIds = $accounts->pluck('client_id')->filter()->unique()->toArray();
        $lastInvoiceDates = $this->getLastInvoiceDates($clientIds);
        $visitInfoPerClient = $this->getVisitInfoPerClient($clientIds);
        $visitsWithActivity = $this->getAllVisitsWithActivity($clientIds);

        // -- 5. أنواع قيود الأيام (بدون سندات قبض) --
        $debitReferenceTypes = [
            ReferenceType::SALES_INVOICE->value,
            ReferenceType::DEBIT_NOTE->value,
            ReferenceType::OPENING_BALANCE->value,
            ReferenceType::JOURNAL_VOUCHER->value,
        ];

        $result = [];

        foreach ($accounts as $account) {
            if (! $account->client) {
                continue;
            }

            $client = $account->client;

            $statusName = $client->status_client?->name ?? 'غير محدد';
            // استثناء أي حالة "موقوف" إذا كان الفلتر هو "الكل ماعدا الموقوف"
            if ($filterStatusId === 'except_suspended') {
                if (str_contains($statusName, 'موقوف')) {
                    continue;
                }
            }

            $clientId = $client->id;
            $balance = (float) $account->balance;

            // بيانات أساسية
            $branchName = $client->branch?->name ?? 'غير محدد';
            $employeeName = $client->employee
                ? ($client->employee->first_name.' '.$client->employee->middle_name)
                : 'غير محدد';
            $categoryName = $client->categoriesClient?->name ?? 'غير محدد';
            $statusColor = $client->status_client?->color ?? 'secondary';

            // العهدة وملاحظات العميل
            $clientNotes = $client->clientRelations ?? collect();
            $lastDeposit = 0;
            foreach ($clientNotes as $note) {
                if (! empty($note->deposit_count)) {
                    $lastDeposit = (int) $note->deposit_count;
                    break;
                }
            }

            $notesList = $clientNotes->map(function ($note) {
                // $note->employee is a User model, which uses 'name', not 'first_name'
                $empName = $note->employee ? $note->employee->name : 'غير معروف';
                // إذا كان للمستخدم ارتباط بجدول employees
                if ($note->employee && $note->employee->employee) {
                    $empName = $note->employee->employee->first_name.' '.$note->employee->employee->middle_name;
                }
                // توحيد مسارات الصور من حقل attachments ومن صورة الموظف (clientEmployee)
                $images = [];
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
                    'date' => \Carbon\Carbon::parse($note->date ?? $note->created_at)->format('Y-m-d'),
                    'deposit' => $note->deposit_count,
                    'description' => $note->description,
                    'competitor_docs' => $note->competitor_documents,
                    'images' => array_filter($images),
                    'employee_name' => $empName,
                ];
            })->toArray();

            // -- حساب أيام التأخير (بدون سندات قبض) --
            $toDateCarbon = Carbon::parse($toDate)->endOfDay();
            $daysLate = 0;

            $relevantEntries = $account->journalEntries->filter(function ($entry) use ($fromDate, $toDateCarbon) {
                $entryDate = Carbon::parse($entry->created_at)->startOfDay();
                if ($entryDate->isBefore($fromDate) || $entryDate->isAfter($toDateCarbon)) {
                    return false;
                }
                if ($entry->debit <= 0) {
                    return false;
                }
                if ($entry->journalEntry?->reference_type) {
                    $rv = is_string($entry->journalEntry->reference_type)
                        ? $entry->journalEntry->reference_type
                        : $entry->journalEntry->reference_type->value;
                    if (in_array($rv, [ReferenceType::RECEIPT_VOUCHER->value, ReferenceType::CUSTOMER_COLLECTION->value])) {
                        return false;
                    }
                }

                return true;
            });

            if ($relevantEntries->count() > 0) {
                $lastDate = Carbon::parse($relevantEntries->last()->created_at)->startOfDay();
                $daysLate = $lastDate->diffInDays($toDateCarbon);
            } elseif ($account->journalEntries && $account->journalEntries->count() > 0) {
                $oldDebitEntries = $account->journalEntries->filter(function ($entry) {
                    if ($entry->debit <= 0) {
                        return false;
                    }
                    if ($entry->journalEntry?->reference_type) {
                        $rv = is_string($entry->journalEntry->reference_type)
                            ? $entry->journalEntry->reference_type
                            : $entry->journalEntry->reference_type->value;
                        if (in_array($rv, [ReferenceType::RECEIPT_VOUCHER->value, ReferenceType::CUSTOMER_COLLECTION->value])) {
                            return false;
                        }
                    }

                    return true;
                });

                if ($oldDebitEntries->count() > 0) {
                    $lastDate = Carbon::parse($oldDebitEntries->last()->created_at)->startOfDay();
                    $daysLate = $lastDate->diffInDays($toDateCarbon);
                } elseif ($account->created_at) {
                    $daysLate = Carbon::parse($account->created_at)->startOfDay()->diffInDays($toDateCarbon);
                }
            } elseif ($account->created_at) {
                $daysLate = Carbon::parse($account->created_at)->startOfDay()->diffInDays($toDateCarbon);
            }

            // -- المبيعات من الاستعلام المجمع --
            $clientSales = $salesPerClient[$clientId] ?? ['total_sales' => 0, 'invoice_count' => 0, 'returns_amount' => 0, 'history' => []];

            $salesHistoryStr = '';
            if (! empty($clientSales['history'])) {
                foreach ($clientSales['history'] as $hist) {
                    $prefix = $hist['type'] === 'مرتجع' ? 'مرتجع - ' : '';
                    $salesHistoryStr .= $prefix.'مبلغ: '.number_format($hist['amount'], 2).' (تاريخ: '.\Carbon\Carbon::parse($hist['date'])->format('Y-m-d').')&#10;';
                }
                $salesHistoryStr = rtrim($salesHistoryStr, '&#10;');
            }

            // -- التحصيلات من الاستعلام المجمع --
            $clientCollections = $collectionsPerClient[$clientId] ?? ['total_collections' => 0, 'last_collection_date' => null, 'history' => []];

            $historyStr = '';
            if (! empty($clientCollections['history'])) {
                foreach ($clientCollections['history'] as $hist) {
                    $historyStr .= 'مبلغ: '.number_format($hist['amount'], 2).' (تاريخ: '.\Carbon\Carbon::parse($hist['date'])->format('Y-m-d').')&#10;';
                }
                $historyStr = rtrim($historyStr, '&#10;');
            }

            // -- آخر ملاحظة وزيارة من العلاقة --
            $latestRel = $client->latestRelation;
            $lastNote = $latestRel?->description ?? null;
            $lastNoteDate = $latestRel?->date ?? $latestRel?->created_at;

            // -- آخر زيارة وآخر فاتورة من الاستعلامات المجمعة --
            $visitInfo = $visitInfoPerClient[$clientId] ?? null;
            $lastVisitDate = $visitInfo['last_visit_date'] ?? null;
            $lastInvoiceDate = $lastInvoiceDates[$clientId] ?? null;

            // -- تاريخية موحدة: دمج الفواتير + التحصيلات في قائمة واحدة مرتبة --
            $unifiedHistory = [];
            foreach (($clientSales['history'] ?? []) as $h) {
                $unifiedHistory[] = [
                    'date' => $h['date'],
                    'amount' => $h['amount'],
                    'type' => $h['type'], // 'مبيعات' أو 'مرتجع'
                ];
            }
            foreach (($clientCollections['history'] ?? []) as $h) {
                $unifiedHistory[] = [
                    'date' => $h['date'],
                    'amount' => $h['amount'],
                    'type' => 'تحصيل',
                ];
            }
            usort($unifiedHistory, fn ($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

            // -- زيارات العميل مع الأنشطة --
            $clientVisits = $visitsWithActivity[$clientId] ?? [];

            $result[] = [
                'client_id' => $clientId,
                'client_code' => $client->code,
                'client_name' => $client->trade_name ?? 'غير محدد',
                'branch_name' => $branchName,
                'employee_name' => $employeeName,
                'category_name' => $categoryName,
                'client_status_name' => $statusName,
                'client_status_color' => $statusColor,
                'invoice_count' => (int) $clientSales['invoice_count'],
                'total_sales' => (float) $clientSales['total_sales'],
                'sales_history' => $salesHistoryStr,
                'returns_amount' => (float) $clientSales['returns_amount'],
                'net_sales' => (float) ($clientSales['total_sales'] - $clientSales['returns_amount']),
                'total_collections' => (float) $clientCollections['total_collections'],
                'collections_history' => $historyStr,
                'balance' => $balance,
                'days_late' => (int) $daysLate,
                'last_deposit' => $lastDeposit,
                'last_collection_date' => $clientCollections['last_collection_date'],
                'last_invoice_date' => $lastInvoiceDate,
                'last_visit_date' => $lastVisitDate,
                'visit_info' => $visitInfo,
                'last_note' => $lastNote,
                'last_note_date' => $lastNoteDate ? Carbon::parse($lastNoteDate)->format('Y-m-d') : null,
                'notes_list' => $notesList,
                'unified_history' => $unifiedHistory,
                'visits_timeline' => $clientVisits,
            ];
        }

        // ترتيب حسب الأيام تنازلياً
        usort($result, fn ($a, $b) => $b['days_late'] <=> $a['days_late']);

        return $result;
    }

    // =========================================================
    // جلب إجماليات المبيعات لكل العملاء (استعلام واحد)
    // =========================================================
    private function getSalesPerClient(
        Carbon $fromDate,
        Carbon $toDate,
        ?array $branchIds,
        $filterClientId,
        $filterEmployeeId
    ): array {

        // فواتير المبيعات
        $salesQuery = DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
            ->leftJoin('clients', 'je.client_id', '=', 'clients.id')
            ->where('jed.account_id', SystemAccountIds::SALES)
            ->where('jed.credit', '>', 0)
            ->whereBetween('je.date', [$fromDate, $toDate])
            ->where('je.status', 1)
            ->whereNotNull('je.client_id')
            ->whereNotIn('je.reference_type', [
                ReferenceType::SALES_RETURN->value,
                ReferenceType::CREDIT_NOTE->value,
            ]);

        if ($branchIds) {
            $salesQuery->where(function ($q) use ($branchIds) {
                $q->whereIn('clients.branch_id', $branchIds)
                    ->orWhereIn('je.branch_id', $branchIds)
                    ->orWhereExists(fn ($ex) => $ex->select(DB::raw(1))
                        ->from('neighborhoods')
                        ->whereColumn('neighborhoods.client_id', 'clients.id')
                        ->whereIn('neighborhoods.region_id', $branchIds)
                    );
            });
        }
        if ($filterClientId) {
            $salesQuery->where('je.client_id', $filterClientId);
        }
        if ($filterEmployeeId) {
            $salesQuery->where('je.created_by_employee', $filterEmployeeId);
        }

        $salesRows = $salesQuery->select(
            'je.client_id',
            'je.date',
            'je.id as je_id',
            DB::raw('(jed.credit - jed.debit) as net_amount')
        )->orderBy('je.date', 'desc')->get();

        $result = [];
        $invoiceIdsPerClient = [];

        foreach ($salesRows as $row) {
            if (! isset($result[$row->client_id])) {
                $result[$row->client_id] = [
                    'total_sales' => 0,
                    'invoice_count' => 0,
                    'returns_amount' => 0,
                    'history' => [],
                ];
                $invoiceIdsPerClient[$row->client_id] = [];
            }
            $result[$row->client_id]['total_sales'] += (float) $row->net_amount;

            if (! in_array($row->je_id, $invoiceIdsPerClient[$row->client_id])) {
                $invoiceIdsPerClient[$row->client_id][] = $row->je_id;
                $result[$row->client_id]['invoice_count']++;
            }

            $result[$row->client_id]['history'][] = [
                'date' => $row->date,
                'amount' => (float) $row->net_amount,
                'type' => 'مبيعات',
            ];
        }

        // مرتجعات
        $returnsQuery = DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
            ->leftJoin('clients', 'je.client_id', '=', 'clients.id')
            ->whereIn('jed.account_id', [SystemAccountIds::SALES, SystemAccountIds::SALES_RETURNS])
            ->where('jed.debit', '>', 0)
            ->whereBetween('je.date', [$fromDate, $toDate])
            ->where('je.status', 1)
            ->whereNotNull('je.client_id')
            ->whereIn('je.reference_type', [
                ReferenceType::SALES_RETURN->value,
                ReferenceType::CREDIT_NOTE->value,
            ]);

        if ($branchIds) {
            $returnsQuery->where(function ($q) use ($branchIds) {
                $q->whereIn('clients.branch_id', $branchIds)
                    ->orWhereIn('je.branch_id', $branchIds)
                    ->orWhereExists(fn ($ex) => $ex->select(DB::raw(1))
                        ->from('neighborhoods')
                        ->whereColumn('neighborhoods.client_id', 'clients.id')
                        ->whereIn('neighborhoods.region_id', $branchIds)
                    );
            });
        }
        if ($filterClientId) {
            $returnsQuery->where('je.client_id', $filterClientId);
        }
        if ($filterEmployeeId) {
            $returnsQuery->where('je.created_by_employee', $filterEmployeeId);
        }

        $returnsRows = $returnsQuery->select(
            'je.client_id',
            'je.date',
            DB::raw('(jed.debit - jed.credit) as returns_amount')
        )->orderBy('je.date', 'desc')->get();

        // دمج النتائج
        foreach ($returnsRows as $row) {
            if (! isset($result[$row->client_id])) {
                $result[$row->client_id] = ['total_sales' => 0, 'invoice_count' => 0, 'returns_amount' => 0, 'history' => []];
            }
            $result[$row->client_id]['returns_amount'] += (float) $row->returns_amount;

            $result[$row->client_id]['history'][] = [
                'date' => $row->date,
                'amount' => (float) $row->returns_amount,
                'type' => 'مرتجع',
            ];
        }

        // ترتيب التاريخ تنازليا
        foreach ($result as &$clientData) {
            usort($clientData['history'], function ($a, $b) {
                return strtotime($b['date']) <=> strtotime($a['date']);
            });
        }

        return $result;
    }

    // =========================================================
    // جلب إجماليات التحصيلات وآخر تحصيل لكل العملاء
    // =========================================================
    private function getCollectionsPerClient(
        Carbon $fromDate,
        Carbon $toDate,
        ?array $branchIds,
        array $treasuryAccountIds,
        $filterClientId,
        $filterEmployeeId
    ): array {

        if (empty($treasuryAccountIds)) {
            return [];
        }

        $query = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'jed.journal_entry_id', '=', 'je.id')
            ->leftJoin('clients', 'je.client_id', '=', 'clients.id')
            ->whereIn('jed.account_id', $treasuryAccountIds)
            ->where('jed.debit', '>', 0)
            ->whereBetween('je.date', [$fromDate, $toDate])
            ->where('je.status', 1)
            ->whereNotNull('je.client_id')
            // استثناء التحويلات بين الخزائن
            ->whereNotExists(function ($sub) use ($treasuryAccountIds) {
                $sub->select(DB::raw(1))
                    ->from('journal_entry_details as jed2')
                    ->whereColumn('jed2.journal_entry_id', 'jed.journal_entry_id')
                    ->whereIn('jed2.account_id', $treasuryAccountIds)
                    ->where('jed2.credit', '>', 0);
            });

        if ($branchIds) {
            $query->where(function ($q) use ($branchIds) {
                $q->whereIn('clients.branch_id', $branchIds)
                    ->orWhereIn('je.branch_id', $branchIds)
                    ->orWhereExists(fn ($ex) => $ex->select(DB::raw(1))
                        ->from('neighborhoods')
                        ->whereColumn('neighborhoods.client_id', 'clients.id')
                        ->whereIn('neighborhoods.region_id', $branchIds)
                    );
            });
        }
        if ($filterClientId) {
            $query->where('je.client_id', $filterClientId);
        }
        if ($filterEmployeeId) {
            $query->where('je.created_by_employee', $filterEmployeeId);
        }

        $rows = $query->select(
            'je.client_id',
            'je.date',
            'jed.debit'
        )->orderBy('je.date', 'desc')->get();

        $result = [];
        foreach ($rows as $row) {
            if (! isset($result[$row->client_id])) {
                $result[$row->client_id] = [
                    'total_collections' => 0,
                    'last_collection_date' => $row->date,
                    'history' => [],
                ];
            }
            $result[$row->client_id]['total_collections'] += (float) $row->debit;

            $result[$row->client_id]['history'][] = [
                'date' => $row->date,
                'amount' => (float) $row->debit,
            ];
        }

        return $result;
    }

    // =========================================================
    // جلب تاريخ آخر فاتورة لكل عميل (استعلام واحد)
    // =========================================================
    private function getLastInvoiceDates(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $rows = DB::table('journal_entries')
            ->whereIn('client_id', $clientIds)
            ->where('status', 1)
            ->where('reference_type', ReferenceType::SALES_INVOICE->value)
            ->select('client_id', DB::raw('MAX(date) as last_invoice_date'))
            ->groupBy('client_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->client_id] = $row->last_invoice_date;
        }

        return $result;
    }

    // =========================================================
    // جلب بيانات الزيارات التفصيلية لكل عميل
    // =========================================================
    private function getVisitInfoPerClient(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        // آخر زيارة لكل عميل مع الحالة
        $rows = DB::table('employee_client_visits')
            ->whereIn('client_id', $clientIds)
            ->select(
                'client_id',
                DB::raw('MAX(visit_date) as last_visit_date'),
                DB::raw('COUNT(*) as total_visits')
            )
            ->groupBy('client_id')
            ->get();

        // جلب حالة آخر زيارة
        $lastVisitDetails = DB::table('employee_client_visits as v1')
            ->whereIn('v1.client_id', $clientIds)
            ->whereRaw('v1.visit_date = (SELECT MAX(v2.visit_date) FROM employee_client_visits v2 WHERE v2.client_id = v1.client_id)')
            ->select('v1.client_id', 'v1.visit_date', 'v1.status', 'v1.justification', 'v1.justification_approved', 'v1.day_of_week', 'v1.week_number', 'v1.year')
            ->get()
            ->keyBy('client_id');

        $result = [];
        foreach ($rows as $row) {
            $detail = $lastVisitDetails[$row->client_id] ?? null;
            $visitDate = $row->last_visit_date ? Carbon::parse($row->last_visit_date) : null;
            $now = Carbon::now();

            // حساب المدة منذ آخر زيارة
            $timeSinceVisit = null;
            $timeSinceClass = 'secondary';
            if ($visitDate) {
                $diffDays = $visitDate->diffInDays($now);
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
                } elseif ($diffDays <= 365) {
                    $months = ceil($diffDays / 30);
                    $timeSinceVisit = 'منذ '.$months.($months == 1 ? ' شهر' : ' أشهر');
                    $timeSinceClass = 'danger';
                } else {
                    $years = ceil($diffDays / 365);
                    $timeSinceVisit = 'منذ أكثر من '.$years.($years == 1 ? ' سنة' : ' سنوات');
                    $timeSinceClass = 'danger';
                }
            }

            // وصف الحالة
            $statusLabel = 'غير محدد';
            $statusClass = 'secondary';
            if ($detail) {
                if ($detail->status === 'active') {
                    $statusLabel = 'مكتملة';
                    $statusClass = 'success';
                } elseif ($detail->status === 'unactive') {
                    if ($detail->justification_approved == 1) {
                        $statusLabel = 'تبرير مقبول';
                        $statusClass = 'info';
                    } elseif ($detail->justification_approved == 2) {
                        $statusLabel = 'تبرير مرفوض';
                        $statusClass = 'danger';
                    } elseif (! empty($detail->justification)) {
                        $statusLabel = 'بانتظار الموافقة';
                        $statusClass = 'warning';
                    } else {
                        $statusLabel = 'غير مكتملة';
                        $statusClass = 'danger';
                    }
                }
            }

            $result[$row->client_id] = [
                'last_visit_date' => $row->last_visit_date,
                'total_visits' => (int) $row->total_visits,
                'status' => $detail->status ?? null,
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'time_since_visit' => $timeSinceVisit,
                'time_since_class' => $timeSinceClass,
                'justification' => $detail->justification ?? null,
                'day_of_week' => $detail->day_of_week ?? null,
                'week_number' => $detail->week_number ?? null,
            ];
        }

        return $result;
    }

    // =========================================================
    // جلب كل الزيارات مع الأنشطة باستخدام EmployeeClientVisit Model
    // =========================================================
    private function getAllVisitsWithActivity(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        // جلب كل الزيارات باستخدام الـ Model مرتبة تنازلياً
        $visits = EmployeeClientVisit::whereIn('client_id', $clientIds)
            ->orderBy('visit_date', 'desc')
            ->get(['id', 'client_id', 'visit_date', 'status', 'justification', 'justification_approved', 'day_of_week', 'week_number', 'year']);

        if ($visits->isEmpty()) {
            return [];
        }

        // تجميع كل تواريخ الزيارات لكل عميل (بتنسيق Y-m-d)
        $visitDatesPerClient = [];
        foreach ($visits as $v) {
            $d = Carbon::parse($v->visit_date)->format('Y-m-d');
            if (! isset($visitDatesPerClient[$v->client_id])) {
                $visitDatesPerClient[$v->client_id] = [];
            }
            $visitDatesPerClient[$v->client_id][] = $d;
        }

        // جلب الفواتير والمرتجعات لنفس تواريخ الزيارات
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
                DB::raw('DATE(je.date) as visit_day'),
                DB::raw('SUM(CASE WHEN jed.credit > 0 AND je.reference_type NOT IN ("'.ReferenceType::SALES_RETURN->value.'","'.ReferenceType::CREDIT_NOTE->value.'") THEN (jed.credit - jed.debit) ELSE 0 END) as sales_amount'),
                DB::raw('SUM(CASE WHEN jed.debit > 0 AND je.reference_type IN ("'.ReferenceType::SALES_RETURN->value.'","'.ReferenceType::CREDIT_NOTE->value.'") THEN (jed.debit - jed.credit) ELSE 0 END) as return_amount')
            )
            ->groupBy('je.client_id', DB::raw('DATE(je.date)'))
            ->get()
            ->groupBy('client_id')
            ->map(fn ($rows) => $rows->keyBy('visit_day'));

        // جلب التحصيلات
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
                ->whereNotNull('je.client_id')
                ->whereNotExists(function ($sub) use ($treasuryAccountIds) {
                    $sub->select(DB::raw(1))
                        ->from('journal_entry_details as jed2')
                        ->whereColumn('jed2.journal_entry_id', 'jed.journal_entry_id')
                        ->whereIn('jed2.account_id', $treasuryAccountIds)
                        ->where('jed2.credit', '>', 0);
                })
                ->select('je.client_id', DB::raw('DATE(je.date) as visit_day'), DB::raw('SUM(jed.debit) as total_collected'))
                ->groupBy('je.client_id', DB::raw('DATE(je.date)'))
                ->get()
                ->groupBy('client_id')
                ->map(fn ($rows) => $rows->keyBy('visit_day'))
            : collect();

        // بناء النتيجة
        $result = [];
        foreach ($visits as $visit) {
            $cid = $visit->client_id;
            $date = Carbon::parse($visit->visit_date)->format('Y-m-d');

            // حالة الزيارة
            if ($visit->status === 'active') {
                $sLabel = 'مكتملة';
                $sClass = 'success';
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

            // الأنشطة في نفس يوم الزيارة
            $activities = [];
            $invRow = $invoices[$cid][$date] ?? null;
            if ($invRow) {
                if ((float) $invRow->sales_amount > 0) {
                    $activities[] = ['type' => 'فاتورة', 'amount' => (float) $invRow->sales_amount, 'class' => 'success', 'icon' => 'fa-file-text-o'];
                }
                if ((float) $invRow->return_amount > 0) {
                    $activities[] = ['type' => 'مرتجع', 'amount' => (float) $invRow->return_amount, 'class' => 'danger', 'icon' => 'fa-undo'];
                }
            }
            $colRow = $collections[$cid][$date] ?? null;
            if ($colRow && (float) $colRow->total_collected > 0) {
                $activities[] = ['type' => 'تحصيل', 'amount' => (float) $colRow->total_collected, 'class' => 'info', 'icon' => 'fa-money'];
            }

            $result[$cid][] = [
                'visit_date' => $date,
                'status_label' => $sLabel,
                'status_class' => $sClass,
                'justification' => $visit->justification,
                'activities' => $activities,
            ];
        }

        return $result;
    }
}
