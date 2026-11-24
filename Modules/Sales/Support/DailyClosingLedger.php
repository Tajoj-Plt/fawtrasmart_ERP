<?php

namespace Modules\Sales\Support;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Enums\VisitStatus;
use App\Models\Account;
use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\CreditNotification;
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentsProcess;
use App\Models\ProductDetails;
use App\Models\Receipt;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Models\Visit;
use App\Services\AttendanceProcessorService;
use App\Support\AccountingSettings;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Seeders\AccountingSettingsSeeder;
use Illuminate\Support\Collection;

/**
 * أرقام الإقفال اليومي للموظف — مصدرٌ واحد لِما يقوله النظام عن يومه.
 *
 * **لماذا صنفٌ مستقل؟**
 *
 * كان بناءُ تفاصيل الإقفال مكتوباً ثلاث مرّات حرفياً في المتحكّم:
 * في `print()` وفي `printReport()` وفي `bulkPrintReport()` — نحو مئتَي
 * سطر مكرّرة ثلاثاً. فأيّ تصحيح في احتساب المرتجع النقدي أو في تمييز سند
 * القبض عن فاتورة البيع كان يلزم تطبيقه ثلاثاً، وقد أُغفل فعلاً: نسخةُ
 * `print()` وحدها تضع اسم العميل في سطر المصروف، والنسختان الأخريان
 * تُسقطانه. فالمطبوعُ الواحد يخرج بثلاث صور.
 *
 * صار البناء هنا مرّةً واحدة، والمتحكّم ينادي `breakdown()` ويعرض.
 *
 * **الحسابات من الإعدادات لا من الشيفرة**
 *
 * كانت أرقام الحسابات تُقرأ من ثوابت `SystemAccountIds` مباشرة، فما يربطه
 * المحاسب في شاشة «الحسابات المحاسبية» لا أثر له في الإقفال: يغيّر حساب
 * المبيعات في الشاشة، ويبقى الإقفال يقرأ الحساب ٤٤. صارت تُقرأ عبر
 * `AccountingSettings`.
 *
 * ويبقى الثابت احتياطاً هنا — بخلاف
 * `Modules\Sales\Http\Controllers\Concerns\ResolvesAccountingAccounts`
 * التي تمنع الاحتياط عمداً. الفرق أنّ تلك تخدم *مولّدات القيود*: قيدٌ
 * بحسابٍ مفقود خطأٌ يجب أن يُصرَّح به لا أن يُرحَّل صامتاً. أمّا الإقفال
 * فيقرأ ولا يكتب قيداً واحداً، وشاشةُ مراجعةٍ تُفرَّغ لأنّ مفتاحاً لم
 * يُربَط بعد أسوأ من شاشةٍ تعمل بالثابت الذي عمل به النظام حتى اليوم.
 *
 * @see AccountingSettingsSeeder مفاتيح الحسابات وقيمها الأولى
 */
class DailyClosingLedger
{
    /**
     * الإجماليات التي يقارن بها الموظفُ إقفاله — كما يراها النظام.
     *
     * @return array{
     *     sales: float, cash_sales: float, receipts: float, cash_refunds: float,
     *     collections: float, returns: float, expenses: float,
     *     stock_total: float, stock_products: array, visits: array
     * }
     */
    public function systemTotals(int $employeeId, Carbon $from, Carbon $to): array
    {
        $date = $from->format('Y-m-d');
        $sales = $this->grossSales($employeeId, $from, $to);
        $cashSales = $this->cashSales($employeeId, $from, $to);
        $receipts = $this->receipts($employeeId, $from, $to);
        $cashRefunds = $this->cashRefunds($employeeId, $date);
        $stockProducts = $this->stockProducts($employeeId);

        return [
            'sales' => round($sales, 2),
            'cash_sales' => round($cashSales, 2),
            'receipts' => round($receipts, 2),
            'cash_refunds' => round($cashRefunds, 2),
            'collections' => round($cashSales + $receipts - $cashRefunds, 2),
            'returns' => round($this->returns($employeeId, $from, $to), 2),
            'expenses' => round($this->expenses($employeeId, $from, $to), 2),
            'stock_total' => round(collect($stockProducts)->sum('system_quantity'), 2),
            'stock_products' => $stockProducts,
            'visits' => $visits = $this->visits($employeeId, $date),
            'work' => $this->work($employeeId, $date, $visits),
        ];
    }

    /**
     * هل لهذا الموظف أيّ حركة في اليوم؟ — يُتخطّى في الإقفال الجماعي من لا حركة له.
     */
    public function hasActivity(array $totals): bool
    {
        return ($totals['sales'] + $totals['cash_sales'] + $totals['receipts']
            + ($totals['cash_refunds'] ?? 0) + $totals['returns'] + $totals['expenses']) > 0;
    }

    /**
     * تفاصيل يوم الموظف سطراً سطراً — للمطبوعات ولشاشة المراجعة.
     *
     * هذه هي الدالّة التي كانت مكرّرة ثلاثاً. مخرجُها الآن *مجموعُ* ما
     * كانت النسخ الثلاث تبنيه، فلا تفقد أيّ شاشةٍ حقلاً كانت تعرضه:
     * `has_return` على سطور البيع (كانت في نسختين)، واسم العميل على سطر
     * المصروف (كان في نسخةٍ واحدة).
     *
     * @return array{
     *     date: string, salesEntries: array, returnEntries: array,
     *     cashSalesEntries: array, receiptEntries: array, cashRefundEntries: array,
     *     expenseEntries: array, stockProducts: array, totalSales: float,
     *     totalReturns: float, totalCashSales: float, totalReceipts: float,
     *     totalCashRefunds: float, totalCollections: float, netSales: float,
     *     totalExpensesDetail: float, systemStock: float, visits: array
     * }
     */
    public function breakdown(int $employeeId, string $date, ?array $cancelledExpenseRefs = null): array
    {
        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $treasuryAndBankAccounts = $this->treasuryAndBankAccountIds();
        $cancelledExpenseRefs ??= $this->cancelledExpenseRefs();

        $salesAccountId = $this->accountId('sales', SystemAccountIds::SALES);
        $salesReturnsAccountId = $this->accountId('sales_returns', SystemAccountIds::SALES_RETURNS);

        $journalEntries = JournalEntry::where('created_by_employee', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 1)
            ->with(['client', 'details.account'])
            ->orderBy('id')
            ->get();

        // ── المباع نقداً: الفواتير المسدَّدة بالكامل ─────────────────────
        $cashSalesInvoices = Invoice::where('created_by', $employeeId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->where('payment_status', 1)
            ->whereIn('type', ['normal', 'pos'])
            ->with(['client'])
            ->orderBy('id')
            ->get();

        $cashSalesEntries = [];
        $totalCashSales = 0;
        $cashInvoiceIds = $cashSalesInvoices->pluck('id')->toArray();
        $settledElsewhere = $this->nonCashSettlements($cashInvoiceIds);

        foreach ($cashSalesInvoices as $invoice) {
            // ما سدّده سندُ قبضٍ يُعدّ في «سندات القبض» لا هنا
            $amount = max(0, (float) $invoice->grand_total - ($settledElsewhere[$invoice->id] ?? 0));

            if ($amount < 0.005) {
                continue;
            }

            $cashSalesEntries[] = [
                'reference' => $invoice->code ?? $invoice->invoice_number ?? $invoice->id,
                'client' => $this->partyName($invoice->client),
                'description' => 'فاتورة '.($invoice->type == 'pos' ? 'نقطة بيع' : 'عادية'),
                'amount' => $amount,
            ];
            $totalCashSales += $amount;
        }

        // ── المرتجع النقدي: ما صُرف للعميل من الخزينة في اليوم ─────────────
        // كان يُطرح من «المباع نقداً» صامتاً، فيوم بلا بيعٍ نقدي وفيه مرتجعٌ
        // مصروفٌ يُخرج المباعَ نقداً سالباً (٠ − ٢٢٤ = −٢٢٤) — رقماً لا يستطيع
        // الموظفُ إدخاله في نموذجٍ لا يقبل السالب، فلا يُقفل يومَه أبداً.
        // صار بنداً ظاهراً وحده، ويُطرح من إجمالي التحصيل لا من المباع نقداً.
        $cashRefundEntries = $this->cashRefundEntries($employeeId, $date);
        $totalCashRefunds = (float) collect($cashRefundEntries)->sum('amount');

        // ── فرز القيود: بيعٌ، أم مردودُ بيع، أم سند قبض ─────────────────
        $salesEntries = [];
        $returnEntries = [];
        $receiptEntries = [];
        $totalSales = 0;
        $totalReturns = 0;
        $totalReceipts = 0;

        $cancelledReceiptEntryIds = SalesLedger::cancelledEntryIds();

        foreach ($journalEntries as $entry) {
            $details = $entry->details;
            $clientName = $this->partyName($entry->client);

            $salesCredit = $details->where('account_id', $salesAccountId)->where('credit', '>', 0);
            $salesReturnsDebit = $details->where('account_id', $salesReturnsAccountId)->where('debit', '>', 0);
            $treasuryDebits = $details->whereIn('account_id', $treasuryAndBankAccounts)->where('debit', '>', 0);
            $treasuryCredits = $details->whereIn('account_id', $treasuryAndBankAccounts)->where('credit', '>', 0);

            $hasSalesCredit = $salesCredit->isNotEmpty();
            $hasSalesReturns = $salesReturnsDebit->isNotEmpty();
            $hasTreasuryDebit = $treasuryDebits->isNotEmpty();
            $hasTreasuryCredit = $treasuryCredits->isNotEmpty();

            if ($hasSalesCredit) {
                $amount = $salesCredit->sum('credit');
                $salesEntries[] = [
                    'reference' => $entry->reference_number,
                    'client' => $clientName,
                    'description' => $entry->description ?? '-',
                    'amount' => $amount,
                ];
                $totalSales += $amount;
            }

            if ($hasSalesReturns) {
                $amount = $salesReturnsDebit->sum('debit');
                $returnEntries[] = [
                    'reference' => $entry->reference_number,
                    'client' => $clientName,
                    'description' => $entry->description ?? '-',
                    'amount' => $amount,
                ];
                $totalReturns += $amount;
            }

            // سند قبض: دخولٌ للخزينة وحده — لا بيعٌ معه ولا خروج ولا مردود،
            // وليس قيدَ فاتورةٍ نقدية عُدَّت أصلاً في «المباع نقداً»
            if ($hasTreasuryDebit && ! $hasSalesCredit && ! $hasTreasuryCredit && ! $hasSalesReturns) {
                if ($entry->invoice_id && in_array($entry->invoice_id, $cashInvoiceIds)) {
                    continue;
                }

                // سندٌ أُلغي: قيدُه الأصليُّ باقٍ مرحَّلاً وعكسيُّه بجانبه، والسندُ نفسُه حُذف —
                // فلا يُعدّ في المطبوعة كما لا يعدّه الإقفالُ (`receipts()` يجمع السندات القائمة)
                if (in_array((int) $entry->id, $cancelledReceiptEntryIds, true)) {
                    continue;
                }

                $amount = $treasuryDebits->sum('debit');
                $receiptEntries[] = [
                    'reference' => $entry->reference_number,
                    'client' => $clientName,
                    'description' => $entry->description ?? '-',
                    'amount' => $amount,
                ];
                $totalReceipts += $amount;
            }
        }

        // ── وسمُ ما ارتدّ من فواتير اليوم ────────────────────────────────
        $returnedOriginalInvoiceIds = $this->returnedOriginalInvoiceIds($employeeId, $startDate, $endDate);

        foreach ($salesEntries as &$saleEntry) {
            $je = $journalEntries->firstWhere('reference_number', $saleEntry['reference']);
            $invoiceId = $je ? (int) $je->invoice_id : null;
            $saleEntry['has_return'] = $invoiceId && in_array($invoiceId, $returnedOriginalInvoiceIds);
        }
        unset($saleEntry);

        foreach ($cashSalesInvoices as $invoice) {
            $ref = $invoice->code ?? $invoice->invoice_number ?? $invoice->id;

            foreach ($cashSalesEntries as &$cashEntry) {
                if ($cashEntry['reference'] == $ref && ! isset($cashEntry['has_return'])) {
                    $cashEntry['has_return'] = in_array((int) $invoice->id, $returnedOriginalInvoiceIds);
                }
            }
            unset($cashEntry);
        }

        $expenses = $this->expenseEntries($employeeId, $startDate, $endDate, $treasuryAndBankAccounts, $cancelledExpenseRefs);
        $stockProducts = $this->stockProducts($employeeId);

        return [
            'date' => $date,
            'salesEntries' => $salesEntries,
            'returnEntries' => $returnEntries,
            'cashSalesEntries' => $cashSalesEntries,
            'receiptEntries' => $receiptEntries,
            'cashRefundEntries' => $cashRefundEntries,
            'expenseEntries' => $expenses['entries'],
            'stockProducts' => $stockProducts,
            'totalSales' => $totalSales,
            'totalReturns' => $totalReturns,
            'totalCashSales' => $totalCashSales,
            'totalReceipts' => $totalReceipts,
            'totalCashRefunds' => $totalCashRefunds,
            'totalCollections' => $totalCashSales + $totalReceipts - $totalCashRefunds,
            'netSales' => $totalSales - $totalReturns,
            'totalExpensesDetail' => $expenses['total'],
            'systemStock' => collect($stockProducts)->sum('system_quantity'),
            'visits' => $visits = $this->visits($employeeId, $date),
            'work' => $this->work($employeeId, $date, $visits),
        ];
    }

    /**
     * أرقام المصروفات الملغاة — تُجلب مرّةً وتُمرَّر إلى `breakdown()` في الحلقات.
     *
     * @return array<int, string>
     */
    public function cancelledExpenseRefs(): array
    {
        return JournalEntry::where('reference_number', 'LIKE', '%-REV')
            ->where('reference_type', ReferenceType::EXPENSES)
            ->pluck('reference_number')
            ->map(fn ($ref) => str_replace('-REV', '', $ref))
            ->toArray();
    }

    /**
     * حسابات الخزائن والبنوك كما رُبطت في شاشة «الحسابات المحاسبية».
     *
     * @return array<int, int>
     */
    public function treasuryAndBankAccountIds(): array
    {
        return Account::whereIn('parent_id', [
            $this->accountId('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT),
            $this->accountId('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT),
        ])->pluck('id')->toArray();
    }

    /**
     * تفاصيل منتجات مستودع الموظف مع كمياتها.
     *
     * @return array<int, array{product_id: int, product_name: string, system_quantity: float}>
     */
    public function stockProducts(int $employeeId): array
    {
        $user = User::find($employeeId);

        if (! $user || ! $user->employee_id) {
            return [];
        }

        $warehouseIds = DefaultWarehouses::where('employee_id', $user->employee_id)
            ->pluck('storehouse_id')
            ->toArray();

        if (empty($warehouseIds)) {
            return [];
        }

        return ProductDetails::whereIn('store_house_id', $warehouseIds)
            ->where('quantity', '>', 0)
            ->selectRaw('product_id, SUM(quantity) as total_quantity')
            ->groupBy('product_id')
            ->with('product:id,name')
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => optional($item->product)->name ?? 'منتج #'.$item->product_id,
                'system_quantity' => $item->total_quantity,
            ])
            ->values()
            ->toArray();
    }

    // ══════════════════════ الإجماليات ══════════════════════

    /**
     * إجمالي المبيعات بلا طرح المرتجعات — تُحسب وحدها في `returns()`.
     */
    public function grossSales(int $employeeId, Carbon $from, Carbon $to): float
    {
        $salesData = JournalEntryDetail::whereHas('journalEntry', function ($query) use ($from, $to, $employeeId) {
            $query->whereBetween('date', [$from, $to])
                ->where('created_by_employee', $employeeId);
        })
            ->where('account_id', $this->accountId('sales', SystemAccountIds::SALES))
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        return (float) (($salesData->total_credit ?? 0) - ($salesData->total_debit ?? 0));
    }

    /**
     * المباع نقداً — الفواتير المسدَّدة بالكامل عند البيع. لا يقلّ عن الصفر.
     *
     * المرتجعُ النقدي لا يُطرح منه — له رقمُه في `cashRefunds()` ويُطرح من
     * إجمالي التحصيل (راجع تعليق `breakdown()`).
     */
    public function cashSales(int $employeeId, Carbon $from, Carbon $to): float
    {
        $date = $from->format('Y-m-d');

        $invoices = Invoice::where('created_by', $employeeId)
            ->whereDate('invoice_date', $date)
            ->where('payment_status', 1)
            ->whereIn('type', ['normal', 'pos'])
            ->pluck('grand_total', 'id');

        $settledElsewhere = $this->nonCashSettlements($invoices->keys()->all());

        return (float) $invoices
            ->map(fn ($grandTotal, $id) => max(0, (float) $grandTotal - ($settledElsewhere[$id] ?? 0)))
            ->sum();
    }

    /**
     * ما سُدِّد من كلّ فاتورة بغير نقدِ البيع — سندُ قبضٍ أو إشعارٌ دائن.
     *
     * «المباع نقداً» هو الفاتورةُ التي صارت «مدفوعة»، و`payment_status`
     * لا يفرّق بين فاتورةٍ دُفعت عند البيع وفاتورةٍ آجلةٍ سدّدها سندُ قبضٍ
     * لاحقاً. فالفاتورةُ الآجلة المسدَّدة بسند كانت تُعدّ مرّتين: بقيمتها
     * في «المباع نقداً»، وبالسند في «سندات القبض» — ١٩٢ فاتورة و١٩٢ سنداً
     * تخرج تحصيلاً ٣٨٤. يُطرح هنا نصيبُ السند من الفاتورة، فيبقى في
     * التحصيل مرّةً واحدة: في «سندات القبض».
     *
     * سطرُ السداد من السند (`IncomesController::applyPaymentToInvoices`)
     * يحمل في `reference_number` معرّفَ السند؛ ويُشترط أن يكون حسابُ السند
     * حسابَ عميل الفاتورة نفسه — فمعرّفاتُ `receipts` و`payments_process`
     * تتقاطع بالآلاف، والرقمُ وحده يلتقط سداداتٍ ليست من سند.
     * وتسويةُ الإشعار الدائن القديمة (`credit_note_settlement`) ليست نقداً
     * أصلاً، والإشعارُ نفسه يُعدّ في «المرتجعات».
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<int, float> معرّف الفاتورة => المبلغ
     */
    protected function nonCashSettlements(array $invoiceIds): array
    {
        if (empty($invoiceIds)) {
            return [];
        }

        return PaymentsProcess::query()
            ->whereIn('payments_process.invoice_id', $invoiceIds)
            ->where(fn ($q) => $q->whereNull('payments_process.payment_status')
                ->orWhere('payments_process.payment_status', '!=', 5))
            ->where(function ($q) {
                $q->where('payments_process.type', 'credit_note_settlement')
                    ->orWhere(fn ($voucher) => $voucher
                        ->where('payments_process.type', 'client payments')
                        ->whereExists(function ($sub) {
                            $sub->selectRaw('1')
                                ->from('receipts')
                                ->join('accounts', 'accounts.id', '=', 'receipts.account_id')
                                ->whereColumn('accounts.client_id', 'payments_process.client_id')
                                ->where(fn ($w) => $w->whereColumn('receipts.id', 'payments_process.reference_number')
                                    ->orWhereColumn('receipts.code', 'payments_process.reference_number'));
                        }));
            })
            ->groupBy('payments_process.invoice_id')
            ->selectRaw('payments_process.invoice_id, SUM(payments_process.amount) as settled')
            ->pluck('settled', 'invoice_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * المرتجعات = فواتير مرتجعة + إشعارات دائنة أنشأها الموظف في الفترة.
     */
    public function returns(int $employeeId, Carbon $from, Carbon $to): float
    {
        $returnInvoices = Invoice::where('created_by', $employeeId)
            ->whereBetween('invoice_date', [$from, $to])
            ->where('type', 'returned')
            ->sum('grand_total');

        $creditNotes = CreditNotification::where('created_by', $employeeId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('grand_total');

        return (float) ($returnInvoices + $creditNotes);
    }

    /**
     * زيارات خطّ السير في يوم الإقفال: المكتملة، والفائتة.
     *
     * الإقفالُ نهايةُ اليوم، فكلُّ زيارةٍ مخطَّطةٍ لم تكتمل ولم تُلغَ تُعدّ
     * «فائتة» هنا — ولو لم يمرّ بعدُ أمرُ `itinerary:mark-missed-visits`
     * الذي يعلّمها فائتةً بعد منتصف الليل. والصفُّ القديم بلا
     * `visit_status` يُقرأ من العمود القديم (`active` = تمّت) كما في
     * `DailyItineraryService::stateOf()`.
     *
     * **المدّة والنتيجة** — لكلّ زيارةٍ مكتملة:
     *
     * - المدّة من مساحة عمل الزيارة (`visits`: من «بدء الزيارة» إلى
     *   «إنهائها» بلا وقت الإيقاف المؤقّت)، وإلّا من صفّ خطّ السير
     *   (`actual_duration_minutes` أو طرفاه). بلا بدايةٍ مسجَّلة لا مدّة.
     * - النتيجة بقاعدة مساحة العمل نفسها (`VisitWorkspaceController`):
     *   فاتورةٌ أو سندُ قبضٍ للعميل في يومه من هذا الموظف ⇐ **ناجحة**،
     *   وإلّا ⇐ **سلبيّة**. ما أنهته مساحةُ العمل يحمل `outcome` جاهزاً؛
     *   وما أُكمل من فاتورةٍ أو سندٍ (بلا مساحة عمل) يُستنتج من مستندات اليوم.
     *
     * وزيارةُ مساحة عملٍ أُنهيت لعميلٍ ليس في خطّ سير اليوم زيارةٌ فعليّة
     * كذلك، فتُضمّ إلى المكتملة موسومةً «خارج خطّ السير».
     *
     * @return array{
     *     completed: array, missed: array,
     *     completed_count: int, missed_count: int,
     *     success_count: int, negative_count: int,
     *     total_minutes: int, total_duration: ?string
     * }
     */
    public function visits(int $employeeId, string $date): array
    {
        $rows = EmployeeClientVisit::where('employee_id', $employeeId)
            ->whereDate('visit_date', $date)
            ->orderByRaw('sequence IS NULL')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get(['id', 'client_id', 'visit_status', 'status', 'started_at', 'completed_at', 'missed_reason', 'actual_duration_minutes']);

        $workspace = $this->workspaceVisits($employeeId, $date);

        $clientIds = $rows->pluck('client_id')->merge($workspace->keys())->filter()->unique()->all();

        $clients = Client::withoutGlobalScopes()
            ->whereIn('id', $clientIds ?: [0])
            ->get(['id', 'trade_name', 'code'])
            ->keyBy('id');

        $servedClientIds = $this->clientsWithSaleOrCollection($employeeId, $date);

        $lists = ['completed' => [], 'missed' => []];

        foreach ($rows as $row) {
            $status = $row->visit_status;
            $clientId = (int) $row->client_id;

            $state = match (true) {
                $status === VisitStatus::COMPLETED, $status === null && $row->status === 'active' => 'completed',
                $status === VisitStatus::CANCELLED => null,
                default => 'missed',
            };

            // العميل الواحد يُذكر مرّةً في كلّ قائمة — صفُّ «سأعود له» يكرّره
            if ($state === null || isset($lists[$state][$clientId])) {
                continue;
            }

            $client = $clients->get($clientId);

            $entry = [
                'client' => $client ? $this->partyName($client, '—') : '#'.$clientId,
                'code' => $client->code ?? null,
                'time' => null,
                'note' => $state === 'missed' ? $row->missed_reason : null,
                'unplanned' => false,
                '_client_id' => $clientId,
            ];

            if ($state === 'completed') {
                $ws = $workspace->get($clientId);

                /* `actual_duration_minutes` المخزَّن أولاً — هو ما تعرضه شاشةُ
                   العميل (`EmployeeClientVisit::getDurationLabelAttribute()`)،
                   فكانت هذه الشاشة تحسبها هنا من جديد من `elapsed_seconds`
                   الحيّ (`Visit`) فتختلف عنها بفارق تقريبٍ (round عند الكتابة
                   مقابل intdiv هنا) رغم أنّهما نفس الزيارة. القيمة المخزَّنة
                   تغلب الآن، ولا يُعاد الحساب إلا حين تكون فارغة فعلاً. */
                $minutes = $row->actual_duration_minutes ?? $ws['minutes'] ?? null;
                if ($minutes === null && $row->started_at && $row->completed_at) {
                    // على حدود الدقيقة كعمود التنقّل — `diffInMinutes` تقتطع
                    // الثواني فتقول «دقيقة» لما بين 14:00 و14:02
                    $minutes = max(0, $this->clockMinute($row->completed_at) - $this->clockMinute($row->started_at));
                }

                $entry += $this->visitOutcomeFields(
                    $minutes !== null ? (int) $minutes : null,
                    $ws['outcome'] ?? null,
                    isset($servedClientIds[$clientId])
                );
                $entry['_visit_end'] = $ws['ended_at'] ?? $row->completed_at;
                $entry['time'] = optional($entry['_visit_end'])->format('H:i');
                // وقتُ بدء الزيارة الفعليّ — يُرتَّب عليه المكتملون أدناه بترتيب
                // حدوثهم لا بترتيب خطّ السير المخطَّط (`sequence`)، ويُعرض في
                // الطباعة إلى جانب وقت الانتهاء (`time`)
                $entry['_visit_start'] = $ws['started_at'] ?? $row->started_at;
                $entry['start_time'] = optional($entry['_visit_start'])->format('H:i');
            }

            $lists[$state][$clientId] = $entry;
        }

        // زيارةُ مساحة عملٍ أُنهيت لعميلٍ ليس في خطّ سير اليوم
        foreach ($workspace as $clientId => $ws) {
            if (isset($lists['completed'][$clientId]) || ! $ws['ended_at']) {
                continue;
            }

            $client = $clients->get($clientId);

            // صفُّ خطّ سيرٍ بقي «مخطَّطاً» وأنهت مساحةُ العمل زيارتَه: زيارةٌ تمّت لا فائتة
            $planned = isset($lists['missed'][$clientId]);

            $lists['completed'][$clientId] = [
                'client' => $client ? $this->partyName($client, '—') : '#'.$clientId,
                'code' => $client->code ?? null,
                'time' => $ws['ended_at']->format('H:i'),
                'start_time' => optional($ws['started_at'])->format('H:i'),
                'note' => null,
                'unplanned' => ! $planned,
                '_client_id' => (int) $clientId,
                '_visit_start' => $ws['started_at'],
                '_visit_end' => $ws['ended_at'],
            ] + $this->visitOutcomeFields($ws['minutes'], $ws['outcome'], isset($servedClientIds[$clientId]));

            unset($lists['missed'][$clientId]);
        }

        // عميلٌ أُكملت زيارتُه لا يُعدّ فائتاً لأنّ له صفّاً آخر لم يُغلق
        $completed = array_values($lists['completed']);
        /* العميلُ الذي لم يُزَر لا ملاحظةَ له اليوم، فعهدتُه **آخرُ ما سُجّل له**
           في أيّ يومٍ سابق ومن أيّ موظف — العهدةُ عند العميل لا عند المندوب.
           ويُذكر تاريخُها معها: رقمٌ بلا تاريخ يُقرأ كأنّه جردُ اليوم. */
        $missedRows = array_diff_key($lists['missed'], $lists['completed']);
        $lastCustody = $this->lastCustodyCounts(array_keys($missedRows));

        $missed = array_map(function ($entry) use ($lastCustody) {
            $last = $lastCustody[$entry['_client_id'] ?? 0] ?? null;
            $entry['custody'] = $last['count'] ?? null;
            $entry['custody_date'] = $last['date'] ?? null;
            unset($entry['_client_id']);

            return $entry;
        }, array_values($missedRows));

        $missedCustodyValues = array_filter(array_column($missed, 'custody'), fn ($v) => $v !== null);

        /* المكتملون بترتيب وقوعهم الفعليّ — أول زيارةٍ بدأت أوّلاً — لا بترتيب
           «خطّ السير» المخطَّط (`sequence`) الذي بُني منه `$rows` أعلاه؛ زيارةٌ
           بلا وقت بدءٍ معروف (نادرة) تُذيَّل القائمة بدل أن تُسقِط الترتيب كلّه. */
        usort($completed, function ($a, $b) {
            $at = $a['_visit_start']?->timestamp;
            $bt = $b['_visit_start']?->timestamp;
            if ($at === null && $bt === null) {
                return 0;
            }
            if ($at === null) {
                return 1;
            }
            if ($bt === null) {
                return -1;
            }

            return $at <=> $bt;
        });
        /* وقتُ التنقّل: من مغادرة العميل السابق إلى الوصول عند هذا العميل.
           غادر الأوّلَ ١:١٥ ووصل الثانيَ ١:٣٠ ⇐ ١٥ دقيقة. الأوّلُ في اليوم لا
           سابقَ له فلا تنقّل له، وكذلك من نقص أحدُ طرفيه. وزيارةٌ بدأت قبل أن
           تُنهى سابقتُها (تداخلٌ في التسجيل) لا تُعطى رقماً سالباً. */
        $previousEnd = null;
        $totalTravel = 0;
        // الاستراحةُ بين زيارتين ليست تنقّلاً — تُطرح من الفاصل وتُذكر بجانبه
        $rests = $this->restIntervals($employeeId, $date);

        foreach ($completed as &$entry) {
            $start = $entry['_visit_start'] ?? null;
            $travel = null;
            $restInGap = 0;

            if ($previousEnd && $start && $start->greaterThanOrEqualTo($previousEnd)) {
                /* الفاصلُ والاستراحةُ يُقاسان على حدود الدقيقة نفسِها التي
                   تُطبَع — انظر {@see clockMinute()}. ولولا ذلك لاختلف العمودُ
                   عن الساعتين المطبوعتين بجانبه. */
                $gapFrom = $this->clockMinute($previousEnd);
                $gapTo = $this->clockMinute($start);
                $gapMinutes = max(0, $gapTo - $gapFrom);

                foreach ($rests as [$restFrom, $restTo]) {
                    $restInGap += max(0, min($this->clockMinute($restTo), $gapTo) - max($this->clockMinute($restFrom), $gapFrom));
                }

                /* الاستراحةُ لا تزيد على الفاصل: فترتان متداخلتان في
                   `rest_intervals` كانتا تُجمَعان مرّتين، فيُطرَح من الفاصل
                   أكثرُ منه — و`max(0, …)` كانت تُخفي ذلك في الرقم وتُظهره في
                   نصّ «(+ استراحة …)» بجانبه. */
                $restInGap = min($restInGap, $gapMinutes);
                $travel = $gapMinutes - $restInGap;
                $totalTravel += $travel;
            }

            $entry['travel_minutes'] = $travel;
            $entry['travel'] = $travel !== null
                ? $this->durationLabel($travel).($restInGap > 0 ? ' (+ استراحة '.$this->durationLabel($restInGap).')' : '')
                : null;

            $previousEnd = $entry['_visit_end'] ?? $previousEnd;
        }
        unset($entry);

        // آخرُ عهدةٍ سُجّلت اليوم في ملاحظة الزيارة — لكلّ عميل
        $custody = $this->custodyCounts($employeeId, $date, $clientIds);

        $completed = array_map(function ($entry) use ($custody) {
            $entry['custody'] = $custody[$entry['_client_id'] ?? 0] ?? null;
            unset($entry['_visit_start'], $entry['_visit_end'], $entry['_client_id']);

            return $entry;
        }, $completed);

        $totalMinutes = (int) collect($completed)->sum(fn ($v) => (int) ($v['duration_minutes'] ?? 0));
        $custodyValues = array_filter(array_column($completed, 'custody'), fn ($v) => $v !== null);

        return [
            'completed' => $completed,
            'missed' => $missed,
            'completed_count' => count($completed),
            'missed_count' => count($missed),
            'success_count' => count(array_filter($completed, fn ($v) => $v['outcome'] === 'success')),
            'negative_count' => count(array_filter($completed, fn ($v) => $v['outcome'] === 'negative')),
            'total_minutes' => $totalMinutes,
            'total_duration' => $totalMinutes > 0 ? $this->durationLabel($totalMinutes) : null,
            'total_travel_minutes' => $totalTravel,
            'total_travel' => $totalTravel > 0 ? $this->durationLabel($totalTravel) : null,
            'custody_total' => $custodyValues === [] ? null : (int) array_sum($custodyValues),
            'missed_custody_total' => $missedCustodyValues === [] ? null : (int) array_sum($missedCustodyValues),
        ];
    }

    /**
     * آخرُ «عدد العهدة» مسجَّلٍ لكلّ عميل — بلا قيدِ يومٍ ولا موظف.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, array{count: int, date: ?string}> معرّف العميل => العدد وتاريخه
     */
    protected function lastCustodyCounts(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $latestIds = ClientRelation::whereIn('client_id', $clientIds)
            ->whereNotNull('deposit_count')
            ->groupBy('client_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        return ClientRelation::whereIn('id', $latestIds)
            ->get(['id', 'client_id', 'deposit_count', 'date', 'created_at'])
            ->mapWithKeys(fn ($note) => [(int) $note->client_id => [
                'count' => (int) $note->deposit_count,
                'date' => $note->date
                    ? Carbon::parse($note->date)->format('Y-m-d')
                    : optional($note->created_at)->format('Y-m-d'),
            ]])
            ->all();
    }

    /**
     * آخرُ «عدد العهدة» سجّله الموظف اليوم في ملاحظة الزيارة، لكلّ عميل.
     *
     * الملاحظةُ صفٌّ في `client_relations` (`employee_id` = users.id،
     * يكتبه `ClientNoteController`). والعميلُ قد تُكتب له أكثرُ من ملاحظةٍ
     * في اليوم — يغلب آخرُها، فهو ما وُجد عنده عند آخر وقوف.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, int> معرّف العميل => عدد العهدة
     */
    protected function custodyCounts(int $employeeId, string $date, array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        return ClientRelation::where('employee_id', $employeeId)
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('deposit_count')
            ->where(fn ($q) => $q->whereDate('date', $date)
                ->orWhere(fn ($w) => $w->whereNull('date')->whereDate('created_at', $date)))
            ->orderBy('id')
            ->get(['id', 'client_id', 'deposit_count'])
            ->mapWithKeys(fn ($note) => [(int) $note->client_id => (int) $note->deposit_count])
            ->all();
    }

    /**
     * حقولُ المدّة والنتيجة لزيارةٍ مكتملة.
     *
     * @param  string|null  $workspaceOutcome  `success`/`failed` كما خزّنته مساحة العمل، أو `null`
     * @param  bool  $served  للعميل فاتورةٌ أو سندُ قبضٍ من الموظف في اليوم
     * @return array{duration_minutes: ?int, duration: ?string, outcome: string, outcome_label: string}
     */
    protected function visitOutcomeFields(?int $minutes, ?string $workspaceOutcome, bool $served): array
    {
        $outcome = match ($workspaceOutcome) {
            'success' => 'success',
            'failed' => 'negative',
            default => $served ? 'success' : 'negative',
        };

        return [
            'duration_minutes' => $minutes,
            'duration' => $minutes !== null ? $this->durationLabel($minutes) : null,
            'outcome' => $outcome,
            'outcome_label' => __('sales_daily_closing.status.visit_'.$outcome),
        ];
    }

    /**
     * زيارات مساحة العمل الفعليّة للموظف في اليوم، مفهرسةً بالعميل — لكلّ
     * عميلٍ آخرُ زيارةٍ أُنهيت (أو الجارية إن لم تُنهَ واحدة).
     *
     * @return Collection<int, array{minutes: ?int, outcome: ?string, ended_at: ?Carbon, started_at: ?Carbon}>
     */
    protected function workspaceVisits(int $employeeId, string $date): Collection
    {
        $visits = Visit::where('employee_id', $employeeId)
            ->whereDate('visit_date', $date)
            ->where(fn ($q) => Visit::constrainActualVisit($q))
            ->where(fn ($q) => $q->whereNull('stage')->orWhere('stage', '!=', Visit::STAGE_CANCELLED))
            ->orderBy('id')
            ->get(['id', 'client_id', 'stage', 'outcome', 'started_at', 'departure_time', 'total_paused_seconds', 'paused_at']);

        $byClient = collect();

        foreach ($visits as $visit) {
            $clientId = (int) $visit->client_id;
            $ended = $visit->isCompleted() ? ($visit->departure_time ?? null) : null;

            $minutes = null;
            if ($visit->started_at && $ended) {
                $minutes = (int) max(0, intdiv($visit->elapsed_seconds, 60));
            }

            $current = $byClient->get($clientId);

            // المنتهية تغلب الجارية، والأحدث يغلب الأقدم
            if ($current && $current['ended_at'] && ! $ended) {
                continue;
            }

            $byClient->put($clientId, [
                'minutes' => $minutes,
                'outcome' => $visit->outcome ?: null,
                'ended_at' => $ended,
                'started_at' => $visit->started_at,
            ]);
        }

        return $byClient;
    }

    /**
     * العملاء الذين لهم فاتورةُ مبيعاتٍ أو سندُ قبضٍ من هذا الموظف في اليوم —
     * قاعدةُ «الزيارة الناجحة» نفسُها في مساحة العمل.
     *
     * @return array<int, int> معرّف العميل مفتاحاً — للفحص بـ `isset()`
     */
    protected function clientsWithSaleOrCollection(int $employeeId, string $date): array
    {
        $invoiced = Invoice::where('created_by', $employeeId)
            ->whereDate('invoice_date', $date)
            ->whereIn('type', ['normal', 'pos'])
            ->pluck('client_id');

        $collected = Receipt::query()
            ->join('accounts', 'accounts.id', '=', 'receipts.account_id')
            ->where('receipts.created_by', $employeeId)
            ->whereDate('receipts.date', $date)
            ->whereNull('receipts.cancelled_at')
            ->pluck('accounts.client_id');

        return $invoiced->merge($collected)->filter()->map(fn ($id) => (int) $id)->unique()->flip()->all();
    }

    /** «٤٥ د» / «١ س ٢٥ د» — كما في `EmployeeClientVisit::getDurationLabelAttribute()`. */
    /**
     * دوامُ اليوم مقابل ما جرى فيه: كم داوم، وكم عند العملاء، وكم بينهم.
     *
     * الإقفالُ كان يعرض الزيارات وحدها — عدداً ومدّةً — بلا دوامٍ يُقاس
     * عليه. فلا يُعرف إن كانت ثلاثُ ساعاتٍ عند العملاء يوماً ممتلئاً أم
     * نصفَ دوامٍ ضائع. هنا صافي الدوام من الحضور (بدون الاستراحة)، والوقتُ
     * عند العملاء والتنقّل من الزيارات نفسِها، والباقي وقتٌ غيرُ مغطّى،
     * ومقارنةٌ بالدوام المطلوب (الوردية أو ساعات الدوام المرن).
     *
     * `$userId` هو users.id (الزيارات)؛ الحضورُ على employees.id.
     *
     * @param  array<string, mixed>  $visits  ناتج `visits()`
     * @return array<string, mixed>|null null لمن لا ملفَّ وظيفيّاً له
     */
    public function work(int $userId, string $date, array $visits): ?array
    {
        $employeeId = User::whereKey($userId)->value('employee_id');
        $employee = $employeeId ? Employee::find($employeeId) : null;

        if (! $employee) {
            return null;
        }

        $day = AttendanceDays::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->first();
        $describe = app(AttendanceProcessorService::class)->describeDay($employee, Carbon::parse($date));
        $config = $describe['config'];
        $flexible = ($config['work_mode'] ?? 'shift') === 'flexible';

        /*
            المطلوبُ صافياً: الدوامُ المرن عددُ ساعاته، والورديةُ طولُها ناقصاً
            الاستراحةَ المسموحة — وردية 09:00–17:00 باستراحة ساعة = 7 ساعات عمل.
            كان يُقاس الصافي (بدون الاستراحة) على طول الوردية كاملاً، فيظهر كلُّ
            من أخذ استراحته المسموحة «أقل من الدوام المطلوب».
        */
        $required = $flexible || ! $describe['expected_check_in'] || ! $describe['expected_check_out']
            ? (int) round(((float) ($config['work_hours_per_day'] ?? 8)) * 60)
            : max(0, (int) $describe['expected_check_in']->diffInMinutes($describe['expected_check_out'], true)
                - (int) ($config['break_allowed_minutes'] ?? 60));

        $breaks = (int) ($day->break_minutes ?? 0);
        $checkIn = $day?->check_in_time ? substr((string) $day->check_in_time, 0, 5) : null;
        $checkOut = $day?->check_out_time ? substr((string) $day->check_out_time, 0, 5) : null;

        // يومٌ لم يُغلق بعد (اليوم الجاري): حتى الآن
        if ($checkIn && ! $checkOut && Carbon::parse($date)->isToday()) {
            $gross = (int) max(0, Carbon::parse($date.' '.$day->check_in_time)->diffInMinutes(now(), false));
            if ($day->break_started_at) {
                $breaks += (int) max(0, $day->break_started_at->diffInMinutes(now(), false));
            }
        } else {
            $gross = (int) ($day?->working_hours ?? 0);
        }
        $net = max(0, $gross - $breaks);

        $atClients = (int) ($visits['total_minutes'] ?? 0);
        // بين نهاية زيارةٍ وبداية التالية — تنقّلٌ أو توقّف (والاستراحةُ إن وقعت بينهما)
        $travel = (int) ($visits['total_travel_minutes'] ?? 0);

        // من الحضور إلى أول زيارة، ومن آخر زيارة إلى الانصراف
        $minuteOf = fn (?string $hm) => $hm ? ((int) substr($hm, 0, 2)) * 60 + (int) substr($hm, 3, 2) : null;
        $starts = array_filter(array_map(fn ($v) => $minuteOf($v['start_time'] ?? null), $visits['completed'] ?? []), fn ($v) => $v !== null);
        $ends = array_filter(array_map(fn ($v) => $minuteOf($v['time'] ?? null), $visits['completed'] ?? []), fn ($v) => $v !== null);
        $beforeFirst = ($checkIn && $starts) ? max(0, min($starts) - $minuteOf($checkIn)) : null;
        $afterLast = ($checkOut && $ends) ? max(0, $minuteOf($checkOut) - max($ends)) : null;

        // `visits()` تطرح الاستراحاتِ الواقعةَ بين الزيارات من التنقّل (`restIntervals`)

        // زياراتٌ وقعت خارج الدوام المسجَّل — قبل الحضور أو بعد الانصراف
        $outside = 0;
        foreach ($visits['completed'] ?? [] as $visit) {
            $start = $visit['start_time'] ?? null;
            $end = $visit['time'] ?? null;
            if (($checkIn && $start && $start < $checkIn) || ($checkOut && $end && $end > $checkOut) || (! $checkIn && $start)) {
                $outside++;
            }
        }

        $threshold = (int) ($config['early_leave_threshold_minutes'] ?? 0);
        $state = match (true) {
            ! $checkIn => 'no_attendance',
            ! $checkOut => 'open',
            $net + $threshold >= $required => 'matched',
            default => 'short',
        };
        // النقصُ ضمن سماح الانصراف لا يُعدّ نقصاً — كما في احتساب الحضور
        $short = $state === 'short' ? $required - $net : 0;

        $label = fn (int $m) => $m > 0 ? $this->durationLabel($m) : '0';

        return [
            'mode' => $flexible ? 'flexible' : 'shift',
            'expected' => $flexible
                ? 'دوام مرن '.$label($required)
                : trim(optional($describe['expected_check_in'])->format('H:i').' – '.optional($describe['expected_check_out'])->format('H:i'), ' –'),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => $day?->status,
            'required_minutes' => $required,
            'required' => $label($required),
            'break_minutes' => $breaks,
            'breaks' => $label($breaks),
            'break_allowed' => (int) ($config['break_allowed_minutes'] ?? 60),
            'net_minutes' => $net,
            'net' => $label($net),
            'clients_minutes' => $atClients,
            'clients' => $label($atClients),
            'travel_minutes' => $travel,
            'travel' => $label($travel),
            'before_first_minutes' => $beforeFirst,
            'before_first' => $beforeFirst === null ? '—' : $label($beforeFirst),
            'after_last_minutes' => $afterLast,
            'after_last' => $afterLast === null ? '—' : $label($afterLast),
            'short_minutes' => $short,
            'short' => $label($short),
            'late_minutes' => (int) ($day->late_minutes ?? 0),
            'visits_count' => (int) ($visits['completed_count'] ?? 0),
            'outside_visits' => $outside,
            'state' => $state,
            'state_label' => [
                'no_attendance' => 'لم يسجّل حضوراً',
                'open' => 'الدوام مفتوح — لم يسجّل انصرافاً',
                'matched' => 'مطابق للدوام المطلوب',
                'short' => 'أقل من الدوام المطلوب',
            ][$state],
        ];
    }

    /**
     * استراحاتُ الموظف في اليوم كفتراتٍ زمنية — من سجل التسجيلات
     * (`rest_in` / `rest_out`). `$userId` هو users.id؛ الحضورُ على employees.id.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    protected function restIntervals(int $userId, string $date): array
    {
        $employeeId = User::whereKey($userId)->value('employee_id');

        if (! $employeeId) {
            return [];
        }

        $punches = AttendancePunch::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->where('result', AttendancePunch::RESULT_ACCEPTED)
            ->whereIn('punch_type', [AttendancePunch::TYPE_BREAK_START, AttendancePunch::TYPE_BREAK_END])
            ->orderBy('punched_at')
            ->get(['punch_type', 'punched_at']);

        $intervals = [];
        $open = null;
        foreach ($punches as $punch) {
            if ($punch->punch_type === AttendancePunch::TYPE_BREAK_START) {
                $open = $punch->punched_at;
            } elseif ($open) {
                $intervals[] = [$open, $punch->punched_at];
                $open = null;
            }
        }

        return $intervals;
    }

    /**
     * رقمُ الدقيقة على الساعة — الطابعُ الزمنيُّ مقسوماً على ستّين بإسقاط
     * الثواني.
     *
     * **لماذا؟** عمودُ «التنقّل من العميل السابق» يُطبَع بين ساعتين بصيغة
     * `H:i`، فالقارئُ يطرح إحداهما من الأخرى. وكان الفرقُ يُحسب بـ
     * `Carbon::diffInMinutes()` ثمّ يُقتطع بـ`(int)`: فانصرافٌ في 14:00:50
     * وبدءٌ في 14:02:30 (مئةُ ثانية = 1.67 دقيقة) يُقتطع إلى **دقيقةٍ واحدة**
     * بينما الساعتان المطبوعتان 14:00 و14:02 — فيقرأ المستخدمُ «دقيقة» حيث
     * يرى دقيقتين. (و Carbon 3 يُرجع `float`، فالاقتطاعُ حتميّ.)
     *
     * فالفرقُ يُقاس على الحدود نفسِها التي تُطبَع: 14:02 ناقص 14:00 = 2.
     * لا تقريبٌ ولا اقتطاعُ ثوانٍ — طرحُ ما يراه القارئ.
     */
    protected function clockMinute(CarbonInterface $moment): int
    {
        return intdiv($moment->getTimestamp() - $moment->second, 60);
    }

    protected function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' '.__('sales_daily_closing.fields.minute_short');
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $label = $hours.' '.__('sales_daily_closing.fields.hour_short');

        return $rest ? $label.' '.$rest.' '.__('sales_daily_closing.fields.minute_short') : $label;
    }

    /** سندات القبض النشطة التي حرّرها الموظف في الفترة. */
    public function receipts(int $employeeId, Carbon $from, Carbon $to): float
    {
        return (float) Receipt::where('created_by', $employeeId)
            ->whereBetween('date', [$from, $to])
            ->where('status', 1)
            ->sum('amount');
    }

    /**
     * المصروفات: ما خرج من عهدة الموظف، مضافاً إليه سنداتُ صرفٍ حرّرها هو
     * على الخزينة الرئيسية — وإلّا ضاع مصروفٌ صُرف من حسابٍ غير عهدته.
     */
    public function expenses(int $employeeId, Carbon $from, Carbon $to): float
    {
        $user = User::find($employeeId);

        if (! $user || ! $user->employee_id) {
            return 0;
        }

        $treasuryAndBankAccounts = $this->treasuryAndBankAccountIds();
        $cancelledExpenseRefs = $this->cancelledExpenseRefs();
        $mainTreasuryId = $this->mainTreasuryId();
        $employeeTreasuryId = $this->employeeTreasuryId($user->employee_id);

        // بلا عهدةٍ خاصّة، أو عهدتُه هي الرئيسية نفسها: سنداتُه وحدها على
        // الرئيسية — فالخزينة الرئيسية مشتركة، وما صرفه غيرُه ليس مصروفَه
        if ($employeeTreasuryId === null || $employeeTreasuryId === $mainTreasuryId) {
            return $this->sumExpenseCredits($employeeId, $from, $to, $mainTreasuryId, $treasuryAndBankAccounts, $cancelledExpenseRefs, true);
        }

        return $this->sumExpenseCredits($employeeId, $from, $to, $employeeTreasuryId, $treasuryAndBankAccounts, $cancelledExpenseRefs, false)
            + $this->sumExpenseCredits($employeeId, $from, $to, $mainTreasuryId, $treasuryAndBankAccounts, $cancelledExpenseRefs, true);
    }

    // ══════════════════════ الداخل ══════════════════════

    /** معرّف الحساب من الإعدادات، وإلّا الثابت البرمجي. */
    protected function accountId(string $key, int $fallback): int
    {
        return (int) (AccountingSettings::id($key, $fallback) ?: $fallback);
    }

    protected function mainTreasuryId(): int
    {
        return $this->accountId('main_treasury', SystemAccountIds::MAIN_TREASURY);
    }

    /** عهدة الموظف — أو `null` إن لم تُسند إليه خزينة. */
    protected function employeeTreasuryId(int $hrEmployeeId): ?int
    {
        $treasuryEmployee = TreasuryEmployee::where('employee_id', $hrEmployeeId)->first();

        return $treasuryEmployee && $treasuryEmployee->treasury_id
            ? (int) $treasuryEmployee->treasury_id
            : null;
    }

    /**
     * المرتجعات النقدية التي خرجت من الخزينة في اليوم — ما أعاده الموظف
     * للعميل نقداً عند مرتجع مبيعات (`payments_process.type = treasury_return`،
     * يكتبه `ReturnInvoiceController`).
     */
    public function cashRefunds(int $employeeId, string $date): float
    {
        return (float) $this->cashRefundQuery($employeeId, $date)->sum('payments_process.amount');
    }

    /**
     * سطورُ المرتجع النقدي للمطبوع: مرجع المرتجع، العميل، المبلغ.
     *
     * @return array<int, array{reference: string, client: string, description: string, amount: float}>
     */
    public function cashRefundEntries(int $employeeId, string $date): array
    {
        return $this->cashRefundQuery($employeeId, $date)
            ->with('invoice.client')
            ->orderBy('payments_process.id')
            ->get()
            ->map(fn ($payment) => [
                'reference' => $payment->invoice->code ?? $payment->reference_number ?? (string) $payment->id,
                'client' => $this->partyName($payment->invoice->client ?? null),
                'description' => __('sales_daily_closing.messages.cash_refund_line'),
                'amount' => (float) $payment->amount,
            ])
            ->all();
    }

    /**
     * صاحبُ المرتجع النقدي هو **مَن حرّر فاتورةَ المرتجع** (`invoices.created_by`
     * = users.id، يكتبه `ReturnInvoiceController` بـ`Auth::id()`).
     *
     * لا يُقرأ `payments_process.employee_id`: صفوفُ الصرف القديمة خُتمت فيه
     * بـ`employees.id` لا `users.id` (راجع تعليق `PaymentsProcess`)، فرقمُ
     * موظفٍ يساوي رقمَ مستخدمٍ آخر يُحمِّل مرتجعاً صرفه غيرُه على مندوبٍ لم
     * يصرف شيئاً — فيُطلب منه في إقفاله مرتجعٌ نقدي ليس له.
     */
    protected function cashRefundQuery(int $employeeId, string $date)
    {
        return PaymentsProcess::where('payments_process.type', 'treasury_return')
            ->whereDate('payments_process.payment_date', $date)
            ->where(fn ($q) => $q->whereNull('payments_process.payment_status')
                ->orWhere('payments_process.payment_status', '!=', 5))
            ->whereHas('invoice', fn ($iq) => $iq->where('created_by', $employeeId));
    }

    /** الفواتير التي ارتدّت — يُوسم بها سطرُ البيع الأصلي في المطبوع. */
    protected function returnedOriginalInvoiceIds(int $employeeId, Carbon $from, Carbon $to): array
    {
        return Invoice::where('created_by', $employeeId)
            ->whereBetween('invoice_date', [$from, $to])
            ->where('type', 'returned')
            ->whereNotNull('reference_number')
            ->pluck('reference_number')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->toArray();
    }

    /**
     * سطور المصروف المعروضة ومجموعها.
     *
     * @return array{entries: array, total: float}
     */
    protected function expenseEntries(
        int $employeeId,
        Carbon $from,
        Carbon $to,
        array $treasuryAndBankAccounts,
        array $cancelledExpenseRefs
    ): array {
        $user = User::find($employeeId);

        if (! $user || ! $user->employee_id) {
            return ['entries' => [], 'total' => 0];
        }

        $validExpenseIds = $this->mergedValidExpenseJournalIds($employeeId, $from, $to, $treasuryAndBankAccounts, $cancelledExpenseRefs);

        if (empty($validExpenseIds)) {
            return ['entries' => [], 'total' => 0];
        }

        $displayTreasuryAccountIds = $this->treasuryAccountIdsForDisplay($this->employeeTreasuryId($user->employee_id));

        $entries = [];
        $total = 0;

        $expenseJEs = JournalEntry::whereIn('id', $validExpenseIds)
            ->with(['client', 'details.account'])
            ->orderBy('id')
            ->get();

        foreach ($expenseJEs as $je) {
            $amount = $je->details
                ->whereIn('account_id', $displayTreasuryAccountIds)
                ->where('credit', '>', 0)
                ->sum('credit');

            // الطرف المدين غير النقدي هو حساب المصروف نفسه
            $expenseAccountDetail = $je->details
                ->whereNotIn('account_id', $treasuryAndBankAccounts)
                ->where('debit', '>', 0)
                ->first();

            $entries[] = [
                'reference' => $je->reference_number,
                'account_name' => $expenseAccountDetail && $expenseAccountDetail->account
                    ? $expenseAccountDetail->account->name
                    : ($je->description ?? '-'),
                'client' => $this->partyName($je->client, '-'),
                'description' => $je->description ?? '-',
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return ['entries' => $entries, 'total' => $total];
    }

    /**
     * حسابات الخزينة التي يُقرأ منها مبلغُ سطر المصروف في المطبوع.
     *
     * @return array<int, int>
     */
    protected function treasuryAccountIdsForDisplay(?int $employeeTreasuryId): array
    {
        $mainTreasuryId = $this->mainTreasuryId();

        if ($employeeTreasuryId === null || $employeeTreasuryId === $mainTreasuryId) {
            return [$mainTreasuryId];
        }

        return [$employeeTreasuryId, $mainTreasuryId];
    }

    /**
     * قيود مصروفات الموظف المعتمدة — عهدتُه وسنداتُه على الرئيسية معاً.
     *
     * @return array<int, int>
     */
    protected function mergedValidExpenseJournalIds(
        int $employeeId,
        Carbon $from,
        Carbon $to,
        array $treasuryAndBankAccounts,
        array $cancelledExpenseRefs
    ): array {
        $user = User::find($employeeId);

        if (! $user || ! $user->employee_id) {
            return [];
        }

        $mainTreasuryId = $this->mainTreasuryId();
        $employeeTreasuryId = $this->employeeTreasuryId($user->employee_id);

        $fromMain = $this->filterValidExpenseJournalIds(
            $this->fetchExpenseJournalIdsCreditingTreasury($employeeId, $from, $to, $mainTreasuryId, $cancelledExpenseRefs, true),
            $treasuryAndBankAccounts
        );

        if ($employeeTreasuryId === null || $employeeTreasuryId === $mainTreasuryId) {
            return $fromMain;
        }

        $fromEmployeeTreasury = $this->filterValidExpenseJournalIds(
            $this->fetchExpenseJournalIdsCreditingTreasury($employeeId, $from, $to, $employeeTreasuryId, $cancelledExpenseRefs, false),
            $treasuryAndBankAccounts
        );

        return array_values(array_unique(array_merge($fromEmployeeTreasury, $fromMain)));
    }

    /**
     * قيودٌ فيها دائنٌ على خزينةٍ محدّدة، بفلاتر الإقفال نفسها.
     *
     * @param  bool  $restrictToOwnExpenseVouchers  قصرُها على سندات مصروفٍ حرّرها هذا
     *                                              المستخدم — لازمٌ للخزينة الرئيسية المشتركة
     * @return array<int, int>
     */
    protected function fetchExpenseJournalIdsCreditingTreasury(
        int $employeeId,
        Carbon $from,
        Carbon $to,
        int $treasuryAccountId,
        array $cancelledExpenseRefs,
        bool $restrictToOwnExpenseVouchers
    ): array {
        return JournalEntryDetail::whereHas('journalEntry', function ($query) use ($from, $to, $cancelledExpenseRefs, $employeeId, $restrictToOwnExpenseVouchers) {
            $query->whereBetween('date', [$from, $to])
                ->where('status', 1)
                ->whereNotIn('reference_type', [ReferenceType::PAYMENT_CANCELLATION, ReferenceType::SALES_RETURN])
                ->where('reference_number', 'NOT LIKE', '%-REV');

            if (! empty($cancelledExpenseRefs)) {
                $query->whereNotIn('reference_number', $cancelledExpenseRefs);
            }

            if ($restrictToOwnExpenseVouchers) {
                $query->where('reference_type', ReferenceType::EXPENSES)
                    ->where('created_by_employee', $employeeId);
            }
        })
            ->where('account_id', $treasuryAccountId)
            ->where('credit', '>', 0)
            ->pluck('journal_entry_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * استبعادُ تحويلات النقد بين الخزائن، والقيود التي تحمل مردوداتِ مبيعات.
     *
     * @param  array<int, int>  $journalEntryIds
     * @return array<int, int>
     */
    protected function filterValidExpenseJournalIds(array $journalEntryIds, array $treasuryAndBankAccounts): array
    {
        if (empty($journalEntryIds)) {
            return [];
        }

        $salesReturnsAccountId = $this->accountId('sales_returns', SystemAccountIds::SALES_RETURNS);

        // استعلامٌ واحدٌ لكل القيود بدل استعلامٍ لكلّ قيدٍ في حلقة
        $detailsByEntry = JournalEntryDetail::whereIn('journal_entry_id', $journalEntryIds)
            ->get()
            ->groupBy('journal_entry_id');

        $excluded = [];

        foreach ($journalEntryIds as $entryId) {
            $details = $detailsByEntry->get($entryId) ?? collect();

            $hasDebitTreasury = $details->whereIn('account_id', $treasuryAndBankAccounts)->where('debit', '>', 0)->isNotEmpty();
            $hasCreditTreasury = $details->whereIn('account_id', $treasuryAndBankAccounts)->where('credit', '>', 0)->isNotEmpty();
            $hasSalesReturns = $details->where('account_id', $salesReturnsAccountId)->isNotEmpty();

            if (($hasDebitTreasury && $hasCreditTreasury) || $hasSalesReturns) {
                $excluded[] = $entryId;
            }
        }

        return array_values(array_diff($journalEntryIds, $excluded));
    }

    protected function sumExpenseCredits(
        int $employeeId,
        Carbon $from,
        Carbon $to,
        int $treasuryAccountId,
        array $treasuryAndBankAccounts,
        array $cancelledExpenseRefs,
        bool $restrictToOwnExpenseVouchers
    ): float {
        $journalEntryIds = $this->fetchExpenseJournalIdsCreditingTreasury(
            $employeeId, $from, $to, $treasuryAccountId, $cancelledExpenseRefs, $restrictToOwnExpenseVouchers
        );

        $validJournalEntryIds = $this->filterValidExpenseJournalIds($journalEntryIds, $treasuryAndBankAccounts);

        if (empty($validJournalEntryIds)) {
            return 0;
        }

        $expensesData = JournalEntryDetail::whereIn('journal_entry_id', $validJournalEntryIds)
            ->where('account_id', $treasuryAccountId)
            ->where('credit', '>', 0)
            ->selectRaw('SUM(credit) as total_credit')
            ->first();

        return (float) ($expensesData->total_credit ?? 0);
    }

    /** اسم العميل كما يُعرض — الاسم التجاري أوّلاً. */
    protected function partyName($client, string $default = 'عميل نقدي'): string
    {
        return optional($client)->trade_name ?? optional($client)->name ?? $default;
    }
}
