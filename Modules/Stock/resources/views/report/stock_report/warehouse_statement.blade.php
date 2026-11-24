@extends('stock::master')

@section('title', 'كشف حساب مستودع')

@section('content')
    @php
        $movements = $statement['movements'] ?? [];
        $selectedWarehouse = request('warehouse', optional($warehouse ?? null)->id ?? optional($warehouses->first())->id);
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'كشف حساب مستودع',
            'icon' => 'file-text',
            'sub' => 'رصيد افتتاحي وحركات وارد وصادر ورصيد ختامي لمستودع واحد',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'كشف حساب مستودع' => null,
            ],
            'actions' => $statement
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
                <form id="statementForm" method="GET" class="row">
                    <div class="col-md-3 col-sm-6">
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

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="brand">العلامة التجارية</label>
                            <select id="brand" name="brand" class="form-control select2">
                                <option value="">كل العلامات</option>
                                @foreach ($brands as $item)
                                    <option value="{{ $item }}" {{ request('brand') == $item ? 'selected' : '' }}>
                                        {{ $item }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="permission_source">مصدر الإذن</label>
                            <select id="permission_source" name="permission_source" class="form-control select2">
                                <option value="">كل المصادر</option>
                                @foreach ($permissionSources as $sourceId => $sourceName)
                                    <option value="{{ $sourceId }}" {{ request('permission_source') == $sourceId ? 'selected' : '' }}>
                                        {{ $sourceName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- الفترة المحاسبية: اختيارها يملأ تاريخَي التقرير --}}
                    @include('partials.accounting-period-filter', [
                        'fromName' => 'start_date',
                        'toName' => 'end_date',
                        'colClass' => 'col-md-3 col-sm-6',
                    ])

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="start_date">من تاريخ</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="end_date">إلى تاريخ</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="{{ request('end_date', now()->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> عرض الكشف
                                </button>
                                <a href="{{ route('storehouse.statement') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-rotate-ccw"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if ($statement)
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-archive"></i></span>
                    <span class="stk-metric__value stk-num">{{ number_format($statement['opening_balance'], 2) }}</span>
                    <span class="stk-metric__label">رصيد افتتاحي</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                    <span class="stk-metric__value stk-num text-success">{{ number_format($statement['total_incoming'], 2) }}</span>
                    <span class="stk-metric__label">إجمالي الوارد</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                    <span class="stk-metric__value stk-num text-danger">{{ number_format($statement['total_outgoing'], 2) }}</span>
                    <span class="stk-metric__label">إجمالي المنصرف</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                    <span class="stk-metric__value stk-num">{{ number_format($statement['closing_balance'], 2) }}</span>
                    <span class="stk-metric__label">الرصيد الختامي</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                    <span class="stk-metric__value stk-num">{{ number_format($statement['current_stock'], 2) }}</span>
                    <span class="stk-metric__label">الرصيد الفعلي الآن</span>
                </div>
            </div>

            <div class="card stk-table-card">
                {{-- ترويسة تظهر عند الطباعة وحدها --}}
                <div class="stk-print-head">
                    <h3>كشف حساب مستودع — {{ $statement['warehouse']->name ?? 'غير محدد' }}</h3>
                    <p>
                        @if ($statement['start_date'] && $statement['end_date'])
                            من {{ \Illuminate\Support\Carbon::parse($statement['start_date'])->format('Y-m-d') }}
                            إلى {{ \Illuminate\Support\Carbon::parse($statement['end_date'])->format('Y-m-d') }}
                        @else
                            كل الفترات
                        @endif
                        — طُبع في {{ now()->format('Y-m-d H:i') }}
                    </p>
                </div>

                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-activity"></i> تفاصيل الحركة
                        <span class="stk-count">{{ count($movements) }}</span>
                    </h6>
                    <span class="stk-balance stk-num">{{ number_format($statement['closing_balance'], 2) }}</span>
                </div>

                @if (count($movements) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="reportTable">
                            <thead>
                                <tr>
                                    <th style="width:44px">#</th>
                                    <th style="width:9%">التاريخ</th>
                                    <th style="width:16%">العملية</th>
                                    <th>العميل</th>
                                    <th style="width:11%">الموظف</th>
                                    <th>المنتج</th>
                                    <th class="text-center" style="width:8%">قبل</th>
                                    <th class="text-center" style="width:9%">الوارد</th>
                                    <th class="text-center" style="width:9%">المنصرف</th>
                                    <th class="text-center" style="width:8%">بعد</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($movements as $index => $movement)
                                    <tr>
                                        <td class="stk-num text-muted">{{ $index + 1 }}</td>
                                        <td class="stk-num">
                                            {{ \Illuminate\Support\Carbon::parse($movement['date'])->format('Y-m-d') }}
                                        </td>
                                        <td>
                                            <div class="stk-media__body">
                                                <span class="stk-media__title">
                                                    {{ $movement['type'] }}
                                                    @if ($movement['is_damaged'])
                                                        <span class="stk-state stk-state--neutral text-danger">تالف</span>
                                                    @endif
                                                </span>
                                                @if ($movement['reference'])
                                                    <span class="stk-media__sub stk-num">#{{ $movement['reference'] }}</span>
                                                @endif
                                                @if ($movement['exchange_reason'])
                                                    <span class="stk-media__sub">{{ $movement['exchange_reason'] }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if ($movement['client_trade_name'])
                                                <div class="stk-media__body">
                                                    <span class="stk-media__title">{{ $movement['client_trade_name'] }}</span>
                                                    <span class="stk-media__sub stk-num">{{ $movement['client_code'] }}</span>
                                                </div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $movement['user_name'] ?? '—' }}</td>
                                        <td>{{ $movement['product_name'] }}</td>
                                        <td class="text-center stk-num text-muted">
                                            {{ number_format($movement['balance_before'], 2) }}
                                        </td>
                                        <td class="text-center stk-num">
                                            @if ($movement['incoming'] > 0)
                                                <span class="stk-move--success">+{{ number_format($movement['incoming'], 2) }}</span>
                                            @else
                                                <span class="stk-move--muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center stk-num">
                                            @if ($movement['outgoing'] > 0)
                                                <span class="stk-move--danger">−{{ number_format($movement['outgoing'], 2) }}</span>
                                            @else
                                                <span class="stk-move--muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center stk-num">
                                            <strong>{{ number_format($movement['balance_after'], 2) }}</strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="stk-total-row">
                                    <td colspan="7">الإجمالي</td>
                                    <td class="text-center stk-num">+{{ number_format($statement['total_incoming'], 2) }}</td>
                                    <td class="text-center stk-num">−{{ number_format($statement['total_outgoing'], 2) }}</td>
                                    <td class="text-center stk-num">{{ number_format($statement['closing_balance'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                            <h5 class="stk-empty__title">لا حركات في الفترة المحدّدة</h5>
                            <p class="stk-empty__hint">وسّع الفترة أو خفّف الفلاتر</p>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-home"></i></div>
                        <h5 class="stk-empty__title">اختر مستودعاً لعرض الكشف</h5>
                        <p class="stk-empty__hint">حدّد المستودع والفترة من الفلاتر أعلاه</p>
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
                fileName: 'كشف_حساب_مستودع',
                title: 'كشف حساب مستودع',
                subTitle: @json($statement['warehouse']->name ?? ''),
                numericColumns: [0, 6, 7, 8, 9],
                cards: [
                    { title: 'رصيد افتتاحي', value: @json(number_format($statement['opening_balance'] ?? 0, 2)) },
                    { title: 'إجمالي الوارد', value: @json(number_format($statement['total_incoming'] ?? 0, 2)), color: 'success', borderColor: 'success' },
                    { title: 'إجمالي المنصرف', value: @json(number_format($statement['total_outgoing'] ?? 0, 2)), color: 'danger', borderColor: 'danger' },
                    { title: 'الرصيد الختامي', value: @json(number_format($statement['closing_balance'] ?? 0, 2)) },
                ],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 5,
            });
        });
    </script>

    <script>
        $(function () {
            if ($.fn.select2) {
                $('#statementForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%', allowClear: true });
            }
        });
    </script>
@endsection
