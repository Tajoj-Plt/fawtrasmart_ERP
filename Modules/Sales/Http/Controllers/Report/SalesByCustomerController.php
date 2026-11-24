<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\User;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Support\SalesLedger;

class SalesByCustomerController extends Controller
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
        $this->middleware('permission:sales.reports.by_customer');
    }

    use SalesReportTrait;

    /**
     * بحثُ العملاء لمُنتقي الشاشة — محروسٌ بصلاحية التقرير نفسِها.
     */
    public function searchClients(Request $request)
    {
        return $this->searchReportClients($request);
    }

    /**
     * عرض تقرير المبيعات حسب العميل
     * الطريقة الصحيحة: جلب المبيعات من JournalEntryDetail مع account_id = SALES
     * نفس طريقة DashboardController
     */
    public function index(Request $request)
    {
        $isSummary = $request->input('view_type', 'summary') !== 'details';

        // =================== معالجة الفترة الزمنية ===================
        /*
         * مصدرٌ واحد للمدى (SalesReportTrait) بدل نسخةٍ لكل تقرير: كانت
         * «أسبوع» هنا سبعةَ أيامٍ زائدَ اليوم الحالي (٨) وستةً زائدَ اليوم
         * في تقرير البنود (٧)، فيختلف التقريران على نفس الزر — وتختلف
         * الفترة القادمة من صفحة الفهرس عمّا يحسبه الزر داخل الصفحة.
         */
        [$fromDate, $toDate] = $this->parseDateRangeRequest($request);
        $this->paidAsOf = $toDate->copy();

        // =================== فلترة الفرع من الهيدر و Cascade (تحديد الفرع الفعال) ===================
        $user = User::find(auth()->user()->id);
        $userBranchId = $user->branch_id;
        $currentBranch = Branch::find($userBranchId);
        $isMainBranch = $currentBranch && $currentBranch->is_main == 1;

        // تحديد الفرع المحدد من الفلتر أو من الهيدر
        $requestedBranchId = $request->input('branch_id');

        if ($isMainBranch) {
            $selectedBranchId = ($requestedBranchId && $requestedBranchId !== '0' && $requestedBranchId !== 'all' && $requestedBranchId !== '')
                ? $requestedBranchId
                : null;
        } else {
            $selectedBranchId = $userBranchId;
        }

        $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
        $branchIds = $effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null;

        $dropdowns = $this->getFilteredDropdowns($branchIds, withClientList: false);
        extract($dropdowns); // $employees, $regionGroups, $branches, $cascadeBranches, $treeNodes
        $users = $employees;

        $selectedClient = $request->filled('client_id')
            ? \App\Models\Client::find($request->input('client_id'))
            : null;

        $categories = CategoriesClient::all();
        $selectedRegionGroupId = $request->input('region_group_id');
        $selectedBranchId = $isMainBranch ? ($request->input('branch_id') ?: null) : $userBranchId;

        // جلب حسابات الخزينة والبنوك للتحصيلات
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        $groupBy = $request->input('group_by', 'client');

        // =================== جلب المبيعات (بدون لوب لكل عميل) ===================
        $salesQuery = JournalEntry::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereIn('reference_type', SalesLedger::SALE_TYPES)
            // قيدُ تكلفة المبيعات توأمُ الفاتورة (النوعُ نفسُه بلا طرفِ عميل) ليس مبيعاً
            ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
            ->with(['details.account', 'client.status_client', 'client.registrationStage', 'createdByEmployee', 'branch', 'invoice.payments', 'linkedInvoices']);

        $returnsQuery = JournalEntry::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereIn('reference_type', array_merge(SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES))
            ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
            ->with(['details.account', 'client.status_client', 'client.registrationStage', 'createdByEmployee', 'branch']);

        // =================== الفترات المحاسبية المقفلة ===================
        $this->excludeClosedRanges($salesQuery, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($salesQuery);
        $this->excludeClosedRanges($returnsQuery, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($returnsQuery);

        // =================== فلتر «تابع لـ» ===================
        if ($branchIds && $effectiveBranchId) {
            $includeDescendants = ! $request->boolean('exact_node');

            $branchFilter = function ($q) use ($branchIds, $effectiveBranchId, $includeDescendants) {
                $q->whereHas('client', function ($sub) use ($effectiveBranchId, $includeDescendants) {
                    $sub->inTreeNode($effectiveBranchId, $includeDescendants);
                })
                    ->orWhere(function ($sub) use ($branchIds) {
                        $sub->whereNull('client_id')
                            ->whereIn('branch_id', $branchIds);
                    });
            };

            $salesQuery->where($branchFilter);
            $returnsQuery->where($branchFilter);
        }

        // تطبيق الفلاتر
        if ($request->filled('client_id')) {
            $salesQuery->where('client_id', $request->client_id);
            $returnsQuery->where('client_id', $request->client_id);
        }
        if ($request->filled('employee_id')) {
            $salesQuery->where('created_by_employee', $request->employee_id);
            $returnsQuery->where('created_by_employee', $request->employee_id);
        }

        // =================== فلتر حالة العميل ووضعه ===================
        if ($request->filled('status')) {
            $statusFilter = fn ($q) => $request->status === 'no_status'
                ? $q->whereNull('status_id')
                : $q->where('status_id', $request->status);
            $salesQuery->whereHas('client', $statusFilter);
            $returnsQuery->whereHas('client', $statusFilter);
        }
        if ($request->filled('registration_stage')) {
            $stageFilter = fn ($q) => $request->registration_stage === 'no_stage'
                ? $q->whereNull('registration_stage_id')
                : $q->where('registration_stage_id', $request->registration_stage);
            $salesQuery->whereHas('client', $stageFilter);
            $returnsQuery->whereHas('client', $stageFilter);
        }

        $allSalesEntries = $salesQuery->get();
        $allReturnEntries = $returnsQuery->get();

        // التجميع الديناميكي
        $getGroupKey = function ($entry) use ($groupBy) {
            switch ($groupBy) {
                case 'day': return Carbon::parse($entry->date)->format('Y-m-d');
                case 'month': return Carbon::parse($entry->date)->format('Y-m');
                case 'employee': return $entry->created_by_employee ?? 'unknown';
                case 'client': return $entry->client_id ?? 'unknown';
                case 'branch': return $entry->branch_id ?? 'unknown';
                default: return 'unknown';
            }
        };

        $salesGrouped = $allSalesEntries->groupBy($getGroupKey);
        $returnsGrouped = $allReturnEntries->groupBy($getGroupKey);

        $allGroupKeys = $salesGrouped->keys()->merge($returnsGrouped->keys())->unique();

        $reportData = collect();
        $totals = [
            'count' => 0, 'amount' => 0, 'returns_count' => 0, 'returns_amount' => 0,
            'net_amount' => 0, 'paid' => 0, 'unpaid' => 0,
        ];

        foreach ($allGroupKeys as $groupKey) {
            $entries = $salesGrouped->get($groupKey, collect());
            $returnEntries = $returnsGrouped->get($groupKey, collect());

            if ($entries->isEmpty() && $returnEntries->isEmpty()) {
                continue;
            }

            $firstEntry = $entries->first() ?? $returnEntries->first();
            $groupName = 'غير محدد';
            switch ($groupBy) {
                case 'day': $groupName = Carbon::parse($firstEntry->date)->format('Y-m-d');
                    break;
                case 'month': $groupName = Carbon::parse($firstEntry->date)->format('Y-m');
                    break;
                case 'employee': $groupName = $firstEntry->createdByEmployee->name ?? 'غير محدد';
                    break;
                case 'client': $groupName = $firstEntry->client->trade_name ?? 'غير محدد';
                    break;
                case 'branch': $groupName = $firstEntry->branch->name ?? 'غير محدد';
                    break;
            }

            $totalAmount = 0;
            $totalPaid = 0;
            $invoiceCount = 0;
            $totalReturnsAmount = 0;
            $returnsCount = 0;
            $totalCreditNotesAmount = 0;
            $creditNotesCount = 0;
            $itemsData = [];

            foreach ($entries as $entry) {
                // طرفُ العميل — إجماليُّ الفاتورة كما تقرؤه لوحةُ المبيعات (انظر SalesReportTrait::clientLeg)
                [$salesDebit, $salesCredit] = $this->clientLeg($entry);
                $salesAmount = $salesDebit - $salesCredit;
                $entryPayments = $this->getEntryCollections($entry, $treasuryAndBankAccounts);

                $isCreditNote = ($entry->reference_type && $entry->reference_type->value == ReferenceType::CREDIT_NOTE->value);
                if ($isCreditNote) {
                    $totalCreditNotesAmount += abs($salesAmount);
                    $creditNotesCount++;
                } else {
                    $totalAmount += $salesAmount;
                    $invoiceCount++;
                }
                $totalPaid += $entryPayments;

                $originalInvoiceRef = null;
                if ($entry->reference_type && $entry->reference_type->value == ReferenceType::SALES_RETURN->value && $entry->invoice_id) {
                    $originalInvoice = Invoice::find($entry->invoice_id);
                    if ($originalInvoice) {
                        $originalInvoiceRef = $originalInvoice->reference_number ?: $originalInvoice->id;
                    }
                }

                $itemsData[] = [
                    'id' => $entry->id,
                    'reference_number' => $entry->reference_number,
                    'date' => Carbon::parse($entry->date)->format('d/m/Y'),
                    'employee_name' => $entry->createdByEmployee->name ?? '-',
                    'client_name' => $entry->client->trade_name ?? '-',
                    'client_code' => $entry->client->code ?? '-',
                    'client_id' => $entry->client_id,
                    'reference_type' => $entry->reference_type ? $entry->reference_type->getName() : '-',
                    'branch_name' => $entry->branch->name ?? '-',
                    'debit' => $salesDebit,
                    'credit' => $salesCredit,
                    'amount' => $salesAmount,
                    'paid' => $entryPayments,
                    'unpaid' => max($salesAmount - $entryPayments, 0),
                    'is_return' => false,
                    'is_credit_note' => $isCreditNote,
                    'original_invoice_ref' => $originalInvoiceRef,
                    'status' => $isCreditNote ? ['text' => __('sales_reports.status.credit_note'), 'class' => 'info', 'code' => 'credit_note'] : $this->getPaymentStatus($salesAmount, $entryPayments),
                    'client_status_name' => $entry->client->status_client->name ?? null,
                    'client_status_color' => $entry->client->status_client->color ?? null,
                    'client_stage_name' => $entry->client->registrationStage->name ?? null,
                ];
            }

            foreach ($returnEntries as $entry) {
                [$returnDebit, $returnCredit] = $this->clientLeg($entry);
                $returnAmount = $returnCredit;
                // قيدُ ردِّ نقدٍ بلا قيمةِ مرتجع (مدينُ العميل وحدَه) ليس مرتجعاً — يُحتسب في التحصيل
                if ($returnAmount <= 0.009) {
                    continue;
                }

                $isCreditNote = ($entry->reference_type && $entry->reference_type->value == ReferenceType::CREDIT_NOTE->value);
                if ($isCreditNote) {
                    $totalCreditNotesAmount += $returnAmount;
                    $creditNotesCount++;
                } else {
                    $totalReturnsAmount += $returnAmount;
                    $returnsCount++;
                }

                $originalInvoiceRef = null;
                if ($entry->invoice_id) {
                    $originalInvoice = Invoice::find($entry->invoice_id);
                    if ($originalInvoice) {
                        $originalInvoiceRef = $originalInvoice->reference_number ?: $originalInvoice->id;
                    }
                }

                $itemsData[] = [
                    'id' => $entry->id,
                    'reference_number' => $entry->reference_number,
                    'date' => Carbon::parse($entry->date)->format('d/m/Y'),
                    'employee_name' => $entry->createdByEmployee->name ?? '-',
                    'client_name' => $entry->client->trade_name ?? '-',
                    'client_code' => $entry->client->code ?? '-',
                    'client_id' => $entry->client_id,
                    'reference_type' => $entry->reference_type ? $entry->reference_type->getName() : 'مرتجع',
                    'branch_name' => $entry->branch->name ?? '-',
                    'debit' => $returnDebit,
                    'credit' => $returnCredit,
                    'amount' => -$returnAmount,
                    'paid' => 0,
                    'unpaid' => 0,
                    'is_return' => true,
                    'is_credit_note' => $isCreditNote,
                    'original_invoice_ref' => $originalInvoiceRef,
                    'status' => $isCreditNote ? ['text' => __('sales_reports.status.credit_note'), 'class' => 'info', 'code' => 'credit_note'] : ['text' => __('sales_reports.status.return'), 'class' => 'danger', 'code' => 'return'],
                    'client_status_name' => $entry->client->status_client->name ?? null,
                    'client_status_color' => $entry->client->status_client->color ?? null,
                    'client_stage_name' => $entry->client->registrationStage->name ?? null,
                ];
            }

            $netAmount = $totalAmount - $totalReturnsAmount - $totalCreditNotesAmount;
            $totalUnpaidForGroup = max($netAmount - $totalPaid, 0);

            $groupData = [
                'name' => $groupName,
                'count' => $invoiceCount,
                'amount' => $totalAmount,
                'returns_count' => $returnsCount + $creditNotesCount,
                'returns_amount' => $totalReturnsAmount + $totalCreditNotesAmount,
                'net_amount' => $netAmount,
                'paid' => $totalPaid,
                'unpaid' => $totalUnpaidForGroup,
                'status' => $this->getPaymentStatus($netAmount, $totalPaid),
            ];

            // «الحالة»/«الوضع» عمودان يخصّان تجميع «حسب العميل» فقط.
            if ($groupBy === 'client') {
                $firstItemData = $itemsData[0] ?? null;
                $groupData['status_name'] = $firstItemData['client_status_name'] ?? null;
                $groupData['status_color'] = $firstItemData['client_status_color'] ?? null;
                $groupData['stage_name'] = $firstItemData['client_stage_name'] ?? null;
            }

            if (! $isSummary) {
                $groupData['items'] = $itemsData;
            }

            $reportData->put($groupKey, $groupData);

            $totals['count'] += $invoiceCount;
            $totals['amount'] += $totalAmount;
            $totals['returns_count'] += ($returnsCount + $creditNotesCount);
            $totals['returns_amount'] += ($totalReturnsAmount + $totalCreditNotesAmount);
            $totals['net_amount'] += $netAmount;
            $totals['paid'] += $totalPaid;
            $totals['unpaid'] += $totalUnpaidForGroup;
        }

        $totals['unpaid'] = max($totals['net_amount'] - $totals['paid'], 0);

        // الترتيب
        if (in_array($groupBy, ['day', 'month'])) {
            $reportData = $reportData->sortKeysDesc();
        } else {
            $reportData = $reportData->sortByDesc('net_amount');
        }

        // التسميات من ملف اللغة كي تتبع لغة المستخدم كبقية الشاشة
        $groupByOptions = [
            'day' => __('sales_reports.group_by.day'),
            'month' => __('sales_reports.group_by.month'),
            'employee' => __('sales_reports.group_by.employee'),
            'client' => __('sales_reports.group_by.client'),
            'branch' => __('sales_reports.group_by.branch'),
        ];

        return view('sales::sals_report.salesRport.Sales_By_Customer', compact(
            'reportData',
            'employees',
            'categories',
            'branches',
            'users',
            'totals',
            'fromDate',
            'toDate',
            'isSummary',
            'selectedBranchId',
            'isMainBranch',
            'regionGroups',
            'cascadeBranches',
            'treeNodes',
            'effectiveBranchId',
            'selectedClient',
            'selectedRegionGroupId',
            'groupBy',
            'groupByOptions',
            'statuses',
            'registrationStages'
        ));
    }

    /**
     * جلب مبيعات عميل معين من القيود المحاسبية
     * نفس طريقة getTotalSales في DashboardController
     */
    private function getClientSales($clientId, $fromDate, $toDate, $employeeId = null)
    {
        $query = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($clientId, $fromDate, $toDate, $employeeId) {
            $q->whereBetween('date', [$fromDate, $toDate])
                ->where('status', 1)
                ->where('client_id', $clientId);

            if ($employeeId) {
                $q->where('created_by_employee', $employeeId);
            }
        })
            ->where('account_id', SystemAccountIds::SALES);

        $result = $query->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit, COUNT(DISTINCT journal_entry_id) as entry_count')
            ->first();

        $totalCredit = $result->total_credit ?? 0;
        $totalDebit = $result->total_debit ?? 0;

        return [
            'amount' => $totalCredit - $totalDebit,
            'count' => $result->entry_count ?? 0,
        ];
    }

    /**
     * جلب تحصيلات عميل معين من القيود المحاسبية
     * نفس طريقة getCollectionsForBranchByClient في DashboardController
     */
    private function getClientCollections($clientId, $fromDate, $toDate, $treasuryAndBankAccounts, $employeeId = null)
    {
        if (empty($treasuryAndBankAccounts)) {
            return 0;
        }

        $query = JournalEntryDetail::whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function ($q) use ($clientId, $fromDate, $toDate, $employeeId) {
                $q->whereBetween('date', [$fromDate, $toDate])
                    ->where('status', 1)
                    ->where('client_id', $clientId);

                if ($employeeId) {
                    $q->where('created_by_employee', $employeeId);
                }
            });

        // استثناء التحويلات: القيد الذي يحتوي على دائن في حسابات الخزينة/البنوك
        $query->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
            $subQuery->select(DB::raw(1))
                ->from('journal_entry_details as jed2')
                ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                ->where('jed2.credit', '>', 0);
        });

        return $query->sum('debit') ?? 0;
    }

    /**
     * جلب القيود المحاسبية لعميل معين
     */
    private function getClientJournalEntries($clientId, $fromDate, $toDate, $employeeId = null)
    {
        $query = JournalEntry::query()
            ->where('client_id', $clientId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereHas('details', function ($q) {
                $q->where('account_id', SystemAccountIds::SALES);
            })
            ->with(['details.account', 'client', 'createdByEmployee', 'branch', 'invoice.payments', 'linkedInvoices']);

        if ($employeeId) {
            $query->where('created_by_employee', $employeeId);
        }

        return $query->get();
    }

    /**
     * جلب قيود المبيعات فقط (بدون المرتجعات) لعميل معين
     * تستثني القيود التي تحتوي على حساب مردودات المبيعات
     */
    private function getClientSalesEntries($clientId, $fromDate, $toDate, $employeeId = null)
    {
        $query = JournalEntry::query()
            ->where('client_id', $clientId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereIn('reference_type', SalesLedger::SALE_TYPES)
            // قيدُ تكلفة المبيعات توأمُ الفاتورة (النوعُ نفسُه بلا طرفِ عميل) ليس مبيعاً
            ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
            ->with(['details.account', 'client.status_client', 'client.registrationStage', 'createdByEmployee', 'branch', 'invoice.payments', 'linkedInvoices']);

        $this->excludeClosedRanges($query, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($query);

        if ($employeeId) {
            $query->where('created_by_employee', $employeeId);
        }

        return $query->get();
    }

    /**
     * جلب قيود المرتجعات لعميل معين
     * تجلب القيود التي تحتوي على حساب مردودات المبيعات (45)
     */
    private function getClientReturnEntries($clientId, $fromDate, $toDate, $employeeId = null)
    {
        $query = JournalEntry::query()
            ->where('client_id', $clientId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereIn('reference_type', array_merge(SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES))
            ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
            ->with(['details.account', 'client.status_client', 'client.registrationStage', 'createdByEmployee', 'branch']);

        $this->excludeClosedRanges($query, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($query);

        if ($employeeId) {
            $query->where('created_by_employee', $employeeId);
        }

        return $query->get();
    }

    /**
     * جلب المدفوعات لفاتورة معينة
     * الطريقة الصحيحة: جلب المدفوعات من الفاتورة مباشرة عبر invoice_id
     */
    private function getEntryCollections($entry, $treasuryAndBankAccounts)
    {
        // إذا كان القيد مرتبط بفاتورة، نجلب المدفوعات من الفاتورة مباشرة
        if ($entry->invoice_id) {
            $invoice = $entry->invoice;
            if ($invoice) {
                return $this->paidUpToReportEnd($invoice);
            }
        }

        // إذا كان القيد مرتبط بفواتير عبر جدول الربط
        if ($entry->linkedInvoices && $entry->linkedInvoices->count() > 0) {
            $totalPaid = 0;
            foreach ($entry->linkedInvoices as $invoice) {
                $totalPaid += $this->paidUpToReportEnd($invoice);
            }

            return $totalPaid;
        }

        // fallback: لا توجد مدفوعات مرتبطة
        return 0;
    }

    /** نهايةُ مدى التقرير — «المدفوع» يُقرأ حتّاها لا حتّى اليوم. */
    private ?Carbon $paidAsOf = null;

    /**
     * ما سُدِّد من الفاتورة **حتى نهاية مدى التقرير**، بلا الدفعات الفاشلة.
     *
     * كان يجمع مدفوعاتِ الفاتورة كلَّها بلا تاريخ: تقريرُ يوليو يُظهر
     * «مدفوعاً» ما سُدِّد في سبتمبر (٧٠٬١٠٨٫١٦ من ٧٨٤ دفعة لفواتير يوليو)،
     * فينكمش «غير المدفوع» لأيّ فترةٍ ماضية. والحالةُ ٥ دفعةٌ فاشلة
     * (`Invoice::installmentBlockReason` يستبعدها كذلك). ويُقرأ من العلاقة
     * المحمَّلة سلفاً بدل استعلامٍ لكلّ قيد.
     */
    private function paidUpToReportEnd($invoice): float
    {
        $asOf = $this->paidAsOf?->toDateString();

        return (float) $invoice->payments
            ->filter(fn ($payment) => (int) $payment->payment_status !== 5
                && (! $asOf || ! $payment->payment_date || substr((string) $payment->payment_date, 0, 10) <= $asOf))
            ->sum('amount');
    }

    /**
     * جلب التحصيلات لعميل معين
     */
    private function getEntryCollectionsForClient($clientId, $treasuryAndBankAccounts)
    {
        if (empty($treasuryAndBankAccounts) || ! $clientId) {
            return 0;
        }

        $query = JournalEntryDetail::whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function ($q) use ($clientId) {
                $q->where('status', 1)
                    ->where('client_id', $clientId);
            });

        // استثناء التحويلات
        $query->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
            $subQuery->select(DB::raw(1))
                ->from('journal_entry_details as jed2')
                ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                ->where('jed2.credit', '>', 0);
        });

        return $query->sum('debit') ?? 0;
    }

    /**
     * تحديد حالة الدفع
     */
    private function getPaymentStatus($amount, $paid)
    {
        if ($amount <= 0) {
            return ['text' => __('sales_reports.status.unknown'), 'class' => 'secondary', 'code' => 'unknown'];
        }

        $paidPercentage = ($paid / $amount) * 100;

        if ($paidPercentage >= 100) {
            return ['text' => __('sales_reports.status.paid_full'), 'class' => 'success', 'code' => 'paid'];
        } elseif ($paidPercentage > 0) {
            return ['text' => __('sales_reports.status.partial'), 'class' => 'warning', 'code' => 'partial'];
        } else {
            return ['text' => __('sales_reports.status.unpaid'), 'class' => 'danger', 'code' => 'unpaid'];
        }
    }

    /**
     * دالة AJAX لجلب بيانات التقرير
     * نفس طريقة index ولكن تُرجع JSON
     */
    public function ajax(Request $request)
    {
        try {
            // =================== معالجة الفترة الزمنية ===================
            $period = $request->input('period', '30');

            if ($request->filled('date_from') && $request->filled('date_to')) {
                $fromDate = Carbon::parse($request->date_from);
                $toDate = Carbon::parse($request->date_to);
            } else {
                $toDate = now();
                switch ($period) {
                    case '1':
                        $fromDate = now()->startOfDay();
                        break;
                    case '7':
                        $fromDate = now()->subDays(7);
                        break;
                    case '30':
                        $fromDate = now()->subDays(30);
                        break;
                    case '90':
                        $fromDate = now()->subDays(90);
                        break;
                    case '180':
                        $fromDate = now()->subDays(180);
                        break;
                    case '365':
                        $fromDate = now()->subDays(365);
                        break;
                    default:
                        $fromDate = now()->subMonth();
                }
            }

            $isSummary = $request->input('view_type', 'summary') !== 'details';

            // =================== فلترة الفرع من الهيدر (نفس Dashboard) ===================
            $user = User::find(auth()->user()->id);
            $userBranchId = $user->branch_id;
            $currentBranch = Branch::find($userBranchId);
            $isMainBranch = $currentBranch && $currentBranch->is_main == 1;

            // تحديد الفرع المحدد من الفلتر أو من الهيدر
            $requestedBranchId = $request->input('branch_id');

            if ($isMainBranch) {
                $selectedBranchId = ($requestedBranchId && $requestedBranchId !== '0' && $requestedBranchId !== 'all' && $requestedBranchId !== '')
                    ? $requestedBranchId
                    : null;
            } else {
                $selectedBranchId = $userBranchId;
            }

            // جلب حسابات الخزينة والبنوك للتحصيلات
            $treasuryAndBankAccounts = Account::whereIn('parent_id', [
                SystemAccountIds::CASH_TREASURIES_PARENT,
                SystemAccountIds::BANK_ACCOUNTS_PARENT,
            ])->pluck('id')->toArray();

            $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
            $branchIds = $effectiveBranchId ? $this->expandBranchToDescendants($effectiveBranchId) : null;

            // جلب العملاء مع فلترة
            $clientsQuery = Client::query()->with(['branch', 'status_client', 'registrationStage']);

            // تطبيق فلتر «تابع لـ» (يشمل الـ territories) — نفس منطق index()
            if ($branchIds) {
                \App\Support\AffiliationTree::constrainClients($clientsQuery, $branchIds);
            }

            // تطبيق الفلاتر الإضافية على العملاء
            if ($request->filled('client_id')) {
                $clientsQuery->where('id', $request->client_id);
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

            $filteredClients = $clientsQuery->get();

            // معالجة البيانات لكل عميل
            $reportData = collect();
            $totals = [
                'count' => 0,
                'amount' => 0,
                'returns_count' => 0,
                'returns_amount' => 0,
                'net_amount' => 0,
                'paid' => 0,
                'unpaid' => 0,
            ];
            $totalEntries = 0;

            foreach ($filteredClients as $client) {
                // جلب فواتير المبيعات (entries) لهذا العميل مباشرة
                $entries = $this->getClientSalesEntries($client->id, $fromDate, $toDate, $request->employee_id);

                // جلب المرتجعات لهذا العميل
                $returnEntries = $this->getClientReturnEntries($client->id, $fromDate, $toDate, $request->employee_id);

                // تخطي العملاء بدون فواتير ومرتجعات
                if ($entries->isEmpty() && $returnEntries->isEmpty()) {
                    continue;
                }

                // حساب الإجماليات من فواتير المبيعات
                $totalAmount = 0;
                $totalPaid = 0;
                $invoiceCount = 0;

                // حساب المرتجعات والإشعارات
                $totalReturnsAmount = 0;
                $returnsCount = 0;
                $totalCreditNotesAmount = 0;
                $creditNotesCount = 0;

                // معالجة كل فاتورة مبيعات
                $itemsData = [];
                foreach ($entries as $entry) {
                    // طرفُ العميل — إجماليُّ الفاتورة كما تقرؤه لوحةُ المبيعات (انظر SalesReportTrait::clientLeg)
                    [$salesDebit, $salesCredit] = $this->clientLeg($entry);
                    $salesAmount = $salesDebit - $salesCredit;
                    $entryPayments = $this->getEntryCollections($entry, $treasuryAndBankAccounts);

                    $isCreditNote = ($entry->reference_type && $entry->reference_type->value == ReferenceType::CREDIT_NOTE->value);

                    if ($isCreditNote) {
                        $totalCreditNotesAmount += abs($salesAmount);
                        $creditNotesCount++;
                    } else {
                        $totalAmount += $salesAmount;
                        $invoiceCount++;
                    }

                    $totalPaid += $entryPayments;

                    // البحث عن الفاتورة الأصلية إذا كان مرتجعاً
                    $originalInvoiceRef = null;
                    if ($entry->reference_type && $entry->reference_type->value == ReferenceType::SALES_RETURN->value && $entry->invoice_id) {
                        $originalInvoice = Invoice::find($entry->invoice_id);
                        if ($originalInvoice) {
                            $originalInvoiceRef = $originalInvoice->reference_number ?: $originalInvoice->id;
                        }
                    }

                    // حفظ بيانات الفاتورة
                    $itemsData[] = [
                        'id' => $entry->id,
                        'reference_number' => $entry->reference_number,
                        'date' => Carbon::parse($entry->date)->format('d/m/Y'),
                        'employee_name' => $entry->createdByEmployee->name ?? '-',
                        'client_name' => $entry->client->trade_name ?? '-',
                        'client_code' => $entry->client->code ?? '-',
                        'client_id' => $entry->client_id,
                        'reference_type' => $entry->reference_type ? $entry->reference_type->getName() : '-',
                        'branch_name' => $entry->branch->name ?? '-',
                        'debit' => $salesDebit,
                        'credit' => $salesCredit,
                        'amount' => $salesAmount,
                        'paid' => $entryPayments,
                        'unpaid' => max($salesAmount - $entryPayments, 0),
                        'is_return' => false,
                        'is_credit_note' => $isCreditNote,
                        'original_invoice_ref' => $originalInvoiceRef,
                        'status' => $isCreditNote ? ['text' => __('sales_reports.status.credit_note'), 'class' => 'info', 'code' => 'credit_note'] : $this->getPaymentStatus($salesAmount, $entryPayments),
                    ];
                }

                // معالجة كل فاتورة مرتجعات
                foreach ($returnEntries as $entry) {
                    [$returnDebit, $returnCredit] = $this->clientLeg($entry);
                    $returnAmount = $returnCredit;
                    // قيدُ ردِّ نقدٍ بلا قيمةِ مرتجع (مدينُ العميل وحدَه) ليس مرتجعاً — يُحتسب في التحصيل
                    if ($returnAmount <= 0.009) {
                        continue;
                    }

                    $isCreditNote = ($entry->reference_type && $entry->reference_type->value == ReferenceType::CREDIT_NOTE->value);

                    if ($isCreditNote) {
                        $totalCreditNotesAmount += $returnAmount;
                        $creditNotesCount++;
                    } else {
                        $totalReturnsAmount += $returnAmount;
                        $returnsCount++;
                    }

                    // البحث عن الفاتورة الأصلية للمرتجع
                    $originalInvoiceRef = null;
                    if ($entry->invoice_id) {
                        $originalInvoice = Invoice::find($entry->invoice_id);
                        if ($originalInvoice) {
                            $originalInvoiceRef = $originalInvoice->reference_number ?: $originalInvoice->id;
                        }
                    }

                    // حفظ بيانات المرتجع
                    $itemsData[] = [
                        'id' => $entry->id,
                        'reference_number' => $entry->reference_number,
                        'date' => Carbon::parse($entry->date)->format('d/m/Y'),
                        'employee_name' => $entry->createdByEmployee->name ?? '-',
                        'client_name' => $entry->client->trade_name ?? '-',
                        'client_code' => $entry->client->code ?? '-',
                        'client_id' => $entry->client_id,
                        'reference_type' => $entry->reference_type ? $entry->reference_type->getName() : 'مرتجع',
                        'branch_name' => $entry->branch->name ?? '-',
                        'debit' => $returnDebit,
                        'credit' => $returnCredit,
                        'amount' => -$returnAmount,
                        'paid' => 0,
                        'unpaid' => 0,
                        'is_return' => true,
                        'is_credit_note' => $isCreditNote,
                        'original_invoice_ref' => $originalInvoiceRef,
                        'status' => $isCreditNote ? ['text' => __('sales_reports.status.credit_note'), 'class' => 'info', 'code' => 'credit_note'] : ['text' => __('sales_reports.status.return'), 'class' => 'danger', 'code' => 'return'],
                    ];
                }

                // حساب صافي المبيعات
                $netAmount = $totalAmount - $totalReturnsAmount - $totalCreditNotesAmount;
                $totalUnpaidForClient = max($netAmount - $totalPaid, 0);

                $statusFields = [
                    'status_name' => $client->status_client->name ?? null,
                    'status_color' => $client->status_client->color ?? null,
                    'stage_name' => $client->registrationStage->name ?? null,
                ];

                if ($isSummary) {
                    $reportData->put($client->id, array_merge([
                        'name' => $client->trade_name ?? 'غير محدد',
                        'count' => $invoiceCount,
                        'amount' => $totalAmount,
                        'returns_count' => $returnsCount + $creditNotesCount,
                        'returns_amount' => $totalReturnsAmount + $totalCreditNotesAmount,
                        'net_amount' => $netAmount,
                        'paid' => $totalPaid,
                        'unpaid' => $totalUnpaidForClient,
                        'status' => $this->getPaymentStatus($netAmount, $totalPaid),
                    ], $statusFields));
                } else {
                    $reportData->put($client->id, array_merge([
                        'name' => $client->trade_name ?? 'غير محدد',
                        'count' => $invoiceCount,
                        'amount' => $totalAmount,
                        'returns_count' => $returnsCount + $creditNotesCount,
                        'returns_amount' => $totalReturnsAmount + $totalCreditNotesAmount,
                        'net_amount' => $netAmount,
                        'paid' => $totalPaid,
                        'unpaid' => $totalUnpaidForClient,
                        'status' => $this->getPaymentStatus($netAmount, $totalPaid),
                        'items' => $itemsData,
                    ], $statusFields));
                }

                // تجميع الإجماليات
                $totals['count'] += $invoiceCount;
                $totals['amount'] += $totalAmount;
                $totals['returns_count'] += ($returnsCount + $creditNotesCount);
                $totals['returns_amount'] += ($totalReturnsAmount + $totalCreditNotesAmount);
                $totals['net_amount'] += $netAmount;
                $totals['paid'] += $totalPaid;
                $totals['unpaid'] += $totalUnpaidForClient;
                $totalEntries += $invoiceCount;
            }

            // التأكد من دقة إجمالي غير المدفوع في الكروت
            $totals['unpaid'] = max($totals['net_amount'] - $totals['paid'], 0);

            return response()->json([
                'success' => true,
                'data' => $reportData,
                'totals' => $totals,
                'from_date' => $fromDate->format('d/m/Y'),
                'to_date' => $toDate->format('d/m/Y'),
                'count' => $totalEntries,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesByCustomer', 'ajax');

            return response()->json([
                'success' => false,
                'message' => 'خطأ: '.$e->getMessage(),
            ], 500);
        }
    }
}
