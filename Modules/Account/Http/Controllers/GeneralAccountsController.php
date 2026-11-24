<?php

namespace Modules\Account\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AssetDepreciation;
use App\Models\Branch;
use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpensesCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Receipt;
use App\Models\ReceiptCategory;
use App\Models\Supplier;
use App\Models\TaxSitting;
use App\Models\User;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GeneralAccountsController extends Controller
{
    use AccountingOperations;

    /** شجرةُ الحسابات لهذا الطلب — `accountTree()` */
    private ?array $accountTree = null;

    /** الطلبُ الذي قُرئت له الشجرة: المتحكّمُ قد يُعاد استعماله بين الطلبات */
    private ?int $accountTreeRequest = null;

    /**
     * صلاحيات التقارير المالية — الكتالوج الجديد account.reports.*. لم يكن
     * لهذا المتحكّم أي حماية صلاحياتٍ إطلاقاً من قبل رغم كونه أضخم متحكّمات
     * الوحدة.
     */
    public function __construct()
    {
        $this->middleware('permission.role:account.reports.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.reports.trial_balance', ['only' => ['trialBalance', 'trialBalanceAjax']]);
        $this->middleware('permission.role:account.reports.trial_balance_totals', ['only' => ['trialBalanceTotals', 'trialBalanceTotalsChildren']]);
        $this->middleware('permission.role:account.reports.account_review', ['only' => ['accountReview']]);
        $this->middleware('permission.role:account.reports.journal', ['only' => ['ReportJournal']]);
        $this->middleware('permission.role:account.reports.chart_of_accounts', ['only' => ['ChartOfAccounts']]);
        $this->middleware('permission.role:account.reports.income_statement', [
            'only' => ['incomeStatement', 'incomeStatementAjax', 'exportIncomeStatementExcel', 'exportIncomeStatementPdf', 'printIncomeStatement'],
        ]);
        $this->middleware('permission.role:account.reports.balance_sheet', ['only' => ['balanceSheet']]);
        $this->middleware('permission.role:account.reports.profit_loss', ['only' => ['profitAndLoss', 'profitLossLegacy']]);
        $this->middleware('permission.role:account.reports.cash_flow', ['only' => ['cashFlowStatement']]);
        $this->middleware('permission.role:account.reports.cash_flow_detailed', [
            'only' => ['detailedCashFlow', '_detailedCashFlowOriginal', 'detailedCashFlowAccount'],
        ]);
        $this->middleware('permission.role:account.reports.financial_transactions', ['only' => ['financialTransactions', 'financialTransactionsExport']]);
        $this->middleware('permission.role:account.reports.assets', ['only' => ['assetsReport']]);
        $this->middleware('permission.role:account.reports.inventory_trial_balance', ['only' => ['inventoryTrialBalance']]);
        $this->middleware('permission.role:account.reports.cost_centers', ['only' => ['CostCentersReport', 'CostCentersReportAjax']]);
        $this->middleware('permission.role:account.reports.tax', ['only' => ['taxReport']]);
        $this->middleware('permission.role:account.reports.tax_declaration', ['only' => ['taxDeclaration']]);
        $this->middleware('permission.role:account.reports.expenses', ['only' => ['expensesByCategory', 'expensesByPeriod']]);
        $this->middleware('permission.role:account.reports.receipts', ['only' => ['receiptsByCategory', 'receiptsByPeriod']]);
        $this->middleware('permission.role:account.reports.by_employee', ['only' => ['journalEntriesByEmployee', 'journalEntriesByEmployeeAjax']]);
    }

    // دالة لعرض الحسابات العامة
    public function index()
    {
        return view('account::report.index'); // يعرض الملف الذي يحتوي على الحسابات العامة
    }

    public function taxReport(Request $request)
    {
        $branches = Branch::all();
        $taxSettings = TaxSitting::all();

        /*
            الفترةُ علاقةً أوّلاً، والمدى التاريخيّ حين يُطلب صراحةً — كبقية
            التقارير. وكان التاريخان يُقرآن من الطلب خامَّين بلا افتراض، فيُبنى
            التقريرُ على تاريخ المنشأة كلِّه متى تُركا فارغَين.
        */
        $scope = $this->reportPeriod($request, $request->input('date_type'), $request->from_date, $request->to_date);
        $reportPeriod = $scope['period'];
        [$fromDate, $toDate] = [$scope['from'], $scope['to']];
        $branchFilter = $request->branch;

        // ========================================
        // جلب البيانات من القيود المحاسبية - بناءً على الحسابات فقط
        // ========================================

        // حسابات الضريبة (VAT)
        // 37 = ضريبة القيمة المضافة المحصلة، 2996 = القيمة المضافة المدفوعة
        $taxAccountIds = [AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE), AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID)];

        // جلب جميع معرفات الحسابات لكل نوع من شجرة الحسابات
        $salesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('sales', SystemAccountIds::SALES)); // 44
        $salesReturnsAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('sales_returns', SystemAccountIds::SALES_RETURNS)); // 45
        $purchasesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('purchases', SystemAccountIds::PURCHASES)); // 25
        $expenseAccountIds = $this->getExpenseAccountIds(); // 5 وأبناؤه
        $clientAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('clients_account', SystemAccountIds::CLIENTS_ACCOUNT)); // 18
        $supplierAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('suppliers', SystemAccountIds::SUPPLIERS)); // 29

        // بناء الاستعلام الأساسي للقيود المعتمدة التي تحتوي على ضرائب فقط
        $baseQueryWithTax = function () use ($scope, $branchFilter, $taxAccountIds) {
            $query = JournalEntry::where('status', 1)
                ->whereHas('details', function ($q) use ($taxAccountIds) {
                    $q->whereIn('account_id', $taxAccountIds)
                        ->where(function ($q2) {
                            $q2->where('debit', '>', 0)
                                ->orWhere('credit', '>', 0);
                        });
                });

            /*
                مجموعةُ الدخول بالعلاقة لا بالمدى: القيدُ مختومٌ بفترته لحظةَ
                إنشائه، فالإقرارُ يُبنى على ما نُسب إلى الفترة فعلاً — لا على
                ما وقع تاريخُه بين حدَّين يُحسبان الآن. وإعادةُ ترسيم الفترات
                لا تُعيد كتابةَ إقرارٍ قُدِّم.
            */
            $this->applyReportPeriod($query, $scope);

            if (! empty($branchFilter)) {
                $query->where('branch_id', $branchFilter);
            }

            return $query;
        };

        // ========================================
        // 1. المبيعات - قيود تحتوي على حساب المبيعات (44) + ضريبة محصلة (37)
        // ========================================
        $salesEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($salesAccountIds) {
                $q->whereIn('account_id', $salesAccountIds)
                    ->where('credit', '>', 0); // المبيعات دائن
            })
            ->whereHas('details', function ($q) {
                $q->where('account_id', AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE))
                    ->where('credit', '>', 0); // ضريبة محصلة دائن
            })
            ->whereDoesntHave('details', function ($q) use ($salesReturnsAccountIds) {
                $q->whereIn('account_id', $salesReturnsAccountIds);
            })
            // منتجاتُ الفاتورة وعميلُها مع القيود — كانت تُحمَّل فاتورةً فاتورة وبنداً بنداً
            ->with(['details.account', 'client', 'invoice.client', 'invoice.items.product'])
            ->get();

        $salesData = $this->processEntriesForTax($salesEntries, 'sales');

        // ========================================
        // 2. إشعارات المبيعات المدينة - قيود: عميل مدين + ضريبة محصلة دائن (بدون مبيعات)
        // ========================================
        $salesDebitNoteEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($clientAccountIds) {
                $q->whereIn('account_id', $clientAccountIds)
                    ->where('debit', '>', 0);
            })
            ->whereHas('details', function ($q) {
                $q->where('account_id', AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE))
                    ->where('credit', '>', 0);
            })
            ->whereDoesntHave('details', function ($q) use ($salesAccountIds) {
                $q->whereIn('account_id', $salesAccountIds);
            })
            ->with(['details.account', 'client'])
            ->get();

        $salesDebitNoteData = $this->processEntriesForTax($salesDebitNoteEntries, 'debit_note');

        // ========================================
        // 3. مردودات المبيعات - قيود تحتوي على حساب مردودات المبيعات (45) + ضريبة
        // ========================================
        $salesReturnsEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($salesReturnsAccountIds) {
                $q->whereIn('account_id', $salesReturnsAccountIds)
                    ->where('debit', '>', 0); // مردودات مدين
            })
            // منتجاتُ الفاتورة وعميلُها مع القيود — كانت تُحمَّل فاتورةً فاتورة وبنداً بنداً
            ->with(['details.account', 'client', 'invoice.client', 'invoice.items.product'])
            ->get();

        $salesReturnsData = $this->processEntriesForTax($salesReturnsEntries, 'returns');

        // ========================================
        // 4. المشتريات - قيود تحتوي على حساب المشتريات (25) + ضريبة مدفوعة (2996)
        // ========================================
        $purchasesEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($purchasesAccountIds) {
                $q->whereIn('account_id', $purchasesAccountIds)
                    ->where('debit', '>', 0); // المشتريات مدين
            })
            ->whereHas('details', function ($q) {
                $q->where('account_id', AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID))
                    ->where('debit', '>', 0); // ضريبة مدفوعة مدين
            })
            ->with(['details.account', 'supplier', 'purchaseInvoice.supplier'])
            ->get();

        $purchasesData = $this->processEntriesForTax($purchasesEntries, 'purchases');

        // ========================================
        // 5. مردودات المشتريات - قيود: مورد مدين + ضريبة مدفوعة دائن + يحتوي على حساب المشتريات
        // ========================================
        $purchaseReturnsEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($supplierAccountIds) {
                $q->whereIn('account_id', $supplierAccountIds)
                    ->where('debit', '>', 0); // المورد مدين (رد المبلغ)
            })
            ->whereHas('details', function ($q) {
                $q->where('account_id', AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID))
                    ->where('credit', '>', 0); // ضريبة مدفوعة دائن (استرداد)
            })
            ->whereHas('details', function ($q) use ($purchasesAccountIds) {
                $q->whereIn('account_id', $purchasesAccountIds); // يجب أن يحتوي على حساب المشتريات لتمييزه عن إشعار دائن
            })
            ->with(['details.account', 'supplier'])
            ->get();

        $purchaseReturnsData = $this->processEntriesForTax($purchaseReturnsEntries, 'purchase_returns');

        // ========================================
        // 6. إشعارات المشتريات الدائنة - قيود: مورد مدين + ضريبة مدفوعة دائن (بدون مشتريات)
        // ========================================
        $purchasesCreditNoteEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($supplierAccountIds) {
                $q->whereIn('account_id', $supplierAccountIds)
                    ->where('debit', '>', 0);
            })
            ->whereHas('details', function ($q) {
                $q->where('account_id', AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID))
                    ->where('credit', '>', 0);
            })
            ->whereDoesntHave('details', function ($q) use ($purchasesAccountIds) {
                $q->whereIn('account_id', $purchasesAccountIds);
            })
            ->with(['details.account', 'supplier'])
            ->get();

        $purchasesCreditNoteData = $this->processEntriesForTax($purchasesCreditNoteEntries, 'credit_note');

        // ========================================
        // 7. المصروفات - تُقرأ من جدول Expense مباشرة لضمان توحيد الأرقام مع تقارير المصروفات
        // (expensesByCategory و expensesByPeriod) ولاحتساب أي حساب ضريبة اختاره المستخدم
        // ========================================
        $expensesData = $this->buildExpensesTaxData($fromDate, $toDate, $branchFilter);

        // ========================================
        // حساب الإجماليات والملخص
        // ========================================

        // ضريبة المبيعات = ضريبة المبيعات + ضريبة إشعارات المبيعات المدينة
        $totalSalesTax = $salesData['tax'] + $salesDebitNoteData['tax'];

        // ضريبة مردودات المبيعات (تخصم)
        $salesReturnsTax = $salesReturnsData['tax'];

        // صافي ضريبة المبيعات
        $netSalesTax = $totalSalesTax - $salesReturnsTax;

        // ضريبة المشتريات = ضريبة المشتريات - ضريبة مردودات المشتريات - ضريبة إشعارات الدائنة + ضريبة المصروفات
        $totalPurchasesTax = $purchasesData['tax'];
        $purchaseReturnsTax = $purchaseReturnsData['tax'];
        $purchasesCreditNoteTax = $purchasesCreditNoteData['tax'];
        $expensesTax = $expensesData['tax'];

        // صافي ضريبة المشتريات (المدخلات)
        $netPurchasesTax = $totalPurchasesTax - $purchaseReturnsTax - $purchasesCreditNoteTax + $expensesTax;

        // صافي الضريبة المستحقة = صافي ضريبة المبيعات - صافي ضريبة المشتريات
        $netTaxDue = $netSalesTax - $netPurchasesTax;

        // ========================================
        // إعداد البيانات للتمرير إلى العرض
        // ========================================
        $taxReportData = [
            'from_date' => ! empty($fromDate) ? Carbon::parse($fromDate)->format('d/m/Y') : '',
            'to_date' => ! empty($toDate) ? Carbon::parse($toDate)->format('d/m/Y') : '',

            // فواتير المبيعات
            'sales' => [
                'invoices' => $salesData['invoices'],
                'total' => $salesData['total'],
                'tax' => $salesData['tax'],
                'grand_total' => $salesData['grand_total'],
                'count' => $salesData['count'],
            ],

            // إشعارات المبيعات المدينة
            'sales_debit_notes' => [
                'invoices' => $salesDebitNoteData['invoices'],
                'total' => $salesDebitNoteData['total'],
                'tax' => $salesDebitNoteData['tax'],
                'grand_total' => $salesDebitNoteData['grand_total'],
                'count' => $salesDebitNoteData['count'],
            ],

            // مردودات المبيعات
            'returns' => [
                'invoices' => $salesReturnsData['invoices'],
                'total' => $salesReturnsData['total'],
                'tax' => $salesReturnsData['tax'],
                'grand_total' => $salesReturnsData['grand_total'],
                'count' => $salesReturnsData['count'],
            ],

            // فواتير الشراء
            'purchases' => [
                'invoices' => $purchasesData['invoices'],
                'total' => $purchasesData['total'],
                'tax' => $purchasesData['tax'],
                'grand_total' => $purchasesData['grand_total'],
                'count' => $purchasesData['count'],
            ],

            // مردودات المشتريات
            'purchase_returns' => [
                'invoices' => $purchaseReturnsData['invoices'],
                'total' => $purchaseReturnsData['total'],
                'tax' => $purchaseReturnsData['tax'],
                'grand_total' => $purchaseReturnsData['grand_total'],
                'count' => $purchaseReturnsData['count'],
            ],

            // إشعارات المشتريات الدائنة
            'purchases_credit_notes' => [
                'invoices' => $purchasesCreditNoteData['invoices'],
                'total' => $purchasesCreditNoteData['total'],
                'tax' => $purchasesCreditNoteData['tax'],
                'grand_total' => $purchasesCreditNoteData['grand_total'],
                'count' => $purchasesCreditNoteData['count'],
            ],

            // المصروفات
            'expenses' => [
                'invoices' => $expensesData['invoices'],
                'total' => $expensesData['total'],
                'tax' => $expensesData['tax'],
                'grand_total' => $expensesData['grand_total'],
                'count' => $expensesData['count'],
            ],

            // الملخص
            'summary' => [
                'sales_tax' => $salesData['tax'],
                'sales_debit_notes_tax' => $salesDebitNoteData['tax'],
                'total_sales_tax' => $totalSalesTax,
                'returns_tax' => $salesReturnsTax,
                'net_sales_tax' => $netSalesTax,

                'purchases_tax' => $purchasesData['tax'],
                'purchase_returns_tax' => $purchaseReturnsTax,
                'purchases_credit_notes_tax' => $purchasesCreditNoteTax,
                'expenses_tax' => $expensesTax,
                'net_purchases_tax' => $netPurchasesTax,

                // إجمالي ضريبة المخرجات (المبيعات)
                'total_output_tax' => $netSalesTax,
                // إجمالي ضريبة المدخلات (المشتريات والمصروفات)
                'total_input_tax' => $netPurchasesTax,

                'net_tax_due' => $netTaxDue,

                'total_invoices' => $salesData['count'] + $salesDebitNoteData['count'] +
                                   $salesReturnsData['count'] + $purchasesData['count'] +
                                   $purchaseReturnsData['count'] + $purchasesCreditNoteData['count'] +
                                   $expensesData['count'],
            ],
        ];

        return view('account::report.accountGeneral.tax_report', compact('branches', 'taxSettings', 'taxReportData'));
    }

    /**
     * معالجة القيود المحاسبية لاستخراج بيانات الضرائب
     * Generated by Copilot
     */
    private function processEntriesForTax($entries, $type)
    {
        $invoices = collect();
        $totalAmount = 0;
        $totalTax = 0;
        $totalGrand = 0;

        foreach ($entries as $entry) {
            $details = $entry->details;

            // حساب المبلغ الأساسي والضريبة من تفاصيل القيد
            $entryData = $this->extractTaxDataFromEntry($entry, $details, $type);

            $invoices->push($entryData);

            $totalAmount += abs($entryData['subtotal']);
            $totalTax += abs($entryData['tax']);
            $totalGrand += abs($entryData['grand_total']);
        }

        return [
            'invoices' => $invoices,
            'total' => $totalAmount,
            'tax' => $totalTax,
            'grand_total' => $totalGrand,
            'count' => $entries->count(),
        ];
    }

    /**
     * استخراج بيانات الضريبة من القيد المحاسبي
     * Generated by Copilot
     */
    private function extractTaxDataFromEntry($entry, $details, $type)
    {
        $subtotal = 0;
        $tax = 0;
        $name = 'غير محدد';
        $taxNumber = '';
        $items = '';

        foreach ($details as $detail) {
            $accountId = $detail->account_id;
            $account = $detail->account;

            if (! $account) {
                continue;
            }

            // استخراج الضريبة
            if ($accountId == AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE) || $accountId == AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID)) {
                // ضريبة القيمة المضافة
                $tax += ($detail->credit - $detail->debit);
            }
            // حساب المبيعات أو تكلفة المبيعات
            elseif ($accountId == AccountingSettings::id('sales', SystemAccountIds::SALES) || $this->isChildOf($accountId, AccountingSettings::id('sales', SystemAccountIds::SALES))) {
                $subtotal += ($detail->credit - $detail->debit);
            }
            // حساب مردودات المبيعات
            elseif ($accountId == AccountingSettings::id('sales_returns', SystemAccountIds::SALES_RETURNS) || $this->isChildOf($accountId, AccountingSettings::id('sales_returns', SystemAccountIds::SALES_RETURNS))) {
                $subtotal += ($detail->debit - $detail->credit);
            }
            // حساب المشتريات
            elseif ($accountId == AccountingSettings::id('purchases', SystemAccountIds::PURCHASES) || $this->isChildOf($accountId, AccountingSettings::id('purchases', SystemAccountIds::PURCHASES))) {
                $subtotal += ($detail->debit - $detail->credit);
            }
            // حساب المصروفات
            elseif ($this->isExpenseAccount($accountId)) {
                $subtotal += ($detail->debit - $detail->credit);
            }
        }

        // استخراج اسم العميل/المورد والرقم الضريبي
        if (in_array($type, ['sales', 'returns', 'debit_note'])) {
            if ($entry->client) {
                $name = $entry->client->trade_name ?? $entry->client->name ?? 'غير محدد';
                $taxNumber = $entry->client->tax_number ?? '';
            } elseif ($entry->invoice && $entry->invoice->client) {
                $name = $entry->invoice->client->trade_name ?? 'غير محدد';
                $taxNumber = $entry->invoice->client->tax_number ?? '';
            }
            // جلب أسماء المنتجات من الفاتورة إن وجدت
            if ($entry->invoice && $entry->invoice->items) {
                $items = $entry->invoice->items->pluck('product.name')->filter()->implode(', ');
            }
        } else {
            if ($entry->supplier) {
                $name = $entry->supplier->trade_name ?? $entry->supplier->name ?? 'غير محدد';
                $taxNumber = $entry->supplier->tax_number ?? '';
            } elseif ($entry->purchaseInvoice && $entry->purchaseInvoice->supplier) {
                $name = $entry->purchaseInvoice->supplier->trade_name ?? 'غير محدد';
                $taxNumber = $entry->purchaseInvoice->supplier->tax_number ?? '';
            }
        }

        // لأنواع المصروفات والمردودات، قد تكون القيم سالبة
        if (in_array($type, ['expenses', 'returns', 'purchase_returns', 'credit_note'])) {
            // المبالغ تبقى موجبة للعرض ولكن تعامل كخصم في الحسابات
        }

        return [
            'id' => $entry->id,
            'code' => $entry->reference_number,
            'date' => $entry->date ? $entry->date->format('d/m/Y') : '',
            'name' => $name,
            'tax_number' => $taxNumber,
            'items' => $items,
            'description' => $entry->description ?? "قيد #{$entry->reference_number}",
            'subtotal' => $subtotal,
            'tax' => abs($tax),
            'grand_total' => $subtotal + abs($tax),
        ];
    }

    /**
     * التحقق مما إذا كان الحساب ابناً لحساب معين
     */
    private function isChildOf($accountId, $parentId)
    {
        $parents = $this->accountTree()['parents'];

        if (! array_key_exists((int) $accountId, $parents)) {
            return false;
        }

        // تحقق مباشر
        if ($parents[(int) $accountId] == $parentId) {
            return true;
        }

        // تحقق تكراري (للحسابات متعددة المستويات)
        $currentParentId = $parents[(int) $accountId];
        $safety = 0;

        while ($currentParentId && $safety < 10) {
            if ($currentParentId == $parentId) {
                return true;
            }
            $currentParentId = $parents[(int) $currentParentId] ?? null;
            $safety++;
        }

        return false;
    }

    /**
     * شجرةُ الحسابات في الذاكرة — تُقرأ مرّةً للطلب.
     *
     * كانت دوالُّ الشجرة هنا تسأل القاعدة عن كلّ عقدة: `isChildOf()` بـ`find()`
     * لكلّ أبٍ لكلّ سطر قيد، و`getAllChildAccountIds()` باستعلامٍ لكلّ حسابٍ
     * تنزل إليه. شجرةُ الأصول وحدها ~4,000 استعلام، وتقريرُ الضرائب ~7,700 —
     * ثوانٍ في كلّ طلب. الآن استعلامٌ واحد وخريطتان.
     *
     * @return array{parents: array<int, int|null>, children: array<int, int[]>}
     */
    private function accountTree(): array
    {
        if ($this->accountTree !== null && $this->accountTreeRequest === spl_object_id(request())) {
            return $this->accountTree;
        }
        $this->accountTreeRequest = spl_object_id(request());

        $parents = [];
        $children = [];
        foreach (DB::table('accounts')->orderBy('id')->get(['id', 'parent_id']) as $row) {
            $id = (int) $row->id;
            $parentId = $row->parent_id !== null ? (int) $row->parent_id : null;
            $parents[$id] = $parentId;
            // حسابٌ أبٌ لنفسه لا يُعدّ ابناً لنفسه
            if ($parentId !== null && $parentId !== $id) {
                $children[$parentId][] = $id;
            }
        }

        return $this->accountTree = ['parents' => $parents, 'children' => $children];
    }

    /**
     * التحقق مما إذا كان الحساب حساب مصروفات
     */
    private function isExpenseAccount($accountId)
    {
        // حساب المصروفات الرئيسي هو 5
        if ($accountId == AccountingSettings::id('expenses', SystemAccountIds::EXPENSES)) {
            return true;
        }

        return $this->isChildOf($accountId, AccountingSettings::id('expenses', SystemAccountIds::EXPENSES));
    }

    /**
     * جلب جميع معرفات حسابات المصروفات (الحساب الرئيسي وجميع أبنائه)
     */
    private function getExpenseAccountIds()
    {
        $expenseIds = [AccountingSettings::id('expenses', SystemAccountIds::EXPENSES)];

        // جلب جميع الحسابات الفرعية للمصروفات بشكل تكراري
        $this->getAllChildAccountIds(AccountingSettings::id('expenses', SystemAccountIds::EXPENSES), $expenseIds);

        return $expenseIds;
    }

    /**
     * جلب جميع معرفات الحسابات الفرعية لحساب معين بشكل تكراري
     */
    private function getAllChildAccountIds($parentId, &$ids)
    {
        // نفس الترتيب القديم (عمقاً أوّلاً، الأبناء بترتيب المعرّف) — من الخريطة لا من القاعدة
        $children = $this->accountTree()['children'];
        $seen = array_fill_keys(array_map('intval', $ids), true);
        $stack = array_reverse($children[(int) $parentId] ?? []);

        while ($stack) {
            $childId = array_pop($stack);
            if (isset($seen[$childId])) {
                continue;
            }
            $seen[$childId] = true;
            $ids[] = $childId;
            foreach (array_reverse($children[$childId] ?? []) as $grandChildId) {
                $stack[] = $grandChildId;
            }
        }
    }

    /**
     * جلب معرف الحساب الرئيسي وجميع أبنائه
     * دالة عامة تستخدم لأي حساب
     */
    private function getAccountIdsWithChildren($accountId)
    {
        $ids = [$accountId];
        $this->getAllChildAccountIds($accountId, $ids);

        return $ids;
    }

    /**
     * بناء بيانات ضريبة المصروفات مباشرة من جدول Expense
     * ليتطابق الإجمالي مع تقريرَي expensesByCategory و expensesByPeriod
     */
    private function buildExpensesTaxData($fromDate, $toDate, $branchFilter)
    {
        // المعكوسُ لا يدخل إقرارَ الضريبة كما لا يدخل تقريرَ المصروفات
        $query = Expense::query()
            ->notReversed()
            ->with(['Supplier', 'expenses_category', 'account']);

        if (! empty($fromDate) && ! empty($toDate)) {
            $query->whereBetween('date', [$fromDate, $toDate]);
        }

        if (! empty($branchFilter)) {
            $query->where('branch_id', $branchFilter);
        }

        $expenses = $query->orderBy('date', 'desc')->get();

        // ربط كل مصروف برقم القيد المحاسبي إن وُجد (لعرضه في الجدول)
        $expenseCodes = $expenses->pluck('code')->filter()->toArray();
        $journalEntries = [];
        if (! empty($expenseCodes)) {
            $journalEntries = JournalEntry::where('reference_type', 'EXP')
                ->whereIn('reference_number', $expenseCodes)
                ->pluck('id', 'reference_number')
                ->toArray();
        }

        $invoices = collect();
        $totalAmount = 0;
        $totalTax = 0;
        $totalGrand = 0;

        foreach ($expenses as $expense) {
            $amount = floatval($expense->amount ?? 0);
            $tax1 = floatval($expense->tax1_amount ?? 0);
            $tax2 = floatval($expense->tax2_amount ?? 0);
            $tax = $tax1 + $tax2;
            $grandTotal = $amount + $tax;

            $name = 'غير محدد';
            $taxNumber = '';
            if ($expense->Supplier) {
                $name = $expense->Supplier->trade_name ?? $expense->Supplier->name ?? 'غير محدد';
                $taxNumber = $expense->Supplier->tax_number ?? '';
            } elseif (! empty($expense->seller)) {
                $name = $expense->seller;
            } elseif ($expense->account) {
                $name = $expense->account->name ?? 'غير محدد';
            }

            $description = $expense->description
                ?? ($expense->expenses_category->name ?? 'مصروف');

            $entryDate = $expense->date instanceof Carbon
                ? $expense->date->format('d/m/Y')
                : (! empty($expense->date) ? Carbon::parse($expense->date)->format('d/m/Y') : '');

            $invoices->push([
                'id' => $journalEntries[$expense->code] ?? $expense->id,
                'code' => $expense->code,
                'date' => $entryDate,
                'name' => $name,
                'tax_number' => $taxNumber,
                'items' => '',
                'description' => $description,
                'subtotal' => $amount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
            ]);

            $totalAmount += $amount;
            $totalTax += $tax;
            $totalGrand += $grandTotal;
        }

        return [
            'invoices' => $invoices,
            'total' => $totalAmount,
            'tax' => $totalTax,
            'grand_total' => $totalGrand,
            'count' => $expenses->count(),
        ];
    }

    /**
     * جلب معرفات كل حسابات البنوك والخزائن (الأب وأبناؤهم بأي مستوى).
     * تُستخدم لتحديد ما هو "تدفق نقدي تفصيلي": أي قيد أحد أطرافه أحد هذه الحسابات.
     *
     * كان هنا دمجٌ لأربعة آباء: 121 و122 بوصفهما «الأب الجديد» للخزائن والبنوك،
     * و13 و15 «للتوافق». لكن 121 و122 كودان لا معرّفان — والحسابان بهذين
     * المعرّفين هما «احتياطي طوارئ» و«احتياطي توسعات»، فكانت حركاتُ احتياطيَّي
     * حقوق الملكية تُحسب تدفقاً نقدياً. الأب الحقيقي للخزائن 13 وللبنوك 15.
     */
    private function getCashFlowAccountIds()
    {
        $ids = array_merge(
            $this->getAccountIdsWithChildren(AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT)),   // 13 - الخزينة (كود 121)
            $this->getAccountIdsWithChildren(AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))      // 15 - البنك (كود 122)
        );

        return array_values(array_unique($ids));
    }

    /**
     * تحويل نوع الفترة (today/this_month/...) إلى نطاق تواريخ Y-m-d.
     * إذا كان dateType = custom أو فارغاً، يُستخدم from/to المُمرَّران (إن وُجدا).
     */
    /**
     * فترةُ التقرير — علاقةً أوّلاً، ومدىً تاريخياً حين يُطلب صراحةً.
     *
     * **التقاريرُ وحدها تقبل الاثنين.** فالقائمةُ التشغيلية دفترُ فترة،
     * أمّا التقريرُ فقد يُطلب على مدىً يقطع الفترات — كأسبوعٍ بين شهرين، أو
     * مقارنةٍ بين سنتين — فمنعُ المدى الحرّ فيه يمنع سؤالاً مشروعاً.
     *
     * والأولوية للمدى المكتوب: من كتب تاريخين فقد طلبهما.
     *
     * @return array{mode: string, from: ?string, to: ?string, period: array}
     */
    protected function reportPeriod(Request $request, $dateType = null, $from = null, $to = null): array
    {
        $period = AccountingPeriodFilter::resolve($request);

        /*
            **ترتيبُ الأولوية — وكان مقلوباً.**

            كان `date_type` يَجُبّ التاريخين والفترةَ معاً: النموذجُ يُرسل
            `date_type` دائماً (قائمةٌ لها قيمةٌ افتراضية)، فأيُّ بحثٍ يعود
            بالنتيجة نفسِها مهما اختار المستخدمُ من فترةٍ أو كتب من تاريخ —
            وهو عطلُ «البحث لا يعمل، تبقى النتائج كما هي».

            والصوابُ أن يتقدّم ما اختاره المستخدمُ صراحةً:

              ١) فترةٌ أو سنةٌ مختارةٌ بمعرّفها — وهي **علاقة** لا مدى.
              ٢) تاريخان كتبهما بيده.
              ٣) مدًى سريع من `date_type`.
              ٤) الافتراضي: السنة المالية الجارية بعلاقتها.
        */

        /*
            تاريخان كتبهما المستخدم بيده **يخالفان** حدَّي الفترة المختارة
            يتقدّمان عليها — كما تقول الشاشة («مدًى حرّ يتقدّم على الفترة»).

            القائمةُ تُرسل السنةَ الجارية دائماً (خيارُها الافتراضي)، واختيارُ
            فترةٍ يملأ الحقلين بحدّيها. فكان كلُّ مدًى يُكتب بعد ذلك — أسبوعٌ
            بين شهرين مثلاً — يُهمَل وتُقرأ السنةُ كلُّها. والتاريخان المطابقان
            لحدّي الفترة هما الفترةُ نفسُها، فتبقى تُقرأ بعلاقتها.
        */
        $typedRange = ! empty($from) || ! empty($to);
        $matchesPeriod = (string) $from === (string) ($period['from'] ?? '')
            && (string) $to === (string) ($period['to'] ?? '');

        if ($typedRange && ! $matchesPeriod && ! $period['none']) {
            [$rangeFrom, $rangeTo] = $this->resolveDateRange('custom', $from, $to);

            return ['mode' => 'dates', 'from' => $rangeFrom, 'to' => $rangeTo, 'period' => $period];
        }

        // ١) اختيارٌ صريحٌ من قائمة الفترات — «كل الفترات» و«بلا فترة» منه
        if ($request->filled('period_id')) {
            if ($period['all'] || $period['no_calendar']) {
                return ['mode' => 'all', 'from' => null, 'to' => null, 'period' => $period];
            }

            return [
                'mode' => 'period',
                'from' => $period['from'],
                'to' => $period['to'],
                'period' => $period,
            ];
        }

        // ٢) تاريخان مكتوبان
        if (! empty($from) || ! empty($to)) {
            [$rangeFrom, $rangeTo] = $this->resolveDateRange('custom', $from, $to);

            return ['mode' => 'dates', 'from' => $rangeFrom, 'to' => $rangeTo, 'period' => $period];
        }

        // ٣) مدًى سريع
        if ($dateType !== null && $dateType !== 'custom') {
            [$rangeFrom, $rangeTo] = $this->resolveDateRange($dateType, null, null);

            return ['mode' => 'dates', 'from' => $rangeFrom, 'to' => $rangeTo, 'period' => $period];
        }

        return [
            // لا سنةَ مالية ⇒ لا معيارَ يُحتكم إليه، فلا حصر
            'mode' => ($period['all'] || $period['no_calendar']) ? 'all' : 'period',
            'from' => $period['from'],
            'to' => $period['to'],
            'period' => $period,
        ];
    }

    /**
     * تقييدُ استعلامِ قيودٍ بفترة التقرير.
     *
     * `$alias` اسمُ جدول القيود في الاستعلام (`journal_entries` أو لقبُه).
     */
    protected function applyReportPeriod($query, array $scope, string $alias = 'journal_entries')
    {
        if ($scope['mode'] === 'all') {
            return $query;
        }

        if ($scope['mode'] === 'dates') {
            if ($scope['from']) {
                $query->whereDate($alias.'.date', '>=', $scope['from']);
            }

            if ($scope['to']) {
                $query->whereDate($alias.'.date', '<=', $scope['to']);
            }

            return $query;
        }

        // الوضعُ الأصل: العلاقة — فترةً كانت أو سنةً بفتراتها
        return AccountingPeriodFilter::applyRaw($query, $scope['period'], $alias.'.accounting_period_id');
    }

    private function resolveDateRange($dateType, $from, $to)
    {
        $now = Carbon::now();

        switch ($dateType) {
            case 'today':
                return [$now->copy()->startOfDay()->toDateString(), $now->copy()->endOfDay()->toDateString()];
            case 'yesterday':
                $y = $now->copy()->subDay();

                return [$y->copy()->startOfDay()->toDateString(), $y->copy()->endOfDay()->toDateString()];
            case 'this_week':
                return [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()];
            case 'last_week':
                $lw = $now->copy()->subWeek();

                return [$lw->copy()->startOfWeek()->toDateString(), $lw->copy()->endOfWeek()->toDateString()];
            case 'this_month':
                return [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()];
            case 'last_month':
                $lm = $now->copy()->subMonth();

                return [$lm->copy()->startOfMonth()->toDateString(), $lm->copy()->endOfMonth()->toDateString()];
            case 'this_quarter':
                return [$now->copy()->startOfQuarter()->toDateString(), $now->copy()->endOfQuarter()->toDateString()];
            case 'last_quarter':
                $lq = $now->copy()->subQuarter();

                return [$lq->copy()->startOfQuarter()->toDateString(), $lq->copy()->endOfQuarter()->toDateString()];
            case 'this_year':
                return [$this->fiscalYearStart($now)->toDateString(), $this->fiscalYearEnd($now)->toDateString()];
            case 'last_year':
                $ly = $now->copy()->subYear();

                return [$this->fiscalYearStart($ly)->toDateString(), $this->fiscalYearEnd($ly)->toDateString()];
            case 'custom':
            default:
                /*
                    **التقريرُ يفتح على الفترة المحاسبية الجارية.**

                    كان المدى يعود `[null, null]` حين لا يكتب المستخدم شيئاً،
                    فيُبنى التقريرُ على تاريخ المنشأة كلِّه: «قائمة الدخل»
                    تجمع سنواتٍ في رقمٍ واحد، و«الإقرار الضريبي» يخرج بمجموعِ
                    ما سبق لا بما يخصّ الفترة.

                    والحدُّ المكتوبُ صراحةً يتقدّم دائماً، ولا يُفرَض افتراضٌ
                    إن لم تكن ثمّة فترةٌ تحوي اليوم — فلا تُخفى بياناتٌ بصمت.
                */
                $default = (empty($from) && empty($to))
                    ? $this->defaultAccountingRange()
                    : ['from' => null, 'to' => null];

                return [
                    ! empty($from) ? Carbon::parse($from)->toDateString() : $default['from'],
                    ! empty($to) ? Carbon::parse($to)->toDateString() : $default['to'],
                ];
        }
    }

    public function taxDeclaration(Request $request)
    {
        $branches = Branch::all();
        $taxSettings = TaxSitting::all();

        // ========================================
        // قراءة الفلاتر من الطلب
        // ========================================
        $branchFilter = $request->input('branch');
        $currencyFilter = $request->input('currency');
        $referenceFilter = trim($request->input('reference_number', ''));
        $searchFilter = trim($request->input('search', ''));
        $sectionFilter = $request->input('section'); // sales/purchases/expenses/returns/...
        $dateType = $request->input('date_type', 'custom');

        // تحديد الفترة الزمنية - دعم النطاقات السريعة مثل اليوم/الشهر/الربع
        /*
            **الفترةُ علاقةً أوّلاً، والمدى التاريخيّ حين يُطلب صراحةً.**

            التقريرُ وحده يقبل الاثنين: قد يُطلب على مدىً يقطع الفترات —
            كأسبوعٍ بين شهرين — والقائمةُ التشغيلية لا تُطلب كذلك.
        */
        $scope = $this->reportPeriod($request, $dateType, $request->input('from_date'), $request->input('to_date'));
        $reportPeriod = $scope['period'];
        [$fromDate, $toDate] = [$scope['from'], $scope['to']];

        // ========================================
        // جلب البيانات من القيود المحاسبية - بناءً على الحسابات فقط
        // ========================================

        // حسابات الضريبة
        $vatPayableId = AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE); // ضريبة محصلة
        $vatPaidId = AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID); // ضريبة مدفوعة
        $taxAccountIds = [$vatPayableId, $vatPaidId];

        // جلب معرفات الحسابات
        $salesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('sales', SystemAccountIds::SALES)); // 44
        $salesReturnsAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('sales_returns', SystemAccountIds::SALES_RETURNS)); // 45
        $purchasesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('purchases', SystemAccountIds::PURCHASES)); // 25
        $expenseAccountIds = $this->getExpenseAccountIds(); // 5 وأبناؤه
        $clientAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('clients_account', SystemAccountIds::CLIENTS_ACCOUNT)); // 18
        $supplierAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('suppliers', SystemAccountIds::SUPPLIERS)); // 29

        // بناء الاستعلام الأساسي للقيود المعتمدة التي تحتوي على ضرائب
        $baseQueryWithTax = function () use ($scope, $branchFilter, $currencyFilter, $referenceFilter, $searchFilter, $taxAccountIds) {
            $query = JournalEntry::with(['details.account'])
                ->where('status', 1) // 1 = معتمد
                ->whereHas('details', function ($q) use ($taxAccountIds) {
                    $q->whereIn('account_id', $taxAccountIds)
                        ->where(function ($sq) {
                            $sq->where('debit', '>', 0)->orWhere('credit', '>', 0);
                        });
                });

            /*
                مجموعةُ الدخول بالعلاقة لا بالمدى: القيدُ مختومٌ بفترته لحظةَ
                إنشائه، فالإقرارُ يُبنى على ما نُسب إلى الفترة فعلاً — لا على
                ما وقع تاريخُه بين حدَّين يُحسبان الآن. وإعادةُ ترسيم الفترات
                لا تُعيد كتابةَ إقرارٍ قُدِّم.
            */
            $this->applyReportPeriod($query, $scope);
            if (! empty($branchFilter)) {
                $query->where('branch_id', $branchFilter);
            }
            if (! empty($currencyFilter)) {
                $query->where('currency', $currencyFilter);
            }
            if ($referenceFilter !== '') {
                $query->where('reference_number', 'LIKE', "%{$referenceFilter}%");
            }
            if ($searchFilter !== '') {
                $query->where(function ($q) use ($searchFilter) {
                    $q->where('description', 'LIKE', "%{$searchFilter}%")
                        ->orWhere('reference_number', 'LIKE', "%{$searchFilter}%")
                        ->orWhereHas('client', function ($cq) use ($searchFilter) {
                            $cq->where('trade_name', 'LIKE', "%{$searchFilter}%")
                                ->orWhere('first_name', 'LIKE', "%{$searchFilter}%");
                        })
                        ->orWhereHas('supplier', function ($sq) use ($searchFilter) {
                            $sq->where('trade_name', 'LIKE', "%{$searchFilter}%")
                                ->orWhere('first_name', 'LIKE', "%{$searchFilter}%");
                        });
                });
            }

            return $query;
        };

        // ========================================
        // حساب ضريبة المخرجات (المبيعات)
        // ========================================

        // 1. المبيعات العادية
        $salesEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($salesAccountIds) {
                $q->whereIn('account_id', $salesAccountIds)->where('credit', '>', 0);
            })
            ->whereHas('details', function ($q) use ($vatPayableId) {
                $q->where('account_id', $vatPayableId)->where('credit', '>', 0);
            })
            ->get();

        // 2. إشعارات المبيعات المدينة (عميل مدين + ضريبة محصلة دائن بدون مبيعات)
        $salesDebitNoteEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($clientAccountIds) {
                $q->whereIn('account_id', $clientAccountIds)->where('debit', '>', 0);
            })
            ->whereHas('details', function ($q) use ($vatPayableId) {
                $q->where('account_id', $vatPayableId)->where('credit', '>', 0);
            })
            ->whereDoesntHave('details', function ($q) use ($salesAccountIds) {
                $q->whereIn('account_id', $salesAccountIds);
            })
            ->get();

        // 3. مردودات المبيعات
        $salesReturnsEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($salesReturnsAccountIds) {
                $q->whereIn('account_id', $salesReturnsAccountIds)->where('debit', '>', 0);
            })
            ->get();

        // ========================================
        // حساب ضريبة المدخلات (المشتريات والمصروفات)
        // ========================================

        // 4. المشتريات العادية
        $purchasesEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($purchasesAccountIds) {
                $q->whereIn('account_id', $purchasesAccountIds)->where('debit', '>', 0);
            })
            ->whereHas('details', function ($q) use ($vatPaidId) {
                $q->where('account_id', $vatPaidId)->where('debit', '>', 0);
            })
            ->get();

        // 5. مردودات المشتريات (يجب أن تحتوي على حساب المشتريات للتمييز عن إشعارات الدائنة)
        $purchaseReturnsEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($supplierAccountIds) {
                $q->whereIn('account_id', $supplierAccountIds)->where('debit', '>', 0);
            })
            ->whereHas('details', function ($q) use ($vatPaidId) {
                $q->where('account_id', $vatPaidId)->where('credit', '>', 0);
            })
            ->whereHas('details', function ($q) use ($purchasesAccountIds) {
                $q->whereIn('account_id', $purchasesAccountIds);
            })
            ->get();

        // 6. إشعارات المشتريات الدائنة
        $purchasesCreditNoteEntries = $baseQueryWithTax()
            ->whereHas('details', function ($q) use ($supplierAccountIds) {
                $q->whereIn('account_id', $supplierAccountIds)->where('debit', '>', 0);
            })
            ->whereHas('details', function ($q) use ($vatPaidId) {
                $q->where('account_id', $vatPaidId)->where('credit', '>', 0);
            })
            ->whereDoesntHave('details', function ($q) use ($purchasesAccountIds) {
                $q->whereIn('account_id', $purchasesAccountIds);
            })
            ->get();

        // 7. المصروفات تُقرأ من جدول Expense مباشرة (لتطابق taxReport و expensesByCategory)

        // ========================================
        // استخراج البيانات من القيود
        // ========================================

        $salesData = $this->extractDeclarationData($salesEntries, $vatPayableId, 'credit', $salesAccountIds, 'credit');
        $salesDebitNoteData = $this->extractDeclarationData($salesDebitNoteEntries, $vatPayableId, 'credit', $clientAccountIds, 'debit');
        $salesReturnsData = $this->extractDeclarationData($salesReturnsEntries, $vatPayableId, 'debit', $salesReturnsAccountIds, 'debit');

        $purchasesData = $this->extractDeclarationData($purchasesEntries, $vatPaidId, 'debit', $purchasesAccountIds, 'debit');
        $purchaseReturnsData = $this->extractDeclarationData($purchaseReturnsEntries, $vatPaidId, 'credit', $supplierAccountIds, 'debit');
        $purchasesCreditNoteData = $this->extractDeclarationData($purchasesCreditNoteEntries, $vatPaidId, 'credit', $supplierAccountIds, 'debit');

        // المصروفات من جدول Expense - مصدر الحقيقة الذي أدخله المستخدم
        $expensesRaw = $this->buildExpensesTaxData($fromDate, $toDate, $branchFilter);
        $expensesData = [
            'tax' => $expensesRaw['tax'],
            'base' => $expensesRaw['total'],
            'count' => $expensesRaw['count'],
        ];

        // ========================================
        // حساب الإجماليات
        // ========================================

        // ضريبة المخرجات = مبيعات + إشعارات مدينة - مردودات
        $totalOutputTax = $salesData['tax'] + $salesDebitNoteData['tax'] - $salesReturnsData['tax'];
        $totalOutputBase = $salesData['base'] + $salesDebitNoteData['base'] - $salesReturnsData['base'];
        $totalOutputEntries = $salesData['count'] + $salesDebitNoteData['count'] + $salesReturnsData['count'];

        // ضريبة المدخلات = مشتريات + مصروفات - مردودات - إشعارات دائنة
        $totalInputTax = $purchasesData['tax'] + $expensesData['tax'] - $purchaseReturnsData['tax'] - $purchasesCreditNoteData['tax'];
        $totalInputBase = $purchasesData['base'] + $expensesData['base'] - $purchaseReturnsData['base'] - $purchasesCreditNoteData['base'];
        $totalInputEntries = $purchasesData['count'] + $expensesData['count'] + $purchaseReturnsData['count'] + $purchasesCreditNoteData['count'];

        // صافي الضريبة
        $netTaxDue = $totalOutputTax - $totalInputTax;
        $totalEntries = $totalOutputEntries + $totalInputEntries;

        // ========================================
        // إعداد بيانات الجداول - 3 أقسام منفصلة
        // ========================================

        // 1. جدول المبيعات - فقط المبيعات الخاضعة للضريبة
        $salesTaxDeclaration = collect();
        if ($salesData['tax'] > 0 || $salesData['base'] > 0) {
            $salesTaxDeclaration->push([
                'tax_name' => 'مبيعات خاضعة للضريبة',
                'base_amount' => $salesData['base'],
                'tax_amount' => $salesData['tax'],
                'total_amount' => $salesData['base'] + $salesData['tax'],
                'invoices_count' => $salesData['count'],
            ]);
        }

        // 2. جدول المشتريات - فقط المشتريات الخاضعة للضريبة
        $purchasesTaxDeclaration = collect();
        if ($purchasesData['tax'] > 0 || $purchasesData['base'] > 0) {
            $purchasesTaxDeclaration->push([
                'tax_name' => 'مشتريات خاضعة للضريبة',
                'base_amount' => $purchasesData['base'],
                'tax_amount' => $purchasesData['tax'],
                'total_amount' => $purchasesData['base'] + $purchasesData['tax'],
                'invoices_count' => $purchasesData['count'],
            ]);
        }

        // 3. جدول أخرى - كل شيء آخر (مصروفات، مردودات، إشعارات)
        $otherTaxDeclaration = collect();

        // إشعارات المبيعات المدينة
        if ($salesDebitNoteData['tax'] > 0 || $salesDebitNoteData['base'] > 0) {
            $otherTaxDeclaration->push([
                'tax_name' => 'إشعارات مبيعات مدينة',
                'base_amount' => $salesDebitNoteData['base'],
                'tax_amount' => $salesDebitNoteData['tax'],
                'total_amount' => $salesDebitNoteData['base'] + $salesDebitNoteData['tax'],
                'invoices_count' => $salesDebitNoteData['count'],
                'type' => 'output', // ضريبة مخرجات
            ]);
        }

        // مردودات المبيعات
        if ($salesReturnsData['tax'] > 0 || $salesReturnsData['base'] > 0) {
            $otherTaxDeclaration->push([
                'tax_name' => '(-) مردودات المبيعات',
                'base_amount' => -$salesReturnsData['base'],
                'tax_amount' => -$salesReturnsData['tax'],
                'total_amount' => -($salesReturnsData['base'] + $salesReturnsData['tax']),
                'invoices_count' => $salesReturnsData['count'],
                'type' => 'output', // ضريبة مخرجات (سالبة)
            ]);
        }

        // مصروفات خاضعة للضريبة
        if ($expensesData['tax'] > 0 || $expensesData['base'] > 0) {
            $otherTaxDeclaration->push([
                'tax_name' => 'مصروفات خاضعة للضريبة',
                'base_amount' => $expensesData['base'],
                'tax_amount' => $expensesData['tax'],
                'total_amount' => $expensesData['base'] + $expensesData['tax'],
                'invoices_count' => $expensesData['count'],
                'type' => 'input', // ضريبة مدخلات
            ]);
        }

        // مردودات المشتريات
        if ($purchaseReturnsData['tax'] > 0 || $purchaseReturnsData['base'] > 0) {
            $otherTaxDeclaration->push([
                'tax_name' => '(-) مردودات المشتريات',
                'base_amount' => -$purchaseReturnsData['base'],
                'tax_amount' => -$purchaseReturnsData['tax'],
                'total_amount' => -($purchaseReturnsData['base'] + $purchaseReturnsData['tax']),
                'invoices_count' => $purchaseReturnsData['count'],
                'type' => 'input', // ضريبة مدخلات (سالبة)
            ]);
        }

        // إشعارات المشتريات الدائنة
        if ($purchasesCreditNoteData['tax'] > 0 || $purchasesCreditNoteData['base'] > 0) {
            $otherTaxDeclaration->push([
                'tax_name' => '(-) إشعارات مشتريات دائنة',
                'base_amount' => -$purchasesCreditNoteData['base'],
                'tax_amount' => -$purchasesCreditNoteData['tax'],
                'total_amount' => -($purchasesCreditNoteData['base'] + $purchasesCreditNoteData['tax']),
                'invoices_count' => $purchasesCreditNoteData['count'],
                'type' => 'input', // ضريبة مدخلات (سالبة)
            ]);
        }

        // ========================================
        // بيانات الرسم البياني
        // ========================================

        // تجميع الضرائب الأخرى للمبيعات (إشعارات مدينة - مردودات)
        $salesOtherTax = $salesDebitNoteData['tax'] - $salesReturnsData['tax'];

        // تجميع الضرائب الأخرى للمشتريات (المصروفات - مردودات - إشعارات دائنة)
        $purchasesOtherTax = $expensesData['tax'] - $purchaseReturnsData['tax'] - $purchasesCreditNoteData['tax'];

        $salesTaxData = [
            'labels' => ['المبيعات', 'أخرى'],
            'amounts' => [$salesData['tax'], abs($salesOtherTax)],
            'total' => $totalOutputTax,
        ];

        $purchasesTaxData = [
            'labels' => ['المشتريات', 'أخرى'],
            'amounts' => [$purchasesData['tax'], abs($purchasesOtherTax)],
            'total' => $totalInputTax,
        ];

        // ========================================
        // إعداد البيانات النهائية
        // ========================================

        $taxData = [
            'from_date' => $fromDate ? Carbon::parse($fromDate)->format('d/m/Y') : '-',
            'to_date' => $toDate ? Carbon::parse($toDate)->format('d/m/Y') : '-',
            'totals' => [
                'total_sales' => $totalOutputBase + $totalOutputTax,
                'total_purchases' => $totalInputBase + $totalInputTax,
                'total_tax_output' => $totalOutputTax,
                'total_tax_input' => $totalInputTax,
                'net_tax_due' => $netTaxDue,
                'total_invoices' => $totalEntries,
            ],
            'sales_tax_declaration' => $salesTaxDeclaration->values(),
            'purchases_tax_declaration' => $purchasesTaxDeclaration->values(),
            'other_tax_declaration' => $otherTaxDeclaration->values(),
            'sales_tax_data' => $salesTaxData,
            'purchases_tax_data' => $purchasesTaxData,
        ];

        // تجهيز قيم الفلاتر النشطة لعرضها مرة أخرى في الـ view
        $appliedFilters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch' => $branchFilter,
            'currency' => $currencyFilter,
            'reference_number' => $referenceFilter,
            'search' => $searchFilter,
            'date_type' => $dateType,
            'section' => $sectionFilter,
        ];

        return view('account::report.accountGeneral.tax_declaration',
            compact('branches', 'taxSettings', 'taxData', 'appliedFilters'));
    }

    /**
     * استخراج بيانات الإقرار الضريبي من القيود
     */
    private function extractDeclarationData($entries, $taxAccountId, $taxDirection, $baseAccountIds, $baseDirection)
    {
        $totalTax = 0;
        $totalBase = 0;
        $count = 0;

        foreach ($entries as $entry) {
            $entryTax = 0;
            $entryBase = 0;

            foreach ($entry->details as $detail) {
                // حساب الضريبة
                if ($detail->account_id == $taxAccountId) {
                    $entryTax += ($taxDirection == 'debit') ? $detail->debit : $detail->credit;
                }
                // حساب المبلغ الأساسي
                if (in_array($detail->account_id, $baseAccountIds)) {
                    $entryBase += ($baseDirection == 'debit') ? $detail->debit : $detail->credit;
                }
            }

            if ($entryTax > 0) {
                $totalTax += $entryTax;
                $totalBase += $entryBase;
                $count++;
            }
        }

        return [
            'tax' => $totalTax,
            'base' => $totalBase,
            'count' => $count,
        ];
    }

    public function incomeStatement(Request $request)
    {
        try {
            // جلب البيانات المطلوبة للفلاتر
            $branches = Branch::all();
            $costCenters = CostCenter::all();

            /*
                **قائمةُ الدخل تُقرأ على الفترة بعلاقتها — كبقية التقارير.**

                كان لها حسابُها الخاصّ للتاريخ لا يمرّ بـ`reportPeriod`، وفيه
                قائمةُ «السنة المالية» تُقرأ **سنواتٍ ميلادية** (2016…2026)
                مكتوبةً في القالب — لا صلةَ لها بالسنوات المالية المعرَّفة ولا
                بفتراتها. فمنشأةٌ سنتُها تبدأ في يوليو تُقرأ قائمةُ دخلها
                مقطوعةً عند يناير، والفترةُ المختارة لا أثر لها.

                وصارت تمرّ بالقاعدة الواحدة: فترةٌ مختارةٌ بمعرّفها ← تاريخان
                مكتوبان ← مدًى سريع ← السنةُ المالية الجارية.
            */
            $scope = $this->reportPeriod(
                $request,
                $request->input('date_type'),
                $request->input('from_date'),
                $request->input('to_date')
            );

            $reportPeriod = $scope['period'];
            [$fromDate, $toDate] = [$scope['from'], $scope['to']];

            // الإيرادات وتكلفة المبيعات وبقية المصروفات — مرتّبةً بالكود
            [$revenueAccounts, $costOfSalesAccounts, $expenseAccounts] = $this->incomeStatementLines(
                fn ($query) => $this->applyReportPeriod($query, $scope),
                $request->filled('branch') ? $request->branch : null,
                $request->filled('cost_center') ? $request->cost_center : null
            );

            // فلترة الحسابات الصفرية إذا كان مطلوباً
            if ($request->filled('account') && $request->account == '2') {
                $revenueAccounts = $revenueAccounts->filter(fn ($item) => abs($item['balance']) > 0.01)->values();
                $costOfSalesAccounts = $costOfSalesAccounts->filter(fn ($item) => abs($item['balance']) > 0.01)->values();
                $expenseAccounts = $expenseAccounts->filter(fn ($item) => abs($item['balance']) > 0.01)->values();
            }

            // حساب الإجماليات
            $totalRevenues = $revenueAccounts->sum('balance');
            $totalCostOfSales = $costOfSalesAccounts->sum('balance');
            $grossProfit = $totalRevenues - $totalCostOfSales;
            $totalExpenses = $expenseAccounts->sum('balance');
            $netIncome = $grossProfit - $totalExpenses;
            $profitMargin = $totalRevenues > 0 ? (($netIncome / $totalRevenues) * 100) : 0;
            $grossProfitMargin = $totalRevenues > 0 ? (($grossProfit / $totalRevenues) * 100) : 0;

            // تحضير بيانات الرسم البياني
            $chartData = [
                'labels' => ['الإيرادات', 'تكلفة المبيعات', 'مجمل الربح', 'المصروفات', 'صافي الدخل'],
                'amounts' => [
                    abs($totalRevenues),
                    abs($totalCostOfSales),
                    abs($grossProfit),
                    abs($totalExpenses),
                    abs($netIncome),
                ],
                'colors' => [
                    '#28a745', // أخضر للإيرادات
                    '#ffc107', // أصفر لتكلفة المبيعات
                    '#17a2b8', // أزرق فاتح لمجمل الربح
                    '#dc3545', // أحمر للمصروفات
                    $netIncome >= 0 ? '#6f42c1' : '#dc3545', // بنفسجي للربح، أحمر للخسارة
                ],
            ];

            $totals = [
                'total_revenues' => round($totalRevenues, 2),
                'total_cost_of_sales' => round($totalCostOfSales, 2),
                'gross_profit' => round($grossProfit, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_income' => round($netIncome, 2),
                'profit_margin' => round($profitMargin, 2),
                'gross_profit_margin' => round($grossProfitMargin, 2),
            ];

            $revenues = $revenueAccounts->toArray();
            $costOfSales = $costOfSalesAccounts->toArray();
            $expenses = $expenseAccounts->toArray();

            return view('account::report.accountGeneral.income_statement',
                compact('branches', 'costCenters', 'revenues', 'costOfSales', 'expenses', 'totals', 'chartData', 'fromDate', 'toDate', 'reportPeriod'));

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'incomeStatement');

            Log::error('Income Statement Error: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            $branches = Branch::all();
            $costCenters = CostCenter::all();
            $revenues = [];
            $costOfSales = [];
            $expenses = [];
            $totals = [
                'total_revenues' => 0,
                'total_cost_of_sales' => 0,
                'gross_profit' => 0,
                'total_expenses' => 0,
                'net_income' => 0,
                'profit_margin' => 0,
                'gross_profit_margin' => 0,
            ];
            $chartData = [
                'labels' => ['الإيرادات', 'تكلفة المبيعات', 'المصروفات', 'صافي الدخل'],
                'amounts' => [0, 0, 0, 0],
                'colors' => ['#28a745', '#ffc107', '#dc3545', '#6f42c1'],
            ];
            $fromDate = $this->fiscalYearStart()->format('Y-m-d');
            $toDate = now()->format('Y-m-d');

            return view('account::report.accountGeneral.income_statement',
                compact('branches', 'costCenters', 'revenues', 'costOfSales', 'expenses', 'totals', 'chartData', 'fromDate', 'toDate'))
                ->with('error', 'حدث خطأ في جلب البيانات: '.$e->getMessage());
        }
    }

    /**
     * دالة مساعدة لحساب مستوى الحساب في الشجرة
     */
    private function getAccountLevel($account)
    {
        $level = 1;
        $seen = [(int) $account->id => true];
        $parent = $account->parent;

        // يقف عند أوّل أبٍ تكرّر — حسابٌ أبٌ لنفسه كان يُبقي الحلقة تدور
        while ($parent && ! isset($seen[(int) $parent->id])) {
            $seen[(int) $parent->id] = true;
            $level++;
            $parent = $parent->parent;
        }

        return $level;
    }

    /**
     * جلب معرف الجذر للحساب عبر التسلسل الأبوي
     */
    private function getRootAccountId($account)
    {
        $node = $account;
        $safety = 0;
        while ($node && $node->parent && $safety < 20) {
            $node = $node->parent;
            $safety++;
        }

        return $node ? (int) $node->id : (int) $account->id;
    }

    /**
     * التحقق هل الحساب ضمن تسلسل فرعي لحساب برقم معرف محدد
     */
    private function isDescendantOf($account, int $ancestorId): bool
    {
        if (! $account) {
            return false;
        }
        if ((int) $account->id === $ancestorId) {
            return true;
        }
        $node = $account;
        $safety = 0;
        while ($node && $node->parent && $safety < 50) {
            if ((int) $node->parent->id === $ancestorId) {
                return true;
            }
            $node = $node->parent;
            $safety++;
        }

        return false;
    }

    /**
     * تحديث مجموعة الحسابات مع الحفاظ على التجميع
     */
    private function updateHierCollection(&$collection, $account, $balance)
    {
        $existing = $collection->firstWhere('id', $account->id);
        if ($existing) {
            $collection = $collection->map(function ($item) use ($account, $balance) {
                if ($item['id'] == $account->id) {
                    $item['balance'] += $balance;
                }

                return $item;
            });
        } else {
            $collection->push([
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'balance' => $balance,
                'level' => $this->getAccountLevel($account),
            ]);
        }
    }

    /**
     * عرض تقرير الميزانية العمومية
     * Generated by Copilot - تم إصلاح مشكلة جمع الأب والابن معاً
     * تم تحديثه لعرض الحسابات بشكل هرمي مع تمييز الآباء
     */
    public function balanceSheet(Request $request)
    {
        try {
            // جلب البيانات المطلوبة للفلاتر
            $branches = Branch::all();
            $costCenters = CostCenter::all();

            // تحديد الفترة الزمنية
            $fromDate = $request->from_date ?? $this->fiscalYearStart()->format('Y-m-d');
            $toDate = $request->to_date ?? now()->format('Y-m-d');

            // معالجة السنة المالية
            if ($request->has('financial_year') && ! empty($request->financial_year)) {
                $years = $request->input('financial_year');

                if (in_array('current', $years)) {
                    $fromDate = $this->fiscalYearStart()->format('Y-m-d');
                    $toDate = now()->format('Y-m-d');
                } elseif (! in_array('all', $years)) {
                    $minYear = min($years);
                    $maxYear = max($years);
                    $fromDate = "$minYear-01-01";
                    $toDate = "$maxYear-12-31";
                }
            }

            // ========================================
            // حساب الأرصدة من القيود المحاسبية للفترة المحددة
            // ========================================

            // حساب الأرصدة من القيود المحاسبية المعتمدة للفترة لجميع الحسابات
            $accountBalances = JournalEntryDetail::select(
                'account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
                ->whereHas('journalEntry', function ($query) use ($fromDate, $toDate, $request) {
                    $query->where('status', 1); // القيود المعتمدة فقط

                    if (! empty($fromDate) && ! empty($toDate)) {
                        $query->whereBetween('date', [$fromDate, $toDate]);
                    }

                    // فلترة الفرع
                    if ($request->filled('branch')) {
                        $query->where('branch_id', $request->branch);
                    }

                    // فلترة مركز التكلفة
                    if ($request->filled('cost_center')) {
                        $query->whereHas('details', function ($q) use ($request) {
                            $q->where('cost_center_id', $request->cost_center);
                        });
                    }
                })
                ->groupBy('account_id')
                ->get()
                ->keyBy('account_id');

            // جلب جميع الحسابات مرتبة حسب الكود
            $allAccounts = Account::with(['parent', 'children', 'branch'])
                ->orderBy('code')
                ->get()
                ->keyBy('id');

            // بناء الهيكل الهرمي للحسابات
            $assets = $this->buildHierarchicalAccounts($allAccounts, $accountBalances, 'assets');
            $liabilities = $this->buildHierarchicalAccounts($allAccounts, $accountBalances, 'liabilities');
            $equity = $this->buildHierarchicalAccounts($allAccounts, $accountBalances, 'equity');

            // حساب الإجماليات (من الحسابات الفرعية فقط - غير الآباء)
            $totalAssets = $assets->where('is_parent', false)->sum('balance');
            $totalLiabilities = $liabilities->where('is_parent', false)->sum('balance');
            $totalEquity = $equity->where('is_parent', false)->sum('balance');

            // تحضير بيانات الرسم البياني
            $chartData = [
                'labels' => ['الأصول', 'الخصوم', 'حقوق الملكية'],
                'amounts' => [abs($totalAssets), abs($totalLiabilities), abs($totalEquity)],
                'colors' => ['#198754', '#dc3545', '#6f42c1'],
            ];

            $totals = [
                'total_assets' => round(abs($totalAssets), 2),
                'total_liabilities' => round(abs($totalLiabilities), 2),
                'total_equity' => round(abs($totalEquity), 2),
                'total_liabilities_equity' => round(abs($totalLiabilities + $totalEquity), 2),
            ];

            return view('account::report.accountGeneral.balance_sheet',
                compact('branches', 'costCenters', 'assets', 'liabilities', 'equity', 'totals', 'chartData', 'fromDate', 'toDate'));

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'balanceSheet');

            Log::error('Balance Sheet Error: '.$e->getMessage().' - '.$e->getTraceAsString());

            $branches = Branch::all();
            $costCenters = CostCenter::all();
            $assets = collect();
            $liabilities = collect();
            $equity = collect();
            $totals = [
                'total_assets' => 0,
                'total_liabilities' => 0,
                'total_equity' => 0,
                'total_liabilities_equity' => 0,
            ];
            $chartData = [
                'labels' => ['الأصول', 'الخصوم', 'حقوق الملكية'],
                'amounts' => [0, 0, 0],
                'colors' => ['#198754', '#dc3545', '#6f42c1'],
            ];
            $fromDate = $this->fiscalYearStart()->format('Y-m-d');
            $toDate = now()->format('Y-m-d');

            return view('account::report.accountGeneral.balance_sheet',
                compact('branches', 'costCenters', 'assets', 'liabilities', 'equity', 'totals', 'chartData', 'fromDate', 'toDate'))
                ->with('error', 'حدث خطأ في جلب البيانات: '.$e->getMessage());
        }
    }

    /**
     * تحديد نوع الحساب في الميزانية العمومية بناءً على شجرة الحسابات
     * Generated by Copilot
     *
     * @param  Account  $account
     * @return string|null 'assets', 'liabilities', 'equity', or null
     */
    private function getBalanceSheetAccountType($account)
    {
        // التحقق من نوع الحساب المحدد مباشرة
        if (! empty($account->account_type)) {
            $type = strtolower($account->account_type);
            if (in_array($type, ['assets', 'asset', 'أصول', 'الأصول'])) {
                return 'assets';
            }
            if (in_array($type, ['liabilities', 'liability', 'خصوم', 'الخصوم'])) {
                return 'liabilities';
            }
            if (in_array($type, ['equity', 'حقوق الملكية', 'حقوق'])) {
                return 'equity';
            }
        }

        // التحقق من الكود - عادة:
        // 1xxx = أصول
        // 2xxx = خصوم
        // 3xxx = حقوق ملكية
        // 4xxx = إيرادات (لا تدخل في الميزانية)
        // 5xxx = مصروفات (لا تدخل في الميزانية)
        if (! empty($account->code)) {
            $firstDigit = substr($account->code, 0, 1);
            switch ($firstDigit) {
                case '1':
                    return 'assets';
                case '2':
                    return 'liabilities';
                case '3':
                    return 'equity';
                    // 4 و 5 هي إيرادات ومصروفات - لا تدخل في الميزانية العمومية
                default:
                    break;
            }
        }

        // البحث في الآباء للتحديد
        $parent = $account->parent;
        $safety = 0;
        while ($parent && $safety < 10) {
            // التحقق من نوع الأب
            if (! empty($parent->account_type)) {
                $type = strtolower($parent->account_type);
                if (in_array($type, ['assets', 'asset', 'أصول', 'الأصول'])) {
                    return 'assets';
                }
                if (in_array($type, ['liabilities', 'liability', 'خصوم', 'الخصوم'])) {
                    return 'liabilities';
                }
                if (in_array($type, ['equity', 'حقوق الملكية', 'حقوق'])) {
                    return 'equity';
                }
            }

            // التحقق من كود الأب
            if (! empty($parent->code)) {
                $firstDigit = substr($parent->code, 0, 1);
                switch ($firstDigit) {
                    case '1':
                        return 'assets';
                    case '2':
                        return 'liabilities';
                    case '3':
                        return 'equity';
                }
            }

            // التحقق من اسم الأب
            $parentName = $parent->name;
            if (strpos($parentName, 'أصول') !== false || strpos($parentName, 'الأصول') !== false) {
                return 'assets';
            }
            if (strpos($parentName, 'خصوم') !== false || strpos($parentName, 'الخصوم') !== false) {
                return 'liabilities';
            }
            if (strpos($parentName, 'حقوق') !== false || strpos($parentName, 'الملكية') !== false) {
                return 'equity';
            }

            $parent = $parent->parent;
            $safety++;
        }

        // التحقق من اسم الحساب نفسه
        $name = $account->name;
        if (strpos($name, 'أصول') !== false || strpos($name, 'الأصول') !== false) {
            return 'assets';
        }
        if (strpos($name, 'خصوم') !== false || strpos($name, 'الخصوم') !== false) {
            return 'liabilities';
        }
        if (strpos($name, 'حقوق') !== false || strpos($name, 'الملكية') !== false || strpos($name, 'رأس المال') !== false) {
            return 'equity';
        }

        // لم يتم تحديد النوع - لا يدخل في الميزانية
        return null;
    }

    /**
     * بناء قائمة الحسابات بشكل هرمي مع تمييز الآباء
     * Generated by Copilot
     *
     * @param  \Illuminate\Support\Collection  $allAccounts  جميع الحسابات
     * @param  \Illuminate\Support\Collection  $accountBalances  أرصدة الحسابات
     * @param  string  $type  نوع الحسابات (assets, liabilities, equity)
     * @return \Illuminate\Support\Collection
     */
    private function buildHierarchicalAccounts($allAccounts, $accountBalances, $type)
    {
        $result = collect();
        $processedIds = collect();

        // فلترة الحسابات حسب النوع وترتيبها حسب الكود
        $typeAccounts = $allAccounts->filter(function ($account) use ($type) {
            return $this->getBalanceSheetAccountType($account) === $type;
        })->sortBy('code');

        // تجميع الحسابات حسب الأب
        $accountsByParent = [];
        foreach ($typeAccounts as $account) {
            $parentId = $account->parent_id ?? 'root';
            if (! isset($accountsByParent[$parentId])) {
                $accountsByParent[$parentId] = collect();
            }
            $accountsByParent[$parentId]->push($account);
        }

        // بناء الهيكل الهرمي بدءاً من الحسابات الجذرية
        $this->addAccountsRecursively($result, $typeAccounts, $accountsByParent, $accountBalances, $type, null, 0, $processedIds);

        return $result;
    }

    /**
     * إضافة الحسابات بشكل تكراري مع الحفاظ على الهيكل الهرمي
     * Generated by Copilot
     */
    private function addAccountsRecursively(&$result, $typeAccounts, $accountsByParent, $accountBalances, $type, $parentId, $level, &$processedIds)
    {
        $key = $parentId ?? 'root';

        if (! isset($accountsByParent[$key])) {
            return;
        }

        $accounts = $accountsByParent[$key]->sortBy('code');

        foreach ($accounts as $account) {
            // تجنب التكرار
            if ($processedIds->contains($account->id)) {
                continue;
            }
            $processedIds->push($account->id);

            // التحقق مما إذا كان هذا الحساب له أبناء في نفس النوع
            $hasChildren = isset($accountsByParent[$account->id]) && $accountsByParent[$account->id]->count() > 0;

            // حساب الرصيد
            $balanceData = $accountBalances->get($account->id);
            $totalDebit = $balanceData ? $balanceData->total_debit : 0;
            $totalCredit = $balanceData ? $balanceData->total_credit : 0;

            // حساب الرصيد بناءً على نوع الحساب
            if ($type === 'assets') {
                $balance = $totalDebit - $totalCredit;
            } else {
                // الخصوم وحقوق الملكية
                $balance = $totalCredit - $totalDebit;
            }

            // إذا كان له أبناء، احسب مجموع أرصدة الأبناء
            $childrenBalance = 0;
            if ($hasChildren) {
                $childrenBalance = $this->calculateChildrenBalance($account->id, $accountsByParent, $accountBalances, $type);
            }

            // إضافة الحساب للنتيجة
            $result->push([
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'balance' => $hasChildren ? $childrenBalance : $balance,
                'parent_name' => $account->parent ? $account->parent->name : null,
                'is_parent' => $hasChildren,
                'level' => $level,
                'has_children' => $hasChildren,
            ]);

            // إضافة الأبناء بشكل تكراري
            if ($hasChildren) {
                $this->addAccountsRecursively($result, $typeAccounts, $accountsByParent, $accountBalances, $type, $account->id, $level + 1, $processedIds);
            }
        }
    }

    /**
     * حساب مجموع أرصدة الأبناء لحساب معين
     * Generated by Copilot
     */
    private function calculateChildrenBalance($parentId, $accountsByParent, $accountBalances, $type)
    {
        $total = 0;

        if (! isset($accountsByParent[$parentId])) {
            return $total;
        }

        foreach ($accountsByParent[$parentId] as $child) {
            // التحقق مما إذا كان هذا الابن له أبناء
            $hasGrandChildren = isset($accountsByParent[$child->id]) && $accountsByParent[$child->id]->count() > 0;

            if ($hasGrandChildren) {
                // إذا كان له أبناء، احسب مجموع أرصدة أبنائه بشكل تكراري
                $total += $this->calculateChildrenBalance($child->id, $accountsByParent, $accountBalances, $type);
            } else {
                // إذا كان leaf account، احسب رصيده من القيود
                $balanceData = $accountBalances->get($child->id);
                $totalDebit = $balanceData ? $balanceData->total_debit : 0;
                $totalCredit = $balanceData ? $balanceData->total_credit : 0;

                if ($type === 'assets') {
                    $total += $totalDebit - $totalCredit;
                } else {
                    $total += $totalCredit - $totalDebit;
                }
            }
        }

        return $total;
    }

    /**
     * جلب بيانات قائمة الدخل عبر AJAX
     */
    public function incomeStatementAjax(Request $request)
    {
        try {
            // تحديد الفترة الزمنية - الأولوية للتواريخ المدخلة يدوياً
            $fromDate = null;
            $toDate = null;

            // 1. أولاً: التحقق من التواريخ المدخلة مباشرة (أعلى أولوية)
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDate = $request->from_date;
                $toDate = $request->to_date;
            }
            // 2. ثانياً: معالجة السنة المالية (فقط إذا لم يتم إدخال تواريخ يدوياً)
            elseif ($request->has('financial_year') && ! empty($request->financial_year)) {
                $years = $request->input('financial_year');

                if (in_array('current', $years)) {
                    $fromDate = $this->fiscalYearStart()->format('Y-m-d');
                    $toDate = now()->format('Y-m-d');
                } elseif (! in_array('all', $years)) {
                    $minYear = min($years);
                    $maxYear = max($years);
                    $fromDate = "$minYear-01-01";
                    $toDate = "$maxYear-12-31";
                }
            }

            // 3. أخيراً: إذا لم يتم تحديد أي تواريخ، استخدم القيم الافتراضية (السنة الحالية)
            if (empty($fromDate) || empty($toDate)) {
                $fromDate = $fromDate ?: $this->fiscalYearStart()->format('Y-m-d');
                $toDate = $toDate ?: now()->format('Y-m-d');
            }

            // تاريخٌ لا يُقرأ يعود للافتراضي، والمدى المقلوب يُعدَل بدل أن يُرجع تقريراً فارغاً
            $validDate = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
            $fromDate = $validDate($fromDate) ? $fromDate : $this->fiscalYearStart()->format('Y-m-d');
            $toDate = $validDate($toDate) ? $toDate : now()->format('Y-m-d');
            if ($fromDate > $toDate) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }

            // الإيرادات وتكلفة المبيعات وبقية المصروفات — مرتّبةً بالكود
            [$revenueAccounts, $costOfSalesAccounts, $expenseAccounts] = $this->incomeStatementLines(
                fn ($query) => $query->whereDate('journal_entries.date', '>=', $fromDate)
                    ->whereDate('journal_entries.date', '<=', $toDate),
                $request->filled('branch') ? $request->branch : null,
                $request->filled('cost_center') ? $request->cost_center : null
            );

            // فلترة الحسابات الصفرية
            if ($request->filled('account') && $request->account == '2') {
                $revenueAccounts = $revenueAccounts->filter(fn ($item) => abs($item['balance']) > 0.01)->values();
                $costOfSalesAccounts = $costOfSalesAccounts->filter(fn ($item) => abs($item['balance']) > 0.01)->values();
                $expenseAccounts = $expenseAccounts->filter(fn ($item) => abs($item['balance']) > 0.01)->values();
            }

            // حساب الإجماليات
            $totalRevenues = $revenueAccounts->sum('balance');
            $totalCostOfSales = $costOfSalesAccounts->sum('balance');
            $grossProfit = $totalRevenues - $totalCostOfSales;
            $totalExpenses = $expenseAccounts->sum('balance');
            $netIncome = $grossProfit - $totalExpenses;
            $profitMargin = $totalRevenues > 0 ? (($netIncome / $totalRevenues) * 100) : 0;
            $grossProfitMargin = $totalRevenues > 0 ? (($grossProfit / $totalRevenues) * 100) : 0;

            $chartData = [
                'labels' => ['الإيرادات', 'تكلفة المبيعات', 'مجمل الربح', 'المصروفات', 'صافي الدخل'],
                'amounts' => [
                    abs($totalRevenues),
                    abs($totalCostOfSales),
                    abs($grossProfit),
                    abs($totalExpenses),
                    abs($netIncome),
                ],
                'colors' => ['#28a745', '#ffc107', '#17a2b8', '#dc3545', $netIncome >= 0 ? '#6f42c1' : '#dc3545'],
            ];

            return response()->json([
                'success' => true,
                'revenues' => $revenueAccounts->toArray(),
                'cost_of_sales' => $costOfSalesAccounts->toArray(),
                'expenses' => $expenseAccounts->toArray(),
                'totals' => [
                    'total_revenues' => round($totalRevenues, 2),
                    'total_cost_of_sales' => round($totalCostOfSales, 2),
                    'gross_profit' => round($grossProfit, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'net_income' => round($netIncome, 2),
                    'profit_margin' => round($profitMargin, 2),
                    'gross_profit_margin' => round($grossProfitMargin, 2),
                ],
                'chart_data' => $chartData,
                'period' => [
                    'from' => Carbon::parse($fromDate)->format('d/m/Y'),
                    'to' => Carbon::parse($toDate)->format('d/m/Y'),
                ],
                'filters_applied' => $request->all(),
            ]);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'incomeStatementAjax');

            Log::error('Income Statement AJAX Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات: '.$e->getMessage(),
                'revenues' => [],
                'cost_of_sales' => [],
                'expenses' => [],
                'totals' => [
                    'total_revenues' => 0,
                    'total_cost_of_sales' => 0,
                    'gross_profit' => 0,
                    'total_expenses' => 0,
                    'net_income' => 0,
                    'profit_margin' => 0,
                    'gross_profit_margin' => 0,
                ],
            ], 500);
        }
    }

    /**
     * حساباتُ قائمة الدخل الثلاث: الإيرادات، وتكلفة المبيعات، وبقية المصروفات.
     *
     * **ما كان**
     *
     * كلُّ قيود الفترة تُحمَّل بسطورها وحساباتها نماذجَ كاملة، ثم يصعد كلُّ
     * سطرٍ شجرةَ آبائه استعلاماً استعلاماً ليعرف جذره، ويُبحث عن حسابه في
     * المجموعة بمسحها كلِّها. سنةٌ واحدة من القيود: ~8,600 استعلام و~290
     * ميغابايت وعشر ثوانٍ — والاستضافةُ تقطع الطلب فيظهر 503.
     *
     * **ما صار**
     *
     * استعلامُ تجميعٍ واحد يُعيد مدينَ كلّ حسابٍ ودائنَه في الفترة بالفلاتر
     * نفسها، والشجرةُ تُقرأ مرّةً وتُصعَد في الذاكرة. كلُّ حسابٍ بما رُحِّل
     * عليه هو (طرفياً كان أو حسابَ تجميع)، والتصنيفُ بالجذر وبشجرة «تكلفة
     * المبيعات»، وقيدُ الإقفال السنوي خارجها.
     *
     * @param  callable(Builder): void  $constrainPeriod  شرطُ الفترة على `journal_entries`
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection}
     */
    private function incomeStatementLines(callable $constrainPeriod, $branch, $costCenter): array
    {
        $sums = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->where('journal_entries.status', 1) // قيود معتمدة فقط
            // قيدُ الإقفال السنوي يُصفّر الإيرادات والمصروفات — قائمةُ الدخل تُقرأ قبله
            ->where(fn ($q) => $q->whereNull('journal_entries.reference_type')->orWhere('journal_entries.reference_type', '!=', ReferenceType::YEAR_CLOSING->value))
            ->whereNotNull('journal_entry_details.account_id')
            ->groupBy('journal_entry_details.account_id')
            ->selectRaw('journal_entry_details.account_id, COALESCE(SUM(journal_entry_details.debit), 0) as debit, COALESCE(SUM(journal_entry_details.credit), 0) as credit');

        $constrainPeriod($sums);

        if ($branch) {
            $sums->where('journal_entries.branch_id', $branch);
        }

        // مركزُ التكلفة يختار **القيد** الذي يحمل سطراً فيه — ثم تُجمع سطورُه كلُّها، كما كان
        if ($costCenter) {
            $sums->whereExists(function ($q) use ($costCenter) {
                $q->select(DB::raw(1))
                    ->from('journal_entry_details as cost_center_line')
                    ->whereColumn('cost_center_line.journal_entry_id', 'journal_entries.id')
                    ->where('cost_center_line.cost_center_id', $costCenter);
            });
        }

        $accounts = Account::select('id', 'code', 'name', 'parent_id')->get()->keyBy('id');

        // سلسلةُ الآباء من الحساب إلى جذره — تقف عند المُكرَّر فلا تدور في حلقة
        $chain = function (int $accountId) use ($accounts): array {
            $ids = [$accountId];
            $seen = [$accountId => true];
            $node = $accounts->get($accountId);
            while ($node && $node->parent_id && ! isset($seen[(int) $node->parent_id]) && $accounts->has($node->parent_id)) {
                $seen[(int) $node->parent_id] = true;
                $ids[] = (int) $node->parent_id;
                $node = $accounts->get($node->parent_id);
            }

            return $ids;
        };

        $revenueRoot = AccountingSettings::id('revenue', SystemAccountIds::REVENUE);
        $expensesRoot = AccountingSettings::id('expenses', SystemAccountIds::EXPENSES);
        $costOfSalesRoot = AccountingSettings::id('cost_of_sales_root', SystemAccountIds::COST_OF_SALES_ROOT);

        $revenues = collect();
        $costOfSales = collect();
        $expenses = collect();

        foreach ($sums->get() as $row) {
            $account = $accounts->get($row->account_id);

            /*
                حسابُ التجميع يُعدّ بما رُحِّل عليه هو — لا يُسقَط.

                كانت الحساباتُ ذاتُ الأبناء تُستبعد كلُّها، على أنّ رصيدها
                مجموعُ أبنائها. لكنّ الجمعَ هنا سطراً سطراً لا رصيداً: كلُّ
                حسابٍ بما قُيِّد عليه وحده، فلا يُعدّ شيءٌ مرّتين. والاستبعادُ
                أسقط ما يُرحَّل على حساب التجميع نفسه — فاتورةُ البيع تُرحِّل
                تكلفتَها على «تكلفة المبيعات» (511) وله أبناء، فغابت ~322 ألفاً
                من تكلفة المبيعات وتضخّم الربح، واختلفت القائمةُ عن قيد الإقفال.
            */
            if (! $account) {
                continue;
            }

            $ancestors = $chain((int) $account->id);
            $rootId = end($ancestors);
            $line = fn ($balance) => [
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'balance' => (float) $balance,
                'level' => count($ancestors),
            ];

            if ($rootId === $revenueRoot) {
                $revenues->push($line($row->credit - $row->debit));
            } elseif (in_array($costOfSalesRoot, $ancestors, true)) {
                $costOfSales->push($line($row->debit - $row->credit));
            } elseif ($rootId === $expensesRoot) {
                $expenses->push($line($row->debit - $row->credit));
            }
        }

        return [
            $revenues->sortBy('code')->values(),
            $costOfSales->sortBy('code')->values(),
            $expenses->sortBy('code')->values(),
        ];
    }

    /**
     * تصدير قائمة الدخل إلى Excel
     */
    public function exportIncomeStatementExcel(Request $request)
    {
        try {
            // استخدام نفس منطق الفلترة من incomeStatementAjax
            $data = $this->incomeStatementAjax($request);
            $responseData = json_decode($data->getContent(), true);

            if (! $responseData['success']) {
                return response()->json(['error' => 'فشل في جلب البيانات'], 500);
            }

            // إنشاء ملف Excel
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // إعداد العنوان
            $sheet->setCellValue('A1', 'قائمة الدخل');
            $sheet->mergeCells('A1:C1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

            // تاريخ التقرير
            $sheet->setCellValue('A2', 'تاريخ التقرير: '.date('Y-m-d H:i:s'));
            $sheet->mergeCells('A2:C2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

            $row = 4;

            // قسم الإيرادات
            $sheet->setCellValue('A'.$row, 'الإيرادات');
            $sheet->mergeCells('A'.$row.':C'.$row);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->getFill()->setFillType('solid')->getStartColor()->setRGB('198754');
            $row++;

            $sheet->setCellValue('A'.$row, 'اسم الحساب');
            $sheet->setCellValue('B'.$row, 'الكود');
            $sheet->setCellValue('C'.$row, 'المبلغ (ريال)');
            $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
            $row++;

            foreach ($responseData['revenues'] as $revenue) {
                $sheet->setCellValue('A'.$row, $revenue['name']);
                $sheet->setCellValue('B'.$row, $revenue['code']);
                $sheet->setCellValue('C'.$row, number_format($revenue['balance'], 2));
                $row++;
            }

            // إجمالي الإيرادات
            $sheet->setCellValue('A'.$row, 'إجمالي الإيرادات');
            $sheet->setCellValue('C'.$row, number_format($responseData['totals']['total_revenues'], 2));
            $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
            $row += 2;

            // قسم المصروفات
            $sheet->setCellValue('A'.$row, 'المصروفات');
            $sheet->mergeCells('A'.$row.':C'.$row);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->getFill()->setFillType('solid')->getStartColor()->setRGB('dc3545');
            $row++;

            $sheet->setCellValue('A'.$row, 'اسم الحساب');
            $sheet->setCellValue('B'.$row, 'الكود');
            $sheet->setCellValue('C'.$row, 'المبلغ (ريال)');
            $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
            $row++;

            foreach ($responseData['expenses'] as $expense) {
                $sheet->setCellValue('A'.$row, $expense['name']);
                $sheet->setCellValue('B'.$row, $expense['code']);
                $sheet->setCellValue('C'.$row, number_format($expense['balance'], 2));
                $row++;
            }

            // إجمالي المصروفات
            $sheet->setCellValue('A'.$row, 'إجمالي المصروفات');
            $sheet->setCellValue('C'.$row, number_format($responseData['totals']['total_expenses'], 2));
            $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
            $row += 2;

            // صافي الدخل
            $sheet->setCellValue('A'.$row, 'صافي الدخل');
            $sheet->setCellValue('C'.$row, number_format($responseData['totals']['net_income'], 2));
            $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row.':C'.$row)->getFill()->setFillType('solid')->getStartColor()->setRGB('6f42c1');

            // تنسيق الأعمدة
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);

            // إنشاء ملف Excel وتحميله
            $writer = new Xlsx($spreadsheet);
            $fileName = 'قائمة_الدخل_'.date('Y-m-d').'.xlsx';

            $temp_file = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($temp_file);

            return response()->download($temp_file, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.GeneralAccounts', 'exportIncomeStatementExcel');

            return response()->json(['error' => 'فشل في تصدير الملف: '.$e->getMessage()], 500);
        }
    }

    /**
     * تصدير قائمة الدخل إلى PDF
     */
    public function exportIncomeStatementPdf(Request $request)
    {
        try {
            // استخدام نفس منطق الفلترة من incomeStatementAjax
            $data = $this->incomeStatementAjax($request);
            $responseData = json_decode($data->getContent(), true);

            if (! $responseData['success']) {
                return response()->json(['error' => 'فشل في جلب البيانات'], 500);
            }

            // إنشاء PDF باستخدام DomPDF أو أي مكتبة PDF أخرى
            $pdf = app('dompdf.wrapper');

            // `cost_of_sales` كان يسقط من الحزمة، فتخرج الورقة بمجمل ربحٍ
            // بلا البند الذي يُطرح منه
            $html = view('account::report.accountGeneral.income_statement_pdf', [
                'revenues' => $responseData['revenues'] ?? [],
                'cost_of_sales' => $responseData['cost_of_sales'] ?? [],
                'expenses' => $responseData['expenses'] ?? [],
                'totals' => $responseData['totals'] ?? [],
                'date' => date('Y-m-d H:i'),
            ])->render();

            $pdf->loadHTML($html);
            $pdf->setPaper('A4', 'portrait');

            $fileName = 'قائمة_الدخل_'.date('Y-m-d').'.pdf';

            return $pdf->download($fileName);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.GeneralAccounts', 'exportIncomeStatementPdf');

            return response()->json(['error' => 'فشل في تصدير الملف: '.$e->getMessage()], 500);
        }
    }

    /**
     * طباعة قائمة الدخل
     */
    public function printIncomeStatement(Request $request)
    {
        try {
            // استخدام نفس منطق الفلترة من incomeStatementAjax
            $data = $this->incomeStatementAjax($request);
            $responseData = json_decode($data->getContent(), true);

            if (! $responseData['success']) {
                return response()->json(['error' => 'فشل في جلب البيانات'], 500);
            }

            return view('account::report.accountGeneral.income_statement_print', [
                'revenues' => $responseData['revenues'],
                'expenses' => $responseData['expenses'],
                'totals' => $responseData['totals'],
                'date' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.GeneralAccounts', 'printIncomeStatement');

            return back()->with('error', 'فشل في تحضير التقرير للطباعة: '.$e->getMessage());
        }

    }

    /**
     * تقرير الربح والخسارة - التحليل الشهري المفصل
     * يدعم نوعين: مستحق (Accrual) ونقدي (Cash)
     */
    /**
     * alias قديم - يستدعي detailedCashFlow للحفاظ على التوافق
     */
    public function profitAndLoss(Request $request)
    {
        return $this->detailedCashFlow($request);
    }

    /**
     * المصدر الوحيد لبيانات التدفقات النقدية.
     * يُرجع للحسابات التي حدثت لها حركة فعلية مع بنك/خزينة:
     *   - account info, opening_balance, cash_inflow, cash_outflow, period_debit, period_credit,
     *     net_change, closing_balance, entries_count
     * تُستخدم من تقريرَي:
     *   - التدفقات النقدية التفصيلية (عرض كامل)
     *   - التدفقات النقدية الإجمالية (تلخيص فقط)
     *
     * $filters keys:
     *   from_date, to_date, branch, account_search, account_id, account_type,
     *   counter_account_id (بنك/خزينة محدد), currency, reference_number, user_id,
     *   movement_type ('in'|'out'|'all'), min_amount, max_amount,
     *   sort_by ('activity'|'name'|'code'|'inflow'|'outflow'|'closing'),
     *   sort_dir ('asc'|'desc')
     */
    private function buildDetailedCashFlowData(array $filters)
    {
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $toDate = $filters['to_date'] ?? now()->format('Y-m-d');
        $branchFilter = $filters['branch'] ?? null;
        $accountSearch = trim((string) ($filters['account_search'] ?? ''));
        $accountIdFilter = $filters['account_id'] ?? null;
        $accountTypeFilter = $filters['account_type'] ?? null;
        $counterAccountId = $filters['counter_account_id'] ?? null;
        $currency = $filters['currency'] ?? null;
        $referenceNumber = trim((string) ($filters['reference_number'] ?? ''));
        $userId = $filters['user_id'] ?? null;
        $movementType = $filters['movement_type'] ?? 'all';
        $minAmount = $filters['min_amount'] ?? null;
        $maxAmount = $filters['max_amount'] ?? null;
        $sortBy = $filters['sort_by'] ?? 'activity';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // مجموعة كل حسابات البنوك/الخزائن (الفلتر الأساسي للتدفق النقدي)
        $cashAccountIds = $this->getCashFlowAccountIds();

        // إذا تم اختيار بنك/خزينة محددة، نقصر على أبنائها فقط
        $cashAccountIdsForQuery = $cashAccountIds;
        if (! empty($counterAccountId)) {
            $specificIds = $this->getAccountIdsWithChildren((int) $counterAccountId);
            // نتأكد أنها تنتمي فعلاً لشجرة البنوك/الخزائن
            $cashAccountIdsForQuery = array_values(array_intersect($specificIds, $cashAccountIds));
            if (empty($cashAccountIdsForQuery)) {
                $cashAccountIdsForQuery = $specificIds; // fallback
            }
        }

        $emptyResult = [
            'rows' => collect(),
            'totals' => ['total_inflows' => 0, 'total_outflows' => 0, 'net_cash_flow' => 0, 'accounts_count' => 0, 'entries_count' => 0],
            'cashAccountIds' => $cashAccountIds,
            'cashEntryIds' => collect(),
            'filters' => $filters,
        ];

        if (empty($cashAccountIdsForQuery)) {
            return $emptyResult;
        }

        // الخطوة 1: قيود اليومية في الفترة التي يحوي تفصيل منها بنك/خزينة + فلاتر القيد
        $cashEntryQuery = JournalEntry::query()
            ->where('status', 1)
            ->whereBetween('date', [$fromDate, $toDate])
            ->whereHas('details', function ($q) use ($cashAccountIdsForQuery) {
                $q->whereIn('account_id', $cashAccountIdsForQuery);
            });

        if (! empty($branchFilter)) {
            $cashEntryQuery->where('branch_id', $branchFilter);
        }
        if (! empty($currency)) {
            $cashEntryQuery->where('currency', $currency);
        }
        if ($referenceNumber !== '') {
            $cashEntryQuery->where('reference_number', 'LIKE', "%{$referenceNumber}%");
        }
        if (! empty($userId)) {
            $cashEntryQuery->where('created_by_employee', $userId);
        }

        $cashEntryIds = $cashEntryQuery->pluck('id');
        if ($cashEntryIds->isEmpty()) {
            return $emptyResult;
        }

        // الخطوة 2: تجميع الحسابات المقابلة (غير النقدية) من تلك القيود.
        // القيودُ باستعلامٍ فرعيّ لا بقائمة معرّفاتها: آلافُ القيم في `IN (…)`
        // كانت تأخذ 5–8 ثوانٍ وحدها فيسقط التقرير على الاستضافة (503).
        $counterQuery = JournalEntryDetail::query()
            ->whereIn('journal_entry_id', (clone $cashEntryQuery)->select('id'))
            ->whereNotIn('account_id', $cashAccountIds) // المقابل لا يكون نقدياً
            ->select(
                'account_id',
                DB::raw('COALESCE(SUM(debit), 0) as period_debit'),
                DB::raw('COALESCE(SUM(credit), 0) as period_credit'),
                DB::raw('COUNT(DISTINCT journal_entry_id) as entries_count')
            )
            ->groupBy('account_id');

        // فلتر نوع الحركة على مستوى DB
        if ($movementType === 'in') {
            $counterQuery->havingRaw('COALESCE(SUM(credit), 0) > 0');
        } elseif ($movementType === 'out') {
            $counterQuery->havingRaw('COALESCE(SUM(debit), 0) > 0');
        } else {
            $counterQuery->havingRaw('(COALESCE(SUM(debit),0) + COALESCE(SUM(credit),0)) > 0');
        }

        if (! empty($accountIdFilter)) {
            $counterQuery->where('account_id', (int) $accountIdFilter);
        }
        if ($minAmount !== null && $minAmount !== '') {
            $counterQuery->havingRaw('(COALESCE(SUM(debit),0) + COALESCE(SUM(credit),0)) >= ?', [(float) $minAmount]);
        }
        if ($maxAmount !== null && $maxAmount !== '') {
            $counterQuery->havingRaw('(COALESCE(SUM(debit),0) + COALESCE(SUM(credit),0)) <= ?', [(float) $maxAmount]);
        }

        $counterRows = $counterQuery->get()->keyBy('account_id');
        $accountIds = $counterRows->keys()->all();
        if (empty($accountIds)) {
            $emptyResult['cashEntryIds'] = $cashEntryIds;
            $emptyResult['totals']['entries_count'] = $cashEntryIds->count();

            return $emptyResult;
        }

        // الخطوة 3: الأرصدة الافتتاحية لكل حساب مقابل (كل حركاته قبل تاريخ البداية)
        $openingBalances = JournalEntryDetail::query()
            ->whereIn('account_id', $accountIds)
            ->whereHas('journalEntry', function ($q) use ($fromDate, $branchFilter) {
                $q->where('status', 1)->where('date', '<', $fromDate);
                if (! empty($branchFilter)) {
                    $q->where('branch_id', $branchFilter);
                }
            })
            ->select('account_id', DB::raw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net'))
            ->groupBy('account_id')
            ->pluck('net', 'account_id');

        // الخطوة 4: جلب معلومات الحسابات + فلاتر الحساب
        $accountsQuery = Account::whereIn('id', $accountIds);
        if ($accountSearch !== '') {
            $accountsQuery->where(function ($q) use ($accountSearch) {
                $q->where('name', 'LIKE', "%{$accountSearch}%")
                    ->orWhere('code', 'LIKE', "%{$accountSearch}%");
            });
        }
        if (! empty($accountTypeFilter)) {
            $accountsQuery->where('account_type', $accountTypeFilter);
        }
        $accounts = $accountsQuery->orderBy('code')->get()->keyBy('id');

        // الخطوة 5: بناء الصفوف
        $rows = collect();
        $totalInflows = 0.0;
        $totalOutflows = 0.0;

        foreach ($counterRows as $aid => $cr) {
            if (! $accounts->has($aid)) {
                continue;
            }
            $account = $accounts[$aid];

            $opening = (float) ($openingBalances[$aid] ?? 0);
            $debit = (float) $cr->period_debit;
            $credit = (float) $cr->period_credit;

            $rows->push([
                'account_id' => (int) $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->account_type,
                'parent_id' => $account->parent_id,
                'balance_type' => $account->balance_type ?? 'debit',
                'opening_balance' => $opening,
                'cash_inflow' => $credit,
                'cash_outflow' => $debit,
                'period_debit' => $debit,
                'period_credit' => $credit,
                'net_change' => $debit - $credit,
                'closing_balance' => $opening + ($debit - $credit),
                'entries_count' => (int) $cr->entries_count,
            ]);

            $totalInflows += $credit;
            $totalOutflows += $debit;
        }

        // الترتيب
        $sortKeyMap = [
            'activity' => fn ($r) => abs($r['cash_inflow']) + abs($r['cash_outflow']),
            'name' => fn ($r) => (string) $r['account_name'],
            'code' => fn ($r) => (string) $r['account_code'],
            'inflow' => fn ($r) => $r['cash_inflow'],
            'outflow' => fn ($r) => $r['cash_outflow'],
            'closing' => fn ($r) => $r['closing_balance'],
        ];
        $sorter = $sortKeyMap[$sortBy] ?? $sortKeyMap['activity'];
        $rows = $sortDir === 'asc' ? $rows->sortBy($sorter)->values() : $rows->sortByDesc($sorter)->values();

        return [
            'rows' => $rows,
            'totals' => [
                'total_inflows' => $totalInflows,
                'total_outflows' => $totalOutflows,
                'net_cash_flow' => $totalInflows - $totalOutflows,
                'accounts_count' => $rows->count(),
                'entries_count' => $cashEntryIds->count(),
            ],
            'cashAccountIds' => $cashAccountIds,
            'cashEntryIds' => $cashEntryIds,
            'filters' => $filters,
        ];
    }

    /**
     * تقرير التدفقات النقدية التفصيلية.
     * يعرض فقط الحسابات التي حدثت لها حركة فعلية مع بنك/خزينة، مع الرصيد الافتتاحي،
     * التدفقات الداخلة/الخارجة، الرصيد الختامي، وعدد القيود + إمكانية الـ drill-down.
     */
    public function detailedCashFlow(Request $request)
    {
        try {
            $branches = Branch::all();

            // قراءة جميع الفلاتر من الـ request + دعم date_type المسبقة
            // الفترةُ علاقةً أوّلاً، والمدى التاريخيّ حين يُطلب صراحةً
            $scope = $this->reportPeriod(
                $request,
                $request->input('date_type', 'custom'),
                $request->input('from_date'),
                $request->input('to_date')
            );
            $reportPeriod = $scope['period'];
            [$fromDate, $toDate] = [$scope['from'], $scope['to']];
            $fromDate = $fromDate ?: now()->startOfMonth()->format('Y-m-d');
            $toDate = $toDate ?: now()->format('Y-m-d');

            $filters = [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'branch' => $request->input('branch'),
                'account_search' => $request->input('account_search'),
                'account_id' => $request->input('account_id'),
                'account_type' => $request->input('account_type'),
                'counter_account_id' => $request->input('counter_account_id'),
                'currency' => $request->input('currency'),
                'reference_number' => $request->input('reference_number'),
                'user_id' => $request->input('user_id'),
                'movement_type' => $request->input('movement_type', 'all'),
                'min_amount' => $request->input('min_amount'),
                'max_amount' => $request->input('max_amount'),
                'sort_by' => $request->input('sort_by', 'activity'),
                'sort_dir' => $request->input('sort_dir', 'desc'),
            ];

            $data = $this->buildDetailedCashFlowData($filters);

            // pagination يدوي على collection (لأن البيانات مُجمَّعة بالفعل)
            $perPage = max(10, min(200, (int) $request->input('per_page', 25)));
            $page = max(1, (int) $request->input('page', 1));
            $rowsAll = $data['rows'];
            $rowsPaged = $rowsAll->slice(($page - 1) * $perPage, $perPage)->values();

            // قوائم للفلاتر (البنوك/الخزائن، أنواع الحسابات)
            $cashAccountsList = Account::whereIn('id', $data['cashAccountIds'])->orderBy('code')->get(['id', 'code', 'name']);
            $accountTypes = Account::query()->select('account_type')->whereNotNull('account_type')->distinct()->pluck('account_type');
            $currencies = JournalEntry::select('currency')->whereNotNull('currency')->distinct()->pluck('currency');

            return view('account::report.accountGeneral.detailed_cash_flow', [
                'rows' => $rowsPaged,
                'rowsAll' => $rowsAll,
                'totals' => $data['totals'],
                'branches' => $branches,
                'cashAccountsList' => $cashAccountsList,
                'accountTypes' => $accountTypes,
                'currencies' => $currencies,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'branchFilter' => $request->input('branch'),
                'accountSearch' => $request->input('account_search'),
                'currentFilters' => $filters,
                'dateType' => $request->input('date_type', 'custom'),
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => max(1, (int) ceil($rowsAll->count() / $perPage)),
            ]);
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'detailedCashFlow');

            Log::error('Detailed Cash Flow Error: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return back()->with('error', 'حدث خطأ في التقرير: '.$e->getMessage());
        }
    }

    /**
     * النسخة الأصلية المختصرة - تستخدم helper مشترك. (يُحتفظ بالاسم القديم للتوافق)
     */
    public function _detailedCashFlowOriginal(Request $request)
    {
        try {
            $branches = Branch::all();

            $fromDate = $request->input('from_date', now()->startOfMonth()->format('Y-m-d'));
            $toDate = $request->input('to_date', now()->format('Y-m-d'));
            $branchFilter = $request->input('branch');
            $accountSearch = trim((string) $request->input('account_search', ''));

            // معرفات حسابات النقدية (البنوك + الخزائن وأبناؤهم)
            $cashAccountIds = $this->getCashFlowAccountIds();

            if (empty($cashAccountIds)) {
                return view('account::report.accountGeneral.detailed_cash_flow', [
                    'rows' => collect(),
                    'totals' => ['total_inflows' => 0, 'total_outflows' => 0, 'net_cash_flow' => 0, 'accounts_count' => 0, 'entries_count' => 0],
                    'branches' => $branches,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'branchFilter' => $branchFilter,
                    'accountSearch' => $accountSearch,
                ]);
            }

            // الخطوة 1: معرفات قيود اليومية في الفترة التي يحوي تفصيل منها حساب نقدي
            $cashEntryIdsQuery = JournalEntry::query()
                ->where('status', 1)
                ->whereBetween('date', [$fromDate, $toDate])
                ->whereHas('details', function ($q) use ($cashAccountIds) {
                    $q->whereIn('account_id', $cashAccountIds);
                });
            if (! empty($branchFilter)) {
                $cashEntryIdsQuery->where('branch_id', $branchFilter);
            }
            $cashEntryIds = $cashEntryIdsQuery->pluck('id');

            if ($cashEntryIds->isEmpty()) {
                return view('account::report.accountGeneral.detailed_cash_flow', [
                    'rows' => collect(),
                    'totals' => ['total_inflows' => 0, 'total_outflows' => 0, 'net_cash_flow' => 0, 'accounts_count' => 0, 'entries_count' => 0],
                    'branches' => $branches,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'branchFilter' => $branchFilter,
                    'accountSearch' => $accountSearch,
                ]);
            }

            // الخطوة 2: تجميع الحسابات المقابلة (غير النقدية) من تلك القيود فقط
            $counterRows = JournalEntryDetail::query()
                ->whereIn('journal_entry_id', $cashEntryIds)
                ->whereNotIn('account_id', $cashAccountIds)
                ->select(
                    'account_id',
                    DB::raw('COALESCE(SUM(debit), 0) as period_debit'),
                    DB::raw('COALESCE(SUM(credit), 0) as period_credit'),
                    DB::raw('COUNT(DISTINCT journal_entry_id) as entries_count')
                )
                ->groupBy('account_id')
                ->havingRaw('(COALESCE(SUM(debit),0) + COALESCE(SUM(credit),0)) > 0')
                ->get()
                ->keyBy('account_id');

            $accountIds = $counterRows->keys()->all();

            if (empty($accountIds)) {
                return view('account::report.accountGeneral.detailed_cash_flow', [
                    'rows' => collect(),
                    'totals' => ['total_inflows' => 0, 'total_outflows' => 0, 'net_cash_flow' => 0, 'accounts_count' => 0, 'entries_count' => $cashEntryIds->count()],
                    'branches' => $branches,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'branchFilter' => $branchFilter,
                    'accountSearch' => $accountSearch,
                ]);
            }

            // الخطوة 3: الأرصدة الافتتاحية (مجموع كل حركات الحساب قبل تاريخ البداية - لا يقتصر على الحركات النقدية)
            $openingBalances = JournalEntryDetail::query()
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $branchFilter) {
                    $q->where('status', 1)->where('date', '<', $fromDate);
                    if (! empty($branchFilter)) {
                        $q->where('branch_id', $branchFilter);
                    }
                })
                ->select(
                    'account_id',
                    DB::raw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
                )
                ->groupBy('account_id')
                ->pluck('net', 'account_id');

            // الخطوة 4: جلب معلومات الحسابات
            $accountsQuery = Account::query()->whereIn('id', $accountIds);
            if ($accountSearch !== '') {
                $accountsQuery->where(function ($q) use ($accountSearch) {
                    $q->where('name', 'LIKE', "%{$accountSearch}%")
                        ->orWhere('code', 'LIKE', "%{$accountSearch}%");
                });
            }
            $accounts = $accountsQuery->orderBy('code')->get();

            // الخطوة 5: بناء الصفوف النهائية
            $rows = collect();
            $totalInflows = 0.0;
            $totalOutflows = 0.0;

            foreach ($accounts as $account) {
                $cr = $counterRows[$account->id] ?? null;
                if (! $cr) {
                    continue;
                }

                $opening = (float) ($openingBalances[$account->id] ?? 0);
                $periodDebit = (float) $cr->period_debit;
                $periodCredit = (float) $cr->period_credit;

                // من منظور النقدية: عندما يكون الحساب المقابل دائناً → النقدية تستلم (داخل)
                //                  عندما يكون الحساب المقابل مديناً → النقدية تدفع (خارج)
                $cashInflow = $periodCredit;  // النقدية دخلت من هذا الحساب
                $cashOutflow = $periodDebit;   // النقدية خرجت إلى هذا الحساب

                $netChange = $periodDebit - $periodCredit; // التغير على الحساب نفسه (debit-natural)
                $closing = $opening + $netChange;

                $rows->push([
                    'account_id' => (int) $account->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'balance_type' => $account->balance_type ?? 'debit',
                    'opening_balance' => $opening,
                    'cash_inflow' => $cashInflow,
                    'cash_outflow' => $cashOutflow,
                    'period_debit' => $periodDebit,
                    'period_credit' => $periodCredit,
                    'net_change' => $netChange,
                    'closing_balance' => $closing,
                    'entries_count' => (int) $cr->entries_count,
                ]);

                $totalInflows += $cashInflow;
                $totalOutflows += $cashOutflow;
            }

            // ترتيب حسب حجم النشاط (الأكثر حركة أولاً)
            $rows = $rows->sortByDesc(fn ($r) => abs($r['cash_inflow']) + abs($r['cash_outflow']))->values();

            $totals = [
                'total_inflows' => $totalInflows,
                'total_outflows' => $totalOutflows,
                'net_cash_flow' => $totalInflows - $totalOutflows,
                'accounts_count' => $rows->count(),
                'entries_count' => $cashEntryIds->count(),
            ];

            return view('account::report.accountGeneral.detailed_cash_flow', [
                'rows' => $rows,
                'totals' => $totals,
                'branches' => $branches,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'branchFilter' => $branchFilter,
                'accountSearch' => $accountSearch,
            ]);
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', '_detailedCashFlowOriginal');

            Log::error('Detailed Cash Flow Error: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ في جلب التدفقات النقدية: '.$e->getMessage());
        }
    }

    /**
     * صفحة تفاصيل حساب واحد ضمن التدفقات النقدية التفصيلية.
     * تعرض كل القيود التي مرّ بها الحساب وتضمنت بنكاً/خزينة، مع رصيد متجدد.
     */
    public function detailedCashFlowAccount(Request $request, $accountId)
    {
        try {
            $account = Account::findOrFail($accountId);

            $fromDate = $request->input('from_date', now()->startOfMonth()->format('Y-m-d'));
            $toDate = $request->input('to_date', now()->format('Y-m-d'));
            $branchFilter = $request->input('branch');

            $cashAccountIds = $this->getCashFlowAccountIds();

            // الرصيد الافتتاحي قبل الفترة (كل الحركات على الحساب)
            $openingQuery = JournalEntryDetail::query()
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $branchFilter) {
                    $q->where('status', 1)->where('date', '<', $fromDate);
                    if (! empty($branchFilter)) {
                        $q->where('branch_id', $branchFilter);
                    }
                });
            $openingDebit = (float) (clone $openingQuery)->sum('debit');
            $openingCredit = (float) (clone $openingQuery)->sum('credit');
            $openingBalance = $openingDebit - $openingCredit;

            // معرفات قيود الفترة التي تحوي بنكاً/خزينة وفيها هذا الحساب
            $entryIdsQuery = JournalEntry::query()
                ->where('status', 1)
                ->whereBetween('date', [$fromDate, $toDate])
                ->whereHas('details', function ($q) use ($cashAccountIds) {
                    $q->whereIn('account_id', $cashAccountIds);
                })
                ->whereHas('details', function ($q) use ($account) {
                    $q->where('account_id', $account->id);
                });
            if (! empty($branchFilter)) {
                $entryIdsQuery->where('branch_id', $branchFilter);
            }
            $entries = $entryIdsQuery->with(['details.account', 'branch'])
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            // بناء صفوف الحركات + رصيد متجدد
            $movements = [];
            $running = $openingBalance;
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($entries as $entry) {
                // كل سطور هذا الحساب في القيد
                $accountDetails = $entry->details->where('account_id', $account->id);
                // الحسابات المقابلة في القيد (غير هذا الحساب) - نفصل البنك/الخزينة عن غيرها
                $otherDetails = $entry->details->where('account_id', '!=', $account->id);
                $cashCounter = $otherDetails->filter(fn ($d) => in_array($d->account_id, $cashAccountIds));
                $nonCashCounter = $otherDetails->filter(fn ($d) => ! in_array($d->account_id, $cashAccountIds));

                $cashCounterNames = $cashCounter->pluck('account.name')->filter()->unique()->implode(', ');
                $otherCounterNames = $nonCashCounter->pluck('account.name')->filter()->unique()->implode(', ');

                foreach ($accountDetails as $d) {
                    $debit = (float) $d->debit;
                    $credit = (float) $d->credit;
                    $running += $debit - $credit;

                    // نوع العملية: استلام نقدي (الحساب credit) / دفع نقدي (الحساب debit)
                    $opType = $credit > 0 ? 'استلام نقدي' : ($debit > 0 ? 'دفع نقدي' : '-');

                    $movements[] = [
                        'date' => optional($entry->date)->format('Y-m-d') ?? $entry->date,
                        'reference_number' => $entry->reference_number,
                        'entry_id' => $entry->id,
                        'description' => $d->description ?: $entry->description,
                        'cash_counter' => $cashCounterNames,    // البنك/الخزينة المقابل
                        'other_counter' => $otherCounterNames,   // أي حسابات أخرى في نفس القيد
                        'op_type' => $opType,
                        'debit' => $debit,
                        'credit' => $credit,
                        'running_balance' => $running,
                        'branch' => optional($entry->branch)->name,
                    ];

                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }
            }

            $closingBalance = $openingBalance + $totalDebit - $totalCredit;

            return view('account::report.accountGeneral.detailed_cash_flow_account', [
                'account' => $account,
                'movements' => $movements,
                'openingBalance' => $openingBalance,
                'closingBalance' => $closingBalance,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'branchFilter' => $branchFilter,
                'branches' => Branch::all(),
            ]);
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'detailedCashFlowAccount');

            Log::error('Detailed Cash Flow (Account) Error: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * النسخة القديمة من تقرير الربح والخسارة (محفوظة للتوافق مع الصفحات/الروابط القديمة).
     * تستخدم العرض القديم profit_loss.blade.php مع المنطق الشهري التفصيلي.
     */
    public function profitLossLegacy(Request $request)
    {
        try {
            $branches = Branch::all();
            $costCenters = CostCenter::all();

            // نوع التقرير (cash = نقدي, accrual = مستحق) - افتراضي نقدي
            $reportType = $request->input('report_type', 'cash');

            // تحديد الفترة الزمنية (افتراضي: آخر سنة)
            $fromDate = $request->input('from_date', now()->subYear()->format('Y-m-d'));
            $toDate = $request->input('to_date', now()->format('Y-m-d'));

            $branchFilter = $request->input('branch');
            $costCenterFilter = $request->input('cost_center');

            // تحويل التواريخ إلى Carbon
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);

            // إنشاء قائمة الأشهر
            $months = [];
            $currentMonth = $startDate->copy()->startOfMonth();
            while ($currentMonth <= $endDate) {
                $months[] = [
                    'year' => $currentMonth->year,
                    'month' => $currentMonth->month,
                    'label' => $currentMonth->translatedFormat('M Y'),
                    'start' => $currentMonth->copy()->startOfMonth()->format('Y-m-d'),
                    'end' => $currentMonth->copy()->endOfMonth()->format('Y-m-d'),
                ];
                $currentMonth->addMonth();
            }

            // معرفات الحسابات المهمة
            $salesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('sales', SystemAccountIds::SALES)); // 44
            $salesReturnsAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('sales_returns', SystemAccountIds::SALES_RETURNS)); // 45
            $purchasesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('purchases', SystemAccountIds::PURCHASES)); // 25
            $clientsAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('clients_account', SystemAccountIds::CLIENTS_ACCOUNT)); // 18 - العملاء
            $costOfSalesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('cost_of_sales_root', SystemAccountIds::COST_OF_SALES_ROOT)); // 49
            $adminExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('admin_expenses_parent', SystemAccountIds::ADMIN_EXPENSES_PARENT)); // 53
            $otherExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('other_expenses_parent', SystemAccountIds::OTHER_EXPENSES_PARENT)); // 61
            $otherRevenueAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('other_revenue_parent', SystemAccountIds::OTHER_REVENUE_PARENT)); // 46

            // حسابات المصروفات الإضافية
            $salariesExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('salaries_wages_parent', SystemAccountIds::SALARIES_WAGES_PARENT)); // 3041 - الرواتب والأجور
            $depreciationExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('depreciation_expense', SystemAccountIds::DEPRECIATION_EXPENSE)); // 60 - مصروفات الإهلاك
            $manufacturingExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('manufacturing_expenses_parent', SystemAccountIds::MANUFACTURING_EXPENSES_PARENT)); // 2962 - مصروفات التصنيع
            $marketingExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('marketing_expenses_parent', SystemAccountIds::MARKETING_EXPENSES_PARENT)); // 180 - مصروفات التسويق
            $transportExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('transport_expenses_parent', SystemAccountIds::TRANSPORT_EXPENSES_PARENT)); // 187 - مصروفات النقل
            $financeExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('finance_expenses_parent', SystemAccountIds::FINANCE_EXPENSES_PARENT)); // 192 - مصروفات التمويل

            // جميع حسابات المصروفات (تشمل: تسويق، نقل، تمويل، إدارية، أخرى)
            $allExpensesAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('expenses', SystemAccountIds::EXPENSES)); // 5 - كل المصروفات

            // معرفات للخزائن والبنوك - أي حركة طرفها بنك/خزينة (وأبناؤهما) تُعتبر تدفقاً نقدياً تفصيلياً
            $cashAccountIds = $this->getCashFlowAccountIds();

            $monthlyDetailed = [];
            $totalRevenues = 0;
            $totalExpenses = 0;
            $monthLabels = [];
            $monthRevenues = [];
            $monthExpenses = [];
            $monthProfits = [];

            foreach ($months as $monthInfo) {
                // بناء الاستعلام الأساسي للشهر
                $baseQuery = JournalEntry::with(['details.account'])
                    ->where('status', 1) // قيود معتمدة فقط
                    ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', '!=', ReferenceType::YEAR_CLOSING->value)) // لا قيدَ الإقفال السنوي
                    ->whereBetween('date', [$monthInfo['start'], $monthInfo['end']]);

                if ($branchFilter) {
                    $baseQuery->where('branch_id', $branchFilter);
                }
                if ($costCenterFilter) {
                    $baseQuery->whereHas('details', function ($q) use ($costCenterFilter) {
                        $q->where('cost_center_id', $costCenterFilter);
                    });
                }

                $entries = $baseQuery->get();

                // حساب القيم بناءً على نوع التقرير
                if ($reportType === 'cash') {
                    // التقرير النقدي: نبحث عن القيود التي تتضمن حركة نقدية (خزينة/بنك)
                    $monthData = $this->calculateCashBasisData($entries, $cashAccountIds, $salesAccountIds,
                        $salesReturnsAccountIds, $purchasesAccountIds, $clientsAccountIds, $costOfSalesAccountIds,
                        $adminExpensesAccountIds, $otherExpensesAccountIds, $otherRevenueAccountIds,
                        $salariesExpensesAccountIds, $depreciationExpensesAccountIds, $manufacturingExpensesAccountIds,
                        $marketingExpensesAccountIds, $transportExpensesAccountIds, $financeExpensesAccountIds);

                    // للتقرير النقدي
                    $totalReceipts = $monthData['cash_sales'] + $monthData['client_collections'] + $monthData['other_cash_receipts'];
                    $totalPayments = $monthData['cash_refunds'] + $monthData['cash_purchases'] + $monthData['cash_expenses'];
                    $monthProfit = $totalReceipts - $totalPayments;

                    $monthlyDetailed[] = [
                        'label' => $monthInfo['label'],
                        'year' => $monthInfo['year'],
                        'month' => $monthInfo['month'],
                        // المقبوضات النقدية
                        'row1' => $monthData['cash_sales'],
                        'row2' => $monthData['client_collections'],
                        'row3' => $monthData['other_cash_receipts'],
                        'total_receipts' => $totalReceipts,
                        // المدفوعات النقدية
                        'exp1' => $monthData['cash_refunds'],
                        'exp2' => $monthData['cash_purchases'],
                        'exp3' => $monthData['cash_expenses'],
                        'total_expenses' => $totalPayments,
                        // تفصيل المصروفات النقدية
                        'salaries_expenses' => $monthData['salaries_expenses'],
                        'depreciation_expenses' => $monthData['depreciation_expenses'],
                        'manufacturing_expenses' => $monthData['manufacturing_expenses'],
                        'marketing_expenses' => $monthData['marketing_expenses'],
                        'transport_expenses' => $monthData['transport_expenses'],
                        'finance_expenses' => $monthData['finance_expenses'],
                        'admin_expenses' => $monthData['admin_expenses'],
                        'other_expenses' => $monthData['other_expenses'],
                        // صافي التدفق النقدي
                        'profit' => $monthProfit,
                    ];

                } else {
                    // التقرير بالاستحقاق: نحسب كل الحركات
                    $monthData = $this->calculateAccrualBasisDataExtended($entries, $salesAccountIds,
                        $salesReturnsAccountIds, $purchasesAccountIds, $clientsAccountIds, $costOfSalesAccountIds,
                        $adminExpensesAccountIds, $otherExpensesAccountIds, $otherRevenueAccountIds,
                        $salariesExpensesAccountIds, $depreciationExpensesAccountIds, $manufacturingExpensesAccountIds,
                        $marketingExpensesAccountIds, $transportExpensesAccountIds, $financeExpensesAccountIds);

                    // للتقرير الاستحقاقي (حسب المعايير المحاسبية)
                    $netSales = $monthData['sales'] - $monthData['sales_returns'];
                    $grossProfit = $netSales - $monthData['cost_of_sales'];

                    // إجمالي المصروفات التشغيلية
                    $totalOperatingExpenses = $monthData['admin_expenses'] + $monthData['salaries_expenses'] +
                        $monthData['depreciation_expenses'] + $monthData['manufacturing_expenses'] +
                        $monthData['marketing_expenses'] + $monthData['transport_expenses'] + $monthData['finance_expenses'];

                    $operatingProfit = $grossProfit - $totalOperatingExpenses;
                    $monthProfit = $operatingProfit + $monthData['other_revenue'] - $monthData['other_expenses'];

                    $totalReceipts = $monthData['sales'] + $monthData['other_revenue'];
                    $totalPayments = $monthData['sales_returns'] + $monthData['cost_of_sales'] +
                                     $totalOperatingExpenses + $monthData['other_expenses'];

                    $monthlyDetailed[] = [
                        'label' => $monthInfo['label'],
                        'year' => $monthInfo['year'],
                        'month' => $monthInfo['month'],
                        // الإيرادات
                        'row1' => $monthData['sales'],
                        'row2' => $monthData['other_revenue'],
                        'row3' => 0, // مردودات المبيعات ستظهر في المصروفات
                        'total_receipts' => $totalReceipts,
                        // المصروفات والتكاليف
                        'exp1' => $monthData['sales_returns'],
                        'exp2' => $monthData['cost_of_sales'],
                        'exp3' => $totalOperatingExpenses + $monthData['other_expenses'],
                        'total_expenses' => $totalPayments,
                        // صافي الربح
                        'profit' => $monthProfit,
                        // بيانات تفصيلية إضافية للتقرير الاستحقاقي
                        'sales' => $monthData['sales'],
                        'sales_returns' => $monthData['sales_returns'],
                        'net_sales' => $netSales,
                        'cost_of_sales' => $monthData['cost_of_sales'],
                        'gross_profit' => $grossProfit,
                        'admin_expenses' => $monthData['admin_expenses'],
                        'salaries_expenses' => $monthData['salaries_expenses'],
                        'depreciation_expenses' => $monthData['depreciation_expenses'],
                        'manufacturing_expenses' => $monthData['manufacturing_expenses'],
                        'marketing_expenses' => $monthData['marketing_expenses'],
                        'transport_expenses' => $monthData['transport_expenses'],
                        'finance_expenses' => $monthData['finance_expenses'],
                        'total_operating_expenses' => $totalOperatingExpenses,
                        'operating_profit' => $operatingProfit,
                        'other_revenue' => $monthData['other_revenue'],
                        'other_expenses' => $monthData['other_expenses'],
                    ];
                }

                $totalRevenues += $totalReceipts;
                $totalExpenses += $totalPayments;

                // بيانات الرسم البياني
                $monthLabels[] = $monthInfo['label'];
                $monthRevenues[] = round($totalReceipts, 2);
                $monthExpenses[] = round($totalPayments, 2);
                $monthProfits[] = round($monthProfit, 2);
            }

            $netProfit = $totalRevenues - $totalExpenses;
            $profitMargin = $totalRevenues > 0 ? ($netProfit / $totalRevenues) * 100 : 0;

            // جمع بيانات حسابات المصروفات الشهرية
            $expenseAccountsMonthly = $this->collectExpenseAccountsMonthly($months, $branchFilter, $costCenterFilter, $reportType, $cashAccountIds);

            // بيانات الرسم البياني
            $monthlyData = [
                'labels' => $monthLabels,
                'revenues' => $monthRevenues,
                'expenses' => $monthExpenses,
                'profits' => $monthProfits,
                'detailed' => $monthlyDetailed,
            ];

            // بيانات الرسم البياني للملخص
            $chartData = [
                'labels' => ['الإيرادات', 'المصروفات', 'صافي الربح'],
                'amounts' => [
                    abs($totalRevenues),
                    abs($totalExpenses),
                    abs($netProfit),
                ],
                'colors' => [
                    '#28a745', // أخضر للإيرادات
                    '#dc3545', // أحمر للمصروفات
                    $netProfit >= 0 ? '#007bff' : '#dc3545', // أزرق للربح، أحمر للخسارة
                ],
            ];

            // الإجماليات النهائية
            $totals = [
                'total_revenues' => $totalRevenues,
                'total_expenses_all' => $totalExpenses,
                'net_profit' => $netProfit,
                'net_profit_margin' => round($profitMargin, 2),
            ];

            // حساب الإجماليات التفصيلية للتقرير الاستحقاقي
            if ($reportType === 'accrual') {
                $totalSales = array_sum(array_column($monthlyDetailed, 'sales'));
                $totalSalesReturns = array_sum(array_column($monthlyDetailed, 'sales_returns'));
                $netSalesTotal = $totalSales - $totalSalesReturns;
                $totalCostOfSales = array_sum(array_column($monthlyDetailed, 'cost_of_sales'));
                $grossProfitTotal = $netSalesTotal - $totalCostOfSales;

                // المصروفات التشغيلية التفصيلية
                $totalAdminExpenses = array_sum(array_column($monthlyDetailed, 'admin_expenses'));
                $totalSalariesExpenses = array_sum(array_column($monthlyDetailed, 'salaries_expenses'));
                $totalDepreciationExpenses = array_sum(array_column($monthlyDetailed, 'depreciation_expenses'));
                $totalManufacturingExpenses = array_sum(array_column($monthlyDetailed, 'manufacturing_expenses'));
                $totalMarketingExpenses = array_sum(array_column($monthlyDetailed, 'marketing_expenses'));
                $totalTransportExpenses = array_sum(array_column($monthlyDetailed, 'transport_expenses'));
                $totalFinanceExpenses = array_sum(array_column($monthlyDetailed, 'finance_expenses'));

                $totalOperatingExpenses = $totalAdminExpenses + $totalSalariesExpenses + $totalDepreciationExpenses +
                    $totalManufacturingExpenses + $totalMarketingExpenses + $totalTransportExpenses + $totalFinanceExpenses;

                $operatingProfitTotal = $grossProfitTotal - $totalOperatingExpenses;
                $totalOtherRevenue = array_sum(array_column($monthlyDetailed, 'other_revenue'));
                $totalOtherExpenses = array_sum(array_column($monthlyDetailed, 'other_expenses'));

                $totals['total_sales'] = $totalSales;
                $totals['total_sales_returns'] = $totalSalesReturns;
                $totals['net_sales'] = $netSalesTotal;
                $totals['total_cost_of_sales'] = $totalCostOfSales;
                $totals['gross_profit'] = $grossProfitTotal;

                // المصروفات التشغيلية التفصيلية
                $totals['total_admin_expenses'] = $totalAdminExpenses;
                $totals['total_salaries_expenses'] = $totalSalariesExpenses;
                $totals['total_depreciation_expenses'] = $totalDepreciationExpenses;
                $totals['total_manufacturing_expenses'] = $totalManufacturingExpenses;
                $totals['total_marketing_expenses'] = $totalMarketingExpenses;
                $totals['total_transport_expenses'] = $totalTransportExpenses;
                $totals['total_finance_expenses'] = $totalFinanceExpenses;
                $totals['total_operating_expenses'] = $totalOperatingExpenses;

                $totals['operating_profit'] = $operatingProfitTotal;
                $totals['total_other_revenue'] = $totalOtherRevenue;
                $totals['total_other_expenses'] = $totalOtherExpenses;

                // نسب الربحية
                $totals['gross_profit_margin'] = $netSalesTotal > 0 ? ($grossProfitTotal / $netSalesTotal) * 100 : 0;
                $totals['operating_profit_margin'] = $netSalesTotal > 0 ? ($operatingProfitTotal / $netSalesTotal) * 100 : 0;
            } else {
                // حساب الإجماليات التفصيلية للتقرير النقدي
                $totalCashSales = array_sum(array_column($monthlyDetailed, 'row1'));
                $totalClientCollections = array_sum(array_column($monthlyDetailed, 'row2'));
                $totalOtherCashReceipts = array_sum(array_column($monthlyDetailed, 'row3'));

                $totalCashRefunds = array_sum(array_column($monthlyDetailed, 'exp1'));
                $totalCashPurchases = array_sum(array_column($monthlyDetailed, 'exp2'));
                $totalCashExpenses = array_sum(array_column($monthlyDetailed, 'exp3'));

                // المصروفات النقدية التفصيلية
                $totals['total_salaries_expenses'] = array_sum(array_column($monthlyDetailed, 'salaries_expenses'));
                $totals['total_depreciation_expenses'] = array_sum(array_column($monthlyDetailed, 'depreciation_expenses'));
                $totals['total_manufacturing_expenses'] = array_sum(array_column($monthlyDetailed, 'manufacturing_expenses'));
                $totals['total_marketing_expenses'] = array_sum(array_column($monthlyDetailed, 'marketing_expenses'));
                $totals['total_transport_expenses'] = array_sum(array_column($monthlyDetailed, 'transport_expenses'));
                $totals['total_finance_expenses'] = array_sum(array_column($monthlyDetailed, 'finance_expenses'));
                $totals['total_admin_expenses'] = array_sum(array_column($monthlyDetailed, 'admin_expenses'));
                $totals['total_other_expenses'] = array_sum(array_column($monthlyDetailed, 'other_expenses'));

                $totals['total_cash_sales'] = $totalCashSales;
                $totals['total_client_collections'] = $totalClientCollections;
                $totals['total_other_cash_receipts'] = $totalOtherCashReceipts;
                $totals['total_cash_refunds'] = $totalCashRefunds;
                $totals['total_cash_purchases'] = $totalCashPurchases;
                $totals['total_cash_expenses'] = $totalCashExpenses;
            }

            return view('account::report.accountGeneral.profit_loss', [
                'branches' => $branches,
                'costCenters' => $costCenters,
                'reportType' => $reportType,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'monthlyData' => $monthlyData,
                'chartData' => $chartData,
                'totals' => $totals,
                'expenseAccountsMonthly' => $expenseAccountsMonthly,
                'netProfit' => $netProfit,
            ]);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'profitLossLegacy');

            Log::error('Profit and Loss Report Error: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return view('account::report.accountGeneral.profit_loss', [
                'branches' => Branch::all(),
                'costCenters' => CostCenter::all(),
                'reportType' => $request->input('report_type', 'accrual'),
                'fromDate' => $request->input('from_date', now()->subYear()->format('Y-m-d')),
                'toDate' => $request->input('to_date', now()->format('Y-m-d')),
                'monthlyData' => ['labels' => [], 'revenues' => [], 'expenses' => [], 'profits' => [], 'detailed' => []],
                'chartData' => ['labels' => [], 'amounts' => [], 'colors' => []],
                'totals' => $this->getEmptyTotals(),
                'expenseAccountsMonthly' => [],
                'netProfit' => 0,
            ])->with('error', 'حدث خطأ في جلب البيانات: '.$e->getMessage());
        }
    }

    /**
     * جمع بيانات حسابات المصروفات الشهرية
     */
    private function collectExpenseAccountsMonthly($months, $branchFilter, $costCenterFilter, $reportType, $cashAccountIds)
    {
        $expenseAccounts = [];
        $monthLabels = [];

        // الحصول على جميع حسابات المصروفات (جذر 5)
        $expenseRootId = AccountingSettings::id('expenses', SystemAccountIds::EXPENSES); // 5

        // معرفات الحسابات الأب لاستبعادها
        $parentAccountIds = Account::whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->flip();

        foreach ($months as $monthInfo) {
            $monthLabels[] = $monthInfo['label'];

            // بناء الاستعلام للشهر
            $baseQuery = JournalEntry::with(['details.account'])
                ->where('status', 1)
                ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', '!=', ReferenceType::YEAR_CLOSING->value)) // لا قيدَ الإقفال السنوي
                ->whereBetween('date', [$monthInfo['start'], $monthInfo['end']]);

            if ($branchFilter) {
                $baseQuery->where('branch_id', $branchFilter);
            }
            if ($costCenterFilter) {
                $baseQuery->whereHas('details', function ($q) use ($costCenterFilter) {
                    $q->where('cost_center_id', $costCenterFilter);
                });
            }

            $entries = $baseQuery->get();

            foreach ($entries as $entry) {
                // للتقرير النقدي، نتحقق من وجود حركة نقدية
                if ($reportType === 'cash') {
                    $hasCashMovement = false;
                    foreach ($entry->details as $detail) {
                        if ($detail->account && in_array($detail->account->id, $cashAccountIds)) {
                            $hasCashMovement = true;
                            break;
                        }
                    }
                    if (! $hasCashMovement) {
                        continue;
                    }
                }

                foreach ($entry->details as $detail) {
                    if (! $detail->account) {
                        continue;
                    }

                    $account = $detail->account;

                    // استبعاد الحسابات الأب
                    if (isset($parentAccountIds[$account->id])) {
                        continue;
                    }

                    $rootId = $this->getRootAccountId($account);

                    // المصروفات فقط (الجذر 5)
                    if ($rootId === $expenseRootId) {
                        $debit = $detail->debit ?? 0;
                        $credit = $detail->credit ?? 0;
                        $balance = $debit - $credit;

                        if ($balance == 0) {
                            continue;
                        }

                        $accountId = $account->id;

                        if (! isset($expenseAccounts[$accountId])) {
                            $expenseAccounts[$accountId] = [
                                'id' => $accountId,
                                'name' => $account->name,
                                'code' => $account->code,
                                'monthly' => array_fill(0, count($months), 0),
                                'total' => 0,
                            ];
                        }

                        // تحديد index الشهر
                        $monthIndex = array_search($monthInfo['label'], $monthLabels);
                        if ($monthIndex !== false) {
                            $expenseAccounts[$accountId]['monthly'][$monthIndex] += $balance;
                            $expenseAccounts[$accountId]['total'] += $balance;
                        }
                    }
                }
            }
        }

        // ترتيب حسب الكود
        usort($expenseAccounts, function ($a, $b) {
            return strcmp($a['code'], $b['code']);
        });

        return [
            'accounts' => array_values($expenseAccounts),
            'labels' => $monthLabels,
        ];
    }

    /**
     * حساب البيانات بأساس الاستحقاق (Accrual Basis)
     * يحسب الإيرادات والمصروفات بناءً على تاريخ الاستحقاق (تاريخ الفاتورة)
     * بغض النظر عن التحصيل النقدي
     */
    private function calculateAccrualBasisData($entries, $salesAccountIds, $salesReturnsAccountIds,
        $purchasesAccountIds, $clientsAccountIds, $costOfSalesAccountIds, $adminExpensesAccountIds,
        $otherExpensesAccountIds, $otherRevenueAccountIds)
    {
        $data = [
            // الإيرادات
            'sales' => 0,              // المبيعات
            'sales_returns' => 0,      // مردودات المبيعات
            'other_revenue' => 0,      // إيرادات أخرى
            // التكاليف والمصروفات
            'cost_of_sales' => 0,      // تكلفة المبيعات
            'purchases' => 0,          // المشتريات
            'admin_expenses' => 0,     // المصروفات الإدارية والعمومية
            'other_expenses' => 0,     // مصروفات أخرى
        ];

        foreach ($entries as $entry) {
            foreach ($entry->details as $detail) {
                if (! $detail->account) {
                    continue;
                }

                $accountId = $detail->account->id;
                $debit = $detail->debit ?? 0;
                $credit = $detail->credit ?? 0;

                // المبيعات (الإيرادات طبيعتها دائنة)
                if (in_array($accountId, $salesAccountIds)) {
                    $data['sales'] += $credit - $debit;
                }

                // مردودات المبيعات (مدين - تخفض المبيعات)
                if (in_array($accountId, $salesReturnsAccountIds)) {
                    $data['sales_returns'] += $debit - $credit;
                }

                // إيرادات أخرى (دائن)
                if (in_array($accountId, $otherRevenueAccountIds)) {
                    $data['other_revenue'] += $credit - $debit;
                }

                // تكلفة المبيعات (مدين)
                if (in_array($accountId, $costOfSalesAccountIds)) {
                    $data['cost_of_sales'] += $debit - $credit;
                }

                // المشتريات (مدين)
                if (in_array($accountId, $purchasesAccountIds)) {
                    $data['purchases'] += $debit - $credit;
                }

                // المصروفات الإدارية والعمومية (مدين)
                if (in_array($accountId, $adminExpensesAccountIds)) {
                    $data['admin_expenses'] += $debit - $credit;
                }

                // مصروفات أخرى (مدين)
                if (in_array($accountId, $otherExpensesAccountIds)) {
                    $data['other_expenses'] += $debit - $credit;
                }
            }
        }

        return $data;
    }

    /**
     * حساب البيانات بأساس الاستحقاق الموسع (Accrual Basis Extended)
     * يشمل جميع أنواع المصروفات التشغيلية
     */
    private function calculateAccrualBasisDataExtended($entries, $salesAccountIds, $salesReturnsAccountIds,
        $purchasesAccountIds, $clientsAccountIds, $costOfSalesAccountIds, $adminExpensesAccountIds,
        $otherExpensesAccountIds, $otherRevenueAccountIds, $salariesExpensesAccountIds,
        $depreciationExpensesAccountIds, $manufacturingExpensesAccountIds, $marketingExpensesAccountIds,
        $transportExpensesAccountIds, $financeExpensesAccountIds)
    {
        $data = [
            // الإيرادات
            'sales' => 0,                    // المبيعات
            'sales_returns' => 0,            // مردودات المبيعات
            'other_revenue' => 0,            // إيرادات أخرى
            // التكاليف والمصروفات
            'cost_of_sales' => 0,            // تكلفة المبيعات
            'purchases' => 0,                // المشتريات
            'admin_expenses' => 0,           // المصروفات الإدارية والعمومية
            'salaries_expenses' => 0,        // الرواتب والأجور
            'depreciation_expenses' => 0,    // مصروفات الإهلاك
            'manufacturing_expenses' => 0,   // مصروفات التصنيع
            'marketing_expenses' => 0,       // مصروفات التسويق
            'transport_expenses' => 0,       // مصروفات النقل
            'finance_expenses' => 0,         // مصروفات التمويل
            'other_expenses' => 0,           // مصروفات أخرى
        ];

        foreach ($entries as $entry) {
            foreach ($entry->details as $detail) {
                if (! $detail->account) {
                    continue;
                }

                $accountId = $detail->account->id;
                $debit = $detail->debit ?? 0;
                $credit = $detail->credit ?? 0;

                // المبيعات (الإيرادات طبيعتها دائنة)
                if (in_array($accountId, $salesAccountIds)) {
                    $data['sales'] += $credit - $debit;

                    continue;
                }

                // مردودات المبيعات (مدين - تخفض المبيعات)
                if (in_array($accountId, $salesReturnsAccountIds)) {
                    $data['sales_returns'] += $debit - $credit;

                    continue;
                }

                // إيرادات أخرى (دائن)
                if (in_array($accountId, $otherRevenueAccountIds)) {
                    $data['other_revenue'] += $credit - $debit;

                    continue;
                }

                // تكلفة المبيعات (مدين)
                if (in_array($accountId, $costOfSalesAccountIds)) {
                    $data['cost_of_sales'] += $debit - $credit;

                    continue;
                }

                // المشتريات (مدين)
                if (in_array($accountId, $purchasesAccountIds)) {
                    $data['purchases'] += $debit - $credit;

                    continue;
                }

                // الرواتب والأجور (مدين)
                if (in_array($accountId, $salariesExpensesAccountIds)) {
                    $data['salaries_expenses'] += $debit - $credit;

                    continue;
                }

                // مصروفات الإهلاك (مدين)
                if (in_array($accountId, $depreciationExpensesAccountIds)) {
                    $data['depreciation_expenses'] += $debit - $credit;

                    continue;
                }

                // مصروفات التصنيع (مدين)
                if (in_array($accountId, $manufacturingExpensesAccountIds)) {
                    $data['manufacturing_expenses'] += $debit - $credit;

                    continue;
                }

                // مصروفات التسويق (مدين)
                if (in_array($accountId, $marketingExpensesAccountIds)) {
                    $data['marketing_expenses'] += $debit - $credit;

                    continue;
                }

                // مصروفات النقل (مدين)
                if (in_array($accountId, $transportExpensesAccountIds)) {
                    $data['transport_expenses'] += $debit - $credit;

                    continue;
                }

                // مصروفات التمويل (مدين)
                if (in_array($accountId, $financeExpensesAccountIds)) {
                    $data['finance_expenses'] += $debit - $credit;

                    continue;
                }

                // المصروفات الإدارية والعمومية (مدين)
                if (in_array($accountId, $adminExpensesAccountIds)) {
                    $data['admin_expenses'] += $debit - $credit;

                    continue;
                }

                // مصروفات أخرى (مدين)
                if (in_array($accountId, $otherExpensesAccountIds)) {
                    $data['other_expenses'] += $debit - $credit;
                }
            }
        }

        return $data;
    }

    /**
     * حساب البيانات بالأساس النقدي (Cash Basis)
     * يحسب الإيرادات والمصروفات فقط عند التحصيل/الدفع النقدي الفعلي
     * أي عندما تمر العملية عبر الخزينة أو البنك
     */
    private function calculateCashBasisData($entries, $cashAccountIds, $salesAccountIds,
        $salesReturnsAccountIds, $purchasesAccountIds, $clientsAccountIds, $costOfSalesAccountIds,
        $adminExpensesAccountIds, $otherExpensesAccountIds, $otherRevenueAccountIds,
        $salariesExpensesAccountIds = [], $depreciationExpensesAccountIds = [],
        $manufacturingExpensesAccountIds = [], $marketingExpensesAccountIds = [],
        $transportExpensesAccountIds = [], $financeExpensesAccountIds = [])
    {
        $data = [
            // المقبوضات النقدية
            'cash_sales' => 0,           // مبيعات نقدية مباشرة
            'client_collections' => 0,    // تحصيلات من العملاء
            'other_cash_receipts' => 0,  // مقبوضات نقدية أخرى
            // المدفوعات النقدية
            'cash_refunds' => 0,         // مردودات مبيعات نقدية
            'cash_purchases' => 0,       // مشتريات نقدية
            'cash_expenses' => 0,        // مصروفات نقدية (إجمالي)
            // تفصيل المصروفات النقدية
            'salaries_expenses' => 0,    // الرواتب والأجور
            'depreciation_expenses' => 0, // مصروفات الإهلاك (نادراً ما تكون نقدية)
            'manufacturing_expenses' => 0, // مصروفات التصنيع
            'marketing_expenses' => 0,   // مصروفات التسويق
            'transport_expenses' => 0,   // مصروفات النقل
            'finance_expenses' => 0,     // مصروفات التمويل
            'admin_expenses' => 0,       // مصروفات إدارية
            'other_expenses' => 0,       // مصروفات أخرى
        ];

        // جمع كل حسابات المصروفات في مصفوفة واحدة للتحقق السريع
        $allExpenseAccountIds = array_merge(
            $adminExpensesAccountIds,
            $otherExpensesAccountIds,
            $salariesExpensesAccountIds,
            $depreciationExpensesAccountIds,
            $manufacturingExpensesAccountIds,
            $marketingExpensesAccountIds,
            $transportExpensesAccountIds,
            $financeExpensesAccountIds
        );

        foreach ($entries as $entry) {
            // نتحقق أن القيد يتضمن حركة نقدية (خزينة أو بنك)
            $hasCashMovement = false;
            $cashDebit = 0;  // مدين = دخول نقد
            $cashCredit = 0; // دائن = خروج نقد

            foreach ($entry->details as $detail) {
                if (! $detail->account) {
                    continue;
                }
                if (in_array($detail->account->id, $cashAccountIds)) {
                    $hasCashMovement = true;
                    $cashDebit += $detail->debit ?? 0;
                    $cashCredit += $detail->credit ?? 0;
                }
            }

            // إذا لم يكن هناك حركة نقدية، نتجاهل القيد
            if (! $hasCashMovement) {
                continue;
            }

            // نحلل الطرف الآخر من القيد
            foreach ($entry->details as $detail) {
                if (! $detail->account) {
                    continue;
                }

                $accountId = $detail->account->id;

                // نتجاهل حسابات النقد نفسها
                if (in_array($accountId, $cashAccountIds)) {
                    continue;
                }

                $debit = $detail->debit ?? 0;
                $credit = $detail->credit ?? 0;

                // المبيعات النقدية (من حساب المبيعات مع دخول نقد)
                if (in_array($accountId, $salesAccountIds)) {
                    if ($cashDebit > 0) {
                        $data['cash_sales'] += $credit;
                    }
                }

                // تحصيل من العملاء (من حساب العملاء مع دخول نقد)
                if (in_array($accountId, $clientsAccountIds)) {
                    if ($cashDebit > 0) {
                        $data['client_collections'] += $credit;
                    }
                }

                // إيرادات أخرى نقدية
                if (in_array($accountId, $otherRevenueAccountIds)) {
                    if ($cashDebit > 0) {
                        $data['other_cash_receipts'] += $credit;
                    }
                }

                // المردودات النقدية (رد مبالغ للعملاء)
                if (in_array($accountId, $salesReturnsAccountIds)) {
                    if ($cashCredit > 0) {
                        $data['cash_refunds'] += $debit;
                    }
                }

                // المشتريات النقدية
                if (in_array($accountId, $purchasesAccountIds)) {
                    if ($cashCredit > 0) {
                        $data['cash_purchases'] += $debit;
                    }
                }

                // تكلفة المبيعات النقدية
                if (in_array($accountId, $costOfSalesAccountIds)) {
                    if ($cashCredit > 0) {
                        $data['cash_purchases'] += $debit;
                    }
                }

                // المصروفات النقدية - مع التفصيل حسب النوع
                if ($cashCredit > 0) {
                    // الرواتب والأجور
                    if (in_array($accountId, $salariesExpensesAccountIds)) {
                        $data['salaries_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                    // مصروفات الإهلاك
                    elseif (in_array($accountId, $depreciationExpensesAccountIds)) {
                        $data['depreciation_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                    // مصروفات التصنيع
                    elseif (in_array($accountId, $manufacturingExpensesAccountIds)) {
                        $data['manufacturing_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                    // مصروفات التسويق
                    elseif (in_array($accountId, $marketingExpensesAccountIds)) {
                        $data['marketing_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                    // مصروفات النقل
                    elseif (in_array($accountId, $transportExpensesAccountIds)) {
                        $data['transport_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                    // مصروفات التمويل
                    elseif (in_array($accountId, $financeExpensesAccountIds)) {
                        $data['finance_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                    // المصروفات الإدارية
                    elseif (in_array($accountId, $adminExpensesAccountIds)) {
                        $data['admin_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                    // مصروفات أخرى
                    elseif (in_array($accountId, $otherExpensesAccountIds)) {
                        $data['other_expenses'] += $debit;
                        $data['cash_expenses'] += $debit;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * تجميع الحسابات لتقرير الربح والخسارة
     */
    private function collectAccountsForProfitLoss($entries, $reportType, $cashAccountIds)
    {
        $salesAccounts = collect();
        $salesReturnsAccounts = collect();
        $costOfSalesAccounts = collect();
        $adminExpenseAccounts = collect();
        $otherExpenseAccounts = collect();
        $otherRevenueAccounts = collect();

        // معرفات الحسابات الأب لاستبعادها
        $parentAccountIds = Account::whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->flip();

        foreach ($entries as $entry) {
            // للتقرير النقدي، نتحقق من وجود حركة نقدية
            if ($reportType === 'cash') {
                $hasCashMovement = false;
                foreach ($entry->details as $detail) {
                    if ($detail->account && in_array($detail->account->id, $cashAccountIds)) {
                        $hasCashMovement = true;
                        break;
                    }
                }
                if (! $hasCashMovement) {
                    continue;
                }
            }

            foreach ($entry->details as $detail) {
                if (! $detail->account) {
                    continue;
                }

                $account = $detail->account;

                // استبعاد الحسابات الأب
                if (isset($parentAccountIds[$account->id])) {
                    continue;
                }

                $rootId = $this->getRootAccountId($account);
                $debit = $detail->debit ?? 0;
                $credit = $detail->credit ?? 0;

                // الإيرادات (الجذر 4)
                if ($rootId === AccountingSettings::id('revenue', SystemAccountIds::REVENUE)) {
                    // التحقق من نوع الإيراد
                    if ($this->isDescendantOf($account, AccountingSettings::id('sales_revenue_parent', SystemAccountIds::SALES_REVENUE_PARENT)) ||
                        $this->isDescendantOf($account, AccountingSettings::id('sales', SystemAccountIds::SALES))) {
                        // مردودات المبيعات
                        if ($this->isDescendantOf($account, AccountingSettings::id('sales_returns', SystemAccountIds::SALES_RETURNS))) {
                            $balance = $debit - $credit;
                            $this->updateHierCollection($salesReturnsAccounts, $account, abs($balance));
                        } else {
                            // المبيعات
                            $balance = $credit - $debit;
                            $this->updateHierCollection($salesAccounts, $account, $balance);
                        }
                    } else {
                        // إيرادات أخرى
                        $balance = $credit - $debit;
                        $this->updateHierCollection($otherRevenueAccounts, $account, $balance);
                    }

                    continue;
                }

                // المصروفات (الجذر 5)
                if ($rootId === AccountingSettings::id('expenses', SystemAccountIds::EXPENSES)) {
                    $balance = $debit - $credit;

                    // تكلفة المبيعات
                    if ($this->isDescendantOf($account, AccountingSettings::id('cost_of_sales_root', SystemAccountIds::COST_OF_SALES_ROOT))) {
                        $this->updateHierCollection($costOfSalesAccounts, $account, $balance);
                    }
                    // المصروفات الإدارية
                    elseif ($this->isDescendantOf($account, AccountingSettings::id('admin_expenses_parent', SystemAccountIds::ADMIN_EXPENSES_PARENT))) {
                        $this->updateHierCollection($adminExpenseAccounts, $account, $balance);
                    }
                    // مصروفات أخرى
                    else {
                        $this->updateHierCollection($otherExpenseAccounts, $account, $balance);
                    }
                }
            }
        }

        // ترتيب الحسابات حسب الكود
        $salesAccounts = $salesAccounts->sortBy('code')->values();
        $salesReturnsAccounts = $salesReturnsAccounts->sortBy('code')->values();
        $costOfSalesAccounts = $costOfSalesAccounts->sortBy('code')->values();
        $adminExpenseAccounts = $adminExpenseAccounts->sortBy('code')->values();
        $otherExpenseAccounts = $otherExpenseAccounts->sortBy('code')->values();
        $otherRevenueAccounts = $otherRevenueAccounts->sortBy('code')->values();

        // حساب الإجماليات
        $totalSales = $salesAccounts->sum('balance');
        $totalSalesReturns = $salesReturnsAccounts->sum('balance');
        $netSales = $totalSales - $totalSalesReturns;
        $totalCostOfSales = $costOfSalesAccounts->sum('balance');
        $grossProfit = $netSales - $totalCostOfSales;
        $totalAdminExpenses = $adminExpenseAccounts->sum('balance');
        $operatingProfit = $grossProfit - $totalAdminExpenses;
        $totalOtherRevenue = $otherRevenueAccounts->sum('balance');
        $totalOtherExpenses = $otherExpenseAccounts->sum('balance');
        $netProfit = $operatingProfit + $totalOtherRevenue - $totalOtherExpenses;

        $grossProfitMargin = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;
        $operatingProfitMargin = $netSales > 0 ? ($operatingProfit / $netSales) * 100 : 0;
        $netProfitMargin = $netSales > 0 ? ($netProfit / $netSales) * 100 : 0;

        return [
            'salesAccounts' => $salesAccounts->toArray(),
            'salesReturnsAccounts' => $salesReturnsAccounts->toArray(),
            'costOfSalesAccounts' => $costOfSalesAccounts->toArray(),
            'adminExpenseAccounts' => $adminExpenseAccounts->toArray(),
            'otherExpenseAccounts' => $otherExpenseAccounts->toArray(),
            'otherRevenueAccounts' => $otherRevenueAccounts->toArray(),
            'totals' => [
                'total_sales' => round($totalSales, 2),
                'total_sales_returns' => round($totalSalesReturns, 2),
                'net_sales' => round($netSales, 2),
                'total_cost_of_sales' => round($totalCostOfSales, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_profit_margin' => round($grossProfitMargin, 2),
                'total_admin_expenses' => round($totalAdminExpenses, 2),
                'operating_profit' => round($operatingProfit, 2),
                'operating_profit_margin' => round($operatingProfitMargin, 2),
                'total_other_revenue' => round($totalOtherRevenue, 2),
                'total_other_expenses' => round($totalOtherExpenses, 2),
                'net_profit' => round($netProfit, 2),
                'net_profit_margin' => round($netProfitMargin, 2),
            ],
        ];
    }

    /**
     * إرجاع قيم افتراضية فارغة للإجماليات
     */
    private function getEmptyTotals()
    {
        return [
            'total_sales' => 0,
            'total_sales_returns' => 0,
            'net_sales' => 0,
            'total_cost_of_sales' => 0,
            'gross_profit' => 0,
            'gross_profit_margin' => 0,
            'total_admin_expenses' => 0,
            'total_salaries_expenses' => 0,
            'total_depreciation_expenses' => 0,
            'total_manufacturing_expenses' => 0,
            'total_marketing_expenses' => 0,
            'total_transport_expenses' => 0,
            'total_finance_expenses' => 0,
            'total_operating_expenses' => 0,
            'operating_profit' => 0,
            'operating_profit_margin' => 0,
            'total_other_revenue' => 0,
            'total_other_expenses' => 0,
            'net_profit' => 0,
            'net_profit_margin' => 0,
            'total_revenues' => 0,
            'total_expenses_all' => 0,
        ];
    }

    public function journalEntriesByEmployee(Request $request)
    {
        // 1. الحصول على البيانات الأساسية للقوائم المنسدلة
        $employees = User::whereNotNull('employee_id')->get();
        $branches = Branch::orderBy('name')->get();
        $accounts = Account::orderBy('code')->get(['id', 'code', 'name']);
        $clients = Client::orderBy('trade_name')->get();
        $costCenters = CostCenter::orderBy('name')->get();

        // 2. تحديد التواريخ الافتراضية
        $fromDate = $request->input('date_from') ? Carbon::parse($request->input('date_from')) : $this->fiscalYearStart();
        $toDate = $request->input('date_to') ? Carbon::parse($request->input('date_to')) : now();

        // 3. تحديد نوع العرض (ملخص أو تفاصيل)
        $isSummary = $request->filled('is_summary') && $request->is_summary == '1';

        // 4. بناء الاستعلام الأساسي
        $query = JournalEntry::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1); // فقط القيود المعتمدة

        // 5. تطبيق الفلاتر
        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('account_id')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->where('account_id', $request->account_id);
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('employee_id')) {
            $query->where('created_by_employee', $request->employee_id);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // 6-7. الإجماليات والملخّص بالتجميع، والتفاصيل لقيود الصفحة — كتقرير القيود العام
        [$reportData, $totals, $journalPage] = $this->journalReport($query, $request, $isSummary);

        // 8. أنواع المراجع المتاحة
        $referenceTypes = ReferenceType::getAllWithDetails();

        // 9. إرجاع العرض
        return view('account::report.daily_restrictions_reports.report_journal', compact(
            'employees',
            'branches',
            'accounts',
            'clients',
            'costCenters',
            'totals',
            'fromDate',
            'toDate',
            'reportData',
            'isSummary',
            'referenceTypes',
            'journalPage'
        ));
    }

    /**
     * الحصول على بيانات تقرير القيود حسب الموظف عبر AJAX
     */
    public function journalEntriesByEmployeeAjax(Request $request)
    {
        try {
            // 1. التحقق من صحة البيانات المدخلة
            $validatedData = $request->validate([
                'employee' => 'nullable|exists:users,id',
                'client' => 'nullable|exists:clients,id',
                'branch' => 'nullable|exists:branches,id',
                'account' => 'nullable|exists:accounts,id',
                'cost_center' => 'nullable|exists:cost_centers,id',
                'status' => 'nullable|in:0,1,2', // 0=معلق, 1=معتمد, 2=مرفوض
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
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

            // 3. إنشاء استعلام القيود مع العلاقات
            $journalEntriesQuery = JournalEntry::with([
                'createdByEmployee',
                'approvedByEmployee',
                'branch',
                'client',
                'details.account',
                'details.costCenter',
            ])
                ->whereBetween('date', [$fromDate, $toDate])
                ->whereNotNull('created_by_employee');

            // 4. تطبيق الفلاتر على القيود
            if ($request->filled('employee')) {
                $journalEntriesQuery->where('created_by_employee', $request->employee);
            }

            if ($request->filled('client')) {
                $journalEntriesQuery->where('client_id', $request->client);
            }

            if ($request->filled('branch')) {
                $journalEntriesQuery->where('branch_id', $request->branch);
            }

            if ($request->filled('cost_center')) {
                // مركز التكلفة يسكن سطور القيد منذ هجرة
                // move_cost_center_to_journal_entry_details — والشرط على
                // رأس القيد كان يرمي «عمود مجهول» عند أول اختيار
                $journalEntriesQuery->whereHas('details', function ($q) use ($request) {
                    $q->where('cost_center_id', $request->cost_center);
                });
            }

            if ($request->filled('status')) {
                $journalEntriesQuery->where('status', $request->status);
            }

            // فلتر الحساب من خلال تفاصيل القيد
            if ($request->filled('account')) {
                $journalEntriesQuery->whereHas('details', function ($query) use ($request) {
                    $query->where('account_id', $request->account);
                });
            }

            // 5. جلب البيانات مع ترتيبها
            $journalEntries = $journalEntriesQuery->orderBy('date', 'desc')->get();

            // 6. معالجة البيانات وتجميعها حسب الموظف
            $groupedData = collect();

            if ($journalEntries->isNotEmpty()) {
                $groupedEntries = $journalEntries->groupBy(function ($entry) {
                    return $entry->createdByEmployee ? $entry->createdByEmployee->id : 'unknown';
                });

                foreach ($groupedEntries as $employeeId => $employeeEntries) {
                    if ($employeeId !== 'unknown' && $employeeEntries->isNotEmpty()) {
                        $employee = $employeeEntries->first()->createdByEmployee;

                        // حساب إجماليات المدين والدائن
                        $totalDebits = 0;
                        $totalCredits = 0;

                        foreach ($employeeEntries as $entry) {
                            $totalDebits += $entry->details->sum('debit');
                            $totalCredits += $entry->details->sum('credit');
                        }

                        $groupedData->put($employeeId, [
                            'employee' => $employee,
                            'entries' => $employeeEntries,
                            'entries_count' => $employeeEntries->count(),
                            'total_debits' => $totalDebits,
                            'total_credits' => $totalCredits,
                            'balance' => $totalDebits - $totalCredits,
                        ]);
                    }
                }
            }

            // 7. حساب الإجماليات العامة
            $uniqueEmployees = $journalEntries
                ->filter(function ($entry) {
                    return $entry->createdByEmployee;
                })
                ->map(function ($entry) {
                    return $entry->createdByEmployee->id;
                })
                ->unique()
                ->count();

            $totalDebits = 0;
            $totalCredits = 0;
            $entriesCount = $journalEntries->count();

            foreach ($journalEntries as $entry) {
                $totalDebits += $entry->details->sum('debit');
                $totalCredits += $entry->details->sum('credit');
            }

            $totals = [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'total_entries' => $totalDebits, // أو يمكن استخدام المدين كإجمالي
                'total_count' => $entriesCount,
                'average_amount' => $entriesCount > 0 ? $totalDebits / $entriesCount : 0,
                'employees_count' => $uniqueEmployees,
            ];

            // 8. إعداد بيانات الرسم البياني (أفضل 10 موظفين)
            $topEmployees = $groupedData
                ->sortByDesc('total_debits')
                ->take(10);

            $chartData = [
                'labels' => $topEmployees
                    ->map(function ($data) {
                        return $data['employee']->name ?? 'غير محدد';
                    })
                    ->values()
                    ->toArray(),
                'debits' => $topEmployees
                    ->map(function ($data) {
                        return $data['total_debits'];
                    })
                    ->values()
                    ->toArray(),
                'credits' => $topEmployees
                    ->map(function ($data) {
                        return $data['total_credits'];
                    })
                    ->values()
                    ->toArray(),
            ];

            // 9. تحويل البيانات للإرسال عبر JSON
            $groupedDataArray = [];
            foreach ($groupedData as $employeeId => $data) {
                $groupedDataArray[$employeeId] = [
                    'employee' => [
                        'id' => $data['employee']->id,
                        'name' => $data['employee']->name,
                        'code' => $data['employee']->code ?? '',
                    ],
                    'entries' => $data['entries']
                        ->map(function ($entry) {
                            return [
                                'id' => $entry->id,
                                'reference_number' => $entry->reference_number,
                                'date' => $entry->date,
                                'description' => $entry->description,
                                'status' => $entry->status,
                                'status_text' => $entry->getStatusTextAttribute(),
                                'total_debits' => $entry->details->sum('debit'),
                                'total_credits' => $entry->details->sum('credit'),
                                'is_balanced' => $entry->isBalanced(),
                                'employee' => $entry->createdByEmployee
                                    ? [
                                        'id' => $entry->createdByEmployee->id,
                                        'name' => $entry->createdByEmployee->name,
                                    ]
                                    : null,
                                'client' => $entry->client
                                    ? [
                                        'id' => $entry->client->id,
                                        'name' => $entry->client->trade_name,
                                    ]
                                    : null,
                                'branch' => $entry->branch
                                    ? [
                                        'id' => $entry->branch->id,
                                        'name' => $entry->branch->name,
                                    ]
                                    : null,
                                'cost_centers' => $entry->details->pluck('costCenter')->filter()->unique('id')->map(function ($cc) {
                                    return $cc ? ['id' => $cc->id, 'name' => $cc->name] : null;
                                })->filter()->values()->toArray(),
                                'details' => $entry->details->map(function ($detail) {
                                    return [
                                        'id' => $detail->id,
                                        'account_id' => $detail->account_id,
                                        'account_code' => $detail->account ? $detail->account->code : '',
                                        'account_name' => $detail->account ? $detail->account->name : 'غير محدد',
                                        'description' => $detail->description,
                                        'debit' => $detail->debit,
                                        'credit' => $detail->credit,
                                        'reference' => $detail->reference,
                                        'cost_center' => $detail->costCenter ? $detail->costCenter->name : null,
                                    ];
                                })->toArray(),
                                // بيانات المصدر والارتباط
                                'reference_type' => $entry->reference_type ? ($entry->reference_type->value ?? $entry->reference_type) : null,
                                'reference_type_name' => $entry->reference_type_name,
                                'source_url' => $this->getJournalEntrySourceUrl($entry),
                            ];
                        })
                        ->toArray(),
                    'entries_count' => $data['entries_count'],
                    'total_debits' => $data['total_debits'],
                    'total_credits' => $data['total_credits'],
                    'balance' => $data['balance'],
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

        } catch (ValidationException $e) {
            // مُدخلٌ مرفوض ردُّه 422 برسالة الحقل، لا 500 يُسجَّل عطلاً
            throw $e;
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'journalEntriesByEmployeeAjax');

            Log::error('خطأ في تقرير القيود حسب الموظف: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function calculateAccountBalanceDetails($account, $isDebitAccount, $openingBalance, $periodDebit, $periodCredit)
    {
        // تحديد نوع الحساب بشكل أكثر دقة
        $accountType = $account->type;

        // حساب الرصيد الافتتاحي بشكل مفصل
        $openingBalanceDebit = 0;
        $openingBalanceCredit = 0;

        // تحديد اتجاه الحساب بناءً على نوع الحساب
        $isDebitNormalBalance = in_array($accountType, [
            'asset', 'expense', 'contra_liability', 'contra_equity',
        ]);

        // معالجة الرصيد الافتتاحي
        if ($isDebitNormalBalance) {
            // للحسابات المدينة الطبيعية (الأصول، المصروفات)
            if ($openingBalance > 0) {
                $openingBalanceDebit = abs($openingBalance);
            } else {
                $openingBalanceCredit = abs($openingBalance);
            }
        } else {
            // للحسابات الدائنة الطبيعية (الالتزامات، الإيرادات، حقوق الملكية)
            if ($openingBalance > 0) {
                $openingBalanceCredit = abs($openingBalance);
            } else {
                $openingBalanceDebit = abs($openingBalance);
            }
        }

        // حساب الرصيد الختامي
        $closingBalance = $openingBalance + $periodDebit - $periodCredit;
        $closingBalanceDebit = 0;
        $closingBalanceCredit = 0;

        if ($isDebitNormalBalance) {
            // للحسابات المدينة الطبيعية
            if ($closingBalance > 0) {
                $closingBalanceDebit = abs($closingBalance);
            } else {
                $closingBalanceCredit = abs($closingBalance);
            }
        } else {
            // للحسابات الدائنة الطبيعية
            if ($closingBalance > 0) {
                $closingBalanceCredit = abs($closingBalance);
            } else {
                $closingBalanceDebit = abs($closingBalance);
            }
        }

        // حساب الإجماليات
        $totalDebit = $openingBalanceDebit + $periodDebit;
        $totalCredit = $openingBalanceCredit + $periodCredit;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'code' => $account->code,
            'parent_id' => $account->parent_id,
            'account_type' => $accountType,
            'account_category' => $account->category,
            'opening_balance_debit' => $openingBalanceDebit,
            'opening_balance_credit' => $openingBalanceCredit,
            'period_debit' => $periodDebit,
            'period_credit' => $periodCredit,
            'closing_balance_debit' => $closingBalanceDebit,
            'closing_balance_credit' => $closingBalanceCredit,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }

    /**
     * الحصول على رابط المصدر لقيد اليومية
     * Get source URL for journal entry based on reference type
     */
    private function getJournalEntrySourceUrl($entry)
    {
        if (! $entry->reference_type) {
            return null;
        }

        $refType = $entry->reference_type instanceof ReferenceType
            ? $entry->reference_type->value
            : $entry->reference_type;

        try {
            switch ($refType) {
                case 'JV': // قيد يومية عام
                    return route('journal.show', $entry->id);

                case 'PV': // سند صرف
                    if ($entry->payment && $entry->payment->id) {
                        return route('payment_vouchers.show', $entry->payment->id);
                    }

                    return route('journal.show', $entry->id);

                case 'RV': // سند قبض
                    if ($entry->payment && $entry->payment->id) {
                        return route('receipt_vouchers.show', $entry->payment->id);
                    }

                    return route('journal.show', $entry->id);

                case 'PI': // فاتورة مشتريات
                    if ($entry->purchase_invoice_id) {
                        return route('purchases.show', $entry->purchase_invoice_id);
                    }

                    return route('journal.show', $entry->id);

                case 'PR': // مردودات مشتريات
                    if ($entry->purchase_invoice_id) {
                        return route('purchase_returns.show', $entry->purchase_invoice_id);
                    }

                    return route('journal.show', $entry->id);

                case 'SI': // فاتورة مبيعات
                    if ($entry->invoice_id) {
                        return route('invoices.show', $entry->invoice_id);
                    }

                    return route('journal.show', $entry->id);

                case 'SR': // مردودات مبيعات
                    if ($entry->invoice_id) {
                        return route('sales_returns.show', $entry->invoice_id);
                    }

                    return route('journal.show', $entry->id);

                case 'EXP': // مصروفات
                    return route('journal.show', $entry->id);

                case 'SAL': // مسير رواتب
                    if ($entry->salary_id) {
                        return route('salaries.show', $entry->salary_id);
                    }

                    return route('journal.show', $entry->id);

                case 'OPB': // قيد افتتاحي
                case 'DN': // إشعار مدين
                case 'CN': // إشعار دائن
                case 'ADV': // سلفة/عهدة
                case 'STK': // تسوية مخزون
                case 'TRF': // تحويل داخلي
                case 'DEP': // إهلاك أصول
                case 'ACC': // تسوية قيود محاسبية
                case 'AW': // مخزن معتمد
                default:
                    return route('journal.show', $entry->id);
            }
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.GeneralAccounts', 'getJournalEntrySourceUrl');

            // في حالة عدم وجود الراوت، نرجع رابط القيد الافتراضي
            try {
                return route('journal.show', $entry->id);
            } catch (\Exception $ex) {
                // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
                OperationLog::failure($ex, 'Account.GeneralAccounts', 'getJournalEntrySourceUrl');

                return null;
            }
        }
    }

    private function getDateRange($dateRange)
    {
        // دالة لتحويل الفترة المحددة إلى نطاق تاريخي
        switch ($dateRange) {
            case 'الأسبوع الماضي':
                return [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()];
            case 'الشهر الأخير':
                return [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];
            case 'من أول الشهر حتى اليوم':
                return [now()->startOfMonth(), now()];
            case 'السنة الماضية':
                return [$this->fiscalYearStart(now()->subYear()), $this->fiscalYearEnd(now()->subYear())];
            case 'من أول السنة حتى اليوم':
                return [$this->fiscalYearStart(), now()];
            default:
                return [now()->subMonth(), now()];
        }
    }

    /**
     * عرض صفحة الحركات المالية
     * Financial Transactions Report
     */
    public function financialTransactions(Request $request)
    {
        // جلب البيانات الأساسية للفلاتر
        $branches = Branch::all();
        $accounts = Account::whereNotNull('parent_id')->orderBy('code')->get();
        $clients = Client::orderBy('trade_name')->get();
        $employees = Employee::orderBy('first_name')->get();
        $costCenters = CostCenter::orderBy('name')->get();

        // تحديد الفترة الزمنية
        $fromDate = $request->from_date ?? now()->startOfMonth()->format('Y-m-d');
        $toDate = $request->to_date ?? now()->format('Y-m-d');
        $branchFilter = $request->branch;
        $accountFilter = $request->account;
        $clientFilter = $request->client;
        $employeeFilter = $request->employee;
        $costCenterFilter = $request->cost_center;
        $referenceTypeFilter = $request->reference_type;
        $statusFilter = $request->status;

        /*
            فلترُ «ضريبي»: ثلاث حالات لا اثنتان — الكلّ، والضريبيّ، وغير
            الضريبيّ. ولو كان خانةَ تأشيرٍ لتعذّر طلبُ «غير الضريبية» وحدَها،
            وهي ما يُراجَع عند تسوية الإقرار.
        */
        $taxableFilter = $request->input('taxable');

        // بناء الاستعلام الأساسي
        $query = JournalEntry::with(['details.account', 'details.costCenter', 'client', 'employee', 'branch', 'invoice', 'purchaseInvoice', 'createdByEmployee'])
            ->where('status', 1) // القيود المعتمدة فقط
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        // تطبيق الفلاتر
        if (! empty($fromDate) && ! empty($toDate)) {
            $query->whereBetween('date', [$fromDate, $toDate]);
        }

        if (! empty($branchFilter)) {
            $query->where('branch_id', $branchFilter);
        }

        if (! empty($accountFilter)) {
            $query->whereHas('details', function ($q) use ($accountFilter) {
                $q->where('account_id', $accountFilter);
            });
        }

        if (! empty($clientFilter)) {
            $query->where('client_id', $clientFilter);
        }

        if (! empty($employeeFilter)) {
            $query->where('employee_id', $employeeFilter);
        }

        if (! empty($costCenterFilter)) {
            $query->whereHas('details', function ($q) use ($costCenterFilter) {
                $q->where('cost_center_id', $costCenterFilter);
            });
        }

        if (! empty($referenceTypeFilter)) {
            $query->where('reference_type', $referenceTypeFilter);
        }

        if ($taxableFilter !== null && $taxableFilter !== '') {
            $query->where('is_taxable', $taxableFilter === '1');
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        // جلب البيانات مع التقسيم
        $journalEntries = $query->paginate(50)->appends($request->except('page'));

        // حساب الإحصائيات
        $statisticsQuery = JournalEntry::where('status', 1);

        if (! empty($fromDate) && ! empty($toDate)) {
            $statisticsQuery->whereBetween('date', [$fromDate, $toDate]);
        }
        if (! empty($branchFilter)) {
            $statisticsQuery->where('branch_id', $branchFilter);
        }

        $totalEntries = $statisticsQuery->count();

        // حساب إجمالي المدين والدائن
        $totalsQuery = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($fromDate, $toDate, $branchFilter) {
            $q->where('status', 1);
            if (! empty($fromDate) && ! empty($toDate)) {
                $q->whereBetween('date', [$fromDate, $toDate]);
            }
            if (! empty($branchFilter)) {
                $q->where('branch_id', $branchFilter);
            }
        });

        $totalDebit = $totalsQuery->sum('debit');
        $totalCredit = (clone $totalsQuery)->sum('credit');

        $openingBalance = 0;
        $closingBalance = 0;

        // معرف حساب الأرصدة الافتتاحية (الحساب المقابل) - يجب استبعاده من الحسابات
        $openingBalanceAccountId = AccountingSettings::id('opening_balance', SystemAccountIds::OPENING_BALANCE); // 38

        // إذا تم اختيار حساب معين، نحسب رصيده فقط
        if (! empty($accountFilter)) {
            $selectedAccount = Account::find($accountFilter);

            if ($selectedAccount) {
                // حساب رصيد أول المدة لهذا الحساب (جميع القيود قبل تاريخ البداية)
                $openingBalanceQuery = JournalEntryDetail::where('account_id', $accountFilter)
                    ->whereHas('journalEntry', function ($q) use ($fromDate, $branchFilter) {
                        $q->where('status', 1);
                        if (! empty($fromDate)) {
                            $q->where('date', '<', $fromDate);
                        }
                        if (! empty($branchFilter)) {
                            $q->where('branch_id', $branchFilter);
                        }
                    });

                $openingDebit = $openingBalanceQuery->sum('debit');
                $openingCredit = (clone $openingBalanceQuery)->sum('credit');

                // تطبيق المعادلة المحاسبية بناءً على طبيعة الحساب
                if ($selectedAccount->balance_type === Account::DEBIT) {
                    // حسابات مدينة بطبيعتها (أصول، مصروفات)
                    $openingBalance = $openingDebit - $openingCredit;
                } else {
                    // حسابات دائنة بطبيعتها (خصوم، إيرادات، حقوق ملكية)
                    $openingBalance = $openingCredit - $openingDebit;
                }

                // حساب رصيد آخر المدة لهذا الحساب (جميع القيود حتى تاريخ النهاية)
                $closingBalanceQuery = JournalEntryDetail::where('account_id', $accountFilter)
                    ->whereHas('journalEntry', function ($q) use ($toDate, $branchFilter) {
                        $q->where('status', 1);
                        if (! empty($toDate)) {
                            $q->where('date', '<=', $toDate);
                        }
                        if (! empty($branchFilter)) {
                            $q->where('branch_id', $branchFilter);
                        }
                    });

                $closingDebit = $closingBalanceQuery->sum('debit');
                $closingCredit = (clone $closingBalanceQuery)->sum('credit');

                if ($selectedAccount->balance_type === Account::DEBIT) {
                    $closingBalance = $closingDebit - $closingCredit;
                } else {
                    $closingBalance = $closingCredit - $closingDebit;
                }
            }
        } else {
            // إذا لم يتم اختيار حساب معين، نحسب الرصيد الإجمالي لجميع الحسابات
            // مع مراعاة طبيعة كل حساب واستبعاد حساب الأرصدة الافتتاحية

            // جلب جميع الحسابات مع طبيعتها (باستثناء حساب الأرصدة الافتتاحية)
            $accountsWithBalanceType = Account::whereNotNull('balance_type')
                ->where('id', '!=', $openingBalanceAccountId) // استبعاد حساب الأرصدة الافتتاحية
                ->select('id', 'balance_type')
                ->get()
                ->keyBy('id');

            /*
                مجاميعُ كلّ حسابٍ حتى تاريخ — بربطٍ مباشر بالقيود لا بـ`whereHas`:
                الـ`EXISTS` المرتبط يُقيَّم سطراً سطراً على جدول السطور كلِّه
                (~0.7 ث لكلٍّ من الاستعلامين).
            */
            $balancesUpTo = function (string $operator, $date) use ($openingBalanceAccountId, $branchFilter) {
                $lines = DB::table('journal_entry_details')
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
                    ->where('journal_entry_details.account_id', '!=', $openingBalanceAccountId) // استبعاد حساب الأرصدة الافتتاحية
                    ->where('journal_entries.status', 1)
                    ->select('journal_entry_details.account_id', DB::raw('SUM(journal_entry_details.debit) as total_debit'), DB::raw('SUM(journal_entry_details.credit) as total_credit'))
                    ->groupBy('journal_entry_details.account_id');

                if (! empty($date)) {
                    $lines->where('journal_entries.date', $operator, $date);
                }
                if (! empty($branchFilter)) {
                    $lines->where('journal_entries.branch_id', $branchFilter);
                }

                return $lines->get();
            };

            // حساب رصيد أول المدة (جميع القيود قبل تاريخ البداية)
            $openingEntries = $balancesUpTo('<', $fromDate);

            foreach ($openingEntries as $entry) {
                $accountInfo = $accountsWithBalanceType->get($entry->account_id);
                if ($accountInfo) {
                    if ($accountInfo->balance_type === Account::DEBIT) {
                        // حسابات مدينة: الأصول والمصروفات
                        $openingBalance += ($entry->total_debit - $entry->total_credit);
                    } else {
                        // حسابات دائنة: الخصوم والإيرادات وحقوق الملكية
                        $openingBalance += ($entry->total_credit - $entry->total_debit);
                    }
                } else {
                    // إذا لم يكن للحساب طبيعة محددة، نعتبره مدين افتراضياً
                    $openingBalance += ($entry->total_debit - $entry->total_credit);
                }
            }

            // حساب رصيد آخر المدة (جميع القيود حتى تاريخ النهاية)
            $closingEntries = $balancesUpTo('<=', $toDate);

            foreach ($closingEntries as $entry) {
                $accountInfo = $accountsWithBalanceType->get($entry->account_id);
                if ($accountInfo) {
                    if ($accountInfo->balance_type === Account::DEBIT) {
                        $closingBalance += ($entry->total_debit - $entry->total_credit);
                    } else {
                        $closingBalance += ($entry->total_credit - $entry->total_debit);
                    }
                } else {
                    $closingBalance += ($entry->total_debit - $entry->total_credit);
                }
            }
        }

        // حساب الإيرادات والمصروفات
        // حسابات الإيرادات (المبيعات وأبناؤها - كود يبدأ بـ 4)
        $revenueAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('sales', SystemAccountIds::SALES)); // 44
        $otherRevenueIds = Account::where('code', 'like', '4%')->pluck('id')->toArray();
        $allRevenueAccountIds = array_unique(array_merge($revenueAccountIds, $otherRevenueIds));

        // حسابات المصروفات (كود يبدأ بـ 5)
        $expenseAccountIds = $this->getExpenseAccountIds();

        // حساب إجمالي الإيرادات (الدائن من حسابات الإيرادات)
        $incomeQuery = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($fromDate, $toDate, $branchFilter) {
            $q->where('status', 1);
            if (! empty($fromDate) && ! empty($toDate)) {
                $q->whereBetween('date', [$fromDate, $toDate]);
            }
            if (! empty($branchFilter)) {
                $q->where('branch_id', $branchFilter);
            }
        })->whereIn('account_id', $allRevenueAccountIds);

        $incomeCredit = (clone $incomeQuery)->sum('credit');
        $incomeDebit = (clone $incomeQuery)->sum('debit');
        $totalIncome = $incomeCredit - $incomeDebit; // الإيرادات = الدائن - المدين
        $incomeCount = (clone $incomeQuery)->distinct('journal_entry_id')->count('journal_entry_id');

        // حساب إجمالي المصروفات (المدين من حسابات المصروفات)
        $expenseQuery = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($fromDate, $toDate, $branchFilter) {
            $q->where('status', 1);
            if (! empty($fromDate) && ! empty($toDate)) {
                $q->whereBetween('date', [$fromDate, $toDate]);
            }
            if (! empty($branchFilter)) {
                $q->where('branch_id', $branchFilter);
            }
        })->whereIn('account_id', $expenseAccountIds);

        $expenseDebit = (clone $expenseQuery)->sum('debit');
        $expenseCredit = (clone $expenseQuery)->sum('credit');
        $totalExpenses = $expenseDebit - $expenseCredit; // المصروفات = المدين - الدائن
        $expensesCount = (clone $expenseQuery)->distinct('journal_entry_id')->count('journal_entry_id');

        // إحصائيات حسب نوع القيد
        $entriesByType = JournalEntry::where('status', 1)
            ->when(! empty($fromDate) && ! empty($toDate), function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('date', [$fromDate, $toDate]);
            })
            ->when(! empty($branchFilter), function ($q) use ($branchFilter) {
                $q->where('branch_id', $branchFilter);
            })
            ->select('reference_type', DB::raw('count(*) as count'), DB::raw('SUM((SELECT SUM(debit) FROM journal_entry_details WHERE journal_entry_id = journal_entries.id)) as total_amount'))
            ->groupBy('reference_type')
            ->get();

        // أنواع القيود المتاحة
        $referenceTypes = ReferenceType::getAllWithDetails();

        // رصيد نهاية المدة محسوب من القيود الفعلية حتى تاريخ النهاية
        $statistics = [
            'total_entries' => $totalEntries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'total_income' => $totalIncome > 0 ? $totalIncome : 0,
            'total_expenses' => $totalExpenses > 0 ? $totalExpenses : 0,
            'income_count' => $incomeCount,
            'expenses_count' => $expensesCount,
            'entries_by_type' => $entriesByType,
        ];

        return view('account::report.accountGeneral.financial_transactions', compact(
            'journalEntries',
            'branches',
            'accounts',
            'clients',
            'employees',
            'costCenters',
            'referenceTypes',
            'statistics',
            'fromDate',
            'toDate',
            'taxableFilter'
        ));
    }

    /**
     * تصدير الحركات المالية إلى Excel
     */
    public function financialTransactionsExport(Request $request)
    {
        // يمكن إضافة منطق التصدير لاحقاً
        return back()->with('info', 'جاري العمل على ميزة التصدير');
    }

    /**
     * تقرير التدفقات النقدية الإجمالية.
     *
     * هذا التقرير ليس Cash Flow Statement محاسبي مستقل، بل هو تجميع وتلخيص
     * لبيانات "التدفقات النقدية التفصيلية" التي مصدرها buildDetailedCashFlowData().
     * يعرض إجماليات الداخل/الخارج/الصافي + تلخيصات حسب نوع الحساب وحسب المجموعة الأم.
     */
    public function cashFlowStatement(Request $request)
    {
        try {
            $branches = Branch::all();

            // الفترةُ علاقةً أوّلاً، والمدى التاريخيّ حين يُطلب صراحةً
            $scope = $this->reportPeriod(
                $request,
                $request->input('date_type', 'custom'),
                $request->input('from_date'),
                $request->input('to_date')
            );
            $reportPeriod = $scope['period'];
            [$fromDate, $toDate] = [$scope['from'], $scope['to']];
            $fromDate = $fromDate ?: $this->fiscalYearStart()->format('Y-m-d');
            $toDate = $toDate ?: now()->format('Y-m-d');

            $filters = [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'branch' => $request->input('branch'),
                'counter_account_id' => $request->input('counter_account_id'),
                'currency' => $request->input('currency'),
            ];

            // المصدر الوحيد: نفس بيانات التدفقات النقدية التفصيلية
            $data = $this->buildDetailedCashFlowData($filters);
            $rows = $data['rows'];
            $totals = $data['totals'];

            // تجميع حسب نوع الحساب (account_type)
            $byType = $rows->groupBy(function ($r) {
                return $r['account_type'] ?: 'غير مصنف';
            })
                ->map(function ($group, $type) {
                    return [
                        'type' => $type,
                        'inflow' => $group->sum('cash_inflow'),
                        'outflow' => $group->sum('cash_outflow'),
                        'net' => $group->sum('cash_inflow') - $group->sum('cash_outflow'),
                        'count' => $group->count(),
                    ];
                })->values();

            // تجميع حسب الحساب الأب (المجموعة)
            $parentIds = $rows->pluck('parent_id')->filter()->unique()->values();
            $parentNames = Account::whereIn('id', $parentIds)->pluck('name', 'id');

            $byCategory = $rows->groupBy('parent_id')
                ->map(function ($group, $parentId) use ($parentNames) {
                    return [
                        'parent_id' => $parentId,
                        'parent_name' => $parentNames[$parentId] ?? 'غير مصنف',
                        'inflow' => $group->sum('cash_inflow'),
                        'outflow' => $group->sum('cash_outflow'),
                        'net' => $group->sum('cash_inflow') - $group->sum('cash_outflow'),
                        'count' => $group->count(),
                    ];
                })
                ->sortByDesc(fn ($i) => $i['inflow'] + $i['outflow'])
                ->values();

            // التوب 5 من الحسابات (الأكثر حركة) - مُختصرة للعرض
            $topAccounts = $rows->take(10);

            // أرصدة البنوك/الخزائن في الفترة (للإطار العام)
            $cashAccountIds = $data['cashAccountIds'];
            $cashOpening = (float) JournalEntryDetail::whereIn('account_id', $cashAccountIds)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $filters) {
                    $q->where('status', 1)->where('date', '<', $fromDate);
                    if (! empty($filters['branch'])) {
                        $q->where('branch_id', $filters['branch']);
                    }
                })
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')->value('net');

            $cashClosing = (float) JournalEntryDetail::whereIn('account_id', $cashAccountIds)
                ->whereHas('journalEntry', function ($q) use ($toDate, $filters) {
                    $q->where('status', 1)->where('date', '<=', $toDate);
                    if (! empty($filters['branch'])) {
                        $q->where('branch_id', $filters['branch']);
                    }
                })
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')->value('net');

            $cashAccountsList = Account::whereIn('id', $cashAccountIds)->orderBy('code')->get(['id', 'code', 'name']);

            return view('account::report.accountGeneral.cash_flow_statement', [
                'totals' => $totals,
                'byType' => $byType,
                'byCategory' => $byCategory,
                'topAccounts' => $topAccounts,
                'cashOpening' => $cashOpening,
                'cashClosing' => $cashClosing,
                'branches' => $branches,
                'cashAccountsList' => $cashAccountsList,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'branchFilter' => $filters['branch'],
                'currentFilters' => $filters,
                'dateType' => $request->input('date_type', 'custom'),
            ]);
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'cashFlowStatement');

            Log::error('Cash Flow Statement Error: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return back()->with('error', 'حدث خطأ في إنشاء التقرير: '.$e->getMessage());
        }
    }

    /**
     * النسخة الأصلية المحاسبية القديمة من cashFlowStatement (operating/investing/financing).
     * محفوظة كـ private مرجع - لم تعد تُستخدم. يمكن حذفها لاحقاً.
     */
    private function _legacyCashFlowStatement(Request $request)
    {
        try {
            $branches = Branch::all();

            $fromDate = $request->from_date ?? $this->fiscalYearStart()->format('Y-m-d');
            $toDate = $request->to_date ?? now()->format('Y-m-d');
            $branchFilter = $request->branch;

            // ========================================
            // تحديد معرفات الحسابات
            // ========================================

            // حسابات النقدية (البنوك + الخزائن وأبناؤهم) - تشمل الثوابت الجديدة 121/122
            $cashAccountIds = $this->getCashFlowAccountIds();

            // حسابات العملاء (المدينون)
            $clientsAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('clients_account', SystemAccountIds::CLIENTS_ACCOUNT)); // 18

            // حسابات الموردين (الدائنون)
            $suppliersAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('suppliers', SystemAccountIds::SUPPLIERS)); // 29

            // حسابات المخزون
            $inventoryAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('inventory', SystemAccountIds::INVENTORY)); // 16

            // حسابات الأصول الثابتة
            $fixedAssetsAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('fixed_assets', SystemAccountIds::FIXED_ASSETS)); // 6 - الأصول الثابتة

            // حسابات الإيرادات
            $revenueAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('revenue', SystemAccountIds::REVENUE)); // 4

            // حسابات المصروفات
            $expenseAccountIds = $this->getExpenseAccountIds(); // 5

            // حسابات حقوق الملكية
            $equityAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('equity', SystemAccountIds::EQUITY)); // 3 - حقوق الملكية

            // حساب الأرصدة الافتتاحية - لاستبعاده
            $openingBalanceAccountId = AccountingSettings::id('opening_balance', SystemAccountIds::OPENING_BALANCE); // 38

            // ========================================
            // بناء الاستعلام الأساسي للقيود المعتمدة
            // ========================================

            $baseQuery = function () use ($fromDate, $toDate, $branchFilter) {
                return JournalEntry::with(['details.account'])
                    ->where('status', 1) // القيود المعتمدة فقط
                    ->when(! empty($fromDate), function ($q) use ($fromDate) {
                        $q->where('date', '>=', $fromDate);
                    })
                    ->when(! empty($toDate), function ($q) use ($toDate) {
                        $q->where('date', '<=', $toDate);
                    })
                    ->when(! empty($branchFilter), function ($q) use ($branchFilter) {
                        $q->where('branch_id', $branchFilter);
                    });
            };

            // ========================================
            // 1. حساب صافي الدخل (من قائمة الدخل)
            // ========================================

            // إجمالي الإيرادات
            $totalRevenue = JournalEntryDetail::whereIn('account_id', $revenueAccountIds)
                ->where('account_id', '!=', $openingBalanceAccountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate, $branchFilter) {
                    $q->where('status', 1);
                    if (! empty($fromDate)) {
                        $q->where('date', '>=', $fromDate);
                    }
                    if (! empty($toDate)) {
                        $q->where('date', '<=', $toDate);
                    }
                    if (! empty($branchFilter)) {
                        $q->where('branch_id', $branchFilter);
                    }
                })
                ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as total')
                ->value('total') ?? 0;

            // إجمالي المصروفات
            $totalExpenses = JournalEntryDetail::whereIn('account_id', $expenseAccountIds)
                ->where('account_id', '!=', $openingBalanceAccountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate, $branchFilter) {
                    $q->where('status', 1);
                    if (! empty($fromDate)) {
                        $q->where('date', '>=', $fromDate);
                    }
                    if (! empty($toDate)) {
                        $q->where('date', '<=', $toDate);
                    }
                    if (! empty($branchFilter)) {
                        $q->where('branch_id', $branchFilter);
                    }
                })
                ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as total')
                ->value('total') ?? 0;

            $netIncome = $totalRevenue - $totalExpenses;

            // ========================================
            // 2. التغير في حسابات العملاء (المدينون)
            // ========================================

            // رصيد بداية الفترة للعملاء
            $clientsOpeningBalance = $this->getAccountBalanceBeforeDate($clientsAccountIds, $fromDate, $branchFilter, $openingBalanceAccountId);

            // رصيد نهاية الفترة للعملاء
            $clientsClosingBalance = $this->getAccountBalanceAtDate($clientsAccountIds, $toDate, $branchFilter, $openingBalanceAccountId);

            // التغير في حسابات العملاء (زيادة = سالب، نقصان = موجب)
            $changeInReceivables = $clientsClosingBalance - $clientsOpeningBalance;

            // ========================================
            // 4. التغير في حسابات الموردين (الدائنون)
            // ========================================

            // رصيد بداية الفترة للموردين
            $suppliersOpeningBalance = $this->getAccountBalanceBeforeDate($suppliersAccountIds, $fromDate, $branchFilter, $openingBalanceAccountId, 'credit');

            // رصيد نهاية الفترة للموردين
            $suppliersClosingBalance = $this->getAccountBalanceAtDate($suppliersAccountIds, $toDate, $branchFilter, $openingBalanceAccountId, 'credit');

            // التغير في حسابات الموردين (زيادة = موجب، نقصان = سالب)
            $changeInPayables = $suppliersClosingBalance - $suppliersOpeningBalance;

            // ========================================
            // 5. التغير في المخزون
            // ========================================

            // رصيد بداية الفترة للمخزون
            $inventoryOpeningBalance = $this->getAccountBalanceBeforeDate($inventoryAccountIds, $fromDate, $branchFilter, $openingBalanceAccountId);

            // رصيد نهاية الفترة للمخزون
            $inventoryClosingBalance = $this->getAccountBalanceAtDate($inventoryAccountIds, $toDate, $branchFilter, $openingBalanceAccountId);

            // التغير في المخزون (زيادة = سالب، نقصان = موجب)
            $changeInInventory = $inventoryClosingBalance - $inventoryOpeningBalance;

            // ========================================
            // إجمالي التدفق النقدي من العمليات التشغيلية
            // ========================================

            $operatingCashFlow = $netIncome
                - $changeInReceivables  // زيادة المدينين تقلل النقدية
                + $changeInPayables     // زيادة الدائنين تزيد النقدية
                - $changeInInventory;   // زيادة المخزون تقلل النقدية

            // ========================================
            // التدفقات النقدية من الأنشطة الاستثمارية
            // ========================================

            // رصيد بداية الفترة للأصول الثابتة
            $fixedAssetsOpeningBalance = $this->getAccountBalanceBeforeDate($fixedAssetsAccountIds, $fromDate, $branchFilter, $openingBalanceAccountId);

            // رصيد نهاية الفترة للأصول الثابتة
            $fixedAssetsClosingBalance = $this->getAccountBalanceAtDate($fixedAssetsAccountIds, $toDate, $branchFilter, $openingBalanceAccountId);

            // التغير في الأصول الثابتة (قبل الإهلاك)
            $changeInFixedAssets = $fixedAssetsClosingBalance - $fixedAssetsOpeningBalance;

            // النقد المُنفق لشراء الأصول (إذا كان موجب = شراء)
            $cashSpentOnAssets = $changeInFixedAssets > 0 ? -$changeInFixedAssets : 0;

            // النقد المستلم من بيع الأصول (إذا كان سالب = بيع)
            $cashReceivedFromAssetSales = $changeInFixedAssets < 0 ? abs($changeInFixedAssets) : 0;

            // إجمالي التدفق النقدي من الاستثمارات
            $investingCashFlow = $cashReceivedFromAssetSales + $cashSpentOnAssets;

            // ========================================
            // التدفقات النقدية من الأنشطة التمويلية
            // ========================================

            // رصيد بداية الفترة لحقوق الملكية
            $equityOpeningBalance = $this->getAccountBalanceBeforeDate($equityAccountIds, $fromDate, $branchFilter, $openingBalanceAccountId, 'credit');

            // رصيد نهاية الفترة لحقوق الملكية
            $equityClosingBalance = $this->getAccountBalanceAtDate($equityAccountIds, $toDate, $branchFilter, $openingBalanceAccountId, 'credit');

            // التغير في حقوق الملكية (باستثناء صافي الدخل)
            $changeInEquity = ($equityClosingBalance - $equityOpeningBalance) - $netIncome;

            // زيادة في الأسهم المشتركة / رأس المال
            $equityIncrease = $changeInEquity > 0 ? $changeInEquity : 0;

            // إجمالي التدفق النقدي من التمويل
            $financingCashFlow = $changeInEquity;

            // ========================================
            // إجمالي التغير في التدفق النقدي
            // ========================================

            $totalCashFlowChange = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

            // ========================================
            // حساب رصيد النقدية الفعلي للتحقق
            // ========================================

            // رصيد النقدية في بداية الفترة
            $cashOpeningBalance = $this->getAccountBalanceBeforeDate($cashAccountIds, $fromDate, $branchFilter, $openingBalanceAccountId);

            // رصيد النقدية في نهاية الفترة
            $cashClosingBalance = $this->getAccountBalanceAtDate($cashAccountIds, $toDate, $branchFilter, $openingBalanceAccountId);

            // التغير الفعلي في النقدية
            $actualCashChange = $cashClosingBalance - $cashOpeningBalance;

            // ========================================
            // إعداد البيانات للعرض
            // ========================================

            $cashFlowData = [
                // التدفقات من العمليات التشغيلية - بدون مصروف الإهلاك (غير نقدي ولم يعد ضمن العرض)
                'operating' => [
                    'net_income' => $netIncome,
                    'depreciation' => 0, // أُلغي من العرض - يبقى الحقل لتجنب كسر الواجهة
                    'change_in_receivables' => -$changeInReceivables, // نعكس الإشارة للعرض
                    'change_in_payables' => $changeInPayables,
                    'change_in_inventory' => -$changeInInventory, // نعكس الإشارة للعرض
                    'total' => $operatingCashFlow,
                ],
                // التدفقات من الاستثمارات
                'investing' => [
                    'cash_from_asset_sales' => $cashReceivedFromAssetSales,
                    'cash_spent_on_assets' => $cashSpentOnAssets,
                    'total' => $investingCashFlow,
                ],
                // التدفقات من التمويل
                'financing' => [
                    'equity_increase' => $changeInEquity,
                    'total' => $financingCashFlow,
                ],
                // الإجماليات
                'totals' => [
                    'total_change' => $totalCashFlowChange,
                    'cash_opening' => $cashOpeningBalance,
                    'cash_closing' => $cashClosingBalance,
                    'actual_change' => $actualCashChange,
                ],
            ];

            return view('account::report.accountGeneral.cash_flow_statement', compact(
                'cashFlowData',
                'branches',
                'fromDate',
                'toDate'
            ));

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', '_legacyCashFlowStatement');

            Log::error('خطأ في تقرير التدفقات النقدية: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء إنشاء التقرير: '.$e->getMessage());
        }
    }

    /**
     * حساب رصيد مجموعة حسابات قبل تاريخ معين
     */
    private function getAccountBalanceBeforeDate($accountIds, $date, $branchFilter, $excludeAccountId, $balanceType = 'debit')
    {
        $query = JournalEntryDetail::whereIn('account_id', $accountIds)
            ->where('account_id', '!=', $excludeAccountId)
            ->whereHas('journalEntry', function ($q) use ($date, $branchFilter) {
                $q->where('status', 1);
                if (! empty($date)) {
                    $q->where('date', '<', $date);
                }
                if (! empty($branchFilter)) {
                    $q->where('branch_id', $branchFilter);
                }
            });

        if ($balanceType === 'credit') {
            return $query->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as total')->value('total') ?? 0;
        } else {
            return $query->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as total')->value('total') ?? 0;
        }
    }

    /**
     * حساب رصيد مجموعة حسابات حتى تاريخ معين
     */
    private function getAccountBalanceAtDate($accountIds, $date, $branchFilter, $excludeAccountId, $balanceType = 'debit')
    {
        $query = JournalEntryDetail::whereIn('account_id', $accountIds)
            ->where('account_id', '!=', $excludeAccountId)
            ->whereHas('journalEntry', function ($q) use ($date, $branchFilter) {
                $q->where('status', 1);
                if (! empty($date)) {
                    $q->where('date', '<=', $date);
                }
                if (! empty($branchFilter)) {
                    $q->where('branch_id', $branchFilter);
                }
            });

        if ($balanceType === 'credit') {
            return $query->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as total')->value('total') ?? 0;
        } else {
            return $query->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as total')->value('total') ?? 0;
        }
    }

    /**
     * تقرير الأصول - يُبنى من شجرة الحسابات
     * يجلب جميع حسابات الأصول (تحت الحساب الأب 1 وكل أحفادها بأي مستوى)
     * ويستثني حسابات العناوين (الآباء التي لها أبناء) ليعرض فقط الحسابات التفصيلية
     * التي تستقبل القيود فعلياً.
     */
    public function assetsReport(Request $request)
    {
        try {
            $branches = Branch::orderBy('name')->get();

            // الفلاتر
            // الفترةُ علاقةً أوّلاً، والمدى التاريخيّ حين يُطلب صراحةً
            $scope = $this->reportPeriod(
                $request,
                $request->input('date_type', 'custom'),
                $request->input('from_date'),
                $request->input('to_date')
            );
            $reportPeriod = $scope['period'];
            [$fromDate, $toDate] = [$scope['from'], $scope['to']];
            $toDate = $toDate ?: now()->format('Y-m-d');
            $branchFilter = $request->input('branch');
            $parentFilter = $request->input('parent_id'); // فلتر الأب (أي مستوى)
            $accountIdsFilter = (array) $request->input('account_ids', []); // فلتر حسابات محددة (متعدد)
            $accountSearch = trim((string) $request->input('account_search', ''));
            $statusFilter = $request->input('status'); // 1=active / 0=inactive
            $movementFilter = $request->input('movement'); // 'with_movement'|'no_movement'|null
            // include_headers=1 يسمح بإظهار حسابات العناوين (الافتراضي: استبعادها)
            $includeHeaders = (bool) $request->input('include_headers', false);
            $groupBy = $request->input('group_by', 'parent');
            // alias قديم: category → parent_id (للحفاظ على التوافق مع روابط سابقة)
            if (empty($parentFilter) && ! empty($request->input('category'))) {
                $parentFilter = $request->input('category');
            }

            // معرفات شجرة الأصول كاملةً (الجذر + كل الأبناء بأي مستوى)
            $allAssetIds = $this->getAccountIdsWithChildren(AccountingSettings::id('assets', SystemAccountIds::ASSETS));

            // قائمة الحسابات الأب (التي لها أبناء) - تُعرض في فلتر "الأب"
            $parentAccounts = Account::whereIn('id', $allAssetIds)
                ->whereExists(function ($sub) {
                    $sub->select(\DB::raw(1))
                        ->from('accounts as child')
                        ->whereColumn('child.parent_id', 'accounts.id');
                })
                ->orderBy('code')->get(['id', 'code', 'name', 'parent_id']);

            // قائمة الحسابات التفصيلية فقط (بدون عناوين) - للفلتر متعدد الحسابات
            $allAccountsInTree = Account::whereIn('id', $allAssetIds)
                ->where('id', '!=', AccountingSettings::id('assets', SystemAccountIds::ASSETS))
                ->whereNotExists(function ($sub) {
                    $sub->select(\DB::raw(1))
                        ->from('accounts as child')
                        ->whereColumn('child.parent_id', 'accounts.id');
                })
                ->orderBy('code')->get(['id', 'code', 'name', 'parent_id']);

            // الاستعلام الفعلي للتقرير
            $accountsQuery = Account::whereIn('id', $allAssetIds)
                ->where('id', '!=', AccountingSettings::id('assets', SystemAccountIds::ASSETS)); // استبعاد جذر الأصول

            // ⭐ استبعاد العناوين (الحسابات التي لها أبناء) ما لم يُطلب إظهارها صراحةً
            if (! $includeHeaders) {
                $accountsQuery->whereNotExists(function ($sub) {
                    $sub->select(\DB::raw(1))
                        ->from('accounts as child')
                        ->whereColumn('child.parent_id', 'accounts.id');
                });
            }

            // فلتر الأب (يشمل أحفاده بأي مستوى)
            if (! empty($parentFilter)) {
                $parentTreeIds = $this->getAccountIdsWithChildren((int) $parentFilter);
                $accountsQuery->whereIn('id', $parentTreeIds);
            }

            // فلتر الحسابات المحددة (يقبل عدة حسابات)
            if (! empty($accountIdsFilter)) {
                $accountIdsFilter = array_filter(array_map('intval', $accountIdsFilter));
                if (! empty($accountIdsFilter)) {
                    $accountsQuery->whereIn('id', $accountIdsFilter);
                }
            }

            if ($accountSearch !== '') {
                $accountsQuery->where(function ($q) use ($accountSearch) {
                    $q->where('name', 'LIKE', "%{$accountSearch}%")
                        ->orWhere('code', 'LIKE', "%{$accountSearch}%");
                });
            }
            if ($statusFilter !== null && $statusFilter !== '') {
                $accountsQuery->where('is_active', (int) $statusFilter);
            }

            $accountsList = $accountsQuery->orderBy('code')->get();
            $accountIds = $accountsList->pluck('id')->all();

            // قاموس أسماء الآباء لكل حساب
            $allCategoryAccountIds = $accountsList->pluck('parent_id')->unique()->merge($accountsList->pluck('id'))->unique();
            $accountNameMap = Account::whereIn('id', $allCategoryAccountIds)->pluck('name', 'id');

            $closingMap = collect();
            $openingMap = collect();

            if (! empty($accountIds)) {
                // الرصيد الختامي + إجماليات الفترة (مدين/دائن للحساب) حتى تاريخ النهاية
                $closingMap = JournalEntryDetail::whereIn('account_id', $accountIds)
                    ->whereHas('journalEntry', function ($q) use ($toDate, $branchFilter) {
                        $q->where('status', 1)->where('date', '<=', $toDate);
                        if (! empty($branchFilter)) {
                            $q->where('branch_id', $branchFilter);
                        }
                    })
                    ->select(
                        'account_id',
                        DB::raw('COALESCE(SUM(debit), 0) as total_debit'),
                        DB::raw('COALESCE(SUM(credit), 0) as total_credit')
                    )
                    ->groupBy('account_id')->get()->keyBy('account_id');

                // الرصيد الافتتاحي قبل تاريخ البداية (إن وُجد)
                if (! empty($fromDate)) {
                    $openingMap = JournalEntryDetail::whereIn('account_id', $accountIds)
                        ->whereHas('journalEntry', function ($q) use ($fromDate, $branchFilter) {
                            $q->where('status', 1)->where('date', '<', $fromDate);
                            if (! empty($branchFilter)) {
                                $q->where('branch_id', $branchFilter);
                            }
                        })
                        ->select(
                            'account_id',
                            DB::raw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
                        )
                        ->groupBy('account_id')->pluck('net', 'account_id');
                }
            }

            // بناء صفوف التقرير
            $rows = collect();
            $totals = [
                'count' => 0,
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'total_balance' => 0.0,
                'opening_balance' => 0.0,
                'period_movement' => 0.0,
                'active_count' => 0,
                'inactive_count' => 0,
                'with_movement' => 0,
                'without_movement' => 0,
            ];

            foreach ($accountsList as $account) {
                $row = $closingMap[$account->id] ?? null;
                $debit = $row ? (float) $row->total_debit : 0.0;
                $credit = $row ? (float) $row->total_credit : 0.0;
                $closingBalance = $debit - $credit; // طبيعة الأصول مدين

                $opening = (float) ($openingMap[$account->id] ?? 0);

                // إذا لم تُحدَّد from_date نأخذ الافتتاحي = 0 وحركة الفترة = الرصيد الكامل
                if (empty($fromDate)) {
                    $opening = 0.0;
                    $periodMovement = $closingBalance;
                } else {
                    $periodMovement = $closingBalance - $opening;
                }

                $hasMovement = ($debit + $credit) > 0;

                // فلتر الحركة
                if ($movementFilter === 'with_movement' && ! $hasMovement) {
                    continue;
                }
                if ($movementFilter === 'no_movement' && $hasMovement) {
                    continue;
                }

                $rows->push([
                    'account_id' => (int) $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'parent_id' => $account->parent_id,
                    'parent_name' => $accountNameMap[$account->parent_id] ?? '-',
                    'is_active' => (int) ($account->is_active ?? 1),
                    'opening_balance' => $opening,
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                    'period_movement' => $periodMovement,
                    'closing_balance' => $closingBalance,
                    'has_movement' => $hasMovement,
                ]);

                $totals['count']++;
                $totals['total_debit'] += $debit;
                $totals['total_credit'] += $credit;
                $totals['total_balance'] += $closingBalance;
                $totals['opening_balance'] += $opening;
                $totals['period_movement'] += $periodMovement;
                if ((int) ($account->is_active ?? 1) === 1) {
                    $totals['active_count']++;
                } else {
                    $totals['inactive_count']++;
                }
                if ($hasMovement) {
                    $totals['with_movement']++;
                } else {
                    $totals['without_movement']++;
                }
            }

            // التجميع حسب الخيار
            $groupedRows = collect();
            if ($groupBy === 'parent' || $groupBy === 'category') {
                $groupedRows = $rows->groupBy(function ($r) {
                    return $r['parent_name'] ?: 'غير مصنف';
                });
            } elseif ($groupBy === 'status') {
                $groupedRows = $rows->groupBy(function ($r) {
                    return $r['is_active'] ? 'نشط' : 'غير نشط';
                });
            } elseif ($groupBy === 'movement') {
                $groupedRows = $rows->groupBy(function ($r) {
                    return $r['has_movement'] ? 'فيه حركة' : 'بدون حركة';
                });
            }

            return view('account::report.accountGeneral.assets_report', [
                'rows' => $rows,
                'groupedRows' => $groupedRows,
                'totals' => $totals,
                'branches' => $branches,
                'parentAccounts' => $parentAccounts,
                'allAccountsInTree' => $allAccountsInTree,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'branchFilter' => $branchFilter,
                'parentFilter' => $parentFilter,
                'accountIdsFilter' => $accountIdsFilter,
                'accountSearch' => $accountSearch,
                'statusFilter' => $statusFilter,
                'movementFilter' => $movementFilter,
                'includeHeaders' => $includeHeaders,
                'groupBy' => $groupBy,
                'dateType' => $request->input('date_type', 'custom'),
            ]);
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'assetsReport');

            Log::error('Assets Report Error: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return back()->with('error', 'حدث خطأ في تقرير الأصول: '.$e->getMessage());
        }
    }

    /**
     * تقرير ميزان مراجعة المخزون.
     * يعتمد فقط على شجرة المخزون (الحساب الأب 123 وكل أحفاده).
     * يعرض لكل حساب: الرصيد الافتتاحي، الداخل، الخارج، الرصيد الختامي + كل الحركات التفصيلية
     * مع الحساب المقابل لكل حركة.
     */
    public function inventoryTrialBalance(Request $request)
    {
        try {
            $branches = Branch::orderBy('name')->get();

            // الفلاتر
            // الفترةُ علاقةً أوّلاً، والمدى التاريخيّ حين يُطلب صراحةً
            $scope = $this->reportPeriod(
                $request,
                $request->input('date_type', 'custom'),
                $request->input('from_date'),
                $request->input('to_date')
            );
            $reportPeriod = $scope['period'];
            [$fromDate, $toDate] = [$scope['from'], $scope['to']];
            $toDate = $toDate ?: now()->format('Y-m-d');
            $branchFilter = $request->input('branch');
            $accountIdFilter = $request->input('account_id'); // حساب مخزون محدد
            $movementType = $request->input('movement_type', 'all'); // in/out/all
            $referenceFilter = trim((string) $request->input('reference_number', ''));
            $searchFilter = trim((string) $request->input('search', ''));
            $perPage = max(10, min(500, (int) $request->input('per_page', 50)));
            $page = max(1, (int) $request->input('page', 1));

            // معرفات حسابات شجرة المخزون — الأب هو 16 (كود 123)
            //
            // كان هنا دمجٌ للأب «الجديد» 123 مع «القديم» 16؛ و123 كودُ الحساب 16 نفسِه
            // لا معرّفٌ ثانٍ له، والمعرّف 123 يخصّ «أرباح محتجزة» فكان يدخل ميزان
            // المخزون حسابُ حقوق ملكية
            $inventoryAccountIds = $this->getAccountIdsWithChildren(AccountingSettings::id('inventory', SystemAccountIds::INVENTORY));

            // التحقق من وجود الحسابات في DB فعلياً
            $existingInventoryIds = Account::whereIn('id', $inventoryAccountIds)->pluck('id')->all();
            $inventoryAccountIds = $existingInventoryIds;

            // قائمة كل حسابات المخزون (للفلتر) - تشمل الكل بما فيها الآباء
            $inventoryAccountsList = Account::whereIn('id', $inventoryAccountIds)
                ->orderBy('code')->get(['id', 'code', 'name', 'parent_id']);

            // الحسابات المعنية في الاستعلام
            $targetAccountIds = $inventoryAccountIds;
            if (! empty($accountIdFilter)) {
                $accountTreeIds = $this->getAccountIdsWithChildren((int) $accountIdFilter);
                $targetAccountIds = array_values(array_intersect($targetAccountIds, $accountTreeIds));
                if (empty($targetAccountIds)) {
                    $targetAccountIds = $accountTreeIds;
                }
            }

            /*
                شروطُ القيد بربطٍ مباشر (`entry`) لا بـ`whereHas('journalEntry')`: الـ`EXISTS`
                المرتبط كان يُقيَّم سطراً سطراً على جدول السطور كلِّه في ثلاثة استعلامات.
                `$period` = false لرصيد أوّل المدة (ما قبل البداية)، و true لحركات الفترة.
            */
            $onEntries = function ($query, bool $period) use ($fromDate, $toDate, $branchFilter, $referenceFilter, $searchFilter) {
                $query->join('journal_entries as entry', 'entry.id', '=', 'journal_entry_details.journal_entry_id')
                    ->where('entry.status', 1);

                if (! $period) {
                    $query->where('entry.date', '<', $fromDate);
                } else {
                    if (! empty($fromDate)) {
                        $query->where('entry.date', '>=', $fromDate);
                    }
                    if (! empty($toDate)) {
                        $query->where('entry.date', '<=', $toDate);
                    }
                    if ($referenceFilter !== '') {
                        $query->where('entry.reference_number', 'LIKE', "%{$referenceFilter}%");
                    }
                    if ($searchFilter !== '') {
                        $query->where('entry.description', 'LIKE', "%{$searchFilter}%");
                    }
                }
                if (! empty($branchFilter)) {
                    $query->where('entry.branch_id', $branchFilter);
                }

                return $query;
            };

            // أرصدة افتتاحية
            $openingBalances = collect();
            if (! empty($targetAccountIds) && ! empty($fromDate)) {
                $openingBalances = $onEntries(JournalEntryDetail::query(), false)
                    ->whereIn('journal_entry_details.account_id', $targetAccountIds)
                    ->select('journal_entry_details.account_id', DB::raw('COALESCE(SUM(journal_entry_details.debit),0) - COALESCE(SUM(journal_entry_details.credit),0) as net'))
                    ->groupBy('journal_entry_details.account_id')->pluck('net', 'account_id');
            }

            // إجماليات الفترة لكل حساب (لبناء صفوف الملخص)
            $periodTotals = collect();
            if (! empty($targetAccountIds)) {
                $periodTotals = $onEntries(JournalEntryDetail::query(), true)
                    ->whereIn('journal_entry_details.account_id', $targetAccountIds)
                    ->select(
                        'journal_entry_details.account_id',
                        DB::raw('COALESCE(SUM(journal_entry_details.debit),0) as total_debit'),
                        DB::raw('COALESCE(SUM(journal_entry_details.credit),0) as total_credit'),
                        DB::raw('COUNT(DISTINCT journal_entry_details.journal_entry_id) as entries_count')
                    )
                    ->groupBy('journal_entry_details.account_id')->get()->keyBy('account_id');
            }

            // فلتر نوع الحركة (in = debit > 0, out = credit > 0)
            if ($movementType === 'in') {
                $periodTotals = $periodTotals->filter(fn ($r) => (float) $r->total_debit > 0);
            } elseif ($movementType === 'out') {
                $periodTotals = $periodTotals->filter(fn ($r) => (float) $r->total_credit > 0);
            }

            // قاموس كل حسابات المخزون (للعرض الكامل حتى لو بلا حركات)
            $accountsMap = Account::whereIn('id', $targetAccountIds)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])->keyBy('id');

            $summary = collect();
            $totals = ['opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0, 'entries' => 0];

            // نمر على كل حسابات المخزون - حتى التي بلا حركات تظهر برصيد افتتاحي + رصيد ختامي
            foreach ($accountsMap as $aid => $acc) {
                $row = $periodTotals[$aid] ?? null;
                $opening = (float) ($openingBalances[$aid] ?? 0);
                $debit = $row ? (float) $row->total_debit : 0.0;
                $credit = $row ? (float) $row->total_credit : 0.0;
                $entries = $row ? (int) $row->entries_count : 0;
                $closing = $opening + $debit - $credit;

                // إذا كان هناك فلتر نوع حركة، لا نعرض الحسابات بدون حركات
                if (in_array($movementType, ['in', 'out'], true) && ! $row) {
                    continue;
                }

                $summary->push([
                    'account_id' => (int) $aid,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'opening_balance' => $opening,
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                    'closing_balance' => $closing,
                    'entries_count' => $entries,
                ]);

                $totals['opening'] += $opening;
                $totals['debit'] += $debit;
                $totals['credit'] += $credit;
                $totals['closing'] += $closing;
                $totals['entries'] += $entries;
            }

            $summary = $summary->sortBy('code')->values();

            // ====== الحركات التفصيلية مع pagination ======
            $movementsTotal = 0;
            $movements = collect();

            if (! empty($targetAccountIds) && $periodTotals->isNotEmpty()) {
                $accountIdsForMovements = $periodTotals->keys()->all();

                $movementsQuery = $onEntries(JournalEntryDetail::query(), true)
                    ->with(['journalEntry.branch', 'journalEntry.details.account', 'account'])
                    ->whereIn('journal_entry_details.account_id', $accountIdsForMovements);

                if ($movementType === 'in') {
                    $movementsQuery->where('journal_entry_details.debit', '>', 0);
                }
                if ($movementType === 'out') {
                    $movementsQuery->where('journal_entry_details.credit', '>', 0);
                }

                $movementsTotal = (clone $movementsQuery)->count();

                $movementsRaw = $movementsQuery
                    ->orderBy('entry.date', 'asc')
                    ->orderBy('entry.id', 'asc')
                    ->orderBy('journal_entry_details.id', 'asc')
                    ->select('journal_entry_details.*')
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->get();

                // تحويل لمصفوفة بسيطة للـ view
                foreach ($movementsRaw as $d) {
                    $entry = $d->journalEntry;
                    if (! $entry) {
                        continue;
                    }
                    $counter = $entry->details->where('account_id', '!=', $d->account_id);
                    $counterAccountNames = $counter->pluck('account.name')->filter()->unique()->implode(', ');

                    $movements->push([
                        'detail_id' => $d->id,
                        'entry_id' => $entry->id,
                        'reference_number' => $entry->reference_number,
                        'date' => optional($entry->date)->format('Y-m-d') ?? $entry->date,
                        'description' => $d->description ?: $entry->description,
                        'debit' => (float) $d->debit,
                        'credit' => (float) $d->credit,
                        'account_id' => (int) $d->account_id,
                        'account_name' => $accountsMap[$d->account_id]->name ?? '-',
                        'account_code' => $accountsMap[$d->account_id]->code ?? '-',
                        'counter_accounts' => $counterAccountNames,
                        'branch' => optional($entry->branch)->name,
                    ]);
                }
            }

            $totalPages = max(1, (int) ceil($movementsTotal / $perPage));

            // معلومات تشخيصية لمساعدة المستخدم في فهم ما تم العثور عليه
            $diagnostics = [
                'parent_id_16_exists' => Account::where('id', AccountingSettings::id('inventory', SystemAccountIds::INVENTORY))->exists(),
                'inventory_tree_count' => count($inventoryAccountIds),
                'target_count' => count($targetAccountIds),
                'parent_id_used' => AccountingSettings::id('inventory', SystemAccountIds::INVENTORY),
            ];

            return view('account::report.accountGeneral.inventory_trial_balance', [
                'summary' => $summary,
                'movements' => $movements,
                'totals' => $totals,
                'branches' => $branches,
                'inventoryAccountsList' => $inventoryAccountsList,
                'diagnostics' => $diagnostics,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'branchFilter' => $branchFilter,
                'accountIdFilter' => $accountIdFilter,
                'movementType' => $movementType,
                'referenceFilter' => $referenceFilter,
                'searchFilter' => $searchFilter,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'movementsTotal' => $movementsTotal,
                'dateType' => $request->input('date_type', 'custom'),
            ]);
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'inventoryTrialBalance');

            Log::error('Inventory Trial Balance Error: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return back()->with('error', 'حدث خطأ في تقرير ميزان مراجعة المخزون: '.$e->getMessage());
        }
    }

    /**
     * النسخة القديمة من تقرير الأصول (تستخدم AssetDepreciation model) - محفوظة كـ private للرجوع.
     */
    private function _legacyAssetsReport(Request $request)
    {
        try {
            // جلب البيانات الأساسية للفلاتر
            $employees = Employee::orderBy('first_name')->get();
            $branches = Branch::orderBy('name')->get();
            $accounts = Account::where('parent_id', AccountingSettings::id('fixed_assets', SystemAccountIds::FIXED_ASSETS))->orderBy('name')->get(); // تصنيفات الأصول

            // تحديد الفترة الزمنية
            $purchaseDateFrom = $request->purchase_date_from;
            $purchaseDateTo = $request->purchase_date_to;
            $depreciationDateFrom = $request->depreciation_date_from ?? now()->subMonth()->format('Y-m-d');
            $depreciationDateTo = $request->depreciation_date_to ?? now()->format('Y-m-d');
            $statusFilter = $request->status;
            $categoryFilter = $request->category;
            $employeeFilter = $request->employee;
            $assetFilter = $request->asset;
            $groupBy = $request->group_by ?? 'All';

            // بناء الاستعلام الأساسي
            $query = AssetDepreciation::with(['employee', 'account', 'depreciation', 'cashAccountRelation']);

            // تطبيق الفلاتر
            if (! empty($assetFilter)) {
                $query->where('id', $assetFilter);
            }

            if (! empty($employeeFilter)) {
                $query->where('employee_id', $employeeFilter);
            }

            if (! empty($purchaseDateFrom)) {
                $query->whereDate('date_price', '>=', $purchaseDateFrom);
            }

            if (! empty($purchaseDateTo)) {
                $query->whereDate('date_price', '<=', $purchaseDateTo);
            }

            if (! empty($statusFilter)) {
                $query->where('status', $statusFilter);
            }

            if (! empty($categoryFilter)) {
                $query->where('account_id', $categoryFilter);
            }

            // جلب الأصول
            $assets = $query->orderBy('code')->get();

            // حساب الإحصائيات
            $totalAssets = $assets->count();
            $totalPurchaseValue = $assets->sum('purchase_value');
            $totalAccumulatedDepreciation = $assets->sum(function ($asset) {
                return $asset->depreciation ? $asset->depreciation->acc_dep : 0;
            });
            $totalBookValue = $assets->sum(function ($asset) {
                if ($asset->depreciation) {
                    return $asset->purchase_value - $asset->depreciation->acc_dep;
                }

                return $asset->purchase_value;
            });

            // إحصائيات حسب الحالة
            $assetsInService = $assets->where('status', 1)->count();
            $assetsInactive = $assets->where('status', 2)->count();
            $assetsDepreciated = $assets->where('status', 3)->count();
            $assetsSold = $assets->where('status', 4)->count();

            // حساب إهلاك الفترة المحددة
            $periodDepreciation = 0;
            foreach ($assets as $asset) {
                if ($asset->depreciation && $asset->depreciation->dep_rate) {
                    // حساب الإهلاك للفترة المحددة
                    $periodDepreciation += $asset->depreciation->dep_rate;
                }
            }

            // التجميع حسب الخيار المحدد
            $groupedAssets = collect();
            if ($groupBy === 'status') {
                $groupedAssets = $assets->groupBy('status');
            } elseif ($groupBy === 'In Service Date') {
                $groupedAssets = $assets->groupBy(function ($asset) {
                    return $asset->date_service ? $asset->date_service->format('Y-m') : 'غير محدد';
                });
            } elseif ($groupBy === 'Purchase Date') {
                $groupedAssets = $assets->groupBy(function ($asset) {
                    return $asset->date_price ? $asset->date_price->format('Y-m') : 'غير محدد';
                });
            } elseif ($groupBy === 'category') {
                $groupedAssets = $assets->groupBy('account_id');
            }

            // تعريفات الحالات
            $statusOptions = [
                1 => 'في الخدمة',
                2 => 'غير نشط',
                3 => 'مهلك',
                4 => 'تم بيعه',
            ];

            // تعريفات طرق الإهلاك
            $depreciationMethods = [
                1 => 'القسط الثابت',
                2 => 'القسط المتناقص',
                3 => 'وحدات الإنتاج',
                4 => 'بدون إهلاك',
            ];

            // جلب قائمة الأصول للفلتر
            $assetsList = AssetDepreciation::select('id', 'code', 'name')->orderBy('code')->get();

            $statistics = [
                'total_assets' => $totalAssets,
                'total_purchase_value' => $totalPurchaseValue,
                'total_accumulated_depreciation' => $totalAccumulatedDepreciation,
                'total_book_value' => $totalBookValue,
                'period_depreciation' => $periodDepreciation,
                'assets_in_service' => $assetsInService,
                'assets_inactive' => $assetsInactive,
                'assets_depreciated' => $assetsDepreciated,
                'assets_sold' => $assetsSold,
            ];

            return view('account::report.accountGeneral.assets_report', compact(
                'assets',
                'groupedAssets',
                'employees',
                'branches',
                'accounts',
                'assetsList',
                'statistics',
                'statusOptions',
                'depreciationMethods',
                'purchaseDateFrom',
                'purchaseDateTo',
                'depreciationDateFrom',
                'depreciationDateTo',
                'statusFilter',
                'categoryFilter',
                'employeeFilter',
                'assetFilter',
                'groupBy'
            ));

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', '_legacyAssetsReport');

            Log::error('خطأ في تقرير الأصول: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء إنشاء التقرير: '.$e->getMessage());
        }
    }

    public function ReportJournal(Request $request)
    {
        // تحديد الفترة الزمنية
        $fromDate = $request->filled('date_from')
            ? Carbon::parse($request->date_from)
            : $this->fiscalYearStart();

        $toDate = $request->filled('date_to')
            ? Carbon::parse($request->date_to)
            : Carbon::now();

        // تحديد نوع العرض (ملخص أو تفاصيل)
        $isSummary = $request->filled('is_summary') && $request->is_summary == '1';

        // بناء الاستعلام الأساسي
        $query = JournalEntry::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('status', 1); // فقط القيود المعتمدة

        // فلترة حسب مركز التكلفة — يسكن سطور القيد لا رأسه
        if ($request->filled('cost_center')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->where('cost_center_id', $request->cost_center);
            });
        }

        // فلترة حسب نوع المرجع (المصدر)
        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        // فلترة حسب الحساب الفرعي
        if ($request->filled('account_id')) {
            $accountIds = is_array($request->account_id) ? $request->account_id : [$request->account_id];
            $query->whereHas('details', function ($q) use ($accountIds) {
                $q->whereIn('account_id', $accountIds);
            });
        }

        // فلترة حسب الفرع
        if ($request->filled('branch_id')) {
            $branchIds = is_array($request->branch_id) ? $request->branch_id : [$request->branch_id];
            $query->whereIn('branch_id', $branchIds);
        }

        // فلترة حسب الموظف
        if ($request->filled('employee_id')) {
            $query->where('created_by_employee', $request->employee_id);
        }

        // فلترة حسب العميل
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        [$reportData, $totals, $journalPage] = $this->journalReport($query, $request, $isSummary);

        // جلب بيانات الفلاتر — الأعمدة التي تعرضها القوائم فقط
        $accounts = Account::orderBy('code')->get(['id', 'code', 'name']);
        $branches = Branch::all();
        $employees = User::whereNotNull('employee_id')->get();
        $clients = Client::all();

        // أنواع المراجع المتاحة
        $referenceTypes = ReferenceType::getAllWithDetails();

        return view('account::report.daily_restrictions_reports.report_journal', compact(
            'reportData',
            'totals',
            'accounts',
            'branches',
            'employees',
            'clients',
            'referenceTypes',
            'fromDate',
            'toDate',
            'isSummary',
            'journalPage'
        ));
    }

    /**
     * بياناتُ تقرير القيود (العام وحسب الموظف): الإجماليات، والملخّص حسب المصدر
     * أو قيودُ الصفحة المعروضة بتفاصيلها.
     *
     * @return array{0: array, 1: array, 2: LengthAwarePaginator|null}
     */
    private function journalReport($query, Request $request, bool $isSummary): array
    {
        /*
            **الإجماليات والملخّص بالتجميع، والتفاصيل صفحةً صفحة.**

            كانت قيودُ الفترة كلُّها تُحمَّل بسطورها وحساباتها ومصادرها ثم
            تُرسم في صفحةٍ واحدة — سنةٌ واحدة ~30 ألف قيد و~60 ألف سطر، فتنفد
            ذاكرةُ PHP وتُسقط الاستضافةُ الطلب بـ503. الآن الإجماليات (والملخّص
            حسب المصدر) باستعلامٍ مجمَّع على الفلاتر كلِّها، والتفاصيلُ لقيود
            الصفحة المعروضة فقط.
        */
        // ربطٌ مباشر بالقيود المفلترة — لا `IN (select id …)` يُعيد مسح الجدول لكلّ سطر
        $filteredLines = fn () => (clone $query)->toBase()
            ->join('journal_entry_details as report_line', 'report_line.journal_entry_id', '=', 'journal_entries.id');

        $totalsRow = $filteredLines()
            ->selectRaw('COALESCE(SUM(report_line.debit), 0) as debit, COALESCE(SUM(report_line.credit), 0) as credit')
            ->first();

        $totals = [
            'total_debit' => (float) $totalsRow->debit,
            'total_credit' => (float) $totalsRow->credit,
            'balance' => (float) $totalsRow->debit - (float) $totalsRow->credit,
            'count' => (clone $query)->count(),
        ];

        $reportData = [];
        $journalPage = null;

        if ($isSummary) {
            // عددُ القيود من رؤوسها، والمبالغُ من سطورها — استعلامان مجمَّعان بلا `COUNT(DISTINCT)`
            $bySource = (clone $query)->toBase()
                ->groupBy('reference_type')
                ->orderByRaw('MAX(date) DESC, MAX(id) DESC')
                ->selectRaw('reference_type, COUNT(*) as entries_count')
                ->get();

            $amountsBySource = $filteredLines()
                ->groupBy('journal_entries.reference_type')
                ->selectRaw('journal_entries.reference_type, COALESCE(SUM(report_line.debit), 0) as total_debit, COALESCE(SUM(report_line.credit), 0) as total_credit')
                ->get()
                ->keyBy(fn ($row) => (string) $row->reference_type);

            foreach ($bySource as $row) {
                $amounts = $amountsBySource->get((string) $row->reference_type);
                $row->total_debit = $amounts->total_debit ?? 0;
                $row->total_credit = $amounts->total_credit ?? 0;

                $key = $row->reference_type ?: 'JV';
                $referenceType = $row->reference_type !== null ? ReferenceType::fromValue($row->reference_type) : null;

                $reportData[$key] = [
                    'name' => $row->reference_type === null ? 'غير محدد' : ($referenceType ? $referenceType->getName() : 'غير محدد'),
                    'code' => $key,
                    'total_debit' => (float) $row->total_debit,
                    'total_credit' => (float) $row->total_credit,
                    'entries_count' => (int) $row->entries_count,
                    'entries' => [],
                ];
            }
        } else {
            $perPage = in_array((int) $request->input('per_page'), [50, 100, 200, 500], true) ? (int) $request->input('per_page') : 100;

            $journalPage = (clone $query)
                ->with(['details.account', 'branch', 'createdByEmployee', 'client', 'invoice', 'purchaseInvoice'])
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString();
        }

        // عرض التفاصيل (قيود الصفحة) - نجمع حسب رقم القيد ثم نعرض الحسابات المتأثرة
        foreach ($journalPage ?? [] as $entry) {
            $reportData[$entry->id] = [
                'journal_entry_id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'date' => $entry->date,
                'description' => $entry->description,
                'source_type' => $entry->reference_type_name ?? 'قيد يومية',
                'source_url' => $this->getJournalEntrySourceUrl($entry),
                'branch_name' => $entry->branch ? $entry->branch->name : 'غير محدد',
                'employee_name' => $entry->createdByEmployee ? $entry->createdByEmployee->name : '',
                'client_name' => $entry->client ? $entry->client->trade_name : '',
                'total_debit' => $entry->details->sum('debit'),
                'total_credit' => $entry->details->sum('credit'),
                'details' => [],
            ];

            foreach ($entry->details as $detail) {
                $reportData[$entry->id]['details'][] = [
                    'account_id' => $detail->account_id,
                    'account_code' => $detail->account ? $detail->account->code : '',
                    'account_name' => $detail->account ? $detail->account->name : 'غير محدد',
                    'debit' => $detail->debit,
                    'credit' => $detail->credit,
                    'description' => $detail->description ?? '',
                ];
            }
        }

        return [$reportData, $totals, $journalPage];
    }

    public function ChartOfAccounts(Request $request)
    {
        // إذا كان الطلب AJAX، نعيد البيانات فقط
        if ($request->ajax()) {
            try {
                // فلترة البيانات بناءً على المدخلات
                $query = Account::with(['branch', 'parent', 'costCenter']);

                // فلترة حسب مستوى الحساب
                if ($request->filled('account_level')) {
                    if ($request->account_level == 'main') {
                        $query->whereNull('parent_id'); // حسابات رئيسية
                    } elseif ($request->account_level == 'sub') {
                        $query->whereNotNull('parent_id'); // حسابات فرعية
                    }
                }

                // فلترة حسب نوع الحساب (مدين/دائن)
                if ($request->filled('account_type')) {
                    $query->where('balance_type', $request->account_type);
                }

                // فلترة حسب نوع الحساب (عملاء/موردين)
                if ($request->filled('account_category')) {
                    $query->where('category', $request->account_category);
                }

                // فلترة حسب الفرع
                if ($request->filled('branch') && $request->branch !== 'all') {
                    $query->where('branch_id', $request->branch);
                }

                // البحث في الكود والاسم
                if ($request->filled('search')) {
                    $searchTerm = $request->search;
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('code', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('name', 'LIKE', "%{$searchTerm}%");
                    });
                }

                // ترتيب حسب الكود أو الاسم
                if ($request->filled('order_by')) {
                    switch ($request->order_by) {
                        case 'desc':
                            $query->orderBy('code', 'desc');
                            break;
                        case 'name_asc':
                            $query->orderBy('name', 'asc');
                            break;
                        case 'name_desc':
                            $query->orderBy('name', 'desc');
                            break;
                        default:
                            $query->orderBy('code', 'asc');
                            break;
                    }
                } else {
                    $query->orderBy('code', 'asc');
                }

                // جلب البيانات
                $accounts = $query->get();

                // حساب الإحصائيات
                $statistics = [
                    'total_accounts' => $accounts->count(),
                    'main_accounts' => $accounts->where('parent_id', null)->count(),
                    'sub_accounts' => $accounts->where('parent_id', '!=', null)->count(),
                    'debit_accounts' => $accounts->where('balance_type', 'debit')->count(),
                    'credit_accounts' => $accounts->where('balance_type', 'credit')->count(),
                ];

                // إضافة معلومات إضافية للحسابات
                $accountsData = $accounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance_type' => $account->balance_type,
                        'parent_id' => $account->parent_id,
                        'branch' => $account->branch ? [
                            'id' => $account->branch->id,
                            'name' => $account->branch->name,
                        ] : null,
                        'cost_center' => $account->costCenter ? [
                            'id' => $account->costCenter->id,
                            'name' => $account->costCenter->name,
                        ] : null,
                        'parent' => $account->parent ? [
                            'id' => $account->parent->id,
                            'name' => $account->parent->name,
                            'code' => $account->parent->code,
                        ] : null,
                    ];
                });

                return response()->json([
                    'success' => true,
                    'accounts' => $accountsData,
                    'statistics' => $statistics,
                    'total_filtered' => $accounts->count(),
                    'filters_applied' => [
                        'account_level' => $request->account_level,
                        'account_type' => $request->account_type,
                        'account_category' => $request->account_category,
                        'branch' => $request->branch,
                        'search' => $request->search,
                        'order_by' => $request->order_by ?? 'asc',
                    ],
                ]);

            } catch (\Exception $e) {
                // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
                OperationLog::failure($e, 'Account.GeneralAccounts', 'ChartOfAccounts');

                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب البيانات: '.$e->getMessage(),
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // للعرض العادي، نعيد الـ view مع البيانات الأساسية
        try {
            $branches = Branch::orderBy('name')->get();
            $costCenters = CostCenter::orderBy('name')->get();

            // جلب الحسابات الأب (التي لها أبناء) للفلترة
            $parentAccounts = Account::whereHas('children')
                ->orWhereNull('parent_id')
                ->orderBy('code')
                ->get();

            // بناء الاستعلام الأساسي
            $query = Account::with(['branch', 'parent', 'costCenter', 'children']);

            // فلترة حسب الحساب الأب (عرض أبناء حساب معين فقط)
            $selectedParentId = null;
            if ($request->filled('parent_account') && $request->parent_account !== 'all') {
                $selectedParentId = $request->parent_account;
                // جلب جميع معرفات الأبناء لهذا الحساب بشكل تكراري
                $childrenIds = $this->getAllChildrenIds($selectedParentId);
                $childrenIds[] = (int) $selectedParentId; // تضمين الحساب الأب نفسه
                $query->whereIn('id', $childrenIds);
            }

            // فلترة حسب مستوى الحساب
            if ($request->filled('account_level')) {
                if ($request->account_level == 'main') {
                    $query->whereNull('parent_id');
                } elseif ($request->account_level == 'sub') {
                    $query->whereNotNull('parent_id');
                }
            }

            // فلترة حسب نوع الحساب (مدين/دائن)
            if ($request->filled('account_type')) {
                $query->where('balance_type', $request->account_type);
            }

            // فلترة حسب نوع الشجرة (أصول/خصوم/حقوق ملكية/إيرادات/مصروفات)
            if ($request->filled('account_tree_type')) {
                $query->where('account_type', $request->account_tree_type);
            }

            // فلترة حسب نوع الحساب (عملاء/موردين)
            if ($request->filled('account_category')) {
                $query->where('category', $request->account_category);
            }

            // فلترة حسب الفرع
            if ($request->filled('branch') && $request->branch !== 'all') {
                $query->where('branch_id', $request->branch);
            }

            // فلترة حسب مركز التكلفة
            if ($request->filled('cost_center') && $request->cost_center !== 'all') {
                // الحساب لا يملك مركز تكلفة: يُقصَر على الحسابات التي تحرّكت
                // في سطور قيدٍ تحمل هذا المركز
                $query->whereExists(function ($sub) use ($request) {
                    $sub->selectRaw('1')
                        ->from('journal_entry_details as jed')
                        ->whereColumn('jed.account_id', 'accounts.id')
                        ->where('jed.cost_center_id', $request->cost_center);
                });
            }

            // فلترة حسب تاريخ الإنشاء
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // البحث في الكود والاسم
            if ($request->filled('search_code')) {
                $query->where('code', 'LIKE', "%{$request->search_code}%");
            }

            if ($request->filled('search_name')) {
                $query->where('name', 'LIKE', "%{$request->search_name}%");
            }

            // جلب جميع الحسابات
            $allAccounts = $query->orderBy('code')->get();

            // بناء الشجرة الهرمية
            $accounts = $this->buildAccountsTree($allAccounts, $request->order_by ?? 'asc');

            return view('account::report.daily_restrictions_reports.chart_of_account_report',
                compact('accounts', 'branches', 'costCenters', 'allAccounts', 'parentAccounts')
            );

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.GeneralAccounts', 'ChartOfAccounts');

            return back()->with('error', 'حدث خطأ أثناء تحميل الصفحة: '.$e->getMessage());
        }
    }

    /**
     * جلب جميع معرفات الأبناء لحساب معين بشكل تكراري
     *
     * @param  int  $parentId  معرف الحساب الأب
     * @return array معرفات جميع الأبناء
     */
    private function getAllChildrenIds($parentId)
    {
        // من شجرة الذاكرة، ويقف عند المُكرَّر: حسابٌ أبٌ لنفسه كان يُعيد نفسه ابناً بلا نهاية
        $ids = [(int) $parentId];
        $this->getAllChildAccountIds($parentId, $ids);

        return array_slice($ids, 1);
    }

    /**
     * بناء شجرة الحسابات بشكل هرمي
     *
     * @param  Collection  $accounts  جميع الحسابات
     * @param  string  $orderBy  ترتيب الحسابات
     * @return Collection الحسابات مرتبة هرمياً
     */
    private function buildAccountsTree($accounts, $orderBy = 'asc')
    {
        $result = collect();

        // تجميع الحسابات حسب الأب
        $accountsByParent = [];
        foreach ($accounts as $account) {
            $parentId = $account->parent_id ?? 'root';
            if (! isset($accountsByParent[$parentId])) {
                $accountsByParent[$parentId] = collect();
            }
            $accountsByParent[$parentId]->push($account);
        }

        // ترتيب كل مجموعة حسب الكود
        foreach ($accountsByParent as $parentId => $children) {
            $accountsByParent[$parentId] = $orderBy === 'desc'
                ? $children->sortByDesc('code')
                : $children->sortBy('code');
        }

        // بناء الشجرة بدءاً من الجذور
        $this->addAccountsToTree($result, $accountsByParent, 'root', 0);

        return $result;
    }

    /**
     * إضافة الحسابات إلى الشجرة بشكل تكراري
     */
    private function addAccountsToTree(&$result, &$accountsByParent, $parentId, $level)
    {
        if (! isset($accountsByParent[$parentId])) {
            return;
        }

        foreach ($accountsByParent[$parentId] as $account) {
            // إضافة مستوى الحساب
            $account->tree_level = $level;
            $account->has_children = isset($accountsByParent[$account->id]) && $accountsByParent[$account->id]->count() > 0;

            $result->push($account);

            // إضافة الأبناء بشكل تكراري
            $this->addAccountsToTree($result, $accountsByParent, $account->id, $level + 1);
        }
    }

    /**
     * تقرير ميزان المراجعة - نسخة محسنة للأداء
     * Trial Balance Report - Optimized Version
     */
    public function trialBalance(Request $request)
    {
        // إعداد البيانات المساعدة للعرض (استعلامات خفيفة)
        $branches = Branch::select('id', 'name')->get();
        $costCenters = CostCenter::select('id', 'name')->get();
        // جلب المستخدمين المرتبطين بموظفين فقط (استبعاد العملاء)
        $users = User::select('id', 'name')
            ->whereNotNull('employee_id')
            ->whereNull('client_id')
            ->get();

        // 1. معالجة نطاق التاريخ
        $dateType = $request->input('date_type', 'this_year');
        $fromDate = $request->input('start_date');
        $toDate = $request->input('end_date');

        $dates = $this->calculateTrialBalanceDateRange($dateType, $fromDate, $toDate);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // 2. الفلاتر
        $branchId = $request->input('branch_id');
        $costCenterId = $request->input('cost_center_id');
        $accountLevel = (int) $request->input('account_level', 0);
        $hideZero = $request->boolean('hide_zero');

        // 3. جلب جميع الحسابات مرة واحدة (الحقول المطلوبة فقط)
        $allAccounts = Account::select('id', 'code', 'name', 'parent_id')
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        // 4. جلب جميع أرصدة الحسابات في استعلامين فقط (الرصيد الافتتاحي + حركات الفترة)
        $accountBalances = $this->fetchAllAccountBalancesOptimized($startDate, $endDate, $branchId, $costCenterId);

        // 5. بناء الشجرة وحساب المجاميع
        $accountsData = $this->buildOptimizedTrialBalanceTree($allAccounts, $accountBalances, $hideZero, $accountLevel);

        // 6. حساب الإجماليات من الحسابات الرئيسية فقط
        $rootAccounts = array_filter($accountsData, fn ($acc) => $acc['level'] == 0);
        $rootCollection = collect($rootAccounts);

        $totals = [
            'opening_balance_debit' => round($rootCollection->sum('opening_balance_debit'), 2),
            'opening_balance_credit' => round($rootCollection->sum('opening_balance_credit'), 2),
            'period_debit' => round($rootCollection->sum('period_debit'), 2),
            'period_credit' => round($rootCollection->sum('period_credit'), 2),
            'closing_balance_debit' => round($rootCollection->sum('closing_balance_debit'), 2),
            'closing_balance_credit' => round($rootCollection->sum('closing_balance_credit'), 2),
            'net_debit' => round($rootCollection->sum('net_debit'), 2),
            'net_credit' => round($rootCollection->sum('net_credit'), 2),
            'accounts_count' => count($accountsData),
            'total_debit' => round($rootCollection->sum('period_debit'), 2),
            'total_credit' => round($rootCollection->sum('period_credit'), 2),
        ];

        // لا نحتاج accounts في الـ view
        $accounts = collect();

        return view('account::report.daily_restrictions_reports.accounts_trial_balance', compact(
            'branches',
            'costCenters',
            'users',
            'accounts',
            'accountsData',
            'totals',
            'startDate',
            'endDate'
        ));
    }

    /**
     * جلب جميع أرصدة الحسابات في استعلامين فقط - محسن للأداء
     */
    private function fetchAllAccountBalancesOptimized($startDate, $endDate, $branchId = null, $costCenterId = null)
    {
        $balances = [];

        // استعلام الرصيد الافتتاحي (قبل تاريخ البداية) - مجمع حسب الحساب
        $openingQuery = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.date', '<', $startDate)
            ->where('journal_entries.status', 1)
            ->selectRaw('journal_entry_details.account_id, SUM(journal_entry_details.debit) as total_debit, SUM(journal_entry_details.credit) as total_credit')
            ->groupBy('journal_entry_details.account_id');

        if ($branchId) {
            $openingQuery->where('journal_entries.branch_id', $branchId);
        }
        if ($costCenterId) {
            $openingQuery->where('journal_entry_details.cost_center_id', $costCenterId);
        }

        $openingBalances = $openingQuery->get()->keyBy('account_id');

        // استعلام حركات الفترة - مجمع حسب الحساب
        $periodQuery = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->selectRaw('journal_entry_details.account_id, SUM(journal_entry_details.debit) as total_debit, SUM(journal_entry_details.credit) as total_credit')
            ->groupBy('journal_entry_details.account_id');

        if ($branchId) {
            $periodQuery->where('journal_entries.branch_id', $branchId);
        }
        if ($costCenterId) {
            $periodQuery->where('journal_entry_details.cost_center_id', $costCenterId);
        }

        $periodMovements = $periodQuery->get()->keyBy('account_id');

        // دمج النتائج في مصفوفة واحدة
        $allAccountIds = $openingBalances->keys()->merge($periodMovements->keys())->unique();

        foreach ($allAccountIds as $accountId) {
            $openingDebit = $openingBalances->get($accountId)->total_debit ?? 0;
            $openingCredit = $openingBalances->get($accountId)->total_credit ?? 0;
            $periodDebit = $periodMovements->get($accountId)->total_debit ?? 0;
            $periodCredit = $periodMovements->get($accountId)->total_credit ?? 0;

            $balances[$accountId] = [
                'opening_debit' => (float) $openingDebit,
                'opening_credit' => (float) $openingCredit,
                'period_debit' => (float) $periodDebit,
                'period_credit' => (float) $periodCredit,
            ];
        }

        return $balances;
    }

    /**
     * بناء شجرة ميزان المراجعة المحسنة
     */
    private function buildOptimizedTrialBalanceTree($allAccounts, $accountBalances, $hideZero = false, $maxLevel = 0)
    {
        // بناء خريطة الأبناء لكل حساب
        $childrenMap = [];
        foreach ($allAccounts as $account) {
            $parentId = $account->parent_id;
            if ($parentId) {
                if (! isset($childrenMap[$parentId])) {
                    $childrenMap[$parentId] = [];
                }
                $childrenMap[$parentId][] = $account->id;
            }
        }

        // حساب المستوى لكل حساب
        $accountLevels = [];
        foreach ($allAccounts as $account) {
            $accountLevels[$account->id] = $this->calculateAccountLevel($account->id, $allAccounts);
        }

        // حساب الأرصدة المجمعة (من الأسفل للأعلى)
        $aggregatedBalances = $this->aggregateBalancesBottomUp($allAccounts, $accountBalances, $childrenMap);

        // بناء النتيجة النهائية
        $result = [];
        $rootAccounts = $allAccounts->filter(fn ($acc) => is_null($acc->parent_id));

        // ترتيب الحسابات الجذرية: التي لها أبناء أولاً ثم حسب الكود
        $sortedRootAccounts = $rootAccounts->map(function ($account) use ($childrenMap) {
            return [
                'account' => $account,
                'has_children' => isset($childrenMap[$account->id]) && count($childrenMap[$account->id]) > 0,
                'code' => $account->code,
            ];
        })->sortBy([
            ['has_children', 'desc'],
            ['code', 'asc'],
        ])->values();

        foreach ($sortedRootAccounts as $item) {
            $this->addAccountToResultOptimized(
                $item['account'],
                $allAccounts,
                $aggregatedBalances,
                $childrenMap,
                $accountLevels,
                $result,
                0,
                $hideZero,
                $maxLevel
            );
        }

        return $result;
    }

    /**
     * حساب مستوى الحساب في الشجرة
     */
    private function calculateAccountLevel($accountId, $allAccounts, $level = 0)
    {
        return $level + $this->ancestorCount($accountId, $allAccounts);
    }

    /**
     * عددُ آباء الحساب في خريطةٍ محمَّلة (`id` ⇒ حساب) — بلا استعلام.
     *
     * يقف عند أوّل أبٍ تكرّر: حسابٌ صار أباً لنفسه (أو لأحد آبائه) كان يُدخل
     * الصعودَ التكراريّ في حلقةٍ تستنفد الذاكرة، فتسقط التقارير كلُّها بـ503.
     */
    private function ancestorCount($accountId, $allAccounts): int
    {
        $count = 0;
        $seen = [(int) $accountId => true];
        $account = $allAccounts->get($accountId);

        while ($account && $account->parent_id && ! isset($seen[(int) $account->parent_id])) {
            $seen[(int) $account->parent_id] = true;
            $count++;
            $account = $allAccounts->get($account->parent_id);
        }

        return $count;
    }

    /**
     * تجميع الأرصدة من الأسفل للأعلى
     */
    private function aggregateBalancesBottomUp($allAccounts, $accountBalances, $childrenMap)
    {
        $aggregated = [];

        // ترتيب الحسابات حسب العمق (الأعمق أولاً)
        $accountsByDepth = [];
        foreach ($allAccounts as $account) {
            $depth = $this->getAccountDepth($account->id, $allAccounts);
            if (! isset($accountsByDepth[$depth])) {
                $accountsByDepth[$depth] = [];
            }
            $accountsByDepth[$depth][] = $account->id;
        }
        krsort($accountsByDepth); // ترتيب تنازلي

        // معالجة من الأعمق للأعلى
        foreach ($accountsByDepth as $depth => $accountIds) {
            foreach ($accountIds as $accountId) {
                // رصيد الحساب نفسه
                $ownBalance = $accountBalances[$accountId] ?? [
                    'opening_debit' => 0,
                    'opening_credit' => 0,
                    'period_debit' => 0,
                    'period_credit' => 0,
                ];

                $totalOpeningDebit = $ownBalance['opening_debit'];
                $totalOpeningCredit = $ownBalance['opening_credit'];
                $totalPeriodDebit = $ownBalance['period_debit'];
                $totalPeriodCredit = $ownBalance['period_credit'];

                // جمع أرصدة الأبناء (التي تم حسابها مسبقاً)
                $children = $childrenMap[$accountId] ?? [];
                foreach ($children as $childId) {
                    if (isset($aggregated[$childId])) {
                        $totalOpeningDebit += $aggregated[$childId]['opening_debit'];
                        $totalOpeningCredit += $aggregated[$childId]['opening_credit'];
                        $totalPeriodDebit += $aggregated[$childId]['period_debit'];
                        $totalPeriodCredit += $aggregated[$childId]['period_credit'];
                    }
                }

                $aggregated[$accountId] = [
                    'opening_debit' => $totalOpeningDebit,
                    'opening_credit' => $totalOpeningCredit,
                    'period_debit' => $totalPeriodDebit,
                    'period_credit' => $totalPeriodCredit,
                ];
            }
        }

        return $aggregated;
    }

    /**
     * حساب عمق الحساب في الشجرة
     */
    private function getAccountDepth($accountId, $allAccounts, $depth = 0)
    {
        return $depth + $this->ancestorCount($accountId, $allAccounts);
    }

    /**
     * إضافة حساب للنتيجة مع أبنائه
     */
    private function addAccountToResultOptimized($account, $allAccounts, $aggregatedBalances, $childrenMap, $accountLevels, &$result, $level, $hideZero, $maxLevel)
    {
        // التحقق من المستوى الأقصى
        if ($maxLevel > 0 && $level >= $maxLevel) {
            return;
        }

        $accountId = $account->id;
        $hasChildren = isset($childrenMap[$accountId]) && count($childrenMap[$accountId]) > 0;

        // جلب الأرصدة المجمعة
        $balances = $aggregatedBalances[$accountId] ?? [
            'opening_debit' => 0,
            'opening_credit' => 0,
            'period_debit' => 0,
            'period_credit' => 0,
        ];

        // حساب الرصيد قبل
        $netOpening = $balances['opening_debit'] - $balances['opening_credit'];
        $beforeDebit = $netOpening > 0 ? $netOpening : 0;
        $beforeCredit = $netOpening < 0 ? abs($netOpening) : 0;

        // حساب الرصيد بعد
        $afterDebit = $beforeDebit + $balances['period_debit'];
        $afterCredit = $beforeCredit + $balances['period_credit'];

        // حساب صافي الرصيد
        $netBalance = $afterDebit - $afterCredit;
        $netDebit = $netBalance > 0 ? $netBalance : 0;
        $netCredit = $netBalance < 0 ? abs($netBalance) : 0;

        // فلترة الأرصدة الصفرية
        $hasActivity = ($beforeDebit > 0 || $beforeCredit > 0 || $balances['period_debit'] > 0 || $balances['period_credit'] > 0);

        if (! $hideZero || $hasActivity) {
            $result[] = [
                'id' => $accountId,
                'code' => $account->code,
                'name' => $account->name,
                'parent_id' => $account->parent_id,
                'level' => $level,
                'has_children' => $hasChildren,
                'opening_balance_debit' => round($beforeDebit, 2),
                'opening_balance_credit' => round($beforeCredit, 2),
                'period_debit' => round($balances['period_debit'], 2),
                'period_credit' => round($balances['period_credit'], 2),
                'closing_balance_debit' => round($afterDebit, 2),
                'closing_balance_credit' => round($afterCredit, 2),
                'net_debit' => round($netDebit, 2),
                'net_credit' => round($netCredit, 2),
            ];

            // إضافة الأبناء - مع ترتيب الحسابات التي لها أبناء أولاً ثم الكود
            $children = $childrenMap[$accountId] ?? [];

            // ترتيب الأبناء: الحسابات التي لديها أبناء أولاً، ثم حسب الكود
            $sortedChildren = collect($children)->map(function ($childId) use ($allAccounts, $childrenMap) {
                $childAccount = $allAccounts->get($childId);

                return [
                    'id' => $childId,
                    'account' => $childAccount,
                    'has_children' => isset($childrenMap[$childId]) && count($childrenMap[$childId]) > 0,
                    'code' => $childAccount ? $childAccount->code : '',
                ];
            })->sortBy([
                ['has_children', 'desc'], // الحسابات التي لها أبناء أولاً
                ['code', 'asc'],           // ثم ترتيب حسب الكود
            ])->values();

            foreach ($sortedChildren as $child) {
                if ($child['account']) {
                    $this->addAccountToResultOptimized(
                        $child['account'],
                        $allAccounts,
                        $aggregatedBalances,
                        $childrenMap,
                        $accountLevels,
                        $result,
                        $level + 1,
                        $hideZero,
                        $maxLevel
                    );
                }
            }
        }
    }

    /**
     * جلب بيانات ميزان المراجعة عبر AJAX
     */
    public function trialBalanceAjax(Request $request)
    {
        try {
            // 1. معالجة نطاق التاريخ
            $dateType = $request->input('date_type', 'this_year');
            $fromDate = $request->input('start_date');
            $toDate = $request->input('end_date');

            // تحديد التواريخ بناءً على النوع
            $dates = $this->calculateTrialBalanceDateRange($dateType, $fromDate, $toDate);
            $startDate = $dates['start'];
            $endDate = $dates['end'];

            /*
                2. الحسابات والأرصدة — استعلاما تجميعٍ لا استعلامان لكلّ حساب.

                كان كلُّ حسابٍ يُجمع رصيدُه باستعلامَين، وأبناؤه يُجلبون
                باستعلامٍ لكلّ عقدة، ومستواه بـ`find()` لكلّ أب: آلافُ
                الاستعلامات للطلب الواحد. وحسابٌ أبٌ لنفسه كان يُدخل الصعودَ
                والنزولَ في حلقةٍ تستنفد الذاكرة (503). الآن: الشجرةُ تُحمَّل
                مرّةً، والأرصدةُ باستعلامَي تجميعٍ بالفلاتر نفسها، ورصيدُ كلّ
                حسابٍ مجموعُ أرصدة شجرته في الذاكرة.
            */
            $branchId = $request->input('branch_id');
            $costCenterId = $request->input('cost_center_id');
            $accountLevel = $request->input('account_level');

            $allAccounts = Account::select('id', 'code', 'name', 'parent_id', 'branch_id')->get()->keyBy('id');

            $childrenMap = [];
            foreach ($allAccounts as $candidate) {
                if ($candidate->parent_id && (int) $candidate->parent_id !== (int) $candidate->id) {
                    $childrenMap[(int) $candidate->parent_id][] = (int) $candidate->id;
                }
            }

            if ($accountLevel && $accountLevel > 0) {
                // مستوًى محدَّد: كلُّ الحسابات حتى هذا المستوى (بلا فلتر فرعٍ على الحسابات — كما كان)
                $accounts = collect();
                foreach ($allAccounts as $account) {
                    $level = $this->ancestorCount($account->id, $allAccounts);
                    if ($level <= $accountLevel) {
                        $account->calculated_level = $level;
                        $accounts->push($account);
                    }
                }
            } else {
                // الافتراضي: الحسابات الرئيسية فقط (parent_id = null)، بفلتر الفرع إن اختير
                $accounts = $allAccounts->filter(fn ($account) => is_null($account->parent_id)
                    && (! $branchId || (int) $account->branch_id === (int) $branchId))->values();
                foreach ($accounts as $account) {
                    $account->calculated_level = 0;
                }
            }

            // 4. حساب الأرصدة للحسابات
            $accountBalances = [];
            $hideZero = $request->input('hide_zero');
            $ownBalances = $this->fetchAllAccountBalancesOptimized($startDate, $endDate, $branchId, $costCenterId);

            foreach ($accounts as $account) {
                // الحسابُ وكلُّ أحفاده — يقف عند المُكرَّر فلا يدور في حلقة
                $subtree = [(int) $account->id => true];
                $stack = [(int) $account->id];
                while ($stack) {
                    foreach ($childrenMap[array_pop($stack)] ?? [] as $childId) {
                        if (! isset($subtree[$childId])) {
                            $subtree[$childId] = true;
                            $stack[] = $childId;
                        }
                    }
                }

                $openingDebit = $openingCredit = $periodDebit = $periodCredit = 0;
                foreach (array_keys($subtree) as $memberId) {
                    $own = $ownBalances[$memberId] ?? null;
                    if ($own) {
                        $openingDebit += $own['opening_debit'];
                        $openingCredit += $own['opening_credit'];
                        $periodDebit += $own['period_debit'];
                        $periodCredit += $own['period_credit'];
                    }
                }

                // حساب صافي الرصيد الافتتاحي
                $netOpeningBalance = $openingDebit - $openingCredit;
                $beforeDebit = $netOpeningBalance > 0 ? abs($netOpeningBalance) : 0;
                $beforeCredit = $netOpeningBalance < 0 ? abs($netOpeningBalance) : 0;

                // حساب الرصيد بعد الحركات
                $afterDebit = $beforeDebit + $periodDebit;
                $afterCredit = $beforeCredit + $periodCredit;

                // حساب صافي الرصيد النهائي
                $netBalance = ($afterDebit - $afterCredit);
                $netDebit = $netBalance > 0 ? abs($netBalance) : 0;
                $netCredit = $netBalance < 0 ? abs($netBalance) : 0;

                // فلترة الحسابات الصفرية إذا طُلب
                if ($hideZero) {
                    $hasActivity = ($beforeDebit > 0 || $beforeCredit > 0 || $periodDebit > 0 || $periodCredit > 0);
                    if (! $hasActivity) {
                        continue;
                    }
                }

                $accountBalances[] = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'parent_id' => $account->parent_id,
                    'level' => $account->calculated_level ?? 0,
                    'has_children' => ! empty($childrenMap[(int) $account->id]),
                    'opening_balance_debit' => round($beforeDebit, 2),
                    'opening_balance_credit' => round($beforeCredit, 2),
                    'period_debit' => round($periodDebit, 2),
                    'period_credit' => round($periodCredit, 2),
                    'closing_balance_debit' => round($afterDebit, 2),
                    'closing_balance_credit' => round($afterCredit, 2),
                    'net_debit' => round($netDebit, 2),
                    'net_credit' => round($netCredit, 2),
                ];
            }

            // ترتيب الحسابات حسب الكود
            usort($accountBalances, function ($a, $b) {
                return strcmp($a['code'], $b['code']);
            });

            // حساب الإجماليات
            $totals = [
                'opening_balance_debit' => round(collect($accountBalances)->sum('opening_balance_debit'), 2),
                'opening_balance_credit' => round(collect($accountBalances)->sum('opening_balance_credit'), 2),
                'period_debit' => round(collect($accountBalances)->sum('period_debit'), 2),
                'period_credit' => round(collect($accountBalances)->sum('period_credit'), 2),
                'closing_balance_debit' => round(collect($accountBalances)->sum('closing_balance_debit'), 2),
                'closing_balance_credit' => round(collect($accountBalances)->sum('closing_balance_credit'), 2),
                'net_debit' => round(collect($accountBalances)->sum('net_debit'), 2),
                'net_credit' => round(collect($accountBalances)->sum('net_credit'), 2),
                'accounts_count' => count($accountBalances),
            ];

            return response()->json([
                'success' => true,
                'data' => $accountBalances,
                'totals' => $totals,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.GeneralAccounts', 'trialBalanceAjax');

            Log::error('Error in trialBalanceAjax: '.$e->getMessage().' - '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * حساب نطاق التاريخ لميزان المراجعة
     */
    private function calculateTrialBalanceDateRange($dateType, $fromDate, $toDate)
    {
        // إذا تم إدخال "من/إلى" يدويًا نُعطيها الأولوية دائماً
        if (! empty($fromDate) || ! empty($toDate)) {
            $startDate = ! empty($fromDate) ? Carbon::parse($fromDate) : $this->fiscalYearStart();
            $endDate = ! empty($toDate) ? Carbon::parse($toDate) : Carbon::now();

            if ($startDate->gt($endDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            return [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ];
        }

        $startDate = null;
        $endDate = null;

        switch ($dateType) {
            case 'today':
                $startDate = Carbon::today();
                $endDate = Carbon::today();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday();
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'last_week':
                $startDate = Carbon::now()->subWeek()->startOfWeek();
                $endDate = Carbon::now()->subWeek()->endOfWeek();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'this_year':
            case 'year_to_date':
                $startDate = $this->fiscalYearStart();
                $endDate = Carbon::now();
                break;
            case 'last_year':
                $startDate = $this->fiscalYearStart(Carbon::now()->subYear());
                $endDate = $this->fiscalYearEnd(Carbon::now()->subYear());
                break;
            case 'custom':
            default:
                $startDate = $fromDate ? Carbon::parse($fromDate) : $this->fiscalYearStart();
                $endDate = $toDate ? Carbon::parse($toDate) : Carbon::now();
                break;
        }

        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * تقرير حساب المراجعة - مع شجرة الحسابات
     * Account Review Report - with accounts tree
     */
    public function accountReview(Request $request)
    {
        // إعداد البيانات المساعدة للعرض (استعلامات خفيفة)
        $branches = Branch::select('id', 'name')->get();
        $costCenters = CostCenter::select('id', 'name')->get();
        // جلب المستخدمين المرتبطين بموظفين فقط (استبعاد العملاء)
        $users = User::select('id', 'name')
            ->whereNotNull('employee_id')
            ->whereNull('client_id')
            ->get();

        // 1. معالجة نطاق التاريخ
        $dateType = $request->input('date_type', 'this_year');
        $fromDate = $request->input('start_date');
        $toDate = $request->input('end_date');

        $dates = $this->calculateTrialBalanceDateRange($dateType, $fromDate, $toDate);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // 2. الفلاتر
        $branchId = $request->input('branch_id');
        $costCenterId = $request->input('cost_center_id');
        $accountLevel = (int) $request->input('account_level', 0);
        $hideZero = $request->boolean('hide_zero');

        // 3. جلب جميع الحسابات مرة واحدة (الحقول المطلوبة فقط)
        $allAccounts = Account::select('id', 'code', 'name', 'parent_id')
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        // 4. جلب جميع أرصدة الحسابات في استعلامين فقط (الرصيد الافتتاحي + حركات الفترة)
        $accountBalances = $this->fetchAllAccountBalancesOptimized($startDate, $endDate, $branchId, $costCenterId);

        // 5. بناء الشجرة وحساب المجاميع
        $accountsData = $this->buildOptimizedTrialBalanceTree($allAccounts, $accountBalances, $hideZero, $accountLevel);

        // 6. حساب الإجماليات من الحسابات الرئيسية فقط
        $rootAccounts = array_filter($accountsData, fn ($acc) => $acc['level'] == 0);
        $rootCollection = collect($rootAccounts);

        $totals = [
            'opening_balance_debit' => round($rootCollection->sum('opening_balance_debit'), 2),
            'opening_balance_credit' => round($rootCollection->sum('opening_balance_credit'), 2),
            'period_debit' => round($rootCollection->sum('period_debit'), 2),
            'period_credit' => round($rootCollection->sum('period_credit'), 2),
            'closing_balance_debit' => round($rootCollection->sum('closing_balance_debit'), 2),
            'closing_balance_credit' => round($rootCollection->sum('closing_balance_credit'), 2),
            'net_debit' => round($rootCollection->sum('net_debit'), 2),
            'net_credit' => round($rootCollection->sum('net_credit'), 2),
            'accounts_count' => count($accountsData),
            'total_debit' => round($rootCollection->sum('period_debit'), 2),
            'total_credit' => round($rootCollection->sum('period_credit'), 2),
        ];

        // لا نحتاج accounts في الـ view
        $accounts = collect();

        return view('account::report.daily_restrictions_reports.account_review', compact(
            'branches',
            'costCenters',
            'users',
            'accounts',
            'accountsData',
            'totals',
            'startDate',
            'endDate'
        ));
    }

    /**
     * تقرير مراكز التكلفة
     */
    public function CostCentersReport(Request $request)
    {
        // جلب البيانات الأساسية
        $branches = Branch::all();
        $costCenters = CostCenter::all();
        $users = User::all();
        $accounts = Account::all();

        // معالجة الفلاتر
        $fromDate = $request->input('date_from', $this->fiscalYearStart()->format('Y-m-d'));
        $toDate = $request->input('date_to', now()->format('Y-m-d'));

        // تحويل التاريخ إذا كان بصيغة d/m/Y
        if (strpos($fromDate, '/') !== false) {
            $fromDate = Carbon::createFromFormat('d/m/Y', $fromDate)->format('Y-m-d');
        }
        if (strpos($toDate, '/') !== false) {
            $toDate = Carbon::createFromFormat('d/m/Y', $toDate)->format('Y-m-d');
        }

        $primaryCostCenterIds = $request->input('primary_cost_center_id', []);
        $secondaryCostCenterIds = $request->input('secondary_cost_center_id', []);
        $accountIds = $request->input('journal_account_id', []);
        $branchIds = $request->input('data.branch_id', []);
        $groupBy = $request->input('group_by', 'cost_center');
        $isSummary = $request->input('is_summary', '') === '1';

        // جلب مراكز التكلفة المختارة
        $selectedCostCenterIds = [];

        // إذا تم اختيار مراكز رئيسية
        if (! empty($primaryCostCenterIds) && ! in_array('-1', $primaryCostCenterIds)) {
            $selectedCostCenterIds = array_merge($selectedCostCenterIds, $primaryCostCenterIds);
        }

        // إذا تم اختيار مراكز فرعية
        if (! empty($secondaryCostCenterIds) && ! in_array('-1', $secondaryCostCenterIds)) {
            $selectedCostCenterIds = array_merge($selectedCostCenterIds, $secondaryCostCenterIds);
        }

        // جلب البيانات
        $reportData = [];
        $totals = [
            'total_debit' => 0,
            'total_credit' => 0,
            'balance' => 0,
            'count' => 0,
        ];

        // بناء الاستعلام - مركز التكلفة الآن في جدول التفاصيل
        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
            ->whereBetween('journal_entries.date', [$fromDate, $toDate])
            ->where('journal_entries.status', 1)
            ->whereNotNull('journal_entry_details.cost_center_id');

        // تطبيق فلتر مراكز التكلفة
        if (! empty($selectedCostCenterIds)) {
            $query->whereIn('journal_entry_details.cost_center_id', $selectedCostCenterIds);
        }

        // تطبيق فلتر الحسابات
        if (! empty($accountIds) && ! in_array('', $accountIds)) {
            $query->whereIn('journal_entry_details.account_id', $accountIds);
        }

        // تطبيق فلتر الفروع
        if (! empty($branchIds)) {
            $query->whereIn('journal_entries.branch_id', $branchIds);
        }

        // جلب البيانات مجمعة حسب مركز التكلفة
        if ($groupBy === 'cost_center') {
            $costCenterData = $query->select(
                'journal_entry_details.cost_center_id',
                DB::raw('SUM(journal_entry_details.debit) as total_debit'),
                DB::raw('SUM(journal_entry_details.credit) as total_credit')
            )
                ->groupBy('journal_entry_details.cost_center_id')
                ->get();

            // تجميع البيانات حسب مراكز التكلفة
            $costCenterBalances = [];
            foreach ($costCenterData as $data) {
                $costCenterBalances[$data->cost_center_id] = [
                    'debit' => $data->total_debit,
                    'credit' => $data->total_credit,
                    'balance' => $data->total_credit - $data->total_debit,
                ];
            }

            // جلب تفاصيل القيود لكل مركز تكلفة (من جدول التفاصيل)
            $entriesQuery = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
                ->leftJoin('cost_centers', 'journal_entry_details.cost_center_id', '=', 'cost_centers.id')
                ->whereBetween('journal_entries.date', [$fromDate, $toDate])
                ->where('journal_entries.status', 1)
                ->whereNotNull('journal_entry_details.cost_center_id');

            if (! empty($selectedCostCenterIds)) {
                $entriesQuery->whereIn('journal_entry_details.cost_center_id', $selectedCostCenterIds);
            }

            if (! empty($branchIds)) {
                $entriesQuery->whereIn('journal_entries.branch_id', $branchIds);
            }

            if (! empty($accountIds) && ! in_array('', $accountIds)) {
                $entriesQuery->whereIn('journal_entry_details.account_id', $accountIds);
            }

            // جلب تفاصيل القيود مع مركز التكلفة
            $entries = $entriesQuery->select(
                'journal_entry_details.id as detail_id',
                'journal_entries.id as journal_entry_id',
                'journal_entries.date',
                'journal_entries.reference_number',
                'journal_entries.description as entry_description',
                'journal_entries.reference_type',
                'journal_entry_details.description as detail_description',
                'journal_entry_details.debit',
                'journal_entry_details.credit',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'cost_centers.id as cost_center_id',
                'cost_centers.name as cost_center_name'
            )
                ->orderBy('cost_centers.name')
                ->orderBy('journal_entries.date')
                ->orderBy('journal_entry_details.id')
                ->get();

            // تجميع البيانات
            $groupedEntries = [];
            $runningBalance = [];

            foreach ($entries as $entry) {
                $ccId = $entry->cost_center_id;
                if (! isset($groupedEntries[$ccId])) {
                    $groupedEntries[$ccId] = [
                        'name' => $entry->cost_center_name,
                        'entries' => [],
                        'total_debit' => 0,
                        'total_credit' => 0,
                    ];
                    $runningBalance[$ccId] = 0;
                }

                $entryDebit = $entry->debit ?? 0;
                $entryCredit = $entry->credit ?? 0;

                $runningBalance[$ccId] += ($entryCredit - $entryDebit);

                $groupedEntries[$ccId]['entries'][] = [
                    'id' => $entry->detail_id,
                    'journal_entry_id' => $entry->journal_entry_id,
                    'date' => $entry->date,
                    'reference_number' => $entry->reference_number,
                    'description' => $entry->detail_description ?? $entry->entry_description,
                    'account_code' => $entry->account_code,
                    'account_name' => $entry->account_name,
                    'debit' => $entryDebit,
                    'credit' => $entryCredit,
                    'balance' => $runningBalance[$ccId],
                ];

                $groupedEntries[$ccId]['total_debit'] += $entryDebit;
                $groupedEntries[$ccId]['total_credit'] += $entryCredit;
            }

            $reportData = $groupedEntries;

            // حساب الإجماليات
            foreach ($groupedEntries as $ccData) {
                $totals['total_debit'] += $ccData['total_debit'];
                $totals['total_credit'] += $ccData['total_credit'];
            }
            $totals['balance'] = $totals['total_credit'] - $totals['total_debit'];
            $totals['count'] = count($groupedEntries);
        }

        // جلب أسماء مراكز التكلفة المختارة للعرض
        $selectedPrimaryCostCenters = [];
        $selectedSecondaryCostCenters = [];

        if (! empty($primaryCostCenterIds) && ! in_array('-1', $primaryCostCenterIds)) {
            $selectedPrimaryCostCenters = CostCenter::whereIn('id', $primaryCostCenterIds)->pluck('name')->toArray();
        }

        if (! empty($secondaryCostCenterIds) && ! in_array('-1', $secondaryCostCenterIds)) {
            $selectedSecondaryCostCenters = CostCenter::whereIn('id', $secondaryCostCenterIds)->pluck('name')->toArray();
        }

        return view('account::report.daily_restrictions_reports.cost_centers_report', compact(
            'branches',
            'costCenters',
            'users',
            'accounts',
            'reportData',
            'totals',
            'fromDate',
            'toDate',
            'groupBy',
            'isSummary',
            'selectedPrimaryCostCenters',
            'selectedSecondaryCostCenters'
        ));
    }

    /**
     * جلب بيانات تقرير مراكز التكلفة عبر AJAX
     */
    public function CostCentersReportAjax(Request $request)
    {
        try {
            $fromDate = $request->input('from_date', $this->fiscalYearStart()->format('Y-m-d'));
            $toDate = $request->input('to_date', now()->format('Y-m-d'));
            $costCenterId = $request->input('cost_center');
            $branchId = $request->input('branch');

            // جلب مراكز التكلفة
            $costCentersQuery = CostCenter::query();
            if ($costCenterId) {
                $costCentersQuery->where('id', $costCenterId);
            }
            $costCenters = $costCentersQuery->get();

            $costCentersData = [];

            foreach ($costCenters as $costCenter) {
                // حساب الحركات لكل مركز تكلفة
                $query = JournalEntryDetail::join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entry_details.cost_center_id', $costCenter->id)
                    ->whereBetween('journal_entries.date', [$fromDate, $toDate])
                    ->where('journal_entries.status', 1);

                if ($branchId) {
                    $query->where('journal_entries.branch_id', $branchId);
                }

                $totalDebit = (clone $query)->sum('debit') ?? 0;
                $totalCredit = (clone $query)->sum('credit') ?? 0;

                $costCentersData[] = [
                    'id' => $costCenter->id,
                    'code' => $costCenter->code,
                    'name' => $costCenter->name,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'balance' => $totalDebit - $totalCredit,
                ];
            }

            // حساب الإجماليات
            $totals = [
                'total_debit' => collect($costCentersData)->sum('total_debit'),
                'total_credit' => collect($costCentersData)->sum('total_credit'),
                'balance' => collect($costCentersData)->sum('balance'),
                'count' => count($costCentersData),
            ];

            return response()->json([
                'success' => true,
                'data' => $costCentersData,
                'totals' => $totals,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.GeneralAccounts', 'CostCentersReportAjax');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تقرير ميزان مراجعة مجاميع وأرصدة
     * Trial Balance with Totals and Balances Report
     * يعرض ميزان المراجعة مع الرصيد قبل وحركات الفترة والرصيد بعد والأرصدة الصافية
     */
    public function trialBalanceTotals(Request $request)
    {
        // إعداد البيانات المساعدة للعرض (استعلامات خفيفة)
        $branches = Branch::select('id', 'name')->get();
        $costCenters = CostCenter::select('id', 'name')->get();
        // جلب المستخدمين المرتبطين بموظفين فقط (استبعاد العملاء)
        $users = User::select('id', 'name')
            ->whereNotNull('employee_id')
            ->whereNull('client_id')
            ->get();

        // 1. معالجة نطاق التاريخ
        $dateType = $request->input('date_type', 'this_year');
        $fromDate = $request->input('start_date');
        $toDate = $request->input('end_date');

        $dates = $this->calculateTrialBalanceDateRange($dateType, $fromDate, $toDate);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // 2. الفلاتر
        $branchId = $request->input('branch_id');
        $costCenterId = $request->input('cost_center_id');
        $accountLevel = (int) $request->input('account_level', 0);
        $hideZero = $request->boolean('hide_zero');

        // 3. جلب جميع الحسابات مرة واحدة (الحقول المطلوبة فقط)
        $allAccounts = Account::select('id', 'code', 'name', 'parent_id')
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        // 4. جلب جميع أرصدة الحسابات في استعلامين فقط (الرصيد الافتتاحي + حركات الفترة)
        $accountBalances = $this->fetchAllAccountBalancesOptimized($startDate, $endDate, $branchId, $costCenterId);

        // 5. بناء الشجرة وحساب المجاميع
        $accountsData = $this->buildOptimizedTrialBalanceTree($allAccounts, $accountBalances, $hideZero, $accountLevel);

        // 6. حساب الإجماليات من الحسابات الرئيسية فقط
        $rootAccounts = array_filter($accountsData, fn ($acc) => $acc['level'] == 0);
        $rootCollection = collect($rootAccounts);

        $totals = [
            'opening_balance_debit' => round($rootCollection->sum('opening_balance_debit'), 2),
            'opening_balance_credit' => round($rootCollection->sum('opening_balance_credit'), 2),
            'period_debit' => round($rootCollection->sum('period_debit'), 2),
            'period_credit' => round($rootCollection->sum('period_credit'), 2),
            'closing_balance_debit' => round($rootCollection->sum('closing_balance_debit'), 2),
            'closing_balance_credit' => round($rootCollection->sum('closing_balance_credit'), 2),
            'net_debit' => round($rootCollection->sum('net_debit'), 2),
            'net_credit' => round($rootCollection->sum('net_credit'), 2),
            'accounts_count' => count($accountsData),
            'total_debit' => round($rootCollection->sum('period_debit'), 2),
            'total_credit' => round($rootCollection->sum('period_credit'), 2),
        ];

        // لا نحتاج accounts في الـ view
        $accounts = collect();

        return view('account::report.daily_restrictions_reports.trial_balance_totals', compact(
            'branches',
            'costCenters',
            'users',
            'accounts',
            'accountsData',
            'totals',
            'startDate',
            'endDate'
        ));
    }

    /**
     * جلب بيانات الحسابات الفرعية لميزان المراجعة عبر AJAX
     */
    public function trialBalanceTotalsChildren(Request $request)
    {
        try {
            $parentId = $request->parent_id;
            $fromDate = $request->from_date ?? $this->fiscalYearStart()->format('Y-m-d');
            $toDate = $request->to_date ?? now()->format('Y-m-d');
            $transactionsBranchFilter = $request->transactions_branch;
            $employeeFilter = $request->employee_id;
            $costCenterFilter = $request->cost_center;
            $beforeBalanceType = $request->before_balance_display_type ?? 'balances_only';

            // جلب الحسابات الفرعية المباشرة
            $children = Account::where('parent_id', $parentId)
                ->orderBy('code')
                ->get();

            $childrenData = collect();

            foreach ($children as $account) {
                // حساب الرصيد قبل الفترة
                $beforeQuery = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($fromDate, $transactionsBranchFilter) {
                    $q->where('status', 1)
                        ->where('date', '<', $fromDate);
                    if (! empty($transactionsBranchFilter)) {
                        $q->where('branch_id', $transactionsBranchFilter);
                    }
                })->where('account_id', $account->id);

                if (! empty($costCenterFilter)) {
                    $beforeQuery->where('cost_center_id', $costCenterFilter);
                }

                $beforeDebit = (clone $beforeQuery)->sum('debit');
                $beforeCredit = (clone $beforeQuery)->sum('credit');

                // حساب حركات الفترة
                $periodQuery = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($fromDate, $toDate, $transactionsBranchFilter, $employeeFilter) {
                    $q->where('status', 1)
                        ->whereBetween('date', [$fromDate, $toDate]);
                    if (! empty($transactionsBranchFilter)) {
                        $q->where('branch_id', $transactionsBranchFilter);
                    }
                    if (! empty($employeeFilter)) {
                        $q->where('employee_id', $employeeFilter);
                    }
                })->where('account_id', $account->id);

                if (! empty($costCenterFilter)) {
                    $periodQuery->where('cost_center_id', $costCenterFilter);
                }

                $periodDebit = (clone $periodQuery)->sum('debit');
                $periodCredit = (clone $periodQuery)->sum('credit');

                // حساب الرصيد بعد
                $afterDebit = $beforeDebit + $periodDebit;
                $afterCredit = $beforeCredit + $periodCredit;

                // حساب صافي الأرصدة
                $netBalance = $afterDebit - $afterCredit;
                $netDebit = $netBalance > 0 ? $netBalance : 0;
                $netCredit = $netBalance < 0 ? abs($netBalance) : 0;

                // التحقق إذا كان الحساب له أبناء
                $hasChildren = Account::where('parent_id', $account->id)->exists();

                $childrenData->push([
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'parent_id' => $account->parent_id,
                    'level' => $this->getAccountLevel($account),
                    'has_children' => $hasChildren,
                    'before_debit' => $beforeBalanceType === 'balances_only'
                        ? ($beforeDebit - $beforeCredit > 0 ? $beforeDebit - $beforeCredit : 0)
                        : $beforeDebit,
                    'before_credit' => $beforeBalanceType === 'balances_only'
                        ? ($beforeCredit - $beforeDebit > 0 ? $beforeCredit - $beforeDebit : 0)
                        : $beforeCredit,
                    'period_debit' => $periodDebit,
                    'period_credit' => $periodCredit,
                    'after_debit' => $afterDebit,
                    'after_credit' => $afterCredit,
                    'net_debit' => $netDebit,
                    'net_credit' => $netCredit,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $childrenData->sortBy('code')->values(),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.GeneralAccounts', 'trialBalanceTotalsChildren');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تقرير المصروفات حسب التصنيف
     * Expenses by Category Report
     */
    public function expensesByCategory(Request $request)
    {
        // جلب البيانات للفلاتر
        // الخزائن والبنوك = الحسابات الفرعية تحت حساب الخزينة والبنك في شجرة الحسابات
        $treasuries = Account::where(function ($q) {
            $q->where('parent_id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
                ->orWhere('id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT));
        })
            ->orderBy('code')
            ->get();
        $categories = ExpensesCategory::all();
        $accounts = Account::where('account_type', 'expenses')->orderBy('code')->get();
        $branches = Branch::all();
        $employees = User::whereNotNull('employee_id')->get();
        $suppliers = Supplier::orderBy('trade_name')->get();

        // تحديد الفترة الزمنية
        $fromDate = $request->filled('date_from')
            ? Carbon::parse($request->date_from)
            : Carbon::now()->subMonth();

        $toDate = $request->filled('date_to')
            ? Carbon::parse($request->date_to)
            : Carbon::now();

        // تحديد نوع العرض (ملخص أو تفاصيل)
        $isSummary = ! $request->filled('view_type') || $request->view_type == 'summary';

        // تحديد نوع التجميع
        $groupBy = $request->filled('group_by') ? $request->group_by : 'category';

        // بناء الاستعلام الأساسي
        // المعكوسُ خارج التقرير — انظر Expense::scopeNotReversed()
        $query = Expense::query()
            ->notReversed()
            ->with(['expenses_category', 'treasury', 'account.client', 'branch', 'createdBy', 'Supplier'])
            ->whereBetween('date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        // تطبيق الفلاتر
        // مركز التكلفة موزَّعٌ في سطور قيد السند لا في السند — انظر
        // scopeCostCenter() في AccountingOperations
        $this->scopeCostCenter(
            $query,
            $request->input('cost_center'),
            ReferenceType::EXPENSES->value,
            'reference_id',
            'expenses.id'
        );

        if ($request->filled('treasury_id')) {
            $query->where('treasury_id', $request->treasury_id);
        }

        if ($request->filled('category_id')) {
            $query->where('expenses_category_id', $request->category_id);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('branch_id')) {
            $branchIds = is_array($request->branch_id) ? $request->branch_id : [$request->branch_id];
            $query->whereHas('branch', function ($q) use ($branchIds) {
                $q->whereIn('id', $branchIds);
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('vendor')) {
            $query->where('seller', 'LIKE', '%'.$request->vendor.'%');
        }

        if ($request->filled('employee_id')) {
            $query->where('created_by', $request->employee_id);
        }

        // عند التجميع حسب العميل، إظهار المصروفات المرتبطة بحسابات عملاء فقط
        // حساب العميل هو حساب أبوه حساب العملاء الرئيسي (ID = 18)
        if ($groupBy == 'client') {
            $query->whereHas('account', function ($q) {
                $q->where('parent_id', AccountingSettings::id('clients_account', SystemAccountIds::CLIENTS_ACCOUNT));
            });
        }

        // عند التجميع حسب المورد، إظهار المصروفات المرتبطة بموردين فقط
        if ($groupBy == 'supplier') {
            $query->whereNotNull('supplier_id');
        }

        // عند التجميع حسب الفرع، تضمين فرع الموظف إذا لم يكن للمصروف فرع مباشر
        // سيتم التعامل مع هذا عند التجميع

        // جلب البيانات مرتبة حسب التاريخ
        $expenses = $query->orderBy('date', 'desc')->get();

        // جلب القيود المرتبطة بالمصروفات عبر كود المصروف
        $expenseCodes = $expenses->pluck('code')->filter()->toArray();
        $journalEntries = [];
        if (! empty($expenseCodes)) {
            $journalEntries = JournalEntry::where('reference_type', 'EXP')
                ->whereIn('reference_number', $expenseCodes)
                ->pluck('id', 'reference_number')
                ->toArray();
        }

        // إضافة journal_entry_id لكل مصروف
        foreach ($expenses as $expense) {
            $expense->journal_entry_id = $journalEntries[$expense->code] ?? null;
        }

        // تجهيز بيانات التقرير
        $reportData = [];
        $totals = [
            'amount' => 0,
            'tax' => 0,
            'total_with_tax' => 0,
            'count' => 0,
        ];

        // تحديد مفتاح التجميع
        $getGroupKey = function ($expense) use ($groupBy) {
            switch ($groupBy) {
                case 'category':
                    return $expense->expenses_category_id ?? 0;
                case 'treasury':
                    return $expense->treasury_id ?? 0;
                case 'branch':
                    // استخدام فرع المصروف أولاً، ثم فرع الموظف
                    if ($expense->branch_id) {
                        return $expense->branch_id;
                    }

                    return $expense->createdBy && $expense->createdBy->branch_id
                        ? $expense->createdBy->branch_id
                        : 0;
                case 'employee':
                    return $expense->created_by ?? 0;
                case 'supplier':
                    return $expense->supplier_id ?? 0;
                case 'client':
                    // التجميع حسب حساب العميل (account_id)
                    return $expense->account_id ?? 0;
                case 'vendor':
                    return $expense->seller ?? 'غير محدد';
                default:
                    return $expense->expenses_category_id ?? 0;
            }
        };

        $getGroupName = function ($expense) use ($groupBy) {
            switch ($groupBy) {
                case 'category':
                    return $expense->expenses_category ? $expense->expenses_category->name : 'غير مصنف';
                case 'treasury':
                    return $expense->treasury ? $expense->treasury->name : 'غير محدد';
                case 'branch':
                    // استخدام فرع المصروف أولاً، ثم فرع الموظف
                    if ($expense->branch && $expense->branch->name) {
                        return $expense->branch->name;
                    }
                    if ($expense->createdBy && $expense->createdBy->branch) {
                        return $expense->createdBy->branch->name;
                    }

                    return 'غير محدد';
                case 'employee':
                    return $expense->createdBy ? $expense->createdBy->name : 'غير محدد';
                case 'supplier':
                    return $expense->Supplier ? $expense->Supplier->trade_name : 'غير محدد';
                case 'client':
                    // اسم العميل من حساب العميل - إذا كان للحساب عميل مرتبط نستخدمه، وإلا نستخدم اسم الحساب
                    if ($expense->account && $expense->account->client) {
                        return $expense->account->client->trade_name ?? $expense->account->name;
                    }

                    return $expense->account ? $expense->account->name : 'غير محدد';
                case 'vendor':
                    return $expense->seller ?: 'غير محدد';
                default:
                    return $expense->expenses_category ? $expense->expenses_category->name : 'غير مصنف';
            }
        };

        foreach ($expenses as $expense) {
            $groupKey = $getGroupKey($expense);
            $groupName = $getGroupName($expense);

            // حساب المبالغ
            $amount = $expense->amount ?? 0;
            $tax1 = $expense->tax1_amount ?? 0;
            $tax2 = $expense->tax2_amount ?? 0;
            $totalTax = $tax1 + $tax2;
            $totalWithTax = $amount + $totalTax;

            if (! isset($reportData[$groupKey])) {
                $reportData[$groupKey] = [
                    'name' => $groupName,
                    'amount' => 0,
                    'tax' => 0,
                    'total_with_tax' => 0,
                    'count' => 0,
                    'items' => collect(),
                ];
            }

            $reportData[$groupKey]['amount'] += $amount;
            $reportData[$groupKey]['tax'] += $totalTax;
            $reportData[$groupKey]['total_with_tax'] += $totalWithTax;
            $reportData[$groupKey]['count']++;

            // إضافة تفاصيل المصروف (الكائن مباشرة)
            $reportData[$groupKey]['items']->push($expense);

            // تحديث الإجماليات
            $totals['amount'] += $amount;
            $totals['tax'] += $totalTax;
            $totals['total_with_tax'] += $totalWithTax;
            $totals['count']++;
        }

        // ترتيب البيانات حسب الاسم مع وضع "غير مصنف" في النهاية
        uasort($reportData, function ($a, $b) {
            // وضع "غير مصنف" و "غير محدد" في النهاية
            $unclassifiedNames = ['غير مصنف', 'غير محدد'];
            $aIsUnclassified = in_array($a['name'], $unclassifiedNames);
            $bIsUnclassified = in_array($b['name'], $unclassifiedNames);

            if ($aIsUnclassified && ! $bIsUnclassified) {
                return 1;
            }
            if (! $aIsUnclassified && $bIsUnclassified) {
                return -1;
            }

            return strcmp($a['name'], $b['name']);
        });

        // أنواع التجميع المتاحة
        $groupByOptions = [
            'category' => 'التصنيف',
            'treasury' => 'الخزينة',
            'branch' => 'الفرع',
            'employee' => 'الموظف',
            'supplier' => 'المورد',
            'client' => 'العميل',
            'vendor' => 'البائع',
        ];

        return view('account::report.expenses.expenses_by_category', compact(
            'reportData',
            'totals',
            'treasuries',
            'categories',
            'accounts',
            'branches',
            'employees',
            'suppliers',
            'fromDate',
            'toDate',
            'isSummary',
            'groupBy',
            'groupByOptions'
        ));
    }

    /**
     * تقرير المصروفات حسب الفترة الزمنية (يومي، أسبوعي، شهري، سنوي)
     * Generated by Copilot
     */
    public function expensesByPeriod(Request $request)
    {
        // جلب البيانات للفلاتر
        $treasuries = Account::where(function ($q) {
            $q->where('parent_id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
                ->orWhere('id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT));
        })
            ->orderBy('code')
            ->get();
        $categories = ExpensesCategory::all();
        $branches = Branch::all();
        $employees = User::whereNotNull('employee_id')->get();
        $suppliers = Supplier::orderBy('trade_name')->get();

        // تحديد نوع الفترة (daily, weekly, monthly, yearly)
        $periodType = $request->filled('period_type') ? $request->period_type : 'monthly';

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
            case 'monthly':
                $defaultFrom = $this->fiscalYearStart($now);
                $defaultTo = $now->copy();
                break;
            case 'yearly':
                $defaultFrom = $this->fiscalYearStart(now()->subYears(5));
                $defaultTo = $now->copy();
                break;
            default:
                $defaultFrom = $this->fiscalYearStart($now);
                $defaultTo = $now->copy();
        }

        $fromDate = $request->filled('date_from')
            ? Carbon::parse($request->date_from)
            : $defaultFrom;

        $toDate = $request->filled('date_to')
            ? Carbon::parse($request->date_to)
            : $defaultTo;

        // تحديد نوع العرض (ملخص أو تفاصيل)
        $isSummary = ! $request->filled('view_type') || $request->view_type == 'summary';

        // بناء الاستعلام الأساسي
        // المعكوسُ خارج التقرير — انظر Expense::scopeNotReversed()
        $query = Expense::query()
            ->notReversed()
            ->with(['expenses_category', 'treasury', 'branch', 'createdBy', 'Supplier'])
            ->whereBetween('date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        // تطبيق الفلاتر
        // مركز التكلفة موزَّعٌ في سطور قيد السند لا في السند — انظر
        // scopeCostCenter() في AccountingOperations
        $this->scopeCostCenter(
            $query,
            $request->input('cost_center'),
            ReferenceType::EXPENSES->value,
            'reference_id',
            'expenses.id'
        );

        if ($request->filled('treasury_id')) {
            $query->where('treasury_id', $request->treasury_id);
        }

        if ($request->filled('category_id')) {
            $query->where('expenses_category_id', $request->category_id);
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('branch', function ($q) use ($request) {
                $q->where('id', $request->branch_id);
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('employee_id')) {
            $query->where('created_by', $request->employee_id);
        }

        // جلب البيانات مرتبة حسب التاريخ
        $expenses = $query->orderBy('date', 'desc')->get();

        // جلب القيود المرتبطة بالمصروفات
        $expenseCodes = $expenses->pluck('code')->filter()->toArray();
        $journalEntries = [];
        if (! empty($expenseCodes)) {
            $journalEntries = JournalEntry::where('reference_type', 'EXP')
                ->whereIn('reference_number', $expenseCodes)
                ->pluck('id', 'reference_number')
                ->toArray();
        }

        // إضافة journal_entry_id لكل مصروف
        foreach ($expenses as $expense) {
            $expense->journal_entry_id = $journalEntries[$expense->code] ?? null;
        }

        // تجهيز بيانات التقرير حسب الفترة
        $reportData = [];
        $totals = [
            'amount' => 0,
            'tax' => 0,
            'total_with_tax' => 0,
            'count' => 0,
        ];

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

                    return $arabicDays[$carbonDate->dayOfWeek].' '.$carbonDate->format('d/m/Y');
                case 'weekly':
                    $weekStart = $carbonDate->startOfWeek();
                    $weekEnd = $carbonDate->endOfWeek();

                    return 'الأسبوع '.$carbonDate->weekOfYear.' ('.$weekStart->format('d/m').' - '.$weekEnd->format('d/m/Y').')';
                case 'monthly':
                    $arabicMonths = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

                    return $arabicMonths[$carbonDate->month].' '.$carbonDate->format('Y');
                case 'yearly':
                    return 'سنة '.$carbonDate->format('Y');
                default:
                    return $carbonDate->format('Y-m');
            }
        };

        foreach ($expenses as $expense) {
            $periodKey = $getPeriodKey($expense->date);
            $periodName = $getPeriodName($expense->date);

            // حساب المبالغ
            $amount = $expense->amount ?? 0;
            $tax1 = $expense->tax1_amount ?? 0;
            $tax2 = $expense->tax2_amount ?? 0;
            $totalTax = $tax1 + $tax2;
            $totalWithTax = $amount + $totalTax;

            if (! isset($reportData[$periodKey])) {
                $reportData[$periodKey] = [
                    'name' => $periodName,
                    'date_key' => $periodKey,
                    'amount' => 0,
                    'tax' => 0,
                    'total_with_tax' => 0,
                    'count' => 0,
                    'items' => collect(),
                ];
            }

            $reportData[$periodKey]['amount'] += $amount;
            $reportData[$periodKey]['tax'] += $totalTax;
            $reportData[$periodKey]['total_with_tax'] += $totalWithTax;
            $reportData[$periodKey]['count']++;

            // إضافة تفاصيل المصروف
            $reportData[$periodKey]['items']->push($expense);

            // تحديث الإجماليات
            $totals['amount'] += $amount;
            $totals['tax'] += $totalTax;
            $totals['total_with_tax'] += $totalWithTax;
            $totals['count']++;
        }

        // ترتيب البيانات حسب التاريخ تنازلياً
        krsort($reportData);

        // تحضير بيانات الرسم البياني
        $chartData = [
            'labels' => [],
            'amounts' => [],
            'taxes' => [],
            'totals' => [],
        ];

        // ترتيب البيانات تصاعدياً للرسم البياني
        $sortedForChart = $reportData;
        ksort($sortedForChart);

        foreach ($sortedForChart as $period) {
            $chartData['labels'][] = $period['name'];
            $chartData['amounts'][] = round($period['amount'], 2);
            $chartData['taxes'][] = round($period['tax'], 2);
            $chartData['totals'][] = round($period['total_with_tax'], 2);
        }

        // أنواع الفترات المتاحة
        $periodTypes = [
            'daily' => 'يومي',
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            'yearly' => 'سنوي',
        ];

        return view('account::report.expenses.expenses_by_period', compact(
            'reportData',
            'totals',
            'chartData',
            'treasuries',
            'categories',
            'branches',
            'employees',
            'suppliers',
            'fromDate',
            'toDate',
            'isSummary',
            'periodType',
            'periodTypes'
        ));
    }

    /**
     * تقرير سندات القبض حسب التصنيف
     * Generated by Copilot
     */
    public function receiptsByCategory(Request $request)
    {
        // جلب البيانات للفلاتر
        // الخزائن والبنوك = الحسابات الفرعية تحت حساب الخزينة والبنك في شجرة الحسابات
        $treasuries = Account::where(function ($q) {
            $q->where('parent_id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
                ->orWhere('id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT));
        })
            ->orderBy('code')
            ->get();
        $categories = ReceiptCategory::all();
        $accounts = Account::whereIn('account_type', ['income', 'revenue'])->orderBy('code')->get();
        $branches = Branch::all();
        $employees = User::whereNotNull('employee_id')->get();
        $clients = Client::orderBy('trade_name')->get();

        // تحديد الفترة الزمنية
        $fromDate = $request->filled('date_from')
            ? Carbon::parse($request->date_from)
            : Carbon::now()->subMonth();

        $toDate = $request->filled('date_to')
            ? Carbon::parse($request->date_to)
            : Carbon::now();

        // تحديد نوع العرض (ملخص أو تفاصيل)
        $isSummary = ! $request->filled('view_type') || $request->view_type == 'summary';

        // تحديد نوع التجميع
        $groupBy = $request->filled('group_by') ? $request->group_by : 'category';

        // بناء الاستعلام الأساسي
        $query = Receipt::query()
            ->with(['incomes_category', 'treasury', 'account.client', 'branch', 'user'])
            ->whereBetween('date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')])
            ->where('status', 1); // فقط السندات النشطة

        // تطبيق الفلاتر
        $this->scopeCostCenter(
            $query,
            $request->input('cost_center'),
            ReferenceType::CUSTOMER_COLLECTION->value,
            'reference_number',
            'receipts.code'
        );

        if ($request->filled('treasury_id')) {
            $query->where('treasury_id', $request->treasury_id);
        }

        if ($request->filled('category_id')) {
            $query->where('incomes_category_id', $request->category_id);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('branch_id')) {
            $branchIds = is_array($request->branch_id) ? $request->branch_id : [$request->branch_id];
            $query->where(function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds)
                    ->orWhereHas('user', function ($uq) use ($branchIds) {
                        $uq->whereIn('branch_id', $branchIds);
                    });
            });
        }

        // العميل يتم الوصول إليه عبر account.client_id
        if ($request->filled('client_id')) {
            $query->whereHas('account', function ($q) use ($request) {
                $q->where('client_id', $request->client_id);
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('created_by', $request->employee_id);
        }

        // عند التجميع حسب العميل، إظهار السندات المرتبطة بحسابات عملاء فقط
        if ($groupBy == 'client') {
            $query->whereHas('account', function ($q) {
                $q->whereNotNull('client_id');
            });
        }

        // جلب البيانات مرتبة حسب التاريخ
        $receipts = $query->orderBy('date', 'desc')->get();

        // جلب القيود المرتبطة بالسندات عبر كود السند
        $receiptCodes = $receipts->pluck('code')->filter()->toArray();
        $journalEntries = [];
        if (! empty($receiptCodes)) {
            // البحث بـ REC (CUSTOMER_COLLECTION) أو RV (RECEIPT_VOUCHER)
            $journalEntries = JournalEntry::whereIn('reference_type', ['REC', 'RV'])
                ->whereIn('reference_number', $receiptCodes)
                ->pluck('id', 'reference_number')
                ->toArray();
        }

        // إضافة journal_entry_id لكل سند
        foreach ($receipts as $receipt) {
            $receipt->journal_entry_id = $journalEntries[$receipt->code] ?? null;
        }

        // تجهيز بيانات التقرير
        $reportData = [];
        $totals = [
            'amount' => 0,
            'tax' => 0,
            'total_with_tax' => 0,
            'count' => 0,
        ];

        // تحديد مفتاح التجميع
        $getGroupKey = function ($receipt) use ($groupBy) {
            switch ($groupBy) {
                case 'category':
                    return $receipt->incomes_category_id ?? 0;
                case 'treasury':
                    return $receipt->treasury_id ?? 0;
                case 'branch':
                    // استخدام فرع السند أولاً، ثم فرع الموظف
                    if ($receipt->branch_id) {
                        return $receipt->branch_id;
                    }

                    return $receipt->user && $receipt->user->branch_id
                        ? $receipt->user->branch_id
                        : 0;
                case 'employee':
                    return $receipt->created_by ?? 0;
                case 'client':
                    // العميل عبر الحساب
                    return $receipt->account && $receipt->account->client_id ? $receipt->account->client_id : 0;
                default:
                    return $receipt->incomes_category_id ?? 0;
            }
        };

        $getGroupName = function ($receipt) use ($groupBy) {
            switch ($groupBy) {
                case 'category':
                    return $receipt->incomes_category ? $receipt->incomes_category->name : 'غير مصنف';
                case 'treasury':
                    return $receipt->treasury ? $receipt->treasury->name : 'غير محدد';
                case 'branch':
                    // استخدام فرع السند أولاً، ثم فرع الموظف
                    if ($receipt->branch && $receipt->branch->name) {
                        return $receipt->branch->name;
                    }
                    if ($receipt->user && $receipt->user->branch) {
                        return $receipt->user->branch->name;
                    }

                    return 'غير محدد';
                case 'employee':
                    return $receipt->user ? $receipt->user->name : 'غير محدد';
                case 'client':
                    // العميل عبر الحساب
                    if ($receipt->account && $receipt->account->client) {
                        $client = $receipt->account->client;

                        return $client->trade_name ?? ($client->first_name.' '.$client->last_name);
                    }

                    return $receipt->account ? $receipt->account->name : 'غير محدد';
                default:
                    return $receipt->incomes_category ? $receipt->incomes_category->name : 'غير مصنف';
            }
        };

        foreach ($receipts as $receipt) {
            $groupKey = $getGroupKey($receipt);
            $groupName = $getGroupName($receipt);

            // حساب المبالغ
            $amount = $receipt->amount ?? 0;
            $tax1 = $receipt->tax1_amount ?? 0;
            $tax2 = $receipt->tax2_amount ?? 0;
            $totalTax = $tax1 + $tax2;
            $totalWithTax = $amount + $totalTax;

            if (! isset($reportData[$groupKey])) {
                $reportData[$groupKey] = [
                    'name' => $groupName,
                    'amount' => 0,
                    'tax' => 0,
                    'total_with_tax' => 0,
                    'count' => 0,
                    'items' => collect(),
                ];
            }

            $reportData[$groupKey]['amount'] += $amount;
            $reportData[$groupKey]['tax'] += $totalTax;
            $reportData[$groupKey]['total_with_tax'] += $totalWithTax;
            $reportData[$groupKey]['count']++;

            // إضافة تفاصيل السند (الكائن مباشرة)
            $reportData[$groupKey]['items']->push($receipt);

            // تحديث الإجماليات
            $totals['amount'] += $amount;
            $totals['tax'] += $totalTax;
            $totals['total_with_tax'] += $totalWithTax;
            $totals['count']++;
        }

        // ترتيب البيانات حسب الاسم مع وضع "غير مصنف" في النهاية
        uasort($reportData, function ($a, $b) {
            // وضع "غير مصنف" و "غير محدد" في النهاية
            $unclassifiedNames = ['غير مصنف', 'غير محدد'];
            $aIsUnclassified = in_array($a['name'], $unclassifiedNames);
            $bIsUnclassified = in_array($b['name'], $unclassifiedNames);

            if ($aIsUnclassified && ! $bIsUnclassified) {
                return 1;
            }
            if (! $aIsUnclassified && $bIsUnclassified) {
                return -1;
            }

            return strcmp($a['name'], $b['name']);
        });

        // أنواع التجميع المتاحة
        $groupByOptions = [
            'category' => 'التصنيف',
            'treasury' => 'الخزينة',
            'branch' => 'الفرع',
            'employee' => 'الموظف',
            'client' => 'العميل',
        ];

        return view('account::report.receipts.receipts_by_category', compact(
            'reportData',
            'totals',
            'treasuries',
            'categories',
            'accounts',
            'branches',
            'employees',
            'clients',
            'fromDate',
            'toDate',
            'isSummary',
            'groupBy',
            'groupByOptions'
        ));
    }

    /**
     * تقرير سندات القبض حسب الفترة الزمنية
     * Generated by Copilot
     */
    public function receiptsByPeriod(Request $request)
    {
        // جلب البيانات للفلاتر
        $treasuries = Account::where(function ($q) {
            $q->where('parent_id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('id', AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT))
                ->orWhere('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
                ->orWhere('id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT));
        })
            ->orderBy('code')
            ->get();
        $categories = ReceiptCategory::all();
        $branches = Branch::all();
        $employees = User::whereNotNull('employee_id')->get();
        $clients = Client::orderBy('trade_name')->get();

        // تحديد نوع الفترة (daily, weekly, monthly, yearly)
        $periodType = $request->filled('period_type') ? $request->period_type : 'monthly';

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
            case 'monthly':
                $defaultFrom = $this->fiscalYearStart($now);
                $defaultTo = $now->copy();
                break;
            case 'yearly':
                $defaultFrom = $this->fiscalYearStart(now()->subYears(5));
                $defaultTo = $now->copy();
                break;
            default:
                $defaultFrom = $this->fiscalYearStart($now);
                $defaultTo = $now->copy();
        }

        $fromDate = $request->filled('date_from')
            ? Carbon::parse($request->date_from)
            : $defaultFrom;

        $toDate = $request->filled('date_to')
            ? Carbon::parse($request->date_to)
            : $defaultTo;

        // تحديد نوع العرض (ملخص أو تفاصيل)
        $isSummary = ! $request->filled('view_type') || $request->view_type == 'summary';

        // بناء الاستعلام الأساسي
        $query = Receipt::query()
            ->with(['incomes_category', 'treasury', 'branch', 'user', 'account.client'])
            ->whereBetween('date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        // تطبيق الفلاتر
        $this->scopeCostCenter(
            $query,
            $request->input('cost_center'),
            ReferenceType::CUSTOMER_COLLECTION->value,
            'reference_number',
            'receipts.code'
        );

        if ($request->filled('treasury_id')) {
            $query->where('treasury_id', $request->treasury_id);
        }

        if ($request->filled('category_id')) {
            $query->where('incomes_category_id', $request->category_id);
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('branch', function ($q) use ($request) {
                $q->where('id', $request->branch_id);
            });
        }

        if ($request->filled('client_id')) {
            $query->whereHas('account', function ($q) use ($request) {
                $q->where('client_id', $request->client_id);
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('created_by', $request->employee_id);
        }

        // جلب البيانات مرتبة حسب التاريخ
        $receipts = $query->orderBy('date', 'desc')->get();

        // جلب القيود المرتبطة بسندات القبض
        $receiptCodes = $receipts->pluck('code')->filter()->toArray();
        $journalEntries = [];
        if (! empty($receiptCodes)) {
            $journalEntries = JournalEntry::whereIn('reference_type', ['REC', 'RV'])
                ->whereIn('reference_number', $receiptCodes)
                ->pluck('id', 'reference_number')
                ->toArray();
        }

        // إضافة journal_entry_id لكل سند قبض
        foreach ($receipts as $receipt) {
            $receipt->journal_entry_id = $journalEntries[$receipt->code] ?? null;
        }

        // تجهيز بيانات التقرير حسب الفترة
        $reportData = [];
        $totals = [
            'amount' => 0,
            'tax' => 0,
            'total_with_tax' => 0,
            'count' => 0,
        ];

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

                    return $arabicDays[$carbonDate->dayOfWeek].' '.$carbonDate->format('d/m/Y');
                case 'weekly':
                    $weekStart = $carbonDate->startOfWeek();
                    $weekEnd = $carbonDate->endOfWeek();

                    return 'الأسبوع '.$carbonDate->weekOfYear.' ('.$weekStart->format('d/m').' - '.$weekEnd->format('d/m/Y').')';
                case 'monthly':
                    $arabicMonths = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

                    return $arabicMonths[$carbonDate->month].' '.$carbonDate->format('Y');
                case 'yearly':
                    return 'سنة '.$carbonDate->format('Y');
                default:
                    return $carbonDate->format('Y-m');
            }
        };

        foreach ($receipts as $receipt) {
            $periodKey = $getPeriodKey($receipt->date);
            $periodName = $getPeriodName($receipt->date);

            /*
                **`tax_amount` عمودٌ لا وجود له في `receipts`.**

                فالضريبة تُقرأ صفراً دائماً، ويخرج «الإجمالي شامل الضريبة»
                في تقرير الفترات مساوياً للمبلغ قبلها — بينما التقاريرُ
                الأخرى تقرأ `tax1_amount` و`tax2_amount` وتصيب.

                والإجمالي `total_amount` محسوبٌ عند الحفظ؛ ويُسقَط إلى الجمع
                للسجلّات القديمة التي حُفظت قبل أن يُحسب.
            */
            $amount = (float) ($receipt->amount ?? 0);
            $tax = (float) ($receipt->tax1_amount ?? 0) + (float) ($receipt->tax2_amount ?? 0);
            $totalWithTax = (float) ($receipt->total_amount ?: ($amount + $tax));

            if (! isset($reportData[$periodKey])) {
                $reportData[$periodKey] = [
                    'name' => $periodName,
                    'date_key' => $periodKey,
                    'amount' => 0,
                    'tax' => 0,
                    'total_with_tax' => 0,
                    'count' => 0,
                    'items' => collect(),
                ];
            }

            $reportData[$periodKey]['amount'] += $amount;
            $reportData[$periodKey]['tax'] += $tax;
            $reportData[$periodKey]['total_with_tax'] += $totalWithTax;
            $reportData[$periodKey]['count']++;

            // إضافة تفاصيل سند القبض
            $reportData[$periodKey]['items']->push($receipt);

            // تحديث الإجماليات
            $totals['amount'] += $amount;
            $totals['tax'] += $tax;
            $totals['total_with_tax'] += $totalWithTax;
            $totals['count']++;
        }

        // ترتيب البيانات حسب التاريخ تنازلياً
        krsort($reportData);

        // تحضير بيانات الرسم البياني
        $chartData = [
            'labels' => [],
            'amounts' => [],
            'taxes' => [],
            'totals' => [],
        ];

        // ترتيب البيانات تصاعدياً للرسم البياني
        $sortedForChart = $reportData;
        ksort($sortedForChart);

        foreach ($sortedForChart as $period) {
            $chartData['labels'][] = $period['name'];
            $chartData['amounts'][] = round($period['amount'], 2);
            $chartData['taxes'][] = round($period['tax'], 2);
            $chartData['totals'][] = round($period['total_with_tax'], 2);
        }

        // أنواع الفترات المتاحة
        $periodTypes = [
            'daily' => 'يومي',
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            'yearly' => 'سنوي',
        ];

        return view('account::report.receipts.receipts_by_period', compact(
            'reportData',
            'totals',
            'chartData',
            'treasuries',
            'categories',
            'branches',
            'employees',
            'clients',
            'fromDate',
            'toDate',
            'isSummary',
            'periodType',
            'periodTypes'
        ));
    }
}
