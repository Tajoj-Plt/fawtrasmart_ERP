<?php

namespace Modules\Purchases\Support;

use App\Enums\PurchaseDocumentType;
use App\Enums\ReferenceType;
use App\Support\ClosedPeriodRanges;
use App\Support\CurrencySettings;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * «ماذا وراء هذا الرقم؟» — قوائمُ لوحة المشتريات.
 *
 * نظيرةُ `Modules\Sales\Support\SalesDashboardDrillDown` دالّةً بدالّة،
 * والمورّدُ فيها مكانُ العميل. وقاعدتُها واحدة:
 *
 * **١. كلُّ قائمةٍ من الاستعلام الذي بُني منه رقمُها.** المستنداتُ من
 * `SupplierLedger::movements()` بقاعدة `accumulate()` حرفاً (المرتجعُ
 * والإشعارُ طرفُهما المدين، ودائنُهما تسوية)، والموردون من `periodTotals()`
 * و`balancesAsOf()`، وبطاقاتُ المستندات بشروط استعلامها في
 * `PurchaseDashboardMetrics`. فمجموعُ عمودِ المبلغ يساوي الرقمَ المنقور.
 *
 * **٢. الصفُّ مورّدٌ لا مستند.** كلُّ قائمةٍ تُجمع مورّداً مورّداً وتُعرض
 * ببطاقته: الكود، المورّد، الجوال، الحالة، ثم أعمدةُ الشريحة، ثم آخرُ
 * حركةٍ والرصيد. يُستثنى ما لا مورّدَ لصفِّه: حركةُ مورّدٍ بعينه
 * (`supplier.{id}`) فهي تفصيلُ مستنداتِ صفٍّ واحد.
 *
 * الصيغة: `group.key[.sub]` →
 *
 *     docs.{purchase|returns|return|debit_note|payment|adjustment|movement|all}
 *     suppliers.{net|creditors|credit}
 *     supplier.{id}                                  كل حركة المورّد في الفترة
 *     emp.{userId}.{all|purchase|returns|payment}
 *     status.{paid|partially_paid|unpaid}
 *     receiving.{received|partially_received|not_received}
 *     product.{id} · orders.open
 *     trend.{Y-m[-d]}.{purchase|payment|movement}
 */
class PurchaseDashboardDrillDown
{
    /** ما يدخل في كل قائمة مستندات — مفاتيحُ `SupplierLedger::classify()` */
    protected const KINDS = [
        'purchase' => ['purchase'],
        'returns' => ['return', 'debit_note'],
        'return' => ['return'],
        'debit_note' => ['debit_note'],
        'payment' => ['payment'],
        'adjustment' => ['adjustment'],
        'movement' => ['purchase', 'payment'],
        'all' => ['purchase', 'return', 'debit_note', 'payment', 'adjustment'],
    ];

    /** عنوانُ كل قائمة */
    protected const TITLES = [
        'purchase' => 'فواتير الشراء',
        'returns' => 'المرتجعات والإشعارات المدينة',
        'return' => 'مرتجعات الشراء',
        'debit_note' => 'الإشعارات المدينة',
        'payment' => 'المدفوع للموردين',
        'adjustment' => 'التسويات الأخرى',
        'movement' => 'المشتريات والسداد',
        'all' => 'كل الحركات',
    ];

    /** اسمُ نوع الحركة في عمود «النوع» */
    protected const KIND_LABELS = [
        'purchase' => 'فاتورة شراء',
        'return' => 'مرتجع شراء',
        'debit_note' => 'إشعار مدين',
        'payment' => 'سداد',
        'adjustment' => 'تسوية',
    ];

    /** رصيدُ كل مورّد حتى نهاية الفترة — يُحسب مرّةً واحدةً للطلب */
    protected ?array $balanceMap = null;

    /** تاريخُ آخر حركةٍ لكل مورّد — كذلك */
    protected ?array $lastMovementMap = null;

    /**
     * @param  array<int, int>  $branchIds  نطاقُ اللوحة — فارغٌ يعني الكل
     * @param  bool  $unbranched  «غير محدّد» — ما لا عقدةَ فرعٍ له وحده
     * @param  array<int, int>|null  $userIds  محورُ الأشخاص — `null` بلا قيد.
     *                                         القائمةُ تشرح رقمَ اللوحة، فلو
     *                                         خرجت عن نطاقه لعرضت صفوفاً لا
     *                                         يُجمَع منها الرقمُ المنقور.
     */
    public function __construct(
        protected array $branchIds,
        protected bool $unbranched = false,
        protected ?array $userIds = null,
    ) {}

    /**
     * قيدُ المنشئ على استعلامٍ — بنفس عقد
     * {@see PurchaseDashboardMetrics::scopeCreator()}.
     */
    protected function scopeCreator($query, string $column = 'created_by')
    {
        if ($this->userIds !== null) {
            $query->whereIn($column, $this->userIds ?: [0]);
        }

        return $query;
    }

    /**
     * @return array{title: string, hint?: string|null, unit?: string, columns: array, rows: array}|null
     */
    public function resolve(string $segment, ReportPeriod $period): ?array
    {
        [$group, $key, $sub] = array_pad(explode('.', $segment, 3), 3, null);
        $id = ctype_digit((string) $key) ? (int) $key : null;

        return match ($group) {
            'docs' => $this->documents($period, (string) $key),
            'suppliers' => $this->suppliers($period, (string) $key),
            'supplier' => $id ? $this->supplierMovements($period, $id) : null,
            'emp' => $id !== null ? $this->employeeDocuments($period, $id, $sub ?? 'all') : null,
            'status' => $this->invoiceStatus($period, 'payment_status', (string) $key),
            'receiving' => $this->invoiceStatus($period, 'receiving_status', (string) $key),
            'product' => $id ? $this->product($period, $id) : null,
            'orders' => $key === 'open' ? $this->openOrders($period) : null,
            'trend' => $this->trendBucket($period, (string) $key, $sub ?? 'movement'),
            default => null,
        };
    }

    /* ══════════════ المستندات ══════════════ */

    protected function documents(ReportPeriod $period, string $kind): ?array
    {
        $rows = $this->documentRows($period, $kind);

        return $rows === null ? null : $this->documentSuppliers($period, self::TITLES[$kind], $rows);
    }

    protected function employeeDocuments(ReportPeriod $period, int $userId, string $kind): ?array
    {
        $rows = $this->documentRows($period, $kind, null, $userId);

        return $rows === null ? null : $this->documentSuppliers(
            $period,
            self::TITLES[$kind].' — '.$this->userName($userId),
            $rows,
        );
    }

    protected function trendBucket(ReportPeriod $period, string $bucket, string $kind): ?array
    {
        if (! preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $bucket) || ! in_array($kind, ['purchase', 'payment', 'movement'], true)) {
            return null;
        }

        return $this->documentSuppliers(
            $period,
            self::TITLES[$kind].' — '.$bucket,
            $this->documentRows($period, $kind, null, null, $bucket) ?? [],
        );
    }

    /**
     * صفوفُ المستندات معروضةً مورّداً مورّداً — مجموعُ «المبلغ» هو الرقمُ المنقور.
     *
     * @param  array<int, array<string, mixed>>  $rows  مخرجاتُ `documentRows()`
     */
    protected function documentSuppliers(ReportPeriod $period, string $title, array $rows): array
    {
        return $this->supplierList(
            $period,
            $title,
            'موردو هذا الرقم — كل قيدٍ مرحَّلٍ منسوبٌ لمورّده، ومجموعُ عمود المبلغ يساوي الرقم المنقور. اضغط اسم المورّد لفتح ملفّه.',
            self::groupBySupplier($rows, ['amount'], 'documents'),
            [
                ['key' => 'documents', 'label' => 'المستندات', 'type' => 'number', 'sum' => true],
                ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'money', 'sum' => true],
            ],
            'amount',
        );
    }

    /**
     * حركةُ مورّدٍ واحد — القائمةُ الوحيدةُ التي صفُّها مستندٌ لا مورّد.
     */
    protected function supplierMovements(ReportPeriod $period, int $supplierId): ?array
    {
        $ledger = $this->ledger($supplierId);
        $totals = $ledger->periodTotals($period)[$supplierId] ?? SupplierLedger::emptyTotals();
        $balance = $ledger->balancesAsOf($period->to)[$supplierId] ?? 0.0;
        $net = $totals['purchases'] - $totals['returns'] - $totals['debit_notes'];

        return [
            'title' => 'حركة المورّد: '.($this->supplierInfo([$supplierId])[$supplierId]['name'] ?? '—'),
            'hint' => 'صافي المشتريات '.CurrencySettings::number($net)
                .' · المدفوع '.CurrencySettings::number($totals['payments'])
                .' · الرصيد حتى نهاية الفترة '.CurrencySettings::number($balance),
            'columns' => [
                ['key' => 'code', 'label' => 'المستند', 'type' => 'doc'],
                ['key' => 'kind_label', 'label' => 'النوع', 'type' => 'kind'],
                ['key' => 'date', 'label' => 'التاريخ', 'type' => 'date'],
                ['key' => 'employee', 'label' => 'الموظّف', 'type' => 'text'],
                ['key' => 'description', 'label' => 'البيان', 'type' => 'longtext'],
                ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'money', 'sum' => true],
            ],
            'rows' => $this->documentRows($period, 'all', $supplierId),
        ];
    }

    /**
     * صفوفُ المستندات — المبلغُ بقاعدة `SupplierLedger::accumulate()`:
     *
     *     فاتورة شراء   دائن − مدين
     *     مرتجع/إشعار   مدينُه قيمتُه، ودائنُه (ما ردّه المورّد نقداً) تسوية
     *     سداد          مدين − دائن
     *     غيرها         دائن − مدين  (تسوية)
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function documentRows(ReportPeriod $period, string $kind, ?int $supplierId = null, ?int $employeeId = null, ?string $bucket = null): ?array
    {
        if (! isset(self::KINDS[$kind])) {
            return null;
        }

        $wanted = self::KINDS[$kind];
        $rows = [];

        foreach ($this->ledger($supplierId)->movements($period) as $m) {
            if ($employeeId !== null && (int) ($m->created_by_employee ?? 0) !== $employeeId) {
                continue;
            }
            if ($bucket !== null && ! str_starts_with((string) $m->date, $bucket)) {
                continue;
            }

            $type = SupplierLedger::classify($m->reference_type);
            $debit = (float) $m->debit;
            $credit = (float) $m->credit;

            $legs = match ($type) {
                'purchase' => ['purchase' => $credit - $debit],
                'return', 'debit_note' => [$type => $debit, 'adjustment' => $credit],
                'payment' => ['payment' => $debit - $credit],
                default => ['adjustment' => $credit - $debit],
            };

            foreach ($legs as $as => $amount) {
                if (! in_array($as, $wanted, true) || abs($amount) < 0.005) {
                    continue;
                }

                $label = self::KIND_LABELS[$type] ?? self::KIND_LABELS['adjustment'];
                if ($as === 'adjustment' && $type !== 'other') {
                    $label .= ' — ما ردّه المورّد نقداً';
                }

                $rows[] = [
                    'entry_id' => (int) $m->entry_id,
                    'reference_type' => (string) $m->reference_type,
                    'purchase_invoice_id' => $m->purchase_invoice_id ? (int) $m->purchase_invoice_id : null,
                    'code' => $m->reference_number ?: '#'.$m->entry_id,
                    'kind' => $as === 'adjustment' ? 'adjustment' : $type,
                    'kind_label' => $label,
                    'supplier_id' => (int) $m->supplier_id,
                    'date' => substr((string) $m->date, 0, 10),
                    'employee' => $m->employee_name ?: '—',
                    'description' => $m->description,
                    'amount' => round($amount, 2),
                ];
            }
        }

        // الأحدثُ أولاً — كما في «آخر الحركات»
        usort($rows, fn ($a, $b) => [$b['date'], $b['entry_id']] <=> [$a['date'], $a['entry_id']]);

        $suppliers = $this->supplierInfo(array_column($rows, 'supplier_id'));
        $rows = self::attachDocumentUrls(collect($rows))->all();

        foreach ($rows as &$row) {
            $row['supplier'] = $suppliers[$row['supplier_id']]['name'] ?? '—';
            $row['supplier_url'] = $suppliers[$row['supplier_id']]['url'] ?? null;
            unset($row['reference_type'], $row['purchase_invoice_id']);
        }

        return $rows;
    }

    /**
     * رابطُ كل مستندٍ إلى شاشته — وإلا القيدُ نفسُه.
     *
     * الفاتورةُ والمرتجعُ والإشعارُ المدين صفوفٌ في `purchase_invoices`
     * يحملها `journal_entries.purchase_invoice_id`. أمّا السدادُ فلا معرّفَ
     * لسنده على القيد (`reference_number` لا يطابق `payments_process`)، فيُفتح
     * قيدُه — ومنه إلى سنده.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function attachDocumentUrls(Collection $rows): Collection
    {
        $link = fn (string $name, $id) => $id && Route::has($name) ? route($name, $id) : null;

        return $rows->map(function ($row) use ($link) {
            $row['url'] = match ($row['reference_type']) {
                ReferenceType::PURCHASE_INVOICE->value => $link('invoicePurchases.show', $row['purchase_invoice_id']),
                ReferenceType::PURCHASE_RETURN->value => $link('ReturnsInvoice.show', $row['purchase_invoice_id']),
                ReferenceType::DEBIT_NOTE->value => $link('CityNotices.show', $row['purchase_invoice_id']),
                default => null,
            } ?? $link('journal.show', $row['entry_id']);

            return $row;
        });
    }

    /* ══════════════ الموردون ══════════════ */

    protected function suppliers(ReportPeriod $period, string $key): ?array
    {
        if ($key === 'net') {
            $values = [];

            foreach ($this->ledger()->periodTotals($period) as $supplierId => $t) {
                $returns = $t['returns'] + $t['debit_notes'];
                $value = [
                    'invoices' => (int) $t['purchases_count'],
                    'purchases' => round($t['purchases'], 2),
                    'returns' => round($returns, 2),
                    'net' => round($t['purchases'] - $returns, 2),
                    'payments' => round($t['payments'], 2),
                ];

                if (abs($value['purchases']) + abs($value['returns']) + abs($value['payments']) > 0.009) {
                    $values[(int) $supplierId] = $value;
                }
            }

            return $this->supplierList(
                $period,
                'صافي المشتريات لكل مورّد',
                'الصافي = المشتريات − المرتجعات − الإشعارات المدينة، والرصيد من القيود حتى نهاية الفترة.',
                $values,
                [
                    ['key' => 'invoices', 'label' => 'الفواتير', 'type' => 'number', 'sum' => true],
                    ['key' => 'purchases', 'label' => 'المشتريات', 'type' => 'money', 'sum' => true],
                    ['key' => 'returns', 'label' => 'المرتجعات', 'type' => 'money', 'sum' => true],
                    ['key' => 'net', 'label' => 'الصافي', 'type' => 'money', 'sum' => true],
                    ['key' => 'payments', 'label' => 'المدفوع', 'type' => 'money', 'sum' => true],
                ],
                'net',
            );
        }

        if (! in_array($key, ['creditors', 'credit'], true)) {
            return null;
        }

        // المبلغُ موجبٌ في العمودين ليصحّ المجموع، والرصيدُ بإشارته
        $amountKey = $key === 'creditors' ? 'due' : 'credit';
        $values = [];

        foreach ($this->balanceMap($period) as $supplierId => $balance) {
            if ($key === 'creditors' ? $balance > 0.009 : $balance < -0.009) {
                $values[(int) $supplierId] = [$amountKey => round(abs($balance), 2)];
            }
        }

        return $this->supplierList(
            $period,
            $key === 'creditors' ? 'الموردون المستحقّ لهم' : 'موردون لنا عندهم رصيد',
            'الرصيد من القيود المرحَّلة حتى '.$period->to->toDateString().' — دائنٌ موجب يعني «له علينا».',
            $values,
            [[
                'key' => $amountKey,
                'label' => $key === 'creditors' ? 'المستحق له' : 'المبلغ لنا عنده',
                'type' => 'money',
                'sum' => true,
            ]],
            $amountKey,
        );
    }

    /* ══════════════ بطاقاتٌ من المستندات ══════════════ */

    /**
     * فواتيرُ الفترة بحالةِ سدادها أو استلامها — شروطُ
     * `PurchaseDashboardMetrics::paymentStatusMix()` و`receivingMix()` نفسُها.
     */
    protected function invoiceStatus(ReportPeriod $period, string $column, string $key): ?array
    {
        $labels = $column === 'payment_status'
            ? ['paid' => 'مسدَّدة', 'partially_paid' => 'مسدَّدة جزئياً', 'unpaid' => 'غير مسدَّدة', 'returned' => 'لها مرتجع']
            : ['received' => 'مستلمة', 'partially_received' => 'مستلمة جزئياً', 'not_received' => 'غير مستلمة'];

        if (! isset($labels[$key])) {
            return null;
        }

        $returnedSql = "EXISTS (SELECT 1 FROM purchase_invoices r WHERE r.reference_id = purchase_invoices.id AND r.type = 'Return')";

        $rows = $this->invoiceQuery($period, PurchaseDocumentType::INVOICE->value)
            // «لها مرتجع» بابٌ مستقلّ في بطاقة حالة السداد — نفسُ شرط `paymentStatusMix()`
            ->when($column === 'payment_status' && $key === 'returned', fn ($q) => $q->whereRaw($returnedSql))
            ->when($column === 'payment_status' && $key !== 'returned', fn ($q) => $q->whereRaw('NOT '.$returnedSql)->where($column, $key))
            ->when($column !== 'payment_status', fn ($q) => $q->where($column, $key))
            ->get(['supplier_id', DB::raw('COUNT(*) as documents'), DB::raw('COALESCE(SUM(grand_total), 0) as amount')])
            ->map(fn ($r) => [
                'supplier_id' => (int) $r->supplier_id,
                'documents' => (int) $r->documents,
                'amount' => round((float) $r->amount, 2),
            ])
            ->all();

        return $this->supplierList(
            $period,
            'فواتير '.$labels[$key],
            'من جدول فواتير الشراء بتاريخ الفاتورة والحالة المخزَّنة عليها — المبلغ شامل الضريبة.',
            self::keyBySupplier($rows),
            [
                ['key' => 'documents', 'label' => 'الفواتير', 'type' => 'number', 'sum' => true],
                ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'money', 'sum' => true],
            ],
            'amount',
        );
    }

    /** أوامرُ الشراء المعلّقة — ليست مستنداً ماليّاً فلا تدخل أيَّ مجموع */
    protected function openOrders(ReportPeriod $period): array
    {
        $rows = $this->invoiceQuery($period, PurchaseDocumentType::ORDER->value, false)
            ->get(['supplier_id', DB::raw('COUNT(*) as orders'), DB::raw('COALESCE(SUM(grand_total), 0) as amount')])
            ->map(fn ($r) => [
                'supplier_id' => (int) $r->supplier_id,
                'orders' => (int) $r->orders,
                'amount' => round((float) $r->amount, 2),
            ])
            ->all();

        return $this->supplierList(
            $period,
            'أوامر الشراء المعلّقة',
            'أوامرُ شراءٍ بتاريخ الفترة لم تتحوّل إلى فواتير — التزامٌ قادم خارج مجاميع اللوحة.',
            self::keyBySupplier($rows),
            [
                ['key' => 'orders', 'label' => 'الأوامر', 'type' => 'number', 'sum' => true],
                ['key' => 'amount', 'label' => 'القيمة', 'type' => 'money', 'sum' => true],
            ],
            'amount',
        );
    }

    /**
     * بنودُ صنفٍ في مستندات الفترة — شروطُ `ProductPurchaseLedger` نفسُها:
     * البنودُ في `invoice_items` (جدولٌ مشترك، ورابطُه `purchase_invoice_id`)،
     * والترحيلُ بـ`EXISTS` لا بضمٍّ (المستندُ قد يحمل قيدَي فاتورةٍ وسداد).
     * والصافي = المشتريات − المرتجعات − الإشعارات، والكمّيةُ لا تعرف الإشعار.
     */
    protected function product(ReportPeriod $period, int $productId): array
    {
        $invoice = "pi.type = '".PurchaseDocumentType::INVOICE->value."'";
        $return = "pi.type = '".PurchaseDocumentType::RETURN->value."'";

        $rows = DB::table('invoice_items as ii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'ii.purchase_invoice_id')
            ->where('ii.product_id', $productId)
            ->whereIn('pi.type', [
                PurchaseDocumentType::INVOICE->value,
                PurchaseDocumentType::RETURN->value,
                PurchaseDocumentType::DEBIT_NOTE->value,
            ])
            ->whereBetween('pi.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('journal_entries as je')
                ->whereColumn('je.purchase_invoice_id', 'pi.id')->where('je.status', 1))
            ->when($this->branchIds !== [] && ! $this->unbranched, fn ($q) => $q->whereIn('pi.branch_id', $this->branchIds))
            ->when($this->unbranched, fn ($q) => $q->whereNull('pi.branch_id'))
            ->tap(fn ($q) => $this->scopeCreator($q, 'pi.created_by'))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'pi.date'))
            ->groupBy('pi.supplier_id')
            ->get([
                'pi.supplier_id',
                DB::raw('COUNT(DISTINCT pi.id) as documents'),
                DB::raw("SUM(CASE WHEN $invoice THEN ii.quantity WHEN $return THEN -ii.quantity ELSE 0 END) as quantity"),
                DB::raw("SUM(CASE WHEN $invoice THEN ii.total ELSE -ii.total END) as amount"),
            ])
            ->map(fn ($r) => [
                'supplier_id' => (int) $r->supplier_id,
                'documents' => (int) $r->documents,
                'quantity' => round((float) $r->quantity, 2),
                'amount' => round((float) $r->amount, 2),
            ])
            ->all();

        return $this->supplierList(
            $period,
            'مشتريات الصنف: '.(DB::table('products')->where('id', $productId)->value('name') ?? '—'),
            'بنودُ فواتير الشراء صافيةً من الضريبة — بندُ المرتجع سالب، فمجموعُ العمود هو الصافي.',
            self::keyBySupplier($rows),
            [
                ['key' => 'documents', 'label' => 'المستندات', 'type' => 'number', 'sum' => true],
                ['key' => 'quantity', 'label' => 'الكمية', 'type' => 'number', 'sum' => true],
                ['key' => 'amount', 'label' => 'صافي القيمة', 'type' => 'money', 'sum' => true],
            ],
            'amount',
        );
    }

    /**
     * استعلامُ مستنداتٍ بنوعٍ ضمن الفترة والنطاق — محورُ الفرع فرعُ الفاتورة.
     *
     * `$excludeClosed` يتبع البطاقةَ التي تفتحها القائمة:
     * `paymentStatusMix()` و`receivingMix()` تُخفيان المقفل، و`openOrders()`
     * لا تفعل (أمرُ الشراء ليس مستنداً ماليّاً). والقائمةُ تساوي رقمَها.
     */
    protected function invoiceQuery(ReportPeriod $period, string $type, bool $excludeClosed = true)
    {
        return DB::table('purchase_invoices')
            ->where('type', $type)
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($this->branchIds !== [] && ! $this->unbranched, fn ($q) => $q->whereIn('branch_id', $this->branchIds))
            ->when($this->unbranched, fn ($q) => $q->whereNull('branch_id'))
            ->tap(fn ($q) => $this->scopeCreator($q))
            ->when($excludeClosed, fn ($q) => ClosedPeriodRanges::exclude($q, 'purchase_invoices.date'))
            ->groupBy('supplier_id');
    }

    /* ══════════════ قائمةُ الموردين — نمطُ لوحة المبيعات ══════════════ */

    /**
     * قائمةُ مورّدين: بطاقتُه، ثم أعمدةُ الشريحة، ثم آخرُ حركةٍ والرصيد.
     *
     * الرصيدُ من القيود حتى نهاية الفترة (`SupplierLedger::balancesAsOf()`)
     * لا من `accounts.balance` — فيبقى كلُّ رقمٍ في اللوحة من مصدرٍ واحد.
     *
     * @param  array<int, array<string, mixed>>  $values  `[supplierId => قيمُ الشريحة]`
     * @param  array<int, array<string, mixed>>  $metricColumns
     * @param  string|null  $sortKey  مفتاحُ ترتيبٍ تنازلي — وإلا فبالاسم
     */
    protected function supplierList(ReportPeriod $period, string $title, ?string $hint, array $values, array $metricColumns, ?string $sortKey = null): array
    {
        $ids = array_map('intval', array_keys($values));
        $profiles = $this->supplierInfo($ids);
        $balances = $this->balanceMap($period);
        $lastMovements = $this->lastMovementMap($period);

        $rows = [];

        foreach ($values as $supplierId => $value) {
            $supplierId = (int) $supplierId;
            $profile = $profiles[$supplierId] ?? [];
            $last = $lastMovements[$supplierId] ?? null;

            $rows[] = array_merge([
                'supplier_id' => $supplierId,
                'code' => $profile['code'] ?? null,
                'name' => $profile['name'] ?? '—',
                'supplier_url' => $profile['url'] ?? null,
                'phone' => $profile['phone'] ?? null,
                'status' => $profile['status'] ?? 'غير معروف',
                'status_color' => $profile['status_color'] ?? '#999999',
                'last_movement' => $last,
                'last_movement_days' => self::daysSince($last),
                'balance' => round($balances[$supplierId] ?? 0, 2),
            ], $value);
        }

        usort($rows, $sortKey === null
            ? fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name'])
            : fn ($a, $b) => ($b[$sortKey] ?? 0) <=> ($a[$sortKey] ?? 0));

        return [
            'title' => $title,
            'hint' => $hint,
            'unit' => 'suppliers',
            'columns' => array_merge([
                ['key' => 'code', 'label' => 'الكود', 'type' => 'text'],
                ['key' => 'name', 'label' => 'المورّد', 'type' => 'supplier'],
                ['key' => 'phone', 'label' => 'الجوال', 'type' => 'text'],
                ['key' => 'status', 'label' => 'الحالة', 'type' => 'status'],
            ], $metricColumns, [
                ['key' => 'last_movement_days', 'label' => 'آخر حركة', 'type' => 'since'],
                ['key' => 'balance', 'label' => 'الرصيد', 'type' => 'money'],
            ]),
            'rows' => $rows,
        ];
    }

    /**
     * بطاقةُ المورّد: الاسمُ المعروض والكودُ والجوال وحالتُه.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    protected function supplierInfo(array $ids): array
    {
        $info = [];
        $canLink = Route::has('SupplierManagement.show');

        foreach (array_chunk(array_values(array_unique(array_filter($ids))), 1000) as $chunk) {
            $rows = DB::table('suppliers')
                ->whereIn('id', $chunk)
                ->get(['id', 'number_suply', 'trade_name', 'first_name', 'last_name', 'phone', 'mobile', 'status']);

            foreach ($rows as $s) {
                $name = $s->trade_name ?: trim(($s->first_name ?? '').' '.($s->last_name ?? '')) ?: '—';
                $active = (int) $s->status === 1;

                $info[(int) $s->id] = [
                    'name' => preg_replace('/\s+/u', ' ', trim($name)) ?: $name,
                    'code' => $s->number_suply,
                    'phone' => trim((string) $s->mobile, " ,\t") ?: trim((string) $s->phone, " ,\t"),
                    'status' => $active ? 'نشط' : 'موقوف',
                    'status_color' => $active ? '#28c76f' : '#ea5455',
                    'url' => $canLink ? route('SupplierManagement.show', $s->id) : null,
                ];
            }
        }

        return $info;
    }

    /* ══════════════ مساعدات ══════════════ */

    /** الدفترُ بنطاق اللوحة — و«غير محدّد» نقيضُ النطاق لا تضييقٌ عليه */
    protected function ledger(?int $supplierId = null): SupplierLedger
    {
        return (new SupplierLedger($supplierId, null, $this->unbranched ? [] : $this->branchIds))
            ->onlyUnbranched($this->unbranched);
    }

    /**
     * يجمع صفوفاً تحمل `supplier_id` مورّداً مورّداً: عدَدُها ومجاميعُ حقولها.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $sumKeys
     * @return array<int, array<string, mixed>>
     */
    protected static function groupBySupplier(array $rows, array $sumKeys, ?string $countKey = null): array
    {
        $values = [];

        foreach ($rows as $row) {
            $id = (int) ($row['supplier_id'] ?? 0);

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

    /**
     * صفوفٌ مجمَّعةٌ في الاستعلام أصلاً — تُفهرَس بالمورّد كما هي.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected static function keyBySupplier(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $id = (int) ($row['supplier_id'] ?? 0);
            unset($row['supplier_id']);
            $values[$id] = $row;
        }

        return $values;
    }

    /** رصيدُ كل مورّد حتى نهاية الفترة — استعلامٌ واحدٌ لكل قائمة */
    protected function balanceMap(ReportPeriod $period): array
    {
        return $this->balanceMap ??= $this->ledger()->balancesAsOf($period->to);
    }

    /** تاريخُ آخر حركةٍ لكل مورّد — كذلك */
    protected function lastMovementMap(ReportPeriod $period): array
    {
        return $this->lastMovementMap ??= $this->ledger()->lastMovementDates($period->to);
    }

    /** أيامٌ كاملةٌ من تاريخٍ حتى اليوم */
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
            : 'غير محدّد';
    }
}
