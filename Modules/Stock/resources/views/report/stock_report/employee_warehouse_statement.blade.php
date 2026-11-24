@extends('stock::master')

@section('title', 'الكشف المخزني')

@section('content')
    @php
        $seesAll = $seesAllWarehouses ?? false;
        $movements = $statement['movements'] ?? [];
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'الكشف المخزني',
            'icon' => 'file-text',
            'sub' => $seesAll
                ? 'حركة أي مستودع في النظام: رصيد افتتاحي ووارد وصادر ورصيد ختامي'
                : 'حركة مستودعاتك: رصيد افتتاحي ووارد وصادر ورصيد ختامي',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'الكشف المخزني' => null,
            ],
            'actions' => '<button type="button" id="printReport" class="btn btn-outline-secondary">'
                . '<i class="feather icon-printer"></i> طباعة</button>'
                . '<button type="button" id="exportExcel" class="btn btn-outline-secondary">'
                . '<i class="feather icon-download"></i> تصدير Excel</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @if (isset($error))
            <div class="card">
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-home"></i></div>
                        <h5 class="stk-empty__title">{{ $error }}</h5>
                        <p class="stk-empty__hint">
                            {{ $seesAll ? 'أضف مستودعاً من شاشة المستودعات' : 'راجع مسؤول المستودعات لربط مستودع بحسابك' }}
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-filter"></i> نطاق الكشف</h4>
                    @if ($seesAll)
                        <span class="stk-state stk-state--neutral">كل المستودعات</span>
                    @endif
                </div>

                <div class="card-body">
                    <form action="{{ route('storehouse.employee_statement') }}" method="GET" class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="warehouse">المستودع</label>
                                <select id="warehouse" name="warehouse" class="form-control select2" onchange="this.form.submit()">
                                    @foreach ($warehouses as $item)
                                        <option value="{{ $item->id }}"
                                                {{ optional($warehouse)->id == $item->id ? 'selected' : '' }}>
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

                        {{-- الفترة المحاسبية: اختيارها يملأ تاريخَي التقرير --}}
                        @include('partials.accounting-period-filter', [
                            'fromName' => 'start_date',
                            'toName' => 'end_date',
                            'colClass' => 'col-md-2 col-sm-6',
                        ])

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="start_date">من تاريخ</label>
                                <input type="date" id="start_date" name="start_date" class="form-control"
                                       value="{{ request('start_date') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="end_date">إلى تاريخ</label>
                                <input type="date" id="end_date" name="end_date" class="form-control"
                                       value="{{ request('end_date') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label class="d-block">&nbsp;</label>
                                <div class="d-flex" style="gap:.4rem">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="feather icon-search"></i> عرض
                                    </button>
                                    <a href="{{ route('storehouse.employee_statement') }}" class="btn btn-outline-secondary">
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
                        <span class="stk-metric__label">إجمالي الصادر</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                        <span class="stk-metric__value stk-num">{{ number_format($statement['closing_balance'], 2) }}</span>
                        <span class="stk-metric__label">الرصيد الختامي</span>
                    </div>
                </div>

                <div class="card stk-table-card">
                    <div class="stk-print-head">
                        <h3>كشف حساب مستودع</h3>
                        <p>
                            {{ optional($warehouse)->name }} —
                            من {{ request('start_date') ?: '…' }} إلى {{ request('end_date') ?: '…' }}
                        </p>
                    </div>

                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-activity"></i> الحركات
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
                                        <th style="width:10%">التاريخ</th>
                                        <th>المنتج</th>
                                        <th>الجهة</th>
                                        <th style="width:12%">النوع</th>
                                        <th class="text-center" style="width:9%">قبل</th>
                                        <th class="text-center" style="width:9%">وارد</th>
                                        <th class="text-center" style="width:9%">صادر</th>
                                        <th class="text-center" style="width:9%">بعد</th>
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
                                                    <span class="stk-media__title">{{ $movement['product_name'] }}</span>
                                                    @if ($movement['reference'])
                                                        <span class="stk-media__sub stk-num">#{{ $movement['reference'] }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if ($movement['transfer_info'])
                                                    <span class="stk-media__sub">
                                                        {{ $movement['transfer_info']['from'] }}
                                                        <i class="feather icon-arrow-left"></i>
                                                        {{ $movement['transfer_info']['to'] }}
                                                    </span>
                                                @else
                                                    {{ $movement['client_trade_name'] ?: '—' }}
                                                @endif

                                                @if ($movement['exchange_reason'])
                                                    <span class="stk-media__sub">{{ $movement['exchange_reason'] }}</span>
                                                @endif
                                            </td>
                                            <td><span class="stk-state stk-state--neutral">{{ $movement['type'] }}</span></td>
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
                                        <td colspan="6">الإجماليات</td>
                                        <td class="text-center stk-num">{{ number_format($statement['total_incoming'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($statement['total_outgoing'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($statement['closing_balance'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                <h5 class="stk-empty__title">لا حركات في هذه الفترة</h5>
                                <p class="stk-empty__hint">جرّب توسيع المدّة أو اختيار مستودع آخر</p>
                            </div>
                        </div>
                    @endif

                    {{-- سطرا التوقيع يظهران في الورقة المطبوعة --}}
                    <div class="card-body d-flex" style="gap:3rem">
                        <div class="flex-grow-1">
                            <span class="stk-writein"></span>
                            <span class="stk-help">توقيع أمين المستودع</span>
                        </div>
                        <div class="flex-grow-1">
                            <span class="stk-writein"></span>
                            <span class="stk-help">اعتماد المشرف</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-home"></i></div>
                            <h5 class="stk-empty__title">اختر مستودعاً</h5>
                            <p class="stk-empty__hint">حدّد المستودع والفترة ثم اضغط «عرض»</p>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#reportTable',
                fileName: 'الكشف_المخزني',
                title: 'الكشف المخزني',
                subTitle: @json(optional($warehouse ?? null)->name ?? ''),
                numericColumns: [0, 5, 6, 7, 8],
                cards: [
                    { title: 'رصيد افتتاحي', value: @json(number_format($statement['opening_balance'] ?? 0, 2)) },
                    { title: 'إجمالي الوارد', value: @json(number_format($statement['total_incoming'] ?? 0, 2)), color: 'success', borderColor: 'success' },
                    { title: 'إجمالي الصادر', value: @json(number_format($statement['total_outgoing'] ?? 0, 2)), color: 'danger', borderColor: 'danger' },
                    { title: 'الرصيد الختامي', value: @json(number_format($statement['closing_balance'] ?? 0, 2)) },
                ],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 2,
            });
        });
    </script>

    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }
        });
    </script>
@endsection
