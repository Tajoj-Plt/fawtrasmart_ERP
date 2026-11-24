@extends('stock::master')

@section('title', 'الحركة التفصيلية المبسّطة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'الحركة التفصيلية المبسّطة',
            'icon' => 'list',
            'sub' => 'عرض مختصر وسريع لحركات المخزون — قابل للتصدير والطباعة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'الحركة التفصيلية المبسّطة' => null,
            ],
            'actions' => '<a href="' . route('StockReport.exportDetailedMovement', request()->query()) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-download"></i> إكسل</a>'
                . '<button type="button" id="printReport" class="btn btn-outline-secondary">'
                . '<i class="feather icon-printer"></i> طباعة</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-filter"></i> خيارات البحث</h4>
                <span class="stk-count stk-num">
                    {{ $startDate->format('Y-m-d') }} → {{ $endDate->format('Y-m-d') }}
                </span>
            </div>

            <div class="card-body">
                <form id="filterForm" method="GET" action="{{ route('StockReport.simpleDetailedMovement') }}" class="row">
                    @include('partials.accounting-period-filter', [
                        'fromName' => 'start_date',
                        'toName' => 'end_date',
                        'colClass' => 'col-md-4 col-sm-6',
                    ])

                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="warehouse">المستودع</label>
                            <select id="warehouse" name="warehouse" class="form-control select2">
                                <option value="">كل المستودعات</option>
                                @foreach ($warehouses as $item)
                                    <option value="{{ $item->id }}" {{ request('warehouse') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث شامل</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="الاسم أو الكود أو رقم الإذن…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="d-flex" style="gap:.4rem">
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-search"></i> بحث
                            </button>
                            <a href="{{ route('StockReport.simpleDetailedMovement') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card" id="reportCard">
            <div class="stk-print-head">
                <h3>الحركة التفصيلية المبسّطة</h3>
                <p>
                    من {{ $startDate->format('Y-m-d') }} إلى {{ $endDate->format('Y-m-d') }}
                    — طُبع في {{ now()->format('Y-m-d H:i') }}
                </p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-activity"></i> الحركات
                    <span class="stk-count">{{ count($movements) }}</span>
                </h6>
            </div>

            @if (count($movements) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="movementsTable">
                        <thead>
                            <tr>
                                <th style="width:12%">التاريخ</th>
                                <th style="width:10%">رقم الإذن</th>
                                <th style="width:10%">الكود</th>
                                <th>الصنف</th>
                                <th style="width:9%">الاتجاه</th>
                                <th class="text-center" style="width:9%">الكمية</th>
                                <th style="width:12%">المستودع</th>
                                <th style="width:10%">المسؤول</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($movements as $item)
                                @php $isIncoming = $item['direction'] === 'in'; @endphp
                                <tr>
                                    <td class="stk-num">{{ \Illuminate\Support\Carbon::parse($item['date'])->format('Y-m-d H:i') }}</td>
                                    <td class="stk-num">{{ $item['permit_number'] }}</td>
                                    <td class="stk-num">{{ $item['product_code'] ?? '—' }}</td>
                                    <td><span class="stk-media__title">{{ $item['product_name'] ?? '—' }}</span></td>
                                    <td>
                                        @if ($isIncoming)
                                            <span class="badge badge-success">وارد</span>
                                        @else
                                            <span class="badge badge-danger">منصرف</span>
                                        @endif
                                    </td>
                                    <td class="text-center stk-num">{{ number_format($item['quantity'], 2) }}</td>
                                    <td>{{ $item['warehouse_name'] }}</td>
                                    <td>{{ $item['user_name'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                        <h5 class="stk-empty__title">لا حركات للعرض</h5>
                        <p class="stk-empty__hint">غيّر معايير البحث أو وسّع الفترة</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            if ($.fn.select2) { $('.select2').select2(); }

            stkReportTools({
                table: '#movementsTable',
                title: 'الحركة التفصيلية المبسّطة',
                subTitle: '{{ $startDate->format('Y-m-d') }} → {{ $endDate->format('Y-m-d') }}',
                numericColumns: [5],
                printButton: '#printReport',
            });
        });
    </script>
@endsection
