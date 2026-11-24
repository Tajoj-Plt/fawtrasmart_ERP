{{--
    تقرير المشتريات حسب الصنف — على تصميم تقارير العملاء المعتمد (`ag-*`):
    ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات،
    رسمان (الأعلى تكلفةً + توزيع الصافي)، ثمّ جدولٌ واحدٌ بترقيم صفحات.

    ما لم يتغيّر: الفلاترُ الاثنا عشر وأسماؤها، والفتراتُ من `ReportPeriod`
    بمفاتيحها، وزرّا «ملخص/تفاصيل البنود» يُرسلان `view_type`، وبطاقةُ
    «الربط بقيود اليومية»، وأعمدةُ الجدولين وذيلاهما، والتصديرُ يُبنى في
    الخادم من صفوف التقرير (`partials.excel`) والطباعةُ تطبع الصفحة.

    تفاصيلُ البنود صارت جدولاً واحداً: صفُّ عنوانٍ لكلّ صنفٍ ثمّ بنودُه ثمّ
    إجماليُّها — والترقيمُ بالصنف.
--}}
@extends('purchases::master')

@section('title')
    تقرير المشتريات حسب الصنف
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .rpt-card > .rpt-equation { margin: 0; border: 0; border-radius: 0; }
        .rpt-row-purchase   td { background: rgba(115, 103, 240, .04); }
        .rpt-row-debit_note td { background: rgba(255, 159, 67, .06); }
    </style>
@endsection

@section('content')
@php
    use App\Enums\PurchaseDocumentType;
    use App\Support\CurrencySettings;
    use Modules\Purchases\Support\ReportPeriod;

    $q = request()->except(['page']);
    $sortBy = request('sort_by', 'net_amount');
    $sortDir = request('sort_dir', 'desc');
    $viewType = request('view_type', 'summary') === 'details' ? 'details' : 'summary';
    $isSummary = $viewType === 'summary';

    // المبالغ بإعدادات شاشة «العملات»؛ الكمّيات أعدادٌ مجرَّدة لا عملة لها
    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number($v);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.') ?: '0';
    $signClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-pos' : 'rpt-neg');
    $currency = CurrencySettings::name();
    $periodLabel = $period->labelWithDates();

    $selectedProduct = request('product') ? optional($products->firstWhere('id', (int) request('product')))->name : null;
    $selectedCategory = request('category') ? optional($categories->firstWhere('id', (int) request('category')))->name : null;
    $selectedSupplier = request('supplier') ? optional($suppliers->firstWhere('id', (int) request('supplier')))->trade_name : null;
    $selectedBranch = $selectedNodeLabel ?? null;
    $selectedStore = request('storehouse') ? optional($storehouses->firstWhere('id', (int) request('storehouse')))->name : null;
    $selectedUser = request('added_by') ? optional($users->firstWhere('id', (int) request('added_by')))->name : null;

    // الفارق بين مجموع البنود وما رُحِّل إلى حساب المشتريات — شحنٌ أو تقريب
    $ledgerGap = round($control['purchases_account'] - $totals['net_amount'], 2);

    $link = function ($col) use ($q, $sortBy, $sortDir) {
        $dir = ($sortBy === $col && $sortDir === 'desc') ? 'asc' : 'desc';
        return route('ReportsPurchases.byProduct', array_merge($q, ['sort_by' => $col, 'sort_dir' => $dir]));
    };
    $caret = fn ($col) => $sortBy === $col ? ' <i class="fas fa-sort-'.($sortDir === 'asc' ? 'up' : 'down').'"></i>' : '';

    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];
    $kindPill = ['purchase' => 'success', 'return' => 'danger', 'debit_note' => 'warning'];
    $receivingLabels = [
        'received' => ['مستلمة', 'success'],
        'partially_received' => ['جزئياً', 'warning'],
        'not_received' => ['لم تُستلم', 'secondary'],
    ];

    /* ─────────── الرسمان ─────────── */
    $topSource = $reportData->sortByDesc('net_amount')->filter(fn ($r) => $r['net_amount'] > 0.005)->take(10);
    $topData = [
        'labels' => $topSource->map(fn ($r) => (string) $r['name'])->values()->all(),
        'values' => $topSource->map(fn ($r) => round((float) $r['net_amount'], 2))->values()->all(),
    ];
    $tPurchases = (float) $totals['purchases'];
    $tReturns = (float) $totals['returns'];
    $tDiscount = (float) $totals['discount'];
    $distLegend = [
        ['label' => 'صافي التكلفة', 'value' => (float) $totals['net_amount'], 'color' => '#3b82f6', 'pct' => $tPurchases > 0 ? round($totals['net_amount'] / $tPurchases * 100) : null],
        ['label' => 'المرتجعات', 'value' => $tReturns, 'color' => '#ef4444', 'pct' => $tPurchases > 0 ? round($tReturns / $tPurchases * 100) : null],
        ['label' => 'الخصومات', 'value' => $tDiscount, 'color' => '#eab308', 'pct' => $tPurchases > 0 ? round($tDiscount / $tPurchases * 100) : null],
    ];
    $distDonut = array_values(array_filter($distLegend, fn ($s) => $s['value'] > 0.005));

    $guide = [
        'title' => 'ما الذي يقيسه هذا التقرير؟',
        'lead' => 'كمّياتُ كلّ صنفٍ وتكلفتُه خلال الفترة — من بنود المستندات المرحَّلة (فواتير ومرتجعات وإشعارات مدينة)، صافيةً من ضريبة المدخلات.',
        'blocks' => [
            'صافي التكلفة' => 'قيمة بنود الشراء − المرتجعات − الإشعارات المدينة، بعد الخصم المحسوب بنوعه لكلّ بند.',
            'الربط بالقيود' => 'مجموعُ البنود لا يساوي حساب المشتريات دائماً: الشحنُ يُرحَّل بلا بند، والضريبة تذهب إلى حسابها — فيُعرض الفارق مفهوماً لا خطأً.',
            'تفاصيل البنود' => 'كلُّ بندٍ بمستنده ومورّده ومخزنه وكمّيته وسعره وحالة استلامه.',
        ],
        'facts' => ['الكمّياتُ أعدادٌ مجرّدة بوحدة الصنف، والمبالغُ بعملة النظام.', 'تاريخان صريحان يتقدّمان على زرّ الفترة.'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => 'تقرير المشتريات حسب الصنف — '.($isSummary ? 'ملخص' : 'تفصيلي'),
        'periodLabel' => $periodLabel,
        'filters' => [
            'الصنف' => $selectedProduct,
            'التصنيف' => $selectedCategory,
            'المورد' => $selectedSupplier,
            'المخزن' => $selectedStore,
            'تابع لـ' => $selectedBranch,
            'أُضيف بواسطة' => $selectedUser,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'boxes',
            'headTitle' => 'المشتريات حسب الصنف',
            'headSubtitle' => 'كمّيات كل منتج وتكلفته خلال الفترة — من بنود المستندات المرحَّلة، صافيةً من ضريبة المدخلات.',
            'headPeriod' => $periodLabel,
            'headExtra' => $isSummary ? 'ملخص' : 'تفاصيل البنود',
            'headGuideKey' => 'purchases-reports.by-product',
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
            <form method="GET" action="{{ route('ReportsPurchases.byProduct') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period->key }}">
                <input type="hidden" name="view_type" value="{{ $viewType }}">
                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                <input type="hidden" name="sort_dir" value="{{ $sortDir }}">

                @include('client::customers.CustomerReport.partials.report-period-tabs', [
                    'tabPeriods' => $tabPeriods,
                    'tabCurrent' => $period->key,
                ])

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="من تاريخ"
                                   value="{{ request('date_from', $fromDate->format('Y-m-d')) }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="إلى تاريخ"
                                   value="{{ request('date_to', $toDate->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="rpt-field">
                        <label for="product">الصنف</label>
                        <select name="product" id="product" class="form-control rpt-select2">
                            <option value="">جميع الأصناف</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) request('product') === (string) $product->id)>
                                    {{ $product->name }}@if ($product->serial_number) — {{ $product->serial_number }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="category">التصنيف</label>
                        <select name="category" id="category" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="supplier">المورد</label>
                        <select name="supplier" id="supplier" class="form-control rpt-select2">
                            <option value="">جميع الموردين</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) request('supplier') === (string) $supplier->id)>{{ $supplier->trade_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="storehouse">المخزن</label>
                        <select name="storehouse" id="storehouse" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($storehouses as $storehouse)
                                <option value="{{ $storehouse->id }}" @selected((string) request('storehouse') === (string) $storehouse->id)>{{ $storehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="added_by">أُضيف بواسطة</label>
                        <select name="added_by" id="added_by" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('added_by') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="invoice_type">نوع المستند</label>
                        <select name="invoice_type" id="invoice_type" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ([PurchaseDocumentType::INVOICE, PurchaseDocumentType::RETURN, PurchaseDocumentType::DEBIT_NOTE] as $type)
                                <option value="{{ $type->value }}" @selected(request('invoice_type') === $type->value)>{{ $type->getName() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="status">حالة السداد</label>
                        <select name="status" id="status" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            <option value="paid" @selected(request('status') === 'paid')>مسددة</option>
                            <option value="partially_paid" @selected(request('status') === 'partially_paid')>مسددة جزئياً</option>
                            <option value="unpaid" @selected(request('status') === 'unpaid')>غير مسددة</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="receiving_status">حالة الاستلام</label>
                        <select name="receiving_status" id="receiving_status" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            <option value="received" @selected(request('receiving_status') === 'received')>مستلمة</option>
                            <option value="partially_received" @selected(request('receiving_status') === 'partially_received')>مستلمة جزئياً</option>
                            <option value="not_received" @selected(request('receiving_status') === 'not_received')>غير مستلمة</option>
                        </select>
                    </div>

                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="btn-group ag-view-toggle" role="group" aria-label="طريقة العرض">
                        <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-list"></i> ملخص
                        </button>
                        <button type="submit" name="view_type" value="details" class="btn {{ ! $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-th-list"></i> تفاصيل البنود
                        </button>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.byProduct') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">الأصناف</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['products_count'] }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['documents'] }} مستند · {{ $totals['lines'] }} بند</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-boxes"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">صافي الكمية</div>
                <div class="ag-kpi__value"><bdi>{{ $qty($totals['quantity']) }}</bdi></div>
                <div class="ag-kpi__sub">مشترى {{ $qty($totals['purchases_qty']) }} · مرتجع {{ $qty($totals['returns_qty']) }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-cubes"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي المشتريات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['purchases']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · قيمة البنود قبل الضريبة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المرتجعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['returns']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $qty($totals['returns_qty']) }} وحدة مرتجعة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#eab308">
            <div>
                <div class="ag-kpi__label">الخصومات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['discount']) }}</bdi></div>
                <div class="ag-kpi__sub">محسوبةً بنوع الخصم لكل بند</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-tags"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">صافي التكلفة</div>
                <div class="ag-kpi__value"><bdi class="{{ $totals['net_amount'] < -0.005 ? 'rpt-neg' : '' }}">{{ $signed($totals['net_amount']) }}</bdi></div>
                <div class="ag-kpi__sub">المشتريات − المرتجعات − الإشعارات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-chart-line"></i></span>
        </div>
    </div>

    {{-- ===================== الربط بالقيود =====================
         مجموع البنود لا يساوي ما رُحِّل إلى حساب المشتريات دائماً: الشحن
         يُرحَّل ولا بند له، والضريبة تذهب إلى حسابها. عرضُ الثلاثة معاً
         يجعل الفارق مفهوماً بدل أن يبدو خطأً. --}}
    <div class="rpt-card">
        <div class="rpt-card__head">
            <h4><i class="fas fa-link"></i> الربط بقيود اليومية</h4>
            <span class="rpt-pill rpt-pill-secondary">{{ $control['entries'] }} قيد مرحَّل</span>
        </div>
        <div class="rpt-equation">
            <div class="rpt-equation__item">
                <small>صافي بنود التقرير</small>
                <strong class="{{ $signClass($totals['net_amount']) }}">{{ $signed($totals['net_amount']) }}</strong>
            </div>
            <div class="rpt-equation__op">≈</div>
            <div class="rpt-equation__item">
                <small>حساب المشتريات (مدين − دائن)</small>
                <strong>{{ $signed($control['purchases_account']) }}</strong>
            </div>
            <div class="rpt-equation__op">+</div>
            <div class="rpt-equation__item">
                <small>ضريبة المدخلات</small>
                <strong class="rpt-zero">{{ $signed($control['vat']) }}</strong>
            </div>
            <div class="rpt-equation__op">|</div>
            <div class="rpt-equation__item">
                <small>الفارق (شحن وتقريب)</small>
                <strong class="{{ abs($ledgerGap) < 0.05 ? 'rpt-pos' : 'rpt-neg' }}">{{ $signed($ledgerGap) }}</strong>
            </div>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-award"></i> أعلى الأصناف تكلفةً</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($topData['labels']))
                    <div class="ag-canvas"><canvas id="topChart" aria-label="أعلى الأصناف تكلفةً" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا توجد بنود مشتريات مرحَّلة في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> ما آل إليه إجمالي المشتريات</h4>
            </div>
            <div class="rpt-card__body">
                @if ($tPurchases > 0.005)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($distLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($distDonut))
                            <div class="ag-donut">
                                <canvas id="distDonut" aria-label="توزيع إجمالي المشتريات" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $money($tPurchases) }}</strong>
                                    <small>إجمالي المشتريات · {{ $currency }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا توجد بنود مشتريات مرحَّلة في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i>
                {{ $isSummary ? 'ملخص الأصناف' : 'تفاصيل البنود' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $reportData->count() }} صنف</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <a href="{{ route('ReportsPurchases.byProductExport', $q) }}" class="btn btn-sm ag-btn-soft" title="تصدير CSV خام"><i class="fas fa-file-csv"></i> CSV</a>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll">
            @if ($isSummary)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th><a href="{{ $link('code') }}">الكود{!! $caret('code') !!}</a></th>
                            <th><a href="{{ $link('name') }}">الصنف{!! $caret('name') !!}</a></th>
                            <th><a href="{{ $link('category') }}">التصنيف{!! $caret('category') !!}</a></th>
                            <th><a href="{{ $link('documents') }}">المستندات{!! $caret('documents') !!}</a></th>
                            <th>الكمية المشتراة</th>
                            <th>كمية المرتجع</th>
                            <th><a href="{{ $link('quantity') }}">صافي الكمية{!! $caret('quantity') !!}</a></th>
                            <th><a href="{{ $link('purchases') }}">إجمالي المشتريات{!! $caret('purchases') !!}</a></th>
                            <th><a href="{{ $link('returns') }}">المرتجعات{!! $caret('returns') !!}</a></th>
                            <th><a href="{{ $link('discount') }}">الخصم{!! $caret('discount') !!}</a></th>
                            <th><a href="{{ $link('net_amount') }}">صافي التكلفة{!! $caret('net_amount') !!}</a></th>
                            <th><a href="{{ $link('avg_price') }}">متوسط السعر{!! $caret('avg_price') !!}</a></th>
                            <th>الموردون</th>
                            <th class="ag-extra" style="width:60px">البنود</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $row)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-num">{{ $row['code'] }}</td>
                                <td class="rpt-name">
                                    {{ $row['name'] }}
                                    @if ($row['last_date'])
                                        <small>آخر شراء: {{ \Carbon\Carbon::parse($row['last_date'])->format('d/m/Y') }}</small>
                                    @endif
                                </td>
                                <td>{{ $row['category'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['documents'] }}</span></td>
                                <td class="rpt-num rpt-pos">{{ $qty($row['purchases_qty']) }}</td>
                                <td class="rpt-num {{ $row['returns_qty'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['returns_qty'] > 0 ? $qty($row['returns_qty']) : '—' }}
                                </td>
                                <td class="rpt-num"><strong>{{ $qty($row['quantity']) }}</strong></td>
                                <td class="rpt-num rpt-pos">{{ $money($row['purchases']) }}</td>
                                <td class="rpt-num {{ $row['returns'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['returns'] > 0 ? '('.$money($row['returns']).')' : '—' }}
                                </td>
                                <td class="rpt-num {{ $row['discount'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['discount'] > 0 ? $money($row['discount']) : '—' }}
                                </td>
                                <td class="rpt-num {{ $signClass($row['net_amount']) }}"><strong>{{ $signed($row['net_amount']) }}</strong></td>
                                <td class="rpt-num">{{ $money($row['avg_price']) }}</td>
                                <td><span class="rpt-pill rpt-pill-primary">{{ $row['suppliers_count'] }}</span></td>
                                <td class="ag-extra">
                                    <a class="ag-actions-btn" title="بنود الصنف" aria-label="بنود الصنف"
                                       href="{{ route('ReportsPurchases.byProduct', array_merge($q, ['view_type' => 'details', 'product' => $row['product_id']])) }}">
                                        <i class="fas fa-stream"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="15" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد بنود مشتريات مرحَّلة في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($reportData->count())
                        <tfoot>
                            <tr>
                                <td colspan="4">الإجمالي العام</td>
                                <td>{{ $totals['documents'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $qty($totals['purchases_qty']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $qty($totals['returns_qty']) }}</td>
                                <td class="rpt-num">{{ $qty($totals['quantity']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['purchases']) }}</td>
                                <td class="rpt-num rpt-neg">({{ $money($totals['returns']) }})</td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['discount']) }}</td>
                                <td class="rpt-num {{ $signClass($totals['net_amount']) }}">{{ $signed($totals['net_amount']) }}</td>
                                <td colspan="2"></td>
                                <td class="ag-extra"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                {{-- ===================== تفاصيل البنود: جدولٌ واحد بمجموعات ===================== --}}
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:100px">التاريخ</th>
                            <th>المستند</th>
                            <th>النوع</th>
                            <th>المورد</th>
                            <th>المخزن</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>الخصم</th>
                            <th>الإجمالي</th>
                            <th>الاستلام</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $row)
                            @php $rowKey = 'p'.$loop->index; @endphp
                            <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                                <td colspan="11" class="rpt-name">
                                    <i class="fas fa-box"></i>
                                    <strong>{{ $row['name'] }}</strong>
                                    <small class="text-muted">— {{ $row['code'] }} · {{ $row['category'] }}</small>
                                    <span class="rpt-pill rpt-pill-primary">{{ $row['documents'] }} مستند</span>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $row['suppliers_count'] }} مورد</span>
                                    <span class="rpt-pill rpt-pill-success">مشترى {{ $qty($row['purchases_qty']) }} · {{ $money($row['purchases']) }}</span>
                                    <span class="rpt-pill rpt-pill-danger">مرتجع {{ $qty($row['returns_qty']) }} · {{ $money($row['returns']) }}</span>
                                    <span class="rpt-pill rpt-pill-warning">خصم {{ $money($row['discount']) }}</span>
                                    <span class="rpt-pill rpt-pill-info">متوسط السعر {{ $money($row['avg_price']) }}</span>
                                </td>
                            </tr>
                            @foreach ($row['items'] as $i => $item)
                                @php $receiving = $receivingLabels[$item['receiving_status']] ?? ['—', 'secondary']; @endphp
                                <tr class="rpt-row-{{ $item['kind'] }}" data-parent-row="{{ $rowKey }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td class="rpt-num">{{ $item['date'] }}</td>
                                    <td><strong>{{ $item['document_code'] }}</strong></td>
                                    <td><span class="rpt-pill rpt-pill-{{ $kindPill[$item['kind']] ?? 'secondary' }}">{{ $item['type_name'] }}</span></td>
                                    <td class="rpt-name">{{ $item['supplier_name'] }}</td>
                                    <td>{{ $item['storehouse_name'] }}</td>
                                    <td class="rpt-num {{ $signClass($item['quantity']) }}">
                                        {{ $qty($item['quantity']) }}@if ($item['unit_name']) <small>{{ $item['unit_name'] }}</small>@endif
                                    </td>
                                    <td class="rpt-num">{{ $money($item['unit_price']) }}</td>
                                    <td class="rpt-num {{ $item['discount'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $item['discount'] > 0 ? $money($item['discount']) : '—' }}</td>
                                    <td class="rpt-num {{ $signClass($item['amount']) }}"><strong>{{ $signed($item['amount']) }}</strong></td>
                                    <td><span class="rpt-pill rpt-pill-{{ $receiving[1] }}">{{ $receiving[0] }}</span></td>
                                </tr>
                            @endforeach
                            <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                                <td colspan="6">إجمالي بنود الصنف — {{ $row['name'] }}</td>
                                <td class="rpt-num">{{ $qty($row['quantity']) }}</td>
                                <td class="rpt-num">{{ $money($row['avg_price']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $money($row['discount']) }}</td>
                                <td class="rpt-num {{ $signClass($row['net_amount']) }}">{{ $signed($row['net_amount']) }}</td>
                                <td></td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد بنود مشتريات مرحَّلة في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($reportData->count())
                        <tfoot>
                            <tr>
                                <td colspan="6">الإجمالي العام — {{ $totals['lines'] }} بند</td>
                                <td class="rpt-num">{{ $qty($totals['quantity']) }}</td>
                                <td></td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['discount']) }}</td>
                                <td class="rpt-num {{ $signClass($totals['net_amount']) }}">{{ $signed($totals['net_amount']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'صنف',
            'pagerStorage' => 'agPurchasesByProductPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')

    <script>
        (function () {
            const TOP = @json($topData);
            const DONUT = @json($distDonut);

            $(function () {
                if ($.fn.select2) {
                    $('.rpt-select2').each(function () {
                        if ($(this).data('select2')) { $(this).select2('destroy'); }
                    });
                    $('.rpt-select2').select2({ dir: 'rtl', width: '100%', allowClear: true, language: { noResults: function () { return 'لا توجد نتائج'; } } });
                }

                $('.period-btn').on('click', function () {
                    var key = String($(this).data('period'));
                    if (key === 'custom') { $('#date_from').trigger('focus'); return; }
                    $('#periodInput').val(key);
                    $('#date_from, #date_to').val('');
                    $('#filterForm').trigger('submit');
                });

                $('#printBtn').on('click', function () { window.print(); });

                if (typeof Chart === 'undefined') return;
                const theme = agChartTheme();

                const topEl = document.getElementById('topChart');
                if (topEl && TOP.labels.length) {
                    new Chart(topEl.getContext('2d'), {
                        type: 'bar',
                        data: { labels: TOP.labels, datasets: [{ data: TOP.values, backgroundColor: agPalette.slice(0, TOP.labels.length), borderRadius: 6, maxBarThickness: 48 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agMoney(c.parsed.y); } } } },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 0, autoSkip: false,
                                     callback: function (v) { const l = String(this.getLabelForValue(v)); return l.length > 14 ? l.slice(0, 13) + '…' : l; } } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        },
                        plugins: [agValueLabels(TOP.values)]
                    });
                }

                const donutEl = document.getElementById('distDonut');
                if (donutEl && DONUT.length) {
                    new Chart(donutEl, {
                        type: 'doughnut',
                        data: {
                            labels: DONUT.map(function (s) { return s.label; }),
                            datasets: [{ data: DONUT.map(function (s) { return s.value; }), backgroundColor: DONUT.map(function (s) { return s.color; }), borderColor: theme.surface, borderWidth: 3, hoverOffset: 6 }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) { const s = DONUT[c.dataIndex]; return ' ' + agMoney(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)'); } } }
                            }
                        }
                    });
                }
            });
        })();
    </script>

    {{-- التصديرُ يُبنى في الخادم من صفوف التقرير — كلُّ الصفوف لا الصفحةُ المعروضة --}}
    @include('purchases::purchases.report.partials.excel', [
        'fileName' => 'المشتريات_حسب_الصنف',
        'mainTitle' => 'تقرير المشتريات حسب الصنف',
        'info' => [
            'الفترة' => $periodLabel,
            'الصنف' => $selectedProduct ?: 'الكل',
            'التصنيف' => $selectedCategory ?: 'الكل',
            'المورد' => $selectedSupplier ?: 'الكل',
            'المخزن' => $selectedStore ?: 'الكل',
            'تابع لـ' => $selectedBranch ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'عدد الأصناف', 'value' => $totals['products_count'], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'صافي الكمية', 'value' => $qty($totals['quantity']), 'valueColor' => 'primaryLight', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المشتريات', 'value' => $money($totals['purchases']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المرتجعات', 'value' => $money($totals['returns']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'صافي التكلفة', 'value' => $money($totals['net_amount']), 'valueColor' => 'purple', 'titleColor' => 'gray', 'backgroundColor' => 'purpleLight', 'borderColor' => 'purple'],
        ],
        'headers' => ['#', 'الكود', 'الصنف', 'التصنيف', 'المستندات', 'الكمية المشتراة', 'كمية المرتجع', 'صافي الكمية', 'المشتريات', 'المرتجعات', 'الخصم', 'صافي التكلفة', 'متوسط السعر', 'الموردون'],
        'widths' => [6, 14, 28, 18, 12, 16, 14, 14, 16, 14, 14, 16, 14, 12],
        'rows' => $reportData->values()->map(fn ($r, $i) => [
            'values' => [
                $i + 1, (string) $r['code'], $r['name'], $r['category'], $r['documents'],
                $r['purchases_qty'], $r['returns_qty'], $r['quantity'],
                $r['purchases'], $r['returns'], $r['discount'],
                $r['net_amount'], $r['avg_price'], $r['suppliers_count'],
            ],
            'alignments' => [null, null, 'right'],
        ])->all(),
        'summaryRows' => $reportData->count() ? [[
            'values' => [
                '', '', 'الإجمالي العام', '', $totals['documents'],
                $totals['purchases_qty'], $totals['returns_qty'], $totals['quantity'],
                $totals['purchases'], $totals['returns'], $totals['discount'],
                $totals['net_amount'], '', '',
            ],
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [],
    ])
@endsection
