<?php

namespace Modules\Sales\Support;

use App\Enums\ReferenceType;
use App\Services\ClientDashboardService;
use App\Support\ClosedPeriodRanges;
use App\Support\CurrencySettings;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;

/**
 * «ماذا وراء هذا الرقم؟» — قوائمُ لوحة المبيعات، بنمط لوحة العملاء.
 *
 * كلُّ قائمةٍ تُبنى من الاستعلام الذي بُني منه رقمُها: المستنداتُ من
 * `SalesLedger::movements()` بقاعدة `periodTotals()` حرفاً (المرتجعُ طرفُه
 * الدائن، ومدينُه تسوية)، والعملاءُ من `periodTotals()` و`balancesAsOf()`،
 * والبطاقاتُ الأخرى بشروط استعلامها في `SalesDashboardMetrics`. فمجموعُ
 * عمودِ المبلغ يساوي الرقمَ المنقور — ولا يُعاد هنا تعريفُ مبيعاتٍ أو تحصيل.
 *
 * **الصفُّ عميلٌ لا مستند (بطلب المستخدم):** كلُّ قائمةٍ تُجمع عميلاً عميلاً
 * وتُعرض بأعمدة لوحة العملاء نفسِها — الكود، العميل، الجوال، الحالة، وضع
 * العميل، ثم أعمدةُ الشريحة، ثم آخرُ زيارةٍ والرصيد (`clientList()`).
 * يُستثنى ما لا عميلَ له: مصروفاتُ الموظّف، وحركةُ عميلٍ بعينه (`client.{id}`)
 * فهي تفصيلُ مستنداتِ صفٍّ واحد.
 *
 * الصيغة: `group.key[.sub]` →
 *
 *     docs.{sale|returns|return|credit_note|collection|refund|cancellation|adjustment}
 *     clients.{net|debtors|credit}
 *     client.{id}                    كل حركة العميل في الفترة
 *     emp.{userId|0}.{all|sale|returns|collection|refund|cancellation}   0 = قيودٌ بلا منشئ
 *     expenses.{userId} · visits.{userId} · plan.{userId}[.missed]
 *     status.{1|2|3} · product.{id} · trend.{Y-m[-d]}.{sale|collection|movement}
 */
class SalesDashboardDrillDown
{
    /** ما يدخل في كل قائمة مستندات — مفاتيحُ `SalesLedger::classify()` */
    protected const KINDS = [
        'sale' => ['sale'],
        'returns' => ['return', 'credit_note'],
        'return' => ['return'],
        'credit_note' => ['credit_note'],
        // المردودُ نقداً للعميل سالبٌ داخل التحصيل — فمجموعُ القائمة هو المحصَّل الصافي
        'collection' => ['collection', 'refund'],
        'refund' => ['refund'],
        // قيودُ إلغاء سندات القبض — مجموعُها `cancelled_receipts`
        'cancellation' => ['cancellation'],
        'adjustment' => ['adjustment'],
        'movement' => ['sale', 'collection', 'refund'],
        'all' => ['sale', 'return', 'credit_note', 'collection', 'refund', 'cancelled_collection', 'cancellation', 'adjustment'],
    ];

    /** @var array<int, true>|null */
    protected ?array $onlyClients;

    /** رصيدُ كل عميلٍ حتى نهاية الفترة — يُحسب مرّةً واحدةً للطلب */
    protected ?array $balanceMap = null;

    /**
     * @param  array<int, int>  $branchIds  نطاقُ اللوحة — فارغٌ يعني الكل
     * @param  array<int, int>|null  $onlyClients  تضييقٌ بعملاء بعينهم («غير محدّد» في الشجرة)
     */
    public function __construct(protected array $branchIds, ?array $onlyClients = null)
    {
        $this->onlyClients = $onlyClients === null ? null : array_fill_keys(array_map('intval', $onlyClients), true);
    }

    /**
     * @return array{title: string, hint?: string|null, columns: array, rows: array}|null
     */
    public function resolve(string $segment, ReportPeriod $period): ?array
    {
        [$group, $key, $sub] = array_pad(explode('.', $segment, 3), 3, null);
        $id = ctype_digit((string) $key) ? (int) $key : null;

        return match ($group) {
            'docs' => $this->documents($period, (string) $key),
            'clients' => $this->clients($period, (string) $key),
            'client' => $id ? $this->clientMovements($period, $id) : null,
            'emp' => $id !== null ? $this->employeeDocuments($period, $id, $sub ?? 'all') : null,
            'expenses' => $id ? $this->expenses($period, $id) : null,
            'visits' => $id ? $this->visits($period, $id, $sub) : null,
            'plan' => $id ? $this->plan($period, $id, $sub) : null,
            'status' => $this->paymentStatus($period, (int) $key),
            'product' => $id ? $this->product($period, $id) : null,
            'trend' => $this->trendBucket($period, (string) $key, $sub ?? 'movement'),
            default => null,
        };
    }

    /* ══════════════ المستندات ══════════════ */

    protected function documents(ReportPeriod $period, string $kind): ?array
    {
        $rows = $this->documentRows($period, $kind);

        return $rows === null ? null : $this->documentClients(
            $period,
            __('sales_dashboard.drill.titles.'.$kind),
            $rows,
        );
    }

    protected function employeeDocuments(ReportPeriod $period, int $userId, string $kind): ?array
    {
        $rows = $this->documentRows($period, $kind, null, $userId);

        return $rows === null ? null : $this->documentClients(
            $period,
            __('sales_dashboard.drill.titles.employee', [
                'title' => __('sales_dashboard.drill.titles.'.$kind),
                'name' => $this->userName($userId),
            ]),
            $rows,
        );
    }

    /**
     * صفوفُ المستندات معروضةً عميلاً عميلاً — مجموعُ «المبلغ» هو الرقمُ المنقور.
     *
     * @param  array<int, array<string, mixed>>  $rows  مخرجاتُ `documentRows()`
     */
    protected function documentClients(ReportPeriod $period, string $title, array $rows): array
    {
        return $this->clientList(
            $period,
            $title,
            __('sales_dashboard.drill.hints.docs_clients'),
            self::groupByClient($rows, ['amount'], 'documents'),
            [
                ['key' => 'documents', 'label' => __('sales_dashboard.drill.cols.documents'), 'type' => 'number', 'sum' => true],
                ['key' => 'amount', 'label' => __('sales_dashboard.table.amount'), 'type' => 'money', 'sum' => true],
            ],
            'amount',
        );
    }

    protected function clientMovements(ReportPeriod $period, int $clientId): ?array
    {
        if ($this->onlyClients !== null && ! isset($this->onlyClients[$clientId])) {
            return null;
        }

        $ledger = new SalesLedger($clientId, null, $this->branchIds);
        $totals = $ledger->periodTotals($period)[$clientId] ?? SalesLedger::emptyTotals();
        $balance = $ledger->balancesAsOf($period->to)[$clientId] ?? 0.0;

        return [
            'title' => __('sales_dashboard.drill.titles.client', ['name' => $this->clientInfo([$clientId])[$clientId]['name'] ?? '—']),
            'hint' => __('sales_dashboard.drill.hints.client', [
                'net' => CurrencySettings::number($totals['sales'] - $totals['returns'] - $totals['credit_notes']),
                'collections' => CurrencySettings::number($totals['collections']),
                'balance' => CurrencySettings::number($balance),
            ]),
            'columns' => $this->documentColumns(),
            'rows' => $this->documentRows($period, 'all', $clientId),
        ];
    }

    protected function trendBucket(ReportPeriod $period, string $bucket, string $kind): ?array
    {
        if (! preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $bucket) || ! in_array($kind, ['sale', 'collection', 'movement'], true)) {
            return null;
        }

        return $this->documentClients(
            $period,
            __('sales_dashboard.drill.titles.trend', ['title' => __('sales_dashboard.drill.titles.'.$kind), 'bucket' => $bucket]),
            $this->documentRows($period, $kind, null, null, $bucket) ?? [],
        );
    }

    /**
     * صفوفُ المستندات — المبلغُ بقاعدة `SalesLedger::periodTotals()`:
     *
     *     فاتورة   مدين − دائن
     *     مرتجع/إشعار   دائنُه قيمتُه، ومدينُه (صرفُ القيمة للعميل) تسوية
     *     تحصيل    دائن − مدين
     *     غيرها    مدين − دائن  (تسوية)
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function documentRows(ReportPeriod $period, string $kind, ?int $clientId = null, ?int $employeeId = null, ?string $bucket = null): ?array
    {
        if (! isset(self::KINDS[$kind])) {
            return null;
        }

        $wanted = self::KINDS[$kind];
        $rows = [];

        foreach ((new SalesLedger($clientId, null, $this->branchIds))->movements($period) as $m) {
            if ($this->onlyClients !== null && ! isset($this->onlyClients[(int) $m->client_id])) {
                continue;
            }
            if ($employeeId !== null && (int) ($m->created_by_employee ?? 0) !== $employeeId) {
                continue;
            }
            if ($bucket !== null && ! str_starts_with((string) $m->date, $bucket)) {
                continue;
            }

            $type = SalesLedger::classify($m->reference_type, (bool) $m->is_cancellation);
            $debit = (float) $m->debit;
            $credit = (float) $m->credit;

            $legs = match ($type) {
                'sale' => ['sale' => $debit - $credit],
                // مدينُ المرتجع والإشعار = ما رُدّ نقداً للعميل: موجبٌ في قائمة المردودات،
                // وسالبٌ في قائمة التحصيل فيساوي مجموعُها المحصَّل الصافي
                'return', 'credit_note' => [$type => $credit, 'refund' => $kind === 'refund' ? $debit : -$debit],
                'collection' => ['collection' => $credit - $debit],
                // الإلغاءُ بمبلغه موجباً، والأصلُ الملغى لا يظهر إلا في حركة العميل
                'cancellation' => ['cancellation' => $debit - $credit],
                'cancelled_collection' => ['cancelled_collection' => $credit - $debit],
                default => ['adjustment' => $debit - $credit],
            };

            foreach ($legs as $as => $amount) {
                if (! in_array($as, $wanted, true) || abs($amount) < 0.005) {
                    continue;
                }

                $label = __('sales_dashboard.doc.'.($type === 'sale' ? 'invoice' : $type));
                if ($as === 'refund') {
                    $label .= ' — '.__('sales_dashboard.drill.refund_leg');
                }

                $rows[] = [
                    'entry_id' => (int) $m->entry_id,
                    'reference_type' => (string) $m->reference_type,
                    'invoice_id' => $m->invoice_id ? (int) $m->invoice_id : null,
                    'code' => $m->reference_number ?: '#'.$m->entry_id,
                    'kind' => $as === 'refund' ? 'refund' : $type,
                    'kind_label' => $label,
                    'client_id' => (int) $m->client_id,
                    'date' => substr((string) $m->date, 0, 10),
                    'employee' => $m->employee_name ?: '—',
                    'description' => $m->description,
                    'amount' => round($amount, 2),
                ];
            }
        }

        // الأحدثُ أولاً — كما في «آخر الحركات»
        usort($rows, fn ($a, $b) => [$b['date'], $b['entry_id']] <=> [$a['date'], $a['entry_id']]);

        $clients = $this->clientInfo(array_column($rows, 'client_id'));
        $rows = self::attachDocumentUrls(collect($rows))->all();

        foreach ($rows as &$row) {
            $row['client'] = $clients[$row['client_id']]['name'] ?? '—';
            $row['client_url'] = $clients[$row['client_id']]['url'] ?? null;
            unset($row['reference_type'], $row['invoice_id']);
        }

        return $rows;
    }

    /**
     * أعمدةُ مستنداتِ عميلٍ واحد — القائمةُ الوحيدةُ التي صفُّها مستندٌ لا عميل.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function documentColumns(): array
    {
        return [
            ['key' => 'code', 'label' => __('sales_dashboard.table.document'), 'type' => 'doc'],
            ['key' => 'kind_label', 'label' => __('sales_dashboard.table.type'), 'type' => 'kind'],
            ['key' => 'date', 'label' => __('sales_dashboard.table.date'), 'type' => 'date'],
            ['key' => 'employee', 'label' => __('sales_dashboard.table.employee'), 'type' => 'text'],
            ['key' => 'description', 'label' => __('sales_dashboard.drill.cols.description'), 'type' => 'longtext'],
            ['key' => 'amount', 'label' => __('sales_dashboard.table.amount'), 'type' => 'money', 'sum' => true],
        ];
    }

    /**
     * رابطُ كل مستندٍ إلى شاشته — وإلا القيدُ نفسُه.
     *
     * الربطُ كما في `JournalEntryController`: الفاتورةُ والمرتجعُ بـ`invoice_id`،
     * والإشعارُ بـ`credit_notifications.credit_number`، وسندُ القبض بـ`receipts.code`.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  تحمل reference_type و invoice_id و code و entry_id
     * @return Collection<int, array<string, mixed>>
     */
    public static function attachDocumentUrls(Collection $rows): Collection
    {
        $codes = fn (array $types) => $rows->whereIn('reference_type', $types)->pluck('code')->filter()->unique()->values();

        $creditNotes = collect();
        foreach ($codes([ReferenceType::CREDIT_NOTE->value])->chunk(1000) as $chunk) {
            $creditNotes = $creditNotes->union(DB::table('credit_notifications')->whereIn('credit_number', $chunk->all())->pluck('id', 'credit_number'));
        }

        $receipts = collect();
        foreach ($codes([ReferenceType::CUSTOMER_COLLECTION->value, ReferenceType::RECEIPT_VOUCHER->value])->chunk(1000) as $chunk) {
            $receipts = $receipts->union(DB::table('receipts')->whereIn('code', $chunk->all())->pluck('id', 'code'));
        }

        $link = fn (string $name, $id) => $id && Route::has($name) ? route($name, $id) : null;

        return $rows->map(function ($row) use ($creditNotes, $receipts, $link) {
            $row['url'] = match ($row['reference_type']) {
                ReferenceType::SALES_INVOICE->value => $link('invoices.show', $row['invoice_id']),
                ReferenceType::SALES_RETURN->value => $link('ReturnIInvoices.show', $row['invoice_id']),
                ReferenceType::CREDIT_NOTE->value => $link('CreditNotes.show', $creditNotes[$row['code']] ?? null),
                ReferenceType::CUSTOMER_COLLECTION->value, ReferenceType::RECEIPT_VOUCHER->value => $link('incomes.show', $receipts[$row['code']] ?? null),
                default => null,
            } ?? $link('journal.show', $row['entry_id']);

            return $row;
        });
    }

    /* ══════════════ العملاء ══════════════ */

    protected function clients(ReportPeriod $period, string $key): ?array
    {
        $ledger = new SalesLedger(null, null, $this->branchIds);
        $keep = fn ($clientId) => $this->onlyClients === null || isset($this->onlyClients[(int) $clientId]);

        if ($key === 'net') {
            $values = [];
            foreach ($ledger->periodTotals($period) as $clientId => $t) {
                if (! $keep($clientId)) {
                    continue;
                }
                $returns = $t['returns'] + $t['credit_notes'];
                $value = [
                    'invoices' => (int) $t['sales_count'],
                    'sales' => round($t['sales'], 2),
                    'returns' => round($returns, 2),
                    'net' => round($t['sales'] - $returns, 2),
                    'collections' => round($t['collections'], 2),
                ];
                if (abs($value['sales']) + abs($value['returns']) + abs($value['collections']) > 0.009) {
                    $values[(int) $clientId] = $value;
                }
            }

            return $this->clientList(
                $period,
                __('sales_dashboard.drill.titles.clients_net'),
                __('sales_dashboard.drill.hints.clients_net'),
                $values,
                [
                    ['key' => 'invoices', 'label' => __('sales_dashboard.table.invoices'), 'type' => 'number', 'sum' => true],
                    ['key' => 'sales', 'label' => __('sales_dashboard.table.sales'), 'type' => 'money', 'sum' => true],
                    ['key' => 'returns', 'label' => __('sales_dashboard.table.returns'), 'type' => 'money', 'sum' => true],
                    ['key' => 'net', 'label' => __('sales_dashboard.table.net'), 'type' => 'money', 'sum' => true],
                    ['key' => 'collections', 'label' => __('sales_dashboard.table.collections'), 'type' => 'money', 'sum' => true],
                ],
                'net',
            );
        }

        if (! in_array($key, ['debtors', 'credit'], true)) {
            return null;
        }

        // المبلغُ موجبٌ في العمودين ليصحّ المجموع، والرصيدُ بإشارته كما لوحة العملاء
        $amountKey = $key === 'debtors' ? 'debt' : 'credit';
        $values = [];
        foreach ($this->balanceMap($period) as $clientId => $balance) {
            if ($keep($clientId) && ($key === 'debtors' ? $balance > 0.009 : $balance < -0.009)) {
                $values[(int) $clientId] = [$amountKey => round(abs($balance), 2)];
            }
        }

        return $this->clientList(
            $period,
            __('sales_dashboard.drill.titles.clients_'.$key),
            __('sales_dashboard.drill.hints.balances', ['date' => $period->to->toDateString()]),
            $values,
            [['key' => $amountKey, 'label' => __('sales_dashboard.drill.cols.'.$amountKey), 'type' => 'money', 'sum' => true]],
            $amountKey,
        );
    }

    /* ══════════════ بطاقاتٌ من المستندات ══════════════ */

    protected function paymentStatus(ReportPeriod $period, int $status): ?array
    {
        $labels = [
            SalesDashboardMetrics::STATUS_PAID => 'paid',
            SalesDashboardMetrics::STATUS_PARTIALLY_PAID => 'partially_paid',
            SalesDashboardMetrics::STATUS_UNPAID => 'unpaid',
        ];
        if (! isset($labels[$status])) {
            return null;
        }

        // شروطُ `SalesDashboardMetrics::paymentStatusMix()` نفسُها
        $rows = DB::table('invoices as i')
            ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.created_by')
            ->whereBetween('i.invoice_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($this->branchIds !== [], fn ($q) => $q->where(fn ($w) => SalesLedger::matchClientNodes($w, 'c', $this->branchIds)))
            ->where('i.payment_status', $status)
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'i.invoice_date'))
            ->orderByDesc('i.invoice_date')->orderByDesc('i.id')
            ->get(['i.id', 'i.code', 'i.type', 'i.client_id', 'i.invoice_date', 'i.grand_total', 'u.name as employee'])
            ->filter(fn ($r) => $this->onlyClients === null || isset($this->onlyClients[(int) $r->client_id]))
            ->map(fn ($r) => [
                'code' => $r->code ?: '#'.$r->id,
                'url' => Route::has($r->type === 'returned' ? 'ReturnIInvoices.show' : 'invoices.show')
                    ? route($r->type === 'returned' ? 'ReturnIInvoices.show' : 'invoices.show', $r->id) : null,
                'client_id' => (int) $r->client_id,
                'date' => substr((string) $r->invoice_date, 0, 10),
                'employee' => $r->employee ?: '—',
                'amount' => round((float) $r->grand_total, 2),
            ])
            ->values()->all();

        return $this->clientList(
            $period,
            __('sales_dashboard.drill.titles.status', ['status' => __('sales_dashboard.status.'.$labels[$status])]),
            __('sales_dashboard.drill.hints.status'),
            self::groupByClient($rows, ['amount'], 'invoices'),
            [
                ['key' => 'invoices', 'label' => __('sales_dashboard.table.invoices'), 'type' => 'number', 'sum' => true],
                ['key' => 'amount', 'label' => __('sales_dashboard.table.amount'), 'type' => 'money', 'sum' => true],
            ],
            'amount',
        );
    }

    protected function product(ReportPeriod $period, int $productId): ?array
    {
        // شروطُ `SalesDashboardMetrics::topProducts()` نفسُها
        $rows = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('clients as c', 'c.id', '=', 'i.client_id')
            ->where('ii.product_id', $productId)
            ->whereBetween('i.invoice_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->whereRaw('EXISTS (SELECT 1 FROM journal_entries e WHERE e.invoice_id = i.id AND e.status = 1)')
            ->when($this->branchIds !== [], fn ($q) => $q->where(fn ($w) => SalesLedger::matchClientNodes($w, 'c', $this->branchIds)))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'i.invoice_date'))
            ->orderByDesc('i.invoice_date')->orderByDesc('i.id')
            ->get(['i.id', 'i.code', 'i.type', 'i.client_id', 'i.invoice_date', 'ii.quantity', 'ii.unit_name', 'ii.unit_price', 'ii.total'])
            ->map(function ($r) {
                // بندُ المرتجع سالبٌ — فمجموعُ العمود هو «الصافي» في البطاقة
                $isReturn = $r->type === 'returned';
                $sign = $isReturn ? -1 : 1;
                $route = $isReturn ? 'ReturnIInvoices.show' : 'invoices.show';

                return [
                    'code' => $r->code ?: '#'.$r->id,
                    'url' => Route::has($route) ? route($route, $r->id) : null,
                    'kind' => $isReturn ? 'return' : 'sale',
                    'kind_label' => __('sales_dashboard.doc.'.($isReturn ? 'return' : 'invoice')),
                    'client_id' => (int) $r->client_id,
                    'date' => substr((string) $r->invoice_date, 0, 10),
                    'quantity' => round($sign * (float) $r->quantity, 2),
                    'unit' => $r->unit_name,
                    'unit_price' => round((float) $r->unit_price, 2),
                    'amount' => round($sign * (float) $r->total, 2),
                ];
            })
            ->all();

        return $this->clientList(
            $period,
            __('sales_dashboard.drill.titles.product', ['name' => DB::table('products')->where('id', $productId)->value('name') ?? '—']),
            __('sales_dashboard.drill.hints.product'),
            self::groupByClient($rows, ['quantity', 'amount'], 'documents'),
            [
                ['key' => 'documents', 'label' => __('sales_dashboard.drill.cols.documents'), 'type' => 'number', 'sum' => true],
                ['key' => 'quantity', 'label' => __('sales_dashboard.table.quantity'), 'type' => 'number', 'sum' => true],
                ['key' => 'amount', 'label' => __('sales_dashboard.table.amount'), 'type' => 'money', 'sum' => true],
            ],
            'amount',
        );
    }

    /* ══════════════ الموظّف: مصروفات وزيارات ══════════════ */

    protected function expenses(ReportPeriod $period, int $userId): array
    {
        // شروطُ `SalesDashboardMetrics::expensesByEmployee()` نفسُها
        $rows = DB::table('expenses')
            ->where('created_by', $userId)
            ->whereNull('reversed_at')
            ->when($this->branchIds !== [], fn ($q) => $q->whereIn('branch_id', $this->branchIds))
            ->whereBetween('date', [$period->from->copy()->startOfDay()->toDateTimeString(), $period->to->copy()->endOfDay()->toDateTimeString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'date'))
            ->orderByDesc('date')->orderByDesc('id')
            ->get(['id', 'code', 'date', 'description', 'amount'])
            ->map(fn ($r) => [
                'code' => $r->code ?: '#'.$r->id,
                'url' => Route::has('expenses.show') ? route('expenses.show', $r->id) : null,
                'date' => substr((string) $r->date, 0, 10),
                'description' => $r->description,
                'amount' => round((float) $r->amount, 2),
            ])
            ->all();

        return [
            'title' => __('sales_dashboard.drill.titles.expenses', ['name' => $this->userName($userId)]),
            'hint' => __('sales_dashboard.drill.hints.expenses'),
            'columns' => [
                ['key' => 'code', 'label' => __('sales_dashboard.table.document'), 'type' => 'doc'],
                ['key' => 'date', 'label' => __('sales_dashboard.table.date'), 'type' => 'date'],
                ['key' => 'description', 'label' => __('sales_dashboard.drill.cols.description'), 'type' => 'longtext'],
                ['key' => 'amount', 'label' => __('sales_dashboard.table.amount'), 'type' => 'money', 'sum' => true],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * زياراتُ الموظّف — صفوفُ `ClientDashboardService::visitRows()` نفسُها التي
     * عُدّت منها الخانة، بنتيجة كلٍّ وتفسيرها.
     *
     * @param  string|null  $group  مفتاحٌ من `SalesDashboardMetrics::VISIT_RESULT_GROUPS`
     */
    protected function visits(ReportPeriod $period, int $userId, ?string $group): ?array
    {
        if ($group !== null && ! isset(SalesDashboardMetrics::VISIT_RESULT_GROUPS[$group])) {
            return null;
        }

        // الزياراتُ المكتملة من خطّ السير بنتيجتها — ما عُدّت منه الخانة (`visitsByEmployee()`)
        [, $planRows] = SalesDashboardMetrics::visitAndPlanRows($this->branchIds, $period);
        $tones = ['success' => 'collection', 'negative' => 'return'];
        $rows = [];

        foreach ($planRows as $plan) {
            $inGroup = $plan['outcome'] ?? null;
            if ($inGroup === null || (int) ($plan['employee_id'] ?? 0) !== $userId || ($group !== null && $inGroup !== $group)) {
                continue;
            }

            $meta = ClientDashboardService::VISIT_OUTCOMES[! empty($plan['return_later']) ? 'return_later' : ($inGroup === 'success' ? 'success' : 'failed')];
            $rows[] = [
                'client_id' => (int) $plan['client_id'],
                'date' => $plan['date'],
                'state' => $meta['label'],
                'description' => $meta['hint'],
                'tone' => $tones[$inGroup],
            ];
        }

        return $this->visitClients(
            $period,
            __('sales_dashboard.drill.titles.visits', ['name' => $this->userName($userId)])
                .($group !== null ? ' — '.__('sales_dashboard.visits.'.$group) : ''),
            __('sales_dashboard.drill.hints.visits'),
            $rows,
            __('sales_dashboard.drill.cols.outcome'),
        );
    }

    /**
     * زياراتٌ أو صفوفُ خطّ سيرٍ معروضةً عميلاً عميلاً: عددُها في الشريحة،
     * وآخرُ حالةٍ وتاريخُها — فالصفُّ عميلٌ كما في لوحة العملاء.
     *
     * @param  array<int, array<string, mixed>>  $rows  تحمل client_id و date و state و description و tone
     */
    protected function visitClients(ReportPeriod $period, string $title, string $hint, array $rows, string $stateLabel): array
    {
        usort($rows, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        $values = [];
        foreach ($rows as $row) {
            $id = (int) $row['client_id'];
            // الصفوفُ مرتّبةٌ بالأحدث — فأوّلُ ما يُرى لعميلٍ هو آخرُ حالته
            $values[$id] ??= [
                'visits' => 0,
                'date' => $row['date'],
                'state' => $row['state'],
                'description' => $row['description'],
                'tone' => $row['tone'],
            ];
            $values[$id]['visits']++;
        }

        return $this->clientList($period, $title, $hint, $values, [
            ['key' => 'visits', 'label' => __('sales_dashboard.table.visits'), 'type' => 'number', 'sum' => true],
            ['key' => 'state', 'label' => $stateLabel, 'type' => 'kind'],
            ['key' => 'date', 'label' => __('sales_dashboard.drill.cols.last_state_date'), 'type' => 'date'],
            ['key' => 'description', 'label' => __('sales_dashboard.drill.cols.description'), 'type' => 'longtext'],
        ], 'visits');
    }

    /**
     * خطُّ سير الموظّف — صفوفُ `ClientDashboardService::planRows()` نفسُها.
     *
     * @param  string|null  $group  مفتاحٌ من `SalesDashboardMetrics::PLAN_STATE_GROUPS`
     */
    protected function plan(ReportPeriod $period, int $userId, ?string $group): ?array
    {
        if ($group !== null && ! isset(SalesDashboardMetrics::PLAN_STATE_GROUPS[$group])) {
            return null;
        }

        [, $planRows] = SalesDashboardMetrics::visitAndPlanRows($this->branchIds, $period);
        $tones = ['completed' => 'collection', 'in_progress' => 'sale', 'missed' => 'return', 'pending' => 'adjustment'];
        $rows = [];

        foreach ($planRows as $plan) {
            $inGroup = SalesDashboardMetrics::groupOf(SalesDashboardMetrics::PLAN_STATE_GROUPS, $plan['key']);
            if ($inGroup === null || (int) ($plan['employee_id'] ?? 0) !== $userId || ($group !== null && $inGroup !== $group)) {
                continue;
            }

            $rows[] = [
                'client_id' => (int) $plan['client_id'],
                'date' => $plan['date'],
                'state' => __('sales_dashboard.visits.'.$inGroup)
                    .($plan['key'] === 'justified' ? ' — '.__('sales_dashboard.visits.justified_tag') : ''),
                'description' => ClientDashboardService::PLAN_COVERAGE[$plan['key']]['hint'] ?? null,
                'tone' => $tones[$inGroup],
            ];
        }

        return $this->visitClients(
            $period,
            __('sales_dashboard.drill.titles.plan', ['name' => $this->userName($userId)])
                .($group !== null ? ' — '.__('sales_dashboard.visits.'.$group) : ''),
            __('sales_dashboard.drill.hints.plan'),
            $rows,
            __('sales_dashboard.drill.cols.state'),
        );
    }

    /* ══════════════ مساعدات ══════════════ */

    /**
     * بطاقةُ العميل — بحقول `ClientDashboardService::rows()` نفسِها: الاسمُ
     * المعروض والكودُ والجوال، وحالتُه ووضعُه بلونَيهما من `statuses`.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    protected function clientInfo(array $ids): array
    {
        $info = [];
        $canLink = Route::has('clients.show');

        foreach (array_chunk(array_values(array_unique(array_filter($ids))), 1000) as $chunk) {
            $rows = DB::table('clients as c')
                ->leftJoin('statuses as st', 'st.id', '=', 'c.status_id')
                ->leftJoin('statuses as sg', 'sg.id', '=', 'c.registration_stage_id')
                ->whereIn('c.id', $chunk)
                ->get([
                    'c.id', 'c.trade_name', 'c.first_name', 'c.last_name', 'c.code', 'c.phone', 'c.mobile',
                    'st.name as status_name', 'st.color as status_color',
                    'sg.name as stage_name', 'sg.color as stage_color',
                ]);

            foreach ($rows as $c) {
                $name = $c->trade_name ?: trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: '—';

                $info[(int) $c->id] = [
                    // الاسمُ التجاريُّ وإلا الشخصي — كما `SalesDashboardMetrics::clientNames()`.
                    // والأسماءُ مُدخلةٌ يدوياً وفيها مسافاتٌ مكرّرة — كما لوحة العملاء
                    'name' => preg_replace('/\s+/u', ' ', trim($name)) ?: $name,
                    'code' => $c->code,
                    'phone' => trim((string) $c->mobile, " ,\t") ?: trim((string) $c->phone, " ,\t"),
                    'status' => $c->status_name ?: __('sales_dashboard.drill.cols.no_status'),
                    'status_color' => $c->status_color ?: '#999999',
                    'stage' => $c->stage_name ?: __('sales_dashboard.drill.cols.no_stage'),
                    'stage_color' => $c->stage_color ?: '#999999',
                    'url' => $canLink ? route('clients.show', $c->id) : null,
                ];
            }
        }

        return $info;
    }

    /* ══════════════ قائمةُ العملاء — نمطُ لوحة العملاء ══════════════ */

    /**
     * قائمةُ عملاءٍ بأعمدة لوحة العملاء حرفاً: بطاقةُ العميل، ثم أعمدةُ
     * الشريحة، ثم آخرُ زيارةٍ والرصيد.
     *
     * الرصيدُ من القيود حتى نهاية الفترة (`SalesLedger::balancesAsOf()`) لا من
     * `accounts.balance` — فيبقى كلُّ رقمٍ في اللوحة من مصدرٍ واحد. وآخرُ زيارةٍ
     * من `ClientDashboardService::lastVisitDates()` نفسِها (اليدويةُ وصفُّ خطّ
     * السير المكتمل، لا تسجيلَ الموقع) فلا يختلف العمودان بين الشاشتين.
     *
     * @param  array<int, array<string, mixed>>  $values  `[clientId => قيمُ الشريحة]`
     * @param  array<int, array<string, mixed>>  $metricColumns  أعمدةُ الشريحة بين البطاقة وآخرِ زيارة
     * @param  string|null  $sortKey  مفتاحُ ترتيبٍ تنازلي — وإلا فبالاسم
     */
    protected function clientList(ReportPeriod $period, string $title, ?string $hint, array $values, array $metricColumns, ?string $sortKey = null): array
    {
        $ids = array_map('intval', array_keys($values));
        $profiles = $this->clientInfo($ids);
        $balances = $this->balanceMap($period);
        $lastVisits = $ids === [] ? [] : app(ClientDashboardService::class)->lastVisitDates($ids);

        $rows = [];
        foreach ($values as $clientId => $value) {
            $clientId = (int) $clientId;
            $profile = $profiles[$clientId] ?? [];
            $last = $lastVisits[$clientId] ?? null;

            $rows[] = array_merge([
                'client_id' => $clientId,
                'code' => $profile['code'] ?? null,
                'name' => $profile['name'] ?? '—',
                'client_url' => $profile['url'] ?? null,
                'phone' => $profile['phone'] ?? null,
                'status' => $profile['status'] ?? __('sales_dashboard.drill.cols.no_status'),
                'status_color' => $profile['status_color'] ?? '#999999',
                'stage' => $profile['stage'] ?? __('sales_dashboard.drill.cols.no_stage'),
                'stage_color' => $profile['stage_color'] ?? '#999999',
                'last_visit' => $last,
                'last_visit_days' => self::daysSince($last),
                'balance' => round($balances[$clientId] ?? 0, 2),
            ], $value);
        }

        usort($rows, $sortKey === null
            ? fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name'])
            : fn ($a, $b) => ($b[$sortKey] ?? 0) <=> ($a[$sortKey] ?? 0));

        return [
            'title' => $title,
            'hint' => $hint,
            'unit' => 'clients',
            'columns' => array_merge(self::clientCardColumns(), $metricColumns, [
                ['key' => 'last_visit_days', 'label' => __('sales_dashboard.drill.cols.last_visit'), 'type' => 'visit'],
                ['key' => 'balance', 'label' => __('sales_dashboard.table.balance'), 'type' => 'money'],
            ]),
            'rows' => $rows,
        ];
    }

    /** أعمدةُ بطاقة العميل — ترتيبُ لوحة العملاء نفسُه */
    protected static function clientCardColumns(): array
    {
        return [
            ['key' => 'code', 'label' => __('sales_dashboard.drill.cols.code'), 'type' => 'text'],
            ['key' => 'name', 'label' => __('sales_dashboard.table.client'), 'type' => 'client'],
            ['key' => 'phone', 'label' => __('sales_dashboard.drill.cols.phone'), 'type' => 'text'],
            ['key' => 'status', 'label' => __('sales_dashboard.table.status'), 'type' => 'status'],
            // الحالةُ نشط/موقوف وحدها؛ «الوضع» تصنيفُ العميل نفسِه (ضريبي، بلدية…)
            ['key' => 'stage', 'label' => __('sales_dashboard.drill.cols.stage'), 'type' => 'status'],
        ];
    }

    /**
     * يجمع صفوفاً تحمل `client_id` عميلاً عميلاً: عدَدُها ومجاميعُ حقولها.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $sumKeys  حقولٌ رقميةٌ تُجمع
     * @param  string|null  $countKey  اسمُ عمودِ العدّ — `null` فلا عدّ
     * @return array<int, array<string, mixed>>
     */
    protected static function groupByClient(array $rows, array $sumKeys, ?string $countKey = null): array
    {
        $values = [];

        foreach ($rows as $row) {
            $id = (int) ($row['client_id'] ?? 0);

            if (! isset($values[$id])) {
                $values[$id] = array_fill_keys($sumKeys, 0.0) + ($countKey !== null ? [$countKey => 0] : []);
            }

            if ($countKey !== null) {
                $values[$id][$countKey]++;
            }

            foreach ($sumKeys as $key) {
                $values[$id][$key] += (float) ($row[$key] ?? 0);
            }
        }

        foreach ($values as &$value) {
            foreach ($sumKeys as $key) {
                $value[$key] = round($value[$key], 2);
            }
        }

        return $values;
    }

    /** رصيدُ كل عميلٍ حتى نهاية الفترة — استعلامٌ واحدٌ لكل قائمة */
    protected function balanceMap(ReportPeriod $period): array
    {
        return $this->balanceMap ??= (new SalesLedger(null, null, $this->branchIds))->balancesAsOf($period->to);
    }

    /** أيامٌ كاملةٌ من تاريخٍ حتى اليوم — كـ`ClientDashboardService::daysSince()` */
    protected static function daysSince(?string $date): ?int
    {
        if (! $date) {
            return null;
        }

        $from = Carbon::parse(substr($date, 0, 10))->startOfDay()->getTimestamp();

        return (int) round((Carbon::today()->getTimestamp() - $from) / 86400);
    }

    protected function userName(int $userId): string
    {
        return $userId > 0
            ? (DB::table('users')->where('id', $userId)->value('name') ?? '—')
            : __('sales_dashboard.common.unassigned');
    }
}
