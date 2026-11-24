<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\User;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Support\SalesLedger;

class SalesByEmployeeController extends Controller
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
        $this->middleware('permission:sales.reports.by_employee');
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
     * عرض تقرير المبيعات حسب الموظف
     * الطريقة الصحيحة: جلب المبيعات من JournalEntryDetail حسب العميل والموظف
     * مع إمكانية فلترة حسب العميل
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

        // =================== جلب البيانات حسب الموظف والعميل (نفس منطق SalesByCustomerController) ===================
        // بدلاً من جلب الموظفين أولاً، نجلب جميع الفواتير ثم نجمعها حسب الموظف

        // جلب فواتير المبيعات فقط (بدون المرتجعات)
        $salesQuery = JournalEntry::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereIn('reference_type', SalesLedger::SALE_TYPES)
            // قيدُ تكلفة المبيعات توأمُ الفاتورة (النوعُ نفسُه بلا طرفِ عميل) ليس مبيعاً
            ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
            ->with(['details.account', 'client.status_client', 'client.registrationStage', 'createdByEmployee', 'branch', 'invoice.payments', 'linkedInvoices']);

        // جلب المرتجعات فقط
        $returnsQuery = JournalEntry::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereIn('reference_type', array_merge(SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES))
            ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
            ->with(['details.account', 'client.status_client', 'client.registrationStage', 'createdByEmployee', 'branch']);

        // =================== الفترات المحاسبية المقفلة ===================
        // استبعادٌ صامتٌ مطابقٌ لشاشة فواتير المبيعات — على تاريخ القيد.
        $this->excludeClosedRanges($salesQuery, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($salesQuery);
        $this->excludeClosedRanges($returnsQuery, 'journal_entries.date');
        $this->scopeJournalToVisibleUsers($returnsQuery);

        // =================== فلتر «تابع لـ» ===================
        // المطابقةُ على تبعيّة العميل (`Client::scopeInTreeNode`) لا على
        // `clients.branch_id` المشتقّ آلياً — نفسُ منطق SalesByItemController.
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

        // تطبيق فلتر العميل إذا تم تحديده
        if ($request->filled('client_id')) {
            $salesQuery->where('client_id', $request->client_id);
            $returnsQuery->where('client_id', $request->client_id);
        }

        // تطبيق فلتر الموظف إذا تم تحديده
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

        $groupBy = $request->input('group_by', 'employee');

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

        // معالجة البيانات

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
                $entryUnpaid = max($salesAmount - $entryPayments, 0);

                // البحث عن الفاتورة الأصلية إذا كان مرتجعاً
                $originalInvoiceRef = null;
                if ($entry->reference_type && $entry->reference_type->value == ReferenceType::SALES_RETURN->value && $entry->invoice_id) {
                    $originalInvoice = Invoice::find($entry->invoice_id);
                    if ($originalInvoice) {
                        $originalInvoiceRef = $originalInvoice->reference_number ?: $originalInvoice->id;
                    }
                }

                $isCreditNote = ($entry->reference_type && $entry->reference_type->value == ReferenceType::CREDIT_NOTE->value);

                if ($isCreditNote) {
                    $totalCreditNotesAmount += abs($salesAmount);
                    $creditNotesCount++;
                } else {
                    $totalAmount += $salesAmount;
                    $invoiceCount++;
                }

                $totalPaid += $entryPayments;

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
                    'unpaid' => $entryUnpaid,
                    'is_return' => false,
                    'is_credit_note' => $isCreditNote,
                    'original_invoice_ref' => $originalInvoiceRef,
                    'status' => $isCreditNote ? ['text' => __('sales_reports.status.credit_note'), 'class' => 'info', 'code' => 'credit_note'] : $this->getPaymentStatus($salesAmount, $entryPayments),
                    'client_status_name' => $entry->client->status_client->name ?? null,
                    'client_status_color' => $entry->client->status_client->color ?? null,
                    'client_stage_name' => $entry->client->registrationStage->name ?? null,
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
                    'client_status_name' => $entry->client->status_client->name ?? null,
                    'client_status_color' => $entry->client->status_client->color ?? null,
                    'client_stage_name' => $entry->client->registrationStage->name ?? null,
                ];
            }

            // حساب صافي المبيعات
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

            // تجميع الإجماليات
            $totals['count'] += $invoiceCount;
            $totals['amount'] += $totalAmount;
            $totals['returns_count'] += ($returnsCount + $creditNotesCount);
            $totals['returns_amount'] += ($totalReturnsAmount + $totalCreditNotesAmount);
            $totals['net_amount'] += $netAmount;
            $totals['paid'] += $totalPaid;
            $totals['unpaid'] += $totalUnpaidForGroup;
        }

        // التأكد النهائي من دقة إجمالي غير المدفوع
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

        return view('sales::sals_report.salesRport.Sales_By_Employee', compact(
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
            'groupByOptions',
            'groupBy',
            'statuses',
            'registrationStages'
        ));
    }

    /**
     * جلب مبيعات موظف معين من القيود المحاسبية
     * نفس طريقة getClientSales في SalesByCustomerController تماماً
     */
    private function getEmployeeSales($employeeId, $fromDate, $toDate)
    {
        $query = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($employeeId, $fromDate, $toDate) {
            $q->whereBetween('date', [$fromDate, $toDate])
                ->where('status', 1)
                ->where('created_by_employee', $employeeId)
                ->whereDoesntHave('details', function ($d) {
                    $d->where('account_id', SystemAccountIds::SALES_RETURNS);
                });
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
     * جلب تحصيلات موظف معين من القيود المحاسبية
     * نفس طريقة getClientCollections في SalesByCustomerController تماماً
     */
    private function getEmployeeCollections($employeeId, $fromDate, $toDate, $treasuryAndBankAccounts)
    {
        if (empty($treasuryAndBankAccounts)) {
            return 0;
        }

        $query = JournalEntryDetail::whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function ($q) use ($employeeId, $fromDate, $toDate) {
                $q->whereBetween('date', [$fromDate, $toDate])
                    ->where('status', 1)
                    ->where('created_by_employee', $employeeId);
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
     * جلب القيود المحاسبية لموظف معين
     */
    private function getEmployeeJournalEntries($employeeId, $fromDate, $toDate)
    {
        $query = JournalEntry::query()
            ->where('created_by_employee', $employeeId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1)
            ->whereIn('reference_type', SalesLedger::SALE_TYPES)
            // قيدُ تكلفة المبيعات توأمُ الفاتورة (النوعُ نفسُه بلا طرفِ عميل) ليس مبيعاً
            ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
            ->with(['details.account', 'client', 'createdByEmployee', 'branch', 'invoice.payments', 'linkedInvoices']);

        return $query->get();
    }

    /**
     * جلب المدفوعات لفاتورة معينة
     * الطريقة الصحيحة: جلب المدفوعات من الفاتورة مباشرة عبر invoice_id
     */
    /** نهايةُ مدى التقرير — «المدفوع» يُقرأ حتّاها لا حتّى اليوم. */
    private ?Carbon $paidAsOf = null;

    /**
     * ما سُدِّد من الفاتورة حتى نهاية مدى التقرير، بلا الدفعات الفاشلة
     * (الحالة ٥) — انظر شرحها في `SalesByCustomerController::paidUpToReportEnd`.
     */
    private function paidUpToReportEnd($invoice): float
    {
        $asOf = $this->paidAsOf?->toDateString();

        return (float) $invoice->payments
            ->filter(fn ($payment) => (int) $payment->payment_status !== 5
                && (! $asOf || ! $payment->payment_date || substr((string) $payment->payment_date, 0, 10) <= $asOf))
            ->sum('amount');
    }

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

            // =================== جلب البيانات حسب الموظف والعميل (نفس منطق الـ index) ===================
            // جلب فواتير المبيعات فقط (بدون المرتجعات)
            $salesQuery = JournalEntry::query()
                ->whereBetween('date', [$fromDate, $toDate])
                ->where('status', 1)
                ->whereIn('reference_type', SalesLedger::SALE_TYPES)
                // قيدُ تكلفة المبيعات توأمُ الفاتورة (النوعُ نفسُه بلا طرفِ عميل) ليس مبيعاً
                ->whereHas('details.account', fn ($a) => $a->whereNotNull('client_id'))
                ->with(['details.account', 'client.status_client', 'client.registrationStage', 'createdByEmployee', 'branch', 'invoice.payments', 'linkedInvoices']);

            // جلب المرتجعات فقط
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

            // تطبيق فلتر العميل إذا تم تحديده
            if ($request->filled('client_id')) {
                $salesQuery->where('client_id', $request->client_id);
                $returnsQuery->where('client_id', $request->client_id);
            }

            // تطبيق فلتر الموظف إذا تم تحديده
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

            // جمع الفواتير حسب الموظف
            $salesByEmployee = $allSalesEntries->groupBy('created_by_employee');
            $returnsByEmployee = $allReturnEntries->groupBy('created_by_employee');

            // الحصول على جميع معرفات الموظفين
            $allEmployeeIds = $salesByEmployee->keys()->merge($returnsByEmployee->keys())->unique();

            // معالجة البيانات لكل موظف
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

            foreach ($allEmployeeIds as $employeeId) {
                $entries = $salesByEmployee->get($employeeId, collect());
                $returnEntries = $returnsByEmployee->get($employeeId, collect());

                // الحصول على اسم الموظف
                $employeeName = $entries->first()?->createdByEmployee?->name
                    ?? $returnEntries->first()?->createdByEmployee?->name
                    ?? 'غير محدد';

                // تخطي الموظفين بدون فواتير ومرتجعات
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
                        'client_status_name' => $entry->client->status_client->name ?? null,
                        'client_stage_name' => $entry->client->registrationStage->name ?? null,
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
                        'client_status_name' => $entry->client->status_client->name ?? null,
                        'client_stage_name' => $entry->client->registrationStage->name ?? null,
                    ];
                }

                // حساب صافي المبيعات
                $netAmount = $totalAmount - $totalReturnsAmount - $totalCreditNotesAmount;
                $totalUnpaidForClient = max($netAmount - $totalPaid, 0);

                if ($isSummary) {
                    $reportData->put($employeeId, [
                        'name' => $employeeName,
                        'count' => $invoiceCount,
                        'amount' => $totalAmount,
                        'returns_count' => $returnsCount + $creditNotesCount,
                        'returns_amount' => $totalReturnsAmount + $totalCreditNotesAmount,
                        'net_amount' => $netAmount,
                        'paid' => $totalPaid,
                        'unpaid' => $totalUnpaidForClient,
                        'status' => $this->getPaymentStatus($netAmount, $totalPaid),
                    ]);
                } else {
                    $reportData->put($employeeId, [
                        'name' => $employeeName,
                        'count' => $invoiceCount,
                        'amount' => $totalAmount,
                        'returns_count' => $returnsCount + $creditNotesCount,
                        'returns_amount' => $totalReturnsAmount + $totalCreditNotesAmount,
                        'net_amount' => $netAmount,
                        'paid' => $totalPaid,
                        'unpaid' => $totalUnpaidForClient,
                        'status' => $this->getPaymentStatus($netAmount, $totalPaid),
                        'items' => $itemsData,
                    ]);
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
            OperationLog::failure($e, 'Sales.SalesByEmployee', 'ajax');

            return response()->json([
                'success' => false,
                'message' => 'خطأ: '.$e->getMessage(),
            ], 500);
        }
    }
}
