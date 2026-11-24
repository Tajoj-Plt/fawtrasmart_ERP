@extends('stock::master')

@section('title', 'تقرير التالف والسليم')

@section('content')
    @php
        $items = $report['items'] ?? [];
        $summary = $report['summary'] ?? [];
        $selectedWarehouse = request('warehouse', optional($warehouse ?? null)->id ?? optional($warehouses->first())->id);
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'التالف والسليم',
            'icon' => 'alert-triangle',
            'sub' => 'تفصيل الكميات التالفة مقابل السليمة وقيمة كلٍّ منهما',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'التالف والسليم' => null,
            ],
            'actions' => $report
                ? '<button type="button" id="printReport" class="btn btn-outline-secondary">'
                . '<i class="feather icon-printer"></i> طباعة</button>'
                . '<button type="button" id="exportExcel" class="btn btn-outline-secondary">'
                . '<i class="feather icon-download"></i> تصدير Excel</button>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-filter"></i> خيارات البحث</h4>
            </div>

            <div class="card-body">
                <form id="reportForm" method="GET" class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="warehouse" class="stk-req">المستودع</label>
                            <select id="warehouse" name="warehouse" required class="form-control select2">
                                <option value="">اختر المستودع…</option>
                                @foreach ($warehouses as $item)
                                    <option value="{{ $item->id }}" {{ $selectedWarehouse == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="product">المنتج</label>
                            <select id="product" name="product" class="form-control select2">
                                <option value="">كل المنتجات</option>
                                @foreach ($products as $item)
                                    <option value="{{ $item->id }}" {{ request('product') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}@if ($item->serial_number) — {{ $item->serial_number }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="category">الفئة</label>
                            <select id="category" name="category" class="form-control select2">
                                <option value="">كل الفئات</option>
                                @foreach ($categories as $item)
                                    <option value="{{ $item->id }}" {{ request('category') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> عرض
                                </button>
                                <a href="{{ route('storehouse.damaged_stock') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-rotate-ccw"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if ($report)
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                    <span class="stk-metric__value stk-num">{{ number_format($summary['total_quantity']) }}</span>
                    <span class="stk-metric__label">إجمالي المخزون</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__value stk-num text-success">{{ number_format($summary['total_good']) }}</span>
                    <span class="stk-metric__label">سليم</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                    <span class="stk-metric__value stk-num text-danger">{{ number_format($summary['total_damaged']) }}</span>
                    <span class="stk-metric__label">تالف — {{ $summary['damaged_percentage'] }}%</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                    <span class="stk-metric__value stk-num">@moneyPlain($summary['total_good_value'])</span>
                    <span class="stk-metric__label">قيمة السليم</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                    <span class="stk-metric__value stk-num">@moneyPlain($summary['total_damaged_value'])</span>
                    <span class="stk-metric__label">قيمة التالف</span>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-print-head">
                    <h3>تقرير التالف والسليم — {{ $report['warehouse']->name ?? 'غير محدد' }}</h3>
                    <p>طُبع في {{ now()->format('Y-m-d H:i') }}</p>
                </div>

                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-package"></i> تفاصيل المنتجات
                        <span class="stk-count">{{ count($items) }}</span>
                    </h6>
                </div>

                @if (count($items) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="reportTable">
                            <thead>
                                <tr>
                                    <th style="width:44px">#</th>
                                    <th>المنتج</th>
                                    <th style="width:14%">الفئة</th>
                                    <th class="text-center" style="width:13%">الرصيد الكلي</th>
                                    <th class="text-center" style="width:13%">السليم</th>
                                    <th class="text-center" style="width:13%">التالف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $index => $item)
                                    <tr>
                                        <td class="stk-num text-muted">{{ $index + 1 }}</td>
                                        <td>
                                            <div class="stk-media__body">
                                                <span class="stk-media__title">{{ $item['product_name'] }}</span>
                                                @if ($item['product_code'])
                                                    <span class="stk-media__sub stk-num">#{{ $item['product_code'] }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $item['category_name'] ?? '—' }}</td>
                                        <td class="text-center stk-num"><strong>{{ number_format($item['total_quantity']) }}</strong></td>
                                        <td class="text-center stk-num">
                                            <span class="stk-move--success">{{ number_format($item['good_quantity']) }}</span>
                                        </td>
                                        <td class="text-center stk-num">
                                            @if ($item['damaged_quantity'] > 0)
                                                <span class="stk-move--danger">{{ number_format($item['damaged_quantity']) }}</span>
                                            @else
                                                <span class="stk-move--muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                {{-- كان التذييل يخرج عشرة أعمدة تحت ترويسةٍ من سبعة فينزاح الجدول --}}
                                <tr class="stk-total-row">
                                    <td colspan="3">المجموع</td>
                                    <td class="text-center stk-num">{{ number_format($summary['total_quantity']) }}</td>
                                    <td class="text-center stk-num">{{ number_format($summary['total_good']) }}</td>
                                    <td class="text-center stk-num">{{ number_format($summary['total_damaged']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                            <h5 class="stk-empty__title">لا منتجات في هذا المستودع</h5>
                            <p class="stk-empty__hint">جرّب مستودعاً آخر أو خفّف الفلاتر</p>
                        </div>
                    </div>
                @endif

                <div class="card-body d-flex" style="gap:2rem">
                    @foreach (['أعدّه', 'راجعه', 'اعتمده'] as $role)
                        <div class="flex-grow-1">
                            <span class="stk-writein"></span>
                            <span class="stk-help">{{ $role }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-home"></i></div>
                        <h5 class="stk-empty__title">اختر مستودعاً</h5>
                        <p class="stk-empty__hint">حدّد المستودع من الفلاتر أعلاه لعرض التقرير</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#reportTable',
                fileName: 'تقرير_التالف_والسليم',
                title: 'تقرير التالف والسليم',
                subTitle: @json($report['warehouse']->name ?? ''),
                numericColumns: [0, 3, 4, 5],
                cards: [
                    { title: 'إجمالي المخزون', value: @json(number_format($summary['total_quantity'] ?? 0)) },
                    { title: 'السليم', value: @json(number_format($summary['total_good'] ?? 0)), color: 'success', borderColor: 'success' },
                    { title: 'التالف', value: @json(number_format($summary['total_damaged'] ?? 0)), color: 'danger', borderColor: 'danger' },
                ],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 1,
            });
        });
    </script>

    <script>
        $(function () {
            if ($.fn.select2) {
                $('#reportForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }
        });
    </script>
@endsection
