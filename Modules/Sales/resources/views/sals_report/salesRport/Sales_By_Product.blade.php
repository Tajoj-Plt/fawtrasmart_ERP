@extends('sales::master')

@section('title')
    تقرير المبيعات حسب المنتج
@stop

{{--
    ملاحظة: القسم هنا `css` لا `styles`.
    `sales::master` يمرّر `@yield('css')` عبر `layouts.head_rtl`، أمّا
    `styles` فمكدَّس (`@stack`) لا يلتقط الأقسام — فكان كل ما يوضع في
    `@section('styles')` يسقط صامتاً ولا يصل إلى الصفحة أصلاً.

    ملاحظة ثانية: هذه الشاشة لا يستدعيها أي متحكّم اليوم — مسار
    `salesReports.byProductNew` يمرّ بـ`SalesByProductController` الذي يعرض
    `Sales_By_Item` بعنوانٍ آخر، ومسار `salesReports.byProduct` يعرض
    `by_Product`. أُعيد تنسيقها هنا لتبقى متّسقة إن أُعيد ربطها لاحقاً.
--}}
@section('css')
    @include('sales::sals_report.report_partials.styles')
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;

    $money = fn ($v) => CurrencySettings::number($v);
    $qty = fn ($v) => number_format((float) $v, 0);

    $periodLabel = 'من '.$fromDate->format('d/m/Y').' إلى '.$toDate->format('d/m/Y');

    $selectedProduct = request('product_id') ? optional($products->firstWhere('id', (int) request('product_id')))->name : null;
    $selectedCategory = request('category_id') ? optional($categories->firstWhere('id', (int) request('category_id')))->name : null;
    $selectedStore = request('storehouse_id') ? optional($storehouses->firstWhere('id', (int) request('storehouse_id')))->name : null;
    $selectedClient = request('client_id') ? optional($clients->firstWhere('id', (int) request('client_id')))->trade_name : null;
    $selectedEmployee = request('employee_id') ? optional($users->firstWhere('id', (int) request('employee_id')))->name : null;
    $selectedBranchName = request('branch_id') ? optional($branches->firstWhere('id', (int) request('branch_id')))->name : null;
@endphp

@include('layouts.alerts.success')
@include('layouts.alerts.error')

<div class="rpt">

    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => 'تقرير المبيعات حسب المنتج — '.($isSummary ? 'ملخص' : 'كشف تفصيلي'),
        'periodLabel' => $periodLabel,
        'filters' => [
            'المنتج' => $selectedProduct,
            'التصنيف' => $selectedCategory,
            'المستودع' => $selectedStore,
            'العميل' => $selectedClient,
            'الموظف' => $selectedEmployee,
            'الفرع' => $selectedBranchName,
        ],
    ])

    @include('sales::sals_report.report_partials.hero', [
        'guideKey' => 'sales-reports.by-product',
        'icon' => 'cube',
        'title' => 'المبيعات حسب المنتج',
        'subtitle' => 'حركة كل منتج خلال الفترة — كمّيةً وقيمةً وخصماً، مجمّعةً ثم مفصّلةً بفواتيرها.',
        'periodLabel' => $periodLabel,
    ])

    {{-- ===================== الفلاتر ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__head">
            <h4><i class="fa fa-filter"></i> خيارات البحث</h4>
        </div>
        <div class="rpt-card__body">

            <form method="GET" action="{{ route('salesReports.byProductNew') }}" id="filterForm">

                @include('sales::sals_report.report_partials.period-tabs')

                {{-- البحث الهرمي بالشجرة — تُبنى قوائمه ديناميكياً --}}
                <input type="hidden" name="cascade_node" id="cascade_node" value="{{ request('cascade_node') }}">
                <div class="row" id="cascadeContainer"></div>

                <div class="row">
                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>من تاريخ</label>
                        <input type="date" name="date_from" id="date_from" class="form-control"
                               value="{{ request('date_from', $fromDate->format('Y-m-d')) }}">
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>إلى تاريخ</label>
                        <input type="date" name="date_to" id="date_to" class="form-control"
                               value="{{ request('date_to', $toDate->format('Y-m-d')) }}">
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>المنتج</label>
                        <select name="product_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->code ?? '' }} - {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>تصنيف المنتج</label>
                        <select name="category_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>المستودع</label>
                        <select name="storehouse_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($storehouses as $storehouse)
                                <option value="{{ $storehouse->id }}" {{ request('storehouse_id') == $storehouse->id ? 'selected' : '' }}>
                                    {{ $storehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>العميل</label>
                        <select name="client_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                    {{ $client->code ?? '' }} - {{ $client->trade_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>الموظف</label>
                        <select name="employee_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ request('employee_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-6 rpt-field">
                        <label>الفرع</label>
                        <select name="branch_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @include('sales::sals_report.report_partials.actions', [
                    'resetUrl' => route('salesReports.byProductNew'),
                    'isSummary' => $isSummary,
                ])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشرات ===================== --}}
    <div class="rpt-kpis">
        <div class="rpt-kpi" style="--k:var(--app-primary,#7367f0)">
            <div class="rpt-kpi__label"><i class="fa fa-cubes"></i> المنتجات</div>
            <div class="rpt-kpi__value">{{ count($reportData) }}</div>
            <div class="rpt-kpi__sub">منتج له حركة</div>
        </div>
        <div class="rpt-kpi" style="--k:var(--app-info,#00cfe8)">
            <div class="rpt-kpi__label"><i class="fa fa-file-invoice"></i> عدد الفواتير</div>
            <div class="rpt-kpi__value">{{ $totals['count'] ?? 0 }}</div>
            <div class="rpt-kpi__sub">مستند في الفترة</div>
        </div>
        <div class="rpt-kpi" style="--k:var(--app-primary,#7367f0)">
            <div class="rpt-kpi__label"><i class="fa fa-boxes"></i> إجمالي الكمية</div>
            <div class="rpt-kpi__value">{{ $qty($totals['quantity'] ?? 0) }}</div>
            <div class="rpt-kpi__sub">وحدة مباعة</div>
        </div>
        <div class="rpt-kpi" style="--k:var(--app-success,#28c76f)">
            <div class="rpt-kpi__label"><i class="fa fa-chart-line"></i> إجمالي المبيعات</div>
            <div class="rpt-kpi__value rpt-pos">{{ $money($totals['amount'] ?? 0) }}</div>
            <div class="rpt-kpi__sub">قيمة البنود</div>
        </div>
        <div class="rpt-kpi" style="--k:var(--app-warning,#ff9f43)">
            <div class="rpt-kpi__label"><i class="fa fa-percent"></i> إجمالي الخصومات</div>
            <div class="rpt-kpi__value rpt-warnc">{{ $money($totals['discount'] ?? 0) }}</div>
            <div class="rpt-kpi__sub">خصمٌ على البنود</div>
        </div>
    </div>

    @if ($isSummary)
        {{-- ===================== الملخص ===================== --}}
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fa fa-table"></i> ملخص المبيعات حسب المنتج</h4>
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} منتج</span>
            </div>
            <div class="rpt-scroll">
                <table class="rpt-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:120px">كود المنتج</th>
                            <th style="width:26%">المنتج</th>
                            <th>التصنيف</th>
                            <th>عدد الفواتير</th>
                            <th>الكمية</th>
                            <th>إجمالي المبيعات</th>
                            <th>الخصم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reportData as $productId => $data)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-num">{{ $data['code'] ?? '-' }}</td>
                                <td class="rpt-name">{{ $data['name'] ?: 'غير محدد' }}</td>
                                <td>{{ $data['category'] ?? '-' }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $data['count'] }}</span></td>
                                <td class="rpt-num"><strong>{{ $qty($data['quantity']) }}</strong></td>
                                <td class="rpt-num rpt-pos"><strong>{{ $money($data['amount']) }}</strong></td>
                                <td class="rpt-num {{ ($data['discount'] ?? 0) != 0 ? 'rpt-warnc' : 'rpt-zero' }}">
                                    {{ ($data['discount'] ?? 0) != 0 ? $money($data['discount']) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty">
                                <i class="fa fa-inbox"></i>
                                لا توجد حركة منتجات في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if (count($reportData) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="4">الإجمالي العام</td>
                                <td>{{ $totals['count'] }}</td>
                                <td class="rpt-num">{{ $qty($totals['quantity']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['amount']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($totals['discount']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @else
        {{-- ===================== الكشف التفصيلي ===================== --}}
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fa fa-th-list"></i> تفاصيل المبيعات حسب المنتج</h4>
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} منتج</span>
            </div>
            <div class="rpt-scroll">
                <table class="rpt-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>رقم الفاتورة</th>
                            <th style="width:100px">التاريخ</th>
                            <th style="width:20%">المنتج</th>
                            <th>العميل</th>
                            <th>الفرع</th>
                            <th>المستودع</th>
                            <th>الكمية</th>
                            <th>السعر</th>
                            <th>الخصم</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $globalRowNum = 0; @endphp
                        @forelse ($reportData as $productId => $data)
                            <tr class="rpt-group">
                                <td colspan="11">
                                    <i class="fa fa-cube"></i>
                                    <strong>{{ $data['code'] ?? '' }} — {{ $data['name'] ?: 'غير محدد' }}</strong>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $data['count'] }} فاتورة</span>
                                    <span class="rpt-pill rpt-pill-primary">{{ $qty($data['quantity']) }} وحدة</span>
                                </td>
                            </tr>

                            @foreach ($data['items'] as $entry)
                                @php $globalRowNum++; @endphp
                                <tr>
                                    <td>{{ $globalRowNum }}</td>
                                    <td><strong>{{ $entry['reference_number'] ?? $entry['invoice_id'] }}</strong></td>
                                    <td class="rpt-num">{{ $entry['date'] }}</td>
                                    <td class="rpt-name">
                                        {{ $entry['product_name'] ?? '-' }}
                                        <small>كود: {{ $entry['product_code'] ?? '-' }}</small>
                                    </td>
                                    <td>{{ $entry['client_code'] ?? '' }} {{ $entry['client_name'] ?? '-' }}</td>
                                    <td>{{ $entry['branch_name'] ?? '-' }}</td>
                                    <td>{{ $entry['storehouse_name'] ?? '-' }}</td>
                                    <td class="rpt-num"><strong>{{ $qty($entry['quantity'] ?? 0) }}</strong></td>
                                    <td class="rpt-num">{{ $money($entry['unit_price'] ?? 0) }}</td>
                                    <td class="rpt-num {{ ($entry['discount'] ?? 0) != 0 ? 'rpt-warnc' : 'rpt-zero' }}">
                                        {{ ($entry['discount'] ?? 0) != 0 ? $money($entry['discount']) : '—' }}
                                    </td>
                                    <td class="rpt-num rpt-pos"><strong>{{ $money($entry['amount'] ?? 0) }}</strong></td>
                                </tr>
                            @endforeach

                            <tr class="rpt-subtotal">
                                <td colspan="7" class="rpt-name">إجمالي {{ $data['name'] ?: 'غير محدد' }}</td>
                                <td class="rpt-num"><strong>{{ $qty($data['quantity']) }}</strong></td>
                                <td class="rpt-zero">—</td>
                                <td class="rpt-num rpt-warnc"><strong>{{ $money($data['discount']) }}</strong></td>
                                <td class="rpt-num rpt-pos"><strong>{{ $money($data['amount']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="rpt-empty">
                                <i class="fa fa-inbox"></i>
                                لا توجد حركة منتجات في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if (count($reportData) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="7">الإجمالي العام</td>
                                <td class="rpt-num">{{ $qty($totals['quantity']) }}</td>
                                <td class="rpt-zero">—</td>
                                <td class="rpt-num rpt-warnc">{{ $money($totals['discount']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['amount']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('sales::sals_report.report_partials.scripts', ['cascadeBranches' => $cascadeBranches ?? []])

    @php
        if ($isSummary) {
            $excelHeaders = ['#', 'كود المنتج', 'المنتج', 'التصنيف', 'عدد الفواتير', 'الكمية', 'إجمالي المبيعات', 'الخصم'];
            $excelWidths = [6, 16, 30, 20, 14, 14, 20, 16];
            $excelRows = collect($reportData)->values()->map(fn ($r, $i) => [
                'values' => [
                    $i + 1,
                    (string) ($r['code'] ?? '-'),
                    $r['name'] ?: 'غير محدد',
                    $r['category'] ?? '-',
                    $r['count'] ?? 0,
                    $r['quantity'] ?? 0,
                    $r['amount'] ?? 0,
                    $r['discount'] ?? 0,
                ],
                'alignments' => [null, null, 'right'],
            ])->all();
            $excelSummary = count($reportData) ? [[
                'values' => [
                    '', '', 'الإجمالي العام', '', $totals['count'] ?? 0,
                    $totals['quantity'] ?? 0, $totals['amount'] ?? 0, $totals['discount'] ?? 0,
                ],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = ['#', 'رقم الفاتورة', 'التاريخ', 'كود المنتج', 'المنتج', 'العميل', 'الفرع', 'المستودع', 'الكمية', 'السعر', 'الخصم', 'الإجمالي'];
            $excelWidths = [6, 14, 12, 14, 26, 22, 16, 18, 12, 14, 14, 16];
            $excelRows = [];
            $n = 0;
            foreach ($reportData as $r) {
                foreach (($r['items'] ?? []) as $entry) {
                    $excelRows[] = [
                        'values' => [
                            ++$n,
                            (string) ($entry['reference_number'] ?? $entry['invoice_id'] ?? '-'),
                            $entry['date'],
                            (string) ($entry['product_code'] ?? '-'),
                            $entry['product_name'] ?? '-',
                            $entry['client_name'] ?? '-',
                            $entry['branch_name'] ?? '-',
                            $entry['storehouse_name'] ?? '-',
                            $entry['quantity'] ?? 0,
                            $entry['unit_price'] ?? 0,
                            $entry['discount'] ?? 0,
                            $entry['amount'] ?? 0,
                        ],
                        'alignments' => [null, null, null, null, 'right'],
                    ];
                }
            }
            $excelSummary = $excelRows ? [[
                'values' => [
                    '', 'الإجمالي العام', '', '', '', '', '', '',
                    $totals['quantity'] ?? 0, '', $totals['discount'] ?? 0, $totals['amount'] ?? 0,
                ],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => 'المبيعات_حسب_المنتج'.($isSummary ? '_ملخص' : '_تفصيلي'),
        'mainTitle' => 'تقرير المبيعات حسب المنتج'.($isSummary ? ' — ملخص' : ' — تفصيلي'),
        'info' => [
            'الفترة' => $periodLabel,
            'المنتج' => $selectedProduct ?: 'الكل',
            'التصنيف' => $selectedCategory ?: 'الكل',
            'المستودع' => $selectedStore ?: 'الكل',
            'العميل' => $selectedClient ?: 'الكل',
            'الموظف' => $selectedEmployee ?: 'الكل',
            'الفرع' => $selectedBranchName ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'عدد الفواتير', 'value' => $totals['count'] ?? 0, 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي الكمية', 'value' => $qty($totals['quantity'] ?? 0), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المبيعات', 'value' => $money($totals['amount'] ?? 0), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'إجمالي الخصومات', 'value' => $money($totals['discount'] ?? 0), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningBg', 'borderColor' => 'warningLight'],
        ],
        'headers' => $excelHeaders,
        'widths' => $excelWidths,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
