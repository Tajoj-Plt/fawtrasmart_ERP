{{--
    تقرير المشتريات حسب البند — المستلم وغير المستلم — على تصميم تقارير
    العملاء المعتمد (`ag-*`): ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ
    واحدة، مؤشّراتٌ بأيقونات، رسمان (الأعلى غيرَ مستلَم + حلقة المستلم/المعلّق)،
    ثمّ جدولٌ واحدٌ بترقيم صفحات.

    التقرير يقيس فجوةً لا مبلغاً: بين ما اشتُري وما دخل المستودع. فالعمود
    المحوري «غير المستلم» وقيمتُه، لا إجمالي الشراء.

    ما لم يتغيّر: الفلاترُ وأسماؤها، والفتراتُ من `ReportPeriod` بمفاتيحها،
    وزرّا «حسب الصنف/حسب البند» يُرسلان `view_type`، وأعمدةُ الجدولين
    وذيلاهما، وتنبيهُ الاستلام الزائد، والتصديرُ يُبنى في الخادم من بنود
    الفواتير (`partials.excel`) والطباعةُ تطبع الصفحة.
--}}
@extends('purchases::master')

@section('title')
    المشتريات حسب البند — المستلم وغير المستلم
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt-row-purchase   td { background: rgba(115, 103, 240, .04); }
        .rpt-row-debit_note td { background: rgba(255, 159, 67, .06); }
        /* شريط نسبة الاستلام — يتبع ألوان «مظهر النظام» */
        .rcv-bar { height: 8px; border-radius: 999px; overflow: hidden; background: var(--rpt-line); margin-bottom: 3px; min-width: 70px; }
        .rcv-bar > span { display: block; height: 100%; border-radius: 999px; background: var(--rpt-ok); min-width: 2px; }
        @media print {
            .rcv-bar { border: 1px solid #999; background: #fff; }
            .rcv-bar > span { background: #666; }
        }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;
    use Modules\Purchases\Support\ReportPeriod;

    $q = request()->except(['page']);
    $isProducts = $viewType !== 'lines';

    $money = fn ($v) => CurrencySettings::number($v);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.') ?: '0';
    $currency = CurrencySettings::name();
    $periodLabel = $period->labelWithDates();

    $selectedProduct = request('product') ? optional($products->firstWhere('id', (int) request('product')))->name : null;
    $selectedCategory = request('category') ? optional($categories->firstWhere('id', (int) request('category')))->name : null;
    $selectedSupplier = request('supplier') ? optional($suppliers->firstWhere('id', (int) request('supplier')))->trade_name : null;
    $selectedBranch = $selectedNodeLabel ?? null;
    $selectedStore = request('storehouse') ? optional($storehouses->firstWhere('id', (int) request('storehouse')))->name : null;
    $selectedEmployee = request('employee_id') ? optional($users->firstWhere('id', (int) request('employee_id')))->name : null;
    $stateLabels = ['received' => 'مستلم بالكامل', 'partial' => 'مستلم جزئياً', 'pending' => 'لم يُستلم'];
    $selectedState = $stateLabels[request('receipt_state')] ?? null;

    // شريط نسبة الاستلام داخل الصف — يُقرأ أسرع من الرقم وحده
    $bar = fn ($rate) => max(0, min(100, (float) $rate));
    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];
    $rowTone = fn ($code) => $code === 'pending' ? 'rpt-row-return' : ($code === 'partial' ? 'rpt-row-debit_note' : 'rpt-row-purchase');

    /* ─────────── الرسمان ─────────── */
    $topSource = $byProduct->sortByDesc('pending')->filter(fn ($r) => $r['pending'] > 0.009)->take(10);
    $topData = [
        'labels' => $topSource->map(fn ($r) => (string) $r['product_name'])->values()->all(),
        'values' => $topSource->map(fn ($r) => round((float) $r['pending'], 2))->values()->all(),
    ];
    $tPurchased = (float) $totals['purchased'];
    $distLegend = [
        ['label' => 'المستلم', 'value' => (float) $totals['received'], 'color' => '#10b981', 'pct' => $tPurchased > 0 ? round($totals['received'] / $tPurchased * 100) : null],
        ['label' => 'غير المستلم', 'value' => (float) $totals['pending'], 'color' => '#ef4444', 'pct' => $tPurchased > 0 ? round($totals['pending'] / $tPurchased * 100) : null],
    ];
    $distDonut = array_values(array_filter($distLegend, fn ($s) => $s['value'] > 0.009));

    $guide = [
        'title' => 'ما الذي يقيسه هذا التقرير؟',
        'lead' => 'ما اشتريناه مقابل ما دخل المستودع فعلاً — الكمية المستلمة من أذون الاستلام المعتمدة، لا من حالة الفاتورة.',
        'blocks' => [
            'غير المستلم' => 'الكمية المشتراة − المستلم، وقيمتُه بسعر وحدة البند: مالٌ التزمنا به وبضاعةٌ لم تصل.',
            'حالة الاستلام' => 'محسوبةٌ من الكمّيات لا مقروءةٌ من عمود: كامل / جزئي / لم يُستلم.',
            'انحراف المستودع' => 'أصنافٌ رصيدُها يخالف مجموع أذون استلامها — صرفٌ لاحق أو خللٌ في التسجيل.',
        ],
        'facts' => ['«حسب الصنف» يجمع البنود على المنتج، و«حسب البند» يعرض كلَّ بند فاتورةٍ وحدَه.', 'تاريخان صريحان يتقدّمان على زرّ الفترة.'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => 'تقرير المشتريات حسب البند — المستلم وغير المستلم',
        'periodLabel' => $periodLabel,
        'filters' => [
            'الصنف' => $selectedProduct,
            'التصنيف' => $selectedCategory,
            'المورد' => $selectedSupplier,
            'المستودع' => $selectedStore,
            'تابع لـ' => $selectedBranch,
            'الموظف' => $selectedEmployee,
            'حالة الاستلام' => $selectedState,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'dolly',
            'headTitle' => 'المشتريات حسب البند',
            'headSubtitle' => 'ما اشتريناه مقابل ما دخل المستودع فعلاً — الكمية المستلمة من أذون الاستلام المعتمدة، لا من حالة الفاتورة.',
            'headPeriod' => $periodLabel,
            'headExtra' => $isProducts ? 'حسب الصنف' : 'حسب البند',
            'headGuideKey' => 'purchases-reports.receiving',
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
            <form method="GET" action="{{ route('ReportsPurchases.receiving') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period->key }}">
                <input type="hidden" name="view_type" value="{{ $viewType }}">

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
                                <option value="{{ $product->id }}" @selected((string) request('product') === (string) $product->id)>{{ $product->name }}</option>
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
                        <label for="storehouse">المستودع</label>
                        <select name="storehouse" id="storehouse" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($storehouses as $storehouse)
                                <option value="{{ $storehouse->id }}" @selected((string) request('storehouse') === (string) $storehouse->id)>{{ $storehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">الموظف</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('employee_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        {{-- الحالة محسوبة من الكميات لا مقروءة من عمود --}}
                        <label for="receipt_state">حالة الاستلام <small class="rpt-zero">(محسوبة)</small></label>
                        <select name="receipt_state" id="receipt_state" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($stateLabels as $code => $label)
                                <option value="{{ $code }}" @selected(request('receipt_state') === $code)>{{ $label }}</option>
                            @endforeach
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
                        <button type="submit" name="view_type" value="products" class="btn {{ $isProducts ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-cubes"></i> حسب الصنف
                        </button>
                        <button type="submit" name="view_type" value="lines" class="btn {{ ! $isProducts ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-th-list"></i> حسب البند
                        </button>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.receiving') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">الكمية المشتراة</div>
                <div class="ag-kpi__value"><bdi>{{ $qty($totals['purchased']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['invoices_count'] }} فاتورة · {{ $totals['products_count'] }} صنف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-shopping-cart"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">المستلم</div>
                <div class="ag-kpi__value"><bdi class="rpt-pos">{{ $qty($totals['received']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['rate'] }}% من المشترى</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-dolly"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">غير المستلم</div>
                <div class="ag-kpi__value"><bdi class="rpt-neg">{{ $qty($totals['pending']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ 100 - $totals['rate'] }}% لم يصل بعد</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#9f1239">
            <div>
                <div class="ag-kpi__label">قيمة غير المستلم</div>
                <div class="ag-kpi__value"><bdi class="rpt-neg">{{ $money($totals['pending_value']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · مالٌ التزمنا به وبضاعةٌ لم تصل</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-coins"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">حالات البنود</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['partial_count'] }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['full_count'] }} كامل · {{ $totals['partial_count'] }} جزئي · {{ $totals['none_count'] }} لم يصل</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-layer-group"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">انحراف المستودع</div>
                <div class="ag-kpi__value"><bdi class="{{ $totals['stock_mismatch'] ? 'rpt-neg' : 'rpt-pos' }}">{{ $totals['stock_mismatch'] }}</bdi></div>
                {{-- الرصيد رقمٌ حاضر — كلّ ما جرى على الصنف حتى الآن — والمستلَم
                     محصورٌ بالفترة المختارة، فبيعٌ أو نقلٌ بعد الاستلام يُظهر
                     فارقاً هنا ليس خطأً في القيد. يُقرأ حرفياً فترةً على كامل
                     تاريخ الصنف وحدها. --}}
                <div class="ag-kpi__sub">أصناف رصيدها الحالي يخالف مستلَم هذه الفترة وحدها</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
    </div>

    {{-- ===================== معادلة الاستلام ===================== --}}
    <div class="rpt-equation">
        <div class="rpt-equation__item"><small>الكمية المشتراة</small><strong>{{ $qty($totals['purchased']) }}</strong></div>
        <div class="rpt-equation__op">−</div>
        <div class="rpt-equation__item"><small>المستلم</small><strong class="rpt-pos">{{ $qty($totals['received']) }}</strong></div>
        <div class="rpt-equation__op">=</div>
        <div class="rpt-equation__item"><small>غير المستلم</small><strong class="rpt-neg">{{ $qty($totals['pending']) }}</strong></div>
        <div class="rpt-equation__op">|</div>
        <div class="rpt-equation__item"><small>قيمة المستلم</small><strong class="rpt-pos">{{ $money($totals['received_value']) }}</strong></div>
        <div class="rpt-equation__op">+</div>
        <div class="rpt-equation__item"><small>قيمة غير المستلم</small><strong class="rpt-neg">{{ $money($totals['pending_value']) }}</strong></div>
        <div class="rpt-equation__op">=</div>
        <div class="rpt-equation__item"><small>قيمة البنود</small><strong>{{ $money($totals['amount']) }}</strong></div>
    </div>

    @if ($totals['over_received'] > 0.5)
        <div class="alert alert-warning" role="status">
            <i class="fas fa-exclamation-triangle"></i>
            أذون استلام تتجاوز كمّيات فواتيرها بمقدار <strong>{{ $qty($totals['over_received']) }}</strong> وحدة —
            يُعرض المستلم مساوياً للمشترى في تلك البنود، والزيادة تحتاج مراجعةً في المخزن.
        </div>
    @endif

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> أعلى الأصناف كميةً غير مستلمة</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($topData['labels']))
                    <div class="ag-canvas"><canvas id="topChart" aria-label="أعلى الأصناف كميةً غير مستلمة" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا كمّيات معلّقة في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> المستلم مقابل غير المستلم</h4>
            </div>
            <div class="rpt-card__body">
                @if ($tPurchased > 0.009)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($distLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $qty($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($distDonut))
                            <div class="ag-donut">
                                <canvas id="distDonut" aria-label="المستلم مقابل غير المستلم" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $qty($tPurchased) }}</strong>
                                    <small>الكمية المشتراة</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا توجد بنود مشتريات في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-{{ $isProducts ? 'cubes' : 'th-list' }}"></i>
                {{ $isProducts ? 'ملخص الأصناف' : 'بنود الفواتير' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isProducts ? $byProduct->count().' صنف' : $lines->count().' بند' }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <a href="{{ route('ReportsPurchases.receivingExport', $q) }}" class="btn btn-sm ag-btn-soft" title="تصدير CSV خام"><i class="fas fa-file-csv"></i> CSV</a>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll">
            @if ($isProducts)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>الكود</th>
                            <th>الصنف</th>
                            <th>التصنيف</th>
                            <th>الفواتير</th>
                            <th>المشترى</th>
                            <th>المستلم</th>
                            <th>غير المستلم</th>
                            <th style="width:14%">نسبة الاستلام</th>
                            <th>قيمة غير المستلم</th>
                            <th>رصيد المستودع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($byProduct as $row)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-num">{{ $row['product_code'] }}</td>
                                <td class="rpt-name">{{ $row['product_name'] }}</td>
                                <td>{{ $row['category_name'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['invoices'] }}</span></td>
                                <td class="rpt-num">{{ $qty($row['purchased']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $qty($row['received']) }}</td>
                                <td class="rpt-num {{ $row['pending'] > 0.009 ? 'rpt-neg' : 'rpt-zero' }}">
                                    <strong>{{ $row['pending'] > 0.009 ? $qty($row['pending']) : '—' }}</strong>
                                </td>
                                <td>
                                    <div class="rcv-bar" title="{{ $row['rate'] }}%">
                                        <span style="width: {{ $bar($row['rate']) }}%"></span>
                                    </div>
                                    <small class="rpt-zero">{{ $row['rate'] }}%</small>
                                </td>
                                <td class="rpt-num {{ $row['pending_value'] > 0.009 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['pending_value'] > 0.009 ? $money($row['pending_value']) : '—' }}
                                </td>
                                <td class="rpt-num">
                                    {{ $qty($row['on_hand']) }}
                                    @if (abs($row['stock_gap']) > 0.5)
                                        {{-- الرصيد يخالف أذون الاستلام: إمّا صرفٌ لاحق أو خللٌ في التسجيل --}}
                                        <small class="d-block rpt-neg" style="font-size:.68rem">فارق {{ $qty($row['stock_gap']) }}</small>
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-{{ $row['state']['class'] }}">{{ $row['state']['text'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد بنود مشتريات في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($byProduct->count())
                        <tfoot>
                            <tr>
                                <td colspan="4">الإجمالي العام</td>
                                <td>{{ $totals['invoices_count'] }}</td>
                                <td class="rpt-num">{{ $qty($totals['purchased']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $qty($totals['received']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $qty($totals['pending']) }}</td>
                                <td>{{ $totals['rate'] }}%</td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['pending_value']) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:95px">التاريخ</th>
                            <th>الفاتورة</th>
                            <th>المورد</th>
                            <th>تابع لـ</th>
                            <th>الكود</th>
                            <th style="width:16%">الصنف</th>
                            <th>المشترى</th>
                            <th>المستلم</th>
                            <th>غير المستلم</th>
                            <th>سعر الوحدة</th>
                            <th>قيمة غير المستلم</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($lines as $row)
                            <tr class="{{ $rowTone($row['state']['code']) }}" data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-num">{{ $row['date'] }}</td>
                                <td><strong>{{ $row['invoice_code'] }}</strong></td>
                                <td class="rpt-name">{{ $row['supplier_name'] }}</td>
                                <td>{{ $row['branch_name'] }}</td>
                                <td class="rpt-num">{{ $row['product_code'] }}</td>
                                <td class="rpt-name">{{ $row['product_name'] }}</td>
                                <td class="rpt-num">{{ $qty($row['purchased']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $qty($row['received']) }}</td>
                                <td class="rpt-num {{ $row['pending'] > 0.009 ? 'rpt-neg' : 'rpt-zero' }}">
                                    <strong>{{ $row['pending'] > 0.009 ? $qty($row['pending']) : '—' }}</strong>
                                </td>
                                <td class="rpt-num">{{ $money($row['unit_price']) }}</td>
                                <td class="rpt-num {{ $row['pending_value'] > 0.009 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['pending_value'] > 0.009 ? $money($row['pending_value']) : '—' }}
                                </td>
                                <td><span class="rpt-pill rpt-pill-{{ $row['state']['class'] }}">{{ $row['state']['text'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد بنود مطابقة في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($lines->count())
                        <tfoot>
                            <tr>
                                <td colspan="7">الإجمالي العام</td>
                                <td class="rpt-num">{{ $qty($totals['purchased']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $qty($totals['received']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $qty($totals['pending']) }}</td>
                                <td></td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['pending_value']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $isProducts ? 'صنف' : 'بند',
            'pagerStorage' => 'agPurchasesReceivingPageSize',
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
                        data: { labels: TOP.labels, datasets: [{ data: TOP.values, backgroundColor: '#ef4444', borderRadius: 6, maxBarThickness: 48 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agWhole(c.parsed.y) + ' وحدة'; } } } },
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
                                tooltip: { rtl: true, callbacks: { label: function (c) { const s = DONUT[c.dataIndex]; return ' ' + agWhole(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)'); } } }
                            }
                        }
                    });
                }
            });
        })();
    </script>

    {{-- التصديرُ يُبنى في الخادم من بنود الفواتير — كلُّ الصفوف لا الصفحةُ المعروضة --}}
    @include('purchases::purchases.report.partials.excel', [
        'fileName' => 'المشتريات_حسب_البند_الاستلام',
        'mainTitle' => 'تقرير المشتريات حسب البند — المستلم وغير المستلم',
        'info' => [
            'الفترة' => $periodLabel,
            'الصنف' => $selectedProduct ?: 'الكل',
            'المورد' => $selectedSupplier ?: 'الكل',
            'المستودع' => $selectedStore ?: 'الكل',
            'تابع لـ' => $selectedBranch ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'الكمية المشتراة', 'value' => $qty($totals['purchased']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'المستلم', 'value' => $qty($totals['received']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'غير المستلم', 'value' => $qty($totals['pending']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'قيمة غير المستلم', 'value' => $money($totals['pending_value']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
        ],
        'headers' => ['#', 'التاريخ', 'الفاتورة', 'المورد', 'تابع لـ', 'الكود', 'الصنف', 'التصنيف', 'المشترى', 'المستلم', 'غير المستلم', 'سعر الوحدة', 'قيمة غير المستلم', 'الحالة'],
        'widths' => [6, 14, 16, 26, 16, 14, 26, 18, 12, 12, 14, 14, 18, 16],
        'rows' => $lines->values()->map(fn ($r, $i) => [
            'values' => [
                $i + 1, $r['raw_date'], $r['invoice_code'], $r['supplier_name'], $r['branch_name'],
                (string) $r['product_code'], $r['product_name'], $r['category_name'],
                $r['purchased'], $r['received'], $r['pending'],
                $r['unit_price'], $r['pending_value'], $r['state']['text'],
            ],
            'alignments' => [null, null, null, 'right', null, null, 'right'],
        ])->all(),
        'summaryRows' => $lines->count() ? [[
            'values' => [
                '', '', 'الإجمالي العام', '', '', '', '', '',
                $totals['purchased'], $totals['received'], $totals['pending'],
                '', $totals['pending_value'], '',
            ],
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [],
    ])
@endsection
