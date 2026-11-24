{{--
    مدفوعات الموردين — على تصميم تقارير العملاء المعتمد (`ag-*`): ترويسةٌ
    فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات، رسمان
    (المدفوع والمتبقي لأعلى الموردين + حالة السداد)، ثمّ جدولٌ واحدٌ
    بترقيم صفحات.

    فواتير الفترة مجمَّعةً على المورّد، ولكل فاتورة ما سُدِّد منها وما بقي.
    المتحكّم يمرّر `fromDate`/`toDate` ولا يمرّر كائن الفترة، فيُقرأ هنا من
    الطلب نفسه بالقاعدة نفسها (`ReportPeriod::fromRequest`) — فتتطابق
    أزرار الفترة مع الحدود التي بُنيت بها الأرقام.

    الجدولُ واحد: صفُّ عنوانٍ لكلّ مورّد ثمّ فواتيرُه ثمّ إجماليُّه —
    والترقيمُ بالمورّد فلا تنقسم فواتيرُه بين صفحتين. والتصديرُ يُبنى في
    الخادم من كلّ الصفوف كما كان.
--}}
@extends('purchases::master')

@section('title')
    تقرير مدفوعات الموردين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); text-align: start; }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .ag-table td.ag-notes { max-width: 220px; white-space: normal; font-size: .76rem; color: var(--rpt-muted); }
    </style>
@endsection

@section('content')
@php
    use App\Enums\PurchaseDocumentType;
    use App\Support\CurrencySettings;
    use Modules\Purchases\Support\ReportPeriod;

    $period = ReportPeriod::fromRequest(request());

    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number($v);
    $currency = CurrencySettings::name();

    $selectedSupplier = request('supplier_id') ? optional($suppliers->firstWhere('id', (int) request('supplier_id')))->trade_name : null;
    $selectedBranch = $selectedNodeLabel ?? null;
    $periodLabel = $period->labelWithDates();

    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];

    $groupOptions = ['invoice' => 'فاتورة', 'supplier' => 'المورد', 'branch' => 'تابع لـ'];
    $groupBy = isset($groupOptions[(string) request('group_by')]) ? (string) request('group_by') : '';
    $orderBy = request('order_by') === 'asc' ? 'asc' : 'desc';

    // نوع المستند كما يُخزَّن في عمود `type` — القيم نصّية لا أرقام
    $typeMeta = [
        PurchaseDocumentType::ORDER->value => ['label' => 'طلب شراء', 'class' => 'info'],
        PurchaseDocumentType::INVOICE->value => ['label' => 'فاتورة', 'class' => 'primary'],
        PurchaseDocumentType::RETURN->value => ['label' => 'مرتجع', 'class' => 'warning'],
        PurchaseDocumentType::DEBIT_NOTE->value => ['label' => 'إشعار مدين', 'class' => 'danger'],
    ];
    $typeOf = function ($type) use ($typeMeta) {
        $case = PurchaseDocumentType::fromValue((string) $type);

        return $case ? $typeMeta[$case->value] : ['label' => (string) $type ?: '—', 'class' => 'secondary'];
    };

    /*
     * الصفوف تُبنى مرّةً واحدة ويقرؤها الجدول والتصدير معاً. والمدفوع من
     * `paidAmount()` — وهو ما يجمع به المتحكّم إجماليات الشاشة — فلا يختلف
     * صفٌّ عن مجموعه في التذييل.
     */
    $groups = $groupedInvoices->map(function ($invoices) use ($typeOf) {
        $rows = $invoices->map(function ($invoice) use ($typeOf) {
            $paid = $invoice->paidAmount();
            $total = (float) $invoice->grand_total;
            // المرتجع ينقص المتبقّي — فاتورةٌ رُدَّت لا تُسدَّد
            $returned = (float) ($invoice->returned_amount ?? 0);

            return [
                'id' => $invoice->id,
                'date' => \Carbon\Carbon::parse($invoice->date)->format('d/m/Y'),
                'type' => $typeOf($invoice->type),
                'supplier' => $invoice->supplier->trade_name ?? '—',
                'code' => $invoice->code,
                'branch' => $invoice->branch->name ?? '—',
                'total' => $total,
                'paid' => $paid,
                'returned' => $returned,
                'remaining' => max(0.0, $total - $returned - $paid),
                'notes' => $invoice->notes,
            ];
        })->values();

        return [
            'supplier' => $invoices->first()->supplier->trade_name ?? 'غير معروف',
            'rows' => $rows,
            'total' => $rows->sum('total'),
            'paid' => $rows->sum('paid'),
            'remaining' => $rows->sum('remaining'),
        ];
    })->values();

    $invoicesCount = $groups->sum(fn ($g) => $g['rows']->count());
    $suppliersCount = $groups->count();
    $paymentPercentage = $totalGrandTotal > 0 ? round(($totalPaid / $totalGrandTotal) * 100, 2) : 0;

    /* ─────────── الرسمان ─────────── */
    $chartBars = collect($chartData['labels'] ?? [])->map(fn ($name, $i) => [
        'name' => (string) $name,
        'short' => mb_strlen($name) > 16 ? mb_substr($name, 0, 15).'…' : $name,
        'paid' => round((float) ($chartData['paid'][$i] ?? 0), 2),
        'remaining' => round((float) ($chartData['remaining'][$i] ?? 0), 2),
    ])->values()->all();

    $payLegend = [
        ['label' => 'المدفوع', 'value' => round((float) $totalPaid, 2), 'color' => '#10b981', 'pct' => $totalGrandTotal > 0 ? round($totalPaid / $totalGrandTotal * 100) : null],
        ['label' => 'المتبقي', 'value' => round((float) $totalRemaining, 2), 'color' => '#f97316', 'pct' => $totalGrandTotal > 0 ? round($totalRemaining / $totalGrandTotal * 100) : null],
    ];
    $payDonut = array_values(array_filter($payLegend, fn ($s) => $s['value'] > 0.005));

    $guide = [
        'title' => 'ما الذي يعرضه تقرير مدفوعات الموردين؟',
        'lead' => 'فواتير الفترة لكل مورّد — صافيها وما سُدِّد منها وما بقي عليه.',
        'blocks' => [
            'المدفوع' => 'ما سُدِّد من كلّ فاتورةٍ عبر سندات الصرف المربوطة بها — لا مدفوعاتُ الفترة عموماً.',
            'المتبقي' => 'صافي الفاتورة ناقصَ ما سُدِّد منها؛ مجموعُه هو ما زال مستحقاً للموردين عن فواتير الفترة.',
            'نسبة الدفع' => 'المدفوعُ من إجمالي فواتير الفترة — مؤشّرُ التزام السداد.',
        ],
        'facts' => ['ما أُقفل محاسبياً لا يظهر هنا', 'الفواتيرُ مجمَّعةٌ على المورّد، ولكلّ مورّدٍ صفُّ إجمالي'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => 'تقرير مدفوعات الموردين',
        'periodLabel' => $periodLabel,
        'filters' => [
            'المورد' => $selectedSupplier,
            'تابع لـ' => $selectedBranch,
            'تجميع حسب' => $groupBy ? $groupOptions[$groupBy] : null,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'hand-holding-usd',
            'headTitle' => 'مدفوعات الموردين',
            'headSubtitle' => 'فواتير الفترة لكل مورّد — صافيها وما سُدِّد منها وما بقي عليه.',
            'headPeriod' => $periodLabel,
            'headGuideKey' => 'purchases-reports.supplier-payments',
            'headGuide' => $guide,
            'headCrumbLabel' => 'تقارير المشتريات',
            'headCrumbUrl' => route('ReportsPurchases.index'),
        ])
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('ReportsPurchases.paymentPurchases') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period->key }}">

                @include('client::customers.CustomerReport.partials.report-period-tabs', [
                    'tabPeriods' => $tabPeriods,
                    'tabCurrent' => $period->key,
                ])

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="date_from" class="form-control" aria-label="من تاريخ"
                                   value="{{ request('from_date', $fromDate->format('Y-m-d')) }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="to_date" id="date_to" class="form-control" aria-label="إلى تاريخ"
                                   value="{{ request('to_date', $toDate->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="rpt-field">
                        <label for="supplier_id">المورد</label>
                        <select name="supplier_id" id="supplier_id" class="form-control rpt-select2">
                            <option value="">جميع الموردين</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->trade_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="group_by">تجميع حسب</label>
                        <select name="group_by" id="group_by" class="form-control rpt-select2">
                            <option value="">بدون تجميع</option>
                            @foreach ($groupOptions as $key => $label)
                                <option value="{{ $key }}" @selected($groupBy === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="order_by">ترتيب حسب</label>
                        <select name="order_by" id="order_by" class="form-control rpt-select2">
                            <option value="desc" @selected($orderBy === 'desc')>التاريخ تنازلي</option>
                            <option value="asc" @selected($orderBy === 'asc')>التاريخ تصاعدي</option>
                        </select>
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.paymentPurchases') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">الصافي</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totalGrandTotal) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · إجمالي فواتير الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-shopping-cart"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">المدفوع</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totalPaid) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · ما سُدِّد من الفواتير</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المتبقي</div>
                <div class="ag-kpi__value"><bdi class="{{ $totalRemaining > 0.005 ? 'rpt-neg' : '' }}">{{ $signed($totalRemaining) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · ما زال مستحقاً للموردين</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">نسبة الدفع</div>
                <div class="ag-kpi__value"><bdi>{{ $paymentPercentage }}%</bdi></div>
                <div class="ag-kpi__sub">من الإجمالي</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-percent"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">عدد الفواتير</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($invoicesCount) }}</bdi></div>
                <div class="ag-kpi__sub">على {{ number_format($suppliersCount) }} مورد</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> أعلى الموردين مبلغاً متبقياً — المدفوع والمتبقي</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($chartBars))
                    <div class="ag-canvas"><canvas id="paymentsBar" aria-label="المدفوع والمتبقي لأعلى الموردين" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا فواتير في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> حالة السداد من إجمالي الفواتير</h4>
            </div>
            <div class="rpt-card__body">
                @if ($totalGrandTotal > 0.005)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($payLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($payDonut))
                            <div class="ag-donut">
                                <canvas id="payDonut" aria-label="حالة السداد من إجمالي الفواتير" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $paymentPercentage }}%</strong>
                                    <small>نسبة الدفع</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا فواتير في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> ملخص مدفوعات الموردين
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($invoicesCount) }} فاتورة · {{ number_format($suppliersCount) }} مورد</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:100px">التاريخ</th>
                        <th>النوع</th>
                        <th style="width:20%">المورد</th>
                        <th>رقم المستند</th>
                        <th>تابع لـ</th>
                        <th>الصافي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @php $counter = 0; @endphp
                    @forelse ($groups as $group)
                        @php $rowKey = 'g'.$loop->index; @endphp
                        <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                            <td colspan="10" class="rpt-name">
                                <i class="fas fa-building"></i>
                                <strong>{{ $group['supplier'] }}</strong>
                                <span class="rpt-pill rpt-pill-secondary">{{ number_format($group['rows']->count()) }} فاتورة</span>
                                <span class="rpt-pill {{ $group['remaining'] > 0.005 ? 'rpt-pill-warning' : 'rpt-pill-success' }}">المتبقي: {{ $money($group['remaining']) }}</span>
                            </td>
                        </tr>
                        @foreach ($group['rows'] as $row)
                            <tr data-parent-row="{{ $rowKey }}">
                                <td>{{ ++$counter }}</td>
                                <td class="rpt-num">{{ $row['date'] }}</td>
                                <td><span class="rpt-pill rpt-pill-{{ $row['type']['class'] }}">{{ $row['type']['label'] }}</span></td>
                                <td class="rpt-name">{{ $row['supplier'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['code'] }}</span></td>
                                <td>{{ $row['branch'] }}</td>
                                <td class="rpt-num">{{ $money($row['total']) }}</td>
                                <td class="rpt-num {{ $row['paid'] > 0.005 ? 'rpt-pos' : 'rpt-zero' }}">{{ $row['paid'] > 0.005 ? $money($row['paid']) : '—' }}</td>
                                <td class="rpt-num {{ $row['remaining'] > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}"><strong>{{ $signed($row['remaining']) }}</strong></td>
                                <td class="ag-notes">{{ $row['notes'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                        <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                            <td colspan="6" style="text-align:start">إجمالي: {{ $group['supplier'] }}</td>
                            <td class="rpt-num">{{ $money($group['total']) }}</td>
                            <td class="rpt-num rpt-pos">{{ $money($group['paid']) }}</td>
                            <td class="rpt-num {{ $group['remaining'] > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $signed($group['remaining']) }}</td>
                            <td>—</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="rpt-empty">
                            <i class="fas fa-inbox"></i>
                            لا توجد فواتير في {{ $periodLabel }}
                        </td></tr>
                    @endforelse
                </tbody>
                @if ($invoicesCount)
                    <tfoot>
                        <tr>
                            <td colspan="6">الإجمالي العام — {{ number_format($invoicesCount) }} فاتورة · نسبة الدفع {{ $paymentPercentage }}%</td>
                            <td class="rpt-num">{{ $money($totalGrandTotal) }}</td>
                            <td class="rpt-num rpt-pos">{{ $money($totalPaid) }}</td>
                            <td class="rpt-num {{ $totalRemaining > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $signed($totalRemaining) }}</td>
                            <td>—</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'مورد',
            'pagerStorage' => 'agSupplierPaymentsPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')

    @php
        $excelRows = [];
        $n = 0;
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $excelRows[] = [
                    'values' => [
                        ++$n, $row['date'], $row['type']['label'], $row['supplier'], (string) $row['code'],
                        $row['branch'], $row['total'], $row['paid'], $row['remaining'], (string) ($row['notes'] ?: ''),
                    ],
                    'alignments' => [null, null, null, 'right'],
                ];
            }
            // صفّ إجمالي المورّد — يُميَّز خليةً خلية، فالمساعد لا يعرف تلويناً على مستوى الصف
            $excelRows[] = [
                'values' => ['', '', '', 'إجمالي: '.$group['supplier'], '', '', $group['total'], $group['paid'], $group['remaining'], ''],
                'alignments' => [null, null, null, 'right'],
                'cellStyles' => array_fill(0, 10, ['background' => 'warningLight', 'color' => 'gray', 'bold' => true]),
            ];
        }
    @endphp

    @include('purchases::purchases.report.partials.excel', [
        'fileName' => 'مدفوعات_الموردين',
        'mainTitle' => 'تقرير مدفوعات الموردين',
        'info' => [
            'الفترة' => $periodLabel,
            'المورد' => $selectedSupplier ?: 'الكل',
            'تابع لـ' => $selectedBranch ?: 'الكل',
            'تجميع حسب' => $groupBy ? $groupOptions[$groupBy] : 'بدون تجميع',
        ],
        'cards' => [
            ['title' => 'الصافي', 'value' => $money($totalGrandTotal), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'المدفوع', 'value' => $money($totalPaid), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المتبقي', 'value' => $money($totalRemaining), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'نسبة الدفع', 'value' => $paymentPercentage.'%', 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningLight', 'borderColor' => 'warning'],
            ['title' => 'عدد الفواتير', 'value' => $invoicesCount, 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
        ],
        'headers' => ['#', 'التاريخ', 'النوع', 'المورد', 'رقم المستند', 'تابع لـ', 'الصافي', 'المدفوع', 'المتبقي', 'ملاحظات'],
        'widths' => [6, 12, 12, 30, 16, 18, 16, 16, 16, 30],
        'rows' => $excelRows,
        'summaryRows' => $invoicesCount ? [[
            'values' => ['', '', '', 'الإجمالي العام', '', '', $totalGrandTotal, $totalPaid, $totalRemaining, ''],
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [],
    ])

    <script>
        (function () {
            var CHART_BARS = @json($chartBars);
            var PAY_DONUT = @json($payDonut);

            function markCustom() {
                $('#periodInput').val('custom');
                $('.period-btn').removeClass('is-active');
                $('.period-btn[data-period="custom"]').addClass('is-active');
            }

            $(function () {
                if ($.fn.select2) {
                    $('.rpt-select2').each(function () { if ($(this).data('select2')) { $(this).select2('destroy'); } });
                    $('.rpt-select2').select2({ dir: 'rtl', width: '100%', language: { noResults: function () { return 'لا توجد نتائج'; } } });
                }

                $('.period-btn[data-period]').on('click', function () {
                    var key = String($(this).data('period'));
                    $('.period-btn').removeClass('is-active');
                    $(this).addClass('is-active');
                    $('#periodInput').val(key);
                    if (key === 'custom') { $('#date_from').trigger('focus'); return; }
                    $('#date_from, #date_to').val('');
                    $('#filterForm').trigger('submit');
                });
                $('#date_from, #date_to').on('change', markCustom);
                $('#filterForm').on('submit', function () {
                    if ($('#periodInput').val() !== 'custom') { $('#date_from, #date_to').val(''); }
                });

                $('#printBtn').on('click', function () { window.print(); });

                renderCharts();
            });

            function renderCharts() {
                if (typeof Chart === 'undefined') return;
                var theme = agChartTheme();

                var barEl = document.getElementById('paymentsBar');
                if (barEl && CHART_BARS.length) {
                    new Chart(barEl, {
                        type: 'bar',
                        data: {
                            labels: CHART_BARS.map(function (r) { return r.short; }),
                            datasets: [
                                { label: 'المدفوع', data: CHART_BARS.map(function (r) { return r.paid; }), backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 36 },
                                { label: 'المتبقي', data: CHART_BARS.map(function (r) { return r.remaining; }), backgroundColor: '#f97316', borderRadius: 6, maxBarThickness: 36 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top', rtl: true, labels: { color: theme.muted, boxWidth: 12 } },
                                tooltip: { rtl: true, callbacks: {
                                    title: function (items) { return CHART_BARS[items[0].dataIndex].name; },
                                    label: function (c) { return ' ' + c.dataset.label + ': ' + agMoney(c.parsed.y); }
                                } }
                            },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 10 }, maxRotation: 45, autoSkip: false } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        }
                    });
                }

                var donutEl = document.getElementById('payDonut');
                if (donutEl && PAY_DONUT.length) {
                    new Chart(donutEl, {
                        type: 'doughnut',
                        data: {
                            labels: PAY_DONUT.map(function (s) { return s.label; }),
                            datasets: [{ data: PAY_DONUT.map(function (s) { return s.value; }), backgroundColor: PAY_DONUT.map(function (s) { return s.color; }), borderColor: theme.surface, borderWidth: 3, hoverOffset: 6 }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) {
                                var s = PAY_DONUT[c.dataIndex];
                                return ' ' + agMoney(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)');
                            } } } }
                        }
                    });
                }
            }
        })();
    </script>
@endsection
