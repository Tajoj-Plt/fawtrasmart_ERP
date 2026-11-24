{{--
    المشترك بين «مبيعات البنود» و«مبيعات الأصناف»: الترويسة، وشبكة الفلترة،
    والبطاقات، والرسمان. يُستدعى مرّةً واحدة في الصفحة (لا داخل حلقة).

    @param string $screenKind   'lines' | 'products'
    @param string $reportTitle
    @param string $reportAction
    @param string $periodLabel
    @param array  $chartData    ['labels' => [], 'values' => []]
    @param string $chartTitle
    والباقي من بيانات المتحكّم (`totals`, القوائم، `groupBy`…).
--}}
@php
    $icMoney = fn ($v) => \App\Support\CurrencySettings::number($v);
    $icSigned = fn ($v) => ($v < -0.005 ? '−' : '').\App\Support\CurrencySettings::number(abs($v));
    $icQty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $icCurrency = \App\Support\CurrencySettings::name();
    $icIsProducts = $screenKind === 'products';

    $icReturnsTotal = ($totals['returns_amount'] ?? 0) + ($totals['credit_notes_amount'] ?? 0);
    $icMix = [
        ['label' => __('sales_reports.columns.net_sales'), 'value' => (float) ($totals['net_sales'] ?? 0), 'color' => '#8b5cf6'],
        ['label' => 'المرتجعات', 'value' => (float) ($totals['returns_amount'] ?? 0), 'color' => '#ef4444'],
        ['label' => 'إشعارات دائنة', 'value' => (float) ($totals['credit_notes_amount'] ?? 0), 'color' => '#06b6d4'],
    ];
    $icSales = (float) ($totals['sales_amount'] ?? 0);
    foreach ($icMix as $i => $s) {
        $icMix[$i]['pct'] = $icSales > 0.005 ? round($s['value'] / $icSales * 100) : null;
    }
    $icMixDonut = array_values(array_filter($icMix, fn ($s) => $s['value'] > 0.005));
@endphp

<div class="no-print">
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => $icIsProducts ? 'boxes' : 'list-ul',
        'headTitle' => $reportTitle,
        'headSubtitle' => $icIsProducts
            ? 'مجمَّع: سطرٌ لكلّ صنف — أو تصنيفٍ أو مستودعٍ أو عميل — بكمّيته ومبالغه.'
            : 'تفصيليّ: سطرٌ لكلّ بندٍ في فاتورةٍ أو مرتجعٍ أو إشعارٍ دائن، بكمّيته وسعره ومبالغه.',
        'headPeriod' => $periodLabel,
        'headExtra' => $icIsProducts ? 'التجميع: '.($groupByOptions[$groupBy] ?? '') : null,
        'headGuideKey' => $icIsProducts ? 'sales-reports.by-product' : 'sales-reports.by-item',
        'headCrumbLabel' => __('sales_reports.index.title'),
        'headCrumbUrl' => route('salesReports.index'),
    ])
</div>

@include('layouts.alerts.success')
@include('layouts.alerts.error')

{{-- ===================== الفلترة ===================== --}}
<div class="rpt-card rpt-filters no-print">
    <div class="rpt-card__body">
        <form method="GET" action="{{ $reportAction }}" id="filterForm">

            @include('sales::sals_report.report_partials.period-tabs')

            <div class="ag-filter-grid">
                <div class="rpt-field">
                    <label for="date_from">الفترة التاريخية</label>
                    <div class="ag-range">
                        <input type="date" name="date_from" id="date_from" class="form-control" aria-label="{{ __('sales_reports.filters.from_date') }}"
                               value="{{ request('date_from', $fromDate->format('Y-m-d')) }}">
                        <span class="ag-range__sep">إلى</span>
                        <input type="date" name="date_to" id="date_to" class="form-control" aria-label="{{ __('sales_reports.filters.to_date') }}"
                               value="{{ request('date_to', $toDate->format('Y-m-d')) }}">
                    </div>
                </div>

                <div class="rpt-field">
                    <label for="product_id">الصنف</label>
                    <select name="product_id" id="product_id" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.common.all') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}{{ $product->barcode ? ' — '.$product->barcode : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rpt-field">
                    <label for="product_group_id">{{ __('sales_reports.group_by.product_group') }}</label>
                    <select name="product_group_id" id="product_group_id" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.common.all') }}</option>
                        @foreach ($productGroups as $group)
                            <option value="{{ $group->id }}" @selected((string) request('product_group_id') === (string) $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rpt-field">
                    <label for="category_id">تصنيف الصنف</label>
                    <select name="category_id" id="category_id" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.common.all') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rpt-field">
                    <label for="unit_id">{{ __('sales_reports.filters.unit') }}</label>
                    <select name="unit_id" id="unit_id" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.common.all') }}</option>
                        @foreach ($unitsList as $unit)
                            <option value="{{ $unit->id }}" @selected((string) request('unit_id') === (string) $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rpt-field">
                    <label for="storehouse_id">المستودع</label>
                    <select name="storehouse_id" id="storehouse_id" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.common.all') }}</option>
                        @foreach ($storehouses as $storehouse)
                            <option value="{{ $storehouse->id }}" @selected((string) request('storehouse_id') === (string) $storehouse->id)>{{ $storehouse->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rpt-field">
                    <label for="item_type">نوع البنود</label>
                    <select name="item_type" id="item_type" class="form-control rpt-select2">
                        <option value="" @selected(request('item_type') == '')>الكل (المنتجات والهدايا)</option>
                        <option value="normal" @selected(request('item_type') == 'normal')>المنتجات العادية فقط</option>
                        <option value="gift" @selected(request('item_type') == 'gift')>الهدايا فقط</option>
                    </select>
                </div>

                <div class="rpt-field">
                    <label for="employee_id">{{ __('sales_reports.filters.employee') }}</label>
                    <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.common.all') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) request('employee_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rpt-field">
                    @include('reports.partials.client-select', [
                        'name' => 'client_id',
                        'id' => 'client_id',
                        'url' => $icIsProducts ? route('salesReports.byProductNew.searchClients') : route('salesReports.byItemNew.searchClients'),
                        'selectedClient' => $selectedClient ?? null,
                        'label' => __('sales_reports.filters.client'),
                        'placeholder' => __('sales_reports.common.all'),
                    ])
                </div>

                <div class="rpt-field">
                    <label for="status">{{ __('sales_reports.filters.client_status') }}</label>
                    <select name="status" id="status" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.filters.all_statuses') }}</option>
                        <option value="no_status" @selected(request('status') == 'no_status')>{{ __('sales_reports.filters.no_status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((string) request('status') === (string) $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rpt-field">
                    <label for="registration_stage">{{ __('sales_reports.filters.client_stage') }}</label>
                    <select name="registration_stage" id="registration_stage" class="form-control rpt-select2">
                        <option value="">{{ __('sales_reports.filters.all_stages') }}</option>
                        <option value="no_stage" @selected(request('registration_stage') == 'no_stage')>{{ __('sales_reports.filters.no_stage') }}</option>
                        @foreach ($registrationStages as $stage)
                            <option value="{{ $stage->id }}" @selected((string) request('registration_stage') === (string) $stage->id)>{{ $stage->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($icIsProducts)
                    <div class="rpt-field">
                        <label for="group_by">{{ __('sales_reports.filters.group_by') }}</label>
                        <select name="group_by" id="group_by" class="form-control rpt-select2">
                            @foreach ($groupByOptions as $key => $label)
                                <option value="{{ $key }}" @selected($groupBy == $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="ag-span-all">
                    <div class="row">
                        @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $effectiveBranchId ?? null])
                    </div>
                </div>
            </div>

            <div class="ag-filter-foot">
                <div class="btn-group ag-view-toggle" role="group" aria-label="طريقة العرض">
                    <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="fas fa-list"></i> {{ __('sales_reports.common.summary') }}
                    </button>
                    <button type="submit" name="view_type" value="details" class="btn {{ ! $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                        <i class="fas fa-th-list"></i> {{ __('sales_reports.common.details') }}
                    </button>
                </div>
                <div class="ag-quick__actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                    <a href="{{ $reportAction }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> {{ __('sales_reports.common.reset') }}</a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ===================== المؤشّرات — رقمٌ واحدٌ لكلّ بطاقة، على أساس «المبيعات حسب العميل» ===================== --}}
<div class="ag-kpis">
    <div class="ag-kpi" style="--c:#3b82f6">
        <div>
            <div class="ag-kpi__label">صافي الكمية المباعة</div>
            <div class="ag-kpi__value"><bdi>{{ $icQty($totals['net_base_qty'] ?? 0) }}</bdi></div>
            <div class="ag-kpi__sub">بالوحدة الأساسية، بعد المرتجع</div>
        </div>
        <span class="ag-kpi__icon"><i class="fas fa-cubes"></i></span>
    </div>
    <div class="ag-kpi" style="--c:#10b981">
        <div>
            <div class="ag-kpi__label">{{ __('sales_reports.columns.sales_total') }}</div>
            <div class="ag-kpi__value"><bdi>{{ $icMoney($totals['sales_amount'] ?? 0) }}</bdi></div>
            <div class="ag-kpi__sub">{{ $totals['count'] ?? 0 }} فاتورة</div>
        </div>
        <span class="ag-kpi__icon"><i class="fas fa-file-invoice-dollar"></i></span>
    </div>
    <div class="ag-kpi" style="--c:#ef4444">
        <div>
            <div class="ag-kpi__label">المرتجعات والإشعارات الدائنة</div>
            <div class="ag-kpi__value"><bdi>{{ $icMoney($icReturnsTotal) }}</bdi></div>
            <div class="ag-kpi__sub">{{ ($totals['returns_count'] ?? 0) + ($totals['credit_notes_count'] ?? 0) }} مستند</div>
        </div>
        <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
    </div>
    <div class="ag-kpi" style="--c:#8b5cf6">
        <div>
            <div class="ag-kpi__label">{{ __('sales_reports.columns.net_sales') }}</div>
            <div class="ag-kpi__value"><bdi class="{{ ($totals['net_sales'] ?? 0) < -0.005 ? 'rpt-neg' : '' }}">{{ $icSigned($totals['net_sales'] ?? 0) }}</bdi></div>
            <div class="ag-kpi__sub">المبيعات − المرتجعات والإشعارات</div>
        </div>
        <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
    </div>
</div>

{{-- ===================== الرسمان ===================== --}}
<div class="ag-charts no-print">
    <div class="rpt-card">
        <div class="rpt-card__head"><h4><i class="fas fa-chart-bar"></i> {{ $chartTitle }}</h4></div>
        <div class="rpt-card__body">
            @if (count($chartData['labels']))
                <div class="ag-canvas"><canvas id="groupsChart" aria-label="{{ $chartTitle }}" role="img"></canvas></div>
            @else
                <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا توجد حركة بنود في {{ $periodLabel }}</div>
            @endif
        </div>
    </div>
    <div class="rpt-card">
        <div class="rpt-card__head"><h4><i class="fas fa-chart-pie"></i> تركيب المبيعات</h4></div>
        <div class="rpt-card__body">
            @if ($icSales > 0.005)
                <div class="ag-dist">
                    <table class="ag-legend">
                        <tbody>
                            @foreach ($icMix as $slice)
                                <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                    <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                    <td class="ag-legend__amount"><bdi>{{ $icSigned($slice['value']) }}</bdi></td>
                                    <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (count($icMixDonut))
                        <div class="ag-donut">
                            <canvas id="mixDonut" aria-label="تركيب المبيعات" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ $icMoney($icSales) }}</strong>
                                <small>{{ __('sales_reports.columns.sales_total') }} · {{ $icCurrency }}</small>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا توجد حركة بنود في {{ $periodLabel }}</div>
            @endif
        </div>
    </div>
</div>

<script>
    window.ITEM_REPORT_CHARTS = { bars: @json($chartData), donut: @json($icMixDonut) };
</script>
